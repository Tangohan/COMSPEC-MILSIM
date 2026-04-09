<?php
declare(strict_types=1);
$forumErr = $adminForumModerationSnapshotError ?? null;
$queueErr = $adminContentQueueSnapshotError ?? null;
$auditErr = $adminAuditModerationError ?? null;
$forumTotal = (int) ($adminForumPendingTotal ?? 0);
$queueTotal = (int) ($adminContentQueueTotal ?? 0);
$forumByT = $adminForumPendingByTenant ?? [];
$queueByT = $adminContentQueueByTenant ?? [];
$contentRows = $adminAuditRecentContent ?? [];
$tenantRows = $adminAuditRecentTenant ?? [];
$auditMore = url('admin/audit');
$boForumMod = url('back-office/forum-moderation');
$contentMod = url('admin/content-moderation');
$memberSanctionsSite = url('admin/system/member-sanctions');
$blocklistSite = url('admin/system/blocklist');
$gateMod = \App\Core\Gate::getInstance();
$canSystemModerationTools = $gateMod->allows('admin.system');
$canOpenForumModConsole = $canSystemModerationTools
    || (function_exists('forum_user_can_moderate') && forum_user_can_moderate());

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
<section class="space-y-6" aria-labelledby="mod-platform-heading">
    <div>
        <h2 id="mod-platform-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Supervision modération</h2>
        <p class="mt-1 text-sm text-slate-600">
            Vue agrégée sur toutes les communautés. Le traitement des signalements et de la quarantaine fichiers se fait
            <strong class="font-semibold text-slate-800">dans le contexte d’une communauté</strong> (sélectionnez-la dans le portail, puis ouvrez les écrans indiqués).
        </p>
        <?php if ($canSystemModerationTools): ?>
        <p class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm font-semibold">
            <a href="<?= htmlspecialchars($memberSanctionsSite, ENT_QUOTES, 'UTF-8') ?>" class="text-rose-800 hover:underline">Sanctions membres (site) →</a>
            <a href="<?= htmlspecialchars($blocklistSite, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-700 hover:underline">Liste e-mail et réseau (site) →</a>
        </p>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-rose-200/90 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-rose-800/80 mb-1">Signalements forum</p>
            <?php if ($forumErr): ?>
                <p class="text-sm text-rose-600"><?= htmlspecialchars($forumErr, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <p class="text-3xl font-black tabular-nums text-rose-900"><?= $forumTotal ?></p>
                <p class="mt-1 text-xs text-slate-500">Ouverts à traiter (toutes communautés)</p>
                <?php if ($canOpenForumModConsole): ?>
                <a href="<?= htmlspecialchars($boForumMod, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex text-sm font-bold text-emerald-800 hover:text-emerald-700 underline decoration-emerald-300">Console modération forum →</a>
                <?php else: ?>
                <p class="mt-4 text-xs text-slate-500">Console modération : sélectionnez une communauté et vérifiez vos habilitations de modérateur.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="rounded-2xl border border-amber-200/90 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900/80 mb-1">Fichiers en attente</p>
            <?php if ($queueErr): ?>
                <p class="text-sm text-rose-600"><?= htmlspecialchars($queueErr, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <p class="text-3xl font-black tabular-nums text-amber-950"><?= $queueTotal ?></p>
                <p class="mt-1 text-xs text-slate-500">Quarantaine ou analyse en cours (toutes communautés)</p>
                <?php if ($canOpenForumModConsole): ?>
                <a href="<?= htmlspecialchars($contentMod, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex text-sm font-bold text-emerald-800 hover:text-emerald-700 underline decoration-emerald-300">File de validation →</a>
                <?php else: ?>
                <p class="mt-4 text-xs text-slate-500">Validation des pièces jointes : accès réservé aux modérateurs habilités.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$forumErr || !$queueErr): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold text-slate-900 mb-3">Signalements par communauté</h3>
            <?php if ($forumErr): ?>
                <p class="text-sm text-slate-500">—</p>
            <?php elseif ($forumTotal === 0): ?>
                <p class="text-sm text-slate-500">Aucun signalement en attente.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 text-sm">
                    <?php foreach ($forumByT as $r): ?>
                        <li class="py-2 flex justify-between gap-3">
                            <span class="text-slate-700"><?= htmlspecialchars($tenantLabel($r), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="font-semibold tabular-nums text-rose-800"><?= (int) ($r['pending'] ?? 0) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold text-slate-900 mb-3">Fichiers en attente par communauté</h3>
            <?php if ($queueErr): ?>
                <p class="text-sm text-slate-500">—</p>
            <?php elseif ($queueTotal === 0): ?>
                <p class="text-sm text-slate-500">Aucun fichier en file d’attente.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 text-sm">
                    <?php foreach ($queueByT as $r): ?>
                        <li class="py-2 flex justify-between gap-3">
                            <span class="text-slate-700"><?= htmlspecialchars($tenantLabel($r), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="font-semibold tabular-nums text-amber-900"><?= (int) ($r['pending'] ?? 0) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div>
        <h3 class="text-sm font-bold text-slate-900 mb-1">Journal — actions récentes</h3>
        <p class="text-xs text-slate-500 mb-4">Extraits ciblés ; l’historique complet est dans le journal d’audit.</p>
        <?php if ($auditErr): ?>
            <p class="text-sm text-rose-600"><?= htmlspecialchars($auditErr, ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-600 mb-3">Contenus &amp; modération</h4>
                    <?php if ($contentRows === []): ?>
                        <p class="text-sm text-slate-500">Aucune entrée récente dans cette catégorie.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-200/80 text-sm">
                            <?php foreach ($contentRows as $row): ?>
                                <li class="py-2 space-y-0.5">
                                    <div class="flex flex-wrap gap-x-2 gap-y-0.5 items-baseline">
                                        <span class="text-slate-500 text-xs whitespace-nowrap"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="font-medium text-slate-800"><?= htmlspecialchars(audit_action_label_fr((string) ($row['action'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="text-xs text-slate-600">
                                        <?= htmlspecialchars((string) ($row['actor_email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($row['tenant_name'])): ?>
                                            <span class="text-slate-400"> · </span>
                                            <?= htmlspecialchars((string) $row['tenant_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-600 mb-3">Organisations (communautés)</h4>
                    <?php if ($tenantRows === []): ?>
                        <p class="text-sm text-slate-500">Aucune entrée récente dans cette catégorie.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-200/80 text-sm">
                            <?php foreach ($tenantRows as $row): ?>
                                <li class="py-2 space-y-0.5">
                                    <div class="flex flex-wrap gap-x-2 gap-y-0.5 items-baseline">
                                        <span class="text-slate-500 text-xs whitespace-nowrap"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="font-medium text-slate-800"><?= htmlspecialchars(audit_action_label_fr((string) ($row['action'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="text-xs text-slate-600">
                                        <?= htmlspecialchars((string) ($row['actor_email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($row['tenant_name'])): ?>
                                            <span class="text-slate-400"> · </span>
                                            <?= htmlspecialchars((string) $row['tenant_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <p class="mt-4">
                <a href="<?= htmlspecialchars($auditMore, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-amber-800 hover:underline">Ouvrir le journal d’audit complet →</a>
            </p>
        <?php endif; ?>
    </div>
</section>
