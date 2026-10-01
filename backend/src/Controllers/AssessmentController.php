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
use PsiClinic\Domain\DocumentLanguage;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Present;

final class AssessmentController extends Controller
{
    public function index(Request $request): void
    {
        $this->ok(Present::page(Assessments::paginate(
            $request->string('instrument'),
            $request->string('status'),
            max(1, $request->integer('page', 1))
        ), ['uuid', 'answers']));
    }

    public function catalog(Request $request): void
    {
        $this->ok(array_map(
            static fn (string $code): array => Instruments::describe($code),
            Instruments::codes()
        ));
    }

    public function instrument(Request $request, string $code): void
    {
        $this->ok($this->abortIfMissing(Instruments::describe($code), "That instrument isn't available."));
    }

    public function store(Request $request): void
    {
        $this->validate($request, ['clinician_notes' => 'max:10000']);

        $code = $request->string('instrument_code');
        $patientId = $request->integer('patient_id');

        if (Instruments::get($code) === null || Patients::find($patientId) === null) {
            throw HttpException::unprocessable(
                'Select a valid patient and instrument.',
                Patients::find($patientId) === null ? ['patient_id' => 'Choose a patient.'] : []
            );
        }

        $answers = Instruments::normalizeAnswers($code, $request->array('answers'));
        if ($answers === null) {
            throw HttpException::unprocessable('Answer every item before saving.');
        }

        $id = Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'instrument_code' => $code,
            'assigned_by' => Auth::id(),
            'status' => 'pending',
            'clinician_notes' => $request->string('clinician_notes'),
        ]);

        $result = Assessments::complete($id, $code, $answers);
        AuditLog::record('create', 'assessment', $id);

        $this->created(['id' => $id, 'alerts' => $result['alerts']], 'Assessment recorded and scored.');
    }

    /**
     * Assessment report. `language` (en|es) picks the language of the report:
     * the instrument text and the band labels are taken from that catalog and
     * recalculated from the stored answers, so the score never changes.
     */
    public function show(Request $request, string $id): void
    {
        $assessment = $this->abortIfMissing(Assessments::find((int) $id), "We couldn't find this assessment.");
        $code = (string) $assessment['instrument_code'];
        $answers = json_decode((string) ($assessment['answers'] ?? '[]'), true) ?: [];
        $language = DocumentLanguage::resolve($request->string('language'));

        $row = Present::row($assessment, ['uuid', 'answers', 'subscale_scores']);
        $subscales = json_decode((string) ($assessment['subscale_scores'] ?? '[]'), true) ?: [];
        $alerts = [];

        if ($assessment['status'] === 'completed' && $answers !== []) {
            $result = Instruments::score($code, $answers, $language);
            $alerts = $result['alerts'];
            $subscales = $result['subscales'];
            $row['severity'] = $result['severity'];
            $row['interpretation'] = $result['interpretation'];
        }

        AuditLog::record('view', 'assessment', (int) $assessment['id']);

        $this->ok([
            'language' => $language,
            'assessment' => $row,
            'instrument' => Instruments::describe($code, $language),
            'answers' => $answers,
            'subscales' => $subscales,
            'alerts' => $alerts,
            'series' => Assessments::series((int) $assessment['patient_id'], $code),
        ]);
    }

    public function assign(Request $request, string $patientId): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $patientId), "We couldn't find this patient.");
        $code = $request->string('instrument_code');

        if (Instruments::get($code) === null) {
            throw HttpException::unprocessable("That instrument isn't available.");
        }

        $id = Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => (int) $patient['id'],
            'instrument_code' => $code,
            'assigned_by' => Auth::id(),
            'status' => 'pending',
        ]);
        AuditLog::record('assign', 'assessment', $id);

        $this->created(['id' => $id], 'Questionnaire sent to the patient portal.');
    }

    public function destroy(Request $request, string $id): void
    {
        $assessment = $this->abortIfMissing(Assessments::find((int) $id), "We couldn't find this assessment.");

        Database::delete('assessments', (int) $assessment['id']);
        AuditLog::record('delete', 'assessment', (int) $assessment['id']);

        $this->message('Assessment deleted.');
    }
}
