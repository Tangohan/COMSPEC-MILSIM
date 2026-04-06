<?php
declare(strict_types=1);
$csrf = $csrfToken ?? \App\Core\Csrf::token();
?>
<div class="max-w-lg mx-auto px-6 py-10">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Nouvelle mission inter-unités</h1>
    <p class="text-sm text-slate-600 mb-6 leading-relaxed">Donnez un nom clair à la coopération (exercice conjoint, acculturation, partage de consignes). Vous pourrez ensuite inviter d’autres unités et partager des sujets du brief une fois la mission validée par toutes les parties.</p>
    <form method="post" action="<?= url('admin/interteam-missions') ?>" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label for="itm-title" class="block text-sm font-semibold text-slate-900">Intitulé</label>
            <input id="itm-title" name="title" type="text" required minlength="3" maxlength="255" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" placeholder="Ex. Exercice Orion — coordination 92e / 126e">
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Créer</button>
            <a href="<?= url('admin/interteam-missions') ?>" class="inline-flex items-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
