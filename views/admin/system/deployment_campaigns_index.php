<?php
declare(strict_types=1);
$rows = is_array($deploymentCampaigns ?? null) ? $deploymentCampaigns : [];
$statusLabels = is_array($deploymentCampaignStatusLabels ?? null) ? $deploymentCampaignStatusLabels : [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <?php
        $fErr = \App\Core\Session::getFlash('error');
        $fOk = \App\Core\Session::getFlash('success');
        ?>
        <?php if ($fErr !== null && trim((string) $fErr) !== ''): ?>
            <?php $flash_variant = 'error'; $flash_message = (string) $fErr; $flash_margin_class = 'mb-6'; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($fOk !== null && trim((string) $fOk) !== ''): ?>
            <?php $flash_variant = 'success'; $flash_message = (string) $fOk; $flash_margin_class = 'mb-6'; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>

        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">
            <a href="<?= htmlspecialchars(url('admin/system/deployment'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Publications et canaux</a>
            <span class="text-slate-400" aria-hidden="true"> · </span>
            <span class="text-slate-600">Campagnes</span>
        </p>
        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Campagnes de publication</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Planifiez une même version sur plusieurs environnements dans l’ordre recommandé, puis exécutez les étapes au rythme souhaité (traitement manuel ou par lots).
                </p>
            </div>
            <a href="<?= htmlspecialchars(url('admin/system/deployment/campaigns/nouveau'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700">Nouvelle campagne</a>
        </div>

        <div class="mt-10 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Réf.</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Fonctionnalité</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Version</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">État</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Créée le</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $rid = (int) ($r['id'] ?? 0);
                        $st = (string) ($r['status'] ?? '');
                        $stLabel = $statusLabels[$st] ?? $st;
                        ?>
                        <?php if ($rid < 1) {
                            continue;
                        } ?>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">#<?= $rid ?></td>
                            <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($r['module_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars((string) ($r['version_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3">
                                <a href="<?= htmlspecialchars(url('admin/system/deployment/campaigns/' . $rid), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-amber-700 hover:text-amber-900">Détails</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Aucune campagne pour le moment. Créez-en une pour enchaîner des publications contrôlées.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
