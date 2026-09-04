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
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$warning = \App\Core\Session::getFlash('warning');
$athena_header_current = 'register';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="theme-color" content="#0b3d38">
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/athena-header.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/dsfr-service.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php $GLOBALS['__dsfr_service_css'] = true; ?>
</head>
<body class="ds-page ds-page--split">
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
    <p class="ds-kicker"><?= htmlspecialchars(__('auth.register_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="ds-title"><?= htmlspecialchars(__('auth.register_heading'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="ds-lead"><?= htmlspecialchars(__('auth.register_sub'), ENT_QUOTES, 'UTF-8') ?></p>

    <button type="button" class="ds-preview-notice" data-preview-open aria-haspopup="dialog">
        <span class="ds-preview-notice__badge">Preview ouverte</span>
        <span>À lire avant de créer votre compte</span>
    </button>

    <?php if ($prefillSlug !== ''): ?>
    <p class="ds-pill" role="status">
        <?= htmlspecialchars(__('auth.register_space_targeted'), ENT_QUOTES, 'UTF-8') ?>
        <?= htmlspecialchars($prefillSlug, ENT_QUOTES, 'UTF-8') ?>
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

    <form method="post" action="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="ds-form" novalidate data-register-form>
        <?= \App\Core\Csrf::field() ?>

        <div>
            <label class="ds-label" for="community_code">
                <?= htmlspecialchars(__('auth.register_invite'), ENT_QUOTES, 'UTF-8') ?>
                <span class="ds-label-optional"><?= htmlspecialchars(__('auth.register_optional'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <p class="ds-hint" id="community_code-hint">
                <?= htmlspecialchars(__('auth.register_invite_optional_hint'), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <input id="community_code" type="text" name="community_code" maxlength="64" autocomplete="off"
                   placeholder="<?= htmlspecialchars(__('auth.register_invite_ph'), ENT_QUOTES, 'UTF-8') ?>"
                   value="<?= htmlspecialchars($prefillCc, ENT_QUOTES, 'UTF-8') ?>"
                   class="ds-input"
                   aria-describedby="community_code-hint"
                   style="text-transform:uppercase;letter-spacing:0.06em">
        </div>

        <div>
            <label class="ds-label" for="email"><?= htmlspecialchars(__('auth.email'), ENT_QUOTES, 'UTF-8') ?></label>
            <p class="ds-hint" id="email-hint"><?= htmlspecialchars(__('auth.register_email_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <input id="email" type="email" name="email" data-lowercase="email" required autocomplete="email"
                   autocapitalize="none" spellcheck="false"
                   placeholder="<?= htmlspecialchars(__('auth.placeholder_email'), ENT_QUOTES, 'UTF-8') ?>"
                   value="<?= $val($old, 'email') ?>"
                   class="ds-input"
                   aria-describedby="email-hint">
        </div>

        <fieldset class="ds-fieldset">
            <legend class="ds-legend"><?= htmlspecialchars(__('auth.register_identity'), ENT_QUOTES, 'UTF-8') ?></legend>
            <p class="ds-hint" id="identity-hint"><?= htmlspecialchars(__('auth.register_identity_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="ds-grid-2">
                <div>
                    <label class="ds-label" for="first_name"><?= htmlspecialchars(__('auth.register_first_name'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="first_name" type="text" name="first_name" required minlength="1" maxlength="100"
                           autocomplete="given-name"
                           placeholder="<?= htmlspecialchars(__('auth.register_first_name_ph'), ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= $val($old, 'first_name') ?>"
                           class="ds-input"
                           aria-describedby="identity-hint">
                </div>
                <div>
                    <label class="ds-label" for="last_name"><?= htmlspecialchars(__('auth.register_last_name'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="last_name" type="text" name="last_name" required minlength="1" maxlength="100"
                           autocomplete="family-name"
                           placeholder="<?= htmlspecialchars(__('auth.register_last_name_ph'), ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= $val($old, 'last_name') ?>"
                           class="ds-input">
                </div>
            </div>
        </fieldset>

        <div class="ds-grid-2">
            <div>
                <label class="ds-label" for="discord_handle">
                    <?= htmlspecialchars(__('auth.register_discord'), ENT_QUOTES, 'UTF-8') ?>
                    <span class="ds-label-optional"><?= htmlspecialchars(__('auth.register_optional'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <input id="discord_handle" type="text" name="discord_handle" maxlength="120" autocomplete="off"
                       placeholder="<?= htmlspecialchars(__('auth.register_discord_ph'), ENT_QUOTES, 'UTF-8') ?>"
                       value="<?= $val($old, 'discord_handle') ?>"
                       class="ds-input">
            </div>
            <div>
                <label class="ds-label" for="steam_profile">
                    <?= htmlspecialchars(__('auth.register_steam'), ENT_QUOTES, 'UTF-8') ?>
                    <span class="ds-label-optional"><?= htmlspecialchars(__('auth.register_optional'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <p class="ds-hint" id="steam-hint"><?= htmlspecialchars(__('auth.register_steam_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                <input id="steam_profile" type="text" name="steam_profile" maxlength="512" autocomplete="off"
                       placeholder="<?= htmlspecialchars(__('auth.register_steam'), ENT_QUOTES, 'UTF-8') ?>"
                       value="<?= $val($old, 'steam_profile') ?>"
                       class="ds-input"
                       aria-describedby="steam-hint">
            </div>
        </div>

        <div class="ds-grid-2">
            <div>
                <label class="ds-label" for="password"><?= htmlspecialchars(__('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
                <p class="ds-hint" id="password-hint"><?= htmlspecialchars(__('auth.register_password_ph'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="ds-password">
                    <input type="password" id="password" name="password" required minlength="8"
                           autocomplete="new-password"
                           class="ds-password__field"
                           aria-describedby="password-hint"
                           data-password-input>
                    <button type="button" class="ds-password__toggle" data-password-toggle="password"
                            aria-controls="password"
                            data-label-show="<?= htmlspecialchars(__('auth.show_password'), ENT_QUOTES, 'UTF-8') ?>"
                            data-label-hide="<?= htmlspecialchars(__('auth.hide_password'), ENT_QUOTES, 'UTF-8') ?>"
                            aria-label="<?= htmlspecialchars(__('auth.show_password'), ENT_QUOTES, 'UTF-8') ?>">
                        <span data-password-toggle-label><?= htmlspecialchars(__('auth.show_password'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </div>
            <div>
                <label class="ds-label" for="password_confirmation"><?= htmlspecialchars(__('auth.register_password_confirm'), ENT_QUOTES, 'UTF-8') ?></label>
                <p class="ds-hint" id="password_confirmation-hint"><?= htmlspecialchars(__('auth.register_password_confirm_ph'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="ds-password">
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8"
                           autocomplete="new-password"
                           class="ds-password__field"
                           aria-describedby="password_confirmation-hint"
                           data-password-confirm-of="password"
                           data-password-mismatch="<?= htmlspecialchars(__('auth.flash_passwords_mismatch'), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="ds-password__toggle" data-password-toggle="password_confirmation"
                            aria-controls="password_confirmation"
                            data-label-show="<?= htmlspecialchars(__('auth.show_password'), ENT_QUOTES, 'UTF-8') ?>"
                            data-label-hide="<?= htmlspecialchars(__('auth.hide_password'), ENT_QUOTES, 'UTF-8') ?>"
                            aria-label="<?= htmlspecialchars(__('auth.show_password'), ENT_QUOTES, 'UTF-8') ?>">
                        <span data-password-toggle-label><?= htmlspecialchars(__('auth.show_password'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="ds-check">
            <input type="checkbox" name="accept_terms" id="accept_terms" value="1" required
                   <?= !empty($old['accept_terms']) ? 'checked' : '' ?>>
            <label for="accept_terms">
                <?= htmlspecialchars(__('auth.register_accept_prefix'), ENT_QUOTES, 'UTF-8') ?>
                <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#cgu" target="_blank" rel="noopener"><?= htmlspecialchars(__('auth.register_terms'), ENT_QUOTES, 'UTF-8') ?></a>
                <?= htmlspecialchars(__('auth.register_accept_and'), ENT_QUOTES, 'UTF-8') ?>
                <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#rgpd" target="_blank" rel="noopener"><?= htmlspecialchars(__('auth.register_privacy'), ENT_QUOTES, 'UTF-8') ?></a>.
            </label>
        </div>

        <button type="submit" class="ds-btn ds-btn--primary ds-btn--block">
            <?= htmlspecialchars(__('auth.register_submit'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </form>

    <div class="ds-alt">
        <p>
            <?= htmlspecialchars(__('auth.register_have_account'), ENT_QUOTES, 'UTF-8') ?>
            <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.login'), ENT_QUOTES, 'UTF-8') ?></a>
        </p>
        <p>
            <?= htmlspecialchars(__('auth.invite_code'), ENT_QUOTES, 'UTF-8') ?>
            <a href="<?= htmlspecialchars(url('join'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('auth.join_community'), ENT_QUOTES, 'UTF-8') ?></a>
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
<script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/js/auth_forms.js"></script>
</body>
</html>
