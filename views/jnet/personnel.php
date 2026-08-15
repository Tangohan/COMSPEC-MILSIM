<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$personnel = is_array($personnel ?? null) ? $personnel : [];
$filter = (string) ($personnelFilter ?? 'all');
$filters = [
    'all' => 'Tous',
    'command' => 'Commandement',
    'alpha' => 'Alpha',
    'bravo' => 'Bravo',
    'support' => 'Support',
    'deployed' => 'Déployés',
    'off' => 'Repos',
];
$face = static function (array $p) use ($h): string {
    if (!empty($p['photo'])) {
        return '<img src="' . $h((string) $p['photo']) . '" alt="">';
    }
    return '<span>' . $h((string) ($p['initials'] ?? '?')) . '</span>';
};
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Personnel<?= $filter !== 'all' ? ' / ' . $h(strtoupper($filter)) : '' ?></h2>
        <span class="jnet-meta"><?= count($personnel) ?> / <?= (int) ($personnelTotal ?? count($personnel)) ?></span>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-filters">
            <?php foreach ($filters as $key => $label): ?>
                <a class="jnet-filter<?= $filter === $key ? ' is-active' : '' ?>" href="<?= $h(url('jnet/personnel') . ($key === 'all' ? '' : '?filtre=' . rawurlencode($key))) ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="jnet-gallery">
            <?php foreach ($personnel as $p): ?>
                <a class="jnet-person-card" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                    <div class="jnet-avatar jnet-avatar--xl"><?= $face($p) ?></div>
                    <strong><?= $h((string) ($p['name'] ?? '')) ?></strong>
                    <span><?= $h((string) ($p['grade'] ?? '')) ?></span>
                    <span><?= $h((string) ($p['unit'] ?? '')) ?></span>
                    <span><?= $h((string) ($p['function'] ?? '')) ?></span>
                    <em class="jnet-duty"><?= $h((string) ($p['duty_label'] ?? '')) ?></em>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($personnel === []): ?>
            <p class="jnet-empty">Aucun personnel pour ce filtre.</p>
        <?php endif; ?>
    </div>
</section>
