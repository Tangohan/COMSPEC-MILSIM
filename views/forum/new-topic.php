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
<main class="w-full px-4 sm:px-6 lg:px-8 py-10 bg-[#f8fafc]">
  <div class="max-w-6xl mx-auto">
  <nav class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-600 mb-6">
    <a href="<?= $baseUrl ?>/forum" class="hover:text-emerald-700">Forum</a>
    <span class="mx-2 text-slate-400">›</span>
    <?php if ($preselectedCategoryId && $preselectedName): ?>
      <a href="<?= $baseUrl ?>/forum/category/<?= htmlspecialchars($preselectedSlug ?: $preselectedName) ?>" class="hover:text-emerald-700"><?= htmlspecialchars($preselectedName) ?></a>
      <span class="mx-2 text-slate-400">›</span>
    <?php endif; ?>
    <span class="text-slate-900">Nouveau sujet</span>
  </nav>

  <div class="bg-white border border-slate-200 shadow-lg relative overflow-hidden rounded-xl">
    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-100/80 rounded-bl-full"></div>
    <div class="h-1 w-full bg-gradient-to-r from-emerald-500/70 to-transparent"></div>
    <div class="relative flex items-center gap-4 p-6 border-b border-slate-100">
      <span class="w-1 h-10 bg-emerald-600 rounded-full" style="box-shadow: 0 0 12px rgba(5,150,105,0.35);"></span>
      <div>
        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-800"><?= htmlspecialchars($agoraTitle) ?></p>
        <h1 class="text-2xl sm:text-3xl font-black italic uppercase text-slate-900"><?= htmlspecialchars($agoraSubtitle) ?></h1>
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
            <button type="button" id="csel-trigger" class="w-full bg-slate-50 border border-slate-200 text-left px-4 py-3 text-slate-900 flex items-center justify-between focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 transition rounded-md" aria-haspopup="listbox" aria-expanded="false">
              <span id="csel-label"><?= $preselectedCategoryId && $preselectedName ? htmlspecialchars($preselectedName) : '— Choisir un secteur —' ?></span>
              <span class="csel-chevron transition-transform">▼</span>
            </button>
            <input type="hidden" name="category_id" id="category_id" value="<?= $preselectedCategoryId ?>" required>
            <div id="csel-dropdown" class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 shadow-lg z-10 hidden max-h-60 overflow-auto rounded-md">
              <div role="option" class="csel-option px-4 py-2 text-slate-500 hover:bg-slate-50 cursor-pointer" data-value="">Choisir un secteur</div>
              <?php foreach ($categoriesWithChildren as $root): ?>
                <div class="px-3 py-1.5 text-[9px] font-black uppercase text-emerald-800"><?= !empty($root['icon']) ? $root['icon'] . ' ' : '' ?><?= htmlspecialchars($root['name']) ?></div>
                <div role="option" class="csel-option px-4 py-2 pl-6 text-slate-900 hover:bg-slate-50 cursor-pointer" data-value="<?= (int) $root['id'] ?>"><?= htmlspecialchars($root['name']) ?></div>
                <?php foreach ($root['children'] ?? [] as $child): ?>
                  <div role="option" class="csel-option px-4 py-2 pl-8 text-slate-700 hover:bg-slate-50 cursor-pointer" data-value="<?= (int) $child['id'] ?>">↳ <?= htmlspecialchars($child['name']) ?></div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div>
          <label for="topic-title" class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">En-tête du message *</label>
          <input type="text" name="title" id="topic-title" maxlength="255" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 rounded-md" placeholder="Sujet de l'émission…">
          <p class="text-[9px] text-neutral-500 mt-1"><span id="title-count">0</span> / 255</p>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label for="topic-content" class="block text-[10px] font-black uppercase tracking-wider text-neutral-500">Corps du flux *</label>
            <span class="text-[8px] text-neutral-600 lg:hidden">Aperçu en direct à droite (desktop)</span>
          </div>
          <!-- Barre d'outils Markdown -->
          <div id="toolbar" class="flex flex-wrap gap-1 p-2 bg-slate-100 border border-slate-200 border-b-0 rounded-t-md">
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition rounded" data-wrap="**" data-wrap-end="**" title="Gras (Ctrl+B)"><strong>G</strong></button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 italic transition" data-wrap="_" data-wrap-end="_" title="Italique (Ctrl+I)">I</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition" data-wrap="~~" data-wrap-end="~~" title="Barré"><del>S</del></button>
            <span class="w-px h-5 bg-slate-300 mx-1 self-center"></span>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition" data-wrap="> " data-wrap-end="" title="Citation">Citation</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition" data-wrap="\n- " data-wrap-end="" title="Liste à puces">Liste</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition" data-wrap="\n1. " data-wrap-end="" title="Liste numérotée">1.</button>
            <span class="w-px h-5 bg-slate-300 mx-1 self-center"></span>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 font-mono transition" data-wrap="`" data-wrap-end="`" title="Code inline">`</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 font-mono transition" data-wrap="\n```\n" data-wrap-end="\n```" title="Bloc code">```</button>
            <span class="w-px h-5 bg-slate-300 mx-1 self-center"></span>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition" id="toolbar-link" title="Lien (Ctrl+K)">Lien</button>
            <button type="button" class="toolbar-btn rtb px-2 py-1.5 border border-slate-200 bg-white text-emerald-700 text-[10px] hover:bg-emerald-50 transition" data-wrap="@" data-wrap-end=" " title="Mention">@</button>
          </div>
          <textarea name="body" id="topic-content" rows="14" maxlength="<?= $maxLen ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 resize-y rounded-b-md font-mono text-sm leading-relaxed" placeholder="Votre message… Markdown : **gras**, *italique*, `code`, > citation, - liste"></textarea>
          <p class="text-[9px] text-neutral-500 mt-1"><span id="content-count">0</span> / <?= $maxLen ?></p>
          <p class="text-[9px] text-neutral-600 mt-1">Raccourcis : Ctrl+B (gras), Ctrl+I (italique), Ctrl+K (lien), Ctrl+Shift+X (barré).</p>
        </div>

        <div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">Tags — max 5, optionnel</label>
          <div class="flex flex-wrap gap-2 items-center p-2 bg-slate-50 border border-slate-200 rounded-md">
            <input type="text" id="tag-input" class="flex-1 min-w-[120px] bg-transparent border-0 text-slate-900 placeholder-slate-400 focus:outline-none text-sm" placeholder="Ajouter un tag (Entrée ou virgule)" maxlength="30">
            <span id="tag-count" class="text-[9px] text-neutral-500">0 / 5</span>
          </div>
          <input type="hidden" name="tags" id="tags-hidden" value="">
          <div id="tag-pills" class="flex flex-wrap gap-2 mt-2"></div>
        </div>

        <div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-neutral-500 mb-2">Pièces jointes (optionnel)</label>
          <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-emerald-300 bg-emerald-50/50 text-[10px] font-bold text-emerald-900 cursor-pointer hover:bg-emerald-50 transition-colors">
            <input type="file" id="nt-file-input" class="hidden" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" multiple>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
            Joindre des fichiers (images / PDF, max 5 × 5 Mo)
          </label>
          <div id="nt-upload-preview" class="flex flex-wrap gap-2 mt-2 min-h-0"></div>
        </div>

        <div class="border-l-4 border-emerald-500/60 pl-4 py-2 text-[11px] text-slate-600 bg-emerald-50/50 rounded-r-md">
          <p class="font-black text-slate-800 mb-1">Protocole de conduite</p>
          <ul class="list-disc list-inside space-y-0.5">
            <li>Respect des autres participants</li>
            <li>Pas de spam ni de contenu hors-sujet</li>
            <li>Titre explicite et descriptif</li>
            <li>Vérifier les archives avant de créer un doublon</li>
          </ul>
        </div>

        <div class="flex flex-wrap gap-3 pt-4">
          <button type="submit" id="submit-topic-btn" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-4 font-black uppercase text-[10px] tracking-[0.25em] transition flex items-center gap-2 rounded-md shadow-sm">
            <span aria-hidden="true">✈</span> Diffuser le sujet
          </button>
          <a href="<?= $baseUrl ?>/forum" class="border border-slate-300 bg-white px-6 py-3 text-xs font-bold uppercase text-slate-600 hover:text-slate-900 hover:border-slate-400 transition rounded-md">✕ Abandonner</a>
        </div>
      </form>

      <!-- Colonne aperçu en temps réel -->
      <div class="hidden lg:block lg:w-[380px] xl:w-[420px] flex-shrink-0">
        <div class="sticky top-4 border border-slate-200 bg-white rounded-lg overflow-hidden shadow-sm">
          <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50">
            <p class="text-[9px] font-black uppercase tracking-widest text-emerald-800">Aperçu en direct</p>
            <p class="text-[8px] text-slate-500 mt-0.5">Rendu Markdown</p>
          </div>
          <div id="live-preview" class="p-4 min-h-[200px] max-h-[70vh] overflow-y-auto text-sm text-slate-800 prose prose-slate max-w-none post-content">
            <p class="text-slate-500 italic">Le rendu s'affichera ici au fur et à mesure.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>
</main>

<div id="forum-toast-nt" class="fixed bottom-4 right-4 z-50 hidden px-4 py-3 rounded-lg border border-slate-200 bg-slate-900 text-sm text-white shadow-lg"></div>

<script>
(function() {
  var baseUrl = '<?= $baseUrl ?>';
  var maxLen = <?= (int) $maxLen ?>;
  var csrf = '<?= \App\Core\Csrf::token() ?>';
  var tags = [];
  var MAX_TAGS = 5;
  var ntAttachmentIds = [];

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  // Rendu Markdown côté client (aligné avec le PHP)
  function markdownToHtml(text) {
    if (!text || !text.trim()) return '<p class="text-slate-500 italic">Le rendu s\'affichera ici au fur et à mesure.</p>';
    var s = escapeHtml(text);
    // Code blocks
    s = s.replace(/```(\w*)\s*([\s\S]*?)```/g, function(_, lang, code) {
      return '<pre class="my-2 p-3 bg-slate-100 border border-slate-200 rounded text-sm overflow-x-auto text-slate-900"><code>' + code + '</code></pre>';
    });
    // Inline code
    s = s.replace(/`([^`\n]+)`/g, '<code class="px-1 py-0.5 bg-slate-100 border border-slate-200 rounded text-xs text-slate-900">$1</code>');
    // Bold
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    // Italic
    s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    s = s.replace(/_([^_]+)_/g, '<em>$1</em>');
    // Strikethrough
    s = s.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    // Links
    s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" rel="noopener noreferrer" class="text-emerald-700 hover:text-emerald-600 underline">$1</a>');
    // URLs brutes (aperçu : ouverture directe ; le message publié utilise /leave pour les externes)
    s = s.replace(/\bhttps?:\/\/[^\s<>"'\[\]]+/gi, function(raw) {
      var trail = '';
      var u = raw;
      var pm = raw.match(/([.,;:!?]+)$/);
      if (pm) { trail = pm[1]; u = raw.slice(0, -trail.length); }
      return '<a href="' + u + '" target="_blank" rel="noopener noreferrer" class="text-orange-400 hover:text-orange-300 underline break-all">' + u + '</a>' + trail;
    });
    // Blockquote
    s = s.replace(/^&gt;\s?(.*)$/gm, '<blockquote class="border-l-2 border-emerald-400 pl-4 my-1.5 text-slate-600">$1</blockquote>');
    // Unordered list
    s = s.replace(/^[-*]\s+(.+)$/gm, '<li>$1</li>');
    s = s.replace(/(<li>.*?<\/li>\n?)+/gs, function(m) { return '<ul class="list-disc list-inside space-y-0.5 my-2 text-slate-700 pl-2">' + m + '</ul>'; });
    // Ordered list
    s = s.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');
    s = s.replace(/(<li>.*?<\/li>\n?)+/gs, function(m) { return '<ol class="list-decimal list-inside space-y-0.5 my-2 text-slate-700 pl-2">' + m + '</ol>'; });
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
      cselTrigger.classList.toggle('border-emerald-500', !open);
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
        cselTrigger.classList.remove('border-emerald-500');
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

  var ntFileInput = document.getElementById('nt-file-input');
  var ntUploadPreview = document.getElementById('nt-upload-preview');
  if (ntFileInput && ntUploadPreview) {
    ntFileInput.addEventListener('change', function() {
      var files = ntFileInput.files;
      if (!files || !files.length) return;
      var fd = new FormData();
      fd.append('_csrf_token', csrf);
      for (var i = 0; i < files.length; i++) fd.append('files[]', files[i]);
      fetch(baseUrl + '/api/forum-upload', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          ntFileInput.value = '';
          if (!d.success || !d.files || !d.files.length) {
            showError(d.error || 'Envoi fichier impossible');
            return;
          }
          d.files.forEach(function(f) {
            if (ntAttachmentIds.length >= 5) return;
            ntAttachmentIds.push(f.id);
            var wrap = document.createElement('span');
            wrap.className = 'inline-flex items-center gap-1 text-[9px] font-bold text-emerald-900 bg-emerald-50 border border-emerald-200 rounded px-2 py-1';
            var id = f.id;
            wrap.innerHTML = '<span class="truncate max-w-[140px]">' + id.replace(/</g, '') + '</span><button type="button" class="text-rose-600" data-nt-rm="' + id.replace(/"/g, '') + '">×</button>';
            wrap.querySelector('button').addEventListener('click', function() {
              var rm = this.getAttribute('data-nt-rm');
              ntAttachmentIds = ntAttachmentIds.filter(function(x) { return x !== rm; });
              wrap.remove();
            });
            ntUploadPreview.appendChild(wrap);
          });
          if (d.warnings && d.warnings.length) toast(d.warnings.join(' '));
        });
    });
  }

  document.getElementById('new-topic-form').addEventListener('submit', function(e) {
    e.preventDefault();
    hideError();
    var catId = parseInt(categoryIdInput.value, 10);
    var title = (titleEl && titleEl.value) ? titleEl.value.trim() : '';
    var content = (contentEl && contentEl.value) ? contentEl.value.trim() : '';
    if (!catId) { showError('Choisissez un secteur.'); return; }
    if (title.length < 3 || title.length > 255) { showError('Le titre doit faire entre 3 et 255 caractères.'); return; }
    if ((content.length < 5 && ntAttachmentIds.length === 0) || content.length > maxLen) { showError('Le contenu doit faire au moins 5 caractères (ou joindre des fichiers), max ' + maxLen + '.'); return; }
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
        tags: tags.join(','),
        attachment_ids: ntAttachmentIds
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
