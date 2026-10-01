<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

/**
 * Writes to the server log. Every value is cleaned so text coming from a
 * request (or from an error message that quotes it) can never forge a new
 * log line or inject terminal escape codes.
 */
final class Log
{
    private const MAX_LENGTH = 4000;

    public static function error(string $message, array $context = []): void
    {
        error_log(self::format($message, $context));
    }

    public static function format(string $message, array $context = []): string
    {
        $line = self::clean($message);

        foreach ($context as $key => $value) {
            $text = is_scalar($value) || $value === null
                ? (string) $value
                : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $line .= ' ' . self::clean((string) $key) . '=' . self::clean($text);
        }

        return strlen($line) > self::MAX_LENGTH ? substr($line, 0, self::MAX_LENGTH) . '...' : $line;
    }

    /** Escapes control characters (CR, LF, ESC, NUL...) as \xNN. */
    public static function clean(string $value): string
    {
        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            static fn (array $match): string => sprintf('\\x%02X', ord($match[0])),
            $value
        ) ?? '';
    }
}
