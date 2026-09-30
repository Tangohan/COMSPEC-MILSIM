/**
 * Overwatch Beta — ORBAT live (arbre gauche + carte + inspecteur).
 * Entité Athena unifiée : nœud ORBAT ↔ contact BFT (callsign / military_id / user_id).
 */
(function () {
  'use strict';

  if (!window.ATAK_OVERWATCH_BETA) return;

  var state = {
    active: false,
    roster: null,
    entities: [],
    byId: {},
    collapsed: {},
    selectedId: '',
    query: '',
    loadError: '',
    loading: false
  };

  function ow() {
    return window.OverwatchBeta || null;
  }

  function esc(v) {
    var beta = ow();
    if (beta && typeof beta.escapeHtml === 'function') return beta.escapeHtml(v);
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function clean(v, fallback) {
    var beta = ow();
    if (beta && typeof beta.clean === 'function') return beta.clean(v, fallback);
    var s = String(v == null ? '' : v).trim();
    return s || (fallback == null ? '' : String(fallback));
  }

  function api(path) {
    var beta = ow();
    if (beta && typeof beta.api === 'function') return beta.api(path);
    var base = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
    return fetch(base + path, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function normKey(s) {
    return String(s || '').trim().toUpperCase().replace(/\s+/g, ' ');
  }

  function buildUserIndex(units) {
    var byUser = {};
    var byCall = {};
    var byMil = {};
    var map = window.ATAK_CALLSIGN_TO_USER || {};
    Object.keys(map).forEach(function (cs) {
      var row = map[cs];
      if (!row || !row.userId) return;
      byUser[row.userId] = byUser[row.userId] || { profile: row, units: [] };
      byUser[row.userId].profile = row;
    });
    (units || []).forEach(function (u) {
      var beta = ow();
      var cs = beta && beta.callsign ? beta.callsign(u) : clean(u.call_sign || u.callsign || u.name, '');
      var key = normKey(cs);
      if (key) byCall[key] = u;
      var mil = clean(u.military_id || u.bft_id || (u.extra && (u.extra.military_id || u.extra.bft_id)), '');
      if (mil) byMil[normKey(mil)] = u;
      var profile = key && map[key] ? map[key] : null;
      if (profile && profile.userId) {
        byUser[profile.userId] = byUser[profile.userId] || { profile: profile, units: [] };
        byUser[profile.userId].units.push(u);
      }
    });
    return { byUser: byUser, byCall: byCall, byMil: byMil };
  }

  function matchBft(member, index) {
    var uid = Number(member && member.user_id || 0);
    if (uid > 0 && index.byUser[uid] && index.byUser[uid].units.length) {
      return index.byUser[uid].units[0];
    }
    var label = normKey(member && member.label);
    if (label && index.byCall[label]) return index.byCall[label];
    return null;
  }

  function isLive(unit) {
    if (!unit) return false;
    var beta = ow();
    if (!beta) return true;
    var age = typeof beta.unitAgeSec === 'function' ? beta.unitAgeSec(unit) : 0;
    var ttl = Number(beta.LIVE_TTL_SEC || 180);
    return !(age > ttl);
  }

  function flattenRoster(node, parentId, depth, out) {
    if (!node || typeof node !== 'object') return;
    var unitId = Number(node.unitId || 0);
    var id = unitId > 0 ? 'unit_' + unitId : String(node.id || ('node_' + out.length));
    var members = Array.isArray(node.members) ? node.members : [];
    var children = Array.isArray(node.children) ? node.children : [];
    var entity = {
      id: id,
      parent_id: parentId || null,
      unitId: unitId,
      name: clean(node.label, 'Unité'),
      short_name: clean(node.role, ''),
      callsign: clean(node.role || node.label, ''),
      side: 'BLUFOR',
      type: clean(node.type || node.structType, 'command'),
      echelon: clean(node.structType || node.type, ''),
      military_id: unitId > 0 ? 'BFT-U' + unitId : '',
      status: clean(node.adminStatus || node.status, 'active'),
      statusLabel: clean(node.adminStatusLabel, ''),
      strength_authorized: Number(node.strength || members.length || 0),
      strength_current: Number(node.visibleStrength != null ? node.visibleStrength : members.length || 0),
      commander: {
        name: clean(node.leader, '—'),
        user_id: Number(node.commanderUserId || 0) || null
      },
      mission: clean(node.mission, ''),
      description: clean(node.orbatDetails || node.descriptionLong, ''),
      image_url: node.chartIconUrl || node.chartImageUrl || null,
      members: members,
      childrenIds: [],
      depth: depth || 0,
      observed: [],
      gap: { planned: 0, observed: 0, missing: 0 },
      position: null,
      visible: true,
      locked: false,
      raw: node
    };
    out.push(entity);
    children.forEach(function (child) {
      var before = out.length;
      flattenRoster(child, id, (depth || 0) + 1, out);
      if (out[before]) entity.childrenIds.push(out[before].id);
    });
  }

  function enrichWithBft(entities, units) {
    var index = buildUserIndex(units);
    entities.forEach(function (ent) {
      var observed = [];
      var positions = [];
      (ent.members || []).forEach(function (mem) {
        var bft = matchBft(mem, index);
        var live = isLive(bft);
        if (bft) {
          observed.push({ member: mem, bft: bft, live: live });
          if (live) {
            var beta = ow();
            var loc = beta && beta.point ? beta.point(bft) : null;
            if (loc) positions.push(loc);
          }
        } else {
          observed.push({ member: mem, bft: null, live: false });
        }
      });
      // Commander match
      if (ent.commander && ent.commander.user_id) {
        var cmdPack = index.byUser[ent.commander.user_id];
        if (cmdPack && cmdPack.units.length) {
          ent.commander.bft = cmdPack.units[0];
          ent.commander.live = isLive(cmdPack.units[0]);
        }
      }
      var liveCount = observed.filter(function (o) { return o.live; }).length;
      var planned = Math.max(ent.strength_authorized, (ent.members || []).length);
      ent.observed = observed;
      ent.strength_current = liveCount;
      ent.gap = {
        planned: planned,
        observed: liveCount,
        missing: Math.max(0, planned - liveCount)
      };
      if (positions.length) {
        var lat = 0;
        var lng = 0;
        positions.forEach(function (p) { lat += p.lat; lng += p.lng; });
        ent.position = {
          source: 'BFT',
          lat: lat / positions.length,
          lng: lng / positions.length,
          updated_at: new Date().toISOString()
        };
      } else {
        ent.position = null;
      }
    });
  }

  function rebuildEntities() {
    var beta = ow();
    var units = beta && typeof beta.getUnits === 'function' ? beta.getUnits() : [];
    var list = [];
    if (state.roster) flattenRoster(state.roster, null, 0, list);
    enrichWithBft(list, units);
    state.entities = list;
    state.byId = {};
    list.forEach(function (e) { state.byId[e.id] = e; });
  }

  function loadRoster() {
    state.loading = true;
    state.loadError = '';
    renderTree();
    return api('/api/orbat/roster')
      .then(function (payload) {
        state.loading = false;
        if (!payload || payload.success === false) {
          state.loadError = clean(payload && payload.message, 'ORBAT indisponible.');
          state.roster = null;
          rebuildEntities();
          renderTree();
          return;
        }
        state.roster = payload.roster || null;
        rebuildEntities();
        renderTree();
        syncSelectionHighlight();
      })
      .catch(function () {
        state.loading = false;
        state.loadError = 'Impossible de charger l’ORBAT.';
        renderTree();
      });
  }

  function nodeMatchesQuery(ent, q) {
    if (!q) return true;
    var blob = [
      ent.name, ent.short_name, ent.callsign, ent.military_id,
      ent.commander && ent.commander.name, ent.mission
    ].join(' ').toLowerCase();
    (ent.members || []).forEach(function (m) { blob += ' ' + String(m.label || '').toLowerCase(); });
    return blob.indexOf(q) !== -1;
  }

  function visibleSet() {
    var q = state.query;
    var keep = {};
    state.entities.forEach(function (ent) {
      if (nodeMatchesQuery(ent, q)) {
        keep[ent.id] = true;
        var pid = ent.parent_id;
        while (pid && state.byId[pid]) {
          keep[pid] = true;
          pid = state.byId[pid].parent_id;
        }
      }
    });
    return keep;
  }

  function statusDot(ent) {
    if (ent.gap.observed > 0) return 'is-live';
    if (ent.gap.planned > 0) return 'is-gap';
    return 'is-idle';
  }

  function renderNode(ent, keep) {
    if (keep && !keep[ent.id]) return '';
    var kids = (ent.childrenIds || []).map(function (cid) { return state.byId[cid]; }).filter(Boolean);
    var open = state.collapsed[ent.id] !== true;
    if (state.query) open = true;
    var gapWarn = ent.gap.missing > 0 && ent.gap.planned > 0;
    var cls = 'ow-orbat-node' +
      (state.selectedId === ent.id ? ' is-selected' : '') +
      (gapWarn ? ' is-gap' : '') +
      (ent.gap.observed > 0 ? ' is-observed' : '');
    var childHtml = kids.map(function (c) { return renderNode(c, keep); }).join('');
    return '<details class="' + cls + '" data-orbat-id="' + esc(ent.id) + '"' + (open ? ' open' : '') + '>' +
      '<summary class="ow-orbat-sum">' +
      '<span class="ow-orbat-dot ' + statusDot(ent) + '" aria-hidden="true"></span>' +
      '<span class="ow-orbat-name">' + esc(ent.name) + '</span>' +
      (ent.short_name && ent.short_name !== ent.name
        ? '<span class="ow-orbat-code">' + esc(ent.short_name) + '</span>' : '') +
      '<span class="ow-orbat-str" title="Observés / planifiés">' +
        ent.gap.observed + '/' + ent.gap.planned + '</span>' +
      (gapWarn ? '<span class="ow-orbat-warn" title="Écart ORBAT">⚠</span>' : '') +
      '</summary>' +
      (childHtml ? '<div class="ow-orbat-kids">' + childHtml + '</div>' : '') +
      '</details>';
  }

  function renderTree() {
    var host = document.getElementById('ow-orbat-tree');
    if (!host) return;
    if (state.loading) {
      host.innerHTML = '<p class="ow-orbat-empty">Chargement de l’ORBAT…</p>';
      return;
    }
    if (state.loadError) {
      host.innerHTML = '<p class="ow-orbat-empty">' + esc(state.loadError) +
        '</p><button type="button" class="ow-secondary" data-orbat-reload>Réessayer</button>';
      return;
    }
    if (!state.entities.length) {
      host.innerHTML = '<p class="ow-orbat-empty">Aucune structure ORBAT pour cette communauté.</p>' +
        '<a class="ow-secondary" href="' + esc(String(window.ATAK_API_BASE || '').replace(/\/$/, '') + '/orbat') +
        '">Ouvrir l’éditeur ORBAT</a>';
      return;
    }
    var keep = state.query ? visibleSet() : null;
    var roots = state.entities.filter(function (e) { return !e.parent_id || !state.byId[e.parent_id]; });
    host.innerHTML = roots.map(function (r) { return renderNode(r, keep); }).join('') ||
      '<p class="ow-orbat-empty">Aucun résultat.</p>';
  }

  function syncSelectionHighlight() {
    document.querySelectorAll('#ow-orbat-tree .ow-orbat-node').forEach(function (el) {
      el.classList.toggle('is-selected', el.getAttribute('data-orbat-id') === state.selectedId);
    });
  }

  function focusEntityOnMap(ent) {
    var beta = ow();
    if (!beta || !beta.map) return;
    if (ent.position) {
      try {
        beta.map.setView([ent.position.lat, ent.position.lng], Math.max(beta.map.getZoom(), 4));
      } catch (e) {}
      return;
    }
    var live = (ent.observed || []).filter(function (o) { return o.live && o.bft; });
    if (live.length === 1 && typeof beta.selectUnit === 'function') {
      beta.selectUnit(live[0].bft);
      var loc = beta.point(live[0].bft);
      if (loc) beta.map.setView(loc, Math.max(beta.map.getZoom(), 4));
    }
  }

  function followEntity(ent) {
    var beta = ow();
    var live = (ent.observed || []).find(function (o) { return o.live && o.bft; });
    if (live && beta && typeof beta.selectUnit === 'function') {
      beta.selectUnit(live.bft);
      var btn = document.querySelector('[data-follow-selected]');
      if (btn) btn.click();
      else if (typeof beta.toast === 'function') beta.toast('Suivi activé sur ' + beta.callsign(live.bft));
    } else if (beta && typeof beta.toast === 'function') {
      beta.toast('Aucun contact BFT en liaison pour cette unité.');
    }
  }

  function inspectorHtml(ent) {
    var gap = ent.gap;
    var gapBlock = gap.missing > 0
      ? '<div class="ow-orbat-gap"><strong>⚠ Écart ORBAT</strong><span>' +
        gap.missing + ' personnel planifié non observé</span></div>'
      : '';
    var members = (ent.observed || []).map(function (o) {
      var st = o.live ? 'OPS' : (o.bft ? 'STALE' : 'ABS');
      var cls = o.live ? 'is-live' : (o.bft ? 'is-stale' : 'is-missing');
      var uid = o.bft && ow() ? ow().unitId(o.bft) : '';
      return '<button type="button" class="ow-orbat-member ' + cls + '"' +
        (uid ? ' data-orbat-bft="' + esc(uid) + '"' : '') + '>' +
        '<span>' + esc(o.member.label || '—') + '</span><strong>' + st + '</strong></button>';
    }).join('');
    var path = [];
    var cur = ent;
    while (cur) {
      path.unshift(cur.name);
      cur = cur.parent_id ? state.byId[cur.parent_id] : null;
    }
    var posLabel = ent.position
      ? (Math.round(ent.position.lat * 10000) / 10000) + ', ' + (Math.round(ent.position.lng * 10000) / 10000)
      : '—';
    return '' +
      '<div class="ow-orbat-insp">' +
      '<p class="ow-orbat-insp-type">' + esc((ent.echelon || ent.type || 'Unité').toUpperCase()) +
      ' · ' + esc(ent.side) + '</p>' +
      '<p class="ow-orbat-insp-status"><span class="ow-orbat-dot ' + statusDot(ent) + '"></span>' +
      (ent.gap.observed > 0 ? 'OPÉRATIONNEL' : (ent.gap.planned > 0 ? 'NON OBSERVÉ' : 'SANS EFFECTIF')) +
      '</p>' +
      gapBlock +
      '<div class="ow-drawer-block"><div class="ow-drawer-block-title">Identification</div>' +
      '<div class="ow-stat-grid">' +
      '<div class="ow-stat"><div class="ow-stat-k">Indicatif</div><div class="ow-stat-v">' + esc(ent.callsign || ent.name) + '</div></div>' +
      '<div class="ow-stat"><div class="ow-stat-k">ID BFT</div><div class="ow-stat-v is-mono">' + esc(ent.military_id || '—') + '</div></div>' +
      '<div class="ow-stat"><div class="ow-stat-k">Type</div><div class="ow-stat-v">' + esc(ent.type || '—') + '</div></div>' +
      '<div class="ow-stat"><div class="ow-stat-k">Rattachement</div><div class="ow-stat-v">' + esc(path.slice(0, -1).join(' / ') || '—') + '</div></div>' +
      '</div></div>' +
      '<div class="ow-drawer-block"><div class="ow-drawer-block-title">État</div>' +
      '<div class="ow-stat-grid">' +
      '<div class="ow-stat"><div class="ow-stat-k">Effectif</div><div class="ow-stat-v">' +
        gap.observed + ' / ' + gap.planned + '</div></div>' +
      '<div class="ow-stat"><div class="ow-stat-k">Chef</div><div class="ow-stat-v">' + esc(ent.commander.name || '—') + '</div></div>' +
      '<div class="ow-stat"><div class="ow-stat-k">Position</div><div class="ow-stat-v is-mono">' + esc(posLabel) + '</div></div>' +
      '<div class="ow-stat"><div class="ow-stat-k">Source</div><div class="ow-stat-v">' +
        (ent.position ? 'BFT' : 'ORBAT planifié') + '</div></div>' +
      '</div></div>' +
      (ent.mission ? '<div class="ow-drawer-block"><div class="ow-drawer-block-title">Mission</div>' +
        '<p class="ow-help">' + esc(ent.mission) + '</p></div>' : '') +
      '<div class="ow-drawer-block"><div class="ow-drawer-block-title">Personnel</div>' +
      (members || '<p class="ow-help">Aucun membre rattaché.</p>') +
      '</div>' +
      '<div class="ow-btn-row">' +
      '<button type="button" class="ow-primary" data-orbat-act="center">Centrer sur la carte</button>' +
      '<button type="button" class="ow-secondary" data-orbat-act="follow">Suivre</button>' +
      '</div>' +
      '<div class="ow-btn-row">' +
      '<a class="ow-secondary" href="' + esc(String(window.ATAK_API_BASE || '').replace(/\/$/, '') + '/orbat?unit=' + (ent.unitId || '')) +
      '">Ouvrir la fiche ORBAT</a>' +
      '</div>' +
      '</div>';
  }

  function selectEntity(id, opts) {
    opts = opts || {};
    var ent = state.byId[id];
    if (!ent) return;
    state.selectedId = id;
    syncSelectionHighlight();
    var beta = ow();
    if (beta && typeof beta.openDrawer === 'function') {
      beta.openDrawer('ORBAT · ' + ent.side, ent.name, inspectorHtml(ent));
      bindInspector();
    }
    if (opts.center !== false) focusEntityOnMap(ent);
    try {
      window.dispatchEvent(new CustomEvent('atak:entity-selected', {
        detail: { entity: ent, source: 'orbat' }
      }));
    } catch (e) {}
  }

  function bindInspector() {
    var body = document.getElementById('ow-drawer-body');
    if (!body) return;
    body.querySelectorAll('[data-orbat-bft]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var beta = ow();
        if (!beta) return;
        var id = btn.getAttribute('data-orbat-bft');
        var unit = (beta.getUnits() || []).find(function (u) { return beta.unitId(u) === id; });
        if (unit) beta.selectUnit(unit);
      });
    });
    body.querySelectorAll('[data-orbat-act]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ent = state.byId[state.selectedId];
        if (!ent) return;
        var act = btn.getAttribute('data-orbat-act');
        if (act === 'center') focusEntityOnMap(ent);
        if (act === 'follow') followEntity(ent);
      });
    });
  }

  function hideCtx() {
    var menu = document.getElementById('ow-orbat-ctx');
    if (menu) menu.hidden = true;
  }

  function showCtx(x, y, ent) {
    var menu = document.getElementById('ow-orbat-ctx');
    if (!menu || !ent) return;
    menu.dataset.orbatId = ent.id;
    menu.querySelector('[data-orbat-ctx-title]').textContent = ent.name;
    menu.hidden = false;
    var w = menu.offsetWidth || 220;
    var h = menu.offsetHeight || 280;
    var left = Math.min(x, window.innerWidth - w - 8);
    var top = Math.min(y, window.innerHeight - h - 8);
    menu.style.left = Math.max(8, left) + 'px';
    menu.style.top = Math.max(8, top) + 'px';
  }

  function runCtx(action, ent) {
    var beta = ow();
    hideCtx();
    if (!ent) return;
    if (action === 'open') selectEntity(ent.id, { center: false });
    if (action === 'center') { selectEntity(ent.id); focusEntityOnMap(ent); }
    if (action === 'follow') { selectEntity(ent.id, { center: true }); followEntity(ent); }
    if (action === 'fiche') {
      window.location.href = String(window.ATAK_API_BASE || '').replace(/\/$/, '') + '/orbat?unit=' + (ent.unitId || '');
    }
    if (action === 'sitrep' && beta && typeof beta.toast === 'function') {
      beta.toast('Demande SITREP — ' + ent.name + ' (canal Ordre).');
      if (typeof beta.openView === 'function') beta.openView('comms');
    }
    if (action === 'order' && beta && typeof beta.toast === 'function') {
      beta.toast('Création d’ordre pour ' + ent.name);
      if (typeof beta.openView === 'function') beta.openView('mission');
    }
    if (action === 'hide') {
      ent.visible = !ent.visible;
      if (typeof beta.toast === 'function') {
        beta.toast(ent.visible ? 'Unité visible sur la carte' : 'Unité masquée (filtre local)');
      }
    }
  }

  function setLeftMode(orbatOn) {
    var workspace = document.querySelector('.ow-workspace');
    var settingsBody = document.querySelector('.ow-settings-body');
    var panel = document.getElementById('ow-orbat-panel');
    var title = document.querySelector('.ow-settings .ow-aside-title');
    if (workspace) {
      workspace.classList.toggle('is-orbat', orbatOn);
      workspace.classList.remove('is-settings-collapsed');
    }
    if (settingsBody) settingsBody.hidden = !!orbatOn;
    if (panel) panel.hidden = !orbatOn;
    if (title) title.textContent = orbatOn ? 'ORBAT' : 'Réglages du poste';
  }

  function activate() {
    state.active = true;
    setLeftMode(true);
    document.querySelectorAll('.ow-nav button, .ow-more-menu button[data-view]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.view === 'orbat');
    });
    if (!state.roster && !state.loading) loadRoster();
    else {
      rebuildEntities();
      renderTree();
    }
    var beta = ow();
    if (beta && beta.map && typeof beta.map.invalidateSize === 'function') {
      setTimeout(function () { beta.map.invalidateSize(); }, 60);
    }
  }

  function deactivate() {
    if (!state.active) return;
    state.active = false;
    setLeftMode(false);
    hideCtx();
  }

  function onUnitsUpdated() {
    if (!state.roster) return;
    rebuildEntities();
    if (state.active) {
      renderTree();
      if (state.selectedId && state.byId[state.selectedId]) {
        var beta = ow();
        var drawer = document.getElementById('ow-drawer');
        var kicker = document.getElementById('ow-drawer-kicker');
        if (drawer && !drawer.hidden && kicker && String(kicker.textContent || '').indexOf('ORBAT') === 0) {
          if (beta && typeof beta.openDrawer === 'function') {
            var ent = state.byId[state.selectedId];
            beta.openDrawer('ORBAT · ' + ent.side, ent.name, inspectorHtml(ent));
            bindInspector();
          }
        }
      }
    }
  }

  function bindUi() {
    var panel = document.getElementById('ow-orbat-panel');
    if (!panel) return;

    panel.addEventListener('click', function (e) {
      var reload = e.target.closest('[data-orbat-reload]');
      if (reload) { loadRoster(); return; }
      var sum = e.target.closest('summary.ow-orbat-sum');
      if (sum) {
        var details = sum.parentElement;
        var id = details && details.getAttribute('data-orbat-id');
        // Let toggle happen; select on click without blocking collapse when chevron area
        if (id) {
          window.setTimeout(function () {
            state.collapsed[id] = !details.open;
            selectEntity(id);
          }, 0);
        }
      }
    });

    panel.addEventListener('contextmenu', function (e) {
      var node = e.target.closest('.ow-orbat-node');
      if (!node) return;
      e.preventDefault();
      e.stopPropagation();
      var id = node.getAttribute('data-orbat-id');
      var ent = state.byId[id];
      if (ent) showCtx(e.clientX, e.clientY, ent);
    });

    var search = document.getElementById('ow-orbat-search');
    if (search) {
      search.addEventListener('input', function () {
        state.query = String(search.value || '').trim().toLowerCase();
        renderTree();
      });
    }

    var expand = document.querySelector('[data-orbat-expand]');
    var collapse = document.querySelector('[data-orbat-collapse]');
    if (expand) expand.addEventListener('click', function () {
      state.entities.forEach(function (e) { state.collapsed[e.id] = false; });
      renderTree();
    });
    if (collapse) collapse.addEventListener('click', function () {
      state.entities.forEach(function (e) { state.collapsed[e.id] = true; });
      renderTree();
    });

    var menu = document.getElementById('ow-orbat-ctx');
    if (menu) {
      menu.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-orbat-ctx]');
        if (!btn) return;
        var ent = state.byId[menu.dataset.orbatId];
        runCtx(btn.getAttribute('data-orbat-ctx'), ent);
      });
    }
    document.addEventListener('click', function (e) {
      if (!e.target.closest('#ow-orbat-ctx')) hideCtx();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') hideCtx();
    });

    window.addEventListener('overwatch:units-updated', onUnitsUpdated);
  }

  // Patch openView once OverwatchBeta is ready
  function patchOpenView() {
    var beta = ow();
    if (!beta || typeof beta.openView !== 'function' || beta.openView.__orbatPatched) return;
    var orig = beta.openView;
    beta.openView = function (name) {
      if (name === 'orbat') {
        activate();
        return;
      }
      deactivate();
      return orig.apply(this, arguments);
    };
    beta.openView.__orbatPatched = true;
  }

  function boot() {
    bindUi();
    patchOpenView();
    // Retry patch if OverwatchBeta mounts slightly later
    var n = 0;
    var t = setInterval(function () {
      patchOpenView();
      n += 1;
      if ((ow() && ow().openView && ow().openView.__orbatPatched) || n > 40) clearInterval(t);
    }, 250);
  }

  window.OverwatchOrbat = {
    activate: activate,
    deactivate: deactivate,
    reload: loadRoster,
    getEntities: function () { return state.entities.slice(); },
    getEntity: function (id) { return state.byId[id] || null; },
    select: selectEntity
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
}());
