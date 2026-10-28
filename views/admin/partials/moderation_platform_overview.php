<?php
declare(strict_types=1);
$reportsErr = $adminReportsSnapshotError ?? null;
$queueErr = $adminContentQueueSnapshotError ?? null;
$reportsTotal = (int) ($adminReportsPendingTotal ?? 0);
$queueTotal = (int) ($adminContentQueueTotal ?? 0);
$reportsByT = $adminReportsPendingByTenant ?? [];
$queueByT = $adminContentQueueByTenant ?? [];
$reportsConsole = url('back-office/signalements');
$contentMod = url('back-office/content-moderation');
$gateMod = \App\Core\Gate::getInstance();
$canSystemModerationTools = $gateMod->allows('admin.system');
$canOpenReportsConsole = $canSystemModerationTools
    || (function_exists('moderation_user_can_moderate') && moderation_user_can_moderate());
$hm = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

/**
 * @param array{tenant_id: int, tenant_name: string|null} $row
 */
$tenantLabel = static function (array $row): string {
    $name = $row['tenant_name'] ?? null;
    if ($name !== null && trim($name) !== '') {
        return $name;
    }

    return 'Communauté nº ' . (int) ($row['tenant_id'] ?? 0);
};
?>
<section class="pa-card" aria-labelledby="mod-platform-heading">
    <div class="pa-card__head">
        <span class="pa-icon pa-icon--rose" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
        <div>
            <p class="pa-card__kicker">Toutes communautés</p>
            <h2 id="mod-platform-heading" class="pa-card__title">Supervision modération</h2>
            <p class="pa-card__help">Le traitement se fait dans le contexte d’une communauté : sélectionnez-la dans le portail, puis ouvrez la console.</p>
        </div>
    </div>

    <dl class="pa-mini-stats">
        <div>
            <dt>Signalements membres</dt>
            <dd><?= $reportsErr ? '—' : $reportsTotal ?></dd>
        </div>
        <div>
            <dt>Fichiers en attente</dt>
            <dd><?= $queueErr ? '—' : $queueTotal ?></dd>
        </div>
    </dl>

    <?php if ($reportsErr): ?><p class="pa-flash pa-flash--err" style="margin:10px 0 0;"><?= $hm((string) $reportsErr) ?></p><?php endif; ?>
    <?php if ($queueErr): ?><p class="pa-flash pa-flash--err" style="margin:10px 0 0;"><?= $hm((string) $queueErr) ?></p><?php endif; ?>

    <?php $byTenant = []; ?>
    <?php if (!$reportsErr && $reportsTotal > 0) { foreach ($reportsByT as $r) { $byTenant[] = [$tenantLabel($r), (int) ($r['pending'] ?? 0), 'signalement(s)']; } } ?>
    <?php if (!$queueErr && $queueTotal > 0) { foreach ($queueByT as $r) { $byTenant[] = [$tenantLabel($r), (int) ($r['pending'] ?? 0), 'fichier(s)']; } } ?>
    <div style="margin-top:12px;">
        <?php if ($byTenant === []): ?>
            <p class="pa-empty-state">Rien en attente : aucun signalement ni fichier à valider.</p>
        <?php else: ?>
            <ul class="pa-rows">
                <?php foreach ($byTenant as [$label, $n, $unit]): ?>
                    <li><span><?= $hm($label) ?></span><b><?= $n ?> <?= $hm($unit) ?></b></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <ul class="pa-links" style="margin-top:14px;">
        <?php if ($canOpenReportsConsole): ?>
        <li><a class="pa-links__a" href="<?= $hm($reportsConsole) ?>">Console des signalements</a></li>
        <li><a class="pa-links__a" href="<?= $hm($contentMod) ?>">File de validation</a></li>
        <?php endif; ?>
        <?php if ($canSystemModerationTools): ?>
        <li><a class="pa-links__a" href="<?= $hm(url('admin/system/member-sanctions')) ?>">Sanctions membres (site)</a></li>
        <li><a class="pa-links__a" href="<?= $hm(url('admin/system/blocklist')) ?>">Liste e-mail et réseau (site)</a></li>
        <li><a class="pa-links__a" href="<?= $hm(url('admin/system/recruitment-portal-tools')) ?>">Suivi candidatures</a></li>
        <?php endif; ?>
    </ul>
</section>
