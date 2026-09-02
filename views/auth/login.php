<?php
$base = url('');
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$warning = \App\Core\Session::getFlash('warning');
$info = \App\Core\Session::getFlash('info');
$pendingVerificationEmail = \App\Core\Session::get('pending_verification_email');
$title = $title ?? __('auth.title_login');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$brandText = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
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
        <p class="ds-visual__caption"><?= htmlspecialchars(__('auth.login_aside'), ENT_QUOTES, 'UTF-8') ?></p>
    </aside>

    <main class="ds-main" id="contenu">
    <div class="ds-main__inner">
    <p class="ds-kicker"><?= htmlspecialchars(__('auth.kicker'), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="ds-title"><?= htmlspecialchars(__('auth.title_login'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="ds-lead"><?= htmlspecialchars(__('auth.intro'), ENT_QUOTES, 'UTF-8') ?></p>

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
        <?php if ($info): ?>
            <?php $flash_variant = 'info'; $flash_message = $info; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <?php if ($pendingVerificationEmail !== null && $pendingVerificationEmail !== ''): ?>
    <div class="ds-callout">
        <form method="post" action="<?= url('resend-verification') ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="email" value="<?= htmlspecialchars((string) $pendingVerificationEmail, ENT_QUOTES, 'UTF-8') ?>">
            <p><?= htmlspecialchars(__('auth.email_unconfirmed'), ENT_QUOTES, 'UTF-8') ?></p>
            <button type="submit" class="ds-btn ds-btn--secondary"><?= htmlspecialchars(__('auth.resend_confirm'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= url('login') ?>" class="ds-form">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="email" class="ds-label"><?= htmlspecialchars(__('auth.email'), ENT_QUOTES, 'UTF-8') ?></label>
            <p class="ds-hint" id="email-hint"><?= htmlspecialchars(__('auth.email_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <input type="email" name="email" id="email" required autocomplete="email" autocapitalize="none" spellcheck="false"
                   class="ds-input"
                   aria-describedby="email-hint"
                   data-lowercase="email">
        </div>
        <div>
            <div class="ds-label-row">
                <label for="password" class="ds-label"><?= htmlspecialchars(__('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
                <a class="ds-link-muted" href="<?= url('forgot-password') ?>">
                    <?= htmlspecialchars(__('auth.forgot_link'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
            <p class="ds-hint" id="password-hint"><?= htmlspecialchars(__('auth.password_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="ds-password">
                <input type="password" name="password" id="password" required autocomplete="current-password"
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
        <button type="submit" class="ds-btn ds-btn--primary ds-btn--block"><?= htmlspecialchars(__('auth.submit_login'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>

    <div class="ds-alt">
        <p>
            <?= htmlspecialchars(__('auth.no_account'), ENT_QUOTES, 'UTF-8') ?>
            <a href="<?= url('register') ?>"><?= htmlspecialchars(__('auth.create_account_link'), ENT_QUOTES, 'UTF-8') ?></a>
        </p>
        <p>
            <?= htmlspecialchars(__('auth.invite_code'), ENT_QUOTES, 'UTF-8') ?>
            <a href="<?= url('join') ?>"><?= htmlspecialchars(__('auth.join_community'), ENT_QUOTES, 'UTF-8') ?></a>
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
