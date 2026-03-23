<?php
declare(strict_types=1);

namespace Heirloom;

/**
 * Simple regex-based HTTP router that matches URI patterns with named placeholders
 * (e.g. /painting/{id}) and dispatches to callable handlers.
 */
class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->routes['GET'][] = [$pattern, $handler];
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->routes['POST'][] = [$pattern, $handler];
    }

    /**
     * Match the request against registered routes and invoke the first matching handler.
     * Renders a 404 error page if no route matches.
     */
    public function dispatch(string $method, string $uri): void
    {
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as [$pattern, $handler]) {
            $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($handler, ...array_values($params));
                return;
            }
        }

        http_response_code(404);
        Template::render('error', [
            'code' => 404,
            'message' => 'The page you requested could not be found.',
            'noLayout' => true,
        ]);
    }
}
