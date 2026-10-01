<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use DateTimeImmutable;
use PsiClinic\Core\Database;
use PsiClinic\Domain\Appointments;

final class AppointmentTest extends FeatureTestCase
{
    public function testAnOverlappingAppointmentIsDetected(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $psychologistId, '2026-08-10 09:00:00');

        $this->assertTrue(
            Appointments::hasConflict($psychologistId, '2026-08-10 09:30:00', '2026-08-10 10:20:00'),
            'Un solape parcial debe detectarse'
        );
    }

    public function testAdjacentAppointmentsDoNotConflict(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $psychologistId, '2026-08-10 09:00:00');

        $this->assertFalse(
            Appointments::hasConflict($psychologistId, '2026-08-10 09:50:00', '2026-08-10 10:40:00'),
            'Una cita que empieza justo al terminar la anterior es valida'
        );
    }

    public function testAnAppointmentDoesNotConflictWithItself(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();
        $appointmentId = $this->createAppointment($patientId, $psychologistId, '2026-08-10 09:00:00');

        $this->assertFalse(
            Appointments::hasConflict($psychologistId, '2026-08-10 09:00:00', '2026-08-10 09:50:00', $appointmentId),
            'Al editar, la propia cita se excluye de la comprobacion'
        );
    }

    public function testCancelledAppointmentsFreeTheSlot(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $psychologistId, '2026-08-10 09:00:00', ['status' => 'cancelled']);

        $this->assertFalse(Appointments::hasConflict($psychologistId, '2026-08-10 09:00:00', '2026-08-10 09:50:00'));
    }

    public function testTwoProfessionalsCanShareTheSameSlot(): void
    {
        $firstPsychologist = $this->createUser('psychologist', 'uno');
        $secondPsychologist = $this->createUser('psychologist', 'dos');
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $firstPsychologist, '2026-08-10 09:00:00');

        $this->assertFalse(Appointments::hasConflict($secondPsychologist, '2026-08-10 09:00:00', '2026-08-10 09:50:00'));
    }

    public function testTheWeekViewAlwaysReturnsSevenDays(): void
    {
        $week = Appointments::week(new DateTimeImmutable('2026-08-12'));

        $this->assertCount(7, $week['days']);
        $this->assertSame('2026-08-10', array_key_first($week['days']), 'La semana empieza en lunes');
        $this->assertSame('2026-08-16', array_key_last($week['days']));
    }

    public function testAppointmentsAreGroupedInTheirDay(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $psychologistId, '2026-08-11 09:00:00');
        $this->createAppointment($patientId, $psychologistId, '2026-08-11 11:00:00');
        $this->createAppointment($patientId, $psychologistId, '2026-08-13 15:00:00');

        $week = Appointments::week(new DateTimeImmutable('2026-08-12'));

        $this->assertCount(2, $week['days']['2026-08-11']);
        $this->assertCount(1, $week['days']['2026-08-13']);
        $this->assertCount(0, $week['days']['2026-08-12']);
    }

    public function testAppointmentsOutsideTheWeekAreExcluded(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $psychologistId, '2026-08-09 09:00:00');
        $this->createAppointment($patientId, $psychologistId, '2026-08-17 09:00:00');

        $week = Appointments::week(new DateTimeImmutable('2026-08-12'));

        $this->assertSame(0, array_sum(array_map('count', $week['days'])));
    }

    public function testUpcomingOnlyReturnsFutureActiveAppointments(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $psychologistId, date('Y-m-d H:i:s', strtotime('-2 days')));
        $this->createAppointment($patientId, $psychologistId, date('Y-m-d H:i:s', strtotime('+2 days')));
        $this->createAppointment($patientId, $psychologistId, date('Y-m-d H:i:s', strtotime('+3 days')), ['status' => 'cancelled']);

        $this->assertCount(1, Appointments::upcoming());
    }

    public function testForDayFiltersByCalendarDate(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();

        $this->createAppointment($patientId, $psychologistId, '2026-08-11 08:00:00');
        $this->createAppointment($patientId, $psychologistId, '2026-08-11 20:00:00');
        $this->createAppointment($patientId, $psychologistId, '2026-08-12 08:00:00');

        $this->assertCount(2, Appointments::forDay('2026-08-11'));
    }

    public function testFindJoinsThePatientData(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient(['first_name' => 'Julian', 'last_name' => 'Pena']);
        $appointmentId = $this->createAppointment($patientId, $psychologistId, '2026-08-11 08:00:00');

        $appointment = Appointments::find($appointmentId);

        $this->assertNotNull($appointment);
        $this->assertSame('Julian', $appointment['first_name']);
    }

    public function testEveryStatusAndModalityInTheDatabaseIsLabelled(): void
    {
        $psychologistId = $this->createUser();
        $patientId = $this->createPatient();

        foreach (array_keys(Appointments::STATUSES) as $index => $status) {
            $this->createAppointment($patientId, $psychologistId, sprintf('2026-09-%02d 09:00:00', $index + 1), ['status' => $status]);
        }

        $stored = array_column(Database::all('SELECT DISTINCT status FROM appointments'), 'status');

        foreach ($stored as $status) {
            $this->assertArrayHasKey($status, Appointments::STATUSES);
        }
    }
}
