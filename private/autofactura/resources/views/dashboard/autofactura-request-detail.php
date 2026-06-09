<?php
$title = 'Detalle Solicitud #' . $request['id'] . ' - AutoFactura';
$pageTitle = 'Solicitud #' . $request['id'];
$activeMenu = 'requests';
ob_start();
?>

<div class="mb-3">
    <a href="<?= url('autofactura-requests') ?>" class="btn btn-sm btn-af-outline">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
</div>

<div class="row g-4">
    <!-- Info de la solicitud -->
    <div class="col-md-6">
        <div class="card-af">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-file-earmark-text me-2"></i>Datos de la Solicitud</h6>
                <table class="table table-sm table-borderless" style="color: var(--af-text);">
                    <tr><td class="text-muted" style="width: 40%;">Estatus</td><td><span class="badge-status badge-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span></td></tr>
                    <tr><td class="text-muted">Concepto</td><td><?= e($concept['name'] ?? 'N/A') ?></td></tr>
                    <tr><td class="text-muted">Importe</td><td><strong><?= format_money($request['amount']) ?></strong></td></tr>
                    <tr><td class="text-muted">Email</td><td><?= e($request['email'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Teléfono</td><td><?= e($request['phone'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Creado</td><td><?= format_date($request['created_at']) ?></td></tr>
                    <tr><td class="text-muted">Expira</td><td><?= $request['expires_at'] ? format_date($request['expires_at']) : '—' ?></td></tr>
                    <tr><td class="text-muted">UUID</td><td><code><?= e($request['invoice_uuid'] ?? '—') ?></code></td></tr>
                    <tr><td class="text-muted">Fecha factura</td><td><?= !empty($request['invoiced_at']) ? format_date($request['invoiced_at']) : '—' ?></td></tr>
                    <tr>
                        <td class="text-muted">XML / PDF</td>
                        <td>
                            <?php if (!empty($request['invoice_xml_url'])): ?>
                                <a href="<?= url(ltrim((string) $request['invoice_xml_url'], '/')) ?>" target="_blank" class="btn btn-sm btn-af-outline me-1">
                                    <i class="bi bi-filetype-xml"></i> XML
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($request['invoice_pdf_url'])): ?>
                                <a href="<?= url(ltrim((string) $request['invoice_pdf_url'], '/')) ?>" target="_blank" class="btn btn-sm btn-af-outline">
                                    <i class="bi bi-filetype-pdf"></i> PDF
                                </a>
                            <?php endif; ?>
                            <?php if (($request['status'] ?? '') === 'facturada' && !empty($request['invoice_uuid'])): ?>
                                <form method="POST" action="<?= url('autofactura-requests/regenerate-pdf') ?>" class="d-inline-block ms-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-af-outline">
                                        <i class="bi bi-arrow-repeat"></i> Regenerar PDF
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if (empty($request['invoice_xml_url']) && empty($request['invoice_pdf_url'])): ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Caducidad</td>
                        <td>
                            <?php if (!empty($request['expires_at'])): ?>
                                <?php
                                $now = new DateTime();
                                $expiresAt = new DateTime($request['expires_at']);
                                $diffSeconds = $expiresAt->getTimestamp() - $now->getTimestamp();
                                if ($diffSeconds <= 0) {
                                    $expiresText = 'Vencida';
                                } else {
                                    $remainingDays = (int) ceil($diffSeconds / 86400);
                                    $expiresText = $remainingDays === 1 ? 'Vence en 1 día' : "Vence en {$remainingDays} días";
                                }
                                ?>
                                <?= e($expiresText) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Link</td>
                        <td>
                            <code style="font-size: 0.75rem; word-break: break-all;"><?= url('f/' . $request['token']) ?></code>
                            <button class="btn btn-sm btn-af-outline ms-2" onclick="copyLink('<?= url('f/' . $request['token']) ?>')">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <?php if (!empty($request['email'])): ?>
                                <form method="POST" action="<?= url('autofactura-requests/resend-email') ?>" class="d-inline-block ms-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-af-outline">
                                        <i class="bi bi-envelope-arrow-up me-1"></i> Reenviar correo
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Datos del cliente -->
    <div class="col-md-6">
        <div class="card-af">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-person me-2"></i>Datos del Cliente</h6>
                <?php if ($customer): ?>
                    <table class="table table-sm table-borderless" style="color: var(--af-text);">
                        <tr><td class="text-muted" style="width: 40%;">RFC</td><td><code><?= e($customer['rfc']) ?></code></td></tr>
                        <tr><td class="text-muted">Razón Social</td><td><?= e($customer['razon_social']) ?></td></tr>
                        <tr><td class="text-muted">Código Postal</td><td><?= e($customer['codigo_postal']) ?></td></tr>
                        <tr><td class="text-muted">Régimen Fiscal</td><td><?= e($customer['regimen_fiscal']) ?></td></tr>
                        <tr><td class="text-muted">Uso CFDI</td><td><?= e($customer['uso_cfdi']) ?></td></tr>
                        <tr><td class="text-muted">Email</td><td><?= e($customer['email']) ?></td></tr>
                        <tr><td class="text-muted">EFOS</td><td><span class="badge bg-<?= $customer['efos_status'] === 'ok' ? 'success' : 'secondary' ?>"><?= e($customer['efos_status'] ?? 'N/A') ?></span></td></tr>
                    </table>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="bi bi-person-x text-muted" style="font-size: 2rem;"></i>
                        <p class="text-muted mt-2 mb-0">El cliente aún no ha capturado sus datos.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logs -->
    <div class="col-12">
        <div class="card-af">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-journal-text me-2"></i>Bitácora</h6>
                <?php if (empty($logs)): ?>
                    <p class="text-muted">Sin registros en la bitácora.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-af mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Acción</th>
                                    <th>Detalles</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><small><?= format_date($log['created_at']) ?></small></td>
                                    <td><code><?= e($log['action']) ?></code></td>
                                    <td><small><?= e($log['details'] ?? '—') ?></small></td>
                                    <td><small class="text-muted"><?= e($log['ip_address'] ?? '—') ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('Link copiado al portapapeles');
    });
}
</script>

<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
?>
