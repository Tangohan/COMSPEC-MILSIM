<?php
declare(strict_types=1);
/**
 * Drawer navigation partagé (accueil + pages marketing).
 * Attendu : $loggedIn (bool), $base (string url base).
 */
$loggedIn = (bool) ($loggedIn ?? \App\Core\Session::get('user_id'));
$base = $base ?? url('');
$homeNavLink = 'flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold text-white/80 transition hover:bg-white/5 hover:text-white';
$homeNavAccent = 'flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/10';
$createCommunityHref = $loggedIn ? url('communities/create') : url('register');
?>
<div class="flex shrink-0 items-center justify-between border-b border-white/10 px-5 py-4">
    <span class="hi-kicker text-white/40"><?= htmlspecialchars(__('common.menu'), ENT_QUOTES, 'UTF-8') ?></span>
    <button type="button" onclick="toggleMenu()" class="rounded-lg p-2 text-white/50 transition hover:bg-white/5 hover:text-white" aria-label="<?= htmlspecialchars(__('common.close_menu'), ENT_QUOTES, 'UTF-8') ?>">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>
<nav class="min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-3 py-3" aria-label="<?= htmlspecialchars(__('home.nav_aria'), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($loggedIn): ?>
        <?php
        $scopeEntries = navigation_scope_drawer_entries();
        $scopeGroups = navigation_scope_group_entries($scopeEntries);
        $navCurrentPath = navigation_current_path();
        ?>
        <p class="px-3 pt-2 pb-1 hi-kicker text-white/30"><?= htmlspecialchars(__('site.nav_discover'), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars($createCommunityHref, ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavAccent ?>"><?= htmlspecialchars(__('site.create_community'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('a-propos'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('site.about'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('site.contact'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('nouveautes'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('site.changelog'), ENT_QUOTES, 'UTF-8') ?></a>
        <?php foreach ($scopeGroups as $groupName => $links): ?>
            <p class="px-3 pt-3 pb-1 hi-kicker text-white/30"><?= htmlspecialchars($groupName) ?></p>
            <?php foreach ($links as $entry): ?>
                <?php
                $rp = (string) ($entry['routePath'] ?? '/');
                $pathActive = preg_replace('/#.*$/', '', $rp) ?: '/';
                $match = navigation_infer_active_match($pathActive);
                $isActive = nav_path_matches($pathActive, $navCurrentPath, $match);
                $rowClass = $isActive ? $homeNavAccent : $homeNavLink;
                ?>
                <a href="<?= htmlspecialchars((string) $entry['href']) ?>" onclick="toggleMenu()" class="<?= $rowClass ?>"><?= htmlspecialchars((string) $entry['label']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="px-3 pt-2 pb-1 hi-kicker text-white/30"><?= htmlspecialchars(__('site.nav_discover'), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.home'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars($createCommunityHref, ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavAccent ?>"><?= htmlspecialchars(__('site.create_community'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('a-propos'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('site.about'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('site.contact'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('nouveautes'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('site.changelog'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.communities'), ENT_QUOTES, 'UTF-8') ?></a>
        <p class="px-3 pt-3 pb-1 hi-kicker text-white/30"><?= htmlspecialchars(__('site.nav_account'), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.login'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.register'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('join'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.join_code'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
</nav>
<div class="shrink-0 space-y-3 border-t border-white/10 p-4">
    <?php if (!$loggedIn): ?>
        <a href="<?= htmlspecialchars($createCommunityHref, ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="hi-cta hi-cta-solid w-full"><?= htmlspecialchars(__('site.create_community'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php else: ?>
        <a href="<?= htmlspecialchars(url('communities/create'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="hi-cta hi-cta-solid w-full"><?= htmlspecialchars(__('site.create_community'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" onclick="toggleMenu()" class="hi-cta hi-cta-ghost w-full"><?= htmlspecialchars(__('common.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
    <?php require base_path('views/partials/language_switcher.php'); ?>
</div>
