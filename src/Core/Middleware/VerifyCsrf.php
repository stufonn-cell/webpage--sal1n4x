<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core\Middleware;

use PsiClinic\Core\Csrf;
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;

final class VerifyCsrf
{
    public function handle(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        if (Csrf::verify((string) $request->input('_token', ''))) {
            return;
        }

        Response::html('Token de seguridad invalido o expirado. Recarga la pagina e intentalo de nuevo.', 419);
        exit;
    }
}
