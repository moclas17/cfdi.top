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
        'pkg_test' => ['name' => 'Prueba', 'credits' => 1, 'subtotal' => 0.86, 'extra_price' => 1.00],
        'starter' => ['name' => 'Starter', 'credits' => 30, 'subtotal' => 128.45, 'extra_price' => 5.50],
        'crecimiento' => ['name' => 'Crecimiento', 'credits' => 100, 'subtotal' => 343.97, 'extra_price' => 5.00],
        'negocio' => ['name' => 'Negocio', 'credits' => 300, 'subtotal' => 861.21, 'extra_price' => 4.50],
    ];

    public function __construct()
    {
        $action = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $isPublicWebhook = str_contains($path, '/webhooks/stripe') || str_contains($path, '/webhooks/clip');

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
        $activeSubscription = $this->resolveActiveSubscriptionSummary($businessId);
        $planChangePreview = $this->resolvePlanChangePreview($businessId, $activeSubscription);

        $packages = [];
        foreach (self::PACKAGES as $key => $package) {
            $packages[] = ['key' => $key, 'stripe_price_id' => $this->stripePriceIdForPackage($key)] + $this->packageAmounts($package);
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
            'stripeEnabled' => $this->isStripeConfigured(),
            'activeSubscription' => $activeSubscription,
            'planChangePreview' => $planChangePreview,
        ]);
    }

    private function resolveActiveSubscriptionSummary(int $businessId): ?array
    {
        if ($businessId <= 0 || is_superuser()) {
            return null;
        }

        $purchase = StampPurchase::findLatestActiveStripeSubscriptionByBusiness($businessId);
        if (!$purchase) {
            return null;
        }

        $subscriptionId = trim((string) ($purchase['payment_request_id'] ?? ''));
        if ($subscriptionId === '') {
            return null;
        }

        $result = StripeService::getSubscription($subscriptionId);
        if (empty($result['success'])) {
            return [
                'package_name' => (string) ($purchase['package_name'] ?? 'Suscripción'),
                'status' => (string) ($purchase['clip_status'] ?? 'desconocido'),
                'renews_at' => null,
                'cancel_at' => null,
                'cancel_at_period_end' => false,
                'source' => 'local',
            ];
        }

        $subscription = StripeService::extractSubscriptionData($result);
        $liveStatus = (string) ($subscription['subscription_status'] ?? ($purchase['clip_status'] ?? 'desconocido'));
        if (!$this->isDisplayableSubscriptionStatus($liveStatus)) {
            return null;
        }

        return [
            'package_name' => (string) ($purchase['package_name'] ?? 'Suscripción'),
            'status' => $liveStatus,
            'subscription_id' => $subscriptionId,
            'subscription_item_id' => (string) ($subscription['subscription_item_id'] ?? ''),
            'current_price_id' => (string) ($subscription['current_price_id'] ?? ''),
            'current_package_key' => $this->packageKeyByStripePriceId((string) ($subscription['current_price_id'] ?? '')),
            'credits' => (int) ($purchase['credits'] ?? 0),
            'renews_at' => $this->formatStripeTimestamp((int) ($subscription['current_period_end'] ?? 0)),
            'period_started_at' => $this->formatStripeTimestamp((int) ($subscription['current_period_start'] ?? 0)),
            'cancel_at' => $this->formatStripeTimestamp((int) ($subscription['cancel_at'] ?? 0)),
            'cancel_at_period_end' => !empty($subscription['cancel_at_period_end']),
            'source' => 'stripe',
        ];
    }

    private function resolvePlanChangePreview(int $businessId, ?array $activeSubscription): ?array
    {
        if ($businessId <= 0 || is_superuser() || !$activeSubscription) {
            return null;
        }

        $targetPackageKey = trim((string) ($_GET['change_plan'] ?? ''));
        if ($targetPackageKey === '') {
            return null;
        }

        $package = self::PACKAGES[$targetPackageKey] ?? null;
        if (!$package) {
            return null;
        }

        $targetPriceId = $this->stripePriceIdForPackage($targetPackageKey);
        $subscriptionId = trim((string) ($activeSubscription['subscription_id'] ?? ''));
        if ($targetPriceId === null || $subscriptionId === '') {
            return null;
        }

        $currentPackageKey = (string) ($activeSubscription['current_package_key'] ?? '');
        if ($currentPackageKey !== '' && $currentPackageKey === $targetPackageKey) {
            return [
                'target_package' => ['key' => $targetPackageKey] + $this->packageAmounts($package),
                'current_package_name' => (string) ($activeSubscription['package_name'] ?? 'Plan actual'),
                'same_plan' => true,
            ];
        }

        $currentCredits = max(0, (int) ($activeSubscription['credits'] ?? 0));
        $targetCredits = max(0, (int) ($package['credits'] ?? 0));
        if ($targetCredits <= $currentCredits) {
            return [
                'target_package' => ['key' => $targetPackageKey] + $this->packageAmounts($package),
                'current_package_name' => (string) ($activeSubscription['package_name'] ?? 'Plan actual'),
                'error' => 'Por ahora solo está habilitado subir a un plan mayor desde una suscripción activa.',
            ];
        }

        if (!$this->isActiveStripeSubscriptionStatus((string) ($activeSubscription['status'] ?? ''))) {
            return null;
        }

        $prorationDate = time();
        $previewResult = StripeService::previewSubscriptionPriceChange($subscriptionId, $targetPriceId, $prorationDate);
        if (empty($previewResult['success'])) {
            return [
                'target_package' => ['key' => $targetPackageKey] + $this->packageAmounts($package),
                'current_package_name' => (string) ($activeSubscription['package_name'] ?? 'Plan actual'),
                'error' => (string) ($previewResult['message'] ?? 'No se pudo calcular el prorrateo en Stripe.'),
            ];
        }

        $preview = StripeService::extractUpcomingInvoicePreview($previewResult);
        return [
            'target_package' => ['key' => $targetPackageKey] + $this->packageAmounts($package),
            'current_package_name' => (string) ($activeSubscription['package_name'] ?? 'Plan actual'),
            'same_plan' => false,
            'amount_due_now' => (float) ($preview['amount_due'] ?? 0),
            'proration_total' => (float) ($preview['proration_total'] ?? 0),
            'currency' => (string) ($preview['currency'] ?? 'MXN'),
            'next_total' => (float) (($this->packageAmounts($package))['total'] ?? 0),
            'additional_credits_now' => max(0, $targetCredits - $currentCredits),
            'proration_date' => $prorationDate,
        ];
    }

    private function formatStripeTimestamp(int $timestamp): ?string
    {
        if ($timestamp <= 0) {
            return null;
        }

        $dt = new DateTime('@' . $timestamp);
        $dt->setTimezone(new DateTimeZone(env('APP_TIMEZONE', 'America/Mexico_City')));
        return $dt->format('d/m/Y H:i');
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

        if (!$this->isStripeConfigured()) {
            flash('error', 'Stripe no está configurado todavía en el entorno.');
            Router::redirect('/stamp-purchases');
        }

        $stripePriceId = $this->stripePriceIdForPackage($packageKey);
        if ($stripePriceId === null) {
            flash('error', 'Esta suscripción todavía no tiene un Price ID de Stripe configurado.');
            Router::redirect('/stamp-purchases');
        }

        $activeSubscription = $this->resolveActiveSubscriptionSummary($businessId);
        if ($activeSubscription && $this->isActiveStripeSubscriptionStatus((string) ($activeSubscription['status'] ?? ''))) {
            $currentPackageKey = trim((string) ($activeSubscription['current_package_key'] ?? ''));
            if ($currentPackageKey !== '' && $currentPackageKey === $packageKey) {
                flash('info', 'Ya tienes activo ese mismo plan.');
                Router::redirect('/stamp-purchases');
            }

            $confirmedUpgrade = (int) ($_POST['confirm_upgrade'] ?? 0) === 1;
            if (!$confirmedUpgrade) {
                Router::redirect('/stamp-purchases?change_plan=' . urlencode($packageKey));
            }

            $this->changeActiveSubscriptionPlan($businessId, $activeSubscription, $packageKey, $package, $stripePriceId);
            return;
        }

        $purchaseId = StampPurchase::createPendingCheckout($businessId, [
            'package_name' => $package['name'],
            'credits' => $package['credits'],
            'amount' => $this->packageAmounts($package)['total'],
            'payment_method' => 'Stripe',
            'status' => 'pending',
        ]);

        $successUrl = url('stamp-purchases/return?purchase=' . $purchaseId . '&result=success&session_id={CHECKOUT_SESSION_ID}');
        $cancelUrl = url('stamp-purchases/return?purchase=' . $purchaseId . '&result=cancelled');

        $stripeResult = StripeService::createSubscriptionCheckout(
            $stripePriceId,
            $successUrl,
            $cancelUrl,
            [
                'purchase_id' => (string) $purchaseId,
                'business_id' => (string) $businessId,
                'package_key' => $packageKey,
                'package_name' => (string) $package['name'],
                'credits' => (string) ((int) $package['credits']),
            ],
            auth_business_email(),
            (string) $purchaseId
        );

        if (empty($stripeResult['success'])) {
            StampPurchase::update($purchaseId, [
                'status' => 'failed',
                'notes' => (string) ($stripeResult['message'] ?? 'No se pudo generar la suscripción.'),
            ]);
            flash('error', 'No se pudo generar el checkout de Stripe: ' . ($stripeResult['message'] ?? 'error desconocido'));
            Router::redirect('/stamp-purchases');
        }

        $checkout = StripeService::extractCheckoutSessionData($stripeResult);
        StampPurchase::update($purchaseId, [
            'payment_request_id' => $checkout['subscription_id'] ?: ($checkout['checkout_session_id'] ?: null),
            'payment_request_url' => $checkout['checkout_session_url'] ?: null,
            'clip_status' => $checkout['subscription_status'] ?: ($checkout['checkout_session_status'] ?: null),
        ]);

        AutofacturaLog::log(
            'stamp_checkout_created',
            null,
            $businessId,
            'Suscripción #' . $purchaseId . ' creada en Stripe'
        );

        header('Location: ' . $checkout['checkout_session_url']);
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

        $sessionId = trim((string) ($_GET['session_id'] ?? ''));
        if ($sessionId !== '' && ($purchase['status'] ?? '') !== 'paid') {
            $sessionResult = StripeService::getCheckoutSession($sessionId);
            if (!empty($sessionResult['success'])) {
                $sessionData = StripeService::extractCheckoutSessionData($sessionResult);
                StampPurchase::update($purchaseId, [
                    'payment_request_id' => $sessionData['subscription_id'] ?: ($purchase['payment_request_id'] ?? null),
                    'payment_request_url' => $sessionData['checkout_session_url'] ?: ($purchase['payment_request_url'] ?? null),
                    'clip_status' => $sessionData['subscription_status'] ?: ($purchase['clip_status'] ?? null),
                ]);
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

        if ($result === 'cancelled') {
            flash('error', 'La suscripción no se completó. Puedes intentarlo nuevamente.');
        } else {
            flash('info', 'Aún estamos validando tu suscripción en Stripe. Si ya completaste el checkout, actualiza en unos segundos.');
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
            app_log('Webhook Stripe rechazado por firma inválida. Evento=' . (string) ($payload['type'] ?? 'desconocido'), 'warning');
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => 'Webhook no autorizado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $eventType = (string) ($payload['type'] ?? '');
        $eventObject = $payload['data']['object'] ?? [];
        app_log('Webhook Stripe recibido y autorizado. Evento=' . $eventType, 'info');

        try {
            switch ($eventType) {
                case 'checkout.session.completed':
                    $this->handleStripeCheckoutCompleted($eventObject);
                    break;

                case 'invoice.paid':
                    $this->handleStripeInvoicePaid($eventObject);
                    break;

                case 'invoice.payment_failed':
                    $this->handleStripeInvoicePaymentFailed($eventObject);
                    break;

                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $this->handleStripeSubscriptionUpdated($eventObject);
                    break;
            }
        } catch (Throwable $e) {
            app_log('Webhook Stripe con error interno: ' . $e->getMessage(), 'error');
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    private function isStripeConfigured(): bool
    {
        $secretKey = trim((string) (env('STRIPE_SECRET_KEY', '') ?: env('STRIPE_API_KEY', '')));
        return $secretKey !== '';
    }

    private function isWebhookAuthorized(string $rawBody): bool
    {
        $signature = trim((string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
        return StripeService::verifyWebhookSignature($rawBody, $signature);
    }

    private function webhookLogPath(): string
    {
        $configured = trim((string) env('STRIPE_WEBHOOK_LOG', ''));
        if ($configured !== '') {
            return $configured;
        }

        return STORAGE_PATH . '/logs/stripe_webhook.log';
    }

    private function stripePriceIdForPackage(string $packageKey): ?string
    {
        $envKey = 'STRIPE_PRICE_' . strtoupper($packageKey);
        $priceId = trim((string) env($envKey, ''));
        return $priceId !== '' ? $priceId : null;
    }

    private function packageKeyByStripePriceId(string $priceId): ?string
    {
        $priceId = trim($priceId);
        if ($priceId === '') {
            return null;
        }

        foreach (array_keys(self::PACKAGES) as $packageKey) {
            if ($this->stripePriceIdForPackage($packageKey) === $priceId) {
                return $packageKey;
            }
        }

        return null;
    }

    private function isActiveStripeSubscriptionStatus(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['active', 'trialing'], true);
    }

    private function isDisplayableSubscriptionStatus(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['active', 'trialing', 'past_due', 'unpaid'], true);
    }

    private function changeActiveSubscriptionPlan(
        int $businessId,
        array $activeSubscription,
        string $packageKey,
        array $package,
        string $stripePriceId
    ): void {
        $subscriptionId = trim((string) ($activeSubscription['subscription_id'] ?? ''));
        if ($subscriptionId === '') {
            flash('error', 'No se encontró el identificador de la suscripción activa.');
            Router::redirect('/stamp-purchases');
        }

        $currentCredits = max(0, (int) ($activeSubscription['credits'] ?? 0));
        $targetCredits = max(0, (int) ($package['credits'] ?? 0));
        $additionalCredits = max(0, $targetCredits - $currentCredits);
        if ($additionalCredits <= 0) {
            flash('error', 'Por ahora solo está habilitado subir a un plan mayor.');
            Router::redirect('/stamp-purchases');
        }

        $prorationDate = (int) ($_POST['proration_date'] ?? time());
        if ($prorationDate <= 0) {
            $prorationDate = time();
        }

        $previewResult = StripeService::previewSubscriptionPriceChange($subscriptionId, $stripePriceId, $prorationDate);
        if (empty($previewResult['success'])) {
            flash('error', 'No se pudo calcular el ajuste del cambio de plan: ' . ($previewResult['message'] ?? 'error desconocido'));
            Router::redirect('/stamp-purchases');
        }

        $preview = StripeService::extractUpcomingInvoicePreview($previewResult);
        $purchaseId = StampPurchase::createPendingCheckout($businessId, [
            'package_name' => 'Cambio de plan a ' . (string) ($package['name'] ?? 'suscripción'),
            'credits' => max(1, $additionalCredits),
            'amount' => max(0, (float) ($preview['amount_due'] ?? 0)),
            'payment_request_id' => $subscriptionId,
            'clip_status' => (string) ($activeSubscription['status'] ?? 'active'),
            'payment_method' => 'Stripe',
            'status' => 'pending',
            'notes' => 'Prorrateo por cambio de plan desde ' . (string) ($activeSubscription['package_name'] ?? 'plan anterior') . '.',
        ]);

        $metadata = [
            'business_id' => (string) $businessId,
            'package_key' => $packageKey,
            'package_name' => (string) ($package['name'] ?? 'Suscripción'),
            'credits' => (string) $targetCredits,
        ];

        $updateResult = StripeService::updateSubscriptionPrice(
            $subscriptionId,
            $stripePriceId,
            $metadata,
            $metadata,
            $prorationDate
        );

        if (empty($updateResult['success'])) {
            StampPurchase::update($purchaseId, [
                'status' => 'failed',
                'notes' => (string) ($updateResult['message'] ?? 'No se pudo actualizar la suscripción en Stripe.'),
            ]);
            flash('error', 'No se pudo cambiar el plan en Stripe: ' . ($updateResult['message'] ?? 'error desconocido'));
            Router::redirect('/stamp-purchases');
        }

        AutofacturaLog::log(
            'stamp_checkout_created',
            null,
            $businessId,
            'Cambio de plan Stripe para suscripción ' . $subscriptionId . ' hacia ' . (string) ($package['name'] ?? 'nuevo plan')
        );

        $amountDueNow = number_format((float) ($preview['amount_due'] ?? 0), 2);
        flash('success', 'Plan actualizado. Stripe cobrará el ajuste proporcional de $' . $amountDueNow . ' MXN y en la siguiente renovación cobrará el plan completo.');
        Router::redirect('/stamp-purchases');
    }

    private function handleStripeCheckoutCompleted(array $session): void
    {
        $purchaseId = (int) ($session['client_reference_id'] ?? $session['metadata']['purchase_id'] ?? 0);
        if ($purchaseId <= 0) {
            app_log('Stripe checkout.session.completed sin purchase_id utilizable.', 'warning');
            return;
        }

        $purchase = StampPurchase::find($purchaseId);
        if (!$purchase) {
            return;
        }

        $subscriptionId = trim((string) ($session['subscription'] ?? ''));
        $checkoutStatus = trim((string) ($session['status'] ?? ''));

        $updateData = [
            'payment_request_id' => $subscriptionId !== '' ? $subscriptionId : ($purchase['payment_request_id'] ?? null),
            'payment_request_url' => (string) ($purchase['payment_request_url'] ?? null),
            'clip_status' => $checkoutStatus !== '' ? $checkoutStatus : ($purchase['clip_status'] ?? null),
        ];
        StampPurchase::update($purchaseId, $updateData);
        app_log('Stripe checkout.session.completed actualizado para compra #' . $purchaseId . ' suscripcion=' . ($subscriptionId !== '' ? $subscriptionId : 'N/A'), 'info');
    }

    private function handleStripeInvoicePaid(array $invoice): void
    {
        $invoiceId = trim((string) ($invoice['id'] ?? ''));
        if ($invoiceId === '' || StampPurchase::findByPaymentReference($invoiceId)) {
            if ($invoiceId !== '') {
                app_log('Stripe invoice.paid ignorado por duplicado. invoice=' . $invoiceId, 'info');
            }
            return;
        }

        $subscriptionId = $this->extractInvoiceSubscriptionId($invoice);
        if ($subscriptionId === '') {
            app_log('Stripe invoice.paid sin subscription id. invoice=' . $invoiceId, 'warning');
            return;
        }

        $metadata = $this->extractInvoiceSubscriptionMetadata($invoice);
        $purchaseId = (int) ($metadata['purchase_id'] ?? 0);
        $subscriptionStatus = $this->extractSubscriptionStatusFromInvoice($invoice);
        if ($subscriptionStatus === '') {
            $subscriptionStatus = 'active';
        }

        $purchase = $purchaseId > 0 ? StampPurchase::find($purchaseId) : null;
        if (!$purchase) {
            $purchase = StampPurchase::findLatestPendingByPaymentRequestId($subscriptionId);
        }
        if (!$purchase) {
            $purchase = StampPurchase::findLatestByPaymentRequestId($subscriptionId);
        }

        if (!$purchase) {
            app_log('Stripe invoice.paid sin compra asociada. invoice=' . $invoiceId . ' subscription=' . $subscriptionId . ' purchase_id=' . $purchaseId, 'warning');
            return;
        }

        $currentPurchaseId = (int) ($purchase['id'] ?? 0);
        $isRenewal = ($purchase['status'] ?? '') === 'paid';

        if ($isRenewal) {
            $renewalPackageName = trim((string) ($metadata['package_name'] ?? ($purchase['package_name'] ?? 'Suscripción de timbres')));
            $renewalCredits = max(0, (int) ($metadata['credits'] ?? ($purchase['credits'] ?? 0)));
            $currentPurchaseId = StampPurchase::createPendingCheckout((int) $purchase['business_id'], [
                'package_name' => $renewalPackageName,
                'credits' => $renewalCredits,
                'amount' => round(((float) ($invoice['amount_paid'] ?? 0)) / 100, 2),
                'payment_request_id' => $subscriptionId,
                'payment_request_url' => (string) ($purchase['payment_request_url'] ?? ''),
                'clip_status' => $subscriptionStatus,
                'payment_method' => 'Stripe',
                'payment_reference' => $invoiceId,
                'status' => 'pending',
                'notes' => 'Renovación automática de Stripe.',
            ]);
            $purchase = StampPurchase::find($currentPurchaseId) ?? $purchase;
            app_log('Stripe invoice.paid creó renovación #' . $currentPurchaseId . ' para suscripción ' . $subscriptionId, 'info');
        } else {
            StampPurchase::update($currentPurchaseId, [
                'payment_request_id' => $subscriptionId,
                'clip_status' => $subscriptionStatus ?: (string) ($purchase['clip_status'] ?? null),
                'payment_reference' => $invoiceId,
            ]);
            $purchase = StampPurchase::find($currentPurchaseId) ?? $purchase;
            app_log('Stripe invoice.paid asociado a compra existente #' . $currentPurchaseId . ' subscription=' . $subscriptionId, 'info');
        }

        $this->fulfillPaidPurchase(
            $purchase,
            $subscriptionStatus,
            $invoiceId
        );
        app_log('Stripe invoice.paid surtió correctamente compra #' . $currentPurchaseId . ' invoice=' . $invoiceId, 'info');
    }

    private function handleStripeInvoicePaymentFailed(array $invoice): void
    {
        $subscriptionId = $this->extractInvoiceSubscriptionId($invoice);
        if ($subscriptionId === '') {
            return;
        }

        $purchase = StampPurchase::findLatestByPaymentRequestId($subscriptionId);
        if (!$purchase) {
            return;
        }

        $update = [
            'clip_status' => 'past_due',
        ];
        if (($purchase['status'] ?? '') === 'pending') {
            $update['status'] = 'failed';
        }

        StampPurchase::update((int) $purchase['id'], $update);
    }

    private function handleStripeSubscriptionUpdated(array $subscription): void
    {
        $subscriptionId = trim((string) ($subscription['id'] ?? ''));
        if ($subscriptionId === '') {
            return;
        }

        $purchase = StampPurchase::findLatestByPaymentRequestId($subscriptionId);
        if (!$purchase) {
            return;
        }

        $status = trim((string) ($subscription['status'] ?? ''));
        $update = [
            'clip_status' => $status !== '' ? $status : ($purchase['clip_status'] ?? null),
        ];

        if (($subscription['cancel_at_period_end'] ?? false) || $status === 'canceled' || $status === 'unpaid') {
            $update['status'] = 'cancelled';
        }

        StampPurchase::update((int) $purchase['id'], $update);
    }

    private function fulfillPaidPurchase(array $purchase, ?string $providerStatus, ?string $reference): void
    {
        $purchaseId = (int) ($purchase['id'] ?? 0);
        if ($purchaseId <= 0) {
            throw new RuntimeException('No existe una compra válida para acreditar.');
        }

        StampPurchase::update($purchaseId, [
            'clip_status' => $providerStatus ?: ($purchase['clip_status'] ?? null),
            'payment_reference' => $reference ?: ($purchase['payment_reference'] ?? null),
        ]);

        $purchase = StampPurchase::find($purchaseId) ?? $purchase;
        app_log('Iniciando surtido de compra #' . $purchaseId . ' negocio=' . (int) ($purchase['business_id'] ?? 0) . ' creditos=' . (int) ($purchase['credits'] ?? 0), 'info');
        $this->transferPurchaseCreditsViaEf($purchase);
        StampPurchase::markAsPaid($purchaseId, $providerStatus, $reference);
        $this->syncBusinessStampCreditsFromEf((int) ($purchase['business_id'] ?? 0));
        $this->ensureSuperuserInvoiceLink($purchaseId);
    }

    private function extractInvoiceSubscriptionMetadata(array $invoice): array
    {
        $sources = [
            $invoice['parent']['subscription_details']['metadata'] ?? null,
            $invoice['lines']['data'][0]['metadata'] ?? null,
        ];

        foreach ($sources as $source) {
            if (is_array($source) && !empty($source)) {
                return $source;
            }
        }

        return [];
    }

    private function extractInvoiceSubscriptionId(array $invoice): string
    {
        $candidates = [
            $invoice['subscription'] ?? null,
            $invoice['parent']['subscription_details']['subscription'] ?? null,
            $invoice['lines']['data'][0]['parent']['subscription_item_details']['subscription'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $subscriptionId = trim((string) $candidate);
            if ($subscriptionId !== '') {
                return $subscriptionId;
            }
        }

        return '';
    }

    private function extractSubscriptionStatusFromInvoice(array $invoice): string
    {
        $candidates = [
            $invoice['subscription_details']['status'] ?? null,
            $invoice['parent']['subscription_details']['status'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $status = trim((string) $candidate);
            if ($status !== '') {
                return $status;
            }
        }

        return $invoice['status'] === 'paid' ? 'active' : '';
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

        if (!StripeService::isProvisionedStatus((string) ($purchase['clip_status'] ?? ''))) {
            flash('error', 'Esta transacción aún no aparece como activa en Stripe.');
            Router::redirect('/stamp-purchases');
        }

        try {
            $this->fulfillPaidPurchase(
                $purchase,
                (string) ($purchase['clip_status'] ?? ''),
                (string) ($purchase['payment_reference'] ?? $purchase['payment_request_id'] ?? '')
            );

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
