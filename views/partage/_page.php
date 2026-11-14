<?php
declare(strict_types=1);

/**
 * Coque de la page « Fichiers partagés » (/partage et /back-office/partage, FileShareController::index) : page autonome au
 * même habillage que l'extranet JNET (jnet_athena.css) : en-tête avec rubriques, pastille du compte, pleine largeur.
 * Le contenu est views/partage/index.php ; les messages s'affichent dans la page, jamais en fenêtre.
 *
 * @var string $title
 * @var string $fsTenantName
 * @var string $fsDisplayName
 * @var string $fsCallsign
 * @var bool $fsCanTba
 * @var int $fsUnreadMail
 * @var bool $fsBo
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$community = trim((string) ($fsTenantName ?? ''));
$who = trim((string) (($fsCallsign ?? '') !== '' ? $fsCallsign : ($fsDisplayName ?? '')));
$unreadMail = (int) ($fsUnreadMail ?? 0);
$pad = static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');

// Les rubriques de l'extranet, plus celle-ci : la page y garde sa place, comme Photos et Documents d'une unité y renvoient.
$tabs = [
    ['label' => 'Mon extranet', 'path' => 'jnet/espaces'],
    ['label' => 'Espace commun', 'path' => 'jnet?commun=1'],
    ['label' => 'Fiche d’unité', 'path' => 'jnet/unite'],
    ['label' => 'Personnel', 'path' => 'jnet/personnel'],
    ['label' => 'Opérations', 'path' => 'jnet/operations'],
    ['label' => 'Renseignement', 'path' => 'jnet/renseignement'],
    ['label' => 'Cibles', 'path' => 'jnet/cibles'],
    ['label' => 'Exploitation', 'path' => 'jnet/exploitation'],
    ['label' => 'Bibliothèque', 'path' => 'jnet/bibliotheque'],
    ['label' => 'Messagerie', 'path' => 'jnet/courrier', 'count' => $unreadMail],
    ['label' => 'Système', 'path' => 'jnet/systeme'],
    ['label' => 'Fichiers', 'path' => 'partage', 'on' => true],
];
?>
<!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#050505">
    <title><?= $h($title ?? 'Fichiers partagés') ?> — JNET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,700;1,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_athena.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/partage.css')) ?>">
</head>
<body class="ja fp">
<a class="ja-skip" href="#fp-main">Aller au contenu</a>

<header class="ja-hd ja-hd--rows">
    <a class="ja-brand" href="<?= $h(url('jnet')) ?>"><i aria-hidden="true">A</i><span>ATHENA<em>.</em></span></a>
    <nav class="ja-tabs" aria-label="Rubriques de l’extranet">
        <?php foreach ($tabs as $i => $tab): ?>
            <?php $count = (int) ($tab['count'] ?? 0); $on = !empty($tab['on']); ?>
            <a class="ja-tab<?= $on ? ' is-on' : '' ?>" href="<?= $h(url((string) $tab['path'])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><span class="ja-mono"><?= $pad($i) ?></span><?= $h((string) $tab['label']) ?><?php if ($count > 0): ?><em><?= min(99, $count) ?></em><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="ja-hd__r">
        <span class="ja-who">extranet<?= $community !== '' ? ' · ' . $h($community) : '' ?></span>
        <?= \App\Support\JnetUi::account($who) ?>
        <a class="ja-ib" href="<?= $h(url('dashboard')) ?>"><?= \App\Support\JnetUi::icon('home') ?><span class="ja-ib__t">Accueil Athena</span></a>
        <?php if (!empty($fsCanTba)): ?><a class="ja-ib" href="<?= $h(url('back-office')) ?>"><?= \App\Support\JnetUi::icon('manage') ?><span class="ja-ib__t">Gestion</span></a><?php endif; ?>
        <form method="post" action="<?= $h(url('logout')) ?>"><?= \App\Core\Csrf::field() ?><button class="ja-ib" type="submit" title="Déconnexion"><?= \App\Support\JnetUi::icon('logout') ?><span class="ja-ib__t">Déconnexion</span></button></form>
    </div>
</header>

<main class="ja-main fp-main" id="fp-main">
    <?php if ($error): ?><div class="ja-flash is-bad" role="alert"><?= $h((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="ja-flash" role="status"><?= $h((string) $success) ?></div><?php endif; ?>
    <?php require base_path('views/partage/index.php'); ?>
</main>

<footer class="ja-foot">
    <span>Rien n’est visible sans partage · accès journalisé</span>
    <span>Fichiers<?= $community !== '' ? ' · ' . $h($community) : '' ?></span>
</footer>
<script src="<?= $h(asset_url('assets/js/partage.js')) ?>" defer></script>
</body>
</html>
