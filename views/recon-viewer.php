<?php
declare(strict_types=1);

/**
 * Lecteur d'un cliché de reconnaissance (GET /recon/images/{id}) — AtakApiController::reconImagesViewer.
 *
 * @var int $photoId
 * @var list<array<string, mixed>> $items
 * @var int $index
 * @var string|null $error tenant | maintenance | not_found | server
 * @var string $errorMessage
 * @var array<string, mixed>|null $mapConfig
 * @var string $mapUrl
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = rtrim(url(''), '/');
$current = $items[$index] ?? null;
$pageTitle = 'Cliché de reconnaissance';
if (is_array($current)) {
    $t = trim((string) ($current['title'] ?? ''));
    $pageTitle = $t !== '' ? $t : ('Cliché ' . (int) ($current['id'] ?? $photoId));
}
$json = static fn ($v): string => (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
$errors = [
    'tenant' => ['Connexion requise', 'Ce cliché appartient à une communauté. Connectez-vous à Athena (ou reliez votre compte en jeu) pour l’afficher.'],
    'maintenance' => ['Carte en maintenance', 'L’accès aux clichés est suspendu pour le moment. Réessayez plus tard.'],
    'not_found' => ['Cliché introuvable', 'Ce cliché a été supprimé, ou il n’existe pas dans votre communauté.'],
    'server' => ['Erreur du serveur', 'Le cliché n’a pas pu être chargé. L’erreur est notée dans le journal du site (préfixe [atak/recon-images]).'],
];
?><!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <meta name="theme-color" content="#07090c">
  <title><?= $h($pageTitle) ?> · Athena</title>
  <link rel="icon" href="<?= $h($base) ?>/assets/icons/athena-192.png">
  <link rel="stylesheet" href="<?= $h(asset_url('assets/css/recon-viewer.css')) ?>">
  <?php if ($mapConfig): ?>
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.css">
  <?php endif; ?>
  <style>
    html, body { margin: 0; height: 100%; background: #07090c; }
    .rv-error { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; font-family: "IBM Plex Sans", Inter, system-ui, sans-serif; color: #e5e9ee; }
    .rv-error__card { max-width: 440px; padding: 28px; border-radius: 14px; background: #10151c; border: 1px solid rgba(148, 163, 184, 0.2); }
    .rv-error__card h1 { margin: 0 0 8px; font-size: 19px; }
    .rv-error__card p { margin: 0 0 16px; color: #cbd5e1; line-height: 1.5; }
    .rv-error__card a { color: #fbbf24; font-weight: 600; margin-right: 16px; }
  </style>
</head>
<body>
<?php if ($error !== null || !$items): ?>
  <?php [$errTitle, $errText] = $errors[$error ?? 'not_found'] ?? $errors['not_found']; ?>
  <main class="rv-error">
    <div class="rv-error__card" role="alert">
      <h1><?= $h($errTitle) ?></h1>
      <p><?= $h($errorMessage !== '' ? $errorMessage : $errText) ?></p>
      <p>
        <?php if ($error === 'tenant'): ?><a href="<?= $h($base . '/login') ?>">Se connecter</a><?php endif; ?>
        <a href="<?= $h($mapUrl) ?>">Carte ATAK</a>
        <a href="<?= $h($base . '/') ?>">Accueil Athena</a>
      </p>
    </div>
  </main>
<?php else: ?>
  <main id="recon-viewer" aria-label="Lecteur de cliché"></main>
  <noscript>
    <p style="color:#e5e9ee;font-family:system-ui;padding:16px">Le lecteur a besoin de JavaScript.
      <?php if (!empty($current['url'])): ?><a style="color:#fbbf24" href="<?= $h($current['url'] . (str_contains((string) $current['url'], '?') ? '&' : '?') . 'raw=1') ?>">Ouvrir l’image</a><?php endif; ?></p>
  </noscript>
  <?php if ($mapConfig): ?>
  <script src="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.js"></script>
  <script src="<?= $h($base) ?>/assets/js/atak-map-crs.js"></script>
  <?php endif; ?>
  <script src="<?= $h(asset_url('assets/js/recon-viewer.js')) ?>"></script>
  <script>
    window.ReconViewer.mount(document.getElementById('recon-viewer'), <?= $json($items) ?>, <?= (int) $index ?>, {
      recon: true,
      map: <?= $json($mapConfig) ?>,
      mapTitle: <?= $json($mapConfig['title'] ?? '') ?>,
      mapUrl: <?= $json($mapUrl) ?>
    });
  </script>
<?php endif; ?>
</body>
</html>
