<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use DateTimeImmutable;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Core\Validator;
use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class AppointmentController extends Controller
{
    private const RULES = [
        'patient_id' => 'required|integer',
        'psychologist_id' => 'required|integer',
        'date' => 'required|date',
        'time' => 'required|max:8',
        'duration' => 'required|numeric',
        'session_type' => 'max:60',
        'location' => 'max:160',
        'meeting_url' => 'max:255',
        'fee' => 'numeric',
        'notes' => 'max:2000',
    ];

    /** Largest fee the DECIMAL(10,2) column can hold. */
    private const MAX_FEE = 99999999.99;

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
        $appointment = $this->abortIfMissing(Appointments::find((int) $id), "We couldn't find this appointment.");

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

        $this->created(['id' => $id, 'week' => substr($payload['starts_at'], 0, 10)], 'Appointment scheduled.');
    }

    public function update(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id), "We couldn't find this appointment.");
        $this->validate($request, self::RULES);
        $payload = $this->payload($request);
        $this->guardConflict($payload, (int) $appointment['id']);

        Database::update('appointments', (int) $appointment['id'], $payload);
        AuditLog::record('update', 'appointment', (int) $appointment['id']);

        $this->message('Appointment updated.', ['id' => (int) $appointment['id'], 'week' => substr($payload['starts_at'], 0, 10)]);
    }

    public function changeStatus(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id), "We couldn't find this appointment.");
        $status = $this->oneOf($request->string('status'), Appointments::STATUSES, 'scheduled');

        Database::update('appointments', (int) $appointment['id'], ['status' => $status]);
        AuditLog::record('status:' . $status, 'appointment', (int) $appointment['id']);

        $this->message(sprintf('Appointment status: %s.', mb_strtolower(Appointments::STATUSES[$status])), ['status' => $status]);
    }

    public function destroy(Request $request, string $id): void
    {
        $appointment = $this->abortIfMissing(Appointments::find((int) $id), "We couldn't find this appointment.");

        Database::delete('appointments', (int) $appointment['id']);
        AuditLog::record('delete', 'appointment', (int) $appointment['id']);

        $this->message('Appointment deleted.');
    }

    private function guardConflict(array $payload, ?int $ignoreId = null): void
    {
        if (in_array($payload['status'], ['cancelled', 'no_show'], true)) {
            return;
        }

        if (Appointments::hasConflict((int) $payload['psychologist_id'], $payload['starts_at'], $payload['ends_at'], $ignoreId)) {
            throw HttpException::conflict('This professional already has an appointment that overlaps with that time. Please choose another time.');
        }
    }

    private function payload(Request $request): array
    {
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $request->string('time')) !== 1) {
            throw HttpException::unprocessable("The date or time isn't valid.", ['time' => 'Please check the time.']);
        }

        try {
            $starts = new DateTimeImmutable($request->string('date') . ' ' . $request->string('time'));
        } catch (\Exception) {
            throw HttpException::unprocessable("The date or time isn't valid.", ['time' => 'Please check the time.']);
        }

        // Both people must exist (and the professional must be an active
        // clinician): an unknown id used to end in a database error.
        $patientId = $request->integer('patient_id');
        $psychologistId = $request->integer('psychologist_id');
        $errors = [];
        if (Database::value('SELECT COUNT(*) FROM patients WHERE id = :id', ['id' => $patientId]) == 0) {
            $errors['patient_id'] = 'Choose a patient.';
        }
        if (Database::value(
            'SELECT COUNT(*) FROM users WHERE id = :id AND role IN ("admin", "psychologist") AND is_active = 1',
            ['id' => $psychologistId]
        ) == 0) {
            $errors['psychologist_id'] = 'Choose a professional from the list.';
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('Please check the highlighted fields in the form.', $errors);
        }

        $duration = max(15, min(480, $request->integer('duration', 50)));

        return [
            'patient_id' => $patientId,
            'psychologist_id' => $psychologistId,
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $starts->modify('+' . $duration . ' minutes')->format('Y-m-d H:i:s'),
            'modality' => $this->oneOf($request->string('modality'), Appointments::MODALITIES, 'in_person'),
            'status' => $this->oneOf($request->string('status'), Appointments::STATUSES, 'scheduled'),
            'session_type' => $request->string('session_type'),
            'location' => $request->string('location'),
            'meeting_url' => $this->safeUrl($request->string('meeting_url')),
            'fee' => round(max(0.0, min(self::MAX_FEE, (float) $request->string('fee', '0'))), 2),
            'notes' => $request->string('notes'),
        ];
    }

    /** Only http(s) links: the link is shown to the patient in the portal. */
    private function safeUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw HttpException::unprocessable("The video call link isn't valid.", [
                'meeting_url' => 'Use a link that starts with https://',
            ]);
        }

        return $url;
    }

    private function reference(string $value): DateTimeImmutable
    {
        // Only real calendar dates (YYYY-MM-DD) within a sensible range.
        if (Validator::isDate($value) && $value >= '1900-01-01' && $value <= '2999-12-31') {
            return new DateTimeImmutable(substr($value, 0, 10));
        }

        return new DateTimeImmutable('today');
    }
}
