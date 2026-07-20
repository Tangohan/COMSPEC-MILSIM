<?php
$rows = is_array($customPagesRows ?? null) ? $customPagesRows : [];
$metrics = is_array($customPagesMetrics ?? null) ? $customPagesMetrics : [];
$cpSearch = trim((string) ($customPagesSearch ?? ''));
$cpStatus = trim((string) ($customPagesStatus ?? ''));
$cpDocStructure = trim((string) ($customPagesDocStructure ?? ''));
$cpTag = trim((string) ($customPagesTagSlug ?? ''));
$cpAllTags = is_array($customPagesAllTags ?? null) ? $customPagesAllTags : [];
$cpTagsByPageId = is_array($customPagesTagsByPageId ?? null) ? $customPagesTagsByPageId : [];
$cpBaseUrl = training_lms_admin_url('pages-html');
$cpHasFilter = $cpSearch !== '' || $cpStatus !== '' || $cpDocStructure !== '' || $cpTag !== '';
$cpStatusOptions = ['draft' => 'Brouillon', 'review' => 'En révision', 'scheduled' => 'Programmé', 'published' => 'Publié', 'archived' => 'Archivé'];
$cpStructureOptions = ['single' => 'Page unique', 'handbook' => 'Manuel (chapitres)'];
$cpTotal = (int) ($customPagesTotal ?? count($rows));
$cpPage = max(1, (int) ($customPagesPage ?? 1));
$cpTotalPages = max(1, (int) ($customPagesTotalPages ?? 1));
$cpPageUrl = static function (int $p) use ($cpBaseUrl, $cpSearch, $cpStatus, $cpDocStructure, $cpTag): string {
    $q = array_filter(['q' => $cpSearch, 'status' => $cpStatus, 'doc_structure' => $cpDocStructure, 'tag' => $cpTag, 'page' => $p > 1 ? $p : null], static fn ($v) => $v !== null && $v !== '');
    return $q === [] ? $cpBaseUrl : $cpBaseUrl . '?' . http_build_query($q);
};
?>
<section class="tc-panel p-6 md:p-8 space-y-4">
  <p class="tc-kicker">Centre des opérations — DOC HTML</p>
  <h1 class="tc-hero-title">Studio documentaire / manuel</h1>
  <div class="grid md:grid-cols-5 gap-3 text-xs">
    <?php foreach (['recently_modified'=>'Modifiés 7j','forgotten_drafts'=>'Brouillons oubliés','scheduled'=>'Programmés','published_without_theme'=>'Publiés sans thème','never_viewed'=>'Publiés jamais ouverts'] as $k=>$l): ?>
      <div class="rounded-lg border border-slate-200 bg-white px-3 py-2"><p class="text-slate-500"><?= $l ?></p><p class="text-lg font-black"><?= (int)($metrics[$k] ?? 0) ?></p></div>
    <?php endforeach; ?>
  </div>
  <div class="flex flex-wrap items-end justify-between gap-3">
    <form method="get" action="<?= htmlspecialchars($cpBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-center gap-1.5">
      <input type="search" name="q" value="<?= htmlspecialchars($cpSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Titre, slug, résumé…" class="h-9 rounded-lg border border-slate-300 px-3 text-sm">
      <select name="status" class="h-9 rounded-lg border border-slate-300 px-2 text-sm">
        <option value="">Tous statuts</option>
        <?php foreach ($cpStatusOptions as $val => $label): ?>
        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $cpStatus === $val ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <select name="doc_structure" class="h-9 rounded-lg border border-slate-300 px-2 text-sm">
        <option value="">Tous types</option>
        <?php foreach ($cpStructureOptions as $val => $label): ?>
        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $cpDocStructure === $val ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($cpAllTags !== []): ?>
      <select name="tag" class="h-9 rounded-lg border border-slate-300 px-2 text-sm">
        <option value="">Tous tags</option>
        <?php foreach ($cpAllTags as $tg): ?>
        <option value="<?= htmlspecialchars((string) $tg['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= $cpTag === $tg['slug'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $tg['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <button type="submit" class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Filtrer</button>
      <?php if ($cpHasFilter): ?>
      <a href="<?= htmlspecialchars($cpBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="h-9 inline-flex items-center rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-500 hover:bg-slate-50">Réinitialiser</a>
      <?php endif; ?>
    </form>
    <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/nouvelle')) ?>" class="tc-btn-primary tc-btn-emerald text-sm">Nouveau document</a>
  </div>
</section>

<?php if ($rows === [] && $cpHasFilter): ?>
<div class="tc-panel p-10 text-center text-slate-600">
  <p class="text-sm font-semibold text-slate-800">Aucun document ne correspond à ces filtres.</p>
  <a href="<?= htmlspecialchars($cpBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost mt-4 inline-flex">Réinitialiser les filtres</a>
</div>
<?php elseif ($rows === []): ?>
<div class="tc-panel p-10 text-center text-slate-600">Aucun document pour cette communauté.</div>
<?php else: ?>
<div class="tc-table-wrap overflow-x-auto">
  <table class="min-w-[860px]">
    <thead>
      <tr><th>Titre</th><th>Type</th><th>Statut</th><th>Visibilité</th><th>MàJ</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): $id=(int)$r['id']; $isPub=!empty($r['is_published']); ?>
      <tr>
        <td><p class="font-semibold text-slate-900"><?= htmlspecialchars((string)$r['title']) ?></p><code class="text-xs text-slate-500">/formations/page/<?= htmlspecialchars((string)$r['slug']) ?></code>
          <?php $rowTags = $cpTagsByPageId[$id] ?? []; if ($rowTags !== []): ?>
          <div class="mt-1 flex flex-wrap gap-1">
            <?php foreach ($rowTags as $rt): ?>
            <span class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"><?= htmlspecialchars((string) $rt['name']) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </td>
        <td class="text-xs text-slate-600"><?= htmlspecialchars($cpStructureOptions[(string)($r['doc_structure'] ?? 'single')] ?? (string)($r['doc_structure'] ?? 'single')) ?></td>
        <td><?php $status=(string)($r['status'] ?? 'draft'); include __DIR__.'/partials/custom_page_status_badge.php'; ?></td>
        <td class="text-xs text-slate-600"><?= htmlspecialchars((string)($r['visibility_level'] ?? 'tenant')) ?></td>
        <td class="text-xs text-slate-500 whitespace-nowrap"><?= htmlspecialchars((string)($r['updated_at'] ?? '')) ?></td>
        <td class="text-xs space-x-2 whitespace-nowrap">
          <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/modifier')) ?>" class="font-semibold text-slate-700">Éditer</a>
          <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/previsualiser')) ?>" target="_blank" class="font-semibold text-sky-700">Aperçu</a>
          <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/pdf')) ?>" class="font-semibold text-slate-700">PDF</a>
          <?php if ($isPub): ?><a href="<?= htmlspecialchars(url('formations/page/'.rawurlencode((string)$r['slug']))) ?>" target="_blank" class="font-semibold text-emerald-700">Public</a><?php endif; ?>
          <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/dupliquer')) ?>" class="inline"><?= \App\Core\Csrf::field() ?><button class="text-indigo-700 font-semibold bg-transparent border-0 p-0">Dupliquer</button></form>
          <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('pages-html/'.$id.'/supprimer')) ?>" class="inline" onsubmit="return confirm('Supprimer définitivement « <?= htmlspecialchars(addslashes((string)$r['title']), ENT_QUOTES, 'UTF-8') ?> » ? Cette action est irréversible.');"><?= \App\Core\Csrf::field() ?><button class="text-rose-700 font-semibold bg-transparent border-0 p-0">Supprimer</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($cpTotalPages > 1): ?>
<div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
  <p><?= $cpTotal ?> document<?= $cpTotal > 1 ? 's' : '' ?> · page <?= $cpPage ?> / <?= $cpTotalPages ?></p>
  <div class="flex gap-1.5">
    <?php if ($cpPage > 1): ?>
    <a href="<?= htmlspecialchars($cpPageUrl($cpPage - 1), ENT_QUOTES, 'UTF-8') ?>" class="h-8 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 font-semibold text-slate-700 hover:bg-slate-50">← Précédent</a>
    <?php endif; ?>
    <?php if ($cpPage < $cpTotalPages): ?>
    <a href="<?= htmlspecialchars($cpPageUrl($cpPage + 1), ENT_QUOTES, 'UTF-8') ?>" class="h-8 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 font-semibold text-slate-700 hover:bg-slate-50">Suivant →</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
