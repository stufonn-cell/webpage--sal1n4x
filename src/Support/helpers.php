<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

use PsiClinic\Core\Auth;
use PsiClinic\Core\Csrf;
use PsiClinic\Core\Env;
use PsiClinic\Core\App;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return '/' . ltrim($path, '/');
}

function csrf(): string
{
    return Csrf::field();
}

function method(string $verb): string
{
    return sprintf('<input type="hidden" name="_method" value="%s">', strtoupper($verb));
}

function old(string $key, mixed $default = ''): string
{
    return e(App::oldInput()[$key] ?? $default);
}

function fieldError(string $key): string
{
    $message = App::errors()[$key] ?? null;

    return $message === null ? '' : sprintf('<span class="field-error">%s</span>', e($message));
}

function hasError(string $key): string
{
    return isset(App::errors()[$key]) ? ' has-error' : '';
}

function currentUser(): ?array
{
    return Auth::user();
}

function config(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function formatDate(?string $value, string $format = 'd/m/Y'): string
{
    if ($value === null || $value === '' || $value === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '-' : date($format, $timestamp);
}

function formatDateTime(?string $value): string
{
    return formatDate($value, 'd/m/Y H:i');
}

function formatMoney(float|int|string|null $amount): string
{
    return number_format((float) $amount, 2, ',', '.');
}

function ageFrom(?string $birthDate): string
{
    if ($birthDate === null || $birthDate === '') {
        return '-';
    }

    try {
        $birth = new DateTimeImmutable($birthDate);
    } catch (Exception) {
        return '-';
    }

    return (string) $birth->diff(new DateTimeImmutable('now'))->y;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = array_map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), array_slice($parts, 0, 2));

    return implode('', $letters);
}

function activeWhen(string $current, string $prefix): string
{
    return str_starts_with($current, $prefix) ? ' is-active' : '';
}

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
