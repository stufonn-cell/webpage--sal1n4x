<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Controllers\AppointmentController;
use PsiClinic\Controllers\AssessmentController;
use PsiClinic\Controllers\AuthController;
use PsiClinic\Controllers\BillingController;
use PsiClinic\Controllers\ConsentController;
use PsiClinic\Controllers\DashboardController;
use PsiClinic\Controllers\DocumentController;
use PsiClinic\Controllers\NoteController;
use PsiClinic\Controllers\PatientController;
use PsiClinic\Controllers\PortalController;
use PsiClinic\Controllers\SettingsController;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Database;
use PsiClinic\Core\Request;
use PsiClinic\Domain\Assessments;

final class PageRenderTest extends FeatureTestCase
{
    private int $psychologistId = 0;
    private int $patientId = 0;
    private int $noteId = 0;
    private int $assessmentId = 0;

    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->psychologistId = $this->createUser('psychologist', 'render');
        $this->patientId = $this->createPatient(['first_name' => 'Mariana', 'last_name' => 'Vega']);
        $this->createAppointment($this->patientId, $this->psychologistId, date('Y-m-d 09:00:00'));
        $this->noteId = $this->createNote($this->patientId, $this->psychologistId, ['mood_score' => 6]);

        $this->assessmentId = Database::insert('assessments', [
            'uuid' => uuid(),
            'patient_id' => $this->patientId,
            'instrument_code' => 'GAD-7',
            'assigned_by' => $this->psychologistId,
            'status' => 'pending',
        ]);
        Assessments::complete($this->assessmentId, 'GAD-7', [2, 2, 1, 1, 1, 1, 1]);

        Auth::logout();
        Auth::attempt('render', 'Clave12345');
    }

    public function testDashboardRendersWithData(): void
    {
        $html = $this->render(fn () => (new DashboardController())->index($this->request()));

        $this->assertContains('<!DOCTYPE html>', $html);
        $this->assertContains('Pacientes activos', $html);
        $this->assertContains('Hecho por Salinas', $html);
    }

    public function testPatientListRenders(): void
    {
        $html = $this->render(fn () => (new PatientController())->index($this->request()));

        $this->assertContains('Mariana', $html);
        $this->assertContains('HC-TEST', $html);
    }

    public function testPatientProfileRendersEveryTab(): void
    {
        $html = $this->render(fn () => (new PatientController())->show($this->request(), (string) $this->patientId));

        foreach (['Resumen', 'Notas', 'Evaluaciones', 'Diagnosticos', 'Citas', 'Documentos', 'Administrativo'] as $tab) {
            $this->assertContains($tab, $html, 'Falta la pestana ' . $tab);
        }

        $this->assertContains('Linea de tiempo', $html);
    }

    public function testPatientFormsRender(): void
    {
        $this->assertContains('Nuevo paciente', $this->render(fn () => (new PatientController())->create($this->request())));
        $this->assertContains('Editar paciente', $this->render(fn () => (new PatientController())->edit($this->request(), (string) $this->patientId)));
    }

    public function testAgendaRendersTheWeekGrid(): void
    {
        $html = $this->render(fn () => (new AppointmentController())->index($this->request()));

        $this->assertContains('Agenda semanal', $html);
        $this->assertContains('Miercoles', $html);
    }

    public function testAppointmentFormRenders(): void
    {
        $this->assertContains('Agendar cita', $this->render(fn () => (new AppointmentController())->create($this->request())));
    }

    public function testNoteViewsRender(): void
    {
        $this->assertContains('Notas clinicas', $this->render(fn () => (new NoteController())->index($this->request())));
        $this->assertContains('Nueva nota de sesion', $this->render(fn () => (new NoteController())->create($this->request())));
        $this->assertContains('Sesion #1', $this->render(fn () => (new NoteController())->show($this->request(), (string) $this->noteId)));
        $this->assertContains('Registro profesional', $this->render(fn () => (new NoteController())->print($this->request(), (string) $this->noteId)));
    }

    public function testAssessmentViewsRender(): void
    {
        $this->assertContains('Evaluaciones psicometricas', $this->render(fn () => (new AssessmentController())->index($this->request())));
        $this->assertContains('Catalogo de instrumentos', $this->render(fn () => (new AssessmentController())->catalog($this->request())));

        $report = $this->render(fn () => (new AssessmentController())->show($this->request(), (string) $this->assessmentId));

        $this->assertContains('Bandas de referencia', $report);
        $this->assertContains('Respuestas item por item', $report);
    }

    public function testEveryInstrumentFormRenders(): void
    {
        foreach (\PsiClinic\Domain\Instruments::codes() as $code) {
            $html = $this->render(fn () => (new AssessmentController())->create($this->request(['instrumento' => $code])));

            $this->assertContains('Corregir y guardar', $html, 'Falla el formulario de ' . $code);
        }
    }

    public function testAdministrativeViewsRender(): void
    {
        $this->assertContains('Consentimientos informados', $this->render(fn () => (new ConsentController())->index($this->request())));
        $this->assertContains('Repositorio', $this->render(fn () => (new DocumentController())->index($this->request())));
        $this->assertContains('Facturacion', $this->render(fn () => (new BillingController())->index($this->request())));
        $this->assertContains('Nueva factura', $this->render(fn () => (new BillingController())->create($this->request())));
    }

    public function testSettingsViewsRender(): void
    {
        $this->assertContains('Configuracion de la clinica', $this->render(fn () => (new SettingsController())->index($this->request())));
        $this->assertContains('Usuarios', $this->render(fn () => (new SettingsController())->users($this->request())));
        $this->assertContains('Registro de auditoria', $this->render(fn () => (new SettingsController())->audit($this->request())));
        $this->assertContains('Mi perfil', $this->render(fn () => (new AuthController())->profile($this->request())));
    }

    public function testLoginPageRendersWithoutASession(): void
    {
        Auth::logout();

        $html = $this->render(fn () => (new AuthController())->showLogin($this->request()));

        $this->assertContains('Bienvenido de vuelta', $html);
        $this->assertContains('name="_token"', $html);
        $this->assertContains('stufonn-cell', $html);
    }

    public function testThePortalRendersForAPatientAccount(): void
    {
        $portalUserId = $this->createUser('patient', 'hc-portal');
        Database::update('users', $portalUserId, ['patient_id' => $this->patientId]);

        Auth::logout();
        Auth::attempt('hc-portal', 'Clave12345');

        $html = $this->render(fn () => (new PortalController())->index($this->request()));

        $this->assertContains('Hola, Mariana', $html);
        $this->assertContains('Portal del paciente', $html);
    }

    public function testGlobalSearchReturnsJson(): void
    {
        $json = $this->render(fn () => (new \PsiClinic\Controllers\SearchController())->index($this->request(['q' => 'Vega'])));

        $payload = json_decode($json, true);

        $this->assertNotNull($payload);
        $this->assertGreaterThan(0, count($payload['results']));
        $this->assertSame('Pacientes', $payload['results'][0]['group']);
    }

    public function testShortSearchTermsReturnNoResults(): void
    {
        $payload = json_decode($this->render(fn () => (new \PsiClinic\Controllers\SearchController())->index($this->request(['q' => 'a']))), true);

        $this->assertCount(0, $payload['results']);
    }

    private function request(array $query = []): Request
    {
        return new Request($query, [], [], $_SERVER);
    }

    private function render(callable $callback): string
    {
        ob_start();

        try {
            $callback();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
