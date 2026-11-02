<?php
/**
 * En-tête commun des pages « Sur mon téléphone » (/m/…) : plein écran téléphone, thème sombre ATAK, menu des apps.
 *
 * @var string $title
 * @var string $mobileView
 * @var array<string, mixed>|null $mobileLink
 * @var string|null $csrf
 * @var array<string, mixed>|null $mapConfig
 * @var bool|null $showBriefs
 * @var string|null $expiresAt
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ver = rawurlencode(class_exists(\App\Support\BuildVersion::class) ? \App\Support\BuildVersion::id() : '1');
$needsMap = in_array($mobileView ?? '', ['carte', 'cr'], true);
$tabs = [
    'cr' => ['m/cr', 'CR'],
    'compte_rendu' => ['m/compte-rendu', 'CR fin d’op'],
    'carte' => ['m/carte', 'Carte'],
];
if (!empty($showBriefs)) {
    $tabs['briefs'] = ['m/briefs', 'Briefs air'];
}
$player = trim((string) ($mobileLink['player_name'] ?? ''));
$until = '';
if (!empty($expiresAt)) {
    $ts = strtotime($expiresAt . ' UTC');
    $until = $ts ? date('H:i', $ts) : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#070a0e">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <?php if (!empty($csrf)): ?><meta name="csrf-token" content="<?= $h($csrf) ?>"><?php endif; ?>
  <title><?= $h($title ?? 'Sur mon téléphone — Athena') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;700&family=IBM+Plex+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <?php if ($needsMap): ?><link rel="stylesheet" href="<?= $h(url('assets/vendor/leaflet-1.9.4/leaflet.css')) ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?= $h(url('assets/css/atak-m.css')) ?>?v=<?= $h($ver) ?>">
</head>
<body class="mm-body" data-view="<?= $h($mobileView ?? '') ?>" data-base="<?= $h(rtrim(url(''), '/')) ?>">
<?php if (!empty($mobileLink)): ?>
  <header class="mm-top">
    <a class="mm-top__brand" href="<?= $h(url('m')) ?>"><span class="mm-top__mark">ATHENA</span><span class="mm-top__app"><?= $h(explode(' — ', (string) ($title ?? ''))[0]) ?></span></a>
    <div class="mm-top__who">
      <?php if ($player !== ''): ?><span translate="no"><?= $h($player) ?></span><?php endif; ?>
      <?php if ($until !== ''): ?><small>jusqu’à <?= $h($until) ?></small><?php endif; ?>
    </div>
  </header>
  <nav class="mm-tabs" aria-label="Apps">
    <?php foreach ($tabs as $key => [$path, $label]): ?>
      <a class="mm-tab<?= ($mobileView ?? '') === $key ? ' is-active' : '' ?>" href="<?= $h(url($path)) ?>"><?= $h($label) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
  <main class="mm-main">
