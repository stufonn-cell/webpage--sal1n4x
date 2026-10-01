<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Controller;
use PsiClinic\Core\Request;
use PsiClinic\Domain\Icd11;

final class Icd11Controller extends Controller
{
    public function search(Request $request): void
    {
        $this->ok([
            'release' => Icd11::RELEASE,
            'results' => Icd11::search($request->string('q'), $request->integer('limit', 20)),
        ]);
    }
}
