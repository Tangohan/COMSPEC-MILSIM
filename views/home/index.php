<?php
$base = url('');
$title = $title ?? __('home.meta_title');
$loggedIn = (bool) \App\Core\Session::get('user_id');
$platformKpis = is_array($platformKpis ?? null) ? $platformKpis : [];
$platformKpiDays = max(1, (int) ($platformKpiDays ?? 30));
$kpiValue = static function (string $key) use ($platformKpis): int {
    return max(0, (int) ($platformKpis[$key] ?? 0));
};
$formatInt = static function (int $value): string {
    [$dec, $thousands] = locale() === 'en' ? ['.', ','] : [',', ' '];

    return number_format($value, 0, $dec, $thousands);
};
$newsletterStatus = (string) ($_GET['newsletter'] ?? '');
$featuredUnits = is_array($featuredUnits ?? null) ? $featuredUnits : [];
$resolveLogo = static function (string $logo) use ($base): string {
    $logo = trim($logo);
    if ($logo === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $logo) === 1) {
        return $logo;
    }
    if (str_starts_with($logo, '/')) {
        return rtrim($base, '/') . $logo;
    }

    return rtrim($base, '/') . '/' . ltrim($logo, '/');
};

/**
 * Clips hero (rotation muted) :
 *   hero-athena(.webm|.mp4)  — alias historique hero-athena-1
 *   hero-athena-2(.webm|.mp4)
 *   hero-athena-3(.webm|.mp4)
 * Les chemins sont toujours branchés ; seuls les fichiers présents partent en preload.
 */
$heroVideoDir = base_path('public/assets/video');
$heroVideoUrlBase = rtrim($base, '/') . '/assets/video';
$heroPosterUrl = rtrim($base, '/') . '/assets/images/fog-team.jpg';
$heroClipGroups = [
    ['hero-athena', 'hero-athena-1'],
    ['hero-athena-2'],
    ['hero-athena-3'],
];
$heroVideoClips = [];
foreach ($heroClipGroups as $candidates) {
    $resolvedStem = null;
    $hasMp4 = false;
    $hasWebm = false;
    foreach ($candidates as $stem) {
        $stemHasMp4 = is_file($heroVideoDir . DIRECTORY_SEPARATOR . $stem . '.mp4');
        $stemHasWebm = is_file($heroVideoDir . DIRECTORY_SEPARATOR . $stem . '.webm');
        if ($stemHasMp4 || $stemHasWebm) {
            $resolvedStem = $stem;
            $hasMp4 = $stemHasMp4;
            $hasWebm = $stemHasWebm;
            break;
        }
    }

    $present = $resolvedStem !== null;
    $slotStem = $resolvedStem ?? $candidates[0];
    $sources = [];
    $encodeStem = static function (string $stem): string {
        // Conserve les tirets ; encode uniquement les caractères réellement réservés.
        return rawurlencode($stem);
    };
    if ($present) {
        if ($hasWebm) {
            $sources[] = ['url' => $heroVideoUrlBase . '/' . $encodeStem($slotStem) . '.webm', 'type' => 'video/webm'];
        }
        if ($hasMp4) {
            $sources[] = ['url' => $heroVideoUrlBase . '/' . $encodeStem($slotStem) . '.mp4', 'type' => 'video/mp4'];
        }
    } else {
        // Chemins attendus : actifs dès dépôt des fichiers (data-present côté serveur).
        foreach ($candidates as $stem) {
            $sources[] = ['url' => $heroVideoUrlBase . '/' . $encodeStem($stem) . '.webm', 'type' => 'video/webm'];
            $sources[] = ['url' => $heroVideoUrlBase . '/' . $encodeStem($stem) . '.mp4', 'type' => 'video/mp4'];
        }
    }

    $heroVideoClips[] = [
        'stem' => $slotStem,
        'present' => $present,
        'sources' => $sources,
    ];
}
$heroPresentClipCount = 0;
foreach ($heroVideoClips as $clip) {
    if (!empty($clip['present'])) {
        $heroPresentClipCount++;
    }
}
$heroVideosPresentOnDisk = $heroPresentClipCount > 0;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $meta_description = $meta_description ?? __('home.meta_description');
    require base_path('views/partials/seo_meta.php');
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/design-system.css'))): ?>
    <link href="<?= htmlspecialchars($base) ?>/assets/css/design-system.css" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/css/home-impact.css" rel="stylesheet">
</head>
<body class="home-impact layout-light bg-[var(--hi-void)] text-[var(--hi-ink)] antialiased selection:bg-emerald-500 selection:text-slate-950 overflow-x-hidden">

    <div id="bodyOverlay" class="overlay fixed inset-0 z-[110] bg-black/60 backdrop-blur-sm" onclick="toggleMenu()"></div>

    <div id="navDrawer" class="drawer-translate fixed top-0 left-0 z-[120] flex h-full w-[min(100%,320px)] flex-col overflow-hidden border-r border-white/10 bg-[#0a0a0a] shadow-2xl">
        <div class="flex shrink-0 items-center justify-between border-b border-white/10 px-5 py-4">
            <span class="hi-kicker text-white/40"><?= htmlspecialchars(__('common.menu'), ENT_QUOTES, 'UTF-8') ?></span>
            <button type="button" onclick="toggleMenu()" class="rounded-lg p-2 text-white/50 transition hover:bg-white/5 hover:text-white" aria-label="<?= htmlspecialchars(__('common.close_menu'), ENT_QUOTES, 'UTF-8') ?>">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-3 py-3" aria-label="<?= htmlspecialchars(__('home.nav_aria'), ENT_QUOTES, 'UTF-8') ?>">
            <?php
            $homeNavLink = 'flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold text-white/80 transition hover:bg-white/5 hover:text-white';
            $homeNavAccent = 'flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/10';
            ?>
            <?php if ($loggedIn): ?>
                <?php
                $scopeEntries = navigation_scope_drawer_entries();
                $scopeGroups = navigation_scope_group_entries($scopeEntries);
                $navCurrentPath = navigation_current_path();
                ?>
                <?php foreach ($scopeGroups as $groupName => $links): ?>
                    <p class="px-3 pt-3 pb-1 hi-kicker text-white/30 first:pt-2"><?= htmlspecialchars($groupName) ?></p>
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
                <a href="<?= $base ?>/" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.home'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= url('login') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.login'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= url('register') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.register'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= url('join') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.join_code'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= url('communities') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>"><?= htmlspecialchars(__('common.communities'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        </nav>
        <div class="shrink-0 space-y-3 border-t border-white/10 p-4">
            <?php if (!$loggedIn): ?>
                <a href="<?= url('register') ?>" onclick="toggleMenu()" class="hi-cta hi-cta-solid w-full"><?= htmlspecialchars(__('common.create_account'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <a href="<?= url('dashboard') ?>" onclick="toggleMenu()" class="hi-cta hi-cta-solid w-full"><?= htmlspecialchars(__('common.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
            <?php require base_path('views/partials/language_switcher.php'); ?>
        </div>
    </div>

    <header class="fixed inset-x-0 top-0 z-[100] border-b border-white/5 bg-black/70 backdrop-blur-md">
        <div class="mx-auto flex h-14 max-w-[100rem] items-center justify-between px-5 md:px-8">
            <button type="button" onclick="toggleMenu()" class="group flex h-6 w-6 flex-col justify-center gap-1.5 outline-none" aria-label="<?= htmlspecialchars(__('common.open_menu'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="h-px w-full bg-white/80 transition group-hover:bg-white"></span>
                <span class="ml-auto h-px w-1/2 bg-white/50 transition group-hover:w-full group-hover:bg-white"></span>
            </button>
            <a href="<?= $base ?>/" class="absolute left-1/2 -translate-x-1/2 text-center">
                <span class="block text-[11px] font-black uppercase tracking-[0.32em] text-white">Athena</span>
            </a>
            <div class="flex items-center gap-4">
                <?php require base_path('views/partials/language_switcher.php'); ?>
                <span id="home-header-clock" class="hidden text-[10px] font-semibold tracking-wide text-white/45 tabular-nums sm:inline">--:--:--</span>
                <?php if (!$loggedIn): ?>
                    <a href="<?= url('login') ?>" class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/70 transition hover:text-white"><?= htmlspecialchars(__('common.enter'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                    <a href="<?= url('hub') ?>" class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 transition hover:text-emerald-300"><?= htmlspecialchars(__('common.ops'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- Un seul hero : fond immersif + marque Athena + accès -->
        <section class="relative flex min-h-[100svh] flex-col justify-end overflow-hidden bg-black pt-14" id="hero" aria-labelledby="hero-title" data-hero-videos-ready="<?= $heroVideosPresentOnDisk ? '1' : '0' ?>">
            <div class="pointer-events-none absolute inset-0" id="heroSlider">
                <div id="heroImageSlides" class="hi-hero-images absolute inset-0">
                    <div class="slide absolute inset-0 opacity-100 transition-opacity duration-1000 ease-in-out">
                        <img id="hero-poster" src="<?= htmlspecialchars($heroPosterUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async" fetchpriority="high">
                    </div>
                    <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                        <img src="<?= $base ?>/assets/images/fog-banner.jpg" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async">
                    </div>
                    <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                        <img src="<?= $base ?>/assets/images/hero-explosion.jpg" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async">
                    </div>
                    <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                        <img src="<?= $base ?>/assets/images/night-team.jpg" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async">
                    </div>
                </div>
                <div id="heroVideoSlides" class="hi-hero-videos absolute inset-0 hi-hero-videos--idle" data-hero-video-count="<?= count($heroVideoClips) ?>" aria-hidden="true">
                    <?php foreach ($heroVideoClips as $clipIndex => $clip): ?>
                    <div class="hi-hero-vslide<?= $clipIndex === 0 ? ' is-active' : '' ?>" data-hero-video-slide data-stem="<?= htmlspecialchars((string) $clip['stem'], ENT_QUOTES, 'UTF-8') ?>" data-present="<?= !empty($clip['present']) ? '1' : '0' ?>">
                        <video
                            class="hi-hero-vslide__video"
                            playsinline
                            muted
                            preload="<?= (!empty($clip['present']) && $clipIndex === 0) ? 'metadata' : 'none' ?>"
                            poster="<?= htmlspecialchars($heroPosterUrl, ENT_QUOTES, 'UTF-8') ?>"
                            data-hero-video
                        >
                            <?php foreach ($clip['sources'] as $source): ?>
                            <source src="<?= htmlspecialchars((string) $source['url'], ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars((string) $source['type'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php endforeach; ?>
                        </video>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/55 to-black/30"></div>
            </div>

            <div class="relative z-10 mx-auto flex w-full max-w-[100rem] flex-col items-start px-5 pb-10 pt-20 md:px-8 md:pb-14">
                <p class="hi-kicker hi-kicker-glitch hi-reveal text-emerald-400/90"><?= htmlspecialchars(__('home.hero_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
                <h1 id="hero-title" class="hi-display hi-hero-brand hi-glitch hi-reveal mt-4 text-white" data-text="Athena" aria-label="Athena">
                    <span class="hi-glitch__main" aria-hidden="true">Athena<span class="hi-glitch__dot">.</span></span>
                </h1>
                <p class="hi-body hi-reveal hi-reveal-delay mt-6 max-w-xl text-white/70">
                    <?= htmlspecialchars(__('home.hero_body'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <div class="hi-reveal hi-reveal-delay mt-8 flex flex-wrap items-center gap-3">
                    <?php if (!$loggedIn): ?>
                        <a href="<?= url('register') ?>" class="hi-cta hi-cta-solid"><?= htmlspecialchars(__('home.cta_create_community'), ENT_QUOTES, 'UTF-8') ?></a>
                        <a href="<?= url('join') ?>" class="hi-cta hi-cta-ghost"><?= htmlspecialchars(__('home.cta_have_code'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else: ?>
                        <a href="<?= url('hub') ?>" class="hi-cta hi-cta-solid"><?= htmlspecialchars(__('home.cta_command_center'), ENT_QUOTES, 'UTF-8') ?></a>
                        <a href="<?= url('dashboard') ?>" class="hi-cta hi-cta-ghost"><?= htmlspecialchars(__('home.cta_personal_brief'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endif; ?>
                    <button type="button" id="btn-enable-immersive" class="hi-body-sm hidden text-left text-emerald-400/80 underline decoration-emerald-500/30 underline-offset-4 hover:text-emerald-300">
                        <?= htmlspecialchars(__('home.enable_video_sound'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>

            <div class="relative z-10 border-t border-white/10 bg-black/40 backdrop-blur-sm">
                <div class="mx-auto flex max-w-[100rem] flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-8">
                    <div class="flex flex-wrap gap-x-6 gap-y-2 hi-body-sm text-[10px] uppercase tracking-[0.14em] text-white/45">
                        <span><?= htmlspecialchars(__('home.pill_multi'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span><?= htmlspecialchars(__('home.pill_stack'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span><?= htmlspecialchars(__('home.pill_atak'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2" aria-label="<?= htmlspecialchars(__('home.slideshow'), ENT_QUOTES, 'UTF-8') ?>" data-hero-pager>
                            <button type="button" onclick="prevSlide()" class="p-1 text-white/35 transition hover:text-white" aria-label="<?= htmlspecialchars(__('home.prev_media'), ENT_QUOTES, 'UTF-8') ?>">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div class="flex gap-2" id="hero-dots" role="tablist" aria-label="<?= htmlspecialchars(__('home.slideshow_dots'), ENT_QUOTES, 'UTF-8') ?>">
                                <?php
                                $heroDotSlots = max(4, count($heroVideoClips));
                                for ($dotIndex = 0; $dotIndex < $heroDotSlots; $dotIndex++):
                                ?>
                                <span class="dot h-1 w-1 rounded-full <?= $dotIndex === 0 ? 'bg-white' : 'bg-white/25' ?> transition-all" data-hero-dot="<?= $dotIndex ?>"<?= $dotIndex >= 4 ? ' hidden' : '' ?>></span>
                                <?php endfor; ?>
                            </div>
                            <button type="button" onclick="nextSlide()" class="p-1 text-white/35 transition hover:text-white" aria-label="<?= htmlspecialchars(__('home.next_media'), ENT_QUOTES, 'UTF-8') ?>">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-3 hi-body-sm text-[10px] uppercase tracking-[0.14em] text-white/35">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-40"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            <span id="timestamp" class="tabular-nums text-white/55">--:--:--</span>
                        </div>
                        <div class="hi-av" id="hero-av" role="group" aria-label="<?= htmlspecialchars(__('home.video_controls'), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="button" id="hero-av-toggle" class="hi-av__btn" aria-label="<?= htmlspecialchars(__('home.play_video'), ENT_QUOTES, 'UTF-8') ?>" data-state="stopped">
                                <svg class="hi-av__icon hi-av__icon--play" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 5.14v13.72L19 12 8 5.14z"/></svg>
                                <svg class="hi-av__icon hi-av__icon--stop" viewBox="0 0 24 24" aria-hidden="true" hidden><rect x="6" y="6" width="12" height="12" fill="currentColor" rx="1"/></svg>
                            </button>
                            <div class="hi-av__audio">
                                <button type="button" id="hero-av-mute" class="hi-av__btn hi-av__btn--mute" aria-label="<?= htmlspecialchars(__('home.mute'), ENT_QUOTES, 'UTF-8') ?>" aria-pressed="true">
                                    <svg class="hi-av__icon hi-av__icon--speaker" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1-3.29-2.5-4.03v8.05c1.5-.74 2.5-2.26 2.5-4.02z"/></svg>
                                    <svg class="hi-av__icon hi-av__icon--muted" viewBox="0 0 24 24" aria-hidden="true" hidden><path fill="currentColor" d="M16.5 12c0-1.77-1-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                                </button>
                                <label class="hi-av__vol-wrap" for="hero-av-volume">
                                    <span class="sr-only"><?= htmlspecialchars(__('home.volume'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <input type="range" id="hero-av-volume" class="hi-av__vol" min="0" max="1" step="0.05" value="0" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($featuredUnits !== []): ?>
        <!-- Logos des unités -->
        <section class="who-we-are bg-white text-slate-900">
            <div class="who-inner">
                <div class="who-title">
                    <h2><?= htmlspecialchars(__('home.units_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
                <div class="who-icons">
                    <?php foreach ($featuredUnits as $unit): ?>
                        <?php
                        $logoSrc = $resolveLogo((string) ($unit['logo_url'] ?? ''));
                        if ($logoSrc === '') {
                            continue;
                        }
                        ?>
                        <a href="<?= htmlspecialchars((string) ($unit['href'] ?? url('communities')), ENT_QUOTES, 'UTF-8') ?>" class="who-item block no-underline transition hover:-translate-y-1">
                            <img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" width="74" height="74" loading="lazy">
                            <h4><?= htmlspecialchars((string) ($unit['name'] ?? __('home.unit_fallback')), ENT_QUOTES, 'UTF-8') ?></h4>
                            <span><?= htmlspecialchars(__('home.public_sheet'), ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <p class="mt-10 text-center">
                    <a href="<?= url('communities') ?>" class="hi-body-sm font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-950"><?= htmlspecialchars(__('home.see_all_communities'), ENT_QUOTES, 'UTF-8') ?></a>
                </p>
            </div>
        </section>
        <?php else: ?>
        <section class="border-y border-slate-200 bg-white py-10 text-center text-slate-900">
            <p class="hi-kicker text-slate-400"><?= htmlspecialchars(__('home.communities_empty_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="hi-body mx-auto mt-3 max-w-lg text-slate-600"><?= htmlspecialchars(__('home.communities_empty_body'), ENT_QUOTES, 'UTF-8') ?></p>
            <a href="<?= url('communities') ?>" class="hi-cta hi-cta-ink mt-6 inline-flex"><?= htmlspecialchars(__('home.communities_registry'), ENT_QUOTES, 'UTF-8') ?></a>
        </section>
        <?php endif; ?>

        <!-- Athena = plateforme · Roleplay = expérience opérateur -->
        <section class="relative overflow-hidden border-y border-slate-200 bg-slate-50 text-slate-900" aria-labelledby="athena-rp-heading">
            <div class="relative mx-auto max-w-6xl px-6 py-16 md:py-20">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="hi-kicker text-emerald-700"><?= htmlspecialchars(__('home.two_worlds'), ENT_QUOTES, 'UTF-8') ?></p>
                    <h2 id="athena-rp-heading" class="hi-display hi-display-md mt-3 text-slate-950">
                        <?= htmlspecialchars(__('home.athena_rp_heading'), ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <p class="mt-4 text-lg font-semibold tracking-tight text-slate-800 md:text-xl">
                        <?= htmlspecialchars(__('home.two_layers'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="hi-body mx-auto mt-4 max-w-2xl text-slate-600">
                        <?= htmlspecialchars(__('home.two_layers_body'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>

                <div class="mt-12 grid gap-5 lg:grid-cols-2 lg:gap-6">
                    <article class="flex flex-col border border-slate-800/80 bg-[#050505] p-7 text-white shadow-xl md:p-8">
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-400/90"><?= htmlspecialchars(__('home.platform'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="mt-3 hi-display text-4xl tracking-tight text-white md:text-5xl">Athena</h3>
                        <p class="mt-2 text-sm font-bold uppercase tracking-[0.14em] text-white/50"><?= htmlspecialchars(__('home.platform_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-5 text-sm leading-relaxed text-white/65">
                            <?= htmlspecialchars(__('home.platform_desc'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <ul class="mt-6 flex-1 space-y-2.5 text-sm text-white/75">
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span><?= htmlspecialchars(__('home.platform_li_1'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span><?= htmlspecialchars(__('home.platform_li_2'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span><?= htmlspecialchars(__('home.platform_li_3'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span><?= htmlspecialchars(__('home.platform_li_4'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span><?= htmlspecialchars(__('home.platform_li_5'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span><?= htmlspecialchars(__('home.platform_li_6'), ENT_QUOTES, 'UTF-8') ?></li>
                        </ul>
                        <p class="mt-8 border-t border-white/10 pt-5 text-xs font-bold uppercase tracking-[0.16em] text-emerald-400/90">
                            <?= htmlspecialchars(__('home.platform_goal'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </article>

                    <article class="flex flex-col border border-emerald-300/80 bg-gradient-to-br from-emerald-50 to-white p-7 text-slate-900 shadow-xl md:p-8">
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-800"><?= htmlspecialchars(__('home.immersion'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="mt-3 hi-display text-4xl tracking-tight text-emerald-950 md:text-5xl">Roleplay</h3>
                        <p class="mt-2 text-sm font-bold uppercase tracking-[0.14em] text-emerald-800/70"><?= htmlspecialchars(__('home.rp_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-5 text-sm leading-relaxed text-slate-600">
                            <?= htmlspecialchars(__('home.rp_desc'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <ul class="mt-6 flex-1 space-y-2.5 text-sm text-slate-700">
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-700" aria-hidden="true"></span><?= htmlspecialchars(__('home.rp_li_1'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-700" aria-hidden="true"></span><?= htmlspecialchars(__('home.rp_li_2'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-700" aria-hidden="true"></span><?= htmlspecialchars(__('home.rp_li_3'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-700" aria-hidden="true"></span><?= htmlspecialchars(__('home.rp_li_4'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-700" aria-hidden="true"></span><?= htmlspecialchars(__('home.rp_li_5'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-700" aria-hidden="true"></span><?= htmlspecialchars(__('home.rp_li_6'), ENT_QUOTES, 'UTF-8') ?></li>
                        </ul>
                        <p class="mt-8 border-t border-emerald-200 pt-5 text-xs font-bold uppercase tracking-[0.16em] text-emerald-900">
                            <?= htmlspecialchars(__('home.rp_goal'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Accès modules (bande restaurée) -->
        <nav class="w-full border-y border-white/5 bg-[#050810] py-8 px-6" aria-label="<?= htmlspecialchars(__('home.modules_access_aria'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mx-auto max-w-6xl">
                <p class="mb-6 text-center hi-kicker text-slate-500"><?= htmlspecialchars(__('home.modules_access'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-6">
                    <?php
                    $strip = $loggedIn
                        ? [
                            ['hub', __('home.eye_ops'), __('home.strip_ops')],
                            ['manoeuvres', __('home.eye_presence'), __('home.strip_presence')],
                            ['communities', __('home.eye_units'), __('home.strip_units')],
                            ['forum', __('home.eye_info'), __('home.strip_info')],
                            ['orbat', __('home.eye_structure'), __('home.strip_structure')],
                            ['c2', __('home.eye_c2'), __('home.strip_c2')],
                            ['formations', __('home.eye_lms'), __('home.strip_lms')],
                            ['enlistment', __('home.eye_rh'), __('home.strip_rh')],
                        ]
                        : [
                            ['login', __('home.eye_access'), __('home.strip_access')],
                            ['register', __('home.eye_account'), __('home.strip_account')],
                            ['join', __('home.eye_code'), __('home.strip_code')],
                            ['communities', __('home.eye_units'), __('home.strip_units')],
                            ['enlistment', __('home.eye_rh'), __('home.strip_rh')],
                        ];
                    foreach ($strip as [$path, $eye, $lab]):
                    ?>
                    <a href="<?= url($path) ?>" class="group flex max-w-[140px] flex-col items-center gap-1 text-center">
                        <span class="text-[7px] font-black uppercase tracking-[0.25em] text-slate-500 transition group-hover:text-emerald-500"><?= htmlspecialchars($eye) ?></span>
                        <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-white transition group-hover:text-emerald-400"><?= htmlspecialchars($lab) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </nav>

        <!-- Storytelling 8 actes -->
        <?php
        $storyActs = [
            ['n' => '01', 'k' => __('home.story_01_k'), 't' => __('home.story_01_t'), 'd' => __('home.story_01_d'), 'bg' => 'bg-[var(--hi-paper)]', 'ink' => 'text-slate-900', 'mute' => 'text-slate-500'],
            ['n' => '02', 'k' => __('home.story_02_k'), 't' => __('home.story_02_t'), 'd' => __('home.story_02_d'), 'bg' => 'bg-[var(--hi-field)]', 'ink' => 'text-white', 'mute' => 'text-white/55'],
            ['n' => '03', 'k' => __('home.story_03_k'), 't' => __('home.story_03_t'), 'd' => __('home.story_03_d'), 'bg' => 'bg-black', 'ink' => 'text-white', 'mute' => 'text-white/50'],
            ['n' => '04', 'k' => __('home.story_04_k'), 't' => __('home.story_04_t'), 'd' => __('home.story_04_d'), 'bg' => 'bg-[var(--hi-field-deep)]', 'ink' => 'text-white', 'mute' => 'text-white/50'],
            ['n' => '05', 'k' => __('home.story_05_k'), 't' => __('home.story_05_t'), 'd' => __('home.story_05_d'), 'bg' => 'bg-[var(--hi-paper)]', 'ink' => 'text-slate-900', 'mute' => 'text-slate-500'],
            ['n' => '06', 'k' => __('home.story_06_k'), 't' => __('home.story_06_t'), 'd' => __('home.story_06_d'), 'bg' => 'bg-black', 'ink' => 'text-white', 'mute' => 'text-white/50'],
            ['n' => '07', 'k' => __('home.story_07_k'), 't' => __('home.story_07_t'), 'd' => __('home.story_07_d'), 'bg' => 'bg-[var(--hi-field)]', 'ink' => 'text-white', 'mute' => 'text-white/55'],
            ['n' => '08', 'k' => __('home.story_08_k'), 't' => __('home.story_08_t'), 'd' => __('home.story_08_d'), 'bg' => 'bg-black', 'ink' => 'text-white', 'mute' => 'text-white/50'],
        ];
        foreach ($storyActs as $act):
        ?>
        <section class="<?= $act['bg'] ?> <?= $act['ink'] ?>">
            <div class="hi-section mx-auto max-w-[100rem]">
                <p class="hi-kicker <?= str_contains($act['bg'], 'paper') || str_contains($act['bg'], 'hi-paper') ? 'text-slate-400' : 'text-emerald-300/80' ?>"><?= htmlspecialchars($act['n']) ?> / <?= htmlspecialchars($act['k']) ?></p>
                <h2 class="hi-display hi-display-lg hi-fade mt-6 max-w-5xl whitespace-pre-line"><?= htmlspecialchars($act['t']) ?></h2>
                <p class="hi-body mt-8 max-w-lg <?= $act['mute'] ?>"><?= htmlspecialchars($act['d']) ?></p>
                <?php if ($act['n'] === '08'): ?>
                <div class="mt-12 flex flex-wrap gap-3">
                    <?php if (!$loggedIn): ?>
                        <a href="<?= url('register') ?>" class="hi-cta hi-cta-solid"><?= htmlspecialchars(__('home.cta_create_account'), ENT_QUOTES, 'UTF-8') ?></a>
                        <a href="<?= url('join') ?>" class="hi-cta hi-cta-ghost"><?= htmlspecialchars(__('home.cta_community_code'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else: ?>
                        <a href="<?= url('hub') ?>" class="hi-cta hi-cta-solid"><?= htmlspecialchars(__('home.cta_open_center'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <!-- Modules -->
        <section class="bg-[var(--hi-paper)] text-slate-900">
            <div class="hi-section mx-auto max-w-[100rem]">
                <div class="grid gap-12 lg:grid-cols-12">
                    <div class="lg:col-span-4">
                        <p class="hi-kicker text-slate-400"><?= htmlspecialchars(__('home.modules_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php $modulesTitleLines = explode("\n", __('home.modules_title'), 2); ?>
                        <h2 class="hi-display hi-display-md mt-4"><?= htmlspecialchars($modulesTitleLines[0], ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars($modulesTitleLines[1] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                    <div class="lg:col-span-8">
                        <?php
                        $modules = [
                            ['n' => '01', 'label' => __('home.mod_01'), 'desc' => __('home.mod_01_d'), 'href' => url('communities')],
                            ['n' => '02', 'label' => __('home.mod_02'), 'desc' => __('home.mod_02_d'), 'href' => url('manoeuvres')],
                            ['n' => '03', 'label' => __('home.mod_03'), 'desc' => __('home.mod_03_d'), 'href' => url('formations')],
                            ['n' => '04', 'label' => __('home.mod_04'), 'desc' => __('home.mod_04_d'), 'href' => url('enlistment')],
                            ['n' => '05', 'label' => __('home.mod_05'), 'desc' => __('home.mod_05_d'), 'href' => url('c2')],
                            ['n' => '06', 'label' => __('home.mod_06'), 'desc' => __('home.mod_06_d'), 'href' => url('boite-reception')],
                        ];
                        foreach ($modules as $m):
                        ?>
                        <a href="<?= htmlspecialchars($m['href']) ?>" class="hi-rule-row group flex flex-col gap-2 py-7 sm:flex-row sm:items-baseline sm:gap-8">
                            <span class="hi-body-sm shrink-0 font-semibold text-slate-400"><?= $m['n'] ?></span>
                            <span class="min-w-[10rem] text-xl font-black italic uppercase tracking-tight text-slate-900 transition group-hover:text-emerald-800 md:text-2xl"><?= htmlspecialchars($m['label']) ?></span>
                            <span class="hi-body-sm flex-1 text-slate-500"><?= htmlspecialchars($m['desc']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Chiffres -->
        <section class="border-y border-white/10 bg-black text-white">
            <div class="hi-section mx-auto max-w-[100rem]">
                <p class="hi-kicker text-white/35"><?= htmlspecialchars(__('home.kpi_kicker', ['days' => (int) $platformKpiDays]), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="mt-10 grid grid-cols-2 gap-10 md:grid-cols-3 lg:grid-cols-6">
                    <?php
                    $kpis = [
                        ['v' => $kpiValue('communities_total'), 'l' => __('home.kpi_communities')],
                        ['v' => $kpiValue('users_active_total'), 'l' => __('home.kpi_members')],
                        ['v' => $kpiValue('forum_posts_in_period'), 'l' => __('home.kpi_forum')],
                        ['v' => $kpiValue('training_completions_in_period'), 'l' => __('home.kpi_training')],
                        ['v' => $kpiValue('enlistments_created_in_period'), 'l' => __('home.kpi_enlistments')],
                        ['v' => $kpiValue('usage_events_in_period'), 'l' => __('home.kpi_interactions')],
                    ];
                    foreach ($kpis as $k):
                    ?>
                    <div>
                        <p class="hi-display text-4xl text-white md:text-5xl"><?= htmlspecialchars($formatInt($k['v'])) ?></p>
                        <p class="hi-body-sm mt-2 text-[10px] uppercase tracking-[0.16em] text-white/40"><?= htmlspecialchars($k['l']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="bg-white text-slate-900">
            <div class="hi-section mx-auto grid max-w-[100rem] gap-12 lg:grid-cols-12 lg:gap-20">
                <div class="lg:col-span-4">
                    <p class="hi-kicker text-slate-400"><?= htmlspecialchars(__('home.faq_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
                    <h2 class="hi-display hi-display-md mt-4"><?= htmlspecialchars(__('home.faq_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
                <div class="lg:col-span-8">
                    <div class="hi-rule-row py-8">
                        <p class="hi-kicker text-slate-400"><?= htmlspecialchars(__('home.faq_1_k'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900 md:text-xl"><?= htmlspecialchars(__('home.faq_1_q'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base"><?= htmlspecialchars(__('home.faq_1_a'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="hi-rule-row py-8">
                        <p class="hi-kicker text-slate-400"><?= htmlspecialchars(__('home.faq_2_k'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900 md:text-xl"><?= htmlspecialchars(__('home.faq_2_q'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base"><?= htmlspecialchars(__('home.faq_2_a'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="hi-rule-row py-8">
                        <p class="hi-kicker text-slate-400"><?= htmlspecialchars(__('home.faq_3_k'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900 md:text-xl"><?= htmlspecialchars(__('home.faq_3_q'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base"><?= htmlspecialchars(__('home.faq_3_a'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="hi-rule-row py-8">
                        <p class="hi-kicker text-slate-400"><?= htmlspecialchars(__('home.faq_4_k'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900 md:text-xl"><?= htmlspecialchars(__('home.faq_4_q'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base"><?= htmlspecialchars(__('home.faq_4_a'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Newsletter -->
        <section id="newsletter" class="hi-newsletter bg-[var(--hi-paper)] text-slate-900" aria-labelledby="newsletter-heading">
            <div class="hi-section mx-auto max-w-[100rem]">
                <div class="hi-newsletter__grid">
                    <div class="hi-newsletter__intro">
                        <p class="hi-kicker text-emerald-700"><?= htmlspecialchars(__('home.newsletter_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php $newsletterTitleLines = explode("\n", __('home.newsletter_title'), 2); ?>
                        <h2 id="newsletter-heading" class="hi-display hi-display-md mt-4 max-w-3xl"><?= htmlspecialchars($newsletterTitleLines[0], ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars($newsletterTitleLines[1] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="hi-body mt-6 max-w-xl text-slate-600">
                            <?= htmlspecialchars(__('home.newsletter_body'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <ul class="hi-newsletter__highlights mt-8" aria-label="<?= htmlspecialchars(__('home.newsletter_highlights'), ENT_QUOTES, 'UTF-8') ?>">
                            <li><?= htmlspecialchars(__('home.newsletter_h1'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li><?= htmlspecialchars(__('home.newsletter_h2'), ENT_QUOTES, 'UTF-8') ?></li>
                            <li><?= htmlspecialchars(__('home.newsletter_h3'), ENT_QUOTES, 'UTF-8') ?></li>
                        </ul>
                    </div>

                    <div class="hi-newsletter__panel">
                        <?php
                        $newsletterMessages = [
                            'confirm_sent' => [
                                'ok' => true,
                                'title' => __('home.nl_confirm_sent_t'),
                                'text' => __('home.nl_confirm_sent_b'),
                            ],
                            'confirmed' => [
                                'ok' => true,
                                'title' => __('home.nl_confirmed_t'),
                                'text' => __('home.nl_confirmed_b'),
                            ],
                            'unsubscribed' => [
                                'ok' => true,
                                'title' => __('home.nl_unsubscribed_t'),
                                'text' => __('home.nl_unsubscribed_b'),
                            ],
                            'invalid_email' => [
                                'ok' => false,
                                'title' => __('home.nl_invalid_email_t'),
                                'text' => __('home.nl_invalid_email_b'),
                            ],
                            'csrf' => [
                                'ok' => false,
                                'title' => __('home.nl_csrf_t'),
                                'text' => __('home.nl_csrf_b'),
                            ],
                            'confirm_invalid' => [
                                'ok' => false,
                                'title' => __('home.nl_confirm_invalid_t'),
                                'text' => __('home.nl_confirm_invalid_b'),
                            ],
                            'unsubscribe_invalid' => [
                                'ok' => false,
                                'title' => __('home.nl_unsub_invalid_t'),
                                'text' => __('home.nl_unsub_invalid_b'),
                            ],
                            'schema_missing' => [
                                'ok' => false,
                                'title' => __('home.nl_schema_t'),
                                'text' => __('home.nl_schema_b'),
                            ],
                        ];
                        $newsletterFeedback = $newsletterMessages[$newsletterStatus] ?? null;
                        $newsletterFormDisabled = $newsletterStatus === 'schema_missing';
                        $newsletterShowForm = !in_array($newsletterStatus, ['confirm_sent', 'confirmed'], true);
                        ?>

                        <?php if ($newsletterFeedback): ?>
                            <div class="hi-newsletter__status <?= $newsletterFeedback['ok'] ? 'hi-newsletter__status--ok' : 'hi-newsletter__status--error' ?>" role="status" aria-live="polite">
                                <p class="hi-newsletter__status-title"><?= htmlspecialchars($newsletterFeedback['title']) ?></p>
                                <p class="hi-newsletter__status-text"><?= htmlspecialchars($newsletterFeedback['text']) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($newsletterShowForm): ?>
                            <form
                                id="newsletter-form"
                                method="post"
                                action="<?= url('newsletter/subscribe') ?>"
                                class="hi-newsletter__form"
                                novalidate
                                data-newsletter-form
                                <?= $newsletterFormDisabled ? 'aria-disabled="true"' : '' ?>
                            >
                                <?= \App\Core\Csrf::field() ?>

                                <div class="hi-newsletter__field">
                                    <label for="newsletter-email" class="hi-newsletter__label"><?= htmlspecialchars(__('home.newsletter_email'), ENT_QUOTES, 'UTF-8') ?></label>
                                    <input
                                        id="newsletter-email"
                                        name="email"
                                        type="email"
                                        required
                                        maxlength="255"
                                        autocomplete="email"
                                        autocapitalize="none"
                                        spellcheck="false"
                                        inputmode="email"
                                        placeholder="<?= htmlspecialchars(__('home.newsletter_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                                        class="hi-newsletter__input"
                                        <?= $newsletterFormDisabled ? 'disabled' : '' ?>
                                        aria-describedby="newsletter-help newsletter-privacy"
                                    >
                                    <p id="newsletter-help" class="hi-newsletter__help">
                                        <?= htmlspecialchars(__('home.newsletter_help'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p id="newsletter-email-error" class="hi-newsletter__field-error" hidden role="alert">
                                        <?= htmlspecialchars(__('home.newsletter_email_error'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    class="hi-cta hi-cta-ink hi-newsletter__submit"
                                    <?= $newsletterFormDisabled ? 'disabled' : '' ?>
                                    data-newsletter-submit
                                    data-label-idle="<?= htmlspecialchars(__('home.newsletter_submit'), ENT_QUOTES, 'UTF-8') ?>"
                                    data-label-loading="<?= htmlspecialchars(__('home.newsletter_loading'), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <span data-newsletter-submit-label><?= htmlspecialchars(__('home.newsletter_submit'), ENT_QUOTES, 'UTF-8') ?></span>
                                </button>

                                <p id="newsletter-privacy" class="hi-newsletter__privacy">
                                    <?= htmlspecialchars(__('home.newsletter_privacy'), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </form>
                        <?php elseif ($newsletterStatus === 'confirm_sent'): ?>
                            <p class="hi-newsletter__empty-hint">
                                <?= htmlspecialchars(__('home.newsletter_confirm_hint'), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <p class="mt-4">
                                <a href="<?= htmlspecialchars(url('/#newsletter'), ENT_QUOTES, 'UTF-8') ?>" class="hi-newsletter__retry" data-newsletter-retry>
                                    <?= htmlspecialchars(__('home.newsletter_other_email'), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </p>
                        <?php else: ?>
                            <p class="hi-newsletter__empty-hint">
                                <?= htmlspecialchars(__('home.newsletter_thanks'), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal immersion RGPD / préférences -->
    <dialog id="immersive-consent" class="max-w-md rounded-2xl border border-white/10 bg-[#0a0a0a] p-0 text-white shadow-2xl backdrop:bg-black/70">
        <div class="p-6 sm:p-8">
            <p class="hi-kicker text-emerald-400/80"><?= htmlspecialchars(__('home.immersive_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
            <h2 class="mt-3 text-2xl font-black italic uppercase tracking-tight"><?= htmlspecialchars(__('home.immersive_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="hi-body mt-4 text-sm text-white/60"><?= htmlspecialchars(__('home.immersive_body'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <button type="button" id="immersive-yes" class="hi-cta hi-cta-solid flex-1"><?= htmlspecialchars(__('home.immersive_yes'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" id="immersive-no" class="hi-cta hi-cta-ghost flex-1"><?= htmlspecialchars(__('home.immersive_no'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </dialog>

    <footer class="border-t border-white/10 bg-black py-10 text-white">
        <div class="mx-auto flex max-w-[100rem] flex-col gap-8 px-5 md:flex-row md:items-start md:justify-between md:px-8">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em]">Athena</p>
                <p class="hi-body-sm mt-3 max-w-xs text-white/45"><?= htmlspecialchars(__('home.footer_tagline'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <nav class="flex max-w-xl flex-wrap gap-x-5 gap-y-2 text-xs" aria-label="<?= htmlspecialchars(__('home.footer_legal_aria'), ENT_QUOTES, 'UTF-8') ?>">
                <?php
                $legal_link_class = 'text-white/40 transition hover:text-emerald-400 font-medium';
                require base_path('views/partials/legal_site_links.php');
                ?>
            </nav>
        </div>
    </footer>

    <?php require base_path('views/partials/cookie_banner.php'); ?>
    <?php require base_path('views/partials/demo_nda_session_widget.php'); ?>

    <script>
        var hiI18n = <?= json_encode([
            'playVideo' => __('home.play_video'),
            'pauseVideo' => __('home.pause_video'),
            'mute' => __('home.mute'),
            'unmute' => __('home.unmute'),
            'newsletterLoading' => __('home.newsletter_loading'),
            'newsletterSubmit' => __('home.newsletter_submit'),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;

        function toggleMenu() {
            document.body.classList.toggle('drawer-open');
            document.body.style.overflow = document.body.classList.contains('drawer-open') ? 'hidden' : '';
        }

        function tickClocks() {
            var now = new Date();
            var t = now.getHours().toString().padStart(2, '0') + ':' +
                now.getMinutes().toString().padStart(2, '0') + ':' +
                now.getSeconds().toString().padStart(2, '0');
            var ts = document.getElementById('timestamp');
            if (ts) ts.textContent = t;
            var hc = document.getElementById('home-header-clock');
            if (hc) hc.textContent = t;
        }
        tickClocks();
        setInterval(tickClocks, 1000);

        (function heroMedia() {
            var KEY = 'athena_immersive_v1';
            var VOL_KEY = 'athena_immersive_vol';
            var IMAGE_INTERVAL_MS = 6000;
            var VIDEO_MAX_MS = 18000;
            var dlg = document.getElementById('immersive-consent');
            var btnLater = document.getElementById('btn-enable-immersive');
            var imageRoot = document.getElementById('heroImageSlides');
            var videoRoot = document.getElementById('heroVideoSlides');
            var imageSlides = imageRoot ? imageRoot.querySelectorAll('.slide') : [];
            var candidateSlides = videoRoot ? Array.prototype.slice.call(videoRoot.querySelectorAll('[data-hero-video-slide]')) : [];
            var videoSlides = [];
            var dots = document.querySelectorAll('#hero-dots .dot');
            var toggleBtn = document.getElementById('hero-av-toggle');
            var muteBtn = document.getElementById('hero-av-mute');
            var volInput = document.getElementById('hero-av-volume');
            var iconPlay = toggleBtn ? toggleBtn.querySelector('.hi-av__icon--play') : null;
            var iconStop = toggleBtn ? toggleBtn.querySelector('.hi-av__icon--stop') : null;
            var iconSpeaker = muteBtn ? muteBtn.querySelector('.hi-av__icon--speaker') : null;
            var iconMuted = muteBtn ? muteBtn.querySelector('.hi-av__icon--muted') : null;
            var reducedMotion = false;
            try {
                reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            } catch (e) {}
            var mode = 'images';
            var current = 0;
            var imageTimer = null;
            var videoSafetyTimer = null;
            var videoDecodeTimer = null;
            var rotationPaused = false;
            var lastVol = 0.55;
            var saved = null;
            var withSound = false;

            try { saved = localStorage.getItem(KEY); } catch (e) {}
            // Ancien « off » = refus du son immersif, pas refus des vidéos muted.
            if (saved === 'off') {
                saved = 'silent';
                try { localStorage.setItem(KEY, 'silent'); } catch (e) {}
            }
            try {
                var storedVol = localStorage.getItem(VOL_KEY);
                if (storedVol !== null) lastVol = Math.min(1, Math.max(0, parseFloat(storedVol) || 0));
            } catch (e) {}

            function activeVideo() {
                if (!videoSlides.length) return null;
                var slide = videoSlides[current] || videoSlides[0];
                return slide ? slide.querySelector('[data-hero-video]') : null;
            }

            function syncDots() {
                var count = mode === 'videos' ? videoSlides.length : imageSlides.length;
                for (var i = 0; i < dots.length; i++) {
                    var on = i === current && i < count;
                    dots[i].classList.toggle('bg-white', on);
                    dots[i].classList.toggle('bg-white/25', !on);
                    dots[i].hidden = i >= count;
                }
            }

            function updateImageSlide(index) {
                if (!imageSlides.length) return;
                var img = imageSlides[current] ? imageSlides[current].querySelector('img') : null;
                if (img) img.style.transform = 'scale(1)';
                if (imageSlides[current]) {
                    imageSlides[current].classList.replace('opacity-100', 'opacity-0');
                }
                current = (index + imageSlides.length) % imageSlides.length;
                if (imageSlides[current]) {
                    imageSlides[current].classList.replace('opacity-0', 'opacity-100');
                }
                img = imageSlides[current] ? imageSlides[current].querySelector('img') : null;
                if (img) img.style.transform = 'scale(1.08)';
                syncDots();
            }

            function clearVideoSafety() {
                if (videoSafetyTimer) {
                    clearTimeout(videoSafetyTimer);
                    videoSafetyTimer = null;
                }
                if (videoDecodeTimer) {
                    clearTimeout(videoDecodeTimer);
                    videoDecodeTimer = null;
                }
            }

            function stopImageSlider() {
                if (imageTimer) {
                    clearInterval(imageTimer);
                    imageTimer = null;
                }
            }

            function startImageSlider() {
                stopImageSlider();
                if (reducedMotion || imageSlides.length < 2) return;
                imageTimer = setInterval(function () {
                    updateImageSlide(current + 1);
                }, IMAGE_INTERVAL_MS);
            }

            function setImageStandby(standby) {
                if (!imageRoot) return;
                imageRoot.classList.toggle('hi-hero-images--standby', !!standby);
                if (standby) {
                    imageSlides.forEach(function (s) {
                        s.classList.add('opacity-0');
                        s.classList.remove('opacity-100');
                    });
                } else if (imageSlides.length) {
                    imageSlides.forEach(function (s, i) {
                        s.classList.toggle('opacity-100', i === current);
                        s.classList.toggle('opacity-0', i !== current);
                    });
                }
            }

            function revealVideoFrame(video) {
                if (!video || video.videoWidth <= 0) return false;
                video.removeAttribute('poster');
                if (mode === 'videos') setImageStandby(true);
                return true;
            }

            function applyAudioToAll(muted, volume) {
                videoSlides.forEach(function (slide) {
                    var v = slide.querySelector('[data-hero-video]');
                    if (!v) return;
                    v.muted = muted;
                    v.volume = volume;
                });
            }

            function syncAvUi() {
                var video = activeVideo();
                var playing = !!(mode === 'videos' && video && !video.paused);
                if (toggleBtn) {
                    toggleBtn.setAttribute('data-state', playing ? 'playing' : 'stopped');
                    toggleBtn.setAttribute('aria-label', playing ? hiI18n.pauseVideo : hiI18n.playVideo);
                    if (iconPlay) iconPlay.hidden = playing;
                    if (iconStop) iconStop.hidden = !playing;
                }
                if (!video) {
                    if (muteBtn) {
                        muteBtn.setAttribute('aria-pressed', 'true');
                        muteBtn.setAttribute('aria-label', hiI18n.unmute);
                        if (iconSpeaker) iconSpeaker.hidden = true;
                        if (iconMuted) iconMuted.hidden = false;
                    }
                    return;
                }
                var muted = video.muted || video.volume === 0;
                var vol = muted ? 0 : video.volume;
                if (volInput) {
                    volInput.value = String(vol);
                    volInput.style.setProperty('--hi-av-pct', Math.round(vol * 100) + '%');
                    volInput.setAttribute('aria-valuenow', String(Math.round(vol * 100)));
                }
                if (muteBtn) {
                    muteBtn.setAttribute('aria-pressed', muted ? 'true' : 'false');
                    muteBtn.setAttribute('aria-label', muted ? hiI18n.unmute : hiI18n.mute);
                    if (iconSpeaker) iconSpeaker.hidden = muted;
                    if (iconMuted) iconMuted.hidden = !muted;
                }
            }

            function persistVol(vol) {
                try { localStorage.setItem(VOL_KEY, String(vol)); } catch (e) {}
            }

            function applyVolume(vol, unmute) {
                vol = Math.min(1, Math.max(0, vol));
                if (vol > 0) lastVol = vol;
                withSound = unmute ? vol > 0 : vol > 0;
                var muted = !withSound || vol === 0;
                applyAudioToAll(muted, muted ? 0 : vol);
                persistVol(vol > 0 ? vol : lastVol);
                try {
                    localStorage.setItem(KEY, muted ? 'silent' : 'full');
                } catch (e) {}
                syncAvUi();
            }

            function playVideoAt(index, opts) {
                opts = opts || {};
                if (!videoSlides.length) return;
                clearVideoSafety();
                var next = (index + videoSlides.length) % videoSlides.length;
                var prevSlide = videoSlides[current];
                var nextSlide = videoSlides[next];
                var prevVideo = prevSlide ? prevSlide.querySelector('[data-hero-video]') : null;
                var nextVideo = nextSlide ? nextSlide.querySelector('[data-hero-video]') : null;

                if (prevVideo && prevVideo !== nextVideo) {
                    prevVideo.pause();
                    try { prevVideo.currentTime = 0; } catch (e) {}
                }
                if (prevSlide) prevSlide.classList.remove('is-active');
                current = next;
                if (nextSlide) nextSlide.classList.add('is-active');
                syncDots();

                if (!nextVideo) return;

                if (withSound) {
                    nextVideo.muted = false;
                    nextVideo.volume = lastVol > 0 ? lastVol : 0.55;
                } else {
                    nextVideo.muted = true;
                    nextVideo.volume = 0;
                }

                if (opts.reset !== false) {
                    try { nextVideo.currentTime = 0; } catch (e) {}
                }

                if (rotationPaused) {
                    syncAvUi();
                    return;
                }

                pruneUnplayableSources(nextVideo);

                function attemptPlayMutedFallback() {
                    nextVideo.muted = true;
                    nextVideo.volume = 0;
                    withSound = false;
                    return nextVideo.play().catch(function () {
                        var sources = nextVideo.querySelectorAll('source');
                        if (sources.length > 1) {
                            sources[0].remove();
                            try {
                                nextVideo.load();
                                return nextVideo.play().catch(function () {
                                    handleVideoError(nextSlide);
                                });
                            } catch (e) {
                                handleVideoError(nextSlide);
                            }
                            return;
                        }
                        handleVideoError(nextSlide);
                    });
                }

                var playPromise = nextVideo.play();
                if (playPromise && playPromise.catch) {
                    playPromise.catch(function () {
                        attemptPlayMutedFallback().then(function () {
                            syncAvUi();
                        }).catch(function () {
                            syncAvUi();
                        });
                    });
                }

                videoDecodeTimer = setTimeout(function () {
                    videoDecodeTimer = null;
                    if (mode !== 'videos' || rotationPaused) return;
                    if (!nextVideo || nextVideo.videoWidth > 0) return;
                    handleVideoError(nextSlide);
                }, 4000);

                if (videoSlides.length > 1) {
                    videoSafetyTimer = setTimeout(function () {
                        if (!rotationPaused && mode === 'videos') {
                            playVideoAt(current + 1);
                        }
                    }, VIDEO_MAX_MS);
                }
                syncAvUi();
            }

            function onVideoEnded(event) {
                if (mode !== 'videos' || rotationPaused || videoSlides.length < 2) return;
                if (event && event.target !== activeVideo()) return;
                playVideoAt(current + 1);
            }

            function handleVideoError(slide) {
                if (!slide) return;
                var idx = videoSlides.indexOf(slide);
                if (idx === -1) return;
                videoSlides.splice(idx, 1);
                slide.hidden = true;
                if (!videoSlides.length) {
                    enableImageMode();
                    return;
                }
                if (current >= videoSlides.length) current = 0;
                playVideoAt(current, { reset: true });
            }

            function enableVideoMode(soundOn) {
                if (!videoSlides.length || reducedMotion) {
                    enableImageMode();
                    return;
                }
                withSound = !!soundOn;
                try { localStorage.setItem(KEY, withSound ? 'full' : 'silent'); } catch (e) {}
                mode = 'videos';
                stopImageSlider();
                if (videoRoot) {
                    videoRoot.classList.remove('hi-hero-videos--idle');
                    videoRoot.setAttribute('aria-hidden', 'false');
                }
                rotationPaused = false;
                if (videoSlides.length === 1) {
                    var only = videoSlides[0].querySelector('[data-hero-video]');
                    if (only) only.loop = true;
                } else {
                    videoSlides.forEach(function (slide) {
                        var v = slide.querySelector('[data-hero-video]');
                        if (v) v.loop = false;
                    });
                }
                current = 0;
                playVideoAt(0, { reset: true });
                if (btnLater) {
                    if (withSound) btnLater.classList.add('hidden');
                    else btnLater.classList.remove('hidden');
                }
                syncAvUi();
            }

            function enableImageMode() {
                mode = 'images';
                clearVideoSafety();
                rotationPaused = false;
                videoSlides.forEach(function (slide) {
                    var v = slide.querySelector('[data-hero-video]');
                    if (v) {
                        v.pause();
                        try { v.currentTime = 0; } catch (e) {}
                    }
                    slide.classList.remove('is-active');
                });
                if (videoRoot) {
                    videoRoot.classList.add('hi-hero-videos--idle');
                    videoRoot.setAttribute('aria-hidden', 'true');
                }
                setImageStandby(false);
                current = 0;
                updateImageSlide(0);
                startImageSlider();
                syncAvUi();
            }

            function togglePlayback() {
                if (!videoSlides.length) return;
                if (mode !== 'videos') {
                    enableVideoMode(saved === 'full');
                    return;
                }
                var video = activeVideo();
                if (!video) return;
                if (video.paused) {
                    rotationPaused = false;
                    var playPromise = video.play();
                    if (playPromise && playPromise.catch) playPromise.catch(function () {});
                    if (videoSlides.length > 1) {
                        clearVideoSafety();
                        videoSafetyTimer = setTimeout(function () {
                            if (!rotationPaused && mode === 'videos') playVideoAt(current + 1);
                        }, Math.max(2000, VIDEO_MAX_MS - (video.currentTime * 1000 || 0)));
                    }
                    syncAvUi();
                    return;
                }
                rotationPaused = true;
                clearVideoSafety();
                video.pause();
                syncAvUi();
            }

            window.nextSlide = function () {
                if (mode === 'videos' && videoSlides.length) {
                    playVideoAt(current + 1);
                    return;
                }
                updateImageSlide(current + 1);
            };
            window.prevSlide = function () {
                if (mode === 'videos' && videoSlides.length) {
                    playVideoAt(current - 1);
                    return;
                }
                updateImageSlide(current - 1);
            };

            function canPlayMime(mime) {
                if (!mime) return true;
                try {
                    var probe = document.createElement('video');
                    var answer = probe.canPlayType(mime);
                    return answer === 'probably' || answer === 'maybe';
                } catch (e) {
                    return true;
                }
            }

            function pruneUnplayableSources(video) {
                if (!video) return false;
                var sources = Array.prototype.slice.call(video.querySelectorAll('source'));
                var kept = 0;
                sources.forEach(function (source) {
                    var type = source.getAttribute('type') || '';
                    if (type && !canPlayMime(type)) {
                        source.remove();
                    } else {
                        kept++;
                    }
                });
                return kept > 0;
            }

            function bindVideoEvents() {
                videoSlides.forEach(function (slide) {
                    var v = slide.querySelector('[data-hero-video]');
                    if (!v) return;
                    v.addEventListener('ended', onVideoEnded);
                    v.addEventListener('play', syncAvUi);
                    v.addEventListener('pause', syncAvUi);
                    v.addEventListener('volumechange', syncAvUi);
                    v.addEventListener('loadeddata', function () {
                        if (mode === 'videos' && v === activeVideo()) revealVideoFrame(v);
                    });
                    v.addEventListener('playing', function () {
                        if (mode === 'videos' && v === activeVideo()) revealVideoFrame(v);
                    });
                    v.addEventListener('error', function () {
                        if (mode === 'videos') handleVideoError(slide);
                    });
                });
            }

            function startHero() {
                if (reducedMotion || !videoSlides.length) {
                    enableImageMode();
                    if (btnLater && videoSlides.length) btnLater.classList.remove('hidden');
                    return;
                }
                enableVideoMode(saved === 'full');
                // Dialogue = opt-in son uniquement (vidéos muted déjà actives).
                if (saved !== 'silent' && saved !== 'full' && dlg && typeof dlg.showModal === 'function') {
                    window.setTimeout(function () { dlg.showModal(); }, 900);
                }
            }

            if (toggleBtn) toggleBtn.addEventListener('click', togglePlayback);
            if (muteBtn) muteBtn.addEventListener('click', function () {
                if (mode !== 'videos') {
                    enableVideoMode(true);
                    return;
                }
                var video = activeVideo();
                if (!video) return;
                if (video.muted || video.volume === 0) applyVolume(lastVol > 0 ? lastVol : 0.55, true);
                else applyVolume(0, true);
            });
            if (volInput) {
                volInput.addEventListener('input', function () {
                    var v = parseFloat(volInput.value) || 0;
                    if (mode !== 'videos' && v > 0) enableVideoMode(true);
                    applyVolume(v, true);
                });
            }

            var yes = document.getElementById('immersive-yes');
            var no = document.getElementById('immersive-no');
            if (yes) yes.addEventListener('click', function () { enableVideoMode(true); if (dlg) dlg.close(); });
            if (no) no.addEventListener('click', function () {
                enableVideoMode(false);
                if (dlg) dlg.close();
            });
            if (btnLater) btnLater.addEventListener('click', function () { enableVideoMode(true); });

            if (!reducedMotion && candidateSlides.length) {
                setImageStandby(false);
                if (imageSlides.length) {
                    imageSlides[0].classList.add('opacity-100');
                    imageSlides[0].classList.remove('opacity-0');
                }

                var presentSlides = candidateSlides.filter(function (slide) {
                    return slide.getAttribute('data-present') === '1';
                });
                videoSlides = presentSlides.length ? presentSlides.slice() : candidateSlides.slice();
                candidateSlides.forEach(function (slide) {
                    slide.hidden = videoSlides.indexOf(slide) === -1;
                });
                bindVideoEvents();
                enableImageMode();
                startHero();
            } else {
                videoSlides = [];
                bindVideoEvents();
                startHero();
            }

            try {
                var motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
                var onMotionChange = function (event) {
                    reducedMotion = !!event.matches;
                    if (event.matches) {
                        enableImageMode();
                    } else if (videoSlides.length) {
                        enableVideoMode(saved === 'full');
                    }
                };
                if (motionQuery.addEventListener) motionQuery.addEventListener('change', onMotionChange);
                else if (motionQuery.addListener) motionQuery.addListener(onMotionChange);
            } catch (e) {}
        })();

        (function newsletterForm() {
            var form = document.querySelector('[data-newsletter-form]');
            if (!form) return;

            var email = form.querySelector('#newsletter-email');
            var errorEl = document.getElementById('newsletter-email-error');
            var submit = form.querySelector('[data-newsletter-submit]');
            var label = form.querySelector('[data-newsletter-submit-label]');
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            function setInvalid(isInvalid) {
                if (!email) return;
                email.classList.toggle('is-invalid', isInvalid);
                email.setAttribute('aria-invalid', isInvalid ? 'true' : 'false');
                if (errorEl) errorEl.hidden = !isInvalid;
            }

            function setLoading(isLoading) {
                if (!submit || !label) return;
                submit.classList.toggle('is-loading', isLoading);
                submit.disabled = isLoading;
                label.textContent = isLoading
                    ? (submit.getAttribute('data-label-loading') || hiI18n.newsletterLoading)
                    : (submit.getAttribute('data-label-idle') || hiI18n.newsletterSubmit);
            }

            if (email) {
                email.addEventListener('input', function () {
                    if (email.value.trim() !== '') setInvalid(false);
                });
            }

            form.addEventListener('submit', function (event) {
                if (submit && submit.disabled && !submit.classList.contains('is-loading')) {
                    event.preventDefault();
                    return;
                }
                var value = email ? email.value.trim() : '';
                if (!value || !emailPattern.test(value)) {
                    event.preventDefault();
                    setInvalid(true);
                    if (email) email.focus();
                    return;
                }
                setInvalid(false);
                setLoading(true);
            });
        })();
    </script>
    <?php require base_path('views/partials/mirror_trap_link.php'); ?>
</body>
</html>
