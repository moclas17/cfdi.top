<?php
/**
 * Modelo: StampPurchase (Historial de compras de timbres)
 */

class StampPurchase extends BaseModel
{
    protected static string $table = 'stamp_purchases';
    private static array $columnCache = [];

    protected static array $fillable = [
        'business_id',
        'package_name',
        'credits',
        'amount',
        'invoice_request_id',
        'invoice_link_sent_at',
        'payment_request_id',
        'payment_request_url',
        'clip_status',
        'payment_method',
        'payment_reference',
        'status',
        'notes',
        'paid_at',
    ];

    public static function getByBusiness(int $businessId, int $limit = 20): array
    {
        $limit = max(1, $limit);

        if (!self::hasColumn('invoice_request_id')) {
            return Database::fetchAll(
                "SELECT sp.*,
                        NULL AS invoice_token,
                        NULL AS invoice_status
                 FROM `stamp_purchases` sp
                 WHERE sp.`business_id` = :business_id
                 ORDER BY COALESCE(sp.`paid_at`, sp.`created_at`) DESC, sp.`id` DESC
                 LIMIT {$limit}",
                ['business_id' => $businessId]
            );
        }

        return Database::fetchAll(
            "SELECT sp.*,
                    ar.token AS invoice_token,
                    ar.status AS invoice_status
             FROM `stamp_purchases` sp
             LEFT JOIN `autofactura_requests` ar ON ar.id = sp.invoice_request_id
             WHERE sp.`business_id` = :business_id
             ORDER BY COALESCE(sp.`paid_at`, sp.`created_at`) DESC, sp.`id` DESC
             LIMIT {$limit}",
            ['business_id' => $businessId]
        );
    }

    public static function getAllCheckoutOrders(int $limit = 100): array
    {
        $limit = max(1, $limit);

        if (!self::hasColumn('invoice_request_id')) {
            return Database::fetchAll(
                "SELECT sp.*,
                        b.name AS business_name,
                        b.email AS business_email,
                        NULL AS invoice_token,
                        NULL AS invoice_status
                 FROM `stamp_purchases` sp
                 INNER JOIN `businesses` b ON b.id = sp.business_id
                 WHERE sp.payment_request_id IS NOT NULL
                   AND sp.payment_request_id <> ''
                 ORDER BY COALESCE(sp.paid_at, sp.created_at) DESC, sp.id DESC
                 LIMIT {$limit}"
            );
        }

        return Database::fetchAll(
            "SELECT sp.*,
                    b.name AS business_name,
                    b.email AS business_email,
                    ar.token AS invoice_token,
                    ar.status AS invoice_status
             FROM `stamp_purchases` sp
             INNER JOIN `businesses` b ON b.id = sp.business_id
             LEFT JOIN `autofactura_requests` ar ON ar.id = sp.invoice_request_id
             WHERE sp.payment_request_id IS NOT NULL
               AND sp.payment_request_id <> ''
             ORDER BY COALESCE(sp.paid_at, sp.created_at) DESC, sp.id DESC
             LIMIT {$limit}"
        );
    }

    public static function findByPaymentRequestId(string $paymentRequestId): ?array
    {
        return self::findBy('payment_request_id', $paymentRequestId);
    }

    public static function hasColumn(string $column): bool
    {
        if (array_key_exists($column, self::$columnCache)) {
            return self::$columnCache[$column];
        }

        try {
            Database::fetchOne("SELECT `{$column}` FROM `stamp_purchases` LIMIT 1");
            self::$columnCache[$column] = true;
        } catch (DatabaseQueryException $e) {
            self::$columnCache[$column] = $e->getSqlState() !== '42S22';
        } catch (Throwable) {
            self::$columnCache[$column] = false;
        }

        return self::$columnCache[$column];
    }

    public static function registerPaidPurchase(int $businessId, array $data): int
    {
        $credits = max(0, (int) ($data['credits'] ?? 0));
        if ($credits <= 0) {
            throw new InvalidArgumentException('La compra de timbres debe incluir una cantidad de créditos mayor a cero.');
        }

        Database::beginTransaction();

        try {
            $purchaseId = self::create([
                'business_id' => $businessId,
                'package_name' => trim((string) ($data['package_name'] ?? 'Recarga de timbres')),
                'credits' => $credits,
                'amount' => (float) ($data['amount'] ?? 0),
                'payment_method' => trim((string) ($data['payment_method'] ?? '')) ?: null,
                'payment_reference' => trim((string) ($data['payment_reference'] ?? '')) ?: null,
                'status' => 'paid',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'paid_at' => $data['paid_at'] ?? date('Y-m-d H:i:s'),
            ]);

            if (!Business::addStampCredits($businessId, $credits)) {
                throw new RuntimeException('No se pudo acreditar el saldo de timbres al negocio.');
            }

            AutofacturaLog::log(
                'stamp_purchase_registered',
                null,
                $businessId,
                'Compra acreditada: +' . $credits . ' timbres'
            );

            Database::commit();
            return $purchaseId;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function createPendingCheckout(int $businessId, array $data): int
    {
        $credits = max(0, (int) ($data['credits'] ?? 0));
        if ($credits <= 0) {
            throw new InvalidArgumentException('La orden de compra debe incluir una cantidad de timbres mayor a cero.');
        }

        return self::create([
            'business_id' => $businessId,
            'package_name' => trim((string) ($data['package_name'] ?? 'Compra de timbres')),
            'credits' => $credits,
            'amount' => (float) ($data['amount'] ?? 0),
            'payment_request_id' => trim((string) ($data['payment_request_id'] ?? '')) ?: null,
            'payment_request_url' => trim((string) ($data['payment_request_url'] ?? '')) ?: null,
            'clip_status' => trim((string) ($data['clip_status'] ?? '')) ?: null,
            'payment_method' => trim((string) ($data['payment_method'] ?? 'Clip')) ?: 'Clip',
            'payment_reference' => trim((string) ($data['payment_reference'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? 'pending')) ?: 'pending',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'paid_at' => $data['paid_at'] ?? null,
        ]);
    }

    public static function markAsPaid(int $purchaseId, ?string $clipStatus = null, ?string $reference = null): bool
    {
        $purchase = self::find($purchaseId);
        if (!$purchase) {
            return false;
        }

        if (($purchase['status'] ?? '') === 'paid') {
            return true;
        }

        Database::beginTransaction();

        try {
            $updated = self::update($purchaseId, [
                'status' => 'paid',
                'clip_status' => $clipStatus ?: ($purchase['clip_status'] ?? null),
                'payment_reference' => $reference ?: ($purchase['payment_reference'] ?? null),
                'paid_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$updated) {
                throw new RuntimeException('No se pudo marcar la compra de timbres como pagada.');
            }

            if (!Business::addStampCredits((int) $purchase['business_id'], (int) $purchase['credits'])) {
                throw new RuntimeException('No se pudieron acreditar los timbres comprados al negocio.');
            }

            AutofacturaLog::log(
                'stamp_purchase_paid',
                null,
                (int) $purchase['business_id'],
                'Compra acreditada: +' . (int) $purchase['credits'] . ' timbres'
            );

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
