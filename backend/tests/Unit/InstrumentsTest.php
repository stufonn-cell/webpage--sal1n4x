<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Domain\Instruments;
use PsiClinic\Tests\TestCase;

final class InstrumentsTest extends TestCase
{
    public function testCatalogExposesTheSixInstruments(): void
    {
        $codes = Instruments::codes();

        $this->assertCount(6, $codes);

        foreach (['PHQ-9', 'GAD-7', 'DASS-21', 'PSS-10', 'RSES', 'WHO-5'] as $code) {
            $this->assertNotNull(Instruments::get($code), 'Falta el instrumento ' . $code);
        }
    }

    public function testUnknownInstrumentReturnsNull(): void
    {
        $this->assertNull(Instruments::get('NO-EXISTE'));
    }

    public function testEveryInstrumentDeclaresTheExpectedShape(): void
    {
        foreach (Instruments::all() as $code => $instrument) {
            foreach (['name', 'domain', 'window', 'description', 'scale', 'items', 'bands'] as $key) {
                $this->assertArrayHasKey($key, $instrument, $code . ' sin la clave ' . $key);
            }

            $this->assertGreaterThan(0, count($instrument['items']), $code . ' sin items');
        }
    }

    public function testPhq9ScoresMinimumAndMaximum(): void
    {
        $minimum = Instruments::score('PHQ-9', array_fill(0, 9, 0));
        $maximum = Instruments::score('PHQ-9', array_fill(0, 9, 3));

        $this->assertSame(0, $minimum['total']);
        $this->assertSame('Minima', $minimum['severity']);
        $this->assertSame(27, $maximum['total']);
        $this->assertSame('Severa', $maximum['severity']);
    }

    public function testPhq9ClassifiesModerateSeverity(): void
    {
        $result = Instruments::score('PHQ-9', [2, 2, 1, 2, 1, 1, 1, 1, 0]);

        $this->assertSame(11, $result['total']);
        $this->assertSame('Moderada', $result['severity']);
    }

    public function testPhq9FlagsTheSuicidalIdeationItem(): void
    {
        $withoutRisk = Instruments::score('PHQ-9', [1, 1, 1, 1, 1, 1, 1, 1, 0]);
        $withRisk = Instruments::score('PHQ-9', [1, 1, 1, 1, 1, 1, 1, 1, 2]);

        $this->assertCount(0, $withoutRisk['alerts']);
        $this->assertCount(1, $withRisk['alerts']);
    }

    public function testGad7UsesItsOwnBands(): void
    {
        $this->assertSame('Minima', Instruments::score('GAD-7', array_fill(0, 7, 0))['severity']);
        $this->assertSame('Severa', Instruments::score('GAD-7', array_fill(0, 7, 3))['severity']);
        $this->assertSame(14, Instruments::score('GAD-7', [2, 2, 2, 2, 2, 2, 2])['total']);
    }

    public function testDass21AppliesTheMultiplierAndSplitsSubscales(): void
    {
        $result = Instruments::score('DASS-21', array_fill(0, 21, 1));

        $this->assertSame(42, $result['total'], 'Suma 21 por el multiplicador 2');
        $this->assertCount(3, $result['subscales']);

        foreach (['Depresion', 'Ansiedad', 'Estres'] as $subscale) {
            $this->assertArrayHasKey($subscale, $result['subscales']);
            $this->assertSame(14, $result['subscales'][$subscale]['score'], $subscale . ': 7 items x 1 x 2');
        }
    }

    public function testDass21IsolatesTheDepressionSubscale(): void
    {
        $answers = array_fill(0, 21, 0);
        foreach ([2, 4, 9, 12, 15, 16, 20] as $depressionIndex) {
            $answers[$depressionIndex] = 3;
        }

        $result = Instruments::score('DASS-21', $answers);

        $this->assertSame(42, $result['subscales']['Depresion']['score']);
        $this->assertSame(0, $result['subscales']['Ansiedad']['score']);
        $this->assertSame(0, $result['subscales']['Estres']['score']);
        $this->assertSame('Extremadamente severa', $result['subscales']['Depresion']['severity']);
    }

    public function testPss10InvertsThePositivelyWordedItems(): void
    {
        $allZero = Instruments::score('PSS-10', array_fill(0, 10, 0));

        $this->assertSame(16, $allZero['total'], 'Cuatro items inversos aportan 4 puntos cada uno');

        $allMaximum = Instruments::score('PSS-10', array_fill(0, 10, 4));

        $this->assertSame(24, $allMaximum['total'], 'Los seis items directos aportan 4 puntos cada uno');
    }

    public function testRsesInvertsFiveItems(): void
    {
        $answers = array_fill(0, 10, 0);
        $result = Instruments::score('RSES', $answers);

        $this->assertSame(15, $result['total'], 'Cinco items inversos aportan 3 puntos cada uno');
        $this->assertSame('Media', $result['severity']);
    }

    public function testWho5ConvertsToAnIndexOutOfOneHundred(): void
    {
        $result = Instruments::score('WHO-5', array_fill(0, 5, 5));

        $this->assertSame(100, $result['total']);
        $this->assertSame('Adecuado', $result['severity']);
        $this->assertSame(0, Instruments::score('WHO-5', array_fill(0, 5, 0))['total']);
    }

    public function testMaxScoreMatchesTheHighestPossibleAnswerSet(): void
    {
        $expected = [
            'PHQ-9' => 27,
            'GAD-7' => 21,
            'DASS-21' => 126,
            'PSS-10' => 40,
            'RSES' => 30,
            'WHO-5' => 100,
        ];

        foreach ($expected as $code => $maximum) {
            $this->assertSame($maximum, Instruments::maxScore($code), 'Maximo de ' . $code);
        }
    }

    public function testMissingAnswersAreTreatedAsZero(): void
    {
        $result = Instruments::score('GAD-7', [3, 3]);

        $this->assertSame(6, $result['total']);
    }

    public function testEveryPossibleScoreFallsInsideABand(): void
    {
        foreach (Instruments::all() as $code => $instrument) {
            $maximum = Instruments::maxScore($code);

            for ($score = 0; $score <= $maximum; $score++) {
                $matches = 0;
                foreach ($instrument['bands'] as $band) {
                    if ($score >= $band[0] && $score <= $band[1]) {
                        $matches++;
                    }
                }

                $this->assertSame(1, $matches, sprintf('%s: el puntaje %d no cae en exactamente una banda', $code, $score));
            }
        }
    }

    public function testScoringAnUnknownInstrumentIsSafe(): void
    {
        $result = Instruments::score('NO-EXISTE', [1, 2, 3]);

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['subscales']);
    }
}
