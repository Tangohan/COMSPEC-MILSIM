<?php
$equipmentClasses = $equipmentClasses ?? [];
$byCategory = [];
foreach ($equipmentClasses as $c) {
    $cat = $c['category'] ?? 'Autres';
    if (!isset($byCategory[$cat])) {
        $byCategory[$cat] = [];
    }
    $byCategory[$cat][] = $c;
}
ksort($byCategory);
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Équipement</h1>
    <p class="text-slate-600 mb-4">Classes d'équipement et documentation associée.</p>
    <p class="mb-8">
        <a href="<?= url('equipment/wardrobes') ?>" class="inline-flex items-center text-sm font-semibold text-slate-800 underline hover:text-slate-600">
            Wardrobes ACE Arsenal (sync cloud)
        </a>
    </p>
    <?php if (empty($equipmentClasses)): ?>
    <p class="text-slate-500">Aucune classe d'équipement pour le moment.</p>
    <?php else: ?>
    <div class="space-y-8">
        <?php foreach ($byCategory as $category => $items): ?>
        <div>
            <h2 class="text-lg font-bold text-slate-800 mb-4"><?= htmlspecialchars($category) ?></h2>
            <ul class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <?php foreach ($items as $c): ?>
                <li>
                    <a href="<?= url('equipment/' . htmlspecialchars($c['slug'])) ?>" class="block p-4 border border-slate-200 rounded-xl bg-white hover:shadow-md hover:border-slate-300 transition-all">
                        <span class="font-semibold text-slate-900"><?= htmlspecialchars($c['name']) ?></span>
                        <?php if (!empty($c['description'])): ?>
                        <p class="text-sm text-slate-500 mt-1 line-clamp-2"><?= htmlspecialchars($c['description']) ?></p>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('documents') ?>" class="underline">Voir tous les documents</a></p>
</div>
