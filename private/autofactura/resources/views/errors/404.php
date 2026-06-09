<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f8; color: #373a3c; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-page { text-align: center; }
        .error-code { font-size: 6rem; font-weight: 800; color: #4099ff; line-height: 1; }
        .error-text { font-size: 1rem; color: #919aa3; margin: 0.75rem 0 1.5rem; }
        .btn-home { background: #4099ff; border: none; color: #fff; padding: 0.6rem 1.5rem; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-block; font-size: 0.85rem; transition: all 0.2s; }
        .btn-home:hover { background: #2d7fe0; color: #fff; box-shadow: 0 2px 8px rgba(64,153,255,0.35); }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-code">404</div>
        <div class="error-text">La página que buscas no existe.</div>
        <a href="/autofactura/" class="btn-home">
            <i class="bi bi-house me-1"></i> Volver al inicio
        </a>
    </div>
</body>
</html>
