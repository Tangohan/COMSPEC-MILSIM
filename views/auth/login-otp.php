<?php
$base = url('');
$error = \App\Core\Session::getFlash('error');
$info = \App\Core\Session::getFlash('info');
$emailMasked = $emailMasked ?? '—';
$expiresAt = (int) ($expiresAt ?? 0);
$title = $title ?? __('auth.title_otp');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$brandText = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
$channel = (string) ($channel ?? 'email');
$isTotp = $channel === 'totp';
$canFallbackEmail = !empty($canFallbackEmail ?? false);
$canFallbackTotp = !empty($canFallbackTotp ?? false);
$canResend = array_key_exists('canResend', get_defined_vars()) ? !empty($canResend) : !$isTotp;
$emailSafe = htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= $brandText ?></title>
    <meta name="theme-color" content="#0b3d38">
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/dsfr-service.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php $GLOBALS['__dsfr_service_css'] = true; ?>
</head>
<body class="ds-page">
<a class="ds-skip" href="#contenu"><?= htmlspecialchars(__('common.skip_to_content'), ENT_QUOTES, 'UTF-8') ?></a>

<header class="ds-header">
    <div class="ds-header__band" aria-hidden="true"></div>
    <div class="ds-header__inner">
        <a class="ds-header__brand" href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>">
            <span class="ds-header__service"><?= $brandText ?></span>
            <span class="ds-header__tagline"><?= htmlspecialchars(__('auth.kicker'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <div class="ds-header__tools">
            <a class="ds-header__link" href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('auth.otp_other_account'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</header>

<main class="ds-main" id="contenu">
    <div class="ds-stepper" aria-label="<?= htmlspecialchars(__('auth.otp_step_sr'), ENT_QUOTES, 'UTF-8') ?>">
        <p class="ds-stepper__title"><?= htmlspecialchars(__('auth.otp_step_title'), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="ds-stepper__state"><?= htmlspecialchars(__('auth.otp_step_state'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="ds-stepper__track" aria-hidden="true">
            <span class="ds-stepper__seg is-done"></span>
            <span class="ds-stepper__seg is-current"></span>
        </div>
    </div>

    <h1 class="ds-title"><?= htmlspecialchars(__('auth.title_otp'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="ds-pill"><?= htmlspecialchars($isTotp ? __('auth.otp_channel_totp') : __('auth.otp_channel_email'), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="ds-lead">
        <?= sprintf($isTotp ? __('auth.otp_totp_body') : __('auth.otp_email_body'), '<strong>' . $emailSafe . '</strong>') ?>
    </p>

    <div class="ds-alert-stack">
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($info): ?>
            <?php $flash_variant = 'info'; $flash_message = $info; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= url('login/otp') ?>" id="login-otp-form" class="ds-form" novalidate>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="otp_code" id="otp_code" value="">

        <fieldset class="ds-fieldset">
            <legend class="ds-label"><?= htmlspecialchars(__('auth.otp_code_legend'), ENT_QUOTES, 'UTF-8') ?></legend>
            <p class="ds-hint" id="otp-hint"><?= htmlspecialchars($isTotp ? __('auth.otp_hint_totp') : __('auth.otp_hint_email'), ENT_QUOTES, 'UTF-8') ?></p>
            <div id="otp-boxes" class="ds-otp" role="group" aria-describedby="otp-hint">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <input
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="1"
                    data-otp-index="<?= (int) $i ?>"
                    class="ds-otp__cell otp-cell"
                    aria-label="<?= htmlspecialchars(sprintf(__('auth.otp_digit'), $i + 1), ENT_QUOTES, 'UTF-8') ?>"
                    <?= $i === 0 ? 'autofocus autocomplete="one-time-code"' : '' ?>
                >
                <?php endfor; ?>
            </div>
        </fieldset>

        <div class="ds-btn-row">
            <button type="submit" class="ds-btn ds-btn--primary"><?= htmlspecialchars(__('auth.otp_continue'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>

    <div class="ds-btn-row" style="margin-top:0.75rem">
        <?php if ($canResend): ?>
        <form method="post" action="<?= url('login/otp/resend') ?>">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="ds-btn ds-btn--secondary"><?= htmlspecialchars(__('auth.otp_resend'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <?php endif; ?>

        <?php if ($isTotp && $canFallbackEmail): ?>
        <form method="post" action="<?= url('login/otp/switch') ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="channel" value="email">
            <button type="submit" class="ds-btn ds-btn--secondary"><?= htmlspecialchars(__('auth.otp_fallback_email'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <?php elseif (!$isTotp && $canFallbackTotp): ?>
        <form method="post" action="<?= url('login/otp/switch') ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="channel" value="totp">
            <button type="submit" class="ds-btn ds-btn--secondary"><?= htmlspecialchars(__('auth.otp_fallback_totp'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!$isTotp && $expiresAt > 0): ?>
    <p class="ds-hint" style="margin-top:1.25rem">
        <?= htmlspecialchars(__('auth.otp_valid_until'), ENT_QUOTES, 'UTF-8') ?>
        <time datetime="<?= htmlspecialchars(date('c', $expiresAt), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(date('d/m/Y \à H:i', $expiresAt), ENT_QUOTES, 'UTF-8') ?>
        </time>
    </p>
    <?php endif; ?>
</main>

<footer class="ds-footer">
    <div class="ds-footer__inner">
        <p><?= htmlspecialchars(__('auth.title_otp'), ENT_QUOTES, 'UTF-8') ?></p>
        <a class="ds-header__link" href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('auth.otp_other_account'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</footer>

<script>
(function () {
    var form = document.getElementById('login-otp-form');
    var hidden = document.getElementById('otp_code');
    var cells = Array.prototype.slice.call(document.querySelectorAll('.otp-cell'));
    if (!form || !hidden || cells.length !== 6) {
        return;
    }

    function syncHidden() {
        hidden.value = cells.map(function (c) { return (c.value || '').replace(/\D/g, '').slice(0, 1); }).join('');
    }

    function focusIndex(i) {
        if (i < 0) i = 0;
        if (i > 5) i = 5;
        cells[i].focus();
        try { cells[i].select(); } catch (e) {}
    }

    cells.forEach(function (cell, idx) {
        cell.addEventListener('input', function () {
            var v = (cell.value || '').replace(/\D/g, '');
            if (v.length > 1) {
                var digits = v.slice(0, 6);
                for (var j = 0; j < 6; j++) {
                    cells[j].value = digits.charAt(j) || '';
                }
                syncHidden();
                focusIndex(digits.length >= 6 ? 5 : Math.max(0, digits.length - 1));
                if (digits.length === 6) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
                return;
            }
            cell.value = v;
            syncHidden();
            if (v && idx < 5) focusIndex(idx + 1);
            if (v && idx === 5 && hidden.value.length === 6) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });

        cell.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !cell.value && idx > 0) {
                e.preventDefault();
                cells[idx - 1].value = '';
                syncHidden();
                focusIndex(idx - 1);
            }
            if (e.key === 'ArrowLeft' && idx > 0) {
                e.preventDefault();
                focusIndex(idx - 1);
            }
            if (e.key === 'ArrowRight' && idx < 5) {
                e.preventDefault();
                focusIndex(idx + 1);
            }
        });

        cell.addEventListener('paste', function (e) {
            e.preventDefault();
            var raw = (e.clipboardData || window.clipboardData).getData('text') || '';
            var digits = raw.replace(/\D/g, '').slice(0, 6);
            for (var j = 0; j < 6; j++) {
                cells[j].value = digits.charAt(j) || '';
            }
            syncHidden();
            focusIndex(digits.length >= 6 ? 5 : Math.max(0, digits.length - 1));
            if (digits.length === 6) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    });

    form.addEventListener('submit', function (e) {
        syncHidden();
        if (!/^\d{6}$/.test(hidden.value)) {
            e.preventDefault();
            focusIndex(0);
        }
    });

    syncHidden();
})();
</script>
</body>
</html>
