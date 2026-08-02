<?php
declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, array $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

        // Strip subdirectory prefix so routes work whether the app is at the
        // domain root or inside a subdirectory (e.g. /mitbringliste/).
        // SCRIPT_NAME is always the full path to index.php, e.g. /subdir/index.php.
        $script   = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = ($script !== '') ? rtrim(dirname($script), '/\\') : '';
        if ($basePath !== '' && str_starts_with($uri, $basePath . '/')) {
            $uri = substr($uri, strlen($basePath));
        }

        // Normalize: leading slash, no trailing slash except root
        $uri = '/' . trim($uri, '/');

        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($method !== $routeMethod) {
                continue;
            }
            $params = $this->matchRoute($pattern, $uri);
            if ($params !== null) {
                [$class, $action] = $handler;
                (new $class())->$action($params);
                return;
            }
        }

        $this->sendNotFound();
    }

    private function matchRoute(string $pattern, string $uri): ?array
    {
        // Convert {placeholder} to named capture groups
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#u';

        if (!preg_match($regex, $uri, $matches)) {
            return null;
        }

        // Return only named captures (filter out integer keys)
        return array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
    }

    private function sendNotFound(): void
    {
        http_response_code(404);
        $tpl = defined('BASE_DIR') ? BASE_DIR . '/templates/errors/404.php' : null;
        if ($tpl && file_exists($tpl)) {
            include $tpl;
        } else {
            echo '<h1>404 – Seite nicht gefunden</h1>';
        }
    }
}
