<?php
$base = url('');
$title = $title ?? 'Athena Compsec — Portail MILSIM';
$loggedIn = (bool) \App\Core\Session::get('user_id');
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
</head>
<body class="layout-light text-slate-900 selection:bg-slate-900 selection:text-white overflow-x-hidden">

    <div class="grain"></div>

    <div id="bodyOverlay" class="overlay fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[110]" onclick="toggleMenu()"></div>

    <div id="navDrawer" class="drawer-translate fixed top-0 left-0 z-[120] flex h-full w-[min(100%,320px)] flex-col overflow-hidden border-r border-slate-200/80 bg-slate-50 shadow-2xl">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200/80 px-5 py-4">
            <span class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Menu</span>
            <button type="button" onclick="toggleMenu()" class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-200/80 hover:text-slate-900" aria-label="Fermer le menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-3 py-3" aria-label="Navigation du portail">
            <?php
            $homeNavLink = 'flex items-center rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-white hover:shadow-sm';
            $homeNavAccent = 'flex items-center rounded-xl px-3 py-2.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50';
            ?>
            <?php if ($loggedIn): ?>
                <?php
                $scopeEntries = navigation_scope_drawer_entries();
                $scopeGroups = navigation_scope_group_entries($scopeEntries);
                $scopeCount = count($scopeEntries);
                $navCurrentPath = navigation_current_path();
                ?>
                <div class="mb-3 rounded-xl border border-slate-200/90 bg-white/90 px-3 py-2.5 shadow-sm">
                    <p class="text-[9px] font-black uppercase tracking-[0.28em] text-slate-400">Périmètre des accès</p>
                    <p class="mt-1 text-[11px] leading-snug text-slate-600">
                        Liste alignée sur votre rôle et la communauté active — seules les pages autorisées apparaissent.
                        <?php if ($scopeCount > 0): ?>
                            <span class="mt-1 block text-[10px] font-semibold text-emerald-800"><?= (int) $scopeCount ?> raccourci<?= $scopeCount > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php foreach ($scopeGroups as $groupName => $links): ?>
                    <p class="px-3 pt-3 pb-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 first:pt-2"><?= htmlspecialchars($groupName) ?></p>
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
                <div class="mb-3 rounded-xl border border-slate-200/90 bg-white/90 px-3 py-2.5 shadow-sm">
                    <p class="text-[9px] font-black uppercase tracking-[0.28em] text-slate-400">Périmètre des accès</p>
                    <p class="mt-1 text-[11px] leading-snug text-slate-600">Une fois connecté, ce menu listera uniquement les modules et pages auxquels vous avez droit.</p>
                </div>
                <a href="<?= $base ?>/" onclick="toggleMenu()" class="<?= $homeNavLink ?>">Accueil</a>
                <a href="<?= url('login') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>">Connexion</a>
                <a href="<?= url('register') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?>">Inscription</a>
                <a href="<?= url('join') ?>" onclick="toggleMenu()" class="<?= $homeNavLink ?> text-slate-600">Rejoindre avec un code</a>
            <?php endif; ?>
        </nav>

        <div class="shrink-0 border-t border-slate-200/80 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-950 p-4 text-white">
            <p class="mb-3 text-center text-[10px] font-black uppercase tracking-[0.2em] text-emerald-400/90">Athena Compsec</p>
            <?php if (!$loggedIn): ?>
            <p class="mb-3 text-center text-[11px] leading-snug text-white/60">Accédez à tout le portail avec un compte.</p>
            <div class="flex flex-col gap-2">
                <a href="<?= url('register') ?>" onclick="toggleMenu()" class="flex min-h-[2.75rem] items-center justify-center rounded-xl bg-emerald-500 text-center text-[10px] font-black uppercase tracking-wider text-slate-950 transition hover:bg-emerald-400">Créer un compte</a>
                <a href="<?= url('login') ?>" onclick="toggleMenu()" class="flex min-h-[2.5rem] items-center justify-center rounded-xl border border-white/20 bg-white/5 text-center text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-white/10">Connexion</a>
            </div>
            <?php else: ?>
            <a href="<?= url('dashboard') ?>" onclick="toggleMenu()" class="mb-2 flex min-h-[2.75rem] items-center justify-center rounded-xl bg-emerald-500 text-center text-[10px] font-black uppercase tracking-wider text-slate-950 transition hover:bg-emerald-400">Ouvrir le tableau de bord</a>
            <form method="post" action="<?= url('logout') ?>" class="mt-1">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="w-full rounded-xl py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-white/50 transition hover:bg-white/5 hover:text-white">Déconnexion</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <header class="sticky top-0 z-[100] w-full bg-slate-50/95 backdrop-blur-md border-b border-slate-900/[0.03]">
        <div class="max-w-5xl mx-auto px-8 h-16 flex items-center justify-between relative text-slate-900 uppercase">
            
            <div class="flex-1">
                <button onclick="toggleMenu()" class="group flex flex-col gap-2 outline-none w-6 h-6 justify-center">
                    <span class="h-[1px] w-full bg-slate-900 transition-all duration-500 group-hover:translate-x-1"></span>
                    <div class="flex justify-end">
                        <span class="h-[1px] w-3 bg-slate-900 transition-all duration-500 group-hover:w-full group-hover:translate-x-0"></span>
                    </div>
                </button>
            </div>

            <div class="absolute left-1/2 -translate-x-1/2 flex flex-col items-center text-center">
                <a href="<?= $base ?>/" class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-900 sm:tracking-[0.26em]">
                    Athena Compsec
                </a>
                <span class="mt-0.5 text-[6px] font-semibold uppercase tracking-[0.32em] text-slate-400">Portail MILSIM</span>
            </div>

            <div class="flex flex-1 justify-end items-center gap-6">
                <div class="hidden sm:flex flex-col items-end leading-none">
                    <span class="text-[7px] font-black tracking-[0.28em] text-slate-400">Heure locale</span>
                    <span id="home-header-clock" class="text-[10px] font-mono font-semibold tracking-wide text-slate-700 tabular-nums">--:--:--</span>
                </div>
                <div class="flex items-center gap-2 border-l border-slate-200 pl-6">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-30"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                    </span>
                    <span class="text-[8px] font-black uppercase tracking-[0.18em] text-slate-500">Service en ligne</span>
                </div>
            </div>
        </div>
    </header>

    <main>
        <section class="relative h-[85vh] w-full overflow-hidden bg-black group">
            <div id="heroSlider" class="absolute inset-0">
                <div class="slide absolute inset-0 opacity-100 transition-opacity duration-1000 ease-in-out">
                    <img src="<?= $base ?>/assets/images/fog-team.jpg" class="w-full h-full object-cover grayscale brightness-[0.4] transition-transform duration-[10000ms] ease-linear scale-100" alt="FOG Team">
                </div>
                <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                    <img src="<?= $base ?>/assets/images/fog-banner.jpg" class="w-full h-full object-cover grayscale brightness-[0.4] transition-transform duration-[10000ms] ease-linear scale-100" alt="FOG Banner">
                </div>
                <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                    <img src="<?= $base ?>/assets/images/hero-explosion.jpg" class="w-full h-full object-cover grayscale brightness-[0.4] transition-transform duration-[10000ms] ease-linear scale-100" alt="Ops">
                </div>
                <div class="slide absolute inset-0 opacity-0 transition-opacity duration-1000 ease-in-out">
                    <img src="<?= $base ?>/assets/images/night-team.jpg" class="w-full h-full object-cover grayscale brightness-[0.4] transition-transform duration-[10000ms] ease-linear scale-100" alt="Night Ops">
                </div>
            </div>

            <div class="absolute inset-0 z-10 pointer-events-none flex flex-col justify-between p-10 md:p-16">
                <div class="flex justify-between items-start pointer-events-auto">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.6)]"></span>
                            <span class="text-[9px] font-black uppercase tracking-[0.28em] text-white/90">Athena Compsec</span>
                        </div>
                        <span class="max-w-xs text-[7px] font-semibold uppercase leading-relaxed tracking-[0.2em] text-white/35">Portail communautaire pour unités Arma 3 — organisation, présence et formation au même endroit.</span>
                    </div>
                    <div class="flex flex-col items-end text-right">
                        <span id="timestamp" class="mb-1 font-mono text-[9px] uppercase tracking-widest text-white/45"></span>
                        <span class="text-[7px] font-bold uppercase tracking-[0.35em] text-white/25">Heure locale</span>
                    </div>
                </div>

                <div class="max-w-3xl pointer-events-auto">
                    <p class="mb-4 text-[10px] font-black uppercase tracking-[0.4em] text-emerald-400/90">Ce que le portail fait pour vous</p>
                    <h1 class="mb-6 text-4xl font-black leading-[0.95] tracking-tight text-white md:text-6xl lg:text-7xl">
                        Une base pour votre unité,<br class="hidden sm:block"> du recrutement au terrain.
                    </h1>
                    <div class="mb-6 h-px w-24 bg-white/20"></div>
                    <p class="max-w-xl text-sm font-medium leading-relaxed text-white/55 md:text-base">
                        Rattachez vos joueurs à une communauté, suivez les présences et les événements, diffusez documents et modpacks,
                        structurez l’ORBAT et formez vos équipes avec des parcours suivis — le tout avec des rôles et des droits adaptés au staff et aux membres.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <?php if (!$loggedIn): ?>
                        <a href="<?= url('register') ?>" class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-6 py-3 text-[10px] font-black uppercase tracking-wider text-slate-950 transition hover:bg-emerald-400">Créer un compte</a>
                        <a href="<?= url('login') ?>" class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/5 px-6 py-3 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-white/10">Connexion</a>
                        <a href="<?= url('join') ?>" class="inline-flex items-center justify-center rounded-xl px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-white/70 underline decoration-white/30 underline-offset-4 transition hover:text-white">J’ai un code communauté</a>
                        <?php else: ?>
                        <a href="<?= url('dashboard') ?>" class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-6 py-3 text-[10px] font-black uppercase tracking-wider text-slate-950 transition hover:bg-emerald-400">Ouvrir le tableau de bord</a>
                        <a href="<?= url('hub') ?>" class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/5 px-6 py-3 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-white/10">Centre opérationnel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-10 left-1/2 -translate-x-1/2 flex items-center gap-12 z-20">
                <button onclick="prevSlide()" class="text-white/20 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                
                <div class="flex gap-4">
                    <div class="dot w-1 h-1 bg-white rounded-full transition-all duration-500"></div>
                    <div class="dot w-1 h-1 bg-white/20 rounded-full transition-all duration-500"></div>
                    <div class="dot w-1 h-1 bg-white/20 rounded-full transition-all duration-500"></div>
                    <div class="dot w-1 h-1 bg-white/20 rounded-full transition-all duration-500"></div>
                </div>

                <button onclick="nextSlide()" class="text-white/20 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>

            <div class="absolute bottom-0 left-0 w-full h-[2px] bg-white/5 z-20">
                <div id="progress" class="h-full bg-white/20 w-0 transition-all duration-[6000ms] ease-linear"></div>
            </div>
        </section>

        <script>
            const slides = document.querySelectorAll('.slide');
            const dots = document.querySelectorAll('.dot');
            const progress = document.getElementById('progress');
            let current = 0;

            function updateSlide(index) {
                slides[current].querySelector('img').style.transform = 'scale(1)';
                slides[current].classList.replace('opacity-100', 'opacity-0');
                dots[current].classList.replace('bg-white', 'bg-white/20');
                
                current = (index + slides.length) % slides.length;
                
                slides[current].classList.replace('opacity-0', 'opacity-100');
                slides[current].querySelector('img').style.transform = 'scale(1.1)';
                dots[current].classList.replace('bg-white/20', 'bg-white');

                progress.style.transition = 'none';
                progress.style.width = '0%';
                setTimeout(() => {
                    progress.style.transition = 'width 6000ms linear';
                    progress.style.width = '100%';
                }, 50);
            }

            function nextSlide() { updateSlide(current + 1); }
            function prevSlide() { updateSlide(current - 1); }

            function tickClocks() {
                const now = new Date();
                const t = now.getHours().toString().padStart(2, '0') + ':' +
                    now.getMinutes().toString().padStart(2, '0') + ':' +
                    now.getSeconds().toString().padStart(2, '0');
                const ts = document.getElementById('timestamp');
                if (ts) ts.innerText = t;
                const hc = document.getElementById('home-header-clock');
                if (hc) hc.innerText = t;
            }
            tickClocks();
            setInterval(tickClocks, 1000);

            setInterval(nextSlide, 6000);
            updateSlide(0);
        </script>

        <nav class="w-full bg-[#050810] py-8 px-6 border-t border-white/5">
            <div class="max-w-6xl mx-auto">
                <p class="text-center text-[8px] font-black uppercase tracking-[0.35em] text-slate-500 mb-6">Accès portail Athena</p>
                <div class="flex flex-wrap justify-center items-center gap-x-8 gap-y-8">
                    <?php if ($loggedIn): ?>
                    <a href="<?= url('dashboard') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Vue</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">DASHBOARD</span>
                    </a>
                    <a href="<?= url('hub') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Ops</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">HUB</span>
                    </a>
                    <a href="<?= url('pointage') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-emerald-600 uppercase">Présence</span>
                        <span class="text-emerald-300 text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-200">POINTAGE</span>
                    </a>
                    <a href="<?= url('communities') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Unités</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">COMMUNAUTÉS</span>
                    </a>
                    <?php else: ?>
                    <a href="<?= url('login') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-emerald-500 uppercase">Accès</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">CONNEXION</span>
                    </a>
                    <a href="<?= url('register') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Compte</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">INSCRIPTION</span>
                    </a>
                    <a href="<?= url('join') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Code</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">REJOINDRE</span>
                    </a>
                    <?php endif; ?>

                    <a href="<?= url('forum') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Info</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">FORUM</span>
                    </a>

                    <a href="<?= url('orbat') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Structure</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">ORBAT</span>
                    </a>

                    <a href="<?= url('atak') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">C2</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">ATAK</span>
                    </a>
                    <a href="<?= url('tacmap') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Carte</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">TACMAP</span>
                    </a>
                    <a href="<?= url('overwatch') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Vue</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">OVERWATCH</span>
                    </a>

                    <a href="<?= url('documents') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Docs</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">DOCUMENTS</span>
                    </a>

                    <a href="<?= url('formations') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">LMS</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">FORMATIONS</span>
                    </a>
                    <a href="<?= url('evenements') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Agenda</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">ÉVÉNEMENTS</span>
                    </a>
                    <a href="<?= url('messages') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Fil</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">MESSAGES</span>
                    </a>
                    <a href="<?= url('equipment') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Tenue</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">MATÉRIEL</span>
                    </a>

                    <a href="<?= url('enlistment') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center relative px-2">
                        <span class="text-[7px] font-black tracking-[0.25em] text-emerald-500 uppercase">RH</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">RECRUTEMENT</span>
                    </a>
                </div>

                <div class="mt-8 flex flex-col items-center justify-between gap-2 border-t border-white/[0.03] pt-4 text-center sm:flex-row sm:text-left">
                    <span class="text-[6px] font-mono uppercase tracking-[0.35em] text-slate-600">Athena Compsec — accès aux modules après connexion</span>
                    <div class="hidden h-px flex-1 bg-gradient-to-r from-transparent via-white/5 to-transparent sm:mx-8 sm:block"></div>
                    <span class="text-[6px] font-semibold uppercase tracking-[0.3em] text-slate-500">Créer une communauté ou rejoindre avec un code</span>
                </div>
            </div>
        </nav>

        <section class="relative overflow-hidden border-y border-slate-200 bg-white">
            <div class="relative mx-auto max-w-6xl px-6 py-16 md:py-20">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="mb-3 text-[9px] font-black uppercase tracking-[0.4em] text-emerald-600">Pourquoi Athena Compsec</p>
                    <h2 class="mb-4 text-3xl font-black uppercase tracking-tight text-slate-900 md:text-4xl">Un portail pour toute la chaîne</h2>
                    <p class="text-sm leading-relaxed text-slate-600 md:text-base">De l’arrivée d’un nouveau membre au briefing du soir : regroupez communautés, présence, documents, formation et cartographie dans un espace unique, avec des droits adaptés au rôle de chacun.</p>
                </div>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-6 text-left transition hover:border-emerald-200 hover:shadow-sm">
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <h3 class="mb-2 text-sm font-black uppercase tracking-wide text-slate-900">Plusieurs communautés</h3>
                        <p class="text-xs leading-relaxed text-slate-600">Registre des unités, création d’espace, invitation par code et bascule rapide entre organisations.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-6 text-left transition hover:border-emerald-200 hover:shadow-sm">
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-sky-500/10 text-sky-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h3 class="mb-2 text-sm font-black uppercase tracking-wide text-slate-900">Rôles et accès</h3>
                        <p class="text-xs leading-relaxed text-slate-600">Le staff pilote l’organisation ; chaque membre ne voit que ce qui lui est utile, selon les permissions définies.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-6 text-left transition hover:border-emerald-200 hover:shadow-sm">
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-800">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <h3 class="mb-2 text-sm font-black uppercase tracking-wide text-slate-900">Formation suivie</h3>
                        <p class="text-xs leading-relaxed text-slate-600">Catalogue de parcours, leçons, évaluations et attestations lorsque votre offre l’active pour la communauté.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-6 text-left transition hover:border-emerald-200 hover:shadow-sm">
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-violet-500/10 text-violet-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </div>
                        <h3 class="mb-2 text-sm font-black uppercase tracking-wide text-slate-900">Carte et jeu</h3>
                        <p class="text-xs leading-relaxed text-slate-600">Tacmap, vue Overwatch et liaison ATAK pour aligner le portail avec ce qui se passe sur le terrain virtuel.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden border-y border-white/5 bg-[#050810] text-white">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.08),transparent_40%)]"></div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.035]" style="background-image:linear-gradient(rgba(255,255,255,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.06) 1px, transparent 1px); background-size: 48px 48px;"></div>

            <div class="relative mx-auto max-w-6xl px-6 py-20">
                <div class="mb-14 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="mb-4 text-[9px] font-black uppercase tracking-[0.45em] text-emerald-500">Trois grands usages</p>
                        <h2 class="mb-5 text-3xl font-black uppercase leading-none tracking-tight md:text-5xl">Ce que vos équipes font sur le portail</h2>
                        <div class="mb-5 h-px w-20 bg-white/15"></div>
                        <p class="max-w-xl text-sm font-medium leading-relaxed text-white/55">Chaque bloc correspond à des écrans déjà disponibles : pas de démonstration fictive, les liens mènent aux modules réels (connexion requise selon les droits).</p>
                    </div>
                    <div class="grid min-w-[260px] grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-white/[0.06] bg-white/[0.03] p-4">
                            <p class="mb-1 text-[8px] font-black uppercase tracking-[0.28em] text-white/35">Organisation</p>
                            <p class="text-lg font-black tracking-tight text-emerald-400">Communautés</p>
                        </div>
                        <div class="rounded-2xl border border-white/[0.06] bg-white/[0.03] p-4">
                            <p class="mb-1 text-[8px] font-black uppercase tracking-[0.28em] text-white/35">Quotidien</p>
                            <p class="text-lg font-black tracking-tight text-white">Hub &amp; présence</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <article class="group relative overflow-hidden rounded-3xl border border-white/[0.06] bg-white/[0.03] p-6 transition-colors hover:border-emerald-500/35">
                        <div class="absolute left-0 top-0 h-0.5 w-full bg-gradient-to-r from-emerald-500/80 via-emerald-500/20 to-transparent"></div>
                        <h3 class="mb-2 text-xl font-black uppercase tracking-tight">Structurer l’unité</h3>
                        <p class="mb-6 text-xs leading-relaxed text-white/50">Créer ou rejoindre une communauté, inviter avec un code, gérer les rattachements et préparer le recrutement.</p>
                        <dl class="space-y-3 border-t border-white/[0.06] pt-4 text-[11px]">
                            <div class="flex justify-between gap-3 border-b border-white/[0.06] pb-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Registre</dt>
                                <dd class="text-right font-semibold text-white/90">Parcourir les communautés</dd>
                            </div>
                            <div class="flex justify-between gap-3 border-b border-white/[0.06] pb-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Adhésion</dt>
                                <dd class="text-right font-semibold text-white/90">Code ou formulaire d’enrôlement</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Staff</dt>
                                <dd class="text-right font-semibold text-white/90">Administration de l’unité</dd>
                            </div>
                        </dl>
                        <div class="mt-6 flex flex-wrap gap-x-4 gap-y-2 text-[10px] font-bold uppercase tracking-wide text-emerald-400">
                            <a href="<?= url('communities') ?>" class="transition hover:text-white">Communautés</a>
                            <a href="<?= url('join') ?>" class="transition hover:text-white">Rejoindre</a>
                            <a href="<?= url('enlistment') ?>" class="transition hover:text-white">Enrôlement</a>
                        </div>
                    </article>

                    <article class="group relative overflow-hidden rounded-3xl border border-white/[0.06] bg-white/[0.03] p-6 transition-colors hover:border-amber-400/35">
                        <div class="absolute left-0 top-0 h-0.5 w-full bg-gradient-to-r from-amber-400/80 via-amber-400/20 to-transparent"></div>
                        <h3 class="mb-2 text-xl font-black uppercase tracking-tight">Coordonner le quotidien</h3>
                        <p class="mb-6 text-xs leading-relaxed text-white/50">Vue d’ensemble, point de présence, agenda des événements et fil de messages internes à la communauté.</p>
                        <dl class="space-y-3 border-t border-white/[0.06] pt-4 text-[11px]">
                            <div class="flex justify-between gap-3 border-b border-white/[0.06] pb-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Synthèse</dt>
                                <dd class="text-right font-semibold text-white/90">Tableau de bord personnel</dd>
                            </div>
                            <div class="flex justify-between gap-3 border-b border-white/[0.06] pb-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Ops</dt>
                                <dd class="text-right font-semibold text-white/90">Hub et pointage</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Échanges</dt>
                                <dd class="text-right font-semibold text-white/90">Événements &amp; messages</dd>
                            </div>
                        </dl>
                        <div class="mt-6 flex flex-wrap gap-x-4 gap-y-2 text-[10px] font-bold uppercase tracking-wide text-amber-300">
                            <a href="<?= url('dashboard') ?>" class="transition hover:text-white">Tableau de bord</a>
                            <a href="<?= url('hub') ?>" class="transition hover:text-white">Hub</a>
                            <a href="<?= url('pointage') ?>" class="transition hover:text-white">Pointage</a>
                            <a href="<?= url('evenements') ?>" class="transition hover:text-white">Événements</a>
                            <a href="<?= url('messages') ?>" class="transition hover:text-white">Messages</a>
                        </div>
                    </article>

                    <article class="group relative overflow-hidden rounded-3xl border border-white/[0.06] bg-white/[0.03] p-6 transition-colors hover:border-sky-400/35">
                        <div class="absolute left-0 top-0 h-0.5 w-full bg-gradient-to-r from-sky-400/80 via-sky-400/20 to-transparent"></div>
                        <h3 class="mb-2 text-xl font-black uppercase tracking-tight">Préparer le terrain</h3>
                        <p class="mb-6 text-xs leading-relaxed text-white/50">ORBAT, documents, formation, packs de mods, matériel référencé et cartographie pour la partie.</p>
                        <dl class="space-y-3 border-t border-white/[0.06] pt-4 text-[11px]">
                            <div class="flex justify-between gap-3 border-b border-white/[0.06] pb-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Structure</dt>
                                <dd class="text-right font-semibold text-white/90">ORBAT &amp; fiches</dd>
                            </div>
                            <div class="flex justify-between gap-3 border-b border-white/[0.06] pb-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Doctrine</dt>
                                <dd class="text-right font-semibold text-white/90">Bibliothèque documentaire</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="font-bold uppercase tracking-wide text-white/35">Accréditation</dt>
                                <dd class="text-right font-semibold text-white/90">Dossier opérateur</dd>
                            </div>
                        </dl>
                        <div class="mt-6 flex flex-wrap gap-x-4 gap-y-2 text-[10px] font-bold uppercase tracking-wide text-sky-300">
                            <a href="<?= url('orbat') ?>" class="transition hover:text-white">ORBAT</a>
                            <a href="<?= url('forum') ?>" class="transition hover:text-white">Forum</a>
                            <a href="<?= url('documents') ?>" class="transition hover:text-white">Documents</a>
                            <a href="<?= url('formations') ?>" class="transition hover:text-white">Formations</a>
                            <a href="<?= url('modpacks') ?>" class="transition hover:text-white">Modpacks</a>
                            <a href="<?= url('equipment') ?>" class="transition hover:text-white">Matériel</a>
                            <a href="<?= url('atak') ?>" class="transition hover:text-white">ATAK</a>
                            <a href="<?= url('tacmap') ?>" class="transition hover:text-white">Tacmap</a>
                            <a href="<?= url('overwatch') ?>" class="transition hover:text-white">Overwatch</a>
                            <a href="<?= url('dossier-operateur/accreditation') ?>" class="transition hover:text-white">Dossier opérateur</a>
                        </div>
                    </article>
                </div>
            </div>
        </section>
        <section class="relative overflow-hidden border-y border-slate-900/[0.04] bg-white text-slate-900">
            <div class="pointer-events-none absolute inset-0 opacity-[0.03]" style="background-image:linear-gradient(rgba(15,23,42,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(15,23,42,0.08) 1px, transparent 1px); background-size: 56px 56px;"></div>

            <div class="relative mx-auto max-w-6xl px-6 py-20">
                <div class="mb-14 max-w-3xl">
                    <p class="mb-4 text-[9px] font-black uppercase tracking-[0.45em] text-emerald-600">Modules du portail</p>
                    <h2 class="mb-5 text-3xl font-black uppercase leading-none tracking-tight md:text-5xl">Fonctions livrées dans Athena Compsec</h2>
                    <div class="mb-5 h-px w-20 bg-slate-900/10"></div>
                    <p class="text-sm font-medium leading-relaxed text-slate-600 md:text-base">Chaque carte résume ce que vous trouverez après connexion. L’activation précise peut dépendre de l’offre de votre communauté et des rôles attribués.</p>
                </div>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                        <h3 class="mb-2 text-lg font-black uppercase tracking-tight text-slate-900">Tableau de bord</h3>
                        <p class="mb-4 flex-1 text-sm leading-relaxed text-slate-600">Vue d’entrée après connexion : raccourcis de la communauté, rappels et accès rapides vers les modules utiles.</p>
                        <ul class="mb-5 space-y-1.5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Synthèse personnelle et messages de la communauté</li>
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Liens épinglés par le staff</li>
                        </ul>
                        <a href="<?= url('dashboard') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Ouvrir le tableau de bord →</a>
                    </article>

                    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                        <h3 class="mb-2 text-lg font-black uppercase tracking-tight text-slate-900">Forum</h3>
                        <p class="mb-4 flex-1 text-sm leading-relaxed text-slate-600">Espace de publication par catégories : briefings, annonces et discussions, avec modération côté organisation.</p>
                        <ul class="mb-5 space-y-1.5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Fils structurés par thème</li>
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Pièces jointes selon les règles de la communauté</li>
                        </ul>
                        <a href="<?= url('forum') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Accéder au forum →</a>
                    </article>

                    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                        <h3 class="mb-2 text-lg font-black uppercase tracking-tight text-slate-900">ORBAT &amp; personnel</h3>
                        <p class="mb-4 flex-1 text-sm leading-relaxed text-slate-600">Organigramme de l’unité et fiches des membres pour aligner les rôles jeu et les responsabilités.</p>
                        <ul class="mb-5 space-y-1.5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Vue hiérarchique des détachements</li>
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Fiche opérateur et complétude du profil</li>
                        </ul>
                        <a href="<?= url('orbat') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Voir l’ORBAT →</a>
                    </article>

                    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                        <h3 class="mb-2 text-lg font-black uppercase tracking-tight text-slate-900">Événements &amp; présence</h3>
                        <p class="mb-4 flex-1 text-sm leading-relaxed text-slate-600">Agenda des activités, réponses de présence et rappels pour les séances collectives.</p>
                        <ul class="mb-5 space-y-1.5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Inscription et suivi par événement</li>
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Complément avec le pointage dédié</li>
                        </ul>
                        <a href="<?= url('evenements') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Consulter l’agenda →</a>
                    </article>

                    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                        <h3 class="mb-2 text-lg font-black uppercase tracking-tight text-slate-900">Documents &amp; formation</h3>
                        <p class="mb-4 flex-1 text-sm leading-relaxed text-slate-600">Bibliothèque classée et parcours pédagogiques avec progression, évaluations et attestations lorsque l’offre le permet.</p>
                        <ul class="mb-5 space-y-1.5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Dossiers et droits de lecture par profil</li>
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Catalogue de formations et inscriptions</li>
                        </ul>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            <a href="<?= url('documents') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Documents →</a>
                            <a href="<?= url('formations') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Formations →</a>
                        </div>
                    </article>

                    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                        <h3 class="mb-2 text-lg font-black uppercase tracking-tight text-slate-900">Cartographie &amp; jeu</h3>
                        <p class="mb-4 flex-1 text-sm leading-relaxed text-slate-600">Tacmap et vue Overwatch pour suivre la situation ; liaison ATAK pour relier le jeu au portail ; modpacks et fiches matériel pour préparer la séance.</p>
                        <ul class="mb-5 space-y-1.5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Cartes Arma et superposition des repères</li>
                            <li class="flex gap-2"><span class="text-emerald-600">•</span> Téléchargement des packs et consignes matériel</li>
                        </ul>
                        <div class="flex flex-wrap gap-x-3 gap-y-2">
                            <a href="<?= url('atak') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">ATAK →</a>
                            <a href="<?= url('tacmap') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Tacmap →</a>
                            <a href="<?= url('overwatch') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Overwatch →</a>
                            <a href="<?= url('modpacks') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Modpacks →</a>
                            <a href="<?= url('equipment') ?>" class="inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:text-slate-900">Matériel →</a>
                        </div>
                    </article>
                </div>

                <div class="mt-12 rounded-3xl border border-slate-200 bg-slate-50 p-8 md:flex md:items-center md:justify-between md:gap-8">
                    <div class="mb-6 md:mb-0">
                        <p class="mb-2 text-[10px] font-black uppercase tracking-[0.35em] text-slate-500">Recrutement &amp; accès</p>
                        <p class="max-w-xl text-sm text-slate-700">Parcours d’enrôlement configurable, messages préparés pour le staff et dossier opérateur pour structurer l’accréditation au sein de l’unité.</p>
                    </div>
                    <div class="flex flex-shrink-0 flex-wrap gap-3">
                        <a href="<?= url('enlistment') ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-slate-800">Enrôlement</a>
                        <a href="<?= url('dossier-operateur/accreditation') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-[10px] font-black uppercase tracking-wider text-slate-800 transition hover:border-slate-400">Dossier opérateur</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php $newsletterStatus = (string) ($_GET['newsletter'] ?? ''); ?>
    <section id="newsletter" class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-4xl px-6 py-14">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-emerald-700">Newsletter Athena</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Recevoir les nouveautés produit et guides MILSIM</h2>
            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-600">Inscription avec confirmation e-mail (double opt-in). Chaque envoi inclut un lien de désabonnement immédiat.</p>

            <?php if ($newsletterStatus !== ''): ?>
                <?php
                $newsletterMessages = [
                    'confirm_sent' => ['ok' => true, 'text' => 'Vérifiez votre boîte e-mail pour confirmer votre inscription.'],
                    'confirmed' => ['ok' => true, 'text' => 'Inscription confirmée. Bienvenue dans la newsletter Athena.'],
                    'unsubscribed' => ['ok' => true, 'text' => 'Vous êtes désinscrit·e de la newsletter.'],
                    'invalid_email' => ['ok' => false, 'text' => 'Adresse e-mail invalide.'],
                    'csrf' => ['ok' => false, 'text' => 'Session expirée, veuillez réessayer.'],
                    'confirm_invalid' => ['ok' => false, 'text' => 'Lien de confirmation invalide ou expiré.'],
                    'unsubscribe_invalid' => ['ok' => false, 'text' => 'Lien de désabonnement invalide.'],
                    'schema_missing' => ['ok' => false, 'text' => 'Module newsletter indisponible (migration manquante).'],
                ];
                $current = $newsletterMessages[$newsletterStatus] ?? null;
                ?>
                <?php if ($current): ?>
                    <p class="mt-5 rounded-xl border px-4 py-3 text-sm <?= $current['ok'] ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' ?>">
                        <?= htmlspecialchars($current['text']) ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" action="<?= url('newsletter/subscribe') ?>" class="mt-6 flex flex-col gap-3 sm:flex-row">
                <?= \App\Core\Csrf::field() ?>
                <label for="newsletter-email" class="sr-only">Adresse e-mail</label>
                <input id="newsletter-email" name="email" type="email" required maxlength="255" placeholder="votre@email.com"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-6 py-3 text-[11px] font-black uppercase tracking-wider text-white transition hover:bg-slate-800">S’abonner</button>
            </form>
        </div>
    </section>

    <footer class="relative mt-0">
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-emerald-950/95 to-slate-900 text-white">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(52,211,153,0.22),transparent_50%)]"></div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.06]" style="background-image:linear-gradient(rgba(255,255,255,0.07) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.07) 1px, transparent 1px); background-size: 64px 64px;"></div>
            <div class="relative mx-auto max-w-4xl px-6 py-16 text-center md:py-20 lg:py-24">
                <p class="mb-4 text-[10px] font-black uppercase tracking-[0.4em] text-emerald-400/90">Athena Compsec</p>
                <h2 class="mb-5 text-3xl font-black leading-tight tracking-tight text-white md:text-4xl lg:text-5xl">
                    Donnez à votre unité un portail digne de vos opérations
                </h2>
                <p class="mx-auto mb-10 max-w-2xl text-base leading-relaxed text-white/65 md:text-lg">
                    Créez votre espace, invitez vos membres et centralisez présence, documents, formation et cartographie — sans multiplier les outils.
                </p>
                <div class="flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                    <?php if (!$loggedIn): ?>
                    <a href="<?= url('register') ?>" class="inline-flex min-h-[3rem] items-center justify-center rounded-2xl bg-emerald-500 px-8 text-xs font-black uppercase tracking-wider text-slate-950 shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-400">
                        Créer un compte gratuitement
                    </a>
                    <a href="<?= url('login') ?>" class="inline-flex min-h-[3rem] items-center justify-center rounded-2xl border border-white/20 bg-white/5 px-8 text-xs font-black uppercase tracking-wider text-white backdrop-blur-sm transition hover:border-white/35 hover:bg-white/10">
                        J’ai déjà un compte
                    </a>
                    <a href="<?= url('join') ?>" class="inline-flex min-h-[3rem] items-center justify-center rounded-2xl px-6 text-xs font-bold uppercase tracking-wide text-emerald-200/90 underline decoration-emerald-500/40 underline-offset-4 transition hover:text-white">
                        Rejoindre avec un code communauté
                    </a>
                    <?php else: ?>
                    <a href="<?= url('dashboard') ?>" class="inline-flex min-h-[3rem] items-center justify-center rounded-2xl bg-emerald-500 px-8 text-xs font-black uppercase tracking-wider text-slate-950 shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-400">
                        Retour au tableau de bord
                    </a>
                    <a href="<?= url('communities') ?>" class="inline-flex min-h-[3rem] items-center justify-center rounded-2xl border border-white/20 bg-white/5 px-8 text-xs font-black uppercase tracking-wider text-white backdrop-blur-sm transition hover:border-white/35 hover:bg-white/10">
                        Voir les communautés
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="border-t border-white/10 bg-slate-950 py-8">
            <div class="mx-auto flex max-w-6xl flex-col items-center gap-8 px-6 md:flex-row md:items-start md:justify-between md:gap-12">
                <div class="text-center md:text-left">
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-white">Athena Compsec</p>
                    <p class="mt-2 max-w-xs text-xs leading-relaxed text-slate-500">Portail communautaire pour unités MILSIM Arma 3 — organisation, présence et formation.</p>
                </div>
                <nav class="flex max-w-xl flex-wrap items-center justify-center gap-x-5 gap-y-2 text-center text-xs md:justify-end" aria-label="Informations légales">
                    <?php
                    $legal_link_class = 'text-slate-400 transition-colors hover:text-emerald-400 font-medium';
                    require base_path('views/partials/legal_site_links.php');
                    ?>
                </nav>
            </div>
        </div>
    </footer>
    <?php require base_path('views/partials/cookie_banner.php'); ?>

    <script>
        function toggleMenu() {
            document.body.classList.toggle('drawer-open');
            if (document.body.classList.contains('drawer-open')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
    </script>
</body>
</html>
