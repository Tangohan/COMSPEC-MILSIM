<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$userName = \App\Core\Session::get('display_name') ?? \App\Core\Session::get('email') ?? 'Connecté';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <!-- Ticker -->
  <div class="border-b border-white/5 bg-[#0a0a0b] py-2 overflow-hidden mb-8">
    <div class="flex animate-ticker whitespace-nowrap">
      <span class="text-[9px] font-black uppercase tracking-[0.3em] text-neutral-700"><?= htmlspecialchars($forumConfig['name'] ?? 'Forum') ?></span>
      <span class="text-orange-500/30 mx-4">///</span>
      <span class="text-[9px] font-black uppercase tracking-[0.3em] text-neutral-700"><?= htmlspecialchars($forumConfig['subtitle'] ?? '') ?></span>
      <span class="text-orange-500/30 mx-4">///</span>
      <span class="text-[9px] font-black uppercase tracking-[0.3em] text-neutral-700"><?= (int) ($topicCount ?? 0) ?> sujets actifs</span>
      <span class="text-orange-500/30 mx-4">///</span>
      <span class="text-[9px] font-black uppercase tracking-[0.3em] text-neutral-700"><?= (int) ($postCount ?? 0) ?> messages</span>
      <span class="text-orange-500/30 mx-4">///</span>
      <span class="text-[9px] font-black uppercase tracking-[0.3em] text-neutral-700"><?= (int) ($activeMembers24h ?? 0) ?> membres actifs (24 h)</span>
      <span class="text-orange-500/30 mx-4">///</span>
      <span class="text-[9px] font-black uppercase tracking-[0.3em] text-neutral-700"><?= htmlspecialchars($forumConfig['name'] ?? 'Forum') ?></span>
      <span class="text-orange-500/30 mx-4">///</span>
      <span class="text-[9px] font-black uppercase tracking-[0.3em] text-neutral-700"><?= htmlspecialchars($forumConfig['subtitle'] ?? '') ?></span>
    </div>
  </div>

  <!-- Hero -->
  <div class="relative mb-12">
    <div class="absolute left-0 top-0 w-32 h-32 rounded-full bg-orange-500/8 blur-[120px] -z-10"></div>
    <div class="absolute right-0 top-0 w-40 h-40 rounded-full bg-indigo-500/5 blur-[100px] -z-10"></div>
    <div class="flex items-center gap-3 mb-4">
      <span class="h-px w-12 bg-orange-500/60"></span>
      <span class="text-[9px] font-black uppercase tracking-[0.5em] text-orange-500"><?= htmlspecialchars($forumConfig['context'] ?? '') ?></span>
    </div>
    <h1 class="text-5xl md:text-7xl lg:text-8xl font-black italic uppercase tracking-tighter text-white leading-[0.9] mb-2">
      La Salle<br>
      de <span class="bg-gradient-to-r from-orange-400 via-orange-500 to-orange-700 bg-clip-text text-transparent">brief</span><span class="text-orange-500 animate-blink">_</span>
    </h1>
    <p class="text-neutral-500 text-sm border-l-2 border-orange-500/25 pl-6 italic max-w-2xl mb-6"><?= htmlspecialchars($forumConfig['tagline'] ?? '') ?></p>
    <div class="flex flex-wrap items-center gap-4">
      <a href="<?= $baseUrl ?>/forum/new-topic" class="inline-block bg-orange-500 text-black px-8 py-4 font-black uppercase text-[10px] tracking-[0.25em] hover:bg-orange-400 transition">Nouveau Sujet</a>
      <a href="<?= $baseUrl ?>/" class="inline-block border border-white/10 px-6 py-3 text-xs font-bold uppercase tracking-wider text-neutral-400 hover:text-white hover:border-white/20 transition">Retour</a>
      <span class="text-[9px] font-black uppercase tracking-widest text-neutral-600 ml-auto"><?= htmlspecialchars($userName) ?></span>
    </div>
  </div>

  <!-- Search -->
  <div class="border border-white/10 bg-white/[0.02] p-4 mb-8">
    <form method="get" action="<?= $baseUrl ?>/forum" class="flex flex-wrap gap-3">
      <input type="search" name="q" value="<?= htmlspecialchars($searchQuery ?? '') ?>" placeholder="<?= htmlspecialchars($labels['search_placeholder'] ?? 'Recherche forum (titre + contenu)') ?>" class="flex-1 min-w-[200px] bg-black/50 border border-white/10 px-4 py-2 text-sm text-white placeholder-neutral-500 focus:outline-none focus:border-orange-500/50">
      <button type="submit" class="bg-indigo-500 hover:bg-indigo-400 text-black text-[10px] font-black uppercase tracking-[0.2em] px-6 py-2 transition">Rechercher</button>
    </form>
    <?php if (!empty($searchQuery) && isset($searchResults)): ?>
      <p class="text-xs text-neutral-500 mt-2"><?= count($searchResults) ?> résultat(s)</p>
      <?php if (!empty($searchResults)): ?>
        <ul class="mt-2 space-y-1">
          <?php foreach ($searchResults as $r): ?>
            <li><a href="<?= $baseUrl ?>/forum/topic/<?= (int) $r['id'] ?>" class="text-sm text-orange-400 hover:text-orange-300"><?= htmlspecialchars($r['title']) ?></a> — <?= htmlspecialchars($r['category_name'] ?? '') ?> · Par <?= htmlspecialchars($r['author_name'] ?? '') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-white/5 mb-10">
    <div class="stat-card bg-[#0d0d0f] p-6 border border-transparent hover:border-orange-500/20 transition">
      <p class="text-[8px] font-black uppercase tracking-[0.25em] text-neutral-600 group-hover:text-orange-500/50">Sujets</p>
      <p class="text-3xl font-black italic text-white mt-1"><?= (int) ($topicCount ?? 0) ?></p>
      <p class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest mt-1">total</p>
      <span class="block text-[22px] opacity-[0.07] hover:opacity-20 mt-2">📁</span>
      <span class="block h-[2px] w-full bg-transparent group-hover:bg-orange-500/60 mt-2"></span>
    </div>
    <div class="stat-card bg-[#0d0d0f] p-6 border border-transparent hover:border-orange-500/20 transition">
      <p class="text-[8px] font-black uppercase tracking-[0.25em] text-neutral-600">Messages</p>
      <p class="text-3xl font-black italic text-white mt-1"><?= (int) ($postCount ?? 0) ?></p>
      <p class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest mt-1">total</p>
      <span class="block text-[22px] opacity-[0.07] mt-2">💬</span>
    </div>
    <div class="stat-card bg-[#0d0d0f] p-6 border border-transparent hover:border-orange-500/20 transition">
      <p class="text-[8px] font-black uppercase tracking-[0.25em] text-neutral-600">Cette semaine</p>
      <p class="text-3xl font-black italic text-white mt-1">+<?= (int) ($postsThisWeek ?? 0) ?></p>
      <p class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest mt-1">messages</p>
      <span class="block text-[22px] opacity-[0.07] mt-2">⚡</span>
    </div>
    <div class="stat-card bg-[#0d0d0f] p-6 border border-transparent hover:border-orange-500/20 transition">
      <p class="text-[8px] font-black uppercase tracking-[0.25em] text-neutral-600">Membres actifs</p>
      <p class="text-3xl font-black italic text-white mt-1"><?= (int) ($activeMembers24h ?? 0) ?></p>
      <p class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest mt-1">24 h</p>
      <span class="block text-[22px] opacity-[0.07] mt-2">🔥</span>
    </div>
  </div>

  <div class="lg:grid lg:grid-cols-12 lg:gap-8">
    <div class="lg:col-span-8 space-y-8">
      <!-- Annonces officielles -->
      <?php if (!empty($pinnedAnnouncements) && isset($announcementsCategory)): ?>
        <section>
          <div class="flex items-center gap-3 mb-4">
            <span class="h-px w-8 bg-orange-500/40"></span>
            <span class="text-[8px] font-black uppercase tracking-[0.4em] text-orange-500/70"><?= htmlspecialchars($labels['official_announcements'] ?? 'Communiqués officiels') ?></span>
            <span class="flex-1 h-px bg-white/5"></span>
          </div>
          <div class="space-y-4">
            <?php foreach (array_slice($pinnedAnnouncements, 0, 3) as $i => $ann): ?>
              <article class="bg-[#0a0a0c] border border-orange-500/20 p-8">
                <div class="flex items-start justify-between gap-4 mb-3">
                  <div class="flex items-center gap-3">
                    <span class="h-12 w-12 bg-white text-black font-black italic flex items-center justify-center text-lg"><?= mb_substr($ann['author_name'] ?? 'A', 0, 1) ?></span>
                    <div>
                      <span class="text-[10px] font-black uppercase tracking-widest text-orange-500">Officiel</span>
                      <p class="text-xl font-black text-white uppercase italic"><?= htmlspecialchars($ann['author_name'] ?? '') ?></p>
                    </div>
                  </div>
                  <span class="text-[9px] text-neutral-500">Édit n°<?= $i + 1 ?> · <?= date('d/m/Y', strtotime($ann['updated_at'] ?? $ann['created_at'] ?? 'now')) ?></span>
                </div>
                <h2 class="text-base font-black text-white uppercase italic mb-2"><?= htmlspecialchars($ann['title']) ?></h2>
                <p class="text-sm text-neutral-400 line-clamp-2"><?= htmlspecialchars(mb_substr(strip_tags($ann['title'] ?? ''), 0, 120)) ?>…</p>
                <div class="flex flex-wrap gap-2 mt-4">
                  <span class="text-[8px] font-black uppercase border border-orange-500/30 text-orange-500/80 px-2 py-0.5">Épinglé</span>
                  <span class="text-[8px] font-black uppercase border border-orange-500/30 text-orange-500/80 px-2 py-0.5">Officiel</span>
                </div>
                <a href="<?= $baseUrl ?>/forum/topic/<?= (int) $ann['id'] ?>" class="inline-block mt-4 text-xs font-bold text-orange-400 hover:text-orange-300">Accéder au fil →</a>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- Canaux -->
      <section>
        <div class="flex items-center gap-3 mb-6">
          <span class="flex-1 h-px bg-white/5"></span>
          <span class="text-[8px] font-black uppercase tracking-[0.4em] text-neutral-700"><?= htmlspecialchars($labels['categories'] ?? 'Canaux') ?> · <?= count($categories ?? []) ?> <?= $labels['channels_active'] ?? 'zones actives' ?></span>
          <span class="flex-1 h-px bg-white/5"></span>
        </div>
        <div class="space-y-4">
          <?php
          $colorThemes = ['orange' => ['border' => 'border-orange-500/20', 'hover' => 'hover:border-orange-500', 'glow' => 'bg-orange-500/5', 'icon' => 'bg-orange-500 text-black', 'title' => 'group-hover:text-orange-400'], 'indigo' => ['border' => 'border-indigo-500/20', 'hover' => 'hover:border-indigo-500', 'glow' => 'bg-indigo-500/5', 'icon' => 'bg-white/5 border border-white/10 group-hover:bg-indigo-500 group-hover:text-black', 'title' => 'group-hover:text-indigo-400'], 'violet' => ['border' => 'border-violet-500/20', 'hover' => 'hover:border-violet-500', 'glow' => 'bg-violet-500/5', 'icon' => 'bg-white/5 border border-white/10 group-hover:bg-violet-500 group-hover:text-black', 'title' => 'group-hover:text-violet-400'], 'rose' => ['border' => 'border-rose-500/20', 'hover' => 'hover:border-rose-500', 'glow' => 'bg-rose-500/5', 'icon' => 'bg-white/5 border border-white/10 group-hover:bg-rose-500 group-hover:text-black', 'title' => 'group-hover:text-rose-400'], 'emerald' => ['border' => 'border-emerald-500/20', 'hover' => 'hover:border-emerald-500', 'glow' => 'bg-emerald-500/5', 'icon' => 'bg-white/5 border border-white/10 group-hover:bg-emerald-500 group-hover:text-black', 'title' => 'group-hover:text-emerald-400'], 'slate' => ['border' => 'border-white/5', 'hover' => 'hover:border-white/30', 'glow' => 'bg-white/5', 'icon' => 'bg-white/5 border border-white/10 group-hover:bg-white/10', 'title' => 'group-hover:text-white']];
          foreach ($categories ?? [] as $idx => $cat):
            $theme = $colorThemes[$cat['color_theme'] ?? 'slate'] ?? $colorThemes['slate'];
            $num = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
          ?>
            <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($cat['slug']) ?>" class="category-card group block border <?= $theme['border'] ?> <?= $theme['hover'] ?> bg-[#0a0a0c] transition relative overflow-hidden">
              <div class="absolute -right-10 -top-10 w-52 h-52 <?= $theme['glow'] ?> blur-3xl opacity-0 group-hover:opacity-100 transition-opacity rounded-full"></div>
              <div class="relative flex flex-col md:flex-row p-6 md:p-8">
                <span class="absolute top-4 right-4 text-[9px] font-black text-neutral-800 tabular-nums"><?= $num ?></span>
                <div class="w-16 h-16 md:w-20 md:h-20 <?= $theme['icon'] ?> flex items-center justify-center text-2xl md:text-3xl mb-4 md:mb-0 md:mr-6 transition -rotate-0 group-hover:-rotate-3">📁</div>
                <div class="flex-1">
                  <h2 class="text-xl md:text-2xl font-black italic uppercase tracking-tighter text-white <?= $theme['title'] ?>"><?= htmlspecialchars($cat['name']) ?></h2>
                  <p class="text-sm text-neutral-500 line-clamp-2 mt-1"><?= htmlspecialchars($cat['description'] ?? '') ?></p>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 border-l border-white/5 pl-4">
                    <div>
                      <p class="text-[8px] font-black text-neutral-700 uppercase">Sujets</p>
                      <p class="text-xs font-black text-white"><?= (int) ($cat['topic_count'] ?? 0) ?></p>
                    </div>
                    <div>
                      <p class="text-[8px] font-black text-neutral-700 uppercase">Messages</p>
                      <p class="text-xs font-black text-white"><?= (int) ($cat['post_count'] ?? 0) ?></p>
                    </div>
                    <div>
                      <p class="text-[8px] font-black text-neutral-700 uppercase"><?= $labels['last_activity'] ?? 'Dernier signal' ?></p>
                      <p class="text-xs text-neutral-500"><?= $cat['last_post_at'] ? date('d/m H:i', strtotime($cat['last_post_at'])) : '—' ?></p>
                    </div>
                    <div>
                      <p class="text-[8px] font-black text-neutral-700 uppercase"><?= $labels['by'] ?? 'Par' ?></p>
                      <p class="text-xs text-neutral-500"><?= htmlspecialchars($cat['last_post_author'] ?? '—') ?></p>
                    </div>
                  </div>
                </div>
                <div class="mt-4 md:mt-0 flex items-center justify-end">
                  <span class="h-12 w-12 border border-white/10 flex items-center justify-center text-xl group-hover:border-orange-500/60 group-hover:text-white transition">→</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <!-- Sidebar -->
    <aside class="lg:col-span-4 space-y-8 mt-10 lg:mt-0">
      <div>
        <div class="flex items-center gap-2 mb-3">
          <span class="w-1.5 h-1.5 bg-orange-500 rounded-full" style="box-shadow: 0 0 8px rgba(249,115,22,0.6);"></span>
          <span class="text-[10px] font-black uppercase tracking-[0.3em] text-white"><?= $labels['recent_archives'] ?? 'Archives récentes' ?></span>
        </div>
        <div class="bg-[#0a0a0c] border border-white/5 divide-y divide-white/5">
          <?php foreach (array_slice($recentTopics ?? [], 0, 8) as $t): ?>
            <a href="<?= $baseUrl ?>/forum/topic/<?= (int) $t['id'] ?>" class="block px-4 py-3 hover:bg-white/[0.02] transition">
              <span class="text-[10px] font-bold text-neutral-500"><?= htmlspecialchars($t['category_name'] ?? '') ?></span>
              <p class="text-xs font-bold text-white hover:text-orange-400 truncate"><?= htmlspecialchars($t['title']) ?></p>
              <p class="text-[9px] text-neutral-600 italic">Par <?= htmlspecialchars($t['last_author_name'] ?? '') ?> · <?= $t['updated_at'] ? date('d/m H:i', strtotime($t['updated_at'])) : '' ?></p>
            </a>
          <?php endforeach; ?>
          <?php if (empty($recentTopics)): ?>
            <p class="px-4 py-6 text-xs text-neutral-600">Aucun sujet récent.</p>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <h3 class="text-[8px] font-black text-neutral-500 uppercase tracking-widest mb-3">Top contributeurs</h3>
        <div class="bg-[#0a0a0c] border border-white/5 p-4 space-y-2">
          <?php
          $contributorClasses = ['bg-orange-500/20 border-orange-500/30 text-orange-400', 'bg-indigo-500/20 border-indigo-500/30 text-indigo-400', 'bg-violet-500/20 border-violet-500/30 text-violet-400', 'bg-rose-500/20 border-rose-500/30 text-rose-400', 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400'];
          foreach (array_slice($topContributors ?? [], 0, 5) as $ci => $c):
            $cls = $contributorClasses[$ci % count($contributorClasses)];
          ?>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-8 h-8 flex items-center justify-center text-xs font-black border <?= $cls ?>"><?= mb_substr($c['display_name'] ?? $c['callsign'] ?? '?', 0, 1) ?></span>
                <span class="text-xs font-bold text-white uppercase"><?= htmlspecialchars($c['display_name'] ?? $c['callsign'] ?? 'Anon') ?></span>
              </div>
              <span class="text-[9px] font-black text-neutral-500"><?= (int) ($c['post_count'] ?? 0) ?> MSG</span>
            </div>
          <?php endforeach; ?>
          <?php if (empty($topContributors)): ?>
            <p class="text-xs text-neutral-600">Aucun message encore.</p>
          <?php endif; ?>
        </div>
      </div>

      <?php if (function_exists('can') && can('forum.moderate')): ?>
        <div>
          <div class="flex items-center gap-2 mb-3">
            <span class="w-1.5 h-1.5 bg-rose-600 rounded-full animate-pulse"></span>
            <span class="text-[10px] font-black uppercase tracking-[0.3em] text-rose-500"><?= $labels['moderation_panel'] ?? 'Terminal de Contrôle' ?></span>
          </div>
          <?php if (!empty($pendingReports)): ?>
            <div class="bg-rose-500/5 border border-rose-500/20 p-4">
              <span class="text-[8px] font-black uppercase text-rose-400">Urgent</span>
              <p class="text-xs text-neutral-300 mt-1"><?= count($pendingReports) ?> signalement(s) en attente.</p>
              <a href="<?= $baseUrl ?>/forum/moderation" class="inline-block mt-2 text-xs font-bold text-rose-400 hover:text-rose-300">Traiter →</a>
            </div>
          <?php else: ?>
            <div class="bg-emerald-500/5 border border-emerald-500/20 p-4 text-xs text-neutral-400">Aucun signalement en attente.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</div>
