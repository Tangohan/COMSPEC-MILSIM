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
$membershipRepairHint = $membershipRepairHint ?? null;
$enlistmentSlaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedAgeHours = isset($e['submitted_age_hours']) ? (int) $e['submitted_age_hours'] : null;
$submittedSlaBreached = !empty($e['submitted_sla_breached']);

$enlistmentTimeline = is_array($enlistmentTimeline ?? null) ? $enlistmentTimeline : [];
$timelineActorLabels = is_array($enlistmentTimelineActorLabels ?? null) ? $enlistmentTimelineActorLabels : [];
$timelineTableMissing = !empty($enlistmentTimelineTableMissing);
$timelineStepLabels = [
    'reception' => 'Réception du dossier',
    'instruction' => 'Instruction et arbitrage',
    'decision' => 'Décision',
    'adhesion' => 'Rattachement au compte membre',
    'general' => 'Commentaire général',
    'portal' => 'Portail candidat',
    'communication' => 'Échanges avec le candidat',
];

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
$portalAllowFiles = !empty($e['candidate_portal_allow_files']);
$portalAllowAudio = !empty($e['candidate_portal_allow_audio']);
$candidatePortalSuiviUrl = isset($candidatePortalSuiviUrl) && is_string($candidatePortalSuiviUrl) && $candidatePortalSuiviUrl !== '' ? $candidatePortalSuiviUrl : null;
$candidatePortalSuiviExpiresFmt = isset($candidatePortalSuiviExpiresFmt) && is_string($candidatePortalSuiviExpiresFmt) && $candidatePortalSuiviExpiresFmt !== '' ? $candidatePortalSuiviExpiresFmt : null;

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
    'submitted' => 'from-amber-500 to-amber-600',
    'reviewed' => 'from-emerald-600 to-emerald-700',
    'rejected' => 'from-rose-500 to-rose-600',
    'blocked' => 'from-slate-600 to-slate-800',
    default => 'from-stone-400 to-stone-600',
};

$recapMeta = match ($statusRaw) {
    'submitted' => [
        'step' => 'Étape 2 sur 3',
        'title' => 'En cours de traitement',
        'bar' => 'w-2/3',
        'barColor' => 'bg-amber-400',
        'hint' => 'Étape suivante : décision finale et notification candidat.',
    ],
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

        <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)] xl:grid-cols-[17rem_minmax(0,1fr)]">
            <aside class="lg:sticky lg:top-6 lg:z-20 lg:self-start lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto lg:overscroll-contain w-full min-w-0">
                <div class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm lg:shadow-md">
                    <div class="border-b border-stone-200 bg-stone-50 px-4 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Dans ce dossier</p>
                    </div>
                    <nav class="space-y-2 p-3" aria-label="Sections du dossier">
                        <a href="#recap-dossier" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-left text-xs font-bold text-slate-900 shadow-sm transition hover:border-emerald-300/60 hover:bg-emerald-50/50">
                            <span>Récapitulatif</span>
                            <span class="text-[10px] font-black text-slate-500">01</span>
                        </a>
                        <?php if ($statusRaw === 'submitted'): ?>
                        <a href="#instruction-dossier" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-left text-xs font-bold text-slate-900 shadow-sm transition hover:border-emerald-300/60 hover:bg-emerald-50/50">
                            <span>Décision</span>
                            <span class="text-[10px] font-black text-slate-500">02</span>
                        </a>
                        <?php endif; ?>
                        <a href="#journal-dossier" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-left text-xs font-bold text-slate-900 shadow-sm transition hover:border-emerald-300/60 hover:bg-emerald-50/50">
                            <span>Journal</span>
                            <span class="text-[10px] font-black text-slate-500">03</span>
                        </a>
                    </nav>
                    <div class="border-t border-stone-200 bg-stone-50 px-4 py-3 text-[11px] text-stone-600 space-y-1.5">
                        <p><span class="font-bold text-stone-800">Statut</span> — <?= htmlspecialchars($statusLabel ?: '—') ?></p>
                        <p><span class="font-bold text-stone-800">Attribué</span> — <?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </aside>

            <div class="space-y-6 min-w-0">
                <section id="recap-dossier" class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
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

                <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                    <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Coordinateur</p>
                        <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Qui suit ce dossier ?</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-stone-600">Le référent correspond à la personne qui a mené l’instruction ou la dernière action enregistrée. Les volontaires permettent à l’équipe de se synchroniser sans attendre une affectation formelle.</p>
                    </div>
                    <div class="space-y-6 px-6 py-6 sm:px-8">
                        <div class="rounded-xl border border-stone-200 bg-[#faf8f3] p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Référent enregistré</p>
                            <p class="mt-2 text-lg font-semibold text-stone-900"><?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <?php if ($recruiterPicksDisplay !== []): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-stone-500">Volontaires</p>
                            <ul class="mt-3 divide-y divide-stone-100 rounded-xl border border-stone-200 bg-white">
                                <?php foreach ($recruiterPicksDisplay as $pk): ?>
                                    <?php
                                    $pkc = trim((string) ($pk['created_at'] ?? ''));
                                    $pkcFmt = $pkc !== '' ? date('d/m/Y H:i', strtotime($pkc) ?: time()) : '—';
                                    ?>
                                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                                        <span class="font-semibold text-stone-900"><?= htmlspecialchars((string) ($pk['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-xs text-stone-500 tabular-nums"><?= htmlspecialchars($pkcFmt, ENT_QUOTES, 'UTF-8') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <?php if ($statusRaw === 'submitted' && $currentStaffUserId > 0): ?>
                            <?php if (!$recruiterPicksTableReady): ?>
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Le volontariat sera disponible après la prochaine mise à jour de la base (migration à exécuter côté hébergement).</p>
                            <?php elseif ($userHasRecruiterPick): ?>
                                <p class="text-sm font-semibold text-emerald-800">Vous avez déjà signalé votre intérêt pour ce dossier.</p>
                            <?php else: ?>
                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/recruteur-volontariat'), ENT_QUOTES, 'UTF-8') ?>" class="inline">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="recruitment-lms-submit-primary inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 text-sm font-bold shadow-sm transition">Me porter volontaire</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php
                $showRetroBlock = $retroWindowEligible || $staffRetroFeedback !== null || $candidateRetroFeedback !== null;
                $retroLabels = [5 => 'Très satisfaisant', 4 => 'Bon', 3 => 'Correct', 2 => 'En-dessous des attentes', 1 => 'À améliorer'];
                ?>
                <?php if ($showRetroBlock): ?>
                <section class="overflow-hidden rounded-2xl border border-sky-200/90 bg-white shadow-sm">
                    <div class="border-b border-sky-100 bg-sky-50 px-6 py-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-sky-800/80">Après 30 jours</p>
                        <h2 class="mt-1 text-base font-black tracking-tight text-sky-950">Bilan du recrutement</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-sky-950/90">Une fois le dossier reçu depuis au moins 30&nbsp;jours, l’équipe et le candidat peuvent laisser une note courte pour améliorer le processus.</p>
                    </div>
                    <div class="space-y-8 px-6 py-6 sm:px-8">
                        <?php if ($candidateRetroFeedback): ?>
                        <div class="rounded-xl border border-stone-200 bg-white p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Retour candidat</p>
                            <p class="mt-2 text-sm font-semibold text-stone-900">Note : <?= (int) ($candidateRetroFeedback['rating'] ?? 0) ?> / 5</p>
                            <div class="mt-3 whitespace-pre-wrap text-sm text-stone-800"><?= htmlspecialchars((string) ($candidateRetroFeedback['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php
                            $cup = trim((string) ($candidateRetroFeedback['updated_at'] ?? ''));
                            $cad = trim((string) ($candidateRetroFeedback['created_at'] ?? ''));
                            $cd = $cup !== '' ? $cup : $cad;
                            ?>
                            <?php if ($cd !== ''): ?>
                                <p class="mt-3 text-xs text-stone-500">Enregistré le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cd) ?: time()), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($retroWindowEligible): ?>
                            <?php if (!$enlistmentEngagementTablesReady): ?>
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Le formulaire de bilan sera disponible après la prochaine mise à jour de la base (migration à exécuter).</p>
                            <?php else: ?>
                                <?php if ($staffRetroFeedback): ?>
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Bilan équipe déjà enregistré</p>
                                    <p class="mt-2 text-sm font-semibold text-stone-900">Note : <?= (int) ($staffRetroFeedback['rating'] ?? 0) ?> / 5</p>
                                    <div class="mt-3 whitespace-pre-wrap text-sm text-stone-800"><?= htmlspecialchars((string) ($staffRetroFeedback['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <p class="mt-3 text-xs text-stone-600">Vous pouvez renvoyer le formulaire pour mettre à jour ce bilan.</p>
                                </div>
                                <?php endif; ?>
                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/bilan-equipe'), ENT_QUOTES, 'UTF-8') ?>" class="max-w-xl space-y-4">
                                    <?= \App\Core\Csrf::field() ?>
                                    <div>
                                        <label for="retro_staff_rating" class="text-xs font-bold text-stone-800">Note sur le déroulé</label>
                                        <select id="retro_staff_rating" name="retro_staff_rating" class="mt-2 block w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-900">
                                            <?php foreach ([5, 4, 3, 2, 1] as $ri): ?>
                                                <option value="<?= $ri ?>"><?= $ri ?> — <?= htmlspecialchars($retroLabels[$ri] ?? (string) $ri, ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="retro_staff_comment" class="text-xs font-bold text-stone-800">Commentaire (obligatoire)</label>
                                        <textarea id="retro_staff_comment" name="retro_staff_comment" rows="4" required class="mt-2 w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900" placeholder="Exemples : délais vécus par le candidat, clarté des échanges, friction sur le portail…"></textarea>
                                    </div>
                                    <button type="submit" class="recruitment-lms-submit-sky inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 text-sm font-bold shadow-sm transition">Enregistrer le bilan</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($enlistmentAnalyticsRecent !== []): ?>
                <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                    <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Indicateurs</p>
                        <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Activité récente sur cette fiche</h2>
                        <p class="mt-2 max-w-3xl text-sm text-stone-600">Historique interne des consultations et actions enregistrées automatiquement sur ce dossier.</p>
                    </div>
                    <ul class="divide-y divide-stone-100 px-6 sm:px-8">
                        <?php foreach ($enlistmentAnalyticsRecent as $ev): ?>
                            <?php
                            $nm = (string) ($ev['name'] ?? '');
                            $labEv = $analyticsEventLabels[$nm] ?? 'Action enregistrée';
                            $ca = trim((string) ($ev['created_at'] ?? ''));
                            $caFmt = $ca !== '' ? date('d/m/Y H:i', strtotime($ca) ?: time()) : '—';
                            ?>
                            <li class="flex flex-wrap items-baseline justify-between gap-2 py-3 text-sm">
                                <span class="font-semibold text-stone-900"><?= htmlspecialchars($labEv, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-xs text-stone-500 tabular-nums"><?= htmlspecialchars($caFmt, ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

        <!-- Couverture dossier -->
        <header class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
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
                </div>
                <div class="flex shrink-0 flex-col items-start sm:items-end gap-2">
                    <span class="inline-flex items-center rounded-xl border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-bold text-stone-900">
                        <?= htmlspecialchars($statusLabel ?: '—') ?>
                    </span>
                    <?php if ($statusRaw === 'submitted' && $submittedAgeHours !== null): ?>
                        <span class="inline-flex items-center rounded-lg border px-2 py-1 text-[10px] font-bold uppercase tracking-wide <?= $submittedSlaBreached ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-sky-200 bg-sky-50 text-sky-900' ?>">
                            <?= $submittedSlaBreached ? 'Délai dépassé' : 'Dans le délai' ?> · <?= $submittedAgeHours ?> h / <?= $enlistmentSlaHours ?> h
                        </span>
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

            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Suivi en ligne</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Portail candidat</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-stone-600">Autorisez ou non l’envoi de pièces depuis le lien de suivi sécurisé envoyé au candidat. Ces réglages valent uniquement pour ce dossier.</p>
                    <?php if (\App\Core\Gate::getInstance()->allows('admin.system')): ?>
                    <p class="mt-3 max-w-3xl rounded-xl border border-sky-200 bg-sky-50/80 px-3 py-2 text-xs text-sky-950">
                        <strong>Assistance site :</strong> après un message bloqué par la modération automatique, en cas de blocage persistant, les opérateurs plateforme peuvent utiliser
                        <a href="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools?' . http_build_query(['tenant_id' => (int) ($e['tenant_id'] ?? 0), 'enlistment_id' => $id])), ENT_QUOTES, 'UTF-8') ?>" class="font-bold underline decoration-sky-400 hover:text-sky-900">Portail recrutement — automod &amp; réouverture</a>
                        (IDs préremplis).
                    </p>
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
                            <button type="submit" class="recruitment-lms-submit-primary inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 text-sm font-bold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40 focus-visible:ring-offset-2">Enregistrer les options</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($candidatePortalUploadsReady && $candidatePortalAttachments !== []): ?>
                        <div class="mt-8 border-t border-stone-200 pt-6">
                            <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-stone-500">Pièces reçues via le portail</h3>
                            <ul class="mt-3 divide-y divide-stone-100 rounded-xl border border-stone-200 bg-white">
                                <?php foreach ($candidatePortalAttachments as $att): ?>
                                    <?php
                                    $aid = (int) ($att['id'] ?? 0);
                                    $fn = trim((string) ($att['original_name'] ?? '—'));
                                    $k = (string) ($att['kind'] ?? 'file');
                                    $sz = (int) ($att['size_bytes'] ?? 0);
                                    $hum = $sz >= 1048576 ? round($sz / 1048576, 1) . ' Mo' : ($sz >= 1024 ? round($sz / 1024, 1) . ' ko' : (string) max(0, $sz) . ' o');
                                    $when = trim((string) ($att['created_at'] ?? ''));
                                    $whenFmt = $when !== '' ? date('d/m/Y H:i', strtotime($when) ?: time()) : '—';
                                    ?>
                                    <li class="flex flex-col gap-3 px-4 py-3 text-sm sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-stone-900"><?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="mt-0.5 text-xs text-stone-500"><?= $k === 'audio' ? 'Audio' : 'Document' ?> · <?= htmlspecialchars($hum, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($whenFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php if ($k === 'audio'): ?>
                                                <div class="mt-3 max-w-md rounded-2xl border border-violet-200 bg-gradient-to-r from-violet-50 to-fuchsia-50 p-3">
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-violet-900/80">Lecture</p>
                                                    <audio controls preload="metadata" src="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/piece/' . $aid . '?inline=1'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg"></audio>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/piece/' . $aid), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0 self-start rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-900 transition hover:bg-slate-200"><?= $k === 'audio' ? 'Télécharger l’audio' : 'Télécharger' ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Rubrique 1</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Identité &amp; réception</h2>
                </div>
                <div class="divide-y divide-stone-100">
                    <?php if ($isInternalOpeningApplication): ?>
                    <div class="px-6 py-4 bg-violet-50/90 border-b border-violet-100">
                        <p class="text-xs font-bold uppercase tracking-wide text-violet-900">Candidature interne ciblée</p>
                        <p class="mt-1 text-sm leading-relaxed text-violet-950">Membre déjà rattaché à cette communauté, dossier positionné sur un avis de poste publié ici.</p>
                    </div>
                    <?php endif; ?>
                    <div class="px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Nom complet</p>
                        <p class="mt-1 text-lg font-semibold text-stone-900"><?= htmlspecialchars(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: '—') ?></p>
                    </div>
                    <div class="grid gap-0 sm:grid-cols-2 sm:divide-x sm:divide-stone-100">
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Courriel</p>
                            <p class="mt-1 break-all text-stone-800"><?= htmlspecialchars((string) ($e['email'] ?? '—')) ?></p>
                        </div>
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Indicatif</p>
                            <p class="mt-1 text-stone-800"><?= htmlspecialchars((string) ($e['callsign'] ?? '—')) ?></p>
                        </div>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Compte portail</p>
                        <div class="mt-1">
                            <?php if ($submitterId > 0): ?>
                                <a href="<?= htmlspecialchars(url('personnel/' . $submitterId)) ?>" class="font-semibold text-[#1c4d6e] underline decoration-[#1c4d6e]/30 underline-offset-2 hover:decoration-[#1c4d6e]">Ouvrir la fiche membre liée</a>
                                <?php if ($isInternalOpeningApplication): ?>
                                    <p class="mt-2 text-sm text-stone-700 leading-relaxed">Compte membre de la communauté — candidature associée à un avis interne (voir ci-dessous).</p>
                                <?php else: ?>
                                    <p class="mt-2 text-sm text-stone-600 leading-relaxed">Soumission avec compte — aucun avis de poste précis au dépôt.</p>
                                <?php endif; ?>
                                <span class="mt-2 block text-xs text-stone-500">Canal de soumission : <?= htmlspecialchars((string) ($e['submitted_via'] ?? '—')) ?></span>
                            <?php elseif ($linkedRo !== null): ?>
                                <span class="text-stone-700">Candidature reçue sans compte au moment du dépôt — dossier relié à un avis de poste.</span>
                            <?php else: ?>
                                <span class="text-stone-500">Candidature invitée (sans compte au dépôt)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($transmissionLines !== []): ?>
                    <div class="px-6 py-4 bg-[#faf8f3]">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-600">Éléments transmis par le candidat</p>
                        <ul class="mt-2 list-disc pl-5 text-sm text-stone-800 space-y-1">
                            <?php foreach ($transmissionLines as $line): ?>
                                <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($e['reviewed_at']) || !empty($e['reviewer_comment']) || !empty($e['reviewed_by'])): ?>
                    <div class="border-t border-stone-200 bg-[#faf8f3]/60 px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Instruction du dossier</p>
                        <div class="mt-2 text-sm text-stone-800">
                            <?php if (!empty($e['reviewed_at'])): ?>
                                <p class="tabular-nums font-medium"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $e['reviewed_at']))) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($e['reviewed_by'])): ?>
                                <p class="mt-1 text-xs text-stone-500">Référent ou dernière action : <?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if (!empty($e['reviewer_comment'])): ?>
                                <div class="mt-3 rounded-xl border border-stone-200 bg-white p-4 text-stone-800 shadow-inner whitespace-pre-wrap"><?= htmlspecialchars((string) $e['reviewer_comment']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($e['recruitment_preset_id'])): ?>
                    <div class="px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Modèle de formulaire utilisé</p>
                        <p class="mt-1 text-stone-700">Référence interne n°<?= (int) $e['recruitment_preset_id'] ?></p>
                    </div>
                    <?php endif; ?>
                    <?php
                    $cslug = trim((string) ($communitySlug ?? ''));
                    $avisSlug = $linkedRo ? trim((string) ($linkedRo['public_page_slug'] ?? '')) : '';
                    ?>
                    <?php if ($linkedRo !== null): ?>
                    <div class="px-6 py-4 bg-sky-50/50">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Avis de poste associé</p>
                        <p class="mt-1 font-semibold text-stone-900"><?= htmlspecialchars((string) ($linkedRo['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (trim((string) ($linkedRo['reference_public'] ?? '')) !== ''): ?>
                            <p class="mt-1 text-sm text-stone-600">Référence affichée : <?= htmlspecialchars((string) $linkedRo['reference_public'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($cslug !== '' && $avisSlug !== ''): ?>
                            <a href="<?= htmlspecialchars(url('c/' . rawurlencode($cslug) . '/avis/' . rawurlencode($avisSlug)), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 inline-block text-sm font-semibold text-sky-800 underline hover:text-sky-950" target="_blank" rel="noopener">Ouvrir la fiche publique</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($statusRaw === 'reviewed'): ?>
            <section class="overflow-hidden rounded-2xl border border-sky-200/90 bg-white shadow-sm">
                <div class="border-b border-sky-100 bg-sky-50/90 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-sky-800/80">Après décision</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-sky-950">Rattachement au compte membre</h2>
                </div>
                <div class="p-6">
                    <?php if (!empty($membershipRepairHint)): ?>
                        <p class="text-sm leading-relaxed text-sky-950"><?= htmlspecialchars((string) $membershipRepairHint) ?></p>
                    <?php else: ?>
                        <p class="text-sm leading-relaxed text-sky-900/90">
                            Si le membre ne voit pas encore votre communauté comme prévu, vous pouvez relancer l’alignement du compte sur cette organisation.
                        </p>
                    <?php endif; ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/finalize-membership')) ?>" class="mt-5">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <button type="submit" class="enlist-membership-repair-btn inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 py-2.5 text-sm font-bold shadow-md transition">
                            Forcer le rattachement au compte de la communauté
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-sky-800/80">Aucun nouvel e-mail automatique. Le membre peut se connecter s’il avait déjà un accès.</p>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($statusRaw === 'submitted'): ?>
            <?php
            $decisionChoices = [
                ['value' => 'accept', 'title' => 'Accepter', 'hint' => 'La candidature est retenue ; le candidat reçoit une confirmation.', 'card' => 'enlist-decision-card enlist-decision-card--accept'],
                ['value' => 'pending', 'title' => 'Mettre en attente', 'hint' => 'Le dossier reste à traiter ; le candidat est informé du délai.', 'card' => 'enlist-decision-card enlist-decision-card--pending'],
                ['value' => 'interview', 'title' => 'Demander un entretien', 'hint' => 'Proposer un échange ; vous pouvez indiquer un créneau ci-dessous.', 'card' => 'enlist-decision-card enlist-decision-card--interview'],
                ['value' => 'reject', 'title' => 'Refuser', 'hint' => 'Décision négative avec courriel au candidat.', 'card' => 'enlist-decision-card enlist-decision-card--reject'],
                ['value' => 'block', 'title' => 'Marquer non admis', 'hint' => 'Clôt définitivement cette candidature pour l’organisation.', 'card' => 'enlist-decision-card enlist-decision-card--block'],
            ];
            ?>
            <section id="instruction-dossier" class="overflow-hidden rounded-2xl border border-amber-200/90 bg-white shadow-sm">
                <div class="border-b border-amber-100 bg-amber-50 px-6 py-4 sm:px-8">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-amber-900/70">Instruction</p>
                    <h2 class="mt-1 text-lg font-black tracking-tight text-amber-950 sm:text-xl">Décision à enregistrer</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-amber-950/85">Choisissez l’issue, rédigez si besoin le texte du courriel, puis validez. Chaque validation envoie un message au candidat avec le lien de suivi.</p>
                </div>
                <div class="p-6 sm:p-8">
                    <form id="instruction-dossier-form" method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/decision')) ?>" class="space-y-8">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                        <div class="space-y-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-amber-900/60">Étape 1</p>
                                <h3 class="mt-1 text-base font-black text-amber-950">Choisir l’issue</h3>
                                <p class="mt-1 max-w-2xl text-sm text-amber-950/80">Une seule issue à la fois. Relisez le libellé avant d’envoyer : le candidat le voit dans son courriel.</p>
                            </div>
                            <fieldset>
                                <legend class="sr-only">Issue pour cette candidature</legend>
                                <div class="grid gap-3 sm:grid-cols-2 enlist-decision-choice-grid" role="radiogroup" aria-label="Issue pour cette candidature">
                                    <?php foreach ($decisionChoices as $idx => $ch): ?>
                                        <label class="<?= htmlspecialchars($ch['card'], ENT_QUOTES, 'UTF-8') ?>">
                                            <span class="flex items-start gap-3">
                                                <input
                                                    type="radio"
                                                    name="decision"
                                                    value="<?= htmlspecialchars($ch['value'], ENT_QUOTES, 'UTF-8') ?>"
                                                    class="enlist-decision-choice mt-1 h-4 w-4 shrink-0 border-stone-300 text-amber-700 focus:ring-amber-500/40"
                                                    <?= $idx === 0 ? 'checked' : '' ?>
                                                >
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-black text-stone-900"><?= htmlspecialchars($ch['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="mt-1 block text-xs leading-relaxed text-stone-600"><?= htmlspecialchars($ch['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        </div>

                        <div id="interview-slot-wrap" class="hidden rounded-xl border border-violet-200 bg-violet-50/70 p-4 sm:p-5">
                            <label for="interview_slot" class="block text-xs font-bold uppercase tracking-wide text-violet-900">Créneau d’entretien proposé (facultatif)</label>
                            <p class="mt-1 text-xs text-violet-900/85">Indiquez une date et une heure si vous souhaitez proposer un rendez-vous directement dans le courriel.</p>
                            <input type="datetime-local" id="interview_slot" name="interview_slot" class="mt-3 w-full max-w-md rounded-lg border border-violet-300 bg-white px-3 py-2.5 text-sm text-violet-950 shadow-inner">
                        </div>

                        <div class="space-y-4 border-t border-amber-100/80 pt-8">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-amber-900/60">Étape 2</p>
                                <h3 class="mt-1 text-base font-black text-amber-950">Texte du courriel au candidat</h3>
                                <p class="mt-1 max-w-2xl text-sm text-amber-950/80">Facultatif : précisions, ton, consignes. Le candidat le reçoit avec le lien de suivi.</p>
                            </div>
                            <div class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-xs leading-relaxed text-stone-700">
                                Vérifiez le texte avant validation : il est repris tel quel dans le courriel envoyé au candidat.
                            </div>
                            <div>
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <label for="reviewer_comment" class="text-xs font-bold text-amber-950">Message (facultatif)</label>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <?php if (!empty($enlistmentCannedMessages)): ?>
                                        <label for="canned-msg-select" class="sr-only">Modèle de texte</label>
                                        <select id="canned-msg-select" class="<?= htmlspecialchars(bo_select_class('max-w-[min(100%,18rem)] border-amber-300 text-xs font-semibold text-amber-950 focus:border-amber-500 focus:ring-amber-500/25'), ENT_QUOTES, 'UTF-8') ?>">
                                            <option value="">— Insérer un modèle —</option>
                                            <?php foreach ($enlistmentCannedMessages as $cm): ?>
                                            <?php $ctx = (string) ($cm['context'] ?? 'generic'); ?>
                                            <option value="<?= (int) ($cm['id'] ?? 0) ?>" data-context="<?= htmlspecialchars($ctx, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string) ($cm['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits')) ?>" class="text-xs font-bold text-[#1c4d6e] underline underline-offset-2 hover:text-[#0c3d5c]">Gérer les modèles</a>
                                    </div>
                                </div>
                                <textarea id="reviewer_comment" name="reviewer_comment" rows="5" class="w-full rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-stone-900 shadow-inner placeholder:text-stone-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20" placeholder="Exemples : bienvenue, motif du refus, invitation à se manifester…"></textarea>
                            </div>
                            <?php if (!empty($enlistmentCannedMessages)): ?>
                            <script type="application/json" id="enlistment-canned-json"><?= json_encode($enlistmentCannedMessages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-amber-100/80 pt-6 sm:flex-row sm:items-center sm:justify-between">
                            <p class="max-w-xl text-xs leading-relaxed text-amber-950/85"><strong>En attente</strong> et <strong>demande d’entretien</strong> laissent le dossier dans la file avec un courriel au candidat. <strong>Non admis</strong> clôt la candidature pour votre organisation.</p>
                            <button type="submit" class="recruitment-lms-submit-primary inline-flex min-h-[3rem] w-full shrink-0 items-center justify-center rounded-2xl px-8 py-3 text-sm font-black shadow-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 sm:w-auto">
                                Enregistrer la décision et prévenir le candidat
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
                'availability_wed_sat' => 'Mercredis & samedis soir',
                'availability' => 'Disponibilité (résumé)',
            ];
            $hasOlympus = false;
            foreach ($olympus as $k => $_) {
                if (isset($e[$k]) && $e[$k] !== '' && $e[$k] !== null) {
                    $hasOlympus = true;
                    break;
                }
            }
            ?>
            <?php if ($hasOlympus): ?>
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
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($e['notes'])): ?>
            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-stone-50/40 shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Synthèse</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Notes consolidées</h2>
                </div>
                <pre class="max-h-[28rem] overflow-auto p-6 text-sm leading-relaxed text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $e['notes']) ?></pre>
            </section>
            <?php endif; ?>

            <?php if ($rpSnap): ?>
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

        <section id="journal-dossier" class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm" aria-labelledby="journal-dossier-heading">
            <div class="border-b border-slate-200 bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800 px-6 py-6 text-white sm:px-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400">Traçabilité</p>
                        <h2 id="journal-dossier-heading" class="mt-2 text-xl font-black tracking-tight">Chronologie d’instruction</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">
                            Événements du dossier, messages laissés depuis le suivi candidat, pièces déposées, notifications à l’équipe et notes internes. Le formulaire en bas de page n’envoie rien au candidat.
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
                            <p class="mt-1 text-sm text-slate-600">Visible uniquement dans ce dossier (pas envoyée au candidat).</p>
                        </div>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/timeline-comment'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                            <div class="grid gap-5 lg:grid-cols-[260px_minmax(0,1fr)]">
                                <div>
                                    <label for="timeline_step" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Étape concernée</label>
                                    <select id="timeline_step" name="timeline_step" class="<?= htmlspecialchars(bo_select_class('w-full rounded-2xl border-slate-300 text-sm font-semibold text-slate-900'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php foreach (['instruction', 'decision', 'adhesion', 'reception', 'general'] as $code): ?>
                                            <?php if (!isset($timelineStepLabels[$code])) {
                                                continue;
                                            } ?>
                                            <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $timelineStepLabels[$code], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="timeline_body" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Commentaire</label>
                                    <textarea id="timeline_body" name="timeline_body" rows="5" required maxlength="8000" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10" placeholder="Consignes, rappel d’échange, point de vigilance…"></textarea>
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

                <p class="mt-10 text-center">
                    <a href="<?= htmlspecialchars(url('back-office/recruitments')) ?>" class="text-sm font-semibold text-stone-600 underline decoration-stone-300 underline-offset-4 transition hover:text-emerald-800">← Retour aux dossiers</a>
                </p>
            </div>
            </div>
        </div>
</div>
<?php if ($statusRaw === 'submitted'): ?>
<script>
(function () {
    var form = document.getElementById('instruction-dossier-form');
    if (!form) return;
    var wrap = document.getElementById('interview-slot-wrap');
    var radios = form.querySelectorAll('input.enlist-decision-choice[name="decision"]');
    var currentDecision = function () {
        var v = 'accept';
        radios.forEach(function (r) { if (r.checked) v = r.value || v; });
        return v;
    };
    var syncInterview = function () {
        if (!wrap) return;
        wrap.classList.toggle('hidden', currentDecision() !== 'interview');
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
    radios.forEach(function (r) {
        r.addEventListener('change', function () { syncInterview(); updateCannedFilter(); });
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
    updateCannedFilter();
    syncInterview();
})();
</script>
<?php endif; ?>
