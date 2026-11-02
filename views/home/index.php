<?php
$base = url('');
$title = $title ?? __('home.meta_title');
$loggedIn = (bool) \App\Core\Session::get('user_id');
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
/*
 * Chiffres réels de toute la plateforme (PublicHistoryStatsService : cache de 10 minutes partagé avec
 * « Notre histoire » et « À propos »). Un compteur à zéro n’apporte rien : seuls les non nuls s’affichent.
 */
$platformStats = is_array($platformStats ?? null) ? $platformStats : [];
$statInt = static fn (string $key): int => max(0, (int) ($platformStats[$key] ?? 0));
$homeFigures = array_values(array_filter([
    ['k' => 'members', 'n' => $statInt('members'), 'u' => ''],
    ['k' => 'communities', 'n' => $statInt('communities'), 'u' => ''],
    ['k' => 'operations', 'n' => $statInt('operations'), 'u' => ''],
    ['k' => 'play', 'n' => $statInt('play_hours'), 'u' => 'h'],
    ['k' => 'flight', 'n' => $statInt('flight_hours'), 'u' => 'h'],
    ['k' => 'academy', 'n' => $statInt('academy_validated'), 'u' => ''],
], static fn (array $f): bool => $f['n'] > 0));
$homeKm = $statInt('distance_km');
$homeKmModes = [];
foreach (is_array($platformStats['distance_modes'] ?? null) ? $platformStats['distance_modes'] : [] as $mode => $km) {
    if (in_array($mode, ['foot', 'land', 'air', 'sea', 'halo'], true) && (int) $km > 0) {
        $homeKmModes[$mode] = (int) $km;
    }
}
$homeSince = '';
$homeFounded = substr((string) ($platformStats['founded_at'] ?? ''), 0, 10);
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $homeFounded) === 1) {
    $homeFoundedAt = new DateTimeImmutable($homeFounded);
    $homeSince = class_exists(IntlDateFormatter::class)
        ? (string) (new IntlDateFormatter(locale() === 'en' ? 'en_GB' : 'fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'LLLL y'))->format($homeFoundedAt)
        : $homeFoundedAt->format('m/Y');
}
/* Fonds du hero : WebP déjà passés en gris et assombris (aucun filtre CSS à calculer), en deux largeurs. */
$heroImages = [];
foreach ([1 => 1920, 2 => 1817, 3 => 1920, 4 => 1818] as $heroN => $heroW) {
    $heroStem = rtrim($base, '/') . '/assets/images/home/hero-' . $heroN;
    $heroImages[] = [
        'src' => $heroStem . '-1920.webp',
        'srcset' => $heroStem . '-960.webp 960w, ' . $heroStem . '-1920.webp ' . $heroW . 'w',
    ];
}
$heroPosterUrl = $heroImages[0]['src'];
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
    <link rel="apple-touch-icon" href="<?= $e($base) ?>/assets/icons/athena-180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= $e($base) ?>/assets/icons/athena-192.png">
    <link rel="preload" as="image" href="<?= $e($heroPosterUrl) ?>" imagesrcset="<?= $e($heroImages[0]['srcset']) ?>" imagesizes="100vw" type="image/webp" fetchpriority="high">
    <?php /* Organisation et site : graphe commun déclaré par views/partials/seo_meta.php. */ ?>
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php /* CSS utilitaire compilé (public/assets/css/tailwind.css) ; CDN seulement si le build est absent. */ ?>
    <?php require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <?php /* Une seule feuille de polices : Inter (texte) et Barlow Condensed (chiffres, comme « Notre histoire »).
             design-system.css n’est plus chargé ici : ses @import en cascade tiraient six familles de polices inutiles,
             et home-impact.css porte déjà ses propres valeurs de repli. */ ?>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="<?= $e(asset_url('assets/css/styles.css')) ?>" rel="stylesheet">
    <link href="<?= $e(asset_url('assets/css/home-impact.css')) ?>" rel="stylesheet">
    <link href="<?= $e(asset_url('assets/css/public-site-footer.css')) ?>" rel="stylesheet">
</head>
<body class="home-impact layout-light bg-[var(--hi-void)] text-[var(--hi-ink)] antialiased selection:bg-emerald-500 selection:text-slate-950 overflow-x-hidden">

    <a href="#contenu" class="hi-skip"><?= $e(__('common.skip_to_content')) ?></a>

    <?php require base_path('views/partials/home_topbar.php'); ?>

    <main id="contenu" tabindex="-1">
        <?php
        // Bandeau de don commun au site public (comme en tête de layout.marketing), sous la barre fixe.
        ob_start();
        require base_path('views/partials/donation_banner.php');
        $donationBannerHtml = trim((string) ob_get_clean());
        ?>
        <?php if ($donationBannerHtml !== ''): ?>
        <div class="hi-dnb"><?= $donationBannerHtml ?></div>
        <?php endif; ?>
        <!-- Hero : fond immersif + marque + proposition + accès -->
        <section class="relative flex min-h-[100svh] flex-col justify-end overflow-hidden bg-black" id="hero" aria-labelledby="hero-title" data-hero-videos-ready="<?= $heroVideosPresentOnDisk ? '1' : '0' ?>">
            <div class="pointer-events-none absolute inset-0" id="heroSlider">
                <div id="heroImageSlides" class="hi-hero-images absolute inset-0">
                    <?php /* Seule la première image part au chargement ; les suivantes sont posées par le script avant leur tour. */ ?>
                    <?php foreach ($heroImages as $heroIndex => $heroImage): ?>
                    <div class="slide absolute inset-0 <?= $heroIndex === 0 ? 'opacity-100' : 'opacity-0' ?> transition-opacity duration-1000 ease-in-out">
                        <?php if ($heroIndex === 0): ?>
                        <img id="hero-poster" src="<?= $e($heroImage['src']) ?>" srcset="<?= $e($heroImage['srcset']) ?>" sizes="100vw" alt="" class="h-full w-full scale-100 object-cover opacity-55 transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async" fetchpriority="high">
                        <?php else: ?>
                        <img data-hero-src="<?= $e($heroImage['src']) ?>" data-hero-srcset="<?= $e($heroImage['srcset']) ?>" sizes="100vw" alt="" class="h-full w-full scale-100 object-cover opacity-55 transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
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
                            preload="none"
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

        <?php if ($homeKm > 0 || $homeFigures !== []): ?>
        <!-- Chiffres réels de la plateforme (mêmes sources que « Notre histoire ») -->
        <section class="hp hp--figures" aria-labelledby="hp-figures-title" data-hp-figures>
            <div class="hi-wrap">
                <div class="hp-head">
                    <h2 id="hp-figures-title" class="hp-title"><?= $e(__('home.figures_title')) ?></h2>
                    <p class="hp-head__aside">
                        <?= $e($homeSince !== '' ? __('home.figures_since', ['date' => $homeSince]) : __('home.figures_scope')) ?>
                        <a href="<?= $e(url('notre-histoire')) ?>" class="hp-link"><?= $e(__('home.figures_story_link')) ?></a>
                    </p>
                </div>
                <?php if ($homeKm > 0): ?>
                <div class="hp-km">
                    <svg class="hp-km__route" viewBox="0 0 600 120" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                        <path class="hp-route" pathLength="1" d="M4 96 C 70 92 110 70 170 78 S 270 108 330 70 S 430 20 490 40 S 560 30 596 14"/>
                        <circle class="hp-wp" cx="170" cy="78" r="5"/>
                        <circle class="hp-wp" cx="330" cy="70" r="5"/>
                        <circle class="hp-wp hp-wp--end" cx="596" cy="14" r="5"/>
                    </svg>
                    <p class="hp-km__value"><span data-hp-count="<?= $homeKm ?>"><?= $e($formatInt($homeKm)) ?></span><span class="hp-km__unit">km</span></p>
                    <p class="hp-km__label"><?= $e(__('site.hist_km_label')) ?></p>
                    <?php if ($homeKmModes !== []): ?>
                    <div class="hp-km__bar" aria-hidden="true">
                        <?php foreach ($homeKmModes as $mode => $km): ?>
                        <span class="hp-mode--<?= $e($mode) ?>" style="flex-grow:<?= (int) $km ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <ul class="hp-km__legend">
                        <?php foreach ($homeKmModes as $mode => $km): ?>
                        <li><span class="hp-swatch hp-mode--<?= $e($mode) ?>" aria-hidden="true"></span><?= $e(__('site.hist_mode_' . $mode)) ?> <strong><?= $e($formatInt($km)) ?> km</strong></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($homeFigures !== []): ?>
                <dl class="hp-figures">
                    <?php foreach ($homeFigures as $figure): ?>
                    <div class="hp-figure">
                        <dt><?= $e(__('site.hist_fig_' . $figure['k'])) ?></dt>
                        <dd class="hp-figure__v"><span data-hp-count="<?= (int) $figure['n'] ?>"><?= $e($formatInt((int) $figure['n'])) ?></span><?php if ($figure['u'] !== ''): ?><span class="hp-figure__u"><?= $e($figure['u']) ?></span><?php endif; ?></dd>
                        <dd class="hp-figure__h"><?= $e(__('site.hist_fig_' . $figure['k'] . '_h')) ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Communautés présentes sur la plateforme (maquette « Section Communautes - Athena ») -->
        <?php
        /*
         * Mêmes données qu'avant (registre public : communautés masquées, archivées ou non listées exclues ;
         * chiffres en cache). Deux « À la une » en grand, sept cartes, puis le défilé des autres unités.
         */
        $unitsFeatured = array_slice(array_values(array_filter($featuredUnits, static fn (array $u): bool => !empty($u['featured']))), 0, 2);
        $unitsFeaturedSlugs = array_column($unitsFeatured, 'slug');
        $unitsRest = array_values(array_filter($featuredUnits, static fn (array $u): bool => !in_array($u['slug'] ?? '', $unitsFeaturedSlugs, true)));
        $unitsGrid = array_slice($unitsRest, 0, 7);
        $unitsOthers = array_slice($unitsRest, 7);
        $homeUnits = array_merge($unitsFeatured, $unitsGrid);
        $unitsMembersTotal = array_sum(array_map(static fn (array $u): int => (int) ($u['members'] ?? 0), $featuredUnits));
        $unitsLangs = array_values(array_unique(array_filter(array_map(static fn (array $u): string => trim((string) ($u['locale_label'] ?? '')), $featuredUnits), static fn (string $l): bool => $l !== '')));
        $unitsCounts = array_values(array_filter([
            ['v' => count($featuredUnits), 'l' => __('home.units_count_units')],
            ['v' => $unitsMembersTotal, 'l' => __('home.units_count_members')],
            ['v' => count($unitsLangs), 'l' => __('home.units_count_langs')],
        ], static fn (array $c): bool => $c['v'] > 0));
        /* Filtres : seulement s'ils départagent les cartes affichées. */
        $unitsShownRecruiting = count(array_filter($homeUnits, static fn (array $u): bool => !empty($u['recruitment_open'])));
        $unitsShownLangs = [];
        foreach ($homeUnits as $u) {
            $l = trim((string) ($u['locale_label'] ?? ''));
            if ($l !== '') {
                $unitsShownLangs[$l] = ($unitsShownLangs[$l] ?? 0) + 1;
            }
        }
        $unitsFilters = [];
        if ($unitsShownRecruiting > 0 && $unitsShownRecruiting < count($homeUnits)) {
            $unitsFilters[] = ['key' => 'rec', 'label' => __('home.units_filter_recruiting'), 'n' => $unitsShownRecruiting, 'dot' => true];
        }
        if (count($unitsShownLangs) > 1) {
            foreach ($unitsShownLangs as $l => $n) {
                $unitsFilters[] = ['key' => 'lang:' . $l, 'label' => $l, 'n' => $n, 'dot' => false];
            }
        }
        $unitCard = static function (array $unit) use ($resolveLogo): array {
            $name = (string) ($unit['name'] ?? __('home.unit_fallback'));
            $slug = (string) ($unit['slug'] ?? $name);
            $initials = '';
            foreach (preg_split('/\s+/u', $name) ?: [] as $part) {
                // Seules les lettres et chiffres comptent : « [CCO] - SPARTA™ » donne « CS ».
                $part = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $part);
                if ($part === '') {
                    continue;
                }
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                if (mb_strlen($initials) >= 3) {
                    break;
                }
            }
            $games = [];
            foreach (\App\Support\GameCatalog::split((string) ($unit['game'] ?? '')) as $part) {
                $g = \App\Support\GameCatalog::canonical($part) ?? $part;
                $games[\App\Support\GameCatalog::key($g)] ??= $g;
            }
            $tagline = trim((string) ($unit['tagline'] ?? ''));
            $affiliation = trim((string) ($unit['affiliation'] ?? ''));
            $accent = (string) ($unit['accent'] ?? '');

            return [
                'name' => $name,
                'initials' => $initials !== '' ? $initials : 'C',
                'logo' => $resolveLogo((string) ($unit['logo_url'] ?? '')),
                'banner' => $resolveLogo((string) ($unit['banner_url'] ?? '')),
                /* Teinte stable par communauté ; la couleur de marque la remplace si elle existe. */
                'style' => '--u-hue:' . (crc32($slug) % 360) . ($accent !== '' ? ';--u-accent:' . $accent : ''),
                'href' => (string) ($unit['href'] ?? url('communities')),
                'games' => array_values($games),
                'locale' => trim((string) ($unit['locale_label'] ?? '')),
                'text' => $tagline !== '' ? $tagline : $affiliation,
                'affiliation' => $tagline !== '' ? $affiliation : '',
                'stats' => array_values(array_filter([
                    ['v' => (int) ($unit['members'] ?? 0), 'l' => __('home.units_stat_members')],
                    ['v' => (int) ($unit['units'] ?? 0), 'l' => __('home.units_stat_orbat')],
                    ['v' => (int) round((float) ($unit['ops_per_month'] ?? 0)), 'l' => __('home.units_stat_ops')],
                ], static fn (array $s): bool => $s['v'] > 0)),
                'recruiting' => !empty($unit['recruitment_open']),
            ];
        };
        $unitCrest = static function (array $c, string $class = 'hu-crest') use ($e): string {
            return '<span class="' . $class . '" aria-hidden="true">'
                . ($c['logo'] !== '' ? '<img src="' . $e($c['logo']) . '" alt="" width="52" height="52" loading="lazy" decoding="async">' : $e($c['initials']))
                . '</span>';
        };
        $unitTags = static function (array $c) use ($e): string {
            $out = '';
            foreach ($c['games'] as $g) {
                $out .= '<span class="hu-tag" translate="no">' . $e($g) . '</span>';
            }
            if ($c['locale'] !== '') {
                $out .= '<span class="hu-tag">' . $e($c['locale']) . '</span>';
            }

            return $out !== '' ? '<span class="hu-tags">' . $out . '</span>' : '';
        };
        $unitStats = static function (array $c, int $max) use ($e, $formatInt): string {
            $out = '';
            foreach (array_slice($c['stats'], 0, $max) as $st) {
                $out .= '<span class="hu-st"><b>' . $e($formatInt($st['v'])) . '</b><small>' . $e($st['l']) . '</small></span>';
            }

            return $out;
        };
        $unitRec = static fn (array $c): string => '<span class="hu-rec' . ($c['recruiting'] ? '' : ' is-inv') . '"><i aria-hidden="true"></i>'
            . $e(__($c['recruiting'] ? 'home.units_recruiting' : 'home.units_invite_only')) . '</span>';
        $unitData = static fn (array $c): string => 'data-hu-rec="' . ($c['recruiting'] ? '1' : '0') . '" data-hu-lang="' . $e($c['locale']) . '"';
        ?>
        <section class="hu" aria-labelledby="units-heading" data-hu>
            <div class="hu-wrap">
                <div class="hu-hd">
                    <div>
                        <span class="hu-kk"><i aria-hidden="true"></i><?= $e(__('home.units_kicker')) ?></span>
                        <h2 id="units-heading"><?= $e(__('home.units_title_lead')) ?> <span><?= $e(__('home.units_title_accent')) ?></span></h2>
                        <p class="hu-lead"><?= $e(__('home.units_intro')) ?></p>
                    </div>
                    <?php if ($unitsCounts !== []): ?>
                    <dl class="hu-counts">
                        <?php foreach ($unitsCounts as $c): ?>
                        <div><dd><?= $e($formatInt($c['v'])) ?></dd><dt><?= $e($c['l']) ?></dt></div>
                        <?php endforeach; ?>
                    </dl>
                    <?php endif; ?>
                </div>

                <div class="hu-bar">
                    <?php if ($unitsFilters !== []): ?>
                    <div class="hu-fc" role="group" aria-label="<?= $e(__('home.units_filter_label')) ?>" data-hu-filters hidden>
                        <button type="button" class="is-on" data-hu-filter="" aria-pressed="true"><?= $e(__('home.units_filter_all')) ?><em><?= $e($formatInt(count($homeUnits))) ?></em></button>
                        <?php foreach ($unitsFilters as $f): ?>
                        <button type="button" data-hu-filter="<?= $e($f['key']) ?>" aria-pressed="false"><?php if ($f['dot']): ?><i aria-hidden="true"></i><?php endif; ?><?= $e($f['label']) ?><em><?= $e($formatInt($f['n'])) ?></em></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <span class="hu-sp"></span>
                    <a class="hu-all" href="<?= $e(url('communities')) ?>"><?= $e(__($homeUnits !== [] ? 'home.see_all_communities' : 'home.communities_registry')) ?><svg class="hu-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                </div>

                <?php if ($unitsFeatured !== []): ?>
                <ul class="hu-feat<?= count($unitsFeatured) === 1 ? ' is-single' : '' ?>">
                    <?php foreach ($unitsFeatured as $unit): $c = $unitCard($unit); ?>
                    <li <?= $unitData($c) ?>>
                        <a href="<?= $e($c['href']) ?>" class="hu-fcard" style="<?= $e($c['style']) ?>">
                            <span class="hu-ph" aria-hidden="true"><?php if ($c['banner'] !== ''): ?><img src="<?= $e($c['banner']) ?>" alt="" width="960" height="420" loading="lazy" decoding="async"><?php endif; ?></span>
                            <span class="hu-star"><svg class="hu-i" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg><?= $e(__('home.units_featured')) ?></span>
                            <span class="hu-fbody">
                                <span class="hu-fid"><?= $unitCrest($c) ?><span class="hu-fname"><?php if ($c['affiliation'] !== ''): ?><small translate="no"><?= $e($c['affiliation']) ?></small><?php endif; ?><span class="hu-name" translate="no"><?= name_blur_html($c['name']) ?></span></span></span>
                                <?php if ($c['text'] !== ''): ?><span class="hu-desc" translate="no"><?= $e($c['text']) ?></span><?php endif; ?>
                                <?= $unitTags($c) ?>
                                <span class="hu-ffoot"><?= $unitStats($c, 3) ?><?= $unitRec($c) ?><span class="hu-go"><?= $e(__($c['recruiting'] ? 'home.units_cta_join' : 'home.units_cta_discover')) ?><svg class="hu-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></span>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <ul class="hu-grid">
                    <?php foreach ($unitsGrid as $unit): $c = $unitCard($unit); ?>
                    <li <?= $unitData($c) ?>>
                        <a href="<?= $e($c['href']) ?>" class="hu-card" style="<?= $e($c['style']) ?>">
                            <span class="hu-bn" aria-hidden="true"><?php if ($c['banner'] !== ''): ?><img src="<?= $e($c['banner']) ?>" alt="" width="640" height="240" loading="lazy" decoding="async"><?php else: ?><span class="hu-code"><?= $e($c['initials']) ?></span><?php endif; ?></span>
                            <span class="hu-cb">
                                <?= $unitCrest($c) ?>
                                <span class="hu-name" translate="no"><?= name_blur_html($c['name']) ?></span>
                                <?= $unitTags($c) ?>
                                <?php if ($c['text'] !== ''): ?><span class="hu-loc" translate="no"><?= $e($c['text']) ?></span><?php endif; ?>
                                <span class="hu-cf"><?= $unitStats($c, 2) ?><?= $unitRec($c) ?></span>
                            </span>
                            <span class="hu-more" aria-hidden="true"></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <li class="hu-join-li">
                        <a class="hu-join" href="<?= $e(url('communities/create/guide')) ?>">
                            <span class="hu-plus" aria-hidden="true"><svg class="hu-i" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
                            <span><span class="hu-jt"><?= $e(__('home.units_join_title')) ?></span><span class="hu-jb"><?= $e(__('home.units_join_body')) ?></span></span>
                            <span class="hu-jl"><?= $e(__('home.units_join_cta')) ?><svg class="hu-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                        </a>
                    </li>
                </ul>
                <?php if ($homeUnits === []): ?>
                <p class="hu-empty"><?= $e(__('home.communities_empty_body')) ?></p>
                <?php endif; ?>
                <p class="hu-empty" data-hu-none hidden><?= $e(__('home.units_filter_empty')) ?></p>
            </div>

            <?php if ($unitsOthers !== []): ?>
            <div class="hu-mq">
                <p class="hu-mq__lbl"><?= $e(count($unitsOthers) > 1 ? __('home.units_others', ['count' => $formatInt(count($unitsOthers))]) : __('home.units_others_one')) ?></p>
                <div class="hu-trk">
                    <?php foreach ([false, true] as $copy): ?>
                    <ul class="hu-trk__set"<?= $copy ? ' aria-hidden="true"' : '' ?>>
                        <?php foreach (array_slice($unitsOthers, 0, 30) as $unit): $c = $unitCard($unit); ?>
                        <li><a class="hu-pill" href="<?= $e($c['href']) ?>" style="<?= $e($c['style']) ?>"<?= $copy ? ' tabindex="-1"' : '' ?>><?= $unitCrest($c) ?><span translate="no"><?= name_blur_html($c['name']) ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
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

        <!-- Aperçu : écrans de la plateforme avec des données fictives -->
        <?php require base_path('views/home/partials/demo_showcase.php'); ?>

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

    <?php require base_path('views/partials/public_site_footer.php'); ?>

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

    </script>
    <script src="<?= $e(asset_url('assets/js/home-impact.js')) ?>" defer></script>
    <?php require base_path('views/partials/mirror_trap_link.php'); ?>
</body>
</html>
