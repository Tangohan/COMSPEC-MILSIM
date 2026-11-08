<?php

declare(strict_types=1);
/**
 * En-tête commun des pages « Sur mon téléphone » (/m/…) : plein écran téléphone, thème de /9-line (noir, vert, Inter et JetBrains Mono), menu des apps.
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
$needsMap = in_array($mobileView ?? '', ['carte', 'cr', 'briefs'], true);
$tabs = [
    'cr' => ['m/cr', 'CR'],
    'compte_rendu' => ['m/compte-rendu', 'Fin d’op'],
    'carte' => ['m/carte', 'Carte'],
    'radio' => ['m/radio', 'Radio'],
    'controle' => ['m/controle', 'ID'],
];
if (!empty($showBriefs)) {
    $tabs['briefs'] = ['m/briefs', 'Air'];
}
// Les pages d'un espace allument l'onglet de l'espace.
$activeTab = match ($mobileView ?? '') {
    'cr_list', 'cr_read' => 'cr',
    'radio_thread', 'radio_sms', 'radio_mpu', 'musique' => 'radio',
    default => (string) ($mobileView ?? ''),
};
$player = trim((string) ($mobileLink['player_name'] ?? ''));
$langHref = static fn (string $code): string => function_exists('locale_switch_url') ? (string) locale_switch_url($code) : (string) url('locale/' . $code);
$curLang = function_exists('locale') ? (string) locale() : 'fr';
$loggedIn = class_exists(\App\Core\Session::class) && (int) (\App\Core\Session::get('user_id') ?? 0) > 0;
$until = '';
if (!empty($expiresAt)) {
    $ts = strtotime($expiresAt . ' UTC');
    $until = $ts ? date('H:i', $ts) : '';
}
?>
<!DOCTYPE html>
<html lang="<?= $h($curLang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#050505">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <?php if (!empty($csrf)): ?><meta name="csrf-token" content="<?= $h($csrf) ?>"><?php endif; ?>
  <title><?= $h($title ?? 'Sur mon téléphone — Athena') ?></title>
  <?php if ($needsMap): ?>
  <link rel="stylesheet" href="<?= $h(url('assets/vendor/leaflet-1.9.4/leaflet.css')) ?>">
  <link rel="stylesheet" href="<?= $h(url('assets/css/atak-overwatch-mapkit.css')) ?>?v=<?= $h($ver) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= $h(url('assets/css/atak-m.css')) ?>?v=<?= $h($ver) ?>">
</head>
<body class="mm-body" data-view="<?= $h($mobileView ?? '') ?>" data-base="<?= $h(rtrim(url(''), '/')) ?>">
<?php if (!empty($mobileLink)): ?>
  <header class="mm-top">
    <a class="mm-top__brand" href="<?= $h(url('m')) ?>"><img src="<?= $h(url('assets/brand/athena-embleme-blanc.svg')) ?>" alt="" width="26" height="22"><span class="mm-top__mark">ATHENA<em>.</em></span></a>
    <span class="mm-top__app"><?= $h(explode(' — ', (string) ($title ?? ''))[0]) ?></span>
    <nav class="mm-lang" aria-label="Langue">
      <?php foreach (['fr', 'en'] as $code): ?>
        <?php if ($curLang === $code): ?><span class="on" aria-current="true"><?= strtoupper($code) ?></span><?php else: ?><a href="<?= $h($langHref($code)) ?>" hreflang="<?= $code ?>"><?= strtoupper($code) ?></a><?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="mm-top__who">
      <?php if ($player !== ''): ?><span translate="no"><?= $h($player) ?></span><?php elseif ($loggedIn): ?><a href="<?= $h(url('account')) ?>">Compte</a><?php endif; ?>
      <?php if ($until !== ''): ?><small>jusqu’à <?= $h($until) ?></small><?php endif; ?>
    </div>
  </header>
  <nav class="mm-tabs" aria-label="Apps">
    <?php foreach ($tabs as $key => [$path, $label]): ?>
      <a class="mm-tab<?= $activeTab === $key ? ' is-active' : '' ?>" href="<?= $h(url($path)) ?>"><?= $h($label) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
  <main class="mm-main">
