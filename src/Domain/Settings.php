<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        'clinic_name' => 'PsiClinic',
        'clinic_tagline' => 'Centro de atencion psicologica',
        'clinic_email' => 'contacto@psiclinic.local',
        'clinic_phone' => '',
        'clinic_address' => '',
        'currency' => 'COP',
        'session_duration' => '50',
        'default_fee' => '120000',
        'working_hours_start' => '07:00',
        'working_hours_end' => '19:00',
        'note_lock_hours' => '72',
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = [];
        foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $row) {
            $stored[$row['setting_key']] = $row['setting_value'];
        }

        self::$cache = $stored + self::DEFAULTS;

        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        return (string) (self::all()[$key] ?? $default);
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function put(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['k' => $key, 'v' => $value]
        );

        self::$cache = null;
    }
}
