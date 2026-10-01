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
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\ConsentTemplates;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Present;
use PsiClinic\Support\Signature;

final class ConsentController extends Controller
{
    public function index(Request $request): void
    {
        $this->ok(array_map(
            static fn (array $row): array => Present::consent($row, true),
            Database::all(
                'SELECT c.id, c.patient_id, c.template_code, c.title, c.status, c.signed_name, c.signed_at,
                        c.created_at, p.first_name, p.last_name, p.record_number
                 FROM consents c
                 JOIN patients p ON p.id = c.patient_id
                 ORDER BY c.created_at DESC
                 LIMIT 100'
            )
        ));
    }

    public function store(Request $request): void
    {
        $patientId = $request->integer('patient_id');
        $code = $request->string('template_code');
        $template = ConsentTemplates::get($code);

        if (Patients::find($patientId) === null || $template === null) {
            throw HttpException::unprocessable('Selecciona un paciente y una plantilla.');
        }

        $id = Database::insert('consents', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'template_code' => $code,
            'title' => $template['title'],
            'body' => $template['body'],
            'created_by' => Auth::id(),
        ]);
        AuditLog::record('create', 'consent', $id);

        $this->created(['id' => $id], 'Consentimiento generado. El paciente ya puede firmarlo desde su portal.');
    }

    public function show(Request $request, string $id): void
    {
        $consent = $this->findAccessible((int) $id);
        AuditLog::record('view', 'consent', (int) $consent['id']);

        $this->ok(Present::consent($consent, Auth::isStaff()));
    }

    public function sign(Request $request, string $id): void
    {
        $consent = $this->findAccessible((int) $id);

        if ($consent['status'] !== 'pending') {
            throw HttpException::conflict('Este consentimiento ya fue firmado.');
        }

        $signedName = mb_substr($request->string('signed_name'), 0, 160);
        $signature = Signature::fromStrokes($request->array('strokes'));

        $errors = [];
        if ($signedName === '') {
            $errors['signed_name'] = 'Escribe tu nombre completo.';
        }
        if ($signature === '') {
            $errors['strokes'] = 'Dibuja tu firma en el recuadro.';
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('Falta completar la firma.', $errors);
        }

        Database::update('consents', (int) $consent['id'], [
            'status' => 'signed',
            'signed_name' => $signedName,
            'signature_svg' => $signature,
            'signed_at' => date('Y-m-d H:i:s'),
            'signed_ip' => $request->ip(),
        ]);
        AuditLog::record('sign', 'consent', (int) $consent['id']);

        $this->message('Consentimiento firmado. Gracias.');
    }

    /**
     * El equipo ve cualquier consentimiento; un paciente solo los suyos. Antes
     * bastaba con cambiar el id en la URL para ver o firmar el de otra persona.
     */
    private function findAccessible(int $id): array
    {
        $consent = $this->abortIfMissing(Database::first(
            'SELECT c.*, p.first_name, p.last_name, p.record_number, p.document_id
             FROM consents c JOIN patients p ON p.id = c.patient_id WHERE c.id = :id',
            ['id' => $id]
        ), 'No encontramos este consentimiento.');

        if (!Auth::isStaff() && (int) $consent['patient_id'] !== Auth::patientId()) {
            throw HttpException::notFound('No encontramos este consentimiento.');
        }

        return $consent;
    }
}
