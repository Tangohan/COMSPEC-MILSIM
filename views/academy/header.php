<!doctype html>
<html lang="<?= $h($lang) ?>">
<head>
<?php
$lmsTitle = isset($space) ? $t($space) . ' · ' . $t('brand') : $t('brand');
require base_path('views/training/partials/lms_head.php');
?>
<meta name="description" content="<?= $h($t('hero_text')) ?>">
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/design-system.css')) ?>">
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy.css')) ?>">
<?php $customTopbar = $customTopbar ?? false; /* Salle de cours et Studio : maquettes dédiées, avec leur propre barre d'en-tête. */ ?>
<?php if ($customTopbar): ?><link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-workspace.css')) ?>"><?php endif ?>
<?php if (in_array($space ?? '', ['dashboard', 'catalogue'], true)): ?><link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-offer.css')) ?>"><?php endif ?>
<?php if (in_array($space ?? '', ['studio', 'shared'], true)): ?><link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-studio.css')) ?>"><?php endif ?>
<script src="<?= $h(asset_url('assets/js/academy.js')) ?>" defer></script>
<?php if (in_array($space ?? '', ['instructor', 'droits', 'studio'], true)): /* Espace instructeur, droits d'accès et réglages d'accès d'un cours. */ ?><link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-instructor.css')) ?>"><script src="<?= $h(asset_url('assets/js/academy-access.js')) ?>" defer></script><?php endif ?>
<?php if (!empty($queue)): /* File « à traiter » de l'encadrement (tableau de bord, espace instructeur). */ ?><?php if (($space ?? '') === 'dashboard'): ?><link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-instructor.css')) ?>"><?php endif ?><script src="<?= $h(asset_url('assets/js/academy-queue.js')) ?>" defer></script><?php endif ?>
<?php if (($space ?? '') === 'suivi'): /* Tableau de suivi : même langage visuel que le suivi CQB de l'aperçu public ; fenêtres de validation de la file. */ ?><link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-instructor.css')) ?>"><link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-tracker.css')) ?>"><script src="<?= $h(asset_url('assets/js/academy-queue.js')) ?>" defer></script><script src="<?= $h(asset_url('assets/js/academy-tracker.js')) ?>" defer></script><?php endif ?>
<?php if ($customTopbar): ?><script type="module" src="<?= $h(asset_url('assets/js/academy-deck.js')) ?>"></script><?php endif ?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-richtext.css')) ?>">
<script src="<?= $h(asset_url('assets/js/academy-richtext.js')) ?>" defer></script>
<?php if ($customTopbar): /* Mode nuit de la salle de cours et du Studio : même préférence que le reste du site (athena.bo.theme), posée avant l'affichage. */ ?>
<script>(function(){var p='auto';try{p=localStorage.getItem('athena.bo.theme')||'auto';}catch(e){}var d=p==='dark'||(p==='auto'&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.setAttribute('data-bo-theme',d?'dark':'light');})();</script>
<?php endif ?>
</head>
<body class="academy-app<?= $customTopbar ? ' academy-workspace academy-workspace--' . $h($space) : '' ?>" data-rt-close="<?= $h($t('rt_close')) ?>">
<a class="skip" href="#main"><?= $h($t('skip_to_content')) ?></a>
<?php if (!$customTopbar): ?>
<?php /* En-tête unique des pages Academy : marque, mention de préversion (ouvre l’avertissement complet), organisation, langue, retour vers ATHENA. */ ?>
<header class="academy-topbar">
<a class="academy-topbar__brand" href="<?= $h($link()) ?>">ATHENA <span>Academy</span></a>
<button type="button" class="academy-topbar__release" data-open-disclaimer title="<?= $h($t('disclaimer')) ?>"><?= $h($t('prerelease')) ?></button>
<?php if (!empty($tenant)): ?><span class="academy-context-label"><?= $h($tenant['name']??$tenant['slug']??'') ?><?php if (!empty($account['display_name'])): ?> · <?= $h($account['display_name']) ?><?php endif ?></span><?php endif ?>
<nav class="languages" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr','en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav>
<a class="academy-topbar__back" href="<?= $h(url('dashboard')) ?>"><span aria-hidden="true">←</span> <?= $h($t('back_athena')) ?></a>
</header>
<?php endif ?>
