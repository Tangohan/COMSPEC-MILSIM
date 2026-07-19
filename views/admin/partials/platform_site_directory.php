<?php
declare(strict_types=1);
$gate = \App\Core\Gate::getInstance();
if (!$gate->allows('admin.system')) {
    return;
}
?>
<section id="hub-annuaire" class="scroll-mt-24 mb-8 lg:mb-10 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm" aria-labelledby="hub-annuaire-heading">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 id="hub-annuaire-heading" class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Pilotage global du site</h2>
            <p class="mt-1 text-base font-bold text-slate-900">Communautés, recrutement et accès commercial</p>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Raccourcis vers l’annuaire transverse, la vitrine publique, le déploiement des fonctionnalités et les écrans portail liés aux formules et au parrainage.
            </p>
        </div>
        <a href="<?= htmlspecialchars(url('admin/tenants'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Annuaire complet</a>
    </div>
    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
            <h3 class="text-sm font-bold text-slate-900">Communautés</h3>
            <p class="mt-1 text-xs text-slate-600">Liste opérateur, annuaire public et mise en ligne contrôlée.</p>
            <ul class="mt-3 space-y-2 text-sm font-semibold">
                <li><a href="<?= htmlspecialchars(url('admin/tenants'), ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-800 hover:text-emerald-950 underline decoration-emerald-200">Annuaire des communautés</a></li>
                <li><a href="<?= htmlspecialchars(url('admin/users'), ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-800 hover:text-emerald-950 underline decoration-emerald-200">Comptes utilisateurs (toutes communautés)</a></li>
                <li><a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-800 hover:text-emerald-950 underline decoration-emerald-200">Annuaire public</a></li>
                <li><a href="<?= htmlspecialchars(url('communities/create'), ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-800 hover:text-emerald-950 underline decoration-emerald-200">Créer une communauté</a></li>
                <li><a href="<?= htmlspecialchars(url('admin/system/deployment'), ENT_QUOTES, 'UTF-8') ?>" class="text-amber-800 hover:text-amber-950 underline decoration-amber-200">Publications et préqualification</a></li>
            </ul>
        </div>
        <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
            <h3 class="text-sm font-bold text-slate-900">Recrutement et offres d’emploi</h3>
            <p class="mt-1 text-xs text-slate-600">
                Chaque communauté pilote ses fiches de poste et ses candidatures dans son propre espace d’administration.
            </p>
            <ul class="mt-3 space-y-2 text-sm font-semibold">
                <li><a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-800 hover:text-emerald-950 underline decoration-emerald-200">Offres (communauté active)</a></li>
                <li><a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-800 hover:text-emerald-950 underline decoration-emerald-200">Dossiers de recrutement</a></li>
            </ul>
        </div>
        <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4 md:col-span-2 xl:col-span-1">
            <h3 class="text-sm font-bold text-slate-900">Accès payant et parrainage</h3>
            <p class="mt-1 text-xs text-slate-600">Attribuez une formule à chaque communauté depuis l’annuaire complet (« Changer la formule »).</p>
            <ul class="mt-3 space-y-2 text-sm font-semibold">
                <li><a href="<?= htmlspecialchars(url('platform/upgrade'), ENT_QUOTES, 'UTF-8') ?>" class="text-indigo-800 hover:text-indigo-950 underline decoration-indigo-200">Mise à niveau du service</a></li>
                <li><a href="<?= htmlspecialchars(url('platform/invite-unit'), ENT_QUOTES, 'UTF-8') ?>" class="text-indigo-800 hover:text-indigo-950 underline decoration-indigo-200">Invitations structurantes</a></li>
            </ul>
        </div>
    </div>
</section>
