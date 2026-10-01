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
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Rips;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class SettingsController extends Controller
{
    public const ROLES = [
        'admin' => 'Administración',
        'psychologist' => 'Psicología clínica',
        'assistant' => 'Asistente',
    ];

    public function index(Request $request): void
    {
        $this->ok(Settings::all());
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
            'rips_obligado_documento' => 'max:15',
            'rips_cod_prestador' => 'max:12',
            'rips_cod_servicio' => 'numeric|max:4',
            'rips_numero_inicial' => 'numeric',
            'rips_finalidad_primera' => 'in:' . implode(',', array_keys(Rips::PURPOSES)),
            'rips_finalidad_control' => 'in:' . implode(',', array_keys(Rips::PURPOSES)),
            'rips_causa' => 'in:' . implode(',', array_keys(Rips::CAUSES)),
            'rips_ambiente' => 'in:pruebas,produccion',
            'rips_muv_url' => 'max:255',
        ]);

        $url = $request->string('rips_muv_url');
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw HttpException::unprocessable('La dirección del validador no es válida.', ['rips_muv_url' => 'Ejemplo: https://localhost:9443']);
        }
        if ($request->string('rips_cod_prestador') !== '' && !preg_match('/^\d{10,12}$/', $request->string('rips_cod_prestador'))) {
            throw HttpException::unprocessable('Revisa el código de habilitación.', ['rips_cod_prestador' => 'Son los 10 a 12 dígitos del código en el REPS.']);
        }

        foreach (array_keys(Settings::DEFAULTS) as $key) {
            if ($request->has($key)) {
                $value = $request->string($key);
                if ($key === 'whatsapp_number') {
                    $value = preg_replace('/\D+/', '', $value) ?? '';
                }
                Settings::put($key, $value);
            }
        }

        AuditLog::record('update', 'settings');
        $this->message('Configuración guardada.', Settings::all());
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
        ]);

        $exists = Database::first(
            'SELECT id FROM users WHERE username = :username OR email = :email',
            ['username' => $request->string('username'), 'email' => $request->string('email')]
        );

        if ($exists !== null) {
            throw HttpException::conflict('Ya existe un usuario con ese nombre de usuario o correo.');
        }

        $id = Database::insert('users', [
            'uuid' => uuid(),
            'username' => $request->string('username'),
            'email' => $request->string('email'),
            'password_hash' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            'full_name' => $request->string('full_name'),
            'role' => $request->string('role'),
            'license_number' => $request->string('license_number'),
            'specialty' => $request->string('specialty'),
        ]);
        AuditLog::record('create', 'user', $id);

        $this->created(['id' => $id], 'Usuario creado.');
    }

    /** Datos del perfil publico que aparece en el sitio. */
    public function updateUser(Request $request, string $id): void
    {
        $user = $this->abortIfMissing(Database::first('SELECT * FROM users WHERE id = :id', ['id' => (int) $id]), 'No encontramos este usuario.');

        if ($user['role'] === 'patient') {
            throw HttpException::unprocessable('Las cuentas de pacientes no tienen perfil público.');
        }

        $this->validate($request, ['specialty' => 'max:120', 'public_bio' => 'max:400', 'license_number' => 'max:60']);

        Database::update('users', (int) $user['id'], [
            'show_on_site' => $request->bool('show_on_site') ? 1 : 0,
            'specialty' => $request->string('specialty'),
            'license_number' => $request->string('license_number'),
            'public_bio' => $request->string('public_bio') ?: null,
        ]);
        AuditLog::record('update:public', 'user', (int) $user['id']);

        $this->message('Perfil público actualizado.');
    }

    public function toggleUser(Request $request, string $id): void
    {
        $user = $this->abortIfMissing(Database::first('SELECT * FROM users WHERE id = :id', ['id' => (int) $id]), 'No encontramos este usuario.');

        if ((int) $user['id'] === Auth::id()) {
            throw HttpException::conflict('No puedes desactivar tu propia cuenta.');
        }

        $active = (int) $user['is_active'] === 1 ? 0 : 1;
        Database::update('users', (int) $user['id'], ['is_active' => $active]);
        AuditLog::record('toggle', 'user', (int) $user['id']);

        $this->message($active === 1 ? 'Usuario activado.' : 'Usuario desactivado.', ['is_active' => (bool) $active]);
    }

    public function audit(Request $request): void
    {
        $this->ok(Present::rows(AuditLog::recent(150), ['user_agent']));
    }
}
