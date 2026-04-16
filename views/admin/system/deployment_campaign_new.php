<?php
declare(strict_types=1);
$modules = is_array($deploymentCampaignModules ?? null) ? $deploymentCampaignModules : [];
$versions = is_array($deploymentCampaignVersions ?? null) ? $deploymentCampaignVersions : [];
$channels = is_array($deploymentCampaignChannels ?? null) ? $deploymentCampaignChannels : [];
$selMid = (int) ($deploymentCampaignSelectedModuleId ?? 0);
$verLabels = is_array($deploymentVersionStatusLabels ?? null) ? $deploymentVersionStatusLabels : [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
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
            <span class="text-slate-600">Nouvelle</span>
        </p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Nouvelle campagne de publication</h1>
        <p class="mt-2 text-sm text-slate-600">
            Choisissez d’abord la fonctionnalité concernée, puis la version à promouvoir et les environnements cibles. L’ordre d’exécution suivra automatiquement la progression recommandée (du plus tôt au plus exposé).
        </p>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Étape 1 — Fonctionnalité</h2>
            <form method="get" action="<?= htmlspecialchars(url('admin/system/deployment/campaigns/nouveau'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-[220px] flex-1">
                    <label for="pick-module" class="block text-xs font-semibold text-slate-600">Fonctionnalité déployable</label>
                    <select id="pick-module" name="module_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($modules as $m): ?>
                            <?php $mid = (int) ($m['id'] ?? 0); ?>
                            <?php if ($mid < 1) {
                                continue;
                            } ?>
                            <option value="<?= $mid ?>" <?= $mid === $selMid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($m['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="inline-flex rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Continuer</button>
            </form>
        </section>

        <?php if ($selMid > 0 && $modules !== []): ?>
            <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Étape 2 — Version et environnements</h2>
                <?php if ($versions === []): ?>
                    <p class="mt-4 text-sm text-amber-900">Aucune version n’est encore enregistrée pour cette fonctionnalité. Créez-en une depuis la fiche « Configurer », puis revenez ici.</p>
                    <p class="mt-4">
                        <a href="<?= htmlspecialchars(url('admin/system/deployment/modules/' . $selMid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-amber-700 hover:text-amber-900">Ouvrir la fiche fonctionnalité</a>
                    </p>
                <?php else: ?>
                    <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/campaigns'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-6">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="module_id" value="<?= $selMid ?>">
                        <div>
                            <label for="pick-version" class="block text-xs font-semibold text-slate-600">Version à publier</label>
                            <select id="pick-version" name="module_version_id" class="mt-1 w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                                <?php foreach ($versions as $v): ?>
                                    <?php $vid = (int) ($v['id'] ?? 0); ?>
                                    <?php if ($vid < 1) {
                                        continue;
                                    } ?>
                                    <?php
                                    $vnum = (string) ($v['version'] ?? '');
                                    $vst = (string) ($v['status'] ?? '');
                                    $vstL = $verLabels[$vst] ?? $vst;
                                    ?>
                                    <option value="<?= $vid ?>"><?= htmlspecialchars($vnum . ' — ' . $vstL, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <fieldset>
                            <legend class="text-xs font-semibold text-slate-600">Environnements cibles (ordre d’exécution automatique)</legend>
                            <p class="mt-1 text-xs text-slate-500">Cochez au moins un environnement. Les étapes seront lancées dans l’ordre recommandé par la plateforme, pas dans l’ordre de vos clics.</p>
                            <ul class="mt-4 space-y-2">
                                <?php foreach ($channels as $ch): ?>
                                    <?php $cid = (int) ($ch['id'] ?? 0); ?>
                                    <?php if ($cid < 1) {
                                        continue;
                                    } ?>
                                    <li class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
                                        <input type="checkbox" name="channel_ids[]" value="<?= $cid ?>" id="ch-<?= $cid ?>" class="mt-1 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                        <label for="ch-<?= $cid ?>" class="text-sm text-slate-800">
                                            <span class="font-semibold"><?= htmlspecialchars((string) ($ch['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </fieldset>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="inline-flex rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700">Enregistrer la campagne</button>
                            <a href="<?= htmlspecialchars(url('admin/system/deployment/campaigns'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Annuler</a>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
