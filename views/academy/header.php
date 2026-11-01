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
<script src="<?= $h(asset_url('assets/js/academy.js')) ?>" defer></script>
</head>
<body class="academy-app">
<a class="skip" href="#main"><?= $h($t('next')) ?></a>
<?php /* En-tête unique des pages Academy : marque, mention de préversion (ouvre l’avertissement complet), organisation, langue, retour vers ATHENA. */ ?>
<header class="academy-topbar">
<a class="academy-topbar__brand" href="<?= $h($link()) ?>">ATHENA <span>Academy</span></a>
<button type="button" class="academy-topbar__release" data-open-disclaimer title="<?= $h($t('disclaimer')) ?>"><?= $h($t('prerelease')) ?></button>
<?php if (!empty($tenant)): ?><span class="academy-context-label"><?= $h($tenant['name']??$tenant['slug']??'') ?><?php if (!empty($account['display_name'])): ?> · <?= $h($account['display_name']) ?><?php endif ?></span><?php endif ?>
<nav class="languages" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr','en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav>
<a class="academy-topbar__back" href="<?= $h(url('dashboard')) ?>"><span aria-hidden="true">←</span> <?= $h($t('back_athena')) ?></a>
</header>
