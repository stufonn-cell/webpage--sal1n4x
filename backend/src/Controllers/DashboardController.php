<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use DateTimeImmutable;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AppointmentRequests;
use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\Metrics;
use PsiClinic\Support\Present;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $psychologistId = Auth::is('psychologist') ? Auth::id() : null;

        $this->ok([
            'metrics' => Metrics::overview() + ['new_requests' => AppointmentRequests::countNew()],
            'sessionsSeries' => Metrics::sessionsPerMonth(6),
            'attendance' => Metrics::attendanceBreakdown(),
            'riskDistribution' => Metrics::riskDistribution(),
            'today' => Present::rows(Appointments::forDay((new DateTimeImmutable())->format('Y-m-d'))),
            'upcoming' => Present::rows(Appointments::upcoming(6, $psychologistId)),
            'pendingAssessments' => Database::all(
                'SELECT a.id, a.instrument_code, a.created_at, a.patient_id, p.first_name, p.last_name
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
                'SELECT n.id, n.session_date, n.session_number, n.is_locked, p.first_name, p.last_name
                 FROM clinical_notes n
                 JOIN patients p ON p.id = n.patient_id
                 ORDER BY n.id DESC
                 LIMIT 6'
            ),
        ]);
    }
}
