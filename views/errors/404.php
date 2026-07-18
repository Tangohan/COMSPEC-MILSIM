<?php
declare(strict_types=1);

$base = function_exists('url') ? url('') : '/';
$loggedIn = (bool) (\App\Core\Session::get('user_id') ?? false);
$heroImg = $base . '/assets/images/fog-team.jpg';
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page introuvable — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="<?= htmlspecialchars($base) ?>/assets/css/styles.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .tactical-grid-dark {
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 44px 44px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 selection:bg-slate-900 selection:text-white overflow-x-hidden">

    <div class="grain"></div>

    <div id="bodyOverlay" class="overlay fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[110]" onclick="toggleMenu()"></div>

    <div id="navDrawer" class="drawer-translate fixed top-0 left-0 w-[300px] h-full bg-slate-50 z-[120] shadow-2xl p-6 flex flex-col">
        <div class="flex justify-between items-center mb-10">
            <span class="text-[10px] font-black tracking-[0.3em] uppercase opacity-50">Menu</span>
            <button type="button" onclick="toggleMenu()" class="hover:rotate-90 transition-transform" aria-label="Fermer le menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <nav class="flex flex-col gap-5" aria-label="Navigation">
            <a href="<?= htmlspecialchars($base) ?>/" class="text-xs font-bold tracking-[0.2em] uppercase">Accueil</a>
            <?php if ($loggedIn): ?>
            <a href="<?= htmlspecialchars(url('dashboard')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Dashboard</a>
            <a href="<?= htmlspecialchars(url('hub')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Hub</a>
            <a href="<?= htmlspecialchars(url('pointage')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase text-emerald-800">Pointage</a>
            <a href="<?= htmlspecialchars(url('communities')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Communautés</a>
            <a href="<?= htmlspecialchars(url('forum')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Forum</a>
            <a href="<?= htmlspecialchars(url('evenements')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Événements</a>
            <a href="<?= htmlspecialchars(url('orbat')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Orbat</a>
            <a href="<?= htmlspecialchars(url('atak')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Atak</a>
            <a href="<?= htmlspecialchars(url('documents')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Documents</a>
            <a href="<?= htmlspecialchars(url('formations')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Formations</a>
            <a href="<?= htmlspecialchars(url('modpacks')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Modpacks</a>
            <a href="<?= htmlspecialchars(url('account')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Compte</a>
            <?php else: ?>
            <a href="<?= htmlspecialchars(url('login')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Connexion</a>
            <a href="<?= htmlspecialchars(url('register')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase">Inscription</a>
            <a href="<?= htmlspecialchars(url('join')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase text-slate-500">Rejoindre par code</a>
            <?php endif; ?>
        </nav>

        <div class="mt-auto pt-10 border-t border-slate-100">
            <div class="flex flex-col gap-4 mb-8">
                <?php if ($loggedIn): ?>
                <form method="post" action="<?= htmlspecialchars(url('logout')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="text-xs font-bold tracking-[0.2em] uppercase text-slate-400 hover:text-slate-900 text-left">Déconnexion</button>
                </form>
                <?php else: ?>
                <a href="<?= htmlspecialchars(url('login')) ?>" class="text-xs font-bold tracking-[0.2em] uppercase text-slate-400 hover:text-slate-900">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <header class="sticky top-0 z-[100] w-full bg-slate-50/95 backdrop-blur-md border-b border-slate-900/[0.03]">
        <div class="max-w-5xl mx-auto px-6 sm:px-8 h-16 flex items-center justify-between relative text-slate-900 uppercase">
            <div class="flex-1">
                <button type="button" onclick="toggleMenu()" class="group flex flex-col gap-2 outline-none w-6 h-6 justify-center" aria-label="Ouvrir le menu">
                    <span class="h-[1px] w-full bg-slate-900 transition-all duration-500 group-hover:translate-x-1"></span>
                    <div class="flex justify-end">
                        <span class="h-[1px] w-3 bg-slate-900 transition-all duration-500 group-hover:w-full group-hover:translate-x-0"></span>
                    </div>
                </button>
            </div>

            <div class="absolute left-1/2 -translate-x-1/2 flex flex-col items-center text-center">
                <a href="<?= htmlspecialchars($base) ?>/" class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-900 sm:tracking-[0.26em]">
                    Athena Compsec
                </a>
                <span class="mt-0.5 text-[6px] font-semibold uppercase tracking-[0.32em] text-slate-400">Portail MILSIM</span>
            </div>

            <div class="flex flex-1 justify-end items-center gap-4 sm:gap-6">
                <div class="hidden sm:flex flex-col items-end leading-none">
                    <span class="text-[7px] font-black tracking-[0.28em] text-slate-400">Horloge</span>
                    <span id="err404-clock" class="text-[10px] font-mono font-semibold tracking-wide text-slate-700 tabular-nums">--:--:--</span>
                </div>
                <div class="flex items-center gap-2 border-l border-slate-200 pl-4 sm:pl-6">
                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(245,158,11,0.5)]"></span>
                    <span class="text-[8px] font-black tracking-[0.2em] text-slate-400">Erreur active</span>
                </div>
            </div>
        </div>
    </header>

    <main>
        <section class="relative min-h-[78vh] w-full overflow-hidden bg-black tactical-grid-dark">
            <div class="absolute inset-0">
                <img src="<?= htmlspecialchars($heroImg) ?>" class="w-full h-full object-cover grayscale brightness-[0.2] scale-105" width="1920" height="1080" alt="" decoding="async">
            </div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/60 to-[#050810]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.08),transparent_35%)]"></div>

            <div class="relative z-10 flex flex-col justify-between min-h-[78vh] p-8 md:p-14 lg:p-16">
                <div class="flex justify-between items-start gap-8">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-red-600 rounded-full animate-pulse" aria-hidden="true"></span>
                            <span class="text-[9px] font-black tracking-[0.4em] text-white uppercase">Alerte routage</span>
                        </div>
                        <span class="text-[7px] font-bold text-white/30 tracking-[0.3em] uppercase max-w-md">Ressource non trouvée — aucune action sur votre session</span>
                    </div>
                    <div class="text-right flex flex-col items-end">
                        <span id="err404-timestamp" class="text-[9px] font-mono text-white/40 tracking-widest uppercase mb-1"></span>
                        <span class="text-[7px] font-bold text-white/20 tracking-[0.5em] uppercase">Secteur portail</span>
                    </div>
                </div>

                <div class="max-w-3xl mt-16 md:mt-0">
                    <p class="text-[9px] font-black tracking-[0.45em] text-emerald-500 uppercase mb-4">Erreur système</p>
                    <h1 class="text-white text-4xl sm:text-5xl md:text-7xl lg:text-8xl font-black tracking-tighter leading-[0.95] mb-6">
                        404<br>
                        <span class="text-white/90">Page introuvable</span>
                    </h1>
                    <div class="h-[1px] w-24 bg-white/20 mb-6"></div>
                    <p class="text-white/50 text-[11px] sm:text-xs font-bold tracking-[0.25em] uppercase leading-relaxed max-w-xl">
                        L’adresse est peut-être incorrecte, la page a été déplacée ou vous n’avez pas accès à ce contenu avec votre profil actuel.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars($base) ?>/" class="inline-flex items-center justify-center px-5 py-3 rounded-2xl bg-white text-slate-950 text-[10px] font-black uppercase tracking-[0.22em] hover:bg-emerald-50 transition-colors">
                            Retour accueil
                        </a>
                        <?php if ($loggedIn): ?>
                        <a href="<?= htmlspecialchars(url('dashboard')) ?>" class="inline-flex items-center justify-center px-5 py-3 rounded-2xl border border-white/15 bg-white/[0.04] text-white text-[10px] font-black uppercase tracking-[0.22em] hover:bg-white/[0.08] transition-colors">
                            Tableau de bord
                        </a>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars(url('login')) ?>" class="inline-flex items-center justify-center px-5 py-3 rounded-2xl border border-white/15 bg-white/[0.04] text-white text-[10px] font-black uppercase tracking-[0.22em] hover:bg-white/[0.08] transition-colors">
                            Connexion
                        </a>
                        <?php endif; ?>
                        <button type="button" onclick="history.back()" class="inline-flex items-center justify-center px-5 py-3 rounded-2xl border border-white/10 text-white/80 text-[10px] font-black uppercase tracking-[0.22em] hover:bg-white/[0.06] transition-colors">
                            Retour arrière
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <nav class="w-full bg-[#050810] py-8 px-6 border-t border-white/[0.06]" aria-label="Liens utiles">
            <div class="max-w-5xl mx-auto flex flex-col sm:flex-row flex-wrap items-center justify-center gap-4 sm:gap-8 text-[10px] font-bold uppercase tracking-[0.2em]">
                <a href="<?= htmlspecialchars(url('formations')) ?>" class="text-white/50 hover:text-white transition-colors">Formations</a>
                <a href="<?= htmlspecialchars(url('login')) ?>" class="text-white/50 hover:text-white transition-colors">Connexion</a>
                <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#rgpd" class="text-white/50 hover:text-white transition-colors">Données personnelles</a>
                <button type="button" data-cookie-preferences class="text-white/50 hover:text-white transition-colors bg-transparent border-0 cursor-pointer font-bold uppercase tracking-[0.2em] text-[10px]">
                    Préférences cookies
                </button>
            </div>
        </nav>
    </main>

    <?php if (function_exists('base_path') && is_file(base_path('views/partials/cookie_banner.php'))): ?>
        <?php require base_path('views/partials/cookie_banner.php'); ?>
    <?php endif; ?>

    <script>
        function toggleMenu() {
            document.body.classList.toggle('drawer-open');
            document.body.style.overflow = document.body.classList.contains('drawer-open') ? 'hidden' : '';
        }
        (function () {
            function pad(n) { return n < 10 ? '0' + n : '' + n; }
            function tickClock() {
                var el = document.getElementById('err404-clock');
                if (!el) return;
                var d = new Date();
                el.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
            }
            function tickTs() {
                var el = document.getElementById('err404-timestamp');
                if (!el) return;
                el.textContent = new Date().toLocaleString('fr-FR', { hour12: false });
            }
            tickClock();
            tickTs();
            setInterval(tickClock, 1000);
            setInterval(tickTs, 1000);
        })();
    </script>
</body>
</html>
