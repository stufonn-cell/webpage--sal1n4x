<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

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
use PsiClinic\Controllers\SearchController;
use PsiClinic\Controllers\SettingsController;
use PsiClinic\Core\Middleware\Authenticate;
use PsiClinic\Core\Middleware\RequireAdmin;
use PsiClinic\Core\Middleware\RequireStaff;
use PsiClinic\Core\Middleware\VerifyCsrf;
use PsiClinic\Core\Router;

$router = new Router();

$guest = [];
$auth = [Authenticate::class];
$staff = [RequireStaff::class];
$staffWrite = [VerifyCsrf::class, RequireStaff::class];
$adminWrite = [VerifyCsrf::class, RequireStaff::class, RequireAdmin::class];
$authWrite = [VerifyCsrf::class, Authenticate::class];

$router->get('/', [AuthController::class, 'root'], $guest);
$router->get('/login', [AuthController::class, 'showLogin'], $guest);
$router->post('/login', [AuthController::class, 'login'], [VerifyCsrf::class]);
$router->post('/logout', [AuthController::class, 'logout'], $authWrite);
$router->get('/perfil', [AuthController::class, 'profile'], $auth);
$router->post('/perfil', [AuthController::class, 'updateProfile'], $authWrite);
$router->post('/tema', [AuthController::class, 'toggleTheme'], $authWrite);

$router->get('/dashboard', [DashboardController::class, 'index'], $staff);
$router->get('/buscar', [SearchController::class, 'index'], $staff);

$router->get('/pacientes', [PatientController::class, 'index'], $staff);
$router->get('/pacientes/nuevo', [PatientController::class, 'create'], $staff);
$router->post('/pacientes', [PatientController::class, 'store'], $staffWrite);
$router->get('/pacientes/{id}', [PatientController::class, 'show'], $staff);
$router->get('/pacientes/{id}/editar', [PatientController::class, 'edit'], $staff);
$router->put('/pacientes/{id}', [PatientController::class, 'update'], $staffWrite);
$router->delete('/pacientes/{id}', [PatientController::class, 'destroy'], $adminWrite);
$router->post('/pacientes/{id}/diagnosticos', [PatientController::class, 'storeDiagnosis'], $staffWrite);
$router->delete('/pacientes/{id}/diagnosticos/{diagnosisId}', [PatientController::class, 'destroyDiagnosis'], $staffWrite);
$router->post('/pacientes/{id}/acceso-portal', [PatientController::class, 'createPortalAccess'], $staffWrite);

$router->get('/agenda', [AppointmentController::class, 'index'], $staff);
$router->get('/agenda/nueva', [AppointmentController::class, 'create'], $staff);
$router->post('/agenda', [AppointmentController::class, 'store'], $staffWrite);
$router->get('/agenda/{id}/editar', [AppointmentController::class, 'edit'], $staff);
$router->put('/agenda/{id}', [AppointmentController::class, 'update'], $staffWrite);
$router->post('/agenda/{id}/estado', [AppointmentController::class, 'changeStatus'], $staffWrite);
$router->delete('/agenda/{id}', [AppointmentController::class, 'destroy'], $staffWrite);

$router->get('/notas', [NoteController::class, 'index'], $staff);
$router->get('/notas/nueva', [NoteController::class, 'create'], $staff);
$router->post('/notas', [NoteController::class, 'store'], $staffWrite);
$router->get('/notas/{id}', [NoteController::class, 'show'], $staff);
$router->get('/notas/{id}/editar', [NoteController::class, 'edit'], $staff);
$router->put('/notas/{id}', [NoteController::class, 'update'], $staffWrite);
$router->post('/notas/{id}/firmar', [NoteController::class, 'sign'], $staffWrite);
$router->get('/notas/{id}/imprimir', [NoteController::class, 'print'], $staff);

$router->get('/evaluaciones', [AssessmentController::class, 'index'], $staff);
$router->get('/evaluaciones/catalogo', [AssessmentController::class, 'catalog'], $staff);
$router->get('/evaluaciones/nueva', [AssessmentController::class, 'create'], $staff);
$router->post('/evaluaciones', [AssessmentController::class, 'store'], $staffWrite);
$router->get('/evaluaciones/{id}', [AssessmentController::class, 'show'], $staff);
$router->post('/evaluaciones/{id}/asignar', [AssessmentController::class, 'assign'], $staffWrite);
$router->delete('/evaluaciones/{id}', [AssessmentController::class, 'destroy'], $staffWrite);

$router->get('/consentimientos', [ConsentController::class, 'index'], $staff);
$router->post('/consentimientos', [ConsentController::class, 'store'], $staffWrite);
$router->get('/consentimientos/{id}', [ConsentController::class, 'show'], $auth);
$router->post('/consentimientos/{id}/firmar', [ConsentController::class, 'sign'], $authWrite);

$router->get('/documentos', [DocumentController::class, 'index'], $staff);
$router->post('/documentos', [DocumentController::class, 'store'], $staffWrite);
$router->get('/documentos/{id}', [DocumentController::class, 'download'], $auth);
$router->delete('/documentos/{id}', [DocumentController::class, 'destroy'], $staffWrite);

$router->get('/facturacion', [BillingController::class, 'index'], $staff);
$router->get('/facturacion/nueva', [BillingController::class, 'create'], $staff);
$router->post('/facturacion', [BillingController::class, 'store'], $staffWrite);
$router->get('/facturacion/{id}', [BillingController::class, 'show'], $staff);
$router->post('/facturacion/{id}/pagos', [BillingController::class, 'storePayment'], $staffWrite);

$router->get('/ajustes', [SettingsController::class, 'index'], $staff);
$router->post('/ajustes', [SettingsController::class, 'update'], $adminWrite);
$router->get('/ajustes/usuarios', [SettingsController::class, 'users'], $staff);
$router->post('/ajustes/usuarios', [SettingsController::class, 'storeUser'], $adminWrite);
$router->post('/ajustes/usuarios/{id}/estado', [SettingsController::class, 'toggleUser'], $adminWrite);
$router->get('/ajustes/auditoria', [SettingsController::class, 'audit'], $staff);

$router->get('/portal', [PortalController::class, 'index'], $auth);
$router->get('/portal/citas', [PortalController::class, 'appointments'], $auth);
$router->get('/portal/cuestionarios', [PortalController::class, 'questionnaires'], $auth);
$router->get('/portal/cuestionarios/{id}', [PortalController::class, 'showQuestionnaire'], $auth);
$router->post('/portal/cuestionarios/{id}', [PortalController::class, 'submitQuestionnaire'], $authWrite);
$router->get('/portal/documentos', [PortalController::class, 'documents'], $auth);

return $router;
