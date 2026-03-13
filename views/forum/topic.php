<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$userId = \App\Core\Session::get('user_id');
$canReply = function_exists('can') && can('forum.reply');
$firstPostId = null;
if (!empty($posts)) {
  $firstPostId = (int) $posts[0]['id'];
}
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <!-- Breadcrumb -->
  <nav class="text-[9px] font-black uppercase tracking-[0.25em] text-neutral-500 mb-6">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-orange-500">Forum</a>
    <span class="mx-2">›</span>
    <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($topic['category_slug'] ?? '') ?>" class="hover:text-orange-500"><?= htmlspecialchars($topic['category_name'] ?? '') ?></a>
    <span class="mx-2">›</span>
    <span class="text-white truncate max-w-[200px] inline-block align-bottom"><?= htmlspecialchars($topic['title']) ?></span>
  </nav>

  <!-- Topic header -->
  <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
      <div class="flex flex-wrap gap-2 mb-2">
        <?php if (!empty($topic['is_locked'])): ?><span class="text-[7px] font-black uppercase text-amber-400 border border-amber-500/30 px-1.5 py-0.5">Verrouillé</span><?php endif; ?>
        <?php if (!empty($topic['is_pinned'])): ?><span class="text-[7px] font-black uppercase text-indigo-400 border border-indigo-500/30 px-1.5 py-0.5">Épinglé</span><?php endif; ?>
        <?php if (!empty($topic['is_archived'])): ?><span class="text-[7px] font-black uppercase text-neutral-500 border border-white/10 px-1.5 py-0.5">Archivé</span><?php endif; ?>
      </div>
      <h1 class="text-2xl md:text-3xl font-black italic uppercase tracking-tighter text-white"><?= htmlspecialchars($topic['title']) ?></h1>
    </div>
    <div class="flex flex-wrap gap-2">
      <?php if ($canReply && empty($topic['is_locked'])): ?>
        <a href="#reply-form" class="bg-orange-500 hover:bg-orange-400 text-black px-4 py-2 text-[10px] font-black uppercase tracking-wider transition"><?= $labels['reply'] ?? 'Répondre' ?></a>
      <?php endif; ?>
      <?php if ($userId): ?>
        <?php if ($isSubscribed ?? false): ?>
          <form method="post" action="<?= $baseUrl ?>/forum/topic/<?= (int) $topic['id'] ?>/unsubscribe" class="inline"><?= \App\Core\Csrf::field() ?><button type="submit" class="border border-white/10 px-4 py-2 text-[10px] font-bold uppercase text-neutral-400 hover:text-white transition"><?= $labels['unsubscribe'] ?? 'Ne plus suivre' ?></button></form>
        <?php else: ?>
          <form method="post" action="<?= $baseUrl ?>/forum/topic/<?= (int) $topic['id'] ?>/subscribe" class="inline"><?= \App\Core\Csrf::field() ?><button type="submit" class="border border-indigo-500/50 text-indigo-400 px-4 py-2 text-[10px] font-bold uppercase hover:bg-indigo-500/10 transition"><?= $labels['subscribe'] ?? 'Suivre' ?></button></form>
        <?php endif; ?>
      <?php endif; ?>
      <?php if (function_exists('can') && can('forum.moderate')): ?>
        <?php if (!empty($topic['is_locked'])): ?>
          <form method="post" action="<?= $baseUrl ?>/forum/topic/<?= (int) $topic['id'] ?>/unlock" class="inline"><?= \App\Core\Csrf::field() ?><button type="submit" class="border border-white/10 px-3 py-1.5 text-[9px] font-bold uppercase text-neutral-400">Déverrouiller</button></form>
        <?php else: ?>
          <form method="post" action="<?= $baseUrl ?>/forum/topic/<?= (int) $topic['id'] ?>/lock" class="inline"><?= \App\Core\Csrf::field() ?><button type="submit" class="border border-amber-500/30 text-amber-400 px-3 py-1.5 text-[9px] font-bold uppercase">Verrouiller</button></form>
        <?php endif; ?>
        <?php if (!empty($topic['is_pinned'])): ?>
          <form method="post" action="<?= $baseUrl ?>/forum/topic/<?= (int) $topic['id'] ?>/unpin" class="inline"><?= \App\Core\Csrf::field() ?><button type="submit" class="border border-white/10 px-3 py-1.5 text-[9px] font-bold uppercase text-neutral-400">Désépingler</button></form>
        <?php else: ?>
          <form method="post" action="<?= $baseUrl ?>/forum/topic/<?= (int) $topic['id'] ?>/pin" class="inline"><?= \App\Core\Csrf::field() ?><button type="submit" class="border border-indigo-500/30 text-indigo-400 px-3 py-1.5 text-[9px] font-bold uppercase">Épingler</button></form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php $flashSuccess = \App\Core\Session::getFlash('success'); $flashError = \App\Core\Session::getFlash('error'); ?>
  <?php if ($flashSuccess): ?>
    <p class="mb-4 text-sm text-emerald-400"><?= htmlspecialchars($flashSuccess) ?></p>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <p class="mb-4 text-sm text-rose-400"><?= htmlspecialchars($flashError) ?></p>
  <?php endif; ?>

  <!-- Posts -->
  <div class="space-y-6">
    <?php foreach ($posts ?? [] as $i => $post): ?>
      <?php
      $roleClass = 'role-default';
      $authorRoleId = (int) ($post['author_role_id'] ?? 0);
      if ($firstPostId && (int) $post['id'] === $firstPostId) $roleClass = 'role-first';
      ?>
      <article class="post-role-frame <?= $roleClass ?> bg-[#0a0a0c] border border-white/5 overflow-hidden">
        <div class="flex flex-col md:flex-row">
          <!-- Author sidebar -->
          <div class="md:w-40 flex-shrink-0 p-4 border-b md:border-b-0 md:border-r border-white/5 bg-[#0d0d0f]">
            <div class="flex items-center gap-3 md:flex-col md:items-start">
              <span class="w-12 h-12 bg-white/10 border border-white/10 flex items-center justify-center text-lg font-black italic text-white"><?= mb_substr($post['author_name'] ?? '?', 0, 1) ?></span>
              <div>
                <p class="text-sm font-black uppercase text-white"><?= htmlspecialchars($post['author_name'] ?? $post['author_callsign'] ?? 'Anon') ?></p>
                <p class="text-[9px] text-neutral-500">#<?= (int) $post['id'] ?></p>
              </div>
            </div>
          </div>
          <!-- Content -->
          <div class="flex-1 p-6">
            <p class="text-[9px] text-neutral-600 mb-3"><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></p>
            <div class="post-content text-sm text-neutral-300 prose prose-invert max-w-none">
              <?= nl2br(htmlspecialchars($post['body'])) ?>
            </div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <!-- Reply form -->
  <?php if ($canReply && empty($topic['is_locked'])): ?>
    <div id="reply-form" class="mt-10 pt-8 border-t border-white/5">
      <h2 class="text-lg font-black uppercase text-white mb-4"><?= $labels['reply'] ?? 'Répondre' ?></h2>
      <form method="post" action="<?= $baseUrl ?>/forum/topic/<?= (int) $topic['id'] ?>/reply">
        <?= \App\Core\Csrf::field() ?>
        <textarea name="body" rows="6" class="w-full bg-black/50 border border-white/10 text-white placeholder-neutral-500 p-4 focus:outline-none focus:border-orange-500/50 resize-y" placeholder="Votre message..." required></textarea>
        <button type="submit" class="mt-3 bg-orange-500 hover:bg-orange-400 text-black px-6 py-3 text-[10px] font-black uppercase tracking-wider transition">Publier</button>
      </form>
    </div>
  <?php endif; ?>
</div>
