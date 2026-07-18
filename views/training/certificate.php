<?php
$base = url('');
$certificate = $certificate ?? null;
if (!$certificate) {
    echo '<p>Certificat non trouvé.</p>';
    return;
}
$title = $title ?? 'Attestation';
$publicConsultationView = !empty($publicConsultationView);
/** @var array{operator_display_name: string, callsign: string, unit_label: string, portrait_url: string}|null */
$attestationSharePersonnel = $attestationSharePersonnel ?? null;
$consultationApiUrl = $consultationApiUrl ?? '';
$og_url = $og_url ?? null;
$og_title = $og_title ?? null;
$og_description = $og_description ?? null;
$appDisplayName = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$tailwindBaseUrl = $base;

$pdfRel = (string) ($certificate['pdf_path'] ?? '');
$pdfFull = '';
if ($pdfRel !== '') {
    $pdfFull = (!str_starts_with($pdfRel, '/') && !preg_match('#^[A-Za-z]:#', $pdfRel)) ? base_path($pdfRel) : $pdfRel;
}
$pdfReadyOnDisk = $pdfFull !== '' && is_file($pdfFull);

$statusRaw = (string) ($certificate['status'] ?? 'valid');
$statusFr = match ($statusRaw) {
    'valid' => 'Valide',
    'expired' => 'Expirée',
    'revoked' => 'Retirée',
    default => $statusRaw,
};
$isCelebration = $statusRaw === 'valid';
$certificateId = (int) ($certificate['id'] ?? 0);
$downloadUrl = $certificateId > 0
    ? url('api/training/certificates/' . $certificateId . '/download')
    : '';
// Toujours proposer le téléchargement au titulaire si l’attestation est valide :
// l’API régénère le document à la demande si le fichier manque encore.
$canDownloadDocument = !$publicConsultationView && $downloadUrl !== '' && ($statusRaw === 'valid' || $pdfReadyOnDisk);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($appDisplayName) ?></title>
    <?php if (is_string($og_url) && $og_url !== '' && is_string($og_title) && $og_title !== ''): ?>
    <meta property="og:url" content="<?= htmlspecialchars($og_url) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>">
    <?php if (is_string($og_description) && $og_description !== ''): ?>
    <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
    <?php endif; ?>
    <?php endif; ?>
    <?php require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* Page autonome : ne pas charger styles.css du portail (thème sombre + ::before en mix-blend),
           sinon sans utilitaires Tailwind le texte clair hérité devient illisible sur fond clair / zones sans bordure. */
        body { font-family: 'Inter', system-ui, sans-serif; margin: 0; }
        @keyframes cert-fade-up {
            from { transform: translateY(14px); }
            to { transform: translateY(0); }
        }
        @keyframes cert-glow {
            0%, 100% { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(251, 191, 36, 0.15); }
            50% { box-shadow: 0 25px 50px -12px rgba(16, 185, 129, 0.2), 0 0 40px rgba(251, 191, 36, 0.12); }
        }
        @keyframes cert-shimmer {
            0% { background-position: 200% center; }
            100% { background-position: -200% center; }
        }
        /* Animation sans opacity:0 initial — évite une carte « vide » si l’animation est bloquée ou en conflit CSS. */
        .cert-animate-in {
            opacity: 1;
            animation: cert-fade-up 0.75s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .cert-card-glow { animation: cert-glow 4s ease-in-out infinite; }
        .cert-title-shine {
            color: #0f172a;
        }
        @supports ((-webkit-background-clip: text) or (background-clip: text)) {
            .cert-title-shine {
                background: linear-gradient(105deg, #0f172a 0%, #0f172a 40%, #059669 50%, #0f172a 60%, #0f172a 100%);
                background-size: 200% auto;
                -webkit-background-clip: text;
                background-clip: text;
                -webkit-text-fill-color: transparent;
                color: transparent;
                animation: cert-shimmer 3.5s linear infinite;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .cert-animate-in, .cert-card-glow, .cert-title-shine { animation: none !important; }
            .cert-title-shine { color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background: none !important; }
        }
    </style>
    <?php if ($isCelebration): ?>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js" defer></script>
    <?php endif; ?>
</head>
<body class="min-h-screen text-slate-100 relative overflow-x-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950">

    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(16,185,129,0.18),transparent)]"></div>
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_100%_100%,rgba(251,191,36,0.08),transparent_50%)]"></div>

    <nav class="relative z-[100] w-full border-b border-white/10 bg-slate-950/80 backdrop-blur-md px-6 h-14 flex items-center">
        <?php if ($publicConsultationView): ?>
            <a href="<?= htmlspecialchars(url('')) ?>" class="text-[11px] font-black uppercase tracking-wider text-emerald-400 hover:text-emerald-300 transition-colors">Portail</a>
        <?php else: ?>
            <a href="<?= url('formations') ?>" class="text-[11px] font-black uppercase tracking-wider text-emerald-400 hover:text-emerald-300 transition-colors">← Formations</a>
        <?php endif; ?>
    </nav>

    <main class="relative z-10 max-w-2xl mx-auto px-6 py-10 sm:py-14">
        <div class="cert-animate-in rounded-3xl border border-amber-400/25 bg-white text-slate-900 p-8 sm:p-12 text-center cert-card-glow">
            <?php if ($isCelebration): ?>
            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-emerald-800 ring-1 ring-emerald-200/80 mb-6">
                <span class="text-lg leading-none" aria-hidden="true">🎉</span>
                Félicitations
            </div>
            <?php endif; ?>

            <?php if ($publicConsultationView && is_array($attestationSharePersonnel)): ?>
            <div class="mb-8 pb-8 border-b border-slate-200 text-left w-full">
                <p class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-800">Identité opérationnelle</p>
                <p class="text-xs text-slate-500 mt-1.5 mb-5 leading-relaxed">Personnage et affectation tels qu’enregistrés sur la fiche personnelle du portail (reconstitution).</p>
                <div class="flex flex-col sm:flex-row sm:items-center gap-5 sm:gap-6">
                    <?php if (!empty($attestationSharePersonnel['portrait_url'])): ?>
                    <div class="shrink-0 mx-auto sm:mx-0 w-28 h-28 rounded-2xl overflow-hidden border-2 border-emerald-200/90 shadow-md bg-slate-100">
                        <img src="<?= htmlspecialchars((string) $attestationSharePersonnel['portrait_url']) ?>" alt="" class="w-full h-full object-cover" width="112" height="112" loading="lazy" decoding="async">
                    </div>
                    <?php endif; ?>
                    <div class="min-w-0 flex-1 text-center sm:text-left">
                        <?php
                        $shareOpName = trim((string) ($attestationSharePersonnel['operator_display_name'] ?? ''));
                        ?>
                        <?php if ($shareOpName !== ''): ?>
                        <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight tracking-tight"><?= htmlspecialchars($shareOpName) ?></p>
                        <?php else: ?>
                        <p class="text-sm text-slate-600 leading-relaxed">L’opérateur n’a pas encore renseigné de nom de personnage sur sa fiche ; les informations ci-dessous peuvent compléter la présentation.</p>
                        <?php endif; ?>
                        <?php if (trim((string) ($attestationSharePersonnel['callsign'] ?? '')) !== ''): ?>
                        <p class="mt-2 text-sm font-bold text-slate-800">Indicatif <?= htmlspecialchars(trim((string) $attestationSharePersonnel['callsign'])) ?></p>
                        <?php endif; ?>
                        <?php if (trim((string) ($attestationSharePersonnel['unit_label'] ?? '')) !== ''): ?>
                        <p class="mt-1.5 text-xs font-semibold text-slate-600">Unité : <?= htmlspecialchars(trim((string) $attestationSharePersonnel['unit_label'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <p class="text-sm font-semibold text-emerald-700 uppercase tracking-widest mb-2">Attestation de formation</p>
            <h1 class="text-2xl sm:text-3xl font-black uppercase tracking-tight leading-tight <?= $isCelebration ? 'cert-title-shine' : 'text-slate-900' ?>">
                <?= $isCelebration ? 'Parcours validé' : 'Votre attestation' ?>
            </h1>
            <p class="mt-6 text-lg text-slate-700 font-medium"><?= htmlspecialchars($certificate['course_title'] ?? 'Formation') ?></p>
            <p class="mt-2 text-sm text-slate-500">Référence <?= htmlspecialchars($certificate['certificate_number'] ?? '') ?></p>
            <p class="mt-5 text-slate-600">Délivrée le <?= date('d/m/Y', strtotime($certificate['issued_at'] ?? 'now')) ?></p>
            <?php if (!empty($certificate['expires_at'])): ?>
            <p class="mt-1 text-sm text-slate-500">Valide jusqu’au <?= date('d/m/Y', strtotime($certificate['expires_at'])) ?></p>
            <?php endif; ?>
            <p class="mt-4 text-slate-800 font-semibold">Score final : <?= (float)($certificate['final_score'] ?? 0) ?> %</p>
            <p class="mt-2 text-sm text-slate-500">Statut : <?= htmlspecialchars($statusFr) ?></p>

            <?php if ($publicConsultationView && is_array($attestationSharePersonnel)):
                $honorName = trim((string) ($attestationSharePersonnel['operator_display_name'] ?? ''));
                $courseNamed = htmlspecialchars((string) ($certificate['course_title'] ?? 'Formation'));
                ?>
            <div class="mt-8 rounded-2xl border border-emerald-100 bg-gradient-to-b from-emerald-50/90 to-white p-6 sm:p-7 text-left shadow-sm">
                <?php if ($honorName !== ''): ?>
                <p class="text-sm sm:text-[15px] text-slate-800 leading-relaxed">
                    Ce document atteste que <strong class="text-slate-900"><?= htmlspecialchars($honorName) ?></strong> a validé le parcours «&nbsp;<?= $courseNamed ?>&nbsp;» avec sérieux et rigueur. Cette réussite témoigne d’une bonne maîtrise des enseignements et d’un engagement soutenu tout au long du parcours.
                </p>
                <?php else: ?>
                <p class="text-sm sm:text-[15px] text-slate-800 leading-relaxed">
                    Ce document atteste que le titulaire a validé le parcours «&nbsp;<?= $courseNamed ?>&nbsp;» avec sérieux et rigueur. Cette réussite témoigne d’une bonne maîtrise des enseignements et d’un engagement soutenu tout au long du parcours.
                </p>
                <?php endif; ?>
                <p class="mt-4 text-sm text-slate-700 leading-relaxed">
                    Une telle démarche honore l’opérateur et renforce la confiance au sein du collectif : elle reflète à la fois le respect des exigences pédagogiques et la volonté de servir avec constance la cohésion de la communauté.
                </p>
            </div>
            <?php endif; ?>

            <?php if ($canDownloadDocument): ?>
            <div class="mt-8 flex flex-col items-center gap-3">
                <a href="<?= htmlspecialchars($downloadUrl) ?>" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-8 py-4 text-sm font-black uppercase tracking-wider text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2" download>
                    <svg class="h-5 w-5 shrink-0 opacity-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Télécharger mon document
                </a>
                <p class="text-xs text-slate-500">Fichier à conserver (format PDF).</p>
            </div>
            <?php elseif ($publicConsultationView): ?>
            <p class="mt-8 text-sm text-slate-500">Le téléchargement du document est disponible depuis l’espace connecté du titulaire.</p>
            <?php endif; ?>

            <?php if (!$publicConsultationView && $consultationApiUrl !== '' && $statusRaw === 'valid'): ?>
            <div class="mt-10 rounded-2xl border border-slate-200 bg-gradient-to-b from-slate-50 to-white p-5 text-left shadow-inner">
                <p class="text-sm font-semibold text-slate-800">Partager une consultation</p>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">Générez un lien temporaire (environ trois mois) pour qu’un tiers puisse consulter cette page sans se connecter.</p>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                    <input type="text" readonly id="consultation-link-out" class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-700" placeholder="Le lien apparaîtra ici">
                    <div class="flex gap-2 shrink-0">
                        <button type="button" id="consultation-copy-btn" class="hidden rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold uppercase text-slate-700 hover:bg-slate-50 transition-colors" title="Copier dans le presse-papiers">Copier</button>
                        <button type="button" id="consultation-link-btn" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold uppercase text-white hover:bg-emerald-700 transition-colors whitespace-nowrap">Obtenir le lien</button>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-slate-500" id="consultation-link-hint" hidden></p>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('consultation-link-btn');
                var out = document.getElementById('consultation-link-out');
                var hint = document.getElementById('consultation-link-hint');
                var copyBtn = document.getElementById('consultation-copy-btn');
                if (!btn || !out) return;
                function showCopy() {
                    if (copyBtn && out.value) copyBtn.classList.remove('hidden');
                }
                if (copyBtn) {
                    copyBtn.addEventListener('click', function () {
                        if (!out.value) return;
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(out.value).then(function () {
                                copyBtn.textContent = 'Copié !';
                                setTimeout(function () { copyBtn.textContent = 'Copier'; }, 2000);
                            }).catch(function () {});
                        }
                    });
                }
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    fetch(<?= json_encode($consultationApiUrl) ?>, { credentials: 'same-origin' })
                        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                        .then(function (x) {
                            if (!x.ok || !x.j || !x.j.consultation_url) {
                                throw new Error(x.j && x.j.error ? x.j.error : 'Erreur');
                            }
                            out.value = x.j.consultation_url;
                            showCopy();
                            if (hint && x.j.expires_at) {
                                var d = new Date(x.j.expires_at * 1000);
                                hint.textContent = 'Ce lien reste utilisable jusqu’au ' + d.toLocaleDateString('fr-FR') + '.';
                                hint.hidden = false;
                            }
                            if (typeof confetti === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                confetti({ particleCount: 90, spread: 65, origin: { y: 0.72 }, colors: ['#10b981', '#fbbf24', '#34d399', '#ffffff'] });
                            }
                        })
                        .catch(function () {
                            out.value = '';
                            if (copyBtn) copyBtn.classList.add('hidden');
                            alert('Impossible de générer le lien pour le moment. Réessayez plus tard ou contactez le support si le problème continue.');
                        })
                        .finally(function () { btn.disabled = false; });
                });
            })();
            </script>
            <?php endif; ?>

            <div class="mt-10 pt-8 border-t border-slate-200 text-left">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Document</p>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Attestation générée par <span class="font-semibold text-slate-600"><?= htmlspecialchars($appDisplayName) ?></span>.
                    Conservez votre référence pour toute vérification auprès de votre organisation.
                </p>
                <?php if ($canDownloadDocument): ?>
                <a href="<?= htmlspecialchars($downloadUrl) ?>" class="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-emerald-700 hover:text-emerald-800 transition-colors" download>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Enregistrer le document
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$publicConsultationView): ?>
        <p class="mt-8 text-center text-sm text-slate-400">
            <a href="<?= url('formations/mes-formations') ?>" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">Mes formations</a>
        </p>
        <?php endif; ?>
    </main>

    <?php if ($isCelebration): ?>
    <script>
    window.addEventListener('load', function () {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        if (typeof confetti !== 'function') return;
        var t = setTimeout(function () {
            confetti({ particleCount: 100, spread: 80, origin: { y: 0.35 }, colors: ['#10b981', '#fbbf24', '#a7f3d0', '#fde68a'] });
        }, 400);
        setTimeout(function () {
            confetti({ particleCount: 60, angle: 60, spread: 55, origin: { x: 0, y: 0.65 }, colors: ['#34d399', '#fbbf24'] });
            confetti({ particleCount: 60, angle: 120, spread: 55, origin: { x: 1, y: 0.65 }, colors: ['#34d399', '#fbbf24'] });
        }, 650);
    });
    </script>
    <?php endif; ?>
</body>
</html>
