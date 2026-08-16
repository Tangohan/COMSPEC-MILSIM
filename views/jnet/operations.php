<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$operations = is_array($operations ?? null) ? $operations : [];
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Mission board — opérations en cours</h2>
        <span class="jnet-meta"><?= count($operations) ?> engagement<?= count($operations) > 1 ? 's' : '' ?></span>
    </div>
    <div class="jnet-panel__body jnet-ops-board">
        <?php foreach ($operations as $op): ?>
            <article class="jnet-mission-card">
                <header>
                    <h3><?= $h((string) ($op['title'] ?? '')) ?></h3>
                    <span class="jnet-badge <?= ($op['state_key'] ?? '') === 'active' ? 'jnet-badge--ok' : 'jnet-badge--watch' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                </header>
                <div class="jnet-mission-card__elements">
                    <?php foreach (($op['facts'] ?? []) as $fact): ?>
                        <div><strong><?= $h((string) ($fact['label'] ?? '')) ?></strong><span><?= $h((string) ($fact['value'] ?? '')) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <?php if (is_array($op['checklist'] ?? null)): ?>
                    <p class="jnet-meta">Points de contrôle obligatoires : <?= (int) $op['checklist']['done'] ?> sur <?= (int) $op['checklist']['required'] ?> validés.</p>
                <?php endif; ?>
                <div class="jnet-mail__actions">
                    <a class="jnet-btn jnet-btn--accent" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">Ouvrir l’opération</a>
                    <a class="jnet-btn" href="<?= $h(url('back-office/tableau-operationnel')) ?>">Ouvrir Athena</a>
                    <a class="jnet-btn" href="<?= $h(url('atak')) ?>">Ouvrir ATAK</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($operations === []): ?>
            <div class="jnet-empty">
                <p>Aucune opération n’est engagée en ce moment.</p>
                <p>Ce mur reprend les missions et dispositifs ouverts sur le tableau opérationnel. Les activités de formation et les informations pratiques restent sur leurs propres écrans.</p>
                <p><a class="jnet-btn" href="<?= $h(url('back-office/tableau-operationnel')) ?>">Ouvrir le tableau opérationnel</a></p>
            </div>
        <?php endif; ?>
    </div>
</section>
<p class="jnet-meta">JNET = connaissance &amp; unité · Athena = commandement de l’opération · ATAK = carte temps réel · SSE = acquisition terrain.</p>
