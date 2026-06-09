<?php
$title = 'Dashboard - AutoFactura';
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
ob_start();
?>

<style>
    .onboarding-card {
        overflow: hidden;
    }

    .onboarding-progress {
        height: 10px;
        background: var(--af-surface-2);
        border-radius: 999px;
        overflow: hidden;
    }

    .onboarding-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--af-primary), #7b61ff);
        border-radius: 999px;
    }

    .onboarding-step {
        border: 1px solid var(--af-border);
        border-radius: 14px;
        background: var(--af-surface);
        padding: 1rem;
        height: 100%;
    }

    .onboarding-step.complete {
        border-color: rgba(46, 216, 163, 0.35);
        background: linear-gradient(180deg, rgba(46, 216, 163, 0.08), rgba(255,255,255,0.95));
    }

    .onboarding-step-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--af-primary-light);
        color: var(--af-primary);
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .onboarding-step.complete .onboarding-step-icon {
        background: rgba(46, 216, 163, 0.14);
        color: #14966f;
    }

    .onboarding-step-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--af-text);
    }

    .onboarding-step-text {
        color: var(--af-text-muted);
        font-size: 0.88rem;
        line-height: 1.45;
        margin-bottom: 0;
    }

    .onboarding-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        padding: 0.28rem 0.65rem;
        font-size: 0.74rem;
        font-weight: 600;
    }

    .onboarding-badge.pending {
        background: rgba(255, 182, 77, 0.16);
        color: #9a6400;
    }

    .onboarding-badge.complete {
        background: rgba(46, 216, 163, 0.14);
        color: #13805e;
    }

    @media (max-width: 767.98px) {
        .dashboard-stats-grid {
            --bs-gutter-x: 0.85rem;
            --bs-gutter-y: 0.85rem;
        }

        .dashboard-stats-grid > [class*="col-"] {
            width: 50%;
        }

        .dashboard-stats-grid .card-body {
            padding: 0.95rem;
            min-height: 124px;
            display: flex;
            align-items: stretch;
        }

        .dashboard-stats-grid .card-body > .d-flex {
            width: 100%;
        }

        .dashboard-stats-grid .card-title {
            font-size: 0.78rem;
            line-height: 1.2;
            min-height: 2.1em;
            display: flex;
            align-items: flex-start;
        }

        .dashboard-stats-grid .card-value {
            font-size: 2rem;
        }

        .dashboard-stats-grid .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }
    }

    @media (max-width: 767.98px) {
        .dashboard-mobile-list {
            display: grid;
            gap: 0.85rem;
        }

        .dashboard-mobile-card {
            border: 1px solid var(--af-border);
            border-radius: 12px;
            background: var(--af-surface);
            padding: 0.95rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }

        .dashboard-mobile-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.7rem;
        }

        .dashboard-mobile-id {
            font-size: 0.78rem;
            color: var(--af-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.2rem;
        }

        .dashboard-mobile-concept {
            font-size: 1rem;
            font-weight: 600;
            color: var(--af-text);
            line-height: 1.35;
        }

        .dashboard-mobile-amount {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--af-text);
            white-space: nowrap;
        }

        .dashboard-mobile-meta {
            display: grid;
            gap: 0.55rem;
            margin-bottom: 0.8rem;
        }

        .dashboard-mobile-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
        }

        .dashboard-mobile-line {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .dashboard-mobile-label {
            font-size: 0.74rem;
            color: var(--af-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .dashboard-mobile-value {
            font-size: 0.9rem;
            color: var(--af-text);
            word-break: break-word;
        }

        .dashboard-mobile-actions .btn {
            width: 100%;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }
    }
</style>

<div class="d-md-none mb-3">
    <a href="<?= url('autofactura-requests') ?>" class="btn btn-af w-100">
        <i class="bi bi-plus-lg me-1"></i> Nueva Factura
    </a>
</div>

<div class="card-af onboarding-card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
            <div>
                <h5 class="mb-1">
                    <i class="bi bi-magic me-2"></i><?= e($onboarding['title']) ?>
                </h5>
                <p class="text-muted mb-0"><?= e($onboarding['description']) ?></p>
            </div>
            <div class="text-lg-end">
                <div class="small text-muted mb-1">Progreso de configuración</div>
                <div class="fw-semibold"><?= (int) $onboarding['completed_steps'] ?> de <?= (int) $onboarding['total_steps'] ?> pasos</div>
            </div>
        </div>

        <div class="onboarding-progress mb-4">
            <div class="onboarding-progress-bar" style="width: <?= (int) $onboarding['progress_percent'] ?>%;"></div>
        </div>

        <div class="row g-3">
            <?php foreach ($onboarding['steps'] as $step): ?>
                <div class="col-md-6 col-xl">
                    <div class="onboarding-step <?= !empty($step['is_complete']) ? 'complete' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="onboarding-step-icon">
                                    <i class="bi <?= e($step['icon']) ?>"></i>
                                </div>
                                <div>
                                    <div class="onboarding-step-title"><?= e($step['title']) ?></div>
                                    <p class="onboarding-step-text mt-1"><?= e($step['description']) ?></p>
                                </div>
                            </div>
                            <span class="onboarding-badge <?= !empty($step['is_complete']) ? 'complete' : 'pending' ?>">
                                <i class="bi <?= !empty($step['is_complete']) ? 'bi-check-circle-fill' : 'bi-hourglass-split' ?>"></i>
                                <?= !empty($step['is_complete']) ? 'Completo' : 'Pendiente' ?>
                            </span>
                        </div>
                        <a href="<?= e($step['action_url']) ?>" class="btn <?= !empty($step['is_complete']) ? 'btn-af-outline' : 'btn-af' ?> btn-sm">
                            <?= e($step['action_label']) ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4 dashboard-stats-grid">
    <div class="col-md-6 col-lg-3">
        <div class="card-af">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="card-title">Total Facturas</div>
                        <div class="card-value"><?= $stats['total_requests'] ?></div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card-af">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="card-title">Pendientes</div>
                        <div class="card-value"><?= $stats['pending_requests'] ?></div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card-af">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="card-title">Facturadas</div>
                        <div class="card-value"><?= $stats['invoiced_requests'] ?></div>
                    </div>
                    <div class="stat-icon success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card-af">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="card-title">Conceptos</div>
                        <div class="card-value"><?= $stats['total_concepts'] ?></div>
                    </div>
                    <div class="stat-icon info">
                        <i class="bi bi-list-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Invoices -->
<div class="card-af">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Facturas Recientes</h5>
            <a href="<?= url('autofactura-requests') ?>" class="btn btn-sm btn-af-outline">Ver todas</a>
        </div>

        <?php if (empty($recentRequests)): ?>
            <div class="text-center py-4">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No hay facturas aún.</p>
                <a href="<?= url('autofactura-requests') ?>" class="btn btn-af">
                    <i class="bi bi-plus-lg me-1"></i> Crear factura
                </a>
            </div>
        <?php else: ?>
            <div class="d-none d-md-block table-responsive">
                <table class="table table-af mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Concepto</th>
                            <th>Importe</th>
                            <th>Estatus</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRequests as $req): ?>
                        <tr>
                            <td>#<?= $req['id'] ?></td>
                            <td><?= e($req['concept_name'] ?? 'N/A') ?></td>
                            <td><?= format_money($req['amount']) ?></td>
                            <td><span class="badge-status badge-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></td>
                            <td><?= format_date($req['created_at']) ?></td>
                            <td>
                                <a href="<?= url('autofactura-requests/' . $req['id']) ?>" class="btn btn-sm btn-af-outline">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-md-none dashboard-mobile-list">
                <?php foreach ($recentRequests as $req): ?>
                    <div class="dashboard-mobile-card">
                        <div class="dashboard-mobile-top">
                            <div>
                                <div class="dashboard-mobile-id">Factura #<?= $req['id'] ?></div>
                                <div class="dashboard-mobile-concept"><?= e($req['concept_name'] ?? 'N/A') ?></div>
                            </div>
                            <div class="dashboard-mobile-amount"><?= format_money($req['amount']) ?></div>
                        </div>

                        <div class="dashboard-mobile-meta">
                            <div class="dashboard-mobile-meta-grid">
                                <div class="dashboard-mobile-line">
                                    <div class="dashboard-mobile-label">Estatus</div>
                                    <div class="dashboard-mobile-value">
                                        <span class="badge-status badge-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span>
                                    </div>
                                </div>
                                <div class="dashboard-mobile-line">
                                    <div class="dashboard-mobile-label">Fecha</div>
                                    <div class="dashboard-mobile-value"><?= format_date($req['created_at']) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-mobile-actions">
                            <a href="<?= url('autofactura-requests/' . $req['id']) ?>" class="btn btn-sm btn-af-outline">
                                <i class="bi bi-eye"></i> Ver detalle
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
?>
