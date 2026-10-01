<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core\Middleware;

use PsiClinic\Core\Auth;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;

final class RequireAdmin
{
    public function handle(Request $request): void
    {
        if (!Auth::is('admin')) {
            throw HttpException::forbidden('You need administrator permissions to do this.');
        }
    }
}
