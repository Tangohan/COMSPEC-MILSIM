<?php
declare(strict_types=1);

/** @var string $baseUrl */

require_once base_path('views/partials/nav_icons.php');

$nav = build_navigation_menu();
$currentPath = navigation_current_path();
$loggedIn = (bool) \App\Core\Session::get('user_id');
$ctx = $loggedIn ? portal_header_context() : [
    'environment' => strtoupper(trim((string) (function_exists('env') ? env('APP_ENV', 'production') : 'production'))),
    'system_status' => '—',
    'tenant_label' => '',
    'alerts' => [],
    'alerts_count' => 0,
    'alerts_severity' => 'info',
    'role_label' => '',
    'display_name' => '',
];

$defaultAccent = 'slate';

/*
 * Navbar Athena (style Caverne) unifiée : appliquée à toutes les pages membre
 * du portail authentifié (hub, forum, formations, effectifs, documents, back-office, …).
 * Les visiteurs non connectés (vitrine communauté, pages marketing) gardent le méga-menu ci-dessous.
 */
$useAthenaHeader = $loggedIn;
if ($useAthenaHeader) {
    $athenaTenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
    $currentUser = null;
    $grade = null;
    $personnelExtras = null;
    $personnelProfile = null;
    try {
        $currentUser = \App\Core\Container::get(\App\Services\Auth\AuthService::class)->user();
    } catch (\Throwable) {
        $currentUser = null;
    }
    if (is_array($currentUser) && $athenaTenantId > 0) {
        try {
            $personnelExtras = \App\Core\Container::get(\App\Repositories\PersonnelExtrasRepository::class)
                ->getByUserId((int) $currentUser['id']);
        } catch (\Throwable) {
            $personnelExtras = null;
        }
        try {
            $personnelProfile = \App\Core\Container::get(\App\Repositories\PersonnelProfileRepository::class)
                ->getByUserId((int) $currentUser['id']);
        } catch (\Throwable) {
            $personnelProfile = null;
        }
        if (!empty($currentUser['grade_id'])) {
            try {
                $grade = \App\Core\Container::get(\App\Repositories\GradeRepository::class)
                    ->findById((int) $currentUser['grade_id'], $athenaTenantId);
            } catch (\Throwable) {
                $grade = null;
            }
        }
    }
}
?>

<?php if ($useAthenaHeader): ?>
    <?php require base_path('views/partials/athena_caverne_header.php'); ?>
<?php else: ?>
<header class="portal-nav sticky top-0 z-[100] w-full"
        data-portal-nav>
    <div class="portal-nav__shell">
        <div class="mx-auto max-w-[1800px] px-4 sm:px-6">
            <div class="portal-nav__bar">
                <div class="portal-nav__brand flex min-w-0 shrink-0 items-center overflow-hidden" data-accent="<?= htmlspecialchars($defaultAccent) ?>">
                    <a href="<?= htmlspecialchars($nav['brand']['href']) ?>"
                       class="group inline-flex items-baseline gap-0.5 focus:outline-none focus-visible:rounded-lg focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">
                        <span class="text-sm font-black uppercase tracking-[0.28em] text-slate-900 transition group-hover:text-emerald-700">Athena</span>
                        <span class="text-base font-black leading-none text-emerald-500" aria-hidden="true">.</span>
                    </a>
                </div>

                <nav class="portal-nav__menu relative z-[1] hidden min-w-0 xl:flex xl:justify-start" aria-label="Navigation principale" data-accent="<?= htmlspecialchars($defaultAccent) ?>">
                    <ul class="flex h-full items-center gap-1 sm:gap-1.5">
                        <?php foreach ($nav['menu'] as $item): ?>
                            <?php
                            $active = nav_item_is_active($item, $currentPath);
                            $mid = htmlspecialchars((string) ($item['id'] ?? ''));
                            $accent = $defaultAccent;
                            if (($item['type'] ?? '') === 'mega') {
                                $accent = (string) ($item['accent'] ?? $defaultAccent);
                            }
                            ?>
                            <?php if (($item['type'] ?? '') === 'link'): ?>
                                <li class="flex items-center" data-accent="<?= htmlspecialchars($accent) ?>">
                                    <a href="<?= htmlspecialchars((string) $item['href']) ?>"
                                       class="portal-nav__link--simple inline-flex min-h-[2.5rem] items-center rounded-lg px-2.5 py-2 text-[12px] font-semibold text-slate-700 transition-colors hover:text-slate-950 sm:px-3 sm:text-[13px]"
                                       data-active="<?= $active ? '1' : '0' ?>"
                                       <?php if (!empty($item['description'])): ?>title="<?= htmlspecialchars((string) $item['description']) ?>"<?php endif; ?>>
                                        <?= htmlspecialchars((string) $item['label']) ?>
                                        <?php if (!empty($item['badge'])): ?>
                                        <span class="ml-1.5 inline-flex items-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-[0.08em] text-white"><?= htmlspecialchars((string) $item['badge']) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <?php
                                $panelId = $mid . '-panel';
                                $triggerId = $mid . '-trigger';
                                $iconName = (string) ($item['icon'] ?? '');
                                ?>
                                <li class="group/nav relative flex items-center focus-within:relative"
                                    data-nav-item
                                    data-nav-type="mega"
                                    data-accent="<?= htmlspecialchars($accent) ?>">
                                    <button type="button"
                                            id="<?= $triggerId ?>"
                                            class="portal-nav__link--mega inline-flex items-center gap-0 rounded-lg border-0 bg-transparent p-0 text-[12px] font-semibold text-slate-700 transition-colors hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 sm:text-[13px]"
                                            aria-expanded="false"
                                            aria-haspopup="true"
                                            aria-controls="<?= $panelId ?>"
                                            data-nav-trigger
                                            data-active="<?= $active ? '1' : '0' ?>">
                                        <span class="portal-nav__trigger-inner relative flex items-center gap-1.5 rounded-lg px-2 py-1.5 sm:px-2.5 sm:py-2">
                                            <?php if ($iconName !== ''): ?>
                                                <span class="text-slate-400 [&>svg]:h-4 [&>svg]:w-4 [&>svg]:opacity-90" style="color: var(--nav-accent, #94a3b8);"><?= nav_icon_svg($iconName, 'h-4 w-4') ?></span>
                                            <?php endif; ?>
                                            <span class="inline-flex items-center gap-1.5"><?= htmlspecialchars((string) $item['label']) ?>
                                            <?php if (!empty($item['badge'])): ?>
                                            <span class="inline-flex items-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-[0.08em] text-white"><?= htmlspecialchars((string) $item['badge']) ?></span>
                                            <?php endif; ?>
                                            </span>
                                            <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-hover/nav:text-slate-600 group-[.nav-mega-is-open]/nav:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </button>
                                    <div id="<?= $panelId ?>"
                                         class="nav-mega-panel pointer-events-none invisible absolute left-1/2 top-full z-50 mt-1.5 w-[min(60rem,calc(100vw-1.25rem))] max-w-[calc(100vw-1rem)] -translate-x-1/2 translate-y-1 opacity-0 transition-all duration-200 ease-out group-hover/nav:pointer-events-auto group-hover/nav:visible group-hover/nav:-translate-x-1/2 group-hover/nav:translate-y-0 group-hover/nav:opacity-100 group-focus-within/nav:pointer-events-auto group-focus-within/nav:visible group-focus-within/nav:-translate-x-1/2 group-focus-within/nav:translate-y-0 group-focus-within/nav:opacity-100 group-[.nav-mega-is-open]/nav:pointer-events-auto group-[.nav-mega-is-open]/nav:visible group-[.nav-mega-is-open]/nav:-translate-x-1/2 group-[.nav-mega-is-open]/nav:translate-y-0 group-[.nav-mega-is-open]/nav:opacity-100"
                                         data-nav-panel
                                         data-accent="<?= htmlspecialchars($accent) ?>"
                                         role="region"
                                         aria-label="<?= htmlspecialchars((string) $item['label']) ?>">
                                        <?php include base_path('views/partials/nav/mega_dropdown.php'); ?>
                                    </div>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <div class="portal-nav__tools flex min-w-0 shrink-0 items-center justify-end gap-2 sm:gap-3 pl-2">
                    <?php if (!empty($nav['search']['shortcut'])): ?>
                        <a href="<?= htmlspecialchars($nav['search']['action']) ?>"
                           class="hidden shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 md:inline-flex"
                           data-portal-search-url
                           title="Recherche portail"
                           aria-label="Ouvrir la recherche portail (Ctrl+K)">
                            <kbd class="pointer-events-none inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded border border-slate-200 bg-slate-100 px-1 font-mono text-[9px] font-bold tracking-wide text-slate-800">Ctrl</kbd>
                            <span class="text-[10px] font-medium text-slate-400" aria-hidden="true">+</span>
                            <kbd class="pointer-events-none inline-flex h-6 min-w-[1.25rem] items-center justify-center rounded border border-slate-200 bg-slate-100 px-1 font-mono text-[9px] font-bold text-slate-800">K</kbd>
                        </a>
                    <?php endif; ?>

                    <?php if ($loggedIn): ?>
                        <button type="button"
                                data-portal-help-trigger
                                class="hidden shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900 sm:inline-flex"
                                title="Signaler un problème ou demander de l’aide aux modérateurs"
                                aria-label="Aide : signalement ou demande aux modérateurs">
                            Aide
                        </button>
                        <?php require base_path('views/partials/portal_alerts_bell.php'); ?>
                    <?php endif; ?>

                    <?php $localeSwitcherVariant = 'light'; require base_path('views/partials/language_switcher.php'); ?>

                    <?php if ($loggedIn): ?>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/account"
                           class="group hidden items-center gap-2 rounded-xl border border-transparent px-2 py-1.5 transition hover:border-slate-200 hover:bg-slate-50 sm:flex">
                            <div class="max-w-[140px] text-right">
                                <p class="truncate text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Compte</p>
                                <p class="truncate text-xs font-semibold text-slate-900" title="<?= htmlspecialchars($ctx['display_name']) ?>">
                                    <?= htmlspecialchars($ctx['display_name'] !== '' ? $ctx['display_name'] : 'Compte') ?>
                                </p>
                            </div>
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-slate-700 transition group-hover:border-emerald-400 group-hover:bg-white group-hover:text-emerald-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        </a>

                        <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/logout" class="flex items-center">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-rose-50 hover:text-rose-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-400"
                                    aria-label="Déconnexion">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/login"
                           class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-800 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500">
                            Connexion
                        </a>
                    <?php endif; ?>

                    <button type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 shadow-sm transition hover:bg-slate-50 xl:hidden focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
                            data-mobile-nav-toggle
                            aria-expanded="false"
                            aria-controls="portal-nav-drawer"
                            aria-label="Ouvrir le menu">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="portal-nav-overlay"
         class="portal-nav-overlay fixed inset-0 z-[110] bg-slate-900/40 opacity-0 pointer-events-none transition-opacity xl:hidden"
         data-mobile-nav-overlay
         aria-hidden="true"></div>

    <div id="portal-nav-drawer"
         class="portal-nav-drawer fixed inset-y-0 right-0 z-[120] flex w-[min(380px,calc(100vw-1rem))] translate-x-full flex-col border-l border-slate-200 bg-white shadow-2xl transition-transform duration-200 ease-out xl:hidden"
         data-mobile-nav-drawer
         role="dialog"
         aria-modal="true"
         aria-label="Menu de navigation"
         tabindex="-1"
         hidden>
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Navigation</span>
            <button type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
                    data-mobile-nav-close
                    aria-label="Fermer le menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-3 py-4">
            <?php if (!empty($loggedIn)): ?>
            <button type="button"
                    data-portal-help-trigger
                    class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-rose-600 bg-rose-600 px-4 py-3 text-[11px] font-black uppercase tracking-[0.16em] text-white shadow-sm transition hover:bg-rose-500"
                    aria-label="Aide : signalement ou demande aux modérateurs">
                HELP — aide &amp; signalement
            </button>
            <p class="mb-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-[11px] leading-snug text-slate-600">
                <span class="font-bold text-slate-800">Périmètre des accès :</span>
                seuls les modules autorisés pour votre profil sont listés ci-dessous (communauté de session).
            </p>
            <?php endif; ?>
            <?php foreach ($nav['menu'] as $item): ?>
                <?php
                $mAccent = ($item['type'] ?? '') === 'mega' ? (string) ($item['accent'] ?? 'slate') : 'slate';
                ?>
                <?php if (($item['type'] ?? '') === 'link'): ?>
                    <a href="<?= htmlspecialchars((string) $item['href']) ?>"
                       class="flex flex-wrap items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50">
                        <?= htmlspecialchars((string) $item['label']) ?>
                        <?php if (!empty($item['badge'])): ?>
                        <span class="inline-flex items-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-[0.08em] text-white"><?= htmlspecialchars((string) $item['badge']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <?php $accId = htmlspecialchars((string) ($item['id'] ?? '') . '-acc'); ?>
                    <div class="border-b border-slate-100 py-1" data-mobile-accordion data-accent="<?= htmlspecialchars($mAccent) ?>">
                        <button type="button"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-slate-900 hover:bg-slate-50"
                                aria-expanded="false"
                                aria-controls="<?= $accId ?>"
                                data-mobile-accordion-trigger>
                            <span class="flex flex-wrap items-center gap-2">
                                <?php
                                $mi = (string) ($item['icon'] ?? '');
                                if ($mi !== '') {
                                    echo nav_icon_svg($mi, 'h-4 w-4 shrink-0');
                                }
                                ?>
                                <?= htmlspecialchars((string) $item['label']) ?>
                                <?php if (!empty($item['badge'])): ?>
                                <span class="inline-flex items-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-[0.08em] text-white"><?= htmlspecialchars((string) $item['badge']) ?></span>
                                <?php endif; ?>
                            </span>
                            <svg class="h-4 w-4 text-slate-400 transition" data-mobile-accordion-icon viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div id="<?= $accId ?>" class="hidden pb-2 pl-2" data-mobile-accordion-panel>
                            <?php foreach ($item['sections'] ?? [] as $section): ?>
                                <p class="px-3 pt-2 text-[11px] font-black uppercase tracking-[0.18em]" style="color: var(--nav-accent, #64748b);">
                                    <?= htmlspecialchars((string) ($section['title'] ?? '')) ?>
                                </p>
                                <?php foreach ($section['links'] ?? [] as $link): ?>
                                    <?php if (!is_array($link)) {
                                        continue;
                                    } ?>
                                    <a href="<?= htmlspecialchars((string) ($link['href'] ?? '#')) ?>"
                                       class="block rounded-2xl border border-transparent px-3 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-slate-200 hover:bg-slate-50">
                                        <span class="inline-flex flex-wrap items-center gap-2">
                                            <?= htmlspecialchars((string) ($link['label'] ?? '')) ?>
                                            <?php if (!empty($link['badge'])): ?>
                                                <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[9px] font-black tabular-nums text-white"><?= htmlspecialchars((string) $link['badge']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if (!empty($link['description'])): ?>
                                            <span class="mt-0.5 block text-xs font-normal leading-5 text-slate-500">
                                                <?= htmlspecialchars((string) $link['description']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php if (!empty($item['featured']) && is_array($item['featured'])): ?>
                                <?php $mfeat = $item['featured']; ?>
                                <div class="mx-1 mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em]"
                                          style="background: var(--nav-accent-soft); color: var(--nav-accent);">
                                        <?= htmlspecialchars((string) (($mfeat['eyebrow'] ?? '') !== '' ? $mfeat['eyebrow'] : 'Module')) ?>
                                    </span>
                                    <h4 class="mt-2 text-lg font-black uppercase tracking-tight text-slate-950">
                                        <?= htmlspecialchars((string) ($mfeat['title'] ?? '')) ?>
                                    </h4>
                                    <?php if (($mfeat['description'] ?? '') !== ''): ?>
                                        <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                            <?= htmlspecialchars((string) $mfeat['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($mfeat['cta_href']) && ($mfeat['cta_label'] ?? '') !== ''): ?>
                                        <a href="<?= htmlspecialchars((string) $mfeat['cta_href']) ?>"
                                           class="<?= htmlspecialchars((string) ($mfeat['cta_classes'] ?? 'nav-cta nav-cta--open')) ?> mt-3 inline-flex w-full items-center justify-center rounded-2xl px-4 py-3 text-sm font-bold">
                                            <?= htmlspecialchars((string) $mfeat['cta_label']) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</header>
<?php endif; ?>

<?php if ($loggedIn && !empty($nav['search']['shortcut'])): ?>
    <?php require base_path('views/partials/portal_command_palette.php'); ?>
<?php endif; ?>
<?php if ($loggedIn): ?>
    <?php require base_path('views/partials/ui/confirm_dialog.php'); ?>
<?php endif; ?>
