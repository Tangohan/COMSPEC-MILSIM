<?php
declare(strict_types=1);
?>
<div class="mx-auto max-w-3xl px-6 py-16">
    <h1 class="text-3xl font-black tracking-tight text-slate-900">Recrutement sur Athena</h1>
    <p class="mt-4 text-slate-600 leading-relaxed">
        Les candidatures se font au niveau de chaque communauté : chaque unité publie ses propres avis sur sa page publique et son formulaire d’enrôlement.
    </p>
    <div class="mt-10 space-y-4 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p class="text-sm font-semibold text-slate-900">Pour postuler</p>
        <ul class="list-disc pl-5 text-sm text-slate-600 space-y-2">
            <li>Ouvrez la <strong>fiche publique</strong> de la communauté qui vous intéresse (lien ou code communiqué par l’unité).</li>
            <li>Consultez la section <strong>« Prospection opérationnelle »</strong> si des offres sont publiées.</li>
            <li>Utilisez le bouton <strong>Candidater</strong> sur l’avis ou le formulaire d’enrôlement de la communauté.</li>
        </ul>
        <div class="pt-4 flex flex-wrap gap-3">
            <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800">Se connecter</a>
            <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50">Créer un compte</a>
            <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50">Parcourir les communautés</a>
        </div>
        <p class="text-xs text-slate-500 pt-2">Sur chaque fiche communautaire, seules les offres publiées par cette équipe apparaissent.</p>
    </div>
</div>
