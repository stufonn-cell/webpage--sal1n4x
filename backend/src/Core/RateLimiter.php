<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

/**
 * Application rate limiter. PHP-FPM workers share no memory, so counters
 * live in MySQL (table rate_limits, migration 005).
 *
 * Algorithm: sliding window approximated with two fixed windows. The count
 * of the previous window is weighted by how much of it still overlaps the
 * last $decay seconds, which avoids the double burst a plain fixed window
 * allows at its edges. Every attempt counts, including refused ones, so
 * hammering an endpoint keeps it closed.
 *
 * Each profile reads "<requests>/<seconds>" from its environment variable;
 * "off" or 0 disables that profile. RATE_LIMIT_ENABLED=false disables all of
 * them. In APP_ENV=testing the limiter is off unless a test turns it on.
 */
final class RateLimiter
{
    public const PROFILES = [
        'api' => [
            'env' => 'RATE_LIMIT_API',
            'default' => '600/60',
            'by' => 'ip',
            'message' => "You're going a little too fast.",
        ],
        'login' => [
            'env' => 'RATE_LIMIT_LOGIN',
            'default' => '10/60',
            'by' => 'ip',
            'message' => 'There were too many sign-in attempts from this connection.',
        ],
        'public_form' => [
            'env' => 'RATE_LIMIT_PUBLIC_FORM',
            'default' => '10/3600',
            'by' => 'ip',
            'message' => "We've received several requests from this connection. If you need anything else, write to us or call us directly.",
        ],
        'search' => [
            'env' => 'RATE_LIMIT_SEARCH',
            'default' => '60/60',
            'by' => 'user',
            'message' => "You're searching very quickly.",
        ],
        'icd11' => [
            'env' => 'RATE_LIMIT_ICD11',
            'default' => '60/60',
            'by' => 'user',
            'message' => "You're searching the ICD-11 catalog very quickly.",
        ],
        'upload' => [
            'env' => 'RATE_LIMIT_UPLOAD',
            'default' => '30/600',
            'by' => 'user',
            'message' => "You've uploaded many files in a short time.",
        ],
        'rips_send' => [
            'env' => 'RATE_LIMIT_RIPS_SEND',
            'default' => '6/600',
            'by' => 'user',
            'message' => "You've sent several reports to the validator in a short time.",
        ],
        'profile' => [
            'env' => 'RATE_LIMIT_PROFILE',
            'default' => '20/600',
            'by' => 'user',
            'message' => "You've changed your profile many times in a short time.",
        ],
    ];

    /** Odds (1 in N) that a hit also deletes expired counters. */
    private const CLEANUP_ODDS = 50;

    private static bool $enabledInTests = false;

    /** Lets a test exercise the real limiter while the rest of the suite runs unthrottled. */
    public static function enableForTests(bool $enabled): void
    {
        self::$enabledInTests = $enabled;
    }

    public static function enabled(): bool
    {
        if (App::isTesting()) {
            return self::$enabledInTests;
        }

        return Env::bool('RATE_LIMIT_ENABLED', true);
    }

    /**
     * Throttles the request under the given profile. Throws a 429 with
     * Retry-After when the limit is exceeded; does nothing when it is not,
     * when the limiter is off, or when the profile is unknown or disabled.
     */
    public static function enforce(string $profile, Request $request): void
    {
        if (!self::enabled() || !isset(self::PROFILES[$profile])) {
            return;
        }

        $limit = self::limitFor($profile);
        if ($limit === null) {
            return;
        }

        $config = self::PROFILES[$profile];
        $identity = $config['by'] === 'user' && Auth::id() !== null
            ? 'user:' . Auth::id()
            : 'ip:' . self::ipIdentity($request->ip());

        try {
            $result = self::hit($profile, $identity, $limit[0], $limit[1]);
        } catch (\PDOException $exception) {
            // Fail open: a broken counter must not take the whole API down.
            // Nginx keeps its own per-IP limits in front of PHP.
            Log::error('Rate limiter unavailable', ['profile' => $profile, 'error' => $exception->getMessage()]);

            return;
        }

        if (!$result['allowed']) {
            throw HttpException::tooManyRequests(
                $config['message'] . ' ' . self::waitMessage($result['retryAfter']),
                $result['retryAfter']
            );
        }
    }

    /**
     * Records one attempt for $identity in $bucket and evaluates it.
     *
     * @return array{allowed: bool, retryAfter: int, remaining: int, limit: int}
     */
    public static function hit(string $bucket, string $identity, int $max, int $decay, ?int $now = null): array
    {
        $now ??= time();
        $decay = max(1, $decay);
        $window = intdiv($now, $decay) * $decay;
        $key = self::key($bucket, $identity);

        Database::run(
            'INSERT INTO rate_limits (rate_key, window_start, bucket, hits, expires_at)
             VALUES (:k, :w, :b, 1, :e)
             ON DUPLICATE KEY UPDATE hits = hits + 1',
            ['k' => $key, 'w' => $window, 'b' => substr($bucket, 0, 40), 'e' => $window + 2 * $decay]
        );

        $counts = [];
        foreach (Database::all(
            'SELECT window_start, hits FROM rate_limits WHERE rate_key = :k AND window_start IN (:current, :previous)',
            ['k' => $key, 'current' => $window, 'previous' => $window - $decay]
        ) as $row) {
            $counts[(int) $row['window_start']] = (int) $row['hits'];
        }

        if (random_int(1, self::CLEANUP_ODDS) === 1) {
            self::cleanup($now);
        }

        return self::evaluate($counts[$window - $decay] ?? 0, $counts[$window] ?? 1, $max, $decay, $now - $window);
    }

    /**
     * Pure decision for the sliding window: $previous and $current are the
     * hits of both windows (current includes this attempt) and $elapsed the
     * seconds already spent in the current window.
     *
     * @return array{allowed: bool, retryAfter: int, remaining: int, limit: int}
     */
    public static function evaluate(int $previous, int $current, int $max, int $decay, int $elapsed): array
    {
        $decay = max(1, $decay);
        $elapsed = max(0, min($decay - 1, $elapsed));
        $estimate = $previous * (($decay - $elapsed) / $decay) + $current;
        $allowed = $estimate <= $max;

        return [
            'allowed' => $allowed,
            'retryAfter' => $allowed ? 0 : self::retryAfter($previous, $current, $max, $decay, $elapsed),
            'remaining' => (int) max(0, floor($max - $estimate)),
            'limit' => $max,
        ];
    }

    /**
     * Seconds until one more attempt would be accepted if no other attempt
     * arrives meanwhile. Clamped between 1 second and two windows.
     */
    public static function retryAfter(int $previous, int $current, int $max, int $decay, int $elapsed): int
    {
        $left = $decay - $elapsed;

        // Still inside this window: wait until enough of the previous one slides out.
        if ($current + 1 <= $max && $previous > 0) {
            $wait = $left - (($max - $current - 1) * $decay / $previous);
            if ($wait < $left) {
                return self::clampWait((int) ceil($wait), $decay);
            }
        }

        // Next window: this window's hits become the weighted "previous" ones.
        $next = $current > 0 ? $decay * (1 - ($max - 1) / $current) : 0;

        return self::clampWait($left + (int) ceil(max(0.0, $next)), $decay);
    }

    /** [requests, seconds] from the profile's variable, or null when that profile is disabled. */
    public static function limitFor(string $profile): ?array
    {
        $config = self::PROFILES[$profile] ?? null;
        if ($config === null) {
            return null;
        }

        $value = Env::get($config['env'], $config['default']);
        if ($value === false || $value === null || self::isOff((string) $value)) {
            return null;
        }

        // A value that cannot be read falls back to the safe default.
        return self::parse((string) $value) ?? self::parse($config['default']);
    }

    /** Parses "10/60" (10 requests per 60 seconds) or "10" (per minute); null when unreadable. */
    public static function parse(string $value): ?array
    {
        if (preg_match('/^(\d{1,7})(?:\s*\/\s*(\d{1,7}))?$/', trim($value), $parts) !== 1) {
            return null;
        }

        $max = (int) $parts[1];
        $decay = isset($parts[2]) ? (int) $parts[2] : 60;

        return $max > 0 && $decay > 0 ? [$max, $decay] : null;
    }

    /** "off", "none", "false" or 0 turn a profile off. */
    public static function isOff(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['off', 'none', 'false', '0'], true);
    }

    /**
     * IPv4 addresses count one by one; IPv6 addresses are grouped by their
     * /64 network, because a single connection usually owns the whole /64
     * and could otherwise rotate addresses to dodge the limit.
     */
    public static function ipIdentity(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        $binary = inet_pton($ip);
        if ($binary === false) {
            return $ip;
        }

        return bin2hex(substr($binary, 0, 8)) . '::/64';
    }

    public static function clear(string $bucket, string $identity): void
    {
        Database::run('DELETE FROM rate_limits WHERE rate_key = :k', ['k' => self::key($bucket, $identity)]);
    }

    public static function cleanup(?int $now = null): int
    {
        return Database::run(
            'DELETE FROM rate_limits WHERE expires_at < :now LIMIT 1000',
            ['now' => $now ?? time()]
        )->rowCount();
    }

    /** Counters are stored under an HMAC: the table never holds IPs or user ids in clear. */
    private static function key(string $bucket, string $identity): string
    {
        return hash_hmac('sha256', $bucket . '|' . $identity, App::key());
    }

    private static function clampWait(int $seconds, int $decay): int
    {
        return max(1, min($seconds, 2 * $decay));
    }

    private static function waitMessage(int $seconds): string
    {
        if ($seconds < 10) {
            return 'Please wait a few seconds and try again.';
        }
        if ($seconds < 90) {
            return sprintf('Please wait about %d seconds and try again.', (int) (ceil($seconds / 5) * 5));
        }

        $minutes = (int) ceil($seconds / 60);

        return sprintf('Please wait about %d %s and try again.', $minutes, $minutes === 1 ? 'minute' : 'minutes');
    }
}
