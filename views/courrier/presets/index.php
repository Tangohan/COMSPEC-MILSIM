<?php
$presets = $courrier['presets'] ?? [];
$baseUrl = url('');
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Formats (presets) — Bureau Courrier</h1>
    </div>
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars((string)\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <p class="mb-6 text-slate-600 text-sm">Formats de mise en page : A4 portrait/paysage, note interne, compte rendu, etc.</p>
    <?php if (empty($presets)): ?>
    <p class="text-slate-500">Aucun preset. Exécutez les migrations pour charger les presets système.</p>
    <?php else: ?>
    <ul class="space-y-3">
        <?php foreach ($presets as $p): ?>
        <li class="flex items-center justify-between p-4 bg-white border border-slate-200 rounded-lg">
            <div>
                <span class="font-medium text-slate-900"><?= htmlspecialchars($p['name'] ?? '') ?></span>
                <span class="ml-2 text-xs text-slate-500"><?= htmlspecialchars($p['code'] ?? '') ?></span>
                <?php if (!empty($p['is_system'])): ?><span class="ml-2 text-xs text-slate-400">Système</span><?php endif; ?>
                <?php if (!empty($p['is_default'])): ?><span class="ml-2 text-xs font-medium text-emerald-600">Par défaut</span><?php endif; ?>
            </div>
            <?php if (empty($p['is_default'])): ?>
            <form method="post" action="<?= $baseUrl ?>/courrier/presets/<?= (int)$p['id'] ?>/default" class="inline">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="px-3 py-1.5 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Définir par défaut</button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="mt-6"><a href="<?= $baseUrl ?>/courrier" class="text-slate-500 hover:text-slate-900 text-sm">← Bureau Courrier</a></p>
</div>
