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
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\ConsentTemplates;
use PsiClinic\Domain\Patients;

final class ConsentController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('patients/consents', [
            'consents' => Database::all(
                'SELECT c.*, p.first_name, p.last_name, p.record_number
                 FROM consents c
                 JOIN patients p ON p.id = c.patient_id
                 ORDER BY c.created_at DESC
                 LIMIT 100'
            ),
            'templates' => ConsentTemplates::all(),
            'patients' => Patients::options(),
        ]);
    }

    public function store(Request $request): void
    {
        $patientId = $request->integer('patient_id');
        $template = ConsentTemplates::get((string) $request->input('template_code', ''));

        if ($patientId <= 0 || $template === null) {
            Session::flash('error', 'Selecciona un paciente y una plantilla.');
            $this->back($request);
        }

        $id = Database::insert('consents', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'template_code' => (string) $request->input('template_code'),
            'title' => $template['title'],
            'body' => $template['body'],
            'created_by' => Auth::id(),
        ]);

        AuditLog::record('create', 'consent', $id);
        Session::flash('success', 'Consentimiento generado y enviado al portal del paciente.');
        $this->back($request);
    }

    public function show(Request $request, string $id): void
    {
        $consent = $this->abortIfMissing(Database::first(
            'SELECT c.*, p.first_name, p.last_name, p.record_number, p.document_id
             FROM consents c JOIN patients p ON p.id = c.patient_id WHERE c.id = :id',
            ['id' => (int) $id]
        ));

        $layout = Auth::is('patient') ? 'portal' : 'app';

        $this->view('patients/consent_show', ['consent' => $consent], $layout);
    }

    public function sign(Request $request, string $id): void
    {
        $consent = $this->abortIfMissing(Database::first('SELECT * FROM consents WHERE id = :id', ['id' => (int) $id]));
        $signedName = trim((string) $request->input('signed_name', ''));

        if ($signedName === '') {
            Session::flash('error', 'Escribe tu nombre completo para firmar.');
            $this->back($request);
        }

        Database::update('consents', (int) $consent['id'], [
            'status' => 'signed',
            'signed_name' => $signedName,
            'signature_svg' => (string) $request->input('signature_svg', ''),
            'signed_at' => date('Y-m-d H:i:s'),
            'signed_ip' => $request->ip(),
        ]);

        AuditLog::record('sign', 'consent', (int) $consent['id']);
        Session::flash('success', 'Consentimiento firmado.');
        $this->redirect('/consentimientos/' . (int) $consent['id']);
    }
}
