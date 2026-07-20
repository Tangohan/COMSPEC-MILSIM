<?php
declare(strict_types=1);

$customPage = $customPage ?? [];
$pageId = (int) ($customPage['id'] ?? 0);
$revisions = $customPageRevisions ?? [];
$revA = (int) ($diffRevA ?? 0);
$revB = (int) ($diffRevB ?? 0);
$rows = $diffRows ?? null;
?>
<div class="max-w-4xl mx-auto p-4 space-y-4">
  <div class="flex items-center justify-between gap-3">
    <h1 class="text-lg font-black text-slate-900">Comparer des versions — <?= htmlspecialchars((string) ($customPage['title'] ?? '')) ?></h1>
    <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/' . $pageId . '/modifier')) ?>" class="tc-btn-primary tc-btn-ghost text-xs">Retour à l’éditeur</a>
  </div>

  <form method="get" action="<?= htmlspecialchars(training_lms_admin_url('pages-html/' . $pageId . '/versions/comparer')) ?>" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
    <div>
      <label class="block text-xs font-bold text-slate-700 mb-1">Version A (avant)</label>
      <select name="a" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
        <option value="">—</option>
        <?php foreach ($revisions as $rev): $rid = (int) ($rev['id'] ?? 0); ?>
        <option value="<?= $rid ?>"<?= $rid === $revA ? ' selected' : '' ?>>v<?= (int) ($rev['version_no'] ?? 0) ?> — <?= htmlspecialchars((string) ($rev['created_at'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-bold text-slate-700 mb-1">Version B (après)</label>
      <select name="b" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
        <option value="">—</option>
        <?php foreach ($revisions as $rev): $rid = (int) ($rev['id'] ?? 0); ?>
        <option value="<?= $rid ?>"<?= $rid === $revB ? ' selected' : '' ?>>v<?= (int) ($rev['version_no'] ?? 0) ?> — <?= htmlspecialchars((string) ($rev['created_at'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="tc-btn-primary tc-btn-emerald text-xs">Comparer</button>
  </form>

  <?php if ($revA > 0 && $revB > 0 && $rows === null): ?>
  <p class="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-sm text-amber-950">Une des deux versions est introuvable.</p>
  <?php elseif ($rows !== null): ?>
  <div class="rounded-xl border border-slate-200 bg-white p-4 font-mono text-xs leading-relaxed overflow-x-auto">
    <?php if ($rows === []): ?>
    <p class="text-slate-500">Aucune différence textuelle entre ces deux versions.</p>
    <?php else: foreach ($rows as $row):
        $cls = match ($row['type']) {
            'added' => 'bg-emerald-50 text-emerald-900 border-l-4 border-emerald-500',
            'removed' => 'bg-rose-50 text-rose-900 border-l-4 border-rose-500 line-through decoration-rose-400',
            default => 'text-slate-600',
        };
        $prefix = match ($row['type']) { 'added' => '+ ', 'removed' => '- ', default => '  ' };
        ?>
    <div class="px-2 py-0.5 <?= $cls ?>"><?= htmlspecialchars($prefix . $row['text']) ?></div>
    <?php endforeach; endif; ?>
  </div>
  <?php else: ?>
  <p class="text-sm text-slate-500">Choisissez deux versions ci-dessus pour afficher les différences.</p>
  <?php endif; ?>
</div>
