<?php
$base = url('');
$certificate = $certificate ?? null;
if (!$certificate) {
    echo '<p>Certificat non trouvé.</p>';
    return;
}
$title = $title ?? 'Attestation';
$publicConsultationView = !empty($publicConsultationView);
$consultationApiUrl = $consultationApiUrl ?? '';
$og_url = $og_url ?? null;
$og_title = $og_title ?? null;
$og_description = $og_description ?? null;

$pdfRel = (string) ($certificate['pdf_path'] ?? '');
$pdfFull = '';
if ($pdfRel !== '') {
    $pdfFull = (!str_starts_with($pdfRel, '/') && !preg_match('#^[A-Za-z]:#', $pdfRel)) ? base_path($pdfRel) : $pdfRel;
}

$statusRaw = (string) ($certificate['status'] ?? 'valid');
$statusFr = match ($statusRaw) {
    'valid' => 'Valide',
    'expired' => 'Expirée',
    'revoked' => 'Retirée',
    default => $statusRaw,
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <?php if (is_string($og_url) && $og_url !== '' && is_string($og_title) && $og_title !== ''): ?>
    <meta property="og:url" content="<?= htmlspecialchars($og_url) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>">
    <?php if (is_string($og_description) && $og_description !== ''): ?>
    <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
    <?php endif; ?>
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

    <nav class="sticky top-0 z-[100] w-full bg-white border-b border-slate-200 px-6 h-14 flex items-center">
        <?php if ($publicConsultationView): ?>
            <a href="<?= htmlspecialchars(url('')) ?>" class="text-[11px] font-black uppercase tracking-wider hover:text-emerald-600">Portail</a>
        <?php else: ?>
            <a href="<?= url('formations') ?>" class="text-[11px] font-black uppercase tracking-wider hover:text-emerald-600">← Formations</a>
        <?php endif; ?>
    </nav>

    <main class="max-w-2xl mx-auto px-6 py-12">
        <div class="rounded-2xl border-2 border-slate-200 bg-white p-10 text-center">
            <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900">Attestation de formation</h1>
            <p class="mt-6 text-slate-600"><?= htmlspecialchars($certificate['course_title'] ?? 'Formation') ?></p>
            <p class="mt-2 text-sm text-slate-500">Référence <?= htmlspecialchars($certificate['certificate_number'] ?? '') ?></p>
            <p class="mt-4 text-slate-600">Délivrée le <?= date('d/m/Y', strtotime($certificate['issued_at'] ?? 'now')) ?></p>
            <?php if (!empty($certificate['expires_at'])): ?>
            <p class="mt-1 text-sm text-slate-500">Valide jusqu’au <?= date('d/m/Y', strtotime($certificate['expires_at'])) ?></p>
            <?php endif; ?>
            <p class="mt-4 text-slate-700">Score final : <?= (float)($certificate['final_score'] ?? 0) ?> %</p>
            <p class="mt-2 text-sm text-slate-500">Statut : <?= htmlspecialchars($statusFr) ?></p>
            <?php if (!$publicConsultationView && $pdfFull !== '' && is_file($pdfFull)): ?>
            <a href="<?= url('api/training/certificates/' . (int)$certificate['id'] . '/download') ?>" class="inline-block mt-8 px-8 py-4 bg-slate-900 text-white font-bold uppercase rounded-xl hover:bg-emerald-600">Télécharger le PDF</a>
            <?php elseif ($publicConsultationView): ?>
            <p class="mt-8 text-sm text-slate-500">Le téléchargement du fichier est disponible depuis votre espace connecté.</p>
            <?php endif; ?>

            <?php if (!$publicConsultationView && $consultationApiUrl !== '' && $statusRaw === 'valid'): ?>
            <div class="mt-10 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left">
                <p class="text-sm font-semibold text-slate-800">Partager une consultation</p>
                <p class="mt-1 text-xs text-slate-600">Générez un lien temporaire (environ trois mois) pour qu’un tiers puisse consulter cette page sans se connecter.</p>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input type="text" readonly id="consultation-link-out" class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700" placeholder="Le lien apparaîtra ici">
                    <button type="button" id="consultation-link-btn" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold uppercase text-white hover:bg-emerald-700">Obtenir le lien</button>
                </div>
                <p class="mt-2 text-[11px] text-slate-500" id="consultation-link-hint" hidden></p>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('consultation-link-btn');
                var out = document.getElementById('consultation-link-out');
                var hint = document.getElementById('consultation-link-hint');
                if (!btn || !out) return;
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    fetch(<?= json_encode($consultationApiUrl) ?>, { credentials: 'same-origin' })
                        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                        .then(function (x) {
                            if (!x.ok || !x.j || !x.j.consultation_url) {
                                throw new Error(x.j && x.j.error ? x.j.error : 'Erreur');
                            }
                            out.value = x.j.consultation_url;
                            if (hint && x.j.expires_at) {
                                var d = new Date(x.j.expires_at * 1000);
                                hint.textContent = 'Valide jusqu’au ' + d.toLocaleDateString('fr-FR') + '.';
                                hint.hidden = false;
                            }
                        })
                        .catch(function () {
                            out.value = '';
                            alert('Impossible de générer le lien pour le moment.');
                        })
                        .finally(function () { btn.disabled = false; });
                });
            })();
            </script>
            <?php endif; ?>
        </div>
        <?php if (!$publicConsultationView): ?>
        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="<?= url('formations/mes-formations') ?>" class="text-emerald-600 hover:underline">Mes formations</a>
        </p>
        <?php endif; ?>
    </main>
</body>
</html>
