/*
 * « Effacer la carte » : fenêtre commune à la carte web (Overwatch) et à la carte du téléphone (/m/carte).
 * Deux temps : choix de la portée et des types (avec les nombres exacts), puis confirmation en tapant SUPPRIMER.
 * Après l'effacement, « Annuler la suppression » reste proposé 10 minutes (corbeille côté serveur).
 *
 * AtakMapClear.open({ call(path, payload) -> Promise<{ok, ...}>, previewPath, undoPath, onChange() })
 * Le serveur reste seul juge des droits : « Tout » est grisé sans le droit de gestion de la carte, et refusé s'il est forcé.
 */
(function () {
  'use strict';
  var PHRASE = 'SUPPRIMER';
  var KINDS = [['marker', 'Marqueurs'], ['form', 'Formes'], ['route', 'Routes'], ['zone', 'Zones']];
  var undoTimer = null;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  function injectStyle() {
    if (document.getElementById('mc-style')) return;
    var s = el('style');
    s.id = 'mc-style';
    s.textContent =
      '.mc-back{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;padding:12px}' +
      '.mc-box{width:100%;max-width:440px;max-height:92vh;overflow:auto;background:#0b0f0e;color:#f4f4f0;border:1px solid rgba(244,244,240,.2);border-top:3px solid #f87171;border-radius:8px;padding:16px;display:flex;flex-direction:column;gap:12px;font:15px/1.4 system-ui,sans-serif}' +
      '.mc-box h2{margin:0;font-size:18px}.mc-box p{margin:0}.mc-muted{color:rgba(244,244,240,.65);font-size:13px}' +
      '.mc-row{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:44px;padding:6px 10px;border:1px solid rgba(244,244,240,.14);border-radius:6px}' +
      '.mc-row label{display:flex;align-items:center;gap:10px;flex:1;min-height:44px}.mc-row input[type=checkbox],.mc-row input[type=radio]{width:22px;height:22px}' +
      '.mc-row b{font-variant-numeric:tabular-nums}.mc-list{display:flex;flex-direction:column;gap:6px}' +
      '.mc-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}' +
      '.mc-btn{min-height:44px;padding:8px 16px;border-radius:6px;border:1px solid rgba(244,244,240,.3);background:#18201d;color:#f4f4f0;font:inherit;font-weight:600;cursor:pointer}' +
      '.mc-btn[disabled]{opacity:.4;cursor:not-allowed}.mc-danger{background:#f87171;border-color:#f87171;color:#2a0a0a}' +
      '.mc-input{min-height:44px;width:100%;padding:8px 10px;border-radius:6px;border:1px solid rgba(244,244,240,.3);background:#050505;color:#f4f4f0;font:inherit;letter-spacing:.08em;text-transform:uppercase}' +
      '.mc-warn{color:#fbbf24}.mc-undo{position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom));z-index:10001;max-width:440px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:10px;background:#0b0f0e;color:#f4f4f0;border:1px solid #fbbf24;border-radius:8px;padding:8px 12px;font:15px system-ui,sans-serif}';
    document.head.appendChild(s);
  }

  function open(opts) {
    injectStyle();
    var state = { counts: {}, mine: {}, canAll: false, scope: 'mine', types: { marker: true, form: true, route: true, zone: true } };
    var back = el('div', 'mc-back');
    back.setAttribute('role', 'dialog');
    back.setAttribute('aria-modal', 'true');
    var box = el('div', 'mc-box');
    back.appendChild(box);
    document.body.appendChild(back);
    function close() { if (back.parentNode) back.parentNode.removeChild(back); document.removeEventListener('keydown', onKey); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', onKey);
    back.addEventListener('click', function (e) { if (e.target === back) close(); });

    function selected() {
      var src = state.scope === 'all' ? state.counts : state.mine;
      var out = {};
      var total = 0;
      KINDS.forEach(function (k) {
        var n = state.types[k[0]] ? Number(src[k[0]] || 0) : 0;
        out[k[0]] = n;
        total += n;
      });
      return { by: out, total: total };
    }
    function button(label, cls, fn) {
      var b = el('button', 'mc-btn' + (cls ? ' ' + cls : ''), label);
      b.type = 'button';
      b.addEventListener('click', fn);
      return b;
    }

    function stepChoose() {
      box.textContent = '';
      box.appendChild(el('h2', '', 'Effacer la carte'));
      box.appendChild(el('p', 'mc-muted', 'Seuls les marqueurs, formes, routes et zones de cette carte sont concernés. Effectifs, comptes et documents ne sont jamais touchés.'));
      var scopeList = el('div', 'mc-list');
      [['all', 'Tout ce qui est sur la carte'], ['mine', 'Seulement mes éléments']].forEach(function (s) {
        var row = el('div', 'mc-row');
        var lab = el('label');
        var r = el('input');
        r.type = 'radio';
        r.name = 'mc-scope';
        r.checked = state.scope === s[0];
        r.disabled = s[0] === 'all' && !state.canAll;
        r.addEventListener('change', function () { state.scope = s[0]; stepChoose(); });
        lab.appendChild(r);
        lab.appendChild(el('span', '', s[1]));
        row.appendChild(lab);
        scopeList.appendChild(row);
      });
      box.appendChild(scopeList);
      if (!state.canAll) box.appendChild(el('p', 'mc-muted', 'Effacer les éléments des autres est réservé à la gestion de la carte.'));
      var src = state.scope === 'all' ? state.counts : state.mine;
      var typeList = el('div', 'mc-list');
      KINDS.forEach(function (k) {
        var row = el('div', 'mc-row');
        var lab = el('label');
        var c = el('input');
        c.type = 'checkbox';
        c.checked = !!state.types[k[0]];
        c.addEventListener('change', function () { state.types[k[0]] = c.checked; stepChoose(); });
        lab.appendChild(c);
        lab.appendChild(el('span', '', k[1]));
        row.appendChild(lab);
        row.appendChild(el('b', '', String(Number(src[k[0]] || 0))));
        typeList.appendChild(row);
      });
      box.appendChild(typeList);
      var sel = selected();
      var tot = el('p');
      tot.appendChild(el('span', '', 'Éléments à supprimer : '));
      tot.appendChild(el('b', '', String(sel.total)));
      box.appendChild(tot);
      var actions = el('div', 'mc-actions');
      actions.appendChild(button('Annuler', '', close));
      var next = button('Continuer', 'mc-danger', stepConfirm);
      next.disabled = sel.total < 1;
      actions.appendChild(next);
      box.appendChild(actions);
    }

    function stepConfirm() {
      var sel = selected();
      box.textContent = '';
      box.appendChild(el('h2', 'mc-warn', 'Dernière vérification'));
      box.appendChild(el('p', '', 'Vous allez supprimer :'));
      var list = el('div', 'mc-list');
      KINDS.forEach(function (k) {
        if (!sel.by[k[0]]) return;
        var row = el('div', 'mc-row');
        row.appendChild(el('span', '', k[1]));
        row.appendChild(el('b', '', String(sel.by[k[0]])));
        list.appendChild(row);
      });
      box.appendChild(list);
      box.appendChild(el('p', '', state.scope === 'all' ? 'Portée : toute la carte, y compris les éléments des autres.' : 'Portée : seulement mes éléments.'));
      box.appendChild(el('p', 'mc-muted', 'Les marqueurs du jeu disparaissent aussi de la carte d’Arma en une minute environ. Vous pourrez annuler pendant 10 minutes.'));
      var lab = el('label', 'mc-muted', 'Tapez SUPPRIMER pour confirmer');
      var input = el('input', 'mc-input');
      input.type = 'text';
      input.autocomplete = 'off';
      input.autocapitalize = 'characters';
      lab.appendChild(input);
      box.appendChild(lab);
      var msg = el('p', 'mc-warn');
      box.appendChild(msg);
      var actions = el('div', 'mc-actions');
      actions.appendChild(button('Annuler', '', close));
      actions.appendChild(button('Retour', '', stepChoose));
      var go = button('Tout supprimer', 'mc-danger', function () {
        go.disabled = true;
        var types = KINDS.map(function (k) { return k[0]; }).filter(function (k) { return state.types[k]; });
        opts.call(opts.previewPath, { mode: 'clear', scope: state.scope, types: types, confirm: input.value.trim() }).then(function (j) {
          if (!j || !j.ok) {
            msg.textContent = j && j.error === 'forbidden' ? 'Vous n’avez pas le droit d’effacer les éléments des autres.'
              : (j && j.error === 'csrf' ? 'Page expirée : rechargez-la.' : 'Suppression impossible, réessayez.');
            go.disabled = input.value.trim().toUpperCase() !== PHRASE;
            return;
          }
          close();
          if (opts.onChange) opts.onChange();
          showUndo(opts, j);
        });
      });
      go.disabled = true;
      input.addEventListener('input', function () { go.disabled = input.value.trim().toUpperCase() !== PHRASE; });
      actions.appendChild(go);
      box.appendChild(actions);
      input.focus();
    }

    box.appendChild(el('p', 'mc-muted', 'Chargement…'));
    opts.call(opts.previewPath, { mode: 'preview' }).then(function (j) {
      if (!j || !j.ok) {
        box.textContent = '';
        box.appendChild(el('p', 'mc-warn', 'Impossible de lire la carte pour le moment.'));
        box.appendChild(button('Fermer', '', close));
        return;
      }
      state.counts = j.counts || {};
      state.mine = j.mine || {};
      state.canAll = !!j.canAll;
      state.scope = state.canAll ? 'all' : 'mine';
      stepChoose();
    });
  }

  function showUndo(opts, result) {
    var old = document.querySelector('.mc-undo');
    if (old) old.parentNode.removeChild(old);
    clearTimeout(undoTimer);
    var bar = el('div', 'mc-undo');
    var total = Number(result.total || 0);
    var txt = el('span');
    txt.appendChild(el('b', '', String(total)));
    txt.appendChild(el('span', '', ' éléments supprimés.'));
    bar.appendChild(txt);
    if (result.batch && total > 0) {
      var b = el('button', 'mc-btn', 'Annuler la suppression');
      b.type = 'button';
      b.addEventListener('click', function () {
        b.disabled = true;
        opts.call(opts.undoPath, { batch: result.batch }).then(function (j) {
          bar.textContent = '';
          bar.appendChild(el('span', '', j && j.ok ? 'Suppression annulée.' : 'Annulation impossible : le délai est passé.'));
          if (j && j.ok && opts.onChange) opts.onChange();
          undoTimer = setTimeout(function () { if (bar.parentNode) bar.parentNode.removeChild(bar); }, 4000);
        });
      });
      bar.appendChild(b);
    }
    var x = el('button', 'mc-btn', 'Fermer');
    x.type = 'button';
    x.addEventListener('click', function () { if (bar.parentNode) bar.parentNode.removeChild(bar); });
    bar.appendChild(x);
    document.body.appendChild(bar);
    undoTimer = setTimeout(function () { if (bar.parentNode) bar.parentNode.removeChild(bar); }, 600000);
  }

  window.AtakMapClear = { open: open };
})();
