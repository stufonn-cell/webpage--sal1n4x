<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Tests\TestCase;

final class HelpersTest extends TestCase
{
    public function testEscapesHtmlAndQuotes(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
        $this->assertSame('Mar&#039;a &amp; Juan', e("Mar'a & Juan"));
        $this->assertSame('', e(null));
    }

    public function testInitialsTakeTheFirstTwoWords(): void
    {
        $this->assertSame('MV', initials('Mariana Vega'));
        $this->assertSame('LM', initials('Laura Moreno Diaz'));
        $this->assertSame('A', initials('Ana'));
        $this->assertSame('', initials('   '));
    }

    public function testAgeIsCalculatedInFullYears(): void
    {
        $thirtyYearsAgo = date('Y-m-d', strtotime('-30 years'));
        $almostOne = date('Y-m-d', strtotime('-11 months'));

        $this->assertSame('30', ageFrom($thirtyYearsAgo));
        $this->assertSame('0', ageFrom($almostOne));
        $this->assertSame('-', ageFrom(null));
        $this->assertSame('-', ageFrom(''));
    }

    public function testDatesAreFormattedForReading(): void
    {
        $this->assertSame('18/03/1994', formatDate('1994-03-18'));
        $this->assertSame('18/03/1994 09:30', formatDateTime('1994-03-18 09:30:00'));
        $this->assertSame('-', formatDate(null));
        $this->assertSame('-', formatDate('0000-00-00'));
    }

    public function testMoneyUsesThousandSeparators(): void
    {
        $this->assertSame('120.000,00', formatMoney(120000));
        $this->assertSame('0,00', formatMoney(null));
        $this->assertSame('1.234.567,89', formatMoney(1234567.891));
    }

    public function testUrlAlwaysStartsWithASingleSlash(): void
    {
        $this->assertSame('/pacientes', url('pacientes'));
        $this->assertSame('/pacientes', url('/pacientes'));
        $this->assertSame('/', url());
    }

    public function testUuidHasTheVersionFourShape(): void
    {
        $value = uuid();

        $this->assertSame(36, strlen($value));
        $this->assertSame('4', $value[14], 'El digito de version debe ser 4');
        $this->assertTrue(in_array($value[19], ['8', '9', 'a', 'b'], true), 'Variante RFC 4122');
        $this->assertTrue(uuid() !== uuid(), 'Cada llamada debe producir un valor distinto');
    }

    public function testMethodFieldRendersAHiddenInput(): void
    {
        $this->assertContains('name="_method"', method('delete'));
        $this->assertContains('value="DELETE"', method('delete'));
    }
}
