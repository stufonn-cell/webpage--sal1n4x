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

final class Authenticate
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            throw new HttpException(401, 'Your session has ended. Please log in again to continue.');
        }
    }
}
