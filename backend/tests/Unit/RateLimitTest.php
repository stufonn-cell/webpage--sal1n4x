<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Core\Env;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\RateLimiter;
use PsiClinic\Tests\TestCase;

/** Pure parts of the rate limiter: configuration, the sliding window and Retry-After. */
final class RateLimitTest extends TestCase
{
    public function setUp(): void
    {
        self::reset();
    }

    public function tearDown(): void
    {
        self::reset();
    }

    public function testLimitsAreReadAsRequestsPerSeconds(): void
    {
        $this->assertSame([10, 60], RateLimiter::parse('10/60'));
        $this->assertSame([5, 3600], RateLimiter::parse(' 5 / 3600 '));
        $this->assertSame([30, 60], RateLimiter::parse('30'), 'A bare number means per minute');
        $this->assertNull(RateLimiter::parse('ten per minute'));
        $this->assertNull(RateLimiter::parse('0/60'));
        $this->assertNull(RateLimiter::parse('-5/60'));
    }

    public function testEveryProfileHasASafeDefault(): void
    {
        foreach (array_keys(RateLimiter::PROFILES) as $profile) {
            $limit = RateLimiter::limitFor($profile);
            $this->assertNotNull($limit, 'Profile without a default: ' . $profile);
            $this->assertGreaterThan(0, $limit[0]);
            $this->assertGreaterThan(0, $limit[1]);
        }

        $this->assertNull(RateLimiter::limitFor('unknown-profile'));
    }

    public function testLimitsComeFromTheEnvironment(): void
    {
        Env::set('RATE_LIMIT_LOGIN', '3/120');
        $this->assertSame([3, 120], RateLimiter::limitFor('login'));

        Env::set('RATE_LIMIT_LOGIN', 'off');
        $this->assertNull(RateLimiter::limitFor('login'), '"off" disables the profile');

        Env::set('RATE_LIMIT_LOGIN', 'garbage');
        $this->assertSame([10, 60], RateLimiter::limitFor('login'), 'An unreadable value falls back to the default');
    }

    public function testTheLimiterIsOffWhileTestingUnlessATestTurnsItOn(): void
    {
        $this->assertFalse(RateLimiter::enabled());

        RateLimiter::enableForTests(true);
        try {
            $this->assertTrue(RateLimiter::enabled());
        } finally {
            RateLimiter::enableForTests(false);
        }
    }

    public function testRequestsUpToTheLimitAreAllowed(): void
    {
        $this->assertTrue(RateLimiter::evaluate(0, 10, 10, 60, 30)['allowed']);
        $this->assertFalse(RateLimiter::evaluate(0, 11, 10, 60, 30)['allowed']);
        $this->assertSame(0, RateLimiter::evaluate(0, 10, 10, 60, 30)['remaining']);
        $this->assertSame(7, RateLimiter::evaluate(0, 3, 10, 60, 30)['remaining']);
    }

    public function testThePreviousWindowStillCountsWhileItSlidesOut(): void
    {
        // 10 hits at the very end of the last window: a fresh window does not
        // open the door to another burst right away (fixed windows would).
        $this->assertFalse(RateLimiter::evaluate(10, 1, 10, 60, 0)['allowed']);
        // Halfway through, half of those hits still count.
        $this->assertTrue(RateLimiter::evaluate(10, 5, 10, 60, 30)['allowed']);
        $this->assertFalse(RateLimiter::evaluate(10, 6, 10, 60, 30)['allowed']);
    }

    public function testRetryAfterIsTheRealWaitUntilTheNextAcceptedRequest(): void
    {
        $max = 10;
        $decay = 60;
        $cases = [[0, 11, 30], [10, 1, 0], [10, 6, 30], [4, 10, 59], [25, 3, 12], [0, 40, 5]];

        foreach ($cases as [$previous, $current, $elapsed]) {
            $result = RateLimiter::evaluate($previous, $current, $max, $decay, $elapsed);
            $this->assertFalse($result['allowed']);
            $wait = $result['retryAfter'];
            $this->assertTrue($wait >= 1 && $wait <= 2 * $decay, 'Retry-After out of bounds: ' . $wait);

            // Simulate one more attempt after waiting exactly Retry-After seconds.
            $this->assertTrue(
                self::acceptedAfter($previous, $current, $max, $decay, $elapsed, $wait),
                sprintf('Still refused after %d s (prev %d, cur %d, elapsed %d)', $wait, $previous, $current, $elapsed)
            );
            if ($wait > 1) {
                $this->assertFalse(
                    self::acceptedAfter($previous, $current, $max, $decay, $elapsed, $wait - 2),
                    sprintf('Retry-After %d s is longer than needed (prev %d, cur %d, elapsed %d)', $wait, $previous, $current, $elapsed)
                );
            }
        }
    }

    public function testIpv6ClientsAreGroupedByTheirNetwork(): void
    {
        $this->assertSame('203.0.113.7', RateLimiter::ipIdentity('203.0.113.7'));
        $this->assertSame(
            RateLimiter::ipIdentity('2001:db8:aaaa:bbbb::1'),
            RateLimiter::ipIdentity('2001:db8:aaaa:bbbb:ffff:ffff:ffff:ffff'),
            'Rotating addresses inside one /64 must not reset the counter'
        );
        $this->assertFalse(RateLimiter::ipIdentity('2001:db8:aaaa:bbbb::1') === RateLimiter::ipIdentity('2001:db8:aaaa:cccc::1'));
    }

    public function testTooManyRequestsCarriesRetryAfterAndTheUsualErrorShape(): void
    {
        $exception = HttpException::tooManyRequests('Slow down.', 42);

        $this->assertSame(429, $exception->status());
        $this->assertSame(['Retry-After' => '42'], $exception->headers());
        $this->assertSame(['error' => ['message' => 'Slow down.']], $exception->toArray());
        $this->assertSame('1', HttpException::tooManyRequests('x', 0)->headers()['Retry-After'], 'Never less than one second');
    }

    private static function reset(): void
    {
        Env::set('RATE_LIMIT_LOGIN', '');
        RateLimiter::enableForTests(false);
    }

    /** Would an attempt $after seconds later be accepted, with no other traffic meanwhile? */
    private static function acceptedAfter(int $previous, int $current, int $max, int $decay, int $elapsed, int $after): bool
    {
        $at = $elapsed + $after;

        if ($at < $decay) {
            return RateLimiter::evaluate($previous, $current + 1, $max, $decay, $at)['allowed'];
        }
        if ($at < 2 * $decay) {
            return RateLimiter::evaluate($current, 1, $max, $decay, $at - $decay)['allowed'];
        }

        return true;
    }
}
