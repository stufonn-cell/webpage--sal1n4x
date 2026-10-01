<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;
use PsiClinic\Core\Env;

final class AppointmentRequests
{
    public const STATUSES = [
        'new' => 'Nueva',
        'contacted' => 'Contactada',
        'scheduled' => 'Cita agendada',
        'dismissed' => 'Descartada',
    ];

    public const MODALITIES = [
        'in_person' => 'Presencial',
        'online' => 'Virtual',
        'no_preference' => 'Sin preferencia',
    ];

    public const ATTENDEES = [
        'self' => 'Para mí',
        'minor' => 'Para un niño, niña o adolescente',
        'other' => 'Para otra persona adulta',
    ];

    public const CONTACT_PREFERENCES = [
        'email' => 'Correo',
        'phone' => 'Llamada',
        'whatsapp' => 'WhatsApp',
    ];

    public const TIMES = [
        'morning' => 'Mañana',
        'afternoon' => 'Tarde',
        'evening' => 'Final de la tarde',
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

    /** Freno basico contra envios masivos desde una misma IP. */
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
        $offset = max(0, ($page - 1) * $perPage);

        return [
            'rows' => Database::all(
                'SELECT r.id, r.full_name, r.email, r.phone, r.contact_preference, r.modality, r.attendee,
                        r.preferred_times, r.message, r.status, r.created_at, r.handled_at,
                        u.full_name AS professional_name, h.full_name AS handled_by_name
                 FROM appointment_requests r
                 LEFT JOIN users u ON u.id = r.preferred_professional_id
                 LEFT JOIN users h ON h.id = r.handled_by
                 WHERE ' . $where . '
                 ORDER BY FIELD(r.status, "new", "contacted", "scheduled", "dismissed"), r.created_at DESC
                 LIMIT ' . $perPage . ' OFFSET ' . $offset,
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
        // Se guarda un hash con la clave de la app, no la IP en claro.
        return hash_hmac('sha256', $ip, (string) Env::get('APP_KEY', 'psiclinic'));
    }
}
