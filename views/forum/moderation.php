<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$pendingReports = $pendingReports ?? [];
$handledReports = $handledReports ?? [];
$panelTitle = $labels['moderation_panel'] ?? 'Terminal de Contrôle';
?>
<div class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <nav class="flex items-center gap-2 text-[9px] font-black uppercase tracking-[0.25em] text-neutral-800 mb-8">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-neutral-500 transition-colors">Forum</a>
    <span>›</span>
    <span class="text-rose-500"><?= htmlspecialchars($panelTitle) ?></span>
  </nav>

  <div class="flex items-center gap-3 mb-8">
    <span class="w-1.5 h-1.5 bg-rose-600 rounded-full animate-pulse"></span>
    <h1 class="text-2xl md:text-3xl font-black italic uppercase text-white"><?= htmlspecialchars($panelTitle) ?></h1>
  </div>

  <?php $success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error'); ?>
  <?php if ($success): ?>
    <p class="mb-4 p-3 border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-sm"><?= htmlspecialchars($success) ?></p>
  <?php endif; ?>
  <?php if ($error): ?>
    <p class="mb-4 p-3 border border-rose-500/30 bg-rose-500/10 text-rose-400 text-sm"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <!-- Onglets -->
  <div class="border-b border-white/10 mb-6">
    <div class="flex gap-1 overflow-x-auto">
      <button type="button" class="mod-tab px-4 py-3 text-[10px] font-black uppercase tracking-wider border-b-2 border-orange-500 text-orange-400 whitespace-nowrap" data-tab="reports">
        Signalements <?php if (count($pendingReports) > 0): ?><span class="ml-1.5 px-1.5 py-0.5 bg-rose-500/20 text-rose-400 rounded text-[9px]"><?= count($pendingReports) ?></span><?php endif; ?>
      </button>
      <button type="button" class="mod-tab px-4 py-3 text-[10px] font-black uppercase tracking-wider border-b-2 border-transparent text-neutral-500 hover:text-white whitespace-nowrap" data-tab="detections">
        Détections
      </button>
      <button type="button" class="mod-tab px-4 py-3 text-[10px] font-black uppercase tracking-wider border-b-2 border-transparent text-neutral-500 hover:text-white whitespace-nowrap" data-tab="bot">
        Bot de modération
      </button>
    </div>
  </div>

  <!-- Panneau Signalements -->
  <div id="mod-panel-reports" class="mod-panel space-y-6">
    <section class="border border-white/10 bg-[#0a0a0c] rounded-lg overflow-hidden">
      <div class="px-4 py-3 border-b border-white/5 bg-black/20">
        <h2 class="text-sm font-black uppercase tracking-wider text-white">En attente</h2>
        <p class="text-[9px] text-neutral-500 mt-0.5">Signalements à traiter</p>
      </div>
      <?php if (empty($pendingReports)): ?>
        <div class="p-8 text-center border-b border-white/5">
          <p class="text-emerald-400 font-bold">Aucun signalement en attente.</p>
          <p class="text-[10px] text-neutral-500 mt-1">Les nouveaux signalements apparaîtront ici.</p>
        </div>
      <?php else: ?>
        <ul class="divide-y divide-white/[0.04]">
          <?php foreach ($pendingReports as $r): ?>
            <li class="p-4 hover:bg-white/[0.02] transition">
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                  <span class="text-[8px] font-black uppercase text-rose-400">#<?= (int) $r['id'] ?></span>
                  <p class="text-sm text-neutral-200 mt-1"><?= nl2br(htmlspecialchars($r['reason'] ?? '—')) ?></p>
                  <p class="text-[10px] text-neutral-500 mt-2">Par <strong><?= htmlspecialchars($r['reporter_name'] ?? '') ?></strong> · <?= $r['created_at'] ? date('d/m/Y H:i', strtotime($r['created_at'])) : '' ?></p>
                  <?php
                  $topicId = (int) ($r['post_topic_id'] ?? $r['topic_id'] ?? 0);
                  if ($topicId && !empty($r['topic_title'])):
                  ?>
                    <p class="text-xs text-neutral-400 mt-1">Sujet : <a href="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>" class="text-orange-400 hover:text-orange-300"><?= htmlspecialchars($r['topic_title']) ?></a></p>
                  <?php endif; ?>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <?php if ($topicId): ?>
                    <a href="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>" class="px-3 py-1.5 text-[9px] font-black uppercase border border-white/10 text-neutral-400 hover:text-white hover:border-white/20 transition">Voir le sujet</a>
                  <?php endif; ?>
                  <form method="post" action="<?= $baseUrl ?>/forum/report/<?= (int) $r['id'] ?>/handle" class="inline">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="px-4 py-2 bg-rose-500 hover:bg-rose-400 text-black text-[10px] font-black uppercase transition">Marquer traité</button>
                  </form>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="border border-white/10 bg-[#0a0a0c] rounded-lg overflow-hidden">
      <div class="px-4 py-3 border-b border-white/5 bg-black/20">
        <h2 class="text-sm font-black uppercase tracking-wider text-white">Traités récemment</h2>
        <p class="text-[9px] text-neutral-500 mt-0.5">Derniers signalements clos</p>
      </div>
      <?php if (empty($handledReports)): ?>
        <div class="p-6 text-center">
          <p class="text-neutral-500 text-sm">Aucun signalement traité récemment.</p>
        </div>
      <?php else: ?>
        <ul class="divide-y divide-white/[0.04]">
          <?php foreach ($handledReports as $r): ?>
            <li class="p-4 text-sm">
              <span class="text-[8px] font-black uppercase text-neutral-600">#<?= (int) $r['id'] ?></span>
              <p class="text-neutral-400 mt-1 line-clamp-2"><?= htmlspecialchars(mb_substr($r['reason'] ?? '', 0, 120)) ?><?= mb_strlen($r['reason'] ?? '') > 120 ? '…' : '' ?></p>
              <p class="text-[9px] text-neutral-600 mt-2">Par <?= htmlspecialchars($r['reporter_name'] ?? '') ?> · Traité par <?= htmlspecialchars($r['handled_by_name'] ?? '') ?> · <?= !empty($r['handled_at']) ? date('d/m/Y H:i', strtotime($r['handled_at'])) : '' ?></p>
              <?php $topicId = (int) ($r['post_topic_id'] ?? $r['topic_id'] ?? 0); if ($topicId && !empty($r['topic_title'])): ?>
                <a href="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>" class="text-[9px] text-orange-500 hover:text-orange-400 mt-1 inline-block"><?= htmlspecialchars($r['topic_title']) ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <!-- Panneau Détections -->
  <div id="mod-panel-detections" class="mod-panel hidden border border-white/10 bg-[#0a0a0c] rounded-lg overflow-hidden">
    <div class="px-4 py-3 border-b border-white/5 bg-black/20">
      <h2 class="text-sm font-black uppercase tracking-wider text-white">Détections automatiques</h2>
      <p class="text-[9px] text-neutral-500 mt-0.5">Alertes spam, doublons, liens suspects, contenus à risque</p>
    </div>
    <div class="p-12 text-center">
      <p class="text-neutral-500 mb-2">Les alertes de détection apparaîtront ici lorsqu’elles seront activées.</p>
      <p class="text-[10px] text-neutral-600">Modules prévus : spam, doublons, liens blacklistés, messages trop courts, CAPS excessives.</p>
    </div>
  </div>

  <!-- Panneau Bot -->
  <div id="mod-panel-bot" class="mod-panel hidden border border-white/10 bg-[#0a0a0c] rounded-lg overflow-hidden">
    <div class="px-4 py-3 border-b border-white/5 bg-black/20">
      <h2 class="text-sm font-black uppercase tracking-wider text-white">Bot de modération</h2>
      <p class="text-[9px] text-neutral-500 mt-0.5">Actions automatiques et historique</p>
    </div>
    <div class="p-12 text-center">
      <p class="text-neutral-500 mb-2">L’historique des actions du bot (avertissements, masquages, verrouillages) sera affiché ici.</p>
      <p class="text-[10px] text-neutral-600">Familles : détection (spam, liens, doublons…) et actions (flag, hide, close).</p>
    </div>
  </div>
</div>

<script>
(function() {
  document.querySelectorAll('.mod-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      var t = this.getAttribute('data-tab');
      document.querySelectorAll('.mod-tab').forEach(function(bt) {
        bt.classList.remove('border-orange-500', 'text-orange-400');
        bt.classList.add('border-transparent', 'text-neutral-500');
      });
      this.classList.remove('border-transparent', 'text-neutral-500');
      this.classList.add('border-orange-500', 'text-orange-400');
      document.querySelectorAll('.mod-panel').forEach(function(panel) {
        panel.classList.add('hidden');
      });
      var panel = document.getElementById('mod-panel-' + t);
      if (panel) panel.classList.remove('hidden');
    });
  });
})();
</script>
