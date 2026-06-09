<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ya Facturada - AutoFactura</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f8; color: #373a3c; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .info-card { background: #fff; border: 1px solid #e8ebed; border-radius: 10px; padding: 2.5rem 2rem; text-align: center; max-width: 420px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .business-logo { max-height: 52px; max-width: 200px; border-radius: 8px; margin-bottom: 0.75rem; object-fit: contain; }
        .business-name { color: #6f7c8a; font-size: 0.9rem; margin-bottom: 1.2rem; }
        .info-icon { font-size: 3rem; color: #2ed8a3; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php
        $displayBusinessName = (string) (($settings['commercial_name'] ?? '') !== '' ? $settings['commercial_name'] : ($business['name'] ?? ''));
        $xmlDownloadUrl = !empty($request['invoice_xml_url']) ? url(ltrim((string) $request['invoice_xml_url'], '/')) : null;
        $pdfDownloadUrl = !empty($request['invoice_pdf_url']) ? url(ltrim((string) $request['invoice_pdf_url'], '/')) : null;
    ?>
    <div class="info-card">
        <?php if (has_flash('success')): ?>
            <div class="alert alert-success text-start mb-3">
                <i class="bi bi-check-circle-fill me-1"></i> <?= e(get_flash('success')) ?>
            </div>
        <?php endif; ?>
        <?php if (has_flash('error')): ?>
            <div class="alert alert-danger text-start mb-3">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e(get_flash('error')) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($settings['logo'])): ?>
            <img src="<?= url('storage/uploads/logos/' . e($settings['logo'])) ?>"
                 alt="<?= e($displayBusinessName) ?>"
                 class="business-logo">
        <?php endif; ?>
        <div class="business-name"><?= e($displayBusinessName) ?></div>
        <div class="info-icon"><i class="bi bi-check-circle"></i></div>
        <h4 style="font-weight: 700;">Factura Ya Generada</h4>
        <p class="text-muted mb-3" style="font-size: 0.85rem;">Esta solicitud ya fue procesada. Puedes descargar tus archivos aquí:</p>
        <?php if (!empty($request['invoice_uuid'])): ?>
            <p class="small text-muted mb-2">UUID: <strong><?= e($request['invoice_uuid']) ?></strong></p>
        <?php endif; ?>
        <div class="d-grid gap-2">
            <?php if (!empty($xmlDownloadUrl)): ?>
                <a href="<?= e($xmlDownloadUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-filetype-xml me-1"></i> Descargar XML
                </a>
            <?php endif; ?>
            <?php if (!empty($pdfDownloadUrl)): ?>
                <a href="<?= e($pdfDownloadUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-filetype-pdf me-1"></i> Descargar PDF
                </a>
            <?php endif; ?>
            <?php if (!empty($customer['email'])): ?>
                <form method="POST" action="<?= url('f/' . $request['token'] . '/send-email') ?>" class="d-grid">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-envelope me-1"></i> Enviar por correo
                    </button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                    <i class="bi bi-envelope me-1"></i> Enviar por correo
                </button>
            <?php endif; ?>
            <?php if (!empty($canSendWhatsapp)): ?>
                <form method="POST" action="<?= url('f/' . $request['token'] . '/send-whatsapp') ?>" class="d-grid">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-whatsapp me-1"></i> Enviar por WhatsApp
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
