<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AppointmentRequests;
use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\ConsentTemplates;
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Invoices;
use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

/**
 * Catalogos que la interfaz necesita para pintar etiquetas y selects. Vienen
 * de las constantes del dominio para que haya una sola fuente de verdad.
 */
final class MetaController extends Controller
{
    public function index(Request $request): void
    {
        $settings = Settings::all();

        $templates = [];
        foreach (ConsentTemplates::all() as $code => $template) {
            $templates[] = ['value' => $code, 'label' => $template['title']];
        }

        $instruments = [];
        foreach (Instruments::all() as $code => $instrument) {
            $instruments[] = ['value' => $code, 'label' => $code . ' · ' . Instruments::describe($code)['domain']];
        }

        $this->ok([
            'patientStatuses' => Present::options(Patients::STATUSES),
            'riskLevels' => Present::options(Patients::RISK_LEVELS),
            'genders' => Present::options(Patients::GENDERS),
            'appointmentStatuses' => Present::options(Appointments::STATUSES),
            'modalities' => Present::options(Appointments::MODALITIES),
            'noteFormats' => Present::options(Notes::FORMATS),
            'interventions' => Notes::INTERVENTIONS,
            'documentCategories' => Present::options(Documents::CATEGORIES),
            'invoiceStatuses' => Present::options(Invoices::STATUSES),
            'paymentMethods' => Present::options(Invoices::METHODS),
            'diagnosisStatuses' => Present::options(PatientController::DIAGNOSIS_STATUSES),
            'requestStatuses' => Present::options(AppointmentRequests::STATUSES),
            'requestModalities' => Present::options(AppointmentRequests::MODALITIES),
            'requestAttendees' => Present::options(AppointmentRequests::ATTENDEES),
            'requestContact' => Present::options(AppointmentRequests::CONTACT_PREFERENCES),
            'requestTimes' => Present::options(AppointmentRequests::TIMES),
            'consentTemplates' => $templates,
            'instruments' => $instruments,
            'roles' => Present::options(SettingsController::ROLES),
            'psychologists' => Database::all(
                'SELECT id, full_name FROM users WHERE role IN ("admin","psychologist") AND is_active = 1 ORDER BY full_name'
            ),
            'patients' => Patients::options(),
            'settings' => [
                'currency' => $settings['currency'],
                'session_duration' => (int) $settings['session_duration'],
                'default_fee' => (float) $settings['default_fee'],
                'working_hours_start' => $settings['working_hours_start'],
                'working_hours_end' => $settings['working_hours_end'],
                'note_lock_hours' => (int) $settings['note_lock_hours'],
            ],
            'nextRecordNumber' => Patients::nextRecordNumber(),
            'nextInvoiceNumber' => Invoices::nextNumber(),
        ]);
    }
}
