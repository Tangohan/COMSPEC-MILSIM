<?php
declare(strict_types=1);

$rows = is_array($rosterRows ?? null) ? $rosterRows : [];
$rosterIdentities = is_array($rosterIdentities ?? null) ? $rosterIdentities : [];
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
// Libellés composés (title, info-bulles) : écrits dans la langue de la page.
$rosterEn = locale() === 'en';
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
$jobTagOptions = is_array($rosterJobTags ?? null) ? $rosterJobTags : [];
$jobTagFilter = (int) ($filters['etiquette_fonction'] ?? 0);
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

$rosterGroup = (string) ($rosterGroup ?? 'unite');
$rosterUnitFilter = (int) ($rosterUnitFilter ?? 0);
$rosterGradeCategory = (string) ($rosterGradeCategory ?? '');
$filterQuery = static function (array $overrides = []) use ($filters, $page, $rosterGroup, $rosterUnitFilter, $rosterGradeCategory): array {
    $q = [
        'q' => $filters['q'] ?? null,
        'status' => !empty($filters['status']) ? $filters['status'] : null,
        'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
        'sans_affectation' => !empty($filters['sans_affectation']) ? '1' : null,
        'sans_role' => !empty($filters['sans_role']) ? '1' : null,
        'tri' => ($rosterGroup ?? 'unite') === 'liste' && !empty($filters['tri']) && ($filters['tri'] ?? 'nom') !== 'nom' ? $filters['tri'] : null,
        'groupe' => ($rosterGroup ?? 'unite') !== 'unite' ? ($rosterGroup ?? null) : null,
        'unite' => (int) ($rosterUnitFilter ?? 0) > 0 ? (int) $rosterUnitFilter : null,
        'categorie' => (string) ($rosterGradeCategory ?? '') !== '' ? (string) $rosterGradeCategory : null,
        'langue' => !empty($filters['langue']) ? $filters['langue'] : null,
        'fonction' => !empty($filters['fonction']) ? $filters['fonction'] : null,
        'etiquette_fonction' => !empty($filters['etiquette_fonction']) ? (int) $filters['etiquette_fonction'] : null,
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
// Fenêtres d'édition du tableur (emploi, rôle dans l'unité, niveau d'accès) : une seule par page, remplie au clic.
$rosterJobsByUserId = is_array($rosterJobsByUserId ?? null) ? $rosterJobsByUserId : [];
$jobPickerGroups = is_array($rosterJobPickerGroups ?? null) ? $rosterJobPickerGroups : [];
$jobRoleMax = max(1, (int) ($rosterJobRoleMax ?? 5));
$editIcon = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
$memberActionTemplate = effectifs_workspace_url('membres/__ID__');
$assignmentRoleSuggestions = [];
foreach ($rows as $suggestRow) {
    $suggest = trim((string) ($suggestRow['assignment_role_name'] ?? ''));
    if ($suggest !== '') {
        $assignmentRoleSuggestions[mb_strtolower($suggest, 'UTF-8')] = $suggest;
    }
}

$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || !empty($filters['role_id'])
    || !empty($filters['sans_affectation'])
    || !empty($filters['sans_role'])
    || !empty($filters['langue'])
    || !empty($filters['fonction'])
    || !empty($filters['etiquette_fonction'])
    || (($rosterGroup ?? 'unite') === 'liste' && ($filters['tri'] ?? 'nom') !== 'nom');

$exportQuery = array_filter([
    'q' => $filters['q'] ?? null,
    'status' => !empty($filters['status']) ? $filters['status'] : null,
    'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
    'sans_affectation' => !empty($filters['sans_affectation']) ? '1' : null,
    'sans_role' => !empty($filters['sans_role']) ? '1' : null,
], static fn ($v) => $v !== null && $v !== '' && $v !== 0);
$exportUrl = effectifs_workspace_url('export') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');
// Export PDF : la vue (générale, médicale, heures de vol), les filtres et le tri affichés, sans pagination.
$pdfQuery = $filterQuery(['page' => null]);
$pdfExportUrl = effectifs_workspace_url('export.pdf') . ($pdfQuery ? '?' . http_build_query($pdfQuery) : '');
$canExportPdf = (bool) ($canExportPdf ?? false);
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
<div class="eff-catalog eff-catalog--roster eff-mq">
    <?php if (!empty($dupScan['enabled']) && $dupGroups !== []): ?>
    <aside class="eff-banner eff-banner--warn" role="status">
        <div class="eff-banner__body">
            <p class="eff-banner__title">Fiches jumelles à relire</p>
            <?php if ($rosterEn): ?>
            <?php
            $dupFieldsEn = array_map(static fn (string $label): string => 'the same ' . mb_strtolower(\App\Support\HtmlTranslator::translateText($label)), $dupFieldLabels);
            $dupPhraseEn = match (count($dupFieldsEn)) {
                0 => 'the same identifiers',
                1 => $dupFieldsEn[0],
                default => implode(', ', array_slice($dupFieldsEn, 0, -1)) . ' or ' . $dupFieldsEn[array_key_last($dupFieldsEn)],
            };
            ?>
            <p class="eff-banner__text"><?= htmlspecialchars(
                $dupGroupCount . ' group' . ($dupGroupCount > 1 ? 's' : '') . ' · ' . $dupMemberCount . ' member' . ($dupMemberCount > 1 ? 's' : '')
                . ' share ' . $dupPhraseEn . '. Check that these are not two files for the same person.',
                ENT_QUOTES,
                'UTF-8'
            ) ?></p>
            <?php else: ?>
            <p class="eff-banner__text">
                <?= $dupGroupCount ?> groupe<?= $dupGroupCount > 1 ? 's' : '' ?> ·
                <?= $dupMemberCount ?> membre<?= $dupMemberCount > 1 ? 's' : '' ?>
                partagent <?= htmlspecialchars($dupFieldPhrase, ENT_QUOTES, 'UTF-8') ?>.
                Vérifiez qu’il ne s’agit pas de deux dossiers pour la même personne.
            </p>
            <?php endif; ?>
        </div>
        <a href="<?= htmlspecialchars(effectifs_workspace_url('doublons'), ENT_QUOTES, 'UTF-8') ?>" class="eff-banner__action">Ouvrir les fiches</a>
    </aside>
    <?php endif; ?>


<?php require __DIR__ . '/partials/roster_toolbar.php'; ?>

    <?php if ($rows === [] && !$hasActiveFilters && $total === 0): ?>
        <div class="eff-catalog__empty">
            <strong>Votre registre est vide</strong>
            Commencez par ajouter une première fiche ou importer votre tableur : les membres n’ont pas besoin de compte.
            <div class="eff-catalog__empty-actions">
                <a class="eff-catalog__btn eff-catalog__btn--primary" href="<?= htmlspecialchars(effectifs_workspace_url('nouveau'), ENT_QUOTES, 'UTF-8') ?>">Ajouter une fiche</a>
                <a class="eff-catalog__btn" href="<?= htmlspecialchars(effectifs_workspace_url('sans-comptes'), ENT_QUOTES, 'UTF-8') ?>">Voir les fiches sans compte</a>
                <a class="eff-catalog__btn" href="<?= htmlspecialchars(effectifs_workspace_url('tableaux'), ENT_QUOTES, 'UTF-8') ?>">Construire un tableau de données</a>
            </div>
        </div>
    <?php elseif ($rows === []): ?>
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
                        <th class="eff-das__sticky eff-mq-col-member">Membre</th>
                        <?php if ($rosterView === 'medical'): ?>
                            <th>Emploi</th>
                            <th class="eff-das__num">Qualifs</th>
                            <th>Groupe sanguin</th>
                            <th class="eff-das__num">Poids</th>
                            <th class="eff-das__num">Taille</th>
                            <th class="eff-das__num" title="Indice de masse corporelle, calculé avec le poids et la taille">IMC</th>
                            <th>Intolérances médicamenteuses</th>
                            <th>Allergies</th>
                        <?php elseif ($rosterView === 'aviation'): ?>
                            <th>Emploi</th>
                            <th class="eff-das__num">Qualifs</th>
                            <th>Affectation</th>
                            <th class="eff-das__num">Heures de vol</th>
                            <?php foreach ($aviationFamilies as $fam): ?>
                                <th class="eff-das__num"><?= htmlspecialchars((string) $fam['label'], ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                            <th>Dernier vol</th>
                        <?php else: ?>
                            <th>Grade</th>
                            <th>Affectation</th>
                            <th title="Ce que fait la personne : radio, médecin, pilote…">Emploi</th>
                            <th title="Le poste tenu dans son unité : chef de groupe, tireur, adjoint…">Rôle</th>
                            <th>Statut</th>
                            <th title="Présence aux activités ; la disponibilité est indiquée dessous">Présence</th>
                            <th title="Dernière connexion au site">Dernière connexion</th>
                            <th class="eff-das__num">Qualifs</th>
                            <th title="Ce que la personne a le droit de faire sur le site">Niveau d’accès</th>
                            <th>Ancienneté</th>
                            <th class="eff-das__num">Temps en mission</th>
                            <th class="eff-das__num" title="Heures aux commandes d'un aéronef, tous appareils confondus">Heures de vol</th>
                            <th class="eff-das__col-dossier" title="Complétion du dossier ; l’icône ouvre le dossier">Dossier</th>
                            <th>À traiter</th>
                        <?php endif; ?>
                        <th class="eff-das__actions-col">Fiche</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Regroupement de la maquette : en-tête de groupe à chaque changement d'unité (ou de grade).
                $groupMode = in_array((string) ($rosterGroup ?? 'unite'), ['unite', 'grade'], true) ? (string) $rosterGroup : '';
                $unitCodesMap = is_array($rosterUnitCodes ?? null) ? $rosterUnitCodes : [];
                $unitCommanders = is_array($rosterUnitCommanders ?? null) ? $rosterUnitCommanders : [];
                $gradeCats = is_array($rosterGradeCategories ?? null) ? $rosterGradeCategories : [];
                $avatarPalette = ['#0f172a', '#065f46', '#1d4ed8', '#b45309', '#6d28d9', '#0369a1', '#9f1239', '#3f6212'];
                $groupKeyOf = static function (array $r) use ($groupMode): string {
                    if ($groupMode === 'grade') {
                        $g = trim((string) ($r['grade_long'] ?? $r['grade_short'] ?? ''));

                        return $g !== '' ? 'g:' . $g : 'g:';
                    }

                    return 'u:' . (int) ($r['unit_id'] ?? 0);
                };
                $groupStats = [];
                if ($groupMode !== '') {
                    foreach ($rows as $gr) {
                        $gk = $groupKeyOf($gr);
                        $groupStats[$gk] ??= ['n' => 0, 'presence' => 0];
                        $groupStats[$gk]['n']++;
                        $groupStats[$gk]['presence'] += (int) ($gr['presence_score'] ?? 0);
                    }
                }
                $colCount = ($canBulkAny ? 1 : 0) + 2 + match ($rosterView) {
                    'medical' => 8,
                    'aviation' => 5 + count($aviationFamilies),
                    default => 14,
                };
                $currentGroup = null;
                ?>
                <?php foreach ($rows as $row):
                    $id = (int) ($row['id'] ?? 0);
                    $display = trim((string) ($row['display_name'] ?? ''));
                    $callsign = trim((string) ($row['callsign'] ?? ''));
                    $email = (string) ($row['email'] ?? '');
                    // Prénom et nom d’abord (« Prénom N. » par défaut), indicatif en second ; repli sur le pseudo puis l’e-mail.
                    $nameRow = \App\Support\MemberName::withIdentity(array_diff_key($row, ['first_name' => 1, 'last_name' => 1]), $rosterIdentities[$id] ?? null);
                    $name = member_name($nameRow, 'list', null, false);
                    if ($name === \App\Support\MemberName::FALLBACK) {
                        $name = $display !== '' ? $display : ($callsign !== '' ? $callsign : $email);
                    }
                    $rowFullName = \App\Support\MemberName::full($nameRow);
                    $rowTrigram = member_trigram($nameRow);
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
                    $rowUnitCode = is_array($rosterUnitCodes ?? null) ? ($rosterUnitCodes[(int) ($row['unit_id'] ?? 0)] ?? null) : null;
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
                    $athenaId = trim((string) ($row['athena_identifier'] ?? ''));
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
                    $radioAssigned = trim((string) ($row['radio_assigned'] ?? ''));
                    $memberBadges = is_array($badgesByUserId[$id] ?? null) ? $badgesByUserId[$id] : [];
                    $phaseBadge = is_array($phaseBadgesByUserId[$id] ?? null) ? $phaseBadgesByUserId[$id] : null;
                    $qualCount = (int) ($row['qualifications_count'] ?? 0);
                    $qualItems = is_array($row['qualifications_items'] ?? null) ? $row['qualifications_items'] : [];
                    $qualAlert = trim((string) ($row['qualifications_alert'] ?? ''));
                    $qualJson = htmlspecialchars(
                        (string) json_encode($qualItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
                        ENT_QUOTES,
                        'UTF-8'
                    );
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
                    <?php
                    $rowGroupKey = $groupMode !== '' ? $groupKeyOf($row) : '';
                    $rowGroupId = $rowGroupKey !== '' ? 'g' . substr(md5($rowGroupKey), 0, 10) : '';
                    $unitColor = $unitId > 0 ? $avatarPalette[$unitId % count($avatarPalette)] : '#64748b';
                    ?>
                    <?php if ($groupMode !== '' && $rowGroupKey !== $currentGroup): ?>
                        <?php
                        $currentGroup = $rowGroupKey;
                        $gStat = $groupStats[$rowGroupKey] ?? ['n' => 0, 'presence' => 0];
                        $gAvg = $gStat['n'] > 0 ? (int) round($gStat['presence'] / $gStat['n']) : 0;
                        $gAvgClass = $gAvg >= 80 ? 'is-good' : ($gAvg >= 50 ? 'is-mid' : 'is-low');
                        if ($groupMode === 'grade') {
                            $gTitle = substr($rowGroupKey, 2) !== '' ? substr($rowGroupKey, 2) : 'Sans grade';
                            $gCode = null;
                            $gPath = '';
                            $gChief = '';
                        } else {
                            $gTitle = $unitId > 0 ? ($unit !== '' ? $unit : $assignmentLeaf) : 'Sans unité';
                            $gCode = $unitId > 0 ? ($unitCodesMap[$unitId] ?? null) : null;
                            $gPath = $unitId > 0 ? $assignmentPath : '';
                            $gChief = $unitId > 0 ? trim((string) ($unitCommanders[$unitId] ?? '')) : '';
                        }
                        ?>
                    <tr class="eff-mq-gh" data-eff-group="<?= $rowGroupId ?>">
                        <td colspan="<?= $colCount ?>">
                            <button type="button" class="eff-mq-gh__toggle" aria-expanded="true" data-eff-group-toggle="<?= $rowGroupId ?>">
                                <svg class="eff-mq-gh__car" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                                <span class="eff-mq-gh__sq" style="background:<?= htmlspecialchars($groupMode === 'unite' ? $unitColor : '#475569', ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                                <b><?= htmlspecialchars($gTitle, ENT_QUOTES, 'UTF-8') ?></b>
                            </button>
                            <?php if (is_array($gCode)): ?><?= eff_unit_code_html((string) $gCode['code'], (string) $gCode['admin_id'], 'sm') ?><?php endif; ?>
                            <span class="eff-mq-gh__meta">
                                <span><?= (int) $gStat['n'] ?> membre<?= $gStat['n'] > 1 ? 's' : '' ?><?= $totalPages > 1 ? ' sur cette page' : '' ?></span>
                                <?php if ($gChief !== ''): ?><span>Chef : <b><?= htmlspecialchars($gChief, ENT_QUOTES, 'UTF-8') ?></b></span><?php endif; ?>
                                <span>Présence moy. <b class="<?= $gAvgClass ?>"><?= $gAvg ?> %</b></span>
                                <?php if ($gPath !== '' && str_contains($gPath, '/')): ?><span class="eff-mq-gh__path"><?= htmlspecialchars(str_replace(' / ', ' › ', $gPath), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr data-ath-quiet-scope="eff-row-<?= $id ?>" class="eff-mq-row<?= $status !== 'active' ? ' is-muted-row' : '' ?>"<?= $rowGroupId !== '' ? ' data-eff-group-row="' . $rowGroupId . '"' : '' ?>>
                        <?php if ($canBulkAny): ?>
                        <td class="eff-das__check"><input type="checkbox" class="eff-bulk-check" name="user_ids[]" value="<?= $id ?>" form="eff-bulk-form" aria-label="Sélectionner <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"></td>
                        <?php endif; ?>
                        <td class="eff-das__sticky eff-mq-col-member" data-label="Membre">
                            <div class="eff-mq-mb">
                                <span class="eff-sheets__avatar eff-mq-av" style="--av:<?= htmlspecialchars($unitColor, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                    <?php if ($avatarUrl !== ''): ?>
                                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async" data-img-fallback="portrait" data-img-initials="<?= htmlspecialchars($rowInitials, ENT_QUOTES, 'UTF-8') ?>" data-img-label="Portrait opérateur indisponible">
                                    <?php else: ?>
                                        <?= htmlspecialchars($rowInitials, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                                <span class="eff-mq-mb__txt">
                                    <a class="eff-sheets__name eff-mq-mb__name" href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>"<?= $rowFullName !== $name ? ' title="' . htmlspecialchars($rowFullName, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
                                    <small class="eff-mq-mb__line">
                                        <?php if ($rowTrigram !== ''): ?>
                                            <span class="eff-mq-cs" title="Trigramme"><?= htmlspecialchars($rowTrigram, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if ($callsign !== ''): ?>
                                            <span class="eff-mq-cs" title="Indicatif<?= $extraCallsigns !== [] ? ' · aussi ' . htmlspecialchars(implode(', ', $extraCallsigns), ENT_QUOTES, 'UTF-8') : '' ?>"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?><?= $extraCallsigns !== [] ? ' +' . count($extraCallsigns) : '' ?></span>
                                        <?php endif; ?>
                                        <?php $rowLang = $rosterLanguages[$id] ?? null; ?>
                                        <?php if (is_array($rowLang)): ?>
                                            <?php
                                            $rowLangCodes = array_merge([(string) $rowLang['primary']], $rowLang['others']);
                                            // Phrase composée : écrite dans la langue de la page (la traduction automatique ne la reconnaît pas).
                                            $rowLangName = static fn (string $c): string => $rosterEn
                                                ? \App\Support\CommunityLanguages::pageLabel($c)
                                                : mb_strtolower(\App\Support\CommunityLanguages::label($c));
                                            $rowLangTitle = ($rosterEn ? 'Plays in ' : 'Joue en ') . $rowLangName((string) $rowLang['primary'])
                                                . ($rowLang['others'] !== [] ? ($rosterEn ? ', understands ' : ', comprend ') . implode(', ', array_map($rowLangName, $rowLang['others'])) : '')
                                                . ($rowLang['source'] !== 'member' ? ($rosterEn ? ' (to be confirmed)' : ' (à confirmer)') : '');
                                            ?>
                                            <span class="eff-mq-lang<?= $rowLang['source'] !== 'member' ? ' is-guess' : '' ?>" title="<?= htmlspecialchars($rowLangTitle, ENT_QUOTES, 'UTF-8') ?>"><?= \App\Support\CommunityLanguages::flagsHtml($rowLangCodes, ['class' => 'eff-sheets__lang-flag', 'size' => '16x12']) ?><span class="eff-sheets__lang-codes"><?= htmlspecialchars(implode('·', array_map('strtoupper', $rowLangCodes)), ENT_QUOTES, 'UTF-8') ?></span></span>
                                        <?php endif; ?>
                                        <?php if ($orgNumber !== '' || $athenaId !== ''): ?>
                                            <span class="eff-mq-mle" title="<?= htmlspecialchars(trim(($orgNumber !== '' ? 'Matricule communauté ' . $orgNumber : '') . ($athenaId !== '' ? ' · Matricule Athena ' . $athenaId : ''), ' ·'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orgNumber !== '' ? $orgNumber : $athenaId, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if ($status !== 'active' && $rosterView !== 'general'): ?>
                                            <span class="eff-sheets__badge <?= htmlspecialchars($statusClass($status), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </small>
                                    <?php
                                    $idMeta = array_values(array_filter([
                                        $character !== '' ? 'Personnage · ' . $character : '',
                                        $radioAssigned !== '' ? 'Radio ' . $radioAssigned : '',
                                        $memberBadges !== [] ? count($memberBadges) . ($rosterEn ? ' award' : ' distinction') . (count($memberBadges) > 1 ? 's' : '') : '',
                                        $orgNumber !== '' && $athenaId !== '' ? 'Athena ' . $athenaId : '',
                                    ]));
                                    ?>
                                    <?php if ($idMeta !== [] || (is_array($phaseBadge) && ($phaseBadge['label'] ?? '') !== '')): ?>
                                    <small class="eff-mq-mb__extra">
                                        <?php if (is_array($phaseBadge) && ($phaseBadge['label'] ?? '') !== ''): ?>
                                            <?php
                                            $phaseClass = match ((string) ($phaseBadge['kind'] ?? 'progress')) {
                                                'gate' => 'eff-sheets__badge--watch',
                                                'ready' => 'eff-sheets__badge--ok',
                                                default => 'eff-sheets__badge--muted',
                                            };
                                            ?>
                                            <span class="eff-sheets__badge <?= $phaseClass ?>"><?= htmlspecialchars((string) $phaseBadge['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars(implode(' · ', $idMeta), ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                    <?php endif; ?>
                                </span>
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
                            <td data-label="Emploi"><?= $fonction !== '' ? '<span class="eff-sheets__fonction">' . htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="eff-das__muted">—</span>' ?></td>
                            <td class="eff-das__num" data-label="Qualifs">
                                <?php if ($qualCount > 0): ?>
                                    <button type="button" class="eff-sheets__qual-count<?= $qualAlert !== '' ? ' is-alert' : '' ?>" data-eff-qual-open data-eff-qual-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" data-eff-qual-fiche="<?= htmlspecialchars($ficheUrl . '#qualifications', ENT_QUOTES, 'UTF-8') ?>" data-eff-qual-items="<?= $qualJson ?>" title="<?= $qualAlert !== '' ? htmlspecialchars($qualAlert, ENT_QUOTES, 'UTF-8') : 'Voir les qualifications' ?>"><?= $qualCount ?></button>
                                <?php else: ?>
                                    <span class="eff-das__muted">0</span>
                                <?php endif; ?>
                            </td>
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
                            <td data-label="Emploi"><?= $fonction !== '' ? '<span class="eff-sheets__fonction">' . htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="eff-das__muted">—</span>' ?></td>
                            <td class="eff-das__num" data-label="Qualifs">
                                <?php if ($qualCount > 0): ?>
                                    <button type="button" class="eff-sheets__qual-count<?= $qualAlert !== '' ? ' is-alert' : '' ?>" data-eff-qual-open data-eff-qual-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" data-eff-qual-fiche="<?= htmlspecialchars($ficheUrl . '#qualifications', ENT_QUOTES, 'UTF-8') ?>" data-eff-qual-items="<?= $qualJson ?>" title="<?= $qualAlert !== '' ? htmlspecialchars($qualAlert, ENT_QUOTES, 'UTF-8') : 'Voir les qualifications' ?>"><?= $qualCount ?></button>
                                <?php else: ?>
                                    <span class="eff-das__muted">0</span>
                                <?php endif; ?>
                            </td>
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
                                <?php
                                $gradeShort = trim((string) ($row['grade_short'] ?? ''));
                                $gradeCat = $gradeCats[(int) ($row['grade_id'] ?? 0)] ?? '';
                                ?>
                                <span class="eff-mq-grade">
                                    <?php if ($gradeShort !== ''): ?><span class="eff-mq-grade__ab<?= $gradeCat !== '' ? ' is-' . $gradeCat : '' ?>"><?= htmlspecialchars($gradeShort, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                    <span class="eff-mq-grade__long"><?= htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            <?php else: ?>
                                <span class="eff-das__muted">Sans grade</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Affectation">
                            <div class="eff-sheets__assign">
                                <?php if ($assignmentPath !== ''): ?>
                                    <span class="eff-das__unit-wrap" title="<?= htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="eff-das__unit"><?= htmlspecialchars($assignmentLeaf, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (is_array($rowUnitCode)): ?>
                                            <span class="eff-ucode eff-ucode--sm" title="Code unité · identifiant <?= htmlspecialchars((string) $rowUnitCode['admin_id'], ENT_QUOTES, 'UTF-8') ?>" translate="no"><?= htmlspecialchars((string) $rowUnitCode['code'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
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
                                            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/affectation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-sheets__pop-form" data-ath-quiet>
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
                        <?php
                        $rowJobs = is_array($rosterJobsByUserId[$id] ?? null) ? $rosterJobsByUserId[$id] : [];
                        $rowJobPrimary = 0;
                        foreach ($rowJobs as $rj) {
                            if (!empty($rj['primary'])) {
                                $rowJobPrimary = (int) $rj['id'];
                                break;
                            }
                        }
                        $rowJobLabel = $rowJobs !== [] ? (string) ($rowJobs[0]['label'] ?? '') : $fonction;
                        $rowJobTitle = implode(' · ', array_map(static fn (array $rj): string => (string) ($rj['label'] ?? ''), $rowJobs));
                        $assignmentRole = trim((string) ($row['assignment_role_name'] ?? ''));
                        $memberData = 'data-eff-member-id="' . $id . '" data-eff-member-name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"';
                        ?>
                        <td data-label="Emploi">
                            <div class="eff-sheets__assign">
                                <?php if ($rowJobLabel !== ''): ?>
                                    <?php $rowJobDecor = is_array($rowJobs[0]['decoration'] ?? null) ? $rowJobs[0]['decoration'] : null; $rowJobDesc = trim((string) ($rowJobDecor['description'] ?? '')); ?>
                                    <span class="eff-sheets__fonction" title="<?= htmlspecialchars(($rowJobTitle !== '' ? $rowJobTitle : $rowJobLabel) . ($rowJobDesc !== '' ? ' — ' . $rowJobDesc : ''), ENT_QUOTES, 'UTF-8') ?>"><?= \App\Support\JobRoleBadge::html($rowJobDecor, false) ?><?= htmlspecialchars($rowJobLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (count($rowJobs) > 1): ?><span class="eff-sheets__badge eff-sheets__badge--info">+<?= count($rowJobs) - 1 ?></span><?php endif; ?>
                                <?php else: ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--watch">Sans emploi</span>
                                <?php endif; ?>
                                <?php if ($canManageAssignments && $jobPickerGroups !== []): ?>
                                    <button type="button" class="eff-das__edit" data-eff-edit="emploi" <?= $memberData ?> data-eff-jobs="<?= htmlspecialchars((string) json_encode(array_map(static fn (array $rj): int => (int) $rj['id'], $rowJobs)), ENT_QUOTES, 'UTF-8') ?>" data-eff-job-primary="<?= $rowJobPrimary ?>" title="Modifier l’emploi"><?= $editIcon ?><span class="sr-only">Modifier l’emploi</span></button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Rôle">
                            <div class="eff-sheets__assign">
                                <?php if ($assignmentRole !== ''): ?>
                                    <span class="eff-sheets__fonction"><?= htmlspecialchars($assignmentRole, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <span class="eff-das__muted">—</span>
                                <?php endif; ?>
                                <?php if ($canManageAssignments): ?>
                                    <button type="button" class="eff-das__edit" data-eff-edit="role" <?= $memberData ?> data-eff-role="<?= htmlspecialchars($assignmentRole, ENT_QUOTES, 'UTF-8') ?>" title="Modifier le rôle dans l’unité"><?= $editIcon ?><span class="sr-only">Modifier le rôle</span></button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Statut">
                            <span class="eff-mq-st eff-mq-st--<?= htmlspecialchars($status !== '' ? $status : 'unknown', ENT_QUOTES, 'UTF-8') ?>"><i aria-hidden="true"></i><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <?php $presClass = $presenceScore >= 80 ? 'is-good' : ($presenceScore >= 50 ? 'is-mid' : 'is-low'); ?>
                        <td data-label="Présence">
                            <span class="eff-mq-pr <?= $presClass ?>" title="Présence <?= $presenceScore ?> % · <?= $rosterEn ? 'Availability' : 'Disponibilité' ?> <?= $availabilityScore ?> %">
                                <span class="eff-mq-pr__b" aria-hidden="true"><i style="width:<?= max(0, min(100, $presenceScore)) ?>%"></i></span>
                                <span class="eff-mq-pr__v"><?= $presenceScore ?> %</span>
                            </span>
                            <span class="eff-das__sub">Dispo. <?= $availabilityScore ?> %</span>
                        </td>
                        <?php
                        $lastLogin = trim((string) ($row['last_login_at'] ?? ''));
                        $lastLoginTs = $lastLogin !== '' ? strtotime($lastLogin) : false;
                        $lastDays = $lastLoginTs !== false ? (int) floor((strtotime('today') - strtotime(date('Y-m-d', $lastLoginTs))) / 86400) : null;
                        $lastLabel = match (true) {
                            $lastDays === null => '—',
                            $lastDays <= 0 => 'Aujourd’hui',
                            $lastDays === 1 => 'Hier',
                            default => 'Il y a ' . $lastDays . ' j',
                        };
                        ?>
                        <td data-label="Dernière connexion">
                            <span class="eff-mq-ls<?= $lastDays !== null && $lastDays > 30 ? ' is-old' : '' ?>"<?= $lastLoginTs !== false ? ' title="' . htmlspecialchars(date('d/m/Y H:i', $lastLoginTs), ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($lastLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td class="eff-das__num" data-label="Qualifs">
                            <?php if ($qualCount > 0): ?>
                                <button type="button" class="eff-sheets__qual-count<?= $qualAlert !== '' ? ' is-alert' : '' ?>" data-eff-qual-open data-eff-qual-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" data-eff-qual-fiche="<?= htmlspecialchars($ficheUrl . '#qualifications', ENT_QUOTES, 'UTF-8') ?>" data-eff-qual-items="<?= $qualJson ?>" title="<?= $qualAlert !== '' ? htmlspecialchars($qualAlert, ENT_QUOTES, 'UTF-8') : 'Voir les qualifications' ?>"><?= $qualCount ?></button>
                            <?php else: ?>
                                <span class="eff-das__muted">0</span>
                            <?php endif; ?>
                        </td>
                        <?php
                        $currentAccessName = '';
                        foreach ($roles as $accessRole) {
                            if ((int) ($accessRole['id'] ?? 0) === $currentAccessRoleId) {
                                $currentAccessName = trim((string) ($accessRole['name'] ?? ''));
                                break;
                            }
                        }
                        ?>
                        <td data-label="Niveau d’accès">
                            <div class="eff-sheets__assign">
                                <?php if ($currentAccessName !== ''): ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--muted" title="<?= htmlspecialchars($rosterEn ? implode(', ', array_map([\App\Support\HtmlTranslator::class, 'translateText'], $roleParts)) : $rolesDisplay, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($currentAccessName, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--watch">Sans niveau</span>
                                <?php endif; ?>
                                <?php if ($canManageRoles && $roles !== []): ?>
                                    <button type="button" class="eff-das__edit" data-eff-edit="acces" <?= $memberData ?> data-eff-access="<?= $currentAccessRoleId ?>" title="<?= $currentAccessName !== '' ? 'Modifier le niveau d’accès' : 'Attribuer un niveau d’accès' ?>"><?= $editIcon ?><span class="sr-only">Modifier le niveau d’accès</span></button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Ancienneté">
                            <?php
                            $seniorityTitle = ($rosterEn ? 'Actual seniority: ' : 'Ancienneté réelle : ') . $seniorityLabel
                                . ($prePlatformLabel !== '' ? ($rosterEn ? ' (before the site: ' : ' (avant le site : ') . $prePlatformLabel . ')' : '');
                            ?>
                            <div class="eff-sheets__assign">
                                <span class="eff-das__seniority" title="<?= htmlspecialchars($seniorityTitle, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($canEditProfiles): ?>
                                    <details class="eff-sheets__pop">
                                        <summary class="eff-das__edit" title="Modifier l’ancienneté"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg><span class="sr-only">Modifier l’ancienneté</span></summary>
                                        <div class="eff-sheets__pop-panel">
                                            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/anciennete'), ENT_QUOTES, 'UTF-8') ?>" class="eff-sheets__pop-form" data-ath-quiet>
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
                        <?php $ringColor = $completionScore >= 90 ? 'var(--eff-mq-good, #059669)' : ($completionScore >= 60 ? 'var(--eff-mq-mid-ring, #64748b)' : 'var(--eff-mq-warn, #d97706)'); ?>
                        <td data-label="Dossier" class="eff-das__col-dossier">
                            <span class="eff-mq-ds">
                                <span class="eff-mq-ring" style="--pct:<?= max(0, min(100, $completionScore)) ?>%;--ring:<?= $ringColor ?>" role="img" aria-label="Dossier complété à <?= $completionScore ?> %"></span>
                                <span class="eff-mq-ds__v"><?= $completionScore ?> %</span>
                                <a class="eff-das__dossier" href="<?= htmlspecialchars(url('personnel/' . $id), ENT_QUOTES, 'UTF-8') ?>" title="Ouvrir le dossier de <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 10h18"/></svg>
                                    <span class="sr-only">Ouvrir le dossier de <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                                </a>
                            </span>
                        </td>
                        <?php
                        // « À traiter » : uniquement ce que les données montrent (pas de signal inventé).
                        $todo = [];
                        if ($completionScore < 60) {
                            $todo[] = ['w', 'Dossier ' . $completionScore . ' %', 'Dossier à compléter'];
                        }
                        if ($qualAlert !== '') {
                            $todo[] = ['w', 'Qualification', $qualAlert];
                        }
                        if ($unitId < 1) {
                            $todo[] = ['w', 'Sans unité', 'Membre à affecter'];
                        }
                        if ($status === 'inactive') {
                            $todo[] = ['x', 'Inactif', 'Compte inactif'];
                        } elseif ($status === 'pending_verification') {
                            $todo[] = ['n', 'E-mail', 'Adresse e-mail à vérifier'];
                        }
                        if (is_array($phaseBadge) && (string) ($phaseBadge['kind'] ?? '') === 'gate') {
                            $todo[] = ['n', 'Étape', (string) ($phaseBadge['label'] ?? 'Étape à valider')];
                        }
                        ?>
                        <td data-label="À traiter">
                            <?php if ($todo === []): ?>
                                <span class="eff-das__muted" aria-label="Rien à traiter">—</span>
                            <?php else: ?>
                                <span class="eff-mq-al">
                                    <?php foreach ($todo as [$kind, $label, $hint]): ?>
                                        <span class="is-<?= $kind ?>" title="<?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
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
                                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/elevation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-elev-dialog__inner eff-sheets__pop-form" data-ath-quiet>
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
        <?php if ($canEditProfiles || $orgFoundingDate !== ''): ?>
        <details class="eff-roster-hero__seniority eff-catalog__notice eff-mq-seniority">
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

<dialog class="eff-elev-dialog eff-qual-dialog" id="eff-qual-dialog" aria-labelledby="eff-qual-dialog-title">
    <div class="eff-elev-dialog__inner">
        <h3 id="eff-qual-dialog-title" class="eff-elev-dialog__title">Qualifications</h3>
        <p class="eff-elev-dialog__lead" id="eff-qual-dialog-lead"></p>
        <ul class="eff-qual-dialog__list" id="eff-qual-dialog-list"></ul>
        <p class="eff-qual-dialog__empty" id="eff-qual-dialog-empty" hidden>Aucune qualification enregistrée.</p>
        <div class="eff-elev-dialog__actions">
            <button type="button" class="eff-catalog__btn" data-eff-qual-close>Fermer</button>
            <a class="eff-catalog__btn eff-catalog__btn--primary" id="eff-qual-dialog-fiche" href="#">Ouvrir la fiche</a>
        </div>
    </div>
</dialog>

<?php if ($rosterView === 'general' && $rows !== []): ?>
<?php if ($canManageAssignments && $jobPickerGroups !== []): ?>
<dialog class="eff-dialog eff-dialog--wide" id="eff-dlg-roster-job" aria-labelledby="eff-dlg-roster-job-title">
    <form method="post" action="" data-eff-action="<?= htmlspecialchars($memberActionTemplate . '/fonctions', ENT_QUOTES, 'UTF-8') ?>" class="eff-dialog__body" data-job-picker data-job-max="<?= $jobRoleMax ?>">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-roster-job-title">Emploi de <span data-eff-member-name></span></h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
        <p class="eff-card__hint">Cochez jusqu’à <?= $jobRoleMax ?> emplois et choisissez le principal (★). L’emploi n’ouvre aucun droit sur le site.</p>
        <div class="eff-dialog__toolbar">
            <input type="search" placeholder="Rechercher un emploi (ex. médecin, JTAC, pilote)…" aria-label="Rechercher un emploi" data-filter-input>
            <span class="eff-dialog__counter" data-job-count></span>
        </div>
        <div class="eff-job-picker" data-filter-scope>
            <?php foreach ($jobPickerGroups as $gi => $group): ?>
                <details class="eff-job-picker__group" data-filter-group <?= $gi === 0 ? 'open' : '' ?>>
                    <summary><?= htmlspecialchars((string) $group['label'], ENT_QUOTES, 'UTF-8') ?> <small><?= count($group['items']) ?></small></summary>
                    <div class="eff-job-picker__items">
                        <?php foreach ($group['items'] as $item): ?>
                            <?php $val = (string) $item['value']; ?>
                            <div class="eff-job-picker__item<?= ($item['origin'] ?? '') === 'community' ? ' is-community' : '' ?>" data-filter-item data-filter-text="<?= htmlspecialchars(mb_strtolower($item['label'] . ' ' . $item['detail'] . ' ' . $group['label'] . ' ' . ($item['abbreviation'] ?? '') . ' ' . implode(' ', array_column($item['tags'] ?? [], 'name')), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"<?= ($item['description'] ?? '') !== '' ? ' title="' . htmlspecialchars((string) $item['description'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                <label class="eff-job-picker__check">
                                    <input type="checkbox" name="job_role_ids[]" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
                                    <span><strong><?= \App\Support\JobRoleBadge::html($item, false) ?><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?> <?= \App\Support\JobRoleBadge::origin((string) ($item['origin'] ?? '')) ?><?php if (!empty($item['hidden'])): ?> <span class="job-hidden-flag">Masquée</span><?php endif; ?></strong><?php if ($item['detail'] !== ''): ?><small><?= htmlspecialchars((string) $item['detail'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></span>
                                </label>
                                <label class="eff-job-picker__primary" title="Emploi principal">
                                    <input type="radio" name="primary_job_role_id" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
                                    <span aria-hidden="true">★</span><span class="eff-sr-only">Principal</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
            <p class="eff-card__hint" data-filter-empty hidden>Aucun emploi ne correspond. <a href="<?= htmlspecialchars(effectifs_workspace_url('fonctions'), ENT_QUOTES, 'UTF-8') ?>">Créer un emploi pour la communauté</a></p>
        </div>
        <footer class="eff-dialog__foot">
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url('fonctions'), ENT_QUOTES, 'UTF-8') ?>">Gérer les emplois</a>
            <span class="eff-dialog__spacer"></span>
            <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
            <button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’emploi</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?php if ($canManageAssignments): ?>
<dialog class="eff-dialog" id="eff-dlg-roster-role" aria-labelledby="eff-dlg-roster-role-title">
    <form method="post" action="" data-eff-action="<?= htmlspecialchars($memberActionTemplate . '/role-unite', ENT_QUOTES, 'UTF-8') ?>" class="eff-dialog__body">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-roster-role-title">Rôle de <span data-eff-member-name></span></h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
        <label for="eff-roster-role-input">Rôle tenu dans son unité</label>
        <input type="text" id="eff-roster-role-input" name="role_name" maxlength="120" required list="eff-roster-role-list" placeholder="Ex. chef de groupe, tireur, adjoint…" data-eff-role-input>
        <datalist id="eff-roster-role-list">
            <?php foreach ($assignmentRoleSuggestions as $suggest): ?>
                <option value="<?= htmlspecialchars($suggest, ENT_QUOTES, 'UTF-8') ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <p class="eff-card__hint">Le changement est conservé dans l’historique des affectations. La personne doit déjà être affectée à une unité.</p>
        <footer class="eff-dialog__foot">
            <span class="eff-dialog__spacer"></span>
            <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
            <button type="submit" class="eff-btn eff-btn--primary">Enregistrer le rôle</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?php if ($canManageRoles && $roles !== []): ?>
<dialog class="eff-dialog" id="eff-dlg-roster-access" aria-labelledby="eff-dlg-roster-access-title">
    <form method="post" action="" data-eff-action="<?= htmlspecialchars($memberActionTemplate . '/roles', ENT_QUOTES, 'UTF-8') ?>" class="eff-dialog__body">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-roster-access-title">Niveau d’accès de <span data-eff-member-name></span></h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
        <fieldset class="eff-role-grid">
            <legend>Un seul niveau par personne</legend>
            <?php foreach ($roles as $accessRole): ?>
                <?php $rid = (int) ($accessRole['id'] ?? 0); ?>
                <label>
                    <input type="radio" name="access_role_id" value="<?= $rid ?>" required>
                    <span>
                        <strong><?= htmlspecialchars((string) ($accessRole['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        <em><?= htmlspecialchars((string) ($accessRole['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></em>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <footer class="eff-dialog__foot">
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url('roles'), ENT_QUOTES, 'UTF-8') ?>">Ce que chaque niveau permet</a>
            <span class="eff-dialog__spacer"></span>
            <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
            <button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’accès</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/eff-member.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/eff-roster-edit.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>

        <script>
        (function () {
            // Délégation : les lignes du tableau peuvent être remplacées après un enregistrement sans rechargement.
            document.addEventListener('click', function (ev) {
                var t = ev.target;
                var open = t.closest ? t.closest('[data-eff-elev-open]') : null;
                if (open) {
                    var id = open.getAttribute('data-eff-elev-open');
                    var dialog = id ? document.getElementById('eff-elev-dialog-' + id) : null;
                    if (dialog && typeof dialog.showModal === 'function') {
                        dialog.showModal();
                    }
                    return;
                }
                var close = t.closest ? t.closest('[data-eff-elev-close]') : null;
                if (close) {
                    var d = close.closest('dialog');
                    if (d) {
                        d.close();
                    }
                    return;
                }
                if (t.matches && t.matches('dialog.eff-elev-dialog')) {
                    t.close();
                }

                var qualOpen = t.closest ? t.closest('[data-eff-qual-open]') : null;
                if (qualOpen) {
                    var qualDialog = document.getElementById('eff-qual-dialog');
                    var list = document.getElementById('eff-qual-dialog-list');
                    var empty = document.getElementById('eff-qual-dialog-empty');
                    var lead = document.getElementById('eff-qual-dialog-lead');
                    var fiche = document.getElementById('eff-qual-dialog-fiche');
                    if (!qualDialog || !list) return;
                    var memberName = qualOpen.getAttribute('data-eff-qual-name') || '';
                    var items = [];
                    try {
                        items = JSON.parse(qualOpen.getAttribute('data-eff-qual-items') || '[]') || [];
                    } catch (e) {
                        items = [];
                    }
                    if (lead) lead.textContent = memberName;
                    list.innerHTML = '';
                    if (!items.length) {
                        if (empty) empty.hidden = false;
                        list.hidden = true;
                    } else {
                        if (empty) empty.hidden = true;
                        list.hidden = false;
                        items.forEach(function (item) {
                            var li = document.createElement('li');
                            li.className = 'eff-qual-dialog__item';
                            var title = document.createElement('div');
                            title.className = 'eff-qual-dialog__name';
                            title.textContent = item.name || 'Qualification';
                            if (item.level) {
                                var lvl = document.createElement('span');
                                lvl.className = 'eff-qual-dialog__level';
                                lvl.textContent = ' — ' + item.level;
                                title.appendChild(lvl);
                            }
                            li.appendChild(title);
                            var meta = document.createElement('div');
                            meta.className = 'eff-qual-dialog__meta';
                            var bits = [];
                            if (item.admin) bits.push(item.admin);
                            if (item.temporal) bits.push(item.temporal);
                            meta.textContent = bits.join(' · ');
                            if (bits.length) li.appendChild(meta);
                            list.appendChild(li);
                        });
                    }
                    var ficheHref = qualOpen.getAttribute('data-eff-qual-fiche') || '';
                    if (fiche) {
                        fiche.href = ficheHref || '#';
                        fiche.hidden = !ficheHref;
                    }
                    if (typeof qualDialog.showModal === 'function') {
                        qualDialog.showModal();
                    }
                    return;
                }
                var qualClose = t.closest ? t.closest('[data-eff-qual-close]') : null;
                if (qualClose) {
                    var qd = qualClose.closest('dialog');
                    if (qd) qd.close();
                    return;
                }
                if (t.matches && t.matches('dialog.eff-qual-dialog')) {
                    t.close();
                }
            });
        })();
        </script>
        <?php if ($canBulkAny && $rows !== []): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/eff-bulk-actions.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/eff-workspace.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
