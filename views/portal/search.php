<?php
/** @var string $query */
$baseUrl = url('');
?>
<div class="max-w-3xl mx-auto px-6 py-16">
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Recherche portail</h1>
    <p class="mt-2 text-sm text-slate-600">
        La recherche unifiée (documents, forum, personnel) sera branchée ici.
    </p>
    <?php if ($query !== ''): ?>
    <p class="mt-6 text-sm font-medium text-slate-800">
        Terme saisi : <span class="font-mono text-sky-800"><?= htmlspecialchars($query) ?></span>
    </p>
    <?php endif; ?>
    <p class="mt-8">
        <a href="<?= htmlspecialchars($baseUrl) ?>/" class="text-sm font-semibold text-sky-700 hover:text-sky-900">← Retour à l’accueil</a>
    </p>
</div>
