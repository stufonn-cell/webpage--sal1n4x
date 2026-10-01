<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

/**
 * Generacion del RIPS en JSON (Resolucion 2275 de 2023, Anexo Tecnico 1, y
 * Lineamientos de generacion, validacion y envio v3.2 de mayo de 2025).
 *
 * Caso cubierto: profesional independiente que atiende a particulares y no
 * esta obligado a expedir factura electronica en salud. Reporta "RIPS sin
 * factura": numFactura null, tipoNota "RS" y numNota consecutivo propio.
 *
 * Solo se reportan consultas (objeto "consultas", campos C01 a C21) de citas
 * en estado "Realizada" que no hayan sido incluidas en un reporte anterior.
 */
final class Rips
{
    /** CUPS de consulta por psicologia (citados en los lineamientos v3.2). */
    public const CUPS_FIRST = '890208';
    public const CUPS_FOLLOW_UP = '890308';

    /** Tabla ModalidadAtencion: 01 intramural, 06 telemedicina interactiva. */
    private const MODALITY = ['in_person' => '01', 'online' => '06', 'phone' => '06'];

    /** Tipos de documento admitidos en U01 y C15 (tabla TipoIdPISIS). */
    public const DOCUMENT_TYPES = [
        'CC' => 'Cédula de ciudadanía',
        'TI' => 'Tarjeta de identidad',
        'RC' => 'Registro civil',
        'CE' => 'Cédula de extranjería',
        'PA' => 'Pasaporte',
        'PT' => 'Permiso por protección temporal',
        'CD' => 'Carné diplomático',
        'SC' => 'Salvoconducto',
        'PE' => 'Permiso especial de permanencia',
        'CN' => 'Certificado de nacido vivo',
        'AS' => 'Adulto sin identificación',
        'MS' => 'Menor sin identificación',
    ];

    /** Tabla RIPSTipoUsuarioVersion2. */
    public const USER_TYPES = [
        '12' => 'Particular',
        '01' => 'Contributivo cotizante',
        '02' => 'Contributivo beneficiario',
        '03' => 'Contributivo adicional',
        '04' => 'Subsidiado',
        '05' => 'No afiliado',
        '06' => 'Especial o excepción cotizante',
        '07' => 'Especial o excepción beneficiario',
        '11' => 'Tomador o amparado de planes voluntarios de salud',
    ];

    /** Tabla Sexo, columna Extra_III: segun el documento de identidad. */
    public const SEXES = ['F' => 'Femenino', 'M' => 'Masculino', 'I' => 'Indeterminado o intersexual'];

    /** Tabla ZonaVersion2. */
    public const ZONES = ['01' => 'Urbana', '02' => 'Rural'];

    /** Tabla RIPSFinalidadConsultaVersion2 (subconjunto util en psicologia). */
    public const PURPOSES = [
        '15' => 'Diagnóstico',
        '16' => 'Tratamiento',
        '17' => 'Rehabilitación',
        '21' => 'Atención básica de orientación familiar',
        '31' => 'Prevención del consumo de sustancias psicoactivas',
        '35' => 'Promoción de estrategias de afrontamiento frente a sucesos vitales',
        '44' => 'Otra',
    ];

    /** Tabla RIPSCausaExternaVersion2 (subconjunto util en psicologia). */
    public const CAUSES = [
        '38' => 'Enfermedad general',
        '39' => 'Enfermedad laboral',
        '21' => 'Accidente de trabajo',
        '28' => 'Lesión por agresión',
        '29' => 'Lesión autoinfligida',
        '30' => 'Sospecha de violencia física',
        '31' => 'Sospecha de violencia psicológica',
        '32' => 'Sospecha de violencia sexual',
        '33' => 'Sospecha de negligencia y abandono',
        '40' => 'Promoción y mantenimiento de la salud',
    ];

    /**
     * Arma el reporte de un periodo. Devuelve las consultas listas, las que
     * tienen datos faltantes (con el motivo) y el JSON resultante.
     */
    public static function build(string $from, string $to, ?string $numNota = null): array
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

        // Consecutivos (U10 y C21): inician en 1 y no se repiten.
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
                'numDocumentoIdObligado' => preg_replace('/\D+/', '', (string) ($settings['rips_obligado_documento'] ?? '')),
                'numFactura' => null,
                'tipoNota' => 'RS',
                'numNota' => $numNota ?? self::nextNumNota(),
                'usuarios' => $usuarios,
            ],
        ];
    }

    public static function nextNumNota(): string
    {
        $last = (int) Database::value('SELECT COALESCE(MAX(CAST(num_nota AS UNSIGNED)), 0) FROM rips_reports');
        $start = (int) Settings::get('rips_numero_inicial', '1');

        return (string) max($last + 1, $start);
    }

    /**
     * CIE-10 en el formato del RIPS: sin punto, en mayusculas. El campo exige
     * al menos 4 caracteres: un codigo de 3 (categoria con subdivisiones) se
     * rechaza para que el profesional elija la subcategoria exacta.
     */
    public static function icd10ForRips(?string $code): ?string
    {
        $normalized = strtoupper(str_replace(['.', ' '], '', (string) $code));

        return preg_match('/^[A-Z]\d{2}[0-9A-Z]{1,2}$/', $normalized) === 1 ? $normalized : null;
    }

    /** Datos de configuracion de la clinica que faltan para poder reportar. */
    public static function configIssues(array $settings): array
    {
        $issues = [];
        if (!preg_match('/^\d{4,12}$/', preg_replace('/\D+/', '', (string) ($settings['rips_obligado_documento'] ?? '')) ?? '')) {
            $issues[] = __('Falta el NIT o documento del obligado a reportar (Configuración → RIPS).');
        }
        if (!preg_match('/^\d{10,12}$/', (string) ($settings['rips_cod_prestador'] ?? ''))) {
            $issues[] = __('Falta el código de habilitación del prestador en el REPS (12 dígitos).');
        }
        if (!preg_match('/^\d{3,4}$/', (string) ($settings['rips_cod_servicio'] ?? ''))) {
            $issues[] = __('Falta el código del servicio habilitado (Resolución 3100 de 2019).');
        }

        return $issues;
    }

    private static function diagnosesFor(int $patientId): array
    {
        $rows = Database::all(
            'SELECT id, `system`, code, icd10_code, title, is_primary, status, created_at
             FROM diagnoses
             WHERE patient_id = :id AND status IN ("active", "remission")
             ORDER BY is_primary DESC, created_at',
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
            $issues[] = __('Tipo de documento del paciente no válido para RIPS.');
        }
        if (!preg_match('/^[A-Za-z0-9]{4,20}$/', (string) $appointment['document_id'])) {
            $issues[] = __('Falta el número de documento del paciente.');
        } elseif (in_array($appointment['document_type'], ['CC', 'TI'], true) && !ctype_digit((string) $appointment['document_id'])) {
            $issues[] = __('Con CC o TI el documento solo puede tener números.');
        }
        if (empty($appointment['birth_date'])) {
            $issues[] = __('Falta la fecha de nacimiento del paciente.');
        }
        if (!array_key_exists((string) $appointment['biological_sex'], self::SEXES)) {
            $issues[] = __('Falta el sexo según el documento de identidad.');
        }
        if ($appointment['residence_country'] === '170' && !preg_match('/^\d{5}$/', (string) $appointment['residence_municipality'])) {
            $issues[] = __('Falta el municipio de residencia (código DIVIPOLA de 5 dígitos).');
        }
        if (!array_key_exists((string) $appointment['professional_document_type'], self::DOCUMENT_TYPES)
            || !preg_match('/^[A-Za-z0-9]{4,20}$/', (string) $appointment['professional_document_number'])) {
            $issues[] = __('Falta el documento del profesional que atendió (Mi perfil o Usuarios).');
        }

        if ($diagnoses === []) {
            $issues[] = __('El paciente no tiene un diagnóstico activo.');
        } elseif ($diagnoses[0]['icd10'] === null) {
            $issues[] = $diagnoses[0]['icd10_raw']
                ? __('El diagnóstico principal necesita su código CIE-10 de 4 caracteres para el RIPS (hoy: %s).', $diagnoses[0]['icd10_raw'])
                : __('El diagnóstico principal necesita su código CIE-10 de 4 caracteres para el RIPS.');
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

        // Tipo de diagnostico (C14): 02 confirmado nuevo en la primera consulta
        // posterior a su registro; 03 confirmado repetido en las siguientes.
        $repeated = (int) Database::value(
            'SELECT COUNT(*) FROM appointments
             WHERE patient_id = :patient AND status = "completed" AND starts_at < :starts AND starts_at >= :since',
            ['patient' => (int) $appointment['patient_id'], 'starts' => $appointment['starts_at'], 'since' => $principal['created_at']]
        ) > 0;

        return [
            'codPrestador' => (string) $settings['rips_cod_prestador'],
            'fechaInicioAtencion' => date('Y-m-d H:i', strtotime((string) $appointment['starts_at'])),
            'numAutorizacion' => null,
            'codConsulta' => $cups,
            'modalidadGrupoServicioTecSal' => self::MODALITY[$appointment['modality']] ?? '01',
            'grupoServicios' => '01',
            'codServicio' => (int) $settings['rips_cod_servicio'],
            'finalidadTecnologiaSalud' => $cups === self::CUPS_FIRST
                ? (string) ($settings['rips_finalidad_primera'] ?? '15')
                : (string) ($settings['rips_finalidad_control'] ?? '16'),
            'causaMotivoAtencion' => (string) ($settings['rips_causa'] ?? '38'),
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

    /** Orden de campos del Anexo Tecnico 1 (U01 a U11 y luego servicios). */
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
