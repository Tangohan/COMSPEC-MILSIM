<?php
declare(strict_types=1);

/**
 * Barre du haut du site public (page d'accueil, page d'invitation /i/…) : menu latéral, liens, langue, connexion.
 * À inclure juste après <body class="home-impact …"> ; styles home-impact.css + styles.css.
 *
 * @var string|null $homeTopbarLoginUrl    lien « Connexion » (ex. avec ?redirect=…)
 * @var string|null $homeTopbarRegisterUrl lien d'inscription (ex. avec ?invite=…)
 */
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$base = $base ?? url('');
$loggedIn = (bool) \App\Core\Session::get('user_id');
$homeTopbarLoginUrl = (string) ($homeTopbarLoginUrl ?? url('login'));
$homeTopbarRegisterUrl = (string) ($homeTopbarRegisterUrl ?? url('register'));
?>
    <div id="bodyOverlay" class="overlay fixed inset-0 z-[110] bg-black/60 backdrop-blur-sm" onclick="toggleMenu()"></div>

    <div id="navDrawer" class="drawer-translate fixed top-0 left-0 z-[120] flex h-full w-[min(100%,320px)] flex-col overflow-hidden border-r border-white/10 bg-[#0a0a0a] shadow-2xl" inert>
        <?php require base_path('views/partials/home_nav_drawer.php'); ?>
    </div>

    <header class="hi-topbar">
        <div class="hi-topbar__inner">
            <div class="hi-topbar__left">
                <button type="button" id="hi-menu-toggle" onclick="toggleMenu()" class="hi-burger" aria-label="<?= $e(__('common.open_menu')) ?>" aria-controls="navDrawer" aria-expanded="false">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </button>
                <a href="<?= $e($base) ?>/" class="hi-wordmark" aria-label="Athena — <?= $e(__('common.home')) ?>"><img src="<?= $e($base) ?>/assets/brand/athena-embleme-blanc.svg" alt="" class="hi-wordmark__emblem" width="30" height="26">Athena<span class="hi-wordmark__dot" aria-hidden="true">.</span></a>
            </div>
            <nav class="hi-topbar__nav" aria-label="<?= $e(__('home.nav_aria')) ?>">
                <a href="<?= $e(url('communities')) ?>"><?= $e(__('common.communities')) ?></a>
                <a href="<?= $e(url('sse')) ?>"><?= $e(__('site.sse')) ?></a>
                <a href="<?= $e(url('atak-natif')) ?>"><?= $e(__('site.atak_native')) ?></a>
                <a href="<?= $e(url('wiki/atak')) ?>">Wiki ATAK</a>
                <a href="<?= $e(url('a-propos')) ?>"><?= $e(__('site.about')) ?></a>
            </nav>
            <div class="hi-topbar__right">
                <?php $localeSwitcherClass = 'hi-topbar__lang'; require base_path('views/partials/language_switcher.php'); unset($localeSwitcherClass); ?>
                <span id="home-header-clock" class="hi-topbar__clock" aria-hidden="true">--:--:--</span>
                <?php if (!$loggedIn): ?>
                    <a href="<?= $e($homeTopbarLoginUrl) ?>" class="hi-topbar__link"><?= $e(__('common.login')) ?></a>
                    <a href="<?= $e($homeTopbarRegisterUrl) ?>" class="hi-cta hi-cta-solid hi-cta--sm hi-topbar__cta"><?= $e(__('home.cta_create_community')) ?></a>
                <?php else: ?>
                    <a href="<?= url('dashboard') ?>" class="hi-topbar__link hi-topbar__link--accent"><?= htmlspecialchars(__('common.ops'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= $e(url('hub')) ?>" class="hi-cta hi-cta-solid hi-cta--sm hi-topbar__cta"><?= $e(__('home.cta_command_center')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <script>
        function toggleMenu(force) {
            var open = typeof force === 'boolean' ? force : !document.body.classList.contains('drawer-open');
            var drawer = document.getElementById('navDrawer');
            var trigger = document.getElementById('hi-menu-toggle');
            document.body.classList.toggle('drawer-open', open);
            document.body.style.overflow = open ? 'hidden' : '';
            if (drawer) {
                if (open) drawer.removeAttribute('inert');
                else drawer.setAttribute('inert', '');
            }
            if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && drawer) {
                var first = drawer.querySelector('a, button');
                if (first) first.focus();
            } else if (!open && trigger) {
                trigger.focus();
            }
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && document.body.classList.contains('drawer-open')) toggleMenu(false);
        });

        (function headerClock() {
            var hc = document.getElementById('home-header-clock');
            if (!hc || !window.matchMedia || !window.matchMedia('(min-width: 1440px)').matches) return;
            function tick() {
                var now = new Date();
                hc.textContent = now.getHours().toString().padStart(2, '0') + ':' +
                    now.getMinutes().toString().padStart(2, '0') + ':' +
                    now.getSeconds().toString().padStart(2, '0');
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
