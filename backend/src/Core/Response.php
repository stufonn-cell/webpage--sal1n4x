<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Response
{
    /** Ultimo codigo emitido; las pruebas lo leen porque en CLI no hay cabeceras. */
    public static int $lastStatus = 200;

    public static function json(array $data, int $status = 200): void
    {
        self::$lastStatus = $status;
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            // Las respuestas contienen datos clinicos: nunca deben quedar en cache.
            header('Cache-Control: no-store, max-age=0');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public static function noContent(): void
    {
        http_response_code(204);
    }

    public static function download(string $file, string $name, string $mime): void
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'documento';

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, max-age=0');
        header(sprintf(
            'Content-Disposition: attachment; filename="%s"; filename*=UTF-8\'\'%s',
            $fallback,
            rawurlencode($name)
        ));
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
    }
}
