<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Session
{
    private const LAST_ACTIVITY = '_last_activity';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) Env::get('SESSION_NAME', 'psiclinic_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Env::bool('SESSION_SECURE', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        self::expireIdle();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Cierra la sesion tras SESSION_LIFETIME segundos sin actividad. Una
     * historia clinica abierta en un equipo compartido no debe quedar viva.
     */
    private static function expireIdle(): void
    {
        $now = time();
        $last = $_SESSION[self::LAST_ACTIVITY] ?? null;

        if (is_int($last) && ($now - $last) > Env::int('SESSION_LIFETIME', 7200)) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION[self::LAST_ACTIVITY] = $now;
    }
}
