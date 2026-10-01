<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\OutboundUrl;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\DocumentLanguage;
use PsiClinic\Domain\Rips;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class SettingsController extends Controller
{
    public const ROLES = [
        'admin' => 'Administrator',
        'psychologist' => 'Clinical psychologist',
        'assistant' => 'Assistant',
    ];

    /** RIPS settings only an administrator needs to see. */
    private const RIPS_KEYS = [
        'rips_reporter_id', 'rips_provider_code', 'rips_service_code', 'rips_purpose_first',
        'rips_purpose_follow_up', 'rips_cause', 'rips_first_note_number', 'rips_environment',
        'rips_validator_url', 'rips_validator_verify_tls',
    ];

    public function index(Request $request): void
    {
        $settings = Settings::all();

        $this->ok(Auth::is('admin') ? $settings : array_diff_key($settings, array_flip(self::RIPS_KEYS)));
    }

    public function update(Request $request): void
    {
        $this->validate($request, [
            'clinic_name' => 'required|max:120',
            'clinic_tagline' => 'max:160',
            'clinic_email' => 'email|max:180',
            'clinic_phone' => 'max:40',
            'clinic_address' => 'max:200',
            'clinic_about' => 'max:800',
            'whatsapp_number' => 'max:20',
            'crisis_line' => 'max:40',
            'currency' => 'max:3',
            'session_duration' => 'numeric',
            'default_fee' => 'numeric',
            'note_lock_hours' => 'numeric',
            'document_language' => 'in:' . implode(',', array_keys(DocumentLanguage::LANGUAGES)),
            'rips_reporter_id' => 'max:15',
            'rips_provider_code' => 'max:12',
            'rips_service_code' => 'numeric|max:4',
            'rips_first_note_number' => 'numeric',
            'rips_purpose_first' => 'in:' . implode(',', array_keys(Rips::PURPOSES)),
            'rips_purpose_follow_up' => 'in:' . implode(',', array_keys(Rips::PURPOSES)),
            'rips_cause' => 'in:' . implode(',', array_keys(Rips::CAUSES)),
            'rips_environment' => 'in:' . implode(',', array_keys(Rips::ENVIRONMENTS)),
            'rips_validator_url' => 'max:255',
            'rips_validator_verify_tls' => 'in:0,1',
            'working_hours_start' => 'max:5',
            'working_hours_end' => 'max:5',
        ]);

        foreach (['working_hours_start', 'working_hours_end'] as $key) {
            if ($request->has($key) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $request->string($key))) {
                throw HttpException::unprocessable('Check the workday hours.', [$key => 'Use a time like 07:00.']);
            }
        }

        // Format, credentials and reserved addresses (cloud metadata, link-local)
        // are checked here; DNS is resolved again right before every submission,
        // so a validator that is not running yet can still be saved.
        $url = $request->string('rips_validator_url');
        $problem = $url === '' ? null : OutboundUrl::problem($url, static fn (string $host): array => ['192.0.2.1']);
        if ($problem !== null) {
            throw HttpException::unprocessable('The validator address is not valid.', ['rips_validator_url' => $problem . ' Example: https://localhost:9443']);
        }
        if ($request->string('rips_provider_code') !== '' && !preg_match('/^\d{10,12}$/', $request->string('rips_provider_code'))) {
            throw HttpException::unprocessable('Check the provider code.', ['rips_provider_code' => 'Use the 10 to 12 digits of the code in REPS.']);
        }
        if ($request->string('rips_reporter_id') !== '' && !preg_match('/^\d{4,12}$/', preg_replace('/\D+/', '', $request->string('rips_reporter_id')) ?? '')) {
            throw HttpException::unprocessable('Check the NIT.', ['rips_reporter_id' => 'Digits only, without the verification digit.']);
        }

        foreach (array_keys(Settings::DEFAULTS) as $key) {
            if ($request->has($key)) {
                $value = $request->string($key);
                if ($key === 'whatsapp_number' || $key === 'rips_reporter_id') {
                    $value = preg_replace('/\D+/', '', $value) ?? '';
                }
                Settings::put($key, $value);
            }
        }

        AuditLog::record('update', 'settings');
        $this->message('Settings saved.', Settings::all());
    }

    public function users(Request $request): void
    {
        $this->ok(Present::users(Database::all(
            'SELECT u.*, p.record_number
             FROM users u
             LEFT JOIN patients p ON p.id = u.patient_id
             ORDER BY FIELD(u.role, "admin", "psychologist", "assistant", "patient"), u.full_name'
        )));
    }

    public function storeUser(Request $request): void
    {
        $this->validate($request, [
            'full_name' => 'required|max:160',
            'username' => 'required|max:60',
            'email' => 'required|email|max:180',
            'password' => 'required|min:10',
            'role' => 'required|in:' . implode(',', array_keys(self::ROLES)),
            'license_number' => 'max:60',
            'specialty' => 'max:120',
            'document_number' => 'max:20',
        ]);

        $exists = Database::first(
            'SELECT id FROM users WHERE username = :username OR email = :email',
            ['username' => $request->string('username'), 'email' => $request->string('email')]
        );

        if ($exists !== null) {
            throw HttpException::conflict('A user with that username or email already exists.');
        }

        $password = (string) $request->input('password');
        if (($problem = Auth::passwordProblem($password)) !== null) {
            throw HttpException::unprocessable('Please choose another password.', ['password' => $problem]);
        }

        $id = Database::insert('users', [
            'uuid' => uuid(),
            'username' => $request->string('username'),
            'email' => $request->string('email'),
            'password_hash' => Auth::hashPassword($password),
            'full_name' => $request->string('full_name'),
            'role' => $request->string('role'),
            'license_number' => $request->string('license_number'),
            'specialty' => $request->string('specialty'),
        ] + self::documentFields($request));
        AuditLog::record('create', 'user', $id);

        $this->created(['id' => $id], 'User created.');
    }

    /** Public profile shown on the site, plus the ID document RIPS reports for each consultation. */
    public function updateUser(Request $request, string $id): void
    {
        $user = $this->abortIfMissing(Database::first('SELECT * FROM users WHERE id = :id', ['id' => (int) $id]), "We couldn't find this user.");

        if ($user['role'] === 'patient') {
            throw HttpException::unprocessable('Patient accounts do not have a public profile.');
        }

        $this->validate($request, ['specialty' => 'max:120', 'public_bio' => 'max:400', 'license_number' => 'max:60', 'document_number' => 'max:20']);

        Database::update('users', (int) $user['id'], [
            'show_on_site' => $request->bool('show_on_site') ? 1 : 0,
            'specialty' => $request->string('specialty'),
            'license_number' => $request->string('license_number'),
            'public_bio' => $request->string('public_bio') ?: null,
        ] + self::documentFields($request));
        AuditLog::record('update:public', 'user', (int) $user['id']);

        $this->message('Professional profile updated.');
    }

    public function toggleUser(Request $request, string $id): void
    {
        $user = $this->abortIfMissing(Database::first('SELECT * FROM users WHERE id = :id', ['id' => (int) $id]), "We couldn't find this user.");

        if ((int) $user['id'] === Auth::id()) {
            throw HttpException::conflict("You can't deactivate your own account.");
        }

        $active = (int) $user['is_active'] === 1 ? 0 : 1;
        Database::update('users', (int) $user['id'], ['is_active' => $active]);
        AuditLog::record('toggle', 'user', (int) $user['id']);

        $this->message($active === 1 ? 'User activated.' : 'User deactivated.', ['is_active' => (bool) $active]);
    }

    public function audit(Request $request): void
    {
        $this->ok(Present::rows(AuditLog::recent(150), ['user_agent']));
    }

    /**
     * ID document of a professional (RIPS fields C15-C16). Only applied when
     * the request sends it, so other forms leave it untouched.
     */
    public static function documentFields(Request $request): array
    {
        if (!$request->has('document_number')) {
            return [];
        }

        return [
            'document_type' => array_key_exists($request->string('document_type'), Rips::DOCUMENT_TYPES) ? $request->string('document_type') : 'CC',
            'document_number' => mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $request->string('document_number')) ?? '', 0, 20) ?: null,
        ];
    }
}
