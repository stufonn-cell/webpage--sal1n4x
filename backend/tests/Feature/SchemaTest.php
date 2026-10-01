<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Core\Database;
use PsiClinic\Core\Env;

final class SchemaTest extends FeatureTestCase
{
    private const EXPECTED_TABLES = [
        'users', 'patients', 'appointments', 'clinical_notes', 'diagnoses',
        'assessments', 'consents', 'documents', 'invoices', 'invoice_items',
        'payments', 'audit_log', 'settings', 'appointment_requests', 'login_attempts',
        'icd11_codes', 'rips_reports', 'rips_report_items',
    ];

    public function testMigrationsCreateEveryTable(): void
    {
        $found = array_column(Database::all(
            'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = :schema',
            ['schema' => (string) Env::get('DB_DATABASE')]
        ), 'name');

        foreach (self::EXPECTED_TABLES as $table) {
            $this->assertTrue(in_array($table, $found, true), 'Missing table ' . $table);
        }
    }

    public function testEveryTableUsesInnoDbAndUtf8mb4(): void
    {
        $rows = Database::all(
            'SELECT table_name AS name, engine AS engine, table_collation AS collation
             FROM information_schema.tables WHERE table_schema = :schema',
            ['schema' => (string) Env::get('DB_DATABASE')]
        );

        foreach ($rows as $row) {
            $this->assertSame('InnoDB', $row['engine'], $row['name'] . ' does not use InnoDB');
            $this->assertContains('utf8mb4', (string) $row['collation'], $row['name'] . ' is not utf8mb4');
        }
    }

    public function testForeignKeysProtectClinicalData(): void
    {
        $constraints = Database::all(
            'SELECT table_name AS child, referenced_table_name AS parent, delete_rule AS delete_rule
             FROM information_schema.referential_constraints
             WHERE constraint_schema = :schema',
            ['schema' => (string) Env::get('DB_DATABASE')]
        );

        $map = [];
        foreach ($constraints as $constraint) {
            $map[$constraint['child'] . '->' . $constraint['parent']] = $constraint['delete_rule'];
        }

        $this->assertSame('CASCADE', $map['appointments->patients'] ?? null);
        $this->assertSame('CASCADE', $map['clinical_notes->patients'] ?? null);
        $this->assertSame('CASCADE', $map['assessments->patients'] ?? null);
        $this->assertSame('CASCADE', $map['invoice_items->invoices'] ?? null);
    }

    public function testDeletingAPatientRemovesItsClinicalRecords(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $userId, date('Y-m-d 09:00:00'));
        $this->createNote($patientId, $userId);

        Database::delete('patients', $patientId);

        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM appointments WHERE patient_id = :id', ['id' => $patientId]));
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM clinical_notes WHERE patient_id = :id', ['id' => $patientId]));
    }

    public function testRecordNumberIsUnique(): void
    {
        $this->createPatient(['record_number' => 'MR-DUP-0001']);

        $this->assertThrows(
            fn () => $this->createPatient(['record_number' => 'MR-DUP-0001']),
            'The record number must be unique'
        );
    }

    public function testTransactionsRollBackOnFailure(): void
    {
        $before = (int) Database::value('SELECT COUNT(*) FROM patients');

        try {
            Database::transaction(function (): void {
                $this->createPatient(['record_number' => 'MR-TX-0001']);
                throw new \RuntimeException('intentional failure');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame($before, (int) Database::value('SELECT COUNT(*) FROM patients'));
    }
}
