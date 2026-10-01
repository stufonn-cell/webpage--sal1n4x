<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Domain\Assessments;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Present;

/**
 * Patient portal. Every query filters by the patient in the session: the id is
 * never taken from the request.
 */
final class PortalController extends Controller
{
    private const APPOINTMENT_FIELDS = 'a.id, a.starts_at, a.ends_at, a.modality, a.status, a.session_type,
        a.location, a.meeting_url, u.full_name AS psychologist_name';

    public function index(Request $request): void
    {
        $patient = $this->patient();
        $id = (int) $patient['id'];

        $this->ok([
            'patient' => ['first_name' => $patient['first_name'], 'last_name' => $patient['last_name'], 'record_number' => $patient['record_number']],
            'appointments' => Database::all(
                'SELECT ' . self::APPOINTMENT_FIELDS . '
                 FROM appointments a JOIN users u ON u.id = a.psychologist_id
                 WHERE a.patient_id = :id AND a.starts_at >= NOW() AND a.status IN ("scheduled","confirmed")
                 ORDER BY a.starts_at LIMIT 5',
                ['id' => $id]
            ),
            'pending' => $this->assessmentsFor($id, 'pending'),
            'consents' => Database::all(
                'SELECT id, title, status, signed_at, created_at FROM consents WHERE patient_id = :id ORDER BY created_at DESC',
                ['id' => $id]
            ),
            'completed' => $this->assessmentsFor($id, 'completed'),
        ]);
    }

    public function appointments(Request $request): void
    {
        $this->ok(Database::all(
            'SELECT ' . self::APPOINTMENT_FIELDS . '
             FROM appointments a JOIN users u ON u.id = a.psychologist_id
             WHERE a.patient_id = :id
             ORDER BY a.starts_at DESC',
            ['id' => (int) $this->patient()['id']]
        ));
    }

    public function questionnaires(Request $request): void
    {
        $this->ok($this->assessmentsFor((int) $this->patient()['id']));
    }

    public function showQuestionnaire(Request $request, string $id): void
    {
        $assessment = $this->ownAssessment((int) $id);

        if ($assessment['status'] === 'completed') {
            throw HttpException::conflict("You've already answered this questionnaire. Thank you!");
        }

        $this->ok([
            'id' => (int) $assessment['id'],
            'instrument' => Instruments::describe((string) $assessment['instrument_code']),
        ]);
    }

    public function submitQuestionnaire(Request $request, string $id): void
    {
        $assessment = $this->ownAssessment((int) $id);

        if ($assessment['status'] === 'completed') {
            throw HttpException::conflict("You've already answered this questionnaire. Thank you!");
        }

        $code = (string) $assessment['instrument_code'];
        $answers = Instruments::normalizeAnswers($code, $request->array('answers'));

        if ($answers === null) {
            throw HttpException::unprocessable('Some questions are still unanswered. Please check the highlighted ones.');
        }

        Assessments::complete((int) $assessment['id'], $code, $answers);
        AuditLog::record('submit', 'assessment', (int) $assessment['id']);

        $this->message('Thank you for taking the time. Your professional will review your answers before your next session.');
    }

    public function documents(Request $request): void
    {
        $this->ok(array_map(
            static fn (array $row): array => array_intersect_key($row, array_flip(['id', 'title', 'category', 'original_name', 'mime_type', 'size_bytes', 'created_at'])),
            Documents::recent((int) $this->patient()['id'], 50)
        ));
    }

    /**
     * The patient sees the date and severity, but not the clinical
     * interpretation: that conversation belongs in the session.
     */
    private function assessmentsFor(int $patientId, ?string $status = null): array
    {
        $rows = Assessments::forPatient($patientId);
        $catalog = Instruments::all();
        $out = [];

        foreach ($rows as $row) {
            if ($status !== null && $row['status'] !== $status) {
                continue;
            }
            $code = (string) $row['instrument_code'];
            $out[] = [
                'id' => (int) $row['id'],
                'instrument_code' => $code,
                'instrument_name' => $catalog[$code]['name'] ?? $code,
                'instrument_domain' => $catalog[$code]['domain'] ?? '',
                'items_count' => count($catalog[$code]['items'] ?? []),
                'status' => $row['status'],
                'total_score' => $row['total_score'],
                'max_score' => Instruments::maxScore($code),
                'severity' => $row['severity'],
                'administered_at' => $row['administered_at'],
                'created_at' => $row['created_at'],
            ];
        }

        return $out;
    }

    private function ownAssessment(int $id): array
    {
        return $this->abortIfMissing(Database::first(
            'SELECT * FROM assessments WHERE id = :id AND patient_id = :patient',
            ['id' => $id, 'patient' => (int) $this->patient()['id']]
        ), "We couldn't find this questionnaire.");
    }

    private function patient(): array
    {
        $patient = Patients::find((int) Auth::patientId());

        if ($patient === null) {
            throw HttpException::forbidden("Your account isn't linked to a clinical record. Please contact the practice.");
        }

        return $patient;
    }
}
