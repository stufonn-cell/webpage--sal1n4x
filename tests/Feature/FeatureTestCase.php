<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PDOException;
use PsiClinic\Core\Database;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Installer;
use PsiClinic\Tests\SkippedTest;
use PsiClinic\Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    private static bool $schemaReady = false;

    public function setUp(): void
    {
        $this->requireDatabase();
        $this->prepareSchema();
        $this->truncateTables();
    }

    protected function requireDatabase(): void
    {
        try {
            Database::connection();
        } catch (PDOException $exception) {
            throw new SkippedTest('base de datos no disponible');
        }
    }

    protected function prepareSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        Installer::migrate();
        self::$schemaReady = true;
    }

    protected function truncateTables(): void
    {
        $tables = [
            'payments', 'invoice_items', 'invoices', 'documents', 'consents',
            'assessments', 'diagnoses', 'clinical_notes', 'appointments',
            'audit_log', 'settings',
        ];

        Database::run('SET FOREIGN_KEY_CHECKS = 0');
        Database::run('UPDATE users SET patient_id = NULL');
        Database::run('DELETE FROM patients');
        foreach ($tables as $table) {
            Database::run(sprintf('TRUNCATE TABLE `%s`', $table));
        }
        Database::run('DELETE FROM users');
        Database::run('ALTER TABLE users AUTO_INCREMENT = 1');
        Database::run('ALTER TABLE patients AUTO_INCREMENT = 1');
        Database::run('SET FOREIGN_KEY_CHECKS = 1');

        Settings::flush();
    }

    protected function createUser(string $role = 'psychologist', string $username = 'tester'): int
    {
        return Database::insert('users', [
            'uuid' => uuid(),
            'username' => $username,
            'email' => $username . '@psiclinic.test',
            'password_hash' => password_hash('Clave12345', PASSWORD_DEFAULT),
            'full_name' => 'Usuario ' . $username,
            'role' => $role,
        ]);
    }

    protected function createPatient(array $overrides = []): int
    {
        static $sequence = 0;
        $sequence++;

        return Database::insert('patients', $overrides + [
            'uuid' => uuid(),
            'record_number' => sprintf('HC-TEST-%04d', $sequence),
            'first_name' => 'Paciente',
            'last_name' => 'Numero ' . $sequence,
            'birth_date' => '1990-01-15',
            'gender' => 'undisclosed',
            'email' => 'paciente' . $sequence . '@psiclinic.test',
            'status' => 'active',
            'risk_level' => 'none',
        ]);
    }

    protected function createAppointment(int $patientId, int $psychologistId, string $startsAt, array $overrides = []): int
    {
        return Database::insert('appointments', $overrides + [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'psychologist_id' => $psychologistId,
            'starts_at' => $startsAt,
            'ends_at' => date('Y-m-d H:i:s', strtotime($startsAt . ' +50 minutes')),
            'modality' => 'in_person',
            'status' => 'scheduled',
            'fee' => 120000,
        ]);
    }

    protected function createNote(int $patientId, int $authorId, array $overrides = []): int
    {
        return Database::insert('clinical_notes', $overrides + [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'author_id' => $authorId,
            'format' => 'soap',
            'session_number' => 1,
            'session_date' => date('Y-m-d'),
            'subjective' => 'Relato del paciente.',
            'risk_level' => 'none',
        ]);
    }
}
