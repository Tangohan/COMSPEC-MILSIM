<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$op = is_array($operation ?? null) ? $operation : [];
?>
<article class="jnet-panel jnet-mission-card">
    <div class="jnet-panel__head">
        <h2><?= $h((string) ($op['title'] ?? 'Opération')) ?></h2>
        <span class="jnet-badge <?= ($op['state_key'] ?? '') === 'active' ? 'jnet-badge--ok' : 'jnet-badge--watch' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-mission-card__elements">
            <?php foreach (($op['facts'] ?? []) as $fact): ?>
                <div><strong><?= $h((string) ($fact['label'] ?? '')) ?></strong><span><?= $h((string) ($fact['value'] ?? '')) ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php if (is_array($op['checklist'] ?? null)): ?>
            <p class="jnet-meta">Points de contrôle obligatoires : <?= (int) $op['checklist']['done'] ?> sur <?= (int) $op['checklist']['required'] ?> validés.</p>
        <?php endif; ?>
        <p class="jnet-meta">Le détail de la conduite (ordres, participants, points de contrôle) se tient dans Athena ; JNET n’en donne que l’état de surface.</p>
        <div class="jnet-mail__actions">
            <a class="jnet-btn jnet-btn--accent" href="<?= $h((string) ($op['href'] ?? url('back-office/tableau-operationnel'))) ?>">Ouvrir dans Athena</a>
            <a class="jnet-btn" href="<?= $h(url('atak')) ?>">Carte ATAK</a>
            <a class="jnet-btn" href="<?= $h(url('jnet/operations')) ?>">Retour au board</a>
        </div>
    </div>
</article>
