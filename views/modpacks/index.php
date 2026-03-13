<?php
$modpacks = $modpacks ?? [];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modpacks</h1>
    <?php if (empty($modpacks)): ?>
    <p class="text-slate-500">Aucun modpack pour le moment.</p>
    <?php else: ?>
    <ul class="space-y-2">
        <?php foreach ($modpacks as $mp): ?>
        <li class="flex items-center justify-between p-3 border border-slate-200 rounded-lg">
            <a href="<?= url('modpacks/' . htmlspecialchars($mp['slug'])) ?>" class="font-medium text-slate-900 hover:underline"><?= htmlspecialchars($mp['name']) ?></a>
            <span class="text-sm text-slate-500"><?= htmlspecialchars($mp['version'] ?? '—') ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
