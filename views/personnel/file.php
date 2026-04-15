<?php
$baseUrl = url('');
$targetUser = $targetUser ?? null;
$personnelExtras = $personnelExtras ?? [];
$personnelProfile = $personnelProfile ?? null;
$userProfile = $userProfile ?? null;
$grade = $grade ?? null;
$grades = $grades ?? [];
$assignments = $assignments ?? [];
$primaryAssignment = $primaryAssignment ?? null;
$commander = $commander ?? null;
$commanderLabelsById = is_array($commanderLabelsById ?? null) ? $commanderLabelsById : [];
$personnelJobRoleAssignments = is_array($personnelJobRoleAssignments ?? null) ? $personnelJobRoleAssignments : [];
$personnelPlanningEntries = is_array($personnelPlanningEntries ?? null) ? $personnelPlanningEntries : [];
$canViewOperationalBoardLink = !empty($canViewOperationalBoardLink);
$qualifications = $qualifications ?? [];
$serviceHistory = $serviceHistory ?? [];
$trainingCertificates = $trainingCertificates ?? [];
$lmsEnrollmentsForPersonnel = $lmsEnrollmentsForPersonnel ?? [];
$completeness = $completeness ?? ['score' => 0, 'sections_critiques' => [], 'details' => []];
$adminPanels = $adminPanels ?? [];
$adminDataByPanel = $adminDataByPanel ?? [];
$canEditNotes = $canEditNotes ?? false;
$canEditProfile = $canEditProfile ?? false;
$canViewCivil = $canViewCivil ?? false;
$canViewCivilSection = $canViewCivilSection ?? $canViewCivil;
$redactPersonalPresentation = $redactPersonalPresentation ?? false;
$canViewCommandNotes = $canViewCommandNotes ?? true;
$displaySettings = $displaySettings ?? [];
$showEmailInContact = $showEmailInContact ?? true;
$showMatriculePublic = $showMatriculePublic ?? true;
$civilIdentity = $civilIdentity ?? ['first_name' => '', 'last_name' => '', 'source' => null];
$civilSourceLabel = $civilSourceLabel ?? '';
$primaryUnitFallbackName = $primaryUnitFallbackName ?? null;
$rpDossierNeedsAttention = $rpDossierNeedsAttention ?? false;
$latestEnlistment = $latestEnlistment ?? null;

$lmsEnrollmentStatusFr = static function (string $s): string {
    return match ($s) {
        'assigned' => 'Non démarré',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
        'failed' => 'Non validé',
        'expired' => 'Expiré',
        'revoked' => 'Retiré par l’organisation',
        'withdrawn' => 'Inscription annulée',
        'pending_approval' => 'En attente de validation',
        default => '—',
    };
};
$lmsCertificateStatusFr = static function (string $s): string {
    return match ($s) {
        'valid' => 'À jour',
        'expired' => 'Expirée',
        'revoked' => 'Retirée',
        default => '—',
    };
};
$accountStatusFr = static function (?string $s): string {
    return match (trim((string) $s)) {
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de validation de l’e-mail',
        'suspended' => 'Compte suspendu',
        'banned' => 'Compte exclu',
        '' => '—',
        default => 'Compte : statut à confirmer auprès d’un administrateur',
    };
};
$qualificationStatusFr = static function (?string $s): string {
    return match (trim((string) $s)) {
        'valid' => 'À jour',
        'expired' => 'Expirée',
        'revoked' => 'Retirée',
        'pending' => 'En attente de validation',
        'suspended' => 'Suspendue',
        '' => '—',
        default => 'État à confirmer',
    };
};
$planningEntryTypeFr = static function (?string $t): string {
    return match (trim((string) $t)) {
        'permanence' => 'Permanence',
        'info' => 'Point d’information',
        'mission' => 'Mission',
        'task' => 'Tâche',
        'formation' => 'Formation',
        default => 'Activité',
    };
};
$planningOperationalStatusFr = static function (?string $t): string {
    return match (trim((string) $t)) {
        'planned' => 'Prévu',
        'in_progress' => 'En cours',
        'suspended' => 'Suspendu',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
        default => '',
    };
};

if (!$targetUser) {
    echo '<p>Utilisateur non trouvé.</p>';
    return;
}

$viewerIsPersonnelSubject = (int) (\App\Core\Session::get('user_id') ?? 0) === (int) ($targetUser['id'] ?? 0);

$userProfile = is_array($userProfile ?? null) ? $userProfile : [];
$personnelProfile = is_array($personnelProfile ?? null) ? $personnelProfile : [];
$personnelExtras = is_array($personnelExtras ?? null) ? $personnelExtras : [];

$matricule = $personnelProfile['matricule_internal'] ?? $personnelExtras['service_number'] ?? null;
$callsign = $personnelProfile['callsign'] ?? $targetUser['callsign'] ?? null;
$rpCharacterName = trim((string) ($personnelProfile['character_name'] ?? ''));
if ($rpCharacterName !== '') {
    $displayName = $rpCharacterName;
} elseif (!empty($redactPersonalPresentation)) {
    $dn = trim((string) ($targetUser['display_name'] ?? ''));
    $displayName = $dn !== '' ? $dn : (string) ($targetUser['email'] ?? '—');
} else {
    $civilFull = trim(($civilIdentity['first_name'] ?? '') . ' ' . ($civilIdentity['last_name'] ?? ''));
    $displayName = $civilFull !== '' ? $civilFull : ($targetUser['display_name'] ?: $targetUser['email']);
}
$readiness = isset($personnelProfile['readiness_score']) ? (int)$personnelProfile['readiness_score'] : (isset($personnelExtras['readiness_percent']) ? (int)$personnelExtras['readiness_percent'] : null);
$adminNotes = trim((string)($personnelProfile['command_notes'] ?? '')) ?: ($personnelExtras['admin_notes'] ?? null);
$clearanceLevel = trim((string)($personnelProfile['clearance_level'] ?? '')) ?: trim((string)($personnelExtras['clearance_level'] ?? ''));

$avatarUrl = !empty($targetUser['avatar_url']) ? $targetUser['avatar_url'] : null;
if ($avatarUrl && strpos($avatarUrl, 'http') !== 0) {
    $avatarUrl = $baseUrl . '/' . ltrim($avatarUrl, '/');
}
$portraitUrl = null;
if (!empty($personnelProfile['character_portrait_path'])) {
    $portraitUrl = $baseUrl . '/' . ltrim($personnelProfile['character_portrait_path'], '/');
}

$unitName = $primaryAssignment['unit_name'] ?? $primaryUnitFallbackName ?? ($personnelExtras['squadron'] ?? null);
$enlistmentDate = $personnelProfile['enlistment_date'] ?? $personnelExtras['date_of_enlistment'] ?? null;
$enlistmentFormatted = null;
if ($enlistmentDate) {
    $d = date_create($enlistmentDate);
    $enlistmentFormatted = $d ? $d->format('d/m/Y') : $enlistmentDate;
}
$flightHours = $personnelExtras['flight_hours'] ?? null;
$specializations = $personnelExtras['specializations'] ?? null;

$completenessScore = (int)($completeness['score'] ?? 0);
$sectionsCritiques = $completeness['sections_critiques'] ?? [];

$gradeLabel = '';
if (is_array($grade)) {
    $gradeLabel = trim((string) ($grade['label_long'] ?? ''));
    if ($gradeLabel === '') {
        $gradeLabel = trim((string) ($grade['label_short'] ?? ''));
    }
}
$rankOverride = trim((string) ($personnelProfile['rank_display_override'] ?? ''));
$rankDisplayRp = trim((string) ($personnelProfile['rank_display'] ?? ''));
$effectiveRankDisplay = $rankOverride !== '' ? $rankOverride : ($rankDisplayRp !== '' ? $rankDisplayRp : $gradeLabel);
$personnelModerationStaffLines = is_array($personnelModerationStaffLines ?? null) ? $personnelModerationStaffLines : [];
$personnelModerationMemberBrief = isset($personnelModerationMemberBrief) && is_string($personnelModerationMemberBrief) && trim($personnelModerationMemberBrief) !== ''
    ? trim($personnelModerationMemberBrief)
    : null;
$seniorityLines = isset($seniorityLines) && is_array($seniorityLines) ? $seniorityLines : [];
?>
<main class="min-h-screen pt-20 pb-24">
    <?php if ($personnelModerationStaffLines !== []): ?>
    <div class="max-w-7xl mx-auto px-6 md:px-8 pt-6">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm" role="region" aria-label="Restrictions d’accès">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Restrictions actuelles (vue encadrement)</p>
            <ul class="mt-2 list-disc pl-5 text-sm text-slate-800 space-y-1">
                <?php foreach ($personnelModerationStaffLines as $line): ?>
                    <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php elseif ($personnelModerationMemberBrief !== null): ?>
    <div class="max-w-7xl mx-auto px-6 md:px-8 pt-6">
        <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 shadow-sm" role="status">
            <?= htmlspecialchars($personnelModerationMemberBrief, ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
    <?php endif; ?>
    <!-- Hero -->
    <section class="w-full bg-slate-900 bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950/30 border-b border-slate-700/50">
        <div class="max-w-7xl mx-auto px-6 md:px-8 py-12 md:py-16">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="space-y-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.4em] text-emerald-400/90 italic">Dossier personnel</p>
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-black uppercase tracking-tighter text-white italic">
                        <?= htmlspecialchars($displayName) ?>
                    </h1>
                    <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                        <?php if ($callsign): ?>
                        <span class="text-lg md:text-xl font-black text-slate-300 italic"><?= htmlspecialchars($callsign) ?></span>
                        <?php endif; ?>
                        <?php if ($matricule && !empty($showMatriculePublic)): ?>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Matricule <?= htmlspecialchars($matricule) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($unitName): ?>
                    <p class="text-sm text-slate-400"><?= htmlspecialchars($unitName) ?></p>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <?php $rawAccountStatus = (string) ($targetUser['status'] ?? ''); ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[10px] font-black uppercase <?= $rawAccountStatus === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-600/30 text-slate-400' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $rawAccountStatus === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' ?>"></span>
                            <?= htmlspecialchars($accountStatusFr($rawAccountStatus)) ?>
                        </span>
                        <?php if ($clearanceLevel): ?>
                        <span class="inline-flex px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-slate-600/30 text-slate-300">Clearance <?= htmlspecialchars($clearanceLevel) ?></span>
                        <?php endif; ?>
                        <?php if (($personnelProfile['deployable'] ?? 1)): ?>
                        <span class="inline-flex px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-400">Déployable</span>
                        <?php endif; ?>
                    </div>
                    <?php if (\App\Core\Session::get('user_id')): ?>
                    <?php $reportUid = (int) ($targetUser['id'] ?? 0); ?>
                    <details class="relative mt-5 max-w-md group">
                        <summary class="flex cursor-pointer list-none items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-center text-[10px] font-black uppercase tracking-[0.18em] text-white shadow-sm backdrop-blur-sm transition hover:border-white/35 hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 [&::-webkit-details-marker]:hidden">
                            <svg class="h-4 w-4 shrink-0 text-rose-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            Signaler un problème
                            <svg class="h-3.5 w-3.5 shrink-0 text-white/70 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </summary>
                        <div class="absolute left-0 top-[calc(100%+0.5rem)] z-30 min-w-[min(100%,17rem)] overflow-hidden rounded-xl border border-white/15 bg-slate-950/95 py-1 shadow-2xl shadow-black/40 backdrop-blur-md ring-1 ring-white/10" role="menu">
                            <button type="button" role="menuitem" data-community-report data-cr-type="member_profile" data-cr-id="<?= $reportUid ?>" data-cr-summary="Signalement concernant cette fiche personnelle." class="flex w-full items-center gap-2 border-b border-white/10 px-4 py-3 text-left text-xs font-semibold text-white transition hover:bg-white/10 focus:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rose-400/60">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400" aria-hidden="true"></span>
                                La fiche affichée
                            </button>
                            <?php if (!empty($avatarUrl)): ?>
                            <button type="button" role="menuitem" data-community-report data-cr-type="profile_picture" data-cr-id="<?= $reportUid ?>" data-cr-summary="Signalement concernant la photo de compte affichée." class="flex w-full items-center gap-2 border-b border-white/10 px-4 py-3 text-left text-xs font-semibold text-white transition hover:bg-white/10 focus:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rose-400/60">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400" aria-hidden="true"></span>
                                La photo de compte
                            </button>
                            <?php endif; ?>
                            <?php if (!empty($portraitUrl)): ?>
                            <button type="button" role="menuitem" data-community-report data-cr-type="operator_visual" data-cr-id="<?= $reportUid ?>" data-cr-summary="Signalement concernant le portrait opérateur affiché." class="flex w-full items-center gap-2 px-4 py-3 text-left text-xs font-semibold text-white transition hover:bg-white/10 focus:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rose-400/60">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400" aria-hidden="true"></span>
                                Le portrait opérateur
                            </button>
                            <?php endif; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-4 md:gap-6">
                    <div class="relative w-20 h-20 md:w-24 md:h-24 rounded-2xl overflow-hidden border-2 border-slate-600/50 bg-slate-800 flex-shrink-0" title="Avatar compte" x-data="{ ready: false }">
                        <?php if ($avatarUrl): ?>
                        <div class="absolute inset-0 z-0 bg-slate-700 animate-pulse" x-show="!ready" x-transition.opacity.duration.200ms></div>
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" loading="eager" decoding="async" draggable="false" width="96" height="96" @load="ready = true" class="relative z-[1] h-full w-full object-cover transition-opacity duration-300" :class="ready ? 'opacity-100' : 'opacity-0'" />
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-500">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="relative w-20 h-20 md:w-24 md:h-24 rounded-2xl overflow-hidden border-2 border-slate-600/50 bg-slate-800 flex-shrink-0" title="Portrait opérateur" x-data="{ ready: false }">
                        <?php if ($portraitUrl): ?>
                        <div class="absolute inset-0 z-0 bg-slate-700 animate-pulse" x-show="!ready" x-transition.opacity.duration.200ms></div>
                        <img src="<?= htmlspecialchars($portraitUrl) ?>" alt="Portrait opérateur" loading="eager" decoding="async" draggable="false" width="96" height="96" @load="ready = true" class="relative z-[1] h-full w-full object-cover transition-opacity duration-300" :class="ready ? 'opacity-100' : 'opacity-0'" />
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-500">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Complétude -->
    <section class="w-full border-b border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto px-6 md:px-8 py-4">
            <div class="flex flex-wrap items-center gap-4">
                <span class="text-sm font-black text-slate-700">Profil complété à <?= $completenessScore ?>%</span>
                <div class="w-32 h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: <?= min(100, max(0, $completenessScore)) ?>%"></div>
                </div>
                <?php if (!empty($sectionsCritiques) && $canEditProfile): ?>
                <span class="text-xs text-amber-700 font-semibold"><?= count($sectionsCritiques) ?> section(s) critique(s) incomplète(s) : <?= htmlspecialchars(implode(', ', $sectionsCritiques)) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Récap -->
    <section class="w-full border-b border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto px-6 md:px-8 py-6">
            <div class="flex flex-wrap gap-6 md:gap-10">
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Rang</p>
                    <p class="text-sm font-black text-slate-900 italic"><?= $effectiveRankDisplay !== '' ? htmlspecialchars($effectiveRankDisplay) : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Unité</p>
                    <p class="text-sm font-black text-slate-900 italic"><?= $unitName ? htmlspecialchars($unitName) : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Habilitation</p>
                    <p class="text-sm font-black text-emerald-600 italic"><?= $clearanceLevel ? htmlspecialchars($clearanceLevel) : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Readiness</p>
                    <p class="text-sm font-black text-slate-900"><?= $readiness !== null ? $readiness . '%' : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Statut réseau</p>
                    <p class="text-sm font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?> italic"><?= htmlspecialchars($accountStatusFr((string) ($targetUser['status'] ?? ''))) ?></p>
                </div>
                <?php if ($enlistmentFormatted): ?>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Enrôlement</p>
                    <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($enlistmentFormatted) ?></p>
                </div>
                <?php endif; ?>
                <?php foreach ($seniorityLines as $seniorityRow): ?>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5"><?= htmlspecialchars((string) ($seniorityRow['label'] ?? 'Ancienneté')) ?></p>
                    <p class="text-sm font-black text-slate-900"><?= htmlspecialchars((string) ($seniorityRow['formatted'] ?? '—')) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-6 md:px-8 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            <!-- Sidebar -->
            <aside class="lg:col-span-3 lg:sticky lg:top-32 h-fit order-2 lg:order-1 space-y-6">
                <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-2">Photo de compte</p>
                    <div class="aspect-square max-w-[140px] bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 mb-4">
                        <?php if ($avatarUrl): ?>
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" class="w-full h-full object-cover" loading="lazy" decoding="async" />
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-300"><svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
                        <?php endif; ?>
                    </div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-2">Portrait opérateur</p>
                    <div class="aspect-[3/4] max-w-[140px] bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 mb-4">
                        <?php if ($portraitUrl): ?>
                        <img src="<?= htmlspecialchars($portraitUrl) ?>" alt="Portrait" class="w-full h-full object-cover" loading="lazy" decoding="async" />
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-300"><svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-3">
                        <?php if (!empty($showMatriculePublic)): ?>
                        <div>
                            <p class="text-[7px] font-black text-slate-400 tracking-[0.3em] mb-0.5 uppercase">Matricule</p>
                            <?php if ($matricule): ?>
                            <p class="text-base font-black text-slate-900"><?= htmlspecialchars($matricule) ?></p>
                            <?php elseif ($canEditProfile): ?>
                            <form method="post" action="<?= url('personnel/' . (int)$targetUser['id'] . '/generate-matricule') ?>"><?= \App\Core\Csrf::field() ?><button type="submit" class="text-[9px] font-black uppercase text-emerald-600 hover:text-emerald-700">Générer</button></form>
                            <?php else: ?>
                            <p class="text-xs text-slate-400 italic">Non attribué</p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($callsign): ?>
                        <div>
                            <p class="text-[7px] font-black text-slate-400 uppercase mb-0.5">Callsign</p>
                            <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($callsign) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($unitName): ?>
                        <div>
                            <p class="text-[7px] font-black text-slate-400 uppercase mb-0.5">Unité</p>
                            <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($unitName) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($canEditProfile): ?>
                <div class="flex flex-col gap-2">
                    <a href="<?= url('personnel/' . (int)$targetUser['id'] . '/edit') ?>" class="text-[9px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-700">Éditer le dossier</a>
                    <a href="<?= url('account/image') ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-600 hover:text-slate-900">Photo de compte</a>
                    <a href="<?= url('account/portrait') ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-600 hover:text-slate-900">Portrait opérateur</a>
                </div>
                <?php endif; ?>
                <a href="<?= url('orbat') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Voir ORBAT</a>
                <a href="<?= url('documents') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Documents</a>
                <?php if ($viewerIsPersonnelSubject): ?>
                <a href="<?= url('formations/mes-formations') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Mes parcours</a>
                <?php endif; ?>
                <a href="<?= url('formations') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Formations</a>
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Dashboard</a>
            </aside>

            <div class="lg:col-span-9 space-y-8 order-1 lg:order-2">
                <!-- Identité opérationnelle -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Identité opérationnelle</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nom opérateur</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($displayName) ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Indicatif radio</p><p class="text-sm font-black text-slate-900"><?= $callsign ? htmlspecialchars($callsign) : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Matricule</p><p class="text-sm font-black text-slate-900"><?= !empty($showMatriculePublic) ? ($matricule ? htmlspecialchars($matricule) : '—') : '—' ?></p></div>
                        <?php if ($gradeLabel !== '' && $gradeLabel !== $effectiveRankDisplay): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Grade de référence</p><p class="text-sm text-slate-700"><?= htmlspecialchars($gradeLabel) ?></p></div>
                        <?php endif; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle principal</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['primary_role'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle secondaire</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['secondary_role'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Unité</p><p class="text-sm font-black text-slate-900"><?= $unitName ? htmlspecialchars($unitName) : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Chef direct</p><p class="text-sm font-black text-slate-900"><?= $commander ? htmlspecialchars($commander['display_name'] ?? $commander['callsign'] ?? '') : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date d'incorporation</p><p class="text-sm font-black text-slate-900"><?= $enlistmentFormatted ?? '—' ?></p></div>
                    </div>
                    <?php
                    $rpMotto = trim((string) ($personnelProfile['motto'] ?? ''));
                    $rpBlood = trim((string) ($personnelProfile['blood_type'] ?? ''));
                    $rpLangs = trim((string) ($personnelProfile['languages'] ?? ''));
                    $rpNat = trim((string) ($personnelProfile['nationality'] ?? ''));
                    $rpExtra = $rpMotto !== '' || $rpBlood !== '' || $rpLangs !== '' || $rpNat !== '';
                    ?>
                    <?php if ($rpExtra): ?>
                    <div class="mt-8 border-t border-slate-100 pt-6">
                        <h3 class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500 mb-4">Détails RP (dossier opérationnel)</h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php if ($rpMotto !== ''): ?>
                            <div class="md:col-span-2"><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Devise / motto</p><p class="text-sm font-semibold text-slate-800 italic"><?= htmlspecialchars($rpMotto) ?></p></div>
                            <?php endif; ?>
                            <?php if ($rpBlood !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Groupe sanguin</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($rpBlood) ?></p></div>
                            <?php endif; ?>
                            <?php if ($rpLangs !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Langues (RP)</p><p class="text-sm text-slate-800"><?= htmlspecialchars($rpLangs) ?></p></div>
                            <?php endif; ?>
                            <?php if ($rpNat !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nationalité (RP)</p><p class="text-sm text-slate-800"><?= htmlspecialchars($rpNat) ?></p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>

                <?php if ($personnelJobRoleAssignments !== []): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Rôles métier (référentiel)</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?php foreach ($personnelJobRoleAssignments as $jr):
                            $jrName = trim((string) ($jr['role_name'] ?? ''));
                            $jrDetail = trim((string) ($jr['role_detail'] ?? ''));
                            $jrLabel = $jrDetail !== '' && $jrName !== '' ? $jrName . ' — ' . $jrDetail : ($jrName !== '' ? $jrName : $jrDetail);
                            $jrPrimary = !empty($jr['is_primary']);
                            ?>
                        <div class="rounded-2xl border border-slate-200 p-5 bg-slate-50/50">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-[9px] font-black uppercase tracking-wider <?= $jrPrimary ? 'text-emerald-700' : 'text-slate-500' ?>"><?= $jrPrimary ? 'Rôle principal' : 'Rôle complémentaire' ?></span>
                            </div>
                            <p class="text-sm font-black text-slate-900"><?= $jrLabel !== '' ? htmlspecialchars($jrLabel) : '—' ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Affectations actives</h2>
                    <?php if ($assignments === []): ?>
                    <p class="text-sm text-slate-600">Aucune affectation d’unité enregistrée pour l’instant<?= $unitName || $primaryUnitFallbackName ? ' — l’unité indiquée dans le dossier peut provenir d’une saisie manuelle.' : '.' ?></p>
                    <?php if ($enlistmentFormatted): ?>
                    <p class="text-xs text-slate-500 mt-3">Date d’enrôlement : <span class="font-semibold text-slate-700"><?= htmlspecialchars($enlistmentFormatted) ?></span></p>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($assignments as $asg):
                            $asgUnit = trim((string) ($asg['unit_name'] ?? ''));
                            $asgRole = trim((string) ($asg['role_name'] ?? ''));
                            $asgPrimary = !empty($asg['is_primary']);
                            $cmdId = (int) ($asg['commander_user_id'] ?? 0);
                            $cmdLabel = $cmdId > 0 ? ($commanderLabelsById[$cmdId] ?? '—') : '—';
                            ?>
                        <div class="rounded-2xl border border-slate-200 p-5 flex flex-col gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <?php if ($asgPrimary): ?>
                                <span class="inline-flex rounded-md bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-900">Affectation principale</span>
                                <?php else: ?>
                                <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">Affectation complémentaire</span>
                                <?php endif; ?>
                            </div>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Unité</p><p class="text-sm font-black text-slate-900"><?= $asgUnit !== '' ? htmlspecialchars($asgUnit) : '—' ?></p></div>
                                <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Fonction dans l’équipe</p><p class="text-sm font-black text-slate-900"><?= $asgRole !== '' ? htmlspecialchars($asgRole) : '—' ?></p></div>
                                <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Chef d’unité</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($cmdLabel) ?></p></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($enlistmentFormatted): ?>
                    <p class="text-xs text-slate-500 mt-6 pt-6 border-t border-slate-100">Date d’enrôlement : <span class="font-semibold text-slate-700"><?= htmlspecialchars($enlistmentFormatted) ?></span></p>
                    <?php endif; ?>
                    <?php endif; ?>
                </section>

                <?php if ($personnelPlanningEntries !== []): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900">Engagements (tableau opérationnel)</h2>
                            <p class="text-sm text-slate-600 mt-2 max-w-2xl">Créneaux et activités auxquels cette personne est affectée dans la période en cours.</p>
                        </div>
                        <?php if ($canViewOperationalBoardLink): ?>
                        <a href="<?= htmlspecialchars(url('back-office/tableau-operationnel')) ?>" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-[10px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50 transition">Ouvrir le tableau</a>
                        <?php endif; ?>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?php foreach ($personnelPlanningEntries as $pe):
                            $peTitle = trim((string) ($pe['title'] ?? 'Activité'));
                            $peType = $planningEntryTypeFr((string) ($pe['entry_type'] ?? ''));
                            $peOp = isset($pe['operational_status']) ? $planningOperationalStatusFr((string) $pe['operational_status']) : '';
                            $peStart = !empty($pe['start_date']) ? date('d/m/Y', strtotime((string) $pe['start_date'])) : null;
                            $peEnd = !empty($pe['end_date']) ? date('d/m/Y', strtotime((string) $pe['end_date'])) : null;
                            $peRole = trim((string) ($pe['personnel_role_label'] ?? ''));
                            $peLead = !empty($pe['personnel_is_lead']);
                            ?>
                        <div class="rounded-2xl border border-slate-200 p-5 flex flex-col gap-2 bg-slate-50/40">
                            <p class="text-sm font-black text-slate-900 leading-snug"><?= htmlspecialchars($peTitle) ?></p>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($peType) ?><?php if ($peOp !== ''): ?> · <?= htmlspecialchars($peOp) ?><?php endif; ?></p>
                            <?php if ($peStart || $peEnd): ?>
                            <p class="text-xs text-slate-700"><?php if ($peStart && $peEnd): ?>Du <?= htmlspecialchars($peStart) ?> au <?= htmlspecialchars($peEnd) ?><?php elseif ($peStart): ?>À partir du <?= htmlspecialchars($peStart) ?><?php else: ?>Jusqu’au <?= htmlspecialchars($peEnd) ?><?php endif; ?></p>
                            <?php endif; ?>
                            <?php if ($peRole !== '' || $peLead): ?>
                            <p class="text-xs text-slate-600"><?php if ($peLead): ?><span class="font-semibold text-slate-800">Responsable désigné</span><?php if ($peRole !== ''): ?> — <?php endif; ?><?php endif; ?><?php if ($peRole !== ''): ?><?= htmlspecialchars($peRole) ?><?php endif; ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Sécurité / habilitation -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Sécurité / habilitation</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Niveau documentaire</p><p class="text-sm font-black text-emerald-600"><?= $clearanceLevel ? htmlspecialchars($clearanceLevel) : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date dernière revue</p><p class="text-sm font-black text-slate-900"><?= !empty($personnelProfile['clearance_reviewed_at']) ? date('d/m/Y', strtotime($personnelProfile['clearance_reviewed_at'])) : '—' ?></p></div>
                    </div>
                </section>

                <!-- Formations Athena, attestations, qualifications dossier -->
                <?php
                $hasLmsSummary = $lmsEnrollmentsForPersonnel !== [] || $trainingCertificates !== [];
                $hasDossierQuals = $qualifications !== [] || (bool) $specializations;
                ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
                        <div>
                            <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900">Formations, attestations et certifications</h2>
                            <p class="text-sm text-slate-600 mt-2 max-w-2xl leading-relaxed">Parcours Athena (suivi et réussites), documents délivrés pour les modules certifiants, et qualifications saisies dans <?= $viewerIsPersonnelSubject ? 'votre dossier' : 'ce dossier' ?>.</p>
                        </div>
                        <?php if ($viewerIsPersonnelSubject): ?>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= htmlspecialchars(url('formations/mes-formations')) ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-[10px] font-black uppercase tracking-wider text-white hover:bg-emerald-700 transition">Mes parcours</a>
                            <a href="<?= htmlspecialchars(url('formations')) ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-[10px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50 transition">Catalogue</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($lmsEnrollmentsForPersonnel !== []): ?>
                    <div class="mb-10">
                        <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500 mb-4">Parcours et suivi</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <?php foreach ($lmsEnrollmentsForPersonnel as $enr):
                                $est = (string) ($enr['status'] ?? '');
                                $pct = (int) round((float) ($enr['progress_percent'] ?? 0));
                                $slugEnr = trim((string) ($enr['course_slug'] ?? ''));
                                $courseLink = $slugEnr !== '' ? url('formations/' . rawurlencode($slugEnr)) : url('formations');
                                $showProgress = !in_array($est, ['revoked', 'expired', 'withdrawn', 'pending_approval'], true);
                                ?>
                            <div class="rounded-2xl border border-slate-200 p-5 flex flex-col gap-3 bg-slate-50/40">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-black text-slate-900 leading-snug">
                                            <a href="<?= htmlspecialchars($courseLink) ?>" class="hover:text-emerald-700"><?= htmlspecialchars((string) ($enr['course_title'] ?? 'Formation')) ?></a>
                                        </p>
                                        <?php if (!empty($enr['is_certifying'])): ?>
                                        <span class="inline-flex mt-1.5 rounded-md bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-900">Certifiant</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="shrink-0 inline-flex rounded-full bg-white px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wide text-slate-600 ring-1 ring-slate-200"><?= htmlspecialchars($lmsEnrollmentStatusFr($est)) ?></span>
                                </div>
                                <?php if ($showProgress): ?>
                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-slate-500 mb-1">
                                        <span>Progression</span>
                                        <span class="tabular-nums"><?= $pct ?> %</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" style="width: <?= min(100, max(0, $pct)) ?>%"></div>
                                    </div>
                                </div>
                                <?php elseif ($est === 'pending_approval'): ?>
                                <p class="text-xs text-slate-600">Demande transmise — un formateur doit valider l’accès au parcours.</p>
                                <?php elseif ($est === 'completed'): ?>
                                <p class="text-xs font-semibold text-emerald-800">Parcours terminé<?= $pct >= 100 ? '' : ' — progression ' . $pct . ' %' ?>.</p>
                                <?php endif; ?>
                                <?php if (!empty($enr['expires_at']) && !in_array($est, ['completed', 'revoked', 'expired', 'withdrawn'], true)): ?>
                                <p class="text-[10px] text-slate-500">Échéance <?= date('d/m/Y', strtotime((string) $enr['expires_at'])) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($trainingCertificates !== []): ?>
                    <div class="mb-10">
                        <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500 mb-4">Attestations et certifications (parcours Athena)</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <?php foreach ($trainingCertificates as $cert):
                                $cst = (string) ($cert['status'] ?? 'valid');
                                $certId = (int) ($cert['id'] ?? 0);
                                $canOpenAttestation = $viewerIsPersonnelSubject && $cst === 'valid' && $certId > 0;
                                ?>
                            <div class="rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 to-white p-5 flex flex-col gap-2">
                                <span class="text-[9px] font-black uppercase tracking-widest text-emerald-800"><?= htmlspecialchars((string) ($cert['course_title'] ?? 'Formation')) ?></span>
                                <p class="text-xs text-slate-700">
                                    <span class="font-bold text-slate-900"><?= htmlspecialchars($lmsCertificateStatusFr($cst)) ?></span>
                                    <?php if (!empty($cert['issued_at'])): ?>
                                    <span class="text-slate-500"> · Délivré le <?= date('d/m/Y', strtotime((string) $cert['issued_at'])) ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php
                                $num = trim((string) ($cert['certificate_number'] ?? ''));
                                if ($num !== ''):
                                ?>
                                <p class="text-[10px] text-slate-600">Référence document : <span class="font-mono font-semibold"><?= htmlspecialchars($num) ?></span></p>
                                <?php endif; ?>
                                <?php if (isset($cert['final_score']) && $cert['final_score'] !== null && $cert['final_score'] !== ''): ?>
                                <p class="text-[10px] text-slate-600">Résultat final : <?= htmlspecialchars(number_format((float) $cert['final_score'], 1, ',', ' ')) ?> %</p>
                                <?php endif; ?>
                                <?php if (!empty($cert['expires_at'])): ?>
                                <p class="text-[10px] text-amber-800">Validité jusqu’au <?= date('d/m/Y', strtotime((string) $cert['expires_at'])) ?></p>
                                <?php endif; ?>
                                <?php if ($canOpenAttestation): ?>
                                <a href="<?= htmlspecialchars(url('formations/certificate/' . $certId)) ?>" class="mt-2 inline-flex w-fit items-center rounded-xl bg-emerald-600 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-emerald-700 transition">Voir l’attestation</a>
                                <?php elseif (!$viewerIsPersonnelSubject && $cst === 'valid'): ?>
                                <p class="text-[10px] text-slate-500 italic mt-1">La consultation du document est réservée au titulaire du compte.</p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasDossierQuals): ?>
                    <div>
                        <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500 mb-4">Qualifications enregistrées dans le dossier</h3>
                        <div class="flex flex-wrap gap-4">
                            <?php if ($specializations): ?>
                            <div class="px-6 py-4 border border-slate-200 rounded-2xl flex flex-col gap-2 min-w-[200px]">
                                <span class="text-[7px] font-black tracking-widest text-slate-400 uppercase">Spécialisations</span>
                                <p class="text-xs font-bold text-slate-900 leading-relaxed"><?= nl2br(htmlspecialchars($specializations)) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php foreach ($qualifications as $q): ?>
                            <div class="px-6 py-4 border border-slate-200 rounded-2xl flex flex-col gap-2">
                                <span class="text-[7px] font-black tracking-widest text-slate-400 uppercase"><?= htmlspecialchars($q['qualification_name']) ?></span>
                                <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars((string) ($q['level'] ?? '')) ?> — <?= htmlspecialchars($qualificationStatusFr((string) ($q['status'] ?? ''))) ?></p>
                                <?php if (!empty($q['expires_at'])): ?><p class="text-[10px] text-slate-500">Expire <?= date('d/m/Y', strtotime($q['expires_at'])) ?></p><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$hasLmsSummary && !$hasDossierQuals): ?>
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-8 text-center">
                        <p class="text-sm text-slate-600">Aucune formation ni qualification renseignée pour l’instant.</p>
                        <?php if ($viewerIsPersonnelSubject): ?>
                        <a href="<?= htmlspecialchars(url('formations')) ?>" class="mt-4 inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-[10px] font-black uppercase tracking-wider text-white hover:bg-emerald-700 transition">Découvrir le catalogue</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Équipement / dotation -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Équipement / dotation</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Classe d'équipement</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['equipment_class'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Kit assigné</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['kit_assigned'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Radio</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['radio_assigned'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Véhicule autorisé</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['vehicle_authorized'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Spécialité armement</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['weapon_specialty'] ?? '—') ?></p></div>
                    </div>
                </section>

                <!-- Readiness -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6 flex items-center justify-between">
                        Operational Readiness
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black italic"><?= $readiness !== null ? $readiness : 0 ?>%</span>
                            <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: <?= min(100, max(0, $readiness ?? 0)) ?>%"></div>
                            </div>
                        </div>
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <?php if ($flightHours !== null && $flightHours !== ''): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase mb-1">Heures de vol</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars((string)$flightHours) ?></p></div>
                        <?php endif; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase mb-1">Déployable</p><p class="text-sm font-black text-slate-900"><?= ($personnelProfile['deployable'] ?? 1) ? 'Oui' : 'Non' ?></p></div>
                    </div>
                </section>

                <!-- Historique de service -->
                <?php if (!empty($serviceHistory)): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Historique de service</h2>
                    <div class="space-y-4">
                        <?php foreach ($serviceHistory as $event): ?>
                        <div class="flex gap-4 border-l-2 border-slate-200 pl-4 py-1">
                            <span class="text-[10px] font-mono text-slate-500 shrink-0"><?= date('Y-m', strtotime($event['event_date'])) ?></span>
                            <div>
                                <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($event['title']) ?></p>
                                <?php if (!empty($event['description'])): ?><p class="text-xs text-slate-600"><?= nl2br(htmlspecialchars($event['description'])) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Notes de commandement -->
                <?php if ($canViewCommandNotes): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6 flex items-center gap-3">
                        <span class="w-1.5 h-1.5 bg-rose-500 rounded-full"></span>
                        Notes de commandement <?= $canEditNotes ? '(éditable)' : '' ?>
                    </h2>
                    <?php if ($canEditNotes): ?>
                    <form method="post" action="<?= url('personnel/' . (int)$targetUser['id'] . '/notes') ?>" class="space-y-4">
                        <?= \App\Core\Csrf::field() ?>
                        <textarea name="admin_notes" rows="4" class="w-full text-xs text-slate-700 border border-slate-200 rounded-xl p-4 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500" placeholder="Notes internes (visible par vous et les admins)"><?= $adminNotes ? htmlspecialchars($adminNotes) : '' ?></textarea>
                        <button type="submit" class="text-[9px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-700 border border-emerald-500/50 rounded-lg px-4 py-2">Enregistrer</button>
                    </form>
                    <?php else: ?>
                    <p class="text-xs text-slate-500 font-medium leading-relaxed italic"><?= $adminNotes ? nl2br(htmlspecialchars($adminNotes)) : '— Aucune note enregistrée.' ?></p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <!-- Identité civile / administrative -->
                <?php if ($canViewCivilSection): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-2">Identité civile / administrative</h2>
                    <?php if ($civilSourceLabel): ?>
                    <p class="text-[10px] text-slate-500 mb-6">Source prénom / nom : <span class="font-semibold text-slate-700"><?= htmlspecialchars($civilSourceLabel) ?></span><?php if (($civilIdentity['source'] ?? null) === 'enlistment' && $latestEnlistment): ?> (candidature #<?= (int) ($latestEnlistment['id'] ?? 0) ?>, <?= htmlspecialchars((string) ($latestEnlistment['status'] ?? '')) ?>)<?php endif; ?>.</p>
                    <?php else: ?>
                    <p class="text-[10px] text-slate-500 mb-6">Renseignez le prénom et le nom dans <a href="<?= url('account/preferences') ?>" class="font-semibold text-emerald-700 underline">Compte → Préférences</a> pour alimenter la fiche.</p>
                    <?php endif; ?>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Prénom</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($civilIdentity['first_name'] !== '' ? $civilIdentity['first_name'] : '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nom</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($civilIdentity['last_name'] !== '' ? $civilIdentity['last_name'] : '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">E-mail</p><p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($targetUser['email']) ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut compte</p><p class="text-sm font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?>"><?= htmlspecialchars($accountStatusFr((string) ($targetUser['status'] ?? ''))) ?></p></div>
                        <?php if (!empty($userProfile['birth_date'])): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date de naissance</p><p class="text-sm text-slate-800"><?= htmlspecialchars((string) $userProfile['birth_date']) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['nationality'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nationalité (dossier)</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['nationality'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['phone'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Téléphone</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['phone'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['timezone'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Fuseau</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['timezone'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['language'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Langue</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['language'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['bio'] ?? '')))): ?>
                        <div class="md:col-span-2"><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Bio (compte)</p><p class="text-sm text-slate-700 leading-relaxed"><?= nl2br(htmlspecialchars(trim((string) $userProfile['bio']))) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty($targetUser['last_login_at'])): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Dernière connexion</p><p class="text-sm text-slate-700"><?= date('d/m/Y H:i', strtotime($targetUser['last_login_at'])) ?></p></div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Contact -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Contact</h2>
                    <div class="space-y-4">
                        <?php if (!empty($showEmailInContact)): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">E-mail Ops</p><p class="text-[11px] font-bold text-slate-900 italic"><?= htmlspecialchars($targetUser['email']) ?></p></div>
                        <?php else: ?>
                        <p class="text-[11px] text-slate-500 italic">E-mail masqué par les préférences de visibilité du titulaire.</p>
                        <?php endif; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut Réseau</p><p class="text-[11px] font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?> italic"><?= htmlspecialchars($accountStatusFr((string) ($targetUser['status'] ?? ''))) ?></p></div>
                    </div>
                </section>

                <?php foreach ($adminPanels as $panel): ?>
                <?php $panelId = (int)$panel['id']; $data = $adminDataByPanel[$panelId] ?? []; ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6"><?= htmlspecialchars($panel['name']) ?></h2>
                    <?php if (empty($data)): ?>
                    <p class="text-[10px] text-slate-400 italic uppercase tracking-wider">Non renseigné</p>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($data as $key => $value): ?>
                        <?php if ($value === null || $value === '') continue; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase mb-1"><?= htmlspecialchars(is_string($key) ? $key : 'Champ') ?></p><p class="text-[11px] font-bold text-slate-900"><?= nl2br(htmlspecialchars(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value)) ?></p></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
