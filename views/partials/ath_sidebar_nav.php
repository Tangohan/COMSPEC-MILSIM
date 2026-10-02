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
    'coop' => 'M8 12h8M10 8l-4 4 4 4M14 8l4 4-4 4M5 5h14v14H5z',
];

$athIco = static function (string $key) use ($athIcoPaths, $h): string {
    $d = $athIcoPaths[$key] ?? '';
    if ($d === '') {
        return '';
    }

    return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'
        . $h($d) . '"></path></svg>';
};

$navMembersActive = $boNavUsers || $boNavEffWorkspace;
$navRecruesActive = $boNavRec && !$boNavRecSettings && !$boNavRecMessages;
$navSanctionsActive = $boNavMod;
$navRoleplayActive = $boNavRoleplayFollowup;
$navRoleplayDeadlinesActive = !empty($boNavRoleplayDeadlines);
$navRoleplayImmersionActive = $boNavRoleplayImmersion;
$navRoleplaySectionActive = $boNavRoleplaySection;
$navOrbatActive = $boNavEff || !empty($boNavStructure);
$navDoctrineActive = $boNavRolesFx;
$navAttributionsActive = $boNavPjrAssignments || ($boNavPjr && !$boNavPjrAssignments && empty($boNavPjrKits));
$navFunctionKitsActive = !empty($boNavPjrKits);
$navFormationsActive = $boNavLmsRes || $boNavLmsSubPage;
$navCommunityActive = $boNavOrgSettings || $boNavCommInscription;
$navPublicPageActive = $boNavCommPres;
$navInscriptionActive = $boNavCommInscription;
$navMediasActive = $boNavMedia;
$navAtakHubActive = $p === 'back-office/atak';
$navTacticalMapActive = str_starts_with($p, 'back-office/operations/carte-tactique');
$navAtakDevicesActive = str_starts_with($p, 'back-office/atak/realisme');
$navAtakCertsActive = str_starts_with($p, 'back-office/atak/certificats');
$navAtakSessionsActive = $boNavAtakOperators;
$navAtakOpActive = str_starts_with($p, 'back-office/atak/fiche-operateur');
$navAtakControlActive = str_starts_with($p, 'back-office/atak/controle-serveur');
$navAtakRelaysActive = str_starts_with($p, 'back-office/atak/relays-network');
$navAtakRoleplayActive = str_starts_with($p, 'back-office/atak/roleplay') || str_starts_with($p, 'admin/atak/roleplay');
$navRolesActive = $boNavRolesPermissions;
$navRolesTableActive = $boNavRoles;
$navProfilsActive = $boNavRolesPresets;
$navAccessActive = $navRolesActive || $navRolesTableActive || $navProfilsActive
    || str_contains($p, '/effectifs/roles') || str_contains($p, '/effectifs/droits');
$navJobsActive = $navAttributionsActive || $navDoctrineActive || str_contains($p, '/effectifs/fonctions');
$navRsvpActive = $boNavEvents && !$boNavEventInsights;
$navRsvpHistActive = $boNavEventInsights;
$boNavPlanning = !empty($boNavPlanning);
$boNavMissionsPortal = !empty($boNavMissionsPortal);
$boNavCooperation = !empty($boNavCooperation);
$boNavCooperationMissions = !empty($boNavCooperationMissions);
$boNavCooperationCreate = !empty($boNavCooperationCreate);
$boNavCooperationCatalog = !empty($boNavCooperationCatalog);
$boNavCooperationAnnouncements = !empty($boNavCooperationAnnouncements);

$recBadgeStr = !empty($boBadges['show_staff_recruitment']) && $boRecN > 0
    ? ($boRecN > 99 ? '99+' : (string) $boRecN)
    : null;

$fmtNavBadge = static function (int $n): ?string {
    if ($n < 1) {
        return null;
    }

    return $n > 99 ? '99+' : (string) $n;
};
$opInboxBadge = $fmtNavBadge((int) ($boBadges['messages_unread'] ?? $boBadges['personal_inbox'] ?? 0));
$opEventsBadge = $fmtNavBadge((int) ($boBadges['events_rsvp_pending'] ?? 0));
$opQualifBadge = $fmtNavBadge((int) ($boBadges['qualifications_expiring'] ?? 0));
$opDemarchesBadge = $fmtNavBadge((int) ($boBadges['my_enlistments_pending'] ?? 0));

/** Vrai si le chemin courant est l’un des préfixes donnés (ou une sous-page). */
$navAt = static function (string ...$prefixes) use ($p): bool {
    foreach ($prefixes as $prefix) {
        if ($p === $prefix || str_starts_with($p, $prefix . '/')) {
            return true;
        }
    }

    return false;
};
$effPath = effectifs_workspace_path();

$membersChildren = array_values(array_filter([
    ['label' => 'Liste des membres', 'href' => effectifs_workspace_url(), 'active' => $p === $effPath || $boNavUsers],
    ['label' => 'Affectations', 'href' => effectifs_workspace_url('affectations'), 'active' => $navAt($effPath . '/affectations')],
    ['label' => 'Chaîne de commandement', 'href' => effectifs_workspace_url('chaine'), 'active' => $navAt($effPath . '/chaine')],
    ['label' => 'Qualifications des membres', 'href' => effectifs_workspace_url('qualifications'), 'active' => $navAt($effPath . '/qualifications')],
    ['label' => 'Départs', 'href' => effectifs_workspace_url('departs'), 'active' => $navAt($effPath . '/departs')],
    ['label' => 'Comptes en double', 'href' => effectifs_workspace_url('doublons'), 'active' => $navAt($effPath . '/doublons')],
    $canMemberModeration
        ? ['label' => 'Sanctions & absences', 'href' => url('back-office/moderation'), 'active' => $navSanctionsActive]
        : null,
], static fn (?array $row): bool => is_array($row)));

$recruitChildren = [
    ['label' => 'Candidatures', 'href' => url('back-office/recruitments'), 'active' => $navRecruesActive],
    ['label' => 'Vue d’ensemble', 'href' => url('back-office/ressources/recrutement'), 'active' => $p === 'back-office/ressources/recrutement'],
    ['label' => 'Offres publiées', 'href' => url('back-office/recruitment/offers'), 'active' => $navAt('back-office/recruitment/offers')],
    ['label' => 'Invitations', 'href' => url('back-office/invitations'), 'active' => $navAt('back-office/invitations')],
    ['label' => 'Statistiques', 'href' => url('back-office/ressources/recrutement/analyses'), 'active' => $navAt('back-office/ressources/recrutement/analyses')],
];

$rhChildren = [
    ['label' => 'Alertes RH', 'href' => effectifs_workspace_url('alertes'), 'active' => $navAt($effPath . '/alertes')],
    ['label' => 'Demandes d’accès élevé', 'href' => effectifs_workspace_url('elevations'), 'active' => $navAt($effPath . '/elevations')],
    ['label' => 'Documents RH', 'href' => effectifs_workspace_url('documents-rh'), 'active' => $navAt($effPath . '/documents-rh')],
    ['label' => 'Intégration', 'href' => effectifs_workspace_url('integration'), 'active' => $navAt($effPath . '/integration')],
    ['label' => 'Mobilité', 'href' => effectifs_workspace_url('mobilite'), 'active' => $navAt($effPath . '/mobilite')],
    ['label' => 'Relève et succession', 'href' => effectifs_workspace_url('vivier'), 'active' => $navAt($effPath . '/vivier')],
    ['label' => 'Corrections de fiches', 'href' => url('back-office/personnel/corrections'), 'active' => $navAt('back-office/personnel/corrections')],
    ['label' => 'Avancement', 'href' => url('back-office/rh/avancement'), 'active' => $navAt('back-office/rh/avancement')],
    ['label' => 'Ancienneté', 'href' => url('back-office/organisation/anciennete'), 'active' => $navAt('back-office/organisation/anciennete')],
    ['label' => 'Réglages RH', 'href' => effectifs_workspace_url('reglages'), 'active' => $navAt($effPath . '/reglages')],
];

$accessChildren = [
    ['label' => 'Rôles', 'href' => effectifs_workspace_url('roles'), 'active' => $navAccessActive && !$navAt($effPath . '/droits')],
    ['label' => 'Droits par membre', 'href' => effectifs_workspace_url('droits'), 'active' => $navAt($effPath . '/droits')],
    ['label' => 'Fonctions et emplois', 'href' => effectifs_workspace_url('fonctions'), 'active' => $navJobsActive],
    ['label' => 'Accès au renseignement', 'href' => url('back-office/renseignement/acces'), 'active' => $navAt('back-office/renseignement')],
];

$roleplayChildren = [
    ['label' => 'Bureau de suivi', 'href' => url('back-office/roleplay-followup'), 'active' => $navRoleplayActive && !$navRoleplayDeadlinesActive],
    ['label' => 'Échéances', 'href' => url('back-office/roleplay-followup/echeances'), 'active' => $navRoleplayDeadlinesActive],
    ['label' => 'Dossiers roleplay', 'href' => effectifs_workspace_url('roleplay'), 'active' => $navAt($effPath . '/roleplay')],
    ['label' => 'Parcours d’immersion', 'href' => url('back-office/roleplay/immersion'), 'active' => $navRoleplayImmersionActive],
    ['label' => 'Parcours RH', 'href' => url('back-office/roleplay/regles-phases'), 'active' => $navAt('back-office/roleplay/regles-phases')],
    ['label' => 'Sessions Arma', 'href' => url('back-office/roleplay/sessions'), 'active' => $navAt('back-office/roleplay/sessions')],
];

$orbatChildren = [
    ['label' => 'Organigramme', 'href' => url('back-office/organisation/structure'), 'active' => !empty($boNavStructure)],
    ['label' => 'Unités', 'href' => url('back-office/units'), 'active' => $navAt('back-office/units')],
    ['label' => 'Groupes', 'href' => url('back-office/groups'), 'active' => $navAt('back-office/groups')],
    ['label' => 'Équipes', 'href' => url('back-office/teams'), 'active' => $navAt('back-office/teams')],
    ['label' => 'Postes', 'href' => url('back-office/positions'), 'active' => $navAt('back-office/positions')],
    ['label' => 'Catalogue de l’organisation', 'href' => url('back-office/organisation/catalogue'), 'active' => !empty($boNavCatalog)],
    ['label' => 'Grades', 'href' => url('back-office/referentiels/grades'), 'active' => $navAt('back-office/referentiels/grades', 'back-office/organisation/grades')],
    ['label' => 'Qualifications', 'href' => url('back-office/referentiels/qualifications'), 'active' => $navAt('back-office/referentiels/qualifications')],
    ['label' => 'Compétences par grade', 'href' => url('back-office/referentiels/competences'), 'active' => $navAt('back-office/referentiels/competences')],
    ['label' => 'Décorations', 'href' => url('back-office/referentiels/decorations'), 'active' => $navAt('back-office/referentiels/decorations')],
    ['label' => 'Dotation', 'href' => url('back-office/referentiels/dotation'), 'active' => $navAt('back-office/referentiels/dotation')],
    ['label' => 'PASS RH', 'href' => url('back-office/organisation/passes'), 'active' => $navAt('back-office/organisation/passes')],
    ['label' => 'Matricules', 'href' => url('back-office/organisation/matricules'), 'active' => $navAt('back-office/organisation/matricules')],
    ['label' => 'Indicatifs radio', 'href' => url('back-office/organisation/indicatifs'), 'active' => $navAt('back-office/organisation/indicatifs')],
    ['label' => 'Disponibilité', 'href' => url('back-office/organisation/disponibilite'), 'active' => $navAt('back-office/organisation/disponibilite')],
];

$communityChildren = array_values(array_filter([
    ['label' => 'Paramètres', 'href' => url('back-office/community'), 'active' => $navCommunityActive || $navInscriptionActive],
    ['label' => 'Page d’accueil publique', 'href' => url('back-office/community/presentation'), 'active' => $navPublicPageActive],
    $canMediaBo
        ? ['label' => 'Médias', 'href' => url('back-office/media'), 'active' => $navMediasActive]
        : null,
    ['label' => 'Raccourcis du portail', 'href' => url('back-office/dashboard-pins'), 'active' => $navAt('back-office/dashboard-pins')],
    ['label' => 'Tenues mises en avant', 'href' => url('back-office/dashboard-tenues'), 'active' => $navAt('back-office/dashboard-tenues')],
    ['label' => 'Catégories du forum', 'href' => url('back-office/categories'), 'active' => $navAt('back-office/categories')],
], static fn (?array $row): bool => is_array($row)));

$messagesChildren = [
    ['label' => 'Annonces', 'href' => url('back-office/alerts'), 'active' => $boNavAlerts],
    ['label' => 'E-mails aux membres', 'href' => url('back-office/communications'), 'active' => $p === 'back-office/communications'],
    ['label' => 'Historique des e-mails', 'href' => url('back-office/communications/history'), 'active' => $navAt('back-office/communications/history')],
    ['label' => 'Modèles d’e-mail', 'href' => url('back-office/communications/templates'), 'active' => $navAt('back-office/communications/templates')],
    ['label' => 'Listes de diffusion', 'href' => url('back-office/communications/groups'), 'active' => $navAt('back-office/communications/groups')],
    ['label' => 'Mini-articles', 'href' => url('back-office/articles'), 'active' => !empty($boNavArticles)],
];

$doctrineChildren = [
    ['label' => 'Doctrine et SOP', 'href' => url('back-office/doctrine'), 'active' => $navAt('back-office/doctrine')],
    ['label' => 'Types de documents', 'href' => url('back-office/documents/types'), 'active' => $navAt('back-office/documents/types')],
    ['label' => 'Nomenclature', 'href' => url('back-office/documents/nomenclature'), 'active' => $navAt('back-office/documents/nomenclature')],
    ['label' => 'Conformité', 'href' => url('back-office/documents/compliance'), 'active' => $navAt('back-office/documents/compliance')],
];

$eventsChildren = [
    ['label' => 'Inscriptions en cours', 'href' => url('back-office/events'), 'active' => $navRsvpActive && (string) ($_GET['vue'] ?? '') !== 'calendrier'],
    ['label' => 'Calendrier', 'href' => url('back-office/events') . '?vue=calendrier', 'active' => $navRsvpActive && (string) ($_GET['vue'] ?? '') === 'calendrier'],
    ['label' => 'Bilan de participation', 'href' => url('back-office/events/insights'), 'active' => $navRsvpHistActive],
];

$cooperationChildren = [
    ['label' => 'Toutes les coopérations', 'href' => cooperation_mission_index_url(), 'active' => $boNavCooperationMissions && !$boNavCooperationCreate],
    ['label' => 'Nouvelle coopération', 'href' => cooperation_mission_create_url(), 'active' => $boNavCooperationCreate],
    ['label' => 'Types & modèles', 'href' => url('back-office/cooperation/catalog'), 'active' => $boNavCooperationCatalog],
    ['label' => 'Messages d’annonce', 'href' => url('back-office/cooperation/announcements'), 'active' => $boNavCooperationAnnouncements],
];

$atakDeviceChildren = [
    ['label' => 'Parc de terminaux', 'href' => url('back-office/atak/realisme'), 'active' => $navAtakDevicesActive],
    ['label' => 'Sessions et connexions', 'href' => url('back-office/atak/operateurs'), 'active' => $navAtakSessionsActive],
    ['label' => 'Certificats', 'href' => url('back-office/atak/certificats'), 'active' => $navAtakCertsActive],
    ['label' => 'Fiche opérateur', 'href' => url('back-office/atak/fiche-operateur'), 'active' => $navAtakOpActive],
    ['label' => 'Détection des marqueurs', 'href' => url('back-office/atak/detection-marqueurs'), 'active' => $navAt('back-office/atak/detection-marqueurs')],
];

$atakResChildren = [
    ['label' => 'Configuration ATAK', 'href' => url('admin/atak-config'), 'active' => $navAt('admin/atak-config')],
    ['label' => 'Mod ATAK', 'href' => url('admin/atak-mod'), 'active' => $navAt('admin/atak-mod')],
    ['label' => 'Modpacks', 'href' => url('admin/modpacks'), 'active' => $navAt('admin/modpacks')],
];

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

$isOperatorBoNav = !empty($isOperatorBoNav);

$opOnglet = (string) ($_GET['onglet'] ?? '');
$opFicheBase = str_starts_with($p, 'back-office/ma-situation/ma-fiche')
    || $p === 'personnel/me'
    || str_starts_with($p, 'personnel/me/');
$opSuiviActive = $opFicheBase && $opOnglet === 'suivi';
$opFicheActive = $opFicheBase && $opOnglet !== 'suivi';
$opAccountSystemActive = $p === 'account/preferences'
    || str_starts_with($p, 'account/preferences/')
    || $p === 'account/security'
    || str_starts_with($p, 'account/security/')
    || $p === 'account/password'
    || str_starts_with($p, 'account/password/')
    || $p === 'account/mail'
    || str_starts_with($p, 'account/mail/')
    || $p === 'account/donnees'
    || str_starts_with($p, 'account/donnees/')
    || $p === 'account/acces'
    || str_starts_with($p, 'account/acces/');
$opMonCompteActive = ($p === 'account' || str_starts_with($p, 'account/')) && !$opAccountSystemActive;
$opExtranetItem = (
    class_exists(\App\Support\PortalAccessChoice::class)
    && \App\Support\PortalAccessChoice::isNoOrganizationContext()
) ? null : [
    'label' => 'Extranet d’unité',
    'href' => url('jnet'),
    'icon' => 'orbat',
    'active' => $boNavJnet,
];

if ($isOperatorBoNav) {
    // Navigation opérateur par usage — pas le menu admin filtré.
    $athNavGroups = [
        [
            'key' => 'pilotage',
            'label' => 'PILOTAGE',
            'items' => [
                ['label' => 'Tableau de bord', 'href' => url('back-office'), 'icon' => 'dash', 'active' => $boNavHome],
            ],
        ],
        [
            'key' => 'personnel',
            'label' => 'MA SITUATION',
            'items' => [
                ['label' => 'Ma fiche', 'href' => url('back-office/ma-situation/ma-fiche'), 'icon' => 'users', 'active' => $opFicheActive],
                ['label' => 'Mon unité', 'href' => url('back-office/ma-situation/unite'), 'icon' => 'ops', 'active' => str_starts_with($p, 'back-office/ma-situation/unite')],
                [
                    'label' => 'Mes qualifications',
                    'href' => url('back-office/ma-situation/qualifications'),
                    'icon' => 'cert',
                    'active' => str_starts_with($p, 'back-office/ma-situation/qualifications'),
                    'badge' => $opQualifBadge,
                    'warn' => $opQualifBadge !== null,
                    'notif' => $opQualifBadge !== null,
                ],
                [
                    'label' => 'Mon avancement',
                    'href' => url('back-office/ma-situation/avancement'),
                    'icon' => 'path',
                    'active' => str_starts_with($p, 'back-office/ma-situation/avancement'),
                ],
                ['label' => 'Dossier de carrière', 'href' => url('back-office/ma-situation/carriere'), 'icon' => 'path', 'active' => str_starts_with($p, 'back-office/ma-situation/carriere')],
                ['label' => 'Décorations', 'href' => url('back-office/ma-situation/decorations'), 'icon' => 'shield', 'active' => str_starts_with($p, 'back-office/ma-situation/decorations')],
                ['label' => 'Ma dotation', 'href' => url('back-office/ma-situation/dotation'), 'icon' => 'gear', 'active' => str_starts_with($p, 'back-office/ma-situation/dotation')],
                ['label' => 'Mon suivi', 'href' => url('back-office/ma-situation/ma-fiche') . '?onglet=suivi', 'icon' => 'path', 'active' => $opSuiviActive],
                [
                    'label' => 'Mon coffre',
                    'href' => url('back-office/ma-situation/coffre'),
                    'icon' => 'cert',
                    'active' => str_starts_with($p, 'back-office/ma-situation/coffre'),
                ],
                ['label' => 'Mon compte', 'href' => url('account'), 'icon' => 'users', 'active' => $opMonCompteActive],
            ],
        ],
        [
            'key' => 'terrain',
            'label' => 'TERRAIN',
            'items' => [
                ['label' => 'Carte ATAK', 'href' => url('atak'), 'icon' => 'ops', 'active' => $p === 'atak'],
                ['label' => 'Ma liaison ATAK', 'href' => url('back-office/ma-situation/liaison-atak'), 'icon' => 'radio', 'active' => str_starts_with($p, 'back-office/ma-situation/liaison-atak') || str_starts_with($p, 'back-office/ma-situation/appareils') || str_starts_with($p, 'back-office/ma-situation/premiere-liaison')],
                ['label' => 'Overwatch Beta', 'href' => url('-ATAK-OVERWATCH-Beta'), 'icon' => 'ops', 'active' => in_array($p, ['-ATAK-OVERWATCH-Beta', 'atak-overwatch-beta', 'ATAK-OVERWATCH-Beta'], true), 'badge' => 'BETA'],
                [
                    'label' => 'Événements',
                    'href' => url('back-office/ma-situation/evenements'),
                    'icon' => 'cal',
                    'active' => $p === 'evenements' || str_starts_with($p, 'evenements/') || str_starts_with($p, 'back-office/ma-situation/evenements'),
                    'badge' => $opEventsBadge,
                    'warn' => $opEventsBadge !== null,
                    'notif' => $opEventsBadge !== null,
                ],
            ],
        ],
        [
            'key' => 'administratif',
            'label' => 'ADMINISTRATIF',
            'items' => [
                [
                    'label' => 'Mes démarches',
                    'href' => url('back-office/ma-situation/mes-demarches'),
                    'icon' => 'path',
                    'active' => str_starts_with($p, 'back-office/ma-situation/mes-demarches') || str_contains($p, 'mon-espace-rh'),
                    'badge' => $opDemarchesBadge,
                    'warn' => $opDemarchesBadge !== null,
                    'notif' => $opDemarchesBadge !== null,
                ],
                [
                    'label' => 'Boîte de réception',
                    'href' => url('boite-reception'),
                    'icon' => 'mail',
                    'active' => $p === 'boite-reception',
                    'badge' => $opInboxBadge,
                    'warn' => $opInboxBadge !== null,
                    'notif' => $opInboxBadge !== null,
                ],
            ],
        ],
        [
            'key' => 'organisation',
            'label' => 'ORGANISATION',
            'items' => array_values(array_filter([
                [
                    'label' => 'Coopérations inter-unités',
                    'href' => cooperation_mission_index_url(),
                    'icon' => 'coop',
                    'active' => $boNavCooperation,
                ],
                $opExtranetItem,
            ], static fn (?array $row): bool => is_array($row))),
        ],
        [
            'key' => 'systeme',
            'label' => 'SYSTÈME',
            'items' => [
                ['label' => 'Paramètres', 'href' => url('account/preferences'), 'icon' => 'gear', 'active' => $opAccountSystemActive],
            ],
        ],
    ];
} else {
    $forumModBadge = $fmtNavBadge((int) ($boBadges['forum_moderation_total'] ?? 0));
    $anyActive = static function (array $children): bool {
        foreach ($children as $child) {
            if (is_array($child) && !empty($child['active'])) {
                return true;
            }
        }

        return false;
    };

    $athNavGroups = [
        [
            'key' => 'pilotage',
            'label' => 'PILOTAGE',
            'items' => array_values(array_filter([
                ['label' => 'Tableau de bord', 'href' => url('back-office'), 'icon' => 'dash', 'active' => $boNavHome],
                ['label' => 'Centre d’opérations', 'href' => url('back-office/centre-operations'), 'icon' => 'ops', 'active' => $navAt('back-office/centre-operations', 'back-office/operations-admin')],
                $canMurOperationnel
                    ? ['label' => 'Tableau opérationnel', 'href' => url('back-office/tableau-operationnel'), 'icon' => 'wall', 'active' => $boNavOpsBoard || $boNavPortalOpsBoard]
                    : null,
                ['label' => 'Indicateurs d’usage', 'href' => url('back-office/analytics'), 'icon' => 'chart', 'active' => $boNavAnalytics],
                ['label' => 'Qualité des données', 'href' => url('back-office/organisation/qualite-donnees'), 'icon' => 'audit', 'active' => $navAt('back-office/organisation/qualite-donnees')],
            ], static fn (?array $row): bool => is_array($row))),
        ],
        [
            'key' => 'personnel',
            'label' => 'PERSONNEL',
            'items' => array_values(array_filter([
                [
                    'label' => 'Effectifs',
                    'href' => effectifs_workspace_url(),
                    'icon' => 'users',
                    'active' => $anyActive($membersChildren),
                    'children' => $membersChildren,
                ],
                [
                    'label' => 'Recrutement',
                    'href' => url('back-office/recruitments'),
                    'icon' => 'mail',
                    'active' => $anyActive($recruitChildren),
                    'badge' => $recBadgeStr,
                    'warn' => $recBadgeStr !== null,
                    'children' => $recruitChildren,
                ],
                [
                    'label' => 'Dossiers RH',
                    'href' => effectifs_workspace_url('alertes'),
                    'icon' => 'path',
                    'active' => $anyActive($rhChildren),
                    'children' => $rhChildren,
                ],
                [
                    'label' => 'Organisation',
                    'href' => url('back-office/organisation-effectifs'),
                    'icon' => 'orbat',
                    'active' => $p === 'back-office/organisation-effectifs' || $anyActive($orbatChildren),
                    'children' => $orbatChildren,
                ],
                $canTraining
                    ? ['label' => 'Formations', 'href' => url($lmsResPath), 'icon' => 'book', 'active' => $navFormationsActive]
                    : null,
            ], static fn (?array $row): bool => is_array($row))),
        ],
        [
            'key' => 'acces',
            'label' => 'ACCÈS',
            'items' => [
                [
                    'label' => 'Rôles et droits',
                    'href' => (string) $accessChildren[0]['href'],
                    'icon' => 'shield',
                    'active' => $anyActive($accessChildren),
                    'children' => $accessChildren,
                ],
                ['label' => 'Sécurité et blocages', 'href' => url('back-office/security-indicators'), 'icon' => 'cert', 'active' => $navAt('back-office/security-indicators')],
                ['label' => 'Journal d’audit', 'href' => url('back-office/audit'), 'icon' => 'audit', 'active' => $boNavAudit],
            ],
        ],
        [
            'key' => 'roleplay',
            'label' => 'ROLEPLAY',
            'items' => [
                [
                    'label' => 'Suivi roleplay',
                    'href' => url('back-office/roleplay-followup'),
                    'icon' => 'roleplay',
                    'active' => $navRoleplaySectionActive || $anyActive($roleplayChildren),
                    'children' => $roleplayChildren,
                ],
                ['label' => 'Mode roleplay ATAK', 'href' => url('back-office/atak/roleplay'), 'icon' => 'radio', 'active' => $navAtakRoleplayActive],
                ['label' => 'Intégration des recrues', 'href' => url('back-office/integration-membres'), 'icon' => 'path', 'active' => $boNavOnbMembers],
            ],
        ],
        [
            'key' => 'operations',
            'label' => 'OPÉRATIONS',
            'items' => array_values(array_filter([
                [
                    'label' => 'Événements',
                    'href' => url('back-office/events'),
                    'icon' => 'rsvp',
                    'active' => $boNavEvents || $navRsvpHistActive,
                    'children' => $eventsChildren,
                ],
                ['label' => 'Planification', 'href' => url('back-office/planification'), 'icon' => 'cal', 'active' => $boNavPlanning],
                ['label' => 'Portail missions', 'href' => url('back-office/missions'), 'icon' => 'orbat', 'active' => $boNavMissionsPortal],
                ['label' => 'Carte tactique', 'href' => url('back-office/operations/carte-tactique'), 'icon' => 'ops', 'active' => $navTacticalMapActive],
                ['label' => 'Comptes rendus', 'href' => url('back-office/atak/comptes-rendus'), 'icon' => 'aar', 'active' => $boNavAar],
                [
                    'label' => 'Coopérations inter-unités',
                    'href' => cooperation_mission_index_url(),
                    'icon' => 'coop',
                    'active' => $boNavCooperation,
                    'children' => $cooperationChildren,
                ],
                $opExtranetItem === null ? null : array_merge($opExtranetItem, ['children' => $jnetChildren]),
            ], static fn (?array $row): bool => is_array($row))),
        ],
        [
            'key' => 'atak',
            'label' => 'ATAK',
            'items' => [
                ['label' => 'Poste de situation', 'href' => url('back-office/atak'), 'icon' => 'ops', 'active' => $navAtakHubActive],
                ['label' => 'Contrôle de mission', 'href' => url('back-office/atak/controle-serveur'), 'icon' => 'gear', 'active' => $navAtakControlActive],
                ['label' => 'Réseau de relais', 'href' => url('back-office/atak/relays-network'), 'icon' => 'radio', 'active' => $navAtakRelaysActive],
                [
                    'label' => 'Terminaux',
                    'href' => url('back-office/atak/realisme'),
                    'icon' => 'phone',
                    'active' => $anyActive($atakDeviceChildren),
                    'children' => $atakDeviceChildren,
                ],
                [
                    'label' => 'Configuration et mods',
                    'href' => url('admin/atak-config'),
                    'icon' => 'plug',
                    'active' => $anyActive($atakResChildren),
                    'children' => $atakResChildren,
                ],
            ],
        ],
        [
            'key' => 'communaute',
            'label' => 'COMMUNAUTÉ',
            'items' => array_values(array_filter([
                ['label' => 'Configuration initiale', 'href' => url('back-office/configuration-initiale'), 'icon' => 'rocket', 'active' => $boNavInitialSetup],
                [
                    'label' => 'Vitrine et portail',
                    'href' => url('back-office/community'),
                    'icon' => 'home',
                    'active' => $anyActive($communityChildren),
                    'children' => $communityChildren,
                ],
                [
                    'label' => 'Communication',
                    'href' => url('back-office/alerts'),
                    'icon' => 'mail',
                    'active' => $anyActive($messagesChildren),
                    'children' => $messagesChildren,
                ],
                [
                    'label' => 'Doctrine et documents',
                    'href' => url('back-office/doctrine'),
                    'icon' => 'book',
                    'active' => $anyActive($doctrineChildren),
                    'children' => $doctrineChildren,
                ],
                [
                    'label' => 'Modération du forum',
                    'href' => url('back-office/forum-moderation'),
                    'icon' => 'shield',
                    'active' => $navAt('back-office/forum-moderation'),
                    'badge' => $forumModBadge,
                    'warn' => $forumModBadge !== null,
                ],
            ], static fn (?array $row): bool => is_array($row))),
        ],
        [
            'key' => 'systeme',
            'label' => 'SYSTÈME',
            'items' => array_values(array_filter([
                $canIntegrationsBo
                    ? ['label' => 'Intégrations', 'href' => url('back-office/integrations'), 'icon' => 'plug', 'active' => $boNavInteg]
                    : null,
                ['label' => 'Traçabilité du courrier', 'href' => url('back-office/courrier/traceabilite'), 'icon' => 'mail', 'active' => $navAt('back-office/courrier/traceabilite')],
                ['label' => 'Mises à jour', 'href' => url('back-office/mise-a-niveau'), 'icon' => 'rocket', 'active' => $navAt('back-office/mise-a-niveau')],
                ['label' => 'Paramètres avancés', 'href' => url('back-office/configuration'), 'icon' => 'gear', 'active' => $boNavConfig],
            ], static fn (?array $row): bool => is_array($row))),
        ],
    ];
}

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
if (function_exists('i18n_translate_nav_item')) {
    $athNavGroups = array_map('i18n_translate_nav_item', $athNavGroups);
}

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
    $notif = !empty($item['notif']);
    $iconMarkup = $athIco((string) ($item['icon'] ?? ''));
    $kidsId = $children !== [] ? 'ath-nav-kids-' . substr(md5((string) $item['href'] . (string) $item['label']), 0, 8) : '';
    // Le parent n’est « actif » visuellement que s’il est la page courante, pas un de ses enfants.
    $parentCurrent = $selfActive && !$childActive;
    ?>
    <div class="ath-sidebar__nav-block<?= $showKids ? ' is-expanded' : '' ?>">
        <div class="ath-sidebar__item-row">
            <a href="<?= $h((string) $item['href']) ?>" class="ath-sidebar__item<?= ($selfActive || $childActive) ? ' is-active' : '' ?>"<?= $parentCurrent ? ' aria-current="page"' : '' ?> title="<?= $h((string) $item['label']) ?>">
                <?php if ($iconMarkup !== ''): ?><?= $iconMarkup ?><?php endif; ?>
                <span class="ath-sidebar__item-label"><?= $h((string) $item['label']) ?></span>
                <?php if ($badge !== null): ?>
                    <span class="ath-sidebar__item-badge<?= $warn ? ' ath-sidebar__item-badge--warn' : '' ?><?= $notif ? ' ath-sidebar__item-badge--notif' : '' ?>"><?= $h($badge) ?></span>
                <?php endif; ?>
            </a>
            <?php if ($children !== []): ?>
            <button type="button" class="ath-sidebar__kids-toggle" data-ath-kids-toggle aria-controls="<?= $h($kidsId) ?>" aria-expanded="<?= $showKids ? 'true' : 'false' ?>" aria-label="<?= $h('Afficher les pages de ' . (string) $item['label']) ?>">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
            </button>
            <?php endif; ?>
        </div>
        <?php if ($children !== []): ?>
        <div class="ath-sidebar__children" id="<?= $h($kidsId) ?>"<?= $showKids ? '' : ' hidden' ?>>
            <?php foreach ($children as $child): ?>
                <?php
                $cActive = !empty($child['active']);
                $cWarn = !empty($child['warn']);
                ?>
                <a href="<?= $h((string) $child['href']) ?>" class="ath-sidebar__child<?= $cActive ? ' is-active' : '' ?><?= $cWarn && !$cActive ? ' is-warn' : '' ?>"<?= $cActive ? ' aria-current="page"' : '' ?> data-ath-child-search="<?= $h(mb_strtolower((string) $child['label'], 'UTF-8')) ?>">
                    <span class="ath-sidebar__child-dot" aria-hidden="true"></span>
                    <span class="ath-sidebar__child-label"><?= $h((string) $child['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
};
