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
use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class NoteController extends Controller
{
    private const RULES = [
        'patient_id' => 'required|numeric',
        'session_date' => 'required|date',
        'format' => 'required',
        'mood_score' => 'numeric',
        'homework' => 'max:5000',
    ];

    public function index(Request $request): void
    {
        $this->ok(Present::page(Notes::paginate($request->string('q'), max(1, $request->integer('page', 1)))));
    }

    /** Datos para preparar una nota nueva de un paciente. */
    public function context(Request $request, string $patientId): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $patientId), 'No encontramos a este paciente.');

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
        $this->abortIfMissing(Patients::find($patientId), 'No encontramos a este paciente.');

        $payload = $this->payload($request) + [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'author_id' => (int) Auth::id(),
            'session_number' => $request->integer('session_number') ?: Notes::nextSessionNumber($patientId),
        ];

        $id = Database::insert('clinical_notes', $payload);
        $this->syncPatientRisk($patientId, (string) $payload['risk_level']);
        AuditLog::record('create', 'clinical_note', $id);

        $this->created(['id' => $id], 'Nota de sesión guardada.');
    }

    public function show(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id), 'No encontramos esta nota.');
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
        $note = $this->abortIfMissing(Notes::find((int) $id), 'No encontramos esta nota.');

        if ((int) $note['is_locked'] === 1) {
            throw HttpException::conflict('La nota está firmada y ya no admite cambios.');
        }

        $this->validate($request, self::RULES);
        $payload = $this->payload($request);

        Database::update('clinical_notes', (int) $note['id'], $payload);
        $this->syncPatientRisk((int) $note['patient_id'], (string) $payload['risk_level']);
        AuditLog::record('update', 'clinical_note', (int) $note['id']);

        $this->message('Nota actualizada.', ['id' => (int) $note['id']]);
    }

    /**
     * Firmar bloquea la nota de forma definitiva. Solo puede hacerlo quien la
     * escribio o un administrador, porque la firma acredita la autoria.
     */
    public function sign(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id), 'No encontramos esta nota.');

        if ((int) $note['is_locked'] === 1) {
            throw HttpException::conflict('Esta nota ya estaba firmada.');
        }

        if (!$this->canSign($note)) {
            throw HttpException::forbidden('Solo quien escribió la nota puede firmarla.');
        }

        Database::update('clinical_notes', (int) $note['id'], [
            'is_locked' => 1,
            'locked_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record('sign', 'clinical_note', (int) $note['id']);

        $this->message('Nota firmada. A partir de ahora queda bloqueada.');
    }

    private function canSign(array $note): bool
    {
        return (int) $note['is_locked'] === 0
            && ((int) $note['author_id'] === Auth::id() || Auth::is('admin'));
    }

    private function payload(Request $request): array
    {
        $catalog = array_flip(Notes::INTERVENTIONS);
        $interventions = array_values(array_filter(
            array_map('strval', $request->array('interventions')),
            static fn (string $item): bool => isset($catalog[$item])
        ));

        $rawMood = $request->string('mood_score');
        $mood = is_numeric($rawMood) ? (int) $rawMood : -1;

        return [
            'appointment_id' => $request->integer('appointment_id') ?: null,
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
