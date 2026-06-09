<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'AutoFactura') ?></title>
    <meta name="description" content="<?= e($description ?? 'Sistema de autofacturación') ?>">
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('img/favicon.png') ?>">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --af-primary: #4099ff;
            --af-primary-hover: #2d7fe0;
            --af-primary-light: rgba(64, 153, 255, 0.1);
            --af-secondary: #6c757d;
            --af-success: #2ed8a3;
            --af-warning: #ffb64d;
            --af-danger: #ff5370;
            --af-info: #00bcd4;
            --af-bg: #f0f2f8;
            --af-white: #ffffff;
            --af-sidebar: #2c3446;
            --af-sidebar-active: #1e2738;
            --af-text: #373a3c;
            --af-text-muted: #919aa3;
            --af-border: #e8ebed;
            --af-surface: #ffffff;
            --af-surface-2: #f8f9fa;
        }

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: var(--af-bg);
            color: var(--af-text);
            min-height: 100vh;
            font-size: 0.9rem;
        }

        /* ===== Sidebar ===== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 240px;
            height: 100vh;
            background: var(--af-sidebar);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 1.25rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-brand img {
            max-height: 40px;
            max-width: 160px;
            object-fit: contain;
            margin-bottom: 0.4rem;
            border-radius: 6px;
            background: #fff;
            padding: 3px;
        }

        .sidebar-brand h5 {
            color: #fff;
            margin: 0;
            font-weight: 600;
            font-size: 1.05rem;
        }

        .sidebar-brand small {
            color: rgba(255,255,255,0.45);
            font-size: 0.7rem;
        }

        .sidebar-nav {
            padding: 0.75rem 0;
        }

        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.55);
            padding: 0.6rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            transition: all 0.2s ease;
            font-size: 0.85rem;
            border-left: 3px solid transparent;
        }

        .sidebar-nav .nav-link:hover {
            color: rgba(255,255,255,0.9);
            background: rgba(255,255,255,0.05);
        }

        .sidebar-nav .nav-link.active {
            color: #fff;
            background: var(--af-sidebar-active);
            border-left-color: var(--af-primary);
        }

        .sidebar-nav .nav-link i {
            font-size: 1rem;
            width: 22px;
            text-align: center;
        }

        /* ===== Main content ===== */
        .main-content {
            margin-left: 240px;
            min-height: 100vh;
        }

        /* ===== Top bar ===== */
        .topbar {
            padding: 0.75rem 1.5rem;
            background: var(--af-white);
            border-bottom: 1px solid var(--af-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--af-text);
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .topbar-user .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--af-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.8rem;
        }

        /* ===== Page content ===== */
        .page-content {
            padding: 1.5rem;
        }

        /* ===== Cards ===== */
        .card-af {
            background: var(--af-white);
            border: 1px solid var(--af-border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: box-shadow 0.2s ease;
        }

        .card-af:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .card-af .card-body {
            padding: 1.25rem;
        }

        .card-af .card-title {
            color: var(--af-text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .card-af .card-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--af-primary);
        }

        /* ===== Stat icons ===== */
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            color: #fff;
        }

        .stat-icon.primary { background: var(--af-primary); }
        .stat-icon.success { background: var(--af-success); }
        .stat-icon.warning { background: var(--af-warning); }
        .stat-icon.info    { background: var(--af-info); }

        /* ===== Tables ===== */
        .table-af {
            color: var(--af-text);
            font-size: 0.85rem;
        }

        .table-af thead th {
            background: var(--af-bg);
            color: var(--af-text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-color: var(--af-border);
            padding: 0.65rem 1rem;
            font-weight: 600;
        }

        .table-af td {
            border-color: var(--af-border);
            padding: 0.65rem 1rem;
            vertical-align: middle;
        }

        .table-af tbody tr:hover {
            background: var(--af-primary-light);
        }

        /* ===== Buttons ===== */
        .btn-af {
            background: var(--af-primary);
            border: none;
            color: #fff;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .btn-af:hover {
            background: var(--af-primary-hover);
            box-shadow: 0 2px 8px rgba(64, 153, 255, 0.35);
            color: #fff;
        }

        .btn-af-outline {
            background: transparent;
            border: 1px solid var(--af-primary);
            color: var(--af-primary);
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .btn-af-outline:hover {
            background: var(--af-primary);
            color: #fff;
        }

        .btn-af-outline-danger {
            background: transparent;
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .btn-af-outline-danger:hover {
            background: #ef4444;
            border-color: #ef4444;
            color: #fff;
        }

        /* ===== Status badges ===== */
        .badge-status {
            padding: 0.3rem 0.65rem;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 500;
        }

        .badge-pendiente { background: rgba(255, 182, 77, 0.15); color: #e6a243; }
        .badge-enviada   { background: rgba(0, 188, 212, 0.12); color: #00a5bb; }
        .badge-capturada { background: rgba(64, 153, 255, 0.12); color: #4099ff; }
        .badge-facturada { background: rgba(46, 216, 163, 0.15); color: #1fb88a; }
        .badge-expirada  { background: rgba(145, 154, 163, 0.15); color: #919aa3; }
        .badge-cancelada { background: rgba(255, 83, 112, 0.12); color: #ff5370; }
        .badge-error     { background: rgba(255, 83, 112, 0.12); color: #ff5370; }

        /* ===== Forms ===== */
        .form-control-af,
        .form-select-af {
            background: var(--af-white);
            border: 1px solid var(--af-border);
            color: var(--af-text);
            border-radius: 6px;
            padding: 0.5rem 0.85rem;
            font-size: 0.85rem;
        }

        .form-control-af:focus,
        .form-select-af:focus {
            border-color: var(--af-primary);
            box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.15);
        }

        .form-control-af::placeholder {
            color: #c0c5ca;
        }

        .form-label-af {
            color: #6f7c8a;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.3rem;
        }

        .form-check-input {
            width: 1.1rem;
            height: 1.1rem;
            margin-top: 0.18rem;
            border: 1px solid #b9c3ce;
            background-color: #fff;
        }

        .form-check-input:checked {
            background-color: var(--af-primary);
            border-color: var(--af-primary);
        }

        .form-check-input:focus {
            border-color: var(--af-primary);
            box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.15);
        }

        .form-check-label {
            color: var(--af-text);
            font-weight: 500;
        }

        .mode-switches .form-check {
            background: #f7f9fc;
            border: 1px solid var(--af-border);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
        }

        /* ===== Flash messages ===== */
        .flash-message {
            border-radius: 6px;
            border: none;
            animation: slideDown 0.3s ease;
            font-size: 0.85rem;
        }

        @keyframes slideDown {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* ===== Modal overrides ===== */
        .modal-content {
            background: var(--af-surface);
            color: var(--af-text);
            border: 1px solid var(--af-border);
            border-radius: 8px;
        }

        .modal-header,
        .modal-footer {
            border-color: var(--af-border) !important;
        }

        .modal-title {
            color: var(--af-text);
        }

        /* ===== Mobile ===== */
        .sidebar-toggle {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-toggle {
                display: block;
            }
            .page-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <?php $logo = business_logo(); ?>
            <img src="<?= e($logo ?: asset('img/autofactura.png')) ?>" alt="Logo">
            <h5></h5>
            <small>cfdi.top - Autofacturación</small>
        </div>
        <nav class="sidebar-nav">
            <a href="<?= url('dashboard') ?>" class="nav-link <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= url('autofactura-requests') ?>" class="nav-link <?= ($activeMenu ?? '') === 'requests' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> Facturas
            </a>
            <a href="<?= url('invoice-concepts') ?>" class="nav-link <?= ($activeMenu ?? '') === 'concepts' ? 'active' : '' ?>">
                <i class="bi bi-list-check"></i> Conceptos
            </a>
            <a href="<?= url('stamp-purchases') ?>" class="nav-link <?= ($activeMenu ?? '') === 'stamps' ? 'active' : '' ?>">
                <i class="bi bi-ticket-perforated"></i> Timbres
            </a>
            <a href="<?= url('business-settings') ?>" class="nav-link <?= ($activeMenu ?? '') === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Configuración
            </a>
            <a href="<?= url('users') ?>" class="nav-link <?= ($activeMenu ?? '') === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> <?= is_superuser() ? 'Usuarios' : 'Mi Cuenta' ?>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <span class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></span>
            </div>
            <div class="topbar-user">
                <span class="text-muted d-none d-md-inline" style="font-size: 0.85rem;"><?= e(auth_business_name() ?? '') ?></span>
                <div class="avatar"><?= strtoupper(substr(auth_business_name() ?? 'U', 0, 1)) ?></div>
                <a href="<?= url('logout') ?>" class="btn btn-sm btn-af-outline" title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Flash Messages -->
            <?php if (has_flash('success')): ?>
                <div class="alert alert-success flash-message alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= e(get_flash('success')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (has_flash('error')): ?>
                <div class="alert alert-danger flash-message alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= e(get_flash('error')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (has_flash('info')): ?>
                <div class="alert alert-info flash-message alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <?= e(get_flash('info')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Content slot -->
            <?= $content ?? '' ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
