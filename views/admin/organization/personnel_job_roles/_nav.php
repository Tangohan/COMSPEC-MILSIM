<?php
$active = $activeTab ?? 'referentiel';
?>
<nav class="mb-8 flex flex-wrap gap-2 border-b border-slate-200 pb-1">
    <a href="<?= url('back-office/personnel-job-roles/kits') ?>" class="rounded-t-lg px-4 py-2 text-sm font-semibold <?= $active === 'kits' ? 'bg-white text-slate-900 ring-1 ring-slate-200 ring-b-0' : 'text-slate-600 hover:text-slate-900' ?>">Kits d’accès</a>
    <a href="<?= url('back-office/personnel-job-roles') ?>" class="rounded-t-lg px-4 py-2 text-sm font-semibold <?= $active === 'referentiel' ? 'bg-white text-slate-900 ring-1 ring-slate-200 ring-b-0' : 'text-slate-600 hover:text-slate-900' ?>">Référentiel</a>
    <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="rounded-t-lg px-4 py-2 text-sm font-semibold <?= $active === 'assignments' ? 'bg-white text-slate-900 ring-1 ring-slate-200 ring-b-0' : 'text-slate-600 hover:text-slate-900' ?>">Attributions effectifs</a>
</nav>
