<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Exitosa - AutoFactura</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f8; color: #373a3c; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .success-card { background: #fff; border: 1px solid #e8ebed; border-radius: 10px; padding: 2.5rem 2rem; text-align: center; max-width: 460px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .success-icon { width: 64px; height: 64px; border-radius: 50%; background: #2ed8a3; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; }
        .success-icon i { font-size: 2rem; color: #fff; }
        .info-box { background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem; }
    </style>
</head>
<body>
    <div class="success-card">
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
        <div class="success-icon"><i class="bi bi-check-lg"></i></div>
        <h4 class="mb-2" style="font-weight: 700;">¡Factura Generada!</h4>
        <p class="text-muted mb-3" style="font-size: 0.85rem;">
            Tus datos fiscales fueron capturados y la factura se generó correctamente.
            También será enviada a <strong style="color: #373a3c;"><?= e($customer['email'] ?? '') ?></strong>.
        </p>
        <div class="info-box">
            <small class="text-muted">UUID</small>
            <div class="fw-bold" style="word-break: break-all; font-size: 0.9rem;"><?= e($invoice['uuid'] ?? $request['invoice_uuid'] ?? 'N/A') ?></div>
            <small class="text-muted mt-2 d-block">RFC</small>
            <div class="fw-bold"><?= e($customer['rfc'] ?? '') ?></div>
            <small class="text-muted mt-2 d-block">Importe</small>
            <div class="fw-bold" style="font-size: 1.35rem; color: #4099ff;"><?= format_money($request['amount']) ?></div>
        </div>
        <?php
            $xmlUrl = $invoice['xml_url'] ?? ($request['invoice_xml_url'] ?? null);
            $pdfUrl = $invoice['pdf_url'] ?? ($request['invoice_pdf_url'] ?? null);
            $xmlDownloadUrl = !empty($xmlUrl) ? url(ltrim((string) $xmlUrl, '/')) : null;
            $pdfDownloadUrl = !empty($pdfUrl) ? url(ltrim((string) $pdfUrl, '/')) : null;
            $shareLines = [];
            if (!empty($pdfDownloadUrl)) {
                $shareLines[] = 'PDF: ' . $pdfDownloadUrl;
            }
            if (!empty($xmlDownloadUrl)) {
                $shareLines[] = 'XML: ' . $xmlDownloadUrl;
            }
        ?>
        <?php if (!empty($xmlUrl) || !empty($pdfUrl)): ?>
            <div class="d-grid gap-2 mb-3">
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
        <?php endif; ?>

        <p style="color: #c0c5ca; font-size: 0.78rem;">
            <i class="bi bi-info-circle me-1"></i> Conserva esta página como comprobante.
        </p>
    </div>
</body>
</html>
