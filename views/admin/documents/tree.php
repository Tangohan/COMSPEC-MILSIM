<?php
$roots = $roots ?? [];
$childrenByParent = $childrenByParent ?? [];
$allDocuments = $allDocuments ?? [];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="mb-6">
        <a href="<?= url('documents/gestion') ?>" class="text-sm text-slate-500 hover:text-slate-900">← Retour aux documents</a>
        <h1 class="text-2xl font-black text-slate-900 mt-2">Arborescence documentaire</h1>
        <p class="text-slate-600 text-sm mt-1">Documents sans parent (racines) et leurs sous-documents.</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <?php if (empty($roots) && empty($childrenByParent)): ?>
        <p class="text-slate-500">Aucun document ou aucune hiérarchie définie.</p>
        <p class="text-sm mt-2"><a href="<?= url('documents/gestion') ?>" class="underline">Voir la liste des documents</a></p>
        <?php else: ?>
        <ul class="space-y-2">
            <?php
            $renderDoc = function ($doc, $depth = 0) use ($childrenByParent, &$renderDoc) {
                $id = (int)$doc['id'];
                $indent = str_repeat('　', $depth);
                $children = $childrenByParent[$id] ?? [];
                ?>
                <li class="py-1">
                    <span class="text-slate-400"><?= $indent ?></span>
                    <?php if ($depth > 0): ?><span class="text-slate-400">└ </span><?php endif; ?>
                    <a href="<?= url('documents/gestion/' . $id . '/modifier') ?>" class="text-slate-900 hover:underline font-medium"><?= htmlspecialchars($doc['title']) ?></a>
                    <span class="text-slate-500 text-xs">(<?= htmlspecialchars($doc['status'] ?? '') ?>)</span>
                </li>
                <?php foreach ($children as $ch): ?>
                <?php $renderDoc($ch, $depth + 1); ?>
                <?php endforeach; ?>
                <?php
            };
            foreach ($roots as $root) {
                $renderDoc($root);
            }
            ?>
        </ul>
        <?php endif; ?>
    </div>

    <p class="mt-6 text-sm text-slate-500">
        <a href="<?= url('documents/gestion/arborescence') ?>" class="underline">Actualiser</a>
        —
        <a href="<?= url('documents/gestion') ?>" class="underline">Liste complète</a>
    </p>
</div>
