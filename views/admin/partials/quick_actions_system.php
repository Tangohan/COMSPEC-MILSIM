<?php
declare(strict_types=1);
/**
 * Actions réservées à admin.system — périmètre plateforme uniquement.
 */
?>
<section class="space-y-4" aria-labelledby="qa-platform-heading">
    <div>
        <h2 id="qa-platform-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Modules plateforme</h2>
        <p class="mt-1 text-sm text-slate-600">Accès direct aux fonctions <strong class="font-semibold text-slate-800">transverses</strong> (hors configuration d’une communauté précise).</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2">Sécurité &amp; accès</p>
            <h3 class="text-base font-bold text-slate-900">Identité globale</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 flex-1">Rôles applicatifs et habilitations au niveau du site (pas les rôles communautaires).</p>
            <div class="flex flex-col gap-2">
                <a href="<?= url('admin/roles') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2.5 text-xs font-bold text-white hover:bg-slate-800">Rôles système</a>
                <a href="<?= url('admin/site-roles') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Affectations rôles site</a>
                <a href="<?= url('admin/system/blocklist') ?>" class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50/80 px-3 py-2.5 text-xs font-semibold text-rose-950 hover:bg-rose-100">Liste de restriction (site entier)</a>
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
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2">Exploitation</p>
            <h3 class="text-base font-bold text-slate-900">Données &amp; conformité</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 flex-1">Maintenance planifiée, traçabilité globale et diagnostics.</p>
            <div class="flex flex-col gap-2">
                <a href="<?= url('admin/maintenance') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2.5 text-xs font-bold text-white hover:bg-slate-800">Maintenance BDD</a>
                <a href="<?= url('admin/audit') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Journal d’audit</a>
            </div>
        </div>
    </div>
</section>
