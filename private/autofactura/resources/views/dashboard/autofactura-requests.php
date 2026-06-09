<?php
$title = 'Facturas - AutoFactura';
$pageTitle = 'Facturas';
$activeMenu = 'requests';
$concepts = InvoiceConcept::getActiveByBusiness(auth_business_id());
$supportsCustomConceptText = AutofacturaRequest::supportsCustomConceptText();
ob_start();
?>

<style>
    @media (max-width: 767.98px) {
        .requests-mobile-list {
            display: grid;
            gap: 0.85rem;
            padding: 0.85rem;
        }

        .request-mobile-card {
            border: 1px solid var(--af-border);
            border-radius: 12px;
            background: var(--af-surface);
            padding: 0.95rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }

        .request-mobile-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.7rem;
        }

        .request-mobile-id {
            font-size: 0.78rem;
            color: var(--af-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.2rem;
        }

        .request-mobile-concept {
            font-size: 1rem;
            font-weight: 600;
            color: var(--af-text);
            line-height: 1.35;
        }

        .request-mobile-amount {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--af-text);
            white-space: nowrap;
        }

        .request-mobile-meta {
            display: grid;
            gap: 0.55rem;
            margin-bottom: 0.8rem;
        }

        .request-mobile-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
        }

        .request-mobile-line {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .request-mobile-label {
            font-size: 0.74rem;
            color: var(--af-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .request-mobile-value {
            font-size: 0.9rem;
            color: var(--af-text);
            word-break: break-word;
        }

        .request-mobile-actions {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .request-mobile-actions .btn {
            flex: 1 1 auto;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }

        .request-mobile-actions form {
            flex: 1 1 auto;
            display: flex;
        }

        .request-mobile-actions form .btn {
            width: 100%;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Facturas</h5>
    <button class="btn btn-af" data-bs-toggle="modal" data-bs-target="#newRequestModal">
        <i class="bi bi-plus-lg me-1"></i> Nueva Factura
    </button>
</div>

<!-- Lista de facturas -->
<div class="card-af">
    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No hay facturas aún.</p>
            </div>
        <?php else: ?>
            <div class="d-none d-md-block table-responsive">
                <table class="table table-af mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Concepto</th>
                            <th>Importe</th>
                            <th>Email / Teléfono</th>
                            <th>Estatus</th>
                            <th>Link</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>#<?= $req['id'] ?></td>
                            <td><?= e($req['concept_name'] ?? 'N/A') ?></td>
                            <td><strong><?= format_money($req['amount']) ?></strong></td>
                            <td>
                                <?php if ($req['email']): ?>
                                    <small><?= e($req['email']) ?></small><br>
                                <?php endif; ?>
                                <?php if ($req['phone']): ?>
                                    <small class="text-muted"><?= e($req['phone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-status badge-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-af-outline" onclick="copyLink('<?= url('f/' . $req['token']) ?>')" title="Copiar link">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                            </td>
                            <td><small><?= format_date($req['created_at']) ?></small></td>
                            <td>
                                <a href="<?= url('autofactura-requests/' . $req['id']) ?>" class="btn btn-sm btn-af-outline" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (in_array((string) ($req['status'] ?? ''), ['pendiente', 'error'], true)): ?>
                                    <form method="POST"
                                          action="<?= url('autofactura-requests/delete') ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Eliminar esta factura? Esta acción no se puede deshacer.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-af-outline-danger" title="Eliminar factura">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-md-none requests-mobile-list">
                <?php foreach ($requests as $req): ?>
                    <div class="request-mobile-card">
                        <div class="request-mobile-top">
                            <div>
                                <div class="request-mobile-id">Factura #<?= $req['id'] ?></div>
                                <div class="request-mobile-concept"><?= e($req['concept_name'] ?? 'N/A') ?></div>
                            </div>
                            <div class="request-mobile-amount"><?= format_money($req['amount']) ?></div>
                        </div>

                        <div class="request-mobile-meta">
                            <div class="request-mobile-line">
                                <div class="request-mobile-label">Contacto</div>
                                <div class="request-mobile-value">
                                    <?php if (!empty($req['email'])): ?>
                                        <?= e($req['email']) ?>
                                    <?php elseif (!empty($req['phone'])): ?>
                                        <?= e($req['phone']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin contacto</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="request-mobile-meta-grid">
                                <div class="request-mobile-line">
                                    <div class="request-mobile-label">Estatus</div>
                                    <div class="request-mobile-value">
                                        <span class="badge-status badge-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span>
                                    </div>
                                </div>
                                <div class="request-mobile-line">
                                    <div class="request-mobile-label">Fecha</div>
                                    <div class="request-mobile-value"><?= format_date($req['created_at']) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="request-mobile-actions">
                            <button class="btn btn-sm btn-af-outline" onclick="copyLink('<?= url('f/' . $req['token']) ?>')" title="Copiar link">
                                <i class="bi bi-link-45deg me-1"></i> Link
                            </button>
                            <a href="<?= url('autofactura-requests/' . $req['id']) ?>" class="btn btn-sm btn-af-outline" title="Ver detalle">
                                <i class="bi bi-eye me-1"></i> Ver
                            </a>
                            <?php if (in_array((string) ($req['status'] ?? ''), ['pendiente', 'error'], true)): ?>
                                <form method="POST"
                                      action="<?= url('autofactura-requests/delete') ?>"
                                      onsubmit="return confirm('¿Eliminar esta factura? Esta acción no se puede deshacer.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-af-outline-danger w-100" title="Eliminar factura">
                                        <i class="bi bi-trash me-1"></i> Eliminar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Nueva Factura -->
<div class="modal fade" id="newRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('autofactura-requests') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nueva Factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label-af mb-0">Concepto *</label>
                            <?php if ($supportsCustomConceptText): ?>
                                <button type="button"
                                        class="btn btn-sm btn-link p-0 text-decoration-none text-muted"
                                        id="toggleCustomConceptButton"
                                        title="Cambiar texto del concepto solo para esta factura"
                                        aria-label="Cambiar texto del concepto solo para esta factura">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <select class="form-select form-select-af" name="concept_id" id="concept_id" required onchange="applyConceptAmount()">
                            <option value="">Seleccionar concepto...</option>
                            <?php foreach ($concepts as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                        data-default-amount="<?= number_format((float) ($c['default_amount'] ?? 0), 2, '.', '') ?>"
                                        <?= $c['is_default'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?> (<?= e($c['sat_product_key']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($supportsCustomConceptText): ?>
                        <div class="mb-3 d-none" id="customConceptTextWrapper">
                            <label class="form-label-af">Texto del concepto para esta factura</label>
                            <input type="hidden" id="useCustomConceptText" name="use_custom_concept_text" value="0">
                            <input type="text"
                                   class="form-control form-control-af"
                                   id="customConceptText"
                                   name="custom_concept_text"
                                   maxlength="255"
                                   placeholder="Escribe aquí solo el texto especial de esta factura. Ejemplo: Consumo de alimentos del día 21 de marzo de 2026">
                            <small class="text-muted d-block mt-2">
                                Esto solo afecta esta factura.
                            </small>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label-af">Importe (MXN) *</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: var(--af-surface-2); border-color: var(--af-border); color: var(--af-text-muted);">$</span>
                            <input type="number" class="form-control form-control-af" id="amount" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-af">Email del cliente (opcional)</label>
                        <input type="email" class="form-control form-control-af" id="contactEmail" name="email" placeholder="cliente@ejemplo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-af">Teléfono del cliente (opcional)</label>
                        <input type="tel" class="form-control form-control-af" id="contactPhone" name="phone" placeholder="55 1234 5678">
                        <small class="text-muted d-block mt-2">
                            Si capturas teléfono, la factura se envía por WhatsApp automáticamente.
                        </small>
                        <small class="text-muted d-block mt-1" id="contactRuleHint">
                            Captura solo un medio de contacto: correo o teléfono.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-af-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-af">
                        <i class="bi bi-send me-1"></i> Crear Factura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function applyConceptAmount() {
    const select = document.getElementById('concept_id');
    const amountInput = document.getElementById('amount');
    if (!select || !amountInput) {
        return;
    }
    const option = select.options[select.selectedIndex];
    if (!option) {
        return;
    }
    const defaultAmount = parseFloat(option.getAttribute('data-default-amount') || '0');
    if (!isNaN(defaultAmount) && defaultAmount > 0) {
        amountInput.value = defaultAmount.toFixed(2);
    }
}

function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        // Mostrar toast o alert
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show" role="alert" style="background: var(--af-surface); border: 1px solid var(--af-border); color: var(--af-text);">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    Link copiado al portapapeles
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    });
}

document.addEventListener('DOMContentLoaded', applyConceptAmount);

function syncCustomConceptInput() {
    const hiddenFlag = document.getElementById('useCustomConceptText');
    const wrapper = document.getElementById('customConceptTextWrapper');
    const input = document.getElementById('customConceptText');
    const button = document.getElementById('toggleCustomConceptButton');

    if (!hiddenFlag || !wrapper || !input || !button) {
        return;
    }

    const isOpen = hiddenFlag.value === '1';
    wrapper.classList.toggle('d-none', !isOpen);
    button.classList.toggle('text-primary', isOpen);
    button.classList.toggle('text-muted', !isOpen);
    button.innerHTML = isOpen
        ? '<i class="bi bi-x-circle"></i>'
        : '<i class="bi bi-pencil-square"></i>';
    button.title = isOpen
        ? 'Usar el texto original del concepto'
        : 'Cambiar texto del concepto solo para esta factura';
    button.setAttribute('aria-label', button.title);
}

function syncContactInputs() {
    const emailInput = document.getElementById('contactEmail');
    const phoneInput = document.getElementById('contactPhone');
    const hint = document.getElementById('contactRuleHint');
    if (!emailInput || !phoneInput) {
        return;
    }

    const hasEmail = emailInput.value.trim() !== '';
    const hasPhone = phoneInput.value.trim() !== '';

    if (hasEmail) {
        phoneInput.value = '';
        phoneInput.disabled = true;
    } else {
        phoneInput.disabled = false;
    }

    if (hasPhone) {
        emailInput.value = '';
        emailInput.disabled = true;
    } else {
        emailInput.disabled = false;
    }

    if (hint) {
        if (hasEmail) {
            hint.textContent = 'Envío configurado por correo.';
        } else if (hasPhone) {
            hint.textContent = 'Envío configurado por WhatsApp.';
        } else {
            hint.textContent = 'Captura solo un medio de contacto: correo o teléfono.';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const emailInput = document.getElementById('contactEmail');
    const phoneInput = document.getElementById('contactPhone');
    const customConceptButton = document.getElementById('toggleCustomConceptButton');
    const customConceptFlag = document.getElementById('useCustomConceptText');
    if (emailInput) {
        emailInput.addEventListener('input', syncContactInputs);
    }
    if (phoneInput) {
        phoneInput.addEventListener('input', syncContactInputs);
    }
    if (customConceptButton && customConceptFlag) {
        customConceptButton.addEventListener('click', function() {
            customConceptFlag.value = customConceptFlag.value === '1' ? '0' : '1';
            if (customConceptFlag.value === '0') {
                const input = document.getElementById('customConceptText');
                if (input) {
                    input.value = '';
                }
            }
            syncCustomConceptInput();
        });
        syncCustomConceptInput();
    }
    syncContactInputs();
});
</script>

<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
?>
