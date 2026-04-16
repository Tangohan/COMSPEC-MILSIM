<?php
declare(strict_types=1);
$list = is_array($deploymentCommunities ?? null) ? $deploymentCommunities : [];
$recentFb = is_array($deploymentRecentFeedback ?? null) ? $deploymentRecentFeedback : [];
$labels = \App\Controllers\Admin\System\PlatformDeploymentAdminController::testerFeedbackLabels();
$statusLbl = $labels['status'] ?? [];
$severityLbl = $labels['severity'] ?? [];
$typeLbl = $labels['type'] ?? [];
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
            <span class="text-slate-600">Communautés de test</span>
        </p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Communautés de préqualification</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Groupes de testeurs : membres rattachés par e-mail ou ID, accès aux modules en avant-première selon les règles de publication.
            Les retours saisis par les testeurs (table <span class="font-mono text-xs">tester_feedback</span>) sont listés ci-dessous et détaillés sur chaque fiche communauté.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <?php
            $totalMembers = 0;
            $totalOpenFb = 0;
            foreach ($list as $r) {
                $totalMembers += (int) ($r['stats_member_count'] ?? 0);
                $totalOpenFb += (int) ($r['stats_feedback_open'] ?? 0);
            }
            ?>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Communautés</p>
                <p class="mt-1 text-2xl font-black text-slate-900"><?= count($list) ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Membres actifs (total)</p>
                <p class="mt-1 text-2xl font-black text-emerald-800"><?= $totalMembers ?></p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-900">Retours ouverts</p>
                <p class="mt-1 text-2xl font-black text-amber-950"><?= $totalOpenFb ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:col-span-2 lg:col-span-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Action</p>
                <p class="mt-2 text-xs text-slate-600">Ouvrez une ligne pour gérer les membres et consulter les feedbacks de la communauté.</p>
            </div>
        </div>

        <div class="mt-10 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Nom</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Membres</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Retours ouverts</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Total retours</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Active</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Priorité</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($list as $row): ?>
                        <?php
                        $id = (int) ($row['id'] ?? 0);
                        $editUrl = url('admin/system/deployment/communities/' . $id . '/edit');
                        ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 tabular-nums text-slate-800"><?= (int) ($row['stats_member_count'] ?? 0) ?></td>
                            <td class="px-4 py-3 tabular-nums">
                                <?php $o = (int) ($row['stats_feedback_open'] ?? 0); ?>
                                <span class="<?= $o > 0 ? 'font-semibold text-amber-800' : 'text-slate-500' ?>"><?= $o ?></span>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-600"><?= (int) ($row['stats_feedback_total'] ?? 0) ?></td>
                            <td class="px-4 py-3"><?= !empty($row['is_active']) ? '<span class="text-emerald-700 font-semibold">Oui</span>' : '<span class="text-slate-400">Non</span>' ?></td>
                            <td class="px-4 py-3 text-slate-600 tabular-nums"><?= (int) ($row['priority'] ?? 0) ?></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-amber-700 hover:text-amber-900">Gérer</a>
                                <span class="text-slate-300" aria-hidden="true">·</span>
                                <a href="<?= htmlspecialchars($editUrl . '#retours-testeurs', ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Retours</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($list === []): ?>
            <p class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-600">Aucune communauté en base. Exécutez la migration des tables de préqualification puis rechargez.</p>
        <?php endif; ?>

        <section class="mt-12 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="recent-fb-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="recent-fb-heading" class="text-lg font-bold text-slate-900">Derniers retours testeurs</h2>
                    <p class="mt-1 text-sm text-slate-500">Synthèse multi-communautés. Détail et contexte sur la fiche « Gérer » de chaque groupe.</p>
                </div>
            </div>
            <?php if ($recentFb === []): ?>
                <p class="mt-6 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    Aucun retour enregistré pour l’instant. Les feedbacks apparaîtront ici lorsque le formulaire côté testeur sera branché sur <span class="font-mono text-xs">tester_feedback</span>.
                </p>
            <?php else: ?>
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4 font-semibold">Date</th>
                                <th class="py-2 pr-4 font-semibold">Communauté</th>
                                <th class="py-2 pr-4 font-semibold">Module</th>
                                <th class="py-2 pr-4 font-semibold">Auteur</th>
                                <th class="py-2 pr-4 font-semibold">Type</th>
                                <th class="py-2 pr-4 font-semibold">Gravité</th>
                                <th class="py-2 pr-4 font-semibold">Statut</th>
                                <th class="py-2 font-semibold">Sujet</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($recentFb as $fb): ?>
                                <?php
                                $cid = (int) ($fb['community_id'] ?? 0);
                                $t = (string) ($fb['type'] ?? '');
                                $s = (string) ($fb['severity'] ?? '');
                                $st = (string) ($fb['status'] ?? '');
                                ?>
                                <tr>
                                    <td class="py-2.5 pr-4 whitespace-nowrap text-xs text-slate-500"><?= htmlspecialchars((string) ($fb['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-2.5 pr-4">
                                        <?php if ($cid > 0): ?>
                                            <a href="<?= htmlspecialchars(url('admin/system/deployment/communities/' . $cid . '/edit#retours-testeurs'), ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-amber-800 hover:underline"><?= htmlspecialchars((string) ($fb['community_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                                            <div class="font-mono text-[10px] text-slate-400"><?= htmlspecialchars((string) ($fb['community_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 pr-4 font-mono text-xs"><?= htmlspecialchars((string) ($fb['module_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <span class="text-slate-400"><?= htmlspecialchars((string) ($fb['module_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="py-2.5 pr-4 text-xs">
                                        <span class="text-slate-800"><?= htmlspecialchars((string) ($fb['user_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($fb['user_callsign'])): ?>
                                            <div class="text-slate-500"><?= htmlspecialchars((string) $fb['user_callsign'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 pr-4"><?= htmlspecialchars($typeLbl[$t] ?? $t, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-2.5 pr-4"><?= htmlspecialchars($severityLbl[$s] ?? $s, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-2.5 pr-4"><?= htmlspecialchars($statusLbl[$st] ?? $st, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-2.5 max-w-xs truncate text-slate-800" title="<?= htmlspecialchars((string) ($fb['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($fb['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
