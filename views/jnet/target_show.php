<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$t = is_array($target ?? null) ? $target : [];
$ini = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($t['name'] ?? 'X')) ?: 'X', 0, 2));
$prioKey = strtolower((string) ($t['priority_key'] ?? 'low'));
$sseHref = (string) ($t['sse_href'] ?? '');
if ($sseHref === '' && ($t['source'] ?? '') === 'interest' && (int) ($t['source_id'] ?? 0) > 0) {
    $sseHref = url('atak/sse/interet/' . (int) $t['source_id']);
}
if ($sseHref === '') {
    $sseHref = url('atak/sse');
}
?>
<article class="jnet-panel jnet-record">
    <div class="jnet-panel__head">
        <h2>Dossier de renseignement</h2>
        <a class="jnet-btn" href="<?= $h(url('jnet/cibles')) ?>">Retour aux dossiers</a>
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
                <p class="jnet-kicker"><?= $h(trim(implode(' · ', array_filter([(string) ($t['kind'] ?? ''), (string) ($t['code'] ?? '')])))) ?></p>
                <h1><?= $h((string) ($t['name'] ?? '')) ?></h1>
                <div class="jnet-tags">
                    <?php if (trim((string) ($t['priority'] ?? '')) !== ''): ?>
                        <span class="jnet-prio jnet-prio--<?= $h($prioKey) ?>"><?= $h((string) $t['priority']) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) ($t['confidence_label'] ?? '')) !== ''): ?>
                        <span><?= $h((string) $t['confidence_label']) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) ($t['status_label'] ?? '')) !== ''): ?>
                        <span><?= $h((string) $t['status_label']) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) ($t['org'] ?? '')) !== ''): ?>
                        <span><?= $h((string) $t['org']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="jnet-record__grid">
                    <?php if (trim((string) ($t['alias'] ?? '')) !== ''): ?>
                        <div><span>Alias</span><strong><?= $h((string) $t['alias']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($t['lastKnown'] ?? '')) !== ''): ?>
                        <div><span>Dernière observation</span><strong><?= $h((string) $t['lastKnown']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($t['lastSeen'] ?? '')) !== ''): ?>
                        <div><span>Dernière mise à jour</span><strong><?= $h((string) $t['lastSeen']) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <p class="jnet-lead">Ce résumé reprend le dossier ouvert au bureau SSE. Les photographies, lieux et recoupements se consultent sur la fiche complète.</p>
        <div class="jnet-mail__actions">
            <a class="jnet-btn jnet-btn--accent" href="<?= $h($sseHref) ?>">Ouvrir la fiche SSE</a>
        </div>
    </div>
</article>
