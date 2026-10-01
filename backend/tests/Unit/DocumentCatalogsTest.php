<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Domain\ConsentTemplates;
use PsiClinic\Domain\DocumentLanguage;
use PsiClinic\Domain\Instruments;
use PsiClinic\Tests\TestCase;

/**
 * Documents can be produced in English or Spanish. Both versions must say the
 * same thing and, for instruments, score exactly the same.
 */
final class DocumentCatalogsTest extends TestCase
{
    public function testTheSupportedLanguagesAreEnglishAndSpanish(): void
    {
        $this->assertSame(['en', 'es'], array_keys(DocumentLanguage::LANGUAGES));
        $this->assertTrue(DocumentLanguage::isValid('es'));
        $this->assertFalse(DocumentLanguage::isValid('fr'));
    }

    public function testEveryConsentTemplateExistsInBothLanguages(): void
    {
        $english = ConsentTemplates::all('en');
        $spanish = ConsentTemplates::all('es');

        $this->assertSame(array_keys($english), array_keys($spanish));
        foreach ($spanish as $code => $template) {
            $this->assertTrue($template['title'] !== '' && $template['body'] !== '', 'Empty Spanish template ' . $code);
            $this->assertFalse($template['title'] === $english[$code]['title'], 'Untranslated title ' . $code);
            $this->assertSame(
                substr_count($english[$code]['body'], "\n\n"),
                substr_count($template['body'], "\n\n"),
                'Both versions of ' . $code . ' should have the same paragraphs'
            );
        }
    }

    public function testBothConsentVersionsCiteTheSameLaws(): void
    {
        foreach (ConsentTemplates::all('en') as $code => $template) {
            preg_match_all('/\b(?:Law|Resolution) (\d+) of (\d{4})/', $template['body'], $english);
            preg_match_all('/\b(?:Ley|Resolución) (\d+) de (\d{4})/u', ConsentTemplates::get($code, 'es')['body'], $spanish);

            $this->assertSame($english[1], $spanish[1], 'Laws cited in ' . $code);
        }
    }

    public function testUnknownLanguagesFallBackToEnglish(): void
    {
        $this->assertSame(ConsentTemplates::all('en'), ConsentTemplates::all('fr'));
        $this->assertSame(Instruments::all('en'), Instruments::all('fr'));
    }

    public function testTheSpanishInstrumentsMirrorTheEnglishOnes(): void
    {
        $spanish = Instruments::all('es');

        foreach (Instruments::all('en') as $code => $instrument) {
            $mirror = $spanish[$code] ?? null;
            $this->assertNotNull($mirror, 'Missing Spanish ' . $code);
            $this->assertCount(count($instrument['items']), $mirror['items'], $code . ' items');
            $this->assertSame(array_values($instrument['scale']), array_values($mirror['scale']), $code . ' scale values');
            $this->assertSame($instrument['reverse'], $mirror['reverse'], $code . ' reverse items');
            $this->assertSame($instrument['multiplier'] ?? 1, $mirror['multiplier'] ?? 1, $code . ' multiplier');
            $this->assertSame($instrument['critical_items'], $mirror['critical_items'], $code . ' critical items');
            $this->assertSame(self::cutOffs($instrument['bands']), self::cutOffs($mirror['bands']), $code . ' bands');
            $this->assertSame(array_values($instrument['subscales'] ?? []), array_values($mirror['subscales'] ?? []), $code . ' subscales');
            $this->assertSame(
                array_map([self::class, 'cutOffs'], array_values($instrument['subscale_bands'] ?? [])),
                array_map([self::class, 'cutOffs'], array_values($mirror['subscale_bands'] ?? [])),
                $code . ' subscale bands'
            );
        }
    }

    public function testScoresAreTheSameInBothLanguages(): void
    {
        $answers = [2, 2, 2, 2, 2, 2, 1, 1, 1];

        $english = Instruments::score('PHQ-9', $answers, 'en');
        $spanish = Instruments::score('PHQ-9', $answers, 'es');

        $this->assertSame($english['total'], $spanish['total']);
        $this->assertSame('Moderately severe', $english['severity']);
        $this->assertSame('Moderadamente severa', $spanish['severity']);
        $this->assertSame(['Pensamientos de que estaría mejor muerto o de hacerse daño'], $spanish['alerts']);
    }

    public function testSpanishSubscalesUseSpanishNames(): void
    {
        $result = Instruments::score('DASS-21', array_fill(0, 21, 1), 'es');

        $this->assertSame(['Depresión', 'Ansiedad', 'Estrés'], array_keys($result['subscales']));
        $this->assertSame(14, $result['subscales']['Depresión']['score']);
        $this->assertSame('Moderada', $result['subscales']['Depresión']['severity']);
    }

    public function testDescribeReturnsTheRequestedLanguage(): void
    {
        $this->assertSame('Generalized Anxiety Disorder Scale', Instruments::describe('GAD-7')['name']);
        $this->assertSame('Escala de Ansiedad Generalizada', Instruments::describe('GAD-7', 'es')['name']);
        $this->assertSame(Instruments::describe('GAD-7')['maxScore'], Instruments::describe('GAD-7', 'es')['maxScore']);
    }

    private static function cutOffs(array $bands): array
    {
        return array_map(static fn (array $band): array => [$band[0], $band[1]], $bands);
    }
}
