/* Bureau SSE — « À trier » : regroupement, sélection, actions groupées, annulation (15 s), raccourcis clavier, règles Cerbère-Tri.
   Aucun texte ici : tous les libellés viennent du HTML (éléments [data-tr-msg]) pour être traduits avec la page.
   Toute écriture est un POST avec le jeton CSRF de la page ; la liste est rechargée ensuite (l'état affiché est toujours celui du serveur). */
(function () {
  'use strict';
  var root = document.querySelector('[data-tr]');
  if (!root) return;

  var CFG = {
    act: root.getAttribute('data-tr-act-url'),
    undo: root.getAttribute('data-tr-undo-url'),
    rules: root.getAttribute('data-tr-rules-url'),
    preview: root.getAttribute('data-tr-preview-url'),
    csrf: root.getAttribute('data-tr-csrf'),
    treat: root.getAttribute('data-tr-treat') === '1'
  };
  var UNDO_SECONDS = 15;
  var OPEN_MAX = 5;
  var STORE_UNDO = 'sse-triage-undo';
  var STORE_OPEN = 'sse-triage-open';
  var STORE_KEYS = 'sse-triage-keys';

  function $(sel, ctx) { return (ctx || root).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
  function store(kind, key, val) {
    try {
      var s = kind === 'session' ? window.sessionStorage : window.localStorage;
      if (val === undefined) return s.getItem(key);
      if (val === null) s.removeItem(key); else s.setItem(key, val);
    } catch (e) { /* stockage indisponible : la page marche sans */ }
    return null;
  }
  function msg(key) {
    var el = $('[data-tr-msg="' + key + '"]');
    var text = el ? el.getAttribute('data-label') || '' : '';
    for (var i = 1; i < arguments.length; i++) text = text.split('%' + i).join(String(arguments[i]));
    return text;
  }

  function post(url, data) {
    var body = new URLSearchParams();
    body.append('_csrf_token', CFG.csrf);
    Object.keys(data).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) v.forEach(function (x) { body.append(k + '[]', x); }); else if (v !== null && v !== undefined) body.append(k, v);
    });
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': CFG.csrf }, body: body })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { j.status = r.status; return j; }); })
      .catch(function () { return { ok: false, error: msg('failed') }; });
  }

  var toastEl = $('[data-tr-toast]');
  var toastTimer = null;
  function toast(text) {
    if (!toastEl) return;
    toastEl.textContent = text;
    toastEl.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { toastEl.hidden = true; }, 6000);
  }

  // ---- Barre d'annulation : survit au rechargement (sessionStorage), 15 secondes.
  var undoEl = $('[data-tr-undo]');
  var undoTimer = null;
  function showUndo(entry) {
    if (!undoEl) return;
    var textEl = $('[data-tr-undo-text]', undoEl);
    var btn = $('[data-tr-undo-btn]', undoEl);
    window.clearInterval(undoTimer);
    function tick() {
      var left = Math.ceil((entry.until - Date.now()) / 1000);
      if (left <= 0) { window.clearInterval(undoTimer); undoEl.hidden = true; store('session', STORE_UNDO, null); return; }
      btn.textContent = msg('undo', left);
    }
    textEl.textContent = entry.text;
    undoEl.hidden = false;
    tick();
    undoTimer = window.setInterval(tick, 1000);
  }
  var undoBtn = $('[data-tr-undo-btn]');
  if (undoBtn) {
    undoBtn.addEventListener('click', function () {
      var raw = store('session', STORE_UNDO);
      var entry = null;
      try { entry = raw ? JSON.parse(raw) : null; } catch (e) { entry = null; }
      if (!entry) return;
      undoBtn.disabled = true;
      post(CFG.undo, { batch: entry.batch }).then(function (res) {
        store('session', STORE_UNDO, null);
        if (res.ok) { store('session', 'sse-triage-flash', msg('undone')); window.location.reload(); } else { undoBtn.disabled = false; toast(res.error || msg('failed')); }
      });
    });
  }
  (function resume() {
    var raw = store('session', STORE_UNDO);
    try {
      var entry = raw ? JSON.parse(raw) : null;
      if (entry && entry.until > Date.now()) showUndo(entry); else store('session', STORE_UNDO, null);
    } catch (e) { store('session', STORE_UNDO, null); }
    var flash = store('session', 'sse-triage-flash');
    if (flash) { store('session', 'sse-triage-flash', null); toast(flash); }
  })();

  function remember() {
    var open = $$('[data-tr-group]').filter(function (g) { return $('[data-tr-toggle]', g).getAttribute('aria-expanded') === 'true'; })
      .map(function (g) { return g.getAttribute('data-title'); });
    store('session', STORE_OPEN, JSON.stringify(open));
  }

  function act(action, keys, extra) {
    if (!CFG.treat || !keys.length) return;
    var data = { action: action, keys: keys };
    Object.keys(extra || {}).forEach(function (k) { data[k] = extra[k]; });
    $$('[data-tr-row]').forEach(function (r) { if (keys.indexOf(r.getAttribute('data-key')) >= 0) r.classList.add('is-busy'); });
    post(CFG.act, data).then(function (res) {
      if (!res.ok) {
        $$('.is-busy').forEach(function (r) { r.classList.remove('is-busy'); });
        toast(res.error || msg('failed'));
        return;
      }
      remember();
      var text = msg(action, res.changed);
      store('session', STORE_UNDO, JSON.stringify({ batch: res.batch, text: text, until: Date.now() + UNDO_SECONDS * 1000 }));
      window.location.reload();
    });
  }

  // ---- Groupes dépliables.
  var wasOpen = [];
  try { wasOpen = JSON.parse(store('session', STORE_OPEN) || '[]'); } catch (e) { wasOpen = []; }
  $$('[data-tr-group]').forEach(function (g) {
    var btn = $('[data-tr-toggle]', g);
    var list = document.getElementById(btn.getAttribute('aria-controls'));
    function set(open) { btn.setAttribute('aria-expanded', open ? 'true' : 'false'); list.hidden = !open; }
    if (wasOpen.indexOf(g.getAttribute('data-title')) >= 0) set(true);
    btn.addEventListener('click', function () { set(btn.getAttribute('aria-expanded') !== 'true'); });
  });
  store('session', STORE_OPEN, null);

  $$('[data-tr-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });

  // ---- Boîtes de dialogue.
  $$('[data-tr-open-dialog]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.getAttribute('data-tr-open-dialog'));
      if (d && d.showModal) d.showModal();
    });
  });
  var rulesDialog = document.getElementById('tr-rules');
  var needsReload = false;
  if (rulesDialog) rulesDialog.addEventListener('close', function () { if (needsReload) window.location.reload(); });

  // ---- Sélection et barre d'actions groupées.
  var bulk = $('[data-tr-bulk]');
  function selectedRows() { return $$('[data-tr-row]').filter(function (r) { var c = $('[data-tr-select]', r); return c && c.checked; }); }
  function refreshBulk() {
    if (!bulk) return;
    var rows = selectedRows();
    bulk.hidden = rows.length === 0;
    $('[data-tr-count]', bulk).textContent = rows.length === 1 ? msg('selectedOne') : msg('selected', rows.length);
    var open = $('[data-tr-bulk-open]', bulk);
    if (open) open.textContent = msg('openN', Math.min(rows.length, OPEN_MAX));
    $$('[data-tr-group]').forEach(function (g) {
      var boxes = $$('[data-tr-select]', g);
      var gb = $('[data-tr-select-group]', g);
      if (!gb || !boxes.length) return;
      var n = boxes.filter(function (c) { return c.checked; }).length;
      gb.checked = n === boxes.length;
      gb.indeterminate = n > 0 && n < boxes.length;
    });
  }
  $$('[data-tr-select]').forEach(function (c) { c.addEventListener('change', refreshBulk); });
  $$('[data-tr-select-group]').forEach(function (gb) {
    gb.addEventListener('change', function () {
      $$('[data-tr-select]', gb.closest('[data-tr-group]')).forEach(function (c) { c.checked = gb.checked; });
      refreshBulk();
    });
  });
  var clear = $('[data-tr-clear]');
  if (clear) clear.addEventListener('click', function () { $$('[data-tr-select]').forEach(function (c) { c.checked = false; }); refreshBulk(); });
  function selectedKeys() { return selectedRows().map(function (r) { return r.getAttribute('data-key'); }); }
  $$('[data-tr-bulk-act]').forEach(function (b) {
    b.addEventListener('click', function () {
      var extra = {};
      if (b.hasAttribute('data-days')) extra.days = b.getAttribute('data-days');
      if (b.hasAttribute('data-assignee')) extra.assignee = b.getAttribute('data-assignee');
      act(b.getAttribute('data-tr-bulk-act'), selectedKeys(), extra);
    });
  });
  var assignee = $('[data-tr-assignee]');
  if (assignee) assignee.addEventListener('change', function () { if (assignee.value) act('assign', selectedKeys(), { assignee: assignee.value }); });
  var bulkOpen = $('[data-tr-bulk-open]');
  if (bulkOpen) bulkOpen.addEventListener('click', function () {
    selectedRows().slice(0, OPEN_MAX).forEach(function (r) { window.open(r.getAttribute('data-open'), '_blank', 'noopener'); });
  });

  // ---- Actions d'une ligne ou d'un groupe.
  $$('[data-tr-act]').forEach(function (b) {
    b.addEventListener('click', function () {
      var extra = {};
      if (b.hasAttribute('data-days')) extra.days = b.getAttribute('data-days');
      if (b.hasAttribute('data-assignee')) extra.assignee = b.getAttribute('data-assignee');
      act(b.getAttribute('data-tr-act'), b.getAttribute('data-keys').split(','), extra);
    });
  });
  var confirmDialog = document.getElementById('tr-confirm');
  $$('[data-tr-group-ack]').forEach(function (b) {
    b.addEventListener('click', function () {
      var g = b.closest('[data-tr-group]');
      var keys = g.getAttribute('data-keys').split(',');
      if (!confirmDialog || !confirmDialog.showModal) { act('acknowledge', keys); return; }
      $('[data-tr-confirm-text]', confirmDialog).textContent = msg('confirmText', g.getAttribute('data-count'), g.getAttribute('data-title'));
      confirmDialog.returnValue = '';
      confirmDialog.onclose = function () { confirmDialog.onclose = null; if (confirmDialog.returnValue === 'ok') act('acknowledge', keys); };
      confirmDialog.showModal();
    });
  });

  // ---- Raccourcis clavier (désactivables).
  var helpDialog = document.getElementById('tr-help');
  var keysToggle = $('[data-tr-keys-toggle]');
  function keysOn() { return store('local', STORE_KEYS) !== 'off'; }
  if (keysToggle) {
    keysToggle.checked = !keysOn();
    keysToggle.addEventListener('change', function () { store('local', STORE_KEYS, keysToggle.checked ? 'off' : null); toast(keysToggle.checked ? msg('keysOff') : msg('keysOn')); });
  }
  var focusIdx = -1;
  function visibleRows() { return $$('[data-tr-row]').filter(function (r) { return !r.closest('[hidden]'); }); }
  function focusRow(delta) {
    var rows = visibleRows();
    if (!rows.length) return;
    focusIdx = Math.max(0, Math.min(rows.length - 1, focusIdx + delta));
    rows.forEach(function (r, i) { r.classList.toggle('is-focus', i === focusIdx); });
    rows[focusIdx].scrollIntoView({ block: 'nearest' });
  }
  function currentKeys() {
    var sel = selectedKeys();
    if (sel.length) return sel;
    var row = visibleRows()[focusIdx];
    return row ? [row.getAttribute('data-key')] : [];
  }
  document.addEventListener('keydown', function (e) {
    if (!keysOn() || e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
    var t = e.target;
    var tag = t && t.tagName ? t.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || (t && t.isContentEditable)) return;
    if (document.querySelector('dialog[open]')) return;
    var k = e.key;
    if (k === '?') { if (helpDialog && helpDialog.showModal) { e.preventDefault(); helpDialog.showModal(); } return; }
    if (k === 'j') { e.preventDefault(); focusRow(1); } else if (k === 'k') { e.preventDefault(); focusRow(-1); }
    else if (k === 'Enter' && focusIdx >= 0 && tag !== 'a' && tag !== 'button' && tag !== 'summary') {
      var row = visibleRows()[focusIdx];
      if (row) { e.preventDefault(); window.location.href = row.getAttribute('data-open'); }
    } else if (k === 'x' && focusIdx >= 0) {
      var r = visibleRows()[focusIdx];
      var c = r && $('[data-tr-select]', r);
      if (c) { c.checked = !c.checked; refreshBulk(); }
    } else if (k === 'e' && CFG.treat) { var keys = currentKeys(); if (keys.length) { e.preventDefault(); act('acknowledge', keys); } }
    else if (k === 's' && CFG.treat) { var ks = currentKeys(); if (ks.length) { e.preventDefault(); act('snooze', ks, { days: 7 }); } }
  });

  // ---- Règles Cerbère-Tri.
  $$('[data-tr-tab]').forEach(function (t) {
    t.addEventListener('click', function () {
      $$('[data-tr-tab]').forEach(function (o) { var on = o === t; o.setAttribute('aria-selected', on ? 'true' : 'false'); o.tabIndex = on ? 0 : -1; });
      $$('[data-tr-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-tr-pane') !== t.getAttribute('data-tr-tab'); });
    });
  });
  $$('[data-tr-rule-toggle]').forEach(function (sw) {
    sw.addEventListener('click', function () {
      var li = sw.closest('[data-tr-rule]');
      var want = sw.getAttribute('aria-checked') !== 'true';
      var data = li.getAttribute('data-preset') ? { op: 'preset', preset: li.getAttribute('data-preset'), enabled: want ? '1' : '0' } : { op: 'toggle', id: li.getAttribute('data-id'), enabled: want ? '1' : '0' };
      sw.disabled = true;
      post(CFG.rules, data).then(function (res) {
        sw.disabled = false;
        if (!res.ok) { toast(res.error || msg('failed')); return; }
        li.setAttribute('data-id', String(res.id));
        sw.setAttribute('aria-checked', res.enabled ? 'true' : 'false');
        $('.tr-switch__text', sw).textContent = res.enabled ? msg('ruleOn') : msg('ruleOff');
        needsReload = true;
      });
    });
  });
  $$('[data-tr-undo-event]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.disabled = true;
      post(CFG.undo, { event: b.getAttribute('data-tr-undo-event') }).then(function (res) {
        if (res.ok) { b.hidden = true; needsReload = true; toast(msg('undone')); } else { b.disabled = false; toast(res.error || msg('failed')); }
      });
    });
  });
  var form = $('[data-tr-rule-form]');
  if (form) {
    var actionSel = $('[data-tr-rule-action]', form);
    function valueInput() { var w = $('[data-tr-value-' + actionSel.value + ']', form); return w ? $('[data-tr-value]', w) : null; }
    function syncValue() {
      ['snooze', 'priority', 'assign'].forEach(function (n) { var w = $('[data-tr-value-' + n + ']', form); if (w) w.hidden = n !== actionSel.value; });
    }
    actionSel.addEventListener('change', syncValue);
    syncValue();
    function fields() {
      var o = {};
      ['source_key', 'type_key', 'keyword', 'min_age_days', 'min_severity', 'max_severity', 'scope'].forEach(function (n) { o[n] = form.elements[n].value; });
      return o;
    }
    var previewOut = $('[data-tr-preview]', form);
    $('[data-tr-preview-btn]', form).addEventListener('click', function () {
      var q = new URLSearchParams(fields());
      fetch(CFG.preview + '?' + q.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          previewOut.textContent = !res.ok ? (res.error || msg('failed')) : (res.count === 0 ? msg('previewNone') : (res.count === 1 ? msg('previewOne') : msg('previewN', res.count)));
        })
        .catch(function () { previewOut.textContent = msg('failed'); });
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = fields();
      data.op = 'custom';
      data.name = form.elements.name.value;
      data.rule_action = actionSel.value;
      var v = valueInput();
      data.action_value = v ? v.value : '0';
      data.enabled = '0';
      post(CFG.rules, data).then(function (res) {
        if (res.ok) { window.location.reload(); } else { toast(res.error || msg('failed')); }
      });
    });
  }
})();
