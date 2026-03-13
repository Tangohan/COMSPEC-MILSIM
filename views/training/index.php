<?php
$modules = $modules ?? [];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Formations</h1>
    <p class="text-slate-600 mb-8">Catalogue des modules de formation.</p>
    <?php if (empty($modules)): ?>
    <p class="text-slate-500">Aucun module pour le moment.</p>
    <?php else: ?>
    <ul class="space-y-2">
        <?php foreach ($modules as $m): ?>
        <li class="p-3 border border-slate-200 rounded-lg">
            <a href="<?= url('formations/' . $m['slug']) ?>" class="font-medium text-slate-900 hover:underline"><?= htmlspecialchars($m['title']) ?></a>
            <?php if (!empty($m['description'])): ?>
            <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($m['description']) ?></p>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
