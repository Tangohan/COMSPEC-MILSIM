/* Overwatch Beta — outils JTAC / C2 (SALUTE, 9-line, alertes, debrief, positions figées). */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || window.__OVERWATCH_C2__) return;
  window.__OVERWATCH_C2__ = true;

  var ALERT_KEY = 'athena:ow-c2-alerts';
  var lastPoNear = {};
  var lastRallyInside = {};
  var freezeTimer = null;

  function ow() {
    return window.OverwatchBeta || null;
  }

  function toast(msg) {
    var api = ow();
    if (api && api.toast) api.toast(msg);
  }

  function prefs() {
    try {
      return JSON.parse(localStorage.getItem(ALERT_KEY) || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function savePrefs(next) {
    try { localStorage.setItem(ALERT_KEY, JSON.stringify(next)); } catch (e1) {}
  }

  function alertsOn() {
    var box = document.getElementById('ow-c2-alerts');
    if (box) return !!box.checked;
    return prefs().on !== false;
  }

  function alertRadius() {
    var sel = document.getElementById('ow-c2-alert-radius');
    var n = Number((sel && sel.value) || prefs().radius || 250);
    return n > 0 ? n : 250;
  }

  function cueBeep() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.type = 'triangle';
      osc.frequency.value = 920;
      gain.gain.value = 0.07;
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      window.setTimeout(function () {
        try { osc.stop(); ctx.close(); } catch (e2) {}
      }, 160);
    } catch (e3) {}
  }

  function announce(text, key) {
    if (!alertsOn()) return;
    toast(text);
    cueBeep();
    try {
      if (document.hidden && window.Notification && Notification.permission === 'granted') {
        new Notification('Athena / Overwatch', { body: text, tag: key || 'ow-c2' });
      }
    } catch (e4) {}
  }

  function gridOf(ll) {
    var api = ow();
    if (!api || !ll) return '';
    var w = api.latLngToWorld(ll);
    return String(Math.round(w.x)).padStart(5, '0') + ' / ' + String(Math.round(w.y)).padStart(5, '0');
  }

  function bindPrefs() {
    var box = document.getElementById('ow-c2-alerts');
    var sel = document.getElementById('ow-c2-alert-radius');
    var stored = prefs();
    if (box) {
      box.checked = stored.on !== false;
      box.addEventListener('change', function () {
        savePrefs({ on: box.checked, radius: alertRadius() });
        if (box.checked && window.Notification && Notification.permission === 'default') {
          try { Notification.requestPermission(); } catch (e5) {}
        }
      });
    }
    if (sel) {
      if (stored.radius) sel.value = String(stored.radius);
      sel.addEventListener('change', function () {
        savePrefs({ on: alertsOn(), radius: alertRadius() });
      });
    }
  }

  function checkProximity() {
    var api = ow();
    if (!api || !alertsOn()) return;
    var units = api.getUnits ? api.getUnits() : [];
    var poRows = api.getPoRows ? api.getPoRows() : [];
    var rallyRows = api.getRallyRows ? api.getRallyRows() : [];
    var radius = alertRadius();
    var seenPo = {};
    poRows.forEach(function (row) {
      if (!row || row.reached) return;
      units.forEach(function (unit) {
        if (!api.side || api.side(unit) !== 'hostile') return;
        var w = api.unitWorld ? api.unitWorld(unit) : null;
        if (!w) return;
        var d = Math.hypot(w.x - Number(row.x), w.y - Number(row.y));
        if (d > radius) return;
        var key = String(row.id) + ':' + (api.unitId ? api.unitId(unit) : api.callsign(unit));
        seenPo[key] = true;
        if (lastPoNear[key]) return;
        lastPoNear[key] = true;
        announce(
          (api.callsign ? api.callsign(unit) : 'Contact') + ' à ' + Math.round(d) + ' m de ' + (row.label || 'l’objectif'),
          key
        );
      });
    });
    Object.keys(lastPoNear).forEach(function (key) {
      if (!seenPo[key]) delete lastPoNear[key];
    });
    var seenRally = {};
    rallyRows.forEach(function (row) {
      if (!row) return;
      var inside = [];
      units.forEach(function (unit) {
        if (!api.side || api.side(unit) !== 'friendly') return;
        var w = api.unitWorld ? api.unitWorld(unit) : null;
        if (!w) return;
        if (Math.hypot(w.x - Number(row.x), w.y - Number(row.y)) <= Number(row.radius || 50)) {
          inside.push(api.callsign ? api.callsign(unit) : 'opérateur');
        }
      });
      var id = String(row.id || row.label || '');
      seenRally[id] = true;
      var was = lastRallyInside[id] || 0;
      lastRallyInside[id] = inside.length;
      if (was === 0 && inside.length > 0) {
        announce(
          inside.length + ' opérateur' + (inside.length > 1 ? 's' : '') + ' au ralliement ' + (row.label || ''),
          'rally-' + id
        );
      }
    });
    Object.keys(lastRallyInside).forEach(function (id) {
      if (!seenRally[id]) delete lastRallyInside[id];
    });
  }

  function paintFreeze() {
    var el = document.getElementById('ow-freeze-banner');
    var api = ow();
    if (!el || !api) return;
    var last = api.getLastRx ? api.getLastRx() : 0;
    var units = api.getUnits ? api.getUnits() : [];
    var staleCount = 0;
    var oldest = 0;
    units.forEach(function (unit) {
      var age = api.unitAgeSec ? api.unitAgeSec(unit) : NaN;
      if (!Number.isFinite(age)) return;
      if (age >= 20) {
        staleCount += 1;
        if (age > oldest) oldest = age;
      }
    });
    var rxAge = last ? (Date.now() - last) / 1000 : 999;
    var frozen = rxAge >= 12 || staleCount > 0;
    if (!frozen) {
      el.hidden = true;
      return;
    }
    var bits = [];
    if (rxAge >= 12) bits.push('liaison jeu figée');
    if (staleCount) {
      var label = oldest >= 60 ? (Math.round(oldest / 60) + ' min') : (Math.round(oldest) + ' s');
      bits.push(staleCount + ' dernière' + (staleCount > 1 ? 's' : '') + ' position' + (staleCount > 1 ? 's' : '') + ' connue' + (staleCount > 1 ? 's' : '') + ' · ' + label);
    }
    el.hidden = false;
    el.textContent = 'Positions figées — ' + bits.join(' · ') + '. Ce n’est plus du temps réel.';
  }

  function openDebrief() {
    var api = ow();
    if (!api) return;
    var tenant = Number(window.ATAK_TENANT_ID || 0);
    var mission = 'mission_' + tenant + '_map_' + Number(api.mapId || window.ATAK_DEFAULT_MAP_ID || 1);
    var pdf = String(window.ATAK_API_BASE || '').replace(/\/$/, '') + '/api/replay/aar/' + encodeURIComponent(mission) + '/export.pdf';
    window.open(pdf, '_blank', 'noopener');
    toast('Bilan de mission ouvert. Imprimez aussi la carte annotée depuis Réglages si besoin.');
    var tl = document.getElementById('ow-timeline');
    if (tl) tl.hidden = false;
  }

  function applyClickGrid(grid) {
    if (!grid) return;
    var nine = document.querySelector('#ow-nine-form [name="line1"]');
    if (nine && !String(nine.value || '').trim()) nine.value = grid;
    var med = document.querySelector('#ow-medevac-form [name="pickup_grid"]');
    if (med && !String(med.value || '').trim()) med.value = grid;
    var loc = document.querySelector('#ow-salute-form [name="location"]');
    if (loc && !String(loc.value || '').trim()) loc.value = grid;
    var g = document.querySelector('#ow-salute-form [name="grid"]');
    if (g) g.value = grid;
  }

  function bindSalute(root) {
    var form = (root || document).querySelector('#ow-salute-form');
    if (!form || form.dataset.bound === '1') return;
    form.dataset.bound = '1';
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var api = ow();
      if (!api) return;
      var data = new FormData(form);
      var grid = String(data.get('grid') || data.get('location') || '').trim();
      var pos = null;
      if (api.getLastClickWorld) pos = api.getLastClickWorld();
      api.api('/api/atak/salute', {
        method: 'POST',
        body: {
          mapId: api.mapId,
          author: api.authorName,
          size: data.get('size'),
          activity: data.get('activity'),
          location: data.get('location'),
          unit: data.get('unit'),
          time: data.get('time'),
          equipment: data.get('equipment'),
          grid: grid,
          pos_x: pos && pos.x,
          pos_y: pos && pos.y
        }
      }).then(function () {
        if (pos && Number.isFinite(pos.x) && Number.isFinite(pos.y) && api.saveShape) {
          var ll = api.worldToLatLng(pos.x, pos.y);
          api.saveShape('POINT', [ll], 'SALUTE ' + (data.get('unit') || data.get('size') || 'contact'), {
            confirmed: true,
            color: '#e05b63',
            meta: { kind: 'salute' }
          });
        }
        toast('Compte rendu SALUTE transmis et posé sur les tracés.');
        form.reset();
        if (grid) {
          var g = form.querySelector('[name="grid"]');
          if (g) g.value = grid;
        }
      }).catch(function () {
        toast('Compte rendu refusé. Renseignez au moins un champ.');
      });
    });
  }

  function onMissionBound() {
    var api = ow();
    bindSalute(document.getElementById('ow-drawer'));
    applyClickGrid(api && api.getLastClickGrid ? api.getLastClickGrid() : '');
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindPrefs();
    paintFreeze();
    freezeTimer = window.setInterval(paintFreeze, 4000);
  });
  window.addEventListener('overwatch:units-updated', function () {
    checkProximity();
    paintFreeze();
  });
  window.addEventListener('overwatch:mission-bound', onMissionBound);
  document.addEventListener('click', function (event) {
    if (event.target && event.target.closest && event.target.closest('[data-ow-debrief]')) {
      openDebrief();
    }
  });

  window.OverwatchC2 = {
    gridOf: gridOf,
    applyClickGrid: applyClickGrid,
    openDebrief: openDebrief,
    bindSalute: bindSalute
  };
})();
