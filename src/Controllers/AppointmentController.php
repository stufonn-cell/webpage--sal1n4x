<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use DateTimeImmutable;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Settings;

final class AppointmentController extends Controller
{
    public function index(Request $request): void
    {
        $reference = $this->reference((string) $request->input('semana', ''));
        $week = Appointments::week($reference);

        $this->view('appointments/index', [
            'week' => $week,
            'reference' => $reference,
            'previous' => $reference->modify('-7 days')->format('Y-m-d'),
            'next' => $reference->modify('+7 days')->format('Y-m-d'),
            'workStart' => Settings::get('working_hours_start', '07:00'),
            'workEnd' => Settings::get('working_hours_end', '19:00'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('appointments/form', [
            'appointment' => null,
            'patients' => Patients::options(),
            'psychologists' => $this->psychologists(),
            'defaultDate' => (string) $request->input('fecha', date('Y-m-d')),
            'defaultTime' => (string) $request->input('hora', '09:00'),
            'duration' => (int) Settings::get('session_duration', '50'),
            'defaultFee' => Settings::get('default_fee', '0'),
        ]);
    }

    public function store(Request $request): void
    {
        $this->validate($request, [
            'patient_id' => 'required|numeric',
            'psychologist_id' => 'required|numeric',
            'date' => 'required|date',
            'time' => 'required',
            'duration' => 'required|numeric',
        ]);

        $payload = $this->payload($request);

        if (Appointments::hasConflict((int) $payload['psychologist_id'], $payload['starts_at'], $payload['ends_at'])) {
            Session::flash('error', 'El profesional ya tiene una cita en ese horario.');
            $this->redirect('/agenda/nueva');
        }

        $id = Database::insert('appointments', $payload + ['uuid' => uuid(), 'created_by' => Auth::id()]);
        AuditLog::record('create', 'appointment', $id);

        Session::flash('success', 'Cita agendada.');
        $this->redirect('/agenda?semana=' . substr($payload['starts_at'], 0, 10));
    }

    public function edit(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id));

        $this->view('appointments/form', [
            'appointment' => $appointment,
            'patients' => Patients::options(),
            'psychologists' => $this->psychologists(),
            'defaultDate' => date('Y-m-d', strtotime((string) $appointment['starts_at'])),
            'defaultTime' => date('H:i', strtotime((string) $appointment['starts_at'])),
            'duration' => (int) round((strtotime((string) $appointment['ends_at']) - strtotime((string) $appointment['starts_at'])) / 60),
            'defaultFee' => (string) $appointment['fee'],
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id));
        $payload = $this->payload($request);

        if (Appointments::hasConflict((int) $payload['psychologist_id'], $payload['starts_at'], $payload['ends_at'], (int) $appointment['id'])) {
            Session::flash('error', 'El profesional ya tiene una cita en ese horario.');
            $this->redirect('/agenda/' . (int) $appointment['id'] . '/editar');
        }

        Database::update('appointments', (int) $appointment['id'], $payload);
        AuditLog::record('update', 'appointment', (int) $appointment['id']);

        Session::flash('success', 'Cita actualizada.');
        $this->redirect('/agenda?semana=' . substr($payload['starts_at'], 0, 10));
    }

    public function changeStatus(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id));
        $status = (string) $request->input('status', 'scheduled');

        if (!array_key_exists($status, Appointments::STATUSES)) {
            $status = 'scheduled';
        }

        Database::update('appointments', (int) $appointment['id'], ['status' => $status]);
        AuditLog::record('status:' . $status, 'appointment', (int) $appointment['id']);

        Session::flash('success', 'Estado de la cita actualizado.');
        $this->back($request);
    }

    public function destroy(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id));

        Database::delete('appointments', (int) $appointment['id']);
        AuditLog::record('delete', 'appointment', (int) $appointment['id']);

        Session::flash('success', 'Cita eliminada.');
        $this->redirect('/agenda');
    }

    private function payload(Request $request): array
    {
        $date = (string) $request->input('date', date('Y-m-d'));
        $time = (string) $request->input('time', '09:00');
        $duration = max(15, $request->integer('duration', 50));

        $starts = new DateTimeImmutable($date . ' ' . $time);
        $ends = $starts->modify('+' . $duration . ' minutes');

        return [
            'patient_id' => $request->integer('patient_id'),
            'psychologist_id' => $request->integer('psychologist_id'),
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'modality' => (string) $request->input('modality', 'in_person'),
            'status' => (string) $request->input('status', 'scheduled'),
            'session_type' => (string) $request->input('session_type', ''),
            'location' => (string) $request->input('location', ''),
            'meeting_url' => (string) $request->input('meeting_url', ''),
            'fee' => (float) $request->input('fee', 0),
            'notes' => (string) $request->input('notes', ''),
        ];
    }

    private function reference(string $value): DateTimeImmutable
    {
        if ($value !== '' && strtotime($value) !== false) {
            return new DateTimeImmutable($value);
        }

        return new DateTimeImmutable('today');
    }

    private function psychologists(): array
    {
        return Database::all(
            'SELECT id, full_name FROM users WHERE role IN ("admin","psychologist") AND is_active = 1 ORDER BY full_name'
        );
    }
}
