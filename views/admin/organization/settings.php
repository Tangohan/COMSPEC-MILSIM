<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $community */
/** @var array<string, mixed> $branding */
/** @var array<string, mixed> $orgSettings */
/** @var list<string> $orgTimezoneOptions */
/** @var string|null $registryCoverUrl */
/** @var string|null $navOpsImageUrl */
/** @var string|null $navResImageUrl */
/** @var string $orgSettingsFormAction */
/** @var array<string, mixed> $integrations */
/** @var array{training_days: int, active_service_days: int} $personnelLifecycle */

$c = $community ?? [];
$i = $integrations ?? [];
$b = $branding ?? [];
$settingsRoot = is_array($orgSettings ?? null) ? $orgSettings : [];
$lifecycle = is_array($personnelLifecycle ?? null) ? $personnelLifecycle : ['training_days' => 14, 'active_service_days' => 0];
$formAction = (string) ($orgSettingsFormAction ?? url('back-office/community'));
$zones = is_array($orgTimezoneOptions ?? null) ? $orgTimezoneOptions : \DateTimeZone::listIdentifiers();
$currentTz = (string) ($settingsRoot['timezone'] ?? 'Europe/Paris');
if ($currentTz === '' || !in_array($currentTz, $zones, true)) {
    $currentTz = 'Europe/Paris';
}

$logoUrl = trim((string) ($b['logo_url'] ?? ''));
if ($logoUrl === '') {
    $logoUrl = trim((string) ($tenant['logo_url'] ?? ''));
}
$bannerUrl = trim((string) ($b['banner_url'] ?? ''));
$faviconUrl = trim((string) ($b['favicon_url'] ?? ''));
$coverUrl = trim((string) ($registryCoverUrl ?? ''));
$navOpsUrl = trim((string) ($navOpsImageUrl ?? ''));
$navResUrl = trim((string) ($navResImageUrl ?? ''));
$primaryColor = trim((string) ($b['primary_color'] ?? '')) ?: '#059669';
$accentColor = trim((string) ($b['accent_color'] ?? '')) ?: '#0f172a';

$pm = is_array($c['public_modules'] ?? null) ? $c['public_modules'] : [];
$registrationMode = \App\Services\Community\TenantCommunityProfileService::normalizeRegistrationMode($c['registration_mode'] ?? 'milsim');
$registrationLabel = \App\Services\Community\TenantCommunityProfileService::registrationModeLabel($registrationMode);
$locale = \App\Support\CommunityLanguages::communityDefault($c);
$langSpoken = \App\Support\CommunityLanguages::communityLanguages($c);
$langOrbat = \App\Support\CommunityLanguages::orbatLanguageSetting($c);
$langUnitCountry = \App\Support\CommunityLanguages::unitCountry($c);
$langCountryFromAffiliation = $langUnitCountry !== '' && trim((string) ($c['unit_country'] ?? '')) === '';
$langCountryOptions = \App\Support\CommunityLanguages::countryOptions();
$langGradeCountry = \App\Support\CommunityLanguages::preferredGradeCountry(['community' => $c] + (is_array($orgSettings ?? null) ? $orgSettings : []));
$orbatVis = (string) ($c['orbat_visibility'] ?? 'members');
if (!in_array($orbatVis, ['public', 'members', 'command'], true)) {
    $orbatVis = 'members';
}
$slugHint = trim((string) ($tenant['slug'] ?? ''));
$publicPageUrl = $slugHint !== '' ? url('c/' . rawurlencode($slugHint)) : '';

$portalNav = is_array($c['portal_nav'] ?? null) ? $c['portal_nav'] : [];
$navAccents = \App\Services\Community\TenantCommunityProfileService::allowedNavAccents();
$navStyles = \App\Services\Community\TenantCommunityProfileService::allowedNavSubmenuStyles();
$navOps = is_array($portalNav['operations'] ?? null) ? $portalNav['operations'] : [];
$navRes = is_array($portalNav['resources'] ?? null) ? $portalNav['resources'] : [];
$navOpsAccent = in_array((string) ($navOps['accent'] ?? 'sky'), $navAccents, true) ? (string) ($navOps['accent'] ?? 'sky') : 'sky';
$navResAccent = in_array((string) ($navRes['accent'] ?? 'amber'), $navAccents, true) ? (string) ($navRes['accent'] ?? 'amber') : 'amber';
$navOpsStyle = in_array((string) ($navOps['submenu_style'] ?? 'cards'), $navStyles, true) ? (string) ($navOps['submenu_style'] ?? 'cards') : 'cards';
$navResStyle = in_array((string) ($navRes['submenu_style'] ?? 'minimal'), $navStyles, true) ? (string) ($navRes['submenu_style'] ?? 'minimal') : 'minimal';
$navOpsImageEnabled = !array_key_exists('image_enabled', $navOps) || !empty($navOps['image_enabled']);
$navResImageEnabled = !array_key_exists('image_enabled', $navRes) || !empty($navRes['image_enabled']);

$loginAccueilImages = is_array($loginAccueilImages ?? null) ? $loginAccueilImages : [];
$loginAccueilSlideshow = (bool) ($loginAccueilSlideshow ?? true);
$loginAccueilMaxImages = (int) ($loginAccueilMaxImages ?? 8);
$loginAccueilDefaultUrl = trim((string) ($loginAccueilDefaultUrl ?? \App\Support\LoginAccueilImageStorage::defaultPublicUrl()));
$loginAccueilHint = trim((string) ($loginAccueilHint ?? \App\Support\LoginAccueilImageStorage::hintText()));
$canAddLoginAccueil = count($loginAccueilImages) < $loginAccueilMaxImages;

$navAccentLabels = [
    'sky' => 'Ciel',
    'amber' => 'Ambre',
    'emerald' => 'Émeraude',
    'violet' => 'Violet',
    'rose' => 'Rose',
    'slate' => 'Ardoise',
];
$navStyleLabels = [
    'standard' => 'Standard',
    'cards' => 'Cartes',
    'minimal' => 'Liste',
];

$communityLocked = !empty($c['community_locked']);
$publicHeroSubtitle = trim((string) ($c['public_hero_subtitle'] ?? ''));
$publicAboutTitle = trim((string) ($c['public_about_title'] ?? ''));
$publicAboutBody = trim((string) ($c['public_about_body'] ?? ''));

$discordInviteMissing = \App\Services\Community\TenantCommunityProfileService::needsDiscordInviteAlert($c);

$registryListed = !array_key_exists('registry_listed', $c) || !empty($c['registry_listed']);
$forumMembersOnly = !empty($c['forum_members_only']);
$unitAffiliation = is_array($c['unit_affiliation'] ?? null) ? $c['unit_affiliation'] : [];
$unitAffiliationIsReal = !empty($unitAffiliation['is_real']);
$unitAffiliationMode = $unitAffiliation !== [] ? ($unitAffiliationIsReal ? 'real' : 'fictional') : '';
$unitAffiliationCountry = strtoupper(trim((string) ($unitAffiliation['country'] ?? '')));
$unitAffiliationFictionalLabel = trim((string) ($unitAffiliation['fictional_label'] ?? ''));
$unitAffiliationUnitIds = [];
foreach (($unitAffiliation['unit_ids'] ?? []) as $unitId) {
    if (is_string($unitId) && trim($unitId) !== '') {
        $unitAffiliationUnitIds[] = trim($unitId);
    }
}
$unitCountryLabels = \App\Services\Community\RealUnitAffiliationCatalog::countryLabels();
$unitCatalogPayload = \App\Services\Community\RealUnitAffiliationCatalog::frontendPayload();

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

/**
 * Interrupteur lisible : titre, aide et conséquence affichée selon l’état.
 * Les noms de champs (valeur cachée 0 + case 1) sont inchangés.
 */
$renderToggle = static function (
    string $label,
    string $help,
    string $name,
    bool $checked,
    string $effectOn = '',
    string $effectOff = '',
) use ($h): void {
    ?>
    <label class="bcs-switch">
        <input type="hidden" name="<?= $h($name) ?>" value="0">
        <input type="checkbox" class="bcs-switch__input" name="<?= $h($name) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
        <span class="bcs-switch__track" aria-hidden="true"></span>
        <span class="bcs-switch__copy">
            <span class="bcs-switch__title"><?= $h($label) ?></span>
            <?php if ($help !== ''): ?><span class="bcs-switch__help"><?= $h($help) ?></span><?php endif; ?>
            <?php if ($effectOn !== ''): ?><span class="bcs-switch__effect bcs-switch__effect--on"><strong>Activé :</strong> <span><?= $h($effectOn) ?></span></span><?php endif; ?>
            <?php if ($effectOff !== ''): ?><span class="bcs-switch__effect bcs-switch__effect--off"><strong>Désactivé :</strong> <span><?= $h($effectOff) ?></span></span><?php endif; ?>
        </span>
    </label>
    <?php
};

/** Pastilles « Où ça apparaît ? » */
$renderWhere = static function (array $places, string $extraClass = '') use ($h): void {
    ?>
    <p class="bcs-where<?= $extraClass !== '' ? ' ' . $h($extraClass) : '' ?>">
        <span class="bcs-where__label">Où ça apparaît ?</span>
        <?php foreach ($places as $place): ?>
            <span class="bcs-chip"><?= $h((string) $place) ?></span>
        <?php endforeach; ?>
    </p>
    <?php
};

/** Vignette d’image (ou état vide). */
$renderThumb = static function (string $url, string $emptyLabel, bool $square = false) use ($h): void {
    ?>
    <span class="bcs-media__thumb<?= $square ? ' bcs-media__thumb--square' : '' ?>">
        <?php if ($url !== ''): ?>
            <img src="<?= $h($url) ?>" alt="">
        <?php else: ?>
            <?= $h($emptyLabel) ?>
        <?php endif; ?>
    </span>
    <?php
};

$tenantTypeOptions = is_array($tenantTypeOptions ?? null) ? $tenantTypeOptions : \App\Services\Community\TenantTypeConfig::availableTypes();
$currentTenantType = \App\Services\Community\TenantTypeConfig::normalizeType(
    (string) ($currentTenantType ?? ($tenant['tenant_type'] ?? 'full'))
);
$tenantTypeFormAction = (string) ($tenantTypeFormAction ?? url('back-office/organisation/profil'));
$currentTypeLabel = \App\Services\Community\TenantTypeConfig::label($currentTenantType);
$hubTab = (string) ($settingsHubTab ?? 'identite');
if (!in_array($hubTab, ['identite', 'vitrine', 'inscription', 'accueil', 'portail', 'profil'], true)) {
    $hubTab = 'identite';
}
$hubInscriptionUrl = $formAction . (str_contains($formAction, '?') ? '&' : '?') . 'onglet=inscription#coordonnees';
$hubSubmitForm = $hubTab === 'inscription' ? 'bo-inscription-settings-form' : 'bo-community-settings-form';
$hubSaveHints = [
    'identite' => 'Enregistre en une fois les rubriques Identité, Vitrine, Portail et le cycle administratif.',
    'vitrine' => 'Enregistre en une fois les rubriques Identité, Vitrine, Portail et le cycle administratif.',
    'portail' => 'Enregistre en une fois les rubriques Identité, Vitrine, Portail et le cycle administratif.',
    'profil' => 'Enregistre le cycle administratif. Le type de communauté s’applique avec son propre bouton « Appliquer le profil ».',
    'inscription' => 'Enregistre le parcours d’arrivée, le contact des candidats et les questions du dossier.',
    'accueil' => 'Chaque photo s’enregistre avec les boutons de la rubrique.',
];
$hubSaveHint = $hubSaveHints[$hubTab] ?? $hubSaveHints['identite'];
$hubNavSelected = static fn (string $key): string => $hubTab === $key
    ? 'class="bcs-nav__item is-active" aria-selected="true" tabindex="0"'
    : 'class="bcs-nav__item" aria-selected="false" tabindex="-1"';
$publicPageBase = (string) url('c/');
if (!preg_match('#^https?://#i', $publicPageBase)) {
    $publicPageBase = trim((string) ($_SERVER['HTTP_HOST'] ?? '')) . '/' . ltrim($publicPageBase, '/');
}
$publicPageHost = preg_replace('#^https?://#i', '', $publicPageBase) ?? 'c/';
$imagesNeedAddress = $slugHint === '';
?>
<div class="bcs bo-settings-hub" data-settings-hub data-active-tab="<?= $h($hubTab) ?>">
<div class="bcs-layout">

    <aside class="bcs-aside" aria-label="Rubriques des paramètres">
        <nav class="bcs-box bcs-nav">
            <p class="bcs-box__title">Rubriques</p>
            <div class="bcs-nav__list" role="tablist" aria-orientation="vertical" aria-label="Rubriques des paramètres">
                <button type="button" role="tab" id="bo-settings-tab-identite" data-settings-tab="identite" <?= $hubNavSelected('identite') ?>>
                    <span class="bcs-nav__num" aria-hidden="true">1</span>
                    <span class="bcs-nav__label">Identité</span>
                    <span class="bcs-nav__desc">Nom, adresse, logo, unité, pays et langues</span>
                </button>
                <button type="button" role="tab" id="bo-settings-tab-vitrine" data-settings-tab="vitrine" <?= $hubNavSelected('vitrine') ?>>
                    <span class="bcs-nav__num" aria-hidden="true">2</span>
                    <span class="bcs-nav__label">Vitrine</span>
                    <span class="bcs-nav__desc">Textes publics, registre, modules visibles</span>
                </button>
                <button type="button" role="tab" id="bo-settings-tab-inscription" data-settings-tab="inscription" <?= $hubNavSelected('inscription') ?>>
                    <span class="bcs-nav__num" aria-hidden="true">3</span>
                    <span class="bcs-nav__label">Inscription</span>
                    <span class="bcs-nav__desc">Candidatures, contact, questions</span>
                </button>
                <button type="button" role="tab" id="bo-settings-tab-accueil" data-settings-tab="accueil" <?= $hubNavSelected('accueil') ?>>
                    <span class="bcs-nav__num" aria-hidden="true">4</span>
                    <span class="bcs-nav__label">Écran d’accueil</span>
                    <span class="bcs-nav__desc">Photos affichées après la connexion</span>
                </button>
                <button type="button" role="tab" id="bo-settings-tab-portail" data-settings-tab="portail" <?= $hubNavSelected('portail') ?>>
                    <span class="bcs-nav__num" aria-hidden="true">5</span>
                    <span class="bcs-nav__label">Portail</span>
                    <span class="bcs-nav__desc">Menus, couleurs, images, relais Discord</span>
                </button>
                <button type="button" role="tab" id="bo-settings-tab-profil" data-settings-tab="profil" <?= $hubNavSelected('profil') ?>>
                    <span class="bcs-nav__num" aria-hidden="true">6</span>
                    <span class="bcs-nav__label">Profil</span>
                    <span class="bcs-nav__desc">Type de communauté, cycle des recrues</span>
                </button>
            </div>
        </nav>

        <div class="bcs-box bcs-aside__extra">
            <p class="bcs-box__title">En un coup d’œil</p>
            <dl class="bcs-status">
                <div class="bcs-status__row">
                    <dt>Page publique</dt>
                    <dd><span class="bcs-pill <?= $slugHint !== '' ? 'bcs-pill--ok' : 'bcs-pill--warn' ?>"><?= $slugHint !== '' ? 'En ligne' : 'Sans adresse' ?></span></dd>
                </div>
                <div class="bcs-status__row">
                    <dt>Registre des unités</dt>
                    <dd><span class="bcs-pill <?= $registryListed ? 'bcs-pill--ok' : '' ?>"><?= $registryListed ? 'Listée' : 'Masquée' ?></span></dd>
                </div>
                <div class="bcs-status__row">
                    <dt>Recrutement</dt>
                    <dd><span class="bcs-pill <?= $communityLocked ? 'bcs-pill--warn' : 'bcs-pill--ok' ?>"><?= $communityLocked ? 'Fermé' : 'Ouvert' ?></span></dd>
                </div>
                <div class="bcs-status__row">
                    <dt>Pays et langues</dt>
                    <dd><span class="bcs-pill"><?= $h(trim(($langUnitCountry !== '' ? \App\Support\Profile\PublicFlagCountryCatalog::flagEmoji($langUnitCountry) . ' ' : '') . implode(' · ', array_map('strtoupper', $langSpoken)))) ?></span></dd>
                </div>
                <div class="bcs-status__row">
                    <dt>Candidature</dt>
                    <dd><span class="bcs-pill"><?= $h($registrationLabel) ?></span></dd>
                </div>
            </dl>
            <div class="bcs-box__actions">
                <?php if ($publicPageUrl !== ''): ?>
                    <a class="bcs-btn bcs-btn--block" href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir la page publique ↗</a>
                <?php endif; ?>
                <a class="bcs-btn bcs-btn--block" href="<?= $h(url('back-office/community/presentation')) ?>">Mise en page de la vitrine</a>
            </div>
        </div>
    </aside>

    <div class="bcs-main">

    <?php if ($discordInviteMissing): ?>
        <div class="bcs-notice" role="status">
            <span aria-hidden="true">⚠</span>
            <span><strong>Lien Discord manquant.</strong> Le recrutement se fait via Discord, mais aucun lien d’invitation n’est renseigné : les candidats ne pourront pas rejoindre votre serveur depuis le formulaire public. <a class="bcs-link" href="<?= $h($hubInscriptionUrl) ?>">Renseigner le lien Discord</a></span>
        </div>
    <?php endif; ?>

    <div data-settings-panel="accueil"<?= $hubTab === 'accueil' ? '' : ' hidden' ?>>
    <header class="bcs-intro">
        <p class="bcs-intro__kicker">Rubrique 4 · Écran d’accueil</p>
        <h2 class="bcs-intro__title">Les photos que voient vos membres en se connectant</h2>
        <p class="bcs-intro__text">Juste après la connexion, Athena affiche un écran de bienvenue en plein écran. Mettez-y des photos de vos opérations : c’est la première chose que voient vos membres. Ces photos ne sont <strong>pas</strong> visibles par les visiteurs.</p>
        <div class="bcs-intro__foot">
            <?php $renderWhere(['Écran de bienvenue après connexion']); ?>
        </div>
    </header>
    <section class="bcs-card" id="accueil-connexion">
        <div class="bcs-card__head">
            <div class="bcs-card__heading">
                <h3 class="bcs-card__title">Images d’accueil</h3>
                <p class="bcs-card__desc">Sans image personnalisée, la photo par défaut du portail est utilisée. Avec plusieurs images, elles défilent dans l’ordre de la liste.</p>
            </div>
        </div>

        <?php if ($loginAccueilImages === []): ?>
        <div class="bo-accueil-preview">
            <img src="<?= $h($loginAccueilDefaultUrl) ?>" alt="" class="bo-accueil-preview__img">
            <p class="bo-accueil-preview__caption">Aucune photo ajoutée : c’est l’image par défaut du portail qui s’affiche.</p>
        </div>
        <?php else: ?>
        <ol class="bo-accueil-gallery">
            <?php foreach ($loginAccueilImages as $idx => $img): ?>
                <?php
                $imgId = (int) ($img['id'] ?? 0);
                $imgUrl = trim((string) ($img['url'] ?? ''));
                $imgAlt = trim((string) ($img['alt'] ?? ''));
                ?>
                <li class="bo-accueil-gallery__item">
                    <?php if ($imgUrl !== ''): ?>
                        <img src="<?= $h($imgUrl) ?>" alt="<?= $h($imgAlt) ?>" class="bo-accueil-gallery__thumb">
                    <?php endif; ?>
                    <div class="bo-accueil-gallery__meta">
                        <p class="bo-accueil-gallery__order">Image <?= (int) $idx + 1 ?></p>
                        <div class="bo-accueil-gallery__actions">
                            <?php if ($idx > 0): ?>
                            <form method="post" action="<?= $h(url('back-office/organisation/parametres/accueil-connexion/' . $imgId . '/ordre')) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="bo-accueil-btn bcs-btn bcs-btn--small">Monter</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($idx < count($loginAccueilImages) - 1): ?>
                            <form method="post" action="<?= $h(url('back-office/organisation/parametres/accueil-connexion/' . $imgId . '/ordre')) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="bo-accueil-btn bcs-btn bcs-btn--small">Descendre</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="<?= $h(url('back-office/organisation/parametres/accueil-connexion/' . $imgId . '/supprimer')) ?>" onsubmit="return confirm('Retirer cette image d’accueil ?');">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="bo-accueil-btn bo-accueil-btn--danger bcs-btn bcs-btn--small bcs-btn--danger">Retirer</button>
                            </form>
                        </div>
                        <form method="post" enctype="multipart/form-data" action="<?= $h(url('back-office/organisation/parametres/accueil-connexion/' . $imgId)) ?>" class="bo-accueil-gallery__edit">
                            <?= \App\Core\Csrf::field() ?>
                            <label class="bo-accueil-field">
                                <span>Description courte <span class="bcs-opt">(facultatif, lue par les lecteurs d’écran)</span></span>
                                <input type="text" name="login_accueil_alt" maxlength="200" value="<?= $h($imgAlt) ?>" placeholder="Paysage nocturne, opérateurs en forêt">
                            </label>
                            <label class="bo-accueil-field">
                                <span>Remplacer l’image</span>
                                <input type="file" name="login_accueil_replace" class="bcs-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                            </label>
                            <button type="submit" class="bo-accueil-btn bo-accueil-btn--primary bcs-btn bcs-btn--small">Enregistrer cette image</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>

        <?php if (count($loginAccueilImages) > 1): ?>
        <form method="post" action="<?= $h(url('back-office/organisation/parametres/accueil-connexion/defilement')) ?>" class="bo-accueil-slideshow bcs-sub">
            <?= \App\Core\Csrf::field() ?>
            <label class="bcs-check">
                <input type="hidden" name="login_accueil_slideshow" value="0">
                <input type="checkbox" name="login_accueil_slideshow" value="1" <?= $loginAccueilSlideshow ? 'checked' : '' ?>>
                Défiler automatiquement les images
            </label>
            <p class="bcs-help">Les photos changent toutes les dix secondes. Le défilement s’arrête si le visiteur a demandé moins d’animations dans son système.</p>
            <div><button type="submit" class="bo-accueil-btn bo-accueil-btn--primary bcs-btn bcs-btn--small">Enregistrer le défilement</button></div>
        </form>
        <?php endif; ?>

        <?php if ($canAddLoginAccueil): ?>
        <form method="post" enctype="multipart/form-data" action="<?= $h(url('back-office/organisation/parametres/accueil-connexion')) ?>" class="bo-accueil-add bcs-sub">
            <?= \App\Core\Csrf::field() ?>
            <p class="bcs-sub__title">Ajouter une image</p>
            <p class="bcs-sub__help"><?= $h($loginAccueilHint) ?> Jusqu’à <?= (int) $loginAccueilMaxImages ?> images. Conseil : photos paysage, sujet au centre (les bords peuvent être rognés sur mobile).</p>
            <input type="file" name="login_accueil_images[]" class="bcs-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple>
            <label class="bo-accueil-field">
                <span>Description courte pour ces nouvelles images <span class="bcs-opt">(facultatif)</span></span>
                <input type="text" name="login_accueil_alt" maxlength="200" placeholder="Opérateurs en vision nocturne">
            </label>
            <div><button type="submit" class="bo-accueil-btn bo-accueil-btn--primary bcs-btn bcs-btn--primary">Ajouter</button></div>
        </form>
        <?php else: ?>
        <p class="bcs-empty">Nombre maximum d’images atteint. Retirez-en une pour en ajouter une autre.</p>
        <?php endif; ?>
    </section>
    </div>

    <div data-settings-panel="profil"<?= $hubTab === 'profil' ? '' : ' hidden' ?>>
    <header class="bcs-intro">
        <p class="bcs-intro__kicker">Rubrique 6 · Profil</p>
        <h2 class="bcs-intro__title">Le type de communauté et le cycle des recrues</h2>
        <p class="bcs-intro__text">Le <strong>type de communauté</strong> choisit les outils proposés à tous vos membres (menus, modules, permissions). Le <strong>cycle administratif</strong> fixe combien de temps une recrue reste « En formation » avant de passer en service actif.</p>
        <div class="bcs-intro__foot">
            <?php $renderWhere(['Menus du portail', 'Fiches des membres']); ?>
        </div>
    </header>
    <form method="post" action="<?= $h($tenantTypeFormAction) ?>" class="bcs-card" id="profil">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="settings_tab" value="profil">
        <span id="org-profil"></span>
        <div class="bcs-card__head">
            <div class="bcs-card__heading">
                <h3 class="bcs-card__title">Type de communauté</h3>
                <p class="bcs-card__desc">Profil actuel : <strong><?= $h($currentTypeLabel) ?></strong>. Changer ou réappliquer un profil ajuste les menus et les permissions, <strong>sans effacer de données</strong>.</p>
            </div>
        </div>
        <fieldset class="bcs-choices" style="border:none;padding:0;margin:14px 0 0;">
            <legend class="sr-only">Profil de la communauté</legend>
            <?php foreach ($tenantTypeOptions as $typeKey => $typeMeta): ?>
                <?php
                $isCurrent = $typeKey === $currentTenantType;
                $inputId = 'tenant_type_' . preg_replace('/[^a-z0-9_-]/i', '', (string) $typeKey);
                ?>
                <label for="<?= $h($inputId) ?>" class="bcs-choice">
                    <input id="<?= $h($inputId) ?>" type="radio" name="tenant_type" value="<?= $h((string) $typeKey) ?>" <?= $isCurrent ? 'checked' : '' ?>>
                    <span>
                        <span class="bcs-choice__title"><?= $h((string) ($typeMeta['label'] ?? $typeKey)) ?><?= $isCurrent ? ' · actuel' : '' ?></span>
                        <span class="bcs-choice__help"><?= $h((string) ($typeMeta['description'] ?? '')) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="bcs-card__foot" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
            <label class="bcs-check">
                <input type="checkbox" name="confirm_type_change" value="1" required>
                <span>Je confirme l’application de ce profil pour toute la communauté.</span>
            </label>
            <button type="submit" class="bcs-btn">Appliquer le profil</button>
        </div>
    </form>
    </div>

    <form method="post" enctype="multipart/form-data" action="<?= $h($formAction) ?>" id="bo-community-settings-form" class="bcs-panel" data-bcs-track>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="settings_tab" value="<?= $h($hubTab) ?>">

            <div data-settings-panel="identite"<?= $hubTab === 'identite' ? '' : ' hidden' ?>>
            <header class="bcs-intro" id="identite">
                <p class="bcs-intro__kicker">Rubrique 1 · Identité</p>
                <h2 class="bcs-intro__title">Qui vous êtes, en quelques champs</h2>
                <p class="bcs-intro__text">Le nom, l’adresse et le logo de votre communauté. Ils sont repris partout : sur votre <strong>page publique</strong>, sur votre <strong>carte dans le registre des unités</strong> et en haut du <strong>portail de vos membres</strong>.</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Page publique', 'Registre des unités', 'Portail membres']); ?>
                    <?php if ($publicPageUrl !== ''): ?>
                        <a class="bcs-link" href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir le résultat ↗</a>
                    <?php endif; ?>
                </div>
            </header>

            <section class="bcs-card">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Nom et adresse</h3>
                        <p class="bcs-card__desc">Ce que les visiteurs lisent en premier et l’adresse à partager sur Discord ou les forums.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="tenant_name">Nom affiché</label>
                        <p class="bcs-help">Le nom complet de votre communauté, tel que vous voulez qu’on le lise. Exemple : « 92e Régiment d’Infanterie » ou « Task Force Phoenix ».</p>
                        <input type="text" id="tenant_name" name="tenant_name" maxlength="255" value="<?= $h((string) ($tenant['name'] ?? '')) ?>" placeholder="Ex. 92e RI">
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="tenant_slug">Adresse de la page publique</label>
                        <p class="bcs-help">Lettres minuscules, chiffres et tirets uniquement. <em>Attention :</em> si vous la changez, les liens déjà partagés ne fonctionneront plus.</p>
                        <div class="bcs-affix">
                            <span class="bcs-affix__prefix"><?= $h($publicPageHost) ?></span>
                            <input type="text" id="tenant_slug" name="tenant_slug" maxlength="50" pattern="[a-z0-9]([-a-z0-9]*[a-z0-9])?" value="<?= $h($slugHint) ?>" placeholder="mon-unite">
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="game_label">Jeu ou plateforme <span class="bcs-opt">(facultatif)</span></label>
                        <p class="bcs-help">Affiché sur votre page publique et sur votre carte du registre : les joueurs filtrent souvent par jeu.</p>
                        <input type="text" id="game_label" name="game_label" class="bcs-input--short" maxlength="120" value="<?= $h((string) ($c['game_label'] ?? '')) ?>" placeholder="Ex. Arma 3, Arma Reforger, Squad">
                    </div>
                </div>
            </section>

            <section class="bcs-card">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Logo et image du registre</h3>
                        <p class="bcs-card__desc">JPG, PNG ou WebP, 12 Mo maximum. Les images sont recadrées automatiquement.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <?php if ($imagesNeedAddress): ?>
                        <p class="bcs-notice bcs-notice--info">Renseignez d’abord l’adresse de la page publique et enregistrez : l’envoi d’images sera alors disponible.</p>
                    <?php endif; ?>
                    <div class="bcs-media">
                        <?php $renderThumb($logoUrl, 'Pas de logo', true); ?>
                        <div class="bcs-media__copy">
                            <span class="bcs-label">Logo</span>
                            <p class="bcs-help">Carré, fond transparent de préférence. Affiché en haut du portail, sur la page publique et dans le registre.</p>
                            <input type="file" name="org_logo" class="bcs-file" aria-label="Choisir un logo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $imagesNeedAddress ? 'disabled' : '' ?>>
                            <?php if ($logoUrl !== ''): ?>
                            <label class="bcs-check">
                                <input type="hidden" name="remove_org_logo" value="0">
                                <input type="checkbox" name="remove_org_logo" value="1">
                                Retirer le logo
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bcs-media">
                        <?php $renderThumb($coverUrl, 'Fond coloré automatique'); ?>
                        <div class="bcs-media__copy">
                            <span class="bcs-label">Image d’en-tête du registre</span>
                            <p class="bcs-help">Photo paysage (environ 1600 × 600) en haut de votre carte dans le registre des unités. Sans image, un fond coloré est généré.</p>
                            <input type="file" name="registry_cover" class="bcs-file" aria-label="Choisir une image d’en-tête du registre" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $imagesNeedAddress ? 'disabled' : '' ?>>
                            <?php if ($coverUrl !== ''): ?>
                            <label class="bcs-check">
                                <input type="hidden" name="remove_registry_cover" value="0">
                                <input type="checkbox" name="remove_registry_cover" value="1">
                                Retirer l’image du registre
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bcs-card">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Accueil des nouveaux membres</h3>
                        <p class="bcs-card__desc">Deux aides pour que les nouveaux trouvent leurs repères.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="welcome_text">Message de bienvenue <span class="bcs-opt">(facultatif)</span></label>
                        <p class="bcs-help">Quelques phrases affichées à vos membres sur le portail. À ne pas confondre avec la bio du bandeau public (rubrique Vitrine).</p>
                        <textarea id="welcome_text" name="welcome_text" rows="3" maxlength="500" placeholder="Bienvenue à la Task Force ! Pensez à lire le règlement et à rejoindre le Discord avant votre première opération."><?= $h((string) ($c['welcome_text'] ?? '')) ?></textarea>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="community_code">Code pour rejoindre <span class="bcs-opt">(facultatif)</span></label>
                        <p class="bcs-help">Un code court à donner aux nouveaux : ils le saisissent sur la page « Rejoindre » pour trouver directement votre communauté.</p>
                        <input type="text" id="community_code" name="community_code" class="bcs-input--short bcs-mono" maxlength="64" value="<?= $h((string) ($tenant['community_code'] ?? '')) ?>" placeholder="Ex. TF-PHOENIX">
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="affiliation">
                <span id="representation-unite"></span>
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Unité représentée</h3>
                        <p class="bcs-card__desc">Votre communauté s’inspire-t-elle d’une unité réelle (régiment, commando, composante…) ou d’un cadre fictif ? Ce choix s’affiche sur votre page publique et aide les joueurs à vous trouver.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <div class="bo-unit-affiliation-panel">
                            <div class="bo-unit-affiliation-modes" role="radiogroup" aria-label="Type de représentation">
                                <label>
                                    <input type="radio" name="unit_affiliation_mode" value="real" <?= $unitAffiliationMode === 'real' ? 'checked' : '' ?>>
                                    <span>Unité réelle</span>
                                </label>
                                <label>
                                    <input type="radio" name="unit_affiliation_mode" value="fictional" <?= $unitAffiliationMode === 'fictional' ? 'checked' : '' ?>>
                                    <span>Unité fictive</span>
                                </label>
                            </div>
                            <?php if ($unitAffiliationMode === ''): ?>
                                <p class="bcs-help">Rien n’est choisi pour l’instant : aucune unité n’est mentionnée sur votre page publique.</p>
                            <?php endif; ?>

                            <div id="bo-unit-affiliation-fictional" class="bo-unit-affiliation-panel<?= $unitAffiliationMode === 'fictional' ? '' : ' is-hidden' ?>"<?= $unitAffiliationMode === 'fictional' ? '' : ' hidden' ?>>
                                <label class="bcs-label" for="unit_affiliation_fictional_label">Nom de l’unité fictive</label>
                                <input
                                    type="text"
                                    name="unit_affiliation_fictional_label"
                                    id="unit_affiliation_fictional_label"
                                    maxlength="200"
                                    value="<?= $h($unitAffiliationFictionalLabel) ?>"
                                    placeholder="Ex. Task Force Phoenix"
                                    <?= $unitAffiliationMode === 'fictional' ? '' : 'disabled' ?>
                                >
                                <p class="bcs-example">Exemples : Task Force Phoenix, 1er régiment fictif, Groupement Alpha…</p>
                            </div>

                            <div id="bo-unit-affiliation-real" class="bo-unit-affiliation-panel<?= $unitAffiliationMode === 'real' ? '' : ' is-hidden' ?>"<?= $unitAffiliationMode === 'real' ? '' : ' hidden' ?>>
                                <label class="bcs-label" for="unit_affiliation_country">Pays de rattachement</label>
                                <select name="unit_affiliation_country" id="unit_affiliation_country" class="bcs-input--short" <?= $unitAffiliationMode === 'real' ? '' : 'disabled' ?>>
                                    <option value="">Choisir un pays</option>
                                    <?php foreach ($unitCountryLabels as $countryCode => $countryLabel): ?>
                                        <option value="<?= $h($countryCode) ?>" <?= $unitAffiliationCountry === $countryCode ? 'selected' : '' ?>><?= $h($countryLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="bcs-label" for="unit_affiliation_search">Rechercher une unité</label>
                                <p class="bcs-help">Cochez une ou plusieurs entités. La recherche comprend aussi les surnoms et sigles (ex. Hubert, 1RPIMA, USASOC).</p>
                                <input type="search" id="unit_affiliation_search" placeholder="Rechercher une unité, un régiment, un commando…" <?= $unitAffiliationMode === 'real' ? '' : 'disabled' ?>>
                                <div id="unit_affiliation_units" class="bo-unit-affiliation-units" role="group" aria-label="Unités disponibles"></div>
                                <p class="bcs-help" id="unit_affiliation_summary">Aucune unité sélectionnée.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bcs-card bcs-lang" id="pays-langues">
                <span id="timezone"></span>
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Pays et langues</h3>
                        <p class="bcs-card__desc">Le pays de votre unité, la langue de travail et les langues parlées par vos joueurs. Ces réglages servent à l’ORBAT, aux grades proposés en premier, à l’annuaire des unités et à la fiche de chaque membre.</p>
                        <?php $renderWhere(['ORBAT', 'Effectifs', 'Registre des unités'], 'bcs-card__where'); ?>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="unit_country">Pays de l’unité</label>
                            <p class="bcs-help">Le pays que votre unité représente en jeu.<?php if ($langCountryFromAffiliation): ?> Repris de l’unité réelle représentée tant que vous n’en choisissez pas un autre.<?php endif; ?></p>
                            <select id="unit_country" name="unit_country">
                                <option value="">Non précisé</option>
                                <?php foreach ($langCountryOptions as $countryCode => $countryLabel): ?>
                                    <option value="<?= $h($countryCode) ?>" <?= $langUnitCountry === $countryCode ? 'selected' : '' ?>><?= $h(trim(\App\Support\Profile\PublicFlagCountryCatalog::flagEmoji($countryCode) . ' ' . $countryLabel)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="bcs-example">Grades proposés en premier : <strong><?= $h($langGradeCountry === 'US' ? 'États-Unis' : 'France') ?></strong>. Le catalogue de grades couvre la France et les États-Unis.</p>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="default_locale">Langue de référence</label>
                            <p class="bcs-help">La langue de travail de l’unité : briefings, ORBAT, annuaire des unités. L’interface du site reste en français ou en anglais, au choix de chaque membre.</p>
                            <select id="default_locale" name="default_locale" data-lang-default>
                                <?php foreach (\App\Support\CommunityLanguages::options() as $langCode => $langLabel): ?>
                                    <option value="<?= $h($langCode) ?>" <?= $locale === $langCode ? 'selected' : '' ?>><?= $h(\App\Support\CommunityLanguages::flag($langCode) . ' ' . $langLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="bcs-field">
                        <span class="bcs-label" id="community_languages_label">Langues parlées dans l’unité</span>
                        <p class="bcs-help">Vos joueurs choisissent leur langue de jeu dans cette liste. La langue de référence en fait toujours partie.</p>
                        <div class="bcs-checks" role="group" aria-labelledby="community_languages_label">
                            <?php foreach (\App\Support\CommunityLanguages::options() as $langCode => $langLabel): ?>
                                <?php $langIsDefault = $langCode === $locale; ?>
                                <label class="bcs-check">
                                    <input type="checkbox" name="community_languages[]" value="<?= $h($langCode) ?>" data-lang-spoken <?= in_array($langCode, $langSpoken, true) ? 'checked' : '' ?><?= $langIsDefault ? ' disabled' : '' ?>>
                                    <span><?= $h(\App\Support\CommunityLanguages::flag($langCode) . ' ' . $langLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="orbat_language">Langue de l’ORBAT</label>
                            <p class="bcs-help">Langue des intitulés de la feuille ORBAT imprimable et du PDF. Les noms d’unités et de postes restent tels que vous les avez saisis.</p>
                            <select id="orbat_language" name="orbat_language">
                                <option value="" <?= $langOrbat === '' ? 'selected' : '' ?> data-lang-follow>Comme la langue de référence (<?= $h(\App\Support\CommunityLanguages::label($locale)) ?>)</option>
                                <?php foreach (\App\Support\CommunityLanguages::options() as $langCode => $langLabel): ?>
                                    <option value="<?= $h($langCode) ?>" <?= $langOrbat === $langCode ? 'selected' : '' ?>><?= $h(\App\Support\CommunityLanguages::flag($langCode) . ' ' . $langLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="bcs-example"><a class="bcs-link" href="<?= $h(url('orbat/export')) ?>" target="_blank" rel="noopener">Voir la feuille ORBAT ↗</a> Chaque lecteur peut aussi changer de langue en haut de la feuille.</p>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="timezone_select">Fuseau horaire</label>
                            <p class="bcs-help">Sert à afficher les heures des événements, échéances et journaux.</p>
                            <select id="timezone_select" name="timezone">
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= $h($z) ?>" <?= $z === $currentTz ? 'selected' : '' ?>><?= $h($z) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="languages_form" value="1">
                </div>
            </section>
            </div>

            <div data-settings-panel="vitrine"<?= $hubTab === 'vitrine' ? '' : ' hidden' ?>>
            <header class="bcs-intro">
                <p class="bcs-intro__kicker">Rubrique 2 · Vitrine</p>
                <h2 class="bcs-intro__title">Ce que voient les visiteurs non connectés</h2>
                <p class="bcs-intro__text">Les textes principaux de votre <strong>page publique</strong>, votre présence dans le <strong>registre des unités</strong> et les blocs affichés. Pour la mise en page complète (photos, étapes de recrutement, FAQ, témoignages…), ouvrez la <a class="bcs-link" href="<?= $h(url('back-office/community/presentation')) ?>">page d’accueil publique</a>.</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Page publique', 'Registre des unités']); ?>
                    <?php if ($publicPageUrl !== ''): ?>
                        <a class="bcs-link" href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir le résultat ↗</a>
                    <?php endif; ?>
                </div>
            </header>

            <section class="bcs-card" id="textes-publics">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Textes de la page publique</h3>
                        <p class="bcs-card__desc">Deux niveaux : une accroche courte en haut de page, puis une présentation détaillée plus bas.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="public_hero_subtitle">Bio courte du bandeau</label>
                        <p class="bcs-help">2 ou 3 lignes sous votre nom, en haut de la page. C’est souvent la seule chose lue : dites qui vous êtes, à quoi vous jouez et quand.</p>
                        <textarea id="public_hero_subtitle" name="public_hero_subtitle" rows="3" maxlength="600" placeholder="Ex. Unité MILSIM francophone sur Arma 3. Opérations coopératives le samedi soir, ambiance sérieuse mais détendue."><?= $h($publicHeroSubtitle) ?></textarea>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="public_about_title">Titre de la présentation</label>
                        <p class="bcs-help">Le titre de la section « Qui sommes-nous » plus bas sur la page.</p>
                        <input type="text" id="public_about_title" name="public_about_title" maxlength="160" value="<?= $h($publicAboutTitle) ?>" placeholder="Qui sommes-nous ?">
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="public_about_body">Texte de la présentation</label>
                        <p class="bcs-help">Votre histoire, votre cadre de jeu, votre façon d’accueillir les nouveaux. Quelques paragraphes suffisent.</p>
                        <textarea id="public_about_body" name="public_about_body" rows="6" maxlength="8000" placeholder="Fondée en 2021, notre unité…"><?= $h($publicAboutBody) ?></textarea>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="visibilite">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Visibilité</h3>
                        <p class="bcs-card__desc">Qui peut vous trouver et ce qu’un visiteur peut consulter sans être membre.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <?php $renderToggle(
                        'Apparaître dans le registre des unités',
                        'Le registre est l’annuaire public des communautés hébergées sur Athena.',
                        'registry_listed',
                        $registryListed,
                        'votre carte est visible dans l’annuaire et mène à votre page publique.',
                        'vous n’apparaissez pas dans l’annuaire. Votre page publique reste accessible à qui a le lien.'
                    ); ?>
                    <?php $renderToggle(
                        'Forum réservé aux membres',
                        'Concerne le bouton « Accéder au forum » de votre page publique.',
                        'forum_members_only',
                        $forumMembersOnly,
                        'les visiteurs non connectés ne voient pas le bouton du forum.',
                        'tout visiteur peut ouvrir et lire le forum depuis votre page publique.'
                    ); ?>
                    <div class="bcs-field">
                        <span class="bcs-label">Page publique</span>
                        <p class="bcs-help">
                            <?php if ($slugHint !== ''): ?>
                                <span class="bcs-pill bcs-pill--ok">En ligne</span>
                                Consultable sans connexion<?php if ($publicPageUrl !== ''): ?> à l’adresse <a class="bcs-link" href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener"><?= $h(preg_replace('#^https?://#i', '', $publicPageUrl) ?? $publicPageUrl) ?></a><?php endif; ?>.
                            <?php else: ?>
                                <span class="bcs-pill bcs-pill--warn">Indisponible</span>
                                Renseignez l’adresse de la page publique dans la rubrique Identité pour la mettre en ligne.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="orbat_visibility">Qui peut voir l’organigramme ?</label>
                        <p class="bcs-help">L’organigramme montre la structure de l’unité (sections, groupes, postes). Le rendre public rassure les candidats ; le réserver protège vos effectifs.</p>
                        <select id="orbat_visibility" name="orbat_visibility" class="bcs-input--short">
                            <option value="public" <?= $orbatVis === 'public' ? 'selected' : '' ?>>Tous les visiteurs</option>
                            <option value="members" <?= $orbatVis === 'members' ? 'selected' : '' ?>>Les membres uniquement</option>
                            <option value="command" <?= $orbatVis === 'command' ? 'selected' : '' ?>>Le commandement uniquement</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="bcs-card" id="modules-vitrine">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Blocs affichés sur la page publique</h3>
                        <p class="bcs-card__desc">Activez uniquement ce qui donne envie de vous rejoindre. Un bloc désactivé reste disponible pour vos membres dans le portail.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <?php
                    $moduleHelp = [
                        'forum' => ['Forum', 'Les derniers sujets publics du forum.'],
                        'documents' => ['Documents', 'Les documents marqués comme publics (règlement, doctrine…).'],
                        'events' => ['Événements', 'Vos prochaines opérations et entraînements ouverts.'],
                        'roster' => ['Effectifs', 'Le nombre de membres et la composition de l’unité.'],
                        'training' => ['Formations', 'Le catalogue des formations proposées.'],
                        'analytics' => ['Statistiques', 'Quelques chiffres d’activité (opérations jouées, présence).'],
                    ];
                    foreach ($moduleHelp as $mk => [$ml, $mh]):
                        $renderToggle(
                            $ml,
                            $mh,
                            'public_mod_' . $mk,
                            !empty($pm[$mk]),
                            '',
                            ''
                        );
                    endforeach;
                    ?>
                </div>
            </section>

            </div>

            <div data-settings-panel="portail"<?= $hubTab === 'portail' ? '' : ' hidden' ?>>
            <header class="bcs-intro">
                <p class="bcs-intro__kicker">Rubrique 5 · Portail</p>
                <h2 class="bcs-intro__title">L’apparence de l’espace membres</h2>
                <p class="bcs-intro__text">Couleurs, images et menus que voient vos membres une fois connectés, ainsi que le relais des annonces vers Discord. Rien ici n’est visible des visiteurs.</p>
                <div class="bcs-intro__foot">
                    <?php $renderWhere(['Portail membres', 'Discord']); ?>
                </div>
            </header>

            <section class="bcs-card" id="navigation">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Menus Opérations et Ressources</h3>
                        <p class="bcs-card__desc">Ces deux menus du portail s’ouvrent en panneau latéral. Choisissez leur présentation, leur couleur et leur image de fond.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <?php foreach ([
                        'operations' => ['label' => 'Menu Opérations', 'style' => $navOpsStyle, 'accent' => $navOpsAccent, 'imageOn' => $navOpsImageEnabled, 'url' => $navOpsUrl, 'file' => 'nav_operations', 'remove' => 'remove_nav_operations'],
                        'resources' => ['label' => 'Menu Ressources', 'style' => $navResStyle, 'accent' => $navResAccent, 'imageOn' => $navResImageEnabled, 'url' => $navResUrl, 'file' => 'nav_resources', 'remove' => 'remove_nav_resources'],
                    ] as $slot => $cfg): ?>
                    <div class="bcs-sub">
                        <p class="bcs-sub__title"><?= $h($cfg['label']) ?></p>
                        <div class="bcs-grid" style="border-top:0;">
                            <div class="bcs-field">
                                <label class="bcs-label" for="nav_<?= $h($slot) ?>_submenu_style">Présentation des liens</label>
                                <select id="nav_<?= $h($slot) ?>_submenu_style" name="nav_<?= $h($slot) ?>_submenu_style">
                                    <?php foreach ($navStyleLabels as $val => $lbl): ?>
                                        <option value="<?= $h($val) ?>" <?= $cfg['style'] === $val ? 'selected' : '' ?>><?= $h($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="bcs-field">
                                <label class="bcs-label" for="nav_<?= $h($slot) ?>_accent">Couleur d’accent</label>
                                <select id="nav_<?= $h($slot) ?>_accent" name="nav_<?= $h($slot) ?>_accent">
                                    <?php foreach ($navAccentLabels as $val => $lbl): ?>
                                        <option value="<?= $h($val) ?>" <?= $cfg['accent'] === $val ? 'selected' : '' ?>><?= $h($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php $renderToggle(
                            'Afficher une image dans le panneau',
                            '',
                            'nav_' . $slot . '_image_enabled',
                            $cfg['imageOn'],
                            'une illustration orne le haut du panneau.',
                            'le panneau n’affiche que les liens.'
                        ); ?>
                        <div class="bcs-media">
                            <?php $renderThumb($cfg['url'], 'Image par défaut'); ?>
                            <div class="bcs-media__copy">
                                <span class="bcs-label">Image personnalisée <span class="bcs-opt">(facultatif)</span></span>
                                <input type="file" name="<?= $h($cfg['file']) ?>" class="bcs-file" aria-label="Image du <?= $h(mb_strtolower($cfg['label'])) ?>" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $imagesNeedAddress ? 'disabled' : '' ?>>
                                <?php if ($cfg['url'] !== ''): ?>
                                <label class="bcs-check">
                                    <input type="hidden" name="<?= $h($cfg['remove']) ?>" value="0">
                                    <input type="checkbox" name="<?= $h($cfg['remove']) ?>" value="1">
                                    Revenir à l’image par défaut
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bcs-card" id="marque">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Marque et relais</h3>
                        <p class="bcs-card__desc">Images complémentaires, couleurs du portail et envoi automatique des annonces vers Discord.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-media">
                        <?php $renderThumb($bannerUrl, 'Pas de bannière'); ?>
                        <div class="bcs-media__copy">
                            <span class="bcs-label">Bannière</span>
                            <p class="bcs-help">Bandeau large utilisé pour les mises en avant. 12 Mo max.</p>
                            <input type="file" name="org_banner" class="bcs-file" aria-label="Choisir une bannière" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $imagesNeedAddress ? 'disabled' : '' ?>>
                            <?php if ($bannerUrl !== ''): ?>
                            <label class="bcs-check"><input type="hidden" name="remove_org_banner" value="0"><input type="checkbox" name="remove_org_banner" value="1"> Retirer la bannière</label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bcs-media">
                        <?php $renderThumb($faviconUrl, 'Icône Athena', true); ?>
                        <div class="bcs-media__copy">
                            <span class="bcs-label">Icône de l’onglet du navigateur</span>
                            <p class="bcs-help">Petite image carrée affichée dans l’onglet et les favoris. Un logo simple rend mieux.</p>
                            <input type="file" name="org_favicon" class="bcs-file" aria-label="Choisir une icône" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $imagesNeedAddress ? 'disabled' : '' ?>>
                            <?php if ($faviconUrl !== ''): ?>
                            <label class="bcs-check"><input type="hidden" name="remove_org_favicon" value="0"><input type="checkbox" name="remove_org_favicon" value="1"> Retirer l’icône</label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bcs-field">
                        <span class="bcs-label">Couleurs de marque</span>
                        <p class="bcs-help">Couleur principale (boutons, liens) et couleur d’accent du portail.</p>
                        <div class="bcs-inline" style="gap:18px;flex-wrap:wrap;">
                            <label class="bcs-inline"><input type="color" name="primary_color" value="<?= $h($primaryColor) ?>"> Principale</label>
                            <label class="bcs-inline"><input type="color" name="accent_color" value="<?= $h($accentColor) ?>"> Accent</label>
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="discord_webhook_url">Relais Discord des annonces <span class="bcs-opt">(facultatif)</span></label>
                        <p class="bcs-help">Collez l’adresse d’un webhook Discord : les annonces du portail et les mises à jour du pack y seront publiées automatiquement. Les salons séparés et les photos se règlent dans <a class="bcs-link" href="<?= $h(url('back-office/integrations')) ?>">Intégrations</a>.</p>
                        <input type="text" inputmode="url" autocomplete="off" spellcheck="false" id="discord_webhook_url" name="discord_webhook_url" maxlength="500" value="<?= $h((string) ($i['discord_webhook_url'] ?? '')) ?>" placeholder="https://discord.com/api/webhooks/…">
                    </div>
                </div>
            </section>
            </div>

            <div data-settings-panel="profil"<?= $hubTab === 'profil' ? '' : ' hidden' ?>>
            <section class="bcs-card" id="cycle-effectif">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Cycle administratif des recrues</h3>
                        <p class="bcs-card__desc">Ces durées indiquent où en est l’enrôlement d’un membre. Elles ne remplacent ni le rôle obligatoire Opérateur, ni les fonctions attribuées.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="personnel_training_days">Durée minimale « En formation »</label>
                            <p class="bcs-help">Nombre de jours avant de pouvoir passer en service actif.</p>
                            <span class="bcs-inline"><input type="number" id="personnel_training_days" name="personnel_training_days" min="0" max="3650" value="<?= (int) ($lifecycle['training_days'] ?? 14) ?>"> jours</span>
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="personnel_active_service_days">Ancienneté de service requise</label>
                            <p class="bcs-help">Délai supplémentaire après la formation (0 = aucun).</p>
                            <span class="bcs-inline"><input type="number" id="personnel_active_service_days" name="personnel_active_service_days" min="0" max="3650" value="<?= (int) ($lifecycle['active_service_days'] ?? 0) ?>"> jours</span>
                        </div>
                    </div>
                </div>
            </section>
            </div>
    </form>

    <div data-settings-panel="inscription"<?= $hubTab === 'inscription' ? '' : ' hidden' ?> id="inscription">
        <header class="bcs-intro">
            <p class="bcs-intro__kicker">Rubrique 3 · Inscription</p>
            <h2 class="bcs-intro__title">Comment on vous rejoint</h2>
            <p class="bcs-intro__text">Le parcours proposé aux candidats depuis votre page publique : type de formulaire, contact, et questions posées. Le bouton « Candidater » de la page publique mène à ce formulaire.</p>
            <div class="bcs-intro__foot">
                <?php $renderWhere(['Page publique', 'Formulaire de candidature']); ?>
                <?php if ($publicPageUrl !== ''): ?>
                    <a class="bcs-link" href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir le résultat ↗</a>
                <?php endif; ?>
            </div>
        </header>
        <?php
        $settingsHubEmbedInscription = true;
        include base_path('views/admin/organization/inscription_settings.php');
        ?>
    </div>

    <div class="bo-settings-hub-save" data-settings-save>
        <div class="bcs-savebar__copy">
            <p class="bcs-savebar__title" data-bcs-save-title>Enregistrer les modifications</p>
            <p class="bcs-savebar__hint" data-bcs-save-hint><?= $h($hubSaveHint) ?></p>
        </div>
        <button type="button" class="bcs-btn bcs-btn--primary" data-settings-submit="<?= $h($hubSubmitForm) ?>"<?= $hubTab === 'accueil' ? ' hidden' : '' ?>>Enregistrer</button>
    </div>

    <p class="bcs-footer-links">
        Aller plus loin :
        <a class="bcs-link" href="<?= $h(url('back-office/community/presentation')) ?>">Page d’accueil publique</a>
        · <a class="bcs-link" href="<?= $h(url('back-office/media')) ?>">Médias</a>
        · <a class="bcs-link" href="<?= $h(url('back-office/integrations')) ?>">Intégrations</a>
        · <a class="bcs-link" href="<?= $h(url('back-office/configuration-initiale')) ?>">Assistant de démarrage</a>
    </p>
    </div>
</div>
</div>
<script>
(function () {
    var unitCatalog = <?= json_encode($unitCatalogPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var selectedIds = <?= json_encode($unitAffiliationUnitIds, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var countrySelect = document.getElementById('unit_affiliation_country');
    var fictionalWrap = document.getElementById('bo-unit-affiliation-fictional');
    var realWrap = document.getElementById('bo-unit-affiliation-real');
    var unitsWrap = document.getElementById('unit_affiliation_units');
    var searchInput = document.getElementById('unit_affiliation_search');
    var summary = document.getElementById('unit_affiliation_summary');
    var fictionalInput = document.getElementById('unit_affiliation_fictional_label');

    function currentAffiliationMode() {
        var checked = document.querySelector('input[name="unit_affiliation_mode"]:checked');
        return checked ? checked.value : '';
    }

    function setPanelVisible(el, visible) {
        if (!el) return;
        el.classList.toggle('is-hidden', !visible);
        el.classList.toggle('hidden', !visible);
        if (visible) {
            el.removeAttribute('hidden');
        } else {
            el.setAttribute('hidden', 'hidden');
        }
    }

    function setDisabled(el, disabled) {
        if (!el) return;
        el.disabled = !!disabled;
    }

    function syncAffiliationPanels() {
        var mode = currentAffiliationMode();
        var isFictional = mode === 'fictional';
        var isReal = mode === 'real';
        setPanelVisible(fictionalWrap, isFictional);
        setPanelVisible(realWrap, isReal);
        setDisabled(fictionalInput, !isFictional);
        setDisabled(countrySelect, !isReal);
        setDisabled(searchInput, !isReal);
        if (unitsWrap) {
            unitsWrap.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                setDisabled(cb, !isReal);
            });
        }
        renderUnitOptions();
    }

    function tierLabel(tier) {
        var map = {
            command: 'Commandement',
            component: 'Composante',
            unit: 'Unité',
            subunit: 'Sous-unité'
        };
        return map[tier] || '';
    }

    function renderUnitOptions() {
        if (!unitsWrap || !countrySelect) return;
        unitsWrap.innerHTML = '';
        var mode = currentAffiliationMode();
        if (mode !== 'real') {
            if (summary) summary.textContent = 'Aucune unité sélectionnée.';
            return;
        }
        var country = countrySelect.value || '';
        var rows = (unitCatalog.units && unitCatalog.units[country]) ? unitCatalog.units[country] : [];
        var query = searchInput ? String(searchInput.value || '').toLowerCase().trim() : '';
        var names = [];
        var lastTier = '';
        rows.forEach(function (row) {
            var name = String(row.name || '');
            var hay = name.toLowerCase();
            if (row.short_name) hay += ' ' + String(row.short_name).toLowerCase();
            if (row.id) hay += ' ' + String(row.id).toLowerCase();
            if (Array.isArray(row.aliases)) {
                row.aliases.forEach(function (a) { hay += ' ' + String(a).toLowerCase(); });
            }
            var compact = hay.replace(/\s+/g, '');
            var qCompact = query.replace(/\s+/g, '');
            if (query && hay.indexOf(query) === -1 && compact.indexOf(qCompact) === -1) return;
            if (row.tier && row.tier !== lastTier) {
                lastTier = row.tier;
                var heading = document.createElement('p');
                heading.className = 'bo-unit-affiliation-tier';
                heading.textContent = tierLabel(row.tier);
                unitsWrap.appendChild(heading);
            }
            var label = document.createElement('label');
            label.className = 'bo-unit-affiliation-item';
            label.style.paddingLeft = (10 + ((parseInt(row.indent || 0, 10) || 0) * 18)) + 'px';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'unit_affiliation_unit_ids[]';
            cb.value = row.id;
            cb.checked = selectedIds.indexOf(row.id) !== -1;
            cb.addEventListener('change', function () {
                if (cb.checked) {
                    if (selectedIds.indexOf(row.id) === -1) selectedIds.push(row.id);
                } else {
                    selectedIds = selectedIds.filter(function (id) { return id !== row.id; });
                }
                renderUnitOptions();
            });
            var text = document.createElement('span');
            text.textContent = name;
            label.appendChild(cb);
            label.appendChild(text);
            unitsWrap.appendChild(label);
            if (cb.checked) names.push(name);
        });
        if (!unitsWrap.children.length) {
            var empty = document.createElement('div');
            empty.className = 'bo-settings-note';
            empty.textContent = query ? 'Aucune unité ne correspond à votre recherche.' : 'Choisissez un pays pour afficher les unités disponibles.';
            unitsWrap.appendChild(empty);
        }
        if (summary) {
            summary.textContent = names.length > 0
                ? names.length + ' unité(s) sélectionnée(s) : ' + names.join(', ')
                : 'Aucune unité sélectionnée.';
        }
    }

    document.querySelectorAll('input[name="unit_affiliation_mode"]').forEach(function (input) {
        input.addEventListener('change', syncAffiliationPanels);
    });
    if (countrySelect) {
        countrySelect.addEventListener('change', function () {
            selectedIds = [];
            renderUnitOptions();
        });
    }
    if (searchInput) {
        searchInput.addEventListener('input', renderUnitOptions);
    }
    syncAffiliationPanels();


    /* Barre d’enregistrement : texte selon la rubrique + modifications en attente */
    var hub = document.querySelector('[data-settings-hub]');
    var saveHints = <?= json_encode($hubSaveHints, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var saveBar = hub ? hub.querySelector('[data-settings-save]') : null;
    var saveHint = hub ? hub.querySelector('[data-bcs-save-hint]') : null;
    var saveTitle = hub ? hub.querySelector('[data-bcs-save-title]') : null;
    var dirtyForms = {};
    function refreshSaveBar() {
        if (!hub) return;
        var tab = hub.getAttribute('data-active-tab') || 'identite';
        if (saveHint) saveHint.textContent = saveHints[tab] || '';
        var formId = tab === 'inscription' ? 'bo-inscription-settings-form' : 'bo-community-settings-form';
        var dirty = !!dirtyForms[formId];
        if (saveBar) saveBar.classList.toggle('is-dirty', dirty);
        if (saveTitle) saveTitle.textContent = dirty ? 'Modifications non enregistrées' : 'Enregistrer les modifications';
    }
    ['bo-community-settings-form', 'bo-inscription-settings-form'].forEach(function (id) {
        var f = document.getElementById(id);
        if (!f) return;
        var mark = function () { dirtyForms[id] = true; refreshSaveBar(); };
        f.addEventListener('input', mark);
        f.addEventListener('change', mark);
    });
    if (hub && typeof MutationObserver === 'function') {
        new MutationObserver(refreshSaveBar).observe(hub, { attributes: true, attributeFilter: ['data-active-tab'] });
    }
    refreshSaveBar();

    if (window.location.hash === '#affiliation') {
        var affEl = document.getElementById('affiliation');
        if (affEl) {
            setTimeout(function () {
                affEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 80);
        }
    }
})();
</script>
<script>
(function () {
    // La langue de référence fait toujours partie des langues parlées, et l’ORBAT la suit par défaut.
    var select = document.querySelector('[data-lang-default]');
    if (!select) { return; }
    var labels = <?= json_encode(array_map([\App\Support\CommunityLanguages::class, 'label'], array_combine(\App\Support\CommunityLanguages::codes(), \App\Support\CommunityLanguages::codes())), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    var sync = function () {
        document.querySelectorAll('[data-lang-spoken]').forEach(function (box) {
            var isDefault = box.value === select.value;
            if (isDefault) { box.checked = true; }
            box.disabled = isDefault;
        });
        var follow = document.querySelector('[data-lang-follow]');
        if (follow) { follow.textContent = 'Comme la langue de référence (' + (labels[select.value] || select.value) + ')'; }
    };
    select.addEventListener('change', sync);
    sync();
})();
</script>
<script src="<?= $h(asset_url('assets/js/community-settings-hub.js')) ?>"></script>
