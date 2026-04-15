<?php
declare(strict_types=1);
$ready = !empty($deploymentSchemaReady);
$channels = is_array($deploymentChannels ?? null) ? $deploymentChannels : [];
$matrix = is_array($deploymentMatrix ?? null) ? $deploymentMatrix : [];
$modules = is_array($deploymentModules ?? null) ? $deploymentModules : [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-black text-slate-900">Déploiement & préqualification</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Tableau des versions actuellement proposées par canal d’environnement, gestion des fonctionnalités déployables et des communautés de test.
        </p>

        <?php if (!$ready): ?>
            <p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Les tables nécessaires ne sont pas installées sur cette base.</p>
        <?php else: ?>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="<?= htmlspecialchars(url('admin/system/deployment/communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Communautés de préqualification</a>
            </div>

            <div class="mt-10 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Fonctionnalité</th>
                            <?php foreach ($channels as $ch): ?>
                                <th class="px-3 py-3 text-left font-semibold text-slate-600"><?= htmlspecialchars((string) ($ch['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($modules as $m): ?>
                            <?php $mid = (int) ($m['id'] ?? 0); ?>
                            <?php if ($mid < 1) {
                                continue;
                            } ?>
                            <?php $row = $matrix[$mid] ?? ['module_name' => (string) ($m['name'] ?? ''), 'channels' => []]; ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($m['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php foreach ($channels as $ch): ?>
                                    <?php $cc = strtoupper(trim((string) ($ch['code'] ?? ''))); ?>
                                    <td class="px-3 py-3 text-slate-600 font-mono text-xs"><?= htmlspecialchars((string) ($row['channels'][$cc] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endforeach; ?>
                                <td class="px-3 py-3">
                                    <a href="<?= htmlspecialchars(url('admin/system/deployment/modules/' . $mid), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-amber-700 hover:text-amber-900">Configurer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <section class="mt-12 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" id="nouveau">
                <h2 class="text-lg font-bold text-slate-900">Nouvelle fonctionnalité déployable</h2>
                <p class="mt-1 text-xs text-slate-500">Référence technique : identifiant stable aligné sur le code métier utilisé par le moteur d’habilitation (ex. training, forum).</p>
                <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/modules'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Référence technique</label>
                        <input name="code" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="ex. TRAINING">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Nom affiché</label>
                        <input name="name" required maxlength="180" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Formations">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">Description (optionnel)</label>
                        <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-700">Créer</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
