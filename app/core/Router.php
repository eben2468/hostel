<?php
namespace App\Core;

/** Minimal regex-based router supporting GET/POST and {param} placeholders. */
class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:array}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        // Convert "/students/{id}/edit" to a regex with named groups.
        $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . rtrim($pattern, '/') . '/?$#';
        $this->routes[$method][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip the base path (e.g. /hostel/public) so routes are clean.
        $base = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = $route['handler'];
                $controller = new $class();
                call_user_func_array([$controller, $action], array_values($params));
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', [], 'blank');
    }
}
