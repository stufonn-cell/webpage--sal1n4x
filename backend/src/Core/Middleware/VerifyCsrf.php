<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
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

        $token = $request->header('X-CSRF-Token');
        if ($token === '') {
            $token = $request->string('_token');
        }

        if (!Csrf::verify($token)) {
            throw new HttpException(419, 'Tu sesión de seguridad expiró. Recarga la página e inténtalo de nuevo.');
        }
    }
}
