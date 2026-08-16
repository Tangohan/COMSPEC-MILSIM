<?php
declare(strict_types=1);
/**
 * Coque téléphone ATAK (texture cTab / IceMan Android S7) + carte Athena dans l’écran.
 * Géométrie calquée sur display_device_macros.hpp (canvas 2048×2048).
 */
$title = (string) ($title ?? 'ATAK — Appareil de terrain');
$atakTenantName = trim((string) ($atakTenantName ?? 'Communauté'));
$atakEmbedUrl = (string) ($atakEmbedUrl ?? url('atak'));
$slidesUrl = (string) ($slidesUrl ?? '');
$chooseUrl = (string) ($chooseUrl ?? url('connect'));
$base = rtrim((string) url(''), '/');
$assetVer = platform_app_version();
$bezelUrl = $base . '/assets/img/connect-device/comspec_phone_bg_ca.png?v=' . rawurlencode($assetVer);
$batteryUrl = $base . '/assets/img/connect-device/comspec_icon_battery_ca.png?v=' . rawurlencode($assetVer);
$signalUrl = $base . '/assets/img/connect-device/comspec_icon_signal_ca.png?v=' . rawurlencode($assetVer);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1">
  <meta name="theme-color" content="#05070c">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/connect-device.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" />
</head>
<body class="connect-device-page">
  <div class="connect-device-stage">
    <header class="connect-device-chrome">
      <a class="connect-device-chrome__back" href="<?= htmlspecialchars($chooseUrl, ENT_QUOTES, 'UTF-8') ?>">Retour</a>
      <div class="connect-device-chrome__brand">
        <span class="connect-device-chrome__eyebrow">Athena · ATAK</span>
        <strong class="connect-device-chrome__title"><?= htmlspecialchars($atakTenantName, ENT_QUOTES, 'UTF-8') ?></strong>
      </div>
      <?php if ($slidesUrl !== ''): ?>
        <a class="connect-device-chrome__link" href="<?= htmlspecialchars($slidesUrl, ENT_QUOTES, 'UTF-8') ?>">Briefing</a>
      <?php else: ?>
        <span class="connect-device-chrome__spacer" aria-hidden="true"></span>
      <?php endif; ?>
    </header>

    <div class="connect-device-shell" role="presentation">
      <img
        class="connect-device-bezel"
        src="<?= htmlspecialchars($bezelUrl, ENT_QUOTES, 'UTF-8') ?>"
        alt=""
        width="2048"
        height="2048"
        decoding="async"
        draggable="false"
      />

      <!-- Trou écran = coords cTab Android (452,713) / 1134×624 sur 2048 -->
      <div class="connect-device-screen">
        <div class="connect-device-osd" aria-hidden="true">
          <span class="connect-device-osd__brand">ATAK</span>
          <span class="connect-device-osd__sep">·</span>
          <span class="connect-device-osd__mode">BFT</span>
          <span class="connect-device-osd__clock" id="connect-device-clock">--:--</span>
          <span class="connect-device-osd__grow"></span>
          <img class="connect-device-osd__icon" src="<?= htmlspecialchars($signalUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" width="32" height="16" />
          <img class="connect-device-osd__icon connect-device-osd__icon--bat" src="<?= htmlspecialchars($batteryUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" width="40" height="16" />
        </div>
        <div class="connect-device-viewport">
          <iframe
            id="connect-device-frame"
            class="connect-device-frame"
            title="Carte tactique Athena ATAK"
            src="<?= htmlspecialchars($atakEmbedUrl, ENT_QUOTES, 'UTF-8') ?>"
            allow="fullscreen; geolocation"
            loading="eager"
            referrerpolicy="same-origin"
          ></iframe>
        </div>
      </div>
    </div>

    <p class="connect-device-hint">Carte Arma dans le terminal ATAK Android — même liaison que sur Athena.</p>
  </div>

  <script src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/js/connect-device.js?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
