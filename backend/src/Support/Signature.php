<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Support;

/**
 * La firma llega como coordenadas y el SVG se construye aqui. Nunca se guarda
 * ni se devuelve marcado enviado por el navegador: eso permitia inyectar
 * scripts en la vista del equipo clinico.
 */
final class Signature
{
    public const WIDTH = 600;
    public const HEIGHT = 200;
    private const MAX_STROKES = 80;
    private const MAX_POINTS = 4000;

    public static function fromStrokes(array $strokes): string
    {
        $paths = [];
        $budget = self::MAX_POINTS;

        foreach (array_slice($strokes, 0, self::MAX_STROKES) as $stroke) {
            if (!is_array($stroke)) {
                continue;
            }

            $points = [];
            foreach ($stroke as $point) {
                if ($budget-- <= 0) {
                    break 2;
                }
                if (!is_array($point) || count($point) < 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
                    continue;
                }
                $points[] = sprintf(
                    '%d %d',
                    max(0, min(self::WIDTH, (int) round((float) $point[0]))),
                    max(0, min(self::HEIGHT, (int) round((float) $point[1])))
                );
            }

            if (count($points) > 1) {
                $paths[] = 'M' . implode(' L', $points);
            }
        }

        return self::build($paths);
    }

    /** Reconstruye una firma guardada conservando solo trazos validos. */
    public static function sanitize(?string $svg): string
    {
        if ($svg === null || trim($svg) === '') {
            return '';
        }

        preg_match_all('/\bd="(M[0-9 .L]+)"/', $svg, $matches);

        return self::build($matches[1] ?? []);
    }

    private static function build(array $paths): string
    {
        if ($paths === []) {
            return '';
        }

        $markup = '';
        foreach ($paths as $d) {
            $markup .= sprintf(
                '<path d="%s" fill="none" stroke="#1f2a28" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
                $d
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d">%s</svg>',
            self::WIDTH,
            self::HEIGHT,
            $markup
        );
    }
}
