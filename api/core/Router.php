<?php
declare(strict_types=1);

/**
 * Small, explicit API router.
 *
 * Routes are allow-listed in api/routes.php. It does not infer a PHP file
 * from the URL, preventing arbitrary module files from being invoked.
 */
final class Router
{
    /** @var array<string, array<string, string>> */
    private array $routes = [];

    public function add(string $method, string $path, string $handler): self
    {
        $method = strtoupper($method);
        $path = $this->normalisePath($path);
        $this->routes[$method][$path] = $handler;
        return $this;
    }

    /** @param array<string, array<string, string>> $routes */
    public function register(array $routes): self
    {
        foreach ($routes as $method => $items) {
            foreach ($items as $path => $handler) {
                $this->add($method, $path, $handler);
            }
        }
        return $this;
    }

    public function dispatch(string $path): never
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = $this->normalisePath($path);

        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            [$handler, $parameters] = $this->matchPattern($method, $path);
            foreach ($parameters as $name => $value) {
                $_GET[$name] = $value;
            }
        }

        if ($handler === null) {
            if ($this->hasPath($path)) {
                Response::error('HTTP method is not allowed for this route.', null, 405);
            }
            Response::notFound('API route was not found.');
        }

        if (!is_file($handler)) {
            ErrorHandler::log('Configured route handler is missing.', ['route' => $path]);
            Response::serverError();
        }

        require $handler;
        Response::serverError('Route handler ended without a response.');
    }

    private function hasPath(string $path): bool
    {
        foreach ($this->routes as $routes) {
            if (isset($routes[$path]) || $this->matchPatternRoutes($routes, $path)[0] !== null) {
                return true;
            }
        }
        return false;
    }

    /** @return array{0: ?string, 1: array<string, string>} */
    private function matchPattern(string $method, string $path): array
    {
        return $this->matchPatternRoutes($this->routes[$method] ?? [], $path);
    }

    /** @param array<string, string> $routes @return array{0: ?string, 1: array<string, string>} */
    private function matchPatternRoutes(array $routes, string $path): array
    {
        foreach ($routes as $pattern => $handler) {
            if (!str_contains($pattern, '{')) {
                continue;
            }
            $names = [];
            $parts = preg_split('/(\{[A-Za-z][A-Za-z0-9_]*\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts === false) {
                continue;
            }
            $regex = '';
            foreach ($parts as $part) {
                if (preg_match('/^\{([A-Za-z][A-Za-z0-9_]*)\}$/', $part, $parameter)) {
                    $names[] = $parameter[1];
                    $regex .= '([^/]+)';
                } else {
                    $regex .= preg_quote($part, '#');
                }
            }
            if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
                continue;
            }
            $parameters = [];
            foreach ($names as $index => $name) {
                $parameters[$name] = rawurldecode($matches[$index + 1]);
            }
            return [$handler, $parameters];
        }
        return [null, []];
    }

    private function normalisePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }
}
