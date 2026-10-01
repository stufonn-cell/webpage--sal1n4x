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
use PsiClinic\Domain\AppointmentRequests;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Support\Present;

/** Bandeja de solicitudes que llegan desde el sitio publico. */
final class AppointmentRequestController extends Controller
{
    public function index(Request $request): void
    {
        $this->ok(Present::page(AppointmentRequests::paginate($request->string('status'), max(1, $request->integer('page', 1)))));
    }

    public function update(Request $request, string $id): void
    {
        $row = $this->abortIfMissing(AppointmentRequests::find((int) $id), 'No encontramos esta solicitud.');
        $status = $this->oneOf($request->string('status'), AppointmentRequests::STATUSES, (string) $row['status']);

        Database::update('appointment_requests', (int) $row['id'], [
            'status' => $status,
            'handled_by' => Auth::id(),
            'handled_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record('status:' . $status, 'appointment_request', (int) $row['id']);

        $this->message(__('Solicitud marcada como %s.', mb_strtolower(__(AppointmentRequests::STATUSES[$status]))), ['status' => $status]);
    }
}
