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
/** Sources écartées faute de codec décodable — diagnostic pour l'équipe. */
$heroVideoRejected = [];
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
            // Un .mp4 encodé en HEVC (export Apple) est accepté par le navigateur, qui
            // décode l'audio et n'affiche rien : lecteur noir au lieu du repli sur
            // l'affiche. On sonde le codec réel et on écarte la source indécodable.
            $mp4Path = $heroVideoDir . DIRECTORY_SEPARATOR . $slotStem . '.mp4';
            $probe = \App\Support\Media\VideoSourceProbe::inspect($mp4Path);
            if ($probe['playable']) {
                $sources[] = [
                    'url' => $heroVideoUrlBase . '/' . $encodeStem($slotStem) . '.mp4',
                    'type' => $probe['mime'],
                ];
            } else {
                $hasMp4 = false;
                $heroVideoRejected[] = [
                    'stem' => $slotStem,
                    'codec' => $probe['codec'],
                    'brand' => $probe['brand'],
                ];
            }
        }
        if (!$hasWebm && !$hasMp4) {
            // Plus aucune source exploitable : l'emplacement retombe sur l'affiche.
            $present = false;
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
        'bytes' => ($present && $hasMp4 && is_file($heroVideoDir . DIRECTORY_SEPARATOR . $slotStem . '.mp4'))
            ? (int) filesize($heroVideoDir . DIRECTORY_SEPARATOR . $slotStem . '.mp4')
            : 0,
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
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
/** Unités affichées sur l’accueil ; le registre complet reste accessible via « Voir toutes les communautés ». */
$homeUnitsLimit = 12;
$homeUnits = array_slice($featuredUnits, 0, $homeUnitsLimit);
$kpis = [
    ['v' => $kpiValue('communities_total'), 'l' => __('home.kpi_communities')],
    ['v' => $kpiValue('users_active_total'), 'l' => __('home.kpi_members')],
    ['v' => $kpiValue('forum_posts_in_period'), 'l' => __('home.kpi_forum')],
    ['v' => $kpiValue('training_completions_in_period'), 'l' => __('home.kpi_training')],
    ['v' => $kpiValue('enlistments_created_in_period'), 'l' => __('home.kpi_enlistments')],
    ['v' => $kpiValue('usage_events_in_period'), 'l' => __('home.kpi_interactions')],
];
/* Un compteur à zéro n’apporte rien au visiteur : on n’affiche que les chiffres réels non nuls. */
$kpisShown = array_values(array_filter($kpis, static fn (array $k): bool => $k['v'] > 0));
?>
<!DOCTYPE html>
<html lang="<?= $e(html_lang()) ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $meta_description = $meta_description ?? __('home.meta_description');
    $og_image = $og_image ?? (rtrim($base, '/') . '/assets/images/fog-team.jpg');
    require base_path('views/partials/seo_meta.php');
?>
    <meta name="theme-color" content="#050505">
    <link rel="apple-touch-icon" href="<?= $e($base) ?>/assets/icons/athena-192.png">
    <link rel="preload" as="image" href="<?= $e($heroPosterUrl) ?>" fetchpriority="high">
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Athena Comspec',
        'url' => rtrim((string) url(''), '/'),
        'logo' => rtrim((string) url(''), '/') . '/assets/icons/athena-192.png',
        'description' => __('home.meta_description'),
        'sameAs' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => __('home.faq_1_q'),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => __('home.faq_1_a')],
            ],
            [
                '@type' => 'Question',
                'name' => __('home.faq_2_q'),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => __('home.faq_2_a')],
            ],
            [
                '@type' => 'Question',
                'name' => __('home.faq_3_q'),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => __('home.faq_3_a')],
            ],
            [
                '@type' => 'Question',
                'name' => __('home.faq_4_q'),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => __('home.faq_4_a')],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php /* CSS utilitaire compilé (public/assets/css/tailwind.css) ; CDN seulement si le build est absent. */ ?>
    <?php require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/design-system.css'))): ?>
    <link href="<?= $e($base) ?>/assets/css/design-system.css" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= $e($base) ?>/assets/css/styles.css" rel="stylesheet">
    <link href="<?= $e(asset_url('assets/css/home-impact.css')) ?>" rel="stylesheet">
</head>
<body class="home-impact layout-light bg-[var(--hi-void)] text-[var(--hi-ink)] antialiased selection:bg-emerald-500 selection:text-slate-950 overflow-x-hidden">

    <a href="#contenu" class="hi-skip"><?= $e(__('common.skip_to_content')) ?></a>

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
                <a href="<?= $e($base) ?>/" class="hi-wordmark" aria-label="Athena — <?= $e(__('common.home')) ?>">Athena<span class="hi-wordmark__dot" aria-hidden="true">.</span></a>
            </div>
            <nav class="hi-topbar__nav" aria-label="<?= $e(__('home.nav_aria')) ?>">
                <a href="<?= $e(url('communities')) ?>"><?= $e(__('common.communities')) ?></a>
                <a href="<?= $e(url('sse')) ?>"><?= $e(__('site.sse')) ?></a>
                <a href="<?= $e(url('atak-natif')) ?>"><?= $e(__('site.atak_native')) ?></a>
                <a href="<?= $e(url('a-propos')) ?>"><?= $e(__('site.about')) ?></a>
            </nav>
            <div class="hi-topbar__right">
                <?php $localeSwitcherClass = 'hi-topbar__lang'; require base_path('views/partials/language_switcher.php'); unset($localeSwitcherClass); ?>
                <span id="home-header-clock" class="hi-topbar__clock" aria-hidden="true">--:--:--</span>
                <?php if (!$loggedIn): ?>
                    <a href="<?= $e(url('login')) ?>" class="hi-topbar__link"><?= $e(__('common.login')) ?></a>
                    <a href="<?= $e(url('register')) ?>" class="hi-cta hi-cta-solid hi-cta--sm hi-topbar__cta"><?= $e(__('home.cta_create_community')) ?></a>
                <?php else: ?>
                    <a href="<?= url('dashboard') ?>" class="hi-topbar__link hi-topbar__link--accent"><?= htmlspecialchars(__('common.ops'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= $e(url('hub')) ?>" class="hi-cta hi-cta-solid hi-cta--sm hi-topbar__cta"><?= $e(__('home.cta_command_center')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main id="contenu" tabindex="-1">
        <!-- Hero : fond immersif + marque + proposition + accès -->
        <section class="relative flex min-h-[100svh] flex-col justify-end overflow-hidden bg-black" id="hero" aria-labelledby="hero-title" data-hero-videos-ready="<?= $heroVideosPresentOnDisk ? '1' : '0' ?>">
            <div class="pointer-events-none absolute inset-0" id="heroSlider">
                <div id="heroImageSlides" class="hi-hero-images absolute inset-0">
                    <div class="slide absolute inset-0 opacity-100 transition-opacity duration-1000 ease-in-out">
                        <img id="hero-poster" src="<?= $e($heroPosterUrl) ?>" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async" fetchpriority="high">
                    </div>
                    <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                        <img src="<?= $e($base) ?>/assets/images/fog-banner.jpg" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async" fetchpriority="low">
                    </div>
                    <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                        <img src="<?= $e($base) ?>/assets/images/hero-explosion.jpg" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async" fetchpriority="low">
                    </div>
                    <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                        <img src="<?= $e($base) ?>/assets/images/night-team.jpg" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async" fetchpriority="low">
                    </div>
                </div>
                <?php /* Aucune source décodable : on ne rend aucun emplacement vidéo, le
                         carrousel d'images porte alors le hero. Rendre des balises <video>
                         sans <source> laisserait un fond noir. */ ?>
                <?php if ($heroPresentClipCount === 0 && $heroVideoRejected !== []): ?>
                <!-- hero-video: sources écartées (codec non lisible navigateur) — <?= htmlspecialchars(implode(', ', array_map(static fn (array $r): string => (string) ($r['stem'] ?? '') . ':' . (string) ($r['codec'] ?? '?'), $heroVideoRejected)), ENT_QUOTES, 'UTF-8') ?> — voir docs/VIDEO-HERO-ENCODAGE.md -->
                <?php endif; ?>
                <?php if ($heroPresentClipCount > 0): ?>
                <div id="heroVideoSlides" class="hi-hero-videos absolute inset-0 hi-hero-videos--idle" data-hero-video-count="<?= count($heroVideoClips) ?>" aria-hidden="true">
                    <?php foreach ($heroVideoClips as $clipIndex => $clip): ?>
                    <div class="hi-hero-vslide<?= $clipIndex === 0 ? ' is-active' : '' ?>" data-hero-video-slide data-stem="<?= $e((string) $clip['stem']) ?>" data-present="<?= !empty($clip['present']) ? '1' : '0' ?>"<?= !empty($clip['bytes']) ? ' data-bytes="' . (int) $clip['bytes'] . '"' : '' ?>>
                        <video
                            class="hi-hero-vslide__video"
                            playsinline
                            muted
                            preload="<?= !empty($clip['present']) ? 'auto' : 'metadata' ?>"
                            <?= ($clipIndex === 0) ? 'poster="' . $e($heroPosterUrl) . '"' : '' ?>
                            data-hero-video
                        >
                            <?php foreach ($clip['sources'] as $source): ?>
                            <source src="<?= $e((string) $source['url']) ?>" type="<?= $e((string) $source['type']) ?>">
                            <?php endforeach; ?>
                        </video>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="pointer-events-none absolute inset-0 z-[4] bg-gradient-to-t from-black via-black/55 to-black/30"></div>
            </div>

            <div class="hi-hero__content">
                <p class="hi-kicker hi-kicker-glitch hi-reveal text-emerald-400"><?= $e(__('home.hero_kicker')) ?></p>
                <h1 id="hero-title" class="hi-display hi-hero-brand hi-glitch hi-reveal mt-4 text-white" data-text="Athena" aria-label="Athena">
                    <span class="hi-glitch__main" aria-hidden="true">Athena<span class="hi-glitch__dot">.</span></span>
                </h1>
                <p class="hi-hero__lead hi-reveal hi-reveal-delay"><?= $e(__('home.footer_tagline')) ?></p>
                <p class="hi-hero__body hi-body hi-reveal hi-reveal-delay"><?= $e(__('home.hero_body')) ?></p>
                <div class="hi-cta-row hi-cta-row--stack hi-reveal hi-reveal-delay mt-8">
                    <?php if (!$loggedIn): ?>
                        <a href="<?= $e(url('register')) ?>" class="hi-cta hi-cta-solid"><?= $e(__('home.cta_create_community')) ?></a>
                        <a href="<?= $e(url('join')) ?>" class="hi-cta hi-cta-ghost"><?= $e(__('home.cta_have_code')) ?></a>
                        <a href="<?= $e(url('login')) ?>" class="hi-link hi-hero__login"><?= $e(__('common.login')) ?> <span aria-hidden="true">→</span></a>
                    <?php else: ?>
                        <a href="<?= $e(url('hub')) ?>" class="hi-cta hi-cta-solid"><?= $e(__('home.cta_command_center')) ?></a>
                        <a href="<?= $e(url('dashboard')) ?>" class="hi-cta hi-cta-ghost"><?= $e(__('home.cta_personal_brief')) ?></a>
                    <?php endif; ?>
                    <button type="button" id="btn-enable-immersive" class="hi-body-sm hidden text-left text-emerald-300 underline decoration-emerald-500/30 underline-offset-4 hover:text-emerald-200">
                        <?= $e(__('home.enable_video_sound')) ?>
                    </button>
                </div>
            </div>

            <div class="relative z-10 hi-media-chrome">
                <div
                    class="hi-media-chrome__progress"
                    role="progressbar"
                    aria-label="<?= $e(__('home.media_progress')) ?>"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="0"
                    id="hero-media-progress"
                >
                    <span class="hi-media-chrome__progress-fill" style="transform: scaleX(0)" id="hero-media-progress-fill"></span>
                </div>
                <div class="hi-media-chrome__row mx-auto flex max-w-[100rem] flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between md:px-8">
                    <ul class="hi-media-chrome__meta flex flex-wrap gap-x-5 gap-y-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/60">
                        <li><?= $e(__('home.pill_multi')) ?></li>
                        <li><?= $e(__('home.pill_stack')) ?></li>
                        <li><?= $e(__('home.pill_atak')) ?></li>
                    </ul>
                    <div class="hi-media-chrome__deck">
                        <div class="hi-media-pager" role="group" aria-label="<?= $e(__('home.slideshow')) ?>" data-hero-pager>
                            <button type="button" onclick="prevSlide()" class="hi-media-pager__nav" aria-label="<?= $e(__('home.prev_media')) ?>">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div class="hi-media-pager__dots" id="hero-dots" aria-hidden="true">
                                <?php
                                $heroDotSlots = max(4, count($heroVideoClips));
                                for ($dotIndex = 0; $dotIndex < $heroDotSlots; $dotIndex++):
                                ?>
                                <span class="hi-media-dot<?= $dotIndex === 0 ? ' is-active' : '' ?>" data-hero-dot="<?= $dotIndex ?>"<?= $dotIndex >= 4 ? ' hidden' : '' ?>></span>
                                <?php endfor; ?>
                            </div>
                            <button type="button" onclick="nextSlide()" class="hi-media-pager__nav" aria-label="<?= $e(__('home.next_media')) ?>">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="hi-media-clock" title="<?= $e(__('home.media_time')) ?>">
                            <span class="hi-media-clock__live" aria-hidden="true"></span>
                            <span id="timestamp" class="hi-media-clock__time">--:-- / --:--</span>
                        </div>
                        <div class="hi-av" id="hero-av" role="group" aria-label="<?= $e(__('home.video_controls')) ?>">
                            <button type="button" id="hero-av-toggle" class="hi-av__btn" aria-label="<?= $e(__('home.play_video')) ?>" data-state="stopped">
                                <svg class="hi-av__icon hi-av__icon--play" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 5.14v13.72L19 12 8 5.14z"/></svg>
                                <svg class="hi-av__icon hi-av__icon--stop" viewBox="0 0 24 24" aria-hidden="true" hidden><path fill="currentColor" d="M7 6h3.2v12H7V6zm6.8 0H17v12h-3.2V6z"/></svg>
                            </button>
                            <div class="hi-av__audio">
                                <button type="button" id="hero-av-mute" class="hi-av__btn hi-av__btn--mute" aria-label="<?= $e(__('home.unmute')) ?>" aria-pressed="true">
                                    <svg class="hi-av__icon hi-av__icon--speaker" viewBox="0 0 24 24" aria-hidden="true" hidden><path fill="currentColor" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1-3.29-2.5-4.03v8.05c1.5-.74 2.5-2.26 2.5-4.02z"/></svg>
                                    <svg class="hi-av__icon hi-av__icon--muted" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.5 12c0-1.77-1-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                                </button>
                                <label class="hi-av__vol-wrap" for="hero-av-volume">
                                    <span class="sr-only"><?= $e(__('home.volume')) ?></span>
                                    <input type="range" id="hero-av-volume" class="hi-av__vol" min="0" max="1" step="0.05" value="0" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Preuve : unités présentes + activité réelle de la plateforme -->
        <section class="hi-proof hi-block--tight" aria-labelledby="units-heading">
            <div class="hi-wrap">
                <div class="hi-proof__head">
                    <h2 id="units-heading" class="hi-kicker text-white"><?= $e(__('home.units_title')) ?></h2>
                    <a href="<?= $e(url('communities')) ?>" class="hi-link text-emerald-300 hover:text-emerald-200"><?= $e(__($homeUnits !== [] ? 'home.see_all_communities' : 'home.communities_registry')) ?> <span aria-hidden="true">→</span></a>
                </div>
                <?php if ($homeUnits !== []): ?>
                <ul class="hi-units">
                    <?php foreach ($homeUnits as $unit): ?>
                        <?php
                        $logoSrc = $resolveLogo((string) ($unit['logo_url'] ?? ''));
                        $unitName = (string) ($unit['name'] ?? __('home.unit_fallback'));
                        $initials = '';
                        foreach (preg_split('/\s+/u', $unitName) ?: [] as $part) {
                            $part = trim((string) $part);
                            if ($part === '') {
                                continue;
                            }
                            $initials .= function_exists('mb_strtoupper')
                                ? mb_strtoupper(mb_substr($part, 0, 1))
                                : strtoupper(substr($part, 0, 1));
                            if ((function_exists('mb_strlen') ? mb_strlen($initials) : strlen($initials)) >= 2) {
                                break;
                            }
                        }
                        if ($initials === '') {
                            $initials = 'C';
                        }
                        ?>
                        <li>
                            <a href="<?= $e((string) ($unit['href'] ?? url('communities'))) ?>" class="hi-unit">
                                <?php if ($logoSrc !== ''): ?>
                                <img src="<?= $e($logoSrc) ?>" alt="" class="hi-unit__logo" width="44" height="44" loading="lazy" decoding="async">
                                <?php else: ?>
                                <span class="hi-unit__initials who-item__initials" aria-hidden="true"><?= $e($initials) ?></span>
                                <?php endif; ?>
                                <span class="min-w-0">
                                    <span class="hi-unit__name"><?= $e($unitName) ?></span>
                                    <span class="hi-unit__meta"><?= $e(__('home.public_sheet')) ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="hi-body mt-4 max-w-xl text-white/70"><?= $e(__('home.communities_empty_body')) ?></p>
                <?php endif; ?>

                <?php if ($kpisShown !== []): ?>
                <div class="hi-kpis">
                    <p class="hi-kicker text-white/60"><?= $e(__('home.kpi_kicker', ['days' => (int) $platformKpiDays])) ?></p>
                    <dl class="hi-kpis__grid">
                        <?php foreach ($kpisShown as $k): ?>
                        <div class="hi-kpi">
                            <dt><?= $e($k['l']) ?></dt>
                            <dd><?= $e($formatInt($k['v'])) ?></dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Pour qui -->
        <section class="bg-[var(--hi-paper)] text-slate-900 hi-block" aria-labelledby="audience-heading" id="pour-qui">
            <div class="hi-wrap">
                <div class="hi-audience">
                    <div class="hi-section-head">
                        <p class="hi-kicker text-emerald-700"><?= $e(__('home.audience_kicker')) ?></p>
                        <h2 id="audience-heading" class="hi-h2 text-slate-950"><?= $e(__('home.audience_title')) ?></h2>
                        <p class="hi-lead text-slate-600"><?= $e(__('home.audience_lead')) ?></p>
                    </div>
                    <?php $homePersonas = \App\Services\Portal\OnboardingPersonaCatalog::all(); ?>
                    <ul class="hi-grid hi-grid--2" aria-label="<?= $e(__('home.persona_choose_aria')) ?>">
                        <?php foreach ($homePersonas as $personaKey => $persona): ?>
                        <li>
                            <a href="<?= $e(url('onboarding') . '?persona=' . rawurlencode((string) $personaKey)) ?>" class="hi-card">
                                <span class="hi-card__eyebrow"><?= $e((string) $persona['eyebrow']) ?></span>
                                <span class="hi-card__title"><?= $e((string) $persona['label']) ?></span>
                                <span class="hi-card__text"><?= $e((string) $persona['description']) ?></span>
                                <span class="hi-card__more"><?= $e(__('home.persona_cta')) ?> <span aria-hidden="true">→</span></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="hi-roles">
                    <div>
                        <h3><?= $e(__('home.audience_cmd_t')) ?></h3>
                        <p><?= $e(__('home.audience_cmd_b')) ?></p>
                    </div>
                    <div>
                        <h3><?= $e(__('home.audience_ops_t')) ?></h3>
                        <p><?= $e(__('home.audience_ops_b')) ?></p>
                    </div>
                    <div>
                        <h3><?= $e(__('home.audience_train_t')) ?></h3>
                        <p><?= $e(__('home.audience_train_b')) ?></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Comment ça fonctionne -->
        <section class="border-y border-slate-200 bg-white text-slate-900 hi-block" aria-labelledby="how-heading" id="comment-ca-marche">
            <div class="hi-wrap">
                <div class="hi-section-head hi-section-head--center">
                    <p class="hi-kicker text-emerald-700"><?= $e(__('home.how_kicker')) ?></p>
                    <h2 id="how-heading" class="hi-h2 text-slate-950"><?= $e(__('home.how_title')) ?></h2>
                    <p class="hi-lead text-slate-600"><?= $e(__('home.how_lead')) ?></p>
                </div>
                <ol class="hi-grid hi-grid--3 mt-10">
                    <?php
                    $howSteps = [
                        ['n' => '01', 't' => __('home.how_1_t'), 'b' => __('home.how_1_b')],
                        ['n' => '02', 't' => __('home.how_2_t'), 'b' => __('home.how_2_b')],
                        ['n' => '03', 't' => __('home.how_3_t'), 'b' => __('home.how_3_b')],
                    ];
                    foreach ($howSteps as $step):
                    ?>
                    <li class="hi-card bg-slate-50">
                        <span class="hi-step__n" aria-hidden="true"><?= $e($step['n']) ?></span>
                        <h3 class="hi-card__title mt-4 text-lg"><?= $e($step['t']) ?></h3>
                        <p class="hi-card__text"><?= $e($step['b']) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <div class="hi-cta-row hi-cta-row--stack mt-10 justify-center">
                    <?php if (!$loggedIn): ?>
                        <a href="<?= $e(url('register')) ?>" class="hi-cta hi-cta-ink"><?= $e(__('home.cta_create_account')) ?></a>
                        <a href="<?= $e(url('join')) ?>" class="hi-cta hi-cta-ghost-ink"><?= $e(__('home.cta_community_code')) ?></a>
                    <?php else: ?>
                        <a href="<?= $e(url('hub')) ?>" class="hi-cta hi-cta-ink"><?= $e(__('home.cta_open_center')) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Athena = plateforme · Roleplay = expérience opérateur -->
        <section class="relative overflow-hidden bg-slate-50 text-slate-900 hi-block" aria-labelledby="athena-rp-heading">
            <div class="hi-wrap">
                <div class="hi-section-head hi-section-head--center">
                    <p class="hi-kicker text-emerald-700"><?= $e(__('home.two_worlds')) ?></p>
                    <h2 id="athena-rp-heading" class="hi-h2 text-slate-950"><?= $e(__('home.athena_rp_heading')) ?></h2>
                    <p class="text-lg font-semibold tracking-tight text-slate-800 md:text-xl"><?= $e(__('home.two_layers')) ?></p>
                    <p class="hi-lead text-slate-600"><?= $e(__('home.two_layers_body')) ?></p>
                </div>

                <div class="mt-10 grid gap-5 lg:grid-cols-2 lg:gap-6">
                    <article class="flex flex-col rounded-2xl border border-slate-800/80 bg-[#050505] p-6 text-white shadow-xl md:p-8">
                        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-emerald-400"><?= $e(__('home.platform')) ?></p>
                        <h3 class="mt-3 hi-display text-4xl tracking-tight text-white md:text-5xl">Athena</h3>
                        <p class="mt-2 text-sm font-bold uppercase tracking-[0.14em] text-white/60"><?= $e(__('home.platform_subtitle')) ?></p>
                        <p class="mt-5 text-sm leading-relaxed text-white/75"><?= $e(__('home.platform_desc')) ?></p>
                        <ul class="mt-6 flex-1 space-y-2.5 text-sm text-white/85">
                            <?php for ($li = 1; $li <= 6; $li++): ?>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span><?= $e(__('home.platform_li_' . $li)) ?></li>
                            <?php endfor; ?>
                        </ul>
                        <p class="mt-8 border-t border-white/10 pt-5 text-xs font-bold uppercase tracking-[0.16em] text-emerald-400"><?= $e(__('home.platform_goal')) ?></p>
                    </article>

                    <article class="flex flex-col rounded-2xl border border-emerald-300/80 bg-gradient-to-br from-emerald-50 to-white p-6 text-slate-900 shadow-xl md:p-8">
                        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-emerald-800"><?= $e(__('home.immersion')) ?></p>
                        <h3 class="mt-3 hi-display text-4xl tracking-tight text-emerald-950 md:text-5xl">Roleplay</h3>
                        <p class="mt-2 text-sm font-bold uppercase tracking-[0.14em] text-emerald-800"><?= $e(__('home.rp_subtitle')) ?></p>
                        <p class="mt-5 text-sm leading-relaxed text-slate-600"><?= $e(__('home.rp_desc')) ?></p>
                        <ul class="mt-6 flex-1 space-y-2.5 text-sm text-slate-700">
                            <?php for ($li = 1; $li <= 6; $li++): ?>
                            <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-emerald-700" aria-hidden="true"></span><?= $e(__('home.rp_li_' . $li)) ?></li>
                            <?php endfor; ?>
                        </ul>
                        <p class="mt-8 border-t border-emerald-200 pt-5 text-xs font-bold uppercase tracking-[0.16em] text-emerald-900"><?= $e(__('home.rp_goal')) ?></p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Récit : de la découverte au debriefing (actes 01 à 07 condensés) -->
        <?php
        $storyActs = [];
        foreach (['01', '02', '03', '04', '05', '06', '07', '08'] as $actN) {
            $storyActs[$actN] = [
                'k' => __('home.story_' . $actN . '_k'),
                't' => __('home.story_' . $actN . '_t'),
                'd' => __('home.story_' . $actN . '_d'),
            ];
        }
        ?>
        <section class="hi-story hi-block" aria-labelledby="story-heading">
            <div class="hi-wrap">
                <div class="hi-section-head">
                    <p class="hi-kicker text-emerald-300">01 / <?= $e($storyActs['01']['k']) ?></p>
                    <h2 id="story-heading" class="hi-h2 whitespace-pre-line text-white"><?= $e($storyActs['01']['t']) ?></h2>
                    <p class="hi-lead text-white/75"><?= $e($storyActs['01']['d']) ?></p>
                </div>
                <ol class="hi-acts">
                    <?php foreach (['02', '03', '04', '05', '06', '07'] as $actN): $act = $storyActs[$actN]; ?>
                    <li class="hi-act">
                        <p class="hi-act__k"><?= $e($actN) ?> / <?= $e($act['k']) ?></p>
                        <h3 class="hi-act__t"><?= $e(str_replace("\n", ' ', $act['t'])) ?></h3>
                        <p class="hi-act__d"><?= $e($act['d']) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>

        <!-- Modules -->
        <section class="bg-[var(--hi-paper)] text-slate-900 hi-block" aria-labelledby="modules-heading">
            <div class="hi-wrap">
                <div class="hi-section-head">
                    <p class="hi-kicker text-emerald-700"><?= $e(__('home.modules_kicker')) ?></p>
                    <h2 id="modules-heading" class="hi-h2 text-slate-950"><?= $e(str_replace("\n", ' ', __('home.modules_title'))) ?></h2>
                </div>
                <?php
                $modules = [
                    ['n' => '01', 'label' => __('home.mod_01'), 'desc' => __('home.mod_01_d'), 'href' => url('communities')],
                    ['n' => '02', 'label' => __('home.mod_02'), 'desc' => __('home.mod_02_d'), 'href' => url('manoeuvres')],
                    ['n' => '03', 'label' => __('home.mod_03'), 'desc' => __('home.mod_03_d'), 'href' => url('formations')],
                    ['n' => '04', 'label' => __('home.mod_04'), 'desc' => __('home.mod_04_d'), 'href' => url('enlistment')],
                    ['n' => '05', 'label' => __('home.mod_05'), 'desc' => __('home.mod_05_d'), 'href' => url('c2')],
                    ['n' => '06', 'label' => __('home.mod_06'), 'desc' => __('home.mod_06_d'), 'href' => url('boite-reception')],
                    ['n' => '07', 'label' => __('home.mod_07'), 'desc' => __('home.mod_07_d'), 'href' => url('sse')],
                    ['n' => '08', 'label' => __('home.mod_08'), 'desc' => __('home.mod_08_d'), 'href' => url('atak-natif')],
                ];
                ?>
                <ul class="hi-modules">
                    <?php foreach ($modules as $m): ?>
                    <li>
                        <a href="<?= $e($m['href']) ?>" class="hi-module">
                            <span class="hi-module__n" aria-hidden="true"><?= $e($m['n']) ?></span>
                            <span class="hi-module__label"><?= $e($m['label']) ?></span>
                            <span class="hi-module__arrow" aria-hidden="true">→</span>
                            <span class="hi-module__desc"><?= $e($m['desc']) ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <!-- SSE — présentation -->
        <section class="bg-[var(--hi-field-deep)] text-white hi-block" aria-labelledby="sse-heading">
            <div class="hi-wrap">
                <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-16">
                    <div class="hi-section-head lg:col-span-6">
                        <p class="hi-kicker text-emerald-300"><?= $e(__('home.sse_kicker')) ?></p>
                        <h2 id="sse-heading" class="hi-h2 whitespace-pre-line"><?= $e(__('home.sse_title')) ?></h2>
                        <p class="hi-lead text-white/75"><?= $e(__('home.sse_body')) ?></p>
                        <div class="hi-cta-row hi-cta-row--stack pt-4">
                            <a href="<?= $e(url('sse')) ?>" class="hi-cta hi-cta-solid"><?= $e(__('home.sse_cta')) ?></a>
                            <a href="<?= $e(url('atak/sse')) ?>" class="hi-cta hi-cta-ghost"><?= $e(__('home.sse_cta_desk')) ?></a>
                        </div>
                    </div>
                    <ul class="grid gap-4 sm:grid-cols-2 lg:col-span-6">
                        <?php
                        $ssePoints = [
                            ['t' => __('home.sse_p1_t'), 'b' => __('home.sse_p1_b')],
                            ['t' => __('home.sse_p2_t'), 'b' => __('home.sse_p2_b')],
                            ['t' => __('home.sse_p3_t'), 'b' => __('home.sse_p3_b')],
                            ['t' => __('home.sse_p4_t'), 'b' => __('home.sse_p4_b')],
                        ];
                        foreach ($ssePoints as $point):
                        ?>
                        <li class="rounded-2xl border border-white/10 bg-black/25 p-5">
                            <h3 class="text-sm font-black uppercase tracking-wide text-emerald-300"><?= $e($point['t']) ?></h3>
                            <p class="mt-2 text-sm leading-relaxed text-white/70"><?= $e($point['b']) ?></p>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Confiance / isolation -->
        <section class="border-y border-white/10 bg-black text-white hi-block" aria-labelledby="trust-heading">
            <div class="hi-wrap">
                <div class="hi-section-head">
                    <p class="hi-kicker text-emerald-300"><?= $e(__('home.trust_kicker')) ?></p>
                    <h2 id="trust-heading" class="hi-h2"><?= $e(__('home.trust_title')) ?></h2>
                    <p class="hi-lead text-white/70"><?= $e(__('home.trust_body')) ?></p>
                </div>
                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    <?php for ($ti = 1; $ti <= 3; $ti++): ?>
                    <div class="border-l-2 border-emerald-500/50 pl-5">
                        <h3 class="text-sm font-black uppercase tracking-[0.14em] text-emerald-400"><?= $e(__('home.trust_' . $ti . '_t')) ?></h3>
                        <p class="mt-3 text-sm leading-relaxed text-white/70"><?= $e(__('home.trust_' . $ti . '_b')) ?></p>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="bg-white text-slate-900 hi-block" aria-labelledby="faq-heading">
            <div class="hi-wrap grid gap-8 lg:grid-cols-12 lg:gap-16">
                <div class="hi-section-head lg:col-span-4">
                    <p class="hi-kicker text-emerald-700"><?= $e(__('home.faq_kicker')) ?></p>
                    <h2 id="faq-heading" class="hi-h2 text-slate-950"><?= $e(__('home.faq_title')) ?></h2>
                </div>
                <div class="hi-faq lg:col-span-8">
                    <?php for ($fi = 1; $fi <= 4; $fi++): ?>
                    <details class="hi-faq__item"<?= $fi === 1 ? ' open' : '' ?>>
                        <summary>
                            <h3>
                                <span class="hi-faq__k"><?= $e(__('home.faq_' . $fi . '_k')) ?></span>
                                <span class="hi-faq__q"><?= $e(__('home.faq_' . $fi . '_q')) ?></span>
                            </h3>
                            <span class="hi-faq__icon" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            </span>
                        </summary>
                        <p class="hi-faq__a"><?= $e(__('home.faq_' . $fi . '_a')) ?></p>
                    </details>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <!-- Appel final (acte 08) + accès rapides au portail -->
        <section class="hi-final hi-block" aria-labelledby="final-heading">
            <div class="hi-wrap">
                <p class="hi-kicker text-emerald-300">08 / <?= $e($storyActs['08']['k']) ?></p>
                <h2 id="final-heading" class="hi-display hi-final__title mt-5 max-w-4xl"><?= $e($storyActs['08']['t']) ?></h2>
                <p class="hi-lead mt-6 max-w-xl text-white/75"><?= $e($storyActs['08']['d']) ?></p>
                <div class="hi-cta-row hi-cta-row--stack mt-8">
                    <?php if (!$loggedIn): ?>
                        <a href="<?= $e(url('register')) ?>" class="hi-cta hi-cta-solid"><?= $e(__('home.cta_create_account')) ?></a>
                        <a href="<?= $e(url('join')) ?>" class="hi-cta hi-cta-ghost"><?= $e(__('home.cta_community_code')) ?></a>
                    <?php else: ?>
                        <a href="<?= $e(url('hub')) ?>" class="hi-cta hi-cta-solid"><?= $e(__('home.cta_open_center')) ?></a>
                    <?php endif; ?>
                </div>

                <nav class="hi-access" aria-label="<?= $e(__('home.modules_access_aria')) ?>">
                    <p class="hi-access__label hi-kicker"><?= $e(__('home.modules_access')) ?></p>
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
                    <a href="<?= $e(url($path)) ?>"><span class="hi-access__eye"><?= $e($eye) ?></span><?= $e($lab) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </section>

        <!-- Newsletter -->
        <section id="newsletter" class="hi-newsletter bg-[var(--hi-paper)] text-slate-900 hi-block" aria-labelledby="newsletter-heading">
            <div class="hi-wrap">
                <div class="hi-newsletter__grid">
                    <div class="hi-newsletter__intro hi-section-head">
                        <p class="hi-kicker text-emerald-700"><?= $e(__('home.newsletter_kicker')) ?></p>
                        <h2 id="newsletter-heading" class="hi-h2 whitespace-pre-line text-slate-950"><?= $e(__('home.newsletter_title')) ?></h2>
                        <p class="hi-lead text-slate-600"><?= $e(__('home.newsletter_body')) ?></p>
                        <ul class="hi-newsletter__highlights" aria-label="<?= $e(__('home.newsletter_highlights')) ?>">
                            <li><?= $e(__('home.newsletter_h1')) ?></li>
                            <li><?= $e(__('home.newsletter_h2')) ?></li>
                            <li><?= $e(__('home.newsletter_h3')) ?></li>
                        </ul>
                    </div>

                    <div class="hi-newsletter__panel rounded-2xl">
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
    <dialog id="immersive-consent" class="max-w-md rounded-2xl border border-white/10 bg-[#0a0a0a] p-0 text-white shadow-2xl backdrop:bg-black/70" aria-labelledby="immersive-title">
        <div class="p-6 sm:p-8">
            <p class="hi-kicker text-emerald-400"><?= $e(__('home.immersive_kicker')) ?></p>
            <h2 id="immersive-title" class="mt-3 text-2xl font-black italic uppercase tracking-tight"><?= $e(__('home.immersive_title')) ?></h2>
            <p class="hi-body mt-4 text-sm text-white/70"><?= $e(__('home.immersive_body')) ?></p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <button type="button" id="immersive-yes" class="hi-cta hi-cta-solid flex-1"><?= $e(__('home.immersive_yes')) ?></button>
                <button type="button" id="immersive-no" class="hi-cta hi-cta-ghost flex-1"><?= $e(__('home.immersive_no')) ?></button>
            </div>
        </div>
    </dialog>

    <footer class="hi-footer border-t border-white/10 bg-black py-10 text-white">
        <div class="hi-wrap flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em]">Athena<span class="text-emerald-400" aria-hidden="true">.</span></p>
                <p class="hi-body-sm mt-3 max-w-xs text-white/60"><?= $e(__('home.footer_tagline')) ?></p>
            </div>
            <nav class="flex max-w-xl flex-wrap gap-x-5 gap-y-1 text-xs" aria-label="<?= $e(__('home.footer_legal_aria')) ?>">
                <a href="<?= $e(url('a-propos')) ?>" class="font-medium text-white/65 transition hover:text-emerald-400"><?= $e(__('site.about')) ?></a>
                <a href="<?= $e(url('sse')) ?>" class="font-medium text-white/65 transition hover:text-emerald-400"><?= $e(__('site.sse')) ?></a>
                <a href="<?= $e(url('atak-natif')) ?>" class="font-medium text-white/65 transition hover:text-emerald-400"><?= $e(__('site.atak_native')) ?></a>
                <a href="<?= $e(url('contact')) ?>" class="font-medium text-white/65 transition hover:text-emerald-400"><?= $e(__('site.contact')) ?></a>
                <a href="<?= $e(url('nouveautes')) ?>" class="font-medium text-white/65 transition hover:text-emerald-400"><?= $e(__('site.changelog')) ?></a>
                <?php
                $legal_link_class = 'text-white/65 transition hover:text-emerald-400 font-medium';
                require base_path('views/partials/legal_site_links.php');
                ?>
            </nav>
        </div>
    </footer>

    <?php require base_path('views/partials/cookie_banner.php'); ?>
    <?php require base_path('views/partials/demo_nda_session_widget.php'); ?>

    <script>
        window.hiI18n = <?= json_encode([
            'playVideo' => __('home.play_video'),
            'pauseVideo' => __('home.pause_video'),
            'mute' => __('home.mute'),
            'unmute' => __('home.unmute'),
            'newsletterLoading' => __('home.newsletter_loading'),
            'newsletterSubmit' => __('home.newsletter_submit'),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;

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
    <script src="<?= $e(asset_url('assets/js/home-impact.js')) ?>" defer></script>
    <?php require base_path('views/partials/mirror_trap_link.php'); ?>
</body>
</html>
