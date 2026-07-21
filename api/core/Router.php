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

        if (!isset($this->routes[$method][$path])) {
            if ($this->hasPath($path)) {
                Response::error('HTTP method is not allowed for this route.', null, 405);
            }
            Response::notFound('API route was not found.');
        }

        $handler = $this->routes[$method][$path];
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
            if (isset($routes[$path])) {
                return true;
            }
        }
        return false;
    }

    private function normalisePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }
}
