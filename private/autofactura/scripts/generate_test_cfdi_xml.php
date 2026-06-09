<?php
declare(strict_types=1);

/**
 * Genera un XML CFDI 4.0 firmado de prueba usando el mismo motor interno de la app.
 *
 * Uso:
 *   php private/autofactura/scripts/generate_test_cfdi_xml.php --business=2
 *   php private/autofactura/scripts/generate_test_cfdi_xml.php --business=2 --output=/tmp/prueba.xml
 *   php private/autofactura/scripts/generate_test_cfdi_xml.php --business=2 --rfc=XAXX010101000 --name="PUBLICO EN GENERAL"
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

function stdout_cfdi(string $text = ''): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function stderr_cfdi(string $text = ''): void
{
    fwrite(STDERR, $text . PHP_EOL);
}

function usage_cfdi(): void
{
    stdout_cfdi('Uso:');
    stdout_cfdi('  php private/autofactura/scripts/generate_test_cfdi_xml.php --business=2');
    stdout_cfdi('  php private/autofactura/scripts/generate_test_cfdi_xml.php --business=2 --output=/tmp/prueba.xml');
    stdout_cfdi('');
    stdout_cfdi('Opciones:');
    stdout_cfdi('  --business=ID        Business ID a usar');
    stdout_cfdi('  --output=PATH        Ruta de salida del XML');
    stdout_cfdi('  --amount=NUM         Importe antes de IVA. Default: 100.00');
    stdout_cfdi('  --concept=TEXT       Concepto. Default: PRUEBA TIMBRADO');
    stdout_cfdi('  --rfc=TEXT           RFC receptor. Default: XAXX010101000');
    stdout_cfdi('  --name=TEXT          Nombre receptor. Default: PUBLICO EN GENERAL');
    stdout_cfdi('  --zip=TEXT           CP receptor. Default: CP del emisor');
    stdout_cfdi('  --regimen=TEXT       Régimen receptor. Default: 616');
    stdout_cfdi('  --uso=TEXT           Uso CFDI. Default: S01');
    stdout_cfdi('  --request-id=NUM     Folio/request_id. Default: timestamp');
    stdout_cfdi('  --product-key=TEXT   ClaveProdServ. Default: 81112100');
    stdout_cfdi('  --unit-key=TEXT      ClaveUnidad. Default: E48');
    stdout_cfdi('  --unit-name=TEXT     Unidad. Default: Servicio');
    stdout_cfdi('  --tax-object=TEXT    ObjetoImp. Default: 02');
    stdout_cfdi('  --tax-rate=NUM       Tasa IVA. Default: 0.16');
    stdout_cfdi('  --help               Mostrar esta ayuda');
}

$options = getopt('', [
    'business:',
    'output:',
    'amount:',
    'concept:',
    'rfc:',
    'name:',
    'zip:',
    'regimen:',
    'uso:',
    'request-id:',
    'product-key:',
    'unit-key:',
    'unit-name:',
    'tax-object:',
    'tax-rate:',
    'help',
]);

if (isset($options['help']) || empty($options['business'])) {
    usage_cfdi();
    exit(isset($options['help']) ? 0 : 1);
}

$businessId = (int) $options['business'];
$business = Business::find($businessId);
$settings = BusinessSetting::getRuntimeByBusiness($businessId);
$csdCredentials = BusinessSetting::getCsdCredentials($businessId);

if (!$business || !$settings) {
    stderr_cfdi('No se encontró configuración para business_id=' . $businessId);
    exit(1);
}

if (!$csdCredentials) {
    stderr_cfdi('Ese negocio no tiene CSD completo para firmar el XML.');
    exit(1);
}

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
    'sat_product_key' => (string) ($options['product-key'] ?? '81112100'),
    'sat_unit_key' => (string) ($options['unit-key'] ?? 'E48'),
    'unit_name' => (string) ($options['unit-name'] ?? 'Servicio'),
    'tax_object' => (string) ($options['tax-object'] ?? '02'),
    'tax_rate' => (float) ($options['tax-rate'] ?? 0.16),
];

try {
    $method = new ReflectionMethod(EfectosFiscalesService::class, 'buildInvoiceXml');
    $method->setAccessible(true);
    $xml = (string) $method->invoke(null, $uuid, $invoiceData, $issuedAt, $csdCredentials);
} catch (Throwable $e) {
    stderr_cfdi('No se pudo generar el XML: ' . $e->getMessage());
    exit(1);
}

$outputPath = trim((string) ($options['output'] ?? ''));
if ($outputPath === '') {
    $safeRfc = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($settings['rfc_emisor'] ?? 'SINRFC')));
    $outputPath = STORAGE_PATH . '/private/runtime/test_cfdi_' . $businessId . '_' . $safeRfc . '.xml';
}

$outputDir = dirname($outputPath);
if (!is_dir($outputDir) && !@mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    stderr_cfdi('No se pudo crear el directorio de salida: ' . $outputDir);
    exit(1);
}

if (@file_put_contents($outputPath, $xml) === false) {
    stderr_cfdi('No se pudo escribir el XML en: ' . $outputPath);
    exit(1);
}

stdout_cfdi('XML generado correctamente.');
stdout_cfdi('Business ID: ' . $businessId);
stdout_cfdi('UUID:        ' . $uuid);
stdout_cfdi('Folio:       ' . $requestId);
stdout_cfdi('Emisor RFC:  ' . $invoiceData['emisor_rfc']);
stdout_cfdi('Receptor:    ' . $invoiceData['receptor_rfc'] . ' - ' . $invoiceData['receptor_nombre']);
stdout_cfdi('Salida:      ' . $outputPath);
