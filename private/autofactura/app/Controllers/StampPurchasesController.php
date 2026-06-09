<?php
/**
 * Controller: StampPurchasesController
 */

class StampPurchasesController
{
    private const IVA_RATE = 0.16;
    private const LOW_STOCK_THRESHOLD = 1000;
    private const LOW_STOCK_ALERT_COOLDOWN = 86400;
    private const DEFAULT_EF_TRANSFER_URL = 'https://efectosfiscales.mx/assign/api_transferir_timbres.php';
    private const PACKAGES = [
        'pkg_test' => ['name' => 'Paquete de prueba (1 factura)', 'credits' => 1, 'subtotal' => 0.86],
        'pkg_20' => ['name' => 'Paquete de 20 timbres', 'credits' => 20, 'subtotal' => 100.00],
        'pkg_50' => ['name' => 'Paquete de 50 timbres', 'credits' => 50, 'subtotal' => 225.00],
        'pkg_150' => ['name' => 'Paquete de 150 timbres', 'credits' => 150, 'subtotal' => 600.00],
        'pkg_500' => ['name' => 'Paquete de 500 timbres', 'credits' => 500, 'subtotal' => 1750.00],
    ];

    public function __construct()
    {
        $action = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $isPublicWebhook = str_contains($path, '/webhooks/clip');

        if (!$isPublicWebhook) {
            AuthMiddleware::check();
        }
    }

    public function index(): void
    {
        $businessId = (int) auth_business_id();
        $superuser = Business::findActiveSuperuser();
        $transferTargets = [];
        $transferSearch = trim((string) ($_GET['transfer_search'] ?? ''));
        $creditSnapshot = $this->resolveDisplayedCredits($businessId);

        $packages = [];
        foreach (self::PACKAGES as $key => $package) {
            $packages[] = ['key' => $key] + $this->packageAmounts($package);
        }

        if (is_superuser()) {
            $transferTargets = Business::searchTransferTargets($transferSearch, 25);
        }

        view('dashboard.stamp-purchases', [
            'currentCredits' => $creditSnapshot['credits'],
            'currentCreditsSource' => $creditSnapshot['source'],
            'providerCredits' => $superuser ? Business::getStampCredits((int) $superuser['id']) : 0,
            'isSuperuser' => is_superuser(),
            'superuser' => $superuser,
            'transferTargets' => $transferTargets,
            'transferSearch' => $transferSearch,
            'inventoryLogs' => is_superuser()
                ? AutofacturaLog::getRecentByBusinessAndActions($businessId, [
                    'superuser_stamp_topup',
                    'user_stamp_transfer',
                    'stamp_purchase_paid',
                ], 20)
                : [],
            'packages' => $packages,
            'purchases' => StampPurchase::getByBusiness($businessId, 20),
            'allCheckoutOrders' => is_superuser() ? StampPurchase::getAllCheckoutOrders(100) : [],
            'clipEnabled' => $this->isClipConfigured(),
        ]);
    }

    private function resolveDisplayedCredits(int $businessId): array
    {
        $localCredits = Business::getStampCredits($businessId);
        $settings = BusinessSetting::getRuntimeByBusiness($businessId) ?? [];

        $apiUrl = trim((string) ($settings['api_url'] ?? ''));
        $apiUser = trim((string) ($settings['api_user'] ?? ''));
        $apiPassword = trim((string) ($settings['api_password'] ?? ''));
        $apiKey = trim((string) ($settings['api_key'] ?? ''));

        if ($apiUrl === '' || (($apiUser === '' || $apiPassword === '') && $apiKey === '')) {
            return [
                'credits' => $localCredits,
                'source' => 'local',
            ];
        }

        $creditResult = EfectosFiscalesService::wsGetCredit([
            'api_url' => $apiUrl,
            'api_user' => $apiUser,
            'api_password' => $apiPassword,
            'api_key' => $apiKey,
        ]);

        $remoteCredits = $this->extractNumericCreditsFromWsGetCredit($creditResult);
        if ($remoteCredits !== null) {
            return [
                'credits' => $remoteCredits,
                'source' => 'ef',
            ];
        }

        return [
            'credits' => $localCredits,
            'source' => 'local',
        ];
    }

    private function extractNumericCreditsFromWsGetCredit(array $creditResult): ?int
    {
        $candidates = [];

        if (array_key_exists('credit', $creditResult)) {
            $candidates[] = $creditResult['credit'];
        }

        if (array_key_exists('message', $creditResult)) {
            $candidates[] = $creditResult['message'];
        }

        if (array_key_exists('raw', $creditResult)) {
            $candidates[] = $creditResult['raw'];
        }

        foreach ($candidates as $candidate) {
            $value = $this->normalizeNumericCreditCandidate($candidate);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeNumericCreditCandidate(mixed $candidate): ?int
    {
        if (is_int($candidate)) {
            return $candidate;
        }

        if (is_float($candidate)) {
            return (int) round($candidate);
        }

        if (is_string($candidate) || is_numeric($candidate)) {
            $text = trim((string) $candidate);
            $text = trim($text, " \t\n\r\0\x0B\"'");
            if ($text !== '' && preg_match('/^-?\d+$/', $text)) {
                return (int) $text;
            }

            if (preg_match('/"(-?\d+)"/', $text, $matches) === 1) {
                return (int) $matches[1];
            }

            return null;
        }

        if (is_array($candidate)) {
            foreach ($candidate as $value) {
                $normalized = $this->normalizeNumericCreditCandidate($value);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    public function createCheckout(): void
    {
        AuthMiddleware::verifyCsrf();

        $businessId = (int) auth_business_id();
        $packageKey = trim((string) ($_POST['package_key'] ?? ''));
        $package = self::PACKAGES[$packageKey] ?? null;
        if (!$package) {
            flash('error', 'Selecciona un paquete válido.');
            Router::redirect('/stamp-purchases');
        }

        if (is_superuser()) {
            flash('info', 'El superusuario se recarga manualmente. Usa la opción de agregar timbres.');
            Router::redirect('/stamp-purchases');
        }

        if (!$this->isClipConfigured()) {
            flash('error', 'Clip no está configurado todavía en el entorno.');
            Router::redirect('/stamp-purchases');
        }

        $purchaseId = StampPurchase::createPendingCheckout($businessId, [
            'package_name' => $package['name'],
            'credits' => $package['credits'],
            'amount' => $this->packageAmounts($package)['total'],
            'payment_method' => 'Clip',
            'status' => 'pending',
        ]);

        $successUrl = url('stamp-purchases/return?purchase=' . $purchaseId . '&result=success');
        $errorUrl = url('stamp-purchases/return?purchase=' . $purchaseId . '&result=error');
        $defaultUrl = url('stamp-purchases/return?purchase=' . $purchaseId . '&result=default');

        $clipResult = ClipService::createCheckoutLink(
            (float) $this->packageAmounts($package)['total'],
            $package['name'],
            $successUrl,
            $errorUrl,
            $defaultUrl
        );

        if (empty($clipResult['success'])) {
            StampPurchase::update($purchaseId, [
                'status' => 'failed',
                'notes' => (string) ($clipResult['message'] ?? 'No se pudo generar el link de pago.'),
            ]);
            flash('error', 'No se pudo generar el link de pago con Clip: ' . ($clipResult['message'] ?? 'error desconocido'));
            Router::redirect('/stamp-purchases');
        }

        $paymentLink = ClipService::extractPaymentLinkData($clipResult);
        StampPurchase::update($purchaseId, [
            'payment_request_id' => $paymentLink['payment_request_id'] ?: null,
            'payment_request_url' => $paymentLink['payment_request_url'] ?: null,
            'clip_status' => $paymentLink['clip_status'] ?: null,
        ]);

        AutofacturaLog::log(
            'stamp_checkout_created',
            null,
            $businessId,
            'Orden #' . $purchaseId . ' creada en Clip'
        );

        header('Location: ' . $paymentLink['payment_request_url']);
        exit;
    }

    public function handleReturn(): void
    {
        $purchaseId = (int) ($_GET['purchase'] ?? 0);
        $purchase = StampPurchase::find($purchaseId);
        $businessId = (int) auth_business_id();

        if (!$purchase || (int) ($purchase['business_id'] ?? 0) !== $businessId) {
            flash('error', 'La orden de compra no fue encontrada.');
            Router::redirect('/stamp-purchases');
        }

        $result = strtolower(trim((string) ($_GET['result'] ?? 'default')));

        if (($purchase['status'] ?? '') !== 'paid' && !empty($purchase['payment_request_id'])) {
            $statusResult = ClipService::getCheckoutStatus((string) $purchase['payment_request_id']);
            if (!empty($statusResult['success'])) {
                $statusData = ClipService::extractPaymentLinkData($statusResult);
                StampPurchase::update($purchaseId, [
                    'clip_status' => $statusData['clip_status'] ?: ($purchase['clip_status'] ?? null),
                ]);

                if (ClipService::isPaidStatus($statusData['clip_status'] ?? null)) {
                    try {
                        $this->transferPurchaseCreditsViaEf($purchase);
                        StampPurchase::markAsPaid($purchaseId, $statusData['clip_status'] ?? null, (string) ($purchase['payment_request_id'] ?? ''));
                        $this->syncBusinessStampCreditsFromEf((int) ($purchase['business_id'] ?? 0));
                        $this->ensureSuperuserInvoiceLink($purchaseId);
                        flash('success', 'Pago confirmado. Ya acreditamos tus timbres.');
                        Router::redirect('/stamp-purchases');
                    } catch (Throwable $e) {
                        app_log('No se pudo surtir la compra de timbres #' . $purchaseId . ': ' . $e->getMessage(), 'error');
                        $updatedPurchase = StampPurchase::find($purchaseId);
                        if (($updatedPurchase['status'] ?? '') === 'paid') {
                            flash('success', 'El pago ya fue confirmado y tus timbres se acreditaron correctamente.');
                        } else {
                            flash('error', 'El pago ya fue confirmado, pero no se pudieron acreditar tus timbres automáticamente. Contacta al administrador.');
                        }
                        Router::redirect('/stamp-purchases');
                    }
                }
            }
        }

        $purchase = StampPurchase::find($purchaseId) ?? $purchase;
        if (($purchase['status'] ?? '') === 'paid' && empty($purchase['invoice_link_sent_at'])) {
            $this->ensureSuperuserInvoiceLink($purchaseId);
            $purchase = StampPurchase::find($purchaseId) ?? $purchase;

            if (!empty($purchase['invoice_link_sent_at'])) {
                flash('success', 'Tu compra ya fue acreditada y te enviamos el link de facturación por correo.');
                Router::redirect('/stamp-purchases');
            }
        }

        if ($result === 'error') {
            flash('error', 'El pago no se completó. Puedes intentarlo nuevamente.');
        } else {
            flash('info', 'Aún estamos validando el pago con Clip. Si ya pagaste, actualiza en unos segundos.');
        }

        Router::redirect('/stamp-purchases');
    }

    public function transferCredits(): void
    {
        AuthMiddleware::requireSuperuser();
        AuthMiddleware::verifyCsrf();

        $fromBusinessId = (int) auth_business_id();
        $targetId = (int) ($_POST['target_id'] ?? 0);
        $credits = max(0, (int) ($_POST['credits'] ?? 0));

        $target = Business::find($targetId);
        $source = Business::find($fromBusinessId);

        if (!$source || !$target) {
            flash('error', 'No se encontró el usuario origen o destino.');
            Router::redirect('/stamp-purchases');
        }

        if ($targetId === $fromBusinessId) {
            flash('error', 'No puedes transferirte timbres a ti mismo desde esta opción.');
            Router::redirect('/stamp-purchases');
        }

        if (($target['role'] ?? 'user') !== 'user') {
            flash('error', 'Solo puedes transferir timbres a usuarios normales.');
            Router::redirect('/stamp-purchases');
        }

        if ((int) ($target['is_active'] ?? 0) !== 1) {
            flash('error', 'No puedes transferir timbres a un usuario inactivo.');
            Router::redirect('/stamp-purchases');
        }

        if ($credits <= 0) {
            flash('error', 'Indica una cantidad válida de timbres a transferir.');
            Router::redirect('/stamp-purchases');
        }

        try {
            $this->sendEfTransferToBusiness(
                $targetId,
                $credits,
                'manual-' . $fromBusinessId . '-' . $targetId . '-' . date('YmdHis'),
                'transferencia manual'
            );
            $this->syncBusinessStampCreditsFromEf($targetId);

            AutofacturaLog::log(
                'user_stamp_transfer',
                null,
                $fromBusinessId,
                'Transferidos ' . $credits . ' timbres al usuario #' . $targetId
            );

            flash('success', 'Se transfirieron ' . $credits . ' timbres a ' . $target['name'] . '.');
        } catch (Throwable $e) {
            flash('error', 'No se pudo completar la transferencia: ' . $e->getMessage());
        }

        Router::redirect('/stamp-purchases');
    }

    public function webhook(): void
    {
        $raw = file_get_contents('php://input');
        $raw = is_string($raw) ? $raw : '';
        $payload = json_decode($raw, true);

        $logEntry = [
            'received_at' => date('c'),
            'payload' => $payload,
        ];
        @file_put_contents($this->webhookLogPath(), json_encode($logEntry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

        if (!$this->isWebhookAuthorized($raw)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => 'Webhook no autorizado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $paymentRequestId = (string) (
            $payload['payment_request_id']
            ?? $payload['data']['payment_request_id']
            ?? $payload['resource']['payment_request_id']
            ?? ''
        );

        $clipStatus = (string) (
            $payload['status']
            ?? $payload['data']['status']
            ?? $payload['resource']['status']
            ?? ''
        );

        if ($paymentRequestId === '') {
            http_response_code(200);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => 'Webhook recibido sin payment_request_id.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $purchase = StampPurchase::findByPaymentRequestId($paymentRequestId);
        if (!$purchase) {
            http_response_code(200);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => 'Sin orden asociada.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        StampPurchase::update((int) $purchase['id'], [
            'clip_status' => $clipStatus !== '' ? $clipStatus : ($purchase['clip_status'] ?? null),
            'payment_reference' => $paymentRequestId,
        ]);

        if (($purchase['status'] ?? '') === 'paid') {
            http_response_code(200);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => 'Compra ya acreditada.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (ClipService::isPaidStatus($clipStatus)) {
            try {
                $this->transferPurchaseCreditsViaEf($purchase);
                StampPurchase::markAsPaid((int) $purchase['id'], $clipStatus, $paymentRequestId);
                $this->syncBusinessStampCreditsFromEf((int) ($purchase['business_id'] ?? 0));
                $this->ensureSuperuserInvoiceLink((int) $purchase['id']);
            } catch (Throwable $e) {
                app_log('Webhook Clip sin surtido de compra #' . (int) $purchase['id'] . ': ' . $e->getMessage(), 'error');
            }
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    private function isClipConfigured(): bool
    {
        $token = trim((string) env('CLIP_API_TOKEN', ''));
        $apiKey = trim((string) env('CLIP_API_KEY', ''));
        $apiSecret = trim((string) (env('CLIP_API_SECRET', '') ?: env('CLIP_SECRET_KEY', '')));

        return $token !== '' || ($apiKey !== '' && $apiSecret !== '');
    }

    private function isWebhookAuthorized(string $rawBody): bool
    {
        $expectedToken = trim((string) env('CLIP_WEBHOOK_TOKEN', ''));
        $providedToken = trim((string) ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ''));
        if ($expectedToken !== '' && $providedToken !== '' && hash_equals($expectedToken, $providedToken)) {
            return true;
        }

        $secret = trim((string) env('CLIP_WEBHOOK_SECRET', ''));
        if ($secret !== '') {
            $headerName = trim((string) env('CLIP_WEBHOOK_SIGNATURE_HEADER', 'X-Clip-Signature'));
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
            $signature = trim((string) ($_SERVER[$serverKey] ?? ''));
            if ($signature !== '') {
                $expectedHex = hash_hmac('sha256', $rawBody, $secret);
                $expectedPrefixed = 'sha256=' . $expectedHex;
                return hash_equals($expectedHex, $signature) || hash_equals($expectedPrefixed, $signature);
            }
        }

        return false;
    }

    private function webhookLogPath(): string
    {
        $configured = trim((string) env('CLIP_WEBHOOK_LOG', ''));
        if ($configured !== '') {
            return $configured;
        }

        return STORAGE_PATH . '/logs/clip_webhook.log';
    }

    private function packageAmounts(array $package): array
    {
        $subtotal = round((float) ($package['subtotal'] ?? 0), 2);
        $iva = round($subtotal * self::IVA_RATE, 2);
        $total = round($subtotal + $iva, 2);

        return $package + [
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
        ];
    }

    private function ensureSuperuserInvoiceLink(int $purchaseId): void
    {
        $purchase = StampPurchase::find($purchaseId);
        if (!$purchase) {
            return;
        }

        $buyer = Business::find((int) $purchase['business_id']);
        $superuser = Business::findActiveSuperuser();

        if (!$buyer || !$superuser) {
            return;
        }

        $superuserConcept = InvoiceConcept::getDefault((int) $superuser['id']) ?: InvoiceConcept::getFirstActive((int) $superuser['id']);
        if (!$superuserConcept) {
            AutofacturaLog::log('stamp_invoice_link_error', null, (int) $purchase['business_id'], 'No hay concepto activo/default en superuser para facturar compra de timbres.');
            return;
        }

        $settings = BusinessSetting::getByBusiness((int) $superuser['id']) ?? [];
        $expirationDays = (int) ($settings['link_expiration_days'] ?? 3);
        if ($expirationDays < 1 || $expirationDays > 30) {
            $expirationDays = 3;
        }

        $invoiceRequest = null;
        $requestId = StampPurchase::hasColumn('invoice_request_id')
            ? (int) ($purchase['invoice_request_id'] ?? 0)
            : 0;
        if ($requestId > 0) {
            $invoiceRequest = AutofacturaRequest::find($requestId);
        }

        $package = $this->packageAmounts($this->packageDefinitionByCredits((int) $purchase['credits']) ?? [
            'name' => (string) ($purchase['package_name'] ?? 'Compra de timbres'),
            'credits' => (int) ($purchase['credits'] ?? 0),
            'subtotal' => round(((float) ($purchase['amount'] ?? 0)) / (1 + self::IVA_RATE), 2),
        ]);

        $commercialName = trim((string) ($settings['commercial_name'] ?? ''));
        $businessDisplayName = $commercialName !== '' ? $commercialName : (string) ($superuser['name'] ?? 'AutoFactura');
        $businessLogoUrl = !empty($settings['logo']) ? url('storage/uploads/logos/' . $settings['logo']) : null;

        if (!$invoiceRequest) {
            $requestPayload = [
                'business_id' => (int) $superuser['id'],
                'concept_id' => (int) $superuserConcept['id'],
                'phone' => trim((string) ($buyer['phone'] ?? '')) ?: null,
                'email' => trim((string) ($buyer['email'] ?? '')) ?: null,
                'amount' => (float) $package['subtotal'],
                'expires_at' => (new DateTime())->modify('+' . $expirationDays . ' days')->format('Y-m-d H:i:s'),
            ];

            if (AutofacturaRequest::supportsCustomConceptText()) {
                $requestPayload['custom_concept_text'] = (string) ($purchase['package_name'] ?? 'Compra de timbres');
            }

            if (AutofacturaRequest::supportsWhatsappSentFlag()) {
                $requestPayload['whatsapp_sent'] = 0;
            }

            $requestId = AutofacturaRequest::createWithToken($requestPayload);
            $invoiceRequest = AutofacturaRequest::find($requestId);
            if (!$invoiceRequest) {
                return;
            }

            $purchaseUpdate = [];
            if (StampPurchase::hasColumn('invoice_request_id')) {
                $purchaseUpdate['invoice_request_id'] = $requestId;
            }
            if (!empty($purchaseUpdate)) {
                StampPurchase::update($purchaseId, $purchaseUpdate);
            }

            // En cuanto existe la solicitud del superadmin, intentar enviar el correo.
            $this->sendSuperuserInvoiceLinkNotifications(
                $purchaseId,
                $purchase,
                $buyer,
                $superuser,
                $invoiceRequest,
                $businessDisplayName,
                $businessLogoUrl,
                $package
            );
            return;
        }

        $this->sendSuperuserInvoiceLinkNotifications(
            $purchaseId,
            $purchase,
            $buyer,
            $superuser,
            $invoiceRequest,
            $businessDisplayName,
            $businessLogoUrl,
            $package
        );
    }

    private function sendSuperuserInvoiceLinkNotifications(
        int $purchaseId,
        array $purchase,
        array $buyer,
        array $superuser,
        array $invoiceRequest,
        string $businessDisplayName,
        ?string $businessLogoUrl,
        array $package
    ): array {
        $requestId = (int) ($invoiceRequest['id'] ?? 0);
        $buyerEmail = trim((string) ($invoiceRequest['email'] ?? ($buyer['email'] ?? '')));
        $buyerPhone = trim((string) ($invoiceRequest['phone'] ?? ($buyer['phone'] ?? '')));
        if ($requestId <= 0) {
            return ['sent' => [], 'failed' => ['No existe una solicitud válida para notificar.']];
        }

        $publicLink = url('f/' . $invoiceRequest['token']);
        $sentSomething = false;
        $sentChannels = [];
        $failedChannels = [];

        app_log(
            'Compra de timbres #' . $purchaseId . ': iniciando notificación de link de facturación. Email='
            . ($buyerEmail !== '' ? $buyerEmail : 'N/A')
            . ' Tel=' . ($buyerPhone !== '' ? $buyerPhone : 'N/A'),
            'info'
        );

        if ($buyerEmail !== '') {
            $mailResult = MailgunService::sendInvoiceLink(
                $buyerEmail,
                $businessDisplayName,
                $publicLink,
                (float) $package['subtotal'],
                (string) ($purchase['package_name'] ?? 'Compra de timbres'),
                (string) ($invoiceRequest['expires_at'] ?? ''),
                $businessLogoUrl
            );

            if (!empty($mailResult['success'])) {
                AutofacturaLog::log('stamp_invoice_link_sent', $requestId, (int) $superuser['id'], 'Link enviado por correo para compra de timbres a: ' . $buyerEmail);
                app_log('Compra de timbres #' . $purchaseId . ': link de facturación enviado por correo a ' . $buyerEmail, 'info');
                $sentSomething = true;
                $sentChannels[] = 'correo';
            } else {
                $message = (string) ($mailResult['message'] ?? 'No se pudo enviar link de factura por correo.');
                AutofacturaLog::log('stamp_invoice_link_error', $requestId, (int) $superuser['id'], 'Correo: ' . $message);
                app_log('Compra de timbres #' . $purchaseId . ': error al enviar correo de facturación a ' . $buyerEmail . '. ' . $message, 'error');
                $failedChannels[] = 'correo: ' . $message;
            }
        }

        if ($buyerPhone !== '') {
            $whatsappResult = MensajesXyzService::sendInvoiceLinkTemplate(
                $buyerPhone,
                (string) ($buyer['name'] ?? 'Cliente'),
                $businessDisplayName,
                (string) ($purchase['package_name'] ?? 'Compra de timbres'),
                '$' . number_format((float) $package['subtotal'], 2, '.', ',') . ' MXN',
                !empty($invoiceRequest['expires_at']) ? date('d/m/Y H:i', strtotime((string) $invoiceRequest['expires_at'])) : 'No definida',
                $publicLink,
                'STAMP-' . $purchaseId
            );

            if (!empty($whatsappResult['success'])) {
                if (AutofacturaRequest::supportsWhatsappSentFlag()) {
                    AutofacturaRequest::markWhatsappSent($requestId);
                }
                AutofacturaLog::log('stamp_invoice_link_whatsapp_sent', $requestId, (int) $superuser['id'], 'Link enviado por WhatsApp para compra de timbres a: ' . $buyerPhone);
                app_log('Compra de timbres #' . $purchaseId . ': link de facturación enviado por WhatsApp a ' . $buyerPhone, 'info');
                $sentSomething = true;
                $sentChannels[] = 'WhatsApp';
            } else {
                $message = (string) ($whatsappResult['message'] ?? 'No se pudo enviar link de factura por WhatsApp.');
                AutofacturaLog::log('stamp_invoice_link_whatsapp_error', $requestId, (int) $superuser['id'], 'WhatsApp: ' . $message);
                app_log('Compra de timbres #' . $purchaseId . ': error al enviar WhatsApp de facturación a ' . $buyerPhone . '. ' . $message, 'error');
                $failedChannels[] = 'WhatsApp: ' . $message;
            }
        }

        if ($buyerEmail === '' && $buyerPhone === '') {
            $message = 'La compra no tiene correo ni teléfono configurados para notificar el link de facturación.';
            AutofacturaLog::log('stamp_invoice_link_error', $requestId, (int) $superuser['id'], $message);
            app_log('Compra de timbres #' . $purchaseId . ': ' . $message, 'error');
            $failedChannels[] = $message;
        }

        if ($sentSomething) {
            $purchaseUpdate = [];
            if (StampPurchase::hasColumn('invoice_request_id')) {
                $purchaseUpdate['invoice_request_id'] = $requestId;
            }
            if (StampPurchase::hasColumn('invoice_link_sent_at')) {
                $purchaseUpdate['invoice_link_sent_at'] = date('Y-m-d H:i:s');
            }
            if (!empty($purchaseUpdate)) {
                StampPurchase::update($purchaseId, $purchaseUpdate);
            }
        }

        return [
            'sent' => $sentChannels,
            'failed' => $failedChannels,
        ];
    }

    private function packageDefinitionByCredits(int $credits): ?array
    {
        foreach (self::PACKAGES as $package) {
            if ((int) $package['credits'] === $credits) {
                return $package;
            }
        }

        return null;
    }

    public function addSelfCredits(): void
    {
        AuthMiddleware::requireSuperuser();
        AuthMiddleware::verifyCsrf();

        $credits = max(0, (int) ($_POST['credits'] ?? 0));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($credits <= 0) {
            flash('error', 'Indica una cantidad válida de timbres para agregar.');
            Router::redirect('/stamp-purchases');
        }

        $businessId = (int) auth_business_id();
        StampPurchase::registerPaidPurchase($businessId, [
            'package_name' => 'Recarga manual de superadmin',
            'credits' => $credits,
            'amount' => 0,
            'payment_method' => 'Manual',
            'payment_reference' => 'MANUAL-' . date('YmdHis'),
            'notes' => $notes !== '' ? $notes : 'Recarga manual realizada por superadmin.',
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        AutofacturaLog::log('superuser_stamp_topup', null, $businessId, 'Recarga manual: +' . $credits . ' timbres');
        flash('success', 'Se agregaron ' . $credits . ' timbres al saldo del superusuario.');
        Router::redirect('/stamp-purchases');
    }

    public function executeTransfer(): void
    {
        AuthMiddleware::requireSuperuser();
        AuthMiddleware::verifyCsrf();

        $purchaseId = max(0, (int) ($_POST['purchase_id'] ?? 0));
        $purchase = StampPurchase::find($purchaseId);

        if (!$purchase) {
            flash('error', 'La transacción indicada no existe.');
            Router::redirect('/stamp-purchases');
        }

        if (($purchase['status'] ?? '') === 'paid') {
            flash('info', 'Esa compra ya fue surtida anteriormente.');
            Router::redirect('/stamp-purchases');
        }

        if (!ClipService::isPaidStatus((string) ($purchase['clip_status'] ?? ''))) {
            flash('error', 'Esta transacción aún no aparece como pagada en Clip.');
            Router::redirect('/stamp-purchases');
        }

        try {
            $this->transferPurchaseCreditsViaEf($purchase);
            StampPurchase::markAsPaid(
                $purchaseId,
                (string) ($purchase['clip_status'] ?? ''),
                (string) ($purchase['payment_request_id'] ?? '')
            );
            $this->syncBusinessStampCreditsFromEf((int) ($purchase['business_id'] ?? 0));
            $this->ensureSuperuserInvoiceLink($purchaseId);

            AutofacturaLog::log(
                'stamp_purchase_manual_transfer',
                null,
                (int) auth_business_id(),
                'Transferencia manual ejecutada para compra #' . $purchaseId
            );

            flash('success', 'La transferencia de timbres se ejecutó correctamente.');
        } catch (Throwable $e) {
            app_log('No se pudo ejecutar la transferencia manual de la compra #' . $purchaseId . ': ' . $e->getMessage(), 'error');
            flash('error', 'No se pudo ejecutar la transferencia: ' . $e->getMessage());
        }

        Router::redirect('/stamp-purchases');
    }

    private function transferPurchaseCreditsViaEf(array $purchase): void
    {
        $purchaseId = (int) ($purchase['id'] ?? 0);
        $businessId = (int) ($purchase['business_id'] ?? 0);
        $credits = (int) ($purchase['credits'] ?? 0);

        if ($purchaseId <= 0 || $businessId <= 0 || $credits <= 0) {
            throw new RuntimeException('La compra no tiene datos válidos para transferir timbres.');
        }

        $this->sendEfTransferToBusiness(
            $businessId,
            $credits,
            'orden-' . $purchaseId,
            'compra #' . $purchaseId
        );
    }

    private function sendEfTransferToBusiness(int $businessId, int $credits, string $reference, string $contextLabel): void
    {
        if ($businessId <= 0 || $credits <= 0) {
            throw new RuntimeException('La transferencia no tiene datos válidos para acreditarse.');
        }

        $settings = BusinessSetting::getRuntimeByBusiness($businessId) ?? [];
        $apiUser = trim((string) ($settings['api_user'] ?? ''));
        $apiPassword = trim((string) ($settings['api_password'] ?? ''));

        if ($apiUser === '' || $apiPassword === '') {
            throw new RuntimeException('El negocio comprador todavía no tiene lista su cuenta de timbrado para recibir timbres.');
        }

        $transferUrl = trim((string) env('EF_TRANSFER_URL', self::DEFAULT_EF_TRANSFER_URL));
        $transferBearer = trim((string) env('EF_TRANSFER_BEARER', ''));
        if ($transferBearer === '') {
            $transferBearer = trim((string) env('EF_ASSIGN_BEARER', ''));
        }

        if ($transferUrl === '' || $transferBearer === '') {
            throw new RuntimeException('Falta configurar EF_TRANSFER_URL o EF_TRANSFER_BEARER en el entorno.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL no está disponible en el servidor para transferir timbres.');
        }

        $payload = [
            'transferencia' => [
                'usuario' => $apiUser,
                'contrasena' => $apiPassword,
                'cantidad' => $credits,
                'referencia' => $reference,
            ],
        ];

        AutofacturaLog::log(
            'stamp_transfer_attempt',
            null,
            $businessId,
            'Intento de transferencia EF para ' . $contextLabel . ' por ' . $credits . ' timbres.'
        );

        $ch = curl_init($transferUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $transferBearer,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new RuntimeException('No se pudo contactar el servicio de transferencia de timbres: ' . $curlError);
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!$this->isEfTransferResponseSuccessful($httpCode, $decoded, (string) $rawResponse)) {
            $message = $this->efTransferErrorMessage($httpCode, $decoded, (string) $rawResponse);
            AutofacturaLog::log(
                'stamp_transfer_error',
                null,
                $businessId,
                'Transferencia EF fallida para ' . $contextLabel . ': ' . $message
            );
            throw new RuntimeException($message);
        }

        AutofacturaLog::log(
            'stamp_transfer_ok',
            null,
            $businessId,
            'Transferencia EF confirmada para ' . $contextLabel . ' con referencia ' . $reference . '.'
        );
    }

    private function isEfTransferResponseSuccessful(int $httpCode, ?array $decoded, string $rawResponse): bool
    {
        if ($httpCode < 200 || $httpCode >= 300) {
            return false;
        }

        if (is_array($decoded)) {
            $flags = [
                $decoded['success'] ?? null,
                $decoded['ok'] ?? null,
                $decoded['resultado'] ?? null,
                $decoded['status'] ?? null,
                $decoded['estado'] ?? null,
            ];

            foreach ($flags as $flag) {
                if ($flag === true || $flag === 1 || $flag === '1') {
                    return true;
                }

                if (is_string($flag) && in_array(strtolower(trim($flag)), ['ok', 'success', 'successful', 'completado', 'completada'], true)) {
                    return true;
                }
            }
        }

        $normalized = strtolower(trim($rawResponse));
        if ($normalized === '') {
            return false;
        }

        if (
            str_contains($normalized, 'ya fue transferid')
            || str_contains($normalized, 'ya se transfiri')
            || str_contains($normalized, 'ya existe')
            || str_contains($normalized, 'duplicad')
            || str_contains($normalized, 'procesad')
        ) {
            return true;
        }

        return !str_contains($normalized, 'error') && !str_contains($normalized, 'fail');
    }

    private function efTransferErrorMessage(int $httpCode, ?array $decoded, string $rawResponse): string
    {
        if (is_array($decoded)) {
            foreach (['message', 'mensaje', 'error', 'detalle', 'detail'] as $key) {
                $value = trim((string) ($decoded[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $body = trim($rawResponse);
        if ($body !== '') {
            return 'Respuesta inválida del servicio de transferencia (HTTP ' . $httpCode . '): ' . mb_substr($body, 0, 220);
        }

        return 'El servicio de transferencia no confirmó la acreditación de timbres (HTTP ' . $httpCode . ').';
    }

    private function syncBusinessStampCreditsFromEf(int $businessId): void
    {
        if ($businessId <= 0) {
            return;
        }

        $settings = BusinessSetting::getRuntimeByBusiness($businessId) ?? [];
        $apiUrl = trim((string) ($settings['api_url'] ?? ''));
        $apiUser = trim((string) ($settings['api_user'] ?? ''));
        $apiPassword = trim((string) ($settings['api_password'] ?? ''));
        $apiKey = trim((string) ($settings['api_key'] ?? ''));

        if ($apiUrl === '' || (($apiUser === '' || $apiPassword === '') && $apiKey === '')) {
            return;
        }

        $creditResult = EfectosFiscalesService::wsGetCredit([
            'api_url' => $apiUrl,
            'api_user' => $apiUser,
            'api_password' => $apiPassword,
            'api_key' => $apiKey,
        ]);

        $remoteCredits = $this->extractNumericCreditsFromWsGetCredit($creditResult);
        if ($remoteCredits === null) {
            return;
        }

        Business::setStampCredits($businessId, $remoteCredits);
    }

    public function resendInvoiceLink(): void
    {
        AuthMiddleware::verifyCsrf();

        $purchaseId = max(0, (int) ($_POST['purchase_id'] ?? 0));
        $purchase = StampPurchase::find($purchaseId);
        $businessId = (int) auth_business_id();

        if (!$purchase) {
            flash('error', 'La compra indicada no existe.');
            Router::redirect('/stamp-purchases');
        }

        $isOwner = (int) ($purchase['business_id'] ?? 0) === $businessId;
        if (!$isOwner && !is_superuser()) {
            flash('error', 'No tienes permisos para reenviar ese link.');
            Router::redirect('/stamp-purchases');
        }

        if (($purchase['status'] ?? '') !== 'paid') {
            flash('error', 'La compra todavía no está pagada.');
            Router::redirect('/stamp-purchases');
        }

        $before = StampPurchase::find($purchaseId);
        $beforeSentAt = (string) ($before['invoice_link_sent_at'] ?? '');

        $this->ensureSuperuserInvoiceLink($purchaseId);

        $after = StampPurchase::find($purchaseId);
        $requestId = (int) ($after['invoice_request_id'] ?? 0);
        $afterSentAt = (string) ($after['invoice_link_sent_at'] ?? '');

        if ($requestId > 0 && $afterSentAt !== '' && $afterSentAt !== $beforeSentAt) {
            flash('success', 'Te reenviamos el link de facturación al correo registrado.');
        } elseif ($requestId > 0 && $afterSentAt !== '') {
            flash('info', 'El link de facturación ya estaba generado. Si el correo no llega, puedes abrirlo directamente desde el botón Facturar.');
        } else {
            flash('error', 'No se pudo reenviar el link de facturación. Revisa la configuración de correo.');
        }

        Router::redirect('/stamp-purchases');
    }

    private function sourceSuperuserIdForPurchase(array $purchase): ?int
    {
        $superuser = Business::findActiveSuperuser();
        if (!$superuser) {
            return null;
        }

        $superuserId = (int) $superuser['id'];
        $buyerId = (int) ($purchase['business_id'] ?? 0);

        return $superuserId > 0 && $superuserId !== $buyerId ? $superuserId : null;
    }

    private function notifySuperuserLowStockIfNeeded(int $superuserId): void
    {
        $currentCredits = Business::getStampCredits($superuserId);
        if ($currentCredits >= self::LOW_STOCK_THRESHOLD) {
            return;
        }

        if (AutofacturaLog::hasRecentActionForBusiness($superuserId, 'superadmin_low_stamp_alert', self::LOW_STOCK_ALERT_COOLDOWN)) {
            return;
        }

        $superuser = Business::find($superuserId);
        if (!$superuser || empty($superuser['email'])) {
            return;
        }

        $mailResult = MailgunService::sendLowStampAlert(
            (string) $superuser['email'],
            (string) ($superuser['name'] ?? 'Superadmin'),
            $currentCredits,
            self::LOW_STOCK_THRESHOLD
        );

        if (!empty($mailResult['success'])) {
            AutofacturaLog::log(
                'superadmin_low_stamp_alert',
                null,
                $superuserId,
                'Alerta de saldo bajo enviada. Saldo actual: ' . $currentCredits . ' timbres.'
            );
            return;
        }

        app_log('No se pudo enviar alerta de saldo bajo al superadmin: ' . ($mailResult['message'] ?? 'error desconocido'), 'error');
    }
}
