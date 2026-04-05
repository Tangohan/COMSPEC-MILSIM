<?php
$base = url('');
$communitySlug = $communitySlug ?? null;
$enlistHref = $communitySlug ? $base . '/c/' . rawurlencode((string) $communitySlug) . '/enlistment' : $base . '/enlistment';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidature enregistrée — Athena</title>
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
            <a href="<?= htmlspecialchars($enlistHref) ?>" class="text-[8px] font-mono text-white/40 hover:text-white tracking-widest uppercase">Enrôlement</a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto py-16 px-6">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-xl overflow-hidden text-center">
            <div class="bg-emerald-500/10 border-b border-emerald-200 px-8 py-10">
                <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500 flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-[9px] font-black tracking-[0.4em] text-emerald-600 uppercase mb-2">Transmission réussie</p>
                <h1 class="text-2xl md:text-3xl font-black tracking-tighter uppercase text-slate-900">Candidature enregistrée</h1>
            </div>
            <div class="p-8 md:p-12 space-y-6 text-left">
                <p class="text-slate-600 leading-relaxed font-medium">
                    Votre dossier a bien été reçu par la cellule de recrutement. Il sera examiné dans les meilleurs délais.
                </p>
                <div class="bg-slate-50 border-l-4 border-slate-900 p-4 rounded-r-xl">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-wider mb-2">Prochaines étapes</p>
                    <ul class="text-sm text-slate-600 space-y-1 list-disc list-inside">
                        <li>Examen de votre dossier par le commandement</li>
                        <li>Contact par email en cas de suite favorable</li>
                        <li>Merci de ne pas relancer l’état-major ; les délais peuvent varier</li>
                    </ul>
                </div>
                <p class="text-slate-500 text-sm italic">
                    Nous vous recontacterons à l’adresse email indiquée dans le formulaire.
                </p>
            </div>
            <div class="px-8 pb-10">
                <a href="<?= $base ?>/" class="inline-block w-full md:w-auto px-8 py-4 bg-slate-900 text-white rounded-xl font-black tracking-[0.3em] uppercase hover:bg-emerald-600 transition-all text-center">Retour à l'accueil</a>
            </div>
        </div>
    </main>
</body>
</html>
