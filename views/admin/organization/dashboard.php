<?php ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Administration organisationnelle</h1>
        <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour au centre</a>
    </div>
    <p class="text-slate-600 mb-8">Gestion des utilisateurs, groupes, équipes et rôles métier.</p>
    <ul class="space-y-2">
        <li><a href="<?= url('admin/organization/users') ?>" class="text-slate-700 hover:underline font-medium">Utilisateurs</a></li>
        <li><a href="<?= url('admin/organization/roles') ?>" class="text-slate-700 hover:underline font-medium">Rôles</a></li>
        <li><a href="<?= url('admin/organization/categories') ?>" class="text-slate-700 hover:underline font-medium">Catégories</a></li>
        <li><a href="<?= url('admin/organization/referentiels/grades') ?>" class="text-slate-700 hover:underline font-medium">Référentiels &gt; Grades</a></li>
        <li><a href="<?= url('admin/organization/groups') ?>" class="text-slate-700 hover:underline font-medium">Groupes</a></li>
        <li><a href="<?= url('admin/organization/teams') ?>" class="text-slate-700 hover:underline font-medium">Équipes</a></li>
        <li><a href="<?= url('admin/configuration') ?>" class="text-slate-700 hover:underline font-medium">Configuration (unités & données)</a></li>
        <li><a href="<?= url('admin/recruitments') ?>" class="text-slate-700 hover:underline font-medium">Candidatures</a></li>
        <?php if (\App\Core\Gate::getInstance()->allows('admin.access') || \App\Core\Gate::getInstance()->allows('documents.upload')): ?>
        <li><a href="<?= url('documents/gestion') ?>" class="text-slate-700 hover:underline font-medium">Documents</a></li>
        <?php endif; ?>
        <?php if (\App\Core\Gate::getInstance()->allows('admin.access') || \App\Core\Gate::getInstance()->allows('training.manage') || \App\Core\Gate::getInstance()->allows('training.assign')): ?>
        <li><a href="<?= url('admin/training') ?>" class="text-slate-700 hover:underline font-medium">Formations (LMS)</a></li>
        <?php endif; ?>
    </ul>
</div>
