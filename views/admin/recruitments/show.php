<?php
declare(strict_types=1);
/** @var array<string,mixed> $enlistment */
/** @var list<array<string,mixed>> $enlistmentCannedMessages */
$e = $enlistment ?? [];
$enlistmentCannedMessages = $enlistmentCannedMessages ?? [];
$rpSnap = is_array($e['recruitment_rp_json'] ?? null) ? $e['recruitment_rp_json'] : null;
$id = (int) ($e['id'] ?? 0);
$statusRaw = (string) ($e['status'] ?? '');
$statusLabels = [
    'submitted' => 'À traiter',
    'reviewed' => 'Acceptée',
    'rejected' => 'Refusée',
    'blocked' => 'Non admis',
];
$statusLabel = $statusLabels[$statusRaw] ?? $statusRaw;
if ($statusRaw === 'rejected' && !empty($e['auto_rejected'])) {
    $statusLabel = 'Refusée automatiquement';
}
// Dossier clos = décision rendue (acceptée, refusée ou non admis) : plus d’instruction à mener, UI allégée.
$dossierClosedStatuses = ['reviewed', 'rejected', 'blocked'];
$isDossierClos = in_array($statusRaw, $dossierClosedStatuses, true);
$portalJourneyStepsForNotes = is_array($portalJourneyStepsForNotes ?? null) ? $portalJourneyStepsForNotes : [];
$instructionFollowup = '';
foreach ($portalJourneyStepsForNotes as $stFollow) {
    if (!is_array($stFollow)) {
        continue;
    }
    $pk = trim((string) ($stFollow['pause_kind'] ?? ''));
    if ($pk === 'pending' || $pk === 'interview') {
        $instructionFollowup = $pk;
        break;
    }
}
if ($statusRaw === 'submitted' && $instructionFollowup === 'pending') {
    $statusLabel = 'À traiter · Mis en attente';
} elseif ($statusRaw === 'submitted' && $instructionFollowup === 'interview') {
    $statusLabel = 'À traiter · Entretien proposé';
}
$reviewedById = (int) ($e['reviewed_by'] ?? 0);
$assigneeLabel = trim((string) ($assigneeDisplayName ?? ''));
if ($assigneeLabel === '') {
    $assigneeLabel = 'Pas encore de référent indiqué';
}
$recruiterPicksDisplay = is_array($recruiterPicksDisplay ?? null) ? $recruiterPicksDisplay : [];
$userHasRecruiterPick = !empty($userHasRecruiterPick);
$currentStaffUserId = (int) ($currentStaffUserId ?? 0);
$enlistmentAgeDays = isset($enlistmentAgeDays) ? (int) $enlistmentAgeDays : null;
$retroWindowEligible = !empty($retroWindowEligible);
$retroNotApplicable = !empty($retroNotApplicable);
$enlistmentEngagementTablesReady = !empty($enlistmentEngagementTablesReady);
$recruiterPicksTableReady = !empty($recruiterPicksTableReady);
$staffRetroFeedback = is_array($staffRetroFeedback ?? null) ? $staffRetroFeedback : null;
$candidateRetroFeedback = is_array($candidateRetroFeedback ?? null) ? $candidateRetroFeedback : null;
$enlistmentAnalyticsRecent = is_array($enlistmentAnalyticsRecent ?? null) ? $enlistmentAnalyticsRecent : [];
$analyticsEventLabels = [
    'enlistment_backoffice_view' => 'Consultation de la fiche',
    'enlistment_recruiter_pick' => 'Volontariat recruteur',
    'enlistment_staff_retro_submit' => 'Bilan équipe enregistré',
    'enlistment_candidate_retro_submit' => 'Retour candidat enregistré',
];
$analyticsRecentInitialRows = 10;
$analyticsMergedRows = [];
foreach ($enlistmentAnalyticsRecent as $ev) {
    $nm = (string) ($ev['name'] ?? '');
    $labEv = $analyticsEventLabels[$nm] ?? 'Action enregistrée';
    $ca = trim((string) ($ev['created_at'] ?? ''));
    if ($ca === '') {
        continue;
    }
    $ts = strtotime($ca) ?: time();
    $minuteSlot = date('Y-m-d H:i', $ts);
    $lastIdx = count($analyticsMergedRows) - 1;
    if ($lastIdx >= 0
        && $analyticsMergedRows[$lastIdx]['minute_slot'] === $minuteSlot
        && $analyticsMergedRows[$lastIdx]['label'] === $labEv) {
        $analyticsMergedRows[$lastIdx]['count']++;
    } else {
        $analyticsMergedRows[] = [
            'minute_slot' => $minuteSlot,
            'day_key' => date('Y-m-d', $ts),
            'time' => date('H:i', $ts),
            'label' => $labEv,
            'count' => 1,
        ];
    }
}
$analyticsMergedVisible = array_slice($analyticsMergedRows, 0, $analyticsRecentInitialRows);
$analyticsMergedMore = array_slice($analyticsMergedRows, $analyticsRecentInitialRows);
$analyticsFrenchDayHeader = static function (string $dayKey): string {
    $ts = strtotime($dayKey . ' 12:00:00') ?: time();
    $mois = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];
    $j = (int) date('j', $ts);
    $m = (int) date('n', $ts);
    $y = (int) date('Y', $ts);

    return $j . ' ' . ($mois[$m] ?? date('F', $ts)) . ' ' . $y;
};
$analyticsGroupByDayOrder = static function (array $mergedRows): array {
    $dayOrder = [];
    $byDay = [];
    foreach ($mergedRows as $r) {
        $dk = (string) ($r['day_key'] ?? '');
        if ($dk === '') {
            continue;
        }
        if (!isset($byDay[$dk])) {
            $byDay[$dk] = [];
            $dayOrder[] = $dk;
        }
        $byDay[$dk][] = $r;
    }

    return [$dayOrder, $byDay];
};
$membershipRepairHint = $membershipRepairHint ?? null;
$enlistmentSlaHours = max(1, (int) ($enlistmentSlaHours ?? \App\Services\Recruitment\TenantRecruitmentSettings::defaultEnlistmentSlaHours()));
$submissionAgeHours = \App\Services\Recruitment\TenantRecruitmentSettings::hoursElapsedSince(
    trim((string) ($e['created_at'] ?? '')) !== '' ? (string) $e['created_at'] : null
);
$slaTargetHuman = \App\Services\Recruitment\TenantRecruitmentSettings::formatSlaHoursLabel($enlistmentSlaHours);
$submissionAgeHuman = null;
if ($submissionAgeHours !== null) {
    $submissionAgeHuman = $submissionAgeHours < 1
        ? 'moins d’une heure'
        : \App\Services\Recruitment\TenantRecruitmentSettings::formatSlaHoursLabel($submissionAgeHours);
}
$submissionSlaBreached = ((string) ($e['status'] ?? '')) === 'submitted'
    && $submissionAgeHours !== null
    && $submissionAgeHours > $enlistmentSlaHours;

$enlistmentTimeline = is_array($enlistmentTimeline ?? null) ? $enlistmentTimeline : [];
$timelineActorLabels = is_array($enlistmentTimelineActorLabels ?? null) ? $enlistmentTimelineActorLabels : [];
$timelineTableMissing = !empty($enlistmentTimelineTableMissing);
$timelineStepLabels = [
    'reception' => 'Réception du dossier',
    'portal_moderation_filter' => 'Contrôle automatique (portail)',
    'portal_moderation_incident' => 'Modération et suites d’incident',
    'instruction' => 'Instruction et arbitrage',
    'suivi' => 'Suivi, pièces et messages',
    'decision' => 'Décision',
    'adhesion' => 'Rattachement au compte membre',
    'general' => 'Commentaire général (aucune étape précise)',
    'portal' => 'Portail candidat (technique, pièces, paramètres)',
    'communication' => 'Échanges avec le candidat (fil de messagerie)',
];

$portalJourneyStepsForNotes = is_array($portalJourneyStepsForNotes ?? null) ? $portalJourneyStepsForNotes : [];
$timelineNoteSuggestedStep = trim((string) ($timelineNoteSuggestedStep ?? 'general'));
$allowedNoteSteps = ['reception', 'portal_moderation_filter', 'portal_moderation_incident', 'instruction', 'suivi', 'decision', 'adhesion', 'portal', 'communication', 'general'];
if ($timelineNoteSuggestedStep === '' || !in_array($timelineNoteSuggestedStep, $allowedNoteSteps, true)) {
    $timelineNoteSuggestedStep = 'general';
}

$linkedRo = is_array($linkedRecruitmentOpening ?? null) ? $linkedRecruitmentOpening : null;
$submitterId = (int) ($e['submitter_user_id'] ?? 0);
$isInternalOpeningApplication = $submitterId > 0 && $linkedRo !== null;
$candidatePortalAttachments = is_array($candidatePortalAttachments ?? null) ? $candidatePortalAttachments : [];
$portalAttById = [];
foreach ($candidatePortalAttachments as $pa) {
    $paid = (int) ($pa['id'] ?? 0);
    if ($paid > 0) {
        $portalAttById[$paid] = $pa;
    }
}
$candidatePortalUploadsReady = !empty($candidatePortalUploadsReady);
$portalStatusDisplayReady = !empty($portalStatusDisplayReady);
$portalAllowFiles = !empty($e['candidate_portal_allow_files']);
$portalAllowAudio = !empty($e['candidate_portal_allow_audio']);
$portalStatusModeForm = ((string) ($e['candidate_portal_status_mode'] ?? 'steps')) === 'manual' ? 'manual' : 'steps';
$portalStatusManualText = trim((string) ($e['candidate_portal_status_manual_text'] ?? ''));
$portalStatusBandRaw = strtolower(trim((string) ($e['candidate_portal_status_manual_band'] ?? 'amber')));
$portalStatusManualBandForm = in_array($portalStatusBandRaw, ['amber', 'emerald', 'rose', 'slate', 'sky'], true) ? $portalStatusBandRaw : 'amber';
$candidatePortalSuiviUrl = isset($candidatePortalSuiviUrl) && is_string($candidatePortalSuiviUrl) && $candidatePortalSuiviUrl !== '' ? $candidatePortalSuiviUrl : null;
$candidatePortalSuiviExpiresFmt = isset($candidatePortalSuiviExpiresFmt) && is_string($candidatePortalSuiviExpiresFmt) && $candidatePortalSuiviExpiresFmt !== '' ? $candidatePortalSuiviExpiresFmt : null;
$dossierPortalEmailBlocked = !empty($dossierPortalEmailBlocked);

$sharedFields = [];
$sfRaw = $e['shared_fields'] ?? null;
if (is_string($sfRaw) && $sfRaw !== '') {
    $decodedSf = json_decode($sfRaw, true);
    $sharedFields = is_array($decodedSf) ? $decodedSf : [];
} elseif (is_array($sfRaw)) {
    $sharedFields = $sfRaw;
}

$transmissionLines = [];
if ($sharedFields !== []) {
    if (!empty($sharedFields['share_name'])) {
        $transmissionLines[] = 'Nom issu du profil portail';
    }
    if (!empty($sharedFields['share_email'])) {
        $transmissionLines[] = 'Adresse e-mail de connexion';
    }
    if (!empty($sharedFields['share_callsign'])) {
        $transmissionLines[] = 'Indicatif enregistré sur le profil';
    }
    $rpS = $sharedFields['rp_shares'] ?? null;
    if (is_array($rpS)) {
        $rpShareLabels = [
            'identity' => 'Identité personnage (prénom, nom, naissance, nationalité)',
            'character_name' => 'Nom de scène (optionnel)',
            'bio' => 'Biographie',
            'cv' => 'Parcours (CV)',
            'image_url' => 'Portrait (fichier)',
            'image_external_url' => 'Lien vers un portrait',
            'admin_notes' => 'Notes du profil',
            'availability' => 'Synthèse des disponibilités',
        ];
        foreach ($rpShareLabels as $rk => $rlab) {
            if (!empty($rpS[$rk])) {
                $transmissionLines[] = $rlab;
            }
        }
    }
    if (!empty($sharedFields['include_milsim_from_preset'])) {
        $transmissionLines[] = 'Réponses techniques du modèle (matériel, créneaux, motivation enregistrée dans le profil, etc.)';
    }
    $fm = $sharedFields['form_mode'] ?? null;
    if ($fm === 'compact') {
        $transmissionLines[] = 'Parcours de dépôt : formulaire court (avis ciblé)';
    } elseif ($fm === 'full') {
        $transmissionLines[] = 'Parcours de dépôt : questionnaire complet';
    }
}

$statusBand = match ($statusRaw) {
    'submitted' => $instructionFollowup === 'pending'
        ? 'from-sky-500 to-sky-600'
        : ($instructionFollowup === 'interview' ? 'from-violet-500 to-violet-600' : 'from-amber-500 to-amber-600'),
    'reviewed' => 'from-emerald-600 to-emerald-700',
    'rejected' => 'from-rose-500 to-rose-600',
    'blocked' => 'from-slate-600 to-slate-800',
    default => 'from-stone-400 to-stone-600',
};

$recapMeta = match ($statusRaw) {
    'submitted' => $instructionFollowup === 'pending'
        ? [
            'step' => 'Étape 2 sur 3',
            'title' => 'Dossier mis en attente',
            'bar' => 'w-2/3',
            'barColor' => 'bg-sky-400',
            'hint' => 'Le traitement est temporairement suspendu. Le candidat a été informé ; vous pouvez reprendre l’instruction quand vous voulez.',
        ]
        : ($instructionFollowup === 'interview'
            ? [
                'step' => 'Étape 2 sur 3',
                'title' => 'Entretien proposé',
                'bar' => 'w-2/3',
                'barColor' => 'bg-violet-400',
                'hint' => 'Un entretien a été proposé au candidat. La décision finale reste à rendre après l’échange.',
            ]
            : [
                'step' => 'Étape 2 sur 3',
                'title' => 'En cours de traitement',
                'bar' => 'w-2/3',
                'barColor' => 'bg-amber-400',
                'hint' => 'Étape suivante : décision finale et notification candidat.',
            ]),
    'reviewed' => [
        'step' => 'Étape 3 sur 3',
        'title' => 'Candidature acceptée',
        'bar' => 'w-full',
        'barColor' => 'bg-emerald-500',
        'hint' => 'Décision positive enregistrée. Vérifiez le rattachement au compte membre si besoin.',
    ],
    'rejected' => [
        'step' => 'Dossier clos',
        'title' => 'Candidature refusée',
        'bar' => 'w-full',
        'barColor' => 'bg-rose-500',
        'hint' => 'Décision négative enregistrée. Le journal conserve la trace des échanges.',
    ],
    'blocked' => [
        'step' => 'Dossier clos',
        'title' => 'Non admis',
        'bar' => 'w-full',
        'barColor' => 'bg-stone-500',
        'hint' => 'Candidature écartée sans suite d’adhésion.',
    ],
    default => [
        'step' => 'Étape 1 sur 3',
        'title' => 'Réception du dossier',
        'bar' => 'w-1/3',
        'barColor' => 'bg-stone-400',
        'hint' => 'Statut à préciser côté instruction.',
    ],
};

$showRetroBlockNav = $retroWindowEligible || $retroNotApplicable || $staffRetroFeedback !== null || $candidateRetroFeedback !== null;
$dossierNavItems = [
    ['id' => 'recap-dossier', 'label' => 'Récapitulatif', 'num' => '01', 'show' => true],
    ['id' => 'coordination-dossier', 'label' => 'Coordination', 'num' => '02', 'show' => !$isDossierClos],
    ['id' => 'bilan-recrutement', 'label' => 'Bilan 30 jours', 'num' => '03', 'show' => $showRetroBlockNav && !$isDossierClos],
    ['id' => 'activite-dossier', 'label' => 'Activité récente', 'num' => '04', 'show' => $analyticsMergedRows !== [] && !$isDossierClos],
    ['id' => 'couverture-dossier', 'label' => 'En-tête candidat', 'num' => '05', 'show' => true],
    ['id' => 'portail-candidat', 'label' => 'Portail candidat', 'num' => '06', 'show' => !$isDossierClos],
    ['id' => 'identite-reception', 'label' => 'Identité & réception', 'num' => '07', 'show' => true],
    // Acceptée = dossier clos pour l’instruction, mais le rattachement membre reste actionnable.
    ['id' => 'rattachement-membre', 'label' => 'Rattachement', 'num' => '08', 'show' => $statusRaw === 'reviewed'],
    ['id' => 'instruction-dossier', 'label' => 'Décision', 'num' => '09', 'show' => $statusRaw === 'submitted' && !$isDossierClos],
    ['id' => 'journal-dossier', 'label' => 'Journal', 'num' => '10', 'show' => !$isDossierClos],
];
$bureauRecrutementCourseUrl = url('formations/parcours-bureau-recrutement');
?>
<div class="recruitment-bureau space-y-6 max-w-[94rem] mx-auto w-full">

        <nav class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white px-4 py-3 shadow-sm sm:px-5" aria-label="Fil d’Ariane">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-stone-600">
                <a href="<?= htmlspecialchars(url('back-office/recruitments')) ?>" class="rounded-lg border border-transparent px-2 py-1.5 text-stone-700 transition hover:border-stone-200 hover:bg-stone-50">Dossiers de candidature</a>
                <span class="text-stone-300" aria-hidden="true">/</span>
                <span class="rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1.5 font-bold text-stone-900">Dossier n°<?= $id ?></span>
                <a href="<?= htmlspecialchars(url('back-office/recruitments/settings')) ?>" class="ml-auto inline-flex min-h-[2.25rem] items-center rounded-xl border border-slate-300 bg-slate-100 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-900 shadow-sm transition hover:bg-slate-200">Délais d’alerte</a>
            </div>
        </nav>

        <?php
        $dossierSideNavMode = 'mobile';
        require base_path('views/admin/recruitments/partials/dossier_side_nav.php');
        ?>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem] xl:grid-cols-[minmax(0,1fr)_17rem]">
            <div class="space-y-6 min-w-0 order-1">
                <?php if ($isDossierClos): ?>
                <section class="scroll-mt-28 overflow-hidden rounded-2xl border border-stone-300/80 bg-gradient-to-br <?= htmlspecialchars($statusBand, ENT_QUOTES, 'UTF-8') ?> px-6 py-5 text-white shadow-sm sm:px-8">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-[10px] font-black uppercase tracking-[0.22em] text-white">Dossier clos</span>
                        <span class="text-sm font-bold text-white"><?= htmlspecialchars($statusLabel ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-white/80">
                        <?php if ($statusRaw === 'reviewed'): ?>
                            La candidature est acceptée. Cette fiche est allégée : identité, décision, suivi candidat, et le rattachement au compte membre si besoin.
                        <?php else: ?>
                            La décision est rendue et a été transmise au candidat. Pour l’alléger, cette fiche n’affiche plus que l’essentiel : identité, dates, décision et lien de suivi.
                        <?php endif; ?>
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-white/70">Dossier reçu le</p>
                            <p class="mt-1 text-sm font-semibold text-white"><?= !empty($e['created_at']) ? htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $e['created_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></p>
                        </div>
                        <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-white/70">Décidé le</p>
                            <p class="mt-1 text-sm font-semibold text-white"><?= !empty($e['reviewed_at']) ? htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $e['reviewed_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></p>
                        </div>
                        <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-white/70">Décidé par</p>
                            <p class="mt-1 text-sm font-semibold text-white"><?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <?php if (!empty($e['reviewer_comment'])): ?>
                        <div class="mt-4 rounded-xl border border-white/20 bg-white/10 px-4 py-3">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-white/70">Message transmis au candidat</p>
                            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-white/90"><?= htmlspecialchars(str_replace(['[MISE EN ATTENTE]', '[DEMANDE ENTRETIEN]'], ['Mise en attente', 'Demande d’entretien'], (string) $e['reviewer_comment']), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($candidatePortalSuiviUrl !== null): ?>
                        <a href="<?= htmlspecialchars($candidatePortalSuiviUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex min-h-[2.5rem] items-center justify-center rounded-xl border-2 border-white/40 bg-white/10 px-4 text-xs font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-white/20">Voir le suivi candidat (portail)</a>
                    <?php endif; ?>
                    <?php if ($statusRaw === 'reviewed'): ?>
                        <a href="#rattachement-membre" class="mt-3 inline-flex min-h-[2.5rem] items-center justify-center rounded-xl border-2 border-white/50 bg-white px-4 text-xs font-black uppercase tracking-wide text-emerald-900 shadow-sm transition hover:bg-emerald-50">
                            <?= $submitterId > 0 ? 'Vérifier le rattachement membre' : 'Rattacher la personne' ?>
                        </a>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <section id="recap-dossier" class="scroll-mt-28 overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                    <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500"><?= htmlspecialchars($recapMeta['step'], ENT_QUOTES, 'UTF-8') ?></p>
                        <h2 class="mt-1 text-xl font-black tracking-tight text-stone-900"><?= htmlspecialchars($recapMeta['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-stone-200">
                            <div class="h-full <?= htmlspecialchars($recapMeta['bar'], ENT_QUOTES, 'UTF-8') ?> rounded-full <?= htmlspecialchars($recapMeta['barColor'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-stone-600"><?= htmlspecialchars($recapMeta['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="grid gap-4 px-6 py-4 text-sm text-stone-800 md:grid-cols-2 xl:grid-cols-4">
                        <div><p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">N° de dossier</p><p class="mt-1 font-semibold text-stone-900">#<?= $id ?></p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Statut</p><p class="mt-1 font-semibold text-stone-900"><?= htmlspecialchars($statusLabel ?: '—', ENT_QUOTES, 'UTF-8') ?></p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Attribué à</p><p class="mt-1 font-semibold text-stone-900"><?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Nature</p><p class="mt-1 font-semibold text-stone-900"><?= $isInternalOpeningApplication ? 'Mobilité interne' : 'Candidature externe' ?></p></div>
                    </div>
                </section>

                <?php if (!$isDossierClos): ?>
                <section id="coordination-dossier" class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
                    <div class="border-b border-slate-100 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-6 py-5 text-white sm:px-8">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-300/90">Coordination</p>
                        <h2 class="mt-1 text-xl font-black tracking-tight text-white">Qui suit ce dossier<span class="text-emerald-400">?</span></h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/65">
                            Le référent pilote l’instruction. Les volontaires indiquent qui souhaite aider, sans attendre qu’une personne soit déjà désignée.
                        </p>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:gap-5 sm:p-6">
                        <?php
                        $hasAssignee = $assigneeLabel !== 'Pas encore de référent indiqué';
                        $assigneeInitials = '—';
                        if ($hasAssignee) {
                            $parts = preg_split('/\s+/', $assigneeLabel) ?: [];
                            $letters = '';
                            foreach (array_slice($parts, 0, 2) as $p) {
                                $letters .= mb_strtoupper(mb_substr((string) $p, 0, 1, 'UTF-8'), 'UTF-8');
                            }
                            $assigneeInitials = $letters !== '' ? $letters : mb_strtoupper(mb_substr($assigneeLabel, 0, 2, 'UTF-8'), 'UTF-8');
                        }
                        ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Référent</p>
                                <?php if ($hasAssignee): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800">Actif</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">À désigner</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4 flex items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full <?= $hasAssignee ? 'bg-slate-900 text-emerald-300' : 'bg-slate-200 text-slate-500' ?> text-sm font-black" aria-hidden="true"><?= htmlspecialchars($assigneeInitials, ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="min-w-0">
                                    <p class="truncate text-base font-bold text-slate-900"><?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-0.5 text-xs text-slate-500"><?= $hasAssignee ? 'Mène l’instruction du dossier' : 'Personne n’est encore en charge' ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Volontaires</p>
                                <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-black tabular-nums text-slate-700"><?= count($recruiterPicksDisplay) ?></span>
                            </div>
                            <?php if ($recruiterPicksDisplay === []): ?>
                                <p class="mt-4 text-sm text-slate-500">Aucun volontaire pour l’instant.</p>
                            <?php else: ?>
                                <ul class="mt-3 max-h-40 space-y-2 overflow-y-auto pr-1">
                                    <?php foreach ($recruiterPicksDisplay as $pk): ?>
                                        <?php
                                        $pkLabel = trim((string) ($pk['label'] ?? ''));
                                        $pkc = trim((string) ($pk['created_at'] ?? ''));
                                        $pkcFmt = $pkc !== '' ? date('d/m/Y · H:i', strtotime($pkc) ?: time()) : '—';
                                        $pkParts = preg_split('/\s+/', $pkLabel) ?: [];
                                        $pkInit = '';
                                        foreach (array_slice($pkParts, 0, 2) as $p) {
                                            $pkInit .= mb_strtoupper(mb_substr((string) $p, 0, 1, 'UTF-8'), 'UTF-8');
                                        }
                                        if ($pkInit === '' && $pkLabel !== '') {
                                            $pkInit = mb_strtoupper(mb_substr($pkLabel, 0, 2, 'UTF-8'), 'UTF-8');
                                        }
                                        ?>
                                        <li class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2.5">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-[11px] font-black text-emerald-900" aria-hidden="true"><?= htmlspecialchars($pkInit !== '' ? $pkInit : '?', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900"><?= htmlspecialchars($pkLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="shrink-0 text-[11px] tabular-nums text-slate-400"><?= htmlspecialchars($pkcFmt, ENT_QUOTES, 'UTF-8') ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($statusRaw === 'submitted' && $currentStaffUserId > 0): ?>
                        <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 sm:px-6">
                            <?php if (!$recruiterPicksTableReady): ?>
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Le volontariat sera disponible après la prochaine mise à jour technique côté hébergement.</p>
                            <?php elseif ($userHasRecruiterPick): ?>
                                <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white" aria-hidden="true">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                    <div>
                                        <p class="text-sm font-bold text-emerald-950">Intérêt déjà enregistré</p>
                                        <p class="mt-0.5 text-xs text-emerald-900/75">Vous figurez parmi les volontaires de ce dossier.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/recruteur-volontariat'), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <?= \App\Core\Csrf::field() ?>
                                    <p class="text-sm text-slate-600">Vous pouvez indiquer que vous êtes disponible pour suivre ce dossier.</p>
                                    <button type="submit" class="inline-flex min-h-[2.75rem] shrink-0 items-center justify-center rounded-xl bg-emerald-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                        Me porter volontaire
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <?php
                $showRetroBlock = $retroWindowEligible || $retroNotApplicable || $staffRetroFeedback !== null || $candidateRetroFeedback !== null;
                $retroLabels = [5 => 'Très satisfaisant', 4 => 'Bon', 3 => 'Correct', 2 => 'En-dessous des attentes', 1 => 'À améliorer'];
                $retroNotApplicableMessage = $statusRaw === 'blocked'
                    ? 'Pas de bilan pour une candidature non admise.'
                    : 'Pas de bilan pour une candidature refusée.';
                ?>
                <?php if ($showRetroBlock && !$isDossierClos): ?>
                <section id="bilan-recrutement" class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
                    <div class="border-b border-slate-100 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-6 py-5 text-white sm:px-8">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-300/90">Après 30 jours</p>
                            <?php if ($retroNotApplicable && !$staffRetroFeedback && !$candidateRetroFeedback): ?>
                                <span class="rounded-full bg-stone-400/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-stone-200">Non concerné</span>
                            <?php elseif ($retroWindowEligible && !$staffRetroFeedback): ?>
                                <span class="rounded-full bg-amber-400/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-200">Bilan attendu</span>
                            <?php elseif ($staffRetroFeedback): ?>
                                <span class="rounded-full bg-emerald-400/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-200">Bilan enregistré</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="mt-1 text-xl font-black tracking-tight text-white">Bilan du recrutement<span class="text-emerald-400">.</span></h2>
                        <?php if ($retroNotApplicable && !$staffRetroFeedback && !$candidateRetroFeedback): ?>
                            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/65"><?= htmlspecialchars($retroNotApplicableMessage, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/65">Après un mois, l’équipe et le candidat peuvent laisser une courte note pour améliorer l’accueil et le suivi.</p>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-6 p-5 sm:p-6">
                        <?php if ($retroNotApplicable && !$staffRetroFeedback && !$candidateRetroFeedback): ?>
                        <p class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-800"><?= htmlspecialchars($retroNotApplicableMessage, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                        <?php if ($candidateRetroFeedback): ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Retour candidat</p>
                            <p class="mt-2 text-sm font-bold text-slate-900">
                                Note <?= (int) ($candidateRetroFeedback['rating'] ?? 0) ?> / 5
                                <?php $cr = (int) ($candidateRetroFeedback['rating'] ?? 0); if (isset($retroLabels[$cr])): ?>
                                    <span class="font-semibold text-slate-500">— <?= htmlspecialchars($retroLabels[$cr], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </p>
                            <div class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700"><?= htmlspecialchars((string) ($candidateRetroFeedback['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php
                            $cup = trim((string) ($candidateRetroFeedback['updated_at'] ?? ''));
                            $cad = trim((string) ($candidateRetroFeedback['created_at'] ?? ''));
                            $cd = $cup !== '' ? $cup : $cad;
                            ?>
                            <?php if ($cd !== ''): ?>
                                <p class="mt-3 text-xs text-slate-500">Enregistré le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cd) ?: time()), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($retroWindowEligible): ?>
                            <?php if (!$enlistmentEngagementTablesReady): ?>
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Le formulaire de bilan sera disponible après la prochaine mise à jour technique côté hébergement.</p>
                            <?php else: ?>
                                <?php if ($staffRetroFeedback): ?>
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-800">Bilan équipe déjà enregistré</p>
                                    <p class="mt-2 text-sm font-bold text-slate-900">
                                        Note <?= (int) ($staffRetroFeedback['rating'] ?? 0) ?> / 5
                                        <?php $sr = (int) ($staffRetroFeedback['rating'] ?? 0); if (isset($retroLabels[$sr])): ?>
                                            <span class="font-semibold text-slate-500">— <?= htmlspecialchars($retroLabels[$sr], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (trim((string) ($staffRetroFeedback['comment'] ?? '')) !== ''): ?>
                                    <div class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700"><?= htmlspecialchars((string) ($staffRetroFeedback['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/bilan-equipe'), ENT_QUOTES, 'UTF-8') ?>" class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                                    <?= \App\Core\Csrf::field() ?>
                                    <div>
                                        <label for="retro_staff_rating" class="text-xs font-bold text-slate-800">Note sur le déroulé</label>
                                        <select id="retro_staff_rating" name="retro_staff_rating" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                            <?php foreach ([5, 4, 3, 2, 1] as $ri): ?>
                                                <option value="<?= $ri ?>"><?= $ri ?> — <?= htmlspecialchars($retroLabels[$ri] ?? (string) $ri, ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="retro_staff_comment" class="text-xs font-bold text-slate-800">Commentaire (obligatoire)</label>
                                        <textarea id="retro_staff_comment" name="retro_staff_comment" rows="4" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Exemples : délais vécus par le candidat, clarté des échanges, points à améliorer…"></textarea>
                                    </div>
                                    <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-emerald-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                        Enregistrer le bilan
                                    </button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($analyticsMergedRows !== [] && !$isDossierClos): ?>
                <?php
                $renderAnalyticsDayBlocks = static function (array $mergedRows) use ($analyticsGroupByDayOrder, $analyticsFrenchDayHeader): void {
                    if ($mergedRows === []) {
                        return;
                    }
                    [$dayOrder, $byDay] = $analyticsGroupByDayOrder($mergedRows);
                    foreach ($dayOrder as $dk) {
                        $rows = $byDay[$dk] ?? [];
                        if ($rows === []) {
                            continue;
                        }
                        ?>
                        <div class="border-b border-stone-100/80 pb-3 last:border-b-0 last:pb-0">
                            <p class="mb-1.5 px-1 text-[10px] font-black uppercase tracking-wider text-stone-500"><?= htmlspecialchars($analyticsFrenchDayHeader($dk), ENT_QUOTES, 'UTF-8') ?></p>
                            <ul class="overflow-hidden rounded-xl border border-stone-100 bg-stone-50/60">
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $labRow = (string) ($r['label'] ?? '');
                                    $timeRow = (string) ($r['time'] ?? '');
                                    $cntRow = max(1, (int) ($r['count'] ?? 1));
                                    ?>
                                    <li class="flex items-center justify-between gap-2 border-b border-stone-100/80 px-3 py-1.5 text-xs last:border-b-0">
                                        <span class="min-w-0 font-semibold leading-snug text-stone-800">
                                            <?= htmlspecialchars($labRow, ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($cntRow > 1): ?>
                                                <span class="ml-1 inline-flex align-middle text-[10px] font-black text-stone-500">×<?= $cntRow ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="shrink-0 tabular-nums text-stone-500"><?= htmlspecialchars($timeRow, ENT_QUOTES, 'UTF-8') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php
                    }
                };
                ?>
                <section id="activite-dossier" class="scroll-mt-28 overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                    <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Indicateurs</p>
                        <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Activité récente sur cette fiche</h2>
                        <p class="mt-2 max-w-3xl text-sm text-stone-600">Historique interne des consultations et actions enregistrées automatiquement sur ce dossier.</p>
                    </div>
                    <div class="px-4 py-3 sm:px-6 sm:py-4">
                        <?php $renderAnalyticsDayBlocks($analyticsMergedVisible); ?>
                    </div>
                    <?php if ($analyticsMergedMore !== []): ?>
                        <?php $moreCount = count($analyticsMergedMore); ?>
                        <details class="group border-t border-stone-200 bg-white">
                            <summary class="cursor-pointer list-none px-4 py-2.5 text-xs font-bold text-sky-800 outline-none transition hover:bg-sky-50 sm:px-6 [&::-webkit-details-marker]:hidden">
                                <span class="inline-flex items-center gap-1.5">
                                    Voir plus
                                    <span class="font-semibold text-stone-400">·</span>
                                    <span class="font-medium text-stone-500"><?= $moreCount ?> ligne<?= $moreCount > 1 ? 's' : '' ?></span>
                                </span>
                            </summary>
                            <div class="border-t border-stone-100 bg-stone-50/40 px-4 py-3 sm:px-6">
                                <?php $renderAnalyticsDayBlocks($analyticsMergedMore); ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

        <!-- Couverture dossier -->
        <header id="couverture-dossier" class="scroll-mt-28 overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
            <div class="h-1.5 bg-gradient-to-r <?= htmlspecialchars($statusBand) ?>" aria-hidden="true"></div>
            <div class="flex flex-col gap-5 border-b border-stone-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-7">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Dossier individuel</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-900 sm:text-3xl">Candidature n°<?= $id ?></h1>
                    <p class="mt-2 text-sm text-stone-600">
                        <?= htmlspecialchars(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: 'Candidat') ?>
                        <?php if (!empty($e['created_at'])): ?>
                            <span class="text-stone-400"> · </span>
                            <span class="tabular-nums">Réception le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $e['created_at']))) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="mt-2 text-xs text-stone-500">
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $id), ENT_QUOTES, 'UTF-8') ?>" class="font-bold text-sky-800 underline decoration-sky-300 underline-offset-2 hover:text-sky-950">Ouvrir le fil de suivi avec le candidat</a>
                        <span class="text-stone-400"> · </span>
                        cette page affiche la fiche instructeur complète (décisions, pièces).
                    </p>
                </div>
                <div class="flex shrink-0 flex-col items-start sm:items-end gap-2">
                    <span class="inline-flex items-center rounded-xl border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-bold text-stone-900">
                        <?= htmlspecialchars($statusLabel ?: '—') ?>
                    </span>
                    <?php if ($statusRaw === 'submitted' && $instructionFollowup === 'pending'): ?>
                        <span class="inline-flex items-center rounded-lg border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-sky-950">Mise en attente active</span>
                    <?php elseif ($statusRaw === 'submitted' && $instructionFollowup === 'interview'): ?>
                        <span class="inline-flex items-center rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-violet-950">Entretien proposé</span>
                    <?php endif; ?>
                    <?php if (!$isDossierClos): ?>
                    <div class="max-w-[18rem] rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-right sm:text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Délai de réponse visé</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-stone-900"><?= htmlspecialchars($slaTargetHuman, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($submissionAgeHuman !== null): ?>
                            <p class="mt-1 text-[11px] leading-snug text-stone-600">Écoulé depuis le dépôt : <?= htmlspecialchars($submissionAgeHuman, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($statusRaw === 'submitted' && $submissionAgeHours !== null): ?>
                            <p class="mt-1.5 inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide <?= $submissionSlaBreached ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900' ?>">
                                <?= $submissionSlaBreached ? 'Hors délai' : 'Dans le délai' ?>
                            </p>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 inline-block text-[10px] font-bold uppercase tracking-wide text-sky-800 underline decoration-sky-300 underline-offset-2 hover:text-sky-950">Modifier le délai communauté</a>
                    </div>
                    <?php endif; ?>
                    <?php if ($candidatePortalSuiviUrl !== null): ?>
                        <a href="<?= htmlspecialchars($candidatePortalSuiviUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-xl border-2 border-sky-300 bg-sky-50 px-4 text-xs font-black uppercase tracking-wide text-sky-950 shadow-sm transition hover:border-sky-400 hover:bg-sky-100">Voir le suivi candidat (portail)</a>
                        <?php if ($candidatePortalSuiviExpiresFmt !== null): ?>
                            <span class="max-w-[16rem] text-right text-[10px] font-medium leading-snug text-stone-500">Lien valide au moins jusqu’au <?= htmlspecialchars($candidatePortalSuiviExpiresFmt, ENT_QUOTES, 'UTF-8') ?> (comme pour le candidat).</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="max-w-[18rem] text-right text-[10px] font-medium leading-snug text-stone-500">Pas de lien de suivi actif : il est créé ou prolongé lors d’un message sur le fil candidat ou d’une notification e-mail au candidat.</span>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars(url('back-office/recruitments')) ?>" class="text-xs font-bold uppercase tracking-wider text-emerald-700 transition hover:text-emerald-900">← Retour à la liste</a>
                </div>
            </div>
        </header>

        <div class="mt-8 space-y-6">

            <?php if (!$isDossierClos): ?>
            <section id="portail-candidat" class="scroll-mt-28 overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Suivi en ligne</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Portail candidat</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-stone-600">Autorisez ou non l’envoi de pièces depuis le lien de suivi sécurisé envoyé au candidat. Ces réglages valent uniquement pour ce dossier.</p>
                    <?php if ($dossierPortalEmailBlocked): ?>
                        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                            <p class="font-bold text-amber-950">Accès au suivi bloqué pour l’adresse e-mail de ce dossier</p>
                            <p class="mt-2 text-amber-950/90">Souvent suite à la modération automatique (contenu refusé). Le candidat voit un message d’indisponibilité. Vous pouvez rétablir l’accès ci-dessous sans passer par l’assistance site.</p>
                            <form method="post" action="<?= htmlspecialchars(url('back-office/ressources/recrutement/automod/restore-access'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-3" onsubmit="return confirm('Rétablir l’accès au portail pour ce dossier (déblocage e-mail sur la communauté) ?');">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="enlistment_id" value="<?= $id ?>">
                                <input type="hidden" name="return_to_dossier" value="1">
                                <label class="flex cursor-pointer items-start gap-3 text-xs text-amber-950/95">
                                    <input type="checkbox" name="also_revoke_ip" value="1" class="mt-0.5 h-4 w-4 rounded border-amber-400 text-amber-800">
                                    <span><strong>Lever aussi</strong> les blocages réseau marqués « portail candidat » sur toute la communauté (utile si le candidat reste bloqué depuis le même lieu).</span>
                                </label>
                                <button type="submit" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-xl bg-emerald-700 px-5 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-emerald-800">Rétablir l’accès au portail</button>
                            </form>
                            <p class="mt-3 text-xs text-amber-900/85">Vue d’ensemble des dossiers concernés : <a class="font-bold underline hover:no-underline" href="<?= htmlspecialchars(url('back-office/ressources/recrutement'), ENT_QUOTES, 'UTF-8') ?>">Bureau recrutement</a>. Blocages manuels : <a class="font-bold underline hover:no-underline" href="<?= htmlspecialchars(url('back-office/security-indicators'), ENT_QUOTES, 'UTF-8') ?>">Blocages portail &amp; sécurité</a>.</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($candidatePortalSuiviUrl !== null): ?>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <a href="<?= htmlspecialchars($candidatePortalSuiviUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-xl bg-sky-700 px-5 text-xs font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-sky-800">Ouvrir la page de suivi complète</a>
                            <span class="text-xs text-stone-600">Même vue que le candidat (nouvel onglet). À utiliser avec discernement — ne pas partager l’URL.</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="px-6 py-6 sm:px-8">
                    <?php if (!$candidatePortalUploadsReady): ?>
                        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Cette fonctionnalité nécessite une mise à jour de la base de données (migration). Exécutez le script de migration du projet puis rechargez cette page.</p>
                    <?php else: ?>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/portal-options'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
                            <?= \App\Core\Csrf::field() ?>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 bg-[#faf8f3] p-4 transition hover:border-stone-300">
                                    <input type="hidden" name="candidate_portal_allow_files" value="0">
                                    <input type="checkbox" name="candidate_portal_allow_files" value="1" class="mt-1 h-4 w-4 rounded border-stone-400 text-[#1c2d41]" <?= $portalAllowFiles ? 'checked' : '' ?>>
                                    <span>
                                        <span class="block text-sm font-bold text-stone-900">Autoriser l’envoi de documents</span>
                                        <span class="mt-1 block text-xs leading-relaxed text-stone-600">PDF, images ou texte simple (taille limitée). Utile pour CV, captures d’écran, etc.</span>
                                    </span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 bg-[#faf8f3] p-4 transition hover:border-stone-300">
                                    <input type="hidden" name="candidate_portal_allow_audio" value="0">
                                    <input type="checkbox" name="candidate_portal_allow_audio" value="1" class="mt-1 h-4 w-4 rounded border-stone-400 text-[#1c2d41]" <?= $portalAllowAudio ? 'checked' : '' ?>>
                                    <span>
                                        <span class="block text-sm font-bold text-stone-900">Autoriser l’envoi d’enregistrements audio</span>
                                        <span class="mt-1 block text-xs leading-relaxed text-stone-600">Sur le portail de suivi, le candidat peut envoyer un fichier audio ou enregistrer un message vocal court directement dans la page.</span>
                                    </span>
                                </label>
                            </div>
                            <?php if ($portalStatusDisplayReady): ?>
                                <?php
                                $bandOpts = [
                                    'amber' => [
                                        'label' => 'Ambre',
                                        'hint' => 'En cours ou attention modérée',
                                        'swatch' => '#f59e0b',
                                        'ring' => 'rgba(245, 158, 11, 0.35)',
                                    ],
                                    'emerald' => [
                                        'label' => 'Vert',
                                        'hint' => 'Message plutôt positif',
                                        'swatch' => '#10b981',
                                        'ring' => 'rgba(16, 185, 129, 0.35)',
                                    ],
                                    'rose' => [
                                        'label' => 'Rose',
                                        'hint' => 'Refus ou point bloquant',
                                        'swatch' => '#f43f5e',
                                        'ring' => 'rgba(244, 63, 94, 0.3)',
                                    ],
                                    'slate' => [
                                        'label' => 'Gris',
                                        'hint' => 'Neutre',
                                        'swatch' => '#64748b',
                                        'ring' => 'rgba(100, 116, 139, 0.35)',
                                    ],
                                    'sky' => [
                                        'label' => 'Bleu ciel',
                                        'hint' => 'Information',
                                        'swatch' => '#0ea5e9',
                                        'ring' => 'rgba(14, 165, 233, 0.35)',
                                    ],
                                ];
                                ?>
                                <div class="rounded-xl border border-stone-200 bg-gradient-to-b from-stone-50/80 to-white p-4 sm:p-5">
                                    <p class="text-sm font-bold text-stone-900">Statut visible sur le portail candidat</p>
                                    <p class="mt-2 text-xs leading-relaxed text-stone-600">Choisissez si le bandeau reprend automatiquement l’étape du parcours (colonne de gauche du suivi) ou un libellé fixe de votre choix. Le référent du dossier et le délai de réponse visé s’affichent aussi côté candidat.</p>
                                    <fieldset class="mt-5 space-y-3">
                                        <legend class="sr-only">Mode d’affichage du statut</legend>
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 bg-white p-3.5 shadow-sm transition hover:border-stone-300">
                                            <input type="radio" name="candidate_portal_status_mode" value="steps" class="mt-1 h-4 w-4 border-stone-400 text-emerald-700" <?= $portalStatusModeForm === 'steps' ? 'checked' : '' ?>>
                                            <span>
                                                <span class="block text-sm font-bold text-stone-900">Aligné sur les étapes</span>
                                                <span class="mt-0.5 block text-xs leading-relaxed text-stone-600">Le titre principal reprend l’étape en cours ; le statut métier du dossier apparaît en sous-texte.</span>
                                            </span>
                                        </label>
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 bg-white p-3.5 shadow-sm transition hover:border-stone-300">
                                            <input type="radio" name="candidate_portal_status_mode" value="manual" class="mt-1 h-4 w-4 border-stone-400 text-emerald-700" <?= $portalStatusModeForm === 'manual' ? 'checked' : '' ?>>
                                            <span>
                                                <span class="block text-sm font-bold text-stone-900">Libellé personnalisé</span>
                                                <span class="mt-0.5 block text-xs leading-relaxed text-stone-600">Vous rédigez le message principal et choisissez la couleur du bandeau (utile pour une consigne locale).</span>
                                            </span>
                                        </label>
                                    </fieldset>

                                    <div class="mt-5 rounded-xl border border-emerald-200/70 bg-emerald-50/40 p-4 sm:p-5">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-900/70">Réglages du libellé personnalisé</p>
                                        <p class="mt-1.5 text-xs leading-relaxed text-stone-600">Ces champs s’appliquent uniquement lorsque « Libellé personnalisé » est sélectionné ci-dessus.</p>

                                        <div class="mt-4 space-y-2">
                                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                                <label for="candidate_portal_status_manual_text" class="block text-[11px] font-bold uppercase tracking-wide text-stone-600">Texte affiché</label>
                                                <span class="text-[11px] font-medium text-stone-500" id="candidate_portal_status_manual_text_hint_len">280 caractères maximum</span>
                                            </div>
                                            <textarea id="candidate_portal_status_manual_text" name="candidate_portal_status_manual_text" rows="2" maxlength="280" aria-describedby="candidate_portal_status_manual_text_hint candidate_portal_status_manual_text_hint_len" class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= htmlspecialchars($portalStatusManualText, ENT_QUOTES, 'UTF-8') ?></textarea>
                                            <p id="candidate_portal_status_manual_text_hint" class="text-[11px] leading-relaxed text-stone-500">Obligatoire uniquement si le mode libellé personnalisé est actif. Ce texte apparaît en titre principal sur le bandeau du candidat.</p>
                                        </div>

                                        <fieldset class="mt-5">
                                            <legend class="block text-[11px] font-bold uppercase tracking-wide text-stone-600">Couleur du bandeau</legend>
                                            <p id="candidate_portal_status_manual_band_hint" class="mt-1 text-[11px] leading-relaxed text-stone-500">Pastille colorée en haut de la carte statut côté candidat. Sans effet en mode « Aligné sur les étapes ».</p>
                                            <div class="portal-band-picker mt-3" role="radiogroup" aria-describedby="candidate_portal_status_manual_band_hint">
                                                <?php foreach ($bandOpts as $val => $meta): ?>
                                                    <?php
                                                    $bandId = 'candidate_portal_status_manual_band_' . $val;
                                                    $isBandChecked = $portalStatusManualBandForm === $val;
                                                    ?>
                                                    <label class="portal-band-option" for="<?= htmlspecialchars($bandId, ENT_QUOTES, 'UTF-8') ?>" style="--band-swatch: <?= htmlspecialchars($meta['swatch'], ENT_QUOTES, 'UTF-8') ?>; --band-ring: <?= htmlspecialchars($meta['ring'], ENT_QUOTES, 'UTF-8') ?>;">
                                                        <input type="radio" id="<?= htmlspecialchars($bandId, ENT_QUOTES, 'UTF-8') ?>" name="candidate_portal_status_manual_band" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" class="portal-band-option__input" <?= $isBandChecked ? 'checked' : '' ?>>
                                                        <span class="portal-band-option__swatch" aria-hidden="true"></span>
                                                        <span class="portal-band-option__copy">
                                                            <span class="portal-band-option__label"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            <span class="portal-band-option__hint"><?= htmlspecialchars($meta['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-950">Le choix entre statut automatique et libellé personnalisé n’est pas encore disponible sur cette installation. Une mise à jour côté serveur est nécessaire.</p>
                            <?php endif; ?>
                            <button type="submit" class="recruitment-lms-submit-primary inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 text-sm font-bold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40 focus-visible:ring-offset-2">Enregistrer les options</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($candidatePortalUploadsReady && $candidatePortalAttachments !== []): ?>
                        <div class="mt-8 border-t border-stone-200 pt-6">
                            <div class="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-stone-500">Pièces reçues via le portail</h3>
                                    <p class="mt-1 text-xs text-stone-600"><?= count($candidatePortalAttachments) ?> élément<?= count($candidatePortalAttachments) > 1 ? 's' : '' ?> transmis par le candidat depuis le lien de suivi.</p>
                                </div>
                            </div>
                            <ul class="mt-4 grid gap-3">
                                <?php foreach ($candidatePortalAttachments as $att): ?>
                                    <?php
                                    $aid = (int) ($att['id'] ?? 0);
                                    $fn = trim((string) ($att['original_name'] ?? '—'));
                                    $k = (string) ($att['kind'] ?? 'file');
                                    $sz = (int) ($att['size_bytes'] ?? 0);
                                    $hum = $sz >= 1048576 ? round($sz / 1048576, 1) . ' Mo' : ($sz >= 1024 ? round($sz / 1024, 1) . ' ko' : (string) max(0, $sz) . ' o');
                                    $when = trim((string) ($att['created_at'] ?? ''));
                                    $whenFmt = $when !== '' ? date('d/m/Y H:i', strtotime($when) ?: time()) : '—';
                                    $isAudio = $k === 'audio';
                                    ?>
                                    <li class="portal-attachment-card flex flex-col gap-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm sm:flex-row sm:items-start sm:justify-between">
                                        <div class="flex min-w-0 flex-1 gap-3">
                                            <span class="portal-attachment-card__icon<?= $isAudio ? ' portal-attachment-card__icon--audio' : '' ?>" aria-hidden="true">
                                                <?php if ($isAudio): ?>
                                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v10.5a3 3 0 1 1-2-2.83V7l8-2v6.5a3 3 0 1 1-2-2.83V5.2L12 6.2V3z"/></svg>
                                                <?php else: ?>
                                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>
                                                <?php endif; ?>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-semibold text-stone-900" title="<?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="mt-0.5 text-xs text-stone-500"><?= $isAudio ? 'Enregistrement audio' : 'Document' ?> · <?= htmlspecialchars($hum, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($whenFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php if ($isAudio): ?>
                                                    <div class="mt-3 max-w-md rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50 to-slate-50 p-3">
                                                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-900/75">Lecture</p>
                                                        <audio controls preload="metadata" src="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/piece/' . $aid . '?inline=1'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg"></audio>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/piece/' . $aid), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 self-start items-center justify-center rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-900 transition hover:border-slate-400 hover:bg-white"><?= $isAudio ? 'Télécharger l’audio' : 'Télécharger' ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php
            $identityFullName = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
            if ($identityFullName === '') {
                $identityFullName = '—';
            }
            $submittedViaRaw = strtolower(trim((string) ($e['submitted_via'] ?? '')));
            $submittedViaHuman = match ($submittedViaRaw) {
                'guest' => 'Invité',
                'account' => 'Compte connecté',
                'preset' => 'Profil enregistré',
                '' => '—',
                default => 'Autre canal',
            };
            $identityInstrItems = [];
            foreach ($enlistmentTimeline as $instrEv) {
                if (!is_array($instrEv)) {
                    continue;
                }
                $instrMeta = is_array($instrEv['metadata'] ?? null) ? $instrEv['metadata'] : [];
                $followupAct = (string) ($instrMeta['followup_action'] ?? '');
                $toStatus = (string) ($instrMeta['to_status'] ?? '');
                $stepCode = (string) ($instrEv['step_code'] ?? '');
                $kind = '';
                $label = '';
                $pill = 'dossier-instr-pill--note';
                if ($followupAct === 'interview') {
                    $kind = 'interview';
                    $label = 'Demande d’entretien';
                    $pill = 'dossier-instr-pill--interview';
                } elseif ($followupAct === 'pending') {
                    $kind = 'pending';
                    $label = 'Mise en attente';
                    $pill = 'dossier-instr-pill--pending';
                } elseif ($stepCode === 'decision' || $toStatus !== '') {
                    $kind = 'decision';
                    $st = $toStatus !== '' ? $toStatus : $statusRaw;
                    $label = match ($st) {
                        'reviewed' => 'Candidature acceptée',
                        'rejected' => 'Candidature refusée',
                        'blocked' => 'Non admis',
                        default => 'Décision enregistrée',
                    };
                    $pill = match ($st) {
                        'reviewed' => 'dossier-instr-pill--accept',
                        'rejected' => 'dossier-instr-pill--reject',
                        'blocked' => 'dossier-instr-pill--block',
                        default => 'dossier-instr-pill--note',
                    };
                } else {
                    continue;
                }
                $actorIdEv = (int) ($instrEv['actor_user_id'] ?? 0);
                $actorNameEv = $actorIdEv > 0
                    ? ($timelineActorLabels[$actorIdEv] ?? null)
                    : null;
                $createdEv = trim((string) ($instrEv['created_at'] ?? ''));
                $identityInstrItems[] = [
                    'kind' => $kind,
                    'label' => $label,
                    'pill' => $pill,
                    'body' => trim((string) ($instrEv['body'] ?? '')),
                    'at' => $createdEv !== '' ? date('d/m/Y à H:i', strtotime($createdEv) ?: time()) : null,
                    'actor' => $actorNameEv,
                ];
            }
            if ($identityInstrItems === []) {
                $rawInstrComment = trim((string) ($e['reviewer_comment'] ?? ''));
                if ($rawInstrComment !== '') {
                    $instrChunks = preg_split("/\n{2,}/u", $rawInstrComment) ?: [];
                    foreach ($instrChunks as $chunk) {
                        $chunk = trim((string) $chunk);
                        if ($chunk === '') {
                            continue;
                        }
                        $kind = 'note';
                        $label = 'Note d’instruction';
                        $pill = 'dossier-instr-pill--note';
                        $body = $chunk;
                        if (preg_match('/^\[DEMANDE ENTRETIEN\]\s*/u', $chunk) === 1) {
                            $kind = 'interview';
                            $label = 'Demande d’entretien';
                            $pill = 'dossier-instr-pill--interview';
                            $body = trim((string) preg_replace('/^\[DEMANDE ENTRETIEN\]\s*/u', '', $chunk));
                        } elseif (preg_match('/^\[MISE EN ATTENTE\]\s*/u', $chunk) === 1) {
                            $kind = 'pending';
                            $label = 'Mise en attente';
                            $pill = 'dossier-instr-pill--pending';
                            $body = trim((string) preg_replace('/^\[MISE EN ATTENTE\]\s*/u', '', $chunk));
                        } elseif (in_array($statusRaw, ['reviewed', 'rejected', 'blocked'], true)) {
                            $kind = 'decision';
                            $label = match ($statusRaw) {
                                'reviewed' => 'Candidature acceptée',
                                'rejected' => 'Candidature refusée',
                                'blocked' => 'Non admis',
                                default => 'Décision enregistrée',
                            };
                            $pill = match ($statusRaw) {
                                'reviewed' => 'dossier-instr-pill--accept',
                                'rejected' => 'dossier-instr-pill--reject',
                                'blocked' => 'dossier-instr-pill--block',
                                default => 'dossier-instr-pill--note',
                            };
                        }
                        $identityInstrItems[] = [
                            'kind' => $kind,
                            'label' => $label,
                            'pill' => $pill,
                            'body' => $body,
                            'at' => null,
                            'actor' => null,
                        ];
                    }
                }
            }
            $showIdentityInstruction = $identityInstrItems !== []
                || !empty($e['reviewed_at'])
                || !empty($e['reviewed_by'])
                || !empty($e['reviewer_comment']);
            $cslug = trim((string) ($communitySlug ?? ''));
            $avisSlug = $linkedRo ? trim((string) ($linkedRo['public_page_slug'] ?? '')) : '';
            ?>
            <section id="identite-reception" class="dossier-identity scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
                <div class="border-b border-slate-100 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-6 py-5 text-white sm:px-8">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-300/90">Rubrique 1</p>
                    <h2 class="mt-1 text-xl font-black tracking-tight text-white">Identité &amp; réception</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/65">Coordonnées du candidat et historique des actions d’instruction visibles sur ce dossier.</p>
                </div>

                <div class="space-y-6 p-5 sm:p-6 sm:px-8">
                    <?php if ($isInternalOpeningApplication): ?>
                    <div class="dossier-identity__banner rounded-xl border border-violet-200 bg-violet-50 px-4 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-800">Candidature interne ciblée</p>
                        <p class="mt-1 text-sm leading-relaxed text-violet-950">Membre déjà rattaché à cette communauté, dossier positionné sur un avis de poste publié ici.</p>
                    </div>
                    <?php endif; ?>

                    <div class="dossier-identity__grid grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="dossier-identity__field rounded-xl border border-slate-200 bg-slate-50/90 p-4">
                            <p class="dossier-identity__label text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Nom complet</p>
                            <p class="mt-2 text-base font-bold leading-snug text-slate-900"><?= htmlspecialchars($identityFullName, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="dossier-identity__field rounded-xl border border-slate-200 bg-slate-50/90 p-4">
                            <p class="dossier-identity__label text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Courriel</p>
                            <p class="mt-2 break-all text-sm font-semibold leading-snug text-slate-800"><?= htmlspecialchars((string) ($e['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="dossier-identity__field rounded-xl border border-slate-200 bg-slate-50/90 p-4">
                            <p class="dossier-identity__label text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Indicatif</p>
                            <p class="mt-2 text-sm font-semibold leading-snug text-slate-800"><?= htmlspecialchars((string) ($e['callsign'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="dossier-identity__field dossier-identity__field--portal rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-4 sm:col-span-2 xl:col-span-1">
                            <p class="dossier-identity__label text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-800/80">Compte portail</p>
                            <div class="mt-2 space-y-2">
                                <?php if ($submitterId > 0): ?>
                                    <a href="<?= htmlspecialchars(url('personnel/' . $submitterId), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex text-sm font-bold text-emerald-900 underline decoration-emerald-700/30 underline-offset-2 transition hover:decoration-emerald-800">Ouvrir la fiche membre liée</a>
                                    <?php if ($isInternalOpeningApplication): ?>
                                        <p class="text-xs leading-relaxed text-slate-700">Compte membre de la communauté — candidature associée à un avis interne.</p>
                                    <?php else: ?>
                                        <p class="text-xs leading-relaxed text-slate-600">Soumission avec compte — aucun avis de poste précis au dépôt.</p>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">Canal : <?= htmlspecialchars($submittedViaHuman, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php elseif ($statusRaw === 'reviewed'): ?>
                                    <p class="text-sm leading-relaxed text-amber-900">Aucun compte lié pour le moment.</p>
                                    <a href="#rattachement-membre" class="inline-flex text-sm font-bold text-sky-900 underline decoration-sky-700/30 underline-offset-2 transition hover:decoration-sky-800">Rattacher la personne</a>
                                <?php elseif ($linkedRo !== null): ?>
                                    <p class="text-sm leading-relaxed text-slate-700">Candidature reçue sans compte au moment du dépôt — dossier relié à un avis de poste.</p>
                                <?php else: ?>
                                    <p class="text-sm text-slate-500">Candidature invitée (sans compte au dépôt)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($transmissionLines !== []): ?>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Éléments transmis par le candidat</p>
                        <ul class="mt-3 space-y-2">
                            <?php foreach ($transmissionLines as $line): ?>
                                <li class="flex gap-2 text-sm text-slate-800">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    <span><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ($showIdentityInstruction): ?>
                    <div class="dossier-instr rounded-xl border border-slate-200 bg-gradient-to-b from-slate-50 to-white p-4 sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Instruction du dossier</p>
                                <p class="mt-1 text-sm text-slate-600">Actions et messages consignés pendant l’instruction.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <?php if (!empty($e['reviewed_at'])): ?>
                                    <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 font-medium tabular-nums text-slate-700"><?= htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $e['reviewed_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($e['reviewed_by'])): ?>
                                    <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-slate-600">Dernière action : <?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($identityInstrItems !== []): ?>
                        <ol class="dossier-instr__timeline mt-5 space-y-0">
                            <?php foreach ($identityInstrItems as $instrItem): ?>
                                <li class="dossier-instr__item relative flex gap-3 pb-5 last:pb-0">
                                    <span class="dossier-instr__rail absolute bottom-0 left-[0.55rem] top-3 w-px bg-slate-200" aria-hidden="true"></span>
                                    <span class="dossier-instr__dot relative z-[1] mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500 ring-4 ring-slate-50" aria-hidden="true"></span>
                                    <div class="min-w-0 flex-1 rounded-xl border border-slate-200/90 bg-white p-3.5 shadow-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="dossier-instr-pill <?= htmlspecialchars((string) $instrItem['pill'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $instrItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($instrItem['at'])): ?>
                                                <span class="text-[11px] font-medium tabular-nums text-slate-500"><?= htmlspecialchars((string) $instrItem['at'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($instrItem['actor'])): ?>
                                                <span class="text-[11px] text-slate-500">· <?= htmlspecialchars((string) $instrItem['actor'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (trim((string) ($instrItem['body'] ?? '')) !== ''): ?>
                                            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800"><?= htmlspecialchars((string) $instrItem['body'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <?php elseif (!empty($e['reviewer_comment'])): ?>
                            <?php
                            $instrFallback = str_replace(
                                ['[MISE EN ATTENTE]', '[DEMANDE ENTRETIEN]'],
                                ['Mise en attente', 'Demande d’entretien'],
                                (string) $e['reviewer_comment']
                            );
                            ?>
                            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-800 shadow-inner whitespace-pre-wrap"><?= htmlspecialchars($instrFallback, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php else: ?>
                            <p class="mt-4 text-sm text-slate-500">Aucune note d’instruction pour le moment.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($e['recruitment_preset_id']) || $linkedRo !== null): ?>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <?php if (!empty($e['recruitment_preset_id'])): ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Modèle de formulaire utilisé</p>
                            <p class="mt-2 text-sm font-semibold text-slate-800">Formulaire type n°<?= (int) $e['recruitment_preset_id'] ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($linkedRo !== null): ?>
                        <div class="rounded-xl border border-sky-200/80 bg-sky-50/60 p-4 <?= empty($e['recruitment_preset_id']) ? 'sm:col-span-2' : '' ?>">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-sky-800/80">Avis de poste associé</p>
                            <p class="mt-2 font-semibold text-slate-900"><?= htmlspecialchars((string) ($linkedRo['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if (trim((string) ($linkedRo['reference_public'] ?? '')) !== ''): ?>
                                <p class="mt-1 text-sm text-slate-600">Référence affichée : <?= htmlspecialchars((string) $linkedRo['reference_public'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($cslug !== '' && $avisSlug !== ''): ?>
                                <a href="<?= htmlspecialchars(url('c/' . rawurlencode($cslug) . '/avis/' . rawurlencode($avisSlug)), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 inline-block text-sm font-semibold text-sky-800 underline hover:text-sky-950" target="_blank" rel="noopener">Ouvrir la fiche publique</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($statusRaw === 'reviewed'): ?>
            <section id="rattachement-membre" class="scroll-mt-28 overflow-hidden rounded-2xl border border-sky-200/90 bg-white shadow-sm">
                <div class="border-b border-sky-100 bg-sky-50/90 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-sky-800/80">Après décision</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-sky-950">Rattachement au compte membre</h2>
                </div>
                <div class="p-6">
                    <?php if (!empty($membershipRepairHint)): ?>
                        <p class="text-sm leading-relaxed text-sky-950"><?= htmlspecialchars((string) $membershipRepairHint) ?></p>
                    <?php elseif ($submitterId > 0): ?>
                        <p class="text-sm leading-relaxed text-sky-900/90">
                            Un compte est déjà lié à ce dossier. Si le membre ne voit pas encore votre communauté comme prévu, vous pouvez relancer l’alignement.
                        </p>
                    <?php else: ?>
                        <p class="text-sm leading-relaxed text-sky-900/90">
                            Aucun compte n’est encore lié à cette candidature. Relancez le rattachement pour créer le compte ou le connecter à un compte existant avec la même adresse e-mail.
                        </p>
                    <?php endif; ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/finalize-membership')) ?>" class="mt-5">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <button type="submit" class="enlist-membership-repair-btn inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 py-2.5 text-sm font-bold shadow-md transition">
                            <?= $submitterId > 0 ? 'Relancer le rattachement au compte de la communauté' : 'Rattacher la personne à la communauté' ?>
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-sky-800/80">
                        <?= $submitterId > 0
                            ? 'Aucun nouvel e-mail automatique. Le membre peut se connecter s’il avait déjà un accès.'
                            : 'Si un nouveau compte est créé, un e-mail d’activation du mot de passe pourra être envoyé selon la configuration.' ?>
                    </p>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($statusRaw === 'submitted'): ?>
            <?php
            $decisionPreselect = ($instructionFollowup === 'pending' || $instructionFollowup === 'interview')
                ? $instructionFollowup
                : 'accept';
            $decisionChoices = [
                [
                    'value' => 'accept',
                    'title' => 'Accepter',
                    'hint' => 'La candidature est retenue.',
                    'effect' => 'Le dossier passe en accepté. Le candidat reçoit une confirmation et le lien de suivi.',
                    'tone' => 'Ton chaleureux : bienvenue, prochaines étapes.',
                    'btn' => 'Accepter et prévenir',
                    'icon' => 'check',
                    'card' => 'enlist-decision-card enlist-decision-card--accept',
                ],
                [
                    'value' => 'pending',
                    'title' => 'Mettre en attente',
                    'hint' => 'Le dossier reste ouvert.',
                    'effect' => 'Le dossier reste dans la file. Le candidat est informé qu’une suite arrivera.',
                    'tone' => 'Ton rassurant : délai, ce qui se passe ensuite.',
                    'btn' => 'Mettre en attente et prévenir',
                    'icon' => 'pause',
                    'card' => 'enlist-decision-card enlist-decision-card--pending',
                ],
                [
                    'value' => 'interview',
                    'title' => 'Demander un entretien',
                    'hint' => 'Proposer un échange.',
                    'effect' => 'Le dossier reste à traiter. Vous pouvez indiquer un créneau dans le courriel.',
                    'tone' => 'Ton professionnel : invitation, modalités, créneau.',
                    'btn' => 'Proposer l’entretien et prévenir',
                    'icon' => 'chat',
                    'card' => 'enlist-decision-card enlist-decision-card--interview',
                ],
                [
                    'value' => 'reject',
                    'title' => 'Refuser',
                    'hint' => 'Décision négative.',
                    'effect' => 'Le dossier est clos en refus. Le candidat reçoit le courriel et le suivi.',
                    'tone' => 'Ton clair et respectueux : motif si utile, sans brusquer.',
                    'btn' => 'Refuser et prévenir',
                    'icon' => 'x',
                    'card' => 'enlist-decision-card enlist-decision-card--reject',
                ],
                [
                    'value' => 'block',
                    'title' => 'Non admis',
                    'hint' => 'Clôture définitive.',
                    'effect' => 'Cette candidature est clôturée pour l’organisation. Le candidat en est informé.',
                    'tone' => 'Ton ferme et factuel : décision définitive.',
                    'btn' => 'Marquer non admis et prévenir',
                    'icon' => 'ban',
                    'card' => 'enlist-decision-card enlist-decision-card--block',
                ],
            ];
            $decisionBtnLabels = [];
            $decisionToneHints = [];
            foreach ($decisionChoices as $chRow) {
                $decisionBtnLabels[$chRow['value']] = $chRow['btn'];
                $decisionToneHints[$chRow['value']] = $chRow['tone'];
            }
            $decisionIcons = [
                'check' => '<svg class="enlist-decision-card__glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9.2 16.6 4.9 12.3l1.4-1.4 2.9 2.9 7.5-7.5 1.4 1.4z"/></svg>',
                'pause' => '<svg class="enlist-decision-card__glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7 5h3.5v14H7zm6.5 0H17v14h-3.5z"/></svg>',
                'chat' => '<svg class="enlist-decision-card__glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 4v-4H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>',
                'x' => '<svg class="enlist-decision-card__glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6z"/></svg>',
                'ban' => '<svg class="enlist-decision-card__glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 2a8 8 0 0 1 6.3 12.9L7.1 5.7A7.9 7.9 0 0 1 12 4zM5.7 7.1 18.9 20.3A8 8 0 0 1 5.7 7.1z"/></svg>',
            ];
            ?>
            <?php
            $discordFormChannel = trim((string) ($e['form_channel'] ?? 'milsim')) === 'discord';
            if ($discordFormChannel):
                $discordAnswersRaw = $e['discord_answers_json'] ?? null;
                $discordAnswers = [];
                if (is_string($discordAnswersRaw) && $discordAnswersRaw !== '') {
                    $decoded = json_decode($discordAnswersRaw, true);
                    $discordAnswers = is_array($decoded) ? $decoded : [];
                } elseif (is_array($discordAnswersRaw)) {
                    $discordAnswers = $discordAnswersRaw;
                }
                $discordEvalRaw = $e['discord_evaluation_json'] ?? null;
                $discordEvaluation = [];
                if (is_string($discordEvalRaw) && $discordEvalRaw !== '') {
                    $decodedEval = json_decode($discordEvalRaw, true);
                    $discordEvaluation = is_array($decodedEval) ? $decodedEval : [];
                } elseif (is_array($discordEvalRaw)) {
                    $discordEvaluation = $discordEvalRaw;
                }
                $discordEvalByCriterion = [];
                foreach ($discordEvaluation as $row) {
                    if (is_array($row) && isset($row['criterion'])) {
                        $discordEvalByCriterion[(string) $row['criterion']] = $row;
                    }
                }
                $discordInterviewAtRaw = trim((string) ($e['discord_interview_at'] ?? ''));
                $discordInterviewAtFmt = $discordInterviewAtRaw !== '' ? date('d/m/Y à H:i', strtotime($discordInterviewAtRaw) ?: time()) : null;
                $discordInterviewAtInput = $discordInterviewAtRaw !== '' ? date('Y-m-d\TH:i', strtotime($discordInterviewAtRaw) ?: time()) : '';
                $discordTransmittedAt = trim((string) ($e['discord_transmitted_at'] ?? ''));
                $discordEvalCriteria = ['Motivation', 'Communication', 'Disponibilité', 'Comportement / attitude', 'Adéquation technique'];
                $discordOverall = $discordEvalByCriterion['Synthèse']['comment'] ?? '';
            ?>
            <section id="discord" class="scroll-mt-28 overflow-hidden rounded-2xl border border-indigo-200/90 bg-white shadow-sm">
                <div class="border-b border-indigo-100 bg-indigo-50/80 px-6 py-5 sm:px-8">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-indigo-900/70">Recrutement Discord</p>
                    <h2 class="mt-1 text-lg font-black tracking-tight text-indigo-950 sm:text-xl">Fiche d’attribution &amp; rendez-vous</h2>
                    <p class="mt-1.5 max-w-3xl text-sm leading-relaxed text-indigo-950/85">Candidature déposée depuis le formulaire Discord. Planifiez le rendez-vous, notez la grille d’évaluation (jamais transmise au candidat), puis transmettez la fiche ou concluez ci-dessous.</p>
                </div>
                <div class="space-y-6 p-5 sm:p-6 sm:px-8">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Pseudo Discord</p>
                            <p class="mt-2 text-base font-bold leading-snug text-slate-900"><?= htmlspecialchars((string) ($e['discord_pseudo'] ?? '—') ?: '—', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Transmission au candidat</p>
                            <p class="mt-2 text-sm font-semibold text-slate-800">
                                <?= $discordTransmittedAt !== '' ? 'Transmise le ' . htmlspecialchars(date('d/m/Y à H:i', strtotime($discordTransmittedAt) ?: time()), ENT_QUOTES, 'UTF-8') : 'Pas encore transmise' ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($discordAnswers !== []): ?>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Réponses au formulaire</p>
                        <dl class="mt-3 space-y-3">
                            <?php foreach ($discordAnswers as $answerRow): ?>
                                <?php if (!is_array($answerRow)) { continue; } ?>
                                <div>
                                    <dt class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($answerRow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dt>
                                    <dd class="mt-0.5 text-sm leading-relaxed text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars((string) ($answerRow['answer'] ?? '—') ?: '—', ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/discord/entretien'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5 rounded-xl border border-indigo-100 bg-indigo-50/30 p-4 sm:p-5">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5" for="discord_interview_at">Rendez-vous Discord</label>
                                <input type="datetime-local" id="discord_interview_at" name="discord_interview_at" value="<?= htmlspecialchars($discordInterviewAtInput, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                                <?php if ($discordInterviewAtFmt !== null): ?>
                                <p class="mt-1 text-[11px] text-slate-500">Dernier rendez-vous enregistré : <?= htmlspecialchars($discordInterviewAtFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5" for="discord_interview_notes">Notes d’entretien</label>
                            <textarea id="discord_interview_notes" name="discord_interview_notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"><?= htmlspecialchars((string) ($e['discord_interview_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Grille d’évaluation (usage interne — jamais visible du candidat)</p>
                            <div class="mt-3 space-y-3">
                                <?php foreach ($discordEvalCriteria as $criterion): ?>
                                    <?php
                                    $key = 'eval_' . md5($criterion);
                                    $existing = $discordEvalByCriterion[$criterion] ?? null;
                                    $existingScore = is_array($existing) ? ($existing['score'] ?? null) : null;
                                    $existingComment = is_array($existing) ? (string) ($existing['comment'] ?? '') : '';
                                    ?>
                                    <div class="grid gap-2 sm:grid-cols-[10rem_5rem_1fr] sm:items-start">
                                        <p class="pt-2 text-sm font-semibold text-slate-800"><?= htmlspecialchars($criterion, ENT_QUOTES, 'UTF-8') ?></p>
                                        <select name="<?= $key ?>_score" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                                            <option value="">—</option>
                                            <?php for ($n = 1; $n <= 5; $n++): ?>
                                                <option value="<?= $n ?>" <?= (int) $existingScore === $n ? 'selected' : '' ?>><?= $n ?>/5</option>
                                            <?php endfor; ?>
                                        </select>
                                        <input type="text" name="<?= $key ?>_comment" value="<?= htmlspecialchars($existingComment, ENT_QUOTES, 'UTF-8') ?>" placeholder="Commentaire (facultatif)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5" for="discord_evaluation_overall">Synthèse générale</label>
                                <textarea id="discord_evaluation_overall" name="discord_evaluation_overall" rows="2" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"><?= htmlspecialchars((string) $discordOverall, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="inline-flex items-center rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-800">Nouveau rendez-vous / enregistrer la fiche</button>
                    </form>

                    <div class="flex flex-wrap gap-3">
                        <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/discord/transmission'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                            <button type="submit" class="inline-flex items-center rounded-xl border border-indigo-300 bg-white px-4 py-2.5 text-sm font-bold text-indigo-800 shadow-sm transition hover:bg-indigo-50">Transmettre la fiche au candidat</button>
                        </form>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/annuler'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Annuler cette candidature ?');">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                            <button type="submit" class="inline-flex items-center rounded-xl border border-rose-300 bg-white px-4 py-2.5 text-sm font-bold text-rose-800 shadow-sm transition hover:bg-rose-50">Annuler et supprimer la candidature</button>
                        </form>
                    </div>
                    <p class="text-xs text-slate-500">Pour valider le candidat et l’intégrer, utilisez « Accepter » dans le bloc « Décision à enregistrer » ci-dessous.</p>
                </div>
            </section>
            <?php endif; ?>

            <section id="instruction-dossier" class="enlist-decision-panel scroll-mt-28 overflow-hidden rounded-2xl border border-amber-200/90 bg-white shadow-sm">
                <div class="enlist-decision-panel__head border-b border-amber-100 bg-amber-50 px-5 py-4 sm:px-6">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-amber-900/70">Instruction</p>
                    <h2 class="mt-1 text-lg font-black tracking-tight text-amber-950 sm:text-xl">Décision à enregistrer</h2>
                    <p class="mt-1.5 max-w-3xl text-sm leading-relaxed text-amber-950/85">Choisissez l’issue, ajoutez un message si besoin, puis validez. Chaque validation envoie un courriel au candidat avec le lien de suivi.</p>
                </div>
                <div class="enlist-decision-panel__body p-5 sm:p-6">
                    <?php if ($instructionFollowup === 'pending'): ?>
                        <div class="enlist-decision-status-banner enlist-decision-status-banner--pending" role="status">
                            <span class="enlist-decision-status-banner__mark" aria-hidden="true"></span>
                            <div>
                                <p class="enlist-decision-status-banner__title">Dossier actuellement en attente</p>
                                <p class="enlist-decision-status-banner__text">Une mise en attente a déjà été envoyée. Vous pouvez la confirmer, passer à un entretien, ou conclure (accepter / refuser / non admis).</p>
                            </div>
                        </div>
                    <?php elseif ($instructionFollowup === 'interview'): ?>
                        <div class="enlist-decision-status-banner enlist-decision-status-banner--interview" role="status">
                            <span class="enlist-decision-status-banner__mark" aria-hidden="true"></span>
                            <div>
                                <p class="enlist-decision-status-banner__title">Entretien déjà proposé</p>
                                <p class="enlist-decision-status-banner__text">Une demande d’entretien est active. Vous pouvez renvoyer un créneau, remettre en attente, ou conclure la candidature.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="instruction-dossier-form" method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/decision')) ?>" class="enlist-decision-form space-y-5">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                        <div class="enlist-decision-step">
                            <div class="enlist-decision-step__head">
                                <p class="enlist-decision-step__num">Étape 1</p>
                                <h3 class="enlist-decision-step__title">Choisir l’issue</h3>
                                <p class="enlist-decision-step__lead">Une seule issue à la fois. Le libellé choisi apparaît dans le courriel au candidat.</p>
                            </div>
                            <fieldset class="enlist-decision-fieldset">
                                <legend class="sr-only">Issue pour cette candidature</legend>
                                <div class="enlist-decision-choice-grid" role="radiogroup" aria-label="Issue pour cette candidature">
                                    <?php foreach ($decisionChoices as $ch): ?>
                                        <?php
                                        $isChecked = $ch['value'] === $decisionPreselect;
                                        $iconHtml = $decisionIcons[$ch['icon']] ?? '';
                                        ?>
                                        <label class="<?= htmlspecialchars($ch['card'], ENT_QUOTES, 'UTF-8') ?>">
                                            <span class="enlist-decision-card__row">
                                                <input
                                                    type="radio"
                                                    name="decision"
                                                    value="<?= htmlspecialchars($ch['value'], ENT_QUOTES, 'UTF-8') ?>"
                                                    class="enlist-decision-choice"
                                                    data-btn-label="<?= htmlspecialchars($ch['btn'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-tone="<?= htmlspecialchars($ch['tone'], ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $isChecked ? 'checked' : '' ?>
                                                >
                                                <span class="enlist-decision-card__icon" aria-hidden="true"><?= $iconHtml ?></span>
                                                <span class="enlist-decision-card__copy">
                                                    <span class="enlist-decision-card__title"><?= htmlspecialchars($ch['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="enlist-decision-card__hint"><?= htmlspecialchars($ch['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="enlist-decision-card__effect"><?= htmlspecialchars($ch['effect'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        </div>

                        <div id="interview-slot-wrap" class="enlist-interview-slot<?= $decisionPreselect === 'interview' ? ' is-open' : '' ?>" aria-hidden="<?= $decisionPreselect === 'interview' ? 'false' : 'true' ?>">
                            <div class="enlist-interview-slot__inner">
                                <p class="enlist-interview-slot__badge">Entretien</p>
                                <label for="interview_slot" class="enlist-interview-slot__label">Créneau proposé <span class="enlist-optional-pill">facultatif</span></label>
                                <p class="enlist-interview-slot__help">Date et heure reprises dans le courriel si vous les renseignez.</p>
                                <input type="datetime-local" id="interview_slot" name="interview_slot" class="enlist-interview-slot__input"<?= $decisionPreselect === 'interview' ? '' : ' tabindex="-1"' ?>>
                            </div>
                        </div>

                        <div class="enlist-decision-step enlist-decision-step--message">
                            <div class="enlist-decision-step__head">
                                <p class="enlist-decision-step__num">Étape 2</p>
                                <h3 class="enlist-decision-step__title">Message au candidat <span class="enlist-optional-pill">facultatif</span></h3>
                                <p class="enlist-decision-step__lead">Précisions, ton, consignes. Le texte est repris tel quel dans le courriel.</p>
                            </div>
                            <p id="enlist-decision-tone" class="enlist-decision-tone" data-default="<?= htmlspecialchars($decisionToneHints[$decisionPreselect] ?? $decisionToneHints['accept'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($decisionToneHints[$decisionPreselect] ?? $decisionToneHints['accept'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <div class="enlist-decision-message-box">
                                <div class="enlist-decision-message-box__toolbar">
                                    <label for="reviewer_comment" class="enlist-decision-message-box__label">Votre texte</label>
                                    <div class="enlist-decision-message-box__tools">
                                        <?php if (!empty($enlistmentCannedMessages)): ?>
                                        <label for="canned-msg-select" class="sr-only">Modèle de texte</label>
                                        <select id="canned-msg-select" class="enlist-decision-canned-select <?= htmlspecialchars(bo_select_class('border-amber-300 text-xs font-semibold text-amber-950 focus:border-amber-500 focus:ring-amber-500/25'), ENT_QUOTES, 'UTF-8') ?>">
                                            <option value="">— Insérer un modèle —</option>
                                            <?php foreach ($enlistmentCannedMessages as $cm): ?>
                                            <?php $ctx = (string) ($cm['context'] ?? 'generic'); ?>
                                            <option value="<?= (int) ($cm['id'] ?? 0) ?>" data-context="<?= htmlspecialchars($ctx, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string) ($cm['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits')) ?>" class="enlist-decision-models-link">Gérer les modèles</a>
                                    </div>
                                </div>
                                <textarea id="reviewer_comment" name="reviewer_comment" rows="4" class="enlist-decision-textarea" placeholder="Exemples : bienvenue, motif du refus, invitation à se manifester…"></textarea>
                            </div>
                            <?php if (!empty($enlistmentCannedMessages)): ?>
                            <script type="application/json" id="enlistment-canned-json"><?= json_encode($enlistmentCannedMessages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                            <?php endif; ?>
                        </div>

                        <div class="enlist-decision-submit-row">
                            <p class="enlist-decision-submit-hint"><strong>En attente</strong> et <strong>entretien</strong> laissent le dossier dans la file. <strong>Accepter</strong>, <strong>refuser</strong> ou <strong>non admis</strong> concluent l’instruction.</p>
                            <button
                                type="submit"
                                id="enlist-decision-submit"
                                class="recruitment-lms-submit-primary enlist-decision-submit enlist-decision-submit--<?= htmlspecialchars($decisionPreselect, ENT_QUOTES, 'UTF-8') ?>"
                                data-labels="<?= htmlspecialchars(json_encode($decisionBtnLabels, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars($decisionBtnLabels[$decisionPreselect] ?? 'Enregistrer et prévenir le candidat', ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($statusRaw === 'submitted'): ?>
            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Aide à la décision</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Parcours possibles</h2>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-3">
                    <article class="rounded-xl border border-sky-200 bg-sky-50/70 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-sky-900">Pré-qualification</h3>
                        <p class="mt-2 text-sm text-sky-900/90">Choisissez « Mettre en attente » dans la fiche décision pour garder le dossier ouvert le temps de compléter le dossier.</p>
                    </article>
                    <article class="rounded-xl border border-violet-200 bg-violet-50/70 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-violet-900">Entretien</h3>
                        <p class="mt-2 text-sm text-violet-900/90">L’issue « Demander un entretien » consigne le besoin d’échange ; vous pouvez proposer un créneau dans le même écran.</p>
                    </article>
                    <article class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-emerald-900">Décision finale</h3>
                        <p class="mt-2 text-sm text-emerald-900/90">Accepter, refuser ou marquer non admis conclut l’instruction et met à jour l’état visible par le candidat.</p>
                    </article>
                </div>
            </section>
            <?php endif; ?>

            <?php
            $olympus = [
                'age' => 'Âge',
                'timezone' => 'Fuseau horaire',
                'weekly_availability' => 'Disponibilités hebdomadaires',
                'system_config' => 'Configuration matérielle',
                'microphone_quality' => 'Qualité microphone',
                'past_milsim_experience' => 'Expérience MilSim',
                'ace_acre_level' => 'Niveau ACE / ACRE',
                'motivation_why_join' => 'Motivation',
                'motivation_accountability' => 'Responsabilité & sérieux',
                'commitment_effort' => 'Engagement',
                'availability_wed_sat' => 'Confirmation des créneaux principaux',
                'availability' => 'Disponibilité (résumé)',
            ];
            $hasOlympus = false;
            foreach ($olympus as $k => $_) {
                if (isset($e[$k]) && $e[$k] !== '' && $e[$k] !== null) {
                    $hasOlympus = true;
                    break;
                }
            }
            $customAnswersRaw = $e['custom_answers_json'] ?? null;
            $customAnswersList = [];
            if (is_string($customAnswersRaw) && $customAnswersRaw !== '') {
                $decodedCa = json_decode($customAnswersRaw, true);
                $customAnswersList = is_array($decodedCa) ? $decodedCa : [];
            } elseif (is_array($customAnswersRaw)) {
                $customAnswersList = $customAnswersRaw;
            }
            $hasCustomAnswers = $customAnswersList !== [];
            ?>
            <?php if (($hasOlympus || $hasCustomAnswers) && !$isDossierClos): ?>
            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Rubrique 2</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Questionnaire MilSim</h2>
                </div>
                <div class="divide-y divide-stone-100 px-6 py-2">
                    <?php foreach ($olympus as $col => $label): ?>
                        <?php if (isset($e[$col]) && $e[$col] !== '' && $e[$col] !== null): ?>
                            <div class="py-4">
                                <p class="text-xs font-bold uppercase tracking-wide text-stone-500"><?= htmlspecialchars($label) ?></p>
                                <p class="mt-2 text-sm leading-relaxed text-stone-800 whitespace-pre-wrap"><?= htmlspecialchars((string) $e[$col]) ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach ($customAnswersList as $caRow): ?>
                        <?php if (!is_array($caRow)) { continue; } ?>
                        <?php
                        $caLabel = trim((string) ($caRow['label'] ?? ''));
                        $caAnswer = trim((string) ($caRow['answer'] ?? ''));
                        if ($caLabel === '' || $caAnswer === '') {
                            continue;
                        }
                        ?>
                        <div class="py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500"><?= htmlspecialchars($caLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-2 text-sm leading-relaxed text-stone-800 whitespace-pre-wrap"><?= htmlspecialchars($caAnswer, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($e['notes']) && !$isDossierClos): ?>
            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-stone-50/40 shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Synthèse</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Notes consolidées</h2>
                </div>
                <pre class="max-h-[28rem] overflow-auto p-6 text-sm leading-relaxed text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $e['notes']) ?></pre>
            </section>
            <?php endif; ?>

            <?php if ($rpSnap && !$isDossierClos): ?>
            <section class="overflow-hidden rounded-2xl border border-emerald-200/80 bg-white shadow-sm">
                <div class="border-b border-emerald-100 bg-emerald-50/80 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-800/80">Profil RP</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-emerald-950">Dossier personnage (copie au dépôt)</h2>
                </div>
                <div class="space-y-5 p-6">
                    <?php
                    $img = trim((string) ($rpSnap['image_url'] ?? ''));
                    $imgExt = trim((string) ($rpSnap['image_external_url'] ?? ''));
                    ?>
                    <?php if ($img !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900 mb-2">Portrait</p>
                            <img src="<?= htmlspecialchars(url($img)) ?>" alt="" class="max-h-52 rounded-xl border border-emerald-200 shadow-sm">
                        </div>
                    <?php endif; ?>
                    <?php if ($imgExt !== ''): ?>
                        <p class="text-sm"><a href="<?= htmlspecialchars($imgExt) ?>" class="break-all font-medium text-emerald-800 underline underline-offset-2 hover:text-emerald-950" target="_blank" rel="noopener">Lien vers portrait externe</a></p>
                    <?php endif; ?>
                    <?php
                    $rpFn = trim((string) ($rpSnap['rp_first_name'] ?? ''));
                    $rpLn = trim((string) ($rpSnap['rp_last_name'] ?? ''));
                    $rpBd = trim((string) ($rpSnap['rp_birth_date'] ?? ''));
                    $rpNat = trim((string) ($rpSnap['rp_nationality'] ?? ''));
                    $rpScene = trim((string) ($rpSnap['rp_scene_name'] ?? ''));
                    $rpDetailAny = $rpFn !== '' || $rpLn !== '' || $rpBd !== '' || $rpNat !== '' || $rpScene !== '';
                    ?>
                    <?php if ($rpDetailAny): ?>
                        <div class="rounded-xl border border-emerald-200/60 bg-emerald-50/40 p-4 grid sm:grid-cols-2 gap-3 text-sm">
                            <?php if ($rpFn !== ''): ?><div><span class="text-[10px] font-bold uppercase text-emerald-900">Prénom</span><p class="mt-0.5 text-stone-900"><?= htmlspecialchars($rpFn) ?></p></div><?php endif; ?>
                            <?php if ($rpLn !== ''): ?><div><span class="text-[10px] font-bold uppercase text-emerald-900">Nom</span><p class="mt-0.5 text-stone-900"><?= htmlspecialchars($rpLn) ?></p></div><?php endif; ?>
                            <?php if ($rpBd !== ''): ?><div><span class="text-[10px] font-bold uppercase text-emerald-900">Naissance</span><p class="mt-0.5 text-stone-900"><?= htmlspecialchars($rpBd) ?></p></div><?php endif; ?>
                            <?php if ($rpNat !== ''): ?><div><span class="text-[10px] font-bold uppercase text-emerald-900">Nationalité</span><p class="mt-0.5 text-stone-900"><?= htmlspecialchars($rpNat) ?></p></div><?php endif; ?>
                            <?php if ($rpScene !== ''): ?><div class="sm:col-span-2"><span class="text-[10px] font-bold uppercase text-emerald-900">Nom de scène</span><p class="mt-0.5 text-stone-900"><?= htmlspecialchars($rpScene) ?></p></div><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($rpSnap['character_name'] ?? '')) !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Libellé dossier (affichage)</p>
                            <p class="mt-1 text-stone-900"><?= htmlspecialchars((string) $rpSnap['character_name']) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($rpSnap['bio'] ?? '')) !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Biographie</p>
                            <pre class="mt-2 text-sm text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['bio']) ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($rpSnap['cv'] ?? '')) !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Parcours (CV)</p>
                            <pre class="mt-2 text-sm text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['cv']) ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($rpSnap['admin_notes'] ?? '')) !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Remarques candidat</p>
                            <pre class="mt-2 text-sm text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['admin_notes']) ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php
                    $derived = is_array($rpSnap['derived_availability'] ?? null) ? $rpSnap['derived_availability'] : null;
                    ?>
                    <?php if ($derived && (!empty($derived['availability']) || !empty($derived['weekly_availability']))): ?>
                        <div class="rounded-xl border border-emerald-200/60 bg-white/80 p-4 text-sm text-emerald-950">
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900 mb-2">Disponibilités (synthèse)</p>
                            <?php if (!empty($derived['weekly_availability'])): ?>
                                <p class="whitespace-pre-wrap"><?= htmlspecialchars((string) $derived['weekly_availability']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($derived['availability']) && ($derived['availability'] ?? '') !== ($derived['weekly_availability'] ?? '')): ?>
                                <p class="mt-2 whitespace-pre-wrap"><?= htmlspecialchars((string) $derived['availability']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

        <?php if (!$isDossierClos): ?>
        <section id="journal-dossier" class="scroll-mt-28 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm" aria-labelledby="journal-dossier-heading">
            <div class="border-b border-slate-200 bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800 px-6 py-6 text-white sm:px-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400">Traçabilité</p>
                        <h2 id="journal-dossier-heading" class="mt-2 text-xl font-black tracking-tight">Chronologie d’instruction</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">
                            Événements du dossier, messages laissés depuis le suivi candidat, pièces déposées, notifications à l’équipe et notes internes. La liste va de la plus ancienne à la plus récente : faites défiler jusqu’en bas pour voir les derniers ajouts. Le formulaire en bas de page n’envoie rien au candidat.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left">
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Entrées</p>
                        <p class="mt-1 text-2xl font-black tabular-nums"><?= $timelineTableMissing ? '—' : (string) count($enlistmentTimeline) ?></p>
                    </div>
                </div>
            </div>
            <div class="px-5 py-6 sm:px-8 sm:py-8">
                <?php if ($timelineTableMissing): ?>
                    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Le journal n’est pas encore activé sur cette base : exécutez les migrations pour créer la table dédiée.</p>
                <?php elseif ($enlistmentTimeline === []): ?>
                    <p class="text-sm text-slate-600">Aucune entrée pour l’instant.</p>
                <?php else: ?>
                    <ol class="relative space-y-5 border-l border-slate-200 pl-5 sm:pl-6">
                        <?php foreach ($enlistmentTimeline as $ev): ?>
                            <?php
                            $evKind = (string) ($ev['entry_kind'] ?? '');
                            $evStep = (string) ($ev['step_code'] ?? 'general');
                            $stepTitle = $timelineStepLabels[$evStep] ?? $timelineStepLabels['general'];
                            $meta = is_array($ev['metadata'] ?? null) ? $ev['metadata'] : [];
                            $family = (string) ($meta['timeline_family'] ?? '');
                            $actorId = (int) ($ev['actor_user_id'] ?? 0);
                            $actorName = $actorId > 0 ? ($timelineActorLabels[$actorId] ?? ('Compte n°' . $actorId)) : null;
                            $created = trim((string) ($ev['created_at'] ?? ''));
                            $createdFmt = $created !== '' ? date('d/m/Y à H:i', strtotime($created) ?: time()) : '—';
                            $summary = trim((string) ($ev['summary'] ?? ''));
                            $body = trim((string) ($ev['body'] ?? ''));
                            $timelinePieceId = null;
                            if (preg_match('/\n\n\[piece:#(\d+)\]\s*$/', $body, $tpm)) {
                                $timelinePieceId = (int) $tpm[1];
                                $body = trim((string) preg_replace('/\n\n\[piece:#\d+\]\s*$/', '', $body));
                            }
                            $timelineLinked = ($timelinePieceId > 0) ? ($portalAttById[$timelinePieceId] ?? null) : null;
                            $timelineAudio = is_array($timelineLinked) && ((string) ($timelineLinked['kind'] ?? '')) === 'audio';
                            $timelineFilePiece = $timelinePieceId > 0 && !$timelineAudio;
                            $dot = 'bg-emerald-500';
                            $kindLabel = 'Événement';
                            $kindClass = 'bg-emerald-50 text-emerald-800 ring-emerald-100';
                            if ($evKind === 'staff_note') {
                                $dot = 'bg-sky-500';
                                $kindLabel = 'Note interne';
                                $kindClass = 'bg-sky-50 text-sky-800 ring-sky-100';
                            }
                            if ($family === 'moderation') {
                                $dot = 'bg-violet-500';
                                $kindLabel = 'Modération';
                                $kindClass = 'bg-violet-50 text-violet-900 ring-violet-100';
                            } elseif ($family === 'email_notify') {
                                $dot = 'bg-sky-500';
                                $kindLabel = 'Courriel';
                                $kindClass = 'bg-sky-50 text-sky-900 ring-sky-100';
                            } elseif ($family === 'portal_message') {
                                $dot = 'bg-amber-500';
                                $kindLabel = 'Message sur le suivi';
                                $kindClass = 'bg-amber-50 text-amber-950 ring-amber-100';
                            } elseif ($family === 'portal_upload') {
                                $dot = 'bg-amber-500';
                                $kindLabel = 'Pièce sur le suivi';
                                $kindClass = 'bg-amber-50 text-amber-950 ring-amber-100';
                            } elseif ($family === 'portal_options') {
                                $dot = 'bg-amber-500';
                                $kindLabel = 'Réglages du suivi';
                                $kindClass = 'bg-amber-50 text-amber-950 ring-amber-100';
                            }
                            ?>
                            <li class="relative">
                                <span class="absolute -left-[1.15rem] top-2 h-2.5 w-2.5 rounded-full border-2 border-white <?= htmlspecialchars($dot, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                                <article class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 <?= htmlspecialchars($kindClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700"><?= htmlspecialchars($stepTitle, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <?php if ($summary !== ''): ?>
                                                <p class="mt-2 text-sm font-bold text-slate-900"><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <time class="shrink-0 text-[11px] font-semibold tabular-nums text-slate-500" datetime="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></time>
                                    </div>
                                    <?php if ($body !== ''): ?>
                                        <?php if ($family === 'portal_message'): ?>
                                            <div class="mt-2 rounded-xl border border-amber-100 bg-amber-50/60 px-3 py-2.5 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap" role="region" aria-label="Message reçu depuis le suivi candidat">
                                                <?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php elseif ($family === 'portal_upload'): ?>
                                            <div class="mt-2 rounded-xl border border-amber-100 bg-amber-50/40 px-3 py-2.5 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap" role="region" aria-label="Dépôt depuis le suivi candidat">
                                                <?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-2 text-sm leading-relaxed text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($timelineFilePiece): ?>
                                        <div class="mt-3">
                                            <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/piece/' . $timelinePieceId), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-lms-submit-secondary inline-flex min-h-[2.25rem] items-center justify-center rounded-xl px-4 py-2 text-xs font-bold shadow-sm">
                                                Télécharger la pièce
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($timelineAudio && $timelinePieceId > 0): ?>
                                        <div class="mt-3 max-w-md rounded-2xl border border-violet-200 bg-gradient-to-r from-violet-600/10 to-fuchsia-600/10 p-3">
                                            <p class="text-[10px] font-black uppercase tracking-wider text-violet-900/90">Écoute</p>
                                            <audio controls preload="metadata" src="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/piece/' . $timelinePieceId . '?inline=1'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg"></audio>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($actorName !== null): ?>
                                        <p class="mt-2 text-[11px] font-medium uppercase tracking-wide text-slate-500">Par <?= htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if (!$timelineTableMissing): ?>
                    <div class="mt-10 rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5 sm:p-6">
                        <div class="mb-5 border-b border-slate-200 pb-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-500">Saisie interne</p>
                            <h3 class="mt-1 text-lg font-black tracking-tight text-slate-950">Ajouter une note sur une étape</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Les options du groupe « Parcours » reprennent <strong class="font-semibold text-slate-800">exactement les titres du portail candidat</strong> (colonne de gauche sur le lien de suivi, y compris les étapes de modération). Le second groupe sert à classer une remarque transverse dans le journal.
                            </p>
                            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600">
                                <li><strong class="font-semibold text-slate-800">Où c’est visible :</strong> dans la zone « Chronologie d’instruction » sur <strong class="font-semibold text-slate-800">cette page</strong>, avec le badge « Note interne » et le nom de l’étape ; la note se place en <strong class="font-semibold text-slate-800">bas de liste</strong> (ordre chronologique du plus ancien au plus récent).</li>
                                <li><strong class="font-semibold text-slate-800">Où ce n’est pas visible :</strong> jamais sur le lien de suivi candidat, jamais par e-mail automatique au candidat.</li>
                            </ul>
                        </div>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/timeline-comment'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                            <div class="grid gap-5 lg:grid-cols-[minmax(0,280px)_minmax(0,1fr)]">
                                <div>
                                    <label for="timeline_step" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Étape concernée</label>
                                    <select id="timeline_step" name="timeline_step" size="14" class="<?= htmlspecialchars(bo_select_class('w-full rounded-2xl border-slate-300 text-sm font-semibold text-slate-900 min-h-[14rem] py-1'), ENT_QUOTES, 'UTF-8') ?>">
                                        <optgroup label="Parcours (comme sur le portail candidat)">
                                            <?php foreach ($portalJourneyStepsForNotes as $idx => $st): ?>
                                                <?php
                                                if (!is_array($st)) {
                                                    continue;
                                                }
                                                $sid = trim((string) ($st['id'] ?? ''));
                                                if ($sid === '' || !isset($timelineStepLabels[$sid])) {
                                                    continue;
                                                }
                                                $ord = (int) $idx + 1;
                                                $hintOpt = trim((string) ($st['hint'] ?? ''));
                                                $titleOpt = $hintOpt !== '' ? $ord . ' · ' . (string) $timelineStepLabels[$sid] . ' — ' . $hintOpt : $ord . ' · ' . (string) $timelineStepLabels[$sid];
                                                ?>
                                                <option value="<?= htmlspecialchars($sid, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($titleOpt, ENT_QUOTES, 'UTF-8') ?>"<?= $timelineNoteSuggestedStep === $sid ? ' selected' : '' ?>><?= (int) $ord ?> · <?= htmlspecialchars((string) $timelineStepLabels[$sid], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Autres étiquettes (journal interne)">
                                            <?php foreach (['portal', 'communication', 'general'] as $code): ?>
                                                <?php if (!isset($timelineStepLabels[$code])) {
                                                    continue;
                                                } ?>
                                                <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars((string) $timelineStepLabels[$code], ENT_QUOTES, 'UTF-8') ?>"<?= $timelineNoteSuggestedStep === $code ? ' selected' : '' ?>><?= htmlspecialchars((string) $timelineStepLabels[$code], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                    <p class="mt-2 text-xs leading-relaxed text-slate-500">Astuce : la ligne pré-sélectionnée correspond à l’<strong class="font-semibold text-slate-700">étape en cours</strong> sur le portail candidat (selon l’état du dossier et les échanges).</p>
                                </div>
                                <div>
                                    <label for="timeline_body" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Commentaire</label>
                                    <textarea id="timeline_body" name="timeline_body" rows="5" required maxlength="8000" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 min-h-[11.5rem]" placeholder="Consignes, rappel d’échange, point de vigilance…"></textarea>
                                </div>
                            </div>
                            <div class="flex justify-end pt-2">
                                <button type="submit" class="recruitment-lms-submit-primary inline-flex min-h-[3rem] items-center justify-center rounded-2xl px-6 py-3 text-sm font-black shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                    Enregistrer dans le journal
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

                </div>

                <p class="mt-10 text-center">
                    <a href="<?= htmlspecialchars(url('back-office/recruitments')) ?>" class="text-sm font-semibold text-stone-600 underline decoration-stone-300 underline-offset-4 transition hover:text-emerald-800">← Retour aux dossiers</a>
                </p>
            </div>
            <?php
            $dossierSideNavMode = 'desktop';
            require base_path('views/admin/recruitments/partials/dossier_side_nav.php');
            ?>
        </div>
</div>
<script src="<?= htmlspecialchars(url('assets/js/recruitment-dossier-tour.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php if ($statusRaw === 'submitted'): ?>
<script>
(function () {
    var form = document.getElementById('instruction-dossier-form');
    if (!form) return;
    var wrap = document.getElementById('interview-slot-wrap');
    var submitBtn = document.getElementById('enlist-decision-submit');
    var toneEl = document.getElementById('enlist-decision-tone');
    var radios = form.querySelectorAll('input.enlist-decision-choice[name="decision"]');
    var submitVariants = ['accept', 'pending', 'interview', 'reject', 'block'];
    var btnLabels = {};
    if (submitBtn) {
        try {
            btnLabels = JSON.parse(submitBtn.getAttribute('data-labels') || '{}') || {};
        } catch (e) {
            btnLabels = {};
        }
    }
    var currentDecision = function () {
        var v = 'accept';
        radios.forEach(function (r) { if (r.checked) v = r.value || v; });
        return v;
    };
    var syncInterview = function () {
        if (!wrap) return;
        var open = currentDecision() === 'interview';
        wrap.classList.toggle('is-open', open);
        wrap.setAttribute('aria-hidden', open ? 'false' : 'true');
        var slotInput = document.getElementById('interview_slot');
        if (slotInput) {
            if (open) {
                slotInput.removeAttribute('tabindex');
            } else {
                slotInput.setAttribute('tabindex', '-1');
            }
        }
    };
    var syncSubmit = function () {
        if (!submitBtn) return;
        var d = currentDecision();
        var label = btnLabels[d] || 'Enregistrer et prévenir le candidat';
        var checked = form.querySelector('input.enlist-decision-choice[name="decision"]:checked');
        if (checked && checked.getAttribute('data-btn-label')) {
            label = checked.getAttribute('data-btn-label') || label;
        }
        submitBtn.textContent = label;
        submitVariants.forEach(function (v) {
            submitBtn.classList.toggle('enlist-decision-submit--' + v, v === d);
        });
    };
    var syncTone = function () {
        if (!toneEl) return;
        var checked = form.querySelector('input.enlist-decision-choice[name="decision"]:checked');
        var tone = (checked && checked.getAttribute('data-tone')) || toneEl.getAttribute('data-default') || '';
        toneEl.textContent = tone;
    };
    var sel = document.getElementById('canned-msg-select');
    var raw = document.getElementById('enlistment-canned-json');
    var ta = document.getElementById('reviewer_comment');
    var byId = {};
    if (raw && raw.textContent) {
        try {
            var list = JSON.parse(raw.textContent || '[]');
            if (Array.isArray(list)) {
                list.forEach(function (row) {
                    if (row && row.id) byId[String(row.id)] = { body: row.body || '', context: row.context || 'generic' };
                });
            }
        } catch (e) {}
    }
    var toContext = function (decision) {
        if (decision === 'accept') return 'accept';
        if (decision === 'reject') return 'reject';
        if (decision === 'block') return 'reject';
        if (decision === 'pending') return 'pending';
        if (decision === 'interview') return 'pending';
        return 'generic';
    };
    var updateCannedFilter = function () {
        if (!sel) return;
        var wanted = toContext(currentDecision());
        Array.prototype.forEach.call(sel.options, function (opt, idx) {
            if (idx === 0) { opt.hidden = false; return; }
            var c = opt.getAttribute('data-context') || 'generic';
            opt.hidden = !(c === 'generic' || c === wanted);
        });
        sel.selectedIndex = 0;
    };
    var syncAll = function () {
        syncInterview();
        syncSubmit();
        syncTone();
        updateCannedFilter();
    };
    radios.forEach(function (r) {
        r.addEventListener('change', syncAll);
    });
    if (sel && ta) {
        sel.addEventListener('change', function () {
            var id = sel.value;
            if (!id || !byId[id]) { sel.selectedIndex = 0; return; }
            var chunk = byId[id].body;
            if (ta.value.trim() !== '') ta.value += '\n\n';
            ta.value += chunk;
            sel.selectedIndex = 0;
            ta.focus();
        });
    }
    syncAll();
})();
</script>
<?php endif; ?>
