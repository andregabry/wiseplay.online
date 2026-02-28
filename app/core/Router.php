<?php

declare(strict_types=1);

namespace App\core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[$method][] = [
            'path' => rtrim($path, '/') ?: '/',
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $normalized = rtrim($path, '/') ?: '/';
        $candidates = $this->routes[$method] ?? [];

        foreach ($candidates as $route) {
            $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']);
            $regex = '#^' . $regex . '$#';

            if (!preg_match($regex, $normalized, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {
                    $params[$k] = $v;
                }
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $methodName] = $handler;
                $controller = new $class();
                $controller->$methodName($params);
                return;
            }

            $handler($params);
            return;
        }

        Response::notFound();
    }
}
