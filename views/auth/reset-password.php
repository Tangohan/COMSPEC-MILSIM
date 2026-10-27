<?php
$token = $token ?? '';
$error = $error ?? \App\Core\Session::getFlash('error');
$title = $title ?? __('auth.title_reset');
$hoursValid = max(1, (int) ($hoursValid ?? 2));
$t = static fn (string $key): string => htmlspecialchars(__($key), ENT_QUOTES, 'UTF-8');
$athena_header_current = 'login';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
<?php $authTitle = $title; require base_path('views/partials/auth/head.php'); ?>
</head>
<body class="ds-page ds-page--split ds-page--auth">
<a class="ds-skip" href="#contenu"><?= $t('common.skip_to_content') ?></a>
<?php require base_path('views/partials/athena_header_guest.php'); ?>

<div class="ds-split">
    <?php $authAside = __('auth.reset_aside'); require base_path('views/partials/auth/visual.php'); ?>

    <main class="ds-main" id="contenu">
    <div class="ds-main__inner au-inner">
    <p class="ds-kicker"><?= $t('auth.kicker') ?></p>
    <h1 class="ds-title"><?= $t('auth.title_reset') ?></h1>
    <p class="ds-lead rg-lead"><?= $t('auth.reset_lead') ?></p>

    <div class="ds-alert-stack">
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= htmlspecialchars(url('reset-password'), ENT_QUOTES, 'UTF-8') ?>" class="ds-form rg-form" data-register-form data-strength-form data-busy-form>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') ?>">
        <section class="rg-section au-card">
            <div class="rg-field">
                <label for="password" class="rg-label"><?= $t('auth.reset_password') ?></label>
                <?php $pwId = 'password'; $pwName = 'password'; $pwAutocomplete = 'new-password'; $pwDescribedBy = 'reset-password-hint'; $pwAttrs = 'minlength="8" autofocus data-strength-source'; require base_path('views/partials/auth/password_input.php'); ?>
                <div class="rg-meter" data-strength-meter data-level="0" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                <p class="rg-hint" id="reset-password-hint" data-strength-text aria-live="polite"
                   data-default="<?= $t('auth.register_password_hint') ?>"
                   data-too-short="<?= $t('auth.register_pw_too_short') ?>"
                   data-weak="<?= $t('auth.register_pw_weak') ?>"
                   data-fair="<?= $t('auth.register_pw_fair') ?>"
                   data-good="<?= $t('auth.register_pw_good') ?>"
                   data-strong="<?= $t('auth.register_pw_strong') ?>"><?= $t('auth.register_password_hint') ?></p>
            </div>
            <div class="rg-field">
                <label for="password_confirmation" class="rg-label"><?= $t('auth.register_password_confirm') ?></label>
                <?php $pwId = 'password_confirmation'; $pwName = 'password_confirmation'; $pwAutocomplete = 'new-password'; $pwDescribedBy = 'reset-confirm-hint'; $pwAttrs = 'minlength="8" data-password-confirm-of="password" data-password-mismatch="' . $t('auth.flash_passwords_mismatch') . '"'; require base_path('views/partials/auth/password_input.php'); ?>
                <p class="rg-hint" id="reset-confirm-hint" data-match-text aria-live="polite"
                   data-default="<?= $t('auth.register_password_confirm_ph') ?>"
                   data-match="<?= $t('auth.reset_match_ok') ?>"
                   data-mismatch="<?= $t('auth.reset_match_ko') ?>"><?= $t('auth.register_password_confirm_ph') ?></p>
            </div>
            <p class="rg-hint"><?= htmlspecialchars(sprintf(__('auth.reset_ttl_note'), $hoursValid), ENT_QUOTES, 'UTF-8') ?></p>
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--block au-btn au-btn--primary" data-busy-label="<?= $t('auth.reset_busy') ?>"><?= $t('auth.reset_submit') ?></button>
        </section>
    </form>

    <div class="ds-alt au-alt">
        <p>
            <a class="au-link" href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>">← <?= $t('auth.back_to_login') ?></a>
        </p>
        <p>
            <a href="<?= htmlspecialchars(url('forgot-password'), ENT_QUOTES, 'UTF-8') ?>"><?= $t('auth.reset_request_again') ?></a>
        </p>
    </div>
    </div>
    </main>
</div>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/auth_forms.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
