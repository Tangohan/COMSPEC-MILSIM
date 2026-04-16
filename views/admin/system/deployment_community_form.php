<?php
declare(strict_types=1);
$c = is_array($deploymentCommunity ?? null) ? $deploymentCommunity : [];
$members = is_array($deploymentCommunityMembers ?? null) ? $deploymentCommunityMembers : [];
$feedbacks = is_array($deploymentCommunityFeedbacks ?? null) ? $deploymentCommunityFeedbacks : [];
$labels = is_array($deploymentFeedbackLabels ?? null) ? $deploymentFeedbackLabels : \App\Controllers\Admin\System\PlatformDeploymentAdminController::testerFeedbackLabels();
$statusLbl = $labels['status'] ?? [];
$severityLbl = $labels['severity'] ?? [];
$typeLbl = $labels['type'] ?? [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$id = (int) ($c['id'] ?? 0);
$fbOpen = (int) ($deploymentCommunityFeedbackOpen ?? 0);
$fbTotal = (int) ($deploymentCommunityFeedbackTotal ?? 0);
$activeMemberCount = (int) ($deploymentCommunityActiveMemberCount ?? 0);
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
            <a href="<?= htmlspecialchars(url('admin/system/deployment'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Publications</a>
            <span class="text-slate-400" aria-hidden="true"> · </span>
            <a href="<?= htmlspecialchars(url('admin/system/deployment/communities'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Communautés de test</a>
        </p>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="mt-1 font-mono text-xs text-slate-500"><?= htmlspecialchars((string) ($c['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="flex flex-wrap gap-2 text-right text-sm">
                <span class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-700">Membres actifs : <strong class="tabular-nums"><?= $activeMemberCount ?></strong></span>
                <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-amber-950">Retours ouverts : <strong class="tabular-nums"><?= $fbOpen ?></strong> / <?= $fbTotal ?></span>
            </div>
        </div>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Paramètres</h2>
            <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/communities/' . $id . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Nom affiché</label>
                    <input name="name" required value="<?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Description</label>
                    <textarea name="description" rows="3" class="mt-1 w-full max-w-2xl rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($c['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Priorité</label>
                        <input type="number" name="priority" value="<?= (int) ($c['priority'] ?? 100) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Début de validité (optionnel)</label>
                        <input name="valid_from" value="<?= htmlspecialchars((string) ($c['valid_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="AAAA-MM-JJ HH:MM:SS">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Fin de validité (optionnel)</label>
                        <input name="valid_until" value="<?= htmlspecialchars((string) ($c['valid_until'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !empty($c['is_active']) ? 'checked' : '' ?>> Communauté active</label>
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Enregistrer</button>
            </form>
        </section>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" id="membres">
            <h2 class="text-lg font-bold text-slate-900">Membres</h2>
            <p class="mt-1 text-sm text-slate-500">Ajoutez un compte par <strong>e-mail</strong> (recommandé, tous les tenants liés à cette adresse sont inscrits) ou par <strong>ID utilisateur</strong> numérique.</p>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-100">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                            <th class="px-3 py-2 font-semibold">ID</th>
                            <th class="px-3 py-2 font-semibold">E-mail</th>
                            <th class="px-3 py-2 font-semibold">Nom affiché</th>
                            <th class="px-3 py-2 font-semibold">Indicatif</th>
                            <th class="px-3 py-2 font-semibold">Inscrit le</th>
                            <th class="px-3 py-2 font-semibold">Expire</th>
                            <th class="px-3 py-2 font-semibold">Statut</th>
                            <th class="px-3 py-2 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($members as $pack): ?>
                            <?php $m = is_array($pack['membership'] ?? null) ? $pack['membership'] : []; ?>
                            <?php $u = is_array($pack['user'] ?? null) ? $pack['user'] : []; ?>
                            <?php $uid = (int) ($m['user_id'] ?? 0); ?>
                            <tr>
                                <td class="px-3 py-2.5 font-mono text-xs"><?= $uid ?></td>
                                <td class="px-3 py-2.5 text-xs text-slate-800"><?= htmlspecialchars((string) ($u['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2.5"><?= htmlspecialchars(trim((string) ($u['display_name'] ?? '')) !== '' ? (string) $u['display_name'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2.5 text-slate-600"><?= htmlspecialchars(trim((string) ($u['callsign'] ?? '')) !== '' ? (string) $u['callsign'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-xs text-slate-500"><?= htmlspecialchars((string) ($m['joined_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-xs text-slate-500"><?= htmlspecialchars((string) ($m['expires_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2.5"><span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"><?= htmlspecialchars((string) ($m['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="px-3 py-2.5 text-right">
                                    <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/communities/' . $id . '/members/remove'), ENT_QUOTES, 'UTF-8') ?>" class="inline" onsubmit="return confirm('Retirer ce membre ?');">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($members === []): ?>
                <p class="mt-4 text-sm text-slate-500">Aucun membre pour l’instant.</p>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/communities/' . $id . '/members'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-4 border-t border-slate-100 pt-6">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/40 p-4">
                        <h3 class="text-sm font-bold text-emerald-950">Par e-mail</h3>
                        <p class="mt-1 text-xs text-emerald-900/80">Identique à la connexion du membre. Si plusieurs comptes partagent l’e-mail, tous sont ajoutés.</p>
                        <label class="mt-3 block text-xs font-semibold text-slate-700">E-mail</label>
                        <input type="email" name="member_email" autocomplete="off" class="mt-1 w-full max-w-md rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="prenom.nom@exemple.fr">
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4">
                        <h3 class="text-sm font-bold text-slate-900">Par identifiant</h3>
                        <p class="mt-1 text-xs text-slate-600">Utilisé si vous connaissez déjà l’ID numérique du compte (laisser l’e-mail vide).</p>
                        <label class="mt-3 block text-xs font-semibold text-slate-700">ID utilisateur</label>
                        <input type="number" name="user_id" min="1" class="mt-1 w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="ex. 42">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Fin d’inclusion (optionnel, s’applique à l’ajout)</label>
                    <input name="expires_at" class="mt-1 w-full max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="AAAA-MM-JJ HH:MM:SS">
                </div>
                <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-amber-700">Ajouter le ou les membres</button>
            </form>
        </section>

        <section class="mt-8 scroll-mt-24 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" id="retours-testeurs" aria-labelledby="fb-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="fb-heading" class="text-lg font-bold text-slate-900">Retours testeurs</h2>
                <p class="text-sm text-slate-600">Ouverts : <strong class="text-amber-800"><?= $fbOpen ?></strong> · Total : <?= $fbTotal ?></p>
            </div>
            <p class="mt-2 text-sm text-slate-500">Données issues de <span class="font-mono text-xs">tester_feedback</span> pour cette communauté. La modification de statut pourra être ajoutée dans une prochaine itération.</p>

            <?php if ($feedbacks === []): ?>
                <p class="mt-6 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Aucun retour lié à cette communauté.</p>
            <?php else: ?>
                <div class="mt-6 space-y-4">
                    <?php foreach ($feedbacks as $fb): ?>
                        <?php
                        $t = (string) ($fb['type'] ?? '');
                        $s = (string) ($fb['severity'] ?? '');
                        $st = (string) ($fb['status'] ?? '');
                        ?>
                        <article class="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h3 class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($fb['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                <span class="shrink-0 text-xs text-slate-500"><?= htmlspecialchars((string) ($fb['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span class="rounded bg-white px-2 py-0.5 font-medium text-slate-700 ring-1 ring-slate-200"><?= htmlspecialchars((string) ($fb['module_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?> v<?= htmlspecialchars((string) ($fb['module_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="rounded bg-white px-2 py-0.5 text-slate-600 ring-1 ring-slate-200"><?= htmlspecialchars($typeLbl[$t] ?? $t, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="rounded bg-white px-2 py-0.5 text-slate-600 ring-1 ring-slate-200"><?= htmlspecialchars($severityLbl[$s] ?? $s, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="rounded bg-amber-100 px-2 py-0.5 font-medium text-amber-950 ring-1 ring-amber-200"><?= htmlspecialchars($statusLbl[$st] ?? $st, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p class="mt-2 text-xs text-slate-600">
                                <span class="font-semibold">Auteur :</span>
                                <?= htmlspecialchars((string) ($fb['user_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($fb['user_callsign'])): ?>
                                    <span class="text-slate-400">· <?= htmlspecialchars((string) $fb['user_callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($fb['description'])): ?>
                                <div class="mt-3 rounded-md border border-slate-200 bg-white p-3 text-sm text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars((string) $fb['description'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
