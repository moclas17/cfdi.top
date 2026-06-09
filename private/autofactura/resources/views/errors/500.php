<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Error interno</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f8fa; color: #30343b; margin: 0; }
        .box { max-width: 640px; margin: 8vh auto; background: #fff; border: 1px solid #e2e6ea; border-radius: 10px; padding: 24px; }
        h1 { margin: 0 0 10px; font-size: 1.5rem; }
        p { margin: 0 0 10px; color: #5f6874; }
        a { color: #2d7fe0; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Ocurrió un error inesperado</h1>
        <p><?= e($errorMessage ?? 'No pudimos completar la operación solicitada.') ?></p>
        <p>Inténtalo de nuevo en unos minutos.</p>
        <?php if (!empty($errorReference)): ?>
            <p><strong>Referencia:</strong> <?= e($errorReference) ?></p>
        <?php endif; ?>
        <p><a href="<?= url(is_authenticated() ? 'dashboard' : 'login') ?>">Volver</a></p>
        <?php if (!empty($debugException)): ?>
            <pre style="white-space: pre-wrap; font-size: 12px; background: #f5f7fa; border: 1px solid #dfe3e8; border-radius: 8px; padding: 12px; overflow: auto;"><?= e($debugException) ?></pre>
        <?php endif; ?>
    </div>
</body>
</html>
