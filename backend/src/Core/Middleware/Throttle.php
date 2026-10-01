<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core\Middleware;

use PsiClinic\Core\RateLimiter;
use PsiClinic\Core\Request;

/**
 * Rate limit for a route. The profile goes after a colon in the route
 * definition, e.g. Throttle::class . ':login'; see RateLimiter::PROFILES.
 */
final class Throttle
{
    public function __construct(private readonly string $profile = 'api')
    {
    }

    public function handle(Request $request): void
    {
        RateLimiter::enforce($this->profile, $request);
    }
}
