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
$forumNewTopicTenantContext = (int) ($forumNewTopicTenantContext ?? 0);
$forumBackForumUrl = $baseUrl . '/forum';
if ($forumNewTopicTenantContext > 1) {
    $forumBackForumUrl .= '?forum_tenant=' . $forumNewTopicTenantContext;
}
?>
<main class="w-full px-4 sm:px-6 lg:px-8 py-10 bg-[#f8fafc]">
  <div class="max-w-6xl mx-auto">
  <nav class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-600 mb-6">
    <a href="<?= htmlspecialchars($forumBackForumUrl, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-700">Forum</a>
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
        <?php if ($forumNewTopicTenantContext > 1): ?>
        <input type="hidden" name="forum_tenant" id="forum-new-topic-tenant-ctx" value="<?= (int) $forumNewTopicTenantContext ?>">
        <?php endif; ?>

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

        <input type="hidden" name="attachment_ids" id="nt-attachment-ids" value="[]">
        <div>
          <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
            <label for="topic-content" class="block text-[10px] font-black uppercase tracking-wider text-neutral-500">Corps du flux *</label>
            <div class="flex items-center gap-2">
              <span id="nt-draft-badge" class="hidden text-[8px] font-bold text-emerald-700">Brouillon enregistré</span>
              <span class="text-[8px] text-neutral-600 lg:hidden">Aperçu à droite (grand écran)</span>
            </div>
          </div>
          <div id="nt-drop-zone" class="rounded-lg border border-dashed border-slate-200 transition-colors relative">
          <div id="nt-toolbar-root" class="flex flex-wrap gap-1 p-2 bg-slate-100 border border-slate-200 border-b-0 rounded-t-md">
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition rounded" data-fc-wrap="**" data-fc-end="**" title="Gras (Ctrl+B)"><strong>G</strong></button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 italic transition rounded" data-fc-wrap="_" data-fc-end="_" title="Italique (Ctrl+I)">I</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition rounded" data-fc-wrap="~~" data-fc-end="~~" title="Barré"><del>S</del></button>
            <span class="w-px h-5 bg-slate-300 mx-1 self-center"></span>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 rounded" data-fc-wrap="## " data-fc-end="\n\n" title="Titre">Titre</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition" data-fc-wrap="> " data-fc-end="" title="Citation">Citation</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition" data-fc-wrap="\n- " data-fc-end="" title="Liste">Liste</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition" data-fc-wrap="\n1. " data-fc-end="" title="Numéros">1.</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 rounded" data-fc-wrap="\n---\n\n" data-fc-end="" title="Séparateur">—</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 rounded" data-fc-wrap=":::spoiler\n" data-fc-end="\n:::\n" title="Spoiler">Spoiler</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 rounded" data-fc-wrap=":::info\n" data-fc-end="\n:::\n" title="Info">Info</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 rounded" data-fc-wrap=":::warning\n" data-fc-end="\n:::\n" title="Attention">Alerte</button>
            <span class="w-px h-5 bg-slate-300 mx-1 self-center"></span>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 font-mono transition rounded" data-fc-wrap="`" data-fc-end="`" title="Code inline">`</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 font-mono transition rounded" data-fc-wrap="\n```\n" data-fc-end="\n```\n" title="Bloc code">{ }</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 rounded" data-fc-wrap="| Colonne A | Colonne B |\n| --- | --- |\n| " data-fc-end=" |  |\n" title="Tableau">Tableau</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-800 text-[10px] hover:bg-slate-50 transition rounded" id="toolbar-link" data-fc-link="1" title="Lien (Ctrl+K)">Lien</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-emerald-700 text-[10px] hover:bg-emerald-50 transition rounded" data-fc-wrap="@" data-fc-end=" " title="Mention">@</button>
            <span class="w-px h-5 bg-slate-300 mx-1 self-center"></span>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-700 text-[9px] font-bold hover:bg-slate-50 rounded" data-fc-action="structure" title="Structurer">Structurer</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-700 text-[9px] font-bold hover:bg-slate-50 rounded" data-fc-action="bullets" title="Points">Points</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-700 text-[9px] font-bold hover:bg-slate-50 rounded" data-fc-action="title" title="Titre">+Titre</button>
            <button type="button" class="px-2 py-1.5 border border-slate-200 bg-white text-slate-700 text-[9px] font-bold hover:bg-slate-50 rounded" data-fc-action="format" title="Nettoyer">Forme</button>
          </div>
          <p class="text-[9px] text-slate-500 px-2 py-1 bg-slate-50 border border-slate-200 border-b-0">Glissez-déposez des fichiers ici ou utilisez le bouton ci-dessous.</p>
          <textarea name="body" id="topic-content" rows="14" maxlength="<?= $maxLen ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 resize-y rounded-b-md rounded-t-none border-t-0 font-mono text-sm leading-relaxed" placeholder="Votre message…"></textarea>
          </div>
          <div id="nt-quality" class="flex flex-wrap gap-1 min-h-[1.25rem] mt-1"></div>
          <div class="text-[9px] text-neutral-500 mt-1 space-y-0.5">
            <div id="content-count" class="tabular-nums">0 / <?= $maxLen ?></div>
            <div id="nt-smart-meta" class="text-neutral-400 font-semibold"></div>
          </div>
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
          <div id="nt-attach-list" class="flex flex-col gap-2 mt-2 min-h-0"></div>
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
          <a href="<?= htmlspecialchars($forumBackForumUrl, ENT_QUOTES, 'UTF-8') ?>" class="border border-slate-300 bg-white px-6 py-3 text-xs font-bold uppercase text-slate-600 hover:text-slate-900 hover:border-slate-400 transition rounded-md">✕ Abandonner</a>
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

<script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/forum-composer.js"></script>
<script>
(function() {
  var baseUrl = '<?= $baseUrl ?>';
  var maxLen = <?= (int) $maxLen ?>;
  var csrf = '<?= \App\Core\Csrf::token() ?>';
  var forumTenantCtx = <?= (int) $forumNewTopicTenantContext ?>;
  var tags = [];
  var MAX_TAGS = 5;

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
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
  var livePreview = document.getElementById('live-preview');
  var ntComposer = null;
  if (window.ForumComposer && contentEl) {
    var extra = {};
    if (forumTenantCtx > 1) extra.forum_tenant = String(forumTenantCtx);
    ntComposer = window.ForumComposer.init({
      textarea: contentEl,
      previewEl: livePreview,
      maxLen: maxLen,
      uploadUrl: baseUrl + '/api/forum-upload',
      csrf: csrf,
      baseUrl: baseUrl,
      toast: toast,
      charCountEl: document.getElementById('content-count'),
      smartMetaEl: document.getElementById('nt-smart-meta'),
      draftBadgeEl: document.getElementById('nt-draft-badge'),
      qualityEl: document.getElementById('nt-quality'),
      fileInput: document.getElementById('nt-file-input'),
      attachmentListEl: document.getElementById('nt-attach-list'),
      hiddenAttachmentInput: document.getElementById('nt-attachment-ids'),
      dropZone: document.getElementById('nt-drop-zone'),
      draftKey: 'forum:draft:new-topic',
      toolbarRoot: document.getElementById('nt-toolbar-root'),
      mentionSearchUrl: baseUrl + '/api/forum?action=mention_search&q=',
      extraFormData: extra,
    });
  }
  document.addEventListener('keydown', function(e) {
    if (!contentEl || document.activeElement !== contentEl) return;
    if (e.ctrlKey || e.metaKey) {
      if (e.key === 'b') { e.preventDefault(); var b = document.querySelector('#nt-toolbar-root [data-fc-wrap="**"]'); if (b) b.click(); }
      if (e.key === 'i') { e.preventDefault(); var i = document.querySelector('#nt-toolbar-root [data-fc-wrap="_"]'); if (i) i.click(); }
      if (e.key === 'k') { e.preventDefault(); var lk = document.getElementById('toolbar-link'); if (lk) lk.click(); }
    }
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'X') {
      e.preventDefault();
      var st = document.querySelector('#nt-toolbar-root [data-fc-wrap="~~"]');
      if (st) st.click();
    }
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
    var ntIds = ntComposer && ntComposer.getAttachmentIds ? ntComposer.getAttachmentIds() : [];
    if ((content.length < 5 && ntIds.length === 0) || content.length > maxLen) { showError('Le contenu doit faire au moins 5 caractères (ou joindre des fichiers), max ' + maxLen + '.'); return; }
    var submitBtn = document.getElementById('submit-topic-btn');
    if (submitBtn) submitBtn.disabled = true;
    var createPayload = {
      action: 'create_topic',
      csrf_token: csrf,
      category_id: catId,
      title: title,
      content: content,
      tags: tags.join(','),
      attachment_ids: ntIds
    };
    if (forumTenantCtx > 1) createPayload.forum_tenant = forumTenantCtx;
    fetch(baseUrl + '/api/forum', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(createPayload)
    }).then(function(r) { return r.json(); }).then(function(d) {
      if (submitBtn) submitBtn.disabled = false;
      if (d.success && d.topic_id) {
        if (ntComposer && ntComposer.clearDraft) ntComposer.clearDraft();
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
