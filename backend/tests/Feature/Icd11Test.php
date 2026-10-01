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
use PsiClinic\Domain\Icd11;

/** ICD-11 catalog, its search and diagnoses coded with it (dual ICD-10 coding). */
final class Icd11Test extends FeatureTestCase
{
    private int $patientId = 0;

    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        Auth::logout();

        $this->createUser('psychologist', 'coder');
        $this->patientId = $this->createPatient(['first_name' => 'Mariana', 'last_name' => 'Vega']);
    }

    public function testTheCatalogIsLoadedWithEnglishTitlesAndIcd10Equivalents(): void
    {
        $this->assertTrue(Icd11::isLoaded());
        $this->assertGreaterThan(30000, (int) Database::value('SELECT COUNT(*) FROM icd11_codes'));

        $entry = Icd11::find('6B00');
        $this->assertNotNull($entry);
        $this->assertContains('anxiety disorder', strtolower($entry['title']));
        $this->assertSame('F41.1', $entry['icd10_code']);
        $this->assertSame('06', $entry['chapter']);
        $this->assertTrue($entry['is_leaf']);
    }

    public function testFindIgnoresCaseAndSurroundingSpaces(): void
    {
        $this->assertSame('6A70.1', Icd11::find(' 6a70.1 ')['code']);
        $this->assertNull(Icd11::find('NOPE'));
    }

    public function testSearchFindsByCodeFirst(): void
    {
        $results = Icd11::search('6B00');

        $this->assertSame('6B00', $results[0]['code']);
    }

    public function testSearchFindsByWordsInEnglishOrSpanish(): void
    {
        $english = array_column(Icd11::search('generalised anxiety'), 'code');
        $spanish = array_column(Icd11::search('ansiedad generalizada'), 'code');

        $this->assertTrue(in_array('6B00', $english, true), 'English words should find 6B00');
        $this->assertTrue(in_array('6B00', $spanish, true), 'Spanish words should also find 6B00');
    }

    public function testMentalHealthCodesComeFirst(): void
    {
        $results = Icd11::search('depressive');

        $this->assertSame(Icd11::MENTAL_HEALTH_CHAPTER, $results[0]['chapter']);
    }

    public function testSearchNeedsTwoCharactersAndCapsTheLimit(): void
    {
        $this->assertCount(0, Icd11::search('a'));
        $this->assertCount(50, Icd11::search('disorder', 500));
    }

    public function testSearchTreatsWildcardsAsText(): void
    {
        $this->assertCount(0, Icd11::search('%%'));
    }

    public function testTheApiSearchesForStaffOnly(): void
    {
        $this->loginAs('coder');
        [$status, $body] = $this->call('GET', '/api/icd11', [], ['q' => '6B00']);

        $this->assertSame(200, $status);
        $this->assertSame(Icd11::RELEASE, $body['data']['release']);
        $this->assertSame('6B00', $body['data']['results'][0]['code']);

        $this->createPortalUser($this->patientId, 'mr-portal');
        $this->loginAs('mr-portal');
        [$status] = $this->call('GET', '/api/icd11', [], ['q' => '6B00']);

        $this->assertSame(403, $status);
    }

    public function testAnIcd11DiagnosisTakesTheCatalogTitleAndIcd10Equivalent(): void
    {
        $this->loginAs('coder');

        [$status, $body] = $this->call('POST', '/api/patients/' . $this->patientId . '/diagnoses', [
            'system' => 'icd11', 'code' => '6b00', 'title' => 'anything typed', 'status' => 'active',
        ]);

        $this->assertSame(201, $status);
        $row = Database::first('SELECT * FROM diagnoses WHERE id = :id', ['id' => $body['data']['id']]);
        $this->assertSame('6B00', $row['code']);
        $this->assertSame(Icd11::find('6B00')['title'], $row['title']);
        $this->assertSame('F41.1', $row['icd10_code']);
        $this->assertSame(1, (int) $row['is_primary'], 'The first diagnosis becomes the primary one');
    }

    public function testUnknownIcd11CodesAreRejected(): void
    {
        $this->loginAs('coder');

        [$status, $body] = $this->call('POST', '/api/patients/' . $this->patientId . '/diagnoses', ['system' => 'icd11', 'code' => 'ZZ99']);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('code', $body['error']['fields']);
    }

    public function testTheIcd10EquivalentIsValidated(): void
    {
        $this->loginAs('coder');

        [$status, $body] = $this->call('POST', '/api/patients/' . $this->patientId . '/diagnoses', [
            'system' => 'icd11', 'code' => '6B00', 'icd10_code' => 'anxiety',
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('icd10_code', $body['error']['fields']);
    }

    public function testIcd10AndDsm5DiagnosesNeedADescription(): void
    {
        $this->loginAs('coder');

        [$status] = $this->call('POST', '/api/patients/' . $this->patientId . '/diagnoses', ['system' => 'dsm5', 'code' => 'F41.1']);
        $this->assertSame(422, $status);

        [$status, $body] = $this->call('POST', '/api/patients/' . $this->patientId . '/diagnoses', [
            'system' => 'icd10', 'code' => 'f41.1', 'title' => 'Generalized anxiety disorder',
        ]);
        $this->assertSame(201, $status);
        $this->assertSame('F41.1', Database::value('SELECT icd10_code FROM diagnoses WHERE id = :id', ['id' => $body['data']['id']]));
    }

    public function testOnlyOneDiagnosisIsPrimary(): void
    {
        $this->loginAs('coder');
        $first = $this->addDiagnosis('6B00');
        $second = $this->addDiagnosis('6A70.1', ['is_primary' => true]);

        $this->assertSame(0, (int) Database::value('SELECT is_primary FROM diagnoses WHERE id = :id', ['id' => $first]));
        $this->assertSame(1, (int) Database::value('SELECT is_primary FROM diagnoses WHERE id = :id', ['id' => $second]));

        [$status] = $this->call('PATCH', '/api/patients/' . $this->patientId . '/diagnoses/' . $first, ['is_primary' => true]);

        $this->assertSame(200, $status);
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM diagnoses WHERE patient_id = :id AND is_primary = 1', ['id' => $this->patientId]));
        $this->assertSame(1, (int) Database::value('SELECT is_primary FROM diagnoses WHERE id = :id', ['id' => $first]));
    }

    public function testTheIcd10EquivalentCanBeCorrected(): void
    {
        $this->loginAs('coder');
        $id = $this->addDiagnosis('6A70.Z');

        [$status] = $this->call('PATCH', '/api/patients/' . $this->patientId . '/diagnoses/' . $id, ['icd10_code' => 'f32.9', 'status' => 'remission']);

        $this->assertSame(200, $status);
        $row = Database::first('SELECT icd10_code, status FROM diagnoses WHERE id = :id', ['id' => $id]);
        $this->assertSame('F32.9', $row['icd10_code']);
        $this->assertSame('remission', $row['status']);
    }

    public function testDeletingThePrimaryDiagnosisPromotesAnother(): void
    {
        $this->loginAs('coder');
        $primary = $this->addDiagnosis('6B00');
        $other = $this->addDiagnosis('6A70.1');

        [$status] = $this->call('DELETE', '/api/patients/' . $this->patientId . '/diagnoses/' . $primary);

        $this->assertSame(200, $status);
        $this->assertSame(1, (int) Database::value('SELECT is_primary FROM diagnoses WHERE id = :id', ['id' => $other]));
    }

    public function testTheProfileShowsWhatRipsWillReceive(): void
    {
        $this->loginAs('coder');
        $this->addDiagnosis('6B00');
        $this->addDiagnosis('6A70.Z');

        [, $body] = $this->call('GET', '/api/patients/' . $this->patientId);
        $codes = array_column($body['data']['diagnoses'], 'rips_code', 'code');

        $this->assertSame('F411', $codes['6B00']);
        $this->assertNull($codes['6A70.Z'], 'F32 is a category: RIPS needs the subcategory');
        $this->assertTrue($body['data']['diagnoses'][0]['is_primary']);
    }

    private function addDiagnosis(string $code, array $extra = []): int
    {
        [$status, $body] = $this->call('POST', '/api/patients/' . $this->patientId . '/diagnoses', ['system' => 'icd11', 'code' => $code] + $extra);
        $this->assertSame(201, $status, 'Could not add diagnosis ' . $code);

        return (int) $body['data']['id'];
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

    private function createPortalUser(int $patientId, string $username): int
    {
        $id = $this->createUser('patient', $username);
        Database::update('users', $id, ['patient_id' => $patientId]);

        return $id;
    }
}
