<?php
$base = url('');
$module = $module ?? null;
if (!$module) {
    echo '<p>Module non trouvé.</p>';
    return;
}
$title = htmlspecialchars($module['title']);
$code = htmlspecialchars($module['code'] ?? 'MOD-' . (int)($module['id']));
$description = $module['description'] ?? '';
$objectives = $description ? array_filter(array_map('trim', explode("\n", $description))) : ['Contenu du module à consulter.'];
$duration = isset($module['estimated_duration_min']) ? (int) $module['estimated_duration_min'] . ' min' : '—';
$image = 'https://media.defense.gov/2019/Sep/12/2002181666/2000/2000/0/190905-F-BT441-0001.JPG';
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> — Formations Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-900">

    <nav class="sticky top-0 z-[100] w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="<?= $base ?>/" class="text-[11px] font-black tracking-[0.5em] uppercase hover:text-emerald-600 transition-colors">FORWARD</a>
            <div class="flex items-center gap-6">
                <a href="<?= url('formations') ?>" class="text-[9px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-900">Catalogue</a>
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-900">Dashboard</a>
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
            </div>
        </div>
    </nav>

    <main class="py-16 md:py-24">
        <div class="max-w-5xl mx-auto px-6">

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.2fr] gap-12 items-start">
                <!-- Visuel -->
                <div class="relative aspect-[3/4] overflow-hidden rounded-3xl shadow-2xl bg-slate-900">
                    <img src="<?= $image ?>" class="absolute inset-0 w-full h-full object-cover opacity-70" alt="">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent"></div>
                    <div class="absolute top-6 left-6">
                        <span class="px-3 py-1 bg-emerald-500 text-[8px] font-black text-white uppercase tracking-widest rounded-full">Actif</span>
                    </div>
                    <div class="absolute bottom-8 left-8 right-8">
                        <p class="text-[9px] font-black text-emerald-400 uppercase tracking-[0.3em] mb-2"><?= $code ?></p>
                        <h1 class="text-2xl md:text-3xl font-black text-white uppercase italic leading-none tracking-tighter"><?= $title ?></h1>
                    </div>
                </div>

                <!-- Contenu -->
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.4em] text-emerald-600 mb-4 italic"><?= $code ?></p>
                    <h2 class="text-3xl md:text-4xl font-black uppercase tracking-tighter text-slate-900 mb-8 italic leading-none"><?= $title ?></h2>

                    <div class="grid grid-cols-2 gap-6 mb-10 border-y border-slate-100 py-8">
                        <div>
                            <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-2">Durée estimée</span>
                            <span class="text-sm font-bold text-slate-900 uppercase"><?= $duration ?></span>
                        </div>
                        <div>
                            <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-2">Statut</span>
                            <span class="text-sm font-bold text-slate-900 uppercase">Publié</span>
                        </div>
                    </div>

                    <?php if (!empty($objectives)): ?>
                    <div class="space-y-6 mb-10">
                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-900 underline decoration-emerald-500 underline-offset-4">Objectifs / Contenu</h4>
                        <ul class="text-sm text-slate-600 space-y-4">
                            <?php foreach ($objectives as $i => $obj): ?>
                            <li class="flex items-start gap-3 italic">
                                <span class="text-emerald-500 font-black"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?> /</span>
                                <?= nl2br(htmlspecialchars($obj)) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="prose prose-slate max-w-none text-slate-600">
                        <?php if ($description && count($objectives) <= 1): ?>
                        <p><?= nl2br(htmlspecialchars($description)) ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-slate-500 italic">Contenu interactif et évaluations à venir (intégration Phase 6).</p>
                    </div>

                    <div class="mt-12 flex flex-wrap gap-4">
                        <a href="<?= url('formations') ?>" class="inline-flex items-center gap-2 px-8 py-4 bg-slate-900 text-white text-[11px] font-black uppercase tracking-[0.3em] rounded-2xl hover:bg-emerald-600 transition-all">
                            Retour au catalogue
                        </a>
                        <a href="<?= url('dashboard') ?>" class="inline-flex items-center gap-2 px-8 py-4 border-2 border-slate-200 text-slate-700 text-[11px] font-black uppercase tracking-[0.3em] rounded-2xl hover:border-slate-900 transition-all">
                            Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-12 mt-20">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
            <span class="text-[10px] font-black tracking-[0.5em] uppercase">Athena — Formations</span>
            <div class="flex gap-12">
                <a href="<?= url('documents') ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 transition-colors italic">Documentation</a>
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 transition-colors italic">Dashboard</a>
            </div>
        </div>
    </footer>
</body>
</html>
