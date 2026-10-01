<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

/**
 * Idioma de las respuestas (espanol o ingles).
 *
 * El texto fuente esta en espanol dentro del codigo y resources/lang/en.php
 * lo traduce. Asi el mensaje sigue siendo legible donde se escribe, y una
 * prueba (LangTest) verifica que todos tengan su traduccion.
 */
final class Lang
{
    public const SUPPORTED = ['es', 'en'];

    private static string $current = 'es';
    private static ?array $dictionary = null;

    /** Toma el idioma de la cabecera Accept-Language que envia el frontend. */
    public static function detect(string $acceptLanguage): void
    {
        $preferred = strtolower(substr(trim($acceptLanguage), 0, 2));
        self::$current = in_array($preferred, self::SUPPORTED, true) ? $preferred : 'es';
    }

    public static function set(string $language): void
    {
        self::$current = in_array($language, self::SUPPORTED, true) ? $language : 'es';
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function isEnglish(): bool
    {
        return self::$current === 'en';
    }

    /** Traduce un texto exacto. Si no hay traduccion, devuelve el original. */
    public static function t(string $text): string
    {
        if (self::$current === 'es' || $text === '') {
            return $text;
        }

        return self::dictionary()[$text] ?? $text;
    }

    /** Traduce los valores de un arreglo de mensajes (errores por campo). */
    public static function all(array $messages): array
    {
        return array_map(static fn (mixed $message): mixed => is_string($message) ? self::t($message) : $message, $messages);
    }

    public static function dictionary(): array
    {
        if (self::$dictionary === null) {
            $file = dirname(__DIR__, 2) . '/resources/lang/en.php';
            self::$dictionary = is_file($file) ? require $file : [];
        }

        return self::$dictionary;
    }
}
