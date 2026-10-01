<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Request
{
    /** Deepest JSON accepted. The deepest real payload (signature strokes) uses 4 levels. */
    public const MAX_JSON_DEPTH = 16;

    private const DEFAULT_MAX_JSON_BYTES = 1048576;

    private array $attributes = [];
    private readonly array $query;
    private readonly array $body;

    public function __construct(
        array $query,
        array $body,
        private readonly array $files,
        private readonly array $server
    ) {
        $this->query = self::clean($query);
        $this->body = self::clean($body);
    }

    public static function capture(): self
    {
        $body = $_POST;
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $body = self::decodeJson(self::readInput());
        }

        return new self($_GET, $body, $_FILES, $_SERVER);
    }

    /** Largest JSON body accepted, in bytes (API_MAX_JSON_BYTES, 1 MB by default). */
    public static function maxJsonBytes(): int
    {
        return max(1024, Env::int('API_MAX_JSON_BYTES', self::DEFAULT_MAX_JSON_BYTES));
    }

    /**
     * Decodes a JSON body. An empty body is an empty payload; anything that is
     * not a JSON object or list, is malformed or nests too deep is refused.
     */
    public static function decodeJson(string $raw): array
    {
        if (strlen($raw) > self::maxJsonBytes()) {
            throw HttpException::payloadTooLarge();
        }

        if (trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            throw HttpException::badRequest();
        }

        if (!is_array($decoded)) {
            throw HttpException::badRequest();
        }

        return $decoded;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return trim((string) ($this->server[$key] ?? ''));
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key, false);

        return is_scalar($value) && filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * The verb can only be overridden from a real POST (HTML forms), and only
     * to PUT, PATCH or DELETE: a GET can never turn into a write.
     */
    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));

        if ($method !== 'POST') {
            return $method;
        }

        $override = $this->body['_method'] ?? '';
        $override = is_string($override) ? strtoupper($override) : '';

        return in_array($override, ['PUT', 'PATCH', 'DELETE'], true) ? $override : $method;
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return '/' . trim($path, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        if (is_int($value)) {
            return $value;
        }
        if (!is_numeric($value)) {
            return $default;
        }

        $exact = filter_var($value, FILTER_VALIDATE_INT);
        if ($exact !== false) {
            return $exact;
        }

        // Floats and out-of-range numbers are clamped instead of overflowing.
        $number = (float) $value;

        return match (true) {
            !is_finite($number) => $default,
            $number >= 9.2e18 => PHP_INT_MAX,
            $number <= -9.2e18 => PHP_INT_MIN,
            default => (int) $number,
        };
    }

    public function array(string $key): array
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        return $file;
    }

    /** PHP upload error code for a file field (UPLOAD_ERR_NO_FILE when it was not sent). */
    public function fileError(string $key): int
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) && is_int($file['error'] ?? null) ? $file['error'] : UPLOAD_ERR_NO_FILE;
    }

    public function isAjax(): bool
    {
        return strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /**
     * Client IP. Nginx already replaced REMOTE_ADDR with the visitor's address
     * when a trusted proxy sits in front (real_ip module); headers such as
     * X-Forwarded-For are never read here because any client can forge them.
     */
    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body + $this->query;
    }

    private static function readInput(): string
    {
        $max = self::maxJsonBytes();

        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $max) {
            throw HttpException::payloadTooLarge();
        }

        // One byte more than the limit is enough to know it was exceeded.
        $raw = file_get_contents('php://input', false, null, 0, $max + 1);

        return $raw === false ? '' : $raw;
    }

    /**
     * Normalises every incoming string once: invalid UTF-8 sequences are
     * replaced and NUL bytes removed, so MySQL (utf8mb4, strict mode) never
     * rejects a value with an error.
     */
    private static function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = str_replace("\0", '', mb_scrub($value, 'UTF-8'));
            } elseif (is_array($value)) {
                $values[$key] = self::clean($value);
            }
        }

        return $values;
    }
}
