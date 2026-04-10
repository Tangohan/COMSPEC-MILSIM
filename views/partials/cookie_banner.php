<?php
declare(strict_types=1);
$b = url('');
?>
<div id="portal-cookie-banner" class="hidden fixed bottom-0 inset-x-0 z-[300] p-4 md:p-6" role="dialog" aria-labelledby="cookie-banner-title" aria-live="polite" aria-modal="false" hidden>
    <div class="max-w-4xl mx-auto rounded-2xl border border-slate-200 bg-white shadow-2xl px-5 py-4 md:px-6 md:py-5 flex flex-col gap-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0 space-y-2">
                <h2 id="cookie-banner-title" class="text-sm font-black text-slate-900 uppercase tracking-tight">Cookies et confidentialité</h2>
                <p class="text-xs text-slate-600 leading-relaxed max-w-xl">
                    Des cookies strictement nécessaires assurent la connexion et la sécurité. Avec votre accord, nous pourrons aussi mesurer la fréquentation et, le cas échéant, afficher des contenus ou annonces personnalisés fournis par des partenaires.
                </p>
                <p class="text-[11px] text-slate-500 flex flex-wrap gap-x-2 gap-y-1 items-center">
                    <a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>" class="font-semibold text-emerald-700 hover:underline">Données personnelles</a>
                    <span class="text-slate-300" aria-hidden="true">·</span>
                    <a href="<?= htmlspecialchars(url('cookies')) ?>" class="font-semibold text-emerald-700 hover:underline">Cookies</a>
                    <span class="text-slate-300" aria-hidden="true">·</span>
                    <a href="<?= htmlspecialchars(url('mentions-legales')) ?>" class="font-semibold text-emerald-700 hover:underline">Mentions légales</a>
                    <span class="text-slate-300" aria-hidden="true">·</span>
                    <a href="<?= htmlspecialchars(url('cgu')) ?>" class="font-semibold text-emerald-700 hover:underline">CGU</a>
                </p>
            </div>
            <div class="flex flex-col sm:flex-row md:flex-col lg:flex-row gap-2 shrink-0">
                <button type="button" id="portal-cookie-essential-only" class="px-4 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-colors">
                    Nécessaires uniquement
                </button>
                <button type="button" id="portal-cookie-customize" class="px-4 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-colors" aria-expanded="false" aria-controls="portal-cookie-panel">
                    Personnaliser
                </button>
                <button type="button" id="portal-cookie-reject-all" class="px-4 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-colors">
                    Tout refuser
                </button>
                <button type="button" id="portal-cookie-accept-all" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-colors">
                    Tout accepter
                </button>
            </div>
        </div>

        <div id="portal-cookie-panel" class="hidden border-t border-slate-100 pt-4 space-y-4" hidden aria-hidden="true">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Affinez vos choix</p>
            <p class="text-xs text-slate-500">Les catégories ci-dessous sont désactivées tant que vous ne les activez pas. Vous pourrez modifier ce réglage à tout moment via « Préférences cookies » en pied de page ou sur la page dédiée.</p>
            <p id="portal-cookie-last-choice" class="text-[11px] text-slate-500">Aucun choix enregistré</p>
            <ul class="space-y-3">
                <li class="flex gap-3 items-start rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                    <span class="mt-0.5 text-emerald-600" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-bold text-slate-900">Fonctionnement du portail</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Toujours actif : session, sécurité des formulaires, préférences techniques indispensables.</p>
                    </div>
                </li>
                <li class="flex gap-3 items-start rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input type="checkbox" id="portal-cookie-audience" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="portal-cookie-audience" class="cursor-pointer">
                        <span class="text-xs font-bold text-slate-900">Mesure d’audience</span>
                        <span class="block text-[11px] text-slate-500 mt-0.5">Comprendre comment le site est utilisé pour l’améliorer (statistiques anonymisées ou agrégées selon les outils retenus).</span>
                    </label>
                </li>
                <li class="flex gap-3 items-start rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input type="checkbox" id="portal-cookie-personalization" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="portal-cookie-personalization" class="cursor-pointer">
                        <span class="text-xs font-bold text-slate-900">Personnalisation du portail</span>
                        <span class="block text-[11px] text-slate-500 mt-0.5">Adapter l’expérience (ordre de widgets publics, contenus proposés, aide contextuelle) sans activer la publicité tierce.</span>
                    </label>
                </li>
                <li class="flex gap-3 items-start rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input type="checkbox" id="portal-cookie-ads" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="portal-cookie-ads" class="cursor-pointer">
                        <span class="text-xs font-bold text-slate-900">Publicité tierce</span>
                        <span class="block text-[11px] text-slate-500 mt-0.5">Réservé aux futurs contenus promotionnels ou messages partenaires. Cette option reste indépendante des préférences de personnalisation du portail.</span>
                    </label>
                </li>
            </ul>
            <p class="text-[11px] text-slate-500">Pour renforcer la confidentialité, un nouveau consentement pourra vous être demandé après 180 jours ou après un changement majeur de politique.</p>
            <div class="flex flex-wrap gap-2 justify-end">
                <button type="button" id="portal-cookie-reset" class="px-4 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-colors">
                    Réinitialiser
                </button>
                <button type="button" id="portal-cookie-save-custom" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-colors">
                    Enregistrer mes choix
                </button>
            </div>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars($b) ?>/assets/js/cookie_consent.js" defer></script>
