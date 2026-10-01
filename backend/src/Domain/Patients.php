<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Patients
{
    public const STATUSES = [
        'active' => 'In treatment',
        'on_hold' => 'On hold',
        'discharged' => 'Discharged',
    ];

    public const RISK_LEVELS = [
        'none' => 'No risk',
        'low' => 'Low',
        'moderate' => 'Moderate',
        'high' => 'High',
    ];

    public const GENDERS = [
        'female' => 'Female',
        'male' => 'Male',
        'non_binary' => 'Non-binary',
        'undisclosed' => 'Prefers not to say',
    ];

    public static function paginate(string $search = '', string $status = '', int $page = 1, int $perPage = 12): array
    {
        $where = ['1 = 1'];
        $params = [];

        $search = mb_substr($search, 0, 100);
        if ($search !== '') {
            $where[] = '(p.first_name LIKE :s1 OR p.last_name LIKE :s2 OR p.record_number LIKE :s3 OR p.document_id LIKE :s4 OR p.email LIKE :s5)';
            $like = Database::like($search);
            $params += ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like, 's5' => $like];
        }

        if ($status !== '' && array_key_exists($status, self::STATUSES)) {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $clause = implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) FROM patients p WHERE ' . $clause, $params);

        $offset = Database::offset($page, $perPage);
        $rows = Database::all(
            'SELECT p.*, u.full_name AS psychologist_name,
                    (SELECT MAX(session_date) FROM clinical_notes n WHERE n.patient_id = p.id) AS last_session,
                    (SELECT COUNT(*) FROM clinical_notes n WHERE n.patient_id = p.id) AS sessions_count
             FROM patients p
             LEFT JOIN users u ON u.id = p.psychologist_id
             WHERE ' . $clause . '
             ORDER BY p.last_name, p.first_name' . Database::limit($perPage, $offset),
            $params
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT p.*, u.full_name AS psychologist_name
             FROM patients p
             LEFT JOIN users u ON u.id = p.psychologist_id
             WHERE p.id = :id',
            ['id' => $id]
        );
    }

    public static function options(): array
    {
        return Database::all(
            "SELECT id, CONCAT(last_name, ', ', first_name, ' (', record_number, ')') AS label
             FROM patients ORDER BY last_name, first_name"
        );
    }

    /** Next record number for the current year: MR-YYYY-NNNN. */
    public static function nextRecordNumber(): string
    {
        $year = date('Y');
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM patients WHERE record_number LIKE :prefix',
            ['prefix' => 'MR-' . $year . '-%']
        );

        return sprintf('MR-%s-%04d', $year, $count + 1);
    }

    public static function fullName(array $patient): string
    {
        return trim((string) $patient['first_name'] . ' ' . (string) $patient['last_name']);
    }

    public static function timeline(int $patientId, int $limit = 40): array
    {
        $events = [];

        foreach (Database::all(
            'SELECT id, session_date AS at, session_number, format, risk_level
             FROM clinical_notes WHERE patient_id = :id ORDER BY session_date DESC LIMIT 30',
            ['id' => $patientId]
        ) as $note) {
            $events[] = [
                'at' => (string) $note['at'],
                'type' => 'note',
                'title' => sprintf('Session %d recorded', (int) $note['session_number']),
                'meta' => strtoupper((string) $note['format']),
                'link' => '/app/notes/' . (int) $note['id'],
            ];
        }

        foreach (Database::all(
            'SELECT id, starts_at AS at, status, modality
             FROM appointments WHERE patient_id = :id ORDER BY starts_at DESC LIMIT 30',
            ['id' => $patientId]
        ) as $appointment) {
            $events[] = [
                'at' => (string) $appointment['at'],
                'type' => 'appointment',
                'title' => sprintf('Appointment %s', mb_strtolower(Appointments::STATUSES[$appointment['status']])),
                'meta' => Appointments::MODALITIES[$appointment['modality']],
                'link' => '/app/schedule',
            ];
        }

        foreach (Database::all(
            'SELECT id, administered_at AS at, instrument_code, total_score, severity
             FROM assessments WHERE patient_id = :id AND status = "completed" ORDER BY administered_at DESC LIMIT 30',
            ['id' => $patientId]
        ) as $assessment) {
            $events[] = [
                'at' => (string) $assessment['at'],
                'type' => 'assessment',
                'title' => sprintf('%s: %d points', $assessment['instrument_code'], (int) $assessment['total_score']),
                'meta' => (string) $assessment['severity'],
                'link' => '/app/assessments/' . (int) $assessment['id'],
            ];
        }

        usort($events, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($events, 0, $limit);
    }
}
