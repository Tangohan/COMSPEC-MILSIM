/*
 * Cycle de 24 h des marqueurs et « Missions archivées » (poste TOC web, ATAK web / Overwatch, TACMAP).
 *
 * Un module pour les trois postes : le balisage vient de views/partials/atak_marker_cycle.php. Il affiche l'indicateur
 * « Cycle en cours : jusqu'à 12:00 (dans 5 h 12) », la liste des missions archivées de la communauté (recherche par date),
 * la consultation d'une mission en lecture seule (carte schématique et liste), l'export, la restauration vers le cycle
 * en cours, la purge (gestion) et les réglages (gestion). La communauté est celle de la session : le script n'envoie jamais
 * d'identifiant de communauté. Données lues en texte (textContent), jamais injectées en HTML. Aucune requête quand
 * l'onglet est masqué.
 *
 * API : window.AtakMarkerCycle.pin(kind, id, pinned) -> Promise<boolean> (épingler ou désépingler un marqueur ou un tracé).
 */
(function (global) {
  'use strict';

  var COLORS = { colorred: '#e5534b', colorblue: '#4c9aff', colorgreen: '#4cc38a', coloryellow: '#f0c93a', colorblack: '#6b7780', colorwhite: '#eef3f6', colororange: '#f08a3a', colorpink: '#e879b9', colorbrown: '#a47a54', colorgrey: '#9fb0bb' };
  var STATUS_EVERY = 60000;
  var TICK_EVERY = 20000;
  var instances = [];

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function clear(n) { while (n.firstChild) n.removeChild(n.firstChild); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  /* « 5 h 12 », « 42 min », « moins d'une minute » */
  function remaining(sec) {
    var m = Math.max(0, Math.floor(sec / 60));
    if (m < 1) return 'moins d’une minute';
    if (m < 60) return m + ' min';
    var h = Math.floor(m / 60);
    if (h >= 48) return Math.floor(h / 24) + ' j';
    return h + ' h ' + pad(m % 60);
  }

  function colorOf(c) {
    c = String(c || '');
    if (c.charAt(0) === '#') return c;
    return COLORS[c.toLowerCase()] || '#9fb0bb';
  }

  function collect(coords, out) {
    if (!Array.isArray(coords)) return;
    if (coords.length >= 2 && typeof coords[0] === 'number' && typeof coords[1] === 'number') { out.push([coords[0], coords[1]]); return; }
    for (var i = 0; i < coords.length; i++) collect(coords[i], out);
  }

  function init(root) {
    var base = (root.getAttribute('data-base') || '').replace(/\/+$/, '');
    var csrf = root.getAttribute('data-csrf') || '';
    var selector = root.getAttribute('data-map-selector') || '';
    var defMap = parseInt(root.getAttribute('data-map'), 10) || 1;
    var chip = root.querySelector('[data-mcy-open]');
    var chipText = root.querySelector('[data-mcy-chip]');
    var dlg = root.querySelector('[data-mcy-dialog]');
    var viewList = root.querySelector('[data-mcy-view="list"]');
    var viewDetail = root.querySelector('[data-mcy-view="detail"]');
    var listEl = root.querySelector('[data-mcy-list]');
    var msgEl = root.querySelector('[data-mcy-msg]');
    var curEl = root.querySelector('[data-mcy-current]');
    var settingsBox = root.querySelector('[data-mcy-settings]');
    var form = root.querySelector('[data-mcy-form]');
    var tzEl = root.querySelector('[data-mcy-tz]');
    var search = root.querySelector('[data-mcy-search]');
    var st = { cycle: null, canOperate: false, canManage: false, offset: 0, open: null };
    var timer = null;
    var tick = null;

    function mapId() {
      if (selector) {
        var s = document.querySelector(selector);
        var v = s ? parseInt(s.value, 10) : 0;
        if (v > 0) return v;
      }
      return defMap;
    }

    function api(path, opts) {
      opts = opts || {};
      var init = { method: opts.method || 'GET', credentials: 'same-origin', cache: 'no-cache', headers: { 'Accept': 'application/json' } };
      if (init.method !== 'GET') {
        init.headers['X-CSRF-Token'] = csrf;
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(opts.body || {});
      }
      return fetch(base + path, init).then(function (r) {
        return r.json().catch(function () { return { ok: false, error: 'http_' + r.status }; });
      }).catch(function () { return { ok: false, error: 'network' }; });
    }

    var ERRORS = {
      forbidden: 'Action réservée au poste TOC ou à la gestion de la communauté.',
      csrf: 'Session expirée : rechargez la page.',
      unauthenticated: 'Connectez-vous pour continuer.',
      not_found: 'Mission archivée introuvable.',
      confirm_required: 'Tapez SUPPRIMER pour confirmer.',
      network: 'Connexion impossible pour le moment.'
    };
    function say(text, isError) {
      msgEl.hidden = !text;
      msgEl.textContent = text || '';
      msgEl.className = 'mcy-msg' + (isError ? ' is-error' : '');
    }
    function sayError(j) { say(ERRORS[j && j.error] || 'Action impossible pour le moment.', true); }

    /* ---------- Indicateur ---------- */
    function paintChip() {
      var c = st.cycle;
      if (!c || !c.ready) { chip.hidden = true; return; }
      chip.hidden = false;
      var left = Math.max(0, c.end - (Math.floor(Date.now() / 1000) - st.offset));
      if (!c.enabled) {
        chipText.textContent = 'Missions archivées';
        chip.className = chip.className.replace(/\bis-(soon|off)\b/g, '').trim() + ' is-off';
        return;
      }
      chipText.textContent = 'Cycle en cours : jusqu’à ' + c.endTime + ' (dans ' + remaining(left) + ')';
      chip.className = chip.className.replace(/\bis-(soon|off)\b/g, '').trim() + (left < 1800 ? ' is-soon' : '');
      chip.title = c.label + ' · ' + c.timezone;
    }

    function loadStatus() {
      if (document.hidden) return Promise.resolve();
      return api('/api/atak/cycle/status').then(function (j) {
        if (!j || !j.ok) return;
        st.cycle = j.cycle;
        st.canOperate = !!j.canOperate;
        st.canManage = !!j.canManage;
        // Décalage horloge du navigateur : on compte à partir de l'heure du serveur reçue à l'instant.
        st.offset = Math.floor(Date.now() / 1000) - j.cycle.now;
        paintChip();
        if (dlg.open) paintCurrent();
      });
    }
    function paintChipTick() {
      var c = st.cycle;
      if (!c || !c.ready) return;
      var nowSrv = Math.floor(Date.now() / 1000) - st.offset;
      if (c.enabled && nowSrv >= c.end) { loadStatus(); return; }
      var left = Math.max(0, c.end - nowSrv);
      if (!c.enabled) return;
      chipText.textContent = 'Cycle en cours : jusqu’à ' + c.endTime + ' (dans ' + remaining(left) + ')';
      chip.className = chip.className.replace(/\bis-(soon|off)\b/g, '').trim() + (left < 1800 ? ' is-soon' : '');
    }

    function paintCurrent() {
      var c = st.cycle;
      if (!c) { curEl.textContent = ''; return; }
      curEl.textContent = c.enabled
        ? 'Cycle en cours : ' + c.startLocal + ' → ' + c.endLocal + ' (' + c.timezone + '). À la bascule, les marqueurs et tracés sont archivés ; les éléments épinglés restent.'
        : 'Le cycle est désactivé : tous les marqueurs restent affichés, rien n’est archivé.';
      settingsBox.hidden = !st.canManage;
      if (st.canManage && form) {
        var s = c.settings || {};
        form.elements.enabled.checked = !!s.enabled;
        form.elements.switch_time.value = s.switch_time || '12:00';
        form.elements.duration_days.value = String(s.duration_days || 1);
        form.elements.retention_days.value = String(s.retention_days === 0 ? 0 : (s.retention_days || 1095));
        tzEl.textContent = 'Fuseau : ' + c.timezone + ' (celui de la communauté).';
      }
    }

    /* ---------- Liste ---------- */
    function missionLabel(a) { return 'Mission du ' + a.startLocal + ' → ' + a.endLocal; }

    function postAction(path, body) { return api(path, { method: 'POST', body: body || {} }); }

    function renderRow(a) {
      var li = el('li', 'mcy-row');
      var t = el('div', 'mcy-row-title', a.legacy ? a.label : missionLabel(a));
      if (a.legacy) t.appendChild(el('span', 'mcy-badge', 'Reprise de l’existant'));
      li.appendChild(t);
      li.appendChild(el('div', 'mcy-row-meta', a.markers + ' marqueur(s) · ' + a.shapes + ' tracé(s)'));
      var acts = el('div', 'mcy-row-actions');
      var see = el('button', 'mcy-btn', 'Consulter');
      see.type = 'button';
      see.addEventListener('click', function () { openArchive(a.id); });
      acts.appendChild(see);
      var exp = el('a', 'mcy-btn', 'Exporter');
      exp.href = base + '/api/atak/cycle/archives/' + a.id + '/export.json';
      exp.setAttribute('download', 'mission-archive-' + a.id + '.json');
      acts.appendChild(exp);
      if (st.canOperate) acts.appendChild(restoreButton(a.id));
      if (st.canManage) acts.appendChild(deleteButton(a.id, li));
      li.appendChild(acts);
      return li;
    }

    function restoreButton(id) {
      var b = el('button', 'mcy-btn', 'Restaurer dans le cycle en cours');
      b.type = 'button';
      b.addEventListener('click', function () {
        b.disabled = true;
        postAction('/api/atak/cycle/archives/' + id + '/restore').then(function (j) {
          b.disabled = false;
          if (j && j.ok) say(j.restored + ' élément(s) remis sur la carte du cycle en cours.' + (j.skipped ? ' ' + j.skipped + ' ignoré(s).' : ''), false);
          else sayError(j);
        });
      });
      return b;
    }

    function deleteButton(id, row) {
      var b = el('button', 'mcy-btn mcy-btn--danger', 'Supprimer');
      b.type = 'button';
      b.addEventListener('click', function () {
        if (row.querySelector('.mcy-confirm')) return;
        var box = el('div', 'mcy-confirm');
        var lab = el('label', 'mcy-field', 'Tapez SUPPRIMER pour effacer définitivement cette mission archivée');
        var inp = el('input');
        inp.type = 'text'; inp.autocomplete = 'off';
        lab.appendChild(inp);
        var ok = el('button', 'mcy-btn mcy-btn--danger', 'Confirmer');
        ok.type = 'button';
        var no = el('button', 'mcy-btn', 'Annuler');
        no.type = 'button';
        no.addEventListener('click', function () { row.removeChild(box); });
        ok.addEventListener('click', function () {
          postAction('/api/atak/cycle/archives/' + id + '/delete', { confirm: inp.value }).then(function (j) {
            if (j && j.ok) { say('Mission archivée supprimée.', false); loadList(); } else sayError(j);
          });
        });
        box.appendChild(lab); box.appendChild(ok); box.appendChild(no);
        row.appendChild(box);
        inp.focus();
      });
      return b;
    }

    function loadList() {
      var q = new URLSearchParams();
      q.set('mapId', String(mapId()));
      if (search) {
        ['q', 'from', 'to'].forEach(function (k) { var v = search.elements[k] && search.elements[k].value; if (v) q.set(k, v); });
      }
      return api('/api/atak/cycle/archives?' + q.toString()).then(function (j) {
        clear(listEl);
        if (!j || !j.ok) { sayError(j); return; }
        say('', false);
        if (!j.archives.length) {
          listEl.appendChild(el('li', 'mcy-note', 'Aucune mission archivée pour ces critères.'));
          return;
        }
        j.archives.forEach(function (a) { listEl.appendChild(renderRow(a)); });
      });
    }

    /* ---------- Consultation en lecture seule ---------- */
    function plot(items) {
      var box = root.querySelector('[data-mcy-plot]');
      clear(box);
      var pts = [];
      var drawn = [];
      items.forEach(function (it) {
        if (it.kind === 'marker' && it.pos) { pts.push(it.pos); drawn.push({ t: 'p', it: it }); }
        if (it.kind === 'shape' && it.geometry) {
          var line = [];
          collect(it.geometry.coordinates, line);
          if (line.length) { line.forEach(function (p) { pts.push(p); }); drawn.push({ t: line.length > 1 ? 'l' : 'p', it: it, line: line, poly: String(it.geometry.type || '').toLowerCase().indexOf('polygon') >= 0 }); }
        }
      });
      if (!pts.length) { box.appendChild(el('p', 'mcy-plot-empty', 'Aucune position à montrer pour cette mission.')); return; }
      var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
      pts.forEach(function (p) { x0 = Math.min(x0, p[0]); x1 = Math.max(x1, p[0]); y0 = Math.min(y0, p[1]); y1 = Math.max(y1, p[1]); });
      var W = 640, H = 400, M = 24;
      var sx = (x1 - x0) || 1, sy = (y1 - y0) || 1;
      var k = Math.min((W - 2 * M) / sx, (H - 2 * M) / sy);
      function X(v) { return M + (v - x0) * k + ((W - 2 * M) - sx * k) / 2; }
      function Y(v) { return H - M - (v - y0) * k - ((H - 2 * M) - sy * k) / 2; }
      var NS = 'http://www.w3.org/2000/svg';
      var svg = document.createElementNS(NS, 'svg');
      svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
      svg.setAttribute('aria-hidden', 'true');
      drawn.forEach(function (d) {
        var color = colorOf(d.it.color);
        var node;
        if (d.t === 'l') {
          node = document.createElementNS(NS, d.poly ? 'polygon' : 'polyline');
          node.setAttribute('points', d.line.map(function (p) { return X(p[0]).toFixed(1) + ',' + Y(p[1]).toFixed(1); }).join(' '));
          node.setAttribute('fill', d.poly ? color : 'none');
          node.setAttribute('fill-opacity', '0.18');
          node.setAttribute('stroke', color);
          node.setAttribute('stroke-width', '2');
        } else {
          var pt = d.it.kind === 'marker' ? d.it.pos : d.line[0];
          node = document.createElementNS(NS, 'circle');
          node.setAttribute('cx', X(pt[0]).toFixed(1));
          node.setAttribute('cy', Y(pt[1]).toFixed(1));
          node.setAttribute('r', '5');
          node.setAttribute('fill', color);
          node.setAttribute('stroke', '#0a0f12');
        }
        var tt = document.createElementNS(NS, 'title');
        tt.textContent = d.it.label || d.it.type || '';
        node.appendChild(tt);
        svg.appendChild(node);
      });
      box.appendChild(svg);
    }

    function hhmm(iso) {
      var d = new Date(iso);
      return isNaN(d.getTime()) ? '' : pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function openArchive(id) {
      api('/api/atak/cycle/archives/' + id).then(function (j) {
        if (!j || !j.ok) { sayError(j); return; }
        st.open = j.archive;
        viewList.hidden = true;
        viewDetail.hidden = false;
        root.querySelector('[data-mcy-detail-title]').textContent = j.archive.legacy ? j.archive.label : missionLabel(j.archive);
        root.querySelector('[data-mcy-detail-note]').textContent = j.archive.markers + ' marqueur(s) · ' + j.archive.shapes + ' tracé(s)';
        var cut = root.querySelector('[data-mcy-detail-cut]');
        cut.hidden = !j.truncated;
        plot(j.items);
        var acts = root.querySelector('[data-mcy-detail-actions]');
        clear(acts);
        var exp = el('a', 'mcy-btn', 'Exporter');
        exp.href = base + '/api/atak/cycle/archives/' + id + '/export.json';
        exp.setAttribute('download', 'mission-archive-' + id + '.json');
        acts.appendChild(exp);
        if (st.canOperate) acts.appendChild(restoreButton(id));
        var ul = root.querySelector('[data-mcy-items]');
        clear(ul);
        j.items.forEach(function (it) {
          var li = el('li', 'mcy-item');
          var kindLabel = it.kind === 'marker' ? 'Marqueur' : 'Tracé';
          li.appendChild(el('b', null, it.label || kindLabel));
          li.appendChild(el('small', null, kindLabel));
          li.appendChild(el('small', null, [it.type, it.author, hhmm(it.at)].filter(Boolean).join(' · ')));
          if (it.pinned) li.appendChild(el('small', null, 'Épinglé'));
          ul.appendChild(li);
        });
        root.querySelector('[data-mcy-back]').focus();
      });
    }

    function back() {
      viewDetail.hidden = true;
      viewList.hidden = false;
      st.open = null;
    }

    /* ---------- Réglages ---------- */
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        postAction('/api/atak/cycle/settings', {
          enabled: form.elements.enabled.checked,
          switch_time: form.elements.switch_time.value,
          duration_days: parseInt(form.elements.duration_days.value, 10),
          retention_days: parseInt(form.elements.retention_days.value, 10)
        }).then(function (j) {
          if (j && j.ok) { st.cycle = j.cycle; st.offset = Math.floor(Date.now() / 1000) - j.cycle.now; paintChip(); paintCurrent(); say('Réglages enregistrés.', false); }
          else sayError(j);
        });
      });
    }

    /* ---------- Dialogue ---------- */
    function open() {
      if (typeof dlg.showModal === 'function') { if (!dlg.open) dlg.showModal(); } else { dlg.setAttribute('open', ''); }
      back();
      paintCurrent();
      loadStatus().then(function () { return loadList(); });
    }
    chip.addEventListener('click', open);
    root.querySelector('[data-mcy-close]').addEventListener('click', function () { if (dlg.close) dlg.close(); else dlg.removeAttribute('open'); });
    root.querySelector('[data-mcy-back]').addEventListener('click', back);
    dlg.addEventListener('click', function (e) { if (e.target === dlg && dlg.close) dlg.close(); });
    if (search) search.addEventListener('submit', function (e) { e.preventDefault(); loadList(); });

    document.addEventListener('visibilitychange', function () { if (!document.hidden) loadStatus(); });
    if (selector) {
      var sel = document.querySelector(selector);
      if (sel) sel.addEventListener('change', function () { if (dlg.open) loadList(); });
    }
    loadStatus();
    timer = setInterval(loadStatus, STATUS_EVERY);
    tick = setInterval(paintChipTick, TICK_EVERY);
    return { root: root, stop: function () { clearInterval(timer); clearInterval(tick); } };
  }

  function pin(kind, id, pinned) {
    var root = document.querySelector('[data-mcy]');
    var csrf = root ? root.getAttribute('data-csrf') || '' : '';
    var base = root ? (root.getAttribute('data-base') || '').replace(/\/+$/, '') : '';
    return fetch(base + '/api/atak/cycle/pin', {
      method: 'POST', credentials: 'same-origin', cache: 'no-cache',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ kind: kind, id: id, pinned: !!pinned })
    }).then(function (r) { return r.json(); }).then(function (j) { return !!(j && j.ok); }).catch(function () { return false; });
  }

  function boot() {
    [].forEach.call(document.querySelectorAll('[data-mcy]'), function (root) {
      if (root.__mcy) return;
      root.__mcy = init(root);
      instances.push(root.__mcy);
    });
  }

  global.AtakMarkerCycle = { pin: pin, boot: boot };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})(window);
