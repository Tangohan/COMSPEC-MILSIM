<?php
declare(strict_types=1);
$mod = is_array($deploymentModule ?? null) ? $deploymentModule : [];
$versions = is_array($deploymentVersions ?? null) ? $deploymentVersions : [];
$channels = is_array($deploymentChannels ?? null) ? $deploymentChannels : [];
$current = is_array($deploymentCurrentReleases ?? null) ? $deploymentCurrentReleases : [];
$rules = is_array($deploymentAccessRules ?? null) ? $deploymentAccessRules : [];
$communities = is_array($deploymentTesterCommunities ?? null) ? $deploymentTesterCommunities : [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$mid = (int) ($mod['id'] ?? 0);
$vLabels = is_array($deploymentVersionStatusLabels ?? null) ? $deploymentVersionStatusLabels : [];
$rLabels = is_array($deploymentRuleTypeLabels ?? null) ? $deploymentRuleTypeLabels : [];
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">
            <a href="<?= htmlspecialchars(url('admin/system/deployment'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Publications et canaux</a>
            <span class="text-slate-400" aria-hidden="true"> · </span>
            <a href="<?= htmlspecialchars(url('admin/system/deployment/communities'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Communautés de test</a>
        </p>
        <h1 class="mt-2 text-2xl font-black text-slate-900"><?= htmlspecialchars((string) ($mod['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Informations générales</h2>
            <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/modules/' . $mid), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Nom affiché</label>
                    <input name="name" value="<?= htmlspecialchars((string) ($mod['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Description</label>
                    <textarea name="description" rows="2" class="mt-1 w-full max-w-2xl rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($mod['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !empty($mod['is_active']) ? 'checked' : '' ?>> Active</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" <?= !empty($mod['is_public']) ? 'checked' : '' ?>> Visible publiquement (catalogue)</label>
                </div>
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Enregistrer</button>
            </form>
        </section>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Versions</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b border-slate-200 text-left text-slate-600">
                        <th class="py-2 pr-4">Numéro</th><th class="py-2 pr-4">État</th><th class="py-2">Créée le</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($versions as $v): ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-4 font-mono"><?= htmlspecialchars((string) ($v['version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2 pr-4"><?= htmlspecialchars((string) ($vLabels[(string) ($v['status'] ?? '')] ?? (string) ($v['status'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2 text-slate-600"><?= htmlspecialchars((string) ($v['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($versions === []): ?>
                            <tr><td colspan="3" class="py-4 text-slate-500">Aucune version.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/modules/' . $mid . '/versions'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-6">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Nouvelle version</label>
                    <input name="version" required maxlength="80" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="1.2.0">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">État initial</label>
                    <select name="status" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($vLabels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700">Ajouter</button>
            </form>
        </section>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Publication par canal</h2>
            <p class="mt-1 text-xs text-slate-500">Pour chaque canal, choisissez la version désormais proposée aux environnements correspondants.</p>
            <?php foreach ($channels as $ch): ?>
                <?php $cid = (int) ($ch['id'] ?? 0); ?>
                <?php $cc = strtoupper(trim((string) ($ch['code'] ?? ''))); ?>
                <?php $cur = $current[$cc] ?? null; ?>
                <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/releases'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-100 bg-slate-50/80 p-4">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="module_id" value="<?= $mid ?>">
                    <input type="hidden" name="channel_id" value="<?= $cid ?>">
                    <div class="min-w-[180px]">
                        <p class="text-xs font-bold text-slate-700"><?= htmlspecialchars((string) ($ch['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500">Actuellement : <span class="font-mono"><?= $cur ? htmlspecialchars((string) ($cur['version'] ?? ''), ENT_QUOTES, 'UTF-8') : '—' ?></span></p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Version à publier</label>
                        <select name="module_version_id" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                            <option value="">—</option>
                            <?php foreach ($versions as $v): ?>
                                <?php $vid = (int) ($v['id'] ?? 0); ?>
                                <option value="<?= $vid ?>"><?= htmlspecialchars((string) ($v['version'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($vLabels[(string) ($v['status'] ?? '')] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Appliquer</button>
                </form>
            <?php endforeach; ?>
        </section>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Règles d’accès et préqualification</h2>
            <div class="mt-4 space-y-3">
                <?php foreach ($rules as $r): ?>
                    <?php $rid = (int) ($r['id'] ?? 0); ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm">
                        <div>
                            <span class="font-medium"><?= htmlspecialchars((string) ($rLabels[(string) ($r['rule_type'] ?? '')] ?? (string) ($r['rule_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($r['community_name'])): ?>
                                <span class="text-slate-600"> — <?= htmlspecialchars((string) $r['community_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($r['environment_channel_code'])): ?>
                                <span class="text-xs text-slate-500"> (canal <?= htmlspecialchars((string) $r['environment_channel_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                            <?php endif; ?>
                            <span class="text-xs <?= !empty($r['is_active']) ? 'text-emerald-700' : 'text-slate-400' ?>"><?= !empty($r['is_active']) ? 'Active' : 'Inactive' ?></span>
                        </div>
                        <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/access-rule-delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Supprimer cette règle ?');">
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="module_id" value="<?= $mid ?>">
                            <input type="hidden" name="rule_id" value="<?= $rid ?>">
                            <button type="submit" class="text-xs font-semibold text-rose-700 hover:text-rose-900">Supprimer</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if ($rules === []): ?>
                    <p class="text-sm text-slate-500">Aucune règle spécifique.</p>
                <?php endif; ?>
            </div>

            <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/modules/' . $mid . '/access-rules'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 grid gap-4 border-t border-slate-100 pt-6 sm:grid-cols-2">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600">Type</label>
                    <select name="rule_type" class="mt-1 w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                        <?php foreach ($rLabels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Communauté (si applicable)</label>
                    <select name="community_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="0">—</option>
                        <?php foreach ($communities as $c): ?>
                            <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Canal d’environnement (optionnel)</label>
                    <select name="environment_channel_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="0">Tous</option>
                        <?php foreach ($channels as $ch): ?>
                            <option value="<?= (int) ($ch['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($ch['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Limiter à une version (optionnel)</label>
                    <select name="applies_to_version_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="0">Non</option>
                        <?php foreach ($versions as $v): ?>
                            <option value="<?= (int) ($v['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($v['version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Priorité (plus haut = prioritaire)</label>
                    <input type="number" name="priority" value="100" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm pb-2"><input type="checkbox" name="is_active" value="1" checked> Règle active</label>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700">Ajouter la règle</button>
                </div>
            </form>
        </section>
    </div>
</div>
