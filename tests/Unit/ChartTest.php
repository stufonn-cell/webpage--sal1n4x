<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Support\Chart;
use PsiClinic\Support\Icons;
use PsiClinic\Tests\TestCase;

final class ChartTest extends TestCase
{
    public function testBarsRenderOneRectanglePerValue(): void
    {
        $svg = Chart::bars(['2026-01' => 4, '2026-02' => 9, '2026-03' => 2]);

        $this->assertContains('<svg', $svg);
        $this->assertSame(3, substr_count($svg, '<rect'));
        $this->assertContains('Ene', $svg, 'Las etiquetas de mes se abrevian');
    }

    public function testChartsFallBackToAPlaceholderWithoutData(): void
    {
        $this->assertContains('Sin datos suficientes', Chart::bars([]));
        $this->assertContains('Sin datos suficientes', Chart::line([], 10));
        $this->assertContains('Sin datos suficientes', Chart::donut(['a' => 0], ['#000']));
    }

    public function testLineRendersOneDotPerPoint(): void
    {
        $svg = Chart::line([
            ['label' => '01/01/2026', 'value' => 12],
            ['label' => '15/01/2026', 'value' => 8],
            ['label' => '01/02/2026', 'value' => 5],
        ], 27);

        $this->assertSame(3, substr_count($svg, '<circle'));
        $this->assertContains('<path', $svg);
    }

    public function testLineHandlesASinglePoint(): void
    {
        $svg = Chart::line([['label' => 'hoy', 'value' => 3]], 27);

        $this->assertContains('<circle', $svg);
    }

    public function testLineNeverExceedsTheChartArea(): void
    {
        $svg = Chart::line([['label' => 'x', 'value' => 999]], 10);

        $this->assertContains('<svg', $svg, 'Un valor fuera de rango no debe romper el trazado');
    }

    public function testDonutDrawsOneArcPerNonEmptySlice(): void
    {
        $svg = Chart::donut(['Sin riesgo' => 5, 'Bajo' => 2, 'Alto' => 0], ['#1', '#2', '#3']);

        $this->assertSame(2, substr_count($svg, '<circle'), 'Las porciones en cero se omiten');
        $this->assertContains('>7<', $svg, 'Muestra el total en el centro');
    }

    public function testGaugeChoosesColourBySeverity(): void
    {
        $this->assertContains('#17a673', Chart::gauge(2, 27, 'de 27'));
        $this->assertContains('#d98207', Chart::gauge(14, 27, 'de 27'));
        $this->assertContains('#d94848', Chart::gauge(25, 27, 'de 27'));
    }

    public function testGaugeNeverDividesByZero(): void
    {
        $this->assertContains('<svg', Chart::gauge(0, 0, 'sin escala'));
    }

    public function testLabelsAreEscapedInsideTheSvg(): void
    {
        $svg = Chart::donut(['<script>' => 3], ['#000']);

        $this->assertFalse(str_contains($svg, '<script>'), 'El contenido debe escaparse');
    }

    public function testEveryIconIsValidInlineSvg(): void
    {
        $svg = Icons::render('patients');

        $this->assertContains('<svg', $svg);
        $this->assertContains('viewBox="0 0 24 24"', $svg);
        $this->assertContains('fill="currentColor"', $svg);
    }

    public function testUnknownIconsFallBackInsteadOfBreaking(): void
    {
        $this->assertContains('<svg', Icons::render('icono-inexistente'));
    }
}
