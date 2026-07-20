<?php
declare(strict_types=1);

$canRolesList = !empty($canRolesList);
$canRolesCanvas = !empty($canRolesCanvas);
$canPresets = !empty($canPresets);
$canGrades = !empty($canGrades);
$canStructure = !empty($canStructure);
$canStructureRecruitmentHub = !empty($canStructureRecruitmentHub);
$canSeniorityAdmin = !empty($canSeniorityAdmin);

$stats = is_array($hubStats ?? null) ? $hubStats : [];
$membersActive = (int) ($stats['members_active'] ?? 0);
$groupsCount = (int) ($stats['groups'] ?? 0);
$teamsCount = (int) ($stats['teams'] ?? 0);
$rolesCount = (int) ($stats['roles'] ?? 0);
$gradesCount = (int) ($stats['grades'] ?? 0);
$jobRolesCount = (int) ($stats['job_roles'] ?? 0);
$communityName = trim((string) ($communityName ?? 'Communauté'));

$fmtCount = static function (int $n, string $one, string $many): string {
    if ($n < 1) {
        return 'Aucun pour l’instant';
    }

    return $n . ' ' . ($n === 1 ? $one : $many);
};

/** @var list<array{id: string, title: string, desc: string, domain: string, domainKey: string, href: string, volume: string, cta: string, primary: bool, ok: bool}> $hubRows */
$hubRows = [
    [
        'id' => 'tableur',
        'title' => 'Tableur des effectifs',
        'desc' => 'Vue quotidienne des membres : identité, grade, fonction, affectation, rôles et actions RH.',
        'domain' => 'Quotidien RH',
        'domainKey' => 'rh',
        'href' => function_exists('effectifs_workspace_url') ? effectifs_workspace_url() : url('back-office/ressources/effectifs'),
        'volume' => $fmtCount($membersActive, 'membre actif', 'membres actifs'),
        'cta' => 'Ouvrir le tableur',
        'primary' => true,
        'ok' => true,
    ],
    [
        'id' => 'structure',
        'title' => 'Structure et recrutement',
        'desc' => 'Organigramme des unités, invitations de membres, création de regroupements et d’équipes.',
        'domain' => 'Structure',
        'domainKey' => 'structure',
        'href' => url('back-office/organisation/structure'),
        'volume' => $fmtCount($groupsCount, 'regroupement', 'regroupements')
            . ' · '
            . $fmtCount($teamsCount, 'équipe', 'équipes'),
        'cta' => 'Ouvrir la structure',
        'primary' => false,
        'ok' => $canStructureRecruitmentHub,
    ],
    [
        'id' => 'groups',
        'title' => 'Unités et regroupements',
        'desc' => 'Gérez les regroupements affichés dans l’organigramme (compagnies, pelotons, etc.).',
        'domain' => 'Structure',
        'domainKey' => 'structure',
        'href' => url('back-office/groups'),
        'volume' => $fmtCount($groupsCount, 'regroupement', 'regroupements'),
        'cta' => 'Gérer les regroupements',
        'primary' => false,
        'ok' => $canStructure,
    ],
    [
        'id' => 'teams',
        'title' => 'Équipes',
        'desc' => 'Équipes transverses (missions, spécialités), indépendantes de l’organigramme principal.',
        'domain' => 'Structure',
        'domainKey' => 'structure',
        'href' => url('back-office/teams'),
        'volume' => $fmtCount($teamsCount, 'équipe', 'équipes'),
        'cta' => 'Gérer les équipes',
        'primary' => false,
        'ok' => $canStructure,
    ],
    [
        'id' => 'roles',
        'title' => 'Rôles et droits',
        'desc' => 'Rôles de gouvernance et opérationnels, avec le détail des habilitations associées.',
        'domain' => 'Rôles et droits',
        'domainKey' => 'roles',
        'href' => url('back-office/roles'),
        'volume' => $fmtCount($rolesCount, 'rôle', 'rôles'),
        'cta' => 'Voir les rôles',
        'primary' => false,
        'ok' => $canRolesList,
    ],
    [
        'id' => 'roles-canvas',
        'title' => 'Toile des rôles et fonctions',
        'desc' => 'Visualisez comment les rôles de la communauté se relient, à partir du référentiel des fonctions.',
        'domain' => 'Rôles et droits',
        'domainKey' => 'roles',
        'href' => url('back-office/roles-functions'),
        'volume' => 'Vue relationnelle',
        'cta' => 'Ouvrir la toile',
        'primary' => false,
        'ok' => $canRolesCanvas,
    ],
    [
        'id' => 'presets',
        'title' => 'Profils de permissions',
        'desc' => 'Appliquez en une fois un ensemble cohérent de droits sur un rôle existant.',
        'domain' => 'Rôles et droits',
        'domainKey' => 'roles',
        'href' => url('back-office/roles/presets'),
        'volume' => 'Modèles prêts à l’emploi',
        'cta' => 'Appliquer un profil',
        'primary' => false,
        'ok' => $canPresets,
    ],
    [
        'id' => 'grades',
        'title' => 'Référentiel des grades',
        'desc' => 'Grades communs (français, américains) utilisés sur les profils des membres.',
        'domain' => 'Référentiels',
        'domainKey' => 'refs',
        'href' => url('back-office/referentiels/grades'),
        'volume' => $fmtCount($gradesCount, 'grade', 'grades'),
        'cta' => 'Parcourir les grades',
        'primary' => false,
        'ok' => $canGrades,
    ],
    [
        'id' => 'job-roles',
        'title' => 'Fonctions métier',
        'desc' => 'Intitulés de fonction sur les dossiers (radio, médic, logistique…), distincts des rôles d’administration.',
        'domain' => 'Référentiels',
        'domainKey' => 'refs',
        'href' => url('back-office/personnel-job-roles'),
        'volume' => $fmtCount($jobRolesCount, 'fonction', 'fonctions'),
        'cta' => 'Gérer les fonctions',
        'primary' => false,
        'ok' => $canStructure,
    ],
    [
        'id' => 'job-assignments',
        'title' => 'Attributions des fonctions',
        'desc' => 'Associez les fonctions métier aux membres et contrôlez qui exerce quelles missions.',
        'domain' => 'Référentiels',
        'domainKey' => 'refs',
        'href' => url('back-office/personnel-job-roles/assignments'),
        'volume' => $fmtCount($jobRolesCount, 'fonction définie', 'fonctions définies'),
        'cta' => 'Attribuer',
        'primary' => false,
        'ok' => $canStructure,
    ],
    [
        'id' => 'seniority',
        'title' => 'Ancienneté affichée',
        'desc' => 'Indicateurs d’ancienneté sur les fiches personnel, installation du catalogue et mise à jour du personnel.',
        'domain' => 'Indicateurs',
        'domainKey' => 'indicateurs',
        'href' => url('back-office/organisation/anciennete'),
        'volume' => 'Fiches et indicateurs',
        'cta' => 'Configurer',
        'primary' => false,
        'ok' => $canSeniorityAdmin,
    ],
];

$visibleRows = array_values(array_filter($hubRows, static fn (array $r): bool => !empty($r['ok'])));

$domainOptions = [];
foreach ($visibleRows as $row) {
    $key = (string) $row['domainKey'];
    if ($key !== '' && !isset($domainOptions[$key])) {
        $domainOptions[$key] = (string) $row['domain'];
    }
}

$rowsForJs = [];
foreach ($visibleRows as $row) {
    $haySrc = $row['title'] . ' ' . $row['desc'] . ' ' . $row['domain'] . ' ' . $row['volume'] . ' ' . $row['cta'];
    $hay = function_exists('mb_strtolower')
        ? mb_strtolower($haySrc, 'UTF-8')
        : strtolower($haySrc);
    $rowsForJs[$row['id']] = [
        'id' => $row['id'],
        'domainKey' => $row['domainKey'],
        'hay' => $hay,
    ];
}

$rowsJson = json_encode(array_values($rowsForJs), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
if ($rowsJson === false) {
    $rowsJson = '[]';
}

$toolsCount = count($visibleRows);
?>
<?php if (is_file(base_path('public/assets/css/effectifs_lms.css'))): ?>
<link href="<?= htmlspecialchars(asset_url('assets/css/effectifs_lms.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php endif; ?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-effectifs-hub.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div
    class="bo-eff-hub"
    x-data="{
        q: '',
        domain: '',
        rows: <?= $rowsJson ?>,
        match(row) {
            const needle = (this.q || '').trim().toLowerCase();
            if (needle && !(row.hay || '').includes(needle)) return false;
            if (this.domain && row.domainKey !== this.domain) return false;
            return true;
        },
        get visibleCount() {
            return this.rows.filter((r) => this.match(r)).length;
        }
    }"
>
    <header class="bo-eff-hub__hero">
        <div class="bo-eff-hub__hero-inner">
            <div>
                <p class="bo-eff-hub__eyebrow">Communauté · Effectifs &amp; RH</p>
                <h1 class="bo-eff-hub__title">Structure &amp; grades</h1>
                <p class="bo-eff-hub__lead">
                    Vue d’ensemble non nominative pour <?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?> :
                    structure, rôles, référentiels et indicateurs utiles aux fiches personnel.
                    Pour le tableur nominatif (profils, statuts, élévations), direction le Bureau effectifs ci-contre.
                </p>
            </div>
            <div class="bo-eff-hub__hero-actions">
                <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="bo-eff-hub__btn bo-eff-hub__btn--ghost">Centre de pilotage</a>
                <a
                    href="<?= htmlspecialchars(function_exists('effectifs_workspace_url') ? effectifs_workspace_url() : url('back-office/ressources/effectifs'), ENT_QUOTES, 'UTF-8') ?>"
                    class="bo-eff-hub__btn bo-eff-hub__btn--solid"
                >Tableur des membres</a>
            </div>
        </div>
    </header>

    <div class="bo-eff-hub__deck">
        <div class="bo-eff-hub__kpi-grid" aria-label="Synthèse de la communauté">
            <div class="bo-eff-hub__kpi">
                <p class="bo-eff-hub__kpi-label">Membres actifs</p>
                <p class="bo-eff-hub__kpi-value"><?= $membersActive ?></p>
                <p class="bo-eff-hub__kpi-meta">Comptes en service</p>
            </div>
            <div class="bo-eff-hub__kpi">
                <p class="bo-eff-hub__kpi-label">Regroupements</p>
                <p class="bo-eff-hub__kpi-value"><?= $groupsCount ?></p>
                <p class="bo-eff-hub__kpi-meta"><?= $fmtCount($teamsCount, 'équipe', 'équipes') ?></p>
            </div>
            <div class="bo-eff-hub__kpi">
                <p class="bo-eff-hub__kpi-label">Rôles</p>
                <p class="bo-eff-hub__kpi-value"><?= $rolesCount ?></p>
                <p class="bo-eff-hub__kpi-meta">Gouvernance et opérations</p>
            </div>
            <div class="bo-eff-hub__kpi">
                <p class="bo-eff-hub__kpi-label">Outils accessibles</p>
                <p class="bo-eff-hub__kpi-value"><?= $toolsCount ?></p>
                <p class="bo-eff-hub__kpi-meta">Selon vos droits</p>
            </div>
        </div>

        <div class="eff-catalog">
            <div class="eff-catalog__head">
                <div class="min-w-0">
                    <p class="eff-catalog__kicker">Catalogue</p>
                    <h2 class="eff-catalog__title">Tableur d’organisation</h2>
                    <p class="eff-catalog__lead">
                        Filtrez par domaine ou recherchez un outil, puis ouvrez la page concernée.
                        Les volumes reflètent l’état actuel de la communauté.
                    </p>
                </div>
                <div class="eff-catalog__tools">
                    <span class="eff-catalog__btn" x-text="visibleCount + ' / <?= $toolsCount ?> affiché(s)'"></span>
                    <button
                        type="button"
                        class="eff-catalog__btn"
                        x-show="q !== '' || domain !== ''"
                        x-cloak
                        @click="q = ''; domain = ''"
                    >Réinitialiser</button>
                </div>
            </div>

            <div class="eff-catalog-filters">
                <div>
                    <label for="bo-eff-hub-q">Recherche</label>
                    <input
                        id="bo-eff-hub-q"
                        type="search"
                        x-model="q"
                        placeholder="Nom, domaine, description…"
                        autocomplete="off"
                    >
                </div>
                <div>
                    <label for="bo-eff-hub-domain">Domaine</label>
                    <select id="bo-eff-hub-domain" x-model="domain">
                        <option value="">Tous les domaines</option>
                        <?php foreach ($domainOptions as $domainKey => $domainLabel): ?>
                            <option value="<?= htmlspecialchars($domainKey, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($domainLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($visibleRows === []): ?>
                <div class="eff-catalog__empty">
                    <strong>Aucun outil disponible</strong>
                    Votre compte n’a pas encore les droits nécessaires pour l’organisation des effectifs.
                </div>
            <?php else: ?>
                <div
                    class="eff-catalog__empty"
                    x-show="visibleCount === 0"
                    x-cloak
                >
                    <strong>Aucun outil ne correspond</strong>
                    Élargissez la recherche ou changez de domaine.
                </div>

                <div
                    class="eff-sheets"
                    role="region"
                    aria-label="Tableur d’organisation des effectifs"
                    tabindex="0"
                    x-show="visibleCount > 0"
                >
                    <table class="eff-sheets__table">
                        <thead>
                            <tr>
                                <th scope="col">Outil</th>
                                <th scope="col">Domaine</th>
                                <th scope="col">Volume</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($visibleRows as $row):
                            $jsRow = $rowsForJs[$row['id']] ?? ['hay' => '', 'domainKey' => $row['domainKey']];
                            $filterPayload = htmlspecialchars(json_encode([
                                'hay' => $jsRow['hay'],
                                'domainKey' => $jsRow['domainKey'],
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            $volumeIsCount = preg_match('/^\d+\s/', $row['volume']) === 1;
                            ?>
                            <tr x-show="match(<?= $filterPayload ?>)">
                                <td data-label="Outil">
                                    <span class="eff-sheets__tool-name"><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="eff-sheets__tool-desc"><?= htmlspecialchars($row['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td data-label="Domaine">
                                    <span class="eff-sheets__badge eff-sheets__badge--scope"><?= htmlspecialchars($row['domain'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td data-label="Volume">
                                    <span class="eff-sheets__volume<?= $volumeIsCount ? '' : ' eff-sheets__volume--muted' ?>">
                                        <?= htmlspecialchars($row['volume'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="eff-sheets__actions">
                                        <a
                                            href="<?= htmlspecialchars($row['href'], ENT_QUOTES, 'UTF-8') ?>"
                                            class="<?= !empty($row['primary']) ? 'is-primary' : '' ?>"
                                        ><?= htmlspecialchars($row['cta'], ENT_QUOTES, 'UTF-8') ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="eff-catalog-foot">
                    <p style="margin:0;font-size:0.75rem;color:#64748b">
                        Les réglages réservés à l’ensemble de la plateforme restent dans l’administration système.
                    </p>
                    <div class="eff-catalog-foot__links">
                        <?php if ($canStructureRecruitmentHub): ?>
                            <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>">Structure</a>
                        <?php endif; ?>
                        <?php if ($canStructure): ?>
                            <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('back-office/groups'), ENT_QUOTES, 'UTF-8') ?>">Regroupements</a>
                            <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('back-office/teams'), ENT_QUOTES, 'UTF-8') ?>">Équipes</a>
                        <?php endif; ?>
                        <?php if ($canRolesList): ?>
                            <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>">Rôles</a>
                        <?php endif; ?>
                        <?php if ($canSeniorityAdmin): ?>
                            <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('back-office/organisation/anciennete'), ENT_QUOTES, 'UTF-8') ?>">Ancienneté</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
