<?php
$baseUrl = url('');
$targetUser = $targetUser ?? null;
$personnelExtras = $personnelExtras ?? [];
$personnelProfile = $personnelProfile ?? null;
$userProfile = $userProfile ?? null;
$grade = $grade ?? null;
$grades = $grades ?? [];
$assignments = $assignments ?? [];
$personnelAssignmentHistory = is_array($personnelAssignmentHistory ?? null) ? $personnelAssignmentHistory : [];
$personnelAssignmentHistoryUnitTotals = is_array($personnelAssignmentHistoryUnitTotals ?? null) ? $personnelAssignmentHistoryUnitTotals : [];
$histUnitPeriodCount = [];
foreach ($personnelAssignmentHistory as $_hx) {
    $_uid = (int) ($_hx['unit_id'] ?? 0);
    if ($_uid > 0) {
        $histUnitPeriodCount[$_uid] = ($histUnitPeriodCount[$_uid] ?? 0) + 1;
    }
}
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
$privatePersonnelIdentity = !empty($privatePersonnelIdentity ?? false);
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
$communityRoleLabelRaw = isset($communityRoleLabel) && is_string($communityRoleLabel) ? trim($communityRoleLabel) : '';
$communityRoleLabel = $communityRoleLabelRaw !== '' ? $communityRoleLabelRaw : null;
$qualificationIssuerLabels = is_array($qualificationIssuerLabels ?? null) ? $qualificationIssuerLabels : [];
$personnelOrgHistory = is_array($personnelOrgHistory ?? null) ? $personnelOrgHistory : [];
$personnelOrgHistorySection = !empty($personnelOrgHistorySection ?? null);
$personnelOrgHistorySchemaReady = !empty($personnelOrgHistorySchemaReady ?? null);
$roleplayFollowupConfig = is_array($roleplayFollowupConfig ?? null) ? $roleplayFollowupConfig : ['enabled' => false, 'optional' => false];
$roleplayEligibility = is_array($roleplayEligibility ?? null) ? $roleplayEligibility : ['eligible' => false, 'checks' => []];
$rpTutorLabel = isset($rpTutorLabel) && is_string($rpTutorLabel) ? trim($rpTutorLabel) : null;
$roleplayTimelineEvents = is_array($roleplayTimelineEvents ?? null) ? $roleplayTimelineEvents : [];

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
        'expiring' => 'Bientôt périmée',
        'expired' => 'Expirée',
        'in_progress' => 'En cours d’obtention',
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
$lmsEnrollmentAssignTypeFr = static function (?string $t): string {
    return match (trim((string) $t)) {
        'manual' => 'Attribution manuelle',
        'role' => 'Selon le rôle',
        'unit' => 'Selon l’unité',
        'campaign' => 'Campagne',
        'self_enroll' => 'Inscription volontaire',
        default => '',
    };
};
$personnelAssignmentStatusFr = static function (?string $t): string {
    return match (trim((string) $t)) {
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'En attente',
        default => '',
    };
};
$unitTypeFr = static function (?string $t): string {
    return match (trim((string) $t)) {
        'hq' => 'État-major',
        'company' => 'Compagnie',
        'platoon' => 'Peloton',
        'squad' => 'Escouade',
        'team' => 'Équipe',
        'section' => 'Section',
        'other' => 'Autre formation',
        default => '',
    };
};
$personnelDurationDaysFr = static function (int $days): string {
    if ($days < 1) {
        return '—';
    }
    if ($days === 1) {
        return '1 jour';
    }

    return $days . ' jours';
};
$serviceHistoryEventTypeFr = static function (?string $t): string {
    return match (trim((string) $t)) {
        'assignment' => 'Affectation',
        'promotion' => 'Avancement',
        'qualification' => 'Qualification',
        'deployment' => 'Déploiement',
        'award' => 'Distinction',
        'discipline' => 'Suivi disciplinaire',
        'note' => 'Note de dossier',
        default => 'Événement',
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
$athenaIdentifier = trim((string) ($targetUser['athena_identifier'] ?? ''));
$rpCharacterName = trim((string) ($personnelProfile['character_name'] ?? ''));
if ($rpCharacterName !== '') {
    $displayName = $rpCharacterName;
} elseif (!empty($redactPersonalPresentation)) {
    $dn = trim((string) ($targetUser['display_name'] ?? ''));
    if ($privatePersonnelIdentity) {
        $displayName = $dn !== '' ? $dn : (string) ($targetUser['email'] ?? '—');
    } else {
        $cs = trim((string) ($callsign ?? ''));
        $displayName = $dn !== '' ? $dn : ($cs !== '' ? $cs : 'Membre');
    }
} elseif (!$privatePersonnelIdentity) {
    $dn = trim((string) ($targetUser['display_name'] ?? ''));
    $cs = trim((string) ($callsign ?? ''));
    $displayName = $dn !== '' ? $dn : ($cs !== '' ? $cs : 'Membre');
} else {
    $civilFull = trim(($civilIdentity['first_name'] ?? '') . ' ' . ($civilIdentity['last_name'] ?? ''));
    $displayName = $civilFull !== '' ? $civilFull : ($targetUser['display_name'] ?: $targetUser['email']);
}
$rScore = (int) ($personnelProfile['readiness_score'] ?? 0);
$rExtra = (int) ($personnelExtras['readiness_percent'] ?? 0);
$readinessMerged = max($rScore, $rExtra);
$readiness = $readinessMerged > 0 ? $readinessMerged : null;
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

$publicFlagCodeRaw = strtoupper(trim((string) ($userProfile['public_flag_country_code'] ?? '')));
$publicFlagCode = ($publicFlagCodeRaw !== '' && \App\Support\Profile\PublicFlagCountryCatalog::isAllowed($publicFlagCodeRaw))
    ? $publicFlagCodeRaw
    : null;
$publicFlagEmoji = $publicFlagCode !== null ? \App\Support\Profile\PublicFlagCountryCatalog::flagEmoji($publicFlagCode) : '';

$unitName = $primaryAssignment['unit_name'] ?? $primaryUnitFallbackName ?? ($personnelExtras['squadron'] ?? null);
$heroFlagLine = '';
if ($publicFlagCode !== null) {
    $heroTail = trim((string) ($unitName ?? ''));
    if ($heroTail === '') {
        $heroTail = trim((string) ($callsign ?? ''));
    }
    if ($heroTail === '') {
        $heroTail = trim((string) ($displayName ?? ''));
    }
    if ($heroTail === '') {
        $heroTail = 'Opérateur';
    }
    $heroFlagLine = '[' . $publicFlagCode . '] ' . $heroTail;
}
$enlistmentDate = $personnelProfile['enlistment_date'] ?? $personnelExtras['date_of_enlistment'] ?? null;
$rpProgress = isset($personnelProfile['rp_followup_progress']) && $personnelProfile['rp_followup_progress'] !== null
    ? max(0, min(100, (int) $personnelProfile['rp_followup_progress']))
    : null;
$rpStage = trim((string) ($personnelProfile['rp_followup_stage'] ?? ''));
$rpStatus = trim((string) ($personnelProfile['rp_followup_status'] ?? ''));
$rpTrack = trim((string) ($personnelProfile['rp_recruitment_stream'] ?? ''));
$rpFunction = trim((string) ($personnelProfile['rp_operational_function'] ?? ''));
$rpOriginRaw = trim((string) ($personnelProfile['rp_recruitment_origin'] ?? ''));
$rpOriginLabel = match ($rpOriginRaw) {
    'internal' => 'Interne',
    'external' => 'Externe',
    default => '',
};
$rpNotes = trim((string) ($personnelProfile['rp_followup_notes'] ?? ''));
$rpDateFr = static function (?string $date): ?string {
    $raw = trim((string) $date);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw);
    if (!$ts) {
        return null;
    }

    return date('d/m/Y', $ts);
};
$rpTimelineCards = [
    [
        'title' => 'Prochain entretien individuel',
        'date' => $rpDateFr((string) ($personnelProfile['rp_next_interview_date'] ?? '')),
        'fallback' => 'À planifier',
        'accent' => 'border-emerald-300 bg-emerald-50/60',
    ],
    [
        'title' => 'Visite médicale',
        'date' => $rpDateFr((string) ($personnelProfile['rp_medical_due_date'] ?? '')),
        'fallback' => 'Échéance non renseignée',
        'accent' => 'border-slate-200 bg-slate-50/70',
    ],
    [
        'title' => 'Rotation de service',
        'date' => $rpDateFr((string) ($personnelProfile['rp_service_rotation_date'] ?? '')),
        'fallback' => 'Non planifiée',
        'accent' => 'border-slate-200 bg-slate-50/70',
    ],
];
$rpTimelineStatusFr = static function (?string $s): string {
    return match (trim((string) $s)) {
        'planned' => 'Prévu',
        'completed' => 'Terminé',
        'blocked' => 'Bloqué',
        'cancelled' => 'Annulé',
        default => '—',
    };
};
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
$seniorityGlobal = isset($seniorityGlobal) && is_array($seniorityGlobal) ? $seniorityGlobal : null;
$seniorityDetailLines = isset($seniorityDetailLines) && is_array($seniorityDetailLines) ? $seniorityDetailLines : [];
$personnelIsSelf = !empty($personnelIsSelf ?? null);
$steamProfileSyncOffered = !empty($steamProfileSyncOffered ?? false);

$completenessDetails = is_array($completeness['details'] ?? null) ? $completeness['details'] : [];
$completenessCheckLabels = [
    'identity_name' => 'Nom affiché dossier personnage',
    'identity_callsign' => 'Indicatif radio',
    'identity_matricule' => 'Matricule',
    'identity_role' => 'Rôle principal (dossier)',
    'identity_unit' => 'Unité ou affectation',
    'identity_enlistment' => 'Date d’incorporation',
    'assignment_role' => 'Rôle dans l’organigramme',
    'security_clearance' => 'Niveau documentaire (clearance)',
    'security_review' => 'Revue de l’habilitation (date)',
    'qualifications' => 'Qualification ou formation certifiée',
    'readiness' => 'Indicateur de disponibilité',
    'contact_email' => 'Adresse e-mail de contact',
    'civil_identity' => 'Prénom et nom (compte ou candidature)',
];
$bannerPath = trim((string) ($personnelProfile['character_banner_path'] ?? ''));
$bannerUrl = $bannerPath !== '' ? $baseUrl . '/' . ltrim($bannerPath, '/') : null;
$isDeployableFile = ((int) ($personnelProfile['deployable'] ?? 1)) === 1;
$legacyServiceNumber = trim((string) ($personnelExtras['service_number'] ?? ''));
$matriculeInternalOnly = trim((string) ($personnelProfile['matricule_internal'] ?? ''));
$showLegacyServiceNumber = $legacyServiceNumber !== '' && ($matriculeInternalOnly === '' || strcasecmp($legacyServiceNumber, $matriculeInternalOnly) !== 0);
$steamId = trim((string) ($targetUser['steam_id'] ?? ''));
$steamId = $steamId !== '' ? $steamId : null;
$accountCreatedDisplay = null;
if (!empty($targetUser['created_at'])) {
    $tsAcc = strtotime((string) $targetUser['created_at']);
    $accountCreatedDisplay = $tsAcc ? date('d/m/Y', $tsAcc) : null;
}
$profilePublicSegment = trim((string) ($targetUser['profile_slug'] ?? ''));
$profilePublicSegment = $profilePublicSegment !== '' ? $profilePublicSegment : null;
$displayNameAccount = trim((string) ($targetUser['display_name'] ?? ''));
$rpCharacterNameDisplay = trim((string) ($personnelProfile['character_name'] ?? ''));
$squadronExtra = trim((string) ($personnelExtras['squadron'] ?? ''));

$enlistmentAppStatusFr = static function (?string $s): string {
    return match (trim((string) $s)) {
        'submitted' => 'Dossier transmis',
        'reviewed' => 'Examinée (suite à traiter ou compte à rattacher)',
        'rejected' => 'Non retenue',
        'blocked' => 'Dossier bloqué',
        'withdrawn' => 'Retirée par le candidat',
        default => $s !== '' ? 'Statut à confirmer auprès du recrutement' : '—',
    };
};
$enlistmentSubmittedViaFr = static function (?string $s): string {
    return match (trim((string) $s)) {
        'guest' => 'Sans compte (formulaire invité)',
        'account' => 'Depuis un compte connecté',
        default => '',
    };
};

if (!function_exists('personnel_file_render_admin_value')) {
    /**
     * @param mixed $value
     */
    function personnel_file_render_admin_value($value, int $depth = 0): void
    {
        if ($depth > 4) {
            echo '<span class="text-slate-500">…</span>';

            return;
        }
        if (is_array($value)) {
            if ($value === []) {
                echo '<span class="text-slate-400">—</span>';

                return;
            }
            $isList = array_keys($value) === range(0, count($value) - 1);
            echo '<ul class="mt-1 list-disc space-y-1 pl-4 text-xs text-slate-800">';
            foreach ($value as $k => $v) {
                echo '<li>';
                if (!$isList && is_string($k) && $k !== '') {
                    echo '<span class="font-semibold text-slate-600">' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . ' : </span>';
                }
                personnel_file_render_admin_value($v, $depth + 1);
                echo '</li>';
            }
            echo '</ul>';

            return;
        }
        $str = $value === null || $value === false ? '' : (string) $value;
        echo $str !== '' ? nl2br(htmlspecialchars($str, ENT_QUOTES, 'UTF-8')) : '<span class="text-slate-400">—</span>';
    }
}
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
    <?php
    $personnelFlashSuccess = \App\Core\Session::getFlash('success');
    $personnelFlashError = \App\Core\Session::getFlash('error');
    ?>
    <?php if ($personnelFlashSuccess || $personnelFlashError): ?>
    <div class="max-w-7xl mx-auto px-6 md:px-8 pt-4">
        <?php if ($personnelFlashSuccess): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm" role="status"><?= htmlspecialchars((string) $personnelFlashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($personnelFlashError): ?>
        <div class="mt-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 shadow-sm" role="alert"><?= htmlspecialchars((string) $personnelFlashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
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
                        <?php if ($privatePersonnelIdentity): ?>
                        <?php $rawAccountStatus = (string) ($targetUser['status'] ?? ''); ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[10px] font-black uppercase <?= $rawAccountStatus === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-600/30 text-slate-400' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $rawAccountStatus === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' ?>"></span>
                            <?= htmlspecialchars($accountStatusFr($rawAccountStatus)) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($clearanceLevel): ?>
                        <span class="inline-flex px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-slate-600/30 text-slate-300">Clearance <?= htmlspecialchars($clearanceLevel) ?></span>
                        <?php endif; ?>
                        <?php if ($isDeployableFile): ?>
                        <span class="inline-flex px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-400">Déployable</span>
                        <?php else: ?>
                        <span class="inline-flex px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-amber-500/15 text-amber-200">Non déployable</span>
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
                <div class="flex items-end gap-4 md:gap-6">
                    <div class="relative w-20 h-20 md:w-24 md:h-24 shrink-0 overflow-hidden rounded-2xl border-2 border-slate-600/50 bg-slate-800" title="Avatar compte" x-data="{ ready: false }">
                        <?php if ($avatarUrl): ?>
                        <div class="absolute inset-0 z-0 bg-slate-700 animate-pulse" x-show="!ready" x-transition.opacity.duration.200ms></div>
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" loading="eager" decoding="async" draggable="false" width="96" height="96" @load="ready = true" class="relative z-[1] h-full w-full object-cover transition-opacity duration-300" :class="ready ? 'opacity-100' : 'opacity-0'" />
                        <?php else: ?>
                        <div class="flex h-full w-full items-center justify-center text-slate-500">
                            <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <?php if ($heroFlagLine !== ''): ?>
                        <p class="line-clamp-2 max-w-[10.5rem] text-right text-[9px] font-black uppercase tracking-[0.22em] text-white/90 md:max-w-[13rem]"><?= htmlspecialchars($heroFlagLine, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <div class="relative aspect-[3/5] w-[10.5rem] max-h-[min(22rem,52vh)] overflow-hidden rounded-2xl border-2 border-slate-600/50 bg-slate-900 shadow-lg shadow-black/40 md:w-[12rem]" title="Portrait opérateur" x-data="{ ready: false }">
                            <?php if ($publicFlagEmoji !== ''): ?>
                            <div class="pointer-events-none absolute inset-0 flex select-none items-end justify-center pb-5 text-[min(7rem,30vw)] leading-none text-white/90 opacity-[0.42] contrast-[1.05] saturate-[0.82] brightness-[0.92] blur-[0.5px]" aria-hidden="true"><?= htmlspecialchars($publicFlagEmoji, ENT_QUOTES, 'UTF-8') ?></div>
                            <?php else: ?>
                            <div class="absolute inset-0 bg-gradient-to-b from-slate-700 to-slate-950" aria-hidden="true"></div>
                            <?php endif; ?>
                            <div class="pointer-events-none absolute inset-0 opacity-[0.38] mix-blend-overlay" style="background-image:repeating-linear-gradient(-33deg,transparent,transparent 5px,rgba(0,0,0,0.22) 5px,rgba(0,0,0,0.22) 6px)" aria-hidden="true"></div>
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black via-black/45 to-transparent" aria-hidden="true"></div>
                            <?php if ($portraitUrl): ?>
                            <div class="absolute inset-0 z-0 bg-slate-800 animate-pulse" x-show="!ready" x-transition.opacity.duration.200ms></div>
                            <img src="<?= htmlspecialchars($portraitUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Portrait opérateur" loading="eager" decoding="async" draggable="false" width="280" height="420" @load="ready = true" class="relative z-[1] h-full w-full object-contain object-bottom transition-opacity duration-300 drop-shadow-[0_8px_24px_rgba(0,0,0,0.65)]" :class="ready ? 'opacity-100' : 'opacity-0'" />
                            <?php else: ?>
                            <div class="relative z-[1] flex h-full w-full items-center justify-center text-slate-500">
                                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </div>
                            <?php endif; ?>
                        </div>
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
                <span class="text-xs text-amber-700 font-semibold"><?= count($sectionsCritiques) ?> point(s) prioritaire(s) : <?= htmlspecialchars(implode(', ', $sectionsCritiques)) ?></span>
                <?php endif; ?>
                <p class="basis-full text-[10px] text-slate-500">Le détail de chaque critère se trouve dans l’onglet <span class="font-semibold text-slate-700">Vue d’ensemble</span>.</p>
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
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Préparation</p>
                    <p class="text-sm font-black text-slate-900"><?= $readiness !== null ? $readiness . ' %' : '—' ?></p>
                </div>
                <?php if ($privatePersonnelIdentity): ?>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Statut réseau</p>
                    <p class="text-sm font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?> italic"><?= htmlspecialchars($accountStatusFr((string) ($targetUser['status'] ?? ''))) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($enlistmentFormatted): ?>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Enrôlement</p>
                    <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($enlistmentFormatted) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($seniorityGlobal !== null): ?>
                <div class="min-w-[9.5rem] max-w-[14rem] shrink-0">
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Ancienneté globale</p>
                    <p class="text-base font-black leading-tight text-slate-900 tabular-nums" title="<?= htmlspecialchars((string) ($seniorityGlobal['basis_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($seniorityGlobal['formatted'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-[9px] font-medium leading-snug text-slate-500 line-clamp-2"><?= htmlspecialchars((string) ($seniorityGlobal['basis_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>
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
                        <div>
                            <p class="text-[7px] font-black text-slate-400 uppercase mb-0.5">Athena ID</p>
                            <p class="text-sm font-black text-slate-900"><?= $athenaIdentifier !== '' ? htmlspecialchars($athenaIdentifier) : '—' ?></p>
                        </div>
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
                <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh'), ENT_QUOTES, 'UTF-8') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-violet-600 hover:text-violet-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-violet-200"></span>Espace RH</a>
                <?php endif; ?>
                <a href="<?= url('formations') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Formations</a>
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Dashboard</a>
            </aside>

            <div class="lg:col-span-9 order-1 lg:order-2 space-y-6" x-data="{ tab: 'resume' }">
                <nav class="flex flex-wrap gap-1.5 rounded-2xl border border-slate-200 bg-slate-50/90 p-1.5 shadow-sm" aria-label="Sections du dossier personnel">
                    <button type="button" @click="tab = 'resume'" :class="tab === 'resume' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'" class="rounded-xl px-3 py-2 text-left text-[11px] font-bold transition min-w-[8.5rem] sm:min-w-0">Vue d’ensemble</button>
                    <?php if ($seniorityDetailLines !== []): ?>
                    <button type="button" @click="tab = 'seniorite'" :class="tab === 'seniorite' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'" class="rounded-xl px-3 py-2 text-left text-[11px] font-bold transition min-w-[8.5rem] sm:min-w-0">Ancienneté</button>
                    <?php endif; ?>
                    <button type="button" @click="tab = 'ops'" :class="tab === 'ops' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'" class="rounded-xl px-3 py-2 text-left text-[11px] font-bold transition min-w-[8.5rem] sm:min-w-0">Poste & affectations</button>
                    <button type="button" @click="tab = 'formation'" :class="tab === 'formation' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'" class="rounded-xl px-3 py-2 text-left text-[11px] font-bold transition min-w-[8.5rem] sm:min-w-0">Habilitations & parcours</button>
                    <button type="button" @click="tab = 'logistique'" :class="tab === 'logistique' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'" class="rounded-xl px-3 py-2 text-left text-[11px] font-bold transition min-w-[8.5rem] sm:min-w-0">Dotation & préparation</button>
                    <button type="button" @click="tab = 'historique'" :class="tab === 'historique' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'" class="rounded-xl px-3 py-2 text-left text-[11px] font-bold transition min-w-[8.5rem] sm:min-w-0">Historique & notes</button>
                    <button type="button" @click="tab = 'administratif'" :class="tab === 'administratif' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'" class="rounded-xl px-3 py-2 text-left text-[11px] font-bold transition min-w-[8.5rem] sm:min-w-0">Coordonnées & dossier</button>
                </nav>

                <?php if ($seniorityDetailLines !== []): ?>
                <div class="space-y-8" x-show="tab === 'seniorite'" x-cloak>
                    <section class="rounded-3xl border border-slate-200 bg-slate-50/90 p-6 shadow-sm md:p-8" aria-labelledby="personnel-seniority-detail-heading">
                        <div class="mb-5">
                            <h2 id="personnel-seniority-detail-heading" class="text-xs font-black uppercase tracking-[0.28em] text-slate-600">Autres indicateurs d’ancienneté</h2>
                            <p class="mt-2 max-w-3xl text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Chaque durée ci-dessous correspond à des <strong>périodes</strong> enregistrées sur le dossier (dates de début et, si besoin, de fin). Il n’y a pas de journal « minute par minute » affiché ici : seules les plages retenues pour le calcul sont visibles. L’organisation peut disposer d’une trace des saisies pour le dossier, sans détail sur cette page.
                            </p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($seniorityDetailLines as $seniorityRow): ?>
                            <div class="rounded-2xl border border-slate-200/90 bg-white px-4 py-3 shadow-sm">
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1"><?= htmlspecialchars((string) ($seniorityRow['label'] ?? 'Indicateur'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-sm font-black text-slate-900 tabular-nums"><?= htmlspecialchars((string) ($seniorityRow['formatted'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
                <?php endif; ?>

                <div class="space-y-8" x-show="tab === 'resume'" x-cloak>
                    <?php if (!empty($rpDossierNeedsAttention)): ?>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950 shadow-sm" role="status">
                        <p class="font-semibold">Nom d’opérateur (personnage) à compléter</p>
                        <p class="mt-1 text-xs leading-relaxed text-amber-900/90">Indiquez un nom de scène cohérent avec votre unité pour finaliser l’identité opérationnelle affichée sur les documents et listes.</p>
                        <?php if ($canEditProfile): ?>
                        <a href="<?= htmlspecialchars(url('personnel/' . (int) $targetUser['id'] . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-flex text-[10px] font-black uppercase tracking-wider text-amber-900 underline decoration-amber-700/50 underline-offset-2 hover:decoration-amber-900">Ouvrir l’édition du dossier</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($bannerUrl): ?>
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-100 shadow-sm">
                        <img src="<?= htmlspecialchars($bannerUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-36 w-full object-cover sm:h-44 md:h-52" loading="lazy" decoding="async" />
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($roleplayFollowupConfig['enabled'])): ?>
                    <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm md:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xs font-black uppercase tracking-[0.35em] text-emerald-900">Back-office roleplay</h2>
                                <p class="mt-2 text-sm text-slate-600 max-w-2xl">Suivi individuel, tutorat, timeline dossier et pilotage d’avancement recrutement.</p>
                            </div>
                            <?php if ($rpProgress !== null): ?>
                            <div class="min-w-[10rem] rounded-2xl border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                                <p class="text-[10px] font-black uppercase tracking-widest text-emerald-900">Progression</p>
                                <p class="mt-1 text-xl font-black text-slate-900"><?= $rpProgress ?>%</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-5 grid gap-3 md:grid-cols-3">
                            <?php foreach ($rpTimelineCards as $card): ?>
                            <article class="rounded-2xl border p-4 <?= htmlspecialchars($card['accent']) ?>">
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-600"><?= htmlspecialchars($card['title']) ?></p>
                                <p class="mt-2 text-lg font-black text-slate-900"><?= htmlspecialchars($card['date'] ?? $card['fallback']) ?></p>
                            </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Étape</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= $rpStage !== '' ? htmlspecialchars($rpStage) : '—' ?></p></div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Statut</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= $rpStatus !== '' ? htmlspecialchars($rpStatus) : '—' ?></p></div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Filière</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= $rpTrack !== '' ? htmlspecialchars($rpTrack) : '—' ?></p></div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Fonction (dossier)</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= $rpFunction !== '' ? htmlspecialchars($rpFunction) : '—' ?></p></div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Profil recrutement</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= $rpOriginLabel !== '' ? htmlspecialchars($rpOriginLabel) : '—' ?></p></div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Tuteur</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= $rpTutorLabel !== null && $rpTutorLabel !== '' ? htmlspecialchars($rpTutorLabel) : '—' ?></p></div>
                        </div>
                        <?php if ($roleplayEligibility['checks'] !== []): ?>
                        <div class="mt-5 rounded-2xl border <?= !empty($roleplayEligibility['eligible']) ? 'border-emerald-200 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/60' ?> p-4">
                            <p class="text-[10px] font-black uppercase tracking-wider <?= !empty($roleplayEligibility['eligible']) ? 'text-emerald-900' : 'text-amber-900' ?>">Indicateur dossier prêt (suivi)</p>
                            <ul class="mt-2 space-y-1.5 text-xs text-slate-700">
                                <?php foreach ($roleplayEligibility['checks'] as $check): ?>
                                <li class="flex items-start gap-2"><span class="font-black <?= !empty($check['ok']) ? 'text-emerald-700' : 'text-amber-700' ?>"><?= !empty($check['ok']) ? '✓' : '!' ?></span><span><?= htmlspecialchars((string) ($check['label'] ?? 'Critère')) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <?php if ($rpNotes !== ''): ?>
                        <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Notes de suivi</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-800"><?= nl2br(htmlspecialchars($rpNotes)) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($roleplayTimelineEvents !== []): ?>
                        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Timeline dossier roleplay</p>
                            <ol class="mt-3 space-y-3">
                                <?php foreach ($roleplayTimelineEvents as $ev):
                                    $evDate = !empty($ev['event_date']) ? date('d/m/Y', strtotime((string) $ev['event_date'])) : (!empty($ev['created_at']) ? date('d/m/Y', strtotime((string) $ev['created_at'])) : '—');
                                    $dueDate = !empty($ev['due_date']) ? date('d/m/Y', strtotime((string) $ev['due_date'])) : null;
                                    $statusRaw = (string) ($ev['status'] ?? 'planned');
                                    $isOverdue = $dueDate !== null && !in_array($statusRaw, ['completed', 'cancelled'], true) && strtotime((string) $ev['due_date']) < strtotime(date('Y-m-d'));
                                    $statusClass = match ($statusRaw) {
                                        'completed' => 'bg-emerald-100 text-emerald-800',
                                        'blocked' => 'bg-rose-100 text-rose-800',
                                        'cancelled' => 'bg-slate-200 text-slate-700',
                                        default => 'bg-amber-100 text-amber-800',
                                    };
                                    $actor = trim((string) ($ev['actor_display_name'] ?? '')) ?: trim((string) ($ev['actor_callsign'] ?? ''));
                                ?>
                                <li class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500"><?= htmlspecialchars((string) ($ev['event_type'] ?? 'événement')) ?></span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold <?= $statusClass ?>"><?= htmlspecialchars($rpTimelineStatusFr($statusRaw)) ?></span>
                                        <?php if ($isOverdue): ?><span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800">En retard</span><?php endif; ?>
                                    </div>
                                    <p class="mt-1 text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($ev['title'] ?? 'Événement')) ?></p>
                                    <?php if (!empty($ev['detail'])): ?><p class="mt-1 text-sm text-slate-700 leading-relaxed"><?= nl2br(htmlspecialchars((string) $ev['detail'])) ?></p><?php endif; ?>
                                    <p class="mt-2 text-[11px] text-slate-500">Date: <span class="font-semibold text-slate-700"><?= htmlspecialchars($evDate) ?></span><?php if ($dueDate !== null): ?> · Échéance: <span class="font-semibold <?= $isOverdue ? 'text-rose-700' : 'text-slate-700' ?>"><?= htmlspecialchars($dueDate) ?></span><?php endif; ?><?php if (!empty($ev['progress_delta']) || (string) ($ev['progress_delta'] ?? '') === '0'): ?> · Impact progression: <span class="font-semibold text-slate-700"><?= (int) $ev['progress_delta'] >= 0 ? '+' : '' ?><?= (int) $ev['progress_delta'] ?></span><?php endif; ?><?php if ($actor !== ''): ?> · Par: <span class="font-semibold text-slate-700"><?= htmlspecialchars($actor) ?></span><?php endif; ?></p>
                                </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                        <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-5">Synthèse</h2>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <?php if ($personnelIsSelf): ?>
                            <div class="sm:col-span-2 xl:col-span-3 rounded-2xl border border-violet-200/80 bg-gradient-to-br from-violet-50/90 to-white px-4 py-4 shadow-sm sm:px-5 sm:py-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-violet-700/90">Espace RH et formations</p>
                                        <p class="mt-1 text-sm leading-snug text-slate-800">Charte, parcours, ancienneté affichée sur cette fiche et programmes de préqualification éventuels — au même endroit.</p>
                                    </div>
                                    <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center self-start rounded-xl bg-violet-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-violet-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 sm:self-center">Ouvrir l’espace RH</a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Unité indiquée</p>
                                <p class="mt-1 text-sm font-bold text-slate-900"><?= $unitName ? htmlspecialchars($unitName) : '—' ?></p>
                                <?php if ($squadronExtra !== '' && ($unitName === null || $squadronExtra !== trim((string) $unitName))): ?>
                                <p class="mt-2 text-[10px] text-slate-600">Mention dossier : <span class="font-semibold"><?= htmlspecialchars($squadronExtra) ?></span></p>
                                <?php endif; ?>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Habilitation documentaire</p>
                                <p class="mt-1 text-sm font-bold text-emerald-700"><?= $clearanceLevel ? htmlspecialchars($clearanceLevel) : '—' ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Préparation opérationnelle</p>
                                <p class="mt-1 text-sm font-bold text-slate-900"><?= $readiness !== null ? $readiness . ' %' : '—' ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Déployabilité</p>
                                <p class="mt-1 text-sm font-bold <?= $isDeployableFile ? 'text-emerald-700' : 'text-amber-800' ?>"><?= $isDeployableFile ? 'Oui' : 'Non' ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Incorporation</p>
                                <p class="mt-1 text-sm font-bold text-slate-900"><?= $enlistmentFormatted ? htmlspecialchars($enlistmentFormatted) : '—' ?></p>
                            </div>
                            <?php if ($communityRoleLabel !== null): ?>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Rôle dans la communauté</p>
                                <p class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars($communityRoleLabel) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if ($steamId !== null): ?>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4 sm:col-span-2 xl:col-span-1">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Identifiant Steam</p>
                                <p class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars($steamId) ?></p>
                                <?php if ($steamProfileSyncOffered): ?>
                                <form method="post" action="<?= htmlspecialchars(url('personnel/' . (int) $targetUser['id'] . '/sync-steam'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-3 border-t border-slate-200/80 pt-4">
                                    <?= \App\Core\Csrf::field() ?>
                                    <label class="flex cursor-pointer items-start gap-2 text-xs text-slate-700">
                                        <input type="checkbox" name="apply_steam_display_name" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                        <span>Mettre aussi à jour le <strong>nom d’affichage</strong> du compte pour qu’il corresponde au profil public Steam (en plus de la photo).</span>
                                    </label>
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800 sm:w-auto">Importer photo depuis Steam</button>
                                    <p class="text-[10px] text-slate-500">Utilise les informations publiques associées à l’identifiant ci-dessus. Le membre peut modifier l’identifiant dans les préférences du compte.</p>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (is_array($armaPlaytime ?? null)): ?>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4 sm:col-span-2 xl:col-span-1">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Temps de jeu en mission</p>
                                <?php if (!empty($armaPlaytime['show_steam_hint_self'])): ?>
                                    <p class="mt-2 text-sm text-slate-700">Indiquez dans les préférences du compte le même identifiant que dans le jeu pour que le cumul puisse être rattaché à votre dossier.</p>
                                <?php elseif (!empty($armaPlaytime['no_steam_staff'])): ?>
                                    <p class="mt-2 text-sm text-slate-600">Non renseigné sur ce dossier.</p>
                                <?php elseif (!empty($armaPlaytime['schema_ready']) && $steamId !== null && ($armaPlaytime['hours_label'] ?? null) !== null): ?>
                                    <p class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars((string) $armaPlaytime['hours_label'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if (!empty($armaPlaytime['last_sync_label'])): ?>
                                        <p class="mt-2 text-xs text-slate-500">Dernière remontée : <?= htmlspecialchars((string) $armaPlaytime['last_sync_label'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <p class="mt-2 text-[10px] text-slate-500">Cumul issu des sessions avec le mod connecté au portail.</p>
                                <?php elseif ($steamId !== null): ?>
                                    <p class="mt-2 text-sm text-slate-600">Le cumul sera affiché après mise à jour du suivi côté portail.</p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($accountCreatedDisplay !== null): ?>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Membre depuis</p>
                                <p class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars($accountCreatedDisplay) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if ($showLegacyServiceNumber): ?>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Ancienne référence dossier</p>
                                <p class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars($legacyServiceNumber) ?></p>
                                <p class="mt-1 text-[10px] text-slate-500">Conserve une trace si le matricule actuel a été réattribué plus tard.</p>
                            </div>
                            <?php endif; ?>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Activité du dossier</p>
                                <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                    <li><span class="font-semibold text-slate-900"><?= count($assignments) ?></span> affectation(s) active(s)</li>
                                    <li><span class="font-semibold text-slate-900"><?= count($qualifications) ?></span> qualification(s)</li>
                                    <li><span class="font-semibold text-slate-900"><?= count($lmsEnrollmentsForPersonnel) ?></span> parcours suivi(s)</li>
                                    <li><span class="font-semibold text-slate-900"><?= count($trainingCertificates) ?></span> attestation(s)</li>
                                </ul>
                            </div>
                        </div>
                        <?php if ($privatePersonnelIdentity && $displayNameAccount !== '' && $displayNameAccount !== $displayName): ?>
                        <p class="mt-5 text-xs text-slate-600">Nom affiché sur le compte : <span class="font-semibold text-slate-800"><?= htmlspecialchars($displayNameAccount) ?></span><?php if ($rpCharacterNameDisplay !== '' && $rpCharacterNameDisplay !== $displayName): ?> · Nom de scène dossier : <span class="font-semibold text-slate-800"><?= htmlspecialchars($rpCharacterNameDisplay) ?></span><?php endif; ?>.</p>
                        <?php elseif ($rpCharacterNameDisplay !== '' && $rpCharacterNameDisplay !== $displayName): ?>
                        <p class="mt-5 text-xs text-slate-600">Nom de scène dossier : <span class="font-semibold text-slate-800"><?= htmlspecialchars($rpCharacterNameDisplay) ?></span>.</p>
                        <?php endif; ?>
                    </section>
                    <?php if ($privatePersonnelIdentity && is_array($latestEnlistment) && $latestEnlistment !== []): ?>
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                        <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-2">Dernière candidature recrutement</h2>
                        <p class="text-xs text-slate-600 mb-4">Résumé issu du dernier dossier transmis (identité telle que saisie à l’époque).</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Prénom et nom</p><p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars(trim((string) ($latestEnlistment['first_name'] ?? '') . ' ' . (string) ($latestEnlistment['last_name'] ?? ''))) ?></p></div>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">État du dossier</p><p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($enlistmentAppStatusFr((string) ($latestEnlistment['status'] ?? ''))) ?></p></div>
                            <?php if (!empty($latestEnlistment['created_at'])): ?>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Envoyée le</p><p class="text-sm text-slate-800"><?= htmlspecialchars(date('d/m/Y', strtotime((string) $latestEnlistment['created_at']))) ?></p></div>
                            <?php endif; ?>
                            <?php if (!empty($latestEnlistment['reviewed_at'])): ?>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Examinée le</p><p class="text-sm text-slate-800"><?= htmlspecialchars(date('d/m/Y', strtotime((string) $latestEnlistment['reviewed_at']))) ?></p></div>
                            <?php endif; ?>
                            <?php
                            $enrCall = trim((string) ($latestEnlistment['callsign'] ?? ''));
                            $enrMail = trim((string) ($latestEnlistment['email'] ?? ''));
                            ?>
                            <?php if ($enrCall !== ''): ?>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Indicatif indiqué</p><p class="text-sm text-slate-800"><?= htmlspecialchars($enrCall) ?></p></div>
                            <?php endif; ?>
                            <?php if ($enrMail !== ''): ?>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">E-mail de contact (dossier)</p><p class="text-sm text-slate-800 break-all"><?= htmlspecialchars($enrMail) ?></p></div>
                            <?php endif; ?>
                            <?php if (!empty(trim((string) ($latestEnlistment['country'] ?? '')))): ?>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Pays / fuseau indiqué</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $latestEnlistment['country'])) ?></p></div>
                            <?php endif; ?>
                            <?php if (!empty(trim((string) ($latestEnlistment['experience'] ?? '')))): ?>
                            <div class="sm:col-span-2"><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Expérience décrite</p><p class="text-sm text-slate-800 leading-relaxed"><?= nl2br(htmlspecialchars(trim((string) $latestEnlistment['experience']))) ?></p></div>
                            <?php endif; ?>
                            <?php if (!empty(trim((string) ($latestEnlistment['specialty'] ?? '')))): ?>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Spécialité indiquée</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $latestEnlistment['specialty'])) ?></p></div>
                            <?php endif; ?>
                            <?php if (!empty(trim((string) ($latestEnlistment['platform'] ?? '')))): ?>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Plateforme de jeu</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $latestEnlistment['platform'])) ?></p></div>
                            <?php endif; ?>
                            <?php if (!empty(trim((string) ($latestEnlistment['availability'] ?? '')))): ?>
                            <div class="sm:col-span-2"><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Disponibilités indiquées</p><p class="text-sm text-slate-800 leading-relaxed"><?= nl2br(htmlspecialchars(trim((string) $latestEnlistment['availability']))) ?></p></div>
                            <?php endif; ?>
                            <?php if (!empty(trim((string) ($latestEnlistment['notes'] ?? '')))): ?>
                            <div class="sm:col-span-2"><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Message joint au dossier</p><p class="text-sm text-slate-800 leading-relaxed"><?= nl2br(htmlspecialchars(trim((string) $latestEnlistment['notes']))) ?></p></div>
                            <?php endif; ?>
                            <?php
                            $viaFr = $enlistmentSubmittedViaFr((string) ($latestEnlistment['submitted_via'] ?? ''));
                            ?>
                            <?php if ($viaFr !== ''): ?>
                            <div><p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Mode de transmission</p><p class="text-sm text-slate-800"><?= htmlspecialchars($viaFr) ?></p></div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-6">
                            <div>
                                <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900">Avancement du dossier</h2>
                                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Chaque point complété améliore la lisibilité de votre dossier pour l’encadrement et les outils du portail.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-2xl font-black text-slate-900 tabular-nums"><?= (int) $completenessScore ?> %</span>
                                <div class="h-2 w-28 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all" style="width: <?= min(100, max(0, $completenessScore)) ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <ul class="grid gap-2 sm:grid-cols-2">
                            <?php foreach ($completenessCheckLabels as $cKey => $cLabel):
                                $ok = !empty($completenessDetails[$cKey]);
                                ?>
                            <li class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5 text-xs text-slate-800">
                                <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-black <?= $ok ? 'bg-emerald-500 text-white' : 'bg-amber-100 text-amber-800' ?>" aria-hidden="true"><?= $ok ? '✓' : '!' ?></span>
                                <span><span class="font-semibold text-slate-900"><?= htmlspecialchars($cLabel) ?></span><?php if (!$ok && $canEditProfile): ?> <span class="text-slate-500">— à compléter</span><?php endif; ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($canEditProfile && !empty($sectionsCritiques)): ?>
                        <p class="mt-5 text-xs font-semibold text-amber-800">Priorité : <?= htmlspecialchars(implode(' · ', $sectionsCritiques)) ?>.</p>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="space-y-8" x-show="tab === 'ops'" x-cloak>
                <!-- Identité opérationnelle -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Identité opérationnelle</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nom opérateur</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($displayName) ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Indicatif radio</p><p class="text-sm font-black text-slate-900"><?= $callsign ? htmlspecialchars($callsign) : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Matricule</p><p class="text-sm font-black text-slate-900"><?= !empty($showMatriculePublic) ? ($matricule ? htmlspecialchars($matricule) : '—') : '—' ?></p></div>
                        <?php if ($rpCharacterNameDisplay !== ''): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nom de scène (personnage)</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($rpCharacterNameDisplay) ?></p></div>
                        <?php endif; ?>
                        <?php if ($privatePersonnelIdentity && $displayNameAccount !== '' && $displayNameAccount !== $displayName): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nom affiché sur le compte</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($displayNameAccount) ?></p></div>
                        <?php endif; ?>
                        <?php if ($gradeLabel !== '' && $gradeLabel !== $effectiveRankDisplay): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Grade de référence</p><p class="text-sm text-slate-700"><?= htmlspecialchars($gradeLabel) ?></p></div>
                        <?php endif; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle principal</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['primary_role'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle secondaire</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['secondary_role'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Unité</p><p class="text-sm font-black text-slate-900"><?= $unitName ? htmlspecialchars($unitName) : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Chef direct</p><p class="text-sm font-black text-slate-900"><?= $commander ? htmlspecialchars($commander['display_name'] ?? $commander['callsign'] ?? '') : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date d'incorporation</p><p class="text-sm font-black text-slate-900"><?= $enlistmentFormatted ?? '—' ?></p></div>
                        <?php if ($showLegacyServiceNumber): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Ancienne référence dossier</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($legacyServiceNumber) ?></p></div>
                        <?php endif; ?>
                        <?php if ($communityRoleLabel !== null): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle dans la communauté</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($communityRoleLabel) ?></p></div>
                        <?php endif; ?>
                        <?php if ($steamId !== null): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Identifiant Steam</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($steamId) ?></p></div>
                        <?php endif; ?>
                    </div>
                    <?php
                    $rpMotto = trim((string) ($personnelProfile['motto'] ?? ''));
                    $rpBlood = trim((string) ($personnelProfile['blood_type'] ?? ''));
                    $rpLangs = trim((string) ($personnelProfile['languages'] ?? ''));
                    $rpNat = trim((string) ($personnelProfile['nationality'] ?? ''));
                    $birthPlace = trim((string) ($personnelProfile['birth_place'] ?? ''));
                    $serviceBranch = trim((string) ($personnelProfile['service_branch'] ?? ''));
                    $serviceStatus = trim((string) ($personnelProfile['service_status'] ?? ''));
                    $gendarmerieStatus = trim((string) ($personnelProfile['gendarmerie_status'] ?? ''));
                    $administrativePosition = trim((string) ($personnelProfile['administrative_position'] ?? ''));
                    $bureauSn = trim((string) ($personnelProfile['bureau_sn'] ?? ''));
                    $militaryOrigin = trim((string) ($personnelProfile['military_origin'] ?? ''));
                    $statutoryLimitRaw = trim((string) ($personnelProfile['statutory_limit_date'] ?? ''));
                    $managementLimitRaw = trim((string) ($personnelProfile['management_service_limit_date'] ?? ''));
                    $statutoryLimitDisplay = ($statutoryLimitRaw !== '' && strtotime($statutoryLimitRaw)) ? date('d/m/Y', strtotime($statutoryLimitRaw)) : $statutoryLimitRaw;
                    $managementLimitDisplay = ($managementLimitRaw !== '' && strtotime($managementLimitRaw)) ? date('d/m/Y', strtotime($managementLimitRaw)) : $managementLimitRaw;
                    $rpExtra = $rpMotto !== '' || $rpBlood !== '' || $rpLangs !== '' || $rpNat !== '';
                    $dossierExtra = $birthPlace !== '' || $serviceBranch !== '' || $serviceStatus !== '' || $gendarmerieStatus !== '' || $administrativePosition !== '' || $bureauSn !== '' || $militaryOrigin !== '' || $statutoryLimitDisplay !== '' || $managementLimitDisplay !== '';
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
                    <?php if ($dossierExtra): ?>
                    <div class="mt-8 border-t border-slate-100 pt-6">
                        <h3 class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500 mb-4">Détails de fiche militaire</h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php if ($birthPlace !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Lieu de naissance</p><p class="text-sm text-slate-800"><?= htmlspecialchars($birthPlace) ?></p></div>
                            <?php endif; ?>
                            <?php if ($serviceBranch !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Corps / filière</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($serviceBranch) ?></p></div>
                            <?php endif; ?>
                            <?php if ($serviceStatus !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut de service</p><p class="text-sm text-slate-800"><?= htmlspecialchars($serviceStatus) ?></p></div>
                            <?php endif; ?>
                            <?php if ($gendarmerieStatus !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut gendarmerie</p><p class="text-sm text-slate-800"><?= htmlspecialchars($gendarmerieStatus) ?></p></div>
                            <?php endif; ?>
                            <?php if ($administrativePosition !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Position administrative</p><p class="text-sm text-slate-800"><?= htmlspecialchars($administrativePosition) ?></p></div>
                            <?php endif; ?>
                            <?php if ($bureauSn !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Bureau du SN</p><p class="text-sm text-slate-800"><?= htmlspecialchars($bureauSn) ?></p></div>
                            <?php endif; ?>
                            <?php if ($militaryOrigin !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Origine recrutement</p><p class="text-sm text-slate-800"><?= htmlspecialchars($militaryOrigin) ?></p></div>
                            <?php endif; ?>
                            <?php if ($statutoryLimitDisplay !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date limite d'âge statutaire</p><p class="text-sm text-slate-800"><?= htmlspecialchars($statutoryLimitDisplay) ?></p></div>
                            <?php endif; ?>
                            <?php if ($managementLimitDisplay !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date limite des services en gestion</p><p class="text-sm text-slate-800"><?= htmlspecialchars($managementLimitDisplay) ?></p></div>
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
                            $asgStartedRaw = trim((string) ($asg['started_at'] ?? ''));
                            $asgStartedDisp = $asgStartedRaw !== '' && strtotime($asgStartedRaw) ? date('d/m/Y', strtotime($asgStartedRaw)) : ($asgStartedRaw !== '' ? $asgStartedRaw : null);
                            $utypeRaw = trim((string) ($asg['unit_type'] ?? ''));
                            $utypeDisp = $unitTypeFr($utypeRaw);
                            $asgStatRaw = trim((string) ($asg['status'] ?? 'active'));
                            $asgStatDisp = $personnelAssignmentStatusFr($asgStatRaw);
                            $asgDurLabel = trim((string) ($asg['duration_label_fr'] ?? ''));
                            $asgSpanOpen = !empty($asg['assignment_span_open']);
                            ?>
                        <div class="rounded-2xl border border-slate-200 p-5 flex flex-col gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <?php if ($asgPrimary): ?>
                                <span class="inline-flex rounded-md bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-900">Affectation principale</span>
                                <?php else: ?>
                                <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">Affectation complémentaire</span>
                                <?php endif; ?>
                                <?php if ($asgStatDisp !== ''): ?>
                                <span class="inline-flex rounded-md bg-slate-50 px-2 py-0.5 text-[9px] font-bold uppercase text-slate-600 ring-1 ring-slate-200"><?= htmlspecialchars($asgStatDisp) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Unité</p><p class="text-sm font-black text-slate-900"><?= $asgUnit !== '' ? htmlspecialchars($asgUnit) : '—' ?></p></div>
                                <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Fonction dans l’équipe</p><p class="text-sm font-black text-slate-900"><?= $asgRole !== '' ? htmlspecialchars($asgRole) : '—' ?></p></div>
                                <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Chef d’unité</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($cmdLabel) ?></p></div>
                                <?php if ($asgStartedDisp !== null): ?>
                                <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Depuis le</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($asgStartedDisp) ?></p></div>
                                <?php endif; ?>
                                <?php if ($utypeDisp !== ''): ?>
                                <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nature de l’unité</p><p class="text-sm text-slate-800"><?= htmlspecialchars($utypeDisp) ?></p></div>
                                <?php endif; ?>
                                <?php if ($asgDurLabel !== '' && $asgDurLabel !== '—'): ?>
                                <div class="md:col-span-2">
                                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Durée sur cette affectation</p>
                                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($asgDurLabel) ?><?php if ($asgSpanOpen): ?> <span class="text-xs font-medium text-slate-500 normal-case">(à ce jour)</span><?php endif; ?></p>
                                    <p class="mt-1 text-[10px] text-slate-500 leading-relaxed">Temps passé dans l’unité sur cette période et sur la fonction indiquée (même durée).</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($enlistmentFormatted): ?>
                    <p class="text-xs text-slate-500 mt-6 pt-6 border-t border-slate-100">Date d’enrôlement : <span class="font-semibold text-slate-700"><?= htmlspecialchars($enlistmentFormatted) ?></span></p>
                    <?php endif; ?>
                    <?php endif; ?>
                </section>

                <?php if ($personnelAssignmentHistory !== []): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-6">
                        <div>
                            <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-2">Historique des affectations</h2>
                            <p class="text-sm text-slate-600 max-w-3xl leading-relaxed">Toutes les périodes enregistrées dans le dossier (y compris les affectations terminées). Les durées sont en jours calendaires (début et fin inclus). Pour chaque ligne, le temps dans l’unité et le temps sur le poste affiché sont les mêmes ; si la personne a eu plusieurs passages dans la même unité, le cumul regroupe l’ensemble des périodes.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 w-full md:w-auto md:min-w-[21rem]">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Périodes</p>
                                <p class="text-base font-black text-slate-900"><?= count($personnelAssignmentHistory) ?></p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Unités concernées</p>
                                <p class="text-base font-black text-slate-900"><?= count($histUnitPeriodCount) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($personnelAssignmentHistory as $hist):
                            $hUnit = trim((string) ($hist['unit_name'] ?? ''));
                            $hRole = trim((string) ($hist['role_name'] ?? ''));
                            $hPrimary = !empty($hist['is_primary']);
                            $hCmdId = (int) ($hist['commander_user_id'] ?? 0);
                            $hCmdLabel = $hCmdId > 0 ? ($commanderLabelsById[$hCmdId] ?? '—') : '—';
                            $hStartRaw = trim((string) ($hist['started_at'] ?? ''));
                            $hStartDisp = $hStartRaw !== '' && strtotime($hStartRaw) ? date('d/m/Y', strtotime($hStartRaw)) : ($hStartRaw !== '' ? $hStartRaw : '—');
                            $hEndRaw = trim((string) ($hist['ended_at'] ?? ''));
                            $hEndDisp = $hEndRaw !== '' && strtotime($hEndRaw) ? date('d/m/Y', strtotime($hEndRaw)) : null;
                            $hOpen = !empty($hist['assignment_span_open']);
                            $hStatRaw = trim((string) ($hist['status'] ?? ''));
                            $hStatDisp = $personnelAssignmentStatusFr($hStatRaw);
                            $hUtype = $unitTypeFr(trim((string) ($hist['unit_type'] ?? '')));
                            $hDur = trim((string) ($hist['duration_label_fr'] ?? ''));
                            $hUnitId = (int) ($hist['unit_id'] ?? 0);
                            $hCumulDays = (int) ($personnelAssignmentHistoryUnitTotals[$hUnitId] ?? 0);
                            $hPeriodsInUnit = (int) ($histUnitPeriodCount[$hUnitId] ?? 0);
                            $hShowCumul = $hPeriodsInUnit > 1 && $hCumulDays > 0;
                            $hRangeLabel = $hOpen ? ($hStartDisp . ' → En cours') : ($hStartDisp . ' → ' . ($hEndDisp ?? '—'));
                            ?>
                        <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50/60">
                            <div class="absolute left-6 top-0 bottom-0 w-px bg-slate-200 hidden sm:block"></div>
                            <div class="relative p-5 sm:pl-12 flex flex-col gap-4">
                                <span class="hidden sm:block absolute left-[1.16rem] top-7 h-3 w-3 rounded-full border-2 border-white <?= $hOpen ? 'bg-sky-500' : 'bg-emerald-500' ?>"></span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if ($hPrimary): ?>
                                    <span class="inline-flex rounded-md bg-emerald-100/90 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-900">Période principale</span>
                                    <?php else: ?>
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">Période complémentaire</span>
                                    <?php endif; ?>
                                    <?php if ($hStatDisp !== ''): ?>
                                    <span class="inline-flex rounded-md bg-white px-2 py-0.5 text-[9px] font-bold uppercase text-slate-600 ring-1 ring-slate-200"><?= htmlspecialchars($hStatDisp) ?></span>
                                    <?php endif; ?>
                                    <?php if ($hOpen): ?>
                                    <span class="inline-flex rounded-md bg-sky-100 px-2 py-0.5 text-[9px] font-black uppercase text-sky-900">En cours</span>
                                    <?php endif; ?>
                                    <span class="inline-flex rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[9px] font-bold text-slate-600"><?= htmlspecialchars($hRangeLabel) ?></span>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Unité</p><p class="text-sm font-black text-slate-900"><?= $hUnit !== '' ? htmlspecialchars($hUnit) : '—' ?></p></div>
                                    <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Fonction dans l’équipe</p><p class="text-sm font-black text-slate-900"><?= $hRole !== '' ? htmlspecialchars($hRole) : '—' ?></p></div>
                                    <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Chef d’unité (référence)</p><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($hCmdLabel) ?></p></div>
                                    <?php if ($hUtype !== ''): ?>
                                    <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nature de l’unité</p><p class="text-sm text-slate-800"><?= htmlspecialchars($hUtype) ?></p></div>
                                    <?php endif; ?>
                                    <?php if ($hDur !== '' && $hDur !== '—'): ?>
                                    <div class="md:col-span-2 rounded-xl border border-slate-100 bg-white/90 px-4 py-3">
                                        <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Durée sur cette période</p>
                                        <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($hDur) ?><?php if ($hOpen): ?> <span class="text-xs font-medium text-slate-500 normal-case">(à ce jour)</span><?php endif; ?></p>
                                        <p class="mt-1 text-[10px] text-slate-500 leading-relaxed">Temps dans l’unité et sur ce poste : même durée pour cette ligne.</p>
                                        <?php if ($hShowCumul): ?>
                                        <div class="mt-2 inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-2.5 py-1.5 ring-1 ring-emerald-100">
                                            <p class="text-[9px] font-black uppercase tracking-wide text-emerald-700">Cumul unité</p>
                                            <p class="text-sm font-semibold text-emerald-800"><?= htmlspecialchars($personnelDurationDaysFr($hCumulDays)) ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

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
                </div>

                <div class="space-y-8" x-show="tab === 'formation'" x-cloak>
                <!-- Sécurité / habilitation -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
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
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
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
                                <?php
                                $atFr = $lmsEnrollmentAssignTypeFr((string) ($enr['assignment_type'] ?? ''));
                                $estMin = (int) ($enr['estimated_minutes'] ?? 0);
                                $catRaw = trim((string) ($enr['category'] ?? ''));
                                $lvlRaw = trim((string) ($enr['level'] ?? ''));
                                $asg = !empty($enr['assigned_at']) ? date('d/m/Y H:i', strtotime((string) $enr['assigned_at'])) : null;
                                $stAt = !empty($enr['started_at']) ? date('d/m/Y H:i', strtotime((string) $enr['started_at'])) : null;
                                $cmpAt = !empty($enr['completed_at']) ? date('d/m/Y H:i', strtotime((string) $enr['completed_at'])) : null;
                                ?>
                                <div class="mt-1 space-y-1 border-t border-slate-200/80 pt-2 text-[10px] text-slate-600">
                                    <?php if ($asg !== null): ?><p>Assigné le <span class="font-semibold text-slate-800"><?= htmlspecialchars($asg) ?></span></p><?php endif; ?>
                                    <?php if ($stAt !== null): ?><p>Première ouverture le <span class="font-semibold text-slate-800"><?= htmlspecialchars($stAt) ?></span></p><?php endif; ?>
                                    <?php if ($cmpAt !== null): ?><p>Terminé le <span class="font-semibold text-slate-800"><?= htmlspecialchars($cmpAt) ?></span></p><?php endif; ?>
                                    <?php if ($atFr !== ''): ?><p>Mode d’inscription : <span class="font-semibold text-slate-800"><?= htmlspecialchars($atFr) ?></span></p><?php endif; ?>
                                    <?php if (!empty($enr['is_mandatory'])): ?><p class="font-semibold text-amber-900">Parcours marqué comme obligatoire</p><?php endif; ?>
                                    <?php if ($estMin > 0):
                                        $dh = intdiv($estMin, 60);
                                        $dm = $estMin % 60;
                                        $durLabel = $dh > 0 ? $dh . ' h' . ($dm > 0 ? ' ' . $dm . ' min' : '') : $dm . ' min';
                                        ?>
                                    <p>Durée indicative : <span class="font-semibold text-slate-800"><?= htmlspecialchars($durLabel) ?></span></p>
                                    <?php endif; ?>
                                    <?php if ($catRaw !== ''): ?><p>Thématique indiquée : <span class="font-semibold text-slate-800"><?= htmlspecialchars($catRaw) ?></span></p><?php endif; ?>
                                    <?php if ($lvlRaw !== ''): ?><p>Niveau indiqué : <span class="font-semibold text-slate-800"><?= htmlspecialchars($lvlRaw) ?></span></p><?php endif; ?>
                                </div>
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
                                    <?php if (!empty($cert['completed_at'])): ?>
                                    <span class="text-slate-500"> · Parcours achevé le <?= date('d/m/Y', strtotime((string) $cert['completed_at'])) ?></span>
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
                            <?php foreach ($qualifications as $q):
                                $qLevel = trim((string) ($q['level'] ?? ''));
                                $qIss = (int) ($q['issued_by'] ?? 0);
                                $qIssuer = $qIss > 0 && isset($qualificationIssuerLabels[$qIss]) ? (string) $qualificationIssuerLabels[$qIss] : null;
                                $qObt = !empty($q['obtained_at']) ? date('d/m/Y', strtotime((string) $q['obtained_at'])) : null;
                                ?>
                            <div class="px-6 py-4 border border-slate-200 rounded-2xl flex flex-col gap-2">
                                <span class="text-[7px] font-black tracking-widest text-slate-400 uppercase"><?= htmlspecialchars((string) ($q['qualification_name'] ?? '')) ?></span>
                                <p class="text-xs font-bold text-slate-900"><?php if ($qLevel !== ''): ?><?= htmlspecialchars($qLevel) ?> — <?php endif; ?><?= htmlspecialchars($qualificationStatusFr((string) ($q['status'] ?? ''))) ?></p>
                                <?php if ($qObt !== null): ?><p class="text-[10px] text-slate-600">Obtenue le <?= htmlspecialchars($qObt) ?></p><?php endif; ?>
                                <?php if (!empty($q['expires_at'])): ?><p class="text-[10px] text-slate-500">Échéance <?= date('d/m/Y', strtotime((string) $q['expires_at'])) ?></p><?php endif; ?>
                                <?php if ($qIssuer !== null && $qIssuer !== ''): ?><p class="text-[10px] text-slate-600">Référent enregistrement : <span class="font-semibold text-slate-800"><?= htmlspecialchars($qIssuer) ?></span></p><?php endif; ?>
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
                </div>

                <div class="space-y-8" x-show="tab === 'logistique'" x-cloak>
                <!-- Équipement / dotation -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Équipement / dotation</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Classe d'équipement</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['equipment_class'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Kit assigné</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['kit_assigned'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Radio</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['radio_assigned'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Véhicule autorisé</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['vehicle_authorized'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Spécialité armement</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['weapon_specialty'] ?? '—') ?></p></div>
                    </div>
                </section>

                <!-- Préparation opérationnelle -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6 flex flex-wrap items-center justify-between gap-3">
                        Préparation opérationnelle
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black italic tabular-nums"><?= $readiness !== null ? $readiness : '—' ?><?= $readiness !== null ? ' %' : '' ?></span>
                            <?php if ($readiness !== null): ?>
                            <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: <?= min(100, max(0, $readiness)) ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <?php if ($flightHours !== null && $flightHours !== ''): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase mb-1">Heures de vol</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars((string)$flightHours) ?></p></div>
                        <?php endif; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase mb-1">Déployable</p><p class="text-sm font-black text-slate-900"><?= $isDeployableFile ? 'Oui' : 'Non' ?></p></div>
                    </div>
                </section>
                </div>

                <div class="space-y-8" x-show="tab === 'historique'" x-cloak>
                <?php if ($personnelOrgHistorySection): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-2">Journal du dossier</h2>
                    <p class="text-[10px] text-slate-500 mb-6 leading-relaxed">Modifications enregistrées par l’organisation (grade, rôles, statut du compte, coordonnées visibles sur la fiche, etc.).</p>
                    <?php if ($personnelOrgHistorySchemaReady && $personnelOrgHistory !== []): ?>
                    <div class="space-y-3">
                        <?php foreach ($personnelOrgHistory as $oh):
                            $ohTs = strtotime((string) ($oh['created_at'] ?? ''));
                            $ohWhen = $ohTs ? date('d/m/Y à H:i', $ohTs) : '—';
                            $ohActor = isset($oh['actor_label']) && is_string($oh['actor_label']) && trim($oh['actor_label']) !== '' ? trim((string) $oh['actor_label']) : null;
                            ?>
                        <div class="flex gap-4 border-l-2 border-indigo-200 pl-4 py-2">
                            <span class="text-[10px] font-semibold tabular-nums text-slate-500 shrink-0 w-[7.5rem] sm:w-36"><?= htmlspecialchars($ohWhen) ?></span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 leading-snug"><?= htmlspecialchars((string) ($oh['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($ohActor !== null): ?>
                                <p class="text-[10px] text-slate-500 mt-1">Par <?= htmlspecialchars($ohActor, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-8 text-center text-sm text-slate-600">
                        <?php if (!$personnelOrgHistorySchemaReady): ?>
                        Le journal du dossier sera disponible après l’initialisation de l’historique de l’organisation.
                        <?php else: ?>
                        Aucune modification n’a encore été consignée dans ce journal.
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <!-- Historique de service -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Historique de service</h2>
                    <?php if (!empty($serviceHistory)): ?>
                    <div class="space-y-4">
                        <?php foreach ($serviceHistory as $event):
                            $evTypeLabel = $serviceHistoryEventTypeFr((string) ($event['event_type'] ?? ''));
                            ?>
                        <div class="flex gap-4 border-l-2 border-emerald-200 pl-4 py-2">
                            <span class="text-[10px] font-semibold tabular-nums text-slate-500 shrink-0 w-16"><?= date('m/Y', strtotime((string) ($event['event_date'] ?? 'now'))) ?></span>
                            <div>
                                <?php if ($evTypeLabel !== '' && $evTypeLabel !== 'Événement'): ?>
                                <p class="text-[9px] font-black uppercase tracking-wider text-emerald-800/90 mb-1"><?= htmlspecialchars($evTypeLabel) ?></p>
                                <?php endif; ?>
                                <p class="text-sm font-black text-slate-900"><?= htmlspecialchars((string) ($event['title'] ?? '')) ?></p>
                                <?php if (!empty($event['description'])): ?><p class="text-xs text-slate-600 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars((string) $event['description'])) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-8 text-center text-sm text-slate-600">
                        Aucun événement d’ancienneté ou de carrière n’est encore enregistré dans ce dossier.
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Notes de commandement -->
                <?php if ($canViewCommandNotes): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
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
                </div>

                <div class="space-y-8" x-show="tab === 'administratif'" x-cloak>
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-4">Compte & accès</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <?php if ($accountCreatedDisplay !== null): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Membre depuis</p><p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($accountCreatedDisplay) ?></p></div>
                        <?php endif; ?>
                        <?php if ($communityRoleLabel !== null): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle dans la communauté</p><p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($communityRoleLabel) ?></p></div>
                        <?php endif; ?>
                        <?php if ($steamId !== null): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Identifiant Steam</p><p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($steamId) ?></p></div>
                        <?php endif; ?>
                        <?php if ($profilePublicSegment !== null): ?>
                        <div class="md:col-span-2">
                            <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Lien de partage de cette fiche</p>
                            <a href="<?= htmlspecialchars(url('personnel/' . rawurlencode($profilePublicSegment)), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-emerald-700 underline decoration-emerald-600/30 underline-offset-2 hover:text-emerald-900">Ouvrir la fiche partageable</a>
                            <p class="mt-1 text-[10px] text-slate-500">Même contenu que cette page, utile pour la transmettre à votre encadrement.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
                <!-- Identité civile / administrative -->
                <?php if ($canViewCivilSection): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-2">Identité civile / administrative</h2>
                    <?php if ($civilSourceLabel): ?>
                    <p class="text-[10px] text-slate-500 mb-6">Source prénom / nom : <span class="font-semibold text-slate-700"><?= htmlspecialchars($civilSourceLabel) ?></span><?php if (($civilIdentity['source'] ?? null) === 'enlistment' && is_array($latestEnlistment) && $latestEnlistment !== []): ?> · Dossier de recrutement associé : <span class="font-semibold text-slate-700"><?= htmlspecialchars($enlistmentAppStatusFr((string) ($latestEnlistment['status'] ?? ''))) ?></span><?php if (!empty($latestEnlistment['created_at'])): ?> (transmis le <?= htmlspecialchars(date('d/m/Y', strtotime((string) $latestEnlistment['created_at']))) ?>)<?php endif; ?><?php endif; ?>.</p>
                    <?php else: ?>
                    <p class="text-[10px] text-slate-500 mb-6">Renseignez le prénom et le nom dans <a href="<?= url('account/preferences') ?>" class="font-semibold text-emerald-700 underline">Compte → Préférences</a> pour alimenter la fiche.</p>
                    <?php endif; ?>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Prénom</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($civilIdentity['first_name'] !== '' ? $civilIdentity['first_name'] : '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nom</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($civilIdentity['last_name'] !== '' ? $civilIdentity['last_name'] : '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">E-mail</p><p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($targetUser['email']) ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut compte</p><p class="text-sm font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?>"><?= htmlspecialchars($accountStatusFr((string) ($targetUser['status'] ?? ''))) ?></p></div>
                        <?php if (!empty($userProfile['birth_date'])):
                            $birthRaw = trim((string) $userProfile['birth_date']);
                            $birthTs = $birthRaw !== '' ? strtotime($birthRaw) : false;
                            $birthDisplay = $birthTs ? date('d/m/Y', $birthTs) : $birthRaw;
                            ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date de naissance</p><p class="text-sm text-slate-800"><?= htmlspecialchars($birthDisplay) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['nationality'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nationalité (dossier)</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['nationality'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['phone'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Téléphone</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['phone'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['arma_callsign'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Indicatif Arma (préférences)</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['arma_callsign'])) ?></p></div>
                        <?php endif; ?>
                        <?php if ($canEditProfile && !empty(trim((string) ($userProfile['emergency_contact'] ?? '')))): ?>
                        <div class="md:col-span-2"><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Contact d’urgence</p><p class="text-sm text-slate-800 leading-relaxed"><?= nl2br(htmlspecialchars(trim((string) $userProfile['emergency_contact']))) ?></p></div>
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
                    <div class="mt-8 border-t border-slate-100 pt-6">
                        <h3 class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500 mb-4">Coordonnées visibles sur cette fiche</h3>
                        <div class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                            <?php if (!empty($showEmailInContact)): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">E-mail</p><p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($targetUser['email'] ?? '')) ?></p></div>
                            <?php else: ?>
                            <p class="text-xs text-slate-600">L’adresse e-mail est masquée selon les préférences du titulaire.</p>
                            <?php endif; ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut du compte</p><p class="text-sm font-semibold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-600' ?>"><?= htmlspecialchars($accountStatusFr((string) ($targetUser['status'] ?? ''))) ?></p></div>
                        </div>
                    </div>
                </section>
                <?php elseif ($privatePersonnelIdentity): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-4">Coordonnées</h2>
                    <div class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                        <?php if (!empty($showEmailInContact)): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">E-mail</p><p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($targetUser['email'] ?? '')) ?></p></div>
                        <?php else: ?>
                        <p class="text-xs text-slate-600">L’adresse e-mail n’est pas affichée sur cette fiche.</p>
                        <?php endif; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut du compte</p><p class="text-sm font-semibold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-600' ?>"><?= htmlspecialchars($accountStatusFr((string) ($targetUser['status'] ?? ''))) ?></p></div>
                    </div>
                </section>
                <?php else: ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-3">Informations réservées</h2>
                    <p class="text-sm text-slate-600 leading-relaxed">L’identité civile, l’état civil administratif et le détail des candidatures de recrutement ne sont pas affichés aux autres membres. Seuls le titulaire du dossier et le personnel habilité (gestion des effectifs ou accès RH sensible) peuvent les consulter.</p>
                </section>
                <?php endif; ?>

                <?php foreach ($adminPanels as $panel): ?>
                <?php $panelId = (int)$panel['id']; $data = $adminDataByPanel[$panelId] ?? []; ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6"><?= htmlspecialchars($panel['name']) ?></h2>
                    <?php if (empty($data)): ?>
                    <p class="text-sm text-slate-500">Aucune information saisie pour ce bloc.</p>
                    <?php else: ?>
                    <div class="space-y-5">
                        <?php foreach ($data as $key => $value): ?>
                        <?php if ($value === null || $value === '') {
                            continue;
                        } ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/40 px-4 py-3">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 mb-2"><?= htmlspecialchars(is_string($key) ? $key : 'Information') ?></p>
                            <div class="text-sm font-medium text-slate-900"><?php personnel_file_render_admin_value($value); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>
