<?php require __DIR__ . '/init.php'; require __DIR__ . '/header.php'; ?>
<main id="main" class="app-main"><p class="eyebrow"><?= $h($t('prerelease')) ?></p><h1><?= $h($t('error_heading')) ?></h1><p><?= $h($t($message)) ?></p><a class="button" href="<?= $h($link('dashboard')) ?>"><?= $h($t('return')) ?> →</a></main>
<?php require __DIR__ . '/footer.php'; ?>
