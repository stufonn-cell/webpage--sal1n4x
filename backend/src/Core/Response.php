<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Response
{
    /**
     * JSON is also escaped for HTML contexts (<, >, &, quotes as \u00XX): even
     * if a response were ever sniffed or embedded as HTML it could not break
     * out into markup. Clients decode the exact same strings.
     */
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_HEX_TAG | JSON_HEX_AMP;

    /** Last status code and extra headers sent; tests read them because there are no headers in CLI. */
    public static int $lastStatus = 200;
    public static array $lastHeaders = [];

    public static function json(array $data, int $status = 200, array $headers = []): void
    {
        $body = json_encode($data, self::JSON_FLAGS);

        if ($body === false) {
            Log::error('JSON encoding of a response failed', ['error' => json_last_error_msg()]);
            $status = 500;
            $headers = [];
            $body = '{"error":{"message":"Something went wrong on our end. Please try again in a few minutes."}}';
        }

        self::$lastStatus = $status;
        self::$lastHeaders = $headers;
        http_response_code($status);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            // Responses contain clinical data: they must never be cached.
            header('Cache-Control: no-store, max-age=0');
            foreach ($headers as $name => $value) {
                header(self::headerLine((string) $name, (string) $value));
            }
        }

        echo $body;
    }

    public static function noContent(): void
    {
        self::$lastStatus = 204;
        http_response_code(204);
    }

    public static function download(string $file, string $name, string $mime): void
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'document';

        header('Content-Type: ' . self::headerValue($mime));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, max-age=0');
        // Even if a browser decided to render the file, it could not run
        // scripts, load anything or be framed.
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('X-Frame-Options: DENY');
        header(sprintf(
            'Content-Disposition: attachment; filename="%s"; filename*=UTF-8\'\'%s',
            $fallback,
            rawurlencode($name)
        ));
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
    }

    private static function headerLine(string $name, string $value): string
    {
        $name = preg_replace('/[^A-Za-z0-9-]/', '', $name) ?? '';

        return $name . ': ' . self::headerValue($value);
    }

    /** Header values never carry line breaks (response splitting). */
    private static function headerValue(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }
}
