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
    public static function boot(string $basePath): void
    {
        Env::load($basePath . '/.env');

        date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'UTC'));
        mb_internal_encoding('UTF-8');

        // Nunca se imprimen errores en la respuesta: la API responde JSON y
        // los detalles van al log del servidor.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        Session::start();
    }

    public static function isLocal(): bool
    {
        return (string) Env::get('APP_ENV', 'production') === 'local';
    }

    public static function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
