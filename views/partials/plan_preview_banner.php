<?php
declare(strict_types=1);

// Bandeau permanent de l'« Aperçu de la formule » (administrateur de la plateforme, lecture seule, session seulement).
$planPreview = \App\Services\Platform\EffectivePlan::current();
$viewPreview = \App\Services\Platform\EffectiveTenantType::current();
if ($planPreview === null && $viewPreview === null) {
    return;
}
?>
<div id="plan-preview-banner" class="sticky top-0 z-[100] bg-violet-700 text-white shadow-lg px-4 py-2" role="status">
 <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3">
  <span class="font-bold"><?php if ($planPreview !== null): ?>Aperçu : formule <?= htmlspecialchars($planPreview['name'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?><?php if ($planPreview !== null && $viewPreview !== null): ?> · <?php endif; ?><?php if ($viewPreview !== null): ?>Aperçu : vue <?= htmlspecialchars($viewPreview['label'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?> <span class="font-normal opacity-90">— lecture seule, rien n’est modifié</span></span>
  <form method="post" action="<?= htmlspecialchars(url('admin/apercu-formule/quitter'), ENT_QUOTES, 'UTF-8') ?>">
   <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
   <button type="submit" class="underline font-bold">Quitter l’aperçu</button>
  </form>
 </div>
</div>
