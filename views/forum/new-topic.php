<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$maxLen = (int) ($maxLen ?? 10000);
$categoriesWithChildren = $categoriesWithChildren ?? [];
$preselectedCategoryId = (int) ($preselectedCategoryId ?? 0);
$preselectedName = '';
$preselectedSlug = '';
foreach ($categoriesWithChildren as $root) {
    if ((int) $root['id'] === $preselectedCategoryId) {
        $preselectedName = $root['name'];
        $preselectedSlug = $root['slug'] ?? '';
        break;
    }
    foreach ($root['children'] ?? [] as $child) {
        if ((int) $child['id'] === $preselectedCategoryId) {
            $preselectedName = $child['name'];
            $preselectedSlug = $child['slug'] ?? '';
            break 2;
        }
    }
}
$agoraTitle = $labels['agora_title'] ?? 'Agora Athena';
$agoraSubtitle = $labels['agora_subtitle'] ?? 'Publier dans l\'Agora';
?>
<main class="w-full px-4 sm:px-6 lg:px-8 py-10 bg-[#080809]">
  <div class="max-w-6xl mx-auto">
  <nav class="text-[9px] font-black uppercase tracking-[0.25em] text-neutral-500 mb-6">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-orange-500">Forum</a>
    <span class="mx-2">›</span>
    <?php if ($preselectedCategoryId && $preselectedName): ?>
      <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($preselectedSlug ?: $preselectedName) ?>" class="hover:text-orange-500"><?= htmlspecialchars($preselectedName) ?></a>
      <span class="mx-2">›</span>
    <?php endif; ?>
    <span class="text-white">Nouveau sujet</span>
  </nav>

  <div class="bg-[#0a0a0c] border border-white/10 shadow-[20px_20px_60px_rgba(0,0,0,0.9)] relative overflow-hidden">
    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/20 rounded-bl-full"></div>
    <div class="h-1 w-full bg-gradient-to-r from-indigo-500/60 to-transparent"></div>
    <div class="relative flex items-center gap-4 p-6 border-b border-white/5">
      <span class="w-1 h-10 bg-indigo-500 rounded-full" style="box-shadow: 0 0 12px rgba(99,102,241,0.5);"></span>
      <div>
        <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400"><?= htmlspecialchars($agoraTitle) ?></p>
        <h1 class="text-2xl sm:text-3xl font-black italic uppercase text-white"><?= htmlspecialchars($agoraSubtitle) ?></h1>
      </div>
    </div>

    <div id="form-error" class="hidden mx-6 mt-4 p-3 border border-rose-500/30 bg-rose-500/10 flex items-center gap-2 text-rose-400 text-sm">
      <span aria-hidden="true">⚠</span>
      <span id="form-error-text"></span>
    </div>

    <div class="flex flex-col lg:flex-row gap-0 lg:gap-6 p-6 sm:p-8">
      <!-- Colonne formulaire -->
      <form id="new-topic-form" class="flex-1 min-w-0 space-y-6">
        <?= \App\Core\Csrf::field() ?>

        <div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">Secteur de diffusion *</label>
          <div class="csel-wrapper relative">
            <button type="button" id="csel-trigger" class="w-full bg-black/50 border border-white/10 text-left px-4 py-3 text-white flex items-center justify-between focus:outline-none focus:border-indigo-500/50 transition" aria-haspopup="listbox" aria-expanded="false">
              <span id="csel-label"><?= $preselectedCategoryId && $preselectedName ? htmlspecialchars($preselectedName) : '— Choisir un secteur —' ?></span>
              <span class="csel-chevron transition-transform">▼</span>
            </button>
            <input type="hidden" name="category_id" id="category_id" value="<?= $preselectedCategoryId ?>" required>
            <div id="csel-dropdown" class="absolute top-full left-0 right-0 mt-1 bg-[#0d0d0f] border border-white/10 shadow-lg z-10 hidden max-h-60 overflow-auto">
              <div role="option" class="csel-option px-4 py-2 text-neutral-400 hover:bg-white/5 cursor-pointer" data-value="">Choisir un secteur</div>
              <?php foreach ($categoriesWithChildren as $root): ?>
                <div class="px-3 py-1.5 text-[9px] font-black uppercase text-indigo-500/80"><?= !empty($root['icon']) ? $root['icon'] . ' ' : '' ?><?= htmlspecialchars($root['name']) ?></div>
                <div role="option" class="csel-option px-4 py-2 pl-6 text-white hover:bg-white/5 cursor-pointer" data-value="<?= (int) $root['id'] ?>"><?= htmlspecialchars($root['name']) ?></div>
                <?php foreach ($root['children'] ?? [] as $child): ?>
                  <div role="option" class="csel-option px-4 py-2 pl-8 text-neutral-300 hover:bg-white/5 cursor-pointer" data-value="<?= (int) $child['id'] ?>">↳ <?= htmlspecialchars($child['name']) ?></div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div>
          <label for="topic-title" class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">En-tête du message *</label>
          <input type="text" name="title" id="topic-title" maxlength="255" required class="w-full bg-black/50 border border-white/10 text-white px-4 py-3 placeholder-neutral-500 focus:outline-none focus:border-orange-500/50" placeholder="Sujet de l'émission…">
          <p class="text-[9px] text-neutral-500 mt-1"><span id="title-count">0</span> / 255</p>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label for="topic-content" class="block text-[10px] font-black uppercase tracking-wider text-neutral-500">Corps du flux *</label>
            <span class="text-[8px] text-neutral-600 lg:hidden">Aperçu en direct à droite (desktop)</span>
          </div>
          <!-- Barre d'outils Markdown -->
          <div id="toolbar" class="flex flex-wrap gap-1 p-2 bg-black/20 border border-white/[0.06] border-b-0 rounded-t">
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 transition" data-wrap="**" data-wrap-end="**" title="Gras (Ctrl+B)"><strong>G</strong></button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 italic transition" data-wrap="_" data-wrap-end="_" title="Italique (Ctrl+I)">I</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 transition" data-wrap="~~" data-wrap-end="~~" title="Barré"><del>S</del></button>
            <span class="w-px h-5 bg-white/10 mx-1 self-center"></span>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 transition" data-wrap="> " data-wrap-end="" title="Citation">Citation</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 transition" data-wrap="\n- " data-wrap-end="" title="Liste à puces">Liste</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 transition" data-wrap="\n1. " data-wrap-end="" title="Liste numérotée">1.</button>
            <span class="w-px h-5 bg-white/10 mx-1 self-center"></span>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 font-mono transition" data-wrap="`" data-wrap-end="`" title="Code inline">`</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 font-mono transition" data-wrap="\n```\n" data-wrap-end="\n```" title="Bloc code">```</button>
            <span class="w-px h-5 bg-white/10 mx-1 self-center"></span>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 transition" id="toolbar-link" title="Lien (Ctrl+K)">Lien</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-white/10 text-[10px] hover:bg-white/5 text-orange-500/80 transition" data-wrap="@" data-wrap-end=" " title="Mention">@</button>
          </div>
          <textarea name="body" id="topic-content" rows="14" maxlength="<?= $maxLen ?>" required class="w-full bg-black/50 border border-white/10 text-white px-4 py-3 placeholder-neutral-500 focus:outline-none focus:border-orange-500/50 resize-y rounded-b font-mono text-sm leading-relaxed" placeholder="Votre message… Markdown : **gras**, *italique*, `code`, > citation, - liste"></textarea>
          <p class="text-[9px] text-neutral-500 mt-1"><span id="content-count">0</span> / <?= $maxLen ?></p>
          <p class="text-[9px] text-neutral-600 mt-1">Raccourcis : Ctrl+B (gras), Ctrl+I (italique), Ctrl+K (lien), Ctrl+Shift+X (barré).</p>
        </div>

        <div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">Tags — max 5, optionnel</label>
          <div class="flex flex-wrap gap-2 items-center p-2 bg-black/30 border border-white/10">
            <input type="text" id="tag-input" class="flex-1 min-w-[120px] bg-transparent border-0 text-white placeholder-neutral-500 focus:outline-none text-sm" placeholder="Ajouter un tag (Entrée ou virgule)" maxlength="30">
            <span id="tag-count" class="text-[9px] text-neutral-500">0 / 5</span>
          </div>
          <input type="hidden" name="tags" id="tags-hidden" value="">
          <div id="tag-pills" class="flex flex-wrap gap-2 mt-2"></div>
        </div>

        <div class="border-l-4 border-orange-500/50 pl-4 py-2 text-[11px] text-neutral-400">
          <p class="font-black text-neutral-300 mb-1">Protocole de conduite</p>
          <ul class="list-disc list-inside space-y-0.5">
            <li>Respect des autres participants</li>
            <li>Pas de spam ni de contenu hors-sujet</li>
            <li>Titre explicite et descriptif</li>
            <li>Vérifier les archives avant de créer un doublon</li>
          </ul>
        </div>

        <div class="flex flex-wrap gap-3 pt-4">
          <button type="submit" id="submit-topic-btn" class="bg-orange-500 hover:bg-orange-400 text-black px-8 py-4 font-black uppercase text-[10px] tracking-[0.25em] transition flex items-center gap-2">
            <span aria-hidden="true">✈</span> Diffuser le sujet
          </button>
          <a href="<?= $baseUrl ?>/forum" class="border border-white/10 px-6 py-3 text-xs font-bold uppercase text-neutral-400 hover:text-white transition">✕ Abandonner</a>
        </div>
      </form>

      <!-- Colonne aperçu en temps réel -->
      <div class="hidden lg:block lg:w-[380px] xl:w-[420px] flex-shrink-0">
        <div class="sticky top-4 border border-white/10 bg-[#0d0d0f] rounded-lg overflow-hidden">
          <div class="px-4 py-2.5 border-b border-white/5 bg-black/30">
            <p class="text-[9px] font-black uppercase tracking-widest text-orange-500/80">Aperçu en direct</p>
            <p class="text-[8px] text-neutral-600 mt-0.5">Rendu Markdown</p>
          </div>
          <div id="live-preview" class="p-4 min-h-[200px] max-h-[70vh] overflow-y-auto text-sm text-neutral-300 prose prose-invert max-w-none post-content">
            <p class="text-neutral-600 italic">Le rendu s'affichera ici au fur et à mesure.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>
</main>

<div id="forum-toast-nt" class="fixed bottom-4 right-4 z-50 hidden px-4 py-3 rounded border border-white/10 bg-[#0a0a0c] text-sm text-white shadow-lg"></div>

<script>
(function() {
  var baseUrl = '<?= $baseUrl ?>';
  var maxLen = <?= (int) $maxLen ?>;
  var csrf = '<?= \App\Core\Csrf::token() ?>';
  var tags = [];
  var MAX_TAGS = 5;

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  // Rendu Markdown côté client (aligné avec le PHP)
  function markdownToHtml(text) {
    if (!text || !text.trim()) return '<p class="text-neutral-600 italic">Le rendu s\'affichera ici au fur et à mesure.</p>';
    var s = escapeHtml(text);
    // Code blocks
    s = s.replace(/```(\w*)\s*([\s\S]*?)```/g, function(_, lang, code) {
      return '<pre class="my-2 p-3 bg-black/30 border border-white/10 rounded text-sm overflow-x-auto"><code>' + code + '</code></pre>';
    });
    // Inline code
    s = s.replace(/`([^`\n]+)`/g, '<code class="px-1 py-0.5 bg-white/10 rounded text-xs">$1</code>');
    // Bold
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    // Italic
    s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    s = s.replace(/_([^_]+)_/g, '<em>$1</em>');
    // Strikethrough
    s = s.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    // Links
    s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" rel="noopener noreferrer" class="text-orange-400 hover:text-orange-300 underline">$1</a>');
    // Blockquote
    s = s.replace(/^&gt;\s?(.*)$/gm, '<blockquote class="border-l-2 border-orange-500/40 pl-4 my-1.5 text-neutral-400">$1</blockquote>');
    // Unordered list
    s = s.replace(/^[-*]\s+(.+)$/gm, '<li>$1</li>');
    s = s.replace(/(<li>.*?<\/li>\n?)+/gs, function(m) { return '<ul class="list-disc list-inside space-y-0.5 my-2 text-neutral-300 pl-2">' + m + '</ul>'; });
    // Ordered list
    s = s.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');
    s = s.replace(/(<li>.*?<\/li>\n?)+/gs, function(m) { return '<ol class="list-decimal list-inside space-y-0.5 my-2 text-neutral-300 pl-2">' + m + '</ol>'; });
    return s.replace(/\n/g, '<br>');
  }

  function toast(msg) {
    var el = document.getElementById('forum-toast-nt');
    if (el) { el.textContent = msg; el.classList.remove('hidden'); setTimeout(function() { el.classList.add('hidden'); }, 3000); }
  }
  function showError(msg) {
    var b = document.getElementById('form-error');
    var t = document.getElementById('form-error-text');
    if (b && t) { t.textContent = msg; b.classList.remove('hidden'); }
  }
  function hideError() {
    var b = document.getElementById('form-error');
    if (b) b.classList.add('hidden');
  }

  var cselTrigger = document.getElementById('csel-trigger');
  var cselLabel = document.getElementById('csel-label');
  var cselDropdown = document.getElementById('csel-dropdown');
  var categoryIdInput = document.getElementById('category_id');
  if (cselTrigger && cselDropdown) {
    cselTrigger.addEventListener('click', function() {
      var open = cselDropdown.classList.toggle('hidden');
      cselTrigger.setAttribute('aria-expanded', open ? 'false' : 'true');
      cselTrigger.classList.toggle('border-indigo-500/45', !open);
      var chevron = cselTrigger.querySelector('.csel-chevron');
      if (chevron) chevron.style.transform = open ? '' : 'rotate(180deg)';
    });
    document.querySelectorAll('.csel-option').forEach(function(opt) {
      opt.addEventListener('click', function() {
        var val = opt.getAttribute('data-value');
        categoryIdInput.value = val;
        cselLabel.textContent = val ? opt.textContent.trim() : '— Choisir un secteur —';
        cselDropdown.classList.add('hidden');
        cselTrigger.setAttribute('aria-expanded', 'false');
        cselTrigger.classList.remove('border-indigo-500/45');
        var chevron = cselTrigger.querySelector('.csel-chevron');
        if (chevron) chevron.style.transform = '';
      });
    });
    document.addEventListener('click', function(e) {
      if (!cselTrigger.contains(e.target) && !cselDropdown.contains(e.target)) {
        cselDropdown.classList.add('hidden');
        cselTrigger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  var titleEl = document.getElementById('topic-title');
  var titleCount = document.getElementById('title-count');
  if (titleEl && titleCount) {
    titleEl.addEventListener('input', function() { titleCount.textContent = titleEl.value.length; });
  }
  var contentEl = document.getElementById('topic-content');
  var contentCount = document.getElementById('content-count');
  var livePreview = document.getElementById('live-preview');
  if (contentEl && contentCount) {
    contentEl.addEventListener('input', function() {
      contentCount.textContent = contentEl.value.length;
      if (livePreview) livePreview.innerHTML = markdownToHtml(contentEl.value);
    });
  }
  if (livePreview && contentEl) {
    contentEl.addEventListener('focus', function() { livePreview.innerHTML = markdownToHtml(contentEl.value); });
  }

  // Toolbar: wrap selection
  document.querySelectorAll('.toolbar-btn.rtb').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var wrap = btn.getAttribute('data-wrap') || '';
      var wrapEnd = btn.getAttribute('data-wrap-end');
      if (wrapEnd === null) wrapEnd = wrap;
      wrap = wrap.replace(/\\n/g, '\n');
      wrapEnd = (wrapEnd || '').replace(/\\n/g, '\n');
      var ta = contentEl;
      if (!ta) return;
      var start = ta.selectionStart, end = ta.selectionEnd;
      var text = ta.value;
      var before = text.slice(0, start), selected = text.slice(start, end), after = text.slice(end);
      ta.value = before + wrap + selected + wrapEnd + after;
      ta.selectionStart = ta.selectionEnd = start + wrap.length + selected.length;
      ta.focus();
      if (livePreview) livePreview.innerHTML = markdownToHtml(ta.value);
      contentCount.textContent = ta.value.length;
    });
  });
  document.getElementById('toolbar-link') && document.getElementById('toolbar-link').addEventListener('click', function() {
    var url = prompt('URL du lien :', 'https://');
    if (url === null) return;
    var text = prompt('Texte du lien :', contentEl && contentEl.value.slice(contentEl.selectionStart, contentEl.selectionEnd) || '');
    if (text === null) return;
    var insert = '[' + (text || 'lien') + '](' + url + ')';
    var ta = contentEl;
    var start = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.slice(0, start) + insert + ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = start + insert.length;
    ta.focus();
    if (livePreview) livePreview.innerHTML = markdownToHtml(ta.value);
    contentCount.textContent = ta.value.length;
  });

  document.addEventListener('keydown', function(e) {
    if (!contentEl || document.activeElement !== contentEl) return;
    if (e.ctrlKey || e.metaKey) {
      if (e.key === 'b') { e.preventDefault(); document.querySelector('.toolbar-btn[data-wrap="**"]') && document.querySelector('.toolbar-btn[data-wrap="**"]').click(); }
      if (e.key === 'i') { e.preventDefault(); document.querySelector('.toolbar-btn[data-wrap="_"]') && document.querySelector('.toolbar-btn[data-wrap="_"]').click(); }
      if (e.key === 'k') { e.preventDefault(); document.getElementById('toolbar-link') && document.getElementById('toolbar-link').click(); }
    }
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'X') { e.preventDefault(); document.querySelector('.toolbar-btn[data-wrap-end="~~"]') && document.querySelector('.toolbar-btn[data-wrap-end="~~"]').click(); }
  });

  var tagInput = document.getElementById('tag-input');
  var tagPills = document.getElementById('tag-pills');
  var tagCountEl = document.getElementById('tag-count');
  var tagsHidden = document.getElementById('tags-hidden');
  function addTag(t) {
    t = t.trim().replace(/,/g, '').slice(0, 30);
    if (!t || tags.length >= MAX_TAGS || tags.indexOf(t) !== -1) return;
    tags.push(t);
    renderTags();
    if (tagInput) tagInput.value = '';
    if (tagCountEl) tagCountEl.textContent = tags.length + ' / ' + MAX_TAGS;
    if (tagsHidden) tagsHidden.value = tags.join(',');
  }
  function removeTag(i) {
    tags.splice(i, 1);
    renderTags();
    if (tagCountEl) tagCountEl.textContent = tags.length + ' / ' + MAX_TAGS;
    if (tagsHidden) tagsHidden.value = tags.join(',');
  }
  function renderTags() {
    if (!tagPills) return;
    tagPills.innerHTML = tags.map(function(t, i) {
      return '<span class="inline-flex items-center gap-1 px-2 py-1 bg-white/10 border border-white/10 text-[10px]">' + escapeHtml(t) + '<button type="button" class="tag-remove text-neutral-400 hover:text-white" data-i="' + i + '">×</button></span>';
    }).join('');
    tagPills.querySelectorAll('.tag-remove').forEach(function(btn) {
      btn.addEventListener('click', function() { removeTag(parseInt(btn.getAttribute('data-i'), 10)); });
    });
  }
  if (tagInput) {
    tagInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(tagInput.value); }
    });
    tagInput.addEventListener('blur', function() { if (tagInput.value.trim()) addTag(tagInput.value); });
  }

  document.getElementById('new-topic-form').addEventListener('submit', function(e) {
    e.preventDefault();
    hideError();
    var catId = parseInt(categoryIdInput.value, 10);
    var title = (titleEl && titleEl.value) ? titleEl.value.trim() : '';
    var content = (contentEl && contentEl.value) ? contentEl.value.trim() : '';
    if (!catId) { showError('Choisissez un secteur.'); return; }
    if (title.length < 3 || title.length > 255) { showError('Le titre doit faire entre 3 et 255 caractères.'); return; }
    if (content.length < 5 || content.length > maxLen) { showError('Le contenu doit faire entre 5 et ' + maxLen + ' caractères.'); return; }
    var submitBtn = document.getElementById('submit-topic-btn');
    if (submitBtn) submitBtn.disabled = true;
    fetch(baseUrl + '/api/forum', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'create_topic',
        csrf_token: csrf,
        category_id: catId,
        title: title,
        content: content,
        tags: tags.join(',')
      })
    }).then(function(r) { return r.json(); }).then(function(d) {
      if (submitBtn) submitBtn.disabled = false;
      if (d.success && d.topic_id) {
        window.location.href = baseUrl + '/forum/topic/' + d.topic_id;
      } else {
        showError(d.error || 'Erreur lors de l\'envoi.');
      }
    }).catch(function() {
      if (submitBtn) submitBtn.disabled = false;
      showError('Erreur réseau.');
    });
  });
})();
</script>
