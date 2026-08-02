<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Core\Validator;
use PsiClinic\Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredRejectsEmptyValues(): void
    {
        $validator = (new Validator(['name' => '  ']))->validate(['name' => 'required']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function testRequiredAcceptsFilledValues(): void
    {
        $validator = (new Validator(['name' => 'Mariana']))->validate(['name' => 'required']);

        $this->assertFalse($validator->fails());
    }

    public function testEmailRuleChecksTheFormat(): void
    {
        $invalid = (new Validator(['email' => 'correo-invalido']))->validate(['email' => 'email']);
        $valid = (new Validator(['email' => 'paciente@example.com']))->validate(['email' => 'email']);

        $this->assertTrue($invalid->fails());
        $this->assertFalse($valid->fails());
    }

    public function testOptionalFieldsAreSkippedWhenEmpty(): void
    {
        $validator = (new Validator(['email' => '']))->validate(['email' => 'email']);

        $this->assertFalse($validator->fails(), 'Un campo opcional vacio no debe fallar');
    }

    public function testMinAndMaxMeasureLength(): void
    {
        $tooShort = (new Validator(['password' => '123']))->validate(['password' => 'min:8']);
        $tooLong = (new Validator(['code' => str_repeat('a', 30)]))->validate(['code' => 'max:20']);
        $exact = (new Validator(['password' => '12345678']))->validate(['password' => 'min:8|max:8']);

        $this->assertTrue($tooShort->fails());
        $this->assertTrue($tooLong->fails());
        $this->assertFalse($exact->fails());
    }

    public function testInRuleRestrictsTheAllowedValues(): void
    {
        $rejected = (new Validator(['role' => 'superuser']))->validate(['role' => 'in:admin,psychologist']);
        $accepted = (new Validator(['role' => 'psychologist']))->validate(['role' => 'in:admin,psychologist']);

        $this->assertTrue($rejected->fails());
        $this->assertFalse($accepted->fails());
    }

    public function testConfirmedRuleComparesTheTwinField(): void
    {
        $mismatch = (new Validator([
            'password' => 'secreto123',
            'password_confirmation' => 'otro',
        ]))->validate(['password' => 'confirmed']);

        $match = (new Validator([
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ]))->validate(['password' => 'confirmed']);

        $this->assertTrue($mismatch->fails());
        $this->assertFalse($match->fails());
    }

    public function testNumericAndDateRules(): void
    {
        $this->assertTrue((new Validator(['fee' => 'gratis']))->validate(['fee' => 'numeric'])->fails());
        $this->assertFalse((new Validator(['fee' => '120000']))->validate(['fee' => 'numeric'])->fails());
        $this->assertTrue((new Validator(['born' => '31/31/2026']))->validate(['born' => 'date'])->fails());
        $this->assertFalse((new Validator(['born' => '1994-03-18']))->validate(['born' => 'date'])->fails());
    }

    public function testOnlyTheFirstErrorPerFieldIsKept(): void
    {
        $validator = (new Validator(['email' => '']))->validate(['email' => 'required|email|min:5']);

        $this->assertCount(1, $validator->errors());
    }

    public function testSeveralFieldsAreValidatedTogether(): void
    {
        $validator = (new Validator([
            'first_name' => '',
            'email' => 'no-es-correo',
            'status' => 'active',
        ]))->validate([
            'first_name' => 'required',
            'email' => 'required|email',
            'status' => 'required|in:active,on_hold,discharged',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertCount(2, $validator->errors());
        $this->assertArrayHasKey('first_name', $validator->errors());
        $this->assertArrayHasKey('email', $validator->errors());
    }
}
