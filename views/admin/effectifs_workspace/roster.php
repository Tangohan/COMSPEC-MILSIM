<?php
declare(strict_types=1);

$rows = is_array($rosterRows ?? null) ? $rosterRows : [];
$filters = is_array($rosterFilters ?? null) ? $rosterFilters : [];
$sortOptions = is_array($rosterSortOptions ?? null) ? $rosterSortOptions : [];
$roles = is_array($orgRoles ?? null) ? $orgRoles : [];
$units = is_array($orgUnits ?? null) ? $orgUnits : [];
$total = (int) ($rosterTotal ?? 0);
$page = (int) ($rosterPage ?? 1);
$totalPages = (int) ($rosterTotalPages ?? 1);
$counts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$canEditProfiles = (bool) ($canEditProfiles ?? false);
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$canManageStatus = (bool) ($canManageStatus ?? false);
$canBulkAny = $canManageStatus || $canManageAssignments;
$canRequestElevation = (bool) ($canRequestElevation ?? false);
$elevationNoRecipients = (bool) ($elevationNoRecipients ?? false);
$csrfToken = (string) ($csrfToken ?? '');
$communityName = trim((string) ($communityName ?? 'Communauté'));
$orgFoundingDate = trim((string) ($orgFoundingDate ?? ''));
$currentSort = (string) ($filters['tri'] ?? 'nom');
$elevationCatalog = is_array($elevationCatalog ?? null) ? $elevationCatalog : [];
$elevationCooldownByUserId = is_array($elevationCooldownByUserId ?? null) ? $elevationCooldownByUserId : [];
$badgesByUserId = is_array($badgesByUserId ?? null) ? $badgesByUserId : [];
$cooldownLabel = static function (int $seconds): string {
    $hours = max(1, (int) ceil($seconds / 3600));
    if ($hours < 24) {
        return $hours . ' h';
    }

    return max(1, (int) ceil($hours / 24)) . ' j';
};

$filterQuery = static function (array $overrides = []) use ($filters, $page): array {
    $q = [
        'q' => $filters['q'] ?? null,
        'status' => !empty($filters['status']) ? $filters['status'] : null,
        'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
        'sans_affectation' => !empty($filters['sans_affectation']) ? '1' : null,
        'sans_role' => !empty($filters['sans_role']) ? '1' : null,
        'tri' => !empty($filters['tri']) && ($filters['tri'] ?? 'nom') !== 'nom' ? $filters['tri'] : null,
        'page' => $page > 1 ? $page : null,
    ];
    foreach ($overrides as $k => $v) {
        $q[$k] = $v;
    }

    return array_filter($q, static fn ($v) => $v !== null && $v !== '' && $v !== false && $v !== 0);
};

$returnUrl = effectifs_workspace_url() . (
    ($filters !== [] && array_filter($filters, static fn ($v) => $v !== null && $v !== '' && $v !== false && $v !== 0))
        ? '?' . http_build_query($filterQuery())
        : ''
);

$statusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'pending_verification' => 'E-mail à vérifier',
        default => $raw !== '' ? $raw : '—',
    };
};
$statusClass = static function (string $raw): string {
    return match ($raw) {
        'active' => 'eff-sheets__badge--ok',
        'inactive' => 'eff-sheets__badge--danger',
        'pending_verification' => 'eff-sheets__badge--watch',
        default => 'eff-sheets__badge--muted',
    };
};
$initials = static function (string $displayName, string $email): string {
    $displayName = trim($displayName);
    if ($displayName !== '') {
        $parts = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts !== false && count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[1], 0, 1, 'UTF-8'), 'UTF-8');
        }

        return mb_strtoupper(mb_substr($displayName, 0, 2, 'UTF-8'), 'UTF-8');
    }
    $local = preg_replace('/@.*$/', '', $email) ?: '?';

    return mb_strtoupper(mb_substr($local, 0, 2, 'UTF-8'), 'UTF-8');
};
$splitRoles = static function (string $rolesDisplay): array {
    $parts = preg_split('/\s*,\s*/u', $rolesDisplay, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
};

$queryUrl = static function (int $p) use ($filterQuery): string {
    $q = $filterQuery(['page' => $p > 1 ? $p : null]);

    return effectifs_workspace_url() . ($q ? '?' . http_build_query($q) : '');
};

$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || !empty($filters['role_id'])
    || !empty($filters['sans_affectation'])
    || !empty($filters['sans_role'])
    || (($filters['tri'] ?? 'nom') !== 'nom');

$exportQuery = array_filter([
    'q' => $filters['q'] ?? null,
    'status' => !empty($filters['status']) ? $filters['status'] : null,
    'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
    'sans_affectation' => !empty($filters['sans_affectation']) ? '1' : null,
    'sans_role' => !empty($filters['sans_role']) ? '1' : null,
], static fn ($v) => $v !== null && $v !== '' && $v !== 0);
$exportUrl = effectifs_workspace_url('export') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');
$dupScan = is_array($personnelDuplicateScan ?? null) ? $personnelDuplicateScan : [];
$dupGroups = is_array($dupScan['groups'] ?? null) ? $dupScan['groups'] : [];
$dupFieldLabels = [];
foreach ($dupGroups as $dupGroup) {
    $dupLabel = trim((string) ($dupGroup['field_label'] ?? ''));
    if ($dupLabel !== '') {
        $dupFieldLabels[$dupLabel] = $dupLabel;
    }
}
$dupFieldLabels = array_values($dupFieldLabels);
$dupFieldBits = array_map(static fn (string $label): string => 'le même ' . mb_strtolower($label), $dupFieldLabels);
$dupFieldPhrase = match (count($dupFieldBits)) {
    0 => 'les mêmes repères',
    1 => $dupFieldBits[0],
    default => implode(', ', array_slice($dupFieldBits, 0, -1)) . ' ou ' . $dupFieldBits[array_key_last($dupFieldBits)],
};
$dupGroupCount = (int) ($dupScan['group_count'] ?? 0);
$dupMemberCount = (int) ($dupScan['member_count'] ?? 0);
$metricAll = ($filters['status'] ?? '') === '' && empty($filters['sans_affectation']) && empty($filters['sans_role']);
$metricActive = ($filters['status'] ?? '') === 'active';
$metricNoUnit = !empty($filters['sans_affectation']);
$metricNoRole = !empty($filters['sans_role']);
$noUnitCount = (int) ($counts['no_unit'] ?? 0);
$noRoleCount = (int) ($counts['no_role'] ?? 0);
$clearanceCount = (int) ($counts['clearance_review_due'] ?? 0);
?>
<div class="eff-catalog eff-catalog--roster">
    <?php if (!empty($dupScan['enabled']) && $dupGroups !== []): ?>
    <aside class="eff-banner eff-banner--warn" role="status">
        <div class="eff-banner__body">
            <p class="eff-banner__title">Fiches jumelles à relire</p>
            <p class="eff-banner__text">
                <?= $dupGroupCount ?> groupe<?= $dupGroupCount > 1 ? 's' : '' ?> ·
                <?= $dupMemberCount ?> membre<?= $dupMemberCount > 1 ? 's' : '' ?>
                partagent <?= htmlspecialchars($dupFieldPhrase, ENT_QUOTES, 'UTF-8') ?>.
                Vérifiez qu’il ne s’agit pas de deux dossiers pour la même personne.
            </p>
        </div>
        <a href="<?= htmlspecialchars(effectifs_workspace_url('doublons'), ENT_QUOTES, 'UTF-8') ?>" class="eff-banner__action">Ouvrir les fiches</a>
    </aside>
    <?php endif; ?>
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Ressources humaines</p>
            <h1 class="eff-catalog__title">Effectifs</h1>
            <p class="eff-catalog__lead">
                Annuaire des membres de <?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?>.
                Identité, grade, fonction, unité et rôles se lisent ici ; une affectation ou une demande d’élévation se fait depuis le tableau.
                Organigramme et grades : <a href="<?= htmlspecialchars(url('back-office/organisation-effectifs'), ENT_QUOTES, 'UTF-8') ?>">Structure et grades</a>.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <?php if ($canEditProfiles): ?>
                <a href="<?= htmlspecialchars(effectifs_workspace_url('nouveau'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn eff-catalog__btn--primary">Ajouter un membre</a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('elevations'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Demandes d’élévation</a>
            <a href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Exporter en CSV</a>
            <?php if ($hasActiveFilters): ?>
                <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Réinitialiser</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canEditProfiles): ?>
    <div class="eff-catalog__notice">
            <p class="eff-catalog__kicker">Ancienneté réelle</p>
        <p class="eff-catalog__notice-lead">
            Date de création de l’organisation, y compris si elle est antérieure à l’arrivée sur Athena.
            Pour un membre déjà présent avant le site, renseignez-le dans la colonne Indicateurs.
        </p>
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('anciennete-entite'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__notice-form">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl ?? effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">
            <label>
                Création de l’organisation
                <input type="date" name="org_founded_on" value="<?= htmlspecialchars($orgFoundingDate, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary" style="height:2.1rem">Enregistrer pour tous les membres</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="eff-metrics eff-metrics--roster" aria-label="Synthèse des effectifs">
        <a class="eff-metric eff-metric--link<?= $metricAll ? ' is-active' : '' ?>" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">
            <p class="eff-metric__k">Membres</p>
            <p class="eff-metric__v"><?= (int) ($counts['total'] ?? $total) ?></p>
        </a>
        <a class="eff-metric eff-metric--link<?= $metricActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars(effectifs_workspace_url() . '?status=active', ENT_QUOTES, 'UTF-8') ?>">
            <p class="eff-metric__k">Actifs</p>
            <p class="eff-metric__v"><?= (int) ($counts['active'] ?? 0) ?></p>
        </a>
        <a class="eff-metric eff-metric--link<?= $metricNoUnit ? ' is-active is-amber' : '' ?><?= !$metricNoUnit && $noUnitCount > 0 ? ' is-amber' : '' ?>" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>">
            <p class="eff-metric__k">Sans unité</p>
            <p class="eff-metric__v"><?= $noUnitCount ?></p>
        </a>
        <a class="eff-metric eff-metric--link<?= $metricNoRole ? ' is-active is-amber' : '' ?><?= !$metricNoRole && $noRoleCount > 0 ? ' is-amber' : '' ?>" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_role=1', ENT_QUOTES, 'UTF-8') ?>">
            <p class="eff-metric__k">Sans rôle</p>
            <p class="eff-metric__v"><?= $noRoleCount ?></p>
        </a>
        <a class="eff-metric eff-metric--link<?= $clearanceCount > 0 ? ' is-amber' : '' ?>" href="<?= htmlspecialchars(effectifs_workspace_url('alertes'), ENT_QUOTES, 'UTF-8') ?>">
            <p class="eff-metric__k">Habilitation à revoir</p>
            <p class="eff-metric__v"><?= $clearanceCount ?></p>
        </a>
    </div>

    <form method="get" action="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="eff-catalog-filters">
            <div>
                <label for="eff-q">Recherche</label>
                <input id="eff-q" type="search" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, indicatif, e-mail…">
            </div>
            <div>
                <label for="eff-status">Statut du compte</label>
                <select id="eff-status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Compte actif</option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Compte inactif</option>
                    <option value="pending_verification" <?= (($filters['status'] ?? '') === 'pending_verification') ? 'selected' : '' ?>>E-mail à vérifier</option>
                </select>
            </div>
            <div>
                <label for="eff-role">Rôle</label>
                <select id="eff-role" name="role_id">
                    <option value="">Tous les rôles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) ($role['id'] ?? 0) ?>" <?= ((int) ($filters['role_id'] ?? 0) === (int) ($role['id'] ?? 0)) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="eff-tri">Trier par</label>
                <select id="eff-tri" name="tri">
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= $currentSort === (string) $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary" style="width:100%;height:2.25rem">Appliquer</button>
            </div>
        </div>
        <div class="eff-catalog-checks">
            <label class="eff-catalog-check<?= !empty($filters['sans_affectation']) ? ' is-on' : '' ?>">
                <input type="checkbox" name="sans_affectation" value="1" <?= !empty($filters['sans_affectation']) ? 'checked' : '' ?>>
                <span>Sans unité</span>
            </label>
            <label class="eff-catalog-check<?= !empty($filters['sans_role']) ? ' is-on' : '' ?>">
                <input type="checkbox" name="sans_role" value="1" <?= !empty($filters['sans_role']) ? 'checked' : '' ?>>
                <span>Sans rôle</span>
            </label>
        </div>
    </form>

    <?php if ($rows === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucun membre ne correspond</strong>
            Élargissez la recherche ou retirez un filtre.
            <?php if ($hasActiveFilters): ?>
                <div style="margin-top:1rem">
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">Voir tous les effectifs</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if ($canBulkAny): ?>
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('bulk/statut'), ENT_QUOTES, 'UTF-8') ?>" id="eff-bulk-form" class="eff-bulkbar" data-eff-bulk-bar>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
            <span class="eff-bulkbar__count" data-eff-bulk-count>0 sélectionné</span>
            <?php if ($canManageStatus): ?>
            <select name="status">
                <option value="active">Passer actif</option>
                <option value="inactive">Passer inactif</option>
                <option value="pending_verification">E-mail à vérifier</option>
            </select>
            <button type="submit" formaction="<?= htmlspecialchars(effectifs_workspace_url('bulk/statut'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn eff-catalog__btn--primary" data-eff-bulk-submit disabled>Appliquer le statut</button>
            <?php endif; ?>
            <?php if ($canManageAssignments): ?>
            <select name="unit_id">
                <option value="0">Retirer l’affectation</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars(trim((string) ($u['assignment_path'] ?? $u['name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="reason" maxlength="255" placeholder="Motif du changement">
            <button type="submit" formaction="<?= htmlspecialchars(effectifs_workspace_url('bulk/affectation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn eff-catalog__btn--primary" data-eff-bulk-submit disabled>Affecter l’unité</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
        <div class="eff-sheets" role="region" aria-label="Tableur des effectifs" tabindex="0">
            <table class="eff-sheets__table" id="eff-roster-table" data-cols-storage="eff-roster-col-widths-v1">
                <colgroup>
                    <?php if ($canBulkAny): ?><col style="width:2rem"><?php endif; ?>
                    <col data-col="identity" style="width:14rem">
                    <col data-col="grade" style="width:6.5rem">
                    <col data-col="fonction" style="width:9rem">
                    <col data-col="affectation" style="width:14rem">
                    <col data-col="roles" style="width:11rem">
                    <col data-col="reperes" style="width:12rem">
                    <col data-col="indicateurs" style="width:14rem">
                    <col data-col="statut" style="width:7.5rem">
                    <col data-col="actions" style="width:13rem">
                </colgroup>
                <thead>
                    <tr>
                        <?php if ($canBulkAny): ?>
                        <th style="width:2rem"><input type="checkbox" data-eff-bulk-all aria-label="Tout sélectionner"></th>
                        <?php endif; ?>
                        <th data-col="identity">Identité<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Identité" tabindex="0"></span></th>
                        <th data-col="grade">Grade<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Grade" tabindex="0"></span></th>
                        <th data-col="fonction">Fonction<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Fonction" tabindex="0"></span></th>
                        <th data-col="affectation">Affectation<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Affectation" tabindex="0"></span></th>
                        <th data-col="roles">Rôles<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Rôles" tabindex="0"></span></th>
                        <th data-col="reperes">Repères<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Repères" tabindex="0"></span></th>
                        <th data-col="indicateurs">Indicateurs<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Indicateurs" tabindex="0"></span></th>
                        <th data-col="statut">Statut<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Statut" tabindex="0"></span></th>
                        <th data-col="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $id = (int) ($row['id'] ?? 0);
                    $display = trim((string) ($row['display_name'] ?? ''));
                    $callsign = trim((string) ($row['callsign'] ?? ''));
                    $email = (string) ($row['email'] ?? '');
                    $name = $display !== '' ? $display : ($callsign !== '' ? $callsign : $email);
                    $status = (string) ($row['status'] ?? '');
                    $grade = function_exists('personnel_assigned_grade_label')
                        ? personnel_assigned_grade_label($row)
                        : (trim((string) ($row['grade_long'] ?? '')) !== ''
                            ? trim((string) ($row['grade_long'] ?? ''))
                            : trim((string) ($row['grade_short'] ?? '')));
                    $fonction = trim((string) ($row['job_role_display'] ?? ''));
                    $unit = trim((string) ($row['unit_name'] ?? ''));
                    $assignmentPath = trim((string) ($row['assignment_path'] ?? ''));
                    if ($assignmentPath === '' && $unit !== '') {
                        $assignmentPath = $unit;
                    }
                    $assignmentLeaf = $assignmentPath;
                    $assignmentParent = '';
                    if ($assignmentPath !== '' && str_contains($assignmentPath, '/')) {
                        $pathParts = array_values(array_filter(array_map('trim', explode('/', $assignmentPath)), static fn (string $part): bool => $part !== ''));
                        if (count($pathParts) >= 2) {
                            $assignmentLeaf = (string) $pathParts[array_key_last($pathParts)];
                            $assignmentParent = implode(' · ', array_slice($pathParts, 0, -1));
                        }
                    }
                    $unitId = (int) ($row['unit_id'] ?? 0);
                    $rolesDisplay = trim((string) ($row['roles_display'] ?? ($row['role_name'] ?? '')));
                    $roleParts = $splitRoles($rolesDisplay);
                    $roleVisible = array_slice($roleParts, 0, 2);
                    $roleExtra = max(0, count($roleParts) - 2);
                    $ficheUrl = effectifs_workspace_url('membres/' . $id);
                    $personnelEditUrl = effectifs_workspace_url('membres/' . $id) . '#modifier-dossier';
                    $avatarUrl = function_exists('personnel_operator_portrait_url')
                        ? (string) (personnel_operator_portrait_url($row) ?? '')
                        : (function_exists('user_media_public_url')
                            ? (user_media_public_url($row['avatar_url'] ?? null) ?? '')
                            : trim((string) ($row['avatar_url'] ?? '')));
                    $seniorityLabel = trim((string) ($row['seniority_label'] ?? '—'));
                    $prePlatformStart = trim((string) ($row['pre_platform_start'] ?? ''));
                    $enlistmentStart = trim((string) ($row['enlistment_date_resolved'] ?? ''));
                    $prePlatformLabel = trim((string) ($row['seniority_pre_platform_label'] ?? ''));
                    $communitySeniorityLabel = trim((string) ($row['seniority_community_label'] ?? ''));
                    $availabilityScore = (int) ($row['availability_score'] ?? 0);
                    $presenceScore = (int) ($row['presence_score'] ?? 0);
                    $completionScore = (int) ($row['completion_score'] ?? 0);
                    $clearanceOverdue = \App\Support\ClearanceReviewPolicy::isOverdue(
                        $row['clearance_level'] ?? null,
                        $row['clearance_reviewed_at'] ?? null
                    );
                    $character = \App\Support\PersonnelDirectoryHints::distinctCharacterLabel($name, (string) ($row['character_name'] ?? ''));
                    $matricule = trim((string) ($row['matricule_internal'] ?? '')) ?: trim((string) ($row['service_number'] ?? ''));
                    $radioAssigned = trim((string) ($row['radio_assigned'] ?? ''));
                    $memberBadges = is_array($badgesByUserId[$id] ?? null) ? $badgesByUserId[$id] : [];
                    ?>
                    <tr>
                        <?php if ($canBulkAny): ?>
                        <td><input type="checkbox" class="eff-bulk-check" name="user_ids[]" value="<?= $id ?>" form="eff-bulk-form" aria-label="Sélectionner <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"></td>
                        <?php endif; ?>
                        <td>
                            <div class="eff-sheets__identity">
                                <span class="eff-sheets__avatar" aria-hidden="true">
                                    <?php if ($avatarUrl !== ''): ?>
                                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initials($name, $email), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                                <div class="eff-sheets__id-text">
                                    <strong class="eff-sheets__name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($callsign !== '' && strcasecmp($callsign, $name) !== 0): ?>
                                        <span class="eff-sheets__meta">Indicatif · <?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php if ($character !== ''): ?><span class="eff-sheets__meta">Personnage · <?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($grade !== ''): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--grade"><?= htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-sheets__path-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($fonction !== ''): ?>
                                <span class="eff-sheets__cell-text"><?= htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch">Fonction manquante</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="eff-sheets__assign">
                                <?php if ($assignmentPath !== ''): ?>
                                    <span class="eff-sheets__path" title="<?= htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($assignmentLeaf, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if ($assignmentParent !== ''): ?>
                                        <span class="eff-sheets__path-muted" title="<?= htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($assignmentParent, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--watch">Sans unité</span>
                                <?php endif; ?>
                                <?php if ($canManageAssignments): ?>
                                    <details class="eff-sheets__pop">
                                        <summary class="eff-sheets__chip" style="height:1.4rem"><?= $assignmentPath !== '' ? 'Modifier' : 'Affecter' ?></summary>
                                        <div class="eff-sheets__pop-panel">
                                            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/affectation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-sheets__pop-form">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                <label for="eff-unit-<?= $id ?>">Unité de rattachement</label>
                                                <select id="eff-unit-<?= $id ?>" name="unit_id">
                                                    <option value="0">Retirer l’affectation</option>
                                                    <?php foreach ($units as $u): ?>
                                                        <?php
                                                        $optId = (int) ($u['id'] ?? 0);
                                                        $optLabel = trim((string) ($u['assignment_path'] ?? $u['name'] ?? ''));
                                                        ?>
                                                        <option value="<?= $optId ?>" <?= $unitId === $optId ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary" style="height:1.85rem">Enregistrer</button>
                                                <a class="eff-sheets__pop-link" href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir le dossier</a>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($roleParts === []): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch">Sans rôle</span>
                            <?php else: ?>
                                <div class="eff-sheets__tags" title="<?= htmlspecialchars($rolesDisplay, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($roleVisible as $rn): ?>
                                        <span class="eff-sheets__badge eff-sheets__badge--muted"><?= htmlspecialchars($rn, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($roleExtra > 0): ?>
                                        <span class="eff-sheets__badge eff-sheets__badge--info">+<?= $roleExtra ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="eff-sheets__reperes">
                                <span><b>Matricule</b> <?= $matricule !== '' ? htmlspecialchars($matricule, ENT_QUOTES, 'UTF-8') : '—' ?></span>
                                <span><b>Radio</b> <?= $radioAssigned !== '' ? htmlspecialchars($radioAssigned, ENT_QUOTES, 'UTF-8') : '—' ?></span>
                                <?php if ($memberBadges !== []): ?>
                                    <?php $badgeNames = array_values(array_filter(array_map(static fn (array $badge): string => trim((string) ($badge['name'] ?? '')), $memberBadges))); ?>
                                    <span class="eff-sheets__distinctions" title="<?= htmlspecialchars(implode(' · ', $badgeNames), ENT_QUOTES, 'UTF-8') ?>"><b>Distinctions</b> <?= count($memberBadges) ?> · <?= htmlspecialchars(implode(', ', array_slice($badgeNames, 0, 2)), ENT_QUOTES, 'UTF-8') ?><?= count($badgeNames) > 2 ? '…' : '' ?></span>
                                <?php else: ?>
                                    <span><b>Distinctions</b> —</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="eff-sheets__metrics">
                                <span class="eff-sheets__metric" title="Ancienneté réelle<?= $prePlatformLabel !== '' ? ' · avant le site : ' . $prePlatformLabel : '' ?><?= $communitySeniorityLabel !== '' ? ' · communauté : ' . $communitySeniorityLabel : '' ?>">Anc. <?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($prePlatformStart !== ''): ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--info" title="Arrivée avant le site">Avant site</span>
                                <?php endif; ?>
                                <span class="eff-sheets__metric" title="Disponibilité">Disp. <?= $availabilityScore ?>%</span>
                                <span class="eff-sheets__metric" title="Présence">Prés. <?= $presenceScore ?>%</span>
                                <span class="eff-sheets__metric" title="Complétion du dossier">Doss. <?= $completionScore ?>%</span>
                                <?php if ($clearanceOverdue): ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--watch" title="Habilitation accordée sans revue récente (&gt; <?= \App\Support\ClearanceReviewPolicy::REVIEW_INTERVAL_DAYS ?> jours)">Habilitation à revoir</span>
                                <?php endif; ?>
                                <?php if ($canEditProfiles): ?>
                                    <details class="eff-sheets__pop">
                                        <summary class="eff-sheets__chip" style="height:1.4rem">Ancienneté</summary>
                                        <div class="eff-sheets__pop-panel">
                                            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/anciennete'), ENT_QUOTES, 'UTF-8') ?>" class="eff-sheets__pop-form">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                <label for="eff-enlist-<?= $id ?>">Arrivée dans la communauté (sur Athena)</label>
                                                <input id="eff-enlist-<?= $id ?>" type="date" name="enlistment_date" value="<?= htmlspecialchars($enlistmentStart, ENT_QUOTES, 'UTF-8') ?>">
                                                <label for="eff-pre-<?= $id ?>">Arrivée avant le site</label>
                                                <input id="eff-pre-<?= $id ?>" type="date" name="pre_platform_start_date" value="<?= htmlspecialchars($prePlatformStart, ENT_QUOTES, 'UTF-8') ?>">
                                                <p style="margin:0;font-size:11px;color:#64748b">Laissez vide s’il n’était pas membre avant l’ouverture du site.</p>
                                                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary" style="height:1.85rem">Enregistrer</button>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="eff-sheets__badge <?= htmlspecialchars($statusClass($status), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <div class="eff-sheets__actions">
                                <a class="is-primary" href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>">Fiche</a>
                                <?php if ($canEditProfiles): ?>
                                    <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>">Modifier</a>
                                <?php endif; ?>
                                <?php if ($canRequestElevation): ?>
                                    <?php $cooldownSec = (int) ($elevationCooldownByUserId[$id] ?? 0); ?>
                                    <?php if ($cooldownSec > 0): ?>
                                        <span class="eff-sheets__chip" style="opacity:.55;cursor:default" title="Une demande a déjà été envoyée récemment pour ce membre — patientez avant d’en renvoyer une.">Élévation (patientez <?= htmlspecialchars($cooldownLabel($cooldownSec), ENT_QUOTES, 'UTF-8') ?>)</span>
                                    <?php else: ?>
                                    <button type="button" class="eff-sheets__chip" data-eff-elev-open="<?= $id ?>">Élévation</button>
                                    <?php endif; ?>
                                <?php elseif ($elevationNoRecipients): ?>
                                    <span class="eff-sheets__chip" style="opacity:.55;cursor:default" title="Aucun autre membre habilité à traiter une demande d’élévation dans cette communauté (vous êtes le seul, ou personne n’a le droit requis).">Élévation indisponible</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($canRequestElevation && (int) ($elevationCooldownByUserId[$id] ?? 0) < 1): ?>
                            <dialog class="eff-elev-dialog" id="eff-elev-dialog-<?= $id ?>" aria-labelledby="eff-elev-dialog-title-<?= $id ?>">
                                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/elevation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-elev-dialog__inner eff-sheets__pop-form">
                                    <h3 id="eff-elev-dialog-title-<?= $id ?>" class="eff-elev-dialog__title">Demander une élévation</h3>
                                    <p class="eff-elev-dialog__lead"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php
                                    $fieldIdPrefix = 'eff-elev-' . $id;
                                    $selectedKind = 'grade';
                                    $includeUnit = true;
                                    require base_path('views/admin/effectifs_workspace/partials/elevation_request_fields.php');
                                    ?>
                                    <div class="eff-elev-dialog__actions">
                                        <button type="button" class="eff-catalog__btn" data-eff-elev-close>Annuler</button>
                                        <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Envoyer la demande</button>
                                    </div>
                                </form>
                            </dialog>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="eff-catalog-foot">
            <p style="margin:0">
                <strong><?= $total ?></strong>
                membre<?= $total > 1 ? 's' : '' ?> — page <?= $page ?> / <?= $totalPages ?>
            </p>
            <div class="eff-catalog-foot__links">
                <?php if ($page > 1): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($queryUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>">Page précédente</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($queryUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>">Page suivante</a>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function () {
            var table = document.getElementById('eff-roster-table');
            if (!table) return;
            var storageKey = table.getAttribute('data-cols-storage') || 'eff-roster-col-widths-v1';
            var cols = table.querySelectorAll('colgroup col[data-col]');
            var minWidth = 56;

            function applyWidths(map) {
                cols.forEach(function (col) {
                    var key = col.getAttribute('data-col');
                    if (!key || !map[key]) return;
                    var w = parseInt(map[key], 10);
                    if (!isFinite(w) || w < minWidth) return;
                    col.style.width = w + 'px';
                });
            }

            function readStored() {
                try {
                    var raw = localStorage.getItem(storageKey);
                    if (!raw) return {};
                    var parsed = JSON.parse(raw);
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (e) {
                    return {};
                }
            }

            function writeStored(map) {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(map));
                } catch (e) { /* ignore quota / private mode */ }
            }

            function currentMap() {
                var map = {};
                cols.forEach(function (col) {
                    var key = col.getAttribute('data-col');
                    if (!key) return;
                    var w = parseInt(col.style.width, 10);
                    if (!isFinite(w) || w < minWidth) {
                        w = Math.round(col.getBoundingClientRect().width);
                    }
                    if (isFinite(w) && w >= minWidth) map[key] = w;
                });
                return map;
            }

            applyWidths(readStored());

            table.querySelectorAll('thead th .eff-sheets__col-resizer').forEach(function (handle) {
                var th = handle.closest('th');
                if (!th) return;
                var colKey = th.getAttribute('data-col');
                if (!colKey) return;
                var col = table.querySelector('colgroup col[data-col="' + colKey + '"]');
                if (!col) return;

                function startResize(clientX) {
                    var startX = clientX;
                    var startW = col.getBoundingClientRect().width;
                    document.body.classList.add('eff-sheets--resizing');

                    function onMove(ev) {
                        var next = Math.max(minWidth, Math.round(startW + (ev.clientX - startX)));
                        col.style.width = next + 'px';
                    }

                    function onUp() {
                        document.body.classList.remove('eff-sheets--resizing');
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        writeStored(currentMap());
                    }

                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                }

                handle.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    startResize(ev.clientX);
                });

                handle.addEventListener('keydown', function (ev) {
                    var step = ev.shiftKey ? 24 : 8;
                    var w = Math.round(col.getBoundingClientRect().width);
                    if (ev.key === 'ArrowLeft') {
                        ev.preventDefault();
                        col.style.width = Math.max(minWidth, w - step) + 'px';
                        writeStored(currentMap());
                    } else if (ev.key === 'ArrowRight') {
                        ev.preventDefault();
                        col.style.width = (w + step) + 'px';
                        writeStored(currentMap());
                    }
                });
            });
        })();
        </script>
    <?php endif; ?>
</div>
        <script>
        (function () {
            document.querySelectorAll('[data-eff-elev-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-eff-elev-open');
                    var dialog = id ? document.getElementById('eff-elev-dialog-' + id) : null;
                    if (dialog && typeof dialog.showModal === 'function') {
                        dialog.showModal();
                    }
                });
            });
            document.querySelectorAll('[data-eff-elev-close]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var dialog = btn.closest('dialog');
                    if (dialog) {
                        dialog.close();
                    }
                });
            });
            document.querySelectorAll('dialog.eff-elev-dialog').forEach(function (dialog) {
                dialog.addEventListener('click', function (ev) {
                    if (ev.target === dialog) {
                        dialog.close();
                    }
                });
            });
        })();
        </script>
        <?php if ($canBulkAny && $rows !== []): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/eff-bulk-actions.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
