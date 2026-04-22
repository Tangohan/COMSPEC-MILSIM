<li class="rounded-lg border border-slate-200 px-3 py-2 text-xs">
  <div class="flex justify-between gap-2"><strong>v<?= (int)($rev['version_no'] ?? 0) ?></strong><span><?= htmlspecialchars((string)($rev['revision_type'] ?? 'update')) ?></span></div>
  <p class="text-slate-500 mt-1"><?= htmlspecialchars((string)($rev['summary_diff'] ?? '')) ?></p>
  <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('pages-html/' . (int)$customPage['id'] . '/versions/' . (int)$rev['id'] . '/restaurer')) ?>" class="mt-2">
    <?= \App\Core\Csrf::field() ?>
    <button type="submit" class="text-indigo-700 font-semibold">Restaurer</button>
  </form>
</li>
