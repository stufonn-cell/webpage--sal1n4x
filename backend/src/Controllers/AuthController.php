<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\App;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Csrf;
use PsiClinic\Core\Database;
use PsiClinic\Core\Env;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class AuthController extends Controller
{
    private const WRONG_CREDENTIALS = "The username and password don't match. Check them and try again.";

    /** Initial state of the SPA: user, CSRF token and basic practice data. */
    public function session(Request $request): void
    {
        $this->ok($this->sessionPayload());
    }

    public function login(Request $request): void
    {
        $identifier = $request->string('identifier');
        $rawPassword = $request->input('password', '');
        $password = is_string($rawPassword) ? $rawPassword : '';
        $ip = $request->ip();

        $errors = [];
        if ($identifier === '') {
            $errors['identifier'] = 'Enter your username or email.';
        }
        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('Fill in your sign-in details.', $errors);
        }

        // Absurdly long values cannot belong to an account: same answer as a
        // wrong password, without touching the database or the hash function.
        if (mb_strlen($identifier) > Auth::MAX_IDENTIFIER_LENGTH || strlen($password) > Auth::MAX_PASSWORD_LENGTH) {
            throw HttpException::unprocessable(self::WRONG_CREDENTIALS);
        }

        if (Auth::tooManyAttempts($identifier, $ip)) {
            throw HttpException::tooManyRequests(
                'There were too many failed attempts. For your security, wait a few minutes before trying again.',
                Env::int('LOGIN_LOCKOUT_SECONDS', 900)
            );
        }

        if (!Auth::attempt($identifier, $password)) {
            Auth::recordFailure($identifier, $ip);
            // One message for unknown users, inactive users and wrong
            // passwords: the answer never reveals which accounts exist.
            throw HttpException::unprocessable(self::WRONG_CREDENTIALS);
        }

        Auth::clearAttempts($identifier);
        $this->ok($this->sessionPayload());
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::start();

        $this->ok(['csrfToken' => Csrf::token()]);
    }

    public function profile(Request $request): void
    {
        $this->ok(Present::user(Auth::user()));
    }

    public function updateProfile(Request $request): void
    {
        $user = (array) Auth::user();

        $this->validate($request, [
            'full_name' => 'required|max:160',
            'email' => 'required|email|max:180',
            'phone' => 'max:40',
            'license_number' => 'max:60',
            'specialty' => 'max:120',
            'document_number' => 'max:20',
        ]);

        $email = $request->string('email');
        $taken = Database::first(
            'SELECT id FROM users WHERE email = :email AND id <> :id',
            ['email' => $email, 'id' => (int) $user['id']]
        );
        if ($taken !== null) {
            throw HttpException::unprocessable('That email is already in use.', ['email' => 'That email belongs to another account.']);
        }

        $data = [
            'full_name' => $request->string('full_name'),
            'email' => $email,
            'phone' => $request->string('phone'),
            'license_number' => $request->string('license_number'),
            'specialty' => $request->string('specialty'),
        ];

        // The professional's ID document is reported with every RIPS consultation (C15-C16).
        if ($user['role'] !== 'patient') {
            $data += SettingsController::documentFields($request);
        }

        $rawPassword = $request->input('password', '');
        $password = is_string($rawPassword) ? $rawPassword : '';
        if ($password !== '') {
            $current = $request->input('current_password', '');
            if (!is_string($current) || !password_verify($current, (string) $user['password_hash'])) {
                throw HttpException::unprocessable('To change your password, confirm the current one.', [
                    'current_password' => 'The current password is not correct.',
                ]);
            }
            $problem = Auth::passwordProblem($password);
            if ($problem !== null) {
                throw HttpException::unprocessable('Please choose another new password.', [
                    'password' => $problem,
                ]);
            }
            $data['password_hash'] = Auth::hashPassword($password);
        }

        Database::update('users', (int) $user['id'], $data);
        AuditLog::record($password !== '' ? 'update:password' : 'update', 'user', (int) $user['id']);

        if ($password !== '') {
            // New session id for this browser; every other session of the
            // account ends on its next request.
            Auth::passwordChanged();
        } else {
            Auth::refresh();
        }

        $this->message('Your details were saved.', Present::user(Auth::user()));
    }

    public function updateTheme(Request $request): void
    {
        $theme = $request->string('theme') === 'dark' ? 'dark' : 'light';
        Database::update('users', (int) Auth::id(), ['theme' => $theme]);

        $this->ok(['theme' => $theme]);
    }

    private function sessionPayload(): array
    {
        return [
            'user' => Present::user(Auth::user()),
            'csrfToken' => Csrf::token(),
            'clinic' => [
                'name' => Settings::get('clinic_name', 'PsiClinic'),
                'tagline' => Settings::get('clinic_tagline'),
            ],
            // Demo accounts are only announced locally.
            'demoAccounts' => App::isLocal() ? $this->demoAccounts() : [],
        ];
    }

    private function demoAccounts(): array
    {
        // The demo patient's username is its record number, which carries the seeding year.
        $patient = Database::value('SELECT username FROM users WHERE role = "patient" ORDER BY id LIMIT 1');

        return array_values(array_filter([
            ['role' => 'Administrator', 'identifier' => 'admin', 'password' => 'Psiclinic2026'],
            ['role' => 'Psychologist', 'identifier' => 'l.moreno', 'password' => 'Psiclinic2026'],
            is_string($patient) ? ['role' => 'Patient', 'identifier' => $patient, 'password' => 'Patient2026'] : null,
        ]));
    }
}
