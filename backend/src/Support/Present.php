<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Support;

/**
 * Shapes what goes out through the API. User rows are never returned in full:
 * they contain the password hash.
 */
final class Present
{
    private const USER_FIELDS = [
        'id', 'username', 'email', 'full_name', 'document_type', 'document_number', 'role', 'license_number', 'specialty',
        'phone', 'theme', 'patient_id', 'is_active', 'last_login_at', 'show_on_site',
        'public_bio', 'created_at',
    ];

    public static function user(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $out = array_intersect_key($user, array_flip(self::USER_FIELDS));
        $out['is_active'] = (bool) ($out['is_active'] ?? true);
        $out['show_on_site'] = (bool) ($out['show_on_site'] ?? false);

        return $out;
    }

    public static function users(array $rows): array
    {
        return array_map(static fn (array $row): array => self::user($row) + array_intersect_key($row, ['record_number' => true]), $rows);
    }

    /** Removes internal columns the interface doesn't need. */
    public static function row(?array $row, array $hidden = ['uuid']): ?array
    {
        if ($row === null) {
            return null;
        }

        return array_diff_key($row, array_flip($hidden));
    }

    public static function rows(array $rows, array $hidden = ['uuid']): array
    {
        return array_map(static fn (array $row): array => self::row($row, $hidden), $rows);
    }

    public static function page(array $result, array $hidden = ['uuid']): array
    {
        return [
            'rows' => self::rows($result['rows'], $hidden),
            'total' => (int) $result['total'],
            'page' => (int) $result['page'],
            'pages' => (int) $result['pages'],
        ];
    }

    public static function consent(array $consent, bool $withAuditTrail = false): array
    {
        $consent = self::row($consent, $withAuditTrail ? ['uuid'] : ['uuid', 'signed_ip']);
        $consent['signature_svg'] = Signature::sanitize($consent['signature_svg'] ?? null);

        return $consent;
    }

    /** Turns a key => label catalog into an ordered list. */
    public static function options(array $catalog): array
    {
        $out = [];
        foreach ($catalog as $value => $label) {
            $out[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $out;
    }
}
