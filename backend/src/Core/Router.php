<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

/**
 * Minimal router. Middleware are class names, optionally followed by one
 * argument after a colon ("Throttle:login"). Parameters named "id" or ending
 * in "Id" only match digits, so "/patients/1abc" never reaches a controller.
 */
final class Router
{
    private array $routes = [];
    private array $before = [];

    /** Middleware that run on every request, before the route is even matched. */
    public function before(string ...$middleware): void
    {
        array_push($this->before, ...$middleware);
    }

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

    /**
     * Registered routes (method, pattern, middleware), for inspection in tests.
     *
     * @return list<array{method: string, pattern: string, middleware: array}>
     */
    public function routes(): array
    {
        return array_map(
            static fn (array $route): array => [
                'method' => $route['method'],
                'pattern' => $route['pattern'],
                'middleware' => $route['middleware'],
            ],
            $this->routes
        );
    }

    /** @return list<string> */
    public function globalMiddleware(): array
    {
        return $this->before;
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->before as $middleware) {
            self::runMiddleware($middleware, $request);
        }

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
                self::runMiddleware($middleware, $request);
            }

            [$class, $action] = $route['handler'];
            (new $class())->{$action}($request, ...array_values($parameters));

            return;
        }

        if ($pathMatched) {
            throw new HttpException(405, 'This method is not allowed for this route.');
        }

        throw HttpException::notFound("The requested resource doesn't exist.");
    }

    private static function runMiddleware(string $definition, Request $request): void
    {
        [$class, $argument] = array_pad(explode(':', $definition, 2), 2, null);
        $instance = $argument === null ? new $class() : new $class($argument);
        $instance->handle($request);
    }

    private function add(string $method, string $pattern, array $handler, array $middleware): void
    {
        $regex = preg_replace_callback(
            '#\{(\w+)\}#',
            static fn (array $match): string => sprintf(
                '(?P<%s>%s)',
                $match[1],
                $match[1] === 'id' || str_ends_with($match[1], 'Id') ? '\d{1,18}' : '[^/]+'
            ),
            $pattern
        );

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }
}
