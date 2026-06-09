<?php
declare(strict_types=1);

/**
 * Script de diagnóstico para wsGetTicket
 *
 * Uso:
 *   php private/autofactura/scripts/test_ws_getticket.php --business=2 --xml-file=/ruta/al/cfdi.xml
 *   php private/autofactura/scripts/test_ws_getticket.php --url="https://efectosfiscales.mx/wscfditop/?wsdl" --user="usuario" --password="contrasena" --xml-file=/ruta/al/cfdi.xml
 *   php private/autofactura/scripts/test_ws_getticket.php --business=2 --xml-base64="PD94bWw..."
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
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Models/BaseModel.php';
require_once APP_PATH . '/Models/BusinessSetting.php';

function stdout_ticket(string $text = ''): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function stderr_ticket(string $text = ''): void
{
    fwrite(STDERR, $text . PHP_EOL);
}

function mask_secret_ticket(string $value): string
{
    $length = strlen($value);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
}

function usage_ticket(): void
{
    stdout_ticket('Uso:');
    stdout_ticket('  php private/autofactura/scripts/test_ws_getticket.php --business=2 --xml-file=/ruta/al/cfdi.xml');
    stdout_ticket('  php private/autofactura/scripts/test_ws_getticket.php --url="https://efectosfiscales.mx/wscfditop/?wsdl" --user="usuario" --password="contrasena" --xml-file=/ruta/al/cfdi.xml');
    stdout_ticket('  php private/autofactura/scripts/test_ws_getticket.php --business=2 --xml-base64="PD94bWw..."');
    stdout_ticket('');
    stdout_ticket('Opciones:');
    stdout_ticket('  --business=ID       Toma api_url/api_user/api_password desde business_settings');
    stdout_ticket('  --url=URL           URL del webservice o WSDL');
    stdout_ticket('  --user=USER         Usuario');
    stdout_ticket('  --password=PASS     Contraseña');
    stdout_ticket('  --xml-file=PATH     Ruta a un XML ya firmado para enviarlo al wsGetTicket');
    stdout_ticket('  --xml-base64=TEXT   XML ya codificado en base64');
    stdout_ticket('  --help              Mostrar esta ayuda');
}

function resolve_wsdl_location_ticket(string $wsdlUrl): string
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
    'xml-file:',
    'xml-base64:',
    'help',
]);

if (isset($options['help'])) {
    usage_ticket();
    exit(0);
}

$config = [
    'api_url' => '',
    'api_user' => '',
    'api_password' => '',
];

if (!empty($options['business'])) {
    $businessId = (int) $options['business'];
    $runtime = BusinessSetting::getRuntimeByBusiness($businessId);

    if (!$runtime) {
        stderr_ticket('No se encontró configuración para business_id=' . $businessId);
        exit(1);
    }

    $config['api_url'] = trim((string) ($runtime['api_url'] ?? ''));
    $config['api_user'] = trim((string) ($runtime['api_user'] ?? ''));
    $config['api_password'] = trim((string) ($runtime['api_password'] ?? ''));
} else {
    $config['api_url'] = trim((string) ($options['url'] ?? env('EF_DEFAULT_API_URL', '')));
    $config['api_user'] = trim((string) ($options['user'] ?? ''));
    $config['api_password'] = trim((string) ($options['password'] ?? ''));
}

$xmlBase64 = trim((string) ($options['xml-base64'] ?? ''));
if ($xmlBase64 === '' && !empty($options['xml-file'])) {
    $xmlFile = (string) $options['xml-file'];
    if (!is_file($xmlFile)) {
        stderr_ticket('No existe el archivo XML: ' . $xmlFile);
        exit(1);
    }

    $xmlContents = @file_get_contents($xmlFile);
    if (!is_string($xmlContents) || trim($xmlContents) === '') {
        stderr_ticket('No se pudo leer el XML: ' . $xmlFile);
        exit(1);
    }

    $xmlBase64 = base64_encode($xmlContents);
}

if ($config['api_url'] === '' || $config['api_user'] === '' || $config['api_password'] === '' || $xmlBase64 === '') {
    stderr_ticket('Faltan datos. Necesitas URL, usuario, contraseña y XML.');
    usage_ticket();
    exit(1);
}

$wsdlUrl = str_contains(strtolower($config['api_url']), '?wsdl')
    ? $config['api_url']
    : rtrim($config['api_url'], '?') . '?wsdl';
$location = resolve_wsdl_location_ticket($wsdlUrl);

stdout_ticket('== Diagnóstico wsGetTicket ==');
stdout_ticket('Fecha:         ' . date('Y-m-d H:i:s'));
stdout_ticket('WSDL:          ' . $wsdlUrl);
stdout_ticket('Location:      ' . $location);
stdout_ticket('Usuario:       ' . $config['api_user']);
stdout_ticket('Contraseña:    ' . mask_secret_ticket($config['api_password']));
stdout_ticket('XML base64:    ' . substr($xmlBase64, 0, 80) . '...');
stdout_ticket('');

if (!class_exists('SoapClient')) {
    stderr_ticket('SOAP no está disponible en este entorno PHP.');
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
    stderr_ticket('No se pudo abrir el WSDL: ' . $e->getMessage());
    exit(1);
}

try {
    stdout_ticket('== Paso 1: wsGetCredit ==');
    $creditResponse = $client->__soapCall('wsGetCredit', [
        $config['api_user'],
        $config['api_password'],
    ]);

    $creditRequest = (string) $client->__getLastRequest();
    $creditSoapResponse = (string) $client->__getLastResponse();

    stdout_ticket('Resultado bruto wsGetCredit:');
    ob_start();
    var_dump($creditResponse);
    stdout_ticket(trim((string) ob_get_clean()));
    stdout_ticket('');
    stdout_ticket('SOAP Request wsGetCredit:');
    stdout_ticket($creditRequest);
    stdout_ticket('');
    stdout_ticket('SOAP Response wsGetCredit:');
    stdout_ticket($creditSoapResponse);
    stdout_ticket('');

    stdout_ticket('== Paso 2: wsGetTicket ==');
    $response = $client->__soapCall('wsGetTicket', [
        $config['api_user'],
        $config['api_password'],
        $xmlBase64,
    ]);

    $lastRequest = (string) $client->__getLastRequest();
    $lastResponse = (string) $client->__getLastResponse();

    stdout_ticket('Resultado bruto:');
    ob_start();
    var_dump($response);
    stdout_ticket(trim((string) ob_get_clean()));
    stdout_ticket('');
    stdout_ticket('SOAP Request:');
    stdout_ticket($lastRequest);
    stdout_ticket('');
    stdout_ticket('SOAP Response:');
    stdout_ticket($lastResponse);
    stdout_ticket('');
} catch (Throwable $e) {
    $lastRequest = '';
    $lastResponse = '';

    try {
        $lastRequest = (string) $client->__getLastRequest();
        $lastResponse = (string) $client->__getLastResponse();
    } catch (Throwable) {
    }

    stderr_ticket('Excepción: ' . $e->getMessage());
    if ($lastRequest !== '') {
        stdout_ticket('');
        stdout_ticket('SOAP Request:');
        stdout_ticket($lastRequest);
    }
    if ($lastResponse !== '') {
        stdout_ticket('');
        stdout_ticket('SOAP Response:');
        stdout_ticket($lastResponse);
    }
    stdout_ticket('');
    exit(1);
}

exit(0);
