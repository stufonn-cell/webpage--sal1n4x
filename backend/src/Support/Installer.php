<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Support;

use PsiClinic\Core\Database;
use PsiClinic\Core\Env;
use PsiClinic\Domain\Icd11;

final class Installer
{
    private const DEMO_PASSWORD = 'Psiclinic2026';
    private const PATIENT_PASSWORD = 'Patient2026';

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
            self::line('Migration applied: ' . basename($file));
        }

        if (!Icd11::isLoaded()) {
            self::importIcd11();
        }
    }

    /** Loads (or refreshes) the ICD-11 catalog from database/data. */
    public static function importIcd11(): void
    {
        $rows = Icd11::import(dirname(__DIR__, 2) . '/database/data');
        self::line(sprintf('ICD-11 catalog %s loaded: %d codes.', Icd11::RELEASE, $rows));
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

        self::line('Database cleared.');
        self::install();
    }

    public static function seed(): void
    {
        if ((int) Database::value('SELECT COUNT(*) FROM users') > 0) {
            self::line('The database already has data. Use "fresh" to start over.');

            return;
        }

        $adminId = self::createUser('admin', 'admin@psiclinic.local', 'Ana Salinas', 'admin', 'TP-10234', '1020304050');
        $psychologistId = self::createUser('l.moreno', 'l.moreno@psiclinic.local', 'Laura Moreno', 'psychologist', 'TP-48120', '1030405060');
        self::createUser('c.rojas', 'c.rojas@psiclinic.local', 'Camilo Rojas', 'assistant', null, null);

        // Profile shown on the public demo site.
        Database::update('users', $psychologistId, [
            'show_on_site' => 1,
            'public_bio' => 'Supports adults and teenagers through anxiety, low mood and emotion regulation, using a cognitive behavioral approach.',
        ]);

        $patients = self::createPatients($psychologistId, $adminId);
        $portalUsername = self::createPortalAccount($patients[0]);
        self::createDiagnoses($patients, $psychologistId);
        self::createAppointments($patients, $psychologistId);
        self::createNotes($patients, $psychologistId);
        self::createAssessments($patients, $psychologistId);
        self::createConsents($patients, $psychologistId);
        self::createInvoices($patients, $adminId);
        self::createSettings();

        self::line('Demo data loaded.');
        self::line('  admin / ' . self::DEMO_PASSWORD);
        self::line('  l.moreno / ' . self::DEMO_PASSWORD);
        self::line('  ' . $portalUsername . ' / ' . self::PATIENT_PASSWORD . ' (patient portal)');
    }

    public static function printHelp(array $commands): void
    {
        self::line('PsiClinic - command line tools');
        self::line('Usage: php bin/console <command>');
        self::line('');

        foreach ($commands as $name => $description) {
            self::line(sprintf('  %-10s %s', $name, $description));
        }
    }

    private static function runScript(string $script): void
    {
        $connection = Database::connection();

        foreach (self::splitStatements($script) as $statement) {
            // query() + closeCursor() instead of exec(): statements such as
            // EXECUTE can return rows, and if they are not consumed the next
            // one fails with "unbuffered queries are active" (2014).
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
        ?string $license,
        ?string $documentNumber
    ): int {
        return Database::insert('users', [
            'uuid' => uuid(),
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT),
            'full_name' => $fullName,
            'document_type' => 'CC',
            'document_number' => $documentNumber,
            'role' => $role,
            'license_number' => $license,
            'specialty' => $role === 'psychologist' ? 'Cognitive behavioral therapy' : null,
        ]);
    }

    private static function createPatients(int $psychologistId, int $adminId): array
    {
        $definitions = [
            [
                'first_name' => 'Mariana', 'last_name' => 'Vega', 'birth_date' => '1994-03-18',
                'gender' => 'female', 'email' => 'mariana.vega@example.com', 'phone' => '3005512233',
                'reason_for_consult' => 'Episodes of anticipatory anxiety linked to her work environment.',
                'relevant_history' => 'No previous psychiatric history. Occasional high caffeine intake.',
                'risk_level' => 'low', 'status' => 'active', 'psychologist_id' => $psychologistId,
            ],
            [
                'first_name' => 'Daniel', 'last_name' => 'Ortiz', 'birth_date' => '1987-11-02',
                'gender' => 'male', 'email' => 'daniel.ortiz@example.com', 'phone' => '3129987744',
                'reason_for_consult' => 'Depressed mood after a recent separation.',
                'relevant_history' => 'Previous depressive episode in 2019, treated with psychotherapy.',
                'risk_level' => 'moderate', 'status' => 'active', 'psychologist_id' => $psychologistId,
            ],
            [
                'first_name' => 'Sofia', 'last_name' => 'Cardenas', 'birth_date' => '2001-07-25',
                'gender' => 'female', 'email' => 'sofia.cardenas@example.com', 'phone' => '3162244551',
                'reason_for_consult' => 'Difficulty regulating emotions and academic stress.',
                'relevant_history' => 'Educational psychology support during high school.',
                'risk_level' => 'none', 'status' => 'active', 'psychologist_id' => $adminId,
            ],
            [
                'first_name' => 'Julian', 'last_name' => 'Pena', 'birth_date' => '1979-01-14',
                'gender' => 'male', 'email' => 'julian.pena@example.com', 'phone' => '3014477882',
                'reason_for_consult' => 'Trouble falling asleep and rumination at night.',
                'relevant_history' => 'Controlled hypertension.',
                'risk_level' => 'none', 'status' => 'discharged', 'psychologist_id' => $psychologistId,
            ],
        ];

        $ids = [];
        $index = 1;

        foreach ($definitions as $definition) {
            $ids[] = Database::insert('patients', $definition + [
                'uuid' => uuid(),
                'record_number' => sprintf('MR-%s-%04d', date('Y'), $index),
                'document_type' => 'CC',
                'document_id' => (string) (1010000000 + $index * 7321),
                'city' => 'Bogota',
                'country' => 'Colombia',
                'emergency_contact_name' => 'Family contact',
                'emergency_contact_phone' => '3001112233',
                // Data RIPS needs for each user: Bogota (DIVIPOLA 11001), urban zone, Colombia (170).
                'biological_sex' => $definition['gender'] === 'female' ? 'F' : 'M',
                'rips_user_type' => '12',
                'residence_country' => '170',
                'residence_municipality' => '11001',
                'residence_zone' => '01',
                'origin_country' => '170',
            ]);
            $index++;
        }

        return $ids;
    }

    /** Creates the portal account of a patient and returns its username. */
    private static function createPortalAccount(int $patientId): string
    {
        $patient = Database::first('SELECT * FROM patients WHERE id = :id', ['id' => $patientId]);
        $username = strtolower((string) $patient['record_number']);

        Database::insert('users', [
            'uuid' => uuid(),
            'username' => $username,
            'email' => (string) $patient['email'],
            'password_hash' => password_hash(self::PATIENT_PASSWORD, PASSWORD_DEFAULT),
            'full_name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'role' => 'patient',
            'patient_id' => $patientId,
        ]);

        return $username;
    }

    /**
     * ICD-11 diagnoses with their ICD-10 equivalent, as RIPS requires. The third
     * patient has none on purpose: the RIPS screen lists her as missing data.
     */
    private static function createDiagnoses(array $patients, int $psychologistId): void
    {
        $diagnoses = [
            [
                'patient' => 0, 'code' => '6B00', 'status' => 'active', 'onset' => '-8 months',
                'notes' => 'Persistent worry about work, with muscle tension and poor sleep.',
            ],
            [
                'patient' => 1, 'code' => '6A70.1', 'status' => 'active', 'onset' => '-2 months',
                'notes' => 'Began after the separation. Reassess risk every session.',
            ],
            [
                'patient' => 3, 'code' => '7A00', 'status' => 'resolved', 'onset' => '-1 year',
                'notes' => 'Sleep improved with stimulus control and sleep hygiene.',
            ],
        ];

        $createdAt = date('Y-m-d H:i:s', strtotime('-15 days'));

        foreach ($diagnoses as $diagnosis) {
            $entry = Icd11::find($diagnosis['code']);
            if ($entry === null) {
                continue;
            }

            Database::insert('diagnoses', [
                'patient_id' => $patients[$diagnosis['patient']],
                'system' => 'icd11',
                'code' => $entry['code'],
                'icd10_code' => $entry['icd10_code'],
                'title' => mb_substr($entry['title'], 0, 200),
                'status' => $diagnosis['status'],
                'is_primary' => 1,
                'onset_date' => date('Y-m-d', strtotime($diagnosis['onset'])),
                'notes' => $diagnosis['notes'],
                'created_by' => $psychologistId,
                'created_at' => $createdAt,
            ]);
        }
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
                'session_type' => $index === 0 ? 'First consultation' : 'Follow-up',
                'location' => $modality === 'in_person' ? 'Office 2' : null,
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
                'subjective' => 'Reports constant tension during the workday and trouble switching off at night.',
                'objective' => 'Coherent speech, anxious tone. Describes recurring anticipatory worry.',
                'assessment' => 'Anxiety symptoms consistent with generalized anxiety of mild to moderate intensity.',
                'plan' => 'Psychoeducation on the anxiety cycle, a worry log and diaphragmatic breathing.',
            ],
            [
                'patient' => 0, 'offset' => '-7 days', 'mood' => 6, 'risk' => 'low',
                'subjective' => 'Reports fewer episodes of tension since she started using the worry log.',
                'objective' => 'More open, congruent affect. Completed the assigned homework.',
                'assessment' => 'Good progress. Still partly avoids team meetings.',
                'plan' => 'Start a graded exposure hierarchy for the work situations she avoids.',
            ],
            [
                'patient' => 1, 'offset' => '-6 days', 'mood' => 3, 'risk' => 'moderate',
                'subjective' => 'Describes anhedonia, social withdrawal and disturbed sleep over the last five weeks.',
                'objective' => 'Mild psychomotor slowing, reduced eye contact. Denies structured suicidal ideation.',
                'assessment' => 'Moderate depressive picture in reaction to grief over the separation.',
                'plan' => 'Behavioral activation, scheduling rewarding activities. Reassess risk every session.',
            ],
            [
                'patient' => 2, 'offset' => '-4 days', 'mood' => 7, 'risk' => 'none',
                'subjective' => 'Seeks help for academic stress ahead of the exam period.',
                'objective' => 'Good insight, cooperative during the session.',
                'assessment' => 'Situational stress that does not meet criteria for a disorder.',
                'plan' => 'Time management training and emotion regulation techniques.',
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
                'interventions' => 'Psychoeducation, Cognitive restructuring',
                'homework' => 'Daily log of triggering situations.',
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

        foreach ([['general', 0, true], ['telehealth', 0, false], ['data_processing', 1, true]] as [$code, $patientIndex, $signed]) {
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
                'number' => sprintf('INV-%s-%04d', date('Y'), $index + 1),
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
                'description' => 'Individual psychotherapy session',
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
            'clinic_tagline' => 'Psychological care center',
            'clinic_email' => 'contact@psiclinic.local',
            'clinic_phone' => '+57 601 000 0000',
            'clinic_address' => 'Calle 100 #15-20, Bogota',
            'currency' => 'COP',
            'session_duration' => '50',
            'default_fee' => '120000',
            'working_hours_start' => '07:00',
            'working_hours_end' => '19:00',
            'note_lock_hours' => '72',
            'clinic_about' => 'We are a clinical psychology team that supports individuals and families with calm, respect and confidentiality. Every process starts by listening to you.',
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
