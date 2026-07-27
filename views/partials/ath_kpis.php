<?php
declare(strict_types=1);

/**
 * Grille KPI ATHENA (maquette, max 5 cartes).
 *
 * @var list<array{label: string, value: string, delta?: string, tone?: string, pct?: string, note?: string}> $athKpis
 */

if (empty($athKpis) || !is_array($athKpis)) {
    return;
}

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$items = \App\Support\AthUi::normalizeKpis($athKpis, 5);
if ($items === []) {
    return;
}
?>
<div class="ath-kpi-grid ath-rise">
    <?php foreach ($items as $kpi): ?>
    <div class="ath-card ath-card--hover ath-kpi">
        <div class="ath-kpi__label"><?= $h($kpi['label']) ?></div>
        <div class="ath-kpi__row">
            <span class="ath-kpi__value"><?= $h($kpi['value']) ?></span>
            <?php if ($kpi['delta'] !== ''): ?>
            <span class="ath-kpi__delta" style="color:<?= $h($kpi['tone']) ?>"><?= $h($kpi['delta']) ?></span>
            <?php endif; ?>
        </div>
        <div class="ath-kpi__bar"><span style="width:<?= $h($kpi['pct']) ?>;background:<?= $h($kpi['tone']) ?>;"></span></div>
        <?php if ($kpi['note'] !== ''): ?>
        <div class="ath-kpi__note"><?= $h($kpi['note']) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
