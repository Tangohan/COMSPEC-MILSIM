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
<?php
$lmsAthenaBannerMessage = $t('brand');
$lmsAthenaBannerReturnLabel = $t('back_athena');
require base_path('views/partials/lms_athena_module_banner.php');
?>
<div class="academy-module-toolbar"><a href="<?= $h($link()) ?>"><?= $h($t('home')) ?> / ATHENA Academy</a><?php if (!empty($tenant)): ?><span class="academy-context-label"><?= $h($tenant['name']??$tenant['slug']??'') ?><?php if (!empty($account['display_name'])): ?> · <?= $h($account['display_name']) ?><?php endif ?></span><?php endif ?><nav class="languages" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr','en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav></div>
<div class="release"><strong><?= $h($t('prerelease')) ?></strong><p><?= $h($t('disclaimer')) ?></p></div>
