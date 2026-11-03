<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/adequacy.php';

$m = is_array($member ?? null) ? $member : [];
$id = (int) ($m['id'] ?? 0);
$assignments = is_array($memberAssignments ?? null) ? $memberAssignments : [];
$roleNames = is_array($memberRoleNames ?? null) ? $memberRoleNames : [];
$jobRoles = is_array($memberJobRoles ?? null) ? $memberJobRoles : [];
$jobDecorations = is_array($memberJobDecorations ?? null) ? $memberJobDecorations : [];
$jobRoleOptions = is_array($jobRoleOptions ?? null) ? $jobRoleOptions : [];
$jobRoleMax = max(1, (int) ($jobRoleMax ?? 5));
$jobRolesAvailable = (bool) ($jobRolesAvailable ?? false);
$units = is_array($orgUnits ?? null) ? $orgUnits : [];
$orgRoles = is_array($orgRoles ?? null) ? $orgRoles : [];
$memberRoleIds = array_values(array_unique(array_map('intval', $memberRoleIds ?? [])));
$profile = is_array($memberPersonnelProfile ?? null) ? $memberPersonnelProfile : [];
$elevationCatalog = is_array($elevationCatalog ?? null) ? $elevationCatalog : [];
$grades = is_array($elevationCatalog['grades'] ?? null) ? $elevationCatalog['grades'] : [];
$canEditProfiles = (bool) ($canEditProfiles ?? false);
$canManageStatus = (bool) ($canManageStatus ?? false);
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$canManageRoles = (bool) ($canManageRoles ?? false);
$canManageGrades = (bool) ($canManageGrades ?? false);
$canRequestElevation = (bool) ($canRequestElevation ?? false);
$csrfToken = (string) ($csrfToken ?? '');
$communityName = trim((string) ($communityName ?? ($m['community_name'] ?? 'Communauté')));
$elevationCooldownSeconds = (int) ($elevationCooldownSeconds ?? 0);
$elevationHistory = is_array($elevationHistory ?? null) ? $elevationHistory : [];
$latestDeparture = is_array($latestDeparture ?? null) ? $latestDeparture : null;
$orgFoundingDate = trim((string) ($orgFoundingDate ?? ''));
$qualifications = is_array($memberQualifications ?? null) ? $memberQualifications : [];
$absences = is_array($memberAbsences ?? null) ? $memberAbsences : [];
$hrDocuments = is_array($memberHrDocuments ?? null) ? $memberHrDocuments : [];
$mobilityRequests = is_array($memberMobilityRequests ?? null) ? $memberMobilityRequests : [];
$orgHistory = is_array($memberOrgHistory ?? null) ? $memberOrgHistory : [];
$serviceHistory = is_array($memberServiceHistory ?? null) ? $memberServiceHistory : [];
$stageBilans = is_array($memberStageBilans ?? null) ? $memberStageBilans : [];
$hrDocumentTypeLabels = is_array($hrDocumentTypeLabels ?? null) ? $hrDocumentTypeLabels : [];
$mobilityTypeLabels = is_array($mobilityTypeLabels ?? null) ? $mobilityTypeLabels : [];
$absenceReasonLabels = is_array($absenceReasonLabels ?? null) ? $absenceReasonLabels : [];
$dutyPosition = trim((string) ($dutyPosition ?? ''));
$remainingTrainingDays = max(0, (int) ($remainingTrainingDays ?? 0));
$gradeGroups = is_array($elevationCatalog['grade_groups'] ?? null)
    ? $elevationCatalog['grade_groups']
    : \App\Support\GradeSelectOptions::groups($grades);
$jobRolePickerGroups = is_array($jobRolePickerGroups ?? null) ? $jobRolePickerGroups : [];
$civilStatusOptions = is_array($civilStatusOptions ?? null) ? $civilStatusOptions : [];
$memberLanguage = is_array($memberLanguage ?? null) ? $memberLanguage : ['primary' => 'fr', 'others' => [], 'source' => 'community'];
$langPrimary = (string) ($memberLanguage['primary'] ?? 'fr');
$langOthers = is_array($memberLanguage['others'] ?? null) ? $memberLanguage['others'] : [];
$langSource = (string) ($memberLanguage['source'] ?? 'community');
$communityLanguageCodes = is_array($communityLanguageCodes ?? null) && $communityLanguageCodes !== [] ? $communityLanguageCodes : [$langPrimary];
// Une langue déjà enregistrée reste proposée même si la communauté ne la liste plus.
$langChoices = array_values(array_unique(array_merge($communityLanguageCodes, [$langPrimary], $langOthers)));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$elevationCooldownLabel = static function (int $seconds): string {
    $hours = max(1, (int) ceil($seconds / 3600));
    if ($hours < 24) {
        return $hours . ' heure' . ($hours > 1 ? 's' : '');
    }
    $days = max(1, (int) ceil($hours / 24));

    return $days . ' jour' . ($days > 1 ? 's' : '');
};
$departureReasonLabels = [
    'end_of_engagement' => 'Fin d’engagement',
    'exclusion' => 'Exclusion',
    'pause' => 'Pause',
    'other' => 'Autre',
];
$elevStatusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente',
        'in_review' => 'En cours d’examen',
        'approved' => 'Acceptée',
        'rejected' => 'Refusée',
        default => $status,
    };
};
$statusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        default => $raw !== '' ? $raw : '—',
    };
};
$fmtDate = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
        return '';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y', $ts) : $raw;
};

$status = (string) ($m['status'] ?? '');
$display = trim((string) ($m['display_name'] ?? ''));
$callsign = trim((string) ($m['callsign'] ?? ''));
$email = function_exists('email_for_display')
    ? email_for_display((string) ($m['email'] ?? ''))
    : (string) ($m['email'] ?? '');
$name = $display !== '' ? $display : ($callsign !== '' ? $callsign : $email);
$grade = trim((string) ($m['grade_short'] ?? ''));
if ($grade === '') {
    $grade = trim((string) ($m['grade_long'] ?? ''));
}
$gradeId = (int) ($m['grade_id'] ?? 0);
$fonction = trim((string) ($m['job_role_display'] ?? ''));
$unit = trim((string) ($m['unit_name'] ?? ''));
$unitId = (int) ($m['unit_id'] ?? 0);
$nicknamePrimary = trim((string) ($profile['nickname_primary'] ?? ''));
$matricule = trim((string) ($m['matricule_internal'] ?? ($profile['matricule_internal'] ?? '')));
$athenaId = trim((string) ($m['athena_identifier'] ?? ''));
$steamId = trim((string) ($m['steam_id'] ?? ''));
$lastLogin = $fmtDate((string) ($m['last_login_at'] ?? ''));
$createdAt = $fmtDate((string) ($m['created_at'] ?? ''));
$seniorityLabel = trim((string) ($m['seniority_label'] ?? ''));
$communityTenureLabel = trim((string) ($m['seniority_community_label'] ?? ''));
$preTenureLabel = trim((string) ($m['seniority_pre_platform_label'] ?? ''));
$enlistmentStart = trim((string) ($m['enlistment_date_resolved'] ?? ($profile['enlistment_date'] ?? '')));
$prePlatformStart = trim((string) ($m['pre_platform_start'] ?? ''));
$portraitSource = [
    'character_portrait_path' => trim((string) ($profile['character_portrait_path'] ?? $m['character_portrait_path'] ?? '')),
];
$avatarUrl = function_exists('personnel_operator_portrait_url')
    ? (string) (personnel_operator_portrait_url($portraitSource) ?? '')
    : '';
if ($avatarUrl !== '' && str_contains($avatarUrl, 'inconnu.svg')) {
    $avatarUrl = '';
}
$initials = function_exists('user_display_initials')
    ? user_display_initials($name !== '' ? $name : $email, 2)
    : mb_strtoupper(mb_substr($name !== '' ? $name : $email, 0, 2, 'UTF-8'), 'UTF-8');

$medalRackItems = [];
$medalRackJson = $profile['medal_rack_json'] ?? null;
if (is_string($medalRackJson) && $medalRackJson !== '') {
    $decodedMedals = json_decode($medalRackJson, true);
    if (is_array($decodedMedals)) {
        foreach ($decodedMedals as $medalItem) {
            $medalItem = trim((string) $medalItem);
            if ($medalItem !== '') {
                $medalRackItems[] = $medalItem;
            }
        }
    }
}

?>
<?php
    $civilFields = [
        'birth_place' => 'Lieu de naissance',
        'family_situation' => 'Situation familiale',
        'sex' => 'Sexe',
        'nationality' => 'Nationalité',
        'blood_type' => 'Groupe sanguin',
        'languages' => 'Langues parlées',
        'weight_kg' => 'Poids',
        'height_cm' => 'Taille',
        'nickname_primary' => 'Surnom',
        'motto' => 'Devise',
    ];
    $civilValue = static function (string $key) use ($profile): string {
        $v = trim((string) ($profile[$key] ?? ''));
        if ($key === 'weight_kg' && $v !== '' && (int) $v > 0) {
            return (int) $v . ' kg';
        }
        if ($key === 'height_cm') {
            return (int) $v > 0 ? (int) $v . ' cm' : '';
        }

        return $v;
    };
    $civilFilled = 0;
    foreach (array_keys($civilFields) as $civilKey) {
        if ($civilValue($civilKey) !== '') {
            $civilFilled++;
        }
    }
    ?>
<?php
    $renderRhList = static function (array $rows, callable $label): void {
        if ($rows === []) {
            echo '<p class="eff-card__lead">Aucune donnée enregistrée.</p>';
            return;
        }
        echo '<ul class="eff-card__list">';
        foreach ($rows as $row) {
            echo '<li>' . htmlspecialchars($label($row), ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
    };
    ?>
<?php
$missing = [];
if ($fonction === '' && $jobRoles === []) {
    $missing[] = ['Fonction', '#eff-dlg-jobs', $canManageAssignments && $jobRolesAvailable];
}
if ($grade === '') {
    $missing[] = ['Grade', '#grade', $canManageGrades];
}
if ($assignments === [] && $unit === '') {
    $missing[] = ['Unité', '#unite', $canManageAssignments];
}
if ($avatarUrl === '') {
    $missing[] = ['Image opérateur', '#eff-dlg-portrait', $canEditProfiles];
}
if ($civilFilled < count($civilFields)) {
    $missing[] = ['État civil (' . $civilFilled . '/' . count($civilFields) . ')', '#eff-dlg-civil', $canEditProfiles];
}
$checks = 5;
$fileScore = (int) round((($checks - count($missing)) / $checks) * 100);
?>
<?php
// Mise en page « Fiche Membre » (maquette 2026-11) : bandeau, chiffres clés, rubriques à onglets
// et colonne latérale. Toute la largeur disponible est utilisée ; seules des données réelles sont affichées.
$ico = static function (string $name): string {
    $paths = [
        'id' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="12" r="2.5"/><path d="M14 10h4M14 14h3"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'shield' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/>',
        'up' => '<path d="m6 15 6-6 6 6"/>',
        'pen' => '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'exit' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'warn' => '<path d="M12 4 2 20h20L12 4Z"/><path d="M12 10v4M12 17v.5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.5"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7L12 6.3"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    ];

    return '<svg class="eff-fm-i" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
};

$gradeLong = trim((string) ($m['grade_long'] ?? ''));
$bannerUrl = '';
if (function_exists('user_media_public_url') && trim((string) ($m['profile_banner_url'] ?? '')) !== '') {
    $bannerUrl = (string) (user_media_public_url((string) $m['profile_banner_url']) ?? '');
}
$lastLoginDays = null;
$lastLoginTs = strtotime((string) ($m['last_login_at'] ?? ''));
if ($lastLoginTs && $lastLoginTs > 0) {
    $lastLoginDays = max(0, (int) floor((time() - $lastLoginTs) / 86400));
}
$lastLoginAgo = $lastLoginDays === null ? 'jamais connecté' : match (true) {
    $lastLoginDays === 0 => 'aujourd’hui',
    $lastLoginDays === 1 => 'hier',
    default => 'il y a ' . $lastLoginDays . ' jours',
};
// Échéances des qualifications : calculées par le service existant, sans nouvelle requête.
$qualStates = [];
$qualExpiring = [];
foreach ($qualifications as $qi => $q) {
    $state = ['code' => '', 'label' => '', 'days_remaining' => null];
    if (($qualificationTemporal ?? null) instanceof \App\Services\Personnel\QualificationTemporalStatusService) {
        $state = $qualificationTemporal->resolve($q);
    }
    $qualStates[$qi] = $state;
    if (in_array((string) ($state['code'] ?? ''), ['expiring_soon', 'expired_grace', 'expired'], true)) {
        $qualExpiring[] = [
            trim((string) ($q['definition_name'] ?? $q['qualification_name'] ?? $q['name'] ?? 'Qualification')),
            (string) ($state['label'] ?? ''),
        ];
    }
}
$civilMissing = count($civilFields) - $civilFilled;
$fileTitle = $fileScore >= 100 ? 'Dossier complet' : ($fileScore >= 60 ? 'Presque complet' : 'À compléter');
$primaryAssignment = null;
foreach ($assignments as $a) {
    if (!empty($a['is_primary'])) {
        $primaryAssignment = $a;
        break;
    }
}
$commandChain = is_array($memberCommandChain ?? null) ? $memberCommandChain : [];
$reportsTo = trim((string) ($commandChain['reports_to_label'] ?? ''));
$chainLabels = is_array($commandChain['chain_labels'] ?? null) ? $commandChain['chain_labels'] : [];
$commands = is_array($commandChain['commands'] ?? null) ? $commandChain['commands'] : [];
$canEditJobs = $canManageAssignments && $jobRolesAvailable && $jobRolePickerGroups !== [];
$monogram = static function (string $label): string {
    $words = preg_split('/[\s\-’\']+/u', trim($label)) ?: [];
    $out = '';
    foreach ($words as $w) {
        if ($w !== '' && mb_strlen($out, 'UTF-8') < 3) {
            $out .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
        }
    }

    return $out !== '' ? $out : 'Q';
};

// Données calculées (présence, rang, équipe, grades, éligibilité, sanctions) : MemberFileInsightsService.
$insights = is_array($memberInsights ?? null) ? $memberInsights : [];
$attendance = is_array($insights['attendance'] ?? null) ? $insights['attendance'] : null;
$rankInfo = is_array($insights['rank'] ?? null) ? $insights['rank'] : null;
$team = is_array($insights['team'] ?? null) ? $insights['team'] : null;
$gradeEntries = is_array($insights['grades']['entries'] ?? null) ? $insights['grades']['entries'] : [];
$gradeSince = trim((string) ($insights['grades']['current_since'] ?? ''));
$eligibility = is_array($insights['eligibility'] ?? null) ? $insights['eligibility'] : null;
$sanctions = is_array($insights['sanctions'] ?? null) ? $insights['sanctions'] : null;
$canViewSanctions = (bool) ($canViewSanctions ?? false) && $sanctions !== null;
$canManageSanctions = (bool) ($canManageSanctions ?? false);
$canSetElevationCriteria = (bool) ($canSetElevationCriteria ?? false);
$canEditOrbat = (bool) ($canEditOrbat ?? false);
$eligibilityStatus = (string) ($eligibility['status'] ?? '');
$isEligible = $eligibility !== null && $eligibilityStatus === \App\Services\Effectifs\ElevationEligibility::ELIGIBLE;
$attendanceRate = $attendance['rate_90'] ?? null;
$attendanceDelta = $attendance['delta_points'] ?? null;
$sanctionsActive = (int) ($sanctions['active'] ?? 0);
?>
<div class="eff-fm">
<section class="eff-fiche-hero eff-fm-hero" aria-label="En-tête du dossier">
    <div class="eff-fm-hero__band<?= $bannerUrl !== '' ? ' has-image' : '' ?>"<?php if ($bannerUrl !== ''): ?> style="--eff-fm-band: url('<?= $h($bannerUrl) ?>')"<?php endif; ?>>
        <nav class="eff-fm-hero__nav" aria-label="Retour">
            <a class="eff-fm-hero__back" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">← Tableur des effectifs</a>
            <?php if ($rankInfo !== null): ?>
                <span class="eff-fm-hero__rank" title="Rang d’ancienneté : membres actifs de la communauté classés par date d’engagement, du plus ancien au plus récent.">Membre <?= (int) $rankInfo['rank'] ?> / <?= (int) $rankInfo['total'] ?></span>
            <?php endif; ?>
        </nav>
        <?php if ($matricule !== '' || $athenaId !== ''): ?>
            <p class="eff-fm-hero__mat">Matricule <?= $h($matricule !== '' ? $matricule : $athenaId) ?></p>
        <?php endif; ?>
    </div>
    <div class="eff-fm-hero__body">
        <div class="eff-fm-portrait">
            <span class="eff-fm-portrait__img" aria-hidden="true">
                <?php if ($avatarUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async" data-img-fallback="portrait" data-img-initials="<?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>" data-img-label="Portrait opérateur indisponible">
                <?php else: ?>
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </span>
            <?php if ($grade !== ''): ?><span class="eff-fm-portrait__rank"><?= $h($grade) ?></span><?php endif; ?>
            <?php if ($canEditProfiles): ?>
                <button type="button" class="eff-fm-portrait__chg" data-dialog-open="eff-dlg-portrait" id="edit-portrait">Changer l’image</button>
            <?php endif; ?>
        </div>
        <div class="eff-fm-who">
            <p class="eff-fm-kicker">Fiche membre<?php if ($gradeLong !== '' || $grade !== ''): ?> · <?= $h($gradeLong !== '' ? $gradeLong : $grade) ?><?php endif; ?></p>
            <h1 class="eff-fm-who__name"><?= staff_name($name, $id, true) ?></h1>
            <p class="eff-fm-meta">
                <?php if ($callsign !== ''): ?>
                    <span><?= $ico('id') ?>Indicatif <strong><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></strong></span>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <span><?= $ico('mail') ?><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span><?= $ico('shield') ?><?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <div class="eff-fm-chips">
                <span class="eff-fm-chip <?= $status === 'active' ? 'is-ok' : 'is-warn' ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($unit !== ''): ?>
                    <span class="eff-fm-chip is-info"><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <span class="eff-fm-chip is-warn">Sans unité</span>
                <?php endif; ?>
                <?php if ($fonction !== ''): ?>
                    <span class="eff-fm-chip"><?= $h($fonction) ?></span>
                <?php endif; ?>
                <?php if ($seniorityLabel !== '' && $seniorityLabel !== '—'): ?>
                    <span class="eff-fm-chip"><?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?> d’ancienneté</span>
                <?php endif; ?>
                <?php if ($isEligible): ?>
                    <a class="eff-fm-chip is-elig" href="#grade">Élévation éligible</a>
                <?php endif; ?>
                <?php if ($canViewSanctions && $sanctionsActive > 0): ?>
                    <a class="eff-fm-chip is-warn" href="#sanctions"><?= $sanctionsActive ?> mesure<?= $sanctionsActive > 1 ? 's' : '' ?> en vigueur</a>
                <?php endif; ?>
            </div>
            <div class="eff-fm-actions">
                <?php if ($canRequestElevation && $elevationCooldownSeconds < 1): ?>
                    <button type="button" class="eff-fm-btn is-dark" data-dialog-open="eff-dlg-elevation"><?= $ico('up') ?>Demander une élévation</button>
                <?php endif; ?>
                <?php if ($canEditJobs): ?>
                    <button type="button" class="eff-fm-btn" data-dialog-open="eff-dlg-jobs"><?= $ico('pen') ?>Modifier les fonctions</button>
                <?php endif; ?>
                <?php if ($canEditProfiles): ?>
                    <button type="button" class="eff-fm-btn" data-dialog-open="eff-dlg-civil"><?= $ico('user') ?>État civil</button>
                <?php endif; ?>
                <a class="eff-fm-btn" href="<?= htmlspecialchars(url('personnel/' . $id), ENT_QUOTES, 'UTF-8') ?>"><?= $ico('eye') ?>Voir la fiche</a>
                <?php if ($canManageStatus): ?>
                    <button type="button" class="eff-fm-btn is-ghost" data-dialog-open="eff-dlg-depart"><?= $ico('exit') ?>Départ</button>
                <?php endif; ?>
            </div>
        </div>
        <aside class="eff-fiche-score eff-fm-complete<?= $fileScore < 60 ? ' is-low' : '' ?>" aria-label="Complétude du dossier">
            <div class="eff-fm-complete__row">
                <span class="eff-fm-ring" style="--p:<?= $fileScore ?>" role="img" aria-label="Dossier rempli à <?= $fileScore ?> %"><b><?= $fileScore ?> %</b></span>
                <div>
                    <p class="eff-fm-k">Dossier</p>
                    <h2 class="eff-fm-complete__title"><?= $h($fileTitle) ?></h2>
                    <p class="eff-fm-complete__lead"><?= $missing === [] ? 'Tout est renseigné.' : count($missing) . ' élément' . (count($missing) > 1 ? 's' : '') . ' à compléter.' ?></p>
                </div>
            </div>
            <?php if ($missing !== []): ?>
                <ul class="eff-fm-todo">
                    <?php foreach ($missing as [$mLabel, $mHref, $mCan]): ?>
                        <li>
                            <?php if ($mCan && str_starts_with($mHref, '#eff-dlg-')): ?>
                                <button type="button" class="eff-fm-todo__item" data-dialog-open="<?= $h(substr($mHref, 1)) ?>"><?= $h($mLabel) ?><em>Compléter</em></button>
                            <?php elseif ($mCan): ?>
                                <a class="eff-fm-todo__item" href="<?= $h($mHref) ?>"><?= $h($mLabel) ?><em>Compléter</em></a>
                            <?php else: ?>
                                <span class="eff-fm-todo__item is-static"><?= $h($mLabel) ?><em>Manquant</em></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </div>
    <dl class="eff-fm-kpis" aria-label="Chiffres clés">
        <div class="eff-fm-kpi">
            <dt class="eff-fm-k">Ancienneté</dt>
            <dd class="eff-fm-kpi__v"><?= $h($seniorityLabel !== '' && $seniorityLabel !== '—' ? $seniorityLabel : '—') ?></dd>
            <dd class="eff-fm-kpi__s"><?= $h($enlistmentStart !== '' ? 'depuis le ' . $fmtDate($enlistmentStart) : 'date d’arrivée non renseignée') ?></dd>
        </div>
        <div class="eff-fm-kpi">
            <dt class="eff-fm-k">Qualifications</dt>
            <dd class="eff-fm-kpi__v"><?= count($qualifications) ?></dd>
            <dd class="eff-fm-kpi__s"><?= $qualExpiring === [] ? 'aucune échéance proche' : count($qualExpiring) . ' à renouveler' ?></dd>
        </div>
        <div class="eff-fm-kpi">
            <dt class="eff-fm-k">Présence 90 j</dt>
            <?php if ($attendance !== null && $attendanceRate !== null): ?>
                <dd class="eff-fm-kpi__v<?= (int) $attendanceRate >= 75 ? ' is-good' : '' ?>"><?= (int) $attendanceRate ?> %</dd>
                <dd class="eff-fm-kpi__s"><?php if ($attendanceDelta !== null): ?><?= $h(((int) $attendanceDelta >= 0 ? '+' : '−') . abs((int) $attendanceDelta) . ' pts vs trimestre précédent') ?><?php else: ?><?= (int) ($attendance['present_90'] ?? 0) ?> / <?= (int) ($attendance['sessions_90'] ?? 0) ?> sessions<?php endif; ?></dd>
            <?php else: ?>
                <dd class="eff-fm-kpi__v">—</dd>
                <dd class="eff-fm-kpi__s">aucune session sur 90 jours</dd>
            <?php endif; ?>
        </div>
        <div class="eff-fm-kpi">
            <dt class="eff-fm-k">Dernière connexion</dt>
            <dd class="eff-fm-kpi__v"><?= $h($lastLogin !== '' ? substr($lastLogin, 0, 5) : '—') ?></dd>
            <dd class="eff-fm-kpi__s"><?= $h($lastLoginAgo) ?></dd>
        </div>
        <?php if ($canViewSanctions): ?>
        <div class="eff-fm-kpi">
            <dt class="eff-fm-k">Sanctions</dt>
            <dd class="eff-fm-kpi__v<?= $sanctionsActive > 0 ? ' is-warn' : '' ?>"><?= $sanctionsActive ?></dd>
            <dd class="eff-fm-kpi__s"><?= (int) ($sanctions['total'] ?? 0) === 0 ? 'dossier vierge' : ($sanctionsActive > 0 ? 'en vigueur' : 'aucune en vigueur') . ' · ' . (int) $sanctions['total'] . ' au total' ?></dd>
        </div>
        <?php else: ?>
        <div class="eff-fm-kpi">
            <dt class="eff-fm-k">Absences</dt>
            <dd class="eff-fm-kpi__v"><?= count($absences) ?></dd>
            <dd class="eff-fm-kpi__s"><?= $absences === [] ? 'aucune déclarée' : 'enregistrée' . (count($absences) > 1 ? 's' : '') ?></dd>
        </div>
        <?php endif; ?>
    </dl>
</section>

<nav class="bo-eff-jump bo-eff-jump--v2 eff-fm-tabs" aria-label="Dossier RH complet : rubriques" data-eff-fm-tabs>
    <a href="#identite">Identité<?php if ($civilMissing > 0): ?> <span class="eff-fm-tabs__n"><?= $civilMissing ?></span><?php endif; ?></a>
    <a href="#poste">Poste</a>
    <a href="#carriere">Carrière</a>
    <a href="#suivi-rh">Suivi RH</a>
    <?php if (is_array($memberPapers ?? null)): ?><a href="#papiers">Identité &amp; papiers</a><?php endif; ?>
    <a href="#historique-rh">Historique</a>
</nav>

<div class="eff-fm-layout">
<div class="eff-fm-main">
    <section class="eff-fm-sec" id="identite" aria-labelledby="identite-titre">
        <header class="eff-fm-sec__head">
            <h2 id="identite-titre">Identité</h2>
            <p>Compte joueur et personnage incarné en mission.</p>
        </header>
        <div class="eff-fm-cards">
    <article class="eff-fm-card" id="situation">
        <div class="eff-fm-card__head">
            <h3>Situation</h3>
            <span class="eff-fm-lock"><?= $ico('lock') ?>Géré par le système</span>
        </div>
        <dl class="eff-fm-fields">
            <div class="eff-fm-f"><dt>Nom affiché</dt><dd><?= htmlspecialchars($display !== '' ? $display : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div class="eff-fm-f"><dt>Identifiant</dt><dd class="eff-fm-mono"><?= htmlspecialchars($athenaId !== '' ? $athenaId : ($matricule !== '' ? $matricule : '—'), ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div class="eff-fm-f"><dt>Surnom</dt><dd><?= htmlspecialchars($nicknamePrimary !== '' ? $nicknamePrimary : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div class="eff-fm-f"><dt>Fonction</dt><dd><?= htmlspecialchars($fonction !== '' ? $fonction : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div class="eff-fm-f"><dt>Compte créé</dt><dd><?= htmlspecialchars($createdAt !== '' ? $createdAt : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div class="eff-fm-f"><dt>Dernière connexion</dt><dd><?= htmlspecialchars($lastLogin !== '' ? $lastLogin : 'Jamais', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <?php if ($steamId !== ''): ?>
            <div class="eff-fm-f"><dt>Liaison Steam</dt><dd class="eff-fm-steam"><?= $ico('link') ?>Compte lié</dd></div>
            <?php endif; ?>
            <?php if ($email !== ''): ?>
            <div class="eff-fm-f"><dt>Courriel</dt><dd><?= $h($email) ?></dd></div>
            <?php endif; ?>
            <?php if ($medalRackItems !== []): ?>
            <div class="eff-fm-f eff-fm-f--wide">
                <dt>Décorations</dt>
                <dd>
                    <?php
                    $medalRackResolved = \App\Support\DecorationCatalog::resolveLines(
                        $medalRackItems,
                        (int) (\App\Core\Session::get('tenant_id') ?? 0) ?: null
                    );
                    $names = array_map(static fn (array $item): string => (string) ($item['name'] ?? ''), $medalRackResolved);
                    echo htmlspecialchars(implode(' · ', array_filter($names)), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="dk-kit" style="margin-top:12px;">
                        <?php
                        $dkItems = $medalRackResolved;
                        $dkShowDemoDevices = false;
                        $dkShowDetail = false;
                        $dkCaption = '';
                        require base_path('views/partials/personnel/decoration_rack.php');
                        ?>
                    </div>
                </dd>
            </div>
            <?php endif; ?>
        </dl>
    </article>
    <article class="eff-fm-card" id="modifier-dossier">
        <div class="eff-fm-card__head">
            <h3>État civil du personnage</h3>
            <?php if ($canEditProfiles): ?>
                <button type="button" class="eff-fm-btn is-sm" data-dialog-open="eff-dlg-civil">Modifier</button>
            <?php endif; ?>
        </div>
        <p class="eff-fm-lead">Identité jouée en mission (<?= $civilFilled ?>/<?= count($civilFields) ?> renseignés). L’identité réelle du joueur reste privée et n’apparaît pas ici.</p>
        <dl class="eff-fm-fields">
            <?php foreach ($civilFields as $civilKey => $civilLabel): ?>
                <?php $cv = $civilValue($civilKey); ?>
                <div class="eff-fm-f<?= $cv === '' ? ' is-empty' : '' ?>">
                    <dt><?= $h($civilLabel) ?></dt>
                    <dd>
                        <?php if ($cv !== ''): ?>
                            <?= $h($cv) ?>
                        <?php elseif ($canEditProfiles): ?>
                            <button type="button" class="eff-fm-add" data-dialog-open="eff-dlg-civil">+ Ajouter</button>
                        <?php else: ?>
                            Non renseigné
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endforeach; ?>
            <?php foreach (['drug_intolerances' => 'Intolérances médicamenteuses', 'allergies' => 'Allergies'] as $medKey => $medLabel): ?>
                <?php $mv = trim((string) ($profile[$medKey] ?? '')); ?>
                <div class="eff-fm-f">
                    <dt><?= $h($medLabel) ?></dt>
                    <dd><?= $h($mv !== '' ? $mv : 'Aucune connue') ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </article>
    <article class="eff-fm-card" id="langues">
        <div class="eff-fm-card__head">
            <h3>Langue du joueur</h3>
            <?php if ($canEditProfiles): ?>
                <button type="button" class="eff-fm-btn is-sm" data-dialog-open="eff-dlg-langues">Modifier</button>
            <?php endif; ?>
        </div>
        <p class="eff-fm-lead">La langue dans laquelle la personne joue et communique en opération (radio, briefings). Elle sert à composer les équipes.</p>
        <div class="eff-fm-langs">
            <div class="eff-fm-lang is-primary">
                <span class="eff-fm-lang__flag" aria-hidden="true"><?= $h(\App\Support\CommunityLanguages::flag($langPrimary)) ?></span>
                <span><strong><?= $h(\App\Support\CommunityLanguages::fullLabel($langPrimary)) ?></strong><small>Principale · radio et briefings</small></span>
            </div>
            <?php foreach ($langOthers as $langOther): ?>
                <div class="eff-fm-lang">
                    <span class="eff-fm-lang__flag" aria-hidden="true"><?= $h(\App\Support\CommunityLanguages::flag((string) $langOther)) ?></span>
                    <span><strong><?= $h(\App\Support\CommunityLanguages::label((string) $langOther)) ?></strong><small>Comprend aussi</small></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($langOthers === []): ?><p class="eff-fm-hint">Aucune autre langue indiquée.</p><?php endif; ?>
        <?php if ($langSource !== 'member'): ?>
            <p class="eff-fm-hint"><?= $h($langSource === 'interface' ? 'Déduite de la langue de son interface' : 'Langue de la communauté, à confirmer') ?></p>
        <?php endif; ?>
    </article>
    <?php // Portée isolée : la liste des liens ne doit pas écraser les variables de la fiche.
    (static function (array $memberInvitePanel, int $id): void {
        require __DIR__ . '/partials/member_invites.php';
    })(is_array($memberInvitePanel ?? null) ? $memberInvitePanel : [], $id); ?>
        </div>
    </section>

    <section class="eff-fm-sec" id="poste" aria-labelledby="poste-titre">
        <header class="eff-fm-sec__head">
            <h2 id="poste-titre">Poste</h2>
            <p>Où elle sert, ce qu’elle fait et ce qu’elle peut faire sur le site.</p>
        </header>
        <div class="eff-fm-cards">
    <article class="eff-fm-card eff-fm-card--wide" id="unite">
        <div class="eff-fm-card__head">
            <h3>Unité</h3>
        </div>
        <?php if ($primaryAssignment !== null || $unit !== ''): ?>
            <?php $unitPath = is_array($team['path'] ?? null) && $team['path'] !== [] ? $team['path'] : [(string) ($primaryAssignment['unit_name'] ?? $unit)]; ?>
            <p class="eff-fm-path" aria-label="Affectation principale">
                <span><?= $h($communityName) ?></span>
                <?php foreach ($unitPath as $pi => $pathPart): ?>
                    <i aria-hidden="true">›</i><span<?= $pi === count($unitPath) - 1 ? ' class="is-cur"' : '' ?>><?= $h($pathPart) ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php if ($team !== null): ?>
            <?php
            $teamSeats = is_array($team['seats'] ?? null) ? $team['seats'] : [];
            $teamOthers = is_array($team['others'] ?? null) ? $team['others'] : [];
            $teamUnitUrl = url('back-office/organisation/unites/' . (int) ($team['unit_id'] ?? 0)) . '#postes';
            ?>
            <div class="eff-fm-team" id="equipe">
                <?php if (!empty($team['has_posts'])): ?>
                    <p class="eff-fm-k">Équipe — <?= (int) $team['filled'] ?> / <?= (int) $team['authorized'] ?> postes pourvus<?php if ((int) $team['vacant'] > 0): ?> · <?= $h(sprintf((int) $team['vacant'] > 1 ? '%d vacants' : '%d vacant', (int) $team['vacant'])) ?><?php endif; ?></p>
                    <ul class="eff-fm-slots">
                        <?php foreach ($teamSeats as $seat): ?>
                            <li class="eff-fm-slot<?= !empty($seat['is_me']) ? ' is-me' : '' ?><?= !empty($seat['vacant']) ? ' is-vacant' : '' ?>">
                                <?php if (!empty($seat['vacant'])): ?>
                                    <b>Poste vacant</b>
                                <?php elseif (!empty($seat['is_me'])): ?>
                                    <b><?= $h($seat['label']) ?></b>
                                <?php else: ?>
                                    <b><a href="<?= $h(effectifs_workspace_url('membres/' . (int) $seat['user_id'])) ?>"><?= $h($seat['label']) ?></a></b>
                                <?php endif; ?>
                                <small><?= $h($seat['title']) ?><?= !empty($seat['is_me']) ? ' · cette fiche' : '' ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="eff-fm-k">Équipe</p>
                    <p class="eff-fm-hint">Aucun poste n’est prévu pour cette unité : le nombre de postes pourvus ne peut pas être calculé.<?php if ($canEditOrbat): ?> <a href="<?= $h($teamUnitUrl) ?>">Définir les postes de l’unité</a><?php endif; ?></p>
                <?php endif; ?>
                <?php if ($teamOthers !== []): ?>
                    <p class="eff-fm-hint"><?= !empty($team['has_posts']) ? 'Sans poste dans l’unité' : 'Membres de l’unité' ?> :
                        <?php foreach ($teamOthers as $oi => $other): ?><?= $oi > 0 ? ', ' : '' ?><?php if (!empty($other['is_me'])): ?><strong><?= $h($other['label']) ?></strong><?php else: ?><a href="<?= $h(effectifs_workspace_url('membres/' . (int) $other['user_id'])) ?>"><?= $h($other['label']) ?></a><?php endif; ?><?php if ($other['job_label'] !== ''): ?> (<?= $h($other['job_label']) ?>)<?php endif; ?><?php endforeach; ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($team['has_posts']) && $canEditOrbat): ?>
                    <p class="eff-fm-hint"><a href="<?= $h($teamUnitUrl) ?>">Modifier les postes et l’effectif prévu de l’unité</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p class="eff-fm-lead">L’affectation indique dans quelle équipe la personne est rattachée. L’affectation principale place la personne dans l’organigramme et sur la fiche.</p>
        <?php if ($assignments !== []): ?>
            <ul class="eff-fm-slots">
                <?php foreach ($assignments as $a): ?>
                    <li class="eff-fm-slot<?= !empty($a['is_primary']) ? ' is-me' : '' ?>">
                        <b><?= htmlspecialchars((string) ($a['unit_name'] ?? 'Unité'), ENT_QUOTES, 'UTF-8') ?></b>
                        <small>
                            <?= htmlspecialchars(!empty($a['role_name']) ? (string) $a['role_name'] : 'Sans rôle précisé', ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($a['is_primary'])): ?> · Principale<?php endif; ?>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="eff-fm-lead">Aucune affectation active.</p>
        <?php endif; ?>
        <?php if ($reportsTo !== '' || $commands !== [] || $chainLabels !== []): ?>
            <dl class="eff-fm-fields eff-fm-fields--3">
                <?php if ($reportsTo !== ''): ?>
                    <div class="eff-fm-f">
                        <dt>Relève de</dt>
                        <dd>
                            <?php if ((int) ($commandChain['reports_to_id'] ?? 0) > 0): ?>
                                <a href="<?= htmlspecialchars(effectifs_workspace_url('membres/' . (int) $commandChain['reports_to_id']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($reportsTo, ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($reportsTo, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>
                <?php if ($chainLabels !== []): ?>
                    <div class="eff-fm-f">
                        <dt>Chaîne</dt>
                        <dd><?= htmlspecialchars(implode(' → ', $chainLabels), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($commands !== []): ?>
                    <div class="eff-fm-f">
                        <dt>Commande</dt>
                        <dd><?= htmlspecialchars(implode(', ', $commands), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        <?php elseif ($assignments !== []): ?>
            <p class="eff-fm-hint">Chef non désigné pour cette unité. <a href="<?= htmlspecialchars(effectifs_workspace_url('chaine'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la chaîne de commandement</a></p>
        <?php endif; ?>
        <?php if ($canManageAssignments && !empty($assignmentNeedsRepair)): ?>
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/affectation/reparer'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <p class="eff-fm-hint">Cette fiche et les effectifs des unités ne concordent pas (affectation enregistrée à moitié).</p>
                <button type="submit" class="eff-btn">Réparer l’affectation</button>
            </form>
        <?php endif; ?>
        <?php if ($canManageAssignments): ?>
            <details class="eff-card__edit">
                <summary>Changer d’unité</summary>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/affectation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_to" value="member">
                    <label for="eff-member-unit">Unité de rattachement</label>
                    <select id="eff-member-unit" name="unit_id">
                        <option value="0">Retirer l’affectation</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int) ($u['id'] ?? 0) ?>" <?= $unitId === (int) ($u['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="eff-member-unit-reason">Motif du changement</label>
                    <input id="eff-member-unit-reason" type="text" name="reason" maxlength="255" placeholder="Ex. Renfort, rotation, remplacement">
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’affectation</button>
                </form>
            </details>
        <?php endif; ?>
    </article>
    <article class="eff-fm-card" id="fonctions">
        <div class="eff-fm-card__head">
            <h3>Fonctions</h3>
            <?php if ($canEditJobs): ?>
                <button type="button" class="eff-fm-btn is-sm" data-dialog-open="eff-dlg-jobs">Modifier</button>
            <?php endif; ?>
        </div>
        <?php if ($jobRoles !== []): ?>
            <ul class="eff-fm-fn">
                <?php foreach ($jobRoles as $jr): ?>
                    <?php $jrDecor = $jobDecorations[(int) ($jr['personnel_job_role_id'] ?? 0)] ?? null; $jrDesc = trim((string) ($jrDecor['description'] ?? '')); ?>
                    <li<?= $jrDesc !== '' ? ' title="' . $h($jrDesc) . '"' : '' ?>>
                        <b><?= \App\Support\JobRoleBadge::html($jrDecor, false) ?><?= $h((string) ($jr['role_name'] ?? 'Fonction')) ?></b>
                        <span class="eff-fm-chip<?= !empty($jr['is_primary']) ? ' is-ok' : '' ?>"><?= !empty($jr['is_primary']) ? 'Principale' : 'Secondaire' ?></span>
                        <?php if (!empty($jrDecor['hidden']) && $canManageAssignments): ?><span class="job-hidden-flag" title="Fonction du site masquée par votre communauté : le membre la garde.">Fonction masquée</span><?php endif; ?>
                        <?= \App\Support\JobRoleBadge::tags($jrDecor['tags'] ?? []) ?>
                        <?php if ($jrDesc !== ''): ?><small class="eff-fm-hint" translate="no"><?= $h($jrDesc) ?></small><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php require __DIR__ . '/partials/job_prerequisites_member.php'; ?>
        <?php else: ?>
            <p class="eff-fm-lead">Aucune fonction attribuée.</p>
        <?php endif; ?>
        <p class="eff-fm-hint">La fonction décrit le poste tenu (tireur, médecin, chef de groupe…). Ce n’est ni l’équipe ni un droit d’accès. La fonction principale apparaît sur la fiche et l’organigramme.</p>
        <?php if ($canManageAssignments && !$jobRolesAvailable): ?>
            <p class="eff-fm-hint">La gestion des fonctions sera disponible après application des migrations.</p>
        <?php endif; ?>
    </article>
    <article class="eff-fm-card" id="acces">
        <?php
        $currentAccessKey = (string) ($currentAccessKey ?? \App\Services\Rbac\CommunityAccessProfiles::MEMBER);
        $accessProfiles = is_array($accessProfiles ?? null) ? $accessProfiles : \App\Services\Rbac\CommunityAccessProfiles::definitions();
        ?>
        <div class="eff-fm-card__head">
            <h3>Niveau d’accès</h3>
        </div>
        <?php if ($roleNames !== []): ?>
            <p class="eff-fm-perm"><?php foreach ($roleNames as $rn): ?><span class="eff-fm-chip"><?= $h($rn) ?></span><?php endforeach; ?></p>
        <?php endif; ?>
        <p class="eff-fm-lead">
            Un seul niveau par personne.
            <?php if ($roleNames !== []): ?>
            Aujourd’hui : <strong><?= htmlspecialchars(implode(', ', $roleNames), ENT_QUOTES, 'UTF-8') ?></strong>.
            <?php endif; ?>
        </p>
        <?php if ($canManageRoles && $orgRoles !== []): ?>
            <details class="eff-card__edit">
                <summary>Changer le niveau</summary>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/roles'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_to" value="member">
                    <fieldset class="eff-role-grid">
                        <legend>Attribuer l’accès</legend>
                        <?php foreach ($orgRoles as $accessRole): ?>
                            <?php $rid = (int) ($accessRole['id'] ?? 0); ?>
                            <label>
                                <input type="radio" name="access_role_id" value="<?= $rid ?>" <?= (int) ($currentAccessRoleId ?? 0) === $rid ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= htmlspecialchars((string) ($accessRole['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <em><?= htmlspecialchars((string) ($accessRole['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></em>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’accès</button>
                </form>
            </details>
            <p class="eff-fm-hint"><a href="<?= htmlspecialchars(effectifs_workspace_url('roles'), ENT_QUOTES, 'UTF-8') ?>">Corriger ce qu’un niveau a le droit de faire</a></p>
        <?php endif; ?>
    </article>
        </div>
    </section>

    <section class="eff-fm-sec" id="carriere" aria-labelledby="carriere-titre">
        <header class="eff-fm-sec__head">
            <h2 id="carriere-titre">Carrière</h2>
            <p>Grades, ancienneté, qualifications, élévations et état du compte.</p>
        </header>
        <div class="eff-fm-cards">
    <article class="eff-fm-card" id="grade">
        <div class="eff-fm-card__head">
            <h3>Grades</h3>
        </div>
        <p class="eff-fm-big"><?= $h($grade !== '' ? $grade : 'Aucun grade') ?><?php if ($gradeLong !== '' && $gradeLong !== $grade): ?> <small><?= $h($gradeLong) ?></small><?php endif; ?></p>
        <p class="eff-fm-hint"><?= $gradeSince !== '' ? 'Grade actuel depuis le ' . $h($fmtDate($gradeSince)) . '.' : ($grade !== '' ? 'Date d’obtention du grade actuel inconnue.' : '') ?></p>
        <?php if ($eligibility !== null): ?>
            <?php
            $eligTitle = match ($eligibilityStatus) {
                \App\Services\Effectifs\ElevationEligibility::ELIGIBLE => 'Élévation éligible',
                \App\Services\Effectifs\ElevationEligibility::NOT_ELIGIBLE => 'Élévation pas encore éligible',
                default => 'Éligibilité à vérifier',
            };
            $eligTone = match ($eligibilityStatus) {
                \App\Services\Effectifs\ElevationEligibility::ELIGIBLE => 'is-ok',
                \App\Services\Effectifs\ElevationEligibility::NOT_ELIGIBLE => 'is-no',
                default => 'is-unknown',
            };
            ?>
            <div class="eff-fm-elig <?= $eligTone ?>">
                <p class="eff-fm-elig__title"><b><?= $h($eligTitle) ?></b> · <?= $h(sprintf((int) $eligibility['total'] > 1 ? '%d / %d critères remplis' : '%d / %d critère rempli', (int) $eligibility['met'], (int) $eligibility['total'])) ?></p>
                <ul class="eff-fm-elig__list">
                    <?php foreach ($eligibility['criteria'] as $crit): ?>
                        <?php $cMet = $crit['met']; ?>
                        <li class="<?= $cMet === true ? 'is-ok' : ($cMet === false ? 'is-no' : 'is-unknown') ?>">
                            <span class="eff-fm-elig__mark" aria-hidden="true"><?= $cMet === true ? '✓' : ($cMet === false ? '✕' : '?') ?></span>
                            <span><?= $h($crit['label']) ?><small><?= $h($crit['detail']) ?></small></span>
                            <span class="eff-fm-sr"><?= $cMet === true ? 'Rempli' : ($cMet === false ? 'Non rempli' : 'À vérifier') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($canSetElevationCriteria): ?>
                    <p class="eff-fm-hint"><a href="<?= $h(effectifs_workspace_url('elevations/criteres')) ?>">Régler les critères d’élévation</a></p>
                <?php endif; ?>
            </div>
        <?php elseif ($canSetElevationCriteria): ?>
            <p class="eff-fm-hint">Aucun critère d’élévation n’est défini pour la communauté. <a href="<?= $h(effectifs_workspace_url('elevations/criteres')) ?>">Définir les critères</a></p>
        <?php endif; ?>
        <?php if ($gradeEntries !== []): ?>
            <ol class="eff-fm-tl eff-fm-tl--grades" aria-label="Frise des grades">
                <?php foreach ($gradeEntries as $gi => $ge): ?>
                    <?php
                    $geHow = $ge['source'] === 'elevation' ? 'Élévation validée' : 'Changement de grade';
                    if ($ge['actor_label'] !== '') {
                        $geHow .= ' par ' . $ge['actor_label'];
                    }
                    ?>
                    <li class="<?= $gi === 0 && $ge['to_grade_id'] !== null && $ge['to_grade_id'] === ($gradeId > 0 ? $gradeId : null) ? 'is-now' : '' ?>">
                        <span class="eff-fm-tl__d"><?= $h($fmtDate($ge['date'])) ?><?= $gi === 0 && $ge['to_grade_id'] === ($gradeId > 0 ? $gradeId : null) ? ' — aujourd’hui' : '' ?></span>
                        <b><?= $h($ge['to_label']) ?></b>
                        <small>
                            <?php if ($ge['from_known']): ?>Précédemment : <?= $h($ge['from_label']) ?> · <?php endif; ?><?= $h($geHow) ?>
                        </small>
                    </li>
                <?php endforeach; ?>
                <?php if ($enlistmentStart !== ''): ?>
                    <li class="is-start">
                        <span class="eff-fm-tl__d"><?= $h($fmtDate($enlistmentStart)) ?></span>
                        <b>Engagement</b>
                        <small>Arrivée dans la communauté</small>
                    </li>
                <?php endif; ?>
            </ol>
            <?php if (count(array_filter($gradeEntries, static fn (array $e): bool => !$e['from_known'])) > 0): ?>
                <p class="eff-fm-hint">Les changements anciens sont repris des traces existantes ; quand le grade précédent n’y figure pas, seule la date est indiquée.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="eff-fm-hint">Aucun changement de grade enregistré pour l’instant. Les prochains changements apparaîtront ici avec le grade avant et après.</p>
        <?php endif; ?>
        <?php if ($canManageGrades): ?>
        <details class="eff-card__edit">
            <summary>Changer le grade</summary>
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/grade'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_to" value="member">
                <label for="eff-member-grade">Grade affiché</label>
                <select id="eff-member-grade" name="grade_id">
                    <option value="">Aucun grade</option>
                    <?php foreach ($gradeGroups as $gradeGroup): ?>
                        <optgroup label="<?= $h($gradeGroup['label']) ?>">
                            <?php foreach ($gradeGroup['grades'] as $g): ?>
                                <?php $gid = (int) ($g['id'] ?? 0); ?>
                                <option value="<?= $gid ?>" <?= $gradeId === $gid ? 'selected' : '' ?>><?= $h(\App\Support\GradeSelectOptions::label($g)) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="eff-card__hint">Grades classés du plus élevé au plus bas. Le changement est ajouté à la frise des grades. <a href="<?= $h(url('back-office/referentiels/grades')) ?>">Gérer le référentiel des grades</a></p>
                <button type="submit" class="eff-btn eff-btn--primary">Enregistrer le grade</button>
            </form>
        </details>
        <?php endif; ?>
    </article>
    <article class="eff-fm-card" id="anciennete">
        <div class="eff-fm-card__head">
            <h3>Ancienneté</h3>
        </div>
        <dl class="eff-fm-fields">
            <div class="eff-fm-f"><dt>Ancienneté réelle</dt><dd><?= htmlspecialchars($seniorityLabel !== '' && $seniorityLabel !== '—' ? $seniorityLabel : 'Non renseignée', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div class="eff-fm-f"><dt>Dans la communauté</dt><dd><?= htmlspecialchars($communityTenureLabel !== '' ? $communityTenureLabel : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div class="eff-fm-f"><dt>Avant le site</dt><dd><?= htmlspecialchars($preTenureLabel !== '' ? $preTenureLabel : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <?php if ($orgFoundingDate !== ''): ?>
            <div class="eff-fm-f"><dt>Création de l’organisation</dt><dd><?= htmlspecialchars($fmtDate($orgFoundingDate), ENT_QUOTES, 'UTF-8') ?></dd></div>
            <?php endif; ?>
        </dl>
        <p class="eff-fm-hint">L’ancienneté réelle reprend la date la plus ancienne : arrivée dans la communauté ou avant l’ouverture du site.</p>
        <?php if ($canEditProfiles): ?>
            <details class="eff-card__edit">
                <summary>Modifier les dates</summary>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/anciennete'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_to" value="member">
                    <label for="eff-member-enlist">Arrivée dans la communauté (sur Athena)</label>
                    <input id="eff-member-enlist" type="date" name="enlistment_date" value="<?= htmlspecialchars($enlistmentStart, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="eff-member-pre">Arrivée avant le site</label>
                    <input id="eff-member-pre" type="date" name="pre_platform_start_date" value="<?= htmlspecialchars($prePlatformStart, ENT_QUOTES, 'UTF-8') ?>">
                    <p class="eff-card__hint">Si la personne était déjà membre avant l’ouverture du site, indiquez sa date d’arrivée réelle. Sinon laissez vide.</p>
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’ancienneté</button>
                </form>
            </details>
        <?php endif; ?>
    </article>
    <article class="eff-fm-card" id="qualifications">
        <div class="eff-fm-card__head">
            <h3>Qualifications</h3>
            <a class="eff-fm-btn is-sm" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/attribuer') . '?user_id=' . (int) $id, ENT_QUOTES, 'UTF-8') ?>">Ajouter</a>
        </div>
        <?php
        $qualificationBadge = $qualificationBadge ?? null;
        if ($qualifications === []): ?>
            <p class="eff-fm-lead">Aucune qualification enregistrée.</p>
        <?php else: ?>
            <ul class="eff-fm-quals">
                <?php foreach ($qualifications as $qi => $q):
                    $qName = trim((string) ($q['definition_name'] ?? $q['qualification_name'] ?? $q['name'] ?? 'Qualification'));
                    $level = trim((string) ($q['level_name'] ?? $q['level'] ?? ''));
                    $adminSt = \App\Support\QualificationAdminStatus::normalize((string) ($q['admin_status'] ?? $q['status'] ?? ''));
                    $qState = $qualStates[$qi] ?? ['code' => '', 'label' => ''];
                    $temporalLabel = (string) ($qState['label'] ?? '');
                    $qCode = (string) ($qState['code'] ?? '');
                    $qTone = in_array($qCode, ['expiring_soon', 'expired_grace', 'expired'], true) ? 'is-exp' : ($qCode === 'valid' ? 'is-ok' : '');
                    $badgePath = $q['level_badge_path'] ?? $q['definition_badge_path'] ?? null;
                    $hasBadge = is_string($badgePath) && trim($badgePath) !== '';
                    $badgeUrl = $qualificationBadge instanceof \App\Services\Personnel\QualificationBadgeStorageService
                        ? $qualificationBadge->publicUrl(is_string($badgePath) ? $badgePath : null)
                        : '';
                    $isPrimary = !empty($q['is_primary']);
                    ?>
                <li class="eff-fm-q <?= $qTone ?><?= $isPrimary ? ' is-primary' : '' ?>">
                    <span class="eff-fm-q__ic" aria-hidden="true">
                        <?php if ($hasBadge && $badgeUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($badgeUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                            <?= $h($monogram($qName)) ?>
                        <?php endif; ?>
                    </span>
                    <div class="eff-fm-q__body">
                        <b>
                            <?= htmlspecialchars($qName, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($level !== ''): ?><span class="eff-fm-q__lvl">— <?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            <?php if ($isPrimary): ?><span class="eff-fm-q__lvl">· Principale</span><?php endif; ?>
                        </b>
                        <small><?= htmlspecialchars(\App\Support\QualificationAdminStatus::label($adminSt), ENT_QUOTES, 'UTF-8') ?></small>
                        <div class="eff-fm-q__acts">
                            <a href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/attribuer') . '?user_id=' . (int) $id . '&renewal_of=' . (int) ($q['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Renouveler</a>
                            <?php if ($adminSt === \App\Support\QualificationAdminStatus::OBTAINED): ?>
                            <form method="post" action="<?= htmlspecialchars(url('back-office/referentiels/qualifications/brevets/' . (int) ($q['id'] ?? 0) . '/generer'), ENT_QUOTES, 'UTF-8') ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit">Générer le brevet</button>
                            </form>
                            <?php if (!empty($q['certificate_document_path'])): ?>
                                <a href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/brevets/' . (int) ($q['id'] ?? 0) . '/telecharger'), ENT_QUOTES, 'UTF-8') ?>">Télécharger le brevet</a>
                            <?php endif; ?>
                            <?php endif; ?>
                            <form method="post" action="<?= htmlspecialchars(url('back-office/referentiels/qualifications/attributions/' . (int) ($q['id'] ?? 0) . '/statut'), ENT_QUOTES, 'UTF-8') ?>" class="eff-fm-q__status">
                                <?= \App\Core\Csrf::field() ?>
                                <select name="admin_status" aria-label="Statut de la qualification">
                                    <?php foreach (\App\Support\QualificationAdminStatus::ALL as $st): ?>
                                        <option value="<?= htmlspecialchars($st) ?>" <?= $st === $adminSt ? 'selected' : '' ?>><?= htmlspecialchars(\App\Support\QualificationAdminStatus::label($st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="revocation_reason" placeholder="Motif si retrait" aria-label="Motif si retrait">
                                <button type="submit">OK</button>
                            </form>
                        </div>
                    </div>
                    <?php if ($temporalLabel !== ''): ?><span class="eff-fm-chip <?= $qTone === 'is-exp' ? 'is-warn' : 'is-ok' ?>"><?= htmlspecialchars($temporalLabel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($qualificationProgress)): ?>
            <h4 class="eff-fm-sub">En progression vers une qualification automatique</h4>
            <?php require base_path('views/partials/qualification_progress.php'); ?>
        <?php endif; ?>
        <div class="eff-fm-row">
            <a class="eff-btn" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/attribuer') . '?user_id=' . (int) $id, ENT_QUOTES, 'UTF-8') ?>">Attribuer une qualification</a>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url('qualifications'), ENT_QUOTES, 'UTF-8') ?>">Matrice des qualifications</a>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications'), ENT_QUOTES, 'UTF-8') ?>">Référentiel</a>
        </div>
    </article>
    <article class="eff-fm-card" id="elevation">
        <div class="eff-fm-card__head">
            <h3>Élévation</h3>
        </div>
        <?php if ($elevationHistory !== []): ?>
            <ol class="eff-fm-tl">
                <?php foreach ($elevationHistory as $ei => $eh): ?>
                    <?php
                    $ehStatus = (string) ($eh['status'] ?? 'pending');
                    $ehRequester = trim((string) ($eh['requester_display_name'] ?? '')) ?: trim((string) ($eh['requester_email'] ?? '')) ?: 'Membre';
                    $ehDateFmt = $fmtDate((string) ($eh['created_at'] ?? '')) ?: '—';
                    ?>
                    <li class="<?= $ehStatus === 'approved' && $ei === 0 ? 'is-now' : (in_array($ehStatus, ['pending', 'in_review'], true) ? 'is-next' : '') ?>">
                        <span class="eff-fm-tl__d"><?= htmlspecialchars($ehDateFmt, ENT_QUOTES, 'UTF-8') ?></span>
                        <b><?= htmlspecialchars($elevStatusLabel($ehStatus), ENT_QUOTES, 'UTF-8') ?></b>
                        <small>Demandée par <?= htmlspecialchars($ehRequester, ENT_QUOTES, 'UTF-8') ?></small>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php if ($canRequestElevation && $elevationCooldownSeconds > 0): ?>
            <p class="eff-fm-lead">Une demande a déjà été envoyée. Patientez <?= htmlspecialchars($elevationCooldownLabel($elevationCooldownSeconds), ENT_QUOTES, 'UTF-8') ?> avant d’en renvoyer une.</p>
        <?php elseif ($canRequestElevation): ?>
            <p class="eff-fm-lead">Demandez un changement de grade, de fonction, d’unité ou de droits à une personne habilitée.</p>
            <button type="button" class="eff-btn eff-btn--warn" data-dialog-open="eff-dlg-elevation">Demander une élévation</button>
        <?php elseif ($elevationNoRecipients ?? false): ?>
            <p class="eff-fm-lead">Élévation indisponible : aucun autre membre n’est habilité à traiter la demande.</p>
        <?php else: ?>
            <p class="eff-fm-lead">Les changements de grade, de fonction ou d’affectation passent par une demande d’élévation.</p>
        <?php endif; ?>
    </article>
    <article class="eff-fm-card" id="statut">
        <div class="eff-fm-card__head">
            <h3>Statut du compte</h3>
        </div>
        <p class="eff-fm-lead">Position administrative : <strong><?= htmlspecialchars($dutyPosition !== '' ? $dutyPosition : 'Non attribuée', ENT_QUOTES, 'UTF-8') ?></strong>.</p>
        <?php if ($dutyPosition === \App\Services\Personnel\PersonnelDutyPositionService::LABEL_TRAINING): ?>
            <?php if ($remainingTrainingDays > 0): ?>
                <p class="eff-fm-hint">Passage en service actif dans <?= $remainingTrainingDays ?> jour(s), après clôture du parcours d’intégration.</p>
            <?php elseif ($canManageStatus): ?>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/position-service'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="eff-btn eff-btn--primary">Passer en service actif</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($canManageStatus): ?>
            <details class="eff-card__edit">
                <summary>Changer l’état du compte</summary>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/statut'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_to" value="member">
                    <label for="eff-quick-status">État du compte</label>
                    <select id="eff-quick-status" name="status">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Compte actif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Compte inactif</option>
                        <option value="pending_verification" <?= $status === 'pending_verification' ? 'selected' : '' ?>>E-mail à vérifier</option>
                    </select>
                    <button type="submit" class="eff-btn eff-btn--ghost">Enregistrer le statut</button>
                </form>
            </details>
        <?php else: ?>
            <p class="eff-fm-lead"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </article>
    <?php if ($canManageStatus): ?>
    <article class="eff-fm-card" id="depart">
        <div class="eff-fm-card__head">
            <h3>Départ</h3>
        </div>
        <?php if ($latestDeparture !== null): ?>
            <?php
            $ldReason = (string) ($latestDeparture['reason'] ?? 'other');
            $ldDate = $fmtDate((string) ($latestDeparture['departed_at'] ?? ''));
            $ldRevoked = !empty($latestDeparture['access_revoked']);
            ?>
            <p class="eff-fm-lead">
                Dernier départ : <?= htmlspecialchars($ldDate !== '' ? $ldDate : '—', ENT_QUOTES, 'UTF-8') ?>
                — <?= htmlspecialchars($departureReasonLabels[$ldReason] ?? $ldReason, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($ldRevoked): ?> · accès retirés<?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($latestDeparture === null): ?>
            <p class="eff-fm-lead">Fin d’engagement, exclusion ou pause : le membre garde son historique.</p>
        <?php endif; ?>
        <button type="button" class="eff-btn eff-btn--ghost" data-dialog-open="eff-dlg-depart">Enregistrer un départ</button>
    </article>
    <?php endif; ?>
        </div>
    </section>

    <section class="eff-fm-sec" id="suivi-rh" aria-labelledby="suivi-rh-titre">
        <header class="eff-fm-sec__head">
            <h2 id="suivi-rh-titre">Suivi RH</h2>
            <p>Présence, absences, sanctions, pièces du dossier et souhaits de mobilité.</p>
        </header>
        <div class="eff-fm-cards">
    <?php
    $renderRhRows = static function (array $rows, callable $row): void {
        if ($rows === []) {
            echo '<p class="eff-fm-lead">Aucune donnée enregistrée.</p>';
            return;
        }
        echo '<ul class="eff-fm-list">';
        foreach ($rows as $r) {
            [$date, $label, $detail] = $row($r) + ['', '', ''];
            echo '<li><span class="eff-fm-list__dt">' . htmlspecialchars($date !== '' ? $date : '—', ENT_QUOTES, 'UTF-8') . '</span><div><b>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</b>'
                . ($detail !== '' ? '<p>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p>' : '')
                . '</div></li>';
        }
        echo '</ul>';
    };
    ?>
    <article class="eff-fm-card eff-fm-card--wide" id="presence">
        <?php
        $grid = is_array($attendance['grid'] ?? null) ? $attendance['grid'] : [];
        $gridCounts = is_array($attendance['grid_counts'] ?? null) ? $attendance['grid_counts'] : [];
        $gridSize = \App\Services\Effectifs\MemberAttendanceSummary::GRID_SIZE;
        $stateLabels = \App\Services\Effectifs\MemberAttendanceSummary::LABELS;
        ?>
        <div class="eff-fm-card__head">
            <h3>Présence — <?= $gridSize ?> dernières sessions</h3>
            <?php if ($grid !== []): ?>
                <p class="eff-fm-pres__sum">
                    <b><?= (int) ($gridCounts['present'] ?? 0) ?></b> <?= (int) ($gridCounts['present'] ?? 0) > 1 ? 'présents' : 'présent' ?>
                    · <b><?= (int) ($gridCounts['excused'] ?? 0) ?></b> <?= (int) ($gridCounts['excused'] ?? 0) > 1 ? 'excusés' : 'excusé' ?>
                    · <b class="<?= (int) ($gridCounts['absent'] ?? 0) > 0 ? 'is-bad' : '' ?>"><?= (int) ($gridCounts['absent'] ?? 0) ?></b> sans prévenir
                    <?php if ((int) ($gridCounts['no_reply'] ?? 0) > 0): ?> · <b><?= (int) $gridCounts['no_reply'] ?></b> sans réponse<?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if ($attendance === null): ?>
            <p class="eff-fm-lead">La présence n’a pas pu être calculée pour le moment.</p>
        <?php elseif ($grid === []): ?>
            <p class="eff-fm-lead">Aucune session passée depuis l’arrivée du membre. Les sessions sont les événements du calendrier de la communauté ; la présence se valide par le pointage.</p>
        <?php else: ?>
            <ol class="eff-fm-pres" aria-label="Présence aux <?= count($grid) ?> dernières sessions, de la plus ancienne à la plus récente">
                <?php for ($pad = count($grid); $pad < $gridSize; $pad++): ?>
                    <li class="eff-fm-pres__c is-none" title="Pas de session"><span class="eff-fm-sr">Pas de session</span></li>
                <?php endfor; ?>
                <?php foreach ($grid as $cell): ?>
                    <?php $cellLabel = $fmtDate($cell['date']) . ($cell['title'] !== '' ? ' — ' . $cell['title'] : '') . ' : ' . ($stateLabels[$cell['state']] ?? ''); ?>
                    <li class="eff-fm-pres__c is-<?= $h($cell['state']) ?>" title="<?= $h($cellLabel) ?>"><span class="eff-fm-sr"><?= $h($cellLabel) ?></span></li>
                <?php endforeach; ?>
            </ol>
            <ul class="eff-fm-pres__legend" aria-hidden="true">
                <li><i class="is-none"></i>Pas de session</li>
                <li><i class="is-present"></i>Présent</li>
                <li><i class="is-excused"></i>Absent excusé</li>
                <li><i class="is-absent"></i>Absent sans prévenir</li>
                <li><i class="is-no_reply"></i>Sans réponse</li>
            </ul>
            <p class="eff-fm-hint">
                <span><?= $h(sprintf('Sur 90 jours : %d présence(s) sur %d session(s).', (int) ($attendance['present_90'] ?? 0), (int) ($attendance['sessions_90'] ?? 0))) ?></span>
                <?php if ($attendanceRate !== null): ?><span>Taux : <?= (int) $attendanceRate ?> %.</span><?php endif; ?>
                <span><?= $h(sprintf('Depuis l’engagement : %d session(s) pointée(s).', (int) ($attendance['present_total'] ?? 0))) ?></span>
                <span>Une présence est comptée quand elle a été pointée le jour de la session.</span>
            </p>
        <?php endif; ?>
    </article>
    <article class="eff-fm-card" id="absences">
        <div class="eff-fm-card__head">
            <h3>Absences</h3>
        </div>
        <?php $renderRhRows($absences, static function (array $a) use ($fmtDate, $absenceReasonLabels): array {
            $reason = (string) ($a['reason'] ?? 'other');
            $start = $fmtDate((string) ($a['starts_at'] ?? $a['start_date'] ?? ''));
            $end = $fmtDate((string) ($a['ends_at'] ?? $a['end_date'] ?? ''));

            return [$start, $absenceReasonLabels[$reason] ?? $reason, 'Du ' . ($start !== '' ? $start : '—') . ' au ' . ($end !== '' ? $end : '—')];
        }); ?>
    </article>
    <?php if ($canViewSanctions): ?>
    <article class="eff-fm-card" id="sanctions">
        <?php $sanctionItems = is_array($sanctions['items'] ?? null) ? $sanctions['items'] : []; ?>
        <div class="eff-fm-card__head">
            <h3>Sanctions</h3>
            <?php if ($sanctionsActive > 0): ?><span class="eff-fm-chip is-warn"><?= $sanctionsActive ?> en vigueur</span><?php endif; ?>
        </div>
        <?php if ($sanctionItems === []): ?>
            <p class="eff-fm-lead">Aucune sanction enregistrée.</p>
        <?php else: ?>
            <ul class="eff-fm-list eff-fm-sanctions">
                <?php foreach ($sanctionItems as $sn): ?>
                    <li class="is-<?= $h($sn['status']) ?>">
                        <span class="eff-fm-list__dt"><?= $h($fmtDate($sn['date']) ?: '—') ?></span>
                        <div>
                            <b><?= $h($sn['label']) ?> <span class="eff-fm-chip <?= $sn['status'] === 'active' ? 'is-warn' : '' ?>"><?= $h($sn['status_label']) ?></span></b>
                            <?php if ($sn['reason'] !== ''): ?><p><?= $h($sn['reason']) ?></p><?php endif; ?>
                            <p class="eff-fm-sanctions__meta">
                                <?= $sn['actor'] !== '' ? 'Par ' . $h($sn['actor']) : '' ?>
                                <?php if ($sn['expires_at'] !== '' && $sn['status'] !== 'lifted'): ?> · jusqu’au <?= $h($fmtDate($sn['expires_at'])) ?><?php endif; ?>
                                <?php if ($sn['status'] === 'lifted'): ?> · <span><?= $h($sn['lifted_by'] !== '' ? sprintf('levée le %s par %s', $fmtDate($sn['lifted_at']), $sn['lifted_by']) : sprintf('levée le %s', $fmtDate($sn['lifted_at']))) ?></span><?php endif; ?>
                            </p>
                            <?php if ($canManageSanctions && $sn['status'] === 'active'): ?>
                                <form method="post" action="<?= $h(effectifs_workspace_url('membres/' . $id . '/sanctions/' . (int) $sn['id'] . '/lever')) ?>" class="eff-fm-sanctions__lift">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <button type="submit" class="eff-fm-btn is-sm">Lever la mesure</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canManageSanctions): ?>
            <details class="eff-card__edit">
                <summary>Donner un avertissement</summary>
                <form method="post" action="<?= $h(effectifs_workspace_url('membres/' . $id . '/sanctions')) ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <label for="eff-sanction-reason">Motif</label>
                    <textarea id="eff-sanction-reason" name="reason" rows="3" maxlength="1000" required placeholder="Ex. Absence non excusée à l’opération du 12/10"></textarea>
                    <p class="eff-card__hint">L’avertissement est inscrit au dossier et journalisé. Il reste visible après avoir été levé.</p>
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’avertissement</button>
                </form>
            </details>
            <p class="eff-fm-hint"><a href="<?= $h(url('back-office/moderation')) ?>">Restreindre l’activité du membre (formations, documents…)</a></p>
        <?php endif; ?>
    </article>
    <?php endif; ?>
    <article class="eff-fm-card" id="documents-rh">
        <div class="eff-fm-card__head">
            <h3>Documents RH</h3>
            <a class="eff-fm-btn is-sm" href="<?= htmlspecialchars(effectifs_workspace_url('documents-rh?user_id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Ajouter ou ouvrir une pièce</a>
        </div>
        <?php $renderRhRows($hrDocuments, static function (array $d) use ($hrDocumentTypeLabels, $fmtDate): array {
            $type = (string) ($d['doc_type'] ?? 'autre');
            $title = trim((string) ($d['title'] ?? '')) ?: ($hrDocumentTypeLabels[$type] ?? 'Document RH');

            return [$fmtDate((string) ($d['created_at'] ?? '')), $title, $hrDocumentTypeLabels[$type] ?? ''];
        }); ?>
    </article>
    <article class="eff-fm-card" id="mobilite">
        <div class="eff-fm-card__head">
            <h3>Mobilité et souhaits</h3>
            <a class="eff-fm-btn is-sm" href="<?= htmlspecialchars(effectifs_workspace_url('mobilite?user_id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Traiter la mobilité</a>
        </div>
        <?php $renderRhRows($mobilityRequests, static function (array $r) use ($mobilityTypeLabels, $fmtDate): array {
            $type = (string) ($r['request_type'] ?? 'career_wish');
            $status = trim((string) ($r['status'] ?? 'pending'));

            return [$fmtDate((string) ($r['created_at'] ?? '')), $mobilityTypeLabels[$type] ?? $type, $status];
        }); ?>
    </article>
        </div>
    </section>

    <?php if (is_array($memberPapers ?? null)): ?>
        <?php require __DIR__ . '/partials/member_papers.php'; ?>
    <?php endif; ?>

    <section class="eff-fm-sec" id="historique" aria-labelledby="historique-titre">
        <header class="eff-fm-sec__head">
            <h2 id="historique-titre">Historique</h2>
            <p>Tout ce qui a changé dans le dossier, du plus récent au plus ancien.</p>
        </header>
        <div class="eff-fm-cards">
    <article class="eff-fm-card eff-fm-card--wide" id="historique-rh">
        <div class="eff-fm-card__head">
            <h3>Historique RH et bilans</h3>
        </div>
        <?php $combinedHistory = array_merge($orgHistory, $serviceHistory, $stageBilans); ?>
        <?php $renderRhRows($combinedHistory, static function (array $h) use ($fmtDate): array {
            $label = trim((string) ($h['title'] ?? $h['event_label'] ?? $h['event_type'] ?? $h['stage_label'] ?? $h['summary'] ?? 'Événement RH'));
            $date = $fmtDate((string) ($h['event_date'] ?? $h['occurred_at'] ?? $h['created_at'] ?? ''));
            // Libellé enregistré en français : traduit morceau par morceau avant d'être affiché.
            $label = \App\Support\HrHistoryLabel::forPage($label);

            return [$date, $label, ''];
        }); ?>
    </article>
        </div>
    </section>
</div>

<aside class="eff-fm-rail" aria-label="Raccourcis du dossier">
    <?php
    $railTodo = [];
    foreach ($qualExpiring as [$qeName, $qeLabel]) {
        $railTodo[] = ['w', 'Qualification à renouveler', $qeName . ($qeLabel !== '' ? ' : ' . mb_strtolower($qeLabel, 'UTF-8') : '') . '.', '#qualifications', 'Voir'];
    }
    if ($assignments === [] && $unit === '') {
        $railTodo[] = ['w', 'Sans unité', 'Aucune affectation active.', '#unite', 'Affecter'];
    }
    if ($canRequestElevation && $elevationCooldownSeconds > 0) {
        $railTodo[] = ['i', 'Élévation en cours', 'Une demande a déjà été envoyée.', '#elevation', 'Voir'];
    } elseif ($isEligible) {
        $railTodo[] = ['i', 'Élévation possible', 'Critères d’élévation remplis (' . (int) $eligibility['met'] . '/' . (int) $eligibility['total'] . ').', '#grade', 'Voir le détail'];
    }
    if ($canViewSanctions && $sanctionsActive > 0) {
        $railTodo[] = ['w', 'Sanction en vigueur', $sanctionsActive . ' mesure' . ($sanctionsActive > 1 ? 's' : '') . ' à suivre.', '#sanctions', 'Voir'];
    }
    ?>
    <section class="eff-fm-card eff-fm-rail__card">
        <div class="eff-fm-card__head">
            <h3>À traiter</h3>
            <?php if ($railTodo !== []): ?><span class="eff-fm-count"><?= count($railTodo) ?></span><?php endif; ?>
        </div>
        <?php if ($railTodo === []): ?>
            <p class="eff-fm-lead">Rien à traiter pour ce dossier.</p>
        <?php else: ?>
            <?php foreach ($railTodo as [$tTone, $tTitle, $tText, $tHref, $tLink]): ?>
                <div class="eff-fm-alert is-<?= $tTone ?>">
                    <?= $ico($tTone === 'w' ? 'warn' : 'info') ?>
                    <div><b><?= $h($tTitle) ?></b><?= $h($tText) ?> <a href="<?= $h($tHref) ?>"><?= $h($tLink) ?></a></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php $mAdq = is_array($memberAdequacy ?? null) ? $memberAdequacy : null; ?>
    <?php if ($mAdq !== null): ?>
    <?= adq_styles() ?>
    <section class="eff-fm-card eff-fm-rail__card adq-place" aria-labelledby="eff-fm-place-title" id="eff-fm-place">
        <div class="eff-fm-card__head"><h3 id="eff-fm-place-title">Place dans l’organisation</h3><?= adq_badge((string) $mAdq['status'], true) ?></div>
        <dl>
            <div><dt>Place</dt><dd><?= $h(adq_place_label((string) $mAdq['place'])) ?><?php if (($mAdq['roles'] ?? []) !== []): ?><small><?= adq_roles_html($mAdq['roles']) ?></small><?php endif; ?></dd></div>
            <div><dt>Chef direct</dt><dd><?php if ((int) $mAdq['chief_id'] > 0): ?><a href="<?= $h(effectifs_workspace_url('membres/' . (int) $mAdq['chief_id'])) ?>"><?= $h((string) $mAdq['chief_label']) ?></a><?php else: ?><span class="eff-fm-muted">Aucun chef désigné</span><?php endif; ?></dd></div>
            <div><dt>Adjoint</dt><dd><?php if ((int) $mAdq['deputy_id'] > 0): ?><a href="<?= $h(effectifs_workspace_url('membres/' . (int) $mAdq['deputy_id'])) ?>"><?= $h((string) $mAdq['deputy_label']) ?></a><?php else: ?><span class="eff-fm-muted">Pas d’adjoint désigné</span><?php endif; ?></dd></div>
            <div><dt>Adéquation</dt><dd><?= $h(adq_status_meta((string) $mAdq['status'])[0]) ?></dd></div>
        </dl>
        <?= adq_issue_list($mAdq['issues'], $mAdq['people'], static fn (int $pid): string => effectifs_workspace_url('membres/' . $pid), $canManageAssignments) ?>
        <p class="adq-note">L’adéquation compare le poste tenu aux attentes de son échelon (grade, qualifications, effectif) : elle décrit une capacité de commandement, pas la valeur de la personne.</p>
        <p class="adq-note"><a href="<?= $h(effectifs_workspace_url('chaine/adequation')) ?>">Voir le référentiel</a></p>
    </section>
    <?php endif; ?>
    <?php if ($reportsTo !== '' || $chainLabels !== []): ?>
    <section class="eff-fm-card eff-fm-rail__card">
        <div class="eff-fm-card__head"><h3>Chaîne de commandement</h3></div>
        <ol class="eff-fm-chain">
            <?php if ($chainLabels !== []): ?>
                <?php foreach ($chainLabels as $ci => $cl): ?>
                    <li><span><?= $ci === 0 ? 'Relève de' : 'Puis' ?></span><b><?= $h((string) $cl) ?></b></li>
                <?php endforeach; ?>
            <?php else: ?>
                <li><span>Relève de</span><b><?= $h($reportsTo) ?></b></li>
            <?php endif; ?>
        </ol>
        <p class="eff-fm-hint"><a href="<?= htmlspecialchars(effectifs_workspace_url('chaine'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la chaîne de commandement</a></p>
    </section>
    <?php endif; ?>
    <section class="eff-fm-card eff-fm-rail__card">
        <div class="eff-fm-card__head"><h3>Raccourcis</h3></div>
        <ul class="eff-fm-links">
            <li><a class="eff-fm-link" href="<?= htmlspecialchars(url('personnel/' . $id), ENT_QUOTES, 'UTF-8') ?>">Voir la fiche du membre</a></li>
            <li><a class="eff-fm-link" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/attribuer') . '?user_id=' . (int) $id, ENT_QUOTES, 'UTF-8') ?>">Attribuer une qualification</a></li>
            <li><a class="eff-fm-link" href="<?= htmlspecialchars(effectifs_workspace_url('documents-rh?user_id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Pièces du dossier RH</a></li>
            <li><a class="eff-fm-link" href="<?= htmlspecialchars(effectifs_workspace_url('mobilite?user_id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Mobilité et souhaits</a></li>
        </ul>
    </section>
</aside>
</div>
</div>

<?php /* Fenêtres de la fiche : ouvertes par les boutons [data-dialog-open] (eff-member.js). */ ?>
<?php if ($canEditProfiles): ?>
<dialog class="eff-dialog" id="eff-dlg-portrait" aria-labelledby="eff-dlg-portrait-title">
    <form method="post" enctype="multipart/form-data" action="<?= $h(url('personnel/' . $id . '/portrait')) ?>" class="eff-dialog__body">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-portrait-title">Image opérateur</h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <input type="hidden" name="effectifs_context" value="1">
        <div class="eff-dialog__portrait">
            <span class="eff-fiche-hero__avatar" data-portrait-preview>
                <?php if ($avatarUrl !== ''): ?><img src="<?= $h($avatarUrl) ?>" alt=""><?php else: ?><?= $h($initials) ?><?php endif; ?>
            </span>
            <div>
                <label for="eff-portrait-file">Nouvelle image</label>
                <input id="eff-portrait-file" type="file" name="portrait" accept="image/jpeg,image/png,image/webp" required data-portrait-input>
                <p class="eff-card__hint">JPG, PNG ou WebP, 2 Mo maximum. L’image remplace le portrait sur la fiche, l’organigramme et le portail.</p>
            </div>
        </div>
        <footer class="eff-dialog__foot">
            <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
            <button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’image</button>
        </footer>
    </form>
</dialog>

<dialog class="eff-dialog" id="eff-dlg-civil" aria-labelledby="eff-dlg-civil-title">
    <form method="post" action="<?= $h(effectifs_workspace_url('membres/' . $id . '/etat-civil')) ?>" class="eff-dialog__body">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-civil-title">État civil du personnage</h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <div class="eff-dialog__grid">
            <label>Surnom<input type="text" name="nickname_primary" maxlength="100" value="<?= $h($profile['nickname_primary'] ?? '') ?>"></label>
            <label>Lieu de naissance<input type="text" name="birth_place" maxlength="150" value="<?= $h($profile['birth_place'] ?? '') ?>" placeholder="Ex. Lyon, France"></label>
            <?php foreach (['family_situation' => 'Situation familiale', 'sex' => 'Sexe', 'blood_type' => 'Groupe sanguin'] as $optKey => $optLabel): ?>
                <?php
                $current = trim((string) ($profile[$optKey] ?? ''));
                $opts = is_array($civilStatusOptions[$optKey] ?? null) ? $civilStatusOptions[$optKey] : [];
                if ($current !== '' && !isset($opts[$current])) {
                    $opts = [$current => $current] + $opts;
                }
                ?>
                <label><?= $h($optLabel) ?>
                    <select name="<?= $h($optKey) ?>">
                        <option value="">Non renseigné</option>
                        <?php foreach ($opts as $optValue => $optText): ?>
                            <option value="<?= $h($optValue) ?>" <?= $current === (string) $optValue ? 'selected' : '' ?>><?= $h($optText) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
            <label>Nationalité
                <select name="nationality">
                    <option value="">Non précisée</option>
                    <?php $nationalityValue = trim((string) ($profile['nationality'] ?? '')); ?>
                    <?php if ($nationalityValue !== ''): ?><option value="<?= $h($nationalityValue) ?>" selected><?= $h($nationalityValue) ?></option><?php endif; ?>
                    <?php foreach ((new \App\Repositories\SseIdCountryRepository())->listForTenant((int) \App\Core\Session::get('tenant_id')) as $nationalityCountry): ?>
                        <?php if ($nationalityCountry['code'] !== $nationalityValue): ?><option value="<?= $h($nationalityCountry['code']) ?>"><?= $h($nationalityCountry['name'] . ' (' . $nationalityCountry['code'] . ')') ?></option><?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Poids (kg)<input type="number" name="weight_kg" min="20" max="300" step="1" value="<?= $h((int) ($profile['weight_kg'] ?? 0) > 0 ? (int) $profile['weight_kg'] : '') ?>"></label>
            <label>Taille (cm)<input type="number" name="height_cm" min="100" max="250" step="1" value="<?= $h((int) ($profile['height_cm'] ?? 0) > 0 ? (int) $profile['height_cm'] : '') ?>"></label>
            <label class="eff-dialog__wide">Intolérances médicamenteuses<input type="text" name="drug_intolerances" maxlength="500" value="<?= $h($profile['drug_intolerances'] ?? '') ?>" placeholder="Ex. Pénicilline, AINS"></label>
            <label class="eff-dialog__wide">Allergies<input type="text" name="allergies" maxlength="500" value="<?= $h($profile['allergies'] ?? '') ?>" placeholder="Ex. Latex, arachide"></label>
            <label class="eff-dialog__wide">Langues parlées<input type="text" name="languages" maxlength="255" value="<?= $h($profile['languages'] ?? '') ?>" placeholder="Ex. Français, anglais, arabe"></label>
            <label class="eff-dialog__wide">Devise<input type="text" name="motto" maxlength="255" value="<?= $h($profile['motto'] ?? '') ?>"></label>
        </div>
        <footer class="eff-dialog__foot">
            <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
            <button type="submit" class="eff-btn eff-btn--primary">Enregistrer</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canEditProfiles): ?>
<dialog class="eff-dialog" id="eff-dlg-langues" aria-labelledby="eff-dlg-langues-title">
    <form method="post" action="<?= $h(effectifs_workspace_url('membres/' . $id . '/langues')) ?>" class="eff-dialog__body">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-langues-title">Langue de <?= $h($name) ?></h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <fieldset class="eff-lang-pick">
            <legend>Langue principale en jeu</legend>
            <?php foreach ($langChoices as $langCode): ?>
                <label class="eff-lang-pick__item">
                    <input type="radio" name="primary_lang" value="<?= $h($langCode) ?>" <?= $langCode === $langPrimary ? 'checked' : '' ?>>
                    <span aria-hidden="true"><?= $h(\App\Support\CommunityLanguages::flag($langCode)) ?></span>
                    <span><?= $h(\App\Support\CommunityLanguages::fullLabel($langCode)) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <fieldset class="eff-lang-pick">
            <legend>Autres langues comprises</legend>
            <?php foreach ($langChoices as $langCode): ?>
                <label class="eff-lang-pick__item">
                    <input type="checkbox" name="other_langs[]" value="<?= $h($langCode) ?>" <?= in_array($langCode, $langOthers, true) ? 'checked' : '' ?>>
                    <span aria-hidden="true"><?= $h(\App\Support\CommunityLanguages::flag($langCode)) ?></span>
                    <span><?= $h(\App\Support\CommunityLanguages::fullLabel($langCode)) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="eff-dialog__hint">Les langues proposées sont celles de la communauté (Paramètres &gt; Identité &gt; Pays et langues). Le joueur peut aussi la régler depuis son dossier.</p>
        <footer class="eff-dialog__foot">
            <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
            <button type="submit" class="eff-btn eff-btn--primary">Enregistrer</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canManageAssignments && $jobRolesAvailable && $jobRolePickerGroups !== []): ?>
<?php
$primaryJobRoleId = 0;
foreach ($jobRoles as $jr) {
    if (!empty($jr['is_primary'])) {
        $primaryJobRoleId = (int) ($jr['personnel_job_role_id'] ?? 0);
        break;
    }
}
?>
<dialog class="eff-dialog eff-dialog--wide" id="eff-dlg-jobs" aria-labelledby="eff-dlg-jobs-title">
    <form method="post" action="<?= $h(effectifs_workspace_url('membres/' . $id . '/fonctions')) ?>" class="eff-dialog__body" data-job-picker data-job-max="<?= $jobRoleMax ?>">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-jobs-title">Fonctions de <?= $h($name) ?></h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <p class="eff-card__hint">Cochez jusqu’à <?= $jobRoleMax ?> fonctions et choisissez la principale (★). Toutes les fonctions du référentiel sont proposées, classées par domaine ; celles de vos unités ORBAT sont à part, en fin de liste.</p>
        <div class="eff-dialog__toolbar">
            <input type="search" placeholder="Rechercher une fonction (ex. médecin, JTAC, pilote)…" aria-label="Rechercher une fonction" data-filter-input>
            <span class="eff-dialog__counter" data-job-count></span>
        </div>
        <div class="eff-job-picker" data-filter-scope>
            <?php foreach ($jobRolePickerGroups as $gi => $group): ?>
                <?php $hasChecked = in_array(true, array_column($group['items'], 'checked'), true); ?>
                <details class="eff-job-picker__group" data-filter-group <?= $hasChecked || $gi === 0 ? 'open' : '' ?>>
                    <summary><?= $h($group['label']) ?> <small><?= count($group['items']) ?></small></summary>
                    <?php if (($group['hint'] ?? '') !== ''): ?><p class="eff-card__hint"><?= $h($group['hint']) ?></p><?php endif; ?>
                    <div class="eff-job-picker__items">
                        <?php foreach ($group['items'] as $item): ?>
                            <?php
                            $val = (string) $item['value'];
                            $isPrimary = $primaryJobRoleId > 0 && $val === (string) $primaryJobRoleId;
                            ?>
                            <div class="eff-job-picker__item<?= ($item['origin'] ?? '') === 'community' ? ' is-community' : '' ?>" data-filter-item data-filter-text="<?= $h(mb_strtolower($item['label'] . ' ' . $item['detail'] . ' ' . $group['label'] . ' ' . ($item['abbreviation'] ?? '') . ' ' . implode(' ', array_column($item['tags'] ?? [], 'name')), 'UTF-8')) ?>"<?= ($item['description'] ?? '') !== '' ? ' title="' . $h((string) $item['description']) . '"' : '' ?>>
                                <label class="eff-job-picker__check">
                                    <input type="checkbox" name="job_role_ids[]" value="<?= $h($val) ?>" <?= $item['checked'] ? 'checked' : '' ?>>
                                    <span><strong><?= \App\Support\JobRoleBadge::html($item, false) ?><?= $h($item['label']) ?> <?= \App\Support\JobRoleBadge::origin((string) ($item['origin'] ?? '')) ?><?php if (!empty($item['hidden'])): ?> <span class="job-hidden-flag">Masquée</span><?php endif; ?></strong><?php if ($item['detail'] !== ''): ?><small><?= $h($item['detail']) ?></small><?php endif; ?></span>
                                </label>
                                <label class="eff-job-picker__primary" title="Fonction principale">
                                    <input type="radio" name="primary_job_role_id" value="<?= $h($val) ?>" <?= $isPrimary ? 'checked' : '' ?>>
                                    <span aria-hidden="true">★</span><span class="eff-sr-only">Principale</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
            <p class="eff-card__hint" data-filter-empty hidden>Aucune fonction ne correspond. <a href="<?= $h(effectifs_workspace_url('fonctions')) ?>">Créer une fonction pour la communauté</a></p>
        </div>
        <?php require __DIR__ . '/partials/job_prerequisites_dialog.php'; ?>
        <footer class="eff-dialog__foot">
            <a class="eff-btn eff-btn--ghost" href="<?= $h(effectifs_workspace_url('fonctions')) ?>">Gérer le catalogue</a>
            <span class="eff-dialog__spacer"></span>
            <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
            <button type="submit" class="eff-btn eff-btn--primary">Enregistrer les fonctions</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canRequestElevation && $elevationCooldownSeconds <= 0): ?>
<dialog class="eff-dialog eff-dialog--wide" id="eff-dlg-elevation" aria-labelledby="eff-dlg-elevation-title">
    <form method="post" action="<?= $h(effectifs_workspace_url('membres/' . $id . '/elevation')) ?>" class="eff-dialog__body">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-elevation-title">Demande d’élévation pour <?= $h($name) ?></h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <input type="hidden" name="return_to" value="member">
        <p class="eff-card__hint">Choisissez le type de demande : seuls les champs utiles s’affichent. La demande part chez les personnes habilitées à la valider.</p>
        <?php
        $fieldIdPrefix = 'eff-member-elev';
        $selectedKind = 'grade';
        $includeUnit = true;
        require base_path('views/admin/effectifs_workspace/partials/elevation_request_fields.php');
        ?>
        <footer class="eff-dialog__foot">
            <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
            <button type="submit" class="eff-btn eff-btn--warn">Envoyer la demande</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canManageStatus): ?>
<dialog class="eff-dialog" id="eff-dlg-depart" aria-labelledby="eff-dlg-depart-title">
    <div class="eff-dialog__body">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-depart-title">Départ de <?= $h($name) ?></h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/depart'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form eff-dialog__form">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_to" value="member">
            <label for="eff-depart-reason">Motif</label>
            <select id="eff-depart-reason" name="reason">
                <option value="end_of_engagement">Fin d’engagement</option>
                <option value="exclusion">Exclusion</option>
                <option value="pause">Pause</option>
                <option value="other">Autre</option>
            </select>
            <label for="eff-depart-date">Date du départ</label>
            <input type="date" id="eff-depart-date" name="departed_at" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
            <label for="eff-depart-note">Note (optionnel)</label>
            <textarea id="eff-depart-note" name="reason_note" rows="2" maxlength="500" placeholder="Contexte du départ…"></textarea>
            <?php if ($canManageRoles): ?>
            <label class="eff-card__check">
                <input type="checkbox" name="revoke_access" value="1">
                Retirer immédiatement les rôles
            </label>
            <?php endif; ?>
            <button type="submit" class="eff-btn eff-btn--warn">Confirmer le départ</button>
        </form>
    </div>
</dialog>
<?php endif; ?>
<script src="<?= $h(asset_url('assets/js/eff-member.js')) ?>" defer></script>

<script defer src="<?= $h(asset_url('assets/js/identity-country-picker.js')) ?>"></script>
