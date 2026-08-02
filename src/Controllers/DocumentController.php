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
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;
use PsiClinic\Core\Session;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Patients;

final class DocumentController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('documents/index', [
            'documents' => Documents::recent(),
            'patients' => Patients::options(),
        ]);
    }

    public function store(Request $request): void
    {
        $patientId = $request->integer('patient_id');
        $file = $request->file('document');

        if ($patientId <= 0 || $file === null) {
            Session::flash('error', 'Selecciona un paciente y un archivo valido.');
            $this->back($request);
        }

        $maxSize = Env::int('UPLOAD_MAX_SIZE', 10485760);
        if ((int) $file['size'] > $maxSize) {
            Session::flash('error', 'El archivo supera el tamano maximo permitido.');
            $this->back($request);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!array_key_exists($mime, Documents::ALLOWED_MIME)) {
            Session::flash('error', 'Tipo de archivo no permitido.');
            $this->back($request);
        }

        $storedName = uuid() . '.' . Documents::ALLOWED_MIME[$mime];
        $directory = $this->uploadPath();

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            Session::flash('error', 'No se pudo preparar el directorio de archivos.');
            $this->back($request);
        }

        move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $storedName);

        $id = Database::insert('documents', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'title' => (string) $request->input('title', $file['name']),
            'category' => (string) $request->input('category', 'general'),
            'stored_name' => $storedName,
            'original_name' => substr((string) $file['name'], 0, 180),
            'mime_type' => $mime,
            'size_bytes' => (int) $file['size'],
            'uploaded_by' => Auth::id(),
        ]);

        AuditLog::record('upload', 'document', $id);
        Session::flash('success', 'Documento adjuntado.');
        $this->back($request);
    }

    public function download(Request $request, string $id): void
    {
        $document = $this->abortIfMissing(Documents::find((int) $id));
        $user = Auth::user();

        if ($user !== null && $user['role'] === 'patient' && (int) $user['patient_id'] !== (int) $document['patient_id']) {
            Session::flash('error', 'No tienes acceso a ese documento.');
            $this->redirect('/portal/documentos');
        }

        $path = $this->uploadPath() . '/' . (string) $document['stored_name'];

        if (!is_file($path)) {
            Session::flash('error', 'El archivo ya no esta disponible.');
            $this->back($request);
        }

        AuditLog::record('download', 'document', (int) $document['id']);
        Response::download($path, (string) $document['original_name'], (string) $document['mime_type']);
    }

    public function destroy(Request $request, string $id): void
    {
        $document = $this->abortIfMissing(Documents::find((int) $id));
        $path = $this->uploadPath() . '/' . (string) $document['stored_name'];

        if (is_file($path)) {
            unlink($path);
        }

        Database::delete('documents', (int) $document['id']);
        AuditLog::record('delete', 'document', (int) $document['id']);

        Session::flash('success', 'Documento eliminado.');
        $this->back($request);
    }

    private function uploadPath(): string
    {
        return dirname(__DIR__, 2) . '/' . trim((string) Env::get('UPLOAD_PATH', 'storage/uploads'), '/');
    }
}
