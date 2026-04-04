<?php
$base = url('');
$attemptId = $attemptId ?? 0;
$title = $title ?? 'Quiz';
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
    <nav class="sticky top-0 z-[100] w-full bg-white border-b border-slate-200 px-6 h-14 flex items-center">
        <a href="<?= url('formations') ?>" class="text-[11px] font-black uppercase tracking-wider hover:text-emerald-600">← Formations</a>
    </nav>
    <main class="max-w-3xl mx-auto px-6 py-10">
        <div id="quiz-app" data-attempt-id="<?= (int) $attemptId ?>" data-base="<?= htmlspecialchars($base) ?>" data-csrf="<?= htmlspecialchars(\App\Core\Csrf::field()) ?>">
            <p class="text-slate-500">Chargement du quiz…</p>
        </div>
    </main>
    <script>
    (function() {
        const el = document.getElementById('quiz-app');
        if (!el) return;
        const attemptId = el.dataset.attemptId;
        const base = el.dataset.base || '';
        const csrf = (el.dataset.csrf || '').match(/value="([^"]+)"/);
        const csrfToken = csrf ? csrf[1] : '';
        fetch(base + '/api/training/quiz/attempts/' + attemptId, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    el.innerHTML = '<p class="text-rose-600">' + (data.error || 'Erreur') + '</p>';
                    return;
                }
                const attempt = data;
                if (attempt.status !== 'in_progress') {
                    el.innerHTML = '<div class="rounded-2xl border border-slate-200 bg-white p-8"><h2 class="text-xl font-bold text-slate-900">Résultat</h2><p class="mt-4">Score : ' + (attempt.score || 0) + ' %</p><p class="mt-2">' + (attempt.passed ? 'Réussi' : 'Non réussi') + '</p><a href="' + base + '/formations" class="inline-block mt-6 px-6 py-3 bg-slate-900 text-white rounded-xl">Retour</a></div>';
                    return;
                }
                const questions = (attempt.responses || []).map(r => r.question_id);
                el.innerHTML = '<p class="text-slate-600">Quiz en cours. Utilisez l’API pour soumettre les réponses (attempt_id: ' + attemptId + ').</p><a href="' + base + '/formations" class="inline-block mt-4 text-emerald-600 font-bold">Retour aux formations</a>';
            })
            .catch(() => { el.innerHTML = '<p class="text-rose-600">Impossible de charger le quiz.</p>'; });
    })();
    </script>
</body>
</html>
