<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
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

        $this->assertFalse(str_contains($hash, 'Password1234'));
        $this->assertTrue(password_verify('Password1234', $hash));
    }

    public function testLoginSucceedsWithTheUsername(): void
    {
        $id = $this->createUser('psychologist', 'l.moreno');

        $this->assertTrue(Auth::attempt('l.moreno', 'Password1234'));
        $this->assertSame($id, Auth::id());
    }

    public function testLoginAlsoAcceptsTheEmail(): void
    {
        $this->createUser('psychologist', 'l.moreno');
        Auth::logout();

        $this->assertTrue(Auth::attempt('l.moreno@psiclinic.test', 'Password1234'));
    }

    public function testLoginFailsWithTheWrongPassword(): void
    {
        $this->createUser('psychologist', 'l.moreno');
        Auth::logout();

        $this->assertFalse(Auth::attempt('l.moreno', 'incorrect'));
        $this->assertNull(Auth::id());
    }

    public function testInactiveUsersCannotLogIn(): void
    {
        $id = $this->createUser('assistant', 'inactive');
        Database::update('users', $id, ['is_active' => 0]);
        Auth::logout();

        $this->assertFalse(Auth::attempt('inactive', 'Password1234'));
    }

    public function testTheLastLoginTimestampIsStored(): void
    {
        $id = $this->createUser('admin', 'tracked');
        Auth::logout();
        Auth::attempt('tracked', 'Password1234');

        $this->assertNotNull(Database::value('SELECT last_login_at FROM users WHERE id = :id', ['id' => $id]));
    }

    public function testLoginIsRecordedInTheAuditLog(): void
    {
        $this->createUser('admin', 'audited');
        Auth::logout();
        Auth::attempt('audited', 'Password1234');

        $this->assertSame(
            1,
            (int) Database::value('SELECT COUNT(*) FROM audit_log WHERE action = "login"')
        );
    }

    public function testRolesDrivePermissionChecks(): void
    {
        $this->createUser('assistant', 'assistant');
        Auth::logout();
        Auth::attempt('assistant', 'Password1234');

        $this->assertSame('assistant', Auth::role());
        $this->assertTrue(Auth::isStaff());
        $this->assertFalse(Auth::is('admin'));
        $this->assertTrue(Auth::is('assistant', 'admin'));
    }

    public function testPatientAccountsAreNotStaff(): void
    {
        $patientId = $this->createPatient();
        $userId = $this->createUser('patient', 'mr-test');
        Database::update('users', $userId, ['patient_id' => $patientId]);

        Auth::logout();
        Auth::attempt('mr-test', 'Password1234');

        $this->assertFalse(Auth::isStaff());
        $this->assertTrue(Auth::is('patient'));
    }

    public function testFailedAttemptsAreThrottled(): void
    {
        $this->createUser('admin', 'locked');
        Auth::logout();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            Auth::attempt('locked', 'wrong');
            Auth::recordFailure('locked');
        }

        $this->assertTrue(Auth::tooManyAttempts('locked'));

        Auth::clearAttempts('locked');

        $this->assertFalse(Auth::tooManyAttempts('locked'));
    }

    public function testLogoutClearsTheSession(): void
    {
        $this->createUser('admin', 'leaving');
        Auth::logout();
        Auth::attempt('leaving', 'Password1234');

        $this->assertNotNull(Auth::id());

        Auth::logout();

        $this->assertNull(Auth::id());
        $this->assertFalse(Auth::check());
    }

    public function testCsrfTokensAreComparedInConstantTime(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::verify($token));
        $this->assertFalse(Csrf::verify('fake-token'));
        $this->assertFalse(Csrf::verify(null));
        $this->assertSame($token, Csrf::token(), 'The token is reused within the same session');
    }

    public function testThrottlingSurvivesANewSession(): void
    {
        $this->createUser('admin', 'persistent');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            Auth::recordFailure('persistent', '10.0.0.8');
        }

        $_SESSION = [];

        $this->assertTrue(Auth::tooManyAttempts('persistent'), 'Clearing the session must not reset the counter');
    }

    public function testTheCsrfTokenRotatesOnLogin(): void
    {
        $this->createUser('admin', 'rotation');
        Auth::logout();
        $before = Csrf::token();

        Auth::attempt('rotation', 'Password1234');

        $this->assertFalse($before === Csrf::token(), 'Signing in must issue a new token');
    }
}
