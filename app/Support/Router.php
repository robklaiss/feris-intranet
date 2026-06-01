<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\AccessControl;
use App\Support\Auth;
use RuntimeException;

final class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    public function get(string $uri, array $handler): void
    {
        $this->add(['GET'], $uri, $handler);
    }

    public function post(string $uri, array $handler): void
    {
        $this->add(['POST'], $uri, $handler);
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, string> $handler
     */
    public function add(array $methods, string $uri, array $handler): void
    {
        $this->routes[] = [
            'methods' => $methods,
            'uri' => $uri,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method();

        if ($path === '/login' && Auth::check()) {
            return Response::redirect('/');
        }

        if (AccessControl::requiresAuthentication($path) && !Auth::check()) {
            return Response::redirect('/login');
        }

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            $permission = AccessControl::permissionFor($method, $path);
            if ($permission !== null && !Auth::can($permission)) {
                Session::flash('error', 'No tenés permisos para ejecutar esa acción.');
                return Response::redirect('/');
            }

            if ($method === 'POST' && !Csrf::validate((string) $request->input('_token'))) {
                Session::flash('error', 'Token CSRF inválido.');
                return Response::redirect($path);
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            $result = $controller->$action($request, ...array_values($params));

            return $result instanceof Response ? $result : Response::html((string) $result);
        }

        return Response::html('<h1>404</h1><p>Ruta no encontrada.</p>', 404);
    }
}
