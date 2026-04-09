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
$counterPendingShow = !empty($interteamCounterPending);
$missionMembers = $interteamMissionMembers ?? [];
$userPicker = $cooperationRoleUserPicker ?? [];
$snapshot = $cooperationActivationSnapshot ?? null;

$myParticipant = null;
foreach ($participants as $p) {
    if ((int) ($p['tenant_id'] ?? 0) === $sessionTenantId) {
        $myParticipant = $p;
        break;
    }
}
$myStatus = (string) ($myParticipant['status'] ?? '');
$isPartner = ($myParticipant['role'] ?? '') === 'partner';

$card = static function (string $title, string $desc, string $href, string $accent = 'slate'): void {
    $ring = match ($accent) {
        'sky' => 'ring-sky-200 hover:border-sky-300',
        'emerald' => 'ring-emerald-200 hover:border-emerald-300',
        'amber' => 'ring-amber-200 hover:border-amber-300',
        default => 'ring-slate-200 hover:border-slate-300',
    };
    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md ' . $ring . ' ring-1">';
    echo '<p class="text-sm font-bold text-slate-900">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p class="mt-1 text-xs text-slate-600 leading-relaxed">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p class="mt-3 text-xs font-semibold text-emerald-800">Ouvrir →</p>';
    echo '</a>';
};
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div class="mb-2">
        <a href="<?= htmlspecialchars(cooperation_mission_index_url(), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Retour à la liste</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-2 text-2xl font-black text-slate-900"><?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-2 text-sm text-slate-600">État : <strong class="text-slate-800"><?= htmlspecialchars($phaseLabel, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <?php if ($prioLabel !== '' || $typoLabel !== '' || $deadline !== ''): ?>
        <ul class="mt-2 text-xs text-slate-600 space-y-1">
            <?php if ($typoLabel !== ''): ?>
            <li>Typologie : <strong class="text-slate-800"><?= htmlspecialchars($typoLabel, ENT_QUOTES, 'UTF-8') ?></strong></li>
            <?php endif; ?>
            <?php if ($prioLabel !== ''): ?>
            <li>Priorité : <strong class="text-slate-800"><?= htmlspecialchars($prioLabel, ENT_QUOTES, 'UTF-8') ?></strong></li>
            <?php endif; ?>
            <?php if ($deadline !== ''): ?>
            <li>Date limite de réponse souhaitée : <strong class="text-slate-800"><?= htmlspecialchars($deadline, ENT_QUOTES, 'UTF-8') ?></strong></li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
        <?php if ($deadlinePassed): ?>
        <p class="mt-3 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">La date limite de réponse est dépassée. Relancez les unités ou ajustez le calendrier depuis la proposition.</p>
        <?php endif; ?>
        <?php if ($counterPendingShow && $canPilot && $canManage): ?>
        <p class="mt-3 text-sm text-rose-900 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">Une contre-proposition attend votre décision. <a class="font-semibold underline" href="<?= htmlspecialchars(cooperation_mission_negotiate_url($sid), ENT_QUOTES, 'UTF-8') ?>">Traiter dans Négociation</a></p>
        <?php endif; ?>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Unités engagées</h2>
        <ul class="mt-4 divide-y divide-slate-100">
            <?php foreach ($participants as $p): ?>
            <li class="py-3 flex flex-wrap justify-between gap-2 text-sm">
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
        </ul>

        <?php if ($isPartner && $myStatus === 'invited' && $canRespond): ?>
        <div class="mt-6 flex flex-wrap gap-3">
            <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/accept'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Accepter la proposition</button>
            </form>
            <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/decline'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Refuser</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($canPilot && $status === 'draft'): ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/invite'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-6">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex-1 min-w-[200px]">
                <label for="partner_tenant_id" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Inviter une unité partenaire</label>
                <select id="partner_tenant_id" name="partner_tenant_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">— Choisir —</option>
                    <?php foreach ($partners as $t): ?>
                    <option value="<?= (int) ($t['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Envoyer la proposition</button>
        </form>
        <?php endif; ?>

        <?php if ($canPilot && $status === 'pending' && $canManage): ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/activate'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Lancer la coopération</button>
            <p class="mt-2 text-xs text-slate-500">Toutes les unités invitées doivent avoir accepté. Un espace commun sera préparé sur le brief de l’unité support.</p>
        </form>
        <?php endif; ?>
    </section>

    <?php if ($canPilot && $canManage && $missionMembers !== []): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Rôles sur cette coopération</h2>
        <ul class="mt-3 text-sm text-slate-700 space-y-1">
            <?php
            $roleChoices = CooperationDictionary::missionMemberRoleChoices();
            foreach ($missionMembers as $mm):
                $rslug = (string) ($mm['role_slug'] ?? '');
                $rlab = $roleChoices[$rslug] ?? $rslug;
                ?>
            <li><?= htmlspecialchars((string) ($mm['user_display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($rlab, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($canPilot && $canManage && $userPicker !== []): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Attribuer un rôle métier</h2>
        <p class="mt-2 text-xs text-slate-600">Le rôle s’applique à cette coopération uniquement (indépendamment du rôle communautaire).</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/assign-member'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Membre</label>
                <select name="member_user_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm min-w-[200px]" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($userPicker as $u): ?>
                    <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Rôle</label>
                <select name="mission_role_slug" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <?php foreach (CooperationDictionary::missionMemberRoleChoices() as $slug => $rlab): ?>
                    <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rlab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($snapshot !== null && ($canPilot && $canManage)): ?>
    <section class="rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Photo à l’activation</h2>
        <p class="mt-2 text-xs text-slate-600">Éléments figés au lancement (audit et cohérence avec l’historique).</p>
        <p class="mt-2 text-xs text-slate-700">Enregistrée le <?= htmlspecialchars((string) ($snapshot['captured_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php endif; ?>

    <?php if ($isLead && $canManage && !empty($participants)): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Co-pilotage</h2>
        <p class="mt-2 text-xs text-slate-600">Désignez une unité partenaire déjà confirmée pour qu’elle puisse inviter et lancer avec vous.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/promote-co-lead'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <select name="co_lead_tenant_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm min-w-[200px]" required>
                <option value="">— Choisir une unité partenaire —</option>
                <?php foreach ($participants as $p): ?>
                <?php if (($p['role'] ?? '') === 'partner' && ($p['status'] ?? '') === 'active'): ?>
                <option value="<?= (int) ($p['tenant_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($p['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Désigner co-pilote</button>
        </form>
    </section>
    <?php endif; ?>

    <section>
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800 mb-4">Accès rapide</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <?php $card('Espace commun', 'Fil coordonné, visio et autorisations d’accès au brief.', cooperation_mission_exchange_url($sid), 'sky'); ?>
            <?php $card('Chronologie', 'Journal des événements et décisions notables.', cooperation_mission_timeline_url($sid), 'slate'); ?>
            <?php $card('Réunion', 'Salon vidéo, compte rendu et historique des réunions.', cooperation_mission_meeting_url($sid), 'emerald'); ?>
            <?php $card('Structures & liaisons', 'ORBAT, points de contact et coordination cartographique.', cooperation_mission_orbat_url($sid), 'amber'); ?>
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
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Dupliquer comme brouillon</h2>
        <p class="mt-2 text-xs text-slate-600">Crée une nouvelle coopération vierge de participants, en reprenant le cadrage général.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/duplicate'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="text" name="duplicate_title" class="flex-1 min-w-[200px] rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Titre de la copie" maxlength="255">
            <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Dupliquer</button>
        </form>
    </section>
    <?php endif; ?>

    <?php
    $trainingCompetencyUrl = trim((string) ($trainingCompetencyCommandUrl ?? ''));
    if ($trainingCompetencyUrl !== '' && $status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Compétences & formations</h2>
        <p class="text-sm text-slate-600 mt-2">Tableau de pilotage des compétences de votre communauté (selon vos droits).</p>
        <a href="<?= htmlspecialchars($trainingCompetencyUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-flex text-sm font-semibold text-emerald-800 underline">Ouvrir le centre compétences</a>
    </section>
    <?php endif; ?>
</div>
