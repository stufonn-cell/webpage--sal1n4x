<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core\Middleware;

use PsiClinic\Core\Csrf;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;

final class VerifyCsrf
{
    public function handle(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        // Second line of defence: modern browsers say where a request comes
        // from. A write started by another site is refused even before the
        // token is checked.
        if (in_array(strtolower($request->header('Sec-Fetch-Site')), ['cross-site', 'same-site'], true)) {
            throw HttpException::forbidden('For your security, this action has to be done from within PsiClinic.');
        }

        $token = $request->header('X-CSRF-Token');
        if ($token === '') {
            $token = $request->string('_token');
        }

        if (!Csrf::verify($token)) {
            throw new HttpException(419, 'Your security session has expired. Reload the page and try again.');
        }
    }
}
