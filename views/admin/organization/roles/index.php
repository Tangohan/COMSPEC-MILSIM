<?php
declare(strict_types=1);

$roles = is_array($roles ?? null) ? $roles : [];
$roleViewSections = is_array($roleViewSections ?? null) ? $roleViewSections : [];
$permissionCounts = is_array($permissionCounts ?? null) ? $permissionCounts : [];
$memberCounts = is_array($memberCounts ?? null) ? $memberCounts : [];
$roleLayerFilter = (string) ($roleLayerFilter ?? '');
$roleTierFilter = (string) ($roleTierFilter ?? '');
$base = url('back-office/roles');
$__g = \App\Core\Gate::getInstance();
$canPresets = $__g->allows('admin.organization') || $__g->allows('admin.roles.manage') || $__g->allows('admin.permissions.manage');

$successFlash = \App\Core\Session::getFlash('success');
$errorFlash = \App\Core\Session::getFlash('error');

$tierMeta = static function (string $t): array {
    return match ($t) {
        'authority' => ['label' => 'Commandement', 'class' => 'bo-roles__badge--tier-authority'],
        'function' => ['label' => 'Emploi', 'class' => 'bo-roles__badge--tier-function'],
        'liaison' => ['label' => 'Liaison', 'class' => 'bo-roles__badge--tier-liaison'],
        'support' => ['label' => 'Soutien', 'class' => 'bo-roles__badge--tier-support'],
        'specialty' => ['label' => 'Spécialité', 'class' => 'bo-roles__badge--tier-specialty'],
        'status' => ['label' => 'Statut affiché', 'class' => 'bo-roles__badge--tier-status'],
        default => ['label' => 'Emploi', 'class' => 'bo-roles__badge--tier-function'],
    };
};

$scopeMeta = static function (string $layer): array {
    return $layer === 'community'
        ? ['label' => 'Gouvernance communauté', 'class' => 'bo-roles__badge--scope-community', 'short' => 'Communauté']
        : ['label' => 'Rôle opérationnel', 'class' => 'bo-roles__badge--scope-unit', 'short' => 'Unité'];
};

$rightsBadgeClass = static function (int $count): string {
    if ($count <= 0) {
        return 'bo-roles__badge--rights-empty';
    }
    if ($count <= 5) {
        return 'bo-roles__badge--rights-low';
    }
    if ($count <= 20) {
        return 'bo-roles__badge--rights-mid';
    }

    return 'bo-roles__badge--rights-high';
};

$appendQuery = static function (string $baseUrl, array $params): string {
    $q = http_build_query(array_filter($params, static fn ($v) => $v !== null && $v !== ''));

    return $q === '' ? $baseUrl : $baseUrl . '?' . $q;
};

$rolesCount = count($roles);
$withRights = 0;
$withMembers = 0;
$totalRights = 0;
$totalMembers = 0;
foreach ($roles as $r) {
    $rid = (int) ($r['id'] ?? 0);
    $pc = (int) ($permissionCounts[$rid] ?? 0);
    $mc = (int) ($memberCounts[$rid] ?? 0);
    $totalRights += $pc;
    $totalMembers += $mc;
    if ($pc > 0) {
        $withRights++;
    }
    if ($mc > 0) {
        $withMembers++;
    }
}

$layerTousParams = [];
if ($roleTierFilter !== '') {
    $layerTousParams['tier'] = $roleTierFilter;
}

$tierLinks = [
    '' => 'Tous les types',
    'authority' => 'Commandement',
    'function' => 'Emploi',
    'liaison' => 'Liaison',
    'support' => 'Soutien',
    'specialty' => 'Spécialité',
    'status' => 'Statut affiché',
];
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-roles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div
    class="bo-roles"
    x-data="{
        q: '',
        match(hay) {
            const needle = (this.q || '').trim().toLowerCase();
            if (!needle) return true;
            return (hay || '').toLowerCase().includes(needle);
        }
    }"
>
    <header class="bo-roles__hero">
        <div class="bo-roles__hero-inner">
            <div>
                <p class="bo-roles__eyebrow">Communauté · Habilitations</p>
                <h1 class="bo-roles__title">Rôles communauté</h1>
                <p class="bo-roles__lead">
                    Table des rôles de votre communauté : type, périmètre, droits accordés et membres concernés.
                    Les habilitations réservées à l’ensemble du site restent gérées par l’administration plateforme.
                </p>
            </div>
            <div class="bo-roles__hero-actions">
                <?php if ($canPresets): ?>
                <a href="<?= htmlspecialchars(url('back-office/roles/presets'), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__btn bo-roles__btn--ghost">Profils prêts</a>
                <a href="<?= htmlspecialchars(url('back-office/positions'), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__btn bo-roles__btn--ghost">Postes organisationnels</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(url('back-office/access-management'), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__btn bo-roles__btn--ghost">Gestion des accès</a>
                <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__btn bo-roles__btn--solid">Centre de pilotage</a>
            </div>
        </div>
    </header>

    <div class="bo-roles__deck">
        <?php if ($successFlash): ?>
            <div class="bo-roles__flash bo-roles__flash--ok" role="status"><?= htmlspecialchars((string) $successFlash, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($errorFlash): ?>
            <div class="bo-roles__flash bo-roles__flash--err" role="alert"><?= htmlspecialchars((string) $errorFlash, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="bo-roles__kpi-grid" aria-label="Synthèse des rôles">
            <div class="bo-roles__kpi">
                <p class="bo-roles__kpi-label">Rôles affichés</p>
                <p class="bo-roles__kpi-value"><?= $rolesCount ?></p>
                <p class="bo-roles__kpi-meta">Selon les filtres actifs</p>
            </div>
            <div class="bo-roles__kpi">
                <p class="bo-roles__kpi-label">Avec droits</p>
                <p class="bo-roles__kpi-value"><?= $withRights ?></p>
                <p class="bo-roles__kpi-meta"><?= $totalRights ?> habilitation<?= $totalRights > 1 ? 's' : '' ?> au total</p>
            </div>
            <div class="bo-roles__kpi">
                <p class="bo-roles__kpi-label">Avec membres</p>
                <p class="bo-roles__kpi-value"><?= $withMembers ?></p>
                <p class="bo-roles__kpi-meta"><?= $totalMembers ?> affectation<?= $totalMembers > 1 ? 's' : '' ?> (cumul)</p>
            </div>
            <div class="bo-roles__kpi">
                <p class="bo-roles__kpi-label">Périmètre</p>
                <p class="bo-roles__kpi-value" style="font-size:1.05rem;line-height:1.35;margin-top:0.55rem;">
                    <?php
                    $scopeLabel = match ($roleLayerFilter) {
                        'community' => 'Gouvernance',
                        'intra' => 'Opérationnel',
                        default => 'Tous',
                    };
                    echo htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8');
                    ?>
                </p>
                <p class="bo-roles__kpi-meta">
                    <?php
                    $tierLabel = $tierLinks[$roleTierFilter] ?? 'Tous les types';
                    echo htmlspecialchars($tierLabel, ENT_QUOTES, 'UTF-8');
                    ?>
                </p>
            </div>
        </div>

        <section class="bo-roles__panel" aria-labelledby="bo-roles-sheet-title">
            <div class="bo-roles__panel-head">
                <h2 id="bo-roles-sheet-title">Table des rôles</h2>
                <p>
                    Liste structurée par famille opérationnelle. Le nombre de droits indique combien d’habilitations sont actives pour chaque rôle.
                    <?php if ($canPresets): ?>
                    Utilisez <a href="<?= htmlspecialchars(url('back-office/roles/presets'), ENT_QUOTES, 'UTF-8') ?>">Profils prêts</a> pour harmoniser, ou ouvrez un rôle pour un réglage précis.
                    <?php endif; ?>
                </p>
            </div>

            <div class="bo-roles__toolbar">
                <div class="bo-roles__filters">
                    <div class="bo-roles__filter-row">
                        <span class="bo-roles__filter-label">Périmètre</span>
                        <a href="<?= htmlspecialchars($appendQuery($base, $layerTousParams), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__chip<?= $roleLayerFilter === '' ? ' is-active' : '' ?>">Tous</a>
                        <a href="<?= htmlspecialchars($appendQuery($base, ['layer' => 'community', 'tier' => $roleTierFilter === '' ? null : $roleTierFilter]), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__chip<?= $roleLayerFilter === 'community' ? ' is-active' : '' ?>">Gouvernance communauté</a>
                        <a href="<?= htmlspecialchars($appendQuery($base, ['layer' => 'intra', 'tier' => $roleTierFilter === '' ? null : $roleTierFilter]), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__chip<?= $roleLayerFilter === 'intra' ? ' is-active' : '' ?>">Rôles opérationnels</a>
                    </div>
                    <div class="bo-roles__filter-row">
                        <span class="bo-roles__filter-label">Type</span>
                        <?php foreach ($tierLinks as $tv => $tlab):
                            $active = $roleTierFilter === $tv;
                            $params = ['tier' => $tv === '' ? null : $tv];
                            if ($roleLayerFilter !== '') {
                                $params['layer'] = $roleLayerFilter;
                            }
                        ?>
                        <a href="<?= htmlspecialchars($appendQuery($base, $params), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__chip<?= $active ? ' is-active-soft' : '' ?>"><?= htmlspecialchars($tlab, ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bo-roles__search">
                    <label for="bo-roles-search">Rechercher</label>
                    <input
                        id="bo-roles-search"
                        type="search"
                        x-model="q"
                        placeholder="Nom, type, périmètre…"
                        autocomplete="off"
                    >
                </div>
            </div>

            <?php if ($roles === []): ?>
                <div class="bo-roles__empty">
                    <div class="bo-roles__empty-icon" aria-hidden="true">∅</div>
                    <p>Aucun rôle ne correspond à ces filtres</p>
                    <span>Élargissez le périmètre ou le type, ou créez un rôle depuis la gestion des accès.</span>
                </div>
            <?php else: ?>
                <div class="bo-roles__sheet-wrap">
                    <table class="bo-roles__sheet">
                        <thead>
                            <tr>
                                <th scope="col">Rôle</th>
                                <th scope="col">Type</th>
                                <th scope="col">Périmètre</th>
                                <th scope="col" class="bo-roles__col-num">Droits</th>
                                <th scope="col" class="bo-roles__col-num">Membres</th>
                                <th scope="col" class="bo-roles__col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roleViewSections as $section):
                                $secRoles = $section['roles'] ?? [];
                                if (!is_array($secRoles) || $secRoles === []) {
                                    continue;
                                }
                                $secTitle = (string) ($section['title'] ?? '');
                                $secCount = count($secRoles);
                                $sectionHay = $secTitle;
                                foreach ($secRoles as $sr) {
                                    $stier = (string) ($sr['semantic_tier'] ?? 'function');
                                    $slayer = (string) ($sr['role_layer'] ?? 'community');
                                    $sectionHay .= ' ' . (string) ($sr['name'] ?? '')
                                        . ' ' . (string) ($sr['description'] ?? '')
                                        . ' ' . (string) ($sr['label_en'] ?? '')
                                        . ' ' . $tierMeta($stier)['label']
                                        . ' ' . $scopeMeta($slayer)['label']
                                        . ' ' . $scopeMeta($slayer)['short'];
                                }
                            ?>
                            <tr class="bo-roles__group-row" x-show="match(<?= htmlspecialchars(json_encode($sectionHay, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">
                                <td colspan="6">
                                    <p class="bo-roles__group-title"><?= htmlspecialchars($secTitle, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="bo-roles__group-meta"><?= $secCount ?> rôle<?= $secCount > 1 ? 's' : '' ?> dans cette famille</p>
                                </td>
                            </tr>
                            <?php foreach ($secRoles as $r):
                                $rid = (int) ($r['id'] ?? 0);
                                $count = (int) ($permissionCounts[$rid] ?? 0);
                                $members = (int) ($memberCounts[$rid] ?? 0);
                                $layer = (string) ($r['role_layer'] ?? 'community');
                                $stier = (string) ($r['semantic_tier'] ?? 'function');
                                $tier = $tierMeta($stier);
                                $scope = $scopeMeta($layer);
                                $rowLocked = (int) ($r['is_locked'] ?? 0) !== 0;
                                $name = (string) ($r['name'] ?? '');
                                $desc = trim((string) ($r['description'] ?? ''));
                                $labelEn = trim((string) ($r['label_en'] ?? ''));
                                $hay = $name . ' ' . $desc . ' ' . $labelEn . ' ' . $tier['label'] . ' ' . $scope['label'] . ' ' . $scope['short'] . ' ' . $secTitle;
                                $hayJson = htmlspecialchars(json_encode($hay, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="<?= $rowLocked ? 'is-locked' : '' ?>" x-show="match(<?= $hayJson ?>)">
                                <td class="bo-roles__col-role" data-label="Rôle">
                                    <span class="bo-roles__role-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($labelEn !== ''): ?>
                                    <span class="bo-roles__role-desc"><?= htmlspecialchars($labelEn, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php if ($desc !== ''): ?>
                                    <span class="bo-roles__role-desc"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php if ($rowLocked): ?>
                                    <div class="bo-roles__role-tags">
                                        <span class="bo-roles__badge bo-roles__badge--locked">Verrouillé</span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Type">
                                    <span class="bo-roles__badge <?= htmlspecialchars($tier['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tier['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td data-label="Périmètre">
                                    <span class="bo-roles__badge <?= htmlspecialchars($scope['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($scope['short'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="bo-roles__col-num" data-label="Droits">
                                    <?php if ($count <= 0): ?>
                                    <span class="bo-roles__badge <?= $rightsBadgeClass(0) ?>">Aucun droit</span>
                                    <?php else: ?>
                                    <div class="bo-roles__metric">
                                        <span class="bo-roles__metric-value"><?= $count ?></span>
                                        <span class="bo-roles__badge <?= $rightsBadgeClass($count) ?>"><?= $count === 1 ? 'droit actif' : 'droits actifs' ?></span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="bo-roles__col-num" data-label="Membres">
                                    <div class="bo-roles__metric">
                                        <span class="bo-roles__metric-value"><?= $members ?></span>
                                        <span class="bo-roles__metric-label"><?= $members === 1 ? 'membre' : 'membres' ?></span>
                                    </div>
                                </td>
                                <td class="bo-roles__col-actions" data-label="Actions">
                                    <div class="bo-roles__actions">
                                        <a href="<?= htmlspecialchars(url('back-office/roles/' . $rid), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__btn bo-roles__btn--link">Fiche</a>
                                        <?php if ($canPresets && !$rowLocked): ?>
                                        <a href="<?= htmlspecialchars(url('back-office/roles/' . $rid . '/permissions'), ENT_QUOTES, 'UTF-8') ?>" class="bo-roles__btn bo-roles__btn--link">Habilitations</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <p class="bo-roles__hint">
            L’ordre reflète une hiérarchie d’emplois, pas un grade automatique.
            Pour créer un nouveau rôle, passez par la <a href="<?= htmlspecialchars(url('back-office/access-management?tab=roles'), ENT_QUOTES, 'UTF-8') ?>">gestion des accès</a>.
        </p>
    </div>
</div>
