<?php
/**
 * AutoFactura - Cargador de variables de entorno
 * Lee el archivo .env y carga las variables en $_ENV y getenv()
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException("Archivo .env no encontrado en: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentarios
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }

        // Separar clave=valor
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remover comillas si existen
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }

        // Establecer en entorno
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

/**
 * Obtener variable de entorno con valor por defecto
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}
