/*
 * « Sur mon téléphone » (/m/…) : comptes rendus, CR fin d'op, carte et briefs air de l'ATAK sur le vrai téléphone.
 * Écritures en POST (formulaire) avec l'en-tête X-CSRF-Token ; lectures GET. Ce qui est envoyé part dans l'ATAK
 * du joueur en jeu (avis Athena) ou dans les marqueurs du site, qui redescendent en jeu.
 */
(function () {
  'use strict';
  var body = document.body;
  var BASE = (body.getAttribute('data-base') || '').replace(/\/$/, '');
  var VIEW = body.getAttribute('data-view') || '';
  var meta = document.querySelector('meta[name="csrf-token"]');
  var CSRF = meta ? meta.getAttribute('content') : '';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function toast(msg) {
    var t = document.getElementById('mm-toast');
    if (!t) return;
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { t.hidden = true; }, 3200);
  }
  function getJSON(path) {
    var ctrl = typeof AbortController === 'function' ? new AbortController() : null;
    if (ctrl) setTimeout(function () { ctrl.abort(); }, 10000);
    return fetch(BASE + path, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }
  function post(path, params) {
    var data = params instanceof URLSearchParams ? params : new URLSearchParams(params || {});
    data.set('_csrf_token', CSRF);
    return fetch(BASE + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' },
      body: data
    }).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }
  function failText(j) {
    if (!j) return 'Envoi impossible : vérifiez la connexion du téléphone.';
    if (j.error === 'session' || j._status === 401) return 'Session terminée : régénérez le QR code dans le jeu.';
    if (j.error === 'csrf') return 'Page expirée : rechargez-la.';
    if (j.error === 'rate_limited') return 'Trop d’envois d’un coup : patientez une minute.';
    if (j.error === 'invalid') return 'Complétez les lignes en rouge.';
    return 'Envoi impossible, réessayez.';
  }
  function pad4(n) { n = Math.max(0, Math.floor(n)) % 10000; return ('0000' + n).slice(-4); }
  function grid(x, y) { return pad4(x / 10) + ' ' + pad4(y / 10); }

  // Choix en boutons (gros boutons, segments) : un seul allumé, valeur dans le champ caché du groupe.
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.mm-choice');
    if (!b) return;
    var group = b.closest('.mm-choices');
    if (!group) return;
    var on = !b.classList.contains('is-on');
    $all('.mm-choice', group).forEach(function (x) { x.classList.remove('is-on'); });
    var input = $('input[type="hidden"]', group);
    if (on || !input) b.classList.add('is-on');
    if (input) input.value = on ? b.getAttribute('data-choice') : '';
    var f = b.closest('.mm-field');
    if (f) f.classList.remove('is-bad');
  });

  var logout = $('[data-mm-logout]');
  if (logout) {
    logout.addEventListener('click', function () {
      if (!window.confirm('Déconnecter ce téléphone ? Il faudra scanner un nouveau QR code dans le jeu.')) return;
      post('/m/quitter').then(function () { window.location.reload(); });
    });
  }

  // « J'ai lu » sur un compte rendu reçu : accusé de lecture, comme dans l'ATAK en jeu.
  $all('[data-mm-read]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.disabled = true;
      post('/m/cr/lire/' + b.getAttribute('data-mm-read')).then(function (j) {
        if (j && j.ok) { b.outerHTML = '<p class="mm-state" data-st="acked">Lu ✓</p>'; return; }
        b.disabled = false;
        toast(failText(j));
      }).catch(function () { b.disabled = false; toast(failText(null)); });
    });
  });

  // Fil de messages : ouvert sur le plus récent.
  var thread = $('[data-thread]');
  if (thread) thread.scrollTop = thread.scrollHeight;

  // Suivi d'un envoi confié à l'ATAK du joueur.
  function follow(id, el) {
    var tries = 0;
    var labels = {
      pending: 'En attente de votre ATAK…',
      delivered: 'Reçu par votre ATAK…',
      acked: 'Pris en charge par votre ATAK ✓ Il l’envoie à Athena : retrouvez-le dans l’historique.',
      unknown: 'Envoi introuvable.'
    };
    (function tick() {
      getJSON('/m/api/suivi/' + id).then(function (j) {
        var st = (j && j.state) || 'pending';
        el.textContent = labels[st] || labels.pending;
        el.setAttribute('data-st', st);
        if (st === 'acked' || st === 'unknown') return;
        if (++tries > 90) { el.textContent = 'Votre ATAK ne l’a pas encore reçu : allumez le téléphone en jeu et vérifiez la liaison Athena.'; return; }
        setTimeout(tick, 3000);
      }).catch(function () { setTimeout(tick, 5000); });
    })();
  }

  // Carte : la carte Overwatch (atak-overwatch-mapkit.js), même fond, même repère et mêmes symboles.
  function makeMap(el) {
    var cfg = window.MM_MAP;
    if (!el || typeof L === 'undefined' || !cfg || !window.OverwatchMapKit) {
      if (el) el.innerHTML = '<p class="mm-muted mm-pad">Carte indisponible pour cette partie.</p>';
      return null;
    }
    var kit = window.OverwatchMapKit.create(el, cfg, { mobile: true, apiBase: BASE });
    setTimeout(function () { kit.map.invalidateSize(); kit.fitMinZoom(); }, 120);
    return kit;
  }

  /**
   * Calques de l'Overwatch sur la carte du téléphone, lus aux mêmes adresses que l'Overwatch (session du téléphone) :
   * amis, marqueurs du théâtre, tracés et zones, appareils, appuis 9-LINE / 5-LINE. Rafraîchis toutes les 5 s.
   * opts : { markerBind(layer, row, data), aircraft: () => [indicatifs de mon appareil], highlight(row), onData(state),
   *          tactical: style tactique proposé (atak-m-tactique.js), placing(): pose d'un marqueur en cours }
   */
  function mountMap(wrap, opts) {
    opts = opts || {};
    var kit = makeMap($('.mm-map', wrap));
    var Kit = window.OverwatchMapKit;
    var MAP_ID = Number(window.MM_MAP_ID || 1);
    var q = '?mapId=' + encodeURIComponent(MAP_ID);
    var chip = $('[data-my-grid]', wrap);
    var net = $('[data-map-net]', wrap);
    var panel = $('[data-layers-panel]', wrap);
    var layersBtn = $('[data-map-layers]', wrap);
    var fbtn = $('[data-map-full]', wrap);
    var state = { me: null, units: [], markers: [], shapes: [], air: [], cas: [], nine: [], pings: [], myAircraft: [] };
    var stores = { units: {}, markers: {}, air: {}, pings: {} };
    var shapeLayers = {};
    var shapeSig = '';
    var jtacLayer = null;
    var jtacSig = '';
    var centered = '';
    var lastOk = 0;
    var LAYER_KEY = 'mm-map-layers';
    var shown = { units: true, markers: true, shapes: true, air: true, jtac: true, pings: true };
    try { var saved = JSON.parse(sessionStorage.getItem(LAYER_KEY) || 'null'); if (saved) Object.keys(shown).forEach(function (k) { if (typeof saved[k] === 'boolean') shown[k] = saved[k]; }); } catch (e) {}
    $all('[data-layer]', wrap).forEach(function (box) {
      var k = box.getAttribute('data-layer');
      box.checked = shown[k] !== false;
      box.addEventListener('change', function () {
        shown[k] = box.checked;
        try { sessionStorage.setItem(LAYER_KEY, JSON.stringify(shown)); } catch (e) {}
        render();
      });
    });
    if (!kit) return null;
    var map = kit.map;
    var tac = opts.tactical && window.MMTactique ? window.MMTactique.create(kit, wrap, { grid: grid, placing: opts.placing }) : null;
    if (tac) {
      $all('[data-map-style]', wrap).forEach(function (radio) {
        radio.checked = radio.value === tac.name();
        radio.addEventListener('change', function () {
          if (!radio.checked) return;
          tac.setStyle(radio.value);
          // Autre habillage : joueurs, marqueurs et tracés refaits une fois, puis seulement ce qui change.
          kit.clearStore(stores.units);
          kit.clearStore(stores.markers);
          render();
        });
      });
    }

    function list(payload, key) {
      if (Array.isArray(payload)) return payload;
      if (payload && Array.isArray(payload[key])) return payload[key];
      if (payload && Array.isArray(payload.items)) return payload.items;
      return null;
    }
    // Lecture d'une adresse de l'Overwatch : null si refusée ou injoignable (on garde la dernière image).
    function read(path, key) {
      // Réseau du téléphone qui ne répond plus : abandon au bout de 10 s, sinon le rafraîchissement restait bloqué.
      var ctrl = typeof AbortController === 'function' ? new AbortController() : null;
      var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 10000) : 0;
      return fetch(BASE + path, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { clearTimeout(timer); return r; }, function (e) { clearTimeout(timer); throw e; })
        .then(function (r) {
          if (!r.ok) return { status: r.status, rows: null };
          return r.json().then(function (j) { return { status: r.status, rows: list(j, key) }; }, function () { return { status: r.status, rows: null }; });
        }, function () { return { status: 0, rows: null }; });
    }
    function clock(ts) { var d = new Date(ts); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
    function setNet(text, since) {
      net.hidden = !text;
      net.innerHTML = text ? '<span>' + esc(text) + '</span>' + (since ? ' <b translate="no">' + esc(since) + '</b>' : '') : '';
    }

    function isMyAircraft(a) {
      var cs = String(a.callsign || a.call_sign || '').trim().toLowerCase();
      return cs !== '' && state.myAircraft.some(function (m) { return String(m).trim().toLowerCase() === cs; });
    }
    function myAircraftRow() {
      return state.air.filter(isMyAircraft)[0] || null;
    }
    function myPoint() {
      var a = myAircraftRow();
      if (a && Number.isFinite(Number(a.pos_x)) && Number.isFinite(Number(a.pos_y))) return { x: Number(a.pos_x), y: Number(a.pos_y), grid: grid(Number(a.pos_x), Number(a.pos_y)), aircraft: a };
      return state.me;
    }
    function mySide() {
      var meId = state.me && state.me.id;
      var mine = meId ? state.units.filter(function (u) { return Number(u.id) === Number(meId); })[0] : null;
      return mine ? String(mine.side || '').toLowerCase() : '';
    }

    function renderUnits() {
      var seen = {};
      var side = mySide();
      if (shown.units) {
        state.units.forEach(function (u) {
          if (!u || Kit.tooOldToShow(u)) return;
          // Positions amies : mon camp (contacts hostiles ou d'un autre camp non tracés sur le téléphone).
          if (Kit.side(u) !== 'friendly') return;
          if (side && u.side && String(u.side).toLowerCase() !== side) return;
          var x = Number(u.pos_x != null ? u.pos_x : u.x);
          var y = Number(u.pos_y != null ? u.pos_y : u.y);
          if (!Number.isFinite(x) || !Number.isFinite(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return;
          var id = String(u.id || Kit.unitCallsign(u));
          var me = state.me && Number(state.me.id) > 0 && Number(u.id) === Number(state.me.id);
          var disc = Kit.isDisconnected(u);
          var color = disc ? '#8d9592' : (me ? '#f59e0b' : '#00d69a');
          var icon = kit.unitIcon({
            style: 'diamond',
            extra: (me ? ' is-me is-selected' : '') + (disc ? ' is-offline' : ''),
            stateColor: color,
            hint: disc ? 'Dernière position connue' : '',
            labelHtml: '<span class="ow-cs" translate="no">' + esc(Kit.unitCallsign(u)) + '</span>'
          });
          seen[id] = true;
          var ll = kit.worldToLatLng(x, y);
          if (tac && tac.isOn()) {
            tac.syncUnit(stores.units, id, u, ll, { me: !!me, disc: disc });
            return;
          }
          if (stores.units[id]) {
            stores.units[id].setLatLng(ll);
            stores.units[id].setIcon(icon);
          } else {
            stores.units[id] = L.marker(ll, { icon: icon, keyboard: false, zIndexOffset: me ? 700 : 300 }).addTo(map);
          }
        });
      }
      Object.keys(stores.units).forEach(function (id) {
        if (!seen[id]) { map.removeLayer(stores.units[id]); delete stores.units[id]; }
      });
    }
    function renderMarkers() {
      kit.syncArmaMarkers(stores.markers, state.markers, {
        enabled: function () { return shown.markers; },
        icon: tac && tac.isOn() ? tac.markerIcon : null,
        shape: tac && tac.isOn() ? tac.markerShape : null,
        bind: function (layer, id, title, row) {
          var data = Kit.parseMarkerData(row);
          var mine = Number(data.mobile_user || 0) > 0 && Number(data.mobile_user) === Number(window.MM_USER_ID || -1);
          if (layer._icon) layer._icon.classList.toggle('is-mine', mine);
          if (opts.markerBind) opts.markerBind(layer, row, data, mine);
        }
      });
    }
    function renderShapes() {
      var sig = shown.shapes ? (tac ? tac.name() : '') + JSON.stringify(state.shapes.map(function (s) { return [s.id, s.updated_at, s.geometry, s.meta, s.color, s.label]; })) : '';
      if (sig === shapeSig) return;
      shapeSig = sig;
      Object.keys(shapeLayers).forEach(function (id) { map.removeLayer(shapeLayers[id]); delete shapeLayers[id]; });
      if (!shown.shapes) return;
      state.shapes.forEach(function (shape) {
        var id = String(shape.id || shape.shape_uid || '');
        if (!id) return;
        var built = kit.shapeLayer(shape);
        if (!built) return;
        if (tac && tac.isOn()) {
          shapeLayers[id] = tac.styleShape(shape, built).addTo(map);
          return;
        }
        built.layer.addTo(map);
        kit.labelShape(shape, built);
        shapeLayers[id] = built.layer;
      });
    }
    function renderAir() {
      if (!shown.air) { kit.clearStore(stores.air); return; }
      kit.syncAirAssets(stores.air, state.air, {
        zIndexOffset: function (a) { return isMyAircraft(a) ? 800 : 500; },
        onClick: function (a) {
          var crew = [a.pilot, a.crew].filter(Boolean).join(' · ');
          L.popup({ autoPanPadding: [16, 80] }).setLatLng(kit.worldToLatLng(Number(a.pos_x), Number(a.pos_y)))
            .setContent('<strong translate="no">' + esc(a.callsign || a.call_sign || '') + '</strong><br><span translate="no">' + esc(a.model || a.aircraft_type || '') + '</span>' +
              (crew ? '<br><span>Équipage</span> <b translate="no">' + esc(crew) + '</b>' : '') +
              (a.alt != null ? '<br><span>Altitude</span> <b translate="no">' + esc(Math.round(Number(a.alt))) + ' m</b>' : '') +
              (a.heading != null ? '<br><span>Cap</span> <b translate="no">' + esc(('00' + Math.round(Number(a.heading))).slice(-3)) + '°</b>' : ''))
            .openOn(map);
        }
      });
      Object.keys(stores.air).forEach(function (id) {
        var row = state.air.filter(function (a) { return kit.airAssetId(a) === id; })[0];
        var el = stores.air[id] && stores.air[id]._icon;
        if (el) el.classList.toggle('mm-my-aircraft', !!(row && isMyAircraft(row)));
      });
    }
    function jtacRows() {
      var seen = {};
      return state.cas.concat(state.nine).filter(function (row) {
        var id = String((row && row.id) || '');
        if (!row || (id && seen[id])) return false;
        if (id) seen[id] = true;
        return true;
      });
    }
    function renderJtac() {
      var rows = jtacRows();
      var sig = shown.jtac ? Kit.jtacGeoSignature(rows) + '|' + (opts.highlightKey ? opts.highlightKey() : '') : '';
      if (jtacLayer && sig === jtacSig) return;
      if (jtacLayer) { map.removeLayer(jtacLayer); jtacLayer = null; }
      jtacSig = sig;
      if (!shown.jtac) return;
      jtacLayer = kit.jtacGeoLayer(rows, { filter: opts.jtacFilter, highlight: opts.highlight }).addTo(map);
    }
    function renderMe() {
      var p = myPoint();
      // Libellés et valeurs séparés : la page anglaise traduit le libellé, la grille et l'indicatif restent tels quels.
      chip.innerHTML = p
        ? (p.aircraft ? '<b translate="no">' + esc(p.aircraft.callsign || '') + '</b>' : '<span>' + esc('Ma position') + '</span>') + ' <b translate="no">' + esc(p.grid) + '</b>'
        : '<span>' + esc('Position en attente du jeu') + '</span>';
      // Centrage une fois : sur mon appareil dès qu'il est connu (pilote), sinon sur moi.
      var on = p ? (p.aircraft ? 'air' : 'me') : '';
      if (p && centered !== on && centered !== 'air') {
        map.setView(kit.worldToLatLng(p.x, p.y), Math.max(map.getZoom(), 4), { animate: false });
        centered = on;
      }
    }
    function render() {
      renderShapes();
      renderMarkers();
      renderUnits();
      renderAir();
      renderJtac();
      renderMe();
      if (tac) {
        tac.syncPings(stores.pings, state.pings, shown.pings);
        if (tac.isOn()) tac.declutter();
      }
      if (opts.onData) opts.onData(state);
    }

    var busy = false;
    function refresh() {
      if (busy) return Promise.resolve();
      busy = true;
      var me = getJSON('/m/api/carte').then(function (j) { return j; }, function () { return null; });
      return Promise.all([
        me,
        read('/api/units' + q, 'units'),
        read('/api/atak/markers' + q, 'markers'),
        read('/api/map-shapes' + q, 'shapes'),
        read('/api/atak/air-assets' + q, 'items'),
        read('/api/cas' + q, 'items'),
        read('/api/nine-line' + q, 'items'),
        opts.aircraft ? opts.aircraft() : Promise.resolve(null),
        Promise.resolve(null)
      ]).then(function (r) {
        busy = false;
        var j = r[0];
        if (j && j._status === 401) { setNet('Session terminée : régénérez le QR code dans le jeu.'); return; }
        var anyOk = !!(j && j.ok);
        if (anyOk) state.me = j.me || null;
        [['units', 1], ['markers', 2], ['shapes', 3], ['air', 4], ['cas', 5], ['nine', 6], ['pings', 8]].forEach(function (p) {
          var res = r[p[1]];
          if (res && res.rows) { state[p[0]] = res.rows; anyOk = true; }
        });
        if (Array.isArray(r[7])) state.myAircraft = r[7];
        if (anyOk) lastOk = Date.now();
        var offline = !anyOk || (typeof navigator !== 'undefined' && navigator.onLine === false);
        setNet(offline ? (lastOk ? 'Hors réseau, carte de' : 'Hors réseau') : '', offline && lastOk ? clock(lastOk) : '');
        render();
      }, function () { busy = false; });
    }

    // Outils : recentrer, calques, zoom, plein écran (Fullscreen API, repli CSS pour iOS, choix gardé en session).
    $('[data-map-me]', wrap).addEventListener('click', function () {
      var p = myPoint();
      if (p) map.setView(kit.worldToLatLng(p.x, p.y), Math.max(map.getZoom(), 5));
      else toast('Position inconnue : votre ATAK n’a pas encore envoyé de position.');
    });
    layersBtn.addEventListener('click', function () {
      panel.hidden = !panel.hidden;
      layersBtn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    });
    $all('[data-map-zoom]', wrap).forEach(function (b) {
      b.addEventListener('click', function () { map.setZoom(map.getZoom() + Number(b.getAttribute('data-map-zoom'))); });
    });
    var setFull = function (on, then) {
      wrap.classList.toggle('is-full', on);
      fbtn.textContent = on ? '✕' : '⤢';
      fbtn.setAttribute('aria-label', on ? 'Quitter le plein écran' : 'Plein écran');
      fbtn.setAttribute('title', on ? 'Quitter le plein écran' : 'Plein écran');
      fbtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      try { sessionStorage.setItem('mm-map-full-' + VIEW, on ? '1' : ''); } catch (e) {}
      setTimeout(function () { map.invalidateSize(); kit.fitMinZoom(); if (then) then(); }, 60);
    };
    var full = function (on, then) {
      if (on && wrap.requestFullscreen) wrap.requestFullscreen().catch(function () {});
      if (!on && document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {});
      setFull(on, then);
    };
    fbtn.addEventListener('click', function () { full(!wrap.classList.contains('is-full')); });
    document.addEventListener('fullscreenchange', function () { if (!document.fullscreenElement && wrap.classList.contains('is-full')) setFull(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (!panel.hidden) { panel.hidden = true; layersBtn.setAttribute('aria-expanded', 'false'); return; }
      if (wrap.classList.contains('is-full') && !document.fullscreenElement) setFull(false);
    });
    map.on('click', function () { if (!panel.hidden) { panel.hidden = true; layersBtn.setAttribute('aria-expanded', 'false'); } });
    window.addEventListener('resize', function () { map.invalidateSize(); });
    window.addEventListener('online', function () { refresh(); });
    window.addEventListener('offline', function () { setNet(lastOk ? 'Hors réseau, carte de' : 'Hors réseau', lastOk ? clock(lastOk) : ''); });
    try { if (sessionStorage.getItem('mm-map-full-' + VIEW)) setFull(true); } catch (e) {}

    refresh();
    setInterval(function () { if (!document.hidden) refresh(); }, 5000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });

    var ctl = {
      kit: kit,
      map: map,
      state: state,
      refresh: refresh,
      render: render,
      full: full,
      jtacRows: jtacRows,
      myPoint: myPoint,
      focus: function (x, y, z) { map.setView(kit.worldToLatLng(x, y), Math.max(map.getZoom(), z || 5), { animate: false }); }
    };
    wrap._mm = ctl; // recette (contrôle de la projection, des calques et des fenêtres)
    return ctl;
  }

  // ── Comptes rendus ───────────────────────────────────────────────────────
  if (VIEW === 'cr') {
    var pick = $('[data-cr-pick]');
    var sent = $('[data-cr-sent]');
    var current = null;
    var gridTarget = null;
    var pickMap = null;
    var show = function (code) {
      pick.hidden = !!code;
      sent.hidden = true;
      $all('[data-cr-form]').forEach(function (f) { f.hidden = f.getAttribute('data-cr-form') !== code; });
      current = code;
      window.scrollTo(0, 0);
    };
    $all('[data-cr-type]').forEach(function (b) {
      b.addEventListener('click', function () { show(b.getAttribute('data-cr-type')); });
    });
    $all('[data-cr-back]').forEach(function (b) { b.addEventListener('click', function () { show(null); }); });
    $('[data-cr-again]').addEventListener('click', function () { show(null); });
    $all('[data-grid-here]').forEach(function (b) {
      b.addEventListener('click', function () {
        var input = $('input', b.closest('.mm-field'));
        getJSON('/m/api/carte').then(function (j) {
          if (j && j.me && j.me.grid) { input.value = j.me.grid; toast('Position du jeu : ' + j.me.grid); } else { toast('Position inconnue : votre ATAK n’a pas encore envoyé de position.'); }
        });
      });
    });
    var sheet = $('[data-map-sheet]');
    $all('[data-grid-map]').forEach(function (b) {
      b.addEventListener('click', function () {
        gridTarget = $('input', b.closest('.mm-field'));
        sheet.hidden = false;
        if (!pickMap) {
          pickMap = makeMap(document.getElementById('mm-pick-map'));
          if (pickMap) {
            pickMap.map.on('click', function (e) {
              var w = pickMap.latLngToWorld(e.latlng);
              if (gridTarget) gridTarget.value = grid(w.x, w.y);
              sheet.hidden = true;
              toast('Grille ' + gridTarget.value);
            });
            getJSON('/m/api/carte').then(function (j) {
              if (j && j.me) {
                var ll = pickMap.worldToLatLng(j.me.x, j.me.y);
                pickMap.map.setView(ll, Math.max(pickMap.map.getZoom(), 4));
                L.circleMarker(ll, { radius: 8, color: '#f08a3c', weight: 3 }).addTo(pickMap.map);
              }
            }).catch(function () {});
          }
        } else {
          setTimeout(function () { pickMap.map.invalidateSize(); pickMap.fitMinZoom(); }, 80);
        }
      });
    });
    $('[data-map-close]').addEventListener('click', function () { sheet.hidden = true; });
    $all('[data-cr-form]').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var data = new URLSearchParams(new FormData(form));
        data.set('type', current || '');
        var btn = $('.mm-btn--send', form);
        btn.disabled = true;
        $all('.mm-field', form).forEach(function (f) { f.classList.remove('is-bad'); });
        post('/m/cr', data).then(function (j) {
          btn.disabled = false;
          if (!j.ok) {
            (j.fields || []).forEach(function (k) { var f = $('[data-field="' + k + '"]', form); if (f) f.classList.add('is-bad'); });
            toast(failText(j));
            return;
          }
          form.reset();
          $all('.mm-choice', form).forEach(function (x) { x.classList.remove('is-on'); });
          form.hidden = true;
          sent.hidden = false;
          follow(j.notice, $('[data-state]', sent));
        }).catch(function () { btn.disabled = false; toast(failText(null)); });
      });
    });
  }

  // ── CR fin d'op ──────────────────────────────────────────────────────────
  if (VIEW === 'compte_rendu') {
    var dform = $('[data-dbf-form]');
    if (dform) {
      dform.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var btn = $('.mm-btn--send', dform);
        btn.disabled = true;
        $all('.mm-field', dform).forEach(function (f) { f.classList.remove('is-bad'); });
        post('/m/compte-rendu', new URLSearchParams(new FormData(dform))).then(function (j) {
          btn.disabled = false;
          if (!j.ok) {
            (j.fields || []).forEach(function (k) { var f = $('[data-field="' + k + '"]', dform); if (f) f.classList.add('is-bad'); });
            toast(j.error === 'invalid' ? 'Répondez aux questions marquées d’une étoile.' : failText(j));
            return;
          }
          dform.hidden = true;
          var s = $('[data-dbf-sent]');
          s.hidden = false;
          follow(j.notice, $('[data-state]', s));
        }).catch(function () { btn.disabled = false; toast(failText(null)); });
      });
    }
  }

  // ── Carte ────────────────────────────────────────────────────────────────
  if (VIEW === 'carte') {
    var Kit = window.OverwatchMapKit;
    var mode = '';
    var editing = null;
    var pending = null;
    var msheet = $('[data-marker-sheet]');
    var hint = $('[data-map-hint]');
    var TYPE_KIND = { hd_dot: 'point', hd_destroy: 'ennemi', hd_warning: 'danger', hd_objective: 'objectif', hd_flag: 'ami', hd_pickup: 'lz' };
    var kindOfData = function (d) { return d.mobile_kind || TYPE_KIND[String(d.type || '').toLowerCase()] || 'point'; };
    var setKind = function (k) {
      $all('[data-marker-kinds] .mm-choice').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-choice') === k); });
    };
    var kindOf = function () { var on = $('[data-marker-kinds] .mm-choice.is-on'); return on ? on.getAttribute('data-choice') : 'point'; };
    var openSheet = function (m, x, y) {
      if (mm) mm.map.closePopup();
      editing = m;
      pending = { x: x, y: y };
      $('[data-marker-title]').textContent = m ? 'Mon marqueur' : 'Nouveau marqueur';
      $('[data-marker-text]').value = m ? (m.text || '') : '';
      setKind(m ? (m.kind || 'point') : 'point');
      $('[data-marker-grid]').textContent = 'Grille ' + grid(x, y);
      $('[data-marker-save]').textContent = m ? 'ENREGISTRER' : 'POSER';
      $('[data-marker-move]').hidden = !m;
      $('[data-marker-delete]').hidden = !m;
      msheet.hidden = false;
    };
    var closeSheet = function () { msheet.hidden = true; editing = null; };
    var mm = mountMap($('[data-mapwrap]'), {
      tactical: true,
      placing: function () { return !!mode; },
      // Mes marqueurs (posés depuis ce téléphone) : fiche de modification ; les autres : leur nom et leur grille.
      markerBind: function (layer, row, data, mine) {
        layer._mmRow = row;
        layer._mmMine = mine;
        if (layer._mmBound) return;
        layer._mmBound = true;
        layer.on('click', function (e) {
          L.DomEvent.stopPropagation(e);
          // Pose ou déplacement en cours : toucher un symbole place le marqueur à cet endroit.
          if (mode) { mm.map.fire('click', { latlng: e.latlng }); return; }
          var d = Kit.parseMarkerData(layer._mmRow);
          var w = Kit.markerWorld(d) || (window.ArmaMapMarkers && window.ArmaMapMarkers.parsePos(d));
          if (!w) return;
          if (layer._mmMine) {
            openSheet({ id: Number(layer._mmRow.id), text: String(d.text || d.label || ''), kind: kindOfData(d) }, w.x, w.y);
            return;
          }
          var name = window.ArmaMapMarkers && window.ArmaMapMarkers.displayLabelOf ? window.ArmaMapMarkers.displayLabelOf(d) : (d.text || d.label || 'Repère');
          var desc = String(d.description || '').trim();
          L.popup({ autoPanPadding: [16, 80] }).setLatLng(mm.kit.worldToLatLng(w.x, w.y))
            .setContent('<strong translate="no">' + esc(name || '') + '</strong>' + (desc ? '<br><span translate="no">' + esc(desc) + '</span>' : '') +
              (d.author ? '<br><small translate="no">' + esc(d.author) + '</small>' : '') + '<br><span>Grille</span> <b class="mm-mono" translate="no">' + esc(grid(w.x, w.y)) + '</b>')
            .openOn(mm.map);
        });
      }
    });
    if (mm) {
      mm.map.on('click', function (e) {
        var w = mm.kit.latLngToWorld(e.latlng);
        if (mode === 'add') { mode = ''; hint.hidden = true; openSheet(null, w.x, w.y); return; }
        if (!mode && !msheet.hidden) { closeSheet(); return; }
        if (mode === 'move' && editing) {
          mode = '';
          hint.hidden = true;
          post('/m/api/marqueurs/' + editing.id, { kind: editing.kind || 'point', text: editing.text || '', x: w.x, y: w.y }).then(function (j) {
            toast(j.ok ? 'Marqueur déplacé.' : failText(j));
            closeSheet();
            mm.refresh();
          }).catch(function () { toast(failText(null)); });
        }
      });
    }
    $('[data-map-add]').addEventListener('click', function () {
      if (!mm) return;
      mm.map.closePopup();
      mode = 'add';
      hint.hidden = false;
      closeSheet();
    });
    $('[data-map-cancel]').addEventListener('click', function () { mode = ''; hint.hidden = true; });
    $('[data-marker-close]').addEventListener('click', closeSheet);
    // Fermer aussi par Échap (fenêtre de marqueur, puis fenêtre de la carte) et en glissant vers le bas.
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (!msheet.hidden) { closeSheet(); return; }
      if (mode) { mode = ''; hint.hidden = true; return; }
      if (mm) mm.map.closePopup();
    });
    var y0 = null;
    msheet.addEventListener('touchstart', function (e) { y0 = msheet.scrollTop <= 0 ? e.touches[0].clientY : null; }, { passive: true });
    msheet.addEventListener('touchend', function (e) { if (y0 !== null && e.changedTouches[0].clientY - y0 > 80) closeSheet(); y0 = null; });
    $('[data-marker-move]').addEventListener('click', function () {
      if (!editing) return;
      editing.kind = kindOf();
      editing.text = $('[data-marker-text]').value;
      mode = 'move';
      msheet.hidden = true;
      hint.hidden = false;
    });
    $('[data-marker-save]').addEventListener('click', function () {
      if (!pending) return;
      var params = { kind: kindOf(), text: $('[data-marker-text]').value, x: pending.x, y: pending.y };
      var path = editing ? ('/m/api/marqueurs/' + editing.id) : '/m/api/marqueurs';
      var wasEditing = !!editing;
      post(path, params).then(function (j) {
        toast(j.ok ? (wasEditing ? 'Marqueur enregistré.' : 'Marqueur posé : il arrive dans les ATAK de la partie.') : failText(j));
        if (j.ok) { closeSheet(); if (mm) mm.refresh(); }
      }).catch(function () { toast(failText(null)); });
    });
    $('[data-marker-delete]').addEventListener('click', function () {
      if (!editing || !window.confirm('Retirer ce marqueur de la carte de la partie ?')) return;
      post('/m/api/marqueurs/' + editing.id + '/supprimer').then(function (j) {
        toast(j.ok ? 'Marqueur retiré.' : failText(j));
        closeSheet();
        if (mm) mm.refresh();
      }).catch(function () { toast(failText(null)); });
    });
  }

  // ── Briefs air (même boîte que l'espace BRIEFS AIR du téléphone en jeu) ──
  if (VIEW === 'briefs') {
    var LABELS = {
      '9line': ['IP / BP', 'Cap depuis l’IP', 'Distance', 'Altitude de la cible', 'Description de la cible', 'Position de la cible', 'Marquage', 'Amis', 'Dégagement'],
      '5line': ['Indicatif et ordre d’alerte', 'Position des amis', 'Position de la cible', 'Description et marquage de la cible', 'Remarques et restrictions'],
      medevac: ['Position de la LZ', 'Fréquence et indicatif', 'Patients par urgence', 'Équipement spécial', 'Patients (couchés / assis)', 'Sécurité de la LZ', 'Marquage de la LZ', 'Nationalité', 'NRBC et terrain']
    };
    var TITLES = { '9line': 'CAS 9-LINE', '5line': '5-LINE', medevac: 'MEDEVAC 9-LINE' };
    var STATES = { sent: 'ENVOYÉ', received: 'REÇU', read: 'LU', wilco: 'WILCO', unable: 'UNABLE', done: 'CLÔTURÉ' };
    var known = null;
    var briefs = [];
    var openId = 0;
    var list = $('[data-brief-list]');
    var bsheet = $('[data-brief-sheet]');
    var banner = $('[data-brief-banner]');
    var baseTitle = document.title;
    var aircraftList = Array.isArray(window.MM_AIRCRAFT) ? window.MM_AIRCRAFT : [];
    var mapBtn = $('[data-brief-map]');
    var unread = function (b) { return b.status === 'sent' || b.status === 'received'; };
    var briefIds = function () { var o = {}; briefs.forEach(function (b) { o[String(b.id)] = true; }); return o; };
    // Carte Overwatch en encart : mon appareil centré, cibles de mes briefs (IP/BP, cap, distance de risque), amis.
    var bmap = mountMap($('[data-mapwrap]'), {
      aircraft: function () { return Promise.resolve(aircraftList); },
      jtacFilter: function (row) { return !!briefIds()[String(row.id)]; },
      highlight: function (row) { return String(row.id) === String(openId); },
      highlightKey: function () { return Object.keys(briefIds()).join(',') + '#' + openId; },
      // Première image : mon appareil et la cible du dernier brief reçu dans la même vue.
      onData: function () {
        if (fitted || !bmap || !briefs.length) return;
        var p = bmap.myPoint();
        var t = targetOf(briefs.slice().sort(function (a, b) { return b.id - a.id; })[0].id);
        if (!p || !t) return;
        var k = bmap.kit;
        fitted = true;
        // Marges : la puce de position en haut, la barre d'outils en bas ne doivent pas cacher l'appareil ni la cible.
        bmap.map.fitBounds(L.latLngBounds([k.worldToLatLng(p.x, p.y), k.worldToLatLng(t.x, t.y)]), { animate: false, maxZoom: 5, paddingTopLeft: [40, 56], paddingBottomRight: [40, 84] });
      }
    });
    var fitted = false;
    // Le pilote a déjà déplacé la carte : plus de cadrage automatique.
    if (bmap) bmap.map.on('dragstart', function () { fitted = true; });
    var targetOf = function (id) {
      if (!bmap) return null;
      var row = bmap.jtacRows().filter(function (r) { return String(r.id) === String(id); })[0];
      var g = row ? window.OverwatchMapKit.rowGeo(row) : null;
      return g ? window.OverwatchMapKit.jtacGeoPoint(g.target) : null;
    };
    var render = function () {
      if (!briefs.length) { list.innerHTML = '<p class="mm-muted">Aucun brief reçu pour l’instant.</p>'; return; }
      var sorted = briefs.slice().sort(function (a, b) { return (unread(a) ? 0 : 1) - (unread(b) ? 0 : 1) || b.id - a.id; });
      var nUnread = sorted.filter(unread).length;
      var row = function (b) {
        var u = unread(b);
        var st = STATES[b.status] ? b.status : 'sent';
        return '<button type="button" class="mm-brief' + (u ? ' is-new' : '') + '" data-brief="' + b.id + '">' +
          '<strong>' + esc(TITLES[b.format] || 'BRIEF') + (u ? ' <em>NOUVEAU</em>' : '') + '</strong>' +
          '<span translate="no">' + esc(b.from) + ' → ' + esc(b.to) + '</span>' +
          '<small>' + esc(b.time) + '</small><b class="mm-ack ' + st + '">' + esc(STATES[st]) + '</b></button>';
      };
      list.innerHTML = (nUnread ? '<p class="mm-unread-h">' + esc('Non lus') + ' · ' + nUnread + '</p>' : '') +
        sorted.slice(0, nUnread).map(row).join('') +
        (nUnread && sorted.length > nUnread ? '<p class="mm-unread-h">' + esc('Déjà lus') + '</p>' : '') +
        sorted.slice(nUnread).map(row).join('');
    };
    var openBrief = function (id) {
      var b = briefs.filter(function (x) { return x.id === id; })[0];
      if (!b) return;
      openId = id;
      var labels = LABELS[b.format] || LABELS['9line'];
      $('[data-brief-title]').textContent = (TITLES[b.format] || 'BRIEF') + ' · ' + b.from;
      $('[data-brief-lines]').innerHTML = labels.map(function (l, i) {
        return '<li><span>' + esc(l) + '</span><b translate="no">' + esc(b.lines[i] || '—') + '</b></li>';
      }).join('') + (b.remarks ? '<li class="mm-lines__rem"><span>Remarques</span><b translate="no">' + esc(b.remarks) + '</b></li>' : '');
      var bst = unread(b) ? 'read' : (STATES[b.status] ? b.status : 'sent');
      $('[data-brief-state]').innerHTML = '<b class="mm-ack ' + bst + '">' + esc(STATES[bst]) + '</b>';
      mapBtn.hidden = !targetOf(id);
      bsheet.hidden = false;
      if (bmap) bmap.render();
      // Ouvert sur le téléphone : « lu » pour le JTAC (une seule fois).
      if (unread(b)) {
        b.status = 'read';
        post('/m/api/briefs/' + id + '/reponse', { answer: 'read' }).catch(function () {});
      }
      render();
    };
    list.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-brief]');
      if (b) openBrief(Number(b.getAttribute('data-brief')));
    });
    var closeBrief = function () { bsheet.hidden = true; openId = 0; if (bmap) bmap.render(); };
    $('[data-brief-close]').addEventListener('click', closeBrief);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !bsheet.hidden) closeBrief(); });
    mapBtn.addEventListener('click', function () {
      var t = targetOf(openId);
      fitted = true;
      bsheet.hidden = true;
      if (!bmap || !t) return;
      bmap.full(true, function () { bmap.focus(t.x, t.y, 4); });
    });
    var poll = function () {
      getJSON('/m/api/briefs').then(function (j) {
        if (!j || !j.ok) {
          if (j && j.error === 'not_aircrew') list.innerHTML = '<p class="mm-muted">Vous n’êtes plus à bord d’un appareil.</p>';
          if (j && (j.error === 'session' || j._status === 401)) list.innerHTML = '<p class="mm-muted">' + esc(failText(j)) + '</p>';
          return;
        }
        briefs = j.briefs || [];
        aircraftList = j.aircraft || [];
        var ids = briefs.map(function (b) { return b.id; });
        if (known !== null) {
          var fresh = ids.filter(function (id) { return known.indexOf(id) < 0; });
          if (fresh.length) {
            banner.hidden = false;
            document.title = '(' + fresh.length + ') ' + baseTitle;
            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
            setTimeout(function () { banner.hidden = true; document.title = baseTitle; }, 8000);
          }
        }
        known = ids;
        render();
        if (bmap) bmap.render();
      }).catch(function () {});
    };
    $all('[data-brief-answer]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!openId) return;
        var answer = btn.getAttribute('data-brief-answer');
        btn.disabled = true;
        post('/m/api/briefs/' + openId + '/reponse', { answer: answer.toLowerCase() }).then(function (j) {
          btn.disabled = false;
          $('[data-brief-state]').textContent = j.ok ? (answer + ' envoyé au JTAC ✓') : failText(j);
          if (j.ok) poll();
        }).catch(function () {
          btn.disabled = false;
          $('[data-brief-state]').textContent = failText(null);
        });
      });
    });
    banner.addEventListener('click', function () { banner.hidden = true; });
    poll();
    setInterval(function () { if (!document.hidden) poll(); }, 5000);
  }

  // ── Contrôle ID : « Joindre à une FRM » (brouillon de fiche à valider par le bureau SSE) ──
  if (VIEW === 'controle') {
    $all('[data-idc-frm]').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var id = form.getAttribute('data-idc-frm');
        var btn = $('button[type="submit"]', form);
        var state = $('[data-idc-state]', form);
        btn.disabled = true;
        post('/m/controle/' + encodeURIComponent(id) + '/frm', { remark: (form.elements.remark && form.elements.remark.value) || '' }).then(function (j) {
          if (j && j.ok) {
            state.textContent = 'Fiche ' + (j.ref || '') + ' : brouillon envoyé au bureau SSE, à valider.';
            state.setAttribute('data-st', 'acked');
            toast('Fiche ' + (j.ref || '') + ' créée');
            if (form.elements.remark) form.elements.remark.disabled = true;
          } else {
            btn.disabled = false;
            state.textContent = j && j.error === 'not_found' ? 'Ce contrôle n’est plus disponible.' : failText(j);
          }
        }, function () { btn.disabled = false; state.textContent = failText(null); });
      });
    });
  }

  // Musique : un seul morceau à la fois, puis le suivant. Sans script, chaque lecteur natif reste utilisable.
  if (VIEW === 'musique') {
    var tracks = $all('[data-mm-tracks] audio');
    tracks.forEach(function (audio, i) {
      audio.addEventListener('play', function () {
        tracks.forEach(function (other) { if (other !== audio) other.pause(); });
      });
      audio.addEventListener('ended', function () {
        var next = tracks[i + 1];
        if (!next) return;
        var started = next.play();
        if (started && started.catch) started.catch(function () {});
      });
      audio.addEventListener('error', function () {
        toast('Ce morceau ne peut pas être lu pour le moment.');
      });
    });
  }
})();
