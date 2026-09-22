/* Overwatch Beta — actions réelles (formulaires, calques, relais, géo, replay). */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || window.__OVERWATCH_OPS__) return;
  window.__OVERWATCH_OPS__ = true;

  var lookLayers = [];
  var predictLayers = [];
  var progressLayers = [];
  var relayLayers = [];
  var dfLayers = [];
  var sitrepPins = [];
  var geoNet = null;
  var geoGroup = null;
  var lastGeoKey = '';
  var relays = [];
  var sitreps = [];
  var replayTimer = null;
  var replayPlaying = false;
  var missionFrames = [];
  var missionGhosts = {};
  var pendingShape = null;
  var pendingPreview = null;
  var pendingMarker = null;
  var recents = [];
  var markerIntelLayers = [];
  
  // Config réalisme centralisée (chargée au démarrage)
  var realismConfig = null;
  var MARKER_SYMBOLS = [
    { key: 'mil_dot', group: 'Repères', short: 'Repère', label: 'Repère', hint: 'Point simple, vu à cet endroit.', noun: 'Ce repère', kind: 'static', warnMin: 40, staleMin: 120 },
    { key: 'mil_triangle', group: 'Repères', short: 'Triangle', label: 'Triangle', hint: 'Repère triangulaire, souvent un contact ou un axe.', noun: 'Ce repère', kind: 'static', warnMin: 40, staleMin: 120 },
    { key: 'mil_box', group: 'Repères', short: 'Carré', label: 'Carré', hint: 'Zone ou bâtiment signalé.', noun: 'Ce carré', kind: 'static', warnMin: 40, staleMin: 120 },
    { key: 'mil_circle', group: 'Repères', short: 'Cercle', label: 'Cercle', hint: 'Point d’intérêt ou rassemblement.', noun: 'Ce cercle', kind: 'static', warnMin: 40, staleMin: 120 },
    { key: 'mil_flag', group: 'Repères', short: 'Drapeau', label: 'Drapeau', hint: 'Position tenue ou à marquer.', noun: 'Ce drapeau', kind: 'static', warnMin: 50, staleMin: 180 },
    { key: 'mil_objective', group: 'Repères', short: 'Objectif', label: 'Objectif', hint: 'But de manœuvre encore pertinent plus longtemps.', noun: 'Cet objectif', kind: 'static', warnMin: 60, staleMin: 240 },
    { key: 'mil_warning', group: 'Repères', short: 'Alerte', label: 'Alerte', hint: 'Danger signalé : mines, embuscade, zone interdite.', noun: 'Cette alerte', kind: 'static', warnMin: 15, staleMin: 45 },
    { key: 'mil_destroy', group: 'Repères', short: 'Destruction', label: 'Destruction', hint: 'Cible à détruire ou déjà détruite.', noun: 'Cette destruction', kind: 'static', warnMin: 30, staleMin: 90 },
    { key: 'mil_ambush', group: 'Repères', short: 'Embuscade', label: 'Embuscade', hint: 'Dispositif d’embuscade vu ou prévu.', noun: 'Cette embuscade', kind: 'infantry', warnMin: 10, staleMin: 25, speedKmh: 4 },
    { key: 'mil_join', group: 'Repères', short: 'Ralliement', label: 'Ralliement', hint: 'Point de regroupement.', noun: 'Ce ralliement', kind: 'static', warnMin: 30, staleMin: 90 },
    { key: 'mil_start', group: 'Repères', short: 'Départ', label: 'Départ', hint: 'Point de départ d’un mouvement.', noun: 'Ce départ', kind: 'static', warnMin: 40, staleMin: 120 },
    { key: 'mil_end', group: 'Repères', short: 'Arrivée', label: 'Arrivée', hint: 'Point d’arrivée prévu.', noun: 'Cette arrivée', kind: 'static', warnMin: 40, staleMin: 120 },
    { key: 'hd_dot', group: 'Repères', short: 'Croquis', label: 'Repère au crayon', hint: 'Annotation à la main, moins formelle.', noun: 'Ce croquis', kind: 'static', warnMin: 30, staleMin: 90 },
    { key: 'b_inf', group: 'Unités amies', short: 'Infanterie', label: 'Infanterie amie', hint: 'Groupe à pied ami vu ici.', noun: 'Cette infanterie amie', kind: 'infantry', warnMin: 10, staleMin: 25, speedKmh: 5 },
    { key: 'b_motor_inf', group: 'Unités amies', short: 'Motorisée', label: 'Infanterie motorisée amie', hint: 'Infanterie amie montée sur véhicules légers.', noun: 'Cette infanterie motorisée amie', kind: 'vehicle', warnMin: 12, staleMin: 30, speedKmh: 40 },
    { key: 'b_mech_inf', group: 'Unités amies', short: 'Mécanisée', label: 'Infanterie mécanisée amie', hint: 'Infanterie amie sous blindés de transport.', noun: 'Cette infanterie mécanisée amie', kind: 'vehicle', warnMin: 12, staleMin: 30, speedKmh: 30 },
    { key: 'b_armor', group: 'Unités amies', short: 'Blindé', label: 'Blindé ami', hint: 'Char ou engin blindé ami.', noun: 'Ce blindé ami', kind: 'vehicle', warnMin: 12, staleMin: 35, speedKmh: 25 },
    { key: 'b_recon', group: 'Unités amies', short: 'Recon', label: 'Reconnaissance amie', hint: 'Élément de reconnaissance ami, souvent mobile.', noun: 'Cette reconnaissance amie', kind: 'infantry', warnMin: 8, staleMin: 20, speedKmh: 15 },
    { key: 'b_air', group: 'Unités amies', short: 'Hélico', label: 'Hélicoptère ami', hint: 'Voilure tournante amie vue au-dessus de ce point.', noun: 'Cet hélicoptère ami', kind: 'air', warnMin: 4, staleMin: 12, speedKmh: 120 },
    { key: 'o_inf', group: 'Unités hostiles', short: 'Infanterie', label: 'Infanterie hostile', hint: 'Groupe à pied adverse vu ici. Il peut déjà avoir bougé.', noun: 'Cette infanterie hostile', kind: 'infantry', warnMin: 8, staleMin: 20, speedKmh: 4 },
    { key: 'o_motor_inf', group: 'Unités hostiles', short: 'Motorisée', label: 'Infanterie motorisée hostile', hint: 'Infanterie adverse montée, capable de quitter vite la zone.', noun: 'Cette infanterie motorisée hostile', kind: 'vehicle', warnMin: 10, staleMin: 25, speedKmh: 40 },
    { key: 'o_mech_inf', group: 'Unités hostiles', short: 'Mécanisée', label: 'Infanterie mécanisée hostile', hint: 'Infanterie adverse sous VCI.', noun: 'Cette infanterie mécanisée hostile', kind: 'vehicle', warnMin: 10, staleMin: 25, speedKmh: 30 },
    { key: 'o_armor', group: 'Unités hostiles', short: 'Blindé', label: 'Blindé hostile', hint: 'Char ou engin blindé adverse.', noun: 'Ce blindé hostile', kind: 'vehicle', warnMin: 12, staleMin: 30, speedKmh: 25 },
    { key: 'o_recon', group: 'Unités hostiles', short: 'Recon', label: 'Reconnaissance hostile', hint: 'Éclaireurs adverses, rarement immobiles longtemps.', noun: 'Cette reconnaissance hostile', kind: 'infantry', warnMin: 6, staleMin: 16, speedKmh: 15 },
    { key: 'o_air', group: 'Unités hostiles', short: 'Hélico', label: 'Hélicoptère hostile', hint: 'Voilure tournante adverse. Le point vieillit très vite.', noun: 'Cet hélicoptère hostile', kind: 'air', warnMin: 3, staleMin: 8, speedKmh: 140 },
    { key: 'n_inf', group: 'Unités inconnues', short: 'Infanterie', label: 'Infanterie inconnue', hint: 'Groupe à pied dont le camp n’est pas tranché.', noun: 'Cette infanterie', kind: 'infantry', warnMin: 8, staleMin: 20, speedKmh: 4 },
    { key: 'n_armor', group: 'Unités inconnues', short: 'Blindé', label: 'Blindé inconnu', hint: 'Engin blindé d’appartenance incertaine.', noun: 'Ce blindé', kind: 'vehicle', warnMin: 12, staleMin: 30, speedKmh: 25 },
    { key: 'n_recon', group: 'Unités inconnues', short: 'Recon', label: 'Reconnaissance inconnue', hint: 'Élément mobile non identifié.', noun: 'Cette reconnaissance', kind: 'infantry', warnMin: 6, staleMin: 16, speedKmh: 15 },
    { key: 'loc_hospital', group: 'Lieux', short: 'Médical', label: 'Poste médical', hint: 'Point santé, en principe fixe.', noun: 'Ce poste médical', kind: 'static', warnMin: 90, staleMin: 360 }
  ];

  function ow() { return window.OverwatchBeta || null; }
  function esc(v) { var api = ow(); return api ? api.escapeHtml(v) : String(v == null ? '' : v); }
  function toast(t) { var api = ow(); if (api) api.toast(t); }
  
  // Charger config réalisme depuis API (utilisée pour fallbacks)
  function loadRealismConfig() {
    if (typeof window.AtakRealismConfig !== 'undefined') {
      window.AtakRealismConfig.load().then(function (config) {
        realismConfig = config;
        console.log('[OverwatchOps] Realism config loaded');
      }).catch(function (err) {
        console.warn('[OverwatchOps] Failed to load realism config:', err);
      });
    }
  }
  
  function missionId() {
    return 'mission_' + Number(window.ATAK_TENANT_ID || 0) + '_map_' + Number((ow() && ow().mapId) || window.ATAK_DEFAULT_MAP_ID || 1);
  }
  function remember(kind, label, id) {
    recents = [{ kind: kind, label: String(label || kind), id: String(id || ''), t: Date.now() }].concat(recents).slice(0, 8);
    try { localStorage.setItem('athena:ow-layer-recents', JSON.stringify(recents)); } catch (e) {}
  }
  function loadRecents() {
    try {
      var raw = JSON.parse(localStorage.getItem('athena:ow-layer-recents') || '[]');
      if (Array.isArray(raw)) recents = raw;
    } catch (e) {}
  }

  function circleByRadius(center, radius, steps) {
    var api = ow();
    if (!api) return [];
    var w = api.latLngToWorld(center);
    var edge = api.worldToLatLng(w.x + radius, w.y);
    var out = [];
    var n = steps || 36;
    var i;
    for (i = 0; i < n; i += 1) {
      var ang = (i / n) * Math.PI * 2;
      out.push(api.worldToLatLng(w.x + Math.sin(ang) * radius, w.y + Math.cos(ang) * radius));
    }
    if (!out.length) out.push(edge);
    return out;
  }

  function headingPoint(ll, heading, meters) {
    var api = ow();
    if (!api || heading == null) return null;
    var w = api.latLngToWorld(ll);
    var rad = (Number(heading) * Math.PI) / 180;
    return api.worldToLatLng(w.x + Math.sin(rad) * meters, w.y + Math.cos(rad) * meters);
  }

  function clearGroup(arr) {
    var api = ow();
    arr.forEach(function (layer) {
      if (api && layer) try { api.map.removeLayer(layer); } catch (e) {}
    });
    arr.length = 0;
  }

  function afterRenderMap() {
    var api = ow();
    if (!api) return;
    renderLookAndPredict();
    renderProgressTrail();
    renderFollowChip();
    renderRelays();
    renderDf();
    renderMarkerIntel();
    injectHatch();
  }

  function headingArrowShape(api, loc, heading, shaftPx) {
    if (!api || !api.map || heading == null || !isFinite(Number(heading))) return null;
    var probe = headingPoint(loc, heading, 80);
    if (!probe) return null;
    var origin = api.map.latLngToLayerPoint(loc);
    var p1 = api.map.latLngToLayerPoint(probe);
    var dx = p1.x - origin.x;
    var dy = p1.y - origin.y;
    var hyp = Math.sqrt(dx * dx + dy * dy);
    if (hyp < 0.8) return null;
    var ux = dx / hyp;
    var uy = dy / hyp;
    var start = L.point(origin.x + ux * 10, origin.y + uy * 10);
    var tip = L.point(origin.x + ux * shaftPx, origin.y + uy * shaftPx);
    var left = L.point(tip.x - ux * 10 + uy * 5.5, tip.y - uy * 10 - ux * 5.5);
    var right = L.point(tip.x - ux * 10 - uy * 5.5, tip.y - uy * 10 + ux * 5.5);
    return {
      shaft: [api.map.layerPointToLatLng(start), api.map.layerPointToLatLng(tip)],
      head: [api.map.layerPointToLatLng(left), api.map.layerPointToLatLng(tip), api.map.layerPointToLatLng(right)],
      tip: api.map.layerPointToLatLng(tip)
    };
  }

  function motionSpeedMs(speed) {
    if (speed == null || !isFinite(Number(speed)) || speed < 0.2) return null;
    var n = Number(speed);
    if (n > 90 && n < 420) n = n / 3.6;
    return n;
  }

  function renderLookAndPredict() {
    var api = ow();
    if (!api) return;
    clearGroup(lookLayers);
    clearGroup(predictLayers);
    var lookOn = document.getElementById('ow-look-arrow');
    var predOn = document.getElementById('ow-predict');
    if ((!lookOn || !lookOn.checked) && (!predOn || !predOn.checked)) return;
    var selected = api.getSelected && api.getSelected();
    var list = predOn && predOn.checked ? (api.getUnits ? api.getUnits() : []) : (selected ? [selected] : []);
    if (lookOn && lookOn.checked && selected && list.indexOf(selected) < 0) list = [selected].concat(list);
    list.forEach(function (unit) {
      if (!unit) return;
      var loc = api.point(unit);
      if (!loc) return;
      var heading = api.unitHeading ? api.unitHeading(unit) : null;
      if (heading == null || !isFinite(Number(heading))) return;
      var lookStart = loc;
      if (lookOn && lookOn.checked && selected && unit === selected) {
        var shape = headingArrowShape(api, loc, heading, 34);
        if (shape) {
          lookStart = shape.tip;
          lookLayers.push(L.polyline(shape.shaft, {
            color: '#e8fff4', weight: 3, opacity: 0.95, lineCap: 'round',
            className: 'ow-look-arrow', pane: 'markerPane', interactive: false
          }).addTo(api.map));
          lookLayers.push(L.polygon(shape.head, {
            color: '#e8fff4', fillColor: '#e8fff4', fillOpacity: 0.95, weight: 1,
            className: 'ow-look-head', pane: 'markerPane', interactive: false
          }).addTo(api.map));
        }
      }
      if (!(predOn && predOn.checked)) return;
      var speed = motionSpeedMs(api.unitSpeed ? api.unitSpeed(unit) : null);
      if (speed == null) return;
      var delayed = String(unit.status || '').toLowerCase() === 'delayed';
      var dist = Math.min(delayed ? 140 : 280, speed * (delayed ? 8 : 18));
      if (dist < 14) return;
      var end = headingPoint(loc, heading, dist);
      if (!end) return;
      predictLayers.push(L.polyline([lookStart, end], {
        color: '#7eb0ff', weight: 2, dashArray: '5 7', opacity: 0.75, lineCap: 'round',
        className: 'ow-predict-line', pane: 'markerPane', interactive: false
      }).addTo(api.map));
      predictLayers.push(L.circleMarker(end, {
        radius: 3.5, color: '#7eb0ff', fillColor: '#0b1a2c', fillOpacity: 0.7, weight: 1.5,
        className: 'ow-predict-dot', pane: 'markerPane', interactive: false
      }).addTo(api.map));
    });
  }

  function renderProgressTrail() {
    var api = ow();
    if (!api) return;
    clearGroup(progressLayers);
    var box = document.getElementById('ow-progress-trail');
    if (!box || !box.checked) return;
    var unit = api.getSelected && api.getSelected();
    if (!unit || !api.unitId) return;
    var samples = ((api.getTrackSamples && api.getTrackSamples()) || {})[api.unitId(unit)] || [];
    if (samples.length < 2) return;
    var pts = samples.map(function (row) { return row.ll; }).filter(Boolean);
    if (pts.length < 2) return;
    progressLayers.push(L.polyline(pts, {
      color: '#9dffc8', weight: 3, opacity: 0.82, lineCap: 'round', lineJoin: 'round',
      className: 'ow-progress-trail', pane: 'overlayPane', interactive: false
    }).addTo(api.map));
  }

  function renderFollowChip() {
    var chip = document.getElementById('ow-follow-chip');
    var label = document.getElementById('ow-follow-label');
    var api = ow();
    if (!chip || !api) return;
    var sel = api.getSelected && api.getSelected();
    var follow = document.getElementById('ow-follow');
    var on = !!(follow && follow.checked && sel);
    chip.hidden = !on;
    if (on && label) {
      var squad = api.squadKey ? api.squadKey(sel) : '';
      var mates = squad ? (api.getUnits() || []).filter(function (u) { return api.squadKey(u) === squad; }) : [];
      label.textContent = (mates.length > 1 && api.group)
        ? ('Suivi · groupe ' + api.group(sel))
        : ('Suivi · ' + (api.callsign ? api.callsign(sel) : 'contact'));
    }
  }

  function markerSymbol(key) {
    var i;
    for (i = 0; i < MARKER_SYMBOLS.length; i += 1) {
      if (MARKER_SYMBOLS[i].key === key) return MARKER_SYMBOLS[i];
    }
    return MARKER_SYMBOLS[0];
  }

  function markerColor(key) {
    if (String(key).indexOf('o_') === 0) return 'ColorEAST';
    if (String(key).indexOf('n_') === 0) return 'ColorGUER';
    if (String(key).indexOf('b_') === 0) return 'ColorWEST';
    return 'ColorGreen';
  }

  function isHostileMarker(data, spec) {
    var aff = String((data && (data.affiliation || data.side)) || '').toLowerCase();
    if (aff === 'hostile' || aff === 'enemy' || aff === 'opfor' || aff === 'east') return true;
    var color = String((data && data.color) || '').toLowerCase();
    if (color === 'coloreast' || color === 'colorred' || color.indexOf('east') >= 0) return true;
    var type = String((data && data.type) || (spec && spec.key) || '').toLowerCase();
    if (type.indexOf('o_') === 0) return true;
    if (spec && spec.group === 'Unités hostiles') return true;
    var helper = window.ArmaMapMarkers;
    if (helper && typeof helper.decodeType === 'function') {
      var decoded = helper.decodeType(data || { type: type });
      if (decoded && decoded.affiliation === 'hostile') return true;
    }
    return false;
  }

  function markerThumb(key) {
    var helper = window.ArmaMapMarkers;
    if (helper && helper.buildIconSpec) {
      var spec = helper.buildIconSpec({ type: key, color: markerColor(key), label: '', text: '' });
      if (spec && spec.html) return '<span class="ow-marker-thumb">' + spec.html + '</span>';
    }
    return '<span class="ow-marker-thumb ow-marker-thumb-empty">●</span>';
  }

  function markerGroups() {
    var groups = [];
    var seen = {};
    MARKER_SYMBOLS.forEach(function (item) {
      if (!seen[item.group]) {
        seen[item.group] = true;
        groups.push(item.group);
      }
    });
    return groups;
  }

  function markerPickerHtml(selected) {
    var html = '<div class="ow-marker-board">';
    markerGroups().forEach(function (group) {
      html += '<p class="ow-marker-group">' + esc(group) + '</p><div class="ow-marker-picker">';
      MARKER_SYMBOLS.filter(function (item) { return item.group === group; }).forEach(function (item) {
        html += '<button type="button" class="ow-marker-pick' + (selected === item.key ? ' is-on' : '') + '" data-ow-mtype="' + esc(item.key) + '" title="' + esc(item.label) + '">' +
          markerThumb(item.key) + '<span>' + esc(item.short) + '</span></button>';
      });
      html += '</div>';
    });
    html += '<p class="ow-marker-group">Liste</p><div class="ow-marker-list" role="list">';
    MARKER_SYMBOLS.forEach(function (item) {
      html += '<button type="button" class="ow-marker-list-row' + (selected === item.key ? ' is-on' : '') + '" data-ow-mtype="' + esc(item.key) + '" role="listitem">' +
        markerThumb(item.key) +
        '<span><strong>' + esc(item.label) + '</strong><small>' + esc(item.hint) + '</small></span></button>';
    });
    html += '</div>';
    var cur = markerSymbol(selected);
    html += '<p class="ow-marker-hint" id="ow-marker-hint">' + esc(cur.hint) + '</p></div>';
    return html;
  }

  function syncMarkerPick(key) {
    pendingMarker.type = key || 'mil_dot';
    var cur = markerSymbol(pendingMarker.type);
    document.querySelectorAll('[data-ow-mtype]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-ow-mtype') === pendingMarker.type);
    });
    var hint = document.getElementById('ow-marker-hint');
    if (hint) hint.textContent = cur.hint;
    var speed = document.querySelector('#ow-marker-form [name="speed_kmh"]');
    if (speed && !speed.dataset.touched) speed.value = cur.speedKmh || '';
  }

  function toggleMarkerMoveFields(form) {
    var movement = String(new FormData(form).get('movement') || 'still');
    var move = form.querySelector('#ow-marker-move-fields');
    var shuttle = form.querySelector('#ow-marker-shuttle-fields');
    if (move) move.hidden = movement === 'still';
    if (shuttle) shuttle.hidden = movement !== 'shuttle';
  }

  function promptMarker(ll) {
    var api = ow();
    if (!api) return Promise.resolve();
    pendingMarker = { ll: ll, type: 'mil_dot' };
    var w = api.latLngToWorld(ll);
    var first = markerSymbol('mil_dot');
    api.openDrawer('Repère', 'Marqueur du théâtre',
      '<p class="ow-help">Le symbole choisi apparaît au poste et en jeu, comme un marqueur posé sur la carte du théâtre.</p>' +
      '<form class="ow-form-grid" id="ow-marker-form">' +
      '<label>Libellé<input name="label" required maxlength="80" placeholder="Nom du repère"></label>' +
      '<label>Description<textarea name="description" maxlength="400" placeholder="Effectif vu, armement, attitude, ce qui s’est passé."></textarea></label>' +
      '<label>Symbole</label>' + markerPickerHtml('mil_dot') +
      '<label>Déplacement<select name="movement">' +
      '<option value="still" selected>À l’arrêt</option>' +
      '<option value="moving">En déplacement</option>' +
      '<option value="shuttle">Allers-retours</option>' +
      '</select></label>' +
      '<div id="ow-marker-move-fields" hidden>' +
      '<label>Cap (0 = nord)<input name="heading" type="number" min="0" max="359" step="1" placeholder="Ex. 45"></label>' +
      '<label>Vitesse estimée (km/h)<input name="speed_kmh" type="number" min="0" max="400" step="1" value="' + (first.speedKmh || '') + '"></label>' +
      '<p class="ow-help">Le poste estime alors où l’unité peut se trouver depuis l’heure de pose.</p>' +
      '</div>' +
      '<div id="ow-marker-shuttle-fields" hidden>' +
      '<label>Longueur du parcours (m)<input name="patrol_m" type="number" min="30" max="4000" step="10" value="250"></label>' +
      '<p class="ow-help">L’unité fait des allers-retours sur cet axe, autour du point posé.</p>' +
      '</div>' +
      '<p class="ow-help">Grille ' + Math.round(w.x) + ' / ' + Math.round(w.y) + '</p>' +
      '<div class="ow-form-actions"><button class="ow-primary" type="submit">Poser</button></div></form>'
    );
    document.querySelectorAll('#ow-marker-form [data-ow-mtype]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        syncMarkerPick(btn.getAttribute('data-ow-mtype') || 'mil_dot');
      });
    });
    var form = document.getElementById('ow-marker-form');
    if (form) {
      var speed = form.querySelector('[name="speed_kmh"]');
      if (speed) speed.addEventListener('input', function () { speed.dataset.touched = '1'; });
      var moveSel = form.querySelector('[name="movement"]');
      if (moveSel) moveSel.addEventListener('change', function () { toggleMarkerMoveFields(form); });
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var data = new FormData(form);
        commitMarker(ll, {
          label: String(data.get('label') || '').trim() || 'Repère',
          description: String(data.get('description') || '').trim(),
          type: pendingMarker.type,
          movement: String(data.get('movement') || 'still'),
          heading: data.get('heading'),
          speed_kmh: data.get('speed_kmh'),
          patrol_m: data.get('patrol_m')
        });
      });
    }
    return Promise.resolve();
  }

  function commitMarker(ll, opts) {
    var api = ow();
    if (!api) return Promise.resolve();
    opts = opts || {};
    var type = opts.type || 'mil_dot';
    var spec = markerSymbol(type);
    var w = api.latLngToWorld(ll);
    var heading = Number(opts.heading);
    var speed = Number(opts.speed_kmh);
    var patrol = Number(opts.patrol_m);
    var movement = opts.movement === 'moving' || opts.movement === 'shuttle' ? opts.movement : 'still';
    return api.api('/api/markers', {
      method: 'POST',
      body: {
        mapId: api.mapId,
        markerData: {
          type: type,
          color: markerColor(type),
          affiliation: String(type).indexOf('o_') === 0 ? 'hostile' : (String(type).indexOf('b_') === 0 ? 'friend' : (String(type).indexOf('n_') === 0 ? 'unknown' : '')),
          label: opts.label,
          text: opts.label,
          description: opts.description || '',
          author: api.authorName,
          pos: [w.x, w.y],
          pos_x: w.x,
          pos_y: w.y,
          source: 'web',
          placed_at: new Date().toISOString(),
          movement: movement,
          heading: Number.isFinite(heading) ? heading : null,
          speed_kmh: Number.isFinite(speed) && speed > 0 ? speed : (spec.speedKmh || null),
          patrol_m: Number.isFinite(patrol) && patrol > 0 ? patrol : 250,
          presence_noun: spec.noun,
          warn_min: spec.warnMin,
          stale_min: spec.staleMin
        }
      }
    }).then(function () {
      toast('Marqueur posé — visible au poste et en jeu.');
      remember('marker', opts.label);
      if (api.loadArmaMarkers) api.loadArmaMarkers();
      document.getElementById('ow-drawer').hidden = true;
    }).catch(function () { toast('Marqueur refusé.'); });
  }

  function parseMarkerAgeMs(data, row) {
    var raw = (data && (data.placed_at || data.placedAt)) || (row && (row.created_at || row.createdAt || row.updated_at));
    var t = raw ? Date.parse(raw) : NaN;
    return Number.isFinite(t) ? Math.max(0, Date.now() - t) : 0;
  }

  function formatMarkerAge(ms) {
    var min = Math.max(1, Math.round(ms / 60000));
    if (min < 60) return min + ' min';
    var h = Math.floor(min / 60);
    var r = min % 60;
    return r ? (h + ' h ' + r) : (h + ' h');
  }

  function markerAgeLevel(data, ageMs) {
    var spec = markerSymbol(data && data.type);
    var warn = Number(data && data.warn_min != null ? data.warn_min : spec.warnMin) * 60000;
    var stale = Number(data && data.stale_min != null ? data.stale_min : spec.staleMin) * 60000;
    if (ageMs >= stale) return 'stale';
    if (ageMs >= warn) return 'warn';
    return '';
  }

  function markerStaleText(data, ageMs, level) {
    var spec = markerSymbol(data && data.type);
    var noun = (data && data.presence_noun) || spec.noun;
    var age = formatMarkerAge(ageMs);
    var text = level === 'stale'
      ? (noun + ' n’est probablement plus à cet endroit — posée il y a ' + age + '.')
      : (noun + ' n’est peut-être plus présente ici — posée il y a ' + age + '.');
    var extra = String((data && data.description) || '').trim();
    return extra ? (text + ' ' + extra) : text;
  }

  function shuttleOffsetM(elapsedSec, speedMps, patrolM) {
    var span = Math.max(30, patrolM);
    var period = (2 * span) / Math.max(0.3, speedMps);
    var t = (elapsedSec % period) / period;
    var u = t < 0.5 ? t * 2 : (1 - t) * 2;
    return (u * 2 - 1) * span;
  }

  function renderMarkerIntel() {
    var api = ow();
    if (!api) return;
    clearGroup(markerIntelLayers);
    var rows = window.__owArmaRows || [];
    rows.forEach(function (row) {
      var data = {};
      var raw = row && (row.markerData || row.marker_data);
      if (typeof raw === 'string') {
        try { data = JSON.parse(raw) || {}; } catch (e) { data = {}; }
      } else if (raw && typeof raw === 'object') data = raw;
      if (data.suppressed || data.po) return;
      var world = null;
      if (Array.isArray(data.pos) && data.pos.length >= 2) world = { x: Number(data.pos[0]), y: Number(data.pos[1]) };
      else world = { x: Number(data.pos_x), y: Number(data.pos_y) };
      if (!world || !Number.isFinite(world.x) || !Number.isFinite(world.y)) return;
      var loc = api.worldToLatLng(world.x, world.y);
      if (!loc) return;
      var spec = markerSymbol(data && data.type);
      var ageMs = parseMarkerAgeMs(data, row);
      var level = isHostileMarker(data, spec) ? markerAgeLevel(data, ageMs) : '';
      if (level) {
        var full = markerStaleText(data, ageMs, level);
        var short = (level === 'stale' ? 'Probablement parti · ' : 'Peut-être plus là · ') + formatMarkerAge(ageMs);
        var chip = L.marker(loc, {
          interactive: true,
          keyboard: false,
          zIndexOffset: 80,
          icon: L.divIcon({
            className: 'ow-marker-age',
            html: '<span class="ow-marker-age-chip is-' + level + '">' + esc(short) + '</span>',
            iconSize: [168, 20],
            iconAnchor: [-12, 10]
          })
        });
        if (chip.bindTooltip) chip.bindTooltip(full, { direction: 'right', opacity: 0.95 });
        markerIntelLayers.push(chip.addTo(api.map));
      }
      var movement = String(data.movement || '').toLowerCase();
      var heading = Number(data.heading);
      if ((movement === 'moving' || movement === 'shuttle') && Number.isFinite(heading)) {
        var kmh = Number(data.speed_kmh);
        if (!Number.isFinite(kmh) || kmh <= 0) kmh = spec.speedKmh || 4;
        var mps = kmh / 3.6;
        var elapsed = ageMs / 1000;
        var dist;
        if (movement === 'shuttle') {
          dist = shuttleOffsetM(elapsed, mps, Number(data.patrol_m) || 250);
        } else {
          dist = Math.min(1800, Math.max(20, mps * elapsed));
        }
        var end = headingPoint(loc, heading, dist);
        if (end) {
          markerIntelLayers.push(L.polyline([loc, end], {
            color: level === 'stale' ? '#c9784a' : '#5b8def',
            weight: 2,
            dashArray: '5 6',
            opacity: 0.8,
            className: 'ow-marker-predict',
            interactive: false
          }).addTo(api.map));
          markerIntelLayers.push(L.circleMarker(end, {
            radius: 5,
            color: '#dff9ef',
            weight: 1,
            fillColor: '#5b8def',
            fillOpacity: 0.35,
            className: 'ow-marker-ghost',
            interactive: false
          }).addTo(api.map));
        }
        if (movement === 'shuttle') {
          var a = headingPoint(loc, heading, Number(data.patrol_m) || 250);
          var b = headingPoint(loc, heading, -(Number(data.patrol_m) || 250));
          if (a && b) {
            markerIntelLayers.push(L.polyline([a, b], {
              color: '#7d8681',
              weight: 1,
              dashArray: '2 8',
              opacity: 0.55,
              interactive: false
            }).addTo(api.map));
          }
        }
      }
    });
  }

  function promptShape(type, latlngs, label) {
    var api = ow();
    if (!api) return Promise.resolve();
    latlngs = (latlngs || []).slice();
    pendingShape = { type: type, latlngs: latlngs, label: label || type };
    var closed = type === 'POLYGON' || type === 'AOI';
    var style = api.drawStyle ? api.drawStyle() : { color: '#00d69a', stroke: 2 };
    dropPendingPreview();
    pendingPreview = drawTempShape(type, latlngs, style.color, closed ? 0.12 : 0, 'none', { weight: style.stroke });
    api.openDrawer('Tracé', label || 'Nouveau tracé',
      '<form class="ow-form-grid" id="ow-shape-form">' +
      '<label>Titre<input name="title" required maxlength="80" value="' + esc(label || '') + '"></label>' +
      '<label>Texte intérieur<textarea name="interior" maxlength="240" placeholder="Affiché dans la zone"></textarea></label>' +
      '<label>Couleur du trait<input name="stroke_color" type="color" value="' + esc(style.color || '#00d69a') + '"></label>' +
      (closed ? '<label>Couleur du fond<input name="fill_color" type="color" value="' + esc(style.color || '#00d69a') + '"></label>' +
        '<label>Fond<select name="fill_style">' +
        '<option value="none">Aucun</option>' +
        '<option value="solid" selected>Plein</option>' +
        '<option value="hatch-h">Stries horizontales</option>' +
        '<option value="hatch-d">Stries diagonales</option>' +
        '</select></label>' : '') +
      '<label class="ow-toggle"><input type="checkbox" name="permanent" checked> Conserver après la session</label>' +
      '<div class="ow-form-actions"><button class="ow-primary" type="submit">Enregistrer</button></div></form>'
    );
    var form = document.getElementById('ow-shape-form');
    var colorInput = form && form.querySelector('[name="stroke_color"]');
    if (colorInput) {
      colorInput.addEventListener('input', function () {
        dropPendingPreview();
        pendingPreview = drawTempShape(type, latlngs, colorInput.value, closed ? 0.12 : 0, 'none', { weight: style.stroke });
      });
    }
    if (form) form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(form);
      commitShape(type, latlngs, {
        label: String(data.get('title') || label || type).trim(),
        interior: String(data.get('interior') || '').trim(),
        fill_style: String(data.get('fill_style') || 'solid'),
        fill_color: String(data.get('fill_color') || style.color || '#00d69a'),
        stroke_color: String(data.get('stroke_color') || style.color || '#00d69a'),
        hatch: String(data.get('fill_style') || '') === 'hatch-d' || String(data.get('fill_style') || '') === 'hatch-h',
        permanent: !!data.get('permanent')
      });
    });
    return Promise.resolve();
  }

  function drawTempShape(type, latlngs, color, fillOp, fillStyle, opts) {
    var api = ow();
    if (!api) return null;
    injectHatch();
    opts = opts || {};
    var hatchClass = fillStyle === 'hatch-h' ? 'ow-hatch-h' : (fillStyle === 'hatch-d' ? 'ow-hatch-diag' : '');
    var layer = (type === 'POLYGON' || type === 'AOI')
      ? L.polygon(latlngs, { color: color, fillColor: (opts.fill_color) || color, fillOpacity: fillOp, className: hatchClass, weight: opts.weight || 2 })
      : L.polyline(latlngs, { color: color, weight: opts.weight || 3 });
    layer.addTo(api.map);
    if (opts.interior && layer.bindTooltip) {
      layer.bindTooltip(String(opts.interior), { permanent: true, direction: 'center', className: 'ow-geo-label' });
    }
    return layer;
  }

  function dropPendingPreview() {
    var api = ow();
    if (pendingPreview && api && api.map) {
      try { api.map.removeLayer(pendingPreview); } catch (ePrev) {}
    }
    pendingPreview = null;
  }

  function injectHatch() {
    var svg = document.querySelector('#ow-map-stage .leaflet-overlay-pane svg, .leaflet-overlay-pane svg');
    if (!svg || svg.querySelector('#ow-hatch-diag')) return;
    var defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    defs.innerHTML = '<pattern id="ow-hatch-diag" patternUnits="userSpaceOnUse" width="8" height="8">' +
      '<path d="M-1,1 l2,-2 M0,8 l8,-8 M7,9 l2,-2" stroke="#00d69a" stroke-width="1"/></pattern>' +
      '<pattern id="ow-hatch-h" patternUnits="userSpaceOnUse" width="8" height="6">' +
      '<path d="M0,3 h8" stroke="#00d69a" stroke-width="1"/></pattern>';
    svg.insertBefore(defs, svg.firstChild);
  }

  function commitShape(type, latlngs, opts) {
    var api = ow();
    if (!api) return Promise.resolve();
    opts = opts || {};
    var coords = latlngs.map(function (ll) {
      var w = api.latLngToWorld(ll);
      return [w.x, w.y];
    });
    var geometry = type === 'POINT'
      ? { type: 'Point', coordinates: coords[0] }
      : (type === 'POLYGON' || type === 'AOI'
        ? { type: 'Polygon', coordinates: [coords.concat([coords[0]])] }
        : { type: 'LineString', coordinates: coords });
    var color = opts.stroke_color || (api.drawStyle && api.drawStyle().color) || '#00d69a';
    var stroke = Number((opts.stroke != null ? opts.stroke : (api.drawStyle && api.drawStyle().stroke)) || 2);
    var fillStyle = opts.fill_style || (opts.hatch ? 'hatch-d' : 'solid');
    var fillOp = fillStyle === 'none' ? 0 : (opts.fill != null ? opts.fill : 0.18);
    dropPendingPreview();
    if (api.setDrawTint) api.setDrawTint(color);
    if (!opts.permanent) {
      var apiLayer = drawTempShape(type, latlngs, color, fillOp, fillStyle, opts);
      if (api.clearDraft) api.clearDraft();
      toast((opts.label || type) + ' posé pour cette session seulement.');
      document.getElementById('ow-drawer').hidden = true;
      return Promise.resolve(apiLayer);
    }
    return api.api('/api/map-shapes', {
      method: 'POST',
      body: {
        mapId: api.mapId,
        type: type,
        label: opts.label || type,
        color: color,
        stroke: stroke,
        fillOpacity: fillOp,
        geometry: geometry,
        createdBy: api.authorName,
        meta: {
          description: opts.interior || '',
          interior: opts.interior || '',
          fill_style: fillStyle,
          fill_color: opts.fill_color || color,
          hatch: fillStyle === 'hatch-d' || fillStyle === 'hatch-h',
          permanent: true
        }
      }
    }).then(function (row) {
      toast((opts.label || type) + ' enregistré.');
      remember('shape', opts.label || type, row && row.id);
      if (api.clearDraft) api.clearDraft();
      if (row && api.drawShape) {
        if (api.getShapes) api.getShapes().push(row);
        api.drawShape(row);
      } else if (api.loadShapes) api.loadShapes();
      else if (row && api.getShapes) {
        api.getShapes().push(row);
        api.renderMap();
      }
      document.getElementById('ow-drawer').hidden = true;
      return row;
    }).catch(function () { toast('Enregistrement impossible.'); });
  }

  function openSitrep(ll) {
    var api = ow();
    if (!api) return;
    var w = api.latLngToWorld(ll);
    api.openDrawer('Compte rendu', 'Situation géolocalisée',
      '<p class="ow-help">Le compte rendu est enregistré sur la carte et dans le renseignement de mission.</p>' +
      '<form class="ow-form-grid" id="ow-sitrep-form">' +
      '<label>Titre<input name="title" required maxlength="80" placeholder="Ce que vous voyez"></label>' +
      '<label>Observation<textarea name="body" required maxlength="800" placeholder="Détail utile au commandement"></textarea></label>' +
      '<label>Nature<select name="target_type">' +
      '<option value="UNKNOWN">Non identifié</option>' +
      '<option value="INFANTRY">Personnel</option>' +
      '<option value="VEHICLE">Véhicule</option>' +
      '<option value="ARMOR">Blindé</option>' +
      '<option value="AIR_DEFENSE">Défense antiaérienne</option>' +
      '</select></label>' +
      '<label>Urgence<select name="urgency">' +
      '<option value="ROUTINE">Routine</option>' +
      '<option value="PRIORITY">Priorité</option>' +
      '<option value="URGENT">Urgent</option>' +
      '</select></label>' +
      '<label>Grille est<input name="grid_x" type="number" step="1" value="' + Math.round(w.x) + '"></label>' +
      '<label>Grille nord<input name="grid_y" type="number" step="1" value="' + Math.round(w.y) + '"></label>' +
      '<label class="ow-toggle"><input type="checkbox" name="chat"> Envoyer aussi sur le canal Commandement</label>' +
      '<div class="ow-form-actions"><button class="ow-primary" type="submit">Transmettre</button></div></form>' +
      '<p class="ow-kicker">Comptes rendus de cette mission</p><div id="ow-sitrep-list" class="ow-sitrep-list"></div>'
    );
    renderSitrepList();
    var form = document.getElementById('ow-sitrep-form');
    if (form) form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(form);
      var title = String(data.get('title') || '').trim();
      var body = String(data.get('body') || '').trim();
      var target = String(data.get('target_type') || 'UNKNOWN');
      var urgency = String(data.get('urgency') || 'ROUTINE');
      var gx = Number(data.get('grid_x'));
      var gy = Number(data.get('grid_y'));
      if (!isFinite(gx)) gx = w.x;
      if (!isFinite(gy)) gy = w.y;
      api.api('/api/intel/report', {
        method: 'POST',
        body: {
          missionId: missionId(),
          mapId: api.mapId,
          target_type: target,
          pos_x: gx,
          pos_y: gy,
          source_callsign: api.authorName,
          report_type: 'SITREP',
          title: title,
          observation: body,
          urgency: urgency
        }
      }).then(function (row) {
        return api.api('/api/map-shapes', {
          method: 'POST',
          body: {
            mapId: api.mapId,
            type: 'POINT',
            label: title,
            color: urgency === 'URGENT' ? '#e05b63' : (urgency === 'PRIORITY' ? '#e7b14d' : '#00d69a'),
            geometry: { type: 'Point', coordinates: [gx, gy] },
            createdBy: api.authorName,
            meta: { kind: 'sitrep', body: body, report_id: row && row.id, urgency: urgency }
          }
        }).then(function () { return row; });
      }).then(function () {
        if (data.get('chat')) {
          api.api('/api/chat', {
            method: 'POST',
            body: { mapId: api.mapId, author: api.authorName, channel: 'commandement', body: 'SITREP ' + Math.round(gx) + '/' + Math.round(gy) + ' — ' + title + (body ? ' — ' + body : '') }
          });
        }
        toast('Compte rendu transmis et posé sur la carte.');
        remember('sitrep', title);
        loadSitreps();
        if (api.loadShapes) api.loadShapes();
        document.getElementById('ow-drawer').hidden = true;
      }).catch(function () { toast('Transmission refusée.'); });
    });
  }

  function openIntel(ll) {
    var api = ow();
    if (!api) return;
    var w = api.latLngToWorld(ll);
    api.openDrawer('Renseignement', 'Observation de terrain',
      '<p class="ow-help">Note liée à cette grille. Elle reste dans le renseignement de mission.</p>' +
      '<form class="ow-form-grid" id="ow-obs-form">' +
      '<label>Titre<input name="title" required maxlength="80"></label>' +
      '<label>Observation<textarea name="body" required maxlength="800"></textarea></label>' +
      '<label>Nature<select name="target_type">' +
      '<option value="UNKNOWN">Non identifié</option>' +
      '<option value="INFANTRY">Personnel</option>' +
      '<option value="VEHICLE">Véhicule</option>' +
      '<option value="ARMOR">Blindé</option>' +
      '</select></label>' +
      '<label>Photo facultative<input name="photo" type="file" accept="image/*"></label>' +
      '<div class="ow-form-actions"><button class="ow-primary" type="submit">Enregistrer</button></div></form>'
    );
    var form = document.getElementById('ow-obs-form');
    if (form) form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(form);
      var title = String(data.get('title') || '').trim();
      var body = String(data.get('body') || '').trim();
      api.api('/api/intel/report', {
        method: 'POST',
        body: {
          missionId: missionId(),
          mapId: api.mapId,
          target_type: String(data.get('target_type') || 'UNKNOWN'),
          pos_x: w.x,
          pos_y: w.y,
          source_callsign: api.authorName,
          report_type: 'OBS',
          title: title,
          observation: body
        }
      }).then(function () {
        return api.api('/api/map-shapes', {
          method: 'POST',
          body: {
            mapId: api.mapId,
            type: 'POINT',
            label: title,
            color: '#8b8bf0',
            geometry: { type: 'Point', coordinates: [w.x, w.y] },
            createdBy: api.authorName,
            meta: { kind: 'observation', body: body }
          }
        });
      }).then(function () {
        var file = data.get('photo');
        if (file && file.size) {
          var fd = new FormData();
          fd.append('photo', file);
          fd.append('mapId', String(api.mapId));
          fd.append('author', api.authorName);
          fd.append('pos_x', String(w.x));
          fd.append('pos_y', String(w.y));
          return api.api('/api/intel/photos', { method: 'POST', body: fd }).catch(function () { return null; });
        }
        return null;
      }).then(function () {
        toast('Observation enregistrée sur la carte.');
        remember('intel', title);
        if (api.loadShapes) api.loadShapes();
        document.getElementById('ow-drawer').hidden = true;
      }).catch(function () { toast('Enregistrement refusé.'); });
    });
  }

  function openChatGrid(ll) {
    var api = ow();
    if (!api) return;
    var w = api.latLngToWorld(ll);
    var grid = Math.round(w.x) + ' / ' + Math.round(w.y);
    api.openDrawer('Canal', 'Envoyer la grille',
      '<form class="ow-form-grid" id="ow-grid-form">' +
      '<p class="ow-help">La grille ' + esc(grid) + ' est ajoutée au message.</p>' +
      '<label>Canal<select name="channel">' +
      '<option value="commandement">Commandement</option>' +
      '<option value="groupe">Groupe</option>' +
      '<option value="general">Général</option>' +
      '<option value="jtac">JTAC</option>' +
      '</select></label>' +
      '<label>Message<input name="text" maxlength="200" placeholder="Contexte optionnel"></label>' +
      '<div class="ow-form-actions"><button class="ow-primary" type="submit">Envoyer</button></div></form>'
    );
    var form = document.getElementById('ow-grid-form');
    if (form) form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(form);
      var extra = String(data.get('text') || '').trim();
      var body = 'GRILLE ' + grid + (extra ? ' — ' + extra : '');
      api.api('/api/chat', {
        method: 'POST',
        body: { mapId: api.mapId, author: api.authorName, channel: data.get('channel'), body: body }
      }).then(function () {
        toast('Grille envoyée sur le canal.');
        document.getElementById('ow-drawer').hidden = true;
      }).catch(function () { toast('Envoi refusé.'); });
    });
  }

  function openPing(ll) {
    var api = ow();
    if (!api) return;
    var w = api.latLngToWorld(ll);
    api.openDrawer('Repère', 'Repère rapide',
      '<form class="ow-form-grid" id="ow-ping-form">' +
      '<label>Message<input name="message" maxlength="120" value="Repère rapide" required></label>' +
      '<label>Disparition<select name="ttl"><option value="30">30 secondes</option><option value="60" selected>1 minute</option><option value="180">3 minutes</option><option value="0">Rester jusqu’à suppression</option></select></label>' +
      '<div class="ow-form-actions"><button class="ow-primary" type="submit">Transmettre</button></div></form>'
    );
    var form = document.getElementById('ow-ping-form');
    if (form) form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(form);
      var msg = String(data.get('message') || 'Repère rapide').trim();
      var ttl = Number(data.get('ttl') || 60);
      api.api('/api/pings', {
        method: 'POST',
        body: { mapId: api.mapId, author: api.authorName, pos_x: w.x, pos_y: w.y, message: msg, ttl_sec: ttl }
      }).then(function (row) {
        var id = String((row && row.id) || Date.now());
        if (api.registerPing) api.registerPing(id, ll, msg, ttl);
        else {
          var pin = L.circleMarker(ll, { radius: 7, color: '#e7b14d', weight: 2 }).addTo(api.map);
          if (pin.bindTooltip) pin.bindTooltip(msg, { direction: 'top' });
          if (ttl > 0) window.setTimeout(function () { try { api.map.removeLayer(pin); } catch (e) {} }, ttl * 1000);
        }
        toast(ttl > 0 ? ('Repère visible ' + ttl + ' s.') : 'Repère transmis. Clic droit pour le retirer.');
        document.getElementById('ow-drawer').hidden = true;
      }).catch(function () { toast('Repère refusé.'); });
    });
  }

  function loadSitreps() {
    var api = ow();
    if (!api) return Promise.resolve();
    return api.api('/api/intel/fused?missionId=' + encodeURIComponent(missionId())).then(function (payload) {
      sitreps = Array.isArray(payload) ? payload : (api.asList ? api.asList(payload, 'reports') : []);
      renderSitrepPins();
      renderSitrepList();
    }).catch(function () { sitreps = []; });
  }

  function renderSitrepList() {
    var host = document.getElementById('ow-sitrep-list');
    if (!host) return;
    host.innerHTML = sitreps.slice(0, 12).map(function (row) {
      var title = row.title || (row.raw_payload_json && row.raw_payload_json.title) || row.report_type || 'Compte rendu';
      if (typeof row.raw_payload_json === 'string') {
        try {
          var raw = JSON.parse(row.raw_payload_json);
          if (raw && raw.title) title = raw.title;
        } catch (e) {}
      }
      return '<button type="button" class="ow-sitrep-item" data-ow-sitrep="' + esc(String(row.id || '')) + '"><strong>' +
        esc(String(title)) + '</strong><small>' + esc(String(row.source_callsign || '')) + '</small></button>';
    }).join('') || '<p class="ow-help">Aucun compte rendu pour cette mission.</p>';
    host.querySelectorAll('[data-ow-sitrep]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = sitreps.filter(function (r) { return String(r.id) === btn.getAttribute('data-ow-sitrep'); })[0];
        if (!row || !ow()) return;
        var ll = ow().worldToLatLng(Number(row.pos_x), Number(row.pos_y));
        ow().map.setView(ll, Math.max(ow().map.getZoom(), 4));
      });
    });
  }

  function renderSitrepPins() {
    var api = ow();
    if (!api) return;
    clearGroup(sitrepPins);
    sitreps.forEach(function (row) {
      var x = Number(row.pos_x);
      var y = Number(row.pos_y);
      if (!isFinite(x) || !isFinite(y)) return;
      var ll = api.worldToLatLng(x, y);
      var pin = L.circleMarker(ll, { radius: 7, color: '#e7b14d', weight: 2, fillOpacity: 0.45 }).addTo(api.map);
      if (api.bindLayerContext) {
        api.bindLayerContext(pin, 'sitrep', String(row.id || ''), String(row.title || row.report_type || 'Compte rendu'));
      }
      sitrepPins.push(pin);
    });
  }

  function layersHtml() {
    var api = ow();
    if (!api) return '';
    var q = String((document.getElementById('ow-layers-q') || {}).value || '').toLowerCase();
    function match(text) { return !q || String(text || '').toLowerCase().indexOf(q) !== -1; }
    function card(kind, id, title, meta, delAttr) {
      return '<div class="ow-layer-card" data-ow-layer-kind="' + esc(kind) + '" data-ow-layer-id="' + esc(String(id || '')) + '">' +
        '<div><strong>' + esc(title) + '</strong><small>' + esc(meta) + '</small></div>' +
        '<div class="ow-layer-actions">' +
        '<button type="button" data-ow-focus="' + esc(kind + ':' + id) + '">Voir</button>' +
        (delAttr ? '<button type="button" class="ow-ctx-danger" ' + delAttr + '>Retirer</button>' : '') +
        '</div></div>';
    }
    var shapes = (api.getShapes && api.getShapes()) || [];
    var markers = window.__owArmaRows || [];
    var html = '<label class="ow-search ow-layers-search"><span>⌕</span><input id="ow-layers-q" type="search" placeholder="Rechercher un calque, un libellé…" value="' + esc(q) + '"></label>';
    html += '<p class="ow-help">Recherchez, recentrez ou retirez. Un retrait demande confirmation.</p>';
    if (recents.length) {
      html += '<p class="ow-kicker">Récents</p>';
      recents.forEach(function (row) {
        html += '<div class="ow-event"><span>' + esc(row.label) + '</span><strong>' + esc(row.kind) + '</strong></div>';
      });
    }
    html += '<p class="ow-kicker">Tracés</p><div class="ow-layers-list">';
    var shapeCards = shapes.filter(function (row) { return match(row.label || row.type); }).map(function (row) {
      return card('shape', row.id, row.label || row.type || 'Tracé', row.type || '', 'data-del-shape="' + esc(String(row.id || '')) + '"');
    }).join('');
    html += shapeCards || '<p class="ow-help">Aucun tracé.</p>';
    html += '</div><p class="ow-kicker">Marqueurs du théâtre</p><div class="ow-layers-list">';
    var armaCards = markers.filter(function (row) {
      var data = row.markerData || row.marker_data || {};
      if (typeof data === 'string') { try { data = JSON.parse(data); } catch (e) { data = {}; } }
      return match(data.label || data.text || data.type);
    }).map(function (row) {
      var data = row.markerData || row.marker_data || {};
      if (typeof data === 'string') { try { data = JSON.parse(data); } catch (e2) { data = {}; } }
      var title = data.label || data.text || '';
      if (!title || /^marker[_-]?\d+$/i.test(title)) {
        title = data.type === 'mil_objective' ? 'Objectif' : (data.type === 'mil_circle' ? 'Cercle' : 'Repère');
      }
      var src = String(data.source || row.source || '').toLowerCase();
      var tag = (src === 'arma' || src === 'ctab' || src === 'ace') ? 'Arma' : 'Poste';
      return card('marker', row.id, title, tag + (data.type ? ' · ' + data.type : ''), 'data-del-marker="' + esc(String(row.id || '')) + '"');
    }).join('');
    html += armaCards || '<p class="ow-help">Aucun marqueur du théâtre.</p></div>';
    html += '<p class="ow-kicker">Relais ATAK</p><div class="ow-layers-list">';
    var relayCards = relays.filter(function (row) {
      return match(relayTitle(row) + ' ' + (row.identity || '') + ' ' + (row.relay_uid || 'Relais'));
    }).map(function (row) {
      var alive = row.alive !== 0 && row.alive !== false;
      return card('relay', row.relay_uid, relayTitle(row), alive ? relayLayerTag(row) : 'Détruit', 'data-del-relay="' + esc(String(row.relay_uid || '')) + '"');
    }).join('');
    html += relayCards || '<p class="ow-help">Aucun relais posé en jeu.</p></div>';
    html += '<div id="ow-layer-confirm" hidden></div>';
    return html;
  }

  function bindLayers() {
    var q = document.getElementById('ow-layers-q');
    if (q) q.addEventListener('input', function () {
      var api = ow();
      if (api) api.openDrawer('Cartographie', 'Calques', layersHtml());
      bindLayers();
    });
    document.querySelectorAll('[data-ow-focus]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var parts = String(btn.getAttribute('data-ow-focus') || '').split(':');
        focusLayer(parts[0], parts[1]);
      });
    });
    document.querySelectorAll('[data-del-marker]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var title = (btn.closest('.ow-layer-card') && btn.closest('.ow-layer-card').querySelector('strong'));
        confirmDelete('marker', btn.getAttribute('data-del-marker'), title ? title.textContent : 'ce marqueur');
      });
    });
    document.querySelectorAll('[data-del-shape]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var title = (btn.closest('.ow-layer-card') && btn.closest('.ow-layer-card').querySelector('strong'));
        confirmDelete('shape', btn.getAttribute('data-del-shape'), title ? title.textContent : 'ce tracé');
      });
    });
    document.querySelectorAll('[data-del-relay]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        confirmDelete('relay', btn.getAttribute('data-del-relay'), 'ce relais');
      });
    });
  }

  function confirmDelete(kind, id, label) {
    var host = document.getElementById('ow-layer-confirm');
    if (!host) return;
    host.hidden = false;
    host.className = 'ow-confirm';
    host.innerHTML = '<p>Retirer ' + esc(label || 'cet élément') + ' de la carte du poste ?</p>' +
      (kind === 'marker' ? '<p class="ow-help">Le repère disparaît du poste, même s’il reste visible sur la carte en jeu.</p>' : '') +
      (kind === 'relay' ? '<p class="ow-help">Le relais disparaît du poste. S’il existe encore en jeu, il peut réapparaître.</p>' : '') +
      '<div class="ow-form-actions"><button type="button" class="ow-primary" id="ow-del-yes">Retirer</button>' +
      '<button type="button" class="ow-secondary" id="ow-del-no">Annuler</button></div>';
    document.getElementById('ow-del-no').addEventListener('click', function () { host.hidden = true; });
    document.getElementById('ow-del-yes').addEventListener('click', function () {
      var api = ow();
      if (!api) return;
      var url = kind === 'shape'
        ? '/api/map-shapes/' + encodeURIComponent(id)
        : (kind === 'relay' ? '/api/atak/relays/' + encodeURIComponent(id) + '?mapId=' + encodeURIComponent(api.mapId) : '/api/markers/' + encodeURIComponent(id));
      api.api(url, { method: 'DELETE' }).then(function () {
        toast('Élément retiré.');
        if (kind === 'shape' && api.loadShapes) api.loadShapes();
        if (kind === 'marker' && api.loadArmaMarkers) api.loadArmaMarkers();
        if (kind === 'relay') loadRelays();
        api.openView('layers');
      }).catch(function (err) {
        if (String(err && err.message) === '404') {
          toast('Cet élément n’est plus au poste.');
          if (kind === 'marker' && api.loadArmaMarkers) api.loadArmaMarkers();
          if (kind === 'shape' && api.loadShapes) api.loadShapes();
          if (kind === 'relay') loadRelays();
          api.openView('layers');
          return;
        }
        toast('Retrait impossible.');
      });
    });
  }

  function focusLayer(kind, id) {
    var api = ow();
    if (!api) return;
    if (kind === 'shape') {
      var layers = api.getShapeLayers ? api.getShapeLayers() : {};
      var layer = layers[String(id)];
      if (layer && layer.getBounds) api.map.fitBounds(layer.getBounds().pad(0.2));
      else if (layer && layer.getLatLng) api.map.setView(layer.getLatLng(), Math.max(api.map.getZoom(), 4));
    }
    if (kind === 'marker') {
      var rows = window.__owArmaRows || [];
      var row = rows.filter(function (r) { return String(r.id) === String(id); })[0];
      if (!row) return;
      var data = row.markerData || row.marker_data || {};
      if (typeof data === 'string') { try { data = JSON.parse(data); } catch (e) { data = {}; } }
      var x = Number((data.pos && data.pos[0]) || data.pos_x);
      var y = Number((data.pos && data.pos[1]) || data.pos_y);
      if (isFinite(x) && isFinite(y)) api.map.setView(api.worldToLatLng(x, y), Math.max(api.map.getZoom(), 4));
    }
  }

  function drawLos(fromLl, toLl, payload) {
    var api = ow();
    if (!api || !payload) return '';
    injectHatch();
    var group = L.layerGroup();
    var samples = Array.isArray(payload.samples) ? payload.samples : [];
    var color = payload.verdict === 'clear' ? '#00d69a' : (payload.verdict === 'masked' ? '#e05b63' : '#e7b14d');
    var cut = null;
    if (payload.obstruction && payload.obstruction.x != null) {
      cut = api.worldToLatLng(Number(payload.obstruction.x), Number(payload.obstruction.y));
    } else {
      samples.some(function (s) {
        if (s && s.clear === false) {
          cut = api.worldToLatLng(Number(s.x), Number(s.y));
          return true;
        }
        return false;
      });
    }
    if (cut) {
      L.polyline([fromLl, cut], { color: '#00d69a', weight: 3, className: 'ow-los-clear' }).addTo(group);
      L.polyline([cut, toLl], { color: '#e05b63', weight: 3, dashArray: '5 6', className: 'ow-los-masked' }).addTo(group);
      var a = api.latLngToWorld(fromLl);
      var c = api.latLngToWorld(cut);
      var vx = c.x - a.x;
      var vy = c.y - a.y;
      var hyp = Math.hypot(vx, vy) || 1;
      var px = -vy / hyp * 7;
      var py = vx / hyp * 7;
      L.polyline([
        api.worldToLatLng(c.x + px, c.y + py),
        api.worldToLatLng(c.x - px, c.y - py)
      ], { color: '#e05b63', weight: 5, className: 'ow-los-block' }).addTo(group);
      L.circleMarker(cut, { radius: 6, color: '#e05b63', fillColor: '#e05b63', weight: 2, fillOpacity: 0.9, className: 'ow-los-block' }).addTo(group);
    } else {
      L.polyline([fromLl, toLl], { color: color, weight: 3, className: 'ow-los-clear' }).addTo(group);
    }
    group.addTo(api.map);
    if (api.registerScratch) api.registerScratch(group, 'los', 'los-' + Date.now(), 'Visée');
    else if (api.bindLayerContext) {
      api.bindLayerContext(group, 'los', 'los', 'Visée');
      group.eachLayer(function (child) { api.bindLayerContext(child, 'los', 'los', 'Visée'); });
    }
    var cause = payload.cause_label || (payload.obstruction && (payload.obstruction.kind_label || payload.obstruction.cause)) || '';
    if (payload.verdict === 'masked') {
      var extra = '';
      if (payload.obstruction && payload.obstruction.excess_m != null) {
        extra = ' · dépasse de ' + Math.round(payload.obstruction.excess_m) + ' m';
      }
      return '<div class="ow-event"><span>Cause</span><strong>' + esc(cause || payload.verdict_label || 'Masqué par le relief') + extra + '</strong></div>';
    }
    if (payload.scene_ready === false) {
      return '<p class="ow-help">Le relief ne masque pas. Bâtiments et couverts n’ont pas encore été relevés sur ce théâtre : ils ne sont pas pris en compte.</p>';
    }
    return '';
  }

  function loadTraffic() {
    var api = ow();
    if (!api) return Promise.resolve();
    return api.api('/api/atak/ingest-traffic?mapId=' + encodeURIComponent(api.mapId)).then(function (payload) {
      var kbps = payload && payload.kbps_now != null ? payload.kbps_now : null;
      var el = document.getElementById('ow-stat-traffic');
      if (el) el.textContent = kbps == null ? '—' : (kbps >= 10 ? Math.round(kbps) + ' ko/s' : (kbps > 0 ? kbps.toFixed(1) + ' ko/s' : '0'));
      var since = document.getElementById('ow-traffic-since');
      if (since) since.textContent = payload && payload.last_sync_ago ? payload.last_sync_ago : 'Aucune remontée';
      var win = document.getElementById('ow-traffic-window');
      if (win) win.textContent = formatMo(payload && payload.bytes_15m);
      var ph = document.getElementById('ow-traffic-photos');
      if (ph) ph.textContent = formatMo(payload && payload.photo_bytes_15m);
      drawSpark(payload && payload.series);
    }).catch(function () {});
  }

  function formatMo(bytes) {
    var n = Number(bytes || 0);
    if (n < 1024) return n + ' o';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' ko';
    return (n / (1024 * 1024)).toFixed(2) + ' Mo';
  }

  function drawSpark(series) {
    var svg = document.getElementById('ow-traffic-spark');
    if (!svg) return;
    var rows = Array.isArray(series) ? series : [];
    if (!rows.length) {
      svg.innerHTML = '';
      return;
    }
    var max = 1;
    rows.forEach(function (n) { max = Math.max(max, Number(n) || 0); });
    var pts = rows.map(function (n, i) {
      var x = (i / Math.max(1, rows.length - 1)) * 280;
      var y = 68 - ((Number(n) || 0) / max) * 60;
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    svg.innerHTML = '<polyline points="' + pts + '"></polyline>';
  }

  function bootGeo() {
    var api = ow();
    if (!api || !window.AtakGeoNetwork || geoNet) return;
    geoGroup = L.layerGroup().addTo(api.map);
    var base = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
    if (base.slice(-4) !== '/api') base += '/api';
    geoNet = window.AtakGeoNetwork.create(base, api.mapId, geoGroup);
    geoNet.loadCoverage().then(function (cov) {
      var places = cov && (cov.places_count || cov.place_count || 0);
      var roads = cov && (cov.roads_count || cov.road_count || 0);
      var ph = document.getElementById('ow-geo-places-help');
      var rh = document.getElementById('ow-geo-roads-help');
      if (ph) ph.textContent = places ? (places + ' localités relevées pour ce théâtre.') : 'Aucun relevé de villes reçu pour ce théâtre.';
      if (rh) rh.textContent = roads ? (roads + ' tronçons relevés pour ce théâtre.') : 'Aucun relevé de routes reçu pour ce théâtre.';
    });
    restoreGeoPrefs();
    ['ow-geo-places', 'ow-geo-roads', 'ow-geo-remember', 'ow-geo-labels'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () {
        persistGeoPrefs();
        refreshGeo();
      });
    });
    api.map.on('moveend', refreshGeo);
  }

  function restoreGeoPrefs() {
    try {
      var raw = JSON.parse(localStorage.getItem('athena:ow-geo-prefs') || '{}');
      if (raw.places && document.getElementById('ow-geo-places')) document.getElementById('ow-geo-places').checked = true;
      if (raw.roads && document.getElementById('ow-geo-roads')) document.getElementById('ow-geo-roads').checked = true;
      if (raw.labels === false && document.getElementById('ow-geo-labels')) document.getElementById('ow-geo-labels').checked = false;
    } catch (e) {}
  }
  function persistGeoPrefs() {
    var remember = document.getElementById('ow-geo-remember');
    if (remember && !remember.checked) return;
    try {
      localStorage.setItem('athena:ow-geo-prefs', JSON.stringify({
        places: !!(document.getElementById('ow-geo-places') || {}).checked,
        roads: !!(document.getElementById('ow-geo-roads') || {}).checked,
        labels: !!(document.getElementById('ow-geo-labels') || {}).checked
      }));
    } catch (e) {}
  }
  function refreshGeo() {
    if (!geoNet || !ow()) return;
    var places = !!(document.getElementById('ow-geo-places') || {}).checked;
    var roads = !!(document.getElementById('ow-geo-roads') || {}).checked;
    geoNet.setVisible('places', places);
    geoNet.setVisible('roads', roads);
    if (!places && !roads) {
      geoNet.render({ places: [], roads: [] });
      lastGeoKey = '';
      return;
    }
    var b = ow().map.getBounds();
    var bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()];
    var key = bbox.map(function (n) { return n.toFixed(0); }).join(',') + places + roads;
    if (key === lastGeoKey) return;
    lastGeoKey = key;
    geoNet.loadBbox(bbox).then(function (data) { geoNet.render(data || { places: [], roads: [] }); });
  }

  function loadRelays() {
    var api = ow();
    if (!api) return Promise.resolve();
    return api.api('/api/atak/relays?mapId=' + encodeURIComponent(api.mapId)).then(function (payload) {
      relays = (payload && payload.relays) || (Array.isArray(payload) ? payload : []);
      var help = document.getElementById('ow-relays-help');
      if (help) help.textContent = relays.length ? (relays.length + ' relais posés en jeu.') : 'Aucun relais posé en jeu pour le moment.';
      var mode = document.getElementById('ow-relay-mode-help');
      if (mode && payload && payload.link_via_relays) {
        mode.textContent = 'La liaison du téléphone passe par les relais posés. Hors portée ou relais détruit : plus de données ATAK.';
      }
      renderRelays();
    }).catch(function () { relays = []; });
  }

  function relayTitle(row) {
    return String(row.display_name || row.name || row.identity || row.relay_uid || 'Relais ATAK');
  }

  function relayLayerTag(row) {
    var bits = [];
    if (row.identity) bits.push(String(row.identity));
    var thru = Number(row.throughput_mbps);
    if (!isNaN(thru) && thru > 0) bits.push(thru.toFixed(1).replace(/\.0$/, '') + ' Mbit/s');
    var rel = Number(row.reliability_pct);
    if (!isNaN(rel)) bits.push(Math.round(rel) + ' %');
    return bits.join(' · ') || 'En service';
  }

  function relayPopupHtml(row) {
    var alive = row.alive !== false && row.alive !== 0;
    var slots = Number(row.slots || 0);
    var used = Number(row.slots_used || row.used || 0);
    var lines = [
      '<strong>' + esc(relayTitle(row)) + '</strong>',
      '<div>' + (alive ? 'En service' : 'Détruit') + '</div>'
    ];
    if (row.identity) lines.push('<div>Identité · ' + esc(String(row.identity)) + '</div>');
    if (row.range_m) lines.push('<div>Portée · ' + Math.round(Number(row.range_m)) + ' m</div>');
    if (row.throughput_mbps != null && row.throughput_mbps !== '') {
      lines.push('<div>Débit · ' + Number(row.throughput_mbps).toFixed(1).replace(/\.0$/, '') + ' Mbit/s</div>');
    }
    if (row.reliability_pct != null && row.reliability_pct !== '') {
      lines.push('<div>Fiabilité · ' + Math.round(Number(row.reliability_pct)) + ' %</div>');
    }
    if (slots > 0) lines.push('<div>Places · ' + used + ' / ' + slots + '</div>');
    if (row.power_w != null && row.power_w !== '') lines.push('<div>Puissance · ' + Math.round(Number(row.power_w)) + ' W</div>');
    if (row.ip_addr || row.ip) lines.push('<div>Adresse réseau · ' + esc(String(row.ip_addr || row.ip)) + '</div>');
    if (row.gateway) lines.push('<div>Passerelle · ' + esc(String(row.gateway)) + '</div>');
    if (row.certificate) lines.push('<div>Certificat · ' + esc(String(row.certificate)) + '</div>');
    return lines.join('');
  }

  function renderRelays() {
    var api = ow();
    if (!api) return;
    clearGroup(relayLayers);
    var box = document.getElementById('ow-relays-layer');
    if (box && !box.checked) return;
    relays.forEach(function (row) {
      var ll = api.worldToLatLng(Number(row.pos_x), Number(row.pos_y));
      var alive = row.alive !== false && row.alive !== 0;
      var uid = String(row.relay_uid || row.uid || '');
      var title = relayTitle(row);
      var icon = L.divIcon({
        className: 'ow-relay-dot' + (alive ? '' : ' is-down'),
        html: '<span></span><em>' + esc(title) + '</em>',
        iconSize: [88, 16],
        iconAnchor: [8, 8]
      });
      var m = L.marker(ll, { icon: icon, interactive: true, keyboard: false }).addTo(api.map);
      var fiche = alive ? title : (title + ' · détruit');
      if (api.bindLayerContext) {
        api.bindLayerContext(m, 'relay', uid, fiche);
      }
      if (m.bindPopup) {
        m.bindPopup(relayPopupHtml(row), { className: 'ow-relay-popup', maxWidth: 280 });
      } else if (m.bindTooltip) {
        m.bindTooltip(fiche, { direction: 'top' });
      }
      relayLayers.push(m);
      // Utiliser config réalisme pour fallback range
      var defaultRange = 2000;
      if (realismConfig && typeof window.AtakRealismConfig !== 'undefined') {
        defaultRange = window.AtakRealismConfig.get(realismConfig, 'radio_relays', 'relay_range_m', 2000);
      }
      var range = Number(row.range_m || defaultRange);
      relayLayers.push(L.polygon(circleByRadius(ll, range, 48), {
        color: alive ? '#e7b14d' : '#e05b63',
        weight: 1,
        dashArray: '4 8',
        fillOpacity: 0.04,
        interactive: false
      }).addTo(api.map));
    });
  }

  function renderDf() {
    var api = ow();
    if (!api) return;
    clearGroup(dfLayers);
    api.api('/api/atak/sigint/zones?mapId=' + encodeURIComponent(api.mapId)).then(function (payload) {
      var zones = Array.isArray(payload) ? payload : (payload && payload.zones) || [];
      var host = document.getElementById('ow-df-list');
      if (host) {
        host.innerHTML = zones.map(function (z) {
          var lab = z.kind === 'azimuth' ? 'Gisement seul' : 'Ellipse';
          return '<div class="ow-event"><span>' + esc(z.call_sign || 'Émetteur') + '</span><strong>' + esc(lab) + '</strong></div>';
        }).join('') || '<p class="ow-help">Aucun émetteur relevé pour le moment.</p>';
      }
      zones.forEach(function (z) {
        var ll = api.worldToLatLng(Number(z.pos_x), Number(z.pos_y));
        if (z.kind === 'azimuth' && z.bearing != null) {
          var far = headingPoint(ll, Number(z.bearing), 1200);
          if (far) dfLayers.push(L.polyline([ll, far], { color: '#e05b63', weight: 1, dashArray: '2 8', opacity: 0.7, interactive: false }).addTo(api.map));
          return;
        }
        var radius = Number(z.radius || 0);
        if (radius > 0) {
          dfLayers.push(L.polygon(circleByRadius(ll, radius, 48), {
            color: '#e05b63', weight: 1, dashArray: '4 6', fillOpacity: 0.08, className: 'ow-df-ellipse', interactive: false
          }).addTo(api.map));
        }
      });
    }).catch(function () {});
  }

  function bindReplay() {
    var play = document.getElementById('ow-replay-play');
    var speed = document.getElementById('ow-replay-speed');
    var source = document.getElementById('ow-replay-source');
    var scrub = document.getElementById('ow-replay-scrub');
    if (play) play.addEventListener('click', function () {
      replayPlaying = !replayPlaying;
      play.textContent = replayPlaying ? 'Pause' : 'Lecture';
      if (replayPlaying) tickReplay();
      else if (replayTimer) { window.clearTimeout(replayTimer); replayTimer = null; }
    });
    if (source) source.addEventListener('change', function () {
      if (source.value === 'mission') loadMissionReplay();
      else {
        missionFrames = [];
        clearMissionGhosts();
        toast('Replay de la session en cours.');
      }
    });
    if (scrub) scrub.addEventListener('input', function () {
      if (source && source.value === 'mission' && missionFrames.length) applyMissionFrame(Number(scrub.value));
    });
    var stop = document.getElementById('ow-follow-stop');
    if (stop) stop.addEventListener('click', function () {
      var box = document.getElementById('ow-follow');
      if (box) { box.checked = false; box.dispatchEvent(new Event('change')); }
    });
    ['ow-look-arrow', 'ow-predict', 'ow-progress-trail', 'ow-relays-layer'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () { afterRenderMap(); });
    });
  }

  function tickReplay() {
    if (!replayPlaying) return;
    var scrub = document.getElementById('ow-replay-scrub');
    var speed = Number((document.getElementById('ow-replay-speed') || {}).value || 1);
    if (!scrub) return;
    var next = Math.min(100, Number(scrub.value) + speed);
    scrub.value = String(next);
    scrub.dispatchEvent(new Event('input'));
    if (next >= 100) {
      replayPlaying = false;
      var play = document.getElementById('ow-replay-play');
      if (play) play.textContent = 'Lecture';
      return;
    }
    replayTimer = window.setTimeout(tickReplay, 400);
  }

  function loadMissionReplay() {
    var api = ow();
    if (!api) return;
    api.api('/api/replay/mission/' + encodeURIComponent(missionId())).then(function (payload) {
      missionFrames = (payload && payload.timeline) || [];
      if (!missionFrames.length) {
        toast('Aucune mission enregistrée pour rejouer.');
        return;
      }
      toast('Replay de mission chargé (' + missionFrames.length + ' instants).');
      applyMissionFrame(0);
    }).catch(function () { toast('Replay de mission indisponible.'); });
  }

  function clearMissionGhosts() {
    var api = ow();
    Object.keys(missionGhosts).forEach(function (id) {
      if (api) try { api.map.removeLayer(missionGhosts[id]); } catch (e) {}
      delete missionGhosts[id];
    });
  }

  function applyMissionFrame(pct) {
    var api = ow();
    if (!api || !missionFrames.length) return;
    var idx = Math.max(0, Math.min(missionFrames.length - 1, Math.round((pct / 100) * (missionFrames.length - 1))));
    var frame = missionFrames[idx];
    var now = document.getElementById('ow-replay-now');
    var live = document.getElementById('ow-replay-live');
    if (now) now.textContent = frame.timestamp || ('T+' + idx);
    if (live) live.textContent = 'Mission';
    var seen = {};
    (frame.units || []).forEach(function (u) {
      var id = String(u.unitId || u.callsign || '');
      var x = Number(u.pos_x != null ? u.pos_x : u.x);
      var y = Number(u.pos_y != null ? u.pos_y : u.y);
      if (!id || !isFinite(x) || !isFinite(y)) return;
      seen[id] = true;
      var ll = api.worldToLatLng(x, y);
      if (!missionGhosts[id]) {
        missionGhosts[id] = L.circleMarker(ll, { radius: 5, color: '#8b8bf0', weight: 2 }).addTo(api.map);
        if (missionGhosts[id].bindTooltip) missionGhosts[id].bindTooltip(String(u.callsign || id), { permanent: true, direction: 'right' });
      } else {
        missionGhosts[id].setLatLng(ll);
      }
    });
    Object.keys(missionGhosts).forEach(function (id) {
      if (!seen[id]) {
        api.map.removeLayer(missionGhosts[id]);
        delete missionGhosts[id];
      }
    });
  }

  function ready() {
    if (!ow()) {
      window.setTimeout(ready, 50);
      return;
    }
    loadRecents();
    loadRealismConfig(); // Charger config réalisme
    bootGeo();
    bindReplay();
    loadTraffic();
    loadRelays();
    loadSitreps();
    window.setInterval(loadTraffic, 10000);
    window.setInterval(loadRelays, 15000);
    window.setInterval(renderMarkerIntel, 15000);
  }

  window.OverwatchOps = {
    promptShape: promptShape,
    promptMarker: promptMarker,
    openSitrep: openSitrep,
    openIntel: openIntel,
    openChatGrid: openChatGrid,
    openPing: openPing,
    layersHtml: layersHtml,
    bindLayers: bindLayers,
    drawLos: drawLos,
    afterRenderMap: afterRenderMap,
    loadRelays: loadRelays,
    getRelays: function () { return relays.slice(); },
    dropSitrepPin: function (id) {
      sitreps = sitreps.filter(function (row) { return String(row.id) !== String(id); });
      sitrepPins = sitrepPins.filter(function (layer) {
        if (layer && layer._owPin && String(layer._owPin.id) === String(id)) {
          var api = ow();
          if (api) try { api.map.removeLayer(layer); } catch (e) {}
          return false;
        }
        return true;
      });
    },
    missionId: missionId,
    setArmaRows: function (rows) { window.__owArmaRows = rows || []; renderMarkerIntel(); }
  };
  ready();
})();
