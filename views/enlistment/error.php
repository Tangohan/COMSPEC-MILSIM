<?php
$base = url('');
$message = $message ?? 'Une erreur est survenue lors de la soumission de votre candidature.';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur de soumission — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #0f172a; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

    <nav class="w-full bg-slate-900 text-white h-10 px-6 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-6">
            <span class="text-[9px] font-black tracking-[0.3em] text-emerald-400">JNET v2.4.0</span>
            <div class="h-4 w-[1px] bg-white/10"></div>
            <a href="<?= $base ?>/" class="text-[8px] font-mono text-white/40 hover:text-white tracking-widest uppercase">Accueil</a>
            <a href="<?= $base ?>/enlistment" class="text-[8px] font-mono text-white/40 hover:text-white tracking-widest uppercase">Enrôlement</a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto py-16 px-6">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-xl overflow-hidden text-center">
            <div class="bg-rose-500/10 border-b border-rose-200 px-8 py-10">
                <div class="w-16 h-16 mx-auto rounded-full bg-rose-500 flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <p class="text-[9px] font-black tracking-[0.4em] text-rose-600 uppercase mb-2">Soumission interrompue</p>
                <h1 class="text-2xl md:text-3xl font-black tracking-tighter uppercase text-slate-900">Erreur</h1>
            </div>
            <div class="p-8 md:p-12 space-y-6 text-left">
                <p class="text-slate-600 leading-relaxed font-medium">
                    <?= htmlspecialchars($message) ?>
                </p>
                <div class="bg-slate-50 border-l-4 border-slate-900 p-4 rounded-r-xl">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-wider mb-2">Que faire ?</p>
                    <ul class="text-sm text-slate-600 space-y-1 list-disc list-inside">
                        <li>Vérifiez que la case « absence d’IA » est bien cochée si vous renvoyez le formulaire</li>
                        <li>Rechargez la page et remplissez à nouveau le formulaire</li>
                        <li>En cas de persistance, contactez le support indiqué sur la page d’accueil</li>
                    </ul>
                </div>
            </div>
            <div class="px-8 pb-10 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= $base ?>/enlistment" class="inline-block px-8 py-4 bg-slate-900 text-white rounded-xl font-black tracking-[0.3em] uppercase hover:bg-slate-800 transition-all text-center">Réessayer le formulaire</a>
                <a href="<?= $base ?>/" class="inline-block px-8 py-4 border-2 border-slate-200 text-slate-700 rounded-xl font-black tracking-[0.2em] uppercase hover:bg-slate-50 transition-all text-center">Retour à l'accueil</a>
            </div>
        </div>
    </main>
</body>
</html>
