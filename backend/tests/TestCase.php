<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests;

abstract class TestCase
{
    private int $assertions = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function assertionCount(): int
    {
        return $this->assertions;
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->record();

        if (!$condition) {
            throw new AssertionFailed($message !== '' ? $message : 'Expected true.');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue(!$condition, $message !== '' ? $message : 'Expected false.');
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->record();

        if ($expected !== $actual) {
            throw new AssertionFailed(sprintf(
                '%sExpected: %s | Actual: %s',
                $message === '' ? '' : $message . ' - ',
                $this->describe($expected),
                $this->describe($actual)
            ));
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->record();

        if ($expected != $actual) {
            throw new AssertionFailed(sprintf(
                '%sExpected: %s | Actual: %s',
                $message === '' ? '' : $message . ' - ',
                $this->describe($expected),
                $this->describe($actual)
            ));
        }
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $this->assertTrue($value === null, $message !== '' ? $message : 'Expected null.');
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        $this->assertTrue($value !== null, $message !== '' ? $message : 'Did not expect null.');
    }

    protected function assertCount(int $expected, array $items, string $message = ''): void
    {
        $this->assertSame($expected, count($items), $message !== '' ? $message : 'Item count');
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            $message !== '' ? $message : sprintf('The text does not contain "%s".', $needle)
        );
    }

    protected function assertGreaterThan(float|int $limit, float|int $value, string $message = ''): void
    {
        $this->assertTrue(
            $value > $limit,
            $message !== '' ? $message : sprintf('%s is not greater than %s.', (string) $value, (string) $limit)
        );
    }

    protected function assertArrayHasKey(string|int $key, array $items, string $message = ''): void
    {
        $this->assertTrue(
            array_key_exists($key, $items),
            $message !== '' ? $message : sprintf('Missing key "%s".', (string) $key)
        );
    }

    protected function assertThrows(callable $callback, string $message = ''): void
    {
        $this->record();

        try {
            $callback();
        } catch (\Throwable) {
            return;
        }

        throw new AssertionFailed($message !== '' ? $message : 'Expected an exception.');
    }

    private function record(): void
    {
        $this->assertions++;
    }

    private function describe(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_array($value) => 'array(' . count($value) . ')',
            is_object($value) => get_class($value),
            default => (string) $value,
        };
    }
}
