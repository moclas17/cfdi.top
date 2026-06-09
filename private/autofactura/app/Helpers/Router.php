<?php
/**
 * AutoFactura - Router Simple
 * Gestiona las rutas de la aplicación con soporte GET y POST.
 */

class Router
{
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '/autofactura')
    {
        if ($basePath === '/autofactura') {
            $configured = trim((string) env('APP_BASE_PATH', ''));
            if ($configured !== '') {
                $basePath = $configured;
            } else {
                $appUrl = trim((string) env('APP_URL', ''));
                $basePath = (string) (parse_url($appUrl, PHP_URL_PATH) ?? '');
            }
        }

        $normalized = '/' . trim($basePath, '/');
        $this->basePath = $normalized === '/' ? '' : rtrim($normalized, '/');
    }

    /**
     * Registrar ruta GET
     */
    public function get(string $path, callable|array $handler): self
    {
        $this->addRoute('GET', $path, $handler);
        return $this;
    }

    /**
     * Registrar ruta POST
     */
    public function post(string $path, callable|array $handler): self
    {
        $this->addRoute('POST', $path, $handler);
        return $this;
    }

    /**
     * Agregar ruta interna
     */
    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        // Convertir parámetros de ruta {param} a regex (acepta puntos y otros caracteres válidos de segmento)
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * Resolver y ejecutar la ruta actual
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extraer solo los parámetros nombrados
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $handler = $route['handler'];

                try {
                    if (is_array($handler)) {
                        // [ControllerClass, method]
                        [$class, $action] = $handler;
                        $controller = new $class();
                        call_user_func_array([$controller, $action], $params);
                    } else {
                        // Callable / Closure
                        call_user_func_array($handler, $params);
                    }
                } catch (Throwable $e) {
                    $this->handleException($e);
                }

                return;
            }
        }

        // Ruta no encontrada
        $this->notFound();
    }

    /**
     * Obtener URI relativa sin basePath ni query string
     */
    private function getUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remover basePath
        if (!empty($this->basePath) && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }

        // Limpiar
        $uri = '/' . trim($uri, '/');
        if ($uri === '/') {
            return '/';
        }

        return rtrim($uri, '/');
    }

    /**
     * Respuesta 404
     */
    private function notFound(): void
    {
        http_response_code(404);
        $viewPath = defined('VIEWS_PATH')
            ? VIEWS_PATH . '/errors/404.php'
            : dirname(__DIR__, 2) . '/resources/views/errors/404.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            echo '<h1>404 - Página no encontrada</h1>';
            echo '<p>La página que buscas no existe.</p>';
            echo '<a href="' . $this->basePath . '/">Volver al inicio</a>';
        }
    }

    /**
     * Respuesta controlada para errores inesperados.
     */
    private function handleException(Throwable $e): void
    {
        $errorId = 'ERR-' . date('YmdHis') . '-' . substr(uniqid('', true), -6);
        $message = function_exists('user_friendly_error_message')
            ? user_friendly_error_message($e)
            : 'Ocurrió un error inesperado.';

        if (function_exists('app_log')) {
            app_log(
                '[' . $errorId . '] Unhandled exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(),
                'error'
            );
        }

        // En operaciones POST, regresar al flujo con mensaje flash.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && function_exists('flash')) {
            flash('error', $message . ' (Ref: ' . $errorId . ')');
            $back = $_SERVER['HTTP_REFERER'] ?? '';
            if ($back !== '') {
                header('Location: ' . $back);
                exit;
            }
            self::redirect('/dashboard');
        }

        if (function_exists('render_error_page')) {
            render_error_page(500, $message, $e, $errorId);
            return;
        }

        http_response_code(500);
        echo '<h1>500 - Error interno del servidor</h1>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '<p>Referencia: ' . htmlspecialchars($errorId) . '</p>';
    }

    /**
     * Redirigir a una ruta
     */
    public static function redirect(string $path): void
    {
        $configured = trim((string) env('APP_BASE_PATH', ''));
        if ($configured !== '') {
            $base = '/' . trim($configured, '/');
            $base = $base === '/' ? '' : $base;
        } else {
            $appUrl = trim((string) env('APP_URL', ''));
            $parsedPath = (string) (parse_url($appUrl, PHP_URL_PATH) ?? '');
            $base = ($parsedPath !== '' && $parsedPath !== '/') ? '/' . trim($parsedPath, '/') : '';
        }

        header('Location: ' . $base . $path);
        exit;
    }
}
