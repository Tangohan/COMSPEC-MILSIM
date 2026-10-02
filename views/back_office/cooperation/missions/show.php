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

$invitationRule = is_array($interteamInvitationRule ?? null) ? $interteamInvitationRule : ['allowed' => false, 'reinforcement' => false];
$launchReady = is_array($interteamLaunchReadiness ?? null) ? $interteamLaunchReadiness : ['ok' => false, 'reason' => '', 'accepted' => [], 'pending' => [], 'ignored' => []];
$isTerminal = !empty($interteamIsTerminal);
$pilotActions = $canPilot && $canManage && !$isTerminal;

$myParticipant = null;
foreach ($participants as $p) {
    if ((int) ($p['tenant_id'] ?? 0) === $sessionTenantId) {
        $myParticipant = $p;
        break;
    }
}
$myStatus = (string) ($myParticipant['status'] ?? '');
$isPartner = ($myParticipant['role'] ?? '') === 'partner';


?>
<div class="max-w-5xl mx-auto px-6 py-10 space-y-10">
    <header id="coop-header" class="space-y-6" data-coop-region>
        <div>
            <a href="<?= htmlspecialchars(cooperation_mission_index_url(), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Retour à la liste</a>
            <?php $cooperationProgressShowAction = false; require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800 px-6 py-8 sm:px-8 text-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Synthèse de coopération</p>
                    <h1 class="mt-3 text-2xl sm:text-3xl font-black tracking-tight text-white break-words"><?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <?php $stBadge = \App\Services\Cooperation\CooperationProgress::stateBadge($m); $ui_badge_label = $stBadge['label']; $ui_badge_variant = $stBadge['variant']; require base_path('views/partials/ui/badge.php'); ?>
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
        <div id="invitation" class="scroll-mt-24 rounded-xl border border-emerald-200 bg-emerald-50/80 px-5 py-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-bold text-emerald-950">Invitation en attente</p>
                <p class="mt-1 text-xs text-emerald-900">Acceptez pour rejoindre cette coopération, ou refusez si votre unité ne peut pas s’engager.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/accept'), ENT_QUOTES, 'UTF-8') ?>" data-coop-ajax>
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Accepter</button>
                </form>
                <details class="coop-decline">
                    <summary class="cursor-pointer list-none rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Refuser…</summary>
                    <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/decline'), ENT_QUOTES, 'UTF-8') ?>" data-coop-ajax class="mt-3 w-full max-w-md space-y-2 rounded-xl border border-slate-200 bg-white p-4"
                          data-ui-confirm="1" data-ui-confirm-title="Refuser l’invitation ?"
                          data-ui-confirm-body="Votre unité ne participera pas à cette coopération. L’unité support est prévenue (avec votre motif s’il est renseigné) et pourra vous réinviter plus tard.">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <label for="decline_reason" class="block text-xs font-bold text-slate-600">Motif du refus <span class="font-normal text-slate-500">(facultatif, transmis à l’unité support)</span></label>
                        <textarea id="decline_reason" name="decline_reason" rows="2" maxlength="1000" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. : indisponibilité sur la période, effectif insuffisant…"></textarea>
                        <button type="submit" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Confirmer le refus</button>
                    </form>
                </details>
            </div>
        </div>
        <?php endif; ?>
    </header>

    <?php
    $progNext = is_array($cooperationProgress['next_action'] ?? null) ? $cooperationProgress['next_action'] : null;
    if ($progNext !== null && (string) ($progNext['href'] ?? '') !== ''):
        $next_steps_title = 'Prochaine action';
        $next_steps_intro = !empty($progNext['actor_is_viewer'])
            ? 'C’est à votre unité d’agir.'
            : 'En attente de : ' . (string) $progNext['actor'] . '.';
        $next_steps = [[
            'label' => (string) $progNext['label'],
            'description' => (string) $progNext['description'],
            'href' => (string) $progNext['href'],
            'accent' => (string) ($progNext['tone'] ?? 'emerald'),
        ]];
        echo '<div id="coop-next" class="-mt-10" data-coop-region>';
        require base_path('views/partials/ui/next_steps_block.php');
        echo '</div>';
    endif;
    ?>

    <section id="participants" data-coop-region class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Unités engagées</h2>
        <p class="mt-2 text-sm text-slate-600">Suivi des invitations, des réponses et des autorisations de partage de chaque unité.</p>

        <?php
        $consentByTenant = is_array($cooperationConsentByTenant ?? null) ? $cooperationConsentByTenant : [];
        $lastReminder = is_array($cooperationLastReminderByTenant ?? null) ? $cooperationLastReminderByTenant : [];
        $fmtDt = static function (?string $raw): string {
            $ts = $raw !== null && trim($raw) !== '' ? strtotime($raw) : false;

            return $ts !== false ? date('d/m/Y H:i', $ts) : '—';
        };
        $stateVariant = static fn (string $st): string => match ($st) {
            'active' => 'success',
            'invited' => 'warning',
            'declined' => 'danger',
            default => 'neutral',
        };
        ?>
        <?php if ($participants === []): ?>
        <p class="mt-6 text-sm text-slate-500">Aucune unité enregistrée pour le moment.</p>
        <?php else: ?>
        <div class="mt-6">
        <table class="coop-parts">
            <caption class="sr-only">Unités engagées et état de leur participation</caption>
            <thead>
                <tr>
                    <th scope="col">Unité</th>
                    <th scope="col">Rôle</th>
                    <th scope="col">État</th>
                    <th scope="col">Invitée le</th>
                    <th scope="col">Réponse le</th>
                    <th scope="col">Autorisation de partage</th>
                    <?php if ($pilotActions): ?><th scope="col">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($participants as $p): ?>
            <?php
                $pTid = (int) ($p['tenant_id'] ?? 0);
                $role = (string) ($p['role'] ?? '');
                $st = (string) ($p['status'] ?? '');
                $pName = (string) ($p['tenant_name'] ?? '');
                $consent = $consentByTenant[$pTid] ?? null;
                if ($st !== 'active' || !in_array($status, ['active', 'archived'], true)) {
                    // Le partage n’est demandé qu’après le lancement.
                    $consentLabel = '—';
                    $consentVariant = 'neutral';
                } elseif ($consent !== null && $consent['valid'] > 0) {
                    $consentLabel = $consent['valid_until'] !== null ? 'Valide jusqu’au ' . $fmtDt($consent['valid_until']) : 'Validée';
                    $consentVariant = 'success';
                } elseif ($consent !== null && $consent['expired'] > 0) {
                    $consentLabel = 'Expirée';
                    $consentVariant = 'warning';
                } else {
                    $consentLabel = 'Non faite';
                    $consentVariant = 'neutral';
                }
                $canRemoveThis = $pilotActions && $role !== 'lead' && $pTid !== $sessionTenantId && in_array($st, ['invited', 'active'], true);
                $remindAt = $lastReminder[$pTid] ?? null;
                $remindRecent = $remindAt !== null && strtotime($remindAt) !== false && (time() - strtotime($remindAt)) < 86400;
                $canPromote = $isLead && $canManage && !$isTerminal && $role === 'partner' && $st === 'active';
            ?>
            <tr>
                <td data-label="Unité"><span class="coop-parts__unit<?= in_array($st, ['declined', 'left'], true) ? ' line-through decoration-slate-400' : '' ?>"><?= htmlspecialchars($pName, ENT_QUOTES, 'UTF-8') ?></span><?= $pTid === $sessionTenantId ? ' <span class="coop-parts__muted">(vous)</span>' : '' ?></td>
                <td data-label="Rôle"><?= htmlspecialchars(CooperationDictionary::participantRoleLabel($role), ENT_QUOTES, 'UTF-8') ?></td>
                <td data-label="État"><?php $ui_badge_label = CooperationDictionary::participantStateLabel($st); $ui_badge_variant = $stateVariant($st); require base_path('views/partials/ui/badge.php'); ?></td>
                <td data-label="Invitée le" class="coop-parts__muted"><?= htmlspecialchars($role === 'lead' ? '—' : $fmtDt($p['invited_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td data-label="Réponse le" class="coop-parts__muted"><?= htmlspecialchars($role === 'lead' || $st === 'invited' ? '—' : $fmtDt($p['responded_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td data-label="Autorisation"><?php if ($consentLabel === '—'): ?><span class="coop-parts__muted">—</span><?php else: $ui_badge_label = $consentLabel; $ui_badge_variant = $consentVariant; require base_path('views/partials/ui/badge.php'); endif; ?></td>
                <?php if ($pilotActions): ?>
                <td data-label="Actions">
                    <div class="coop-parts__actions">
                        <?php if ($st === 'invited' && $role !== 'lead'): ?>
                        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/remind'), ENT_QUOTES, 'UTF-8') ?>" data-coop-ajax>
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="partner_tenant_id" value="<?= $pTid ?>">
                            <button type="submit" class="coop-parts__btn"<?= $remindRecent ? ' disabled title="Déjà relancée le ' . htmlspecialchars($fmtDt($remindAt), ENT_QUOTES, 'UTF-8') . ' (une relance par 24 h)"' : '' ?>>Relancer</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($canPromote): ?>
                        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/promote-co-lead'), ENT_QUOTES, 'UTF-8') ?>"
                              data-ui-confirm="1" data-ui-confirm-title="Désigner co-pilote ?"
                              data-ui-confirm-body="<?= htmlspecialchars($pName . ' pourra inviter des unités, lancer et conduire la coopération avec vous.', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="co_lead_tenant_id" value="<?= $pTid ?>">
                            <button type="submit" class="coop-parts__btn">Co-pilote</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($canRemoveThis): ?>
                        <details>
                            <summary class="coop-parts__btn coop-parts__btn--danger"><?= $st === 'invited' ? 'Retirer l’invitation' : 'Retirer' ?></summary>
                            <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/remove-partner'), ENT_QUOTES, 'UTF-8') ?>" data-coop-ajax class="coop-parts__pop space-y-2"
                                  data-ui-confirm="1"
                                  data-ui-confirm-title="<?= $st === 'invited' ? 'Retirer l’invitation ?' : 'Retirer cette unité ?' ?>"
                                  data-ui-confirm-body="<?= htmlspecialchars($st === 'invited'
                                      ? $pName . ' ne pourra plus répondre à cette invitation. Vous pourrez la réinviter plus tard.'
                                      : $pName . ' quitte la coopération : ses accès partagés au brief sont fermés et elle est prévenue. Vous pourrez la réinviter plus tard.', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="partner_tenant_id" value="<?= $pTid ?>">
                                <label class="block text-xs font-bold text-slate-600" for="remove_reason_<?= $pTid ?>">Motif <span class="font-normal text-slate-500">(facultatif, transmis à l’unité)</span></label>
                                <input id="remove_reason_<?= $pTid ?>" type="text" name="remove_reason" maxlength="1000" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                                <button type="submit" class="rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-800">Retirer</button>
                            </form>
                        </details>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <?php if ($pilotActions && !empty($invitationRule['allowed'])): ?>
        <?php $reinforcement = !empty($invitationRule['reinforcement']); ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/invite'), ENT_QUOTES, 'UTF-8') ?>" data-coop-ajax class="mt-8 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-6"
              <?php if ($reinforcement): ?>data-ui-confirm="1" data-ui-confirm-title="Inviter une unité en renfort ?" data-ui-confirm-body="La coopération est déjà en cours. L’unité invitée rejoindra l’espace commun après avoir accepté et validé son autorisation de partage."<?php endif; ?>>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($reinforcement): ?>
            <input type="hidden" name="confirm_reinforcement" value="1">
            <?php endif; ?>
            <div class="flex-1 min-w-[200px]">
                <label for="partner_tenant_id" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1"><?= $reinforcement ? 'Inviter une unité en renfort' : 'Inviter une unité partenaire' ?></label>
                <select id="partner_tenant_id" name="partner_tenant_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" required
                        data-coop-combobox data-source="remote" data-required="1"
                        data-endpoint="<?= htmlspecialchars(url('back-office/cooperation/api/tenants/search'), ENT_QUOTES, 'UTF-8') ?>"
                        data-mission-id="<?= (int) $sid ?>" data-multiple-name="partner_tenant_ids[]" data-fallback-name="partner_tenant_id"
                        data-placeholder="Rechercher une ou plusieurs unités…">
                    <option value="">— Choisir —</option>
                    <?php foreach ($partners as $t): ?>
                    <option value="<?= (int) ($t['id'] ?? 0) ?>"<?= empty($t['selectable']) ? ' disabled' : '' ?>><?= htmlspecialchars((string) ($t['name'] ?? '') . (!empty($t['state_label']) ? ' — ' . $t['state_label'] : ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">Plusieurs unités peuvent être invitées en une fois. Celles déjà invitées ou engagées ne sont pas sélectionnables ; une unité ayant refusé ou retirée peut être réinvitée.</p>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Envoyer les invitations</button>
        </form>
        <?php require base_path('views/back_office/cooperation/missions/_combobox_assets.php'); ?>
        <?php endif; ?>

        <?php if ($pilotActions && in_array($status, ['draft', 'pending'], true)): ?>
        <div id="lancement" class="scroll-mt-24 mt-6 border-t border-slate-100 pt-6">
            <?php if (!empty($launchReady['ok'])): ?>
            <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/activate'), ENT_QUOTES, 'UTF-8') ?>"
                  data-ui-confirm="1" data-ui-confirm-title="Lancer la coopération ?"
                  data-ui-confirm-body="<?= htmlspecialchars('La coopération démarre avec : ' . implode(', ', $launchReady['accepted']) . '. Un fil commun est ouvert sur le brief de l’unité support et chaque unité devra valider son autorisation de partage.' . ($launchReady['ignored'] !== [] ? ' Les unités ayant refusé ou retirées (' . implode(', ', $launchReady['ignored']) . ') ne seront pas engagées.' : ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Lancer la coopération</button>
            </form>
            <?php else: ?>
            <button type="button" disabled aria-describedby="coop-launch-why" class="cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-400">Lancer la coopération</button>
            <p id="coop-launch-why" class="mt-3 text-xs text-slate-600"><?= htmlspecialchars(\App\Services\Cooperation\CooperationTransitionRules::reasonLabel((string) ($launchReady['reason'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <ul class="mt-3 space-y-1 text-xs text-slate-600">
                <?php if ($launchReady['accepted'] !== []): ?><li>Ont accepté : <strong class="text-slate-800"><?= htmlspecialchars(implode(', ', $launchReady['accepted']), ENT_QUOTES, 'UTF-8') ?></strong></li><?php endif; ?>
                <?php if ($launchReady['pending'] !== []): ?><li>En attente de réponse : <strong class="text-amber-800"><?= htmlspecialchars(implode(', ', $launchReady['pending']), ENT_QUOTES, 'UTF-8') ?></strong></li><?php endif; ?>
                <?php if ($launchReady['ignored'] !== []): ?><li>Non engagées (refus ou retrait, ignorées au lancement) : <?= htmlspecialchars(implode(', ', $launchReady['ignored']), ENT_QUOTES, 'UTF-8') ?></li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($canPilot && $canManage): ?>
    <section id="conduite" data-coop-region class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
        <?php $isSuspended = !empty($cooperationProgress['suspended']); ?>
        <?php if (!$isTerminal && $status === 'active'): ?>
        <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl border <?= $isSuspended ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50' ?> px-4 py-3">
            <p class="text-sm <?= $isSuspended ? 'text-amber-950' : 'text-slate-700' ?>">
                <?= $isSuspended
                    ? '<strong>Coopération suspendue.</strong> L’espace commun est en lecture seule ; la conduite et les points de situation sont gelés.'
                    : 'Besoin de geler temporairement la coopération (incident, report) ? La suspension met l’espace commun en lecture seule.' ?>
            </p>
            <?php if ($isSuspended): ?>
            <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/resume'), ENT_QUOTES, 'UTF-8') ?>"
                  data-ui-confirm="1" data-ui-confirm-title="Reprendre la coopération ?" data-ui-confirm-body="L’espace commun redevient accessible en écriture et la conduite peut reprendre. Les unités engagées sont prévenues.">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="rounded-xl bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Reprendre</button>
            </form>
            <?php else: ?>
            <details class="relative">
                <summary class="cursor-pointer list-none rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Suspendre…</summary>
                <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/suspend'), ENT_QUOTES, 'UTF-8') ?>" class="coop-parts__pop space-y-2"
                      data-ui-confirm="1" data-ui-confirm-title="Suspendre la coopération ?" data-ui-confirm-body="L’espace commun passe en lecture seule et la conduite est gelée jusqu’à la reprise. Les unités engagées reçoivent votre motif.">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="suspend_motive" class="block text-xs font-bold text-slate-600">Motif (obligatoire, transmis aux unités)</label>
                    <input id="suspend_motive" type="text" name="suspend_motive" required minlength="3" maxlength="500" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                    <button type="submit" class="rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800">Suspendre</button>
                </form>
            </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div>
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Conduite de la coopération</h2>
            <p class="mt-2 text-sm text-slate-600">Seuls les éléments de l’étape en cours sont à compléter ; ceux des autres étapes restent consultables plus bas.</p>
        </div>
        <?php
        $conductFields = [
            'opord_text' => ['Ordre d’opération', 'Intentions, objectif, règles d’engagement, organisation…', 5, (string) ($m['opord_text'] ?? '')],
            'command_validation_notes' => ['Notes de validation du commandement', 'Conditions de lancement, restrictions, arbitrages…', 3, (string) ($m['command_validation_notes'] ?? '')],
            'linked_resources_text' => ['Ressources engagées', 'Véhicules, matériels, soutiens…', 3, $resourcesText],
            'simulated_losses_text' => ['Pertes simulées', 'Effectifs, matériels, conséquences…', 3, $lossesText],
            'aar_summary' => ['Bilan', 'Ce qui a fonctionné, écarts, recommandations…', 5, (string) ($m['aar_summary'] ?? '')],
            'lessons_learned_text' => ['Enseignements retenus', 'Points à conserver pour la prochaine coopération…', 3, $lessonsText],
            'corrective_actions_text' => ['Actions correctives', 'Qui fait quoi, échéance, état…', 4, $correctiveText],
        ];
        $stageDefs = [
            'opord_draft' => ['fields' => ['opord_text'], 'next' => 'command_validation', 'prereq' => 'opord'],
            'command_validation' => ['fields' => ['opord_text', 'command_validation_notes'], 'next' => 'execution', 'prereq' => 'launched'],
            'execution' => ['fields' => ['linked_resources_text', 'simulated_losses_text', 'aar_summary'], 'next' => 'closed_aar', 'prereq' => 'aar'],
            'closed_aar' => ['fields' => ['aar_summary', 'lessons_learned_text'], 'next' => 'corrective_actions', 'prereq' => ''],
            'corrective_actions' => ['fields' => ['corrective_actions_text', 'lessons_learned_text'], 'next' => null, 'prereq' => ''],
        ];
        $curDef = $stageDefs[$operationalStage] ?? $stageDefs['opord_draft'];
        $nextStage = $curDef['next'];
        $nextLabel = $nextStage !== null ? preg_replace('/^\d\)\s*/u', '', (string) ($operationalChoices[$nextStage] ?? $nextStage)) : '';
        $curLabel = preg_replace('/^\d\)\s*/u', '', (string) ($operationalChoices[$operationalStage] ?? ''));
        $conductLocked = $isTerminal || !empty($cooperationProgress['suspended']);
        $prereqMessages = [
            'opord' => 'Rédigez l’ordre d’opération avant de demander la validation du commandement.',
            'launched' => 'La coopération doit être lancée (toutes les unités ont répondu) pour passer en exécution.',
            'aar' => 'Rédigez le bilan avant de passer à la clôture.',
        ];
        $prereqKey = (string) $curDef['prereq'];
        $prereqServerBlock = $prereqKey === 'launched' && $status !== 'active';
        $otherFields = array_values(array_diff(array_keys($conductFields), $curDef['fields']));
        ?>
        <?php if ($operationalChoices !== []): ?>
        <form id="coop-conduct-form" method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/operational-stage'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-5" data-coop-conduct data-prereq="<?= htmlspecialchars($prereqKey, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <p class="text-sm text-slate-700">Étape de conduite en cours : <strong class="text-slate-900"><?= htmlspecialchars((string) $curLabel, ENT_QUOTES, 'UTF-8') ?></strong></p>
            <?php foreach ($curDef['fields'] as $fk): ?>
            <?php [$flabel, $fph, $frows, $fval] = $conductFields[$fk]; ?>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="cf_<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8') ?></label>
                <textarea id="cf_<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?>" rows="<?= (int) $frows ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="<?= htmlspecialchars($fph, ENT_QUOTES, 'UTF-8') ?>" data-conduct-field="<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?>"<?= $conductLocked ? ' disabled' : '' ?>><?= htmlspecialchars($fval, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <?php endforeach; ?>

            <?php if (!$conductLocked): ?>
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" name="operational_stage" value="<?= htmlspecialchars($operationalStage, ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Enregistrer</button>
                <?php if ($nextStage !== null): ?>
                <button type="submit" name="operational_stage" value="<?= htmlspecialchars($nextStage, ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                        data-coop-advance data-next-label="<?= htmlspecialchars((string) $nextLabel, ENT_QUOTES, 'UTF-8') ?>"
                        aria-describedby="coop-advance-prereq"<?= $prereqServerBlock ? ' disabled' : '' ?>>Passer à : <?= htmlspecialchars((string) $nextLabel, ENT_QUOTES, 'UTF-8') ?></button>
                <?php endif; ?>
            </div>
            <?php if ($nextStage !== null && $prereqKey !== ''): ?>
            <p id="coop-advance-prereq" class="fr-message <?= $prereqServerBlock ? 'fr-message--error' : '' ?> text-xs text-slate-600" data-prereq-message><?= htmlspecialchars($prereqMessages[$prereqKey] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php else: ?>
            <p class="text-sm text-amber-900">La conduite est gelée (coopération <?= $isTerminal ? 'clôturée' : 'suspendue' ?>).</p>
            <?php endif; ?>
        </form>

        <?php if ($nextStage !== null && !$conductLocked): ?>
        <dialog id="coop-advance-dialog" class="w-[min(100vw-2rem,28rem)] rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-900/40" aria-labelledby="coop-advance-title">
            <div class="border-b border-slate-100 px-5 py-4">
                <p id="coop-advance-title" class="text-sm font-bold">Passer à : <?= htmlspecialchars((string) $nextLabel, ENT_QUOTES, 'UTF-8') ?> ?</p>
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                    <li>Étape actuelle : <?= htmlspecialchars((string) $curLabel, ENT_QUOTES, 'UTF-8') ?> (les champs saisis sont enregistrés).</li>
                    <?php if ($prereqKey !== ''): ?><li>Prérequis : <?= htmlspecialchars($prereqMessages[$prereqKey] ?? '', ENT_QUOTES, 'UTF-8') ?></li><?php endif; ?>
                    <li>Les unités engagées sont prévenues du changement d’étape. Le retour à une étape précédente n’est pas possible.</li>
                </ul>
            </div>
            <div class="flex justify-end gap-2 px-4 py-3">
                <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-coop-advance-cancel>Annuler</button>
                <button type="submit" form="coop-conduct-form" name="operational_stage" value="<?= htmlspecialchars($nextStage, ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Confirmer le passage</button>
            </div>
        </dialog>
        <?php endif; ?>

        <?php
        $filledOthers = array_filter($otherFields, static fn (string $k): bool => trim((string) $conductFields[$k][3]) !== '');
        ?>
        <?php if ($filledOthers !== []): ?>
        <details class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3">
            <summary class="cursor-pointer text-sm font-semibold text-slate-800">Éléments des autres étapes (<?= count($filledOthers) ?>)</summary>
            <dl class="mt-3 space-y-4">
                <?php foreach ($filledOthers as $fk): ?>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= htmlspecialchars($conductFields[$fk][0], ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-800"><?= htmlspecialchars((string) $conductFields[$fk][3], ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
        </details>
        <?php endif; ?>
        <script defer src="<?= htmlspecialchars(asset_url('assets/js/cooperation/conduct.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php endif; ?>

        <?php if ($operationalStage === 'execution' && !$conductLocked): ?>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/sitrep'), ENT_QUOTES, 'UTF-8') ?>" data-coop-ajax class="border-t border-slate-100 pt-8 grid gap-4">
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
    <section id="coop-roles" data-coop-region class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
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
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/assign-member'), ENT_QUOTES, 'UTF-8') ?>" data-coop-ajax class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-6">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1" for="member_user_id">Membre</label>
                <select id="member_user_id" name="member_user_id" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm min-w-[200px]" required
                        data-coop-combobox data-source="remote"
                        data-endpoint="<?= htmlspecialchars(url('back-office/cooperation/api/members/search'), ENT_QUOTES, 'UTF-8') ?>"
                        data-mission-id="<?= (int) $sid ?>" data-placeholder="Rechercher un membre de votre unité…">
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
        <?php require base_path('views/back_office/cooperation/missions/_combobox_assets.php'); ?>
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

    <?php /* Co-pilotage : action « Co-pilote » du tableau des unités engagées. */ ?>


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
