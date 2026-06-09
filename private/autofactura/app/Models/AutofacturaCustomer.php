<?php
/**
 * Modelo: AutofacturaCustomer (Datos fiscales del cliente)
 */

class AutofacturaCustomer extends BaseModel
{
    protected static string $table = 'autofactura_customers';

    protected static array $fillable = [
        'request_id',
        'rfc',
        'razon_social',
        'codigo_postal',
        'regimen_fiscal',
        'uso_cfdi',
        'email',
        'phone',
        'efos_status',
        'efos_checked_at',
    ];

    /**
     * Obtener datos del cliente por ID de solicitud
     */
    public static function getByRequest(int $requestId): ?array
    {
        return self::findBy('request_id', $requestId);
    }

    public static function upsertByRequest(int $requestId, array $data): int
    {
        $existing = self::getByRequest($requestId);
        if ($existing) {
            self::update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        return self::create($data);
    }
}
