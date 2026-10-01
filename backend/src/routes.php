<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

use PsiClinic\Controllers\AppointmentController;
use PsiClinic\Controllers\AppointmentRequestController;
use PsiClinic\Controllers\AssessmentController;
use PsiClinic\Controllers\AuthController;
use PsiClinic\Controllers\BillingController;
use PsiClinic\Controllers\ConsentController;
use PsiClinic\Controllers\DashboardController;
use PsiClinic\Controllers\DocumentController;
use PsiClinic\Controllers\Icd11Controller;
use PsiClinic\Controllers\MetaController;
use PsiClinic\Controllers\NoteController;
use PsiClinic\Controllers\PatientController;
use PsiClinic\Controllers\PortalController;
use PsiClinic\Controllers\PublicController;
use PsiClinic\Controllers\RipsController;
use PsiClinic\Controllers\SearchController;
use PsiClinic\Controllers\SettingsController;
use PsiClinic\Core\Middleware\Authenticate;
use PsiClinic\Core\Middleware\RequireAdmin;
use PsiClinic\Core\Middleware\RequirePatient;
use PsiClinic\Core\Middleware\RequireStaff;
use PsiClinic\Core\Middleware\VerifyCsrf;
use PsiClinic\Core\Router;

/*
 * API JSON consumida por el frontend (frontend/). Todas las rutas viven bajo
 * /api y toda escritura exige el token CSRF en la cabecera X-CSRF-Token.
 */

$router = new Router();

$csrf = [VerifyCsrf::class];
$auth = [Authenticate::class];
$authWrite = [VerifyCsrf::class, Authenticate::class];
$staff = [RequireStaff::class];
$staffWrite = [VerifyCsrf::class, RequireStaff::class];
$admin = [RequireStaff::class, RequireAdmin::class];
$adminWrite = [VerifyCsrf::class, RequireStaff::class, RequireAdmin::class];
$patient = [RequirePatient::class];
$patientWrite = [VerifyCsrf::class, RequirePatient::class];

// Sitio publico y sesion
$router->get('/api/session', [AuthController::class, 'session']);
$router->get('/api/public/site', [PublicController::class, 'site']);
$router->post('/api/public/appointment-requests', [PublicController::class, 'requestAppointment'], $csrf);
$router->post('/api/auth/login', [AuthController::class, 'login'], $csrf);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], $authWrite);

// Perfil de la persona autenticada
$router->get('/api/profile', [AuthController::class, 'profile'], $auth);
$router->put('/api/profile', [AuthController::class, 'updateProfile'], $authWrite);
$router->put('/api/profile/theme', [AuthController::class, 'updateTheme'], $authWrite);

// Equipo clinico
$router->get('/api/meta', [MetaController::class, 'index'], $staff);
$router->get('/api/dashboard', [DashboardController::class, 'index'], $staff);
$router->get('/api/search', [SearchController::class, 'index'], $staff);

$router->get('/api/appointment-requests', [AppointmentRequestController::class, 'index'], $staff);
$router->patch('/api/appointment-requests/{id}', [AppointmentRequestController::class, 'update'], $staffWrite);

$router->get('/api/patients', [PatientController::class, 'index'], $staff);
$router->post('/api/patients', [PatientController::class, 'store'], $staffWrite);
$router->get('/api/patients/{id}', [PatientController::class, 'show'], $staff);
$router->put('/api/patients/{id}', [PatientController::class, 'update'], $staffWrite);
$router->delete('/api/patients/{id}', [PatientController::class, 'destroy'], $adminWrite);
$router->post('/api/patients/{id}/diagnoses', [PatientController::class, 'storeDiagnosis'], $staffWrite);
$router->patch('/api/patients/{id}/diagnoses/{diagnosisId}', [PatientController::class, 'updateDiagnosis'], $staffWrite);
$router->delete('/api/patients/{id}/diagnoses/{diagnosisId}', [PatientController::class, 'destroyDiagnosis'], $staffWrite);
$router->post('/api/patients/{id}/portal-access', [PatientController::class, 'createPortalAccess'], $staffWrite);
$router->post('/api/patients/{id}/assessments', [AssessmentController::class, 'assign'], $staffWrite);
$router->get('/api/patients/{id}/note-context', [NoteController::class, 'context'], $staff);

$router->get('/api/appointments', [AppointmentController::class, 'index'], $staff);
$router->post('/api/appointments', [AppointmentController::class, 'store'], $staffWrite);
$router->get('/api/appointments/{id}', [AppointmentController::class, 'show'], $staff);
$router->put('/api/appointments/{id}', [AppointmentController::class, 'update'], $staffWrite);
$router->post('/api/appointments/{id}/status', [AppointmentController::class, 'changeStatus'], $staffWrite);
$router->delete('/api/appointments/{id}', [AppointmentController::class, 'destroy'], $staffWrite);

$router->get('/api/notes', [NoteController::class, 'index'], $staff);
$router->post('/api/notes', [NoteController::class, 'store'], $staffWrite);
$router->get('/api/notes/{id}', [NoteController::class, 'show'], $staff);
$router->put('/api/notes/{id}', [NoteController::class, 'update'], $staffWrite);
$router->post('/api/notes/{id}/sign', [NoteController::class, 'sign'], $staffWrite);

$router->get('/api/instruments', [AssessmentController::class, 'catalog'], $staff);
$router->get('/api/instruments/{code}', [AssessmentController::class, 'instrument'], $staff);
$router->get('/api/assessments', [AssessmentController::class, 'index'], $staff);
$router->post('/api/assessments', [AssessmentController::class, 'store'], $staffWrite);
$router->get('/api/assessments/{id}', [AssessmentController::class, 'show'], $staff);
$router->delete('/api/assessments/{id}', [AssessmentController::class, 'destroy'], $staffWrite);

$router->get('/api/consents', [ConsentController::class, 'index'], $staff);
$router->post('/api/consents', [ConsentController::class, 'store'], $staffWrite);
$router->get('/api/consents/{id}', [ConsentController::class, 'show'], $auth);
$router->post('/api/consents/{id}/sign', [ConsentController::class, 'sign'], $authWrite);

$router->get('/api/documents', [DocumentController::class, 'index'], $staff);
$router->post('/api/documents', [DocumentController::class, 'store'], $staffWrite);
$router->get('/api/documents/{id}/download', [DocumentController::class, 'download'], $auth);
$router->delete('/api/documents/{id}', [DocumentController::class, 'destroy'], $staffWrite);

$router->get('/api/invoices', [BillingController::class, 'index'], $staff);
$router->post('/api/invoices', [BillingController::class, 'store'], $staffWrite);
$router->get('/api/invoices/{id}', [BillingController::class, 'show'], $staff);
$router->post('/api/invoices/{id}/payments', [BillingController::class, 'storePayment'], $staffWrite);

// Catalogo CIE-11 y RIPS (Resolucion 2275 de 2023)
$router->get('/api/icd11', [Icd11Controller::class, 'search'], $staff);
$router->get('/api/rips', [RipsController::class, 'index'], $admin);
$router->post('/api/rips/preview', [RipsController::class, 'preview'], $adminWrite);
$router->post('/api/rips', [RipsController::class, 'store'], $adminWrite);
$router->get('/api/rips/{id}', [RipsController::class, 'show'], $admin);
$router->get('/api/rips/{id}/download', [RipsController::class, 'download'], $admin);
$router->post('/api/rips/{id}/send', [RipsController::class, 'send'], $adminWrite);
$router->delete('/api/rips/{id}', [RipsController::class, 'destroy'], $adminWrite);

$router->get('/api/settings', [SettingsController::class, 'index'], $staff);
$router->put('/api/settings', [SettingsController::class, 'update'], $adminWrite);
$router->get('/api/users', [SettingsController::class, 'users'], $staff);
$router->post('/api/users', [SettingsController::class, 'storeUser'], $adminWrite);
$router->patch('/api/users/{id}', [SettingsController::class, 'updateUser'], $adminWrite);
$router->post('/api/users/{id}/toggle', [SettingsController::class, 'toggleUser'], $adminWrite);
$router->get('/api/audit', [SettingsController::class, 'audit'], $staff);

// Portal del paciente
$router->get('/api/portal', [PortalController::class, 'index'], $patient);
$router->get('/api/portal/appointments', [PortalController::class, 'appointments'], $patient);
$router->get('/api/portal/questionnaires', [PortalController::class, 'questionnaires'], $patient);
$router->get('/api/portal/questionnaires/{id}', [PortalController::class, 'showQuestionnaire'], $patient);
$router->post('/api/portal/questionnaires/{id}', [PortalController::class, 'submitQuestionnaire'], $patientWrite);
$router->get('/api/portal/documents', [PortalController::class, 'documents'], $patient);

return $router;
