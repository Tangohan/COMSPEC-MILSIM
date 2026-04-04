<?php
$base = url('');
$course = $course ?? null;
$enrollment = $enrollment ?? null;
$progressPercent = $progressPercent ?? 0;
$certificate = $certificate ?? null;
if (!$course) {
    echo '<p>Formation non trouvée.</p>';
    return;
}
$courseId = (int) $course['id'];
$modules = $course['modules'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> — Athena</title>
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
                <a href="<?= url('formations/mes-formations') ?>" class="text-[9px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-900">Mes formations</a>
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-900">Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12">
        <?php if (!empty($course['banner_path'])): ?>
        <div class="rounded-2xl overflow-hidden mb-8 aspect-[3/1] bg-slate-800">
            <img src="<?= htmlspecialchars($base . '/' . $course['banner_path']) ?>" alt="" class="w-full h-full object-cover">
        </div>
        <?php endif; ?>

        <div class="flex flex-wrap items-start justify-between gap-6 mb-8">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-tight text-slate-900"><?= htmlspecialchars($course['title']) ?></h1>
                <?php if (!empty($course['short_description'])): ?>
                <p class="text-slate-600 mt-2"><?= htmlspecialchars($course['short_description']) ?></p>
                <?php endif; ?>
                <p class="text-sm text-slate-500 mt-2"><?= (int)($course['estimated_minutes'] ?? 0) ?> min — <?= htmlspecialchars($course['category'] ?? '') ?></p>
            </div>
            <?php if ($enrollment): ?>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <p class="text-2xl font-black text-slate-900"><?= (int) $progressPercent ?> %</p>
                    <p class="text-xs text-slate-500 uppercase tracking-wider">Progression</p>
                </div>
                <?php if ($certificate): ?>
                <a href="<?= url('formations/certificate/' . (int) $certificate['id']) ?>" class="px-6 py-3 bg-emerald-600 text-white text-xs font-bold uppercase rounded-xl hover:bg-emerald-700">Attestation</a>
                <?php elseif (($enrollment['status'] ?? '') === 'completed'): ?>
                <span class="text-sm font-bold text-emerald-600">Formation validée</span>
                <?php else: ?>
                <a href="#" data-start-course="<?= (int) $enrollment['id'] ?>" class="px-6 py-3 bg-slate-900 text-white text-xs font-bold uppercase rounded-xl hover:bg-emerald-600">Reprendre</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($enrollment && (int) $progressPercent < 100): ?>
        <div class="mb-8">
            <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-600 rounded-full transition-all" style="width: <?= (int) $progressPercent ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <?php foreach ($modules as $mod): ?>
                <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($mod['title']) ?></h2>
                        <?php if (!empty($mod['description'])): ?>
                        <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($mod['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($mod['lessons'] ?? [] as $lesson): ?>
                        <li class="flex items-center justify-between px-5 py-3">
                            <span class="text-sm text-slate-700"><?= htmlspecialchars($lesson['title']) ?></span>
                            <?php if ($enrollment): ?>
                            <a href="<?= url('formations/lesson/' . (int) $lesson['id'] . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="text-xs font-bold text-emerald-600 hover:underline">Voir</a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endforeach; ?>
            </div>
            <div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sticky top-24">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-4">Résumé</h3>
                    <p class="text-sm text-slate-600"><?= (int)($course['estimated_minutes'] ?? 0) ?> min</p>
                    <p class="text-sm text-slate-600 mt-1"><?= count($modules) ?> module(s)</p>
                    <?php if (!$enrollment): ?>
                    <p class="text-sm text-slate-500 mt-4">Inscrivez-vous pour suivre cette formation.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mt-12">
            <a href="<?= url('formations') ?>" class="text-sm font-bold text-slate-600 hover:text-slate-900">← Retour au catalogue</a>
        </div>
    </main>

    <footer class="border-t border-slate-200 py-8 mt-12">
        <div class="max-w-7xl mx-auto px-6 text-center text-xs text-slate-500">Athena — Formations</div>
    </footer>
</body>
</html>
