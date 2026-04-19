<?php
declare(strict_types=1);
$base = url('');
$message = $message ?? 'Une erreur est survenue lors de la soumission de votre candidature.';
$enlistmentRetryUrl = $enlistmentRetryUrl ?? url('enlistment');
$errorContext = $errorContext ?? null;
$isPortalSuspended = $errorContext === 'portal_access_suspended';
$pageTitle = $isPortalSuspended ? 'Suivi en ligne indisponible — Athena' : 'Erreur de soumission — Athena';
$heading = $isPortalSuspended ? 'Accès au suivi suspendu' : 'Erreur';
$badge = $isPortalSuspended ? 'Suivi candidature' : 'Soumission interrompue';
$badgeClass = $isPortalSuspended ? 'text-amber-700' : 'text-rose-600';
$topBarClass = $isPortalSuspended ? 'bg-amber-500/10 border-b border-amber-200' : 'bg-rose-500/10 border-b border-rose-200';
$iconBg = $isPortalSuspended ? 'bg-amber-500' : 'bg-rose-500';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
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
            <a href="<?= htmlspecialchars($enlistmentRetryUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-[8px] font-mono text-white/40 hover:text-white tracking-widest uppercase">Enrôlement</a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto py-16 px-6">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-xl overflow-hidden text-center">
            <div class="<?= htmlspecialchars($topBarClass, ENT_QUOTES, 'UTF-8') ?> px-8 py-10">
                <div class="w-16 h-16 mx-auto rounded-full <?= htmlspecialchars($iconBg, ENT_QUOTES, 'UTF-8') ?> flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <?php if ($isPortalSuspended): ?>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        <?php else: ?>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        <?php endif; ?>
                    </svg>
                </div>
                <p class="text-[9px] font-black tracking-[0.4em] <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?> uppercase mb-2"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></p>
                <h1 class="text-2xl md:text-3xl font-black tracking-tighter uppercase text-slate-900"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="p-8 md:p-12 space-y-6 text-left">
                <p class="text-slate-600 leading-relaxed font-medium">
                    <?= htmlspecialchars($message) ?>
                </p>
                <?php if ($isPortalSuspended): ?>
                <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl">
                    <p class="text-[10px] font-black text-amber-800 uppercase tracking-wider mb-2">Pour la suite</p>
                    <ul class="text-sm text-amber-950/90 space-y-2 list-disc list-inside">
                        <li>Contactez l’équipe recrutement par un moyen que vous avez déjà utilisé avec eux (courriel, forum, etc.).</li>
                        <li>Indiquez-leur que le <strong>lien de suivi</strong> ne s’ouvre plus : ils peuvent rétablir l’accès depuis leur espace d’administration.</li>
                        <li>Si le lien avait une date limite, ils pourront vous en envoyer un <strong>nouveau</strong> une fois l’accès rétabli.</li>
                    </ul>
                </div>
                <?php else: ?>
                <div class="bg-slate-50 border-l-4 border-slate-900 p-4 rounded-r-xl">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-wider mb-2">Que faire ?</p>
                    <ul class="text-sm text-slate-600 space-y-1 list-disc list-inside">
                        <li>Vérifiez que la case « absence d’IA » est bien cochée si vous renvoyez le formulaire</li>
                        <li>Rechargez la page et remplissez à nouveau le formulaire</li>
                        <li>En cas de persistance, contactez le support indiqué sur la page d’accueil</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <div class="px-8 pb-10 flex flex-col sm:flex-row gap-3 justify-center">
                <?php if (!$isPortalSuspended): ?>
                <a href="<?= htmlspecialchars($enlistmentRetryUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-block px-8 py-4 bg-slate-900 text-white rounded-xl font-black tracking-[0.3em] uppercase hover:bg-slate-800 transition-all text-center">Réessayer le formulaire</a>
                <?php endif; ?>
                <a href="<?= $base ?>/" class="inline-block px-8 py-4 border-2 border-slate-200 text-slate-700 rounded-xl font-black tracking-[0.2em] uppercase hover:bg-slate-50 transition-all text-center">Retour à l'accueil</a>
            </div>
        </div>
    </main>
</body>
</html>
