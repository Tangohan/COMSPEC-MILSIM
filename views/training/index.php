<?php
$base = url('');
$modules = $modules ?? [];

// Exemples statics pour démo (toujours affichés)
$examples = [
    [
        'slug' => 'operateur-jtac',
        'title' => 'Opérateur JTAC',
        'code' => 'Module T-01',
        'category' => 'tactique',
        'duration_meta' => 'Durée de formation',
        'duration_label' => '12 Semaines',
        'status_label' => 'Actif',
        'badge_position' => 'right',
        'badge_style' => 'slate',
        'badge_class' => 'text-emerald-400',
        'image' => 'https://media.defense.gov/2019/Sep/12/2002181666/2000/2000/0/190905-F-BT441-0001.JPG',
        'from_db' => false,
        'objectives' => [
            'Gestion des vecteurs aériens et appui feu rapproché (CAS).',
            'Maîtrise des protocoles radio OTAN et désignation laser.',
            'Coordination inter-armes en environnement dégradé.',
        ],
        'next_cycle' => '12 Mars 2026',
        'location' => "Zone d'entraînement Nord",
    ],
    [
        'slug' => 'architecture-ddd',
        'title' => 'Architecture & DDD',
        'code' => 'Module TECH-09',
        'category' => 'technique',
        'duration_meta' => 'Niveau Requis',
        'duration_label' => 'Accréditation Expert',
        'status_label' => '',
        'badge_position' => '',
        'badge_style' => '',
        'badge_class' => 'text-blue-400',
        'image' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop',
        'from_db' => false,
        'objectives' => [
            'Principes SOLID et architectures modernes.',
            'Domain Driven Design (DDD) et modélisation.',
            'Optimisation des performances sous charge.',
        ],
        'next_cycle' => '25 Avril 2026',
        'location' => 'Campus Paris / Visio',
    ],
    [
        'slug' => 'tccc-trauma-care',
        'title' => 'TCCC / Trauma Care',
        'code' => 'Module MED-03',
        'category' => 'médical',
        'duration_meta' => 'Statut Actuel',
        'duration_label' => 'Prochainement',
        'status_label' => 'Waitlist',
        'badge_position' => 'left',
        'badge_style' => 'rose',
        'badge_class' => 'text-rose-500',
        'image' => 'https://media.defense.gov/2022/Jun/14/2003017004/-1/-1/0/220607-F-DA916-1189.JPG',
        'from_db' => false,
        'objectives' => [
            'Tactical Combat Casualty Care (TCCC).',
            'Gestes de sauvetage au combat et évacuation.',
            'Coordination avec les équipes médicales.',
        ],
        'next_cycle' => 'À définir',
        'location' => 'À définir',
    ],
];

// Modules venant de la BDD (on leur attribue une catégorie par défaut)
$dbItems = [];
foreach ($modules as $m) {
    $dbItems[] = [
        'slug' => $m['slug'],
        'title' => $m['title'],
        'code' => $m['code'] ?? ('MOD-' . (int)$m['id']),
        'category' => 'tactique',
        'duration_meta' => 'Durée',
        'duration_label' => isset($m['estimated_duration_min']) ? (int)$m['estimated_duration_min'] . ' min' : '—',
        'status_label' => 'Actif',
        'badge_position' => 'right',
        'badge_style' => 'slate',
        'badge_class' => 'text-emerald-400',
        'image' => function_exists('training_course_default_cover_url') ? training_course_default_cover_url() : 'https://www.armytimes.com/resizer/v2/RAZQ3MLRIBFRLBIO4MWPXAB6XM.jpg?width=1200&auth=45ae6a1e3391a70c6e9e748d98ade72e1ed3f43ae5d0a5441a65e1d8a4a93e00',
        'from_db' => true,
        'description' => $m['description'] ?? '',
        'objectives' => $m['description'] ? array_filter(array_map('trim', explode("\n", $m['description']))) : ['Voir le module pour le détail.'],
        'next_cycle' => '—',
        'location' => '—',
    ];
}

$allItems = array_merge($examples, $dbItems);
$categories = ['all' => 'Tous les modules', 'tactique' => 'tactique', 'technique' => 'technique', 'médical' => 'médical'];
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue Formations — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 selection:bg-slate-900 selection:text-white" x-data="trainingCatalogue()">

    <nav class="sticky top-0 z-[100] w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="<?= $base ?>/" class="text-[11px] font-black tracking-[0.28em] uppercase hover:text-emerald-600 transition-colors">Athena Compsec</a>
            <div class="flex flex-wrap items-center gap-4 md:gap-6">
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-900">Tableau de bord</a>
                <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh'), ENT_QUOTES, 'UTF-8') ?>" class="text-[9px] font-black text-violet-700 uppercase tracking-widest hover:text-violet-950">Espace RH</a>
                <a href="<?= htmlspecialchars(url('account/charte-formations'), ENT_QUOTES, 'UTF-8') ?>" class="text-[9px] font-black text-emerald-700 uppercase tracking-widest hover:text-emerald-950">Charte</a>
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Training_Protocol_v4.0</span>
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
            </div>
        </div>
    </nav>

    <main class="min-h-screen pt-24 pb-32 bg-slate-50">
        <div class="max-w-7xl mx-auto px-8">

            <header class="mb-24">
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-12">
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            <span class="w-12 h-[1px] bg-slate-900"></span>
                            <span class="text-[10px] font-black uppercase tracking-[0.5em] text-emerald-600 italic">Operational Readiness</span>
                        </div>
                        <h1 class="text-6xl md:text-9xl font-black uppercase italic tracking-tighter leading-[0.8] text-slate-900">
                            Catalogue <br> <span class="text-slate-300">Formations</span>
                        </h1>
                    </div>

                    <nav class="flex flex-wrap gap-4 border-l border-slate-200 pl-8">
                        <button type="button" @click="activeCategory = 'all'" :class="activeCategory === 'all' ? 'text-slate-900 underline underline-offset-8' : 'text-slate-400 hover:text-slate-600'" class="text-[10px] font-black uppercase tracking-[0.3em] transition-all italic">
                            All_Modules
                        </button>
                        <button type="button" @click="activeCategory = 'tactique'" :class="activeCategory === 'tactique' ? 'text-slate-900 underline underline-offset-8' : 'text-slate-400 hover:text-slate-600'" class="text-[10px] font-black uppercase tracking-[0.3em] transition-all italic">
                            Tactique
                        </button>
                        <button type="button" @click="activeCategory = 'technique'" :class="activeCategory === 'technique' ? 'text-slate-900 underline underline-offset-8' : 'text-slate-400 hover:text-slate-600'" class="text-[10px] font-black uppercase tracking-[0.3em] transition-all italic">
                            Technique
                        </button>
                        <button type="button" @click="activeCategory = 'médical'" :class="activeCategory === 'médical' ? 'text-slate-900 underline underline-offset-8' : 'text-slate-400 hover:text-slate-600'" class="text-[10px] font-black uppercase tracking-[0.3em] transition-all italic">
                            Médical
                        </button>
                    </nav>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-20">
                <?php foreach ($allItems as $item): ?>
                <article x-show="activeCategory === 'all' || activeCategory === '<?= htmlspecialchars($item['category']) ?>'"
                         class="group relative cursor-pointer"
                         @click="openModal('<?= htmlspecialchars($item['slug'], ENT_QUOTES) ?>')">
                    <div class="relative aspect-[4/5] overflow-hidden rounded-3xl mb-8 bg-slate-200">
                        <img src="<?= htmlspecialchars($item['image']) ?>" class="absolute inset-0 w-full h-full object-cover grayscale transition-all duration-700 group-hover:scale-110 group-hover:grayscale-0" alt="<?= htmlspecialchars($item['title']) ?>">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent opacity-60"></div>
                        <?php if (!empty($item['status_label'])): ?>
                        <div class="absolute top-6 <?= ($item['badge_position'] ?? '') === 'left' ? 'left-6' : 'right-6' ?>">
                            <?php if (($item['badge_style'] ?? '') === 'rose'): ?>
                            <span class="px-3 py-1 bg-rose-600 text-[8px] font-black text-white uppercase tracking-widest rounded-sm italic animate-pulse"><?= htmlspecialchars($item['status_label']) ?></span>
                            <?php else: ?>
                            <span class="px-3 py-1 bg-slate-900/40 backdrop-blur-md border border-white/10 text-[8px] font-black text-white uppercase tracking-widest rounded-sm"><?= htmlspecialchars($item['status_label']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="absolute bottom-8 left-8 right-8 translate-y-2 group-hover:translate-y-0 transition-transform duration-500">
                            <p class="text-[8px] font-black <?= htmlspecialchars($item['badge_class']) ?> uppercase tracking-[0.5em] mb-3"><?= htmlspecialchars($item['code']) ?></p>
                            <h3 class="text-3xl font-black text-white uppercase italic leading-none tracking-tighter group-hover:tracking-normal transition-all"><?= htmlspecialchars($item['title']) ?></h3>
                        </div>
                    </div>
                    <div class="flex justify-between items-center px-2">
                        <div class="flex flex-col">
                            <span class="text-[7px] font-black text-slate-400 uppercase tracking-widest italic mb-1"><?= htmlspecialchars($item['duration_meta'] ?? 'Durée') ?></span>
                            <span class="text-[10px] font-bold text-slate-900 uppercase <?= ($item['category'] ?? '') === 'technique' ? 'italic underline decoration-blue-500 underline-offset-4' : '' ?> <?= ($item['category'] ?? '') === 'médical' ? 'text-rose-600 italic' : '' ?>"><?= htmlspecialchars($item['duration_label'] ?? '—') ?></span>
                        </div>
                        <div class="h-8 w-[1px] bg-slate-200"></div>
                        <span class="text-[9px] font-black italic text-slate-900 uppercase tracking-widest group-hover:text-emerald-600 transition-colors">Details +</span>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <div x-show="visibleCount === 0" x-cloak class="mx-auto max-w-xl rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-14 text-center">
                <p class="text-base font-bold text-slate-800">Aucun module dans cette catégorie</p>
                <p class="mt-2 text-sm text-slate-600">Choisissez une autre famille de modules ou ouvrez le catalogue complet.</p>
                <p class="mt-8">
                    <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-emerald-700">Tout afficher</a>
                </p>
            </div>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-12">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex flex-col items-center md:items-start">
                <span class="text-[10px] font-black tracking-[0.5em] uppercase">Athena — Formations</span>
                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mt-1">Operational Excellence</span>
            </div>
            <div class="flex gap-12">
                <a href="<?= url('documents') ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 transition-colors italic">Documentation</a>
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 transition-colors italic">Dashboard</a>
            </div>
        </div>
    </footer>

    <!-- Modal détail formation -->
    <template x-if="openModalSlug">
        <div class="fixed inset-0 z-[200] flex items-center justify-center p-4" x-show="openModalSlug">
            <div class="absolute inset-0 bg-slate-950/95 backdrop-blur-md" @click="openModalSlug = null"></div>
            <div class="relative bg-white w-full max-w-5xl max-h-[90vh] overflow-hidden rounded-[2.5rem] shadow-2xl flex flex-col md:flex-row shadow-emerald-900/20"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <button type="button" @click="openModalSlug = null" class="absolute top-8 right-8 z-10 p-3 bg-slate-100 rounded-full hover:bg-slate-200 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <template x-if="selectedItem">
                    <div class="flex-1 flex flex-col md:flex-row min-h-0">
                        <div class="w-full md:w-1/2 md:min-h-[400px] relative bg-slate-900 flex-shrink-0">
                            <img :src="selectedItem.image" class="w-full h-full object-cover object-left opacity-60" :alt="selectedItem.title">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[1em] block mb-4 italic">Détail module</span>
                                    <a :href="selectedItem.from_db ? '<?= $base ?>/formations/' + selectedItem.slug : '#'" 
                                       class="w-16 h-16 border-2 border-white/20 rounded-full flex items-center justify-center mx-auto hover:border-emerald-500 transition-colors">
                                        <svg class="w-6 h-6 text-white fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 p-8 md:p-16 overflow-y-auto bg-white">
                            <p class="text-[10px] font-black uppercase tracking-[0.4em] text-emerald-600 mb-6 italic" x-text="selectedItem.code"></p>
                            <h2 class="text-4xl md:text-5xl font-black uppercase tracking-tighter text-slate-900 mb-8 italic leading-none" x-text="selectedItem.title"></h2>
                            <div class="grid grid-cols-2 gap-8 mb-12 border-y border-slate-100 py-10">
                                <div>
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-2">Prochain Cycle</span>
                                    <span class="text-sm font-bold text-slate-900 uppercase" x-text="selectedItem.next_cycle"></span>
                                </div>
                                <div>
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-2">Localisation</span>
                                    <span class="text-sm font-bold text-slate-900 uppercase" x-text="selectedItem.location"></span>
                                </div>
                            </div>
                            <div class="space-y-6 mb-12" x-show="selectedItem.objectives && selectedItem.objectives.length">
                                <h4 class="text-xs font-black uppercase tracking-widest text-slate-900 underline decoration-emerald-500 underline-offset-4">Objectifs de mission</h4>
                                <ul class="text-sm text-slate-600 space-y-4">
                                    <template x-for="(obj, idx) in selectedItem.objectives" :key="idx">
                                        <li class="flex items-start gap-3 italic">
                                            <span class="text-emerald-500 font-black" x-text="String(idx + 1).padStart(2, '0') + ' /'"></span>
                                            <span x-text="obj"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                            <a :href="selectedItem.from_db ? '<?= $base ?>/formations/' + selectedItem.slug : '#'"
                               class="block w-full py-6 bg-slate-900 text-white text-[11px] font-black uppercase tracking-[0.4em] rounded-2xl hover:bg-emerald-600 transition-all hover:translate-y-[-4px] shadow-2xl shadow-slate-200 text-center">
                                <span x-text="selectedItem.from_db ? 'Accéder au module' : 'S\'inscrire au programme de sélection'"></span>
                            </a>
                            <p class="mt-6 text-[8px] text-center font-bold text-slate-300 uppercase tracking-widest">Sous réserve d'accréditation</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <script>
        function trainingCatalogue() {
            const items = <?= json_encode(array_values($allItems)) ?>;
            return {
                activeCategory: 'all',
                openModalSlug: null,
                get visibleCount() {
                    return items.filter(
                        (m) => this.activeCategory === 'all' || m.category === this.activeCategory
                    ).length;
                },
                get selectedItem() {
                    if (!this.openModalSlug) return null;
                    return items.find(m => m.slug === this.openModalSlug) || null;
                },
                openModal(slug) {
                    this.openModalSlug = slug;
                }
            };
        }
    </script>
</body>
</html>
