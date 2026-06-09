<?php
/**
 * AutoFactura - Database Service (Singleton PDO)
 * Gestiona la conexión a la base de datos usando PDO.
 */

class DatabaseQueryException extends RuntimeException
{
    private string $sqlState;
    private int $driverCode;

    public function __construct(
        string $message,
        string $sqlState = '',
        int $driverCode = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->sqlState = $sqlState;
        $this->driverCode = $driverCode;
    }

    public function getSqlState(): string
    {
        return $this->sqlState;
    }

    public function getDriverCode(): int
    {
        return $this->driverCode;
    }

    public function isIntegrityViolation(): bool
    {
        return $this->sqlState === '23000';
    }
}

class Database
{
    private static ?PDO $instance = null;

    /**
     * Obtener la instancia de PDO (singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                if (env('APP_DEBUG', false)) {
                    throw new RuntimeException('Error de conexión a BD: ' . $e->getMessage());
                }
                throw new RuntimeException('Error de conexión a la base de datos.');
            }
        }

        return self::$instance;
    }

    /**
     * Ejecutar una consulta preparada y retornar el statement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode() ?? '');
            $driverCode = (int) ($e->errorInfo[1] ?? 0);

            $message = env('APP_DEBUG', false)
                ? ('Error SQL [' . $sqlState . ']: ' . $e->getMessage())
                : 'Ocurrió un error al procesar la operación en base de datos.';

            throw new DatabaseQueryException($message, $sqlState, $driverCode, $e);
        }
    }

    /**
     * Obtener un solo registro
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Obtener todos los registros
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Insertar y retornar el ID generado
     */
    public static function insert(string $sql, array $params = []): string
    {
        self::query($sql, $params);
        return self::getInstance()->lastInsertId();
    }

    /**
     * Actualizar/eliminar y retornar filas afectadas
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Iniciar una transacción
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Confirmar transacción
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Revertir transacción
     */
    public static function rollBack(): bool
    {
        return self::getInstance()->rollBack();
    }

    /**
     * Prevenir clonación
     */
    private function __clone() {}
}
