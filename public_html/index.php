<?php
/**
 * AutoFactura - Front Controller
 * Punto de entrada de la aplicación.
 * Todas las peticiones pasan por aquí.
 */

// =============================================
// Configuración de errores
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);

// =============================================
// Definir constantes de rutas
// =============================================
define('BASE_PATH', dirname(__DIR__) . '/private/autofactura');
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('VIEWS_PATH', BASE_PATH . '/resources/views');
define('STORAGE_PATH', BASE_PATH . '/storage');

// =============================================
// Cargar variables de entorno
// =============================================
require_once CONFIG_PATH . '/env.php';
loadEnv(BASE_PATH . '/.env');

$appTimezone = env('APP_TIMEZONE', 'America/Mexico_City');
if (is_string($appTimezone) && $appTimezone !== '') {
    date_default_timezone_set($appTimezone);
}

// Activar errores en desarrollo
if (env('APP_DEBUG', false)) {
    ini_set('display_errors', 1);
}

// =============================================
// Iniciar sesión
// =============================================
session_start();

// =============================================
// Autoload manual (sin Composer)
// =============================================

// Servicios
require_once APP_PATH . '/Services/Database.php';
require_once APP_PATH . '/Services/EfectosFiscalesService.php';
require_once APP_PATH . '/Services/EfosValidationService.php';
require_once APP_PATH . '/Services/MailgunService.php';
require_once APP_PATH . '/Services/MensajesXyzService.php';
require_once APP_PATH . '/Services/ClipService.php';

// Helpers
require_once APP_PATH . '/Helpers/Router.php';
require_once APP_PATH . '/Helpers/functions.php';

set_exception_handler(function (Throwable $e): void {
    $errorId = 'ERR-' . date('YmdHis') . '-' . substr(uniqid('', true), -6);
    $message = function_exists('user_friendly_error_message')
        ? user_friendly_error_message($e)
        : 'Ocurrió un error inesperado.';

    if (function_exists('app_log')) {
        app_log(
            '[' . $errorId . '] Global exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(),
            'error'
        );
    }

    if (function_exists('render_error_page')) {
        render_error_page(500, $message, $e, $errorId);
        return;
    }

    http_response_code(500);
    echo '500 - Error interno del servidor. Ref: ' . htmlspecialchars($errorId);
});

// Modelos
require_once APP_PATH . '/Models/BaseModel.php';
require_once APP_PATH . '/Models/Business.php';
require_once APP_PATH . '/Models/BusinessSetting.php';
require_once APP_PATH . '/Models/StampPurchase.php';
require_once APP_PATH . '/Models/InvoiceConcept.php';
require_once APP_PATH . '/Models/AutofacturaRequest.php';
require_once APP_PATH . '/Models/AutofacturaCustomer.php';
require_once APP_PATH . '/Models/AutofacturaLog.php';

// Middleware
require_once APP_PATH . '/Middleware/AuthMiddleware.php';

// Controllers
require_once APP_PATH . '/Controllers/AuthController.php';
require_once APP_PATH . '/Controllers/DashboardController.php';
require_once APP_PATH . '/Controllers/BusinessSettingsController.php';
require_once APP_PATH . '/Controllers/InvoiceConceptsController.php';
require_once APP_PATH . '/Controllers/AutofacturaRequestsController.php';
require_once APP_PATH . '/Controllers/PublicInvoiceController.php';
require_once APP_PATH . '/Controllers/UsersController.php';
require_once APP_PATH . '/Controllers/StampPurchasesController.php';

// =============================================
// Configurar router y rutas
// =============================================
$router = new Router((string) env('APP_BASE_PATH', (string) (parse_url((string) env('APP_URL', ''), PHP_URL_PATH) ?? '')));
require_once BASE_PATH . '/routes/web.php';

// =============================================
// Despachar la solicitud
// =============================================
$router->dispatch();
