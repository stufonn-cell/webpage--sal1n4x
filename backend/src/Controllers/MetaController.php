<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AppointmentRequests;
use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\ConsentTemplates;
use PsiClinic\Domain\DocumentLanguage;
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Icd11;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Invoices;
use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Rips;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

/**
 * Catalogs the interface needs to render labels and selects. They come from
 * the domain constants so there is a single source of truth.
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
            'diagnosisSystems' => Present::options(PatientController::DIAGNOSIS_SYSTEMS),
            'icd11Release' => Icd11::RELEASE,
            'requestStatuses' => Present::options(AppointmentRequests::STATUSES),
            'requestModalities' => Present::options(AppointmentRequests::MODALITIES),
            'requestAttendees' => Present::options(AppointmentRequests::ATTENDEES),
            'requestContact' => Present::options(AppointmentRequests::CONTACT_PREFERENCES),
            'requestTimes' => Present::options(AppointmentRequests::TIMES),
            'consentTemplates' => $templates,
            'documentLanguages' => Present::options(DocumentLanguage::LANGUAGES),
            'instruments' => $instruments,
            'roles' => Present::options(SettingsController::ROLES),
            'rips' => [
                'documentTypes' => Present::options(Rips::DOCUMENT_TYPES),
                'userTypes' => Present::options(Rips::USER_TYPES),
                'sexes' => Present::options(Rips::SEXES),
                'zones' => Present::options(Rips::ZONES),
                'purposes' => Present::options(Rips::PURPOSES),
                'causes' => Present::options(Rips::CAUSES),
                'environments' => Present::options(Rips::ENVIRONMENTS),
                'statuses' => Present::options(Rips::STATUSES),
            ],
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
                'document_language' => DocumentLanguage::resolve(''),
            ],
            'nextRecordNumber' => Patients::nextRecordNumber(),
            'nextInvoiceNumber' => Invoices::nextNumber(),
        ]);
    }
}
