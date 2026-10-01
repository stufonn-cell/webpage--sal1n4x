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
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AppointmentRequests;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

/**
 * Endpoints del sitio publico. No requieren sesion y solo exponen datos que
 * la clinica ha decidido publicar.
 */
final class PublicController extends Controller
{
    public function site(Request $request): void
    {
        $instruments = [];
        foreach (Instruments::all() as $code => $instrument) {
            $instruments[] = ['code' => $code, 'name' => $instrument['name'], 'domain' => $instrument['domain']];
        }

        $this->ok([
            'clinic' => Settings::publicValues(),
            'professionals' => $this->professionals(),
            'approaches' => Notes::INTERVENTIONS,
            'instruments' => $instruments,
            'form' => [
                'modalities' => Present::options(AppointmentRequests::MODALITIES),
                'attendees' => Present::options(AppointmentRequests::ATTENDEES),
                'contactPreferences' => Present::options(AppointmentRequests::CONTACT_PREFERENCES),
                'times' => Present::options(AppointmentRequests::TIMES),
            ],
        ]);
    }

    public function requestAppointment(Request $request): void
    {
        // Campo trampa: las personas no lo ven, los bots suelen llenarlo.
        if ($request->string('website') !== '') {
            $this->created(null, 'Recibimos tu solicitud.');

            return;
        }

        if (AppointmentRequests::tooManyFrom($request->ip())) {
            throw new HttpException(429, 'Ya recibimos varias solicitudes desde esta conexión. Si necesitas algo más, escríbenos o llámanos directamente.');
        }

        $this->validate($request, [
            'full_name' => 'required|max:160',
            'email' => 'required|email|max:180',
            'phone' => 'max:40',
            'contact_preference' => 'required|in:' . implode(',', array_keys(AppointmentRequests::CONTACT_PREFERENCES)),
            'modality' => 'required|in:' . implode(',', array_keys(AppointmentRequests::MODALITIES)),
            'attendee' => 'required|in:' . implode(',', array_keys(AppointmentRequests::ATTENDEES)),
            'message' => 'max:600',
        ]);

        $errors = [];
        $contact = $request->string('contact_preference');
        if (in_array($contact, ['phone', 'whatsapp'], true) && $request->string('phone') === '') {
            $errors['phone'] = 'Déjanos un número para poder llamarte o escribirte.';
        }
        if (!$request->bool('privacy_accepted')) {
            $errors['privacy_accepted'] = 'Necesitamos tu autorización para usar estos datos y contactarte.';
        }

        $professionalId = $request->integer('preferred_professional_id');
        if ($professionalId > 0 && !in_array($professionalId, array_column($this->professionals(), 'id'), true)) {
            $errors['preferred_professional_id'] = 'Elige un profesional de la lista.';
        }

        if ($errors !== []) {
            throw HttpException::unprocessable('Revisa los campos marcados.', $errors);
        }

        $times = array_values(array_intersect(
            array_map('strval', $request->array('preferred_times')),
            array_keys(AppointmentRequests::TIMES)
        ));

        $id = AppointmentRequests::create([
            'full_name' => $request->string('full_name'),
            'email' => $request->string('email'),
            'phone' => $request->string('phone') ?: null,
            'contact_preference' => $contact,
            'modality' => $request->string('modality'),
            'attendee' => $request->string('attendee'),
            'preferred_professional_id' => $professionalId > 0 ? $professionalId : null,
            'preferred_times' => $times === [] ? null : implode(',', $times),
            'message' => $request->string('message') ?: null,
        ], $request->ip());

        AuditLog::record('create', 'appointment_request', $id);

        $this->created(null, 'Recibimos tu solicitud.');
    }

    private function professionals(): array
    {
        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id']] + $row,
            Database::all(
                'SELECT id, full_name, specialty, license_number, public_bio
                 FROM users
                 WHERE show_on_site = 1 AND is_active = 1 AND role IN ("admin","psychologist")
                 ORDER BY full_name'
            )
        );
    }
}
