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
    <meta name="theme-color" content="#0b3d38">
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/dsfr-service.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php $GLOBALS['__dsfr_service_css'] = true; ?>
    <script defer src="https://unpkg.com/alpinejs@3/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="ds-page ds-page--split" x-data="{ view: 'login', showPassword: false }">
<a class="ds-skip" href="#contenu"><?= htmlspecialchars(__('common.skip_to_content'), ENT_QUOTES, 'UTF-8') ?></a>

<header class="ds-header">
    <div class="ds-header__band" aria-hidden="true"></div>
    <div class="ds-header__inner">
        <a class="ds-header__brand" href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>">
            <span class="ds-header__service"><?= $brandText ?></span>
            <span class="ds-header__tagline"><?= htmlspecialchars(__('auth.kicker'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <div class="ds-header__tools">
            <?php $localeSwitcherVariant = 'light'; $localeSwitcherClass = 'ds-lang'; require base_path('views/partials/language_switcher.php'); ?>
            <a class="ds-header__link" href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.register'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</header>

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
    <p class="ds-kicker"><?= htmlspecialchars(__('auth.title_login'), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="ds-title"><?= $brandText ?></h1>
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

    <div x-show="view === 'login'">
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
                    <button type="button" class="ds-link-muted" @click.prevent="view = 'forgot'">
                        <?= htmlspecialchars(__('auth.forgot_link'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                <p class="ds-hint" id="password-hint"><?= htmlspecialchars(__('auth.password_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="ds-password">
                    <input :type="showPassword ? 'text' : 'password'" name="password" id="password" required autocomplete="current-password"
                           class="ds-password__field"
                           aria-describedby="password-hint">
                    <button type="button" class="ds-password__toggle" @click="showPassword = !showPassword"
                            :aria-label="showPassword ? <?= json_encode(__('auth.hide_password'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(__('auth.show_password'), JSON_UNESCAPED_UNICODE) ?>">
                        <span x-text="showPassword ? <?= json_encode(__('auth.hide_password'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(__('auth.show_password'), JSON_UNESCAPED_UNICODE) ?>"></span>
                    </button>
                </div>
            </div>
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--block"><?= htmlspecialchars(__('auth.submit_login'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    </div>

    <div x-show="view === 'forgot'" x-cloak>
        <h2 class="ds-title" style="font-size:1.5rem;margin-top:1.75rem"><?= htmlspecialchars(__('auth.recovery'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="ds-lead" style="font-size:1rem"><?= htmlspecialchars(__('auth.recovery_hint'), ENT_QUOTES, 'UTF-8') ?></p>
        <form method="post" action="<?= url('forgot-password') ?>" class="ds-form">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="forgot-email" class="ds-label"><?= htmlspecialchars(__('auth.email'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="email" name="email" id="forgot-email" required autocomplete="email" autocapitalize="none" spellcheck="false"
                       class="ds-input"
                       data-lowercase="email">
            </div>
            <div class="ds-btn-row">
                <button type="submit" class="ds-btn ds-btn--primary"><?= htmlspecialchars(__('auth.send_link'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="ds-btn ds-btn--secondary" @click.prevent="view = 'login'"><?= htmlspecialchars(__('auth.back_to_login'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
    </div>

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

<footer class="ds-footer">
    <div class="ds-footer__inner">
        <p><?= $brandText ?></p>
        <nav class="ds-footer__links" aria-label="<?= htmlspecialchars(__('legal.mentions'), ENT_QUOTES, 'UTF-8') ?>">
            <?php
            $legal_link_class = '';
            require base_path('views/partials/legal_site_links.php');
            ?>
        </nav>
    </div>
</footer>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/js/auth_forms.js"></script>
</body>
</html>
