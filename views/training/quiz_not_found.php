<?php
declare(strict_types=1);
$base = url('');
$lmsTitle = 'Quiz indisponible';
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
            <div class="max-w-3xl mx-auto px-5 py-4">
                <a href="<?= htmlspecialchars(url('formations')) ?>" class="text-[11px] font-black uppercase tracking-wider text-slate-600 hover:text-emerald-700">← Formations</a>
            </div>
        </header>
        <main class="flex-1 flex items-center justify-center px-5 py-16">
            <div class="lms-panel rounded-[2rem] max-w-lg w-full p-8 md:p-10 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-violet-500 via-emerald-500/50 to-transparent rounded-t-[2rem]"></div>
                <p class="text-[10px] font-black tracking-[0.3em] uppercase text-slate-400 mb-3">Évaluation</p>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-3">Quiz introuvable</h1>
                <p class="text-slate-600 text-sm leading-relaxed mb-6">
                    Cette tentative n’existe pas, a expiré, ou ne vous appartient pas. Ouvrez le quiz depuis la fiche de votre formation.
                </p>
                <a href="<?= htmlspecialchars(url('formations')) ?>" class="inline-flex justify-center items-center px-6 py-3 bg-slate-900 text-white text-xs font-black uppercase rounded-xl hover:bg-emerald-700">Retour au catalogue</a>
            </div>
        </main>
    </div>
</body>
</html>
