<?php
/**
 * Servicio: MensajesXyzService
 * Integración configurable para envío de WhatsApp mediante api.mensajes.xyz
 */

class MensajesXyzService
{
    public static function sendInvoiceLinkTemplate(
        string $toPhone,
        string $customerName,
        string $businessName,
        string $conceptName,
        string $formattedAmount,
        string $expiresText,
        string $link,
        string $orderId = ''
    ): array {
        $enabled = filter_var((string) env('MENSAJES_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return ['success' => false, 'message' => 'MENSAJES desactivado (MENSAJES_ENABLED=false).'];
        }

        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL no disponible en el servidor.'];
        }

        $baseUrl = rtrim((string) env('MENSAJES_BASE_URL', 'https://api.mensajes.xyz'), '/');
        $sendPath = trim((string) env('MENSAJES_SEND_PATH', '/v1/notify.php'));
        if ($sendPath === '') {
            $sendPath = '/v1/notify.php';
        }
        if (!str_starts_with($sendPath, '/')) {
            $sendPath = '/' . $sendPath;
        }

        $url = $baseUrl . $sendPath;
        $token = trim((string) env('MENSAJES_API_KEY', ''));
        $timeout = (int) env('MENSAJES_TIMEOUT', 20);

        if ($token === '') {
            return ['success' => false, 'message' => 'Falta MENSAJES_API_KEY en .env'];
        }

        $to = self::normalizePhone($toPhone);
        if ($to === '') {
            return ['success' => false, 'message' => 'Teléfono inválido para envío de WhatsApp.'];
        }

        $payload = self::notifyPayload(
            $to,
            $customerName,
            $businessName,
            $conceptName,
            $formattedAmount,
            $expiresText,
            $link,
            $orderId
        );

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $headers[] = 'Authorization: Bearer ' . $token;

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['success' => false, 'message' => 'No se pudo serializar payload JSON para MENSAJES.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout > 0 ? $timeout : 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => 'Error de conexión MENSAJES: ' . ($curlError ?: 'sin respuesta')];
        }

        $json = json_decode((string) $response, true);
        $okByCode = $httpCode >= 200 && $httpCode < 300;
        $okByBody = is_array($json) && (
            (bool) ($json['success'] ?? false)
            || strtolower((string) ($json['status'] ?? '')) === 'success'
            || strtolower((string) ($json['message'] ?? '')) === 'queued'
        );

        if (!($okByCode || $okByBody)) {
            $message = is_array($json)
                ? ($json['message'] ?? ($json['error'] ?? 'Error desconocido MENSAJES'))
                : ('HTTP ' . $httpCode . ' sin JSON válido');

            return [
                'success' => false,
                'message' => 'MENSAJES error: ' . $message,
                'http_code' => $httpCode,
                'raw' => $response,
            ];
        }

        return [
            'success' => true,
            'message' => is_array($json) ? ($json['message'] ?? 'Mensaje enviado correctamente.') : 'Mensaje enviado correctamente.',
            'http_code' => $httpCode,
            'raw' => $json ?? $response,
        ];
    }

    private static function notifyPayload(
        string $to,
        string $customerName,
        string $businessName,
        string $conceptName,
        string $formattedAmount,
        string $expiresText,
        string $link,
        string $orderId
    ): array {
        $templateName = trim((string) env('MENSAJES_TEMPLATE_NAME', 'autofactura_factura_link_v1'));
        $language = trim((string) env('MENSAJES_TEMPLATE_LANGUAGE', 'es_MX'));
        $templateFormat = strtolower(trim((string) env('MENSAJES_TEMPLATE_FORMAT', 'body_named')));
        $includeAccountName = filter_var((string) env('MENSAJES_INCLUDE_ACCOUNT_NAME', 'false'), FILTER_VALIDATE_BOOLEAN);
        $accountName = trim((string) env('MENSAJES_ACCOUNT_NAME', ''));
        $nameParam = trim((string) env('MENSAJES_PARAM_NAME', 'nombre'));
        $businessParam = trim((string) env('MENSAJES_PARAM_BUSINESS', 'negocio'));
        $conceptParam = trim((string) env('MENSAJES_PARAM_CONCEPT', 'concepto'));
        $amountParam = trim((string) env('MENSAJES_PARAM_AMOUNT', 'importe'));
        $expiresParam = trim((string) env('MENSAJES_PARAM_EXPIRES', 'vence'));
        $linkParam = trim((string) env('MENSAJES_PARAM_LINK', 'link_factura'));
        $safeName = $customerName !== '' ? $customerName : 'cliente';
        $metadataOrder = $orderId !== '' ? $orderId : ('REQ-' . date('YmdHis'));

        $template = [
            'name' => $templateName,
            'lang' => $language,
        ];

        if ($templateFormat === 'params') {
            $template['params'] = [
                self::waText($safeName),
                self::waText($businessName),
                self::waText($conceptName),
                self::waText($formattedAmount),
                self::waText($expiresText),
                self::waText($link),
            ];
        } else {
            $template['body'] = [
                ['type' => 'text', 'parameter_name' => $nameParam, 'text' => self::waText($safeName)],
                ['type' => 'text', 'parameter_name' => $businessParam, 'text' => self::waText($businessName)],
                ['type' => 'text', 'parameter_name' => $conceptParam, 'text' => self::waText($conceptName)],
                ['type' => 'text', 'parameter_name' => $amountParam, 'text' => self::waText($formattedAmount)],
                ['type' => 'text', 'parameter_name' => $expiresParam, 'text' => self::waText($expiresText)],
                ['type' => 'text', 'parameter_name' => $linkParam, 'text' => self::waText($link)],
            ];
        }

        $payload = [
            'to' => $to,
            'template' => $template,
            'metadata' => [
                'order_id' => $metadataOrder,
            ],
        ];

        if ($includeAccountName && $accountName !== '') {
            $payload['account_name'] = $accountName;
        }

        return $payload;
    }

    private static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return '';
        }

        $countryCode = preg_replace('/\D+/', '', (string) env('MENSAJES_DEFAULT_COUNTRY', '52'));
        if ($countryCode === '') {
            $countryCode = '52';
        }

        // México: si llega local de 10 dígitos, anteponer country code
        if (strlen($digits) === 10) {
            $digits = $countryCode . $digits;
        }

        return $digits;
    }

    private static function waText(string $value, string $default = '-'): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $value)));
        if ($clean === '' || strtolower($clean) === 'null') {
            return $default;
        }
        return $clean;
    }
}
