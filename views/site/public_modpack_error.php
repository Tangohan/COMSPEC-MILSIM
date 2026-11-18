<?php $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>
<div class="mp" lang="<?= $e(locale()) ?>" translate="no">
<section class="mp-wrap mp-error" aria-labelledby="mp-error-title">
    <h1 id="mp-error-title" class="hi-display"><?= $e(__('modpack.error_title')) ?></h1>
    <p><?= $e($message) ?></p>
    <a class="mp-primary" href="<?= $e(url('modpacks')) ?>"><?= $e(__('modpack.back')) ?> <span class="mp-arrow" aria-hidden="true"></span></a>
</section>
</div>
