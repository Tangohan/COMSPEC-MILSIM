<?php
declare(strict_types=1);
?>
<section class="training-studio-intro training-studio-panel mb-6" aria-labelledby="training-studio-intro-title">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0 flex-1">
            <p class="text-[0.5625rem] font-extrabold uppercase tracking-[0.28em] text-emerald-700 mb-2">Espace auteur dédié</p>
            <h2 id="training-studio-intro-title" class="text-lg md:text-xl font-black tracking-tight text-slate-900 mb-2">
                Studio LMS — mode application externe
            </h2>
            <p class="text-sm text-slate-600 leading-relaxed max-w-3xl">
                Cette interface est un <strong class="font-semibold text-slate-800">atelier de conception</strong> séparé du portail : vous y structurez les parcours, les modules et les leçons sans la navigation habituelle du site.
                Une fois publié, le parcours rejoint le <a href="<?= htmlspecialchars(url('formations')) ?>" class="text-emerald-700 font-semibold underline-offset-2 hover:underline">catalogue formations</a> pour les apprenants.
            </p>
        </div>
        <div class="shrink-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600 max-w-md">
            <p class="font-bold text-slate-800 mb-1">Raccourcis</p>
            <ul class="list-disc pl-4 space-y-1">
                <li>Utilisez le menu (☰) pour replier le panneau latéral et gagner de la place.</li>
                <li>Les modifications sont enregistrées dans le contexte de votre communauté.</li>
            </ul>
        </div>
    </div>
</section>
