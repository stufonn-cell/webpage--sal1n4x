<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Controllers\RipsController;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Csrf;
use PsiClinic\Core\Database;
use PsiClinic\Core\Env;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Middleware\Authenticate;
use PsiClinic\Core\Middleware\RequireAdmin;
use PsiClinic\Core\Middleware\RequirePatient;
use PsiClinic\Core\Middleware\RequireStaff;
use PsiClinic\Core\Middleware\VerifyCsrf;
use PsiClinic\Core\RateLimiter;
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;
use PsiClinic\Core\Router;
use PsiClinic\Domain\Settings;

/**
 * Attacks against the real API: SQL injection payloads, LIKE wildcards,
 * broken access control, oversized or malformed input, rate limits and
 * session handling. The rate limiter is switched on only inside the tests
 * that check it.
 */
final class SecurityTest extends FeatureTestCase
{
    private const SQL_PAYLOADS = [
        "' OR 1=1 --",
        "' OR '1'='1",
        '1; DROP TABLE users',
        "1' UNION SELECT username, password_hash, email, role FROM users --",
        "\" OR \"\"=\"",
        "') OR SLEEP(2) --",
        '%',
        '_',
        '%%',
        '\\',
        "admin'/*",
    ];

    private int $adminId = 0;
    private int $psychologistId = 0;
    private int $patientId = 0;

    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        Auth::logout();
        RateLimiter::enableForTests(false);
        Database::run('DELETE FROM rate_limits');

        $this->adminId = $this->createUser('admin', 'boss');
        $this->psychologistId = $this->createUser('psychologist', 'laura');
        $this->patientId = $this->createPatient(['first_name' => 'Mariana', 'last_name' => 'Vega']);
    }

    public function tearDown(): void
    {
        RipsController::$clientFactory = null;
    }

    // ----- SQL injection -------------------------------------------------

    public function testSqlPayloadsInSearchBoxesAndFiltersAreJustText(): void
    {
        $this->loginAs('laura');
        $this->createNote($this->patientId, $this->psychologistId);
        $users = (int) Database::value('SELECT COUNT(*) FROM users');

        $endpoints = [
            ['/api/search', 'q'], ['/api/patients', 'q'], ['/api/patients', 'status'], ['/api/notes', 'q'],
            ['/api/invoices', 'status'], ['/api/assessments', 'instrument'], ['/api/assessments', 'status'],
            ['/api/appointment-requests', 'status'], ['/api/icd11', 'q'], ['/api/appointments', 'week'],
            ['/api/patients', 'page'], ['/api/icd11', 'limit'],
        ];

        foreach ($endpoints as [$path, $parameter]) {
            foreach (self::SQL_PAYLOADS as $payload) {
                [$status, $body] = $this->call('GET', $path, [], [$parameter => $payload]);
                $this->assertSame(200, $status, sprintf('%s?%s=%s answered %d', $path, $parameter, $payload, $status));
                $this->assertNoLeak($body);
            }
        }

        // A payload that "matched everything" would list Mariana.
        [, $body] = $this->call('GET', '/api/patients', [], ['q' => "' OR 1=1 --"]);
        $this->assertSame(0, $body['data']['total']);
        [, $body] = $this->call('GET', '/api/search', [], ['q' => "' OR '1'='1"]);
        $this->assertCount(0, $body['results']);

        $this->assertSame($users, (int) Database::value('SELECT COUNT(*) FROM users'), 'The users table was altered');
    }

    public function testLikeWildcardsMatchOnlyLiterally(): void
    {
        $this->loginAs('laura');
        $this->createPatient(['first_name' => 'Ana', 'last_name' => 'Paz']);

        foreach (['%%', '__', '%_', '\\%'] as $wildcard) {
            [, $body] = $this->call('GET', '/api/search', [], ['q' => $wildcard]);
            $this->assertCount(0, $body['results'], 'The search treated ' . $wildcard . ' as a wildcard');

            [, $body] = $this->call('GET', '/api/patients', [], ['q' => $wildcard]);
            $this->assertSame(0, $body['data']['total'], 'The patient list treated ' . $wildcard . ' as a wildcard');
        }

        $this->createPatient(['first_name' => '100%', 'last_name' => 'Real_name']);
        [, $body] = $this->call('GET', '/api/patients', [], ['q' => '100%']);
        $this->assertSame(1, $body['data']['total'], 'A literal % must still be searchable');
        [, $body] = $this->call('GET', '/api/patients', [], ['q' => 'l_n']);
        $this->assertSame(1, $body['data']['total'], 'A literal _ must still be searchable');
    }

    public function testSqlPayloadsAtSignInNeverLogAnyoneIn(): void
    {
        $payloads = array_merge(self::SQL_PAYLOADS, ["boss' --", "boss' #", 'boss" --', "' OR username LIKE '%"]);

        foreach ($payloads as $payload) {
            foreach ([['identifier' => $payload, 'password' => $payload], ['identifier' => 'boss', 'password' => $payload]] as $credentials) {
                $_SESSION = [];
                Database::run('DELETE FROM login_attempts');
                [$status, $body] = $this->call('POST', '/api/auth/login', $credentials);

                $this->assertSame(422, $status, 'Unexpected answer to ' . $payload);
                $this->assertContains("don't match", $body['error']['message']);
                $this->assertFalse(Auth::check(), 'Signed in with ' . $payload);
            }
        }

        $this->assertSame(2, (int) Database::value('SELECT COUNT(*) FROM users'));
    }

    public function testNonTextCredentialsAreRefusedCleanly(): void
    {
        foreach ([['identifier' => ['boss'], 'password' => 'Password1234'], ['identifier' => 'boss', 'password' => ['Password1234']]] as $credentials) {
            [$status] = $this->call('POST', '/api/auth/login', $credentials);
            $this->assertSame(422, $status);
            $this->assertFalse(Auth::check());
        }

        [$status] = $this->call('POST', '/api/auth/login', ['identifier' => str_repeat('a', 5000), 'password' => 'x']);
        $this->assertSame(422, $status);
    }

    public function testRouteIdsOnlyAcceptDigits(): void
    {
        $this->loginAs('laura');

        foreach (['1abc', '1 OR 1=1', "1'", '1;DROP TABLE patients', '-1', '1e3'] as $id) {
            [$status] = $this->call('GET', '/api/patients/' . rawurlencode($id));
            $this->assertSame(404, $status, 'Route matched id ' . $id);
        }

        [$status] = $this->call('GET', '/api/patients/' . $this->patientId);
        $this->assertSame(200, $status);
    }

    // ----- Access control -------------------------------------------------

    public function testEveryWriteRouteRequiresTheCsrfToken(): void
    {
        foreach ($this->router()->routes() as $route) {
            if ($route['method'] === 'GET') {
                continue;
            }
            $this->assertTrue(
                in_array(VerifyCsrf::class, $route['middleware'], true),
                sprintf('%s %s has no CSRF check', $route['method'], $route['pattern'])
            );
        }
    }

    public function testOnlyThePublicRoutesWorkWithoutASession(): void
    {
        $public = ['GET /api/session', 'GET /api/public/site', 'POST /api/public/appointment-requests', 'POST /api/auth/login'];
        $guards = [Authenticate::class, RequireStaff::class, RequirePatient::class];

        foreach ($this->router()->routes() as $route) {
            $name = $route['method'] . ' ' . $route['pattern'];
            if (in_array($name, $public, true)) {
                continue;
            }
            $this->assertTrue(
                array_intersect($guards, $route['middleware']) !== [],
                $name . ' can be reached without signing in'
            );
        }
    }

    public function testAdministrativeRoutesRequireAnAdministrator(): void
    {
        $adminOnly = [
            'DELETE /api/patients/{id}', 'PUT /api/settings', 'POST /api/users', 'PATCH /api/users/{id}',
            'POST /api/users/{id}/toggle', 'GET /api/rips', 'POST /api/rips', 'POST /api/rips/preview',
            'GET /api/rips/{id}', 'GET /api/rips/{id}/download', 'POST /api/rips/{id}/send', 'DELETE /api/rips/{id}',
        ];
        $routes = [];
        foreach ($this->router()->routes() as $route) {
            $routes[$route['method'] . ' ' . $route['pattern']] = $route['middleware'];
        }

        foreach ($adminOnly as $name) {
            $this->assertArrayHasKey($name, $routes);
            $this->assertTrue(in_array(RequireAdmin::class, $routes[$name], true), $name . ' is not restricted to administrators');
        }

        $this->loginAs('laura');
        [$status] = $this->call('DELETE', '/api/patients/' . $this->patientId);
        $this->assertSame(403, $status);
        [$status] = $this->call('GET', '/api/rips');
        $this->assertSame(403, $status);
    }

    public function testAPatientCannotReachOtherRecordsByChangingIds(): void
    {
        $other = $this->createPatient(['first_name' => 'Other', 'last_name' => 'Person']);
        $userId = $this->createUser('patient', 'mr-portal');
        Database::update('users', $userId, ['patient_id' => $this->patientId]);
        $foreignDocument = Database::insert('documents', [
            'uuid' => uuid(), 'patient_id' => $other, 'title' => 'Report', 'category' => 'general',
            'stored_name' => uuid() . '.pdf', 'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10,
        ]);
        $foreignAssessment = Database::insert('assessments', [
            'uuid' => uuid(), 'patient_id' => $other, 'instrument_code' => 'PHQ-9', 'status' => 'pending',
        ]);

        $this->loginAs('mr-portal');

        [$status] = $this->call('GET', '/api/documents/' . $foreignDocument . '/download');
        $this->assertSame(404, $status);
        [$status] = $this->call('GET', '/api/portal/questionnaires/' . $foreignAssessment);
        $this->assertSame(404, $status);
        [$status] = $this->call('GET', '/api/patients/' . $other);
        $this->assertSame(403, $status);
        [$status] = $this->call('GET', '/api/search', [], ['q' => 'Other']);
        $this->assertSame(403, $status);
    }

    public function testOnlyTheAuthorOrAnAdministratorCanRewriteANote(): void
    {
        $noteId = $this->createNote($this->patientId, $this->psychologistId);
        $this->createUser('psychologist', 'colleague');
        $payload = ['patient_id' => $this->patientId, 'session_date' => date('Y-m-d'), 'format' => 'soap', 'subjective' => 'Changed'];

        $this->loginAs('colleague');
        [$status] = $this->call('PUT', '/api/notes/' . $noteId, $payload);
        $this->assertSame(403, $status);

        $this->loginAs('laura');
        [$status] = $this->call('PUT', '/api/notes/' . $noteId, $payload);
        $this->assertSame(200, $status);

        $this->loginAs('boss');
        [$status] = $this->call('PUT', '/api/notes/' . $noteId, ['subjective' => 'Admin fix'] + $payload);
        $this->assertSame(200, $status);
    }

    public function testCrossSiteWritesAreRefusedEvenWithAToken(): void
    {
        $this->loginAs('laura');

        [$status] = $this->call('POST', '/api/patients', ['first_name' => 'X', 'last_name' => 'Y', 'gender' => 'female', 'status' => 'active'], [], true, [
            'HTTP_SEC_FETCH_SITE' => 'cross-site',
        ]);
        $this->assertSame(403, $status);

        [$status] = $this->call('POST', '/api/patients', ['first_name' => 'X', 'last_name' => 'Y', 'gender' => 'female', 'status' => 'active'], [], true, [
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ]);
        $this->assertSame(201, $status);
    }

    // ----- Input validation (no 500s) ------------------------------------

    public function testOversizedOrInvalidValuesGiveFriendlyErrorsInsteadOfServerErrors(): void
    {
        $this->loginAs('laura');
        $base = ['first_name' => 'Lucia', 'last_name' => 'Paz', 'gender' => 'female', 'status' => 'active'];

        [$status, $body] = $this->call('POST', '/api/patients', $base + ['address' => str_repeat('a', 500)]);
        $this->assertSame(422, $status);
        $this->assertArrayHasKey('address', $body['error']['fields']);

        [$status, $body] = $this->call('POST', '/api/patients', $base + ['birth_date' => 'tomorrow']);
        $this->assertSame(422, $status);
        $this->assertArrayHasKey('birth_date', $body['error']['fields']);

        [$status, $body] = $this->call('POST', '/api/patients', $base + ['psychologist_id' => 99999]);
        $this->assertSame(422, $status);
        $this->assertArrayHasKey('psychologist_id', $body['error']['fields']);

        $appointment = [
            'patient_id' => 99999, 'psychologist_id' => $this->psychologistId, 'date' => date('Y-m-d', strtotime('+2 days')),
            'time' => '10:00', 'duration' => 50,
        ];
        [$status, $body] = $this->call('POST', '/api/appointments', $appointment);
        $this->assertSame(422, $status, 'An unknown patient must not end in a database error');
        $this->assertArrayHasKey('patient_id', $body['error']['fields']);

        $portalUser = $this->createUser('patient', 'not-a-clinician');
        [$status, $body] = $this->call('POST', '/api/appointments', ['patient_id' => $this->patientId, 'psychologist_id' => $portalUser] + $appointment);
        $this->assertSame(422, $status, 'A patient account cannot be booked as the professional');
        $this->assertArrayHasKey('psychologist_id', $body['error']['fields']);

        [$status] = $this->call('POST', '/api/appointments', ['patient_id' => $this->patientId, 'time' => '10:00; DROP TABLE x'] + $appointment);
        $this->assertSame(422, $status);

        [$status] = $this->call('POST', '/api/invoices', [
            'patient_id' => $this->patientId, 'issued_at' => date('Y-m-d'),
            'items' => [['description' => 'Session', 'quantity' => '1e308', 'unit_price' => '9999999999999']],
        ]);
        $this->assertSame(422, $status, 'Amounts beyond the column size must not overflow');

        $otherPatient = $this->createPatient();
        $foreignAppointment = $this->createAppointment($otherPatient, $this->psychologistId, date('Y-m-d H:i:s', strtotime('+1 day')));
        [$status, $body] = $this->call('POST', '/api/notes', [
            'patient_id' => $this->patientId, 'session_date' => date('Y-m-d'), 'format' => 'soap', 'appointment_id' => $foreignAppointment,
        ]);
        $this->assertSame(422, $status, "A note cannot be linked to another patient's appointment");
        $this->assertArrayHasKey('appointment_id', $body['error']['fields']);

        [$status] = $this->call('GET', '/api/patients', [], ['page' => '9999999999999999999999']);
        $this->assertSame(200, $status, 'A huge page number must not overflow the OFFSET');
    }

    // ----- Rate limiting --------------------------------------------------

    public function testSignInIsThrottledPerConnection(): void
    {
        $this->withLimiter(['RATE_LIMIT_LOGIN' => '3/60'], function (): void {
            for ($i = 0; $i < 3; $i++) {
                [$status] = $this->call('POST', '/api/auth/login', ['identifier' => 'user' . $i, 'password' => 'wrong']);
                $this->assertSame(422, $status);
            }

            [$status, $body, $headers] = $this->call('POST', '/api/auth/login', ['identifier' => 'boss', 'password' => 'Password1234']);
            $this->assertSame(429, $status);
            $this->assertContains('too many sign-in attempts', $body['error']['message']);
            $this->assertArrayHasKey('Retry-After', $headers);
            $this->assertTrue((int) $headers['Retry-After'] >= 1 && (int) $headers['Retry-After'] <= 120);
            $this->assertFalse(Auth::check(), 'A throttled request must not sign in');

            // Another connection is not affected.
            [$status] = $this->call('POST', '/api/auth/login', ['identifier' => 'boss', 'password' => 'Password1234'], [], true, ['REMOTE_ADDR' => '203.0.113.9']);
            $this->assertSame(200, $status);
        });
    }

    public function testTheWholeApiHasAGlobalLimitPerIp(): void
    {
        $this->withLimiter(['RATE_LIMIT_API' => '5/60'], function (): void {
            for ($i = 0; $i < 5; $i++) {
                [$status] = $this->call('GET', '/api/session');
                $this->assertSame(200, $status);
            }

            [$status, $body, $headers] = $this->call('GET', '/api/session');
            $this->assertSame(429, $status);
            $this->assertArrayHasKey('message', $body['error']);
            $this->assertArrayHasKey('Retry-After', $headers);

            // Unknown routes count too: they cannot be used to probe the API freely.
            [$status] = $this->call('GET', '/api/does-not-exist');
            $this->assertSame(429, $status);
        });
    }

    public function testSearchIsThrottledPerUser(): void
    {
        $this->withLimiter(['RATE_LIMIT_SEARCH' => '2/60'], function (): void {
            $this->loginAs('laura');

            $this->assertSame(200, $this->call('GET', '/api/search', [], ['q' => 'Ma'])[0]);
            $this->assertSame(200, $this->call('GET', '/api/search', [], ['q' => 'Mar'])[0]);
            [$status, $body] = $this->call('GET', '/api/search', [], ['q' => 'Mari']);
            $this->assertSame(429, $status);
            $this->assertContains('searching very quickly', $body['error']['message']);

            // The same IP with another account still has its own budget.
            $this->loginAs('boss');
            $this->assertSame(200, $this->call('GET', '/api/search', [], ['q' => 'Ma'])[0]);
        });
    }

    public function testCountersLiveInTheDatabaseAndAreHashed(): void
    {
        $this->withLimiter([], function (): void {
            $this->call('GET', '/api/session');

            $rows = Database::all('SELECT rate_key, bucket, hits FROM rate_limits');
            $this->assertCount(1, $rows);
            $this->assertSame('api', $rows[0]['bucket']);
            $this->assertSame(64, strlen((string) $rows[0]['rate_key']));
            $this->assertFalse(str_contains((string) $rows[0]['rate_key'], '127.0.0.1'));

            Database::run('UPDATE rate_limits SET expires_at = 1');
            $this->assertSame(1, RateLimiter::cleanup());
        });
    }

    public function testADisabledProfileDoesNotThrottle(): void
    {
        $this->withLimiter(['RATE_LIMIT_API' => 'off'], function (): void {
            for ($i = 0; $i < 8; $i++) {
                $this->assertSame(200, $this->call('GET', '/api/session')[0]);
            }
            $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM rate_limits'));
        });
    }

    // ----- Sessions and passwords ----------------------------------------

    public function testUnknownUsersAndWrongPasswordsGetTheSameAnswer(): void
    {
        [$unknownStatus, $unknown] = $this->call('POST', '/api/auth/login', ['identifier' => 'nobody', 'password' => 'Password1234']);
        [$wrongStatus, $wrong] = $this->call('POST', '/api/auth/login', ['identifier' => 'boss', 'password' => 'wrong-password']);
        Database::update('users', $this->psychologistId, ['is_active' => 0]);
        [$inactiveStatus, $inactive] = $this->call('POST', '/api/auth/login', ['identifier' => 'laura', 'password' => 'Password1234']);

        $this->assertSame($unknownStatus, $wrongStatus);
        $this->assertSame($unknownStatus, $inactiveStatus);
        $this->assertSame($unknown, $wrong);
        $this->assertSame($unknown, $inactive);
    }

    public function testChangingThePasswordEndsEveryOtherSession(): void
    {
        $this->loginAs('laura');
        $otherDevice = $_SESSION;

        [$status] = $this->call('PUT', '/api/profile', [
            'full_name' => 'Laura Moreno', 'email' => 'laura@psiclinic.test',
            'current_password' => 'Password1234', 'password' => 'A-new-passphrase-2026',
        ]);
        $this->assertSame(200, $status);

        Auth::refresh();
        $this->assertTrue(Auth::check(), 'The browser that changed the password stays signed in');

        $_SESSION = $otherDevice;
        Auth::refresh();
        $this->assertFalse(Auth::check(), 'A session opened with the old password must end');
    }

    public function testDeactivatingAnAccountEndsItsSessionImmediately(): void
    {
        $this->loginAs('laura');
        Database::update('users', $this->psychologistId, ['is_active' => 0]);
        Auth::refresh();

        [$status] = $this->call('GET', '/api/dashboard');
        $this->assertSame(401, $status);
    }

    public function testNewPasswordsAreHashedWithBcryptAndTheConfiguredCost(): void
    {
        $this->loginAs('laura');
        $this->call('PUT', '/api/profile', [
            'full_name' => 'Laura Moreno', 'email' => 'laura@psiclinic.test',
            'current_password' => 'Password1234', 'password' => 'A-new-passphrase-2026',
        ]);

        $hash = (string) Database::value('SELECT password_hash FROM users WHERE id = :id', ['id' => $this->psychologistId]);
        $this->assertSame('2y', password_get_info($hash)['algo']);
        $this->assertSame(Auth::passwordOptions()['cost'], password_get_info($hash)['options']['cost']);

        [$status, $body] = $this->call('PUT', '/api/profile', [
            'full_name' => 'Laura Moreno', 'email' => 'laura@psiclinic.test',
            'current_password' => 'A-new-passphrase-2026', 'password' => str_repeat('x', 80),
        ]);
        $this->assertSame(422, $status, 'bcrypt ignores everything after 72 bytes: longer passwords are refused');
        $this->assertArrayHasKey('password', $body['error']['fields']);
    }

    // ----- Outbound calls (SSRF) -----------------------------------------

    public function testTheRipsValidatorIsNeverCalledOnMetadataAddresses(): void
    {
        $this->loginAs('boss');
        $reportId = Database::insert('rips_reports', [
            'note_number' => '1', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'payload' => '{}', 'users_count' => 1, 'services_count' => 1, 'created_by' => $this->adminId,
        ]);
        $called = false;
        RipsController::$clientFactory = static function () use (&$called): never {
            $called = true;
            throw new \RuntimeException('The validator must not be called.');
        };

        foreach (['http://169.254.169.254/latest/meta-data', 'http://metadata.google.internal', 'http://[::ffff:169.254.169.254]'] as $url) {
            Settings::put('rips_validator_url', $url);
            [$status] = $this->call('POST', '/api/rips/' . $reportId . '/send', [
                'document_type' => 'CC', 'document_number' => '1020304050', 'password' => 'secret',
            ]);
            $this->assertSame(422, $status, 'Validator called on ' . $url);
        }

        $this->assertFalse($called);
    }

    // ----- Helpers -------------------------------------------------------

    /** Runs $test with the real rate limiter on and the given limits, then restores everything. */
    private function withLimiter(array $limits, callable $test): void
    {
        RateLimiter::enableForTests(true);
        foreach ($limits as $key => $value) {
            Env::set($key, $value);
        }

        try {
            $test();
        } finally {
            RateLimiter::enableForTests(false);
            foreach (array_keys($limits) as $key) {
                Env::set($key, '');
            }
            Database::run('DELETE FROM rate_limits');
        }
    }

    private function router(): Router
    {
        return require dirname(__DIR__, 2) . '/src/routes.php';
    }

    /** @return array{0:int,1:array,2:array} status, decoded body, extra headers */
    private function call(string $method, string $uri, array $body = [], array $query = [], bool $withToken = true, array $server = []): array
    {
        $server += [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri . ($query === [] ? '' : '?' . http_build_query($query)),
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        if ($withToken) {
            $server['HTTP_X_CSRF_TOKEN'] = Csrf::token();
        }

        $previousAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = $server['REMOTE_ADDR'];

        ob_start();
        try {
            Response::$lastStatus = 200;
            Response::$lastHeaders = [];
            $this->router()->dispatch(new Request($query, $body, [], $server));
            $status = Response::$lastStatus;
            $headers = Response::$lastHeaders;
        } catch (HttpException $exception) {
            Response::json($exception->toArray(), $exception->status(), $exception->headers());
            $status = $exception->status();
            $headers = $exception->headers();
        } finally {
            $output = (string) ob_get_clean();
            $_SERVER['REMOTE_ADDR'] = $previousAddress;
        }

        return [$status, json_decode($output, true) ?? [], $headers];
    }

    private function loginAs(string $username): void
    {
        Auth::logout();
        $_SESSION = [];
        $this->assertTrue(Auth::attempt($username, 'Password1234'), 'Could not sign in as ' . $username);
    }

    /** Nothing that looks like a database error or a password hash reaches the client. */
    private function assertNoLeak(array $body): void
    {
        $text = (string) json_encode($body);

        foreach (['SQLSTATE', 'syntax', 'PDO', 'mysql', '$2y$', 'password_hash'] as $needle) {
            $this->assertFalse(stripos($text, $needle) !== false, 'Response leaks "' . $needle . '": ' . substr($text, 0, 200));
        }
    }
}
