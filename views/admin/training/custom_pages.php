<?php
$rows = is_array($customPagesRows ?? null) ? $customPagesRows : [];
$metrics = is_array($customPagesMetrics ?? null) ? $customPagesMetrics : [];
?>
<section class="tc-panel p-6 md:p-8 space-y-4">
  <p class="tc-kicker">Centre des opérations — DOC HTML</p>
  <h1 class="tc-hero-title">Studio documentaire / manuel</h1>
  <div class="grid md:grid-cols-5 gap-3 text-xs">
    <?php foreach (['recently_modified'=>'Modifiés 7j','forgotten_drafts'=>'Brouillons oubliés','scheduled'=>'Programmés','published_without_theme'=>'Publiés sans thème','never_viewed'=>'Publiés jamais ouverts'] as $k=>$l): ?>
      <div class="rounded-lg border border-slate-200 bg-white px-3 py-2"><p class="text-slate-500"><?= $l ?></p><p class="text-lg font-black"><?= (int)($metrics[$k] ?? 0) ?></p></div>
    <?php endforeach; ?>
  </div>
  <div class="flex gap-2">
    <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/nouvelle')) ?>" class="tc-btn-primary tc-btn-emerald text-sm">Nouveau document</a>
  </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead><tr class="bg-slate-50 border-b border-slate-200 text-left"><th class="p-3">Titre</th><th class="p-3">Type</th><th class="p-3">Statut</th><th class="p-3">Visibilité</th><th class="p-3">MàJ</th><th class="p-3">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $id=(int)$r['id']; $isPub=!empty($r['is_published']); ?>
      <tr class="border-b border-slate-100">
        <td class="p-3"><p class="font-semibold"><?= htmlspecialchars((string)$r['title']) ?></p><code class="text-xs">/formations/page/<?= htmlspecialchars((string)$r['slug']) ?></code></td>
        <td class="p-3 text-xs"><?= htmlspecialchars((string)($r['doc_structure'] ?? 'single')) ?></td>
        <td class="p-3"><?php $status=(string)($r['status'] ?? 'draft'); include __DIR__.'/partials/custom_page_status_badge.php'; ?></td>
        <td class="p-3 text-xs"><?= htmlspecialchars((string)($r['visibility_level'] ?? 'tenant')) ?></td>
        <td class="p-3 text-xs"><?= htmlspecialchars((string)($r['updated_at'] ?? '')) ?></td>
        <td class="p-3 text-xs space-x-2 whitespace-nowrap">
          <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/modifier')) ?>" class="font-semibold text-slate-700">Éditer</a>
          <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/previsualiser')) ?>" target="_blank" class="font-semibold text-sky-700">Aperçu</a>
          <?php if ($isPub): ?><a href="<?= htmlspecialchars(url('formations/page/'.rawurlencode((string)$r['slug']))) ?>" target="_blank" class="font-semibold text-emerald-700">Public</a><?php endif; ?>
          <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/dupliquer')) ?>" class="inline"><?= \App\Core\Csrf::field() ?><button class="text-indigo-700 font-semibold bg-transparent border-0 p-0">Dupliquer</button></form>
          <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/supprimer')) ?>" class="inline"><?= \App\Core\Csrf::field() ?><button class="text-rose-700 font-semibold bg-transparent border-0 p-0">Supprimer</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($rows===[]): ?><tr><td colspan="6" class="p-8 text-center text-slate-500">Aucun document.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
