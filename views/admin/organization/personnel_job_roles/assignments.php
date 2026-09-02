<?php
declare(strict_types=1);

$assignmentRows = $assignmentRows ?? [];
$assignmentPivot = $assignmentPivot ?? [];
$jobRoleOptions = $jobRoleOptions ?? [];
$jobRolePermissionCounts = $jobRolePermissionCounts ?? [];
$pjrAssignSettings = $pjrAssignSettings ?? [];
$pivotEnabled = !empty($pivotEnabled);
$filters = $filters ?? [];
$assignmentsPage = (int) ($assignmentsPage ?? 1);
$assignmentsTotal = (int) ($assignmentsTotal ?? 0);
$assignmentsPerPage = (int) ($assignmentsPerPage ?? 30);
$assignmentsTotalPages = (int) ($assignmentsTotalPages ?? 1);
$activeTab = $activeTab ?? 'assignments';
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$isAthShell = !empty($isBackOfficeShell);

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$maxRoles = (int) ($pjrAssignSettings['max_roles_per_member'] ?? 5);
$defaultExpand = (int) ($pjrAssignSettings['default_expand_role_rows'] ?? 3);

$returnQuery = http_build_query(array_filter([
    'search' => $filters['search'] ?? '',
    'job_role_id' => !empty($filters['job_role_id']) ? (int) $filters['job_role_id'] : null,
    'unassigned' => !empty($filters['unassigned']) ? '1' : null,
    'page' => $assignmentsPage > 1 ? $assignmentsPage : null,
], static fn ($v) => $v !== null && $v !== ''));

$baseUrl = url('back-office/personnel-job-roles/assignments');

$assignmentsQuery = static function (int $page) use ($filters, $baseUrl): string {
    $q = array_filter([
        'search' => $filters['search'] ?? '',
        'job_role_id' => !empty($filters['job_role_id']) ? (int) $filters['job_role_id'] : null,
        'unassigned' => !empty($filters['unassigned']) ? '1' : null,
        'page' => $page > 1 ? $page : null,
    ], static fn ($v) => $v !== null && $v !== '');

    return $baseUrl . ($q !== [] ? '?' . http_build_query($q) : '');
};

$statusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de vérification',
        'suspended' => 'Compte suspendu',
        default => $raw !== '' ? $raw : '—',
    };
};

$rolesInCatalog = count($jobRoleOptions);
$startRow = $assignmentsTotal > 0 ? (($assignmentsPage - 1) * $assignmentsPerPage) + 1 : 0;
$endRow = min($assignmentsTotal, $assignmentsPage * $assignmentsPerPage);

if ($isAthShell):
    $athKpis = [
        ['label' => 'MEMBRES', 'value' => (string) $assignmentsTotal, 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'effectif filtré'],
        ['label' => 'EMPLOIS', 'value' => (string) $rolesInCatalog, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'référentiel'],
        ['label' => 'MAX / MEMBRE', 'value' => (string) $maxRoles, 'delta' => '', 'tone' => '#c98a12', 'pct' => '—', 'note' => 'emplois attribuables'],
        ['label' => 'MODE', 'value' => $pivotEnabled ? 'Multi-emplois' : 'Simple', 'delta' => '', 'tone' => $pivotEnabled ? '#0b8a5c' : '#8c979b', 'pct' => '—', 'note' => 'attribution'],
    ];
    require base_path('views/partials/ath_kpis.php');
    ?>
<div class="flex flex-wrap gap-2 ath-rise">
    <a href="<?= url('back-office/personnel-job-roles/kits') ?>" class="ath-btn<?= $activeTab === 'kits' ? ' ath-btn--solid' : '' ?>">Kits de fonctions</a>
    <a href="<?= url('back-office/personnel-job-roles') ?>" class="ath-btn<?= $activeTab === 'referentiel' ? ' ath-btn--solid' : '' ?>">Référentiel</a>
    <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="ath-btn<?= $activeTab === 'assignments' ? ' ath-btn--solid' : '' ?>">Attributions effectifs</a>
    <a href="<?= url('back-office/personnel-job-roles/roles/create') ?>" class="ath-btn">Nouvel emploi</a>
</div>
<?php endif; ?>

<div class="<?= $isAthShell ? 'pjr-assignments ath-dash-page' : 'mx-auto max-w-7xl px-6 py-12' ?>">
    <?php if (!empty($functionKitsActive)): ?>
    <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
        Les listes ci-dessous se limitent aux domaines choisis pour votre communauté, plus les fonctions déjà attribuées.
        <a class="font-semibold underline" href="<?= $h(url('back-office/personnel-job-roles/kits')) ?>">Modifier les domaines</a>
    </p>
    <?php endif; ?>
    <?php if (!$isAthShell): ?>
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Attributions métier</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">
                <?php if ($pivotEnabled): ?>
                Attribuez un ou plusieurs emplois du référentiel à chaque membre. Indiquez l’emploi <strong class="font-semibold">principal</strong> : il sert de référence pour le dossier et l’ordre de bataille.
                <?php else: ?>
                Attribuez les emplois du référentiel à chaque membre. L’attribution de plusieurs emplois à une même personne n’est pas encore disponible pour votre communauté.
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= url('back-office/personnel-job-roles') ?>" class="text-sm font-medium text-slate-600 hover:underline">Référentiel &amp; catégories</a>
    </div>
    <?php endif; ?>

    <?php if ($isAthShell): ?>
    <div class="ath-panel-dark ath-rise">
        <p class="ath-panel-dark__kicker">Autorisations &amp; emplois</p>
        <p class="ath-body" style="color:#d5dde0;margin-top:8px;">
            Chaque emploi du référentiel peut être associé à des <strong style="color:#fff;font-weight:800;">autorisations</strong> (section « Autorisations » sur la fiche de l’emploi).
            Lors du choix d’un emploi ci-dessous, une mention indique si des autorisations y sont liées.
            Pour consulter la liste fusionnée pour une personne (tous ses emplois attribués), utilisez le lien sous son nom.
        </p>
    </div>

    <details class="ath-roles-edit-item ath-rise">
        <summary class="pjr-assign-settings__summary">
            <span class="pjr-assign-settings__title">Paramètres d’attribution</span>
            <span class="pjr-assign-settings__hint">Organisation · taille de page, plafond et affichage</span>
        </summary>
        <form method="post" action="<?= url('back-office/personnel-job-roles/assignments/settings') ?>" class="ath-roles-edit-form">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="pjr-max-roles">Emplois maximum par membre</label>
                <input type="number" name="max_roles_per_member" id="pjr-max-roles" min="1" max="12" value="<?= (int) ($pjrAssignSettings['max_roles_per_member'] ?? 5) ?>" class="bo-setting-row__field">
            </div>
            <div>
                <label for="pjr-expand-rows">Lignes proposées au chargement</label>
                <input type="number" name="default_expand_role_rows" id="pjr-expand-rows" min="1" max="12" value="<?= (int) ($pjrAssignSettings['default_expand_role_rows'] ?? 3) ?>" class="bo-setting-row__field">
                <p class="pjr-assign-settings__help">Nombre de lignes de saisie affichées par défaut, sans dépasser le maximum ci-dessus.</p>
            </div>
            <div>
                <label for="pjr-page-size">Membres par page</label>
                <input type="number" name="assignments_page_size" id="pjr-page-size" min="10" max="100" value="<?= (int) ($pjrAssignSettings['assignments_page_size'] ?? 30) ?>" class="bo-setting-row__field">
            </div>
            <label class="ath-users-filters__check">
                <input type="hidden" name="require_primary_when_multiple" value="0">
                <input type="checkbox" name="require_primary_when_multiple" id="req_pri" value="1" <?= !empty($pjrAssignSettings['require_primary_when_multiple']) ? 'checked' : '' ?>>
                Exiger un emploi principal lorsque plusieurs emplois sont renseignés
            </label>
            <label class="ath-users-filters__check">
                <input type="hidden" name="show_english_labels" value="0">
                <input type="checkbox" name="show_english_labels" id="show_en" value="1" <?= !empty($pjrAssignSettings['show_english_labels']) ? 'checked' : '' ?>>
                Afficher le libellé anglais dans les listes
            </label>
            <label class="ath-users-filters__check">
                <input type="hidden" name="show_category_in_role_picklist" value="0">
                <input type="checkbox" name="show_category_in_role_picklist" id="show_cat" value="1" <?= !empty($pjrAssignSettings['show_category_in_role_picklist']) ? 'checked' : '' ?>>
                Afficher la famille d’emplois dans les listes
            </label>
            <label class="ath-users-filters__check">
                <input type="hidden" name="append_secondaries_to_primary_display" value="0">
                <input type="checkbox" name="append_secondaries_to_primary_display" id="append_sec" value="1" <?= !empty($pjrAssignSettings['append_secondaries_to_primary_display']) ? 'checked' : '' ?>>
                Fusionner les emplois secondaires dans le libellé principal du dossier
            </label>
            <div class="pjr-assign-settings__actions">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les paramètres</button>
            </div>
        </form>
    </details>

    <form method="get" action="<?= $h($baseUrl) ?>" class="ath-users-filters ath-rise">
        <div>
            <label class="ath-users-filters__label" for="pjr-filter-search">Recherche</label>
            <input type="search" name="search" id="pjr-filter-search" value="<?= $h((string) ($filters['search'] ?? '')) ?>" placeholder="Nom, e-mail, indicatif…" class="bo-setting-row__field">
        </div>
        <div class="pjr-assign-filter-role">
            <label class="ath-users-filters__label" for="pjr-filter-job-role-btn">Emploi attribué</label>
            <?php
            $pjrComboName = 'job_role_id';
            $pjrComboSelectedId = (int) ($filters['job_role_id'] ?? 0);
            $pjrComboEmptyValue = '0';
            $pjrComboEmptyLabel = 'Tous les emplois';
            $pjrComboId = 'pjr-filter-job-role';
            require __DIR__ . '/_role_combobox.php';
            ?>
        </div>
        <label class="ath-users-filters__check">
            <input type="checkbox" name="unassigned" id="unassigned" value="1" <?= !empty($filters['unassigned']) ? 'checked' : '' ?>>
            Sans emploi attribué
        </label>
        <button type="submit" class="ath-btn ath-btn--solid">Appliquer les filtres</button>
        <a href="<?= $h(url('back-office/personnel-job-roles')) ?>" class="ath-btn">Référentiel</a>
    </form>
    <?php else: ?>
    <details class="mb-8 rounded-xl border border-amber-200 bg-amber-50/40 p-4 shadow-sm open:bg-amber-50/60">
        <summary class="cursor-pointer text-sm font-bold text-amber-950">Paramètres d’attribution (organisation)</summary>
        <p class="mt-2 text-xs text-amber-900/90">Ces réglages s’appliquent à toute votre communauté : taille de page, nombre maximal d’emplois par membre, affichage des listes et du libellé dossier.</p>
        <form method="post" action="<?= url('back-office/personnel-job-roles/assignments/settings') ?>" class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700">Emplois max. par membre</label>
                <input type="number" name="max_roles_per_member" min="1" max="12" value="<?= (int) ($pjrAssignSettings['max_roles_per_member'] ?? 5) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700">Lignes vides affichées au chargement</label>
                <input type="number" name="default_expand_role_rows" min="1" max="12" value="<?= (int) ($pjrAssignSettings['default_expand_role_rows'] ?? 3) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700">Membres par page</label>
                <input type="number" name="assignments_page_size" min="10" max="100" value="<?= (int) ($pjrAssignSettings['assignments_page_size'] ?? 30) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2 md:col-span-2 lg:col-span-3">
                <input type="hidden" name="require_primary_when_multiple" value="0">
                <input type="checkbox" name="require_primary_when_multiple" id="req_pri_legacy" value="1" <?= !empty($pjrAssignSettings['require_primary_when_multiple']) ? 'checked' : '' ?>>
                <label for="req_pri_legacy" class="text-sm text-slate-800">Exiger un emploi principal lorsque plusieurs emplois sont renseignés.</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="show_english_labels" value="0">
                <input type="checkbox" name="show_english_labels" id="show_en_legacy" value="1" <?= !empty($pjrAssignSettings['show_english_labels']) ? 'checked' : '' ?>>
                <label for="show_en_legacy" class="text-sm text-slate-800">Afficher le libellé anglais dans les listes</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="show_category_in_role_picklist" value="0">
                <input type="checkbox" name="show_category_in_role_picklist" id="show_cat_legacy" value="1" <?= !empty($pjrAssignSettings['show_category_in_role_picklist']) ? 'checked' : '' ?>>
                <label for="show_cat_legacy" class="text-sm text-slate-800">Afficher la famille d’emplois dans les listes</label>
            </div>
            <div class="flex items-center gap-2 md:col-span-2">
                <input type="hidden" name="append_secondaries_to_primary_display" value="0">
                <input type="checkbox" name="append_secondaries_to_primary_display" id="append_sec_legacy" value="1" <?= !empty($pjrAssignSettings['append_secondaries_to_primary_display']) ? 'checked' : '' ?>>
                <label for="append_sec_legacy" class="text-sm text-slate-800">Fusionner les emplois secondaires dans le libellé principal du dossier.</label>
            </div>
            <div class="md:col-span-2 lg:col-span-3">
                <button type="submit" class="rounded-lg bg-amber-800 px-4 py-2 text-sm font-bold text-white hover:bg-amber-900">Enregistrer les paramètres</button>
            </div>
        </form>
    </details>

    <form method="get" action="<?= $h($baseUrl) ?>" class="mb-8 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-semibold text-slate-600">Recherche</label>
            <input type="text" name="search" value="<?= $h((string) ($filters['search'] ?? '')) ?>" placeholder="Nom, e-mail, indicatif…" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div class="min-w-[240px] max-w-md flex-1">
            <label class="mb-1 block text-xs font-semibold text-slate-600" for="pjr-filter-job-role-btn">Emploi attribué</label>
            <?php
            $pjrComboName = 'job_role_id';
            $pjrComboSelectedId = (int) ($filters['job_role_id'] ?? 0);
            $pjrComboEmptyValue = '0';
            $pjrComboEmptyLabel = 'Tous les emplois';
            $pjrComboId = 'pjr-filter-job-role';
            require __DIR__ . '/_role_combobox.php';
            ?>
        </div>
        <div class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="unassigned" id="unassigned_legacy" value="1" <?= !empty($filters['unassigned']) ? 'checked' : '' ?>>
            <label for="unassigned_legacy" class="text-sm text-slate-700">Sans emploi attribué</label>
        </div>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrer</button>
    </form>

    <div class="mb-4 text-sm text-slate-600">
        <?= (int) $assignmentsTotal ?> membre(s) · page <?= (int) $assignmentsPage ?> / <?= (int) $assignmentsTotalPages ?>
        <?php if ($pivotEnabled): ?> · jusqu’à <?= (int) $maxRoles ?> emploi(s) par personne<?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="<?= $isAthShell ? 'ath-table-panel ath-rise' : 'overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm' ?>">
        <?php if ($isAthShell): ?>
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title">Attributions par membre</span>
            <span class="ath-table-toolbar__count"><?= (int) $assignmentsTotal ?> membre<?= $assignmentsTotal > 1 ? 's' : '' ?></span>
            <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
            <?php if ($pivotEnabled): ?>
            <span class="pjr-assign-toolbar__meta">Jusqu’à <?= (int) $maxRoles ?> emploi(s) par personne</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="<?= $isAthShell ? 'ath-table-wrap' : '' ?>">
            <table class="<?= $isAthShell ? 'ath-table pjr-assign-table' : 'w-full min-w-[960px] border-collapse text-left text-sm' ?>">
                <thead class="<?= $isAthShell ? '' : 'border-b border-slate-200 bg-slate-50' ?>">
                    <tr>
                        <th class="<?= $isAthShell ? '' : 'p-3 text-xs font-semibold uppercase text-slate-600' ?>" scope="col">Membre</th>
                        <th class="<?= $isAthShell ? '' : 'p-3 text-xs font-semibold uppercase text-slate-600' ?>" scope="col">Statut</th>
                        <th class="<?= $isAthShell ? '' : 'p-3 text-xs font-semibold uppercase text-slate-600' ?>" scope="col">Emplois (dossier)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignmentRows as $row): ?>
                    <?php
                    $uid = (int) ($row['id'] ?? 0);
                    $slug = trim((string) ($row['profile_slug'] ?? ''));
                    $personnelUrl = url('personnel/' . ($slug !== '' ? $slug : (string) $uid));
                    $pivotRows = isset($assignmentPivot[$uid]) ? $assignmentPivot[$uid] : [];
                    $nExisting = count($pivotRows);
                    $slotCount = min($maxRoles, max($nExisting, 1, min($defaultExpand, $maxRoles)));
                    $primaryIdxFromData = null;
                    $dossierLabel = '';
                    foreach ($pivotRows as $pidx => $prow) {
                        if (!empty($prow['is_primary'])) {
                            $primaryIdxFromData = (int) $pidx;
                            $prName = trim((string) ($prow['role_name'] ?? ''));
                            $prDetail = trim((string) ($prow['role_detail'] ?? ''));
                            $dossierLabel = $prDetail !== '' && $prName !== '' ? $prName . ' — ' . $prDetail : ($prName !== '' ? $prName : $prDetail);
                            break;
                        }
                    }
                    $memberStatus = $statusLabel((string) ($row['status'] ?? ''));
                    ?>
                    <tr class="<?= $isAthShell ? 'ath-row' : 'border-b border-slate-100 align-top hover:bg-slate-50/80' ?>">
                        <td class="<?= $isAthShell ? '' : 'p-3' ?>">
                            <p class="<?= $isAthShell ? 'pjr-assign-member__name' : 'font-semibold text-slate-900' ?>"><?= $h((string) ($row['display_name'] ?? '—')) ?></p>
                            <p class="<?= $isAthShell ? 'pjr-assign-member__meta' : 'text-xs text-slate-500' ?>"><?= $h((string) ($row['email'] ?? '')) ?></p>
                            <?php if (trim((string) ($row['callsign'] ?? '')) !== ''): ?>
                            <p class="<?= $isAthShell ? 'pjr-assign-member__callsign' : 'text-xs font-mono text-slate-600' ?>"><?= $h((string) $row['callsign']) ?></p>
                            <?php endif; ?>
                            <a href="<?= $h($personnelUrl) ?>" class="<?= $isAthShell ? 'pjr-assign-member__link' : 'mt-1 inline-block text-xs font-medium text-cyan-700 hover:underline' ?>">Fiche personnelle</a>
                            <button
                                type="button"
                                class="pjr-open-member-perms <?= $isAthShell ? 'ath-btn pjr-assign-perms-btn' : 'mt-2 flex w-full max-w-[16rem] items-center justify-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-left text-[11px] font-semibold text-indigo-900 shadow-sm transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400' ?>"
                                data-user-id="<?= (int) $uid ?>"
                                data-member-name="<?= $h((string) ($row['display_name'] ?? '')) ?>"
                            >
                                <?php if ($isAthShell): ?>
                                Autorisations liées aux emplois
                                <?php else: ?>
                                <svg class="h-3.5 w-3.5 shrink-0 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 0 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                <span>Autorisations liées aux emplois</span>
                                <?php endif; ?>
                            </button>
                        </td>
                        <td class="<?= $isAthShell ? '' : 'p-3 text-xs uppercase text-slate-600' ?>">
                            <?php if ($isAthShell): ?>
                            <span class="ath-cell ath-cell--badge" style="color:#3c474c;background:#f4f6f7;border-color:#e2e6e8;padding:3px 8px;font-weight:800;"><?= $h($memberStatus) ?></span>
                            <?php else: ?>
                            <?= $h($memberStatus) ?>
                            <?php endif; ?>
                        </td>
                        <td class="<?= $isAthShell ? '' : 'p-3' ?>">
                            <form method="post" action="<?= url('back-office/personnel-job-roles/assignments/save') ?>" class="pjr-assign-form" data-max-slots="<?= (int) $maxRoles ?>" data-user-id="<?= $uid ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="return_query" value="<?= $h($returnQuery) ?>">
                                <div class="pjr-assign-slots">
                                    <p class="pjr-assign-slots__label">Emplois</p>
                                    <?php
                                    for ($si = 0; $si < $slotCount; $si++):
                                        $pr = $pivotRows[$si] ?? null;
                                        $selId = $pr ? (int) ($pr['personnel_job_role_id'] ?? 0) : 0;
                                        $det = $pr ? trim((string) ($pr['role_detail'] ?? '')) : '';
                                        $isPri = ($primaryIdxFromData !== null && $primaryIdxFromData < $slotCount && $si === $primaryIdxFromData)
                                            || (($primaryIdxFromData === null || $primaryIdxFromData >= $slotCount) && $si === 0);
                                    ?>
                                    <div class="pjr-assign-slot">
                                        <label class="pjr-assign-slot__primary">
                                            <input type="radio" name="primary_slot" value="<?= $si ?>" class="pjr-primary-radio" <?= $isPri ? 'checked' : '' ?>>
                                            Principal
                                        </label>
                                        <div class="pjr-assign-slot__role">
                                            <label class="pjr-assign-slot__field-label" for="pjr-slot-<?= $uid ?>-<?= $si ?>-btn">Emploi</label>
                                            <?php
                                            $pjrComboName = 'slots[' . $si . '][role_id]';
                                            $pjrComboSelectedId = $selId;
                                            $pjrComboEmptyValue = '';
                                            $pjrComboEmptyLabel = 'Aucun choix';
                                            $pjrComboId = 'pjr-slot-' . $uid . '-' . $si;
                                            require __DIR__ . '/_role_combobox.php';
                                            ?>
                                        </div>
                                        <div class="pjr-assign-slot__detail">
                                            <label class="pjr-assign-slot__field-label">Précision</label>
                                            <input type="text" name="slots[<?= $si ?>][detail]" value="<?= $h($det) ?>" class="pjr-assign-slot__input" maxlength="150" placeholder="Optionnel">
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                    <?php if ($pivotEnabled): ?>
                                    <button type="button" class="pjr-add-slot ath-btn pjr-assign-add-slot">Ajouter une ligne d’emploi</button>
                                    <?php endif; ?>
                                </div>
                                <div class="pjr-assign-dossier">
                                    <span class="pjr-assign-dossier__label">Libellé dossier</span>
                                    <?= $dossierLabel !== '' ? $h($dossierLabel) : '—' ?>
                                </div>
                                <div class="pjr-assign-form__actions">
                                    <button type="submit" class="<?= $isAthShell ? 'ath-btn ath-btn--solid' : 'rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800' ?>">Enregistrer</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($assignmentRows)): ?>
                    <tr>
                        <td colspan="3" class="<?= $isAthShell ? 'ath-table-empty' : 'p-8 text-center text-slate-500' ?>">Aucun membre ne correspond aux filtres.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($isAthShell): ?>
        <div class="ath-table-foot">
            <div class="ath-table-foot__meta">
                <?= $assignmentsTotal > 0
                    ? 'Affichage ' . $startRow . ' – ' . $endRow . ' sur ' . $assignmentsTotal . ' · page ' . $assignmentsPage . ' / ' . $assignmentsTotalPages
                    : 'Aucun membre · page ' . $assignmentsPage . ' / ' . $assignmentsTotalPages ?>
            </div>
            <?php if ($assignmentsTotalPages > 1): ?>
            <div class="ath-pager">
                <?php if ($assignmentsPage > 1): ?>
                <a href="<?= $h($assignmentsQuery($assignmentsPage - 1)) ?>" class="ath-pager__btn">‹</a>
                <?php endif; ?>
                <?php
                $fromPage = max(1, $assignmentsPage - 2);
                $toPage = min($assignmentsTotalPages, $assignmentsPage + 2);
                for ($p = $fromPage; $p <= $toPage; $p++):
                ?>
                <a href="<?= $h($assignmentsQuery($p)) ?>" class="ath-pager__btn<?= $p === $assignmentsPage ? ' is-active' : '' ?>"><?= (int) $p ?></a>
                <?php endfor; ?>
                <?php if ($assignmentsPage < $assignmentsTotalPages): ?>
                <a href="<?= $h($assignmentsQuery($assignmentsPage + 1)) ?>" class="ath-pager__btn">›</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$isAthShell && $assignmentsTotalPages > 1): ?>
    <div class="mt-6 flex flex-wrap justify-center gap-2">
        <?php for ($p = 1; $p <= $assignmentsTotalPages; $p++): ?>
        <a href="<?= $h($assignmentsQuery($p)) ?>" class="min-w-[2.25rem] rounded border px-3 py-1.5 text-sm <?= $p === $assignmentsPage ? 'border-slate-900 bg-slate-900 font-bold text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"><?= (int) $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <dialog id="pjr-member-perms-dialog" class="pjr-assign-dialog<?= $isAthShell ? ' pjr-assign-dialog--ath' : '' ?>">
        <div class="pjr-assign-dialog__inner">
            <div class="pjr-assign-dialog__head">
                <h2 id="pjr-member-perms-title" class="pjr-assign-dialog__title">Autorisations liées aux emplois</h2>
                <button type="button" class="pjr-member-perms-close <?= $isAthShell ? 'ath-btn' : 'rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500' ?>">Fermer</button>
            </div>
            <div id="pjr-member-perms-body" class="pjr-assign-dialog__body"></div>
        </div>
    </dialog>
</div>

<?php if ($pivotEnabled): ?>
<template id="pjr-slot-template">
    <div class="pjr-assign-slot">
        <label class="pjr-assign-slot__primary">
            <input type="radio" name="primary_slot" value="__IDX__" class="pjr-primary-radio">
            Principal
        </label>
        <div class="pjr-assign-slot__role">
            <label class="pjr-assign-slot__field-label" for="pjr-slot-tpl-__IDX__-btn">Emploi</label>
            <div class="pjr-role-combobox w-full min-w-0" data-pjr-role-combobox data-reset-value="" data-reset-label="Aucun choix">
                <input type="hidden" name="slots[__IDX__][role_id]" value="" class="pjr-role-combobox-value">
                <button type="button" class="pjr-role-combobox-trigger" aria-haspopup="listbox" aria-expanded="false" id="pjr-slot-tpl-__IDX__-btn" aria-labelledby="pjr-slot-tpl-__IDX__-lbl">
                    <span id="pjr-slot-tpl-__IDX__-lbl" class="pjr-role-combobox-label">Aucun choix</span>
                    <svg class="pjr-role-combobox-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
            </div>
        </div>
        <div class="pjr-assign-slot__detail">
            <label class="pjr-assign-slot__field-label">Précision</label>
            <input type="text" name="slots[__IDX__][detail]" value="" class="pjr-assign-slot__input" maxlength="150" placeholder="Optionnel">
        </div>
    </div>
</template>
<script>
(function () {
  var maxGlobal = <?= (int) $maxRoles ?>;
  document.querySelectorAll('.pjr-assign-form').forEach(function (form) {
    var maxSlots = parseInt(form.getAttribute('data-max-slots') || String(maxGlobal), 10) || maxGlobal;
    var tpl = document.getElementById('pjr-slot-template');
    if (!tpl) return;
    var wrap = form.querySelector('.pjr-assign-slots');
    if (!wrap) return;
    var addBtn = form.querySelector('.pjr-add-slot');
    function countSlots() {
      return wrap.querySelectorAll('.pjr-assign-slot').length;
    }
    function reindexSlots() {
      var uid = form.getAttribute('data-user-id') || '0';
      var slots = wrap.querySelectorAll('.pjr-assign-slot');
      slots.forEach(function (row, i) {
        row.querySelectorAll('input, select').forEach(function (el) {
          var n = el.getAttribute('name');
          if (n && n.indexOf('slots[') === 0) {
            el.setAttribute('name', n.replace(/slots\[\d+]/, 'slots[' + i + ']'));
          }
        });
        var rad = row.querySelector('.pjr-primary-radio');
        if (rad) {
          rad.value = String(i);
          rad.setAttribute('name', 'primary_slot');
        }
        var trig = row.querySelector('.pjr-role-combobox-trigger');
        var lbl = row.querySelector('.pjr-role-combobox-label');
        if (trig && lbl) {
          var base = 'pjr-slot-' + uid + '-' + i;
          trig.id = base + '-btn';
          lbl.id = base + '-lbl';
          var lab = row.querySelector('.pjr-assign-slot__field-label[for]');
          if (lab) {
            lab.setAttribute('for', base + '-btn');
          }
        }
      });
    }
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        if (countSlots() >= maxSlots) return;
        var idx = countSlots();
        var html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
        var div = document.createElement('div');
        div.innerHTML = html.trim();
        var node = div.firstElementChild;
        if (!node) return;
        wrap.insertBefore(node, addBtn);
        reindexSlots();
        var radios = wrap.querySelectorAll('input[type=radio][name=primary_slot]');
        if (radios.length && !Array.prototype.some.call(radios, function (r) { return r.checked; })) {
          radios[0].checked = true;
        }
      });
    }
  });
})();
</script>
<?php endif; ?>

<?php
$pjrComboboxPayload = array_map(static function (array $jo) use ($jobRolePermissionCounts): array {
    $rid = (int) ($jo['id'] ?? 0);
    $row = [
        'id' => $rid,
        'label' => (string) $jo['label'],
        'segments' => array_values($jo['segments'] ?? []),
        'search' => (string) ($jo['search'] ?? ''),
        'permission_count' => (int) ($jobRolePermissionCounts[$rid] ?? 0),
    ];
    if (trim((string) ($jo['label_en'] ?? '')) !== '') {
        $row['label_en'] = (string) $jo['label_en'];
    }

    return $row;
}, $jobRoleOptions);
$pjrComboboxJson = json_encode($pjrComboboxPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$pjrMemberPermUrlJson = json_encode(url('back-office/personnel-job-roles/assignments/member-permissions'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<script>window.__PJR_JOB_ROLES = <?= $pjrComboboxJson !== false ? $pjrComboboxJson : '[]' ?>;</script>
<script>window.__PJR_MEMBER_PERM_URL = <?= $pjrMemberPermUrlJson !== false ? $pjrMemberPermUrlJson : '""' ?>;</script>
<script src="<?= $h(url('assets/js/pjr_role_combobox.js')) ?>" defer></script>
<script src="<?= $h(url('assets/js/pjr_member_job_permissions.js')) ?>" defer></script>
