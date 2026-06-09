<?php
/**
 * AutoFactura - Funciones Helper Globales
 */

/**
 * Generar URL completa de la aplicación
 */
function url(string $path = ''): string
{
    $base = env('APP_URL', '/autofactura');
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Obtener el base path interno de la aplicación.
 */
function app_base_path(): string
{
    $configured = trim((string) env('APP_BASE_PATH', ''));
    if ($configured !== '') {
        $normalized = '/' . trim($configured, '/');
        return $normalized === '/' ? '' : $normalized;
    }

    $appUrl = trim((string) env('APP_URL', ''));
    if ($appUrl !== '') {
        $path = trim((string) parse_url($appUrl, PHP_URL_PATH));
        if ($path !== '' && $path !== '/') {
            return '/' . trim($path, '/');
        }
    }

    return '';
}

/**
 * Generar URL de asset (CSS, JS, imágenes)
 */
function asset(string $path): string
{
    return url(ltrim($path, '/'));
}

/**
 * Renderizar una vista con datos
 */
function view(string $name, array $data = []): void
{
    $file = VIEWS_PATH . '/' . str_replace('.', '/', $name) . '.php';

    if (!file_exists($file)) {
        throw new RuntimeException("Vista no encontrada: {$name}");
    }

    extract($data);
    require $file;
}

/**
 * Generar token CSRF
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Generar campo hidden CSRF
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verificar token CSRF
 */
function verify_csrf(string $token): bool
{
    return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Escapar HTML para prevenir XSS
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar si el usuario está autenticado
 */
function is_authenticated(): bool
{
    return isset($_SESSION['business_id']) && !empty($_SESSION['business_id']);
}

/**
 * Obtener el ID del negocio autenticado
 */
function auth_business_id(): ?int
{
    return $_SESSION['business_id'] ?? null;
}

/**
 * Obtener el nombre del negocio autenticado
 */
function auth_business_name(): ?string
{
    return $_SESSION['business_name'] ?? null;
}

/**
 * Obtener email del usuario autenticado
 */
function auth_business_email(): ?string
{
    return $_SESSION['business_email'] ?? null;
}

/**
 * Obtener rol del usuario autenticado
 */
function auth_business_role(): string
{
    return $_SESSION['business_role'] ?? 'user';
}

/**
 * Validar si el usuario actual es superusuario
 */
function is_superuser(): bool
{
    return auth_business_role() === 'superuser';
}

/**
 * Obtener la URL del logo del negocio autenticado
 */
function business_logo(): ?string
{
    if (!is_authenticated()) {
        return null;
    }

    // Cachear en sesión para no consultar BD en cada request
    if (!isset($_SESSION['_business_logo_checked'])) {
        $settings = BusinessSetting::getByBusiness(auth_business_id());
        $_SESSION['business_logo'] = $settings['logo'] ?? null;
        $_SESSION['_business_logo_checked'] = true;
    }

    $logo = $_SESSION['business_logo'] ?? null;
    if ($logo) {
        return url('storage/uploads/logos/' . $logo);
    }
    return null;
}

/**
 * Refrescar cache del logo en sesión
 */
function refresh_business_logo(): void
{
    unset($_SESSION['_business_logo_checked']);
    unset($_SESSION['business_logo']);
}

/**
 * Establecer mensaje flash (para mostrar después de redirect)
 */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][$type] = $message;
}

/**
 * Obtener y limpiar mensaje flash
 */
function get_flash(string $type): ?string
{
    $message = $_SESSION['_flash'][$type] ?? null;
    unset($_SESSION['_flash'][$type]);
    return $message;
}

/**
 * Verificar si hay un flash de cierto tipo
 */
function has_flash(string $type): bool
{
    return isset($_SESSION['_flash'][$type]);
}

/**
 * Formatear monto como moneda MXN
 */
function format_money(float $amount): string
{
    return '$' . number_format($amount, 2, '.', ',');
}

/**
 * Formatear fecha
 */
function format_date(string $date, string $format = 'd/m/Y H:i'): string
{
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Log de aplicación
 */
function app_log(string $message, string $level = 'info'): void
{
    $logFile = STORAGE_PATH . '/logs/app.log';
    $date = date('Y-m-d H:i:s');
    $entry = "[{$date}] [{$level}] {$message}" . PHP_EOL;

    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $written = @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        error_log(trim($entry));
    }
}

/**
 * Obtener la llave de cifrado de la aplicación.
 */
function app_encryption_key(): string
{
    $configuredKey = trim((string) env('APP_KEY', ''));
    if ($configuredKey === '') {
        throw new RuntimeException('Falta APP_KEY en el archivo .env para proteger credenciales sensibles.');
    }

    if (str_starts_with($configuredKey, 'base64:')) {
        $decoded = base64_decode(substr($configuredKey, 7), true);
        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('APP_KEY no es válida. Usa una llave base64 de 32 bytes.');
        }
        return hash('sha256', $decoded, true);
    }

    return hash('sha256', $configuredKey, true);
}

/**
 * Cifrar texto sensible para almacenamiento.
 */
function encrypt_secret(?string $plainText): ?string
{
    if ($plainText === null || $plainText === '') {
        return null;
    }

    $cipher = require CONFIG_PATH . '/app.php';
    $cipherName = $cipher['cipher'] ?? 'AES-256-CBC';
    $ivLength = openssl_cipher_iv_length($cipherName);
    $iv = random_bytes($ivLength);
    $cipherText = openssl_encrypt($plainText, $cipherName, app_encryption_key(), OPENSSL_RAW_DATA, $iv);

    if ($cipherText === false) {
        throw new RuntimeException('No se pudo cifrar la información sensible.');
    }

    $mac = hash_hmac('sha256', $iv . $cipherText, app_encryption_key(), true);
    return 'enc:v1:' . base64_encode($iv . $mac . $cipherText);
}

/**
 * Descifrar texto sensible almacenado.
 */
function decrypt_secret(?string $payload): ?string
{
    if ($payload === null || $payload === '') {
        return null;
    }

    if (!str_starts_with($payload, 'enc:v1:')) {
        return $payload;
    }

    $cipher = require CONFIG_PATH . '/app.php';
    $cipherName = $cipher['cipher'] ?? 'AES-256-CBC';
    $raw = base64_decode(substr($payload, 7), true);
    if ($raw === false) {
        throw new RuntimeException('No se pudo leer el secreto cifrado.');
    }

    $ivLength = openssl_cipher_iv_length($cipherName);
    $macLength = 32;
    if (strlen($raw) <= ($ivLength + $macLength)) {
        throw new RuntimeException('Secreto cifrado incompleto.');
    }

    $iv = substr($raw, 0, $ivLength);
    $mac = substr($raw, $ivLength, $macLength);
    $cipherText = substr($raw, $ivLength + $macLength);
    $expectedMac = hash_hmac('sha256', $iv . $cipherText, app_encryption_key(), true);

    if (!hash_equals($expectedMac, $mac)) {
        throw new RuntimeException('La integridad del secreto cifrado no es válida.');
    }

    $plainText = openssl_decrypt($cipherText, $cipherName, app_encryption_key(), OPENSSL_RAW_DATA, $iv);
    if ($plainText === false) {
        throw new RuntimeException('No se pudo descifrar la información sensible.');
    }

    return $plainText;
}

/**
 * Crear un directorio privado accesible para PHP pero bloqueado por reglas HTTP.
 */
function ensure_private_directory(string $directory): void
{
    if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo crear el directorio privado: ' . $directory);
    }

    @chmod($directory, 0777);
}

/**
 * Escribir un archivo cifrado con permisos restrictivos.
 */
function store_encrypted_file(string $sourcePath, string $targetPath): void
{
    $contents = @file_get_contents($sourcePath);
    if ($contents === false) {
        throw new RuntimeException('No se pudo leer el archivo sensible cargado.');
    }

    $encrypted = encrypt_secret($contents);
    if ($encrypted === null || @file_put_contents($targetPath, $encrypted, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar el archivo sensible de forma segura.');
    }

    @chmod($targetPath, 0640);
}

/**
 * Leer y descifrar un archivo privado.
 */
function read_encrypted_file(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $contents = @file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('No se pudo leer el archivo sensible almacenado.');
    }

    return decrypt_secret($contents);
}

/**
 * Mensaje amigable para usuarios finales según tipo de excepción.
 */
function user_friendly_error_message(Throwable $e): string
{
    if ($e instanceof DatabaseQueryException) {
        if ($e->getSqlState() === '42S22') {
            return 'La base de datos requiere actualización de estructura. Ejecuta las consultas pendientes de schema.sql/seed.sql.';
        }
        if ($e->isIntegrityViolation()) {
            return 'No se pudo completar la operación porque existen datos relacionados.';
        }
        return 'Ocurrió un problema al consultar la base de datos.';
    }

    if ($e instanceof RuntimeException && str_contains($e->getMessage(), 'Token CSRF')) {
        return 'Tu sesión de formulario expiró. Recarga la página e inténtalo de nuevo.';
    }

    return 'Ocurrió un error inesperado. Inténtalo nuevamente en unos minutos.';
}

/**
 * Render estándar de error para toda la app.
 */
function render_error_page(int $statusCode, string $message, ?Throwable $e = null, ?string $errorId = null): void
{
    http_response_code($statusCode);

    $viewPath = VIEWS_PATH . '/errors/500.php';
    if (file_exists($viewPath)) {
        $errorMessage = $message;
        $debugException = (env('APP_DEBUG', false) && $e) ? (string) $e : null;
        $errorReference = $errorId;
        require $viewPath;
        return;
    }

    echo '<h1>' . $statusCode . ' - Error</h1>';
    echo '<p>' . e($message) . '</p>';
    if ($errorId) {
        echo '<p>Referencia: ' . e($errorId) . '</p>';
    }
}
