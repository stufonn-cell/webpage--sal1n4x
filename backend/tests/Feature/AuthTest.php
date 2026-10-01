<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Csrf;
use PsiClinic\Core\Database;

final class AuthTest extends FeatureTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function testPasswordsAreNeverStoredInPlainText(): void
    {
        $this->createUser('admin', 'ana');

        $hash = (string) Database::value('SELECT password_hash FROM users WHERE username = :u', ['u' => 'ana']);

        $this->assertFalse(str_contains($hash, 'Clave12345'));
        $this->assertTrue(password_verify('Clave12345', $hash));
    }

    public function testLoginSucceedsWithTheUsername(): void
    {
        $id = $this->createUser('psychologist', 'l.moreno');

        $this->assertTrue(Auth::attempt('l.moreno', 'Clave12345'));
        $this->assertSame($id, Auth::id());
    }

    public function testLoginAlsoAcceptsTheEmail(): void
    {
        $this->createUser('psychologist', 'l.moreno');
        Auth::logout();

        $this->assertTrue(Auth::attempt('l.moreno@psiclinic.test', 'Clave12345'));
    }

    public function testLoginFailsWithTheWrongPassword(): void
    {
        $this->createUser('psychologist', 'l.moreno');
        Auth::logout();

        $this->assertFalse(Auth::attempt('l.moreno', 'incorrecta'));
        $this->assertNull(Auth::id());
    }

    public function testInactiveUsersCannotLogIn(): void
    {
        $id = $this->createUser('assistant', 'inactivo');
        Database::update('users', $id, ['is_active' => 0]);
        Auth::logout();

        $this->assertFalse(Auth::attempt('inactivo', 'Clave12345'));
    }

    public function testTheLastLoginTimestampIsStored(): void
    {
        $id = $this->createUser('admin', 'registro');
        Auth::logout();
        Auth::attempt('registro', 'Clave12345');

        $this->assertNotNull(Database::value('SELECT last_login_at FROM users WHERE id = :id', ['id' => $id]));
    }

    public function testLoginIsRecordedInTheAuditLog(): void
    {
        $this->createUser('admin', 'auditado');
        Auth::logout();
        Auth::attempt('auditado', 'Clave12345');

        $this->assertSame(
            1,
            (int) Database::value('SELECT COUNT(*) FROM audit_log WHERE action = "login"')
        );
    }

    public function testRolesDrivePermissionChecks(): void
    {
        $this->createUser('assistant', 'asistente');
        Auth::logout();
        Auth::attempt('asistente', 'Clave12345');

        $this->assertSame('assistant', Auth::role());
        $this->assertTrue(Auth::isStaff());
        $this->assertFalse(Auth::is('admin'));
        $this->assertTrue(Auth::is('assistant', 'admin'));
    }

    public function testPatientAccountsAreNotStaff(): void
    {
        $patientId = $this->createPatient();
        $userId = $this->createUser('patient', 'hc-test');
        Database::update('users', $userId, ['patient_id' => $patientId]);

        Auth::logout();
        Auth::attempt('hc-test', 'Clave12345');

        $this->assertFalse(Auth::isStaff());
        $this->assertTrue(Auth::is('patient'));
    }

    public function testFailedAttemptsAreThrottled(): void
    {
        $this->createUser('admin', 'bloqueado');
        Auth::logout();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            Auth::attempt('bloqueado', 'mala');
            Auth::recordFailure('bloqueado');
        }

        $this->assertTrue(Auth::tooManyAttempts('bloqueado'));

        Auth::clearAttempts('bloqueado');

        $this->assertFalse(Auth::tooManyAttempts('bloqueado'));
    }

    public function testLogoutClearsTheSession(): void
    {
        $this->createUser('admin', 'salida');
        Auth::logout();
        Auth::attempt('salida', 'Clave12345');

        $this->assertNotNull(Auth::id());

        Auth::logout();

        $this->assertNull(Auth::id());
        $this->assertFalse(Auth::check());
    }

    public function testCsrfTokensAreComparedInConstantTime(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::verify($token));
        $this->assertFalse(Csrf::verify('token-falso'));
        $this->assertFalse(Csrf::verify(null));
        $this->assertSame($token, Csrf::token(), 'El token se reutiliza dentro de la misma sesion');
    }

    public function testThrottlingSurvivesANewSession(): void
    {
        $this->createUser('admin', 'persistente');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            Auth::recordFailure('persistente', '10.0.0.8');
        }

        $_SESSION = [];

        $this->assertTrue(Auth::tooManyAttempts('persistente'), 'Borrar la sesion no debe reiniciar el contador');
    }

    public function testTheCsrfTokenRotatesOnLogin(): void
    {
        $this->createUser('admin', 'rotacion');
        Auth::logout();
        $before = Csrf::token();

        Auth::attempt('rotacion', 'Clave12345');

        $this->assertFalse($before === Csrf::token(), 'Iniciar sesion debe emitir un token nuevo');
    }
}
