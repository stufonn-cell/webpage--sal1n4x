<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core\Middleware;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;
use PsiClinic\Core\Session;

final class RequireAdmin
{
    public function handle(Request $request): void
    {
        if (!Auth::is('admin')) {
            Session::flash('error', 'Necesitas permisos de administrador para esa seccion.');
            Response::redirect('/dashboard');
        }
    }
}
