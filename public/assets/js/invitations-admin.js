/**
 * Back-office › Invitations (views/admin/organization/invitations.php).
 * - Tableau : recherche, filtre d'état, tri par colonne ; état dans l'adresse (?status=, ?q=, ?tri=).
 * - « Copier le lien » : nouveau lien demandé par fetch (Accept: application/json) puis copié ; boutons « Copier » ;
 *   confirmation dans le message bref .iath-toast (invitations-ath.css).
 * - Confirmation des formulaires data-confirm (renvoyer, révoquer).
 * - Assistant « Inviter des membres » en trois étapes (destinataires, accès et arrivée, message et envoi).
 * Les textes viennent du <template data-inv-texts> de la vue (traduits côté serveur et par i18n_dom.js).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-inv]');
  if (!root) return;

  var texts = {};
  var tpl = root.querySelector('template[data-inv-texts]');
  if (tpl) {
    Array.prototype.forEach.call(tpl.content.querySelectorAll('[data-t]'), function (el) {
      texts[el.getAttribute('data-t')] = el.textContent.trim();
    });
  }
  function t(key, arg) {
    var s = texts[key] || '';
    return arg !== undefined ? s.replace('%1', String(arg)) : s;
  }
  function all(r, sel) { return Array.prototype.slice.call(r.querySelectorAll(sel)); }
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }

  function message(kind, text) {
    var box = root.querySelector('[data-inv-msg]');
    if (!box || !text) return;
    box.innerHTML = '';
    var p = document.createElement('p');
    p.className = 'effk-note ' + (kind === 'ok' ? 'is-info' : 'is-warn');
    p.setAttribute('role', kind === 'ok' ? 'status' : 'alert');
    p.textContent = text;
    box.appendChild(p);
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  var toastEl = root.querySelector('[data-inv-toast]');
  var toastTimer = null;
  function toast(text, bad) {
    if (!toastEl || !text) { message(bad ? 'err' : 'ok', text); return; }
    toastEl.textContent = text;
    toastEl.classList.toggle('is-bad', !!bad);
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2600);
  }

  function copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(ta);
      if (ok) resolve(); else reject(new Error('copy'));
    });
  }

  // ——— Tableau : filtres, tri, adresse ———
  var tbody = root.querySelector('[data-inv-rows]');
  var rows = tbody ? all(tbody, 'tr[data-inv-row]') : [];
  var fq = root.querySelector('[data-inv-f="q"]');
  var fs = root.querySelector('[data-inv-f="status"]');
  var countEl = root.querySelector('[data-inv-count]');
  var none = root.querySelector('[data-inv-none]');
  var resets = all(root, '[data-inv-reset]');
  var sortKey = 'created';
  var sortDesc = true;
  var statusOrder = { pending: 0, expired: 1, accepted: 2, revoked: 3 };

  function readUrl() {
    var p = new URLSearchParams(location.search);
    if (fq && p.get('q')) fq.value = p.get('q');
    if (fs && p.has('status')) fs.value = p.get('status') || '';
    var tri = p.get('tri') || '';
    if (tri) {
      sortDesc = tri.charAt(0) === '-';
      sortKey = tri.replace(/^-/, '');
    }
  }

  function writeUrl() {
    if (!history.replaceState) return;
    var p = new URLSearchParams(location.search);
    function set(k, v) { if (v) p.set(k, v); else p.delete(k); }
    set('q', fq ? fq.value.trim() : '');
    set('status', fs ? fs.value : '');
    set('tri', sortKey === 'created' && sortDesc ? '' : (sortDesc ? '-' : '') + sortKey);
    var qs = p.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
  }

  function apply() {
    if (!tbody) return;
    var q = norm(fq ? fq.value.trim() : '');
    var st = fs ? fs.value : '';
    var shown = 0;
    rows.forEach(function (tr) {
      var ok = (!st || tr.getAttribute('data-status') === st) && (!q || norm(tr.getAttribute('data-search')).indexOf(q) !== -1);
      tr.hidden = !ok;
      if (ok) shown++;
    });
    if (countEl) countEl.textContent = String(shown);
    if (none) none.hidden = shown !== 0;
    var filtered = !!(q || st);
    resets.forEach(function (b) { if (b.closest('[data-inv-none]') === null) b.hidden = !filtered; });
    if (fs) fs.classList.toggle('is-set', !!st);
    writeUrl();
  }

  function sort() {
    if (!tbody) return;
    var sorted = rows.slice().sort(function (a, b) {
      var va, vb;
      if (sortKey === 'status') {
        va = statusOrder[a.getAttribute('data-status')] || 9;
        vb = statusOrder[b.getAttribute('data-status')] || 9;
      } else {
        va = a.getAttribute('data-' + sortKey) || '';
        vb = b.getAttribute('data-' + sortKey) || '';
      }
      var c = va < vb ? -1 : (va > vb ? 1 : 0);
      return sortDesc ? -c : c;
    });
    sorted.forEach(function (tr) { tbody.appendChild(tr); });
    all(root, '[data-inv-sort]').forEach(function (btn) {
      var th = btn.closest('th');
      if (!th) return;
      if (btn.getAttribute('data-inv-sort') === sortKey) th.setAttribute('aria-sort', sortDesc ? 'descending' : 'ascending');
      else th.removeAttribute('aria-sort');
    });
  }

  if (tbody) {
    readUrl();
    sort();
    apply();
    if (fq) fq.addEventListener('input', apply);
    if (fs) fs.addEventListener('change', apply);
    resets.forEach(function (b) {
      b.addEventListener('click', function () {
        if (fq) fq.value = '';
        if (fs) fs.value = '';
        apply();
      });
    });
    all(root, '[data-inv-sort]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-inv-sort');
        if (key === sortKey) sortDesc = !sortDesc;
        else { sortKey = key; sortDesc = btn.hasAttribute('data-inv-desc'); }
        sort();
        writeUrl();
      });
    });
  }

  // ——— Copier, confirmer, nouveau lien ———
  root.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-inv-copy]');
    if (btn) {
      copy(btn.getAttribute('data-inv-copy')).then(function () { toast(t('copied')); }, function () { toast(t('copy-fail'), true); });
      return;
    }
    var sel = ev.target.closest('[data-inv-select]');
    if (sel) sel.select();
  });

  root.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form.hasAttribute('data-inv-link')) {
      ev.preventDefault();
      if (!window.confirm(t('link-confirm'))) return;
      var button = form.querySelector('button');
      if (button) button.disabled = true;
      fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (data) {
          if (!data || !data.ok || !data.url) { toast((data && data.message) || t('error'), true); return; }
          return copy(data.url).then(function () { toast(data.message || t('copied')); }, function () {
            message('ok', data.message || '');
            window.prompt(t('copy-fail'), data.url);
          });
        })
        .catch(function () { toast(t('error'), true); })
        .then(function () { if (button) button.disabled = false; });
      return;
    }
    var confirmText = form.getAttribute('data-confirm');
    if (confirmText && !window.confirm(confirmText)) ev.preventDefault();
  });

  // ——— Assistant ———
  var dialog = document.getElementById('inviter');
  var wiz = dialog ? dialog.querySelector('[data-inv-wiz]') : null;

  function openWizard() {
    if (!dialog) return;
    if (!dialog.open) {
      if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
      show(1);
    }
    if (history.replaceState) history.replaceState(null, '', location.pathname + location.search + '#inviter');
  }
  function closeWizard() {
    if (!dialog) return;
    if (typeof dialog.close === 'function' && dialog.open) dialog.close(); else dialog.removeAttribute('open');
  }
  document.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-inv-open]')) { ev.preventDefault(); openWizard(); return; }
    if (ev.target.closest('[data-inv-close]')) closeWizard();
  });
  if (dialog) {
    dialog.addEventListener('close', function () {
      if (location.hash === '#inviter' && history.replaceState) history.replaceState(null, '', location.pathname + location.search);
    });
    if (location.hash === '#inviter') openWizard();
  }
  if (!wiz) return;

  var current = 1;
  var steps = all(wiz, '[data-inv-step]');
  var items = all(wiz, '.ds-stepper__item');
  var prev = wiz.querySelector('[data-inv-prev]');
  var next = wiz.querySelector('[data-inv-next]');
  var submit = wiz.querySelector('[data-inv-submit]');
  var where = wiz.querySelector('[data-inv-where]');
  var emailsEl = wiz.querySelector('[data-inv-emails]');
  var chips = wiz.querySelector('[data-inv-chips]');
  var roleEl = wiz.querySelector('[data-inv-role]');
  var roleQ = wiz.querySelector('[data-inv-role-q]');
  var roleHint = wiz.querySelector('[data-inv-role-hint]');
  var roleHintDefault = roleHint ? roleHint.textContent : '';
  var max = parseInt(wiz.getAttribute('data-max') || '50', 10);
  var remainingAttr = wiz.getAttribute('data-remaining');
  var remaining = remainingAttr === '' || remainingAttr === null ? null : parseInt(remainingAttr, 10);
  var EMAIL = /^[^\s@<>"',;]+@[^\s@<>"',;]+\.[^\s@<>"',;]{2,}$/;

  function parse() {
    var valid = [];
    var invalid = [];
    String(emailsEl ? emailsEl.value : '').split(/[\s,;]+/).forEach(function (raw) {
      var e = raw.replace(/^[<"']+|[>"']+$/g, '').trim().toLowerCase();
      if (!e) return;
      if (!EMAIL.test(e)) invalid.push(e);
      else if (valid.indexOf(e) === -1) valid.push(e);
    });
    return { valid: valid, invalid: invalid };
  }

  function renderChips() {
    if (!chips) return;
    var p = parse();
    chips.innerHTML = '';
    if (!p.valid.length && !p.invalid.length) return;
    var ok = document.createElement('span');
    ok.className = 'is-ok';
    ok.textContent = p.valid.length + ' ' + t('valid');
    chips.appendChild(ok);
    if (p.invalid.length) {
      var bad = document.createElement('span');
      bad.className = 'is-bad';
      bad.textContent = p.invalid.length + ' ' + t('invalid');
      chips.appendChild(bad);
      p.invalid.slice(0, 6).forEach(function (e) {
        var c = document.createElement('code');
        c.textContent = e;
        c.setAttribute('translate', 'no');
        chips.appendChild(c);
      });
    }
  }

  function err(step, text) {
    var el = wiz.querySelector('[data-inv-err="' + step + '"]');
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
  }

  function validate(step) {
    if (step === 1) {
      var p = parse();
      var msg = '';
      if (!p.valid.length) msg = t('need-email');
      else if (p.invalid.length) msg = t('fix-invalid');
      else if (p.valid.length > max) msg = t('too-many');
      else if (remaining !== null && p.valid.length > remaining) msg = t('over-cap');
      err(1, msg);
      if (msg && emailsEl) emailsEl.focus();
      return !msg;
    }
    if (step === 2) {
      var ok = roleEl && roleEl.value !== '';
      var el = wiz.querySelector('[data-inv-err="2"]');
      if (el) el.hidden = ok;
      if (!ok && roleEl) roleEl.focus();
      return ok;
    }
    return true;
  }

  function selectedText(name) {
    var el = wiz.querySelector('[name="' + name + '"]');
    if (!el || el.tagName !== 'SELECT') return '';
    var o = el.options[el.selectedIndex];
    return o ? o.textContent.trim() : '';
  }

  function summary() {
    var p = parse();
    function set(k, v) { var dd = wiz.querySelector('[data-sum="' + k + '"]'); if (dd) dd.textContent = v || t('none'); }
    set('emails', p.valid.join(', '));
    set('role', selectedText('role_id'));
    set('grade', selectedText('grade_id'));
    var unit = wiz.querySelector('[name="unit_id"]');
    var assign = wiz.querySelector('[name="assignment_label"]');
    set('unit', unit && unit.value !== '0' ? selectedText('unit_id') + (assign && assign.value.trim() ? ' — ' + assign.value.trim() : '') : '');
    set('expiry', selectedText('expiry_days'));
  }

  function show(n) {
    current = n;
    steps.forEach(function (s) { s.hidden = parseInt(s.getAttribute('data-inv-step'), 10) !== n; });
    items.forEach(function (li, i) {
      li.classList.toggle('ds-stepper__item--active', i + 1 === n);
      li.classList.toggle('ds-stepper__item--done', i + 1 < n);
      if (i + 1 === n) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
      var num = li.querySelector('.ds-stepper__num');
      if (num) num.textContent = i + 1 < n ? '✓' : String(i + 1);
    });
    if (prev) prev.hidden = n === 1;
    if (next) next.hidden = n === steps.length;
    if (submit) submit.hidden = n !== steps.length;
    if (where) where.textContent = t('step', n);
    if (n === steps.length) summary();
    var first = steps[n - 1] ? steps[n - 1].querySelector('textarea, select, input:not([type=hidden])') : null;
    if (first) first.focus({ preventScroll: true });
  }

  if (next) next.addEventListener('click', function () { if (validate(current)) show(current + 1); });
  if (prev) prev.addEventListener('click', function () { show(Math.max(1, current - 1)); });
  if (emailsEl) emailsEl.addEventListener('input', function () { renderChips(); err(1, ''); });
  wiz.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && ev.target.tagName !== 'TEXTAREA' && current < steps.length) {
      ev.preventDefault();
      if (validate(current)) show(current + 1);
    }
  });
  wiz.addEventListener('submit', function (ev) {
    if (!validate(1)) { ev.preventDefault(); show(1); validate(1); return; }
    if (!validate(2)) { ev.preventDefault(); show(2); validate(2); return; }
    if (submit) submit.disabled = true;
  });

  if (roleEl) {
    roleEl.addEventListener('change', function () {
      var o = roleEl.options[roleEl.selectedIndex];
      var hint = o ? (o.getAttribute('data-hint') || '') : '';
      if (roleHint) roleHint.textContent = hint || roleHintDefault;
      var el = wiz.querySelector('[data-inv-err="2"]');
      if (el && roleEl.value) el.hidden = true;
    });
  }
  if (roleQ && roleEl) {
    roleQ.addEventListener('input', function () {
      var q = norm(roleQ.value.trim());
      all(roleEl, 'optgroup').forEach(function (g) {
        var n = 0;
        all(g, 'option').forEach(function (o) {
          var ok = !q || norm(o.getAttribute('data-search') || o.textContent).indexOf(q) !== -1;
          o.hidden = !ok;
          o.disabled = !ok;
          if (ok) n++;
        });
        g.hidden = n === 0;
      });
      var sel = roleEl.options[roleEl.selectedIndex];
      if (sel && sel.disabled) roleEl.value = '';
    });
  }
})();
