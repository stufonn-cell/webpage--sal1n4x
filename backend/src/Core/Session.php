<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Session
{
    private const LAST_ACTIVITY = '_last_activity';
    private const AUTHENTICATED_AT = '_authenticated_at';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = self::secure();

        // "__Host-" cookies are only accepted over HTTPS, for the whole site
        // and without a Domain: no subdomain or plain-HTTP page can plant or
        // overwrite them.
        session_name(($secure ? '__Host-' : '') . self::cookieName());

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        // The garbage collector must not delete a session before the idle timeout does.
        ini_set('session.gc_maxlifetime', (string) max(1440, self::idleLifetime()));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => self::sameSite(),
        ]);
        session_start();

        self::enforceTimeouts();
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

    /** Called right after a successful sign-in: new id and start of the absolute lifetime. */
    public static function markAuthenticated(?int $now = null): void
    {
        self::regenerate();
        $_SESSION[self::AUTHENTICATED_AT] = $now ?? time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Ends the session after SESSION_LIFETIME seconds of inactivity, and in
     * any case SESSION_ABSOLUTE_LIFETIME seconds after signing in. A clinical
     * record left open on a shared computer must not stay alive.
     */
    public static function enforceTimeouts(?int $now = null): void
    {
        $now ??= time();
        $last = $_SESSION[self::LAST_ACTIVITY] ?? null;
        $authenticatedAt = $_SESSION[self::AUTHENTICATED_AT] ?? null;

        $idle = is_int($last) && ($now - $last) > self::idleLifetime();
        $absolute = is_int($authenticatedAt) && ($now - $authenticatedAt) > self::absoluteLifetime();

        if ($idle || $absolute) {
            $_SESSION = [];
            self::regenerate();
        }

        $_SESSION[self::LAST_ACTIVITY] = $now;
    }

    public static function idleLifetime(): int
    {
        return max(60, Env::int('SESSION_LIFETIME', 7200));
    }

    public static function absoluteLifetime(): int
    {
        return max(self::idleLifetime(), Env::int('SESSION_ABSOLUTE_LIFETIME', 43200));
    }

    /**
     * Secure cookie when SESSION_SECURE=true, when the request arrived over
     * HTTPS, and by default in production (an explicit SESSION_SECURE=false
     * still wins, for a production copy served on plain HTTP inside a LAN).
     */
    public static function secure(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        return $https || Env::bool('SESSION_SECURE', App::isProduction());
    }

    /**
     * Strict by default: the cookie never travels on requests started by
     * another site. The SPA still works when opened from an external link,
     * because its own API calls are same-site.
     */
    public static function sameSite(): string
    {
        $value = ucfirst(strtolower((string) Env::get('SESSION_SAMESITE', 'Strict')));

        return in_array($value, ['Strict', 'Lax'], true) ? $value : 'Strict';
    }

    private static function cookieName(): string
    {
        $name = (string) Env::get('SESSION_NAME', 'psiclinic_session');

        return preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $name) === 1 ? $name : 'psiclinic_session';
    }
}
