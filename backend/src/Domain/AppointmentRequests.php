<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;
use PsiClinic\Core\Env;

final class AppointmentRequests
{
    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'scheduled' => 'Appointment booked',
        'dismissed' => 'Dismissed',
    ];

    public const MODALITIES = [
        'in_person' => 'In person',
        'online' => 'Online',
        'no_preference' => 'No preference',
    ];

    public const ATTENDEES = [
        'self' => 'For me',
        'minor' => 'For a child or teenager',
        'other' => 'For another adult',
    ];

    public const CONTACT_PREFERENCES = [
        'email' => 'Email',
        'phone' => 'Phone call',
        'whatsapp' => 'WhatsApp',
    ];

    public const TIMES = [
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'evening' => 'Late afternoon',
    ];

    private const MAX_PER_HOUR = 3;

    public static function create(array $data, string $ip): int
    {
        return Database::insert('appointment_requests', $data + [
            'uuid' => uuid(),
            'ip_hash' => self::ipHash($ip),
            'privacy_accepted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Basic brake against bulk submissions from the same IP. */
    public static function tooManyFrom(string $ip): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM appointment_requests WHERE ip_hash = :ip AND created_at >= :since',
            ['ip' => self::ipHash($ip), 'since' => date('Y-m-d H:i:s', time() - 3600)]
        ) >= self::MAX_PER_HOUR;
    }

    public static function paginate(string $status = '', int $page = 1, int $perPage = 20): array
    {
        $where = '1 = 1';
        $params = [];

        if (array_key_exists($status, self::STATUSES)) {
            $where = 'r.status = :status';
            $params['status'] = $status;
        }

        $total = (int) Database::value('SELECT COUNT(*) FROM appointment_requests r WHERE ' . $where, $params);
        $offset = Database::offset($page, $perPage);

        return [
            'rows' => Database::all(
                'SELECT r.id, r.full_name, r.email, r.phone, r.contact_preference, r.modality, r.attendee,
                        r.preferred_times, r.message, r.status, r.created_at, r.handled_at,
                        u.full_name AS professional_name, h.full_name AS handled_by_name
                 FROM appointment_requests r
                 LEFT JOIN users u ON u.id = r.preferred_professional_id
                 LEFT JOIN users h ON h.id = r.handled_by
                 WHERE ' . $where . '
                 ORDER BY FIELD(r.status, "new", "contacted", "scheduled", "dismissed"), r.created_at DESC' .
                Database::limit($perPage, $offset),
                $params
            ),
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM appointment_requests WHERE id = :id', ['id' => $id]);
    }

    public static function countNew(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM appointment_requests WHERE status = "new"');
    }

    private static function ipHash(string $ip): string
    {
        // A hash keyed with the app key is stored, never the plain IP.
        return hash_hmac('sha256', $ip, (string) Env::get('APP_KEY', 'psiclinic'));
    }
}
