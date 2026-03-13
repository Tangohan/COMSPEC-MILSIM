<?php
$documents = $documents ?? [];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Documents</h1>
    <p class="text-slate-600 mb-8">Liste des documents publiés.</p>
    <?php if (empty($documents)): ?>
    <p class="text-slate-500">Aucun document pour le moment.</p>
    <?php else: ?>
    <ul class="space-y-2">
        <?php foreach ($documents as $doc): ?>
        <li class="flex items-center justify-between p-3 border border-slate-200 rounded-lg">
            <span class="font-medium text-slate-900"><?= htmlspecialchars($doc['title']) ?></span>
            <?php if (!empty($doc['file_path'])): ?>
            <a href="<?= url('documents/' . $doc['id'] . '/download') ?>" class="text-sm text-slate-600 hover:text-slate-900 underline">Télécharger</a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
