<?php
$base = url('');
$title = $title ?? 'Athena — Commandement Aérien MILSIM';
$loggedIn = (bool) \App\Core\Session::get('user_id');
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-900 selection:bg-slate-900 selection:text-white overflow-x-hidden">

    <div class="grain"></div>

    <div id="bodyOverlay" class="overlay fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[110]" onclick="toggleMenu()"></div>

    <div id="navDrawer" class="drawer-translate fixed top-0 left-0 w-[300px] h-full bg-slate-50 z-[120] shadow-2xl p-6 flex flex-col">
        <div class="flex justify-between items-center mb-10">
            <span class="text-[10px] font-black tracking-[0.3em] uppercase opacity-50">Menu</span>
            <button onclick="toggleMenu()" class="hover:rotate-90 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <nav class="flex flex-col gap-5">
            <a href="<?= $base ?>/" class="text-xs font-bold tracking-[0.2em] uppercase">ACCUEIL</a>
            <?php if ($loggedIn): ?>
            <a href="<?= url('dashboard') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">DASHBOARD</a>
            <a href="<?= url('hub') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">HUB</a>
            <a href="<?= url('pointage') ?>" class="text-xs font-bold tracking-[0.2em] uppercase text-emerald-800">POINTAGE</a>
            <a href="<?= url('communities') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">COMMUNAUTÉS</a>
            <a href="<?= url('forum') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">FORUM</a>
            <a href="<?= url('orbat') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">ORBAT</a>
            <a href="<?= url('atak') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">ATAK</a>
            <a href="<?= url('documents') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">DOCUMENTS</a>
            <a href="<?= url('formations') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">FORMATIONS</a>
            <a href="<?= url('modpacks') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">MODPACKS</a>
            <a href="<?= url('account') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">COMPTE</a>
            <?php else: ?>
            <a href="<?= url('login') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">CONNEXION</a>
            <a href="<?= url('register') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">INSCRIPTION</a>
            <a href="<?= url('join') ?>" class="text-xs font-bold tracking-[0.2em] uppercase text-slate-500">REJOINDRE PAR CODE</a>
            <?php endif; ?>
        </nav>

        <div class="mt-auto pt-10 border-t border-slate-100">
            <div class="flex flex-col gap-4 mb-8">
                <?php if (!$loggedIn): ?>
                <a href="<?= url('login') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Connexion</a>
                <a href="<?= url('register') ?>" class="text-xs font-bold tracking-[0.2em] uppercase text-slate-400">Créer un compte</a>
                <?php else: ?>
                <form method="post" action="<?= url('logout') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="text-xs font-bold tracking-[0.2em] uppercase text-slate-400 hover:text-slate-900">Déconnexion</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center">
                    <span class="text-[10px] font-bold">IG</span>
                </div>
                <div class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center">
                    <span class="text-[10px] font-bold">YT</span>
                </div>
            </div>
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

            <div class="absolute left-1/2 -translate-x-1/2 flex flex-col items-center">
                <a href="<?= $base ?>/" class="text-[11px] font-black tracking-[0.35em] -mr-[0.35em]">
                    ATHENA
                </a>
                <div class="flex items-center gap-2 mt-1">
                    <span class="h-[1px] w-4 bg-slate-200"></span>
                    <span class="text-[6px] font-black tracking-[0.35em] text-slate-400">PORTAIL MILSIM</span>
                    <span class="h-[1px] w-4 bg-slate-200"></span>
                </div>
            </div>

            <div class="flex-1 flex justify-end items-center gap-10">
                <div class="hidden lg:flex flex-col items-end leading-none font-black">
                    <span class="text-[7px] tracking-[0.3em] text-slate-400 mb-1">LAT / LONG</span>
                    <span class="text-[9px] tracking-widest italic">38.89°N 77.03°W</span>
                </div>
                
                <div class="flex items-center gap-3 border-l border-slate-200 pl-8 h-4">
                    <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                    <span class="text-[8px] font-black tracking-[0.2em] text-slate-400">RÉSEAU ACTIF</span>
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
                <div class="flex justify-between items-start">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-red-600 rounded-full animate-pulse"></span>
                            <span class="text-[9px] font-black tracking-[0.4em] text-white uppercase">Mode REC : Actif</span>
                        </div>
                        <span class="text-[7px] font-bold text-white/30 tracking-[0.3em] uppercase">Autorisation : Niveau_04 // Chiffré</span>
                    </div>
                    <div class="text-right flex flex-col items-end">
                        <span id="timestamp" class="text-[9px] font-mono text-white/40 tracking-widest uppercase mb-1"></span>
                        <span class="text-[7px] font-bold text-white/20 tracking-[0.5em] uppercase">Secteur 7-G</span>
                    </div>
                </div>

                <div class="max-w-2xl">
                    <h1 class="text-white text-5xl md:text-8xl font-black tracking-tighter leading-none mb-6">
                        J.T.A.C <br> OPERATEURS
                    </h1>
                    <div class="h-[1px] w-24 bg-white/20 mb-6"></div>
                    <p class="text-white/40 text-[10px] font-bold tracking-[0.3em] uppercase leading-relaxed max-w-sm">
                        Les JTAC (Joint Terminal Attack Controllers) sont des opérateurs spécialisés, formés pour coordonner les frappes aériennes.
                    </p>
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

            setInterval(() => {
                const now = new Date();
                document.getElementById('timestamp').innerText =
                    now.getHours().toString().padStart(2, '0') + ':' +
                    now.getMinutes().toString().padStart(2, '0') + ':' +
                    now.getSeconds().toString().padStart(2, '0') + ' Z';
            }, 1000);

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

                    <a href="<?= url('documents') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">Docs</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">DOCUMENTS</span>
                    </a>

                    <a href="<?= url('formations') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center">
                        <span class="text-[7px] font-black tracking-[0.25em] text-slate-500 uppercase group-hover:text-emerald-500 transition-colors">LMS</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">FORMATIONS</span>
                    </a>

                    <a href="<?= url('enlistment') ?>" class="group flex flex-col items-center gap-1 max-w-[140px] text-center relative px-2">
                        <span class="text-[7px] font-black tracking-[0.25em] text-emerald-500 uppercase">RH</span>
                        <span class="text-white text-[10px] font-bold tracking-[0.15em] uppercase transition-all group-hover:text-emerald-400">RECRUTEMENT</span>
                    </a>
                </div>

                <div class="mt-8 flex justify-between items-center border-t border-white/[0.03] pt-4">
                    <span class="text-[6px] font-mono text-slate-600 tracking-[0.5em] uppercase text-left">Système : V.2.0.4 // COMSPEC</span>
                    <div class="h-[1px] flex-1 mx-8 bg-gradient-to-r from-transparent via-white/5 to-transparent"></div>
                    <span class="text-[6px] font-mono text-slate-600 tracking-[0.5em] uppercase text-right italic">En attente d’ordres...</span>
                </div>
            </div>
        </nav>

        <section class="who-we-are">
            <div class="who-inner">

                <div class="who-title">
                    <h2>Qui sommes-nous</h2>
                </div>

                <div class="who-icons">
                    <div class="who-item">
                        <img src="<?= $base ?>/assets/images/index3.png" alt="75th Ranger Regiment">
                        <h4>75th Ranger Regiment</h4>
                        <span>Army Rangers</span>
                    </div>

                    <div class="who-item">
                        <img src="<?= $base ?>/assets/images/index2.png" alt="AFSOC">
                        <h4>AFSOC</h4>
                        <span>Global Access</span>
                    </div>

                    <div class="who-item">
                        <img src="<?= $base ?>/assets/images/index1.png" alt="USASOC">
                        <h4>USASOC</h4>
                        <span>National Mission Force</span>
                    </div>

                    <div class="who-item">
                        <img src="<?= $base ?>/assets/images/index5.png" alt="JSOAC">
                        <h4>JSOAC</h4>
                        <span>Aviation</span>
                    </div>

                    <div class="who-item">
                        <img src="<?= $base ?>/assets/images/index4.png" alt="CIA">
                        <h4>CIA</h4>
                        <span>Intelligence Experts</span>
                    </div>
                </div>
            </div>
        </section>
        <section class="relative bg-[#050810] text-white overflow-hidden border-y border-white/5">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.08),transparent_40%)]"></div>
    <div class="absolute inset-0 opacity-[0.035] pointer-events-none" style="background-image:linear-gradient(rgba(255,255,255,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.06) 1px, transparent 1px); background-size: 48px 48px;"></div>

    <div class="relative max-w-6xl mx-auto px-6 py-20">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8 mb-14">
            <div class="max-w-2xl">
                <p class="text-[9px] font-black tracking-[0.45em] text-emerald-500 uppercase mb-4">Situation actuelle</p>
                <h2 class="text-3xl md:text-5xl font-black tracking-tight uppercase leading-none mb-5">
                    Opérations déployées à travers différents secteurs
                </h2>
                <div class="h-[1px] w-20 bg-white/15 mb-5"></div>
                <p class="text-white/45 text-[11px] font-bold tracking-[0.18em] uppercase leading-relaxed max-w-xl">
                    Visualisez la disponibilité réelle des unités, l’état de préparation et la coordination entre les équipes suivant la mission en cours.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 min-w-[280px]">
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4">
                    <p class="text-[8px] font-black tracking-[0.28em] text-white/30 uppercase mb-2">Unités actives</p>
                    <p class="text-2xl font-black tracking-tight">04</p>
                </div>
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4">
                    <p class="text-[8px] font-black tracking-[0.28em] text-white/30 uppercase mb-2">Préparation</p>
                    <p class="text-2xl font-black tracking-tight text-emerald-400">Optimale</p>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <article class="group relative bg-white/[0.03] border border-white/[0.06] rounded-3xl p-6 hover:border-emerald-500/30 transition-colors overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-500/70 via-emerald-500/20 to-transparent"></div>

                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <p class="text-[8px] font-black tracking-[0.35em] text-white/30 uppercase mb-2">Zone 01</p>
                        <h3 class="text-xl font-black tracking-tight uppercase">Secteur Est</h3>
                    </div>
                    <span class="inline-flex items-center gap-2 text-[9px] font-black tracking-[0.2em] uppercase text-emerald-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Opérationnel
                    </span>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Mission</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Reconnaissance</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Appui aérien</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Prêt</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Fenêtre</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">2200Z–0100Z</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Équipe</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Aviation / Recon</span>
                    </div>
                </div>
            </article>

            <article class="group relative bg-white/[0.03] border border-white/[0.06] rounded-3xl p-6 hover:border-amber-400/30 transition-colors overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-amber-400/70 via-amber-400/20 to-transparent"></div>

                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <p class="text-[8px] font-black tracking-[0.35em] text-white/30 uppercase mb-2">Zone 02</p>
                        <h3 class="text-xl font-black tracking-tight uppercase">Secteur Sud</h3>
                    </div>
                    <span class="inline-flex items-center gap-2 text-[9px] font-black tracking-[0.2em] uppercase text-amber-300">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        Prêt Alerte
                    </span>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Mission</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Assaut</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">QRF</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Déploiement 30min</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Mobilité aérienne</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">En attente</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Équipe</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Unité d’assaut</span>
                    </div>
                </div>
            </article>

            <article class="group relative bg-white/[0.03] border border-white/[0.06] rounded-3xl p-6 hover:border-sky-400/30 transition-colors overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-sky-400/70 via-sky-400/20 to-transparent"></div>

                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <p class="text-[8px] font-black tracking-[0.35em] text-white/30 uppercase mb-2">Théâtre 03</p>
                        <h3 class="text-xl font-black tracking-tight uppercase">Quadrant Nord</h3>
                    </div>
                    <span class="inline-flex items-center gap-2 text-[9px] font-black tracking-[0.2em] uppercase text-sky-300">
                        <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                        Sous surveillance
                    </span>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Mission principale</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Signaux / Renseignement</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Mode de collecte</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Passif</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/[0.06] pb-3">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">État réseau</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Stable</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-white/35">Cellule assignée</span>
                        <span class="text-[10px] font-black tracking-[0.14em] uppercase text-white">Cellule renseignement</span>
                    </div>
                </div>
            </article>
        </div>
    </div>
</section>
<section class="relative bg-white text-slate-900 overflow-hidden border-y border-slate-900/[0.04]">
    <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image:linear-gradient(rgba(15,23,42,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(15,23,42,0.08) 1px, transparent 1px); background-size: 56px 56px;"></div>

    <div class="relative max-w-6xl mx-auto px-6 py-20">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8 mb-14">
            <div class="max-w-2xl">
                <p class="text-[9px] font-black tracking-[0.45em] text-emerald-600 uppercase mb-4">Capacités</p>
                <h2 class="text-3xl md:text-5xl font-black tracking-tight uppercase leading-none mb-5">
                    Missions conçues pour<br>la précision et la continuité
                </h2>
                <div class="h-[1px] w-20 bg-slate-900/10 mb-5"></div>
                <p class="text-slate-500 text-[11px] font-bold tracking-[0.18em] uppercase leading-relaxed max-w-xl">
                    Reconnaissance intégrée, appui aux frappes, soutien aérien et planification guidée par le renseignement en environnement contesté.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 min-w-[280px]">
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
                    <p class="text-[8px] font-black tracking-[0.28em] text-slate-400 uppercase mb-2">Fonctions clés</p>
                    <p class="text-2xl font-black tracking-tight">06</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
                    <p class="text-[8px] font-black tracking-[0.28em] text-slate-400 uppercase mb-2">Posture opérationnelle</p>
                    <p class="text-2xl font-black tracking-tight text-emerald-600">Intégrée</p>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-6">
            <article class="group bg-white border border-slate-200 rounded-3xl p-6 hover:border-emerald-500/30 hover:shadow-[0_20px_50px_rgba(15,23,42,0.06)] transition-all">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 11l9-7 9 7M5 10v9h14v-9"></path>
                        </svg>
                    </div>
                    <span class="text-[8px] font-black tracking-[0.25em] text-slate-400 uppercase">01</span>
                </div>

                <h3 class="text-lg font-black tracking-tight uppercase mb-3">Reconnaissance</h3>
                <p class="text-[11px] text-slate-600 leading-relaxed font-medium mb-5">
                    Ouverture d’itinéraires, familiarisation de zone, lecture du terrain et observation des objectifs avant engagement de la force principale.
                </p>

                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between text-[10px] uppercase tracking-[0.14em] font-bold">
                        <span class="text-slate-400">Préparation</span>
                        <span class="text-slate-900">Élevée</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full w-[88%] bg-emerald-500 rounded-full"></div>
                    </div>
                </div>
            </article>

            <article class="group bg-white border border-slate-200 rounded-3xl p-6 hover:border-emerald-500/30 hover:shadow-[0_20px_50px_rgba(15,23,42,0.06)] transition-all">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.868v4.264a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"></path>
                        </svg>
                    </div>
                    <span class="text-[8px] font-black tracking-[0.25em] text-slate-400 uppercase">02</span>
                </div>

                <h3 class="text-lg font-black tracking-tight uppercase mb-3">Action directe</h3>
                <p class="text-[11px] text-slate-600 leading-relaxed font-medium mb-5">
                    Actions offensives de courte durée axées sur la capture, la perturbation ou la neutralisation d’objectifs de haute valeur.
                </p>

                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between text-[10px] uppercase tracking-[0.14em] font-bold">
                        <span class="text-slate-400">Préparation</span>
                        <span class="text-slate-900">Qualifiée</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full w-[82%] bg-emerald-500 rounded-full"></div>
                    </div>
                </div>
            </article>

            <article class="group bg-white border border-slate-200 rounded-3xl p-6 hover:border-emerald-500/30 hover:shadow-[0_20px_50px_rgba(15,23,42,0.06)] transition-all">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 14l11-2-9-9-2 11zm0 0l-2 7-2-5-5-2 9-2z"></path>
                        </svg>
                    </div>
                    <span class="text-[8px] font-black tracking-[0.25em] text-slate-400 uppercase">03</span>
                </div>

                <h3 class="text-lg font-black tracking-tight uppercase mb-3">Mobilité aérienne</h3>
                <p class="text-[11px] text-slate-600 leading-relaxed font-medium mb-5">
                    Insertion, extraction et redéploiement rapide par voilure tournante dans des fenêtres opérationnelles contraintes.
                </p>

                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between text-[10px] uppercase tracking-[0.14em] font-bold">
                        <span class="text-slate-400">Préparation</span>
                        <span class="text-slate-900">Disponible</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full w-[85%] bg-emerald-500 rounded-full"></div>
                    </div>
                </div>
            </article>

            <article class="group bg-white border border-slate-200 rounded-3xl p-6 hover:border-emerald-500/30 hover:shadow-[0_20px_50px_rgba(15,23,42,0.06)] transition-all">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-6m3 6V7m3 10v-3m3 3V5M5 19h14"></path>
                        </svg>
                    </div>
                    <span class="text-[8px] font-black tracking-[0.25em] text-slate-400 uppercase">04</span>
                </div>

                <h3 class="text-lg font-black tracking-tight uppercase mb-3">Intégration ISR</h3>
                <p class="text-[11px] text-slate-600 leading-relaxed font-medium mb-5">
                    Collecte, fusion et diffusion d’un renseignement exploitable pour appuyer la décision en temps réel.
                </p>

                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between text-[10px] uppercase tracking-[0.14em] font-bold">
                        <span class="text-slate-400">Préparation</span>
                        <span class="text-slate-900">Stable</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full w-[91%] bg-emerald-500 rounded-full"></div>
                    </div>
                </div>
            </article>

            <article class="group bg-white border border-slate-200 rounded-3xl p-6 hover:border-emerald-500/30 hover:shadow-[0_20px_50px_rgba(15,23,42,0.06)] transition-all">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v12m6-6H6"></path>
                        </svg>
                    </div>
                    <span class="text-[8px] font-black tracking-[0.25em] text-slate-400 uppercase">05</span>
                </div>

                <h3 class="text-lg font-black tracking-tight uppercase mb-3">Soutien médical</h3>
                <p class="text-[11px] text-slate-600 leading-relaxed font-medium mb-5">
                    Stabilisation des blessés, appui à l’extraction et soins prolongés sur le terrain lors d’opérations étendues.
                </p>

                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between text-[10px] uppercase tracking-[0.14em] font-bold">
                        <span class="text-slate-400">Préparation</span>
                        <span class="text-slate-900">Prête</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full w-[78%] bg-emerald-500 rounded-full"></div>
                    </div>
                </div>
            </article>

            <article class="group bg-white border border-slate-200 rounded-3xl p-6 hover:border-emerald-500/30 hover:shadow-[0_20px_50px_rgba(15,23,42,0.06)] transition-all">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 9h8M8 13h6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H9l-4 4v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span class="text-[8px] font-black tracking-[0.25em] text-slate-400 uppercase">06</span>
                </div>

                <h3 class="text-lg font-black tracking-tight uppercase mb-3">Planification de mission</h3>
                <p class="text-[11px] text-slate-600 leading-relaxed font-medium mb-5">
                    Cycles de planification structurés, organisation des tâches et supports de briefing conçus pour une exécution reproductible.
                </p>

                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between text-[10px] uppercase tracking-[0.14em] font-bold">
                        <span class="text-slate-400">Préparation</span>
                        <span class="text-slate-900">Constante</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full w-[89%] bg-emerald-500 rounded-full"></div>
                    </div>
                </div>
            </article>
        </div>
    </div>
</section>
    </main>

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