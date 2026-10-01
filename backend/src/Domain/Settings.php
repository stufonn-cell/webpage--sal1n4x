<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        'clinic_name' => 'PsiClinic',
        'clinic_tagline' => 'Psychological care center',
        'clinic_email' => 'contact@psiclinic.local',
        'clinic_phone' => '',
        'clinic_address' => '',
        'currency' => 'COP',
        'session_duration' => '50',
        'default_fee' => '120000',
        'working_hours_start' => '07:00',
        'working_hours_end' => '19:00',
        'note_lock_hours' => '72',
        'clinic_about' => '',
        'whatsapp_number' => '',
        'crisis_line' => '123',
        // Default language of consents, printed notes, invoices and reports.
        'document_language' => 'en',
        // RIPS (Resolution 2275 of 2023). Never exposed on the public site.
        'rips_reporter_id' => '',
        'rips_provider_code' => '',
        'rips_service_code' => '344',
        'rips_purpose_first' => '15',
        'rips_purpose_follow_up' => '16',
        'rips_cause' => '38',
        'rips_first_note_number' => '1',
        'rips_environment' => 'test',
        'rips_validator_url' => '',
        'rips_validator_verify_tls' => '1',
    ];

    /** Keys any visitor of the public site can read. */
    public const PUBLIC_KEYS = [
        'clinic_name', 'clinic_tagline', 'clinic_email', 'clinic_phone', 'clinic_address',
        'session_duration', 'working_hours_start', 'working_hours_end',
        'clinic_about', 'whatsapp_number', 'crisis_line',
    ];

    public static function publicValues(): array
    {
        return array_intersect_key(self::all(), array_flip(self::PUBLIC_KEYS));
    }

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = [];
        foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $row) {
            $stored[$row['setting_key']] = $row['setting_value'];
        }

        // Only known keys: stale rows from older versions never reach the API.
        self::$cache = array_intersect_key($stored, self::DEFAULTS) + self::DEFAULTS;

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
