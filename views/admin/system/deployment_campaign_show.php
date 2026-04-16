<?php
declare(strict_types=1);
$camp = is_array($deploymentCampaign ?? null) ? $deploymentCampaign : [];
$jobs = is_array($deploymentCampaignJobs ?? null) ? $deploymentCampaignJobs : [];
$statusLabels = is_array($deploymentCampaignStatusLabels ?? null) ? $deploymentCampaignStatusLabels : [];
$jobStatusLabels = is_array($deploymentCampaignJobStatusLabels ?? null) ? $deploymentCampaignJobStatusLabels : [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$cid = (int) ($camp['id'] ?? 0);
$cst = (string) ($camp['status'] ?? '');
$cstLabel = $statusLabels[$cst] ?? $cst;
$canRun = !in_array($cst, ['completed', 'failed', 'cancelled'], true);
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
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
            <a href="<?= htmlspecialchars(url('admin/system/deployment/campaigns'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Campagnes</a>
            <span class="text-slate-400" aria-hidden="true"> · </span>
            <span class="text-slate-600">Campagne #<?= $cid ?></span>
        </p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Campagne de publication</h1>
        <p class="mt-2 text-sm text-slate-600">
            Fonctionnalité <span class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($camp['module_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            — version <span class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($camp['version_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </p>
        <p class="mt-2 inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
            État : <?= htmlspecialchars($cstLabel, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if ($canRun): ?>
            <section class="mt-8 rounded-xl border border-amber-200 bg-amber-50/60 p-6 shadow-sm">
                <h2 class="text-lg font-bold text-amber-950">Exécuter les étapes</h2>
                <p class="mt-1 text-sm text-amber-950/90">
                    Chaque clic applique la version sur l’environnement suivant dans la file, dans l’ordre prévu. Vous pouvez traiter plusieurs étapes d’affilée en choisissant un lot plus large ci-dessous.
                </p>
                <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/campaigns/' . $cid . '/executer'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex flex-wrap items-end gap-4">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <div>
                        <label for="max-steps" class="block text-xs font-semibold text-amber-900">Nombre maximum d’étapes pour cette action</label>
                        <select id="max-steps" name="max_steps" class="mt-1 rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-slate-900">
                            <option value="1">1</option>
                            <option value="3">3</option>
                            <option value="5" selected>5</option>
                            <option value="10">10</option>
                        </select>
                    </div>
                    <button type="submit" class="inline-flex rounded-xl bg-amber-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-800">Lancer l’exécution</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="mt-10 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Étapes planifiées</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Ordre</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Environnement</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">État</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($jobs as $j): ?>
                            <?php
                            $jst = (string) ($j['status'] ?? '');
                            $jstL = $jobStatusLabels[$jst] ?? $jst;
                            $err = trim((string) ($j['error_message'] ?? ''));
                            ?>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-slate-600"><?= (int) ($j['step_order'] ?? 0) ?></td>
                                <td class="px-3 py-2 font-medium text-slate-900"><?= htmlspecialchars((string) ($j['channel_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($jstL, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-slate-600"><?= $err !== '' ? htmlspecialchars($err, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($jobs === []): ?>
                            <tr>
                                <td colspan="4" class="px-3 py-8 text-center text-slate-500">Aucune étape enregistrée.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
