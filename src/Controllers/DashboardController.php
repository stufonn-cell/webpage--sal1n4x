<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use DateTimeImmutable;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Request;
use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\Metrics;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $psychologistId = Auth::is('psychologist') ? Auth::id() : null;

        $this->view('dashboard/index', [
            'metrics' => Metrics::overview(),
            'sessionsSeries' => Metrics::sessionsPerMonth(6),
            'attendance' => Metrics::attendanceBreakdown(),
            'riskDistribution' => Metrics::riskDistribution(),
            'today' => Appointments::forDay((new DateTimeImmutable())->format('Y-m-d')),
            'upcoming' => Appointments::upcoming(6, $psychologistId),
            'pendingAssessments' => Database::all(
                'SELECT a.*, p.first_name, p.last_name
                 FROM assessments a
                 JOIN patients p ON p.id = a.patient_id
                 WHERE a.status = "pending"
                 ORDER BY a.created_at DESC
                 LIMIT 6'
            ),
            'riskPatients' => Database::all(
                'SELECT id, first_name, last_name, record_number, risk_level
                 FROM patients
                 WHERE risk_level IN ("moderate","high") AND status = "active"
                 ORDER BY FIELD(risk_level, "high", "moderate"), last_name
                 LIMIT 6'
            ),
            'recentNotes' => Database::all(
                'SELECT n.id, n.session_date, n.session_number, p.first_name, p.last_name
                 FROM clinical_notes n
                 JOIN patients p ON p.id = n.patient_id
                 ORDER BY n.id DESC
                 LIMIT 6'
            ),
        ]);
    }
}
