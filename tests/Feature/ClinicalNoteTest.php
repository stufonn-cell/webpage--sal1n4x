<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Core\Database;
use PsiClinic\Domain\Notes;

final class ClinicalNoteTest extends FeatureTestCase
{
    public function testSessionNumbersIncrementPerPatient(): void
    {
        $userId = $this->createUser();
        $firstPatient = $this->createPatient();
        $secondPatient = $this->createPatient();

        $this->assertSame(1, Notes::nextSessionNumber($firstPatient));

        $this->createNote($firstPatient, $userId, ['session_number' => 1]);
        $this->createNote($firstPatient, $userId, ['session_number' => 2]);

        $this->assertSame(3, Notes::nextSessionNumber($firstPatient));
        $this->assertSame(1, Notes::nextSessionNumber($secondPatient), 'Cada paciente lleva su propia numeracion');
    }

    public function testNotesAreListedFromNewestToOldest(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createNote($patientId, $userId, ['session_number' => 1, 'session_date' => '2026-01-10']);
        $this->createNote($patientId, $userId, ['session_number' => 2, 'session_date' => '2026-02-10']);

        $notes = Notes::forPatient($patientId);

        $this->assertSame('2026-02-10', $notes[0]['session_date']);
    }

    public function testFindJoinsPatientAndAuthorDetails(): void
    {
        $userId = $this->createUser('psychologist', 'laura');
        Database::update('users', $userId, ['license_number' => 'TP-48120']);
        $patientId = $this->createPatient(['first_name' => 'Mariana', 'last_name' => 'Vega']);

        $note = Notes::find($this->createNote($patientId, $userId));

        $this->assertSame('Mariana', $note['first_name']);
        $this->assertSame('Usuario laura', $note['author_name']);
        $this->assertSame('TP-48120', $note['license_number']);
    }

    public function testSearchLooksInsideTheNoteBody(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createNote($patientId, $userId, ['assessment' => 'Sintomatologia ansiosa en remision']);
        $this->createNote($patientId, $userId, ['session_number' => 2, 'assessment' => 'Duelo no complicado']);

        $this->assertCount(1, Notes::paginate('remision')['rows']);
        $this->assertCount(1, Notes::paginate('Duelo')['rows']);
        $this->assertCount(2, Notes::paginate('')['rows']);
    }

    public function testMoodSeriesSkipsNotesWithoutAScore(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createNote($patientId, $userId, ['session_date' => '2026-01-10', 'mood_score' => 4]);
        $this->createNote($patientId, $userId, ['session_number' => 2, 'session_date' => '2026-02-10', 'mood_score' => null]);
        $this->createNote($patientId, $userId, ['session_number' => 3, 'session_date' => '2026-03-10', 'mood_score' => 7]);

        $series = Notes::moodSeries($patientId);

        $this->assertCount(2, $series);
        $this->assertSame(4, (int) $series[0]['mood_score'], 'La serie va en orden cronologico');
        $this->assertSame(7, (int) $series[1]['mood_score']);
    }

    public function testSigningLocksTheNote(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();
        $noteId = $this->createNote($patientId, $userId);

        $this->assertSame(0, (int) Database::value('SELECT is_locked FROM clinical_notes WHERE id = :id', ['id' => $noteId]));

        Database::update('clinical_notes', $noteId, ['is_locked' => 1, 'locked_at' => date('Y-m-d H:i:s')]);

        $note = Notes::find($noteId);

        $this->assertSame(1, (int) $note['is_locked']);
        $this->assertNotNull($note['locked_at']);
    }

    public function testDeletingAnAppointmentKeepsTheNote(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();
        $appointmentId = $this->createAppointment($patientId, $userId, '2026-08-11 09:00:00');
        $noteId = $this->createNote($patientId, $userId, ['appointment_id' => $appointmentId]);

        Database::delete('appointments', $appointmentId);

        $note = Notes::find($noteId);

        $this->assertNotNull($note, 'La nota clinica no se elimina con la cita');
        $this->assertNull($note['appointment_id']);
    }

    public function testEveryDeclaredFormatIsAccepted(): void
    {
        $userId = $this->createUser();
        $patientId = $this->createPatient();

        foreach (array_keys(Notes::FORMATS) as $index => $format) {
            $this->createNote($patientId, $userId, ['session_number' => $index + 1, 'format' => $format]);
        }

        $this->assertCount(count(Notes::FORMATS), Notes::forPatient($patientId));
    }
}
