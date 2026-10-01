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
use PsiClinic\Domain\ConsentTemplates;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Settings;

/** Consents and assessment reports in English or Spanish, through the real API. */
final class DocumentLanguageTest extends FeatureTestCase
{
    private int $patientId = 0;

    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        Auth::logout();

        $this->createUser('admin', 'boss');
        $this->createUser('psychologist', 'laura');
        $this->patientId = $this->createPatient(['first_name' => 'Mariana', 'last_name' => 'Vega']);
    }

    public function testConsentsTableStoresTheLanguage(): void
    {
        $column = Database::first(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = "consents" AND column_name = "language"'
        );

        $this->assertNotNull($column);
    }

    public function testAConsentCanBeCreatedInSpanish(): void
    {
        $this->loginAs('laura');

        [$status, $body] = $this->call('POST', '/api/consents', ['patient_id' => $this->patientId, 'template_code' => 'telehealth', 'language' => 'es']);

        $this->assertSame(201, $status);
        $row = Database::first('SELECT language, title, body FROM consents WHERE id = :id', ['id' => $body['data']['id']]);
        $this->assertSame('es', $row['language']);
        $this->assertSame(ConsentTemplates::get('telehealth', 'es')['title'], $row['title']);
        $this->assertContains('Ley 1419 de 2010', $row['body']);

        [$status, $body] = $this->call('GET', '/api/consents/' . $body['data']['id']);
        $this->assertSame(200, $status);
        $this->assertSame('es', $body['data']['language']);
    }

    public function testWithoutALanguageThePracticeDefaultApplies(): void
    {
        $this->loginAs('laura');

        [, $english] = $this->call('POST', '/api/consents', ['patient_id' => $this->patientId, 'template_code' => 'general']);
        Settings::put('document_language', 'es');
        [, $spanish] = $this->call('POST', '/api/consents', ['patient_id' => $this->patientId, 'template_code' => 'general']);

        $this->assertSame('en', Database::value('SELECT language FROM consents WHERE id = :id', ['id' => $english['data']['id']]));
        $this->assertSame('es', Database::value('SELECT language FROM consents WHERE id = :id', ['id' => $spanish['data']['id']]));
    }

    public function testUnknownLanguagesAreRejected(): void
    {
        $this->loginAs('laura');

        [$status, $body] = $this->call('POST', '/api/consents', ['patient_id' => $this->patientId, 'template_code' => 'general', 'language' => 'fr']);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('language', $body['error']['fields']);
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM consents'));
    }

    public function testTheConsentListShowsEachLanguage(): void
    {
        $this->loginAs('laura');
        $this->call('POST', '/api/consents', ['patient_id' => $this->patientId, 'template_code' => 'minors', 'language' => 'es']);

        [, $body] = $this->call('GET', '/api/consents');

        $this->assertSame('es', $body['data'][0]['language']);
    }

    public function testTheAssessmentReportCanBeReadInSpanish(): void
    {
        $id = $this->completedPhq9([2, 2, 2, 2, 2, 2, 1, 1, 1]);
        $this->loginAs('laura');

        [$status, $english] = $this->call('GET', '/api/assessments/' . $id);
        [, $spanish] = $this->call('GET', '/api/assessments/' . $id, [], ['language' => 'es']);

        $this->assertSame(200, $status);
        $this->assertSame('en', $english['data']['language']);
        $this->assertSame('Moderately severe', $english['data']['assessment']['severity']);
        $this->assertSame('es', $spanish['data']['language']);
        $this->assertSame('Moderadamente severa', $spanish['data']['assessment']['severity']);
        $this->assertSame('Cuestionario de Salud del Paciente', $spanish['data']['instrument']['name']);
        $this->assertSame($english['data']['assessment']['total_score'], $spanish['data']['assessment']['total_score']);
        $this->assertCount(1, $spanish['data']['alerts']);
    }

    public function testTheReportFollowsTheDefaultDocumentLanguage(): void
    {
        $id = $this->completedPhq9(array_fill(0, 9, 0));
        Settings::put('document_language', 'es');
        $this->loginAs('laura');

        [, $body] = $this->call('GET', '/api/assessments/' . $id, [], ['language' => 'xx']);

        $this->assertSame('es', $body['data']['language']);
        $this->assertSame('Mínima', $body['data']['assessment']['severity']);
    }

    public function testTheDefaultDocumentLanguageIsASetting(): void
    {
        $this->loginAs('boss');

        [$status, $body] = $this->call('PUT', '/api/settings', ['clinic_name' => 'PsiClinic', 'document_language' => 'de']);
        $this->assertSame(422, $status);
        $this->assertArrayHasKey('document_language', $body['error']['fields']);

        [$status] = $this->call('PUT', '/api/settings', ['clinic_name' => 'PsiClinic', 'document_language' => 'es']);
        $this->assertSame(200, $status);

        [, $body] = $this->call('GET', '/api/meta');
        $this->assertSame('es', $body['data']['settings']['document_language']);
        $this->assertSame(['en', 'es'], array_column($body['data']['documentLanguages'], 'value'));
    }

    private function completedPhq9(array $answers): int
    {
        $result = Instruments::score('PHQ-9', $answers);

        return Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => $this->patientId,
            'instrument_code' => 'PHQ-9',
            'status' => 'completed',
            'answers' => json_encode($answers),
            'total_score' => $result['total'],
            'severity' => $result['severity'],
            'interpretation' => $result['interpretation'],
            'administered_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function call(string $method, string $uri, array $body = [], array $query = []): array
    {
        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri . ($query === [] ? '' : '?' . http_build_query($query)),
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_CSRF_TOKEN' => Csrf::token(),
        ];

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
}
