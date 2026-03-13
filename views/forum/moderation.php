<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <nav class="text-[9px] font-black uppercase tracking-[0.25em] text-neutral-500 mb-6">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-orange-500">Forum</a>
    <span class="mx-2">›</span>
    <span class="text-rose-500"><?= $labels['moderation_panel'] ?? 'Terminal de Contrôle' ?></span>
  </nav>

  <div class="flex items-center gap-2 mb-8">
    <span class="w-1.5 h-1.5 bg-rose-600 rounded-full animate-pulse"></span>
    <h1 class="text-2xl font-black italic uppercase text-white"><?= $labels['moderation_panel'] ?? 'Terminal de Contrôle' ?></h1>
  </div>

  <?php if (empty($pendingReports)): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/20 p-8 text-center">
      <p class="text-emerald-400 font-bold">Aucun signalement en attente.</p>
    </div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($pendingReports as $r): ?>
        <div class="bg-rose-500/5 border border-rose-500/20 p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <span class="text-[8px] font-black uppercase text-rose-400">Signalement #<?= (int) $r['id'] ?></span>
              <p class="text-sm text-neutral-300 mt-1">Raison : <?= htmlspecialchars($r['reason'] ?? '—') ?></p>
              <p class="text-[10px] text-neutral-500 mt-1">Par <?= htmlspecialchars($r['reporter_name'] ?? '') ?> · <?= $r['created_at'] ? date('d/m/Y H:i', strtotime($r['created_at'])) : '' ?></p>
              <?php if (!empty($r['topic_title'])): ?>
                <p class="text-xs text-neutral-400 mt-2">Sujet : <a href="<?= $baseUrl ?>/forum/topic/<?= (int) ($r['post_topic_id'] ?? $r['topic_id']) ?>" class="text-orange-400 hover:text-orange-300"><?= htmlspecialchars($r['topic_title']) ?></a></p>
              <?php endif; ?>
            </div>
            <form method="post" action="<?= $baseUrl ?>/forum/report/<?= (int) $r['id'] ?>/handle">
              <?= \App\Core\Csrf::field() ?>
              <button type="submit" class="bg-rose-500 hover:bg-rose-400 text-black px-4 py-2 text-[10px] font-black uppercase">Traiter</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
