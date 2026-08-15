<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$operations = is_array($operations ?? null) ? $operations : [];
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Mission board — opérations en cours</h2>
    </div>
    <div class="jnet-panel__body jnet-ops-board">
        <?php foreach ($operations as $op): ?>
            <article class="jnet-mission-card">
                <header>
                    <h3><?= $h((string) ($op['title'] ?? '')) ?></h3>
                    <span class="jnet-badge <?= ($op['state_key'] ?? '') === 'active' ? 'jnet-badge--ok' : 'jnet-badge--watch' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                </header>
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
                    <a class="jnet-btn jnet-btn--accent" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">Ouvrir l’opération</a>
                    <a class="jnet-btn" href="<?= $h(url('back-office/tableau-operationnel')) ?>">Ouvrir Athena</a>
                    <a class="jnet-btn" href="<?= $h(url('atak')) ?>">Ouvrir ATAK</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($operations === []): ?>
            <p class="jnet-empty">Aucune opération active.</p>
        <?php endif; ?>
    </div>
</section>
<p class="jnet-meta">JNET = connaissance & unité · Athena = commandement de l’opération · ATAK = carte temps réel · SSE = acquisition terrain.</p>
