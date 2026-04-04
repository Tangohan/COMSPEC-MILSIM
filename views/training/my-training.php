<?php
$base = url('');
$enrollments = $enrollments ?? [];
$title = $title ?? 'Mes formations';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

    <nav class="sticky top-0 z-[100] w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="<?= $base ?>/" class="text-[11px] font-black tracking-[0.5em] uppercase hover:text-emerald-600">ATHENA</a>
            <div class="flex items-center gap-6">
                <a href="<?= url('formations') ?>" class="text-[9px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-900">Catalogue</a>
                <a href="<?= url('formations/mes-formations') ?>" class="text-[9px] font-black text-slate-900 uppercase tracking-widest">Mes formations</a>
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-900">Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12">
        <h1 class="text-4xl font-black uppercase tracking-tight text-slate-900 mb-2">Mes formations</h1>
        <p class="text-slate-600 mb-8">Reprenez là où vous vous êtes arrêté.</p>

        <?php if (empty($enrollments)): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
            <p class="text-slate-500">Aucune formation assignée.</p>
            <a href="<?= url('formations') ?>" class="inline-block mt-4 px-6 py-3 bg-slate-900 text-white text-sm font-bold uppercase tracking-wider rounded-xl hover:bg-emerald-600 transition-colors">Voir le catalogue</a>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($enrollments as $e): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($e['course_title'] ?? 'Formation') ?></h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Statut : <span class="font-medium <?= ($e['status'] ?? '') === 'completed' ? 'text-emerald-600' : 'text-amber-600' ?>"><?= htmlspecialchars($e['status'] ?? '') ?></span>
                        — <?= (int)($e['progress_percent'] ?? 0) ?> %
                    </p>
                    <?php if (!empty($e['expires_at'])): ?>
                    <p class="text-xs text-slate-400 mt-1">Expire le <?= date('d/m/Y', strtotime($e['expires_at'])) ?></p>
                    <?php endif; ?>
                </div>
                <a href="<?= url('formations/' . htmlspecialchars($e['course_slug'] ?? '')) ?>" class="px-6 py-3 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-emerald-600 transition-colors">
                    <?= ($e['status'] ?? '') === 'completed' ? 'Voir' : 'Reprendre' ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <footer class="border-t border-slate-200 py-8 mt-12">
        <div class="max-w-7xl mx-auto px-6 text-center text-xs text-slate-500">Athena — Formations</div>
    </footer>
</body>
</html>
