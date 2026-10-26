<?php
/** @var list<array{id: string, label: string, value: string|null, hint: string|null, error: string|null}> $kpis */
/** @var string|null $blockError */
$kpis = $kpis ?? [];
$blockError = $blockError ?? null;
$kpiLabels = ['tenants' => 'Communautés'];
$reportsPending = isset($adminReportsSnapshotError) && $adminReportsSnapshotError ? null : (int) ($adminReportsPendingTotal ?? 0);
$filesPending = isset($adminContentQueueSnapshotError) && $adminContentQueueSnapshotError ? null : (int) ($adminContentQueueTotal ?? 0);
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<?php if ($blockError): ?>
    <p class="pa-flash pa-flash--warn"><?= $e($blockError) ?></p>
<?php endif; ?>
<ul class="pa-kpi-strip">
    <?php foreach ($kpis as $k): ?>
        <?php $id = (string) ($k['id'] ?? ''); ?>
        <li class="pa-kpi">
            <span class="pa-kpi__label"><?= $e($kpiLabels[$id] ?? (string) ($k['label'] ?? '')) ?></span>
            <?php if (!empty($k['error'])): ?>
                <span class="pa-kpi__err"><?= $e((string) $k['error']) ?></span>
            <?php else: ?>
                <span class="pa-kpi__value"><?= $e((string) ($k['value'] ?? '—')) ?></span>
            <?php endif; ?>
            <?php if (!empty($k['hint'])): ?>
                <span class="pa-kpi__hint"><?= $e((string) $k['hint']) ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    <?php if ($reportsPending !== null): ?>
        <li><a class="pa-kpi<?= $reportsPending > 0 ? ' pa-kpi--alert' : '' ?>" href="<?= $e(url('back-office/signalements')) ?>">
            <span class="pa-kpi__label">Signalements membres</span>
            <span class="pa-kpi__value"><?= $reportsPending ?></span>
            <span class="pa-kpi__hint">À traiter, toutes communautés</span>
        </a></li>
    <?php endif; ?>
    <?php if ($filesPending !== null): ?>
        <li><a class="pa-kpi<?= $filesPending > 0 ? ' pa-kpi--warn' : '' ?>" href="<?= $e(url('back-office/content-moderation')) ?>">
            <span class="pa-kpi__label">Fichiers en attente</span>
            <span class="pa-kpi__value"><?= $filesPending ?></span>
            <span class="pa-kpi__hint">Quarantaine ou analyse</span>
        </a></li>
    <?php endif; ?>
</ul>
