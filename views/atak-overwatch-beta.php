<?php

declare(strict_types=1);

$base = url('');
$assetVer = platform_app_version();
$map = $atakMapConfig ?? null;
$config = is_array($map) ? ($map['config'] ?? []) : [];
$slug = (string) ($map['slug'] ?? 'altis');
$mapConfig = atak_with_aerial_layer([
    'slug' => $slug,
    'tilePattern' => atak_resolve_tile_pattern((string) ($map['tile_pattern'] ?? ''), $slug),
    'center' => $config['center'] ?? [15000, 15000],
    'defaultZoom' => (int) ($config['defaultZoom'] ?? 3),
    'minZoom' => (int) ($config['minZoom'] ?? 0),
    'maxZoom' => (int) ($config['maxZoom'] ?? 6),
    'tileSize' => (int) ($config['tileSize'] ?? 212),
    'worldSize' => (int) ($config['worldSize'] ?? 30720),
    'crs' => $config['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
    'offsetX' => (float) ($config['offset_x'] ?? 0),
    'offsetY' => (float) ($config['offset_y'] ?? 0),
], $slug);
$operator = trim((string) ($atakUserForJs['callsign'] ?? $atakUserForJs['displayName'] ?? 'OPÉRATEUR'));
?>
<!doctype html>
<html lang="fr" class="ow-root">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#060807">
  <title>ATHENA // Overwatch Beta</title>
  <link rel="icon" href="<?= htmlspecialchars($base) ?>/assets/icons/athena-192.png">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/atak-overwatch-beta.css?v=<?= htmlspecialchars($assetVer) ?>">
  <script>
    window.ATAK_OVERWATCH_BETA = true;
    window.ATAK_API_BASE = <?= json_encode($base) ?>;
    window.ATAK_TOKEN = <?= json_encode($atakToken ?? '') ?>;
    window.ATAK_TENANT_ID = <?= (int) ($atakTenantId ?? 0) ?>;
    window.ATAK_DEFAULT_MAP_ID = <?= (int) ($atakDefaultMapId ?? 1) ?>;
    window.ATAK_MAP_CONFIG = <?= json_encode($mapConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  </script>
</head>
<body>
<div class="ow-shell">
  <header class="ow-topbar">
    <a class="ow-brand" href="<?= htmlspecialchars(url('-ATAK-OVERWATCH-Beta')) ?>"><b>A</b><span>ATHENA<small>COMSPEC / OVERWATCH BETA</small></span></a>
    <nav class="ow-nav" aria-label="Espaces de travail">
      <button class="is-active" data-view="network">OVERWATCH</button><button data-view="mission">MISSION</button><button data-view="intel">INTEL</button><button data-view="layers">LAYERS</button>
    </nav>
    <button class="ow-command" data-command>⌘&nbsp; COMMAND</button>
    <div class="ow-session"><i></i><span><strong><?= htmlspecialchars($operator, ENT_QUOTES, 'UTF-8') ?></strong><small id="ow-link-label">CONNEXION…</small></span></div>
  </header>

  <main class="ow-workspace">
    <aside class="ow-rail" aria-label="Outils cartographiques">
      <button class="is-active" data-tool="cursor" title="Sélection">⌖</button>
      <button data-tool="center" title="Centrer les contacts">◎</button>
      <button data-tool="zoom-in" title="Zoom avant">＋</button>
      <button data-tool="zoom-out" title="Zoom arrière">−</button>
      <span></span><button data-tool="refresh" title="Actualiser">↻</button>
    </aside>
    <section class="ow-map-stage">
      <div id="ow-map" aria-label="Carte tactique temps réel"></div>
      <div class="ow-map-tools"><button class="is-active">AO LIVE</button><button data-toggle-roster>CONTACTS <b id="ow-contact-count">0</b></button><button data-view="layers">CALQUES</button></div>
      <div class="ow-coordinate" id="ow-coordinate">GRID — · ALT — · LIVE</div>
      <div class="ow-empty" id="ow-empty"><b>AUCUNE TÉLÉMÉTRIE</b><span>En attente des contacts autorisés pour cette communauté.</span></div>
    </section>
    <aside class="ow-contacts" id="ow-contacts">
      <header><span><b>●</b> TACTICAL NETWORK</span><button data-toggle-roster aria-label="Fermer">×</button></header>
      <label class="ow-search"><span>⌕</span><input id="ow-search" type="search" placeholder="Filtrer callsign, groupe…"></label>
      <div class="ow-section-label">LIVE / CONTACTS AUTORISÉS</div>
      <div id="ow-contact-list" class="ow-contact-list" aria-live="polite"></div>
    </aside>
    <aside class="ow-drawer" id="ow-drawer" hidden>
      <header><small>BFT / CONTACT</small><button data-close-drawer>×</button><h2 id="ow-drawer-title">CONTACT</h2></header>
      <dl id="ow-drawer-data"></dl>
      <button class="ow-primary" data-center-selected>CENTRER SUR LA CARTE</button>
    </aside>
  </main>
  <footer class="ow-footer"><b id="ow-footer-link">ATHENA ● SYNCHRONISATION</b><span id="ow-latency">RX —</span><span id="ow-map-name"><?= htmlspecialchars(strtoupper($slug)) ?></span><span>MAP CACHE READY</span><span class="ow-footer-end">OVERWATCH // BETA</span></footer>
</div>
<div class="ow-palette" id="ow-palette" hidden><div><input id="ow-command-input" placeholder="Rechercher un contact ou une commande…"><p>↑↓ NAVIGUER &nbsp; ENTER EXÉCUTER &nbsp; ESC FERMER</p></div></div>
<script src="<?= htmlspecialchars($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.js"></script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/atak-overwatch-beta.js?v=<?= htmlspecialchars($assetVer) ?>"></script>
</body>
</html>
