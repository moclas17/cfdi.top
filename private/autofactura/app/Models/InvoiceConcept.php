<?php
/**
 * Modelo: InvoiceConcept (Concepto de facturación)
 */

class InvoiceConcept extends BaseModel
{
    protected static string $table = 'invoice_concepts';
    private static array $columnCache = [];

    protected static array $fillable = [
        'business_id',
        'name',
        'description',
        'sat_product_key',
        'sat_unit_key',
        'unit_name',
        'tax_object',
        'tax_type',
        'tax_rate',
        'default_amount',
        'is_default',
        'is_active',
    ];

    /**
     * Obtener conceptos activos de un negocio
     */
    public static function getActiveByBusiness(int $businessId): array
    {
        $sql = "SELECT * FROM `invoice_concepts` WHERE `business_id` = :bid AND `is_active` = 1 ORDER BY `is_default` DESC, `name` ASC";
        return Database::fetchAll($sql, ['bid' => $businessId]);
    }

    /**
     * Obtener el concepto por defecto de un negocio
     */
    public static function getDefault(int $businessId): ?array
    {
        $sql = "SELECT * FROM `invoice_concepts` WHERE `business_id` = :bid AND `is_default` = 1 AND `is_active` = 1 LIMIT 1";
        return Database::fetchOne($sql, ['bid' => $businessId]);
    }

    public static function getFirstActive(int $businessId): ?array
    {
        $sql = "SELECT *
                FROM `invoice_concepts`
                WHERE `business_id` = :bid AND `is_active` = 1
                ORDER BY `is_default` DESC, `id` ASC
                LIMIT 1";
        return Database::fetchOne($sql, ['bid' => $businessId]);
    }

    /**
     * Verificar si una columna existe en la tabla de conceptos.
     */
    public static function hasColumn(string $column): bool
    {
        if (array_key_exists($column, self::$columnCache)) {
            return self::$columnCache[$column];
        }

        try {
            // Prueba directa de columna para evitar falsos negativos por permisos de metadatos.
            Database::fetchOne("SELECT `{$column}` FROM `invoice_concepts` LIMIT 1");
            self::$columnCache[$column] = true;
        } catch (DatabaseQueryException $e) {
            self::$columnCache[$column] = $e->getSqlState() !== '42S22';
        } catch (Throwable) {
            self::$columnCache[$column] = false;
        }

        return self::$columnCache[$column];
    }

    public static function supportsDefaultAmount(): bool
    {
        return self::hasColumn('default_amount');
    }

    /**
     * Crear concepto asegurando un solo default por negocio.
     */
    public static function createForBusiness(array $data): int
    {
        $businessId = (int) ($data['business_id'] ?? 0);
        $isDefault = !empty($data['is_default']);

        Database::beginTransaction();

        try {
            if ($businessId > 0 && $isDefault) {
                self::clearDefaultForBusiness($businessId);
            }

            $id = self::create($data);
            Database::commit();

            return $id;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Actualizar concepto asegurando un solo default por negocio.
     */
    public static function updateForBusiness(int $id, array $data): bool
    {
        $concept = self::find($id);
        if (!$concept) {
            return false;
        }

        $businessId = (int) ($concept['business_id'] ?? 0);
        $isDefault = !empty($data['is_default']);

        Database::beginTransaction();

        try {
            if ($businessId > 0 && $isDefault) {
                self::clearDefaultForBusiness($businessId, $id);
            }

            $updated = self::update($id, $data);
            Database::commit();

            return $updated;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Limpiar concepto default previo del negocio.
     */
    public static function clearDefaultForBusiness(int $businessId, ?int $excludeId = null): void
    {
        $sql = "UPDATE `invoice_concepts`
                SET `is_default` = 0
                WHERE `business_id` = :bid";

        $params = ['bid' => $businessId];

        if ($excludeId !== null) {
            $sql .= " AND `id` <> :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        Database::execute($sql, $params);
    }
}
