<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

/**
 * RIPS JSON generation for Colombia (Resolution 2275 of 2023, Technical
 * Annex 1, and the Ministry of Health guidelines for generating, validating
 * and sending RIPS, v3.2 of May 2025).
 *
 * Covered case: an independent professional who sees private patients and is
 * not required to issue an electronic health invoice. They report "RIPS
 * without invoice": numFactura null, tipoNota "RS" and their own consecutive
 * numNota.
 *
 * Only consultations are reported (the "consultas" object, fields C01-C21),
 * for appointments in "completed" status that were not included in a previous
 * report. JSON keys and code values are fixed by the Ministry and stay as the
 * standard defines them; only the labels shown in the app are in English.
 */
final class Rips
{
    /** CUPS codes for psychology consultations (quoted in the v3.2 guidelines). */
    public const CUPS_FIRST = '890208';
    public const CUPS_FOLLOW_UP = '890308';

    /** ModalidadAtencion table: 01 on-site, 06 interactive telemedicine. */
    private const MODALITY = ['in_person' => '01', 'online' => '06', 'phone' => '06'];

    /** Document types accepted in U01 and C15 (TipoIdPISIS table). */
    public const DOCUMENT_TYPES = [
        'CC' => 'CC · Citizenship card',
        'TI' => 'TI · Identity card (minors)',
        'RC' => 'RC · Civil registry',
        'CE' => 'CE · Foreigner ID card',
        'PA' => 'PA · Passport',
        'PT' => 'PT · Temporary protection permit',
        'CD' => 'CD · Diplomatic card',
        'SC' => 'SC · Safe-conduct',
        'PE' => 'PE · Special stay permit',
        'CN' => 'CN · Live birth certificate',
        'AS' => 'AS · Adult without ID',
        'MS' => 'MS · Minor without ID',
    ];

    /** RIPSTipoUsuarioVersion2 table. */
    public const USER_TYPES = [
        '12' => 'Private (self-pay)',
        '01' => 'Contributory scheme, contributor',
        '02' => 'Contributory scheme, beneficiary',
        '03' => 'Contributory scheme, additional',
        '04' => 'Subsidized scheme',
        '05' => 'Not insured',
        '06' => 'Special or exception scheme, contributor',
        '07' => 'Special or exception scheme, beneficiary',
        '11' => 'Voluntary health plan holder',
    ];

    /** Sexo table: as stated on the identity document. */
    public const SEXES = ['F' => 'Female', 'M' => 'Male', 'I' => 'Indeterminate or intersex'];

    /** ZonaVersion2 table. */
    public const ZONES = ['01' => 'Urban', '02' => 'Rural'];

    /** RIPSFinalidadConsultaVersion2 table (subset useful in psychology). */
    public const PURPOSES = [
        '15' => 'Diagnosis',
        '16' => 'Treatment',
        '17' => 'Rehabilitation',
        '21' => 'Basic family guidance',
        '31' => 'Prevention of psychoactive substance use',
        '35' => 'Coping strategies for life events',
        '44' => 'Other',
    ];

    /** RIPSCausaExternaVersion2 table (subset useful in psychology). */
    public const CAUSES = [
        '38' => 'General illness',
        '39' => 'Occupational illness',
        '21' => 'Work accident',
        '28' => 'Injury from assault',
        '29' => 'Self-inflicted injury',
        '30' => 'Suspected physical violence',
        '31' => 'Suspected psychological violence',
        '32' => 'Suspected sexual violence',
        '33' => 'Suspected neglect or abandonment',
        '40' => 'Health promotion and maintenance',
    ];

    /** Informative only: the validator itself decides where it reports. */
    public const ENVIRONMENTS = ['test' => 'Test', 'production' => 'Production'];

    public const STATUSES = ['generated' => 'Generated', 'validated' => 'Validated', 'rejected' => 'Rejected'];

    /**
     * Builds the report for a period. Returns the consultations that are
     * ready, the ones with missing data (and why) and the resulting JSON.
     */
    public static function build(string $from, string $to, ?string $noteNumber = null): array
    {
        $settings = Settings::all();
        $config = self::configIssues($settings);

        $appointments = Database::all(
            'SELECT a.id, a.patient_id, a.psychologist_id, a.starts_at, a.modality, a.fee,
                    p.first_name, p.last_name, p.record_number, p.document_type, p.document_id,
                    p.birth_date, p.biological_sex, p.rips_user_type, p.residence_country,
                    p.residence_municipality, p.residence_zone, p.origin_country,
                    u.full_name AS professional_name, u.document_type AS professional_document_type,
                    u.document_number AS professional_document_number
             FROM appointments a
             JOIN patients p ON p.id = a.patient_id
             JOIN users u ON u.id = a.psychologist_id
             LEFT JOIN rips_report_items ri ON ri.appointment_id = a.id
             WHERE a.status = "completed" AND ri.appointment_id IS NULL
               AND a.starts_at >= :from AND a.starts_at < DATE_ADD(:to, INTERVAL 1 DAY)
             ORDER BY p.last_name, p.first_name, a.starts_at',
            ['from' => $from, 'to' => $to]
        );

        $items = [];
        $users = [];

        foreach ($appointments as $appointment) {
            $diagnoses = self::diagnosesFor((int) $appointment['patient_id']);
            $issues = self::issuesFor($appointment, $diagnoses);
            $consulta = $issues === [] ? self::consulta($appointment, $diagnoses, $settings) : null;

            $items[] = [
                'appointment_id' => (int) $appointment['id'],
                'patient_id' => (int) $appointment['patient_id'],
                'patient_name' => trim($appointment['first_name'] . ' ' . $appointment['last_name']),
                'record_number' => $appointment['record_number'],
                'starts_at' => $appointment['starts_at'],
                'professional_name' => $appointment['professional_name'],
                'cups' => self::cupsFor($appointment),
                'diagnosis' => $diagnoses[0]['icd10'] ?? null,
                'value' => (int) round((float) $appointment['fee']),
                'issues' => $issues,
            ];

            if ($consulta === null) {
                continue;
            }

            $key = (int) $appointment['patient_id'];
            if (!isset($users[$key])) {
                $users[$key] = self::usuario($appointment) + ['servicios' => ['consultas' => []]];
            }
            $users[$key]['servicios']['consultas'][] = $consulta;
        }

        // Sequence numbers (U10 and C21) start at 1 and never repeat.
        $usuarios = [];
        foreach (array_values($users) as $userIndex => $user) {
            $user['consecutivo'] = $userIndex + 1;
            foreach ($user['servicios']['consultas'] as $serviceIndex => $service) {
                $user['servicios']['consultas'][$serviceIndex]['consecutivo'] = $serviceIndex + 1;
            }
            $usuarios[] = self::orderUser($user);
        }

        $ready = array_values(array_filter($items, static fn (array $item): bool => $item['issues'] === []));

        return [
            'configIssues' => $config,
            'items' => $items,
            'readyCount' => count($ready),
            'usersCount' => count($usuarios),
            'rips' => [
                'numDocumentoIdObligado' => self::digits((string) ($settings['rips_reporter_id'] ?? '')),
                'numFactura' => null,
                'tipoNota' => 'RS',
                'numNota' => $noteNumber ?? self::nextNoteNumber(),
                'usuarios' => $usuarios,
            ],
        ];
    }

    public static function nextNoteNumber(): string
    {
        $last = (int) Database::value('SELECT COALESCE(MAX(CAST(note_number AS UNSIGNED)), 0) FROM rips_reports');
        $start = (int) Settings::get('rips_first_note_number', '1');

        return (string) max($last + 1, $start, 1);
    }

    /**
     * ICD-10 in RIPS format: no dot, upper case. The field needs at least 4
     * characters: a 3-character code (a category with subdivisions) is
     * rejected so the professional picks the exact subcategory.
     */
    public static function icd10ForRips(?string $code): ?string
    {
        $normalized = strtoupper(str_replace(['.', ' '], '', (string) $code));

        return preg_match('/^[A-Z]\d{2}[0-9A-Z]{1,2}$/', $normalized) === 1 ? $normalized : null;
    }

    /** Practice settings that are still missing before anything can be reported. */
    public static function configIssues(array $settings): array
    {
        $issues = [];
        if (!preg_match('/^\d{4,12}$/', self::digits((string) ($settings['rips_reporter_id'] ?? '')))) {
            $issues[] = 'Add the NIT or ID number of the party required to report (Settings → RIPS).';
        }
        if (!preg_match('/^\d{10,12}$/', (string) ($settings['rips_provider_code'] ?? ''))) {
            $issues[] = 'Add the provider code registered in REPS (10 to 12 digits).';
        }
        if (!preg_match('/^\d{3,4}$/', (string) ($settings['rips_service_code'] ?? ''))) {
            $issues[] = 'Add the code of the enabled health service (Resolution 3100 of 2019).';
        }

        return $issues;
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private static function diagnosesFor(int $patientId): array
    {
        $rows = Database::all(
            'SELECT id, `system`, code, icd10_code, title, is_primary, status, created_at
             FROM diagnoses
             WHERE patient_id = :id AND status IN ("active", "remission")
             ORDER BY is_primary DESC, created_at, id',
            ['id' => $patientId]
        );

        $out = [];
        foreach ($rows as $row) {
            $icd10 = $row['system'] === 'icd10' ? $row['code'] : $row['icd10_code'];
            $out[] = [
                'id' => (int) $row['id'],
                'label' => $row['code'] . ' · ' . $row['title'],
                'icd10' => self::icd10ForRips($icd10),
                'icd10_raw' => $icd10,
                'created_at' => $row['created_at'],
            ];
        }

        return $out;
    }

    private static function issuesFor(array $appointment, array $diagnoses): array
    {
        $issues = [];

        if (!array_key_exists((string) $appointment['document_type'], self::DOCUMENT_TYPES)) {
            $issues[] = 'The patient\'s document type is not valid for RIPS.';
        }
        if (!preg_match('/^[A-Za-z0-9]{4,20}$/', (string) $appointment['document_id'])) {
            $issues[] = 'The patient\'s document number is missing.';
        } elseif (in_array($appointment['document_type'], ['CC', 'TI'], true) && !ctype_digit((string) $appointment['document_id'])) {
            $issues[] = 'With CC or TI the document number can only contain digits.';
        }
        if (empty($appointment['birth_date'])) {
            $issues[] = 'The patient\'s date of birth is missing.';
        }
        if (!array_key_exists((string) $appointment['biological_sex'], self::SEXES)) {
            $issues[] = 'The sex stated on the patient\'s ID document is missing.';
        }
        if ($appointment['residence_country'] === '170' && !preg_match('/^\d{5}$/', (string) $appointment['residence_municipality'])) {
            $issues[] = 'The municipality of residence is missing (5-digit DIVIPOLA code).';
        }
        if (!array_key_exists((string) $appointment['professional_document_type'], self::DOCUMENT_TYPES)
            || !preg_match('/^[A-Za-z0-9]{4,20}$/', (string) $appointment['professional_document_number'])) {
            $issues[] = 'The ID document of the professional who saw the patient is missing (My profile or Users).';
        }

        if ($diagnoses === []) {
            $issues[] = 'The patient has no active diagnosis.';
        } elseif ($diagnoses[0]['icd10'] === null) {
            $issues[] = $diagnoses[0]['icd10_raw']
                ? sprintf('The primary diagnosis needs its 4-character ICD-10 code for RIPS (currently %s).', $diagnoses[0]['icd10_raw'])
                : 'The primary diagnosis needs its 4-character ICD-10 code for RIPS.';
        }

        return $issues;
    }

    private static function cupsFor(array $appointment): string
    {
        $previous = (int) Database::value(
            'SELECT COUNT(*) FROM appointments
             WHERE patient_id = :patient AND status = "completed" AND starts_at < :starts',
            ['patient' => (int) $appointment['patient_id'], 'starts' => $appointment['starts_at']]
        );

        return $previous === 0 ? self::CUPS_FIRST : self::CUPS_FOLLOW_UP;
    }

    private static function usuario(array $appointment): array
    {
        $colombia = $appointment['residence_country'] === '170';

        return [
            'tipoDocumentoIdentificacion' => $appointment['document_type'],
            'numDocumentoIdentificacion' => (string) $appointment['document_id'],
            'tipoUsuario' => $appointment['rips_user_type'] ?: '12',
            'fechaNacimiento' => date('Y-m-d', strtotime((string) $appointment['birth_date'])),
            'codSexo' => $appointment['biological_sex'],
            'codPaisResidencia' => $appointment['residence_country'],
            'codMunicipioResidencia' => $colombia ? $appointment['residence_municipality'] : null,
            'codZonaTerritorialResidencia' => $appointment['residence_zone'] ?: null,
            'incapacidad' => 'NO',
            'codPaisOrigen' => $appointment['origin_country'] ?: '170',
        ];
    }

    private static function consulta(array $appointment, array $diagnoses, array $settings): array
    {
        $cups = self::cupsFor($appointment);
        $principal = $diagnoses[0];
        $related = array_values(array_filter(array_map(
            static fn (array $diagnosis): ?string => $diagnosis['icd10'],
            array_slice($diagnoses, 1)
        )));

        // Diagnosis type (C14): 02 newly confirmed at the first consultation
        // after it was recorded; 03 confirmed, repeated, afterwards.
        $repeated = (int) Database::value(
            'SELECT COUNT(*) FROM appointments
             WHERE patient_id = :patient AND status = "completed" AND starts_at < :starts AND starts_at >= :since',
            ['patient' => (int) $appointment['patient_id'], 'starts' => $appointment['starts_at'], 'since' => $principal['created_at']]
        ) > 0;

        return [
            'codPrestador' => (string) $settings['rips_provider_code'],
            'fechaInicioAtencion' => date('Y-m-d H:i', strtotime((string) $appointment['starts_at'])),
            'numAutorizacion' => null,
            'codConsulta' => $cups,
            'modalidadGrupoServicioTecSal' => self::MODALITY[$appointment['modality']] ?? '01',
            'grupoServicios' => '01',
            'codServicio' => (int) $settings['rips_service_code'],
            'finalidadTecnologiaSalud' => $cups === self::CUPS_FIRST
                ? (string) ($settings['rips_purpose_first'] ?? '15')
                : (string) ($settings['rips_purpose_follow_up'] ?? '16'),
            'causaMotivoAtencion' => (string) ($settings['rips_cause'] ?? '38'),
            'codDiagnosticoPrincipal' => $principal['icd10'],
            'codDiagnosticoRelacionado1' => $related[0] ?? null,
            'codDiagnosticoRelacionado2' => $related[1] ?? null,
            'codDiagnosticoRelacionado3' => $related[2] ?? null,
            'tipoDiagnosticoPrincipal' => $repeated ? '03' : '02',
            'tipoDocumentoIdentificacion' => $appointment['professional_document_type'],
            'numDocumentoIdentificacion' => (string) $appointment['professional_document_number'],
            'vrServicio' => (int) round((float) $appointment['fee']),
            'conceptoRecaudo' => '05',
            'valorPagoModerador' => 0,
            'numFEVPagoModerador' => null,
            'consecutivo' => 0,
        ];
    }

    /** Field order of Technical Annex 1 (U01 to U11, then services). */
    private static function orderUser(array $user): array
    {
        $order = [
            'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'tipoUsuario', 'fechaNacimiento',
            'codSexo', 'codPaisResidencia', 'codMunicipioResidencia', 'codZonaTerritorialResidencia',
            'incapacidad', 'consecutivo', 'codPaisOrigen', 'servicios',
        ];

        $out = [];
        foreach ($order as $key) {
            $out[$key] = $user[$key] ?? null;
        }

        return $out;
    }
}
