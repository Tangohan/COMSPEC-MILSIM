<?php
declare(strict_types=1);
/**
 * Raccourcis lecture / suivi pour le rôle assistance site (sans configuration système).
 */
?>
<section class="space-y-4" aria-labelledby="qa-support-hub-heading">
    <div>
        <h2 id="qa-support-hub-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Assistance &amp; suivi</h2>
        <p class="mt-1 text-sm text-slate-600">
            Accès aux vues de synthèse et aux journaux. Les réglages sensibles du site restent réservés aux <strong class="font-semibold text-slate-800">administrateurs plateforme</strong>.
        </p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="<?= url('admin/ops-center') ?>" class="group flex flex-col rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5 shadow-sm hover:border-indigo-300 hover:shadow-md transition-all">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-800/90">Synthèse</span>
            <span class="mt-2 text-base font-bold text-slate-900 group-hover:underline">Centre opérationnel</span>
            <span class="mt-1 text-xs text-slate-600 flex-1">Files d’actions et priorités par rôle.</span>
        </a>
        <a href="<?= url('admin/system/alerts') ?>" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300 hover:shadow-md transition-all">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Communication</span>
            <span class="mt-2 text-base font-bold text-slate-900 group-hover:underline">Alertes visibles sur le site</span>
            <span class="mt-1 text-xs text-slate-600 flex-1">Messages d’information ou d’incident.</span>
        </a>
        <a href="<?= url('admin/maintenance') ?>" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300 hover:shadow-md transition-all">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Disponibilité</span>
            <span class="mt-2 text-base font-bold text-slate-900 group-hover:underline">Fenêtres de maintenance</span>
            <span class="mt-1 text-xs text-slate-600 flex-1">Planification et état des interruptions.</span>
        </a>
        <a href="<?= url('admin/audit') ?>" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300 hover:shadow-md transition-all">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Traçabilité</span>
            <span class="mt-2 text-base font-bold text-slate-900 group-hover:underline">Journal d’audit</span>
            <span class="mt-1 text-xs text-slate-600 flex-1">Historique des opérations sensibles.</span>
        </a>
    </div>
</section>
