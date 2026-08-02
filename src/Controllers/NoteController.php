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
use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Settings;

final class NoteController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('notes/index', [
            'result' => Notes::paginate((string) $request->input('q', ''), max(1, $request->integer('page', 1))),
            'search' => (string) $request->input('q', ''),
        ]);
    }

    public function create(Request $request): void
    {
        $patientId = $request->integer('paciente');

        $this->view('notes/form', [
            'note' => null,
            'patients' => Patients::options(),
            'selectedPatient' => $patientId,
            'sessionNumber' => $patientId > 0 ? Notes::nextSessionNumber($patientId) : 1,
            'appointments' => $this->appointmentOptions($patientId),
        ]);
    }

    public function store(Request $request): void
    {
        $this->validate($request, [
            'patient_id' => 'required|numeric',
            'session_date' => 'required|date',
            'format' => 'required',
        ]);

        $patientId = $request->integer('patient_id');
        $payload = $this->payload($request) + [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'author_id' => (int) Auth::id(),
            'session_number' => $request->integer('session_number', Notes::nextSessionNumber($patientId)),
        ];

        $id = Database::insert('clinical_notes', $payload);
        $this->syncPatientRisk($patientId, (string) $payload['risk_level']);
        AuditLog::record('create', 'clinical_note', $id);

        Session::flash('success', 'Nota clinica guardada.');
        $this->redirect('/notas/' . $id);
    }

    public function show(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id));
        AuditLog::record('view', 'clinical_note', (int) $note['id']);

        $this->view('notes/show', [
            'note' => $note,
            'lockHours' => (int) Settings::get('note_lock_hours', '72'),
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id));

        if ((int) $note['is_locked'] === 1) {
            Session::flash('error', 'La nota esta firmada y no admite cambios.');
            $this->redirect('/notas/' . (int) $note['id']);
        }

        $this->view('notes/form', [
            'note' => $note,
            'patients' => Patients::options(),
            'selectedPatient' => (int) $note['patient_id'],
            'sessionNumber' => (int) $note['session_number'],
            'appointments' => $this->appointmentOptions((int) $note['patient_id']),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id));

        if ((int) $note['is_locked'] === 1) {
            Session::flash('error', 'La nota esta firmada y no admite cambios.');
            $this->redirect('/notas/' . (int) $note['id']);
        }

        Database::update('clinical_notes', (int) $note['id'], $this->payload($request));
        $this->syncPatientRisk((int) $note['patient_id'], (string) $request->input('risk_level', 'none'));
        AuditLog::record('update', 'clinical_note', (int) $note['id']);

        Session::flash('success', 'Nota clinica actualizada.');
        $this->redirect('/notas/' . (int) $note['id']);
    }

    public function sign(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id));

        Database::update('clinical_notes', (int) $note['id'], [
            'is_locked' => 1,
            'locked_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record('sign', 'clinical_note', (int) $note['id']);

        Session::flash('success', 'Nota firmada y bloqueada.');
        $this->redirect('/notas/' . (int) $note['id']);
    }

    public function print(Request $request, string $id): void
    {
        $note = $this->abortIfMissing(Notes::find((int) $id));

        $this->view('notes/print', ['note' => $note], 'blank');
    }

    private function payload(Request $request): array
    {
        $interventions = $request->array('interventions');

        return [
            'appointment_id' => $request->integer('appointment_id') ?: null,
            'format' => (string) $request->input('format', 'soap'),
            'session_date' => (string) $request->input('session_date', date('Y-m-d')),
            'subjective' => (string) $request->input('subjective', ''),
            'objective' => (string) $request->input('objective', ''),
            'assessment' => (string) $request->input('assessment', ''),
            'plan' => (string) $request->input('plan', ''),
            'interventions' => substr(implode(', ', array_map('strval', $interventions)), 0, 255),
            'homework' => (string) $request->input('homework', ''),
            'mood_score' => $request->integer('mood_score') ?: null,
            'risk_level' => (string) $request->input('risk_level', 'none'),
        ];
    }

    private function syncPatientRisk(int $patientId, string $riskLevel): void
    {
        Database::update('patients', $patientId, ['risk_level' => $riskLevel]);
    }

    private function appointmentOptions(int $patientId): array
    {
        if ($patientId <= 0) {
            return [];
        }

        return Database::all(
            'SELECT id, starts_at FROM appointments WHERE patient_id = :id ORDER BY starts_at DESC LIMIT 20',
            ['id' => $patientId]
        );
    }
}
