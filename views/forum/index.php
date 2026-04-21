<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$userName = \App\Core\Session::get('display_name') ?? \App\Core\Session::get('email') ?? 'Connecté';
$heroImageUrl = trim((string) ($forumConfig['forum_hero_image_url'] ?? ''));
$hasHeroBg = $heroImageUrl !== '';
$forumDisplayTitle = trim((string) ($forumConfig['name'] ?? ''));
$forumDisplaySubtitle = trim((string) ($forumConfig['subtitle'] ?? $forumConfig['forum_subtitle'] ?? ''));
$forumContextMenuEnabled = !empty($forumContextMenuEnabled);
$forumFullCategoryAdmin = !empty($forumFullCategoryAdmin);
$forumCsrfToken = $forumCsrfToken ?? '';
$forumCategoriesApiUrl = $forumCategoriesApiUrl ?? '';
$forumAdminForumConfigUrl = $forumAdminForumConfigUrl ?? url('admin/forum-config');
$forumContextTenantId = (int) ($forumContextTenantId ?? \App\Core\Session::get('tenant_id') ?? 0);
$forumSessionTenantId = (int) ($forumSessionTenantId ?? $forumContextTenantId);
$forumViewTenantSwitcher = is_array($forumViewTenantSwitcher ?? null) ? $forumViewTenantSwitcher : [];
$forumEffectiveTenantName = trim((string) ($forumEffectiveTenantName ?? ''));
$forumCanDeleteCategoryMenu = !empty($forumCanDeleteCategoryMenu);
?>
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 bg-[#f8fafc]">
  <!-- Hero -->
  <div class="relative mb-10 md:mb-12">
    <?php if (!$hasHeroBg): ?>
    <div class="absolute left-0 top-0 w-32 h-32 rounded-full bg-emerald-500/10 blur-[120px] -z-10"></div>
    <div class="absolute right-0 top-0 w-40 h-40 rounded-full bg-emerald-400/5 blur-[100px] -z-10"></div>
    <?php endif; ?>
    <?php if ($hasHeroBg): ?>
    <div class="relative overflow-hidden rounded-2xl border border-slate-200/60 shadow-md">
      <div class="absolute inset-0 bg-cover bg-center bg-slate-800" style="background-image:url('<?= htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') ?>')"></div>
      <div class="absolute inset-0 forum-hero-overlay bg-gradient-to-br from-slate-950/90 via-slate-900/65 to-emerald-950/40"></div>
      <div class="relative z-10 px-5 py-8 sm:px-8 sm:py-10 md:px-10 md:py-11">
    <?php endif; ?>
    <div class="flex items-center gap-3 mb-3">
      <span class="h-px w-12 <?= $hasHeroBg ? 'bg-emerald-400/55' : 'bg-emerald-500/50' ?>"></span>
      <span class="text-[9px] font-black uppercase tracking-[0.5em] <?= $hasHeroBg ? 'text-emerald-300' : 'text-emerald-700' ?>"><?= htmlspecialchars($forumConfig['context'] ?? '') ?></span>
    </div>
    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black italic uppercase tracking-tighter leading-[0.95] mb-3 <?= $hasHeroBg ? 'text-white drop-shadow-sm' : 'text-slate-900' ?>">
      <?= htmlspecialchars($forumDisplayTitle !== '' ? $forumDisplayTitle : 'Forum', ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <?php if ($forumDisplaySubtitle !== ''): ?>
    <p class="text-xs md:text-sm font-semibold uppercase tracking-[0.2em] mb-3 <?= $hasHeroBg ? 'text-emerald-100/90' : 'text-slate-500' ?>"><?= htmlspecialchars($forumDisplaySubtitle, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <p class="text-sm border-l-2 pl-6 italic max-w-2xl mb-6 leading-relaxed <?= $hasHeroBg ? 'border-emerald-400/45 text-slate-200' : 'border-emerald-500/35 text-slate-600' ?>"><?= htmlspecialchars($forumConfig['tagline'] ?? '') ?></p>
    <?php
    $newTopicHref = $baseUrl . '/forum/new-topic';
    if ($forumViewTenantSwitcher !== [] && $forumContextTenantId !== $forumSessionTenantId && $forumContextTenantId > 1) {
        $newTopicHref .= '?forum_tenant=' . (int) $forumContextTenantId;
    }
    ?>
    <div class="flex flex-wrap items-center gap-3 md:gap-4">
      <a href="<?= htmlspecialchars($newTopicHref, ENT_QUOTES, 'UTF-8') ?>" class="inline-block bg-emerald-600 text-white px-6 py-3 md:px-8 md:py-3.5 font-black uppercase text-[10px] tracking-[0.25em] hover:bg-emerald-500 transition shadow-sm rounded-md">Nouveau Sujet</a>
      <a href="<?= $baseUrl ?>/" class="inline-block px-5 py-2.5 md:px-6 md:py-3 text-xs font-bold uppercase tracking-wider transition rounded-md <?= $hasHeroBg ? 'border border-white/25 bg-white/10 text-white hover:bg-white/15' : 'border border-slate-300 bg-white text-slate-600 hover:text-slate-900 hover:border-slate-400' ?>">Retour</a>
      <span class="text-[9px] font-black uppercase tracking-widest <?= $hasHeroBg ? 'text-slate-300' : 'text-slate-500' ?> ml-auto"><?= htmlspecialchars($userName) ?></span>
    </div>
    <?php if ($hasHeroBg): ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($forumViewTenantSwitcher !== []): ?>
  <div class="mb-6 rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 shadow-sm flex flex-col sm:flex-row sm:items-center gap-3">
    <div class="min-w-0 flex-1">
      <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Vue opérateur site</p>
      <p class="text-xs text-amber-950/90 mt-0.5">Consultez et modérez le forum d’une autre communauté. Les actions s’appliquent à la communauté choisie.</p>
    </div>
    <form method="get" action="<?= htmlspecialchars($baseUrl . '/forum', ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-center gap-2 shrink-0">
      <?php if (($searchQuery ?? '') !== ''): ?>
        <input type="hidden" name="q" value="<?= htmlspecialchars((string) $searchQuery, ENT_QUOTES, 'UTF-8') ?>">
      <?php endif; ?>
      <label class="sr-only" for="forum-tenant-switch">Communauté</label>
      <select name="forum_tenant" id="forum-tenant-switch" class="rounded-lg border border-amber-300/80 bg-white px-3 py-2 text-sm font-semibold text-slate-900 max-w-[min(100%,20rem)]" onchange="this.form.submit()">
        <?php foreach ($forumViewTenantSwitcher as $topt):
            $tid = (int) ($topt['id'] ?? 0);
            if ($tid < 2) {
                continue;
            }
            ?>
          <option value="<?= $tid ?>" <?= $tid === $forumContextTenantId ? 'selected' : '' ?>><?= htmlspecialchars((string) ($topt['name'] ?? ('#' . $tid)), ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button type="submit" class="rounded-lg bg-amber-700 px-3 py-2 text-xs font-bold text-white">Afficher</button></noscript>
    </form>
  </div>
  <?php elseif ($forumEffectiveTenantName !== '' && $forumContextTenantId !== $forumSessionTenantId): ?>
  <div class="mb-6 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs text-slate-600">
    Vue : <span class="font-bold text-slate-900"><?= htmlspecialchars($forumEffectiveTenantName, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <?php endif; ?>

  <?php if ($forumContextMenuEnabled): ?>
  <div class="mb-6 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 via-white to-slate-50 px-4 py-3 shadow-sm flex flex-wrap items-center gap-3">
    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white text-xs font-black" title="Staff">⌁</span>
    <div class="min-w-0 flex-1">
      <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800">Raccourcis encadrement</p>
      <p class="text-xs text-slate-700 mt-0.5"><span class="font-semibold text-slate-900">Clic droit</span> sur une catégorie racine : <span class="font-semibold">sous-catégorie</span><?= $forumCanDeleteCategoryMenu ? ', <span class="font-semibold">suppression</span> si la catégorie est vide' : '' ?> (selon vos droits).</p>
    </div>
    <?php if ($forumFullCategoryAdmin): ?>
    <a href="<?= htmlspecialchars($forumAdminForumConfigUrl, ENT_QUOTES, 'UTF-8') ?>" class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-800 shadow-sm hover:border-emerald-400 hover:text-emerald-900 transition">Configuration forum</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Search -->
  <div class="border border-slate-200 bg-white rounded-lg shadow-sm p-4 mb-8">
    <form method="get" action="<?= $baseUrl ?>/forum" class="flex flex-wrap gap-3">
      <?php if ($forumViewTenantSwitcher !== []): ?>
      <input type="hidden" name="forum_tenant" value="<?= (int) $forumContextTenantId ?>">
      <?php endif; ?>
      <input type="search" name="q" value="<?= htmlspecialchars($searchQuery ?? '') ?>" placeholder="<?= htmlspecialchars($labels['search_placeholder'] ?? 'Recherche forum (titre + contenu)') ?>" class="flex-1 min-w-[200px] bg-slate-50 border border-slate-200 px-4 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 rounded-md">
      <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-[0.2em] px-6 py-2 transition rounded-md">Rechercher</button>
    </form>
    <?php if (!empty($searchQuery) && isset($searchResults)): ?>
      <p class="text-xs text-slate-500 mt-2"><?= count($searchResults) ?> résultat(s)</p>
      <?php if (!empty($searchResults)): ?>
        <ul class="mt-2 space-y-1">
          <?php foreach ($searchResults as $r): ?>
            <li><a href="<?= $baseUrl ?>/forum/topic/<?= (int) $r['id'] ?>" class="text-sm text-emerald-700 hover:text-emerald-600"><?= htmlspecialchars($r['title']) ?></a> — <?= htmlspecialchars($r['category_name'] ?? '') ?> · Par <?= htmlspecialchars($r['topic_author_display'] ?? $r['author_name'] ?? '') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- KPIs -->
  <div class="flex md:grid md:grid-cols-4 gap-2 md:gap-3 mb-8 md:mb-10 overflow-x-auto pb-1 md:pb-0 -mx-1 px-1 md:mx-0 md:px-0 snap-x snap-mandatory md:snap-none">
    <div class="stat-card bg-white p-3 md:p-4 border border-slate-200 rounded-lg shadow-sm hover:border-emerald-300/60 hover:shadow transition min-w-[7rem] md:min-w-0 shrink-0 snap-start">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Sujets</p>
      <p class="text-lg md:text-xl font-black italic text-slate-900 tabular-nums mt-0.5"><?= (int) ($topicCount ?? 0) ?></p>
      <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">total</p>
    </div>
    <div class="stat-card bg-white p-3 md:p-4 border border-slate-200 rounded-lg shadow-sm hover:border-emerald-300/60 hover:shadow transition min-w-[7rem] md:min-w-0 shrink-0 snap-start">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Messages</p>
      <p class="text-lg md:text-xl font-black italic text-slate-900 tabular-nums mt-0.5"><?= (int) ($postCount ?? 0) ?></p>
      <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">total</p>
    </div>
    <div class="stat-card bg-white p-3 md:p-4 border border-slate-200 rounded-lg shadow-sm hover:border-emerald-300/60 hover:shadow transition min-w-[7rem] md:min-w-0 shrink-0 snap-start">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Cette semaine</p>
      <p class="text-lg md:text-xl font-black italic text-emerald-800 tabular-nums mt-0.5">+<?= (int) ($postsThisWeek ?? 0) ?></p>
      <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">messages</p>
    </div>
    <div class="stat-card bg-white p-3 md:p-4 border border-slate-200 rounded-lg shadow-sm hover:border-emerald-300/60 hover:shadow transition min-w-[7rem] md:min-w-0 shrink-0 snap-start">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Membres actifs</p>
      <p class="text-lg md:text-xl font-black italic text-slate-900 tabular-nums mt-0.5"><?= (int) ($activeMembers24h ?? 0) ?></p>
      <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">24 h</p>
    </div>
  </div>

  <div class="lg:grid lg:grid-cols-12 lg:gap-8">
    <div class="lg:col-span-8 space-y-8">
      <!-- Annonces officielles — style Chambre des Murmures -->
      <?php if (!empty($pinnedAnnouncements) && isset($announcementsCategory)): ?>
        <section>
          <div class="flex items-center gap-3 mb-4">
            <span class="h-px w-8 bg-emerald-500/40"></span>
            <span class="text-[8px] font-black uppercase tracking-[0.4em] text-emerald-700"><?= htmlspecialchars($labels['official_announcements'] ?? 'Communiqués officiels') ?></span>
            <span class="flex-1 h-px bg-slate-200"></span>
          </div>
          <div class="flex flex-col gap-4">
            <?php foreach (array_slice($pinnedAnnouncements, 0, 3) as $i => $ann): ?>
              <div class="bg-white border border-emerald-200/80 p-8 anim-up rounded-xl shadow-sm" style="animation-delay:<?= 120 + $i * 40 ?>ms">
                <!-- En-tête carte -->
                <div class="flex items-start justify-between border-b border-slate-100 pb-6 mb-6 gap-4">
                  <div class="flex items-center gap-4">
                    <div class="shrink-0 h-12 w-12 bg-emerald-100 text-emerald-900 font-black flex items-center justify-center italic text-lg select-none rounded-md">
                      <?= mb_strtoupper(mb_substr($ann['topic_author_display'] ?? $ann['author_name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div>
                      <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Équipe</p>
                      <h2 class="text-xl font-black text-slate-900 uppercase italic leading-tight"><?= htmlspecialchars($ann['topic_author_display'] ?? $ann['author_name'] ?? '') ?></h2>
                    </div>
                  </div>
                  <div class="text-right shrink-0">
                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter">Édit n°<?= str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT) ?></p>
                    <p class="text-[9px] font-bold text-slate-400 uppercase"><?= date('d M Y', strtotime($ann['updated_at'] ?? $ann['created_at'] ?? 'now')) ?></p>
                  </div>
                </div>
                <!-- Corps -->
                <div>
                  <p class="text-base font-black text-slate-900 leading-tight uppercase italic mb-4">
                    <?= htmlspecialchars($ann['title']) ?>
                  </p>
                  <?php
                  $excerpt = isset($ann['body']) ? strip_tags((string)$ann['body']) : '';
                  if ($excerpt === '' && isset($ann['title'])) $excerpt = (string)$ann['title'];
                  $excerpt = mb_substr($excerpt, 0, 220);
                  if (mb_strlen($excerpt) >= 200) $excerpt .= '…';
                  ?>
                  <p class="text-sm text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($excerpt)) ?></p>
                  <a href="<?= $baseUrl ?>/forum/topic/<?= (int) $ann['id'] ?>" class="inline-block mt-4 text-[9px] font-black uppercase tracking-widest text-emerald-700 hover:text-emerald-600 transition-colors">
                    Lire la suite →
                  </a>
                </div>
                <!-- Pied de carte -->
                <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between flex-wrap gap-3">
                  <div class="flex gap-2">
                    <span class="px-3 py-1 bg-slate-100 text-[9px] font-black text-slate-700 uppercase tracking-widest rounded">Épinglé</span>
                    <span class="px-3 py-1 bg-emerald-50 text-[9px] font-black text-emerald-800 uppercase tracking-widest rounded border border-emerald-200/80">Officiel</span>
                  </div>
                  <div class="flex items-center gap-5">
                    <span class="text-[9px] font-bold text-slate-400 tracking-widest uppercase">Réf. <?= htmlspecialchars($announcementsCategory['name'] ?? 'Annonce') ?> · <?= date('y', strtotime($ann['created_at'] ?? 'now')) ?>-<?= str_pad((string)(int)$ann['id'], 2, '0', STR_PAD_LEFT) ?></span>
                    <a href="<?= $baseUrl ?>/forum/topic/<?= (int) $ann['id'] ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-900 transition-colors">
                      Accéder au fil →
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (!empty($interteamSharedTopics)): ?>
      <section class="mb-10 rounded-xl border border-sky-200 bg-sky-50/60 p-5 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
          <span class="h-px w-8 bg-sky-400/60"></span>
          <span class="text-[8px] font-black uppercase tracking-[0.35em] text-sky-900">Coopération inter-unités</span>
          <span class="flex-1 h-px bg-sky-200"></span>
        </div>
        <p class="text-xs text-sky-950/90 mb-4 leading-relaxed">Sujets partagés avec votre unité dans le cadre d’une mission commune. Lecture seule depuis votre brief.</p>
        <ul class="space-y-2">
          <?php foreach ($interteamSharedTopics as $st): ?>
          <li>
            <a href="<?= $baseUrl ?>/forum/coop/<?= htmlspecialchars((string) ($st['mission_slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>/sujet/<?= (int) ($st['topic_id'] ?? 0) ?>" class="text-sm font-semibold text-sky-900 hover:text-sky-700">
              <?= htmlspecialchars((string) ($st['topic_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span class="text-[10px] text-sky-800/80 ml-2">— <?= htmlspecialchars((string) ($st['mission_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php if (!empty($forumCommunitySectionClosedNotice)): ?>
      <section class="mb-10 rounded-xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm">
        <div class="flex items-center gap-3 mb-3">
          <span class="h-px w-8 bg-amber-400/70"></span>
          <span class="text-[8px] font-black uppercase tracking-[0.35em] text-amber-900">Section unité</span>
          <span class="flex-1 h-px bg-amber-200"></span>
        </div>
        <p class="text-sm text-amber-950 leading-relaxed whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $forumCommunitySectionClosedNotice, ENT_QUOTES, 'UTF-8')) ?></p>
      </section>
      <?php endif; ?>

      <?php if (!empty($forumOrganizationCategories)): ?>
      <section class="mb-10">
        <div class="flex items-center gap-3 mb-6">
          <span class="flex-1 h-px bg-white/5"></span>
          <span class="text-[8px] font-black uppercase tracking-[0.4em] text-emerald-700">Espaces organisations</span>
          <span class="flex-1 h-px bg-slate-200"></span>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <?php foreach ($forumOrganizationCategories as $ocat): ?>
            <div class="forum-category-root rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-emerald-400/60 hover:shadow-md overflow-hidden" data-forum-category-root="1" data-category-id="<?= (int) ($ocat['id'] ?? 0) ?>" data-category-name="<?= htmlspecialchars($ocat['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-category-scope="<?= htmlspecialchars($ocat['scope'] ?? 'organization', ENT_QUOTES, 'UTF-8') ?>" data-category-locked="<?= !empty($ocat['is_locked']) ? '1' : '0' ?>">
            <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($ocat['slug']) ?>" class="block p-5 hover:bg-slate-50/50 transition">
              <p class="text-[9px] font-black uppercase tracking-widest text-emerald-700 mb-1">Unité</p>
              <h3 class="text-lg font-black text-slate-900 uppercase italic"><?= htmlspecialchars($ocat['name']) ?></h3>
              <p class="text-xs text-slate-600 mt-2 line-clamp-2"><?= htmlspecialchars($ocat['description'] ?? '') ?></p>
              <p class="text-[10px] text-slate-500 mt-3"><?= (int) ($ocat['topic_count'] ?? 0) ?> sujets · <?= (int) ($ocat['post_count'] ?? 0) ?> messages</p>
            </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!empty($forumModerationCategories)): ?>
      <section class="mb-10">
        <div class="flex items-center gap-3 mb-6">
          <span class="flex-1 h-px bg-rose-200"></span>
          <span class="text-[8px] font-black uppercase tracking-[0.4em] text-rose-800">Espaces modération</span>
          <span class="flex-1 h-px bg-rose-200"></span>
        </div>
        <p class="text-xs text-slate-600 mb-4 max-w-2xl">Canaux visibles uniquement par l’équipe de modération (signalements internes, consignes, coordination).</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <?php foreach ($forumModerationCategories as $mcat): ?>
            <div class="forum-category-root rounded-xl border-2 border-rose-200 bg-rose-50/80 shadow-sm transition hover:border-rose-400 hover:shadow-md overflow-hidden" data-forum-category-root="1" data-category-id="<?= (int) ($mcat['id'] ?? 0) ?>" data-category-name="<?= htmlspecialchars($mcat['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-category-scope="<?= htmlspecialchars($mcat['scope'] ?? 'moderation', ENT_QUOTES, 'UTF-8') ?>" data-category-locked="<?= !empty($mcat['is_locked']) ? '1' : '0' ?>">
            <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($mcat['slug']) ?>" class="block p-5 hover:bg-rose-50 transition">
              <p class="text-[9px] font-black uppercase tracking-widest text-rose-800 mb-1">Staff</p>
              <h3 class="text-lg font-black text-slate-900 uppercase italic"><?= htmlspecialchars($mcat['name']) ?></h3>
              <p class="text-xs text-slate-700 mt-2 line-clamp-2"><?= htmlspecialchars($mcat['description'] ?? '') ?></p>
              <p class="text-[10px] text-rose-900 mt-3 font-bold"><?= (int) ($mcat['topic_count'] ?? 0) ?> sujets · <?= (int) ($mcat['post_count'] ?? 0) ?> messages</p>
              <?php if (!empty($mcat['children'])): ?>
              <ul class="mt-3 space-y-1 border-t border-rose-200/80 pt-3">
                <?php foreach ($mcat['children'] as $ch): ?>
                <li><a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($ch['slug']) ?>" class="text-xs font-semibold text-rose-900 hover:underline">↳ <?= htmlspecialchars($ch['name']) ?></a></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- Canaux (forum général) -->
      <section>
        <div class="flex items-center gap-3 mb-6">
          <span class="flex-1 h-px bg-white/5"></span>
          <span class="text-[8px] font-black uppercase tracking-[0.4em] text-slate-500"><?= htmlspecialchars($labels['categories'] ?? 'Canaux') ?> · <?= count($categories ?? []) ?> <?= $labels['channels_active'] ?? 'zones actives' ?></span>
          <span class="flex-1 h-px bg-slate-200"></span>
        </div>
        <div class="space-y-4">
          <?php
          $colorThemes = ['orange' => ['border' => 'border-orange-200', 'hover' => 'hover:border-orange-400', 'glow' => 'bg-orange-100/50', 'icon' => 'bg-orange-100 text-orange-900 border border-orange-200', 'title' => 'group-hover:text-orange-700'], 'indigo' => ['border' => 'border-slate-200', 'hover' => 'hover:border-indigo-400', 'glow' => 'bg-indigo-50', 'icon' => 'bg-slate-100 border border-slate-200 group-hover:bg-indigo-100 group-hover:text-indigo-900', 'title' => 'group-hover:text-indigo-700'], 'violet' => ['border' => 'border-slate-200', 'hover' => 'hover:border-violet-400', 'glow' => 'bg-violet-50', 'icon' => 'bg-slate-100 border border-slate-200 group-hover:bg-violet-100 group-hover:text-violet-900', 'title' => 'group-hover:text-violet-700'], 'rose' => ['border' => 'border-slate-200', 'hover' => 'hover:border-rose-400', 'glow' => 'bg-rose-50', 'icon' => 'bg-slate-100 border border-slate-200 group-hover:bg-rose-100 group-hover:text-rose-900', 'title' => 'group-hover:text-rose-700'], 'emerald' => ['border' => 'border-emerald-200', 'hover' => 'hover:border-emerald-500', 'glow' => 'bg-emerald-50', 'icon' => 'bg-emerald-100 border border-emerald-200 group-hover:bg-emerald-600 group-hover:text-white', 'title' => 'group-hover:text-emerald-800'], 'slate' => ['border' => 'border-slate-200', 'hover' => 'hover:border-slate-400', 'glow' => 'bg-slate-100/80', 'icon' => 'bg-slate-100 border border-slate-200 group-hover:bg-slate-200', 'title' => 'group-hover:text-slate-900']];
          foreach ($categories ?? [] as $idx => $cat):
            $theme = $colorThemes[$cat['color_theme'] ?? 'slate'] ?? $colorThemes['slate'];
            $num = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
          ?>
            <div class="category-card forum-category-root group border <?= $theme['border'] ?> <?= $theme['hover'] ?> bg-white rounded-xl shadow-sm transition relative overflow-hidden hover:shadow-md" data-forum-category-root="1" data-category-id="<?= (int) ($cat['id'] ?? 0) ?>" data-category-name="<?= htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-category-scope="<?= htmlspecialchars($cat['scope'] ?? 'general', ENT_QUOTES, 'UTF-8') ?>" data-category-locked="<?= !empty($cat['is_locked']) ? '1' : '0' ?>">
              <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($cat['slug']) ?>" class="block">
              <div class="absolute -right-10 -top-10 w-52 h-52 <?= $theme['glow'] ?> blur-3xl opacity-0 group-hover:opacity-100 transition-opacity rounded-full pointer-events-none"></div>
              <div class="relative flex flex-col md:flex-row p-4 md:p-6">
                <span class="absolute top-3 right-3 text-[8px] font-bold text-slate-300/90 tabular-nums select-none"><?= $num ?></span>
                <div class="w-16 h-16 md:w-20 md:h-20 <?= $theme['icon'] ?> flex items-center justify-center text-2xl md:text-3xl mb-4 md:mb-0 md:mr-6 transition -rotate-0 group-hover:-rotate-3 rounded-lg">📁</div>
                <div class="flex-1">
                  <h2 class="text-xl md:text-2xl font-black italic uppercase tracking-tighter text-slate-900 <?= $theme['title'] ?>"><?= htmlspecialchars($cat['name']) ?></h2>
                  <p class="text-sm text-slate-600 line-clamp-2 mt-1"><?= htmlspecialchars($cat['description'] ?? '') ?></p>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 border-l border-slate-200 pl-4">
                    <div>
                      <p class="text-[8px] font-black text-slate-500 uppercase">Sujets</p>
                      <p class="text-xs font-black text-slate-900"><?= (int) ($cat['topic_count'] ?? 0) ?></p>
                    </div>
                    <div>
                      <p class="text-[8px] font-black text-slate-500 uppercase">Messages</p>
                      <p class="text-xs font-black text-slate-900"><?= (int) ($cat['post_count'] ?? 0) ?></p>
                    </div>
                    <div>
                      <p class="text-[8px] font-black text-slate-500 uppercase"><?= $labels['last_activity'] ?? 'Dernier signal' ?></p>
                      <p class="text-xs text-slate-600"><?= $cat['last_post_at'] ? date('d/m H:i', strtotime($cat['last_post_at'])) : '—' ?></p>
                    </div>
                    <div>
                      <p class="text-[8px] font-black text-slate-500 uppercase"><?= $labels['by'] ?? 'Par' ?></p>
                      <p class="text-xs text-slate-600"><?= htmlspecialchars($cat['last_post_author'] ?? '—') ?></p>
                    </div>
                  </div>
                </div>
                <div class="mt-4 md:mt-0 flex items-center justify-end">
                  <span class="h-12 w-12 border border-slate-200 flex items-center justify-center text-xl text-slate-500 group-hover:border-emerald-500/60 group-hover:text-emerald-700 transition rounded-md">→</span>
                </div>
              </div>
              </a>
              <?php if (!empty($cat['children'])): ?>
              <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-3 flex flex-wrap gap-2">
                <?php foreach ($cat['children'] as $sub): ?>
                  <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($sub['slug'] ?? '') ?>" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-800 hover:border-emerald-400 transition">↳ <?= htmlspecialchars($sub['name'] ?? '') ?></a>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <!-- Sidebar -->
    <aside class="lg:col-span-4 space-y-8 mt-10 lg:mt-0">
      <!-- Accès rapides -->
      <div>
        <div class="flex items-center gap-2 mb-3">
          <span class="h-px w-6 bg-emerald-500/50"></span>
          <span class="text-[10px] font-black uppercase tracking-[0.3em] text-emerald-800">Accès rapides</span>
        </div>
        <div class="bg-white border border-slate-200 divide-y divide-slate-100 rounded-lg shadow-sm overflow-hidden">
          <a href="<?= $baseUrl ?>/formations" class="block px-4 py-3 hover:bg-slate-50 transition">
            <p class="text-xs font-bold text-slate-900 hover:text-emerald-700">Formations</p>
            <p class="text-[9px] text-slate-500">Catalogue et suivi des formations</p>
          </a>
          <?php if (\App\Core\Gate::getInstance()->allows('documents.view')): ?>
          <a href="<?= $baseUrl ?>/documents" class="block px-4 py-3 hover:bg-slate-50 transition">
            <p class="text-xs font-bold text-slate-900 hover:text-emerald-700">Documents</p>
            <p class="text-[9px] text-slate-500">Consultation des documents et fiches</p>
          </a>
          <?php endif; ?>
          <a href="<?= $baseUrl ?>/atak" class="block px-4 py-3 hover:bg-slate-50 transition">
            <p class="text-xs font-bold text-slate-900 hover:text-emerald-700">ATAK / TACMAP</p>
            <p class="text-[9px] text-slate-500">Carte tactique, marqueurs et outils C2</p>
          </a>
        </div>
      </div>

      <?php if (function_exists('forum_user_can_moderate') && forum_user_can_moderate()): ?>
      <?php
      $prRaw = $pendingReports ?? null;
      $reportPending = is_countable($prRaw) ? count($prRaw) : (int) ($prRaw ?? 0);
      $queueArtifacts = (int) ($contentModerationQueueCount ?? 0);
      $artifactsReady = !empty($moderationArtifactsTableAvailable);
      ?>
      <div>
        <div class="flex items-center gap-2 mb-3">
          <span class="h-px w-6 bg-violet-500/60"></span>
          <span class="text-[10px] font-black uppercase tracking-[0.28em] text-violet-900"><?= htmlspecialchars($labels['moderation_automation'] ?? 'Modération & automatisation') ?></span>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/90 shadow-sm">
          <div class="border-b border-slate-100 px-4 py-3">
            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-500">Signalements utilisateurs</p>
            <div class="mt-2 flex items-start justify-between gap-3">
              <div>
                <?php if ($reportPending > 0): ?>
                  <p class="text-lg font-black tabular-nums text-rose-700"><?= $reportPending ?></p>
                  <p class="text-[10px] text-slate-600">en attente de traitement</p>
                <?php else: ?>
                  <p class="text-sm font-bold text-emerald-800">Aucun signalement</p>
                  <p class="text-[10px] text-slate-500">File vide</p>
                <?php endif; ?>
              </div>
              <a href="<?= $baseUrl ?>/back-office/forum-moderation" class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-800 shadow-sm transition hover:border-violet-300 hover:text-violet-900">Console</a>
            </div>
          </div>
          <?php if ($artifactsReady): ?>
          <div class="px-4 py-3">
            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-500">Fichiers &amp; quarantaine</p>
            <p class="mt-0.5 text-[10px] leading-snug text-slate-500">Résultats du scan automatique (pièces jointes, uploads) avant validation.</p>
            <div class="mt-2 flex items-start justify-between gap-3">
              <div>
                <?php if ($queueArtifacts > 0): ?>
                  <p class="text-lg font-black tabular-nums text-amber-800"><?= $queueArtifacts ?></p>
                  <p class="text-[10px] text-slate-600">élément(s) en file</p>
                <?php else: ?>
                  <p class="text-sm font-bold text-emerald-800">File vide</p>
                  <p class="text-[10px] text-slate-500">Aucune quarantaine active</p>
                <?php endif; ?>
              </div>
              <a href="<?= $baseUrl ?>/admin/content-moderation" class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-800 shadow-sm transition hover:border-amber-300 hover:text-amber-900">Traiter</a>
            </div>
          </div>
          <?php else: ?>
          <div class="border-t border-slate-100 px-4 py-3">
            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Scan &amp; quarantaine</p>
            <p class="mt-1 text-[10px] text-slate-500">Module de modération fichiers non déployé sur cette instance.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div>
        <div class="flex items-center gap-2 mb-3">
          <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full" style="box-shadow: 0 0 8px rgba(16,185,129,0.45);"></span>
          <span class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-800"><?= $labels['recent_archives'] ?? 'Archives récentes' ?></span>
        </div>
        <div class="bg-white border border-slate-200 divide-y divide-slate-100 rounded-lg shadow-sm overflow-hidden">
          <?php foreach (array_slice($recentTopics ?? [], 0, 8) as $t): ?>
            <a href="<?= $baseUrl ?>/forum/topic/<?= (int) $t['id'] ?>" class="block px-4 py-3 hover:bg-slate-50 transition">
              <span class="text-[10px] font-bold text-slate-500"><?= htmlspecialchars($t['category_name'] ?? '') ?></span>
              <p class="text-xs font-bold text-slate-900 hover:text-emerald-700 truncate"><?= htmlspecialchars($t['title']) ?></p>
              <p class="text-[9px] text-slate-500 italic">Par <?= htmlspecialchars($t['last_author_name'] ?? '') ?> · <?= $t['updated_at'] ? date('d/m H:i', strtotime($t['updated_at'])) : '' ?></p>
            </a>
          <?php endforeach; ?>
          <?php if (empty($recentTopics)): ?>
            <p class="px-4 py-6 text-xs text-slate-500">Aucun sujet récent.</p>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <h3 class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-3">Top contributeurs</h3>
        <div class="bg-white border border-slate-200 p-4 space-y-2 rounded-lg shadow-sm">
          <?php
          $contributorClasses = ['bg-orange-500/20 border-orange-500/30 text-orange-400', 'bg-indigo-500/20 border-indigo-500/30 text-indigo-400', 'bg-violet-500/20 border-violet-500/30 text-violet-400', 'bg-rose-500/20 border-rose-500/30 text-rose-400', 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400'];
          foreach (array_slice($topContributors ?? [], 0, 5) as $ci => $c):
            $cls = $contributorClasses[$ci % count($contributorClasses)];
          ?>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-8 h-8 flex items-center justify-center text-xs font-black border <?= $cls ?>"><?= mb_substr($c['display_name_resolved'] ?? $c['display_name'] ?? $c['callsign'] ?? '?', 0, 1) ?></span>
                <span class="text-xs font-bold text-slate-900 uppercase"><?= htmlspecialchars($c['display_name_resolved'] ?? $c['display_name'] ?? $c['callsign'] ?? 'Anon') ?></span>
              </div>
              <span class="text-[9px] font-black text-slate-500"><?= (int) ($c['post_count'] ?? 0) ?> MSG</span>
            </div>
          <?php endforeach; ?>
          <?php if (empty($topContributors)): ?>
            <p class="text-xs text-slate-500">Aucun message encore.</p>
          <?php endif; ?>
        </div>
      </div>

    </aside>
  </div>
</div>

<?php if ($forumContextMenuEnabled): ?>
<div id="forum-context-menu-config" class="hidden" data-enabled="1" data-api-url="<?= htmlspecialchars($forumCategoriesApiUrl, ENT_QUOTES, 'UTF-8') ?>" data-csrf="<?= htmlspecialchars($forumCsrfToken, ENT_QUOTES, 'UTF-8') ?>" data-full-admin="<?= $forumFullCategoryAdmin ? '1' : '0' ?>" data-admin-url="<?= htmlspecialchars($forumAdminForumConfigUrl, ENT_QUOTES, 'UTF-8') ?>" data-context-tenant-id="<?= (int) $forumContextTenantId ?>" data-delete-menu="<?= $forumCanDeleteCategoryMenu ? '1' : '0' ?>"></div>

<div id="forum-subcategory-modal" class="forum-modal-backdrop fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" aria-hidden="true">
  <div class="forum-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="forum-subcat-modal-title">
    <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-emerald-50 to-white">
      <h2 id="forum-subcat-modal-title" class="text-sm font-black uppercase tracking-[0.15em] text-slate-900">Nouvelle sous-catégorie</h2>
      <p id="forum-subcat-modal-parent" class="text-xs text-slate-600 mt-1"></p>
    </div>
    <form id="forum-subcategory-form" class="p-5 space-y-4">
      <div>
        <label for="forum-subcat-name" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Nom <span class="text-rose-600">*</span></label>
        <input type="text" id="forum-subcat-name" name="name" required maxlength="120" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30" placeholder="Ex. Briefings équipe Alpha">
      </div>
      <div>
        <label for="forum-subcat-slug" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Adresse courte du canal <span class="text-slate-400 font-normal normal-case">(optionnel)</span></label>
        <input type="text" id="forum-subcat-slug" name="slug" maxlength="80" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30" placeholder="Laissez vide pour la dériver du nom">
      </div>
      <div>
        <label for="forum-subcat-desc" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Description <span class="text-slate-400 font-normal normal-case">(optionnel)</span></label>
        <textarea id="forum-subcat-desc" name="description" rows="2" maxlength="2000" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 resize-y min-h-[4rem]" placeholder="Une ligne pour situer le canal"></textarea>
      </div>
      <p id="forum-subcat-error" class="hidden text-xs text-rose-700 font-semibold"></p>
      <div class="flex flex-wrap gap-2 justify-end pt-1">
        <button type="button" id="forum-subcat-cancel" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50">Annuler</button>
        <button type="submit" id="forum-subcat-submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-emerald-500 shadow-sm">Créer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
