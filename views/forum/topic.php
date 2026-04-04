<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$userId = \App\Core\Session::get('user_id');
$topicId = (int) ($topic['id'] ?? 0);
$firstPostId = $firstPostId ?? null;
$postCount = (int) ($postCount ?? 0);
$viewCount = (int) ($topic['view_count'] ?? 0);
?>
<main class="w-full max-w-6xl mx-auto px-4 sm:px-6 py-10 bg-[#f8fafc] min-h-[60vh]">
  <!-- Fil d'Ariane -->
  <nav class="flex items-center gap-2 text-[9px] font-black uppercase tracking-[0.25em] text-slate-600 mb-8 flex-wrap anim-up" style="animation-delay:0ms">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-emerald-700 transition-colors">Forum</a>
    <span class="text-slate-400">›</span>
    <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($topic['category_slug'] ?? '') ?>" class="hover:text-emerald-700 transition-colors"><?= htmlspecialchars($topic['category_name'] ?? '') ?></a>
    <span class="text-slate-400">›</span>
    <span class="text-slate-800 truncate max-w-[200px] normal-case tracking-normal font-bold"><?= htmlspecialchars($topic['title']) ?></span>
  </nav>

  <!-- En-tête sujet -->
  <div class="mb-10 anim-up" style="animation-delay:40ms">
    <div class="flex items-center gap-3 mb-3">
      <span class="h-px w-8 bg-emerald-500/50"></span>
      <span class="text-[8px] font-black uppercase tracking-[0.5em] text-emerald-700"><?= htmlspecialchars($topic['category_name'] ?? '') ?></span>
    </div>
    <div class="flex items-start justify-between gap-6 flex-wrap">
      <div class="flex-1 min-w-0">
        <h1 class="text-3xl md:text-4xl font-black italic uppercase tracking-tighter text-slate-900 leading-tight mb-3">
          <?= htmlspecialchars($topic['title']) ?>
        </h1>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[9px] text-slate-600 font-bold">
            Par <span class="text-slate-800"><?= htmlspecialchars($topic['author_name'] ?? '') ?></span>
            · <?= function_exists('forum_time_ago') ? forum_time_ago($topic['created_at'] ?? '') : date('d/m/Y', strtotime($topic['created_at'] ?? 'now')) ?>
            · <?= $viewCount ?> vue<?= $viewCount !== 1 ? 's' : '' ?>
            · <?= $postCount ?> msg
          </span>
        </div>
      </div>
      <div class="shrink-0 flex gap-2 flex-wrap items-start">
        <button type="button" id="topic-sub-btn" data-topic-id="<?= $topicId ?>" data-subscribed="<?= !empty($isSubscribed) ? '1' : '0' ?>" class="flex items-center gap-2 px-3 py-1.5 text-[8px] font-black uppercase tracking-widest border transition-colors rounded-md <?= !empty($isSubscribed) ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-white border-slate-200 text-slate-600 hover:text-emerald-700' ?>">
          <svg class="w-3 h-3" fill="<?= !empty($isSubscribed) ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
          </svg>
          <span id="topic-sub-label"><?= !empty($isSubscribed) ? 'Abonné' : 'Suivre' ?></span>
        </button>
        <?php if (!empty($moderationTutorialHtml)): ?>
          <button type="button" id="btn-guide-tribunal" class="flex items-center gap-2 px-3 py-1.5 text-[8px] font-black uppercase tracking-widest border bg-fuchsia-500/10 border-fuchsia-500/35 text-fuchsia-300 hover:bg-fuchsia-500/20 transition-colors">Guide Tribunal</button>
        <?php endif; ?>
        <?php if (!empty($isModo)): ?>
          <div class="flex gap-1.5 flex-wrap" id="modo-actions">
            <button type="button" class="topic-modo-btn px-3 py-1.5 text-[8px] font-black uppercase tracking-widest bg-amber-500/10 border border-amber-500/25 text-amber-400 hover:bg-amber-500/20 transition-colors" data-action="<?= !empty($topic['is_locked']) ? 'unlock_topic' : 'lock_topic' ?>"><?= !empty($topic['is_locked']) ? 'Déverrouiller' : 'Verrouiller' ?></button>
            <button type="button" class="topic-modo-btn px-3 py-1.5 text-[8px] font-black uppercase tracking-widest bg-slate-100 border border-slate-300 text-slate-800 hover:bg-slate-200 transition-colors rounded-md" data-action="<?= !empty($topic['is_pinned']) ? 'unpin_topic' : 'pin_topic' ?>"><?= !empty($topic['is_pinned']) ? 'Désépingler' : 'Épingler' ?></button>
            <button type="button" class="topic-modo-btn px-3 py-1.5 text-[8px] font-black uppercase tracking-widest bg-rose-500/10 border border-rose-500/25 text-rose-400 hover:bg-rose-500/20 transition-colors" data-action="<?= !empty($topic['is_hidden']) ? 'unhide_topic' : 'hide_topic' ?>"><?= !empty($topic['is_hidden']) ? 'Restaurer' : 'Masquer' ?></button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($topic['is_locked'])): ?>
    <div class="border border-amber-300 bg-amber-50 p-4 mb-6 text-amber-900 text-sm rounded-lg">Ce sujet est verrouillé — les nouvelles réponses ne sont pas acceptées.</div>
  <?php endif; ?>

  <?php $flashSuccess = \App\Core\Session::getFlash('success'); $flashError = \App\Core\Session::getFlash('error'); ?>
  <?php if ($flashSuccess): ?><p class="mb-4 text-sm text-emerald-700"><?= htmlspecialchars($flashSuccess) ?></p><?php endif; ?>
  <?php if ($flashError): ?><p class="mb-4 text-sm text-rose-600"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

  <!-- Pagination (top) -->
  <?php if ($totalPages > 1): ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <span class="text-[10px] text-slate-500">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
      <?php for ($i = 1; $i <= min($totalPages, 8); $i++): ?>
        <a href="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>?page=<?= $i ?>" class="min-w-[2rem] py-1.5 text-center border text-[10px] font-bold rounded-md <?= $i === $page ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300' ?> transition"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <!-- Liste des messages -->
  <div id="posts-list" class="flex flex-col gap-px bg-slate-200/60 rounded-lg overflow-hidden border border-slate-200 mb-10">
    <?php
    $postBodiesForJs = [];
    if (!empty($posts)) {
        foreach ($posts as $p) {
            $postBodiesForJs[(int) $p['id']] = $p['body'] ?? '';
        }
    }
    ?>
    <?php if (empty($posts)): ?>
      <p class="py-8 text-center text-slate-500 bg-white">Aucun message dans ce sujet.</p>
    <?php else: ?>
      <?php foreach ($posts as $post): ?>
        <?php
        $roleClass = 'role-default';
        if ($firstPostId && (int) $post['id'] === $firstPostId) $roleClass = 'role-admin';
        $postIsHidden = !empty($post['is_hidden']);
        $showEdit = ($userId && (int) $post['user_id'] === $userId) || !empty($isModo);
        $isOwnPost = $userId && (int) $post['user_id'] === $userId;
        $initial = mb_strtoupper(mb_substr($post['author_name'] ?? '?', 0, 1));
        $authorDisplayName = $post['author_name'] ?? $post['author_callsign'] ?? 'Anon';
        $avatarUrl = isset($post['author_avatar_url']) && trim((string) $post['author_avatar_url']) !== '' ? trim($post['author_avatar_url']) : null;
        if ($avatarUrl && strpos($avatarUrl, 'http') !== 0) {
            $avatarUrl = $baseUrl . '/' . ltrim($avatarUrl, '/');
        }
        $roleNameRaw = isset($post['author_role_name']) && trim((string) $post['author_role_name']) !== '' ? trim($post['author_role_name']) : null;
        $roleName = $roleNameRaw !== null && strtolower($roleNameRaw) === 'administrator' ? 'Administrateur' : $roleNameRaw;
        $postCount = isset($post['author_post_count']) ? (int) $post['author_post_count'] : 0;
        $authorCreatedAt = isset($post['author_created_at']) && $post['author_created_at'] ? $post['author_created_at'] : null;
        $authorBio = isset($post['author_bio']) && trim((string) $post['author_bio']) !== '' ? trim($post['author_bio']) : null;
        $authorMatricule = isset($post['author_matricule']) && trim((string) $post['author_matricule']) !== '' ? trim($post['author_matricule']) : null;
        $authorGradeName = isset($post['author_grade_name']) && trim((string) $post['author_grade_name']) !== '' ? trim($post['author_grade_name']) : null;
        $authorGradeNato = isset($post['author_grade_nato']) && trim((string) $post['author_grade_nato']) !== '' ? trim($post['author_grade_nato']) : null;
        $authorPrimaryRole = isset($post['author_primary_role']) && trim((string) $post['author_primary_role']) !== '' ? trim($post['author_primary_role']) : null;
        $authorUnitName = isset($post['author_unit_name']) && trim((string) $post['author_unit_name']) !== '' ? trim($post['author_unit_name']) : null;
        $authorUnitCode = isset($post['author_unit_code']) && trim((string) $post['author_unit_code']) !== '' ? trim($post['author_unit_code']) : null;
        $authorUnitDepth = isset($post['author_unit_depth']) && $post['author_unit_depth'] !== '' && $post['author_unit_depth'] !== null ? (int) $post['author_unit_depth'] : null;
        $authorAwards = isset($post['author_awards']) && trim((string) $post['author_awards']) !== '' ? trim($post['author_awards']) : null;
        $accentIsOrange = $firstPostId && (int) $post['id'] === $firstPostId;
        $level = $postCount > 0 ? min(99, 1 + (int) floor($postCount / 5)) : 1;
        ?>
        <div id="post-<?= (int) $post['id'] ?>" class="group post-card <?= $roleClass ?>" data-post-id="<?= (int) $post['id'] ?>">
          <div class="flex flex-col md:flex-row">
            <div class="author-card shrink-0 md:w-44 bg-slate-100 border-b md:border-b-0 md:border-r border-slate-200 p-5 flex md:flex-col items-center md:items-start gap-5 relative overflow-hidden">
              <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.015)_1px,transparent_1px)] bg-[size:100%_4px] pointer-events-none opacity-20"></div>
              <?php if ($isOwnPost): ?>
              <button type="button" onclick="typeof openForumSettings === 'function' ? openForumSettings() : null" title="Paramètres du profil forum" class="absolute top-2 right-2 z-20 w-5 h-5 flex items-center justify-center text-neutral-700 hover:text-neutral-300 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </button>
              <?php endif; ?>
              <div class="relative shrink-0 z-10">
                <div class="absolute -top-1 -left-1 w-3 h-3 border-t border-l <?= $accentIsOrange ? 'border-emerald-600' : 'border-red-500' ?> z-20 pointer-events-none"></div>
                <div class="absolute -bottom-1 -right-1 w-3 h-3 border-b border-r <?= $accentIsOrange ? 'border-emerald-600' : 'border-red-500' ?> z-20 pointer-events-none"></div>
                <div class="relative w-14 h-14 md:w-20 md:h-20 overflow-hidden border border-slate-200 group-hover:border-emerald-300 transition-colors duration-300 bg-white flex items-center justify-center">
                  <?php if ($avatarUrl): ?>
                  <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($authorDisplayName) ?>" class="avatar-img w-full h-full object-cover scale-110 group-hover:scale-100 transition-transform duration-700">
                  <div class="absolute inset-0 bg-gradient-to-t <?= $accentIsOrange ? 'from-emerald-900/25' : 'from-red-900/40' ?> to-transparent opacity-60 group-hover:opacity-80 transition-opacity duration-300"></div>
                  <?php else: ?>
                  <span class="text-xl md:text-2xl font-black italic text-slate-800"><?= $initial ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="flex-1 md:flex-none min-w-0 w-full space-y-2.5 z-10 md:mt-3">
                <div>
                  <p class="hidden md:block text-[8px] font-bold text-neutral-700 uppercase tracking-[0.2em] mb-0.5">Identifiant</p>
                  <p class="text-[11px] font-black uppercase tracking-tighter italic leading-none truncate max-w-[150px]"><?= htmlspecialchars($authorDisplayName) ?></p>
                </div>
                <?php if ($authorMatricule): ?>
                <p class="text-[9px] text-neutral-600"><span class="text-neutral-700 font-bold uppercase tracking-wider">Matricule</span> <?= htmlspecialchars($authorMatricule) ?></p>
                <?php else: ?>
                <p class="text-[9px] text-neutral-600">#<?= (int) $post['id'] ?></p>
                <?php endif; ?>
                <?php if ($roleName): ?>
                <div class="relative inline-block">
                  <span class="rank-badge-glow absolute inset-0 rounded opacity-30 blur-sm <?= $accentIsOrange ? 'bg-emerald-500' : 'bg-red-500' ?>"></span>
                  <span class="relative block text-[7px] font-black uppercase tracking-[0.18em] px-2 py-1 <?= $accentIsOrange ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-red-500/10 border-red-500/40 text-red-400' ?> border"><?= htmlspecialchars($roleName) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($authorGradeName || $authorGradeNato || $authorPrimaryRole || $authorUnitName || $authorUnitCode || $authorUnitDepth !== null || $authorAwards): ?>
                <div class="hidden md:block pt-2.5 border-t border-slate-200 space-y-1.5">
                  <?php if ($authorGradeName): ?>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-slate-500 uppercase tracking-widest">Grade</span>
                    <span class="text-[8px] font-black text-slate-900"><?= htmlspecialchars($authorGradeName) ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if ($authorGradeNato): ?>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Grade OTAN</span>
                    <span class="text-[8px] font-black text-slate-600"><?= htmlspecialchars($authorGradeNato) ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if ($authorPrimaryRole): ?>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Rôle</span>
                    <span class="text-[8px] font-black text-slate-900 truncate max-w-[100px]" title="<?= htmlspecialchars($authorPrimaryRole) ?>"><?= htmlspecialchars($authorPrimaryRole) ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if ($authorUnitName || $authorUnitCode): ?>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Unité</span>
                    <?php $authorUnitDisplay = ($authorUnitCode && $authorUnitName) ? $authorUnitCode . ' – ' . $authorUnitName : ($authorUnitName ?? $authorUnitCode ?? ''); ?>
<span class="text-[8px] font-black text-slate-900 truncate max-w-[100px]" title="<?= htmlspecialchars($authorUnitDisplay) ?>"><?= htmlspecialchars($authorUnitDisplay) ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if ($authorUnitDepth !== null): ?>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Niv. ORBAT</span>
                    <span class="text-[8px] font-black <?= $accentIsOrange ? 'text-emerald-700' : 'text-red-400' ?>"><?= $authorUnitDepth ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if ($authorAwards): ?>
                  <div class="pt-1">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest block mb-0.5">Décorations / Médailles</span>
                    <p class="text-[8px] text-neutral-400 leading-tight break-words line-clamp-3" title="<?= htmlspecialchars($authorAwards) ?>"><?= htmlspecialchars($authorAwards) ?></p>
                  </div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="hidden md:block pt-2.5 border-t border-white/[0.05] space-y-1.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Niveau</span>
                    <span class="text-[8px] font-black tabular-nums <?= $accentIsOrange ? 'text-emerald-700' : 'text-red-400' ?>">LVL_<?= $level ?></span>
                  </div>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Contrib.</span>
                    <span class="text-[8px] font-black text-slate-900 tabular-nums"><?= str_pad((string) $postCount, 3, '0', STR_PAD_LEFT) ?></span>
                  </div>
                  <?php if ($authorCreatedAt): ?>
                  <div class="flex items-center justify-between gap-2" title="Membre depuis le <?= date('d/m/Y', strtotime($authorCreatedAt)) ?>">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Inscrit</span>
                    <span class="text-[8px] font-black text-neutral-500 tabular-nums"><?= date('d/m/Y', strtotime($authorCreatedAt)) ?></span>
                  </div>
                  <?php endif; ?>
                </div>
                <?php if ($authorBio): ?>
                <div class="hidden md:block pt-2.5 border-t border-white/[0.04]">
                  <p class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest mb-1.5">Bio</p>
                  <p class="text-[9px] text-neutral-500 italic leading-relaxed break-words line-clamp-3"><?= htmlspecialchars($authorBio) ?></p>
                </div>
                <?php endif; ?>
              </div>
              <div class="absolute bottom-2 right-2 opacity-[0.03] pointer-events-none hidden md:block">
                <svg width="36" height="36" viewBox="0 0 40 40" fill="none"><path d="M0 0H40V40H0V0ZM2 2V38H38V2H2Z" fill="white"></path><path d="M10 10H30V30H10V10Z" fill="white"></path></svg>
              </div>
            </div>
            <div class="post-role-frame flex-1 min-w-0 flex flex-col bg-white">
              <div class="flex items-center justify-between px-5 py-2.5 border-b border-slate-100 bg-slate-50/80">
                <div class="flex items-center gap-3 flex-wrap">
                  <span class="text-[8px] font-black uppercase tracking-[0.2em] text-slate-500">#<?= (int) $post['id'] ?></span>
                  <span class="text-[9px] text-slate-600 font-bold"><?= function_exists('forum_time_ago') ? forum_time_ago($post['created_at'] ?? '') : date('d/m/Y H:i', strtotime($post['created_at'] ?? 'now')) ?></span>
                  <?php if ($postIsHidden && !empty($isModo)): ?><span class="text-rose-600 text-[8px]">· Masqué</span><?php endif; ?>
                </div>
                <a href="#post-<?= (int) $post['id'] ?>" class="text-[10px] text-slate-400 hover:text-slate-700 transition-colors font-bold select-none">¶</a>
              </div>
              <div class="flex-1 px-5 py-5">
                <div class="post-content text-sm text-slate-800 leading-relaxed rounded-xl border border-slate-200 bg-slate-50/50 p-4 md:p-5 ring-1 ring-emerald-500/15 shadow-sm">
                  <?= function_exists('forum_render_content') ? forum_render_content($post['body'] ?? '') : nl2br(htmlspecialchars($post['body'] ?? '')) ?>
                </div>
              </div>
              <div class="border-t border-slate-100 px-5 py-3 flex items-center justify-between gap-4 flex-wrap bg-slate-50/50">
                <div class="flex items-center gap-1 flex-wrap"></div>
                <div class="flex items-center gap-4 ml-auto flex-wrap">
                  <?php if ($userId): ?><button type="button" class="post-quote-btn text-[8px] font-black uppercase tracking-widest text-slate-500 hover:text-emerald-700 transition-colors flex items-center gap-1.5" data-post-id="<?= (int) $post['id'] ?>" data-author="<?= htmlspecialchars($post['author_name'] ?? '') ?>"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg> Citer</button><?php endif; ?>
                  <?php if ($showEdit): ?><button type="button" class="post-edit-btn text-[8px] font-black uppercase tracking-widest text-neutral-600 hover:text-amber-400 transition-colors flex items-center gap-1.5" data-post-id="<?= (int) $post['id'] ?>"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg> Modifier</button><?php endif; ?>
                  <?php if ($showEdit): ?><button type="button" class="post-delete-btn text-[8px] font-black uppercase tracking-widest text-neutral-700 hover:text-rose-400 transition-colors flex items-center gap-1.5" data-post-id="<?= (int) $post['id'] ?>"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg> Suppr.</button><?php endif; ?>
                  <?php if ($userId && (int) $post['user_id'] !== $userId): ?><button type="button" class="post-report-btn text-[8px] font-black uppercase tracking-widest text-neutral-600 hover:text-rose-400 transition-colors" data-post-id="<?= (int) $post['id'] ?>">Signaler</button><?php endif; ?>
                  <?php if (!empty($isModo)): ?><button type="button" class="post-modo-hide-btn text-[8px] font-black uppercase tracking-widest text-rose-800 hover:text-rose-400 transition-colors" data-post-id="<?= (int) $post['id'] ?>" data-is-hidden="<?= $postIsHidden ? '1' : '0' ?>"><?= $postIsHidden ? 'Restaurer' : 'Masquer' ?></button><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination (bottom) -->
  <?php if ($totalPages > 1): ?>
    <div class="flex flex-wrap items-center gap-2 mt-6">
      <span class="text-[10px] text-slate-500">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
      <?php for ($i = 1; $i <= min($totalPages, 8); $i++): ?>
        <a href="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>?page=<?= $i ?>" class="min-w-[2rem] py-1.5 text-center border text-[10px] font-bold rounded-md <?= $i === $page ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300' ?> transition"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <!-- Formulaire de réponse -->
  <?php if (!empty($canReply)): ?>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 md:p-7 anim-up" style="animation-delay:200ms" id="reply-form">
      <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
        <div class="w-9 h-9 bg-emerald-600 text-white font-black italic flex items-center justify-center text-sm select-none rounded-md">
          <?= mb_strtoupper(mb_substr(\App\Core\Session::get('display_name') ?? 'U', 0, 1)) ?>
        </div>
        <div>
          <p class="text-[8px] font-black uppercase tracking-[0.3em] text-emerald-700">Votre réponse</p>
          <p class="text-xs font-black text-slate-900"><?= htmlspecialchars(\App\Core\Session::get('display_name') ?? '') ?></p>
        </div>
      </div>
      <form method="post" action="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>/reply">
        <?= \App\Core\Csrf::field() ?>
        <textarea name="body" id="reply-content" rows="6" maxlength="10000" class="w-full bg-slate-50 border border-slate-200 px-5 py-4 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 resize-y transition-colors font-mono leading-relaxed rounded-md" placeholder="Écrivez votre réponse… Markdown supporté : **gras**, *italique*, `code`, &gt; citation" required></textarea>
        <p class="mt-2 text-[8px] uppercase tracking-[0.2em] text-slate-500 font-black">Raccourcis: Ctrl/Cmd+B, Ctrl/Cmd+I</p>
        <div class="flex items-center justify-between mt-4">
          <span class="text-[9px] text-slate-500 font-bold tabular-nums" id="reply-char-count">0 / 10 000</span>
          <button type="submit" class="inline-flex items-center gap-3 px-7 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black uppercase text-[9px] tracking-[0.2em] transition-colors rounded-md">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z"></path></svg>
            Envoyer la réponse
          </button>
        </div>
      </form>
    </div>
  <?php elseif (empty($topic['is_locked']) && empty($topic['is_archived'])): ?>
    <p class="mt-10 pt-8 border-t border-slate-200 text-slate-500 text-sm">Vous ne pouvez pas répondre à ce sujet.</p>
  <?php else: ?>
    <p class="mt-10 pt-8 border-t border-slate-200 text-slate-500 text-sm">Ce sujet est verrouillé ou archivé. Les nouvelles réponses ne sont pas acceptées.</p>
  <?php endif; ?>
</main>

<?php if (!empty($moderationTutorialHtml)): ?>
<div id="forum-tutorial-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" aria-hidden="true" style="display: none;">
  <div class="bg-white border border-slate-200 max-w-2xl w-full max-h-[80vh] overflow-auto p-6 rounded-xl shadow-xl">
    <div class="prose prose-slate max-w-none text-sm"><?= $moderationTutorialHtml ?></div>
    <button type="button" class="mt-4 border border-slate-200 px-4 py-2 text-[10px] font-bold uppercase text-slate-600 hover:bg-slate-50 rounded-md" data-close-modal="forum-tutorial-modal">Fermer</button>
  </div>
</div>
<?php endif; ?>

<div id="report-modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white border border-slate-200 p-6 max-w-md w-full rounded-xl shadow-xl">
    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
      <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
      <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Signaler ce contenu</h3>
    </div>
    <form id="report-form">
      <input type="hidden" id="report-target-type" value="post">
      <input type="hidden" id="report-target-id" value="">
      <p class="text-[8px] font-black uppercase tracking-widest text-slate-500 mb-2">Motif</p>
      <select id="report-reason" class="w-full bg-slate-50 border border-slate-200 px-3 py-2.5 text-sm text-slate-900 mb-4 focus:border-rose-400 focus:ring-1 focus:ring-rose-200 focus:outline-none transition-colors rounded-md">
        <option value="spam">Spam</option>
        <option value="harassment">Harcèlement</option>
        <option value="inappropriate">Contenu inapproprié</option>
        <option value="suspicious_link">Lien suspect</option>
        <option value="other">Autre</option>
      </select>
      <p class="text-[8px] font-black uppercase tracking-widest text-slate-500 mb-2">Détails (optionnel)</p>
      <textarea id="report-details" rows="3" placeholder="Précisez si nécessaire…" class="w-full bg-slate-50 border border-slate-200 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-rose-400 focus:ring-1 focus:ring-rose-200 focus:outline-none mb-5 transition-colors resize-none rounded-md"></textarea>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-[8px] font-black uppercase tracking-widest transition-colors rounded-md">Envoyer</button>
        <button type="button" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[8px] font-black uppercase tracking-widest transition-colors rounded-md" data-close-modal="report-modal">Annuler</button>
      </div>
    </form>
  </div>
</div>

<div id="edit-post-modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white border border-slate-200 p-6 max-w-2xl w-full max-h-[90vh] flex flex-col rounded-xl shadow-xl">
    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
      <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
      <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Modifier le message</h3>
    </div>
    <textarea id="edit-post-content" rows="10" maxlength="10000" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-200 focus:outline-none resize-y mb-4 font-mono leading-relaxed rounded-md"></textarea>
    <p class="text-[8px] text-slate-500 mb-4"><span id="edit-post-char-count">0</span> / 10 000</p>
    <div class="flex gap-3">
      <button type="button" id="edit-post-submit" class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-[8px] font-black uppercase tracking-widest transition-colors rounded-md">Enregistrer</button>
      <button type="button" id="edit-post-cancel" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[8px] font-black uppercase tracking-widest transition-colors rounded-md">Annuler</button>
    </div>
  </div>
</div>

<div id="forum-toast" class="hidden fixed bottom-6 right-6 z-50 px-5 py-3 text-[10px] font-black uppercase tracking-widest shadow-2xl transition-all bg-slate-900 text-white border border-slate-700 rounded-lg"></div>

<script>
(function() {
  var baseUrl = '<?= $baseUrl ?>';
  var topicId = <?= $topicId ?>;
  var csrf = '<?= \App\Core\Csrf::token() ?>';
  window.__forumPostBodies = <?= json_encode($postBodiesForJs ?? []) ?>;

  function toast(msg) {
    var el = document.getElementById('forum-toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    el.style.display = 'block';
    setTimeout(function() { el.classList.add('hidden'); el.style.display = ''; }, 3000);
  }

  var subBtn = document.getElementById('topic-sub-btn');
  if (subBtn) {
    subBtn.addEventListener('click', function() {
      var subscribed = subBtn.getAttribute('data-subscribed') === '1';
      var action = subscribed ? 'unsubscribe' : 'subscribe';
      fetch(baseUrl + '/api/forum', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action, type: 'topic', target_id: topicId, csrf_token: csrf })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
          subBtn.setAttribute('data-subscribed', subscribed ? '0' : '1');
          var lbl = document.getElementById('topic-sub-label');
          if (lbl) lbl.textContent = subscribed ? 'Suivre' : 'Abonné';
          subBtn.className = 'flex items-center gap-2 px-3 py-1.5 text-[8px] font-black uppercase tracking-widest border transition-colors rounded-md ' + (subscribed ? 'bg-white border-slate-200 text-slate-600 hover:text-emerald-700' : 'bg-emerald-50 border-emerald-300 text-emerald-800');
          toast(subscribed ? 'Abonnement annulé' : 'Vous suivez ce sujet');
        }
      });
    });
  }

  document.querySelectorAll('[data-close-modal]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = btn.getAttribute('data-close-modal');
      var m = document.getElementById(id);
      if (m) { m.classList.add('hidden'); m.style.display = 'none'; }
    });
  });
  var guideBtn = document.getElementById('btn-guide-tribunal');
  if (guideBtn) {
    guideBtn.addEventListener('click', function() {
      var m = document.getElementById('forum-tutorial-modal');
      if (m) { m.classList.remove('hidden'); m.style.display = 'flex'; }
    });
  }
  document.getElementById('forum-tutorial-modal') && document.getElementById('forum-tutorial-modal').classList.add('flex');
  document.getElementById('report-modal') && document.getElementById('report-modal').classList.add('flex');

  document.querySelectorAll('.topic-modo-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var action = btn.getAttribute('data-action');
      fetch(baseUrl + '/api/forum-moderation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action, topic_id: topicId, csrf_token: csrf })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { window.location.reload(); } else { toast(d.error || 'Erreur'); }
      });
    });
  });

  document.querySelectorAll('.post-modo-hide-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var postId = parseInt(btn.getAttribute('data-post-id'), 10);
      var isHidden = btn.getAttribute('data-is-hidden') === '1';
      var action = isHidden ? 'unhide_post' : 'hide_post';
      fetch(baseUrl + '/api/forum-moderation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action, post_id: postId, csrf_token: csrf })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { window.location.reload(); } else { toast(d.error || 'Erreur'); }
      });
    });
  });

  var replyContent = document.getElementById('reply-content');
  document.querySelectorAll('.post-quote-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var postId = parseInt(btn.getAttribute('data-post-id'), 10);
      var author = btn.getAttribute('data-author') || 'Anon';
      var body = (window.__forumPostBodies && window.__forumPostBodies[postId]) ? String(window.__forumPostBodies[postId]) : '';
      var quoted = '> ' + author + ' a écrit:\n' + body.split('\n').map(function(line) { return '> ' + line; }).join('\n') + '\n\n';
      if (replyContent) {
        var start = replyContent.selectionStart || replyContent.value.length;
        var end = replyContent.selectionEnd || replyContent.value.length;
        var before = replyContent.value.substring(0, start);
        var after = replyContent.value.substring(end);
        replyContent.value = before + quoted + after;
        replyContent.selectionStart = replyContent.selectionEnd = start + quoted.length;
        replyContent.focus();
        var form = document.getElementById('reply-form');
        if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (document.getElementById('reply-char-count')) document.getElementById('reply-char-count').textContent = replyContent.value.length + ' / 10 000';
        toast('Citation insérée');
      } else {
        toast('Réponse non disponible (sujet verrouillé ou formulaire absent)');
      }
    });
  });

  var editModal = document.getElementById('edit-post-modal');
  var editContent = document.getElementById('edit-post-content');
  var editCharCount = document.getElementById('edit-post-char-count');
  var editSubmit = document.getElementById('edit-post-submit');
  var editCancel = document.getElementById('edit-post-cancel');
  var editingPostId = null;

  if (editContent && editCharCount) {
    editContent.addEventListener('input', function() { editCharCount.textContent = editContent.value.length; });
  }
  document.querySelectorAll('.post-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var postId = parseInt(btn.getAttribute('data-post-id'), 10);
      var body = (window.__forumPostBodies && window.__forumPostBodies[postId]) ? String(window.__forumPostBodies[postId]) : '';
      editingPostId = postId;
      if (editContent) editContent.value = body;
      if (editCharCount) editCharCount.textContent = body.length;
      if (editModal) { editModal.classList.remove('hidden'); editModal.style.display = 'flex'; }
    });
  });
  if (editSubmit && editContent) {
    editSubmit.addEventListener('click', function() {
      if (editingPostId === null) return;
      var content = editContent.value.trim();
      if (content.length < 1) { toast('Le message ne peut pas être vide'); return; }
      if (content.length > 10000) { toast('Message trop long'); return; }
      fetch(baseUrl + '/api/forum', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'edit_post', post_id: editingPostId, content: content, csrf_token: csrf })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
          if (editModal) { editModal.classList.add('hidden'); editModal.style.display = 'none'; }
          editingPostId = null;
          window.location.reload();
        } else { toast(d.error || 'Erreur'); }
      });
    });
  }
  if (editCancel && editModal) {
    editCancel.addEventListener('click', function() {
      editModal.classList.add('hidden');
      editModal.style.display = 'none';
      editingPostId = null;
    });
  }

  document.querySelectorAll('.post-delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var postId = parseInt(btn.getAttribute('data-post-id'), 10);
      if (!confirm('Supprimer ce message ?')) return;
      fetch(baseUrl + '/api/forum', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_post', post_id: postId, csrf_token: csrf })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { window.location.reload(); } else { toast(d.error || 'Erreur'); }
      });
    });
  });

  var reportModal = document.getElementById('report-modal');
  if (reportModal) {
    reportModal.style.display = 'none';
    reportModal.classList.add('flex');
    document.querySelectorAll('.post-report-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        document.getElementById('report-target-type').value = 'post';
        document.getElementById('report-target-id').value = btn.getAttribute('data-post-id');
        reportModal.classList.remove('hidden');
        reportModal.style.display = 'flex';
      });
    });
    document.getElementById('report-form').addEventListener('submit', function(e) {
      e.preventDefault();
      var targetType = document.getElementById('report-target-type').value;
      var targetId = document.getElementById('report-target-id').value;
      var reason = document.getElementById('report-reason').value;
      var details = document.getElementById('report-details').value;
      fetch(baseUrl + '/api/forum', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'report', target_type: targetType, target_id: parseInt(targetId, 10), reason: reason, details: details, csrf_token: csrf })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { reportModal.classList.add('hidden'); reportModal.style.display = 'none'; toast('Signalement envoyé'); }
      });
    });
  }

  var replyContent = document.getElementById('reply-content');
  var replyCount = document.getElementById('reply-char-count');
  if (replyContent && replyCount) {
    replyContent.addEventListener('input', function() { replyCount.textContent = replyContent.value.length + ' / 10 000'; });
  }
})();
</script>
