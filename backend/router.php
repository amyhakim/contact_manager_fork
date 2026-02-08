<?php
/**
 * Dynamic Router for Contact Manager API
 * Automatically discovers PHP files and creates clean URL routes
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users, but log them

// Set basic response header (CORS handled by Apache)
header('Content-Type: application/json');

// OPTIONS requests are handled by Apache via apache-api.conf

class DynamicRouter {
    private $routes = [];
    private $basePath;

    public function __construct($basePath = __DIR__) {
        $this->basePath = $basePath;
        $this->discoverRoutes();
    }

    /**
     * Automatically discover PHP files and create routes
     */
    private function discoverRoutes() {
        $phpFiles = glob($this->basePath . '/*.php');

        foreach ($phpFiles as $file) {
            $filename = basename($file, '.php');

            // Skip system files that shouldn't be routed
            if ($this->shouldSkipFile($filename)) {
                continue;
            }

            // Create clean URL route (remove .php extension)
            $cleanRoute = '/' . $filename;
            $this->routes[$cleanRoute] = $file;

            // Also map the .php version for backward compatibility
            $phpRoute = '/' . $filename . '.php';
            $this->routes[$phpRoute] = $file;

            error_log("[ROUTER] Registered route: $cleanRoute -> $filename.php");
        }

        // Add special system routes
        $this->addSystemRoutes();
    }

    /**
     * Check if a file should be skipped from routing
     */
    private function shouldSkipFile($filename) {
        $skipFiles = [
            'router',           // This router file
            'index',           // Index file
            'db',              // Database connection file (not a route)
            'middleware',      // Middleware file
            '.htaccess',       // Apache config
            'config'           // General config files
        ];

        return in_array(strtolower($filename), $skipFiles);
    }

    /**
     * Add special system routes that don't correspond to PHP files
     */
    private function addSystemRoutes() {
        // Health check endpoint
        $this->routes['/health'] = function() {
            header('Content-Type: text/plain');
            http_response_code(200);
            echo 'healthy';
            exit();
        };

        // API info route (maps to api_info.php if it exists)
        if (file_exists($this->basePath . '/api_info.php')) {
            $this->routes['/api'] = $this->basePath . '/api_info.php';
            $this->routes['/info'] = $this->basePath . '/api_info.php';
        }

        // Database test route (maps to db_test.php if it exists)
        if (file_exists($this->basePath . '/db_test.php')) {
            $this->routes['/db'] = $this->basePath . '/db_test.php';
        }
    }

    /**
     * Handle incoming request
     */
    public function handleRequest() {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Remove query parameters and decode URL
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = urldecode($path);

        // Remove trailing slash except for root
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        error_log("[ROUTER] Handling request: $requestMethod $path");

        // Handle root path
        if ($path === '/' || $path === '') {
            $this->handleRootRequest();
            return;
        }

        // Check if route exists
        if (isset($this->routes[$path])) {
            $this->executeRoute($this->routes[$path], $path);
        } else {
            $this->handleNotFound($path);
        }
    }

    /**
     * Execute a route (either file or function)
     */
    private function executeRoute($route, $path) {
        try {
            if (is_callable($route)) {
                // Execute function route
                $route();
            } elseif (is_string($route) && file_exists($route)) {
                // Include PHP file
                error_log("[ROUTER] Executing route: $path -> " . basename($route));
                require_once $route;
            } else {
                throw new Exception("Route handler not found or invalid");
            }
        } catch (Exception $e) {
            error_log("[ROUTER] Error executing route $path: " . $e->getMessage());
            $this->handleError($e, $path);
        }
    }

    /**
     * Handle root request - show API info
     */
    private function handleRootRequest() {
        $availableRoutes = array_keys($this->routes);
        sort($availableRoutes);

        $response = [
            'message' => 'Contact Manager API - Dynamic Router',
            'version' => '1.0',
            'status' => 'running',
            'timestamp' => date('Y-m-d H:i:s T'),
            'total_routes' => count($this->routes),
            'available_routes' => $availableRoutes,
            'usage' => [
                'note' => 'All routes support both clean URLs and .php extensions',
                'examples' => [
                    'POST /Login' => 'User authentication',
                    'POST /Register' => 'User registration',
                    'GET /swagger' => 'API documentation',
                    'GET /health' => 'Health check'
                ]
            ]
        ];

        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle 404 - route not found
     */
    private function handleNotFound($path) {
        error_log("[ROUTER] Route not found: $path");

        http_response_code(404);
        echo json_encode([
            'error' => 'Route not found',
            'path' => $path,
            'message' => 'The requested endpoint does not exist',
            'available_routes' => array_keys($this->routes),
            'suggestion' => 'Check the available routes above or visit / for API information'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle errors during route execution
     */
    private function handleError($exception, $path) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Internal server error',
            'path' => $path,
            'message' => 'An error occurred while processing your request',
            'debug' => [
                'error' => $exception->getMessage(),
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine()
            ]
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get all discovered routes (for debugging)
     */
    public function getRoutes() {
        return $this->routes;
    }

    /**
     * Add custom route manually
     */
    public function addRoute($path, $handler) {
        $this->routes[$path] = $handler;
        error_log("[ROUTER] Added custom route: $path");
    }
}

// Initialize router and handle request
try {
    $router = new DynamicRouter();

    // Log discovered routes for debugging
    error_log("[ROUTER] Initialized with " . count($router->getRoutes()) . " routes");

    // Handle the incoming request
    $router->handleRequest();

} catch (Exception $e) {
    error_log("[ROUTER] Critical error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Router initialization failed',
        'message' => 'A critical error occurred in the routing system'
    ]);
} catch (Error $e) {
    error_log("[ROUTER] Fatal error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'System error',
        'message' => 'A fatal system error occurred'
    ]);
}
?>
