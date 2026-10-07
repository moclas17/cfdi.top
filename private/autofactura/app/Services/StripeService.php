<?php
/**
 * Servicio: StripeService
 * Maneja Checkout Sessions de suscripción y validación de webhooks Stripe.
 */

class StripeService
{
    public static function createSubscriptionCheckout(
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        ?string $customerEmail = null,
        ?string $clientReferenceId = null
    ): array {
        $priceId = trim($priceId);
        if ($priceId === '') {
            return ['success' => false, 'message' => 'Falta el Price ID de Stripe para esta suscripción.'];
        }

        foreach ([$successUrl, $cancelUrl] as $url) {
            if (!self::isValidUrl($url)) {
                return ['success' => false, 'message' => 'Las URLs de retorno de Stripe no son válidas.'];
            }
        }

        $secretKey = self::secretKey();
        if ($secretKey === null) {
            return ['success' => false, 'message' => 'Faltan credenciales de Stripe en el entorno.'];
        }

        $payload = [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'billing_address_collection' => 'auto',
        ];

        if ($customerEmail !== null && trim($customerEmail) !== '') {
            $payload['customer_email'] = trim($customerEmail);
        }

        if ($clientReferenceId !== null && trim($clientReferenceId) !== '') {
            $payload['client_reference_id'] = trim($clientReferenceId);
        }

        foreach ($metadata as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $payload['metadata'][$normalizedKey] = (string) $value;
            $payload['subscription_data']['metadata'][$normalizedKey] = (string) $value;
        }

        return self::apiRequest('POST', '/v1/checkout/sessions', $secretKey, $payload);
    }

    public static function getCheckoutSession(string $sessionId): array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return ['success' => false, 'message' => 'Falta el Checkout Session ID de Stripe.'];
        }

        $secretKey = self::secretKey();
        if ($secretKey === null) {
            return ['success' => false, 'message' => 'Faltan credenciales de Stripe en el entorno.'];
        }

        return self::apiRequest('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId), $secretKey, [
            'expand' => ['subscription'],
        ]);
    }

    public static function getSubscription(string $subscriptionId): array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            return ['success' => false, 'message' => 'Falta el Subscription ID de Stripe.'];
        }

        $secretKey = self::secretKey();
        if ($secretKey === null) {
            return ['success' => false, 'message' => 'Faltan credenciales de Stripe en el entorno.'];
        }

        return self::apiRequest('GET', '/v1/subscriptions/' . rawurlencode($subscriptionId), $secretKey);
    }

    public static function previewSubscriptionPriceChange(string $subscriptionId, string $newPriceId, ?int $prorationDate = null): array
    {
        $subscriptionResult = self::getSubscription($subscriptionId);
        if (empty($subscriptionResult['success'])) {
            return $subscriptionResult;
        }

        $subscription = self::extractSubscriptionData($subscriptionResult);
        $subscriptionItemId = trim((string) ($subscription['subscription_item_id'] ?? ''));
        if ($subscriptionItemId === '') {
            return ['success' => false, 'message' => 'No se encontró el item activo de la suscripción en Stripe.'];
        }

        $secretKey = self::secretKey();
        if ($secretKey === null) {
            return ['success' => false, 'message' => 'Faltan credenciales de Stripe en el entorno.'];
        }

        $prorationDate = $prorationDate ?? time();

        return self::apiRequest('POST', '/v1/invoices/create_preview', $secretKey, [
            'subscription' => $subscriptionId,
            'subscription_details' => [
                'proration_date' => $prorationDate,
                'items' => [
                    [
                        'id' => $subscriptionItemId,
                        'price' => $newPriceId,
                        'quantity' => (int) ($subscription['subscription_item_quantity'] ?? 1),
                    ],
                ],
                'proration_behavior' => 'always_invoice',
            ],
        ]);
    }

    public static function updateSubscriptionPrice(
        string $subscriptionId,
        string $newPriceId,
        array $subscriptionMetadata = [],
        array $subscriptionItemMetadata = [],
        ?int $prorationDate = null
    ): array {
        $subscriptionResult = self::getSubscription($subscriptionId);
        if (empty($subscriptionResult['success'])) {
            return $subscriptionResult;
        }

        $subscription = self::extractSubscriptionData($subscriptionResult);
        $subscriptionItemId = trim((string) ($subscription['subscription_item_id'] ?? ''));
        if ($subscriptionItemId === '') {
            return ['success' => false, 'message' => 'No se encontró el item activo de la suscripción en Stripe.'];
        }

        $secretKey = self::secretKey();
        if ($secretKey === null) {
            return ['success' => false, 'message' => 'Faltan credenciales de Stripe en el entorno.'];
        }

        $prorationDate = $prorationDate ?? time();

        $payload = [
            'items' => [
                [
                    'id' => $subscriptionItemId,
                    'price' => $newPriceId,
                    'quantity' => (int) ($subscription['subscription_item_quantity'] ?? 1),
                ],
            ],
            'proration_behavior' => 'always_invoice',
            'proration_date' => $prorationDate,
            'payment_behavior' => 'pending_if_incomplete',
        ];

        foreach ($subscriptionMetadata as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $payload['metadata'][$normalizedKey] = (string) $value;
        }

        foreach ($subscriptionItemMetadata as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $payload['items'][0]['metadata'][$normalizedKey] = (string) $value;
        }

        return self::apiRequest('POST', '/v1/subscriptions/' . rawurlencode($subscriptionId), $secretKey, $payload);
    }

    public static function extractCheckoutSessionData(array $response): array
    {
        $data = $response['data'] ?? [];
        $subscription = $data['subscription'] ?? null;
        $subscriptionId = '';
        $subscriptionStatus = '';
        $subscriptionMetadata = [];

        if (is_array($subscription)) {
            $subscriptionId = (string) ($subscription['id'] ?? '');
            $subscriptionStatus = (string) ($subscription['status'] ?? '');
            $subscriptionMetadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
        } elseif (is_string($subscription)) {
            $subscriptionId = $subscription;
        }

        return [
            'checkout_session_id' => (string) ($data['id'] ?? ''),
            'checkout_session_url' => (string) ($data['url'] ?? ''),
            'checkout_session_status' => (string) ($data['status'] ?? ''),
            'payment_status' => (string) ($data['payment_status'] ?? ''),
            'subscription_id' => $subscriptionId,
            'subscription_status' => $subscriptionStatus,
            'subscription_metadata' => $subscriptionMetadata,
            'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            'raw' => $data,
        ];
    }

    public static function extractSubscriptionData(array $response): array
    {
        $data = $response['data'] ?? [];

        return [
            'subscription_id' => (string) ($data['id'] ?? ''),
            'subscription_status' => (string) ($data['status'] ?? ''),
            'customer_id' => (string) ($data['customer'] ?? ''),
            'subscription_item_id' => (string) ($data['items']['data'][0]['id'] ?? ''),
            'subscription_item_quantity' => (int) ($data['items']['data'][0]['quantity'] ?? 1),
            'current_price_id' => (string) ($data['items']['data'][0]['price']['id'] ?? ''),
            'current_period_start' => (int) ($data['items']['data'][0]['current_period_start'] ?? 0),
            'current_period_end' => (int) ($data['items']['data'][0]['current_period_end'] ?? 0),
            'cancel_at' => (int) ($data['cancel_at'] ?? 0),
            'cancel_at_period_end' => !empty($data['cancel_at_period_end']),
            'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            'raw' => $data,
        ];
    }

    public static function extractUpcomingInvoicePreview(array $response): array
    {
        $data = $response['data'] ?? [];
        $amountDue = (int) ($data['amount_due'] ?? 0);
        $total = (int) ($data['total'] ?? 0);
        $subtotal = (int) ($data['subtotal'] ?? 0);
        $currency = strtoupper((string) ($data['currency'] ?? 'MXN'));
        $prorationAmount = 0;

        foreach (($data['lines']['data'] ?? []) as $line) {
            if (!empty($line['proration'])) {
                $prorationAmount += (int) ($line['amount'] ?? 0);
            }
        }

        return [
            'amount_due' => round($amountDue / 100, 2),
            'subtotal' => round($subtotal / 100, 2),
            'total' => round($total / 100, 2),
            'proration_total' => round($prorationAmount / 100, 2),
            'currency' => $currency,
            'raw' => $data,
        ];
    }

    public static function verifyWebhookSignature(string $rawBody, ?string $signatureHeader = null): bool
    {
        $secret = trim((string) env('STRIPE_WEBHOOK_SECRET', ''));
        $signatureHeader = trim((string) $signatureHeader);

        if ($secret === '' || $signatureHeader === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = ctype_digit((string) $value) ? (int) $value : null;
            } elseif ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || empty($signatures)) {
            return false;
        }

        $tolerance = max(0, (int) env('STRIPE_WEBHOOK_TOLERANCE', 300));
        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public static function isProvisionedStatus(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), [
            'active',
            'trialing',
        ], true);
    }

    private static function secretKey(): ?string
    {
        $key = trim((string) (env('STRIPE_SECRET_KEY', '') ?: env('STRIPE_API_KEY', '')));
        return $key !== '' ? $key : null;
    }

    private static function apiRequest(string $method, string $path, string $secretKey, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL no está disponible en el servidor.'];
        }

        $baseUrl = rtrim((string) env('STRIPE_API_BASE', 'https://api.stripe.com'), '/');
        $url = $baseUrl . '/' . ltrim($path, '/');

        if (strtoupper($method) === 'GET' && !empty($payload)) {
            $url .= '?' . self::buildFormBody($payload);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        if (strtoupper($method) !== 'GET' && $payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, self::buildFormBody($payload));
        }

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'message' => 'Error al conectar con Stripe: ' . ($curlError ?: 'sin respuesta')];
        }

        $decoded = json_decode((string) $raw, true);
        $data = is_array($decoded) ? $decoded : ['raw' => $raw];
        $success = $status >= 200 && $status < 300;

        if (!$success) {
            $message = (string) ($data['error']['message'] ?? $data['message'] ?? 'Error Stripe');
            return [
                'success' => false,
                'message' => 'Stripe respondió HTTP ' . $status . ': ' . $message,
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

    private static function buildFormBody(array $payload): string
    {
        return http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    }

    private static function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
