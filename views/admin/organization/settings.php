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

$c = $community ?? [];
$i = $integrations ?? [];
$b = $branding ?? [];
$settingsRoot = is_array($orgSettings ?? null) ? $orgSettings : [];
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
$locale = strtolower((string) ($c['default_locale'] ?? 'fr'));
if ($locale === 'fr-fr') {
    $locale = 'fr';
}
if ($locale === 'en-us') {
    $locale = 'en';
}
if (!in_array($locale, ['fr', 'en'], true)) {
    $locale = 'fr';
}
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

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
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

$renderToggle = static function (
    string $label,
    string $help,
    string $name,
    bool $checked,
    string $valueOn = 'Actif',
    string $valueOff = 'Inactif',
) use ($h): void {
    ?>
    <div class="bo-setting-row">
        <div class="bo-setting-row__copy">
            <div class="bo-setting-row__label"><?= $h($label) ?></div>
            <div class="bo-setting-row__help"><?= $h($help) ?></div>
        </div>
        <div class="bo-setting-row__control">
            <span class="bo-setting-row__value" data-bo-toggle-value="<?= $h($name) ?>"><?= $h($checked ? $valueOn : $valueOff) ?></span>
            <label class="ath-toggle <?= $checked ? 'is-on' : 'is-off' ?>">
                <input type="hidden" name="<?= $h($name) ?>" value="0">
                <input type="checkbox" class="ath-toggle__input" name="<?= $h($name) ?>" value="1" <?= $checked ? 'checked' : '' ?> data-bo-toggle="<?= $h($name) ?>" data-on="<?= $h($valueOn) ?>" data-off="<?= $h($valueOff) ?>">
                <span class="ath-toggle__knob" aria-hidden="true"></span>
            </label>
        </div>
    </div>
    <?php
};

$tenantTypeOptions = is_array($tenantTypeOptions ?? null) ? $tenantTypeOptions : \App\Services\Community\TenantTypeConfig::availableTypes();
$currentTenantType = \App\Services\Community\TenantTypeConfig::normalizeType(
    (string) ($currentTenantType ?? ($tenant['tenant_type'] ?? 'full'))
);
$tenantTypeFormAction = (string) ($tenantTypeFormAction ?? url('back-office/organisation/profil'));
$currentTypeLabel = \App\Services\Community\TenantTypeConfig::label($currentTenantType);
?>
<div class="bo-community-settings">

    <?php if ($err): ?>
        <div class="bo-settings-flash bo-settings-flash--err" role="alert"><?= $h((string) $err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="bo-settings-flash bo-settings-flash--ok" role="status"><?= $h((string) $ok) ?></div>
    <?php endif; ?>

    <?php if ($discordInviteMissing): ?>
        <div class="bo-settings-flash bo-settings-flash--warn" role="alert">
            Le recrutement via Discord est actif, mais aucun lien d’invitation n’est renseigné.
            Les candidats ne pourront pas ouvrir votre serveur depuis le formulaire public.
            <a href="<?= $h(url('back-office/community/inscription#coordonnees')) ?>">Renseigner le lien Discord</a>
        </div>
    <?php endif; ?>
    <div class="bo-settings-flash bo-settings-flash--warn" role="status">
        De nouveaux réglages sont disponibles ici&nbsp;: représentation de la communauté (unité réelle ou fictive),
        bio du bandeau et texte «&nbsp;Qui sommes-nous&nbsp;?&nbsp;».
        Les options d’inscription (créneaux, motivation, mode de candidature) ont leur propre page.
        <a href="#representation-unite">Représentation</a>
        · <a href="#textes-publics">Textes publics</a>
        · <a href="<?= $h(url('back-office/community/inscription')) ?>">Inscription</a>
        · <a href="<?= $h(url('back-office/community/presentation')) ?>">Vitrine complète</a>
    </div>

    <form method="post" enctype="multipart/form-data" action="<?= $h($formAction) ?>" id="bo-community-settings-form">
        <?= \App\Core\Csrf::field() ?>

        <div class="bo-settings-grid">

            <section class="ath-card ath-rise bo-setting-group" id="identite">
                <p class="bo-setting-group__kicker">Identité</p>
                <h2 class="bo-setting-group__title">Vitrine et portail</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Nom affiché</div>
                            <div class="bo-setting-row__help">Titre visible sur la page publique et le registre.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="text" id="tenant_name" name="tenant_name" class="bo-setting-row__field--wide" maxlength="255" required value="<?= $h((string) ($tenant['name'] ?? '')) ?>" placeholder="Ex. 92e RI">
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Adresse courte de la page publique</div>
                            <div class="bo-setting-row__help">Lettres minuscules, chiffres et tirets. Mettez à jour les liens déjà partagés si vous la changez.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="text" id="tenant_slug" name="tenant_slug" class="bo-setting-row__field--wide" maxlength="50" required pattern="[a-z0-9]([-a-z0-9]*[a-z0-9])?" value="<?= $h($slugHint) ?>" placeholder="mon-unite">
                        </div>
                    </div>
                    <div class="bo-setting-row">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Logo</div>
                            <div class="bo-setting-row__help">JPG, PNG ou WebP · 12 Mo maximum.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <?php if ($logoUrl !== ''): ?>
                                <img src="<?= $h($logoUrl) ?>" alt="" class="bo-setting-thumb">
                            <?php endif; ?>
                            <span class="bo-setting-row__value"><?= $logoUrl !== '' ? 'Défini' : '—' ?></span>
                            <input type="file" name="org_logo" class="bo-setting-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $slugHint === '' ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <?php if ($logoUrl !== ''): ?>
                    <label class="bo-setting-remove">
                        <input type="hidden" name="remove_org_logo" value="0">
                        <input type="checkbox" name="remove_org_logo" value="1">
                        Retirer le logo
                    </label>
                    <?php endif; ?>
                    <div class="bo-setting-row">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Image d’en-tête du registre</div>
                            <div class="bo-setting-row__help">Bandeau paysage sur la carte du registre des unités.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <?php if ($coverUrl !== ''): ?>
                                <img src="<?= $h($coverUrl) ?>" alt="" class="bo-setting-thumb bo-setting-thumb--wide">
                            <?php endif; ?>
                            <span class="bo-setting-row__value"><?= $coverUrl !== '' ? 'Définie' : '—' ?></span>
                            <input type="file" name="registry_cover" class="bo-setting-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $slugHint === '' ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <?php if ($coverUrl !== ''): ?>
                    <label class="bo-setting-remove">
                        <input type="hidden" name="remove_registry_cover" value="0">
                        <input type="checkbox" name="remove_registry_cover" value="1">
                        Retirer l’image du registre
                    </label>
                    <?php endif; ?>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Message d’accueil <span style="font-weight:600;color:var(--ath-subtle)">(facultatif)</span></div>
                            <div class="bo-setting-row__help">Texte court de bienvenue (portail / fiche). Distinct de la bio du bandeau public dans « Textes publics ».</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <textarea id="welcome_text" name="welcome_text" rows="3" maxlength="500" class="bo-setting-row__field--wide" placeholder="Présentez votre unité en quelques phrases…"><?= $h((string) ($c['welcome_text'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Code pour rejoindre <span style="font-weight:600;color:var(--ath-subtle)">(facultatif)</span></div>
                            <div class="bo-setting-row__help">Affiché sur la page « Rejoindre » pour faciliter l’arrivée des membres.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="text" id="community_code" name="community_code" class="bo-setting-row__field" maxlength="64" value="<?= $h((string) ($tenant['community_code'] ?? '')) ?>" placeholder="MON-UNIT">
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Jeu ou plateforme <span style="font-weight:600;color:var(--ath-subtle)">(facultatif)</span></div>
                            <div class="bo-setting-row__help">Affiché sur la fiche publique et dans le registre.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="text" id="game_label" name="game_label" class="bo-setting-row__field" maxlength="120" value="<?= $h((string) ($c['game_label'] ?? '')) ?>" placeholder="Ex. Arma 3">
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack" id="affiliation">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Représentation de la communauté</div>
                            <div class="bo-setting-row__help">Choisissez une ou plusieurs entités du référentiel militaire (commandement, composante, régiment, commando…) ou indiquez un cadre fictif. La recherche fonctionne aussi sur les alias (ex. Hubert, 1RPIMA, USASOC).</div>
                        </div>
                        <div class="bo-setting-row__control" style="align-items:flex-start;max-width:100%;width:100%;">
                            <div class="bo-unit-affiliation-panel" style="width:min(100%,760px);">
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

                                <div id="bo-unit-affiliation-fictional" class="bo-unit-affiliation-panel<?= $unitAffiliationMode === 'fictional' ? '' : ' is-hidden' ?>"<?= $unitAffiliationMode === 'fictional' ? '' : ' hidden' ?>>
                                    <label class="bo-setting-row__label" for="unit_affiliation_fictional_label">Nom de l’unité fictive</label>
                                    <input
                                        type="text"
                                        name="unit_affiliation_fictional_label"
                                        id="unit_affiliation_fictional_label"
                                        class="bo-setting-row__field--wide"
                                        maxlength="200"
                                        value="<?= $h($unitAffiliationFictionalLabel) ?>"
                                        placeholder="Ex. Task Force Phoenix"
                                        <?= $unitAffiliationMode === 'fictional' ? '' : 'disabled' ?>
                                    >
                                    <p class="bo-settings-note" style="margin-top:0;">Exemple&nbsp;: Task Force Phoenix, 1er régiment fictif…</p>
                                </div>

                                <div id="bo-unit-affiliation-real" class="bo-unit-affiliation-panel<?= $unitAffiliationMode === 'real' ? '' : ' is-hidden' ?>"<?= $unitAffiliationMode === 'real' ? '' : ' hidden' ?>>
                                    <label class="bo-setting-row__label" for="unit_affiliation_country">Pays de rattachement</label>
                                    <select name="unit_affiliation_country" id="unit_affiliation_country" class="bo-setting-row__field" <?= $unitAffiliationMode === 'real' ? '' : 'disabled' ?>>
                                        <option value="">Choisir un pays</option>
                                        <?php foreach ($unitCountryLabels as $countryCode => $countryLabel): ?>
                                            <option value="<?= $h($countryCode) ?>" <?= $unitAffiliationCountry === $countryCode ? 'selected' : '' ?>><?= $h($countryLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="bo-setting-row__label" for="unit_affiliation_search">Rechercher une unité</label>
                                    <input type="search" id="unit_affiliation_search" class="bo-setting-row__field--wide" placeholder="Rechercher une unité, un régiment, un commando…" <?= $unitAffiliationMode === 'real' ? '' : 'disabled' ?>>
                                    <div id="unit_affiliation_units" class="bo-unit-affiliation-units" role="group" aria-label="Unités disponibles"></div>
                                    <p class="bo-settings-note" id="unit_affiliation_summary" style="margin-top:0;">Aucune unité sélectionnée.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($publicPageUrl !== ''): ?>
                    <p class="bo-settings-note"><a href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir la page publique ↗</a></p>
                <?php endif; ?>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="textes-publics">
                <p class="bo-setting-group__kicker">Vitrine</p>
                <h2 class="bo-setting-group__title">Textes publics</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Bio courte du bandeau</div>
                            <div class="bo-setting-row__help">Quelques lignes sous le titre, dans le bandeau d’accueil de la page publique. Ce n’est pas le texte « Qui sommes-nous ».</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <textarea id="public_hero_subtitle" name="public_hero_subtitle" rows="3" maxlength="600" class="bo-setting-row__field--wide" placeholder="Quelques lignes pour situer votre communauté dès l’arrivée sur la page."><?= $h($publicHeroSubtitle) ?></textarea>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Titre « Qui sommes-nous »</div>
                            <div class="bo-setting-row__help">Intitulé de la section de présentation longue sur la vitrine.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="text" id="public_about_title" name="public_about_title" class="bo-setting-row__field--wide" maxlength="160" value="<?= $h($publicAboutTitle) ?>" placeholder="Qui sommes-nous ?">
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Texte « Qui sommes-nous »</div>
                            <div class="bo-setting-row__help">Présentation détaillée (histoire, cadre de jeu, accueil). Distinct de la bio du bandeau.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <textarea id="public_about_body" name="public_about_body" rows="5" maxlength="8000" class="bo-setting-row__field--wide" placeholder="Présentez votre histoire, votre cadre de jeu, votre manière d’accueillir…"><?= $h($publicAboutBody) ?></textarea>
                        </div>
                    </div>
                </div>
                <p class="bo-settings-note">Mise en page avancée, modules et médias : <a href="<?= $h(url('back-office/community/presentation')) ?>">Page d’accueil publique</a></p>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="visibilite">
                <p class="bo-setting-group__kicker">Visibilité</p>
                <h2 class="bo-setting-group__title">Registre des unités</h2>
                <div class="bo-setting-group__rows">
                    <?php $renderToggle(
                        'Apparaître dans le registre public',
                        'Rend la communauté visible dans la liste des unités.',
                        'registry_listed',
                        $registryListed,
                        'Public',
                        'Masquée'
                    ); ?>
                    <?php $renderToggle(
                        'Forum réservé aux membres',
                        'Masque « Accéder au forum » aux visiteurs non connectés.',
                        'forum_members_only',
                        $forumMembersOnly,
                        'Réservé',
                        'Ouvert'
                    ); ?>
                    <div class="bo-setting-row">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Page d’accueil publique</div>
                            <div class="bo-setting-row__help">Vitrine consultable sans connexion lorsque l’adresse courte est définie.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <span class="bo-setting-row__value"><?= $slugHint !== '' ? 'En ligne' : 'Indisponible' ?></span>
                        </div>
                    </div>
                    <div class="bo-setting-row">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Indexation moteurs</div>
                            <div class="bo-setting-row__help">Autoriser le référencement de la vitrine par les moteurs de recherche.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <span class="bo-setting-row__value">Bientôt</span>
                            <span class="ath-toggle is-off" aria-hidden="true" title="Réglage à venir"><span class="ath-toggle__knob"></span></span>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Visibilité de l’organigramme</div>
                            <div class="bo-setting-row__help">Qui peut consulter la structure de l’unité.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="orbat_visibility" name="orbat_visibility" class="bo-setting-row__field--wide">
                                <option value="public" <?= $orbatVis === 'public' ? 'selected' : '' ?>>Visible par tous les visiteurs</option>
                                <option value="members" <?= $orbatVis === 'members' ? 'selected' : '' ?>>Réservée aux membres</option>
                                <option value="command" <?= $orbatVis === 'command' ? 'selected' : '' ?>>Réservée au commandement</option>
                            </select>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack" id="timezone">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Fuseau horaire</div>
                            <div class="bo-setting-row__help">Base de tous les horodatages (événements, échéances, journaux).</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="timezone_select" name="timezone" class="bo-setting-row__field--wide">
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= $h($z) ?>" <?= $z === $currentTz ? 'selected' : '' ?>><?= $h($z) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Langue de référence</div>
                            <div class="bo-setting-row__help">Langue par défaut pour les textes du portail.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="default_locale" name="default_locale" class="bo-setting-row__field">
                                <option value="fr" <?= $locale === 'fr' ? 'selected' : '' ?>>Français</option>
                                <option value="en" <?= $locale === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                    </div>
                    <?php foreach (['forum' => 'Forum', 'documents' => 'Documents', 'events' => 'Événements', 'roster' => 'Effectifs', 'training' => 'Formations', 'analytics' => 'Statistiques'] as $mk => $ml): ?>
                        <?php $renderToggle(
                            'Module « ' . $ml . ' » sur la vitrine',
                            'Affiche ce module sur la page de présentation publique.',
                            'public_mod_' . $mk,
                            !empty($pm[$mk]),
                            'Visible',
                            'Masqué'
                        ); ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="navigation">
                <p class="bo-setting-group__kicker">Navigation</p>
                <h2 class="bo-setting-group__title">Portail</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Sous-menu Opérations</div>
                            <div class="bo-setting-row__help">Style d’affichage du sous-menu latéral.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select name="nav_operations_submenu_style" class="bo-setting-row__field">
                                <?php foreach ($navStyleLabels as $val => $lbl): ?>
                                    <option value="<?= $h($val) ?>" <?= $navOpsStyle === $val ? 'selected' : '' ?>><?= $h($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Couleur d’accent Opérations</div>
                            <div class="bo-setting-row__help">Teinte appliquée au panneau latéral Opérations.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select name="nav_operations_accent" class="bo-setting-row__field">
                                <?php foreach ($navAccentLabels as $val => $lbl): ?>
                                    <option value="<?= $h($val) ?>" <?= $navOpsAccent === $val ? 'selected' : '' ?>><?= $h($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php $renderToggle(
                        'Image du panneau Opérations',
                        'Illustration affichée dans le menu latéral Opérations.',
                        'nav_operations_image_enabled',
                        $navOpsImageEnabled,
                        'Affichée',
                        'Masquée'
                    ); ?>
                    <div class="bo-setting-row">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Visuel Opérations</div>
                            <div class="bo-setting-row__help">Image de fond du menu Opérations.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <?php if ($navOpsUrl !== ''): ?>
                                <img src="<?= $h($navOpsUrl) ?>" alt="" class="bo-setting-thumb bo-setting-thumb--wide">
                            <?php endif; ?>
                            <span class="bo-setting-row__value"><?= $navOpsUrl !== '' ? 'Définie' : '—' ?></span>
                            <input type="file" name="nav_operations" class="bo-setting-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $slugHint === '' ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <?php if ($navOpsUrl !== ''): ?>
                    <label class="bo-setting-remove">
                        <input type="hidden" name="remove_nav_operations" value="0">
                        <input type="checkbox" name="remove_nav_operations" value="1">
                        Retirer le visuel Opérations
                    </label>
                    <?php endif; ?>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Sous-menu Ressources</div>
                            <div class="bo-setting-row__help">Style d’affichage du sous-menu Ressources.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select name="nav_resources_submenu_style" class="bo-setting-row__field">
                                <?php foreach ($navStyleLabels as $val => $lbl): ?>
                                    <option value="<?= $h($val) ?>" <?= $navResStyle === $val ? 'selected' : '' ?>><?= $h($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Couleur d’accent Ressources</div>
                            <div class="bo-setting-row__help">Teinte appliquée au panneau latéral Ressources.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select name="nav_resources_accent" class="bo-setting-row__field">
                                <?php foreach ($navAccentLabels as $val => $lbl): ?>
                                    <option value="<?= $h($val) ?>" <?= $navResAccent === $val ? 'selected' : '' ?>><?= $h($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php $renderToggle(
                        'Image du panneau Ressources',
                        'Illustration affichée dans le menu latéral Ressources.',
                        'nav_resources_image_enabled',
                        $navResImageEnabled,
                        'Affichée',
                        'Masquée'
                    ); ?>
                    <div class="bo-setting-row">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Visuel Ressources</div>
                            <div class="bo-setting-row__help">Image de fond du menu Ressources.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <?php if ($navResUrl !== ''): ?>
                                <img src="<?= $h($navResUrl) ?>" alt="" class="bo-setting-thumb bo-setting-thumb--wide">
                            <?php endif; ?>
                            <span class="bo-setting-row__value"><?= $navResUrl !== '' ? 'Définie' : '—' ?></span>
                            <input type="file" name="nav_resources" class="bo-setting-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $slugHint === '' ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <?php if ($navResUrl !== ''): ?>
                    <label class="bo-setting-remove">
                        <input type="hidden" name="remove_nav_resources" value="0">
                        <input type="checkbox" name="remove_nav_resources" value="1">
                        Retirer le visuel Ressources
                    </label>
                    <?php endif; ?>
                </div>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="inscription">
                <p class="bo-setting-group__kicker">Inscription</p>
                <h2 class="bo-setting-group__title">Arrivée des membres</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Mode actuel</div>
                            <div class="bo-setting-row__help">Parcours de candidature, rôle d’accueil, contact des candidats, créneaux et motivation.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <span class="bo-setting-row__value"><?= $h($registrationLabel) ?><?= $communityLocked ? ' · recrutement fermé' : '' ?></span>
                        </div>
                    </div>
                </div>
                <p class="bo-settings-note">
                    <a href="<?= $h(url('back-office/community/inscription')) ?>">Gérer tous les paramètres d’inscription</a>
                    · <a href="<?= $h(url('back-office/community/presentation') . '#pack-milsim-editor') ?>">Éditeur complet du dossier candidature</a>
                </p>
            </section>

        </div>

        <details class="ath-card ath-rise bo-setting-group">
            <summary class="bo-setting-group__title" style="cursor:pointer;list-style:none;">Images &amp; marque complémentaires</summary>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Bannière</div>
                        <div class="bo-setting-row__help">Bandeau large pour les mises en avant (12 Mo max.).</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <span class="bo-setting-row__value"><?= $bannerUrl !== '' ? 'Définie' : '—' ?></span>
                        <input type="file" name="org_banner" class="bo-setting-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $slugHint === '' ? 'disabled' : '' ?>>
                    </div>
                </div>
                <?php if ($bannerUrl !== ''): ?>
                <label class="bo-setting-remove"><input type="hidden" name="remove_org_banner" value="0"><input type="checkbox" name="remove_org_banner" value="1"> Retirer la bannière</label>
                <?php endif; ?>
                <div class="bo-setting-row">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Icône navigateur</div>
                        <div class="bo-setting-row__help">Petite icône carrée dans l’onglet du navigateur.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <span class="bo-setting-row__value"><?= $faviconUrl !== '' ? 'Définie' : '—' ?></span>
                        <input type="file" name="org_favicon" class="bo-setting-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $slugHint === '' ? 'disabled' : '' ?>>
                    </div>
                </div>
                <?php if ($faviconUrl !== ''): ?>
                <label class="bo-setting-remove"><input type="hidden" name="remove_org_favicon" value="0"><input type="checkbox" name="remove_org_favicon" value="1"> Retirer l’icône</label>
                <?php endif; ?>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Couleurs de marque</div>
                        <div class="bo-setting-row__help">Couleur principale et couleur d’accent du portail.</div>
                    </div>
                    <div class="bo-setting-row__control" style="gap:12px;">
                        <input type="color" name="primary_color" value="<?= $h($primaryColor) ?>" title="Couleur principale">
                        <input type="color" name="accent_color" value="<?= $h($accentColor) ?>" title="Couleur d’accent">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Relais Discord (annonces)</div>
                        <div class="bo-setting-row__help">Les annonces portail et les mises à jour du pack Overwatch (changelog inclus) peuvent être relayées vers un salon Discord.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="url" id="discord_webhook_url" name="discord_webhook_url" class="bo-setting-row__field--wide" maxlength="500" value="<?= $h((string) ($i['discord_webhook_url'] ?? '')) ?>" placeholder="https://discord.com/api/webhooks/…">
                    </div>
                </div>
            </div>
        </details>

        <div class="bo-settings-save">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
        </div>
    </form>

    <form method="post" action="<?= $h($tenantTypeFormAction) ?>" class="ath-card ath-rise bo-setting-group" id="profil">
        <?= \App\Core\Csrf::field() ?>
        <p class="bo-setting-group__kicker">Profil</p>
        <h2 class="bo-setting-group__title">Type de communauté</h2>
        <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
            Le profil détermine les outils visibles pour tous les membres. Profil actuel :
            <strong><?= $h($currentTypeLabel) ?></strong>.
            Changer ou réappliquer un profil ajuste les menus et permissions sans effacer les données.
        </p>
        <fieldset class="bo-setting-group__rows" style="border:none;padding:0;margin-top:13px;">
            <legend class="sr-only">Profil de la communauté</legend>
            <?php foreach ($tenantTypeOptions as $typeKey => $typeMeta): ?>
                <?php
                $isCurrent = $typeKey === $currentTenantType;
                $inputId = 'tenant_type_' . preg_replace('/[^a-z0-9_-]/i', '', (string) $typeKey);
                ?>
                <label for="<?= $h($inputId) ?>" class="bo-setting-row" style="align-items:flex-start;cursor:pointer;<?= $isCurrent ? 'background:var(--ath-row-hover);margin:0 -8px;padding-left:8px;padding-right:8px;' : '' ?>">
                    <input id="<?= $h($inputId) ?>" type="radio" name="tenant_type" value="<?= $h((string) $typeKey) ?>" style="margin-top:3px;min-height:auto;" <?= $isCurrent ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label"><?= $h((string) ($typeMeta['label'] ?? $typeKey)) ?><?= $isCurrent ? ' · profil actuel' : '' ?></span>
                        <span class="bo-setting-row__help"><?= $h((string) ($typeMeta['description'] ?? '')) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;margin-top:8px;">
            <input type="checkbox" name="confirm_type_change" value="1" required style="margin-top:3px;min-height:auto;">
            <span class="bo-setting-row__copy">
                <span class="bo-setting-row__label">Confirmation</span>
                <span class="bo-setting-row__help">Je confirme l’application (ou la réapplication) de ce profil pour toute la communauté.</span>
            </span>
        </label>
        <div class="bo-settings-save">
            <button type="submit" class="ath-btn">Appliquer le profil</button>
        </div>
    </form>

    <p class="bo-settings-note">
        Inscription et vitrine :
        <a href="<?= $h(url('back-office/community/inscription')) ?>">Paramètres d’inscription</a>
        · <a href="<?= $h(url('back-office/community/presentation')) ?>">Page d’accueil publique</a>
        · <a href="<?= $h(url('back-office/configuration-initiale')) ?>">Assistant de démarrage</a>
    </p>
</div>
<script>
(function () {
    document.querySelectorAll('.ath-toggle__input[data-bo-toggle]').forEach(function (input) {
        var sync = function () {
            var label = document.querySelector('[data-bo-toggle-value="' + input.getAttribute('data-bo-toggle') + '"]');
            var wrap = input.closest('.ath-toggle');
            if (wrap) {
                wrap.classList.toggle('is-on', input.checked);
                wrap.classList.toggle('is-off', !input.checked);
            }
            if (label) {
                label.textContent = input.checked ? (input.getAttribute('data-on') || 'Actif') : (input.getAttribute('data-off') || 'Inactif');
            }
        };
        input.addEventListener('change', sync);
        sync();
    });

    var hash = (location.hash || '').replace(/^#/, '');
    if (hash) {
        var target = document.getElementById(hash);
        if (target) {
            requestAnimationFrame(function () {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    }

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
