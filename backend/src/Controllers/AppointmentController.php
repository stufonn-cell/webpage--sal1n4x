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
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class AppointmentController extends Controller
{
    private const RULES = [
        'patient_id' => 'required|numeric',
        'psychologist_id' => 'required|numeric',
        'date' => 'required|date',
        'time' => 'required',
        'duration' => 'required|numeric',
        'session_type' => 'max:80',
        'location' => 'max:160',
        'meeting_url' => 'max:255',
        'fee' => 'numeric',
        'notes' => 'max:2000',
    ];

    public function index(Request $request): void
    {
        $reference = $this->reference($request->string('week'));
        $psychologistId = $request->integer('psychologist_id') ?: null;
        $week = Appointments::week($reference, $psychologistId);

        $days = [];
        foreach ($week['days'] as $date => $items) {
            $days[] = ['date' => $date, 'appointments' => Present::rows($items)];
        }

        $this->ok([
            'start' => $week['start']->format('Y-m-d'),
            'end' => $week['end']->modify('-1 day')->format('Y-m-d'),
            'previous' => $reference->modify('-7 days')->format('Y-m-d'),
            'next' => $reference->modify('+7 days')->format('Y-m-d'),
            'days' => $days,
            'workStart' => Settings::get('working_hours_start', '07:00'),
            'workEnd' => Settings::get('working_hours_end', '19:00'),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id), 'No encontramos esta cita.');

        $this->ok(Present::row($appointment) + [
            'date' => date('Y-m-d', strtotime((string) $appointment['starts_at'])),
            'time' => date('H:i', strtotime((string) $appointment['starts_at'])),
            'duration' => (int) round((strtotime((string) $appointment['ends_at']) - strtotime((string) $appointment['starts_at'])) / 60),
        ]);
    }

    public function store(Request $request): void
    {
        $this->validate($request, self::RULES);
        $payload = $this->payload($request);
        $this->guardConflict($payload);

        $id = Database::insert('appointments', $payload + ['uuid' => uuid(), 'created_by' => Auth::id()]);
        AuditLog::record('create', 'appointment', $id);

        $this->created(['id' => $id, 'week' => substr($payload['starts_at'], 0, 10)], 'Cita agendada.');
    }

    public function update(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id), 'No encontramos esta cita.');
        $this->validate($request, self::RULES);
        $payload = $this->payload($request);
        $this->guardConflict($payload, (int) $appointment['id']);

        Database::update('appointments', (int) $appointment['id'], $payload);
        AuditLog::record('update', 'appointment', (int) $appointment['id']);

        $this->message('Cita actualizada.', ['id' => (int) $appointment['id'], 'week' => substr($payload['starts_at'], 0, 10)]);
    }

    public function changeStatus(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id), 'No encontramos esta cita.');
        $status = $this->oneOf($request->string('status'), Appointments::STATUSES, 'scheduled');

        Database::update('appointments', (int) $appointment['id'], ['status' => $status]);
        AuditLog::record('status:' . $status, 'appointment', (int) $appointment['id']);

        $this->message(__('Estado de la cita: %s.', mb_strtolower(__(Appointments::STATUSES[$status]))), ['status' => $status]);
    }

    public function destroy(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id), 'No encontramos esta cita.');

        Database::delete('appointments', (int) $appointment['id']);
        AuditLog::record('delete', 'appointment', (int) $appointment['id']);

        $this->message('Cita eliminada.');
    }

    private function guardConflict(array $payload, ?int $ignoreId = null): void
    {
        if (in_array($payload['status'], ['cancelled', 'no_show'], true)) {
            return;
        }

        if (Appointments::hasConflict((int) $payload['psychologist_id'], $payload['starts_at'], $payload['ends_at'], $ignoreId)) {
            throw HttpException::conflict('El profesional ya tiene una cita que se cruza con ese horario. Elige otra hora.');
        }
    }

    private function payload(Request $request): array
    {
        try {
            $starts = new DateTimeImmutable($request->string('date') . ' ' . $request->string('time'));
        } catch (\Exception) {
            throw HttpException::unprocessable('La fecha u hora no son válidas.', ['time' => 'Revisa la hora.']);
        }

        $duration = max(15, min(480, $request->integer('duration', 50)));

        return [
            'patient_id' => $request->integer('patient_id'),
            'psychologist_id' => $request->integer('psychologist_id'),
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $starts->modify('+' . $duration . ' minutes')->format('Y-m-d H:i:s'),
            'modality' => $this->oneOf($request->string('modality'), Appointments::MODALITIES, 'in_person'),
            'status' => $this->oneOf($request->string('status'), Appointments::STATUSES, 'scheduled'),
            'session_type' => $request->string('session_type'),
            'location' => $request->string('location'),
            'meeting_url' => $this->safeUrl($request->string('meeting_url')),
            'fee' => max(0, (float) $request->input('fee', 0)),
            'notes' => $request->string('notes'),
        ];
    }

    /** Solo enlaces http(s): el enlace se muestra al paciente en el portal. */
    private function safeUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw HttpException::unprocessable('El enlace de la videollamada no es válido.', [
                'meeting_url' => 'Usa un enlace que empiece por https://',
            ]);
        }

        return $url;
    }

    private function reference(string $value): DateTimeImmutable
    {
        if ($value !== '' && strtotime($value) !== false) {
            return new DateTimeImmutable($value);
        }

        return new DateTimeImmutable('today');
    }
}
