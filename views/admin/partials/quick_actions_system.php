<?php
declare(strict_types=1);
/**
 * Actions réservées à admin.system — périmètre plateforme uniquement.
 */
$gate = \App\Core\Gate::getInstance();
if (!$gate->allows('admin.system')) {
    return;
}
?>
<section class="space-y-4" aria-labelledby="qa-platform-heading" id="hub-plateforme-modules">
    <div>
        <h2 id="qa-platform-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Modules plateforme</h2>
        <p class="mt-1 text-sm text-slate-600">Accès direct aux fonctions <strong class="font-semibold text-slate-800">transverses</strong> (hors configuration d’une communauté précise).</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2">Sécurité &amp; accès</p>
            <h3 class="text-base font-bold text-slate-900">Identité globale</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 flex-1">Rôles applicatifs et habilitations au niveau du site (pas les rôles communautaires).</p>
            <div class="flex flex-col gap-2">
                <a href="<?= url('admin/roles') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2.5 text-xs font-bold text-white hover:bg-slate-800">Rôles système</a>
                <a href="<?= url('admin/site-roles') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Affectations rôles site</a>
                <a href="<?= url('admin/system/blocklist') ?>" class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50/80 px-3 py-2.5 text-xs font-semibold text-rose-950 hover:bg-rose-100">Liste de restriction (site entier)</a>
                <a href="<?= url('admin/system/member-sanctions') ?>" class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-white px-3 py-2.5 text-xs font-semibold text-rose-900 hover:bg-rose-50">Sanctions à l’échelle du site</a>
                <a href="<?= url('admin/system/recruitment-portal-tools') ?>" class="inline-flex items-center justify-center rounded-lg border border-sky-200 bg-sky-50/90 px-3 py-2.5 text-xs font-semibold text-sky-950 hover:bg-sky-100">Portail recrutement — automod &amp; réouverture</a>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2">Configuration</p>
            <h3 class="text-base font-bold text-slate-900">Paramètres &amp; communication</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 flex-1">Variables applicatives et messages visibles sur l’ensemble des instances.</p>
            <div class="flex flex-col gap-2">
                <a href="<?= url('admin/settings') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2.5 text-xs font-bold text-white hover:bg-slate-800">Paramètres système</a>
                <a href="<?= url('admin/system/brief') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Brief (accès membres)</a>
                <a href="<?= url('admin/system/alerts') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Alertes plateforme</a>
                <a href="<?= url('admin/system/subscription-plans') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Formules d’accès (paliers)</a>
                <a href="<?= url('admin/system/cooperation/catalog') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Types de coopération (référence)</a>
                <a href="<?= url('admin/system/cooperation/announcements') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Annonces coopération (défauts)</a>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2">Exploitation</p>
            <h3 class="text-base font-bold text-slate-900">Données &amp; conformité</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 flex-1">Maintenance planifiée, traçabilité globale et diagnostics.</p>
            <div class="flex flex-col gap-2">
                <a href="<?= url('admin/maintenance') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2.5 text-xs font-bold text-white hover:bg-slate-800">Maintenance BDD</a>
                <a href="<?= url('admin/ops-center') ?>" class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2.5 text-xs font-semibold text-indigo-900 hover:bg-indigo-100">Ops Center rôles</a>
                <a href="<?= url('admin/command-center') ?>" class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs font-semibold text-rose-900 hover:bg-rose-100">Command Center (undo)</a>
                <a href="<?= url('admin/analytics') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Indicateurs transverses</a>
                <a href="<?= url('admin/audit') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Journal d’audit</a>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col md:col-span-2 xl:col-span-1">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-700/90 mb-2">Déploiement</p>
            <h3 class="text-base font-bold text-slate-900">Publications et préqualification</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 flex-1">Canaux d’environnement, versions courantes et communautés de test.</p>
            <div class="flex flex-col gap-2">
                <a href="<?= url('admin/tenants') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Annuaire des communautés</a>
                <a href="<?= url('admin/system/deployment') ?>" class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-3 py-2.5 text-xs font-bold text-white hover:bg-amber-700">Tableau des publications</a>
                <a href="<?= url('admin/system/deployment/communities') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Communautés de test</a>
            </div>
        </div>
    </div>
</section>
