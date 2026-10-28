<?php
$error = $error ?? \App\Core\Session::getFlash('error');
$success = $success ?? \App\Core\Session::getFlash('success');
$title = $title ?? __('auth.title_forgot');
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
    <?php $authAside = __('auth.forgot_aside'); require base_path('views/partials/auth/visual.php'); ?>

    <main class="ds-main" id="contenu">
    <div class="ds-main__inner au-inner">
    <p class="ds-kicker"><?= $t('auth.kicker') ?></p>
    <h1 class="ds-title"><?= $t('auth.title_forgot') ?></h1>
    <p class="ds-lead rg-lead"><?= $t('auth.forgot_lead') ?></p>

    <div class="ds-alert-stack">
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($success): ?>
            <?php $flash_variant = 'success'; $flash_message = $success; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= htmlspecialchars(url('forgot-password'), ENT_QUOTES, 'UTF-8') ?>" class="ds-form rg-form" data-busy-form>
        <?= \App\Core\Csrf::field() ?>
        <section class="rg-section au-card" aria-label="<?= $t('auth.forgot_card_title') ?>">
            <div class="rg-field">
                <label for="email" class="rg-label"><?= $t('auth.email') ?></label>
                <input type="email" name="email" id="email" required autocomplete="email" autocapitalize="none" spellcheck="false"
                       class="rg-input"
                       aria-describedby="forgot-email-hint"
                       placeholder="<?= $t('auth.placeholder_email') ?>"
                       data-lowercase="email" autofocus>
                <p class="rg-hint" id="forgot-email-hint"><?= $t('auth.forgot_email_hint') ?> <?= htmlspecialchars(sprintf(__('auth.forgot_ttl_hint'), $hoursValid), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--block au-btn au-btn--primary" data-busy-label="<?= $t('auth.forgot_busy') ?>"><?= $t('auth.send_link') ?></button>
        </section>
    </form>

    <div class="ds-alt au-alt">
        <p>
            <a class="au-link" href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>">← <?= $t('auth.back_to_login') ?></a>
        </p>
        <p>
            <?= $t('auth.no_account') ?>
            <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>"><?= $t('auth.create_account_link') ?></a>
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
