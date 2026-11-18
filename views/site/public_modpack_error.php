<?php $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>
<div class="mp" lang="fr" translate="no">
<section class="mp-wrap mp-error" aria-labelledby="mp-error-title">
    <h1 id="mp-error-title" class="hi-display">Téléchargement</h1>
    <p><?= $e($message) ?></p>
    <a class="mp-primary" href="<?= $e(url('modpacks')) ?>">Retour au modpack <span class="mp-arrow" aria-hidden="true"></span></a>
</section>
</div>
