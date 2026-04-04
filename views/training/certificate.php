<?php
$base = url('');
$certificate = $certificate ?? null;
if (!$certificate) {
    echo '<p>Certificat non trouvé.</p>';
    return;
}
$title = $title ?? 'Attestation';
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

    <main class="max-w-2xl mx-auto px-6 py-12">
        <div class="rounded-2xl border-2 border-slate-200 bg-white p-10 text-center">
            <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900">Attestation de formation</h1>
            <p class="mt-6 text-slate-600"><?= htmlspecialchars($certificate['course_title'] ?? 'Formation') ?></p>
            <p class="mt-2 text-sm text-slate-500">N° <?= htmlspecialchars($certificate['certificate_number'] ?? '') ?></p>
            <p class="mt-4 text-slate-600">Délivrée le <?= date('d/m/Y', strtotime($certificate['issued_at'] ?? 'now')) ?></p>
            <?php if (!empty($certificate['expires_at'])): ?>
            <p class="mt-1 text-sm text-slate-500">Valide jusqu’au <?= date('d/m/Y', strtotime($certificate['expires_at'])) ?></p>
            <?php endif; ?>
            <p class="mt-4 text-slate-700">Score final : <?= (float)($certificate['final_score'] ?? 0) ?> %</p>
            <p class="mt-2 text-sm text-slate-500">Statut : <?= htmlspecialchars($certificate['status'] ?? 'valid') ?></p>
            <?php if (!empty($certificate['pdf_path']) && is_file($certificate['pdf_path'])): ?>
            <a href="<?= url('api/training/certificates/' . (int)$certificate['id'] . '/download') ?>" class="inline-block mt-8 px-8 py-4 bg-slate-900 text-white font-bold uppercase rounded-xl hover:bg-emerald-600">Télécharger le PDF</a>
            <?php endif; ?>
        </div>
        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="<?= url('formations/mes-formations') ?>" class="text-emerald-600 hover:underline">Mes formations</a>
        </p>
    </main>
</body>
</html>
