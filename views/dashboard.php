<?php $base = url(''); $title = $title ?? 'Dashboard — Athena'; ?>
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
    <div id="bodyOverlay" class="overlay fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[110]" onclick="toggleMenu()"></div>

    <div id="navDrawer" class="drawer-translate fixed top-0 left-0 w-[300px] h-full bg-slate-50 z-[120] shadow-2xl p-6 flex flex-col">
        <div class="flex justify-between items-center mb-10">
            <span class="text-[10px] font-black tracking-[0.3em] uppercase opacity-50">Menu</span>
            <button onclick="toggleMenu()" class="hover:rotate-90 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <nav class="flex flex-col gap-6">
            <a href="<?= $base ?>/" class="text-xs font-bold tracking-[0.2em] uppercase">ACCUEIL</a>
            <a href="<?= url('dashboard') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">DASHBOARD</a>
            <a href="<?= url('personnel/me') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">MA FICHE</a>
            <a href="<?= url('atak') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">ATAK / TACMAP</a>
            <a href="<?= url('orbat') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">ORBAT / UNITÉ</a>
            <a href="<?= url('documents') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">DOCUMENTS</a>
            <a href="<?= url('modpacks') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">MODPACKS</a>
            <a href="<?= url('formations') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">FORMATIONS</a>
            <a href="<?= url('equipement') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">ÉQUIPEMENT</a>
            <a href="<?= url('account') ?>" class="text-xs font-bold tracking-[0.2em] uppercase">PARAMÈTRES</a>
            <?php if (\App\Core\Gate::getInstance()->allows('admin.access')): ?>
            <a href="<?= url('admin') ?>" class="text-xs font-bold tracking-[0.2em] uppercase text-slate-500">ADMIN</a>
            <?php endif; ?>
        </nav>

        <div class="mt-auto pt-10 border-t border-slate-100">
            <form method="post" action="<?= url('logout') ?>" class="mb-6">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="text-xs font-bold tracking-[0.2em] uppercase text-slate-500 hover:text-slate-900 transition-colors">Déconnexion</button>
            </form>
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
    <div class="w-full bg-slate-900 text-white/30 h-8 flex items-center border-b border-white/5 overflow-hidden select-none">
        <div class="max-w-5xl mx-auto px-8 w-full flex justify-between items-center font-mono text-[8px] tracking-[0.15em] uppercase">
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 group">
                    <span class="text-emerald-500 font-black tracking-[0.3em]">ZULU</span>
                    <span id="t-zulu" class="text-white text-[10px] font-medium tracking-normal w-[65px]">00:00:00</span>
                </div>
                
                <span class="text-white/10 text-[10px]">|</span>
                
                <div class="hidden sm:flex items-center gap-3 opacity-60 hover:opacity-100 transition-opacity">
                    <span class="label">PST</span>
                    <span id="t-pst" class="text-white/80 tracking-normal w-[65px]">00:00:00</span>
                    <span class="text-white/10">|</span>
                    <span class="label">MTN</span>
                    <span id="t-mtn" class="text-white/80 tracking-normal w-[65px]">00:00:00</span>
                    <span class="text-white/10">|</span>
                    <span class="label">EST</span>
                    <span id="t-est" class="text-white/80 tracking-normal w-[65px]">00:00:00</span>
                </div>
            </div>
            
            <div class="flex items-center gap-3 text-[7px] tracking-[0.25em]">
                <span class="flex h-1.5 w-1.5 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-20"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500/80"></span>
                </span>
                <span class="opacity-40 italic">ACTIF</span>
            </div>
        </div>
    </div>
    
    <header class="sticky top-0 z-[100] w-full bg-slate-50/95 backdrop-blur-md border-b border-slate-900/[0.03]">
        </header>
    
    <script>
        function formatClock(date, timeZone = 'UTC') {
            return new Intl.DateTimeFormat('en-GB', { // en-GB pour le format 24h naturel
                hour: '2-digit', minute: '2-digit', second: '2-digit',
                hour12: false,
                timeZone: timeZone
            }).format(date);
        }
    
        function updateOperationalClocks() {
            const now = new Date();
            
            // Mapping des IDs et fuseaux
            const zones = {
                't-zulu': 'UTC',
                't-pst': 'America/Los_Angeles',
                't-mtn': 'America/Denver',
                't-est': 'America/New_York',
                'clock-local': Intl.DateTimeFormat().resolvedOptions().timeZone
            };
    
            for (const [id, tz] of Object.entries(zones)) {
                const el = document.getElementById(id);
                if (el) el.textContent = formatClock(now, tz);
            }
        }
    
        setInterval(updateOperationalClocks, 1000);
        updateOperationalClocks();
    </script>
    
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
                <a href="<?= $base ?>/" class="text-[11px] font-black tracking-[0.7em] -mr-[0.7em] hover:text-emerald-600 transition-colors">
                    FORWARD
                </a>
                <div class="flex items-center gap-2 mt-1">
                    <span class="h-[1px] w-4 bg-slate-200"></span>
                    <span class="text-[6px] font-black tracking-[0.4em] text-slate-400">OBS. GROUP</span>
                    <span class="h-[1px] w-4 bg-slate-200"></span>
                </div>
            </div>
    
            <div class="flex-1 flex justify-end items-center gap-8">
                <div class="hidden lg:flex flex-col items-end leading-none font-black">
                    <span class="text-[7px] tracking-[0.3em] text-slate-400 mb-1">LOCAL_TIME</span>
                    <span id="clock-local" class="text-[10px] tracking-widest italic">00:00:00</span>
                </div>
                
                <div class="flex items-center gap-3 border-l border-slate-200 pl-8 h-4">
                    <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                    <span class="text-[8px] font-black tracking-[0.2em] text-slate-400">RÉSEAU ACTIF</span>
                </div>
            </div>
        </div>
    </header>
    
    
    <main class="min-h-screen bg-[#f8fafc] text-slate-900">
        <?php
        $communityMemberships = $communityMemberships ?? [];
        $currentTid = (int) (\App\Core\Session::get('tenant_id') ?? 0);
        ?>
        <?php if (count($communityMemberships) > 0): ?>
        <section class="border-b border-slate-200 bg-slate-100/90">
            <div class="max-w-5xl mx-auto px-8 py-3 flex flex-wrap items-center gap-3 text-[11px]">
                <span class="font-black uppercase tracking-widest text-slate-500">Communauté active</span>
                <?php foreach ($communityMemberships as $m): ?>
                    <?php if ((int) $m['tenant_id'] === $currentTid): ?>
                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-900 rounded-lg font-bold"><?= htmlspecialchars((string) $m['name']) ?></span>
                    <?php else: ?>
                        <form method="post" action="<?= url('community/switch') ?>" class="inline">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="tenant_id" value="<?= (int) $m['tenant_id'] ?>">
                            <button type="submit" class="text-slate-600 hover:text-emerald-700 underline font-semibold"><?= htmlspecialchars((string) $m['name']) ?></button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="<?= url('platform/invite-unit') ?>" class="text-slate-600 hover:text-emerald-700 font-semibold text-[11px]">Inviter une unité</a>
                <a href="<?= url('communities/create') ?>" class="ml-auto font-black uppercase tracking-wider text-emerald-700 hover:text-slate-900">+ Nouvelle communauté</a>
            </div>
        </section>
        <?php endif; ?>

        <?php
        $showFounderTrialBanner = $show_founder_trial_banner ?? false;
        $founderTrialEndsAt = $founder_trial_ends_at ?? null;
        ?>
        <?php if ($showFounderTrialBanner && is_string($founderTrialEndsAt) && $founderTrialEndsAt !== ''): ?>
        <section class="border-b border-amber-200 bg-amber-50">
            <div class="max-w-5xl mx-auto px-8 py-3 text-sm text-amber-950">
                <strong class="font-black uppercase tracking-wide">Fondateur</strong>
                — essai Pro (ATAK, événements, analytics) jusqu’au
                <?= htmlspecialchars(date('d/m/Y', strtotime($founderTrialEndsAt))) ?>.
                <a href="<?= url('platform/upgrade') ?>" class="underline font-semibold ml-2">Voir les offres</a>
            </div>
        </section>
        <?php endif; ?>

        <!-- HERO / HUB -->
        <section class="relative overflow-hidden border-b border-slate-200 bg-white">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(37,99,235,0.08),transparent_30%),radial-gradient(circle_at_bottom_left,rgba(15,23,42,0.05),transparent_35%)]"></div>
    
            <div class="relative max-w-7xl mx-auto px-6 md:px-10 py-10 md:py-14">
                <div class="grid grid-cols-1 xl:grid-cols-[1.25fr_0.75fr] gap-8 items-start">
    
                    <!-- Bloc principal -->
                    <div class="space-y-8">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-black uppercase tracking-[0.2em]">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Session sécurisée active
                            </span>
    
                            <span class="inline-flex items-center px-3 py-1.5 bg-slate-100 border border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-[0.2em]">
                                Niveau d'accès : Opérateur
                            </span>
                        </div>
    
                        <div class="max-w-4xl">
                            <p class="text-[11px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">
                                Centre de commandement
                            </p>
    
                            <h1 class="text-4xl md:text-6xl font-black uppercase italic tracking-[-0.04em] text-[#001529] leading-none">
                                Hub opérationnel
                            </h1>
    
                            <p class="mt-5 max-w-2xl text-sm md:text-base text-slate-600 leading-relaxed">
                                Point d’entrée centralisé pour les opérations, la formation, le suivi individuel, la documentation, les modules tactiques et les dossiers d’accréditation. 
                                L’interface doit permettre une lecture immédiate des priorités, des accès critiques et des activités récentes.
                            </p>
                        </div>
    
                        <!-- Actions critiques -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <a href="<?= url('atak') ?>" class="group flex items-center justify-between p-5 bg-white border border-slate-200 hover:shadow-xl hover:shadow-slate-100 transition-all">
                                <div class="space-y-1">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Tactique</span>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">ATAK / Tacmap</h3>
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </a>
                            <a href="<?= url('orbat') ?>" class="group flex items-center justify-between p-5 bg-white border border-slate-200 hover:shadow-xl hover:shadow-slate-100 transition-all">
                                <div class="space-y-1">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Organisation</span>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">ORBAT / Unité</h3>
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </a>
                            <a href="<?= url('formations') ?>" class="group flex items-center justify-between p-5 bg-white border border-slate-200 hover:shadow-xl hover:shadow-slate-100 transition-all">
                                <div class="space-y-1">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Instruction</span>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">Formation Continue</h3>
                                </div>
                                <span class="text-[10px] font-mono text-blue-600 font-bold bg-blue-50 px-2 py-1 italic">96%</span>
                            </a>
                            <a href="<?= url('documents') ?>" class="group flex items-center justify-between p-5 bg-white border border-slate-200 hover:shadow-xl hover:shadow-slate-100 transition-all">
                                <div class="space-y-1">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Data_Ref</span>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">Documentation</h3>
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
    
                    <!-- Bloc situation Modpack -->
                    <aside class="bg-slate-900 text-white p-6 md:p-7 border border-slate-800 shadow-2xl relative overflow-hidden group">
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-500">Infrastructure</p>
                                    <h2 class="mt-2 text-xl font-black uppercase italic tracking-tight text-white">État du Modpack</h2>
                                </div>
                                <div class="w-10 h-10 border border-white/10 flex items-center justify-center bg-white/5">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                                    </svg>
                                </div>
                            </div>
                            <?php
                            $modpack = $modpack ?? null;
                            if ($modpack):
                                $sizeFormatted = '—';
                                if (!empty($modpack['size'])) {
                                    $b = (int) $modpack['size'];
                                    $sizeFormatted = $b >= 1073741824 ? number_format($b / 1073741824, 1, ',', ' ') . ' Go' : ($b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ') . ' Mo' : number_format($b / 1024, 1, ',', ' ') . ' Ko');
                                }
                                $updatedAt = !empty($modpack['updated_at']) ? date('d.m.y', strtotime($modpack['updated_at'])) : '—';
                                $detailUrl = url('modpacks/' . htmlspecialchars($modpack['slug']));
                                $downloadUrl = url('modpacks/' . $modpack['id'] . '/download');
                            ?>
                            <div class="mt-8 space-y-4">
                                <div class="flex items-center justify-between p-3 border border-white/5 bg-white/[0.02]">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Version Actuelle</span>
                                        <span class="text-sm font-mono font-bold text-white"><?= htmlspecialchars($modpack['version'] ?? '—') ?></span>
                                    </div>
                                    <span class="px-2 py-1 bg-emerald-500/10 text-emerald-400 text-[8px] font-black rounded-sm border border-emerald-500/20">À JOUR</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="p-3 border border-white/5 bg-white/[0.02]">
                                        <span class="text-[9px] font-black text-white/30 uppercase tracking-widest block">Taille Totale</span>
                                        <span class="text-sm font-mono font-bold text-white"><?= $sizeFormatted ?></span>
                                    </div>
                                    <div class="p-3 border border-white/5 bg-white/[0.02]">
                                        <span class="text-[9px] font-black text-white/30 uppercase tracking-widest block">Update Last</span>
                                        <span class="text-sm font-mono font-bold text-white"><?= $updatedAt ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 text-[10px] tracking-wider text-white/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
                                    REPOS_A3_SYNC: NOMINAL
                                </div>
                            </div>
                            <div class="mt-8">
                                <a href="<?= $downloadUrl ?>" class="flex items-center justify-center gap-3 w-full py-4 bg-emerald-600 hover:bg-emerald-500 text-white transition-all duration-300 shadow-lg shadow-emerald-900/20 group/btn">
                                    <span class="text-[11px] font-black uppercase tracking-[0.3em]">Sync Modpack Arma 3</span>
                                    <svg class="w-4 h-4 transition-transform duration-500 group-hover/btn:translate-y-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                </a>
                                <p class="mt-3 text-center">
                                    <a href="<?= $detailUrl ?>" class="text-[8px] font-bold text-white/40 hover:text-white/70 uppercase tracking-[0.2em]">Voir la fiche modpack</a>
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="mt-8 space-y-4">
                                <p class="text-sm text-white/60">Aucun modpack configuré.</p>
                                <?php if (function_exists('can') && can('admin.access')): ?>
                                <a href="<?= url('admin/modpacks/create') ?>" class="inline-block px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold rounded border border-white/20">Créer un modpack</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </aside>

                    <!-- Mod COMSPEC ATAK -->
                    <?php $atakModDownloadUrl = $atakModDownloadUrl ?? null; if ($atakModDownloadUrl): ?>
                    <aside class="bg-slate-900 text-white p-6 md:p-7 border border-slate-800 shadow-2xl relative overflow-hidden group">
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-500">Tactique</p>
                                    <h2 class="mt-2 text-xl font-black uppercase italic tracking-tight text-white">Mod COMSPEC ATAK</h2>
                                </div>
                                <div class="w-10 h-10 border border-white/10 flex items-center justify-center bg-white/5">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-4 text-sm text-white/70">Téléchargez le mod ATAK COMSPEC Overwatch pour la carte tactique et la synchronisation avec le serveur.</p>
                            <div class="mt-6">
                                <a href="<?= htmlspecialchars($atakModDownloadUrl) ?>" class="flex items-center justify-center gap-3 w-full py-4 bg-emerald-600 hover:bg-emerald-500 text-white transition-all duration-300 shadow-lg shadow-emerald-900/20 group/btn">
                                    <span class="text-[11px] font-black uppercase tracking-[0.3em]">Télécharger le mod ATAK</span>
                                    <svg class="w-4 h-4 transition-transform duration-500 group-hover/btn:translate-y-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                </a>
                                <p class="mt-3 text-center">
                                    <a href="<?= url('atak') ?>" class="text-[8px] font-bold text-white/40 hover:text-white/70 uppercase tracking-[0.2em]">Page ATAK / Tacmap</a>
                                </p>
                            </div>
                        </div>
                    </aside>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <div class="py-12 flex justify-center">
            <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
        </div>
        <section class="py-12 bg-slate-50 overflow-hidden" x-data="{ openModal: null }">
            <div class="max-w-7xl mx-auto px-6 mb-8 flex justify-between items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-blue-600 mb-2">Catalogue</p>
                    <h2 class="text-3xl font-black uppercase tracking-tighter text-slate-900 italic">Nos Formations</h2>
                </div>
                <div class="flex gap-2">
                    <button class="p-3 border border-slate-200 rounded-full hover:bg-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button class="p-3 border border-slate-200 rounded-full hover:bg-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        
            <div class="flex gap-4 overflow-x-auto pb-12 px-[max(1.5rem,calc((100vw-80rem)/2))] no-scrollbar snap-x">
                
                <div @click="openModal = 1" class="flex-none w-72 h-[450px] relative group cursor-pointer snap-start overflow-hidden rounded-3xl transition-all duration-500 hover:-translate-y-2 hover:shadow-2xl hover:shadow-blue-900/20">
                    <img src="https://media.defense.gov/2019/Sep/12/2002181666/2000/2000/0/190905-F-BT441-0001.JPG" 
                         class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" alt="JTAC">
                    
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/20 to-transparent"></div>
        
                    <div class="absolute bottom-0 left-0 p-6 w-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="px-2 py-0.5 bg-emerald-500 text-[8px] font-black text-white uppercase tracking-widest rounded">Ouvert</span>
                        </div>
                        <h3 class="text-xl font-black text-white uppercase italic leading-none tracking-tighter mb-1">Opérateur JTAC</h3>
                        <p class="text-[10px] text-slate-300 font-bold uppercase tracking-[0.2em]">12 Mars 2026 • Paris / Remote</p>
                    </div>
                </div>
        
                <div @click="openModal = 2" class="flex-none w-72 h-[450px] relative group cursor-pointer snap-start overflow-hidden rounded-3xl transition-all duration-500 hover:-translate-y-2 hover:shadow-2xl">
                    <img src="https://media.defense.gov/2023/Sep/08/2003296716/2000/2000/0/230905-Z-VT419-1491.JPG" 
                         class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" alt="Formation UI/UX">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-6 w-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="px-2 py-0.5 bg-slate-600 text-[8px] font-black text-white uppercase tracking-widest rounded">Complet</span>
                        </div>
                        <h3 class="text-xl font-black text-white uppercase italic leading-none tracking-tighter mb-1">Basic Pistol</h3>
                        <p class="text-[10px] text-slate-300 font-bold uppercase tracking-[0.2em]">25 Avril 2026 • Lyon</p>
                    </div>
                </div>
        
                </div>
        
            <template x-if="openModal">
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
                    <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-sm" @click="openModal = null"></div>
                    
                    <div class="relative bg-white w-full max-w-4xl h-[80vh] overflow-hidden rounded-[2.5rem] shadow-2xl flex flex-col md:flex-row"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100">
                        
                        <button @click="openModal = null" class="absolute top-6 right-6 z-10 p-2 bg-slate-100 rounded-full hover:bg-slate-200 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
        
                        <div class="w-full md:w-1/2 h-64 md:h-auto bg-slate-900">
                            <img src="https://www.riotgames.com/darkroom/1000/93903a320a7bb5838f1aa5a2893dee2a:8fa0894a97f915c7a3925b1affec0182/final-16x9-lor-7-3-devvideothumbbanner-stripped-optimized-1.jpg" 
                                 class="w-full h-full object-cover opacity-80" alt="">
                        </div>
        
                        <div class="flex-1 p-8 md:p-12 overflow-y-auto bg-white">
                            <p class="text-[10px] font-black uppercase tracking-[0.4em] text-blue-600 mb-4 italic">Détails Formation</p>
                            <h2 class="text-4xl font-black uppercase tracking-tighter text-slate-900 mb-6 italic leading-none">Architecture & Design Patterns</h2>
                            
                            <div class="grid grid-cols-2 gap-6 mb-10 border-y border-slate-100 py-8">
                                <div>
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Date du cycle</span>
                                    <span class="text-sm font-bold text-slate-900 uppercase">12.03.2026</span>
                                </div>
                                <div>
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Lieu d'affectation</span>
                                    <span class="text-sm font-bold text-slate-900 uppercase">Campus Paris / Visio</span>
                                </div>
                            </div>
        
                            <p class="text-slate-600 leading-relaxed mb-10">
                                Plongez dans les entrailles des architectures modernes. Ce module avancé couvre les principes SOLID, le Domain Driven Design (DDD) et l'optimisation des performances backend sous haute charge.
                            </p>
        
                            <button class="w-full py-5 bg-slate-900 text-white text-[11px] font-black uppercase tracking-[0.3em] rounded-2xl hover:bg-blue-600 transition-all hover:translate-y-[-2px]">
                                S'inscrire au programme
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </section>
        
        <style>
            /* Pour cacher la scrollbar mais garder le défilement */
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
        <section class="max-w-7xl mx-auto px-6 md:px-10 py-12">
            <div class="grid grid-cols-1 2xl:grid-cols-[1.2fr_0.8fr] gap-12">
        
                <div class="space-y-12">
        
                    <section class="bg-white border-t-4 border-slate-900 shadow-sm">
                        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-600">Priorités immédiates</p>
                                <h2 class="mt-1 text-2xl font-black uppercase tracking-tight text-slate-900">Tableau de conduite</h2>
                            </div>
                            <a href="<?= url('formations') ?>" class="group flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors">
                                Consulter l'intégralité
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                        </div>
        
                        <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
                            <a href="<?= url('formations') ?>" class="p-8 hover:bg-slate-50 transition-all group">
                                <span class="text-[10px] font-mono font-bold text-slate-300 group-hover:text-blue-600 transition-colors">01</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.1em] text-slate-900 leading-snug">Finaliser le module opérateur</h3>
                                <p class="mt-4 text-[13px] text-slate-500 leading-relaxed font-medium">
                                    Reprise de la progression sur le manuel fondamental. Validation des séquences théoriques restantes.
                                </p>
                            </a>
        
                            <a href="/dossier-operateur/accreditation" class="p-8 hover:bg-slate-50 transition-all group text-balance">
                                <span class="text-[10px] font-mono font-bold text-slate-300 group-hover:text-blue-600 transition-colors">02</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.1em] text-slate-900 leading-snug">Mettre à jour l’accréditation</h3>
                                <p class="mt-4 text-[13px] text-slate-500 leading-relaxed font-medium">
                                    Audit des pièces justificatives et état de validation du profil individuel.
                                </p>
                            </a>
        
                            <a href="<?= url('documents') ?>" class="p-8 hover:bg-slate-50 transition-all group">
                                <span class="text-[10px] font-mono font-bold text-slate-300 group-hover:text-blue-600 transition-colors">03</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.1em] text-slate-900 leading-snug">Note opérationnelle</h3>
                                <p class="mt-4 text-[13px] text-slate-500 leading-relaxed font-medium">
                                    Consultation des derniers comptes-rendus et directives stratégiques en vigueur.
                                </p>
                            </a>
                        </div>
                    </section>
        
                    <section>
                        <div class="mb-6 flex items-baseline gap-4">
                            <h2 class="text-[11px] font-black uppercase tracking-[0.3em] text-slate-400">Modules stratégiques</h2>
                            <div class="h-[1px] flex-grow bg-slate-100"></div>
                        </div>
        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <a href="<?= url('dashboard') ?>" class="bg-white p-8 border border-slate-200 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-slate-200/50 transition-all group">
                                <div class="flex flex-col h-full">
                                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 group-hover:text-blue-600 transition-colors italic">Commandement</h3>
                                    <p class="mt-4 text-[13px] text-slate-500 leading-relaxed">Vue tactique, briefings, état du réseau et cellules actives.</p>
                                    <div class="mt-8 pt-6 border-t border-slate-50 flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-300">
                                        <span>Accès Niveau 1</span>
                                        <svg class="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </div>
                                </div>
                            </a>
        
                            <a href="<?= url('formations') ?>" class="bg-white p-8 border border-slate-200 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-slate-200/50 transition-all group">
                                <div class="flex flex-col h-full">
                                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 group-hover:text-blue-600 transition-colors italic">Académie</h3>
                                    <p class="mt-4 text-[13px] text-slate-500 leading-relaxed">Parcours d'instruction, progression et résultats des validations.</p>
                                    <div class="mt-8 pt-6 border-t border-slate-50 flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-300">
                                        <span>96% Validé</span>
                                        <svg class="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </div>
                                </div>
                            </a>
        
                            <a href="<?= url('documents') ?>" class="bg-white p-8 border border-slate-200 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-slate-200/50 transition-all group">
                                <div class="flex flex-col h-full">
                                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 group-hover:text-blue-600 transition-colors italic">Référentiel</h3>
                                    <p class="mt-4 text-[13px] text-slate-500 leading-relaxed">Doctrines, procédures, fiches et manuels techniques.</p>
                                    <div class="mt-8 pt-6 border-t border-slate-50 flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-300">
                                        <span>1.4k Entrées</span>
                                        <svg class="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </div>
                                </div>
                            </a>
                        </div>  
                    </section>
              

                    <!-- Bloc activité -->
                    <section class="bg-white border border-slate-200">
                        <div class="px-6 py-5 border-b border-slate-100">
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Journal</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight text-[#001529]">Activité récente</h2>
                        </div>
    
                        <div class="divide-y divide-slate-100">
                            <div class="px-6 py-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-black uppercase text-[#001529]">Connexion validée sur le nœud principal</p>
                                    <p class="mt-1 text-sm text-slate-500">Session ouverte depuis un terminal reconnu avec journalisation active.</p>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 whitespace-nowrap">20:15</span>
                            </div>
    
                            <div class="px-6 py-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-black uppercase text-[#001529]">Progression mise à jour sur le module fondamental</p>
                                    <p class="mt-1 text-sm text-slate-500">Dernière séquence validée : procédures d’entrée, organisation et doctrine.</p>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 whitespace-nowrap">18:42</span>
                            </div>
    
                            <div class="px-6 py-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-black uppercase text-[#001529]">Révision du dossier opérateur effectuée</p>
                                    <p class="mt-1 text-sm text-slate-500">Statut documentaire inchangé. Aucune anomalie bloquante détectée.</p>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 whitespace-nowrap">17:10</span>
                            </div>
                        </div>
                    </section>
                </div>
    
    
                <!-- Colonne droite -->
                <aside class="space-y-8">
                    <?php
                    $cu = $currentUser ?? null;
                    $pe = $personnelExtras ?? null;
                    $gr = $grade ?? null;
                    $displayName = $cu ? ($cu['display_name'] ?? $cu['email']) : '—';
                    $initials = $cu && !empty($cu['display_name']) ? strtoupper(preg_replace('/[^A-Z]/', '', substr((string)$cu['display_name'], 0, 2)) ?: 'OP') : 'OP';
                    $matricule = $pe ? ($pe['service_number'] ?? null) : null;
                    $idLine = $matricule ? 'Matricule: ' . $matricule : ($cu ? 'ID: ' . (int)$cu['id'] : '—');
                    $statut = $cu ? ($cu['status'] ?? '—') : '—';
                    $statutLabel = ($statut === 'active') ? 'Opérationnel' : $statut;
                    $gradeName = $gr ? ($gr['label_short'] ?? $gr['short_name'] ?? $gr['label_long'] ?? $gr['name'] ?? '—') : '—';
                    $clearance = $pe ? ($pe['clearance_level'] ?? '—') : '—';
                    $squadron = $pe ? ($pe['squadron'] ?? '—') : '—';
                    ?>
                    <section class="bg-white border border-slate-200 rounded-[2rem] overflow-hidden shadow-sm shadow-slate-200/50 transition-all hover:shadow-xl hover:shadow-slate-200/60">
                        <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center">
                            <div class="space-y-1">
                                <p class="text-[10px] font-[900] uppercase tracking-[0.3em] text-blue-600">Identité Opérateur</p>
                                <h2 class="text-xl font-black uppercase tracking-tighter text-slate-900">Dossier personnel</h2>
                            </div>
                            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        </div>
                
                        <div class="p-8">
                            <div class="flex items-center gap-6">
                                <div class="relative group">
                                    <div class="absolute inset-0 bg-blue-600 rounded-full blur opacity-10 group-hover:opacity-20 transition-opacity"></div>
                                    <div class="relative w-16 h-16 rounded-full border-2 border-slate-900 flex items-center justify-center bg-white overflow-hidden">
                                        <span class="text-xl font-black text-slate-900 italic tracking-tighter"><?= htmlspecialchars($initials) ?></span>
                                    </div>
                                </div>
                
                                <div class="min-w-0">
                                    <h3 class="text-2xl font-[950] uppercase italic tracking-tighter text-slate-900 leading-none"><?= htmlspecialchars($displayName) ?></h3>
                                    <p class="mt-2 font-mono text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($idLine) ?></p>
                                </div>
                            </div>
                
                            <div class="grid grid-cols-2 gap-px bg-slate-100 border border-slate-100 mt-10 rounded-2xl overflow-hidden">
                                <div class="bg-slate-50 p-5 space-y-1">
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Statut</span>
                                    <span class="block text-xs font-black uppercase <?= $statut === 'active' ? 'text-emerald-600 italic' : 'text-slate-900' ?>"><?= htmlspecialchars($statutLabel) ?></span>
                                </div>
                                <div class="bg-slate-50 p-5 space-y-1 text-right lg:text-left">
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Rang</span>
                                    <span class="block text-xs font-black uppercase text-slate-900"><?= htmlspecialchars($gradeName) ?></span>
                                </div>
                                <div class="bg-slate-50 p-5 space-y-1">
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Habilitation</span>
                                    <span class="block text-xs font-black uppercase text-slate-900"><?= htmlspecialchars($clearance ?: '—') ?></span>
                                </div>
                                <div class="bg-slate-50 p-5 space-y-1 text-right lg:text-left">
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Unité</span>
                                    <span class="block text-xs font-black uppercase text-slate-400 italic"><?= htmlspecialchars($squadron) ?></span>
                                </div>
                            </div>
                
                            <div class="mt-8">
                                <a href="<?= url('personnel/me') ?>" class="group flex items-center justify-center gap-4 w-full py-4 bg-white border-2 border-slate-900 text-slate-900 hover:bg-slate-900 hover:text-white transition-all duration-300 rounded-2xl">
                                    <span class="text-[11px] font-[900] uppercase tracking-[0.25em]">Accès dossier complet</span>
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                    </svg>
                                </a>
                                <p class="mt-4 text-center text-[9px] font-bold text-slate-300 uppercase tracking-widest italic">
                                    Fiche détaillée et données administratives
                                </p>
                            </div>
                        </div>
                    </section>
                                      
                        <!-- Alertes -->
                    <section class="bg-white border border-slate-200">
                        <div class="px-6 py-5 border-b border-slate-100">
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Surveillance</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight text-[#001529]">Alertes et échéances</h2>
                        </div>
    
                        <div class="divide-y divide-slate-100">
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-[#001529]">Validation documentaire à confirmer</p>
                                    <span class="text-[10px] px-2 py-1 bg-amber-50 border border-amber-200 text-amber-700 font-black uppercase tracking-[0.2em]">Majeur</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">Une pièce justificative requiert une vérification avant clôture du cycle.</p>
                            </div>
    
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-[#001529]">Module avancé non terminé</p>
                                    <span class="text-[10px] px-2 py-1 bg-slate-100 border border-slate-200 text-slate-600 font-black uppercase tracking-[0.2em]">Suivi</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">La progression est suspendue à 68 %. Reprise recommandée avant affectation.</p>
                            </div>
    
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-[#001529]">Synchronisation matériel prévue</p>
                                    <span class="text-[10px] px-2 py-1 bg-blue-50 border border-blue-200 text-blue-700 font-black uppercase tracking-[0.2em]">Info</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">Maintenance logicielle programmée sur l’équipement personnel enregistré.</p>
                            </div>
                        </div>
                    </section>
    
                    <!-- Raccourcis -->
                    <section class="bg-[#001529] text-white border border-slate-800">
                        <div class="px-6 py-5 border-b border-white/10">
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-white/40">Accès rapide</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight">Raccourcis de service</h2>
                        </div>
    
                        <div class="grid grid-cols-2 gap-px bg-white/10">
                            <a href="<?= url('atak') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">ATAK / Tacmap</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Carte tactique temps réel</p>
                            </a>
                            <a href="<?= url('orbat') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">ORBAT / Unité</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Organisation et personnel</p>
                            </a>
                            <a href="<?= url('personnel/me') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Ma fiche</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Dossier personnel</p>
                            </a>
                            <a href="<?= url('documents') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Documents</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Ordres et notes</p>
                            </a>
                            <a href="<?= url('formations') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Formations</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Séquences</p>
                            </a>
                            <a href="<?= url('account') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Paramètres</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Profil et paramètres</p>
                            </a>
                        </div>
                    </section>
    
                </aside>
    
            </div>
        </section>
    </main>
</body>
</html>