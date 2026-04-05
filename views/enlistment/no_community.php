<?php
$base = url('');
$loginUrl = $loginUrl ?? url('login');
$joinUrl = $joinUrl ?? url('join');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrôlement — Aucune organisation</title>
    <?php $twCss = is_file(base_path('public/assets/css/tailwind.css')) ? url('assets/css/tailwind.css') : null; ?>
    <?php if ($twCss !== null): ?>
    <link href="<?= htmlspecialchars($twCss) ?>" rel="stylesheet">
    <?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900">
    <nav class="w-full bg-slate-900 text-white h-10 px-6 flex items-center justify-between sticky top-0 z-50">
        <span class="text-[9px] font-black tracking-[0.3em] text-amber-400">RECRUTEMENT</span>
        <a href="<?= htmlspecialchars($base) ?>/" class="text-[8px] font-mono text-white/50 hover:text-white tracking-widest uppercase">Accueil</a>
    </nav>
    <main class="max-w-2xl mx-auto py-16 px-6">
        <div class="bg-white border border-amber-200 rounded-3xl shadow-xl overflow-hidden">
            <div class="bg-amber-500/10 border-b border-amber-200 px-8 py-10">
                <p class="text-[9px] font-black tracking-[0.4em] text-amber-800 uppercase mb-2">Contexte manquant</p>
                <h1 class="text-2xl md:text-3xl font-black tracking-tight uppercase text-slate-900">Aucune unité sélectionnée</h1>
                <p class="mt-4 text-sm text-slate-600 leading-relaxed">
                    Ce portail d’enrôlement MilSim est lié à une <strong>communauté précise</strong>. Sans organisation cible, le dossier ne peut pas être ouvert.
                </p>
            </div>
            <div class="p-8 md:p-10 space-y-6 text-sm text-slate-700">
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Comment continuer</p>
                    <ul class="space-y-3 list-decimal list-inside">
                        <li>Utilisez le <strong>lien d’invitation</strong> ou le message que votre organisation vous a envoyé : c’est le moyen le plus simple d’arriver sur la bonne page d’enrôlement.</li>
                        <li>Si vous ne l’avez pas, <strong>contactez votre unité</strong> pour qu’on vous renvoie le bon lien.</li>
                        <li>Si vous avez déjà un compte, <strong>connectez-vous</strong> : vous pourrez choisir votre communauté parmi celles auxquelles vous appartenez.</li>
                    </ul>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="<?= htmlspecialchars($loginUrl) ?>" class="inline-flex justify-center px-6 py-3.5 bg-slate-900 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:bg-emerald-700 transition-colors">Connexion</a>
                    <a href="<?= htmlspecialchars($joinUrl) ?>" class="inline-flex justify-center px-6 py-3.5 border-2 border-slate-200 text-slate-800 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-50">Créer un compte</a>
                    <a href="<?= htmlspecialchars($base) ?>/" class="inline-flex justify-center px-6 py-3.5 text-slate-500 text-xs font-bold uppercase tracking-widest hover:text-slate-800">Accueil</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
