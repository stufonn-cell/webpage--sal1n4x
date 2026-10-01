<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Core\Database;
use PsiClinic\Domain\Assessments;

final class AssessmentTest extends FeatureTestCase
{
    public function testCompletingAnAssessmentStoresScoreAndSeverity(): void
    {
        $patientId = $this->createPatient();
        $assessmentId = $this->assign($patientId, 'PHQ-9');

        $result = Assessments::complete($assessmentId, 'PHQ-9', [3, 3, 2, 2, 2, 2, 2, 1, 0]);

        $stored = Assessments::find($assessmentId);

        $this->assertSame(17, $result['total']);
        $this->assertSame(17, (int) $stored['total_score']);
        $this->assertSame('Moderately severe', $stored['severity']);
        $this->assertSame('completed', $stored['status']);
        $this->assertNotNull($stored['administered_at']);
    }

    public function testAnswersArePersistedAsJson(): void
    {
        $patientId = $this->createPatient();
        $assessmentId = $this->assign($patientId, 'GAD-7');
        $answers = [1, 2, 3, 0, 1, 2, 3];

        Assessments::complete($assessmentId, 'GAD-7', $answers);

        $stored = json_decode((string) Assessments::find($assessmentId)['answers'], true);

        $this->assertSame($answers, $stored);
    }

    public function testSubscalesArePersistedForMultidimensionalInstruments(): void
    {
        $patientId = $this->createPatient();
        $assessmentId = $this->assign($patientId, 'DASS-21');

        Assessments::complete($assessmentId, 'DASS-21', array_fill(0, 21, 2));

        $subscales = json_decode((string) Assessments::find($assessmentId)['subscale_scores'], true);

        $this->assertCount(3, $subscales);
        $this->assertSame(28, $subscales['Depression']['score']);
    }

    public function testPendingAssessmentsAreListedForThePortal(): void
    {
        $patientId = $this->createPatient();
        $pendingId = $this->assign($patientId, 'PHQ-9');
        $completedId = $this->assign($patientId, 'GAD-7');

        Assessments::complete($completedId, 'GAD-7', array_fill(0, 7, 1));

        $pending = Assessments::pendingForPatient($patientId);

        $this->assertCount(1, $pending);
        $this->assertSame($pendingId, (int) $pending[0]['id']);
    }

    public function testTheProgressSeriesIsChronological(): void
    {
        $patientId = $this->createPatient();

        foreach ([['-30 days', 18], ['-15 days', 12], ['-1 days', 6]] as [$offset, $score]) {
            $id = $this->assign($patientId, 'GAD-7');
            Database::update('assessments', $id, [
                'status' => 'completed',
                'total_score' => $score,
                'severity' => 'Moderate',
                'administered_at' => date('Y-m-d H:i:s', strtotime($offset)),
            ]);
        }

        $series = Assessments::series($patientId, 'GAD-7');

        $this->assertCount(3, $series);
        $this->assertSame(18, (int) $series[0]['total_score']);
        $this->assertSame(6, (int) $series[2]['total_score']);
    }

    public function testTheSeriesIgnoresOtherInstrumentsAndPendingEntries(): void
    {
        $patientId = $this->createPatient();

        $gad = $this->assign($patientId, 'GAD-7');
        Assessments::complete($gad, 'GAD-7', array_fill(0, 7, 1));
        Assessments::complete($this->assign($patientId, 'PHQ-9'), 'PHQ-9', array_fill(0, 9, 1));
        $this->assign($patientId, 'GAD-7');

        $this->assertCount(1, Assessments::series($patientId, 'GAD-7'));
    }

    public function testFilteringByInstrumentAndStatus(): void
    {
        $patientId = $this->createPatient();

        $this->assign($patientId, 'PHQ-9');
        $this->assign($patientId, 'GAD-7');
        Assessments::complete($this->assign($patientId, 'GAD-7'), 'GAD-7', array_fill(0, 7, 1));

        $this->assertCount(2, Assessments::paginate('GAD-7', '')['rows']);
        $this->assertCount(2, Assessments::paginate('', 'pending')['rows']);
        $this->assertCount(1, Assessments::paginate('GAD-7', 'completed')['rows']);
    }

    public function testAssessmentsAreRemovedWithTheirPatient(): void
    {
        $patientId = $this->createPatient();
        $this->assign($patientId, 'PHQ-9');

        Database::delete('patients', $patientId);

        $this->assertCount(0, Assessments::forPatient($patientId));
    }

    public function testEveryInstrumentCanBeStoredAndRecovered(): void
    {
        $patientId = $this->createPatient();

        foreach (\PsiClinic\Domain\Instruments::all() as $code => $instrument) {
            $id = $this->assign($patientId, $code);
            $answers = array_fill(0, count($instrument['items']), 1);

            Assessments::complete($id, $code, $answers);

            $stored = Assessments::find($id);

            $this->assertSame('completed', $stored['status'], $code);
            $this->assertNotNull($stored['severity'], $code . ' has no severity');
        }
    }

    private function assign(int $patientId, string $code): int
    {
        return Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'instrument_code' => $code,
            'status' => 'pending',
        ]);
    }
}
