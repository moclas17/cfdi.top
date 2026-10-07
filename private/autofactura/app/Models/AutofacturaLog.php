<?php
/**
 * Modelo: AutofacturaLog (Bitácora de eventos)
 */

class AutofacturaLog extends BaseModel
{
    protected static string $table = 'autofactura_logs';

    protected static array $fillable = [
        'request_id',
        'business_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
    ];

    /**
     * Registrar un evento en la bitácora
     */
    public static function log(string $action, ?int $requestId = null, ?int $businessId = null, ?string $details = null): int
    {
        return self::create([
            'request_id'  => $requestId,
            'business_id' => $businessId,
            'action'      => $action,
            'details'     => self::sanitizeDetails($details),
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    /**
     * Obtener logs de una solicitud
     */
    public static function getByRequest(int $requestId): array
    {
        return self::sanitizeRows(self::where('request_id', $requestId, 'created_at', 'DESC'));
    }

    public static function getLatestByRequestAndAction(int $requestId, string $action): ?array
    {
        $sql = "SELECT *
                FROM `autofactura_logs`
                WHERE `request_id` = :request_id
                  AND `action` = :action
                ORDER BY `created_at` DESC, `id` DESC
                LIMIT 1";

        $row = Database::fetchOne($sql, [
            'request_id' => $requestId,
            'action' => $action,
        ]);

        return $row === null ? null : self::sanitizeRow($row);
    }

    /**
     * Obtener logs de un negocio
     */
    public static function getByBusiness(int $businessId): array
    {
        return self::sanitizeRows(self::where('business_id', $businessId, 'created_at', 'DESC'));
    }

    public static function hasActionForRequest(int $requestId, string $action): bool
    {
        $sql = "SELECT id FROM `autofactura_logs` WHERE `request_id` = :request_id AND `action` = :action LIMIT 1";
        return Database::fetchOne($sql, [
            'request_id' => $requestId,
            'action' => $action,
        ]) !== null;
    }

    public static function hasRecentActionForBusiness(int $businessId, string $action, int $withinSeconds): bool
    {
        $threshold = date('Y-m-d H:i:s', time() - max(0, $withinSeconds));

        $sql = "SELECT id
                FROM `autofactura_logs`
                WHERE `business_id` = :business_id
                  AND `action` = :action
                  AND `created_at` >= :threshold
                LIMIT 1";

        return Database::fetchOne($sql, [
            'business_id' => $businessId,
            'action' => $action,
            'threshold' => $threshold,
        ]) !== null;
    }

    public static function getRecentByBusinessAndActions(int $businessId, array $actions, int $limit = 20): array
    {
        $actions = array_values(array_filter(array_map('trim', $actions), static fn (string $action): bool => $action !== ''));
        if (empty($actions)) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $placeholders = [];
        $params = ['business_id' => $businessId];

        foreach ($actions as $index => $action) {
            $key = 'action_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $action;
        }

        $sql = "SELECT *
                FROM `autofactura_logs`
                WHERE `business_id` = :business_id
                  AND `action` IN (" . implode(', ', $placeholders) . ")
                ORDER BY `created_at` DESC, `id` DESC
                LIMIT {$limit}";

        return self::sanitizeRows(Database::fetchAll($sql, $params));
    }

    private static function sanitizeRows(array $rows): array
    {
        return array_map(static fn(array $row): array => self::sanitizeRow($row), $rows);
    }

    private static function sanitizeRow(array $row): array
    {
        if (array_key_exists('details', $row)) {
            $row['details'] = self::sanitizeDetails(
                is_string($row['details']) ? $row['details'] : null
            );
        }

        return $row;
    }

    private static function sanitizeDetails(?string $details): ?string
    {
        if ($details === null || $details === '') {
            return $details;
        }

        $details = preg_replace(
            '/\s*Usuario enviado:.*?(?=\s*API URL configurada:|\s*Endpoint SOAP efectivo:|$)/is',
            '',
            $details
        ) ?? $details;
        $details = preg_replace(
            '/(contrase(?:ñ|n)a|password)\s*(?:enviada)?\s*:\s*[^\s|]+/iu',
            '$1: [PROTEGIDA]',
            $details
        ) ?? $details;
        $details = preg_replace(
            '/(Authorization\s*:\s*Bearer\s+)[A-Za-z0-9._~+\/-]+/i',
            '$1[PROTEGIDO]',
            $details
        ) ?? $details;

        return trim($details);
    }
}
