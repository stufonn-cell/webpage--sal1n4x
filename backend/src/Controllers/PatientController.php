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
use PsiClinic\Domain\Assessments;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Icd11;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Rips;
use PsiClinic\Support\Present;

final class PatientController extends Controller
{
    public const DIAGNOSIS_STATUSES = [
        'active' => 'Active',
        'remission' => 'In remission',
        'resolved' => 'Resolved',
        'ruled_out' => 'Ruled out',
    ];

    public const DIAGNOSIS_SYSTEMS = [
        'icd11' => 'ICD-11',
        'icd10' => 'ICD-10',
        'dsm5' => 'DSM-5',
    ];

    /** ICD-10 code with or without the dot: F41.1, F411, F32, Z63.0. */
    private const ICD10_PATTERN = '/^[A-Z]\d{2}(\.?[0-9A-Z]{1,2})?$/';

    /**
     * Every text field is capped at its column size: a longer value would
     * otherwise reach MySQL (strict mode) and fail with a server error.
     * TEXT columns hold 65,535 bytes, so 10,000 characters always fit.
     */
    private const RULES = [
        'first_name' => 'required|max:80',
        'last_name' => 'required|max:80',
        'birth_date' => 'date',
        'email' => 'email|max:180',
        'phone' => 'max:40',
        'document_id' => 'max:40',
        'gender' => 'required|max:20',
        'status' => 'required|max:20',
        'address' => 'max:220',
        'city' => 'max:80',
        'country' => 'max:80',
        'occupation' => 'max:120',
        'marital_status' => 'max:40',
        'emergency_contact_name' => 'max:140',
        'emergency_contact_phone' => 'max:40',
        'referred_by' => 'max:140',
        'reason_for_consult' => 'max:10000',
        'relevant_history' => 'max:10000',
        'current_medication' => 'max:10000',
        'psychologist_id' => 'integer',
    ];

    public function index(Request $request): void
    {
        $this->ok(Present::page(Patients::paginate(
            $request->string('q'),
            $request->string('status'),
            max(1, $request->integer('page', 1))
        )));
    }

    public function store(Request $request): void
    {
        $this->validate($request, self::RULES);

        $id = Database::insert('patients', $this->payload($request) + [
            'uuid' => uuid(),
            'record_number' => Patients::nextRecordNumber(),
        ]);
        AuditLog::record('create', 'patient', $id);

        $this->created(['id' => $id], 'Patient registered.');
    }

    public function show(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), "We couldn't find this patient.");
        $patientId = (int) $patient['id'];

        $series = [];
        foreach (Instruments::codes() as $code) {
            $points = Assessments::series($patientId, $code);
            if ($points !== []) {
                $series[] = ['code' => $code, 'maxScore' => Instruments::maxScore($code), 'points' => $points];
            }
        }

        AuditLog::record('view', 'patient', $patientId);

        $portal = Database::first('SELECT * FROM users WHERE patient_id = :id', ['id' => $patientId]);

        $this->ok([
            'patient' => Present::row($patient),
            'notes' => Present::rows(Notes::forPatient($patientId)),
            'assessments' => Present::rows(Assessments::forPatient($patientId)),
            'series' => $series,
            'moodSeries' => Notes::moodSeries($patientId),
            'timeline' => Patients::timeline($patientId),
            'diagnoses' => array_map(fn (array $row): array => $this->presentDiagnosis($row), Database::all(
                'SELECT * FROM diagnoses WHERE patient_id = :id ORDER BY is_primary DESC, created_at DESC, id DESC',
                ['id' => $patientId]
            )),
            'appointments' => Present::rows(Database::all(
                'SELECT a.*, u.full_name AS psychologist_name
                 FROM appointments a JOIN users u ON u.id = a.psychologist_id
                 WHERE a.patient_id = :id ORDER BY a.starts_at DESC LIMIT 20',
                ['id' => $patientId]
            )),
            'documents' => Present::rows(Documents::recent($patientId, 20), ['uuid', 'stored_name']),
            'consents' => array_map(
                static fn (array $row): array => Present::consent($row, true),
                Database::all('SELECT * FROM consents WHERE patient_id = :id ORDER BY created_at DESC', ['id' => $patientId])
            ),
            'invoices' => Present::rows(Database::all(
                'SELECT i.*, COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id = i.id), 0) AS paid
                 FROM invoices i WHERE i.patient_id = :id ORDER BY i.issued_at DESC',
                ['id' => $patientId]
            )),
            'portalAccount' => $portal === null ? null : [
                'username' => $portal['username'],
                'is_active' => (bool) $portal['is_active'],
                'last_login_at' => $portal['last_login_at'],
            ],
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), "We couldn't find this patient.");
        $this->validate($request, self::RULES);

        Database::update('patients', (int) $patient['id'], $this->payload($request));
        AuditLog::record('update', 'patient', (int) $patient['id']);

        $this->message('Patient details updated.', ['id' => (int) $patient['id']]);
    }

    public function destroy(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), "We couldn't find this patient.");

        Database::delete('patients', (int) $patient['id']);
        AuditLog::record('delete', 'patient', (int) $patient['id']);

        $this->message('Patient deleted together with their clinical record.');
    }

    /**
     * Diagnoses in ICD-11 (default), ICD-10 or DSM-5. An ICD-11 code must
     * exist in the catalog and its ICD-10 equivalent is stored too, because
     * that is the code RIPS requires today (dual coding during the transition).
     */
    public function storeDiagnosis(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), "We couldn't find this patient.");

        $this->validate($request, [
            'code' => 'required|max:20',
            'title' => 'max:200',
            'icd10_code' => 'max:10',
            'onset_date' => 'date',
            'notes' => 'max:2000',
        ]);

        $system = $this->oneOf($request->string('system'), self::DIAGNOSIS_SYSTEMS, 'icd11');
        $code = strtoupper($request->string('code'));
        $title = $request->string('title');
        $icd10 = $this->icd10($request);

        if ($system === 'icd11') {
            $entry = Icd11::find($code);
            if ($entry === null) {
                throw HttpException::unprocessable(sprintf('That code is not in ICD-11 %s.', Icd11::RELEASE), ['code' => 'Pick a code from the list.']);
            }
            $code = $entry['code'];
            $title = $entry['title'];
            $icd10 = $icd10 !== '' ? $icd10 : (string) ($entry['icd10_code'] ?? '');
        } elseif ($title === '') {
            throw HttpException::unprocessable('Write the description of the diagnosis.', ['title' => 'Write the description.']);
        }

        if ($system === 'icd10') {
            if (!preg_match(self::ICD10_PATTERN, $code)) {
                throw HttpException::unprocessable('Check the ICD-10 code.', ['code' => 'Use a code like F41.1.']);
            }
            $icd10 = $code;
        }

        $isPrimary = $request->bool('is_primary')
            || (int) Database::value('SELECT COUNT(*) FROM diagnoses WHERE patient_id = :id AND is_primary = 1', ['id' => (int) $patient['id']]) === 0;

        $diagnosisId = Database::transaction(function () use ($patient, $system, $code, $title, $icd10, $isPrimary, $request): int {
            if ($isPrimary) {
                Database::run('UPDATE diagnoses SET is_primary = 0 WHERE patient_id = :id', ['id' => (int) $patient['id']]);
            }

            return Database::insert('diagnoses', [
                'patient_id' => (int) $patient['id'],
                'system' => $system,
                'code' => $code,
                'icd10_code' => $icd10 !== '' ? $icd10 : null,
                'title' => mb_substr($title, 0, 200),
                'status' => $this->oneOf($request->string('status'), self::DIAGNOSIS_STATUSES, 'active'),
                'is_primary' => $isPrimary ? 1 : 0,
                'onset_date' => $request->string('onset_date') ?: null,
                'notes' => $request->string('notes'),
                'created_by' => Auth::id(),
            ]);
        });
        AuditLog::record('create', 'diagnosis', $diagnosisId);

        $this->created(['id' => $diagnosisId], 'Diagnosis added.');
    }

    /** Changes the status or the ICD-10 equivalent, or marks the primary diagnosis. */
    public function updateDiagnosis(Request $request, string $id, string $diagnosisId): void
    {
        $diagnosis = $this->abortIfMissing(Database::first(
            'SELECT * FROM diagnoses WHERE id = :id AND patient_id = :patient',
            ['id' => (int) $diagnosisId, 'patient' => (int) $id]
        ), "We couldn't find this diagnosis.");

        $this->validate($request, ['icd10_code' => 'max:10']);

        $data = [];
        if ($request->has('status')) {
            $data['status'] = $this->oneOf($request->string('status'), self::DIAGNOSIS_STATUSES, (string) $diagnosis['status']);
        }
        if ($request->has('icd10_code') && $diagnosis['system'] !== 'icd10') {
            $data['icd10_code'] = $this->icd10($request) ?: null;
        }

        Database::transaction(static function () use ($request, $diagnosis, $data): void {
            if ($request->bool('is_primary')) {
                Database::run('UPDATE diagnoses SET is_primary = 0 WHERE patient_id = :id', ['id' => (int) $diagnosis['patient_id']]);
                $data['is_primary'] = 1;
            }
            if ($data !== []) {
                Database::update('diagnoses', (int) $diagnosis['id'], $data);
            }
        });
        AuditLog::record('update', 'diagnosis', (int) $diagnosis['id']);

        $this->message('Diagnosis updated.');
    }

    /** Deletes a diagnosis. If it was the primary one, the oldest remaining diagnosis takes its place. */
    public function destroyDiagnosis(Request $request, string $id, string $diagnosisId): void
    {
        $diagnosis = $this->abortIfMissing(Database::first(
            'SELECT * FROM diagnoses WHERE id = :id AND patient_id = :patient',
            ['id' => (int) $diagnosisId, 'patient' => (int) $id]
        ), "We couldn't find this diagnosis.");

        Database::transaction(static function () use ($diagnosis): void {
            Database::delete('diagnoses', (int) $diagnosis['id']);

            if ((int) $diagnosis['is_primary'] === 1) {
                $next = Database::first(
                    'SELECT id FROM diagnoses WHERE patient_id = :id
                     ORDER BY FIELD(status, "active", "remission", "resolved", "ruled_out"), created_at, id LIMIT 1',
                    ['id' => (int) $diagnosis['patient_id']]
                );
                if ($next !== null) {
                    Database::update('diagnoses', (int) $next['id'], ['is_primary' => 1]);
                }
            }
        });
        AuditLog::record('delete', 'diagnosis', (int) $diagnosis['id']);

        $this->message('Diagnosis deleted.');
    }

    /**
     * Creates the patient portal account. The temporary password is returned
     * only once, in this response, and is not stored anywhere else.
     */
    public function createPortalAccess(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), "We couldn't find this patient.");
        $email = (string) ($patient['email'] ?? '');

        if ($email === '') {
            throw HttpException::unprocessable("Add the patient's email before creating their portal access.");
        }

        if (Database::first('SELECT id FROM users WHERE patient_id = :id', ['id' => (int) $patient['id']]) !== null) {
            throw HttpException::conflict('This patient already has portal access.');
        }

        if (Database::first('SELECT id FROM users WHERE email = :email', ['email' => $email]) !== null) {
            throw HttpException::conflict('An account with that email already exists.');
        }

        $username = strtolower((string) $patient['record_number']);
        $password = bin2hex(random_bytes(6));

        $userId = Database::insert('users', [
            'uuid' => uuid(),
            'username' => $username,
            'email' => $email,
            'password_hash' => Auth::hashPassword($password),
            'full_name' => Patients::fullName($patient),
            'role' => 'patient',
            'patient_id' => (int) $patient['id'],
        ]);
        AuditLog::record('create:portal', 'user', $userId);

        $this->created(['username' => $username, 'temporaryPassword' => $password], 'Portal access created.');
    }

    private function presentDiagnosis(array $row): array
    {
        $icd10 = $row['system'] === 'icd10' ? $row['code'] : $row['icd10_code'];

        return [
            'id' => (int) $row['id'],
            'system' => $row['system'],
            'code' => $row['code'],
            'icd10_code' => $row['icd10_code'],
            'title' => $row['title'],
            'status' => $row['status'],
            'is_primary' => (int) $row['is_primary'] === 1,
            'onset_date' => $row['onset_date'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at'],
            // What RIPS will receive, or null when the code is not reportable yet.
            'rips_code' => Rips::icd10ForRips($icd10),
        ];
    }

    /** ICD-10 code typed by the professional, validated and upper-cased ('' when empty). */
    private function icd10(Request $request): string
    {
        $icd10 = strtoupper(trim($request->string('icd10_code')));
        if ($icd10 !== '' && !preg_match(self::ICD10_PATTERN, $icd10)) {
            throw HttpException::unprocessable('Check the ICD-10 equivalent.', ['icd10_code' => 'Use a code like F41.1.']);
        }

        return $icd10;
    }

    private function payload(Request $request): array
    {
        $errors = [];
        $municipality = $request->string('residence_municipality');
        if ($municipality !== '' && !preg_match('/^\d{5}$/', $municipality)) {
            $errors['residence_municipality'] = 'Use the 5-digit DIVIPOLA code (for example 11001 for Bogota).';
        }
        foreach (['residence_country', 'origin_country'] as $field) {
            $value = $request->string($field);
            if ($value !== '' && !preg_match('/^\d{3}$/', $value)) {
                $errors[$field] = 'Use the 3-digit numeric country code (170 for Colombia).';
            }
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('Please check the RIPS details.', $errors);
        }

        // The treating professional must be an active clinician, never a
        // patient account or an id that does not exist.
        $psychologistId = $request->integer('psychologist_id');
        if ($psychologistId > 0 && Database::value(
            'SELECT COUNT(*) FROM users WHERE id = :id AND role IN ("admin", "psychologist") AND is_active = 1',
            ['id' => $psychologistId]
        ) == 0) {
            throw HttpException::unprocessable('Please check the highlighted fields in the form.', [
                'psychologist_id' => 'Choose a professional from the list.',
            ]);
        }

        return [
            'first_name' => $request->string('first_name'),
            'last_name' => $request->string('last_name'),
            'birth_date' => $request->string('birth_date') ?: null,
            'gender' => $this->oneOf($request->string('gender'), Patients::GENDERS, 'undisclosed'),
            'biological_sex' => array_key_exists($request->string('biological_sex'), Rips::SEXES) ? $request->string('biological_sex') : null,
            'rips_user_type' => $this->oneOf($request->string('rips_user_type'), Rips::USER_TYPES, '12'),
            'residence_country' => preg_match('/^\d{3}$/', $request->string('residence_country')) ? $request->string('residence_country') : '170',
            'residence_municipality' => preg_match('/^\d{5}$/', $municipality) ? $municipality : null,
            'residence_zone' => $this->oneOf($request->string('residence_zone'), Rips::ZONES, '01'),
            'origin_country' => preg_match('/^\d{3}$/', $request->string('origin_country')) ? $request->string('origin_country') : '170',
            'document_type' => $this->oneOf($request->string('document_type'), Rips::DOCUMENT_TYPES, 'CC'),
            'document_id' => $request->string('document_id'),
            'email' => $request->string('email'),
            'phone' => $request->string('phone'),
            'address' => $request->string('address'),
            'city' => $request->string('city'),
            'country' => $request->string('country'),
            'occupation' => $request->string('occupation'),
            'marital_status' => $request->string('marital_status'),
            'emergency_contact_name' => $request->string('emergency_contact_name'),
            'emergency_contact_phone' => $request->string('emergency_contact_phone'),
            'referred_by' => $request->string('referred_by'),
            'reason_for_consult' => $request->string('reason_for_consult'),
            'relevant_history' => $request->string('relevant_history'),
            'current_medication' => $request->string('current_medication'),
            'risk_level' => $this->oneOf($request->string('risk_level'), Patients::RISK_LEVELS, 'none'),
            'status' => $this->oneOf($request->string('status'), Patients::STATUSES, 'active'),
            'psychologist_id' => $psychologistId > 0 ? $psychologistId : null,
        ];
    }
}
