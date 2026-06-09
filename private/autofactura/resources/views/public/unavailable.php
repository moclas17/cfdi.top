<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Disponible - AutoFactura</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f8; color: #373a3c; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-card { background: #fff; border: 1px solid #e8ebed; border-radius: 10px; padding: 2.5rem 2rem; text-align: center; max-width: 420px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .error-icon { font-size: 3rem; color: #919aa3; margin-bottom: 1rem; }
        .error-detail { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 0.75rem; color: #6c757d; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon"><i class="bi bi-slash-circle"></i></div>
        <h4 style="font-weight: 700;">Solicitud No Disponible</h4>
        <p class="text-muted" style="font-size: 0.85rem;">Esta solicitud no está disponible en este momento. Contacta al negocio para más información.</p>
        <?php if (!empty($errorMessage)): ?>
            <div class="error-detail"><?= e($errorMessage) ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
