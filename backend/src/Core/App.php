<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class App
{
    public static function boot(string $basePath): void
    {
        Env::load($basePath . '/.env');

        date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'UTC'));
        mb_internal_encoding('UTF-8');

        // Errors are never printed in the response: the API answers with JSON
        // and the details go to the server log.
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('html_errors', '0');
        ini_set('log_errors', '1');
        // Stack traces in the log never carry argument values (passwords, tokens).
        ini_set('zend.exception_ignore_args', '1');
        error_reporting(E_ALL);

        Session::start();
    }

    public static function environment(): string
    {
        return strtolower((string) Env::get('APP_ENV', 'production'));
    }

    public static function isLocal(): bool
    {
        return self::environment() === 'local';
    }

    public static function isTesting(): bool
    {
        return self::environment() === 'testing';
    }

    /** Any environment that is not explicitly local or testing is treated as production. */
    public static function isProduction(): bool
    {
        return !in_array(self::environment(), ['local', 'development', 'testing'], true);
    }

    /** Secret used to key hashes (HMAC). Falls back to a constant when APP_KEY is missing. */
    public static function key(): string
    {
        return (string) Env::get('APP_KEY', 'psiclinic');
    }

    public static function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
