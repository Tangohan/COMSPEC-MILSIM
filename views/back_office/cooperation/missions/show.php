<?php
declare(strict_types=1);

use App\Support\CooperationDictionary;

$m = $interteamMission ?? [];
$participants = $interteamParticipants ?? [];
$isLead = !empty($interteamIsLead);
$canManage = !empty($interteamCanManage);
$canPilot = !empty($interteamCanPilot);
$canRespond = !empty($interteamCanRespond);
$partners = $interteamPartnerPicker ?? [];
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$status = (string) ($m['status'] ?? '');
$sessionTenantId = (int) ($sessionTenantId ?? 0);
$phaseLabel = function_exists('cooperation_mission_display_label') ? cooperation_mission_display_label($m) : CooperationDictionary::phaseLabel(CooperationDictionary::effectivePhase($m));
$prioKey = (string) ($m['cooperation_priority'] ?? 'routine');
$typoKey = trim((string) ($m['cooperation_typology'] ?? ''));
$prioLabel = CooperationDictionary::priorityChoices()[$prioKey] ?? '';
$typoLabel = trim((string) ($interteamCooperationTypologyLabel ?? ''));
if ($typoKey !== '' && $typoLabel === '') {
    $typoLabel = CooperationDictionary::typologyChoices()[$typoKey] ?? '';
}
$deadline = trim((string) ($m['proposal_deadline_at'] ?? ''));
$deadlineTs = $deadline !== '' ? strtotime($deadline) : false;
$deadlinePassed = $status === 'pending' && $deadlineTs !== false && $deadlineTs < time();
$deadlineDisplay = ($deadlineTs !== false) ? date('d/m/Y H:i', $deadlineTs) : $deadline;
$counterPendingShow = !empty($interteamCounterPending);
$missionMembers = $interteamMissionMembers ?? [];
$userPicker = $cooperationRoleUserPicker ?? [];
$snapshot = $cooperationActivationSnapshot ?? null;
$operationalStage = (string) ($interteamOperationalStage ?? ($m['operational_stage'] ?? 'opord_draft'));
$operationalChoices = is_array($interteamOperationalStageChoices ?? null) ? $interteamOperationalStageChoices : [];
$sitreps = is_array($interteamSitreps ?? null) ? $interteamSitreps : [];
$correctiveText = (string) ($interteamCorrectiveActionsText ?? '');
$resourcesText = (string) ($interteamLinkedResourcesText ?? '');
$lossesText = (string) ($interteamSimulatedLossesText ?? '');
$lessonsText = (string) ($interteamLessonsLearnedText ?? '');

$myParticipant = null;
foreach ($participants as $p) {
    if ((int) ($p['tenant_id'] ?? 0) === $sessionTenantId) {
        $myParticipant = $p;
        break;
    }
}
$myStatus = (string) ($myParticipant['status'] ?? '');
$isPartner = ($myParticipant['role'] ?? '') === 'partner';

$statusTone = match ($status) {
    'active' => 'bg-emerald-100 text-emerald-900 ring-emerald-200',
    'pending' => 'bg-amber-100 text-amber-950 ring-amber-200',
    'draft' => 'bg-slate-100 text-slate-800 ring-slate-200',
    'archived' => 'bg-slate-200 text-slate-700 ring-slate-300',
    default => 'bg-slate-100 text-slate-800 ring-slate-200',
};

$card = static function (string $title, string $desc, string $href, string $accent = 'slate'): void {
    $ring = match ($accent) {
        'sky' => 'border-sky-200 hover:border-sky-300 hover:bg-sky-50/40',
        'emerald' => 'border-emerald-200 hover:border-emerald-300 hover:bg-emerald-50/40',
        'amber' => 'border-amber-200 hover:border-amber-300 hover:bg-amber-50/40',
        default => 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/60',
    };
    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="group block rounded-xl border bg-white p-5 shadow-sm transition ' . $ring . '">';
    echo '<p class="text-sm font-bold text-slate-900 group-hover:text-slate-950">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p class="mt-2 text-xs text-slate-600 leading-relaxed">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p class="mt-4 text-xs font-semibold text-emerald-800">Ouvrir →</p>';
    echo '</a>';
};
?>
<div class="max-w-5xl mx-auto px-6 py-10 space-y-10">
    <header class="space-y-6">
        <div>
            <a href="<?= htmlspecialchars(cooperation_mission_index_url(), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Retour à la liste</a>
            <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800 px-6 py-8 sm:px-8 text-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Synthèse de coopération</p>
                    <h1 class="mt-3 text-2xl sm:text-3xl font-black tracking-tight text-white break-words"><?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset <?= htmlspecialchars($statusTone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phaseLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($typoLabel !== ''): ?>
                        <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-slate-100 ring-1 ring-white/15"><?= htmlspecialchars($typoLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($prioLabel !== ''): ?>
                        <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-slate-100 ring-1 ring-white/15">Priorité : <?= htmlspecialchars($prioLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($deadline !== ''): ?>
                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/10 shrink-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-300">Date limite de réponse</p>
                    <p class="mt-1 text-sm font-semibold text-white"><?= htmlspecialchars($deadlineDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($canPilot && $canManage && $operationalChoices !== []): ?>
            <p class="mt-6 text-sm text-slate-300">Conduite en cours :
                <strong class="text-white"><?= htmlspecialchars((string) ($operationalChoices[$operationalStage] ?? 'Non définie'), ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
            <?php endif; ?>
        </div>

        <?php if ($deadlinePassed): ?>
        <p class="text-sm text-amber-950 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">La date limite de réponse est dépassée. Relancez les unités ou ajustez le calendrier depuis la proposition.</p>
        <?php endif; ?>
        <?php if ($counterPendingShow && $canPilot && $canManage): ?>
        <p class="text-sm text-rose-950 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3">Une contre-proposition attend votre décision. <a class="font-semibold underline" href="<?= htmlspecialchars(cooperation_mission_negotiate_url($sid), ENT_QUOTES, 'UTF-8') ?>">Traiter dans Négociation</a></p>
        <?php endif; ?>
        <?php if ($isPartner && $myStatus === 'invited' && $canRespond): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 px-5 py-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-bold text-emerald-950">Invitation en attente</p>
                <p class="mt-1 text-xs text-emerald-900">Acceptez pour rejoindre cette coopération, ou refusez si votre unité ne peut pas s’engager.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/accept'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Accepter</button>
                </form>
                <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/decline'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Refuser</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Unités engagées</h2>
        <p class="mt-2 text-sm text-slate-600">Communautés participantes et état de leur engagement.</p>
        <ul class="mt-6 divide-y divide-slate-100">
            <?php foreach ($participants as $p): ?>
            <li class="py-4 flex flex-wrap justify-between gap-3 text-sm">
                <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($p['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-slate-600"><?php
                    $role = (string) ($p['role'] ?? '');
                    $roleLabel = CooperationDictionary::participantRoleLabel($role);
                    $st = (string) ($p['status'] ?? '');
                    $stLabel = CooperationDictionary::participantStateLabel($st);
                    echo htmlspecialchars($roleLabel . ' — ' . $stLabel, ENT_QUOTES, 'UTF-8');
                ?></span>
            </li>
            <?php endforeach; ?>
            <?php if ($participants === []): ?>
            <li class="py-4 text-sm text-slate-500">Aucune unité enregistrée pour le moment.</li>
            <?php endif; ?>
        </ul>

        <?php if ($canPilot && $status === 'draft'): ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/invite'), ENT_QUOTES, 'UTF-8') ?>" class="mt-8 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-6">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex-1 min-w-[200px]">
                <label for="partner_tenant_id" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Inviter une unité partenaire</label>
                <select id="partner_tenant_id" name="partner_tenant_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($partners as $t): ?>
                    <option value="<?= (int) ($t['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Envoyer l’invitation</button>
        </form>
        <?php endif; ?>

        <?php if ($canPilot && $status === 'pending' && $canManage): ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/activate'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 border-t border-slate-100 pt-6">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Lancer la coopération</button>
            <p class="mt-3 text-xs text-slate-500">Toutes les unités invitées doivent avoir accepté. Un espace commun sera préparé sur le brief de l’unité support.</p>
        </form>
        <?php endif; ?>
    </section>

    <?php if ($canPilot && $canManage): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
        <div>
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Conduite de la coopération</h2>
            <p class="mt-2 text-sm text-slate-600">Avancez étape par étape : préparation, validation, exécution, bilan, puis actions correctives.</p>
            <p class="mt-4 text-sm text-slate-700">Étape actuelle :
                <strong class="text-slate-900"><?= htmlspecialchars((string) ($operationalChoices[$operationalStage] ?? 'Non définie'), ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        </div>

        <?php if ($operationalChoices !== []): ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/operational-stage'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-6">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="operational_stage">Passer à l’étape</label>
                <select id="operational_stage" name="operational_stage" class="w-full max-w-md rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    <?php foreach ($operationalChoices as $slug => $label): ?>
                    <option value="<?= htmlspecialchars((string) $slug, ENT_QUOTES, 'UTF-8') ?>" <?= $operationalStage === $slug ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="opord_text">Ordre d’opération (brouillon)</label>
                <textarea id="opord_text" name="opord_text" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Intentions, objectif, règles d’engagement, organisation…"><?= htmlspecialchars((string) ($m['opord_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="command_validation_notes">Notes de validation du commandement</label>
                <textarea id="command_validation_notes" name="command_validation_notes" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Conditions de lancement, restrictions, arbitrages…"><?= htmlspecialchars((string) ($m['command_validation_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="aar_summary">Bilan de clôture</label>
                <textarea id="aar_summary" name="aar_summary" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Ce qui a fonctionné, écarts, recommandations…"><?= htmlspecialchars((string) ($m['aar_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="corrective_actions_text">Actions correctives</label>
                    <textarea id="corrective_actions_text" name="corrective_actions_text" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Qui fait quoi, échéance, état…"><?= htmlspecialchars($correctiveText, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="linked_resources_text">Ressources engagées</label>
                    <textarea id="linked_resources_text" name="linked_resources_text" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Véhicules, matériels, soutiens…"><?= htmlspecialchars($resourcesText, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="simulated_losses_text">Pertes simulées</label>
                    <textarea id="simulated_losses_text" name="simulated_losses_text" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Effectifs, matériels, conséquences…"><?= htmlspecialchars($lossesText, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="lessons_learned_text">Enseignements retenus</label>
                    <textarea id="lessons_learned_text" name="lessons_learned_text" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Points à conserver pour la prochaine coopération…"><?= htmlspecialchars($lessonsText, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
            <div>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer l’étape de conduite</button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($operationalStage === 'execution'): ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/sitrep'), ENT_QUOTES, 'UTF-8') ?>" class="border-t border-slate-100 pt-8 grid gap-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-700">Ajouter un point de situation</h3>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="sitrep_occurred_at">Date et heure</label>
                <input id="sitrep_occurred_at" type="datetime-local" name="sitrep_occurred_at" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="sitrep_summary">Situation</label>
                <textarea id="sitrep_summary" name="sitrep_summary" rows="2" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Situation, actions en cours, besoins, risques…"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="sitrep_notes">Complément (optionnel)</label>
                <input id="sitrep_notes" type="text" name="sitrep_notes" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Unité concernée, niveau de priorité…">
            </div>
            <div>
                <button type="submit" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Enregistrer le point de situation</button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($sitreps !== []): ?>
        <div class="border-t border-slate-100 pt-8">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-700">Journal des points de situation</h3>
            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach ($sitreps as $s): ?>
                <li class="py-4">
                    <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($s['occurred_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($s['actor_display_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-sm text-slate-800 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars((string) ($s['summary'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($canPilot && $canManage && ($missionMembers !== [] || $userPicker !== [])): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
        <div>
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Rôles sur cette coopération</h2>
            <p class="mt-2 text-sm text-slate-600">Désignations propres à ce dossier (indépendantes du rôle communautaire).</p>
        </div>
        <?php if ($missionMembers !== []): ?>
        <ul class="text-sm text-slate-700 space-y-2">
            <?php
            $roleChoices = CooperationDictionary::missionMemberRoleChoices();
            foreach ($missionMembers as $mm):
                $rslug = (string) ($mm['role_slug'] ?? '');
                $rlab = $roleChoices[$rslug] ?? $rslug;
                ?>
            <li class="flex flex-wrap gap-2">
                <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($mm['user_display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-slate-500">—</span>
                <span><?= htmlspecialchars($rlab, ENT_QUOTES, 'UTF-8') ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($userPicker !== []): ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/assign-member'), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-6">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1" for="member_user_id">Membre</label>
                <select id="member_user_id" name="member_user_id" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm min-w-[200px]" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($userPicker as $u): ?>
                    <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1" for="mission_role_slug">Rôle</label>
                <select id="mission_role_slug" name="mission_role_slug" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    <?php foreach (CooperationDictionary::missionMemberRoleChoices() as $slug => $rlab): ?>
                    <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rlab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Désigner</button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($snapshot !== null && ($canPilot && $canManage)): ?>
    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-6 sm:p-8 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Photo à l’activation</h2>
        <p class="mt-2 text-sm text-slate-600">Éléments figés au lancement (pour l’historique et la cohérence du dossier).</p>
        <p class="mt-3 text-xs text-slate-700">Enregistrée le <?= htmlspecialchars((string) ($snapshot['captured_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php endif; ?>

    <?php if ($isLead && $canManage && !empty($participants)): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Co-pilotage</h2>
        <p class="mt-2 text-sm text-slate-600">Désignez une unité partenaire déjà confirmée pour qu’elle puisse inviter et lancer avec vous.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/promote-co-lead'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <select name="co_lead_tenant_id" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm min-w-[220px]" required>
                <option value="">— Choisir une unité partenaire —</option>
                <?php foreach ($participants as $p): ?>
                <?php if (($p['role'] ?? '') === 'partner' && ($p['status'] ?? '') === 'active'): ?>
                <option value="<?= (int) ($p['tenant_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($p['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-900">Désigner co-pilote</button>
        </form>
    </section>
    <?php endif; ?>

    <section>
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800 mb-5">Accès rapide</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <?php $card('Espace commun', 'Fil coordonné, visio et autorisations d’accès au brief.', cooperation_mission_exchange_url($sid), 'sky'); ?>
            <?php $card('Chronologie', 'Journal des événements et décisions notables.', cooperation_mission_timeline_url($sid), 'slate'); ?>
            <?php $card('Réunion', 'Salon vidéo, compte rendu et historique des réunions.', cooperation_mission_meeting_url($sid), 'emerald'); ?>
            <?php $card('Structures & liaisons', 'Organisation, points de contact et coordination.', cooperation_mission_orbat_url($sid), 'amber'); ?>
            <?php $card('Autorisation de partage', 'Valider ce que vous acceptez de partager (code par e-mail).', cooperation_mission_consent_url($sid), 'slate'); ?>
            <?php if ($status === 'pending'): ?>
            <?php $card('Négociation', 'Contre-propositions et réponses de l’unité support.', cooperation_mission_negotiate_url($sid), 'amber'); ?>
            <?php endif; ?>
            <?php if ($canPilot): ?>
            <?php $card('Proposition', 'Titre, typologie, priorité et échéance de réponse.', cooperation_mission_edit_url($sid), 'slate'); ?>
            <?php endif; ?>
            <?php if ($status === 'archived'): ?>
            <?php $card('Retour d’expérience', 'Bilan et recommandations par unité.', cooperation_mission_rex_url($sid), 'slate'); ?>
            <?php endif; ?>
            <?php if ($canPilot && $canManage && in_array($status, ['draft', 'active'], true)): ?>
            <?php $card('Clôture', 'Terminer la coopération et préparer le retour d’expérience.', cooperation_mission_archive_url($sid), 'slate'); ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canPilot && $canManage && in_array($status, ['archived', 'active', 'pending'], true)): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Dupliquer comme brouillon</h2>
        <p class="mt-2 text-sm text-slate-600">Crée une nouvelle coopération vierge de participants, en reprenant le cadrage général.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/duplicate'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="text" name="duplicate_title" class="flex-1 min-w-[200px] rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Titre de la copie" maxlength="255">
            <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Dupliquer</button>
        </form>
    </section>
    <?php endif; ?>

    <?php
    $trainingCompetencyUrl = trim((string) ($trainingCompetencyCommandUrl ?? ''));
    if ($trainingCompetencyUrl !== '' && $status === 'active'): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Compétences & formations</h2>
        <p class="text-sm text-slate-600 mt-2">Tableau de pilotage des compétences de votre communauté (selon vos droits).</p>
        <a href="<?= htmlspecialchars($trainingCompetencyUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex text-sm font-semibold text-emerald-800 underline">Ouvrir le centre compétences</a>
    </section>
    <?php endif; ?>
</div>
