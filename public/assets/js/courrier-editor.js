/**
 * Assistant Courrier : charge les snippets et insertion au curseur.
 */
(function () {
  var root = document.getElementById('courrier-snippets-root');
  if (!root) return;

  var api = window.COURRIER_SNIPPETS_API || '';
  var docId = window.COURRIER_DOC_ID;
  var locked = root.getAttribute('data-locked') === '1';

  var toolbar = document.getElementById('courrier-insert-toolbar');
  if (toolbar && !locked) {
    toolbar.querySelectorAll('.courrier-insert-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ta = document.getElementById('body-rendered');
        var kind = btn.getAttribute('data-kind') || 'para';
        var html = kind === 'alinea' ? '<p class="courrier-alinea"></p>' : '<p></p>';
        insertAtCursor(ta, html);
      });
    });
  }

  function insertAtCursor(textarea, text) {
    if (!textarea || locked) return;
    var start = textarea.selectionStart || 0;
    var end = textarea.selectionEnd || 0;
    var val = textarea.value || '';
    textarea.value = val.slice(0, start) + text + val.slice(end);
    var pos = start + text.length;
    textarea.selectionStart = textarea.selectionEnd = pos;
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function groupLabel(phase) {
    if (phase === 'intro') return 'Introductions';
    if (phase === 'transition') return 'Transitions';
    if (phase === 'closure') return 'Clôtures';
    return phase;
  }

  function render(snippets) {
    if (!snippets.length) {
      root.innerHTML = '<p class="text-slate-400">Aucune formule (vérifiez ressources/courrier/snippets.json).</p>';
      return;
    }
    var byPhase = {};
    snippets.forEach(function (s) {
      var p = s.phase || 'intro';
      if (!byPhase[p]) byPhase[p] = [];
      byPhase[p].push(s);
    });
    var html = '';
    Object.keys(byPhase).forEach(function (phase) {
      html += '<div class="border-t border-slate-100 pt-2 first:border-t-0 first:pt-0">';
      html += '<p class="font-semibold text-slate-600 mb-1">' + groupLabel(phase) + '</p>';
      html += '<ul class="space-y-1">';
      byPhase[phase].forEach(function (s) {
        var esc = s.label.replace(/</g, '&lt;');
        html += '<li><button type="button" class="text-left w-full px-2 py-1 rounded hover:bg-slate-100 text-slate-700 courrier-snippet-btn" data-html="' +
          encodeURIComponent(s.html || '') + '">' + esc + '</button></li>';
      });
      html += '</ul></div>';
    });
    root.innerHTML = html;
    root.querySelectorAll('.courrier-snippet-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ta = document.getElementById('body-rendered');
        var raw = decodeURIComponent(btn.getAttribute('data-html') || '');
        insertAtCursor(ta, raw);
      });
    });
  }

  if (!api) {
    root.innerHTML = '<p class="text-amber-600 text-xs">API snippets non configurée.</p>';
    return;
  }

  var url = api + (docId ? '?document_id=' + encodeURIComponent(String(docId)) : '');
  fetch(url, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      render(data.snippets || []);
    })
    .catch(function () {
      root.innerHTML = '<p class="text-rose-600 text-xs">Impossible de charger les formules.</p>';
    });

  /* Suggestion intro si corps vide */
  var ta = document.getElementById('body-rendered');
  if (ta && !locked && (!ta.value || !ta.value.trim())) {
    var hint = document.createElement('p');
    hint.className = 'text-xs text-slate-400 mt-1';
    hint.textContent = 'Astuce : choisissez une introduction dans l’assistant à droite.';
    root.parentElement.appendChild(hint);
  }
})();
