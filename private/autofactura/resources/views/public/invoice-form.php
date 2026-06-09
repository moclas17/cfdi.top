<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $displayBusinessName = (string) (($settings['commercial_name'] ?? '') !== '' ? $settings['commercial_name'] : ($business['name'] ?? '')); ?>
    <title>Autofactura - <?= e($displayBusinessName) ?></title>
    <meta name="description" content="Captura tus datos fiscales para generar tu factura">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f8; color: #373a3c; min-height: 100vh; }

        .public-container { max-width: 600px; margin: 0 auto; padding: 2rem 1rem; }

        .public-card {
            background: #fff;
            border: 1px solid #e8ebed;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .public-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid #e8ebed;
        }

        .public-logo {
            max-height: 52px;
            max-width: 200px;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            object-fit: contain;
        }

        .public-header h2 {
            font-weight: 700;
            color: #373a3c;
            font-size: 1.4rem;
        }

        .amount-display {
            font-size: 1.75rem;
            font-weight: 700;
            color: #4099ff;
            text-align: center;
            margin: 0.75rem 0;
        }

        .info-badge {
            background: rgba(64, 153, 255, 0.08);
            color: #4099ff;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.8rem;
            text-align: center;
            font-weight: 500;
        }

        .form-label-pub { color: #919aa3; font-size: 0.8rem; font-weight: 500; margin-bottom: 0.3rem; }

        .form-control-pub, .form-select-pub {
            background: #fff;
            border: 1px solid #e8ebed;
            color: #373a3c;
            border-radius: 6px;
            padding: 0.5rem 0.85rem;
            font-size: 0.85rem;
        }

        .form-control-pub:focus, .form-select-pub:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.15);
        }

        .form-control-pub::placeholder { color: #c0c5ca; }

        .btn-submit {
            background: #4099ff;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 0.6rem;
            border-radius: 6px;
            font-size: 0.9rem;
            width: 100%;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            background: #2d7fe0;
            box-shadow: 0 2px 8px rgba(64, 153, 255, 0.35);
            color: #fff;
        }

        .alert { font-size: 0.85rem; border-radius: 6px; }
        .efos-alert { display: none; }

        .preview-qr {
            border: 1px dashed #cfd8e3;
            border-radius: 10px;
            padding: 0.9rem;
            background: #fbfdff;
            text-align: center;
            margin-bottom: 1rem;
        }

        .preview-qr #qrCode {
            display: inline-block;
            padding: 8px;
            background: #fff;
            border: 1px solid #e8ebed;
            border-radius: 8px;
        }

        .preview-qr-link {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #6f7c8a;
            word-break: break-all;
        }

        @media (max-width: 576px) {
            .public-container {
                padding: 0.75rem 0.5rem;
            }

            .public-card {
                padding: 1rem;
                border-radius: 8px;
            }

            .public-header {
                margin-bottom: 0.85rem;
                padding-bottom: 0.75rem;
            }

            .public-logo {
                max-height: 38px;
                max-width: 150px;
                margin-bottom: 0.45rem;
            }

            .public-header h2 {
                font-size: 1.1rem;
                margin-bottom: 0.15rem;
            }

            .public-header p {
                font-size: 0.8rem !important;
            }

            .info-badge {
                font-size: 0.72rem;
                padding: 0.3rem 0.5rem;
                margin-bottom: 0.4rem !important;
            }

            .amount-display {
                font-size: 1.35rem;
                margin: 0.45rem 0 0.75rem;
            }

            .row.g-3 {
                --bs-gutter-y: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="public-container">
        <div class="public-card">
            <?php if (!empty($_GET['preview']) && is_authenticated()): ?>
                <div class="alert alert-info">
                    <i class="bi bi-eye me-1"></i>
                    Vista previa del enlace público de factura.
                    <a href="<?= url('autofactura-requests') ?>" class="ms-2">Volver a facturas</a>
                </div>

                <div class="preview-qr">
                    <div class="mb-2 text-muted">
                        <i class="bi bi-qr-code-scan me-1"></i> Escanea para abrir esta factura
                    </div>
                    <div id="qrCode"></div>
                    <code class="preview-qr-link" id="qrTargetLink"><?= e(url('f/' . $token)) ?></code>
                </div>
            <?php endif; ?>

            <?php if (has_flash('success')): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= e(get_flash('success')) ?>
                </div>
            <?php endif; ?>
            <?php if (has_flash('info')): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-1"></i> <?= e(get_flash('info')) ?>
                </div>
            <?php endif; ?>
            <?php if (has_flash('error')): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e(get_flash('error')) ?>
                </div>
            <?php endif; ?>

            <div class="public-header">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?= url('storage/uploads/logos/' . e($settings['logo'])) ?>"
                         alt="<?= e($displayBusinessName) ?>"
                         class="public-logo">
                <?php endif; ?>
                <h2><?php if (empty($settings['logo'])): ?><i class="bi bi-receipt-cutoff me-2"></i><?php endif; ?>Autofactura</h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;"><?= e($displayBusinessName) ?></p>
            </div>

            <div class="info-badge mb-2">
                <i class="bi bi-tag me-1"></i> <?= e($concept['name'] ?? '') ?>
            </div>
            <div class="amount-display"><?= format_money($request['amount']) ?></div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('f/' . $token) ?>" id="invoiceForm">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-pub" for="rfc">RFC *</label>
                        <input type="text" class="form-control form-control-pub" id="rfc" name="rfc"
                               value="<?= e($old['rfc'] ?? '') ?>" required maxlength="13"
                               placeholder="XAXX010101000" style="text-transform: uppercase;">
                    </div>
                    <div class="col-12">
                        <?php
                        $efosClass = 'alert-warning';
                        if (!empty($efosCheck['severity']) && $efosCheck['severity'] === 'blocked') {
                            $efosClass = 'alert-danger';
                        }
                        ?>
                        <div id="efosAlert"
                             class="alert efos-alert <?= $efosClass ?>"
                             data-initial-visible="<?= !empty($efosCheck['found']) ? '1' : '0' ?>">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <span id="efosAlertMessage"><?= e((string) ($efosCheck['message'] ?? '')) ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-pub" for="razon_social">Razón Social *</label>
                        <input type="text" class="form-control form-control-pub" id="razon_social" name="razon_social"
                               value="<?= e($old['razon_social'] ?? '') ?>" required
                               placeholder="Mi Empresa S.A. de C.V." style="text-transform: uppercase;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-pub" for="codigo_postal">Código Postal *</label>
                        <input type="text" class="form-control form-control-pub" id="codigo_postal" name="codigo_postal"
                               value="<?= e($old['codigo_postal'] ?? '') ?>" required maxlength="5" placeholder="06600">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-pub" for="regimen_fiscal">Régimen Fiscal *</label>
                        <select class="form-select form-select-pub" id="regimen_fiscal" name="regimen_fiscal" required>
                            <option value="">Seleccionar...</option>
                            <?php
                            $regimenes = [
                                '601' => '601 - General de Ley PM',
                                '603' => '603 - PM con Fines no Lucrativos',
                                '605' => '605 - Sueldos y Salarios',
                                '606' => '606 - Arrendamiento',
                                '612' => '612 - PF con Act. Empresariales',
                                '616' => '616 - Sin obligaciones fiscales',
                                '621' => '621 - Incorporación Fiscal',
                                '625' => '625 - Plataformas Tecnológicas',
                                '626' => '626 - RESICO',
                            ];
                            foreach ($regimenes as $val => $label):
                            ?>
                                <option value="<?= $val ?>" <?= ($old['regimen_fiscal'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-pub" for="uso_cfdi">Uso de CFDI *</label>
                        <select class="form-select form-select-pub" id="uso_cfdi" name="uso_cfdi" required>
                            <option value="">Seleccionar...</option>
                            <?php
                            $usos = [
                                'G01' => 'G01 - Adquisición de mercancías',
                                'G02' => 'G02 - Devoluciones, descuentos',
                                'G03' => 'G03 - Gastos en general',
                                'I01' => 'I01 - Construcciones',
                                'I02' => 'I02 - Mobiliario y equipo',
                                'I03' => 'I03 - Equipo de transporte',
                                'I04' => 'I04 - Equipo de cómputo',
                                'I08' => 'I08 - Otra maquinaria',
                                'D01' => 'D01 - Honorarios médicos',
                                'D02' => 'D02 - Gastos médicos',
                                'D03' => 'D03 - Gastos funerales',
                                'D04' => 'D04 - Donativos',
                                'D10' => 'D10 - Servicios educativos',
                                'S01' => 'S01 - Sin efectos fiscales',
                                'CP01'=> 'CP01 - Pagos',
                            ];
                            foreach ($usos as $val => $label):
                            ?>
                                <option value="<?= $val ?>" <?= ($old['uso_cfdi'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-pub" for="email">Correo Electrónico *</label>
                        <input type="email" class="form-control form-control-pub" id="email" name="email"
                               value="<?= e($old['email'] ?? '') ?>" required placeholder="correo@ejemplo.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label-pub" for="phone">Teléfono (opcional)</label>
                        <input type="tel" class="form-control form-control-pub" id="phone" name="phone"
                               value="<?= e($old['phone'] ?? '') ?>" placeholder="55 1234 5678">
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-submit">
                            <i class="bi bi-send me-1"></i> Solicitar Factura
                        </button>
                    </div>
                </div>
            </form>

            <p class="text-center mt-3 mb-0" style="color: #c0c5ca; font-size: 0.75rem;">
                <i class="bi bi-shield-check me-1"></i> Tus datos están seguros y serán utilizados solo para generar tu factura.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const rfcInput = document.getElementById('rfc');
        const razonSocialInput = document.getElementById('razon_social');
        const codigoPostalInput = document.getElementById('codigo_postal');
        const regimenFiscalSelect = document.getElementById('regimen_fiscal');
        const usoCfdiSelect = document.getElementById('uso_cfdi');
        const invoiceForm = document.getElementById('invoiceForm');
        const efosAlert = document.getElementById('efosAlert');
        const efosAlertMessage = document.getElementById('efosAlertMessage');
        const publicGeneralRfc = 'XAXX010101000';
        const emitterZipCode = <?= json_encode((string) ($settings['codigo_postal'] ?? '')) ?>;
        const efosCheckUrlBase = <?= json_encode(url('f/' . $token . '/efos-check')) ?>;
        let efosTimer = null;
        let lastEfosCheckedRfc = '';

        function hideEfosAlert() {
            if (!efosAlert || !efosAlertMessage) {
                return;
            }
            efosAlert.style.display = 'none';
            efosAlert.classList.remove('alert-danger', 'alert-warning');
            efosAlertMessage.textContent = '';
        }

        function showEfosAlert(severity, message) {
            if (!efosAlert || !efosAlertMessage || !message) {
                return;
            }
            efosAlert.classList.remove('alert-danger', 'alert-warning');
            efosAlert.classList.add(severity === 'blocked' ? 'alert-danger' : 'alert-warning');
            efosAlertMessage.textContent = message;
            efosAlert.style.display = 'block';
        }

        function normalizeRfc(value) {
            return (value || '').trim().toUpperCase();
        }

        function shouldCheckEfos(rfc) {
            return rfc.length === 12 || rfc.length === 13;
        }

        function runEfosCheck() {
            const normalizedRfc = normalizeRfc(rfcInput.value);

            if (!shouldCheckEfos(normalizedRfc)) {
                lastEfosCheckedRfc = '';
                hideEfosAlert();
                return;
            }

            if (normalizedRfc === lastEfosCheckedRfc) {
                return;
            }

            fetch(efosCheckUrlBase + '?rfc=' + encodeURIComponent(normalizedRfc), {
                headers: { 'Accept': 'application/json' }
            })
                .then(response => response.ok ? response.json() : null)
                .then(data => {
                    if (!data || !data.success || !data.result || !data.result.found) {
                        hideEfosAlert();
                        lastEfosCheckedRfc = normalizedRfc;
                        return;
                    }

                    showEfosAlert(data.result.severity, data.result.message || '');
                    lastEfosCheckedRfc = normalizedRfc;
                })
                .catch(() => {
                    hideEfosAlert();
                });
        }

        function applyPublicoEnGeneralRules() {
            const normalizedRfc = (rfcInput.value || '').trim().toUpperCase();
            const isPublicoEnGeneral = normalizedRfc === publicGeneralRfc;

            if (isPublicoEnGeneral) {
                codigoPostalInput.value = emitterZipCode;
                codigoPostalInput.readOnly = true;
                regimenFiscalSelect.value = '616';
                regimenFiscalSelect.disabled = true;
                usoCfdiSelect.value = 'S01';
                usoCfdiSelect.disabled = true;
                return;
            }

            codigoPostalInput.readOnly = false;
            regimenFiscalSelect.disabled = false;
            usoCfdiSelect.disabled = false;
        }

        rfcInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            applyPublicoEnGeneralRules();
            clearTimeout(efosTimer);
            efosTimer = setTimeout(runEfosCheck, 450);
        });
        razonSocialInput.addEventListener('input', function() { this.value = this.value.toUpperCase(); });

        invoiceForm.addEventListener('submit', function() {
            regimenFiscalSelect.disabled = false;
            usoCfdiSelect.disabled = false;
        });

        applyPublicoEnGeneralRules();

        if (efosAlert && efosAlert.dataset.initialVisible === '1') {
            efosAlert.style.display = 'block';
        }

        runEfosCheck();

        (function initPreviewQr() {
            const qrContainer = document.getElementById('qrCode');
            const linkNode = document.getElementById('qrTargetLink');
            if (!qrContainer || !linkNode || typeof QRCode === 'undefined') {
                return;
            }

            const targetUrl = linkNode.textContent.trim();
            if (!targetUrl) {
                return;
            }

            new QRCode(qrContainer, {
                text: targetUrl,
                width: 150,
                height: 150,
                correctLevel: QRCode.CorrectLevel.M
            });
        })();
    </script>
</body>
</html>
