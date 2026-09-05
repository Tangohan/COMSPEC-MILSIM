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
$memberEditorHtml = (string) ($memberEditorHtml ?? '');

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
$clearanceRaw = trim((string) ($m['clearance_level'] ?? ''));
$clearanceLabels = \App\Services\Documents\DocumentAccessService::getClassificationLevelLabels();
$clearanceLabel = $clearanceRaw !== '' ? ($clearanceLabels[$clearanceRaw] ?? $clearanceRaw) : '';
$clearanceReviewedAt = trim((string) ($m['clearance_reviewed_at'] ?? ''));
$clearanceOverdue = \App\Support\ClearanceReviewPolicy::isOverdue($clearanceRaw, $clearanceReviewedAt);
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
$avatarUrl = function_exists('user_media_public_url')
    ? (user_media_public_url($m['avatar_url'] ?? null) ?? '')
    : trim((string) ($m['avatar_url'] ?? ''));
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

$memberHubUserId = $id;
$memberHubCurrent = 'effectifs';
$memberHubTheme = 'lms';
?>
<section class="eff-fiche-hero">
    <a class="eff-fiche-hero__back" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">← Tableur des effectifs</a>
    <?php if ($canEditProfiles): ?>
        <a class="eff-btn eff-btn--primary" href="#modifier-dossier">Modifier le dossier complet</a>
    <?php endif; ?>
    <div class="eff-fiche-hero__row">
        <span class="eff-fiche-hero__avatar" aria-hidden="true">
            <?php if ($avatarUrl !== ''): ?>
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
            <?php else: ?>
                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </span>
        <div class="eff-fiche-hero__id">
            <p class="eff-page-kicker">Fiche membre</p>
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
                <?php if ($grade !== ''): ?>
                    <span class="eff-tag eff-tag--grade"><?= htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($unit !== ''): ?>
                    <span class="eff-tag eff-tag--unit"><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <span class="eff-tag eff-tag--warn">Sans unité</span>
                <?php endif; ?>
                <?php if ($clearanceLabel !== ''): ?>
                    <span class="eff-tag"><?= htmlspecialchars($clearanceLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($seniorityLabel !== '' && $seniorityLabel !== '—'): ?>
                    <span class="eff-tag"><?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php require base_path('views/partials/member_hub_nav.php'); ?>
</section>

<?php if ($canEditProfiles && $memberEditorHtml !== ''): ?>
<section id="modifier-dossier" class="eff-card" style="margin-bottom:1rem;scroll-margin-top:1rem">
    <h2 class="eff-card__title">Modifier le dossier complet</h2>
    <p class="eff-card__lead">Tous les champs du dossier sont regroupés ici, sans ouvrir une autre page.</p>
    <?= $memberEditorHtml ?>
</section>
<?php endif; ?>

<div class="eff-fiche-grid">
    <article class="eff-card" style="grid-column:1/-1">
        <h2 class="eff-card__title">Dossier RH complet</h2>
        <p class="eff-card__lead">Toutes les informations et toutes les actions RH de ce membre sont réunies sur cette fiche Effectifs. Les espaces transverses restent accessibles pour ajouter une pièce ou traiter une demande, sans quitter le bureau Effectifs.</p>
        <div class="eff-tags">
            <a class="eff-btn eff-btn--ghost" href="#qualifications">Qualifications</a>
            <a class="eff-btn eff-btn--ghost" href="#absences">Absences</a>
            <a class="eff-btn eff-btn--ghost" href="#documents-rh">Documents RH</a>
            <a class="eff-btn eff-btn--ghost" href="#mobilite">Mobilité</a>
            <a class="eff-btn eff-btn--ghost" href="#historique-rh">Historique</a>
        </div>
    </article>
    <article class="eff-card">
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
                <dt>Habilitation</dt>
                <dd>
                    <?php if ($clearanceLabel !== ''): ?>
                        <?= htmlspecialchars($clearanceLabel, ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($clearanceReviewedAt !== ''): ?>
                            <span class="eff-card__hint">revue le <?= htmlspecialchars($fmtDate($clearanceReviewedAt), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($clearanceOverdue): ?>
                            <span class="eff-tag eff-tag--warn">À revoir</span>
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
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
                <dd><?= htmlspecialchars(implode(' · ', $medalRackItems), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </article>

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
        <?php endif; ?>
    </article>

    <article class="eff-card">
        <h2 class="eff-card__title">Unité</h2>
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
        <?php if ($canManageAssignments): ?>
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
        <?php endif; ?>
    </article>

    <article class="eff-card" id="fonctions">
        <h2 class="eff-card__title">Fonctions</h2>
        <?php if ($jobRoles !== []): ?>
            <div class="eff-tags" style="margin-bottom:0.85rem">
                <?php foreach ($jobRoles as $jr): ?>
                    <span class="eff-tag">
                        <?= htmlspecialchars((string) ($jr['role_name'] ?? 'Fonction'), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($jr['is_primary'])): ?> · principale<?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="eff-card__lead">Aucune fonction attribuée.</p>
        <?php endif; ?>
        <?php if ($canManageAssignments && $jobRolesAvailable && $jobRoleOptions !== []): ?>
            <?php
            $assignedJobRoleIds = array_map(static fn (array $jr): int => (int) ($jr['personnel_job_role_id'] ?? 0), $jobRoles);
            $primaryJobRoleId = 0;
            foreach ($jobRoles as $jr) {
                if (!empty($jr['is_primary'])) {
                    $primaryJobRoleId = (int) ($jr['personnel_job_role_id'] ?? 0);
                    break;
                }
            }
            ?>
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/fonctions'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <fieldset class="eff-role-grid">
                    <legend>Attribuer les fonctions (<?= $jobRoleMax ?> maximum)</legend>
                    <?php foreach ($jobRoleOptions as $option): ?>
                        <?php $jobRoleId = (int) ($option['id'] ?? 0); ?>
                        <label>
                            <input type="checkbox" name="job_role_ids[]" value="<?= $jobRoleId ?>" <?= in_array($jobRoleId, $assignedJobRoleIds, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars((string) ($option['label'] ?? $option['name'] ?? 'Fonction'), ENT_QUOTES, 'UTF-8') ?>
                            <span class="eff-card__hint"><input type="radio" name="primary_job_role_id" value="<?= $jobRoleId ?>" <?= $primaryJobRoleId === $jobRoleId ? 'checked' : '' ?>> principale</span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <p class="eff-card__hint">Cochez les fonctions exercées et désignez celle qui doit apparaître en priorité dans le dossier et l’ordre de bataille.</p>
                <button type="submit" class="eff-btn eff-btn--primary">Enregistrer les fonctions</button>
            </form>
        <?php elseif ($canManageAssignments && $jobRolesAvailable): ?>
            <p class="eff-card__lead">Le référentiel ne contient encore aucune fonction.</p>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url('fonctions'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le référentiel des fonctions</a>
        <?php elseif ($canManageAssignments): ?>
            <p class="eff-card__lead">La gestion des fonctions sera disponible après application des migrations.</p>
        <?php endif; ?>
    </article>

    <article class="eff-card">
        <h2 class="eff-card__title">Rôles et accès</h2>
        <?php if ($roleNames === []): ?>
            <p class="eff-card__lead">Aucun rôle attribué.</p>
        <?php else: ?>
            <div class="eff-tags" style="margin-bottom:0.85rem">
                <?php foreach ($roleNames as $rn): ?>
                    <span class="eff-tag"><?= htmlspecialchars((string) $rn, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($canManageRoles && $orgRoles !== []): ?>
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/roles'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_to" value="member">
                <fieldset class="eff-role-grid">
                    <legend>Attribuer les rôles</legend>
                    <?php foreach ($orgRoles as $role): ?>
                        <?php
                        $rid = (int) ($role['id'] ?? 0);
                        if ($rid < 1) {
                            continue;
                        }
                        $rname = trim((string) ($role['name'] ?? ''));
                        ?>
                        <label>
                            <input type="checkbox" name="role_ids[]" value="<?= $rid ?>" <?= in_array($rid, $memberRoleIds, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($rname !== '' ? $rname : 'Rôle', ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <button type="submit" class="eff-btn eff-btn--primary">Enregistrer les rôles</button>
            </form>
        <?php elseif ($canManageRoles): ?>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le catalogue des rôles</a>
        <?php endif; ?>
    </article>

    <?php if ($canManageGrades): ?>
    <article class="eff-card">
        <h2 class="eff-card__title">Grade</h2>
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/grade'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_to" value="member">
            <label for="eff-member-grade">Grade affiché</label>
            <select id="eff-member-grade" name="grade_id">
                <option value="">Aucun grade</option>
                <?php foreach ($grades as $g): ?>
                    <?php $gid = (int) ($g['id'] ?? 0); ?>
                    <option value="<?= $gid ?>" <?= $gradeId === $gid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($g['label_long'] ?? $g['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="eff-btn eff-btn--primary">Enregistrer le grade</button>
        </form>
    </article>
    <?php endif; ?>

    <article class="eff-card">
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
        <?php else: ?>
            <p class="eff-card__lead"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </article>

    <article class="eff-card">
        <h2 class="eff-card__title">Élévation</h2>
        <?php if ($elevationHistory !== []): ?>
            <ul class="eff-card__list">
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
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/elevation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_to" value="member">
                <p class="eff-card__lead">Demandez un changement de grade, de fonction, d’unité ou d’habilitation à une personne habilitée.</p>
                <?php
                $fieldIdPrefix = 'eff-member-elev';
                $selectedKind = 'grade';
                $includeUnit = true;
                require base_path('views/admin/effectifs_workspace/partials/elevation_request_fields.php');
                ?>
                <button type="submit" class="eff-btn eff-btn--warn">Envoyer la demande</button>
            </form>
        <?php elseif ($elevationNoRecipients ?? false): ?>
            <p class="eff-card__lead">Élévation indisponible : aucun autre membre n’est habilité à traiter la demande.</p>
        <?php else: ?>
            <p class="eff-card__lead">Les changements d’habilitation passent par une demande d’élévation.</p>
        <?php endif; ?>
    </article>

    <?php if ($canManageStatus): ?>
    <article class="eff-card">
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
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/depart'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card__form">
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
                Retirer immédiatement les rôles et l’habilitation
            </label>
            <?php endif; ?>
            <button type="submit" class="eff-btn eff-btn--warn">Confirmer le départ</button>
        </form>
    </article>
    <?php endif; ?>

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
    <article class="eff-card" id="qualifications">
        <h2 class="eff-card__title">Qualifications</h2>
        <?php $renderRhList($qualifications, static function (array $q) use ($fmtDate): string {
            $name = trim((string) ($q['qualification_name'] ?? $q['name'] ?? $q['title'] ?? 'Qualification'));
            $expiry = $fmtDate((string) ($q['expires_at'] ?? ''));
            return $name . ($expiry !== '' ? ' · échéance ' . $expiry : '');
        }); ?>
        <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url('qualifications'), ENT_QUOTES, 'UTF-8') ?>">Piloter les qualifications</a>
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

    <article class="eff-card" id="historique-rh" style="grid-column:1/-1">
        <h2 class="eff-card__title">Historique RH et bilans</h2>
        <?php $combinedHistory = array_merge($orgHistory, $serviceHistory, $stageBilans); ?>
        <?php $renderRhList($combinedHistory, static function (array $h) use ($fmtDate): string {
            $label = trim((string) ($h['title'] ?? $h['event_label'] ?? $h['event_type'] ?? $h['stage_label'] ?? $h['summary'] ?? 'Événement RH'));
            $date = $fmtDate((string) ($h['event_date'] ?? $h['occurred_at'] ?? $h['created_at'] ?? ''));
            return ($date !== '' ? $date . ' · ' : '') . $label;
        }); ?>
    </article>
</div>
