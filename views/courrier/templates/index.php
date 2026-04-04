<?php
$templates = $courrier['templates'] ?? [];
$baseUrl = url('');
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Modèles — Bureau Courrier</h1>
        <a href="<?= $baseUrl ?>/courrier/templates/create" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouveau modèle</a>
    </div>
    <p class="mb-6 text-slate-600 text-sm">Modèles système (verrouillés) et modèles métier modifiables.</p>
    <?php if (empty($templates)): ?>
    <p class="text-slate-500">Aucun modèle. <a href="<?= $baseUrl ?>/courrier/templates/create" class="underline">Créer un modèle</a>.</p>
    <?php else: ?>
    <ul class="space-y-3">
        <?php foreach ($templates as $t): ?>
        <li class="flex items-center justify-between p-4 bg-white border border-slate-200 rounded-lg">
            <div>
                <span class="font-medium text-slate-900"><?= htmlspecialchars($t['name'] ?? '') ?></span>
                <?php if (!empty($t['is_system'])): ?><span class="ml-2 text-xs text-slate-500">Système</span><?php endif; ?>
                <?php if (!empty($t['is_locked'])): ?><span class="ml-2 text-xs text-amber-600">Verrouillé</span><?php endif; ?>
                <?php if (!empty($t['preset_name'])): ?><span class="ml-2 text-xs text-slate-400"><?= htmlspecialchars($t['preset_name']) ?></span><?php endif; ?>
            </div>
            <?php if (empty($t['is_locked'])): ?>
            <a href="<?= $baseUrl ?>/courrier/templates/<?= (int)$t['id'] ?>/edit" class="px-3 py-1.5 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Modifier</a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="mt-6"><a href="<?= $baseUrl ?>/courrier" class="text-slate-500 hover:text-slate-900 text-sm">← Bureau Courrier</a></p>
</div>
