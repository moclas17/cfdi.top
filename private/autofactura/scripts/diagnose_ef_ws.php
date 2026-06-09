<?php
declare(strict_types=1);

/**
 * Diagnóstico integral de EfectosFiscales:
 * 1. Carga credenciales desde business_settings o parámetros manuales
 * 2. Genera un XML CFDI firmado de prueba
 * 3. Ejecuta wsGetCredit
 * 4. Ejecuta wsGetTicket
 * 5. Imprime requests y responses SOAP
 *
 * Uso:
 *   php private/autofactura/scripts/diagnose_ef_ws.php --business=2
 *   php private/autofactura/scripts/diagnose_ef_ws.php --business=2 --rfc=XAXX010101000 --name="PUBLICO EN GENERAL"
 *   php private/autofactura/scripts/diagnose_ef_ws.php --url="https://efectosfiscales.mx/wscfditop/?wsdl" --user="usuario" --password="contrasena" --xml-file=/ruta/al/xml.xml
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
require_once APP_PATH . '/Models/Business.php';
require_once APP_PATH . '/Models/BusinessSetting.php';

function diag_out(string $text = ''): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function diag_err(string $text = ''): void
{
    fwrite(STDERR, $text . PHP_EOL);
}

function diag_mask(string $value): string
{
    $length = strlen($value);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
}

function diag_usage(): void
{
    diag_out('Uso:');
    diag_out('  php private/autofactura/scripts/diagnose_ef_ws.php --business=2');
    diag_out('  php private/autofactura/scripts/diagnose_ef_ws.php --business=2 --output=/tmp/prueba.xml');
    diag_out('  php private/autofactura/scripts/diagnose_ef_ws.php --url="https://efectosfiscales.mx/wscfditop/?wsdl" --user="usuario" --password="contrasena" --xml-file=/ruta/al/xml.xml');
    diag_out('');
    diag_out('Opciones:');
    diag_out('  --business=ID        Toma datos desde business_settings');
    diag_out('  --url=URL            URL del webservice o WSDL');
    diag_out('  --user=USER          Usuario del webservice');
    diag_out('  --password=PASS      Contraseña del webservice');
    diag_out('  --xml-file=PATH      Usa un XML ya existente en vez de generarlo');
    diag_out('  --output=PATH        Ruta de salida para el XML generado');
    diag_out('  --amount=NUM         Importe antes de IVA. Default: 100.00');
    diag_out('  --concept=TEXT       Concepto. Default: PRUEBA TIMBRADO');
    diag_out('  --rfc=TEXT           RFC receptor. Default: XAXX010101000');
    diag_out('  --name=TEXT          Nombre receptor. Default: PUBLICO EN GENERAL');
    diag_out('  --zip=TEXT           CP receptor. Default: CP del emisor');
    diag_out('  --regimen=TEXT       Régimen receptor. Default: 616');
    diag_out('  --uso=TEXT           Uso CFDI. Default: S01');
    diag_out('  --request-id=NUM     Folio/request_id. Default: timestamp');
    diag_out('  --help               Mostrar esta ayuda');
}

function diag_resolve_wsdl_location(string $wsdlUrl): string
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

function diag_generate_xml(
    int $businessId,
    array $settings,
    array $business,
    array $csdCredentials,
    array $options
): array {
    $requestId = (string) ($options['request-id'] ?? (string) time());
    $issuedAt = (new DateTime('now', new DateTimeZone(env('APP_TIMEZONE', 'America/Mexico_City'))))
        ->format('Y-m-d\TH:i:s');
    $uuid = strtoupper(sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    ));

    $invoiceData = [
        'request_id' => $requestId,
        'amount' => (float) ($options['amount'] ?? 100.00),
        'concepto' => (string) ($options['concept'] ?? 'PRUEBA TIMBRADO'),
        'emisor_rfc' => (string) ($settings['rfc_emisor'] ?? ''),
        'emisor_nombre' => (string) ($settings['nombre_emisor'] ?? ($business['name'] ?? 'AutoFactura')),
        'emisor_regimen_fiscal' => (string) ($settings['regimen_fiscal'] ?? '601'),
        'emisor_cp' => (string) ($settings['codigo_postal'] ?? ''),
        'receptor_rfc' => strtoupper(trim((string) ($options['rfc'] ?? 'XAXX010101000'))),
        'receptor_nombre' => strtoupper(trim((string) ($options['name'] ?? 'PUBLICO EN GENERAL'))),
        'receptor_cp' => trim((string) ($options['zip'] ?? ($settings['codigo_postal'] ?? '00000'))),
        'receptor_regimen_fiscal' => (string) ($options['regimen'] ?? '616'),
        'receptor_uso_cfdi' => (string) ($options['uso'] ?? 'S01'),
        'sat_product_key' => '81112100',
        'sat_unit_key' => 'E48',
        'unit_name' => 'Servicio',
        'tax_object' => '02',
        'tax_rate' => 0.16,
    ];

    $method = new ReflectionMethod(EfectosFiscalesService::class, 'buildInvoiceXml');
    $method->setAccessible(true);
    $xml = (string) $method->invoke(null, $uuid, $invoiceData, $issuedAt, $csdCredentials);

    $outputPath = trim((string) ($options['output'] ?? ''));
    if ($outputPath === '') {
        $safeRfc = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($settings['rfc_emisor'] ?? 'SINRFC')));
        $outputPath = STORAGE_PATH . '/private/runtime/diagnose_cfdi_' . $businessId . '_' . $safeRfc . '.xml';
    }

    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir) && !@mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
        throw new RuntimeException('No se pudo crear el directorio de salida: ' . $outputDir);
    }

    if (@file_put_contents($outputPath, $xml) === false) {
        throw new RuntimeException('No se pudo escribir el XML en: ' . $outputPath);
    }

    return [
        'uuid' => $uuid,
        'request_id' => $requestId,
        'xml' => $xml,
        'xml_base64' => base64_encode($xml),
        'output' => $outputPath,
    ];
}

$options = getopt('', [
    'business:',
    'url:',
    'user:',
    'password:',
    'xml-file:',
    'output:',
    'amount:',
    'concept:',
    'rfc:',
    'name:',
    'zip:',
    'regimen:',
    'uso:',
    'request-id:',
    'help',
]);

if (isset($options['help'])) {
    diag_usage();
    exit(0);
}

$config = [
    'api_url' => '',
    'api_user' => '',
    'api_password' => '',
];
$business = null;
$settings = null;
$csdCredentials = null;
$businessId = 0;

if (!empty($options['business'])) {
    $businessId = (int) $options['business'];
    $business = Business::find($businessId);
    $settings = BusinessSetting::getRuntimeByBusiness($businessId);
    $csdCredentials = BusinessSetting::getCsdCredentials($businessId);

    if (!$business || !$settings) {
        diag_err('No se encontró configuración para business_id=' . $businessId);
        exit(1);
    }

    $config['api_url'] = trim((string) ($settings['api_url'] ?? ''));
    $config['api_user'] = trim((string) ($settings['api_user'] ?? ''));
    $config['api_password'] = trim((string) ($settings['api_password'] ?? ''));
} else {
    $config['api_url'] = trim((string) ($options['url'] ?? ''));
    $config['api_user'] = trim((string) ($options['user'] ?? ''));
    $config['api_password'] = trim((string) ($options['password'] ?? ''));
}

if ($config['api_url'] === '' || $config['api_user'] === '' || $config['api_password'] === '') {
    diag_err('Faltan URL, usuario o contraseña.');
    diag_usage();
    exit(1);
}

$xmlBase64 = '';
$xmlFile = trim((string) ($options['xml-file'] ?? ''));

if ($xmlFile !== '') {
    if (!is_file($xmlFile)) {
        diag_err('No existe el archivo XML: ' . $xmlFile);
        exit(1);
    }

    $xmlContents = @file_get_contents($xmlFile);
    if (!is_string($xmlContents) || trim($xmlContents) === '') {
        diag_err('No se pudo leer el XML: ' . $xmlFile);
        exit(1);
    }

    $xmlBase64 = base64_encode($xmlContents);
} else {
    if ($businessId <= 0 || !$settings || !$business || !$csdCredentials) {
        diag_err('Para generar el XML automáticamente necesitas usar --business=ID con CSD configurado.');
        exit(1);
    }

    try {
        $generated = diag_generate_xml($businessId, $settings, $business, $csdCredentials, $options);
        $xmlBase64 = $generated['xml_base64'];
        $xmlFile = $generated['output'];
    } catch (Throwable $e) {
        diag_err('No se pudo generar el XML de prueba: ' . $e->getMessage());
        exit(1);
    }
}

$wsdlUrl = str_contains(strtolower($config['api_url']), '?wsdl')
    ? $config['api_url']
    : rtrim($config['api_url'], '?') . '?wsdl';
$location = diag_resolve_wsdl_location($wsdlUrl);

diag_out('== Diagnóstico EfectosFiscales ==');
diag_out('Fecha:         ' . date('Y-m-d H:i:s'));
diag_out('Business ID:   ' . ($businessId > 0 ? (string) $businessId : 'manual'));
diag_out('WSDL:          ' . $wsdlUrl);
diag_out('Location:      ' . $location);
diag_out('Usuario:       ' . $config['api_user']);
diag_out('Contraseña:    ' . diag_mask($config['api_password']));
diag_out('XML file:      ' . $xmlFile);
diag_out('XML base64:    ' . substr($xmlBase64, 0, 80) . '...');
diag_out('');

if (!class_exists('SoapClient')) {
    diag_err('SOAP no está disponible en este entorno PHP.');
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
    diag_err('No se pudo abrir el WSDL: ' . $e->getMessage());
    exit(1);
}

try {
    diag_out('== Paso 1: wsGetCredit ==');
    $creditResponse = $client->__soapCall('wsGetCredit', [
        $config['api_user'],
        $config['api_password'],
    ]);

    diag_out('Resultado bruto wsGetCredit:');
    ob_start();
    var_dump($creditResponse);
    diag_out(trim((string) ob_get_clean()));
    diag_out('');
    diag_out('SOAP Request wsGetCredit:');
    diag_out((string) $client->__getLastRequest());
    diag_out('');
    diag_out('SOAP Response wsGetCredit:');
    diag_out((string) $client->__getLastResponse());
    diag_out('');

    diag_out('== Paso 2: wsGetTicket ==');
    $ticketResponse = $client->__soapCall('wsGetTicket', [
        $config['api_user'],
        $config['api_password'],
        $xmlBase64,
    ]);

    diag_out('Resultado bruto wsGetTicket:');
    ob_start();
    var_dump($ticketResponse);
    diag_out(trim((string) ob_get_clean()));
    diag_out('');
    diag_out('SOAP Request wsGetTicket:');
    diag_out((string) $client->__getLastRequest());
    diag_out('');
    diag_out('SOAP Response wsGetTicket:');
    diag_out((string) $client->__getLastResponse());
    diag_out('');
} catch (Throwable $e) {
    diag_err('Excepción: ' . $e->getMessage());
    diag_out('');
    diag_out('SOAP Request:');
    diag_out((string) $client->__getLastRequest());
    diag_out('');
    diag_out('SOAP Response:');
    diag_out((string) $client->__getLastResponse());
    exit(1);
}

exit(0);
