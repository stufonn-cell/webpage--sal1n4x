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
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Domain\Assessments;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Patients;

final class AssessmentController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('assessments/index', [
            'result' => Assessments::paginate(
                (string) $request->input('instrumento', ''),
                (string) $request->input('estado', ''),
                max(1, $request->integer('page', 1))
            ),
            'instruments' => Instruments::all(),
            'instrument' => (string) $request->input('instrumento', ''),
            'status' => (string) $request->input('estado', ''),
        ]);
    }

    public function catalog(Request $request): void
    {
        $this->view('assessments/catalog', ['instruments' => Instruments::all()]);
    }

    public function create(Request $request): void
    {
        $code = (string) $request->input('instrumento', 'PHQ-9');
        $instrument = Instruments::get($code);

        if ($instrument === null) {
            Session::flash('error', 'Instrumento no disponible.');
            $this->redirect('/evaluaciones');
        }

        $this->view('assessments/form', [
            'instrument' => $instrument,
            'instruments' => Instruments::all(),
            'patients' => Patients::options(),
            'selectedPatient' => $request->integer('paciente'),
        ]);
    }

    public function store(Request $request): void
    {
        $code = (string) $request->input('instrument_code', '');
        $instrument = Instruments::get($code);
        $patientId = $request->integer('patient_id');

        if ($instrument === null || $patientId <= 0) {
            Session::flash('error', 'Selecciona un paciente y un instrumento valido.');
            $this->redirect('/evaluaciones/nueva');
        }

        $answers = array_map('intval', $request->array('answers'));

        if (count(array_filter($answers, static fn ($v) => $v !== null)) < count($instrument['items'])) {
            Session::flash('error', 'Responde todos los items antes de guardar.');
            $this->back($request);
        }

        $id = Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'instrument_code' => $code,
            'assigned_by' => Auth::id(),
            'status' => 'pending',
            'clinician_notes' => (string) $request->input('clinician_notes', ''),
        ]);

        Assessments::complete($id, $code, $answers);
        AuditLog::record('create', 'assessment', $id);

        Session::flash('success', 'Evaluacion registrada y corregida automaticamente.');
        $this->redirect('/evaluaciones/' . $id);
    }

    public function show(Request $request, string $id): void
    {
        $assessment = $this->abortIfMissing(Assessments::find((int) $id));
        $instrument = Instruments::get((string) $assessment['instrument_code']);

        $this->view('assessments/show', [
            'assessment' => $assessment,
            'instrument' => $instrument,
            'answers' => json_decode((string) ($assessment['answers'] ?? '[]'), true) ?: [],
            'subscales' => json_decode((string) ($assessment['subscale_scores'] ?? '[]'), true) ?: [],
            'series' => Assessments::series((int) $assessment['patient_id'], (string) $assessment['instrument_code']),
            'maxScore' => Instruments::maxScore((string) $assessment['instrument_code']),
        ]);
    }

    public function assign(Request $request, string $id): void
    {
        $patientId = (int) $id;
        $code = (string) $request->input('instrument_code', '');

        if (Instruments::get($code) === null) {
            Session::flash('error', 'Instrumento no disponible.');
            $this->back($request);
        }

        Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'instrument_code' => $code,
            'assigned_by' => Auth::id(),
            'status' => 'pending',
        ]);

        Session::flash('success', 'Cuestionario asignado al portal del paciente.');
        $this->back($request);
    }

    public function destroy(Request $request, string $id): void
    {
        Database::delete('assessments', (int) $id);
        AuditLog::record('delete', 'assessment', (int) $id);

        Session::flash('success', 'Evaluacion eliminada.');
        $this->redirect('/evaluaciones');
    }
}
