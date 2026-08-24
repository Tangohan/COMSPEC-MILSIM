/* Plan de mission ATAK — barre de conduite, onglet, tableau, fiche. */
window.ATAKMissionPlan = (function () {
  'use strict';

  var snap = null;
  var timer = null;
  var clockTimer = null;
  var clockBase = 0;
  var clockSeconds = null;
  var boardView = 'taskorg';
  var commanderMode = 'map';
  var placingId = 0;
  var placingLine = null;

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId
      ? window.ATAKSocket.getMapId()
      : 1;
  }
  function csrf() {
    return window.ATAK_CSRF_TOKEN || '';
  }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function qs(id) {
    return document.getElementById(id);
  }
  function fmtClock(sec) {
    if (sec == null || !isFinite(sec)) return '—';
    var n = Math.round(sec);
    var neg = n < 0;
    n = Math.abs(n);
    var h = Math.floor(n / 3600);
    var m = Math.floor((n % 3600) / 60);
    var s = n % 60;
    var t = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    return neg ? '−' + t : t;
  }
  function fmtEta(sec) {
    if (sec == null || !isFinite(sec)) return '—';
    var n = Math.max(0, Math.round(sec));
    var m = Math.floor(n / 60);
    var s = n % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }
  function hasPlan() {
    return !!(snap && snap.plan && snap.plan.id);
  }
  function slotFor(callsign) {
    if (!snap || !snap.slots) return null;
    return snap.slots[String(callsign || '').toUpperCase()] || null;
  }

  function fetchSnap() {
    var url = apiBase() + '/api/atak/mission-plan?mapId=' + encodeURIComponent(mapId());
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) {
      return r.text().then(function (raw) {
        var data = null;
        try { data = raw ? JSON.parse(raw) : null; } catch (e) { data = null; }
        return data;
      });
    }).then(function (data) {
      snap = data && typeof data === 'object' ? data : null;
      clockBase = Date.now();
      clockSeconds = hasPlan() ? snap.plan.clock_seconds : null;
      renderAll();
      try {
        window.dispatchEvent(new CustomEvent('atak:mission-plan-updated', { detail: snap }));
      } catch (e) {}
      return snap;
    }).catch(function () {
      return snap;
    });
  }

  function post(path, payload) {
    return fetch(apiBase() + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf()
      },
      body: JSON.stringify(Object.assign({ mapId: mapId(), _csrf_token: csrf() }, payload || {}))
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data && data.ok !== false && data.plan !== undefined) {
        snap = data;
        clockBase = Date.now();
        clockSeconds = hasPlan() ? snap.plan.clock_seconds : null;
        renderAll();
        try {
          window.dispatchEvent(new CustomEvent('atak:mission-plan-updated', { detail: snap }));
        } catch (e) {}
      }
      return data;
    });
  }

  function liveClock() {
    if (!hasPlan()) return '—';
    if (clockSeconds == null) return '—';
    var s = clockSeconds + Math.floor((Date.now() - clockBase) / 1000);
    return fmtClock(s);
  }

  function tickClock() {
    var el = qs('atak-mission-clock');
    if (el) el.textContent = liveClock();
    var bar = qs('atak-mission-c2-clock');
    if (bar) bar.textContent = liveClock();
  }

  function setCommanderMode(mode) {
    commanderMode = mode || 'map';
    document.querySelectorAll('[data-mission-mode]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-mission-mode') === commanderMode);
    });
    if (commanderMode === 'cop') {
      closeBoard();
      if (window.ATAKCopBoard && window.ATAKCopBoard.open) window.ATAKCopBoard.open();
      return;
    }
    if (window.ATAKCopBoard && window.ATAKCopBoard.close) window.ATAKCopBoard.close();
    if (commanderMode === 'roster') {
      openBoard('roster');
      return;
    }
    if (commanderMode === 'mission') {
      closeBoard();
      if (window.ATAKMap && window.ATAKMap.patchDisplayPrefs) {
        window.ATAKMap.patchDisplayPrefs({ showMissionOverlay: true });
      }
      var tab = document.querySelector('#atak-panel-left .atak-tab[data-tab="mission"]');
      if (tab) tab.click();
      return;
    }
    closeBoard();
  }

  function renderBar() {
    var bar = qs('atak-mission-c2bar');
    if (!bar) return;
    bar.classList.toggle('is-on', hasPlan());
    if (!hasPlan()) return;
    var p = snap.plan;
    var status = qs('atak-mission-c2-status');
    if (!status) return;
    var bits = [];
    bits.push('<span class="atak-mission-c2bar__op">' + esc(p.operation_name || p.title) + '</span>');
    bits.push('<span><span class="atak-mission-c2bar__k">Phase</span><span class="atak-mission-c2bar__v">' + esc(p.phase_label) + '</span></span>');
    bits.push('<span><span class="atak-mission-c2bar__k">Horloge</span><span class="atak-mission-c2bar__v" id="atak-mission-c2-clock">' + esc(liveClock()) + '</span></span>');
    bits.push('<span><span class="atak-mission-c2bar__k">En liaison</span><span class="atak-mission-c2bar__v">' + esc(String(p.live || 0)) + '</span></span>');
    bits.push('<span><span class="atak-mission-c2bar__k">Hors liaison</span><span class="atak-mission-c2bar__v">' + esc(String(p.offline || 0)) + '</span></span>');
    (snap.unit_status || []).forEach(function (u) {
      var eta = u.eta_seconds != null ? ' ETA ' + fmtEta(u.eta_seconds) : '';
      bits.push('<span><span class="atak-mission-c2bar__k">' + esc(u.code) + '</span><span class="atak-mission-c2bar__v">' + esc(u.status_label) + eta + '</span></span>');
    });
    status.innerHTML = bits.join('');
  }

  function renderPanel() {
    var root = qs('atak-mission-panel');
    if (!root) return;
    if (!hasPlan()) {
      root.innerHTML = '<p class="atak-mission-empty">Aucun ordre de mission publié ou en session pour cette carte. La planification se prépare dans le bureau Opérations, puis s’affiche ici dès qu’elle est ouverte.</p>';
      return;
    }
    var p = snap.plan;
    var next = (snap.next_events || []).map(function (ev) {
      return '<li><span>' + esc(ev.clock) + ' · ' + esc(ev.label) + '</span></li>';
    }).join('') || '<li class="muted">Aucun jalon à venir</li>';
    var units = (snap.unit_status || []).map(function (u) {
      return '<li><span>' + esc(u.code) + '</span><span>' + esc(u.status_label) + '</span></li>';
    }).join('');
    var graphics = (snap.overlay && snap.overlay.graphics ? snap.overlay.graphics : []).map(function (g) {
      var opts = ['planned', 'current', 'completed', 'modified'].map(function (st) {
        var lab = st === 'planned' ? 'Prévu' : st === 'current' ? 'En cours' : st === 'completed' ? 'Terminé' : 'Modifié en session';
        return '<option value="' + st + '"' + (g.draw_state === st ? ' selected' : '') + '>' + lab + '</option>';
      }).join('');
      return '<div class="atak-mission-graphic-row">' +
        '<strong>' + esc(g.code) + '</strong>' +
        '<span class="muted">' + esc(g.placed ? g.kind_label : 'Non placé') + '</span>' +
        '<select data-mission-graphic-state="' + g.id + '">' + opts + '</select>' +
        '<button type="button" class="atak-mission-btn" data-mission-place="' + g.id + '" data-geom="' + esc(g.geom_type) + '">' +
          (g.placed ? 'Déplacer' : 'Placer') +
        '</button></div>';
    }).join('');
    var phases = [
      { v: 'PREPARATION', l: 'Préparation' },
      { v: 'MOVEMENT', l: 'Mouvement' },
      { v: 'ASSAULT', l: 'Assaut' },
      { v: 'CONSOLIDATION', l: 'Consolidation' },
      { v: 'EXFIL', l: 'Exfiltration' }
    ].map(function (ph) {
      var cur = String(p.phase || '').toUpperCase();
      return '<option value="' + ph.v + '"' + (cur === ph.v ? ' selected' : '') + '>' + ph.l + '</option>';
    }).join('');
    root.innerHTML =
      '<div class="atak-mission-panel-head">' +
        '<div><h3 class="atak-mission-panel-title">' + esc(p.operation_name || p.title) + '</h3>' +
        '<p class="atak-mission-panel-sub">' + esc(p.mission_code) + ' · ' + esc(p.phase_label) + ' · ' + esc(p.status_label) + '</p></div>' +
        '<div class="atak-mission-clock" id="atak-mission-clock">' + esc(liveClock()) + '</div>' +
      '</div>' +
      '<div class="atak-mission-kv">' +
        '<div class="atak-mission-kv__row"><span class="atak-mission-kv__k">Présents</span><span class="atak-mission-kv__v">' + esc(p.present) + ' / ' + esc(p.auth) + '</span></div>' +
        '<div class="atak-mission-kv__row"><span class="atak-mission-kv__k">En liaison</span><span class="atak-mission-kv__v">' + esc(p.live) + ' · hors liaison ' + esc(p.offline) + '</span></div>' +
      '</div>' +
      '<p class="atak-panel-hint">Prochains jalons</p>' +
      '<ul class="atak-mission-list">' + next + '</ul>' +
      '<p class="atak-panel-hint">État des éléments</p>' +
      '<ul class="atak-mission-list">' + units + '</ul>' +
      '<div class="atak-mission-actions">' +
        '<button type="button" class="atak-mission-btn atak-mission-btn--primary" data-mission-open-board>Ouvrir le tableau de mission</button>' +
      '</div>' +
      '<label class="atak-mission-form"><span class="muted">Phase</span>' +
        '<select id="atak-mission-phase">' + phases + '</select></label>' +
      '<p class="atak-panel-hint">Repères sur la carte</p>' + graphics;
  }

  function treeHtml(nodes, depth) {
    depth = depth || 0;
    var html = '';
    (nodes || []).forEach(function (n) {
      html += '<div class="atak-mission-tree__el" style="padding-left:' + (depth * 12) + 'px">' + esc(n.code || n.label) + ' · ' + esc(n.kind_label) + '</div>';
      (n.slots || []).forEach(function (s) {
        html += '<div class="atak-mission-tree__slot' + (s.online ? '' : ' is-off') + (s.mismatch ? ' is-mismatch' : '') + '">' +
          '<span>' + esc(s.callsign) + '</span>' +
          '<span>Prévu ' + esc(s.planned_name) + '</span>' +
          '<span>Actuel ' + esc(s.current_name) + '</span>' +
          '<span>' + esc(s.presence_label) + '</span></div>';
      });
      html += treeHtml(n.children || [], depth + 1);
    });
    return html;
  }

  function renderBoard() {
    var body = qs('atak-mission-board-body');
    var title = qs('atak-mission-board-title');
    if (!body) return;
    document.querySelectorAll('[data-mission-board-tab]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-mission-board-tab') === boardView);
    });
    if (!hasPlan()) {
      body.innerHTML = '<p class="atak-mission-empty">Aucun plan ouvert.</p>';
      return;
    }
    if (title) title.textContent = snap.plan.operation_name || 'Tableau de mission';
    if (boardView === 'roster') {
      var rows = (snap.roster || []).map(function (r) {
        return '<tr><td>' + esc(r.callsign) + '</td><td>' + esc(r.player) + '</td><td>' + esc(r.role) + '</td>' +
          '<td>' + esc(r.online_label) + '</td><td>' + esc(r.position) + '</td><td>' + esc(r.task) + '</td>' +
          '<td>' + esc(r.destination) + '</td><td>' + esc(fmtEta(r.eta_seconds)) + '</td>' +
          '<td>' + esc(r.med) + '</td><td>' + esc(r.comms) + '</td></tr>';
      }).join('');
      body.innerHTML = '<table class="atak-mission-table"><thead><tr>' +
        '<th>Indicatif</th><th>Joueur</th><th>Fonction</th><th>Liaison</th><th>Position</th>' +
        '<th>Tâche</th><th>Destination</th><th>ETA</th><th>Sanitaire</th><th>Radio</th>' +
        '</tr></thead><tbody>' + rows + '</tbody></table>';
      return;
    }
    if (boardView === 'timeline') {
      var items = (snap.timeline || []).map(function (ev) {
        return '<li><span>' + esc(ev.clock) + '</span><span>' + esc(ev.label) + '</span><span class="muted">' + esc(ev.source_label) + '</span></li>';
      }).join('');
      body.innerHTML = '<ul class="atak-mission-list">' + items + '</ul>' +
        '<form class="atak-mission-form" id="atak-mission-timeline-form">' +
        '<input type="text" id="atak-mission-timeline-input" maxlength="200" placeholder="Ajouter un événement de conduite" />' +
        '<button type="submit" class="atak-mission-btn atak-mission-btn--primary">Inscrire</button></form>';
      return;
    }
    if (boardView === 'documents') {
      var docs = (snap.documents || []).map(function (d) {
        return '<li><span>' + esc(d.label) + '</span></li>';
      }).join('');
      var pdf = apiBase() + '/api/atak/mission-plan/pdf?mapId=' + encodeURIComponent(mapId());
      body.innerHTML = '<p class="atak-panel-hint">Le paquet mission reste le même document que dans la préparation : ordre, organisation, effectifs, radio, chronologie et annexes.</p>' +
        '<ul class="atak-mission-docs">' + docs + '</ul>' +
        '<div class="atak-mission-actions"><a class="atak-mission-btn atak-mission-btn--primary" href="' + esc(pdf) + '" target="_blank" rel="noopener">Ouvrir le paquet</a></div>';
      return;
    }
    body.innerHTML = '<div class="atak-mission-tree">' + treeHtml(snap.task_org || []) + '</div>';
  }

  function openBoard(view) {
    if (view) boardView = view;
    var el = qs('atak-mission-board');
    if (el) el.hidden = false;
    renderBoard();
  }
  function closeBoard() {
    var el = qs('atak-mission-board');
    if (el) el.hidden = true;
  }

  function renderAll() {
    renderBar();
    renderPanel();
    var board = qs('atak-mission-board');
    if (board && !board.hidden) renderBoard();
  }

  function startPlace(id, geom) {
    placingId = id;
    placingLine = geom === 'line' ? [] : null;
    document.body.classList.add('atak-placing-mission');
  }
  function cancelPlace() {
    placingId = 0;
    placingLine = null;
    document.body.classList.remove('atak-placing-mission');
  }
  function onMapClick(e) {
    if (!placingId || !e || !e.latlng || !window.ATAKMap || !window.ATAKMap.worldFromLatLng) return;
    var w = window.ATAKMap.worldFromLatLng(e.latlng);
    if (placingLine) {
      placingLine.push({ x: w.x, y: w.y });
      if (placingLine.length < 2) return;
      post('/api/atak/mission-plan/graphics/' + placingId, { path: placingLine }).then(cancelPlace);
      return;
    }
    post('/api/atak/mission-plan/graphics/' + placingId, { x: w.x, y: w.y }).then(cancelPlace);
  }

  function bind() {
    document.addEventListener('click', function (ev) {
      var t = ev.target;
      if (!t || !t.closest) return;
      var mode = t.closest('[data-mission-mode]');
      if (mode) {
        setCommanderMode(mode.getAttribute('data-mission-mode'));
        return;
      }
      if (t.closest('[data-mission-open-board]')) {
        openBoard('taskorg');
        return;
      }
      if (t.closest('[data-mission-board-close]')) {
        closeBoard();
        setCommanderMode('map');
        return;
      }
      var tab = t.closest('[data-mission-board-tab]');
      if (tab) {
        boardView = tab.getAttribute('data-mission-board-tab') || 'taskorg';
        renderBoard();
        return;
      }
      var place = t.closest('[data-mission-place]');
      if (place) {
        startPlace(parseInt(place.getAttribute('data-mission-place'), 10), place.getAttribute('data-geom'));
      }
    });
    document.addEventListener('change', function (ev) {
      var t = ev.target;
      if (!t) return;
      if (t.id === 'atak-mission-phase') {
        post('/api/atak/mission-plan/phase', { phase: t.value });
        return;
      }
      var gid = t.getAttribute && t.getAttribute('data-mission-graphic-state');
      if (gid) {
        post('/api/atak/mission-plan/graphics/' + gid + '/state', { state: t.value });
      }
    });
    document.addEventListener('submit', function (ev) {
      var form = ev.target;
      if (!form || form.id !== 'atak-mission-timeline-form') return;
      ev.preventDefault();
      var input = qs('atak-mission-timeline-input');
      var label = input ? String(input.value || '').trim() : '';
      if (!label) return;
      post('/api/atak/mission-plan/timeline', { label: label }).then(function () {
        if (input) input.value = '';
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if (placingId) cancelPlace();
        else closeBoard();
      }
    });
    window.addEventListener('atak:mapready', function () {
      var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
      if (map && !map._atakMissionClick) {
        map._atakMissionClick = true;
        map.on('click', onMapClick);
      }
      fetchSnap();
    });
  }

  function start() {
    if (timer) return;
    fetchSnap();
    timer = setInterval(fetchSnap, 8000);
    clockTimer = setInterval(tickClock, 1000);
  }

  bind();
  window.addEventListener('atak:mapready', start);

  return {
    refresh: fetchSnap,
    snapshot: function () { return snap; },
    slotFor: slotFor,
    openBoard: openBoard,
    closeBoard: closeBoard,
    setCommanderMode: setCommanderMode
  };
})();
