<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\App;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Csrf;
use PsiClinic\Core\Database;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Rips;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class AuthController extends Controller
{
    /** Estado inicial de la SPA: usuario, token CSRF y datos basicos. */
    public function session(Request $request): void
    {
        $this->ok($this->sessionPayload());
    }

    public function login(Request $request): void
    {
        $identifier = $request->string('identifier');
        $password = (string) $request->input('password', '');
        $ip = $request->ip();

        $errors = [];
        if ($identifier === '') {
            $errors['identifier'] = 'Escribe tu usuario o tu correo.';
        }
        if ($password === '') {
            $errors['password'] = 'Escribe tu contraseña.';
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('Completa tus datos de acceso.', $errors);
        }

        if (Auth::tooManyAttempts($identifier, $ip)) {
            throw new HttpException(429, 'Hubo demasiados intentos fallidos. Por seguridad, espera unos minutos antes de volver a intentarlo.');
        }

        if (!Auth::attempt($identifier, $password)) {
            Auth::recordFailure($identifier, $ip);
            throw HttpException::unprocessable('El usuario o la contraseña no coinciden. Revísalos e inténtalo de nuevo.');
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
        ]);

        $email = $request->string('email');
        $taken = Database::first(
            'SELECT id FROM users WHERE email = :email AND id <> :id',
            ['email' => $email, 'id' => (int) $user['id']]
        );
        if ($taken !== null) {
            throw HttpException::unprocessable('Ese correo ya está en uso.', ['email' => 'Ese correo ya pertenece a otra cuenta.']);
        }

        $data = [
            'full_name' => $request->string('full_name'),
            'email' => $email,
            'phone' => $request->string('phone'),
            'license_number' => $request->string('license_number'),
            'specialty' => $request->string('specialty'),
        ];

        // Documento del profesional: se informa en cada consulta del RIPS (C15-C16).
        if ($request->has('document_number')) {
            $data['document_type'] = array_key_exists($request->string('document_type'), Rips::DOCUMENT_TYPES) ? $request->string('document_type') : 'CC';
            $data['document_number'] = mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $request->string('document_number')) ?? '', 0, 20) ?: null;
        }

        $password = (string) $request->input('password', '');
        if ($password !== '') {
            if (!password_verify((string) $request->input('current_password', ''), (string) $user['password_hash'])) {
                throw HttpException::unprocessable('Para cambiar la contraseña confirma la actual.', [
                    'current_password' => 'La contraseña actual no es correcta.',
                ]);
            }
            if (mb_strlen($password) < 10) {
                throw HttpException::unprocessable('La nueva contraseña es muy corta.', [
                    'password' => 'Usa al menos 10 caracteres.',
                ]);
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', (int) $user['id'], $data);
        AuditLog::record($password !== '' ? 'update:password' : 'update', 'user', (int) $user['id']);
        Auth::refresh();

        $this->message('Tus datos quedaron guardados.', Present::user(Auth::user()));
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
            // Las cuentas de demostracion solo se anuncian en local.
            'demoAccounts' => App::isLocal() ? [
                ['role' => 'Administración', 'identifier' => 'admin', 'password' => 'Psiclinic2026'],
                ['role' => 'Psicóloga', 'identifier' => 'l.moreno', 'password' => 'Psiclinic2026'],
                ['role' => 'Paciente', 'identifier' => 'hc-2026-0001', 'password' => 'Paciente2026'],
            ] : [],
        ];
    }
}
