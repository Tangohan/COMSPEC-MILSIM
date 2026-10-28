/* Overwatch — équipes de feu (composition, création, membres) et destinations assignées aux unités. */
window.OverwatchTeams = (function () {
  'use strict';

  var teams = [];
  var assignments = [];
  var pickUnit = null;
  var bound = false;

  function ow() { return window.OverwatchBeta || null; }
  function esc(v) { var api = ow(); return api ? api.escapeHtml(v) : String(v == null ? '' : v); }
  function toast(t) { var api = ow(); if (api) api.toast(t); }
  function mapId() { var api = ow(); return Number((api && api.mapId) || window.ATAK_DEFAULT_MAP_ID || 1); }
  function caps() { return window.ATAK_CAPS || {}; }
  function canEdit() { return !!caps().loggedIn && !caps().phoneSession; }
  function liveUnits() { var api = ow(); return api && api.getUnits ? (api.getUnits() || []) : []; }
  function callsignOf(u) { return String((u && (u.call_sign || u.callsign)) || '').trim(); }

  /* Requête JSON avec le message d'erreur du serveur (api() ne renvoie que le code HTTP). */
  function request(path, method, body) {
    var api = ow();
    var csrf = api && api.csrf ? api.csrf() : (window.ATAK_CSRF_TOKEN || '');
    var headers = { 'Accept': 'application/json' };
    var opts = { method: method || 'GET', credentials: 'include', cache: 'no-store', headers: headers };
    if (opts.method !== 'GET') {
      if (csrf) headers['X-CSRF-TOKEN'] = csrf;
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(Object.assign({ _csrf_token: csrf, mapId: mapId() }, body || {}));
    }
    var base = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
    return fetch(base + path, opts).then(function (r) {
      if (r.status === 204) return {};
      return r.json().catch(function () { return {}; }).then(function (payload) {
        if (!r.ok || (payload && payload.ok === false)) {
          throw new Error((payload && (payload.message || (typeof payload.error === 'string' && payload.error.indexOf('_') < 0 ? payload.error : ''))) || 'La demande a échoué.');
        }
        return payload || {};
      });
    });
  }

  function loadTeams() {
    return request('/api/atak/fire-teams?mapId=' + encodeURIComponent(mapId())).then(function (data) {
      teams = Array.isArray(data && data.fire_teams) ? data.fire_teams : [];
      return teams;
    }).catch(function () { teams = []; return teams; });
  }

  function loadAssignments() {
    return request('/api/atak/assignments?mapId=' + encodeURIComponent(mapId())).then(function (data) {
      assignments = Array.isArray(data && data.assignments) ? data.assignments : [];
      return assignments;
    }).catch(function () { assignments = []; return assignments; });
  }

  function isLinked(u) {
    var api = ow();
    if (api && typeof api.isDisconnected === 'function') return !api.isDisconnected(u);
    var st = String((u && u.status) || '').toLowerCase();
    return st !== 'offline' && st !== 'disconnected';
  }

  function unitForCallsign(cs) {
    var key = String(cs || '').trim().toUpperCase();
    if (!key) return null;
    var hit = null;
    liveUnits().some(function (u) {
      if (callsignOf(u).toUpperCase() === key) { hit = u; return true; }
      return false;
    });
    return hit;
  }

  function memberRowHtml(team, m) {
    var cs = String(m.callsign || m.display_name || '').trim() || '—';
    var u = unitForCallsign(m.callsign);
    var live = u ? (isLinked(u) ? 'En liaison' : 'Hors liaison') : 'Absent de la carte';
    var leader = m.role === 'leader';
    var actions = '';
    if (canEdit()) {
      actions = '<span class="ow-ft-actions">' +
        (leader ? '' : '<button type="button" class="ow-tag" data-ft-leader="' + esc(team.id) + ':' + esc(m.id) + '" title="Nommer chef d’équipe">Chef</button>') +
        '<button type="button" class="ow-tag" data-ft-remove="' + esc(team.id) + ':' + esc(m.id) + '" title="Retirer de l’équipe">Retirer</button>' +
        '</span>';
    }
    return '<li class="ow-ft-member">' +
      '<span class="ow-ft-cs">' + esc(cs) + '</span>' +
      (leader ? '<span class="ow-ft-role">Chef d’équipe</span>' : '') +
      '<span class="ow-ft-live' + (u && isLinked(u) ? ' is-ok' : '') + '">' + esc(live) + '</span>' +
      actions + '</li>';
  }

  function memberOptionsHtml(team) {
    var taken = {};
    (team.members || []).forEach(function (m) { taken[String(m.callsign || '').toUpperCase()] = true; });
    var opts = liveUnits().map(callsignOf).filter(function (cs) {
      return cs && !taken[cs.toUpperCase()];
    }).sort().filter(function (cs, i, all) { return all.indexOf(cs) === i; });
    return opts.map(function (cs) { return '<option value="' + esc(cs) + '">' + esc(cs) + '</option>'; }).join('');
  }

  function teamCardHtml(team) {
    var color = String(team.color || '').trim();
    var members = Array.isArray(team.members) ? team.members : [];
    var linked = members.filter(function (m) { var u = unitForCallsign(m.callsign); return u && isLinked(u); }).length;
    var rows = members.map(function (m) { return memberRowHtml(team, m); }).join('');
    var add = '';
    if (canEdit()) {
      var options = memberOptionsHtml(team);
      add = '<form class="ow-ft-add" data-ft-add="' + esc(team.id) + '">' +
        '<span class="ow-select"><select name="callsign" aria-label="Contact à ajouter">' +
        (options ? '<option value="">Ajouter un contact…</option>' + options : '<option value="">Aucun contact libre sur la carte</option>') +
        '</select></span>' +
        '<input name="manual" placeholder="ou indicatif" aria-label="Indicatif à ajouter" maxlength="64">' +
        '<button type="submit" class="ow-secondary">Ajouter</button></form>' +
        '<div class="ow-btn-row"><button type="button" class="ow-secondary is-danger" data-ft-dissolve="' + esc(team.id) + '">Dissoudre l’équipe</button></div>';
    }
    return '<article class="ow-ft-card"' + (color ? ' style="--ft-color:' + esc(color) + '"' : '') + '>' +
      '<header class="ow-ft-head"><span class="ow-ft-chip"><i aria-hidden="true"></i>' + esc(team.label || 'Équipe') + '</span>' +
      '<small>' + linked + ' / ' + members.length + ' en liaison</small></header>' +
      (rows ? '<ul class="ow-ft-list">' + rows + '</ul>' : '<p class="ow-help">Aucun membre pour le moment.</p>') +
      add + '</article>';
  }

  function assignmentRowHtml(a) {
    var nav = a.navigation && typeof a.navigation === 'object' ? a.navigation : {};
    var eta = Number(nav.eta_seconds);
    var course = String(nav.course_status || '').toUpperCase();
    var state = course === 'ARRIVED' ? 'Arrivé'
      : (Number.isFinite(eta) && eta > 0 ? 'Arrivée dans ' + (eta >= 60 ? Math.round(eta / 60) + ' min' : Math.round(eta) + ' s') : 'En route');
    var dist = Number(nav.distance_m);
    var meta = [state, Number.isFinite(dist) && dist > 0 ? (dist >= 1000 ? (dist / 1000).toFixed(1) + ' km' : Math.round(dist) + ' m') : ''].filter(Boolean).join(' · ');
    return '<div class="ow-event ow-asg-row">' +
      '<span>' + esc(a.unit_ref || 'Unité') + ' → ' + esc(a.destination_label || 'Destination') +
      '<small class="ow-net-meta">' + esc(meta) + '</small></span>' +
      (canEdit() ? '<button type="button" class="ow-tag" data-asg-detach="' + esc(a.id) + '">Détacher</button>' : '') +
      '</div>';
  }

  function viewHtml() {
    var create = canEdit()
      ? '<form class="ow-form-grid" id="ow-ft-create">' +
        '<label>Nom de l’équipe<input name="label" required maxlength="80" placeholder="Ex. ALPHA 1"></label>' +
        '<label>Couleur<input name="color" type="color" value="#2563eb"></label>' +
        '<button class="ow-primary" type="submit">Créer l’équipe</button></form>'
      : '<p class="ow-help">Connectez-vous pour créer ou modifier une équipe.</p>';
    var list = teams.length
      ? teams.map(teamCardHtml).join('')
      : '<p class="ow-help">Aucune équipe de feu sur cette carte. Créez-en une ci-dessus, ou depuis le back-office.</p>';
    var asg = assignments.length
      ? assignments.map(assignmentRowHtml).join('')
      : '<p class="ow-help">Aucune destination assignée. Ouvrez la fiche d’un contact puis « Assigner une destination ».</p>';
    return '<p class="ow-kicker">Équipes de feu</p>' +
      '<p class="ow-help">Composition de chaque équipe, comparée aux contacts réellement en liaison sur la carte.</p>' +
      create + '<div id="ow-ft-list">' + list + '</div>' +
      '<p class="ow-kicker">Destinations assignées</p>' +
      '<div id="ow-asg-list">' + asg + '</div>';
  }

  function paint() {
    var body = document.getElementById('ow-drawer-body');
    var host = document.getElementById('ow-teams-view');
    if (!body || !host) return;
    host.innerHTML = viewHtml();
  }

  function open() {
    var api = ow();
    if (!api) return;
    bind();
    Promise.all([loadTeams(), loadAssignments()]).then(function () {
      api.openDrawer('Organisation', 'Équipes', '<div id="ow-teams-view">' + viewHtml() + '</div>');
    });
  }

  function reload() {
    return Promise.all([loadTeams(), loadAssignments()]).then(paint);
  }

  function run(job, done) {
    return job.then(function () { if (done) toast(done); return reload(); })
      .catch(function (err) { toast(err && err.message ? err.message : 'La demande a échoué.'); });
  }

  function splitPair(raw) {
    var parts = String(raw || '').split(':');
    return { team: encodeURIComponent(parts[0] || ''), member: encodeURIComponent(parts[1] || '') };
  }

  /* --- Destinations : la prochaine sélection sur la carte devient la destination de l'unité. --- */
  function setPickBanner(on, cs) {
    var bar = document.getElementById('ow-assign-banner');
    if (!bar && on) {
      bar = document.createElement('div');
      bar.id = 'ow-assign-banner';
      bar.className = 'ow-assign-banner';
      bar.innerHTML = '<span></span><button type="button" class="ow-secondary" data-asg-cancel>Annuler</button>';
      var stage = document.getElementById('ow-map-stage') || document.body;
      stage.appendChild(bar);
    }
    if (!bar) return;
    bar.hidden = !on;
    if (on) bar.querySelector('span').textContent = 'Cliquez sur la carte la destination de ' + cs + ' (un contact, un repère ou un point). Échap pour annuler.';
  }

  function beginPick(unit) {
    if (!unit) return;
    if (!canEdit()) { toast('Connectez-vous pour assigner une destination.'); return; }
    pickUnit = unit;
    setPickBanner(true, callsignOf(unit) || 'cette unité');
  }

  function cancelPick() {
    pickUnit = null;
    setPickBanner(false);
  }

  function isPicking() { return !!pickUnit; }

  function nearestUnit(world) {
    var best = null;
    var bestD = 25;
    liveUnits().forEach(function (u) {
      var x = Number(u.pos_x);
      var y = Number(u.pos_y);
      if (!Number.isFinite(x) || !Number.isFinite(y) || u === pickUnit) return;
      var d = Math.hypot(x - world.x, y - world.y);
      if (d < bestD) { bestD = d; best = u; }
    });
    return best;
  }

  function nearestMarker(world) {
    var api = ow();
    var rows = api && api.getArmaMarkerRows ? (api.getArmaMarkerRows() || []) : [];
    var best = null;
    var bestD = 40;
    rows.forEach(function (row) {
      var data = api.parseMarkerData ? (api.parseMarkerData(row) || {}) : {};
      var w = api.markerWorld ? api.markerWorld(Object.assign({}, row, data)) : null;
      if (!w || !Number.isFinite(Number(w.x)) || !Number.isFinite(Number(w.y))) return;
      var d = Math.hypot(Number(w.x) - world.x, Number(w.y) - world.y);
      if (d < bestD) {
        bestD = d;
        best = { id: row.id, label: String(data.label || data.text || row.label || row.name || 'Repère'), x: Number(w.x), y: Number(w.y) };
      }
    });
    return best;
  }

  /* Appelée par Overwatch pour chaque clic carte : true si le clic a servi à choisir une destination. */
  function consumeMapClick(ll) {
    if (!pickUnit) return false;
    var api = ow();
    if (!api || !ll) return false;
    var world = api.latLngToWorld(ll);
    var unit = pickUnit;
    var dest;
    var hitU = nearestUnit(world);
    var hitM = hitU ? null : nearestMarker(world);
    if (hitU) {
      dest = { type: 'unit', id: callsignOf(hitU), label: callsignOf(hitU), x: Number(hitU.pos_x), y: Number(hitU.pos_y) };
    } else if (hitM) {
      dest = { type: 'marker', id: String(hitM.id), label: hitM.label, x: hitM.x, y: hitM.y };
    } else {
      var grid = api.gridLabel ? api.gridLabel(ll) : '';
      dest = { type: 'custom', id: null, label: grid || 'Point ' + Math.round(world.x) + ' / ' + Math.round(world.y), x: world.x, y: world.y };
    }
    cancelPick();
    request('/api/atak/assignments', 'POST', {
      unit_kind: 'ground',
      unit_id: unit.id || null,
      unit_ref: callsignOf(unit),
      destination_type: dest.type,
      destination_id: dest.id,
      destination_label: dest.label,
      destination_x: dest.x,
      destination_y: dest.y,
      assignment_mode: 'DIRECT'
    }).then(function () {
      toast((callsignOf(unit) || 'Unité') + ' → ' + dest.label);
      loadAssignments().then(paint);
    }).catch(function (err) { toast(err && err.message ? err.message : 'Impossible d’assigner la destination.'); });
    return true;
  }

  function assignmentFor(unit) {
    var cs = callsignOf(unit).toUpperCase();
    var hit = null;
    assignments.some(function (a) {
      if (String(a.unit_ref || '').toUpperCase() === cs && String(a.unit_kind || 'ground') === 'ground') { hit = a; return true; }
      return false;
    });
    if (hit) return hit;
    var nav = unit && (unit.navigation || unit.assignment);
    return nav && typeof nav === 'object' && nav.id ? nav : null;
  }

  function detach(id) {
    return run(request('/api/atak/assignments/' + encodeURIComponent(id) + '/detach', 'POST'), 'Destination détachée.');
  }

  function bind() {
    if (bound) return;
    bound = true;
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && pickUnit) cancelPick();
    });
    document.addEventListener('click', function (e) {
      var t = e.target;
      if (!t || !t.closest) return;
      if (t.closest('[data-asg-cancel]')) { cancelPick(); return; }
      var det = t.closest('[data-asg-detach]');
      if (det) { detach(det.getAttribute('data-asg-detach')); return; }
      var rm = t.closest('[data-ft-remove]');
      if (rm) {
        var p = splitPair(rm.getAttribute('data-ft-remove'));
        run(request('/api/atak/fire-teams/' + p.team + '/members/' + p.member, 'DELETE'), 'Membre retiré.');
        return;
      }
      var lead = t.closest('[data-ft-leader]');
      if (lead) {
        var q = splitPair(lead.getAttribute('data-ft-leader'));
        run(request('/api/atak/fire-teams/' + q.team + '/members/' + q.member, 'PATCH', { role: 'leader' }), 'Chef d’équipe nommé.');
        return;
      }
      var dis = t.closest('[data-ft-dissolve]');
      if (dis) {
        if (!window.confirm('Dissoudre cette équipe ? Elle disparaît de la carte, son historique est conservé.')) return;
        run(request('/api/atak/fire-teams/' + encodeURIComponent(dis.getAttribute('data-ft-dissolve')) + '/dissolve', 'POST'), 'Équipe dissoute.');
      }
    });
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || !form.matches) return;
      if (form.matches('#ow-ft-create')) {
        e.preventDefault();
        var label = String(form.elements.label.value || '').trim();
        if (!label) return;
        run(request('/api/atak/fire-teams', 'POST', { label: label, color: form.elements.color.value || '#2563eb', kind: 'ephemeral' }), 'Équipe créée.');
        return;
      }
      if (form.matches('[data-ft-add]')) {
        e.preventDefault();
        var cs = String(form.elements.manual.value || '').trim() || String(form.elements.callsign.value || '').trim();
        if (!cs) { toast('Choisissez un contact ou saisissez un indicatif.'); return; }
        run(request('/api/atak/fire-teams/' + encodeURIComponent(form.getAttribute('data-ft-add')) + '/members', 'POST', { callsign: cs }), cs + ' ajouté à l’équipe.');
      }
    });
  }

  function boot() {
    bind();
    loadAssignments();
    window.setInterval(function () { if (!document.hidden) loadAssignments(); }, 30000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();

  return {
    open: open,
    reload: reload,
    beginPick: beginPick,
    cancelPick: cancelPick,
    isPicking: isPicking,
    consumeMapClick: consumeMapClick,
    assignmentFor: assignmentFor,
    detach: detach,
    getTeams: function () { return teams.slice(); }
  };
})();
