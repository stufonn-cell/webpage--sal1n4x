<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

use PsiClinic\Core\Env;

require dirname(__DIR__) . '/src/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'PsiClinic\\Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

Env::load(dirname(__DIR__) . '/.env');
Env::set('APP_ENV', 'testing');
Env::set('APP_DEBUG', 'true');
Env::set('DB_DATABASE', (string) Env::get('DB_TEST_DATABASE', 'psiclinic_test'));

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'UTC'));
mb_internal_encoding('UTF-8');
