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
use PsiClinic\Core\Session;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Settings;

final class SettingsController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('settings/index', ['settings' => Settings::all()]);
    }

    public function update(Request $request): void
    {
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            if ($request->has($key)) {
                Settings::put($key, (string) $request->input($key, ''));
            }
        }

        AuditLog::record('update', 'settings');
        Session::flash('success', 'Configuracion guardada.');
        $this->redirect('/ajustes');
    }

    public function users(Request $request): void
    {
        $this->view('settings/users', [
            'users' => Database::all(
                'SELECT u.*, p.record_number
                 FROM users u
                 LEFT JOIN patients p ON p.id = u.patient_id
                 ORDER BY u.role, u.full_name'
            ),
        ]);
    }

    public function storeUser(Request $request): void
    {
        $this->validate($request, [
            'full_name' => 'required|max:160',
            'username' => 'required|max:60',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,psychologist,assistant',
        ]);

        $exists = Database::first(
            'SELECT id FROM users WHERE username = :username OR email = :email',
            ['username' => (string) $request->input('username'), 'email' => (string) $request->input('email')]
        );

        if ($exists !== null) {
            Session::flash('error', 'Ya existe un usuario con ese nombre de usuario o correo.');
            $this->redirect('/ajustes/usuarios');
        }

        $id = Database::insert('users', [
            'uuid' => uuid(),
            'username' => (string) $request->input('username'),
            'email' => (string) $request->input('email'),
            'password_hash' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            'full_name' => (string) $request->input('full_name'),
            'role' => (string) $request->input('role'),
            'license_number' => (string) $request->input('license_number', ''),
            'specialty' => (string) $request->input('specialty', ''),
        ]);

        AuditLog::record('create', 'user', $id);
        Session::flash('success', 'Usuario creado.');
        $this->redirect('/ajustes/usuarios');
    }

    public function toggleUser(Request $request, string $id): void
    {
        $user = $this->abortIfMissing(Database::first('SELECT * FROM users WHERE id = :id', ['id' => (int) $id]));

        Database::update('users', (int) $user['id'], ['is_active' => (int) $user['is_active'] === 1 ? 0 : 1]);
        AuditLog::record('toggle', 'user', (int) $user['id']);

        Session::flash('success', 'Estado del usuario actualizado.');
        $this->redirect('/ajustes/usuarios');
    }

    public function audit(Request $request): void
    {
        $this->view('settings/audit', ['entries' => AuditLog::recent(150)]);
    }
}
