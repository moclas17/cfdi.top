<?php
/**
 * AutoFactura - Base Model
 * Modelo abstracto con operaciones CRUD genéricas.
 * Los modelos hijos definen $table y $fillable.
 */

abstract class BaseModel
{
    /**
     * Nombre de la tabla (definir en modelo hijo)
     */
    protected static string $table = '';

    /**
     * Columnas permitidas para asignación masiva
     */
    protected static array $fillable = [];

    /**
     * Columna de clave primaria
     */
    protected static string $primaryKey = 'id';

    // =========================================
    // Lectura
    // =========================================

    /**
     * Obtener todos los registros
     */
    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $table = static::$table;
        $sql = "SELECT * FROM `{$table}` ORDER BY `{$orderBy}` {$direction}";
        return Database::fetchAll($sql);
    }

    /**
     * Buscar por ID
     */
    public static function find(int $id): ?array
    {
        $table = static::$table;
        $pk = static::$primaryKey;
        $sql = "SELECT * FROM `{$table}` WHERE `{$pk}` = :id LIMIT 1";
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Buscar por un campo específico
     */
    public static function findBy(string $field, mixed $value): ?array
    {
        $table = static::$table;
        $sql = "SELECT * FROM `{$table}` WHERE `{$field}` = :value LIMIT 1";
        return Database::fetchOne($sql, ['value' => $value]);
    }

    /**
     * Obtener múltiples registros por un campo
     */
    public static function where(string $field, mixed $value, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $table = static::$table;
        $sql = "SELECT * FROM `{$table}` WHERE `{$field}` = :value ORDER BY `{$orderBy}` {$direction}";
        return Database::fetchAll($sql, ['value' => $value]);
    }

    /**
     * Contar registros con condición
     */
    public static function count(string $field = '', mixed $value = null): int
    {
        $table = static::$table;
        if ($field && $value !== null) {
            $sql = "SELECT COUNT(*) as total FROM `{$table}` WHERE `{$field}` = :value";
            $result = Database::fetchOne($sql, ['value' => $value]);
        } else {
            $sql = "SELECT COUNT(*) as total FROM `{$table}`";
            $result = Database::fetchOne($sql);
        }
        return (int) ($result['total'] ?? 0);
    }

    // =========================================
    // Escritura
    // =========================================

    /**
     * Crear un nuevo registro
     */
    public static function create(array $data): int
    {
        $table = static::$table;
        $data = self::filterFillable($data);

        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

        $sql = "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$placeholders})";
        return (int) Database::insert($sql, $data);
    }

    /**
     * Actualizar un registro por ID
     */
    public static function update(int $id, array $data): bool
    {
        $table = static::$table;
        $pk = static::$primaryKey;
        $data = self::filterFillable($data);

        $sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($data)));
        $data['_pk_id'] = $id;

        $sql = "UPDATE `{$table}` SET {$sets} WHERE `{$pk}` = :_pk_id";
        return Database::execute($sql, $data) > 0;
    }

    /**
     * Eliminar un registro por ID
     */
    public static function delete(int $id): bool
    {
        $table = static::$table;
        $pk = static::$primaryKey;
        $sql = "DELETE FROM `{$table}` WHERE `{$pk}` = :id";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    // =========================================
    // Utilidades
    // =========================================

    /**
     * Filtrar datos permitidos (fillable)
     */
    protected static function filterFillable(array $data): array
    {
        if (empty(static::$fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip(static::$fillable));
    }

    /**
     * Generar un token seguro
     */
    public static function generateToken(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
