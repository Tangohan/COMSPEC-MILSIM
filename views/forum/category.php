<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$categorySlug = $category['slug'] ?? '';
$categoryId = (int) ($category['id'] ?? 0);
$buildUrl = $buildCategoryUrl ?? function ($o = []) use ($baseUrl, $categorySlug) {
  return $baseUrl . '/forum/category/' . $categorySlug . (empty($o) ? '' : '?' . http_build_query(array_filter($o)));
};
$hasActiveFilters = ($filter ?? '') !== '' || ($sort ?? 'activity') !== 'activity' || ($q ?? '') !== '';
?>
<main class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 bg-[#f8fafc] min-h-[60vh]">
  <!-- Fil d'Ariane -->
  <nav class="flex items-center gap-2 text-[9px] font-black uppercase tracking-[0.25em] text-slate-600 mb-10 header-anim" style="animation-delay:0ms">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-emerald-700 transition-colors">Forum</a>
    <span class="text-slate-400">›</span>
    <span class="text-slate-800"><?= htmlspecialchars($category['name'] ?? '') ?></span>
  </nav>

  <!-- En-tête catégorie -->
  <div class="flex items-start justify-between gap-6 mb-10 header-anim" style="animation-delay:40ms">
    <div class="flex items-start gap-5">
      <div class="shrink-0 w-14 h-14 flex items-center justify-center text-3xl bg-white border border-slate-200 rounded-lg shadow-sm">
        <?= !empty($category['icon']) ? htmlspecialchars($category['icon']) : '⚠️' ?>
      </div>
      <div>
        <div class="flex items-center gap-3 mb-2">
          <span class="h-px w-6 bg-emerald-500/50"></span>
          <span class="text-[8px] font-black uppercase tracking-[0.5em] text-emerald-700">Catégorie</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-black italic uppercase tracking-tighter text-slate-900 leading-none mb-2">
          <?= htmlspecialchars($category['name']) ?>
        </h1>
        <?php if (!empty($category['description'])): ?>
          <p class="text-[11px] text-slate-600 font-medium max-w-lg leading-relaxed"><?= htmlspecialchars($category['description']) ?></p>
        <?php endif; ?>
        <div class="flex items-center gap-4 mt-3 text-[9px] font-black uppercase tracking-widest text-slate-500">
          <span><?= (int) $totalTopics ?> sujets</span>
          <?php if ($totalPages > 1): ?><span>Page <?= (int) $page ?> / <?= (int) $totalPages ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="flex items-center gap-2 shrink-0">
      <button type="button" id="sub-btn" data-category-id="<?= $categoryId ?>" data-subscribed="<?= !empty($isSubscribed) ? '1' : '0' ?>" class="flex items-center gap-2 px-4 py-3 text-[9px] font-black uppercase tracking-[0.2em] border transition-colors rounded-md bg-white border-slate-200 text-slate-600 hover:border-emerald-300 <?= !empty($isSubscribed) ? 'border-emerald-300 text-emerald-800 bg-emerald-50' : '' ?>">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        <span id="sub-label"><?= !empty($isSubscribed) ? 'Abonné' : 'Suivre' ?></span>
      </button>
      <?php if (!empty($canCreate)): ?>
        <a href="<?= $baseUrl ?>/forum/new-topic?category_id=<?= $categoryId ?>" class="flex items-center gap-2.5 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-[9px] font-black uppercase tracking-[0.25em] transition-colors rounded-md">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
          Nouveau sujet
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recherche + Filtres -->
  <div class="mb-4 border border-slate-200 bg-white rounded-lg shadow-sm p-3 md:p-4 header-anim" style="animation-delay:50ms">
    <form method="get" action="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($categorySlug) ?>" class="flex flex-col lg:flex-row gap-2 lg:items-center">
      <input type="hidden" name="category" value="<?= htmlspecialchars($categorySlug) ?>">
      <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Rechercher dans cette catégorie" class="flex-1 bg-slate-50 border border-slate-200 px-3 py-2 text-xs tracking-wide text-slate-900 rounded-md focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20">
      <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-md">Rechercher</button>
    </form>
    <div class="mt-3 flex flex-wrap gap-2">
      <a href="<?= $buildUrl(['filter' => '']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border rounded-md <?= ($filter ?? '') === '' ? 'border-emerald-500 text-emerald-800 bg-emerald-50' : 'border-slate-200 text-slate-600 hover:border-slate-300 bg-white' ?>">Tous</a>
      <a href="<?= $buildUrl(['filter' => 'unread']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border border-slate-200 text-slate-600 hover:border-slate-300 rounded-md bg-white">Non lus</a>
      <a href="<?= $buildUrl(['filter' => 'unanswered']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border border-slate-200 text-slate-600 hover:border-slate-300 rounded-md bg-white">Sans réponse</a>
      <a href="<?= $buildUrl(['filter' => 'my_subscriptions']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border border-slate-200 text-slate-600 hover:border-slate-300 rounded-md bg-white">Mes abonnements</a>
      <a href="<?= $buildUrl(['filter' => 'my_topics']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border border-slate-200 text-slate-600 hover:border-slate-300 rounded-md bg-white">Mes sujets</a>
    </div>
    <div class="mt-2 flex flex-wrap gap-2">
      <a href="<?= $buildUrl(['sort' => 'activity']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border rounded-md <?= ($sort ?? 'activity') === 'activity' ? 'border-emerald-500 text-emerald-800 bg-emerald-50' : 'border-slate-200 text-slate-600 hover:border-slate-300 bg-white' ?>">Activité récente</a>
      <a href="<?= $buildUrl(['sort' => 'newest']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border border-slate-200 text-slate-600 hover:border-slate-300 rounded-md bg-white">Nouveaux d'abord</a>
      <a href="<?= $buildUrl(['sort' => 'oldest']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border border-slate-200 text-slate-600 hover:border-slate-300 rounded-md bg-white">Anciens d'abord</a>
      <a href="<?= $buildUrl(['sort' => 'popular_7d']) ?>" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.18em] border border-slate-200 text-slate-600 hover:border-slate-300 rounded-md bg-white">Plus actifs (7j)</a>
    </div>
    <?php if ($hasActiveFilters): ?>
      <p class="text-[10px] text-neutral-500 mt-2">
        <?php if (($q ?? '') !== ''): ?>Recherche : « <?= htmlspecialchars($q) ?> » · <?php endif; ?>
        <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($categorySlug) ?>" class="text-rose-600 hover:text-rose-700">Réinitialiser</a>
      </p>
    <?php endif; ?>
  </div>

  <!-- Header colonnes (desktop) -->
  <div class="hidden md:grid grid-cols-[1fr_80px_80px_130px] gap-x-4 px-4 mb-2 pb-2 border-b border-slate-200 header-anim" style="animation-delay:60ms">
    <span class="text-[8px] font-black uppercase tracking-[0.28em] text-slate-500">Sujet</span>
    <span class="text-[8px] font-black uppercase tracking-[0.28em] text-slate-500 text-center"><?= $labels['replies'] ?? 'Réponses' ?></span>
    <span class="text-[8px] font-black uppercase tracking-[0.28em] text-slate-500 text-center"><?= $labels['views'] ?? 'Lectures' ?></span>
    <span class="text-[8px] font-black uppercase tracking-[0.28em] text-slate-500 text-right"><?= $labels['last_activity'] ?? 'Dernier signal' ?></span>
  </div>

  <!-- Liste des sujets -->
  <div class="divide-y divide-slate-100 bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
    <?php if (empty($topics)): ?>
      <p class="py-12 text-center text-slate-500">Aucun signal détecté dans cette zone.<?php if (!empty($canCreate)): ?> <a href="<?= $baseUrl ?>/forum/new-topic?category_id=<?= $categoryId ?>" class="text-emerald-700 hover:text-emerald-600 font-semibold">Émettre le premier signal</a><?php endif; ?></p>
    <?php else: ?>
      <?php foreach ($topics as $index => $t): ?>
        <?php
        $isHidden = !empty($t['is_hidden']);
        $rowClass = 'topic-row group grid grid-cols-1 md:grid-cols-[1fr_80px_80px_130px] gap-x-4 items-center px-4 py-5 no-underline transition-all hover:bg-slate-50 border-l-2 border-transparent hover:border-emerald-500';
        if ($isHidden && !empty($isModo)) $rowClass .= ' opacity-40';
        if (!empty($t['is_locked'])) $rowClass .= ' topic-locked';
        if (!empty($t['is_pinned'])) $rowClass .= ' topic-pinned';
        if (!empty($t['is_archived'])) $rowClass .= ' topic-archived';
        $viewCount = (int) ($t['view_count'] ?? 0);
        $viewStr = $viewCount >= 1000 ? round($viewCount / 1000, 1) . 'k' : (string) $viewCount;
        $timeAgo = function_exists('forum_time_ago') ? forum_time_ago($t['created_at'] ?? '') : ($t['created_at'] ? date('d/m/Y', strtotime($t['created_at'])) : '');
        ?>
        <a href="<?= $baseUrl ?>/forum/topic/<?= (int) $t['id'] ?>" class="<?= $rowClass ?>" style="animation-delay: <?= 80 + $index * 38 ?>ms">
          <div class="flex items-start gap-4 min-w-0">
            <div class="relative shrink-0 mt-1 w-9 h-9 flex items-center justify-center border border-emerald-200 bg-emerald-50 text-emerald-700 group-hover:bg-emerald-600 group-hover:text-white transition-all rounded-md">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1.5">
                <?php if (!empty($t['is_pinned'])): ?><span class="text-[7px] font-black px-1.5 py-0.5 uppercase tracking-tighter bg-emerald-600 text-white rounded">Épinglé</span><?php endif; ?>
                <?php if (!empty($t['is_locked'])): ?><span class="text-[7px] font-black px-1.5 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase tracking-tighter">Verrouillé</span><?php endif; ?>
                <?php if (empty($t['is_locked'])): ?><span class="text-[7px] font-black px-1.5 py-0.5 bg-green-500/10 text-green-500 border border-green-500/20 uppercase tracking-tighter">Ouvert</span><?php endif; ?>
                <h3 class="w-full md:w-auto font-black italic uppercase tracking-tight text-slate-800 group-hover:text-emerald-700 transition-colors text-sm leading-tight mt-1 md:mt-0">
                  <?= htmlspecialchars($t['title']) ?>
                </h3>
              </div>
              <div class="flex items-center gap-2 text-[9px] text-neutral-600 font-bold">
                <span class="uppercase tracking-widest">ID : <span class="text-neutral-500">#<?= str_pad((string)(int)$t['id'], 3, '0', STR_PAD_LEFT) ?></span></span>
                <span class="text-neutral-800">/</span>
                <span class="uppercase tracking-widest">Par <span class="text-emerald-700 group-hover:underline"><?= htmlspecialchars($t['topic_author_display'] ?? $t['author_name'] ?? '') ?></span></span>
                <span class="text-neutral-800">·</span>
                <span class="italic text-neutral-500"><?= $timeAgo ?></span>
              </div>
            </div>
          </div>
          <div class="hidden md:flex flex-col items-center justify-center border-x border-slate-100">
            <span class="text-sm font-black tabular-nums text-slate-700"><?= (int) ($t['post_count'] ?? 0) ?></span>
            <span class="text-[7px] text-neutral-700 font-black uppercase tracking-widest"><?= $labels['replies'] ?? 'réponses' ?></span>
          </div>
          <div class="hidden md:flex flex-col items-center justify-center">
            <span class="text-sm font-black text-slate-600 tabular-nums"><?= $viewStr ?></span>
            <span class="text-[7px] text-neutral-700 font-black uppercase tracking-widest"><?= $labels['views'] ?? 'lectures' ?></span>
          </div>
          <div class="hidden md:block text-right pr-2">
            <div class="text-[9px] font-bold text-slate-500 tabular-nums"><?= $t['last_post_at'] ? date('H:i', strtotime($t['last_post_at'])) : '—' ?> <span class="text-slate-300">|</span> <?= $t['last_post_at'] ? date('d.m', strtotime($t['last_post_at'])) : '—' ?></div>
            <div class="text-[8px] font-black text-emerald-700 uppercase tracking-widest mt-0.5 group-hover:-translate-x-1 transition-transform">→ <?= htmlspecialchars($t['last_post_author_name'] ?? '—') ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="flex flex-wrap items-center justify-center gap-2 mt-8">
      <span class="text-[10px] text-slate-500">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
      <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
        <a href="<?= $buildUrl(['page' => $i, 'sort' => $sort ?? 'activity', 'filter' => $filter ?? '', 'q' => $q ?? '']) ?>" class="min-w-[2.5rem] py-2 text-center border text-[10px] font-bold rounded-md <?= $i === $page ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300' ?> transition"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <!-- Subcategories -->
  <?php if (!empty($subcategories)): ?>
    <section class="mt-12">
      <div class="flex items-center gap-2 mb-4">
        <span class="h-px w-6 bg-emerald-500/50"></span>
        <h2 class="text-[8px] font-black uppercase tracking-[0.4em] text-emerald-800">Sous-catégories</h2>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <?php foreach ($subcategories as $sub): ?>
          <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($sub['slug']) ?>" class="border border-slate-200 bg-white p-4 hover:border-emerald-300 transition flex items-center justify-between group rounded-lg shadow-sm">
            <div class="flex items-center gap-3 min-w-0">
              <span class="w-10 h-10 bg-slate-100 border border-slate-200 flex items-center justify-center text-lg flex-shrink-0 rounded-md"><?= !empty($sub['icon']) ? htmlspecialchars($sub['icon']) : '📁' ?></span>
              <div class="min-w-0">
                <p class="text-sm font-black italic uppercase text-slate-900 truncate"><?= htmlspecialchars($sub['name']) ?></p>
                <?php if (!empty($sub['description'])): ?>
                  <p class="text-[9px] text-slate-500 line-clamp-2 mt-0.5"><?= htmlspecialchars($sub['description']) ?></p>
                <?php endif; ?>
                <p class="text-[9px] text-slate-500 mt-1"><?= (int) ($sub['topic_count'] ?? 0) ?> sujets</p>
              </div>
            </div>
            <span class="text-slate-400 group-hover:text-emerald-600 transition flex-shrink-0">→</span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
<script>
(function() {
  var btn = document.getElementById('sub-btn');
  if (!btn) return;
  var categoryId = btn.getAttribute('data-category-id');
  var subscribed = btn.getAttribute('data-subscribed') === '1';
  var baseUrl = '<?= $baseUrl ?>';
  var csrf = '<?= \App\Core\Csrf::token() ?>';
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    var action = subscribed ? 'unsubscribe' : 'subscribe';
    fetch(baseUrl + '/api/forum', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ action: action, type: 'category', target_id: parseInt(categoryId, 10), csrf_token: csrf })
    }).then(function(r) { return r.json(); }).then(function(d) {
      if (d.success) {
        subscribed = !subscribed;
        btn.setAttribute('data-subscribed', subscribed ? '1' : '0');
        var label = document.getElementById('sub-label');
        if (label) label.textContent = subscribed ? 'Abonné' : 'Suivre';
        btn.className = 'flex items-center gap-2 px-4 py-3 text-[9px] font-black uppercase tracking-[0.2em] border transition-colors rounded-md ' + (subscribed ? 'border-emerald-300 text-emerald-800 bg-emerald-50' : 'bg-white border-slate-200 text-slate-600 hover:border-emerald-300');
      }
    });
  });
})();
</script>
