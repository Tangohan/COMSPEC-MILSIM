<?php
$base = url('');
$lesson = $lesson ?? null;
$enrollment = $enrollment ?? null;
$resources = $resources ?? [];
$progress = $progress ?? ['progress' => [], 'percent' => 0];
if (!$lesson || !$enrollment) {
    echo '<p>Leçon ou inscription non trouvée.</p>';
    return;
}
$lessonType = $lesson['lesson_type'] ?? 'richtext';
$csrf = \App\Core\Csrf::field();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lesson['title']) ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <style> body { font-family: 'Inter', sans-serif; } .prose { max-width: none; } </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

    <nav class="sticky top-0 z-[100] w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 h-14 flex items-center justify-between">
            <a href="<?= url('formations/' . ($enrollment['course_slug'] ?? '')) ?>" class="text-[11px] font-black tracking-[0.5em] uppercase hover:text-emerald-600">← Formation</a>
            <div class="flex items-center gap-4">
                <span class="text-xs font-bold text-slate-500"><?= (float)($progress['percent'] ?? 0) ?> %</span>
                <div class="w-32 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-600 rounded-full" style="width: <?= (float)($progress['percent'] ?? 0) ?>%"></div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-6 py-10">
        <article class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
            <div class="p-8">
                <h1 class="text-2xl font-black text-slate-900 mb-6"><?= htmlspecialchars($lesson['title']) ?></h1>

                <?php if ($lessonType === 'richtext' && !empty($lesson['content'])): ?>
                <div class="prose prose-slate max-w-none">
                    <?= $lesson['content'] ?>
                </div>
                <?php elseif ($lessonType === 'video' && !empty($lesson['external_url'])): ?>
                <div class="aspect-video rounded-xl overflow-hidden bg-slate-900">
                    <iframe src="<?= htmlspecialchars($lesson['external_url']) ?>" class="w-full h-full" allowfullscreen></iframe>
                </div>
                <?php elseif ($lessonType === 'external_link' && !empty($lesson['external_url'])): ?>
                <p class="mb-4">Lien externe :</p>
                <a href="<?= htmlspecialchars($lesson['external_url']) ?>" target="_blank" rel="noopener" class="text-emerald-600 font-bold hover:underline"><?= htmlspecialchars($lesson['external_url']) ?></a>
                <?php else: ?>
                <p class="text-slate-500">Contenu à afficher (type : <?= htmlspecialchars($lessonType) ?>).</p>
                <?php endif; ?>

                <?php if (!empty($resources)): ?>
                <div class="mt-10 pt-8 border-t border-slate-200">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 mb-3">Ressources</h3>
                    <ul class="space-y-2">
                        <?php foreach ($resources as $r): ?>
                        <li>
                            <?php if (!empty($r['file_path'])): ?>
                            <a href="<?= url('api/training/resource/' . (int)$r['id'] . '/download') ?>" class="text-emerald-600 hover:underline"><?= htmlspecialchars($r['title']) ?></a>
                            <?php elseif (!empty($r['external_url'])): ?>
                            <a href="<?= htmlspecialchars($r['external_url']) ?>" target="_blank" rel="noopener" class="text-emerald-600 hover:underline"><?= htmlspecialchars($r['title']) ?></a>
                            <?php else: ?>
                            <span class="text-slate-600"><?= htmlspecialchars($r['title']) ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="mt-10 flex flex-wrap gap-4">
                    <form method="post" action="<?= url('api/training/progress/lesson') ?>" class="inline" data-progress-lesson>
                        <?= $csrf ?>
                        <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
                        <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id'] ?>">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="px-6 py-3 bg-emerald-600 text-white text-sm font-bold uppercase rounded-xl hover:bg-emerald-700">Marquer comme terminé</button>
                    </form>
                    <a href="<?= url('formations/' . ($enrollment['course_slug'] ?? '')) ?>" class="px-6 py-3 border border-slate-300 text-slate-700 text-sm font-bold uppercase rounded-xl hover:bg-slate-100">Retour à la formation</a>
                </div>
            </div>
        </article>
    </main>
</body>
</html>
