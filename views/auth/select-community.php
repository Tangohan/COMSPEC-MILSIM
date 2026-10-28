<?php
/** @var string $email */
/** @var list<array{tenant_id: int, user_id: int, tenant_name: string, tenant_slug: string}> $candidates */
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$title = $title ?? __('auth.title_select_community');
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
    <?php $authAside = __('auth.select_community_aside'); require base_path('views/partials/auth/visual.php'); ?>

    <main class="ds-main" id="contenu">
    <div class="ds-main__inner au-inner">
    <p class="ds-kicker"><?= $t('auth.select_community_kicker') ?></p>
    <h1 class="ds-title"><?= $t('auth.title_select_community') ?></h1>
    <p class="ds-lead rg-lead"><?= $t('auth.select_community_lead') ?></p>
    <p class="au-method"><?= htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') ?></p>

    <div class="ds-alert-stack">
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($success): ?>
            <?php $flash_variant = 'success'; $flash_message = $success; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= url('login/select-community') ?>" class="ds-form rg-form" data-busy-form>
        <?= \App\Core\Csrf::field() ?>
        <section class="rg-section au-card">
            <fieldset class="ds-fieldset au-choices">
                <legend class="rg-label"><?= $t('auth.title_select_community') ?></legend>
                <?php foreach ($candidates as $i => $c): ?>
                <?php $name = trim((string) $c['tenant_name']) !== '' ? (string) $c['tenant_name'] : (string) $c['tenant_slug']; ?>
                <label class="au-choice">
                    <input type="radio" name="tenant_id" value="<?= (int) $c['tenant_id'] ?>" required<?= $i === 0 ? ' checked' : '' ?>>
                    <span class="au-choice__mark" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($name, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="au-choice__text">
                        <span class="au-choice__title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="au-choice__meta"><?= htmlspecialchars((string) $c['tenant_slug'], ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </fieldset>
            <label class="ds-check au-confirm">
                <input type="checkbox" name="confirm_definitive" value="1" required>
                <span><?= $t('auth.select_community_confirm') ?></span>
            </label>
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--block au-btn au-btn--primary" data-busy-label="<?= $t('auth.submit_login_busy') ?>"><?= $t('auth.select_community_continue') ?></button>
        </section>
    </form>

    <form method="post" action="<?= url('login/select-community') ?>" class="ds-form rg-form au-temp-form" data-busy-form>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="temporary_access" value="1">
        <input type="hidden" name="tenant_id" value="<?= (int) ($candidates[0]['tenant_id'] ?? 0) ?>" id="au-temp-tenant">
        <p class="ds-hint au-temp-hint"><?= $t('auth.select_community_temporary') ?></p>
        <button type="submit" class="ds-btn ds-btn--ghost ds-btn--block au-btn" data-busy-label="<?= $t('auth.submit_login_busy') ?>"><?= $t('auth.select_community_temporary') ?></button>
    </form>
    <script>
    (function () {
        var temp = document.getElementById('au-temp-tenant');
        if (!temp) return;
        document.querySelectorAll('input[name="tenant_id"][type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) temp.value = radio.value;
            });
            if (radio.checked) temp.value = radio.value;
        });
    })();
    </script>

    <div class="ds-alt au-alt">
        <p><a class="au-link" href="<?= htmlspecialchars(url('login') . '?autre-compte=1', ENT_QUOTES, 'UTF-8') ?>"><?= $t('auth.otp_other_account') ?></a></p>
    </div>
    </div>
    </main>
</div>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/auth_forms.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
