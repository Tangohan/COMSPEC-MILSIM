<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$op = is_array($operation ?? null) ? $operation : [];
?>
<article class="jnet-panel jnet-mission-card">
    <div class="jnet-panel__head">
        <h2><?= $h((string) ($op['title'] ?? 'Opération')) ?></h2>
        <span class="jnet-badge jnet-badge--ok"><?= $h((string) ($op['state'] ?? '')) ?></span>
    </div>
    <div class="jnet-panel__body">
        <p class="jnet-meta">Zone : <?= $h((string) (($op['zone'] ?? '') !== '' ? $op['zone'] : '—')) ?></p>
        <div class="jnet-mission-card__elements">
            <?php foreach (($op['elements'] ?? []) as $el): ?>
                <div><strong><?= $h((string) ($el['label'] ?? '')) ?></strong><span><?= $h((string) ($el['state'] ?? '')) ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="jnet-statstrip jnet-statstrip--inline">
            <div><span>Personnel</span><strong><?= (int) ($op['personnel'] ?? 0) ?></strong></div>
            <div><span>Objectifs</span><strong><?= (int) ($op['objectives'] ?? 0) ?></strong></div>
            <div><span>PIR</span><strong><?= (int) ($op['pir'] ?? 0) ?></strong></div>
            <div><span>Intel ouvert</span><strong><?= (int) ($op['openIntel'] ?? 0) ?></strong></div>
        </div>
        <div class="jnet-mail__actions">
            <a class="jnet-btn jnet-btn--accent" href="<?= $h((string) ($op['href'] ?? url('back-office/tableau-operationnel'))) ?>">Ouvrir dans Athena</a>
            <a class="jnet-btn" href="<?= $h(url('atak')) ?>">Carte ATAK</a>
            <a class="jnet-btn" href="<?= $h(url('jnet/operations')) ?>">Retour au board</a>
        </div>
    </div>
</article>
