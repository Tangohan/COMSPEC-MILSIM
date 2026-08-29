<?php
/** @var string $mobileModule */
/** @var string $mobileModuleLabel */
/** @var string $tenantName */
/** @var int $tenantId */
/** @var int $mapId */
/** @var array<string,mixed> $mapConfig */
/** @var array<string,mixed>|null $phoneSession */
/** @var array<string,mixed>|null $user */
/** @var string $apiBase */
/** @var string $assetVer */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string) ($apiBase ?? url('')), '/');
$ver = rawurlencode((string) ($assetVer ?? '1'));
$module = (string) ($mobileModule ?? 'c2');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <meta name="theme-color" content="#070a0e">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= $h($title ?? 'COMSPEC ATAK Mobile') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;700&family=IBM+Plex+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.css">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/css/atak-mobile.css?v=<?= $h($ver) ?>">
</head>
<body class="am-body" data-module="<?= $h($module) ?>">
  <header class="am-topbar" role="banner">
    <div class="am-topbar__brand">
      <span class="am-topbar__mark">COMSPEC</span>
      <span class="am-topbar__module" id="am-module-label"><?= $h($mobileModuleLabel ?? 'C2') ?></span>
    </div>
    <div class="am-topbar__meta">
      <span class="am-topbar__live" id="am-live" data-state="offline">OFFLINE</span>
      <span class="am-topbar__zulu" id="am-zulu">--:--Z</span>
      <button type="button" class="am-icon-btn" id="am-drawer-open" aria-label="Modules">☰</button>
    </div>
  </header>

  <main class="am-main" id="am-main" role="main">
    <section class="am-screen is-active" data-screen="c2" id="am-screen-c2"></section>
    <section class="am-screen" data-screen="sitac" id="am-screen-sitac" hidden>
      <div id="am-map" class="am-map" aria-label="SITAC"></div>
      <div class="am-map-chips" id="am-map-chips"></div>
      <div class="am-map-tools" id="am-map-tools"></div>
    </section>
    <section class="am-screen" data-screen="chat" id="am-screen-chat" hidden></section>
    <section class="am-screen" data-screen="bft" id="am-screen-bft" hidden></section>
    <section class="am-screen" data-screen="status" id="am-screen-status" hidden></section>
    <section class="am-screen" data-screen="pings" id="am-screen-pings" hidden></section>
    <section class="am-screen" data-screen="intel" id="am-screen-intel" hidden></section>
    <section class="am-screen" data-screen="jtac" id="am-screen-jtac" hidden></section>
    <section class="am-screen" data-screen="air" id="am-screen-air" hidden></section>
    <section class="am-screen" data-screen="sigint" id="am-screen-sigint" hidden></section>
    <section class="am-screen" data-screen="orders" id="am-screen-orders" hidden></section>
    <section class="am-screen" data-screen="explosives" id="am-screen-explosives" hidden></section>
  </main>

  <nav class="am-bottom" aria-label="Navigation principale">
    <button type="button" class="am-bottom__btn" data-nav="c2"><span>C2</span></button>
    <button type="button" class="am-bottom__btn" data-nav="sitac"><span>SITAC</span></button>
    <button type="button" class="am-bottom__btn" data-nav="chat"><span>TCHAT</span></button>
    <button type="button" class="am-bottom__btn" data-nav="bft"><span>BFT</span></button>
    <button type="button" class="am-bottom__btn" data-nav="plus" id="am-nav-plus"><span>PLUS</span></button>
  </nav>

  <div class="am-drawer" id="am-drawer" hidden>
    <div class="am-drawer__backdrop" data-am-close-drawer></div>
    <div class="am-drawer__panel" role="dialog" aria-label="Modules">
      <div class="am-drawer__head">
        <div>
          <p class="am-drawer__eyebrow">Modules</p>
          <h2>COMSPEC ATAK</h2>
        </div>
        <button type="button" class="am-icon-btn" data-am-close-drawer aria-label="Fermer">×</button>
      </div>
      <div class="am-drawer__grid" id="am-drawer-grid"></div>
      <div class="am-drawer__foot">
        <p><strong id="am-tenant-label"><?= $h($tenantName ?? '') ?></strong></p>
        <p class="am-muted">Carte #<?= (int) ($mapId ?? 1) ?> · <?= $h($mapConfig['title'] ?? $mapConfig['slug'] ?? '—') ?></p>
      </div>
    </div>
  </div>

  <div class="am-sheet" id="am-sheet" hidden>
    <div class="am-sheet__backdrop" data-am-close-sheet></div>
    <div class="am-sheet__panel" role="dialog" aria-modal="true">
      <div class="am-sheet__handle" aria-hidden="true"></div>
      <div class="am-sheet__body" id="am-sheet-body"></div>
    </div>
  </div>

  <script>
    window.ATAK_MOBILE = {
      module: <?= json_encode($module, JSON_UNESCAPED_UNICODE) ?>,
      apiBase: <?= json_encode($base, JSON_UNESCAPED_UNICODE) ?>,
      mapId: <?= (int) ($mapId ?? 1) ?>,
      mapConfig: <?= json_encode($mapConfig ?? new stdClass(), JSON_UNESCAPED_UNICODE) ?>,
      tenantId: <?= (int) ($tenantId ?? 0) ?>,
      tenantName: <?= json_encode($tenantName ?? '', JSON_UNESCAPED_UNICODE) ?>,
      phoneSession: <?= json_encode($phoneSession, JSON_UNESCAPED_UNICODE) ?>,
      user: <?= json_encode($user ? [
        'id' => (int) ($user['id'] ?? 0),
        'displayName' => (string) ($user['display_name'] ?? ''),
        'callsign' => (string) ($user['callsign'] ?? ''),
      ] : null, JSON_UNESCAPED_UNICODE) ?>,
      assetBase: <?= json_encode($base . '/assets', JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.js"></script>
  <script src="<?= $h($base) ?>/assets/js/atak-map-crs.js?v=<?= $h($ver) ?>"></script>
  <script src="<?= $h($base) ?>/assets/js/atak-mobile/atak-mobile.js?v=<?= $h($ver) ?>"></script>
</body>
</html>
