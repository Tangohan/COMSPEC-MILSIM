<?php
declare(strict_types=1);

/**
 * Bandeau Athena pour les pages d’accès (connexion, etc.).
 * Même chrome que l’espace membre, sans session ni liens internes.
 */
$h = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$homeHref = url('');
$loginHref = url('login');
$registerHref = url('register');
$joinHref = url('join');
$aboutHref = url('a-propos');
$newsHref = url('nouveautes');
$contactHref = url('contact');

$navItems = [
    ['key' => 'academy', 'label' => 'Academy', 'href' => url('academy')],
    ['key' => 'home', 'label' => __('common.home'), 'href' => $homeHref],
    ['key' => 'about', 'label' => __('site.about'), 'href' => $aboutHref],
    ['key' => 'news', 'label' => __('auth.nav_news'), 'href' => $newsHref],
    ['key' => 'contact', 'label' => __('site.contact'), 'href' => $contactHref],
    ['key' => 'login', 'label' => __('common.login'), 'href' => $loginHref],
];
$currentKey = (string) ($athena_header_current ?? 'login');

$espaceLinks = [
    ['abbr' => 'ACC', 'label' => __('common.home'), 'desc' => __('auth.header_espace_home'), 'href' => $homeHref],
    ['abbr' => 'APR', 'label' => __('site.about'), 'desc' => __('auth.header_espace_about'), 'href' => $aboutHref],
    ['abbr' => 'NOV', 'label' => __('auth.nav_news'), 'desc' => __('auth.header_espace_news'), 'href' => $newsHref],
    ['abbr' => 'CNT', 'label' => __('site.contact'), 'desc' => __('auth.header_espace_contact'), 'href' => $contactHref],
    ['abbr' => 'INS', 'label' => __('common.create_account'), 'desc' => __('auth.header_espace_register'), 'href' => $registerHref],
    ['abbr' => 'INV', 'label' => __('auth.join_community'), 'desc' => __('auth.header_espace_join'), 'href' => $joinHref],
];

$espacesGroups = \App\Support\EspacesMenu::groups($espaceLinks, '');

$quickLinks = [
    ['label' => __('common.home'), 'href' => $homeHref],
    ['label' => __('site.about'), 'href' => $aboutHref],
    ['label' => __('auth.nav_news'), 'href' => $newsHref],
    ['label' => __('site.contact'), 'href' => $contactHref],
    ['label' => __('common.login'), 'href' => $loginHref],
    ['label' => __('common.create_account'), 'href' => $registerHref],
    ['label' => __('auth.join_community'), 'href' => $joinHref],
];
?>
<nav
    class="athena-header"
    role="navigation"
    aria-label="<?= $h(__('auth.header_nav_label')) ?>"
    data-athena-header
    data-tenant-type="guest"
>
    <div class="athena-header__inner">
        <a href="<?= $h($homeHref) ?>" class="athena-header__brand">
            <?php $athSeasonal = athena_seasonal_logo_img(); ?><span class="athena-header__brand-mark"<?= $athSeasonal !== '' ? ' style="background:transparent"' : '' ?> aria-hidden="true"><?= $athSeasonal !== '' ? $athSeasonal : '<span class="athena-header__brand-emblem"></span>' ?></span>
            <span class="athena-header__brand-text">
                <span class="athena-header__brand-title">Athena<span class="athena-header__brand-dot">.</span></span>
                <span class="athena-header__brand-sub"><?= $h(__('auth.header_sub')) ?></span>
            </span>
        </a>

        <div class="athena-header__nav-center">
            <?php foreach ($navItems as $index => $item): ?>
                <?php if ($index > 0): ?><span class="athena-header__sep" aria-hidden="true">/</span><?php endif; ?>
                <?php
                $isActive = ((string) ($item['key'] ?? '')) === $currentKey;
                $itemLabel = $h((string) ($item['label'] ?? ''));
                ?>
                <?php if ($isActive): ?>
                    <span class="athena-header__link athena-header__link--active" aria-current="page"><?= $itemLabel ?></span>
                <?php else: ?>
                    <a href="<?= $h((string) ($item['href'] ?? '#')) ?>" class="athena-header__link"><?= $itemLabel ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="athena-header__actions">
            <a href="<?= $h($registerHref) ?>" class="athena-header__cta">
                <svg class="athena-header__cta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span class="athena-header__cta-label"><?= $h(__('auth.header_cta')) ?></span>
            </a>

            <?php $espacesMode = 'bar'; $espacesLabel = __('auth.header_espaces'); $espacesAria = __('auth.header_espaces_aria'); require base_path('views/partials/athena_espaces_menu.php'); ?>

            <div class="relative hidden md:block">
                <button
                    type="button"
                    class="athena-header__icon-btn"
                    data-athena-toggle="quick"
                    aria-label="<?= $h(__('auth.header_quick')) ?>"
                    aria-expanded="false"
                    aria-controls="athena-header-quick"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <span class="athena-header__menu-label"><?= $h(__('common.menu')) ?></span>
                </button>
                <div class="athena-header__panel athena-header__panel--quick hidden" id="athena-header-quick" data-athena-panel="quick">
                    <div class="athena-header__quick-head">
                        <strong><?= $h(__('auth.header_quick')) ?></strong>
                        <span><?= count($quickLinks) ?></span>
                    </div>
                    <div class="athena-header__quick-grid">
                        <?php foreach ($quickLinks as $ql): ?>
                            <a href="<?= $h((string) $ql['href']) ?>"><?= $h((string) $ql['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="athena-header__lang">
                <?php
                $localeSwitcherVariant = 'dark';
                $localeSwitcherClass = '';
                require base_path('views/partials/language_switcher.php');
                ?>
            </div>
        </div>
    </div>
</nav>
