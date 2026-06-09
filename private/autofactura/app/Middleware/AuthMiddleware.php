<?php
/**
 * AutoFactura - Middleware de Autenticación
 * Verifica que el usuario esté autenticado antes de acceder a rutas privadas.
 */

class AuthMiddleware
{
    /**
     * Verificar que el usuario esté autenticado.
     * Si no lo está, redirigir al login.
     */
    public static function check(): void
    {
        if (!is_authenticated()) {
            flash('error', 'Debes iniciar sesión para acceder.');
            Router::redirect('/login');
        }
    }

    /**
     * Verificar token CSRF en peticiones POST.
     */
    public static function verifyCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['_csrf_token'] ?? '';
            if (!verify_csrf($token)) {
                http_response_code(403);
                die('Error: Token CSRF inválido. Por favor recarga la página.');
            }
        }
    }

    /**
     * Verificar que el usuario autenticado sea superusuario.
     */
    public static function requireSuperuser(): void
    {
        self::check();

        if (!is_superuser()) {
            flash('error', 'No tienes permisos para acceder a esta sección.');
            Router::redirect('/dashboard');
        }
    }
}
