<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put(self::KEY, $token);
        }

        return $token;
    }

    public static function rotate(): string
    {
        Session::forget(self::KEY);

        return self::token();
    }

    public static function verify(?string $token): bool
    {
        $stored = Session::get(self::KEY);

        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }
}
