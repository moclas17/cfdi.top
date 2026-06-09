<?php
declare(strict_types=1);

/**
 * Script de diagnóstico para wsGetCredit
 *
 * Uso:
 *   php private/autofactura/scripts/test_ws_getcredit.php --business=1
 *   php private/autofactura/scripts/test_ws_getcredit.php --url="https://efectosfiscales.mx/wsdemo/regresajson2.php?wsdl" --user="usuario" --password="contrasena"
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('VIEWS_PATH', BASE_PATH . '/resources/views');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once CONFIG_PATH . '/env.php';
loadEnv(BASE_PATH . '/.env');

$appTimezone = env('APP_TIMEZONE', 'America/Mexico_City');
if (is_string($appTimezone) && $appTimezone !== '') {
    date_default_timezone_set($appTimezone);
}

require_once APP_PATH . '/Services/Database.php';
require_once APP_PATH . '/Services/EfectosFiscalesService.php';
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Models/BaseModel.php';
require_once APP_PATH . '/Models/BusinessSetting.php';

function stdout(string $text = ''): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function stderr(string $text = ''): void
{
    fwrite(STDERR, $text . PHP_EOL);
}

function mask_secret(string $value): string
{
    $length = strlen($value);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
}

function usage(): void
{
    stdout('Uso:');
    stdout('  php private/autofactura/scripts/test_ws_getcredit.php --business=1');
    stdout('  php private/autofactura/scripts/test_ws_getcredit.php --url="https://efectosfiscales.mx/wsdemo/regresajson2.php?wsdl" --user="usuario" --password="contrasena"');
    stdout('');
    stdout('Opciones:');
    stdout('  --business=ID       Toma api_url/api_user/api_password/api_key desde business_settings');
    stdout('  --url=URL           URL del webservice o WSDL');
    stdout('  --user=USER         Usuario');
    stdout('  --password=PASS     Contraseña');
    stdout('  --key=KEY           API key opcional');
    stdout('  --help              Mostrar esta ayuda');
}

function resolve_wsdl_location(string $wsdlUrl): string
{
    $defaultLocation = str_ireplace('?wsdl', '', $wsdlUrl);
    $contents = @file_get_contents($wsdlUrl);

    if (!is_string($contents) || trim($contents) === '') {
        return $defaultLocation;
    }

    if (preg_match('/<soap:address\b[^>]*location="([^"]+)"/i', $contents, $matches)) {
        $location = trim((string) ($matches[1] ?? ''));
        if ($location !== '') {
            return $location;
        }
    }

    return $defaultLocation;
}

$options = getopt('', [
    'business:',
    'url:',
    'user:',
    'password:',
    'key:',
    'help',
]);

if (isset($options['help'])) {
    usage();
    exit(0);
}

$config = [
    'api_url' => '',
    'api_user' => '',
    'api_password' => '',
    'api_key' => '',
];

if (!empty($options['business'])) {
    $businessId = (int) $options['business'];
    $runtime = BusinessSetting::getRuntimeByBusiness($businessId);

    if (!$runtime) {
        stderr('No se encontró configuración para business_id=' . $businessId);
        exit(1);
    }

    $config['api_url'] = trim((string) ($runtime['api_url'] ?? ''));
    $config['api_user'] = trim((string) ($runtime['api_user'] ?? ''));
    $config['api_password'] = trim((string) ($runtime['api_password'] ?? ''));
    $config['api_key'] = trim((string) ($runtime['api_key'] ?? ''));
} else {
    $config['api_url'] = trim((string) ($options['url'] ?? env('EF_DEFAULT_API_URL', '')));
    $config['api_user'] = trim((string) ($options['user'] ?? ''));
    $config['api_password'] = trim((string) ($options['password'] ?? ''));
    $config['api_key'] = trim((string) ($options['key'] ?? ''));
}

if ($config['api_url'] === '' || $config['api_user'] === '' || $config['api_password'] === '') {
    stderr('Faltan datos. Necesitas URL, usuario y contraseña.');
    usage();
    exit(1);
}

$wsdlUrl = str_contains(strtolower($config['api_url']), '?wsdl')
    ? $config['api_url']
    : rtrim($config['api_url'], '?') . '?wsdl';
$location = resolve_wsdl_location($wsdlUrl);

stdout('== Diagnóstico wsGetCredit ==');
stdout('Fecha:      ' . date('Y-m-d H:i:s'));
stdout('WSDL:       ' . $wsdlUrl);
stdout('Location:   ' . $location);
stdout('Usuario:    ' . $config['api_user']);
stdout('Contraseña: ' . mask_secret($config['api_password']));
stdout('API key:    ' . ($config['api_key'] !== '' ? mask_secret($config['api_key']) : '(vacía)'));
stdout('');

if (!class_exists('SoapClient')) {
    stderr('SOAP no está disponible en este entorno PHP.');
    exit(1);
}

try {
    $client = new SoapClient($wsdlUrl, [
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'connection_timeout' => 20,
        'soap_version' => SOAP_1_1,
        'style' => SOAP_RPC,
        'use' => SOAP_ENCODED,
        'uri' => 'urn:efectosfiscales',
        'location' => $location,
    ]);
} catch (Throwable $e) {
    stderr('No se pudo abrir el WSDL: ' . $e->getMessage());
    exit(1);
}

stdout('----------------------------------------');
stdout('Intento único: Firma WSDL (username, password)');

try {
    $response = $client->__soapCall('wsGetCredit', [$config['api_user'], $config['api_password']]);
    $lastRequest = (string) $client->__getLastRequest();
    $lastResponse = (string) $client->__getLastResponse();

    stdout('Resultado bruto:');
    ob_start();
    var_dump($response);
    stdout(trim((string) ob_get_clean()));
    stdout('');
    stdout('SOAP Request:');
    stdout($lastRequest);
    stdout('');
    stdout('SOAP Response:');
    stdout($lastResponse);
    stdout('');
} catch (Throwable $e) {
    $lastRequest = '';
    $lastResponse = '';

    try {
        $lastRequest = (string) $client->__getLastRequest();
        $lastResponse = (string) $client->__getLastResponse();
    } catch (Throwable) {
    }

    stderr('Excepción: ' . $e->getMessage());
    if ($lastRequest !== '') {
        stdout('');
        stdout('SOAP Request:');
        stdout($lastRequest);
    }
    if ($lastResponse !== '') {
        stdout('');
        stdout('SOAP Response:');
        stdout($lastResponse);
    }
    stdout('');
}

stdout('========================================');
stdout('Resultado normalizado de la app:');
try {
    $normalized = EfectosFiscalesService::wsGetCredit($config);
    ob_start();
    var_export($normalized);
    stdout(trim((string) ob_get_clean()));
    stdout('');
} catch (Throwable $e) {
    stderr('Falló EfectosFiscalesService::wsGetCredit(): ' . $e->getMessage());
    exit(1);
}

exit(0);
