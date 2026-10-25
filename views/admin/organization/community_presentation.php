<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $community */
$c = $community ?? [];
$badgeLabels = \App\Services\Community\TenantCommunityProfileService::badgeLabels();
$registryTagLabels = \App\Services\Community\TenantCommunityProfileService::registryTagLabels();
$selectedBadges = is_array($c['style_badges'] ?? null) ? $c['style_badges'] : [];
$selectedRegistryTags = is_array($c['registry_tags'] ?? null) ? $c['registry_tags'] : [];
$military = is_array($c['military_sections'] ?? null) ? $c['military_sections'] : [];
if ($military === []) {
    $military = [
        ['label' => 'PRIMO', 'title' => '', 'body' => ''],
        ['label' => 'SECUNDO', 'title' => '', 'body' => ''],
    ];
}
$registryCoverUrl = $registryCoverUrl ?? null;
$navOpsImageUrl = $navOpsImageUrl ?? null;
$navResImageUrl = $navResImageUrl ?? null;
$portalNav = is_array($c['portal_nav'] ?? null) ? $c['portal_nav'] : [];
$navAccents = \App\Services\Community\TenantCommunityProfileService::allowedNavAccents();
$navStyles = \App\Services\Community\TenantCommunityProfileService::allowedNavSubmenuStyles();
$navOps = is_array($portalNav['operations'] ?? null) ? $portalNav['operations'] : [];
$navRes = is_array($portalNav['resources'] ?? null) ? $portalNav['resources'] : [];
$navOpsAccentRaw = (string) ($navOps['accent'] ?? 'sky');
$navResAccentRaw = (string) ($navRes['accent'] ?? 'amber');
$navOpsAccent = in_array($navOpsAccentRaw, $navAccents, true) ? $navOpsAccentRaw : 'sky';
$navResAccent = in_array($navResAccentRaw, $navAccents, true) ? $navResAccentRaw : 'amber';
$navOpsStyle = in_array(($navOps['submenu_style'] ?? 'cards'), $navStyles, true) ? (string) ($navOps['submenu_style'] ?? 'cards') : 'cards';
$navResStyle = in_array(($navRes['submenu_style'] ?? 'minimal'), $navStyles, true) ? (string) ($navRes['submenu_style'] ?? 'minimal') : 'minimal';
$navOpsImageEnabled = !array_key_exists('image_enabled', $navOps) || !empty($navOps['image_enabled']);
$navResImageEnabled = !array_key_exists('image_enabled', $navRes) || !empty($navRes['image_enabled']);
$presentationMode = ($c['presentation_mode'] ?? 'simple') === 'military' ? 'military' : 'simple';
$registrationMode = \App\Services\Community\TenantCommunityProfileService::normalizeRegistrationMode($c['registration_mode'] ?? 'milsim');
$em = \App\Services\Community\EnlistmentMilsimPackService::forCommunity($c);
$emRoeLines = implode("\n", is_array($em['roe_items'] ?? null) ? $em['roe_items'] : []);
$emPreambleStatus = implode("\n", is_array($em['preamble_status_lines'] ?? null) ? $em['preamble_status_lines'] : []);
$emFieldsJson = json_encode($em['fields'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($emFieldsJson === false) {
    $emFieldsJson = '{}';
}
$cmdChain = is_array($c['public_command_chain'] ?? null) ? $c['public_command_chain'] : [];
while (count($cmdChain) < 3) {
    $cmdChain[] = ['role_label' => '', 'display_name' => '', 'hint' => ''];
}
$pm = is_array($c['public_modules'] ?? null) ? $c['public_modules'] : [];
$sm = is_array($c['public_stats_manual'] ?? null) ? $c['public_stats_manual'] : [];
$publicLayoutSel = ($c['public_page_layout'] ?? 'legacy') === 'showcase' ? 'showcase' : 'legacy';
$publicAudienceSel = ($c['public_audience'] ?? 'unit') === 'platform' ? 'platform' : 'unit';
$regionBadgesLines = implode("\n", is_array($c['public_region_badges'] ?? null) ? $c['public_region_badges'] : []);
$specialtiesLines = implode("\n", is_array($c['public_specialties'] ?? null) ? $c['public_specialties'] : []);
$statsModeSel = ($c['public_stats_mode'] ?? 'manual') === 'computed' ? 'computed' : 'manual';
$prereqCatalog = \App\Services\Community\TenantCommunityProfileService::prerequisiteCatalog();
$prereqRows = [];
foreach (is_array($c['public_prerequisites'] ?? null) ? $c['public_prerequisites'] : [] as $pr) {
    if (!is_array($pr)) {
        continue;
    }
    $pk = strtolower(trim((string) ($pr['key'] ?? '')));
    if ($pk !== '' && !isset($prereqCatalog[$pk])) {
        $pk = '';
    }
    $plabel = trim((string) ($pr['label'] ?? ''));
    if ($plabel === '' && $pk !== '') {
        $plabel = $prereqCatalog[$pk]['label'];
    }
    if ($plabel === '' && $pk === '') {
        continue;
    }
    $prereqRows[] = [
        'key' => $pk,
        'label' => $plabel,
        'status' => \App\Services\Community\TenantCommunityProfileService::normalizePrerequisiteStatus($pr['status'] ?? 'required'),
        'detail' => (string) ($pr['detail'] ?? ''),
    ];
}
if ($prereqRows === []) {
    $prereqRows[] = ['key' => '', 'label' => '', 'status' => 'required', 'detail' => ''];
}
$pitchRows = is_array($c['public_pitch'] ?? null) ? $c['public_pitch'] : [];
while (count($pitchRows) < 4) {
    $pitchRows[] = ['title' => '', 'body' => ''];
}
$stepRows = [];
foreach (is_array($c['public_process_steps'] ?? null) ? $c['public_process_steps'] : [] as $st) {
    if (!is_array($st)) {
        continue;
    }
    $stTitle = trim((string) ($st['title'] ?? ''));
    $stBody = trim((string) ($st['body'] ?? ''));
    if ($stTitle === '' && $stBody === '') {
        continue;
    }
    $stepRows[] = [
        'title' => $stTitle,
        'delay' => (string) ($st['delay'] ?? ''),
        'body' => $stBody,
        'highlight' => !empty($st['highlight']),
    ];
}
if ($stepRows === []) {
    $stepRows[] = ['title' => '', 'delay' => '', 'body' => '', 'highlight' => false];
}
$faqRows = is_array($c['public_faq'] ?? null) ? $c['public_faq'] : [];
while (count($faqRows) < 4) {
    $faqRows[] = ['q' => '', 'a' => ''];
}
$quoteRows = is_array($c['public_testimonials'] ?? null) ? $c['public_testimonials'] : [];
while (count($quoteRows) < 3) {
    $quoteRows[] = ['text' => '', 'name' => '', 'meta' => '', 'initials' => ''];
}
$videoChapterRows = is_array($c['public_video_chapters'] ?? null) ? $c['public_video_chapters'] : [];
while (count($videoChapterRows) < 4) {
    $videoChapterRows[] = ['time' => '', 'label' => ''];
}
$partnersLines = implode("\n", is_array($c['public_partners'] ?? null) ? $c['public_partners'] : []);
$hasMilitaryContent = false;
foreach ($military as $sec) {
    if (!is_array($sec)) {
        continue;
    }
    if (trim((string) ($sec['title'] ?? '')) !== '' || trim((string) ($sec['body'] ?? '')) !== '') {
        $hasMilitaryContent = true;
        break;
    }
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$cv = static fn (string $key): string => htmlspecialchars((string) ($c[$key] ?? ''), ENT_QUOTES, 'UTF-8');
$slugHint = trim((string) ($tenant['slug'] ?? ''));
$publicPageUrl = $slugHint !== '' ? url('c/' . rawurlencode($slugHint)) : '';
$settingsUrl = url('back-office/community');

/**
 * Interrupteur lisible. $withHidden = false pour les cases qui n’envoyaient
 * pas de valeur « 0 » (modules) : les données reçues restent identiques.
 */
$renderSwitch = static function (
    string $name,
    bool $checked,
    string $title,
    string $help,
    string $effectOn = '',
    string $effectOff = '',
    bool $withHidden = true,
    string $id = '',
) use ($h): void {
    ?>
    <label class="bcs-switch"<?= $id !== '' ? ' id="' . $h($id) . '"' : '' ?>>
        <?php if ($withHidden): ?><input type="hidden" name="<?= $h($name) ?>" value="0"><?php endif; ?>
        <input type="checkbox" class="bcs-switch__input" name="<?= $h($name) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
        <span class="bcs-switch__track" aria-hidden="true"></span>
        <span class="bcs-switch__copy">
            <span class="bcs-switch__title"><?= $h($title) ?></span>
            <?php if ($help !== ''): ?><span class="bcs-switch__help"><?= $h($help) ?></span><?php endif; ?>
            <?php if ($effectOn !== ''): ?><span class="bcs-switch__effect bcs-switch__effect--on"><strong>Activé :</strong> <span><?= $h($effectOn) ?></span></span><?php endif; ?>
            <?php if ($effectOff !== ''): ?><span class="bcs-switch__effect bcs-switch__effect--off"><strong>Désactivé :</strong> <span><?= $h($effectOff) ?></span></span><?php endif; ?>
        </span>
    </label>
    <?php
};

$renderWhere = static function (array $places) use ($h): void {
    ?>
    <p class="bcs-where">
        <span class="bcs-where__label">Où ça apparaît ?</span>
        <?php foreach ($places as $place): ?><span class="bcs-chip"><?= $h((string) $place) ?></span><?php endforeach; ?>
    </p>
    <?php
};

/** En-tête de carte : titre, explication, emplacement. */
$cardHead = static function (string $title, string $desc, array $where = []) use ($h, $renderWhere): void {
    ?>
    <div class="bcs-card__head">
        <div class="bcs-card__heading">
            <h3 class="bcs-card__title"><?= $h($title) ?></h3>
            <?php if ($desc !== ''): ?><p class="bcs-card__desc"><?= $desc ?></p><?php endif; ?>
            <?php if ($where !== []): ?><div class="bcs-card__where"><?php $renderWhere($where); ?></div><?php endif; ?>
        </div>
    </div>
    <?php
};

/* ---------- Complétion du profil public : priorités et liens ---------- */
$hasSocial = trim((string) ($c['public_social_website'] ?? '')) !== ''
    || trim((string) ($c['public_social_youtube'] ?? '')) !== ''
    || trim((string) ($c['public_social_teamspeak'] ?? '')) !== '';
$profileItems = [
    ['label' => 'Bio courte du bandeau', 'where' => 'Page publique', 'done' => trim((string) ($c['public_hero_subtitle'] ?? '')) !== '' || trim((string) ($c['simple_body'] ?? '')) !== '', 'href' => '#f-hero-subtitle'],
    ['label' => 'Texte « Qui sommes-nous ? »', 'where' => 'Page publique', 'done' => trim((string) ($c['public_about_body'] ?? '')) !== '' || trim((string) ($c['simple_body'] ?? '')) !== '' || $hasMilitaryContent, 'href' => '#f-about-body'],
    ['label' => 'Image d’en-tête du registre', 'where' => 'Registre des unités', 'done' => $registryCoverUrl !== null && trim((string) $registryCoverUrl) !== '', 'href' => '#f-registry-cover'],
    ['label' => 'Lien Discord', 'where' => 'Contact', 'done' => trim((string) ($c['contact_discord_url'] ?? '')) !== '', 'href' => '#f-contact-discord'],
    ['label' => 'Étapes de recrutement', 'where' => 'Page publique', 'done' => $stepRows !== [] && trim((string) ($stepRows[0]['title'] ?? '')) !== '', 'href' => '#cp-steps'],
    ['label' => 'Prérequis pour candidater', 'where' => 'Page publique', 'done' => $prereqRows !== [] && trim((string) ($prereqRows[0]['label'] ?? '')) !== '', 'href' => '#cp-prereq'],
    ['label' => 'Accroche du registre', 'where' => 'Registre des unités', 'done' => trim((string) ($c['registry_tagline'] ?? '')) !== '' || trim((string) ($c['public_hero_subtitle'] ?? '')) !== '', 'href' => '#f-registry-tagline'],
    ['label' => 'E-mail de contact', 'where' => 'Contact', 'done' => trim((string) ($c['contact_email'] ?? '')) !== '', 'href' => '#f-contact-email'],
    ['label' => 'Code pour rejoindre', 'where' => 'Paramètres · Identité', 'done' => trim((string) ($tenant['community_code'] ?? '')) !== '', 'href' => $settingsUrl . '?onglet=identite#community_code'],
    ['label' => 'Description pour Google et les partages', 'where' => 'Référencement', 'done' => trim((string) ($c['public_meta_description'] ?? '')) !== '', 'href' => '#f-meta-description'],
    ['label' => 'Style de jeu ou pastilles', 'where' => 'Registre des unités', 'done' => $selectedBadges !== [] || $selectedRegistryTags !== [], 'href' => '#f-registry-tags'],
    ['label' => 'Mission publique', 'where' => 'Page publique', 'done' => trim((string) ($c['public_mission'] ?? '')) !== '', 'href' => '#f-mission'],
    ['label' => 'Attentes envers les joueurs', 'where' => 'Page publique', 'done' => trim((string) ($c['expectations'] ?? '')) !== '', 'href' => '#f-expectations'],
    ['label' => 'Doctrine ou type d’accès', 'where' => 'Page publique', 'done' => trim((string) ($c['public_doctrine'] ?? '')) !== '' || trim((string) ($c['public_access_label'] ?? '')) !== '', 'href' => '#f-doctrine'],
    ['label' => 'Alerte ou créneaux de jeu', 'where' => 'Page publique', 'done' => trim((string) ($c['public_highlight_body'] ?? '')) !== '' || trim((string) ($c['public_schedule_body'] ?? '')) !== '', 'href' => '#cp-alert'],
    ['label' => 'Site, YouTube ou TeamSpeak', 'where' => 'Page publique', 'done' => $hasSocial, 'href' => '#cp-social'],
    ['label' => 'Formulaire « Nous écrire »', 'where' => 'Contact', 'done' => !empty($c['contact_form_enabled']), 'href' => '#f-contact-form'],
    ['label' => 'Bloc Événements affiché', 'where' => 'Page publique', 'done' => !empty($pm['events']), 'href' => '#cp-modules'],
    ['label' => 'Nom de la communauté', 'where' => 'Paramètres · Identité', 'done' => trim((string) ($tenant['name'] ?? '')) !== '', 'href' => $settingsUrl . '?onglet=identite#tenant_name'],
];
$profileChecklistTotal = count($profileItems);
$profileChecklistDone = count(array_filter($profileItems, static fn (array $it): bool => (bool) $it['done']));
$profileChecklistPercent = $profileChecklistTotal > 0 ? (int) round(($profileChecklistDone / $profileChecklistTotal) * 100) : 0;
$profileMissing = array_values(array_filter($profileItems, static fn (array $it): bool => !$it['done']));
$profileTop = array_slice($profileMissing, 0, 4);
$profileRestMissing = array_slice($profileMissing, 4);
$profileDoneItems = array_values(array_filter($profileItems, static fn (array $it): bool => (bool) $it['done']));

$renderTodo = static function (array $it) use ($h): void {
    $external = !str_starts_with((string) $it['href'], '#');
    ?>
    <li class="bcs-todo__item<?= $it['done'] ? ' is-done' : '' ?>">
        <a class="bcs-todo__link" href="<?= $h((string) $it['href']) ?>"<?= $external ? '' : ' data-cp-goto' ?>>
            <span><span class="bcs-todo__label"><?= $h((string) $it['label']) ?></span><span class="bcs-todo__where"><?= $h((string) $it['where']) ?></span></span>
        </a>
    </li>
    <?php
};

$cpPanels = [
    'visibilite' => ['Visibilité & registre', 'Annuaire, image, forum'],
    'vitrine' => ['Page publique', 'Bandeau, sections, recrutement'],
    'textes' => ['Présentation & contact', 'Texte libre, attentes, contact'],
    'profil' => ['Jeu & inscription', 'Type de candidature, mods, style'],
    'candidature' => ['Formulaire de candidature', 'Questions et textes du dossier'],
    'portail' => ['Menus du portail', 'Opérations et Ressources'],
];
$cpNum = 0;
?>
<div class="bcs cp-presentation" data-cp-root>
<div class="bcs-layout">

    <aside class="bcs-aside" aria-label="Navigation de la page">
        <section class="bcs-box bcs-progress" aria-labelledby="cp-progress-title">
            <p class="bcs-box__title" id="cp-progress-title">Profil public complété</p>
            <div class="bcs-progress__head">
                <span class="bcs-progress__value"><?= $profileChecklistPercent ?>&nbsp;%</span>
                <span class="bcs-progress__count"><?= $profileChecklistDone ?> sur <?= $profileChecklistTotal ?> éléments</span>
            </div>
            <div class="bcs-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $profileChecklistPercent ?>" aria-label="Complétion du profil public">
                <div class="bcs-progress__fill" style="width: <?= max(2, $profileChecklistPercent) ?>%"></div>
            </div>
            <?php if ($profileTop !== []): ?>
                <p class="bcs-progress__lead">À compléter en priorité</p>
                <ul class="bcs-todo">
                    <?php foreach ($profileTop as $it) { $renderTodo($it); } ?>
                </ul>
                <?php if ($profileRestMissing !== [] || $profileDoneItems !== []): ?>
                <details class="bcs-more">
                    <summary>Voir tout (<?= $profileChecklistTotal ?>)</summary>
                    <ul class="bcs-todo">
                        <?php foreach ($profileRestMissing as $it) { $renderTodo($it); } ?>
                        <?php foreach ($profileDoneItems as $it) { $renderTodo($it); } ?>
                    </ul>
                </details>
                <?php endif; ?>
            <?php else: ?>
                <p class="bcs-progress__done">Tout est renseigné : votre page publique est complète.</p>
            <?php endif; ?>
        </section>

        <nav class="bcs-box bcs-nav" aria-label="Parties de la fiche">
            <p class="bcs-box__title">Parties de la page</p>
            <div class="bcs-nav__list cp-presentation-tabs" role="tablist" aria-orientation="vertical">
                <?php foreach ($cpPanels as $pKey => [$pLabel, $pDesc]): $cpNum++; ?>
                <div class="bcs-nav__group">
                    <button type="button" role="tab" class="bcs-nav__item<?= $pKey === 'visibilite' ? ' is-active' : '' ?>" data-cp-tab="<?= $h($pKey) ?>" aria-selected="<?= $pKey === 'visibilite' ? 'true' : 'false' ?>" aria-controls="cp-panel-<?= $h($pKey) ?>">
                        <span class="bcs-nav__num" aria-hidden="true"><?= $cpNum ?></span>
                        <span class="bcs-nav__label"><?= $h($pLabel) ?></span>
                        <span class="bcs-nav__desc"><?= $h($pDesc) ?></span>
                    </button>
                    <ul class="bcs-nav__sub" data-cp-sub="<?= $h($pKey) ?>"<?= $pKey === 'visibilite' ? '' : ' hidden' ?>></ul>
                </div>
                <?php endforeach; ?>
            </div>
        </nav>

        <div class="bcs-box bcs-aside__extra">
            <div class="bcs-box__actions" style="margin-top:0;">
                <?php if ($publicPageUrl !== ''): ?>
                    <a class="bcs-btn bcs-btn--block bcs-btn--primary" href="<?= $h($publicPageUrl . '?edition=1') ?>" target="_blank" rel="noopener">Modifier directement sur la page ↗</a>
                    <a class="bcs-btn bcs-btn--block" href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir la page publique ↗</a>
                <?php endif; ?>
                <a class="bcs-btn bcs-btn--block" href="<?= $h(url('communities')) ?>" target="_blank" rel="noopener">Voir le registre des unités ↗</a>
                <a class="bcs-btn bcs-btn--block" href="<?= $h($settingsUrl) ?>">Paramètres de la communauté</a>
            </div>
        </div>
    </aside>

    <div class="bcs-main">
    <form id="community-presentation-form" method="post" enctype="multipart/form-data" action="<?= $h(url('back-office/community/presentation')) ?>" class="bcs-panel">
        <?= \App\Core\Csrf::field() ?>

        <!-- 1. Visibilité & registre -->
        <div class="bcs-panel" data-cp-panel="visibilite" id="cp-panel-visibilite" role="tabpanel">
            <header class="bcs-intro">
                <p class="bcs-intro__kicker">Partie 1 · Visibilité &amp; registre</p>
                <h2 class="bcs-intro__title">Qui peut vous trouver ?</h2>
                <p class="bcs-intro__text">Le <strong>registre des unités</strong> est l’annuaire public d’Athena : chaque communauté listée y a une carte (image, accroche, pastilles) qui mène à sa <strong>page publique</strong>. Choisissez ici si vous y figurez et ce que voit un visiteur non connecté.</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Registre des unités', 'Page publique']); ?>
                    <a class="bcs-link" href="<?= $h(url('communities')) ?>" target="_blank" rel="noopener">Voir le registre ↗</a>
                </div>
            </header>

            <section class="bcs-card" id="visibilite">
                <?php $cardHead('Visibilité', 'Trois réglages, avec leur effet concret.', ['Registre des unités', 'Page publique']); ?>
                <div class="bcs-card__body">
                    <?php $renderSwitch(
                        'registry_listed',
                        !array_key_exists('registry_listed', $c) || !empty($c['registry_listed']),
                        'Apparaître dans le registre public des unités',
                        'Le meilleur moyen d’être découvert par des joueurs qui cherchent une unité.',
                        'votre carte est visible dans l’annuaire et mène à votre page publique.',
                        'vous n’êtes pas dans l’annuaire. Votre page publique reste accessible à qui possède le lien.'
                    ); ?>
                    <?php $renderSwitch(
                        'registry_featured',
                        !empty($c['registry_featured']),
                        'Mettre en avant dans le registre',
                        'Ajoute un badge sur votre carte et la place parmi les premières.',
                        'votre carte porte un badge « mise en avant » et remonte dans la liste.',
                        'votre carte est classée normalement.'
                    ); ?>
                    <?php $renderSwitch(
                        'forum_members_only',
                        !empty($c['forum_members_only']),
                        'Réserver le forum aux membres',
                        'Concerne le bouton « Accéder au forum » de votre page publique.',
                        'les visiteurs non membres ne voient pas le bouton du forum.',
                        'tout visiteur peut ouvrir et lire le forum depuis votre page publique.'
                    ); ?>
                    <p class="bcs-notice bcs-notice--info">Faites aussi connaître votre communauté sur le Discord Athena : <a class="bcs-link" href="<?= $h(url('back-office/community/discord')) ?>">Annoncer sur Discord</a>.</p>
                </div>
            </section>

            <section class="bcs-card" id="carte-registre">
                <?php $cardHead('Votre carte dans le registre', 'Ce qu’un joueur voit en parcourant l’annuaire, avant même de cliquer.', ['Registre des unités']); ?>
                <div class="bcs-card__body">
                    <div class="bcs-media" id="f-registry-cover">
                        <span class="bcs-media__thumb">
                            <?php if ($registryCoverUrl): ?>
                                <img src="<?= $h((string) $registryCoverUrl) ?>" alt="">
                            <?php else: ?>
                                Fond coloré automatique
                            <?php endif; ?>
                        </span>
                        <div class="bcs-media__copy">
                            <label class="bcs-label" for="registry_cover">Image d’en-tête <span class="bcs-opt">(facultatif)</span></label>
                            <p class="bcs-help">Une photo paysage type bandeau (environ 1600 × 600) donne le meilleur rendu. JPG, PNG ou WebP, 3&nbsp;Mo maximum. Sans image, la carte affiche un fond coloré.</p>
                            <?php if ($slugHint === ''): ?>
                                <p class="bcs-notice bcs-notice--info">Définissez d’abord l’adresse publique de votre communauté dans <a class="bcs-link" href="<?= $h($settingsUrl . '?onglet=identite') ?>">Paramètres · Identité</a> pour activer l’envoi.</p>
                            <?php endif; ?>
                            <input type="file" name="registry_cover" id="registry_cover" class="bcs-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"<?= $slugHint === '' ? ' disabled aria-disabled="true" title="Adresse publique requise"' : '' ?>>
                            <?php if ($registryCoverUrl): ?>
                            <label class="bcs-check">
                                <input type="hidden" name="remove_registry_cover" value="0">
                                <input type="checkbox" name="remove_registry_cover" value="1">
                                <span>Retirer l’image et revenir au fond coloré automatique</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-registry-tagline">Accroche de la carte</label>
                        <p class="bcs-help">Une seule phrase sous votre nom dans l’annuaire. Si elle est vide, le début de la bio du bandeau est utilisé.</p>
                        <input type="text" id="f-registry-tagline" name="registry_tagline" value="<?= $cv('registry_tagline') ?>" maxlength="220" placeholder="Ex. Unité MILSIM francophone, opérations coopératives le samedi soir">
                    </div>
                    <div class="bcs-field" id="f-registry-tags">
                        <span class="bcs-label">Pastilles affichées sur la carte</span>
                        <p class="bcs-help">Cochez ce qui décrit le mieux votre univers : les joueurs filtrent l’annuaire avec ces pastilles.</p>
                        <div class="bcs-checks">
                            <?php foreach ($registryTagLabels as $slug => $label): ?>
                                <label class="bcs-check">
                                    <input type="checkbox" name="registry_tags[]" value="<?= $h((string) $slug) ?>" <?= in_array($slug, $selectedRegistryTags, true) ? 'checked' : '' ?>>
                                    <?= $h((string) $label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- 2. Page publique -->
        <div class="bcs-panel" data-cp-panel="vitrine" id="cp-panel-vitrine" role="tabpanel" hidden>
            <header class="bcs-intro">
                <p class="bcs-intro__kicker">Partie 2 · Page publique</p>
                <h2 class="bcs-intro__title">Votre vitrine, de haut en bas</h2>
                <p class="bcs-intro__text">Les blocs ci-dessous suivent l’ordre de la page publique : bandeau d’accueil, présentation, recrutement, puis bas de page. <strong>Un bloc laissé vide n’est simplement pas affiché</strong> : remplissez d’abord l’essentiel (bandeau, présentation, étapes).</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Page publique']); ?>
                    <?php if ($publicPageUrl !== ''): ?><a class="bcs-link" href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir le résultat ↗</a><?php endif; ?>
                </div>
            </header>

            <section class="bcs-card" id="cp-modele">
                <?php $cardHead('Modèle de page', 'La mise en page générale et les éléments flottants.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-layout">Modèle</label>
                            <p class="bcs-help"><em>Vitrine</em> : pleine page, grandes images, idéal pour recruter. <em>Classique</em> : carte compacte.</p>
                            <select id="f-layout" name="public_page_layout">
                                <option value="legacy" <?= $publicLayoutSel === 'legacy' ? 'selected' : '' ?>>Classique (carte compacte)</option>
                                <option value="showcase" <?= $publicLayoutSel === 'showcase' ? 'selected' : '' ?>>Vitrine (pleine page)</option>
                            </select>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-audience">Type de fiche</label>
                            <p class="bcs-help">Choisissez « Plateforme » uniquement pour un portail ou un outil : le recrutement y est moins mis en avant.</p>
                            <select id="f-audience" name="public_audience">
                                <option value="unit" <?= $publicAudienceSel === 'unit' ? 'selected' : '' ?>>Unité / communauté de jeu</option>
                                <option value="platform" <?= $publicAudienceSel === 'platform' ? 'selected' : '' ?>>Plateforme / outil</option>
                            </select>
                        </div>
                    </div>
                    <?php $renderSwitch(
                        'public_recruitment_badge_open',
                        !empty($c['public_recruitment_badge_open']),
                        'Badge « Recrutement ouvert »',
                        'Un badge bien visible dans le bandeau d’accueil.',
                        'le badge s’affiche en haut de la page.',
                        'pas de badge.'
                    ); ?>
                    <?php $renderSwitch(
                        'public_sticky_cta_enabled',
                        !array_key_exists('public_sticky_cta_enabled', $c) || !empty($c['public_sticky_cta_enabled']),
                        'Bouton « Candidater » toujours visible',
                        'Une barre fixée en bas de l’écran pendant que le visiteur fait défiler la page.',
                        'la barre suit le visiteur : le bouton Candidater est toujours à portée.',
                        'le bouton n’apparaît qu’aux endroits prévus de la page.'
                    ); ?>
                    <?php $renderSwitch(
                        'public_roster_enabled',
                        !empty($c['public_roster_enabled']),
                        'Afficher la liste publique des membres',
                        'Seuls les membres ayant accepté d’y figurer apparaissent.',
                        'un tableau des membres volontaires est affiché.',
                        'aucune liste de membres sur la page publique.'
                    ); ?>
                </div>
            </section>

            <section class="bcs-card" id="cp-bandeau">
                <?php $cardHead('Bandeau d’accueil', 'Le haut de page : c’est souvent la seule partie lue. Soyez court et concret.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-hero-headline">Titre d’accroche</label>
                        <p class="bcs-help">Une phrase forte qui résume votre identité.</p>
                        <textarea id="f-hero-headline" name="public_hero_headline" rows="2" maxlength="220" placeholder="Ex. Une unité qui joue sérieusement, sans se prendre au sérieux."><?= $cv('public_hero_headline') ?></textarea>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-hero-subtitle">Bio courte</label>
                        <p class="bcs-help">2 ou 3 lignes sous le titre : qui vous êtes, à quoi vous jouez, quand. Vide, une bio existante peut être reprise automatiquement.</p>
                        <textarea id="f-hero-subtitle" name="public_hero_subtitle" rows="3" maxlength="600" placeholder="Ex. Unité MILSIM francophone sur Arma 3. Opérations coopératives le samedi soir, ambiance sérieuse mais détendue."><?= $cv('public_hero_subtitle') ?></textarea>
                    </div>
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-session">Session de recrutement <span class="bcs-opt">(badge)</span></label>
                            <input type="text" id="f-session" name="public_recruitment_session_label" value="<?= $cv('public_recruitment_session_label') ?>" maxlength="80" placeholder="Ex. Session septembre 2026">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-founded">Année de fondation</label>
                            <input type="text" id="f-founded" name="public_founded_year" value="<?= $cv('public_founded_year') ?>" maxlength="12" class="bcs-input--tiny" placeholder="2023">
                        </div>
                        <div class="bcs-field" id="f-doctrine-wrap">
                            <label class="bcs-label" for="f-doctrine">Doctrine <span class="bcs-opt">(court)</span></label>
                            <input type="text" id="f-doctrine" name="public_doctrine" value="<?= $cv('public_doctrine') ?>" maxlength="200" placeholder="Ex. Doctrine US Army, procédures OTAN">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-access">Type d’accès</label>
                            <input type="text" id="f-access" name="public_access_label" value="<?= $cv('public_access_label') ?>" maxlength="120" placeholder="Ex. Public + validation">
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-region-badges">Badges libres</label>
                        <p class="bcs-help">Un badge par ligne, affichés sous le titre. Exemple : FR, OTAN, 18+.</p>
                        <textarea id="f-region-badges" name="public_region_badges" rows="3" class="bcs-mono" placeholder="FR&#10;OTAN"><?= $h($regionBadgesLines) ?></textarea>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-alert">
                <?php $cardHead('Alerte et créneaux de jeu', 'Un bandeau d’information sous le haut de page (session en cours, pause…) et une ligne rappelant vos horaires.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-hl-style">Ton de l’alerte</label>
                            <?php
                            // Même garde que TenantCommunityProfileService::publicShowcasePayload :
                            // ne pas relire $c['public_highlight_style'] après ?? (clé absente → 500).
                            $hlStyleSel = in_array(
                                ($hlStyleRaw = strtolower(trim((string) ($c['public_highlight_style'] ?? 'info')))),
                                ['info', 'warning', 'success'],
                                true
                            ) ? $hlStyleRaw : 'info';
                            ?>
                            <select id="f-hl-style" name="public_highlight_style">
                                <option value="info" <?= $hlStyleSel === 'info' ? 'selected' : '' ?>>Information (bleu)</option>
                                <option value="warning" <?= $hlStyleSel === 'warning' ? 'selected' : '' ?>>Attention (orange)</option>
                                <option value="success" <?= $hlStyleSel === 'success' ? 'selected' : '' ?>>Ouverture / bonne nouvelle (vert)</option>
                            </select>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-hl-title">Titre de l’alerte</label>
                            <input type="text" id="f-hl-title" name="public_highlight_title" value="<?= $cv('public_highlight_title') ?>" maxlength="120" placeholder="Ex. Session de recrutement en cours">
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-hl-body">Texte de l’alerte</label>
                        <p class="bcs-help">Laissez vide pour ne pas afficher d’alerte.</p>
                        <textarea id="f-hl-body" name="public_highlight_body" rows="2" maxlength="400" placeholder="Ex. Inscriptions ouvertes jusqu’au 30 septembre, places limitées."><?= $cv('public_highlight_body') ?></textarea>
                    </div>
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-sched-label">Libellé des créneaux</label>
                            <input type="text" id="f-sched-label" name="public_schedule_label" value="<?= $cv('public_schedule_label') ?>" maxlength="80" placeholder="Créneaux hebdo">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-sched-body">Horaires</label>
                            <input type="text" id="f-sched-body" name="public_schedule_body" value="<?= $cv('public_schedule_body') ?>" maxlength="240" placeholder="Ven. 20h–23h · Dim. opérations">
                        </div>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-presentation">
                <?php $cardHead('Présentation « Qui sommes-nous ? »', 'La section qui raconte votre histoire et votre façon de jouer.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-about-title">Titre de la section</label>
                        <input type="text" id="f-about-title" name="public_about_title" value="<?= $cv('public_about_title') ?>" maxlength="160" placeholder="Ex. Du milsim structuré, pas militarisé">
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-about-body">Texte principal</label>
                        <p class="bcs-help">Histoire, cadre de jeu, ambiance, accueil des nouveaux. Vide, la présentation existante (partie 3) est reprise.</p>
                        <textarea id="f-about-body" name="public_about_body" rows="5" maxlength="8000" placeholder="Fondée en 2021, notre unité…"><?= $cv('public_about_body') ?></textarea>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-about-2">Second paragraphe <span class="bcs-opt">(facultatif)</span></label>
                        <textarea id="f-about-2" name="public_about_body_secondary" rows="3" maxlength="4000"><?= $cv('public_about_body_secondary') ?></textarea>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-mission">Mission publique</label>
                        <p class="bcs-help">Ce que vous cherchez à accomplir ensemble, en quelques lignes.</p>
                        <textarea id="f-mission" name="public_mission" rows="3" maxlength="4000" placeholder="Ex. Offrir des opérations réalistes et bien préparées à des joueurs adultes et disponibles."><?= $cv('public_mission') ?></textarea>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-specialties">Spécialités</label>
                        <p class="bcs-help">Une par ligne. Exemple : Infanterie légère, Reconnaissance, Appui aérien.</p>
                        <textarea id="f-specialties" name="public_specialties" rows="3"><?= $h($specialtiesLines) ?></textarea>
                    </div>
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-sections-title">Titre du bloc « Sections »</label>
                            <input type="text" id="f-sections-title" name="public_sections_title" value="<?= $cv('public_sections_title') ?>" maxlength="160" placeholder="Nos sections">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-sections-lead">Sous-texte</label>
                            <input type="text" id="f-sections-lead" name="public_sections_lead" value="<?= $cv('public_sections_lead') ?>" maxlength="240" placeholder="Les places ouvertes sont indiquées par section.">
                        </div>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-pitch">
                <?php $cardHead('Points forts', 'Jusqu’à quatre arguments courts (titre + une phrase). Exemple : « Encadrement » — « Un instructeur suit chaque recrue pendant un mois ».'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-pairs">
                        <?php foreach ($pitchRows as $i => $pr): ?>
                        <div class="bcs-pair">
                            <input type="text" name="public_pitch_title[]" value="<?= $h((string) ($pr['title'] ?? '')) ?>" maxlength="120" placeholder="Titre" aria-label="Point fort <?= (int) $i + 1 ?> : titre">
                            <input type="text" name="public_pitch_body[]" value="<?= $h((string) ($pr['body'] ?? '')) ?>" maxlength="400" placeholder="Description courte" aria-label="Point fort <?= (int) $i + 1 ?> : description">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-prereq">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Prérequis pour candidater</h3>
                        <p class="bcs-card__desc">Ce qu’un candidat doit avoir avant de postuler (âge, micro, jeu…). Une liste claire évite les candidatures inadaptées.</p>
                    </div>
                    <button type="button" id="add-prereq-row" class="bcs-btn bcs-btn--small">+ Ajouter un prérequis</button>
                </div>
                <div id="prereq-rows" class="bcs-rows" data-prereq-max="16">
                    <?php foreach ($prereqRows as $i => $prRow): ?>
                        <?php
                        $st = (string) ($prRow['status'] ?? 'required');
                        $pkeySel = (string) ($prRow['key'] ?? '');
                        ?>
                    <div class="bcs-row cp-dyn-row" data-prereq-row>
                        <div class="bcs-row__head">
                            <span class="bcs-row__index">Prérequis <span data-row-index><?= (int) $i + 1 ?></span></span>
                            <div class="bcs-row__tools">
                                <button type="button" class="cp-move-up bcs-btn bcs-btn--small" title="Monter" aria-label="Monter">↑</button>
                                <button type="button" class="cp-move-down bcs-btn bcs-btn--small" title="Descendre" aria-label="Descendre">↓</button>
                                <button type="button" class="cp-remove-row bcs-btn bcs-btn--small bcs-btn--danger">Retirer</button>
                            </div>
                        </div>
                        <div class="bcs-grid">
                            <div class="bcs-field">
                                <label class="bcs-mini-label">Libellé affiché</label>
                                <input type="text" name="public_prereq_label[]" value="<?= $h((string) ($prRow['label'] ?? '')) ?>" maxlength="160" placeholder="Ex. Micro correct et Discord">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-mini-label">Catégorie <span class="bcs-opt">(remplit le libellé)</span></label>
                                <select name="public_prereq_key[]" class="cp-prereq-cat">
                                    <option value="">Libre (texte seul)</option>
                                    <?php foreach ($prereqCatalog as $ck => $cinfo): ?>
                                    <option value="<?= $h((string) $ck) ?>" <?= $pkeySel === $ck ? 'selected' : '' ?> data-hint="<?= $h((string) $cinfo['hint']) ?>" data-label="<?= $h((string) $cinfo['label']) ?>"><?= $h((string) $cinfo['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-mini-label">Niveau d’exigence</label>
                                <select name="public_prereq_status[]">
                                    <option value="required" <?= $st === 'required' ? 'selected' : '' ?>>Exigé</option>
                                    <option value="optional" <?= $st === 'optional' ? 'selected' : '' ?>>Souhaité</option>
                                    <option value="not_required" <?= $st === 'not_required' ? 'selected' : '' ?>>Non exigé</option>
                                </select>
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-mini-label">Précision <span class="bcs-opt">(facultatif)</span></label>
                                <input type="text" name="public_prereq_detail[]" value="<?= $h((string) ($prRow['detail'] ?? '')) ?>" maxlength="240" placeholder="Court complément affiché sous le libellé">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bcs-card" id="cp-steps">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Étapes de recrutement</h3>
                        <p class="bcs-card__desc">Le parcours d’un candidat, de la candidature au premier terrain. Exemple : Candidature → Entretien vocal → Formation initiale → Première opération.</p>
                    </div>
                    <button type="button" id="add-step-row" class="bcs-btn bcs-btn--small">+ Ajouter une étape</button>
                </div>
                <div id="step-rows" class="bcs-rows" data-step-max="12">
                    <?php foreach ($stepRows as $i => $stRow): ?>
                    <div class="bcs-row cp-dyn-row" data-step-row>
                        <div class="bcs-row__head">
                            <span class="bcs-row__index">Étape <span data-row-index><?= (int) $i + 1 ?></span></span>
                            <div class="bcs-row__tools">
                                <button type="button" class="cp-move-up bcs-btn bcs-btn--small" title="Monter" aria-label="Monter">↑</button>
                                <button type="button" class="cp-move-down bcs-btn bcs-btn--small" title="Descendre" aria-label="Descendre">↓</button>
                                <button type="button" class="cp-remove-row bcs-btn bcs-btn--small bcs-btn--danger">Retirer</button>
                            </div>
                        </div>
                        <div class="bcs-grid bcs-grid--3">
                            <div class="bcs-field">
                                <label class="bcs-mini-label">Titre</label>
                                <input type="text" name="public_step_title[]" value="<?= $h((string) ($stRow['title'] ?? '')) ?>" maxlength="120" placeholder="Ex. Candidature en ligne">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-mini-label">Délai</label>
                                <input type="text" name="public_step_delay[]" value="<?= $h((string) ($stRow['delay'] ?? '')) ?>" maxlength="40" placeholder="sous 5 jours">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-mini-label">Affichage</label>
                                <select name="public_step_highlight[]">
                                    <option value="0" <?= empty($stRow['highlight']) ? 'selected' : '' ?>>Normal</option>
                                    <option value="1" <?= !empty($stRow['highlight']) ? 'selected' : '' ?>>Mis en avant</option>
                                </select>
                            </div>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-mini-label">Description courte</label>
                            <textarea name="public_step_body[]" rows="2" maxlength="500" placeholder="Ce que le candidat doit savoir à cette étape"><?= $h((string) ($stRow['body'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bcs-card" id="cp-modules">
                <?php $cardHead('Blocs automatiques', 'Des blocs remplis automatiquement à partir du portail. Activez ceux qui donnent envie de vous rejoindre.'); ?>
                <div class="bcs-card__body">
                    <?php foreach ([
                        'events' => ['Événements', 'Vos prochaines opérations et entraînements ouverts.'],
                        'forum' => ['Forum', 'Les derniers sujets publics du forum.'],
                        'documents' => ['Documents', 'Les documents marqués comme publics (règlement, doctrine…).'],
                        'roster' => ['Effectifs', 'La composition de l’unité.'],
                        'training' => ['Formations', 'Le catalogue des formations proposées.'],
                        'analytics' => ['Statistiques', 'Quelques chiffres d’activité.'],
                    ] as $mk => [$ml, $mh]): ?>
                        <?php $renderSwitch('public_mod_' . $mk, !empty($pm[$mk]), $ml, $mh, '', '', false); ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bcs-card" id="cp-stats">
                <?php $cardHead('Chiffres clés', 'Quatre chiffres en vue d’ensemble. En mode automatique, effectif et nombre d’unités sont calculés pour vous.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-stats-mode">Mode de calcul</label>
                        <select id="f-stats-mode" name="public_stats_mode" class="bcs-input--short">
                            <option value="manual" <?= $statsModeSel === 'manual' ? 'selected' : '' ?>>Saisie manuelle</option>
                            <option value="computed" <?= $statsModeSel === 'computed' ? 'selected' : '' ?>>Calcul automatique</option>
                        </select>
                    </div>
                    <div class="bcs-grid">
                        <div class="bcs-field"><label class="bcs-mini-label" for="f-st-eff">Effectif</label><input type="text" id="f-st-eff" name="public_stats_effectif" value="<?= $h((string) ($sm['effectif'] ?? '')) ?>" maxlength="12" placeholder="42"></div>
                        <div class="bcs-field"><label class="bcs-mini-label" for="f-st-un">Unités</label><input type="text" id="f-st-un" name="public_stats_unites" value="<?= $h((string) ($sm['unites'] ?? '')) ?>" maxlength="12" placeholder="4"></div>
                        <div class="bcs-field"><label class="bcs-mini-label" for="f-st-act">Activité en % <span class="bcs-opt">(manuel)</span></label><input type="text" id="f-st-act" name="public_stats_activite" value="<?= $h((string) ($sm['activite_percent'] ?? '')) ?>" maxlength="12" placeholder="92"></div>
                        <div class="bcs-field"><label class="bcs-mini-label" for="f-st-th">Théâtre <span class="bcs-opt">(manuel)</span></label><input type="text" id="f-st-th" name="public_stats_theatre" value="<?= $h((string) ($sm['theatre'] ?? '')) ?>" maxlength="120" placeholder="Europe"></div>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-encadrement">
                <?php $cardHead('Encadrement affiché', 'Les responsables que vous souhaitez présenter (texte libre, sans lien avec les grades du portail).'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-pairs">
                        <?php foreach ($cmdChain as $ci => $crow): ?>
                            <?php if (!is_array($crow)) { continue; } ?>
                        <div class="bcs-pair bcs-pair--3">
                            <input type="text" name="cmd_role_label[]" value="<?= $h((string) ($crow['role_label'] ?? '')) ?>" placeholder="Rôle (ex. Chef d’unité)" aria-label="Responsable <?= (int) $ci + 1 ?> : rôle">
                            <input type="text" name="cmd_display_name[]" value="<?= $h((string) ($crow['display_name'] ?? '')) ?>" placeholder="Nom affiché" aria-label="Responsable <?= (int) $ci + 1 ?> : nom">
                            <input type="text" name="cmd_hint[]" value="<?= $h((string) ($crow['hint'] ?? '')) ?>" placeholder="Sous-titre" aria-label="Responsable <?= (int) $ci + 1 ?> : sous-titre">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-video">
                <?php $cardHead('Vidéo de présentation', 'Une vidéo YouTube ou Vimeo intégrée à la page. Les chapitres permettent d’aller directement à un moment.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-video-url">Lien de la vidéo</label>
                        <input type="url" id="f-video-url" name="public_video_url" value="<?= $cv('public_video_url') ?>" maxlength="1024" placeholder="https://www.youtube.com/watch?v=…">
                    </div>
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-video-title">Titre</label>
                            <input type="text" id="f-video-title" name="public_video_title" value="<?= $cv('public_video_title') ?>" maxlength="160">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-video-body">Texte d’accompagnement</label>
                            <textarea id="f-video-body" name="public_video_body" rows="2" maxlength="800"><?= $cv('public_video_body') ?></textarea>
                        </div>
                    </div>
                    <div class="bcs-field">
                        <span class="bcs-label">Chapitres <span class="bcs-opt">(facultatif)</span></span>
                        <div class="bcs-pairs">
                            <?php foreach ($videoChapterRows as $i => $ch): ?>
                            <div class="bcs-pair bcs-pair--time">
                                <input type="text" name="public_video_chapter_time[]" value="<?= $h((string) ($ch['time'] ?? '')) ?>" maxlength="16" class="bcs-mono" placeholder="00:34" aria-label="Chapitre <?= (int) $i + 1 ?> : minutage">
                                <input type="text" name="public_video_chapter_label[]" value="<?= $h((string) ($ch['label'] ?? '')) ?>" maxlength="120" placeholder="Briefing et distribution des ordres" aria-label="Chapitre <?= (int) $i + 1 ?> : titre">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-faq">
                <?php $cardHead('Questions fréquentes', 'Répondez d’avance aux questions des candidats (âge minimum, fréquence de jeu, matériel…). Les paires vides sont ignorées.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-pairs">
                        <?php foreach ($faqRows as $i => $fq): ?>
                        <div class="bcs-stack bcs-row">
                            <input type="text" name="public_faq_q[]" value="<?= $h((string) ($fq['q'] ?? '')) ?>" maxlength="200" placeholder="Question (ex. Faut-il de l’expérience ?)" aria-label="Question <?= (int) $i + 1 ?>">
                            <textarea name="public_faq_a[]" rows="2" maxlength="800" placeholder="Réponse" aria-label="Réponse <?= (int) $i + 1 ?>"><?= $h((string) ($fq['a'] ?? '')) ?></textarea>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-temoignages">
                <?php $cardHead('Témoignages', 'Quelques mots de vos membres. Les initiales servent d’avatar.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-pairs">
                        <?php foreach ($quoteRows as $i => $qr): ?>
                        <div class="bcs-stack bcs-row">
                            <textarea name="public_quote_text[]" rows="2" maxlength="600" placeholder="Citation" aria-label="Témoignage <?= (int) $i + 1 ?>"><?= $h((string) ($qr['text'] ?? '')) ?></textarea>
                            <div class="bcs-pair bcs-pair--3">
                                <input type="text" name="public_quote_name[]" value="<?= $h((string) ($qr['name'] ?? '')) ?>" maxlength="80" placeholder="Indicatif" aria-label="Témoignage <?= (int) $i + 1 ?> : indicatif">
                                <input type="text" name="public_quote_meta[]" value="<?= $h((string) ($qr['meta'] ?? '')) ?>" maxlength="120" placeholder="Grade · section · ancienneté" aria-label="Témoignage <?= (int) $i + 1 ?> : détails">
                                <input type="text" name="public_quote_initials[]" value="<?= $h((string) ($qr['initials'] ?? '')) ?>" maxlength="4" class="bcs-mono" placeholder="BA" aria-label="Témoignage <?= (int) $i + 1 ?> : initiales">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-cta">
                <?php $cardHead('Partenaires et appel final', 'Le bas de page : unités alliées, puis un dernier appel à candidater.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-partners">Unités alliées ou partenaires</label>
                        <p class="bcs-help">Une par ligne.</p>
                        <textarea id="f-partners" name="public_partners" rows="3"><?= $h($partnersLines) ?></textarea>
                    </div>
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-cta-kicker">Sur-titre</label>
                            <input type="text" id="f-cta-kicker" name="public_cta_kicker" value="<?= $cv('public_cta_kicker') ?>" maxlength="80" placeholder="Prêt à nous rejoindre ?">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-cta-title">Titre</label>
                            <input type="text" id="f-cta-title" name="public_cta_title" value="<?= $cv('public_cta_title') ?>" maxlength="160" placeholder="Rejoignez la prochaine session">
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-cta-body">Texte</label>
                        <textarea id="f-cta-body" name="public_cta_body" rows="2" maxlength="500"><?= $cv('public_cta_body') ?></textarea>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-social">
                <?php $cardHead('Réseaux et présence en ligne', 'Liens affichés en bas de la page publique. Laissez vide ce que vous n’avez pas.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-web">Site web</label>
                            <input type="url" id="f-web" name="public_social_website" value="<?= $cv('public_social_website') ?>" maxlength="500" placeholder="https://">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-yt">Chaîne YouTube</label>
                            <input type="url" id="f-yt" name="public_social_youtube" value="<?= $cv('public_social_youtube') ?>" maxlength="500" placeholder="https://youtube.com/@…">
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-ts">TeamSpeak ou adresse vocale</label>
                        <input type="text" id="f-ts" name="public_social_teamspeak" value="<?= $cv('public_social_teamspeak') ?>" maxlength="160" placeholder="ts.exemple.net ou ts3server://…">
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-seo">
                <?php $cardHead('Référencement et partage', 'Le titre et le résumé affichés par Google et lors d’un partage de lien (Discord, réseaux sociaux).', ['Google', 'Aperçus de liens']); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-meta-title">Titre <span class="bcs-opt">(facultatif)</span></label>
                        <p class="bcs-help">Vide, le nom de la communauté est utilisé.</p>
                        <input type="text" id="f-meta-title" name="public_meta_title" value="<?= $cv('public_meta_title') ?>" maxlength="120" placeholder="Ex. 1er RPIMa — Unité MILSIM Arma 3">
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-meta-description">Description</label>
                        <p class="bcs-help">Entre 150 et 220 caractères : une phrase claire qui donne envie de cliquer.</p>
                        <textarea id="f-meta-description" name="public_meta_description" rows="2" maxlength="320" placeholder="Unité MILSIM francophone sur Arma 3 : opérations coopératives chaque samedi, formation des recrues, ambiance sérieuse et conviviale."><?= $cv('public_meta_description') ?></textarea>
                    </div>
                </div>
            </section>
        </div>

        <!-- 3. Présentation & contact -->
        <div class="bcs-panel" data-cp-panel="textes" id="cp-panel-textes" role="tabpanel" hidden>
            <header class="bcs-intro">
                <p class="bcs-intro__kicker">Partie 3 · Présentation &amp; contact</p>
                <h2 class="bcs-intro__title">Votre texte de présentation et vos coordonnées</h2>
                <p class="bcs-intro__text">Le texte de présentation de votre fiche (en texte libre ou en sections étiquetées façon ordre d’opération), ce que vous attendez des joueurs, et comment vous contacter.</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Page publique', 'Formulaire de contact']); ?>
                </div>
            </header>

            <section class="bcs-card" id="cp-texte">
                <?php $cardHead('Présentation', 'Choisissez la forme qui vous ressemble. Seul le mode choisi est affiché.'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <div class="bcs-choices">
                            <label class="bcs-choice">
                                <input type="radio" name="presentation_mode" value="simple" <?= $presentationMode === 'simple' ? 'checked' : '' ?>>
                                <span><span class="bcs-choice__title">Texte libre</span><span class="bcs-choice__help">Un seul bloc de texte, comme un article.</span></span>
                            </label>
                            <label class="bcs-choice">
                                <input type="radio" name="presentation_mode" value="military" <?= $presentationMode === 'military' ? 'checked' : '' ?>>
                                <span><span class="bcs-choice__title">Sections étiquetées</span><span class="bcs-choice__help">Plusieurs blocs titrés (PRIMO, SECUNDO…), style ordre militaire.</span></span>
                            </label>
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-simple-body">Texte libre</label>
                        <textarea id="f-simple-body" name="simple_body" rows="8"><?= $cv('simple_body') ?></textarea>
                    </div>
                    <div class="bcs-field" id="military-sections">
                        <div class="bcs-row__head" style="margin:0;">
                            <span class="bcs-label">Sections étiquetées</span>
                            <button type="button" id="add-military-section" class="bcs-btn bcs-btn--small">+ Ajouter une section</button>
                        </div>
                        <?php foreach ($military as $i => $sec): ?>
                            <?php if (!is_array($sec)) { continue; } ?>
                            <div class="bcs-row bcs-stack military-section-row">
                                <div class="bcs-pair">
                                    <input type="text" name="military_label[]" value="<?= $h((string) ($sec['label'] ?? '')) ?>" maxlength="32" class="bcs-mono" placeholder="Étiquette (ex. PRIMO)" aria-label="Section <?= (int) $i + 1 ?> : étiquette">
                                    <input type="text" name="military_title[]" value="<?= $h((string) ($sec['title'] ?? '')) ?>" maxlength="200" placeholder="Titre de section" aria-label="Section <?= (int) $i + 1 ?> : titre">
                                </div>
                                <textarea name="military_body[]" rows="4" placeholder="Contenu" aria-label="Section <?= (int) $i + 1 ?> : contenu"><?= $h((string) ($sec['body'] ?? '')) ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-expectations">Attentes envers les joueurs</label>
                        <p class="bcs-help">Présence, comportement, matériel, âge… Être clair ici fait gagner du temps à tout le monde.</p>
                        <textarea id="f-expectations" name="expectations" rows="4" placeholder="Ex. Présence à 2 opérations par mois, micro obligatoire, 18 ans minimum, respect de la chaîne de commandement en jeu."><?= $cv('expectations') ?></textarea>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-contact">
                <?php $cardHead('Contact public', 'Comment un visiteur peut vous joindre avant de candidater.', ['Page publique']); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-contact-intro">Phrase d’introduction <span class="bcs-opt">(facultatif)</span></label>
                        <input type="text" id="f-contact-intro" name="contact_intro" value="<?= $cv('contact_intro') ?>" maxlength="500" placeholder="Une question ? Écrivez-nous, on répond sous 48 h.">
                    </div>
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-contact-discord">Lien d’invitation Discord</label>
                            <p class="bcs-help">Un lien qui n’expire pas.</p>
                            <input type="url" id="f-contact-discord" name="contact_discord_url" value="<?= $cv('contact_discord_url') ?>" maxlength="500" placeholder="https://discord.gg/…">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-contact-email">E-mail de contact</label>
                            <p class="bcs-help">Affiché et destinataire du formulaire.</p>
                            <input type="email" id="f-contact-email" name="contact_email" value="<?= $cv('contact_email') ?>" maxlength="255" placeholder="recrutement@mon-unite.fr">
                        </div>
                    </div>
                    <?php $renderSwitch(
                        'contact_form_enabled',
                        !empty($c['contact_form_enabled']),
                        'Formulaire « Nous écrire »',
                        'Nécessite un e-mail de contact valide.',
                        'les visiteurs peuvent vous envoyer un message depuis la page publique.',
                        'seuls l’e-mail et le Discord sont affichés.',
                        true,
                        'f-contact-form'
                    ); ?>
                </div>
            </section>
        </div>

        <!-- 4. Jeu & inscription -->
        <div class="bcs-panel" data-cp-panel="profil" id="cp-panel-profil" role="tabpanel" hidden>
            <header class="bcs-intro">
                <p class="bcs-intro__kicker">Partie 4 · Jeu &amp; inscription</p>
                <h2 class="bcs-intro__title">À quoi vous jouez et comment on candidate</h2>
                <p class="bcs-intro__text">Ces informations aident un joueur à savoir en un coup d’œil si votre unité lui correspond (jeu, mods, style) et définissent le formulaire ouvert par le bouton « Candidater ».</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Page publique', 'Registre des unités', 'Formulaire de candidature']); ?>
                </div>
            </header>

            <section class="bcs-card" id="cp-inscription">
                <?php $cardHead('Formulaire de candidature', 'Le parcours proposé aux visiteurs qui cliquent sur « Candidater ».'); ?>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-registration-mode">Type de candidature</label>
                        <p class="bcs-help"><em>MilSim complet</em> : dossier détaillé (expérience, disponibilités, motivation), réglable en partie 5. <em>Simple</em> : quelques champs. <em>Discord</em> : pseudo + vos questions, la suite se passe sur votre serveur.</p>
                        <select id="f-registration-mode" name="registration_mode">
                            <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>MilSim complet (dossier détaillé)</option>
                            <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Simple (champs réduits, arrivée rapide)</option>
                            <option value="discord" <?= $registrationMode === 'discord' ? 'selected' : '' ?>>Via Discord (pseudo et questions personnalisées)</option>
                        </select>
                        <?php if ($registrationMode === 'discord'): ?>
                        <p class="bcs-help">Composez les questions dans <a class="bcs-link" href="<?= $h(url('back-office/recruitments/discord-questions')) ?>">Questions Discord</a> et renseignez le lien d’invitation en partie 3.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="cp-jeu">
                <?php $cardHead('Jeu et matériel', 'Ce qu’un candidat doit installer pour jouer avec vous.', ['Page publique', 'Registre des unités']); ?>
                <div class="bcs-card__body">
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-game">Jeu</label>
                            <input type="text" id="f-game" name="game_label" value="<?= $cv('game_label') ?>" maxlength="120" placeholder="Arma 3, Arma Reforger, Squad…">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-modpack">Taille du modpack <span class="bcs-opt">(Go, indicatif)</span></label>
                            <input type="text" id="f-modpack" name="modpack_size_gb" value="<?= $cv('modpack_size_gb') ?>" maxlength="32" class="bcs-input--tiny" placeholder="45">
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="f-mods">Mods principaux</label>
                        <p class="bcs-help">Une liste courte ou une description (ex. ACE3, TFAR, RHS).</p>
                        <textarea id="f-mods" name="main_mods" rows="4" class="bcs-mono" placeholder="ACE3&#10;TFAR&#10;RHS"><?= $cv('main_mods') ?></textarea>
                    </div>
                    <div class="bcs-field" id="f-style-badges">
                        <span class="bcs-label">Style de jeu</span>
                        <p class="bcs-help">Cochez ce qui vous correspond : affiché sur votre page publique.</p>
                        <div class="bcs-checks">
                            <?php foreach ($badgeLabels as $slug => $label): ?>
                                <label class="bcs-check">
                                    <input type="checkbox" name="style_badges[]" value="<?= $h((string) $slug) ?>" <?= in_array($slug, $selectedBadges, true) ? 'checked' : '' ?>>
                                    <?= $h((string) $label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- 5. Formulaire de candidature -->
        <div class="bcs-panel" data-cp-panel="candidature" id="cp-panel-candidature" role="tabpanel" hidden>
            <header class="bcs-intro">
                <p class="bcs-intro__kicker">Partie 5 · Formulaire de candidature</p>
                <h2 class="bcs-intro__title">Le dossier que remplissent les candidats</h2>
                <p class="bcs-intro__text">Utilisé quand le type de candidature est <strong>MilSim complet</strong> (partie 4). Les blocs les plus utiles sont ouverts ; les textes secondaires sont repliés. La marque affichée en haut du formulaire est fixée par la plateforme : <strong><?= $h(\App\Services\Community\EnlistmentMilsimPackService::PLATFORM_NAV_BRAND) ?></strong>.</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Formulaire de candidature']); ?>
                    <?php if ($registrationMode !== 'milsim'): ?><span class="bcs-pill bcs-pill--warn">Non utilisé actuellement (type : <?= $h(\App\Services\Community\TenantCommunityProfileService::registrationModeLabel($registrationMode)) ?>)</span><?php endif; ?>
                </div>
            </header>

            <section id="pack-milsim-editor" class="bcs-card">
                <?php $cardHead('Contenu du dossier', 'Ouvrez un bloc pour le modifier.'); ?>

                <details open class="bcs-fold">
                    <summary>Champs du dossier <span class="bcs-fold__hint">libellés, aides, type de réponse</span></summary>
                    <div class="bcs-fold__body">
                        <p class="bcs-help" style="margin:10px 0;">Pour chaque champ : libellé, aide et <em>type de réponse</em> (texte, zone, liste, oui/non). Pour les listes, une option par ligne.</p>
                        <?php
                        $fieldsData = is_array($em['fields'] ?? null) ? $em['fields'] : [];
                        $inputPrefix = 'em_fld';
                        include base_path('views/partials/milsim_pack_fields_editor.php');
                        ?>
                    </div>
                </details>

                <details open class="bcs-fold">
                    <summary>Section Motivation <span class="bcs-fold__hint">pourquoi vous rejoindre</span></summary>
                    <div class="bcs-fold__body">
                        <p class="bcs-help" style="margin:10px 0;">Titre, introduction, questions affichées et caractère obligatoire.</p>
                        <?php
                        $motivationData = is_array($em['motivation'] ?? null) ? $em['motivation'] : [];
                        $inputPrefix = 'em_motivation';
                        include base_path('views/partials/motivation_section_editor.php');
                        ?>
                    </div>
                </details>

                <details open class="bcs-fold">
                    <summary>Questions supplémentaires <span class="bcs-fold__hint">vos propres questions</span></summary>
                    <div class="bcs-fold__body">
                        <?php
                        $customQuestions = \App\Services\Community\EnlistmentMilsimPackService::normalizeCustomQuestions($em['custom_questions'] ?? []);
                        include base_path('views/partials/enlistment_custom_questions_editor.php');
                        ?>
                    </div>
                </details>

                <details class="bcs-fold">
                    <summary>Disponibilités et engagement <span class="bcs-fold__hint">créneaux proposés</span></summary>
                    <div class="bcs-fold__body">
                        <div class="bcs-field" style="border-top:0;">
                            <label class="bcs-label" for="f-em-q13">Question d’engagement</label>
                            <input type="text" id="f-em-q13" name="em_commitment_q13" value="<?= $h((string) ($em['commitment_q13'] ?? '')) ?>" maxlength="400" placeholder="Question engagement">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-q15">Question de confirmation des créneaux</label>
                            <p class="bcs-help">Vide alors que des créneaux sont cochés : une formulation est proposée automatiquement.</p>
                            <input type="text" id="f-em-q15" name="em_availability_q15" value="<?= $h((string) ($em['availability_q15'] ?? '')) ?>" maxlength="400" placeholder="Question de confirmation sur les créneaux">
                        </div>
                        <div class="bcs-field">
                            <?php
                            $selectedSlots = is_array($em['availability_slots'] ?? null) ? $em['availability_slots'] : [];
                            $idsInputName = 'em_availability_slot_ids[]';
                            $customInputName = 'em_availability_slot_custom[]';
                            $configuredFlagName = '';
                            $formId = '';
                            include base_path('views/partials/availability_slots_editor.php');
                            ?>
                        </div>
                    </div>
                </details>

                <details class="bcs-fold">
                    <summary>Refus automatique <span class="bcs-fold__hint">selon certaines réponses</span></summary>
                    <div class="bcs-fold__body">
                        <?php
                        $autoRefuseRules = \App\Services\Community\EnlistmentMilsimPackService::normalizeAutoRefuseRules($em['auto_refuse_rules'] ?? []);
                        $refuseFieldLabels = \App\Services\Community\EnlistmentMilsimPackService::refuseTargetFieldLabels($em);
                        include base_path('views/partials/enlistment_auto_refuse_editor.php');
                        ?>
                    </div>
                </details>

                <details class="bcs-fold">
                    <summary>Page d’entrée du formulaire <span class="bcs-fold__hint">préambule avant le dossier</span></summary>
                    <div class="bcs-fold__body">
                        <div class="bcs-grid">
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-letter">Lettre du logo</label>
                                <input type="text" id="f-em-letter" name="em_logo_letter" value="<?= $h(mb_substr((string) ($em['logo_letter'] ?? 'F'), 0, 3)) ?>" maxlength="3" class="bcs-input--tiny bcs-mono">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-watermark">Filigrane <span class="bcs-opt">(arrière-plan)</span></label>
                                <input type="text" id="f-em-watermark" name="em_watermark" value="<?= $h((string) ($em['watermark'] ?? '')) ?>" maxlength="40">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-ptitle">Titre du portail</label>
                                <input type="text" id="f-em-ptitle" name="em_portal_title" value="<?= $h((string) ($em['portal_title'] ?? '')) ?>" maxlength="200">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-psub">Sous-titre du portail</label>
                                <input type="text" id="f-em-psub" name="em_portal_subtitle" value="<?= $h((string) ($em['portal_subtitle'] ?? '')) ?>" maxlength="400">
                            </div>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-pretitle">Grand titre</label>
                            <input type="text" id="f-em-pretitle" name="em_preamble_title" value="<?= $h((string) ($em['preamble_title'] ?? '')) ?>" maxlength="200">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-lead">Texte d’introduction</label>
                            <textarea id="f-em-lead" name="em_preamble_lead" rows="3" maxlength="2000"><?= $h((string) ($em['preamble_lead'] ?? '')) ?></textarea>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-status">Bloc statut</label>
                            <p class="bcs-help">Une information par ligne.</p>
                            <textarea id="f-em-status" name="em_preamble_status" rows="4" class="bcs-mono"><?= $h($emPreambleStatus) ?></textarea>
                        </div>
                        <div class="bcs-grid">
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-cta">Bouton d’entrée</label>
                                <input type="text" id="f-em-cta" name="em_preamble_cta" value="<?= $h((string) ($em['preamble_cta'] ?? '')) ?>" maxlength="120">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-foot">Pied de page</label>
                                <input type="text" id="f-em-foot" name="em_preamble_footer" value="<?= $h((string) ($em['preamble_footer'] ?? '')) ?>" maxlength="600">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-classified">Badge « classifié »</label>
                                <input type="text" id="f-em-classified" name="em_classified_badge" value="<?= $h((string) ($em['classified_badge'] ?? '')) ?>" maxlength="40">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="bcs-fold">
                    <summary>Textes du dossier <span class="bcs-fold__hint">titres, règles, colonnes, bouton d’envoi</span></summary>
                    <div class="bcs-fold__body">
                        <div class="bcs-grid">
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-progress">Préfixe de progression</label>
                                <input type="text" id="f-em-progress" name="em_progress_prefix" value="<?= $h((string) ($em['progress_prefix'] ?? '')) ?>" maxlength="80">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-session">Titre du bloc session</label>
                                <input type="text" id="f-em-session" name="em_session_block_title" value="<?= $h((string) ($em['session_block_title'] ?? '')) ?>" maxlength="120">
                            </div>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-roe-title">Règles d’engagement (ROE)</label>
                            <p class="bcs-help">Un titre, puis une règle par ligne.</p>
                            <input type="text" id="f-em-roe-title" name="em_roe_title" value="<?= $h((string) ($em['roe_title'] ?? '')) ?>" maxlength="160" placeholder="Titre">
                            <textarea name="em_roe_lines" rows="5" aria-label="Règles, une par ligne"><?= $h($emRoeLines) ?></textarea>
                        </div>
                        <div class="bcs-grid">
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-doc">Référence du document</label>
                                <input type="text" id="f-em-doc" name="em_doc_control" value="<?= $h((string) ($em['doc_control'] ?? '')) ?>" maxlength="120">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-cand">Préfixe du titre de candidature</label>
                                <input type="text" id="f-em-cand" name="em_candidate_prefix" value="<?= $h((string) ($em['candidate_prefix'] ?? '')) ?>" maxlength="80">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-queue">Libellé de la file d’attente</label>
                                <input type="text" id="f-em-queue" name="em_queue_label" value="<?= $h((string) ($em['queue_label'] ?? '')) ?>" maxlength="120">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-ref">Libellé « Réf. »</label>
                                <input type="text" id="f-em-ref" name="em_ref_label" value="<?= $h((string) ($em['ref_label'] ?? '')) ?>" maxlength="40">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-sec">Mention de sécurité</label>
                                <input type="text" id="f-em-sec" name="em_security_label" value="<?= $h((string) ($em['security_label'] ?? '')) ?>" maxlength="40">
                            </div>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-op-title">Note opérationnelle : titre</label>
                            <input type="text" id="f-em-op-title" name="em_op_note_title" value="<?= $h((string) ($em['op_note_title'] ?? '')) ?>" maxlength="160">
                        </div>
                        <div class="bcs-grid">
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-col1">Colonne 1</label>
                                <textarea id="f-em-col1" name="em_op_col1" rows="3"><?= $h((string) ($em['op_col1'] ?? '')) ?></textarea>
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-col2">Colonne 2</label>
                                <textarea id="f-em-col2" name="em_op_col2" rows="3"><?= $h((string) ($em['op_col2'] ?? '')) ?></textarea>
                            </div>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-aiwarn">Avertissement IA <span class="bcs-opt">(affiché en rouge)</span></label>
                            <input type="text" id="f-em-aiwarn" name="em_op_ai_warning" value="<?= $h((string) ($em['op_ai_warning'] ?? '')) ?>" maxlength="600">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-archive">Note d’archive <span class="bcs-opt">(encadré gris)</span></label>
                            <textarea id="f-em-archive" name="em_archive_note" rows="2" maxlength="1200"><?= $h((string) ($em['archive_note'] ?? '')) ?></textarea>
                        </div>
                        <div class="bcs-field">
                            <span class="bcs-label">Titres des sections du formulaire</span>
                            <div class="bcs-grid" style="border-top:0;">
                                <div class="bcs-field"><label class="bcs-mini-label" for="f-em-s0">Section 0 (mode de candidature)</label><input type="text" id="f-em-s0" name="em_section_0" value="<?= $h((string) ($em['section_0'] ?? '')) ?>" maxlength="200"></div>
                                <?php for ($si = 1; $si <= 4; $si++): ?>
                                <div class="bcs-field"><label class="bcs-mini-label" for="f-em-s<?= $si ?>">Section <?= $si ?><?= $si === 3 ? ' (Motivation)' : '' ?></label><input type="text" id="f-em-s<?= $si ?>" name="em_section_<?= $si ?>" value="<?= $h((string) ($em['section_' . $si] ?? '')) ?>" maxlength="200"></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-em-aicheck">Case d’attestation « sans IA »</label>
                            <input type="text" id="f-em-aicheck" name="em_ai_checkbox" value="<?= $h((string) ($em['ai_checkbox'] ?? '')) ?>" maxlength="400">
                        </div>
                        <div class="bcs-grid">
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-submit">Bouton d’envoi</label>
                                <input type="text" id="f-em-submit" name="em_submit_button" value="<?= $h((string) ($em['submit_button'] ?? '')) ?>" maxlength="120">
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="f-em-submit-foot">Texte sous le bouton</label>
                                <input type="text" id="f-em-submit-foot" name="em_submit_footer" value="<?= $h((string) ($em['submit_footer'] ?? '')) ?>" maxlength="200">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="bcs-fold">
                    <summary>Avertissements affichés au candidat <span class="bcs-fold__hint">une idée par ligne</span></summary>
                    <div class="bcs-fold__body">
                        <p class="bcs-help" style="margin:10px 0;">Les mentions sur le rôle d’Athena et l’analyse automatique des réponses sont toujours ajoutées par la plateforme.</p>
                        <textarea name="em_disclaimer_recruitment" rows="6" aria-label="Avertissements, un par ligne"><?= $h(implode("\n", is_array($em['disclaimer_recruitment_lines'] ?? null) ? $em['disclaimer_recruitment_lines'] : \App\Services\Community\EnlistmentMilsimPackService::defaultRecruitmentDisclaimerLines())) ?></textarea>
                    </div>
                </details>

                <details class="bcs-fold">
                    <summary>Import avancé <span class="bcs-fold__hint">pour l’équipe technique</span></summary>
                    <div class="bcs-fold__body">
                        <p class="bcs-help" style="margin:10px 0;">Facultatif. Reprend les réglages de l’éditeur des champs ci-dessus ; utile pour coller une configuration fournie par l’équipe technique. Ne modifiez pas sans savoir ce que vous faites.</p>
                        <textarea name="em_fields_json" rows="10" class="bcs-mono" aria-label="Configuration avancée des champs"><?= $h($emFieldsJson) ?></textarea>
                    </div>
                </details>
            </section>
        </div>

        <!-- 6. Menus du portail -->
        <div class="bcs-panel" data-cp-panel="portail" id="cp-panel-portail" role="tabpanel" hidden>
            <header class="bcs-intro">
                <p class="bcs-intro__kicker">Partie 6 · Menus du portail</p>
                <h2 class="bcs-intro__title">Les menus Opérations et Ressources</h2>
                <p class="bcs-intro__text">Réservé à vos membres connectés : la présentation, la couleur et l’image des deux panneaux de navigation du portail. Ces réglages sont aussi disponibles dans <a class="bcs-link" href="<?= $h($settingsUrl . '?onglet=portail') ?>">Paramètres · Portail</a>.</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Portail membres']); ?>
                </div>
            </header>
            <?php foreach ([
                'operations' => ['label' => 'Menu Opérations', 'accent' => $navOpsAccent, 'style' => $navOpsStyle, 'imageUrl' => $navOpsImageUrl, 'imageEnabled' => $navOpsImageEnabled],
                'resources' => ['label' => 'Menu Ressources', 'accent' => $navResAccent, 'style' => $navResStyle, 'imageUrl' => $navResImageUrl, 'imageEnabled' => $navResImageEnabled],
            ] as $slot => $cfg): ?>
            <section class="bcs-card" id="cp-nav-<?= $h($slot) ?>">
                <?php $cardHead($cfg['label'], ''); ?>
                <div class="bcs-card__body">
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-nav-<?= $h($slot) ?>-accent">Couleur d’accent</label>
                            <select id="f-nav-<?= $h($slot) ?>-accent" name="nav_<?= $h($slot) ?>_accent">
                                <?php foreach ($navAccents as $accent): ?>
                                    <option value="<?= $h((string) $accent) ?>" <?= $cfg['accent'] === $accent ? 'selected' : '' ?>><?= $h(ucfirst((string) $accent)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="f-nav-<?= $h($slot) ?>-style">Présentation des liens</label>
                            <select id="f-nav-<?= $h($slot) ?>-style" name="nav_<?= $h($slot) ?>_submenu_style">
                                <option value="standard" <?= $cfg['style'] === 'standard' ? 'selected' : '' ?>>Standard</option>
                                <option value="cards" <?= $cfg['style'] === 'cards' ? 'selected' : '' ?>>Cartes</option>
                                <option value="minimal" <?= $cfg['style'] === 'minimal' ? 'selected' : '' ?>>Minimal</option>
                            </select>
                        </div>
                    </div>
                    <?php $renderSwitch(
                        'nav_' . $slot . '_image_enabled',
                        !empty($cfg['imageEnabled']),
                        'Afficher une image dans le panneau',
                        '',
                        'une illustration orne le haut du panneau.',
                        'le panneau n’affiche que les liens.'
                    ); ?>
                    <div class="bcs-media">
                        <span class="bcs-media__thumb">
                            <?php if (!empty($cfg['imageUrl'])): ?><img src="<?= $h((string) $cfg['imageUrl']) ?>" alt=""><?php else: ?>Image par défaut<?php endif; ?>
                        </span>
                        <div class="bcs-media__copy">
                            <label class="bcs-label" for="f-nav-<?= $h($slot) ?>-image">Image personnalisée <span class="bcs-opt">(facultatif)</span></label>
                            <p class="bcs-help">JPG, PNG ou WebP, 3 Mo maximum.</p>
                            <input type="file" id="f-nav-<?= $h($slot) ?>-image" name="nav_<?= $h($slot) ?>_image" class="bcs-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                            <?php if (!empty($cfg['imageUrl'])): ?>
                            <label class="bcs-check">
                                <input type="hidden" name="remove_nav_<?= $h($slot) ?>_image" value="0">
                                <input type="checkbox" name="remove_nav_<?= $h($slot) ?>_image" value="1">
                                <span>Revenir à l’image par défaut</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    </form>

    <div class="bcs-savebar" id="presentation-save-dock">
        <div class="bcs-savebar__copy">
            <p class="bcs-savebar__title" id="presentation-save-title">Aucune modification en attente</p>
            <p class="bcs-savebar__hint">Un seul bouton pour toute la page : un récapitulatif des changements s’affiche avant l’enregistrement.</p>
        </div>
        <button type="button" id="presentation-open-recap" class="bcs-btn bcs-btn--primary">Enregistrer</button>
    </div>
    </div>
</div>

    <div id="presentation-recap-modal" class="bcs-modal" role="dialog" aria-modal="true" aria-labelledby="presentation-recap-title" hidden>
        <div class="bcs-modal__backdrop" data-recap-close="1" tabindex="-1"></div>
        <div class="bcs-modal__dialog">
            <div class="bcs-modal__head">
                <h2 id="presentation-recap-title" class="bcs-modal__title">Vérifier avant d’enregistrer</h2>
                <p class="bcs-help">Les changements faits depuis l’ouverture de la page.</p>
            </div>
            <div id="presentation-recap-body" class="bcs-modal__body"></div>
            <p id="presentation-recap-empty" class="bcs-modal__body bcs-help" hidden>Aucune modification détectée depuis l’ouverture de la page. Vous pouvez tout de même enregistrer.</p>
            <div class="bcs-modal__foot">
                <button type="button" class="bcs-btn" data-recap-close="1">Continuer à modifier</button>
                <button type="button" id="presentation-confirm-submit" class="bcs-btn bcs-btn--primary">Confirmer l’enregistrement</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('community-presentation-form');
    if (!form) return;

    function cssEsc(s) {
        if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') return CSS.escape(s);
        return String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }
    function formToState(f) {
        var fd = new FormData(f);
        var o = {};
        fd.forEach(function (v, k) {
            if (v instanceof File) v = v.name || '';
            if (o[k] === undefined) o[k] = [];
            o[k].push(String(v));
        });
        Object.keys(o).forEach(function (k) { o[k].sort(); });
        return o;
    }
    var initialState = formToState(form);
    var recapBypass = false;

    function truncate(s, n) {
        s = String(s || '');
        if (s.length <= n) return s;
        return s.slice(0, n - 1) + '…';
    }
    function clean(t) {
        return truncate(String(t || '').replace(/\s+/g, ' ').trim(), 100);
    }
    function fieldLabel(el) {
        if (!el || !el.name) return el && el.name ? el.name : '';
        if (el.id) {
            var lb = form.querySelector('label[for="' + cssEsc(el.id) + '"]');
            if (lb) return clean(lb.textContent);
        }
        var sw = el.closest('.bcs-switch');
        if (sw) {
            var st = sw.querySelector('.bcs-switch__title');
            if (st) return clean(st.textContent);
        }
        if (el.getAttribute('aria-label')) return clean(el.getAttribute('aria-label'));
        var field = el.closest('.bcs-field');
        var fl = field ? field.querySelector('.bcs-label, .bcs-mini-label') : null;
        var card = el.closest('.bcs-card, .bcs-fold');
        var ct = card ? card.querySelector('.bcs-card__title, summary') : null;
        var cardTitle = ct ? clean(ct.childNodes[0] ? ct.childNodes[0].textContent : ct.textContent) : 'Formulaire';
        if (fl) return cardTitle + ' — ' + clean(fl.textContent);
        var p = el.closest('label');
        if (p) return clean(p.textContent);
        return cardTitle;
    }
    function getRepresentativeElement(name) {
        var list = form.querySelectorAll('[name="' + cssEsc(name) + '"]');
        for (var i = 0; i < list.length; i++) {
            if (list[i].type !== 'hidden') return list[i];
        }
        return list.length ? list[0] : null;
    }
    function changedNames() {
        var cur = formToState(form);
        var keys = Object.keys(initialState).concat(Object.keys(cur)).filter(function (v, i, a) { return a.indexOf(v) === i; });
        var out = [];
        keys.forEach(function (k) {
            if (JSON.stringify(initialState[k] || []) !== JSON.stringify(cur[k] || [])) out.push(k);
        });
        return { names: out, cur: cur };
    }

    var modal = document.getElementById('presentation-recap-modal');
    var bodyEl = document.getElementById('presentation-recap-body');
    var emptyEl = document.getElementById('presentation-recap-empty');
    var btnOpen = document.getElementById('presentation-open-recap');
    var btnConfirm = document.getElementById('presentation-confirm-submit');
    var dock = document.getElementById('presentation-save-dock');
    var dockTitle = document.getElementById('presentation-save-title');

    function refreshDock() {
        var n = changedNames().names.length;
        if (dock) dock.classList.toggle('is-dirty', n > 0);
        if (dockTitle) {
            dockTitle.textContent = n === 0
                ? 'Aucune modification en attente'
                : (n === 1 ? '1 champ modifié, non enregistré' : n + ' champs modifiés, non enregistrés');
        }
    }
    var dockTimer = null;
    function scheduleDock() {
        if (dockTimer) clearTimeout(dockTimer);
        dockTimer = setTimeout(refreshDock, 250);
    }
    form.addEventListener('input', scheduleDock);
    form.addEventListener('change', scheduleDock);
    form.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('button[type="button"]')) scheduleDock();
    });

    function openModal() {
        var diff = changedNames();
        var changes = diff.names;
        var cur = diff.cur;
        bodyEl.innerHTML = '';
        if (changes.length === 0) {
            emptyEl.hidden = false;
            bodyEl.hidden = true;
        } else {
            emptyEl.hidden = true;
            bodyEl.hidden = false;
            var ul = document.createElement('ul');
            ul.className = 'bcs-recap';
            changes.forEach(function (name) {
                var el = getRepresentativeElement(name);
                var before = truncate((initialState[name] || []).filter(Boolean).join(', ') || '—', 120);
                var after = truncate((cur[name] || []).filter(Boolean).join(', ') || '—', 120);
                var li = document.createElement('li');
                var pTitle = document.createElement('p');
                pTitle.className = 'bcs-recap__name';
                pTitle.textContent = fieldLabel(el);
                var pBefore = document.createElement('p');
                pBefore.className = 'bcs-recap__before';
                pBefore.textContent = 'Avant : ' + before;
                var pAfter = document.createElement('p');
                pAfter.className = 'bcs-recap__after';
                pAfter.textContent = 'Après : ' + after;
                li.appendChild(pTitle);
                li.appendChild(pBefore);
                li.appendChild(pAfter);
                ul.appendChild(li);
            });
            bodyEl.appendChild(ul);
        }
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (btnConfirm) btnConfirm.focus();
    }
    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        if (btnOpen) btnOpen.focus();
    }
    form.addEventListener('submit', function (e) {
        if (recapBypass) {
            recapBypass = false;
            return;
        }
        e.preventDefault();
        openModal();
    });
    if (btnOpen) btnOpen.addEventListener('click', openModal);
    modal.querySelectorAll('[data-recap-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
    });
    if (btnConfirm) btnConfirm.addEventListener('click', function () {
        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
            closeModal();
            return;
        }
        modal.hidden = true;
        document.body.style.overflow = '';
        if (typeof form.requestSubmit === 'function') {
            recapBypass = true;
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
    refreshDock();
})();
(function () {
    var container = document.getElementById('military-sections');
    var btn = document.getElementById('add-military-section');
    if (!container || !btn) return;
    btn.addEventListener('click', function () {
        var n = container.querySelectorAll('textarea[name="military_body[]"]').length + 1;
        var wrap = document.createElement('div');
        wrap.className = 'bcs-row bcs-stack military-section-row';
        wrap.innerHTML =
            '<div class="bcs-pair">' +
            '<input type="text" name="military_label[]" value="POINT ' + n + '" maxlength="32" class="bcs-mono" placeholder="Étiquette (ex. PRIMO)" aria-label="Section ' + n + ' : étiquette">' +
            '<input type="text" name="military_title[]" value="" maxlength="200" placeholder="Titre de section" aria-label="Section ' + n + ' : titre">' +
            '</div>' +
            '<textarea name="military_body[]" rows="4" placeholder="Contenu" aria-label="Section ' + n + ' : contenu"></textarea>';
        container.appendChild(wrap);
    });
})();
(function () {
    function reindex(container) {
        var rows = container.querySelectorAll('[data-prereq-row], [data-step-row]');
        rows.forEach(function (row, i) {
            var idx = row.querySelector('[data-row-index]');
            if (idx) idx.textContent = String(i + 1);
        });
    }
    var rowTools = '<div class="bcs-row__tools">' +
        '<button type="button" class="cp-move-up bcs-btn bcs-btn--small" title="Monter" aria-label="Monter">↑</button>' +
        '<button type="button" class="cp-move-down bcs-btn bcs-btn--small" title="Descendre" aria-label="Descendre">↓</button>' +
        '<button type="button" class="cp-remove-row bcs-btn bcs-btn--small bcs-btn--danger">Retirer</button>' +
        '</div>';
    function bindList(containerId, addBtnId, rowAttr, maxAttr, buildHtml) {
        var container = document.getElementById(containerId);
        var addBtn = document.getElementById(addBtnId);
        if (!container || !addBtn) return null;
        var max = parseInt(container.getAttribute(maxAttr) || '12', 10) || 12;
        function rows() {
            return container.querySelectorAll('[' + rowAttr + ']');
        }
        addBtn.addEventListener('click', function () {
            if (rows().length >= max) return;
            var wrap = document.createElement('div');
            wrap.className = 'bcs-row cp-dyn-row';
            wrap.setAttribute(rowAttr, '');
            wrap.innerHTML = buildHtml(rows().length + 1);
            container.appendChild(wrap);
            reindex(container);
            var first = wrap.querySelector('input[type="text"]');
            if (first) first.focus();
        });
        container.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) return;
            var row = t.closest('[' + rowAttr + ']');
            if (!row) return;
            if (t.closest('.cp-remove-row')) {
                if (rows().length <= 1) {
                    row.querySelectorAll('input[type="text"], textarea').forEach(function (el) { el.value = ''; });
                    row.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
                    reindex(container);
                    return;
                }
                row.remove();
                reindex(container);
                return;
            }
            if (t.closest('.cp-move-up')) {
                var prev = row.previousElementSibling;
                if (prev && prev.hasAttribute(rowAttr)) {
                    container.insertBefore(row, prev);
                    reindex(container);
                }
                return;
            }
            if (t.closest('.cp-move-down')) {
                var next = row.nextElementSibling;
                if (next && next.hasAttribute(rowAttr)) {
                    container.insertBefore(next, row);
                    reindex(container);
                }
            }
        });
        return container;
    }
    var prereqCatOptions = '';
    <?php foreach ($prereqCatalog as $ck => $cinfo): ?>
    prereqCatOptions += '<option value="<?= htmlspecialchars($ck, ENT_QUOTES, 'UTF-8') ?>" data-hint="<?= htmlspecialchars($cinfo['hint'], ENT_QUOTES, 'UTF-8') ?>" data-label="<?= htmlspecialchars($cinfo['label'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cinfo['label'], ENT_QUOTES, 'UTF-8') ?></option>';
    <?php endforeach; ?>
    var prereqContainer = bindList('prereq-rows', 'add-prereq-row', 'data-prereq-row', 'data-prereq-max', function (n) {
        return '' +
            '<div class="bcs-row__head"><span class="bcs-row__index">Prérequis <span data-row-index>' + n + '</span></span>' + rowTools + '</div>' +
            '<div class="bcs-grid">' +
            '<div class="bcs-field"><label class="bcs-mini-label">Libellé affiché</label>' +
            '<input type="text" name="public_prereq_label[]" value="" maxlength="160" placeholder="Ex. Micro correct et Discord"></div>' +
            '<div class="bcs-field"><label class="bcs-mini-label">Catégorie <span class="bcs-opt">(remplit le libellé)</span></label>' +
            '<select name="public_prereq_key[]" class="cp-prereq-cat"><option value="">Libre (texte seul)</option>' + prereqCatOptions + '</select></div>' +
            '<div class="bcs-field"><label class="bcs-mini-label">Niveau d’exigence</label>' +
            '<select name="public_prereq_status[]"><option value="required" selected>Exigé</option><option value="optional">Souhaité</option><option value="not_required">Non exigé</option></select></div>' +
            '<div class="bcs-field"><label class="bcs-mini-label">Précision <span class="bcs-opt">(facultatif)</span></label>' +
            '<input type="text" name="public_prereq_detail[]" value="" maxlength="240" placeholder="Court complément affiché sous le libellé"></div>' +
            '</div>';
    });
    if (prereqContainer) {
        prereqContainer.addEventListener('change', function (e) {
            var sel = e.target;
            if (!sel || !sel.classList || !sel.classList.contains('cp-prereq-cat')) return;
            var row = sel.closest('[data-prereq-row]');
            if (!row) return;
            var opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) return;
            var labelInput = row.querySelector('input[name="public_prereq_label[]"]');
            var detailInput = row.querySelector('input[name="public_prereq_detail[]"]');
            var catLabel = opt.getAttribute('data-label') || '';
            var catHint = opt.getAttribute('data-hint') || '';
            if (labelInput && labelInput.value.trim() === '') {
                labelInput.value = catLabel;
            }
            if (detailInput && detailInput.value === '' && catHint) {
                detailInput.placeholder = catHint;
            }
        });
    }
    bindList('step-rows', 'add-step-row', 'data-step-row', 'data-step-max', function (n) {
        return '' +
            '<div class="bcs-row__head"><span class="bcs-row__index">Étape <span data-row-index>' + n + '</span></span>' + rowTools + '</div>' +
            '<div class="bcs-grid bcs-grid--3">' +
            '<div class="bcs-field"><label class="bcs-mini-label">Titre</label>' +
            '<input type="text" name="public_step_title[]" value="" maxlength="120" placeholder="Ex. Candidature en ligne"></div>' +
            '<div class="bcs-field"><label class="bcs-mini-label">Délai</label>' +
            '<input type="text" name="public_step_delay[]" value="" maxlength="40" placeholder="sous 5 jours"></div>' +
            '<div class="bcs-field"><label class="bcs-mini-label">Affichage</label>' +
            '<select name="public_step_highlight[]"><option value="0" selected>Normal</option><option value="1">Mis en avant</option></select></div>' +
            '</div>' +
            '<div class="bcs-field"><label class="bcs-mini-label">Description courte</label>' +
            '<textarea name="public_step_body[]" rows="2" maxlength="500" placeholder="Ce que le candidat doit savoir à cette étape"></textarea></div>';
    });
})();
(function () {
    /* Navigation entre les parties + sommaire de la partie active */
    var tabs = document.querySelectorAll('[data-cp-tab]');
    var panels = document.querySelectorAll('[data-cp-panel]');
    if (!tabs.length || !panels.length) return;
    var order = [];
    panels.forEach(function (p) { order.push(p.getAttribute('data-cp-panel')); });
    var main = document.querySelector('.cp-presentation .bcs-main');

    panels.forEach(function (p) {
        var key = p.getAttribute('data-cp-panel');
        var sub = document.querySelector('[data-cp-sub="' + key + '"]');
        if (!sub) return;
        p.querySelectorAll('.bcs-card[id]').forEach(function (card) {
            var t = card.querySelector('.bcs-card__title');
            if (!t) return;
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.className = 'bcs-nav__sublink';
            a.href = '#' + card.id;
            a.setAttribute('data-cp-goto', '');
            a.textContent = t.textContent;
            li.appendChild(a);
            sub.appendChild(li);
        });
    });

    function scrollToEl(el) {
        if (!el) return;
        window.requestAnimationFrame(function () {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
    function show(id, opts) {
        opts = opts || {};
        if (order.indexOf(id) === -1) id = order[0];
        panels.forEach(function (p) {
            p.hidden = p.getAttribute('data-cp-panel') !== id;
        });
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-cp-tab') === id;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.classList.toggle('is-active', on);
        });
        document.querySelectorAll('[data-cp-sub]').forEach(function (s) {
            s.hidden = s.getAttribute('data-cp-sub') !== id;
        });
        if (opts.updateHash) {
            try { history.replaceState(null, '', '#' + id); } catch (e) {}
        }
        if (opts.scrollTop && main) {
            var top = main.getBoundingClientRect().top + window.pageYOffset - 80;
            if (window.pageYOffset > top) window.scrollTo({ top: top, behavior: 'smooth' });
        }
    }
    function goTo(hash, updateHash) {
        var h = String(hash || '').replace(/^#/, '');
        if (!h) return false;
        if (order.indexOf(h) !== -1 && !document.getElementById(h)) {
            show(h, { updateHash: updateHash, scrollTop: true });
            return true;
        }
        var el = document.getElementById(h);
        if (!el) return false;
        var panel = el.closest('[data-cp-panel]');
        if (!panel) return false;
        show(panel.getAttribute('data-cp-panel'), {});
        if (updateHash) {
            try { history.replaceState(null, '', '#' + h); } catch (e) {}
        }
        scrollToEl(el);
        var focusable = el.matches('input, select, textarea') ? el : null;
        if (focusable) setTimeout(function () { focusable.focus({ preventScroll: true }); }, 350);
        return true;
    }
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            show(String(t.getAttribute('data-cp-tab') || ''), { updateHash: true, scrollTop: true });
        });
    });
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[data-cp-goto]') : null;
        if (!a) return;
        if (goTo(a.getAttribute('href'), true)) e.preventDefault();
    });
    window.addEventListener('hashchange', function () {
        goTo(location.hash, false);
    });
    if (!goTo(location.hash, false)) show(order[0], {});
})();
</script>
