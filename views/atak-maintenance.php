<?php
$base = url('');
$message = trim((string) ($maintenanceMessage ?? ''));
if ($message === '') {
    $message = 'La carte tactique est temporairement indisponible. Un administrateur de votre communauté a activé le mode maintenance.';
}
$canAccessAdminAtakConfig = !empty($canAccessAdminAtakConfig);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Carte tactique en maintenance | ATHENA</title>
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/atak.css" rel="stylesheet" />
  <style>
    .atak-maint {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.25rem;
      background: radial-gradient(1200px 600px at 50% -10%, #1e293b 0%, #0f172a 55%, #020617 100%);
      color: #e2e8f0;
      font-family: system-ui, sans-serif;
    }
    .atak-maint__card {
      max-width: 32rem;
      width: 100%;
      padding: 2rem 1.75rem;
      border: 1px solid rgba(148, 163, 184, 0.25);
      border-radius: 1rem;
      background: rgba(15, 23, 42, 0.85);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    }
    .atak-maint__eyebrow {
      font-size: 0.75rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: #94a3b8;
      margin: 0 0 0.75rem;
    }
    .atak-maint__title {
      font-size: 1.5rem;
      font-weight: 800;
      margin: 0 0 0.75rem;
      color: #f8fafc;
    }
    .atak-maint__body {
      font-size: 0.95rem;
      line-height: 1.55;
      color: #cbd5e1;
      margin: 0 0 1.5rem;
      white-space: pre-wrap;
    }
    .atak-maint__actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
    }
    .atak-maint__actions a {
      display: inline-flex;
      align-items: center;
      padding: 0.65rem 1rem;
      border-radius: 0.6rem;
      font-size: 0.875rem;
      font-weight: 600;
      text-decoration: none;
    }
    .atak-maint__actions a.primary {
      background: #e2e8f0;
      color: #0f172a;
    }
    .atak-maint__actions a.secondary {
      border: 1px solid rgba(148, 163, 184, 0.4);
      color: #e2e8f0;
    }
  </style>
</head>
<body>
  <main class="atak-maint">
    <div class="atak-maint__card">
      <p class="atak-maint__eyebrow">ATHENA · Carte tactique</p>
      <h1 class="atak-maint__title">Maintenance en cours</h1>
      <p class="atak-maint__body"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <div class="atak-maint__actions">
        <a class="primary" href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">Retour au tableau de bord</a>
        <?php if ($canAccessAdminAtakConfig): ?>
        <a class="secondary" href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>">Gérer la maintenance</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
