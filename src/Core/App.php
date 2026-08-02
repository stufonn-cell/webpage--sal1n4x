<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class App
{
    private static array $oldInput = [];
    private static array $errors = [];
    private static array $flash = [];

    public static function boot(string $basePath): void
    {
        Env::load($basePath . '/.env');

        date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'UTC'));
        mb_internal_encoding('UTF-8');

        $debug = Env::bool('APP_DEBUG', false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

        Session::start();

        self::$oldInput = Session::pullOld();
        self::$errors = Session::pullErrors();
        self::$flash = Session::pullFlash();

        View::share('appName', (string) Env::get('APP_NAME', 'PsiClinic'));
    }

    public static function oldInput(): array
    {
        return self::$oldInput;
    }

    public static function errors(): array
    {
        return self::$errors;
    }

    public static function flash(): array
    {
        return self::$flash;
    }

    public static function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
