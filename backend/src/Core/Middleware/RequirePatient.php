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

final class RequirePatient
{
    public function handle(Request $request): void
    {
        (new Authenticate())->handle($request);

        if (!Auth::is('patient') || Auth::user()['patient_id'] === null) {
            throw HttpException::forbidden('Esta sección es el portal del paciente.');
        }
    }
}
