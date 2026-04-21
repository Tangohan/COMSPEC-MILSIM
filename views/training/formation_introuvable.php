<?php
declare(strict_types=1);
$slug = trim((string) ($slug ?? ''));
$context = (string) ($context ?? 'fiche');
$base = url('');
$lmsTitle = 'Formation introuvable';
$lmsBase = $base;
$lmsThemeVars = '';
$lmsExtraHead = '';
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen overflow-x-hidden">
    <div class="lms-grain"></div>
    <div class="min-h-screen relative z-10 flex flex-col">
        <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
            <div class="max-w-3xl mx-auto px-5 py-4 flex items-center justify-between gap-4">
                <a href="<?= htmlspecialchars(url('formations')) ?>" class="text-[11px] font-black uppercase tracking-wider text-slate-600 hover:text-emerald-700">← Formations</a>
                <a href="<?= htmlspecialchars(url('')) ?>" class="text-[11px] font-bold text-slate-500 hover:text-slate-800">Portail</a>
            </div>
        </header>
        <main class="flex-1 flex items-center justify-center px-5 py-16">
            <div class="lms-panel rounded-[2rem] max-w-lg w-full p-8 md:p-10 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 via-amber-400/40 to-transparent rounded-t-[2rem]"></div>
                <p class="text-[10px] font-black tracking-[0.3em] uppercase text-slate-400 mb-3">Formations</p>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-3">Contenu non disponible</h1>
                <p class="text-slate-600 text-sm leading-relaxed mb-6">
                    <?php if ($context === 'echanges'): ?>
                    Impossible d’afficher les avis et échanges : cette formation n’existe pas dans votre communauté, ou n’est plus publiée.
                    <?php elseif ($context === 'documentation'): ?>
                    Cette documentation HTML n’existe pas, n’est pas encore publiée, ou n’est plus disponible pour votre communauté.
                    <?php else: ?>
                    Cette formation n’existe pas dans votre communauté, ou n’est plus accessible.
                    <?php endif; ?>
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?= htmlspecialchars(url('formations')) ?>" class="inline-flex justify-center items-center px-6 py-3 bg-emerald-600 text-white text-xs font-black uppercase rounded-xl hover:bg-emerald-700">Catalogue</a>
                    <a href="<?= htmlspecialchars(url('formations/mes-formations')) ?>" class="inline-flex justify-center items-center px-6 py-3 border border-slate-200 text-slate-800 text-xs font-black uppercase rounded-xl hover:bg-slate-50">Mes parcours</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
