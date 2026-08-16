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
$canTba = !empty($jnetCanTba);
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$who = trim($callsign !== '' ? $callsign : $displayName);
$unitLabel = $tenantName !== '' ? $tenantName : 'Unité';
$zulu = gmdate('H:i:s') . 'Z';

$navGroups = [
    ['label' => 'Situation', 'items' => [
        ['id' => 'home', 'label' => 'Tableau d’unité', 'path' => 'jnet'],
        ['id' => 'unit', 'label' => 'Fiche d’unité', 'path' => 'jnet/unite'],
        ['id' => 'personnel', 'label' => 'Personnel', 'path' => 'jnet/personnel'],
    ]],
    ['label' => 'Conduite', 'items' => [
        ['id' => 'operations', 'label' => 'Opérations', 'path' => 'jnet/operations'],
    ]],
    ['label' => 'Renseignement', 'items' => [
        ['id' => 'intelligence', 'label' => 'Flux de renseignement', 'path' => 'jnet/renseignement'],
        ['id' => 'targets', 'label' => 'Cibles prioritaires', 'path' => 'jnet/cibles'],
        ['id' => 'exploitation', 'label' => 'Exploitation', 'path' => 'jnet/exploitation'],
    ]],
    ['label' => 'Ressources', 'items' => [
        ['id' => 'library', 'label' => 'Bibliothèque', 'path' => 'jnet/bibliotheque'],
        ['id' => 'inbox', 'label' => 'Messagerie', 'path' => 'jnet/courrier', 'count' => $unreadMail],
        ['id' => 'system', 'label' => 'Système', 'path' => 'jnet/systeme'],
    ]],
];
$navIndex = 0;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_portal.css')) ?>?v=202608161612">
</head>
<body class="jnet-iw">

<div class="jnet-app">
    <header class="jnet-topbar" role="banner">
        <div class="jnet-brand">
            <strong>JNET <sup>Bêta</sup></strong>
            <span>Extranet d’unité</span>
        </div>

        <form class="jnet-search" method="get" action="<?= $h(url('jnet/personnel')) ?>" role="search">
            <label class="sr-only" for="jnet-q">Rechercher dans l’annuaire</label>
            <input id="jnet-q" name="filtre" type="search" placeholder="Rechercher un membre, une fonction, une section…"
                   value="<?= $h((string) ($_GET['filtre'] ?? '')) ?>" autocomplete="off">
            <button type="submit" aria-label="Lancer la recherche">⌕</button>
        </form>

        <div class="jnet-status" aria-label="État des liaisons">
            <span class="jnet-pill jnet-pill--ok" title="Liaison Athena">Athena</span>
            <span class="jnet-pill jnet-pill--ok" title="Carte tactique">ATAK</span>
            <span class="jnet-pill" title="Terminal d’unité">Terminal <?= $h($nodeId) ?></span>
            <span class="jnet-zulu" title="Heure Zulu"><?= $h($zulu) ?></span>
            <?php if ($who !== ''): ?>
                <span class="jnet-session" title="Utilisateur connecté"><?= $h($who) ?></span>
            <?php endif; ?>
        </div>

        <div class="jnet-actions">
            <a class="jnet-btn" href="<?= $h(url('jnet/courrier')) ?>">Messagerie<?= $unreadMail > 0 ? ' <b>' . min(99, $unreadMail) . '</b>' : '' ?></a>
            <a class="jnet-btn jnet-btn--solid" href="<?= $h(url('jnet/operations')) ?>">Opérations</a>
            <?php if ($canTba): ?>
                <a class="jnet-btn jnet-btn--ghost" href="<?= $h(url('back-office')) ?>">Administration</a>
            <?php endif; ?>
            <a class="jnet-btn jnet-btn--ghost" href="<?= $h(url('dashboard')) ?>">Quitter</a>
        </div>
    </header>

    <div class="jnet-classbar" role="status">
        <strong>Diffusion restreinte</strong>
        <span>Réservé au personnel de l’unité — consultations journalisées</span>
        <em><?= $h($dtg) ?></em>
    </div>

    <div class="jnet-shell">
        <aside class="jnet-nav" aria-label="Navigation JNET">
            <p class="jnet-nav-kicker">Extranet <?= $h($unitLabel) ?></p>

            <?php foreach ($navGroups as $group): ?>
                <p class="jnet-nav-section"><?= $h((string) $group['label']) ?></p>
                <?php foreach ($group['items'] as $item): ?>
                    <?php $navIndex++; $count = (int) ($item['count'] ?? 0); ?>
                    <a href="<?= $h(url((string) $item['path'])) ?>" class="<?= $activeNav === $item['id'] ? 'is-active' : '' ?>">
                        <b><?= str_pad((string) $navIndex, 2, '0', STR_PAD_LEFT) ?></b>
                        <span><?= $h((string) $item['label']) ?></span>
                        <?php if ($count > 0): ?><i><?= min(99, $count) ?></i><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <div class="jnet-nav-foot">
                <span>Unité rattachée</span>
                <strong><?= $h($unitLabel) ?></strong>
            </div>
        </aside>

        <main class="jnet-main">
            <aside class="jnet-beta">
                <span class="jnet-beta__tag">Version bêta</span>
                <p>
                    Le portail est encore en construction : la structure est posée, mais une bonne partie
                    des contenus affichés sont des exemples de démonstration. Ne vous en servez pas encore
                    comme référence pour la conduite des opérations — passez par le tableau de bord, la carte
                    ou la messagerie habituels. Les sections seront fiabilisées une par une.
                </p>
            </aside>
            <?php if ($error): ?><div class="jnet-flash jnet-flash--err"><?= $h((string) $error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="jnet-flash jnet-flash--ok"><?= $h((string) $success) ?></div><?php endif; ?>
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
</div>
</body>
</html>
