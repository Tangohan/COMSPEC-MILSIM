<?php
declare(strict_types=1);
?>
<div class="max-w-lg mx-auto px-6 py-16">
    <h1 class="text-2xl font-black text-slate-900 mb-3">Accès au Studio LMS</h1>
    <p class="text-slate-600 leading-relaxed mb-6">
        Votre rôle ne dispose pas des permissions nécessaires pour ouvrir le Studio (création et édition des formations).
        Les profils avec <strong>assignation</strong>, <strong>gestion</strong> ou <strong>publication</strong> des formations y ont normalement accès.
    </p>
    <p class="text-sm text-slate-500 mb-8">Si vous pensez qu’il s’agit d’une erreur, contactez un administrateur de la communauté pour ajuster vos permissions.</p>
    <a href="<?= htmlspecialchars(url('admin/training'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">← Formations (admin)</a>
    <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="ml-3 text-sm font-semibold text-slate-600 underline hover:text-slate-900">Tableau de bord</a>
</div>
