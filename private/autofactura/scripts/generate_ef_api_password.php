<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se puede ejecutar por CLI.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

require_once CONFIG_PATH . '/env.php';
loadEnv(BASE_PATH . '/.env');
require_once APP_PATH . '/Helpers/functions.php';

$plainText = isset($argv[1]) ? trim((string) $argv[1]) : '';

if ($plainText === '') {
    fwrite(STDERR, "Uso:\n");
    fwrite(STDERR, "  php generate_ef_api_password.php \"texto a cifrar\"\n");
    fwrite(STDERR, "Ejemplo:\n");
    fwrite(STDERR, "  php generate_ef_api_password.php \"cfditop1VACE850510U59#\"\n");
    exit(1);
}

$encrypted = encrypt_secret($plainText);

if ($encrypted === null || $encrypted === '') {
    fwrite(STDERR, "No se pudo generar el valor enc.\n");
    exit(1);
}

fwrite(STDOUT, $encrypted . PHP_EOL);
