<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

use PsiClinic\Domain\AuditLog;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $identifier, string $password): bool
    {
        $user = Database::first(
            'SELECT * FROM users WHERE (username = :id OR email = :id) AND is_active = 1 LIMIT 1',
            ['id' => $identifier]
        );

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', (int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Database::update('users', (int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        self::$user = $user;

        AuditLog::record('login', 'user', (int) $user['id']);

        return true;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = Session::get('user_id');
        if (!is_int($id)) {
            return null;
        }

        self::$user = Database::first('SELECT * FROM users WHERE id = :id AND is_active = 1', ['id' => $id]);

        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): string
    {
        return (string) (self::user()['role'] ?? 'guest');
    }

    public static function is(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function isStaff(): bool
    {
        return self::is('admin', 'psychologist', 'assistant');
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditLog::record('logout', 'user', (int) self::id());
        }

        self::$user = null;
        Session::destroy();
    }

    public static function throttleKey(string $identifier): string
    {
        return 'login_attempts_' . md5(strtolower($identifier));
    }

    public static function tooManyAttempts(string $identifier): bool
    {
        $state = Session::get(self::throttleKey($identifier));

        if (!is_array($state)) {
            return false;
        }

        if ($state['count'] < Env::int('LOGIN_MAX_ATTEMPTS', 5)) {
            return false;
        }

        return (time() - $state['time']) < Env::int('LOGIN_LOCKOUT_SECONDS', 900);
    }

    public static function recordFailure(string $identifier): void
    {
        $key = self::throttleKey($identifier);
        $state = Session::get($key);
        $count = is_array($state) ? (int) $state['count'] + 1 : 1;

        Session::put($key, ['count' => $count, 'time' => time()]);
    }

    public static function clearAttempts(string $identifier): void
    {
        Session::forget(self::throttleKey($identifier));
    }
}
