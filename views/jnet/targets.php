<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$targets = is_array($targets ?? null) ? $targets : [];
$face = static function (array $t) use ($h): string {
    if (!empty($t['photo'])) {
        return '<img src="' . $h((string) $t['photo']) . '" alt="">';
    }
    $ini = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($t['name'] ?? 'X')) ?: 'X', 0, 2));
    return '<span>' . $h($ini) . '</span>';
};
$prioKey = static fn (array $t): string => strtolower((string) ($t['priority_key'] ?? 'low'));
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Dossiers de renseignement</h2>
        <span class="jnet-meta"><?= (int) ($targetsTotal ?? count($targets)) ?> dossier<?= count($targets) > 1 ? 's' : '' ?></span>
    </div>
    <div class="jnet-panel__body">
        <?php if ($targets === []): ?>
            <div class="jnet-empty">
                <p>Aucun dossier ouvert pour le moment.</p>
                <p>Les personnes et objectifs suivis par le bureau SSE apparaissent ici dès qu’un dossier est constitué.</p>
                <p><a class="jnet-btn" href="<?= $h(url('atak/sse/interet')) ?>">Ouvrir le bureau SSE</a></p>
            </div>
        <?php else: ?>
            <div class="jnet-target-rail">
                <?php foreach ($targets as $t): ?>
                    <a class="jnet-target-card"
                       href="<?= $h(url('jnet/cibles/' . rawurlencode((string) ($t['id'] ?? '')))) ?>">
                        <div class="jnet-avatar jnet-avatar--xl jnet-avatar--target"><?= $face($t) ?></div>
                        <strong><?= $h((string) ($t['name'] ?? '')) ?></strong>
                        <span><?= $h(trim(implode(' · ', array_filter([(string) ($t['code'] ?? ''), (string) ($t['kind'] ?? '')])))) ?></span>
                        <em class="jnet-prio jnet-prio--<?= $h($prioKey($t)) ?>"><?= $h((string) ($t['priority'] ?? '')) ?></em>
                        <?php if (trim((string) ($t['confidence_label'] ?? '')) !== ''): ?>
                            <small><?= $h((string) $t['confidence_label']) ?></small>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
