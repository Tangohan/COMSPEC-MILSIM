<?php
$currentUser = \App\Core\Session::get('display_name') ?: \App\Core\Session::get('email');
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Dashboard</h1>
    <?php if ($currentUser): ?>
    <p class="text-slate-600 mb-8">Bienvenue, <?= htmlspecialchars($currentUser) ?>.</p>
    <?php else: ?>
    <p class="text-slate-600 mb-8">Espace opérationnel — formations, priorités, accès commandement.</p>
    <?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="<?= url('personnel/me') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Fiche personnel</span>
        </a>
        <a href="<?= url('orbat') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">ORBAT</span>
        </a>
        <a href="<?= url('documents') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Documents</span>
        </a>
        <a href="<?= url('formations') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Formations</span>
        </a>
    </div>
</div>
