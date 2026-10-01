<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Support;

use PsiClinic\Core\Database;
use PsiClinic\Core\Env;
use PsiClinic\Domain\Icd11;

final class Installer
{
    private const DEMO_PASSWORD = 'Psiclinic2026';
    private const PATIENT_PASSWORD = 'Paciente2026';

    public static function install(): void
    {
        self::migrate();
        self::seed();
    }

    public static function migrate(): void
    {
        $directory = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($directory . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            self::runScript((string) file_get_contents($file));
            self::line('Migracion aplicada: ' . basename($file));
        }

        if (!Icd11::isLoaded()) {
            self::importIcd11();
        }
    }

    /** Carga (o actualiza) el catalogo CIE-11 desde database/data. */
    public static function importIcd11(): void
    {
        $rows = Icd11::import(dirname(__DIR__, 2) . '/database/data');
        self::line(sprintf('Catalogo CIE-11 %s cargado: %d codigos.', Icd11::RELEASE, $rows));
    }

    public static function fresh(): void
    {
        $database = (string) Env::get('DB_DATABASE', 'psiclinic');
        $tables = Database::all(
            'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = :schema',
            ['schema' => $database]
        );

        Database::run('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            Database::run(sprintf('DROP TABLE IF EXISTS `%s`', $table['name']));
        }
        Database::run('SET FOREIGN_KEY_CHECKS = 1');

        self::line('Base de datos vaciada.');
        self::install();
    }

    public static function seed(): void
    {
        if ((int) Database::value('SELECT COUNT(*) FROM users') > 0) {
            self::line('La base ya contiene datos. Usa "fresh" para reiniciar.');

            return;
        }

        $adminId = self::createUser('admin', 'admin@psiclinic.local', 'Ana Salinas', 'admin', 'TP-10234');
        $psychologistId = self::createUser('l.moreno', 'l.moreno@psiclinic.local', 'Laura Moreno', 'psychologist', 'TP-48120');
        self::createUser('c.rojas', 'c.rojas@psiclinic.local', 'Camilo Rojas', 'assistant', null);

        // Perfil visible en el sitio publico de demostracion.
        Database::update('users', $psychologistId, [
            'show_on_site' => 1,
            'public_bio' => 'Acompaña a adultos y adolescentes en procesos de ansiedad, estado de ánimo y regulación emocional desde un enfoque cognitivo conductual.',
        ]);

        $patients = self::createPatients($psychologistId, $adminId);
        self::createPortalAccount($patients[0]);
        self::createAppointments($patients, $psychologistId);
        self::createNotes($patients, $psychologistId);
        self::createAssessments($patients, $psychologistId);
        self::createConsents($patients, $psychologistId);
        self::createInvoices($patients, $adminId);
        self::createSettings();

        self::line('Datos de demostracion cargados.');
        self::line('  admin / ' . self::DEMO_PASSWORD);
        self::line('  l.moreno / ' . self::DEMO_PASSWORD);
        self::line('  hc-2026-0001 / ' . self::PATIENT_PASSWORD . ' (portal del paciente)');
    }

    public static function printHelp(array $commands): void
    {
        self::line('PsiClinic - utilidades de linea de comandos');
        self::line('Uso: php bin/console <comando>');
        self::line('');

        foreach ($commands as $name => $description) {
            self::line(sprintf('  %-10s %s', $name, $description));
        }
    }

    private static function runScript(string $script): void
    {
        $connection = Database::connection();

        foreach (self::splitStatements($script) as $statement) {
            // query() + closeCursor() en lugar de exec(): sentencias como
            // EXECUTE pueden devolver filas, y si no se consumen la siguiente
            // falla con "unbuffered queries are active" (2014).
            $result = $connection->query($statement);
            if ($result !== false) {
                if ($result->columnCount() > 0) {
                    $result->fetchAll();
                }
                $result->closeCursor();
            }
        }
    }

    private static function splitStatements(string $script): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $script) ?? $script;
        $statements = array_map('trim', explode(';', $withoutComments));

        return array_values(array_filter(
            $statements,
            static fn (string $statement): bool => $statement !== ''
        ));
    }

    private static function createUser(
        string $username,
        string $email,
        string $fullName,
        string $role,
        ?string $license
    ): int {
        return Database::insert('users', [
            'uuid' => uuid(),
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT),
            'full_name' => $fullName,
            'role' => $role,
            'license_number' => $license,
            'specialty' => $role === 'psychologist' ? 'Terapia cognitivo conductual' : null,
        ]);
    }

    private static function createPatients(int $psychologistId, int $adminId): array
    {
        $definitions = [
            [
                'first_name' => 'Mariana', 'last_name' => 'Vega', 'birth_date' => '1994-03-18',
                'gender' => 'female', 'email' => 'mariana.vega@example.com', 'phone' => '3005512233',
                'reason_for_consult' => 'Episodios de ansiedad anticipatoria asociados al entorno laboral.',
                'relevant_history' => 'Sin antecedentes psiquiatricos previos. Consumo ocasional de cafeina elevado.',
                'risk_level' => 'low', 'status' => 'active', 'psychologist_id' => $psychologistId,
            ],
            [
                'first_name' => 'Daniel', 'last_name' => 'Ortiz', 'birth_date' => '1987-11-02',
                'gender' => 'male', 'email' => 'daniel.ortiz@example.com', 'phone' => '3129987744',
                'reason_for_consult' => 'Estado de animo depresivo tras separacion reciente.',
                'relevant_history' => 'Episodio depresivo previo en 2019 tratado con psicoterapia.',
                'risk_level' => 'moderate', 'status' => 'active', 'psychologist_id' => $psychologistId,
            ],
            [
                'first_name' => 'Sofia', 'last_name' => 'Cardenas', 'birth_date' => '2001-07-25',
                'gender' => 'female', 'email' => 'sofia.cardenas@example.com', 'phone' => '3162244551',
                'reason_for_consult' => 'Dificultades de regulacion emocional y estres academico.',
                'relevant_history' => 'Acompanamiento psicopedagogico en bachillerato.',
                'risk_level' => 'none', 'status' => 'active', 'psychologist_id' => $adminId,
            ],
            [
                'first_name' => 'Julian', 'last_name' => 'Pena', 'birth_date' => '1979-01-14',
                'gender' => 'male', 'email' => 'julian.pena@example.com', 'phone' => '3014477882',
                'reason_for_consult' => 'Insomnio de conciliacion y rumiacion nocturna.',
                'relevant_history' => 'Hipertension controlada.',
                'risk_level' => 'none', 'status' => 'discharged', 'psychologist_id' => $psychologistId,
            ],
        ];

        $ids = [];
        $index = 1;

        foreach ($definitions as $definition) {
            $ids[] = Database::insert('patients', $definition + [
                'uuid' => uuid(),
                'record_number' => sprintf('HC-%s-%04d', date('Y'), $index),
                'document_type' => 'CC',
                'document_id' => (string) (1010000000 + $index * 7321),
                'city' => 'Bogota',
                'country' => 'Colombia',
                'emergency_contact_name' => 'Contacto familiar',
                'emergency_contact_phone' => '3001112233',
            ]);
            $index++;
        }

        return $ids;
    }

    private static function createPortalAccount(int $patientId): void
    {
        $patient = Database::first('SELECT * FROM patients WHERE id = :id', ['id' => $patientId]);

        Database::insert('users', [
            'uuid' => uuid(),
            'username' => strtolower((string) $patient['record_number']),
            'email' => (string) $patient['email'],
            'password_hash' => password_hash(self::PATIENT_PASSWORD, PASSWORD_DEFAULT),
            'full_name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'role' => 'patient',
            'patient_id' => $patientId,
        ]);
    }

    private static function createAppointments(array $patients, int $psychologistId): void
    {
        $slots = [
            ['-14 days', '09:00', 'completed', 'in_person'],
            ['-7 days', '09:00', 'completed', 'in_person'],
            ['-3 days', '11:00', 'completed', 'online'],
            ['-1 days', '15:00', 'no_show', 'in_person'],
            ['+1 days', '09:00', 'confirmed', 'in_person'],
            ['+2 days', '10:00', 'scheduled', 'online'],
            ['+3 days', '14:00', 'scheduled', 'in_person'],
            ['+6 days', '16:00', 'scheduled', 'online'],
        ];

        foreach ($slots as $index => [$offset, $time, $status, $modality]) {
            $starts = date('Y-m-d', strtotime($offset)) . ' ' . $time . ':00';

            Database::insert('appointments', [
                'uuid' => uuid(),
                'patient_id' => $patients[$index % count($patients)],
                'psychologist_id' => $psychologistId,
                'starts_at' => $starts,
                'ends_at' => date('Y-m-d H:i:s', strtotime($starts . ' +50 minutes')),
                'modality' => $modality,
                'status' => $status,
                'session_type' => $index === 0 ? 'Primera consulta' : 'Seguimiento',
                'location' => $modality === 'in_person' ? 'Consultorio 2' : null,
                'meeting_url' => $modality === 'online' ? 'https://meet.example.com/psiclinic' : null,
                'fee' => 120000,
            ]);
        }
    }

    private static function createNotes(array $patients, int $psychologistId): void
    {
        $notes = [
            [
                'patient' => 0, 'offset' => '-14 days', 'mood' => 5, 'risk' => 'low',
                'subjective' => 'Refiere tension sostenida durante la jornada laboral y dificultad para desconectar por las noches.',
                'objective' => 'Discurso coherente, tono ansioso. Verbaliza preocupacion anticipatoria recurrente.',
                'assessment' => 'Sintomatologia ansiosa compatible con ansiedad generalizada de intensidad leve a moderada.',
                'plan' => 'Psicoeducacion sobre el ciclo de la ansiedad, registro de preocupaciones y respiracion diafragmatica.',
            ],
            [
                'patient' => 0, 'offset' => '-7 days', 'mood' => 6, 'risk' => 'low',
                'subjective' => 'Reporta menor frecuencia de episodios de tension tras aplicar el registro de preocupaciones.',
                'objective' => 'Mayor apertura, afecto congruente. Completo la tarea asignada.',
                'assessment' => 'Evolucion favorable. Mantiene evitacion parcial de reuniones de equipo.',
                'plan' => 'Iniciar jerarquia de exposicion gradual a situaciones laborales evitadas.',
            ],
            [
                'patient' => 1, 'offset' => '-6 days', 'mood' => 3, 'risk' => 'moderate',
                'subjective' => 'Describe anhedonia, aislamiento social y alteracion del sueno desde hace cinco semanas.',
                'objective' => 'Enlentecimiento psicomotor leve, contacto visual reducido. Niega ideacion suicida estructurada.',
                'assessment' => 'Cuadro depresivo moderado reactivo a duelo por separacion.',
                'plan' => 'Activacion conductual con programacion de actividades gratificantes. Reevaluar riesgo cada sesion.',
            ],
            [
                'patient' => 2, 'offset' => '-4 days', 'mood' => 7, 'risk' => 'none',
                'subjective' => 'Consulta por estres academico previo a periodo de evaluaciones.',
                'objective' => 'Buen nivel de insight, colaboradora durante la sesion.',
                'assessment' => 'Estres situacional sin criterios de trastorno.',
                'plan' => 'Entrenamiento en organizacion del tiempo y tecnicas de regulacion emocional.',
            ],
        ];

        foreach ($notes as $index => $note) {
            Database::insert('clinical_notes', [
                'uuid' => uuid(),
                'patient_id' => $patients[$note['patient']],
                'author_id' => $psychologistId,
                'format' => 'soap',
                'session_number' => $index + 1,
                'session_date' => date('Y-m-d', strtotime($note['offset'])),
                'subjective' => $note['subjective'],
                'objective' => $note['objective'],
                'assessment' => $note['assessment'],
                'plan' => $note['plan'],
                'interventions' => 'Psicoeducación, Reestructuración cognitiva',
                'homework' => 'Registro diario de situaciones activadoras.',
                'mood_score' => $note['mood'],
                'risk_level' => $note['risk'],
                'is_locked' => $index < 2 ? 1 : 0,
                'locked_at' => $index < 2 ? date('Y-m-d H:i:s', strtotime($note['offset'])) : null,
            ]);
        }
    }

    private static function createAssessments(array $patients, int $psychologistId): void
    {
        $applications = [
            ['patient' => 0, 'code' => 'GAD-7', 'offset' => '-28 days', 'answers' => [3, 3, 2, 2, 2, 2, 1]],
            ['patient' => 0, 'code' => 'GAD-7', 'offset' => '-14 days', 'answers' => [2, 2, 2, 1, 1, 1, 1]],
            ['patient' => 0, 'code' => 'GAD-7', 'offset' => '-3 days', 'answers' => [1, 1, 1, 1, 0, 1, 0]],
            ['patient' => 1, 'code' => 'PHQ-9', 'offset' => '-21 days', 'answers' => [3, 3, 2, 3, 2, 2, 2, 1, 0]],
            ['patient' => 1, 'code' => 'PHQ-9', 'offset' => '-6 days', 'answers' => [2, 2, 2, 2, 1, 2, 1, 1, 0]],
            ['patient' => 2, 'code' => 'PSS-10', 'offset' => '-4 days', 'answers' => [3, 2, 3, 1, 2, 1, 2, 2, 3, 2]],
            ['patient' => 2, 'code' => 'WHO-5', 'offset' => '-4 days', 'answers' => [3, 3, 2, 2, 3]],
        ];

        foreach ($applications as $application) {
            $id = Database::insert('assessments', [
                'uuid' => uuid(),
                'patient_id' => $patients[$application['patient']],
                'instrument_code' => $application['code'],
                'assigned_by' => $psychologistId,
                'status' => 'pending',
            ]);

            $result = \PsiClinic\Domain\Instruments::score($application['code'], $application['answers']);

            Database::update('assessments', $id, [
                'answers' => json_encode($application['answers']),
                'total_score' => $result['total'],
                'subscale_scores' => json_encode($result['subscales'], JSON_UNESCAPED_UNICODE),
                'severity' => $result['severity'],
                'interpretation' => $result['interpretation'],
                'status' => 'completed',
                'administered_at' => date('Y-m-d H:i:s', strtotime($application['offset'])),
            ]);
        }

        Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => $patients[0],
            'instrument_code' => 'DASS-21',
            'assigned_by' => $psychologistId,
            'status' => 'pending',
        ]);
    }

    private static function createConsents(array $patients, int $psychologistId): void
    {
        $templates = \PsiClinic\Domain\ConsentTemplates::all();

        foreach ([['general', 0, true], ['teleconsulta', 0, false], ['datos', 1, true]] as [$code, $patientIndex, $signed]) {
            $patientId = $patients[$patientIndex];
            $patient = Database::first('SELECT * FROM patients WHERE id = :id', ['id' => $patientId]);

            Database::insert('consents', [
                'uuid' => uuid(),
                'patient_id' => $patientId,
                'template_code' => $code,
                'title' => $templates[$code]['title'],
                'body' => $templates[$code]['body'],
                'status' => $signed ? 'signed' : 'pending',
                'signed_name' => $signed ? $patient['first_name'] . ' ' . $patient['last_name'] : null,
                'signed_at' => $signed ? date('Y-m-d H:i:s', strtotime('-20 days')) : null,
                'signed_ip' => $signed ? '127.0.0.1' : null,
                'created_by' => $psychologistId,
            ]);
        }
    }

    private static function createInvoices(array $patients, int $adminId): void
    {
        foreach ([[0, 2, true], [1, 3, false]] as $index => [$patientIndex, $sessions, $paid]) {
            $unitPrice = 120000.0;
            $subtotal = $unitPrice * $sessions;

            $invoiceId = Database::insert('invoices', [
                'uuid' => uuid(),
                'patient_id' => $patients[$patientIndex],
                'number' => sprintf('FAC-%s-%04d', date('Y'), $index + 1),
                'issued_at' => date('Y-m-d', strtotime('-10 days')),
                'due_at' => date('Y-m-d', strtotime('+5 days')),
                'subtotal' => $subtotal,
                'tax' => 0,
                'total' => $subtotal,
                'status' => $paid ? 'paid' : 'issued',
                'created_by' => $adminId,
            ]);

            Database::insert('invoice_items', [
                'invoice_id' => $invoiceId,
                'description' => 'Sesion de psicoterapia individual',
                'quantity' => $sessions,
                'unit_price' => $unitPrice,
                'amount' => $subtotal,
            ]);

            if ($paid) {
                Database::insert('payments', [
                    'invoice_id' => $invoiceId,
                    'paid_at' => date('Y-m-d', strtotime('-8 days')),
                    'amount' => $subtotal,
                    'method' => 'transfer',
                    'reference' => 'TRX-99120',
                    'created_by' => $adminId,
                ]);
            }
        }
    }

    private static function createSettings(): void
    {
        $values = [
            'clinic_name' => 'PsiClinic',
            'clinic_tagline' => 'Centro de atención psicológica',
            'clinic_email' => 'contacto@psiclinic.local',
            'clinic_phone' => '+57 601 000 0000',
            'clinic_address' => 'Calle 100 #15-20, Bogotá',
            'currency' => 'COP',
            'session_duration' => '50',
            'default_fee' => '120000',
            'working_hours_start' => '07:00',
            'working_hours_end' => '19:00',
            'note_lock_hours' => '72',
            'clinic_about' => 'Somos un equipo de psicología clínica que acompaña procesos individuales y familiares con calma, respeto y confidencialidad. Cada proceso empieza por escucharte.',
            'whatsapp_number' => '',
            'crisis_line' => '123',
        ];

        foreach ($values as $key => $value) {
            Database::run(
                'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                ['k' => $key, 'v' => $value]
            );
        }
    }

    private static function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
