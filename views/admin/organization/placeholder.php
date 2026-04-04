<?php $label = $label ?? 'Cette section'; ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <p class="text-slate-600"><?= htmlspecialchars($label) ?></p>
    <p class="mt-4"><a href="<?= url('admin/organization') ?>" class="text-sm font-medium text-slate-600 hover:underline">Retour administration organisationnelle</a></p>
</div>
