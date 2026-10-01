<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
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
        $this->ok($this->abortIfMissing(Instruments::describe($code), 'Ese instrumento no está disponible.'));
    }

    public function store(Request $request): void
    {
        $code = $request->string('instrument_code');
        $patientId = $request->integer('patient_id');

        if (Instruments::get($code) === null || Patients::find($patientId) === null) {
            throw HttpException::unprocessable(
                'Selecciona un paciente y un instrumento válidos.',
                Patients::find($patientId) === null ? ['patient_id' => 'Elige un paciente.'] : []
            );
        }

        $answers = Instruments::normalizeAnswers($code, $request->array('answers'));
        if ($answers === null) {
            throw HttpException::unprocessable('Responde todos los ítems antes de guardar.');
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

        $this->created(['id' => $id, 'alerts' => $result['alerts']], 'Evaluación registrada y corregida.');
    }

    public function show(Request $request, string $id): void
    {
        $assessment = $this->abortIfMissing(Assessments::find((int) $id), 'No encontramos esta evaluación.');
        $code = (string) $assessment['instrument_code'];
        $answers = json_decode((string) ($assessment['answers'] ?? '[]'), true) ?: [];

        $alerts = [];
        if ($assessment['status'] === 'completed') {
            $alerts = Instruments::score($code, $answers)['alerts'];
        }

        AuditLog::record('view', 'assessment', (int) $assessment['id']);

        $this->ok([
            'assessment' => Present::row($assessment, ['uuid', 'answers', 'subscale_scores']),
            'instrument' => Instruments::describe($code),
            'answers' => $answers,
            'subscales' => json_decode((string) ($assessment['subscale_scores'] ?? '[]'), true) ?: [],
            'alerts' => $alerts,
            'series' => Assessments::series((int) $assessment['patient_id'], $code),
        ]);
    }

    public function assign(Request $request, string $patientId): void
    {
        $patient = $this->abortIfMissing(Patients::find((int) $patientId), 'No encontramos a este paciente.');
        $code = $request->string('instrument_code');

        if (Instruments::get($code) === null) {
            throw HttpException::unprocessable('Ese instrumento no está disponible.');
        }

        $id = Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => (int) $patient['id'],
            'instrument_code' => $code,
            'assigned_by' => Auth::id(),
            'status' => 'pending',
        ]);
        AuditLog::record('assign', 'assessment', $id);

        $this->created(['id' => $id], 'Cuestionario enviado al portal del paciente.');
    }

    public function destroy(Request $request, string $id): void
    {
        $assessment = $this->abortIfMissing(Assessments::find((int) $id), 'No encontramos esta evaluación.');

        Database::delete('assessments', (int) $assessment['id']);
        AuditLog::record('delete', 'assessment', (int) $assessment['id']);

        $this->message('Evaluación eliminada.');
    }
}
