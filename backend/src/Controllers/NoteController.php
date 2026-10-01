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
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class NoteController extends Controller
{
    /** TEXT columns hold 65,535 bytes: 15,000 characters always fit, even in 4-byte UTF-8. */
    private const RULES = [
        'patient_id' => 'required|integer',
        'session_date' => 'required|date',
        'format' => 'required|max:10',
        'mood_score' => 'numeric',
        'subjective' => 'max:15000',
        'objective' => 'max:15000',
        'assessment' => 'max:15000',
        'plan' => 'max:15000',
        'homework' => 'max:5000',
        'appointment_id' => 'integer',
        'session_number' => 'integer',
    ];

    public function index(Request $request): void
    {
        $this->ok(Present::page(Notes::paginate($request->string('q'), max(1, $request->integer('page', 1)))));
    }

    /** Data needed to prepare a new note for a patient. */
    public function context(Request $request, string $patientId): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $patientId), "We couldn't find this patient.");

        $this->ok([
            'nextSessionNumber' => Notes::nextSessionNumber((int) $patient['id']),
            'appointments' => Database::all(
                'SELECT id, starts_at, status FROM appointments WHERE patient_id = :id ORDER BY starts_at DESC LIMIT 20',
                ['id' => (int) $patient['id']]
            ),
        ]);
    }

    public function store(Request $request): void
    {
        $this->validate($request, self::RULES);

        $patientId = $request->integer('patient_id');
        $this->abortIfMissing(Patients::find($patientId), "We couldn't find this patient.");

        $sessionNumber = $request->integer('session_number');

        $payload = $this->payload($request, $patientId) + [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'author_id' => (int) Auth::id(),
            // SMALLINT UNSIGNED column.
            'session_number' => $sessionNumber > 0 ? min($sessionNumber, 65535) : Notes::nextSessionNumber($patientId),
        ];

        $id = Database::insert('clinical_notes', $payload);
        $this->syncPatientRisk($patientId, (string) $payload['risk_level']);
        AuditLog::record('create', 'clinical_note', $id);

        $this->created(['id' => $id], 'Session note saved.');
    }

    public function show(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id), "We couldn't find this note.");
        AuditLog::record('view', 'clinical_note', (int) $note['id']);

        $this->ok([
            'note' => Present::row($note),
            'lockHours' => (int) Settings::get('note_lock_hours', '72'),
            'canSign' => $this->canSign($note),
            'clinic' => [
                'name' => Settings::get('clinic_name', 'PsiClinic'),
                'address' => Settings::get('clinic_address'),
                'phone' => Settings::get('clinic_phone'),
            ],
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id), "We couldn't find this note.");

        if ((int) $note['is_locked'] === 1) {
            throw HttpException::conflict("This note is signed and can't be changed anymore.");
        }

        // A session note belongs to whoever wrote it: colleagues can read it,
        // but only the author (or an administrator) can rewrite it.
        if ((int) $note['author_id'] !== Auth::id() && !Auth::is('admin')) {
            throw HttpException::forbidden('Only the person who wrote this note can change it.');
        }

        $this->validate($request, self::RULES);
        $payload = $this->payload($request, (int) $note['patient_id']);

        Database::update('clinical_notes', (int) $note['id'], $payload);
        $this->syncPatientRisk((int) $note['patient_id'], (string) $payload['risk_level']);
        AuditLog::record('update', 'clinical_note', (int) $note['id']);

        $this->message('Note updated.', ['id' => (int) $note['id']]);
    }

    /**
     * Signing locks the note for good. Only the person who wrote it or an
     * administrator can do it, because the signature certifies authorship.
     */
    public function sign(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id), "We couldn't find this note.");

        if ((int) $note['is_locked'] === 1) {
            throw HttpException::conflict('This note was already signed.');
        }

        if (!$this->canSign($note)) {
            throw HttpException::forbidden('Only the person who wrote the note can sign it.');
        }

        Database::update('clinical_notes', (int) $note['id'], [
            'is_locked' => 1,
            'locked_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record('sign', 'clinical_note', (int) $note['id']);

        $this->message('Note signed. From now on it is locked.');
    }

    private function canSign(array $note): bool
    {
        return (int) $note['is_locked'] === 0
            && ((int) $note['author_id'] === Auth::id() || Auth::is('admin'));
    }

    private function payload(Request $request, int $patientId): array
    {
        // The linked appointment must be one of this patient's appointments.
        $appointmentId = $request->integer('appointment_id');
        if ($appointmentId > 0 && Database::value(
            'SELECT COUNT(*) FROM appointments WHERE id = :id AND patient_id = :patient',
            ['id' => $appointmentId, 'patient' => $patientId]
        ) == 0) {
            throw HttpException::unprocessable('Please check the highlighted fields in the form.', [
                'appointment_id' => "Choose one of this patient's appointments.",
            ]);
        }

        $catalog = array_flip(Notes::INTERVENTIONS);
        $interventions = array_values(array_filter(
            array_map('strval', array_filter($request->array('interventions'), 'is_scalar')),
            static fn (string $item): bool => isset($catalog[$item])
        ));

        $rawMood = $request->string('mood_score');
        $mood = is_numeric($rawMood) ? (int) $rawMood : -1;

        return [
            'appointment_id' => $appointmentId > 0 ? $appointmentId : null,
            'format' => $this->oneOf($request->string('format'), Notes::FORMATS, 'soap'),
            'session_date' => $request->string('session_date') ?: date('Y-m-d'),
            'subjective' => $request->string('subjective'),
            'objective' => $request->string('objective'),
            'assessment' => $request->string('assessment'),
            'plan' => $request->string('plan'),
            'interventions' => mb_substr(implode(', ', $interventions), 0, 255),
            'homework' => $request->string('homework'),
            'mood_score' => $mood >= 0 && $mood <= 10 ? $mood : null,
            'risk_level' => $this->oneOf($request->string('risk_level'), Patients::RISK_LEVELS, 'none'),
        ];
    }

    private function syncPatientRisk(int $patientId, string $riskLevel): void
    {
        Database::update('patients', $patientId, ['risk_level' => $riskLevel]);
    }
}
