<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Support;

final class Chart
{
    public static function bars(array $series, int $width = 520, int $height = 180): string
    {
        if ($series === []) {
            return self::placeholder($width, $height);
        }

        $max = max(1, max(array_values($series)));
        $count = count($series);
        $slot = $width / $count;
        $barWidth = min(38.0, $slot * 0.52);
        $chartHeight = $height - 34;

        $bars = '';
        $index = 0;

        foreach ($series as $label => $value) {
            $barHeight = $max === 0 ? 0 : ($value / $max) * ($chartHeight - 12);
            $x = $slot * $index + ($slot - $barWidth) / 2;
            $y = $chartHeight - $barHeight;

            $bars .= sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="6" fill="url(#barGradient)"><title>%s: %d</title></rect>',
                $x,
                $y,
                $barWidth,
                max(2.0, $barHeight),
                htmlspecialchars((string) $label, ENT_QUOTES),
                (int) $value
            );

            $bars .= sprintf(
                '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="10" fill="currentColor" opacity=".55">%s</text>',
                $slot * $index + $slot / 2,
                (float) $height - 8,
                htmlspecialchars(self::shortLabel((string) $label), ENT_QUOTES)
            );

            $bars .= sprintf(
                '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="10.5" font-weight="600" fill="currentColor" opacity=".8">%d</text>',
                $slot * $index + $slot / 2,
                $y - 5,
                (int) $value
            );

            $index++;
        }

        return sprintf(
            '<svg class="chart-svg" viewBox="0 0 %d %d" role="img" aria-label="Grafico de barras">
                <defs><linearGradient id="barGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#3f5ff2"/><stop offset="1" stop-color="#6285fb" stop-opacity=".65"/>
                </linearGradient></defs>
                <line x1="0" y1="%d" x2="%d" y2="%d" stroke="currentColor" stroke-opacity=".18"/>
                %s
            </svg>',
            $width,
            $height,
            $chartHeight,
            $width,
            $chartHeight,
            $bars
        );
    }

    public static function line(array $points, int $max, int $width = 520, int $height = 190): string
    {
        if (count($points) < 1) {
            return self::placeholder($width, $height);
        }

        $max = max(1, $max);
        $padding = 26;
        $usableWidth = $width - $padding * 2;
        $usableHeight = $height - $padding * 2;
        $count = max(1, count($points) - 1);

        $coordinates = [];
        foreach (array_values($points) as $index => $point) {
            $x = $padding + ($count === 0 ? 0 : ($index / $count) * $usableWidth);
            $y = $padding + $usableHeight - (min((float) $point['value'], (float) $max) / $max) * $usableHeight;
            $coordinates[] = [$x, $y, $point];
        }

        $path = '';
        foreach ($coordinates as $index => [$x, $y]) {
            $path .= ($index === 0 ? 'M' : ' L') . sprintf('%.1f %.1f', $x, $y);
        }

        $area = $path . sprintf(
            ' L%.1f %.1f L%.1f %.1f Z',
            $coordinates[count($coordinates) - 1][0],
            (float) ($padding + $usableHeight),
            $coordinates[0][0],
            (float) ($padding + $usableHeight)
        );

        $dots = '';
        foreach ($coordinates as [$x, $y, $point]) {
            $dots .= sprintf(
                '<circle cx="%.1f" cy="%.1f" r="4.5" fill="#3f5ff2" stroke="#fff" stroke-width="2"><title>%s: %s</title></circle>',
                $x,
                $y,
                htmlspecialchars((string) $point['label'], ENT_QUOTES),
                htmlspecialchars((string) $point['value'], ENT_QUOTES)
            );
        }

        $gridLines = '';
        for ($i = 0; $i <= 4; $i++) {
            $y = $padding + ($usableHeight / 4) * $i;
            $value = (int) round($max - ($max / 4) * $i);
            $gridLines .= sprintf(
                '<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="currentColor" stroke-opacity=".12"/>
                 <text x="2" y="%.1f" font-size="9.5" fill="currentColor" opacity=".5">%d</text>',
                $padding,
                $y,
                $width - 6,
                $y,
                $y + 3,
                $value
            );
        }

        return sprintf(
            '<svg class="chart-svg" viewBox="0 0 %d %d" role="img" aria-label="Grafico de evolucion">
                <defs><linearGradient id="areaGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#3f5ff2" stop-opacity=".28"/>
                    <stop offset="1" stop-color="#3f5ff2" stop-opacity="0"/>
                </linearGradient></defs>
                %s
                <path d="%s" fill="url(#areaGradient)"/>
                <path d="%s" fill="none" stroke="#3f5ff2" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                %s
            </svg>',
            $width,
            $height,
            $gridLines,
            $area,
            $path,
            $dots
        );
    }

    public static function donut(array $series, array $colors, int $size = 190): string
    {
        $total = array_sum(array_values($series));

        if ($total <= 0) {
            return self::placeholder($size, $size);
        }

        $radius = $size / 2 - 16;
        $circumference = 2 * M_PI * $radius;
        $offset = 0.0;
        $segments = '';
        $index = 0;

        foreach ($series as $label => $value) {
            if ($value <= 0) {
                $index++;
                continue;
            }

            $portion = $value / $total;
            $length = $portion * $circumference;
            $color = $colors[$index % count($colors)];

            $segments .= sprintf(
                '<circle cx="%d" cy="%d" r="%.1f" fill="none" stroke="%s" stroke-width="18"
                         stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 %d %d)">
                    <title>%s: %d</title></circle>',
                $size / 2,
                $size / 2,
                $radius,
                $color,
                $length,
                $circumference - $length,
                -$offset,
                $size / 2,
                $size / 2,
                htmlspecialchars((string) $label, ENT_QUOTES),
                (int) $value
            );

            $offset += $length;
            $index++;
        }

        return sprintf(
            '<svg class="chart-svg" viewBox="0 0 %d %d" style="max-width:%dpx;margin:0 auto" role="img" aria-label="Distribucion">
                %s
                <text x="%d" y="%d" text-anchor="middle" font-size="24" font-weight="700" fill="currentColor">%d</text>
                <text x="%d" y="%d" text-anchor="middle" font-size="10" fill="currentColor" opacity=".55">TOTAL</text>
            </svg>',
            $size,
            $size,
            $size,
            $segments,
            $size / 2,
            $size / 2 + 4,
            $total,
            $size / 2,
            $size / 2 + 20
        );
    }

    public static function gauge(int $value, int $max, string $label, int $size = 150): string
    {
        $max = max(1, $max);
        $ratio = min(1.0, $value / $max);
        $radius = $size / 2 - 14;
        $circumference = 2 * M_PI * $radius;
        $filled = $circumference * $ratio;

        $color = match (true) {
            $ratio < 0.34 => '#17a673',
            $ratio < 0.67 => '#d98207',
            default => '#d94848',
        };

        return sprintf(
            '<svg class="chart-svg" viewBox="0 0 %d %d" style="max-width:%dpx" role="img" aria-label="Puntaje">
                <circle cx="%d" cy="%d" r="%.1f" fill="none" stroke="currentColor" stroke-opacity=".12" stroke-width="12"/>
                <circle cx="%d" cy="%d" r="%.1f" fill="none" stroke="%s" stroke-width="12" stroke-linecap="round"
                        stroke-dasharray="%.2f %.2f" transform="rotate(-90 %d %d)"/>
                <text x="%d" y="%d" text-anchor="middle" font-size="26" font-weight="700" fill="currentColor">%d</text>
                <text x="%d" y="%d" text-anchor="middle" font-size="9.5" fill="currentColor" opacity=".55">%s</text>
            </svg>',
            $size,
            $size,
            $size,
            $size / 2,
            $size / 2,
            $radius,
            $size / 2,
            $size / 2,
            $radius,
            $color,
            $filled,
            $circumference - $filled,
            $size / 2,
            $size / 2,
            $size / 2,
            $size / 2 + 3,
            $value,
            $size / 2,
            $size / 2 + 19,
            htmlspecialchars($label, ENT_QUOTES)
        );
    }

    private static function placeholder(int $width, int $height): string
    {
        return sprintf(
            '<svg class="chart-svg" viewBox="0 0 %d %d" role="img" aria-label="Sin datos">
                <rect width="%d" height="%d" rx="12" fill="currentColor" fill-opacity=".04"/>
                <text x="%d" y="%d" text-anchor="middle" font-size="12" fill="currentColor" opacity=".45">Sin datos suficientes</text>
            </svg>',
            $width,
            $height,
            $width,
            $height,
            $width / 2,
            $height / 2
        );
    }

    private static function shortLabel(string $label): string
    {
        if (preg_match('/^\d{4}-(\d{2})$/', $label, $matches) === 1) {
            $months = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun',
                       '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];

            return $months[$matches[1]] ?? $label;
        }

        return mb_strlen($label) > 8 ? mb_substr($label, 0, 8) : $label;
    }
}
