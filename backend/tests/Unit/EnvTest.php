<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
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
        # Initial comment
        APP_NAME="PsiClinic QA"
        APP_DEBUG=true
        SESSION_SECURE=false
        SESSION_LIFETIME=7200        # seconds
        EMPTY_VALUE=
        SINGLE_QUOTED='quoted value'
        NOT_A_PAIR
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
        $this->assertSame('quoted value', Env::get('SINGLE_QUOTED'));
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
        $this->assertSame('fallback', Env::get('EMPTY_VALUE', 'fallback'));
        $this->assertSame('fallback', Env::get('NOT_DEFINED', 'fallback'));
        $this->assertSame(50, Env::int('NOT_DEFINED', 50));
    }

    public function testLinesWithoutAnEqualsSignAreIgnored(): void
    {
        $this->assertNull(Env::get('NOT_A_PAIR'));
    }

    public function testIntFallsBackWhenTheValueIsNotNumeric(): void
    {
        $this->assertSame(10, Env::int('APP_NAME', 10));
    }

    public function testValuesCanBeOverriddenAtRuntime(): void
    {
        Env::set('APP_NAME', 'Another name');

        $this->assertSame('Another name', Env::get('APP_NAME'));
    }

    public function testLoadingAMissingFileDoesNotFail(): void
    {
        Env::set('APP_NAME', 'Another name');
        Env::load('/path/that/does/not/exist/.env');

        $this->assertSame('Another name', Env::get('APP_NAME'));
    }
}
