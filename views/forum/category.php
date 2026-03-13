<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <!-- Breadcrumb -->
  <nav class="text-[9px] font-black uppercase tracking-[0.25em] text-neutral-500 mb-6">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-orange-500">Forum</a>
    <span class="mx-2">›</span>
    <span class="text-white"><?= htmlspecialchars($category['name'] ?? '') ?></span>
  </nav>

  <!-- Header -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <span class="w-14 h-14 bg-white/[0.03] border border-white/[0.08] flex items-center justify-center text-2xl">📁</span>
      <div>
        <p class="text-[8px] font-black uppercase tracking-[0.5em] text-orange-500/60">Catégorie</p>
        <h1 class="text-3xl md:text-4xl font-black italic uppercase tracking-tighter text-white"><?= htmlspecialchars($category['name']) ?></h1>
        <?php if (!empty($category['description'])): ?>
          <p class="text-sm text-neutral-500 mt-1"><?= htmlspecialchars($category['description']) ?></p>
        <?php endif; ?>
        <p class="text-xs text-neutral-600 mt-2"><?= (int) $totalTopics ?> sujets · Page <?= (int) $page ?>/<?= (int) $totalPages ?></p>
      </div>
    </div>
    <div class="flex flex-wrap gap-3">
      <a href="<?= $baseUrl ?>/forum/new-topic?category=<?= htmlspecialchars($category['slug']) ?>" class="bg-orange-500 hover:bg-orange-400 text-black px-6 py-3 text-[10px] font-black uppercase tracking-wider transition">Nouveau sujet</a>
    </div>
  </div>

  <!-- Filters / Sort -->
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <span class="text-[8px] font-black text-neutral-600 uppercase">Tri :</span>
    <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($category['slug']) ?>?sort=activity" class="text-[10px] font-bold px-3 py-1.5 border <?= ($sort ?? '') === 'activity' ? 'border-indigo-500 text-indigo-300' : 'border-white/10 text-neutral-500 hover:text-white' ?> transition">Activité récente</a>
    <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($category['slug']) ?>?sort=newest" class="text-[10px] font-bold px-3 py-1.5 border <?= ($sort ?? '') === 'newest' ? 'border-indigo-500 text-indigo-300' : 'border-white/10 text-neutral-500 hover:text-white' ?> transition">Nouveaux d'abord</a>
    <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($category['slug']) ?>?sort=replies" class="text-[10px] font-bold px-3 py-1.5 border <?= ($sort ?? '') === 'replies' ? 'border-indigo-500 text-indigo-300' : 'border-white/10 text-neutral-500 hover:text-white' ?> transition">Plus de réponses</a>
  </div>

  <!-- Table header (desktop) -->
  <div class="hidden md:grid md:grid-cols-[1fr_80px_80px_130px] gap-4 py-2 border-b border-white/5 text-[8px] font-black uppercase tracking-[0.28em] text-neutral-700 mb-0">
    <span>Sujet</span>
    <span><?= $labels['replies'] ?? 'Réponses' ?></span>
    <span><?= $labels['views'] ?? 'Lectures' ?></span>
    <span><?= $labels['last_activity'] ?? 'Dernier signal' ?></span>
  </div>

  <!-- Topic list -->
  <div class="divide-y divide-white/5">
    <?php foreach ($topics ?? [] as $t): ?>
      <?php
      $rowClass = 'topic-row border-l-2 border-transparent hover:border-indigo-500/40';
      if (!empty($t['is_locked'])) $rowClass .= ' topic-locked';
      if (!empty($t['is_pinned'])) $rowClass .= ' topic-pinned';
      ?>
      <a href="<?= $baseUrl ?>/forum/topic/<?= (int) $t['id'] ?>" class="<?= $rowClass ?> block py-4 px-2 -mx-2 hover:bg-white/[0.02] transition">
        <div class="md:grid md:grid-cols-[1fr_80px_80px_130px] md:gap-4 md:items-center">
          <div>
            <div class="flex flex-wrap items-center gap-2 mb-1">
              <?php if (!empty($t['is_pinned'])): ?><span class="text-[7px] font-black uppercase text-indigo-400 border border-indigo-500/30 px-1.5 py-0.5">Épinglé</span><?php endif; ?>
              <?php if (!empty($t['is_locked'])): ?><span class="text-[7px] font-black uppercase text-amber-400 border border-amber-500/30 px-1.5 py-0.5">Verrouillé</span><?php endif; ?>
            </div>
            <h2 class="text-sm font-black italic uppercase text-white hover:text-orange-400 transition"><?= htmlspecialchars($t['title']) ?></h2>
            <p class="text-[9px] text-neutral-600 mt-0.5">ID #<?= (int) $t['id'] ?> · Par <?= htmlspecialchars($t['author_name'] ?? '') ?> · <?= $t['created_at'] ? date('d/m H:i', strtotime($t['created_at'])) : '' ?></p>
          </div>
          <div class="mt-2 md:mt-0">
            <span class="text-sm font-black text-white"><?= (int) ($t['post_count'] ?? 0) ?></span>
            <span class="text-[9px] text-neutral-600"> <?= $labels['replies'] ?? 'réponses' ?></span>
          </div>
          <div class="mt-1 md:mt-0">
            <span class="text-sm font-black text-white"><?= (int) ($t['view_count'] ?? 0) ?></span>
            <span class="text-[9px] text-neutral-600"> <?= $labels['views'] ?? 'lectures' ?></span>
          </div>
          <div class="mt-1 md:mt-0 text-[10px] text-neutral-500">
            <?= $t['last_post_at'] ? date('H:i d/m', strtotime($t['last_post_at'])) : '—' ?>
            <?php if (!empty($t['last_post_author_name'])): ?>
              <br><span class="text-neutral-600">→ <?= htmlspecialchars($t['last_post_author_name']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($topics)): ?>
    <p class="py-12 text-center text-neutral-500">Aucun sujet dans cette catégorie. <a href="<?= $baseUrl ?>/forum/new-topic?category=<?= htmlspecialchars($category['slug']) ?>" class="text-orange-400 hover:text-orange-300">Créer le premier</a>.</p>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="flex flex-wrap justify-center gap-2 mt-8">
      <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
        <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($category['slug']) ?>?page=<?= $i ?>&sort=<?= htmlspecialchars($sort ?? 'activity') ?>" class="min-w-[2.5rem] py-2 text-center border text-[10px] font-bold <?= $i === $page ? 'bg-orange-500 border-orange-500 text-black' : 'border-white/[0.08] text-neutral-600 hover:text-white' ?> transition"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <!-- Subcategories -->
  <?php if (!empty($subcategories)): ?>
    <section class="mt-12">
      <h2 class="text-[8px] font-black uppercase tracking-[0.4em] text-neutral-600 mb-4">Sous-catégories</h2>
      <div class="grid gap-4 md:grid-cols-2">
        <?php foreach ($subcategories as $sub): ?>
          <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($sub['slug']) ?>" class="border border-white/10 bg-[#0a0a0c] p-4 hover:border-orange-500/30 transition flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="w-10 h-10 bg-white/5 flex items-center justify-center text-lg">📁</span>
              <div>
                <p class="text-sm font-black italic uppercase text-white"><?= htmlspecialchars($sub['name']) ?></p>
                <p class="text-[9px] text-neutral-500"><?= (int) ($sub['topic_count'] ?? 0) ?> sujets</p>
              </div>
            </div>
            <span class="text-neutral-500">→</span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
