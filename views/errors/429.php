<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Trop de requêtes') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 1rem; }
        .box { max-width: 28rem; text-align: center; }
        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
        p { color: #94a3b8; font-size: 0.95rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Trop de requêtes</h1>
        <p>Veuillez patienter quelques instants avant de réessayer.</p>
    </div>
</body>
</html>
