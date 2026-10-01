<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Database;
use PsiClinic\Domain\Metrics;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Installer;

final class InstallerTest extends FeatureTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        ob_start();
        Installer::seed();
        ob_end_clean();
    }

    public function testSeedCreatesTheDemonstrationAccounts(): void
    {
        foreach (['admin', 'l.moreno', 'c.rojas', 'mr-' . strtolower(date('Y')) . '-0001'] as $username) {
            $this->assertNotNull(
                Database::first('SELECT id FROM users WHERE username = :u', ['u' => $username]),
                'Missing user ' . $username
            );
        }
    }

    public function testTheDemonstrationCredentialsWork(): void
    {
        Auth::logout();

        $this->assertTrue(Auth::attempt('admin', 'Psiclinic2026'));

        Auth::logout();

        $this->assertTrue(Auth::attempt('l.moreno', 'Psiclinic2026'));

        Auth::logout();

        $this->assertTrue(Auth::attempt('mr-' . date('Y') . '-0001', 'Patient2026'), 'The demo patient can sign in to the portal');
    }

    public function testThePortalAccountIsLinkedToItsPatient(): void
    {
        $account = Database::first('SELECT * FROM users WHERE role = "patient"');

        $this->assertNotNull($account);
        $this->assertNotNull($account['patient_id']);
        $this->assertNotNull(
            Database::first('SELECT id FROM patients WHERE id = :id', ['id' => $account['patient_id']])
        );
    }

    public function testSeedIsIdempotent(): void
    {
        $before = (int) Database::value('SELECT COUNT(*) FROM users');

        ob_start();
        Installer::seed();
        ob_end_clean();

        $this->assertSame($before, (int) Database::value('SELECT COUNT(*) FROM users'));
    }

    public function testEverySeededAssessmentIsScored(): void
    {
        $rows = Database::all('SELECT * FROM assessments WHERE status = "completed"');

        $this->assertGreaterThan(0, count($rows));

        foreach ($rows as $row) {
            $this->assertNotNull($row['total_score'], $row['instrument_code'] . ' has no score');
            $this->assertNotNull($row['severity'], $row['instrument_code'] . ' has no severity');
        }
    }

    public function testSettingsAreLoadedWithDefaults(): void
    {
        $this->assertSame('PsiClinic', Settings::get('clinic_name'));
        $this->assertSame('50', Settings::get('session_duration'));
        $this->assertSame('default value', Settings::get('missing_key', 'default value'));
    }

    public function testSettingsCanBeOverwritten(): void
    {
        Settings::put('clinic_name', 'Salinas Center');

        $this->assertSame('Salinas Center', Settings::get('clinic_name'));
    }

    public function testDashboardMetricsAreConsistentWithTheSeed(): void
    {
        $metrics = Metrics::overview();

        $this->assertGreaterThan(0, $metrics['active_patients']);
        $this->assertGreaterThan(0, $metrics['total_patients']);
        $this->assertGreaterThan(0, $metrics['pending_assessments']);
        $this->assertTrue($metrics['active_patients'] <= $metrics['total_patients']);
    }

    public function testTheMonthlySeriesAlwaysCoversTheRequestedRange(): void
    {
        $series = Metrics::sessionsPerMonth(6);

        $this->assertCount(6, $series);
        $this->assertArrayHasKey(date('Y-m'), $series);
    }

    public function testTheRiskDistributionCoversEveryLevel(): void
    {
        $distribution = Metrics::riskDistribution();

        foreach (['none', 'low', 'moderate', 'high'] as $level) {
            $this->assertArrayHasKey($level, $distribution);
        }
    }

    public function testAttendanceBreakdownCoversEveryStatus(): void
    {
        $breakdown = Metrics::attendanceBreakdown();

        $this->assertCount(5, $breakdown);
        $this->assertGreaterThan(0, array_sum($breakdown));
    }
}
