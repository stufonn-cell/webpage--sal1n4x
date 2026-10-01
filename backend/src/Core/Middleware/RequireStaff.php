<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core\Middleware;

use PsiClinic\Core\Auth;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;

final class RequireStaff
{
    public function handle(Request $request): void
    {
        (new Authenticate())->handle($request);

        if (!Auth::isStaff()) {
            throw HttpException::forbidden('Esta sección es solo para el equipo clínico.');
        }
    }
}
