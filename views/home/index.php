<?php
$base = url('');
$title = $title ?? 'Athena Compsec — Portail MILSIM';
$loggedIn = (bool) \App\Core\Session::get('user_id');
$platformKpis = is_array($platformKpis ?? null) ? $platformKpis : [];
$platformKpiDays = max(1, (int) ($platformKpiDays ?? 30));
$kpiValue = static function (string $key) use ($platformKpis): int {
    return max(0, (int) ($platformKpis[$key] ?? 0));
};
$formatInt = static function (int $value): string {
    return number_format($value, 0, ',', ' ');
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
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $meta_description = $meta_description ?? 'Athena Compsec : portail MILSIM pour communautés Arma — formations, unités, forum et outils opérationnels.';
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
            <span class="hi-kicker text-white/40">Menu</span>
            <button type="button" onclick="toggleMenu()" class="rounded-lg p-2 text-white/50 transition hover:bg-white/5 hover:text-white" aria-label="Fermer le menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-3 py-3" aria-label="Navigation du portail">
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
                <a href="<?= $base ?>/" onclick="toggleMenu()" class="<?= $homeNavLink ?>">Accueil</a>
                <a href="<?= url('login') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>">Connexion</a>
                <a href="<?= url('register') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>">Inscription</a>
                <a href="<?= url('join') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>">Rejoindre avec un code</a>
                <a href="<?= url('communities') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>">Communautés</a>
            <?php endif; ?>
        </nav>
        <div class="shrink-0 border-t border-white/10 p-4">
            <?php if (!$loggedIn): ?>
                <a href="<?= url('register') ?>" onclick="toggleMenu()" class="hi-cta hi-cta-solid w-full">Créer un compte</a>
            <?php else: ?>
                <a href="<?= url('dashboard') ?>" onclick="toggleMenu()" class="hi-cta hi-cta-solid w-full">Tableau de bord</a>
            <?php endif; ?>
        </div>
    </div>

    <header class="fixed inset-x-0 top-0 z-[100] border-b border-white/5 bg-black/70 backdrop-blur-md">
        <div class="mx-auto flex h-14 max-w-[100rem] items-center justify-between px-5 md:px-8">
            <button type="button" onclick="toggleMenu()" class="group flex h-6 w-6 flex-col justify-center gap-1.5 outline-none" aria-label="Ouvrir le menu">
                <span class="h-px w-full bg-white/80 transition group-hover:bg-white"></span>
                <span class="ml-auto h-px w-1/2 bg-white/50 transition group-hover:w-full group-hover:bg-white"></span>
            </button>
            <a href="<?= $base ?>/" class="absolute left-1/2 -translate-x-1/2 text-center">
                <span class="block text-[11px] font-black uppercase tracking-[0.32em] text-white">Athena</span>
            </a>
            <div class="flex items-center gap-4">
                <span id="home-header-clock" class="hidden text-[10px] font-semibold tracking-wide text-white/45 tabular-nums sm:inline">--:--:--</span>
                <?php if (!$loggedIn): ?>
                    <a href="<?= url('login') ?>" class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/70 transition hover:text-white">Entrer</a>
                <?php else: ?>
                    <a href="<?= url('hub') ?>" class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 transition hover:text-emerald-300">Ops</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- Vidéo / diaporama immersif (plein écran) -->
        <section class="relative flex min-h-[100svh] flex-col justify-end overflow-hidden bg-black pt-14" id="hero-immersive" aria-label="Introduction visuelle">
            <div class="pointer-events-none absolute inset-0" id="heroSlider">
                <div class="slide absolute inset-0 opacity-100 transition-opacity duration-1000 ease-in-out">
                    <img id="hero-poster" src="<?= $base ?>/assets/images/fog-team.jpg" alt="" class="h-full w-full scale-100 object-cover opacity-55 grayscale brightness-[0.5] transition-transform duration-[10000ms] ease-linear" width="1920" height="1080" decoding="async" fetchpriority="high">
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
                <video id="hero-video" class="absolute inset-0 hidden h-full w-full object-cover opacity-60" playsinline loop muted preload="none" poster="<?= $base ?>/assets/images/fog-team.jpg">
                    <source data-src="<?= $base ?>/assets/video/hero-athena.webm" type="video/webm">
                    <source data-src="<?= $base ?>/assets/video/hero-athena.mp4" type="video/mp4">
                </video>
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-black/25"></div>
            </div>

            <div class="relative z-10 mx-auto flex w-full max-w-[100rem] flex-col items-start gap-4 px-5 pb-10 pt-24 md:px-8 md:pb-12">
                <p class="hi-kicker text-emerald-400/90">Athena Compsec</p>
                <p class="max-w-md text-sm font-medium leading-relaxed text-white/70 md:text-base">
                    Introduction visuelle du portail — le détail et les accès sont juste en dessous.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="#hero-classic" class="hi-cta hi-cta-solid">Voir Athena</a>
                    <button type="button" id="btn-enable-immersive" class="hi-body-sm hidden text-left text-emerald-400/80 underline decoration-emerald-500/30 underline-offset-4 hover:text-emerald-300">
                        Activer l’expérience immersive
                    </button>
                </div>
            </div>

            <div class="relative z-10 border-t border-white/10 bg-black/40 backdrop-blur-sm">
                <div class="mx-auto flex max-w-[100rem] flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-8">
                    <div class="flex flex-wrap gap-x-6 gap-y-2 hi-body-sm text-[10px] uppercase tracking-[0.14em] text-white/45">
                        <span>Multi-communautés</span>
                        <span>ORBAT · Formation · Forum</span>
                        <span>COMSPEC ATAK</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2" aria-label="Diaporama">
                            <button type="button" onclick="prevSlide()" class="p-1 text-white/35 transition hover:text-white" aria-label="Image précédente">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div class="flex gap-2">
                                <span class="dot h-1 w-1 rounded-full bg-white transition-all"></span>
                                <span class="dot h-1 w-1 rounded-full bg-white/25 transition-all"></span>
                                <span class="dot h-1 w-1 rounded-full bg-white/25 transition-all"></span>
                                <span class="dot h-1 w-1 rounded-full bg-white/25 transition-all"></span>
                            </div>
                            <button type="button" onclick="nextSlide()" class="p-1 text-white/35 transition hover:text-white" aria-label="Image suivante">
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
                    </div>
                </div>
            </div>
        </section>

        <!-- Hero classique Athena (après la vidéo) -->
        <section id="hero-classic" class="hi-hero-classic scroll-mt-14 border-b border-slate-200 bg-[var(--hi-paper)] text-slate-900" aria-labelledby="hero-classic-title">
            <div class="hi-section mx-auto max-w-[100rem]">
                <p class="hi-kicker text-emerald-700">Portail MILSIM · Commandement d’unité</p>
                <h1 id="hero-classic-title" class="hi-display hi-hero-brand mt-6 text-slate-900">
                    Athena<span class="text-emerald-600">.</span>
                </h1>
                <p class="hi-body mt-8 max-w-xl text-slate-600">
                    Une base pour votre communauté Arma — du recrutement au terrain.
                    Organisation, présence, doctrine et C2 au même endroit.
                </p>
                <div class="mt-10 flex flex-wrap gap-3">
                    <?php if (!$loggedIn): ?>
                        <a href="<?= url('register') ?>" class="hi-cta hi-cta-ink">Créer ma communauté</a>
                        <a href="<?= url('join') ?>" class="hi-cta hi-cta-ghost-ink">J’ai un code</a>
                    <?php else: ?>
                        <a href="<?= url('hub') ?>" class="hi-cta hi-cta-ink">Centre de commandement</a>
                        <a href="<?= url('dashboard') ?>" class="hi-cta hi-cta-ghost-ink">Briefing personnel</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($featuredUnits !== []): ?>
        <!-- Logos des unités -->
        <section class="who-we-are bg-white text-slate-900">
            <div class="who-inner">
                <div class="who-title">
                    <h2>Unités sur la plateforme</h2>
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
                            <h4><?= htmlspecialchars((string) ($unit['name'] ?? 'Unité'), ENT_QUOTES, 'UTF-8') ?></h4>
                            <span>Fiche publique</span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <p class="mt-10 text-center">
                    <a href="<?= url('communities') ?>" class="hi-body-sm font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-950">Voir toutes les communautés</a>
                </p>
            </div>
        </section>
        <?php else: ?>
        <section class="border-y border-slate-200 bg-white py-10 text-center text-slate-900">
            <p class="hi-kicker text-slate-400">Communautés</p>
            <p class="hi-body mx-auto mt-3 max-w-lg text-slate-600">Parcourez le registre des unités présentes sur Athena.</p>
            <a href="<?= url('communities') ?>" class="hi-cta hi-cta-ink mt-6 inline-flex">Registre des communautés</a>
        </section>
        <?php endif; ?>

        <!-- Site / Roleplay (restauré) -->
        <section class="relative overflow-hidden border-y border-slate-200 bg-gradient-to-b from-white to-slate-50/70 text-slate-900">
            <div class="relative mx-auto max-w-6xl px-6 py-16 md:py-20">
                <div class="max-w-3xl">
                    <p class="mb-3 hi-kicker text-emerald-700">Athena en deux couches</p>
                    <h2 class="hi-display hi-display-md text-slate-900">Différencier le site et le roleplay</h2>
                    <p class="hi-body mt-4 text-slate-600">
                        Le <strong>site Athena</strong> reste votre couche de pilotage. La couche <strong>roleplay</strong> structure l’immersion.
                    </p>
                </div>
                <div class="mt-10 grid gap-6 lg:grid-cols-2">
                    <article class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm">
                        <p class="hi-kicker text-slate-500">Couche plateforme</p>
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Site Athena</h3>
                        <p class="hi-body-sm mt-3 text-slate-600">Pour le staff et la logistique : coordonner, tracer, former et recruter.</p>
                        <ul class="mt-5 space-y-2 hi-body-sm text-slate-600">
                            <li><span class="font-bold text-slate-900">• Gouvernance :</span> rôles, accès, workflows.</li>
                            <li><span class="font-bold text-slate-900">• Pilotage :</span> événements, présences, communication.</li>
                            <li><span class="font-bold text-slate-900">• Capitalisation :</span> docs, formation, archives.</li>
                        </ul>
                    </article>
                    <article class="rounded-3xl border border-emerald-200 bg-emerald-50/50 p-7 shadow-sm">
                        <p class="hi-kicker text-emerald-700">Couche immersion</p>
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-emerald-900">Roleplay de l’unité</h3>
                        <p class="hi-body-sm mt-3 text-emerald-900/80">Pour le personnage et la fiction tactique : identité, progression, accréditation.</p>
                        <ul class="mt-5 space-y-2 hi-body-sm text-emerald-900/80">
                            <li><span class="font-bold text-emerald-900">• Identité RP :</span> nom opérateur, callsign, profil.</li>
                            <li><span class="font-bold text-emerald-900">• Parcours RP :</span> dossier opérateur, jalons.</li>
                            <li><span class="font-bold text-emerald-900">• Cohérence :</span> compte civil / personnage séparés.</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        <!-- Accès modules (bande restaurée) -->
        <nav class="w-full border-y border-white/5 bg-[#050810] py-8 px-6" aria-label="Accès aux modules">
            <div class="mx-auto max-w-6xl">
                <p class="mb-6 text-center hi-kicker text-slate-500">Accès portail Athena</p>
                <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-6">
                    <?php
                    $strip = $loggedIn
                        ? [
                            ['hub', 'Ops', 'Commandement'],
                            ['manoeuvres', 'Présence', 'Manœuvres'],
                            ['communities', 'Unités', 'Communautés'],
                            ['forum', 'Info', 'Forum'],
                            ['orbat', 'Structure', 'ORBAT'],
                            ['c2', 'C2', 'Espace C2'],
                            ['formations', 'LMS', 'Formations'],
                            ['enlistment', 'RH', 'Recrutement'],
                        ]
                        : [
                            ['login', 'Accès', 'Connexion'],
                            ['register', 'Compte', 'Inscription'],
                            ['join', 'Code', 'Rejoindre'],
                            ['communities', 'Unités', 'Communautés'],
                            ['enlistment', 'RH', 'Recrutement'],
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
            ['n' => '01', 'k' => 'Découverte', 't' => "Un portail\npensé pour\ncommander.", 'd' => 'Pas un Discord ni un TeamSpeak : un système d’information pour unités MILSIM.', 'bg' => 'bg-[var(--hi-paper)]', 'ink' => 'text-slate-900', 'mute' => 'text-slate-500'],
            ['n' => '02', 'k' => 'Communauté', 't' => "Une unité\nprivée,\npilotée.", 'd' => 'Créez votre espace, invitez vos membres, isolez vos données.', 'bg' => 'bg-[var(--hi-field)]', 'ink' => 'text-white', 'mute' => 'text-white/55'],
            ['n' => '03', 'k' => 'Organisation', 't' => "ORBAT.\nGrades.\nDroits.", 'd' => 'Structurez les détachements et les responsabilités sans ambiguïté.', 'bg' => 'bg-black', 'ink' => 'text-white', 'mute' => 'text-white/50'],
            ['n' => '04', 'k' => 'Commandement', 't' => "Centre de\ncommandement.", 'd' => 'Synthèse, actions à traiter, mur opérationnel et boîte de réception unifiée.', 'bg' => 'bg-[var(--hi-field-deep)]', 'ink' => 'text-white', 'mute' => 'text-white/50'],
            ['n' => '05', 'k' => 'Entraînement', 't' => "Former\navant\nd’engager.", 'd' => 'Parcours, évaluations et attestations pour aligner l’unité.', 'bg' => 'bg-[var(--hi-paper)]', 'ink' => 'text-slate-900', 'mute' => 'text-slate-500'],
            ['n' => '06', 'k' => 'Opération', 't' => "Du briefing\nau terrain.", 'd' => 'Manœuvres, pointage, puis liaison COMSPEC ATAK / Overwatch.', 'bg' => 'bg-black', 'ink' => 'text-white', 'mute' => 'text-white/50'],
            ['n' => '07', 'k' => 'Debriefing', 't' => "Capitaliser\naprès\nl’action.", 'd' => 'Documents, forum, distinctions et analyses pour progresser.', 'bg' => 'bg-[var(--hi-field)]', 'ink' => 'text-white', 'mute' => 'text-white/55'],
            ['n' => '08', 'k' => 'Athena Compsec', 't' => "Votre unité\nmérite mieux\nqu’un Discord / TS.", 'd' => 'Un portail pensé pour le commandement : clair, structuré, prêt pour le terrain.', 'bg' => 'bg-black', 'ink' => 'text-white', 'mute' => 'text-white/50'],
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
                        <a href="<?= url('register') ?>" class="hi-cta hi-cta-solid">Créer un compte</a>
                        <a href="<?= url('join') ?>" class="hi-cta hi-cta-ghost">Code communauté</a>
                    <?php else: ?>
                        <a href="<?= url('hub') ?>" class="hi-cta hi-cta-solid">Ouvrir le centre</a>
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
                        <p class="hi-kicker text-slate-400">Modules</p>
                        <h2 class="hi-display hi-display-md mt-4">Ce que<br>vous pilotez</h2>
                    </div>
                    <div class="lg:col-span-8">
                        <?php
                        $modules = [
                            ['n' => '01', 'label' => 'Communautés', 'desc' => 'Registre, création, invitation par code, bascule d’espace.', 'href' => url('communities')],
                            ['n' => '02', 'label' => 'Manœuvres', 'desc' => 'Calendrier, RSVP et pointage unifiés.', 'href' => url('manoeuvres')],
                            ['n' => '03', 'label' => 'Formation', 'desc' => 'Parcours, leçons, évaluations et attestations.', 'href' => url('formations')],
                            ['n' => '04', 'label' => 'Recrutement', 'desc' => 'Avis, candidatures et suivi des dossiers.', 'href' => url('enlistment')],
                            ['n' => '05', 'label' => 'Espace C2', 'desc' => 'ATAK, Tacmap, Overwatch et mode terrain.', 'href' => url('c2')],
                            ['n' => '06', 'label' => 'Boîte de réception', 'desc' => 'Notifications, messages et actions à traiter.', 'href' => url('boite-reception')],
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
                <p class="hi-kicker text-white/35">Activité · <?= (int) $platformKpiDays ?> derniers jours</p>
                <div class="mt-10 grid grid-cols-2 gap-10 md:grid-cols-3 lg:grid-cols-6">
                    <?php
                    $kpis = [
                        ['v' => $kpiValue('communities_total'), 'l' => 'Communautés'],
                        ['v' => $kpiValue('users_active_total'), 'l' => 'Membres actifs'],
                        ['v' => $kpiValue('forum_posts_in_period'), 'l' => 'Messages forum'],
                        ['v' => $kpiValue('training_completions_in_period'), 'l' => 'Formations'],
                        ['v' => $kpiValue('enlistments_created_in_period'), 'l' => 'Candidatures'],
                        ['v' => $kpiValue('usage_events_in_period'), 'l' => 'Interactions'],
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
                    <p class="hi-kicker text-slate-400">Questions</p>
                    <h2 class="hi-display hi-display-md mt-4">FAQ</h2>
                </div>
                <div class="lg:col-span-8">
                    <div class="hi-rule-row py-8">
                        <p class="hi-kicker text-slate-400">Accès</p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900 md:text-xl">Comment rejoindre une unité&nbsp;?</h3>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base">Avec un code d’invitation, via une candidature d’enrôlement, ou en créant votre propre communauté sur le portail.</p>
                    </div>
                    <div class="hi-rule-row py-8">
                        <p class="hi-kicker text-slate-400">Isolation</p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900 md:text-xl">Mes données restent-elles dans ma communauté&nbsp;?</h3>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base">Oui. Chaque communauté dispose d’un espace dédié : membres, documents, forum et configuration ne se mélangent pas.</p>
                    </div>
                    <div class="hi-rule-row py-8">
                        <p class="hi-kicker text-slate-400">Terrain</p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900 md:text-xl">Qu’est-ce que ATAK sur Athena&nbsp;?</h3>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base">Une couche de commandement liée au jeu (carte, repères, supervision Overwatch) pour aligner le portail et la session Arma.</p>
                    </div>
                    <div class="hi-rule-row py-8">
                        <p class="hi-kicker text-slate-400">Offre</p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900 md:text-xl">Puis-je commencer gratuitement&nbsp;?</h3>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base">Oui. Des fonctions essentielles sont disponibles ; les capacités avancées se débloquent selon l’offre de la communauté.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Newsletter -->
        <section id="newsletter" class="hi-newsletter bg-[var(--hi-paper)] text-slate-900" aria-labelledby="newsletter-heading">
            <div class="hi-section mx-auto max-w-[100rem]">
                <div class="hi-newsletter__grid">
                    <div class="hi-newsletter__intro">
                        <p class="hi-kicker text-emerald-700">Communications Athena</p>
                        <h2 id="newsletter-heading" class="hi-display hi-display-md mt-4 max-w-3xl">Recevez les<br>nouveautés Athena</h2>
                        <p class="hi-body mt-6 max-w-xl text-slate-600">
                            Suivez les évolutions de la plateforme, les nouveaux modules tactiques, les guides d’installation Arma&nbsp;3 et les mises à jour dédiées aux communautés MILSIM.
                        </p>
                        <ul class="hi-newsletter__highlights mt-8" aria-label="Ce que vous recevrez">
                            <li>Annonces de nouvelles fonctions du portail</li>
                            <li>Guides pratiques pour unités et formateurs</li>
                            <li>Rappels utiles avant les grandes mises à jour</li>
                        </ul>
                    </div>

                    <div class="hi-newsletter__panel">
                        <?php
                        $newsletterMessages = [
                            'confirm_sent' => [
                                'ok' => true,
                                'title' => 'Vérifiez votre boîte e-mail',
                                'text' => 'Un message de confirmation vient de partir. Ouvrez-le et validez votre adresse pour activer l’envoi des communications Athena.',
                            ],
                            'confirmed' => [
                                'ok' => true,
                                'title' => 'Inscription confirmée',
                                'text' => 'Votre adresse est enregistrée. Vous recevrez désormais les nouveautés Athena et les guides destinés aux communautés MILSIM.',
                            ],
                            'unsubscribed' => [
                                'ok' => true,
                                'title' => 'Désinscription effectuée',
                                'text' => 'Vous ne recevrez plus nos communications. Vous pourrez vous réinscrire à tout moment depuis cette page.',
                            ],
                            'invalid_email' => [
                                'ok' => false,
                                'title' => 'Adresse e-mail incorrecte',
                                'text' => 'Saisissez une adresse complète du type nom@exemple.fr, puis réessayez.',
                            ],
                            'csrf' => [
                                'ok' => false,
                                'title' => 'Session expirée',
                                'text' => 'Pour des raisons de sécurité, veuillez renseigner à nouveau votre adresse e-mail et valider le formulaire.',
                            ],
                            'confirm_invalid' => [
                                'ok' => false,
                                'title' => 'Lien de confirmation inutilisable',
                                'text' => 'Ce lien a déjà été utilisé ou n’est plus valable. Inscrivez-vous de nouveau pour recevoir un nouveau message de confirmation.',
                            ],
                            'unsubscribe_invalid' => [
                                'ok' => false,
                                'title' => 'Lien de désabonnement inutilisable',
                                'text' => 'Ce lien n’est plus valable. Si vous recevez encore nos messages, utilisez le lien « Se désabonner » présent en bas de chaque e-mail.',
                            ],
                            'schema_missing' => [
                                'ok' => false,
                                'title' => 'Inscription temporairement impossible',
                                'text' => 'Les communications Athena ne peuvent pas être enregistrées pour le moment. Réessayez un peu plus tard.',
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
                                    <label for="newsletter-email" class="hi-newsletter__label">Adresse e-mail</label>
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
                                        placeholder="vous@exemple.fr"
                                        class="hi-newsletter__input"
                                        <?= $newsletterFormDisabled ? 'disabled' : '' ?>
                                        aria-describedby="newsletter-help newsletter-privacy"
                                    >
                                    <p id="newsletter-help" class="hi-newsletter__help">
                                        Un e-mail de confirmation vous sera envoyé afin de valider votre inscription.
                                    </p>
                                    <p id="newsletter-email-error" class="hi-newsletter__field-error" hidden role="alert">
                                        Indiquez une adresse e-mail valide pour continuer.
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    class="hi-cta hi-cta-ink hi-newsletter__submit"
                                    <?= $newsletterFormDisabled ? 'disabled' : '' ?>
                                    data-newsletter-submit
                                    data-label-idle="S’inscrire aux communications"
                                    data-label-loading="Envoi en cours…"
                                >
                                    <span data-newsletter-submit-label>S’inscrire aux communications</span>
                                </button>

                                <p id="newsletter-privacy" class="hi-newsletter__privacy">
                                    Vous pourrez vous désabonner à tout moment depuis chaque message reçu. Aucune adresse n’est partagée avec des tiers.
                                </p>
                            </form>
                        <?php elseif ($newsletterStatus === 'confirm_sent'): ?>
                            <p class="hi-newsletter__empty-hint">
                                Vous n’avez pas reçu le message&nbsp;? Vérifiez le dossier indésirables, puis réessayez dans quelques minutes si besoin.
                            </p>
                            <p class="mt-4">
                                <a href="<?= htmlspecialchars(url('/#newsletter'), ENT_QUOTES, 'UTF-8') ?>" class="hi-newsletter__retry" data-newsletter-retry>
                                    Utiliser une autre adresse
                                </a>
                            </p>
                        <?php else: ?>
                            <p class="hi-newsletter__empty-hint">
                                Merci de votre confiance. Les prochaines communications Athena arriveront directement dans votre boîte e-mail.
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
            <p class="hi-kicker text-emerald-400/80">Expérience</p>
            <h2 class="mt-3 text-2xl font-black italic uppercase tracking-tight">Souhaitez-vous activer l’expérience immersive&nbsp;?</h2>
            <p class="hi-body mt-4 text-sm text-white/60">Vidéo en arrière-plan, son et animation d’introduction. Votre choix est mémorisé sur cet appareil. Vous pourrez le modifier plus tard.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <button type="button" id="immersive-yes" class="hi-cta hi-cta-solid flex-1">Oui</button>
                <button type="button" id="immersive-no" class="hi-cta hi-cta-ghost flex-1">Non</button>
            </div>
        </div>
    </dialog>

    <footer class="border-t border-white/10 bg-black py-10 text-white">
        <div class="mx-auto flex max-w-[100rem] flex-col gap-8 px-5 md:flex-row md:items-start md:justify-between md:px-8">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em]">Athena</p>
                <p class="hi-body-sm mt-3 max-w-xs text-white/45">Portail communautaire pour unités MILSIM Arma 3.</p>
            </div>
            <nav class="flex max-w-xl flex-wrap gap-x-5 gap-y-2 text-xs" aria-label="Informations légales">
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

        var slides = document.querySelectorAll('#heroSlider .slide');
        var dots = document.querySelectorAll('.dot');
        var current = 0;
        var slideTimer = null;

        function updateSlide(index) {
            if (!slides.length) return;
            var img = slides[current].querySelector('img');
            if (img) img.style.transform = 'scale(1)';
            slides[current].classList.replace('opacity-100', 'opacity-0');
            if (dots[current]) {
                dots[current].classList.remove('bg-white');
                dots[current].classList.add('bg-white/25');
            }
            current = (index + slides.length) % slides.length;
            slides[current].classList.replace('opacity-0', 'opacity-100');
            img = slides[current].querySelector('img');
            if (img) img.style.transform = 'scale(1.08)';
            if (dots[current]) {
                dots[current].classList.remove('bg-white/25');
                dots[current].classList.add('bg-white');
            }
        }
        function nextSlide() { updateSlide(current + 1); }
        function prevSlide() { updateSlide(current - 1); }
        function startSlider() {
            if (slideTimer) clearInterval(slideTimer);
            slideTimer = setInterval(nextSlide, 6000);
        }
        function stopSlider() {
            if (slideTimer) clearInterval(slideTimer);
            slideTimer = null;
        }
        if (slides.length) {
            updateSlide(0);
            startSlider();
        }

        (function immersiveExperience() {
            var KEY = 'athena_immersive_v1';
            var dlg = document.getElementById('immersive-consent');
            var video = document.getElementById('hero-video');
            var poster = document.getElementById('hero-poster');
            var btnLater = document.getElementById('btn-enable-immersive');
            var saved = null;
            try { saved = localStorage.getItem(KEY); } catch (e) {}

            function loadSources() {
                if (!video) return;
                var sources = video.querySelectorAll('source[data-src]');
                sources.forEach(function (s) {
                    if (!s.getAttribute('src')) s.setAttribute('src', s.getAttribute('data-src'));
                });
                video.load();
            }

            function enableImmersive(withSound) {
                try { localStorage.setItem(KEY, withSound ? 'full' : 'silent'); } catch (e) {}
                stopSlider();
                if (!video) return;
                loadSources();
                video.classList.remove('hidden');
                slides.forEach(function (s) { s.classList.add('opacity-0'); s.classList.remove('opacity-100'); });
                video.muted = !withSound;
                var playPromise = video.play();
                if (playPromise && playPromise.catch) playPromise.catch(function () {
                    video.muted = true;
                    video.play().catch(function () {});
                });
                if (btnLater) btnLater.classList.add('hidden');
            }

            function disableImmersive() {
                try { localStorage.setItem(KEY, 'off'); } catch (e) {}
                if (video) {
                    video.pause();
                    video.classList.add('hidden');
                }
                if (slides.length) {
                    updateSlide(current);
                    startSlider();
                }
                if (btnLater) btnLater.classList.remove('hidden');
            }

            if (saved === 'full') enableImmersive(true);
            else if (saved === 'silent') enableImmersive(false);
            else if (saved === 'off') {
                if (btnLater) btnLater.classList.remove('hidden');
            } else if (dlg && typeof dlg.showModal === 'function') {
                window.setTimeout(function () { dlg.showModal(); }, 400);
            }

            var yes = document.getElementById('immersive-yes');
            var no = document.getElementById('immersive-no');
            if (yes) yes.addEventListener('click', function () { enableImmersive(true); if (dlg) dlg.close(); });
            if (no) no.addEventListener('click', function () { disableImmersive(); if (dlg) dlg.close(); });
            if (btnLater) btnLater.addEventListener('click', function () { enableImmersive(true); });
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
                    ? (submit.getAttribute('data-label-loading') || 'Envoi en cours…')
                    : (submit.getAttribute('data-label-idle') || 'S’inscrire aux communications');
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
</body>
</html>
