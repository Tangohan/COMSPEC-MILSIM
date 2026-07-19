<?php
declare(strict_types=1);

/**
 * Navbar Athena (structure HTML proche Caverne) — dashboard / portail sombre.
 * Remplace portal-nav / dash-topnav ; ne remplace PAS l’aside tuiles ni les modals.
 *
 * Variables optionnelles (sinon dérivées du contexte session / dashboard) :
 * @var string|null $dashboard_tenant_label
 * @var array<string,mixed>|null $currentUser
 * @var array<string,mixed>|null $grade
 * @var array<string,mixed>|null $personnelExtras
 * @var array<string,mixed>|null $personnelProfile Dossier (fonction, unité d’affectation, rôle)
 * @var bool|null $show_staff_enlistments
 * @var string|null $athena_header_section  Sous-ligne brand (ex. Tableau de bord)
 * @var string|null $athena_header_current Clé lien actif (dashboard|hub|forum|formations|effectifs|recrutement|admin|…)
 */

$h = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$baseUrl = $baseUrl ?? url('');
$dashboardTenantLabel = $dashboard_tenant_label ?? null;
$unitLabel = ($dashboardTenantLabel !== null && $dashboardTenantLabel !== '')
    ? (string) $dashboardTenantLabel
    : '';
if ($unitLabel === '' && function_exists('portal_header_context')) {
    try {
        $phCtxTmp = portal_header_context();
        $unitLabel = (string) ($phCtxTmp['tenant_label'] ?? '');
    } catch (\Throwable) {
        $unitLabel = '';
    }
}
if ($unitLabel === '') {
    $unitLabel = 'Communauté';
}

$currentPath = function_exists('navigation_current_path') ? navigation_current_path() : '';
$currentKey = (string) ($athena_header_current ?? '');
if ($currentKey === '') {
    if ($currentPath === 'dashboard' || $currentPath === '') {
        $currentKey = 'dashboard';
    } elseif (str_starts_with($currentPath, 'hub')) {
        $currentKey = 'hub';
    } elseif (str_starts_with($currentPath, 'forum')) {
        $currentKey = 'forum';
    } elseif (str_starts_with($currentPath, 'formations')) {
        $currentKey = 'formations';
    } elseif (str_starts_with($currentPath, 'personnel') || str_starts_with($currentPath, 'orbat')) {
        $currentKey = 'effectifs';
    } elseif (
        str_starts_with($currentPath, 'back-office/ressources/recrutement')
        || str_starts_with($currentPath, 'back-office/recruitments')
        || str_starts_with($currentPath, 'back-office/recruitment')
    ) {
        $currentKey = 'recrutement';
    } elseif (str_starts_with($currentPath, 'back-office') || str_starts_with($currentPath, 'admin')) {
        $currentKey = 'admin';
    } elseif (str_starts_with($currentPath, 'evenements')) {
        $currentKey = 'events';
    }
}
$sectionLabelByKey = [
    'dashboard' => 'Tableau de bord',
    'hub' => 'Hub',
    'forum' => 'Forum',
    'formations' => 'Formations',
    'effectifs' => 'Effectifs',
    'recrutement' => 'Recrutement',
    'admin' => 'Administration',
    'events' => 'Manœuvres',
];
$sectionLabel = (string) ($athena_header_section ?? ($sectionLabelByKey[$currentKey] ?? 'Espace membre'));

$canAdmin = function_exists('can') && (can('admin.organization') || can('admin.access'));
$showStaff = !empty($show_staff_enlistments) || $canAdmin;
$canRecruit = $showStaff || (function_exists('can') && can('organization.recruitment.manage'));
$canDocsMenu = !function_exists('can') || can('documents.view');
$canInvitationsMenu = $canAdmin || (function_exists('can') && can('invitations.send'));
$recruitmentHref = function_exists('recruitment_workspace_url')
    ? recruitment_workspace_url()
    : url('back-office/ressources/recrutement');

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => url('dashboard')],
    ['key' => 'hub', 'label' => 'Hub', 'href' => url('hub')],
    ['key' => 'forum', 'label' => 'Forum', 'href' => url('forum')],
    ['key' => 'formations', 'label' => 'Formations', 'href' => url('formations')],
    ['key' => 'effectifs', 'label' => 'Effectifs', 'href' => url('personnel')],
];
if ($canRecruit) {
    $navItems[] = ['key' => 'recrutement', 'label' => 'Recrutement', 'href' => $recruitmentHref];
}
if ($canAdmin) {
    $navItems[] = ['key' => 'admin', 'label' => 'Administration', 'href' => url('back-office')];
}

$ctaHref = url('evenements');
$ctaLabel = 'Nouvelle manœuvre';
$ctaActive = $currentKey === 'events';

$espaceLinks = [
    ['abbr' => 'DOC', 'label' => 'Documents', 'desc' => 'Ordres et références', 'href' => url('documents')],
    ['abbr' => 'OPS', 'label' => 'Manœuvres', 'desc' => 'Calendrier opérationnel', 'href' => url('evenements')],
    ['abbr' => 'ORB', 'label' => 'ORBAT', 'desc' => 'Structure et effectifs', 'href' => url('orbat')],
    ['abbr' => 'ATK', 'label' => 'ATAK', 'desc' => 'Carte tactique', 'href' => url('atak')],
    ['abbr' => 'FRM', 'label' => 'Formations', 'desc' => 'Catalogue et parcours', 'href' => url('formations')],
];
if ($canRecruit) {
    $espaceLinks[] = [
        'abbr' => 'REC',
        'label' => 'Recrutement',
        'desc' => 'Dossiers et offres',
        'href' => $recruitmentHref,
    ];
}
if ($canAdmin) {
    $espaceLinks[] = [
        'abbr' => 'CMD',
        'label' => 'Commandement',
        'desc' => 'Espace état-major',
        'href' => url('back-office'),
    ];
}

$quickLinks = [
    ['label' => 'Tableau de bord', 'href' => url('dashboard')],
    ['label' => 'Hub', 'href' => url('hub')],
    ['label' => 'Forum', 'href' => url('forum')],
    ['label' => 'Formations', 'href' => url('formations')],
    ['label' => 'Effectifs', 'href' => url('personnel')],
    ['label' => 'Documents', 'href' => url('documents')],
    ['label' => 'ATAK', 'href' => url('atak')],
    ['label' => 'Manœuvres', 'href' => url('evenements')],
    ['label' => 'Ma fiche', 'href' => url('personnel/me')],
    ['label' => 'Compte', 'href' => url('account')],
];
if ($canRecruit) {
    $quickLinks[] = ['label' => 'Recrutement', 'href' => $recruitmentHref];
}
if ($canAdmin) {
    $quickLinks[] = ['label' => 'Administration', 'href' => url('back-office')];
}

$cu = $currentUser ?? null;
if (!is_array($cu)) {
    try {
        $cu = \App\Core\Container::get(\App\Services\Auth\AuthService::class)->user();
    } catch (\Throwable) {
        $cu = null;
    }
}
$displayName = $cu
    ? (string) ($cu['display_name'] ?? $cu['email'] ?? 'Opérateur')
    : trim((string) (\App\Core\Session::get('display_name') ?? \App\Core\Session::get('callsign') ?? 'Opérateur'));
if ($displayName === '') {
    $displayName = 'Opérateur';
}

$gradeLabel = 'Opérateur';
$gradeLong = 'Opérateur';
$gradeOtan = null;
$gr = $grade ?? null;
if (is_array($gr)) {
    $gradeLabel = (string) ($gr['label_short'] ?? $gr['short_name'] ?? $gr['label_long'] ?? $gr['name'] ?? 'Opérateur');
    $gradeLong = (string) ($gr['label_long'] ?? $gr['name'] ?? $gradeLabel);
    $otanRaw = trim((string) ($gr['label_otan'] ?? $gr['nato_code'] ?? ''));
    $gradeOtan = $otanRaw !== '' ? $otanRaw : null;
}

$pe = $personnelExtras ?? null;
$matricule = is_array($pe) ? ($pe['service_number'] ?? null) : null;
$matriculeLabel = $matricule ? ('Matricule ' . (string) $matricule) : 'Matricule non attribué';

$statut = $cu ? (string) ($cu['status'] ?? '') : (string) (\App\Core\Session::get('status') ?? '');
$statutLabel = match ($statut) {
    'active' => 'Opérationnel',
    'pending_verification' => 'Vérification e-mail',
    'inactive' => 'Inactif',
    'suspended' => 'Suspendu',
    default => 'Compte',
};

$athenaTenantIdForHeader = (int) (\App\Core\Session::get('tenant_id') ?? 0);

// Dossier personnel (fonction, unité d’affectation) : fourni par l’appelant, sinon dérivé pour rester
// utilisable depuis n’importe quelle page qui inclut ce partial (mêmes garanties que $grade ci-dessus).
$pp = $personnelProfile ?? null;
if (!is_array($pp) && is_array($cu)) {
    try {
        $pp = \App\Core\Container::get(\App\Repositories\PersonnelProfileRepository::class)->getByUserId((int) $cu['id']);
    } catch (\Throwable) {
        $pp = null;
    }
}

$fonctionLabel = null;
if (is_array($pp)) {
    $jobRoleId = (int) ($pp['personnel_job_role_id'] ?? 0);
    if ($jobRoleId > 0 && $athenaTenantIdForHeader > 0) {
        try {
            $jobRole = \App\Core\Container::get(\App\Repositories\PersonnelJobRoleRepository::class)
                ->findRoleById($jobRoleId, $athenaTenantIdForHeader);
            if ($jobRole) {
                $fonctionLabel = trim((string) ($jobRole['name'] ?? ''));
                $subLabel = trim((string) ($pp['role_sub_label'] ?? ''));
                if ($fonctionLabel !== '' && $subLabel !== '') {
                    $fonctionLabel .= ' — ' . $subLabel;
                }
            }
        } catch (\Throwable) {
            $fonctionLabel = null;
        }
    }
    if ($fonctionLabel === null || $fonctionLabel === '') {
        $freeRole = trim((string) ($pp['primary_role'] ?? ''));
        $fonctionLabel = $freeRole !== '' ? $freeRole : null;
    }
    if ($fonctionLabel === null || $fonctionLabel === '') {
        $rpFunction = trim((string) ($pp['rp_operational_function'] ?? ''));
        $fonctionLabel = $rpFunction !== '' ? $rpFunction : null;
    }
}

$affectationLabel = null;
if (is_array($pp) && !empty($pp['primary_unit_id']) && $athenaTenantIdForHeader > 0) {
    try {
        $unitRow = \App\Core\Container::get(\App\Repositories\UnitRepository::class)
            ->findById((int) $pp['primary_unit_id'], $athenaTenantIdForHeader);
        if ($unitRow) {
            $affectationLabel = trim((string) ($unitRow['name'] ?? '')) ?: null;
        }
    } catch (\Throwable) {
        $affectationLabel = null;
    }
}
if ($affectationLabel === null && is_array($pe)) {
    $squadron = trim((string) ($pe['squadron'] ?? ''));
    $affectationLabel = $squadron !== '' ? $squadron : null;
}

$athenaSessionId = is_array($cu) ? trim((string) ($cu['athena_identifier'] ?? '')) : '';

$initials = function_exists('user_display_initials')
    ? user_display_initials($displayName)
    : mb_strtoupper(mb_substr(preg_replace('/\s+/', '', $displayName) ?: 'A', 0, 1));
$avatarSrc = function_exists('user_media_public_url')
    ? user_media_public_url(is_array($cu) ? ($cu['avatar_url'] ?? null) : null)
    : null;
$bannerSrc = function_exists('user_media_public_url')
    ? user_media_public_url(is_array($cu) ? ($cu['profile_banner_url'] ?? null) : null)
    : null;

$alertsCtx = [
    'alerts' => [],
    'alerts_count' => 0,
    'alerts_severity' => 'info',
];
if (function_exists('portal_header_context')) {
    try {
        $ph = portal_header_context();
        $alertsCtx = [
            'alerts' => is_array($ph['alerts'] ?? null) ? $ph['alerts'] : [],
            'alerts_count' => (int) ($ph['alerts_count'] ?? 0),
            'alerts_severity' => (string) ($ph['alerts_severity'] ?? 'info'),
            'display_name' => (string) ($ph['display_name'] ?? $displayName),
            'role_label' => (string) ($ph['role_label'] ?? $gradeLabel),
            'tenant_label' => (string) ($ph['tenant_label'] ?? $unitLabel),
        ];
        if (($alertsCtx['display_name'] ?? '') !== '') {
            $displayName = (string) $alertsCtx['display_name'];
        }
    } catch (\Throwable) {
        // ignore
    }
}
$alertsCount = (int) ($alertsCtx['alerts_count'] ?? 0);
$alertsSeverity = (string) ($alertsCtx['alerts_severity'] ?? 'info');

/**
 * Informations affichées dans la grille du menu session (@see views/partials/athena_caverne_header.php).
 * @var list<array{label:string,value:string,otan?:string|null}>
 */
$profileFacts = [
    ['label' => 'Grade', 'value' => $gradeLong, 'otan' => $gradeOtan],
    ['label' => 'Fonction', 'value' => $fonctionLabel ?? 'Non renseignée'],
    ['label' => 'Matricule', 'value' => $matricule ? (string) $matricule : 'Non attribué'],
    ['label' => 'Statut', 'value' => $statutLabel],
    ['label' => 'Communauté', 'value' => $unitLabel],
];
if ($affectationLabel !== null && strcasecmp($affectationLabel, $unitLabel) !== 0) {
    $profileFacts[] = ['label' => 'Affectation', 'value' => $affectationLabel];
}

/**
 * Raccourcis du menu session : réunit les entrées personnelles (toujours visibles) et les
 * accès de gestion (affichés seulement si l’utilisateur en a le droit).
 * @var list<array{label:string,desc:string,href:string}>
 */
$profileMenuItems = [
    ['label' => 'Ma fiche', 'desc' => 'Identité, grade et fonction', 'href' => url('personnel/me')],
    ['label' => 'Mes formations', 'desc' => 'Parcours et compétences', 'href' => url('formations/mes-formations')],
    ['label' => 'Manœuvres', 'desc' => 'Calendrier opérationnel', 'href' => url('evenements')],
];
if ($canDocsMenu) {
    $profileMenuItems[] = ['label' => 'Documents', 'desc' => 'Ordres et références', 'href' => url('documents')];
}
if ($canInvitationsMenu) {
    $profileMenuItems[] = ['label' => 'Invitations', 'desc' => 'Codes d’accès à la communauté', 'href' => url('back-office/invitations/envoyees')];
}
if ($canAdmin) {
    $profileMenuItems[] = ['label' => 'Commandement', 'desc' => 'Espace état-major', 'href' => url('back-office')];
}
$profileMenuItems[] = ['label' => 'Paramètres', 'desc' => 'Compte et préférences', 'href' => url('account')];
$profileMenuItems[] = ['label' => 'Couverture', 'desc' => 'Bandeau du menu session', 'href' => url('account/banner')];
?>
<nav
    class="athena-header"
    role="navigation"
    aria-label="Navigation principale"
    data-athena-header
>
    <div class="athena-header__inner">
        <a href="<?= $h(url('dashboard')) ?>" class="athena-header__brand">
            <span class="athena-header__brand-mark" aria-hidden="true">A</span>
            <span class="athena-header__brand-text">
                <span class="athena-header__brand-title">Athena<span class="athena-header__brand-dot">.</span></span>
                <span class="athena-header__brand-sub"><?= $h($sectionLabel) ?> · <?= $h($unitLabel) ?></span>
            </span>
        </a>

        <div class="athena-header__nav-center">
            <?php foreach ($navItems as $index => $item): ?>
                <?php if ($index > 0): ?><span class="athena-header__sep" aria-hidden="true">/</span><?php endif; ?>
                <?php
                $isActive = ((string) ($item['key'] ?? '')) === $currentKey;
                $itemLabel = $h((string) ($item['label'] ?? ''));
                ?>
                <?php if ($isActive): ?>
                    <span class="athena-header__link athena-header__link--active" aria-current="page"><?= $itemLabel ?></span>
                <?php else: ?>
                    <a href="<?= $h((string) ($item['href'] ?? '#')) ?>" class="athena-header__link"><?= $itemLabel ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="athena-header__actions">
            <a
                href="<?= $h($ctaHref) ?>"
                class="athena-header__cta<?= $ctaActive ? ' is-active' : '' ?>"
                <?php if ($ctaActive): ?>aria-current="page"<?php endif; ?>
            >
                <svg class="athena-header__cta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/>
                </svg>
                <span class="athena-header__cta-label"><?= $h($ctaLabel) ?></span>
            </a>

            <div class="athena-header__menu relative hidden md:block">
                <button
                    type="button"
                    class="athena-header__menu-trigger"
                    data-athena-toggle="espaces"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="athena-header-espaces"
                >
                    Espaces
                    <svg class="athena-header__chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="m6 9 6 6 6-6"/>
                    </svg>
                </button>
                <div class="athena-header__panel athena-header__panel--espaces hidden" id="athena-header-espaces" data-athena-panel="espaces" role="region" aria-label="Espaces Athena">
                    <div class="athena-header__panel-head">
                        <p class="athena-header__kicker">Commandement</p>
                        <h3 class="athena-header__panel-title">Espaces</h3>
                    </div>
                    <div class="athena-header__espaces-grid">
                        <?php foreach ($espaceLinks as $link): ?>
                            <a href="<?= $h((string) $link['href']) ?>" class="athena-header__espace-item">
                                <span class="athena-header__espace-abbr"><?= $h((string) $link['abbr']) ?></span>
                                <span class="athena-header__espace-meta">
                                    <strong><?= $h((string) $link['label']) ?></strong>
                                    <em><?= $h((string) $link['desc']) ?></em>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="relative hidden md:block">
                <button
                    type="button"
                    class="athena-header__icon-btn"
                    data-athena-toggle="quick"
                    aria-label="Navigation rapide"
                    aria-expanded="false"
                    aria-controls="athena-header-quick"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <span class="athena-header__menu-label">Menu</span>
                </button>
                <div class="athena-header__panel athena-header__panel--quick hidden" id="athena-header-quick" data-athena-panel="quick">
                    <div class="athena-header__quick-head">
                        <strong>Navigation rapide</strong>
                        <span><?= count($quickLinks) ?></span>
                    </div>
                    <div class="athena-header__quick-grid">
                        <?php foreach ($quickLinks as $ql): ?>
                            <a href="<?= $h((string) $ql['href']) ?>"><?= $h((string) $ql['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="relative">
                <button
                    type="button"
                    class="athena-header__icon-btn"
                    data-athena-toggle="notif"
                    aria-label="Annonces et alertes"
                    aria-expanded="false"
                    aria-controls="athena-header-notif"
                    aria-haspopup="dialog"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0"/>
                    </svg>
                    <?php if ($alertsCount > 0): ?>
                        <span class="athena-header__dot<?= $alertsSeverity === 'urgent' ? ' athena-header__dot--urgent' : '' ?>"><?= (int) min(99, $alertsCount) ?></span>
                    <?php endif; ?>
                </button>
                <div
                    class="athena-header__panel athena-header__panel--notif hidden"
                    id="athena-header-notif"
                    data-athena-panel="notif"
                    role="dialog"
                    aria-label="Liste des annonces"
                >
                    <div class="athena-header__notif-card">
                        <div class="athena-header__notif-head">
                            <div>
                                <p>Centre d’alerte</p>
                                <h3>Annonces</h3>
                            </div>
                            <strong><?= (int) $alertsCount ?></strong>
                        </div>
                        <div class="athena-header__notif-list">
                            <?php if (($alertsCtx['alerts'] ?? []) === []): ?>
                                <div class="athena-header__notif-empty">Aucune annonce active.</div>
                            <?php else: ?>
                                <?php foreach ($alertsCtx['alerts'] as $a): ?>
                                    <?php if (!is_array($a)) {
                                        continue;
                                    } ?>
                                    <?php
                                    $aScope = (string) ($a['scope'] ?? '');
                                    $aKind = (string) ($a['kind'] ?? '');
                                    $aKindLabel = match ($aKind) {
                                        'urgent' => 'Urgent',
                                        'discount' => 'Offre',
                                        'novelty' => 'Nouveauté',
                                        'rappel' => 'Rappel',
                                        'info' => 'Info',
                                        default => $aKind !== '' ? $aKind : 'Info',
                                    };
                                    ?>
                                    <div class="athena-header__notif-item">
                                        <p class="athena-header__notif-meta"><?= $h($aScope !== '' ? $aScope . ' · ' . $aKindLabel : $aKindLabel) ?></p>
                                        <p class="athena-header__notif-title"><?= $h((string) ($a['title'] ?? '')) ?></p>
                                        <?php if (trim((string) ($a['body'] ?? '')) !== ''): ?>
                                            <p class="athena-header__notif-body"><?= $h((string) $a['body']) ?></p>
                                        <?php endif; ?>
                                        <?php if ((!empty($a['cta_label']) && !empty($a['cta_url'])) || (!empty($a['cta_secondary_label']) && !empty($a['cta_secondary_url']))): ?>
                                            <div class="athena-header__notif-actions">
                                                <?php if (!empty($a['cta_label']) && !empty($a['cta_url'])): ?>
                                                    <a href="<?= $h((string) $a['cta_url']) ?>" class="athena-header__notif-cta"><?= $h((string) $a['cta_label']) ?></a>
                                                <?php endif; ?>
                                                <?php if (!empty($a['cta_secondary_label']) && !empty($a['cta_secondary_url'])): ?>
                                                    <a href="<?= $h((string) $a['cta_secondary_url']) ?>" class="athena-header__notif-cta athena-header__notif-cta--secondary"><?= $h((string) $a['cta_secondary_label']) ?></a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative">
                <button
                    type="button"
                    class="athena-header__profile-trigger"
                    data-athena-toggle="profile"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="athena-header-profile"
                >
                    <span class="athena-header__profile-identity">
                        <span class="athena-header__profile-name"><?= $h($displayName) ?></span>
                        <span class="athena-header__profile-title">
                            <?= $h($gradeLabel) ?><?php if ($gradeOtan !== null): ?> · <?= $h($gradeOtan) ?><?php endif; ?>
                        </span>
                    </span>
                    <?php
                    $class = 'athena-header__avatar' . ($avatarSrc ? ' athena-header__avatar--photo' : '');
                    $imgClass = 'athena-header__avatar-img';
                    $alt = '';
                    require base_path('views/partials/ui/user_avatar.php');
                    ?>
                    <svg class="athena-header__chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="m6 9 6 6 6-6"/>
                    </svg>
                </button>
                <div class="athena-header__panel athena-header__panel--profile hidden" id="athena-header-profile" data-athena-panel="profile">
                    <div class="athena-header__profile-card">
                        <div class="athena-header__profile-cover<?= $bannerSrc ? ' athena-header__profile-cover--photo' : '' ?>">
                            <?php if ($bannerSrc): ?>
                                <img
                                    src="<?= $h($bannerSrc) ?>"
                                    alt=""
                                    class="athena-header__profile-cover-img"
                                    loading="lazy"
                                    decoding="async"
                                >
                            <?php endif; ?>
                            <div class="athena-header__profile-cover-shade"></div>
                            <div class="athena-header__profile-topline">
                                <span>Session active</span>
                                <b><?= $h($displayName) ?></b>
                            </div>
                        </div>
                        <div class="athena-header__profile-body">
                            <div class="athena-header__profile-identity-block">
                                <?php
                                $class = 'athena-header__profile-avatar-lg' . ($avatarSrc ? ' athena-header__profile-avatar-lg--photo' : '');
                                $imgClass = 'athena-header__avatar-img';
                                $alt = '';
                                require base_path('views/partials/ui/user_avatar.php');
                                ?>
                                <div class="athena-header__profile-name-block">
                                    <strong><?= $h($displayName) ?></strong>
                                    <span>
                                        <?= $h($gradeLong) ?><?php if ($gradeOtan !== null): ?> · <?= $h($gradeOtan) ?><?php endif; ?>
                                        · <?= $h($matriculeLabel) ?>
                                    </span>
                                </div>
                                <span class="athena-header__profile-status"><?= $h($statutLabel) ?></span>
                            </div>

                            <dl class="athena-header__profile-facts">
                                <?php foreach ($profileFacts as $fact): ?>
                                    <div class="athena-header__profile-fact">
                                        <dt><?= $h((string) $fact['label']) ?></dt>
                                        <dd>
                                            <?= $h((string) $fact['value']) ?>
                                            <?php if (!empty($fact['otan'])): ?>
                                                <span class="athena-header__profile-otan"><?= $h((string) $fact['otan']) ?></span>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>

                            <?php if ($athenaSessionId !== ''): ?>
                                <p class="athena-header__profile-sessionid" title="Identifiant discret de votre session Athena">
                                    Session <?= $h($athenaSessionId) ?>
                                </p>
                            <?php endif; ?>

                            <div class="athena-header__profile-menu">
                                <?php foreach ($profileMenuItems as $item): ?>
                                    <a href="<?= $h((string) $item['href']) ?>"><span><?= $h((string) $item['label']) ?></span><em><?= $h((string) $item['desc']) ?></em></a>
                                <?php endforeach; ?>
                                <form method="post" action="<?= $h(rtrim((string) $baseUrl, '/') . '/logout') ?>" class="athena-header__logout-form">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="danger"><span>Déconnexion</span><em>Fermer la session</em></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
