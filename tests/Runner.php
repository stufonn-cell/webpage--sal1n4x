<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests;

use ReflectionClass;
use Throwable;

final class Runner
{
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private int $assertions = 0;
    private array $failures = [];

    public function run(string $suite = ''): int
    {
        $start = microtime(true);

        foreach ($this->discover($suite) as $class) {
            $this->runTestCase($class);
        }

        return $this->report(microtime(true) - $start);
    }

    private function discover(string $suite): array
    {
        $directories = $suite === ''
            ? ['Unit', 'Feature']
            : [ucfirst($suite)];

        $classes = [];

        foreach ($directories as $directory) {
            foreach (glob(__DIR__ . '/' . $directory . '/*Test.php') ?: [] as $file) {
                require_once $file;
                $classes[] = 'PsiClinic\\Tests\\' . $directory . '\\' . basename($file, '.php');
            }
        }

        return array_filter($classes, 'class_exists');
    }

    private function runTestCase(string $class): void
    {
        $instance = new $class();
        $methods = array_filter(
            get_class_methods($instance),
            static fn (string $method): bool => str_starts_with($method, 'test')
        );

        $shortName = (new ReflectionClass($instance))->getShortName();
        $this->write(PHP_EOL . '  ' . $shortName . PHP_EOL);

        foreach ($methods as $method) {
            $this->runTest($instance, $method, $shortName);
        }
    }

    private function runTest(TestCase $instance, string $method, string $shortName): void
    {
        $label = $this->humanize($method);
        $before = $instance->assertionCount();

        try {
            $instance->setUp();
            $instance->{$method}();
            $instance->tearDown();

            $this->passed++;
            $this->write('    [ok]   ' . $label . PHP_EOL);
        } catch (SkippedTest $skip) {
            $this->skipped++;
            $this->write('    [skip] ' . $label . ' (' . $skip->getMessage() . ')' . PHP_EOL);
        } catch (Throwable $error) {
            $this->failed++;
            $this->failures[] = sprintf('%s::%s
      %s', $shortName, $method, $error->getMessage());
            $this->write('    [FAIL] ' . $label . PHP_EOL);
        }

        $this->assertions += $instance->assertionCount() - $before;
    }

    private function report(float $elapsed): int
    {
        if ($this->failures !== []) {
            $this->write(PHP_EOL . '  Fallos:' . PHP_EOL . PHP_EOL);
            foreach ($this->failures as $failure) {
                $this->write('    ' . $failure . PHP_EOL . PHP_EOL);
            }
        }

        $this->write(sprintf(
            PHP_EOL . '  %d correctas, %d fallidas, %d omitidas | %d aserciones | %.2f s' . PHP_EOL . PHP_EOL,
            $this->passed,
            $this->failed,
            $this->skipped,
            $this->assertions,
            $elapsed
        ));

        return $this->failed === 0 ? 0 : 1;
    }

    private function humanize(string $method): string
    {
        $words = preg_replace('/(?<!^)[A-Z]/', ' $0', substr($method, 4)) ?? $method;

        return strtolower(trim($words));
    }

    private function write(string $message): void
    {
        fwrite(STDOUT, $message);
    }
}
