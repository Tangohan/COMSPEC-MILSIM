<?php
$equipmentClass = $equipmentClass ?? null;
$linkedDocuments = $linkedDocuments ?? [];
if (!$equipmentClass) {
    echo '<p>Classe non trouvée.</p>';
    return;
}
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <a href="<?= url('equipment') ?>" class="text-sm text-slate-500 hover:text-slate-900 mb-4 inline-block">← Retour à l'équipement</a>
    <h1 class="text-2xl font-black text-slate-900 mb-2"><?= htmlspecialchars($equipmentClass['name']) ?></h1>
    <?php if (!empty($equipmentClass['category'])): ?>
    <p class="text-slate-500 text-sm mb-4"><?= htmlspecialchars($equipmentClass['category']) ?></p>
    <?php endif; ?>
    <?php if (!empty($equipmentClass['description'])): ?>
    <div class="prose prose-slate max-w-none mb-8">
        <p><?= nl2br(htmlspecialchars($equipmentClass['description'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="border-t border-slate-200 pt-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Documentation</h2>
        <?php if (empty($linkedDocuments)): ?>
        <p class="text-slate-500">Aucun document associé.</p>
        <?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($linkedDocuments as $doc): ?>
            <li>
                <a href="<?= url('documents/' . htmlspecialchars($doc['slug'])) ?>" class="text-slate-700 hover:text-emerald-600 font-medium underline"><?= htmlspecialchars($doc['title']) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('documents') ?>" class="underline">Tous les documents</a></p>
</div>
