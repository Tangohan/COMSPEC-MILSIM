/**
 * Éditeur documentations HTML : aperçu en direct, mode livret (aperçu), détection document complet / fragment.
 */
(function () {
  'use strict';

  var boot = document.getElementById('cp-editor-root');
  if (!boot) return;

  var ta = document.getElementById('cp-html');
  var titleInput = document.getElementById('cp-title');
  var iframe = document.getElementById('cp-preview-frame');
  var badge = document.getElementById('cp-detect-badge');
  var livretCb = document.getElementById('cp-livret-preview');
  var btnNewTab = document.getElementById('cp-open-preview-tab');
  var tabs = boot.querySelectorAll('[data-cp-tab]');
  var panelCode = document.getElementById('cp-panel-code');
  var panelPreview = document.getElementById('cp-panel-preview');
  var lastBlobUrl = null;

  function escapeTitle(s) {
    var t = (s || '').trim();
    if (!t) return 'Aperçu';
    return t
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function isFullDocument(html) {
    var t = (html || '').trim();
    return /^\s*<!DOCTYPE\s+html/i.test(t) || /^\s*<html[\s>]/i.test(t);
  }

  function docThemeFontLink() {
    return (
      '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&amp;family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&amp;display=swap">'
    );
  }

  function docThemeCssLink() {
    var href = typeof window.cpDocCssHref === 'string' ? window.cpDocCssHref : '';
    if (!href) return '';
    var esc = href.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    return '<link rel="stylesheet" href="' + esc + '">';
  }

  function docThemeHead() {
    return docThemeFontLink() + docThemeCssLink();
  }

  function wrapFragmentWithDocShell(innerHtml, title) {
    var t = escapeTitle(title);
    return (
      '<div class="formation-doc-shell formation-doc-shell--single">' +
      '<header class="formation-doc-header"><div class="formation-doc-header__inner">' +
      '<p class="formation-doc-kicker">Documentation</p>' +
      '<h1 class="formation-doc-title">' +
      t +
      '</h1></div></header>' +
      '<main class="formation-doc-main"><article class="formation-doc-prose">' +
      innerHtml +
      '</article></main></div>'
    );
  }

  function livretHeadInjection() {
    return (
      '<style id="cp-livret-styles">' +
      'html,body{min-height:100%;margin:0;background:#e2e8f0;}' +
      '.cp-livret-stage{min-height:100vh;padding:1.25rem 1rem 2.5rem;box-sizing:border-box;}' +
      '.cp-livret-sheet{max-width:52rem;margin:0 auto;background:#fff;border-radius:6px;' +
      'box-shadow:0 12px 48px rgba(15,23,42,.14);overflow:hidden;position:relative;}' +
      '.cp-livret-sheet::after{content:"";position:absolute;inset:0;pointer-events:none;opacity:.045;' +
      'background-image:repeating-linear-gradient(-12deg,#64748b 0 1px,transparent 1px 14px);}' +
      '.cp-livret-topbar{background:linear-gradient(135deg,#0f172a,#1e293b);color:#e2e8f0;padding:.65rem 1.25rem;' +
      'font-size:10px;letter-spacing:.22em;text-transform:uppercase;font-weight:800;position:relative;z-index:2;}' +
      '.cp-livret-body{padding:1.75rem 1.5rem 2rem;position:relative;z-index:2;}' +
      '.cp-livret-watermark{position:fixed;left:50%;top:46%;transform:translate(-50%,-50%) rotate(-16deg);' +
      'font-size:clamp(2.5rem,10vw,5.5rem);font-weight:900;color:#0f172a;opacity:.05;pointer-events:none;' +
      'white-space:nowrap;z-index:1;font-family:system-ui,sans-serif;}' +
      '</style>'
    );
  }

  function wrapBodyFromFragment(innerHtml, title) {
    var bodyInner = wrapFragmentWithDocShell(innerHtml, title);
    return (
      '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>' +
      escapeTitle(title) +
      '</title>' +
      docThemeHead() +
      '</head><body class="formation-doc-body">' +
      bodyInner +
      '</body></html>'
    );
  }

  function extractBodyInner(html) {
    try {
      var p = new DOMParser();
      var d = p.parseFromString(html, 'text/html');
      if (d && d.body) {
        return d.body.innerHTML;
      }
    } catch (e) {}
    return html;
  }

  function buildLivretPreviewHtml(source, title) {
    var inner = isFullDocument(source) ? extractBodyInner(source) : source;
    var innerThemed =
      isFullDocument(source) ? inner : '<div class="formation-doc-prose">' + inner + '</div>';
    var wrapped =
      '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>' +
      escapeTitle(title) +
      '</title>' +
      docThemeHead() +
      livretHeadInjection() +
      '</head><body>' +
      '<div class="cp-livret-watermark" aria-hidden="true">Aperçu</div>' +
      '<div class="cp-livret-stage"><div class="cp-livret-sheet">' +
      '<div class="cp-livret-topbar">Documentation — lecture à l’écran</div>' +
      '<div class="cp-livret-body">' +
      innerThemed +
      '</div></div></div></body></html>';
    return wrapped;
  }

  function buildPlainPreviewHtml(source, title) {
    if (isFullDocument(source)) {
      return source;
    }
    return wrapBodyFromFragment(source, title);
  }

  function buildPreviewHtml() {
    var title = titleInput ? titleInput.value : '';
    if (typeof window.cpHandbookIsActive === 'function' && window.cpHandbookIsActive()) {
      if (typeof window.cpBuildHandbookPreviewHtml === 'function') {
        var hb = window.cpBuildHandbookPreviewHtml();
        if (livretCb && livretCb.checked) {
          try {
            var inner = '';
            var p = new DOMParser();
            var d = p.parseFromString(hb, 'text/html');
            if (d && d.body) inner = d.body.innerHTML;
            if (inner) return buildLivretPreviewHtml(inner, title);
          } catch (e1) {}
        }
        return hb;
      }
    }
    var raw = ta ? ta.value : '';
    if (livretCb && livretCb.checked) {
      return buildLivretPreviewHtml(raw, title);
    }
    return buildPlainPreviewHtml(raw, title);
  }

  function updateBadge() {
    if (!badge || !ta) return;
    if (typeof window.cpHandbookIsActive === 'function' && window.cpHandbookIsActive()) {
      badge.textContent = 'Manuel à chapitres';
      badge.className =
        'inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-[11px] font-bold tracking-wide text-violet-900';
      return;
    }
    if (isFullDocument(ta.value)) {
      badge.textContent = 'Page complète (titre et styles inclus)';
      badge.className =
        'inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] font-bold tracking-wide text-sky-900';
    } else {
      badge.textContent = 'Extrait : le site ajoute l’en-tête minimal pour l’affichage';
      badge.className =
        'inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[11px] font-bold tracking-wide text-slate-700';
    }
  }

  function pushPreview() {
    if (!iframe || !ta) return;
    updateBadge();
    var html = buildPreviewHtml();
    try {
      if (lastBlobUrl) {
        URL.revokeObjectURL(lastBlobUrl);
        lastBlobUrl = null;
      }
      var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
      lastBlobUrl = URL.createObjectURL(blob);
      iframe.setAttribute('src', lastBlobUrl);
    } catch (e) {
      iframe.removeAttribute('src');
      try {
        iframe.srcdoc = html;
      } catch (e2) {}
    }
  }

  function debounce(fn, ms) {
    var t;
    return function () {
      clearTimeout(t);
      t = setTimeout(fn, ms);
    };
  }

  var debouncedPush = debounce(pushPreview, 280);

  if (ta) {
    ta.addEventListener('input', debouncedPush);
    ta.addEventListener('change', pushPreview);
  }
  if (titleInput) {
    titleInput.addEventListener('input', debouncedPush);
  }
  if (livretCb) {
    livretCb.addEventListener('change', pushPreview);
  }

  if (btnNewTab && ta) {
    btnNewTab.addEventListener('click', function () {
      var html = buildPreviewHtml();
      var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
      var u = URL.createObjectURL(blob);
      var w = window.open(u, '_blank', 'noopener');
      if (w) {
        setTimeout(function () {
          URL.revokeObjectURL(u);
        }, 6e4);
      } else {
        URL.revokeObjectURL(u);
      }
    });
  }

  function setTab(which) {
    if (!panelCode || !panelPreview) return;
    var isCode = which === 'code';
    var wide = window.matchMedia('(min-width: 768px)').matches;
    if (wide) {
      panelCode.classList.remove('hidden');
      panelPreview.classList.remove('hidden');
    } else {
      panelCode.classList.toggle('hidden', !isCode);
      panelPreview.classList.toggle('hidden', isCode);
    }
    tabs.forEach(function (btn) {
      var on = btn.getAttribute('data-cp-tab') === which;
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
      btn.classList.toggle('bg-white', on);
      btn.classList.toggle('shadow-sm', on);
      btn.classList.toggle('text-slate-900', on);
      btn.classList.toggle('text-slate-500', !on);
    });
  }

  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTab(btn.getAttribute('data-cp-tab') || 'code');
    });
  });

  var tablist = document.getElementById('cp-mobile-tablist');

  function applyLayout() {
    if (!panelCode || !panelPreview) return;
    var wide = window.matchMedia('(min-width: 768px)').matches;
    if (wide) {
      panelCode.classList.remove('hidden');
      panelPreview.classList.remove('hidden');
      if (tablist) tablist.classList.add('hidden');
    } else {
      if (tablist) tablist.classList.remove('hidden');
      setTab('code');
    }
  }

  var onResize = debounce(applyLayout, 200);
  window.addEventListener('resize', onResize);
  applyLayout();

  pushPreview();
})();


// DOC HTML studio: autosave local + indicateur non sauvegardé.
(function(){
  var form = document.getElementById('cp-main-form');
  if(!form){ return; }
  var pageKey = 'cp_draft_' + (window.location.pathname || 'new');
  var fields = form.querySelectorAll('input[name], textarea[name], select[name]');
  var dirty = false;
  var bar = document.createElement('p');
  bar.className = 'text-xs text-slate-500 mt-1';
  bar.textContent = 'État: sauvegardé';
  form.prepend(bar);

  function serialize(){
    var payload={};
    fields.forEach(function(f){
      if(f.type==='checkbox'){ payload[f.name]=f.checked ? '1' : '0'; return; }
      if(f.type==='radio'){ if(f.checked){ payload[f.name]=f.value; } return; }
      payload[f.name]=f.value;
    });
    return payload;
  }
  function restore(){
    try {
      var raw = localStorage.getItem(pageKey);
      if(!raw){ return; }
      var payload = JSON.parse(raw);
      Object.keys(payload).forEach(function(name){
        var list=form.querySelectorAll('[name="'+name+'"]');
        list.forEach(function(f){
          if(f.type==='checkbox'){ f.checked = payload[name] === '1'; }
          else if(f.type==='radio'){ f.checked = f.value === payload[name]; }
          else { f.value = payload[name]; }
        });
      });
      bar.textContent = 'État: brouillon local récupéré';
    } catch(e){}
  }
  function autosave(){
    try{ localStorage.setItem(pageKey, JSON.stringify(serialize())); dirty=false; bar.textContent='État: brouillon local auto-sauvegardé'; }catch(e){}
  }
  restore();
  fields.forEach(function(f){ f.addEventListener('input', function(){ dirty=true; bar.textContent='État: modifications non enregistrées'; }); });
  setInterval(function(){ if(dirty){ autosave(); } }, 8000);
  form.addEventListener('submit', function(){ try{ localStorage.removeItem(pageKey);}catch(e){} bar.textContent='État: envoi en cours…'; });
  window.addEventListener('beforeunload', function(e){ if(!dirty){ return; } e.preventDefault(); e.returnValue=''; });
})();
