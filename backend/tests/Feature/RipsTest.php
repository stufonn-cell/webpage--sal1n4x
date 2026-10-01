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
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;
use PsiClinic\Core\Router;
use PsiClinic\Domain\Rips;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\MuvClient;

/**
 * RIPS without invoice (Resolution 2275 of 2023): JSON structure, missing
 * data, one report per appointment, numbering, permissions and submission to
 * the Ministry validator through a fake transport.
 */
final class RipsTest extends FeatureTestCase
{
    private const FROM = '2026-08-01';
    private const TO = '2026-08-31';

    private const USER_KEYS = [
        'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'tipoUsuario', 'fechaNacimiento',
        'codSexo', 'codPaisResidencia', 'codMunicipioResidencia', 'codZonaTerritorialResidencia',
        'incapacidad', 'consecutivo', 'codPaisOrigen', 'servicios',
    ];

    private const CONSULTATION_KEYS = [
        'codPrestador', 'fechaInicioAtencion', 'numAutorizacion', 'codConsulta', 'modalidadGrupoServicioTecSal',
        'grupoServicios', 'codServicio', 'finalidadTecnologiaSalud', 'causaMotivoAtencion', 'codDiagnosticoPrincipal',
        'codDiagnosticoRelacionado1', 'codDiagnosticoRelacionado2', 'codDiagnosticoRelacionado3', 'tipoDiagnosticoPrincipal',
        'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'vrServicio', 'conceptoRecaudo', 'valorPagoModerador',
        'numFEVPagoModerador', 'consecutivo',
    ];

    private int $psychologistId = 0;
    private int $patientId = 0;

    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        Auth::logout();
        RipsController::$clientFactory = null;

        $adminId = $this->createUser('admin', 'boss');
        Database::update('users', $adminId, ['document_type' => 'CC', 'document_number' => '1020304050']);
        $this->psychologistId = $this->createUser('psychologist', 'laura');
        Database::update('users', $this->psychologistId, ['document_type' => 'CC', 'document_number' => '1030405060']);

        $this->patientId = $this->createReadyPatient('Mariana', 'Vega', '1012345678', 'F');
        $this->addDiagnosis($this->patientId, 'F41.1');
        $this->configure();
    }

    public function tearDown(): void
    {
        RipsController::$clientFactory = null;
        parent::tearDown();
    }

    public function testIcd10CodesAreNormalizedAndCategoriesRejected(): void
    {
        $this->assertSame('F411', Rips::icd10ForRips('F41.1'));
        $this->assertSame('F321', Rips::icd10ForRips(' f32.1 '));
        $this->assertSame('Z630', Rips::icd10ForRips('Z63.0'));
        $this->assertSame('F03X', Rips::icd10ForRips('F03X'));
        $this->assertNull(Rips::icd10ForRips('F32'), 'A 3-character category needs its subcategory');
        $this->assertNull(Rips::icd10ForRips('6B00'));
        $this->assertNull(Rips::icd10ForRips(null));
    }

    public function testConfigIssuesListWhatIsMissing(): void
    {
        $this->assertCount(0, Rips::configIssues(Settings::all()));
        $this->assertCount(3, Rips::configIssues(['rips_reporter_id' => '', 'rips_provider_code' => '12', 'rips_service_code' => 'x']));
    }

    public function testTheReportFollowsTheMinistryStructure(): void
    {
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-10 09:00:00', ['status' => 'completed', 'modality' => 'online']);

        $result = Rips::build(self::FROM, self::TO, '7');
        $rips = $result['rips'];

        $this->assertSame(['numDocumentoIdObligado', 'numFactura', 'tipoNota', 'numNota', 'usuarios'], array_keys($rips));
        $this->assertSame('900123456', $rips['numDocumentoIdObligado']);
        $this->assertNull($rips['numFactura']);
        $this->assertSame('RS', $rips['tipoNota']);
        $this->assertSame('7', $rips['numNota']);
        $this->assertCount(1, $rips['usuarios']);

        $user = $rips['usuarios'][0];
        $this->assertSame(self::USER_KEYS, array_keys($user));
        $this->assertSame('CC', $user['tipoDocumentoIdentificacion']);
        $this->assertSame('1012345678', $user['numDocumentoIdentificacion']);
        $this->assertSame('12', $user['tipoUsuario']);
        $this->assertSame('1990-01-15', $user['fechaNacimiento']);
        $this->assertSame('F', $user['codSexo']);
        $this->assertSame('170', $user['codPaisResidencia']);
        $this->assertSame('11001', $user['codMunicipioResidencia']);
        $this->assertSame('01', $user['codZonaTerritorialResidencia']);
        $this->assertSame('NO', $user['incapacidad']);
        $this->assertSame(1, $user['consecutivo']);

        [$first, $second] = $user['servicios']['consultas'];
        $this->assertSame(self::CONSULTATION_KEYS, array_keys($first));
        $this->assertSame('110010000001', $first['codPrestador']);
        $this->assertSame('2026-08-03 09:00', $first['fechaInicioAtencion']);
        $this->assertSame(Rips::CUPS_FIRST, $first['codConsulta']);
        $this->assertSame(Rips::CUPS_FOLLOW_UP, $second['codConsulta']);
        $this->assertSame('01', $first['modalidadGrupoServicioTecSal']);
        $this->assertSame('06', $second['modalidadGrupoServicioTecSal'], 'Online sessions are interactive telemedicine');
        $this->assertSame('01', $first['grupoServicios']);
        $this->assertSame(344, $first['codServicio']);
        $this->assertSame('15', $first['finalidadTecnologiaSalud']);
        $this->assertSame('16', $second['finalidadTecnologiaSalud']);
        $this->assertSame('38', $first['causaMotivoAtencion']);
        $this->assertSame('F411', $first['codDiagnosticoPrincipal']);
        $this->assertSame('02', $first['tipoDiagnosticoPrincipal'], 'Confirmed new at the first consultation');
        $this->assertSame('03', $second['tipoDiagnosticoPrincipal'], 'Confirmed repeated afterwards');
        $this->assertSame('1030405060', $first['numDocumentoIdentificacion'], 'The professional who attended');
        $this->assertSame(120000, $first['vrServicio']);
        $this->assertSame('05', $first['conceptoRecaudo']);
        $this->assertSame(1, $first['consecutivo']);
        $this->assertSame(2, $second['consecutivo']);
    }

    public function testRelatedDiagnosesFollowThePrimaryOne(): void
    {
        $this->addDiagnosis($this->patientId, 'Z63.0', false);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);

        $consultation = Rips::build(self::FROM, self::TO, '1')['rips']['usuarios'][0]['servicios']['consultas'][0];

        $this->assertSame('F411', $consultation['codDiagnosticoPrincipal']);
        $this->assertSame('Z630', $consultation['codDiagnosticoRelacionado1']);
        $this->assertNull($consultation['codDiagnosticoRelacionado2']);
    }

    public function testEachPatientIsNumberedOnce(): void
    {
        $other = $this->createReadyPatient('Daniel', 'Ortiz', '1098765432', 'M');
        $this->addDiagnosis($other, 'F32.1');
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);
        $this->createAppointment($other, $this->psychologistId, '2026-08-04 09:00:00', ['status' => 'completed']);
        $this->createAppointment($other, $this->psychologistId, '2026-08-11 09:00:00', ['status' => 'completed']);

        $usuarios = Rips::build(self::FROM, self::TO, '1')['rips']['usuarios'];

        $this->assertSame([1, 2], array_column($usuarios, 'consecutivo'));
        $this->assertSame(['M', 'F'], array_column($usuarios, 'codSexo'), 'Ordered by last name: Ortiz, Vega');
        $this->assertCount(2, $usuarios[0]['servicios']['consultas']);
    }

    public function testOnlyCompletedAppointmentsInsideThePeriodAreIncluded(): void
    {
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-31 18:00:00', ['status' => 'completed']);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-05 09:00:00', ['status' => 'cancelled']);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-06 09:00:00', ['status' => 'no_show']);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-07-31 09:00:00', ['status' => 'completed']);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-09-01 09:00:00', ['status' => 'completed']);

        $result = Rips::build(self::FROM, self::TO);

        $this->assertCount(2, $result['items']);
        $this->assertSame(2, $result['readyCount']);
    }

    public function testMissingDataIsExplainedAndLeftOut(): void
    {
        $incomplete = $this->createPatient(['first_name' => 'Sofia', 'last_name' => 'Cardenas', 'document_type' => 'CC', 'document_id' => '12AB']);
        $this->createAppointment($incomplete, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-04 09:00:00', ['status' => 'completed']);

        $result = Rips::build(self::FROM, self::TO, '1');
        $issues = $result['items'][0]['issues'];
        $text = implode(' | ', $issues);

        $this->assertSame('Sofia Cardenas', $result['items'][0]['patient_name']);
        $this->assertContains('only contain digits', $text);
        $this->assertContains('sex stated', $text);
        $this->assertContains('municipality', $text);
        $this->assertContains('no active diagnosis', $text);
        $this->assertSame(1, $result['readyCount']);
        $this->assertCount(1, $result['rips']['usuarios'], 'Incomplete consultations never reach the JSON');
    }

    public function testAThreeCharacterIcd10CodeAsksForTheSubcategory(): void
    {
        Database::run('UPDATE diagnoses SET code = "F41", icd10_code = "F41" WHERE patient_id = :id', ['id' => $this->patientId]);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);

        $issues = Rips::build(self::FROM, self::TO)['items'][0]['issues'];

        $this->assertCount(1, $issues);
        $this->assertContains('(currently F41)', $issues[0]);
    }

    public function testAProfessionalWithoutDocumentBlocksTheirConsultations(): void
    {
        Database::update('users', $this->psychologistId, ['document_number' => null]);
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);

        $issues = Rips::build(self::FROM, self::TO)['items'][0]['issues'];

        $this->assertContains('professional', implode(' ', $issues));
    }

    public function testOnlyAdministratorsCanUseRips(): void
    {
        $this->loginAs('laura');

        [$status] = $this->call('GET', '/api/rips');
        $this->assertSame(403, $status);

        [$status] = $this->call('POST', '/api/rips/preview', ['from' => self::FROM, 'to' => self::TO]);
        $this->assertSame(403, $status);
    }

    public function testThePreviewStoresNothing(): void
    {
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);
        $this->loginAs('boss');

        [$status, $body] = $this->call('POST', '/api/rips/preview', ['from' => self::FROM, 'to' => self::TO]);

        $this->assertSame(200, $status);
        $this->assertSame(1, $body['data']['readyCount']);
        $this->assertFalse(array_key_exists('rips', $body['data']));
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM rips_reports'));
    }

    public function testThePeriodIsValidated(): void
    {
        $this->loginAs('boss');

        [$status, $body] = $this->call('POST', '/api/rips/preview', ['from' => self::TO, 'to' => self::FROM]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('from', $body['error']['fields']);
    }

    public function testGeneratingNeedsThePracticeSettings(): void
    {
        Settings::put('rips_provider_code', '');
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);
        $this->loginAs('boss');

        [$status, $body] = $this->call('POST', '/api/rips', ['from' => self::FROM, 'to' => self::TO]);

        $this->assertSame(422, $status);
        $this->assertContains('REPS', $body['error']['message']);
    }

    public function testEachAppointmentIsReportedOnceAndNumbersAreConsecutive(): void
    {
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);
        $this->loginAs('boss');

        [$status, $body] = $this->call('POST', '/api/rips', ['from' => self::FROM, 'to' => self::TO]);
        $this->assertSame(201, $status);
        $firstId = (int) $body['data']['id'];

        [$status] = $this->call('POST', '/api/rips', ['from' => self::FROM, 'to' => self::TO]);
        $this->assertSame(422, $status, 'The same consultation cannot be reported twice');

        $this->createAppointment($this->patientId, $this->psychologistId, '2026-09-02 09:00:00', ['status' => 'completed']);
        [$status, $body] = $this->call('POST', '/api/rips', ['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertSame(201, $status);

        $numbers = Database::all('SELECT id, note_number FROM rips_reports ORDER BY id');
        $this->assertSame(['1', '2'], array_column($numbers, 'note_number'));
        $this->assertSame(2, (int) Database::value('SELECT COUNT(*) FROM rips_report_items'));
        $this->assertSame('1', Database::value('SELECT note_number FROM rips_reports WHERE id = :id', ['id' => $firstId]));
    }

    public function testTheFirstNoteNumberSettingIsRespected(): void
    {
        Settings::put('rips_first_note_number', '50');

        $this->assertSame('50', Rips::nextNoteNumber());
    }

    public function testTheListAndDetailDescribeTheReport(): void
    {
        $id = $this->generate();

        [$status, $body] = $this->call('GET', '/api/rips');
        $this->assertSame(200, $status);
        $this->assertSame('1', $body['data']['reports'][0]['note_number']);
        $this->assertSame('generated', $body['data']['reports'][0]['status']);
        $this->assertSame('2', $body['data']['nextNoteNumber']);
        $this->assertCount(0, $body['data']['configIssues']);

        [$status, $body] = $this->call('GET', '/api/rips/' . $id);
        $this->assertSame(200, $status);
        $this->assertSame('RS', $body['data']['payload']['tipoNota']);
        $this->assertNull($body['data']['validation_result']);
        $this->assertFalse($body['data']['validatorConfigured']);
        $this->assertSame(Database::value('SELECT full_name FROM users WHERE username = "boss"'), $body['data']['created_by_name']);
    }

    public function testTheDownloadIsTheStoredJson(): void
    {
        $id = $this->generate();

        $output = $this->raw('GET', '/api/rips/' . $id . '/download');
        $json = json_decode($output, true);

        $this->assertSame('RS', $json['tipoNota']);
        $this->assertSame('1', $json['numNota']);
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM audit_log WHERE action = "download" AND entity = "rips_report"'));
    }

    public function testSendingNeedsAValidatorAddress(): void
    {
        $id = $this->generate();

        [$status, $body] = $this->call('POST', '/api/rips/' . $id . '/send', ['document_type' => 'CC', 'document_number' => '1020304050', 'password' => 'secret']);

        $this->assertSame(422, $status);
        $this->assertContains('validator', $body['error']['message']);
    }

    public function testAnAcceptedReportStoresTheCuvAndCannotBeDeleted(): void
    {
        $id = $this->generate();
        Settings::put('rips_validator_url', 'https://localhost:9443');
        $calls = [];
        $this->fakeValidator($calls, [
            'ResultState' => true,
            'ProcesoId' => 991,
            'CodigoUnicoValidacion' => 'cuv-abc-123',
            'FechaRadicacion' => '2026-09-01T10:00:00',
            'ResultadosValidacion' => [['Clase' => 'NOTIFICACION', 'Codigo' => 'RVG18', 'Descripcion' => 'Aviso', 'Observaciones' => '', 'PathFuente' => '']],
        ]);

        [$status, $body] = $this->call('POST', '/api/rips/' . $id . '/send', ['document_type' => 'CC', 'document_number' => '1020304050', 'password' => 'S3cret!']);

        $this->assertSame(200, $status);
        $this->assertTrue($body['data']['accepted']);
        $this->assertSame('/api/Auth/LoginSISPRO', $calls[0]['path']);
        $this->assertSame('900123456', $calls[0]['payload']['nit']);
        $this->assertSame('/api/PaquetesFevRips/CargarRipsSinFactura', $calls[1]['path']);
        $this->assertSame('token-xyz', $calls[1]['token']);
        $this->assertSame('RS', $calls[1]['payload']['rips']['tipoNota']);

        $row = Database::first('SELECT * FROM rips_reports WHERE id = :id', ['id' => $id]);
        $this->assertSame('validated', $row['status']);
        $this->assertSame('cuv-abc-123', $row['cuv']);
        $this->assertFalse(str_contains((string) $row['validation_result'], 'S3cret!'), 'The SISPRO password is never stored');

        [$status] = $this->call('DELETE', '/api/rips/' . $id);
        $this->assertSame(409, $status);

        [$status] = $this->call('POST', '/api/rips/' . $id . '/send', ['document_type' => 'CC', 'document_number' => '1020304050', 'password' => 'again']);
        $this->assertSame(409, $status);
    }

    public function testARejectedReportCanBeDeletedAndGeneratedAgain(): void
    {
        $id = $this->generate();
        Settings::put('rips_validator_url', 'https://localhost:9443');
        $calls = [];
        $this->fakeValidator($calls, [
            'ResultState' => false,
            'ResultadosValidacion' => [['Clase' => 'RECHAZADO', 'Codigo' => 'RVC019', 'Descripcion' => 'Codigo invalido', 'Observaciones' => 'F41', 'PathFuente' => 'usuarios[0]']],
        ]);

        [$status, $body] = $this->call('POST', '/api/rips/' . $id . '/send', ['document_type' => 'CC', 'document_number' => '1020304050', 'password' => 'secret']);

        $this->assertSame(200, $status);
        $this->assertFalse($body['data']['accepted']);
        $this->assertSame('RVC019', $body['data']['results'][0]['code']);
        $this->assertSame('rejected', Database::value('SELECT status FROM rips_reports WHERE id = :id', ['id' => $id]));

        [$status] = $this->call('DELETE', '/api/rips/' . $id);
        $this->assertSame(200, $status);
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM rips_report_items'));

        [$status] = $this->call('POST', '/api/rips', ['from' => self::FROM, 'to' => self::TO]);
        $this->assertSame(201, $status, 'Deleting frees the consultations for a new report');
    }

    public function testWrongSisproCredentialsChangeNothing(): void
    {
        $id = $this->generate();
        Settings::put('rips_validator_url', 'https://localhost:9443');
        RipsController::$clientFactory = static fn (string $url, bool $tls): MuvClient => new MuvClient(
            $url,
            $tls,
            static fn (string $method, string $path, array $payload, ?string $token): array => [401, '{"login":false}']
        );

        [$status, $body] = $this->call('POST', '/api/rips/' . $id . '/send', ['document_type' => 'CC', 'document_number' => '1020304050', 'password' => 'wrong']);

        $this->assertSame(422, $status);
        $this->assertContains('SISPRO', $body['error']['message']);
        $this->assertSame('generated', Database::value('SELECT status FROM rips_reports WHERE id = :id', ['id' => $id]));
    }

    public function testAnUnexpectedValidatorAnswerIsABadGateway(): void
    {
        $id = $this->generate();
        Settings::put('rips_validator_url', 'https://localhost:9443');
        RipsController::$clientFactory = static fn (string $url, bool $tls): MuvClient => new MuvClient(
            $url,
            $tls,
            static fn (string $method, string $path, array $payload, ?string $token): array => $token === null
                ? [200, '{"token":"t"}']
                : [500, '<html>error</html>']
        );

        [$status] = $this->call('POST', '/api/rips/' . $id . '/send', ['document_type' => 'CC', 'document_number' => '1020304050', 'password' => 'x']);

        $this->assertSame(502, $status);
    }

    public function testPatientRipsFieldsAreValidatedAndStored(): void
    {
        $this->loginAs('laura');
        $base = ['first_name' => 'Julian', 'last_name' => 'Pena', 'gender' => 'male', 'status' => 'active'];

        [$status, $body] = $this->call('POST', '/api/patients', $base + ['residence_municipality' => '110', 'origin_country' => 'COL']);
        $this->assertSame(422, $status);
        $this->assertArrayHasKey('residence_municipality', $body['error']['fields']);
        $this->assertArrayHasKey('origin_country', $body['error']['fields']);

        [$status, $body] = $this->call('POST', '/api/patients', $base + [
            'document_type' => 'CE', 'document_id' => 'X12345', 'biological_sex' => 'M', 'rips_user_type' => '04',
            'residence_municipality' => '05001', 'residence_zone' => '02', 'residence_country' => '170', 'origin_country' => '862',
        ]);
        $this->assertSame(201, $status);

        $row = Database::first('SELECT * FROM patients WHERE id = :id', ['id' => $body['data']['id']]);
        $this->assertSame('CE', $row['document_type']);
        $this->assertSame('M', $row['biological_sex']);
        $this->assertSame('04', $row['rips_user_type']);
        $this->assertSame('05001', $row['residence_municipality']);
        $this->assertSame('02', $row['residence_zone']);
        $this->assertSame('862', $row['origin_country']);
        $this->assertStringStartsWithMr((string) $row['record_number']);
    }

    public function testTheProfessionalDocumentCanBeSetFromUsersAndFromTheProfile(): void
    {
        $this->loginAs('boss');
        [$status] = $this->call('PATCH', '/api/users/' . $this->psychologistId, ['document_type' => 'CE', 'document_number' => '99-88 77']);
        $this->assertSame(200, $status);
        $this->assertSame('998877', Database::value('SELECT document_number FROM users WHERE id = :id', ['id' => $this->psychologistId]));

        $this->loginAs('laura');
        [$status] = $this->call('PUT', '/api/profile', [
            'full_name' => 'Laura Moreno', 'email' => 'laura@psiclinic.test', 'document_type' => 'CC', 'document_number' => '52123456',
        ]);
        $this->assertSame(200, $status);
        $row = Database::first('SELECT document_type, document_number FROM users WHERE id = :id', ['id' => $this->psychologistId]);
        $this->assertSame('CC', $row['document_type']);
        $this->assertSame('52123456', $row['document_number']);
    }

    public function testRipsSettingsAreHiddenFromNonAdministratorsAndValidated(): void
    {
        $this->loginAs('laura');
        [, $body] = $this->call('GET', '/api/settings');
        $this->assertFalse(array_key_exists('rips_reporter_id', $body['data']));
        $this->assertArrayHasKey('clinic_name', $body['data']);

        $this->loginAs('boss');
        [, $body] = $this->call('GET', '/api/settings');
        $this->assertSame('900123456', $body['data']['rips_reporter_id']);

        [$status, $body] = $this->call('PUT', '/api/settings', ['clinic_name' => 'PsiClinic', 'rips_provider_code' => '12AB']);
        $this->assertSame(422, $status);
        $this->assertArrayHasKey('rips_provider_code', $body['error']['fields']);

        [$status, $body] = $this->call('PUT', '/api/settings', ['clinic_name' => 'PsiClinic', 'rips_validator_url' => 'ftp://validator']);
        $this->assertSame(422, $status);
        $this->assertArrayHasKey('rips_validator_url', $body['error']['fields']);

        [$status] = $this->call('PUT', '/api/settings', ['clinic_name' => 'PsiClinic', 'rips_reporter_id' => '900.123.456', 'rips_environment' => 'production']);
        $this->assertSame(200, $status);
        $this->assertSame('900123456', Settings::get('rips_reporter_id'));
        $this->assertSame('production', Settings::get('rips_environment'));
    }

    public function testTheMetaCatalogsIncludeTheRipsTables(): void
    {
        $this->loginAs('laura');

        [, $body] = $this->call('GET', '/api/meta');

        $this->assertSame('CC', $body['data']['rips']['documentTypes'][0]['value']);
        $this->assertSame(['F', 'M', 'I'], array_column($body['data']['rips']['sexes'], 'value'));
        $this->assertSame('icd11', $body['data']['diagnosisSystems'][0]['value']);
    }

    // --- Helpers ---------------------------------------------------------

    private function configure(): void
    {
        Settings::put('rips_reporter_id', '900123456');
        Settings::put('rips_provider_code', '110010000001');
        Settings::put('rips_service_code', '344');
    }

    private function createReadyPatient(string $first, string $last, string $document, string $sex): int
    {
        return $this->createPatient([
            'first_name' => $first,
            'last_name' => $last,
            'document_type' => 'CC',
            'document_id' => $document,
            'biological_sex' => $sex,
            'residence_municipality' => '11001',
        ]);
    }

    private function addDiagnosis(int $patientId, string $icd10, bool $primary = true): void
    {
        Database::insert('diagnoses', [
            'patient_id' => $patientId,
            'system' => 'icd10',
            'code' => $icd10,
            'icd10_code' => $icd10,
            'title' => 'Diagnosis ' . $icd10,
            'status' => 'active',
            'is_primary' => $primary ? 1 : 0,
            'created_at' => '2026-07-01 08:00:00',
        ]);
    }

    /** Generates the report for August with one consultation, signed in as the administrator. */
    private function generate(): int
    {
        $this->createAppointment($this->patientId, $this->psychologistId, '2026-08-03 09:00:00', ['status' => 'completed']);
        $this->loginAs('boss');

        [$status, $body] = $this->call('POST', '/api/rips', ['from' => self::FROM, 'to' => self::TO]);
        $this->assertSame(201, $status, 'The RIPS could not be generated');

        return (int) $body['data']['id'];
    }

    /** Fake MUV: answers the login with a token and the submission with $answer. */
    private function fakeValidator(array &$calls, array $answer): void
    {
        RipsController::$clientFactory = static function (string $url, bool $tls) use (&$calls, $answer): MuvClient {
            return new MuvClient($url, $tls, static function (string $method, string $path, array $payload, ?string $token) use (&$calls, $answer): array {
                $calls[] = ['path' => $path, 'payload' => $payload, 'token' => $token];

                return $token === null
                    ? [200, json_encode(['token' => 'token-xyz', 'login' => true])]
                    : [200, json_encode($answer)];
            });
        };
    }

    private function assertStringStartsWithMr(string $value): void
    {
        $this->assertTrue(str_starts_with($value, 'MR-'), 'Record numbers start with MR-: ' . $value);
    }

    private function call(string $method, string $uri, array $body = []): array
    {
        $output = $this->raw($method, $uri, $body, $status);

        return [$status, json_decode($output, true) ?? []];
    }

    private function raw(string $method, string $uri, array $body = [], ?int &$status = null): string
    {
        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_CSRF_TOKEN' => Csrf::token(),
        ];

        /** @var Router $router */
        $router = require dirname(__DIR__, 2) . '/src/routes.php';

        ob_start();
        try {
            Response::$lastStatus = 200;
            $router->dispatch(new Request([], $body, [], $server));
            $status = Response::$lastStatus;
        } catch (HttpException $exception) {
            Response::json($exception->toArray(), $exception->status());
            $status = $exception->status();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    private function loginAs(string $username): void
    {
        Auth::logout();
        $_SESSION = [];
        $this->assertTrue(Auth::attempt($username, 'Password1234'), 'Could not sign in as ' . $username);
    }
}
