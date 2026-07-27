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

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$domainBadge = static function (string $key): array {
    return match ($key) {
        'rh' => ['fg' => '#0b8a5c', 'bg' => '#ecfdf5', 'bd' => '#a7f3d0'],
        'structure' => ['fg' => '#1e4f80', 'bg' => '#eff6ff', 'bd' => '#bfdbfe'],
        'roles' => ['fg' => '#6d28d9', 'bg' => '#f5f3ff', 'bd' => '#ddd6fe'],
        'refs' => ['fg' => '#c98a12', 'bg' => '#fffbeb', 'bd' => '#fde68a'],
        'indicateurs' => ['fg' => '#475569', 'bg' => '#f8fafc', 'bd' => '#e2e8f0'],
        default => ['fg' => '#3c474c', 'bg' => '#f6f8f9', 'bd' => '#e2e8f0'],
    };
};

$effectifsUrl = function_exists('effectifs_workspace_url')
    ? effectifs_workspace_url()
    : url('back-office/ressources/effectifs');

$athKpis = [
    ['label' => 'MEMBRES ACTIFS', 'value' => (string) $membersActive, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $membersActive > 0 ? '100%' : '0%', 'note' => 'comptes en service'],
    ['label' => 'REGROUPEMENTS', 'value' => (string) $groupsCount, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $groupsCount > 0 ? '100%' : '0%', 'note' => $fmtCount($teamsCount, 'équipe', 'équipes')],
    ['label' => 'RÔLES', 'value' => (string) $rolesCount, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '—', 'note' => 'gouvernance et opérations'],
    ['label' => 'OUTILS', 'value' => (string) $toolsCount, 'delta' => '', 'tone' => '#c98a12', 'pct' => '—', 'note' => 'selon vos droits'],
];
?>
<div
    class="bo-eff-hub ath-dash-page"
    x-data="boEffHub(<?= $h($rowsJson) ?>)"
>
    <?php require base_path('views/partials/ath_kpis.php'); ?>

    <nav class="bo-eff-hub__shortcuts ath-rise" aria-label="Accès rapide">
        <span class="bo-eff-hub__shortcuts-label">Accès rapide</span>
        <a href="<?= $h($effectifsUrl) ?>" class="ath-btn ath-btn--solid">Tableur des membres</a>
        <?php if ($canStructureRecruitmentHub): ?>
            <a href="<?= $h(url('back-office/organisation/structure')) ?>" class="ath-btn">Structure</a>
        <?php endif; ?>
        <?php if ($canStructure): ?>
            <a href="<?= $h(url('back-office/groups')) ?>" class="ath-btn">Regroupements</a>
            <a href="<?= $h(url('back-office/teams')) ?>" class="ath-btn">Équipes</a>
        <?php endif; ?>
        <?php if ($canRolesList): ?>
            <a href="<?= $h(url('back-office/roles')) ?>" class="ath-btn">Rôles</a>
        <?php endif; ?>
        <?php if ($canGrades): ?>
            <a href="<?= $h(url('back-office/referentiels/grades')) ?>" class="ath-btn">Grades</a>
        <?php endif; ?>
        <?php if ($canSeniorityAdmin): ?>
            <a href="<?= $h(url('back-office/organisation/anciennete')) ?>" class="ath-btn">Ancienneté</a>
        <?php endif; ?>
    </nav>

    <div class="ath-table-panel ath-rise">
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title">Catalogue des outils</span>
            <span class="ath-table-toolbar__count" x-text="visibleCount + ' / <?= $toolsCount ?> affiché(s)'"></span>
            <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
            <label class="ath-table-toolbar__search">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#8c979b" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
                <input
                    id="bo-eff-hub-q"
                    type="search"
                    x-model="q"
                    placeholder="Rechercher un outil…"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="Rechercher dans le catalogue"
                >
            </label>
            <label class="bo-eff-hub__domain-filter">
                <span class="visually-hidden">Filtrer par domaine</span>
                <select id="bo-eff-hub-domain" x-model="domain" aria-label="Filtrer par domaine">
                    <option value="">Tous les domaines</option>
                    <?php foreach ($domainOptions as $domainKey => $domainLabel): ?>
                        <option value="<?= $h($domainKey) ?>"><?= $h($domainLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button
                type="button"
                class="ath-btn"
                x-show="q !== '' || domain !== ''"
                x-cloak
                @click="q = ''; domain = ''"
            >Réinitialiser</button>
        </div>

        <?php if ($visibleRows === []): ?>
            <div class="ath-table-empty bo-eff-hub__empty-state">
                <strong>Aucun outil disponible</strong>
                <p>Votre compte n’a pas encore les droits nécessaires pour l’organisation des effectifs.</p>
            </div>
        <?php else: ?>
            <div
                class="ath-table-empty bo-eff-hub__empty-state"
                x-show="visibleCount === 0"
                x-cloak
            >
                <strong>Aucun outil ne correspond</strong>
                <p>Élargissez la recherche ou changez de domaine.</p>
            </div>

            <div class="ath-table-wrap" x-show="visibleCount > 0">
                <table class="ath-table bo-eff-hub__table">
                    <colgroup>
                        <col class="bo-eff-hub__col-tool">
                        <col class="bo-eff-hub__col-domain">
                        <col class="bo-eff-hub__col-volume">
                        <col class="bo-eff-hub__col-action">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Outil</th>
                            <th scope="col">Domaine</th>
                            <th scope="col">Volume</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visibleRows as $row):
                        $badge = $domainBadge((string) $row['domainKey']);
                        $volumeIsCount = preg_match('/^\d+\s/', $row['volume']) === 1;
                        ?>
                        <tr x-show="matchById('<?= $h((string) $row['id']) ?>')">
                            <td data-label="Outil">
                                <div class="bo-eff-hub__tool">
                                    <span class="bo-eff-hub__tool-name"><?= $h($row['title']) ?></span>
                                    <span class="bo-eff-hub__tool-desc"><?= $h($row['desc']) ?></span>
                                </div>
                            </td>
                            <td data-label="Domaine">
                                <span
                                    class="ath-cell ath-cell--badge"
                                    style="color:<?= $h($badge['fg']) ?>;background:<?= $h($badge['bg']) ?>;border-color:<?= $h($badge['bd']) ?>"
                                ><?= $h($row['domain']) ?></span>
                            </td>
                            <td data-label="Volume" class="<?= $volumeIsCount ? 'ath-td-num' : '' ?>">
                                <span class="bo-eff-hub__volume<?= $volumeIsCount ? '' : ' bo-eff-hub__volume--muted' ?>">
                                    <?= $h($row['volume']) ?>
                                </span>
                            </td>
                            <td data-label="Action">
                                <a href="<?= $h($row['href']) ?>" class="ath-btn"><?= $h($row['cta']) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="ath-table-foot">
                <div class="ath-table-foot__meta">
                    Les réglages réservés à l’ensemble de la plateforme restent dans l’administration système.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
function boEffHub(rows) {
    return {
        q: '',
        domain: '',
        rows: Array.isArray(rows) ? rows : [],
        match: function (row) {
            var needle = (this.q || '').trim().toLowerCase();
            if (needle && !(row.hay || '').includes(needle)) return false;
            if (this.domain && row.domainKey !== this.domain) return false;
            return true;
        },
        matchById: function (id) {
            var row = this.rows.find(function (r) { return r.id === id; });
            return row ? this.match(row) : false;
        },
        get visibleCount() {
            var self = this;
            return this.rows.filter(function (r) { return self.match(r); }).length;
        }
    };
}
</script>
