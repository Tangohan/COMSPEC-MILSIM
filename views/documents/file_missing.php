<?php
declare(strict_types=1);

$missingTitle = trim((string) ($missingTitle ?? ''));
$missingReference = trim((string) ($missingReference ?? ''));
$documentBackHref = (string) ($documentBackHref ?? url('documents'));
$referentialHref = (string) ($referentialHref ?? (url('documents') . '?category_slug=doctrine'));
$heading = (string) ($missingHeading ?? 'Document indisponible');
$lead = (string) ($missingLead ?? 'Le fichier de ce document n’est pas encore consultable. Revenez à la fiche, ou ouvrez le référentiel.');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/doctrine-referential.css'), ENT_QUOTES, 'UTF-8') ?>">

<section class="doc-missing">
    <div class="doc-missing__card">
        <p class="doc-missing__kicker">Athena · Documentation</p>
        <h1 class="doc-missing__title"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="doc-missing__lead"><?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($missingReference !== '' || $missingTitle !== ''): ?>
        <dl class="doc-missing__meta">
            <?php if ($missingReference !== ''): ?>
            <div>
                <dt>Référence</dt>
                <dd><code><?= htmlspecialchars($missingReference, ENT_QUOTES, 'UTF-8') ?></code></dd>
            </div>
            <?php endif; ?>
            <?php if ($missingTitle !== ''): ?>
            <div>
                <dt>Document</dt>
                <dd><?= htmlspecialchars($missingTitle, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>
        </dl>
        <?php endif; ?>
        <div class="doc-missing__actions">
            <a href="<?= htmlspecialchars($documentBackHref, ENT_QUOTES, 'UTF-8') ?>" class="doctrine-ref__btn doctrine-ref__btn--primary">Retour au document</a>
            <a href="<?= htmlspecialchars($referentialHref, ENT_QUOTES, 'UTF-8') ?>" class="doctrine-ref__btn">Voir le référentiel</a>
        </div>
    </div>
</section>
