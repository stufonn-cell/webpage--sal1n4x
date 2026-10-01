<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Metrics
{
    public static function overview(): array
    {
        return [
            'active_patients' => (int) Database::value('SELECT COUNT(*) FROM patients WHERE status = "active"'),
            'total_patients' => (int) Database::value('SELECT COUNT(*) FROM patients'),
            'sessions_this_month' => (int) Database::value(
                'SELECT COUNT(*) FROM clinical_notes WHERE YEAR(session_date) = YEAR(CURDATE()) AND MONTH(session_date) = MONTH(CURDATE())'
            ),
            'appointments_today' => (int) Database::value('SELECT COUNT(*) FROM appointments WHERE DATE(starts_at) = CURDATE()'),
            'pending_assessments' => (int) Database::value('SELECT COUNT(*) FROM assessments WHERE status = "pending"'),
            'high_risk' => (int) Database::value('SELECT COUNT(*) FROM patients WHERE risk_level IN ("moderate","high") AND status = "active"'),
            'outstanding_balance' => (float) Database::value(
                'SELECT COALESCE(SUM(i.total), 0) - COALESCE((SELECT SUM(amount) FROM payments), 0)
                 FROM invoices i WHERE i.status IN ("issued","draft")'
            ),
        ];
    }

    public static function sessionsPerMonth(int $months = 6): array
    {
        $rows = Database::all(
            'SELECT DATE_FORMAT(session_date, "%Y-%m") AS period, COUNT(*) AS total
             FROM clinical_notes
             WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL ' . max(1, $months) . ' MONTH)
             GROUP BY period
             ORDER BY period'
        );

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = date('Y-m', strtotime('-' . $i . ' months'));
            $series[$key] = 0;
        }

        foreach ($rows as $row) {
            $series[$row['period']] = (int) $row['total'];
        }

        return $series;
    }

    public static function attendanceBreakdown(): array
    {
        $rows = Database::all(
            'SELECT status, COUNT(*) AS total
             FROM appointments
             WHERE starts_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
             GROUP BY status'
        );

        $breakdown = array_fill_keys(array_keys(Appointments::STATUSES), 0);
        foreach ($rows as $row) {
            $breakdown[$row['status']] = (int) $row['total'];
        }

        return $breakdown;
    }

    public static function riskDistribution(): array
    {
        $rows = Database::all(
            'SELECT risk_level, COUNT(*) AS total FROM patients WHERE status = "active" GROUP BY risk_level'
        );

        $distribution = array_fill_keys(array_keys(Patients::RISK_LEVELS), 0);
        foreach ($rows as $row) {
            $distribution[$row['risk_level']] = (int) $row['total'];
        }

        return $distribution;
    }
}
