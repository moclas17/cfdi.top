<?php

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once CONFIG_PATH . '/env.php';
loadEnv(BASE_PATH . '/.env');

date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Mexico_City'));

require_once APP_PATH . '/Services/Database.php';
require_once APP_PATH . '/Services/EfectosFiscalesService.php';

$legacyDir = STORAGE_PATH . '/invoices';
$legacyXmlFiles = glob($legacyDir . '/*.xml') ?: [];

if (empty($legacyXmlFiles)) {
    echo "No legacy invoice XML files found.\n";
    exit(0);
}

$moved = 0;
$updated = 0;
$skipped = 0;

foreach ($legacyXmlFiles as $xmlFile) {
    $uuid = strtoupper((string) pathinfo($xmlFile, PATHINFO_FILENAME));
    $pdfFile = $legacyDir . '/' . $uuid . '.pdf';

    $xml = @file_get_contents($xmlFile);
    if (!is_string($xml) || trim($xml) === '') {
        echo "SKIP {$uuid}: XML unreadable.\n";
        $skipped++;
        continue;
    }

    $document = new DOMDocument();
    if (!@$document->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
        echo "SKIP {$uuid}: XML invalid.\n";
        $skipped++;
        continue;
    }

    $comprobante = $document->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Comprobante')->item(0);
    $emisor = $document->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Emisor')->item(0);

    $issuedAt = $comprobante?->getAttribute('Fecha') ?: date('Y-m-d\TH:i:s');
    $emisorRfc = $emisor?->getAttribute('Rfc') ?: 'SINRFC';

    $storage = EfectosFiscalesService::invoiceStoragePaths($emisorRfc, $issuedAt, $uuid);
    if (!is_dir($storage['dir']) && !@mkdir($storage['dir'], 0775, true) && !is_dir($storage['dir'])) {
        echo "SKIP {$uuid}: could not create {$storage['dir']}.\n";
        $skipped++;
        continue;
    }

    $targetXml = $storage['xml_file'];
    $targetPdf = $storage['pdf_file'];

    if (!@rename($xmlFile, $targetXml)) {
        echo "SKIP {$uuid}: could not move XML.\n";
        $skipped++;
        continue;
    }

    if (is_file($pdfFile)) {
        if (!@rename($pdfFile, $targetPdf)) {
            echo "WARN {$uuid}: XML moved but PDF could not be moved.\n";
        }
    }

    $moved++;

    $dbUpdated = Database::execute(
        "UPDATE `autofactura_requests`
         SET `invoice_xml_url` = :xml_url,
             `invoice_pdf_url` = :pdf_url
         WHERE `invoice_uuid` = :uuid",
        [
            'xml_url' => $storage['xml_url'],
            'pdf_url' => is_file($targetPdf) ? $storage['pdf_url'] : null,
            'uuid' => $uuid,
        ]
    );

    $updated += $dbUpdated;
    echo "OK {$uuid} -> {$storage['xml_url']}\n";
}

echo "Moved: {$moved}\n";
echo "DB rows updated: {$updated}\n";
echo "Skipped: {$skipped}\n";
