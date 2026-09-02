/**
 * Assistant rédactionnel : charge les formules et les insère au curseur.
 */
(function () {
  var root = document.querySelector('[data-writing-assistant]') || document.getElementById('courrier-snippets-root');
  if (!root) return;

  var api = root.getAttribute('data-api') || window.COURRIER_SNIPPETS_API || '';
  var docId = root.getAttribute('data-doc-id') || window.COURRIER_DOC_ID || '';
  var locked = root.getAttribute('data-locked') === '1';
  var targetId = root.getAttribute('data-target') || 'body-rendered';
  var insertMode = root.getAttribute('data-insert-mode') || 'html';

  function targetField() {
    return document.getElementById(targetId);
  }

  var toolbar = document.getElementById('courrier-insert-toolbar');
  if (toolbar && !locked) {
    toolbar.querySelectorAll('.courrier-insert-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var kind = btn.getAttribute('data-kind') || 'para';
        var chunk = insertMode === 'markdown'
          ? (kind === 'alinea' ? '\n\n    ' : '\n\n')
          : (kind === 'alinea' ? '<p class="courrier-alinea"></p>' : '<p></p>');
        insertAtCursor(targetField(), chunk);
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

  function toMarkdown(html) {
    var raw = String(html || '');
    var tmp = document.createElement('div');
    tmp.innerHTML = raw;
    var text = (tmp.textContent || tmp.innerText || raw).trim();
    return text !== '' ? text + '\n\n' : '';
  }

  function groupLabel(phase) {
    if (phase === 'intro') return 'Introductions';
    if (phase === 'transition') return 'Transitions';
    if (phase === 'closure') return 'Clôtures';
    return 'Formules';
  }

  function render(snippets) {
    if (!snippets.length) {
      root.innerHTML = '<p class="writing-assistant__muted">Aucune formule n’est proposée pour le moment. Rédigez le texte à la main, ou réessayez plus tard.</p>';
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
      html += '<div class="writing-assistant__group">';
      html += '<p class="writing-assistant__group-title">' + groupLabel(phase) + '</p>';
      html += '<ul class="writing-assistant__items">';
      byPhase[phase].forEach(function (s) {
        var esc = String(s.label || '').replace(/</g, '&lt;');
        html += '<li><button type="button" class="writing-assistant__item courrier-snippet-btn" data-html="' +
          encodeURIComponent(s.html || '') + '">' + esc + '</button></li>';
      });
      html += '</ul></div>';
    });
    root.innerHTML = html;
    root.querySelectorAll('.courrier-snippet-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var raw = decodeURIComponent(btn.getAttribute('data-html') || '');
        var chunk = insertMode === 'markdown' ? toMarkdown(raw) : raw;
        insertAtCursor(targetField(), chunk);
      });
    });
  }

  if (!api) {
    root.innerHTML = '<p class="writing-assistant__warn">L’assistant n’est pas disponible pour le moment.</p>';
    return;
  }

  var url = api + (docId ? '?document_id=' + encodeURIComponent(String(docId)) : '');
  fetch(url, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      render(data.snippets || []);
    })
    .catch(function () {
      root.innerHTML = '<p class="writing-assistant__warn">Les formules n’ont pas pu être chargées. Réessayez dans un instant.</p>';
    });

  var ta = targetField();
  if (ta && !locked && (!ta.value || !ta.value.trim())) {
    var hint = document.createElement('p');
    hint.className = 'writing-assistant__tip';
    hint.textContent = 'Astuce : commencez par une introduction proposée par l’assistant.';
    root.parentElement.appendChild(hint);
  }
})();
