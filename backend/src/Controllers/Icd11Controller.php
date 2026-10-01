<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Controller;
use PsiClinic\Core\Request;
use PsiClinic\Domain\Icd11;

/** ICD-11 lookup used by the diagnosis picker. */
final class Icd11Controller extends Controller
{
    public function search(Request $request): void
    {
        $this->ok([
            'release' => Icd11::RELEASE,
            'results' => Icd11::search(mb_substr($request->string('q'), 0, 100), $request->integer('limit', 20)),
        ]);
    }
}
