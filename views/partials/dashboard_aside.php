<?php
declare(strict_types=1);

/**
 * Aside dashboard Athena — panneau sombre pliable (hover / focus).
 * Clic sur une tuile → panneau détail (même aside/rail gauche, contenu remplacé, bouton « Retour »).
 * Nécessite public/assets/js/dashboard-rail.js (boutons sans href : sans ce script, les
 * tuiles restent inertes — voir data-dash-rail-open / data-dash-rail-nested).
 * Pattern repris de l’aside « Registre » (clic profil → masque la liste, affiche le panneau, retour).
 *
 * Regroupe les entrées de l’ancienne navbar (Dashboard, Hub, Forum, Formations,
 * Effectifs, Back-office, Administration, Espaces, Invitations, Compte) avec permissions.
 *
 * @var string|null $dashboard_tenant_label
 * @var bool $show_staff_enlistments
 * @var int $staffCount
 * @var int $opsCount
 * @var int $trainCount
 * @var string $statutLabel
 * @var string $activeDashNav
 * @var array<string,mixed>|null $currentUser Fourni par dashboard_command_center.php (identité compte)
 */

$unitLabel = ($dashboard_tenant_label !== null && $dashboard_tenant_label !== '')
    ? (string) $dashboard_tenant_label
    : (isset($unitLabel) ? (string) $unitLabel : 'Communauté');
$showStaff = !empty($show_staff_enlistments);
$staffCount = (int) ($staffCount ?? 0);
$opsCount = (int) ($opsCount ?? 0);
$trainCount = (int) ($trainCount ?? 0);
$statutLabel = (string) ($statutLabel ?? 'Opérationnel');
$activeDashNav = (string) ($activeDashNav ?? 'overview');

$canAdmin = function_exists('can') && (can('admin.organization') || can('admin.access'));
$canSystem = function_exists('can') && can('admin.system');
$canForum = !function_exists('can') || can('forum.view');
$canForumCreate = function_exists('can') && can('forum.create_topic');
$canTrainingManage = function_exists('can') && (
    can('admin.access') || can('training.manage') || can('training.create')
    || can('training.assign') || can('training.update') || can('training.publish')
);
$canOrbat = !function_exists('can') || can('organization.orbat.view');
$canDocs = !function_exists('can') || can('documents.view');
$canDocsUpload = function_exists('can') && (can('documents.upload') || can('admin.access'));
$canRecruit = $showStaff || (function_exists('can') && (
    can('organization.recruitment.manage') || can('admin.organization') || can('admin.access')
));
$canInvitationsTile = isset($canManageInvitationsAside)
    ? (bool) $canManageInvitationsAside
    : ($canAdmin || (function_exists('can') && can('invitations.send')));
$pendingInvitationsForTile = (int) ($pendingInvitationsCountAside ?? 0);

// Identité compte (tuile « Mon compte ») — fournie par dashboard_command_center.php ; défauts défensifs.
$acctUser = is_array($cu ?? null) ? $cu : (is_array($currentUser ?? null) ? $currentUser : null);
$acctDisplayName = (string) ($displayName ?? ($acctUser['display_name'] ?? $acctUser['email'] ?? 'Opérateur'));
$acctRoleHint = (string) ($roleHint ?? 'Opérateur');
$acctPlatformRole = (string) ($platformRole ?? '');
$acctMatricule = $matricule ?? null;
$acctAvatarSrc = $avatarSrc ?? null;
$acctAthenaId = trim((string) ($athenaIdentifier ?? ''));
$acctMemberSinceLabel = (string) ($memberSinceLabel ?? '—');
$acctMemberSinceDate = $memberSinceDateLabel ?? null;
$acctCandidateDossier = isset($candidateDossierNumber) && $candidateDossierNumber !== null ? (int) $candidateDossierNumber : null;
$acctEmailDisabledCount = (int) ($emailAlertsDisabledCountAside ?? 0);

$n = 0;
$num = static function () use (&$n): string {
    $n++;

    return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
};

$h = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

/** Icônes SVG discrètes — réservées à quelques tuiles clés (pas de bruit visuel). */
$icon = static function (string $key): string {
    $icons = [
        'overview' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>',
        'hub' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.2"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.2"/></svg>',
        'events' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="15.5" rx="1.5"/><path stroke-linecap="round" d="M3.5 9.5h17M8 3v3.2M16 3v3.2"/></svg>',
        'documents' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5A1.5 1.5 0 0 1 5.5 5h4l1.6 2h8.4A1.5 1.5 0 0 1 21 8.5v9A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5v-9"/></svg>',
        'atak' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/></svg>',
        'recruitment' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9.5" cy="8" r="3.2"/><path stroke-linecap="round" d="M3.5 20v-1.3c0-2.7 2.7-4.8 6-4.8s6 2.1 6 4.8V20"/><path stroke-linecap="round" d="M18.5 6.5v5M16 9h5"/></svg>',
        'invitations' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5.5" width="17" height="13" rx="1.6"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 7 6.7 5a2 2 0 0 0 2.1 0l6.7-5"/></svg>',
        'backoffice' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5v-7Z"/><path stroke-linecap="round" d="M4 8.5 12 13l8-4.5M12 13v7"/></svg>',
        'admin' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5 19 6.5v5.4c0 4.6-3 7.7-7 8.6-4-.9-7-4-7-8.6V6.5L12 3.5Z"/><path stroke-linecap="round" d="M12 8v4.5M12 15.2h.01"/></svg>',
        'profile' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8.2" r="3.4"/><path stroke-linecap="round" d="M4.8 19.5c.9-3.4 3.8-5.4 7.2-5.4s6.3 2 7.2 5.4"/></svg>',
    ];

    return $icons[$key] ?? '';
};

/**
 * @param list<array{label:string,href:string,hint?:string}|null> $links
 * @return list<array{label:string,href:string,hint?:string}>
 */
$links = static function (array $items): array {
    $out = [];
    foreach ($items as $item) {
        if (is_array($item) && isset($item['label'], $item['href']) && $item['href'] !== '') {
            $out[] = $item;
        }
    }

    return $out;
};

/**
 * @param list<array{label:string,href:string,hint?:string}> $tileLinks
 */
$tile = static function (
    string $id,
    string $label,
    string $hint,
    string $variant,
    ?string $badge,
    array $tileLinks,
    string $iconKey = '',
    string $extraHtml = ''
) use ($activeDashNav): array {
    return [
        'id' => $id,
        'label' => $label,
        'hint' => $hint,
        'variant' => $variant,
        'badge' => $badge,
        'active' => $id === $activeDashNav,
        'links' => $tileLinks,
        'icon' => $iconKey,
        'extra' => $extraHtml,
    ];
};

$navTiles = [
    $tile('overview', 'Vue d’ensemble', 'Synthèse du jour', 'default', null, $links([
        ['label' => 'Tableau de bord', 'href' => url('dashboard'), 'hint' => 'Briefing et indicateurs'],
        ['label' => 'Annonces', 'href' => url('dashboard') . '#dashboard-announce', 'hint' => 'Alertes et annonces'],
        ['label' => 'Boîte de réception', 'href' => url('boite-reception'), 'hint' => 'Messages et éléments à traiter'],
    ]), 'overview'),
    $tile('hub', 'Hub', 'Centre de commandement', 'default', null, $links([
        ['label' => 'Ouvrir le hub', 'href' => url('hub'), 'hint' => 'Modules et raccourcis'],
        ['label' => 'Manœuvres (présences)', 'href' => url('manoeuvres'), 'hint' => 'Confirmations aux créneaux'],
        ['label' => 'Communautés', 'href' => url('communities'), 'hint' => 'Registre des unités'],
        ['label' => 'Premiers pas', 'href' => url('onboarding'), 'hint' => 'Parcours d’accueil'],
    ]), 'hub'),
];

if ($canForum) {
    $navTiles[] = $tile('forum', 'Forum', 'Échanges de la communauté', 'default', null, $links([
        ['label' => 'Accueil du forum', 'href' => url('forum'), 'hint' => 'Rubriques et sujets'],
        ['label' => 'Messagerie interne', 'href' => url('messages'), 'hint' => 'Échanges avec l’encadrement'],
        $canForumCreate
            ? ['label' => 'Publier un sujet', 'href' => url('forum/new-topic'), 'hint' => 'Démarrer une discussion']
            : null,
    ]));
}

$navTiles[] = $tile(
    'trainings',
    'Formations',
    'Catalogue et parcours',
    'default',
    $trainCount > 0 ? (string) $trainCount : null,
    $links([
        ['label' => 'Catalogue', 'href' => url('formations'), 'hint' => 'Parcours disponibles', 'lms_module' => 'formation'],
        ['label' => 'Mes formations', 'href' => url('formations/mes-formations'), 'hint' => 'Inscriptions en cours', 'lms_module' => 'formation'],
        ['label' => 'Compétences', 'href' => url('formations/competences'), 'hint' => 'Progression', 'lms_module' => 'formation'],
        ['label' => 'Code d’accès', 'href' => url('formations/code-acces'), 'hint' => 'Rejoindre un parcours', 'lms_module' => 'formation'],
        $canTrainingManage
            ? ['label' => 'Pilotage des formations', 'href' => url('formation'), 'hint' => 'Espace instructeurs', 'lms_module' => 'formation']
            : null,
        $canTrainingManage
            ? ['label' => 'Créer un entraînement', 'href' => url('formations/creer'), 'hint' => 'Publication', 'lms_module' => 'formation']
            : null,
    ])
);

$navTiles[] = $tile('effectifs', 'Effectifs', 'Annuaire et structure', 'default', null, $links([
    ['label' => 'Annuaire', 'href' => url('personnel'), 'hint' => 'Rechercher un membre', 'lms_module' => 'effectifs'],
    $canOrbat
        ? ['label' => 'Organisation (ORBAT)', 'href' => url('orbat'), 'hint' => 'Vue hiérarchique', 'lms_module' => 'effectifs']
        : null,
    ['label' => 'Ma fiche', 'href' => url('personnel/me'), 'hint' => 'Identité et grade', 'lms_module' => 'effectifs'],
    ['label' => 'Espace RH', 'href' => url('personnel/mon-espace-rh'), 'hint' => 'Dossier administratif', 'lms_module' => 'effectifs'],
    ['label' => 'Distinctions', 'href' => url('distinctions'), 'hint' => 'Reconnaissances', 'lms_module' => 'effectifs'],
]));

$navTiles[] = $tile(
    'events',
    'Manœuvres',
    'Calendrier et opérations',
    'default',
    $opsCount > 0 ? (string) $opsCount : null,
    $links([
        ['label' => 'Calendrier', 'href' => url('evenements'), 'hint' => 'Prochaines opérations'],
        ['label' => 'Nouvelle manœuvre', 'href' => url('evenements'), 'hint' => 'Ouvrir le planning'],
        $canAdmin
            ? ['label' => 'Gestion état-major', 'href' => url('back-office/events'), 'hint' => 'Administration des événements']
            : null,
    ]),
    'events'
);

$espaceTiles = [];

if ($canDocs) {
    $espaceTiles[] = $tile('documents', 'Documents', 'Ordres et références', 'default', null, $links([
        ['label' => 'Bibliothèque', 'href' => url('documents'), 'hint' => 'Tous les documents'],
        ['label' => 'Collections', 'href' => url('documents/collections'), 'hint' => 'Dossiers thématiques'],
        ['label' => 'Accréditation', 'href' => url('documents/accreditation'), 'hint' => 'Niveaux d’accès'],
        $canDocsUpload
            ? ['label' => 'Gestion', 'href' => url('documents/gestion'), 'hint' => 'Publication']
            : null,
    ]), 'documents');
}

$espaceTiles[] = $tile('atak', 'ATAK', 'Carte tactique', 'default', null, $links([
    ['label' => 'Carte', 'href' => url('atak'), 'hint' => 'Situation tactique'],
    ['label' => 'Première liaison', 'href' => url('atak/premiere-liaison'), 'hint' => 'Mise en service'],
    ['label' => 'Configuration', 'href' => url('atak/setup'), 'hint' => 'Paramètres'],
    ['label' => 'Tutoriel', 'href' => url('atak/tuto'), 'hint' => 'Prise en main'],
]), 'atak');

if ($canRecruit) {
    $espaceTiles[] = $tile(
        'recruitment',
        'Recrutement',
        'Candidatures à traiter',
        'default',
        $staffCount > 0 ? (string) $staffCount : null,
        $links([
            ['label' => 'File des dossiers', 'href' => url('back-office/recruitments') . '?status=submitted', 'hint' => 'À instruire', 'lms_module' => 'recrutement'],
            ['label' => 'Offres', 'href' => url('back-office/recruitment/offers'), 'hint' => 'Avis d’ouverture', 'lms_module' => 'recrutement'],
            ['label' => 'Espace recrutement', 'href' => url('back-office/ressources/recrutement'), 'hint' => 'Pilotage', 'lms_module' => 'recrutement'],
            ['label' => 'Mur équipe', 'href' => url('back-office/recruitments/equipe'), 'hint' => 'Coordination', 'lms_module' => 'recrutement'],
            ['label' => 'Page publique', 'href' => url('recrutement'), 'hint' => 'Vitrine candidats'],
        ]),
        'recruitment'
    );
}

// --- Back-office (orange) : outils de gestion courante de la communauté ---
$backofficeTiles = [];
if ($canInvitationsTile) {
    $backofficeTiles[] = $tile(
        'invitations',
        'Invitations',
        'Codes d’accès à la communauté',
        'bo',
        $pendingInvitationsForTile > 0 ? (string) $pendingInvitationsForTile : null,
        $links([
            ['label' => 'File des invitations', 'href' => url('back-office/invitations/envoyees'), 'hint' => 'En attente de réponse'],
            ['label' => 'Envoyer une invitation', 'href' => url('back-office/invitations'), 'hint' => 'Nouveau code d’accès'],
        ]),
        'invitations'
    );
}
if ($canAdmin) {
    $backofficeTiles[] = $tile('backoffice', 'Back-office', 'Espace état-major', 'bo', null, $links([
        ['label' => 'Back-office', 'href' => url('back-office'), 'hint' => 'Espace état-major'],
        ['label' => 'Centre d’opérations', 'href' => url('back-office/centre-operations'), 'hint' => 'File actionnable'],
        ['label' => 'Utilisateurs', 'href' => url('back-office/users'), 'hint' => 'Comptes de la communauté'],
        ['label' => 'Rubriques du forum', 'href' => url('back-office/categories'), 'hint' => 'Arborescence'],
        ['label' => 'Paramètres de la communauté', 'href' => url('back-office/community'), 'hint' => 'Identité, images et options'],
    ]), 'backoffice');
}

// --- Administration (rouge) : administration de la plateforme (droits système) ---
$adminTiles = [];
if ($canSystem) {
    $adminTiles[] = $tile('admin', 'Administration', 'Administration du site', 'admin', null, $links([
        ['label' => 'Plateforme', 'href' => url('admin'), 'hint' => 'Administration du site'],
    ]), 'admin');
}

// --- Compte : identité, ancienneté, identifiants, préférences e-mail ---
ob_start();
$acctInitials = function_exists('user_display_initials') ? user_display_initials($acctDisplayName) : mb_strtoupper(mb_substr($acctDisplayName, 0, 1));
?>
<div class="dash-rail__identity">
    <span class="dash-rail__identity-avatar<?= $acctAvatarSrc ? ' dash-rail__identity-avatar--photo' : '' ?>">
        <?php if ($acctAvatarSrc): ?>
            <img src="<?= $h((string) $acctAvatarSrc) ?>" alt="Photo de compte" class="dash-rail__identity-avatar-img" loading="lazy" data-img-fallback="avatar" data-img-initials="<?= $h($acctInitials) ?>" data-img-label="Photo de compte indisponible">
        <?php else: ?>
            <?= $h($acctInitials) ?>
        <?php endif; ?>
    </span>
    <span class="dash-rail__identity-meta">
        <strong><?= $h($acctDisplayName) ?></strong>
        <em>
            <?= $h($acctRoleHint) ?>
            <?php if ($acctPlatformRole !== '' && strcasecmp($acctPlatformRole, $acctRoleHint) !== 0): ?>
                · <?= $h($acctPlatformRole) ?>
            <?php endif; ?>
        </em>
    </span>
</div>
<dl class="dash-rail__facts">
    <div class="dash-rail__fact">
        <dt>Matricule</dt>
        <dd><?= $h($acctMatricule ? (string) $acctMatricule : 'Non attribué') ?></dd>
    </div>
    <div class="dash-rail__fact">
        <dt>Ancienneté</dt>
        <dd>
            <?= $h($acctMemberSinceLabel) ?>
            <?php if ($acctMemberSinceDate): ?>
                <span class="dash-rail__fact-sub">depuis le <?= $h($acctMemberSinceDate) ?></span>
            <?php endif; ?>
        </dd>
    </div>
    <?php if ($acctCandidateDossier !== null): ?>
    <div class="dash-rail__fact">
        <dt>Numéro de candidat</dt>
        <dd>Dossier n°<?= (int) $acctCandidateDossier ?></dd>
    </div>
    <?php endif; ?>
    <?php if ($acctAthenaId !== ''): ?>
    <div class="dash-rail__fact">
        <dt>Numéro de session</dt>
        <dd><?= $h($acctAthenaId) ?></dd>
    </div>
    <?php endif; ?>
    <div class="dash-rail__fact">
        <dt>Statut</dt>
        <dd><?= $h($statutLabel) ?></dd>
    </div>
    <div class="dash-rail__fact">
        <dt>Communauté</dt>
        <dd><?= $h($unitLabel) ?></dd>
    </div>
</dl>
<div class="dash-rail__email-card">
    <p class="dash-rail__email-card-title">Préférences e-mail</p>
    <p class="dash-rail__email-card-text">
        <?php if ($acctEmailDisabledCount > 0): ?>
            <?= (int) $acctEmailDisabledCount ?> alerte<?= $acctEmailDisabledCount > 1 ? 's' : '' ?> par e-mail désactivée<?= $acctEmailDisabledCount > 1 ? 's' : '' ?>, les autres restent actives.
        <?php else: ?>
            Toutes les alertes par e-mail sont actives.
        <?php endif; ?>
    </p>
    <a class="dash-rail__email-card-link" href="<?= $h(url('account/preferences')) ?>">Gérer mes préférences e-mail</a>
</div>
<?php
$accountExtraHtml = (string) ob_get_clean();

$accountTiles = [
    $tile('profile', 'Mon compte', 'Identité et préférences', 'accent', null, $links([
        ['label' => 'Ma fiche', 'href' => url('personnel/me'), 'hint' => 'Identité et grade'],
        ['label' => 'Modifier le dossier', 'href' => url('personnel/me/edit'), 'hint' => 'Mise à jour'],
        ['label' => 'Compte', 'href' => url('account'), 'hint' => 'Sécurité et médias'],
        ['label' => 'Préférences', 'href' => url('account/preferences'), 'hint' => 'Langue, affichage et e-mail'],
        ['label' => 'Mes données', 'href' => url('account/donnees'), 'hint' => 'Export RGPD'],
        ['label' => 'Tutoriels', 'href' => url('personnel/tutorials'), 'hint' => 'Guides'],
    ]), 'profile', $accountExtraHtml),
];

$renderLinks = static function (array $item) use ($h): void {
    ?>
    <ul class="dash-rail__links" role="list">
        <?php foreach ($item['links'] as $link): ?>
            <?php
            $lmsModule = isset($link['lms_module']) ? trim((string) $link['lms_module']) : '';
            ?>
            <li>
                <a
                    class="dash-rail__link"
                    href="<?= $h((string) $link['href']) ?>"
                    <?php if ($lmsModule !== ''): ?>data-lms-module-entry="<?= $h($lmsModule) ?>"<?php endif; ?>
                >
                    <span class="dash-rail__link-label"><?= $h((string) $link['label']) ?></span>
                    <?php if (!empty($link['hint'])): ?>
                        <span class="dash-rail__link-hint"><?= $h((string) $link['hint']) ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};

$renderTile = static function (array $item) use ($num, $h, $renderLinks, $icon): void {
    $id = (string) $item['id'];
    $label = (string) $item['label'];
    $active = !empty($item['active']);
    $variant = (string) ($item['variant'] ?? 'default');
    $badge = $item['badge'] ?? null;
    $classes = ['dash-rail__tile'];
    if ($active) {
        $classes[] = 'is-active';
    }
    if ($variant === 'accent') {
        $classes[] = 'dash-rail__tile--accent';
    } elseif ($variant === 'bo') {
        $classes[] = 'dash-rail__tile--bo';
    } elseif ($variant === 'admin') {
        $classes[] = 'dash-rail__tile--admin';
    } elseif ($variant === 'disabled') {
        $classes[] = 'is-disabled';
    }
    $disabled = $variant === 'disabled';
    $nestedId = 'dash-rail-nested-' . $id;
    $idxLabel = $num();
    $iconMarkup = $icon((string) ($item['icon'] ?? ''));
    ?>
    <div class="dash-rail__item" data-dash-rail-item="<?= $h($id) ?>">
        <button
            type="button"
            class="<?= $h(implode(' ', $classes)) ?>"
            data-dash-rail-open="<?= $h($id) ?>"
            aria-expanded="false"
            aria-controls="<?= $h($nestedId) ?>"
            <?php if ($disabled): ?>disabled aria-disabled="true"<?php endif; ?>
        >
            <?php if ($iconMarkup !== ''): ?>
                <span class="dash-rail__tile-icon" aria-hidden="true"><?= $iconMarkup ?></span>
            <?php else: ?>
                <b class="dash-rail__idx"><?= $h($idxLabel) ?></b>
            <?php endif; ?>
            <span class="dash-rail__copy">
                <strong class="dash-rail__label"><?= $h($label) ?></strong>
                <em class="dash-rail__hint"><?= $h((string) $item['hint']) ?></em>
            </span>
            <?php if ($active): ?>
                <i class="dash-rail__meta dash-rail__meta--actif">Actif</i>
            <?php elseif (is_string($badge) && $badge !== ''): ?>
                <i class="dash-rail__meta dash-rail__meta--badge" aria-label="<?= $h($badge . ' élément(s)') ?>"><?= $h($badge) ?></i>
            <?php elseif ($disabled): ?>
                <i class="dash-rail__meta dash-rail__meta--soon">Bientôt</i>
            <?php else: ?>
                <i class="dash-rail__meta dash-rail__meta--empty" aria-hidden="true">—</i>
            <?php endif; ?>
        </button>
        <div
            class="dash-rail__nested"
            id="<?= $h($nestedId) ?>"
            data-dash-rail-nested="<?= $h($id) ?>"
            data-dash-rail-title="<?= $h($label) ?>"
            data-dash-rail-lead="<?= $h((string) $item['hint']) ?>"
            hidden
        >
            <div class="dash-rail__nested-body">
                <?php if (!empty($item['extra'])): ?>
                    <?= $item['extra'] ?>
                <?php endif; ?>
                <?php $renderLinks($item); ?>
            </div>
        </div>
    </div>
    <?php
};
?>
<aside class="dash-rail" id="dash-rail" aria-label="Navigation du tableau de bord" data-dash-rail>
    <div class="dash-rail__inner lms-dark-panel">
        <div class="dash-rail__compact" aria-hidden="true">
            <span>Athena</span>
        </div>

        <div class="dash-rail__panel">
            <div class="dash-rail__view dash-rail__view--root" data-dash-rail-root>
                <div class="dash-rail__brand">
                    <p class="dash-rail__eyebrow">Athena / Commandement</p>
                    <h2 class="dash-rail__title">Tableau de bord</h2>
                    <p class="dash-rail__unit"><?= $h($unitLabel) ?></p>
                </div>

                <nav class="dash-rail__nav" aria-label="Rubriques">
                    <p class="dash-rail__section">Navigation</p>
                    <?php foreach ($navTiles as $item): ?>
                        <?php $renderTile($item); ?>
                    <?php endforeach; ?>

                    <?php if ($espaceTiles !== []): ?>
                        <p class="dash-rail__section">Espaces</p>
                        <?php foreach ($espaceTiles as $item): ?>
                            <?php $renderTile($item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($backofficeTiles !== []): ?>
                        <p class="dash-rail__section dash-rail__section--bo">Back-office</p>
                        <?php foreach ($backofficeTiles as $item): ?>
                            <?php $renderTile($item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($adminTiles !== []): ?>
                        <p class="dash-rail__section dash-rail__section--admin">Administration</p>
                        <?php foreach ($adminTiles as $item): ?>
                            <?php $renderTile($item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <p class="dash-rail__section">Compte</p>
                    <?php foreach ($accountTiles as $item): ?>
                        <?php $renderTile($item); ?>
                    <?php endforeach; ?>
                </nav>

                <div class="dash-rail__foot">
                    <p class="dash-rail__foot-label">Situation</p>
                    <div class="dash-rail__foot-row">
                        <span class="dash-rail__foot-meta">Portail membre</span>
                        <span class="dash-rail__foot-live"><?= $h($statutLabel) ?></span>
                    </div>
                    <p class="dash-rail__foot-hint">Survolez le rail · Cliquez une tuile pour ouvrir son panneau</p>
                </div>
            </div>

            <div
                class="dash-rail__view dash-rail__view--drill"
                data-dash-rail-drill
                hidden
                role="region"
                aria-labelledby="dash-rail-drill-heading"
            >
                <div class="dash-rail__drill-head">
                    <button
                        type="button"
                        class="dash-rail__back"
                        data-dash-rail-back
                        aria-label="Retour à la navigation"
                    >
                        <span aria-hidden="true">←</span>
                        <span>Retour</span>
                    </button>
                    <div class="dash-rail__drill-titles">
                        <p class="dash-rail__drill-kicker">Rubrique</p>
                        <h3 class="dash-rail__drill-title" id="dash-rail-drill-heading">Rubrique</h3>
                        <p class="dash-rail__drill-lead" data-dash-rail-drill-lead></p>
                    </div>
                </div>
                <div class="dash-rail__drill-body" data-dash-rail-drill-body></div>
            </div>
        </div>
    </div>
</aside>
