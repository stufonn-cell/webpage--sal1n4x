<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Core\Env;
use PsiClinic\Tests\TestCase;

final class EnvTest extends TestCase
{
    private string $file = '';

    public function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/psiclinic-env-test';

        file_put_contents($this->file, <<<'DOTENV'
        # Comentario inicial
        APP_NAME="PsiClinic QA"
        APP_DEBUG=true
        SESSION_SECURE=false
        SESSION_LIFETIME=7200        # segundos
        EMPTY_VALUE=
        SINGLE_QUOTED='valor citado'
        NO_ES_PAR
        DOTENV);

        Env::load($this->file);
    }

    public function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testQuotesAreRemoved(): void
    {
        $this->assertSame('PsiClinic QA', Env::get('APP_NAME'));
        $this->assertSame('valor citado', Env::get('SINGLE_QUOTED'));
    }

    public function testInlineCommentsAreStripped(): void
    {
        $this->assertSame(7200, Env::int('SESSION_LIFETIME'));
    }

    public function testBooleanStringsAreCast(): void
    {
        $this->assertTrue(Env::bool('APP_DEBUG'));
        $this->assertFalse(Env::bool('SESSION_SECURE'));
    }

    public function testEmptyAndMissingValuesUseTheDefault(): void
    {
        $this->assertSame('respaldo', Env::get('EMPTY_VALUE', 'respaldo'));
        $this->assertSame('respaldo', Env::get('NO_DEFINIDA', 'respaldo'));
        $this->assertSame(50, Env::int('NO_DEFINIDA', 50));
    }

    public function testLinesWithoutAnEqualsSignAreIgnored(): void
    {
        $this->assertNull(Env::get('NO_ES_PAR'));
    }

    public function testIntFallsBackWhenTheValueIsNotNumeric(): void
    {
        $this->assertSame(10, Env::int('APP_NAME', 10));
    }

    public function testValuesCanBeOverriddenAtRuntime(): void
    {
        Env::set('APP_NAME', 'Otro nombre');

        $this->assertSame('Otro nombre', Env::get('APP_NAME'));
    }

    public function testLoadingAMissingFileDoesNotFail(): void
    {
        Env::set('APP_NAME', 'Otro nombre');
        Env::load('/ruta/que/no/existe/.env');

        $this->assertSame('Otro nombre', Env::get('APP_NAME'));
    }
}
