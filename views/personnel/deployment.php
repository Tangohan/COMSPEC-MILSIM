<?php
$rows = is_array($deploymentRows ?? null) ? $deploymentRows : [];
$canManage = !empty($deploymentCanManage);
$q = (string) ($deploymentSearch ?? '');
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="mx-auto w-full max-w-7xl space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-black uppercase tracking-[0.25em] text-emerald-600">Opérations RH</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Déploiement du personnel</h1>
        <p class="mt-2 max-w-4xl text-sm text-slate-600">Déployez les personnels disponibles puis validez leur check-up obligatoire: mods, qualification du rôle/métier, recyclage ALPHA/Bravo, VMP, dernier entretien et informations personnelles (poids, groupe sanguin, matricule, affectation).</p>
    </div>

    <?php if ($canManage): ?>
    <form method="get" action="<?= htmlspecialchars(url('deploiement'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label for="q" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Recherche personnel</label>
        <div class="flex gap-2">
            <input type="text" id="q" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom, callsign, email" />
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrer</button>
        </div>
    </form>
    <?php endif; ?>

    <div class="space-y-4">
        <?php foreach ($rows as $r): ?>
            <?php
                $uid = (int) ($r['user_id'] ?? 0);
                $status = (string) ($r['deployment_status'] ?? 'non_deploye');
                $isValidated = $status === 'checkup_validated';
                $deployedAt = trim((string) ($r['deployed_at'] ?? ''));
                $anomalies = is_array($r['anomalies'] ?? null) ? $r['anomalies'] : [];
            ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900"><?= htmlspecialchars((string) ($r['display_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="text-sm text-slate-600"><?= htmlspecialchars((string) ($r['callsign'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-xs text-slate-500">Unité: <strong><?= htmlspecialchars((string) ($r['unit_name'] ?? 'Non affecté'), ENT_QUOTES, 'UTF-8') ?></strong> · Rôle: <strong><?= htmlspecialchars((string) ($r['primary_role'] ?? 'Non défini'), ENT_QUOTES, 'UTF-8') ?></strong></p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide <?= $isValidated ? 'bg-emerald-100 text-emerald-800' : ($status === 'deployed' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700') ?>"><?= $isValidated ? 'Check-up validé' : ($status === 'deployed' ? 'Déployé' : 'Non déployé') ?></span>
                        <?php if ($deployedAt !== ''): ?><p class="mt-2 text-xs text-slate-500">Déployé le <?= htmlspecialchars($deployedAt, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    </div>
                </div>

                <?php if ($canManage && $status !== 'deployed' && !$isValidated): ?>
                    <form method="post" action="<?= htmlspecialchars(url('deploiement/' . $uid . '/assigner'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>" />
                        <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-700">Déployer ce personnel</button>
                    </form>
                <?php endif; ?>

                <?php if ($status === 'deployed' || $isValidated): ?>
                    <form method="post" action="<?= htmlspecialchars(url('deploiement/' . $uid . '/checkup'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 space-y-4 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>" />
                        <h3 class="text-sm font-black uppercase tracking-[0.18em] text-slate-700">Check-up obligatoire</h3>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <?php
                            $checks = [
                                'mods_up_to_date' => 'Mods à jour',
                                'role_qualified_authorized' => 'Rôle qualifié et autorisé (métier/emploi)',
                                'recycling_alpha_bravo_up_to_date' => 'Recyclage ALPHA et Bravo à jour',
                                'vmp_up_to_date' => 'VMP à jour',
                                'last_interview_done' => 'Dernier entretien effectué',
                            ];
                            foreach ($checks as $k => $label):
                                $checked = (int) ($r[$k] ?? 0) === 1;
                            ?>
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <input type="hidden" name="<?= $k ?>" value="0" />
                                    <input type="checkbox" name="<?= $k ?>" value="1" <?= $checked ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="text-xs font-bold text-slate-600">Poids (kg)
                                <input type="number" step="0.1" min="0" max="350" name="weight_kg" value="<?= htmlspecialchars((string) ($r['weight_kg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </label>
                            <label class="text-xs font-bold text-slate-600">Groupe sanguin
                                <input type="text" name="blood_type" value="<?= htmlspecialchars((string) (($r['blood_type'] ?? '') ?: ($r['profile_blood_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="O+, A-, ..." />
                            </label>
                            <label class="text-xs font-bold text-slate-600">Matricule
                                <input type="text" name="matricule" value="<?= htmlspecialchars((string) (($r['matricule'] ?? '') ?: ($r['matricule_internal'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </label>
                            <label class="text-xs font-bold text-slate-600">Affectation
                                <input type="text" name="assignment_label" value="<?= htmlspecialchars((string) (($r['assignment_label'] ?? '') ?: ($r['unit_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </label>
                        </div>

                        <label class="block text-xs font-bold text-slate-600">Notes check-up
                            <textarea name="checkup_notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Détails complémentaires, restrictions temporaires..."><?= htmlspecialchars((string) ($r['checkup_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer / valider check-up</button>
                        </div>
                    </form>

                    <form method="post" action="<?= htmlspecialchars(url('deploiement/' . $uid . '/anomalie'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-4">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>" />
                        <label class="block text-xs font-bold uppercase tracking-[0.14em] text-rose-800">Signaler une anomalie</label>
                        <textarea name="anomaly_message" rows="2" class="mt-2 w-full rounded-lg border border-rose-300 px-3 py-2 text-sm" placeholder="Ex: document VMP expiré, erreur de matricule, modpack non conforme..."></textarea>
                        <button type="submit" class="mt-2 rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-rose-800">Envoyer anomalie</button>
                    </form>

                    <?php if ($anomalies !== []): ?>
                        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                            <p class="text-xs font-black uppercase tracking-[0.14em] text-amber-800">Anomalies récentes</p>
                            <ul class="mt-2 space-y-2 text-sm text-amber-950">
                                <?php foreach ($anomalies as $a): ?>
                                    <li class="rounded-lg border border-amber-200 bg-white px-3 py-2">
                                        <p><?= htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-1 text-[11px] text-amber-700">Par <?= htmlspecialchars((string) ($a['reported_by_name'] ?? 'inconnu'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($a['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($rows === []): ?>
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Aucun personnel à afficher sur cette page.</div>
        <?php endif; ?>
    </div>
</section>
