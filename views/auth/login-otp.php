<?php
$error = \App\Core\Session::getFlash('error');
$info = \App\Core\Session::getFlash('info');
$emailMasked = $emailMasked ?? '—';
$expiresAt = (int) ($expiresAt ?? 0);
$channel = (string) ($channel ?? 'email');
$isTotp = $channel === 'totp';
$canFallbackEmail = !empty($canFallbackEmail ?? false);
$canFallbackTotp = !empty($canFallbackTotp ?? false);
$canResend = array_key_exists('canResend', get_defined_vars()) ? !empty($canResend) : !$isTotp;
$canUseRecovery = !empty($canUseRecovery ?? false);
$canRemember = !empty($canRemember ?? false);
$generatedAt = (int) ($generatedAt ?? 0);
$resendIntervalSec = (int) ($resendIntervalSec ?? 60);
$trustedDays = \App\Services\Auth\TrustedDeviceService::TTL_DAYS;
$emailSafe = htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8');
$t = static fn (string $key): string => htmlspecialchars(__($key), ENT_QUOTES, 'UTF-8');
$athena_header_current = 'login';
$resendAt = $generatedAt > 0 ? $generatedAt + $resendIntervalSec : 0;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
<?php $authTitle = $title ?? __('auth.title_otp'); require base_path('views/partials/auth/head.php'); ?>
</head>
<body class="ds-page ds-page--split ds-page--auth">
<a class="ds-skip" href="#contenu"><?= $t('common.skip_to_content') ?></a>
<?php require base_path('views/partials/athena_header_guest.php'); ?>

<div class="ds-split">
    <?php $authAside = __('auth.otp_aside'); require base_path('views/partials/auth/visual.php'); ?>

    <main class="ds-main" id="contenu">
    <div class="ds-main__inner au-inner">
    <ol class="au-steps" aria-label="<?= $t('auth.otp_step_sr') ?>">
        <li class="au-steps__item is-done"><span class="au-steps__dot" aria-hidden="true">✓</span><?= $t('auth.otp_step_password') ?></li>
        <li class="au-steps__item is-current" aria-current="step"><span class="au-steps__dot" aria-hidden="true">2</span><?= $t('auth.otp_step_code') ?></li>
    </ol>

    <p class="ds-kicker"><?= $t('auth.kicker') ?></p>
    <h1 class="ds-title"><?= $t('auth.title_otp') ?></h1>
    <p class="au-method">
        <?php if ($isTotp): ?>
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><rect x="6" y="2.5" width="12" height="19" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M11 18h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <?php else: ?>
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m3.5 6.5 8.5 6 8.5-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        <?php endif; ?>
        <?= $t($isTotp ? 'auth.otp_channel_totp' : 'auth.otp_channel_email') ?>
    </p>
    <p class="ds-lead rg-lead">
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

    <form method="post" action="<?= url('login/otp') ?>" id="login-otp-form" class="ds-form rg-form" novalidate>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="otp_code" id="otp_code" value="">

        <section class="rg-section au-card">
            <?php if ($canRemember): ?>
            <label class="au-remember">
                <input type="checkbox" name="remember_device" value="1" data-remember-device>
                <span>
                    <span class="au-remember__title"><?= htmlspecialchars(sprintf(__('auth.otp_remember_label'), $trustedDays), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="au-remember__hint"><?= $t('auth.otp_remember_hint') ?></span>
                </span>
            </label>
            <?php endif; ?>

            <fieldset class="ds-fieldset">
                <legend class="rg-label"><?= $t('auth.otp_code_legend') ?></legend>
                <div id="otp-boxes" class="ds-otp au-otp" role="group" aria-describedby="otp-hint">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="1"
                        data-otp-index="<?= (int) $i ?>"
                        class="ds-otp__cell otp-cell"
                        aria-label="<?= htmlspecialchars(sprintf(__('auth.otp_digit'), $i + 1), ENT_QUOTES, 'UTF-8') ?>"
                        <?= $i === 0 ? 'autofocus autocomplete="one-time-code"' : 'autocomplete="off"' ?>
                    >
                    <?php endfor; ?>
                </div>
                <p class="rg-hint" id="otp-hint"><?= $t($isTotp ? 'auth.otp_hint_totp' : 'auth.otp_hint_email') ?></p>
                <?php if (!$isTotp && $expiresAt > 0): ?>
                <p class="rg-hint au-countdown" data-expires-at="<?= (int) $expiresAt ?>"
                   data-label="<?= $t('auth.otp_expires_in') ?>"
                   data-expired="<?= $t('auth.otp_expired_now') ?>">
                    <?= $t('auth.otp_valid_until') ?>
                    <time datetime="<?= htmlspecialchars(date('c', $expiresAt), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(date('d/m/Y H:i', $expiresAt), ENT_QUOTES, 'UTF-8') ?></time>
                </p>
                <?php endif; ?>
            </fieldset>

            <button type="submit" class="ds-btn ds-btn--primary ds-btn--block au-btn au-btn--primary" data-busy-label="<?= $t('auth.otp_busy') ?>"><?= $t('auth.otp_continue') ?></button>
        </section>
    </form>

    <section class="au-more" aria-labelledby="otp-more-title">
        <h2 class="au-more__title" id="otp-more-title"><?= $t('auth.otp_other_methods') ?></h2>
        <div class="au-more__actions">
            <?php if ($canResend): ?>
            <form method="post" action="<?= url('login/otp/resend') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="ds-btn ds-btn--secondary au-btn" data-resend-at="<?= (int) $resendAt ?>"
                        data-resend-label="<?= $t('auth.otp_resend') ?>"
                        data-resend-wait="<?= $t('auth.otp_resend_in') ?>"><?= $t('auth.otp_resend') ?></button>
            </form>
            <?php endif; ?>

            <?php if ($isTotp && $canFallbackEmail): ?>
            <form method="post" action="<?= url('login/otp/switch') ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="channel" value="email">
                <button type="submit" class="ds-btn ds-btn--secondary au-btn"><?= $t('auth.otp_fallback_email') ?></button>
            </form>
            <?php elseif (!$isTotp && $canFallbackTotp): ?>
            <form method="post" action="<?= url('login/otp/switch') ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="channel" value="totp">
                <button type="submit" class="ds-btn ds-btn--secondary au-btn"><?= $t('auth.otp_fallback_totp') ?></button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($canUseRecovery): ?>
        <details class="rg-optional au-recovery" id="secours">
            <summary class="rg-optional__summary">
                <span class="rg-optional__badge">ABCD-EFGH</span>
                <span class="rg-optional__label"><?= $t('auth.otp_recovery_toggle') ?></span>
                <svg class="rg-optional__chevron" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <form method="post" action="<?= url('login/otp') ?>" class="rg-optional__body" data-busy-form>
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="use_recovery" value="1">
                <div class="rg-field">
                    <label class="rg-label" for="recovery_code"><?= $t('auth.otp_recovery_label') ?></label>
                    <input type="text" name="recovery_code" id="recovery_code" class="rg-input rg-input--code au-code-input" required
                           autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="12"
                           placeholder="ABCD-EFGH" aria-describedby="recovery-hint">
                    <p class="rg-hint" id="recovery-hint"><?= $t('auth.otp_recovery_hint') ?></p>
                </div>
                <?php if ($canRemember): ?>
                <input type="hidden" name="remember_device" value="" data-remember-mirror>
                <?php endif; ?>
                <button type="submit" class="ds-btn ds-btn--secondary au-btn" data-busy-label="<?= $t('auth.otp_busy') ?>"><?= $t('auth.otp_recovery_submit') ?></button>
            </form>
        </details>
        <?php endif; ?>

        <p class="au-more__note"><?= $t('auth.otp_lost_access') ?></p>
        <p class="au-more__note">
            <a class="au-link" href="<?= htmlspecialchars(url('login') . '?autre-compte=1', ENT_QUOTES, 'UTF-8') ?>"><?= $t('auth.otp_other_account') ?></a>
        </p>
    </section>
    </div>
    </main>
</div>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/auth_forms.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

<script>
(function () {
    var form = document.getElementById('login-otp-form');
    var hidden = document.getElementById('otp_code');
    var cells = Array.prototype.slice.call(document.querySelectorAll('.otp-cell'));
    if (!form || !hidden || cells.length !== 6) {
        return;
    }

    // Un seul envoi : l'envoi automatique au 6e chiffre suivi d'un clic sur « Continuer »
    // (ou de la touche Entrée) postait deux fois le code. Le premier envoi connectait et
    // renouvelait la session ; le second, parti avec l'ancien cookie, tombait sur
    // « Session expirée » puis « Ce code a expiré », et le navigateur n'affichait que lui.
    var submitting = false;
    var submitBtn = form.querySelector('button[type="submit"]');

    function submitOnce() {
        if (submitting) return;
        if (form.requestSubmit) {
            form.requestSubmit();
        } else if (form.dispatchEvent(new Event('submit', { cancelable: true }))) {
            form.submit();
        }
    }

    function syncHidden() {
        hidden.value = cells.map(function (c) { return (c.value || '').replace(/\D/g, '').slice(0, 1); }).join('');
        cells.forEach(function (c) { c.classList.toggle('is-filled', (c.value || '') !== ''); });
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
                    submitOnce();
                }
                return;
            }
            cell.value = v;
            syncHidden();
            if (v && idx < 5) focusIndex(idx + 1);
            if (v && idx === 5 && hidden.value.length === 6) {
                submitOnce();
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
                submitOnce();
            }
        });
    });

    form.addEventListener('submit', function (e) {
        syncHidden();
        if (submitting) {
            e.preventDefault();
            return;
        }
        if (!/^\d{6}$/.test(hidden.value)) {
            e.preventDefault();
            form.classList.remove('is-shaking');
            void form.offsetWidth;
            form.classList.add('is-shaking');
            focusIndex(hidden.value.length);
            return;
        }
        submitting = true;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
            var busy = submitBtn.getAttribute('data-busy-label');
            if (busy) submitBtn.textContent = busy;
        }
    });

    // Retour arrière du navigateur (page restaurée depuis le cache) : réautoriser l'envoi.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            submitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.removeAttribute('aria-busy');
            }
        }
    });

    // Lien direct « #secours » (erreur sur un code de secours) : rouvrir le volet.
    if (window.location.hash === '#secours') {
        var rec = document.getElementById('secours');
        if (rec) { rec.open = true; var input = document.getElementById('recovery_code'); if (input) input.focus(); }
    }

    syncHidden();
})();
</script>
</body>
</html>
