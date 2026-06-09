<?php
/**
 * Controller: BusinessSettingsController
 */

class BusinessSettingsController
{
    private const CSD_MAX_SIZE = 1024 * 1024;
    private const DEFAULT_EF_API_URL = 'https://efectosfiscales.mx/wscfditop/?wsdl';

    public function __construct()
    {
        AuthMiddleware::check();
    }

    /**
     * Mostrar configuración
     */
    public function index(): void
    {
        $businessId = auth_business_id();
        $settings = BusinessSetting::getByBusiness($businessId);
        $business = Business::find((int) $businessId);

        view('dashboard.business-settings', [
            'settings' => $settings,
            'business' => $business,
            'defaultEfApiUrl' => trim((string) env('EF_DEFAULT_API_URL', self::DEFAULT_EF_API_URL)),
        ]);
    }

    /**
     * Actualizar configuración
     */
    public function update(): void
    {
        AuthMiddleware::verifyCsrf();

        $businessId = auth_business_id();
        $existing = BusinessSetting::getByBusiness($businessId) ?? [];
        $runtimeSettings = BusinessSetting::getRuntimeByBusiness($businessId) ?? [];
        $efAssignFeedback = null;
        $data = [
            'invoicing_mode' => 'ef_api',
            'rfc_emisor'     => trim($_POST['rfc_emisor'] ?? '') ?: (string) ($existing['rfc_emisor'] ?? ''),
            'nombre_emisor'  => trim($_POST['nombre_emisor'] ?? '') ?: (string) ($existing['nombre_emisor'] ?? ''),
            'regimen_fiscal' => trim($_POST['regimen_fiscal'] ?? ''),
            'codigo_postal'  => trim($_POST['codigo_postal'] ?? ''),
            'api_url'        => trim($_POST['api_url'] ?? '') ?: (string) ($existing['api_url'] ?? trim((string) env('EF_DEFAULT_API_URL', self::DEFAULT_EF_API_URL))),
            'api_user'       => trim($_POST['api_user'] ?? '') ?: (string) ($existing['api_user'] ?? ''),
            'commercial_name'=> trim($_POST['commercial_name'] ?? ''),
            'template_color' => $this->normalizeHexColor($_POST['template_color'] ?? '#359BE3', '#359BE3'),
            'font_color' => $this->normalizeHexColor($_POST['font_color'] ?? '#111111', '#111111'),
            'link_expiration_days' => (int) ($_POST['link_expiration_days'] ?? 3),
        ];
        $plainApiPassword = trim($_POST['api_password'] ?? '');
        $plainApiKey = trim($_POST['api_key'] ?? '');
        $csdPassword = trim($_POST['csd_password'] ?? '');
        $removeCsd = isset($_POST['remove_csd']) && $_POST['remove_csd'] === '1';

        if ($data['link_expiration_days'] < 1 || $data['link_expiration_days'] > 30) {
            $data['link_expiration_days'] = 3;
        }

        $data['api_password'] = $plainApiPassword !== ''
            ? encrypt_secret($plainApiPassword)
            : ($existing['api_password'] ?? null);
        $data['api_key'] = $plainApiKey !== ''
            ? encrypt_secret($plainApiKey)
            : ($existing['api_key'] ?? null);

        // Procesar subida de logo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo = $_FILES['logo'];

            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $logo['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                flash('error', 'Tipo de archivo no permitido. Usa JPG, PNG, GIF, WebP o SVG.');
                Router::redirect('/business-settings');
                return;
            }

            // Validar tamaño (máx 2MB)
            if ($logo['size'] > 2 * 1024 * 1024) {
                flash('error', 'El logo no debe superar los 2MB.');
                Router::redirect('/business-settings');
                return;
            }

            // Generar nombre único
            $ext = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                default => 'png',
            };
            $filename = 'logo_' . $businessId . '_' . time() . '.' . $ext;
            $uploadDir = STORAGE_PATH . '/uploads/logos/';
            $uploadPath = $uploadDir . $filename;

            // Eliminar logo anterior si existe
            if ($existing && !empty($existing['logo'])) {
                $oldPath = STORAGE_PATH . '/uploads/logos/' . $existing['logo'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // Mover archivo
            if (move_uploaded_file($logo['tmp_name'], $uploadPath)) {
                $data['logo'] = $filename;
            } else {
                flash('error', 'Error al subir el logo. Verifica los permisos del directorio.');
                Router::redirect('/business-settings');
                return;
            }
        }

        // Remover logo si se solicitó
        if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
            if ($existing && !empty($existing['logo'])) {
                $oldPath = STORAGE_PATH . '/uploads/logos/' . $existing['logo'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            $data['logo'] = null;
        }

        try {
            $this->handleCsdFiles($businessId, $existing, $runtimeSettings, $data, $csdPassword, $removeCsd, $efAssignFeedback);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            Router::redirect('/business-settings');
            return;
        }

        BusinessSetting::upsert($businessId, $data);
        refresh_business_logo();
        AutofacturaLog::log('settings_updated', null, $businessId);
        flash('success', 'Configuración actualizada correctamente.');
        if (is_string($efAssignFeedback) && trim($efAssignFeedback) !== '') {
            flash('info', $efAssignFeedback);
        }
        Router::redirect('/business-settings');
    }

    private function normalizeHexColor(mixed $value, string $default): string
    {
        $color = strtoupper(trim((string) $value));
        if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
            return $color;
        }

        return strtoupper($default);
    }

    /**
     * Probar conexión de EfectosFiscales API usando wsGetCredit
     */
    public function testConnection(): void
    {
        AuthMiddleware::verifyCsrf();

        $businessId = auth_business_id();
        $settings = BusinessSetting::getByBusiness($businessId) ?? [];
        $runtimeSettings = BusinessSetting::getRuntimeByBusiness($businessId) ?? [];
        $mode = trim((string) ($settings['invoicing_mode'] ?? 'ef_api'));

        if ($mode !== 'ef_api') {
            flash('error', 'La prueba de conexión solo aplica en modo EfectosFiscales API.');
            Router::redirect('/business-settings');
            return;
        }

        $apiConfig = [
            'api_url'      => trim((string) ($settings['api_url'] ?? $_POST['api_url'] ?? env('EF_DEFAULT_API_URL', self::DEFAULT_EF_API_URL))),
            'api_user'     => trim((string) ($settings['api_user'] ?? $_POST['api_user'] ?? '')),
            'api_password' => trim($_POST['api_password'] ?? '') ?: (string) ($runtimeSettings['api_password'] ?? ''),
            'api_key'      => trim($_POST['api_key'] ?? '') ?: (string) ($runtimeSettings['api_key'] ?? ''),
        ];

        $result = EfectosFiscalesService::wsGetCredit($apiConfig);

        if (!empty($result['success'])) {
            $creditText = isset($result['credit']) ? ' Crédito disponible: ' . $result['credit'] . '.' : '';
            flash('success', 'Conexión exitosa con EfectosFiscales (wsGetCredit).' . $creditText);
            AutofacturaLog::log('ef_api_connection_ok', null, $businessId, $result['message'] ?? 'Conexión OK');
            Router::redirect('/business-settings');
            return;
        }

        flash('error', 'No se pudo conectar con EfectosFiscales: ' . ($result['message'] ?? 'Error desconocido.'));
        AutofacturaLog::log('ef_api_connection_error', null, $businessId, $result['message'] ?? 'Error desconocido');
        Router::redirect('/business-settings');
    }

    /**
     * Procesar CSD de forma privada y cifrada.
     */
    private function handleCsdFiles(
        int $businessId,
        array $existing,
        array $runtimeSettings,
        array &$data,
        string $csdPassword,
        bool $removeCsd,
        ?string &$efAssignFeedback = null
    ): void {
        $business = Business::find($businessId);
        if (!$business) {
            throw new RuntimeException('No se encontró el negocio que se está configurando.');
        }

        $keyUpload = $_FILES['csd_key'] ?? null;
        $cerUpload = $_FILES['csd_cer'] ?? null;
        $keyProvided = $keyUpload && ($keyUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $cerProvided = $cerUpload && ($cerUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $passwordProvided = $csdPassword !== '';
        $hasExistingCsd = !empty($existing['csd_key_path']) && !empty($existing['csd_cer_path']) && !empty($existing['csd_password']);

        if ($removeCsd) {
            $this->deleteCsdFiles($existing);
            $data['csd_key_path'] = null;
            $data['csd_cer_path'] = null;
            $data['csd_password'] = null;
            $data['csd_uploaded_at'] = null;
            $data['csd_rfc'] = null;
            $data['csd_valid_from'] = null;
            $data['csd_valid_to'] = null;
            return;
        }

        if (!$keyProvided && !$cerProvided && !$passwordProvided) {
            $data['csd_key_path'] = $existing['csd_key_path'] ?? null;
            $data['csd_cer_path'] = $existing['csd_cer_path'] ?? null;
            $data['csd_password'] = $existing['csd_password'] ?? null;
            $data['csd_uploaded_at'] = $existing['csd_uploaded_at'] ?? null;
            $data['csd_rfc'] = $existing['csd_rfc'] ?? null;
            $data['csd_valid_from'] = $existing['csd_valid_from'] ?? null;
            $data['csd_valid_to'] = $existing['csd_valid_to'] ?? null;
            return;
        }

        if (!$keyProvided || !$cerProvided || !$passwordProvided) {
            throw new RuntimeException('Para actualizar los sellos CSD debes cargar .key, .cer y contraseña al mismo tiempo.');
        }

        $this->validateCsdUpload($keyUpload, 'key');
        $this->validateCsdUpload($cerUpload, 'cer');
        $certificateInfo = $this->extractCertificateMetadata($cerUpload['tmp_name']);
        $apiCredentials = $this->provisionEfApiCredentials($business, $certificateInfo, $existing, $runtimeSettings);
        $efAssignFeedback = $apiCredentials['feedback_message'] ?? null;

        $privateDir = STORAGE_PATH . '/private/csd/business_' . $businessId;
        ensure_private_directory(STORAGE_PATH . '/private');
        ensure_private_directory(STORAGE_PATH . '/private/csd');
        ensure_private_directory($privateDir);
        $this->writeApacheDenyRule(STORAGE_PATH . '/private/.htaccess');
        $this->writeApacheDenyRule(STORAGE_PATH . '/private/csd/.htaccess');
        $this->writeApacheDenyRule($privateDir . '/.htaccess');

        $keyRelativePath = 'private/csd/business_' . $businessId . '/csd.key.enc';
        $cerRelativePath = 'private/csd/business_' . $businessId . '/csd.cer.enc';
        store_encrypted_file($keyUpload['tmp_name'], STORAGE_PATH . '/' . $keyRelativePath);
        store_encrypted_file($cerUpload['tmp_name'], STORAGE_PATH . '/' . $cerRelativePath);

        $data['csd_key_path'] = $keyRelativePath;
        $data['csd_cer_path'] = $cerRelativePath;
        $data['csd_password'] = encrypt_secret($csdPassword);
        $data['csd_uploaded_at'] = date('Y-m-d H:i:s');
        $data['csd_rfc'] = $certificateInfo['rfc'];
        $data['csd_valid_from'] = $certificateInfo['valid_from'];
        $data['csd_valid_to'] = $certificateInfo['valid_to'];
        $data['rfc_emisor'] = $certificateInfo['rfc'];
        $data['nombre_emisor'] = $certificateInfo['legal_name'] ?: ($data['nombre_emisor'] ?? null);
        $data['api_url'] = $apiCredentials['api_url'];
        $data['api_user'] = $apiCredentials['api_user'];
        $data['api_password'] = encrypt_secret($apiCredentials['api_password']);
        $data['invoicing_mode'] = 'ef_api';

        if ($hasExistingCsd && !empty($runtimeSettings['csd_password'])) {
            AutofacturaLog::log('csd_rotated', null, $businessId, 'CSD reemplazado de forma segura.');
        }
    }

    /**
     * Validar extensión, tamaño y errores de subida del archivo CSD.
     */
    private function validateCsdUpload(array $file, string $expectedExtension): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo cargar el archivo .' . $expectedExtension . ' del CSD.');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== $expectedExtension) {
            throw new RuntimeException('El archivo del sello debe tener extensión .' . $expectedExtension . '.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::CSD_MAX_SIZE) {
            throw new RuntimeException('Cada archivo CSD debe pesar entre 1 byte y 1 MB.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('La carga del archivo CSD no es válida.');
        }
    }

    /**
     * Eliminar archivos CSD previamente guardados.
     */
    private function deleteCsdFiles(array $existing): void
    {
        foreach (['csd_key_path', 'csd_cer_path'] as $field) {
            $relativePath = $existing[$field] ?? null;
            if (!$relativePath) {
                continue;
            }

            $fullPath = STORAGE_PATH . '/' . ltrim((string) $relativePath, '/');
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    /**
     * Defensa adicional para Apache aunque los archivos ya viven fuera de URLs públicas.
     */
    private function writeApacheDenyRule(string $path): void
    {
        if (is_file($path)) {
            return;
        }

        @file_put_contents($path, "Require all denied\nDeny from all\n");
        @chmod($path, 0600);
    }

    /**
     * Extraer RFC, razón social y vigencia desde un certificado SAT .cer.
     */
    private function extractCertificateMetadata(string $certificatePath): array
    {
        $contents = @file_get_contents($certificatePath);
        if ($contents === false || $contents === '') {
            throw new RuntimeException('No se pudo leer el certificado .cer para validar su vigencia.');
        }

        $certificate = @openssl_x509_read($contents);
        if ($certificate === false) {
            $pem = "-----BEGIN CERTIFICATE-----\n"
                . chunk_split(base64_encode($contents), 64, "\n")
                . "-----END CERTIFICATE-----\n";
            $certificate = @openssl_x509_read($pem);
        }

        if ($certificate === false) {
            throw new RuntimeException('El archivo .cer no es un certificado X.509 válido.');
        }

        $parsed = @openssl_x509_parse($certificate);
        if ($parsed === false) {
            throw new RuntimeException('No se pudo interpretar la información del certificado CSD.');
        }

        $rfc = $this->findRfcInCertificate($parsed);
        if ($rfc === null) {
            throw new RuntimeException('No fue posible identificar el RFC dentro del certificado CSD.');
        }
        $legalName = $this->findLegalNameInCertificate($parsed, $rfc);
        if ($legalName === null) {
            throw new RuntimeException('No fue posible identificar la razón social dentro del certificado CSD.');
        }

        $validFrom = isset($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', (int) $parsed['validFrom_time_t']) : null;
        $validTo = isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', (int) $parsed['validTo_time_t']) : null;

        if ($validFrom === null || $validTo === null) {
            throw new RuntimeException('No fue posible obtener la vigencia del certificado CSD.');
        }

        return [
            'rfc' => $rfc,
            'legal_name' => $legalName,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
        ];
    }

    /**
     * Buscar el RFC en el subject, extensiones o texto completo del certificado.
     */
    private function findRfcInCertificate(array $parsed): ?string
    {
        $candidates = [];

        if (!empty($parsed['subject']) && is_array($parsed['subject'])) {
            $candidates = array_merge($candidates, array_values($parsed['subject']));
        }

        if (!empty($parsed['issuer']) && is_array($parsed['issuer'])) {
            $candidates = array_merge($candidates, array_values($parsed['issuer']));
        }

        if (!empty($parsed['extensions']) && is_array($parsed['extensions'])) {
            $candidates = array_merge($candidates, array_values($parsed['extensions']));
        }

        $candidates[] = json_encode($parsed, JSON_UNESCAPED_UNICODE);

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            if (preg_match('/\b([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})\b/u', strtoupper($candidate), $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function findLegalNameInCertificate(array $parsed, string $rfc): ?string
    {
        $subject = $parsed['subject'] ?? [];
        if (is_array($subject)) {
            foreach (['CN', 'name', 'O', 'OU'] as $field) {
                $value = trim((string) ($subject[$field] ?? ''));
                if ($value !== '' && strtoupper($value) !== strtoupper($rfc)) {
                    return $value;
                }
            }
        }

        $candidates = [];
        if (is_array($subject)) {
            foreach ($subject as $value) {
                if (is_string($value)) {
                    $candidates[] = trim($value);
                }
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $upper = strtoupper($candidate);
            if (str_contains($upper, strtoupper($rfc))) {
                $clean = trim(str_ireplace($rfc, '', $candidate), " \t\n\r\0\x0B,.;:-");
                if ($clean !== '') {
                    return $clean;
                }
            }
        }

        return null;
    }

    private function provisionEfApiCredentials(array $business, array $certificateInfo, array $existing, array $runtimeSettings): array
    {
        $businessId = (int) ($business['id'] ?? 0);
        $rfc = strtoupper(trim((string) ($certificateInfo['rfc'] ?? '')));
        $legalName = trim((string) ($certificateInfo['legal_name'] ?? ''));
        $email = trim((string) ($business['email'] ?? ''));

        if ($businessId <= 0 || $rfc === '' || $legalName === '' || $email === '') {
            throw new RuntimeException('No se pudo preparar el alta automática del timbrado porque faltan datos del certificado o del negocio.');
        }

        $username = 'cfditop' . $businessId . $rfc;
        $password = 'cfditop' . $businessId . $rfc . '#';
        $apiUrl = trim((string) ($existing['api_url'] ?? ''));
        if ($apiUrl === '') {
            $apiUrl = trim((string) env('EF_DEFAULT_API_URL', self::DEFAULT_EF_API_URL));
        }

        $assignUrl = trim((string) env('EF_ASSIGN_URL', ''));
        $assignBearer = trim((string) env('EF_ASSIGN_BEARER', ''));

        if ($assignUrl === '' || $assignBearer === '') {
            throw new RuntimeException('Falta configurar EF_ASSIGN_URL o EF_ASSIGN_BEARER en el entorno para dar de alta el timbrado automáticamente.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('No está disponible cURL en el servidor para registrar la cuenta de timbrado.');
        }

        $payload = [
            'usuario' => [
                'nombre_completo' => $legalName,
                'rfc' => $rfc,
                'usuario' => $username,
                'contrasena' => $password,
                'correo' => $email,
            ],
        ];
        $payloadJson = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        AutofacturaLog::log(
            'ef_assign_user_attempt',
            null,
            $businessId,
            'Intentando alta automática de usuario de timbrado: ' . $username
        );

        $ch = curl_init($assignUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $assignBearer,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $payloadJson,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            AutofacturaLog::log('ef_assign_user_error', null, (int) $business['id'], $curlError !== '' ? $curlError : 'Error de red al registrar usuario EF.');
            throw new RuntimeException('No se pudo registrar la cuenta de timbrado: ' . ($curlError !== '' ? $curlError : 'error de red'));
        }

        $decoded = json_decode((string) $response, true);
        $responseText = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : trim((string) $response);
        $responseText = $responseText !== '' ? $responseText : 'Respuesta vacía';

        $alreadyExists = $httpCode === 409
            || str_contains(strtolower($responseText), 'existe')
            || str_contains(strtolower($responseText), 'duplic');

        if (($httpCode < 200 || $httpCode >= 300) && !$alreadyExists) {
            AutofacturaLog::log('ef_assign_user_error', null, (int) $business['id'], 'HTTP ' . $httpCode . ': ' . $responseText);
            throw new RuntimeException('No se pudo registrar la cuenta de timbrado automáticamente. Respuesta del servicio: ' . $responseText);
        }

        AutofacturaLog::log(
            $alreadyExists ? 'ef_assign_user_exists' : 'ef_assign_user_ok',
            null,
            (int) $business['id'],
            'HTTP ' . $httpCode . ' - Usuario timbrado: ' . $username . '. Respuesta: ' . $responseText
        );

        return [
            'api_url' => $apiUrl,
            'api_user' => $username,
            'api_password' => $password,
            'feedback_message' => $alreadyExists
                ? 'Cuenta de timbrado validada: el usuario ya existía en EfectosFiscales y se reutilizó correctamente.'
                : 'Cuenta de timbrado creada correctamente en EfectosFiscales.',
        ];
    }
}
