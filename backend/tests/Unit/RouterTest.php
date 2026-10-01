<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
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
        $router->get('/pacientes/{id}', [RouteSpy::class, 'show']);

        $router->dispatch($this->request('GET', '/pacientes/42'));

        $this->assertSame('show', RouteSpy::$calledMethod);
        $this->assertSame(['42'], RouteSpy::$arguments);
    }

    public function testItSupportsSeveralParameters(): void
    {
        $router = new Router();
        $router->delete('/pacientes/{id}/diagnosticos/{diagnosisId}', [RouteSpy::class, 'destroy']);

        $router->dispatch($this->request('DELETE', '/pacientes/7/diagnosticos/13'));

        $this->assertSame(['7', '13'], RouteSpy::$arguments);
    }

    public function testTheHttpMethodIsPartOfTheMatch(): void
    {
        $router = new Router();
        $router->get('/pacientes', [RouteSpy::class, 'index']);
        $router->post('/pacientes', [RouteSpy::class, 'store']);

        $router->dispatch($this->request('POST', '/pacientes'));

        $this->assertSame('store', RouteSpy::$calledMethod);
    }

    public function testMethodSpoofingIsHonoured(): void
    {
        $router = new Router();
        $router->put('/pacientes/{id}', [RouteSpy::class, 'update']);

        $router->dispatch(new Request([], ['_method' => 'PUT'], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/pacientes/5',
        ]));

        $this->assertSame('update', RouteSpy::$calledMethod);
    }

    public function testTrailingSlashesAreNormalised(): void
    {
        $router = new Router();
        $router->get('/agenda', [RouteSpy::class, 'index']);

        $router->dispatch($this->request('GET', '/agenda/'));

        $this->assertSame('index', RouteSpy::$calledMethod);
    }

    public function testQueryStringIsIgnoredWhenMatching(): void
    {
        $router = new Router();
        $router->get('/pacientes', [RouteSpy::class, 'index']);

        $router->dispatch($this->request('GET', '/pacientes?q=vega&page=2'));

        $this->assertSame('index', RouteSpy::$calledMethod);
    }

    public function testMiddlewareRunsBeforeTheController(): void
    {
        $router = new Router();
        $router->post('/pacientes', [RouteSpy::class, 'store'], [MiddlewareSpy::class]);

        $router->dispatch($this->request('POST', '/pacientes'));

        $this->assertSame(['middleware', 'store'], RouteSpy::$sequence);
    }

    public function testAParameterDoesNotMatchAcrossSegments(): void
    {
        $router = new Router();
        $router->get('/pacientes/{id}', [RouteSpy::class, 'show']);

        $this->assertThrows(fn () => $router->dispatch($this->request('GET', '/pacientes/1/editar')));
        $this->assertNull(RouteSpy::$calledMethod, 'La ruta con dos segmentos no debe coincidir');
    }

    public function testUnknownRoutesRaiseANotFoundError(): void
    {
        $router = new Router();
        $router->get('/dashboard', [RouteSpy::class, 'index']);

        $status = null;
        try {
            $router->dispatch($this->request('GET', '/ruta-inexistente'));
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
