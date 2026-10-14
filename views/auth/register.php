<?php
$base = url('');
$title = $title ?? __('auth.title_register');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$old = is_array($register_old ?? null) ? $register_old : [];
$prefillCc = (string) ($prefill_community_code ?? ($old['community_code'] ?? ''));
$prefillSlug = (string) ($prefill_tenant_slug ?? '');
$val = static function (array $old, string $key, string $default = '') : string {
    $v = $old[$key] ?? $default;

    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$t = static fn (string $key): string => htmlspecialchars(__($key), ENT_QUOTES, 'UTF-8');
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$warning = \App\Core\Session::getFlash('warning');
$athena_header_current = 'register';

// Erreur serveur rattachée à un champ : affichée sous le champ (et non en haut de page).
$errorField = (string) ($register_error_field ?? '');
$fieldErrors = [];
$fieldNames = ['email', 'password', 'password_confirmation', 'first_name', 'last_name', 'community_code', 'discord_handle', 'steam_profile', 'accept_terms'];
if ($error && in_array($errorField, $fieldNames, true)) {
    $fieldErrors[$errorField] = (string) $error;
    $error = null;
}
$fieldError = static fn (string $name): string => $fieldErrors[$name] ?? '';
// Attributs communs : description (aide + erreur), état invalide, focus sur le champ en erreur.
$describe = static function (string $name, bool $hint = true) use ($fieldError): string {
    $ids = ($hint ? $name . '-hint ' : '') . $name . '-error';
    $out = ' aria-describedby="' . htmlspecialchars($ids, ENT_QUOTES, 'UTF-8') . '"';
    if ($fieldError($name) !== '') {
        $out .= ' aria-invalid="true" autofocus';
    }

    return $out;
};
$optionalOpen = $prefillCc !== '' || $prefillSlug !== ''
    || trim((string) ($old['discord_handle'] ?? '')) !== ''
    || trim((string) ($old['steam_profile'] ?? '')) !== ''
    || in_array($errorField, ['community_code', 'discord_handle', 'steam_profile'], true);
$eyeIcons = '<svg class="rg-eye__show" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>'
    . '<svg class="rg-eye__hide" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A16.5 16.5 0 0 0 2 12s3.6 7 10 7a9.8 9.8 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="theme-color" content="#0b3d38">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light dark">
    <script>
    // Mode nuit : même préférence que le reste du site (localStorage « athena.bo.theme », sinon le système).
    (function () {
        var pref = null;
        try { pref = localStorage.getItem('athena.bo.theme'); } catch (e) {}
        var dark = pref === 'dark' || (pref !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-bo-theme', dark ? 'dark' : 'light');
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/athena-header.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/dsfr-service.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/auth-register.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php $GLOBALS['__dsfr_service_css'] = true; ?>
</head>
<body class="ds-page ds-page--split ds-page--register">
<a class="ds-skip" href="#contenu"><?= htmlspecialchars(__('common.skip_to_content'), ENT_QUOTES, 'UTF-8') ?></a>
<?php require base_path('views/partials/athena_header_guest.php'); ?>

<div class="ds-split">
    <aside class="ds-visual" aria-hidden="true">
        <img class="ds-visual__img"
             src="<?= htmlspecialchars(asset_url('assets/images/fog-team.jpg'), ENT_QUOTES, 'UTF-8') ?>"
             alt=""
             width="1600"
             height="1067"
             decoding="async">
        <div class="ds-visual__veil"></div>
        <p class="ds-visual__caption"><?= htmlspecialchars(__('auth.register_aside'), ENT_QUOTES, 'UTF-8') ?></p>
    </aside>

    <main class="ds-main" id="contenu">
    <div class="ds-main__inner ds-main__inner--register">
    <p class="ds-kicker"><?= $t('auth.register_kicker') ?></p>
    <h1 class="ds-title"><?= $t('auth.register_heading') ?></h1>
    <p class="ds-lead rg-lead"><?= $t('auth.register_sub_short') ?></p>

    <div class="rg-preview">
        <span class="ds-preview-notice__badge">Preview ouverte</span>
        <span class="rg-preview__text"><?= $t('auth.register_preview_line') ?></span>
        <button type="button" class="rg-preview__more" data-preview-open aria-haspopup="dialog"><?= $t('auth.register_preview_more') ?></button>
    </div>

    <?php if ($prefillSlug !== ''): ?>
    <p class="ds-pill" role="status">
        <?= $t('auth.register_space_targeted') ?>
        <?= $e($prefillSlug) ?>
    </p>
    <?php endif; ?>

    <div class="ds-alert-stack">
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($success): ?>
            <?php $flash_variant = 'success'; $flash_message = $success; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($warning): ?>
            <?php $flash_variant = 'warning'; $flash_message = $warning; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= $e(url('register')) ?>" class="ds-form rg-form" novalidate data-register-form data-inline-validation
          data-msg-email-required="<?= $t('auth.register_err_email_required') ?>"
          data-msg-email-invalid="<?= $t('auth.register_err_email_invalid') ?>"
          data-msg-first-name="<?= $t('auth.register_err_first_name') ?>"
          data-msg-last-name="<?= $t('auth.register_err_last_name') ?>"
          data-msg-password="<?= $t('auth.register_err_password') ?>"
          data-msg-confirm="<?= $t('auth.register_err_confirm') ?>"
          data-msg-terms="<?= $t('auth.register_err_terms') ?>"
          data-msg-summary="<?= $t('auth.register_err_summary') ?>">
        <?= \App\Core\Csrf::field() ?>
        <p class="rg-summary" data-error-summary role="alert" tabindex="-1" hidden></p>

        <fieldset class="rg-section">
            <legend class="rg-section__title"><span class="rg-section__num" aria-hidden="true">1</span><?= $t('auth.register_section_access') ?></legend>
            <p class="rg-section__hint"><?= $t('auth.register_section_access_hint') ?></p>

            <div class="rg-field">
                <label class="rg-label" for="email"><?= $t('auth.email') ?></label>
                <input id="email" type="email" name="email" data-lowercase="email" required autocomplete="email"
                       inputmode="email" autocapitalize="none" spellcheck="false" maxlength="190"
                       placeholder="<?= $t('auth.placeholder_email') ?>"
                       value="<?= $val($old, 'email') ?>"
                       class="rg-input"<?= $describe('email') ?>>
                <p class="rg-hint" id="email-hint"><?= $t('auth.register_email_hint_short') ?></p>
                <p class="rg-error" id="email-error" data-error-for="email"><?= $e($fieldError('email')) ?></p>
            </div>

            <div class="rg-grid">
                <div class="rg-field">
                    <label class="rg-label" for="password"><?= $t('auth.password') ?></label>
                    <div class="rg-password">
                        <input type="password" id="password" name="password" required minlength="8"
                               autocomplete="new-password" spellcheck="false" autocapitalize="none"
                               class="rg-input rg-input--password"
                               data-password-input data-strength-source<?= $describe('password') ?>>
                        <button type="button" class="rg-eye" data-password-toggle="password"
                                aria-controls="password" aria-pressed="false"
                                data-label-show="<?= $t('auth.show_password') ?>"
                                data-label-hide="<?= $t('auth.hide_password') ?>"
                                aria-label="<?= $t('auth.show_password') ?>"
                                title="<?= $t('auth.show_password') ?>"><?= $eyeIcons ?></button>
                    </div>
                    <div class="rg-meter" data-strength-meter data-level="0" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                    <p class="rg-hint" id="password-hint" data-strength-text aria-live="polite"
                       data-default="<?= $t('auth.register_password_hint') ?>"
                       data-too-short="<?= $t('auth.register_pw_too_short') ?>"
                       data-weak="<?= $t('auth.register_pw_weak') ?>"
                       data-fair="<?= $t('auth.register_pw_fair') ?>"
                       data-good="<?= $t('auth.register_pw_good') ?>"
                       data-strong="<?= $t('auth.register_pw_strong') ?>"><?= $t('auth.register_password_hint') ?></p>
                    <p class="rg-error" id="password-error" data-error-for="password"><?= $e($fieldError('password')) ?></p>
                </div>
                <div class="rg-field">
                    <label class="rg-label" for="password_confirmation"><?= $t('auth.register_password_confirm') ?></label>
                    <div class="rg-password">
                        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8"
                               autocomplete="new-password" spellcheck="false" autocapitalize="none"
                               class="rg-input rg-input--password"
                               data-password-confirm-of="password"
                               data-password-mismatch="<?= $t('auth.register_pw_mismatch') ?>"<?= $describe('password_confirmation') ?>>
                        <button type="button" class="rg-eye" data-password-toggle="password_confirmation"
                                aria-controls="password_confirmation" aria-pressed="false"
                                data-label-show="<?= $t('auth.show_password') ?>"
                                data-label-hide="<?= $t('auth.hide_password') ?>"
                                aria-label="<?= $t('auth.show_password') ?>"
                                title="<?= $t('auth.show_password') ?>"><?= $eyeIcons ?></button>
                    </div>
                    <div class="rg-meter rg-meter--spacer" aria-hidden="true"></div>
                    <p class="rg-hint" id="password_confirmation-hint" data-match-text aria-live="polite"
                       data-default="<?= $t('auth.register_password_confirm_ph') ?>"
                       data-match="<?= $t('auth.register_pw_match') ?>"
                       data-mismatch="<?= $t('auth.register_pw_mismatch') ?>"><?= $t('auth.register_password_confirm_ph') ?></p>
                    <p class="rg-error" id="password_confirmation-error" data-error-for="password_confirmation"><?= $e($fieldError('password_confirmation')) ?></p>
                </div>
            </div>
        </fieldset>

        <fieldset class="rg-section">
            <legend class="rg-section__title"><span class="rg-section__num" aria-hidden="true">2</span><?= $t('auth.register_identity') ?></legend>
            <p class="rg-section__hint" id="identity-hint"><?= $t('auth.register_section_identity_hint') ?></p>
            <div class="rg-grid">
                <div class="rg-field">
                    <label class="rg-label" for="first_name"><?= $t('auth.register_first_name') ?></label>
                    <input id="first_name" type="text" name="first_name" required minlength="1" maxlength="100"
                           autocomplete="given-name" autocapitalize="words"
                           placeholder="<?= $t('auth.register_first_name_ph') ?>"
                           value="<?= $val($old, 'first_name') ?>"
                           class="rg-input"
                           aria-describedby="identity-hint first_name-error"<?= $fieldError('first_name') !== '' ? ' aria-invalid="true" autofocus' : '' ?>>
                    <p class="rg-error" id="first_name-error" data-error-for="first_name"><?= $e($fieldError('first_name')) ?></p>
                </div>
                <div class="rg-field">
                    <label class="rg-label" for="last_name"><?= $t('auth.register_last_name') ?></label>
                    <input id="last_name" type="text" name="last_name" required minlength="1" maxlength="100"
                           autocomplete="family-name" autocapitalize="words"
                           placeholder="<?= $t('auth.register_last_name_ph') ?>"
                           value="<?= $val($old, 'last_name') ?>"
                           class="rg-input"
                           aria-describedby="identity-hint last_name-error"<?= $fieldError('last_name') !== '' ? ' aria-invalid="true" autofocus' : '' ?>>
                    <p class="rg-error" id="last_name-error" data-error-for="last_name"><?= $e($fieldError('last_name')) ?></p>
                </div>
            </div>
        </fieldset>

        <details class="rg-optional" data-optional-section<?= $optionalOpen ? ' open' : '' ?>>
            <summary class="rg-optional__summary">
                <span class="rg-optional__badge"><?= $t('auth.register_section_optional') ?></span>
                <span class="rg-optional__label"><?= $t('auth.register_section_optional_summary') ?></span>
                <svg class="rg-optional__chevron" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <div class="rg-optional__body">
                <p class="rg-section__hint"><?= $t('auth.register_section_optional_hint') ?></p>

                <div class="rg-field">
                    <label class="rg-label" for="community_code"><?= $t('auth.register_invite') ?></label>
                    <input id="community_code" type="text" name="community_code" maxlength="64" autocomplete="off"
                           autocapitalize="characters" spellcheck="false"
                           placeholder="<?= $t('auth.register_invite_ph') ?>"
                           value="<?= $e($prefillCc) ?>"
                           class="rg-input rg-input--code"<?= $describe('community_code') ?>>
                    <p class="rg-hint" id="community_code-hint"><?= $t('auth.register_invite_optional_hint') ?></p>
                    <p class="rg-error" id="community_code-error" data-error-for="community_code"><?= $e($fieldError('community_code')) ?></p>
                </div>

                <div class="rg-grid">
                    <div class="rg-field">
                        <label class="rg-label" for="discord_handle"><?= $t('auth.register_discord') ?></label>
                        <input id="discord_handle" type="text" name="discord_handle" maxlength="120" autocomplete="off"
                               autocapitalize="none" spellcheck="false"
                               placeholder="<?= $t('auth.register_discord_ph') ?>"
                               value="<?= $val($old, 'discord_handle') ?>"
                               class="rg-input"<?= $describe('discord_handle') ?>>
                        <p class="rg-hint" id="discord_handle-hint"><?= $t('auth.register_discord_hint') ?></p>
                        <p class="rg-error" id="discord_handle-error" data-error-for="discord_handle"><?= $e($fieldError('discord_handle')) ?></p>
                    </div>
                    <div class="rg-field">
                        <label class="rg-label" for="steam_profile"><?= $t('auth.register_steam') ?></label>
                        <input id="steam_profile" type="text" name="steam_profile" maxlength="512" autocomplete="off"
                               inputmode="url" autocapitalize="none" spellcheck="false"
                               placeholder="steamcommunity.com/profiles/…"
                               value="<?= $val($old, 'steam_profile') ?>"
                               class="rg-input"<?= $describe('steam_profile') ?>>
                        <p class="rg-hint" id="steam_profile-hint"><?= $t('auth.register_steam_hint_short') ?></p>
                        <p class="rg-error" id="steam_profile-error" data-error-for="steam_profile"><?= $e($fieldError('steam_profile')) ?></p>
                    </div>
                </div>

                <details class="rg-help">
                    <summary><?= $t('auth.register_steam_help_title') ?></summary>
                    <ol>
                        <li><?= $t('auth.register_steam_help_1') ?></li>
                        <li><?= $t('auth.register_steam_help_2') ?></li>
                        <li><?= $t('auth.register_steam_help_3') ?></li>
                    </ol>
                </details>
            </div>
        </details>

        <div class="rg-submit">
            <div class="ds-check rg-check<?= $fieldError('accept_terms') !== '' ? ' is-invalid' : '' ?>">
                <input type="checkbox" name="accept_terms" id="accept_terms" value="1" required
                       aria-describedby="accept_terms-error"
                       <?= $fieldError('accept_terms') !== '' ? 'aria-invalid="true" autofocus' : '' ?>
                       <?= !empty($old['accept_terms']) ? 'checked' : '' ?>>
                <label for="accept_terms">
                    <?= $t('auth.register_accept_prefix') ?>
                    <a href="<?= $e(url('legal/site')) ?>#cgu" target="_blank" rel="noopener"><?= $t('auth.register_terms') ?></a>
                    <?= $t('auth.register_accept_and') ?>
                    <a href="<?= $e(url('legal/site')) ?>#rgpd" target="_blank" rel="noopener"><?= $t('auth.register_privacy') ?></a>.
                </label>
            </div>
            <p class="rg-error rg-error--check" id="accept_terms-error" data-error-for="accept_terms"><?= $e($fieldError('accept_terms')) ?></p>

            <button type="submit" class="ds-btn ds-btn--primary ds-btn--block rg-submit__btn">
                <?= $t('auth.register_submit') ?>
            </button>
            <p class="rg-submit__hint"><?= $t('auth.register_submit_hint') ?></p>
        </div>
    </form>

    <div class="ds-alt">
        <p>
            <?= $t('auth.register_have_account') ?>
            <a href="<?= $e(url('login')) ?>"><?= $t('common.login') ?></a>
        </p>
        <p>
            <?= $t('auth.invite_code') ?>
            <a href="<?= $e(url('join')) ?>"><?= $t('auth.join_community') ?></a>
        </p>
    </div>

    <div class="ds-footer__links" style="margin-top:1.5rem;justify-content:center">
        <?php
        $legal_link_class = '';
        require base_path('views/partials/legal_site_links.php');
        ?>
    </div>
    </div>
</main>
</div>

<dialog class="ds-preview-modal" data-preview-modal aria-labelledby="preview-title" aria-describedby="preview-description">
    <div class="ds-preview-modal__header">
        <span class="ds-preview-notice__badge">Preview ouverte</span>
        <button type="button" class="ds-preview-modal__close" data-preview-close aria-label="Fermer">×</button>
    </div>
    <h2 id="preview-title">Athena évolue avec vos retours</h2>
    <div id="preview-description" class="ds-preview-modal__body">
        <p>Vous accédez à une <strong>preview ouverte</strong> : l’interface et son intuitivité font encore l’objet d’améliorations.</p>
        <p>Le portail web et le mod évoluent séparément. Selon leurs versions, une fonctionnalité peut fonctionner, puis devenir temporairement indisponible jusqu’à leur réalignement.</p>
        <p>Tout est en preview. Chaque conseil ou retour d’expérience nous aide : écrivez-nous à <a href="mailto:no-reply@athena.ttrd.fr">no-reply@athena.ttrd.fr</a>.</p>
    </div>
    <button type="button" class="ds-btn ds-btn--primary ds-btn--block" data-preview-close>J’ai compris, continuer</button>
</dialog>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= $e(asset_url('assets/js/auth_forms.js')) ?>"></script>
<script defer src="<?= $e(asset_url('assets/js/auth_register.js')) ?>"></script>
</body>
</html>
