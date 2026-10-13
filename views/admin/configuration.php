<?php
declare(strict_types=1);

/**
 * Configuration de la communauté (back-office) : sommaire par sections, recherche de réglage,
 * formulaire « Affichage des rôles » avec états (modifié / enregistré), référentiels et historique.
 * Comportements : public/assets/js/back-office-configuration.js. Styles : back-office-configuration.css.
 */

$units = $units ?? [];
$grades = $grades ?? [];
$matriculeConfig = $matriculeConfig ?? null;
$adminPanels = $adminPanels ?? [];
$tenant = $tenant ?? [];
$community = is_array($community ?? null) ? $community : [];
$onboardingHealth = $onboardingHealth ?? ['gaps' => [], 'needs_recovery' => false];
$enlistmentCounts = $enlistmentCounts ?? [];
$publicCommunityUrl = (string) ($publicCommunityUrl ?? '');
$settings = is_array($settings ?? null) ? $settings : [];
$configHistory = is_array($configHistory ?? null) ? $configHistory : [];
$appDebug = (bool) ($appDebug ?? config('app.debug', false));

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$gate = \App\Core\Gate::getInstance();
$canDocs = $gate->allows('documents.upload') || $gate->allows('admin.access');
$canTraining = $gate->allows('training.manage') || $gate->allows('training.assign') || $gate->allows('admin.access');
$canOrgAdmin = $gate->allows('admin.organization') || $gate->allows('admin.access');
$canModerateMembers = $gate->allows('admin.members.moderate');
$canModerateForum = $gate->allows('forum.moderate') || $gate->allows('forum.moderate_organization') || $canOrgAdmin;
$forumVisible = !function_exists('forum_public_nav_visible') || forum_public_nav_visible();

$nUnits = count($units);
$nGrades = count($grades);
$nPanels = count($adminPanels);
$pendingEnlist = (int) ($enlistmentCounts['submitted'] ?? 0);

$orbatVis = (string) ($community['orbat_visibility'] ?? 'members');
$orbatLabel = match ($orbatVis) {
    'public' => 'Public',
    'command' => 'Commandement',
    default => 'Membres uniquement',
};
$layoutPub = ($community['public_page_layout'] ?? 'legacy') === 'showcase' ? 'Vitrine' : 'Classique';
$locale = (string) ($community['default_locale'] ?? ($tenant['default_locale'] ?? 'fr-FR'));
$gradeSystem = trim((string) ($settings['grade_system_code'] ?? ''));
$tenantName = trim((string) ($tenant['name'] ?? ''));
$tenantSlug = trim((string) ($tenant['slug'] ?? ''));

/* Valeurs du formulaire « Affichage des rôles » (mêmes normalisations que le contrôleur). */
$memberChooseRole = !empty($community['member_can_choose_display_role']);
$badgesMode = (string) ($community['display_badges_mode'] ?? 'primary_only');
if ($badgesMode !== 'multi') {
    $badgesMode = 'primary_only';
}
$orgRoleLabels = (string) ($community['organization_role_labels'] ?? 'fr');
if ($orgRoleLabels !== 'en') {
    $orgRoleLabels = 'fr';
}
$savedSection = (string) \App\Core\Session::getFlash('config_saved_section', '');

$icon = static function (string $name): string {
    $paths = match ($name) {
        'overview' => '<path d="M4 13h6V4H4zM14 20h6v-9h-6zM14 4v4h6V4zM4 20h6v-3H4z"/>',
        'identity' => '<path d="M12 12a4 4 0 100-8 4 4 0 000 8z"/><path d="M4 21a8 8 0 0116 0"/>',
        'structure' => '<path d="M12 3v5M5 13v-2h14v2M5 13v3M19 13v3M12 8v8"/><rect x="9" y="3" width="6" height="0.01"/><circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>',
        'access' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>',
        'recruit' => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        'display' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0L3 13V3h10l7.6 7.6a2 2 0 010 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'content' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
        'moderation' => '<path d="M12 3l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V7l8-4z"/><path d="M9 12l2 2 4-4"/>',
        'data' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'history' => '<path d="M3 12a9 9 0 109-9 9 9 0 00-6.4 2.6L3 8"/><path d="M3 3v5h5M12 7v5l3 3"/>',
        'dev' => '<path d="M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'chevron' => '<path d="M9 6l6 6-6 6"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 012-2h10"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
        'warn' => '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17.5v.01"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="bo-cfg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};

/*
 * Sommaire des modules. Chaque entrée : lien, titre, description, valeur actuelle (facultative),
 * mots-clés de recherche et condition d’affichage (droits inchangés par rapport à l’ancienne page).
 */
$sections = [
    [
        'id' => 'identite',
        'icon' => 'identity',
        'title' => 'Identité & page publique',
        'lead' => 'Ce que voient les visiteurs et les membres : nom, adresse, vitrine et annonces.',
        'items' => [
            ['href' => url('back-office/community'), 'title' => 'Identité & code rejoindre', 'desc' => 'Nom, adresse courte du lien et code communauté pour rejoindre l’unité.', 'value' => $tenantName !== '' ? $tenantName : null, 'value_raw' => true, 'keywords' => 'nom slug url code invitation rejoindre identite'],
            ['href' => url('back-office/community/presentation'), 'title' => 'Fiche registre & vitrine', 'desc' => 'Textes publics, modèle vitrine ou classique, ORBAT, recrutement, forum visiteurs.', 'value' => $layoutPub . ' · ORBAT ' . mb_strtolower($orbatLabel), 'keywords' => 'vitrine classique orbat public presentation registre langue locale'],
            ['href' => url('back-office/configuration-initiale'), 'title' => 'Configuration initiale', 'desc' => 'Assistant des cinq réglages essentiels : identité, contact, inscription, modules visibles, rôle des nouveaux.', 'keywords' => 'assistant demarrage setup logo contact inscription modules'],
            ['href' => url('back-office/alerts'), 'title' => 'Alertes & annonces', 'desc' => 'Bandeaux et messages pour les membres connectés.', 'keywords' => 'bandeau message annonce alerte'],
        ],
    ],
    [
        'id' => 'structure',
        'icon' => 'structure',
        'title' => 'Structure & effectifs',
        'lead' => 'Organigramme, grades et fonctions qui alimentent les fiches personnel.',
        'items' => [
            ['href' => url('back-office/groups'), 'title' => 'Unités & groupes', 'desc' => 'Hiérarchie, publication sur la page publique, fiches unités.', 'value' => $nUnits . ($nUnits > 1 ? ' unités' : ' unité'), 'keywords' => 'orbat organigramme unite groupe section hierarchie'],
            ['href' => url('back-office/teams'), 'title' => 'Équipes', 'desc' => 'Équipes tactiques et composition.', 'keywords' => 'equipe team fireteam'],
            ['href' => url('back-office/referentiels/grades'), 'title' => 'Référentiel grades', 'desc' => 'Rangs, codes OTAN, libellés pour les fiches.', 'value' => $nGrades . ($nGrades > 1 ? ' grades' : ' grade') . ($gradeSystem !== '' ? ' · ' . $gradeSystem : ''), 'keywords' => 'grade rang otan nato referentiel'],
            ['href' => url('back-office/personnel-job-roles'), 'title' => 'Rôles métier & fiches', 'desc' => 'Rôles de poste, affectations et champs dossier.', 'keywords' => 'metier poste fiche dossier affectation'],
            ['href' => url('back-office/positions'), 'title' => 'Postes organisationnels', 'desc' => 'Intitulés de fonction et affectations, distincts des rôles et habilitations.', 'keywords' => 'poste fonction intitule affectation'],
        ],
    ],
    [
        'id' => 'acces',
        'icon' => 'access',
        'title' => 'Accès & rôles',
        'lead' => 'Qui peut se connecter et ce que chacun peut faire dans la communauté.',
        'items' => [
            ['href' => url('back-office/users'), 'title' => 'Utilisateurs', 'desc' => 'Comptes, désactivation, fiches liées.', 'keywords' => 'compte membre utilisateur desactiver'],
            ['href' => url('back-office/invitations'), 'title' => 'Invitations', 'desc' => 'Inviter par e-mail avec rôle proposé.', 'keywords' => 'inviter email invitation'],
            ['href' => url('back-office/roles-permissions'), 'title' => 'Rôles & permissions', 'desc' => 'Matrice d’accès par module et titulaires.', 'keywords' => 'droits permission matrice acces'],
            ['href' => url('back-office/roles'), 'title' => 'Rôles communautaires', 'desc' => 'Liste des rôles et détail des droits.', 'keywords' => 'role liste droits'],
            ['href' => url('back-office/roles-functions'), 'title' => 'Rôles & fonctions (toile)', 'desc' => 'Catalogue FR/US, graphe, unités, templates.', 'keywords' => 'toile graphe catalogue fonction'],
            ['href' => url('back-office/roles/presets'), 'title' => 'Profils de permissions', 'desc' => 'Appliquer un jeu complet de droits à un rôle en une fois (hors droits plateforme).', 'keywords' => 'profil preset modele droits'],
        ],
    ],
    [
        'id' => 'recrutement',
        'icon' => 'recruit',
        'title' => 'Recrutement & parcours',
        'lead' => 'Candidatures, accueil des recrues et suivi roleplay.',
        'items' => [
            ['href' => url('back-office/recruitments'), 'title' => 'Candidatures', 'desc' => 'Dossiers, décisions, file d’attente.', 'value' => $pendingEnlist > 0 ? $pendingEnlist . ' en attente' : 'Aucune en attente', 'tone' => $pendingEnlist > 0 ? 'warn' : null, 'keywords' => 'candidature recrutement dossier enrolement'],
            ['href' => url('back-office/recruitments/settings'), 'title' => 'Paramètres SLA (recrutement)', 'desc' => 'Délai interne sans action et suivi des dossiers bloqués.', 'keywords' => 'sla delai relance bloque'],
            ['href' => url('back-office/recruitments/messages-prefaits'), 'title' => 'Messages préfaits (recrutement)', 'desc' => 'Modèles de commentaires internes pour traiter les candidatures.', 'keywords' => 'modele message prefait commentaire'],
            ['href' => url('back-office/roleplay-followup'), 'title' => 'Suivi roleplay', 'desc' => 'Pilotage tutorat, timeline dossiers et avancement individuel.', 'keywords' => 'roleplay tutorat suivi timeline'],
            ['href' => url('back-office/roleplay/immersion'), 'title' => 'Parcours d’immersion', 'desc' => 'Suivi d’arrivée : étapes, filières, tuteur et indicateur « dossier prêt ».', 'keywords' => 'immersion arrivee filiere tuteur'],
            ['href' => url('back-office/roleplay/regles-phases'), 'title' => 'Parcours RH', 'desc' => 'Étapes du parcours et conditions de passage à l’étape suivante.', 'keywords' => 'rh phase etape passage regle'],
            ['href' => url('back-office/roleplay/sessions'), 'title' => 'Sessions Arma', 'desc' => 'Types de sessions, pointage et temps retenu pour le suivi.', 'keywords' => 'arma session pointage temps jeu'],
        ],
    ],
    [
        'id' => 'contenu',
        'icon' => 'content',
        'title' => 'Contenu & événements',
        'lead' => 'Forum, agenda, documents, formations et mesure d’audience.',
        'items' => array_values(array_filter([
            $forumVisible ? ['href' => url('back-office/categories'), 'title' => 'Catégories forum', 'desc' => 'Organisation des espaces de discussion.', 'keywords' => 'forum categorie discussion'] : null,
            ['href' => url('back-office/events'), 'title' => 'Événements & pointage', 'desc' => 'Séances, présences, rappels.', 'keywords' => 'evenement agenda presence pointage rappel seance'],
            ['href' => url('back-office/analytics'), 'title' => 'Analytics', 'desc' => 'Indicateurs d’usage de la communauté.', 'keywords' => 'statistiques indicateurs usage audience'],
            $canDocs ? ['href' => url('documents/gestion'), 'title' => 'Documents', 'desc' => 'Bibliothèque et dépôt selon vos droits.', 'keywords' => 'document bibliotheque fichier depot'] : null,
            $canTraining ? ['href' => training_lms_admin_url(), 'title' => 'Formations (LMS)', 'desc' => 'Parcours, inscriptions, certificats.', 'keywords' => 'formation lms cours certificat'] : null,
        ])),
    ],
    [
        'id' => 'moderation',
        'icon' => 'moderation',
        'title' => 'Modération & conformité',
        'lead' => 'Restrictions, files de modération et traçabilité.',
        'items' => array_values(array_filter([
            $canModerateMembers ? ['href' => url('back-office/moderation'), 'title' => 'Restrictions membres', 'desc' => 'Limitations d’activité dans la communauté (formations, documents, etc.).', 'keywords' => 'restriction sanction limitation membre'] : null,
            ['href' => url('back-office/audit'), 'title' => 'Journal d’activité', 'desc' => 'Traçabilité des actions administratives.', 'keywords' => 'journal audit trace historique log'],
            ($canModerateForum && $forumVisible) ? ['href' => url('back-office/forum-moderation'), 'title' => 'Modération forum', 'desc' => 'Files et outils modérateur forum.', 'keywords' => 'forum moderation signalement'] : null,
            $canModerateForum ? ['href' => url('back-office/content-moderation'), 'title' => 'Modération fichiers', 'desc' => 'Approbation des pièces jointes et médias.', 'keywords' => 'fichier media piece jointe approbation'] : null,
        ])),
    ],
];

/* Navigation latérale : sections du sommaire + blocs spécifiques. */
$nav = [['id' => 'apercu', 'icon' => 'overview', 'title' => 'Vue d’ensemble']];
foreach ($sections as $i => $sec) {
    $nav[] = ['id' => $sec['id'], 'icon' => $sec['icon'], 'title' => $sec['title'], 'count' => count($sec['items'])];
    if ($sec['id'] === 'acces' && $canOrgAdmin) {
        $nav[] = ['id' => 'affichage', 'icon' => 'display', 'title' => 'Affichage des rôles'];
    }
}
$nav[] = ['id' => 'donnees', 'icon' => 'data', 'title' => 'Référentiels & fiches'];
$nav[] = ['id' => 'historique', 'icon' => 'history', 'title' => 'Historique des changements'];
if ($appDebug) {
    $nav[] = ['id' => 'dev', 'icon' => 'dev', 'title' => 'Outil de développement'];
}

$roleDisplayFieldLabels = [
    'member_can_choose_display_role' => 'Choix du rôle affiché',
    'display_badges_mode' => 'Affichage des badges',
    'organization_role_labels' => 'Langue des intitulés de rôles',
];
$roleDisplayValueLabel = static function (string $key, mixed $value): string {
    return match ($key) {
        'member_can_choose_display_role' => !empty($value) ? 'Activé' : 'Désactivé',
        'display_badges_mode' => $value === 'multi' ? 'Plusieurs badges' : 'Un seul badge principal',
        'organization_role_labels' => $value === 'en' ? 'Anglais' : 'Français',
        default => is_scalar($value) ? (string) $value : '—',
    };
};
$historyActionLabel = static fn (string $action): string => match ($action) {
    'configuration.member_role_display' => 'Affichage des rôles',
    default => $action,
};

$renderItem = static function (array $item, string $sectionTitle) use ($h, $icon): void {
    $search = mb_strtolower($item['title'] . ' ' . $item['desc'] . ' ' . ($item['keywords'] ?? '') . ' ' . $sectionTitle . ' ' . ($item['value'] ?? ''));
    $tone = (string) ($item['tone'] ?? '');
    ?>
    <li class="bo-cfg-item" data-cfg-search="<?= $h($search) ?>">
        <a class="bo-cfg-item__link" href="<?= $h($item['href']) ?>">
            <span class="bo-cfg-item__body">
                <span class="bo-cfg-item__title"><?= $h($item['title']) ?></span>
                <span class="bo-cfg-item__desc"><?= $h($item['desc']) ?></span>
            </span>
            <?php if (!empty($item['value'])): ?>
            <span class="bo-cfg-item__value<?= $tone !== '' ? ' bo-cfg-item__value--' . $h($tone) : '' ?>"<?= !empty($item['value_raw']) ? ' translate="no"' : '' ?>><?= $h($item['value']) ?></span>
            <?php endif; ?>
            <span class="bo-cfg-item__go"><?= $icon('chevron') ?></span>
        </a>
    </li>
    <?php
};
?>
<div class="bo-cfg" data-bo-cfg>
    <div class="bo-cfg__toolbar">
        <label class="bo-cfg-search" for="bo-cfg-search">
            <?= $icon('search') ?>
            <span class="bo-cfg-sr">Rechercher un réglage</span>
            <input type="search" id="bo-cfg-search" class="bo-cfg-search__input" placeholder="Rechercher un réglage (grades, invitations, vitrine…)" autocomplete="off" data-cfg-search-input>
            <kbd class="bo-cfg-search__kbd" aria-hidden="true">/</kbd>
        </label>
        <p class="bo-cfg-search__status" data-cfg-search-status aria-live="polite" hidden>
            <span data-cfg-search-count>0</span> <span>résultat(s)</span>
        </p>
    </div>

    <div class="bo-cfg__layout">
        <nav class="bo-cfg-nav" aria-label="Sections de la configuration">
            <p class="bo-cfg-nav__kicker">Sections</p>
            <ul class="bo-cfg-nav__list">
                <?php foreach ($nav as $n): ?>
                <li>
                    <a class="bo-cfg-nav__link" href="#cfg-<?= $h($n['id']) ?>" data-cfg-nav="<?= $h($n['id']) ?>">
                        <?= $icon($n['icon']) ?>
                        <span class="bo-cfg-nav__label"><?= $h($n['title']) ?></span>
                        <?php if (isset($n['count'])): ?>
                        <span class="bo-cfg-nav__count" data-cfg-nav-count="<?= $h($n['id']) ?>"><?= (int) $n['count'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="bo-cfg__main">
            <?php if (!empty($onboardingHealth['needs_recovery'])): ?>
            <div class="bo-cfg-alert" role="status" data-cfg-hide-on-search>
                <span class="bo-cfg-alert__icon"><?= $icon('warn') ?></span>
                <div class="bo-cfg-alert__body">
                    <p class="bo-cfg-alert__title">Rattrapage de configuration recommandé</p>
                    <ul class="bo-cfg-alert__list">
                        <?php foreach ($onboardingHealth['gaps'] as $gap): ?>
                        <li><?= $h($gap) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <a class="ath-btn ath-btn--solid bo-cfg-alert__cta" href="<?= $h(url('back-office/onboarding-recovery')) ?>">Assistant de rattrapage</a>
            </div>
            <?php endif; ?>

            <!-- Vue d’ensemble -->
            <section class="bo-cfg-section" id="cfg-apercu" data-cfg-section="apercu" data-cfg-static>
                <header class="bo-cfg-section__head">
                    <span class="bo-cfg-section__icon"><?= $icon('overview') ?></span>
                    <div>
                        <h2 class="bo-cfg-section__title">Vue d’ensemble</h2>
                        <p class="bo-cfg-section__lead">Les réglages clés de votre communauté, en un coup d’œil.</p>
                    </div>
                </header>
                <div class="bo-cfg-stats">
                    <a class="bo-cfg-stat" href="<?= $h(url('back-office/groups')) ?>">
                        <span class="bo-cfg-stat__label">Unités</span>
                        <span class="bo-cfg-stat__value"><?= $nUnits ?></span>
                    </a>
                    <a class="bo-cfg-stat" href="<?= $h(url('back-office/referentiels/grades')) ?>">
                        <span class="bo-cfg-stat__label">Grades</span>
                        <span class="bo-cfg-stat__value"><?= $nGrades ?></span>
                    </a>
                    <a class="bo-cfg-stat<?= $pendingEnlist > 0 ? ' bo-cfg-stat--warn' : '' ?>" href="<?= $h(url('back-office/recruitments')) ?>">
                        <span class="bo-cfg-stat__label">Candidatures en attente</span>
                        <span class="bo-cfg-stat__value"><?= $pendingEnlist ?></span>
                    </a>
                    <a class="bo-cfg-stat" href="#cfg-donnees">
                        <span class="bo-cfg-stat__label">Panneaux fiche perso.</span>
                        <span class="bo-cfg-stat__value"><?= $nPanels ?></span>
                    </a>
                </div>
                <dl class="bo-cfg-facts">
                    <div class="bo-cfg-fact">
                        <dt>Nom affiché</dt>
                        <dd translate="no"><?= $h($tenantName !== '' ? $tenantName : '—') ?></dd>
                    </div>
                    <div class="bo-cfg-fact">
                        <dt>Adresse courte du lien</dt>
                        <dd class="bo-cfg-mono" translate="no"><?= $h($tenantSlug !== '' ? $tenantSlug : '—') ?></dd>
                    </div>
                    <div class="bo-cfg-fact">
                        <dt>Page publique</dt>
                        <dd><?= $h($layoutPub) ?></dd>
                    </div>
                    <div class="bo-cfg-fact">
                        <dt>Visibilité ORBAT</dt>
                        <dd><?= $h($orbatLabel) ?></dd>
                    </div>
                    <div class="bo-cfg-fact">
                        <dt>Langue par défaut</dt>
                        <dd class="bo-cfg-mono" translate="no"><?= $h($locale) ?></dd>
                    </div>
                    <div class="bo-cfg-fact">
                        <dt>Réf. grades (système)</dt>
                        <dd class="bo-cfg-mono" translate="no"><?= $h($gradeSystem !== '' ? $gradeSystem : '—') ?></dd>
                    </div>
                </dl>
                <?php if ($publicCommunityUrl !== ''): ?>
                <div class="bo-cfg-url">
                    <span class="bo-cfg-url__label">Lien de la page publique</span>
                    <code class="bo-cfg-url__value" translate="no"><?= $h($publicCommunityUrl) ?></code>
                    <span class="bo-cfg-url__actions">
                        <button type="button" class="ath-btn bo-cfg-url__btn" data-cfg-copy="<?= $h($publicCommunityUrl) ?>"><?= $icon('copy') ?><span data-cfg-copy-label>Copier</span></button>
                        <a class="ath-btn bo-cfg-url__btn" href="<?= $h($publicCommunityUrl) ?>" target="_blank" rel="noopener"><?= $icon('external') ?><span>Ouvrir</span></a>
                    </span>
                    <span class="bo-cfg-hidden" data-cfg-copied-label>Copié</span>
                </div>
                <?php endif; ?>
            </section>

            <?php foreach ($sections as $sec): ?>
            <section class="bo-cfg-section" id="cfg-<?= $h($sec['id']) ?>" data-cfg-section="<?= $h($sec['id']) ?>">
                <header class="bo-cfg-section__head">
                    <span class="bo-cfg-section__icon"><?= $icon($sec['icon']) ?></span>
                    <div>
                        <h2 class="bo-cfg-section__title"><?= $h($sec['title']) ?></h2>
                        <p class="bo-cfg-section__lead"><?= $h($sec['lead']) ?></p>
                    </div>
                </header>
                <ul class="bo-cfg-list">
                    <?php foreach ($sec['items'] as $item) { $renderItem($item, $sec['title']); } ?>
                </ul>
            </section>

            <?php if ($sec['id'] === 'acces' && $canOrgAdmin): ?>
            <section class="bo-cfg-section bo-cfg-section--form" id="cfg-affichage" data-cfg-section="affichage"
                     data-cfg-search="<?= $h(mb_strtolower('affichage des rôles badge badges rôle affiché priorité langue intitulés français anglais doctrine américaine forum portail')) ?>">
                <header class="bo-cfg-section__head">
                    <span class="bo-cfg-section__icon"><?= $icon('display') ?></span>
                    <div>
                        <h2 class="bo-cfg-section__title">Affichage des rôles</h2>
                        <p class="bo-cfg-section__lead">Comment les rôles de l’organisation apparaissent sur le forum, le portail et dans le back-office. Les droits attachés aux rôles ne changent pas.</p>
                    </div>
                </header>

                <form method="post" action="<?= $h(url('back-office/configuration/member-role-display')) ?>" class="bo-cfg-form" data-cfg-form
                      data-cfg-confirm-leave="Des modifications ne sont pas enregistrées. Quitter quand même ?">
                    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">

                    <div class="bo-cfg-field" data-cfg-field data-cfg-default="0">
                        <div class="bo-cfg-field__text">
                            <p class="bo-cfg-field__label" id="cfg-choose-label">Choix du rôle affiché par le membre <span class="bo-cfg-badge bo-cfg-badge--dirty" data-cfg-dirty-badge hidden>Modifié</span></p>
                            <p class="bo-cfg-field__help">Chaque membre choisit, parmi ses rôles organisation, celui qui apparaît en priorité sur le forum et dans le portail.</p>
                            <p class="bo-cfg-field__default">Par défaut : désactivé</p>
                        </div>
                        <div class="bo-cfg-field__control">
                            <input type="hidden" name="member_can_choose_display_role" value="0">
                            <label class="bo-cfg-switch">
                                <input type="checkbox" name="member_can_choose_display_role" value="1" aria-labelledby="cfg-choose-label" <?= $memberChooseRole ? 'checked' : '' ?>>
                                <span class="bo-cfg-switch__track" aria-hidden="true"><span class="bo-cfg-switch__thumb"></span></span>
                                <span class="bo-cfg-switch__state"><span class="bo-cfg-switch__on">Activé</span><span class="bo-cfg-switch__off">Désactivé</span></span>
                            </label>
                        </div>
                    </div>

                    <div class="bo-cfg-field bo-cfg-field--stack" role="radiogroup" aria-labelledby="cfg-badges-label" data-cfg-field data-cfg-default="primary_only">
                        <p class="bo-cfg-field__label" id="cfg-badges-label">Affichage des badges <span class="bo-cfg-badge bo-cfg-badge--dirty" data-cfg-dirty-badge hidden>Modifié</span></p>
                        <p class="bo-cfg-field__help">Nombre de badges de rôle montrés à côté du nom d’un membre.</p>
                        <div class="bo-cfg-choices">
                            <label class="bo-cfg-choice">
                                <input type="radio" name="display_badges_mode" value="primary_only" <?= $badgesMode === 'primary_only' ? 'checked' : '' ?>>
                                <span class="bo-cfg-choice__box">
                                    <span class="bo-cfg-choice__title">Un seul badge principal <span class="bo-cfg-badge">Par défaut</span></span>
                                    <span class="bo-cfg-choice__desc">Le rôle principal (ou celui choisi par le membre) est le seul affiché.</span>
                                    <span class="bo-cfg-choice__preview" aria-hidden="true"><span class="bo-cfg-chip-demo">Sergent</span></span>
                                </span>
                            </label>
                            <label class="bo-cfg-choice">
                                <input type="radio" name="display_badges_mode" value="multi" <?= $badgesMode === 'multi' ? 'checked' : '' ?>>
                                <span class="bo-cfg-choice__box">
                                    <span class="bo-cfg-choice__title">Plusieurs badges</span>
                                    <span class="bo-cfg-choice__desc">Prévu pour l’affichage de plusieurs rôles quand l’interface le proposera.</span>
                                    <span class="bo-cfg-choice__preview" aria-hidden="true"><span class="bo-cfg-chip-demo">Sergent</span><span class="bo-cfg-chip-demo">Instructeur</span><span class="bo-cfg-chip-demo">Recruteur</span></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="bo-cfg-field bo-cfg-field--stack" role="radiogroup" aria-labelledby="cfg-labels-label" data-cfg-field data-cfg-default="fr">
                        <p class="bo-cfg-field__label" id="cfg-labels-label">Langue des intitulés de rôles (administration) <span class="bo-cfg-badge bo-cfg-badge--dirty" data-cfg-dirty-badge hidden>Modifié</span></p>
                        <p class="bo-cfg-field__help">Invitations, attribution des comptes et listes déroulantes du back-office. Les droits attachés aux rôles ne changent pas.</p>
                        <div class="bo-cfg-choices">
                            <label class="bo-cfg-choice">
                                <input type="radio" name="organization_role_labels" value="fr" <?= $orgRoleLabels === 'fr' ? 'checked' : '' ?>>
                                <span class="bo-cfg-choice__box">
                                    <span class="bo-cfg-choice__title">Français <span class="bo-cfg-badge">Par défaut</span></span>
                                    <span class="bo-cfg-choice__desc">Intitulés de la doctrine française.</span>
                                    <span class="bo-cfg-choice__preview" aria-hidden="true" translate="no"><span class="bo-cfg-chip-demo">Chef de groupe</span><span class="bo-cfg-chip-demo">Opérateur</span></span>
                                </span>
                            </label>
                            <label class="bo-cfg-choice">
                                <input type="radio" name="organization_role_labels" value="en" <?= $orgRoleLabels === 'en' ? 'checked' : '' ?>>
                                <span class="bo-cfg-choice__box">
                                    <span class="bo-cfg-choice__title">Anglais (doctrine américaine)</span>
                                    <span class="bo-cfg-choice__desc">Intitulés de la doctrine américaine.</span>
                                    <span class="bo-cfg-choice__preview" aria-hidden="true" translate="no"><span class="bo-cfg-chip-demo">Squad Leader</span><span class="bo-cfg-chip-demo">Operator</span></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="bo-cfg-savebar" data-cfg-savebar data-state="<?= $savedSection === 'affichage' ? 'saved' : 'clean' ?>">
                        <p class="bo-cfg-savebar__status">
                            <span class="bo-cfg-savebar__dot" aria-hidden="true"></span>
                            <span class="bo-cfg-savebar__msg bo-cfg-savebar__msg--clean">Tout est enregistré.</span>
                            <span class="bo-cfg-savebar__msg bo-cfg-savebar__msg--saved"><?= $icon('check') ?> Modifications enregistrées.</span>
                            <span class="bo-cfg-savebar__msg bo-cfg-savebar__msg--dirty">Modifications non enregistrées : <strong data-cfg-dirty-count>0</strong></span>
                        </p>
                        <div class="bo-cfg-savebar__actions">
                            <button type="button" class="ath-btn bo-cfg-savebar__ghost" data-cfg-defaults>Valeurs par défaut</button>
                            <button type="button" class="ath-btn bo-cfg-savebar__ghost" data-cfg-reset disabled>Annuler</button>
                            <button type="submit" class="ath-btn ath-btn--solid" data-cfg-submit disabled>Enregistrer</button>
                        </div>
                    </div>
                </form>
            </section>
            <?php endif; ?>
            <?php endforeach; ?>

            <!-- Référentiels & fiches personnel (lecture) -->
            <section class="bo-cfg-section" id="cfg-donnees" data-cfg-section="donnees"
                     data-cfg-search="<?= $h(mb_strtolower('référentiels fiches personnel unités grades matricule préfixe format numéro panneaux administratifs')) ?>">
                <header class="bo-cfg-section__head">
                    <span class="bo-cfg-section__icon"><?= $icon('data') ?></span>
                    <div>
                        <h2 class="bo-cfg-section__title">Référentiels & fiches personnel</h2>
                        <p class="bo-cfg-section__lead">Aperçu en lecture seule. Chaque bloc renvoie vers son écran d’édition.</p>
                    </div>
                </header>
                <div class="bo-cfg-data">
                    <article class="bo-cfg-card bo-cfg-card--wide">
                        <header class="bo-cfg-card__head">
                            <h3 class="bo-cfg-card__title">Unités, équipes & groupes</h3>
                            <span class="bo-cfg-card__actions">
                                <a class="ath-btn" href="<?= $h(url('back-office/groups')) ?>">Liste</a>
                                <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/groups/create')) ?>">Nouvelle unité</a>
                            </span>
                        </header>
                        <?php if ($units === []): ?>
                        <p class="bo-cfg-empty">Aucune unité. <a class="bo-cfg-link" href="<?= $h(url('back-office/groups/create')) ?>">Créer une unité</a>.</p>
                        <?php else: ?>
                        <div class="bo-cfg-table-wrap">
                            <table class="bo-cfg-table">
                                <thead><tr><th>Nom</th><th>Type</th><th>Code</th><th><span class="bo-cfg-sr">Actions</span></th></tr></thead>
                                <tbody>
                                    <?php foreach ($units as $u): ?>
                                    <tr>
                                        <td class="bo-cfg-table__strong" translate="no"><?= $h($u['name'] ?? '') ?></td>
                                        <td><?= $h($u['type'] ?? '—') ?></td>
                                        <td class="bo-cfg-mono" translate="no"><?= $h($u['code'] ?? '—') ?></td>
                                        <td class="bo-cfg-table__end"><a class="bo-cfg-link" href="<?= $h(url('back-office/groups/' . (int) ($u['id'] ?? 0) . '/edit')) ?>">Modifier</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </article>

                    <article class="bo-cfg-card">
                        <header class="bo-cfg-card__head">
                            <h3 class="bo-cfg-card__title">Grades</h3>
                            <a class="bo-cfg-link" href="<?= $h(url('back-office/referentiels/grades')) ?>">Éditer le référentiel</a>
                        </header>
                        <?php if ($grades === []): ?>
                        <p class="bo-cfg-empty">Aucun grade défini.</p>
                        <?php else: ?>
                        <ul class="bo-cfg-grades">
                            <?php foreach ($grades as $g): ?>
                            <?php
                            $gradeShortLabel = (string) ($g['label_short'] ?? $g['short_name'] ?? '');
                            $gradeNatoCode = trim((string) ($g['label_otan'] ?? $g['nato_code'] ?? ''));
                            ?>
                            <li>
                                <span class="bo-cfg-grades__name" translate="no"><?= $h($g['label_long'] ?? $g['name'] ?? '') ?></span>
                                <span class="bo-cfg-grades__code" translate="no"><?= $h($gradeShortLabel) ?><?= $gradeNatoCode !== '' ? ' · ' . $h($gradeNatoCode) : '' ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </article>

                    <article class="bo-cfg-card">
                        <header class="bo-cfg-card__head">
                            <h3 class="bo-cfg-card__title">Matricule</h3>
                        </header>
                        <?php if ($matriculeConfig): ?>
                        <dl class="bo-cfg-kv">
                            <div><dt>Préfixe</dt><dd class="bo-cfg-mono" translate="no"><?= $h($matriculeConfig['prefix'] ?? '—') ?></dd></div>
                            <div><dt>Format</dt><dd class="bo-cfg-mono" translate="no"><?= $h($matriculeConfig['format_pattern'] ?? '—') ?></dd></div>
                            <div><dt>Prochain numéro</dt><dd class="bo-cfg-mono"><?= (int) ($matriculeConfig['next_number'] ?? 0) ?></dd></div>
                        </dl>
                        <p class="bo-cfg-note">Utilisé lors de la génération depuis la fiche personnel.</p>
                        <?php else: ?>
                        <p class="bo-cfg-empty">Créée à la première génération de matricule.</p>
                        <?php endif; ?>
                    </article>

                    <article class="bo-cfg-card bo-cfg-card--wide">
                        <header class="bo-cfg-card__head">
                            <h3 class="bo-cfg-card__title">Panneaux administratifs (fiche personnel)</h3>
                        </header>
                        <?php if ($adminPanels === []): ?>
                        <p class="bo-cfg-empty">Aucun panneau configuré.</p>
                        <?php else: ?>
                        <div class="bo-cfg-table-wrap">
                            <table class="bo-cfg-table">
                                <thead><tr><th>Nom</th><th>Référence</th><th>Ordre</th></tr></thead>
                                <tbody>
                                    <?php foreach ($adminPanels as $p): ?>
                                    <tr>
                                        <td class="bo-cfg-table__strong" translate="no"><?= $h($p['name'] ?? '') ?></td>
                                        <td class="bo-cfg-mono" translate="no"><?= $h($p['slug'] ?? '') ?></td>
                                        <td class="bo-cfg-mono"><?= (int) ($p['display_order'] ?? 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </article>
                </div>
            </section>

            <!-- Historique des changements faits depuis cette page -->
            <section class="bo-cfg-section" id="cfg-historique" data-cfg-section="historique"
                     data-cfg-search="<?= $h(mb_strtolower('historique changements journal audit modifications')) ?>">
                <header class="bo-cfg-section__head">
                    <span class="bo-cfg-section__icon"><?= $icon('history') ?></span>
                    <div>
                        <h2 class="bo-cfg-section__title">Historique des changements</h2>
                        <p class="bo-cfg-section__lead">Derniers réglages modifiés depuis cette page, avec l’auteur et l’ancienne valeur.</p>
                    </div>
                    <a class="bo-cfg-link bo-cfg-section__more" href="<?= $h(url('back-office/audit') . '?action=' . rawurlencode(\App\Controllers\Admin\AdminConfigurationController::AUDIT_ACTION_PREFIX)) ?>">Journal complet</a>
                </header>
                <?php if ($configHistory === []): ?>
                <p class="bo-cfg-empty">Aucun changement enregistré pour l’instant. Les prochaines modifications apparaîtront ici.</p>
                <?php else: ?>
                <ol class="bo-cfg-history">
                    <?php foreach ($configHistory as $row): ?>
                    <?php
                    $actor = trim((string) ($row['actor_callsign'] ?? '')) ?: trim((string) ($row['actor_display_name'] ?? '')) ?: '—';
                    $old = json_decode((string) ($row['old_value'] ?? ''), true);
                    $new = json_decode((string) ($row['new_value'] ?? ''), true);
                    $old = is_array($old) ? $old : [];
                    $new = is_array($new) ? $new : [];
                    $ts = strtotime((string) ($row['created_at'] ?? '')) ?: null;
                    ?>
                    <li class="bo-cfg-history__item">
                        <span class="bo-cfg-history__dot" aria-hidden="true"></span>
                        <div class="bo-cfg-history__body">
                            <p class="bo-cfg-history__head">
                                <strong><?= $h($historyActionLabel((string) ($row['action'] ?? ''))) ?></strong>
                                <span class="bo-cfg-history__meta">par <span translate="no"><?= $h($actor) ?></span><?php if ($ts): ?> · <time datetime="<?= $h(date('c', $ts)) ?>" translate="no"><?= $h(date('d/m/Y H:i', $ts)) ?></time><?php endif; ?></span>
                            </p>
                            <?php if ($new !== []): ?>
                            <ul class="bo-cfg-history__changes">
                                <?php foreach ($new as $k => $v): ?>
                                <li>
                                    <span><?= $h($roleDisplayFieldLabels[$k] ?? $k) ?> :</span>
                                    <?php if (array_key_exists($k, $old)): ?><del><?= $h($roleDisplayValueLabel((string) $k, $old[$k])) ?></del> → <?php endif; ?>
                                    <ins><?= $h($roleDisplayValueLabel((string) $k, $v)) ?></ins>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </section>

            <?php if ($appDebug): ?>
            <section class="bo-cfg-section bo-cfg-section--danger" id="cfg-dev" data-cfg-section="dev"
                     data-cfg-search="<?= $h(mb_strtolower('outil de développement debug recrues candidatures acceptées mise à jour')) ?>">
                <header class="bo-cfg-section__head">
                    <span class="bo-cfg-section__icon"><?= $icon('dev') ?></span>
                    <div>
                        <h2 class="bo-cfg-section__title">Outil de développement</h2>
                        <p class="bo-cfg-section__lead">Visible seulement en mode debug (APP_DEBUG). À ne pas utiliser en production.</p>
                    </div>
                </header>
                <p class="bo-cfg-dev__text">
                    Met à jour les comptes liés aux <strong>candidatures acceptées</strong> :
                    statut <strong>membre actif</strong>, e-mail considéré comme vérifié,
                    rôle <strong>Période d’essai</strong> (ou Visiteur, sinon Opérateur),
                    et affectation comme <strong>Recrue</strong> sur la <strong>première unité</strong> de l’organigramme, s’il en existe une.
                </p>
                <form method="post" action="<?= $h(url('back-office/configuration/debug-recruit-sync')) ?>" class="bo-cfg-dev__form"
                      data-confirm="Mettre à jour tous les comptes des candidatures acceptées ? Cette action modifie des rôles et des affectations.">
                    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                    <button type="submit" class="ath-btn bo-cfg-btn-danger">Relancer la mise à jour des recrues</button>
                </form>
            </section>
            <?php endif; ?>

            <div class="bo-cfg-noresult" data-cfg-noresult hidden>
                <p class="bo-cfg-noresult__title">Aucun réglage ne correspond.</p>
                <p class="bo-cfg-noresult__text">Essayez un autre mot (par exemple « grade », « invitation », « vitrine ») ou <button type="button" class="bo-cfg-link" data-cfg-search-clear>effacez la recherche</button>.</p>
            </div>
        </div>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-configuration.js')) ?>" defer></script>
