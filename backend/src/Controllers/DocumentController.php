<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Env;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Log;
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Present;

final class DocumentController extends Controller
{
    private const HIDDEN = ['uuid', 'stored_name'];

    public function index(Request $request): void
    {
        $this->ok(Present::rows(Documents::recent(), self::HIDDEN));
    }

    /**
     * Upload checks, in order: a real HTTP upload, size, the type detected
     * from the content (finfo, never the browser's claim), an extension that
     * agrees with that type, and a decodable image for PNG/JPG. The file is
     * stored outside the web root under a random name with an extension
     * chosen by the server, so nothing uploaded can ever be executed.
     */
    public function store(Request $request): void
    {
        $patientId = $request->integer('patient_id');
        $file = $request->file('document');
        $maxSize = Env::int('UPLOAD_MAX_SIZE', 10485760);

        if ($file === null && in_array($request->fileError('document'), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw HttpException::unprocessable('The file is too large.', [
                'document' => sprintf('The maximum size is %s.', Documents::humanSize($maxSize)),
            ]);
        }

        $errors = [];
        if (Patients::find($patientId) === null) {
            $errors['patient_id'] = 'Choose a patient.';
        }
        if ($file === null || !is_uploaded_file((string) $file['tmp_name'])) {
            $errors['document'] = 'Attach a file.';
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('Some information is missing to attach the document.', $errors);
        }

        $size = (int) filesize((string) $file['tmp_name']);
        if ($size > $maxSize) {
            throw HttpException::unprocessable('The file is too large.', [
                'document' => sprintf('The maximum size is %s.', Documents::humanSize($maxSize)),
            ]);
        }
        if ($size === 0) {
            throw HttpException::unprocessable('The file is empty.', ['document' => 'Attach a file that has content.']);
        }

        $originalName = Documents::cleanFileName((string) $file['name']);
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!Documents::isAllowed($mime, $originalName, (string) $file['tmp_name'])) {
            throw HttpException::unprocessable("That file type isn't allowed.", [
                'document' => 'Use PDF, an image (PNG or JPG), text or Word.',
            ]);
        }

        $storedName = uuid() . '.' . Documents::ALLOWED_MIME[$mime];
        $directory = $this->uploadPath();

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            Log::error('Could not create the documents directory', ['directory' => $directory]);
            throw new HttpException(500, "We couldn't save the file. Please try again in a few minutes.");
        }

        if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $storedName)) {
            Log::error('move_uploaded_file failed', ['stored_name' => $storedName]);
            throw new HttpException(500, "We couldn't save the file. Please try again in a few minutes.");
        }
        // Readable and writable by the application only; never executable.
        @chmod($directory . '/' . $storedName, 0640);

        $title = mb_substr($request->string('title'), 0, 180);

        $id = Database::insert('documents', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'title' => $title !== '' ? $title : $originalName,
            'category' => $this->oneOf($request->string('category'), Documents::CATEGORIES, 'general'),
            'stored_name' => $storedName,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'uploaded_by' => Auth::id(),
        ]);
        AuditLog::record('upload', 'document', $id);

        $this->created(['id' => $id], 'Document attached.');
    }

    public function download(Request $request, string $id): void
    {
        $document = $this->abortIfMissing(Documents::find((int) $id), "We couldn't find this document.");

        if (!Auth::isStaff() && (int) $document['patient_id'] !== Auth::patientId()) {
            throw HttpException::notFound("We couldn't find this document.");
        }

        $path = $this->uploadPath() . '/' . basename((string) $document['stored_name']);

        if (!is_file($path)) {
            throw HttpException::notFound('The file is no longer available.');
        }

        // Only types accepted at upload are ever announced to the browser.
        $mime = (string) $document['mime_type'];
        if (!array_key_exists($mime, Documents::ALLOWED_MIME)) {
            $mime = 'application/octet-stream';
        }

        AuditLog::record('download', 'document', (int) $document['id']);
        Response::download($path, (string) $document['original_name'], $mime);
    }

    public function destroy(Request $request, string $id): void
    {
        $document = $this->abortIfMissing(Documents::find((int) $id), "We couldn't find this document.");
        $path = $this->uploadPath() . '/' . basename((string) $document['stored_name']);

        if (is_file($path)) {
            unlink($path);
        }

        Database::delete('documents', (int) $document['id']);
        AuditLog::record('delete', 'document', (int) $document['id']);

        $this->message('Document deleted.');
    }

    private function uploadPath(): string
    {
        return dirname(__DIR__, 2) . '/' . trim((string) Env::get('UPLOAD_PATH', 'storage/uploads'), '/');
    }
}
