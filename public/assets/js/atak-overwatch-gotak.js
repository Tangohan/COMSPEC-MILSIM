/* Overwatch Beta — outils poste (OSINT, ETA, relief, météo, geofence, journal, satellites). */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || window.__OVERWATCH_GOTAK__) return;
  if (!document.getElementById('ow-map')) return;
  window.__OVERWATCH_GOTAK__ = true;

  var FENCE_KEY = 'athena:overwatch-geofence';
  var WX_KEY = 'athena:overwatch-weather-layer';
  var WALK_MS = 1.4;
  var VEHICLE_MS = 11;
  var insideMap = {};
  var replayGhosts = {};

  function ow() { return window.OverwatchBeta || null; }
  function toast(text) { var api = ow(); if (api) api.toast(text); }
  function esc(value) { var api = ow(); return api ? api.escapeHtml(value) : String(value == null ? '' : value); }
  function clean(value, fallback) { var api = ow(); return api ? api.clean(value, fallback) : (value || fallback || ''); }

  function pointInRing(ll, ring) {
    var x = ll.lng;
    var y = ll.lat;
    var inside = false;
    var i;
    var j = ring.length - 1;
    for (i = 0; i < ring.length; i += 1) {
      var xi = ring[i].lng;
      var yi = ring[i].lat;
      var xj = ring[j].lng;
      var yj = ring[j].lat;
      var intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / ((yj - yi) || 1e-12) + xi);
      if (intersect) inside = !inside;
      j = i;
    }
    return inside;
  }

  function aoiRings() {
    var api = ow();
    if (!api) return [];
    var out = [];
    var shapes = api.getShapes();
    var layers = api.getShapeLayers();
    shapes.forEach(function (shape) {
      var type = String(shape.type || '').toUpperCase();
      if (type !== 'AOI' && type !== 'POLYGON') return;
      var layer = layers[String(shape.id || shape.shape_uid || '')];
      if (!layer || typeof layer.getLatLngs !== 'function') return;
      var latlngs = layer.getLatLngs();
      var ring = Array.isArray(latlngs[0]) ? latlngs[0] : latlngs;
      if (ring && ring.length >= 3) out.push({ id: String(shape.id || shape.label || ''), label: shape.label || 'AOI', ring: ring });
    });
    return out;
  }

  function geofenceEnabled() {
    var box = document.getElementById('ow-geofence');
    return !box || box.checked;
  }

  function checkGeofence() {
    if (!geofenceEnabled()) return;
    var api = ow();
    if (!api) return;
    var aois = aoiRings();
    if (!aois.length) return;
    api.getUnits().forEach(function (unit) {
      var loc = api.point(unit);
      if (!loc) return;
      var uid = api.unitId(unit);
      if (!insideMap[uid]) insideMap[uid] = {};
      aois.forEach(function (aoi) {
        var now = pointInRing(loc, aoi.ring);
        var was = !!insideMap[uid][aoi.id];
        if (now && !was) toast(api.callsign(unit) + ' entre dans ' + (aoi.label || 'la zone'));
        if (!now && was) toast(api.callsign(unit) + ' sort de ' + (aoi.label || 'la zone'));
        insideMap[uid][aoi.id] = now;
      });
    });
  }

  function formatEta(meters, speed) {
    var seconds = meters / speed;
    if (seconds < 60) return Math.max(1, Math.round(seconds)) + ' s';
    var minutes = seconds / 60;
    if (minutes < 90) return Math.round(minutes) + ' min';
    return (Math.round(minutes / 6) / 10) + ' h';
  }

  function showEta(points) {
    var api = ow();
    if (!api || points.length < 2) return;
    var meters = 0;
    var i;
    for (i = 1; i < points.length; i += 1) meters += api.map.distance(points[i - 1], points[i]);
    var html = '<p class="ow-help">Distance relevée sur la carte. Vitesses indicatives : marche 5 km/h, véhicule 40 km/h.</p>' +
      '<div class="ow-event"><span>Distance</span><strong>' + Math.round(meters) + ' m</strong></div>' +
      '<div class="ow-event"><span>À pied</span><strong>' + formatEta(meters, WALK_MS) + '</strong></div>' +
      '<div class="ow-event"><span>Véhicule</span><strong>' + formatEta(meters, VEHICLE_MS) + '</strong></div>';
    api.openDrawer('MOUVEMENT', 'ETA', html);
    toast('ETA calculé : pied ' + formatEta(meters, WALK_MS) + ' · véhicule ' + formatEta(meters, VEHICLE_MS));
  }

  function sparkline(samples, payload) {
    var zs = samples.map(function (s) { return Number(s.z != null ? s.z : s.elevation); }).filter(function (z) { return !isNaN(z); });
    if (!zs.length) return '<p class="ow-help">Relief non relevé sur ce tronçon.</p>';
    var min = Math.min.apply(null, zs);
    var max = Math.max.apply(null, zs);
    var span = Math.max(1, max - min);
    var w = 280;
    var h = 72;
    var pts = zs.map(function (z, i) {
      var x = (i / Math.max(1, zs.length - 1)) * w;
      var y = h - ((z - min) / span) * (h - 8) - 4;
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    return '<svg class="ow-spark" viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true"><polyline fill="none" stroke="#00d69a" stroke-width="2" points="' + pts + '"/></svg>' +
      (payload && payload.distance_m != null ? '<div class="ow-event"><span>Distance</span><strong>' + (Number(payload.distance_m) >= 1000 ? (Number(payload.distance_m) / 1000).toFixed(2).replace('.', ',') + ' km' : Math.round(Number(payload.distance_m)) + ' m') + '</strong></div>' : '') +
      (payload && payload.climb_m != null ? '<div class="ow-event"><span>D+</span><strong>' + Math.round(Number(payload.climb_m)) + ' m</strong></div>' : '') +
      (payload && payload.descent_m != null ? '<div class="ow-event"><span>D−</span><strong>' + Math.round(Number(payload.descent_m)) + ' m</strong></div>' : '') +
      (payload && payload.max_slope_pct != null ? '<div class="ow-event"><span>Pente max</span><strong>' + String(payload.max_slope_pct).replace('.', ',') + ' %</strong></div>' : '') +
      '<div class="ow-event"><span>Altitude min</span><strong>' + Math.round(min) + ' m</strong></div>' +
      '<div class="ow-event"><span>Altitude max</span><strong>' + Math.round(max) + ' m</strong></div>';
  }

  function slopeColor(pct) {
    pct = Math.abs(Number(pct) || 0);
    if (pct >= 20) return '#e05b63';
    if (pct >= 10) return '#e7b14d';
    return '#00d69a';
  }

  function colorRouteBySlope(points, payload) {
    var api = ow();
    if (!api || !payload || !Array.isArray(payload.samples) || payload.samples.length < 2) return;
    var samples = payload.samples;
    var group = L.layerGroup();
    var i;
    for (i = 1; i < samples.length; i += 1) {
      var a = samples[i - 1];
      var b = samples[i];
      if (a.x == null || b.x == null) continue;
      var dz = (Number(b.z) || 0) - (Number(a.z) || 0);
      var dd = Math.max(1, (Number(b.d) || 0) - (Number(a.d) || 0));
      var pct = Math.abs(dz / dd) * 100;
      var line = L.polyline([
        api.worldToLatLng(Number(a.x), Number(a.y)),
        api.worldToLatLng(Number(b.x), Number(b.y))
      ], { color: slopeColor(pct), weight: 4, className: 'ow-profile-slope' });
      line.addTo(group);
    }
    group.addTo(api.map);
    if (api.registerScratch) api.registerScratch(group, 'shape', 'profile-' + Date.now(), 'Profil');
  }

  function showProfile(points) {
    var api = ow();
    if (!api || points.length < 2) return;
    var world = points.map(function (ll) { return api.latLngToWorld(ll); });
    api.openDrawer('RELIEF', 'PROFIL D’ÉLÉVATION', '<p class="ow-help">Calcul du profil sur le théâtre…</p>');
    api.api('/api/atak/terrain/profile', { method: 'POST', body: { mapId: api.mapId, points: world } }).then(function (payload) {
      if (!payload || payload.ready === false) {
        api.openDrawer('RELIEF', 'PROFIL D’ÉLÉVATION', '<p class="ow-help">' + esc(payload && (payload.gap_message || payload.message) || 'Relief non relevé.') + '</p>');
        return;
      }
      var samples = payload.samples || [];
      api.openDrawer('RELIEF', 'PROFIL D’ÉLÉVATION', sparkline(samples, payload));
      colorRouteBySlope(points, payload);
    }).catch(function () {
      api.openDrawer('RELIEF', 'PROFIL D’ÉLÉVATION', '<p class="ow-help">Relief non relevé.</p>');
    });
  }

  function segIntersect(p, q, r, s) {
    var d = (q.lng - p.lng) * (s.lat - r.lat) - (q.lat - p.lat) * (s.lng - r.lng);
    if (Math.abs(d) < 1e-12) return null;
    var t = ((r.lng - p.lng) * (s.lat - r.lat) - (r.lat - p.lat) * (s.lng - r.lng)) / d;
    var u = ((r.lng - p.lng) * (q.lat - p.lat) - (r.lat - p.lat) * (q.lng - p.lng)) / d;
    if (t < 0 || t > 1 || u < 0 || u > 1) return null;
    return L.latLng(p.lat + t * (q.lat - p.lat), p.lng + t * (q.lng - p.lng));
  }

  function splitAoi(parent, cut) {
    var api = ow();
    if (!api || !parent || cut.length < 2) return;
    var layers = api.getShapeLayers();
    var layer = layers[String(parent.id || parent.shape_uid || '')];
    if (!layer || typeof layer.getLatLngs !== 'function') {
      toast('Zone introuvable pour la coupe.');
      return;
    }
    var latlngs = layer.getLatLngs();
    var ring = (Array.isArray(latlngs[0]) ? latlngs[0] : latlngs).slice();
    if (ring.length > 1 && ring[0].equals && ring[0].equals(ring[ring.length - 1])) ring.pop();
    var a = cut[0];
    var b = cut[cut.length - 1];
    var hits = [];
    var i;
    for (i = 0; i < ring.length; i += 1) {
      var hit = segIntersect(ring[i], ring[(i + 1) % ring.length], a, b);
      if (hit) hits.push({ i: i, ll: hit });
    }
    if (hits.length < 2) {
      toast('La ligne de coupe doit traverser la zone.');
      return;
    }
    var h0 = hits[0];
    var h1 = hits[hits.length - 1];
    var left = [h0.ll];
    for (i = h0.i + 1; i <= h1.i; i += 1) left.push(ring[i]);
    left.push(h1.ll);
    var right = [h1.ll];
    for (i = h1.i + 1; i < ring.length; i += 1) right.push(ring[i]);
    for (i = 0; i <= h0.i; i += 1) right.push(ring[i]);
    right.push(h0.ll);
    var label = parent.label || 'AOI';
    api.saveShape('AOI', left, label + ' / A');
    api.saveShape('AOI', right, label + ' / B');
    toast('Zone découpée en deux sous-zones.');
  }

  function applyWeatherOverlay(weather) {
    var box = document.getElementById('ow-wx');
    var enabled = document.getElementById('ow-weather-layer');
    if (!box) return;
    if (enabled && !enabled.checked) { box.hidden = true; return; }
    if (!weather || (!weather.condition && weather.temperature_c == null && weather.wind_kph == null)) {
      box.hidden = true;
      return;
    }
    var bits = [];
    if (weather.condition) bits.push(weather.condition);
    if (weather.temperature_c != null && weather.temperature_c !== '') bits.push(weather.temperature_c + ' °C');
    if (weather.wind_kph != null && weather.wind_kph !== '') {
      bits.push('vent ' + weather.wind_kph + ' km/h' + (weather.wind_dir ? ' ' + weather.wind_dir : ''));
    }
    box.textContent = bits.join(' · ');
    box.hidden = bits.length === 0;
  }

  function openOsint() {
    var api = ow();
    if (!api) return;
    Promise.all([
      api.api('/api/sse/notes?limit=25').catch(function () { return { notes: [] }; }),
      api.api('/api/intel/fused').catch(function () { return []; })
    ]).then(function (rows) {
      var notes = api.asList(rows[0], 'notes');
      var fused = api.asList(rows[1], 'reports');
      var list = notes.map(function (note) {
        return '<div class="ow-card"><div class="ow-card-head"><span>' + esc(clean(note.title || note.reference_code, 'FICHE')) +
          '</span><span class="ow-tag">' + esc(clean(note.note_kind, 'SSE')) + '</span></div><div class="ow-card-body">' +
          esc(clean(note.body, '')) + '</div></div>';
      }).join('') + fused.slice(0, 8).map(function (row) {
        return '<div class="ow-event"><span>' + esc(clean(row.target_type || row.report_type, 'SIGNALEMENT')) +
          '</span><span class="ow-tag">' + esc(clean(row.status, '')) + '</span></div>';
      }).join('');
      var html = '<label class="ow-search"><span>⌕</span><input id="ow-osint-q" placeholder="Filtrer une fiche, un thème…"></label>' +
        (list || '<p class="ow-help">Aucune fiche de renseignement pour cette mission.</p>') +
        '<p class="ow-kicker">NOUVELLE FICHE</p>' +
        '<form class="ow-form-grid" id="ow-osint-form">' +
        '<label>Objet<input name="title" maxlength="120" placeholder="Titre court"></label>' +
        '<label>Thème<select name="theme"><option value="GENERAL">Général</option>' +
        '<option value="PERSON">Personnes / cibles</option><option value="INFRA">Infrastructures</option>' +
        '<option value="COMMS">Communications</option><option value="MOUV">Mouvements</option></select></label>' +
        '<label>Observation<textarea name="body" required placeholder="Ce qui a été vu, entendu ou recoupé…"></textarea></label>' +
        '<button class="ow-primary" type="submit">TRANSMETTRE AU BUREAU</button></form>';
      api.openDrawer('RENSEIGNEMENT', 'OSINT / SSE', html);
      var q = document.getElementById('ow-osint-q');
      if (q) q.addEventListener('input', function () {
        var needle = q.value.toLowerCase();
        document.querySelectorAll('#ow-drawer-body .ow-card').forEach(function (card) {
          card.hidden = needle !== '' && card.textContent.toLowerCase().indexOf(needle) === -1;
        });
      });
      var form = document.getElementById('ow-osint-form');
      if (form) form.addEventListener('submit', function (event) {
        event.preventDefault();
        var data = new FormData(form);
        api.api('/api/sse/notes/web?mapId=' + encodeURIComponent(api.mapId), {
          method: 'POST',
          body: {
            mapId: api.mapId,
            title: data.get('title'),
            body: data.get('body'),
            themes: [data.get('theme')],
            intel_source: 'OSINT',
            note_kind: 'FRM',
            author_label: api.authorName
          }
        }).then(function () {
          toast('Fiche transmise au bureau.');
          openOsint();
        }).catch(function () {
          toast('Fiche refusée. Vérifiez le thème et le texte.');
        });
      });
    });
  }

  function openLogs() {
    var api = ow();
    if (!api) return;
    api.api('/api/atak/activity?mapId=' + encodeURIComponent(api.mapId) + '&limit=40').then(function (payload) {
      var events = api.asList(payload, 'events');
      var html = events.map(function (row) {
        return '<div class="ow-event"><span>' + esc(clean(row.label || row.message || row.type, 'ÉVÉNEMENT')) +
          '</span><small>' + esc(clean(row.at || row.created_at, '')) + '</small></div>';
      }).join('') || '<p class="ow-help">Aucun événement de mission pour le moment.</p>';
      api.openDrawer('MISSION', 'JOURNAL', html);
    }).catch(function () {
      api.openDrawer('MISSION', 'JOURNAL', '<p class="ow-help">Journal indisponible pour le moment.</p>');
    });
  }

  function openCalcs() {
    var api = ow();
    if (!api) return;
    var rows = api.getUnits();
    var groups = {};
    rows.forEach(function (unit) {
      var key = api.squadKey ? api.squadKey(unit) : '';
      var loc = api.point(unit);
      if (!key || !loc) return;
      if (!groups[key]) groups[key] = { label: api.clean(unit.fire_team_label || unit.group_name || unit.group, 'GROUPE'), pts: [] };
      groups[key].pts.push(loc);
    });
    var html = '<p class="ow-help">Distances et dispersion calculées à partir des positions transmises. Rien n’est inventé si le cap ou la vitesse manque.</p>';
    var keys = Object.keys(groups);
    if (!keys.length) html += '<p class="ow-help">Aucun groupe localisé pour le moment.</p>';
    keys.forEach(function (key) {
      var pack = groups[key];
      var span = 0;
      pack.pts.forEach(function (a) {
        pack.pts.forEach(function (b) { span = Math.max(span, api.map.distance(a, b)); });
      });
      html += '<div class="ow-event"><span>' + esc(pack.label) + ' · ' + pack.pts.length + '</span><strong>disp. ' +
        (api.formatMeters ? api.formatMeters(span) : Math.round(span) + ' m') + '</strong></div>';
    });
    html += '<p class="ow-kicker">Outils</p>' +
      '<div class="ow-event"><span>Cap / distance</span><button type="button" class="ow-tag" data-tool-goto="bearing">Tracer</button></div>' +
      '<div class="ow-event"><span>Cercle</span><button type="button" class="ow-tag" data-tool-goto="circle">Tracer</button></div>' +
      '<div class="ow-event"><span>Rectangle</span><button type="button" class="ow-tag" data-tool-goto="rect">Tracer</button></div>' +
      '<div class="ow-event"><span>Aller à une grille</span><button type="button" class="ow-tag" data-tool-goto="goto">Ouvrir</button></div>' +
      '<div class="ow-event"><span>Anneaux de portée</span><button type="button" class="ow-tag" data-tool-goto="range">Poser</button></div>' +
      '<div class="ow-event"><span>Visée / masque</span><button type="button" class="ow-tag" data-tool-goto="los">Tracer</button></div>' +
      '<div class="ow-event"><span>Temps de parcours</span><button type="button" class="ow-tag" data-tool-goto="eta">Tracer</button></div>';
    api.openDrawer('Calcul', 'Mesures live', html);
    document.querySelectorAll('#ow-drawer-body [data-tool-goto]').forEach(function (button) {
      button.addEventListener('click', function () { api.setTool(button.getAttribute('data-tool-goto')); });
    });
  }

  function openIntercept() {
    var api = ow();
    if (!api) return;
    var rows = api.getUnits().filter(function (unit) { return api.point(unit); });
    if (rows.length < 2) {
      api.openDrawer('Mouvement', 'Interception', '<p class="ow-help">Il faut au moins deux contacts localisés.</p>');
      return;
    }
    var opts = rows.map(function (unit) {
      return '<option value="' + esc(api.unitId(unit)) + '">' + esc(api.callsign(unit)) + '</option>';
    }).join('');
    var html = '<p class="ow-help">Cap et distance entre deux contacts déjà en liaison. La vitesse n’apparaît que si elle a été transmise.</p>' +
      '<form class="ow-form-grid" id="ow-intercept-form">' +
      '<label>Premier contact<span class="ow-select"><select name="a">' + opts + '</select></span></label>' +
      '<label>Second contact<span class="ow-select"><select name="b">' + opts + '</select></span></label>' +
      '<button class="ow-primary" type="submit">Relever</button></form><div id="ow-intercept-out"></div>';
    api.openDrawer('Mouvement', 'Interception', html);
    var form = document.getElementById('ow-intercept-form');
    if (!form) return;
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var aId = form.querySelector('[name="a"]').value;
      var bId = form.querySelector('[name="b"]').value;
      var a = rows.filter(function (u) { return api.unitId(u) === aId; })[0];
      var b = rows.filter(function (u) { return api.unitId(u) === bId; })[0];
      var out = document.getElementById('ow-intercept-out');
      if (!a || !b || aId === bId) {
        if (out) out.innerHTML = '<p class="ow-help">Choisissez deux contacts distincts.</p>';
        return;
      }
      var la = api.point(a);
      var lb = api.point(b);
      var meters = api.map.distance(la, lb);
      var cap = Math.round((function () {
        var wa = api.latLngToWorld(la);
        var wb = api.latLngToWorld(lb);
        var deg = Math.atan2(wb.x - wa.x, wb.y - wa.y) * 180 / Math.PI;
        return (deg + 360) % 360;
      })());
      var extraA = (function () {
        var raw = a.extra;
        if (typeof raw === 'string') { try { raw = JSON.parse(raw); } catch (e3) { raw = {}; } }
        return raw && typeof raw === 'object' ? raw : {};
      })();
      var spd = Number(extraA.speed_ms);
      var eta = isFinite(spd) && spd > 0.2 ? Math.round(meters / spd) + ' s à la vitesse actuelle du premier' : 'Vitesse du premier inconnue';
      if (out) {
        out.innerHTML = '<div class="ow-event"><span>Distance</span><strong>' + (api.formatMeters ? api.formatMeters(meters) : Math.round(meters) + ' m') + '</strong></div>' +
          '<div class="ow-event"><span>Cap</span><strong>' + cap + '°</strong></div>' +
          '<div class="ow-event"><span>Temps</span><strong>' + esc(eta) + '</strong></div>';
      }
      if (window.__owInterceptLine) {
        try { api.map.removeLayer(window.__owInterceptLine); } catch (e4) {}
      }
      window.__owInterceptLine = L.polyline([la, lb], { color: '#5b8def', weight: 2, dashArray: '6 4' }).addTo(api.map);
      if (api.registerScratch) api.registerScratch(window.__owInterceptLine, 'intercept', 'intercept', 'Interception');
      else if (api.bindLayerContext) api.bindLayerContext(window.__owInterceptLine, 'intercept', 'intercept', 'Interception');
    });
  }

  function openQrf() {
    var api = ow();
    if (!api) return;
    var html = '<p class="ow-help">Posez un anneau de rassemblement de 500 m autour du contact ouvert, ou cliquez la carte avec l’outil Anneaux.</p>' +
      '<button type="button" class="ow-primary" id="ow-qrf-go">Anneau 500 m sur le contact</button>';
    api.openDrawer('Mouvement', 'Rassemblement', html);
    var btn = document.getElementById('ow-qrf-go');
    if (btn) btn.addEventListener('click', function () {
      var units = api.getUnits();
      var selectedId = document.querySelector('#ow-contact-list [data-unit-id].is-active, #ow-drawer-body [data-unit-id]');
      var unit = units[0];
      if (selectedId) {
        var id = selectedId.getAttribute('data-unit-id');
        unit = units.filter(function (row) { return api.unitId(row) === id; })[0] || unit;
      }
    var loc = (typeof api.getSelected === 'function' && api.getSelected() && api.point(api.getSelected())) || (unit && api.point(unit));
      if (!loc) { toast('Aucun contact localisé.'); return; }
      if (window.OverwatchTools) window.OverwatchTools.placeRange(loc, [250, 500, 1000]);
    });
  }

  function openPanel(name) {
    if (name === 'osint') openOsint();
    if (name === 'sats') {
      if (window.OverwatchBeta && typeof window.OverwatchBeta.openView === 'function') {
        window.OverwatchBeta.openView('network');
        return;
      }
      try {
        var btn = document.querySelector('.ow-nav [data-view="network"]');
        if (btn) { btn.click(); return; }
      } catch (e) {}
      toast('Aucun catalogue satellitaire n’est fourni. Rien n’est inventé.');
      return;
    }
    if (name === 'logs') openLogs();
    if (name === 'calcs') openCalcs();
    if (name === 'intercept') openIntercept();
    if (name === 'qrf') openQrf();
    if (name === 'notes' && window.OverwatchTools) window.OverwatchTools.openNotes();
    if (name === 'goto' && window.OverwatchTools) window.OverwatchTools.openGoto();
  }

  function clearReplayGhosts() {
    var api = ow();
    Object.keys(replayGhosts).forEach(function (id) {
      if (api && replayGhosts[id]) api.map.removeLayer(replayGhosts[id]);
      delete replayGhosts[id];
    });
  }

  function applyReplay(pct) {
    var source = document.getElementById('ow-replay-source');
    if (source && source.value === 'mission') return;
    var api = ow();
    if (!api) return;
    var live = document.getElementById('ow-replay-live');
    var samples = api.getTrackSamples();
    var ids = Object.keys(samples);
    if (pct >= 99) {
      if (live) live.textContent = 'LIVE';
      clearReplayGhosts();
      api.renderMap();
      try { window.dispatchEvent(new CustomEvent('overwatch:replay', { detail: { live: true, pct: pct } })); } catch (e0) {}
      return;
    }
    if (!ids.length) {
      toast('Aucun déplacement enregistré pour le moment.');
      return;
    }
    if (live) live.textContent = 'REPLAY';
    var minT = Infinity;
    var maxT = 0;
    ids.forEach(function (id) {
      samples[id].forEach(function (row) {
        if (row.t < minT) minT = row.t;
        if (row.t > maxT) maxT = row.t;
      });
    });
    var target = minT + ((maxT - minT) * pct / 100);
    var markers = api.getMarkers();
    ids.forEach(function (id) {
      var rows = samples[id];
      var chosen = rows[0];
      rows.forEach(function (row) { if (row.t <= target) chosen = row; });
      if (markers[id]) markers[id].setLatLng(chosen.ll);
    });
    var nowEl = document.getElementById('ow-replay-now');
    if (nowEl && isFinite(target)) {
      var d = new Date(target);
      nowEl.textContent = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    try { window.dispatchEvent(new CustomEvent('overwatch:replay', { detail: { live: false, pct: pct, t: target } })); } catch (e1) {}
  }

  function bindPrefs() {
    var fence = document.getElementById('ow-geofence');
    var wx = document.getElementById('ow-weather-layer');
    try {
      if (fence && localStorage.getItem(FENCE_KEY) === '0') fence.checked = false;
      if (wx && localStorage.getItem(WX_KEY) === '0') wx.checked = false;
    } catch (e) {}
    if (fence) fence.addEventListener('change', function () {
      try { localStorage.setItem(FENCE_KEY, fence.checked ? '1' : '0'); } catch (e2) {}
    });
    if (wx) wx.addEventListener('change', function () {
      try { localStorage.setItem(WX_KEY, wx.checked ? '1' : '0'); } catch (e2) {}
      var box = document.getElementById('ow-wx');
      if (box && !wx.checked) box.hidden = true;
    });
  }

  function ready() {
    if (!ow()) {
      window.setTimeout(ready, 40);
      return;
    }
    bindPrefs();
    window.addEventListener('overwatch:units-updated', checkGeofence);
    window.addEventListener('overwatch:weather', function (event) { applyWeatherOverlay(event.detail); });
    window.addEventListener('overwatch:panel', function (event) { openPanel(event.detail && event.detail.panel); });
    window.addEventListener('overwatch:draft-finish', function (event) {
      var detail = event.detail || {};
      if (detail.tool === 'eta') showEta(detail.points || []);
      if (detail.tool === 'profile') showProfile(detail.points || []);
      if (detail.tool === 'split') splitAoi(detail.parent, detail.points || []);
    });
    document.addEventListener('click', function (event) {
      var panel = event.target.closest('[data-ow-panel]');
      if (panel) openPanel(panel.getAttribute('data-ow-panel'));
    });
    var scrub = document.getElementById('ow-replay-scrub');
    if (scrub) {
      scrub.addEventListener('input', function () { applyReplay(Number(scrub.value) || 0); });
    }
    document.addEventListener('click', function (event) {
      if (!event.target.closest('[data-ow-replay]')) return;
      window.setTimeout(function () {
        var bar = document.getElementById('ow-timeline');
        if (bar && !bar.hidden && scrub) applyReplay(Number(scrub.value) || 100);
      }, 0);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
  else ready();
}());
