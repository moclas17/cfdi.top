<?php
$title = 'Timbres - AutoFactura';
$pageTitle = 'Timbres';
$activeMenu = 'stamps';
$purchaseStatusLabel = static function (string $status): string {
    return match ($status) {
        'paid' => 'Pagado',
        'failed' => 'Fallido',
        'cancelled' => 'Cancelado',
        default => 'Pendiente',
    };
};
$inventoryActionLabel = static function (string $action): string {
    return match ($action) {
        'superuser_stamp_topup' => 'Recarga manual',
        'user_stamp_transfer' => 'Transferencia a usuario',
        'stamp_purchase_paid' => 'Compra surtida',
        default => $action,
    };
};
ob_start();
?>

<style>
    .stamp-package-card {
        height: 100%;
        border: 1px solid var(--af-border);
        border-radius: 12px;
        background: var(--af-surface);
        padding: 1.15rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }

    .stamp-package-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--af-text);
        margin-bottom: 0.4rem;
    }

    .stamp-package-credits {
        font-size: 2rem;
        font-weight: 800;
        color: var(--af-primary);
        line-height: 1;
        margin-bottom: 0.35rem;
    }

    .stamp-package-price {
        font-size: 1rem;
        font-weight: 600;
        color: var(--af-text);
        margin-bottom: 1rem;
    }
</style>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card-af">
            <div class="card-body">
                <div class="card-title">Timbres Disponibles</div>
                <div class="card-value"><?= (int) $currentCredits ?></div>
                <div class="text-muted small mt-2">
                    <?php if (($currentCreditsSource ?? 'local') === 'ef'): ?>
                        Saldo sincronizado desde EfectosFiscales. Este saldo se descuenta automáticamente al timbrar una factura.
                    <?php else: ?>
                        Este saldo se descuenta automáticamente al timbrar una factura.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-af">
            <div class="card-body">
                <h5 class="mb-2"><i class="bi bi-info-circle me-2"></i>Cómo funciona</h5>
                <?php if ($isSuperuser): ?>
                    <p class="text-muted mb-1">Este saldo funciona como inventario principal de timbres. Las compras aprobadas de los negocios se descuentan de aquí.</p>
                    <p class="text-muted mb-0">Como superadmin, tú eres quien puede agregar timbres nuevos al sistema manualmente.</p>
                <?php else: ?>
                    <p class="text-muted mb-1">Compra un paquete, paga con Clip y cuando el pago se confirme acreditamos tus timbres automáticamente.</p>
                    <p class="text-muted mb-0">Cada CFDI timbrado consume 1 timbre.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($isSuperuser): ?>
    <div class="card-af mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <h5 class="mb-1"><i class="bi bi-plus-circle me-2"></i>Agregar timbres al superadmin</h5>
                    <p class="text-muted mb-0">Esta recarga alimenta el inventario desde donde se surten las compras de todos los negocios.</p>
                </div>
            </div>

            <form method="POST" action="<?= url('stamp-purchases/add-self-credits') ?>">
                <?= csrf_field() ?>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Timbres</label>
                        <input type="number" name="credits" class="form-control" min="1" step="1" placeholder="Ej. 500" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Notas (opcional)</label>
                        <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Ej. Recarga para surtir ventas de la semana">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-af">
                            <i class="bi bi-plus-lg me-1"></i> Agregar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-af mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <h5 class="mb-1"><i class="bi bi-arrow-left-right me-2"></i>Transferir timbres a usuarios</h5>
                    <p class="text-muted mb-0">Busca por nombre o correo y transfiere sin cargar una lista enorme de usuarios.</p>
                </div>
            </div>

            <form method="GET" action="<?= url('stamp-purchases') ?>" class="row g-3 align-items-end mb-4">
                <div class="col-md-8 col-lg-6">
                    <label class="form-label">Buscar usuario</label>
                    <input
                        type="text"
                        name="transfer_search"
                        class="form-control"
                        value="<?= e((string) ($transferSearch ?? '')) ?>"
                        placeholder="Nombre o correo"
                    >
                </div>
                <div class="col-md-4 col-lg-3">
                    <button type="submit" class="btn btn-af w-100">
                        <i class="bi bi-search me-1"></i> Buscar
                    </button>
                </div>
            </form>

            <?php if (empty($transferTargets)): ?>
                <div class="text-center py-4 text-muted">
                    <?= !empty($transferSearch) ? 'No se encontraron usuarios con ese criterio.' : 'Empieza escribiendo un nombre o correo para buscar usuarios.' ?>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small">
                        <?= !empty($transferSearch) ? 'Resultados para: ' . e($transferSearch) : 'Mostrando primeros 25 usuarios activos' ?>
                    </div>
                    <div class="text-muted small">Máximo 25 resultados por búsqueda</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-af align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Timbres actuales</th>
                                <th style="width: 240px;">Transferir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transferTargets as $target): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string) ($target['name'] ?? '')) ?></td>
                                    <td><?= e((string) ($target['email'] ?? '')) ?></td>
                                    <td><span class="badge text-bg-light border"><?= number_format((int) ($target['stamp_credits'] ?? 0)) ?> timbres</span></td>
                                    <td>
                                        <form method="POST" action="<?= url('stamp-purchases/transfer-credits') ?>" class="d-flex gap-2 align-items-center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="target_id" value="<?= (int) ($target['id'] ?? 0) ?>">
                                            <input
                                                type="number"
                                                name="credits"
                                                class="form-control"
                                                min="1"
                                                step="1"
                                                placeholder="Ej. 20"
                                                aria-label="Timbres a transferir a <?= e((string) ($target['name'] ?? '')) ?>"
                                                required
                                            >
                                            <button type="submit" class="btn btn-af text-nowrap">
                                                Transferir
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!$clipEnabled): ?>
    <div class="alert alert-warning">
        Clip aún no está configurado en el entorno. Agrega tus credenciales antes de habilitar compras en línea.
    </div>
<?php endif; ?>

<?php if (!$isSuperuser): ?>
<div class="row g-4 mb-4">
    <?php foreach ($packages as $package): ?>
        <div class="col-md-6 col-xl-3">
            <div class="stamp-package-card">
                <div class="stamp-package-title"><?= e($package['name']) ?></div>
                <div class="stamp-package-credits"><?= (int) $package['credits'] ?></div>
                <div class="text-muted small mb-2">timbres</div>
                <div class="small text-muted">Subtotal: <?= format_money((float) $package['subtotal']) ?></div>
                <div class="small text-muted">IVA 16%: <?= format_money((float) $package['iva']) ?></div>
                <div class="stamp-package-price mt-2">Total: <?= format_money((float) $package['total']) ?> MXN</div>

                <form method="POST" action="<?= url('stamp-purchases/checkout') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="package_key" value="<?= e((string) $package['key']) ?>">
                    <button type="submit" class="btn btn-af w-100" <?= !$clipEnabled ? 'disabled' : '' ?>>
                        <i class="bi bi-credit-card me-1"></i> Comprar
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card-af">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i><?= $isSuperuser ? 'Recargas recientes del superadmin' : 'Compras recientes' ?></h5>
            <span class="text-muted small">Total: <?= count($purchases) ?></span>
        </div>

        <?php if (empty($purchases)): ?>
            <div class="text-center py-4 text-muted"><?= $isSuperuser ? 'Aún no hay recargas manuales registradas.' : 'Aún no hay compras de timbres registradas.' ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-af mb-0">
                    <thead>
                        <tr>
                            <th>Paquete</th>
                            <th>Timbres</th>
                            <th>Monto</th>
                            <th>Estatus</th>
                            <th>Clip</th>
                            <th>Fecha</th>
                            <th>Factura</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><?= e($purchase['package_name']) ?></td>
                                <td><strong><?= (int) $purchase['credits'] ?></strong></td>
                                <td><?= format_money((float) $purchase['amount']) ?></td>
                                <td>
                                    <?php
                                    $status = (string) ($purchase['status'] ?? 'pending');
                                    $clipPaid = ClipService::isPaidStatus((string) ($purchase['clip_status'] ?? ''));
                                    $displayStatus = $status;
                                    $badgeClass = match ($status) {
                                        'paid' => 'badge-capturada',
                                        'failed', 'cancelled' => 'badge-error',
                                        default => 'badge-pendiente',
                                    };

                                    if ($status !== 'paid' && $clipPaid) {
                                        $displayStatus = 'Pagado';
                                        $badgeClass = 'badge-capturada';
                                    } else {
                                        $displayStatus = $purchaseStatusLabel($status);
                                    }
                                    ?>
                                    <span class="badge-status <?= $badgeClass ?>"><?= e($displayStatus) ?></span>
                                    <?php if ($status !== 'paid' && $clipPaid): ?>
                                        <div><small class="text-warning fw-semibold">Pago confirmado, pendiente de surtir</small></div>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= e((string) ($purchase['clip_status'] ?? '—')) ?></small></td>
                                <td><small><?= format_date((string) ($purchase['paid_at'] ?: $purchase['created_at'])) ?></small></td>
                                <td>
                                    <?php
                                    $isManualSuperadminTopUp = $isSuperuser
                                        && (
                                            strtolower((string) ($purchase['payment_method'] ?? '')) === 'manual'
                                            || trim((string) ($purchase['payment_request_id'] ?? '')) === ''
                                        );
                                    ?>

                                    <?php if ($isManualSuperadminTopUp): ?>
                                        <small class="text-muted">No aplica</small>
                                    <?php elseif (($purchase['status'] ?? '') === 'paid' && !empty($purchase['invoice_token'])): ?>
                                        <div class="d-flex flex-column gap-2">
                                            <a href="<?= url('f/' . $purchase['invoice_token']) ?>" target="_blank" class="btn btn-sm btn-af-outline">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> Facturar
                                            </a>
                                            <form method="POST" action="<?= url('stamp-purchases/resend-invoice-link') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="purchase_id" value="<?= (int) ($purchase['id'] ?? 0) ?>">
                                                <button type="submit" class="btn btn-sm btn-af-outline w-100">
                                                    <i class="bi bi-envelope-arrow-up me-1"></i> Reenviar
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif (($purchase['status'] ?? '') === 'paid'): ?>
                                        <form method="POST" action="<?= url('stamp-purchases/resend-invoice-link') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="purchase_id" value="<?= (int) ($purchase['id'] ?? 0) ?>">
                                            <button type="submit" class="btn btn-sm btn-af-outline w-100">
                                                <i class="bi bi-envelope-arrow-up me-1"></i> Generar / reenviar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <small class="text-muted">—</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isSuperuser): ?>
    <div class="card-af mt-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Bitácora de movimientos</h5>
                <span class="text-muted small">Total: <?= count($inventoryLogs) ?></span>
            </div>

            <?php if (empty($inventoryLogs)): ?>
                <div class="text-center py-4 text-muted">Aún no hay movimientos internos registrados.</div>
            <?php else: ?>
                <div class="table-responsive mb-4">
                    <table class="table table-af align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Movimiento</th>
                                <th>Detalle</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventoryLogs as $log): ?>
                                <tr>
                                    <td><span class="fw-semibold"><?= e($inventoryActionLabel((string) ($log['action'] ?? ''))) ?></span></td>
                                    <td><?= e((string) ($log['details'] ?? '—')) ?></td>
                                    <td><small><?= format_date((string) ($log['created_at'] ?? 'now')) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Links de pago generados</h5>
                <span class="text-muted small">Total: <?= count($allCheckoutOrders) ?></span>
            </div>

            <p class="text-muted small mb-3">Aquí ves todos los links creados para compra de timbres, quién los generó y cuáles realmente quedaron pagados.</p>

            <?php if (empty($allCheckoutOrders)): ?>
                <div class="text-center py-4 text-muted">Todavía no se han generado links de pago con Clip.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-af mb-0">
                        <thead>
                            <tr>
                                <th>Negocio</th>
                                <th>Paquete</th>
                                <th>Timbres</th>
                                <th>Importe</th>
                                <th>Estatus</th>
                                <th>Clip</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allCheckoutOrders as $order): ?>
                                <?php
                                $status = (string) ($order['status'] ?? 'pending');
                                $displayStatus = $purchaseStatusLabel($status);
                                $badgeClass = match ($status) {
                                    'paid' => 'badge-capturada',
                                    'failed', 'cancelled' => 'badge-error',
                                    default => 'badge-pendiente',
                                };
                                $linkUrl = trim((string) ($order['payment_request_url'] ?? ''));
                                $clipPaid = ClipService::isPaidStatus((string) ($order['clip_status'] ?? ''));
                                $needsManualTransfer = $clipPaid && $status !== 'paid';
                                if ($needsManualTransfer) {
                                    $displayStatus = 'Pagado';
                                    $badgeClass = 'badge-capturada';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($order['business_name'] ?? '')) ?></div>
                                        <small class="text-muted"><?= e((string) ($order['business_email'] ?? '')) ?></small>
                                    </td>
                                    <td><?= e((string) ($order['package_name'] ?? '')) ?></td>
                                    <td><strong><?= (int) ($order['credits'] ?? 0) ?></strong></td>
                                    <td><?= format_money((float) ($order['amount'] ?? 0)) ?></td>
                                    <td>
                                        <span class="badge-status <?= $badgeClass ?>"><?= e($displayStatus) ?></span>
                                        <?php if ($needsManualTransfer): ?>
                                            <div><small class="text-warning fw-semibold">Pago confirmado, pendiente de surtir</small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><small class="text-muted"><?= e((string) ($order['clip_status'] ?? '—')) ?></small></div>
                                        <div><small class="text-muted"><?= e((string) ($order['payment_request_id'] ?? '')) ?></small></div>
                                    </td>
                                    <td>
                                        <small><?= format_date((string) ($order['created_at'] ?? 'now')) ?></small>
                                        <?php if (!empty($order['paid_at'])): ?>
                                            <div><small class="text-success">Pagado: <?= format_date((string) $order['paid_at']) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-2">
                                            <?php if ($linkUrl !== ''): ?>
                                                <a href="<?= e($linkUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                    Abrir
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($needsManualTransfer): ?>
                                                <form method="POST" action="<?= url('stamp-purchases/execute-transfer') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="purchase_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning w-100">
                                                        Ejecutar transferencia
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($linkUrl === '' && !$needsManualTransfer): ?>
                                                <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
?>
