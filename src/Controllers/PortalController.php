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
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Patients;

final class PortalController extends Controller
{
    public function index(Request $request): void
    {
        $patient = $this->patient();

        $this->view('portal/index', [
            'patient' => $patient,
            'appointments' => Database::all(
                'SELECT a.*, u.full_name AS psychologist_name
                 FROM appointments a
                 JOIN users u ON u.id = a.psychologist_id
                 WHERE a.patient_id = :id AND a.starts_at >= NOW()
                 ORDER BY a.starts_at LIMIT 5',
                ['id' => (int) $patient['id']]
            ),
            'pending' => Assessments::pendingForPatient((int) $patient['id']),
            'consents' => Database::all(
                'SELECT * FROM consents WHERE patient_id = :id ORDER BY created_at DESC',
                ['id' => (int) $patient['id']]
            ),
            'progress' => Assessments::forPatient((int) $patient['id']),
        ], 'portal');
    }

    public function appointments(Request $request): void
    {
        $patient = $this->patient();

        $this->view('portal/appointments', [
            'patient' => $patient,
            'appointments' => Database::all(
                'SELECT a.*, u.full_name AS psychologist_name
                 FROM appointments a
                 JOIN users u ON u.id = a.psychologist_id
                 WHERE a.patient_id = :id
                 ORDER BY a.starts_at DESC',
                ['id' => (int) $patient['id']]
            ),
        ], 'portal');
    }

    public function questionnaires(Request $request): void
    {
        $patient = $this->patient();

        $this->view('portal/questionnaires', [
            'patient' => $patient,
            'assessments' => Assessments::forPatient((int) $patient['id']),
            'instruments' => Instruments::all(),
        ], 'portal');
    }

    public function showQuestionnaire(Request $request, string $id): void
    {
        $patient = $this->patient();
        $assessment = $this->abortIfMissing(Database::first(
            'SELECT * FROM assessments WHERE id = :id AND patient_id = :patient',
            ['id' => (int) $id, 'patient' => (int) $patient['id']]
        ));

        if ((string) $assessment['status'] === 'completed') {
            Session::flash('error', 'Ese cuestionario ya fue respondido.');
            $this->redirect('/portal/cuestionarios');
        }

        $this->view('portal/questionnaire', [
            'patient' => $patient,
            'assessment' => $assessment,
            'instrument' => Instruments::get((string) $assessment['instrument_code']),
        ], 'portal');
    }

    public function submitQuestionnaire(Request $request, string $id): void
    {
        $patient = $this->patient();
        $assessment = $this->abortIfMissing(Database::first(
            'SELECT * FROM assessments WHERE id = :id AND patient_id = :patient',
            ['id' => (int) $id, 'patient' => (int) $patient['id']]
        ));

        $instrument = Instruments::get((string) $assessment['instrument_code']);
        $answers = array_map('intval', $request->array('answers'));

        if ($instrument === null || count($answers) < count($instrument['items'])) {
            Session::flash('error', 'Responde todos los items para enviar el cuestionario.');
            $this->back($request);
        }

        Assessments::complete((int) $assessment['id'], (string) $assessment['instrument_code'], $answers);

        Session::flash('success', 'Cuestionario enviado. Tu profesional revisara los resultados.');
        $this->redirect('/portal/cuestionarios');
    }

    public function documents(Request $request): void
    {
        $patient = $this->patient();

        $this->view('portal/documents', [
            'patient' => $patient,
            'documents' => Documents::recent((int) $patient['id'], 50),
        ], 'portal');
    }

    private function patient(): array
    {
        $user = Auth::user();

        if ($user === null) {
            $this->redirect('/login');
        }

        if ($user['role'] !== 'patient' || $user['patient_id'] === null) {
            $this->redirect('/dashboard');
        }

        $patient = Patients::find((int) $user['patient_id']);

        if ($patient === null) {
            Auth::logout();
            $this->redirect('/login');
        }

        return $patient;
    }
}
