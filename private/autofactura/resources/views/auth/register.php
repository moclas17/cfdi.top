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

        .privacy-consent {
            background: #f8f9fc;
            border: 1px solid #e8ebed;
            border-radius: 8px;
            padding: 0.85rem 1rem;
        }

        .consent-option {
            align-items: flex-start;
            display: flex;
            gap: 0.7rem;
        }

        .privacy-consent .form-check-input {
            flex: 0 0 auto;
            float: none;
            height: 1.15rem;
            margin: 0.15rem 0 0;
            width: 1.15rem;
        }

        .privacy-consent .form-check-label {
            color: #626b75;
            font-size: 0.8rem;
            line-height: 1.45;
        }

        .consent-note {
            color: #919aa3;
            display: block;
            font-size: 0.74rem;
            margin-top: 0.15rem;
        }

        .btn-register:disabled {
            background: #aeb7c2;
            box-shadow: none;
            cursor: not-allowed;
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

                <div class="col-12">
                    <div class="privacy-consent">
                        <div class="consent-option">
                            <input class="form-check-input" type="checkbox" value="1" id="privacy_accepted" name="privacy_accepted" required>
                            <label class="form-check-label" for="privacy_accepted">
                                He leído y acepto el
                                <a href="<?= url('aviso-de-privacidad') ?>" target="_blank" rel="noopener noreferrer">Aviso de Privacidad</a>.
                                <span class="consent-note">Obligatorio para crear la cuenta.</span>
                            </label>
                        </div>
                        <div class="consent-option mt-3">
                            <input class="form-check-input" type="checkbox" value="1" id="marketing_consent" name="marketing_consent">
                            <label class="form-check-label" for="marketing_consent">
                                Acepto recibir ofertas de folios, promociones y encuestas de satisfacción.
                                <span class="consent-note">Opcional y desmarcado por defecto.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-register mt-3" id="register_submit" disabled>
                <i class="bi bi-check-circle me-1"></i> Crear cuenta
            </button>
        </form>

        <div class="register-footer">
            ¿Ya tienes cuenta? <a href="<?= url('login') ?>" class="text-decoration-none">Inicia sesión</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const privacyAccepted = document.getElementById('privacy_accepted');
        const registerSubmit = document.getElementById('register_submit');

        function syncRegisterButton() {
            registerSubmit.disabled = !privacyAccepted.checked;
        }

        privacyAccepted.addEventListener('change', syncRegisterButton);
        syncRegisterButton();
    </script>
</body>
</html>
