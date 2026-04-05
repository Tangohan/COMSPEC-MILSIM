<?php
$baseUrl = url('');
$artifacts = $artifacts ?? [];
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 30);
$missingTables = !empty($missingTables);
?>
<div class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <nav class="flex items-center gap-2 text-[9px] font-black uppercase tracking-[0.25em] text-neutral-800 mb-8">
    <a href="<?= $baseUrl ?>/back-office" class="hover:text-neutral-500 transition-colors">Back-office</a>
    <span>›</span>
    <span class="text-amber-500">Modération fichiers</span>
  </nav>

  <h1 class="text-2xl md:text-3xl font-black italic uppercase text-white mb-2">File & quarantaine</h1>
  <p class="text-sm text-neutral-500 mb-6">Artefacts en attente (forum, documents, scores texte courrier).</p>

  <?php $success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error'); ?>
  <?php if ($success): ?>
    <p class="mb-4 p-3 border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-sm"><?= htmlspecialchars($success) ?></p>
  <?php endif; ?>
  <?php if ($error): ?>
    <p class="mb-4 p-3 border border-rose-500/30 bg-rose-500/10 text-rose-400 text-sm"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <?php if ($missingTables): ?>
    <p class="p-4 border border-amber-500/30 bg-amber-500/10 text-amber-200 text-sm">Tables de modération absentes — exécutez les migrations (setup-database / run-migrations).</p>
  <?php elseif (empty($artifacts)): ?>
    <p class="text-emerald-400 font-bold">Aucun élément en file d’attente.</p>
  <?php else: ?>
    <p class="text-xs text-neutral-500 mb-4"><?= (int) $total ?> élément(s) · page <?= (int) $page ?></p>
    <ul class="space-y-3">
      <?php foreach ($artifacts as $a): ?>
        <li class="border border-white/10 rounded-lg p-4 bg-[#0a0a0c]">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <span class="text-[9px] font-black uppercase text-amber-400">#<?= (int) ($a['id'] ?? 0) ?> · <?= htmlspecialchars((string) ($a['source_type'] ?? '')) ?></span>
              <p class="text-sm text-neutral-200 mt-1">État : <strong><?= htmlspecialchars((string) ($a['state'] ?? '')) ?></strong> · score <?= (int) ($a['risk_score'] ?? 0) ?></p>
              <?php if (!empty($a['original_name'])): ?>
                <p class="text-xs text-neutral-400 mt-1">Fichier : <?= htmlspecialchars((string) $a['original_name']) ?></p>
              <?php endif; ?>
              <?php if (!empty($a['file_path'])): ?>
                <p class="text-[10px] text-neutral-500 mt-1 break-all"><?= htmlspecialchars((string) $a['file_path']) ?></p>
              <?php endif; ?>
              <?php
              $codes = $a['reason_codes'] ?? [];
              if (is_array($codes) && $codes !== []):
              ?>
                <p class="text-[10px] text-rose-300 mt-1">Motifs : <?= htmlspecialchars(implode(', ', $codes)) ?></p>
              <?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
              <form method="post" action="<?= $baseUrl ?>/back-office/content-moderation/<?= (int) ($a['id'] ?? 0) ?>/approve" class="inline">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-black uppercase rounded">Approuver</button>
              </form>
              <form method="post" action="<?= $baseUrl ?>/back-office/content-moderation/<?= (int) ($a['id'] ?? 0) ?>/reject" class="inline">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="px-3 py-2 bg-rose-600 hover:bg-rose-500 text-white text-[10px] font-black uppercase rounded">Rejeter</button>
              </form>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
