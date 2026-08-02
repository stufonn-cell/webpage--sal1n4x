<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Domain\Settings;

final class AuthController extends Controller
{
    public function root(Request $request): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $this->redirect(Auth::isStaff() ? '/dashboard' : '/portal');
    }

    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::isStaff() ? '/dashboard' : '/portal');
        }

        $this->view('auth/login', ['settings' => Settings::all()], 'blank');
    }

    public function login(Request $request): void
    {
        $identifier = (string) $request->input('identifier', '');
        $password = (string) $request->input('password', '');

        if ($identifier === '' || $password === '') {
            Session::flash('error', 'Ingresa tu usuario y tu contrasena.');
            $this->redirect('/login');
        }

        if (Auth::tooManyAttempts($identifier)) {
            Session::flash('error', 'Demasiados intentos fallidos. Espera unos minutos antes de reintentar.');
            $this->redirect('/login');
        }

        if (!Auth::attempt($identifier, $password)) {
            Auth::recordFailure($identifier);
            Session::flash('error', 'Credenciales incorrectas.');
            $this->redirect('/login');
        }

        Auth::clearAttempts($identifier);
        Session::flash('success', 'Sesion iniciada correctamente.');
        $this->redirect(Auth::isStaff() ? '/dashboard' : '/portal');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $this->redirect('/login');
    }

    public function profile(Request $request): void
    {
        $this->view('settings/profile', ['user' => Auth::user()]);
    }

    public function updateProfile(Request $request): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $this->validate($request, [
            'full_name' => 'required|max:160',
            'email' => 'required|email|max:180',
        ]);

        $data = [
            'full_name' => (string) $request->input('full_name'),
            'email' => (string) $request->input('email'),
            'phone' => (string) $request->input('phone', ''),
            'license_number' => (string) $request->input('license_number', ''),
            'specialty' => (string) $request->input('specialty', ''),
        ];

        $password = (string) $request->input('password', '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                Session::flash('error', 'La contrasena debe tener al menos 8 caracteres.');
                $this->redirect('/perfil');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', (int) $user['id'], $data);
        Session::flash('success', 'Perfil actualizado.');
        $this->redirect('/perfil');
    }

    public function toggleTheme(Request $request): void
    {
        $user = Auth::user();
        $theme = (string) $request->input('theme', 'light') === 'dark' ? 'dark' : 'light';

        if ($user !== null) {
            Database::update('users', (int) $user['id'], ['theme' => $theme]);
        }

        Session::put('theme', $theme);
        $this->json(['theme' => $theme]);
    }
}
