<?php
declare(strict_types=1);

$user = $user ?? [];
$profile = is_array($profile ?? null) ? $profile : [];
$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$prefsSuccess = $success ?? null;
$prefsError = $error ?? null;
$uiPrefs = $uiPrefs ?? ['theme' => 'system', 'density' => 'comfortable', 'sidebar_collapsed' => false];
$extraCallsignSlots = isset($extraCallsignSlots) ? max(5, (int) $extraCallsignSlots) : (function_exists('personnel_extra_callsign_slots') ? personnel_extra_callsign_slots() : 5);
$extraCallsigns = is_array($extraCallsigns ?? null) ? $extraCallsigns : [];
if (isset($old['extra_callsigns']) && is_array($old['extra_callsigns'])) {
    $extraCallsigns = array_values(array_map(static fn ($v): string => (string) $v, $old['extra_callsigns']));
}
while (count($extraCallsigns) < $extraCallsignSlots) {
    $extraCallsigns[] = '';
}
$extraCallsigns = array_slice($extraCallsigns, 0, $extraCallsignSlots);
$notifEmailCatalog = $notifEmailCatalog ?? [];
$notifEmailState = $notifEmailState ?? [];
$timezoneSuggestions = $timezoneSuggestions ?? [];
$steamWebConfigured = !empty($steamWebConfigured ?? false);
$steamSyncReport = is_array($steamSyncReport ?? null) ? $steamSyncReport : null;
$uiThemeSync = is_string($uiThemeSync ?? null) ? $uiThemeSync : null;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/* Après une erreur de saisie, la page garde ce qui vient d’être tapé. */
$val = static function (string $field, mixed $saved) use ($old): string {
    if (array_key_exists($field, $old) && !is_array($old[$field])) {
        return (string) $old[$field];
    }

    return (string) ($saved ?? '');
};
$fieldErrors = static function (string $field) use ($errors, $h): string {
    if (empty($errors[$field]) || !is_array($errors[$field])) {
        return '';
    }
    $out = '';
    foreach ($errors[$field] as $e) {
        $out .= '<p class="ah-field__error" id="' . $h($field) . '-error">' . $h($e) . '</p>';
    }

    return $out;
};
$invalid = static fn (string $field): string => !empty($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '-error"' : '';

$fieldLabels = [
    'first_name' => 'Prénom',
    'last_name' => 'Nom',
    'callsign' => 'Indicatif principal',
    'steam_id' => 'Numéro ou adresse Steam',
    'timezone' => 'Fuseau horaire',
    'language' => 'Langue',
    'profile_slug' => 'Adresse courte de votre fiche',
];

$currentTheme = (string) ($old['ui_theme'] ?? ($uiPrefs['theme'] ?? 'system'));
if (!in_array($currentTheme, ['system', 'light', 'dark', 'tenant'], true)) {
    $currentTheme = 'system';
}
$currentDensity = (string) ($old['ui_density'] ?? ($uiPrefs['density'] ?? 'comfortable'));
if (!in_array($currentDensity, ['comfortable', 'compact'], true)) {
    $currentDensity = 'comfortable';
}
$themeOptions = [
    'system' => ['Automatique', 'Suit le réglage de votre appareil'],
    'light' => ['Clair', 'Fond clair en permanence'],
    'dark' => ['Sombre', 'Mode nuit en permanence'],
    'tenant' => ['Communauté', 'Couleurs de votre communauté'],
];
$currentLanguage = $val('language', $profile['language'] ?? 'fr');
$currentTimezone = $val('timezone', ($profile['timezone'] ?? '') !== '' ? $profile['timezone'] : 'Europe/Paris');
$steamId = trim((string) ($user['steam_id'] ?? ''));
$filledAliases = count(array_filter($extraCallsigns, static fn (string $v): bool => trim($v) !== ''));

if ($old !== []) {
    $oldNotif = is_array($old['notif_email'] ?? null) ? $old['notif_email'] : [];
    foreach ($notifEmailCatalog as $item) {
        $notifEmailState[$item['key']] = isset($oldNotif[$item['key']]);
    }
}
$notifByGroup = [];
foreach ($notifEmailCatalog as $item) {
    $g = $item['group'] ?? 'Autres';
    $notifByGroup[$g][] = $item;
}
$notifOn = count(array_filter($notifEmailCatalog, static fn (array $item): bool => !empty($notifEmailState[$item['key']])));

// Rôles liés de Discord : formulaires séparés, rattachés à leurs boutons par l’attribut form.
$discordLr = null;
$discordLink = null;
try {
    $discordLr = new \App\Services\Integrations\DiscordLinkedRoles();
    $discordLinks = new \App\Repositories\UserDiscordLinkRepository();
    $discordLink = $discordLinks->schemaReady() ? $discordLinks->find((int) ($user['id'] ?? 0)) : null;
} catch (\Throwable) {
}
$discordReady = $discordLr !== null && $discordLr->configured();
$discordOk = \App\Core\Session::getFlash('discord_link_success');
$discordErr = \App\Core\Session::getFlash('discord_link_error');
$showDiscord = $discordReady || $discordLink !== null || $discordOk || $discordErr;

$prefSections = [
    'section-profil' => 'Identité',
    'section-liaisons' => 'Jeu et comptes liés',
    'section-locale' => 'Langue et heure',
    'section-interface' => 'Affichage',
    'notifications-email' => 'Notifications',
];

$accountNavKey = 'preferences';
$accountTitle = 'Profil & préférences';
$accountLead = 'Votre identité sur le portail, vos comptes liés, la langue, l’affichage et les e-mails reçus.';
$accountUser = $user;
// Les messages d’enregistrement s’affichent dans la barre d’enregistrement de cette page.
$success = null;
$error = null;
require base_path('views/partials/account/shell_open.php');

$saveState = $errors !== [] || $prefsError ? 'error' : ($prefsSuccess ? 'saved' : 'clean');
?>

<div class="ah-pref" data-prefs>

<nav class="ah-pref__toc" aria-label="Sections des préférences">
    <?php foreach ($prefSections as $anchor => $label): ?>
    <a href="#<?= $h($anchor) ?>" data-toc-link="<?= $h($anchor) ?>"><?= $h($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($errors !== []): ?>
<div class="ah-pref__alert is-error" role="alert" tabindex="-1" data-prefs-alert>
    <p class="ah-pref__alert-title">Rien n’a été enregistré : corrigez <?= count($errors) > 1 ? 'les champs suivants' : 'le champ suivant' ?>.</p>
    <ul>
        <?php foreach ($errors as $field => $messages): ?>
        <li><a href="#<?= $h($field) ?>"><?= $h($fieldLabels[$field] ?? 'Champ') ?></a> : <?= $h(is_array($messages) ? implode(' ', $messages) : (string) $messages) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php elseif ($prefsError): ?>
<div class="ah-pref__alert is-error" role="alert" tabindex="-1" data-prefs-alert>
    <p class="ah-pref__alert-title">Rien n’a été enregistré.</p>
    <p><?= $h($prefsError) ?></p>
</div>
<?php endif; ?>

<?php if ($steamSyncReport !== null): ?>
<div class="ah-pref__alert <?= !empty($steamSyncReport['ok']) ? 'is-ok' : 'is-warn' ?>" role="region" aria-label="Détail de la synchronisation Steam">
    <p class="ah-pref__alert-title"><?= !empty($steamSyncReport['ok']) ? 'Synchronisation terminée' : 'Synchronisation interrompue' ?><?php if (!empty($steamSyncReport['finished_at'])): ?> · <?= $h($steamSyncReport['finished_at']) ?><?php endif; ?></p>
    <?php $steps = isset($steamSyncReport['steps']) && is_array($steamSyncReport['steps']) ? $steamSyncReport['steps'] : []; ?>
    <?php if ($steps !== []): ?>
    <ol class="ah-pref__steps">
        <?php foreach ($steps as $st): ?>
        <li class="<?= !empty($st['ok']) ? 'is-ok' : 'is-warn' ?>">
            <strong><?= $h($st['label'] ?? 'Étape') ?></strong>
            <?php if (!empty($st['detail'])): ?><span><?= $h($st['detail']) ?></span><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php
    $sd = isset($steamSyncReport['data']) && is_array($steamSyncReport['data']) ? $steamSyncReport['data'] : [];
    $pseudo = isset($sd['public_pseudo']) ? trim((string) $sd['public_pseudo']) : '';
    $sidShow = isset($sd['steam_id']) ? trim((string) $sd['steam_id']) : '';
    ?>
    <?php if ($pseudo !== '' || $sidShow !== ''): ?>
    <p><?php if ($pseudo !== ''): ?>Pseudo Steam : <strong><?= $h($pseudo) ?></strong>. <?php endif; ?><?php if ($sidShow !== ''): ?>Identifiant confirmé : <strong class="ah-mono"><?= $h($sidShow) ?></strong>.<?php endif; ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" action="<?= $h(url('account/preferences')) ?>" class="ah-pref__form" id="prefs-form" data-prefs-form>
    <?= \App\Core\Csrf::field() ?>

    <section id="section-profil" class="ah-pref__section" aria-labelledby="prefs-identity-heading">
        <header class="ah-pref__section-head">
            <h2 id="prefs-identity-heading" class="ah-pref__section-title">Identité</h2>
            <p class="ah-pref__section-desc">Comment vous apparaissez sur le portail, dans l’annuaire et à la radio.</p>
        </header>

        <div class="ah-pref__rows">
            <div class="ah-pref__row">
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title">Prénom et nom</p>
                    <p class="ah-pref__row-help">Repris sur votre dossier et dans l’annuaire de la communauté. Jamais affichés en public.</p>
                </div>
                <div class="ah-pref__row-control ah-pref__grid-2">
                    <div class="ah-field" data-locate-box>
                        <label class="ah-field__label" for="first_name">Prénom</label>
                        <input type="text" name="first_name" id="first_name" value="<?= $h($val('first_name', $profile['first_name'] ?? '')) ?>" maxlength="100" autocomplete="given-name" required<?= $invalid('first_name') ?>>
                        <?= $fieldErrors('first_name') ?>
                    </div>
                    <div class="ah-field" data-locate-box>
                        <label class="ah-field__label" for="last_name">Nom</label>
                        <input type="text" name="last_name" id="last_name" value="<?= $h($val('last_name', $profile['last_name'] ?? '')) ?>" maxlength="100" autocomplete="family-name" required<?= $invalid('last_name') ?>>
                        <?= $fieldErrors('last_name') ?>
                    </div>
                </div>
            </div>

            <div class="ah-pref__row">
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title">Nom en public</p>
                    <p class="ah-pref__row-help">Le pseudo s’affiche sur les pages publiques du site (contributeurs, propositions de traduction) et en premier dans l’administration. Vide : l’indicatif est repris.</p>
                </div>
                <div class="ah-pref__row-control ah-pref__grid-2">
                    <div class="ah-field" data-locate-box>
                        <label class="ah-field__label" for="public_name">Pseudo public</label>
                        <input type="text" name="public_name" id="public_name" value="<?= $h($val('public_name', $publicName ?? '')) ?>" maxlength="40" autocomplete="nickname" placeholder="<?= $h($user['callsign'] ?? '') ?>">
                    </div>
                    <div class="ah-field" data-locate-box>
                        <label class="ah-field__label" for="callsign">Indicatif principal</label>
                        <input type="text" name="callsign" id="callsign" value="<?= $h($val('callsign', $user['callsign'] ?? '')) ?>" maxlength="50" autocomplete="off"<?= $invalid('callsign') ?>>
                        <p class="ah-field__help">Sur le portail et dans les outils de carte.</p>
                        <?= $fieldErrors('callsign') ?>
                    </div>
                </div>
            </div>

            <div class="ah-pref__row" id="indicatifs-supplementaires" data-locate-box>
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title">Indicatifs supplémentaires</p>
                    <p class="ah-pref__row-help">Jusqu’à <?= (int) $extraCallsignSlots ?> alias radio : variantes d’unité, indicatifs secondaires.</p>
                </div>
                <div class="ah-pref__row-control">
                    <details class="ah-pref__disclosure"<?= $filledAliases > 0 ? ' open' : '' ?>>
                        <summary><?= $filledAliases > 0 ? $h(sprintf($filledAliases > 1 ? '%d alias enregistrés' : '%d alias enregistré', $filledAliases)) : 'Ajouter des alias' ?></summary>
                        <div class="ah-pref__grid-2 ah-pref__aliases">
                            <?php for ($i = 0; $i < $extraCallsignSlots; $i++): ?>
                            <div class="ah-field">
                                <label class="ah-field__label" for="extra_callsign_<?= $i ?>">Alias <?= $i + 1 ?></label>
                                <input type="text" name="extra_callsigns[]" id="extra_callsign_<?= $i ?>" value="<?= $h($extraCallsigns[$i] ?? '') ?>" maxlength="100" autocomplete="off">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </details>
                </div>
            </div>

            <div class="ah-pref__row">
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title">Adresse courte de votre fiche</p>
                    <p class="ah-pref__row-help">Facultatif. Lettres minuscules, chiffres et tirets, 40 caractères au plus. Vide : l’adresse par défaut est utilisée.</p>
                </div>
                <div class="ah-pref__row-control">
                    <div class="ah-field" data-locate-box>
                        <label class="ah-field__label" for="profile_slug">Adresse courte</label>
                        <input type="text" name="profile_slug" id="profile_slug" class="ah-mono" value="<?= $h($val('profile_slug', $user['profile_slug'] ?? '')) ?>" maxlength="40" placeholder="ex. jean-dupont" autocomplete="off" spellcheck="false" data-slug-input<?= $invalid('profile_slug') ?>>
                        <?= $fieldErrors('profile_slug') ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="section-liaisons" class="ah-pref__section" aria-labelledby="prefs-links-heading">
        <header class="ah-pref__section-head">
            <h2 id="prefs-links-heading" class="ah-pref__section-title">Jeu et comptes liés</h2>
            <p class="ah-pref__section-desc">Steam vous fait reconnaître en jeu et par COMSPEC ATAK. Discord affiche vos communautés sur votre profil Discord.</p>
        </header>

        <div class="ah-pref__rows">
            <div class="ah-pref__row" data-locate-box>
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title">Steam</p>
                    <p class="ah-pref__row-help">Lié uniquement au jeu et à la cartographie. La photo publique Steam n’est jamais importée.</p>
                </div>
                <div class="ah-pref__row-control">
                    <div class="ah-link-card<?= $steamId !== '' ? ' is-linked' : '' ?>">
                        <span class="ah-link-card__state"><?= $steamId !== '' ? 'Compte Steam lié' : 'Aucun compte Steam lié' ?></span>
                        <a href="<?= $h(url('account/steam/connect')) ?>" class="account-hub__btn account-hub__btn--ink"><?= $steamId !== '' ? 'Changer de compte Steam' : 'Se connecter avec Steam' ?></a>
                    </div>
                    <details class="ah-pref__disclosure"<?= !empty($errors['steam_id']) ? ' open' : '' ?>>
                        <summary>Indiquer le numéro ou l’adresse du profil à la main</summary>
                        <div class="ah-field" data-locate-box>
                            <label class="ah-field__label" for="steam_id">Numéro ou adresse Steam</label>
                            <input type="text" name="steam_id" id="steam_id" class="ah-mono" value="<?= $h($val('steam_id', $user['steam_id'] ?? '')) ?>" placeholder="Numéro en jeu, STEAM_0:…, ou adresse de profil" maxlength="512" autocomplete="off" spellcheck="false"<?= $invalid('steam_id') ?>>
                            <p class="ah-field__help">Le numéro affiché dans le jeu (souvent 17 chiffres), un identifiant Steam classique, ou l’adresse de votre profil<?php if ($steamWebConfigured): ?>, y compris un lien avec votre pseudo<?php endif; ?>. Effacer ce champ délie Steam.</p>
                            <?= $fieldErrors('steam_id') ?>
                        </div>
                    </details>
                </div>
            </div>

            <?php if ($showDiscord): ?>
            <div class="ah-pref__row" id="section-discord" data-locate-box>
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title">Discord</p>
                    <p class="ah-pref__row-help">Le serveur Discord d’Athena vous reconnaît comme compte Athena. Rien n’est posté en votre nom.</p>
                </div>
                <div class="ah-pref__row-control">
                    <?php if ($discordOk): ?><p class="ah-pref__inline-ok" role="status"><?= $h($discordOk) ?></p><?php endif; ?>
                    <?php if ($discordErr): ?><p class="ah-field__error" role="alert"><?= $h($discordErr) ?></p><?php endif; ?>
                    <?php if ($discordLink !== null): ?>
                    <div class="ah-link-card is-linked">
                        <span class="ah-link-card__state">
                            Relié à <strong><?= $h($discordLink['discord_username'] ?: $discordLink['discord_user_id']) ?></strong><?php if (!empty($discordLink['synced_at'])): ?><small>Dernière mise à jour <?= $h($discordLink['synced_at']) ?></small><?php endif; ?>
                        </span>
                        <span class="ah-link-card__actions">
                            <button type="submit" form="discord-refresh-form" class="account-hub__btn account-hub__btn--soft">Mettre à jour maintenant</button>
                            <button type="submit" form="discord-unlink-form" class="account-hub__btn ah-btn-danger">Délier mon Discord</button>
                        </span>
                    </div>
                    <?php elseif ($discordReady): ?>
                    <div class="ah-link-card">
                        <span class="ah-link-card__state">Discord n’est pas relié</span>
                        <a class="account-hub__btn account-hub__btn--ink" href="<?= $h(url('discord/roles-lies')) ?>">Relier mon Discord</a>
                    </div>
                    <p class="ah-field__help">Discord vous demande d’autoriser Athena à lire votre identifiant et à mettre à jour vos rôles liés.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="section-locale" class="ah-pref__section" aria-labelledby="prefs-locale-heading">
        <header class="ah-pref__section-head">
            <h2 id="prefs-locale-heading" class="ah-pref__section-title">Langue et heure</h2>
            <p class="ah-pref__section-desc">La langue s’applique aux pages et aux e-mails. Le fuseau sert à afficher les heures des événements et de votre activité.</p>
        </header>
        <div class="ah-pref__rows">
            <div class="ah-pref__row">
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title">Langue et fuseau horaire</p>
                </div>
                <div class="ah-pref__row-control ah-pref__grid-2">
                    <div class="ah-field" data-locate-box>
                        <label class="ah-field__label" for="language"><?= $h(function_exists('__') ? __('common.language') : 'Langue') ?></label>
                        <select name="language" id="language"<?= $invalid('language') ?>>
                            <option value="fr" <?= $currentLanguage === 'fr' ? 'selected' : '' ?>><?= $h(function_exists('__') ? __('common.language_fr') : 'Français') ?></option>
                            <option value="en" <?= $currentLanguage === 'en' ? 'selected' : '' ?>><?= $h(function_exists('__') ? __('common.language_en') : 'English') ?></option>
                            <?php foreach (\App\Services\I18n\CommunityLanguages::published() as $langCode => $langName): ?>
                            <option value="<?= $h($langCode) ?>" lang="<?= $h($langCode) ?>" <?= $currentLanguage === $langCode ? 'selected' : '' ?>><?= $h($langName) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fieldErrors('language') ?>
                    </div>
                    <div class="ah-field" data-locate-box>
                        <label class="ah-field__label" for="timezone">Fuseau horaire</label>
                        <input type="text" name="timezone" id="timezone" value="<?= $h($currentTimezone) ?>" placeholder="Europe/Paris" list="tz-suggestions" autocomplete="off" maxlength="50" spellcheck="false" data-tz-input<?= $invalid('timezone') ?>>
                        <datalist id="tz-suggestions">
                            <?php foreach ($timezoneSuggestions as $tz): ?>
                            <option value="<?= $h($tz) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <p class="ah-field__help" data-tz-preview>Choisissez dans la liste, ou saisissez un fuseau standard comme Europe/Paris.</p>
                        <?= $fieldErrors('timezone') ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="section-interface" class="ah-pref__section" aria-labelledby="prefs-display-heading">
        <header class="ah-pref__section-head">
            <h2 id="prefs-display-heading" class="ah-pref__section-title">Affichage</h2>
            <p class="ah-pref__section-desc">Le thème s’applique à votre espace compte et au back-office. La densité concerne les listes d’ATAK web.</p>
        </header>
        <div class="ah-pref__rows">
            <div class="ah-pref__row" id="ui_theme" data-locate-box>
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title" id="prefs-theme-label">Thème</p>
                </div>
                <div class="ah-pref__row-control">
                    <div class="ah-theme-picker" role="radiogroup" aria-labelledby="prefs-theme-label">
                        <?php foreach ($themeOptions as $themeValue => [$themeLabel, $themeHint]): ?>
                        <label class="ah-theme-option">
                            <input type="radio" name="ui_theme" value="<?= $h($themeValue) ?>" <?= $currentTheme === $themeValue ? 'checked' : '' ?>>
                            <span class="ah-theme-option__swatch is-<?= $h($themeValue) ?>" aria-hidden="true"><span></span><span></span><span></span></span>
                            <span class="ah-theme-option__label"><?= $h($themeLabel) ?></span>
                            <span class="ah-theme-option__hint"><?= $h($themeHint) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="ah-pref__row" id="ui_density" data-locate-box>
                <div class="ah-pref__row-label">
                    <p class="ah-pref__row-title" id="prefs-density-label">Densité des listes</p>
                    <p class="ah-pref__row-help">Dans ATAK web.</p>
                </div>
                <div class="ah-pref__row-control">
                    <div class="ah-segmented" role="radiogroup" aria-labelledby="prefs-density-label">
                        <label><input type="radio" name="ui_density" value="comfortable" <?= $currentDensity === 'comfortable' ? 'checked' : '' ?>><span>Confortable</span></label>
                        <label><input type="radio" name="ui_density" value="compact" <?= $currentDensity === 'compact' ? 'checked' : '' ?>><span>Compact</span></label>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="notifications-email" class="ah-pref__section" aria-labelledby="prefs-notif-heading">
        <header class="ah-pref__section-head">
            <h2 id="prefs-notif-heading" class="ah-pref__section-title">Notifications par e-mail</h2>
            <p class="ah-pref__section-desc">Désactivez les messages que vous ne voulez plus recevoir. Les e-mails indispensables (mot de passe oublié, vérification d’adresse, liens à usage unique) sont toujours envoyés.</p>
        </header>

        <?php if ($notifByGroup === []): ?>
        <div class="account-hub__empty">
            <p class="account-hub__empty-title">Aucune préférence disponible</p>
            <p class="account-hub__empty-desc">Les types de messages seront proposés ici dès qu’ils seront activés pour votre communauté.</p>
        </div>
        <?php else: ?>
        <div class="ah-notif-tools">
            <div class="ah-field ah-notif-tools__search">
                <label class="ah-field__label" for="notif-search">Filtrer les notifications</label>
                <input type="search" id="notif-search" placeholder="Ex. sécurité, formation, recrutement…" autocomplete="off">
            </div>
            <div class="ah-notif-tools__row">
                <span id="notif-stats" class="ah-notif-tools__count" aria-live="polite"><?= (int) $notifOn ?> sur <?= count($notifEmailCatalog) ?> activées</span>
                <span class="ah-notif-tools__presets" role="group" aria-label="Sélections rapides">
                    <button type="button" class="ah-chip" data-notif-preset="minimum">Sécurité seulement</button>
                    <button type="button" class="ah-chip" data-notif-preset="standard">Usage courant</button>
                    <button type="button" class="ah-chip" data-notif-all="1">Tout recevoir</button>
                    <button type="button" class="ah-chip" data-notif-all="0">Tout couper</button>
                </span>
            </div>
            <p class="ah-notif-tools__note" id="notif-filter-note" hidden>Filtre actif : « Tout recevoir » et « Tout couper » ne touchent que les notifications affichées. <button type="button" class="ah-linkbtn" id="notif-reset-filter">Tout afficher</button></p>
        </div>

        <div class="ah-notif-groups">
            <?php foreach ($notifByGroup as $groupName => $items): ?>
            <?php $groupId = 'notif-group-' . substr(md5((string) $groupName), 0, 8); ?>
            <div class="ah-notif-group" data-notif-group="<?= $h(mb_strtolower((string) $groupName)) ?>" role="group" aria-labelledby="<?= $h($groupId) ?>">
                <div class="ah-notif-group__head">
                    <h3 class="ah-notif-group__title" id="<?= $h($groupId) ?>"><?= $h($groupName) ?></h3>
                    <span class="ah-notif-group__actions">
                        <button type="button" class="ah-linkbtn" data-group-toggle="1">Tout activer</button>
                        <button type="button" class="ah-linkbtn" data-group-toggle="0">Tout désactiver</button>
                    </span>
                </div>
                <ul class="ah-notif-list">
                    <?php foreach ($items as $item): ?>
                    <?php
                    $key = (string) $item['key'];
                    $checked = !empty($notifEmailState[$key]);
                    $searchBlob = mb_strtolower(($item['label'] ?? '') . ' ' . ($item['hint'] ?? '') . ' ' . $groupName);
                    $idSafe = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
                    ?>
                    <li class="ah-notif" data-notif-item="<?= $h($searchBlob) ?>">
                        <label class="ah-notif__label" for="notif_<?= $h($idSafe) ?>">
                            <span class="ah-notif__title"><?= $h($item['label']) ?></span>
                            <span class="ah-notif__hint"><?= $h($item['hint']) ?></span>
                        </label>
                        <span class="ah-switch">
                            <input type="checkbox" role="switch" class="notif-email-toggle" name="notif_email[<?= $h($key) ?>]" id="notif_<?= $h($idSafe) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
                            <span class="ah-switch__track" aria-hidden="true"></span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <div class="ah-savebar" data-save-state="<?= $h($saveState) ?>" data-savebar>
        <p class="ah-savebar__status" role="status" aria-live="polite">
            <span class="ah-savebar__dot" aria-hidden="true"></span>
            <span data-save-message
                  data-msg-clean="Aucune modification en attente."
                  data-msg-dirty="Modifications non enregistrées."
                  data-msg-saving="Enregistrement…"
                  data-msg-saved="<?= $h($prefsSuccess ?: 'Préférences enregistrées.') ?>"
                  data-msg-error="<?= $h($errors !== [] ? 'Rien n’a été enregistré : corrigez les champs signalés.' : ($prefsError ?: 'Rien n’a été enregistré.')) ?>"><?php
                echo $h(match ($saveState) {
                    'error' => $errors !== [] ? 'Rien n’a été enregistré : corrigez les champs signalés.' : ($prefsError ?: 'Rien n’a été enregistré.'),
                    'saved' => $prefsSuccess ?: 'Préférences enregistrées.',
                    default => 'Aucune modification en attente.',
                });
            ?></span>
        </p>
        <div class="ah-savebar__actions">
            <button type="reset" class="account-hub__btn ah-btn-quiet" data-prefs-reset hidden>Annuler les modifications</button>
            <button type="submit" class="account-hub__btn account-hub__btn--primary" data-prefs-submit>Enregistrer les préférences</button>
        </div>
    </div>
</form>

<?php if ($discordLink !== null): ?>
<form id="discord-refresh-form" method="post" action="<?= $h(url('account/discord/actualiser')) ?>" hidden><?= \App\Core\Csrf::field() ?></form>
<form id="discord-unlink-form" method="post" action="<?= $h(url('account/discord/delier')) ?>" hidden onsubmit="return confirm('Délier votre compte Discord ? Vos rôles liés disparaîtront du serveur.');"><?= \App\Core\Csrf::field() ?></form>
<?php endif; ?>

</div>

<script src="<?= $h(asset_url('assets/js/account-preferences.js')) ?>" defer></script>

<?php if ($uiThemeSync !== null): ?>
<script>
/* Thème enregistré : appliqué au mode nuit de l’espace compte et du back-office (même clé que le bouton jour/nuit). */
(function () {
    var theme = <?= json_encode($uiThemeSync) ?>;
    try {
        if (theme === 'dark' || theme === 'light') {
            localStorage.setItem('athena.bo.theme', theme);
        } else {
            localStorage.removeItem('athena.bo.theme');
        }
    } catch (e) {}
    var dark = theme === 'dark' || (theme !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-bo-theme', dark ? 'dark' : 'light');
})();
</script>
<?php endif; ?>

<?php require base_path('views/partials/account/shell_close.php'); ?>
