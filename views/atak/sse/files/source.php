<?php
declare(strict_types=1);
/**
 * Source citée par un recoupement (compte rendu, fiche de renseignement, contrôle, extraction, annotation) : texte entier,
 * dans la limite de l'habilitation de la session, avec retour à la fiche du sujet.
 *
 * @var array<string,mixed> $source
 * @var int $subjectId
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
?>
<div class="fx">
    <div class="fx-th">
        <div><h1 translate="no"><?= $h($source['title']) ?></h1><p><?= $h($source['label']) ?><?= ($source['at'] ?? '') !== '' ? ' · ' . $h(substr((string) $source['at'], 0, 16)) . 'Z' : '' ?> · niveau de diffusion <?= $h($source['scope']) ?>.</p></div>
        <div class="fx-acts">
            <?php if ($subjectId > 0): ?><a class="btn" href="<?= $h(url('atak/sse/fichiers/sujet/' . $subjectId) . '#recoupements') ?>">Retour à la fiche</a><?php endif; ?>
            <?php if ($source['type'] === 'field_note'): ?><a class="btn" href="<?= $h(url('atak/sse/fiches/' . (int) $source['id'])) ?>">Ouvrir la fiche de renseignement</a><?php endif; ?>
            <?php if ($source['type'] === 'check'): ?><a class="btn" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $source['id'])) ?>">Ouvrir le contrôle</a><?php endif; ?>
            <?php if ($source['extraction_id'] !== null): ?><a class="btn" href="<?= $h(url('atak/sse/fichiers/telephones/' . (int) $source['extraction_id'])) ?>">Ouvrir l’extraction</a><?php endif; ?>
        </div>
    </div>
    <article class="fx-card"><div class="fx-ds" style="border-top:0"><p style="white-space:pre-wrap;color:var(--fx-tx);font-size:14px" translate="no"><?= $h($source['text']) ?></p></div></article>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
