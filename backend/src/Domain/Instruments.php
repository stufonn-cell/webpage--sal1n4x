<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

/**
 * Psychometric instruments. The English catalog is the reference; the Spanish
 * one (instrument_catalog_es.php) mirrors it item by item and is used to
 * produce assessment reports in Spanish. Scores never depend on the language.
 */
final class Instruments
{
    private const CATALOGS = [
        'en' => 'instrument_catalog.php',
        'es' => 'instrument_catalog_es.php',
    ];

    private const UNCLASSIFIED = ['en' => 'Unclassified', 'es' => 'Sin clasificar'];

    /** @var array<string, array> */
    private static array $catalogs = [];

    public static function all(string $language = 'en'): array
    {
        $language = isset(self::CATALOGS[$language]) ? $language : 'en';

        if (!isset(self::$catalogs[$language])) {
            self::$catalogs[$language] = require __DIR__ . '/' . self::CATALOGS[$language];
        }

        return self::$catalogs[$language];
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $code, string $language = 'en'): ?array
    {
        $instrument = self::all($language)[$code] ?? null;

        return $instrument === null ? null : $instrument + ['code' => $code];
    }

    public static function maxScore(string $code): int
    {
        $instrument = self::get($code);

        if ($instrument === null) {
            return 0;
        }

        $max = max(array_values($instrument['scale']));
        $total = $max * count($instrument['items']);

        return (int) ($total * ($instrument['multiplier'] ?? 1));
    }

    /**
     * Normalizes the answers received. Returns null when an item is missing or
     * when a value is not part of the instrument's scale.
     */
    public static function normalizeAnswers(string $code, array $answers): ?array
    {
        $instrument = self::get($code);

        if ($instrument === null) {
            return null;
        }

        $allowed = array_map('intval', array_values($instrument['scale']));
        $normalized = [];

        foreach (array_keys($instrument['items']) as $index) {
            $value = $answers[$index] ?? $answers[(string) $index] ?? null;

            if (!is_numeric($value) || !in_array((int) $value, $allowed, true)) {
                return null;
            }

            $normalized[$index] = (int) $value;
        }

        return $normalized;
    }

    /** Public version of the instrument, used to render the form and the report. */
    public static function describe(string $code, string $language = 'en'): ?array
    {
        $instrument = self::get($code, $language);

        if ($instrument === null) {
            return null;
        }

        $scale = [];
        foreach ($instrument['scale'] as $label => $value) {
            $scale[] = ['label' => (string) $label, 'value' => (int) $value];
        }

        return [
            'code' => $code,
            'name' => $instrument['name'],
            'domain' => $instrument['domain'],
            'window' => $instrument['window'],
            'description' => $instrument['description'],
            'items' => array_values($instrument['items']),
            'scale' => $scale,
            'bands' => array_map(
                static fn (array $band): array => ['min' => $band[0], 'max' => $band[1], 'label' => $band[2], 'interpretation' => $band[3] ?? ''],
                $instrument['bands']
            ),
            'subscales' => array_keys($instrument['subscales'] ?? []),
            'criticalItems' => $instrument['critical_items'] ?? [],
            'maxScore' => self::maxScore($code),
        ];
    }

    public static function score(string $code, array $answers, string $language = 'en'): array
    {
        $instrument = self::get($code, $language);
        $unclassified = self::UNCLASSIFIED[$language] ?? self::UNCLASSIFIED['en'];

        if ($instrument === null) {
            return ['total' => 0, 'severity' => '', 'interpretation' => '', 'subscales' => [], 'alerts' => []];
        }

        $maxOption = max(array_values($instrument['scale']));
        $reverse = $instrument['reverse'] ?? [];
        $values = [];

        foreach ($instrument['items'] as $index => $_) {
            $raw = (int) ($answers[$index] ?? 0);
            $values[$index] = in_array($index, $reverse, true) ? $maxOption - $raw : $raw;
        }

        $multiplier = (int) ($instrument['multiplier'] ?? 1);
        $total = array_sum($values) * $multiplier;

        $subscales = [];
        foreach ($instrument['subscales'] ?? [] as $label => $indexes) {
            $subtotal = 0;
            foreach ($indexes as $index) {
                $subtotal += $values[$index] ?? 0;
            }
            $subtotal *= $multiplier;

            $subscales[$label] = [
                'score' => $subtotal,
                'severity' => self::band($instrument['subscale_bands'][$label] ?? [], $subtotal, $unclassified)[0],
            ];
        }

        [$severity, $interpretation] = self::band($instrument['bands'], $total, $unclassified);

        $alerts = [];
        foreach ($instrument['critical_items'] ?? [] as $index) {
            if ((int) ($answers[$index] ?? 0) > 0) {
                $alerts[] = $instrument['items'][$index];
            }
        }

        return [
            'total' => $total,
            'severity' => $severity,
            'interpretation' => $interpretation,
            'subscales' => $subscales,
            'alerts' => $alerts,
        ];
    }

    /** @return array{0:string,1:string} */
    private static function band(array $bands, int $score, string $unclassified): array
    {
        foreach ($bands as $band) {
            if ($score >= $band[0] && $score <= $band[1]) {
                return [(string) $band[2], (string) ($band[3] ?? '')];
            }
        }

        return [$unclassified, ''];
    }
}
