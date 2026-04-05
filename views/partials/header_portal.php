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

$brandTagline = trim((string) ($nav['brand']['tagline'] ?? ''));
$alertsSeverity = (string) ($ctx['alerts_severity'] ?? 'info');
$alertsChipClass = 'portal-nav__status-chip';
if ($ctx['alerts_count'] > 0) {
    $alertsChipClass .= $alertsSeverity === 'urgent' ? ' portal-nav__status-chip--alert-urgent' : ' portal-nav__status-chip--alert-warn';
}

$defaultAccent = 'slate';
?>

<header class="portal-nav sticky top-0 z-[100] w-full"
        data-portal-nav>
    <div class="portal-nav__shell">
        <div class="mx-auto max-w-[1800px] px-4 sm:px-6">
            <div class="flex flex-col gap-2 py-2 lg:flex-row lg:items-center lg:gap-4 lg:py-3 xl:min-h-[4.5rem]">
                <div class="flex min-w-0 flex-1 items-start gap-3" data-accent="<?= htmlspecialchars($defaultAccent) ?>">
                    <span class="portal-nav__brand-mark mt-0.5 hidden h-14 w-1 shrink-0 sm:block" aria-hidden="true"></span>
                    <a href="<?= htmlspecialchars($nav['brand']['href']) ?>"
                       class="group flex min-w-0 flex-col leading-none">
                        <span class="text-[13px] font-black uppercase italic tracking-[0.38em] text-slate-950 transition-colors group-hover:text-slate-800">
                            <?= htmlspecialchars($nav['brand']['name']) ?>
                        </span>
                        <span class="mt-1 text-[11px] font-semibold leading-tight text-slate-600">
                            <?= htmlspecialchars($nav['brand']['subtitle']) ?>
                        </span>
                        <?php if ($brandTagline !== ''): ?>
                            <span class="mt-0.5 text-[10px] font-mono uppercase tracking-[0.12em] text-slate-400">
                                <?= htmlspecialchars($brandTagline) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>

                <nav class="hidden min-w-0 flex-[1.2] justify-center xl:flex" aria-label="Navigation principale" data-accent="<?= htmlspecialchars($defaultAccent) ?>">
                    <ul class="flex h-full items-stretch gap-0.5">
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
                                <li data-accent="<?= htmlspecialchars($accent) ?>">
                                    <a href="<?= htmlspecialchars((string) $item['href']) ?>"
                                       class="portal-nav__link--simple inline-flex h-14 items-center border-b-[3px] border-transparent px-3 text-[13px] font-semibold text-slate-600 transition hover:text-slate-950"
                                       data-active="<?= $active ? '1' : '0' ?>">
                                        <?= htmlspecialchars((string) $item['label']) ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <?php
                                $panelId = $mid . '-panel';
                                $triggerId = $mid . '-trigger';
                                $iconName = (string) ($item['icon'] ?? '');
                                ?>
                                <li class="group/nav relative flex items-stretch focus-within:relative"
                                    data-nav-item
                                    data-nav-type="mega"
                                    data-accent="<?= htmlspecialchars($accent) ?>">
                                    <button type="button"
                                            id="<?= $triggerId ?>"
                                            class="portal-nav__link--mega inline-flex h-14 items-center gap-1.5 border-b-[3px] border-transparent px-2 text-[13px] font-semibold text-slate-600 transition hover:text-slate-950"
                                            aria-expanded="false"
                                            aria-haspopup="true"
                                            aria-controls="<?= $panelId ?>"
                                            data-nav-trigger
                                            data-active="<?= $active ? '1' : '0' ?>">
                                        <span class="portal-nav__trigger-inner flex items-center gap-1.5 border-b-[3px] border-transparent px-1 pb-0.5">
                                            <?php if ($iconName !== ''): ?>
                                                <span class="text-slate-500 [&>svg]:opacity-90" style="color: var(--nav-accent, #64748b);"><?= nav_icon_svg($iconName, 'h-4 w-4') ?></span>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars((string) $item['label']) ?></span>
                                            <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-hover/nav:text-slate-700 group-[.nav-mega-is-open]/nav:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </button>
                                    <div id="<?= $panelId ?>"
                                         class="nav-mega-panel pointer-events-none invisible absolute left-0 top-full z-50 mt-0 w-[min(1080px,calc(100vw-1.5rem))] max-w-[1080px] translate-y-2 opacity-0 transition-all duration-200 ease-out group-hover/nav:pointer-events-auto group-hover/nav:visible group-hover/nav:translate-y-0 group-hover/nav:opacity-100 group-focus-within/nav:pointer-events-auto group-focus-within/nav:visible group-focus-within/nav:translate-y-0 group-focus-within/nav:opacity-100 group-[.nav-mega-is-open]/nav:pointer-events-auto group-[.nav-mega-is-open]/nav:visible group-[.nav-mega-is-open]/nav:translate-y-0 group-[.nav-mega-is-open]/nav:opacity-100"
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

                <div class="flex w-full min-w-0 flex-1 items-center justify-end gap-2 sm:gap-3 lg:flex-initial lg:justify-end">
                    <?php if (!empty($nav['search']['enabled'])): ?>
                        <form method="<?= htmlspecialchars(strtoupper($nav['search']['method'])) ?>"
                              action="<?= htmlspecialchars($nav['search']['action']) ?>"
                              class="hidden min-w-0 max-w-[14rem] flex-1 md:flex lg:max-w-xs"
                              role="search">
                            <div class="flex w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-50 transition focus-within:border-sky-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-sky-100">
                                <input type="search"
                                       name="<?= htmlspecialchars($nav['search']['param']) ?>"
                                       value="<?= htmlspecialchars((string) ($_GET[$nav['search']['param']] ?? '')) ?>"
                                       placeholder="<?= htmlspecialchars($nav['search']['placeholder']) ?>"
                                       class="h-10 w-full min-w-0 border-0 bg-transparent px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                       autocomplete="off"
                                       aria-label="Recherche portail">
                                <button type="submit"
                                        class="inline-flex w-10 shrink-0 items-center justify-center border-l border-slate-200 text-slate-500 transition hover:bg-sky-600 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-600"
                                        aria-label="Lancer la recherche">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.5 3a5.5 5.5 0 104.24 9.01l3.62 3.62a.75.75 0 101.06-1.06l-3.62-3.62A5.5 5.5 0 008.5 3zm-4 5.5a4 4 0 118 0 4 4 0 01-8 0z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($loggedIn): ?>
                        <div class="relative" data-portal-alerts-wrap>
                            <button type="button"
                                    class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
                                    data-portal-alerts-trigger
                                    aria-expanded="false"
                                    aria-controls="portal-alerts-dropdown"
                                    aria-haspopup="dialog"
                                    aria-label="Annonces et alertes">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <?php if ($ctx['alerts_count'] > 0): ?>
                                    <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full px-1 text-[10px] font-black <?= $alertsSeverity === 'urgent' ? 'bg-rose-600 text-white' : 'bg-amber-500 text-slate-950' ?>">
                                        <?= (int) $ctx['alerts_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <div id="portal-alerts-dropdown"
                                 class="portal-alerts-panel absolute right-0 top-full z-[130] mt-2 max-h-[min(70vh,420px)] overflow-y-auto rounded-2xl p-0"
                                 data-portal-alerts-panel
                                 hidden
                                 role="dialog"
                                 aria-label="Liste des annonces">
                                <?php if (($ctx['alerts'] ?? []) === []): ?>
                                    <p class="px-4 py-6 text-center text-sm text-slate-500">Aucune annonce active.</p>
                                <?php else: ?>
                                    <?php foreach ($ctx['alerts'] as $a): ?>
                                        <?php if (!is_array($a)) {
                                            continue;
                                        } ?>
                                        <div class="portal-alerts-panel__item px-4 py-3">
                                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400"><?= htmlspecialchars((string) ($a['scope'] ?? '')) ?> · <?= htmlspecialchars((string) ($a['kind'] ?? '')) ?></p>
                                            <p class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($a['title'] ?? '')) ?></p>
                                            <?php if (trim((string) ($a['body'] ?? '')) !== ''): ?>
                                                <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars((string) $a['body']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($a['cta_label']) && !empty($a['cta_url'])): ?>
                                                <a href="<?= htmlspecialchars((string) $a['cta_url']) ?>" class="mt-2 inline-flex text-xs font-bold text-sky-700 hover:underline">
                                                    <?= htmlspecialchars((string) $a['cta_label']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($loggedIn): ?>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/account"
                           class="group hidden items-center gap-2 rounded-xl border border-transparent px-2 py-1.5 transition hover:border-slate-200 hover:bg-slate-50 sm:flex">
                            <div class="max-w-[140px] text-right">
                                <p class="truncate text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Session</p>
                                <p class="truncate text-xs font-semibold text-slate-900" title="<?= htmlspecialchars($ctx['display_name']) ?>">
                                    <?= htmlspecialchars($ctx['display_name'] !== '' ? $ctx['display_name'] : 'Compte') ?>
                                </p>
                            </div>
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition group-hover:border-sky-200 group-hover:text-sky-800">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        </a>

                        <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/logout" class="flex items-center">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-400"
                                    aria-label="Déconnexion">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/login"
                           class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
                            Connexion
                        </a>
                    <?php endif; ?>

                    <button type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 shadow-sm transition hover:bg-slate-50 xl:hidden focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
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

            <?php if ($loggedIn): ?>
                <div class="portal-nav__status flex flex-wrap items-center gap-2 border-t border-slate-200/90 px-0 py-2 text-[10px] font-mono font-semibold uppercase tracking-wider text-slate-600">
                    <span class="<?= htmlspecialchars($alertsChipClass) ?> rounded-lg px-2 py-1">
                        Env · <?= htmlspecialchars($ctx['environment']) ?>
                    </span>
                    <span class="portal-nav__status-chip rounded-lg px-2 py-1">
                        Statut · <?= htmlspecialchars($ctx['system_status']) ?>
                    </span>
                    <?php if (($ctx['tenant_label'] ?? '') !== ''): ?>
                        <span class="portal-nav__status-chip max-w-[200px] truncate rounded-lg px-2 py-1" title="<?= htmlspecialchars($ctx['tenant_label']) ?>">
                            Communauté · <?= htmlspecialchars($ctx['tenant_label']) ?>
                        </span>
                    <?php endif; ?>
                    <span class="<?= htmlspecialchars($alertsChipClass) ?> rounded-lg px-2 py-1">
                        Alertes · <?= (int) $ctx['alerts_count'] ?>
                    </span>
                    <?php if (($ctx['role_label'] ?? '') !== ''): ?>
                        <span class="portal-nav__status-chip rounded-lg px-2 py-1">
                            Rôle · <?= htmlspecialchars($ctx['role_label']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
            <?php foreach ($nav['menu'] as $item): ?>
                <?php
                $mAccent = ($item['type'] ?? '') === 'mega' ? (string) ($item['accent'] ?? 'slate') : 'slate';
                ?>
                <?php if (($item['type'] ?? '') === 'link'): ?>
                    <a href="<?= htmlspecialchars((string) $item['href']) ?>"
                       class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        <?= htmlspecialchars((string) $item['label']) ?>
                    </a>
                <?php else: ?>
                    <?php $accId = htmlspecialchars((string) ($item['id'] ?? '') . '-acc'); ?>
                    <div class="border-b border-slate-100 py-1" data-mobile-accordion data-accent="<?= htmlspecialchars($mAccent) ?>">
                        <button type="button"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-slate-800 hover:bg-slate-50"
                                aria-expanded="false"
                                aria-controls="<?= $accId ?>"
                                data-mobile-accordion-trigger>
                            <span class="flex items-center gap-2">
                                <?php
                                $mi = (string) ($item['icon'] ?? '');
                                if ($mi !== '') {
                                    echo nav_icon_svg($mi, 'h-4 w-4 shrink-0');
                                }
                                ?>
                                <?= htmlspecialchars((string) $item['label']) ?>
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
                                        <?= htmlspecialchars((string) ($link['label'] ?? '')) ?>
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
