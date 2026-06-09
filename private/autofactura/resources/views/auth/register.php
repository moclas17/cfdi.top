<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - AutoFactura</title>
    <meta name="description" content="Crear cuenta en AutoFactura SaaS">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; }

        body {
            background: #f0f2f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .register-card {
            background: #fff;
            border: 1px solid #e8ebed;
            border-radius: 10px;
            padding: 2rem;
            width: 100%;
            max-width: 540px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .register-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .register-logo {
            width: 56px;
            height: 56px;
            background: #4099ff;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .register-logo i {
            font-size: 1.75rem;
            color: #fff;
        }

        .register-header h4 {
            font-weight: 700;
            color: #373a3c;
            margin-bottom: 0.25rem;
        }

        .register-header p {
            color: #919aa3;
            font-size: 0.85rem;
            margin: 0;
        }

        .form-label {
            color: #919aa3;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .form-control {
            border: 1px solid #e8ebed;
            border-radius: 6px;
            padding: 0.55rem 0.85rem;
            font-size: 0.85rem;
        }

        .form-control:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.15);
        }

        .btn-register {
            background: #4099ff;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 0.6rem;
            border-radius: 6px;
            width: 100%;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-register:hover {
            background: #2d7fe0;
            box-shadow: 0 2px 8px rgba(64, 153, 255, 0.35);
            color: #fff;
        }

        .register-footer {
            text-align: center;
            margin-top: 1rem;
            color: #919aa3;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="register-header">
            <div class="register-logo">
                <i class="bi bi-person-plus"></i>
            </div>
            <h4>Crear cuenta</h4>
            <p>Registra tu negocio para usar AutoFactura</p>
        </div>

        <?php if (has_flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= e(get_flash('error')) ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (has_flash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1"></i>
                <?= e(get_flash('success')) ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (has_flash('info')): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-1"></i>
                <?= e(get_flash('info')) ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('register') ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="name">Nombre del negocio</label>
                    <input type="text" class="form-control" id="name" name="name" required maxlength="255">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input type="email" class="form-control" id="email" name="email" required maxlength="255">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="phone">Teléfono (opcional)</label>
                    <input type="text" class="form-control" id="phone" name="phone" maxlength="20">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="password">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="6">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="password_confirm">Confirmar contraseña</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6">
                </div>
            </div>

            <button type="submit" class="btn btn-register mt-4">
                <i class="bi bi-check-circle me-1"></i> Crear cuenta
            </button>
        </form>

        <div class="register-footer">
            ¿Ya tienes cuenta? <a href="<?= url('login') ?>" class="text-decoration-none">Inicia sesión</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
