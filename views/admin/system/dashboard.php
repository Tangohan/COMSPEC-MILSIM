<?php
$kpis = $adminKpis ?? [];
$blockError = $adminKpiBlockError ?? null;
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Administration système</h1>
        <a href="<?= url('dashboard') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour au tableau de bord</a>
    </div>
    <p class="text-slate-600 mb-6">Tableau de bord réservé aux super-administrateurs — outils <strong class="font-semibold text-slate-800">plateforme</strong> ci-dessous ; les modules métier par communauté sont regroupés ensuite.</p>

    <?php
    require base_path('views/admin/partials/kpi_row.php');
    require base_path('views/admin/partials/recent_activity.php');
    require base_path('views/admin/partials/quick_actions_system.php');
    require base_path('views/admin/partials/tenant_session_modules.php');
    ?>

    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 mb-6">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-3">Plateforme (global)</h2>
        <ul class="space-y-2">
            <li><a href="<?= url('admin/roles') ?>" class="text-slate-700 hover:underline font-medium">Rôles site (plateforme)</a></li>
            <li><a href="<?= url('admin/site-roles') ?>" class="text-slate-700 hover:underline font-medium">Affectations rôles site</a></li>
            <li><a href="<?= url('admin/settings') ?>" class="text-slate-700 hover:underline font-medium">Paramètres applicatifs</a></li>
            <li><a href="<?= url('admin/system/alerts') ?>" class="text-slate-700 hover:underline font-medium">Alertes plateforme (promo, nouveautés)</a></li>
            <li><a href="<?= url('admin/maintenance') ?>" class="text-slate-700 hover:underline font-medium">Maintenance (BDD)</a></li>
            <li><a href="<?= url('admin/audit') ?>" class="text-slate-700 hover:underline font-medium">Journaux d'audit</a></li>
        </ul>
    </div>
</div>
