(function () {
  'use strict';

  if (!window.ATAK_OVERWATCH_BETA || !window.L || window.__OVERWATCH_STANDALONE__) return;
  if (!document.getElementById('ow-map')) return;
  window.__OVERWATCH_STANDALONE__ = true;

  var LOOK_KEY = 'athena:atak-fond-look';
  var DISCLAIMER_KEY = 'athena:overwatch-disclaimer-hidden';
  var POLL_KEY = 'athena:overwatch-poll-ms';
  var THEME_KEY = 'athena:overwatch-theme';
  var STYLE_KEY = 'athena:overwatch-marker-style';
  var COLOR_FRIEND_KEY = 'athena:overwatch-color-friend';
  var COLOR_HOSTILE_KEY = 'athena:overwatch-color-hostile';
  var ASIDE_KEY = 'athena:overwatch-aside-widths-v2';
  var DRAW_COLOR_KEY = 'athena:overwatch-draw-color';
  var DRAW_WIDTH_KEY = 'athena:overwatch-draw-width';
  var TILE_CACHE = 'athena-overwatch-tiles-v1';
  var LABEL_SIZE_KEY = 'athena:overwatch-label-size';

  function proxiedTilePattern(pattern) {
    if (window.OverwatchTheaterProjection && window.OverwatchTheaterProjection.proxiedTilePattern) {
      return window.OverwatchTheaterProjection.proxiedTilePattern(pattern);
    }
    var raw = String(pattern || '');
    if (!/^https?:\/\//i.test(raw)) return raw;
    try {
      if (new URL(raw, window.location.href).hostname === window.location.hostname) return raw;
    } catch (e0) {}
    var api = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
    var encoded = encodeURIComponent(raw)
      .replace(/%7Bz%7D/gi, '{z}')
      .replace(/%7Bx%7D/gi, '{x}')
      .replace(/%7By%7D/gi, '{y}');
    return api + '/api/atak/tiles?u=' + encoded;
  }
  var ICON_SIZE_KEY = 'athena:overwatch-icon-size';
  var SETTINGS_COLLAPSED_KEY = 'athena:overwatch-settings-collapsed';
  var CHAT_READ_KEY = 'athena:ow-chat-read-v1';
  var EMPTY_DISMISS_KEY = 'athena:overwatch-empty-dismissed';

  var config = window.ATAK_MAP_CONFIG || {};
  var apiBase = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
  var mapId = Number(window.ATAK_DEFAULT_MAP_ID || 1);
  var user = window.ATAK_USER || {};
  var authorName = String(user.callsign || user.displayName || 'Poste').trim() || 'Poste';

  var markers = {};
  var pingMarkers = {};
  var postedMarkers = {};
  var shapeLayers = {};
  var units = [];
  var shapes = [];
  var channels = [];
  var chatMessages = [];
  var supportMessages = [];
  var unreadByChannel = {};
  var photos = [];
  var nineLines = [];
  var casRows = [];
  var airAssets = [];
  var gpsVehicles = [];
  var gpsVehicleMarkers = {};
  var airAssetMarkers = {};
  var filterWave = false;
  var medevacs = [];
  var zoneAlerts = [];
  var selected = null;
  var bftSectState = { live: false, offline: false };
  var ctxTarget = null;
  var lastRx = 0;
  var requestStarted = 0;
  var pollMs = 5000;
  var pollTimer = null;
  var realtimeOn = false;
  var activeChannel = 'general';
  var activeTool = 'cursor';
  var draftPoints = [];
  var draftLayer = null;
  var ctxLatLng = null;
  var measureFrom = null;
  var splitTarget = null;
  var hiddenLayers = {};
  var tracksOn = false;
  var trackLines = {};
  var trackSamples = {};
  var squadLinkLayers = [];
  var freehandOn = false;
  var followOn = false;
  var rangeRingLayers = [];
  var losLayer = null;
  var losGroups = [];
  var lastShapeId = 0;
  var drawMode = false;
  var shapeUndoStack = [];
  var shapeRedoStack = [];
  var poRows = [];
  var poLayers = [];
  var poAnnounced = {};
  var poPlaceSession = [];
  var rallyRows = [];
  var rallyLayers = [];
  var RALLY_RADIUS_M = 50;
  var groupTasks = [];
  var canIssueGroupTasks = true;
  var canIssueAlert = !!(window.ATAK_CAPS && window.ATAK_CAPS.canIssueAlert);
  var lastClickWorld = null;
  var lastClickGrid = '';
  var sceneRows = [];
  var sceneCache = [];
  var sceneCacheMapId = 0;
  var scenePreloadPromise = null;
  var sceneCanvas = null;
  var sceneCtx = null;
  var sceneDrawFrame = 0;
  var sceneZooming = false;
  var lastSceneObject = null;
  var hoverSceneId = null;
  var measureKeepLayer = null;
  var lastTaskSelectSig = '';
  var lastFsSelectSig = '';
  var terminals = [];
  var armaMarkerLayers = {};
  var armaMarkerRows = [];
  var presenceLayer = null;
  var dragDrawOn = false;
  var dragMoved = false;
  var dragPending = false;
  var dragStartPt = null;
  var dragStartLl = null;
  var COLLAPSE_KEY = 'athena:overwatch-settings-collapsed';
  var GROUP_TASK_TYPES = {
    MOVE: 'Se déplacer',
    HOLD: 'Tenir la position',
    RECON: 'Reconnaissance',
    QRF: 'Force de réaction'
  };

  var crsOpt = config.crs || {};
  var factorx = crsOpt.factorx != null ? Number(crsOpt.factorx) : 0.006839;
  var factory = crsOpt.factory != null ? Number(crsOpt.factory) : 0.006836;
  var tileWidth = crsOpt.tileWidth != null ? Number(crsOpt.tileWidth) : 212;
  var CRS = typeof window.MGRS_CRS === 'function' ? window.MGRS_CRS(factorx, factory, tileWidth) : L.CRS.Simple;
  var worldSize = Number(config.worldSize || 30720);
  var maxNative = Number(config.maxZoom != null ? config.maxZoom : 6);
  var maxZoom = maxNative + 2;

  var map = L.map('ow-map', {
    crs: CRS,
    zoomControl: false,
    attributionControl: true,
    minZoom: Number(config.minZoom || 0),
    maxZoom: maxZoom
  });

  function worldToLatLng(x, y) {
    return L.latLng(Number(y) + Number(config.offsetY || 0), Number(x) + Number(config.offsetX || 0));
  }
  function latLngToWorld(ll) {
    return { x: Number(ll.lng) - Number(config.offsetX || 0), y: Number(ll.lat) - Number(config.offsetY || 0) };
  }

  var southWest = worldToLatLng(0, 0);
  var northEast = worldToLatLng(worldSize, worldSize);
  var bounds = L.latLngBounds(southWest, northEast);

  var baseTileLayer = null;
  if (config.tilePattern) {
    var tilePattern = proxiedTilePattern(config.tilePattern);
    baseTileLayer = L.tileLayer(tilePattern, {
      tileSize: Number(config.tileSize || 212),
      minZoom: Number(config.minZoom || 0),
      maxZoom: maxZoom,
      maxNativeZoom: maxNative,
      noWrap: true,
      crossOrigin: true,
      bounds: bounds,
      attribution: config.attribution || '&copy; Bohemia Interactive'
    });
    baseTileLayer.on('tileload', function (ev) {
      var src = ev && ev.tile && ev.tile.src;
      if (!src || !window.caches) return;
      caches.open(TILE_CACHE).then(function (cache) {
        return fetch(src, { mode: 'cors', credentials: 'omit' }).then(function (res) {
          if (res && res.ok) cache.put(src, res);
        });
      }).catch(function () {});
    });
    baseTileLayer.on('tileerror', function (ev) {
      var img = ev && ev.tile;
      if (!img || !window.caches) return;
      caches.open(TILE_CACHE).then(function (cache) {
        return cache.match(img.src).then(function (hit) {
          if (hit) return hit.blob().then(function (blob) { img.src = URL.createObjectURL(blob); });
        });
      }).catch(function () {});
    });
    baseTileLayer.addTo(map);
  }

  var center = Array.isArray(config.center) ? config.center : [15000, 15000];
  map.setView(center, Number(config.defaultZoom || 3));
  map.setMaxBounds(bounds.pad(0.08));

  if (window.ATAKAerial && typeof window.ATAKAerial.attach === 'function') {
    window.ATAKAerial.attach(map, config);
  }

  function clean(value, fallback) {
    var text = String(value == null ? '' : value).trim();
    return text || fallback || '—';
  }
  function callsign(unit) { return clean(unit.call_sign || unit.callsign || unit.name || unit.label, 'Contact'); }
  function group(unit) { return clean(unit.fire_team_label || unit.group_name || unit.group, 'Sans groupe'); }
  function unitId(unit) { return String(unit.id || unit.uuid || callsign(unit)); }
  function point(unit) {
    var x = Number(unit.pos_x != null ? unit.pos_x : unit.x);
    var y = Number(unit.pos_y != null ? unit.pos_y : unit.y);
    return Number.isFinite(x) && Number.isFinite(y) && (Math.abs(x) > .5 || Math.abs(y) > .5) ? worldToLatLng(x, y) : null;
  }
  function initials(value) { return String(value).replace(/[^a-z0-9]/gi, '').slice(0, 2).toUpperCase() || '•'; }
  function escapeHtml(value) {
    var node = document.createElement('span'); node.textContent = String(value == null ? '' : value); return node.innerHTML;
  }
  function side(unit) {
    var raw = String(unit.side || unit.faction || unit.iff || unit.type || '').toLowerCase();
    if (/hostile|opfor|east|enemy/.test(raw)) return 'hostile';
    if (/unknown|civ|neutral/.test(raw)) return 'unknown';
    return 'friendly';
  }
  function isAir(unit) {
    var raw = String(unit.type || unit.role || unit.vehicle_class || '').toLowerCase();
    return /air|heli|uav|drone|plane|jet/.test(raw);
  }
  function isVehicle(unit) {
    var raw = String(unit.type || unit.role || unit.vehicle_class || '').toLowerCase();
    return /vehicle|car|tank|armor|truck|boat/.test(raw) && !isAir(unit);
  }
  function flagOn(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
  }
  function profileOf(unit) {
    var mapUsers = window.ATAK_CALLSIGN_TO_USER || {};
    var key = callsign(unit).toUpperCase();
    return mapUsers[key] || null;
  }
  function isWave(unit) {
    var extra = extraOf(unit);
    return flagOn(extra.wr_mpu5) || flagOn(extra.wr_gateway) || flagOn(extra.wr_bridge);
  }
  function formatSpeedDisplay(speedMs) {
    if (speedMs == null || !Number.isFinite(speedMs) || speedMs < 0) return '';
    var kmh = speedMs * 3.6;
    if (kmh < 10) return kmh.toFixed(1).replace('.', ',') + ' km/h (à pied)';
    return Math.round(kmh) + ' km/h';
  }
  function seatLabelFr(seat) {
    var s = String(seat || '').toLowerCase();
    if (s === 'driver' || s === 'pilot') return 'Pilote';
    if (s === 'commander' || s === 'leader') return 'Chef';
    if (s === 'gunner') return 'Tireur';
    if (s === 'copilot') return 'Copilote';
    if (s === 'cargo' || s === 'passenger') return 'Passager';
    return seat ? String(seat) : 'À bord';
  }
  function airOccupantsList(row) {
    var occ = row && (row.occupants || row.crew);
    if (typeof occ === 'string') {
      try { occ = JSON.parse(occ); } catch (e) { occ = []; }
    }
    return Array.isArray(occ) ? occ : [];
  }
  function vehicleClassLabel(kind) {
    var k = String(kind || '').toUpperCase();
    if (k === 'HELICOPTER') return 'Hélicoptère';
    if (k === 'FIXED_WING') return 'Avion';
    if (k === 'UAV') return 'Drone';
    if (k === 'BOAT') return 'Embarcation';
    if (k === 'ARTILLERY') return 'Artillerie';
    return 'Véhicule';
  }
  function extraOf(unit) {
    var extra = unit.extra || unit.meta || {};
    if (typeof extra === 'string') {
      try { extra = JSON.parse(extra); } catch (e) { extra = {}; }
    }
    return extra && typeof extra === 'object' ? extra : {};
  }
  function firstNumber() {
    var i;
    for (i = 0; i < arguments.length; i += 1) {
      if (arguments[i] == null || arguments[i] === '') continue;
      var n = Number(arguments[i]);
      if (Number.isFinite(n)) return n;
    }
    return null;
  }
  function armaOf(unit) {
    var extra = extraOf(unit);
    var arma = unit.source_arma || extra.source_arma || {};
    return arma && typeof arma === 'object' ? arma : {};
  }
  function linkLabel(unit) {
    var extra = extraOf(unit);
    var raw = String(unit.link_state || extra.link_state || extra.linkState || '').toLowerCase();
    if (raw === 'linked') return 'ouverte';
    if (raw === 'degraded') return 'dégradée';
    if (raw === 'connecting') return 'en cours';
    if (raw === 'offline' || raw === 'disconnected' || raw === 'lost') return 'perdue';
    return raw ? raw : 'non transmise';
  }
  function txLabel(unit) {
    var extra = extraOf(unit);
    var raw = String(extra.transmit_mode || extra.transmitMode || unit.transmit_mode || '').toLowerCase();
    if (raw === 'full') return 'complète';
    if (raw === 'position') return 'position seule';
    if (raw === 'blocked') return 'bloquée';
    var speaking = extra.radio_speaking || extra.radio_tx;
    if (speaking) return 'radio en cours';
    return raw ? raw : 'non transmise';
  }
  function maskIpForDisplay(raw) {
    var ip = String(raw || '').trim();
    if (!ip) return '';
    var v4 = ip.match(/(\d{1,3}(?:\.\d{1,3}){3})/);
    if (v4) {
      var octets = v4[1].split('.');
      return octets[0] + '.' + octets[1] + '.' + octets[2] + '.·';
    }
    if (ip.indexOf(':') >= 0) {
      var cleaned = ip.replace(/^::ffff:/i, '');
      var v4mapped = cleaned.match(/(\d{1,3}(?:\.\d{1,3}){3})/);
      if (v4mapped) {
        var mapped = v4mapped[1].split('.');
        return mapped[0] + '.' + mapped[1] + '.' + mapped[2] + '.·';
      }
      var groups = ip.split(':').filter(function (part) { return part !== ''; });
      if (groups.length >= 2) return groups.slice(0, 2).join(':') + ':·';
    }
    return ip.length > 10 ? ip.slice(0, 8) + '·' : ip;
  }
  function displayValue(raw) {
    var text = String(raw == null ? '' : raw).trim();
    if (!text) return '';
    var low = text.toLowerCase();
    if (low === 'n/a' || low === 'na' || low === 'none' || low === 'null' || low === 'undefined' || low === 'unknown' || low === '-' || low === 'non transmis' || low === 'non transmise') return '';
    return text;
  }
  function kv(label, value, mono) {
    var shown = displayValue(value);
    if (!shown) return '';
    return '<span>' + escapeHtml(label) + '</span><span' + (mono ? ' class="ow-mono"' : '') + '>' + escapeHtml(shown) + '</span>';
  }
  function statCell(label, value, opts) {
    opts = opts || {};
    var shown = displayValue(value) || '—';
    var cls = 'ow-stat-v' + (opts.mono ? ' is-mono' : '') + (opts.dim ? ' is-dim' : '');
    return '<div class="ow-stat"><div class="ow-stat-k">' + escapeHtml(label) + '</div><div class="' + cls + '">' +
      escapeHtml(shown) + '</div></div>';
  }
  function pillHtml(text, kind) {
    if (!text) return '';
    return '<span class="ow-pill is-' + escapeHtml(kind || 'ok') + '">' + escapeHtml(text) + '</span>';
  }
  function discBlock(title, inner, warn) {
    return '<details class="ow-disc">' +
      '<summary' + (warn ? ' class="is-warn"' : '') + '>' + escapeHtml(title) +
      '<span class="ow-disc-car" aria-hidden="true">▾</span></summary>' +
      '<div class="ow-disc-body">' + inner + '</div></details>';
  }
  function btnIcon(d) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">' + d + '</svg>';
  }
  function clearDrawerContactHead() {
    var av = document.getElementById('ow-drawer-avatar');
    if (av) {
      av.hidden = true;
      av.innerHTML = '';
      av.className = 'ow-drawer-avatar';
    }
  }
  function setDrawerContactHead(unit) {
    var av = document.getElementById('ow-drawer-avatar');
    var disc = isDisconnected(unit);
    var sideId = side(unit);
    var profile = profileOf(unit);
    if (av) {
      av.hidden = false;
      if (profile && profile.avatarUrl) {
        av.innerHTML = '<img class="ow-bft-avatar-img" src="' + escapeHtml(profile.avatarUrl) + '" alt="" width="32" height="32" loading="lazy"><span class="ow-bft-status"></span>';
      } else {
        av.innerHTML = escapeHtml(initials(callsign(unit))) + '<span class="ow-bft-status"></span>';
      }
      av.className = 'ow-drawer-avatar' +
        (disc ? ' is-offline' : ' is-live') +
        (sideId === 'hostile' ? ' is-hostile' : '') +
        (sideId === 'unknown' ? ' is-unknown' : '');
    }
    var role = clean(unit.role, '');
    var grp = group(unit);
    var sub = [role, grp && grp !== '—' ? grp : ''].filter(Boolean).join(' · ');
    document.getElementById('ow-drawer-kicker').textContent = sub || 'Contact';
    document.getElementById('ow-drawer-title').textContent = callsign(unit);
  }
  function terminalFor(unit) {
    var cs = callsign(unit).toLowerCase();
    var extra = extraOf(unit);
    var uid = String(unit.terminal_uid || extra.terminal_uid || '').trim();
    var found = null;
    terminals.forEach(function (t) {
      var tcs = String(t.operator_callsign || t.callsign || '').trim().toLowerCase();
      var tuid = String(t.terminal_uid || '').trim();
      if ((tcs && tcs === cs) || (uid && tuid && tuid === uid)) found = t;
    });
    return found;
  }
  function formatSeen(iso) {
    if (!iso) return '';
    var raw = String(iso);
    var t = Date.parse(raw.indexOf('T') >= 0 ? raw : raw.replace(' ', 'T'));
    if (isNaN(t)) return raw;
    var diff = Date.now() - t;
    if (Math.abs(diff) < 60000) return 'à l’instant';
    if (diff > 0 && diff < 3600000) return 'il y a ' + Math.round(diff / 60000) + ' min';
    if (diff > 0 && diff < 86400000) return 'il y a ' + Math.round(diff / 3600000) + ' h';
    try { return new Date(t).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); } catch (e) { return raw; }
  }
  function loadTerminals() {
    return api('/api/atak/terminals').then(function (payload) {
      terminals = asList(payload, 'terminals');
    }).catch(function () { terminals = []; });
  }
  function squadKey(unit) {
    var id = String(unit.fire_team_id || '').trim();
    if (id) return 'id:' + id;
    var label = String(unit.fire_team_label || unit.group_name || unit.group || '').trim();
    if (label && label !== '—' && label !== 'UNITÉ NON AFFECTÉE') return 'lb:' + label.toLowerCase();
    return '';
  }
  function armaGroupName(unit) {
    var extra = extraOf(unit);
    return String(unit.group_name || extra.group_name || extra.group || unit.group || unit.fire_team_label || '').trim();
  }
  function listSquads() {
    var groups = {};
    units.forEach(function (unit) {
      var key = squadKey(unit);
      if (!key) return;
      if (!groups[key]) {
        var ftId = String(unit.fire_team_id || '').trim();
        var gName = armaGroupName(unit);
        groups[key] = {
          key: key,
          label: group(unit),
          targetType: ftId ? 'fire_team' : 'group',
          targetRef: ftId || gName,
          members: []
        };
      }
      groups[key].members.push(unit);
    });
    return Object.keys(groups).sort().map(function (key) { return groups[key]; });
  }
  function groupTaskFormHtml() {
    return '<form class="ow-form-grid ow-group-task-form">' +
      '<label>Groupe<select name="squad" required><option value="">Choisir un groupe</option></select></label>' +
      '<div class="ow-field-row">' +
      '<label>Tâche<select name="type">' +
      '<option value="MOVE">Se déplacer</option>' +
      '<option value="HOLD">Tenir</option>' +
      '<option value="RECON">Reconnaître</option>' +
      '<option value="QRF">Force de réaction</option>' +
      '</select></label>' +
      '<label>Urgence<span class="ow-select ow-select--prio"><i class="ow-prio-dot" data-prio-dot></i><select name="priority">' +
      '<option value="ROUTINE">Sans urgence</option>' +
      '<option value="IMPORTANT" selected>Normale</option>' +
      '<option value="URGENT">Urgente</option>' +
      '<option value="CONTACT">Contact</option>' +
      '</select></span></label>' +
      '</div>' +
      '<label>Consignes<textarea name="payload" maxlength="800" placeholder="Ce que le groupe doit faire…"></textarea></label>' +
      '<label>Point à atteindre<select name="po"><option value="">Aucun</option></select></label>' +
      '<button class="ow-primary" type="submit">' +
      btnIcon('<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>') +
      'Transmettre la tâche</button></form>' +
      discBlock('Tâches transmises', '<div class="ow-group-task-list"></div>');
  }
  function fullscreenAlertFormHtml(preset) {
    var locked = preset && preset.dest ? '<input type="hidden" name="dest" value="' + escapeHtml(preset.dest) + '">' : '';
    var destField = preset && preset.dest ? '' :
      '<label>Destinataire<select name="dest" required><option value="all">Tous les opérateurs</option></select></label>';
    var ph = preset && preset.solo
      ? 'Ce que l’opérateur doit voir sur l’écran…'
      : 'Ce que les opérateurs doivent voir…';
    return '<form class="ow-form-grid ow-fs-alert-form">' + locked + destField +
      '<label>' + (preset && preset.solo ? '' : 'Message') +
      '<textarea name="message" required maxlength="280" placeholder="' + escapeHtml(ph) + '"></textarea></label>' +
      '<button class="ow-primary" type="submit">Envoyer l’alerte</button></form>' +
      '<p class="ow-help">Recouvre l’écran du téléphone jusqu’à fermeture ou expiration.</p>';
  }
  function fillFsAlertSelects(force) {
    var squads = listSquads();
    var friends = units.filter(function (unit) { return side(unit) !== 'hostile'; });
    var sig = squads.map(function (row) { return row.key; }).join('|') + '#' +
      friends.map(function (unit) { return callsign(unit); }).join('|') + '#' + (canIssueGroupTasks ? '1' : '0') +
      '#' + (canIssueAlert ? '1' : '0');
    if (!force && sig === lastFsSelectSig) return;
    lastFsSelectSig = sig;
    document.querySelectorAll('.ow-fs-alert-form').forEach(function (form) {
      var destSel = form.querySelector('select[name="dest"]');
      var prev = destSel ? destSel.value : '';
      if (destSel) {
        destSel.innerHTML = (canIssueAlert ? '<option value="all">Tous les opérateurs</option>' : '') +
          squads.map(function (row) {
            return '<option value="squad:' + escapeHtml(row.key) + '">Groupe · ' + escapeHtml(row.label) +
              ' · ' + row.members.length + ' opérateur' + (row.members.length > 1 ? 's' : '') + '</option>';
          }).join('') +
          friends.slice(0, 40).map(function (unit) {
            return '<option value="unit:' + escapeHtml(callsign(unit)) + '">Opérateur · ' +
              escapeHtml(callsign(unit)) + '</option>';
          }).join('');
        if (destSel.options.length === 0) {
          destSel.innerHTML = '<option value="" disabled selected>Aucun destinataire disponible</option>';
        } else if (prev && Array.prototype.some.call(destSel.options, function (opt) { return opt.value === prev; })) {
          destSel.value = prev;
        }
      }
      var submit = form.querySelector('[type="submit"]');
      if (submit) submit.disabled = !canIssueGroupTasks;
      var hint = form.querySelector('.ow-fs-denied');
      if (!canIssueGroupTasks) {
        if (!hint) {
          hint = document.createElement('p');
          hint.className = 'ow-help ow-fs-denied';
          hint.textContent = 'Votre profil ne permet pas d’envoyer une alerte depuis le poste.';
          form.appendChild(hint);
        }
      } else if (!canIssueAlert) {
        if (!hint) {
          hint = document.createElement('p');
          hint.className = 'ow-help ow-fs-denied';
          hint.textContent = 'L’alerte à tous les opérateurs est réservée au commandement. Vous pouvez encore viser un groupe ou un indicatif.';
          form.appendChild(hint);
        } else {
          hint.textContent = 'L’alerte à tous les opérateurs est réservée au commandement. Vous pouvez encore viser un groupe ou un indicatif.';
        }
      } else if (hint) {
        hint.remove();
      }
    });
  }
  function bindFsAlertForms(root) {
    (root || document).querySelectorAll('.ow-fs-alert-form').forEach(function (form) {
      if (form.dataset.bound === '1') return;
      form.dataset.bound = '1';
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        submitFullscreenAlert(form);
      });
    });
  }
  function mountFsAlertPanel() {
    var host = document.getElementById('ow-fs-alert-host');
    if (!host || host.dataset.ready === '1') return;
    host.innerHTML = discBlock('Alerte plein écran', fullscreenAlertFormHtml(), true);
    host.dataset.ready = '1';
    bindFsAlertForms(host);
    fillFsAlertSelects(true);
  }
  function openFullscreenAlertForm(dest) {
    mountFsAlertPanel();
    document.getElementById('ow-drawer').hidden = true;
    openView('comms');
    switchChatTab('squads');
    fillFsAlertSelects(true);
    if (dest) {
      document.querySelectorAll('.ow-fs-alert-form [name="dest"]').forEach(function (sel) { sel.value = dest; });
    }
    var ta = document.querySelector('[data-chat-panel="squads"] textarea[name="message"]');
    if (ta) ta.focus();
  }
  function submitFullscreenAlert(form) {
    var data = new FormData(form);
    var dest = String(data.get('dest') || 'all');
    var message = String(data.get('message') || '').trim();
    if (!message) { toast('Saisissez le message à afficher sur l’écran.'); return; }
    if (dest === 'all' && !canIssueAlert) {
      toast('Votre fonction ne permet pas d’alerter tous les opérateurs.');
      return;
    }
    var targetType = 'all';
    var targetRef = '';
    var targetLabel = 'Tous les opérateurs';
    var fallback = null;
    if (dest.indexOf('squad:') === 0) {
      var key = dest.slice(6);
      var squad = listSquads().find(function (row) { return row.key === key; });
      if (!squad || !squad.targetRef) { toast('Choisissez un groupe.'); return; }
      targetType = squad.targetType;
      targetRef = squad.targetRef;
      targetLabel = squad.label;
      var gName = armaGroupName(squad.members[0] || {}) || squad.label;
      if (squad.targetType === 'fire_team' && gName) fallback = { targetType: 'group', targetRef: gName };
    } else if (dest.indexOf('unit:') === 0) {
      var cs = dest.slice(5);
      if (!cs) { toast('Choisissez un opérateur.'); return; }
      targetType = 'solo';
      targetRef = cs;
      targetLabel = cs;
    }
    function send(kind, ref) {
      return api('/api/atak/orders', {
        method: 'POST',
        body: {
          mapId: mapId,
          issuer: authorName,
          order_type: 'NOTIFY_FULL',
          type_label: 'Alerte plein écran',
          target_type: kind,
          target_ref: ref,
          target_label: targetLabel,
          payload: message,
          priority: 'URGENT',
          radio_sim: false
        }
      });
    }
    send(targetType, targetRef).catch(function (err) {
      if (String(err && err.message) === '400' && fallback) return send(fallback.targetType, fallback.targetRef);
      throw err;
    }).then(function () {
      toast('Alerte transmise à ' + targetLabel + '.');
      var locked = form.querySelector('input[name="dest"]');
      form.reset();
      if (locked) locked.value = dest;
      else {
        var sel = form.querySelector('select[name="dest"]');
        if (sel && dest) sel.value = dest;
      }
      fillFsAlertSelects(true);
    }).catch(function (err) {
      var status = String(err && err.message || '');
      if (status === '403') toast('Votre profil ne permet pas d’envoyer une alerte depuis le poste.');
      else if (status === '503') toast('Les alertes plein écran ne sont pas encore disponibles sur ce serveur.');
      else if (status === '400') toast('Destinataire introuvable. Attendez une mise à jour des positions.');
      else toast('Alerte refusée. Vérifiez le destinataire et le message.');
    });
  }
  function fillGroupTaskSelects(force) {
    var squads = listSquads();
    var sig = squads.map(function (row) { return row.key; }).join('|') + '#' +
      poRows.map(function (row) { return row.id; }).join('|') + '#' + (canIssueGroupTasks ? '1' : '0');
    if (!force && sig === lastTaskSelectSig) {
      renderGroupTaskLists();
      fillFsAlertSelects();
      return;
    }
    lastTaskSelectSig = sig;
    document.querySelectorAll('.ow-group-task-form').forEach(function (form) {
      var squadSel = form.querySelector('[name="squad"]');
      var poSel = form.querySelector('[name="po"]');
      var prevSquad = squadSel ? squadSel.value : '';
      var prevPo = poSel ? poSel.value : '';
      if (squadSel) {
        squadSel.innerHTML = '<option value="">Choisir un groupe</option>' + squads.map(function (row) {
          return '<option value="' + escapeHtml(row.key) + '">' + escapeHtml(row.label) + ' · ' +
            row.members.length + ' opérateur' + (row.members.length > 1 ? 's' : '') + '</option>';
        }).join('');
        if (prevSquad && squads.some(function (row) { return row.key === prevSquad; })) squadSel.value = prevSquad;
      }
      if (poSel) {
        poSel.innerHTML = '<option value="">Aucun</option>' + poRows.map(function (row) {
          return '<option value="' + escapeHtml(String(row.id)) + '">' + escapeHtml(row.label) +
            (row.reached ? ' — atteint' : '') + '</option>';
        }).join('');
        if (prevPo && poRows.some(function (row) { return String(row.id) === prevPo; })) poSel.value = prevPo;
      }
      var submit = form.querySelector('[type="submit"]');
      if (submit) submit.disabled = !canIssueGroupTasks;
      var hint = form.querySelector('.ow-task-denied');
      if (!canIssueGroupTasks) {
        if (!hint) {
          hint = document.createElement('p');
          hint.className = 'ow-help ow-task-denied';
          hint.textContent = 'Votre profil ne permet pas de transmettre une tâche depuis le poste.';
          form.appendChild(hint);
        }
      } else if (hint) {
        hint.remove();
      }
    });
    renderGroupTaskLists();
    fillFsAlertSelects();
  }
  function renderGroupTaskLists() {
    var html = groupTasks.slice(0, 12).map(function (row) {
      var status = String(row.status || '').toUpperCase();
      var open = status !== 'CANCELLED' && status !== 'FAILED' && status !== 'DONE';
      var text = String(row.payload_display || '').trim();
      var who = String(row.ack_by || row.status_by || '').trim();
      var statusText = clean(row.status_label, 'Transmis');
      if (who && (status === 'ACK' || status === 'EXEC' || status === 'DONE' || status === 'DELIVERED')) {
        statusText += ' · ' + who;
      }
      return '<div class="ow-event"><span>' + escapeHtml(clean(row.type_label, 'Tâche')) +
        ' · ' + escapeHtml(clean(row.target_label, 'Groupe')) +
        '</span><strong>' + escapeHtml(statusText) +
        (row.priority_label ? ' · ' + escapeHtml(row.priority_label) : '') +
        '</strong>' +
        (open && canIssueGroupTasks ? '<button type="button" class="ow-tag" data-cancel-task="' +
          escapeHtml(String(row.id || '')) + '">Annuler</button>' : '') +
        '</div>' + (text ? '<p class="ow-help">' + escapeHtml(text) + '</p>' : '');
    }).join('') || '<p class="ow-help">Aucune tâche transmise pour cette mission.</p>';
    document.querySelectorAll('.ow-group-task-list').forEach(function (host) {
      host.innerHTML = html;
    });
  }
  function bindGroupTaskForms(root) {
    (root || document).querySelectorAll('.ow-group-task-form').forEach(function (form) {
      if (form.dataset.bound === '1') return;
      form.dataset.bound = '1';
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        submitGroupTask(form);
      });
    });
  }
  function mountSquadTaskPanel() {
    var host = document.getElementById('ow-group-task-host');
    if (host && host.dataset.ready !== '1') {
      host.innerHTML = groupTaskFormHtml();
      host.dataset.ready = '1';
      bindGroupTaskForms(host);
    }
    mountFsAlertPanel();
    fillGroupTaskSelects(true);
  }
  function openSquadTaskForm(key) {
    mountSquadTaskPanel();
    document.getElementById('ow-drawer').hidden = true;
    openView('comms');
    switchChatTab('squads');
    fillGroupTaskSelects(true);
    if (key) {
      document.querySelectorAll('.ow-group-task-form [name="squad"]').forEach(function (sel) { sel.value = key; });
    }
    var ta = document.querySelector('[data-chat-panel="squads"] textarea[name="payload"]');
    if (ta) ta.focus();
  }
  function submitGroupTask(form) {
    var data = new FormData(form);
    var key = String(data.get('squad') || '');
    var squad = listSquads().find(function (row) { return row.key === key; });
    if (!squad || !squad.targetRef) { toast('Choisissez un groupe.'); return; }
    var type = String(data.get('type') || 'MOVE').toUpperCase();
    if (!GROUP_TASK_TYPES[type]) type = 'MOVE';
    var priority = String(data.get('priority') || 'IMPORTANT');
    var text = String(data.get('payload') || '').trim();
    var poId = String(data.get('po') || '');
    var po = poRows.find(function (row) { return String(row.id) === poId; });
    if (!text && !po) { toast('Indiquez des consignes ou un point à atteindre.'); return; }
    var payload = text;
    if (po && Number.isFinite(Number(po.x)) && Number.isFinite(Number(po.y))) {
      payload = (payload ? payload + '\n' : '') + '@WP:' + Number(po.x).toFixed(2) + '|' +
        Number(po.y).toFixed(2) + '|GRID:' + Math.round(Number(po.x)) + ' / ' + Math.round(Number(po.y)) +
        '|LBL:' + String(po.label || '');
    }
    function send(targetType, targetRef) {
      return api('/api/atak/orders', {
        method: 'POST',
        body: {
          mapId: mapId,
          issuer: authorName,
          order_type: type,
          type_label: GROUP_TASK_TYPES[type],
          target_type: targetType,
          target_ref: targetRef,
          target_label: squad.label,
          payload: payload,
          priority: priority
        }
      });
    }
    send(squad.targetType, squad.targetRef).catch(function (err) {
      var gName = armaGroupName(squad.members[0] || {}) || squad.label;
      if (String(err && err.message) === '400' && squad.targetType === 'fire_team' && gName) {
        return send('group', gName);
      }
      throw err;
    }).then(function () {
      toast('Tâche transmise à ' + squad.label + '.');
      form.reset();
      fillGroupTaskSelects(true);
      if (key) form.querySelector('[name="squad"]').value = key;
      loadGroupTasks();
    }).catch(function (err) {
      var status = String(err && err.message || '');
      if (status === '403') toast('Votre profil ne permet pas de transmettre une tâche depuis le poste.');
      else if (status === '503') toast('Les tâches de groupe ne sont pas encore disponibles sur ce serveur.');
      else if (status === '400') toast('Ce groupe n’est plus visible sur la carte. Attendez une mise à jour des positions.');
      else toast('Tâche refusée. Vérifiez le groupe et les consignes.');
    });
  }
  function loadGroupTasks() {
    return api('/api/atak/orders?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      var rows = asList(payload, 'orders');
      groupTasks = rows.filter(function (row) {
        var kind = String(row.target_type || '').toLowerCase();
        return kind === 'group' || kind === 'fire_team';
      }).sort(function (a, b) {
        return String(b.created_at || b.updated_at || '').localeCompare(String(a.created_at || a.updated_at || ''));
      });
      if (payload && payload.canIssue === false) canIssueGroupTasks = false;
      else if (payload && payload.canIssue === true) canIssueGroupTasks = true;
      if (payload && payload.canIssueAlert === false) canIssueAlert = false;
      else if (payload && payload.canIssueAlert === true) canIssueAlert = true;
      fillGroupTaskSelects();
    }).catch(function () {});
  }
  function squadColor(unit) {
    var raw = String(unit.fire_team_color || '').trim();
    if (/^#?[0-9a-f]{3,8}$/i.test(raw)) return raw.charAt(0) === '#' ? raw : '#' + raw;
    return markerPrefs().friend;
  }
  var STALE_HIDE_SEC = 15 * 60;
  var STALE_DEAD_SEC = 2 * 60 * 60;
  var LIVE_TTL_SEC = 120;
  var DELAYED_SEC = 72;
  var DISC_COLOR = '#8d9592';
  function stampUnitAges(list) {
    var now = Date.now();
    return (list || []).map(function (unit) {
      var next = Object.assign({}, unit);
      next._receivedAt = now;
      var apiAge = Number(unit.age_seconds);
      next._ageAtReceive = Number.isFinite(apiAge) && apiAge >= 0 ? apiAge : NaN;
      return next;
    });
  }
  function unitAgeSec(unit) {
    if (!unit) return NaN;
    var apiAge = Number(unit._ageAtReceive);
    if (!Number.isFinite(apiAge) || apiAge < 0) apiAge = Number(unit.age_seconds);
    if (Number.isFinite(apiAge) && apiAge >= 0) {
      var received = Number(unit._receivedAt);
      if (Number.isFinite(received) && received > 0) {
        return Math.max(0, apiAge + (Date.now() - received) / 1000);
      }
      return apiAge;
    }
    var extra = extraOf(unit || {});
    var stamped = extra.last_seen_at || unit.updated_at || unit.last_seen_at || unit.last_seen;
    if (!stamped) return NaN;
    var raw = String(stamped).trim();
    var iso = raw.indexOf('T') >= 0 ? raw : raw.replace(' ', 'T');
    if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += 'Z';
    var t = Date.parse(iso);
    if (!Number.isFinite(t)) return NaN;
    return Math.max(0, (Date.now() - t) / 1000);
  }
  function isTrackedAi(unit) {
    var extra = extraOf(unit);
    if (extra.ally_ai || extra.enemy_ai || extra.is_ai) return true;
    var src = String(extra.source || '').toLowerCase();
    if (src === 'ally' || src === 'enemy') return true;
    var cs = callsign(unit).toUpperCase();
    return cs.indexOf('ALLY-') === 0 || cs.indexOf('ENY-') === 0;
  }
  function isDisconnected(unit) {
    var st = String(unit.status || '').toLowerCase();
    if (st === 'offline' || st === 'disconnected') return true;
    var extra = extraOf(unit);
    var link = String(unit.link_state || extra.link_state || extra.linkState || '').toLowerCase();
    return link === 'offline' || link === 'disconnected' || link === 'lost';
  }
  function tooOldToShow(unit) {
    var age = unitAgeSec(unit);
    if (Number.isFinite(age) && age > STALE_DEAD_SEC) return true;
    if (isTrackedAi(unit) || !isDisconnected(unit)) return false;
    return Number.isFinite(age) && age > STALE_HIDE_SEC;
  }
  function hasSquadColor(unit) {
    return /^#?[0-9a-f]{3,8}$/i.test(String(unit.fire_team_color || '').trim());
  }
  function squadColorOn() {
    var box = document.getElementById('ow-squad-color');
    return !!(box && box.checked);
  }
  function visibleUnits() {
    return units.filter(function (unit) { return !tooOldToShow(unit); });
  }
  function unitHeading(unit) {
    var extra = extraOf(unit);
    var arma = armaOf(unit);
    var motion = unit.motion || extra.motion || {};
    var n = firstNumber(
      extra.movement_heading,
      unit.movement_heading,
      arma.movement_heading_deg,
      extra.heading_object,
      unit.heading_object,
      arma.heading_deg,
      extra.heading,
      unit.heading,
      motion.heading
    );
    return n != null ? ((n % 360) + 360) % 360 : null;
  }
  function unitSpeed(unit) {
    var extra = extraOf(unit);
    var arma = armaOf(unit);
    var motion = unit.motion || extra.motion || {};
    var n = firstNumber(
      extra.speed,
      extra.speed_ms,
      unit.speed_ms,
      unit.speed,
      arma.speed_ms,
      motion.speed_ms,
      motion.speed_current,
      motion.speed
    );
    return n != null && n >= 0 ? n : null;
  }
  function unitAlt(unit) {
    var extra = extraOf(unit);
    var arma = armaOf(unit);
    var n = firstNumber(
      extra.asl_z,
      extra.pos_z,
      extra.altitude,
      unit.pos_z,
      unit.altitude,
      arma.altitude_m
    );
    return n;
  }
  function drawTintEl() {
    return document.getElementById('ow-tac-color') || document.getElementById('ow-draw-color');
  }
  function drawWidthEl() {
    return document.getElementById('ow-tac-width') || document.getElementById('ow-draw-width');
  }
  function setDrawTint(color) {
    var hex = String(color || '').trim();
    if (!hex) return;
    var tac = document.getElementById('ow-tac-color');
    var main = document.getElementById('ow-draw-color');
    if (tac) tac.value = hex;
    if (main) main.value = hex;
  }
  function drawStyle() {
    var tint = drawTintEl();
    var width = drawWidthEl();
    return {
      color: (tint && tint.value) || '#00d69a',
      stroke: Number((width && width.value) || 2)
    };
  }
  function formatMeters(meters) {
    if (meters >= 1000) return (Math.round(meters / 10) / 100) + ' km';
    return Math.round(meters) + ' m';
  }
  function bearingWorld(fromLl, toLl) {
    var a = latLngToWorld(fromLl);
    var b = latLngToWorld(toLl);
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    return ((Math.atan2(dx, dy) * 180 / Math.PI) + 360) % 360;
  }
  function polygonAreaM2(latlngs) {
    if (!latlngs || latlngs.length < 3) return 0;
    var pts = latlngs.map(latLngToWorld);
    var sum = 0;
    var i;
    for (i = 0; i < pts.length; i += 1) {
      var a = pts[i];
      var b = pts[(i + 1) % pts.length];
      sum += a.x * b.y - b.x * a.y;
    }
    return Math.abs(sum) / 2;
  }
  function pathLength(latlngs) {
    var meters = 0;
    var i;
    for (i = 1; i < latlngs.length; i += 1) meters += map.distance(latlngs[i - 1], latlngs[i]);
    return meters;
  }
  function circleLatLngs(center, edge, steps) {
    var radius = map.distance(center, edge);
    var cw = latLngToWorld(center);
    var out = [];
    var i;
    var n = steps || 32;
    for (i = 0; i < n; i += 1) {
      var ang = (i / n) * Math.PI * 2;
      out.push(worldToLatLng(cw.x + Math.sin(ang) * radius, cw.y + Math.cos(ang) * radius));
    }
    return out;
  }
  function rectLatLngs(a, b) {
    return [a, L.latLng(a.lat, b.lng), b, L.latLng(b.lat, a.lng)];
  }
  function ellipseLatLngs(a, b, steps) {
    var cx = (a.lng + b.lng) / 2;
    var cy = (a.lat + b.lat) / 2;
    var rx = Math.abs(b.lng - a.lng) / 2;
    var ry = Math.abs(b.lat - a.lat) / 2;
    var out = [];
    var n = steps || 48;
    var i;
    for (i = 0; i < n; i += 1) {
      var ang = (i / n) * Math.PI * 2;
      out.push(L.latLng(cy + Math.sin(ang) * ry, cx + Math.cos(ang) * rx));
    }
    return out;
  }
  function parseShapeMeta(shape) {
    var meta = shape && shape.meta;
    if (typeof meta === 'string') {
      try { meta = JSON.parse(meta); } catch (eMeta) { meta = {}; }
    }
    return meta && typeof meta === 'object' ? meta : {};
  }
  function natoDash(meta) {
    if (meta.dash) return String(meta.dash);
    var nato = String(meta.nato || '');
    if (nato === 'phase') return '8 6';
    if (nato === 'sector') return '10 4 2 4';
    if (nato === 'highlight') return '6 4';
    return null;
  }
  function offsetPerp(aw, bw, dist) {
    var dx = bw.x - aw.x;
    var dy = bw.y - aw.y;
    var len = Math.sqrt(dx * dx + dy * dy) || 1;
    return { x: (-dy / len) * dist, y: (dx / len) * dist };
  }
  function arrowHeadPolygon(latlngs, color, wide) {
    if (!latlngs || latlngs.length < 2) return null;
    var a = latLngToWorld(latlngs[latlngs.length - 2]);
    var b = latLngToWorld(latlngs[latlngs.length - 1]);
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var len = Math.sqrt(dx * dx + dy * dy) || 1;
    var ux = dx / len;
    var uy = dy / len;
    var size = wide ? 36 : 18;
    var spread = wide ? 16 : 8;
    var tip = worldToLatLng(b.x, b.y);
    var left = worldToLatLng(b.x - ux * size + (-uy) * spread, b.y - uy * size + ux * spread);
    var right = worldToLatLng(b.x - ux * size - (-uy) * spread, b.y - uy * size - ux * spread);
    return L.polygon([tip, left, right], { color: color, fillColor: color, fillOpacity: 1, weight: 1, interactive: false });
  }
  function filledAttackLatLngs(latlngs, halfWidth) {
    if (!latlngs || latlngs.length < 2) return [];
    var left = [];
    var right = [];
    var i;
    for (i = 0; i < latlngs.length; i += 1) {
      var a = latLngToWorld(latlngs[Math.max(0, i - 1)]);
      var b = latLngToWorld(latlngs[Math.min(latlngs.length - 1, i + 1)]);
      if (i === 0) { a = latLngToWorld(latlngs[0]); b = latLngToWorld(latlngs[1]); }
      if (i === latlngs.length - 1) { a = latLngToWorld(latlngs[i - 1]); b = latLngToWorld(latlngs[i]); }
      var off = offsetPerp(a, b, halfWidth);
      var p = latLngToWorld(latlngs[i]);
      left.push(worldToLatLng(p.x + off.x, p.y + off.y));
      right.push(worldToLatLng(p.x - off.x, p.y - off.y));
    }
    var last = latLngToWorld(latlngs[latlngs.length - 1]);
    var prev = latLngToWorld(latlngs[latlngs.length - 2]);
    var dx = last.x - prev.x;
    var dy = last.y - prev.y;
    var len = Math.sqrt(dx * dx + dy * dy) || 1;
    var ux = dx / len;
    var uy = dy / len;
    var tip = worldToLatLng(last.x, last.y);
    var headW = halfWidth * 2.4;
    var leftH = worldToLatLng(last.x - ux * halfWidth * 2.2 + (-uy) * headW, last.y - uy * halfWidth * 2.2 + ux * headW);
    var rightH = worldToLatLng(last.x - ux * halfWidth * 2.2 - (-uy) * headW, last.y - uy * halfWidth * 2.2 - ux * headW);
    return left.concat([leftH, tip, rightH], right.slice().reverse());
  }
  function sectorTickLayers(latlngs, color) {
    var out = [];
    if (!latlngs || latlngs.length < 2) return out;
    var i;
    for (i = 1; i < latlngs.length; i += 1) {
      var a = latLngToWorld(latlngs[i - 1]);
      var b = latLngToWorld(latlngs[i]);
      var dx = b.x - a.x;
      var dy = b.y - a.y;
      var len = Math.sqrt(dx * dx + dy * dy) || 1;
      var steps = Math.max(1, Math.floor(len / 90));
      var s;
      for (s = 0; s <= steps; s += 1) {
        var t = s / steps;
        var px = a.x + dx * t;
        var py = a.y + dy * t;
        var off = offsetPerp(a, b, 14);
        out.push(L.polyline([
          worldToLatLng(px - off.x, py - off.y),
          worldToLatLng(px + off.x, py + off.y)
        ], { color: color, weight: 2, interactive: false }));
      }
    }
    return out;
  }
  function geometryToLatLngs(geo) {
    if (!geo) return [];
    if (geo.type === 'Point' && geo.coordinates) {
      return [worldToLatLng(geo.coordinates[0], geo.coordinates[1])];
    }
    if (geo.type === 'LineString' && Array.isArray(geo.coordinates)) {
      return geo.coordinates.map(function (p) { return worldToLatLng(p[0], p[1]); });
    }
    var ring = geo.coordinates && geo.coordinates[0] && Array.isArray(geo.coordinates[0][0]) ? geo.coordinates[0] : (geo.coordinates || []);
    var pts = ring.map(function (p) { return worldToLatLng(p[0], p[1]); });
    if (pts.length > 1 && pts[0].equals && pts[0].equals(pts[pts.length - 1])) pts.pop();
    return pts;
  }
  function isDrawBarTool(tool) {
    return /^(draw|arrow|freehand|polygon|highlight|text|measure|axis|attack|phase|sector|assembly|objective)$/.test(tool);
  }
  function syncDrawBar() {
    var bar = document.getElementById('ow-drawbar');
    if (bar) bar.hidden = !drawMode;
    if (!bar) return;
    bar.querySelectorAll('[data-draw]').forEach(function (btn) {
      var name = btn.getAttribute('data-draw');
      if (name === 'undo' || name === 'redo') return;
      btn.classList.toggle('is-active', name === activeTool);
    });
    var crayon = document.querySelector('.ow-rail [data-tool="draw"]');
    if (crayon) crayon.classList.toggle('is-active', drawMode);
  }
  function convexHull(latlngs) {
    var pts = latlngs.slice().sort(function (a, b) { return a.lng - b.lng || a.lat - b.lat; });
    if (pts.length < 3) return pts;
    function cross(o, a, b) {
      return (a.lng - o.lng) * (b.lat - o.lat) - (a.lat - o.lat) * (b.lng - o.lng);
    }
    var lower = [];
    pts.forEach(function (p) {
      while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], p) <= 0) lower.pop();
      lower.push(p);
    });
    var upper = [];
    pts.slice().reverse().forEach(function (p) {
      while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], p) <= 0) upper.pop();
      upper.push(p);
    });
    upper.pop();
    lower.pop();
    return lower.concat(upper);
  }
  function labelsOn() {
    var box = document.getElementById('ow-show-labels');
    return !box || box.checked;
  }
  function ageLabel(unit) {
    var sec = unitAgeSec(unit);
    if (!Number.isFinite(sec)) return '';
    sec = Math.max(0, Math.round(sec));
    if (sec < 15) return 'à l’instant';
    if (sec < 60) return 'il y a ' + sec + ' s';
    if (sec < 3600) return 'il y a ' + Math.round(sec / 60) + ' min';
    return 'il y a ' + Math.round(sec / 3600) + ' h';
  }
  function ringRadii() {
    var out = [];
    document.querySelectorAll('[data-ow-ring]').forEach(function (input) {
      if (input.checked) out.push(Number(input.getAttribute('data-ow-ring')));
    });
    return out.filter(function (n) { return n > 0; });
  }
  function clearRangeRings() {
    rangeRingLayers.forEach(function (layer) { map.removeLayer(layer); });
    rangeRingLayers = [];
  }
  function renderRangeRings() {
    clearRangeRings();
    var loc = selected && point(selected);
    if (!loc) return;
    var style = drawStyle();
    ringRadii().forEach(function (radius) {
      var edge = worldToLatLng(latLngToWorld(loc).x + radius, latLngToWorld(loc).y);
      rangeRingLayers.push(L.polygon(circleLatLngs(loc, edge, 48), {
        color: style.color, weight: 1, dashArray: '3 6', fillOpacity: 0.04, interactive: false
      }).addTo(map));
      rangeRingLayers.push(L.marker(edge, {
        interactive: false,
        icon: L.divIcon({
          className: 'ow-ring-label',
          html: '<span>' + formatMeters(radius) + '</span>',
          iconSize: [64, 18],
          iconAnchor: [32, 9]
        })
      }).addTo(map));
    });
  }

  function isPoLabel(label) {
    var text = String(label || '').trim();
    if (!text) return false;
    return /^PO(?:[\s\-_.#]*\d+|[\s\-_.]+[A-Z0-9]{1,16})?$/i.test(text);
  }
  function parseMarkerData(row) {
    var raw = row && (row.markerData || row.marker_data);
    if (typeof raw === 'string') {
      try { raw = JSON.parse(raw); } catch (e) { raw = {}; }
    }
    return raw && typeof raw === 'object' ? raw : {};
  }
  function markerWorld(data) {
    if (Array.isArray(data.pos) && data.pos.length >= 2) {
      var px = Number(data.pos[0]);
      var py = Number(data.pos[1]);
      if (Number.isFinite(px) && Number.isFinite(py) && (Math.abs(px) > 0.5 || Math.abs(py) > 0.5)) return { x: px, y: py };
    }
    var x = Number(data.pos_x != null ? data.pos_x : data.x);
    var y = Number(data.pos_y != null ? data.pos_y : data.y);
    return Number.isFinite(x) && Number.isFinite(y) && (Math.abs(x) > 0.5 || Math.abs(y) > 0.5) ? { x: x, y: y } : null;
  }
  function armaMarkersOn() {
    var box = document.getElementById('ow-arma-markers');
    return !box || box.checked;
  }
  function clearArmaMarkers() {
    Object.keys(armaMarkerLayers).forEach(function (id) {
      map.removeLayer(armaMarkerLayers[id]);
      delete armaMarkerLayers[id];
    });
  }
  function armaDuplicatesBft(data, latlng) {
    var helper = window.ArmaMapMarkers;
    var label = helper && helper.displayLabelOf ? helper.displayLabelOf(data) : (data.label || data.text || '');
    var key = String(label || '').trim().toLowerCase();
    if (!key) return false;
    return units.some(function (unit) {
      if (callsign(unit).toLowerCase() !== key) return false;
      var loc = point(unit);
      if (!loc) return false;
      var a = latLngToWorld(loc);
      var b = latLngToWorld(latlng);
      return Math.hypot(a.x - b.x, a.y - b.y) <= 25;
    });
  }
  function renderArmaMarkers() {
    var helper = window.ArmaMapMarkers;
    if (!helper) return;
    var seen = {};
    if (!armaMarkersOn()) { clearArmaMarkers(); return; }
    armaMarkerRows.forEach(function (row) {
      var id = String(row.id || '');
      if (!id) return;
      var data = parseMarkerData(row);
      if (data.suppressed) return;
      if (isPoLabel(String(data.text || data.label || '')) || data.po) return;
      var world = markerWorld(data) || helper.parsePos(data);
      if (!world) return;
      var latlng = worldToLatLng(world.x, world.y);
      if (armaDuplicatesBft(data, latlng)) {
        if (armaMarkerLayers[id]) { map.removeLayer(armaMarkerLayers[id]); delete armaMarkerLayers[id]; }
        return;
      }
      var buildingLike = helper.isBuildingLikeArea && helper.isBuildingLikeArea(data);
      if (buildingLike && leafletSceneWanted() && sceneCache.length) {
        if (armaMarkerLayers[id]) { map.removeLayer(armaMarkerLayers[id]); delete armaMarkerLayers[id]; }
        return;
      }
      seen[id] = true;
      var title = helper.displayLabelOf ? helper.displayLabelOf(data) : (data.label || data.text || 'Repère');
      if (buildingLike) title = '';
      var desc = String(data.description || '').trim();
      var tip = helper.markerTooltipOf ? helper.markerTooltipOf(data) : (title ? (desc ? (String(title) + ' — ' + desc) : String(title)) : '');
      if (desc && tip && tip.indexOf(desc) < 0) tip = tip + ' — ' + desc;
      if (armaMarkerLayers[id]) {
        if (armaMarkerLayers[id].setLatLng) armaMarkerLayers[id].setLatLng(latlng);
        if (!buildingLike && helper.leafletDivIcon && armaMarkerLayers[id].setIcon && !(helper.isAreaShape && helper.isAreaShape(data))) {
          armaMarkerLayers[id].setIcon(helper.leafletDivIcon(L, data));
        }
        if (buildingLike && armaMarkerLayers[id].setStyle && helper.buildingFootprintStyle) {
          armaMarkerLayers[id].setStyle(helper.buildingFootprintStyle());
        }
        if (tip && armaMarkerLayers[id].setTooltipContent) armaMarkerLayers[id].setTooltipContent(tip);
        else if (!tip && armaMarkerLayers[id].unbindTooltip) armaMarkerLayers[id].unbindTooltip();
        return;
      }
      var layer = null;
      if (helper.isAreaShape && helper.isAreaShape(data) && helper.leafletShapeLayer) {
        layer = helper.leafletShapeLayer(L, data, latlng);
      } else if (helper.leafletDivIcon) {
        layer = L.marker(latlng, { icon: helper.leafletDivIcon(L, data), keyboard: false });
      }
      if (!layer) return;
      if (tip && layer.bindTooltip) layer.bindTooltip(tip, { permanent: false, direction: 'top' });
      else if (layer.unbindTooltip) layer.unbindTooltip();
      layer.addTo(map);
      armaMarkerLayers[id] = layer;
      bindLayerContext(layer, 'arma', id, title);
    });
    Object.keys(armaMarkerLayers).forEach(function (id) {
      if (!seen[id]) { map.removeLayer(armaMarkerLayers[id]); delete armaMarkerLayers[id]; }
    });
  }
  function loadArmaMarkers() {
    return api('/api/atak/markers?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      armaMarkerRows = Array.isArray(payload) ? payload : asList(payload, 'markers');
      if (window.OverwatchOps && window.OverwatchOps.setArmaRows) window.OverwatchOps.setArmaRows(armaMarkerRows);
      renderArmaMarkers();
    }).catch(function () {});
  }
  function clearGpsVehicleMarkers() {
    Object.keys(gpsVehicleMarkers).forEach(function (id) {
      try { map.removeLayer(gpsVehicleMarkers[id]); } catch (e) {}
      delete gpsVehicleMarkers[id];
    });
  }
  function renderGpsVehiclesOnMap(rows) {
    gpsVehicles = Array.isArray(rows) ? rows : gpsVehicles;
    if (hiddenLayers.vehicles) { clearGpsVehicleMarkers(); return; }
    var seen = {};
    gpsVehicles.forEach(function (item) {
      if (!item) return;
      var id = item.id != null ? String(item.id) : String(item.vehicle_callsign || '');
      if (!id) return;
      var x = Number(item.pos_x);
      var y = Number(item.pos_y);
      if (!Number.isFinite(x) || !Number.isFinite(y) || (Math.abs(x) < 1 && Math.abs(y) < 1)) return;
      seen[id] = true;
      var latlng = worldToLatLng(x, y);
      var pretty = String(item.vehicle_name || item.vehicle_callsign || 'Véhicule');
      var gps = String(item.mission_type || '').toUpperCase() === 'GPS_BEACON';
      var color = gps ? '#38bdf8' : '#f59e0b';
      var kind = gps ? 'Balise GPS' : 'Véhicule suivi';
      var icon = L.divIcon({
        className: 'ow-gps-map-icon',
        html: '<span class="ow-gps-pin" style="border-bottom-color:' + color + '"></span><span class="ow-gps-label">' + escapeHtml(pretty) + '</span>',
        iconSize: [16, 16],
        iconAnchor: [8, 16]
      });
      var popup = '<div class="ow-gps-popup"><strong>' + escapeHtml(pretty) + '</strong><br/>' +
        escapeHtml(kind) + ' · ' + escapeHtml(vehicleClassLabel(item.vehicle_class)) +
        (item.crew_count != null ? '<br/>À bord : ' + escapeHtml(String(item.crew_count)) : '') + '</div>';
      if (gpsVehicleMarkers[id]) {
        gpsVehicleMarkers[id].setLatLng(latlng);
        gpsVehicleMarkers[id].setIcon(icon);
        if (gpsVehicleMarkers[id].setPopupContent) gpsVehicleMarkers[id].setPopupContent(popup);
        return;
      }
      var marker = L.marker(latlng, { icon: icon, zIndexOffset: 380 });
      marker.bindPopup(popup);
      marker.addTo(map);
      gpsVehicleMarkers[id] = marker;
    });
    Object.keys(gpsVehicleMarkers).forEach(function (id) {
      if (!seen[id]) {
        try { map.removeLayer(gpsVehicleMarkers[id]); } catch (e) {}
        delete gpsVehicleMarkers[id];
      }
    });
  }
  function loadVehicles() {
    return api('/api/atak/vehicles?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      var rows = Array.isArray(payload && payload.vehicles) ? payload.vehicles : asList(payload, 'vehicles');
      var now = Date.now();
      gpsVehicles = rows.filter(function (item) {
        if (!item) return false;
        if (String(item.status || '').toUpperCase() === 'DESTROYED') return false;
        var raw = item.last_seen_at || item.updated_at || '';
        if (!raw) return true;
        var ts = Date.parse(String(raw).indexOf('T') >= 0 ? String(raw) : String(raw).replace(' ', 'T'));
        if (!Number.isFinite(ts)) return true;
        return (now - ts) < (4 * 60 * 1000);
      });
      renderGpsVehiclesOnMap(gpsVehicles);
    }).catch(function () {
      gpsVehicles = [];
      clearGpsVehicleMarkers();
    });
  }
  function clearAirAssetMarkers() {
    Object.keys(airAssetMarkers).forEach(function (id) {
      try { map.removeLayer(airAssetMarkers[id]); } catch (e) {}
      delete airAssetMarkers[id];
    });
  }
  function renderAirAssetsOnMap(rows) {
    airAssets = Array.isArray(rows) ? rows : airAssets;
    if (hiddenLayers.air) { clearAirAssetMarkers(); return; }
    var nato = window.NatoSidcIcons;
    var seen = {};
    airAssets.forEach(function (a) {
      if (!a) return;
      var id = 'air_' + String(a.callsign || a.call_sign || a.id || '').replace(/\s/g, '_');
      if (!id || id === 'air_') return;
      var x = Number(a.pos_x);
      var y = Number(a.pos_y);
      if (!Number.isFinite(x) || !Number.isFinite(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return;
      seen[id] = true;
      var latlng = worldToLatLng(x, y);
      var airSide = String(a.side || 'WEST').toUpperCase();
      var status = String(a.status || 'IN-FLIGHT').toUpperCase();
      var aff = 'friend';
      if (airSide === 'EAST') aff = 'hostile';
      else if (airSide === 'GUER' || airSide === 'CIV' || status === 'SUSPECT') aff = 'unknown';
      var label = a.callsign || a.call_sign || 'Aérien';
      var icon = nato && nato.leafletDivIcon
        ? nato.leafletDivIcon(L, {
            affiliation: aff,
            aircraftType: a.aircraft_type || 'plane',
            role: a.model || a.aircraft_type || '',
            callSign: label,
            showLabel: true,
            size: 22
          })
        : L.divIcon({
            className: 'ow-air-map-icon',
            html: '<span class="ow-air-glyph">▲</span><span class="ow-air-label">' + escapeHtml(label) + '</span>',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
          });
      if (airAssetMarkers[id]) {
        airAssetMarkers[id].setLatLng(latlng);
        airAssetMarkers[id].setIcon(icon);
        return;
      }
      var marker = L.marker(latlng, { icon: icon, zIndexOffset: 500 });
      marker.on('click', function () { openAirAssetSheet(a); });
      marker.addTo(map);
      airAssetMarkers[id] = marker;
    });
    Object.keys(airAssetMarkers).forEach(function (id) {
      if (!seen[id]) {
        try { map.removeLayer(airAssetMarkers[id]); } catch (e) {}
        delete airAssetMarkers[id];
      }
    });
  }
  function renderPresenceHeat() {
    if (presenceLayer) { map.removeLayer(presenceLayer); presenceLayer = null; }
    var box = document.getElementById('ow-presence-heat');
    if (!box || !box.checked) return;
    presenceLayer = L.layerGroup();
    units.forEach(function (unit) {
      var loc = point(unit);
      if (!loc || tooOldToShow(unit)) return;
      var edge = worldToLatLng(latLngToWorld(loc).x + 160, latLngToWorld(loc).y);
      L.polygon(circleLatLngs(loc, edge, 24), {
        color: '#e05b63',
        weight: 0,
        fillColor: '#e7b14d',
        fillOpacity: Number(getComputedStyle(document.documentElement).getPropertyValue('--ow-heat-opacity')) || 0.16,
        interactive: false,
        className: 'ow-heat-dot'
      }).addTo(presenceLayer);
    });
    presenceLayer.addTo(map);
  }
  function updateStatsBanner() {
    var c = document.getElementById('ow-stat-contacts');
    var s = document.getElementById('ow-stat-shapes');
    var p = document.getElementById('ow-stat-photos');
    if (c) c.textContent = String(visibleUnits().length);
    if (s) s.textContent = String(shapes.length + poRows.length + rallyRows.length + armaMarkerRows.length);
    if (p) p.textContent = String(photos.length);
  }
  function poEnabled() {
    var box = document.getElementById('ow-po-markers');
    return !box || box.checked;
  }
  function unitMayConfirmPo(unit) {
    var extra = extraOf(unit);
    if (extra.phone_geoloc || extra.ally_ai || extra.enemy_ai || extra.gps_beacon) return false;
    var src = String(extra.source || '').toLowerCase();
    if (src === 'phone' || src === 'ally' || src === 'enemy' || src === 'gps' || src === 'gps_beacon') return false;
    return true;
  }
  function unitWorld(unit) {
    var x = Number(unit.pos_x != null ? unit.pos_x : unit.x);
    var y = Number(unit.pos_y != null ? unit.pos_y : unit.y);
    return Number.isFinite(x) && Number.isFinite(y) && (Math.abs(x) > 0.5 || Math.abs(y) > 0.5) ? { x: x, y: y } : null;
  }
  function clearPoLayers() {
    poLayers.forEach(function (layer) { map.removeLayer(layer); });
    poLayers = [];
  }
  function confirmPo(row, unit) {
    if (!row || !row.id || row.reached || row.confirming) return;
    if (!unitMayConfirmPo(unit)) return;
    var w = unitWorld(unit);
    if (!w) return;
    row.confirming = true;
    api('/api/atak/markers/' + encodeURIComponent(row.id) + '/reached', {
      method: 'POST',
      body: {
        mapId: mapId,
        unit_callsign: callsign(unit),
        reached_by_callsign: callsign(unit),
        pos_x: w.x,
        pos_y: w.y
      }
    }).then(function (payload) {
      row.confirming = false;
      if (!payload || !payload.ok) return;
      row.reached = true;
      row.reached_by = (payload.point && payload.point.reached_by) || callsign(unit);
      if (!poAnnounced[row.id]) {
        poAnnounced[row.id] = true;
        toast((row.kind === 'detection' ? 'Point suivi atteint — ' : 'Point d’objectif atteint — ') + row.label + ' · ' + row.reached_by);
      }
      renderPoMarkers();
    }).catch(function () { row.confirming = false; });
  }
  function renderPoMarkers() {
    clearPoLayers();
    if (!poEnabled()) return;
    var ordered = poRows.slice().sort(function (a, b) { return poNumberOf(a.label) - poNumberOf(b.label); });
    ordered.forEach(function (row) {
      var loc = worldToLatLng(row.x, row.y);
      var edge = worldToLatLng(row.x + row.radius, row.y);
      var inside = [];
      units.forEach(function (unit) {
        var w = unitWorld(unit);
        if (!w) return;
        var dx = w.x - row.x;
        var dy = w.y - row.y;
        if (Math.sqrt(dx * dx + dy * dy) <= row.radius) inside.push(unit);
      });
      var confirmer = inside.filter(unitMayConfirmPo)[0] || null;
      if (confirmer && !row.reached) confirmPo(row, confirmer);
      var color = row.reached ? '#6b7280' : (inside.length ? '#00d69a' : '#e7b14d');
      var ring = L.polygon(circleLatLngs(loc, edge, 36), {
        color: color, weight: 2, dashArray: row.reached ? '2 8' : '4 4', fillOpacity: row.reached ? 0.04 : 0.08, interactive: false
      }).addTo(map);
      var status = row.reached
        ? ('Atteint' + (row.reached_by ? ' · ' + row.reached_by : ''))
        : (inside.length ? (inside.length + ' ATAK dans le rayon') : ('En attente · ' + Math.round(row.radius) + ' m'));
      var pin = L.marker(loc, {
        icon: L.divIcon({
          className: 'ow-po-label',
          html: '<span>' + escapeHtml(row.label) + '<small>' + escapeHtml(status) + '</small></span>'
        })
      }).addTo(map);
      bindLayerContext(pin, 'po', row.id, row.label);
      poLayers.push(ring, pin);
    });
    if (ordered.length >= 2) {
      var all = ordered.map(function (row) { return worldToLatLng(row.x, row.y); });
      var pending = ordered.some(function (row) { return !row.reached; });
      poLayers.push(L.polyline(all, {
        color: pending ? '#e7b14d' : '#6b7280',
        weight: 2,
        dashArray: '6 6',
        opacity: 0.85,
        interactive: false
      }).addTo(map));
    }
  }
  function loadPoMarkers() {
    return api('/api/markers?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      var rows = Array.isArray(payload) ? payload : asList(payload, 'markers');
      var pending = {};
      poRows.forEach(function (row) { if (row.confirming) pending[row.id] = true; });
      poRows = [];
      rows.forEach(function (row) {
        var data = parseMarkerData(row);
        var label = String(data.text || data.label || '').trim();
        if (!isPoLabel(label) && !data.po && !data.detection) return;
        var world = markerWorld(data);
        if (!world) return;
        var id = String(row.id || '');
        var isDetection = !!data.detection && !isPoLabel(label) && !data.po;
        var radius = Number(
          (isDetection ? data.detection_radius_m : null) || data.po_radius_m || data.radius_m || 20
        ) || 20;
        poRows.push({
          id: id,
          kind: isDetection ? 'detection' : 'po',
          label: isDetection
            ? String(data.detection_label || label || 'Point suivi')
            : (label || 'PO'),
          x: world.x,
          y: world.y,
          radius: radius,
          reached: !!data.reached,
          reached_by: String(data.reached_by || ''),
          confirming: !!pending[id]
        });
        if (data.reached) poAnnounced[id] = true;
      });
      renderPoMarkers();
      fillGroupTaskSelects();
    }).catch(function () {});
  }
  function rallyEnabled() {
    var box = document.getElementById('ow-rally-markers');
    return !box || box.checked;
  }
  function clearRallyLayers() {
    rallyLayers.forEach(function (layer) { map.removeLayer(layer); });
    rallyLayers = [];
  }
  function rallyNumberOf(label) {
    var match = String(label || '').trim().match(/^ralliement(?:\s+|[\-_.#]*)(\d+)$/i);
    return match ? parseInt(match[1], 10) : 0;
  }
  function nextRallyLabel() {
    var max = 0;
    rallyRows.forEach(function (row) { max = Math.max(max, rallyNumberOf(row.label)); });
    return 'Ralliement ' + (max + 1);
  }
  function renderRallyPoints() {
    clearRallyLayers();
    if (!rallyEnabled()) return;
    rallyRows.forEach(function (row) {
      var loc = worldToLatLng(row.x, row.y);
      var edge = worldToLatLng(row.x + row.radius, row.y);
      var inside = [];
      units.forEach(function (unit) {
        if (side(unit) !== 'friendly') return;
        var w = unitWorld(unit);
        if (!w) return;
        var dx = w.x - row.x;
        var dy = w.y - row.y;
        if (Math.sqrt(dx * dx + dy * dy) <= row.radius) inside.push(unit);
      });
      var color = inside.length ? '#00d69a' : '#00ffaa';
      var ring = L.polygon(circleLatLngs(loc, edge, 36), {
        color: color, weight: 2, dashArray: '5 4', fillColor: '#00ffaa', fillOpacity: inside.length ? 0.14 : 0.08, interactive: false
      }).addTo(map);
      var status = inside.length
        ? (inside.length + ' opérateur' + (inside.length > 1 ? 's' : '') + ' dans le rayon')
        : 'Ralliement · 50 m';
      var pin = L.marker(loc, {
        icon: L.divIcon({
          className: 'ow-po-label ow-rally-label',
          html: '<span>' + escapeHtml(row.label) + '<small>' + escapeHtml(status) + '</small></span>'
        })
      }).addTo(map);
      bindLayerContext(pin, 'rally', row.id, row.label);
      rallyLayers.push(ring, pin);
    });
  }
  function loadRallyPoints() {
    return api('/api/atak/zones?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      var rows = asList(payload, 'zones');
      rallyRows = [];
      rows.forEach(function (row) {
        if (String(row.zone_type || '').toUpperCase() !== 'RALLY_POINT') return;
        var x = Number(row.center_x);
        var y = Number(row.center_y);
        if (!Number.isFinite(x) || !Number.isFinite(y)) return;
        rallyRows.push({
          id: String(row.id || ''),
          label: String(row.zone_name || row.zone_code || 'Ralliement').trim() || 'Ralliement',
          x: x,
          y: y,
          radius: Number(row.radius) > 0 ? Number(row.radius) : RALLY_RADIUS_M
        });
      });
      renderRallyPoints();
    }).catch(function () {});
  }
  function placeRallyPoint(ll) {
    var w = latLngToWorld(ll);
    var label = nextRallyLabel();
    return api('/api/atak/zones', {
      method: 'POST',
      body: {
        mapId: mapId,
        zone_name: label,
        zone_code: 'RP' + (rallyNumberOf(label) || rallyRows.length + 1),
        zone_type: 'RALLY_POINT',
        geometry_type: 'CIRCLE',
        center_x: w.x,
        center_y: w.y,
        radius: RALLY_RADIUS_M,
        status: 'ACTIVE',
        is_visible: true,
        show_label: true,
        fill_color: '#00ffaa',
        border_color: '#00ffaa',
        opacity: 0.3,
        purpose: 'Point de ralliement posé depuis le poste'
      }
    }).then(function () {
      toast(label + ' posé · rayon 50 m. Visible en jeu.');
      loadRallyPoints();
    }).catch(function () {
      toast('Point de ralliement refusé.');
    });
  }

  function csrfToken() {
    return String(window.ATAK_CSRF || window.ATAK_CSRF_TOKEN || '');
  }

  function api(path, opts) {
    var options = opts || {};
    options.credentials = 'include';
    var csrf = csrfToken();
    options.headers = Object.assign({ 'Accept': 'application/json' }, options.headers || {});
    var method = String(options.method || 'GET').toUpperCase();
    if (csrf && method !== 'GET') options.headers['X-CSRF-TOKEN'] = csrf;
    if (options.body instanceof FormData) {
      if (csrf && !options.body.has('_csrf_token')) options.body.append('_csrf_token', csrf);
    } else if (options.body && typeof options.body !== 'string') {
      if (csrf) options.body = Object.assign({ _csrf_token: csrf }, options.body);
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    return fetch(apiBase + path, options).then(function (response) {
      if (!response.ok) throw new Error(String(response.status));
      var type = response.headers.get('content-type') || '';
      return type.indexOf('json') !== -1 ? response.json() : response.text();
    });
  }

  function toast(text) {
    var box = document.getElementById('ow-toast');
    document.getElementById('ow-toast-text').textContent = text;
    box.hidden = false;
    window.setTimeout(function () { box.hidden = true; }, 2400);
  }

  function markerPrefs() {
    var styleEl = document.getElementById('ow-marker-style');
    var friendEl = document.getElementById('ow-color-friend');
    var hostileEl = document.getElementById('ow-color-hostile');
    return {
      style: (styleEl && styleEl.value) || 'diamond',
      friend: (friendEl && friendEl.value) || '#00d69a',
      hostile: (hostileEl && hostileEl.value) || '#e05b63',
      unknown: '#e7b14d'
    };
  }

  function stackBucketKey(loc) {
    var w = latLngToWorld(loc);
    return Math.round(w.x / 10) + ':' + Math.round(w.y / 10);
  }
  function fanLatLng(trueLoc, index, count) {
    if (count <= 1) return trueLoc;
    var pt = map.latLngToLayerPoint(trueLoc);
    var angle = (Math.PI * 2 * index) / count - Math.PI / 2;
    var r = 22 + Math.min(18, (count - 1) * 4);
    return map.layerPointToLatLng(L.point(pt.x + Math.cos(angle) * r, pt.y + Math.sin(angle) * r));
  }
  function unitsAtSamePoint(unit) {
    var loc = point(unit);
    if (!loc) return [];
    var key = stackBucketKey(loc);
    return units.filter(function (row) {
      if (unitId(row) === unitId(unit) || !layerVisible(row)) return false;
      var other = point(row);
      return other && stackBucketKey(other) === key;
    });
  }
  function markerIcon(unit, opts) {
    opts = opts || {};
    var kind = side(unit);
    var prefs = markerPrefs();
    var color = kind === 'hostile' ? prefs.hostile : (kind === 'unknown' ? prefs.unknown : prefs.friend);
    var extra = kind === 'hostile' ? ' is-hostile' : (kind === 'unknown' ? ' is-unknown' : '');
    var selected = !!opts.selected;
    var stackCount = Number(opts.stackCount || 1);
    var labelLeft = !!opts.labelLeft;
    var showLabel = labelsOn() && (stackCount <= 5 || selected || opts.forceLabel);
    extra += selected ? ' is-selected' : '';
    extra += labelLeft ? ' is-label-left' : '';
    extra += stackCount > 1 ? ' is-stacked' : '';
    var disc = isDisconnected(unit);
    var stateColor = disc ? DISC_COLOR : color;
    var squad = hasSquadColor(unit) ? squadColor(unit) : stateColor;
    var pulse = squadColorOn() && hasSquadColor(unit);
    extra += disc ? ' is-offline' : '';
    extra += pulse ? ' is-squad-pulse' : '';
    var ageSec = unitAgeSec(unit);
    var delayedSt = String(unit.status || '').toLowerCase() === 'delayed';
    var lagging = !disc && (delayedSt || (Number.isFinite(ageSec) && ageSec >= DELAYED_SEC && ageSec < LIVE_TTL_SEC));
    var stalePos = !disc && Number.isFinite(ageSec) && ageSec >= LIVE_TTL_SEC;
    extra += lagging ? ' is-delayed' : '';
    extra += stalePos ? ' is-stale-pos' : '';
    var age = ageLabel(unit);
    var hint = '';
    if (disc) hint = 'Dernière position connue' + (age ? ' · ' + age : '');
    else if (stalePos || lagging) hint = 'Différé' + (age ? ' · ' + age : '');
    var label = showLabel ? '<span class="ow-cs">' + escapeHtml(callsign(unit)) + '</span>' : '';
    var badge = (opts.forceLabel && stackCount > 1) ? '<b class="ow-stack-n">+' + (stackCount - 1) + '</b>' : '';
    return L.divIcon({
      className: 'ow-marker ow-mark-' + prefs.style + extra,
      iconSize: [16, 16],
      iconAnchor: [8, 8],
      html: '<div' + (hint ? ' title="' + escapeHtml(hint) + '"' : '') + ' style="--ow-iff:' + escapeHtml(stateColor) + ';--ow-state:' + escapeHtml(stateColor) + ';--ow-squad:' + escapeHtml(squad) + '"><i></i>' + label + badge + '</div>'
    });
  }

  function selectUnit(unit) {
    selected = unit;
    setDrawerContactHead(unit);
    var loc = point(unit);
    var grid = loc ? Math.round(latLngToWorld(loc).x) + ' / ' + Math.round(latLngToWorld(loc).y) : '';
    var speed = unitSpeed(unit);
    var alt = unitAlt(unit);
    var extra = extraOf(unit);
    var term = terminalFor(unit);
    var mates = loc ? units.filter(function (row) { return squadKey(row) && squadKey(row) === squadKey(unit) && unitId(row) !== unitId(unit) && point(row); }) : [];
    var mateHtml = mates.map(function (row) {
      var other = point(row);
      var dist = map.distance(loc, other);
      var cap = Math.round(bearingWorld(loc, other));
      return '<button type="button" class="ow-mate" data-unit-id="' + escapeHtml(unitId(row)) + '"><span>' +
        escapeHtml(callsign(row)) + '</span><strong>' + formatMeters(dist) + ' · ' + cap + '°</strong></button>';
    }).join('');
    var stackedHere = unitsAtSamePoint(unit);
    var stackedHtml = stackedHere.map(function (row) {
      return '<button type="button" class="ow-mate" data-unit-id="' + escapeHtml(unitId(row)) + '"><span>' +
        escapeHtml(callsign(row)) + '</span><strong>' + escapeHtml(group(row)) + '</strong></button>';
    }).join('');
    var certLabel = '';
    var certKind = 'ok';
    var certExp = '';
    if (term) {
      var csSt = String(term.certificate_status || '').toLowerCase();
      if (csSt === 'revoked') { certLabel = 'Révoqué'; certKind = 'bad'; }
      else if (csSt === 'expired') { certLabel = 'Expiré'; certKind = 'bad'; }
      else if (csSt === 'active' || csSt === 'issued') { certLabel = 'Actif'; certKind = 'ok'; }
      else if (String(term.certificate_ref || '').trim()) { certLabel = 'Émis'; certKind = 'ok'; }
      certExp = String(term.certificate_expires_at || '').trim();
      if (certExp) {
        var expMs = Date.parse(certExp);
        if (!isNaN(expMs)) certExp = new Date(expMs).toLocaleDateString('fr-FR');
      }
    }
    var ip = extra.client_ip || extra.ip || extra.public_ip || extra.network || (term && term.last_client_ip) || '';
    var lastTerm = term && term.last_seen_at ? formatSeen(term.last_seen_at) : '';
    var compromiseRaw = term && term.compromise_state ? String(term.compromise_state).toLowerCase() : '';
    var compromise = '';
    var compromiseKind = 'ok';
    if (compromiseRaw === 'none') compromise = 'Intègre';
    else if (compromiseRaw === 'captured') { compromise = 'Saisi'; compromiseKind = 'bad'; }
    else if (compromiseRaw === 'compromised') { compromise = 'Compromis'; compromiseKind = 'bad'; }
    var lastPos = ageLabel(unit) || (isDisconnected(unit) ? 'hors liaison' : 'à l’instant');
    var heading = unitHeading(unit);
    var team = clean(unit.fire_team_label || extra.fire_team_label, '');
    var grpName = clean(extra.group_name || unit.group_name || unit.group, '');
    var leader = clean(extra.leader || extra.group_leader || unit.pilot || unit.leader, '');
    var fuel = firstNumber(extra.fuel_pct, armaOf(unit).fuel_pct, unit.fuel_pct);
    var ammo = clean(extra.ammo || armaOf(unit).ammo || unit.ammo, '');
    var health = firstNumber(extra.health, armaOf(unit).health, unit.health);
    var phoneHtml = '<div class="ow-stat-grid">' +
      (certLabel ? '<div class="ow-stat"><div class="ow-stat-k">Certificat</div>' + pillHtml(certLabel, certKind) + '</div>' : '') +
      (compromise ? '<div class="ow-stat"><div class="ow-stat-k">Intégrité</div>' + pillHtml(compromise, compromiseKind) + '</div>' : '') +
      (certExp ? statCell('Échéance', certExp) : '') +
      (lastTerm ? statCell('Activité', lastTerm, { dim: true }) : '') +
      '</div>' +
      (maskIpForDisplay(ip) ? '<div class="ow-stat ow-stat-block">' +
        '<div class="ow-stat-k">Adresse réseau</div><div class="ow-stat-v is-mono">' +
        escapeHtml(maskIpForDisplay(ip)) + '</div></div>' : '') +
      (stackedHere.length ? '<p class="ow-kicker">Au même point</p><div class="ow-mate-list">' + stackedHtml + '</div>' +
        '<p class="ow-help">Plusieurs contacts occupent ce lieu. Sur la carte, ils sont écartés autour du point réel.</p>' : '') +
      (mates.length
        ? '<p class="ow-kicker">Même groupe</p><div class="ow-mate-list">' + mateHtml + '</div>' +
          '<button type="button" class="ow-secondary" data-fit-squad>Cadrer le groupe</button>'
        : '<p class="ow-note">Aucun autre membre du groupe localisé.</p>');
    document.getElementById('ow-drawer-body').innerHTML =
      '<div class="ow-stat-grid">' +
      statCell('Vitesse', formatSpeedDisplay(speed)) +
      statCell('Cap', heading != null ? Math.round(heading) + '°' : '') +
      statCell('Altitude', alt != null ? Math.round(alt) + ' m' : '') +
      statCell('Dernière position', lastPos, { dim: true }) +
      statCell('Grille', clean(unit.grid || unit.grid_ref || unit.mgrs, grid), { mono: true }) +
      (grpName ? statCell('Groupe', grpName) : '') +
      (team ? statCell('Équipe', team) : '') +
      (leader ? statCell('Chef', leader) : '') +
      (fuel != null ? statCell('Carburant', Math.round(fuel) + ' %') : '') +
      (ammo ? statCell('Munitions', ammo) : '') +
      (health != null ? statCell('Santé', Math.round(health) + ' %') : '') +
      '</div>' +
      '<button type="button" class="ow-primary ow-btn-icon" data-center-selected>' +
      btnIcon('<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>') +
      'Centrer sur la carte</button>' +
      '<div class="ow-btn-row">' +
      '<button type="button" class="ow-secondary ow-btn-icon" data-follow-selected>' +
      btnIcon('<path d="M9 18l6-6-6-6"/>') +
      (followOn ? 'Arrêter' : 'Suivre') + '</button>' +
      (squadKey(unit)
        ? '<button type="button" class="ow-secondary ow-btn-icon" data-squad-task-from-unit>' +
          btnIcon('<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>') +
          'Tâche</button>'
        : '') +
      '</div>' +
      discBlock('Téléphone ATAK', phoneHtml) +
      discBlock('Alerte plein écran', fullscreenAlertFormHtml({ dest: 'unit:' + callsign(unit), solo: true }), true);
    document.getElementById('ow-drawer').hidden = false;
    bindFsAlertForms(document.getElementById('ow-drawer'));
    fillFsAlertSelects(true);
    renderSquadLinks();
    renderRangeRings();
    renderMap();
    syncReachOverlay(unit);
    if (window.ATAKUnitDossier && typeof window.ATAKUnitDossier.open === 'function') {
      try { window.ATAKUnitDossier.open(unit); } catch (e2) {}
    }
  }

  function reachZoneEnabled() {
    var box = document.getElementById('ow-reach-zone');
    return !!(box && box.checked);
  }
  function syncReachOverlay(unit) {
    if (!window.ATAKReachOverlay) return;
    if (!reachZoneEnabled()) {
      if (typeof window.ATAKReachOverlay.clear === 'function') {
        try { window.ATAKReachOverlay.clear(true); } catch (e) {}
      }
      return;
    }
    if (unit && typeof window.ATAKReachOverlay.select === 'function') {
      try { window.ATAKReachOverlay.select(unit, { center: false }); } catch (e2) {}
    }
  }

  function layerVisible(unit) {
    if (tooOldToShow(unit)) return false;
    if (hiddenLayers.units) return false;
    if (isAir(unit) && hiddenLayers.air) return false;
    if (isVehicle(unit) && hiddenLayers.vehicles) return false;
    return true;
  }

  function renderMap() {
    if (selected && tooOldToShow(selected)) {
      selected = null;
      var drawer = document.getElementById('ow-drawer');
      if (drawer && document.getElementById('ow-drawer-kicker') && document.getElementById('ow-drawer-kicker').textContent.indexOf('BFT') === 0) {
        drawer.hidden = true;
      }
    }
    var alive = {};
    var buckets = {};
    units.forEach(function (unit) {
      var loc = point(unit);
      if (!loc || !layerVisible(unit)) {
        var hideId = unitId(unit);
        if (markers[hideId]) { map.removeLayer(markers[hideId]); delete markers[hideId]; }
        return;
      }
      var key = stackBucketKey(loc);
      if (!buckets[key]) buckets[key] = [];
      buckets[key].push(unit);
    });
    Object.keys(buckets).forEach(function (key) {
      var pack = buckets[key];
      pack.sort(function (a, b) { return callsign(a).localeCompare(callsign(b), 'fr'); });
      pack.forEach(function (unit, index) {
        var trueLoc = point(unit);
        var id = unitId(unit);
        alive[id] = true;
        var displayLoc = fanLatLng(trueLoc, index, pack.length);
        var angle = pack.length > 1 ? (Math.PI * 2 * index) / pack.length - Math.PI / 2 : 0;
        var isSel = !!(selected && unitId(selected) === id);
        var iconOpts = {
          selected: isSel,
          stackCount: pack.length,
          labelLeft: Math.cos(angle) < -0.15,
          forceLabel: pack.length > 5 && index === 0 && !pack.some(function (row) { return selected && unitId(selected) === unitId(row); })
        };
        if (!markers[id]) {
          markers[id] = L.marker(displayLoc, { icon: markerIcon(unit, iconOpts), riseOnHover: true }).addTo(map);
        } else {
          markers[id].setLatLng(displayLoc).setIcon(markerIcon(unit, iconOpts));
        }
        markers[id].setZIndexOffset(isSel ? 800 : 400 - index);
        markers[id].off('click').on('click', function () { selectUnit(unit); });
        appendTrack(id, trueLoc, unit);
      });
    });
    Object.keys(markers).forEach(function (id) {
      if (!alive[id]) { map.removeLayer(markers[id]); delete markers[id]; }
    });
    renderSquadLinks();
    renderHud();
    renderRangeRings();
    renderPoMarkers();
    renderRallyPoints();
    renderPresenceHeat();
    updateStatsBanner();
    if (followOn && selected) {
      var loc = point(selected);
      if (loc) map.panTo(loc);
    }
    if (window.OverwatchOps && window.OverwatchOps.afterRenderMap) window.OverwatchOps.afterRenderMap();
  }

  function clearSquadLinks() {
    squadLinkLayers.forEach(function (layer) { map.removeLayer(layer); });
    squadLinkLayers = [];
  }

  function renderSquadLinks() {
    clearSquadLinks();
    var linksOn = document.getElementById('ow-squad-links');
    var hullOn = document.getElementById('ow-squad-hull');
    if (linksOn && !linksOn.checked && hullOn && !hullOn.checked) return;
    var groups = {};
    units.forEach(function (unit) {
      var key = squadKey(unit);
      var loc = point(unit);
      if (!key || !loc || !layerVisible(unit)) return;
      if (!groups[key]) groups[key] = { color: squadColor(unit), pts: [], units: [] };
      groups[key].pts.push(loc);
      groups[key].units.push(unit);
    });
    Object.keys(groups).forEach(function (key) {
      var pack = groups[key];
      if (pack.pts.length < 2) return;
      var highlighted = selected && squadKey(selected) === key;
      var baseW = Number((document.getElementById('ow-squad-width') || {}).value || 0.75);
      var weight = highlighted ? Math.max(1.25, baseW + 0.5) : baseW;
      var opacity = highlighted ? 0.7 : 0.32;
      if (!linksOn || linksOn.checked) {
        var i;
        var j;
        if (pack.pts.length <= 6) {
          for (i = 0; i < pack.pts.length; i += 1) {
            for (j = i + 1; j < pack.pts.length; j += 1) {
              var line = L.polyline([pack.pts[i], pack.pts[j]], {
                color: pack.color, weight: weight, opacity: opacity, dashArray: '2 8', interactive: false
              }).addTo(map);
              squadLinkLayers.push(line);
              var distOn = document.getElementById('ow-squad-dist');
              if (distOn && distOn.checked && pack.pts.length <= 5) {
                var midPt = L.latLng((pack.pts[i].lat + pack.pts[j].lat) / 2, (pack.pts[i].lng + pack.pts[j].lng) / 2);
                squadLinkLayers.push(L.marker(midPt, {
                  interactive: false,
                  icon: L.divIcon({ className: 'ow-ring-label', html: '<span>' + formatMeters(map.distance(pack.pts[i], pack.pts[j])) + '</span>' })
                }).addTo(map));
              }
            }
          }
        } else {
          var cx = 0;
          var cy = 0;
          pack.pts.forEach(function (p) { cx += p.lat; cy += p.lng; });
          var mid = L.latLng(cx / pack.pts.length, cy / pack.pts.length);
          pack.pts.forEach(function (p) {
            squadLinkLayers.push(L.polyline([mid, p], {
              color: pack.color, weight: weight, opacity: opacity, dashArray: '2 8', interactive: false
            }).addTo(map));
          });
        }
      }
      if ((!hullOn || hullOn.checked) && pack.pts.length >= 3) {
        squadLinkLayers.push(L.polygon(convexHull(pack.pts), {
          color: pack.color, weight: 1, fillOpacity: highlighted ? 0.12 : 0.06, interactive: false
        }).addTo(map));
      }
    });
  }

  function renderHud() {
    var hud = document.getElementById('ow-data-hud');
    if (!hud) return;
    var friend = 0;
    var hostile = 0;
    var unknown = 0;
    var squads = {};
    units.forEach(function (unit) {
      var kind = side(unit);
      if (tooOldToShow(unit)) return;
      if (kind === 'hostile') hostile += 1;
      else if (kind === 'unknown') unknown += 1;
      else friend += 1;
      var key = squadKey(unit);
      if (key) squads[key] = true;
    });
    hud.textContent = 'GROUPES ' + Object.keys(squads).length + ' · AMI ' + friend + ' · HOSTILE ' + hostile + (unknown ? ' · INCONNU ' + unknown : '');
  }

  function fitSquad(unit) {
    var key = squadKey(unit);
    var pts = units.filter(function (row) { return squadKey(row) === key; }).map(point).filter(Boolean);
    if (pts.length) map.fitBounds(L.latLngBounds(pts).pad(0.35));
  }

  function renderSquadList() {
    var host = document.getElementById('ow-squad-list');
    if (!host) return;
    var groups = {};
    units.forEach(function (unit) {
      var key = squadKey(unit) || 'none';
      if (tooOldToShow(unit)) return;
      if (!groups[key]) groups[key] = [];
      groups[key].push(unit);
    });
    var keys = Object.keys(groups).sort();
    host.innerHTML = keys.map(function (key) {
      var rows = groups[key];
      var locates = rows.map(point).filter(Boolean);
      var span = 0;
      locates.forEach(function (a) {
        locates.forEach(function (b) { span = Math.max(span, map.distance(a, b)); });
      });
      var label = key === 'none' ? 'Sans groupe' : group(rows[0]);
      var taskBtn = key === 'none' ? '' : '<button type="button" class="ow-tag" data-squad-task="' + escapeHtml(key) + '">Tâche</button>';
      return '<div class="ow-squad-row">' +
        '<button type="button" class="ow-contact" data-squad-key="' + escapeHtml(key) + '"><span class="cicon" style="border-color:' +
        escapeHtml(key === 'none' ? '#2b3531' : squadColor(rows[0])) + '"></span><span><div class="cname">' +
        escapeHtml(label) + '</div><div class="cmeta">' + rows.length + ' opérateur' + (rows.length > 1 ? 's' : '') +
        (span ? ' · ' + formatMeters(span) : '') + '</div></span><em class="online">' + locates.length + ' POS</em></button>' +
        taskBtn + '</div>';
    }).join('') || '<p class="ow-help">Aucun groupe transmis pour cette mission.</p>';
    fillGroupTaskSelects();
  }

  function trackColorForUnit(unit) {
    var kind = side(unit);
    if (kind === 'hostile') return '#e05b63';
    if (kind === 'unknown') return '#e7b14d';
    return '#00d69a';
  }
  function appendTrack(id, location, unit) {
    if (!trackSamples[id]) trackSamples[id] = [];
    var prev = trackSamples[id][trackSamples[id].length - 1];
    if (prev && map.distance(prev.ll, location) < 2.5) return;
    var live = !(unit && isDisconnected(unit));
    trackSamples[id].push({ ll: location, t: Date.now(), live: live });
    if (trackSamples[id].length > 240) trackSamples[id].shift();
    var progress = document.getElementById('ow-progress-trail');
    var skipLine = !!(progress && progress.checked && selected && unitId(selected) === id);
    var color = trackColorForUnit(unit || {});
    var style = {
      color: color,
      weight: 2.25,
      opacity: live ? 0.72 : 0.38,
      dashArray: live ? null : '4 7',
      interactive: false,
      className: 'ow-track-line' + (live ? '' : ' is-stale')
    };
    if (!trackLines[id]) {
      trackLines[id] = L.polyline(trackSamples[id].map(function (row) { return row.ll; }), style);
      if (tracksOn && !hiddenLayers.tracks && !skipLine) trackLines[id].addTo(map);
      return;
    }
    trackLines[id].setLatLngs(trackSamples[id].map(function (row) { return row.ll; }));
    if (trackLines[id].setStyle) trackLines[id].setStyle(style);
    if (tracksOn && !hiddenLayers.tracks && !skipLine) {
      if (!map.hasLayer(trackLines[id])) trackLines[id].addTo(map);
    } else if (map.hasLayer(trackLines[id])) {
      map.removeLayer(trackLines[id]);
    }
  }

  function isBftRelay(unit) {
    var extra = extraOf(unit);
    if (unit.gateway_partner || extra.via_relay || extra.radio_relay) return true;
    var link = String(unit.link_state || extra.link_state || extra.linkState || '').toLowerCase();
    return link === 'degraded';
  }
  function bftAgeText(unit) {
    var age = ageLabel(unit);
    if (isDisconnected(unit)) {
      if (!age) return 'hors liaison';
      if (age === 'à l’instant') return 'déconnecté à l’instant';
      return 'déconnecté ' + age;
    }
    if (!age || age === 'à l’instant') return 'actif à l’instant';
    return age;
  }
  function bftSquadStats(key) {
    var members = units.filter(function (unit) {
      return !tooOldToShow(unit) && (squadKey(unit) || 'none') === key;
    });
    var live = 0;
    members.forEach(function (unit) { if (!isDisconnected(unit)) live += 1; });
    return { live: live, total: members.length };
  }
  function bftGroupHtml(key, rows, selectedId) {
    var stats = bftSquadStats(key);
    var liveHere = rows.some(function (unit) { return !isDisconnected(unit); });
    var headName = key === 'none' ? 'Sans groupe' : group(rows[0]);
    var contacts = rows.slice().sort(function (a, b) {
      return callsign(a).localeCompare(callsign(b), 'fr', { sensitivity: 'base' });
    }).map(function (unit) {
      return bftContactHtml(unit, selectedId);
    }).join('');
    return '<div class="ow-bft-group"><div class="ow-bft-group-head">' +
      '<span class="ow-bft-dot' + (liveHere ? ' is-live' : '') + '"></span>' +
      '<span class="ow-bft-group-name">' + escapeHtml(headName) + '</span>' +
      '<span class="ow-bft-group-n">' + rows.length + '/' + stats.total + '</span></div>' +
      contacts + '</div>';
  }
  function bftContactHtml(unit, selectedId) {
    var disc = isDisconnected(unit);
    var sideId = side(unit);
    var role = clean(unit.role, '');
    var relay = !disc && isBftRelay(unit);
    var wave = isWave(unit);
    var profile = profileOf(unit);
    var cls = 'ow-bft-contact' +
      (disc ? ' is-offline' : ' is-live') +
      (sideId === 'hostile' ? ' is-hostile' : '') +
      (sideId === 'unknown' ? ' is-unknown' : '') +
      (selectedId === unitId(unit) ? ' is-active' : '');
    var chanBits = [];
    if (!disc) {
      chanBits.push('<span class="ow-bft-chan ' + (relay ? 'is-relay' : 'is-direct') + '">' +
        (relay ? 'RELAIS' : 'DIRECT') + '</span>');
    }
    if (wave) {
      var extra = extraOf(unit);
      chanBits.push('<span class="ow-bft-chan is-wave" title="Wave Relay">WAVE</span>');
      if (flagOn(extra.wr_gateway)) chanBits.push('<span class="ow-bft-chan is-wave" title="Passerelle Wave">PASSERELLE</span>');
      if (flagOn(extra.wr_bridge)) chanBits.push('<span class="ow-bft-chan is-wave" title="Pont radio actif">PONT</span>');
    }
    var chan = chanBits.join('');
    var locate = disc
      ? '<button type="button" class="ow-bft-locate" data-bft-locate="' + escapeHtml(unitId(unit)) +
        '" title="Centrer la carte sur la dernière position connue" aria-label="Dernière position connue">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></button>'
      : '';
    var grp = group(unit);
    var avatarInner = (profile && profile.avatarUrl)
      ? '<img class="ow-bft-avatar-img" src="' + escapeHtml(profile.avatarUrl) + '" alt="" width="32" height="32" loading="lazy">'
      : escapeHtml(initials(callsign(unit)));
    return '<div class="' + cls + '" data-unit-id="' + escapeHtml(unitId(unit)) + '" role="button" tabindex="0">' +
      '<span class="ow-bft-avatar">' + avatarInner + '<span class="ow-bft-status"></span></span>' +
      '<span class="ow-bft-info"><span class="ow-bft-top"><span class="ow-bft-name">' + escapeHtml(callsign(unit)) + '</span>' +
      (role ? '<span class="ow-bft-role">' + escapeHtml(role) + '</span>' : '') + '</span>' +
      '<span class="ow-bft-bottom"><span>' + escapeHtml(grp) + '</span><span class="ow-bft-sep"></span>' +
      '<span class="ow-bft-time">' + escapeHtml(bftAgeText(unit)) + '</span></span></span>' +
      chan + locate + '</div>';
  }
  function bftSectionHtml(kind, rows, selectedId) {
    if (!rows.length) return '';
    var collapsed = !!bftSectState[kind];
    var groups = {};
    rows.forEach(function (unit) {
      var key = squadKey(unit) || 'none';
      if (!groups[key]) groups[key] = [];
      groups[key].push(unit);
    });
    var keys = Object.keys(groups).sort(function (a, b) {
      if (a === 'none') return 1;
      if (b === 'none') return -1;
      return a.localeCompare(b, 'fr', { sensitivity: 'base' });
    });
    var label = kind === 'live' ? 'En liaison' : 'Hors liaison';
    var count = kind === 'live' ? (rows.length + ' en ligne') : String(rows.length);
    return '<div class="ow-bft-sect' + (collapsed ? ' is-collapsed' : '') + '">' +
      '<button type="button" class="ow-bft-sect-head" data-bft-sect="' + kind + '">' +
      '<span class="ow-bft-car">▾</span><span class="ow-bft-sect-label' + (kind === 'live' ? ' is-on' : ' is-off') + '">' +
      label + '</span><span class="ow-bft-sect-count">' + escapeHtml(count) + '</span></button>' +
      '<div class="ow-bft-groups">' + keys.map(function (key) {
        return bftGroupHtml(key, groups[key], selectedId);
      }).join('') + '</div></div>';
  }
  function renderEffectifsTable(list) {
    var body = document.getElementById('ow-units-table-body');
    var countEl = document.getElementById('ow-effectifs-count');
    if (!body) return;
    var rows = Array.isArray(list) ? list : visibleUnits();
    if (countEl) countEl.textContent = String(rows.length);
    body.innerHTML = rows.map(function (unit) {
      var loc = point(unit);
      var grid = loc ? Math.round(latLngToWorld(loc).x) + ' / ' + Math.round(latLngToWorld(loc).y) : clean(unit.grid || unit.grid_ref, '—');
      var heading = unitHeading(unit);
      var notes = clean(unit.notes || extraOf(unit).notes, '—');
      return '<tr data-unit-id="' + escapeHtml(unitId(unit)) + '" class="' +
        (isDisconnected(unit) ? 'is-offline' : 'is-live') +
        (selected && unitId(selected) === unitId(unit) ? ' is-active' : '') + '">' +
        '<td>' + escapeHtml(callsign(unit)) + '</td>' +
        '<td>' + escapeHtml(clean(unit.role, '—')) + '</td>' +
        '<td>' + escapeHtml(clean(unit.fire_team_label || group(unit), '—')) + '</td>' +
        '<td>' + escapeHtml(isDisconnected(unit) ? 'Hors liaison' : 'En liaison') + '</td>' +
        '<td>' + (heading != null ? escapeHtml(String(Math.round(heading)) + '°') : '—') + '</td>' +
        '<td class="is-mono">' + escapeHtml(grid) + '</td>' +
        '<td>' + escapeHtml(notes) + '</td>' +
        '</tr>';
    }).join('') || '<tr><td colspan="7" class="ow-help">Aucun effectif transmis.</td></tr>';
  }
  function renderList() {
    var query = (document.getElementById('ow-search').value || '').trim().toLowerCase();
    var sideFilter = (document.getElementById('ow-side-filter') || {}).value || 'all';
    var waveBtn = document.getElementById('ow-filter-wave');
    if (waveBtn) {
      waveBtn.classList.toggle('is-active', !!filterWave);
      waveBtn.setAttribute('aria-pressed', filterWave ? 'true' : 'false');
    }
    var visible = units.filter(function (unit) {
      if (tooOldToShow(unit)) return false;
      var disc = isDisconnected(unit);
      if (filterWave && !isWave(unit)) return false;
      if (sideFilter === 'wave' && !isWave(unit)) return false;
      if (sideFilter === 'live' && disc) return false;
      if (sideFilter === 'offline' && !disc) return false;
      if (sideFilter === 'friendly' || sideFilter === 'hostile' || sideFilter === 'unknown') {
        if (side(unit) !== sideFilter) return false;
      }
      return (callsign(unit) + ' ' + group(unit) + ' ' + clean(unit.role, '')).toLowerCase().indexOf(query) !== -1;
    });
    var selectedId = selected ? unitId(selected) : '';
    var liveRows = visible.filter(function (unit) { return !isDisconnected(unit); });
    var offRows = visible.filter(function (unit) { return isDisconnected(unit); });
    var html = bftSectionHtml('live', liveRows, selectedId) + bftSectionHtml('offline', offRows, selectedId);
    var emptyMsg = (query || sideFilter !== 'all' || filterWave)
      ? 'Aucun contact ne correspond à ce filtre.'
      : 'Aucun contact en liaison pour le moment.';
    document.getElementById('ow-contact-list').innerHTML = html ||
      '<p class="ow-bft-empty">' + emptyMsg + '</p>';
    var n = visibleUnits().length;
    var bftCount = document.getElementById('ow-bft-count');
    if (bftCount) bftCount.textContent = 'BFT ' + n;
    var sub = document.getElementById('ow-bft-sub');
    if (sub) sub.textContent = n + ' unité' + (n > 1 ? 's' : '');
    renderEffectifsTable(visible);
    syncEmptyNotice();
    renderHud();
    renderSquadList();
  }

  function emptyNoticeStorageKey() {
    return EMPTY_DISMISS_KEY + '-' + mapId;
  }

  function emptyNoticeDismissed() {
    try {
      return sessionStorage.getItem(emptyNoticeStorageKey()) === '1';
    } catch (e) {
      return false;
    }
  }

  function dismissEmptyNotice() {
    try { sessionStorage.setItem(emptyNoticeStorageKey(), '1'); } catch (e) {}
    var el = document.getElementById('ow-empty');
    if (el) el.hidden = true;
  }

  function syncEmptyNotice() {
    var el = document.getElementById('ow-empty');
    if (!el) return;
    var hasContacts = visibleUnits().some(point);
    el.hidden = hasContacts || !config.tilePattern || emptyNoticeDismissed();
  }

  function asList(payload, key) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload[key])) return payload[key];
    if (payload && Array.isArray(payload.data)) return payload.data;
    if (payload && Array.isArray(payload.units)) return payload.units;
    return [];
  }

  function applyPayload(payload) {
    units = stampUnitAges(asList(payload, 'units'));
    lastRx = Date.now();
    renderMap();
    renderList();
    syncStatus(true);
    window.dispatchEvent(new CustomEvent('overwatch:units-updated', { detail: { units: units } }));
    try {
      window.dispatchEvent(new CustomEvent('atak:units-markers-updated', { detail: { units: units } }));
    } catch (e) {}
    if (window.ATAKRadio && typeof window.ATAKRadio.onUnitsUpdated === 'function') {
      try { window.ATAKRadio.onUnitsUpdated(); } catch (e2) {}
    }
    if (window.ATAKUnitDossier && typeof window.ATAKUnitDossier.render === 'function') {
      try { window.ATAKUnitDossier.render(); } catch (e3) {}
    }
  }

  function syncStatus(ok) {
    var session = document.querySelector('.ow-session');
    session.classList.toggle('is-live', ok);
    session.classList.toggle('is-offline', !ok);
    var word = ok ? (realtimeOn ? 'Opérationnel' : 'Synchronisation') : 'Reconnexion';
    document.getElementById('ow-link-label').textContent = ok ? (realtimeOn ? 'En liaison' : 'Synchronisation') : 'Reconnexion';
    document.getElementById('ow-footer-link').textContent = ok ? 'En liaison' : 'Liaison dégradée';
    document.getElementById('ow-status-word').textContent = word;
    var latEl = document.getElementById('ow-latency');
    if (latEl && requestStarted) {
      var dt = Date.now() - requestStarted;
      if (dt >= 0 && dt < 15000) latEl.textContent = 'Rx ' + dt + ' ms';
      requestStarted = 0;
    }
  }

  function refreshUnits() {
    requestStarted = Date.now();
    return api('/api/units?mapId=' + encodeURIComponent(mapId) + '&include_gateway=1')
      .then(function (payload) {
        if (window.ATAKMap && typeof window.ATAKMap.setUnitsMarkers === 'function') {
          window.ATAKMap.setUnitsMarkers(asList(payload, 'units'));
        } else {
          applyPayload(payload);
        }
      })
      .then(function () { return loadPoMarkers(); })
      .then(function () { return loadArmaMarkers(); })
      .then(function () { return loadVehicles(); })
      .then(function () { return loadAirAssets(); })
      .then(function () { return loadTerminals(); })
      .catch(function () { syncStatus(false); });
  }

  function channelLabel(key) {
    var labels = { groupe: 'Groupe', commandement: 'Commandement', general: 'Général', jtac: 'JTAC', air: 'Air', support: 'Support technique' };
    return labels[key] || key;
  }

  function messageKind(row) {
    var src = String(row.source || '').toLowerCase();
    var body = String(row.body || '');
    if (src === 'system' || /^\[sys\]/i.test(body)) return 'system';
    if (/alerte|alert|ko\b|casvac|medevac|contact/i.test(body) || src === 'alert') return 'alert';
    return 'normal';
  }

  function channelIcon(key) {
    var k = String(key || 'general');
    var paths = {
      general: 'M4 6h16v10H7l-3 3z',
      groupe: 'M8 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM16 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM4 19c0-3 2-5 4-5s4 2 4 5M12 19c0-3 2-5 4-5s4 2 4 5',
      commandement: 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z',
      jtac: 'M4 18l8-12 8 12H4z',
      air: 'M12 4l8 7H4zM6 14h12v3H6z',
      support: 'M12 3v4M12 17v4M4.5 7.5l3 3M16.5 13.5l3 3M3 12h4M17 12h4M4.5 16.5l3-3M16.5 10.5l3-3'
    };
    var d = paths[k] || paths.general;
    return '<span class="cicon is-ch-' + escapeHtml(k) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="' + d + '"/></svg></span>';
  }

  function parseCommsBody(body) {
    var raw = String(body || '').trim();
    if (raw.indexOf('GROUPE|') === 0) {
      var p = raw.split('|');
      return { type: 'system', groupId: p[1] || '', author: p[2] || '', code: p[3] || '', text: p.slice(4).join('|') };
    }
    var m = raw.match(/^\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\s*([\s\S]*)$/);
    if (m) {
      return { type: 'radio', gameTime: m[1], net: String(m[2] || '').toUpperCase(), prio: String(m[3] || '').toUpperCase(), crypto: String(m[4] || '').toUpperCase(), text: m[5] || '' };
    }
    return { type: 'unknown', text: raw };
  }
  function prioClass(p) {
    if (p === 'ROUTINE') return 'routine';
    if (p === 'PRIORITY' || p === 'IMMEDIATE' || p === 'IMPORTANT') return 'priority';
    return 'flash';
  }
  function prioLabel(p) {
    var c = prioClass(p);
    if (c === 'priority') return 'priorité';
    if (c === 'flash') return 'urgent';
    return 'routine';
  }
  function cryptoLabel(c) {
    var k = String(c || '').toUpperCase();
    if (k === 'FREE') return 'libre';
    if (k === 'ENC' || k === 'ENCRYPTED' || k === 'CRYPTO') return 'chiffré';
    return String(c || '').toLowerCase();
  }
  function fmtDay(dateStr) {
    var d = new Date(String(dateStr).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(dateStr || '');
    return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
  }
  function fmtClock(dateStr) {
    var s = String(dateStr || '');
    var part = s.indexOf(' ') >= 0 ? s.split(' ')[1] : s.split('T')[1];
    return part ? part.slice(0, 5) : '';
  }

  function myChatAliases() {
    var u = window.ATAK_USER || {};
    var out = [];
    [authorName, u.callsign, u.displayName, u.armaCallsign].forEach(function (v) {
      var s = String(v || '').trim().toLowerCase();
      if (s && out.indexOf(s) < 0) out.push(s);
    });
    return out;
  }
  function isOwnChatRow(row) {
    var a = String((row && row.author) || '').trim().toLowerCase();
    return a !== '' && myChatAliases().indexOf(a) >= 0;
  }
  function chatRowChannel(row) {
    return String((row && (row.channel_key || row.channel)) || 'general').toLowerCase() || 'general';
  }
  function chatReadStore() {
    try {
      return JSON.parse(localStorage.getItem(CHAT_READ_KEY + '-' + mapId) || '{}') || {};
    } catch (e) {
      return {};
    }
  }
  function saveChatReadStore(store) {
    try { localStorage.setItem(CHAT_READ_KEY + '-' + mapId, JSON.stringify(store || {})); } catch (e) {}
  }
  function visibleChatChannel() {
    var support = document.querySelector('[data-chat-panel="support"]');
    if (support && !support.hidden) return 'support';
    return String(activeChannel || 'general');
  }
  function lastReadId(channel) {
    return Number(chatReadStore()[String(channel || '')] || 0) || 0;
  }
  function markChannelRead(channel, rows) {
    var key = String(channel || activeChannel || 'general');
    var maxId = lastReadId(key);
    (rows || []).forEach(function (row) {
      var id = Number(row && row.id);
      if (id > maxId) maxId = id;
    });
    if (maxId < 1) return;
    var store = chatReadStore();
    store[key] = Math.max(Number(store[key] || 0), maxId);
    saveChatReadStore(store);
    unreadByChannel[key] = 0;
    paintUnreadBadges();
  }
  function seedChatRead(rows) {
    var store = chatReadStore();
    var maxBy = {};
    (rows || []).forEach(function (row) {
      var ck = chatRowChannel(row);
      var id = Number(row && row.id) || 0;
      if (id > (maxBy[ck] || 0)) maxBy[ck] = id;
    });
    var changed = false;
    Object.keys(maxBy).forEach(function (ck) {
      if (store[ck] == null) {
        store[ck] = maxBy[ck];
        changed = true;
      }
    });
    if (changed) saveChatReadStore(store);
  }
  function recountUnread(rows) {
    var map = {};
    seedChatRead(rows);
    (rows || []).forEach(function (row) {
      if (isOwnChatRow(row)) return;
      var ck = chatRowChannel(row);
      var id = Number(row && row.id) || 0;
      if (id > 0 && id <= lastReadId(ck)) return;
      if (ck === String(visibleChatChannel() || '')) return;
      map[ck] = (map[ck] || 0) + 1;
    });
    unreadByChannel = map;
    paintUnreadBadges();
  }
  function unreadLabel(n) {
    var c = Math.max(0, Number(n) || 0);
    if (c < 1) return '';
    return c > 99 ? '99+' : String(c);
  }
  function paintUnreadBadges() {
    renderChannels();
    var channelTotal = 0;
    Object.keys(unreadByChannel).forEach(function (k) {
      if (k === 'support') return;
      channelTotal += Number(unreadByChannel[k] || 0);
    });
    var supportN = Number(unreadByChannel.support || 0);
    var tabCh = document.querySelector('[data-unread-tab="channels"]');
    var tabSu = document.querySelector('[data-unread-tab="support"]');
    if (tabCh) {
      tabCh.hidden = channelTotal < 1;
      tabCh.textContent = unreadLabel(channelTotal);
    }
    if (tabSu) {
      tabSu.hidden = supportN < 1;
      tabSu.textContent = unreadLabel(supportN);
    }
    var head = document.getElementById('ow-comms-unread');
    var all = channelTotal + supportN;
    if (head) {
      head.hidden = all < 1;
      head.textContent = unreadLabel(all);
    }
  }
  function loadChatInbox() {
    return api('/api/chat?mapId=' + encodeURIComponent(mapId) + '&limit=200').then(function (payload) {
      recountUnread(asList(payload, 'messages'));
    }).catch(function () {});
  }

  function renderChannels() {
    var filter = (document.getElementById('ow-channel-filter').value || '').toLowerCase();
    var rows = channels.filter(function (ch) {
      var key = String(ch.channel_key || ch.key || '');
      if (key === 'support') return false;
      return (channelLabel(key) + ' ' + key).toLowerCase().indexOf(filter) !== -1;
    });
    if (!rows.length) {
      rows = ['general', 'groupe', 'commandement', 'jtac', 'air'].map(function (key) {
        return { channel_key: key, label: channelLabel(key) };
      });
    }
    document.getElementById('ow-channel-list').innerHTML = rows.map(function (ch) {
      var key = String(ch.channel_key || ch.key || 'general');
      var unread = key === activeChannel ? 0 : Number(unreadByChannel[key] || 0);
      var right = unread > 0
        ? '<em class="ow-unread" aria-label="' + unread + ' messages non lus">' + escapeHtml(unreadLabel(unread)) + '</em>'
        : '<em class="online is-live">Direct</em>';
      return '<button type="button" class="ow-channel' + (key === activeChannel ? ' is-active' : '') + '" data-channel="' +
        escapeHtml(key) + '">' + channelIcon(key) +
        '<span><div class="cname">' + escapeHtml(ch.label || channelLabel(key)) +
        '</div><div class="cmeta">' + escapeHtml(key === activeChannel && chatMessages.length
          ? (parseCommsBody(chatMessages[chatMessages.length - 1].body).text || String(chatMessages[chatMessages.length - 1].body || '')).slice(0, 42)
          : 'Canal mission') +
        '</div></span>' + right + '</button>';
    }).join('');
  }

  function parseMessageBadges(body) {
    var badges = [];
    var text = String(body || '');
    var bracketMatch = text.match(/^\[([\w\s\-]+)\]/);
    if (bracketMatch) {
      badges.push(bracketMatch[1]);
      text = text.substring(bracketMatch[0].length).trim();
    }
    if (/^groupe\s*\|/i.test(text)) {
      badges.push('GROUPE');
    }
    return { badges: badges, text: text };
  }

  function renderChatLog(targetId, rows) {
    var host = document.getElementById(targetId);
    if (!host) return;
    if (targetId === 'ow-chat-log') {
      var q = String((document.getElementById('ow-comms-search') || {}).value || '').trim().toLowerCase();
      if (q) {
        rows = rows.filter(function (row) {
          var parsed = parseCommsBody(row.body);
          return (String(row.author || '') + ' ' + String(parsed.text || row.body || '')).toLowerCase().indexOf(q) >= 0;
        });
      }
    }
    var lastDay = null;
    var lastGroupKey = null;
    var html = '';
    rows.slice(-80).forEach(function (row) {
      var raw = String(row.body || '');
      var parsed = parseCommsBody(raw);
      var real = String(row.created_at || row.time || '');
      var day = real.slice(0, 10);
      if (day && day !== lastDay) {
        if (lastGroupKey) html += '</div></div>';
        html += '<div class="ow-day-sep">' + escapeHtml(fmtDay(real)) + '</div>';
        lastDay = day;
        lastGroupKey = null;
      }
      if (parsed.type === 'system') {
        if (lastGroupKey) html += '</div></div>';
        html += '<div class="ow-sys"><span>◇</span> ' + escapeHtml(parsed.author || clean(row.author, 'Système')) +
          ' a rejoint le groupe ' + escapeHtml(parsed.groupId) +
          ' <span class="code">#' + escapeHtml(parsed.code) + '</span>' +
          (parsed.text ? ' — ' + escapeHtml(parsed.text) : '') + '</div>';
        lastGroupKey = null;
        return;
      }
      var author = clean(row.author, parsed.author || 'Système');
      var net = parsed.net || '';
      var groupKey = author + '|' + net;
      if (groupKey !== lastGroupKey) {
        html += (lastGroupKey ? '</div></div>' : '') +
          '<div class="ow-msg-group"><div class="ow-group-head"><span class="ow-author">' + escapeHtml(author) +
          '</span>' + (net ? '<span class="ow-net-tag ' + (net === 'GROUPE' ? 'groupe' : 'squad') + '">' + escapeHtml(net === 'GROUPE' ? 'Groupe' : 'Squad') + '</span>' : '') +
          '<span class="ow-group-time">' + escapeHtml(fmtClock(real)) + '</span></div><div class="ow-rows">';
        lastGroupKey = groupKey;
      }
      var bar = parsed.prio ? '<span class="bar ' + prioClass(parsed.prio) + '"></span>' : (messageKind(row) === 'alert' ? '<span class="bar flash"></span>' : '');
      var ownDel = (row.id && isOwnChatRow(row))
        ? '<button type="button" class="ow-msg-del" data-del-chat="' + escapeHtml(String(row.id)) + '" title="Retirer ce message">Retirer</button>'
        : '';
      html += '<div class="ow-row-msg">' + bar + '<div class="ow-row-main"><div class="ow-row-text">' +
        escapeHtml(parsed.text || raw) + '</div>' +
        (parsed.prio ? '<div class="ow-row-meta"><span>' + escapeHtml(prioLabel(parsed.prio)) +
          '</span><span>·</span><span class="' + (parsed.crypto === 'FREE' ? 'crypto-free' : 'crypto-enc') + '">' +
          escapeHtml(cryptoLabel(parsed.crypto)) + '</span>' +
          (parsed.gameTime ? '<span>·</span><span>en jeu ' + escapeHtml(parsed.gameTime) + '</span>' : '') +
          '</div>' : '') +
        '</div>' + ownDel + '<div class="ow-raw-preview">' + escapeHtml(raw) + '</div></div>';
    });
    if (lastGroupKey) html += '</div></div>';
    host.innerHTML = html || '<p class="ow-fil-empty">Aucun message pour le moment.</p>';
    host.scrollTop = host.scrollHeight;
  }

  function loadChannels() {
    return api('/api/chat/channels?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      channels = asList(payload, 'channels');
      renderChannels();
    }).catch(function () { renderChannels(); });
  }

  function loadChat(channel) {
    return api('/api/chat?mapId=' + encodeURIComponent(mapId) + '&channel=' + encodeURIComponent(channel) + '&limit=80')
      .then(function (payload) {
        var rows = asList(payload, 'messages');
        if (channel === 'support') {
          supportMessages = rows;
          renderChatLog('ow-support-log', rows);
        } else {
          chatMessages = rows;
          renderChatLog('ow-chat-log', rows);
        }
        markChannelRead(channel, rows);
        return rows;
      }).catch(function () {});
  }

  function dropChatLocal(id) {
    var sid = String(id);
    chatMessages = chatMessages.filter(function (row) { return String(row.id) !== sid; });
    supportMessages = supportMessages.filter(function (row) { return String(row.id) !== sid; });
    renderChatLog('ow-chat-log', chatMessages);
    renderChatLog('ow-support-log', supportMessages);
  }

  function deleteOwnChat(id) {
    if (!id) return;
    api('/api/chat/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
      dropChatLocal(id);
      toast('Message retiré.');
    }).catch(function (err) {
      var code = String(err && err.message || '');
      if (code === '404') {
        dropChatLocal(id);
        toast('Message déjà retiré.');
        return;
      }
      toast(code === '403' ? 'Vous ne pouvez retirer que vos propres messages.' : 'Impossible de retirer ce message.');
    });
  }

  function showChatPurgeConfirm() {
    var host = document.getElementById('ow-chat-purge-box');
    if (!host) return;
    var label = channelLabel(activeChannel);
    host.hidden = false;
    host.innerHTML = '<p>Effacer l’historique de <strong>' + escapeHtml(label) + '</strong> pour tout le poste et les opérateurs ?</p>' +
      '<p class="ow-help">Cette action est définitive.</p>' +
      '<div class="ow-form-actions">' +
      '<button type="button" class="ow-primary" id="ow-purge-channel">Vider ce canal</button>' +
      '<button type="button" class="ow-fil-action" id="ow-purge-all">Tous les canaux</button>' +
      '<button type="button" class="ow-fil-action" id="ow-purge-no">Annuler</button></div>';
    document.getElementById('ow-purge-no').addEventListener('click', function () { host.hidden = true; host.innerHTML = ''; });
    document.getElementById('ow-purge-channel').addEventListener('click', function () { runChatPurge('EFFACER_CANAL'); });
    document.getElementById('ow-purge-all').addEventListener('click', function () { runChatPurge('EFFACER_TOUT'); });
  }

  function runChatPurge(confirmToken) {
    var host = document.getElementById('ow-chat-purge-box');
    var body = { mapId: mapId, confirm: confirmToken };
    if (confirmToken === 'EFFACER_CANAL') body.channel = activeChannel;
    api('/api/chat/purge', { method: 'POST', body: body }).then(function (payload) {
      if (host) { host.hidden = true; host.innerHTML = ''; }
      if (confirmToken === 'EFFACER_TOUT') {
        chatMessages = [];
        supportMessages = [];
        unreadByChannel = {};
        saveChatReadStore({});
      } else {
        chatMessages = [];
        unreadByChannel[activeChannel] = 0;
      }
      renderChatLog('ow-chat-log', chatMessages);
      renderChatLog('ow-support-log', supportMessages);
      paintUnreadBadges();
      toast((payload && payload.message) || 'Historique effacé.');
    }).catch(function (err) {
      var code = String(err && err.message || '');
      toast(code === '403' || code === '401' ? 'Seul le poste peut vider l’historique.' : 'Impossible de vider l’historique.');
    });
  }

  function sendChat(channel, body) {
    var text = String(body || '').trim();
    if (!text) return Promise.resolve();
    return api('/api/chat', {
      method: 'POST',
      body: { mapId: mapId, author: authorName, body: text, channel: channel }
    }).then(function () {
      return loadChat(channel);
    });
  }

  function appendChatMessage(row) {
    if (!row) return;
    var key = chatRowChannel(row);
    if (key === 'support') {
      supportMessages.push(row);
      renderChatLog('ow-support-log', supportMessages);
      if (isOwnChatRow(row)) markChannelRead('support', supportMessages);
      else if (document.querySelector('[data-chat-panel="support"]:not([hidden])')) markChannelRead('support', supportMessages);
      else {
        unreadByChannel.support = (unreadByChannel.support || 0) + 1;
        paintUnreadBadges();
      }
      return;
    }
    if (key === activeChannel || !row.channel_key) {
      chatMessages.push(row);
      renderChatLog('ow-chat-log', chatMessages);
      markChannelRead(activeChannel, chatMessages);
      return;
    }
    if (!isOwnChatRow(row)) {
      unreadByChannel[key] = (unreadByChannel[key] || 0) + 1;
      paintUnreadBadges();
    }
  }

  function geometryOf(shape) {
    var geo = shape.geometry;
    if (typeof geo === 'string') {
      try { geo = JSON.parse(geo); } catch (e) { return null; }
    }
    return geo;
  }

  function drawShape(shape) {
    if (hiddenLayers.shapes) return;
    var id = String(shape.id || shape.shape_uid || '');
    if (!id) return;
    if (shapeLayers[id]) {
      map.removeLayer(shapeLayers[id]);
      delete shapeLayers[id];
    }
    var geo = geometryOf(shape);
    if (!geo) return;
    var color = shape.color || '#00d69a';
    var layer = null;
    var type = String(shape.type || '').toUpperCase();
    var geoType = String(geo.type || '').toLowerCase();
    if (!type) {
      if (geoType === 'point') type = 'POINT';
      else if (geoType === 'linestring') type = 'LINE';
      else if (geoType === 'polygon') type = 'POLYGON';
    }
    var meta = parseShapeMeta(shape);
    var nato = String(meta.nato || meta.kind || '');
    var dash = natoDash(meta);
    var latlngs = [];
    var isLine = type === 'LINE' || type === 'ROUTE' || type === 'POLYLINE' || geoType === 'linestring';
    var isPoly = type === 'POLYGON' || type === 'AOI' || geoType === 'polygon';
    if (type === 'POINT' && geo.coordinates) {
      var pll = worldToLatLng(geo.coordinates[0], geo.coordinates[1]);
      if (meta.kind === 'buildingPlan') {
        var pin = document.createElement('div');
        pin.className = 'ow-bplan-pin';
        pin.innerHTML = '<small>' + escapeHtml(shape.label || 'Plan') + '</small>Plan rattaché';
        layer = L.marker(pll, { icon: L.divIcon({ className: '', html: pin.outerHTML, iconSize: [140, 36], iconAnchor: [70, 36] }) });
      } else {
        layer = L.circleMarker(pll, { radius: 8, color: color, weight: 2, fillOpacity: 0.5 });
      }
    } else if (isLine && Array.isArray(geo.coordinates)) {
      latlngs = geo.coordinates.map(function (p) {
        if (!p) return worldToLatLng(0, 0);
        if (Array.isArray(p)) return worldToLatLng(p[0], p[1]);
        return worldToLatLng(p.x != null ? p.x : p.lng, p.y != null ? p.y : p.lat);
      });
      if (nato === 'attack') {
        layer = L.polygon(filledAttackLatLngs(latlngs, 16), {
          color: color, fillColor: color, fillOpacity: 0.92, weight: 1
        });
      } else {
        layer = L.polyline(latlngs, {
          color: color,
          weight: Number(shape.stroke || (nato === 'arrow' || nato === 'axis' ? 3 : 2)),
          dashArray: dash || undefined
        });
      }
    } else if (isPoly && Array.isArray(geo.coordinates)) {
      var ring = geo.coordinates[0] && Array.isArray(geo.coordinates[0][0]) ? geo.coordinates[0] : geo.coordinates;
      latlngs = ring.map(function (p) { return worldToLatLng(p[0], p[1]); });
      var fillStyle = String(meta.fill_style || (meta.hatch ? 'hatch-d' : 'solid'));
      var hatchClass = fillStyle === 'hatch-h' ? 'ow-hatch-h' : (fillStyle === 'hatch-d' || meta.hatch ? 'ow-hatch-diag' : '');
      var fillOp = fillStyle === 'none' ? 0 : Number(shape.fillOpacity || shape.fill_opacity || meta.fill_opacity || 0.15);
      if (nato === 'assembly' || nato === 'objective') fillOp = 0;
      if (nato === 'highlight') fillOp = Number(meta.fill_opacity || 0.18);
      layer = L.polygon(latlngs, {
        color: color,
        fillColor: meta.fill_color || color,
        fillOpacity: fillOp,
        className: hatchClass,
        dashArray: dash || undefined,
        weight: nato === 'highlight' ? 1.5 : 2
      });
      if (meta.interior && layer.bindTooltip) {
        layer.bindTooltip(String(meta.interior), { permanent: true, direction: 'center', className: 'ow-geo-label' });
      }
    }
    if (!layer) return;
    if (meta.interior && type !== 'POLYGON' && type !== 'AOI' && layer.bindTooltip && !layer.getTooltip()) {
      layer.bindTooltip(String(meta.interior), { permanent: true, direction: 'center', className: 'ow-geo-label' });
    }
    if ((nato === 'arrow' || nato === 'axis' || meta.arrow) && type !== 'POINT' && nato !== 'attack' && latlngs.length >= 2) {
      var group = L.featureGroup([layer]);
      var head = arrowHeadPolygon(latlngs, color, false);
      if (head) group.addLayer(head);
      layer = group;
    }
    if (nato === 'sector' && latlngs.length >= 2) {
      var ticks = L.featureGroup(layer instanceof L.FeatureGroup ? [layer] : [layer]);
      sectorTickLayers(latlngs, color).forEach(function (tick) { ticks.addLayer(tick); });
      layer = ticks;
    }
    layer.addTo(map);
    shapeLayers[id] = layer;
    bindLayerContext(layer, 'shape', id, shape.label || shape.type || 'Tracé');
    var labelNato = /^(phase|assembly|objective|highlight|sector|axis|attack)$/.test(nato);
    if ((type === 'POINT' || labelNato) && shape.label && layer.bindTooltip && !layer.getTooltip()) {
      layer.bindTooltip(String(shape.label), {
        permanent: labelNato,
        direction: labelNato ? 'center' : 'top',
        sticky: !labelNato,
        className: labelNato ? 'ow-geo-label ow-nato-label' : ''
      });
    }
  }

  function loadShapes() {
    return api('/api/map-shapes?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      shapes = asList(payload, 'shapes');
      Object.keys(shapeLayers).forEach(function (id) { map.removeLayer(shapeLayers[id]); delete shapeLayers[id]; });
      shapes.forEach(drawShape);
      updateStatsBanner();
    }).catch(function () {});
  }

  function saveShape(type, latlngs, label, opts) {
    opts = opts || {};
    latlngs = (latlngs || []).slice();
    if (drawMode && !opts.confirmed) {
      opts = Object.assign({}, opts, { confirmed: true });
    }
    if (!opts.confirmed && window.OverwatchOps && typeof window.OverwatchOps.promptShape === 'function') {
      return window.OverwatchOps.promptShape(type, latlngs, label);
    }
    var style = drawStyle();
    var coords = latlngs.map(function (ll) {
      var w = latLngToWorld(ll);
      return [w.x, w.y];
    });
    var geometry = type === 'POINT'
      ? { type: 'Point', coordinates: coords[0] }
      : (type === 'POLYGON' || type === 'AOI'
        ? { type: 'Polygon', coordinates: [coords.concat([coords[0]])] }
        : { type: 'LineString', coordinates: coords });
    return api('/api/map-shapes', {
      method: 'POST',
      body: {
        mapId: mapId,
        type: type,
        label: label || type,
        color: opts.color || style.color,
        stroke: opts.stroke != null ? opts.stroke : style.stroke,
        fillOpacity: opts.fillOpacity != null ? opts.fillOpacity : 0.15,
        geometry: geometry,
        createdBy: authorName,
        meta: opts.meta || {}
      }
    }).then(function (row) {
      clearDraft();
      if (row) {
        shapes.push(row);
        drawShape(row);
        lastShapeId = Number(row.id || 0);
        if (lastShapeId) shapeUndoStack.push(lastShapeId);
        shapeRedoStack = [];
      }
      toast((label || type) + ' enregistré.');
      return row;
    }).catch(function () { toast('Enregistrement impossible.'); });
  }

  function saveMarker(ll, label) {
    if (window.OverwatchOps && typeof window.OverwatchOps.promptMarker === 'function' && !isPoLabel(label)) {
      return window.OverwatchOps.promptMarker(ll);
    }
    var w = latLngToWorld(ll);
    var text = label || 'Marqueur';
    return api('/api/markers', {
      method: 'POST',
      body: {
        mapId: mapId,
        markerData: {
          type: isPoLabel(text) ? 'mil_objective' : 'mil_dot',
          label: text,
          text: text,
          author: authorName,
          pos: [w.x, w.y],
          pos_x: w.x,
          pos_y: w.y,
          source: 'web',
          po: isPoLabel(text)
        }
      }
    }).then(function (row) {
      var id = row && row.id != null ? String(row.id) : '';
      var layer = L.circleMarker(ll, { radius: 6, color: '#e7b14d' }).addTo(map);
      bindLayerContext(layer, 'marker', id, text);
      if (id) postedMarkers[id] = layer;
      toast(isPoLabel(text) ? ('Point d’objectif posé — ' + text + ' · rayon 20 m') : 'Marqueur posé.');
      if (isPoLabel(text)) loadPoMarkers();
    }).catch(function (err) {
      toast('Marqueur refusé.');
      throw err;
    });
  }

  function poNumberOf(label) {
    var match = String(label || '').trim().match(/^PO(?:[\s\-_.#]*(\d+))?$/i);
    if (!match) return 0;
    return match[1] ? parseInt(match[1], 10) : 1;
  }
  function nextPoLabel() {
    var max = 0;
    poRows.forEach(function (row) { max = Math.max(max, poNumberOf(row.label)); });
    poPlaceSession.forEach(function (row) { max = Math.max(max, poNumberOf(row.label)); });
    return 'PO ' + (max + 1);
  }
  var lastPoPlaceAt = 0;
  var lastPoLl = null;
  function placeReachPoint(ll, label) {
    var now = Date.now();
    if (lastPoLl && now - lastPoPlaceAt < 400 && map.distance(lastPoLl, ll) < 12) {
      return Promise.resolve();
    }
    lastPoPlaceAt = now;
    lastPoLl = ll;
    var text = String(label || nextPoLabel()).trim() || nextPoLabel();
    var w = latLngToWorld(ll);
    poPlaceSession.push({ x: w.x, y: w.y, label: text });
    return saveMarker(ll, text).catch(function () { poPlaceSession.pop(); });
  }
  function finishPoSession() {
    var session = poPlaceSession.slice();
    poPlaceSession = [];
    if (!session.length) return;
    api('/api/atak/waypoint-routes', {
      method: 'POST',
      body: {
        mapId: mapId,
        route_name: session.length === 1 ? session[0].label : ('Points à atteindre · ' + session[0].label + ' → ' + session[session.length - 1].label),
        route_type: 'PATROL',
        status: 'ACTIVE',
        is_visible: true,
        waypoints: session.map(function (point, index) {
          return {
            pos_x: point.x,
            pos_y: point.y,
            sequence_number: index + 1,
            label: point.label,
            radius_m: 20,
            waypoint_type: 'CHECKPOINT'
          };
        })
      }
    }).then(function () {
      toast(session.length === 1
        ? (session[0].label + ' transmis aux opérateurs · détection 20 m')
        : (session.length + ' points à atteindre transmis · détection 20 m'));
    }).catch(function () {
      toast(session.length === 1
        ? (session[0].label + ' posé au poste · détection 20 m. Guidage terrain non transmis.')
        : (session.length + ' points posés au poste · détection 20 m. Guidage terrain non transmis.'));
    });
  }

  function savePing(ll) {
    var w = latLngToWorld(ll);
    return api('/api/pings', {
      method: 'POST',
      body: { mapId: mapId, author: authorName, pos_x: w.x, pos_y: w.y, message: 'Quick Ping' }
    }).then(function (row) {
      wrapCot('a-h-G-U-C', { x: w.x, y: w.y, author: authorName });
      var id = String((row && row.id) || Date.now());
      pingMarkers[id] = L.circleMarker(ll, { radius: 8, color: '#00d69a' }).addTo(map);
      bindLayerContext(pingMarkers[id], 'ping', id, 'Repère rapide');
      toast('Quick Ping transmis.');
    }).catch(function () { toast('Ping refusé.'); });
  }

  function saveSitrep(ll) {
    if (window.OverwatchOps && typeof window.OverwatchOps.openSitrep === 'function') {
      return window.OverwatchOps.openSitrep(ll);
    }
    toast('Ouvrez le compte rendu depuis le menu contextuel.');
  }

  function saveIntelNote(ll) {
    if (window.OverwatchOps && typeof window.OverwatchOps.openIntel === 'function') {
      return window.OverwatchOps.openIntel(ll);
    }
    toast('Ouvrez l’observation depuis le menu contextuel.');
  }

  function copyCoords(ll) {
    var w = latLngToWorld(ll);
    var text = Math.round(w.x) + ' / ' + Math.round(w.y);
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text);
    toast('Coordonnées copiées : ' + text);
  }

  function clearDraft() {
    draftPoints = [];
    if (draftLayer) { map.removeLayer(draftLayer); draftLayer = null; }
  }

  function updateDraft() {
    if (draftLayer) { map.removeLayer(draftLayer); draftLayer = null; }
    var style = drawStyle();
    if (activeTool === 'circle' && draftPoints.length === 2) {
      draftLayer = L.polygon(circleLatLngs(draftPoints[0], draftPoints[1]), { color: style.color, dashArray: '4 4' }).addTo(map);
      return;
    }
    if (activeTool === 'rect' && draftPoints.length === 2) {
      draftLayer = L.polygon(rectLatLngs(draftPoints[0], draftPoints[1]), { color: style.color, dashArray: '4 4' }).addTo(map);
      return;
    }
    if ((activeTool === 'highlight' || activeTool === 'assembly') && draftPoints.length === 2) {
      var previewColor = activeTool === 'highlight' ? '#f0a63a' : style.color;
      draftLayer = L.polygon(rectLatLngs(draftPoints[0], draftPoints[1]), {
        color: previewColor,
        fillColor: previewColor,
        fillOpacity: activeTool === 'highlight' ? 0.18 : 0,
        dashArray: activeTool === 'highlight' ? '6 4' : '4 4'
      }).addTo(map);
      return;
    }
    if (activeTool === 'objective' && draftPoints.length === 2) {
      draftLayer = L.polygon(ellipseLatLngs(draftPoints[0], draftPoints[1]), { color: style.color, fillOpacity: 0, dashArray: '4 4' }).addTo(map);
      return;
    }
    if (activeTool === 'attack' && draftPoints.length >= 2) {
      draftLayer = L.polygon(filledAttackLatLngs(draftPoints, 16), { color: style.color, fillColor: style.color, fillOpacity: 0.85, weight: 1 }).addTo(map);
      return;
    }
    if (draftPoints.length === 1) {
      draftLayer = L.circleMarker(draftPoints[0], { radius: 5, color: style.color }).addTo(map);
    } else if (draftPoints.length > 1) {
      var closed = activeTool === 'polygon' || activeTool === 'aoi';
      draftLayer = (closed ? L.polygon : L.polyline)(draftPoints, { color: style.color, dashArray: '4 4', weight: style.stroke }).addTo(map);
    }
  }

  function nearestUnitAt(world, maxM) {
    var best = null;
    var bestD = maxM;
    (units || []).forEach(function (u) {
      var w = unitWorld(u);
      if (!w) return;
      var d = Math.hypot(w.x - world.x, w.y - world.y);
      if (d <= bestD) {
        best = u;
        bestD = d;
      }
    });
    return best;
  }
  function altitudeAtPoint(world) {
    var u = nearestUnitAt(world, 80);
    if (!u) return null;
    var alt = unitAlt(u);
    return alt != null && isFinite(alt) ? alt : null;
  }
  function requestLos(fromLl, toLl) {
    var a = latLngToWorld(fromLl);
    var b = latLngToWorld(toLl);
    var oz = altitudeAtPoint(a);
    var tz = altitudeAtPoint(b);
    var observer = { x: a.x, y: a.y };
    var target = { x: b.x, y: b.y };
    if (oz != null) observer.z = oz;
    if (tz != null) target.z = tz;
    api('/api/atak/terrain/los', {
      method: 'POST',
      body: { mapId: mapId, observer: observer, target: target }
    }).then(function (payload) {
      if (!payload || payload.ready === false) {
        openDrawer('Visée', 'Masque du relief', '<p class="ow-help">' + escapeHtml((payload && (payload.gap_message || payload.message || payload.verdict_label)) || 'Relief non relevé.') + '</p>');
        return;
      }
      var color = payload.verdict === 'clear' ? '#00d69a' : (payload.verdict === 'masked' ? '#e05b63' : '#e7b14d');
      var extraCause = '';
      if (window.OverwatchOps && typeof window.OverwatchOps.drawLos === 'function') {
        extraCause = window.OverwatchOps.drawLos(fromLl, toLl, payload) || '';
      } else {
        var line = L.polyline([fromLl, toLl], { color: color, weight: 3, className: 'ow-los-clear' }).addTo(map);
        registerScratch(line, 'los', 'los-' + Date.now(), 'Visée');
      }
      var html = '<p class="ow-help">' + escapeHtml(payload.detail || payload.verdict_label || '') + '</p>' + extraCause +
        '<div class="ow-event"><span>Résultat</span><strong>' + escapeHtml(payload.verdict_label || '') + '</strong></div>' +
        '<div class="ow-event"><span>Distance</span><strong>' + formatMeters(Number(payload.distance_m || 0)) + '</strong></div>';
      if (payload.observer_ground_z != null && payload.target_ground_z != null) {
        var dg = Number(payload.target_ground_z) - Number(payload.observer_ground_z);
        var slope = Math.abs(dg) < 4 ? 'quasi plat' : (dg < 0 ? 'en descente' : 'en montée');
        html += '<div class="ow-event"><span>Pente du sol</span><strong>' + slope + ' · ' + Math.round(Math.abs(dg)) + ' m</strong></div>';
      }
      if (payload.observer_z != null) {
        html += '<div class="ow-event"><span>' + (payload.observer_from_unit ? 'Observateur (appareil)' : 'Observateur') + '</span><strong>' + Math.round(payload.observer_z) + ' m</strong></div>';
      }
      if (payload.target_z != null) {
        html += '<div class="ow-event"><span>' + (payload.target_from_unit ? 'Cible (appareil)' : 'Cible') + '</span><strong>' + Math.round(payload.target_z) + ' m</strong></div>';
      }
      html += '<div class="ow-event"><span>Visibilité</span><strong>' + formatMeters(Number(payload.distance_m || 0)) + '</strong></div>';
      if (payload.obstruction) {
        var obs = payload.obstruction;
        html += '<div class="ow-event"><span>Obstruction</span><strong>' + escapeHtml(obs.kind_label || obs.cause || 'Obstacle') + '</strong></div>';
        if (obs.z != null) html += '<div class="ow-event"><span>Altitude obstacle</span><strong>' + Math.round(Number(obs.z)) + ' m</strong></div>';
      }
      openDrawer('Visée', 'Masque du relief', html);
      toast(payload.verdict_label || 'Visée calculée.');
    }).catch(function () {
      openDrawer('Visée', 'Masque du relief', '<p class="ow-help">Relief non relevé.</p>');
    });
  }

  function undoLastShape() {
    var id = shapeUndoStack.pop() || lastShapeId;
    if (!id) { toast('Aucun tracé récent à retirer.'); return; }
    var snap = shapes.filter(function (row) { return Number(row.id) === Number(id); })[0] || null;
    api('/api/map-shapes/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
      if (shapeLayers[String(id)]) { map.removeLayer(shapeLayers[String(id)]); delete shapeLayers[String(id)]; }
      shapes = shapes.filter(function (row) { return Number(row.id) !== Number(id); });
      lastShapeId = shapeUndoStack[shapeUndoStack.length - 1] || 0;
      if (snap) shapeRedoStack.push(snap);
      toast('Dernier tracé retiré.');
    }).catch(function () { toast('Impossible de retirer ce tracé.'); });
  }

  function redoLastShape() {
    var snap = shapeRedoStack.pop();
    if (!snap) { toast('Rien à rétablir.'); return; }
    var geo = geometryOf(snap);
    var latlngs = geometryToLatLngs(geo);
    if (!latlngs.length) { toast('Impossible de rétablir ce tracé.'); return; }
    saveShape(String(snap.type || 'LINE').toUpperCase(), latlngs, snap.label || 'Tracé', {
      confirmed: true,
      color: snap.color,
      stroke: snap.stroke,
      fillOpacity: snap.fillOpacity || snap.fill_opacity,
      meta: parseShapeMeta(snap)
    });
  }

  function finishDraft() {
    if (activeTool === 'po') {
      return;
    }
    if ((activeTool === 'measure' || activeTool === 'bearing') && draftPoints.length >= 2) {
      var meters = pathLength(draftPoints);
      var cap = Math.round(bearingWorld(draftPoints[0], draftPoints[draftPoints.length - 1]));
      toast('Distance : ' + formatMeters(meters) + ' · Cap ' + cap + '°');
      showCalcDrawer(draftPoints, meters, cap);
      clearDraft();
      return;
    }
    if (activeTool === 'measure3d' && draftPoints.length >= 2) {
      if (window.OverwatchGlTactics) window.OverwatchGlTactics.requestMeasure3d(draftPoints.slice());
      clearDraft();
      return;
    }
    if (activeTool === 'slice' && draftPoints.length >= 2) {
      if (window.OverwatchGlTactics) window.OverwatchGlTactics.requestSlice(draftPoints.slice());
      clearDraft();
      return;
    }
    if (activeTool === 'volume' && draftPoints.length >= 3) {
      if (window.OverwatchGlTactics) window.OverwatchGlTactics.saveVolume(draftPoints.slice());
      clearDraft();
      return;
    }
    if (activeTool === 'circle' && draftPoints.length >= 2) {
      var ring = circleLatLngs(draftPoints[0], draftPoints[1]);
      var radius = map.distance(draftPoints[0], draftPoints[1]);
      saveShape('AOI', ring, 'Cercle ' + formatMeters(radius));
      toast('Cercle · rayon ' + formatMeters(radius) + ' · surface ' + Math.round(Math.PI * radius * radius) + ' m²');
      return;
    }
    if (activeTool === 'rect' && draftPoints.length >= 2) {
      var rect = rectLatLngs(draftPoints[0], draftPoints[1]);
      saveShape('AOI', rect, 'Rectangle');
      toast('Rectangle · ' + Math.round(polygonAreaM2(rect)) + ' m²');
      return;
    }
    if (activeTool === 'freehand' && draftPoints.length >= 2) {
      saveShape('LINE', draftPoints.slice(), 'Croquis');
      toast('Croquis · ' + formatMeters(pathLength(draftPoints)));
      return;
    }
    if (activeTool === 'measure' && draftPoints.length >= 2) {
      var mPts = draftPoints.slice();
      var mMeters = map.distance(mPts[0], mPts[mPts.length - 1]);
      var mCap = Math.round(bearingWorld(mPts[0], mPts[mPts.length - 1]));
      toast('Distance : ' + formatMeters(mMeters) + ' · Cap ' + mCap + '°');
      showCalcDrawer(mPts, mMeters, mCap);
      measureFrom = null;
      clearDraft();
      return;
    }
    if (activeTool === 'los' && draftPoints.length >= 2) {
      requestLos(draftPoints[0], draftPoints[1]);
      clearDraft();
      return;
    }
    if ((activeTool === 'eta' || activeTool === 'profile') && draftPoints.length >= 2) {
      window.dispatchEvent(new CustomEvent('overwatch:draft-finish', {
        detail: { tool: activeTool, points: draftPoints.slice() }
      }));
      clearDraft();
      return;
    }
    if (activeTool === 'split' && splitTarget && draftPoints.length >= 2) {
      window.dispatchEvent(new CustomEvent('overwatch:draft-finish', {
        detail: { tool: 'split', points: draftPoints.slice(), parent: splitTarget }
      }));
      splitTarget = null;
      clearDraft();
      return;
    }
    if (activeTool === 'arrow' && draftPoints.length >= 2) {
      saveShape('LINE', draftPoints.slice(), 'Flèche', { confirmed: true, meta: { nato: 'arrow', arrow: true } });
      return;
    }
    if (activeTool === 'axis' && draftPoints.length >= 2) {
      saveShape('LINE', draftPoints.slice(), 'Axe de progression', { confirmed: true, meta: { nato: 'axis', arrow: true } });
      return;
    }
    if (activeTool === 'attack' && draftPoints.length >= 2) {
      saveShape('LINE', draftPoints.slice(), 'Attaque principale', { confirmed: true, meta: { nato: 'attack', arrow: true, filled: true } });
      return;
    }
    if (activeTool === 'phase' && draftPoints.length >= 2) {
      var phaseName = window.prompt('Nom de la ligne de phase', 'PL ') || 'Ligne de phase';
      saveShape('LINE', draftPoints.slice(), phaseName.trim(), { confirmed: true, meta: { nato: 'phase' } });
      return;
    }
    if (activeTool === 'sector' && draftPoints.length >= 2) {
      var sectorName = window.prompt('Limite de secteur', '') || 'Limite de secteur';
      saveShape('LINE', draftPoints.slice(), sectorName.trim(), { confirmed: true, meta: { nato: 'sector' } });
      return;
    }
    if (activeTool === 'highlight' && draftPoints.length >= 2) {
      var hiRing = draftPoints.length === 2 ? rectLatLngs(draftPoints[0], draftPoints[1]) : draftPoints.slice();
      var hiName = window.prompt('Libellé de la zone', 'ZONE À RISQUE') || 'ZONE À RISQUE';
      var hiColor = drawStyle().color || '#f0a63a';
      saveShape('AOI', hiRing, hiName.trim(), {
        confirmed: true,
        color: hiColor,
        fillOpacity: 0.18,
        meta: { nato: 'highlight', fill_opacity: 0.18, fill_color: hiColor }
      });
      return;
    }
    if (activeTool === 'assembly' && draftPoints.length >= 2) {
      var aaRing = draftPoints.length === 2 ? rectLatLngs(draftPoints[0], draftPoints[1]) : draftPoints.slice();
      var aaName = window.prompt('Zone de rassemblement', 'AA ') || 'Zone de rassemblement';
      saveShape('AOI', aaRing, aaName.trim(), {
        confirmed: true,
        fillOpacity: 0,
        meta: { nato: 'assembly', fill_style: 'none' }
      });
      return;
    }
    if (activeTool === 'objective' && draftPoints.length >= 2) {
      var objRing = ellipseLatLngs(draftPoints[0], draftPoints[draftPoints.length - 1]);
      var objName = window.prompt('Objectif', 'OBJ ') || 'Objectif';
      saveShape('AOI', objRing, objName.trim(), {
        confirmed: true,
        fillOpacity: 0,
        meta: { nato: 'objective', fill_style: 'none' }
      });
      return;
    }
    if ((activeTool === 'line' || activeTool === 'route') && draftPoints.length >= 2) {
      saveShape(activeTool === 'route' ? 'ROUTE' : 'LINE', draftPoints.slice(), activeTool === 'route' ? 'Route' : 'Ligne');
      return;
    }
    if ((activeTool === 'polygon' || activeTool === 'aoi') && draftPoints.length >= 3) {
      saveShape(activeTool === 'aoi' ? 'AOI' : 'POLYGON', draftPoints.slice(), activeTool === 'aoi' ? 'Zone tactique' : 'Zone');
      toast('Zone · ' + Math.round(polygonAreaM2(draftPoints)) + ' m²');
    }
  }

  function keepMeasureOverlay(points) {
    if (measureKeepLayer) {
      try { map.removeLayer(measureKeepLayer); } catch (eKeep) {}
      measureKeepLayer = null;
    }
    if (!points || points.length < 2) return;
    measureKeepLayer = L.polyline(points, { color: '#7eb0ff', weight: 2, dashArray: '6 4', interactive: false }).addTo(map);
  }

  function showCalcDrawer(points, meters, cap) {
    var a = points[0];
    var b = points[points.length - 1];
    keepMeasureOverlay(points);
    var html = '<p class="ow-help">Mesure entre deux points du théâtre. Les temps de parcours restent indicatifs.</p>' +
      '<div class="ow-event"><span>Départ</span><strong>' + escapeHtml(gridLabel(a)) + '</strong></div>' +
      '<div class="ow-event"><span>Arrivée</span><strong>' + escapeHtml(gridLabel(b)) + '</strong></div>' +
      '<div class="ow-event"><span>Distance</span><strong>' + formatMeters(meters) + '</strong></div>' +
      '<div class="ow-event"><span>Cap</span><strong>' + cap + '°</strong></div>';
    if (points.length >= 3) {
      html += '<div class="ow-event"><span>Périmètre</span><strong>' + formatMeters(pathLength(points.concat([points[0]]))) + '</strong></div>';
      html += '<div class="ow-event"><span>Surface</span><strong>' + Math.round(polygonAreaM2(points)) + ' m²</strong></div>';
    }
    html += '<div class="ow-event"><span>À pied (~5 km/h)</span><strong>' + Math.max(1, Math.round(meters / 1.4 / 60)) + ' min</strong></div>';
    html += '<div class="ow-event"><span>Véhicule (~40 km/h)</span><strong>' + Math.max(1, Math.round(meters / 11 / 60)) + ' min</strong></div>';
    openDrawer('Calcul', 'Mesure', html);
  }

  function setTool(tool, keepDraw) {
    var previous = activeTool;
    if (previous === 'po' && tool !== 'po') {
      finishPoSession();
      try { map.doubleClickZoom.enable(); } catch (e) {}
    }
    if (previous === 'rally' && tool !== 'rally') {
      try { map.doubleClickZoom.enable(); } catch (eRally) {}
    }
    if (tool === 'draw') {
      drawMode = true;
      tool = 'arrow';
    } else if (keepDraw) {
      drawMode = true;
    } else if (tool === 'cursor' || !isDrawBarTool(tool)) {
      drawMode = false;
    }
    activeTool = tool;
    document.querySelectorAll('[data-tool]').forEach(function (button) {
      var on = button.dataset.tool === tool;
      if (drawMode && button.closest && button.closest('.ow-rail') && button.dataset.tool === 'cursor') on = false;
      button.classList.toggle('is-active', on);
    });
    syncDrawBar();
    if (window.OverwatchGlTactics && window.OverwatchGlTactics.isSplit()) {
      var cmp = document.querySelector('[data-tool="compare"]');
      if (cmp) cmp.classList.add('is-active');
    }
    var extra = document.getElementById('ow-rail-extra');
    var more = document.querySelector('[data-ow-rail-more]');
    if (extra && more) {
      var inExtra = !!(tool && extra.querySelector('[data-tool="' + tool + '"]'));
      extra.hidden = !inExtra;
      more.setAttribute('aria-expanded', inExtra ? 'true' : 'false');
      more.classList.toggle('is-open', inExtra);
      more.textContent = inExtra ? '‹' : '›';
    }
    if (tool === 'cursor') clearDraft();
    map.getContainer().style.cursor = tool === 'cursor' ? '' : 'crosshair';
    if (tool === 'center') map.fitBounds(bounds);
    if (tool === 'refresh') refreshAll();
    if (tool === 'locate' && navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(function () { map.setView(center, Math.max(map.getZoom(), 4)); toast('Recentrage théâtre.'); });
    }
    if (tool === 'eta') toast('Maintenez du départ à l’arrivée, ou cliquez les points puis double-clic.');
    if (tool === 'profile') toast('Maintenez pour tracer le profil, relâchez pour relever le relief.');
    if (tool === 'circle') toast('Maintenez au centre et glissez le rayon.');
    if (tool === 'rect') toast('Maintenez un coin et glissez l’opposé.');
    if (tool === 'highlight') setDrawTint('#f0a63a');
    if (tool === 'freehand') toast('Maintenez le clic pour croquer, relâchez pour poser le trait.');
    if (tool === 'text') toast('Cliquez l’emplacement du texte.');
    if (tool === 'bearing') toast('Maintenez du départ à l’arrivée.');
    if (tool === 'los') toast('Maintenez de l’observateur à la cible.');
    if (tool === 'polygon' || tool === 'aoi') toast('Maintenez pour tracer le contour. Relâchez pour fermer la zone.');
    if (tool === 'line' || tool === 'route' || tool === 'arrow' || tool === 'axis' || tool === 'attack') toast('Maintenez pour un segment, ou cliquez des sommets puis double-clic.');
    if (tool === 'phase') toast('Tracez la ligne de phase, puis nommez-la (ex. PL BISON).');
    if (tool === 'sector') toast('Tracez la limite de secteur, puis indiquez les unités.');
    if (tool === 'highlight') toast('Glissez un rectangle : zone surlignée, semi-transparente.');
    if (tool === 'assembly') toast('Glissez le rectangle de la zone de rassemblement.');
    if (tool === 'objective') toast('Glissez l’ellipse de l’objectif.');
    if (tool === 'measure') toast('Cliquez le départ, puis l’arrivée. Distance, cap, grilles et temps s’affichent.');
    if (tool === 'viewshed') toast('Cliquez un opérateur, une caméra ou un point d’observation.');
    if (tool === 'horizon') toast('Cliquez le point depuis lequel lire l’horizon.');
    if (tool === 'slice') toast('Glissez de A vers B pour la coupe verticale.');
    if (tool === 'measure3d') toast('Glissez deux points : distance au sol, spatiale, dénivelé, cap et pente.');
    if (tool === 'volume') toast('Tracez le polygone du volume, double-clic pour poser les altitudes.');
    if (tool === 'compare') {
      var tac = window.OverwatchGlTactics;
      if (tac && typeof tac.setSplit === 'function') tac.setSplit(!tac.isSplit());
      setTool('cursor');
      return;
    }
    if (tool === 'bookmark') {
      var t2 = window.OverwatchGlTactics;
      if (t2 && typeof t2.captureBookmark === 'function') t2.captureBookmark();
      setTool('cursor');
      return;
    }
    if (tool === 'po') {
      if (previous !== 'po') poPlaceSession = [];
      try { map.doubleClickZoom.disable(); } catch (e2) {}
      toast('Cliquez pour poser un point à atteindre. Rayon 20 m. Double-clic pour terminer.');
    }
    if (tool === 'rally') {
      try { map.doubleClickZoom.disable(); } catch (e3) {}
      toast('Cliquez pour poser un point de ralliement. Rayon 50 m. Double-clic pour terminer.');
    }
    if (tool === 'goto') toast('Saisissez une grille ou cliquez un point.');
    if (tool === 'range') toast('Cliquez le centre des anneaux de portée.');
    if (tool === 'undo') { undoLastShape(); setTool('cursor'); return; }
    if (tool !== 'freehand') { freehandOn = false; try { map.dragging.enable(); } catch (e) {} }
    window.dispatchEvent(new CustomEvent('overwatch:tool', { detail: { tool: tool } }));
  }

  function onMapClick(event) {
    hideContext();
    if (window.OverwatchTacmap && typeof window.OverwatchTacmap.consumeMapClick === 'function' && window.OverwatchTacmap.consumeMapClick(event.latlng)) return;
    if (activeTool === 'cursor' || activeTool === 'freehand' || activeTool === 'goto' || activeTool === 'range') return;
    if (activeTool === 'text') {
      var label = window.prompt('Texte à poser sur la carte', '');
      if (label && label.trim()) saveShape('POINT', [event.latlng], label.trim());
      return;
    }
    if (activeTool === 'marker') { saveMarker(event.latlng, 'Marqueur'); return; }
    if (activeTool === 'viewshed') {
      if (window.OverwatchGlTactics) window.OverwatchGlTactics.requestViewshed(event.latlng);
      return;
    }
    if (activeTool === 'horizon') {
      if (window.OverwatchGlTactics) window.OverwatchGlTactics.requestHorizon(event.latlng);
      return;
    }
    if (activeTool === 'po') { placeReachPoint(event.latlng); return; }
    if (activeTool === 'rally') { placeRallyPoint(event.latlng); return; }
    if (activeTool === 'split') {
      var hit = null;
      Object.keys(shapeLayers).forEach(function (id) {
        var layer = shapeLayers[id];
        if (layer instanceof L.Polygon && layer.getBounds().contains(event.latlng)) hit = shapes.filter(function (s) { return String(s.id) === id; })[0];
      });
      if (!splitTarget) {
        splitTarget = hit;
        toast(hit ? 'Zone retenue. Tracez la ligne de coupe.' : 'Cliquez d’abord une zone existante.');
        if (hit) draftPoints = [];
        return;
      }
    }
    draftPoints.push(event.latlng);
    updateDraft();
    if (activeTool === 'measure' && measureFrom) {
      var meters = map.distance(measureFrom, event.latlng);
      var cap = Math.round(bearingWorld(measureFrom, event.latlng));
      toast('Distance : ' + formatMeters(meters) + ' · Cap ' + cap + '°');
      showCalcDrawer(draftPoints, meters, cap);
      measureFrom = null;
      clearDraft();
      return;
    }
    if (activeTool === 'measure' && !measureFrom) measureFrom = event.latlng;
    if ((activeTool === 'circle' || activeTool === 'rect' || activeTool === 'bearing' || activeTool === 'los' || activeTool === 'slice' || activeTool === 'measure3d' || activeTool === 'highlight' || activeTool === 'assembly' || activeTool === 'objective' || activeTool === 'arrow' || activeTool === 'axis' || activeTool === 'attack' || activeTool === 'phase' || activeTool === 'sector') && draftPoints.length >= 2) {
      finishDraft();
      return;
    }
    if ((activeTool === 'line' || activeTool === 'route' || activeTool === 'polygon' || activeTool === 'aoi' || activeTool === 'volume' || activeTool === 'split' || activeTool === 'eta' || activeTool === 'profile' || activeTool === 'arrow' || activeTool === 'axis' || activeTool === 'attack' || activeTool === 'phase' || activeTool === 'sector') && draftPoints.length >= (activeTool === 'aoi' || activeTool === 'polygon' || activeTool === 'volume' ? 3 : 2)) {
      /* keep collecting until double-click */
    }
  }

  function isDragTool(tool) {
    return /^(circle|rect|freehand|line|route|polygon|aoi|volume|measure|measure3d|bearing|los|eta|profile|slice|arrow|highlight|axis|attack|phase|sector|assembly|objective)$/.test(tool);
  }
  function liveMeasureHud() {
    var el = document.getElementById('ow-live-measure');
    if (!el || draftPoints.length < 2) { if (el) el.hidden = true; return; }
    var meters = pathLength(draftPoints);
    var a = draftPoints[0];
    var b = draftPoints[draftPoints.length - 1];
    var cap = Math.round(bearingWorld(a, b));
    var bits = [formatMeters(meters), cap + '°'];
    bits.push(gridLabel(a).replace('Grille ', 'A ') + ' → ' + gridLabel(b).replace('Grille ', 'B '));
    bits.push('pied ~' + Math.max(1, Math.round(meters / 1.4 / 60)) + ' min');
    bits.push('véh. ~' + Math.max(1, Math.round(meters / 11 / 60)) + ' min');
    if (activeTool === 'circle') bits.push('rayon ' + formatMeters(map.distance(a, b)));
    if (activeTool === 'polygon' || activeTool === 'aoi' || activeTool === 'rect') bits.push(Math.round(polygonAreaM2(draftPoints)) + ' m²');
    el.textContent = bits.join(' · ');
    el.hidden = false;
  }

  map.on('dblclick', function (event) {
    L.DomEvent.stop(event);
    if (activeTool === 'rally') { setTool('cursor'); return; }
    finishDraft();
  });
  map.on('click', function (event) {
    if (dragMoved) { dragMoved = false; return; }
    if (window.OverwatchTacmap && typeof window.OverwatchTacmap.consumeMapClick === 'function' && window.OverwatchTacmap.consumeMapClick(event.latlng)) return;
    if ((activeTool === 'cursor' || !activeTool) && tryInspectScene(event.latlng, event.originalEvent)) return;
    onMapClick(event);
  });
  function endDragDraw() {
    dragPending = false;
    if (!dragDrawOn) {
      try { map.dragging.enable(); } catch (e0) {}
      return;
    }
    dragDrawOn = false;
    freehandOn = false;
    try { map.dragging.enable(); } catch (e) {}
    var hud = document.getElementById('ow-live-measure');
    if (hud) hud.hidden = true;
    if (dragMoved) finishDraft();
  }
  map.on('mousedown', function (event) {
    if (event.originalEvent && event.originalEvent.button !== 0) return;
    if (!isDragTool(activeTool)) return;
    dragPending = true;
    dragDrawOn = false;
    dragMoved = false;
    dragStartLl = event.latlng;
    dragStartPt = map.latLngToContainerPoint(event.latlng);
    try { map.dragging.disable(); } catch (eDrag) {}
  });
  map.on('mousemove', function (event) {
    if (!dragPending && !dragDrawOn) return;
    var now = map.latLngToContainerPoint(event.latlng);
    if (!dragMoved && dragStartPt && now.distanceTo(dragStartPt) <= 8) return;
    if (!dragDrawOn) {
      dragDrawOn = true;
      dragMoved = true;
      freehandOn = activeTool === 'freehand' || activeTool === 'polygon' || activeTool === 'aoi' || activeTool === 'volume';
      draftPoints = [dragStartLl];
    }
    if (freehandOn) {
      draftPoints.push(event.latlng);
      if (draftPoints.length > 220) draftPoints = draftPoints.slice(-220);
    } else {
      draftPoints = [draftPoints[0], event.latlng];
    }
    updateDraft();
    liveMeasureHud();
  });
  map.on('mouseup', endDragDraw);
  L.DomEvent.on(document, 'mouseup', endDragDraw);

  function gridLabel(ll) {
    var w = latLngToWorld(ll);
    return 'Grille ' + String(Math.round(w.x)).padStart(4, '0') + ' ' + String(Math.round(w.y)).padStart(4, '0');
  }

  function hideContext() {
    var ctx = document.getElementById('ow-context');
    if (ctx) ctx.hidden = true;
    ctxTarget = null;
  }

  function bindLayerContext(layer, kind, id, label) {
    if (!layer) return layer;
    if (kind) {
      layer._owPin = { kind: kind, id: String(id || ''), label: label || '' };
    }
    var tipLabel = String(label || '').trim();
    if (window.ArmaMapMarkers && window.ArmaMapMarkers.isTechnicalLabel && window.ArmaMapMarkers.isTechnicalLabel(tipLabel)) {
      tipLabel = '';
    }
    if (tipLabel && layer.bindTooltip && !layer.getTooltip()) {
      layer.bindTooltip(tipLabel, { direction: 'top', sticky: true });
    }
    if (layer._owCtxBound) return layer;
    layer._owCtxBound = true;
    layer.on('contextmenu', function (event) {
      L.DomEvent.stop(event);
      var orig = event.originalEvent || event;
      openContextAt(event.latlng || (layer.getLatLng && layer.getLatLng()), orig, layer);
    });
    return layer;
  }

  function registerPing(id, ll, label, ttlSec) {
    var key = String(id || Date.now());
    var pin = L.circleMarker(ll, { radius: 7, color: '#e7b14d', weight: 2, fillOpacity: 0.45 });
    pin.addTo(map);
    bindLayerContext(pin, 'ping', key, label || 'Repère rapide');
    pingMarkers[key] = pin;
    if (ttlSec > 0) {
      window.setTimeout(function () { dropLocalId(pingMarkers, key); }, ttlSec * 1000);
    }
    return pin;
  }

  function pxDist(a, b) {
    if (!a || !b) return 9999;
    return map.latLngToContainerPoint(a).distanceTo(map.latLngToContainerPoint(b));
  }

  function flattenLatLngs(lls) {
    var out = [];
    (function walk(value) {
      if (!value) return;
      if (value.lat != null && value.lng != null) { out.push(value); return; }
      if (Array.isArray(value)) value.forEach(walk);
    })(lls);
    return out;
  }

  function nearestPxOnLatLngs(lls, ll) {
    var pts = flattenLatLngs(lls);
    var best = 9999;
    var p = map.latLngToContainerPoint(ll);
    function segDist(a, b) {
      var pa = map.latLngToContainerPoint(a);
      var pb = map.latLngToContainerPoint(b);
      var dx = pb.x - pa.x;
      var dy = pb.y - pa.y;
      var len2 = dx * dx + dy * dy;
      if (len2 < 1) return p.distanceTo(pa);
      var t = Math.max(0, Math.min(1, ((p.x - pa.x) * dx + (p.y - pa.y) * dy) / len2));
      return p.distanceTo(L.point(pa.x + t * dx, pa.y + t * dy));
    }
    for (var i = 0; i < pts.length; i++) {
      best = Math.min(best, pxDist(ll, pts[i]));
      if (i > 0) best = Math.min(best, segDist(pts[i - 1], pts[i]));
    }
    if (pts.length > 2) {
      var first = pts[0];
      var last = pts[pts.length - 1];
      if (first.lat !== last.lat || first.lng !== last.lng) best = Math.min(best, segDist(last, first));
    }
    return best;
  }

  function layerHitDist(layer, ll) {
    if (!layer || !ll) return 9999;
    if (layer.getLatLng) return pxDist(ll, layer.getLatLng());
    if (layer.getLatLngs) return nearestPxOnLatLngs(layer.getLatLngs(), ll);
    var best = 9999;
    if (layer.eachLayer) {
      layer.eachLayer(function (child) {
        best = Math.min(best, layerHitDist(child, ll));
      });
    }
    return best;
  }

  function registerScratch(layer, kind, id, label) {
    if (!layer) return layer;
    bindLayerContext(layer, kind, id, label);
    if (layer.eachLayer) {
      layer.eachLayer(function (child) { bindLayerContext(child, kind, id, label); });
    }
    if (kind === 'los') {
      losGroups.push(layer);
      losLayer = layer;
    }
    return layer;
  }

  function removeScratch(kind, id) {
    function drop(layer) {
      if (!layer) return;
      try { map.removeLayer(layer); } catch (e) {}
    }
    if (kind === 'los') {
      if (id === 'los-orphan') {
        map.eachLayer(function (layer) {
          var cls = String((layer.options && layer.options.className) || '');
          if (cls.indexOf('ow-los') < 0) return;
          if (layer._owPin && layer._owPin.id && layer._owPin.id !== 'los-orphan') return;
          drop(layer);
        });
        return;
      }
      if (!id || id === 'los') {
        losGroups.forEach(drop);
        losGroups = [];
        losLayer = null;
        map.eachLayer(function (layer) {
          var cls = String((layer.options && layer.options.className) || '');
          if (cls.indexOf('ow-los') >= 0) drop(layer);
        });
        return;
      }
      losGroups = losGroups.filter(function (layer) {
        if (layer._owPin && String(layer._owPin.id) === String(id)) {
          drop(layer);
          return false;
        }
        return true;
      });
      losLayer = losGroups[losGroups.length - 1] || null;
      return;
    }
    if (kind === 'range' && window.OverwatchTools && window.OverwatchTools.clearRange) {
      window.OverwatchTools.clearRange();
      return;
    }
    if (kind === 'intercept' && window.__owInterceptLine) {
      drop(window.__owInterceptLine);
      window.__owInterceptLine = null;
    }
  }

  function latlngsContains(lls, ll) {
    var pts = flattenLatLngs(lls);
    var x = ll.lng;
    var y = ll.lat;
    var inside = false;
    for (var i = 0, j = pts.length - 1; i < pts.length; j = i++) {
      var xi = pts[i].lng;
      var yi = pts[i].lat;
      var xj = pts[j].lng;
      var yj = pts[j].lat;
      var intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / ((yj - yi) || 1e-12) + xi);
      if (intersect) inside = !inside;
    }
    return inside;
  }

  function targetFromLayer(layer) {
    if (!layer) return null;
    if (layer._owPin && layer._owPin.kind) {
      return { kind: layer._owPin.kind, id: layer._owPin.id, rank: 0, dist: 0, label: layer._owPin.label || 'Élément' };
    }
    var id;
    for (id in pingMarkers) {
      if (pingMarkers[id] === layer) return { kind: 'ping', id: id, rank: 0, dist: 0, label: 'Repère rapide' };
    }
    for (id in postedMarkers) {
      if (postedMarkers[id] === layer) {
        var poHit = poRows.filter(function (row) { return String(row.id) === String(id); })[0];
        if (poHit) return { kind: 'po', id: id, rank: 0, dist: 0, label: poHit.label };
        return { kind: 'marker', id: id, rank: 0, dist: 0, label: 'Marqueur' };
      }
    }
    for (id in armaMarkerLayers) {
      if (armaMarkerLayers[id] === layer) return { kind: 'arma', id: id, rank: 0, dist: 0, label: 'Repère' };
    }
    for (id in shapeLayers) {
      if (shapeLayers[id] === layer) {
        var shape = shapes.filter(function (row) { return String(row.id) === String(id); })[0];
        return { kind: 'shape', id: id, rank: 1, dist: 0, label: (shape && (shape.label || shape.type)) || 'Tracé' };
      }
    }
    if (losLayer === layer || losGroups.indexOf(layer) >= 0) {
      return { kind: 'los', id: (layer._owPin && layer._owPin.id) || 'los', rank: 1, dist: 0, label: (layer._owPin && layer._owPin.label) || 'Visée' };
    }
    if (window.OverwatchTools && typeof window.OverwatchTools.getRangeLayer === 'function' && window.OverwatchTools.getRangeLayer() === layer) {
      return { kind: 'range', id: 'range', rank: 1, dist: 0, label: 'Anneaux de portée' };
    }
    if (window.__owInterceptLine === layer) {
      return { kind: 'intercept', id: 'intercept', rank: 1, dist: 0, label: 'Interception' };
    }
    var loc;
    var i;
    for (i = 0; i < poRows.length; i++) {
      loc = worldToLatLng(poRows[i].x, poRows[i].y);
      if (layer.getLatLng && pxDist(layer.getLatLng(), loc) <= 8) {
        return { kind: 'po', id: poRows[i].id, rank: 0, dist: 0, label: poRows[i].label };
      }
    }
    for (i = 0; i < rallyRows.length; i++) {
      loc = worldToLatLng(rallyRows[i].x, rallyRows[i].y);
      if (layer.getLatLng && pxDist(layer.getLatLng(), loc) <= 8) {
        return { kind: 'rally', id: rallyRows[i].id, rank: 0, dist: 0, label: rallyRows[i].label };
      }
    }
    return null;
  }

  function hitDeletable(ll, preferLayer) {
    if (!ll) return null;
    var hits = [];
    var fromLayer = targetFromLayer(preferLayer);
    if (fromLayer) hits.push(fromLayer);

    function pushHit(hit) {
      if (!hit || hit.id == null || hit.id === '') return;
      var exists = hits.some(function (row) { return row.kind === hit.kind && String(row.id) === String(hit.id); });
      if (!exists) hits.push(hit);
    }

    var HIT_POINT = 18;
    var HIT = 40;
    Object.keys(pingMarkers).forEach(function (id) {
      var layer = pingMarkers[id];
      if (!layer || !layer.getLatLng) return;
      var d = pxDist(ll, layer.getLatLng());
      if (d <= HIT_POINT) pushHit({ kind: 'ping', id: id, rank: 0, dist: d, label: (layer._owPin && layer._owPin.label) || 'Repère rapide' });
    });
    poRows.forEach(function (row) {
      var loc = worldToLatLng(row.x, row.y);
      var d = pxDist(ll, loc);
      var inside = map.distance(ll, loc) <= row.radius;
      if (inside || d <= HIT_POINT) pushHit({ kind: 'po', id: row.id, rank: 0, dist: d, label: row.label });
    });
    rallyRows.forEach(function (row) {
      var loc = worldToLatLng(row.x, row.y);
      var d = pxDist(ll, loc);
      var inside = map.distance(ll, loc) <= row.radius;
      if (inside || d <= HIT_POINT) pushHit({ kind: 'rally', id: row.id, rank: 0, dist: d, label: row.label });
    });
    Object.keys(postedMarkers).forEach(function (id) {
      var layer = postedMarkers[id];
      if (!layer || !layer.getLatLng) return;
      var d = pxDist(ll, layer.getLatLng());
      if (d > HIT_POINT) return;
      if (poRows.some(function (row) { return String(row.id) === String(id); })) return;
      pushHit({ kind: 'marker', id: id, rank: 0, dist: d, label: 'Marqueur' });
    });
    Object.keys(armaMarkerLayers).forEach(function (id) {
      var layer = armaMarkerLayers[id];
      if (!layer) return;
      var d = 9999;
      var rank = 0;
      if (layer.getLatLng) d = pxDist(ll, layer.getLatLng());
      else if (layer.getLatLngs) {
        var lls = layer.getLatLngs();
        if ((layer instanceof L.Polygon) && latlngsContains(lls, ll)) { d = 0; rank = 2; }
        else d = nearestPxOnLatLngs(lls, ll);
        if (!(layer instanceof L.Polygon)) rank = 1;
      }
      if ((rank === 0 && d <= HIT_POINT) || (rank !== 0 && (d <= HIT || rank === 2))) {
        if (poRows.some(function (row) { return String(row.id) === String(id); })) return;
        pushHit({ kind: 'arma', id: id, rank: rank, dist: d, label: (layer._owPin && layer._owPin.label) || 'Repère' });
      }
    });
    Object.keys(shapeLayers).forEach(function (id) {
      var layer = shapeLayers[id];
      if (!layer) return;
      var d = 9999;
      var rank = 1;
      if (layer.getLatLng) { d = pxDist(ll, layer.getLatLng()); rank = 0; }
      else if (layer.getLatLngs) {
        var lls = layer.getLatLngs();
        if ((layer instanceof L.Polygon) && latlngsContains(lls, ll)) { d = 0; rank = 2; }
        else {
          d = nearestPxOnLatLngs(lls, ll);
          rank = 1;
        }
      }
      if (d <= HIT || rank === 2) {
        var shape = shapes.filter(function (row) { return String(row.id) === String(id); })[0];
        pushHit({ kind: 'shape', id: id, rank: rank, dist: d, label: (shape && (shape.label || shape.type)) || 'Tracé' });
      }
    });
    losGroups.forEach(function (group) {
      var dLos = layerHitDist(group, ll);
      if (dLos <= HIT) {
        var losPin = group._owPin || {};
        pushHit({ kind: 'los', id: losPin.id || 'los', rank: 1, dist: dLos, label: losPin.label || 'Visée' });
      }
    });
    if (window.OverwatchTools && typeof window.OverwatchTools.getRangeLayer === 'function') {
      var rangeLayer = window.OverwatchTools.getRangeLayer();
      var dRange = layerHitDist(rangeLayer, ll);
      if (dRange <= HIT) pushHit({ kind: 'range', id: 'range', rank: 1, dist: dRange, label: 'Anneaux de portée' });
    }
    if (window.__owInterceptLine) {
      var dInt = layerHitDist(window.__owInterceptLine, ll);
      if (dInt <= HIT) pushHit({ kind: 'intercept', id: 'intercept', rank: 1, dist: dInt, label: 'Interception' });
    }
    if (draftLayer) {
      var draftDist = layerHitDist(draftLayer, ll);
      if (draftDist <= HIT) pushHit({ kind: 'draft', id: 'draft', rank: 0, dist: draftDist, label: 'Croquis en cours' });
    }
    map.eachLayer(function (layer) {
      if (!layer) return;
      var pin = layer._owPin;
      if (pin && pin.kind) {
        var dPin = layerHitDist(layer, ll);
        if (dPin <= HIT) {
          pushHit({
            kind: pin.kind,
            id: pin.id,
            rank: (pin.kind === 'los' || pin.kind === 'range') ? 1 : 0,
            dist: dPin,
            label: pin.label || 'Élément'
          });
        }
        return;
      }
      var cls = String((layer.options && layer.options.className) || '');
      if (cls.indexOf('ow-los') >= 0) {
        var dOrphan = layerHitDist(layer, ll);
        if (dOrphan <= HIT) pushHit({ kind: 'los', id: 'los-orphan', rank: 1, dist: dOrphan, label: 'Visée' });
      }
    });

    hits.sort(function (a, b) {
      if (Math.abs((a.dist || 0) - (b.dist || 0)) > 12) return a.dist - b.dist;
      return a.rank - b.rank || a.dist - b.dist;
    });
    var best = hits[0] || null;
    if (fromLayer && fromLayer.kind) {
      var overlay = fromLayer.kind === 'los' || fromLayer.kind === 'range' || fromLayer.kind === 'intercept' || fromLayer.kind === 'shape';
      if (overlay) {
        if (!best || (best.kind === fromLayer.kind && String(best.id) === String(fromLayer.id))) return fromLayer;
        if (best.rank === 0 && best.dist <= 14) return best;
        return fromLayer;
      }
    }
    return best;
  }

  function pickUnitAt(ll) {
    if (!ll) return null;
    var best = null;
    var bestD = 22;
    units.forEach(function (unit) {
      var loc = point(unit);
      if (!loc) return;
      var d = pxDist(ll, loc);
      if (d <= bestD) {
        bestD = d;
        best = unit;
      }
    });
    return best;
  }

  function asMapLatLng(ll) {
    if (!ll) return null;
    if (typeof ll.distanceTo === 'function') return ll;
    if (ll.lat == null || ll.lng == null) return null;
    return L.latLng(ll.lat, ll.lng);
  }

  function sceneToggleOn() {
    var box = document.getElementById('atak-scene-buildings');
    return !box || box.checked;
  }
  function mapViewMode() {
    var sel = document.getElementById('atak-terrain-3d-mode');
    var v = sel ? String(sel.value || 'flat') : 'flat';
    if (v === 'immersive' || v === 'volume' || v === 'tactical') return v;
    return 'flat';
  }
  function leafletSceneWanted() {
    if (!sceneToggleOn()) return false;
    if (mapViewMode() !== 'immersive') return false;
    var gl = window.OverwatchGlMap;
    if (gl && typeof gl.isActive === 'function' && gl.isActive()) {
      var stage = document.querySelector('.ow-map-stage');
      if (!stage || !stage.classList.contains('is-split')) return false;
    }
    return true;
  }
  function ensureScenePane() {
    if (!map.getPane('owScenePane')) {
      map.createPane('owScenePane');
      var pane = map.getPane('owScenePane');
      pane.style.zIndex = '350';
      pane.style.pointerEvents = 'none';
      pane.classList.add('leaflet-zoom-animated');
    }
    return map.getPane('owScenePane');
  }
  function placeSceneCanvas() {
    if (!sceneCanvas) return;
    var pane = ensureScenePane();
    if (sceneCanvas.parentNode !== pane) pane.appendChild(sceneCanvas);
    sceneCanvas.style.position = 'absolute';
    sceneCanvas.style.pointerEvents = 'none';
    var size = map.getSize();
    var ratio = Math.min(2, window.devicePixelRatio || 1);
    var padX = Math.round(size.x * 0.35);
    var padY = Math.round(size.y * 0.35);
    var cssW = size.x + padX * 2;
    var cssH = size.y + padY * 2;
    var w = Math.round(cssW * ratio);
    var h = Math.round(cssH * ratio);
    if (sceneCanvas.width !== w || sceneCanvas.height !== h) {
      sceneCanvas.width = w;
      sceneCanvas.height = h;
      sceneCanvas.style.width = cssW + 'px';
      sceneCanvas.style.height = cssH + 'px';
    }
    var topLeft = map.containerPointToLayerPoint([-padX, -padY]);
    if (L.DomUtil && typeof L.DomUtil.setPosition === 'function') L.DomUtil.setPosition(sceneCanvas, topLeft);
    else {
      sceneCanvas.style.left = topLeft.x + 'px';
      sceneCanvas.style.top = topLeft.y + 'px';
    }
    if (sceneCtx) sceneCtx.setTransform(ratio, 0, 0, ratio, padX * ratio, padY * ratio);
  }
  function sceneCorners(item) {
    var angle = Number(item.bearing || 0) * Math.PI / 180;
    var c = Math.cos(angle);
    var s = Math.sin(angle);
    var hw = Math.max(2, Number(item.width || 4) / 2);
    var hd = Math.max(2, Number(item.depth || 4) / 2);
    var x = Number(item.x);
    var y = Number(item.y);
    return [[-hw, -hd], [hw, -hd], [hw, hd], [-hw, hd]].map(function (v) {
      return map.latLngToContainerPoint(worldToLatLng(x + v[0] * c - v[1] * s, y + v[0] * s + v[1] * c));
    });
  }
  function isForestKind(kind) {
    var k = String(kind || '').toLowerCase();
    return k === 'forest' || k === 'forests' || k === 'tree' || k === 'trees' || k === 'wood' || k === 'woods';
  }
  function drawForestBlob(x, y, radiusM) {
    if (!sceneCtx) return;
    var c = map.latLngToContainerPoint(worldToLatLng(x, y));
    var edge = map.latLngToContainerPoint(worldToLatLng(Number(x) + Math.max(4, radiusM), y));
    var rad = Math.max(4, Math.hypot(edge.x - c.x, edge.y - c.y));
    var grd = sceneCtx.createRadialGradient(c.x, c.y, 0, c.x, c.y, rad);
    grd.addColorStop(0, 'rgba(22, 64, 30, 0.34)');
    grd.addColorStop(0.52, 'rgba(24, 70, 32, 0.18)');
    grd.addColorStop(1, 'rgba(24, 70, 32, 0)');
    sceneCtx.beginPath();
    sceneCtx.arc(c.x, c.y, rad, 0, Math.PI * 2);
    sceneCtx.fillStyle = grd;
    sceneCtx.fill();
  }
  function drawForests(rows, zoom, view) {
    if (zoom < 3.3) return;
    var members = [];
    rows.forEach(function (item) {
      if (!isForestKind(item.kind)) return;
      var ll = worldToLatLng(item.x, item.y);
      if (!view.contains(ll)) return;
      members.push(item);
    });
    if (!members.length) return;
    var cell = zoom >= 6.6 ? 16 : (zoom >= 5.2 ? 28 : 40);
    var bins = {};
    members.forEach(function (item) {
      var gx = Math.floor(Number(item.x) / cell);
      var gy = Math.floor(Number(item.y) / cell);
      var k = gx + ':' + gy;
      if (!bins[k]) bins[k] = [];
      bins[k].push(item);
    });
    Object.keys(bins).forEach(function (k) {
      var list = bins[k];
      var sx = 0;
      var sy = 0;
      var i;
      for (i = 0; i < list.length; i += 1) {
        sx += Number(list[i].x);
        sy += Number(list[i].y);
      }
      var cx = sx / list.length;
      var cy = sy / list.length;
      var reach = 0;
      var farthest = list[0];
      var farD = 0;
      for (i = 0; i < list.length; i += 1) {
        var half = Math.max(Number(list[i].width) || 8, Number(list[i].depth) || 8) / 2;
        var dist = Math.hypot(Number(list[i].x) - cx, Number(list[i].y) - cy);
        if (dist + half > reach) reach = dist + half;
        if (dist > farD) {
          farD = dist;
          farthest = list[i];
        }
      }
      var radius = Math.max(8, reach * 1.08 + cell * 0.16);
      drawForestBlob(cx, cy, radius);
      if (list.length >= 3 && farD > 6) {
        drawForestBlob(
          (Number(farthest.x) + cx) / 2,
          (Number(farthest.y) + cy) / 2,
          radius * 0.7
        );
      }
    });
  }
  function setSceneLoad(on, text) {
    var el = document.getElementById('ow-scene-load');
    if (!el) return;
    el.hidden = !on;
    if (on) el.textContent = text || 'Chargement du relevé…';
  }
  var sceneLoadTimer = 0;
  function sceneLoadHint(on) {
    window.clearTimeout(sceneLoadTimer);
    if (!on) { setSceneLoad(false); return; }
    sceneLoadTimer = window.setTimeout(function () { setSceneLoad(true); }, 280);
  }
  function drawScenePoly(points, fill, stroke) {
    if (!sceneCtx || !points.length) return;
    sceneCtx.beginPath();
    sceneCtx.moveTo(points[0].x, points[0].y);
    points.slice(1).forEach(function (p) { sceneCtx.lineTo(p.x, p.y); });
    sceneCtx.closePath();
    sceneCtx.fillStyle = fill;
    sceneCtx.fill();
    if (stroke) {
      sceneCtx.strokeStyle = stroke;
      sceneCtx.lineWidth = 1.2;
      sceneCtx.stroke();
    }
  }
  function drawSceneFootprints() {
    sceneDrawFrame = 0;
    if (!sceneCanvas || !sceneCtx) return;
    if (sceneZooming) return;
    placeSceneCanvas();
    var size = map.getSize();
    var padX = Math.round(size.x * 0.35);
    var padY = Math.round(size.y * 0.35);
    sceneCtx.clearRect(-padX - 2, -padY - 2, size.x + padX * 2 + 4, size.y + padY * 2 + 4);
    if (!leafletSceneWanted()) return;
    var zoom = map.getZoom();
    var view = map.getBounds().pad(0.4);
    var minSpan = zoom < 2 ? 48 : (zoom < 3 ? 22 : (zoom < 4 ? 10 : 0));
    var rows = sceneCache.length ? sceneCache : sceneRows;
    drawForests(rows, zoom, view);
    rows.forEach(function (item) {
      if (isForestKind(item.kind)) return;
      if (minSpan && Math.max(Number(item.width) || 0, Number(item.depth) || 0) < minSpan) return;
      var ll = worldToLatLng(item.x, item.y);
      if (!view.contains(ll)) return;
      var pts = sceneCorners(item);
      if (pts.length < 4) return;
      var kind = String(item.kind || 'building');
      if (kind === 'building') drawScenePoly(pts, 'rgba(196,206,214,.52)', 'rgba(232,238,244,.95)');
      else drawScenePoly(pts, 'rgba(120,110,96,.38)', 'rgba(180,168,148,.8)');
      if (zoom >= 4 && hoverSceneId && String(hoverSceneId) === String(item.id)
        && (!lastSceneObject || String(lastSceneObject.id) !== String(item.id))) {
        drawScenePoly(pts, 'rgba(226,232,240,.2)', 'rgba(255,255,255,.98)');
      }
      if (zoom >= 5 && lastSceneObject && String(lastSceneObject.id) === String(item.id)) {
        drawScenePoly(pts, 'rgba(0,214,154,.22)', 'rgba(0,214,154,.95)');
      }
    });
  }
  function scheduleSceneDraw() {
    if (sceneZooming) return;
    if (!sceneDrawFrame) sceneDrawFrame = requestAnimationFrame(drawSceneFootprints);
  }
  function sceneWorldBbox() {
    return [0, 0, worldSize, worldSize].join(',');
  }
  function preloadSceneAll() {
    if (scenePreloadPromise && sceneCacheMapId === mapId) return scenePreloadPromise;
    sceneCacheMapId = mapId;
    sceneLoadHint(true);
    scenePreloadPromise = api('/api/atak/scene?mapId=' + encodeURIComponent(mapId) + '&bbox=' + encodeURIComponent(sceneWorldBbox()) + '&limit=40000').then(function (payload) {
      sceneCache = asList(payload, 'objects');
      sceneRows = sceneCache;
      scheduleSceneDraw();
      renderArmaMarkers();
      sceneLoadHint(false);
      return sceneCache;
    }).catch(function () {
      scenePreloadPromise = null;
      sceneLoadHint(false);
      return sceneCache;
    });
    return scenePreloadPromise;
  }
  function loadSceneFootprints() {
    if (!leafletSceneWanted()) {
      sceneRows = [];
      scheduleSceneDraw();
      renderArmaMarkers();
      return;
    }
    if (sceneCache.length && sceneCacheMapId === mapId) {
      sceneRows = sceneCache;
      scheduleSceneDraw();
      renderArmaMarkers();
      return;
    }
    preloadSceneAll();
  }
  function pointInSceneItem(wx, wy, item) {
    var angle = -(Number(item.bearing || 0) * Math.PI / 180);
    var c = Math.cos(angle);
    var s = Math.sin(angle);
    var dx = wx - Number(item.x);
    var dy = wy - Number(item.y);
    var lx = dx * c - dy * s;
    var ly = dx * s + dy * c;
    var hw = Math.max(2, Number(item.width || 4) / 2);
    var hd = Math.max(2, Number(item.depth || 4) / 2);
    return Math.abs(lx) <= hw && Math.abs(ly) <= hd;
  }
  function hitSceneAt(ll, force) {
    if (!force && !leafletSceneWanted()) return null;
    if (!ll) return null;
    var w = latLngToWorld(ll);
    var best = null;
    var bestArea = Infinity;
    var rows = sceneCache.length ? sceneCache : sceneRows;
    rows.forEach(function (item) {
      if (!pointInSceneItem(w.x, w.y, item)) return;
      var area = Math.max(4, Number(item.width || 4)) * Math.max(4, Number(item.depth || 4));
      if (area < bestArea) {
        bestArea = area;
        best = item;
      }
    });
    return best;
  }
  function tryInspectScene(ll, originalEvent) {
    if (activeTool && activeTool !== 'cursor') return false;
    var unit = pickUnitAt(ll);
    if (unit && !(originalEvent && (originalEvent.button === 2 || originalEvent.ctrlKey))) return false;
    var hit = hitSceneAt(ll);
    if (!hit || !hit.id) return false;
    inspectSceneObject(hit.id, ll, hit);
    return true;
  }
  function initSceneFootprints() {
    if (sceneCanvas) return;
    sceneCanvas = document.createElement('canvas');
    sceneCanvas.className = 'ow-scene-footprints';
    sceneCanvas.setAttribute('aria-hidden', 'true');
    sceneCtx = sceneCanvas.getContext('2d');
    placeSceneCanvas();
    map.on('zoomstart', function () { sceneZooming = true; });
    map.on('zoomend', function () {
      sceneZooming = false;
      scheduleSceneDraw();
    });
    map.on('moveend', scheduleSceneDraw);
    map.on('resize', scheduleSceneDraw);
    map.on('mousemove', function (event) {
      if (!leafletSceneWanted() || (activeTool && activeTool !== 'cursor')) {
        if (hoverSceneId) { hoverSceneId = null; scheduleSceneDraw(); }
        return;
      }
      var hit = hitSceneAt(event.latlng);
      var next = hit && hit.id ? String(hit.id) : null;
      if (next === hoverSceneId) return;
      hoverSceneId = next;
      scheduleSceneDraw();
    });
    var toggle = document.getElementById('atak-scene-buildings');
    if (toggle) toggle.addEventListener('change', function () { loadSceneFootprints(); });
    var mode = document.getElementById('atak-terrain-3d-mode');
    if (mode) mode.addEventListener('change', function () { loadSceneFootprints(); });
    window.addEventListener('atak:terrain3dchange', loadSceneFootprints);
    window.addEventListener('atak:overwatch-gl-change', loadSceneFootprints);
    window.addEventListener('overwatch:scene-load', function (event) {
      var d = event && event.detail ? event.detail : {};
      setSceneLoad(!!d.on, d.text);
    });
    preloadSceneAll();
    loadSceneFootprints();
  }

  function inspectSceneObject(id, ll, hint) {
    id = String(id || '');
    if (!id || id.indexOf('cluster') >= 0) {
      if (ll) inspectWorld(ll);
      return;
    }
    var fallbackLl = ll;
    api('/api/atak/scene/object?mapId=' + encodeURIComponent(mapId) + '&id=' + encodeURIComponent(id)).then(function (payload) {
      var obj = payload && payload.object ? payload.object : null;
      if (!obj) {
        if (fallbackLl) inspectWorld(fallbackLl);
        return;
      }
      var x = Number(obj.x);
      var y = Number(obj.y);
      var loc = (isFinite(x) && isFinite(y)) ? worldToLatLng(x, y) : fallbackLl;
      lastSceneObject = { id: id, ll: loc, object: obj };
      if (window.OverwatchGlLayers && typeof window.OverwatchGlLayers.setFocus === 'function') {
        window.OverwatchGlLayers.setFocus(id);
      }
      openDrawer('Construction', obj.name || obj.kind_label || 'Volume', sceneObjectHtml(obj, loc));
      bindDrawerForms();
      scheduleSceneDraw();
    }).catch(function () {
      if (fallbackLl) inspectWorld(fallbackLl);
    });
  }

  function sceneQualityLabel(q) {
    if (q === 'complete') return 'Données complètes';
    if (q === 'approx') return 'Dimensions approximées';
    if (q === 'position') return 'Position seulement';
    if (q === 'suspect') return 'Géométrie suspecte';
    return '';
  }

  function sceneObjectHtml(obj, loc) {
    var grid = loc ? gridLabel(loc).replace(/^GRID\s+/i, '') : (Math.round(obj.x) + ' ' + Math.round(obj.y));
    var bearing = Math.round(((Number(obj.bearing) % 360) + 360) % 360);
    var floors = Number(obj.floors) || 1;
    var height = Number(obj.height);
    var alt = obj.z != null && isFinite(Number(obj.z)) ? Number(obj.z) : null;
    var doors = Number(obj.doors) || 0;
    var q = String(obj.quality || '');
    var qLabel = sceneQualityLabel(q);
    var photos = Array.isArray(obj.photos) ? obj.photos : [];
    var html = '<div class="ow-event"><span>Grille</span><strong>' + escapeHtml(grid) + '</strong></div>' +
      '<div class="ow-event"><span>Orientation</span><strong>' + bearing + '°</strong></div>' +
      '<div class="ow-event"><span>Niveaux</span><strong>' + floors + '</strong></div>';
    if (isFinite(height)) html += '<div class="ow-event"><span>Hauteur</span><strong>' + height.toFixed(1).replace('.', ',') + ' m</strong></div>';
    if (alt != null) html += '<div class="ow-event"><span>Altitude</span><strong>' + Math.round(alt) + ' m</strong></div>';
    html += '<div class="ow-event"><span>Emprise</span><strong>' + Math.round(Number(obj.width) || 0) + ' × ' + Math.round(Number(obj.depth) || 0) + ' m</strong></div>';
    if (doors > 0) html += '<div class="ow-event"><span>Entrées détectées</span><strong>' + doors + '</strong></div>';
    if (qLabel) {
      html += '<div class="ow-event"><span>Qualité</span><strong><i class="ow-quality-dot is-' + escapeHtml(q) + '"></i>' + escapeHtml(qLabel) + '</strong></div>';
    }
    html += '<div class="ow-scene-actions">' +
      '<button type="button" class="ow-primary" data-scene-act="marker">Marquer</button>' +
      '<button type="button" class="ow-secondary" data-scene-act="po">Définir comme objectif</button>' +
      '<button type="button" class="ow-secondary" data-scene-act="entry">Ajouter une entrée</button>' +
      '<button type="button" class="ow-secondary" data-scene-act="photo">Attacher une photo</button>' +
      '<button type="button" class="ow-secondary" data-scene-act="task">Créer une TASK</button>' +
      '<button type="button" class="ow-secondary" data-scene-act="plan">Ouvrir le plan d’étage</button>' +
      '</div>';
    if (photos.length) {
      html += '<p class="ow-kicker">Photos à proximité</p>' + photos.map(function (p) {
        return '<div class="ow-event"><span>' + escapeHtml(p.author || 'Photo') + '</span><strong>' + escapeHtml(String(p.created_at || '').slice(11, 19)) + '</strong></div>';
      }).join('');
    }
    var labels = Array.isArray(obj.floor_labels) ? obj.floor_labels : [];
    if (labels.length) {
      html += '<p class="ow-kicker">Étage</p><div class="ow-floor-row">';
      labels.forEach(function (fl) {
        var on = Number(obj.floor_focus) === Number(fl.value);
        html += '<button type="button" class="ow-secondary' + (on ? ' is-active' : '') + '" data-scene-act="floor" data-floor="' + Number(fl.value) + '">' + escapeHtml(fl.label) + '</button>';
      });
        html += '</div>';
    }
    if (obj.roof && obj.roof.area_m2) {
      html += '<div class="ow-event"><span>Toit</span><strong>' + Math.round(obj.roof.area_m2) + ' m² · ' + (Number(obj.roof.height) || 0).toFixed(1).replace('.', ',') + ' m</strong></div>';
    }
    var anchors = Array.isArray(obj.anchors) ? obj.anchors : [];
    if (anchors.length) {
      html += '<p class="ow-kicker">Ancrages</p>' + anchors.slice(-8).map(function (a) {
        return '<div class="ow-event"><span>' + escapeHtml(a.label || a.kind || 'Note') + '</span><strong>' + escapeHtml((a.face ? 'Façade ' + a.face : '') + (a.floor != null ? ' niv. ' + a.floor : '')) + '</strong></div>';
      }).join('');
    }
    html += '<button type="button" class="ow-secondary" data-scene-act="anchor">Ancrer une note</button>';
    var insp = document.getElementById('atak-scene-inspector');
    if (insp && insp.checked) {
      html += '<p class="ow-kicker">Inspection</p>' +
        '<div class="ow-event"><span>Identifiant</span><strong>' + escapeHtml(String(obj.id || '')) + '</strong></div>' +
        '<div class="ow-event"><span>Modèle</span><strong>' + escapeHtml(String(obj.model || '—')) + '</strong></div>' +
        '<div class="ow-event"><span>XYZ</span><strong>' + Math.round(obj.x) + ' / ' + Math.round(obj.y) + ' / ' + (obj.z != null ? Math.round(obj.z) : '—') + '</strong></div>' +
        '<div class="ow-event"><span>Direction</span><strong>' + bearing + '°</strong></div>' +
        '<div class="ow-event"><span>Boîte</span><strong>' + Math.round(Number(obj.width) || 0) + ' × ' + Math.round(Number(obj.depth) || 0) + ' × ' + (isFinite(height) ? height.toFixed(1) : '—') + '</strong></div>' +
        '<div class="ow-event"><span>Bloc</span><strong>' + escapeHtml(String(obj.chunk || '—')) + '</strong></div>' +
        '<div class="ow-event"><span>Source</span><strong>' + escapeHtml(String(obj.source || 'relevé jeu')) + '</strong></div>' +
        '<div class="ow-event"><span>Confiance</span><strong>' + escapeHtml(sceneQualityLabel(obj.confidence || obj.quality) || String(obj.confidence || '—')) + '</strong></div>';
    }
    return html;
  }

  function inspectWorld(ll, originalEvent, layer) {
    ll = asMapLatLng(ll);
    if (!ll) return false;
    var right = !!(originalEvent && (originalEvent.button === 2 || originalEvent.which === 3 || originalEvent.ctrlKey));
    var unit = pickUnitAt(ll);
    if (unit && !right) {
      selectUnit(unit);
      hideContext();
      return true;
    }
    openContextAt(ll, originalEvent, layer);
    return true;
  }

  function handleWorldClick(ll, originalEvent, layer) {
    ll = asMapLatLng(ll);
    if (!ll) return false;
    if (window.OverwatchTacmap && typeof window.OverwatchTacmap.consumeMapClick === 'function' && window.OverwatchTacmap.consumeMapClick(ll)) return true;
    var fake = { latlng: ll, originalEvent: originalEvent || null };
    if (activeTool && activeTool !== 'cursor' && activeTool !== 'freehand' && activeTool !== 'goto' && activeTool !== 'range') {
      onMapClick(fake);
      return true;
    }
    if (tryInspectScene(ll, originalEvent)) return true;
    return inspectWorld(ll, originalEvent, layer);
  }

  function handleWorldContext(ll, originalEvent, layer) {
    ll = asMapLatLng(ll);
    if (!ll) return false;
    openContextAt(ll, originalEvent, layer);
    return true;
  }

  function dropLocalId(store, id) {
    var key = String(id);
    if (store[key]) {
      try { map.removeLayer(store[key]); } catch (e) {}
      delete store[key];
    }
  }

  function dropMarkerLocal(id) {
    dropLocalId(postedMarkers, id);
    dropLocalId(armaMarkerLayers, id);
    armaMarkerRows = armaMarkerRows.filter(function (row) { return String(row.id) !== String(id); });
    if (window.OverwatchOps && window.OverwatchOps.setArmaRows) window.OverwatchOps.setArmaRows(armaMarkerRows);
    poRows = poRows.filter(function (row) { return String(row.id) !== String(id); });
    renderPoMarkers();
    renderArmaMarkers();
  }

  function deleteMapTarget(target) {
    if (!target) return;
    var id = String(target.id || '');
    var kind = target.kind;
    if (kind === 'los') {
      removeScratch('los', id);
      toast('Visée retirée.');
      return;
    }
    if (kind === 'range') {
      removeScratch('range', id);
      toast('Anneaux retirés.');
      return;
    }
    if (kind === 'intercept') {
      removeScratch('intercept', id);
      toast('Relevé d’interception retiré.');
      return;
    }
    if (kind === 'draft') {
      clearDraft();
      toast('Croquis annulé.');
      return;
    }
    if (kind === 'relay') {
      api('/api/atak/relays/' + encodeURIComponent(id) + '?mapId=' + encodeURIComponent(mapId), { method: 'DELETE' }).then(function () {
        if (window.OverwatchOps && window.OverwatchOps.loadRelays) window.OverwatchOps.loadRelays();
        toast('Relais retiré du poste. S’il existe encore en jeu, il peut réapparaître.');
      }).catch(function () { toast('Impossible de retirer ce relais.'); });
      return;
    }
    if (kind === 'sitrep') {
      var mid = (window.OverwatchOps && typeof window.OverwatchOps.missionId === 'function')
        ? window.OverwatchOps.missionId()
        : ('mission_' + Number(window.ATAK_TENANT_ID || 0) + '_map_' + Number(mapId || 1));
      api('/api/intel/report/' + encodeURIComponent(id) + '?missionId=' + encodeURIComponent(mid) + '&mapId=' + encodeURIComponent(mapId), {
        method: 'DELETE',
        body: { missionId: mid, mapId: mapId }
      }).then(function () {
        if (window.OverwatchOps && window.OverwatchOps.dropSitrepPin) window.OverwatchOps.dropSitrepPin(id);
        var twin = shapes.filter(function (row) {
          var meta = parseShapeMeta(row);
          return meta && String(meta.report_id || '') === id;
        })[0];
        if (twin && twin.id) {
          return api('/api/map-shapes/' + encodeURIComponent(twin.id), { method: 'DELETE' }).then(function () {
            dropLocalId(shapeLayers, twin.id);
            shapes = shapes.filter(function (row) { return String(row.id) !== String(twin.id); });
          });
        }
      }).then(function () {
        toast('Compte rendu retiré.');
        updateStatsBanner();
      }).catch(function () { toast('Impossible de retirer ce compte rendu.'); });
      return;
    }
    if (kind === 'shape') {
      api('/api/map-shapes/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
        dropLocalId(shapeLayers, id);
        shapes = shapes.filter(function (row) { return String(row.id) !== id; });
        if (String(lastShapeId) === id) lastShapeId = 0;
        toast('Tracé retiré.');
        updateStatsBanner();
      }).catch(function () { toast('Impossible de retirer ce tracé.'); });
      return;
    }
    if (kind === 'po' || kind === 'marker' || kind === 'arma') {
      var goneLabel = kind === 'po' ? 'Point à atteindre retiré.' : 'Repère retiré.';
      api('/api/markers/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
        dropMarkerLocal(id);
        toast(goneLabel);
        updateStatsBanner();
      }).catch(function (err) {
        if (String(err && err.message) === '404') {
          dropMarkerLocal(id);
          toast('Ce repère n’est plus au poste.');
          updateStatsBanner();
          return;
        }
        toast('Impossible de retirer ce repère.');
      });
      return;
    }
    if (kind === 'rally') {
      api('/api/atak/zones/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
        rallyRows = rallyRows.filter(function (row) { return String(row.id) !== id; });
        renderRallyPoints();
        toast('Point de ralliement retiré.');
        updateStatsBanner();
      }).catch(function () { toast('Impossible de retirer ce point de ralliement.'); });
      return;
    }
    if (kind === 'ping') {
      api('/api/pings/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
        dropLocalId(pingMarkers, id);
        toast('Repère rapide retiré.');
      }).catch(function () {
        dropLocalId(pingMarkers, id);
        toast('Repère rapide retiré.');
      });
    }
  }

  function syncDeleteAction(target) {
    ctxTarget = target || null;
    var group = document.getElementById('ow-ctx-delete-group');
    var btn = document.getElementById('ow-ctx-delete');
    var show = !!target;
    if (group) {
      group.hidden = !show;
      group.textContent = (target && target.label) ? String(target.label) : 'Élément';
    }
    if (btn) btn.hidden = !show;
  }

  function rememberClick(ll) {
    if (!ll) return '';
    lastClickWorld = latLngToWorld(ll);
    lastClickGrid = Math.round(lastClickWorld.x) + ' / ' + Math.round(lastClickWorld.y);
    return lastClickGrid;
  }

  function openContextAt(ll, originalEvent, layer) {
    if (!ll) return;
    ctxLatLng = ll;
    rememberClick(ll);
    syncDeleteAction(hitDeletable(ll, layer));
    document.getElementById('ow-ctx-head').textContent = gridLabel(ll);
    var cx = originalEvent && originalEvent.clientX;
    var cy = originalEvent && originalEvent.clientY;
    if (cx == null || cy == null) {
      var pt = map.latLngToContainerPoint(ll);
      var stage = document.getElementById('ow-map-stage').getBoundingClientRect();
      cx = stage.left + pt.x;
      cy = stage.top + pt.y;
    }
    placeContextMenu(cx, cy);
  }

  function placeContextMenu(clientX, clientY) {
    var ctx = document.getElementById('ow-context');
    var stage = document.getElementById('ow-map-stage').getBoundingClientRect();
    ctx.hidden = false;
    var w = ctx.offsetWidth || 240;
    var h = ctx.offsetHeight || 320;
    var maxH = Math.max(160, stage.height - 16);
    ctx.style.maxHeight = maxH + 'px';
    h = Math.min(ctx.offsetHeight || h, maxH);
    var left = clientX - stage.left + 8;
    var top = clientY - stage.top + 8;
    if (left + w > stage.width - 8) left = clientX - stage.left - w - 8;
    if (top + h > stage.height - 8) top = clientY - stage.top - h - 8;
    if (left < 8) left = 8;
    if (top < 8) top = 8;
    ctx.style.left = left + 'px';
    ctx.style.top = top + 'px';
  }

  map.on('contextmenu', function (event) {
    L.DomEvent.preventDefault(event);
    openContextAt(event.latlng, event.originalEvent, null);
  });
  document.addEventListener('click', function (event) {
    if (!document.getElementById('ow-context').contains(event.target)) hideContext();
  });

  document.getElementById('ow-context').addEventListener('contextmenu', function (event) {
    event.preventDefault();
  });
  document.getElementById('ow-context').addEventListener('click', function (event) {
    var action = event.target.closest('[data-ctx]');
    if (!action || !ctxLatLng) return;
    var act = action.dataset.ctx;
    var target = ctxTarget;
    var ll = ctxLatLng;
    hideContext();
    if (act === 'delete') { deleteMapTarget(target); return; }
    if (act === 'marker') saveMarker(ll, 'Marqueur');
    if (act === 'ping') {
      if (window.OverwatchOps && window.OverwatchOps.openPing) window.OverwatchOps.openPing(ll);
      else savePing(ll);
    }
    if (act === 'aoi') { setTool('aoi'); draftPoints = [ll]; updateDraft(); toast('Cliquez les sommets, double-clic pour fermer.'); }
    if (act === 'intel') saveIntelNote(ll);
    if (act === 'sitrep') saveSitrep(ll);
    if (act === 'salute' || act === 'nineline' || act === 'casevac') {
      rememberClick(ll);
      if (act === 'nineline') openView('air');
      else openView('mission');
      toast(act === 'salute'
        ? 'Grille reprise dans le compte rendu SALUTE.'
        : (act === 'nineline' ? 'Grille reprise dans la demande d’appui.' : 'Grille reprise dans le CASEVAC.'));
      return;
    }
    if (act === 'po') { placeReachPoint(ll); finishPoSession(); }
    if (act === 'rally') { placeRallyPoint(ll); }
    if (act === 'los') { setTool('los'); draftPoints = [ll]; toast('Cliquez la cible.'); }
    if (act === 'chatgrid') {
      if (window.OverwatchOps && window.OverwatchOps.openChatGrid) window.OverwatchOps.openChatGrid(ll);
      else sendChat(activeChannel, 'GRILLE ' + gridLabel(ll).replace('GRID ', ''));
    }
    if (act === 'measure') { setTool('measure'); measureFrom = ll; draftPoints = [ll]; toast('Cliquez le second point.'); }
    if (act === 'copy') copyCoords(ll);
  });

  function applyLook(look) {
    if (look === 'classic' || look === 'aerial') look = 'color';
    var allowed = { color: 1, bw: 1 };
    if (!allowed[look]) look = 'color';
    try { localStorage.setItem(LOOK_KEY, look); } catch (e) {}
    document.getElementById('ow-map-stage').dataset.look = look;
    document.querySelectorAll('[data-ow-look]').forEach(function (input) { input.checked = input.value === look; });
    var tilePane = map.getPane('tilePane');
    var aerialPane = map.getPane('atakAerialPane');
    var filter = look === 'bw' ? 'grayscale(1) contrast(1.18) brightness(1.02)' : 'none';
    if (tilePane) tilePane.style.filter = filter;
    if (aerialPane) aerialPane.style.filter = filter;
    var markerPane = map.getPane('markerPane');
    if (markerPane) markerPane.style.filter = 'none';
    var overlayPane = map.getPane('overlayPane');
    if (overlayPane) overlayPane.style.filter = 'none';
    if (window.OverwatchTools && typeof window.OverwatchTools.applyNvg === 'function') {
      window.OverwatchTools.applyNvg();
    }
  }

  function storedLook() {
    try {
      var v = localStorage.getItem(LOOK_KEY);
      if (v === 'classic' || v === 'aerial') return 'color';
      if (v === 'color' || v === 'bw') return v;
    } catch (e) {}
    return 'color';
  }

  function storedLabelSize() {
    try {
      var v = localStorage.getItem(LABEL_SIZE_KEY);
      if (v) return Math.max(6, Math.min(18, parseFloat(v)));
    } catch (e) {}
    return 9;
  }

  function storedIconSize() {
    try {
      var v = localStorage.getItem(ICON_SIZE_KEY);
      if (v) return Math.max(0.5, Math.min(2, parseFloat(v)));
    } catch (e) {}
    return 1;
  }

  function applyLabelSize(size) {
    var s = Math.max(6, Math.min(18, parseFloat(size) || 9));
    try { localStorage.setItem(LABEL_SIZE_KEY, String(s)); } catch (e) {}
    document.documentElement.style.setProperty('--ow-label-size', s + 'px');
  }

  function applyIconSize(size) {
    var s = Math.max(0.5, Math.min(2, parseFloat(size) || 1));
    try { localStorage.setItem(ICON_SIZE_KEY, String(s)); } catch (e) {}
    document.documentElement.style.setProperty('--ow-icon-size', String(s));
  }

  function toggleSettingsAside() {
    var workspace = document.querySelector('.ow-workspace');
    var collapsed = workspace.classList.contains('is-settings-collapsed');
    workspace.classList.toggle('is-settings-collapsed', !collapsed);
    try {
      localStorage.setItem(SETTINGS_COLLAPSED_KEY, collapsed ? '0' : '1');
    } catch (e) {}
    setTimeout(function () { map.invalidateSize(); }, 50);
  }

  function restoreSettingsCollapsed() {
    try {
      var v = localStorage.getItem(SETTINGS_COLLAPSED_KEY);
      if (v === '1') {
        document.querySelector('.ow-workspace').classList.add('is-settings-collapsed');
      }
    } catch (e) {}
  }

  document.querySelectorAll('[data-ow-look]').forEach(function (input) {
    input.addEventListener('change', function () { applyLook(input.value); });
  });
  applyLook(storedLook());
  applyLabelSize(storedLabelSize());
  applyIconSize(storedIconSize());
  restoreSettingsCollapsed();

  document.querySelectorAll('[data-ow-layer]').forEach(function (input) {
    input.addEventListener('change', function () {
      hiddenLayers[input.dataset.owLayer] = !input.checked;
      if (input.dataset.owLayer === 'tracks') {
        tracksOn = input.checked;
        Object.keys(trackLines).forEach(function (id) {
          var progress = document.getElementById('ow-progress-trail');
          var skipLine = !!(progress && progress.checked && selected && unitId(selected) === id);
          if (tracksOn && !skipLine) trackLines[id].addTo(map); else map.removeLayer(trackLines[id]);
        });
      }
      if (input.dataset.owLayer === 'shapes') loadShapes();
      if (input.dataset.owLayer === 'vehicles') renderGpsVehiclesOnMap(gpsVehicles);
      if (input.dataset.owLayer === 'air') renderAirAssetsOnMap(airAssets);
      if (input.dataset.owLayer === 'arma-markers') renderArmaMarkers();
      renderMap();
    });
  });

  function openDrawer(kicker, title, html) {
    clearDrawerContactHead();
    document.getElementById('ow-drawer-kicker').textContent = kicker;
    document.getElementById('ow-drawer-title').textContent = title;
    document.getElementById('ow-drawer-body').innerHTML = html;
    document.getElementById('ow-drawer').hidden = false;
  }

  function card(title, tag, body) {
    return '<div class="ow-card"><div class="ow-card-head"><span>' + escapeHtml(title) + '</span><span class="ow-tag">' +
      escapeHtml(tag) + '</span></div><div class="ow-card-body">' + body + '</div></div>';
  }

  function loadPhotos() {
    return api('/api/recon/images?limit=80').then(function (payload) {
      photos = asList(payload, 'images');
      updateStatsBanner();
      if (window.OverwatchTools && window.OverwatchTools.drawPhotos) window.OverwatchTools.drawPhotos();
    }).catch(function () { photos = []; });
  }

  function loadNine() {
    return api('/api/nine-line?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      nineLines = asList(payload, 'items');
      paintAirLists();
    }).catch(function () { nineLines = []; });
  }

  function loadCas() {
    return api('/api/cas?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      casRows = asList(payload, 'items');
      paintAirLists();
    }).catch(function () { casRows = []; });
  }

  function loadAirAssets() {
    return api('/api/atak/air-assets?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      airAssets = Array.isArray(payload) ? payload : asList(payload, 'items');
      paintAirLists();
      renderAirAssetsOnMap(airAssets);
      try { window.dispatchEvent(new CustomEvent('atak:air-markers-updated', { detail: { assets: airAssets } })); } catch (e) {}
    }).catch(function () {
      airAssets = [];
      clearAirAssetMarkers();
    });
  }

  function jtacRows() {
    var seen = {};
    var out = [];
    casRows.concat(nineLines).forEach(function (row) {
      if (!row) return;
      var id = String(row.id || '');
      if (id && seen[id]) return;
      if (id) seen[id] = true;
      out.push(row);
    });
    return out;
  }

  function airStatusLabel(s) {
    s = String(s || '').toUpperCase();
    if (s === 'SUSPECT') return 'À vérifier';
    if (s === 'OFFLINE') return 'Hors liaison';
    if (s === 'AVAILABLE') return 'Au sol';
    return 'En vol';
  }

  function airPilotLabel(s) {
    s = String(s || '').toUpperCase();
    if (s === 'ROGER') return 'Reçu';
    if (s === 'INBOUND') return 'En approche';
    if (s === 'ONSTA') return 'À poste';
    if (s === 'ENGAGED') return 'Engagé';
    if (s === 'RTB') return 'Retour';
    return '';
  }

  function airRoleLabel(s) {
    s = String(s || '').toLowerCase();
    if (s === 'transport') return 'Transport';
    if (s === 'cas') return 'Appui aérien';
    if (s === 'recon') return 'Reconnaissance';
    if (s === 'medevac') return 'Évacuation sanitaire';
    if (s === 'resupply') return 'Ravitaillement';
    if (s === 'escort') return 'Escorte';
    return '';
  }

  function jtacStatusLabel(s) {
    s = String(s || '').toUpperCase();
    if (s === 'DRAFT') return 'Brouillon';
    if (s === 'SUBMITTED' || s === 'ACTIVE') return 'Transmise';
    if (s === 'ACKNOWLEDGED') return 'Accusée';
    if (s === 'CHECKING') return 'Vérification';
    if (s === 'TARGET_ACQUIRED') return 'Objectif acquis';
    if (s === 'INBOUND') return 'En approche';
    if (s === 'CLEARED_HOT') return 'Feu autorisé';
    if (s === 'ENGAGED') return 'Engagé';
    if (s === 'BDA_PENDING') return 'Compte rendu en attente';
    if (s === 'COMPLETE') return 'Terminée';
    if (s === 'ABORTED' || s === 'CANCELLED') return 'Annulée';
    if (s === 'REQUESTED') return 'Demandée';
    if (s === 'LAUNCHED') return 'Décollée';
    if (s === 'ON_SCENE') return 'Sur zone';
    return s || 'Transmise';
  }

  function formatAirOrdnance(raw) {
    if (raw == null || raw === '') return '';
    if (Array.isArray(raw)) {
      return raw.map(function (bit) { return String(bit); }).filter(Boolean).join(' · ');
    }
    if (typeof raw === 'object') {
      var bits = [];
      Object.keys(raw).forEach(function (key) {
        var val = raw[key];
        if (val == null || val === '') return;
        bits.push(String(val));
      });
      return bits.join(' · ');
    }
    return String(raw);
  }

  function airCrewCount(row) {
    var occ = row && (row.occupants || row.crew);
    if (typeof occ === 'string') {
      try { occ = JSON.parse(occ); } catch (e) { occ = []; }
    }
    if (Array.isArray(occ)) return occ.length;
    var n = Number(row && row.crew_count);
    return Number.isFinite(n) ? n : 0;
  }

  function airEtaLabel(row) {
    var n = Number(row && row.eta_minutes);
    if (!Number.isFinite(n) || n < 0) return '';
    if (n === 0) return 'Arrivée estimée maintenant';
    return 'Arrivée estimée ' + n + ' min';
  }

  function normalizeAirAsset(row) {
    if (!row) return null;
    var asset = Object.assign({}, row);
    var cs = String(asset.callsign || asset.call_sign || '').trim();
    asset.callsign = cs;
    asset.call_sign = cs;
    return asset;
  }
  function airAssetDetailHtml(row) {
    var card = airAssetCardHtml(row);
    // La carte liste est un bouton : pour le tiroir on garde le contenu sans wrapper interactif.
    return '<div class="ow-air-sheet">' + card.replace(/^<button\b[^>]*>/, '<div class="ow-air-card is-sheet">').replace(/<\/button>$/, '</div>') + '</div>';
  }
  function openAirAssetSheet(row) {
    var asset = normalizeAirAsset(row);
    if (!asset) return;
    selected = null;
    if (window.ATAKReachOverlay && typeof window.ATAKReachOverlay.clear === 'function') {
      try { window.ATAKReachOverlay.clear(true); } catch (e) {}
    }
    var x = Number(asset.pos_x);
    var y = Number(asset.pos_y);
    if (Number.isFinite(x) && Number.isFinite(y) && (Math.abs(x) > 0.5 || Math.abs(y) > 0.5)) {
      var ll = worldToLatLng(x, y);
      if (ll) map.setView(ll, Math.max(map.getZoom(), 4));
    } else {
      var match = units.filter(function (unit) {
        return callsign(unit).toLowerCase() === String(asset.callsign || '').toLowerCase();
      })[0];
      var loc = match ? point(match) : null;
      if (loc) map.setView(loc, Math.max(map.getZoom(), 4));
    }
    if (window.ATAKUnitDossier && typeof window.ATAKUnitDossier.open === 'function') {
      try { window.ATAKUnitDossier.open(asset); } catch (e2) {}
    }
    clearDrawerContactHead();
    setDrawerContactHead({
      call_sign: asset.callsign,
      role: asset.model || asset.aircraft_type || 'Aéronef',
      group: asset.group_name || asset.group || 'Air',
      side: asset.side
    });
    document.getElementById('ow-drawer-kicker').textContent = 'Fiche aérienne';
    document.getElementById('ow-drawer-title').textContent = clean(asset.callsign, 'Aéronef');
    document.getElementById('ow-drawer-body').innerHTML = airAssetDetailHtml(asset);
    document.getElementById('ow-drawer').hidden = false;
  }
  function airLocate(row) {
    openAirAssetSheet(row);
  }

  function airAssetCardHtml(row) {
    var status = airStatusLabel(row.status);
    var pilot = airPilotLabel(row.pilot_status);
    var role = airRoleLabel(row.mission_id);
    var dest = String(row.station || row.dest || '').trim();
    var eta = airEtaLabel(row);
    var ordnance = formatAirOrdnance(row.ordnance);
    var notes = String(row.checklist || row.notes || '').trim();
    if (notes.charAt(0) === '{') notes = '';
    var n = airCrewCount(row);
    var occupants = airOccupantsList(row);
    var chef = clean(row.leader || row.group_leader || row.pilot, '');
    var team = clean(row.fire_team_label || row.team, '');
    var grp = clean(row.group_name || row.group, '');
    var ammo = clean(row.ammo || (row.source_arma && row.source_arma.ammo), '');
    var model = clean(row.model || row.aircraft_type, '');
    var kv = '';
    kv += '<span>Situation</span><span>' + escapeHtml(status + (pilot ? ' · ' + pilot : '')) + '</span>';
    if (model) kv += '<span>Modèle</span><span>' + escapeHtml(model) + (row.aircraft_count > 1 ? ' ×' + row.aircraft_count : '') + '</span>';
    if (chef) kv += '<span>Chef</span><span>' + escapeHtml(chef) + '</span>';
    if (team) kv += '<span>Équipe</span><span>' + escapeHtml(team) + '</span>';
    if (grp) kv += '<span>Groupe</span><span>' + escapeHtml(grp) + '</span>';
    if (role) kv += '<span>Mission</span><span>' + escapeHtml(role) + '</span>';
    if (dest) kv += '<span>Destination</span><span>' + escapeHtml(dest) + '</span>';
    if (eta) kv += '<span>ETA</span><span>' + escapeHtml(eta.replace(/^Arrivée estimée /, '')) + '</span>';
    if (row.freq) kv += '<span>Fréquence</span><span>' + escapeHtml(row.freq) + '</span>';
    if (row.laser) kv += '<span>Code laser</span><span>' + escapeHtml(row.laser) + '</span>';
    if (row.auth_code || row.auth) kv += '<span>Authentification</span><span>' + escapeHtml(row.auth_code || row.auth) + '</span>';
    if (occupants.length) {
      kv += '<span>Équipage</span><span>' + occupants.map(function (o) {
        var name = clean((o && (o.name || o.callsign || o.call_sign)) || '', '—');
        var seat = seatLabelFr(o && (o.seat || o.role));
        return escapeHtml(name) + (seat ? ' (' + escapeHtml(seat) + ')' : '');
      }).join('<br/>') + '</span>';
    } else if (n) {
      kv += '<span>À bord</span><span>' + n + ' personne' + (n > 1 ? 's' : '') + '</span>';
    }
    if (ordnance) kv += '<span>Emport</span><span>' + escapeHtml(ordnance) + '</span>';
    if (ammo) kv += '<span>Munitions</span><span>' + escapeHtml(ammo) + '</span>';
    if (row.fuel_pct != null && row.fuel_pct !== '') kv += '<span>Carburant</span><span>' + escapeHtml(String(row.fuel_pct)) + ' %</span>';
    if (row.bingo_fuel) kv += '<span>Autonomie</span><span>' + escapeHtml(String(row.bingo_fuel)) + '</span>';
    if (notes) kv += '<span>Notes</span><span>' + escapeHtml(notes) + '</span>';
    var tag = status + (pilot ? ' · ' + pilot : '');
    return '<button type="button" class="ow-air-card" data-air-locate="' + escapeHtml(String(row.callsign || '')) + '">' +
      '<div class="ow-card-head"><span>' + escapeHtml(clean(row.callsign, 'Aéronef')) +
      '</span><span class="ow-tag">' + escapeHtml(tag) + '</span></div>' +
      (kv ? '<div class="ow-card-body"><div class="ow-kv">' + kv + '</div></div>' : '') +
      '</button>';
  }

  function jtacCardHtml(row) {
    var author = clean(row.author || row.jtac || row.call_sign, 'JTAC');
    var assigned = String(row.assignedAircraft || row.assigned_aircraft || '').trim();
    var target = String(row.line5 || '').trim();
    var grid = String(row.line1 || row.line6 || '').trim();
    var kv = '';
    if (assigned) kv += '<span>Appareil</span><span>' + escapeHtml(assigned) + '</span>';
    if (grid) kv += '<span>Point initial</span><span>' + escapeHtml(grid) + '</span>';
    if (target) kv += '<span>Objectif</span><span>' + escapeHtml(target) + '</span>';
    if (row.line7) kv += '<span>Type</span><span>' + escapeHtml(String(row.line7)) + '</span>';
    return '<div class="ow-card">' +
      '<div class="ow-card-head"><span>' + escapeHtml(author) +
      '</span><span class="ow-tag">' + escapeHtml(jtacStatusLabel(row.status)) + '</span></div>' +
      (kv ? '<div class="ow-card-body"><div class="ow-kv">' + kv + '</div></div>' : '') +
      '</div>';
  }

  function nineFormHtml() {
    return '<form class="ow-form-grid" id="ow-nine-form">' +
      '<label>IP / grille<input name="line1" required placeholder="Point initial" value="' + escapeHtml(lastClickGrid) + '"></label>' +
      '<label>Cap<input name="line2" placeholder="Cap d’approche"></label>' +
      '<label>Distance<input name="line3" placeholder="Distance à la cible"></label>' +
      '<label>Élévation<input name="line4" placeholder="Altitude cible"></label>' +
      '<label>Description<input name="line5" placeholder="Nature de la cible"></label>' +
      '<label>Marquage<input name="line6" placeholder="Fumée, laser…"></label>' +
      '<label>Type de mission<input name="line7" value="CAS"></label>' +
      '<label>Amis à proximité<input name="line8" placeholder="Position des amis"></label>' +
      '<label>Sortie<input name="line9" placeholder="Itinéraire de sortie"></label>' +
      '<button class="ow-primary" type="submit">Envoyer la demande</button></form>';
  }

  function airListHtml() {
    return airAssets.map(airAssetCardHtml).join('') ||
      '<p class="ow-help">Aucun aéronef déclaré pour le moment. Un manifeste de vol envoyé depuis le jeu apparaît ici avec l’emport, le carburant et l’arrivée estimée.</p>';
  }

  function jtacListHtml() {
    var rows = jtacRows().slice(0, 12);
    return rows.map(jtacCardHtml).join('') ||
      '<p class="ow-help">Aucune demande d’appui pour le moment.</p>';
  }

  function paintAirLists() {
    var airHost = document.getElementById('ow-air-list');
    var jtacHost = document.getElementById('ow-jtac-list');
    if (airHost) airHost.innerHTML = airListHtml();
    if (jtacHost) jtacHost.innerHTML = jtacListHtml();
  }

  function airHtml() {
    return card('Situation aérienne', String(airAssets.length),
      '<div class="ow-event"><span>Aéronefs</span><strong>' + airAssets.length + '</strong></div>' +
      '<div class="ow-event"><span>Demandes d’appui</span><strong>' + jtacRows().length + '</strong></div>') +
      '<p class="ow-kicker">Aéronefs</p>' +
      '<p class="ow-help">Manifestes de vol reçus du jeu : indicatif, mission, emport, carburant et arrivée estimée. Cliquez une fiche pour centrer la carte.</p>' +
      '<div id="ow-air-list">' + airListHtml() + '</div>' +
      '<p class="ow-kicker">Demandes JTAC</p>' +
      '<p class="ow-help">Les 9-line transmises par le terrain ou préparées ici. Clic droit sur la carte pour reprendre la grille.</p>' +
      '<div id="ow-jtac-list">' + jtacListHtml() + '</div>' +
      '<p class="ow-kicker">Nouvelle demande d’appui</p>' +
      nineFormHtml();
  }

  function loadMedevac() {
    return api('/api/atak/medevac?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      medevacs = asList(payload, 'items');
    }).catch(function () { medevacs = []; });
  }

  function loadAlerts() {
    return api('/api/atak/zones/alerts?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      zoneAlerts = asList(payload, 'alerts');
      if (zoneAlerts.length) toast('Alerte de zone : une unité est entrée ou sortie d’une AOI.');
    }).catch(function () {});
  }

  function loadWeather() {
    return api('/api/atak/weather?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      return payload;
    }).catch(function () { return null; });
  }

  function missionHtml() {
    return card('Opération', 'Actif',
      '<div class="ow-event"><span>Contacts</span><strong>' + units.length + '</strong></div>' +
      '<div class="ow-event"><span>Tracés</span><strong>' + shapes.length + '</strong></div>' +
      '<div class="ow-event"><span>Photos</span><strong>' + photos.length + '</strong></div>') +
      '<p class="ow-kicker">Tâches de groupe</p>' +
      '<p class="ow-help">Transmettez une tâche à un groupe visible sur la carte. Elle arrive sur les téléphones ATAK des opérateurs concernés.</p>' +
      groupTaskFormHtml() +
      '<p class="ow-kicker">Alerte plein écran</p>' +
      '<p class="ow-help">Le message recouvre tout l’écran du téléphone, ouvert ou en position mini.</p>' +
      fullscreenAlertFormHtml() +
      '<p class="ow-kicker">Replay et bilan</p>' +
      '<p class="ow-help">Rejouez les trajectoires déjà reçues, puis exportez le bilan de mission (carte annotée et fil d’ordres).</p>' +
      '<div class="ow-form-actions"><button type="button" class="ow-secondary" data-ow-replay>Ouvrir le replay</button>' +
      '<button type="button" class="ow-primary" data-ow-debrief>Exporter le bilan</button></div>' +
      '<p class="ow-kicker">Compte rendu SALUTE</p>' +
      '<p class="ow-help">Taille, activité, position, unité, heure, équipement. Le compte rendu part sur le fil et se pose sur les tracés. Clic droit sur la carte pour préremplir la grille.</p>' +
      '<form class="ow-form-grid" id="ow-salute-form">' +
      '<label>Taille<input name="size" placeholder="Ex. 2 véhicules, 8 personnes"></label>' +
      '<label>Activité<input name="activity" placeholder="Ex. en déplacement vers le nord"></label>' +
      '<label>Position<input name="location" placeholder="Lieu ou grille" value="' + escapeHtml(lastClickGrid) + '"></label>' +
      '<label>Unité<input name="unit" placeholder="Ex. infanterie motorisée"></label>' +
      '<label>Heure<input name="time" placeholder="Heure du contact"></label>' +
      '<label>Équipement<input name="equipment" placeholder="Ex. RPG, mitrailleuse"></label>' +
      '<input type="hidden" name="grid" value="' + escapeHtml(lastClickGrid) + '">' +
      '<button class="ow-primary" type="submit">Transmettre le SALUTE</button></form>' +
      '<p class="ow-kicker">Appui aérien</p>' +
      '<p class="ow-help">Les aéronefs, les manifestes et les demandes JTAC sont dans l’espace Air.</p>' +
      '<button type="button" class="ow-secondary" data-open-view="air">Ouvrir Air</button>' +
      (jtacRows().slice(0, 3).map(function (row) {
        return '<div class="ow-event"><span>' + escapeHtml(clean(row.author || row.jtac, 'JTAC')) + '</span><span class="ow-tag">' + escapeHtml(jtacStatusLabel(row.status)) + '</span></div>';
      }).join('') || '<p class="ow-help">Aucune demande d’appui pour le moment.</p>') +
      '<p class="ow-kicker">CASEVAC</p>' +
      (medevacs.slice(0, 5).map(function (row) {
        return '<div class="ow-event"><span>' + escapeHtml(clean(row.call_sign || row.author, 'MEDEVAC')) + '</span><span class="ow-tag amber">' + escapeHtml(clean(row.status, 'OPEN')) + '</span></div>';
      }).join('') || '<p class="ow-help">Aucune évacuation ouverte.</p>') +
      '<form class="ow-form-grid" id="ow-medevac-form">' +
      '<label>Indicatif<input name="callsign" value="' + escapeHtml(authorName) + '"></label>' +
      '<label>Grille de ramassage<input name="pickup_grid" placeholder="À pointer sur la carte" value="' + escapeHtml(lastClickGrid) + '"></label>' +
      '<label>Blessés T1<input name="patients_t1_urgent" type="number" min="0" value="0"></label>' +
      '<label>Blessés T2<input name="patients_t2_urgent" type="number" min="0" value="0"></label>' +
      '<label>Blessés T3<input name="patients_t3_delayed" type="number" min="0" value="0"></label>' +
      '<label>Blessés T4<input name="patients_t4_expectant" type="number" min="0" value="0"></label>' +
      '<label>Observations<textarea name="remarks" placeholder="État, sécurité LZ, fréquence…"></textarea></label>' +
      '<button class="ow-primary" type="submit">Ouvrir CASEVAC</button></form>';
  }

  function formatPhotoWhen(row) {
    var captured = String(row && (row.captured_at || '') || '').trim();
    var created = String(row && (row.created_at || '') || '').trim();
    var raw = captured;
    if (!raw || /^1970-/.test(raw) || /^0000-/.test(raw)) raw = created;
    if (!raw) return '';
    if (/^\d+$/.test(raw)) {
      var n = Number(raw);
      if (n < 1e12) n *= 1000;
      if (n < 1e12) return created || '';
      var dNum = new Date(n);
      if (isNaN(dNum.getTime()) || dNum.getUTCFullYear() < 2001) return created || '';
      return formatPhotoClock(dNum);
    }
    var iso = raw.indexOf('T') >= 0 ? raw : raw.replace(' ', 'T');
    var d = new Date(iso);
    if (isNaN(d.getTime()) || d.getUTCFullYear() < 2001) {
      if (created && created !== raw) return formatPhotoWhen({ captured_at: created, created_at: '' });
      return raw;
    }
    return formatPhotoClock(d);
  }

  function formatPhotoClock(d) {
    var dd = d.getDate();
    var mm = d.getMonth() + 1;
    var yyyy = d.getFullYear();
    var hh = d.getHours();
    var mi = d.getMinutes();
    return (dd < 10 ? '0' : '') + dd + '/' + (mm < 10 ? '0' : '') + mm + '/' + yyyy +
      ' · ' + (hh < 10 ? '0' : '') + hh + ':' + (mi < 10 ? '0' : '') + mi;
  }

  function openPhotoLightbox(src, blurred) {
    if (!src) return;
    var existing = document.getElementById('ow-photo-lightbox');
    if (existing) existing.remove();
    var overlay = document.createElement('div');
    overlay.id = 'ow-photo-lightbox';
    overlay.className = 'ow-photo-lightbox' + (blurred ? ' is-blurred' : '');
    overlay.innerHTML =
      '<button type="button" class="ow-photo-lightbox-close" aria-label="Fermer">×</button>' +
      '<img src="' + escapeHtml(src) + '" alt="Photo agrandie">';
    document.body.appendChild(overlay);
    function close() { overlay.remove(); }
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay || (e.target && e.target.classList && e.target.classList.contains('ow-photo-lightbox-close'))) {
        close();
      }
    });
    document.addEventListener('keydown', function onKey(ev) {
      if (ev.key === 'Escape') {
        document.removeEventListener('keydown', onKey);
        close();
      }
    });
  }

  function intelHtml() {
    return '<label class="ow-search"><span>⌕</span><input id="ow-intel-q" placeholder="Rechercher une photo, une note…"></label>' +
      '<form class="ow-form-grid" id="ow-photo-form">' +
      '<label>Photo liée à la carte<input type="file" name="photo" accept="image/*"></label>' +
      '<button class="ow-primary" type="submit">Déposer la photo</button></form>' +
      photos.slice(0, 24).map(function (row) {
        var url = String(row.url || '');
        var id = String(row.id || '');
        var title = clean(row.author_callsign || row.author || row.device_label, 'CAPTURE');
        var stamp = formatPhotoWhen(row);
        var caption = String(row.caption || '').trim();
        var blurred = !!Number(row.is_blurred || 0);
        var transferred = !!(row.sse_case_id || row.sse_transferred_at);
        return '<div class="ow-card' + (blurred ? ' is-blurred' : '') + '" data-photo-card="' + escapeHtml(id) + '">' +
          '<div class="ow-card-head"><span>' + escapeHtml(title) +
          '</span><span class="ow-tag">' + escapeHtml(clean(row.device_label, 'PHOTO')) + '</span></div>' +
          (url
            ? '<div class="ow-thumb" style="background-image:url(\'' + escapeHtml(url) + '\')" data-photo-op="open" data-photo-id="' + escapeHtml(id) + '" data-photo-url="' + escapeHtml(url) + '" role="button" tabindex="0" title="Ouvrir en grand"></div>'
            : '') +
          '<div class="ow-card-body">' +
          (caption ? '<p class="ow-help">' + escapeHtml(caption) + '</p>' : '') +
          (transferred ? '<p class="ow-help">Classée dans un dossier SSE</p>' : '') +
          '<div class="ow-event"><span>' + escapeHtml(stamp) + '</span></div>' +
          '<div class="ow-photo-actions">' +
          '<button type="button" class="ow-tag" data-send-photo="' + escapeHtml(id) + '">Envoyer</button>' +
          (url ? '<button type="button" class="ow-tag" data-photo-op="open" data-photo-id="' + escapeHtml(id) + '" data-photo-url="' + escapeHtml(url) + '">Agrandir</button>' : '') +
          '<button type="button" class="ow-tag" data-photo-op="blur" data-photo-id="' + escapeHtml(id) + '">' + (blurred ? 'Retirer le flou' : 'Flouter') + '</button>' +
          '<button type="button" class="ow-tag" data-photo-op="sse" data-photo-id="' + escapeHtml(id) + '">Passer en SSE</button>' +
          '<button type="button" class="ow-tag red" data-photo-op="delete" data-photo-id="' + escapeHtml(id) + '">Supprimer</button>' +
          '</div></div></div>';
      }).join('') || '<p class="ow-help">Aucune image reçue pour cette mission.</p>';
  }

  function toolsHtml() {
    function card(title, text, action) {
      return '<div class="ow-tool-card"><h3>' + title + '</h3><p>' + text + '</p>' + (action || '') + '</div>';
    }
    return '<p class="ow-help">Les outils restent actifs après une pose. Échap ou Sélection pour quitter. Maintenez le clic pour tracer une zone, un cercle ou une ligne.</p>' +
      '<p class="ow-kicker">Carte</p>' +
      card('Zone et lasso', 'Choisissez Zone ou Zone tactique. Maintenez, dessinez le contour, relâchez pour fermer.', '<button type="button" class="ow-tag" data-tool-goto="aoi">Tracer</button>') +
      card('Cercle et rectangle', 'Appuyez au premier point, glissez, relâchez pour poser. L’outil reste prêt pour un autre tracé.', '<button type="button" class="ow-tag" data-tool-goto="circle">Cercle</button>') +
      card('Point à atteindre', 'Un clic pose un point avec un anneau de 20 m. Dès qu’un téléphone ATAK y entre, le point est confirmé.', '<button type="button" class="ow-tag" data-tool-goto="po">Poser</button>') +
      card('Point de ralliement', 'Un clic pose un lieu de regroupement avec un anneau de 50 m, visible au poste et en jeu.', '<button type="button" class="ow-tag" data-tool-goto="rally">Poser</button>') +
      card('Visée / masque', 'Glissez de l’observateur à la cible. Le poste indique si le relief masque la visée.', '<button type="button" class="ow-tag" data-tool-goto="los">Tracer</button>') +
      card('Temps de parcours', 'Glissez un trajet. Les temps à pied et en véhicule restent indicatifs.', '<button type="button" class="ow-tag" data-tool-goto="eta">Tracer</button>') +
      card('Profil d’élévation', 'Glissez une coupe. Le relief s’affiche seulement s’il a été relevé.', '<button type="button" class="ow-tag" data-tool-goto="profile">Tracer</button>') +
      card('Tâche de groupe', 'Choisissez un groupe, une action et l’urgence. La tâche arrive sur les téléphones concernés.', '<button type="button" class="ow-tag" data-ow-group-task>Ouvrir</button>') +
      card('Alerte plein écran', 'Le message recouvre tout l’écran du téléphone, ouvert ou en position mini.', '<button type="button" class="ow-tag" data-ow-fs-alert>Ouvrir</button>') +
      card('Aller à une grille', 'Saisissez est / nord ou cliquez un point.', '<button type="button" class="ow-tag" data-tool-goto="goto">Ouvrir</button>') +
      card('Anneaux de portée', 'Cliquez un centre. 100, 250, 500 et 1 000 m.', '<button type="button" class="ow-tag" data-tool-goto="range">Poser</button>') +
      card('Bloc-notes', 'Notes du poste, conservées sur cet ordinateur.', '<button type="button" class="ow-tag" data-ow-notes>Ouvrir</button>') +
      card('Interception', 'Cap et distance entre deux contacts déjà en liaison.', '<button type="button" class="ow-tag" data-ow-panel="intercept">Ouvrir</button>') +
      card('Replay', 'Faites glisser la frise pour revoir les trajectoires déjà reçues.', '<button type="button" class="ow-tag" data-ow-replay>Ouvrir</button>') +
      '<p class="ow-kicker">Renseignement</p>' +
      card('Notes de terrain', 'Déposez une observation liée à la carte.', '<button type="button" class="ow-tag" data-ow-panel="osint">Ouvrir</button>') +
      card('Journal', 'Parcourt l’activité déjà transmise pour cette mission.', '<button type="button" class="ow-tag" data-ow-panel="logs">Ouvrir</button>') +
      '<p class="ow-kicker">Aide</p>' +
      '<button type="button" class="ow-secondary" data-ow-help>Ouvrir l’aide du poste</button>';
  }

  function layersHtml() {
    if (window.OverwatchOps && typeof window.OverwatchOps.layersHtml === 'function') {
      return window.OverwatchOps.layersHtml();
    }
    var list = shapes.slice().reverse().slice(0, 24).map(function (row) {
      return '<div class="ow-event"><span>' + escapeHtml(clean(row.label || row.type, 'TRACÉ')) +
        '</span><button type="button" class="ow-tag" data-del-shape="' + escapeHtml(String(row.id || '')) + '">Retirer</button></div>';
    }).join('') || '<p class="ow-help">Aucun tracé sur cette carte.</p>';
    var poList = poRows.map(function (row) {
      return '<div class="ow-event"><span>' + escapeHtml(row.label) +
        '</span><strong>' + escapeHtml(row.reached ? ('Atteint' + (row.reached_by ? ' · ' + row.reached_by : '')) : 'En attente · 20 m') +
        '</strong><button type="button" class="ow-tag" data-del-po="' + escapeHtml(String(row.id || '')) + '">Retirer</button></div>';
    }).join('') || '<p class="ow-help">Aucun point à atteindre. Rail gauche : outil ①, ou clic droit → Point à atteindre.</p>';
    var rallyList = rallyRows.map(function (row) {
      return '<div class="ow-event"><span>' + escapeHtml(row.label) +
        '</span><strong>50 m</strong><button type="button" class="ow-tag" data-del-rally="' + escapeHtml(String(row.id || '')) + '">Retirer</button></div>';
    }).join('') || '<p class="ow-help">Aucun point de ralliement. Rail gauche : outil ⚑, ou clic droit → Point de ralliement.</p>';
    var armaList = armaMarkerRows.slice(0, 24).map(function (row) {
      var data = parseMarkerData(row);
      var helper = window.ArmaMapMarkers;
      var label = helper && helper.displayLabelOf ? helper.displayLabelOf(data) : (data.label || data.text || 'Repère');
      return '<div class="ow-event"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(helper && helper.typeLabelFr ? helper.typeLabelFr(data) : '') + '</strong></div>';
    }).join('') || '<p class="ow-help">Aucun marqueur du théâtre pour l’instant. Ils apparaissent dès qu’ils sont posés en jeu.</p>';
    return '<p class="ow-help">Les fonds et le relief se règlent à gauche. Ici : état live, marqueurs, points d’objectif, ralliements et tracés.</p>' +
      '<div class="ow-event"><span>Contacts</span><strong>' + units.length + '</strong></div>' +
      '<div class="ow-event"><span>Marqueurs</span><strong>' + armaMarkerRows.length + '</strong></div>' +
      '<div class="ow-event"><span>Points d’objectif</span><strong>' + poRows.length + '</strong></div>' +
      '<div class="ow-event"><span>Points de ralliement</span><strong>' + rallyRows.length + '</strong></div>' +
      '<div class="ow-event"><span>Tracés</span><strong>' + shapes.length + '</strong></div>' +
      '<p class="ow-kicker">Marqueurs du théâtre</p>' + armaList +
      '<p class="ow-kicker">Points d’objectif</p>' + poList +
      '<p class="ow-kicker">Points de ralliement</p>' + rallyList +
      '<p class="ow-kicker">Tracés</p>' + list;
  }

  function bindDrawerForms() {
    var nine = document.getElementById('ow-nine-form');
    if (nine) nine.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(nine);
      api('/api/nine-line', {
        method: 'POST',
        body: {
          mapId: mapId,
          author: authorName,
          line1: data.get('line1'),
          line2: data.get('line2'),
          line3: data.get('line3'),
          line4: data.get('line4'),
          line5: data.get('line5'),
          line6: data.get('line6'),
          line7: data.get('line7'),
          line8: data.get('line8'),
          line9: data.get('line9')
        }
      })
        .then(function () {
          toast('Demande d’appui transmise.');
          Promise.all([loadNine(), loadCas()]).then(function () { openView('air'); });
        })
        .catch(function () { toast('Demande d’appui refusée.'); });
    });
    var med = document.getElementById('ow-medevac-form');
    if (med) med.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(med);
      var world = (lastClickWorld && Number.isFinite(Number(lastClickWorld.x)))
        ? lastClickWorld
        : { x: null, y: null };
      if ((world.x == null || world.y == null) && selected) {
        var loc = point(selected);
        world = loc ? latLngToWorld(loc) : world;
      }
      api('/api/atak/medevac', {
        method: 'POST',
        body: {
          mapId: mapId,
          call_sign: data.get('callsign'),
          author: authorName,
          pickup_grid: data.get('pickup_grid'),
          pickup_pos_x: world.x,
          pickup_pos_y: world.y,
          patients_t1_urgent: Number(data.get('patients_t1_urgent') || 0),
          patients_t2_urgent: Number(data.get('patients_t2_urgent') || 0),
          patients_t3_delayed: Number(data.get('patients_t3_delayed') || 0),
          patients_t4_expectant: Number(data.get('patients_t4_expectant') || 0),
          remarks: data.get('remarks'),
          notes: data.get('remarks')
        }
      })
        .then(function () { toast('CASEVAC ouvert.'); loadMedevac().then(function () { openView('mission'); }); })
        .catch(function () { toast('CASEVAC refusé.'); });
    });
    document.querySelectorAll('[data-send-photo]').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = button.dataset.sendPhoto;
        var photo = photos.filter(function (row) { return String(row.id) === String(id); })[0] || {};
        var url = String(photo.url || '').trim();
        var caption = String(photo.caption || photo.label || 'Photo de renseignement').trim() || 'Photo de renseignement';
        var text = url ? (caption + ' — ' + url) : (caption + ' transmise depuis le poste.');
        sendChat(activeChannel, text).then(function () {
          toast('Photo transmise sur le canal.');
        }).catch(function () {
          toast('Transmission de la photo refusée.');
        });
      });
    });
    function reloadIntel() {
      openView('intel');
    }
    function photoOps(id, payload) {
      return api('/api/recon/images/' + encodeURIComponent(id) + '/ops', { method: 'POST', body: payload });
    }
    document.querySelectorAll('[data-photo-op]').forEach(function (el) {
      el.addEventListener('click', function (event) {
        event.preventDefault();
        var op = el.getAttribute('data-photo-op') || '';
        var id = String(el.getAttribute('data-photo-id') || '');
        var url = String(el.getAttribute('data-photo-url') || '');
        var card = el.closest('[data-photo-card]');
        var photo = photos.filter(function (row) { return String(row.id) === id; })[0] || {};
        if (!url) url = String(photo.url || '');
        if (op === 'open') {
          openPhotoLightbox(url, !!(card && card.classList.contains('is-blurred')) || !!Number(photo.is_blurred || 0));
          return;
        }
        if (!id) return;
        if (op === 'delete') {
          if (!window.confirm('Retirer cette photo du panneau tactique ?')) return;
          photoOps(id, { action: 'delete' }).then(function () {
            toast('Photo retirée.');
            return reloadIntel();
          }).catch(function () { toast('Impossible de retirer cette photo pour le moment.'); });
          return;
        }
        if (op === 'blur') {
          var blurred = !!(card && card.classList.contains('is-blurred')) || !!Number(photo.is_blurred || 0);
          photoOps(id, { action: 'blur', blurred: !blurred }).then(function () {
            toast(blurred ? 'Flou retiré.' : 'Photo floutée.');
            return reloadIntel();
          }).catch(function () { toast('Impossible de modifier le flou pour le moment.'); });
          return;
        }
        if (op === 'sse') {
          api('/api/recon/images/sse-cases').then(function (payload) {
            var cases = (payload && Array.isArray(payload.cases)) ? payload.cases : [];
            if (!cases.length) {
              toast('Aucun dossier SSE ouvert. Ouvrez d’abord le portail SSE.');
              return;
            }
            var choices = cases.map(function (c) {
              return c.id + ' — ' + (c.reference_code || 'SSE') + ' — ' + (c.title || 'Sans titre');
            }).join('\n');
            var selected = window.prompt('Choisissez le numéro du dossier SSE :\n\n' + choices, String(cases[0].id));
            if (selected === null) return;
            var caseId = parseInt(String(selected).trim(), 10);
            if (!caseId) {
              toast('Dossier SSE invalide.');
              return;
            }
            return photoOps(id, { action: 'sse_transfer', case_id: caseId }).then(function () {
              toast('Photo classée dans le dossier SSE.');
              return reloadIntel();
            });
          }).catch(function () {
            toast('Impossible de classer cette photo pour le moment.');
          });
        }
      });
    });
    var photoForm = document.getElementById('ow-photo-form');
    if (photoForm) photoForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var fileInput = photoForm.querySelector('input[name="photo"]');
      var file = fileInput && fileInput.files && fileInput.files[0];
      if (!file) { toast('Choisissez une photo.'); return; }
      var loc = selected && point(selected);
      var world = loc ? latLngToWorld(loc) : { x: '', y: '' };
      var fd = new FormData();
      fd.append('photo', file);
      fd.append('mapId', String(mapId));
      fd.append('author', authorName);
      fd.append('callsign', authorName);
      if (world.x !== '') fd.append('pos_x', String(world.x));
      if (world.y !== '') fd.append('pos_y', String(world.y));
      api('/api/intel/photos', { method: 'POST', body: fd }).then(function () {
        toast('Photo déposée et liée à la position.');
        loadPhotos().then(function () { openView('intel'); });
      }).catch(function () {
        toast('Dépôt refusé. Les photos de terrain restent la source principale.');
      });
    });
    var intelQ = document.getElementById('ow-intel-q');
    if (intelQ) intelQ.addEventListener('input', function () {
      var q = intelQ.value.toLowerCase();
      document.querySelectorAll('#ow-drawer-body .ow-card').forEach(function (card) {
        card.hidden = q !== '' && card.textContent.toLowerCase().indexOf(q) === -1;
      });
    });
    document.querySelectorAll('[data-tool-goto]').forEach(function (button) {
      button.addEventListener('click', function () { setTool(button.getAttribute('data-tool-goto')); });
    });
    document.querySelectorAll('[data-del-shape]').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = button.getAttribute('data-del-shape');
        api('/api/map-shapes/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
          if (shapeLayers[String(id)]) { map.removeLayer(shapeLayers[String(id)]); delete shapeLayers[String(id)]; }
          shapes = shapes.filter(function (row) { return String(row.id) !== String(id); });
          toast('Tracé retiré.');
          openView('layers');
        }).catch(function () { toast('Impossible de retirer ce tracé.'); });
      });
    });
    document.querySelectorAll('[data-del-po]').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = button.getAttribute('data-del-po');
        api('/api/markers/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
          poRows = poRows.filter(function (row) { return String(row.id) !== String(id); });
          toast('Point à atteindre retiré.');
          renderPoMarkers();
          openView('layers');
        }).catch(function () { toast('Impossible de retirer ce point.'); });
      });
    });
    document.querySelectorAll('[data-del-rally]').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = button.getAttribute('data-del-rally');
        api('/api/atak/zones/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
          rallyRows = rallyRows.filter(function (row) { return String(row.id) !== String(id); });
          toast('Point de ralliement retiré.');
          renderRallyPoints();
          openView('layers');
        }).catch(function () { toast('Impossible de retirer ce point de ralliement.'); });
      });
    });
    bindGroupTaskForms(document.getElementById('ow-drawer'));
    bindFsAlertForms(document.getElementById('ow-drawer'));
    fillGroupTaskSelects(true);
    document.querySelectorAll('[data-open-view]').forEach(function (button) {
      button.addEventListener('click', function () { openView(button.getAttribute('data-open-view')); });
    });
    fillFsAlertSelects(true);
    document.querySelectorAll('[data-ow-group-task]').forEach(function (button) {
      button.addEventListener('click', function () { openSquadTaskForm(''); });
    });
    document.querySelectorAll('[data-ow-fs-alert]').forEach(function (button) {
      button.addEventListener('click', function () { openFullscreenAlertForm(''); });
    });
    document.querySelectorAll('#ow-drawer [data-ow-help]').forEach(function (button) {
      button.addEventListener('click', function () {
        var g = document.getElementById('ow-guide');
        if (g) g.hidden = false;
      });
    });
    if (window.OverwatchOps && typeof window.OverwatchOps.bindLayers === 'function') {
      window.OverwatchOps.bindLayers();
    }
    try { window.dispatchEvent(new CustomEvent('overwatch:mission-bound')); } catch (eBound) {}
  }

  function restoreOpsPanels() {
    var host = document.getElementById('ow-legacy-ops');
    if (!host) return;
    ['tab-radio', 'tab-identification', 'tab-pings'].forEach(function (id) {
      var panel = document.getElementById(id);
      if (panel && panel.parentElement !== host) host.appendChild(panel);
    });
    host.hidden = true;
  }
  function showOpsPanel(panelId, kicker, title) {
    var host = document.getElementById('ow-legacy-ops');
    var panel = document.getElementById(panelId);
    if (!panel) {
      openDrawer(kicker, title, '<p class="ow-help">Panneau indisponible.</p>');
      return;
    }
    restoreOpsPanels();
    if (host) host.hidden = true;
    openDrawer(kicker, title, '');
    var body = document.getElementById('ow-drawer-body');
    if (body) {
      body.innerHTML = '';
      body.appendChild(panel);
      panel.hidden = false;
      panel.style.display = '';
    }
  }
  function openView(name) {
    document.querySelectorAll('.ow-nav button, .ow-more-menu button[data-view]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.view === name);
    });
    var workspace = document.querySelector('.ow-workspace');
    workspace.classList.toggle('is-comms', name === 'comms');
    workspace.classList.toggle('is-settings', name === 'layers');
    if (name === 'overwatch') { restoreOpsPanels(); document.getElementById('ow-drawer').hidden = true; map.invalidateSize(); return; }
    if (name === 'comms') { switchChatTab('channels'); map.invalidateSize(); return; }
    if (name === 'layers') { restoreOpsPanels(); openDrawer('Cartographie', 'Calques', layersHtml()); bindDrawerForms(); map.invalidateSize(); return; }
    if (name === 'mission') {
      Promise.all([loadNine(), loadCas(), loadMedevac(), loadGroupTasks()]).then(function () {
        restoreOpsPanels();
        openDrawer('Opérations', 'Mission', missionHtml());
        bindDrawerForms();
      });
      return;
    }
    if (name === 'air') {
      Promise.all([loadAirAssets(), loadNine(), loadCas()]).then(function () {
        restoreOpsPanels();
        openDrawer('Appui aérien', 'Air', airHtml());
        bindDrawerForms();
        map.invalidateSize();
      });
      return;
    }
    if (name === 'intel') {
      loadPhotos().then(function () { restoreOpsPanels(); openDrawer('Renseignement', 'Photos', intelHtml()); bindDrawerForms(); });
      return;
    }
    if (name === 'radio') {
      showOpsPanel('tab-radio', 'Radio', 'Proximité');
      if (window.ATAKRadio && typeof window.ATAKRadio.render === 'function') {
        try { window.ATAKRadio.render(); } catch (e) {}
      }
      return;
    }
    if (name === 'iff' || name === 'identification') {
      showOpsPanel('tab-identification', 'Identification', 'IFF');
      if (window.ATAKIFF && typeof window.ATAKIFF.refresh === 'function') {
        try { window.ATAKIFF.refresh(); } catch (e) {}
      }
      if (window.ATAKIFF && typeof window.ATAKIFF.onTabActivated === 'function') {
        try { window.ATAKIFF.onTabActivated(); } catch (e2) {}
      }
      return;
    }
    if (name === 'pings') {
      showOpsPanel('tab-pings', 'Pings', 'Repères');
      if (window.ATAKPings && typeof window.ATAKPings.fetchPings === 'function') {
        try { window.ATAKPings.fetchPings(); } catch (e) {}
      }
      return;
    }
    if (name === 'tools') {
      restoreOpsPanels();
      openDrawer('Système', 'Outils', toolsHtml());
      bindDrawerForms();
      map.invalidateSize();
    }
  }

  function switchChatTab(name) {
    document.querySelectorAll('[data-chat-tab]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.chatTab === name);
    });
    document.querySelectorAll('[data-chat-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-chat-panel') !== name;
    });
    if (name === 'support') loadChat('support');
    if (name === 'squads') {
      mountSquadTaskPanel();
      fillGroupTaskSelects();
      loadGroupTasks();
    }
  }

  var commands = [
    ['Aller à une grille', 'Carte', function () { setTool('goto'); }],
    ['Anneaux de portée', 'Carte', function () { setTool('range'); }],
    ['Bloc-notes du poste', 'Mission', function () { window.dispatchEvent(new CustomEvent('overwatch:panel', { detail: { panel: 'notes' } })); }],
    ['Ouvrir le tchat opérationnel', 'Ordre', function () { openView('comms'); }],
    ['Ouvrir la mission', 'Mission', function () { openView('mission'); }],
    ['Ouvrir Air', 'Air', function () { openView('air'); }],
    ['Préparer un SITREP', 'Mission', function () { setTool('cursor'); toast('Clic droit sur la carte → compte rendu géolocalisé.'); }],
    ['Préparer un SALUTE', 'Mission', function () { openView('mission'); toast('Clic droit sur la carte pour préremplir la grille, puis remplissez le compte rendu.'); }],
    ['Tracer une route', 'Carte', function () { setTool('route'); toast('Cliquez les points, double-clic pour terminer.'); }],
    ['Ouvrir le replay', 'Outils', function () { document.getElementById('ow-timeline').hidden = !document.getElementById('ow-timeline').hidden; }],
    ['Afficher les paramètres', 'Calques', function () { openView('layers'); }],
    ['Ouvrir le renseignement', 'Renseignement', function () { openView('intel'); }],
    ['Mesurer une distance', 'Carte', function () { setTool('measure'); }],
    ['Calculer un cap', 'Carte', function () { setTool('bearing'); }],
    ['Dessiner un cercle', 'Carte', function () { setTool('circle'); }],
    ['Dessiner un rectangle', 'Carte', function () { setTool('rect'); }],
    ['Croquis à main levée', 'Carte', function () { setTool('freehand'); }],
    ['Poser un texte', 'Carte', function () { setTool('text'); }],
    ['Calculer un ETA', 'Carte', function () { setTool('eta'); }],
    ['Profil d’élévation', 'Carte', function () { setTool('profile'); }],
    ['Ouvrir les notes de terrain', 'Renseignement', function () { window.dispatchEvent(new CustomEvent('overwatch:panel', { detail: { panel: 'osint' } })); }],
    ['Ouvrir le journal', 'Mission', function () { window.dispatchEvent(new CustomEvent('overwatch:panel', { detail: { panel: 'logs' } })); }],
    ['Visée / masque du relief', 'Carte', function () { setTool('los'); }],
    ['Masque de visibilité', 'Carte', function () { setTool('viewshed'); }],
    ['Horizon du relief', 'Carte', function () { setTool('horizon'); }],
    ['Coupe verticale', 'Carte', function () { setTool('slice'); }],
    ['Mesure 3D', 'Carte', function () { setTool('measure3d'); }],
    ['Volume 3D', 'Carte', function () { setTool('volume'); }],
    ['Comparer 2D et 3D', 'Carte', function () { setTool('compare'); }],
    ['Enregistrer une vue caméra', 'Carte', function () { setTool('bookmark'); }],
    ['Poser un point à atteindre', 'Carte', function () { setTool('po'); }],
    ['Poser un point de ralliement', 'Carte', function () { setTool('rally'); }],
    ['Transmettre une tâche de groupe', 'Mission', function () { openSquadTaskForm(''); }],
    ['Envoyer une alerte plein écran', 'Ordre', function () { openFullscreenAlertForm(''); }],
    ['Suivre le contact', 'Contacts', function () { followOn = true; var box = document.getElementById('ow-follow'); if (box) box.checked = true; toast('Suivi activé. Ouvrez un contact.'); }],
    ['Annuler le dernier tracé', 'Carte', function () { undoLastShape(); }]
  ];

  function renderPalette(query) {
    var q = String(query || '').toLowerCase();
    var host = document.getElementById('ow-palette-results');
    host.innerHTML = '';
    commands.filter(function (item) { return item[0].toLowerCase().indexOf(q) !== -1; }).concat(
      units.filter(function (unit) { return callsign(unit).toLowerCase().indexOf(q) !== -1; }).slice(0, 6).map(function (unit) {
        return ['Find ' + callsign(unit), 'UNIT', function () { selectUnit(unit); var loc = point(unit); if (loc) map.setView(loc, Math.max(map.getZoom(), 4)); }];
      })
    ).forEach(function (item, index) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'pitem' + (index === 0 ? ' is-selected' : '');
      button.innerHTML = '<span></span><small></small>';
      button.firstChild.textContent = item[0];
      button.lastChild.textContent = item[1];
      button.addEventListener('click', function () { togglePalette(false); item[2](); });
      host.appendChild(button);
    });
  }

  var palette = document.getElementById('ow-palette');
  function togglePalette(show) {
    palette.hidden = !show;
    if (show) {
      document.getElementById('ow-command-input').value = '';
      renderPalette('');
      window.setTimeout(function () { document.getElementById('ow-command-input').focus(); }, 30);
    }
  }

  function wrapCot(kind, payload) {
    var envelope = { type: kind, how: 'm-g', stale: new Date(Date.now() + 30000).toISOString(), detail: payload };
    window.dispatchEvent(new CustomEvent('overwatch:cot', { detail: envelope }));
    return envelope;
  }

  var DISPLAY_PREFS_KEY = 'atak_map_display_prefs';
  var displayPrefsCache = null;
  function clampPref(n, a, b, d) {
    var v = Number(n);
    if (!isFinite(v)) return d;
    return Math.max(a, Math.min(b, v));
  }
  function normalizeDisplayPrefs(raw) {
    var src = raw && typeof raw === 'object' ? raw : {};
    return {
      styleMode: src.styleMode || 'nato',
      iconSize: clampPref(src.iconSize, 12, 28, 20),
      labelSize: clampPref(src.labelSize, 9, 16, 11),
      showFtFrame: src.showFtFrame !== false,
      markerDepth: src.markerDepth !== false,
      markerMotion: src.markerMotion !== false,
      showIntelPhotoMarkers: src.showIntelPhotoMarkers !== false,
      showUnitTrails: src.showUnitTrails !== false,
      showUnitGhostTrails: !!src.showUnitGhostTrails || !!src.showSseGhostTracks,
      showSseGhostTracks: !!src.showSseGhostTracks || !!src.showUnitGhostTrails,
      showMotionArrows: src.showMotionArrows !== false,
      showMotionProjection: src.showMotionProjection !== false,
      showMotionTrail: src.showMotionTrail !== false,
      showAssignmentLines: src.showAssignmentLines !== false,
      showEtaLabels: !!src.showEtaLabels,
      terrainHillshade: src.terrainHillshade != null ? !!src.terrainHillshade : true,
      terrainContours10: src.terrainContours10 != null ? !!src.terrainContours10 : true,
      terrainContours50: !!src.terrainContours50,
      terrainAltitudes: !!src.terrainAltitudes,
      terrainSlope: !!src.terrainSlope,
      terrainOpacity: clampPref(src.terrainOpacity, 0.05, 1, 0.32),
      terrainSunAzimuth: clampPref(src.terrainSunAzimuth, 0, 360, 315),
      terrainLayer: src.terrainLayer || 'hillshade'
    };
  }
  function getDisplayPrefs() {
    if (displayPrefsCache) return displayPrefsCache;
    try {
      var raw = localStorage.getItem(DISPLAY_PREFS_KEY);
      displayPrefsCache = normalizeDisplayPrefs(raw ? JSON.parse(raw) : null);
    } catch (e) {
      displayPrefsCache = normalizeDisplayPrefs(null);
    }
    return displayPrefsCache;
  }
  function applyDisplayPrefsToOw(prefs) {
    var p = prefs || getDisplayPrefs();
    var mapEl = document.getElementById('ow-map');
    document.documentElement.style.setProperty('--ow-label-size', p.labelSize + 'px');
    document.documentElement.style.setProperty('--ow-icon-size', Math.max(6, Math.round(p.iconSize / 2.5)) + 'px');
    if (mapEl) {
      mapEl.style.setProperty('--atak-unit-label-size', p.labelSize + 'px');
      mapEl.style.setProperty('--atak-unit-icon-size', p.iconSize + 'px');
      mapEl.classList.toggle('atak-map--marker-depth', !!p.markerDepth);
      mapEl.classList.toggle('atak-map--marker-motion', !!p.markerMotion);
    }
    var sizeEl = document.getElementById('ow-label-size');
    if (sizeEl && String(sizeEl.value) !== String(p.labelSize)) sizeEl.value = String(p.labelSize);
    var iconEl = document.getElementById('ow-icon-size');
    if (iconEl && String(iconEl.value) !== String(p.iconSize)) iconEl.value = String(p.iconSize);
    var d = document.getElementById('ow-look-depth');
    var m = document.getElementById('ow-look-motion');
    var f = document.getElementById('ow-look-frame');
    if (d) d.checked = !!p.markerDepth;
    if (m) m.checked = !!p.markerMotion;
    if (f) f.checked = !!p.showFtFrame;
  }
  function patchDisplayPrefs(patch) {
    var next = Object.assign({}, getDisplayPrefs(), patch || {});
    displayPrefsCache = normalizeDisplayPrefs(next);
    try { localStorage.setItem(DISPLAY_PREFS_KEY, JSON.stringify(displayPrefsCache)); } catch (e) {}
    applyDisplayPrefsToOw(displayPrefsCache);
    try { window.dispatchEvent(new CustomEvent('atak:display-prefs-changed', { detail: displayPrefsCache })); } catch (e2) {}
    return displayPrefsCache;
  }

  window.ATAKSocket = { getMapId: function () { return mapId; }, getApiBase: function () { return apiBase; } };
  function applyOffset(latOrY, lngOrX) {
    return [Number(latOrY) + Number(config.offsetY || 0), Number(lngOrX) + Number(config.offsetX || 0)];
  }
  var mapPingMarkers = {};
  function addTemporaryPingMarker(posX, posY, author, message, pingId) {
    var x = Number(posX);
    var y = Number(posY);
    if (!Number.isFinite(x) || !Number.isFinite(y)) return;
    var ll = worldToLatLng(x, y);
    var id = pingId != null && String(pingId) !== '' ? String(pingId) : ('live_' + Date.now());
    if (mapPingMarkers[id]) {
      try { map.removeLayer(mapPingMarkers[id]); } catch (e) {}
    }
    var marker = L.circleMarker(ll, {
      radius: 7,
      color: '#00d69a',
      weight: 2,
      fillColor: '#00d69a',
      fillOpacity: 0.35
    }).addTo(map);
    marker.bindPopup('<strong>' + escapeHtml(author || 'Ping') + '</strong><br/>' + escapeHtml(message || ''));
    mapPingMarkers[id] = marker;
  }
  function setPingsOnMap(list) {
    var seen = {};
    (Array.isArray(list) ? list : []).forEach(function (p) {
      if (!p || p.pos_x == null || p.pos_y == null) return;
      var id = p.id != null ? String(p.id) : ('p_' + p.pos_x + '_' + p.pos_y);
      seen[id] = true;
      addTemporaryPingMarker(p.pos_x, p.pos_y, p.author, p.message, id);
    });
    Object.keys(mapPingMarkers).forEach(function (id) {
      if (!seen[id] && id.indexOf('live_') !== 0) {
        try { map.removeLayer(mapPingMarkers[id]); } catch (e) {}
        delete mapPingMarkers[id];
      }
    });
  }
  window.ATAKMap = {
    getMap: function () { return map; },
    getBaseTileLayer: function () { return baseTileLayer; },
    worldFromLatLng: latLngToWorld,
    latLngFromWorld: function (x, y) { return worldToLatLng(x, y); },
    applyOffset: applyOffset,
    invalidateSize: function () { try { map.invalidateSize({ animate: false }); } catch (e) {} },
    getDisplayPrefs: getDisplayPrefs,
    patchDisplayPrefs: patchDisplayPrefs,
    setGpsVehiclesOnMap: renderGpsVehiclesOnMap,
    setAirAssets: function (rows) {
      renderAirAssetsOnMap(rows);
      try { window.dispatchEvent(new CustomEvent('atak:air-markers-updated', { detail: { assets: airAssets } })); } catch (e) {}
    },
    setUnitsMarkers: function (list) {
      applyPayload(Array.isArray(list) ? { units: list } : list);
    },
    setPingsOnMap: setPingsOnMap,
    addTemporaryPingMarker: addTemporaryPingMarker
  };
  window.ATAKAirAssets = {
    getAssets: function () { return airAssets; }
  };
  window.ATAKUnits = {
    getUnits: function () { return units; },
    setUnits: function (rows) { applyPayload(rows); wrapCot('a-f-G-U-C', { count: (rows || []).length }); },
    parseCoords: function (u) {
      var x = u && u.pos_x != null && u.pos_x !== '' ? parseFloat(u.pos_x) : NaN;
      var y = u && u.pos_y != null && u.pos_y !== '' ? parseFloat(u.pos_y) : NaN;
      if (isNaN(x) || isNaN(y)) {
        var parts = String((u && u.grid_ref) || '').trim().split(/\s+/);
        if (parts.length >= 2) { x = parseFloat(parts[0]); y = parseFloat(parts[1]); }
      }
      return { x: x, y: y };
    },
    unitAgeSeconds: function (u) { return unitAgeSec(u); },
    resolveLiveStatus: function (u) {
      if (isDisconnected(u)) return 'offline';
      var st = String((u && u.status) || '').toLowerCase();
      return st || 'linked';
    },
    formatAgeFr: function (sec) {
      if (!Number.isFinite(sec)) return '';
      if (sec < 5) return 'à l’instant';
      if (sec < 60) return Math.round(sec) + ' s';
      if (sec < 3600) return Math.round(sec / 60) + ' min';
      return Math.round(sec / 3600) + ' h';
    },
    formatGrid: function (u) {
      var loc = point(u);
      if (!loc) return String((u && (u.grid_ref || u.grid)) || '').trim();
      var w = latLngToWorld(loc);
      return Math.round(w.x) + ' / ' + Math.round(w.y);
    },
    getUnitByKey: function (key) {
      var k = String(key || '');
      for (var i = 0; i < units.length; i++) {
        var u = units[i];
        var idKey = u && u.id != null ? 'id:' + String(u.id) : '';
        var csKey = 'cs:' + callsign(u).toUpperCase();
        if (k === idKey || k === csKey) return u;
      }
      return null;
    },
    getUnitById: function (id) {
      var want = String(id || '');
      for (var i = 0; i < units.length; i++) {
        if (String(units[i].id || '') === want) return units[i];
      }
      return null;
    }
  };
  window.ATAKChat = {
    getCachedMessages: function () { return chatMessages.concat(supportMessages); },
    appendMessage: function (row) { appendChatMessage(row); }
  };
  window.ATAKMapShapes = {
    getShapes: function () { return shapes; },
    createShape: function (payload) {
      return api('/api/map-shapes', { method: 'POST', body: Object.assign({ mapId: mapId, createdBy: authorName }, payload) })
        .then(function (row) { if (row) { shapes.push(row); drawShape(row); } return row; });
    }
  };
  window.ATAKPolling = {
    setRealtimeActive: function (active) {
      realtimeOn = !!active;
      startPoll(active ? 30000 : pollMs);
      syncStatus(true);
    }
  };
  window.ATAKShowNotification = toast;
  window.ATAKShowError = toast;

  function syncTrailPrefsFromUi() {
    var trails = document.getElementById('atak-unit-trails');
    var ghost = document.getElementById('atak-ghost-trails');
    patchDisplayPrefs({
      showUnitTrails: !trails || trails.checked,
      showUnitGhostTrails: !!(ghost && ghost.checked),
      showSseGhostTracks: !!(ghost && ghost.checked)
    });
  }
  ['atak-unit-trails', 'atak-ghost-trails'].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', syncTrailPrefsFromUi);
  });
  syncTrailPrefsFromUi();

  var REACH_KEY = 'athena:overwatch-reach-zone';
  var reachBox = document.getElementById('ow-reach-zone');
  if (reachBox) {
    try {
      var savedReach = localStorage.getItem(REACH_KEY);
      // Désactivé par défaut ; on ne réactive que si l’opérateur l’a explicitement coché.
      reachBox.checked = savedReach === '1';
    } catch (eReach) {
      reachBox.checked = false;
    }
    reachBox.addEventListener('change', function () {
      try { localStorage.setItem(REACH_KEY, reachBox.checked ? '1' : '0'); } catch (eSave) {}
      if (reachBox.checked && selected) syncReachOverlay(selected);
      else syncReachOverlay(null);
      renderRangeRings();
    });
    if (!reachBox.checked) syncReachOverlay(null);
  }
  try {
    window.dispatchEvent(new CustomEvent('atak:mapready', { detail: { map: map } }));
  } catch (eMap) {}
  window.setTimeout(function () {
    try {
      window.dispatchEvent(new CustomEvent('atak:mapready', { detail: { map: map } }));
    } catch (eMap2) {}
    if (window.ATAKSseLayers && typeof window.ATAKSseLayers.startPolling === 'function') {
      try { window.ATAKSseLayers.startPolling(); } catch (eSse) {}
    }
    if (window.ATAKPings && typeof window.ATAKPings.fetchPings === 'function') {
      try { window.ATAKPings.fetchPings(); } catch (ePing2) {}
    }
  }, 600);
  if (window.ATAKPings && typeof window.ATAKPings.fetchPings === 'function') {
    try { window.ATAKPings.fetchPings(); } catch (ePing) {}
  }

  function startPoll(ms) {
    if (pollTimer) window.clearInterval(pollTimer);
    pollTimer = window.setInterval(function () {
      refreshUnits();
      loadChatInbox();
      var squadsPanel = document.querySelector('[data-chat-panel="squads"]');
      var drawerTitle = document.getElementById('ow-drawer-title');
      var drawer = document.getElementById('ow-drawer');
      var squadsOpen = squadsPanel && !squadsPanel.hidden;
      var missionOpen = drawer && !drawer.hidden && drawerTitle && drawerTitle.textContent === 'Mission';
      var airOpen = drawer && !drawer.hidden && drawerTitle && drawerTitle.textContent === 'Air';
      if (squadsOpen || missionOpen) loadGroupTasks();
      if (airOpen) {
        loadAirAssets();
        loadNine();
        loadCas();
      }
    }, Math.max(3000, Number(ms) || 5000));
  }

  function refreshAll() {
    refreshUnits();
    loadChannels();
    loadChat(activeChannel);
    loadChatInbox();
    loadShapes();
    loadPoMarkers();
    loadRallyPoints();
    loadGroupTasks();
    loadAlerts();
    loadPhotos();
    loadAirAssets();
    loadNine();
    loadCas();
    loadWeather().then(function (payload) {
      var w = payload && payload.weather ? payload.weather : payload;
      var chip = document.getElementById('ow-weather-chip');
      if (!chip) return;
      if (!w || (!w.condition && w.temperature_c == null && w.wind_kph == null)) {
        chip.textContent = 'Météo —';
        return;
      }
      var bits = [];
      if (w.condition) bits.push(w.condition);
      if (w.temperature_c != null && w.temperature_c !== '') bits.push(w.temperature_c + ' °C');
      if (w.wind_kph != null && w.wind_kph !== '') bits.push('vent ' + w.wind_kph + ' km/h');
        chip.textContent = bits.length ? bits.join(' · ') : 'Météo —';
      window.dispatchEvent(new CustomEvent('overwatch:weather', { detail: w }));
    });
  }

  document.getElementById('ow-contact-list').addEventListener('click', function (event) {
    var sect = event.target.closest('[data-bft-sect]');
    if (sect) {
      var kind = sect.getAttribute('data-bft-sect');
      bftSectState[kind] = !bftSectState[kind];
      var box = sect.closest('.ow-bft-sect');
      if (box) box.classList.toggle('is-collapsed', !!bftSectState[kind]);
      return;
    }
    var locBtn = event.target.closest('[data-bft-locate]');
    var row = event.target.closest('[data-unit-id]');
    if (!row) return;
    var unit = units.find(function (item) { return unitId(item) === row.dataset.unitId; });
    if (!unit) return;
    selectUnit(unit);
    var location = point(unit);
    if (location) map.panTo(location);
    if (locBtn || isDisconnected(unit)) {
      followOn = false;
      var followBox = document.getElementById('ow-follow');
      if (followBox) { followBox.checked = false; followBox.dispatchEvent(new Event('change')); }
      renderList();
      if (locBtn) toast(location ? 'Dernière position connue.' : 'Aucune position enregistrée.');
      return;
    }
    followOn = true;
    var box = document.getElementById('ow-follow');
    if (box) { box.checked = true; box.dispatchEvent(new Event('change')); }
    renderList();
  });
  document.getElementById('ow-contact-list').addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    var row = event.target.closest('.ow-bft-contact[data-unit-id]');
    if (!row) return;
    event.preventDefault();
    row.click();
  });
  document.getElementById('ow-search').addEventListener('input', renderList);
  var sideFilter = document.getElementById('ow-side-filter');
  if (sideFilter) sideFilter.addEventListener('change', renderList);
  var waveFilterBtn = document.getElementById('ow-filter-wave');
  if (waveFilterBtn) {
    waveFilterBtn.addEventListener('click', function () {
      filterWave = !filterWave;
      renderList();
    });
  }
  var effectifsBody = document.getElementById('ow-units-table-body');
  if (effectifsBody) {
    effectifsBody.addEventListener('click', function (event) {
      var row = event.target.closest('tr[data-unit-id]');
      if (!row) return;
      var id = row.getAttribute('data-unit-id');
      var unit = units.filter(function (u) { return unitId(u) === id; })[0];
      if (unit) selectUnit(unit);
    });
  }
  document.getElementById('ow-channel-filter').addEventListener('input', renderChannels);
  var commsSearch = document.getElementById('ow-comms-search');
  if (commsSearch) commsSearch.addEventListener('input', function () { renderChatLog('ow-chat-log', chatMessages); });
  document.getElementById('ow-channel-list').addEventListener('click', function (event) {
    var row = event.target.closest('[data-channel]'); if (!row) return;
    activeChannel = row.dataset.channel;
    unreadByChannel[activeChannel] = 0;
    paintUnreadBadges();
    loadChat(activeChannel);
  });
  document.querySelector('[data-close-drawer]').addEventListener('click', function () {
    restoreOpsPanels();
    document.getElementById('ow-drawer').hidden = true;
    lastSceneObject = null;
    if (window.OverwatchGlLayers && typeof window.OverwatchGlLayers.setFocus === 'function') {
      window.OverwatchGlLayers.setFocus('');
    }
    openView('overwatch');
  });
  document.getElementById('ow-drawer').addEventListener('click', function (event) {
    var sceneBtn = event.target.closest('[data-scene-act]');
    if (sceneBtn && lastSceneObject && lastSceneObject.ll) {
      var act = sceneBtn.getAttribute('data-scene-act');
      var loc = lastSceneObject.ll;
      var obj = lastSceneObject.object || {};
      if (act === 'marker') saveMarker(loc, obj.name || 'Marqueur');
      if (act === 'po') { placeReachPoint(loc); finishPoSession(); }
      if (act === 'entry') saveMarker(loc, 'Entrée');
      if (act === 'photo') {
        if (window.OverwatchOps && typeof window.OverwatchOps.openIntel === 'function') window.OverwatchOps.openIntel(loc);
        else openView('intel');
      }
      if (act === 'task') {
        var grid = gridLabel(loc);
        openDrawer('Tâche', obj.name || 'Objectif', groupTaskFormHtml() +
          '<p class="ow-help">Point : ' + escapeHtml(grid) + '. Indiquez le groupe puis transmettez.</p>');
        fillGroupTaskSelects(true);
        bindGroupTaskForms(document.getElementById('ow-drawer'));
        bindDrawerForms();
        var ta = document.querySelector('#ow-drawer textarea[name="payload"]');
        if (ta && !ta.value) ta.value = 'Objectif : ' + (obj.name || 'construction') + ' · ' + grid;
      }
      if (act === 'plan') {
        if (window.OverwatchTacmap && typeof window.OverwatchTacmap.attachSceneBuilding === 'function') {
          window.OverwatchTacmap.attachSceneBuilding(obj, loc);
        }
      }
      if (act === 'floor' || act === 'roof' || act === 'anchor') {
        var floor = act === 'roof' ? (Number(obj.floors) || 1) : Number(sceneBtn.getAttribute('data-floor'));
        var note = act === 'anchor' ? (window.prompt('Libellé de l’ancrage', 'Façade nord') || '') : '';
        if (act === 'anchor' && !note.trim()) return;
        api('/api/atak/scene/anchor', {
          method: 'POST',
          body: {
            mapId: mapId,
            id: lastSceneObject.id,
            floor: isFinite(floor) ? floor : 0,
            set_floor: act !== 'anchor',
            face: act === 'roof' ? 'roof' : '',
            kind: act === 'anchor' ? 'note' : 'note',
            label: note || (act === 'roof' ? 'Toit' : ('Niveau ' + floor))
          }
        }).then(function () {
          toast(act === 'roof' ? 'Toit sélectionné.' : (act === 'anchor' ? 'Ancrage enregistré.' : 'Étage retenu.'));
          inspectSceneObject(lastSceneObject.id, lastSceneObject.ll, lastSceneObject.object);
        }).catch(function () { toast('Impossible d’enregistrer.'); });
      }
      return;
    }
    if (event.target.matches('[data-center-selected]')) {
      var location = selected && point(selected);
      if (location) map.setView(location, Math.max(map.getZoom(), 4));
    }
    if (event.target.matches('[data-fit-squad]') && selected) fitSquad(selected);
    if (event.target.matches('[data-squad-task-from-unit]') && selected) {
      var unitKey = squadKey(selected);
      if (!unitKey) { toast('Cet opérateur n’appartient à aucun groupe.'); return; }
      openSquadTaskForm(unitKey);
      toast('Complétez la tâche pour ' + group(selected) + '.');
    }
    handleGroupTaskClick(event);
    if (event.target.matches('[data-follow-selected]')) {
      followOn = !followOn;
      var box = document.getElementById('ow-follow');
      if (box) box.checked = followOn;
      toast(followOn ? 'Suivi du contact activé.' : 'Suivi arrêté.');
      if (selected) selectUnit(selected);
    }
    var mate = event.target.closest('[data-unit-id]');
    if (mate && document.getElementById('ow-drawer').contains(mate)) {
      var other = units.find(function (item) { return unitId(item) === mate.dataset.unitId; });
      if (other) { selectUnit(other); var loc = point(other); if (loc) map.panTo(loc); }
    }
  });
  var squadList = document.getElementById('ow-squad-list');
  if (squadList) squadList.addEventListener('click', function (event) {
    var taskBtn = event.target.closest('[data-squad-task]');
    if (taskBtn) {
      event.preventDefault();
      event.stopPropagation();
      openSquadTaskForm(taskBtn.getAttribute('data-squad-task'));
      toast('Choisissez la tâche, puis transmettez.');
      return;
    }
    var row = event.target.closest('[data-squad-key]'); if (!row) return;
    var key = row.getAttribute('data-squad-key');
    var first = units.find(function (unit) { return (squadKey(unit) || 'none') === key; });
    if (first) { selectUnit(first); if (key !== 'none') fitSquad(first); }
  });
  function handleGroupTaskClick(event) {
    var btn = event.target.closest('[data-cancel-task]');
    if (!btn) return;
    event.preventDefault();
    var id = btn.getAttribute('data-cancel-task');
    api('/api/atak/orders/' + encodeURIComponent(id) + '/status', {
      method: 'POST',
      body: { mapId: mapId, status: 'CANCELLED', by: authorName }
    }).then(function () {
      toast('Tâche annulée.');
      loadGroupTasks();
    }).catch(function () { toast('Impossible d’annuler cette tâche.'); });
  }
  document.getElementById('ow-chat').addEventListener('click', handleGroupTaskClick);
  document.getElementById('ow-chat').addEventListener('click', function (event) {
    var del = event.target.closest('[data-del-chat]');
    if (!del) return;
    event.preventDefault();
    deleteOwnChat(del.getAttribute('data-del-chat'));
  });
  var purgeBtn = document.getElementById('ow-chat-purge');
  if (purgeBtn) purgeBtn.addEventListener('click', showChatPurgeConfirm);
  document.querySelectorAll('[data-tool]').forEach(function (button) {
    button.addEventListener('click', function () { setTool(button.dataset.tool); });
  });
  document.querySelectorAll('[data-view]').forEach(function (button) {
    button.addEventListener('click', function () { openView(button.dataset.view); });
  });
  var airDrawer = document.getElementById('ow-drawer');
  if (airDrawer) {
    airDrawer.addEventListener('click', function (event) {
      var locate = event.target.closest('[data-air-locate]');
      if (!locate) return;
      var cs = locate.getAttribute('data-air-locate');
      var row = airAssets.filter(function (item) { return String(item.callsign || '') === cs; })[0] || { callsign: cs };
      airLocate(row);
    });
  }
  document.querySelectorAll('[data-chat-tab]').forEach(function (button) {
    button.addEventListener('click', function () { switchChatTab(button.dataset.chatTab); });
  });
  document.getElementById('ow-chat-form').addEventListener('submit', function (event) {
    event.preventDefault();
    var input = document.getElementById('ow-chat-input');
    if (!input) return;
    sendChat(activeChannel, input.value).then(function () {
      input.value = '';
    }).catch(function () {
      toast('Message non envoyé.');
    });
  });
  document.getElementById('ow-support-form').addEventListener('submit', function (event) {
    event.preventDefault();
    var input = document.getElementById('ow-support-input');
    sendChat('support', input.value).then(function () { input.value = ''; });
  });
  document.querySelectorAll('[data-command]').forEach(function (button) {
    button.addEventListener('click', function () { togglePalette(true); });
  });
  palette.addEventListener('click', function (event) { if (event.target === palette) togglePalette(false); });
  document.getElementById('ow-command-input').addEventListener('input', function () { renderPalette(this.value); });
  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-ow-replay]')) return;
    document.getElementById('ow-timeline').hidden = !document.getElementById('ow-timeline').hidden;
  });
  document.addEventListener('keydown', function (event) {
    var tag = String((event.target && event.target.tagName) || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); togglePalette(palette.hidden); }
    if (event.key === 'Escape') { togglePalette(false); hideContext(); document.getElementById('ow-drawer').hidden = true; setTool('cursor'); }
    if ((event.key === 'Delete' || event.key === 'Backspace') && !document.getElementById('ow-context').hidden && ctxTarget) {
      event.preventDefault();
      var toDrop = ctxTarget;
      hideContext();
      deleteMapTarget(toDrop);
      return;
    }
    if (event.key === 'm' || event.key === 'M') setTool('measure');
    if (event.key === 'b' || event.key === 'B') setTool('bearing');
    if (event.key === 'v' || event.key === 'V') setTool('los');
    if (event.key === 'p' || event.key === 'P') setTool('po');
    if (event.key === 'r' || event.key === 'R') setTool('rally');
    if (event.key === 'o' || event.key === 'O') setTool('circle');
    if (event.key === 'u' || event.key === 'U') undoLastShape();
    if (event.key === 'f' || event.key === 'F') {
      followOn = !followOn;
      var box = document.getElementById('ow-follow');
      if (box) box.checked = followOn;
      toast(followOn ? 'Suivi du contact activé.' : 'Suivi arrêté.');
    }
  });
  map.on('mousemove', function (event) {
    var extra = '';
    if (draftPoints.length && (activeTool === 'measure' || activeTool === 'bearing' || activeTool === 'circle' || activeTool === 'los' || activeTool === 'eta')) {
      var meters = map.distance(draftPoints[0], event.latlng);
      extra = ' · ' + formatMeters(meters) + ' · ' + Math.round(bearingWorld(draftPoints[0], event.latlng)) + '°';
    }
    document.getElementById('ow-coordinate').textContent = gridLabel(event.latlng) + extra + ' · Direct';
    var live = document.getElementById('ow-live-measure');
    if (live) {
      if (extra) { live.hidden = false; live.textContent = extra.replace(' · ', ''); }
      else live.hidden = true;
    }
  });

  var rate = document.getElementById('ow-refresh-rate');
  try {
    var storedPoll = Number(localStorage.getItem(POLL_KEY) || 5000);
    if ([3000, 8000, 15000, 30000].indexOf(storedPoll) !== -1) { pollMs = storedPoll; rate.value = String(storedPoll); }
  } catch (e) {}
  rate.addEventListener('change', function () {
    pollMs = Number(rate.value) || 5000;
    try { localStorage.setItem(POLL_KEY, String(pollMs)); } catch (e2) {}
    if (!realtimeOn) startPoll(pollMs);
  });

  var theme = document.getElementById('ow-theme');
  try { theme.value = localStorage.getItem(THEME_KEY) || 'night'; } catch (e) {}
  function applyTheme() {
    document.body.classList.toggle('ow-theme-day', theme.value === 'day');
    try { localStorage.setItem(THEME_KEY, theme.value); } catch (e) {}
  }
  theme.addEventListener('change', applyTheme);
  applyTheme();

  function persistMarkerPrefs() {
    try {
      localStorage.setItem(STYLE_KEY, (document.getElementById('ow-marker-style') || {}).value || 'diamond');
      localStorage.setItem(COLOR_FRIEND_KEY, (document.getElementById('ow-color-friend') || {}).value || '#00d69a');
      localStorage.setItem(COLOR_HOSTILE_KEY, (document.getElementById('ow-color-hostile') || {}).value || '#e05b63');
      localStorage.setItem(DRAW_COLOR_KEY, (document.getElementById('ow-draw-color') || {}).value || '#00d69a');
      localStorage.setItem(DRAW_WIDTH_KEY, (document.getElementById('ow-draw-width') || {}).value || '2');
    } catch (e) {}
    renderMap();
  }
  try {
    var storedStyle = localStorage.getItem(STYLE_KEY);
    var styleEl = document.getElementById('ow-marker-style');
    if (styleEl && storedStyle) styleEl.value = storedStyle;
    var cf = document.getElementById('ow-color-friend');
    var ch = document.getElementById('ow-color-hostile');
    if (cf && localStorage.getItem(COLOR_FRIEND_KEY)) cf.value = localStorage.getItem(COLOR_FRIEND_KEY);
    if (ch && localStorage.getItem(COLOR_HOSTILE_KEY)) ch.value = localStorage.getItem(COLOR_HOSTILE_KEY);
    var dc = document.getElementById('ow-draw-color');
    var dw = document.getElementById('ow-draw-width');
    if (dc && localStorage.getItem(DRAW_COLOR_KEY)) dc.value = localStorage.getItem(DRAW_COLOR_KEY);
    if (dw && localStorage.getItem(DRAW_WIDTH_KEY)) dw.value = localStorage.getItem(DRAW_WIDTH_KEY);
  } catch (e) {}
  ['ow-marker-style', 'ow-color-friend', 'ow-color-hostile', 'ow-draw-color', 'ow-draw-width'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', persistMarkerPrefs);
  });
  ['ow-squad-links', 'ow-squad-hull', 'ow-show-labels', 'ow-squad-color', 'ow-squad-dist', 'ow-po-markers', 'ow-rally-markers'].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    try {
      var stored = localStorage.getItem('athena:overwatch-' + id);
      if (stored === '0') el.checked = false;
      if (stored === '1') el.checked = true;
    } catch (e) {}
    el.addEventListener('change', function () {
      try { localStorage.setItem('athena:overwatch-' + id, el.checked ? '1' : '0'); } catch (e2) {}
      renderMap();
    });
  });
  var labelSize = document.getElementById('ow-label-size');
  if (labelSize) {
    try {
      var storedSize = localStorage.getItem('athena:overwatch-label-size');
      if (storedSize) labelSize.value = storedSize;
    } catch (e) {}
    function applyLabelSize() {
      document.documentElement.style.setProperty('--ow-label-size', labelSize.value + 'px');
      try { localStorage.setItem('athena:overwatch-label-size', labelSize.value); } catch (e2) {}
      patchDisplayPrefs({ labelSize: Number(labelSize.value) || 12 });
    }
    labelSize.addEventListener('input', applyLabelSize);
    applyLabelSize();
  }

  function applyAsideWidths() {
    var left = document.getElementById('ow-aside-left');
    var right = document.getElementById('ow-aside-right');
    var lv = left ? Number(left.value) : 300;
    var rv = right ? Number(right.value) : 300;
    document.documentElement.style.setProperty('--ow-aside-left', lv + 'px');
    document.documentElement.style.setProperty('--ow-aside-right', rv + 'px');
    try { localStorage.setItem(ASIDE_KEY, JSON.stringify({ left: lv, right: rv })); } catch (e) {}
    map.invalidateSize({ animate: false });
  }
  try {
    var storedAside = JSON.parse(localStorage.getItem(ASIDE_KEY) || 'null');
    if (storedAside && storedAside.left) {
      var leftEl = document.getElementById('ow-aside-left');
      var rightEl = document.getElementById('ow-aside-right');
      if (leftEl) leftEl.value = String(storedAside.left);
      if (rightEl) rightEl.value = String(storedAside.right);
    }
  } catch (e) {}
  ['ow-aside-left', 'ow-aside-right'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', applyAsideWidths);
  });
  applyAsideWidths();

  var followBox = document.getElementById('ow-follow');
  if (followBox) {
    try { if (localStorage.getItem('athena:overwatch-ow-follow') === '1') { followBox.checked = true; followOn = true; } } catch (e) {}
    followBox.addEventListener('change', function () {
      followOn = followBox.checked;
      try { localStorage.setItem('athena:overwatch-ow-follow', followOn ? '1' : '0'); } catch (e2) {}
      toast(followOn ? 'Suivi du contact activé.' : 'Suivi arrêté.');
      if (window.OverwatchOps && window.OverwatchOps.afterRenderMap) window.OverwatchOps.afterRenderMap();
    });
  }
  document.querySelectorAll('[data-ow-ring]').forEach(function (input) {
    input.addEventListener('change', renderRangeRings);
  });
  document.querySelectorAll('[data-ow-compact]').forEach(function (button) {
    button.addEventListener('click', function () {
      var workspace = document.querySelector('.ow-workspace');
      workspace.classList.toggle('is-compact');
      button.classList.toggle('is-active', workspace.classList.contains('is-compact'));
      map.invalidateSize({ animate: false });
    });
  });

  function applySettingsCollapsed(on) {
    var workspace = document.querySelector('.ow-workspace');
    workspace.classList.toggle('is-settings-collapsed', !!on);
    var btn = document.querySelector('[data-ow-collapse-settings]');
    if (btn) {
      btn.textContent = on ? '›' : '‹';
      btn.setAttribute('aria-expanded', on ? 'false' : 'true');
      btn.title = on ? 'Ouvrir les réglages' : 'Rabattre les réglages';
    }
    try { localStorage.setItem(COLLAPSE_KEY, on ? '1' : '0'); } catch (e) {}
    map.invalidateSize({ animate: false });
  }
  try { if (localStorage.getItem(COLLAPSE_KEY) === '1') applySettingsCollapsed(true); } catch (e) {}
  var collapseBtn = document.querySelector('[data-ow-collapse-settings]');
  if (collapseBtn) collapseBtn.addEventListener('click', function () {
    applySettingsCollapsed(!document.querySelector('.ow-workspace').classList.contains('is-settings-collapsed'));
  });

  var moreBtn = document.querySelector('[data-ow-more]');
  var moreMenu = document.getElementById('ow-more-menu');
  if (moreBtn && moreMenu) {
    moreBtn.addEventListener('click', function (event) {
      event.stopPropagation();
      moreMenu.hidden = !moreMenu.hidden;
    });
  }

  var railMore = document.querySelector('[data-ow-rail-more]');
  var railExtra = document.getElementById('ow-rail-extra');
  if (railMore && railExtra) {
    railMore.addEventListener('click', function (event) {
      event.stopPropagation();
      var open = railExtra.hidden;
      railExtra.hidden = !open;
      railMore.setAttribute('aria-expanded', open ? 'true' : 'false');
      railMore.classList.toggle('is-open', open);
      railMore.textContent = open ? '‹' : '›';
    });
  }
  document.addEventListener('click', function (event) {
    if (moreMenu && !event.target.closest('[data-ow-more]')) moreMenu.hidden = true;
    if (railExtra && railMore && !event.target.closest('.ow-rail')) {
      if (!railExtra.querySelector('[data-tool].is-active')) {
        railExtra.hidden = true;
        railMore.setAttribute('aria-expanded', 'false');
        railMore.classList.remove('is-open');
        railMore.textContent = '›';
      }
    }
  });

  function openGuide(on) {
    var g = document.getElementById('ow-guide');
    if (g) g.hidden = !on;
  }
  document.querySelectorAll('[data-ow-help]').forEach(function (btn) {
    btn.addEventListener('click', function () { openGuide(true); });
  });
  var guideOk = document.getElementById('ow-guide-ok');
  if (guideOk) guideOk.addEventListener('click', function () { openGuide(false); });
  var guide = document.getElementById('ow-guide');
  if (guide) guide.addEventListener('click', function (event) { if (event.target === guide) openGuide(false); });

  var rawToggle = document.getElementById('ow-chat-raw');
  if (rawToggle) rawToggle.addEventListener('change', function () {
    var fil = document.getElementById('ow-chat-log');
    if (fil) fil.classList.toggle('is-raw', rawToggle.checked);
  });

  function bindDrawTint() {
    var tac = document.getElementById('ow-tac-color');
    var main = document.getElementById('ow-draw-color');
    var tw = document.getElementById('ow-tac-width');
    var mw = document.getElementById('ow-draw-width');
    function syncColor(from, to) {
      if (from && to && to.value !== from.value) to.value = from.value;
    }
    if (tac && main) {
      if (main.value) tac.value = main.value;
      tac.addEventListener('input', function () { syncColor(tac, main); });
      main.addEventListener('input', function () { syncColor(main, tac); });
    }
    if (tw && mw) {
      tw.value = mw.value || tw.value;
      tw.addEventListener('input', function () { mw.value = tw.value; });
      mw.addEventListener('input', function () { tw.value = mw.value; });
    }
    document.querySelectorAll('[data-tint]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setDrawTint(btn.getAttribute('data-tint'));
      });
    });
  }
  bindDrawTint();

  var heatBox = document.getElementById('ow-presence-heat');
  if (heatBox) heatBox.addEventListener('change', renderPresenceHeat);
  var armaBox = document.getElementById('ow-arma-markers');
  if (armaBox) armaBox.addEventListener('change', renderArmaMarkers);
  var squadW = document.getElementById('ow-squad-width');
  if (squadW) squadW.addEventListener('input', renderSquadLinks);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      if (!document.getElementById('ow-guide').hidden) { openGuide(false); return; }
      if (!document.getElementById('ow-context').hidden) { hideContext(); return; }
      if (activeTool !== 'cursor') setTool('cursor');
    }
  });

  document.addEventListener('change', function (event) {
    var sel = event.target && event.target.matches && event.target.matches('select[name="priority"]') ? event.target : null;
    if (!sel) return;
    var wrap = sel.closest('.ow-select--prio');
    var dot = wrap && wrap.querySelector('[data-prio-dot]');
    if (!dot) return;
    var v = sel.value;
    dot.style.background = v === 'ROUTINE' ? 'var(--prio-routine)' : (v === 'URGENT' || v === 'CONTACT' ? 'var(--prio-flash)' : 'var(--prio-priority)');
  });

  window.OverwatchBeta = {
    api: api,
    map: map,
    mapId: mapId,
    authorName: authorName,
    toast: toast,
    openDrawer: openDrawer,
    openView: openView,
    setTool: setTool,
    saveShape: saveShape,
    drawShape: drawShape,
    clearDraft: clearDraft,
    drawStyle: drawStyle,
    setDrawTint: setDrawTint,
    undoLastShape: undoLastShape,
    redoLastShape: redoLastShape,
    parseShapeMeta: parseShapeMeta,
    getChatMessages: function () { return chatMessages; },
    getActiveChannel: function () { return activeChannel; },
    worldToLatLng: worldToLatLng,
    latLngToWorld: latLngToWorld,
    gridLabel: gridLabel,
    asList: asList,
    wrapCot: wrapCot,
    escapeHtml: escapeHtml,
    clean: clean,
    csrf: csrfToken,
    commands: commands,
    getUnits: function () { return units; },
    getShapes: function () { return shapes; },
    getShapeLayers: function () { return shapeLayers; },
    getTrackSamples: function () { return trackSamples; },
    getMarkers: function () { return markers; },
    point: point,
    unitId: unitId,
    callsign: callsign,
    group: group,
    squadKey: squadKey,
    fitSquad: fitSquad,
    renderMap: renderMap,
    renderSquadLinks: renderSquadLinks,
    showCalcDrawer: showCalcDrawer,
    getSelected: function () { return selected; },
    selectUnit: selectUnit,
    inspectWorld: inspectWorld,
    inspectSceneObject: inspectSceneObject,
    hitSceneAt: hitSceneAt,
    getSceneBuildings: function () {
      var rows = sceneCache.length ? sceneCache : sceneRows;
      return rows.filter(function (item) {
        return String(item.kind || 'building') === 'building';
      });
    },
    getArmaMarkerRows: function () { return armaMarkerRows; },
    parseMarkerData: parseMarkerData,
    markerWorld: markerWorld,
    handleWorldClick: handleWorldClick,
    handleWorldContext: handleWorldContext,
    openContextAt: openContextAt,
    pickUnitAt: pickUnitAt,
    renderPresenceHeat: renderPresenceHeat,
    getPhotos: function () { return photos; },
    getAirAssets: function () { return airAssets; },
    applyLook: applyLook,
    formatMeters: formatMeters,
    loadShapes: loadShapes,
    loadArmaMarkers: loadArmaMarkers,
    bindLayerContext: bindLayerContext,
    registerScratch: registerScratch,
    registerPing: registerPing,
    getShapes: function () { return shapes; },
    getShapeLayers: function () { return shapeLayers; },
    unitHeading: unitHeading,
    unitSpeed: unitSpeed,
    unitAgeSec: unitAgeSec,
    DELAYED_SEC: DELAYED_SEC,
    LIVE_TTL_SEC: LIVE_TTL_SEC,
    getPollMs: function () { return pollMs; },
    isTrackedAi: isTrackedAi,
    unitWorld: unitWorld,
    side: side,
    getPoRows: function () { return poRows; },
    getRallyRows: function () { return rallyRows; },
    getLastRx: function () { return lastRx; },
    getLastClickWorld: function () { return lastClickWorld; },
    getLastClickGrid: function () { return lastClickGrid; }
  };
  applyDisplayPrefsToOw();

  var disclaimer = document.getElementById('ow-disclaimer');
  var skip = false;
  try { skip = localStorage.getItem(DISCLAIMER_KEY) === '1'; } catch (e) {}
  if (!skip) disclaimer.hidden = false;
  document.getElementById('ow-disclaimer-ok').addEventListener('click', function () {
    if (document.getElementById('ow-disclaimer-hide').checked) {
      try { localStorage.setItem(DISCLAIMER_KEY, '1'); } catch (e) {}
    }
    disclaimer.hidden = true;
    window.setTimeout(function () { map.invalidateSize({ animate: false }); }, 80);
  });

  ['ow-empty-close', 'ow-empty-dismiss'].forEach(function (id) {
    var btn = document.getElementById(id);
    if (btn) btn.addEventListener('click', dismissEmptyNotice);
  });

  // Bouton repli des réglages
  var toggleSettingsBtn = document.getElementById('ow-toggle-settings');
  if (toggleSettingsBtn) {
    toggleSettingsBtn.addEventListener('click', toggleSettingsAside);
  }

  restoreSettingsCollapsed();

  // Gestion des calques (layers)
  document.querySelectorAll('[data-ow-layer]').forEach(function(checkbox) {
    // Restaurer l'état sauvegardé
    var layer = checkbox.getAttribute('data-ow-layer');
    try {
      var saved = localStorage.getItem('athena:ow-layer-' + layer);
      if (saved !== null) {
        checkbox.checked = saved === '1';
      }
    } catch(e) {}
    
    // Event listener pour changements
    checkbox.addEventListener('change', function() {
      var visible = checkbox.checked;
      
      switch(layer) {
        case 'units':
          hiddenLayers.units = !visible;
          renderMap();
          break;
        case 'labels':
          document.body.classList.toggle('ow-labels-hidden', !visible);
          break;
        case 'shapes':
          hiddenLayers.shapes = !visible;
          Object.keys(shapeLayers).forEach(function(id) {
            var shapeLayer = shapeLayers[id];
            if (visible) {
              if (!map.hasLayer(shapeLayer)) map.addLayer(shapeLayer);
            } else {
              if (map.hasLayer(shapeLayer)) map.removeLayer(shapeLayer);
            }
          });
          break;
        case 'aerial-view':
          applyLook(visible ? 'aerial' : 'classic');
          break;
      }
      
      // Sauvegarder la préférence
      try {
        localStorage.setItem('athena:ow-layer-' + layer, visible ? '1' : '0');
      } catch(e) {}
    });
  });

  window.addEventListener('resize', function () { map.invalidateSize({ animate: false }); });
  map.on('zoomend', function () { renderMap(); });
  window.setTimeout(function () { map.invalidateSize({ animate: false }); }, 120);

  caches.open(TILE_CACHE).then(function () {
    document.getElementById('ow-cache-label').textContent = 'Fonds prêts';
  }).catch(function () {
    document.getElementById('ow-cache-label').textContent = 'Fonds indisponibles';
  });

  mountSquadTaskPanel();
  mountFsAlertPanel();
  initSceneFootprints();
  refreshAll();
  startPoll(pollMs);
  window.setInterval(function () { if (lastRx && Date.now() - lastRx > Math.max(15000, pollMs * 5)) syncStatus(false); }, 3000);
  window.setTimeout(function () {
    window.dispatchEvent(new CustomEvent('atak:mapready'));
  }, 0);
}());
