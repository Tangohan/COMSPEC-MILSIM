<?php
$unitsTree = $unitsTree ?? [];
function renderOrbatTree(array $nodes, int $level = 0): void {
    foreach ($nodes as $unit):
        $pad = $level * 1.5;
?>
    <div class="border-l-2 border-slate-200 pl-4 py-2" style="margin-left: <?= $pad ?>rem;">
        <div class="font-semibold text-slate-900"><?= htmlspecialchars($unit['name']) ?></div>
        <?php if (!empty($unit['code'])): ?><span class="text-sm text-slate-500"><?= htmlspecialchars($unit['code']) ?></span><?php endif; ?>
        <?php if (!empty($unit['children'])): ?>
        <div class="mt-2">
            <?php renderOrbatTree($unit['children'], $level + 1); ?>
        </div>
        <?php endif; ?>
    </div>
<?php
    endforeach;
}
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">ORBAT</h1>
    <p class="text-slate-600 mb-8">Structure organique des unités.</p>
    <?php if (empty($unitsTree)): ?>
    <p class="text-slate-500">Aucune unité configurée. Créez des unités dans l'administration.</p>
    <?php else: ?>
    <div class="space-y-0">
        <?php renderOrbatTree($unitsTree); ?>
    </div>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
