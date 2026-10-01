<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Csrf;
use PsiClinic\Core\Database;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;
use PsiClinic\Core\Router;
use PsiClinic\Domain\Instruments;

/**
 * Exercises the real API (routes + middleware + controllers) the way the
 * frontend uses it: JSON body and the CSRF token in a header.
 */
final class ApiTest extends FeatureTestCase
{
    private int $psychologistId = 0;
    private int $patientId = 0;

    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        Auth::logout();

        $this->psychologistId = $this->createUser('psychologist', 'render');
        $this->patientId = $this->createPatient(['first_name' => 'Mariana', 'last_name' => 'Vega']);
    }

    public function testTheSessionEndpointWorksWithoutLogin(): void
    {
        [$status, $body] = $this->call('GET', '/api/session');

        $this->assertSame(200, $status);
        $this->assertNull($body['data']['user']);
        $this->assertSame(64, strlen($body['data']['csrfToken']));
        $this->assertCount(0, $body['data']['demoAccounts'], 'Demo accounts are only advertised locally');
    }

    public function testLoginReturnsTheUserWithoutItsPasswordHash(): void
    {
        [$status, $body] = $this->call('POST', '/api/auth/login', ['identifier' => 'render', 'password' => 'Password1234']);

        $this->assertSame(200, $status);
        $this->assertSame('render', $body['data']['user']['username']);
        $this->assertFalse(array_key_exists('password_hash', $body['data']['user']));
    }

    public function testWrongCredentialsGiveAFriendlyError(): void
    {
        [$status, $body] = $this->call('POST', '/api/auth/login', ['identifier' => 'render', 'password' => 'wrong']);

        $this->assertSame(422, $status);
        $this->assertContains("don't match", $body['error']['message']);
    }

    public function testWritesWithoutTheCsrfTokenAreRejected(): void
    {
        $this->loginAs('render');

        [$status] = $this->call('POST', '/api/patients', ['first_name' => 'X'], [], false);

        $this->assertSame(419, $status);
    }

    public function testStaffEndpointsRequireASession(): void
    {
        [$status] = $this->call('GET', '/api/dashboard');

        $this->assertSame(401, $status);
    }

    public function testPatientsCannotReachStaffEndpoints(): void
    {
        $this->createPortalUser($this->patientId, 'mr-portal');
        $this->loginAs('mr-portal');

        [$status] = $this->call('GET', '/api/patients');

        $this->assertSame(403, $status);
    }

    public function testDashboardReturnsTheMetrics(): void
    {
        $this->loginAs('render');

        [$status, $body] = $this->call('GET', '/api/dashboard');

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('active_patients', $body['data']['metrics']);
        $this->assertArrayHasKey('new_requests', $body['data']['metrics']);
    }

    public function testCreatingAPatientValidatesEachField(): void
    {
        $this->loginAs('render');

        [$status, $body] = $this->call('POST', '/api/patients', ['first_name' => '', 'email' => 'not-an-email']);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('first_name', $body['error']['fields']);
        $this->assertArrayHasKey('email', $body['error']['fields']);
    }

    public function testUnknownEnumValuesFallBackInsteadOfFailing(): void
    {
        $this->loginAs('render');

        [$status, $body] = $this->call('POST', '/api/patients', [
            'first_name' => 'Lucia', 'last_name' => 'Paz', 'gender' => 'other-value', 'status' => 'odd', 'risk_level' => 'x',
        ]);

        $this->assertSame(201, $status);
        $row = Database::first('SELECT gender, status, risk_level FROM patients WHERE id = :id', ['id' => $body['data']['id']]);
        $this->assertSame('undisclosed', $row['gender']);
        $this->assertSame('active', $row['status']);
        $this->assertSame('none', $row['risk_level']);
    }

    public function testThePatientProfileBundlesEverySection(): void
    {
        $this->loginAs('render');

        [$status, $body] = $this->call('GET', '/api/patients/' . $this->patientId);

        $this->assertSame(200, $status);
        foreach (['patient', 'notes', 'assessments', 'series', 'timeline', 'diagnoses', 'appointments', 'documents', 'consents', 'invoices'] as $key) {
            $this->assertArrayHasKey($key, $body['data'], 'Missing section ' . $key);
        }
        $this->assertSame('Mariana', $body['data']['patient']['first_name']);
    }

    public function testOverlappingAppointmentsAreAConflict(): void
    {
        $this->loginAs('render');
        $date = date('Y-m-d', strtotime('+3 days'));
        $payload = [
            'patient_id' => $this->patientId, 'psychologist_id' => $this->psychologistId,
            'date' => $date, 'time' => '10:00', 'duration' => 50,
        ];

        [$first] = $this->call('POST', '/api/appointments', $payload);
        [$second, $body] = $this->call('POST', '/api/appointments', ['time' => '10:30'] + $payload);

        $this->assertSame(201, $first);
        $this->assertSame(409, $second);
        $this->assertContains('overlaps', $body['error']['message']);
    }

    public function testMeetingLinksMustBeHttp(): void
    {
        $this->loginAs('render');

        [$status, $body] = $this->call('POST', '/api/appointments', [
            'patient_id' => $this->patientId, 'psychologist_id' => $this->psychologistId,
            'date' => date('Y-m-d', strtotime('+4 days')), 'time' => '09:00', 'duration' => 50,
            'modality' => 'online', 'meeting_url' => 'javascript:alert(1)',
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('meeting_url', $body['error']['fields']);
    }

    public function testOnlyTheAuthorCanSignANoteAndOnlyOnce(): void
    {
        $noteId = $this->createNote($this->patientId, $this->psychologistId);
        $this->createUser('psychologist', 'colleague');

        $this->loginAs('colleague');
        [$foreign] = $this->call('POST', '/api/notes/' . $noteId . '/sign');

        $this->loginAs('render');
        [$own] = $this->call('POST', '/api/notes/' . $noteId . '/sign');
        [$again] = $this->call('POST', '/api/notes/' . $noteId . '/sign');
        [$edit] = $this->call('PUT', '/api/notes/' . $noteId, [
            'patient_id' => $this->patientId, 'session_date' => date('Y-m-d'), 'format' => 'soap',
        ]);

        $this->assertSame(403, $foreign);
        $this->assertSame(200, $own);
        $this->assertSame(409, $again);
        $this->assertSame(409, $edit, 'A signed note cannot be changed');
    }

    public function testAPatientCannotOpenAnotherPatientsConsent(): void
    {
        $otherPatient = $this->createPatient(['first_name' => 'Other', 'last_name' => 'Person']);
        $foreignConsent = $this->createConsent($otherPatient);
        $this->createPortalUser($this->patientId, 'mr-own');
        $this->loginAs('mr-own');

        [$view] = $this->call('GET', '/api/consents/' . $foreignConsent);
        [$sign] = $this->call('POST', '/api/consents/' . $foreignConsent . '/sign', [
            'signed_name' => 'Intruder', 'strokes' => [[[1, 1], [20, 20]]],
        ]);

        $this->assertSame(404, $view);
        $this->assertSame(404, $sign);
    }

    public function testSignaturesAreRebuiltOnTheServer(): void
    {
        $consentId = $this->createConsent($this->patientId);
        $this->createPortalUser($this->patientId, 'mr-signature');
        $this->loginAs('mr-signature');

        [$status] = $this->call('POST', '/api/consents/' . $consentId . '/sign', [
            'signed_name' => 'Mariana Vega',
            'signature_svg' => '<svg><script>alert(1)</script></svg>',
            'strokes' => [[[10, 10], [50, 40], [9999, -5]]],
        ]);
        [$again] = $this->call('POST', '/api/consents/' . $consentId . '/sign', [
            'signed_name' => 'Mariana Vega', 'strokes' => [[[1, 1], [2, 2]]],
        ]);

        $stored = (string) Database::value('SELECT signature_svg FROM consents WHERE id = :id', ['id' => $consentId]);

        $this->assertSame(200, $status);
        $this->assertSame(409, $again, 'It cannot be signed twice');
        $this->assertFalse(str_contains($stored, 'script'));
        $this->assertContains('M10 10 L50 40 L600 0', $stored);
    }

    public function testStoredSignaturesAreSanitizedOnTheWayOut(): void
    {
        $consentId = $this->createConsent($this->patientId);
        Database::update('consents', $consentId, [
            'status' => 'signed',
            'signature_svg' => '<svg onload="alert(1)"><path d="M1 1 L2 2"/><script>x</script></svg>',
        ]);
        $this->loginAs('render');

        [, $body] = $this->call('GET', '/api/consents/' . $consentId);

        $this->assertFalse(str_contains($body['data']['signature_svg'], 'onload'));
        $this->assertFalse(str_contains($body['data']['signature_svg'], 'script'));
        $this->assertContains('M1 1 L2 2', $body['data']['signature_svg']);
    }

    public function testAQuestionnaireCanOnlyBeAnsweredOnceAndWithValidValues(): void
    {
        $assessmentId = Database::insert('assessments', [
            'uuid' => uuid(), 'patient_id' => $this->patientId, 'instrument_code' => 'GAD-7',
            'assigned_by' => $this->psychologistId, 'status' => 'pending',
        ]);
        $this->createPortalUser($this->patientId, 'mr-questionnaire');
        $this->loginAs('mr-questionnaire');

        [$invalid] = $this->call('POST', '/api/portal/questionnaires/' . $assessmentId, ['answers' => [9, 9, 9, 9, 9, 9, 9]]);
        [$valid] = $this->call('POST', '/api/portal/questionnaires/' . $assessmentId, ['answers' => [1, 1, 1, 1, 1, 1, 1]]);
        [$again] = $this->call('POST', '/api/portal/questionnaires/' . $assessmentId, ['answers' => [0, 0, 0, 0, 0, 0, 0]]);

        $this->assertSame(422, $invalid);
        $this->assertSame(200, $valid);
        $this->assertSame(409, $again);
        $this->assertSame(7, (int) Database::value('SELECT total_score FROM assessments WHERE id = :id', ['id' => $assessmentId]));
    }

    public function testThePublicSiteOnlyListsProfessionalsWhoOptedIn(): void
    {
        Database::update('users', $this->psychologistId, ['show_on_site' => 1, 'specialty' => 'Couples therapy']);
        $this->createUser('psychologist', 'private');

        [$status, $body] = $this->call('GET', '/api/public/site');

        $this->assertSame(200, $status);
        $this->assertCount(1, $body['data']['professionals']);
        $this->assertFalse(array_key_exists('email', $body['data']['professionals'][0]));
        $this->assertFalse(array_key_exists('default_fee', $body['data']['clinic']), 'The fee is not public data');
    }

    public function testAppointmentRequestsAreValidatedAndRateLimited(): void
    {
        $valid = [
            'full_name' => 'Sofia Ruiz', 'email' => 'sofia@example.com', 'contact_preference' => 'email',
            'modality' => 'online', 'attendee' => 'self', 'privacy_accepted' => true,
            'preferred_times' => ['morning', 'made-up'],
        ];

        [$missing, $errors] = $this->call('POST', '/api/public/appointment-requests', ['privacy_accepted' => false] + $valid);
        [$first] = $this->call('POST', '/api/public/appointment-requests', $valid);
        $this->call('POST', '/api/public/appointment-requests', $valid);
        $this->call('POST', '/api/public/appointment-requests', $valid);
        [$limited] = $this->call('POST', '/api/public/appointment-requests', $valid);

        $this->assertSame(422, $missing);
        $this->assertArrayHasKey('privacy_accepted', $errors['error']['fields']);
        $this->assertSame(201, $first);
        $this->assertSame(429, $limited);
        $this->assertSame('morning', (string) Database::value('SELECT preferred_times FROM appointment_requests ORDER BY id LIMIT 1'));
    }

    public function testTheHoneypotSilentlyDropsBots(): void
    {
        [$status] = $this->call('POST', '/api/public/appointment-requests', ['website' => 'http://spam.example']);

        $this->assertSame(201, $status);
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM appointment_requests'));
    }

    public function testUserListingsNeverExposePasswordHashes(): void
    {
        $this->loginAs('render');

        [, $body] = $this->call('GET', '/api/users');

        foreach ($body['data'] as $user) {
            $this->assertFalse(array_key_exists('password_hash', $user));
        }
    }

    public function testAnAdminCannotDeactivateTheirOwnAccount(): void
    {
        $adminId = $this->createUser('admin', 'boss');
        $this->loginAs('boss');

        [$status] = $this->call('POST', '/api/users/' . $adminId . '/toggle');

        $this->assertSame(409, $status);
    }

    public function testSearchLinksPointToTheNewInterface(): void
    {
        $this->loginAs('render');

        [, $body] = $this->call('GET', '/api/search', [], ['q' => 'Mariana']);

        $this->assertSame('/app/patients/' . $this->patientId, $body['results'][0]['url']);
    }

    public function testEveryInstrumentCanBeDescribed(): void
    {
        $this->loginAs('render');

        foreach (Instruments::codes() as $code) {
            [$status, $body] = $this->call('GET', '/api/instruments/' . $code);
            $this->assertSame(200, $status, 'Instrument failed: ' . $code);
            $this->assertGreaterThan(0, count($body['data']['items']));
        }
    }

    public function testUnknownRoutesAnswerWithJson(): void
    {
        [$status, $body] = $this->call('GET', '/api/does-not-exist');

        $this->assertSame(404, $status);
        $this->assertArrayHasKey('message', $body['error']);
    }

    /** @return array{0:int,1:array} */
    private function call(string $method, string $uri, array $body = [], array $query = [], bool $withToken = true): array
    {
        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri . ($query === [] ? '' : '?' . http_build_query($query)),
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        if ($withToken) {
            $server['HTTP_X_CSRF_TOKEN'] = Csrf::token();
        }

        /** @var Router $router */
        $router = require dirname(__DIR__, 2) . '/src/routes.php';

        ob_start();
        try {
            Response::$lastStatus = 200;
            $router->dispatch(new Request($query, $body, [], $server));
            $status = Response::$lastStatus;
        } catch (HttpException $exception) {
            Response::json($exception->toArray(), $exception->status());
            $status = $exception->status();
        } finally {
            $output = (string) ob_get_clean();
        }

        return [$status, json_decode($output, true) ?? []];
    }

    private function loginAs(string $username): void
    {
        Auth::logout();
        $_SESSION = [];
        $this->assertTrue(Auth::attempt($username, 'Password1234'), 'Could not sign in as ' . $username);
    }

    private function createPortalUser(int $patientId, string $username): int
    {
        $id = $this->createUser('patient', $username);
        Database::update('users', $id, ['patient_id' => $patientId]);

        return $id;
    }

    private function createConsent(int $patientId): int
    {
        return Database::insert('consents', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'template_code' => 'general',
            'title' => 'Test consent',
            'body' => 'Text',
            'created_by' => $this->psychologistId,
        ]);
    }
}
