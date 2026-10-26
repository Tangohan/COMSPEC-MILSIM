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
$canManageRoles = (bool) ($canManageRoles ?? false);
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
$phaseBadgesByUserId = is_array($phaseBadgesByUserId ?? null) ? $phaseBadgesByUserId : [];
$rosterLanguages = is_array($rosterLanguages ?? null) ? $rosterLanguages : [];
$rosterLanguageCodes = is_array($rosterLanguageCodes ?? null) ? $rosterLanguageCodes : [];
$languageFilter = (string) ($filters['langue'] ?? '');
$rosterView = in_array((string) ($rosterView ?? ($filters['vue'] ?? '')), ['medical', 'aviation'], true) ? (string) ($rosterView ?? $filters['vue']) : 'general';
$jobFilter = (string) ($filters['fonction'] ?? '');
$jobOptions = is_array($rosterJobRoleOptions ?? null) ? $rosterJobRoleOptions : [];
if ($jobFilter !== '' && !in_array($jobFilter, $jobOptions, true)) {
    array_unshift($jobOptions, $jobFilter);
}
// Durée en heures, lisible dans une cellule : « 52 h 30 », « 45 min ».
$hours = static function (int $seconds): string {
    if ($seconds < 3600) {
        return max(1, intdiv($seconds, 60)) . ' min';
    }
    $m = intdiv($seconds, 60);

    return intdiv($m, 60) . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
};
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
        'langue' => !empty($filters['langue']) ? $filters['langue'] : null,
        'fonction' => !empty($filters['fonction']) ? $filters['fonction'] : null,
        'vue' => !empty($filters['vue']) && $filters['vue'] !== 'general' ? $filters['vue'] : null,
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
$queryUrlView = static function (string $view) use ($filterQuery): string {
    $q = $filterQuery(['vue' => $view !== 'general' ? $view : null, 'page' => null]);

    return effectifs_workspace_url() . ($q ? '?' . http_build_query($q) : '');
};

$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || !empty($filters['role_id'])
    || !empty($filters['sans_affectation'])
    || !empty($filters['sans_role'])
    || !empty($filters['langue'])
    || !empty($filters['fonction'])
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
    <div class="eff-roster-hero eff-roster-hero--compact">
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
        </div>
        <?php if ($canEditProfiles || $orgFoundingDate !== ''): ?>
        <details class="eff-roster-hero__seniority eff-catalog__notice">
            <summary class="eff-roster-seniority__summary">
                <span class="eff-catalog__kicker">Ancienneté réelle</span>
                <?php if ($orgFoundingDate !== ''): ?>
                    <?php $orgFoundedTs = strtotime($orgFoundingDate); ?>
                    <?php if ($orgFoundedTs !== false): ?>
                    <span class="eff-roster-hero__seniority-date">Organisation créée le <?= htmlspecialchars(date('d/m/Y', $orgFoundedTs), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="eff-roster-hero__seniority-date">Date de création à renseigner</span>
                <?php endif; ?>
            </summary>
            <div class="eff-roster-seniority__panel">
            <p class="eff-catalog__notice-lead">
                La date de création de l’organisation compte pour tout le monde, y compris si la communauté
                existait avant l’arrivée sur Athena. Pour un membre déjà présent à cette époque, indiquez
                son arrivée avec le bouton « Ancienneté » de sa ligne.
            </p>
            <?php if ($canEditProfiles): ?>
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('anciennete-entite'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__notice-form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl ?? effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">
                <label>
                    Création de l’organisation
                    <input type="date" name="org_founded_on" value="<?= htmlspecialchars($orgFoundingDate, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Enregistrer pour tous les membres</button>
            </form>
            <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
    </div>


    <form method="get" action="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-roster-toolbar" data-autosubmit>
        <?php if (!empty($filters['sans_affectation'])): ?>
            <input type="hidden" name="sans_affectation" value="1">
        <?php endif; ?>
        <?php if (!empty($filters['sans_role'])): ?>
            <input type="hidden" name="sans_role" value="1">
        <?php endif; ?>
        <?php if ($rosterView !== 'general'): ?>
            <input type="hidden" name="vue" value="<?= htmlspecialchars($rosterView, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="eff-catalog-filters">
            <div class="eff-roster-toolbar__field eff-roster-toolbar__field--grow">
                <label for="eff-q">Recherche</label>
                <input id="eff-q" type="search" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, indicatif, e-mail…">
            </div>
            <div class="eff-roster-toolbar__field">
                <label for="eff-status">Statut du compte</label>
                <select id="eff-status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Compte actif</option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Compte inactif</option>
                    <option value="pending_verification" <?= (($filters['status'] ?? '') === 'pending_verification') ? 'selected' : '' ?>>E-mail à vérifier</option>
                </select>
            </div>
            <div class="eff-roster-toolbar__field">
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
            <?php if ($jobOptions !== []): ?>
            <div class="eff-roster-toolbar__field">
                <label for="eff-fonction">Fonction</label>
                <select id="eff-fonction" name="fonction">
                    <option value="">Toutes les fonctions</option>
                    <?php foreach ($jobOptions as $jobName): ?>
                        <option value="<?= htmlspecialchars((string) $jobName, ENT_QUOTES, 'UTF-8') ?>" <?= $jobFilter === (string) $jobName ? 'selected' : '' ?>><?= htmlspecialchars((string) $jobName, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (count($rosterLanguageCodes) > 1 || $languageFilter !== ''): ?>
            <div class="eff-roster-toolbar__field">
                <label for="eff-langue">Langue</label>
                <select id="eff-langue" name="langue">
                    <option value="">Toutes les langues</option>
                    <?php foreach (array_values(array_unique(array_merge($rosterLanguageCodes, $languageFilter !== '' ? [$languageFilter] : []))) as $langCode): ?>
                        <option value="<?= htmlspecialchars($langCode, ENT_QUOTES, 'UTF-8') ?>" <?= $languageFilter === $langCode ? 'selected' : '' ?>><?= htmlspecialchars(\App\Support\CommunityLanguages::flag($langCode) . ' ' . \App\Support\CommunityLanguages::label($langCode), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="eff-roster-toolbar__field">
                <label for="eff-tri">Trier par</label>
                <select id="eff-tri" name="tri">
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= $currentSort === (string) $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-roster-toolbar__actions">
                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Appliquer</button>
                <?php if ($hasActiveFilters): ?>
                <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Réinitialiser</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <nav class="eff-das-views" aria-label="Colonnes du tableur">
        <?php foreach (['general' => ['Vue générale', 'Grade, affectation, fonction, accès, temps en mission, heures de vol'], 'medical' => ['Médical', 'Groupe sanguin, poids, taille, intolérances et allergies'], 'aviation' => ['Heures de vol', 'Heures aux commandes, par type d’appareil']] as $viewKey => [$viewLabel, $viewHint]): ?>
            <a class="eff-das-views__tab<?= $rosterView === $viewKey ? ' is-active' : '' ?>" href="<?= htmlspecialchars($queryUrlView($viewKey), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($viewHint, ENT_QUOTES, 'UTF-8') ?>"<?= $rosterView === $viewKey ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
        <span class="eff-das-views__hint"><?= htmlspecialchars(match ($rosterView) {
            'medical' => 'Données du dossier médical, modifiables depuis la fiche (état civil).',
            'aviation' => 'Heures relevées en jeu par la liaison ATAK, cumulées depuis le début.',
            default => 'Un membre par ligne : cliquez sur un nom pour ouvrir sa fiche.',
        }, ENT_QUOTES, 'UTF-8') ?></span>
    </nav>

    <?php if ($rows === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucun membre ne correspond</strong>
            Élargissez la recherche ou retirez un filtre.
            <?php if ($hasActiveFilters): ?>
                <div class="eff-catalog__empty-actions">
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
        <?php
        // Colonnes « type d'appareil » de la vue Aviation : les appareils les plus pilotés de la page, 6 au plus.
        $aviationFamilies = [];
        if ($rosterView === 'aviation') {
            foreach ($rows as $avRow) {
                foreach (is_array($avRow['pilot_families'] ?? null) ? $avRow['pilot_families'] : [] as $fam) {
                    $fk = (string) ($fam['key'] ?? '');
                    if ($fk === '') {
                        continue;
                    }
                    $aviationFamilies[$fk] ??= ['label' => (string) ($fam['label'] ?? $fk), 'seconds' => 0];
                    $aviationFamilies[$fk]['seconds'] += (int) ($fam['seconds'] ?? 0);
                }
            }
            uasort($aviationFamilies, static fn (array $x, array $y): int => $y['seconds'] <=> $x['seconds']);
            $aviationFamilies = array_slice($aviationFamilies, 0, 6, true);
        }
        ?>
        <div class="eff-sheets eff-sheets--das" role="region" aria-label="Tableur des effectifs" tabindex="0">
            <table class="eff-sheets__table eff-das__table eff-das__table--<?= htmlspecialchars($rosterView, ENT_QUOTES, 'UTF-8') ?>" id="eff-roster-table">
                <thead>
                    <tr>
                        <?php if ($canBulkAny): ?>
                        <th class="eff-das__check"><input type="checkbox" data-eff-bulk-all aria-label="Tout sélectionner"></th>
                        <?php endif; ?>
                        <th class="eff-das__sticky">Membre</th>
                        <?php if ($rosterView === 'medical'): ?>
                            <th>Fonction</th>
                            <th>Groupe sanguin</th>
                            <th class="eff-das__num">Poids</th>
                            <th class="eff-das__num">Taille</th>
                            <th class="eff-das__num" title="Indice de masse corporelle, calculé avec le poids et la taille">IMC</th>
                            <th>Intolérances médicamenteuses</th>
                            <th>Allergies</th>
                        <?php elseif ($rosterView === 'aviation'): ?>
                            <th>Fonction</th>
                            <th>Affectation</th>
                            <th class="eff-das__num">Heures de vol</th>
                            <?php foreach ($aviationFamilies as $fam): ?>
                                <th class="eff-das__num"><?= htmlspecialchars((string) $fam['label'], ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                            <th>Dernier vol</th>
                        <?php else: ?>
                            <th>Grade</th>
                            <th>Affectation</th>
                            <th>Fonction</th>
                            <th>Accès</th>
                            <th>Ancienneté</th>
                            <th class="eff-das__num">Temps en mission</th>
                            <th class="eff-das__num" title="Heures aux commandes d'un aéronef, tous appareils confondus">Heures de vol</th>
                            <th title="Disponibilité et présence">Disponibilité</th>
                            <th>Dossier</th>
                        <?php endif; ?>
                        <th class="eff-das__actions-col">Fiche</th>
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
                    $assignmentLeaf = \App\Support\UnitAbbreviation::standalone($assignmentPath);
                    $assignmentParent = '';
                    if ($assignmentPath !== '' && str_contains($assignmentPath, '/')) {
                        $pathParts = array_values(array_filter(array_map('trim', explode('/', $assignmentPath)), static fn (string $part): bool => $part !== ''));
                        if (count($pathParts) >= 2) {
                            $assignmentTrail = \App\Support\UnitAbbreviation::trail($pathParts);
                            $assignmentLeaf = (string) $assignmentTrail[array_key_last($assignmentTrail)]['short'];
                            $assignmentParent = implode(' · ', array_column(array_slice($assignmentTrail, 0, -1), 'short'));
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
                        : '';
                    if ($avatarUrl !== '' && str_contains($avatarUrl, 'inconnu.svg')) {
                        $avatarUrl = '';
                    }
                    $rowInitials = $initials($name, $email);
                    $accessRoleIds = array_values(array_filter(
                        array_map('intval', is_array($row['access_role_ids'] ?? null) ? $row['access_role_ids'] : []),
                        static fn (int $rid): bool => $rid > 0
                    ));
                    // Tout le monde porte Membre : présélectionner le niveau le plus élevé tenu.
                    $currentAccessRoleId = \App\Services\Rbac\CommunityAccessProfiles::currentAccessRoleId($roles, $accessRoleIds);
                    if ($currentAccessRoleId < 1 && $roleParts !== []) {
                        foreach ($roles as $accessRole) {
                            $roleName = trim((string) ($accessRole['name'] ?? ''));
                            if ($roleName !== '' && in_array($roleName, $roleParts, true)) {
                                $currentAccessRoleId = (int) ($accessRole['id'] ?? 0);
                                break;
                            }
                        }
                    }
                    $orgNumber = trim((string) ($row['tenant_member_number'] ?? ''));
                    $extraCallsigns = function_exists('personnel_decode_extra_callsigns')
                        ? personnel_decode_extra_callsigns($row['extra_callsigns_json'] ?? null)
                        : [];
                    $seniorityLabel = trim((string) ($row['seniority_label'] ?? '—'));
                    $prePlatformStart = trim((string) ($row['pre_platform_start'] ?? ''));
                    $enlistmentStart = trim((string) ($row['enlistment_date_resolved'] ?? ''));
                    $prePlatformLabel = trim((string) ($row['seniority_pre_platform_label'] ?? ''));
                    $communitySeniorityLabel = trim((string) ($row['seniority_community_label'] ?? ''));
                    $availabilityScore = (int) ($row['availability_score'] ?? 0);
                    $presenceScore = (int) ($row['presence_score'] ?? 0);
                    $completionScore = (int) ($row['completion_score'] ?? 0);
                    $character = \App\Support\PersonnelDirectoryHints::distinctCharacterLabel($name, (string) ($row['character_name'] ?? ''));
                    $matricule = trim((string) ($row['matricule_internal'] ?? '')) ?: trim((string) ($row['service_number'] ?? ''));
                    $radioAssigned = trim((string) ($row['radio_assigned'] ?? ''));
                    $memberBadges = is_array($badgesByUserId[$id] ?? null) ? $badgesByUserId[$id] : [];
                    $phaseBadge = is_array($phaseBadgesByUserId[$id] ?? null) ? $phaseBadgesByUserId[$id] : null;
                    ?>
                    <?php
                    $playtimeSecs = (int) ($row['arma_playtime_seconds'] ?? 0);
                    $pilotSecs = (int) ($row['pilot_seconds'] ?? 0);
                    $pilotFamilies = is_array($row['pilot_families'] ?? null) ? $row['pilot_families'] : [];
                    $pilotTitle = implode(' · ', array_map(
                        static fn (array $f): string => (string) ($f['label'] ?? '') . ' ' . $hours((int) ($f['seconds'] ?? 0)),
                        $pilotFamilies
                    ));
                    ?>
                    <tr<?= $status !== 'active' ? ' class="is-muted-row"' : '' ?>>
                        <?php if ($canBulkAny): ?>
                        <td class="eff-das__check"><input type="checkbox" class="eff-bulk-check" name="user_ids[]" value="<?= $id ?>" form="eff-bulk-form" aria-label="Sélectionner <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"></td>
                        <?php endif; ?>
                        <td class="eff-das__sticky">
                            <div class="eff-sheets__identity">
                                <span class="eff-sheets__avatar" aria-hidden="true">
                                    <?php if ($avatarUrl !== ''): ?>
                                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async" data-img-fallback="portrait" data-img-initials="<?= htmlspecialchars($rowInitials, ENT_QUOTES, 'UTF-8') ?>" data-img-label="Portrait opérateur indisponible">
                                    <?php else: ?>
                                        <?= htmlspecialchars($rowInitials, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                                <div class="eff-sheets__id-text">
                                    <a class="eff-sheets__name" href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
                                    <span class="eff-sheets__grade-line">
                                        <?php if ($callsign !== '' && strcasecmp($callsign, $name) !== 0): ?>
                                            <span class="eff-das__callsign" title="Indicatif"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php $rowLang = $rosterLanguages[$id] ?? null; ?>
                                        <?php if (is_array($rowLang)): ?>
                                            <?php
                                            $rowLangCodes = array_merge([(string) $rowLang['primary']], $rowLang['others']);
                                            $rowLangTitle = 'Joue en ' . mb_strtolower(\App\Support\CommunityLanguages::label((string) $rowLang['primary']))
                                                . ($rowLang['others'] !== [] ? ', comprend ' . implode(', ', array_map(static fn (string $c): string => mb_strtolower(\App\Support\CommunityLanguages::label($c)), $rowLang['others'])) : '')
                                                . ($rowLang['source'] !== 'member' ? ' (à confirmer)' : '');
                                            ?>
                                            <span class="eff-sheets__badge eff-sheets__badge--lang<?= $rowLang['source'] !== 'member' ? ' eff-sheets__badge--muted' : '' ?>" title="<?= htmlspecialchars($rowLangTitle, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(\App\Support\CommunityLanguages::flag((string) $rowLang['primary']) . ' ' . implode('·', array_map('strtoupper', $rowLangCodes)), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if (is_array($phaseBadge) && ($phaseBadge['label'] ?? '') !== ''): ?>
                                            <?php
                                            $phaseKind = (string) ($phaseBadge['kind'] ?? 'progress');
                                            $phaseClass = match ($phaseKind) {
                                                'gate' => 'eff-sheets__badge--watch',
                                                'ready' => 'eff-sheets__badge--ok',
                                                default => 'eff-sheets__badge--muted',
                                            };
                                            ?>
                                            <span class="eff-sheets__badge <?= $phaseClass ?>"><?= htmlspecialchars((string) $phaseBadge['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if ($status !== 'active'): ?>
                                            <span class="eff-sheets__badge <?= htmlspecialchars($statusClass($status), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($character !== ''): ?><span class="eff-sheets__meta">Personnage · <?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                    <?php
                                    $idMeta = array_values(array_filter([
                                        $matricule !== '' ? 'Mle ' . $matricule : '',
                                        $orgNumber !== '' && strcasecmp($orgNumber, $matricule) !== 0 ? 'N° communauté ' . $orgNumber : '',
                                        $radioAssigned !== '' ? 'Radio ' . $radioAssigned : '',
                                        $memberBadges !== [] ? count($memberBadges) . ' distinction' . (count($memberBadges) > 1 ? 's' : '') : '',
                                    ]));
                                    ?>
                                    <?php if ($idMeta !== []): ?><span class="eff-sheets__meta"><?= htmlspecialchars(implode(' · ', $idMeta), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php if ($rosterView === 'medical'): ?>
                            <?php
                            $blood = trim((string) ($row['blood_type'] ?? ''));
                            $weightKg = (int) ($row['weight_kg'] ?? 0);
                            $heightCm = (int) ($row['height_cm'] ?? 0);
                            $bmi = $weightKg > 0 && $heightCm > 0 ? $weightKg / (($heightCm / 100) ** 2) : null;
                            $intolerances = trim((string) ($row['drug_intolerances'] ?? ''));
                            $allergies = trim((string) ($row['allergies'] ?? ''));
                            ?>
                            <td data-label="Fonction"><?= $fonction !== '' ? '<span class="eff-sheets__fonction">' . htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="eff-das__muted">—</span>' ?></td>
                            <td data-label="Groupe sanguin">
                                <?php if ($blood !== ''): ?>
                                    <span class="eff-das__blood"><?= htmlspecialchars($blood, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <a class="eff-das__missing" href="<?= htmlspecialchars($ficheUrl . '#eff-dlg-civil', ENT_QUOTES, 'UTF-8') ?>">À renseigner</a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Poids" class="eff-das__num"><?= $weightKg > 0 ? $weightKg . ' kg' : '<span class="eff-das__muted">—</span>' ?></td>
                            <td data-label="Taille" class="eff-das__num"><?= $heightCm > 0 ? $heightCm . ' cm' : '<span class="eff-das__muted">—</span>' ?></td>
                            <td data-label="IMC" class="eff-das__num"><?= $bmi !== null ? htmlspecialchars(number_format($bmi, 1, ',', ' '), ENT_QUOTES, 'UTF-8') : '<span class="eff-das__muted">—</span>' ?></td>
                            <td data-label="Intolérances"><?= $intolerances !== '' ? '<span class="eff-das__alert">' . htmlspecialchars($intolerances, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="eff-das__muted">Aucune connue</span>' ?></td>
                            <td data-label="Allergies"><?= $allergies !== '' ? '<span class="eff-das__alert">' . htmlspecialchars($allergies, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="eff-das__muted">Aucune connue</span>' ?></td>
                        <?php elseif ($rosterView === 'aviation'): ?>
                            <?php
                            $famSeconds = [];
                            foreach ($pilotFamilies as $fam) {
                                $famSeconds[(string) ($fam['key'] ?? '')] = (int) ($fam['seconds'] ?? 0);
                            }
                            $lastFlown = trim((string) ($row['pilot_last_flown_at'] ?? ''));
                            $lastFlownTs = $lastFlown !== '' ? strtotime($lastFlown) : false;
                            ?>
                            <td data-label="Fonction"><?= $fonction !== '' ? '<span class="eff-sheets__fonction">' . htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="eff-das__muted">—</span>' ?></td>
                            <td data-label="Affectation"><?= $assignmentPath !== '' ? '<span class="eff-das__unit" title="' . htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($assignmentLeaf, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="eff-das__muted">Non affecté</span>' ?></td>
                            <td data-label="Heures de vol" class="eff-das__num"><?= $pilotSecs > 0 ? '<strong class="eff-das__hours">' . htmlspecialchars($hours($pilotSecs), ENT_QUOTES, 'UTF-8') . '</strong>' : '<span class="eff-das__muted">—</span>' ?></td>
                            <?php foreach (array_keys($aviationFamilies) as $famKey): ?>
                                <?php $fs = (int) ($famSeconds[$famKey] ?? 0); ?>
                                <td class="eff-das__num" data-label="<?= htmlspecialchars((string) $aviationFamilies[$famKey]['label'], ENT_QUOTES, 'UTF-8') ?>"><?= $fs > 0 ? htmlspecialchars($hours($fs), ENT_QUOTES, 'UTF-8') : '<span class="eff-das__muted">—</span>' ?></td>
                            <?php endforeach; ?>
                            <td data-label="Dernier vol"><?= $lastFlownTs !== false ? htmlspecialchars(date('d/m/Y', $lastFlownTs), ENT_QUOTES, 'UTF-8') : '<span class="eff-das__muted">—</span>' ?></td>
                        <?php else: ?>
                        <td data-label="Grade">
                            <?php if ($grade !== ''): ?>
                                <span class="eff-das__strong"><?= htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-das__muted">Sans grade</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Affectation">
                            <div class="eff-sheets__assign">
                                <?php if ($assignmentPath !== ''): ?>
                                    <span class="eff-das__unit-wrap" title="<?= htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="eff-das__unit"><?= htmlspecialchars($assignmentLeaf, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($assignmentParent !== ''): ?>
                                            <span class="eff-das__unit-parent"><?= htmlspecialchars($assignmentParent, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--watch">Sans unité</span>
                                <?php endif; ?>
                                <?php if ($canManageAssignments): ?>
                                    <details class="eff-sheets__pop">
                                        <summary class="eff-das__edit" title="<?= $assignmentPath !== '' ? 'Modifier l’affectation' : 'Affecter à une unité' ?>"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg><span class="sr-only"><?= $assignmentPath !== '' ? 'Modifier l’affectation' : 'Affecter' ?></span></summary>
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
                                                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Enregistrer</button>
                                                <a class="eff-sheets__pop-link" href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir le dossier</a>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Fonction">
                            <?php if ($fonction !== ''): ?>
                                <span class="eff-sheets__fonction"><?= htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <a class="eff-sheets__badge eff-sheets__badge--watch" href="<?= htmlspecialchars($ficheUrl . '#eff-dlg-jobs', ENT_QUOTES, 'UTF-8') ?>">Fonction manquante</a>
                            <?php endif; ?>
                            <?php
                            $qualSummary = trim((string) ($row['qualifications_summary'] ?? '—'));
                            $qualAlert = trim((string) ($row['qualifications_alert'] ?? ''));
                            ?>
                            <?php if ($qualSummary !== '' && $qualSummary !== '—'): ?><span class="eff-sheets__meta">Qualifications · <?= htmlspecialchars($qualSummary, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            <?php if ($qualAlert !== ''): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch" title="<?= htmlspecialchars($qualAlert, ENT_QUOTES, 'UTF-8') ?>">Alerte</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Accès">
                            <div class="eff-sheets__assign">
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
                                <?php if ($canManageRoles && $roles !== []): ?>
                                    <details class="eff-sheets__pop">
                                        <summary class="eff-das__edit" title="<?= $roleParts !== [] ? 'Modifier l’accès' : 'Attribuer l’accès' ?>"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg><span class="sr-only"><?= $roleParts !== [] ? 'Modifier l’accès' : 'Attribuer l’accès' ?></span></summary>
                                        <div class="eff-sheets__pop-panel">
                                            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/roles'), ENT_QUOTES, 'UTF-8') ?>" class="eff-sheets__pop-form">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                <label for="eff-access-<?= $id ?>">Niveau d’accès</label>
                                                <select id="eff-access-<?= $id ?>" name="access_role_id">
                                                    <?php foreach ($roles as $accessRole): ?>
                                                        <?php $rid = (int) ($accessRole['id'] ?? 0); ?>
                                                        <option value="<?= $rid ?>" <?= $currentAccessRoleId === $rid ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars((string) ($accessRole['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Enregistrer l’accès</button>
                                                <a class="eff-sheets__pop-link" href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir le dossier</a>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Ancienneté">
                            <?php
                            $seniorityTitle = 'Ancienneté réelle : ' . $seniorityLabel
                                . ($prePlatformLabel !== '' ? ' (avant le site : ' . $prePlatformLabel . ')' : '');
                            ?>
                            <div class="eff-sheets__assign">
                                <span class="eff-das__seniority" title="<?= htmlspecialchars($seniorityTitle, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($canEditProfiles): ?>
                                    <details class="eff-sheets__pop">
                                        <summary class="eff-das__edit" title="Modifier l’ancienneté"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg><span class="sr-only">Modifier l’ancienneté</span></summary>
                                        <div class="eff-sheets__pop-panel">
                                            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/anciennete'), ENT_QUOTES, 'UTF-8') ?>" class="eff-sheets__pop-form">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                <label for="eff-enlist-<?= $id ?>">Arrivée dans la communauté (sur Athena)</label>
                                                <input id="eff-enlist-<?= $id ?>" type="date" name="enlistment_date" value="<?= htmlspecialchars($enlistmentStart, ENT_QUOTES, 'UTF-8') ?>">
                                                <label for="eff-pre-<?= $id ?>">Arrivée avant le site</label>
                                                <input id="eff-pre-<?= $id ?>" type="date" name="pre_platform_start_date" value="<?= htmlspecialchars($prePlatformStart, ENT_QUOTES, 'UTF-8') ?>">
                                                <p>Laissez vide s’il n’était pas membre avant l’ouverture du site.</p>
                                                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Enregistrer</button>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Temps en mission" class="eff-das__num">
                            <?php if ($playtimeSecs > 0): ?>
                                <span class="eff-das__time" title="Temps de mission transmis par la liaison terrain"><?= htmlspecialchars($hours($playtimeSecs), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-das__muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Heures de vol" class="eff-das__num">
                            <?php if ($pilotSecs > 0): ?>
                                <a class="eff-das__hours" href="<?= htmlspecialchars($queryUrlView('aviation'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($pilotTitle, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($hours($pilotSecs), ENT_QUOTES, 'UTF-8') ?></a>
                                <?php if ($pilotFamilies !== []): ?>
                                    <span class="eff-das__sub"><?= htmlspecialchars((string) ($pilotFamilies[0]['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= count($pilotFamilies) > 1 ? ' +' . (count($pilotFamilies) - 1) : '' ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="eff-das__muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Disponibilité">
                            <?php
                            $availHue = max(0, min(120, (int) round($availabilityScore * 1.2)));
                            ?>
                            <span class="eff-das__avail" style="--avail-hue:<?= $availHue ?>;--avail-pct:<?= max(0, min(100, $availabilityScore)) ?>%" title="Disponibilité <?= $availabilityScore ?> % · Présence <?= $presenceScore ?> %">
                                <span class="eff-das__avail-track" aria-hidden="true"><span class="eff-das__avail-fill"></span></span>
                                <span class="eff-das__avail-pct"><?= $availabilityScore ?> %</span>
                            </span>
                            <span class="eff-das__sub<?= $presenceScore < 40 ? ' is-low' : '' ?>">Présence <?= $presenceScore ?> %</span>
                        </td>
                        <td data-label="Dossier">
                            <div class="eff-sheets__meter<?= $completionScore < 40 ? ' is-low' : '' ?>">
                                <div class="eff-sheets__meter-head">
                                    <span>Complété</span>
                                    <strong><?= $completionScore ?>%</strong>
                                </div>
                                <div class="eff-sheets__meter-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $completionScore ?>" aria-label="Complétion du dossier">
                                    <span style="width:<?= max(0, min(100, $completionScore)) ?>%"></span>
                                </div>
                            </div>
                        </td>
                        <?php endif; ?>
                        <td class="eff-das__actions-col">
                            <div class="eff-sheets__actions">
                                <a class="is-primary" href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir</a>
                                <?php if ($canRequestElevation): ?>
                                    <?php $cooldownSec = (int) ($elevationCooldownByUserId[$id] ?? 0); ?>
                                    <?php if ($cooldownSec > 0): ?>
                                        <span class="eff-sheets__chip" style="opacity:.55;cursor:default" title="Une demande a déjà été envoyée récemment pour ce membre. Patientez <?= htmlspecialchars($cooldownLabel($cooldownSec), ENT_QUOTES, 'UTF-8') ?> avant d’en renvoyer une.">Élévation envoyée</span>
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
<script defer src="<?= htmlspecialchars(asset_url('assets/js/eff-workspace.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
