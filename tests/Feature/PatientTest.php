<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Core\Database;
use PsiClinic\Domain\Patients;

final class PatientTest extends FeatureTestCase
{
    public function testRecordNumbersFollowTheYearlySequence(): void
    {
        $year = date('Y');

        $this->assertSame(sprintf('HC-%s-0001', $year), Patients::nextRecordNumber());

        Database::insert('patients', [
            'uuid' => uuid(),
            'record_number' => sprintf('HC-%s-0001', $year),
            'first_name' => 'Primero',
            'last_name' => 'Paciente',
            'gender' => 'undisclosed',
        ]);

        $this->assertSame(sprintf('HC-%s-0002', $year), Patients::nextRecordNumber());
    }

    public function testFindReturnsTheAssignedProfessional(): void
    {
        $psychologistId = $this->createUser('psychologist', 'laura');
        $patientId = $this->createPatient(['psychologist_id' => $psychologistId]);

        $patient = Patients::find($patientId);

        $this->assertNotNull($patient);
        $this->assertSame('Usuario laura', $patient['psychologist_name']);
    }

    public function testFindReturnsNullForAMissingPatient(): void
    {
        $this->assertNull(Patients::find(999999));
    }

    public function testSearchMatchesNameDocumentAndRecordNumber(): void
    {
        $this->createPatient(['first_name' => 'Mariana', 'last_name' => 'Vega', 'document_id' => '1015998877']);
        $this->createPatient(['first_name' => 'Daniel', 'last_name' => 'Ortiz', 'document_id' => '1020112233']);

        $this->assertCount(1, Patients::paginate('Mariana')['rows']);
        $this->assertCount(1, Patients::paginate('Ortiz')['rows']);
        $this->assertCount(1, Patients::paginate('1015998877')['rows']);
        $this->assertCount(0, Patients::paginate('inexistente')['rows']);
    }

    public function testFilteringByStatus(): void
    {
        $this->createPatient(['status' => 'active']);
        $this->createPatient(['status' => 'discharged']);
        $this->createPatient(['status' => 'discharged']);

        $this->assertCount(1, Patients::paginate('', 'active')['rows']);
        $this->assertCount(2, Patients::paginate('', 'discharged')['rows']);
        $this->assertCount(3, Patients::paginate('', '')['rows']);
    }

    public function testPaginationSplitsTheResultSet(): void
    {
        for ($index = 0; $index < 7; $index++) {
            $this->createPatient();
        }

        $firstPage = Patients::paginate('', '', 1, 3);
        $lastPage = Patients::paginate('', '', 3, 3);

        $this->assertSame(7, $firstPage['total']);
        $this->assertSame(3, $firstPage['pages']);
        $this->assertCount(3, $firstPage['rows']);
        $this->assertCount(1, $lastPage['rows']);
    }

    public function testListingCountsSessionsAndLastSessionDate(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createNote($patientId, $userId, ['session_number' => 1, 'session_date' => '2026-01-10']);
        $this->createNote($patientId, $userId, ['session_number' => 2, 'session_date' => '2026-02-20']);

        $row = Patients::paginate()['rows'][0];

        $this->assertSame(2, (int) $row['sessions_count']);
        $this->assertSame('2026-02-20', $row['last_session']);
    }

    public function testTimelineMergesNotesAppointmentsAndAssessments(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createNote($patientId, $userId, ['session_date' => '2026-03-01']);
        $this->createAppointment($patientId, $userId, '2026-03-05 09:00:00');
        Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'instrument_code' => 'GAD-7',
            'status' => 'completed',
            'total_score' => 9,
            'severity' => 'Leve',
            'administered_at' => '2026-03-10 10:00:00',
        ]);

        $timeline = Patients::timeline($patientId);

        $this->assertCount(3, $timeline);
        $this->assertSame('assessment', $timeline[0]['type'], 'El evento mas reciente va primero');
        $this->assertSame('note', $timeline[2]['type']);
    }

    public function testOptionsAreFormattedForSelectInputs(): void
    {
        $this->createPatient(['first_name' => 'Sofia', 'last_name' => 'Cardenas', 'record_number' => 'HC-2026-0009']);

        $options = Patients::options();

        $this->assertCount(1, $options);
        $this->assertSame('Cardenas, Sofia (HC-2026-0009)', $options[0]['label']);
    }

    public function testFullNameJoinsBothParts(): void
    {
        $patient = ['first_name' => 'Mariana', 'last_name' => 'Vega'];

        $this->assertSame('Mariana Vega', Patients::fullName($patient));
    }
}
