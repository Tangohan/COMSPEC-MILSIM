/* Tableau tactique global (COP) superposé à la Tacmap. */
window.ATAKCopBoard = (function () {
  'use strict';

  var FILTERS = [
    { id: 'all', label: 'Tous' },
    { id: 'inf', label: 'Infanterie' },
    { id: 'veh', label: 'Véhicules' },
    { id: 'air', label: 'Aérien' },
    { id: 'mov', label: 'En mouvement' },
    { id: 'stat', label: 'Immobiles' },
    { id: 'contact', label: 'En contact' },
    { id: 'wia', label: 'Blessés' },
    { id: 'nolos', label: 'Sans liaison' },
    { id: 'dest', label: 'Destination assignée' },
    { id: 'eta5', label: 'ETA < 5 min' }
  ];
  var filter = 'all';
  var sortKey = 'cs';
  var sortDir = 1;
  var root = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function extra(u) {
    try {
      if (typeof u.extra === 'string') return JSON.parse(u.extra || '{}') || {};
      if (u.extra && typeof u.extra === 'object') return u.extra;
    } catch (e) {}
    return {};
  }
  function catOf(u) {
    return String((u.motion && u.motion.category) || extra(u).platform || '').toUpperCase();
  }
  function isAir(u) {
    return window.ATAKMotion && window.ATAKMotion.isAir ? window.ATAKMotion.isAir(u) : (catOf(u) === 'HELICOPTER' || catOf(u) === 'FIXED_WING' || catOf(u) === 'UAV');
  }
  function typeShort(u) {
    var c = catOf(u);
    if (c === 'INFANTRY') return 'INF';
    if (c === 'GROUND_VEHICLE') return 'VEH';
    if (c === 'HELICOPTER') return 'HEL';
    if (c === 'FIXED_WING') return 'AIR';
    if (c === 'UAV') return 'UAV';
    return isAir(u) ? 'AIR' : '—';
  }
  function statusShort(u) {
    var s = String((u.motion && u.motion.status) || '').toUpperCase();
    if (s === 'MOVING') return 'MOV';
    if (s === 'STATIC') return 'ARR';
    if (s === 'FAST') return 'RAP';
    if (s === 'MANEUVERING') return 'MAN';
    return '—';
  }
  function live(u) {
    if (window.ATAKUnits && window.ATAKUnits.resolveLiveStatus) return window.ATAKUnits.resolveLiveStatus(u);
    return String(u.status || '').toLowerCase();
  }
  function rows() {
    var g = (window.ATAKUnits && window.ATAKUnits.getUnits) ? (window.ATAKUnits.getUnits() || []) : [];
    if (window.ATAKUnits && window.ATAKUnits.shouldHideEnemyAi) {
      g = g.filter(function (u) { return !window.ATAKUnits.shouldHideEnemyAi(u, g); });
    }
    var a = (window.ATAKAirAssets && window.ATAKAirAssets.getAssets) ? (window.ATAKAirAssets.getAssets() || []) : [];
    if (window.ATAKUnits && window.ATAKUnits.showEnemyAiEnabled && !window.ATAKUnits.showEnemyAiEnabled(g)) {
      a = a.filter(function (x) {
        return String((x && x.side) || 'WEST').toUpperCase() !== 'EAST';
      });
    }
    return g.concat(a);
  }
  function matchFilter(u) {
    var c = catOf(u);
    var st = String((u.motion && u.motion.status) || '').toUpperCase();
    var ex = extra(u);
    var asg = (window.ATAKMotion && window.ATAKMotion.assignmentOf) ? window.ATAKMotion.assignmentOf(u) : (u.navigation || u.assignment);
    var health = String(ex.health || u.health || '').toLowerCase();
    var l = live(u);
    if (filter === 'inf') return c === 'INFANTRY' || (!c && !isAir(u) && !ex.in_vehicle);
    if (filter === 'veh') return c === 'GROUND_VEHICLE' || !!ex.in_vehicle;
    if (filter === 'air') return isAir(u);
    if (filter === 'mov') return st === 'MOVING' || st === 'FAST' || st === 'MANEUVERING';
    if (filter === 'stat') return st === 'STATIC';
    if (filter === 'contact') return !!(u.operational && u.operational.combat && u.operational.combat.contact);
    if (filter === 'wia') return health === 'wounded' || health === 'injured' || health === 'unconscious' || health === 'critical' || health === 'cardiac_arrest';
    if (filter === 'nolos') return l === 'offline' || l === 'delayed';
    if (filter === 'dest') return !!(asg && asg.destination_label);
    if (filter === 'eta5') return !!(asg && asg.eta && asg.eta.seconds != null && asg.eta.seconds < 300 && !asg.eta.arrived);
    return true;
  }
  function sortVal(u, key) {
    var M = window.ATAKMotion;
    var asg = M ? M.assignmentOf(u) : null;
    var ex = extra(u);
    if (key === 'cs') return String(u.call_sign || u.callsign || '').toUpperCase();
    if (key === 'type') return typeShort(u);
    if (key === 'etat') return statusShort(u);
    if (key === 'cap') return Number(u.movement_heading || u.heading_object || u.heading || 0);
    if (key === 'spd') return Number((u.motion && u.motion.speed_current) || u.speed || 0);
    if (key === 'alt') return Number((u.air && u.air.altitude) || extra(u).asl_z || 0);
    if (key === 'eta') return (asg && asg.eta && asg.eta.seconds != null) ? asg.eta.seconds : 1e12;
    if (key === 'maj') return String(u.updated_at || '');
    return '';
  }
  function ago(iso) {
    if (!iso) return '—';
    var t = new Date(iso).getTime();
    if (isNaN(t)) return '—';
    var sec = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (sec < 60) return sec + ' s';
    return Math.floor(sec / 60) + ' min';
  }

  function render() {
    root = root || document.getElementById('atak-cop');
    if (!root || root.hidden) return;
    var body = document.getElementById('atak-cop-body');
    var chips = document.getElementById('atak-cop-filters');
    if (chips) {
      chips.innerHTML = FILTERS.map(function (f) {
        return '<button type="button" class="atak-cop__chip' + (f.id === filter ? ' is-active' : '') + '" data-cop-filter="' + f.id + '">' + esc(f.label) + '</button>';
      }).join('');
    }
    if (!body) return;
    var list = rows().filter(matchFilter);
    list.sort(function (a, b) {
      var va = sortVal(a, sortKey);
      var vb = sortVal(b, sortKey);
      if (va < vb) return -1 * sortDir;
      if (va > vb) return 1 * sortDir;
      return 0;
    });
    if (!list.length) {
      body.innerHTML = '<tr><td colspan="12" class="atak-cop__empty">Aucune unité pour ce filtre.</td></tr>';
      return;
    }
    var M = window.ATAKMotion;
    body.innerHTML = list.map(function (u) {
      var ex = extra(u);
      var asg = M ? M.assignmentOf(u) : null;
      var P = window.ATAKUnitPopup;
      var isPhone = P && P.isPhoneGeoloc ? P.isPhoneGeoloc(ex) : !!(ex.phone_geoloc);
      var rev = (isPhone && P && P.phoneReveal) ? P.phoneReveal(ex) : null;
      var cs = (P && P.unitDisplayName) ? P.unitDisplayName(u, ex) : ((isPhone && P && P.phoneDisplayName) ? P.phoneDisplayName(u, ex) : (u.call_sign || u.callsign || '—'));
      var cap = (!isPhone || (rev && rev.heading)) ? (M ? M.formatHeading(u.movement_heading || u.heading_object || u.heading) : '') : '';
      var spd = isPhone ? '' : (M ? M.formatSpeed(u) : '');
      var alt = (!isPhone || (rev && rev.altitude)) ? (M ? M.formatAlt(u) : '') : '';
      var n = isPhone ? '' : (extra(u).group_count || extra(u).crew_count || '');
      var eta = (isPhone || !asg || !asg.eta) ? '—' : (M ? M.formatEta(asg.eta.seconds, asg.eta.arrived) : '');
      var dest = isPhone ? '—' : (asg && asg.destination_label ? asg.destination_label : '—');
      var radio = isPhone ? '—' : ((u.source_arma && u.source_arma.radio_freq) || ex.radio_freq || '—');
      var contact = isPhone ? '—' : ((u.operational && u.operational.combat && u.operational.combat.contact) ? 'Oui' : 'Non');
      var typeCell = isPhone ? 'TEL' : typeShort(u);
      var statusCell = isPhone ? '—' : statusShort(u);
      var agoCell = (!isPhone || (rev && rev.updated)) ? ago(u.updated_at) : '—';
      var x = u.pos_x;
      var y = u.pos_y;
      return '<tr data-cop-cs="' + esc(u.call_sign || u.callsign || '') + '" data-cop-x="' + esc(x) + '" data-cop-y="' + esc(y) + '">'
        + '<td>' + esc(cs) + '</td><td>' + esc(typeCell) + '</td><td>' + esc(statusCell) + '</td>'
        + '<td>' + esc(n || '—') + '</td><td>' + esc(cap || '—') + '</td><td>' + esc(spd || '—') + '</td>'
        + '<td>' + esc(alt || '—') + '</td><td>' + esc(dest) + '</td><td>' + esc(eta) + '</td>'
        + '<td>' + esc(radio) + '</td><td>' + esc(contact) + '</td><td>' + esc(agoCell) + '</td></tr>';
    }).join('');
  }

  function open() {
    root = root || document.getElementById('atak-cop');
    if (!root) return;
    root.hidden = false;
    render();
  }
  function close() {
    root = root || document.getElementById('atak-cop');
    if (root) root.hidden = true;
  }
  function toggle() {
    root = root || document.getElementById('atak-cop');
    if (!root) return;
    if (root.hidden) open(); else close();
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) return;
    if (t.closest('[data-cop-open]')) {
      toggle();
      return;
    }
    if (t.closest('[data-cop-close]')) {
      close();
      return;
    }
    var f = t.closest('[data-cop-filter]');
    if (f) {
      filter = f.getAttribute('data-cop-filter') || 'all';
      render();
      return;
    }
    var th = t.closest('[data-cop-sort]');
    if (th) {
      var k = th.getAttribute('data-cop-sort');
      if (k === sortKey) sortDir *= -1;
      else { sortKey = k; sortDir = 1; }
      render();
    }
  });
  document.addEventListener('dblclick', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) return;
    var tr = t.closest('#atak-cop-body tr[data-cop-cs]');
    if (!tr) return;
    var x = parseFloat(tr.getAttribute('data-cop-x'));
    var y = parseFloat(tr.getAttribute('data-cop-y'));
    if (isFinite(x) && isFinite(y) && window.ATAKMap && window.ATAKMap.centerOn) {
      window.ATAKMap.centerOn(y, x);
      close();
    }
  });
  window.addEventListener('atak:units-updated', function () { render(); });
  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      root = root || document.getElementById('atak-cop');
      if (root && !root.hidden) close();
    }
  });

  return { open: open, close: close, toggle: toggle, render: render };
})();
