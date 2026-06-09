<?php
/**
 * Modelo: BusinessSetting (Configuración del negocio)
 */

class BusinessSetting extends BaseModel
{
    protected static string $table = 'business_settings';
    private static array $columnCache = [];

    protected static array $fillable = [
        'business_id',
        'invoicing_mode',
        'rfc_emisor',
        'nombre_emisor',
        'regimen_fiscal',
        'codigo_postal',
        'api_url',
        'api_user',
        'api_password',
        'api_key',
        'commercial_name',
        'logo',
        'template_color',
        'font_color',
        'link_expiration_days',
        'csd_key_path',
        'csd_cer_path',
        'csd_password',
        'csd_uploaded_at',
        'csd_rfc',
        'csd_valid_from',
        'csd_valid_to',
    ];

    /**
     * Obtener configuración del negocio
     */
    public static function getByBusiness(int $businessId): ?array
    {
        return self::findBy('business_id', $businessId);
    }

    /**
     * Obtener configuración lista para uso interno del sistema.
     */
    public static function getRuntimeByBusiness(int $businessId): ?array
    {
        $settings = self::getByBusiness($businessId);
        if (!$settings) {
            return null;
        }

        foreach (['api_password', 'api_key', 'csd_password'] as $secretField) {
            if (array_key_exists($secretField, $settings)) {
                $settings[$secretField] = decrypt_secret($settings[$secretField]);
            }
        }

        return $settings;
    }

    /**
     * Obtener credenciales CSD descifradas para el motor de sellado.
     */
    public static function getCsdCredentials(int $businessId): ?array
    {
        $settings = self::getRuntimeByBusiness($businessId);
        if (!$settings || empty($settings['csd_key_path']) || empty($settings['csd_cer_path']) || empty($settings['csd_password'])) {
            return null;
        }

        $keyPath = STORAGE_PATH . '/' . ltrim((string) $settings['csd_key_path'], '/');
        $cerPath = STORAGE_PATH . '/' . ltrim((string) $settings['csd_cer_path'], '/');

        return [
            'key_contents' => read_encrypted_file($keyPath),
            'cer_contents' => read_encrypted_file($cerPath),
            'password' => (string) $settings['csd_password'],
            'key_path' => $keyPath,
            'cer_path' => $cerPath,
        ];
    }

    /**
     * Verificar si una columna existe en la tabla.
     */
    public static function hasColumn(string $column): bool
    {
        if (array_key_exists($column, self::$columnCache)) {
            return self::$columnCache[$column];
        }

        try {
            Database::fetchOne("SELECT `{$column}` FROM `business_settings` LIMIT 1");
            self::$columnCache[$column] = true;
        } catch (DatabaseQueryException $e) {
            self::$columnCache[$column] = $e->getSqlState() !== '42S22';
        } catch (Throwable) {
            self::$columnCache[$column] = false;
        }

        return self::$columnCache[$column];
    }

    /**
     * Crear o actualizar configuración
     */
    public static function upsert(int $businessId, array $data): bool
    {
        $existing = self::getByBusiness($businessId);
        $data = self::filterSupportedColumns($data);
        $data['business_id'] = $businessId;

        if ($existing) {
            return self::update($existing['id'], $data);
        } else {
            return self::create($data) > 0;
        }
    }

    /**
     * Remover columnas que aún no existan en la BD.
     */
    private static function filterSupportedColumns(array $data): array
    {
        foreach (array_keys($data) as $column) {
            if ($column === 'business_id') {
                continue;
            }

            if (!self::hasColumn($column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }
}
