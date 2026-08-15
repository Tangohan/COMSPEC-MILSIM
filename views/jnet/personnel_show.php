<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$p = is_array($person ?? null) ? $person : [];
$photo = $p['photo'] ?? null;
$initials = (string) ($p['initials'] ?? '?');
?>
<article class="jnet-panel jnet-record">
    <div class="jnet-panel__head">
        <h2>Fiche personnel JNET</h2>
        <a class="jnet-btn" href="<?= $h(url('jnet/personnel')) ?>">Retour</a>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-record__hero">
            <div class="jnet-avatar jnet-avatar--hero">
                <?php if (is_string($photo) && $photo !== ''): ?>
                    <img src="<?= $h($photo) ?>" alt="">
                <?php else: ?>
                    <span><?= $h($initials) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <p class="jnet-kicker"><?= $h((string) ($p['jnet_id'] ?? '')) ?></p>
                <h1><?= $h((string) ($p['name'] ?? '')) ?></h1>
                <div class="jnet-record__grid">
                    <div><span>Indicatif</span><strong><?= $h((string) ($p['callsign'] ?? '—')) ?></strong></div>
                    <div><span>Unité</span><strong><?= $h((string) ($p['unit'] ?? '—')) ?></strong></div>
                    <div><span>Fonction</span><strong><?= $h((string) ($p['function'] ?? '—')) ?></strong></div>
                    <div><span>Statut</span><strong><?= $h((string) ($p['duty_label'] ?? '—')) ?></strong></div>
                    <div><span>Grade</span><strong><?= $h((string) ($p['grade'] ?? '—')) ?></strong></div>
                    <div><span>Opération</span><strong><?= $h((string) ($p['current_op'] ?? '—')) ?></strong></div>
                </div>
            </div>
        </div>

        <section class="jnet-section">
            <h3>Qualifications</h3>
            <div class="jnet-tags">
                <?php foreach (($p['qualifications'] ?? []) as $q): ?>
                    <span><?= $h((string) $q) ?></span>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="jnet-section">
            <h3>Équipement</h3>
            <ul class="jnet-bullet"><?php foreach (($p['equipment'] ?? []) as $e): ?><li><?= $h((string) $e) ?></li><?php endforeach; ?></ul>
        </section>
        <section class="jnet-section">
            <h3>Activité</h3>
            <ul class="jnet-bullet"><?php foreach (($p['activity'] ?? []) as $e): ?><li><?= $h((string) $e) ?></li><?php endforeach; ?></ul>
        </section>
        <section class="jnet-section">
            <h3>Documents</h3>
            <ul class="jnet-bullet"><?php foreach (($p['documents'] ?? []) as $e): ?><li><?= $h((string) $e) ?></li><?php endforeach; ?></ul>
        </section>
        <section class="jnet-section">
            <h3>Historique de mission</h3>
            <ul class="jnet-bullet"><?php foreach (($p['missionHistory'] ?? []) as $e): ?><li><?= $h((string) $e) ?></li><?php endforeach; ?></ul>
        </section>
        <?php if (empty($p['demo'])): ?>
            <p class="jnet-meta">Relié au compte Athena — formations et dossier effectifs enrichiront progressivement cette fiche.</p>
        <?php endif; ?>
    </div>
</article>
