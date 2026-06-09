<?php
/**
 * Servicio: ClipService
 * Generación y consulta de links de pago Clip Checkout v2.
 */

class ClipService
{
    public static function createCheckoutLink(float $amount, string $description, string $successUrl, string $errorUrl, string $defaultUrl): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'El monto debe ser mayor a cero.'];
        }

        foreach ([$successUrl, $errorUrl, $defaultUrl] as $url) {
            if (!self::isValidHttpsUrl($url)) {
                return ['success' => false, 'message' => 'Las URLs de retorno de Clip deben ser HTTPS válidas.'];
            }
        }

        $token = self::authorizationToken();
        if ($token === null) {
            return ['success' => false, 'message' => 'Faltan credenciales de Clip en el entorno.'];
        }

        $endpoint = rtrim((string) env('CLIP_API_BASE', 'https://api.payclip.com/v2/checkout'), '/');
        $payload = [
            'amount' => $amount,
            'currency' => 'MXN',
            'purchase_description' => self::maxLen(trim($description), 120),
            'redirection_url' => [
                'success' => $successUrl,
                'error' => $errorUrl,
                'default' => $defaultUrl,
            ],
        ];

        return self::jsonRequest('POST', $endpoint, $token, $payload);
    }

    public static function getCheckoutStatus(string $paymentRequestId): array
    {
        $paymentRequestId = trim($paymentRequestId);
        if ($paymentRequestId === '') {
            return ['success' => false, 'message' => 'Falta el payment_request_id de Clip.'];
        }

        $token = self::authorizationToken();
        if ($token === null) {
            return ['success' => false, 'message' => 'Faltan credenciales de Clip en el entorno.'];
        }

        $endpoint = 'https://api.payclip.com/v2/checkout/' . rawurlencode($paymentRequestId);
        return self::jsonRequest('GET', $endpoint, $token);
    }

    public static function extractPaymentLinkData(array $response): array
    {
        $data = $response['data'] ?? [];

        return [
            'payment_request_id' => (string) ($data['payment_request_id'] ?? ''),
            'payment_request_url' => (string) ($data['payment_request_url'] ?? ''),
            'clip_status' => (string) ($data['status'] ?? ''),
            'raw' => $data,
        ];
    }

    public static function isPaidStatus(?string $status): bool
    {
        $normalized = strtoupper(trim((string) $status));
        return in_array($normalized, [
            'CHECKOUT_COMPLETED',
            'PAYMENT_COMPLETED',
            'COMPLETED',
            'PAID',
            'APPROVED',
        ], true);
    }

    private static function authorizationToken(): ?string
    {
        $token = trim((string) env('CLIP_API_TOKEN', ''));
        $apiKey = trim((string) env('CLIP_API_KEY', ''));
        $apiSecret = trim((string) (env('CLIP_API_SECRET', '') ?: env('CLIP_SECRET_KEY', '')));

        if ($token === '' && $apiKey !== '' && $apiSecret !== '') {
            $token = 'Basic ' . base64_encode($apiKey . ':' . $apiSecret);
        } elseif ($token !== '' && !preg_match('/^(Bearer|Basic)\s+/i', $token)) {
            $token = 'Bearer ' . $token;
        }

        return $token !== '' ? $token : null;
    }

    private static function jsonRequest(string $method, string $url, string $token, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL no está disponible en el servidor.'];
        }

        $ch = curl_init($url);
        $headers = [
            'Authorization: ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'message' => 'Error al conectar con Clip: ' . ($curlError ?: 'sin respuesta')];
        }

        $decoded = json_decode((string) $raw, true);
        $data = is_array($decoded) ? $decoded : ['raw' => $raw];
        $success = $status >= 200 && $status < 300;

        if (!$success) {
            $message = (string) ($data['message'] ?? $data['error'] ?? 'Error Clip');
            return [
                'success' => false,
                'message' => 'Clip respondió HTTP ' . $status . ': ' . $message,
                'status_code' => $status,
                'data' => $data,
            ];
        }

        return [
            'success' => true,
            'status_code' => $status,
            'data' => $data,
        ];
    }

    private static function isValidHttpsUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https';
    }

    private static function maxLen(string $value, int $max): string
    {
        return strlen($value) <= $max ? $value : substr($value, 0, $max);
    }
}
