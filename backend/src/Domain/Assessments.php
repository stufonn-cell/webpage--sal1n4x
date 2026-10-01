<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Assessments
{
    public static function paginate(string $instrument, string $status, int $page = 1, int $perPage = 15): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($instrument !== '') {
            $where[] = 'a.instrument_code = :instrument';
            $params['instrument'] = $instrument;
        }

        if (in_array($status, ['pending', 'completed'], true)) {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }

        $clause = implode(' AND ', $where);
        $total = (int) Database::value(
            'SELECT COUNT(*) FROM assessments a WHERE ' . $clause,
            $params
        );

        $offset = Database::offset($page, $perPage);

        return [
            'rows' => Database::all(
                'SELECT a.*, p.first_name, p.last_name, p.record_number
                 FROM assessments a
                 JOIN patients p ON p.id = a.patient_id
                 WHERE ' . $clause . '
                 ORDER BY COALESCE(a.administered_at, a.created_at) DESC' . Database::limit($perPage, $offset),
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
            'SELECT a.*, p.first_name, p.last_name, p.record_number, u.full_name AS assigned_by_name
             FROM assessments a
             JOIN patients p ON p.id = a.patient_id
             LEFT JOIN users u ON u.id = a.assigned_by
             WHERE a.id = :id',
            ['id' => $id]
        );
    }

    public static function forPatient(int $patientId): array
    {
        return Database::all(
            'SELECT * FROM assessments WHERE patient_id = :id ORDER BY COALESCE(administered_at, created_at) DESC',
            ['id' => $patientId]
        );
    }

    public static function series(int $patientId, string $instrumentCode): array
    {
        return Database::all(
            'SELECT administered_at, total_score, severity
             FROM assessments
             WHERE patient_id = :id AND instrument_code = :code AND status = "completed"
             ORDER BY administered_at',
            ['id' => $patientId, 'code' => $instrumentCode]
        );
    }

    public static function pendingForPatient(int $patientId): array
    {
        return Database::all(
            'SELECT * FROM assessments WHERE patient_id = :id AND status = "pending" ORDER BY created_at DESC',
            ['id' => $patientId]
        );
    }

    public static function complete(int $id, string $instrumentCode, array $answers): array
    {
        $result = Instruments::score($instrumentCode, $answers);

        Database::update('assessments', $id, [
            'answers' => json_encode(array_values($answers), JSON_UNESCAPED_UNICODE),
            'total_score' => $result['total'],
            'subscale_scores' => json_encode($result['subscales'], JSON_UNESCAPED_UNICODE),
            'severity' => $result['severity'],
            'interpretation' => $result['interpretation'],
            'status' => 'completed',
            'administered_at' => date('Y-m-d H:i:s'),
        ]);

        return $result;
    }
}
