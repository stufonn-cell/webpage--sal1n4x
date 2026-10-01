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
    private const IP_ATTEMPT_FACTOR = 4;

    private static ?array $user = null;

    public static function attempt(string $identifier, string $password): bool
    {
        $user = Database::first(
            'SELECT * FROM users WHERE (username = :username OR email = :email) AND is_active = 1 LIMIT 1',
            ['username' => $identifier, 'email' => $identifier]
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
        Csrf::rotate();
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

    public static function refresh(): void
    {
        self::$user = null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function patientId(): ?int
    {
        $user = self::user();

        return $user === null || $user['patient_id'] === null ? null : (int) $user['patient_id'];
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
        return hash('sha256', mb_strtolower(trim($identifier)));
    }

    /**
     * Los intentos fallidos se guardan en la base y no en la sesion: borrar la
     * cookie no debe reiniciar el contador. Se limita por usuario y por IP.
     */
    public static function tooManyAttempts(string $identifier, ?string $ip = null): bool
    {
        $max = Env::int('LOGIN_MAX_ATTEMPTS', 5);
        $since = date('Y-m-d H:i:s', time() - Env::int('LOGIN_LOCKOUT_SECONDS', 900));

        $byIdentifier = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE throttle_key = :k AND attempted_at >= :since',
            ['k' => self::throttleKey($identifier), 'since' => $since]
        );

        if ($byIdentifier >= $max) {
            return true;
        }

        if ($ip === null) {
            return false;
        }

        $byIp = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND attempted_at >= :since',
            ['ip' => $ip, 'since' => $since]
        );

        return $byIp >= $max * self::IP_ATTEMPT_FACTOR;
    }

    public static function recordFailure(string $identifier, ?string $ip = null): void
    {
        Database::insert('login_attempts', [
            'throttle_key' => self::throttleKey($identifier),
            'ip_address' => substr((string) ($ip ?? ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45),
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function clearAttempts(string $identifier): void
    {
        Database::run('DELETE FROM login_attempts WHERE throttle_key = :k', ['k' => self::throttleKey($identifier)]);
    }
}
