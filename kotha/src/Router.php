<?php
// src/Router.php

class Router {
    private $routes = [];

    /**
     * Add a route.
     * @param string $method GET, POST, etc.
     * @param string $path Route path (e.g. '/login', '/chat/{id}')
     * @param mixed $handler Controller@method string or callable
     */
    public function add(string $method, string $path, $handler): void {
        $path = trim($path, '/');
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler
        ];
    }

    /**
     * Dispatch the current request.
     */
    public function dispatch(string $method, string $uri): void {
        $method = strtoupper($method);
        $parsedUrl = parse_url($uri);
        $path = trim($parsedUrl['path'] ?? '', '/');

        // Handle running in a subdirectory (e.g. /kotha)
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $scriptDirClean = trim($scriptDir, '/\\');
        
        if (!empty($scriptDirClean) && strpos($path, $scriptDirClean) === 0) {
            $path = substr($path, strlen($scriptDirClean));
            $path = trim($path, '/');
        }

        foreach ($this->routes as $route) {
            // Replace {param} with regex pattern ([^/]+)
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#i';

            if ($route['method'] === $method && preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove first match (full string)

                $handler = $route['handler'];

                if (is_string($handler) && strpos($handler, '@') !== false) {
                    list($controllerName, $methodName) = explode('@', $handler);
                    $controllerClass = "Controller\\" . $controllerName;

                    if (class_exists($controllerClass)) {
                        $controller = new $controllerClass();
                        if (method_exists($controller, $methodName)) {
                            call_user_func_array([$controller, $methodName], $matches);
                            return;
                        }
                    }
                    
                    header("HTTP/1.1 500 Internal Server Error");
                    echo "Controller or action not found: " . e($controllerClass) . "@" . e($methodName);
                    return;
                }

                if (is_callable($handler)) {
                    call_user_func_array($handler, $matches);
                    return;
                }
            }
        }

        // Route not found fallback - if user is logged in, redirect to dashboard. Else, root.
        // Let's implement a clean 404 layout or redirect.
        header("HTTP/1.1 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The requested route '/" . e($path) . "' does not exist on this server.</p>";
    }
}
