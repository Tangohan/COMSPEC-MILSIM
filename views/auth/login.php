<?php
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$warning = \App\Core\Session::getFlash('warning');
$info = \App\Core\Session::getFlash('info');
$pendingVerificationEmail = \App\Core\Session::get('pending_verification_email');
$t = static fn (string $key): string => htmlspecialchars(__($key), ENT_QUOTES, 'UTF-8');
$athena_header_current = 'login';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
<?php $authTitle = $title ?? __('auth.title_login'); require base_path('views/partials/auth/head.php'); ?>
</head>
<body class="ds-page ds-page--split ds-page--auth">
<a class="ds-skip" href="#contenu"><?= $t('common.skip_to_content') ?></a>
<?php require base_path('views/partials/athena_header_guest.php'); ?>

<div class="ds-split">
    <?php $authAside = __('auth.login_aside'); require base_path('views/partials/auth/visual.php'); ?>

    <main class="ds-main" id="contenu">
    <div class="ds-main__inner au-inner">
    <p class="ds-kicker"><?= $t('auth.kicker') ?></p>
    <h1 class="ds-title"><?= $t('auth.title_login') ?></h1>
    <p class="ds-lead rg-lead"><?= $t('auth.intro') ?></p>

    <div class="ds-alert-stack">
        <?php if (!empty($GLOBALS['__maintenance_admin_login'])): ?>
            <?php $flash_variant = 'warning'; $flash_message = 'Athena est en maintenance : seuls les administrateurs de la plateforme peuvent se connecter. Les autres comptes sont déconnectés jusqu’à la réouverture.'; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
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
    <div class="ds-callout au-callout">
        <form method="post" action="<?= url('resend-verification') ?>" data-busy-form>
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="email" value="<?= htmlspecialchars((string) $pendingVerificationEmail, ENT_QUOTES, 'UTF-8') ?>">
            <p><?= $t('auth.email_unconfirmed') ?></p>
            <button type="submit" class="ds-btn ds-btn--secondary au-btn"><?= $t('auth.resend_confirm') ?></button>
        </form>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= url('login') ?>" class="ds-form rg-form" data-busy-form>
        <?= \App\Core\Csrf::field() ?>
        <section class="rg-section au-card" aria-label="<?= $t('auth.login_card_title') ?>">
            <div class="rg-field">
                <label for="email" class="rg-label"><?= $t('auth.email') ?></label>
                <input type="email" name="email" id="email" required autocomplete="username" autocapitalize="none" spellcheck="false"
                       value="<?= htmlspecialchars((string) ($oldEmail ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="rg-input"
                       placeholder="<?= $t('auth.placeholder_email') ?>"
                       aria-describedby="email-hint"
                       data-lowercase="email"<?= ($oldEmail ?? '') === '' ? ' autofocus' : '' ?>>
                <p class="rg-hint" id="email-hint"><?= $t('auth.email_hint') ?></p>
            </div>
            <div class="rg-field">
                <div class="au-label-row">
                    <label for="password" class="rg-label"><?= $t('auth.password') ?></label>
                    <a class="au-link" href="<?= url('forgot-password') ?>"><?= $t('auth.forgot_link') ?></a>
                </div>
                <?php $pwId = 'password'; $pwName = 'password'; $pwAutocomplete = 'current-password'; $pwAttrs = ($oldEmail ?? '') !== '' ? 'autofocus' : ''; require base_path('views/partials/auth/password_input.php'); ?>
            </div>
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--block au-btn au-btn--primary" data-busy-label="<?= $t('auth.submit_login_busy') ?>"><?= $t('auth.submit_login') ?></button>
            <p class="au-trust">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M12 3 4.5 6v5.5c0 4.5 3.1 8.4 7.5 9.5 4.4-1.1 7.5-5 7.5-9.5V6L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m8.8 12 2.2 2.2 4.2-4.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><?= $t('auth.login_trust') ?></span>
            </p>
        </section>
    </form>

    <div class="au-divider"><span><?= $t('auth.login_new_here') ?></span></div>
    <div class="au-alt-actions">
        <a class="ds-btn ds-btn--secondary au-btn" href="<?= url('register') ?>"><?= $t('auth.login_create_cta') ?></a>
        <a class="ds-btn ds-btn--secondary au-btn" href="<?= url('join') ?>"><?= $t('auth.login_join_cta') ?></a>
    </div>
    </div>
    </main>
</div>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/auth_forms.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
