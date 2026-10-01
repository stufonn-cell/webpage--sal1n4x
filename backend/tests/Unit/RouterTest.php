<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Core\Router;
use PsiClinic\Tests\TestCase;

final class RouterTest extends TestCase
{
    public function setUp(): void
    {
        RouteSpy::reset();
    }

    public function testItMatchesAStaticRoute(): void
    {
        $router = new Router();
        $router->get('/dashboard', [RouteSpy::class, 'index']);

        $router->dispatch($this->request('GET', '/dashboard'));

        $this->assertSame('index', RouteSpy::$calledMethod);
    }

    public function testItExtractsRouteParameters(): void
    {
        $router = new Router();
        $router->get('/patients/{id}', [RouteSpy::class, 'show']);

        $router->dispatch($this->request('GET', '/patients/42'));

        $this->assertSame('show', RouteSpy::$calledMethod);
        $this->assertSame(['42'], RouteSpy::$arguments);
    }

    public function testItSupportsSeveralParameters(): void
    {
        $router = new Router();
        $router->delete('/patients/{id}/diagnoses/{diagnosisId}', [RouteSpy::class, 'destroy']);

        $router->dispatch($this->request('DELETE', '/patients/7/diagnoses/13'));

        $this->assertSame(['7', '13'], RouteSpy::$arguments);
    }

    public function testTheHttpMethodIsPartOfTheMatch(): void
    {
        $router = new Router();
        $router->get('/patients', [RouteSpy::class, 'index']);
        $router->post('/patients', [RouteSpy::class, 'store']);

        $router->dispatch($this->request('POST', '/patients'));

        $this->assertSame('store', RouteSpy::$calledMethod);
    }

    public function testMethodSpoofingIsHonoured(): void
    {
        $router = new Router();
        $router->put('/patients/{id}', [RouteSpy::class, 'update']);

        $router->dispatch(new Request([], ['_method' => 'PUT'], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/patients/5',
        ]));

        $this->assertSame('update', RouteSpy::$calledMethod);
    }

    public function testTrailingSlashesAreNormalised(): void
    {
        $router = new Router();
        $router->get('/schedule', [RouteSpy::class, 'index']);

        $router->dispatch($this->request('GET', '/schedule/'));

        $this->assertSame('index', RouteSpy::$calledMethod);
    }

    public function testQueryStringIsIgnoredWhenMatching(): void
    {
        $router = new Router();
        $router->get('/patients', [RouteSpy::class, 'index']);

        $router->dispatch($this->request('GET', '/patients?q=vega&page=2'));

        $this->assertSame('index', RouteSpy::$calledMethod);
    }

    public function testMiddlewareRunsBeforeTheController(): void
    {
        $router = new Router();
        $router->post('/patients', [RouteSpy::class, 'store'], [MiddlewareSpy::class]);

        $router->dispatch($this->request('POST', '/patients'));

        $this->assertSame(['middleware', 'store'], RouteSpy::$sequence);
    }

    public function testAParameterDoesNotMatchAcrossSegments(): void
    {
        $router = new Router();
        $router->get('/patients/{id}', [RouteSpy::class, 'show']);

        $this->assertThrows(fn () => $router->dispatch($this->request('GET', '/patients/1/edit')));
        $this->assertNull(RouteSpy::$calledMethod, 'A path with two segments must not match');
    }

    public function testUnknownRoutesRaiseANotFoundError(): void
    {
        $router = new Router();
        $router->get('/dashboard', [RouteSpy::class, 'index']);

        $status = null;
        try {
            $router->dispatch($this->request('GET', '/missing-route'));
        } catch (HttpException $exception) {
            $status = $exception->status();
        }

        $this->assertNull(RouteSpy::$calledMethod);
        $this->assertSame(404, $status);
    }

    public function testAKnownPathWithAnotherMethodIsNotAllowed(): void
    {
        $router = new Router();
        $router->get('/api/patients', [RouteSpy::class, 'index']);

        $status = null;
        try {
            $router->dispatch($this->request('DELETE', '/api/patients'));
        } catch (HttpException $exception) {
            $status = $exception->status();
        }

        $this->assertSame(405, $status);
    }

    private function request(string $method, string $uri): Request
    {
        return new Request([], [], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
        ]);
    }
}

final class RouteSpy
{
    public static ?string $calledMethod = null;
    public static array $arguments = [];
    public static array $sequence = [];

    public static function reset(): void
    {
        self::$calledMethod = null;
        self::$arguments = [];
        self::$sequence = [];
    }

    public function index(Request $request): void
    {
        $this->record('index');
    }

    public function show(Request $request, string $id): void
    {
        $this->record('show', [$id]);
    }

    public function store(Request $request): void
    {
        $this->record('store');
    }

    public function update(Request $request, string $id): void
    {
        $this->record('update', [$id]);
    }

    public function destroy(Request $request, string $id, string $diagnosisId): void
    {
        $this->record('destroy', [$id, $diagnosisId]);
    }

    private function record(string $method, array $arguments = []): void
    {
        self::$calledMethod = $method;
        self::$arguments = $arguments;
        self::$sequence[] = $method;
    }
}

final class MiddlewareSpy
{
    public function handle(Request $request): void
    {
        RouteSpy::$sequence[] = 'middleware';
    }
}
