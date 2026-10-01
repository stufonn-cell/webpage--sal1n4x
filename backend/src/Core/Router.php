<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('PATCH', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($parameters as $key => $value) {
                $request->setAttribute($key, $value);
            }

            foreach ($route['middleware'] as $middleware) {
                (new $middleware())->handle($request);
            }

            [$class, $action] = $route['handler'];
            (new $class())->{$action}($request, ...array_values($parameters));

            return;
        }

        if ($pathMatched) {
            throw new HttpException(405, 'Método no permitido para esta ruta.');
        }

        throw HttpException::notFound('El recurso solicitado no existe.');
    }

    private function add(string $method, string $pattern, array $handler, array $middleware): void
    {
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);

        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }
}
