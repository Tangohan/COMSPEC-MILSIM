<?php
declare(strict_types=1);

$m = is_array($member ?? null) ? $member : [];
$id = (int) ($m['id'] ?? 0);
$assignments = is_array($memberAssignments ?? null) ? $memberAssignments : [];
$roleNames = is_array($memberRoleNames ?? null) ? $memberRoleNames : [];
$jobRoles = is_array($memberJobRoles ?? null) ? $memberJobRoles : [];
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
<section class="eff-fiche-hero eff-fiche-hero--v2">
    <div class="eff-fiche-hero__nav">
        <a class="eff-fiche-hero__back" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">← Tableur des effectifs</a>
        <a class="eff-fiche-hero__back" href="<?= htmlspecialchars(url('personnel/' . $id), ENT_QUOTES, 'UTF-8') ?>">Voir la fiche</a>
    </div>
    <div class="eff-fiche-hero__row">
        <div class="eff-fiche-hero__portrait">
        <span class="eff-fiche-hero__avatar" aria-hidden="true">
            <?php if ($avatarUrl !== ''): ?>
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async" data-img-fallback="portrait" data-img-initials="<?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>" data-img-label="Portrait opérateur indisponible">
            <?php else: ?>
                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </span>
        <?php if ($canEditProfiles): ?>
            <button type="button" class="eff-fiche-hero__portrait-btn" data-dialog-open="eff-dlg-portrait" id="edit-portrait">Changer l’image</button>
        <?php endif; ?>
        </div>
        <div class="eff-fiche-hero__id">
            <p class="eff-page-kicker">Fiche membre<?php if ($grade !== ''): ?> · <?= $h($grade) ?><?php endif; ?></p>
            <h1 class="eff-page-title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="eff-fiche-hero__meta">
                <?php if ($callsign !== ''): ?>
                    <span>Indicatif <?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <span><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span><?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <div class="eff-fiche-chips">
                <span class="eff-tag <?= $status === 'active' ? 'eff-tag--unit' : 'eff-tag--warn' ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($fonction !== ''): ?>
                    <span class="eff-tag"><?= $h($fonction) ?></span>
                <?php endif; ?>
                <?php if ($unit !== ''): ?>
                    <span class="eff-tag eff-tag--unit"><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <span class="eff-tag eff-tag--warn">Sans unité</span>
                <?php endif; ?>
                <?php if ($seniorityLabel !== '' && $seniorityLabel !== '—'): ?>
                    <span class="eff-tag"><?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <div class="eff-fiche-hero__actions">
                <?php if ($canEditProfiles): ?>
                    <button type="button" class="ath-btn" data-dialog-open="eff-dlg-civil">État civil</button>
                <?php endif; ?>
                <?php if ($canManageAssignments && $jobRolesAvailable && $jobRolePickerGroups !== []): ?>
                    <button type="button" class="ath-btn" data-dialog-open="eff-dlg-jobs">Fonctions</button>
                <?php endif; ?>
                <?php if ($canRequestElevation && $elevationCooldownSeconds < 1): ?>
                    <button type="button" class="ath-btn" data-dialog-open="eff-dlg-elevation">Demander une élévation</button>
                <?php endif; ?>
                <?php if ($canManageStatus): ?>
                    <button type="button" class="ath-btn" data-dialog-open="eff-dlg-depart">Départ</button>
                <?php endif; ?>
            </div>
        </div>
        <aside class="eff-fiche-score<?= $fileScore < 60 ? ' is-low' : '' ?>" aria-label="Complétude du dossier">
            <p class="eff-fiche-score__k">Dossier</p>
            <p class="eff-fiche-score__v"><?= $fileScore ?> %</p>
            <div class="eff-fiche-score__bar"><span style="width:<?= $fileScore ?>%"></span></div>
            <?php if ($missing === []): ?>
                <p class="eff-fiche-score__ok">Tout est renseigné.</p>
            <?php else: ?>
                <p class="eff-fiche-score__lead">À compléter :</p>
                <div class="eff-fiche-score__fix">
                    <?php foreach ($missing as [$mLabel, $mHref, $mCan]): ?>
                        <?php if ($mCan && str_starts_with($mHref, '#eff-dlg-')): ?>
                            <button type="button" class="eff-fix-link" data-dialog-open="<?= $h(substr($mHref, 1)) ?>"><?= $h($mLabel) ?></button>
                        <?php elseif ($mCan): ?>
                            <a class="eff-fix-link" href="<?= $h($mHref) ?>"><?= $h($mLabel) ?></a>
                        <?php else: ?>
                            <span class="eff-pill"><?= $h($mLabel) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</section>

<nav class="bo-eff-jump bo-eff-jump--v2" aria-label="Dossier RH complet : rubriques">
    <a href="#identite">Identité</a>
    <a href="#poste">Poste</a>
    <a href="#carriere">Carrière</a>
    <a href="#suivi-rh">Suivi RH</a>
    <a href="#historique-rh">Historique</a>
</nav>

<div class="eff-fiche-sections">
    <section class="eff-fiche-section" id="identite" aria-labelledby="identite-titre">
        <header class="eff-fiche-section__head">
            <h2 class="eff-fiche-section__title" id="identite-titre">Identité</h2>
            <p>Qui est la personne : compte, surnom et identité jouée en mission.</p>
        </header>
        <div class="eff-fiche-grid">
    <article class="eff-card" id="situation">
        <h2 class="eff-card__title">Situation</h2>
        <dl class="eff-dl">
            <div>
                <dt>Nom affiché</dt>
                <dd><?= htmlspecialchars($display !== '' ? $display : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Surnom</dt>
                <dd><?= htmlspecialchars($nicknamePrimary !== '' ? $nicknamePrimary : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Identifiant</dt>
                <dd><?= htmlspecialchars($athenaId !== '' ? $athenaId : ($matricule !== '' ? $matricule : '—'), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Fonction</dt>
                <dd><?= htmlspecialchars($fonction !== '' ? $fonction : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Dernière connexion</dt>
                <dd><?= htmlspecialchars($lastLogin !== '' ? $lastLogin : 'Jamais', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Compte créé</dt>
                <dd><?= htmlspecialchars($createdAt !== '' ? $createdAt : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php if ($steamId !== ''): ?>
            <div>
                <dt>Liaison Steam</dt>
                <dd>Compte lié</dd>
            </div>
            <?php endif; ?>
            <?php if ($medalRackItems !== []): ?>
            <div>
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
    <article class="eff-card" id="modifier-dossier">
        <div class="eff-card__head">
            <h2 class="eff-card__title">État civil du personnage</h2>
            <?php if ($canEditProfiles): ?>
                <button type="button" class="eff-btn eff-btn--ghost eff-btn--sm" data-dialog-open="eff-dlg-civil">Modifier</button>
            <?php endif; ?>
        </div>
        <p class="eff-card__lead">Identité jouée en mission (<?= $civilFilled ?>/<?= count($civilFields) ?> renseignés). L’identité réelle du joueur reste privée et n’apparaît pas ici.</p>
        <dl class="eff-dl eff-dl--compact">
            <?php foreach ($civilFields as $civilKey => $civilLabel): ?>
                <?php $cv = $civilValue($civilKey); ?>
                <div>
                    <dt><?= $h($civilLabel) ?></dt>
                    <dd class="<?= $cv === '' ? 'eff-dd--empty' : '' ?>"><?= $h($cv !== '' ? $cv : 'Non renseigné') ?></dd>
                </div>
            <?php endforeach; ?>
            <?php foreach (['drug_intolerances' => 'Intolérances médicamenteuses', 'allergies' => 'Allergies'] as $medKey => $medLabel): ?>
                <?php $mv = trim((string) ($profile[$medKey] ?? '')); ?>
                <div>
                    <dt><?= $h($medLabel) ?></dt>
                    <dd class="<?= $mv === '' ? 'eff-dd--empty' : '' ?>"><?= $h($mv !== '' ? $mv : 'Aucune connue') ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </article>
    <article class="eff-card eff-lang-card" id="langues">
        <div class="eff-card__head">
            <h2 class="eff-card__title">Langue du joueur</h2>
            <?php if ($canEditProfiles): ?>
                <button type="button" class="eff-btn eff-btn--ghost eff-btn--sm" data-dialog-open="eff-dlg-langues">Modifier</button>
            <?php endif; ?>
        </div>
        <p class="eff-card__lead">La langue dans laquelle la personne joue et communique en opération (radio, briefings). Elle sert à composer les équipes.</p>
        <div class="eff-lang">
            <span class="eff-lang__flag" aria-hidden="true"><?= $h(\App\Support\CommunityLanguages::flag($langPrimary)) ?></span>
            <div class="eff-lang__text">
                <strong><?= $h(\App\Support\CommunityLanguages::fullLabel($langPrimary)) ?></strong>
                <span><?= $h($langOthers !== [] ? 'Comprend aussi : ' . implode(', ', array_map([\App\Support\CommunityLanguages::class, 'label'], $langOthers)) : 'Aucune autre langue indiquée') ?></span>
            </div>
            <?php if ($langSource !== 'member'): ?>
                <span class="eff-lang__source"><?= $h($langSource === 'interface' ? 'Déduite de la langue de son interface' : 'Langue de la communauté, à confirmer') ?></span>
            <?php endif; ?>
        </div>
    </article>
        </div>
    </section>
    <section class="eff-fiche-section" id="poste" aria-labelledby="poste-titre">
        <header class="eff-fiche-section__head">
            <h2 class="eff-fiche-section__title" id="poste-titre">Poste</h2>
            <p>Où elle sert, ce qu’elle fait et ce qu’elle peut faire sur le site.</p>
        </header>
        <div class="eff-fiche-grid">
    <article class="eff-card" id="unite">
        <h2 class="eff-card__title">Unité</h2>
        <p class="eff-card__lead">L’affectation indique dans quelle équipe la personne est rattachée. L’affectation principale place la personne dans l’organigramme et sur la fiche.</p>
        <?php if ($assignments !== []): ?>
            <ul class="eff-card__list">
                <?php foreach ($assignments as $a): ?>
                    <li>
                        <?= htmlspecialchars((string) ($a['unit_name'] ?? 'Unité'), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($a['role_name'])): ?>
                            — <?= htmlspecialchars((string) $a['role_name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                        <?php if (!empty($a['is_primary'])): ?>
                            <span class="eff-badge eff-badge--active">Principale</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="eff-card__lead">Aucune affectation active.</p>
        <?php endif; ?>
        <?php
        $commandChain = is_array($memberCommandChain ?? null) ? $memberCommandChain : [];
        $reportsTo = trim((string) ($commandChain['reports_to_label'] ?? ''));
        $chainLabels = is_array($commandChain['chain_labels'] ?? null) ? $commandChain['chain_labels'] : [];
        $commands = is_array($commandChain['commands'] ?? null) ? $commandChain['commands'] : [];
        ?>
        <?php if ($reportsTo !== '' || $commands !== [] || $chainLabels !== []): ?>
            <dl class="eff-dl">
                <?php if ($reportsTo !== ''): ?>
                    <div>
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
                    <div>
                        <dt>Chaîne</dt>
                        <dd><?= htmlspecialchars(implode(' → ', $chainLabels), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($commands !== []): ?>
                    <div>
                        <dt>Commande</dt>
                        <dd><?= htmlspecialchars(implode(', ', $commands), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        <?php elseif ($assignments !== []): ?>
            <p class="eff-card__hint">Chef non désigné pour cette unité. <a href="<?= htmlspecialchars(effectifs_workspace_url('chaine'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la chaîne de commandement</a></p>
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
    <article class="eff-card" id="fonctions">
        <div class="eff-card__head">
            <h2 class="eff-card__title">Fonctions</h2>
            <?php if ($canManageAssignments && $jobRolesAvailable && $jobRolePickerGroups !== []): ?>
                <button type="button" class="eff-btn eff-btn--ghost eff-btn--sm" data-dialog-open="eff-dlg-jobs">Modifier</button>
            <?php endif; ?>
        </div>
        <p class="eff-card__lead">La fonction décrit le poste tenu (tireur, médecin, chef de groupe…). Ce n’est ni l’équipe ni un droit d’accès. La fonction principale apparaît sur la fiche et l’organigramme.</p>
        <?php if ($jobRoles !== []): ?>
            <div class="eff-tags">
                <?php foreach ($jobRoles as $jr): ?>
                    <span class="eff-tag<?= !empty($jr['is_primary']) ? ' eff-tag--grade' : '' ?>">
                        <?= $h((string) ($jr['role_name'] ?? 'Fonction')) ?>
                        <?php if (!empty($jr['is_primary'])): ?> · principale<?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="eff-card__lead">Aucune fonction attribuée.</p>
        <?php endif; ?>
        <?php if ($canManageAssignments && !$jobRolesAvailable): ?>
            <p class="eff-card__hint">La gestion des fonctions sera disponible après application des migrations.</p>
        <?php endif; ?>
    </article>
    <?php if ($canManageGrades): ?>
    <article class="eff-card" id="grade">
        <h2 class="eff-card__title">Grade</h2>
        <p class="eff-card__value"><?= $h($grade !== '' ? $grade : 'Aucun grade') ?></p>
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
                <p class="eff-card__hint">Grades classés du plus élevé au plus bas. <a href="<?= $h(url('back-office/referentiels/grades')) ?>">Gérer le référentiel des grades</a></p>
                <button type="submit" class="eff-btn eff-btn--primary">Enregistrer le grade</button>
            </form>
        </details>
    </article>
    <?php endif; ?>
    <article class="eff-card" id="acces">
        <h2 class="eff-card__title">Niveau d’accès</h2>
        <?php
        $currentAccessKey = (string) ($currentAccessKey ?? \App\Services\Rbac\CommunityAccessProfiles::MEMBER);
        $accessProfiles = is_array($accessProfiles ?? null) ? $accessProfiles : \App\Services\Rbac\CommunityAccessProfiles::definitions();
        ?>
        <p class="eff-card__lead">
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
            <p class="eff-card__hint"><a href="<?= htmlspecialchars(effectifs_workspace_url('roles'), ENT_QUOTES, 'UTF-8') ?>">Corriger ce qu’un niveau a le droit de faire</a></p>
        <?php endif; ?>
    </article>
        </div>
    </section>
    <section class="eff-fiche-section" id="carriere" aria-labelledby="carriere-titre">
        <header class="eff-fiche-section__head">
            <h2 class="eff-fiche-section__title" id="carriere-titre">Carrière</h2>
            <p>Ancienneté, état du compte, élévations et départs.</p>
        </header>
        <div class="eff-fiche-grid">
    <article class="eff-card" id="anciennete">
        <h2 class="eff-card__title">Ancienneté</h2>
        <p class="eff-card__lead">L’ancienneté réelle reprend la date la plus ancienne : arrivée dans la communauté ou avant l’ouverture du site.</p>
        <dl class="eff-dl">
            <div>
                <dt>Ancienneté réelle</dt>
                <dd><?= htmlspecialchars($seniorityLabel !== '' && $seniorityLabel !== '—' ? $seniorityLabel : 'Non renseignée', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Dans la communauté</dt>
                <dd><?= htmlspecialchars($communityTenureLabel !== '' ? $communityTenureLabel : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Avant le site</dt>
                <dd><?= htmlspecialchars($preTenureLabel !== '' ? $preTenureLabel : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php if ($orgFoundingDate !== ''): ?>
            <div>
                <dt>Création de l’organisation</dt>
                <dd><?= htmlspecialchars($fmtDate($orgFoundingDate), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>
        </dl>
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
    <article class="eff-card" id="statut">
        <h2 class="eff-card__title">Statut du compte</h2>
        <p class="eff-card__lead">Position administrative : <strong><?= htmlspecialchars($dutyPosition !== '' ? $dutyPosition : 'Non attribuée', ENT_QUOTES, 'UTF-8') ?></strong>.</p>
        <?php if ($dutyPosition === \App\Services\Personnel\PersonnelDutyPositionService::LABEL_TRAINING): ?>
            <?php if ($remainingTrainingDays > 0): ?>
                <p class="eff-card__hint">Passage en service actif dans <?= $remainingTrainingDays ?> jour(s), après clôture du parcours d’intégration.</p>
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
            <p class="eff-card__lead"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </article>
    <article class="eff-card" id="elevation">
        <h2 class="eff-card__title">Élévation</h2>
        <?php if ($elevationHistory !== []): ?>
            <ul class="eff-card__list eff-card__list--scroll">
                <?php foreach ($elevationHistory as $eh): ?>
                    <?php
                    $ehStatus = (string) ($eh['status'] ?? 'pending');
                    $ehRequester = trim((string) ($eh['requester_display_name'] ?? '')) ?: trim((string) ($eh['requester_email'] ?? '')) ?: 'Membre';
                    $ehDateFmt = $fmtDate((string) ($eh['created_at'] ?? '')) ?: '—';
                    ?>
                    <li>
                        <?= htmlspecialchars($ehDateFmt, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($ehRequester, ENT_QUOTES, 'UTF-8') ?>
                        <span class="eff-badge <?= $ehStatus === 'approved' ? 'eff-badge--active' : '' ?>"><?= htmlspecialchars($elevStatusLabel($ehStatus), ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canRequestElevation && $elevationCooldownSeconds > 0): ?>
            <p class="eff-card__lead">Une demande a déjà été envoyée. Patientez <?= htmlspecialchars($elevationCooldownLabel($elevationCooldownSeconds), ENT_QUOTES, 'UTF-8') ?> avant d’en renvoyer une.</p>
        <?php elseif ($canRequestElevation): ?>
            <p class="eff-card__lead">Demandez un changement de grade, de fonction, d’unité ou de droits à une personne habilitée.</p>
            <button type="button" class="eff-btn eff-btn--warn" data-dialog-open="eff-dlg-elevation">Demander une élévation</button>
        <?php elseif ($elevationNoRecipients ?? false): ?>
            <p class="eff-card__lead">Élévation indisponible : aucun autre membre n’est habilité à traiter la demande.</p>
        <?php else: ?>
            <p class="eff-card__lead">Les changements de grade, de fonction ou d’affectation passent par une demande d’élévation.</p>
        <?php endif; ?>
    </article>
    <?php if ($canManageStatus): ?>
    <article class="eff-card" id="depart">
        <h2 class="eff-card__title">Départ</h2>
        <?php if ($latestDeparture !== null): ?>
            <?php
            $ldReason = (string) ($latestDeparture['reason'] ?? 'other');
            $ldDate = $fmtDate((string) ($latestDeparture['departed_at'] ?? ''));
            $ldRevoked = !empty($latestDeparture['access_revoked']);
            ?>
            <p class="eff-card__lead">
                Dernier départ : <?= htmlspecialchars($ldDate !== '' ? $ldDate : '—', ENT_QUOTES, 'UTF-8') ?>
                — <?= htmlspecialchars($departureReasonLabels[$ldReason] ?? $ldReason, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($ldRevoked): ?> · accès retirés<?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($latestDeparture === null): ?>
            <p class="eff-card__lead">Fin d’engagement, exclusion ou pause : le membre garde son historique.</p>
        <?php endif; ?>
        <button type="button" class="eff-btn eff-btn--ghost" data-dialog-open="eff-dlg-depart">Enregistrer un départ</button>
    </article>
    <?php endif; ?>
        </div>
    </section>
    <section class="eff-fiche-section" id="suivi-rh" aria-labelledby="suivi-rh-titre">
        <header class="eff-fiche-section__head">
            <h2 class="eff-fiche-section__title" id="suivi-rh-titre">Suivi RH</h2>
            <p>Qualifications, absences, pièces du dossier et souhaits de mobilité.</p>
        </header>
        <div class="eff-fiche-grid">
    <article class="eff-card" id="qualifications">
        <h2 class="eff-card__title">Qualifications</h2>
        <?php
        $qualificationTemporal = $qualificationTemporal ?? null;
        $qualificationBadge = $qualificationBadge ?? null;
        if ($qualifications === []): ?>
            <p class="eff-muted">Aucune qualification enregistrée.</p>
        <?php else: ?>
            <ul class="space-y-3 mb-4">
                <?php foreach ($qualifications as $q):
                    $qName = trim((string) ($q['definition_name'] ?? $q['qualification_name'] ?? $q['name'] ?? 'Qualification'));
                    $level = trim((string) ($q['level_name'] ?? $q['level'] ?? ''));
                    $adminSt = \App\Support\QualificationAdminStatus::normalize((string) ($q['admin_status'] ?? $q['status'] ?? ''));
                    $temporalLabel = '';
                    if ($qualificationTemporal instanceof \App\Services\Personnel\QualificationTemporalStatusService) {
                        $t = $qualificationTemporal->resolve($q);
                        $temporalLabel = (string) ($t['label'] ?? '');
                    }
                    $badgePath = $q['level_badge_path'] ?? $q['definition_badge_path'] ?? null;
                    $badgeUrl = $qualificationBadge instanceof \App\Services\Personnel\QualificationBadgeStorageService
                        ? $qualificationBadge->publicUrl(is_string($badgePath) ? $badgePath : null)
                        : url('assets/img/qualification-badge-default.svg');
                    $isPrimary = !empty($q['is_primary']);
                    ?>
                <li class="flex gap-3 items-start <?= $isPrimary ? 'rounded border border-emerald-200 bg-emerald-50/50 p-2' : '' ?>">
                    <img src="<?= htmlspecialchars($badgeUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="w-10 h-10 object-contain shrink-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-slate-900">
                            <?= htmlspecialchars($qName, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($level !== ''): ?><span class="text-slate-500 font-normal">— <?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            <?php if ($isPrimary): ?><span class="ml-1 text-[10px] uppercase tracking-wider text-emerald-800">Principale</span><?php endif; ?>
                        </div>
                        <div class="text-xs text-slate-600 mt-0.5">
                            <?= htmlspecialchars(\App\Support\QualificationAdminStatus::label($adminSt), ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($temporalLabel !== ''): ?> · <?= htmlspecialchars($temporalLabel, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <a class="underline text-emerald-800" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/attribuer') . '?user_id=' . (int) $id . '&renewal_of=' . (int) ($q['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Renouveler</a>
                            <?php if ($adminSt === \App\Support\QualificationAdminStatus::OBTAINED): ?>
                            <form method="post" action="<?= htmlspecialchars(url('back-office/referentiels/qualifications/brevets/' . (int) ($q['id'] ?? 0) . '/generer'), ENT_QUOTES, 'UTF-8') ?>" class="inline">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="underline text-emerald-800">Générer le brevet</button>
                            </form>
                            <?php if (!empty($q['certificate_document_path'])): ?>
                                <a class="underline text-emerald-800" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/brevets/' . (int) ($q['id'] ?? 0) . '/telecharger'), ENT_QUOTES, 'UTF-8') ?>">Télécharger le brevet</a>
                            <?php endif; ?>
                            <?php endif; ?>
                            <form method="post" action="<?= htmlspecialchars(url('back-office/referentiels/qualifications/attributions/' . (int) ($q['id'] ?? 0) . '/statut'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1">
                                <?= \App\Core\Csrf::field() ?>
                                <select name="admin_status" class="border border-slate-300 rounded text-xs py-0.5">
                                    <?php foreach (\App\Support\QualificationAdminStatus::ALL as $st): ?>
                                        <option value="<?= htmlspecialchars($st) ?>" <?= $st === $adminSt ? 'selected' : '' ?>><?= htmlspecialchars(\App\Support\QualificationAdminStatus::label($st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="revocation_reason" placeholder="Motif si retrait" class="border border-slate-300 rounded text-xs px-1 py-0.5 w-32">
                                <button type="submit" class="underline text-slate-700">OK</button>
                            </form>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="flex flex-wrap gap-2">
            <a class="eff-btn" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/attribuer') . '?user_id=' . (int) $id, ENT_QUOTES, 'UTF-8') ?>">Attribuer une qualification</a>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url('qualifications'), ENT_QUOTES, 'UTF-8') ?>">Piloter les échéances</a>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications'), ENT_QUOTES, 'UTF-8') ?>">Référentiel</a>
        </div>
    </article>
    <article class="eff-card" id="absences">
        <h2 class="eff-card__title">Absences</h2>
        <?php $renderRhList($absences, static function (array $a) use ($fmtDate, $absenceReasonLabels): string {
            $reason = (string) ($a['reason'] ?? 'other');
            $period = $fmtDate((string) ($a['starts_at'] ?? $a['start_date'] ?? '')) . ' → ' . $fmtDate((string) ($a['ends_at'] ?? $a['end_date'] ?? ''));
            return ($absenceReasonLabels[$reason] ?? $reason) . ' · ' . $period;
        }); ?>
    </article>
    <article class="eff-card" id="documents-rh">
        <h2 class="eff-card__title">Documents RH</h2>
        <?php $renderRhList($hrDocuments, static function (array $d) use ($hrDocumentTypeLabels, $fmtDate): string {
            $type = (string) ($d['doc_type'] ?? 'autre');
            $title = trim((string) ($d['title'] ?? '')) ?: ($hrDocumentTypeLabels[$type] ?? 'Document RH');
            return $title . ' · ' . ($fmtDate((string) ($d['created_at'] ?? '')) ?: 'date inconnue');
        }); ?>
        <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url('documents-rh?user_id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Ajouter ou ouvrir une pièce</a>
    </article>
    <article class="eff-card" id="mobilite">
        <h2 class="eff-card__title">Mobilité et souhaits</h2>
        <?php $renderRhList($mobilityRequests, static function (array $r) use ($mobilityTypeLabels, $fmtDate): string {
            $type = (string) ($r['request_type'] ?? 'career_wish');
            $status = trim((string) ($r['status'] ?? 'pending'));
            return ($mobilityTypeLabels[$type] ?? $type) . ' · ' . $status . ' · ' . ($fmtDate((string) ($r['created_at'] ?? '')) ?: 'date inconnue');
        }); ?>
        <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url('mobilite?user_id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Traiter la mobilité</a>
    </article>
        </div>
    </section>
    <section class="eff-fiche-section" id="historique" aria-labelledby="historique-titre">
        <header class="eff-fiche-section__head">
            <h2 class="eff-fiche-section__title" id="historique-titre">Historique</h2>
            <p>Tout ce qui a changé dans le dossier, du plus récent au plus ancien.</p>
        </header>
        <div class="eff-fiche-grid eff-fiche-grid--single">
    <article class="eff-card" id="historique-rh">
        <h2 class="eff-card__title">Historique RH et bilans</h2>
        <?php $combinedHistory = array_merge($orgHistory, $serviceHistory, $stageBilans); ?>
        <?php $renderRhList($combinedHistory, static function (array $h) use ($fmtDate): string {
            $label = trim((string) ($h['title'] ?? $h['event_label'] ?? $h['event_type'] ?? $h['stage_label'] ?? $h['summary'] ?? 'Événement RH'));
            $date = $fmtDate((string) ($h['event_date'] ?? $h['occurred_at'] ?? $h['created_at'] ?? ''));
            // Libellé enregistré en français : traduit morceau par morceau avant d'être collé à la date.
            $label = \App\Support\HrHistoryLabel::forPage($label);
            return ($date !== '' ? $date . ' · ' : '') . $label;
        }); ?>
    </article>
        </div>
    </section>
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
            <label>Nationalité<input type="text" name="nationality" maxlength="100" value="<?= $h($profile['nationality'] ?? '') ?>" placeholder="Ex. Française"></label>
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
                            <div class="eff-job-picker__item" data-filter-item data-filter-text="<?= $h(mb_strtolower($item['label'] . ' ' . $item['detail'] . ' ' . $group['label'], 'UTF-8')) ?>">
                                <label class="eff-job-picker__check">
                                    <input type="checkbox" name="job_role_ids[]" value="<?= $h($val) ?>" <?= $item['checked'] ? 'checked' : '' ?>>
                                    <span><strong><?= $h($item['label']) ?></strong><?php if ($item['detail'] !== ''): ?><small><?= $h($item['detail']) ?></small><?php endif; ?></span>
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
