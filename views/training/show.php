<?php
$module = $module ?? null;
if (!$module) {
    echo '<p>Module non trouvé.</p>';
    return;
}
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6"><?= htmlspecialchars($module['title']) ?></h1>
    <?php if (!empty($module['description'])): ?>
    <p class="text-slate-600 mb-8"><?= htmlspecialchars($module['description']) ?></p>
    <?php endif; ?>
    <div class="prose prose-slate max-w-none">
        <p>Contenu du module (HTML / intégration Phase 6 avancée). Code : <?= htmlspecialchars($module['code'] ?? '—') ?></p>
    </div>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('formations') ?>" class="underline">Retour au catalogue</a></p>
</div>
