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
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Cibles prioritaires</h2>
        <span class="jnet-meta"><?= (int) ($targetsTotal ?? count($targets)) ?> dossiers</span>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-target-rail">
            <?php foreach ($targets as $t): ?>
                <a class="jnet-target-card"
                   href="<?= $h(url('jnet/cibles/' . rawurlencode((string) ($t['id'] ?? '')))) ?>"
                   title="<?= $h(trim(($t['alias'] ?? '') . ' · ' . ($t['org'] ?? '') . ' · ' . ($t['lastKnown'] ?? ''))) ?>">
                    <div class="jnet-avatar jnet-avatar--xl jnet-avatar--target"><?= $face($t) ?></div>
                    <strong><?= $h((string) ($t['name'] ?? '')) ?></strong>
                    <span><?= $h((string) ($t['code'] ?? '')) ?> · <?= $h((string) ($t['kind'] ?? '')) ?></span>
                    <em class="jnet-prio jnet-prio--<?= strtolower((string) ($t['priority'] ?? 'low')) ?>"><?= $h((string) ($t['priority'] ?? '')) ?></em>
                    <small>Conf. <?= (int) ($t['confidence'] ?? 0) ?>%</small>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="jnet-meta" style="margin-top:1rem">HVT = haute valeur · POI = personne d’intérêt · WATCHLIST = surveillance · UNKNOWN = identité non établie. Survolez une carte pour l’alias et la dernière observation.</p>
    </div>
</section>
