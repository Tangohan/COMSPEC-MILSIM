<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
?>
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <nav class="text-[9px] font-black uppercase tracking-[0.25em] text-neutral-500 mb-6">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-orange-500">Forum</a>
    <span class="mx-2">›</span>
    <span class="text-white">Nouveau sujet</span>
  </nav>

  <div class="bg-[#0a0a0c] border border-white/10 shadow-[20px_20px_60px_rgba(0,0,0,0.9)] relative overflow-hidden">
    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/20 rounded-bl-full"></div>
    <div class="h-1 w-full bg-gradient-to-r from-indigo-500/60 to-transparent"></div>
    <div class="relative flex items-center gap-4 p-6 border-b border-white/5">
      <span class="w-1 h-10 bg-indigo-500 rounded-full" style="box-shadow: 0 0 12px rgba(99,102,241,0.5);"></span>
      <div>
        <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400"><?= htmlspecialchars($forumConfig['name'] ?? 'Forum') ?></p>
        <h1 class="text-2xl sm:text-3xl font-black italic uppercase text-white">Transmettre un brief</h1>
      </div>
    </div>

    <?php $err = \App\Core\Session::getFlash('error'); if ($err): ?>
      <p class="mx-6 mt-4 text-sm text-rose-400"><?= htmlspecialchars($err) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= $baseUrl ?>/forum/new-topic" class="p-6 sm:p-8 space-y-6">
      <?= \App\Core\Csrf::field() ?>

      <div>
        <label for="category_id" class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">Secteur (catégorie)</label>
        <select name="category_id" id="category_id" required class="w-full bg-black/50 border border-white/10 text-white px-4 py-3 focus:outline-none focus:border-indigo-500/50">
          <option value="">— Choisir un canal —</option>
          <?php foreach ($categories ?? [] as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= ($preselectedSlug ?? '') === $c['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="title" class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">Titre</label>
        <input type="text" name="title" id="title" required maxlength="500" value="" class="w-full bg-black/50 border border-white/10 text-white px-4 py-3 placeholder-neutral-500 focus:outline-none focus:border-orange-500/50" placeholder="Titre du sujet">
      </div>

      <div>
        <label for="body" class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">Contenu</label>
        <textarea name="body" id="body" rows="10" required class="w-full bg-black/50 border border-white/10 text-white px-4 py-3 placeholder-neutral-500 focus:outline-none focus:border-orange-500/50 resize-y" placeholder="Votre message..."></textarea>
      </div>

      <div class="flex flex-wrap gap-3 pt-4">
        <button type="submit" class="bg-orange-500 hover:bg-orange-400 text-black px-8 py-4 font-black uppercase text-[10px] tracking-[0.25em] transition">Émettre</button>
        <a href="<?= $baseUrl ?>/forum" class="border border-white/10 px-6 py-3 text-xs font-bold uppercase text-neutral-400 hover:text-white transition">Annuler</a>
      </div>
    </form>
  </div>
</div>
