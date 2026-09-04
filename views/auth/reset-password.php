<?php
$base = url('');
$token = $token ?? '';
$error = $error ?? \App\Core\Session::getFlash('error');
$title = $title ?? __('auth.title_reset');
$hoursValid = max(1, (int) ($hoursValid ?? 2));
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$brandText = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
$athena_header_current = 'login';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= $brandText ?></title>
    <meta name="theme-color" content="#050505">
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=Source+Sans+3:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
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
        <p class="ds-visual__caption"><?= htmlspecialchars(__('auth.reset_aside'), ENT_QUOTES, 'UTF-8') ?></p>
    </aside>

    <main class="ds-main" id="contenu">
    <div class="ds-main__inner">
    <p class="ds-kicker"><?= htmlspecialchars(__('auth.kicker'), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="ds-title"><?= htmlspecialchars(__('auth.title_reset'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="ds-lead"><?= htmlspecialchars(__('auth.reset_lead'), ENT_QUOTES, 'UTF-8') ?></p>

    <div class="ds-alert-stack">
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= htmlspecialchars(url('reset-password'), ENT_QUOTES, 'UTF-8') ?>" class="ds-form" data-register-form>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label for="password" class="ds-label"><?= htmlspecialchars(__('auth.reset_password'), ENT_QUOTES, 'UTF-8') ?></label>
            <p class="ds-hint" id="reset-password-hint"><?= htmlspecialchars(__('auth.reset_password_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="ds-password">
                <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password"
                       class="ds-password__field"
                       aria-describedby="reset-password-hint"
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
            <label for="password_confirmation" class="ds-label"><?= htmlspecialchars(__('auth.register_password_confirm'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="ds-password">
                <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8" autocomplete="new-password"
                       class="ds-password__field"
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
        <p class="ds-hint"><?= htmlspecialchars(sprintf(__('auth.reset_ttl_note'), $hoursValid), ENT_QUOTES, 'UTF-8') ?></p>
        <button type="submit" class="ds-btn ds-btn--primary ds-btn--block"><?= htmlspecialchars(__('auth.reset_submit'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>

    <div class="ds-alt">
        <p>
            <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('auth.back_to_login'), ENT_QUOTES, 'UTF-8') ?></a>
        </p>
        <p>
            <a href="<?= htmlspecialchars(url('forgot-password'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('auth.reset_request_again'), ENT_QUOTES, 'UTF-8') ?></a>
        </p>
    </div>
    </div>
    </main>
</div>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/js/auth_forms.js"></script>
</body>
</html>
