<?php

declare(strict_types=1);

use PsiClinic\Core\App;
use PsiClinic\Core\Request;
use PsiClinic\Core\Router;
use PsiClinic\Core\View;

require dirname(__DIR__) . '/src/autoload.php';

App::boot(dirname(__DIR__));

/** @var Router $router */
$router = require dirname(__DIR__) . '/src/routes.php';

try {
    $router->dispatch(Request::capture());
} catch (Throwable $exception) {
    if (\PsiClinic\Core\Env::bool('APP_DEBUG', false)) {
        throw $exception;
    }

    error_log($exception->getMessage());
    http_response_code(500);
    echo View::render('errors/500', [], 'blank');
}
