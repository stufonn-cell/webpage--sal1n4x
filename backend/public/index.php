<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

use PsiClinic\Core\App;
use PsiClinic\Core\Env;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Log;
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;
use PsiClinic\Core\Router;

require dirname(__DIR__) . '/src/autoload.php';

// Security headers for every API response. Nginx sets the site-wide ones
// too; these keep the API safe if it is ever served without that Nginx.
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');

try {
    App::boot(dirname(__DIR__));

    $request = Request::capture();

    /** @var Router $router */
    $router = require dirname(__DIR__) . '/src/routes.php';
    $router->dispatch($request);
} catch (HttpException $exception) {
    Response::json($exception->toArray(), $exception->status(), $exception->headers());
} catch (Throwable $exception) {
    // Technical details (including SQL errors) go to the log only; the
    // person sees a human message.
    Log::error(sprintf('[%s] %s in %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()));

    $body = ['error' => ['message' => 'Something went wrong on our end. Please try again in a few minutes.']];
    // Local debugging aid only. SQL errors are never echoed, not even locally:
    // they can quote data and reveal the schema.
    if (Env::bool('APP_DEBUG', false) && App::isLocal()) {
        $body['error']['debug'] = $exception instanceof PDOException
            ? 'Database error. The details are in the server log.'
            : $exception->getMessage();
    }

    Response::json($body, 500);
}
