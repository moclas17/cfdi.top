<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - AutoFactura</title>
    <meta name="description" content="Accede al sistema de autofacturación">

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
        }

        .login-card {
            background: #fff;
            border: 1px solid #e8ebed;
            border-radius: 10px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
            display: block;
            width: min(100%, 320px);
            margin: 0 auto 0.75rem;
        }

        .login-header h4 {
            font-weight: 700;
            color: #373a3c;
            margin-bottom: 0.25rem;
        }

        .login-header p {
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

        .input-group-text {
            background: #f8f9fa;
            border: 1px solid #e8ebed;
            color: #919aa3;
        }

        .btn-login {
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

        .btn-login:hover {
            background: #2d7fe0;
            box-shadow: 0 2px 8px rgba(64, 153, 255, 0.35);
            color: #fff;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.25rem;
            color: #c0c5ca;
            font-size: 0.78rem;
        }

        .alert {
            font-size: 0.85rem;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="<?= asset('img/autofactura.png') ?>" alt="AutoFactura" class="login-logo">
            <p>Inicia sesión para continuar</p>
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

        <form method="POST" action="<?= url('login') ?>" id="loginForm">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="email">Correo electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="correo@ejemplo.com" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
            </button>
        </form>

        <div class="login-footer">
            <div class="mb-2">
                ¿No tienes cuenta?
                <a href="<?= url('register') ?>" class="text-decoration-none">Regístrate</a>
            </div>
            <i class="bi bi-shield-check me-1"></i> Conexión segura
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
