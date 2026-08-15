<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$t = is_array($target ?? null) ? $target : [];
$tab = (string) ($targetTab ?? 'profil');
$tabs = [
    'profil' => 'Profil',
    'photos' => 'Photos',
    'timeline' => 'Chronologie',
    'associates' => 'Relations',
    'locations' => 'Lieux',
    'devices' => 'Terminaux',
    'reports' => 'Rapports',
    'sse' => 'SSE',
    'analysis' => 'Analyse',
];
$id = (string) ($t['id'] ?? '');
$ini = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($t['name'] ?? 'X')) ?: 'X', 0, 2));
?>
<article class="jnet-panel jnet-record">
    <div class="jnet-panel__head">
        <h2>Dossier de renseignement cible</h2>
        <a class="jnet-btn" href="<?= $h(url('jnet/cibles')) ?>">Retour</a>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-record__hero">
            <div class="jnet-avatar jnet-avatar--hero jnet-avatar--target">
                <?php if (!empty($t['photo'])): ?>
                    <img src="<?= $h((string) $t['photo']) ?>" alt="">
                <?php else: ?>
                    <span><?= $h($ini) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <p class="jnet-kicker"><?= $h((string) ($t['kind'] ?? '')) ?> · <?= $h((string) ($t['code'] ?? '')) ?></p>
                <h1><?= $h((string) ($t['name'] ?? '')) ?></h1>
                <div class="jnet-tags">
                    <span class="jnet-prio jnet-prio--<?= strtolower((string) ($t['priority'] ?? 'low')) ?>"><?= $h((string) ($t['priority'] ?? '')) ?></span>
                    <span>Confiance <?= (int) ($t['confidence'] ?? 0) ?>%</span>
                    <span><?= $h((string) ($t['org'] ?? '')) ?></span>
                </div>
                <div class="jnet-record__grid">
                    <div><span>Alias</span><strong><?= $h((string) (($t['alias'] ?? '') !== '' ? $t['alias'] : '—')) ?></strong></div>
                    <div><span>Dernière observation</span><strong><?= $h((string) ($t['lastKnown'] ?? '—')) ?></strong></div>
                    <div><span>Vu</span><strong><?= $h((string) (($t['lastSeen'] ?? '') !== '' ? $t['lastSeen'] : '—')) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="jnet-filters">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="jnet-filter<?= $tab === $key ? ' is-active' : '' ?>" href="<?= $h(url('jnet/cibles/' . rawurlencode($id)) . '?onglet=' . $key) ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($tab === 'photos'): ?>
            <h3 class="jnet-section-title">Renseignement image</h3>
            <div class="jnet-photo-strip">
                <?php foreach (($t['photos'] ?? []) as $ph): ?>
                    <div class="jnet-photo-tile">
                        <div class="jnet-avatar jnet-avatar--xl jnet-avatar--target"><span><?= $h(substr((string) ($ph['label'] ?? 'P'), 0, 2)) ?></span></div>
                        <strong><?= $h((string) ($ph['label'] ?? '')) ?></strong>
                        <span><?= $h((string) ($ph['kind'] ?? '')) ?> · <?= $h((string) ($ph['when'] ?? '')) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="jnet-statstrip jnet-statstrip--inline">
                <div><span>Références visage</span><strong>8</strong></div>
                <div><span>Photos terrain</span><strong>4</strong></div>
                <div><span>Photos SSE</span><strong>3</strong></div>
                <div><span>Imagerie ISR</span><strong>6</strong></div>
                <div><span>Non vérifiées</span><strong>2</strong></div>
            </div>
        <?php elseif ($tab === 'timeline'): ?>
            <div class="jnet-feed">
                <?php foreach (($t['timeline'] ?? []) as $ev): ?>
                    <div class="jnet-feed__item">
                        <time><?= $h((string) ($ev['when'] ?? '')) ?></time>
                        <div>
                            <strong><?= $h((string) ($ev['label'] ?? '')) ?></strong>
                            <span><?= $h((string) ($ev['detail'] ?? '')) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($tab === 'associates'): ?>
            <ul class="jnet-bullet"><?php foreach (($t['associates'] ?? []) as $a): ?><li><?= $h((string) $a) ?></li><?php endforeach; ?></ul>
        <?php elseif ($tab === 'locations'): ?>
            <ul class="jnet-bullet"><?php foreach (($t['locations'] ?? []) as $a): ?><li><?= $h((string) $a) ?></li><?php endforeach; ?></ul>
        <?php elseif ($tab === 'devices'): ?>
            <ul class="jnet-bullet"><?php foreach (($t['devices'] ?? []) as $a): ?><li><?= $h((string) $a) ?></li><?php endforeach; ?></ul>
        <?php elseif ($tab === 'reports'): ?>
            <ul class="jnet-bullet"><?php foreach (($t['reports'] ?? []) as $a): ?><li><?= $h((string) $a) ?></li><?php endforeach; ?></ul>
        <?php elseif ($tab === 'sse'): ?>
            <p class="jnet-lead">Reliez cette cible aux dossiers SSE / SEEK pour remonter photos et preuves terrain.</p>
            <?php
            $sseHref = (string) ($t['sse_href'] ?? '');
            if ($sseHref === '' && ($t['source'] ?? '') === 'interest' && (int) ($t['source_id'] ?? 0) > 0) {
                $sseHref = url('atak/sse/interet/' . (int) $t['source_id']);
            }
            if ($sseHref === '') {
                $sseHref = url('atak/sse');
            }
            ?>
            <a class="jnet-btn jnet-btn--accent" href="<?= $h($sseHref) ?>">Ouvrir dans SSE</a>
        <?php elseif ($tab === 'analysis'): ?>
            <p class="jnet-lead">Analyse consolidée : priorité <?= $h((string) ($t['priority'] ?? '')) ?>, confiance <?= (int) ($t['confidence'] ?? 0) ?>%, organisation <?= $h((string) ($t['org'] ?? '—')) ?>.</p>
        <?php else: ?>
            <p class="jnet-lead">Profil consolidé pour exploitation et diffusion contrôlée. Les photographies, lieux et relations complètent le package de renseignement.</p>
        <?php endif; ?>
    </div>
</article>
