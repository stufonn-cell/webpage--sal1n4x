<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
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

// The feature suite truncates tables and re-runs the installer: it must never
// touch the development or production database. DB_DATABASE is forced to
// DB_TEST_DATABASE, which must be a separate database whose name ends in
// "_test"; otherwise nothing runs. The process environment is overwritten too,
// so anything that reads getenv() (or a child process) sees the same value.
$appDatabase = (string) Env::get('DB_DATABASE', 'psiclinic');
$testDatabase = (string) Env::get('DB_TEST_DATABASE', 'psiclinic_test');

if (preg_match('/^[A-Za-z0-9_]+_test$/', $testDatabase) !== 1 || $testDatabase === $appDatabase) {
    fwrite(STDERR, sprintf(
        "\n  Refusing to run the tests: DB_TEST_DATABASE (\"%s\") must be a separate database whose name ends in \"_test\".\n\n",
        $testDatabase
    ));
    exit(1);
}

Env::set('DB_DATABASE', $testDatabase);
putenv('DB_DATABASE=' . $testDatabase);
$_ENV['DB_DATABASE'] = $testDatabase;
$_SERVER['DB_DATABASE'] = $testDatabase;
unset($appDatabase, $testDatabase);

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'UTC'));
mb_internal_encoding('UTF-8');
