<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Notes
{
    public const FORMATS = [
        'soap' => 'SOAP (subjective, objective, assessment, plan)',
        'dap' => 'DAP (data, assessment, plan)',
        'free' => 'Free-form note',
    ];

    public const INTERVENTIONS = [
        'Cognitive restructuring',
        'Behavioral activation',
        'Graded exposure',
        'Relaxation training',
        'Mindfulness',
        'Emotion regulation',
        'Social skills training',
        'Psychoeducation',
        'Acceptance and commitment therapy',
        'Motivational interviewing',
    ];

    public static function paginate(string $search, int $page = 1, int $perPage = 15): array
    {
        $where = '1 = 1';
        $params = [];

        $search = mb_substr($search, 0, 100);
        if ($search !== '') {
            $where = '(p.first_name LIKE :s1 OR p.last_name LIKE :s2 OR n.subjective LIKE :s3 OR n.assessment LIKE :s4)';
            $like = Database::like($search);
            $params = ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like];
        }

        $total = (int) Database::value(
            'SELECT COUNT(*) FROM clinical_notes n JOIN patients p ON p.id = n.patient_id WHERE ' . $where,
            $params
        );

        $offset = Database::offset($page, $perPage);

        return [
            'rows' => Database::all(
                'SELECT n.*, p.first_name, p.last_name, p.record_number, u.full_name AS author_name
                 FROM clinical_notes n
                 JOIN patients p ON p.id = n.patient_id
                 JOIN users u ON u.id = n.author_id
                 WHERE ' . $where . '
                 ORDER BY n.session_date DESC, n.id DESC' . Database::limit($perPage, $offset),
                $params
            ),
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT n.*, p.first_name, p.last_name, p.record_number, p.birth_date, p.document_id,
                    u.full_name AS author_name, u.license_number
             FROM clinical_notes n
             JOIN patients p ON p.id = n.patient_id
             JOIN users u ON u.id = n.author_id
             WHERE n.id = :id',
            ['id' => $id]
        );
    }

    public static function forPatient(int $patientId): array
    {
        return Database::all(
            'SELECT n.*, u.full_name AS author_name
             FROM clinical_notes n
             JOIN users u ON u.id = n.author_id
             WHERE n.patient_id = :id
             ORDER BY n.session_date DESC, n.id DESC',
            ['id' => $patientId]
        );
    }

    public static function nextSessionNumber(int $patientId): int
    {
        return 1 + (int) Database::value(
            'SELECT COALESCE(MAX(session_number), 0) FROM clinical_notes WHERE patient_id = :id',
            ['id' => $patientId]
        );
    }

    public static function moodSeries(int $patientId): array
    {
        return Database::all(
            'SELECT session_date, mood_score
             FROM clinical_notes
             WHERE patient_id = :id AND mood_score IS NOT NULL
             ORDER BY session_date',
            ['id' => $patientId]
        );
    }
}
