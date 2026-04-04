<?php
$base = url('');
$courses = $courses ?? [];
$legacyModules = $legacyModules ?? [];
$title = $title ?? 'Catalogue Formations';
$totalModules = count($courses) + count($legacyModules);
$categories = array_values(array_unique(array_filter(array_map(function ($c) {
    return $c['category'] ?? null;
}, $courses))));
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .grain {
            position: fixed; inset: 0; pointer-events: none; z-index: 1; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
        }
        .panel {
            background: rgba(255,255,255,0.78);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(15,23,42,0.06);
            box-shadow: 0 18px 40px rgba(15,23,42,0.05);
        }
        .dark-panel {
            background: rgba(5,8,16,0.94);
            border: 1px solid rgba(255,255,255,0.06);
            box-shadow: 0 20px 50px rgba(2,6,23,0.45);
        }
        .active-nav {
            background: linear-gradient(90deg, rgba(16,185,129,0.12), rgba(16,185,129,0.02));
            border-color: rgba(16,185,129,0.25);
            color: #ffffff;
        }
        .course-card:hover {
            transform: translateY(-4px);
            border-color: rgba(16,185,129,0.25);
            box-shadow: 0 20px 45px rgba(15,23,42,0.07);
        }
        .session-row:hover {
            background: rgba(15,23,42,0.025);
        }
        .progress-bar > span {
            display: block; height: 100%; border-radius: 999px;
            background: linear-gradient(90deg, #10b981, #34d399);
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 overflow-x-hidden">
    <div class="grain"></div>

    <div class="min-h-screen relative z-10">
        <div class="grid lg:grid-cols-[290px_1fr] min-h-screen">

            <aside class="dark-panel text-white p-6 lg:p-8 flex flex-col">
                <div class="pb-8 border-b border-white/10">
                    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-emerald-400 mb-3">Athena / COMSPEC</p>
                    <h1 class="text-2xl font-black tracking-tight uppercase leading-none">Training Command</h1>
                    <p class="text-[11px] text-white/35 font-medium mt-3 leading-relaxed">
                        Catalogue, cycles de qualification, sessions planifiées et suivi de disponibilité opérationnelle.
                    </p>
                </div>

                <nav class="pt-8 space-y-3">
                    <a href="<?= $base ?>/formations#overview" class="active-nav flex items-center justify-between rounded-2xl border px-4 py-3 transition-all">
                        <span>
                            <span class="block text-[8px] font-black tracking-[0.3em] uppercase text-emerald-400">01</span>
                            <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Overview</span>
                        </span>
                        <span class="text-[10px] font-black tracking-widest uppercase text-white/40">Live</span>
                    </a>
                    <a href="<?= $base ?>/formations#catalogue" class="flex items-center justify-between rounded-2xl border border-white/5 bg-white/[0.02] px-4 py-3 transition-all hover:border-emerald-500/20">
                        <span>
                            <span class="block text-[8px] font-black tracking-[0.3em] uppercase text-white/25">02</span>
                            <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Catalogue</span>
                        </span>
                        <span class="text-[10px] font-black tracking-widest uppercase text-white/25"><?= $totalModules ?></span>
                    </a>
                    <a href="<?= $base ?>/formations/mes-formations" class="flex items-center justify-between rounded-2xl border border-white/5 bg-white/[0.02] px-4 py-3 transition-all hover:border-emerald-500/20">
                        <span>
                            <span class="block text-[8px] font-black tracking-[0.3em] uppercase text-white/25">03</span>
                            <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Mes formations</span>
                        </span>
                        <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Suivi</span>
                    </a>
                    <a href="<?= $base ?>/formations#sessions" class="flex items-center justify-between rounded-2xl border border-white/5 bg-white/[0.02] px-4 py-3 transition-all hover:border-emerald-500/20">
                        <span>
                            <span class="block text-[8px] font-black tracking-[0.3em] uppercase text-white/25">04</span>
                            <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Sessions</span>
                        </span>
                        <span class="text-[10px] font-black tracking-widest uppercase text-white/25">—</span>
                    </a>
                    <a href="<?= $base ?>/formations#qualifications" class="flex items-center justify-between rounded-2xl border border-white/5 bg-white/[0.02] px-4 py-3 transition-all hover:border-emerald-500/20">
                        <span>
                            <span class="block text-[8px] font-black tracking-[0.3em] uppercase text-white/25">05</span>
                            <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Qualifications</span>
                        </span>
                        <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Grid</span>
                    </a>
                </nav>

                <div class="mt-10 pt-8 border-t border-white/10 space-y-5">
                    <div class="rounded-2xl bg-white/[0.03] border border-white/5 p-4">
                        <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2">Catalogue</p>
                        <p class="text-sm font-black uppercase tracking-[0.14em]"><?= $totalModules ?> module<?= $totalModules > 1 ? 's' : '' ?></p>
                        <p class="text-[11px] text-white/35 mt-2">Formations et parcours opérationnels disponibles.</p>
                    </div>
                    <div class="rounded-2xl bg-white/[0.03] border border-white/5 p-4">
                        <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2">Prochaine fenêtre</p>
                        <p class="text-sm font-black uppercase tracking-[0.14em]">Voir Sessions</p>
                        <p class="text-[11px] text-emerald-400 mt-2 font-bold uppercase tracking-[0.14em]">Mes formations</p>
                    </div>
                </div>

                <div class="mt-auto pt-8 border-t border-white/10">
                    <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/20">Build</p>
                    <div class="flex items-center justify-between mt-3">
                        <span class="text-[10px] font-mono text-white/35 tracking-[0.22em] uppercase">Training</span>
                        <span class="text-[10px] font-black uppercase text-emerald-400">Operational</span>
                    </div>
                </div>
            </aside>

            <main class="p-5 md:p-8 lg:p-10 space-y-8">

                <header id="overview" class="panel rounded-[2rem] p-6 md:p-8 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-500/80 via-emerald-500/20 to-transparent"></div>
                    <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-8">
                        <div class="max-w-3xl">
                            <p class="text-[9px] font-black tracking-[0.45em] text-emerald-600 uppercase mb-4">Training Overview</p>
                            <h2 class="text-3xl md:text-5xl font-black tracking-tight uppercase leading-none mb-5">
                                Structured Instruction For<br>Operational Continuity
                            </h2>
                            <div class="h-[1px] w-20 bg-slate-900/10 mb-5"></div>
                            <p class="text-slate-500 text-[11px] font-bold tracking-[0.18em] uppercase leading-relaxed max-w-2xl">
                                Centralized training catalogue, scheduled sessions, qualification states and standardization of core tactical procedures.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 min-w-full xl:min-w-[560px]">
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Modules</p>
                                <p class="text-2xl font-black tracking-tight"><?= $totalModules ?></p>
                            </div>
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Parcours</p>
                                <p class="text-2xl font-black tracking-tight"><?= count($courses) ?></p>
                            </div>
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Legacy</p>
                                <p class="text-2xl font-black tracking-tight text-emerald-600"><?= count($legacyModules) ?></p>
                            </div>
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Accès</p>
                                <p class="text-2xl font-black tracking-tight text-amber-500">Ouvert</p>
                            </div>
                        </div>
                    </div>
                </header>

                <section class="grid xl:grid-cols-[1.2fr_0.8fr] gap-8">
                    <div id="catalogue" class="panel rounded-[2rem] p-6 md:p-8">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                            <div>
                                <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Catalogue</p>
                                <h3 class="text-2xl font-black tracking-tight uppercase">Available Training Modules</h3>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="<?= $base ?>/formations#catalogue" class="px-4 py-2 rounded-full border border-slate-200 bg-slate-900 text-white text-[10px] font-black tracking-[0.18em] uppercase">All</a>
                                <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
                                <span class="px-4 py-2 rounded-full border border-slate-200 bg-white text-slate-700 text-[10px] font-black tracking-[0.18em] uppercase"><?= htmlspecialchars($cat) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if (empty($courses) && empty($legacyModules)): ?>
                        <div class="py-16 text-center">
                            <p class="text-sm font-bold uppercase tracking-[0.24em] text-slate-400">Aucun module disponible</p>
                            <p class="mt-3 text-slate-500">Aucune formation publiée pour le moment.</p>
                            <p class="mt-6"><a href="<?= $base ?>/dashboard" class="text-emerald-600 hover:underline font-semibold">Tableau de bord</a></p>
                        </div>
                        <?php else: ?>
                        <div class="grid md:grid-cols-2 gap-5">
                            <?php
                            $cardColorClasses = [
                                ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/20', 'text' => 'text-emerald-700'],
                                ['bg' => 'bg-sky-500/10', 'border' => 'border-sky-500/20', 'text' => 'text-sky-700'],
                                ['bg' => 'bg-red-500/10', 'border' => 'border-red-500/20', 'text' => 'text-red-700'],
                                ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/20', 'text' => 'text-amber-700'],
                                ['bg' => 'bg-violet-500/10', 'border' => 'border-violet-500/20', 'text' => 'text-violet-700'],
                            ];
                            $ci = 0;
                            foreach ($courses as $c):
                                $cat = $c['category'] ?? 'Core';
                                $code = $c['code'] ?? ('F-' . (int)($c['id'] ?? 0));
                                $mins = (int)($c['estimated_minutes'] ?? 0);
                                $duration = $mins ? $mins . ' min' : '—';
                                $cc = $cardColorClasses[$ci % count($cardColorClasses)];
                                $ci++;
                            ?>
                            <a href="<?= $base ?>/formations/<?= htmlspecialchars($c['slug']) ?>" class="course-card block bg-white rounded-3xl border border-slate-200 p-5 transition-all">
                                <div class="flex items-start justify-between gap-4 mb-5">
                                    <div class="w-12 h-12 rounded-2xl <?= $cc['bg'] ?> <?= $cc['border'] ?> flex items-center justify-center">
                                        <span class="text-[11px] font-black tracking-widest <?= $cc['text'] ?>"><?= htmlspecialchars(mb_substr($code, 0, 4)) ?></span>
                                    </div>
                                    <span class="text-[8px] font-black tracking-[0.25em] uppercase text-slate-400"><?= htmlspecialchars($cat) ?></span>
                                </div>
                                <h4 class="text-lg font-black tracking-tight uppercase mb-2"><?= htmlspecialchars($c['title']) ?></h4>
                                <p class="text-[11px] text-slate-600 font-medium leading-relaxed mb-5"><?= !empty($c['short_description']) ? htmlspecialchars($c['short_description']) : 'Parcours opérationnel.' ?></p>
                                <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.14em]">
                                    <span class="text-slate-400">Duration</span>
                                    <span><?= $duration ?></span>
                                </div>
                                <?php if (!empty($c['enrollment'])): ?>
                                <div class="mt-3">
                                    <span class="text-[10px] font-bold <?= ($c['enrollment']['status'] ?? '') === 'completed' ? 'text-emerald-600' : 'text-amber-600' ?>">
                                        <?= ($c['enrollment']['status'] ?? '') === 'completed' ? 'Qualified' : ((int)($c['progress_percent'] ?? 0) . '%') ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>

                            <?php foreach ($legacyModules as $m):
                                $code = $m['code'] ?? ('MOD-' . (int)$m['id']);
                            ?>
                            <a href="<?= $base ?>/formations/<?= htmlspecialchars($m['slug']) ?>" class="course-card block bg-white rounded-3xl border border-slate-200 p-5 transition-all">
                                <div class="flex items-start justify-between gap-4 mb-5">
                                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                                        <span class="text-[11px] font-black tracking-widest text-sky-700"><?= htmlspecialchars(mb_substr($code, 0, 4)) ?></span>
                                    </div>
                                    <span class="text-[8px] font-black tracking-[0.25em] uppercase text-slate-400">Legacy</span>
                                </div>
                                <h4 class="text-lg font-black tracking-tight uppercase mb-2"><?= htmlspecialchars($m['title']) ?></h4>
                                <p class="text-[11px] text-slate-600 font-medium leading-relaxed mb-5"><?= htmlspecialchars($m['code'] ?? 'Module') ?></p>
                                <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.14em]">
                                    <span class="text-slate-400">Type</span>
                                    <span>Module</span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="panel rounded-[2rem] p-6 md:p-8">
                        <div class="flex items-center justify-between gap-4 mb-6">
                            <div>
                                <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Suivi</p>
                                <h3 class="text-2xl font-black tracking-tight uppercase">Mes formations</h3>
                            </div>
                            <span class="px-3 py-2 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-black tracking-[0.18em] uppercase text-emerald-700">Open</span>
                        </div>
                        <p class="text-[12px] text-slate-700 leading-relaxed font-medium mb-5">
                            Consultez votre progression, les modules en cours et vos attestations.
                        </p>
                        <a href="<?= $base ?>/formations/mes-formations" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl hover:bg-emerald-600 transition-all">
                            Accéder à Mes formations
                        </a>
                    </div>
                </section>

                <section id="sessions" class="panel rounded-[2rem] p-6 md:p-8">
                    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
                        <div>
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Scheduled Sessions</p>
                            <h3 class="text-2xl font-black tracking-tight uppercase">Upcoming Training Windows</h3>
                        </div>
                        <div class="text-[10px] font-black tracking-[0.18em] uppercase text-slate-400">Sessions communiquées par le commandement</div>
                    </div>
                    <div class="py-8 text-center text-slate-500 text-sm rounded-2xl border border-slate-200 bg-slate-50/50">
                        Aucune session planifiée affichée. Consultez les briefings et le tableau de bord pour les créneaux.
                    </div>
                </section>

                <section id="qualifications" class="grid xl:grid-cols-[0.9fr_1.1fr] gap-8">
                    <div class="panel rounded-[2rem] p-6 md:p-8">
                        <div class="mb-8">
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Qualification State</p>
                            <h3 class="text-2xl font-black tracking-tight uppercase">Readiness Snapshot</h3>
                        </div>
                        <p class="text-slate-600 text-sm mb-6">Votre progression par parcours et modules.</p>
                        <a href="<?= $base ?>/formations/mes-formations" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl hover:bg-emerald-600 transition-all">
                            Voir Mes formations
                        </a>
                    </div>
                    <div class="panel rounded-[2rem] p-6 md:p-8">
                        <div class="mb-8">
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Qualifications</p>
                            <h3 class="text-2xl font-black tracking-tight uppercase">Progression & certifications</h3>
                        </div>
                        <p class="text-slate-500 text-sm">Consultez <a href="<?= $base ?>/formations/mes-formations" class="text-emerald-600 hover:underline font-semibold">Mes formations</a> pour votre progression et vos attestations.</p>
                    </div>
                </section>

            </main>
        </div>
    </div>
</body>
</html>
