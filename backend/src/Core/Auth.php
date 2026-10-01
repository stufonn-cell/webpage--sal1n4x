<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

use PsiClinic\Domain\AuditLog;

final class Auth
{
    private const IP_ATTEMPT_FACTOR = 4;

    /** Longest identifier (username or email) and password accepted at sign-in. */
    public const MAX_IDENTIFIER_LENGTH = 180;
    public const MAX_PASSWORD_LENGTH = 1024;

    /** bcrypt only uses the first 72 bytes of a password. */
    public const MAX_NEW_PASSWORD_BYTES = 72;
    public const MIN_NEW_PASSWORD_LENGTH = 10;

    private const SESSION_USER = 'user_id';
    private const SESSION_FINGERPRINT = '_auth_fingerprint';
    private const SESSION_ROLE = '_auth_role';

    private static ?array $user = null;

    public static function attempt(string $identifier, string $password): bool
    {
        $user = Database::first(
            'SELECT * FROM users WHERE (username = :username OR email = :email) AND is_active = 1 LIMIT 1',
            ['username' => $identifier, 'email' => $identifier]
        );

        if ($user === null) {
            // Same amount of work as a real check, so the response time does
            // not reveal whether the account exists.
            password_hash($password, PASSWORD_BCRYPT, self::passwordOptions());

            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (self::needsRehash((string) $user['password_hash'])) {
            $user['password_hash'] = self::hashPassword($password);
            Database::update('users', (int) $user['id'], ['password_hash' => $user['password_hash']]);
        }

        Session::markAuthenticated();
        self::remember($user);
        Csrf::rotate();
        Database::update('users', (int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        self::$user = $user;

        AuditLog::record('login', 'user', (int) $user['id']);

        return true;
    }

    /**
     * The signed-in user, reloaded from the database on every request. The
     * session ends by itself when the account was deactivated or its password
     * changed after this session signed in (the stored fingerprint no longer
     * matches), and the session id is renewed when the role changed.
     */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = Session::get(self::SESSION_USER);
        if (!is_int($id)) {
            return null;
        }

        $user = Database::first('SELECT * FROM users WHERE id = :id AND is_active = 1', ['id' => $id]);
        $stored = Session::get(self::SESSION_FINGERPRINT);

        if ($user === null || !is_string($stored) || !hash_equals(self::fingerprint($user), $stored)) {
            self::forgetSession();

            return null;
        }

        if (Session::get(self::SESSION_ROLE) !== $user['role']) {
            Session::regenerate();
            Session::put(self::SESSION_ROLE, $user['role']);
        }

        self::$user = $user;

        return self::$user;
    }

    public static function refresh(): void
    {
        self::$user = null;
    }

    /**
     * After the signed-in person changes their own password: this session
     * stays valid with a new id, every other session of the account ends.
     */
    public static function passwordChanged(): void
    {
        $id = self::id();
        self::$user = null;

        $user = $id === null ? null : Database::first('SELECT * FROM users WHERE id = :id AND is_active = 1', ['id' => $id]);
        if ($user === null) {
            return;
        }

        Session::regenerate();
        self::remember($user);
        self::$user = $user;
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

    /** bcrypt with an explicit cost (PASSWORD_BCRYPT_COST, 12 by default). */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, self::passwordOptions());
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, self::passwordOptions());
    }

    /**
     * Rules for a new password. Returns the message to show, or null when it
     * is acceptable.
     */
    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_NEW_PASSWORD_LENGTH) {
            return sprintf('Use at least %d characters.', self::MIN_NEW_PASSWORD_LENGTH);
        }
        if (strlen($password) > self::MAX_NEW_PASSWORD_BYTES) {
            return sprintf('Use %d characters or fewer.', self::MAX_NEW_PASSWORD_BYTES);
        }

        return null;
    }

    public static function passwordOptions(): array
    {
        $cost = Env::int('PASSWORD_BCRYPT_COST', App::isTesting() ? 10 : 12);

        return ['cost' => max(10, min(15, $cost))];
    }

    public static function throttleKey(string $identifier): string
    {
        return hash('sha256', mb_strtolower(trim($identifier)));
    }

    /**
     * Failed attempts are stored in the database, not in the session: deleting
     * the cookie must not reset the counter. Limits apply per user and per IP.
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

        // Old attempts are useless for the lockout: trim them now and then so
        // the table cannot grow without limit under a sustained attack.
        if (random_int(1, 50) === 1) {
            Database::run(
                'DELETE FROM login_attempts WHERE attempted_at < :before LIMIT 1000',
                ['before' => date('Y-m-d H:i:s', time() - max(86400, Env::int('LOGIN_LOCKOUT_SECONDS', 900)))]
            );
        }
    }

    public static function clearAttempts(string $identifier): void
    {
        Database::run('DELETE FROM login_attempts WHERE throttle_key = :k', ['k' => self::throttleKey($identifier)]);
    }

    private static function remember(array $user): void
    {
        Session::put(self::SESSION_USER, (int) $user['id']);
        Session::put(self::SESSION_FINGERPRINT, self::fingerprint($user));
        Session::put(self::SESSION_ROLE, (string) $user['role']);
    }

    private static function forgetSession(): void
    {
        Session::forget(self::SESSION_USER);
        Session::forget(self::SESSION_FINGERPRINT);
        Session::forget(self::SESSION_ROLE);
        Session::regenerate();
    }

    /** Ties the session to the current password hash without storing the hash itself. */
    private static function fingerprint(array $user): string
    {
        return hash_hmac('sha256', (int) $user['id'] . '|' . (string) $user['password_hash'], App::key());
    }
}
