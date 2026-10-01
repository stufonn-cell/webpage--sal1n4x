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
        'active' => 'Activo',
        'remission' => 'En remisión',
        'resolved' => 'Resuelto',
        'ruled_out' => 'Descartado',
    ];

    private const RULES = [
        'first_name' => 'required|max:80',
        'last_name' => 'required|max:80',
        'birth_date' => 'date',
        'email' => 'email|max:180',
        'phone' => 'max:40',
        'document_id' => 'max:40',
        'gender' => 'required',
        'status' => 'required',
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

        $this->created(['id' => $id], 'Paciente registrado.');
    }

    public function show(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), 'No encontramos a este paciente.');
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
            'diagnoses' => Database::all('SELECT * FROM diagnoses WHERE patient_id = :id ORDER BY created_at DESC', ['id' => $patientId]),
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
        $patient = $this->abortIfMissing(Patients::find((int) $id), 'No encontramos a este paciente.');
        $this->validate($request, self::RULES);

        Database::update('patients', (int) $patient['id'], $this->payload($request));
        AuditLog::record('update', 'patient', (int) $patient['id']);

        $this->message('Datos del paciente actualizados.', ['id' => (int) $patient['id']]);
    }

    public function destroy(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), 'No encontramos a este paciente.');

        Database::delete('patients', (int) $patient['id']);
        AuditLog::record('delete', 'patient', (int) $patient['id']);

        $this->message('Paciente eliminado junto con su historia clínica.');
    }

    /**
     * Diagnosticos en CIE-11 (por defecto), CIE-10 o DSM-5. Para CIE-11 el
     * codigo debe existir en el catalogo y se guarda su equivalente CIE-10,
     * que es el que hoy exige el RIPS (codificacion dual de la transicion).
     */
    public function storeDiagnosis(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), 'No encontramos a este paciente.');

        $this->validate($request, [
            'code' => 'required|max:20',
            'title' => 'max:200',
            'icd10_code' => 'max:10',
            'onset_date' => 'date',
            'notes' => 'max:2000',
        ]);

        $system = $this->oneOf($request->string('system'), ['icd11' => 1, 'icd10' => 1, 'dsm5' => 1], 'icd11');
        $code = strtoupper($request->string('code'));
        $title = $request->string('title');
        $icd10 = strtoupper($request->string('icd10_code'));

        if ($system === 'icd11') {
            $entry = Icd11::find($code);
            if ($entry === null) {
                throw HttpException::unprocessable(__('Ese código no está en la CIE-11 %s.', Icd11::RELEASE), ['code' => 'Elige un código de la lista.']);
            }
            $title = $entry['title'];
            $icd10 = $icd10 !== '' ? $icd10 : (string) ($entry['icd10_code'] ?? '');
        } elseif ($title === '') {
            throw HttpException::unprocessable('Escribe la descripción del diagnóstico.', ['title' => 'Escribe la descripción.']);
        }

        if ($system === 'icd10') {
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

        $this->created(['id' => $diagnosisId], 'Diagnóstico agregado.');
    }

    /** Cambia el estado, el equivalente CIE-10 o marca el diagnostico principal. */
    public function updateDiagnosis(Request $request, string $id, string $diagnosisId): void
    {
        $diagnosis = $this->abortIfMissing(Database::first(
            'SELECT * FROM diagnoses WHERE id = :id AND patient_id = :patient',
            ['id' => (int) $diagnosisId, 'patient' => (int) $id]
        ), 'No encontramos este diagnóstico.');

        $this->validate($request, ['icd10_code' => 'max:10']);

        $data = [];
        if ($request->has('status')) {
            $data['status'] = $this->oneOf($request->string('status'), self::DIAGNOSIS_STATUSES, (string) $diagnosis['status']);
        }
        if ($request->has('icd10_code') && $diagnosis['system'] !== 'icd10') {
            $data['icd10_code'] = strtoupper($request->string('icd10_code')) ?: null;
        }

        Database::transaction(static function () use ($request, $diagnosis, $data): void {
            if ($request->bool('is_primary')) {
                Database::run('UPDATE diagnoses SET is_primary = 0 WHERE patient_id = :id', ['id' => (int) $diagnosis['patient_id']]);
                $data['is_primary'] = 1;
            }
            Database::update('diagnoses', (int) $diagnosis['id'], $data);
        });
        AuditLog::record('update', 'diagnosis', (int) $diagnosis['id']);

        $this->message('Diagnóstico actualizado.');
    }

    public function destroyDiagnosis(Request $request, string $id, string $diagnosisId): void
    {
        Database::run(
            'DELETE FROM diagnoses WHERE id = :id AND patient_id = :patient',
            ['id' => (int) $diagnosisId, 'patient' => (int) $id]
        );
        AuditLog::record('delete', 'diagnosis', (int) $diagnosisId);

        $this->message('Diagnóstico eliminado.');
    }

    /**
     * Crea la cuenta del portal. La contrasena temporal se devuelve una sola
     * vez en esta respuesta y no queda guardada en ningun otro lugar.
     */
    public function createPortalAccess(Request $request, string $id): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $id), 'No encontramos a este paciente.');
        $email = (string) ($patient['email'] ?? '');

        if ($email === '') {
            throw HttpException::unprocessable('Registra un correo del paciente antes de crear su acceso al portal.');
        }

        if (Database::first('SELECT id FROM users WHERE patient_id = :id', ['id' => (int) $patient['id']]) !== null) {
            throw HttpException::conflict('Este paciente ya tiene acceso al portal.');
        }

        if (Database::first('SELECT id FROM users WHERE email = :email', ['email' => $email]) !== null) {
            throw HttpException::conflict('Ya existe una cuenta con ese correo.');
        }

        $username = strtolower((string) $patient['record_number']);
        $password = bin2hex(random_bytes(6));

        $userId = Database::insert('users', [
            'uuid' => uuid(),
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => Patients::fullName($patient),
            'role' => 'patient',
            'patient_id' => (int) $patient['id'],
        ]);
        AuditLog::record('create:portal', 'user', $userId);

        $this->created(['username' => $username, 'temporaryPassword' => $password], 'Acceso al portal creado.');
    }

    private function payload(Request $request): array
    {
        $errors = [];
        $municipality = $request->string('residence_municipality');
        if ($municipality !== '' && !preg_match('/^\d{5}$/', $municipality)) {
            $errors['residence_municipality'] = 'Usa el código DIVIPOLA de 5 dígitos (por ejemplo 11001 para Bogotá).';
        }
        foreach (['residence_country', 'origin_country'] as $field) {
            $value = $request->string($field);
            if ($value !== '' && !preg_match('/^\d{3}$/', $value)) {
                $errors[$field] = 'Usa el código numérico de 3 dígitos (170 para Colombia).';
            }
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('Revisa los datos para el RIPS.', $errors);
        }

        return [
            'first_name' => $request->string('first_name'),
            'last_name' => $request->string('last_name'),
            'birth_date' => $request->string('birth_date') ?: null,
            'gender' => $this->oneOf($request->string('gender'), Patients::GENDERS, 'undisclosed'),
            'biological_sex' => array_key_exists($request->string('biological_sex'), Rips::SEXES) ? $request->string('biological_sex') : null,
            'rips_user_type' => $this->oneOf($request->string('rips_user_type'), Rips::USER_TYPES, '12'),
            'residence_country' => preg_match('/^\d{3}$/', $request->string('residence_country')) ? $request->string('residence_country') : '170',
            'residence_municipality' => preg_match('/^\d{5}$/', $request->string('residence_municipality')) ? $request->string('residence_municipality') : null,
            'residence_zone' => $this->oneOf($request->string('residence_zone'), Rips::ZONES, '01'),
            'origin_country' => preg_match('/^\d{3}$/', $request->string('origin_country')) ? $request->string('origin_country') : '170',
            'document_type' => mb_substr($request->string('document_type'), 0, 20),
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
            'psychologist_id' => $request->integer('psychologist_id') ?: null,
        ];
    }
}
