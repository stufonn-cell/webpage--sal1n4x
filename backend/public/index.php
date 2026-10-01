<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

use PsiClinic\Core\App;
use PsiClinic\Core\Env;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Lang;
use PsiClinic\Core\Request;
use PsiClinic\Core\Response;
use PsiClinic\Core\Router;

require dirname(__DIR__) . '/src/autoload.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

try {
    App::boot(dirname(__DIR__));

    $request = Request::capture();
    Lang::detect($request->header('Accept-Language'));

    /** @var Router $router */
    $router = require dirname(__DIR__) . '/src/routes.php';
    $router->dispatch($request);
} catch (HttpException $exception) {
    Response::json($exception->toArray(), $exception->status());
} catch (Throwable $exception) {
    // El detalle tecnico va al log; a la persona solo un mensaje humano.
    error_log(sprintf('[%s] %s en %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()));

    $body = ['error' => ['message' => Lang::t('Algo salió mal de nuestro lado. Intenta de nuevo en unos minutos.')]];
    if (Env::bool('APP_DEBUG', false) && App::isLocal()) {
        $body['error']['debug'] = $exception->getMessage();
    }

    Response::json($body, 500);
}
