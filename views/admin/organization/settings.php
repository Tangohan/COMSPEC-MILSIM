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
/** @var list<array{slug: string, name: string}> $roleOptions */
/** @var string $defaultGuestRoleSlug */

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

$roles = is_array($roleOptions ?? null) ? $roleOptions : [];
$guestRoleSlug = trim((string) ($defaultGuestRoleSlug ?? ($c['default_guest_role_slug'] ?? 'invite')));
$guestRoleLabel = '—';
foreach ($roles as $role) {
    if (($role['slug'] ?? '') === $guestRoleSlug) {
        $guestRoleLabel = (string) ($role['name'] ?? $guestRoleSlug);
        break;
    }
}

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
$discordInviteMissing = \App\Services\Community\TenantCommunityProfileService::needsDiscordInviteAlert($c);

$registryListed = !array_key_exists('registry_listed', $c) || !empty($c['registry_listed']);
$forumMembersOnly = !empty($c['forum_members_only']);
$communityLocked = !empty($c['community_locked']);
$contactFormEnabled = !empty($c['contact_form_enabled']);
$requireAiAck = !array_key_exists('require_ai_ack', $c) || !empty($c['require_ai_ack']);
$recruitmentBadgeOpen = !empty($c['public_recruitment_badge_open']);

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
            <a href="#inscription">Renseigner le lien Discord</a>
        </div>
    <?php endif; ?>

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
                            <div class="bo-setting-row__label">Message d’accueil</div>
                            <div class="bo-setting-row__help">Texte court affiché aux visiteurs sur la page publique.</div>
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
                </div>
                <?php if ($publicPageUrl !== ''): ?>
                    <p class="bo-settings-note"><a href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir la page publique ↗</a></p>
                <?php endif; ?>
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
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Fuseau horaire</div>
                            <div class="bo-setting-row__help">Base de tous les horodatages (événements, échéances, journaux).</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="timezone" name="timezone" class="bo-setting-row__field--wide">
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
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Mode d’inscription</div>
                            <div class="bo-setting-row__help">Détermine le parcours de candidature proposé aux visiteurs.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="registration_mode" name="registration_mode" class="bo-setting-row__field--wide">
                                <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>Dossier MilSim complet</option>
                                <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Formulaire court</option>
                                <option value="discord" <?= $registrationMode === 'discord' ? 'selected' : '' ?>>Recrutement via Discord</option>
                            </select>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Rôle d’accueil</div>
                            <div class="bo-setting-row__help">Attribué automatiquement au nouvel arrivant.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="default_guest_role_slug" name="default_guest_role_slug" class="bo-setting-row__field--wide">
                                <?php if ($roles === []): ?>
                                    <option value="<?= $h($guestRoleSlug) ?>"><?= $h($guestRoleLabel) ?></option>
                                <?php else: ?>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $h((string) $role['slug']) ?>" <?= ($role['slug'] ?? '') === $guestRoleSlug ? 'selected' : '' ?>><?= $h((string) $role['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">E-mail de contact</div>
                            <div class="bo-setting-row__help">Affiché aux candidats et visiteurs.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="email" id="contact_email" name="contact_email" class="bo-setting-row__field--wide" maxlength="255" value="<?= $h((string) ($c['contact_email'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Lien Discord<?= $registrationMode === 'discord' ? ' *' : '' ?></div>
                            <div class="bo-setting-row__help">Obligatoire pour le recrutement via Discord.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="url" id="contact_discord_url" name="contact_discord_url" class="bo-setting-row__field--wide" maxlength="500" value="<?= $h((string) ($c['contact_discord_url'] ?? '')) ?>" placeholder="https://discord.gg/…">
                        </div>
                    </div>
                    <?php $renderToggle(
                        'Formulaire « nous écrire »',
                        'Permet aux visiteurs d’envoyer un message depuis la fiche publique.',
                        'contact_form_enabled',
                        $contactFormEnabled,
                        'Actif',
                        'Inactif'
                    ); ?>
                    <?php $renderToggle(
                        'Fermeture temporaire du recrutement',
                        'Suspend le dépôt de nouvelles candidatures.',
                        'community_locked',
                        $communityLocked,
                        'Fermée',
                        'Ouverte'
                    ); ?>
                    <?php $renderToggle(
                        'Accusé de réception des règles',
                        'Exige l’acceptation des règles avant dépôt d’une candidature.',
                        'require_ai_ack',
                        $requireAiAck,
                        'Exigé',
                        'Facultatif'
                    ); ?>
                    <?php $renderToggle(
                        'Badge « recrutement ouvert »',
                        'Affiche un badge sur la fiche publique lorsque le recrutement est ouvert.',
                        'public_recruitment_badge_open',
                        $recruitmentBadgeOpen,
                        'Affiché',
                        'Masqué'
                    ); ?>
                </div>
                <p class="bo-settings-note">
                    Mode actuel : <strong><?= $h($registrationLabel) ?></strong>
                    <?php if ($registrationMode === 'discord'): ?>
                        · <a href="<?= $h(url('back-office/recruitments/discord-questions')) ?>">Configurer les questions Discord</a>
                    <?php endif; ?>
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
                        <div class="bo-setting-row__label">Message d’introduction contact</div>
                        <div class="bo-setting-row__help">Texte affiché au-dessus du formulaire de contact.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="text" id="contact_intro" name="contact_intro" class="bo-setting-row__field--wide" maxlength="500" value="<?= $h((string) ($c['contact_intro'] ?? '')) ?>">
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
        Textes détaillés de la vitrine et formulaire de candidature complet :
        <a href="<?= $h(url('back-office/community/presentation')) ?>">Page d’accueil publique</a>
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
})();
</script>
