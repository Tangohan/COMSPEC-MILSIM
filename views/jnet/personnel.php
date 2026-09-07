<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$personnel = is_array($personnel ?? null) ? $personnel : [];
$filter = (string) ($personnelFilter ?? 'all');
$filters = is_array($personnelFilters ?? null) && $personnelFilters !== []
    ? $personnelFilters
    : [['key' => 'all', 'label' => 'Tous']];
$face = static function (array $p) use ($h): string {
    if (!empty($p['photo'])) {
        return '<img src="' . $h((string) $p['photo']) . '" alt="">';
    }
    return '<span>' . $h((string) ($p['initials'] ?? '?')) . '</span>';
};
$activeLabel = 'Tous';
foreach ($filters as $opt) {
    if ((string) ($opt['key'] ?? '') === $filter) {
        $activeLabel = (string) ($opt['label'] ?? $filter);
        break;
    }
}
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Personnel<?= strcasecmp($filter, 'all') !== 0 ? ' · ' . $h($activeLabel) : '' ?></h2>
        <span class="jnet-meta"><?= count($personnel) ?> / <?= (int) ($personnelTotal ?? count($personnel)) ?></span>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-filters">
            <?php foreach ($filters as $opt): ?>
                <?php
                $key = (string) ($opt['key'] ?? 'all');
                $label = (string) ($opt['label'] ?? $key);
                $href = url('jnet/personnel') . (strcasecmp($key, 'all') === 0 ? '' : '?filtre=' . rawurlencode($key));
                ?>
                <a class="jnet-filter<?= $filter === $key ? ' is-active' : '' ?>" href="<?= $h($href) ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="jnet-gallery">
            <?php foreach ($personnel as $p): ?>
                <a class="jnet-person-card" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                    <div class="jnet-avatar jnet-avatar--xl"><?= $face($p) ?></div>
                    <strong><?= $h((string) ($p['name'] ?? '')) ?></strong>
                    <?php if (trim((string) ($p['grade'] ?? '')) !== ''): ?>
                        <span><?= $h((string) $p['grade']) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) ($p['unit'] ?? '')) !== ''): ?>
                        <span><?= $h((string) $p['unit']) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) ($p['function'] ?? '')) !== ''): ?>
                        <span><?= $h((string) $p['function']) ?></span>
                    <?php endif; ?>
                    <em class="jnet-duty"><?= $h((string) ($p['duty_label'] ?? '')) ?></em>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($personnel === []): ?>
            <div class="jnet-empty">
                <p><?= strcasecmp($filter, 'all') === 0 ? 'Aucun membre actif dans l’annuaire.' : 'Aucun membre pour ce filtre.' ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
