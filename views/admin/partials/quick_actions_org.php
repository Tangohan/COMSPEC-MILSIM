<?php
$gate = \App\Core\Gate::getInstance();
?>
<div class="rounded-xl border border-amber-200 bg-amber-50/40 p-5 mb-8">
    <h2 class="text-lg font-bold text-slate-900 mb-3">Actions rapides</h2>
    <div class="flex flex-wrap gap-2">
        <a href="<?= url('admin/organization/users/create') ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">Nouvel utilisateur</a>
        <a href="<?= url('admin/organization/invitations') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Invitations</a>
        <a href="<?= url('admin/organization/groups/create') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Nouveau groupe</a>
        <a href="<?= url('admin/organization/teams/create') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Nouvelle équipe</a>
        <a href="<?= url('admin/organization/moderation') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Modération</a>
        <a href="<?= url('admin/organization/events') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Événements</a>
        <?php if ($gate->allows('documents.upload') || $gate->allows('admin.access')): ?>
        <a href="<?= url('documents/gestion') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Documents</a>
        <?php endif; ?>
        <?php if ($gate->allows('training.manage') || $gate->allows('training.assign') || $gate->allows('admin.access')): ?>
        <a href="<?= url('admin/training') ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">Formations</a>
        <?php endif; ?>
    </div>
</div>
