<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$userId = \App\Core\Session::get('user_id');
$topicId = (int) ($topic['id'] ?? 0);
$firstPostId = $firstPostId ?? null;
$postCount = (int) ($postCount ?? 0);
$viewCount = (int) ($topic['view_count'] ?? 0);
$categoryScope = $categoryScope ?? ($topic['category_scope'] ?? 'general');
$tenantDisplayName = isset($tenantDisplayName) ? trim((string) $tenantDisplayName) : '';
$personnelEditBase = url('personnel/' . (int) $userId . '/edit');
$forumMaxPostLen = (int) ($forumMaxPostLen ?? 10000);
$topicAuthorIsStaff = !empty($topicAuthorIsStaff);
$topicTrendLevel = $topicTrendLevel ?? null;
$topicStaleNotice = !empty($topicStaleNotice);
$topicAutoLockedNotice = !empty($topicAutoLockedNotice);
$forumOrgRoleChoices = $forumOrgRoleChoices ?? [];
$forumVisibleRoleCurrent = (int) ($forumVisibleRoleCurrent ?? 0);
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
  <div class="mb-8 anim-up" style="animation-delay:40ms">
    <div class="flex items-center gap-3 mb-3">
      <span class="h-px w-8 bg-emerald-500/50"></span>
      <span class="text-[8px] font-black uppercase tracking-[0.5em] text-emerald-700"><?= htmlspecialchars($topic['category_name'] ?? '') ?></span>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
      <?php if ($topicTrendLevel === 'hot'): ?>
        <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 text-orange-800 border border-orange-200/80 px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider" title="Activité récente élevée">🔥 Tendance</span>
      <?php elseif ($topicTrendLevel === 'active'): ?>
        <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 text-sky-800 border border-sky-200 px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider">⚡ Actif</span>
      <?php endif; ?>
      <?php if (!empty($topic['is_official'])): ?>
        <span class="inline-flex items-center rounded-full bg-indigo-600 text-white px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider shadow-sm">Officiel</span>
      <?php endif; ?>
      <?php if ($topicAuthorIsStaff): ?>
        <span class="inline-flex items-center gap-1 rounded-full bg-slate-800 text-white px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider" title="Créé par un membre de l’équipe">🛡 Équipe</span>
      <?php endif; ?>
      <?php if (!empty($topic['is_pinned'])): ?>
        <span class="inline-flex items-center rounded-full bg-emerald-600 text-white px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider">À la une</span>
      <?php endif; ?>
      <?php if (!empty($topic['is_locked'])): ?>
        <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-900 border border-amber-300/80 px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider">Clos</span>
      <?php endif; ?>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
      <div class="flex-1 min-w-0">
        <h1 class="text-3xl md:text-4xl font-black italic uppercase tracking-tighter text-slate-900 leading-tight mb-3">
          <?= htmlspecialchars($topic['title']) ?>
        </h1>
        <div class="flex items-center gap-2 flex-wrap text-[9px] text-slate-600 font-bold">
          <span>Par <span class="text-slate-800"><?= htmlspecialchars($topic['topic_author_display'] ?? $topic['author_name'] ?? '') ?></span></span>
          <span class="text-slate-300">·</span>
          <span><?= function_exists('forum_time_ago') ? forum_time_ago($topic['created_at'] ?? '') : date('d/m/Y', strtotime($topic['created_at'] ?? 'now')) ?></span>
          <span class="text-slate-300">·</span>
          <span><?= $viewCount ?> vue<?= $viewCount !== 1 ? 's' : '' ?></span>
          <span class="text-slate-300">·</span>
          <span><?= $postCount ?> msg</span>
        </div>
      </div>
      <div class="shrink-0 flex flex-wrap items-center gap-2">
        <?php if ($userId): ?>
        <button type="button" id="topic-report-topic-btn" class="inline-flex items-center gap-2 px-4 py-2.5 text-[9px] font-black uppercase tracking-widest border-2 border-rose-300 bg-white text-rose-700 hover:bg-rose-50 transition-colors rounded-lg shadow-sm" title="Signaler le sujet">Signaler</button>
        <?php endif; ?>
        <?php if ($userId): ?>
        <details class="relative group/topic-actions z-30">
          <summary class="list-none cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 text-[9px] font-black uppercase tracking-widest border border-slate-300 bg-white text-slate-800 hover:border-slate-400 hover:bg-slate-50 transition-colors rounded-lg shadow-sm [&::-webkit-details-marker]:hidden">
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
            Actions
          </summary>
          <div class="absolute right-0 mt-2 w-64 rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl ring-1 ring-slate-900/5">
            <button type="button" data-open-forum-settings class="w-full text-left px-4 py-2.5 text-[11px] font-semibold text-slate-800 hover:bg-slate-50 flex items-center gap-2">
              <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              Compte &amp; affichage
            </button>
            <button type="button" id="topic-sub-btn" data-topic-id="<?= $topicId ?>" data-subscribed="<?= !empty($isSubscribed) ? '1' : '0' ?>" class="w-full text-left px-4 py-2.5 text-[11px] font-semibold text-slate-800 hover:bg-slate-50 flex items-center gap-2 border-t border-slate-100 <?= !empty($isSubscribed) ? 'text-emerald-800' : '' ?>">
              <svg class="w-4 h-4 shrink-0" fill="<?= !empty($isSubscribed) ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
              <span id="topic-sub-label"><?= !empty($isSubscribed) ? 'Ne plus suivre' : 'Suivre le sujet' ?></span>
            </button>
            <button type="button" id="topic-report-url-btn" class="w-full text-left px-4 py-2.5 text-[11px] font-semibold text-slate-800 hover:bg-slate-50 border-t border-slate-100">Signaler une URL</button>
            <?php if (!empty($moderationTutorialHtml)): ?>
            <button type="button" id="btn-guide-tribunal" class="w-full text-left px-4 py-2.5 text-[11px] font-semibold text-fuchsia-800 hover:bg-fuchsia-50 border-t border-slate-100">Guide Tribunal</button>
            <?php endif; ?>
            <?php if (!empty($isModo)): ?>
            <div class="border-t border-slate-200 mt-1 pt-1">
              <p class="px-4 py-1 text-[8px] font-black uppercase tracking-widest text-slate-400">Modération</p>
              <button type="button" class="topic-modo-btn w-full text-left px-4 py-2 text-[11px] font-semibold text-amber-900 hover:bg-amber-50" data-action="<?= !empty($topic['is_locked']) ? 'unlock_topic' : 'lock_topic' ?>"><?= !empty($topic['is_locked']) ? 'Déverrouiller le sujet' : 'Verrouiller le sujet' ?></button>
              <button type="button" class="topic-modo-btn w-full text-left px-4 py-2 text-[11px] font-semibold text-slate-800 hover:bg-slate-50" data-action="<?= !empty($topic['is_pinned']) ? 'unpin_topic' : 'pin_topic' ?>"><?= !empty($topic['is_pinned']) ? 'Désépingler' : 'Épingler / à la une' ?></button>
              <button type="button" class="topic-modo-btn w-full text-left px-4 py-2 text-[11px] font-semibold text-indigo-800 hover:bg-indigo-50 border-t border-slate-50" data-action="toggle_official"><?= !empty($topic['is_official']) ? 'Retirer le badge officiel' : 'Marquer comme officiel' ?></button>
              <button type="button" class="topic-modo-btn w-full text-left px-4 py-2 text-[11px] font-semibold text-rose-800 hover:bg-rose-50 border-t border-slate-50" data-action="<?= !empty($topic['is_hidden']) ? 'unhide_topic' : 'hide_topic' ?>"><?= !empty($topic['is_hidden']) ? 'Restaurer le sujet' : 'Masquer le sujet' ?></button>
            </div>
            <?php endif; ?>
          </div>
        </details>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($topicStaleNotice && empty($topic['is_locked'])): ?>
    <div class="border border-amber-200 bg-amber-50/90 p-4 mb-4 text-amber-950 text-sm rounded-xl flex gap-3 items-start">
      <span class="text-lg shrink-0" aria-hidden="true">⏱</span>
      <div>
        <p class="font-bold text-amber-900">Sujet ancien</p>
        <p class="text-amber-900/90 text-[13px] mt-0.5 leading-snug">Les informations peuvent être obsolètes — vérifiez les dates et les consignes en vigueur.</p>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($topicAutoLockedNotice): ?>
    <div class="border border-slate-200 bg-slate-50 p-4 mb-4 text-slate-800 text-sm rounded-xl">
      <span class="font-bold">Verrouillage automatique</span> — ce sujet n’a pas reçu de réponse récente et a été clos après six mois. Un modérateur peut rouvrir si nécessaire.
    </div>
  <?php endif; ?>

  <?php if (!empty($topic['is_locked'])): ?>
    <div class="border border-amber-300 bg-amber-50 p-4 mb-6 text-amber-900 text-sm rounded-xl">Ce sujet est verrouillé — les nouvelles réponses ne sont pas acceptées.</div>
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
  <div id="posts-list" class="flex flex-col gap-3 mb-10">
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
        $authorDisplayName = trim((string) ($post['author_display_resolved'] ?? '')) ?: ($post['author_name'] ?? $post['author_callsign'] ?? 'Anon');
        $initial = mb_strtoupper(mb_substr($authorDisplayName ?: '?', 0, 1));
        $avatarUrl = isset($post['author_avatar_url']) && trim((string) $post['author_avatar_url']) !== '' ? trim($post['author_avatar_url']) : null;
        if ($avatarUrl && strpos($avatarUrl, 'http') !== 0) {
            $avatarUrl = $baseUrl . '/' . ltrim($avatarUrl, '/');
        }
        $roleNameRaw = isset($post['author_role_name']) && trim((string) $post['author_role_name']) !== '' ? trim($post['author_role_name']) : null;
        $roleSlug = isset($post['author_role_slug']) && trim((string) $post['author_role_slug']) !== '' ? trim((string) $post['author_role_slug']) : null;
        $roleName = function_exists('forum_forum_role_display')
            ? forum_forum_role_display($roleNameRaw, $roleSlug)
            : ($roleNameRaw !== null && strtolower($roleNameRaw) === 'administrator' ? 'Administrateur' : $roleNameRaw);
        $authorPostCount = isset($post['author_post_count']) ? (int) $post['author_post_count'] : 0;
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
        $level = $authorPostCount > 0 ? min(99, 1 + (int) floor($authorPostCount / 5)) : 1;
        ?>
        <div id="post-<?= (int) $post['id'] ?>" class="group post-card forum-post-card <?= $roleClass ?>" data-post-id="<?= (int) $post['id'] ?>">
          <div class="flex flex-col md:flex-row">
            <div class="author-card shrink-0 md:w-44 bg-gradient-to-b from-slate-50 to-slate-100/90 border-b md:border-b-0 md:border-r border-slate-200/90 p-5 flex md:flex-col items-center md:items-start gap-5 relative overflow-hidden">
              <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.015)_1px,transparent_1px)] bg-[size:100%_4px] pointer-events-none opacity-20"></div>
              <?php if ($isOwnPost): ?>
              <button type="button" data-open-forum-settings title="Compte, forum et communauté" class="absolute top-2 right-2 z-20 w-5 h-5 flex items-center justify-center text-slate-600 hover:text-emerald-700 transition-colors">
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
                  <?php if ($tenantDisplayName !== ''): ?>
                  <p class="text-[8px] text-neutral-500 font-bold uppercase tracking-wider truncate max-w-[150px] mt-0.5" title="<?= htmlspecialchars($tenantDisplayName) ?>"><?= htmlspecialchars($tenantDisplayName) ?></p>
                  <?php endif; ?>
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
                  <?php if ((int) ($post['author_hide_forum_level'] ?? 0) === 0): ?>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Niveau</span>
                    <span class="text-[8px] font-black tabular-nums <?= $accentIsOrange ? 'text-emerald-700' : 'text-red-400' ?>">LVL_<?= $level ?></span>
                  </div>
                  <?php endif; ?>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[7px] font-bold text-neutral-700 uppercase tracking-widest">Contrib.</span>
                    <span class="text-[8px] font-black text-slate-900 tabular-nums"><?= str_pad((string) $authorPostCount, 3, '0', STR_PAD_LEFT) ?></span>
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
                <?php if (!empty($isModo) && !empty($post['mod_legal_full_name'])): ?>
                <div class="mt-2 p-2 bg-rose-100 border border-rose-300 rounded text-[7px] text-rose-950 space-y-1 leading-snug">
                  <p class="font-black uppercase tracking-wider text-rose-800">Modération — identité réelle</p>
                  <p><span class="font-bold">ID utilisateur</span> #<?= (int) ($post['mod_author_user_id'] ?? 0) ?></p>
                  <p><span class="font-bold">Nom légal</span> <?= htmlspecialchars($post['mod_legal_full_name']) ?></p>
                  <p><span class="font-bold">Email</span> <?= htmlspecialchars($post['mod_author_email'] ?? '') ?></p>
                  <p class="text-rose-800"><span class="font-bold">Affichage public</span> <?= htmlspecialchars($authorDisplayName) ?> · <?= htmlspecialchars(trim((string) ($post['author_callsign'] ?? ''))) ?></p>
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
                <div class="post-content text-sm text-slate-800 leading-relaxed rounded-2xl border border-slate-200/90 bg-white p-4 md:p-5 ring-1 ring-slate-900/[0.04] shadow-[0_1px_3px_rgba(15,23,42,0.06)]" style="font-family: Inter, ui-sans-serif, 'Segoe UI Emoji', 'Apple Color Emoji', 'Noto Color Emoji', sans-serif;">
                  <?= function_exists('forum_render_content') ? forum_render_content($post['body'] ?? '') : nl2br(htmlspecialchars($post['body'] ?? '')) ?>
                </div>
                <?php if (!empty($post['attachments'])): ?>
                <div class="mt-3 flex flex-wrap gap-2 items-start">
                  <?php foreach ($post['attachments'] as $att): ?>
                    <?php
                    $fp = (string) ($att['file_path'] ?? '');
                    $attUrl = (strpos($fp, 'http') === 0) ? $fp : $baseUrl . '/' . ltrim($fp, '/');
                    $mimeAtt = (string) ($att['mime'] ?? '');
                    $isImg = (strpos($mimeAtt, 'image/') === 0);
                    ?>
                    <?php if ($isImg): ?>
                    <a href="<?= htmlspecialchars($attUrl) ?>" target="_blank" rel="noopener noreferrer" class="block rounded-lg border border-emerald-200/80 overflow-hidden bg-white shadow-sm max-w-[min(100%,280px)]">
                      <img src="<?= htmlspecialchars($attUrl) ?>" alt="" class="max-h-52 w-full object-contain" loading="lazy" decoding="async">
                    </a>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars($attUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-[9px] font-bold text-emerald-800 hover:underline border border-emerald-200 rounded-lg px-3 py-2 bg-emerald-50/90">
                      <svg class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                      <?= htmlspecialchars(basename($fp)) ?>
                    </a>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
              <div class="border-t border-slate-100 px-5 py-3 flex items-center justify-between gap-4 flex-wrap bg-slate-50/50">
                <div class="flex items-center gap-2 flex-wrap">
                  <?php if ($userId): ?>
                  <span class="text-[9px] font-black text-slate-500 tabular-nums forum-vote-score" data-post-id="<?= (int) $post['id'] ?>"><?= (int) ($post['vote_score'] ?? 0) ?></span>
                  <button type="button" class="forum-vote-btn px-2 py-0.5 text-[10px] font-black border border-slate-200 rounded bg-white hover:bg-emerald-50" data-post-id="<?= (int) $post['id'] ?>" data-value="1" title="+1">+</button>
                  <button type="button" class="forum-vote-btn px-2 py-0.5 text-[10px] font-black border border-slate-200 rounded bg-white hover:bg-rose-50" data-post-id="<?= (int) $post['id'] ?>" data-value="-1" title="-1">−</button>
                  <?php endif; ?>
                </div>
                <div class="flex items-center gap-4 ml-auto flex-wrap">
                  <?php if ($userId): ?><button type="button" class="post-quote-btn text-[8px] font-black uppercase tracking-widest text-slate-500 hover:text-emerald-700 transition-colors flex items-center gap-1.5" data-post-id="<?= (int) $post['id'] ?>" data-author="<?= htmlspecialchars($authorDisplayName) ?>"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg> Citer</button><?php endif; ?>
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
      <div class="flex items-center gap-3 mb-4 pb-4 border-b border-slate-100">
        <div class="w-9 h-9 bg-emerald-600 text-white font-black italic flex items-center justify-center text-sm select-none rounded-md">
          <?= mb_strtoupper(mb_substr(\App\Core\Session::get('display_name') ?? 'U', 0, 1)) ?>
        </div>
        <div>
          <p class="text-[8px] font-black uppercase tracking-[0.3em] text-emerald-700">Votre réponse</p>
          <p class="text-xs font-black text-slate-900"><?= htmlspecialchars(\App\Core\Session::get('display_name') ?? '') ?></p>
        </div>
      </div>
      <form id="reply-form-el" method="post" action="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>/reply" class="space-y-3">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="attachment_ids" id="reply-attachment-ids" value="[]">
        <div class="flex flex-wrap gap-1 p-2 bg-slate-100 border border-slate-200 rounded-t-md border-b-0">
          <button type="button" class="reply-tb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 rounded" data-wrap="**" data-end="**" title="Gras (Ctrl+B)"><strong>G</strong></button>
          <button type="button" class="reply-tb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 italic rounded" data-wrap="_" data-end="_" title="Italique (Ctrl+I)">I</button>
          <button type="button" class="reply-tb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 font-mono rounded" data-wrap="`" data-end="`" title="Code inline">`</button>
          <span class="w-px h-5 bg-slate-300 mx-0.5 self-center"></span>
          <button type="button" id="reply-tb-link" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 rounded" title="Lien">Lien</button>
        </div>
        <div class="flex flex-wrap gap-0.5 px-2 py-1.5 bg-slate-50 border border-slate-200 border-b-0 text-lg leading-none" id="reply-emoji-bar" aria-label="Insérer un emoji">
          <?php foreach (['👍', '👏', '✅', '❌', '🎯', '⚠️', '📝', '🔥', '🙏', '😅', '🫡', 'o7'] as $emo): ?>
          <button type="button" class="reply-emo px-1 py-0.5 rounded hover:bg-white border border-transparent hover:border-slate-200" data-ch="<?= htmlspecialchars($emo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($emo, ENT_QUOTES, 'UTF-8') ?></button>
          <?php endforeach; ?>
        </div>
        <textarea name="body" id="reply-content" rows="7" maxlength="<?= (int) $forumMaxPostLen ?>" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 resize-y transition-colors font-mono leading-relaxed rounded-b-md rounded-t-none" placeholder="Écrivez votre réponse… **gras**, *italique*, `code`, emojis 👍" required style="font-family: ui-monospace, 'JetBrains Mono', monospace, 'Segoe UI Emoji', 'Apple Color Emoji', sans-serif;"></textarea>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-emerald-300 bg-emerald-50/50 text-[10px] font-bold text-emerald-900 cursor-pointer hover:bg-emerald-50 transition-colors">
            <input type="file" id="reply-file-input" class="hidden" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" multiple>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
            Joindre des fichiers
          </label>
          <span class="text-[9px] text-slate-500">Images ou PDF · max 5 × 5&nbsp;Mo · modération automatique</span>
        </div>
        <div id="reply-upload-preview" class="flex flex-wrap gap-2 min-h-0"></div>
        <div class="flex items-center justify-between pt-1">
          <span class="text-[9px] text-slate-500 font-bold tabular-nums" id="reply-char-count">0 / <?= (int) $forumMaxPostLen ?></span>
          <button type="submit" id="reply-submit-btn" class="inline-flex items-center gap-3 px-7 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black uppercase text-[9px] tracking-[0.2em] transition-colors rounded-md disabled:opacity-50">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z"></path></svg>
            Envoyer la réponse
          </button>
        </div>
        <p class="text-[8px] uppercase tracking-[0.15em] text-slate-400 font-bold">Raccourcis · Ctrl/Cmd+B · Ctrl/Cmd+I · Ctrl/Cmd+K (lien)</p>
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

<div id="forum-settings-modal" class="hidden fixed inset-0 z-[60] bg-slate-900/50 backdrop-blur-sm items-center justify-center p-4" style="display:none;">
  <div class="bg-white border border-slate-200 max-w-md w-full rounded-xl shadow-xl p-4 max-h-[90vh] overflow-y-auto">
    <h2 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-0.5">Compte et affichage forum</h2>
    <p class="text-[10px] text-slate-600 mb-2 leading-snug">Plateforme (toutes communautés) vs <strong class="text-slate-800">cette communauté</strong> (étiquette, carte auteur).</p>
    <?php if (!empty($categoryScope)): ?>
    <p class="text-[9px] text-slate-500 mb-2 rounded-md bg-slate-50 px-2 py-1 border border-slate-100">Sujet : <strong class="text-slate-700"><?= $categoryScope === 'platform' ? 'forum global' : ($categoryScope === 'organization' ? 'espace communauté' : 'standard') ?></strong></p>
    <?php endif; ?>
    <?php if (!empty($forumOrgRoleChoices)): ?>
    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50/80 p-3">
      <label for="forum-modal-visible-role" class="block text-[9px] font-black uppercase tracking-wider text-slate-600 mb-1.5">Rôle visible sur le forum</label>
      <p class="text-[10px] text-slate-600 mb-2 leading-snug">Choisissez le rôle communauté affiché sur votre carte auteur (à la place du rôle principal).</p>
      <div class="flex flex-col sm:flex-row gap-2">
        <select id="forum-modal-visible-role" class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900">
          <option value=""<?= $forumVisibleRoleCurrent === 0 ? ' selected' : '' ?>>Rôle principal (défaut)</option>
          <?php foreach ($forumOrgRoleChoices as $fro): ?>
            <?php $oid = (int) ($fro['id'] ?? 0); if ($oid < 1) continue; ?>
            <option value="<?= $oid ?>"<?= $forumVisibleRoleCurrent === $oid ? ' selected' : '' ?>><?= htmlspecialchars($fro['name'] !== '' ? $fro['name'] : ('#' . $oid)) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" id="forum-save-visible-role" class="shrink-0 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-wider">Enregistrer</button>
      </div>
      <p id="forum-visible-role-msg" class="text-[10px] mt-2 text-emerald-700 hidden"></p>
    </div>
    <?php endif; ?>
    <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Priorité d’étiquette (si attribué)</p>
    <div class="flex flex-wrap gap-1 mb-3">
      <a class="inline-flex items-center rounded-md border border-violet-200 bg-violet-50/80 px-2 py-1 text-[9px] font-bold text-violet-900 hover:border-violet-400" href="<?= htmlspecialchars($personnelEditBase . '?forum_mode=display_name&forum_focus=label#forum-community-settings') ?>">Nom affiché</a>
      <a class="inline-flex items-center rounded-md border border-violet-200 bg-violet-50/80 px-2 py-1 text-[9px] font-bold text-violet-900 hover:border-violet-400" href="<?= htmlspecialchars($personnelEditBase . '?forum_mode=callsign&forum_focus=label#forum-community-settings') ?>">Callsign</a>
      <a class="inline-flex items-center rounded-md border border-violet-200 bg-violet-50/80 px-2 py-1 text-[9px] font-bold text-violet-900 hover:border-violet-400" href="<?= htmlspecialchars($personnelEditBase . '?forum_mode=character_name&forum_focus=label#forum-community-settings') ?>">Nom opér.</a>
      <a class="inline-flex items-center rounded-md border border-violet-200 bg-violet-50/80 px-2 py-1 text-[9px] font-bold text-violet-900 hover:border-violet-400" href="<?= htmlspecialchars($personnelEditBase . '?forum_mode=forum_alias&forum_focus=label#forum-community-settings') ?>">Pseudo</a>
      <a class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[9px] font-bold text-slate-700 hover:border-slate-400" href="<?= htmlspecialchars($personnelEditBase . '?forum_focus=hide_level#forum-community-settings') ?>">Masquer LVL</a>
    </div>
    <div class="space-y-1.5">
      <a href="<?= htmlspecialchars(url('account/preferences')) ?>" class="block rounded-lg border border-slate-200 bg-slate-50/90 px-2.5 py-2 hover:border-slate-300 hover:bg-white transition">
        <span class="text-[8px] font-black uppercase tracking-wider text-slate-500">Plateforme</span>
        <span class="block text-xs font-bold text-slate-900 leading-tight">Préférences du compte</span>
        <span class="text-[9px] text-slate-600 leading-tight">Nom, indicatif, langue…</span>
      </a>
      <a href="<?= htmlspecialchars($personnelEditBase) ?>#forum-community-settings" class="block rounded-lg border border-emerald-200/90 bg-emerald-50/50 px-2.5 py-2 hover:border-emerald-400 hover:bg-emerald-50/90 transition">
        <span class="text-[8px] font-black uppercase tracking-wider text-emerald-800">Communauté</span>
        <span class="block text-xs font-bold text-slate-900 leading-tight">Dossier &amp; forum</span>
        <span class="text-[9px] text-slate-600 leading-tight">Pseudo, visibilité carte, ORBAT…</span>
      </a>
      <a href="<?= htmlspecialchars(url('account/portrait')) ?>" class="block rounded-lg border border-slate-100 bg-white px-2.5 py-2 hover:border-slate-200 transition">
        <span class="text-[8px] font-black uppercase tracking-wider text-slate-400">Médias</span>
        <span class="block text-xs font-bold text-slate-800 leading-tight">Portrait / avatar</span>
      </a>
    </div>
    <button type="button" class="mt-3 w-full py-2 text-[9px] font-black uppercase tracking-widest text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50" data-close-modal="forum-settings-modal">Fermer</button>
  </div>
</div>

<div id="report-modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white border border-slate-200 p-6 max-w-md w-full rounded-xl shadow-xl">
    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
      <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
      <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Signaler ce contenu</h3>
    </div>
    <form id="report-form">
      <input type="hidden" id="report-target-type" value="post">
      <input type="hidden" id="report-target-id" value="">
      <input type="hidden" id="report-page-topic-id" value="<?= (int) $topicId ?>">
      <p id="report-target-summary" class="text-[10px] text-slate-600 mb-3 leading-snug"></p>
      <div id="report-url-block" class="hidden mb-4 space-y-2">
        <p class="text-[8px] font-black uppercase tracking-widest text-slate-500">URL à signaler</p>
        <input type="url" id="report-url-input" inputmode="url" autocomplete="off" placeholder="https://…" class="w-full bg-slate-50 border border-slate-200 px-3 py-2.5 text-sm text-slate-900 focus:border-rose-400 focus:ring-1 focus:ring-rose-200 focus:outline-none rounded-md">
        <p class="text-[9px] text-slate-500 -mt-1">Astuce : clic droit sur un lien dans un message → copier l’adresse du lien, puis collez-la ci-dessus.</p>
        <p class="text-[8px] font-black uppercase tracking-widest text-slate-500">Message de référence (optionnel)</p>
        <select id="report-url-post-select" class="w-full bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-900 rounded-md">
          <option value="">— Aucun message précis —</option>
          <?php if (!empty($posts)) { foreach ($posts as $rp) { ?>
          <option value="<?= (int) $rp['id'] ?>">Message #<?= (int) $rp['id'] ?></option>
          <?php } } ?>
        </select>
      </div>
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
    <textarea id="edit-post-content" rows="10" maxlength="<?= (int) $forumMaxPostLen ?>" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-200 focus:outline-none resize-y mb-4 font-mono leading-relaxed rounded-md"></textarea>
    <p class="text-[8px] text-slate-500 mb-4"><span id="edit-post-char-count">0</span> / <?= (int) $forumMaxPostLen ?></p>
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
  var maxPostLen = <?= (int) $forumMaxPostLen ?>;
  window.__forumPostBodies = <?= json_encode($postBodiesForJs ?? []) ?>;

  window.openForumSettings = function () {
    var m = document.getElementById('forum-settings-modal');
    if (m) {
      m.classList.remove('hidden');
      m.classList.add('flex');
      m.style.display = 'flex';
    }
  };

  document.querySelectorAll('[data-open-forum-settings]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      openForumSettings();
    });
  });

  var saveVisBtn = document.getElementById('forum-save-visible-role');
  if (saveVisBtn) {
    saveVisBtn.addEventListener('click', function () {
      var sel = document.getElementById('forum-modal-visible-role');
      var msg = document.getElementById('forum-visible-role-msg');
      var v = sel && sel.value !== undefined ? sel.value : '';
      fetch(baseUrl + '/api/forum', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_profile_settings', forum_visible_role_id: v === '' ? null : parseInt(v, 10), csrf_token: csrf })
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (!msg) return;
        msg.classList.remove('hidden');
        msg.textContent = d.success ? 'Enregistré. Actualisez la page pour mettre à jour l’étiquette sur vos messages.' : (d.error || 'Erreur');
        msg.classList.toggle('text-rose-600', !d.success);
        msg.classList.toggle('text-emerald-700', !!d.success);
      });
    });
  }

  var forumSettingsModal = document.getElementById('forum-settings-modal');
  if (forumSettingsModal) {
    forumSettingsModal.classList.add('flex');
  }

  function toast(msg) {
    var el = document.getElementById('forum-toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    el.style.display = 'block';
    setTimeout(function() { el.classList.add('hidden'); el.style.display = ''; }, 3000);
  }

  document.querySelectorAll('.forum-vote-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var postId = parseInt(btn.getAttribute('data-post-id'), 10);
      var value = parseInt(btn.getAttribute('data-value'), 10);
      fetch(baseUrl + '/api/forum/posts/' + postId + '/vote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ value: value, csrf_token: csrf })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success && d.data) {
          var el = document.querySelector('.forum-vote-score[data-post-id="' + postId + '"]');
          if (el) el.textContent = d.data.score;
        } else { toast(d.error || 'Vote impossible'); }
      });
    });
  });

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
          if (lbl) lbl.textContent = subscribed ? 'Suivre le sujet' : 'Ne plus suivre';
          subBtn.className = 'w-full text-left px-4 py-2.5 text-[11px] font-semibold border-t border-slate-100 flex items-center gap-2 ' + (subscribed ? 'text-slate-800 hover:bg-slate-50' : 'text-emerald-800 hover:bg-emerald-50/50');
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
  var replyFormEl = document.getElementById('reply-form-el');
  var replyAttachmentIds = [];
  var replyCharCount = document.getElementById('reply-char-count');
  var replyAttachHidden = document.getElementById('reply-attachment-ids');
  var replySubmitBtn = document.getElementById('reply-submit-btn');

  function syncReplyAttachments() {
    if (replyAttachHidden) replyAttachHidden.value = JSON.stringify(replyAttachmentIds);
  }

  function updateReplyChar() {
    if (replyContent && replyCharCount) {
      replyCharCount.textContent = replyContent.value.length + ' / ' + maxPostLen;
    }
  }

  if (replyContent) {
    replyContent.removeAttribute('required');
    replyContent.addEventListener('input', updateReplyChar);
    updateReplyChar();
  }

  document.querySelectorAll('.reply-tb').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (!replyContent) return;
      var w = btn.getAttribute('data-wrap') || '';
      var end = btn.getAttribute('data-end') || w;
      var start = replyContent.selectionStart, endPos = replyContent.selectionEnd;
      var t = replyContent.value;
      var sel = t.slice(start, endPos);
      replyContent.value = t.slice(0, start) + w + sel + end + t.slice(endPos);
      replyContent.selectionStart = start + w.length + sel.length + end.length;
      replyContent.selectionEnd = replyContent.selectionStart;
      replyContent.focus();
      updateReplyChar();
    });
  });
  var replyTbLink = document.getElementById('reply-tb-link');
  if (replyTbLink && replyContent) {
    replyTbLink.addEventListener('click', function() {
      var url = prompt('URL du lien :', 'https://');
      if (url === null) return;
      var text = prompt('Texte du lien :', replyContent.value.slice(replyContent.selectionStart, replyContent.selectionEnd) || '');
      if (text === null) return;
      var insert = '[' + (text || 'lien') + '](' + url + ')';
      var start = replyContent.selectionStart, endPos = replyContent.selectionEnd;
      replyContent.value = replyContent.value.slice(0, start) + insert + replyContent.value.slice(endPos);
      replyContent.selectionStart = replyContent.selectionEnd = start + insert.length;
      replyContent.focus();
      updateReplyChar();
    });
  }
  document.querySelectorAll('.reply-emo').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (!replyContent) return;
      var ch = btn.getAttribute('data-ch') || '';
      var start = replyContent.selectionStart, endPos = replyContent.selectionEnd;
      var t = replyContent.value;
      replyContent.value = t.slice(0, start) + ch + (ch === 'o7' ? ' ' : '') + t.slice(endPos);
      replyContent.selectionStart = replyContent.selectionEnd = start + ch.length + (ch === 'o7' ? 1 : 0);
      replyContent.focus();
      updateReplyChar();
    });
  });

  var replyFileInput = document.getElementById('reply-file-input');
  var replyUploadPreview = document.getElementById('reply-upload-preview');
  if (replyFileInput && replyUploadPreview) {
    replyFileInput.addEventListener('change', function() {
      var files = replyFileInput.files;
      if (!files || !files.length) return;
      var fd = new FormData();
      fd.append('_csrf_token', csrf);
      for (var i = 0; i < files.length; i++) fd.append('files[]', files[i]);
      fetch(baseUrl + '/api/forum-upload', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          replyFileInput.value = '';
          if (!d.success || !d.files || !d.files.length) {
            toast(d.error || 'Envoi fichier impossible');
            return;
          }
          d.files.forEach(function(f) {
            if (replyAttachmentIds.length >= 5) return;
            var id = f.id;
            replyAttachmentIds.push(id);
            var url = f.url || (baseUrl + '/uploads/forum/' + id);
            var wrap = document.createElement('span');
            wrap.className = 'inline-flex items-center gap-1 text-[9px] font-bold text-emerald-900 bg-emerald-50 border border-emerald-200 rounded px-2 py-1';
            wrap.innerHTML = '<span class="truncate max-w-[140px]" title="' + id.replace(/"/g, '&quot;') + '">' + id.replace(/</g, '') + '</span><button type="button" class="text-rose-600 hover:text-rose-800" data-rm="' + id.replace(/"/g, '') + '">×</button>';
            wrap.querySelector('button').addEventListener('click', function() {
              var rm = this.getAttribute('data-rm');
              replyAttachmentIds = replyAttachmentIds.filter(function(x) { return x !== rm; });
              syncReplyAttachments();
              wrap.remove();
            });
            replyUploadPreview.appendChild(wrap);
          });
          syncReplyAttachments();
          if (d.warnings && d.warnings.length) toast(d.warnings.join(' '));
        });
    });
  }

  if (replyFormEl && replyContent) {
    replyFormEl.addEventListener('submit', function(e) {
      e.preventDefault();
      var text = replyContent.value.trim();
      if (text.length < 1 && replyAttachmentIds.length === 0) {
        toast('Saisissez un message ou joignez un fichier');
        return;
      }
      if (text.length > maxPostLen) {
        toast('Message trop long');
        return;
      }
      if (replySubmitBtn) replySubmitBtn.disabled = true;
      fetch(baseUrl + '/api/forum', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create_post',
          csrf_token: csrf,
          topic_id: topicId,
          content: replyContent.value,
          attachment_ids: replyAttachmentIds
        })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (replySubmitBtn) replySubmitBtn.disabled = false;
        if (d.success && d.post_id) {
          window.location.href = baseUrl + '/forum/topic/' + topicId + '?newpost=' + d.post_id + '#post-' + d.post_id;
        } else {
          toast(d.error || 'Envoi impossible');
        }
      }).catch(function() {
        if (replySubmitBtn) replySubmitBtn.disabled = false;
        toast('Erreur réseau');
      });
    });
  }

  document.addEventListener('keydown', function(e) {
    if (!replyContent || document.activeElement !== replyContent) return;
    if (e.ctrlKey || e.metaKey) {
      if (e.key === 'b') { e.preventDefault(); document.querySelector('.reply-tb[data-wrap=\"**\"]') && document.querySelector('.reply-tb[data-wrap=\"**\"]').click(); }
      if (e.key === 'i') { e.preventDefault(); document.querySelector('.reply-tb[data-wrap=\"_\"]') && document.querySelector('.reply-tb[data-wrap=\"_\"]').click(); }
      if (e.key === 'k') { e.preventDefault(); document.getElementById('reply-tb-link') && document.getElementById('reply-tb-link').click(); }
    }
  });

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
        updateReplyChar();
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
    editContent.addEventListener('input', function() { editCharCount.textContent = editContent.value.length + ' / ' + maxPostLen; });
  }
  document.querySelectorAll('.post-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var postId = parseInt(btn.getAttribute('data-post-id'), 10);
      var body = (window.__forumPostBodies && window.__forumPostBodies[postId]) ? String(window.__forumPostBodies[postId]) : '';
      editingPostId = postId;
      if (editContent) editContent.value = body;
      if (editCharCount) editCharCount.textContent = body.length + ' / ' + maxPostLen;
      if (editModal) { editModal.classList.remove('hidden'); editModal.style.display = 'flex'; }
    });
  });
  if (editSubmit && editContent) {
    editSubmit.addEventListener('click', function() {
      if (editingPostId === null) return;
      var content = editContent.value.trim();
      if (content.length < 1) { toast('Le message ne peut pas être vide'); return; }
      if (content.length > maxPostLen) { toast('Message trop long'); return; }
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
  var reportUrlBlock = document.getElementById('report-url-block');
  var reportTargetSummary = document.getElementById('report-target-summary');

  function openReportModal(mode, opts) {
    opts = opts || {};
    var tt = document.getElementById('report-target-type');
    var tid = document.getElementById('report-target-id');
    var pageTopic = document.getElementById('report-page-topic-id');
    var pTopic = pageTopic ? parseInt(pageTopic.value, 10) : topicId;
    if (!reportModal || !tt || !tid) return;
    if (mode === 'post') {
      tt.value = 'post';
      tid.value = String(opts.postId || '');
      if (reportUrlBlock) reportUrlBlock.classList.add('hidden');
      if (reportTargetSummary) reportTargetSummary.textContent = 'Cible : message #' + (opts.postId || '') + ' dans ce sujet.';
    } else if (mode === 'topic') {
      tt.value = 'topic';
      tid.value = String(pTopic);
      if (reportUrlBlock) reportUrlBlock.classList.add('hidden');
      if (reportTargetSummary) reportTargetSummary.textContent = 'Cible : le sujet entier (titre et fil).';
    } else if (mode === 'url') {
      tt.value = 'url';
      tid.value = '0';
      if (reportUrlBlock) reportUrlBlock.classList.remove('hidden');
      if (reportTargetSummary) reportTargetSummary.textContent = 'Indiquez l’URL problématique (lien externe, pièce jointe distante, etc.). Le sujet actuel sert de contexte pour la modération.';
      var urlIn = document.getElementById('report-url-input');
      if (urlIn) { urlIn.value = opts.prefillUrl || ''; urlIn.focus(); }
    }
    reportModal.classList.remove('hidden');
    reportModal.style.display = 'flex';
  }

  if (reportModal) {
    reportModal.style.display = 'none';
    reportModal.classList.add('flex');
    document.querySelectorAll('.post-report-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        openReportModal('post', { postId: btn.getAttribute('data-post-id') });
      });
    });
    var trTopic = document.getElementById('topic-report-topic-btn');
    if (trTopic) trTopic.addEventListener('click', function() { openReportModal('topic'); });
    var trUrl = document.getElementById('topic-report-url-btn');
    if (trUrl) trUrl.addEventListener('click', function() { openReportModal('url'); });

    document.getElementById('report-form').addEventListener('submit', function(e) {
      e.preventDefault();
      var targetType = document.getElementById('report-target-type').value;
      var targetId = parseInt(document.getElementById('report-target-id').value, 10) || 0;
      var pageTopicEl = document.getElementById('report-page-topic-id');
      var pageTopicId = pageTopicEl ? parseInt(pageTopicEl.value, 10) : topicId;
      var reason = document.getElementById('report-reason').value;
      var details = document.getElementById('report-details').value;
      var payload = {
        action: 'report',
        target_type: targetType,
        target_id: targetId,
        topic_id: pageTopicId,
        reason: reason,
        details: details,
        csrf_token: csrf
      };
      if (targetType === 'url') {
        var urlVal = document.getElementById('report-url-input') ? document.getElementById('report-url-input').value.trim() : '';
        payload.reported_url = urlVal;
        var ps = document.getElementById('report-url-post-select');
        if (ps && ps.value) payload.post_id = parseInt(ps.value, 10);
      }
      fetch(baseUrl + '/api/forum', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
          reportModal.classList.add('hidden');
          reportModal.style.display = 'none';
          toast('Signalement envoyé');
        } else {
          toast(d.error || 'Envoi impossible');
        }
      }).catch(function() { toast('Erreur réseau'); });
    });
  }
})();
</script>
