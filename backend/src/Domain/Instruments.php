<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

final class Instruments
{
    private static ?array $catalog = null;

    public static function all(): array
    {
        if (self::$catalog === null) {
            self::$catalog = require __DIR__ . '/instrument_catalog.php';
        }

        return self::$catalog;
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $code): ?array
    {
        $instrument = self::all()[$code] ?? null;

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
     * Normaliza las respuestas recibidas. Devuelve null si falta algun item o
     * si algun valor no pertenece a la escala del instrumento.
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

    /** Version publica del instrumento para pintar el formulario. */
    public static function describe(string $code): ?array
    {
        $instrument = self::get($code);

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

    public static function score(string $code, array $answers): array
    {
        $instrument = self::get($code);

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
                'severity' => self::bandLabel($instrument['subscale_bands'][$label] ?? [], $subtotal),
            ];
        }

        [$severity, $interpretation] = self::band($instrument['bands'], $total);

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

    private static function band(array $bands, int $score): array
    {
        foreach ($bands as $band) {
            if ($score >= $band[0] && $score <= $band[1]) {
                return [$band[2], $band[3] ?? ''];
            }
        }

        return ['Sin clasificar', ''];
    }

    private static function bandLabel(array $bands, int $score): string
    {
        foreach ($bands as $band) {
            if ($score >= $band[0] && $score <= $band[1]) {
                return (string) $band[2];
            }
        }

        return 'Sin clasificar';
    }
}
