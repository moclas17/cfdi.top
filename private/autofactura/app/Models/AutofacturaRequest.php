<?php
/**
 * Modelo: AutofacturaRequest (Solicitud de autofactura)
 */

class AutofacturaRequest extends BaseModel
{
    protected static string $table = 'autofactura_requests';
    private static array $columnCache = [];

    protected static array $fillable = [
        'business_id',
        'concept_id',
        'custom_concept_text',
        'token',
        'phone',
        'email',
        'whatsapp_sent',
        'amount',
        'invoice_uuid',
        'invoice_xml_url',
        'invoice_pdf_url',
        'invoiced_at',
        'status',
        'expires_at',
    ];

    /**
     * Buscar solicitud por token
     */
    public static function findByToken(string $token): ?array
    {
        return self::findBy('token', $token);
    }

    /**
     * Obtener solicitudes de un negocio
     */
    public static function getByBusiness(int $businessId): array
    {
        $sql = "SELECT r.*, c.name as base_concept_name,
                       COALESCE(NULLIF(r.custom_concept_text, ''), c.name) as concept_name
                FROM `autofactura_requests` r
                LEFT JOIN `invoice_concepts` c ON r.concept_id = c.id
                WHERE r.business_id = :bid
                ORDER BY r.created_at DESC";
        return Database::fetchAll($sql, ['bid' => $businessId]);
    }

    /**
     * Actualizar estatus
     */
    public static function updateStatus(int $id, string $status): bool
    {
        return self::update($id, ['status' => $status]);
    }

    /**
     * Verificar si la solicitud ha expirado
     */
    public static function isExpired(array $request): bool
    {
        if (empty($request['expires_at'])) {
            return false;
        }
        return new DateTime($request['expires_at']) < new DateTime();
    }

    /**
     * Crear solicitud con token generado
     */
    public static function createWithToken(array $data): int
    {
        $data['token'] = self::generateToken();
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = (new DateTime())->modify('+3 days')->format('Y-m-d H:i:s');
        }
        return self::create($data);
    }

    /**
     * Verificar si una columna existe en la tabla de solicitudes.
     */
    public static function hasColumn(string $column): bool
    {
        if (array_key_exists($column, self::$columnCache)) {
            return self::$columnCache[$column];
        }

        try {
            Database::fetchOne("SELECT `{$column}` FROM `autofactura_requests` LIMIT 1");
            self::$columnCache[$column] = true;
        } catch (DatabaseQueryException $e) {
            self::$columnCache[$column] = $e->getSqlState() !== '42S22';
        } catch (Throwable) {
            self::$columnCache[$column] = false;
        }

        return self::$columnCache[$column];
    }

    public static function supportsWhatsappSentFlag(): bool
    {
        return self::hasColumn('whatsapp_sent');
    }

    public static function supportsCustomConceptText(): bool
    {
        return self::hasColumn('custom_concept_text');
    }

    public static function markWhatsappSent(int $id): bool
    {
        if (!self::supportsWhatsappSentFlag()) {
            return false;
        }
        return self::update($id, ['whatsapp_sent' => 1]);
    }
}
