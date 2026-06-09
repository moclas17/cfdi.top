<?php
/**
 * AutoFactura - Servicio de Facturación (EfectosFiscales)
 * Mock para simular la integración con el servicio de facturación.
 */

class EfectosFiscalesService
{
    private const INVOICE_STORAGE_ROOT = STORAGE_PATH . '/invoices';

    private static function nowCfdi(): string
    {
        return (new DateTime('now', new DateTimeZone(env('APP_TIMEZONE', 'America/Mexico_City'))))
            ->format('Y-m-d\TH:i:s');
    }

    /**
     * Probar conexión con EF invocando wsGetCredit.
     * Intenta SOAP si hay WSDL y, si falla, hace POST JSON.
     */
    public static function wsGetCredit(array $config): array
    {
        $apiUrl = trim($config['api_url'] ?? '');
        $apiUser = trim($config['api_user'] ?? '');
        $apiPassword = trim($config['api_password'] ?? '');
        $apiKey = trim($config['api_key'] ?? '');

        if ($apiUrl === '' || (($apiUser === '' || $apiPassword === '') && $apiKey === '')) {
            return [
                'success' => false,
                'message' => 'Completa la URL y las credenciales necesarias para probar la conexión.',
            ];
        }

        $attemptErrors = [];

        // Intento SOAP (si la extensión está disponible)
        if (class_exists('SoapClient')) {
            foreach (self::wsdlCandidates($apiUrl) as $wsdlUrl) {
                $soapResult = self::trySoapVariants($wsdlUrl, $apiUser, $apiPassword, $apiKey);
                if ($soapResult['success']) {
                    return $soapResult;
                }
                if (!empty($soapResult['message'])) {
                    $attemptErrors[] = 'SOAP(' . $wsdlUrl . '): ' . $soapResult['message'];
                }
            }
        }

        // Si la URL configurada es un WSDL SOAP, no intentamos fallback HTTP/JSON
        // porque el endpoint responde SOAP/Fault y solo mete ruido en el diagnóstico.
        if (str_contains(strtolower($apiUrl), '?wsdl')) {
            return [
                'success' => false,
                'message' => 'No se pudo autenticar con EfectosFiscales. ' . implode(' | ', array_slice($attemptErrors, 0, 3)),
            ];
        }

        // Fallback HTTP (POST JSON)
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'No está disponible SOAP ni cURL en el servidor.',
            ];
        }

        $httpResult = self::tryHttpVariants($apiUrl, $apiUser, $apiPassword, $apiKey);
        if ($httpResult['success']) {
            return $httpResult;
        }

        if (!empty($httpResult['message'])) {
            $attemptErrors[] = 'HTTP: ' . $httpResult['message'];
        }

        return [
            'success' => false,
            'message' => 'No se pudo autenticar con EfectosFiscales. ' . implode(' | ', array_slice($attemptErrors, 0, 3)),
        ];
    }

    /**
     * Crear factura.
     * - En modo API (config con api_url/user/password) intenta wsGetTicket.
     * - Si no hay API configurada, genera XML/PDF local para flujo directo.
     */
    public static function createInvoice(array $invoiceData, array $apiConfig = [], ?array $csdCredentials = null): array
    {
        $apiUrl = trim((string) ($apiConfig['api_url'] ?? ''));
        $apiUser = trim((string) ($apiConfig['api_user'] ?? ''));
        $apiPassword = trim((string) ($apiConfig['api_password'] ?? ''));
        $useEfApi = $apiUrl !== '' && $apiUser !== '' && $apiPassword !== '';

        if ($useEfApi) {
            $ticketResult = self::wsGetTicket($apiConfig, $invoiceData, $csdCredentials);
            if (!empty($ticketResult['success'])) {
                return $ticketResult;
            }
            return [
                'success' => false,
                'message' => (string) ($ticketResult['message'] ?? 'No se pudo generar CFDI con wsGetTicket.'),
                'raw' => $ticketResult['raw'] ?? null,
            ];
        }

        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
        $uuid = strtoupper($uuid);

        $issuedAt = self::nowCfdi();
        $storage = self::invoiceStoragePaths((string) ($invoiceData['emisor_rfc'] ?? ''), $issuedAt, $uuid);
        if (!self::ensureInvoiceStorageDirectory($storage['dir'])) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el directorio de facturas en storage.',
            ];
        }

        $xmlFile = $storage['xml_file'];
        $pdfFile = $storage['pdf_file'];

        $xmlContent = self::buildInvoiceXml($uuid, $invoiceData, $issuedAt, $csdCredentials);
        if (@file_put_contents($xmlFile, $xmlContent) === false) {
            return [
                'success' => false,
                'message' => 'No se pudo generar el XML de la factura.',
            ];
        }

        $pdfContent = self::buildInvoicePdf($uuid, $invoiceData, $issuedAt, $xmlContent);
        if (@file_put_contents($pdfFile, $pdfContent) === false) {
            return [
                'success' => false,
                'message' => 'No se pudo generar el PDF de la factura.',
            ];
        }

        return [
            'success' => true,
            'uuid' => $uuid,
            'xml_url' => $storage['xml_url'],
            'pdf_url' => $storage['pdf_url'],
            'message' => !empty($csdCredentials) ? 'Factura generada y sellada exitosamente.' : 'Factura generada exitosamente (modo directo).',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Intentar generar CFDI en EF mediante wsGetTicket.
     */
    public static function wsGetTicket(array $config, array $invoiceData, ?array $csdCredentials = null): array
    {
        $apiUrl = trim((string) ($config['api_url'] ?? ''));
        $apiUser = trim((string) ($config['api_user'] ?? ''));
        $apiPassword = trim((string) ($config['api_password'] ?? ''));
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiUrl === '' || $apiUser === '' || $apiPassword === '') {
            return [
                'success' => false,
                'message' => 'Configuración EF incompleta: URL, usuario y contraseña son obligatorios.',
            ];
        }
        $draftUuid = self::generateUuid();
        $issuedAt = self::nowCfdi();
        $xmlDraft = self::buildInvoiceXml($draftUuid, $invoiceData, $issuedAt, $csdCredentials);
        $xmlDraftBase64 = base64_encode($xmlDraft);
        $attemptErrors = [];
        $creditHint = '';
        $remoteCreditsBeforeStamp = null;

        // Pre-chequeo de créditos con las mismas credenciales para diagnóstico fino.
        $creditCheck = self::wsGetCredit($config);
        if (!empty($creditCheck['success']) && isset($creditCheck['credit'])) {
            $creditHint = 'Créditos reportados por wsGetCredit: ' . (string) $creditCheck['credit'] . '. ';
            $remoteCreditsBeforeStamp = self::extractCreditInteger($creditCheck['credit']);
        }

        if (class_exists('SoapClient')) {
            foreach (self::wsdlCandidates($apiUrl) as $wsdlUrl) {
                $soapResult = self::trySoapTicketVariants($wsdlUrl, $apiUser, $apiPassword, $apiKey, $xmlDraftBase64);
                if (!empty($soapResult['success'])) {
                    return self::materializeTicketResult($soapResult, $invoiceData, $xmlDraft, $draftUuid, $remoteCreditsBeforeStamp);
                }
                if (!empty($soapResult['message'])) {
                    $attemptErrors[] = 'SOAP(' . $wsdlUrl . '): ' . $soapResult['message'];
                }
            }
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'No está disponible SOAP ni cURL en el servidor para wsGetTicket.',
            ];
        }

        // Si la URL configurada es WSDL SOAP, no intentamos fallback HTTP JSON
        // porque el endpoint responde XML SOAP/Fault y solo agrega ruido al diagnóstico.
        if (str_contains(strtolower($apiUrl), '?wsdl')) {
            return [
                'success' => false,
                'message' => $creditHint . self::decorateTicketErrorMessage(
                    'wsGetTicket falló. ' . implode(' | ', array_slice($attemptErrors, 0, 3)),
                    $xmlDraft
                ),
                'raw' => [
                    'signed_xml' => $xmlDraft,
                    'signed_xml_base64' => $xmlDraftBase64,
                ],
            ];
        }

        $httpResult = self::tryHttpTicketVariants($apiUrl, $apiUser, $apiPassword, $apiKey, $xmlDraftBase64);
        if (!empty($httpResult['success'])) {
            return self::materializeTicketResult($httpResult, $invoiceData, $xmlDraft, $draftUuid, $remoteCreditsBeforeStamp);
        }
        if (!empty($httpResult['message'])) {
            $attemptErrors[] = 'HTTP(' . $apiUrl . '): ' . $httpResult['message'];
        }

        return [
            'success' => false,
            'message' => $creditHint . self::decorateTicketErrorMessage(
                'wsGetTicket falló. ' . implode(' | ', array_slice($attemptErrors, 0, 3)),
                $xmlDraft
            ),
            'raw' => array_filter([
                'response' => $httpResult['raw'] ?? null,
                'signed_xml' => $xmlDraft,
                'signed_xml_base64' => $xmlDraftBase64,
            ], static fn($value) => $value !== null),
        ];
    }

    /**
     * Obtener factura por UUID (mock)
     *
     * @param string $uuid UUID de la factura
     * @return array Datos de la factura
     */
    public static function getInvoice(string $uuid): array
    {
        return [
            'success' => true,
            'uuid' => $uuid,
            'status' => 'vigente',
            'xml_url' => self::findInvoiceAssetUrls($uuid)['xml_url'] ?? null,
            'pdf_url' => self::findInvoiceAssetUrls($uuid)['pdf_url'] ?? null,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public static function regenerateInvoicePdf(string $uuid, array $invoiceData, ?string $issuedAt = null): array
    {
        $issuedAt = $issuedAt ?: self::nowCfdi();
        $storage = self::invoiceStoragePaths((string) ($invoiceData['emisor_rfc'] ?? ''), $issuedAt, $uuid);
        if (!self::ensureInvoiceStorageDirectory($storage['dir'])) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el directorio de facturas en storage.',
            ];
        }

        $pdfFile = $storage['pdf_file'];
        $xmlFile = $storage['xml_file'];
        if (!is_file($xmlFile)) {
            $existing = self::findInvoiceAssetUrls($uuid);
            $existingXmlPath = self::storagePathFromUrl((string) ($existing['xml_url'] ?? ''));
            if ($existingXmlPath && is_file($existingXmlPath)) {
                $xmlFile = $existingXmlPath;
            }
        }
        $xmlContent = is_file($xmlFile) ? (string) @file_get_contents($xmlFile) : null;

        try {
            $pdfContent = self::buildInvoicePdf($uuid, $invoiceData, $issuedAt, $xmlContent);
        } catch (Throwable $e) {
            app_log('No se pudo regenerar el PDF ' . $uuid . ': ' . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'No se pudo regenerar el PDF: ' . $e->getMessage(),
            ];
        }

        if (@file_put_contents($pdfFile, $pdfContent) === false) {
            app_log('No se pudo escribir el PDF regenerado ' . $uuid . ' en ' . $pdfFile, 'error');
            return [
                'success' => false,
                'message' => 'No se pudo regenerar el PDF de la factura.',
            ];
        }

        return [
            'success' => true,
            'pdf_url' => $storage['pdf_url'],
            'message' => 'PDF regenerado correctamente.',
        ];
    }

    /**
     * Cancelar factura por UUID (mock)
     *
     * @param string $uuid UUID de la factura
     * @param string $reason Motivo de cancelación
     * @return array Resultado de la cancelación
     */
    public static function cancelInvoice(string $uuid, string $reason = '02'): array
    {
        return [
            'success' => true,
            'uuid' => $uuid,
            'status' => 'cancelada',
            'message' => 'Factura cancelada exitosamente (DEMO).',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Normaliza respuestas de wsGetCredit para distintos formatos.
     */
    private static function normalizeCreditResponse(mixed $response, string $defaultMessage): array
    {
        if (is_scalar($response)) {
            $creditText = trim((string) $response);
            $lowerText = mb_strtolower($creditText, 'UTF-8');
            $looksLikeError = $creditText === ''
                || str_contains($lowerText, 'no existe')
                || str_contains($lowerText, 'inval')
                || str_contains($lowerText, 'error')
                || str_contains($lowerText, 'deneg')
                || str_contains($lowerText, 'incorrect');

            return [
                'success' => !$looksLikeError,
                'credit' => $creditText,
                'message' => $looksLikeError ? $creditText : $defaultMessage,
                'raw' => $response,
            ];
        }

        $data = json_decode(json_encode($response, JSON_UNESCAPED_UNICODE), true);
        if (!is_array($data)) {
            return [
                'success' => true,
                'message' => $defaultMessage,
                'raw' => $response,
            ];
        }

        $credit = $data['credit'] ?? $data['saldo'] ?? $data['wsGetCreditResult'] ?? null;
        $creditText = is_scalar($credit) ? trim((string) $credit) : '';
        $message = (string) ($data['message'] ?? $defaultMessage);
        $lowerText = mb_strtolower($creditText !== '' ? $creditText : $message, 'UTF-8');
        $looksLikeError = str_contains($lowerText, 'no existe')
            || str_contains($lowerText, 'inval')
            || str_contains($lowerText, 'error')
            || str_contains($lowerText, 'deneg')
            || str_contains($lowerText, 'incorrect');
        $success = (bool) ($data['success'] ?? !$looksLikeError);

        return [
            'success' => $success,
            'credit' => $credit,
            'message' => $looksLikeError ? ($creditText !== '' ? $creditText : $message) : $message,
            'raw' => $data,
        ];
    }

    private static function wsdlCandidates(string $apiUrl): array
    {
        $clean = trim($apiUrl);
        $lower = strtolower($clean);
        if (str_contains($lower, '?wsdl')) {
            return [$clean];
        }
        return [$clean, $clean . '?WSDL', $clean . '?wsdl'];
    }

    private static function generateUuid(): string
    {
        return strtoupper(sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        ));
    }

    public static function invoiceStoragePaths(string $emisorRfc, string $issuedAt, string $uuid): array
    {
        $rfc = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($emisorRfc)));
        if ($rfc === '') {
            $rfc = 'SINRFC';
        }

        try {
            $date = new DateTime($issuedAt);
        } catch (Throwable) {
            $date = new DateTime('now', new DateTimeZone(env('APP_TIMEZONE', 'America/Mexico_City')));
        }

        $year = $date->format('Y');
        $month = $date->format('m');
        $dir = self::INVOICE_STORAGE_ROOT . '/' . $rfc . '/' . $year . '/' . $month;

        return [
            'rfc' => $rfc,
            'year' => $year,
            'month' => $month,
            'dir' => $dir,
            'xml_file' => $dir . '/' . $uuid . '.xml',
            'pdf_file' => $dir . '/' . $uuid . '.pdf',
            'xml_url' => '/storage/invoices/' . rawurlencode($rfc) . '/' . $year . '/' . $month . '/' . rawurlencode($uuid . '.xml'),
            'pdf_url' => '/storage/invoices/' . rawurlencode($rfc) . '/' . $year . '/' . $month . '/' . rawurlencode($uuid . '.pdf'),
        ];
    }

    public static function storagePathFromUrl(string $url): ?string
    {
        $path = trim(parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/storage/invoices/')) {
            $relative = substr($path, strlen('/storage/invoices/'));
            return self::INVOICE_STORAGE_ROOT . '/' . ltrim(rawurldecode($relative), '/');
        }

        return null;
    }

    public static function findInvoiceAssetUrls(string $uuid): array
    {
        $result = [];

        foreach (glob(self::INVOICE_STORAGE_ROOT . '/*/*/*/' . $uuid . '.xml') ?: [] as $xmlFile) {
            $relative = ltrim(str_replace(self::INVOICE_STORAGE_ROOT, '', $xmlFile), '/');
            $result['xml_url'] = '/storage/invoices/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
            break;
        }

        foreach (glob(self::INVOICE_STORAGE_ROOT . '/*/*/*/' . $uuid . '.pdf') ?: [] as $pdfFile) {
            $relative = ltrim(str_replace(self::INVOICE_STORAGE_ROOT, '', $pdfFile), '/');
            $result['pdf_url'] = '/storage/invoices/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
            break;
        }

        return $result;
    }

    private static function ensureInvoiceStorageDirectory(string $dir): bool
    {
        return is_dir($dir) || (@mkdir($dir, 0775, true) && is_dir($dir));
    }

    private static function trySoapVariants(string $wsdlUrl, string $apiUser, string $apiPassword, string $apiKey): array
    {
        $lastError = '';
        $soapLocation = self::resolveSoapLocation($wsdlUrl);

        try {
            $soapClient = new SoapClient($wsdlUrl, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 15,
                'soap_version' => SOAP_1_1,
                'uri' => 'urn:efectosfiscales',
                'location' => $soapLocation,
                'style' => SOAP_RPC,
                'use' => SOAP_ENCODED,
            ]);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'No se pudo abrir WSDL.'];
        }

        try {
            $soapResponse = self::invokeSoapCall($soapClient, 'wsGetCredit', [$apiUser, $apiPassword]);
            self::logSoapDebug($soapClient, 'wsGetCredit');
            $result = self::normalizeCreditResponse($soapResponse, 'Conexión SOAP exitosa.');
            if ($result['success']) {
                return $result;
            }
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }

        $lastResponse = '';
        try {
            $lastResponse = (string) $soapClient->__getLastResponse();
        } catch (Throwable) {
            $lastResponse = '';
        }

        if ($lastResponse !== '') {
            $fault = self::extractSoapFaultString($lastResponse);
            if ($fault !== '') {
                $lastError = $fault;
            } else {
                $wrappedResult = self::extractSoapWrappedResult($lastResponse, 'wsGetCredit');
                if ($wrappedResult !== '') {
                    $lastError = $wrappedResult;
                }
            }
        }

        return [
            'success' => false,
            'message' => 'wsGetCredit no respondió con autenticación válida.' . ($lastError !== '' ? ' ' . $lastError : ''),
        ];
    }

    private static function invokeSoapCall(SoapClient $soapClient, string $method, array $params): mixed
    {
        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): never {
            throw new RuntimeException($message . ($file !== '' ? ' @ ' . $file . ':' . $line : ''));
        });

        try {
            return $soapClient->__soapCall($method, $params);
        } finally {
            restore_error_handler();
        }
    }

    private static function trySoapTicketVariants(
        string $wsdlUrl,
        string $apiUser,
        string $apiPassword,
        string $apiKey,
        string $xmlDraftBase64
    ): array {
        $lastError = '';
        $soapClient = null;
        $soapLocation = self::resolveSoapLocation($wsdlUrl);
        try {
            $soapClient = new SoapClient($wsdlUrl, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 20,
                'soap_version' => SOAP_1_1,
                'uri' => 'urn:efectosfiscales',
                'location' => $soapLocation,
                'style' => SOAP_RPC,
                'use' => SOAP_ENCODED,
            ]);
        } catch (Throwable) {
            return ['success' => false, 'message' => 'No se pudo abrir WSDL para wsGetTicket.'];
        }

        try {
            $soapResponse = self::invokeSoapCall($soapClient, 'wsGetTicket', [$apiUser, $apiPassword, $xmlDraftBase64]);
            self::logSoapDebug($soapClient, 'wsGetTicket');
            $result = self::normalizeTicketResponse($soapResponse, 'CFDI generado por SOAP.');
            if (!empty($result['success'])) {
                return $result;
            }
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }

        $lastResponse = '';
        try {
            $lastResponse = (string) ($soapClient?->__getLastResponse() ?? '');
        } catch (Throwable) {
            $lastResponse = '';
        }
        if ($lastResponse !== '') {
            $fault = self::extractSoapFaultString($lastResponse);
            if ($fault !== '') {
                $lastError = $fault;
            } else {
                $wrappedResult = self::extractSoapWrappedResult($lastResponse, 'wsGetTicket');
                if ($wrappedResult !== '') {
                    $wrappedParsed = self::normalizeTicketResponse($wrappedResult, 'CFDI generado por SOAP.');
                    if (!empty($wrappedParsed['success'])) {
                        return $wrappedParsed;
                    }
                    if (!empty($wrappedParsed['message'])) {
                        $lastError = (string) $wrappedParsed['message'];
                    } else {
                        $lastError .= ' | SOAP response: ' . substr(trim($lastResponse), 0, 400);
                    }
                } else {
                    $lastError .= ' | SOAP response: ' . substr(trim($lastResponse), 0, 400);
                }
            }
        }

        return [
            'success' => false,
            'message' => 'wsGetTicket SOAP sin respuesta válida.'
                . (!empty($lastError) ? ' ' . $lastError : '')
                . ' Usuario enviado: ' . $apiUser . '.'
                . ' Contraseña enviada: ' . $apiPassword . '.'
                . ' API URL configurada: ' . $wsdlUrl . '.'
                . ' Endpoint SOAP efectivo: ' . $soapLocation . '.',
        ];
    }

    private static function resolveSoapLocation(string $wsdlUrl): string
    {
        $defaultLocation = str_ireplace('?wsdl', '', $wsdlUrl);
        $wsdlContents = self::fetchWsdlContents($wsdlUrl);
        if ($wsdlContents !== '' && preg_match('/<soap:address\b[^>]*location="([^"]+)"/i', $wsdlContents, $matches)) {
            $location = trim((string) ($matches[1] ?? ''));
            if ($location !== '') {
                return $location;
            }
        }

        return $defaultLocation;
    }

    private static function fetchWsdlContents(string $wsdlUrl): string
    {
        $contents = @file_get_contents($wsdlUrl);
        if (is_string($contents) && trim($contents) !== '') {
            return $contents;
        }

        if (!function_exists('curl_init')) {
            return '';
        }

        $ch = curl_init($wsdlUrl);
        if ($ch === false) {
            return '';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPGET => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return is_string($response) ? $response : '';
    }

    private static function tryHttpVariants(string $apiUrl, string $apiUser, string $apiPassword, string $apiKey): array
    {
        $effectiveApiKey = $apiKey !== '' ? $apiKey : $apiUser;
        $jsonPayloads = [];
        $jsonPayloads[] = [
            'method' => 'wsGetCredit',
            'username' => $apiUser,
            'password' => $apiPassword,
        ];
        $jsonPayloads[] = [
            'method' => 'wsGetCredit',
            'user' => $apiUser,
            'password' => $apiPassword,
        ];
        $jsonPayloads[] = [
            'method' => 'wsGetCredit',
            'usuario' => $apiUser,
            'contrasena' => $apiPassword,
        ];
        if ($effectiveApiKey !== '') {
            $jsonPayloads[] = [
                'method' => 'wsGetCredit',
                'apikey' => $effectiveApiKey,
            ];
            $jsonPayloads[] = [
                'method' => 'wsGetCredit',
                'api_key' => $effectiveApiKey,
            ];
        }

        foreach ($jsonPayloads as $payload) {
            $result = self::doHttpRequest($apiUrl, $payload, 'application/json');
            if ($result['success']) {
                return $result;
            }
        }

        $formPayload = [
            'method' => 'wsGetCredit',
            'username' => $apiUser,
            'password' => $apiPassword,
            'user' => $apiUser,
            'usuario' => $apiUser,
            'contrasena' => $apiPassword,
        ];
        if ($effectiveApiKey !== '') {
            $formPayload['apikey'] = $effectiveApiKey;
            $formPayload['api_key'] = $effectiveApiKey;
        }
        return self::doHttpRequest($apiUrl, $formPayload, 'application/x-www-form-urlencoded');
    }

    private static function tryHttpTicketVariants(
        string $apiUrl,
        string $apiUser,
        string $apiPassword,
        string $apiKey,
        string $xmlDraftBase64
    ): array {
        $payload = self::buildTicketPayload($apiUser, $apiPassword, $apiKey, $xmlDraftBase64);

        $jsonPayloads = [];
        $jsonPayloads[] = array_merge(['method' => 'wsGetTicket'], $payload);
        $jsonPayloads[] = array_merge(['action' => 'wsGetTicket'], $payload);
        $jsonPayloads[] = [
            'method' => 'wsGetTicket',
            'user' => $apiUser,
            'password' => $apiPassword,
            'usuario' => $apiUser,
            'contrasena' => $apiPassword,
            'xml' => $xmlDraftBase64,
            'xml_base64' => $xmlDraftBase64,
            'base64' => $xmlDraftBase64,
            'cfdi' => $xmlDraftBase64,
        ];
        if ($apiKey !== '') {
            $jsonPayloads[2]['api_key'] = $apiKey;
            $jsonPayloads[2]['apikey'] = $apiKey;
        }

        foreach ($jsonPayloads as $jsonPayload) {
            $result = self::doHttpRequest($apiUrl, $jsonPayload, 'application/json');
            if (!empty($result['success'])) {
                return $result;
            }
        }

        $formPayload = array_merge(['method' => 'wsGetTicket'], $payload);
        return self::doHttpRequest($apiUrl, $formPayload, 'application/x-www-form-urlencoded');
    }

    private static function buildTicketPayload(
        string $apiUser,
        string $apiPassword,
        string $apiKey,
        string $xmlDraftBase64
    ): array {
        $payload = [
            'user' => $apiUser,
            'password' => $apiPassword,
            'usuario' => $apiUser,
            'contrasena' => $apiPassword,
            // wsGetTicket espera CFDI en base64.
            'xml' => $xmlDraftBase64,
            'xml_base64' => $xmlDraftBase64,
            'cfdi' => $xmlDraftBase64,
            'base64' => $xmlDraftBase64,
        ];
        if ($apiKey !== '') {
            $payload['api_key'] = $apiKey;
            $payload['apikey'] = $apiKey;
        }
        return $payload;
    }

    private static function logSoapDebug(SoapClient $client, string $method): void
    {
        if (!function_exists('app_log')) {
            return;
        }
        try {
            $request = (string) $client->__getLastRequest();
            $response = (string) $client->__getLastResponse();
            app_log("SOAP {$method} request: " . substr($request, 0, 5000), 'debug');
            app_log("SOAP {$method} response: " . substr($response, 0, 5000), 'debug');
        } catch (Throwable $e) {
            app_log("SOAP {$method} debug log error: " . $e->getMessage(), 'error');
        }
    }

    private static function doHttpRequest(string $apiUrl, array $payload, string $contentType): array
    {
        $postBody = $contentType === 'application/json'
            ? (string) json_encode($payload, JSON_UNESCAPED_UNICODE)
            : http_build_query($payload);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Content-Type: ' . $contentType,
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $postBody,
        ]);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . ($curlError ?: 'No se obtuvo respuesta.'),
            ];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Respuesta no JSON (HTTP ' . $httpCode . '). Body: ' . substr(trim((string) $body), 0, 400),
            ];
        }

        $isOk = (bool) ($decoded['success'] ?? $decoded['ok'] ?? ($httpCode >= 200 && $httpCode < 300));
        $credit = $decoded['credit'] ?? $decoded['saldo'] ?? $decoded['data']['credit'] ?? null;
        $message = $decoded['message']
            ?? $decoded['error']
            ?? $decoded['detail']['error']['message']
            ?? ($isOk ? 'Conexión HTTP exitosa.' : 'El servicio respondió con error.');

        return [
            'success' => $isOk,
            'credit' => $credit,
            'message' => $message,
            'raw' => $decoded,
        ];
    }

    private static function normalizeTicketResponse(mixed $response, string $defaultMessage): array
    {
        if (is_scalar($response)) {
            $scalar = self::unwrapQuotedScalar(trim((string) $response));
            $normalizedScalar = mb_strtoupper($scalar, 'UTF-8');

            // Si viene un JSON serializado como string, parsearlo para detectar errores.
            if (($scalar !== '') && (($scalar[0] ?? '') === '{' || ($scalar[0] ?? '') === '[')) {
                $decodedScalar = json_decode($scalar, true);
                if (is_array($decodedScalar)) {
                    return self::normalizeTicketResponse($decodedScalar, $defaultMessage);
                }
            }

            if (self::isTicketErrorMessage($scalar)) {
                return [
                    'success' => false,
                    'message' => $scalar,
                    'raw' => $response,
                ];
            }

            if (in_array($normalizedScalar, ['ACEPTADO', 'OK', 'TIMBRADO'], true)) {
                return [
                    'success' => true,
                    'uuid' => null,
                    'xml' => null,
                    'message' => $scalar,
                    'raw' => $response,
                ];
            }

            $scalarAsXml = self::decodePossiblyBase64($scalar);
            if (self::looksLikeXml($scalarAsXml)) {
                return [
                    'success' => true,
                    'uuid' => null,
                    'xml' => $scalarAsXml,
                    'message' => $defaultMessage,
                    'raw' => $response,
                ];
            }

            $uuidCandidate = self::extractUuidCandidate($scalar);
            if ($uuidCandidate === null) {
                return [
                    'success' => false,
                    'message' => $scalar !== '' ? $scalar : 'wsGetTicket regresó una respuesta no reconocida.',
                    'raw' => $response,
                ];
            }

            return [
                'success' => true,
                'uuid' => $uuidCandidate,
                'message' => $defaultMessage,
                'raw' => $response,
            ];
        }

        $data = json_decode(json_encode($response, JSON_UNESCAPED_UNICODE), true);
        if (!is_array($data)) {
            return [
                'success' => false,
                'message' => 'Respuesta inválida de wsGetTicket.',
                'raw' => $response,
            ];
        }

        $state = (int) ($data['state'] ?? $data['status'] ?? 0);
        $hasErrorFlag = isset($data['error']) || isset($data['errors']);
        $hasErrorState = $state < 0;

        $success = (bool) ($data['success'] ?? $data['ok'] ?? (!$hasErrorFlag && !$hasErrorState));
        $uuid = $data['uuid'] ?? $data['UUID'] ?? $data['ticket'] ?? $data['folio'] ?? $data['data']['uuid'] ?? null;
        $uuid = is_scalar($uuid) ? self::extractUuidCandidate((string) $uuid) : null;
        $xml = $data['xml'] ?? $data['XML'] ?? $data['xml_cfdi'] ?? $data['cfdi_xml'] ?? $data['data']['xml'] ?? null;
        if (!is_scalar($xml) || trim((string) $xml) === '') {
            $xml = self::extractTicketXmlPayload($data);
        }
        $pdf = $data['pdf'] ?? $data['PDF'] ?? $data['pdf_base64'] ?? $data['cfdi_pdf'] ?? $data['data']['pdf'] ?? null;
        $xmlUrl = $data['xml_url'] ?? $data['XML_URL'] ?? $data['data']['xml_url'] ?? null;
        $pdfUrl = $data['pdf_url'] ?? $data['PDF_URL'] ?? $data['data']['pdf_url'] ?? null;
        $message = (string) (
            $data['message']
            ?? $data['Message']
            ?? $data['descripcion']
            ?? $data['Descripcion']
            ?? $data['description']
            ?? $data['Description']
            ?? $data['detail']['error']['message']
            ?? ($success ? $defaultMessage : 'Error en wsGetTicket.')
        );
        $normalizedMessage = mb_strtoupper(trim($message), 'UTF-8');
        $acceptedMessage = in_array($normalizedMessage, ['ACEPTADO', 'OK', 'TIMBRADO'], true);

        $xmlText = self::decodePossiblyBase64(trim((string) $xml));
        if ($uuid === null && $xmlText !== '') {
            $uuid = self::extractUuidFromXml($xmlText);
        }
        $looksLikeXml = $xmlText !== '' && (str_starts_with($xmlText, '<?xml') || str_contains($xmlText, '<cfdi:Comprobante'));
        $hasUsefulPayload = !empty($uuid) || !empty($xmlUrl) || $looksLikeXml;

        if (!$hasUsefulPayload && !$acceptedMessage) {
            $success = false;
        }
        if ($hasErrorFlag || $hasErrorState) {
            $success = false;
        }

        return [
            'success' => $success,
            'uuid' => $uuid ? strtoupper((string) $uuid) : null,
            'xml' => $xml,
            'pdf' => $pdf,
            'xml_url' => $xmlUrl,
            'pdf_url' => $pdfUrl,
            'message' => $message,
            'raw' => $data,
        ];
    }

    private static function extractTicketXmlPayload(array $data): ?string
    {
        $preferredKeys = [
            'xml',
            'XML',
            'cfdi',
            'CFDI',
            'cfdi',
            'base64',
            'Base64',
            'base64Cfd',
            'base64CFDI',
            'xml_base64',
            'XML_BASE64',
            'cfdi_xml',
            'xml_cfdi',
            'archivo_xml',
            'comprobante',
            'Comprobante',
        ];

        foreach ($preferredKeys as $key) {
            if (!array_key_exists($key, $data) || !is_scalar($data[$key])) {
                continue;
            }

            $candidate = trim((string) $data[$key]);
            if ($candidate === '') {
                continue;
            }

            $decoded = self::decodePossiblyBase64($candidate);
            if (self::looksLikeXml($decoded)) {
                return $candidate;
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $nested = self::extractTicketXmlPayload($value);
                if ($nested !== null) {
                    return $nested;
                }
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $candidate = trim((string) $value);
            if ($candidate === '') {
                continue;
            }

            $decoded = self::decodePossiblyBase64($candidate);
            if (self::looksLikeXml($decoded)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function extractUuidFromXml(string $xml): ?string
    {
        if (!self::looksLikeXml($xml)) {
            return null;
        }

        $document = new DOMDocument();
        if (!@$document->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
            return null;
        }

        $nodes = $document->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital', 'TimbreFiscalDigital');
        $uuid = $nodes->length > 0 ? $nodes->item(0)?->getAttribute('UUID') : null;

        return is_string($uuid) ? self::extractUuidCandidate($uuid) : null;
    }

    private static function materializeTicketResult(
        array $ticketResult,
        array $invoiceData,
        string $xmlDraft,
        string $fallbackUuid,
        ?int $remoteCreditsBeforeStamp = null
    ): array
    {
        $uuid = strtoupper((string) ($ticketResult['uuid'] ?? $fallbackUuid));
        if ($uuid === '') {
            $uuid = $fallbackUuid;
        }

        $xmlPayload = (string) ($ticketResult['xml'] ?? '');
        $xmlUrlFromApi = (string) ($ticketResult['xml_url'] ?? '');
        $xmlSource = 'api_payload';
        $xmlContent = self::decodePossiblyBase64($xmlPayload);
        if ($xmlContent === '' && $xmlUrlFromApi === '') {
            // El proveedor normalmente regresa solo XML. Si no llegó contenido, guardamos el draft.
            $xmlContent = $xmlDraft;
            $xmlSource = 'fallback_local_draft';
        } elseif ($xmlContent === '' && $xmlUrlFromApi !== '') {
            $xmlSource = 'api_url_reference_fallback_local';
            $xmlContent = $xmlDraft;
        }
        if ($xmlContent !== '' && !self::looksLikeXml($xmlContent)) {
            return ['success' => false, 'message' => 'wsGetTicket respondió contenido XML inválido.'];
        }

        $issuedAt = self::nowCfdi();
        $storage = self::invoiceStoragePaths((string) ($invoiceData['emisor_rfc'] ?? ''), $issuedAt, $uuid);
        if (!self::ensureInvoiceStorageDirectory($storage['dir'])) {
            return ['success' => false, 'message' => 'No se pudo crear el directorio de almacenamiento del CFDI.'];
        }

        $xmlFile = $storage['xml_file'];
        $pdfFile = $storage['pdf_file'];

        if (@file_put_contents($xmlFile, $xmlContent) === false) {
            return ['success' => false, 'message' => 'No se pudo guardar XML de wsGetTicket.'];
        }

        $pdfContent = self::buildInvoicePdf($uuid, $invoiceData, $issuedAt, $xmlContent);
        if (@file_put_contents($pdfFile, $pdfContent) === false) {
            return ['success' => false, 'message' => 'No se pudo construir PDF local de la factura.'];
        }

        $xmlUrl = $storage['xml_url'];
        $pdfUrl = $storage['pdf_url'];

        $message = (string) ($ticketResult['message'] ?? 'CFDI generado con wsGetTicket.');
        if ($xmlPayload === '' && $xmlUrlFromApi !== '') {
            $message .= ' (XML API referenciado sin contenido inline; se guardó XML de respaldo local).';
        }

        return [
            'success' => true,
            'uuid' => $uuid,
            'xml_url' => $xmlUrl,
            'pdf_url' => $pdfUrl,
            'xml_source' => $xmlSource,
            'remote_credits_before_stamp' => $remoteCreditsBeforeStamp,
            'message' => $message,
            'raw' => $ticketResult['raw'] ?? null,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public static function extractCreditInteger(mixed $credit): ?int
    {
        if (!is_scalar($credit)) {
            return null;
        }

        $text = self::unwrapQuotedScalar(trim((string) $credit));
        if ($text === '' || !preg_match('/^-?\d+$/', $text)) {
            return null;
        }

        return (int) $text;
    }

    private static function decodePossiblyBase64(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '%PDF')) {
            return $trimmed;
        }

        $decoded = base64_decode($trimmed, true);
        if ($decoded !== false) {
            return $decoded;
        }
        return $trimmed;
    }

    private static function looksLikeXml(string $value): bool
    {
        $trimmed = ltrim($value);
        return str_starts_with($trimmed, '<?xml') || str_contains($trimmed, '<cfdi:Comprobante');
    }

    private static function extractSoapFaultString(string $soapXml): string
    {
        if (preg_match('/<[^>]*faultstring[^>]*>(.*?)<\/[^>]*faultstring>/si', $soapXml, $m)) {
            $text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            return $text;
        }
        return '';
    }

    private static function extractSoapWrappedResult(string $soapXml, string $method): string
    {
        $patterns = [
            '/<[^>]*' . preg_quote($method, '/') . 'Result[^>]*>(.*?)<\/[^>]*' . preg_quote($method, '/') . 'Result>/si',
            '/<[^>]*' . preg_quote($method, '/') . 'Return[^>]*>(.*?)<\/[^>]*' . preg_quote($method, '/') . 'Return>/si',
            '/<[^>]*return[^>]*>(.*?)<\/[^>]*return>/si',
            '/<[^>]*resultado[^>]*>(.*?)<\/[^>]*resultado>/si',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $soapXml, $m)) {
                $raw = trim($m[1]);
                if (preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $raw, $cdata)) {
                    return self::unwrapQuotedScalar(trim($cdata[1]));
                }
                return self::unwrapQuotedScalar(trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_XML1, 'UTF-8')));
            }
        }
        return '';
    }

    private static function unwrapQuotedScalar(string $value): string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) >= 2) {
            $first = $trimmed[0];
            $last = $trimmed[strlen($trimmed) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return trim(substr($trimmed, 1, -1));
            }
        }

        return $trimmed;
    }

    private static function soapMethodSignatures(SoapClient $client, string $method): array
    {
        try {
            $functions = $client->__getFunctions();
        } catch (Throwable) {
            return [];
        }

        $signatures = [];
        foreach ($functions as $fn) {
            if (stripos($fn, $method) !== false) {
                $signatures[] = $fn;
            }
        }
        return $signatures;
    }

    private static function argsForTicketSignature(
        string $signature,
        string $apiUser,
        string $apiPassword,
        string $apiKey,
        string $xmlDraftBase64
    ): ?array {
        if (!preg_match('/\((.*)\)/', $signature, $m)) {
            return null;
        }
        $paramsRaw = trim($m[1]);
        $paramCount = $paramsRaw === '' ? 0 : count(array_filter(array_map('trim', explode(',', $paramsRaw))));

        if ($paramCount === 2) {
            return $apiKey !== '' ? [$apiKey, $xmlDraftBase64] : [$apiUser, $xmlDraftBase64];
        }
        if ($paramCount === 3) {
            return [$apiUser, $apiPassword, $xmlDraftBase64];
        }
        if ($paramCount === 4) {
            return [$apiUser, $apiPassword, $apiKey, $xmlDraftBase64];
        }
        return null;
    }

    private static function uniqueSoapCalls(array $calls): array
    {
        $seen = [];
        $result = [];
        foreach ($calls as $call) {
            $key = md5(json_encode($call));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $call;
        }
        return $result;
    }

    private static function decorateTicketErrorMessage(string $message, string $xmlDraft): string
    {
        $upper = mb_strtoupper($message, 'UTF-8');
        $hasStartIndex = str_contains($upper, 'STARTINDEX');
        $hasCredits = str_contains($upper, 'CREDITOS') || str_contains($upper, 'CRÉDITOS');
        $isSignedCfdi = str_contains($xmlDraft, ' Sello="')
            && str_contains($xmlDraft, ' Certificado="')
            && str_contains($xmlDraft, ' NoCertificado="');

        if ($hasStartIndex && !$hasCredits && !$isSignedCfdi) {
            $message .= ' | Diagnóstico: el XML enviado no está sellado (faltan Sello/Certificado/NoCertificado). El PAC de wsGetTicket requiere CFDI firmado.';
        }

        return $message;
    }

    private static function extractUuidCandidate(string $value): ?string
    {
        $trimmed = trim($value, "\"' \t\n\r\0\x0B");
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[1-5][A-Fa-f0-9]{3}-[89ABab][A-Fa-f0-9]{3}-[A-Fa-f0-9]{12}$/', $trimmed)) {
            return strtoupper($trimmed);
        }

        if (preg_match('/^[A-Za-z0-9._-]{6,64}$/', $trimmed)) {
            return $trimmed;
        }

        return null;
    }

    private static function isTicketErrorMessage(string $value): bool
    {
        $text = mb_strtoupper(trim($value), 'UTF-8');
        if ($text === '') {
            return false;
        }

        $errorMarkers = [
            'NO DISPONES DE CREDITOS',
            'NO DISPONES DE CRÉDITOS',
            'ERROR',
            'INVALID',
            'NO AUTORIZADO',
            'DENEGADO',
            'STATE',
            'DESCRIPCION',
            'DESCRIPCIÓN',
            'NO PUEDE PROCESAR LA SOLICITUD',
            'STARTINDEX',
            'PARAMETER',
            'PARÁMETRO',
        ];

        foreach ($errorMarkers as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }

        return false;
    }

    private static function buildInvoiceXml(string $uuid, array $data, string $issuedAt, ?array $csdCredentials = null): string
    {
        $emisorRfc = self::xml((string) ($data['emisor_rfc'] ?? 'XAXX010101000'));
        $emisorNombre = self::xml((string) ($data['emisor_nombre'] ?? 'EMISOR DEMO'));
        $emisorRegimen = self::xml((string) ($data['emisor_regimen_fiscal'] ?? '601'));
        $emisorCp = self::xml((string) ($data['emisor_cp'] ?? '00000'));
        $receptorRfc = self::xml((string) ($data['receptor_rfc'] ?? 'XAXX010101000'));
        $receptorNombre = self::xml((string) ($data['receptor_nombre'] ?? 'PUBLICO EN GENERAL'));
        $receptorCp = self::xml((string) ($data['receptor_cp'] ?? '00000'));
        $regimenFiscal = self::xml((string) ($data['receptor_regimen_fiscal'] ?? '601'));
        $usoCfdi = self::xml((string) ($data['receptor_uso_cfdi'] ?? 'G03'));
        $concepto = self::xml((string) ($data['concepto'] ?? 'SERVICIO'));
        $claveProdServ = self::xml((string) ($data['sat_product_key'] ?? '01010101'));
        $claveUnidad = self::xml((string) ($data['sat_unit_key'] ?? 'E48'));
        $unidad = self::xml((string) ($data['unit_name'] ?? 'Servicio'));
        $taxObject = (string) ($data['tax_object'] ?? '01');
        $taxObject = in_array($taxObject, ['01', '02', '03'], true) ? $taxObject : '01';
        $amount = (float) ($data['amount'] ?? 0);
        $subtotalValue = round($amount, 2);
        $taxRate = (float) ($data['tax_rate'] ?? 0.0);
        $hasTax = $taxObject === '02' && $taxRate > 0;
        $taxAmountValue = $hasTax ? round($subtotalValue * $taxRate, 2) : 0.0;
        $totalValue = $subtotalValue + $taxAmountValue;
        $monto = number_format($subtotalValue, 2, '.', '');
        $subtotal = $monto;
        $total = number_format($totalValue, 2, '.', '');
        $fecha = self::xml($issuedAt);
        $folio = self::xml((string) ($data['request_id'] ?? '0'));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<cfdi:Comprobante Version="4.0" Serie="AF" Folio="' . $folio . '" Fecha="' . $fecha . '" '
            . 'SubTotal="' . $subtotal . '" Moneda="MXN" Total="' . $total . '" TipoDeComprobante="I" '
            . 'Exportacion="01" MetodoPago="PUE" FormaPago="01" LugarExpedicion="' . $emisorCp . '" '
            . 'xmlns:cfdi="http://www.sat.gob.mx/cfd/4" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd">' . "\n"
            . '  <cfdi:Emisor Rfc="' . $emisorRfc . '" Nombre="' . $emisorNombre . '" RegimenFiscal="' . $emisorRegimen . '" />' . "\n"
            . '  <cfdi:Receptor Rfc="' . $receptorRfc . '" Nombre="' . $receptorNombre . '" DomicilioFiscalReceptor="' . $receptorCp . '" RegimenFiscalReceptor="' . $regimenFiscal . '" UsoCFDI="' . $usoCfdi . '" />' . "\n"
            . '  <cfdi:Conceptos>' . "\n"
            . '    <cfdi:Concepto ClaveProdServ="' . $claveProdServ . '" Cantidad="1" ClaveUnidad="' . $claveUnidad . '" Unidad="' . $unidad . '" Descripcion="' . $concepto . '" ValorUnitario="' . $monto . '" Importe="' . $monto . '" ObjetoImp="' . $taxObject . '">';

        if ($hasTax) {
            $taxRateFormatted = number_format($taxRate, 6, '.', '');
            $taxAmount = number_format($taxAmountValue, 2, '.', '');
            $xml .= "\n"
                . '      <cfdi:Impuestos>' . "\n"
                . '        <cfdi:Traslados>' . "\n"
                . '          <cfdi:Traslado Base="' . $monto . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="' . $taxRateFormatted . '" Importe="' . $taxAmount . '" />' . "\n"
                . '        </cfdi:Traslados>' . "\n"
                . '      </cfdi:Impuestos>' . "\n"
                . '    </cfdi:Concepto>' . "\n"
                . '  </cfdi:Conceptos>' . "\n"
                . '  <cfdi:Impuestos TotalImpuestosTrasladados="' . $taxAmount . '">' . "\n"
                . '    <cfdi:Traslados>' . "\n"
                . '      <cfdi:Traslado Base="' . $monto . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="' . $taxRateFormatted . '" Importe="' . $taxAmount . '" />' . "\n"
                . '    </cfdi:Traslados>' . "\n"
                . '  </cfdi:Impuestos>' . "\n";
        } else {
            $xml .= ' />' . "\n"
                . '  </cfdi:Conceptos>' . "\n";
        }

        $xml .= '</cfdi:Comprobante>' . "\n";

        if (empty($csdCredentials)) {
            return $xml;
        }

        return self::signCfdi40Xml($xml, $csdCredentials);
    }

    private static function signCfdi40Xml(string $xml, array $csdCredentials): string
    {
        if (empty($csdCredentials['cer_contents']) || empty($csdCredentials['key_contents']) || empty($csdCredentials['password'])) {
            throw new RuntimeException('El negocio no tiene CSD completo para sellar el CFDI.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        if (!$document->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
            throw new RuntimeException('No se pudo preparar el XML para el sellado CFDI.');
        }

        $certificatePem = self::certificateDerToPem((string) $csdCredentials['cer_contents']);
        $certificateResource = openssl_x509_read($certificatePem);
        if ($certificateResource === false) {
            throw new RuntimeException('No se pudo leer el certificado CSD para el sellado.');
        }

        $certificateInfo = openssl_x509_parse($certificateResource);
        if ($certificateInfo === false) {
            throw new RuntimeException('No se pudo interpretar el certificado CSD para el sellado.');
        }

        $comprobante = $document->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Comprobante')->item(0);
        if (!$comprobante instanceof DOMElement) {
            throw new RuntimeException('El XML no contiene el nodo Comprobante requerido para sellar.');
        }

        $comprobante->setAttribute('NoCertificado', self::certificateSerialNumber($certificateInfo));
        $comprobante->setAttribute('Certificado', base64_encode((string) $csdCredentials['cer_contents']));

        $cadena = self::createCadenaOriginal40($document);
        $privateKeyPem = self::privateKeyDerToPem((string) $csdCredentials['key_contents'], (string) $csdCredentials['password']);
        $privateKey = openssl_pkey_get_private($privateKeyPem, (string) $csdCredentials['password']);
        if ($privateKey === false) {
            throw new RuntimeException('No se pudo abrir la llave privada del CSD. Revisa la contraseña.');
        }

        $signature = '';
        if (!openssl_sign($cadena, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo generar el sello digital del CFDI.');
        }

        $comprobante->setAttribute('Sello', base64_encode($signature));

        return $document->saveXML() ?: $xml;
    }

    private static function createCadenaOriginal40(DOMDocument $document): string
    {
        $xsltPath = self::localCadenaOriginalPath();
        if (!is_file($xsltPath)) {
            throw new RuntimeException('No se encontró la plantilla local para crear la cadena original CFDI 4.0.');
        }

        $xslt = new DOMDocument('1.0', 'UTF-8');
        if (!$xslt->load($xsltPath)) {
            throw new RuntimeException('No se pudo cargar la plantilla XSLT local para CFDI 4.0.');
        }

        $processor = new XSLTProcessor();
        $processor->importStylesheet($xslt);
        $cadena = $processor->transformToXML($document);
        if ($cadena === false) {
            throw new RuntimeException('No se pudo generar la cadena original del CFDI.');
        }

        return trim($cadena);
    }

    public static function validateCsdCredentials(array $csdCredentials): array
    {
        $certificatePem = self::certificateDerToPem((string) ($csdCredentials['cer_contents'] ?? ''));
        $certificateResource = openssl_x509_read($certificatePem);
        if ($certificateResource === false) {
            throw new RuntimeException('No se pudo leer el certificado CSD.');
        }

        $certificateInfo = openssl_x509_parse($certificateResource);
        if ($certificateInfo === false) {
            throw new RuntimeException('No se pudo interpretar el certificado CSD.');
        }

        $privateKeyPem = self::privateKeyDerToPem(
            (string) ($csdCredentials['key_contents'] ?? ''),
            (string) ($csdCredentials['password'] ?? '')
        );

        $privateKey = openssl_pkey_get_private($privateKeyPem, (string) ($csdCredentials['password'] ?? ''));
        if ($privateKey === false) {
            throw new RuntimeException('No se pudo abrir la llave privada del CSD. Revisa la contraseña.');
        }

        $certificatePublicKey = openssl_pkey_get_public($certificatePem);
        if ($certificatePublicKey === false) {
            throw new RuntimeException('No se pudo leer la llave pública del certificado CSD.');
        }

        $privateKeyDetails = openssl_pkey_get_details($privateKey);
        $certificateKeyDetails = openssl_pkey_get_details($certificatePublicKey);

        if (
            !is_array($privateKeyDetails)
            || !is_array($certificateKeyDetails)
            || empty($privateKeyDetails['key'])
            || empty($certificateKeyDetails['key'])
        ) {
            throw new RuntimeException('No se pudieron validar las llaves del CSD.');
        }

        if (!hash_equals((string) $privateKeyDetails['key'], (string) $certificateKeyDetails['key'])) {
            throw new RuntimeException('La llave .key no corresponde al certificado .cer proporcionado.');
        }

        return $certificateInfo;
    }

    private static function localCadenaOriginalPath(): string
    {
        $minimal = BASE_PATH . '/resources/cfdi/cadenaoriginal_minima_4_0.xslt';
        if (is_file($minimal)) {
            return $minimal;
        }

        return self::cfdiResourcePath('cadenaoriginal_4_0.xslt');
    }

    private static function cfdiResourcePath(string $filename): string
    {
        $preferred = BASE_PATH . '/resources/cfdi/xml-4.0/' . ltrim($filename, '/');
        if (is_file($preferred)) {
            return $preferred;
        }

        return BASE_PATH . '/resources/cfdi/' . ltrim($filename, '/');
    }

    private static function certificateDerToPem(string $certificateDer): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($certificateDer), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private static function privateKeyDerToPem(string $keyDer, string $password): string
    {
        $directPem = self::privateKeyDerToPemDirect($keyDer);
        $directKey = @openssl_pkey_get_private($directPem, $password);
        if ($directKey !== false) {
            return $directPem;
        }

        $inputPath = self::privateTempFile('af_key_der_');
        $outputPath = self::privateTempFile('af_key_pem_');
        if ($inputPath === false || $outputPath === false) {
            throw new RuntimeException('No se pudieron crear archivos temporales para convertir la llave CSD.');
        }

        try {
            if (@file_put_contents($inputPath, $keyDer, LOCK_EX) === false) {
                throw new RuntimeException('No se pudo escribir la llave temporal para el CSD.');
            }

            $command = implode(' ', array_map('escapeshellarg', [
                self::opensslBinary(),
                'pkcs8',
                '-inform',
                'DER',
                '-in',
                $inputPath,
                '-passin',
                'pass:' . $password,
                '-out',
                $outputPath,
            ])) . ' 2>&1';

            if (!function_exists('shell_exec')) {
                throw new RuntimeException('La función shell_exec() está deshabilitada en este servidor y no se pudo abrir la llave CSD con el método directo.');
            }

            $output = shell_exec($command);
            $pem = @file_get_contents($outputPath);
            if ($pem === false || trim($pem) === '') {
                throw new RuntimeException('La contraseña del CSD es incorrecta.');
            }

            return $pem;
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }

    private static function privateKeyDerToPemDirect(string $keyDer): string
    {
        return "-----BEGIN ENCRYPTED PRIVATE KEY-----\n"
            . chunk_split(base64_encode($keyDer), 64, "\n")
            . "-----END ENCRYPTED PRIVATE KEY-----\n";
    }

    private static function privateTempFile(string $prefix): string|false
    {
        $runtimeDir = STORAGE_PATH . '/private/runtime';
        ensure_private_directory($runtimeDir);

        $tempFile = @tempnam($runtimeDir, $prefix);
        if ($tempFile !== false) {
            @chmod($tempFile, 0600);
            return $tempFile;
        }

        $fallback = $runtimeDir . '/' . $prefix . bin2hex(random_bytes(8)) . '.tmp';
        $created = @file_put_contents($fallback, '');
        if ($created === false) {
            return false;
        }

        @chmod($fallback, 0600);
        return $fallback;
    }

    private static function opensslBinary(): string
    {
        $configured = trim((string) env('OPENSSL_BIN', ''));
        if ($configured !== '') {
            return $configured;
        }

        $xamppBinary = '/Applications/XAMPP/xamppfiles/bin/openssl';
        if (self::pathAllowedByOpenBaseDir($xamppBinary) && is_file($xamppBinary)) {
            return $xamppBinary;
        }

        return 'openssl';
    }

    private static function pathAllowedByOpenBaseDir(string $path): bool
    {
        $openBaseDir = trim((string) ini_get('open_basedir'));
        if ($openBaseDir === '') {
            return true;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $allowedPaths = array_filter(array_map('trim', explode(PATH_SEPARATOR, $openBaseDir)));

        foreach ($allowedPaths as $allowedPath) {
            $normalizedAllowed = rtrim(str_replace('\\', '/', $allowedPath), '/');
            if ($normalizedAllowed === '') {
                continue;
            }

            if ($normalizedPath === $normalizedAllowed || str_starts_with($normalizedPath, $normalizedAllowed . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function certificateSerialNumber(array $certificateInfo): string
    {
        $serial = trim((string) ($certificateInfo['serialNumber'] ?? ''));
        if ($serial !== '' && preg_match('/^\d+$/', $serial) === 1) {
            return $serial;
        }

        $serialHex = trim((string) ($certificateInfo['serialNumberHex'] ?? ''));
        if ($serialHex === '' && str_starts_with(strtolower($serial), '0x')) {
            $serialHex = substr($serial, 2);
        }
        if ($serialHex === '') {
            throw new RuntimeException('No se pudo obtener el número de certificado del CSD.');
        }

        $asciiSerial = @hex2bin($serialHex);
        if ($asciiSerial !== false && preg_match('/^\d+$/', $asciiSerial) === 1) {
            return $asciiSerial;
        }

        return self::hexToDecimalString($serialHex);
    }

    private static function hexToDecimalString(string $hex): string
    {
        $hex = strtoupper(ltrim($hex, '0'));
        if ($hex === '') {
            return '0';
        }

        $decimal = '0';
        foreach (str_split($hex) as $digit) {
            $decimal = self::decimalStringMultiply($decimal, 16);
            $decimal = self::decimalStringAdd($decimal, hexdec($digit));
        }

        return $decimal;
    }

    private static function decimalStringMultiply(string $number, int $multiplier): string
    {
        $carry = 0;
        $result = '';

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $product = ((int) $number[$i] * $multiplier) + $carry;
            $result = ($product % 10) . $result;
            $carry = intdiv($product, 10);
        }

        while ($carry > 0) {
            $result = ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function decimalStringAdd(string $number, int $addend): string
    {
        $carry = $addend;
        $result = '';

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum = (int) $number[$i] + ($carry % 10);
            $carry = intdiv($carry, 10);

            if ($sum >= 10) {
                $sum -= 10;
                $carry++;
            }

            $result = $sum . $result;
        }

        while ($carry > 0) {
            $result = ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function buildInvoicePdf(string $uuid, array $data, string $issuedAt, ?string $xmlContent = null): string
    {
        $pdfData = self::extractPdfDataFromXml($xmlContent, $uuid, $issuedAt, $data);
        $logoAsset = self::preparePdfLogoAsset((string) ($data['business_logo_path'] ?? ''));
        $qrAsset = self::preparePdfQrAsset($pdfData);

        $wkhtmltopdfPdf = self::renderInvoicePdfWithWkhtmltopdf($pdfData, $logoAsset, $qrAsset);
        if (is_string($wkhtmltopdfPdf) && $wkhtmltopdfPdf !== '') {
            return $wkhtmltopdfPdf;
        }

        // Fallback nativo temporalmente deshabilitado mientras ajustamos
        // la plantilla HTML de wkhtmltopdf.
        throw new RuntimeException('No se pudo generar el PDF con wkhtmltopdf.');
    }

    private static function extractPdfDataFromXml(?string $xmlContent, string $uuid, string $issuedAt, array $fallbackData): array
    {
        $fallbackSubtotal = (float) ($fallbackData['amount'] ?? 0);
        $fallbackTaxRate = (float) ($fallbackData['tax_rate'] ?? 0);
        $fallbackTaxAmount = ((string) ($fallbackData['tax_object'] ?? '01') === '02' && $fallbackTaxRate > 0)
            ? round($fallbackSubtotal * $fallbackTaxRate, 2)
            : 0.0;

        $result = [
            'uuid' => $uuid,
            'issued_at' => $issuedAt,
            'certified_at' => $issuedAt,
            'subtotal' => $fallbackSubtotal,
            'tax_amount' => $fallbackTaxAmount,
            'tax_rate' => $fallbackTaxRate,
            'total' => $fallbackSubtotal + $fallbackTaxAmount,
            'emitter_name' => (string) ($fallbackData['emisor_nombre'] ?? 'EMISOR DEMO'),
            'emitter_rfc' => (string) ($fallbackData['emisor_rfc'] ?? 'XAXX010101000'),
            'emitter_regime' => (string) ($fallbackData['emisor_regimen_fiscal'] ?? '601'),
            'emitter_zip' => (string) ($fallbackData['emisor_cp'] ?? '00000'),
            'receiver_name' => (string) ($fallbackData['receptor_nombre'] ?? 'PUBLICO EN GENERAL'),
            'receiver_rfc' => (string) ($fallbackData['receptor_rfc'] ?? 'XAXX010101000'),
            'receiver_zip' => (string) ($fallbackData['receptor_cp'] ?? '00000'),
            'receiver_regime' => (string) ($fallbackData['receptor_regimen_fiscal'] ?? '616'),
            'uso_cfdi' => (string) ($fallbackData['receptor_uso_cfdi'] ?? 'G03'),
            'concept' => (string) ($fallbackData['concepto'] ?? 'SERVICIO'),
            'unit_key' => (string) ($fallbackData['sat_unit_key'] ?? 'E48'),
            'product_key' => (string) ($fallbackData['sat_product_key'] ?? '01010101'),
            'payment_form' => (string) ($fallbackData['payment_form'] ?? '01'),
            'payment_method' => (string) ($fallbackData['payment_method'] ?? 'PUE'),
            'series' => (string) ($fallbackData['series'] ?? 'AF'),
            'folio' => (string) ($fallbackData['request_id'] ?? '0'),
            'currency' => (string) ($fallbackData['currency'] ?? 'MXN'),
            'invoice_type_label' => ((string) ($fallbackData['invoice_type'] ?? 'I')) === 'I' ? 'CFDI de Ingreso' : 'CFDI',
            'template_color' => (string) ($fallbackData['template_color'] ?? '#359BE3'),
            'font_color' => (string) ($fallbackData['font_color'] ?? '#111111'),
            'pac_rfc' => 'Pendiente PAC',
            'sat_cert' => 'N/D',
            'cfdi_seal' => (string) ($fallbackData['cfdi_sello'] ?? 'Generado con CSD del negocio.'),
            'sat_seal' => 'Pendiente PAC',
            'original_chain' => 'UUID ' . $uuid . ' Fecha ' . $issuedAt,
        ];

        if (!is_string($xmlContent) || trim($xmlContent) === '' || !self::looksLikeXml($xmlContent)) {
            return $result;
        }

        $document = new DOMDocument();
        if (!@$document->loadXML($xmlContent, LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
            return $result;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xpath->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $comprobante = $xpath->query('/cfdi:Comprobante')->item(0);
        $emisor = $xpath->query('/cfdi:Comprobante/cfdi:Emisor')->item(0);
        $receptor = $xpath->query('/cfdi:Comprobante/cfdi:Receptor')->item(0);
        $concepto = $xpath->query('/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto[1]')->item(0);
        $traslado = $xpath->query('/cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado[1]')->item(0);
        $timbre = $xpath->query('//tfd:TimbreFiscalDigital[1]')->item(0);

        if ($comprobante instanceof DOMElement) {
            $tipo = (string) $comprobante->getAttribute('TipoDeComprobante');
            $result['series'] = $comprobante->getAttribute('Serie') ?: $result['series'];
            $result['folio'] = $comprobante->getAttribute('Folio') ?: $result['folio'];
            $result['issued_at'] = $comprobante->getAttribute('Fecha') ?: $result['issued_at'];
            $result['currency'] = $comprobante->getAttribute('Moneda') ?: $result['currency'];
            $result['payment_form'] = $comprobante->getAttribute('FormaPago') ?: $result['payment_form'];
            $result['payment_method'] = $comprobante->getAttribute('MetodoPago') ?: $result['payment_method'];
            $result['emitter_zip'] = $comprobante->getAttribute('LugarExpedicion') ?: $result['emitter_zip'];
            $result['subtotal'] = (float) ($comprobante->getAttribute('SubTotal') ?: $result['subtotal']);
            $result['total'] = (float) ($comprobante->getAttribute('Total') ?: $result['total']);
            $result['cfdi_seal'] = $comprobante->getAttribute('Sello') ?: $result['cfdi_seal'];
            $result['invoice_type_label'] = $tipo === 'I' ? 'CFDI de Ingreso' : ($tipo !== '' ? 'CFDI ' . $tipo : $result['invoice_type_label']);
        }

        if ($emisor instanceof DOMElement) {
            $result['emitter_name'] = $emisor->getAttribute('Nombre') ?: $result['emitter_name'];
            $result['emitter_rfc'] = $emisor->getAttribute('Rfc') ?: $result['emitter_rfc'];
            $result['emitter_regime'] = $emisor->getAttribute('RegimenFiscal') ?: $result['emitter_regime'];
        }

        if ($receptor instanceof DOMElement) {
            $result['receiver_name'] = $receptor->getAttribute('Nombre') ?: $result['receiver_name'];
            $result['receiver_rfc'] = $receptor->getAttribute('Rfc') ?: $result['receiver_rfc'];
            $result['receiver_zip'] = $receptor->getAttribute('DomicilioFiscalReceptor') ?: $result['receiver_zip'];
            $result['receiver_regime'] = $receptor->getAttribute('RegimenFiscalReceptor') ?: $result['receiver_regime'];
            $result['uso_cfdi'] = $receptor->getAttribute('UsoCFDI') ?: $result['uso_cfdi'];
        }

        if ($concepto instanceof DOMElement) {
            $result['product_key'] = $concepto->getAttribute('ClaveProdServ') ?: $result['product_key'];
            $result['unit_key'] = $concepto->getAttribute('ClaveUnidad') ?: $result['unit_key'];
            $result['concept'] = $concepto->getAttribute('Descripcion') ?: $result['concept'];
        }

        if ($traslado instanceof DOMElement) {
            $result['tax_rate'] = (float) ($traslado->getAttribute('TasaOCuota') ?: $result['tax_rate']);
            $result['tax_amount'] = (float) ($traslado->getAttribute('Importe') ?: $result['tax_amount']);
        } else {
            $result['tax_amount'] = max(0.0, round($result['total'] - $result['subtotal'], 2));
        }

        if ($timbre instanceof DOMElement) {
            $result['uuid'] = $timbre->getAttribute('UUID') ?: $result['uuid'];
            $result['certified_at'] = $timbre->getAttribute('FechaTimbrado') ?: $result['certified_at'];
            $result['pac_rfc'] = $timbre->getAttribute('RfcProvCertif') ?: $result['pac_rfc'];
            $result['sat_cert'] = $timbre->getAttribute('NoCertificadoSAT') ?: $result['sat_cert'];
            $result['sat_seal'] = $timbre->getAttribute('SelloSAT') ?: $result['sat_seal'];
            $result['original_chain'] = '||1.1|'
                . $result['uuid'] . '|'
                . $result['certified_at'] . '|'
                . $result['pac_rfc'] . '|'
                . $result['cfdi_seal'] . '|'
                . $result['sat_cert'] . '||';
        }

        return $result;
    }

    private static function pdfWrappedLines(string $text, int $chunkLength, int $startY, int $step, int $maxLines): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text)) ?: '';
        if ($normalized === '') {
            return [];
        }

        $lines = [];
        for ($i = 0; $i < $maxLines; $i++) {
            $chunk = substr($normalized, $i * $chunkLength, $chunkLength);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $lines[] = [
                'text' => $chunk,
                'y' => $startY - ($i * $step),
            ];
        }

        return $lines;
    }

    private static function simplePdfFromContent(string $content, array $imageAssets = []): string
    {
        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $xObjectEntries = [];
        $imageObjectNumbers = [];
        $nextObjectNumber = 6;
        foreach ($imageAssets as $index => $asset) {
            $imageName = '/Im' . ($index + 1);
            $imageObjectNumbers[$index] = $nextObjectNumber++;
            $xObjectEntries[] = $imageName . ' ' . $imageObjectNumbers[$index] . ' 0 R';
        }
        $xObjectPart = !empty($xObjectEntries) ? ' /XObject << ' . implode(' ', $xObjectEntries) . ' >>' : '';
        $contentsObject = $nextObjectNumber . ' 0 R';
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >>{$xObjectPart} >> /Contents {$contentsObject} >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
        foreach ($imageAssets as $asset) {
            $objects[] = "<< /Type /XObject /Subtype /Image /Width {$asset['width']} /Height {$asset['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($asset['data']) . " >>\nstream\n" . $asset['data'] . "\nendstream";
        }
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    private static function renderInvoicePdfWithWkhtmltopdf(array $pdfData, ?array $logoAsset, ?array $qrAsset): ?string
    {
        $binary = self::wkhtmltopdfBinary();
        if ($binary === null) {
            app_log('wkhtmltopdf no está disponible o no es ejecutable.', 'error');
            return null;
        }

        $htmlFile = self::tempFileWithExtension('af_pdf_html_', '.html');
        $pdfFile = self::tempFileWithExtension('af_pdf_out_', '.pdf');
        if ($htmlFile === false || $pdfFile === false) {
            app_log('No se pudieron crear archivos temporales para wkhtmltopdf.', 'error');
            return null;
        }

        try {
            $html = self::buildInvoicePdfHtml($pdfData, $logoAsset, $qrAsset);
            if (@file_put_contents($htmlFile, $html, LOCK_EX) === false) {
                app_log('No se pudo escribir el HTML temporal para wkhtmltopdf: ' . $htmlFile, 'error');
                return null;
            }

            $command = implode(' ', array_map('escapeshellarg', [
                $binary,
                '--enable-local-file-access',
                '--margin-top',
                '8',
                '--margin-right',
                '8',
                '--margin-bottom',
                '8',
                '--margin-left',
                '8',
                '--page-size',
                'Letter',
                '--encoding',
                'UTF-8',
                '--print-media-type',
                '--disable-smart-shrinking',
                $htmlFile,
                $pdfFile,
            ])) . ' 2>&1';

            $output = shell_exec($command);
            $pdf = @file_get_contents($pdfFile);
            if ($pdf === false || trim($pdf) === '') {
                app_log('wkhtmltopdf falló. Binario: ' . $binary . ' | HTML: ' . $htmlFile . ' | PDF: ' . $pdfFile . ' | Output: ' . trim((string) $output), 'warning');
                return null;
            }

            return $pdf;
        } finally {
            @unlink($htmlFile);
            @unlink($pdfFile);
        }
    }

    private static function buildInvoicePdfHtml(array $pdfData, ?array $logoAsset, ?array $qrAsset): string
    {
        $logoSrc = self::pdfImageDataUri($logoAsset);
        $qrSrc = self::pdfImageDataUri($qrAsset);
        $footerLogoSrc = self::pdfImageDataUri(self::preparePdfLogoAsset(dirname(BASE_PATH) . '/public_html/img/autofactura.png'));
        $accentColor = self::sanitizePdfColor((string) ($pdfData['template_color'] ?? '#359BE3'), '#359BE3');
        $fontColor = self::sanitizePdfColor((string) ($pdfData['font_color'] ?? '#111111'), '#111111');
        $accentContrast = self::pdfContrastColor($accentColor);
        $softAccentBackground = self::pdfHexToRgba($accentColor, 0.06);
        $taxBorder = self::pdfHexToRgba($accentColor, 0.20);
        $subtotal = '$' . number_format((float) ($pdfData['subtotal'] ?? 0), 2, '.', ',');
        $taxAmount = '$' . number_format((float) ($pdfData['tax_amount'] ?? 0), 2, '.', ',');
        $total = '$' . number_format((float) ($pdfData['total'] ?? 0), 2, '.', ',');
        $taxRatePercent = number_format(((float) ($pdfData['tax_rate'] ?? 0)) * 100, 2, '.', '');
        $totalInWords = self::htmlText(self::amountToSpanishWords((float) ($pdfData['total'] ?? 0), (string) ($pdfData['currency'] ?? 'MXN')));

        $taxRow = '';
        if ((float) ($pdfData['tax_amount'] ?? 0) > 0) {
            $taxRow = '<div class="tax-box">'
                . '<div class="tax-box-title">Traslados</div>'
                . '<table class="tax-table"><tr>'
                . '<td>Impuesto 002</td>'
                . '<td>IVA ' . self::htmlText($taxRatePercent) . '%</td>'
                . '<td>Base ' . self::htmlText($subtotal) . '</td>'
                . '<td>Importe ' . self::htmlText($taxAmount) . '</td>'
                . '</tr></table>'
                . '</div>';
        }

        $logoHtml = $logoSrc !== '' ? '<div class="logo-wrap"><img class="logo" src="' . $logoSrc . '" alt="Logo"></div>' : '';
        $qrHtml = $qrSrc !== '' ? '<img class="qr" src="' . $qrSrc . '" alt="QR CFDI">' : '';

        return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 0; }
body { font-family: Arial, Helvetica, sans-serif; color: ' . $fontColor . '; margin: 0; padding: 18px 22px; font-size: 12px; }
.bar { background: ' . $accentColor . '; color: ' . $accentContrast . '; font-weight: 700; border-radius: 6px; text-align: center; padding: 3px 8px; line-height: 1.15; }
.top { display: table; width: 100%; margin-bottom: 8px; table-layout: fixed; }
.top-left, .top-meta { display: table-cell; vertical-align: top; }
.top-left { width: 50%; padding-right: 18px; }
.top-meta { width: 50%; padding-left: 8px; }
.logo-wrap { width: 100%; max-width: 190px; height: 108px; display: flex; align-items: flex-start; justify-content: flex-start; margin-bottom: 8px; overflow: hidden; }
.logo { max-width: 100%; max-height: 108px; width: auto; height: auto; object-fit: contain; }
.issuer-name { font-size: 18px; font-weight: 700; margin: 10px 0 4px; line-height: 1.15; }
.issuer-rfc { font-size: 14px; font-weight: 700; margin: 0 0 6px; }
.issuer-meta { font-size: 12px; line-height: 1.2; color: #3c4d63; margin-top: 2px; }
.meta-grid { width: 100%; border-collapse: separate; border-spacing: 0 4px; margin-top: 10px; }
.meta-grid td { padding: 0; font-size: 12px; line-height: 1.15; vertical-align: top; }
.meta-grid .label { width: 54%; color: #3c4d63; padding-right: 12px; }
.meta-grid .value { width: 46%; font-weight: 700; text-align: left; }
.client-block { margin-top: 10px; }
.section-body { padding-top: 6px; min-height: 48px; }
.name { font-size: 13px; font-weight: 700; margin-bottom: 4px; line-height: 1.15; }
.line { font-size: 12px; margin-bottom: 3px; line-height: 1.15; }
.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
.items thead th { background: ' . $accentColor . '; color: ' . $accentContrast . '; font-size: 12px; padding: 6px 8px; text-align: left; }
.items thead th.right, .items tbody td.right { text-align: right; }
.items tbody td { border: 1px solid #d8e0ea; padding: 8px 8px; font-size: 12px; vertical-align: top; line-height: 1.15; }
.items tbody tr:nth-child(odd) td { background: ' . $softAccentBackground . '; }
.summary-area { display: table; width: 100%; margin-top: 8px; }
.summary-left, .summary-right { display: table-cell; vertical-align: top; }
.summary-left { width: 58%; padding-right: 12px; }
.summary-right { width: 44%; }
.tax-box { background: ' . $softAccentBackground . '; border: 1px solid ' . $taxBorder . '; border-radius: 6px; padding: 7px 9px; }
.tax-box-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: ' . $accentColor . '; margin-bottom: 5px; letter-spacing: .3px; }
.tax-table { width: 100%; border-collapse: collapse; }
.tax-table td { font-size: 11px; line-height: 1.2; color: ' . $fontColor . '; padding: 3px 8px 3px 0; white-space: nowrap; }
.tax-table td:last-child { padding-right: 0; text-align: right; }
.amount-words-label { color: #666; font-size: 11px; margin-bottom: 2px; }
.amount-words { font-size: 12px; font-weight: 700; line-height: 1.3; }
.money-block { width: 100%; border-collapse: collapse; margin-top: 4px; }
.money-block td { padding: 4px 0; font-size: 13px; font-weight: 700; line-height: 1.1; }
.money-block .label { width: 52%; text-align: right; padding-right: 22px; }
.money-block .value { width: 48%; text-align: right; }
.currency { color: ' . $fontColor . '; font-size: 12px; font-weight: 400; }
.currency .currency-label { color: inherit; font-weight: 400; margin-right: 4px; }
.payments { display: table; width: 100%; margin: 10px 0 8px; }
.payments .cell { display: table-cell; width: 38%; font-size: 12px; vertical-align: top; line-height: 1.15; }
.payments .cell.currency-cell { width: 24%; text-align: center; }
.payments .cell.right { text-align: left; padding-left: 24px; }
.stamp-grid { display: table; width: 100%; margin-bottom: 8px; }
.stamp-grid .cell { display: table-cell; width: 50%; vertical-align: top; }
.stamp-grid .cell.left { padding-right: 6px; }
.stamp-grid .cell.right { padding-left: 6px; }
.stamp-body { padding-top: 6px; font-size: 12px; line-height: 1.15; word-break: break-word; }
.seal-area { display: table; width: 100%; margin-top: 4px; }
.seal-qr, .seal-text { display: table-cell; vertical-align: top; }
.seal-qr { width: 124px; padding-right: 10px; }
.seal-text { width: auto; }
.qr { width: 122px; height: 122px; object-fit: contain; }
.seal-block { margin-bottom: 6px; page-break-inside: avoid; }
.seal-text-body { font-size: 10px; line-height: 1.2; word-break: break-all; padding-top: 5px; }
.compact-gap { height: 8px; }
.pdf-footer { margin-top: 10px; display: table; width: 100%; }
.pdf-footer-text, .pdf-footer-logo { display: table-cell; vertical-align: middle; }
.pdf-footer-text { font-size: 10px; color: #6f879a; }
.pdf-footer-logo { text-align: right; }
.footer-logo { max-width: 72px; max-height: 24px; object-fit: contain; opacity: .4; }
</style>
</head>
<body>
  <div class="top">
    <div class="top-left">' . $logoHtml . '
      <div class="issuer-name">' . self::htmlText((string) ($pdfData['emitter_name'] ?? '')) . '</div>
      <div class="issuer-rfc">RFC ' . self::htmlText((string) ($pdfData['emitter_rfc'] ?? '')) . '</div>
      <div class="issuer-meta">Regimen fiscal ' . self::htmlText((string) ($pdfData['emitter_regime'] ?? '')) . '   C.P. ' . self::htmlText((string) ($pdfData['emitter_zip'] ?? '')) . '</div>
    </div>
    <div class="top-meta">
      <div class="bar">' . self::htmlText((string) ($pdfData['invoice_type_label'] ?? 'CFDI')) . '</div>
      <table class="meta-grid">
        <tr><td class="label">Serie</td><td class="value">' . self::htmlText((string) ($pdfData['series'] ?? '')) . '</td></tr>
        <tr><td class="label">Folio</td><td class="value">' . self::htmlText((string) ($pdfData['folio'] ?? '')) . '</td></tr>
        <tr><td class="label">Lugar de emision</td><td class="value">' . self::htmlText((string) ($pdfData['emitter_zip'] ?? '')) . '</td></tr>
        <tr><td class="label">Fecha y hora de emision</td><td class="value">' . self::htmlText((string) ($pdfData['issued_at'] ?? '')) . '</td></tr>
      </table>
      <div class="client-block">
      <div class="bar">Informacion del cliente</div>
      <div class="section-body">
        <div class="name">' . self::htmlText((string) ($pdfData['receiver_name'] ?? '')) . '</div>
        <div class="line">RFC ' . self::htmlText((string) ($pdfData['receiver_rfc'] ?? '')) . '</div>
        <div class="line">C.P. ' . self::htmlText((string) ($pdfData['receiver_zip'] ?? '')) . '   Regimen ' . self::htmlText((string) ($pdfData['receiver_regime'] ?? '')) . '</div>
        <div class="line">Uso CFDI ' . self::htmlText((string) ($pdfData['uso_cfdi'] ?? '')) . '</div>
      </div>
    </div>
    </div>
  </div>
  <table class="items">
    <thead>
      <tr>
        <th>Codigo</th>
        <th>Clave unidad</th>
        <th>Descripcion</th>
        <th class="right">Valor Unitario</th>
        <th class="right">Cantidad</th>
        <th class="right">Importe</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>' . self::htmlText((string) ($pdfData['product_key'] ?? '')) . '</td>
        <td>' . self::htmlText((string) ($pdfData['unit_key'] ?? '')) . '</td>
        <td>' . self::htmlText((string) ($pdfData['concept'] ?? '')) . '</td>
        <td class="right">' . self::htmlText($subtotal) . '</td>
        <td class="right">1.00</td>
        <td class="right">' . self::htmlText($subtotal) . '</td>
      </tr>
    </tbody>
  </table>
  <div class="summary-area">
    <div class="summary-left">
      ' . $taxRow . '
      <div class="compact-gap"></div>
      <div class="amount-words-label">Importe en letra</div>
      <div class="amount-words">' . $totalInWords . '</div>
    </div>
    <div class="summary-right">
      <table class="money-block">
        <tr><td class="label">Subtotal</td><td class="value">' . self::htmlText($subtotal) . '</td></tr>
        <tr><td class="label">IVA</td><td class="value">' . self::htmlText($taxAmount) . '</td></tr>
        <tr><td class="label">Total</td><td class="value">' . self::htmlText($total) . '</td></tr>
      </table>
    </div>
  </div>
  <div class="payments">
    <div class="cell">Forma de pago ' . self::htmlText((string) ($pdfData['payment_form'] ?? '')) . '</div>
    <div class="cell currency-cell"><span class="currency"><span class="currency-label">Moneda</span>' . self::htmlText((string) ($pdfData['currency'] ?? 'MXN')) . '</span></div>
    <div class="cell right">Metodo de pago ' . self::htmlText((string) ($pdfData['payment_method'] ?? '')) . '</div>
  </div>
  <div class="stamp-grid">
    <div class="cell left"><div class="bar">Folio fiscal</div><div class="stamp-body">' . self::htmlText((string) ($pdfData['uuid'] ?? '')) . '</div></div>
    <div class="cell right"><div class="bar">Fecha de certificacion</div><div class="stamp-body">' . self::htmlText((string) ($pdfData['certified_at'] ?? '')) . '</div></div>
  </div>
  <div class="stamp-grid">
    <div class="cell left"><div class="bar">RFC proveedor de certificacion</div><div class="stamp-body">' . self::htmlText((string) ($pdfData['pac_rfc'] ?? '')) . '</div></div>
    <div class="cell right"><div class="bar">Numero de certificado SAT</div><div class="stamp-body">' . self::htmlText((string) ($pdfData['sat_cert'] ?? '')) . '</div></div>
  </div>
  <div class="seal-area">
    <div class="seal-qr">' . $qrHtml . '</div>
    <div class="seal-text">
      <div class="seal-block"><div class="bar">Sello digital del CFDI</div><div class="seal-text-body">' . self::htmlText((string) ($pdfData['cfdi_seal'] ?? '')) . '</div></div>
      <div class="seal-block"><div class="bar">Sello digital del SAT</div><div class="seal-text-body">' . self::htmlText((string) ($pdfData['sat_seal'] ?? '')) . '</div></div>
      <div class="seal-block"><div class="bar">Cadena original del timbre</div><div class="seal-text-body">' . self::htmlText((string) ($pdfData['original_chain'] ?? '')) . '</div></div>
    </div>
  </div>
  <div class="pdf-footer">
    <div class="pdf-footer-text">PDF generado por AutoFactura</div>
    <div class="pdf-footer-logo">' . ($footerLogoSrc !== '' ? '<img class="footer-logo" src="' . $footerLogoSrc . '" alt="AutoFactura">' : '') . '</div>
  </div>
</body>
</html>';
    }

    private static function pdfImageDataUri(?array $asset): string
    {
        if (!$asset || empty($asset['data'])) {
            return '';
        }

        return 'data:image/jpeg;base64,' . base64_encode((string) $asset['data']);
    }

    private static function wkhtmltopdfBinary(): ?string
    {
        $configured = trim((string) env('WKHTMLTOPDF_BIN', ''));
        if ($configured !== '' && is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        foreach ([
            '/usr/local/bin/wkhtmltopdf',
            '/Applications/XAMPP/xamppfiles/bin/wkhtmltopdf',
            '/opt/homebrew/bin/wkhtmltopdf',
        ] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        if (!function_exists('shell_exec')) {
            return null;
        }

        $resolved = trim((string) shell_exec('command -v wkhtmltopdf 2>/dev/null'));
        return ($resolved !== '' && is_file($resolved) && is_executable($resolved)) ? $resolved : null;
    }

    private static function tempFileWithExtension(string $prefix, string $extension): string|false
    {
        $runtimeDir = STORAGE_PATH . '/private/runtime';
        ensure_private_directory($runtimeDir);

        $base = tempnam($runtimeDir, $prefix);
        if ($base === false) {
            return false;
        }

        $target = $base . $extension;
        @unlink($base);

        $created = @file_put_contents($target, '');
        if ($created === false) {
            return false;
        }

        @chmod($target, 0600);
        return $target;
    }

    private static function htmlText(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function sanitizePdfColor(string $value, string $default): string
    {
        $color = strtoupper(trim($value));
        if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
            return $color;
        }

        return strtoupper($default);
    }

    private static function pdfContrastColor(string $hexColor): string
    {
        $hex = ltrim(self::sanitizePdfColor($hexColor, '#000000'), '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $luminance >= 150 ? '#111111' : '#FFFFFF';
    }

    private static function pdfHexToRgba(string $hexColor, float $alpha): string
    {
        $hex = ltrim(self::sanitizePdfColor($hexColor, '#000000'), '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $alpha = max(0, min(1, $alpha));

        return sprintf('rgba(%d, %d, %d, %.2F)', $r, $g, $b, $alpha);
    }

    private static function preparePdfQrAsset(array $pdfData): ?array
    {
        $qrText = self::buildSatQrText($pdfData);
        if ($qrText === '') {
            return null;
        }

        $libraryPath = BASE_PATH . '/app/Libraries/phpqrcode/qrlib.php';
        if (!is_file($libraryPath) || !extension_loaded('gd')) {
            return null;
        }

        require_once $libraryPath;
        if (!class_exists('QRcode')) {
            return null;
        }

        $qrFile = self::privateTempFile('af_qr_');
        if ($qrFile === false) {
            return null;
        }

        try {
            QRcode::png($qrText, $qrFile, 'M', 4, 1);

            $image = @imagecreatefrompng($qrFile);
            if (!$image) {
                return null;
            }

            try {
                $width = imagesx($image);
                $height = imagesy($image);
                if ($width <= 0 || $height <= 0) {
                    return null;
                }

                ob_start();
                imageinterlace($image, false);
                imagejpeg($image, null, 95);
                $jpegData = (string) ob_get_clean();
                if ($jpegData === '') {
                    return null;
                }

                return [
                    'data' => $jpegData,
                    'width' => $width,
                    'height' => $height,
                    'display_width' => 90.0,
                    'display_height' => 90.0,
                    'x' => 24.0,
                    'y' => 104.0,
                ];
            } finally {
                imagedestroy($image);
            }
        } finally {
            @unlink($qrFile);
        }
    }

    private static function buildSatQrText(array $pdfData): string
    {
        $uuid = trim((string) ($pdfData['uuid'] ?? ''));
        $emitterRfc = strtoupper(trim((string) ($pdfData['emitter_rfc'] ?? '')));
        $receiverRfc = strtoupper(trim((string) ($pdfData['receiver_rfc'] ?? '')));
        $total = (float) ($pdfData['total'] ?? 0);
        $cfdiSeal = trim((string) ($pdfData['cfdi_seal'] ?? ''));

        if ($uuid === '' || $emitterRfc === '' || $receiverRfc === '' || $cfdiSeal === '') {
            return '';
        }

        $sealFragment = substr($cfdiSeal, -8);
        if ($sealFragment === false) {
            $sealFragment = '';
        }

        return 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx'
            . '?id=' . $uuid
            . '&re=' . $emitterRfc
            . '&rr=' . $receiverRfc
            . '&tt=' . number_format($total, 6, '.', '')
            . '&fe=' . $sealFragment;
    }

    private static function amountToSpanishWords(float $amount, string $currencyCode = 'MXN'): string
    {
        $amount = round($amount, 2);
        $whole = (int) floor($amount);
        $cents = (int) round(($amount - $whole) * 100);
        if ($cents === 100) {
            $whole++;
            $cents = 0;
        }

        $currencyLabel = strtoupper($currencyCode) === 'MXN' ? 'PESOS' : strtoupper($currencyCode);

        return trim(self::numberToSpanishWords($whole) . ' ' . $currencyLabel . ' ' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT) . '/100 M.N.');
    }

    private static function numberToSpanishWords(int $number): string
    {
        if ($number === 0) {
            return 'CERO';
        }

        if ($number < 0) {
            return 'MENOS ' . self::numberToSpanishWords(abs($number));
        }

        $parts = [];

        $millions = intdiv($number, 1000000);
        if ($millions > 0) {
            $parts[] = $millions === 1
                ? 'UN MILLON'
                : self::convertHundredsToSpanish($millions) . ' MILLONES';
            $number %= 1000000;
        }

        $thousands = intdiv($number, 1000);
        if ($thousands > 0) {
            $parts[] = $thousands === 1
                ? 'MIL'
                : self::convertHundredsToSpanish($thousands) . ' MIL';
            $number %= 1000;
        }

        if ($number > 0) {
            $parts[] = self::convertHundredsToSpanish($number);
        }

        return implode(' ', array_filter($parts));
    }

    private static function convertHundredsToSpanish(int $number): string
    {
        $units = [
            0 => '',
            1 => 'UNO',
            2 => 'DOS',
            3 => 'TRES',
            4 => 'CUATRO',
            5 => 'CINCO',
            6 => 'SEIS',
            7 => 'SIETE',
            8 => 'OCHO',
            9 => 'NUEVE',
            10 => 'DIEZ',
            11 => 'ONCE',
            12 => 'DOCE',
            13 => 'TRECE',
            14 => 'CATORCE',
            15 => 'QUINCE',
            16 => 'DIECISEIS',
            17 => 'DIECISIETE',
            18 => 'DIECIOCHO',
            19 => 'DIECINUEVE',
            20 => 'VEINTE',
            21 => 'VEINTIUNO',
            22 => 'VEINTIDOS',
            23 => 'VEINTITRES',
            24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO',
            26 => 'VEINTISEIS',
            27 => 'VEINTISIETE',
            28 => 'VEINTIOCHO',
            29 => 'VEINTINUEVE',
        ];

        $tens = [
            30 => 'TREINTA',
            40 => 'CUARENTA',
            50 => 'CINCUENTA',
            60 => 'SESENTA',
            70 => 'SETENTA',
            80 => 'OCHENTA',
            90 => 'NOVENTA',
        ];

        $hundreds = [
            100 => 'CIEN',
            200 => 'DOSCIENTOS',
            300 => 'TRESCIENTOS',
            400 => 'CUATROCIENTOS',
            500 => 'QUINIENTOS',
            600 => 'SEISCIENTOS',
            700 => 'SETECIENTOS',
            800 => 'OCHOCIENTOS',
            900 => 'NOVECIENTOS',
        ];

        if ($number < 30) {
            return $units[$number];
        }

        if ($number < 100) {
            $ten = intdiv($number, 10) * 10;
            $unit = $number % 10;
            return $unit === 0 ? $tens[$ten] : $tens[$ten] . ' Y ' . $units[$unit];
        }

        if ($number === 100) {
            return 'CIEN';
        }

        if ($number < 200) {
            return 'CIENTO ' . self::convertHundredsToSpanish($number - 100);
        }

        $hundred = intdiv($number, 100) * 100;
        $remainder = $number % 100;

        return $remainder === 0
            ? $hundreds[$hundred]
            : $hundreds[$hundred] . ' ' . self::convertHundredsToSpanish($remainder);
    }

    private static function preparePdfLogoAsset(string $logoPath): ?array
    {
        if ($logoPath === '' || !is_file($logoPath) || !extension_loaded('gd')) {
            return null;
        }

        $imageInfo = @getimagesize($logoPath);
        if ($imageInfo === false) {
            return null;
        }

        $mimeType = (string) ($imageInfo['mime'] ?? '');
        $sourceImage = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($logoPath),
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($logoPath) : false,
            'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($logoPath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($logoPath) : false,
            default => false,
        };

        if (!$sourceImage) {
            return null;
        }

        try {
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            if ($sourceWidth <= 0 || $sourceHeight <= 0) {
                return null;
            }

            $maxWidth = 120.0;
            $maxHeight = 46.0;
            $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1.0);
            $displayWidth = max(1.0, round($sourceWidth * $scale, 2));
            $displayHeight = max(1.0, round($sourceHeight * $scale, 2));

            ob_start();
            imageinterlace($sourceImage, false);
            imagejpeg($sourceImage, null, 88);
            $jpegData = (string) ob_get_clean();
            if ($jpegData === '') {
                return null;
            }

            return [
                'data' => $jpegData,
                'width' => $sourceWidth,
                'height' => $sourceHeight,
                'display_width' => $displayWidth,
                'display_height' => $displayHeight,
                'x' => 24.0,
                'y' => 690.0,
            ];
        } finally {
            imagedestroy($sourceImage);
        }
    }

    private static function pdfText(string $text): string
    {
        return self::pdfEscape($text);
    }

    private static function pdfEscape(string $text): string
    {
        $text = str_replace(["\r", "\n"], ' ', $text);
        $text = preg_replace('/[^\x20-\x7E]/', '?', $text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
