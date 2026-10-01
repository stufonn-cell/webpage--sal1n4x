<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use DateTimeImmutable;
use PsiClinic\Core\Database;

final class Appointments
{
    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
    ];

    public const MODALITIES = [
        'in_person' => 'In person',
        'online' => 'Online',
        'phone' => 'Phone',
    ];

    public static function week(DateTimeImmutable $reference, ?int $psychologistId = null): array
    {
        $start = $reference->modify('monday this week')->setTime(0, 0);
        $end = $start->modify('+7 days');

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];

        $filter = '';
        if ($psychologistId !== null) {
            $filter = ' AND a.psychologist_id = :psychologist';
            $params['psychologist'] = $psychologistId;
        }

        $rows = Database::all(
            'SELECT a.*, p.first_name, p.last_name, p.record_number, u.full_name AS psychologist_name
             FROM appointments a
             JOIN patients p ON p.id = a.patient_id
             JOIN users u ON u.id = a.psychologist_id
             WHERE a.starts_at >= :start AND a.starts_at < :end' . $filter . '
             ORDER BY a.starts_at',
            $params
        );

        $grouped = [];
        for ($day = 0; $day < 7; $day++) {
            $grouped[$start->modify('+' . $day . ' days')->format('Y-m-d')] = [];
        }

        foreach ($rows as $row) {
            $key = date('Y-m-d', strtotime((string) $row['starts_at']));
            $grouped[$key][] = $row;
        }

        return ['start' => $start, 'end' => $end, 'days' => $grouped];
    }

    public static function upcoming(int $limit = 8, ?int $psychologistId = null): array
    {
        $filter = $psychologistId === null ? '' : ' AND a.psychologist_id = :psychologist';
        $params = $psychologistId === null ? [] : ['psychologist' => $psychologistId];

        return Database::all(
            'SELECT a.*, p.first_name, p.last_name, p.record_number
             FROM appointments a
             JOIN patients p ON p.id = a.patient_id
             WHERE a.starts_at >= NOW() AND a.status IN ("scheduled","confirmed")' . $filter . '
             ORDER BY a.starts_at
             LIMIT ' . max(1, min($limit, 50)),
            $params
        );
    }

    public static function forDay(string $date): array
    {
        return Database::all(
            'SELECT a.*, p.first_name, p.last_name, p.record_number
             FROM appointments a
             JOIN patients p ON p.id = a.patient_id
             WHERE DATE(a.starts_at) = :date
             ORDER BY a.starts_at',
            ['date' => $date]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT a.*, p.first_name, p.last_name, p.record_number
             FROM appointments a
             JOIN patients p ON p.id = a.patient_id
             WHERE a.id = :id',
            ['id' => $id]
        );
    }

    public static function hasConflict(int $psychologistId, string $startsAt, string $endsAt, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM appointments
                WHERE psychologist_id = :psychologist
                  AND status NOT IN ("cancelled","no_show")
                  AND starts_at < :ends
                  AND ends_at > :starts';
        $params = [
            'psychologist' => $psychologistId,
            'starts' => $startsAt,
            'ends' => $endsAt,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore';
            $params['ignore'] = $ignoreId;
        }

        return (int) Database::value($sql, $params) > 0;
    }
}
