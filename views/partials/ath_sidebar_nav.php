<?php
declare(strict_types=1);

/**
 * Navigation ATHENA — structure et rendu alignés sur Back-Office.dc.html (nav()).
 *
 * @var callable(string): string $h
 * @var callable(string): bool $boHrefAllowed
 */

$athIcoPaths = [
    'dash' => 'M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z',
    'wall' => 'M4 5h16v6H4zM4 14h7v5H4zM13 14h7v5h-7z',
    'cal' => 'M4 5h16v16H4zM8 3v4M16 3v4M4 10h16',
    'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8M22 21v-2a4 4 0 0 0-3-3.9',
    'orbat' => 'M12 3v4M6 21v-4M18 21v-4M4 7h16M6 17h12M12 7v10',
    'book' => 'M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2zM4 18h15',
    'ops' => 'M12 2v3M12 19v3M2 12h3M19 12h3M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10',
    'rsvp' => 'M4 5h16v14H4zM8 3v4M16 3v4M9 13l2 2 4-4',
    'aar' => 'M8 3h8l4 4v14H4V3zM9 12h6M9 16h4',
    'radio' => 'M12 12a2 2 0 1 0 .01 0M7.8 7.8a6 6 0 0 0 0 8.5M16.2 7.8a6 6 0 0 1 0 8.5M4.9 4.9a10 10 0 0 0 0 14.2M19.1 4.9a10 10 0 0 1 0 14.2',
    'phone' => 'M7 2h10v20H7zM11 18h2',
    'cert' => 'M12 3l7 4v6c0 4-3 6.5-7 8-4-1.5-7-4-7-8V7zM9 12l2 2 4-4',
    'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    'audit' => 'M9 3h6M4 6h16v15H4zM8 11h8M8 15h5',
    'shield' => 'M12 3l8 4v6c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V7z',
    'plug' => 'M9 2v6M15 2v6M6 8h12v4a6 6 0 0 1-12 0zM12 18v4',
    'gear' => 'M12 9a3 3 0 1 0 .01 0M20 12l2-1-2-3.5-2.3.6a6 6 0 0 0-1.6-.9L15.5 5h-4l-.6 2.2a6 6 0 0 0-1.6.9L7 7.5 5 11l2 1-2 1 2 3.5 2.3-.6c.5.4 1 .7 1.6.9l.6 2.2h4l.6-2.2c.6-.2 1.1-.5 1.6-.9l2.3.6L22 13z',
    'rocket' => 'M12 2c3 2 5 5.5 5 9.5L12 16l-5-4.5C7 7.5 9 4 12 2M9 17l-2 4 5-2 5 2-2-4',
    'home' => 'M4 11l8-7 8 7v9H4zM10 20v-6h4v6',
    'path' => 'M6 3v6a4 4 0 0 0 4 4h4a4 4 0 0 1 4 4v4M6 3a2 2 0 1 0 .01 0M18 21a2 2 0 1 0 .01 0',
    'mail' => 'M3 6h18v12H3zM3 7l9 6 9-6',
    'roleplay' => 'M12 3c3 0 5 2 5 5 0 2-1 3.5-2.5 4.5L12 21l-2.5-8.5C8 11.5 7 10 7 8c0-3 2-5 5-5z',
];

$athIco = static function (string $key) use ($athIcoPaths, $h): string {
    $d = $athIcoPaths[$key] ?? '';
    if ($d === '') {
        return '';
    }

    return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'
        . $h($d) . '"></path></svg>';
};

$navMembersActive = $boNavUsers;
$navRecruesActive = $boNavRec && !$boNavRecSettings && !$boNavRecMessages;
$navSanctionsActive = $boNavMod;
$navRoleplayActive = $boNavRoleplayFollowup;
$navRoleplayDeadlinesActive = !empty($boNavRoleplayDeadlines);
$navRoleplayImmersionActive = $boNavRoleplayImmersion;
$navRoleplaySectionActive = $boNavRoleplaySection;
$navOrbatActive = $boNavEff || $boNavEffWorkspace;
$navDoctrineActive = $boNavRolesFx;
$navAttributionsActive = $boNavPjrAssignments || ($boNavPjr && !$boNavPjrAssignments);
$navFormationsActive = $boNavLmsRes || $boNavLmsSubPage;
$navCommunityActive = $boNavOrgSettings;
$navPublicPageActive = $boNavCommPres;
$navInscriptionActive = $boNavCommInscription;
$navMediasActive = $boNavMedia;
$navAtakHubActive = $p === 'back-office/atak';
$navAtakDevicesActive = str_starts_with($p, 'back-office/atak/realisme');
$navAtakCertsActive = str_starts_with($p, 'back-office/atak/certificats');
$navAtakSessionsActive = $boNavAtakOperators;
$navAtakOpActive = str_starts_with($p, 'back-office/atak/fiche-operateur');
$navRolesActive = $boNavRolesPermissions;
$navRolesTableActive = $boNavRoles;
$navProfilsActive = $boNavRolesPresets;
$navRsvpActive = $boNavEvents && !$boNavEventInsights;
$navRsvpHistActive = $boNavEventInsights;
$boNavPlanning = !empty($boNavPlanning);
$boNavMissionsPortal = !empty($boNavMissionsPortal);

$recBadgeStr = !empty($boBadges['show_staff_recruitment']) && $boRecN > 0
    ? ($boRecN > 99 ? '99+' : (string) $boRecN)
    : null;

$membersChildren = array_values(array_filter([
    ['label' => 'Annuaire complet', 'href' => url('back-office/users'), 'active' => $navMembersActive],
    ['label' => 'Candidatures', 'href' => url('back-office/recruitments'), 'active' => $navRecruesActive, 'warn' => true],
    $canMemberModeration
        ? ['label' => 'Sanctions & absences', 'href' => url('back-office/moderation'), 'active' => $navSanctionsActive]
        : null,
], static fn (?array $row): bool => is_array($row)));

$roleplayChildren = array_values(array_filter([
    ['label' => 'Bureau de suivi', 'href' => url('back-office/roleplay-followup'), 'active' => $navRoleplayActive],
    ['label' => 'Échéances', 'href' => url('back-office/roleplay-followup/echeances'), 'active' => $navRoleplayDeadlinesActive],
    ['label' => 'Réglages d’immersion', 'href' => url('back-office/roleplay/immersion'), 'active' => $navRoleplayImmersionActive],
], static fn (?array $row): bool => is_array($row)));

$orbatChildren = array_values(array_filter([
    ['label' => 'Structure & effectifs', 'href' => url('back-office/organisation-effectifs'), 'active' => $navOrbatActive],
    ['label' => 'Catalogue de l’organisation', 'href' => url('back-office/organisation/catalogue'), 'active' => !empty($boNavCatalog)],
    ['label' => 'Doctrine des fonctions', 'href' => url('back-office/roles-functions'), 'active' => $navDoctrineActive],
    ['label' => 'Attributions métier', 'href' => url('back-office/personnel-job-roles/assignments'), 'active' => $navAttributionsActive],
], static fn (?array $row): bool => is_array($row)));

$communityChildren = array_values(array_filter([
    ['label' => 'Identité & options', 'href' => url('back-office/community'), 'active' => $navCommunityActive],
    ['label' => 'Paramètres d’inscription', 'href' => url('back-office/community/inscription'), 'active' => $navInscriptionActive],
    ['label' => 'Page d’accueil publique', 'href' => url('back-office/community/presentation'), 'active' => $navPublicPageActive],
    $canMediaBo
        ? ['label' => 'Médias', 'href' => url('back-office/media'), 'active' => $navMediasActive]
        : null,
], static fn (?array $row): bool => is_array($row)));

$rsvpChildren = array_values(array_filter([
    ['label' => 'Inscriptions en cours', 'href' => url('back-office/events'), 'active' => $navRsvpActive, 'warn' => true],
    ['label' => 'Historique', 'href' => url('back-office/events/insights'), 'active' => $navRsvpHistActive],
], static fn (?array $row): bool => is_array($row)));

$atakDeviceChildren = array_values(array_filter([
    ['label' => 'Poste de situation', 'href' => url('back-office/atak'), 'active' => $navAtakHubActive],
    ['label' => 'Parc de terminaux', 'href' => url('back-office/atak/realisme'), 'active' => $navAtakDevicesActive],
    ['label' => 'Sessions & connexions', 'href' => url('back-office/atak/operateurs'), 'active' => $navAtakSessionsActive],
    ['label' => 'Certificats', 'href' => url('back-office/atak/certificats'), 'active' => $navAtakCertsActive, 'warn' => true],
    ['label' => 'Fiche opérateur', 'href' => url('back-office/atak/fiche-operateur'), 'active' => $navAtakOpActive],
], static fn (?array $row): bool => is_array($row)));

$rolesChildren = array_values(array_filter([
    ['label' => 'Matrice des rôles', 'href' => url('back-office/roles-permissions'), 'active' => $navRolesActive],
    ['label' => 'Table des rôles', 'href' => url('back-office/roles'), 'active' => $navRolesTableActive],
    ['label' => 'Profils de permissions', 'href' => url('back-office/roles/presets'), 'active' => $navProfilsActive],
], static fn (?array $row): bool => is_array($row)));

$jnetChildren = [
    ['label' => 'Tableau d’unité', 'href' => url('jnet'), 'active' => $boNavJnetHome],
    ['label' => 'Fiche d’unité', 'href' => url('jnet/unite'), 'active' => $boNavJnetUnit],
    ['label' => 'Personnel', 'href' => url('jnet/personnel'), 'active' => $boNavJnetPersonnel],
    ['label' => 'Opérations', 'href' => url('jnet/operations'), 'active' => $boNavJnetOps],
    ['label' => 'Renseignement', 'href' => url('jnet/renseignement'), 'active' => $boNavJnetIntel],
    ['label' => 'Cibles', 'href' => url('jnet/cibles'), 'active' => $boNavJnetTargets],
    ['label' => 'Exploitation', 'href' => url('jnet/exploitation'), 'active' => $boNavJnetExploit],
    ['label' => 'Bibliothèque', 'href' => url('jnet/bibliotheque'), 'active' => $boNavJnetLibrary],
    ['label' => 'Messagerie', 'href' => url('jnet/courrier'), 'active' => $boNavJnetMail],
    ['label' => 'Système', 'href' => url('jnet/systeme'), 'active' => $boNavJnetSystem],
];

$athNavGroups = [
    [
        'key' => 'pilotage',
        'label' => 'PILOTAGE',
        'items' => array_values(array_filter([
            ['label' => 'Tableau de bord', 'href' => url('back-office'), 'icon' => 'dash', 'active' => $boNavHome],
            $canMurOperationnel
                ? ['label' => 'Mur opérationnel', 'href' => url('back-office/tableau-operationnel'), 'icon' => 'wall', 'active' => $boNavOpsBoard || $boNavPortalOpsBoard]
                : null,
            ['label' => 'Agenda', 'href' => url('back-office/events') . '?vue=calendrier', 'icon' => 'cal', 'active' => $boNavEvents],
        ], static fn (?array $row): bool => is_array($row))),
    ],
    [
        'key' => 'personnel',
        'label' => 'PERSONNEL',
        'items' => array_values(array_filter([
            [
                'label' => 'Membres',
                'href' => url('back-office/users'),
                'icon' => 'users',
                'active' => $navMembersActive || $navRecruesActive || $navSanctionsActive,
                'badge' => $recBadgeStr,
                'warn' => $recBadgeStr !== null,
                'children' => $membersChildren,
            ],
            [
                'label' => 'Ordre de bataille',
                'href' => url('back-office/organisation-effectifs'),
                'icon' => 'orbat',
                'active' => $navOrbatActive || $navDoctrineActive || $navAttributionsActive || !empty($boNavCatalog),
                'children' => $orbatChildren,
            ],
            [
                'label' => 'Corrections RH',
                'href' => url('back-office/personnel/corrections'),
                'icon' => 'users',
                'active' => str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/back-office/personnel/corrections'),
            ],
            $canTraining
                ? ['label' => 'Formations', 'href' => url($lmsResPath), 'icon' => 'book', 'active' => $navFormationsActive]
                : null,
        ], static fn (?array $row): bool => is_array($row))),
    ],
    [
        'key' => 'roleplay',
        'label' => 'ROLEPLAY',
        'items' => array_values(array_filter([
            [
                'label' => 'Immersion & tutorat',
                'href' => url('back-office/roleplay-followup'),
                'icon' => 'roleplay',
                'active' => $navRoleplaySectionActive,
                'children' => $roleplayChildren,
            ],
        ], static fn (?array $row): bool => is_array($row))),
    ],
    [
        'key' => 'communaute',
        'label' => 'COMMUNAUTÉ',
        'items' => array_values(array_filter([
            ['label' => 'Configuration initiale', 'href' => url('back-office/configuration-initiale'), 'icon' => 'rocket', 'active' => $boNavInitialSetup, 'warn' => true],
            [
                'label' => 'Paramètres',
                'href' => url('back-office/community'),
                'icon' => 'home',
                'active' => $navCommunityActive || $navPublicPageActive || $navInscriptionActive || $navMediasActive,
                'children' => $communityChildren,
            ],
            ['label' => 'Annonces & alertes', 'href' => url('back-office/alerts'), 'icon' => 'mail', 'active' => $boNavAlerts],
            ['label' => 'Intégration des nouveaux membres', 'href' => url('back-office/integration-membres'), 'icon' => 'path', 'active' => $boNavOnbMembers],
            ['label' => 'Indicateurs d’usage', 'href' => url('back-office/analytics'), 'icon' => 'chart', 'active' => $boNavAnalytics],
        ], static fn (?array $row): bool => is_array($row))),
    ],
    [
        'key' => 'operations',
        'label' => 'OPÉRATIONS',
        'items' => array_values(array_filter([
            ['label' => 'Opérations', 'href' => url('back-office/events'), 'icon' => 'ops', 'active' => $boNavEvents],
            ['label' => 'Portail missions', 'href' => url('back-office/missions'), 'icon' => 'orbat', 'active' => !empty($boNavMissionsPortal)],
            ['label' => 'Planification', 'href' => url('back-office/planification'), 'icon' => 'orbat', 'active' => $boNavPlanning],
            [
                'label' => 'RSVP',
                'href' => url('back-office/events'),
                'icon' => 'rsvp',
                'active' => $navRsvpActive || $navRsvpHistActive,
                'warn' => true,
                'children' => $rsvpChildren,
            ],
            ['label' => 'Comptes rendus', 'href' => url('back-office/atak/comptes-rendus'), 'icon' => 'aar', 'active' => $boNavAar, 'warn' => true],
            (
                class_exists(\App\Support\PortalAccessChoice::class)
                && \App\Support\PortalAccessChoice::isNoOrganizationContext()
            ) ? null : [
                'label' => 'Extranet d’unité',
                'href' => url('jnet'),
                'icon' => 'orbat',
                'active' => $boNavJnet,
                'children' => $jnetChildren,
            ],
        ], static fn (?array $row): bool => is_array($row))),
    ],
    [
        'key' => 'atak',
        'label' => 'ATAK',
        'items' => array_values(array_filter([
            [
                'label' => 'Poste de situation',
                'href' => url('back-office/atak'),
                'icon' => 'ops',
                'active' => $navAtakHubActive,
            ],
            [
                'label' => 'Terminaux',
                'href' => url('back-office/atak/realisme'),
                'icon' => 'phone',
                'active' => $navAtakDevicesActive || $navAtakSessionsActive || $navAtakCertsActive || $navAtakOpActive,
                'children' => $atakDeviceChildren,
            ],
            ['label' => 'Sessions', 'href' => url('back-office/atak/operateurs'), 'icon' => 'radio', 'active' => $navAtakSessionsActive],
            ['label' => 'Certificats', 'href' => url('back-office/atak/certificats'), 'icon' => 'cert', 'active' => $navAtakCertsActive, 'warn' => true],
        ], static fn (?array $row): bool => is_array($row))),
    ],
    [
        'key' => 'systeme',
        'label' => 'SYSTÈME',
        'items' => array_values(array_filter([
            ['label' => 'Journal d’audit', 'href' => url('back-office/audit'), 'icon' => 'audit', 'active' => $boNavAudit],
            [
                'label' => 'Rôles & accès',
                'href' => url('back-office/roles-permissions'),
                'icon' => 'shield',
                'active' => $navRolesActive || $navRolesTableActive || $navProfilsActive,
                'children' => $rolesChildren,
            ],
            $canIntegrationsBo
                ? ['label' => 'Intégrations', 'href' => url('back-office/integrations'), 'icon' => 'plug', 'active' => $boNavInteg, 'warn' => true]
                : null,
            ['label' => 'Paramètres', 'href' => url('back-office/configuration'), 'icon' => 'gear', 'active' => $boNavConfig],
        ], static fn (?array $row): bool => is_array($row))),
    ],
];

$athNavFilterGroup = static function (array $groups) use ($boHrefAllowed): array {
    $out = [];
    foreach ($groups as $group) {
        $items = [];
        foreach ($group['items'] as $item) {
            if (!is_array($item) || ($item['href'] ?? '') === '') {
                continue;
            }
            $children = [];
            foreach ($item['children'] ?? [] as $child) {
                if (!is_array($child) || ($child['href'] ?? '') === '' || !$boHrefAllowed((string) $child['href'])) {
                    continue;
                }
                $children[] = $child;
            }
            $parentOk = $boHrefAllowed((string) $item['href']);
            // Parent sans droit mais enfants autorisés : conserver le bloc, cibler le 1er enfant.
            if (!$parentOk && $children === []) {
                continue;
            }
            if (!$parentOk && $children !== []) {
                $item['href'] = (string) ($children[0]['href'] ?? $item['href']);
            }
            $item['children'] = $children;
            $items[] = $item;
        }
        if ($items === []) {
            continue;
        }
        $group['items'] = $items;
        $out[] = $group;
    }

    return $out;
};

$athNavGroups = $athNavFilterGroup($athNavGroups);

$athNavResolveGroups = static function (array $groups) use ($p): array {
    foreach ($groups as &$group) {
        foreach ($group['items'] as &$item) {
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children !== []) {
                $item['children'] = back_office_nav_resolve_sibling_active($children, $p);
            }
        }
        unset($item);
        $group['items'] = back_office_nav_resolve_sibling_active($group['items'], $p);
    }
    unset($group);

    return $groups;
};

$athNavGroups = $athNavResolveGroups($athNavGroups);

$renderAthNavItem = static function (array $item) use ($h, $athIco): void {
    $children = is_array($item['children'] ?? null) ? $item['children'] : [];
    $selfActive = !empty($item['active']);
    $childActive = false;
    foreach ($children as $child) {
        if (!empty($child['active'])) {
            $childActive = true;
            break;
        }
    }
    $showKids = $children !== [] && ($selfActive || $childActive);
    $badge = isset($item['badge']) && (string) $item['badge'] !== '' ? (string) $item['badge'] : null;
    $warn = !empty($item['warn']);
    $iconMarkup = $athIco((string) ($item['icon'] ?? ''));
    ?>
    <div class="ath-sidebar__nav-block">
        <a href="<?= $h((string) $item['href']) ?>" class="ath-sidebar__item<?= $selfActive ? ' is-active' : '' ?>" title="<?= $h((string) $item['label']) ?>">
            <?php if ($iconMarkup !== ''): ?><?= $iconMarkup ?><?php endif; ?>
            <span class="ath-sidebar__item-label"><?= $h((string) $item['label']) ?></span>
            <?php if ($badge !== null): ?>
                <span class="ath-sidebar__item-badge<?= $warn ? ' ath-sidebar__item-badge--warn' : '' ?>"><?= $h($badge) ?></span>
            <?php endif; ?>
        </a>
        <?php if ($showKids): ?>
        <div class="ath-sidebar__children">
            <?php foreach ($children as $child): ?>
                <?php
                $cActive = !empty($child['active']);
                $cWarn = !empty($child['warn']);
                ?>
                <a href="<?= $h((string) $child['href']) ?>" class="ath-sidebar__child<?= $cActive ? ' is-active' : '' ?><?= $cWarn && !$cActive ? ' is-warn' : '' ?>">
                    <span class="ath-sidebar__child-dot" aria-hidden="true"></span>
                    <span class="ath-sidebar__child-label"><?= $h((string) $child['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
};
