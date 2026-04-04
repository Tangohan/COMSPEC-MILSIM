<?php ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Administration système</h1>
        <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour au centre</a>
    </div>
    <p class="text-slate-600 mb-8">Tableau de bord réservé aux super-administrateurs.</p>
    <ul class="space-y-2">
        <li><a href="<?= url('admin/system/roles') ?>" class="text-slate-700 hover:underline font-medium">Rôles système</a></li>
        <li><a href="<?= url('admin/system/settings') ?>" class="text-slate-700 hover:underline font-medium">Paramètres applicatifs</a></li>
        <li><a href="<?= url('admin/system/audit') ?>" class="text-slate-700 hover:underline font-medium">Journaux d'audit</a></li>
    </ul>
</div>
