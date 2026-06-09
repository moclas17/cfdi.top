<?php
/**
 * AutoFactura - Definición de Rutas
 *
 * Aquí se registran todas las rutas de la aplicación.
 * Variable $router disponible desde el front controller.
 */

// =============================================
// Rutas Públicas
// =============================================

// Autenticación
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/verify-account/{token}', [AuthController::class, 'verifyEmail']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/webhooks/clip', function() {
    http_response_code(405);
    echo 'Método no permitido';
});
$router->post('/webhooks/clip', [StampPurchasesController::class, 'webhook']);

// Autofactura pública (link para clientes)
$router->get('/f/{token}/efos-check', [PublicInvoiceController::class, 'efosCheck']);
$router->get('/f/{token}', [PublicInvoiceController::class, 'show']);
$router->post('/f/{token}', [PublicInvoiceController::class, 'submit']);
$router->post('/f/{token}/send-email', [PublicInvoiceController::class, 'sendInvoiceEmail']);
$router->post('/f/{token}/send-whatsapp', [PublicInvoiceController::class, 'sendInvoiceWhatsapp']);

// Servir archivos de storage (logos, etc.)
$router->get('/storage/uploads/logos/{filename}', function(string $filename) {
    $path = STORAGE_PATH . '/uploads/logos/' . basename($filename);
    if (!file_exists($path)) {
        http_response_code(404);
        echo 'Archivo no encontrado';
        return;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $path);
    finfo_close($finfo);
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile($path);
});

$router->get('/storage/invoices/{rfc}/{year}/{month}/{filename}', function(string $rfc, string $year, string $month, string $filename) {
    $safeRfc = preg_replace('/[^A-Z0-9]/', '', strtoupper($rfc));
    $safeYear = preg_replace('/\D+/', '', $year);
    $safeMonth = preg_replace('/\D+/', '', $month);
    $safeFilename = basename($filename);
    $path = STORAGE_PATH . '/invoices/' . $safeRfc . '/' . $safeYear . '/' . $safeMonth . '/' . $safeFilename;

    if (!file_exists($path)) {
        http_response_code(404);
        echo 'Archivo no encontrado';
        return;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'xml') {
        header('Content-Type: application/xml; charset=UTF-8');
    } elseif ($ext === 'pdf') {
        header('Content-Type: application/pdf');
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        header('Content-Type: ' . $mime);
    }

    header('Cache-Control: public, max-age=86400');
    readfile($path);
});

$router->get('/storage/invoices/{filename}', function(string $filename) {
    $path = STORAGE_PATH . '/invoices/' . basename($filename);
    if (!file_exists($path)) {
        http_response_code(404);
        echo 'Archivo no encontrado';
        return;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'xml') {
        header('Content-Type: application/xml; charset=UTF-8');
    } elseif ($ext === 'pdf') {
        header('Content-Type: application/pdf');
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        header('Content-Type: ' . $mime);
    }

    header('Cache-Control: public, max-age=86400');
    readfile($path);
});

// Home pública / dashboard autenticado
$router->get('/', function() {
    if (is_authenticated()) {
        Router::redirect('/dashboard');
    }

    view('public.landing');
});

// =============================================
// Rutas Privadas (requieren autenticación)
// =============================================

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index']);

// Timbres
$router->get('/stamp-purchases', [StampPurchasesController::class, 'index']);
$router->post('/stamp-purchases/checkout', [StampPurchasesController::class, 'createCheckout']);
$router->post('/stamp-purchases/add-self-credits', [StampPurchasesController::class, 'addSelfCredits']);
$router->post('/stamp-purchases/execute-transfer', [StampPurchasesController::class, 'executeTransfer']);
$router->post('/stamp-purchases/resend-invoice-link', [StampPurchasesController::class, 'resendInvoiceLink']);
$router->get('/stamp-purchases/return', [StampPurchasesController::class, 'handleReturn']);

// Configuración del negocio
$router->get('/business-settings', [BusinessSettingsController::class, 'index']);
$router->post('/business-settings', [BusinessSettingsController::class, 'update']);
$router->post('/business-settings/test-connection', [BusinessSettingsController::class, 'testConnection']);

// Conceptos de facturación
$router->get('/invoice-concepts', [InvoiceConceptsController::class, 'index']);
$router->post('/invoice-concepts', [InvoiceConceptsController::class, 'store']);
$router->post('/invoice-concepts/update', [InvoiceConceptsController::class, 'update']);
$router->post('/invoice-concepts/toggle-default', [InvoiceConceptsController::class, 'toggleDefault']);
$router->post('/invoice-concepts/toggle-active', [InvoiceConceptsController::class, 'toggleActive']);
$router->post('/invoice-concepts/delete', [InvoiceConceptsController::class, 'delete']);

// Solicitudes de autofactura
$router->get('/autofactura-requests', [AutofacturaRequestsController::class, 'index']);
$router->post('/autofactura-requests', [AutofacturaRequestsController::class, 'store']);
$router->post('/autofactura-requests/delete', [AutofacturaRequestsController::class, 'delete']);
$router->post('/autofactura-requests/resend-email', [AutofacturaRequestsController::class, 'resendEmail']);
$router->post('/autofactura-requests/regenerate-pdf', [AutofacturaRequestsController::class, 'regeneratePdf']);
$router->get('/autofactura-requests/{id}', [AutofacturaRequestsController::class, 'show']);

// Usuarios SaaS
$router->get('/users', [UsersController::class, 'index']);
$router->post('/users/profile', [UsersController::class, 'updateProfile']);
$router->post('/users/update', [UsersController::class, 'updateUser']);
$router->post('/stamp-purchases/transfer-credits', [StampPurchasesController::class, 'transferCredits']);
