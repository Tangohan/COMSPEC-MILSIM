<?php

declare(strict_types=1);

/**
 * En-tête de l'espace d'administration du téléphone (/m/admin) : même thème que /m, quatre onglets, session d'une heure.
 *
 * @var string $title
 * @var string $adminView
 * @var array<string, mixed>|null $adminLink
 * @var bool|null $adminSessions
 * @var string|null $csrf
 * @var string|null $expiresAt
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ver = rawurlencode(class_exists(\App\Support\BuildVersion::class) ? \App\Support\BuildVersion::id() : '1');
$tabs = [
    'admin_home' => ['m/admin', 'Accueil'],
    'admin_players' => ['m/admin/joueurs', 'Joueurs'],
];
if (!empty($adminSessions)) {
    $tabs['admin_sessions'] = ['m/admin/sessions', 'Sessions'];
}
$tabs['admin_cr'] = ['m/admin/cr', 'CR'];
$activeTab = $adminView === 'admin_player' ? 'admin_players' : $adminView;
$until = '';
if (!empty($expiresAt)) {
    $ts = strtotime($expiresAt . ' UTC');
    $until = $ts ? date('H:i', $ts) : '';
}
$loggedIn = !empty($adminLink);
$curLang = function_exists('locale') ? (string) locale() : 'fr';
$langHref = static fn (string $code): string => function_exists('locale_switch_url') ? (string) locale_switch_url($code) : (string) url('locale/' . $code);
?>
<!DOCTYPE html>
<html lang="<?= $h($curLang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#050505">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <?php if (!empty($csrf)): ?><meta name="csrf-token" content="<?= $h($csrf) ?>"><?php endif; ?>
  <title><?= $h($title ?? 'Administration — Athena') ?></title>
  <link rel="stylesheet" href="<?= $h(url('assets/css/atak-m.css')) ?>?v=<?= $h($ver) ?>">
  <link rel="stylesheet" href="<?= $h(url('assets/css/atak-m-admin.css')) ?>?v=<?= $h($ver) ?>">
</head>
<body class="mm-body" data-view="<?= $h($adminView ?? '') ?>" data-base="<?= $h(rtrim(url(''), '/')) ?>">
<?php if ($loggedIn): ?>
  <header class="mm-top">
    <a class="mm-top__brand" href="<?= $h(url('m/admin')) ?>"><img src="<?= $h(url('assets/brand/athena-embleme-blanc.svg')) ?>" alt="" width="26" height="22"><span class="mm-top__mark">ATHENA<em>.</em></span></a>
    <span class="mm-top__app">Administration</span>
    <nav class="mm-lang" aria-label="Langue">
      <?php foreach (['fr', 'en'] as $code): ?>
        <?php if ($curLang === $code): ?><span class="on" aria-current="true"><?= strtoupper($code) ?></span><?php else: ?><a href="<?= $h($langHref($code)) ?>" hreflang="<?= $code ?>"><?= strtoupper($code) ?></a><?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="mm-top__who">
      <span translate="no"><?= $h((string) ($adminLink['player_name'] ?? '')) ?></span>
      <?php if ($until !== ''): ?><small>jusqu’à <?= $h($until) ?></small><?php endif; ?>
    </div>
  </header>
  <nav class="mm-tabs" aria-label="Administration">
    <?php foreach ($tabs as $key => [$path, $label]): ?>
      <a class="mm-tab<?= $activeTab === $key ? ' is-active' : '' ?>" href="<?= $h(url($path)) ?>"><?= $h($label) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
  <main class="mm-main">
