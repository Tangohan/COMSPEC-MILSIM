<?php
declare(strict_types=1);
/**
 * Coque JNET Extranet — bureau numérique d’unité.
 * @var string $title
 * @var string $activeNav
 * @var string $jnetTenantName
 * @var string $jnetDisplayName
 * @var string $jnetCallsign
 * @var string $jnetNodeId
 * @var bool $jnetCanTba
 * @var int $jnetUnreadMail
 * @var string $jnetDtg
 * @var string $jnetContentView
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$activeNav = (string) ($activeNav ?? 'home');
$pageTitle = (string) ($title ?? 'JNET');
$tenantName = (string) ($jnetTenantName ?? '');
$displayName = (string) ($jnetDisplayName ?? '');
$callsign = (string) ($jnetCallsign ?? '');
$nodeId = (string) ($jnetNodeId ?? 'NODE');
$unreadMail = (int) ($jnetUnreadMail ?? 0);
$dtg = (string) ($jnetDtg ?? strtoupper(gmdate('dHi') . 'Z' . gmdate('M y')));
$contentView = (string) ($jnetContentView ?? 'jnet.home');
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$who = trim($callsign !== '' ? $callsign : $displayName);

$nav = [
    ['id' => 'home', 'label' => 'Accueil', 'path' => 'jnet'],
    ['id' => 'unit', 'label' => 'Unité', 'path' => 'jnet/unite'],
    ['id' => 'personnel', 'label' => 'Personnel', 'path' => 'jnet/personnel'],
    ['id' => 'operations', 'label' => 'Opérations', 'path' => 'jnet/operations'],
    ['id' => 'intelligence', 'label' => 'Renseignement', 'path' => 'jnet/renseignement'],
    ['id' => 'targets', 'label' => 'Cibles', 'path' => 'jnet/cibles'],
    ['id' => 'exploitation', 'label' => 'Exploitation', 'path' => 'jnet/exploitation'],
    ['id' => 'library', 'label' => 'Bibliothèque', 'path' => 'jnet/bibliotheque'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $h($pageTitle) ?> — JNET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_portal.css')) ?>?v=202608152355">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_home.css')) ?>?v=202608152355">
</head>
<body class="jnet-shell">
<div class="jnet-app jnet-app--topnav">
    <header class="jnet-chrome">
        <div class="jnet-chrome__classif">
            <span>SECRET // REL COMSPEC</span>
            <strong>JNET</strong>
        </div>
        <div class="jnet-chrome__brand">
            <div>
                <p class="jnet-chrome__network">JOINT INTELLIGENCE NETWORK</p>
                <p class="jnet-chrome__unit"><?= $h($tenantName !== '' ? $tenantName : 'Unité') ?></p>
            </div>
            <div class="jnet-chrome__meta">
                <span><?= $h($dtg) ?></span>
                <span>Nœud <?= $h($nodeId) ?></span>
                <?php if ($who !== ''): ?><span><?= $h($who) ?></span><?php endif; ?>
            </div>
        </div>
        <nav class="jnet-chrome__nav" aria-label="Navigation JNET">
            <?php foreach ($nav as $item): ?>
                <a class="jnet-chrome__link<?= $activeNav === $item['id'] ? ' is-active' : '' ?>" href="<?= $h(url($item['path'])) ?>"><?= $h($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="jnet-chrome__tools">
            <form class="jnet-search" method="get" action="<?= $h(url('jnet/personnel')) ?>" role="search">
                <label class="sr-only" for="jnet-q">Recherche</label>
                <input id="jnet-q" type="search" name="filtre" placeholder="Recherche JNET…" value="">
            </form>
            <a class="jnet-tool" href="<?= $h(url('dashboard')) ?>">Athena</a>
            <a class="jnet-tool" href="<?= $h(url('atak')) ?>">ATAK</a>
            <a class="jnet-tool" href="<?= $h(url('jnet/courrier')) ?>">Messagerie<?= $unreadMail > 0 ? ' (' . min(99, $unreadMail) . ')' : '' ?></a>
            <a class="jnet-tool jnet-tool--ghost" href="<?= $h(url('jnet/systeme')) ?>">Système</a>
        </div>
    </header>

    <main class="jnet-main">
        <?php if ($error || $success): ?>
            <div class="jnet-flash<?= $error ? ' jnet-flash--err' : ' jnet-flash--ok' ?>">
                <?= $h((string) ($error ?: $success)) ?>
            </div>
        <?php endif; ?>
        <div class="jnet-stage">
            <?php
            $viewFile = base_path('views/' . str_replace('.', '/', $contentView) . '.php');
            if (is_file($viewFile)) {
                require $viewFile;
            } else {
                echo '<p class="jnet-empty">Écran indisponible.</p>';
            }
            ?>
        </div>
    </main>
</div>
<style>.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}</style>
</body>
</html>
