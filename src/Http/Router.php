<?php

declare(strict_types=1);

namespace SearPHP\Http;

final class Router
{
    /** @var array<int, array{method: string, path: string, handler: callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->normalizePath($request->path);
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if ($route['path'] !== $path) {
                continue;
            }
            $pathMatched = true;
            $methodMatches = $route['method'] === $request->method
                || ($request->method === 'HEAD' && $route['method'] === 'GET');
            if (!$methodMatches) {
                continue;
            }

            /** @var Response $response */
            $response = ($route['handler'])($request);

            return $response;
        }

        if ($pathMatched) {
            return Response::error('method_not_allowed', 'Method not allowed', 405, !$request->accepts('application/json'));
        }

        return Response::error('not_found', 'Route not found', 404, !$request->accepts('application/json'));
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
