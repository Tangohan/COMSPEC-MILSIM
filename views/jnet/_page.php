<?php
declare(strict_types=1);

/**
 * Coque commune des pages JNET hors espaces d'unité (JnetPortalController::render) : espace commun, fiche d'unité,
 * personnel, opérations, renseignement, cibles, exploitation, bibliothèque, messagerie, système. Page autonome au même
 * habillage que l'extranet (jnet_athena.css) : en-tête, rubriques numérotées, titre d'affichage ; le contenu de la page
 * (jnetInnerView) est repris tel quel par _bo_content.php.
 *
 * @var string $title
 * @var string $activeNav
 * @var string $jnetTenantName
 * @var string $jnetDisplayName
 * @var string $jnetCallsign
 * @var bool $jnetCanTba
 * @var int $jnetUnreadMail
 * @var string $jnetPageTitle
 * @var list<string> $jnetPageCss
 */

use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
// Les fiches et la messagerie allument la rubrique dont elles dépendent.
$activeNav = (string) ($activeNav ?? 'home');
$activeNav = ['mail' => 'inbox', 'mail_compose' => 'inbox', 'personnel_show' => 'personnel', 'operation_show' => 'operations', 'target_show' => 'targets'][$activeNav] ?? $activeNav;
$pageTitle = (string) ($jnetPageTitle ?? $title ?? 'JNET');
$community = trim((string) ($jnetTenantName ?? ''));
$who = trim((string) (($jnetCallsign ?? '') !== '' ? $jnetCallsign : ($jnetDisplayName ?? '')));
$unreadMail = (int) ($jnetUnreadMail ?? 0);
$pad = static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);

$tabs = [
    ['id' => 'hub', 'label' => 'Mon extranet', 'path' => 'jnet/espaces'],
    ['id' => 'home', 'label' => 'Espace commun', 'path' => 'jnet?commun=1'],
    ['id' => 'unit', 'label' => 'Fiche d’unité', 'path' => 'jnet/unite'],
    ['id' => 'personnel', 'label' => 'Personnel', 'path' => 'jnet/personnel'],
    ['id' => 'operations', 'label' => 'Opérations', 'path' => 'jnet/operations'],
    ['id' => 'intelligence', 'label' => 'Renseignement', 'path' => 'jnet/renseignement'],
    ['id' => 'targets', 'label' => 'Cibles', 'path' => 'jnet/cibles'],
    ['id' => 'exploitation', 'label' => 'Exploitation', 'path' => 'jnet/exploitation'],
    ['id' => 'library', 'label' => 'Bibliothèque', 'path' => 'jnet/bibliotheque'],
    ['id' => 'inbox', 'label' => 'Messagerie', 'path' => 'jnet/courrier', 'count' => $unreadMail],
    ['id' => 'system', 'label' => 'Système', 'path' => 'jnet/systeme'],
];

$leads = [
    'home' => 'La situation de l’organisation : ses unités, ses échanges et ses opérations.',
    'unit' => 'L’organigramme et les unités de la communauté.',
    'personnel' => 'Retrouver un camarade, sa fonction, sa section.',
    'operations' => 'Les missions et dispositifs ouverts en ce moment.',
    'intelligence' => 'Le flux de renseignement de la communauté.',
    'targets' => 'Les dossiers d’intérêt prioritaires.',
    'exploitation' => 'Les prises de terrain et leur exploitation.',
    'library' => 'Consignes, doctrines et documents publiés.',
    'inbox' => 'Messages reçus et envoyés dans l’extranet.',
    'system' => 'Votre point d’entrée par défaut et la bascule entre espaces.',
];
$lead = (string) ($leads[$activeNav] ?? '');
// Ces pages ouvrent sur leur propre titre (unité, personne, dossier, organisation).
$ownHead = in_array((string) ($jnetInnerView ?? ''), ['jnet.home', 'jnet.unit', 'jnet.personnel_show', 'jnet.target_show'], true);
$css = is_array($jnetPageCss ?? null) ? $jnetPageCss : ['jnet_portal.css', 'jnet_spaces.css'];
?>
<!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#050505">
    <title><?= $h($pageTitle) ?> — JNET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,700;1,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_athena.css')) ?>">
    <?php foreach ($css as $file): ?>
        <link rel="stylesheet" href="<?= $h(asset_url('assets/css/' . $file)) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_pages.css')) ?>">
</head>
<body class="ja jp">
<a class="ja-skip" href="#jp-main">Aller au contenu</a>

<header class="ja-hd ja-hd--rows">
    <a class="ja-brand" href="<?= $h(url('jnet')) ?>"><i aria-hidden="true">A</i><span>ATHENA<em>.</em></span></a>
    <nav class="ja-tabs" aria-label="Rubriques de l’extranet">
        <?php foreach ($tabs as $i => $tab): ?>
            <?php $count = (int) ($tab['count'] ?? 0); $on = $activeNav === $tab['id']; ?>
            <a class="ja-tab<?= $on ? ' is-on' : '' ?>" href="<?= $h(url((string) $tab['path'])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><span class="ja-mono"><?= $pad($i) ?></span><?= $h((string) $tab['label']) ?><?php if ($count > 0): ?><em><?= min(99, $count) ?></em><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="ja-hd__r">
        <span class="ja-who">extranet<?= $community !== '' ? ' · ' . $h($community) : '' ?></span>
        <?= JnetUi::account($who) ?>
        <a class="ja-ib" href="<?= $h(url('dashboard')) ?>"><?= JnetUi::icon('home') ?><span class="ja-ib__t">Accueil Athena</span></a>
        <?php if (!empty($jnetCanTba)): ?><a class="ja-ib" href="<?= $h(url('back-office')) ?>"><?= JnetUi::icon('manage') ?><span class="ja-ib__t">Gestion</span></a><?php endif; ?>
        <form method="post" action="<?= $h(url('logout')) ?>"><?= \App\Core\Csrf::field() ?><button class="ja-ib" type="submit" title="Déconnexion"><?= JnetUi::icon('logout') ?><span class="ja-ib__t">Déconnexion</span></button></form>
    </div>
</header>

<main class="ja-main jp-main" id="jp-main">
    <?php if (!$ownHead): ?>
    <header class="jp-head">
        <h1 class="ja-disp ja-disp--md"><?= $h($pageTitle) ?></h1>
        <?php if ($lead !== ''): ?><p class="ja-lead"><?= $h($lead) ?></p><?php endif; ?>
    </header>
    <?php else: ?>
    <div class="jp-gap"></div>
    <?php endif; ?>
    <?php require base_path('views/jnet/_bo_content.php'); ?>
</main>

<footer class="ja-foot">
    <span>Accès réservé aux membres affectés · journalisé</span>
    <span>JNET<?= $community !== '' ? ' · ' . $h($community) : '' ?></span>
</footer>
</body>
</html>
