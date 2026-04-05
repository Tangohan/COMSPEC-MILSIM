<div class="rounded-xl border border-amber-200 bg-amber-50/40 p-5 mb-8">
    <h2 class="text-lg font-bold text-slate-900 mb-1">Actions rapides — plateforme</h2>
    <p class="text-xs text-slate-600 mb-3">Réservé au périmètre <strong class="font-semibold text-slate-800">site entier</strong> (rôles site, paramètres, audit global).</p>
    <div class="flex flex-wrap gap-2">
        <a href="<?= url('admin/roles') ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">Rôles site</a>
        <a href="<?= url('admin/site-roles') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Affectations site</a>
        <a href="<?= url('admin/settings') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Paramètres</a>
        <a href="<?= url('admin/system/alerts') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Alertes plateforme</a>
        <a href="<?= url('admin/maintenance') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Maintenance</a>
        <a href="<?= url('admin/audit') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Audit</a>
        <?php if (\App\Core\Gate::getInstance()->allows('admin.organization') || \App\Core\Gate::getInstance()->allows('admin.access')): ?>
        <a href="<?= url('back-office') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-emerald-300 bg-emerald-50 text-sm font-medium text-emerald-900 hover:bg-emerald-100">Back-office communauté</a>
        <?php endif; ?>
    </div>
</div>
