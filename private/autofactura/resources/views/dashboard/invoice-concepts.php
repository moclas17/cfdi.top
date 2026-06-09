<?php
$title = 'Conceptos - AutoFactura';
$pageTitle = 'Conceptos';
$activeMenu = 'concepts';
ob_start();

$renderTaxBreakdown = static function (float $amount, string $taxObject, string $taxType, float $taxRate): string {
    $subtotal = $amount;
    $taxAmount = ($taxObject === '02' && $taxRate > 0) ? round($amount * $taxRate, 2) : 0.0;
    $total = $subtotal + $taxAmount;

    $parts = ['Subtotal: ' . format_money($subtotal)];
    if ($taxAmount > 0) {
        $parts[] = $taxType . ': ' . format_money($taxAmount);
    }
    $parts[] = 'Total: ' . format_money($total);

    return implode(' | ', $parts);
};
?>

<style>
    @media (max-width: 767.98px) {
        .concepts-mobile-list {
            display: grid;
            gap: 0.85rem;
            padding: 0.85rem;
        }

        .concept-mobile-card {
            border: 1px solid var(--af-border);
            border-radius: 12px;
            background: var(--af-surface);
            padding: 0.95rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }

        .concept-mobile-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .concept-mobile-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--af-text);
            line-height: 1.35;
        }

        .concept-mobile-amount {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--af-text);
            white-space: nowrap;
        }

        .concept-mobile-description {
            font-size: 0.82rem;
            color: var(--af-text-muted);
            margin-top: 0.15rem;
        }

        .concept-mobile-meta {
            display: grid;
            gap: 0.55rem;
            margin-bottom: 0.8rem;
        }

        .concept-mobile-line {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .concept-mobile-label {
            font-size: 0.74rem;
            color: var(--af-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .concept-mobile-value {
            font-size: 0.9rem;
            color: var(--af-text);
            word-break: break-word;
        }

        .concept-mobile-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .concept-mobile-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            border: 1px solid var(--af-border);
            background: var(--af-surface-2);
            color: var(--af-text);
        }

        .concept-mobile-badge.is-default {
            border-color: rgba(37, 99, 235, 0.24);
            background: rgba(37, 99, 235, 0.08);
            color: #2563eb;
        }

        .concept-mobile-badge.is-active {
            border-color: rgba(22, 163, 74, 0.24);
            background: rgba(22, 163, 74, 0.08);
            color: #16a34a;
        }

        .concept-mobile-badge.is-inactive {
            border-color: rgba(107, 114, 128, 0.24);
            background: rgba(107, 114, 128, 0.08);
            color: #6b7280;
        }

        .concept-mobile-actions {
            display: grid;
            gap: 0.45rem;
        }

        .concept-mobile-actions-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.45rem;
        }

        .concept-mobile-actions .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            width: 100%;
        }

        .concept-mobile-actions form {
            display: flex;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Conceptos</h5>
    <button class="btn btn-af" data-bs-toggle="modal" data-bs-target="#newConceptModal">
        <i class="bi bi-plus-lg me-1"></i> Nuevo
    </button>
</div>

<!-- Lista de conceptos -->
<div class="card-af">
    <div class="card-body p-0">
        <?php if (empty($concepts)): ?>
            <div class="text-center py-5">
                <i class="bi bi-clipboard-x text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No hay conceptos registrados.</p>
            </div>
        <?php else: ?>
            <div class="d-none d-md-block table-responsive">
                <table class="table table-af mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Clave Producto</th>
                            <th>Clave Unidad</th>
                            <th>Impuesto</th>
                            <th>Tasa</th>
                            <th>Importe Sugerido</th>
                            <th>Default</th>
                            <th>Activo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($concepts as $c): ?>
                        <tr>
                            <td>
                                <strong><?= e($c['name']) ?></strong>
                                <?php if ($c['description']): ?>
                                    <br><small class="text-muted"><?= e($c['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($c['sat_product_key']) ?></code></td>
                            <td><code><?= e($c['sat_unit_key']) ?></code> (<?= e($c['unit_name']) ?>)</td>
                            <td><?= e($c['tax_type']) ?></td>
                            <td><?= number_format($c['tax_rate'] * 100, 0) ?>%</td>
                            <td>
                                <strong><?= format_money((float) ($c['default_amount'] ?? 0)) ?></strong>
                                <?php if ((float) ($c['default_amount'] ?? 0) > 0): ?>
                                    <br><small class="text-muted"><?= e($renderTaxBreakdown((float) ($c['default_amount'] ?? 0), (string) ($c['tax_object'] ?? '02'), (string) ($c['tax_type'] ?? 'IVA'), (float) ($c['tax_rate'] ?? 0))) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="<?= url('invoice-concepts/toggle-default') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm <?= $c['is_default'] ? 'btn-primary' : 'btn-outline-primary' ?>"
                                            title="<?= $c['is_default'] ? 'Quitar como concepto por defecto' : 'Marcar como concepto por defecto' ?>">
                                        <?= $c['is_default'] ? 'Sí' : 'Marcar' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="<?= url('invoice-concepts/toggle-active') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm <?= $c['is_active'] ? 'btn-outline-success' : 'btn-outline-secondary' ?>"
                                            title="<?= $c['is_active'] ? 'Desactivar concepto' : 'Activar concepto' ?>">
                                        <?= $c['is_active'] ? 'Activo' : 'Inactivo' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="<?= url('invoice-concepts/delete') ?>"
                                      class="d-inline" onsubmit="return confirm('¿Eliminar este concepto?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-md-none concepts-mobile-list">
                <?php foreach ($concepts as $c): ?>
                    <div class="concept-mobile-card">
                        <div class="concept-mobile-top">
                            <div>
                                <div class="concept-mobile-name"><?= e($c['name']) ?></div>
                                <?php if (!empty($c['description'])): ?>
                                    <div class="concept-mobile-description"><?= e($c['description']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="concept-mobile-amount"><?= format_money((float) ($c['default_amount'] ?? 0)) ?></div>
                        </div>

                        <div class="concept-mobile-meta">
                            <div class="concept-mobile-line">
                                <div class="concept-mobile-label">Claves SAT</div>
                                <div class="concept-mobile-value">
                                    <?= e($c['sat_product_key']) ?> / <?= e($c['sat_unit_key']) ?> (<?= e($c['unit_name']) ?>)
                                </div>
                            </div>
                            <div class="concept-mobile-line">
                                <div class="concept-mobile-label">Impuesto</div>
                                <div class="concept-mobile-value">
                                    <?= e($c['tax_type']) ?> <?= number_format($c['tax_rate'] * 100, 0) ?>%
                                </div>
                            </div>
                            <?php if ((float) ($c['default_amount'] ?? 0) > 0): ?>
                                <div class="concept-mobile-line">
                                    <div class="concept-mobile-label">Desglose estimado</div>
                                    <div class="concept-mobile-value">
                                        <?= e($renderTaxBreakdown((float) ($c['default_amount'] ?? 0), (string) ($c['tax_object'] ?? '02'), (string) ($c['tax_type'] ?? 'IVA'), (float) ($c['tax_rate'] ?? 0))) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="concept-mobile-line">
                                <div class="concept-mobile-label">Estado</div>
                                <div class="concept-mobile-badges">
                                    <span class="concept-mobile-badge <?= $c['is_default'] ? 'is-default' : '' ?>">
                                        <?= $c['is_default'] ? 'Por defecto' : 'No default' ?>
                                    </span>
                                    <span class="concept-mobile-badge <?= $c['is_active'] ? 'is-active' : 'is-inactive' ?>">
                                        <?= $c['is_active'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="concept-mobile-actions">
                            <div class="concept-mobile-actions-row">
                                <form method="POST" action="<?= url('invoice-concepts/toggle-default') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-af-outline"
                                            title="<?= $c['is_default'] ? 'Quitar como concepto por defecto' : 'Marcar como concepto por defecto' ?>">
                                        <i class="bi bi-star<?= $c['is_default'] ? '-fill' : '' ?>"></i>
                                        <?= $c['is_default'] ? 'Default' : 'Marcar' ?>
                                    </button>
                                </form>
                                <form method="POST" action="<?= url('invoice-concepts/toggle-active') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-af-outline"
                                            title="<?= $c['is_active'] ? 'Desactivar concepto' : 'Activar concepto' ?>">
                                        <i class="bi bi-toggle-<?= $c['is_active'] ? 'on' : 'off' ?>"></i>
                                        <?= $c['is_active'] ? 'Activo' : 'Activar' ?>
                                    </button>
                                </form>
                            </div>
                            <form method="POST" action="<?= url('invoice-concepts/delete') ?>"
                                  onsubmit="return confirm('¿Eliminar este concepto?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-af-outline-danger" title="Eliminar">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Nuevo Concepto -->
<div class="modal fade" id="newConceptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url('invoice-concepts') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Concepto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-af">Nombre *</label>
                            <input type="text" class="form-control form-control-af" name="name" required placeholder="Servicio General">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-af">Descripción</label>
                            <input type="text" class="form-control form-control-af" name="description" placeholder="Descripción del concepto">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-af">Clave Producto SAT *</label>
                            <input type="text" class="form-control form-control-af" name="sat_product_key" required placeholder="80101500">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-af">Clave Unidad SAT *</label>
                            <input type="text" class="form-control form-control-af" name="sat_unit_key" required placeholder="E48">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-af">Nombre Unidad</label>
                            <input type="text" class="form-control form-control-af" name="unit_name" value="Servicio" placeholder="Servicio">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-af">Objeto Impuesto</label>
                            <select class="form-select form-select-af" name="tax_object">
                                <option value="01">01 - No objeto de impuesto</option>
                                <option value="02" selected>02 - Sí objeto de impuesto</option>
                                <option value="03">03 - Sí objeto, no obligado al desglose</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-af">Tipo Impuesto</label>
                            <select class="form-select form-select-af" name="tax_type">
                                <option value="IVA" selected>IVA</option>
                                <option value="ISR">ISR</option>
                                <option value="IEPS">IEPS</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-af">Tasa</label>
                            <select class="form-select form-select-af" name="tax_rate">
                                <option value="0.16" selected>16%</option>
                                <option value="0.08">8%</option>
                                <option value="0.00">0%</option>
                            </select>
                        </div>
                        <?php if (!empty($supportsDefaultAmount)): ?>
                            <div class="col-md-4">
                                <label class="form-label-af">Importe sugerido (MXN)</label>
                                <input type="number" class="form-control form-control-af" name="default_amount" id="defaultAmountInput"
                                       min="0" step="0.01" value="0.00" placeholder="0.00">
                            </div>
                            <div class="col-12">
                                <div class="p-3 rounded border bg-light-subtle">
                                    <div class="small text-muted mb-1">Desglose estimado de la factura</div>
                                    <div id="amountBreakdownPreview" class="fw-semibold">
                                        Subtotal: <?= format_money(0) ?> | Total: <?= format_money(0) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="isDefault">
                                <label class="form-check-label" for="isDefault">
                                    Marcar como concepto por defecto
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-af-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-af">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateConceptBreakdown() {
    const amountInput = document.getElementById('defaultAmountInput');
    const taxObjectInput = document.querySelector('#newConceptModal [name="tax_object"]');
    const taxTypeInput = document.querySelector('#newConceptModal [name="tax_type"]');
    const taxRateInput = document.querySelector('#newConceptModal [name="tax_rate"]');
    const preview = document.getElementById('amountBreakdownPreview');

    if (!amountInput || !taxObjectInput || !taxTypeInput || !taxRateInput || !preview) {
        return;
    }

    const amount = parseFloat(amountInput.value || '0') || 0;
    const taxObject = taxObjectInput.value || '02';
    const taxType = taxTypeInput.value || 'IVA';
    const taxRate = parseFloat(taxRateInput.value || '0') || 0;
    const subtotal = amount;
    const taxAmount = (taxObject === '02' && taxRate > 0) ? (amount * taxRate) : 0;
    const total = subtotal + taxAmount;

    const money = (value) => new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);

    const parts = [`Subtotal: ${money(subtotal)}`];
    if (taxAmount > 0) {
        parts.push(`${taxType}: ${money(taxAmount)}`);
    }
    parts.push(`Total: ${money(total)}`);
    preview.textContent = parts.join(' | ');
}

document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('defaultAmountInput');
    if (amountInput) {
        amountInput.addEventListener('input', updateConceptBreakdown);
    }

    ['tax_object', 'tax_type', 'tax_rate'].forEach(function(name) {
        const el = document.querySelector('#newConceptModal [name="' + name + '"]');
        if (el) {
            el.addEventListener('change', updateConceptBreakdown);
        }
    });

    updateConceptBreakdown();
});
</script>

<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
?>
