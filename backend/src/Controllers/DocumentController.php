<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Env;
use PsiClinic\Core\HttpException;
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

    public function store(Request $request): void
    {
        $patientId = $request->integer('patient_id');
        $file = $request->file('document');

        $errors = [];
        if (Patients::find($patientId) === null) {
            $errors['patient_id'] = 'Elige un paciente.';
        }
        if ($file === null) {
            $errors['document'] = 'Adjunta un archivo.';
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('Falta información para adjuntar el documento.', $errors);
        }

        $maxSize = Env::int('UPLOAD_MAX_SIZE', 10485760);
        if ((int) $file['size'] > $maxSize) {
            throw HttpException::unprocessable('El archivo es demasiado grande.', [
                'document' => __('El tamaño máximo es %s.', Documents::humanSize($maxSize)),
            ]);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!array_key_exists($mime, Documents::ALLOWED_MIME)) {
            throw HttpException::unprocessable('Ese tipo de archivo no está permitido.', [
                'document' => 'Usa PDF, imagen (PNG o JPG), texto o Word.',
            ]);
        }

        $storedName = uuid() . '.' . Documents::ALLOWED_MIME[$mime];
        $directory = $this->uploadPath();

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            error_log('No se pudo crear el directorio de documentos: ' . $directory);
            throw new HttpException(500, 'No pudimos guardar el archivo. Intenta de nuevo en unos minutos.');
        }

        if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $storedName)) {
            error_log('Fallo move_uploaded_file para ' . $storedName);
            throw new HttpException(500, 'No pudimos guardar el archivo. Intenta de nuevo en unos minutos.');
        }

        $title = mb_substr($request->string('title'), 0, 180);

        $id = Database::insert('documents', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'title' => $title !== '' ? $title : mb_substr((string) $file['name'], 0, 180),
            'category' => $this->oneOf($request->string('category'), Documents::CATEGORIES, 'general'),
            'stored_name' => $storedName,
            'original_name' => mb_substr(basename((string) $file['name']), 0, 180),
            'mime_type' => $mime,
            'size_bytes' => (int) $file['size'],
            'uploaded_by' => Auth::id(),
        ]);
        AuditLog::record('upload', 'document', $id);

        $this->created(['id' => $id], 'Documento adjuntado.');
    }

    public function download(Request $request, string $id): void
    {
        $document = $this->abortIfMissing(Documents::find((int) $id), 'No encontramos este documento.');

        if (!Auth::isStaff() && (int) $document['patient_id'] !== Auth::patientId()) {
            throw HttpException::notFound('No encontramos este documento.');
        }

        $path = $this->uploadPath() . '/' . basename((string) $document['stored_name']);

        if (!is_file($path)) {
            throw HttpException::notFound('El archivo ya no está disponible.');
        }

        AuditLog::record('download', 'document', (int) $document['id']);
        Response::download($path, (string) $document['original_name'], (string) $document['mime_type']);
    }

    public function destroy(Request $request, string $id): void
    {
        $document = $this->abortIfMissing(Documents::find((int) $id), 'No encontramos este documento.');
        $path = $this->uploadPath() . '/' . basename((string) $document['stored_name']);

        if (is_file($path)) {
            unlink($path);
        }

        Database::delete('documents', (int) $document['id']);
        AuditLog::record('delete', 'document', (int) $document['id']);

        $this->message('Documento eliminado.');
    }

    private function uploadPath(): string
    {
        return dirname(__DIR__, 2) . '/' . trim((string) Env::get('UPLOAD_PATH', 'storage/uploads'), '/');
    }
}
