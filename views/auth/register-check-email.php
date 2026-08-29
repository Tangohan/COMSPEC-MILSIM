<?php
$base = url('');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$emailAddr = (string) ($email ?? '');
$error = $error ?? null;
$warning = $warning ?? null;
$fileMailerNotice = \email_file_mailer_notice();
$athena_header_current = 'register';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? __('auth.register_check_title'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
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
    <div class="ds-main__inner">
    <p class="ds-kicker"><?= htmlspecialchars(__('auth.register_check_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="ds-title"><?= htmlspecialchars(__('auth.register_check_title'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="ds-lead"><?= htmlspecialchars(__('auth.register_check_lead'), ENT_QUOTES, 'UTF-8') ?></p>

    <div class="ds-alert-stack">
        <?php if (!empty($error)): ?>
            <?php $flash_variant = 'error'; $flash_message = (string) $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if (!empty($warning)): ?>
            <?php $flash_variant = 'warning'; $flash_message = (string) $warning; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <div class="ds-callout" style="margin-top:1.75rem">
        <?php if ($fileMailerNotice !== ''): ?>
            <p class="ds-hint" style="margin-bottom:0.75rem"><?= htmlspecialchars($fileMailerNotice, ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= htmlspecialchars(__('auth.register_check_address'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <p><?= htmlspecialchars(__('auth.register_check_sent_to'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <p class="ds-title" style="font-size:1.15rem;margin-top:0.35rem;word-break:break-all">
            <?= htmlspecialchars($emailAddr, ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p style="margin-top:0.75rem;margin-bottom:0">
            <?= htmlspecialchars(
                $fileMailerNotice !== ''
                    ? __('auth.register_check_file_mailer_hint')
                    : __('auth.register_check_ttl_hint'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <form method="post" action="<?= htmlspecialchars(url('resend-verification'), ENT_QUOTES, 'UTF-8') ?>" class="ds-form" style="margin-top:1.25rem">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="email" value="<?= htmlspecialchars($emailAddr, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--block">
                <?= htmlspecialchars(__('auth.resend_confirm'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </form>
    </div>

    <div class="ds-alt">
        <p>
            <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('auth.back_to_login'), ENT_QUOTES, 'UTF-8') ?></a>
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

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
