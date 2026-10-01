<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Request
{
    private array $attributes = [];

    public function __construct(
        private readonly array $query,
        private readonly array $body,
        private readonly array $files,
        private readonly array $server
    ) {
    }

    public static function capture(): self
    {
        $body = $_POST;
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($decoded) ? $decoded : [];
        }

        return new self($_GET, $body, $_FILES, $_SERVER);
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
        return filter_var($this->input($key, false), FILTER_VALIDATE_BOOL);
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        $override = strtoupper((string) ($this->body['_method'] ?? ''));

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

        return is_numeric($value) ? (int) $value : $default;
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

    public function isAjax(): bool
    {
        return strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
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
}
