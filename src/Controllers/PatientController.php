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
use PsiClinic\Domain\Assessments;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;

final class PatientController extends Controller
{
    private const RULES = [
        'first_name' => 'required|max:80',
        'last_name' => 'required|max:80',
        'birth_date' => 'date',
        'email' => 'email|max:180',
        'gender' => 'required',
        'status' => 'required',
    ];

    public function index(Request $request): void
    {
        $result = Patients::paginate(
            (string) $request->input('q', ''),
            (string) $request->input('status', ''),
            max(1, $request->integer('page', 1))
        );

        $this->view('patients/index', [
            'result' => $result,
            'search' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('patients/form', [
            'patient' => null,
            'recordNumber' => Patients::nextRecordNumber(),
            'psychologists' => $this->psychologists(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->validate($request, self::RULES);

        $data = $this->payload($request) + [
            'uuid' => uuid(),
            'record_number' => Patients::nextRecordNumber(),
        ];

        $id = Database::insert('patients', $data);
        AuditLog::record('create', 'patient', $id);

        Session::flash('success', 'Paciente registrado correctamente.');
        $this->redirect('/pacientes/' . $id);
    }

    public function show(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id));
        $patientId = (int) $patient['id'];

        $seriesByInstrument = [];
        foreach (Instruments::codes() as $code) {
            $series = Assessments::series($patientId, $code);
            if ($series !== []) {
                $seriesByInstrument[$code] = $series;
            }
        }

        AuditLog::record('view', 'patient', $patientId);

        $this->view('patients/show', [
            'patient' => $patient,
            'notes' => Notes::forPatient($patientId),
            'assessments' => Assessments::forPatient($patientId),
            'series' => $seriesByInstrument,
            'moodSeries' => Notes::moodSeries($patientId),
            'timeline' => Patients::timeline($patientId),
            'diagnoses' => Database::all('SELECT * FROM diagnoses WHERE patient_id = :id ORDER BY created_at DESC', ['id' => $patientId]),
            'appointments' => Database::all(
                'SELECT * FROM appointments WHERE patient_id = :id ORDER BY starts_at DESC LIMIT 20',
                ['id' => $patientId]
            ),
            'documents' => Documents::recent($patientId, 20),
            'consents' => Database::all('SELECT * FROM consents WHERE patient_id = :id ORDER BY created_at DESC', ['id' => $patientId]),
            'invoices' => Database::all(
                'SELECT i.*, COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id = i.id), 0) AS paid
                 FROM invoices i WHERE i.patient_id = :id ORDER BY i.issued_at DESC',
                ['id' => $patientId]
            ),
            'portalAccount' => Database::first('SELECT * FROM users WHERE patient_id = :id', ['id' => $patientId]),
            'instruments' => Instruments::all(),
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id));

        $this->view('patients/form', [
            'patient' => $patient,
            'recordNumber' => (string) $patient['record_number'],
            'psychologists' => $this->psychologists(),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id));
        $this->validate($request, self::RULES);

        Database::update('patients', (int) $patient['id'], $this->payload($request));
        AuditLog::record('update', 'patient', (int) $patient['id']);

        Session::flash('success', 'Datos del paciente actualizados.');
        $this->redirect('/pacientes/' . (int) $patient['id']);
    }

    public function destroy(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id));

        Database::delete('patients', (int) $patient['id']);
        AuditLog::record('delete', 'patient', (int) $patient['id']);

        Session::flash('success', 'Paciente eliminado junto con su historia clinica.');
        $this->redirect('/pacientes');
    }

    public function storeDiagnosis(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id));

        $this->validate($request, ['code' => 'required|max:20', 'title' => 'required|max:200']);

        Database::insert('diagnoses', [
            'patient_id' => (int) $patient['id'],
            'system' => (string) $request->input('system', 'icd10') === 'dsm5' ? 'dsm5' : 'icd10',
            'code' => (string) $request->input('code'),
            'title' => (string) $request->input('title'),
            'status' => (string) $request->input('status', 'active'),
            'onset_date' => $request->input('onset_date') ?: null,
            'notes' => (string) $request->input('notes', ''),
            'created_by' => Auth::id(),
        ]);

        Session::flash('success', 'Diagnostico agregado.');
        $this->redirect('/pacientes/' . (int) $patient['id'] . '#diagnosticos');
    }

    public function destroyDiagnosis(Request $request, string $id, string $diagnosisId): void
    {
        Database::run(
            'DELETE FROM diagnoses WHERE id = :id AND patient_id = :patient',
            ['id' => (int) $diagnosisId, 'patient' => (int) $id]
        );

        Session::flash('success', 'Diagnostico eliminado.');
        $this->redirect('/pacientes/' . (int) $id . '#diagnosticos');
    }

    public function createPortalAccess(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id));
        $email = (string) ($patient['email'] ?? '');

        if ($email === '') {
            Session::flash('error', 'El paciente necesita un correo registrado para crear el acceso al portal.');
            $this->redirect('/pacientes/' . (int) $patient['id']);
        }

        if (Database::first('SELECT id FROM users WHERE email = :email', ['email' => $email]) !== null) {
            Session::flash('error', 'Ya existe un usuario con ese correo.');
            $this->redirect('/pacientes/' . (int) $patient['id']);
        }

        $password = bin2hex(random_bytes(5));

        Database::insert('users', [
            'uuid' => uuid(),
            'username' => strtolower((string) $patient['record_number']),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => Patients::fullName($patient),
            'role' => 'patient',
            'patient_id' => (int) $patient['id'],
        ]);

        Session::flash('success', sprintf(
            'Acceso creado. Usuario: %s | Contrasena temporal: %s',
            strtolower((string) $patient['record_number']),
            $password
        ));
        $this->redirect('/pacientes/' . (int) $patient['id']);
    }

    private function payload(Request $request): array
    {
        return [
            'first_name' => (string) $request->input('first_name'),
            'last_name' => (string) $request->input('last_name'),
            'birth_date' => $request->input('birth_date') ?: null,
            'gender' => (string) $request->input('gender', 'undisclosed'),
            'document_type' => (string) $request->input('document_type', ''),
            'document_id' => (string) $request->input('document_id', ''),
            'email' => (string) $request->input('email', ''),
            'phone' => (string) $request->input('phone', ''),
            'address' => (string) $request->input('address', ''),
            'city' => (string) $request->input('city', ''),
            'country' => (string) $request->input('country', ''),
            'occupation' => (string) $request->input('occupation', ''),
            'marital_status' => (string) $request->input('marital_status', ''),
            'emergency_contact_name' => (string) $request->input('emergency_contact_name', ''),
            'emergency_contact_phone' => (string) $request->input('emergency_contact_phone', ''),
            'referred_by' => (string) $request->input('referred_by', ''),
            'reason_for_consult' => (string) $request->input('reason_for_consult', ''),
            'relevant_history' => (string) $request->input('relevant_history', ''),
            'current_medication' => (string) $request->input('current_medication', ''),
            'risk_level' => (string) $request->input('risk_level', 'none'),
            'status' => (string) $request->input('status', 'active'),
            'psychologist_id' => $request->integer('psychologist_id') ?: null,
        ];
    }

    private function psychologists(): array
    {
        return Database::all(
            'SELECT id, full_name FROM users WHERE role IN ("admin","psychologist") AND is_active = 1 ORDER BY full_name'
        );
    }
}
