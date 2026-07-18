<?php
declare(strict_types=1);

$ready = !empty($updatesSchemaReady);
$current = (string) ($updatesCurrentVersion ?? '1.0.0');
$releases = is_array($updatesReleases ?? null) ? $updatesReleases : [];
$selected = is_array($updatesSelected ?? null) ? $updatesSelected : null;
$files = is_array($updatesFiles ?? null) ? $updatesFiles : [];
$logs = is_array($updatesLogs ?? null) ? $updatesLogs : [];
$health = is_array($updatesHealth ?? null) ? $updatesHealth : ['ok' => false, 'checks' => [], 'messages' => []];
$csrf = htmlspecialchars((string) ($updatesCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$signatureRequired = !empty($updatesSignatureRequired);

$statusLabels = [
    'uploaded' => 'Déposé',
    'validated' => 'Contrôlé',
    'previewed' => 'Prêt à déployer',
    'deploying' => 'Déploiement en cours',
    'deployed' => 'Déployé',
    'failed' => 'Échec',
    'rolled_back' => 'Restauré',
];
$actionLabels = [
    'add' => 'Ajout',
    'update' => 'Modification',
    'delete' => 'Suppression',
    'unchanged' => 'Inchangé',
];
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-black text-slate-900">Mises à jour de la plateforme</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Déposez un package de mise à jour versionné, contrôlez les fichiers concernés, puis déployez.
            Les secrets et fichiers utilisateurs ne sont jamais écrasés.
        </p>

        <div class="mt-6 flex flex-wrap items-center gap-3 text-sm">
            <span class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-800">
                Version installée : <?= htmlspecialchars($current, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php if (!empty($health['ok'])): ?>
                <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-emerald-900">Santé : OK</span>
            <?php else: ?>
                <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-amber-950">Santé : à vérifier</span>
            <?php endif; ?>
            <?php if ($signatureRequired): ?>
                <span class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-600">Signature des packages exigée</span>
            <?php endif; ?>
        </div>

        <?php if (!$ready): ?>
            <p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Les tables nécessaires ne sont pas installées. Exécutez la migration de la base, puis rechargez cette page.
            </p>
        <?php else: ?>
            <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Déposer un package</h2>
                <p class="mt-1 text-sm text-slate-600">Archive <code class="text-xs">.zip</code> contenant le manifeste, les fichiers, les migrations et scripts éventuels.</p>
                <form method="post" action="<?= htmlspecialchars(url('admin/system/updates/upload'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-4">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600" for="package">Archive de mise à jour</label>
                        <input id="package" type="file" name="package" accept=".zip,application/zip" required class="mt-1 block w-full max-w-md text-sm text-slate-700">
                    </div>
                    <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-700">Contrôler le package</button>
                </form>
            </section>

            <div class="mt-10 grid gap-8 lg:grid-cols-5">
                <section class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-900">Historique</h2>
                    <ul class="divide-y divide-slate-100 max-h-[28rem] overflow-y-auto">
                        <?php foreach ($releases as $r): ?>
                            <?php
                            $rid = (int) ($r['id'] ?? 0);
                            $isSel = $selected && (int) ($selected['id'] ?? 0) === $rid;
                            $st = (string) ($r['status'] ?? '');
                            $stLabel = $statusLabels[$st] ?? $st;
                            ?>
                            <li>
                                <a href="<?= htmlspecialchars(url('admin/system/updates?release=' . $rid), ENT_QUOTES, 'UTF-8') ?>"
                                   class="block px-4 py-3 hover:bg-slate-50 <?= $isSel ? 'bg-amber-50' : '' ?>">
                                    <div class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($r['version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars((string) ($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($releases === []): ?>
                            <li class="px-4 py-8 text-center text-sm text-slate-500">Aucun package déposé pour le moment.</li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="lg:col-span-3 space-y-6">
                    <?php if ($selected): ?>
                        <?php
                        $sid = (int) $selected['id'];
                        $sst = (string) ($selected['status'] ?? '');
                        $canDeploy = in_array($sst, ['validated', 'previewed', 'failed'], true);
                        ?>
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-bold text-slate-900">
                                Version <?= htmlspecialchars((string) $selected['version'], ENT_QUOTES, 'UTF-8') ?>
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                État : <?= htmlspecialchars($statusLabels[$sst] ?? $sst, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <?php if (!empty($selected['error_message'])): ?>
                                <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                                    <?= htmlspecialchars((string) $selected['error_message'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-4 flex flex-wrap gap-3">
                                <?php if ($canDeploy): ?>
                                    <form method="post" action="<?= htmlspecialchars(url('admin/system/updates/' . $sid . '/deploy'), ENT_QUOTES, 'UTF-8') ?>"
                                          onsubmit="return confirm('Déployer cette version sur la plateforme ? Une sauvegarde sera créée automatiquement.');">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-700">Déployer</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (in_array($sst, ['deployed', 'failed', 'rolled_back'], true)): ?>
                                    <form method="post" action="<?= htmlspecialchars(url('admin/system/updates/' . $sid . '/rollback'), ENT_QUOTES, 'UTF-8') ?>"
                                          onsubmit="return confirm('Restaurer la version précédente à partir de la sauvegarde ?');">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Restaurer la version précédente</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-900">Aperçu des fichiers</h3>
                            <div class="max-h-80 overflow-auto">
                                <table class="min-w-full divide-y divide-slate-100 text-sm">
                                    <thead class="bg-slate-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left font-semibold text-slate-600">Fichier</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Action</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Conflit</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <?php foreach ($files as $f): ?>
                                            <tr>
                                                <td class="px-4 py-2 font-mono text-xs text-slate-800"><?= htmlspecialchars((string) ($f['relative_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($actionLabels[(string) ($f['action'] ?? '')] ?? (string) ($f['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="px-3 py-2"><?= !empty($f['conflict']) ? 'Oui' : '—' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if ($files === []): ?>
                                            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">Aucun fichier listé.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                            Sélectionnez une mise à jour dans l’historique ou déposez un nouveau package.
                        </p>
                    <?php endif; ?>

                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-900">Journal</h3>
                        <ul class="max-h-56 overflow-y-auto divide-y divide-slate-50 text-sm">
                            <?php foreach ($logs as $log): ?>
                                <li class="px-4 py-2">
                                    <span class="text-xs text-slate-400"><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="ml-2 text-slate-800"><?= htmlspecialchars((string) ($log['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($logs === []): ?>
                                <li class="px-4 py-6 text-center text-slate-500">Aucun événement pour le moment.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </div>
</div>
