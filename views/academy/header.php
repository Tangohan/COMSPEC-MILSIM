<!doctype html>
<html lang="<?= $h($lang) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="<?= $h($t('hero_text')) ?>"><title><?= $h(isset($space) ? $t($space) . ' · ' . $t('brand') : $t('brand')) ?></title><link rel="stylesheet" href="<?= $h(url('assets/css/academy.css')) ?>"><script src="<?= $h(url('assets/js/academy.js')) ?>" defer></script></head>
<body class="<?= isset($space) ? 'academy-app' : 'academy-public' ?>">
<a class="skip" href="#main"><?= $h($t('next')) ?></a>
<div class="release"><div><span class="status-dot"></span><strong><?= $h($t('prerelease')) ?></strong><span><?= $h($t('disclaimer')) ?></span></div></div>
<header class="topbar">
<a class="brand" href="<?= $h($link()) ?>"><span class="emblem" aria-hidden="true">Λ</span><span>ATHENA <b>ACADEMY</b><small><?= $h($t('tagline')) ?></small></span></a>
<?php if (!isset($space)): ?><nav class="public-nav" aria-label="<?= $h($t('home')) ?>"><a href="#program"><?= $h($t('program')) ?></a><a href="#method"><?= $h($t('method')) ?></a><a href="#access"><?= $h($t('access')) ?></a></nav><?php endif ?>
<div class="top-actions"><nav class="languages" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr','en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav><a class="button small outline" href="<?= $h(isset($space) ? url('dashboard') : url('login')) ?>"><?= $h($t(isset($space) ? 'back_athena' : 'login')) ?> <span aria-hidden="true">↗</span></a></div>
</header>
