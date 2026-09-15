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
  var photos = [];
  var nineLines = [];
  var medevacs = [];
  var zoneAlerts = [];
  var selected = null;
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
  var lastShapeId = 0;
  var poRows = [];
  var poLayers = [];
  var poAnnounced = {};
  var poPlaceSession = [];
  var rallyRows = [];
  var rallyLayers = [];
  var RALLY_RADIUS_M = 50;
  var groupTasks = [];
  var canIssueGroupTasks = true;
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
    baseTileLayer = L.tileLayer(config.tilePattern, {
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
      '<label>Tâche<select name="type">' +
      '<option value="MOVE">Se déplacer</option>' +
      '<option value="HOLD">Tenir la position</option>' +
      '<option value="RECON">Reconnaissance</option>' +
      '<option value="QRF">Force de réaction</option>' +
      '</select></label>' +
      '<label>Urgence<span class="ow-select ow-select--prio"><i class="ow-prio-dot" data-prio-dot></i><select name="priority">' +
      '<option value="ROUTINE">Sans urgence</option>' +
      '<option value="IMPORTANT" selected>Normale</option>' +
      '<option value="URGENT">Urgente</option>' +
      '<option value="CONTACT">Contact</option>' +
      '</select></span></label>' +
      '<label>Point à atteindre<select name="po"><option value="">Aucun</option></select></label>' +
      '<label>Consignes<textarea name="payload" maxlength="800" placeholder="Ce que le groupe doit faire…"></textarea></label>' +
      '<button class="ow-primary" type="submit">Transmettre la tâche</button></form>' +
      '<p class="ow-kicker">Tâches transmises</p>' +
      '<div class="ow-group-task-list"></div>';
  }
  function fullscreenAlertFormHtml(preset) {
    var locked = preset && preset.dest ? '<input type="hidden" name="dest" value="' + escapeHtml(preset.dest) + '">' : '';
    var destField = preset && preset.dest ? '' :
      '<label>Destinataire<select name="dest" required><option value="all">Tous les opérateurs</option></select></label>';
    return '<form class="ow-form-grid ow-fs-alert-form">' + locked + destField +
      '<label>Message<textarea name="message" required maxlength="280" placeholder="Ce que les opérateurs doivent voir sur l’écran…"></textarea></label>' +
      '<button class="ow-primary" type="submit">Envoyer l’alerte plein écran</button></form>' +
      '<p class="ow-help">Le message recouvre tout l’écran du téléphone, ouvert ou en position mini. Il disparaît après quelques secondes, ou dès que l’opérateur appuie sur Fermer.</p>';
  }
  function fillFsAlertSelects(force) {
    var squads = listSquads();
    var friends = units.filter(function (unit) { return side(unit) !== 'hostile'; });
    var sig = squads.map(function (row) { return row.key; }).join('|') + '#' +
      friends.map(function (unit) { return callsign(unit); }).join('|') + '#' + (canIssueGroupTasks ? '1' : '0');
    if (!force && sig === lastFsSelectSig) return;
    lastFsSelectSig = sig;
    document.querySelectorAll('.ow-fs-alert-form').forEach(function (form) {
      var destSel = form.querySelector('select[name="dest"]');
      var prev = destSel ? destSel.value : '';
      if (destSel) {
        destSel.innerHTML = '<option value="all">Tous les opérateurs</option>' +
          squads.map(function (row) {
            return '<option value="squad:' + escapeHtml(row.key) + '">Groupe · ' + escapeHtml(row.label) +
              ' · ' + row.members.length + ' opérateur' + (row.members.length > 1 ? 's' : '') + '</option>';
          }).join('') +
          friends.slice(0, 40).map(function (unit) {
            return '<option value="unit:' + escapeHtml(callsign(unit)) + '">Opérateur · ' +
              escapeHtml(callsign(unit)) + '</option>';
          }).join('');
        if (prev && Array.prototype.some.call(destSel.options, function (opt) { return opt.value === prev; })) {
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
    host.innerHTML = '<p class="ow-kicker ow-fs-kicker">Alerte plein écran</p>' + fullscreenAlertFormHtml();
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
      var open = status !== 'CANCELLED' && status !== 'FAILED';
      var text = String(row.payload_display || '').trim();
      return '<div class="ow-event"><span>' + escapeHtml(clean(row.type_label, 'Tâche')) +
        ' · ' + escapeHtml(clean(row.target_label, 'Groupe')) +
        '</span><strong>' + escapeHtml(clean(row.status_label, 'Émis')) +
        (row.priority_label ? ' · ' + escapeHtml(row.priority_label) : '') +
        '</strong>' +
        (open && canIssueGroupTasks ? '<button type="button" class="ow-tag" data-cancel-task="' +
          escapeHtml(String(row.id || '')) + '">Annuler</button>' : '') +
        '</div>' + (text ? '<p class="ow-help">' + escapeHtml(text) + '</p>' : '');
    }).join('') || '<p class="ow-help">Aucune tâche de groupe transmise pour cette mission.</p>';
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
      fillGroupTaskSelects();
    }).catch(function () {});
  }
  function squadColor(unit) {
    var raw = String(unit.fire_team_color || '').trim();
    if (/^#?[0-9a-f]{3,8}$/i.test(raw)) return raw.charAt(0) === '#' ? raw : '#' + raw;
    return markerPrefs().friend;
  }
  var STALE_HIDE_SEC = 15 * 60;
  var DISC_COLOR = '#8d9592';
  function unitAgeSec(unit) {
    if (!unit) return NaN;
    var apiAge = Number(unit.age_seconds);
    if (Number.isFinite(apiAge) && apiAge >= 0) return apiAge;
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
    if (isTrackedAi(unit) || !isDisconnected(unit)) return false;
    var age = unitAgeSec(unit);
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
  function drawStyle() {
    return {
      color: (document.getElementById('ow-draw-color') || {}).value || '#00d69a',
      stroke: Number((document.getElementById('ow-draw-width') || {}).value || 2)
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
    var raw = unit.updated_at || unit.last_seen_at || unit.last_seen || unit.captured_at;
    if (!raw) return '';
    var t = Date.parse(raw);
    if (!isFinite(t)) return '';
    var sec = Math.max(0, Math.round((Date.now() - t) / 1000));
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
        icon: L.divIcon({ className: 'ow-ring-label', html: '<span>' + formatMeters(radius) + '</span>' })
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
      seen[id] = true;
      if (armaMarkerLayers[id]) {
        if (armaMarkerLayers[id].setLatLng) armaMarkerLayers[id].setLatLng(latlng);
        return;
      }
      var layer = null;
      if (helper.isAreaShape && helper.isAreaShape(data) && helper.leafletShapeLayer) {
        layer = helper.leafletShapeLayer(L, data, latlng);
      } else if (helper.leafletDivIcon) {
        layer = L.marker(latlng, { icon: helper.leafletDivIcon(L, data), keyboard: false });
      }
      if (!layer) return;
      var title = helper.displayLabelOf ? helper.displayLabelOf(data) : (data.label || data.text || 'Repère');
      if (layer.bindTooltip) layer.bindTooltip(String(title), { permanent: false, direction: 'top' });
      layer.addTo(map);
      armaMarkerLayers[id] = layer;
      bindLayerContext(layer);
    });
    Object.keys(armaMarkerLayers).forEach(function (id) {
      if (!seen[id]) { map.removeLayer(armaMarkerLayers[id]); delete armaMarkerLayers[id]; }
    });
  }
  function loadArmaMarkers() {
    return api('/api/atak/markers?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      armaMarkerRows = Array.isArray(payload) ? payload : asList(payload, 'markers');
      renderArmaMarkers();
    }).catch(function () {});
  }
  function renderPresenceHeat() {
    if (presenceLayer) { map.removeLayer(presenceLayer); presenceLayer = null; }
    var box = document.getElementById('ow-presence-heat');
    if (!box || !box.checked) return;
    presenceLayer = L.layerGroup();
    units.forEach(function (unit) {
      var loc = point(unit);
      if (!loc || tooOldToShow(unit)) return;
      L.circle(loc, {
        radius: 160,
        color: '#e05b63',
        weight: 0,
        fillColor: '#e7b14d',
        fillOpacity: Number(getComputedStyle(document.documentElement).getPropertyValue('--ow-heat-opacity')) || 0.16,
        interactive: false
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
      bindLayerContext(pin);
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
      bindLayerContext(pin);
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
    var label = showLabel ? '<span class="ow-cs">' + escapeHtml(callsign(unit)) + '</span>' : '';
    var badge = (opts.forceLabel && stackCount > 1) ? '<b class="ow-stack-n">+' + (stackCount - 1) + '</b>' : '';
    return L.divIcon({
      className: 'ow-marker ow-mark-' + prefs.style + extra,
      iconSize: [16, 16],
      iconAnchor: [8, 8],
      html: '<div style="--ow-iff:' + escapeHtml(stateColor) + ';--ow-state:' + escapeHtml(stateColor) + ';--ow-squad:' + escapeHtml(squad) + '"><i></i>' + label + badge + '</div>'
    });
  }

  function selectUnit(unit) {
    selected = unit;
    document.getElementById('ow-drawer-kicker').textContent = 'BFT / Contact';
    document.getElementById('ow-drawer-title').textContent = callsign(unit);
    var loc = point(unit);
    var grid = loc ? Math.round(latLngToWorld(loc).x) + ' / ' + Math.round(latLngToWorld(loc).y) : '—';
    var heading = unitHeading(unit);
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
    var span = 0;
    mates.concat([unit]).forEach(function (row) {
      var a = point(row);
      mates.concat([unit]).forEach(function (other) {
        var b = point(other);
        if (a && b) span = Math.max(span, map.distance(a, b));
      });
    });
    var stackedHere = unitsAtSamePoint(unit);
    var stackedHtml = stackedHere.map(function (row) {
      return '<button type="button" class="ow-mate" data-unit-id="' + escapeHtml(unitId(row)) + '"><span>' +
        escapeHtml(callsign(row)) + '</span><strong>' + escapeHtml(group(row)) + '</strong></button>';
    }).join('');
    var certLabel = 'non transmis';
    var certRef = '';
    var certExp = '';
    if (term) {
      var csSt = String(term.certificate_status || '').toLowerCase();
      if (csSt === 'revoked') certLabel = 'Révoqué';
      else if (csSt === 'expired') certLabel = 'Expiré';
      else if (csSt === 'active' || csSt === 'issued') certLabel = 'Actif';
      else if (String(term.certificate_ref || '').trim()) certLabel = 'Émis';
      else certLabel = 'Aucun';
      certRef = String(term.certificate_ref || '').trim();
      certExp = String(term.certificate_expires_at || '').trim();
      if (certExp) {
        var expMs = Date.parse(certExp);
        if (!isNaN(expMs)) certExp = new Date(expMs).toLocaleDateString('fr-FR');
      }
    }
    var ip = extra.client_ip || extra.ip || extra.public_ip || extra.network || (term && term.last_client_ip) || '';
    var batteryShown = displayValue(extra.battery != null ? extra.battery : unit.battery);
    if (batteryShown && batteryShown.indexOf('%') < 0) batteryShown += ' %';
    var health = displayValue(extra.health != null ? extra.health : unit.health);
    if (/^(ok|healthy|fine)$/i.test(health)) health = 'Indemne';
    var radio = displayValue(extra.toc_radio || extra.radio_freq || extra.radio || '');
    var compromiseRaw = term && term.compromise_state ? String(term.compromise_state).toLowerCase() : '';
    var compromise = '';
    if (compromiseRaw === 'none') compromise = 'Intègre';
    else if (compromiseRaw === 'captured') compromise = 'Saisi';
    else if (compromiseRaw === 'compromised') compromise = 'Compromis';
    var phone = !!(extra.phone_geoloc || extra.source === 'phone');
    var geoloc = phone ? 'Géolocalisation téléphone' : (loc ? 'Liaison Arma' : '');
    var lastTerm = term && term.last_seen_at ? formatSeen(term.last_seen_at) : '';
    var statusRaw = String(unit.status || '').toLowerCase();
    var liaison = statusRaw === 'offline' ? 'Hors liaison' : (statusRaw === 'delayed' ? 'Différé' : linkLabel(unit));
    document.getElementById('ow-drawer-body').innerHTML =
      '<div class="ow-kv">' +
      kv('Type', clean(unit.type || unit.role, 'BFT')) +
      kv('Groupe', group(unit)) +
      kv('Rôle', clean(unit.role, '')) +
      kv('Liaison', liaison) +
      kv('Transmission', txLabel(unit)) +
      kv('État', health) +
      kv('Cap', heading != null ? Math.round(heading) + '°' : '') +
      kv('Vitesse', speed != null ? Math.round(speed * 3.6) + ' km/h' : '') +
      kv('Altitude', alt != null ? Math.round(alt) + ' m' : '') +
      kv('Dernière pos.', ageLabel(unit) || '') +
      kv('Grille', clean(unit.grid || unit.mgrs, grid), true) +
      kv('Source', clean(unit.source, phone ? 'Téléphone ATAK' : 'Athena')) +
      kv('Géoloc', geoloc) +
      '</div>' +
      '<p class="ow-kicker">Téléphone ATAK</p><div class="ow-kv">' +
      kv('Certificat', certLabel) +
      kv('Référence', certRef, true) +
      kv('Échéance', certExp) +
      kv('Dernière activité', lastTerm) +
      kv('Adresse réseau', maskIpForDisplay(ip), true) +
      kv('Intégrité', compromise) +
      kv('Batterie', batteryShown) +
      kv('Radio', radio) +
      '</div>' +
      (stackedHere.length ? '<p class="ow-kicker">Au même point</p><div class="ow-mate-list">' + stackedHtml + '</div>' +
        '<p class="ow-help">Plusieurs contacts occupent ce lieu. Sur la carte, ils sont écartés autour du point réel.</p>' : '') +
      (mates.length ? '<p class="ow-kicker">Même groupe</p><p class="ow-help">' + mates.length + ' autre' + (mates.length > 1 ? 's' : '') + (span ? ' · dispersion ' + formatMeters(span) : '') + '</p><div class="ow-mate-list">' + mateHtml + '</div>' : '<p class="ow-help">Aucun autre membre de groupe localisé.</p>') +
      '<div class="ow-drawer-actions">' +
      '<button type="button" class="ow-primary" data-center-selected>Centrer sur la carte</button>' +
      '<button type="button" class="ow-secondary" data-follow-selected>' +
      (followOn ? 'Arrêter le suivi' : 'Suivre ce contact') + '</button>' +
      (mates.length ? '<button type="button" class="ow-secondary" data-fit-squad>Cadrer le groupe</button>' : '') +
      (squadKey(unit) ? '<button type="button" class="ow-secondary" data-squad-task-from-unit>Tâche au groupe</button>' : '') +
      '</div>' +
      '<p class="ow-kicker ow-fs-kicker">Alerte plein écran</p>' +
      fullscreenAlertFormHtml({ dest: 'unit:' + callsign(unit) });
    document.getElementById('ow-drawer').hidden = false;
    bindFsAlertForms(document.getElementById('ow-drawer'));
    fillFsAlertSelects(true);
    renderSquadLinks();
    renderRangeRings();
    renderMap();
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
        if (tracksOn) appendTrack(id, trueLoc);
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

  function appendTrack(id, location) {
    if (!trackSamples[id]) trackSamples[id] = [];
    trackSamples[id].push({ ll: location, t: Date.now() });
    if (trackSamples[id].length > 80) trackSamples[id].shift();
    if (!trackLines[id]) {
      trackLines[id] = L.polyline([location], { color: '#00d69a', weight: 2, opacity: 0.55 });
      if (tracksOn && !hiddenLayers.tracks) trackLines[id].addTo(map);
      return;
    }
    var latlngs = trackLines[id].getLatLngs();
    latlngs.push(location);
    if (latlngs.length > 80) latlngs.shift();
    trackLines[id].setLatLngs(latlngs);
  }

  function renderList() {
    var query = (document.getElementById('ow-search').value || '').trim().toLowerCase();
    var sideFilter = (document.getElementById('ow-side-filter') || {}).value || 'all';
    var visible = units.filter(function (unit) {
      if (tooOldToShow(unit)) return false;
      if (sideFilter !== 'all' && side(unit) !== sideFilter) return false;
      return (callsign(unit) + ' ' + group(unit) + ' ' + clean(unit.role, '')).toLowerCase().indexOf(query) !== -1;
    });
    var groups = {};
    visible.forEach(function (unit) {
      var key = squadKey(unit) || 'none';
      if (!groups[key]) groups[key] = [];
      groups[key].push(unit);
    });
    document.getElementById('ow-contact-list').innerHTML = Object.keys(groups).sort().map(function (key) {
      var rows = groups[key];
      var head = '<div class="ow-squad-head">' + escapeHtml(key === 'none' ? 'Sans groupe' : group(rows[0])) + '<em>' + rows.length + '</em></div>';
      return head + rows.map(function (unit) {
        var stale = unit.status === 'delayed' || unit.status === 'offline';
        var disc = isDisconnected(unit);
        var pulse = squadColorOn() && hasSquadColor(unit);
        var stateColor = disc ? DISC_COLOR : (side(unit) === 'hostile' ? markerPrefs().hostile : (side(unit) === 'unknown' ? markerPrefs().unknown : markerPrefs().friend));
        var squad = hasSquadColor(unit) ? squadColor(unit) : stateColor;
        return '<button type="button" class="ow-contact' + (disc ? ' is-offline' : '') + '" data-unit-id="' + escapeHtml(unitId(unit)) + '"><span class="cicon' + (disc ? ' is-offline' : '') + (pulse ? ' is-squad-pulse' : '') + '" style="--ow-iff:' + escapeHtml(stateColor) + ';--ow-state:' + escapeHtml(stateColor) + ';--ow-squad:' + escapeHtml(squad) + '">' +
          initials(callsign(unit)) + '</span><span><div class="cname ow-mono">' + escapeHtml(callsign(unit)) + '</div><div class="cmeta">' +
          escapeHtml(group(unit)) + (ageLabel(unit) ? ' · ' + ageLabel(unit) : '') + '</div></span><em class="online' + (disc ? ' is-stale' : (stale ? ' is-stale' : ' is-live')) + '">' +
          (disc ? 'Hors liaison' : (stale ? 'Différé' : 'Direct')) + '</em></button>';
      }).join('');
    }).join('');
    document.getElementById('ow-bft-count').textContent = 'BFT ' + visibleUnits().length;
    document.getElementById('ow-empty').hidden = visibleUnits().some(point) || !config.tilePattern;
    renderHud();
    renderSquadList();
  }

  function asList(payload, key) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload[key])) return payload[key];
    if (payload && Array.isArray(payload.data)) return payload.data;
    if (payload && Array.isArray(payload.units)) return payload.units;
    return [];
  }

  function applyPayload(payload) {
    units = asList(payload, 'units');
    lastRx = Date.now();
    renderMap();
    renderList();
    syncStatus(true);
    window.dispatchEvent(new CustomEvent('overwatch:units-updated', { detail: { units: units } }));
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
      .then(applyPayload)
      .then(function () { return loadPoMarkers(); })
      .then(function () { return loadArmaMarkers(); })
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
      return '<button type="button" class="ow-channel' + (key === activeChannel ? ' is-active' : '') + '" data-channel="' +
        escapeHtml(key) + '">' + channelIcon(key) +
        '<span><div class="cname">' + escapeHtml(ch.label || channelLabel(key)) +
        '</div><div class="cmeta">' + escapeHtml(key === activeChannel && chatMessages.length
          ? (parseCommsBody(chatMessages[chatMessages.length - 1].body).text || String(chatMessages[chatMessages.length - 1].body || '')).slice(0, 42)
          : 'Canal mission') +
        '</div></span><em class="online is-live">' +
        (key === activeChannel && chatMessages.length ? String(chatMessages.length) : 'Direct') + '</em></button>';
    }).join('');
  }

  function renderChatLog(targetId, rows) {
    var host = document.getElementById(targetId);
    if (!host) return;
    var lastDay = null;
    var lastGroupKey = null;
    var html = '';
    rows.slice(-80).forEach(function (row) {
      var raw = String(row.body || '');
      var parsed = parseCommsBody(raw);
      var real = String(row.created_at || row.time || '');
      var day = real.slice(0, 10);
      if (day && day !== lastDay) {
        html += '<div class="ow-day-sep">' + escapeHtml(fmtDay(real)) + '</div>';
        lastDay = day;
        lastGroupKey = null;
      }
      if (parsed.type === 'system') {
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
      html += '<div class="ow-row-msg">' + bar + '<div class="ow-row-main"><div class="ow-row-text">' +
        escapeHtml(parsed.text || raw) + '</div>' +
        (parsed.prio ? '<div class="ow-row-meta"><span>' + escapeHtml(prioLabel(parsed.prio)) +
          '</span><span>·</span><span class="' + (parsed.crypto === 'FREE' ? 'crypto-free' : 'crypto-enc') + '">' +
          escapeHtml(cryptoLabel(parsed.crypto)) + '</span>' +
          (parsed.gameTime ? '<span>·</span><span>en jeu ' + escapeHtml(parsed.gameTime) + '</span>' : '') +
          '</div>' : '') +
        '</div></div><div class="ow-raw-preview">' + escapeHtml(raw) + '</div>';
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
      }).catch(function () {});
  }

  function sendChat(channel, body) {
    var text = String(body || '').trim();
    if (!text) return Promise.resolve();
    return api('/api/chat', {
      method: 'POST',
      body: { mapId: mapId, author: authorName, body: text, channel: channel }
    }).then(function () { return loadChat(channel); });
  }

  function appendChatMessage(row) {
    if (!row) return;
    var key = String(row.channel_key || row.channel || 'general');
    if (key === 'support') {
      supportMessages.push(row);
      renderChatLog('ow-support-log', supportMessages);
      return;
    }
    if (key === activeChannel || !row.channel_key) {
      chatMessages.push(row);
      renderChatLog('ow-chat-log', chatMessages);
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
    if (type === 'POINT' && geo.coordinates) {
      layer = L.circleMarker(worldToLatLng(geo.coordinates[0], geo.coordinates[1]), { radius: 6, color: color });
    } else if ((type === 'LINE' || type === 'ROUTE' || type === 'POLYLINE') && Array.isArray(geo.coordinates)) {
      layer = L.polyline(geo.coordinates.map(function (p) { return worldToLatLng(p[0], p[1]); }), { color: color, weight: Number(shape.stroke || 2) });
    } else if ((type === 'POLYGON' || type === 'AOI') && Array.isArray(geo.coordinates)) {
      var ring = geo.coordinates[0] && Array.isArray(geo.coordinates[0][0]) ? geo.coordinates[0] : geo.coordinates;
      layer = L.polygon(ring.map(function (p) { return worldToLatLng(p[0], p[1]); }), {
        color: color, fillOpacity: Number(shape.fillOpacity || shape.fill_opacity || 0.15)
      });
    }
    if (!layer) return;
    layer.addTo(map);
    shapeLayers[id] = layer;
    bindLayerContext(layer);
  }

  function loadShapes() {
    return api('/api/map-shapes?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      shapes = asList(payload, 'shapes');
      Object.keys(shapeLayers).forEach(function (id) { map.removeLayer(shapeLayers[id]); delete shapeLayers[id]; });
      shapes.forEach(drawShape);
      updateStatsBanner();
    }).catch(function () {});
  }

  function saveShape(type, latlngs, label) {
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
      body: { mapId: mapId, type: type, label: label || type, color: style.color, stroke: style.stroke, geometry: geometry, createdBy: authorName }
    }).then(function (row) {
      if (row) {
        shapes.push(row);
        drawShape(row);
        lastShapeId = Number(row.id || 0);
      }
      toast((label || type) + ' enregistré.');
      return row;
    }).catch(function () { toast('Enregistrement impossible.'); });
  }

  function saveMarker(ll, label) {
    var w = latLngToWorld(ll);
    var text = label || 'Marqueur';
    return api('/api/markers', {
      method: 'POST',
      body: {
        mapId: mapId,
        markerData: {
          type: 'manual',
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
      bindLayerContext(layer);
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
      bindLayerContext(pingMarkers[id]);
      toast('Quick Ping transmis.');
    }).catch(function () { toast('Ping refusé.'); });
  }

  function saveSitrep(ll) {
    var w = latLngToWorld(ll);
    var missionId = 'mission_' + Number(window.ATAK_TENANT_ID || 0) + '_map_' + mapId;
    return api('/api/intel/report', {
      method: 'POST',
      body: { missionId: missionId, mapId: mapId, target_type: 'UNKNOWN', pos_x: w.x, pos_y: w.y, source_callsign: authorName, report_type: 'SITREP' }
    }).then(function () {
      toast('SITREP géolocalisé transmis.');
    }).catch(function () {
      return sendChat('commandement', 'SITREP ' + Math.round(w.x) + '/' + Math.round(w.y) + ' — observation depuis le poste.');
    });
  }

  function saveIntelNote(ll) {
    var w = latLngToWorld(ll);
    return saveShape('POINT', [ll], 'Observation Intel').then(function () {
      return sendChat('general', 'OBS INTEL ' + Math.round(w.x) + '/' + Math.round(w.y));
    });
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
    if (draftPoints.length === 1) {
      draftLayer = L.circleMarker(draftPoints[0], { radius: 5, color: style.color }).addTo(map);
    } else if (draftPoints.length > 1) {
      var closed = activeTool === 'polygon' || activeTool === 'aoi';
      draftLayer = (closed ? L.polygon : L.polyline)(draftPoints, { color: style.color, dashArray: '4 4', weight: style.stroke }).addTo(map);
    }
  }

  function requestLos(fromLl, toLl) {
    var a = latLngToWorld(fromLl);
    var b = latLngToWorld(toLl);
    if (losLayer) { map.removeLayer(losLayer); losLayer = null; }
    api('/api/atak/terrain/los', {
      method: 'POST',
      body: { mapId: mapId, observer: { x: a.x, y: a.y }, target: { x: b.x, y: b.y } }
    }).then(function (payload) {
      if (!payload || payload.ready === false) {
        openDrawer('Visée', 'Masque du relief', '<p class="ow-help">' + escapeHtml((payload && (payload.gap_message || payload.message || payload.verdict_label)) || 'Relief non relevé.') + '</p>');
        return;
      }
      var color = payload.verdict === 'clear' ? '#00d69a' : (payload.verdict === 'masked' ? '#e05b63' : '#e7b14d');
      losLayer = L.polyline([fromLl, toLl], { color: color, weight: 3 }).addTo(map);
      bindLayerContext(losLayer);
      var html = '<p class="ow-help">' + escapeHtml(payload.detail || payload.verdict_label || '') + '</p>' +
        '<div class="ow-event"><span>Résultat</span><strong>' + escapeHtml(payload.verdict_label || '') + '</strong></div>' +
        '<div class="ow-event"><span>Distance</span><strong>' + formatMeters(Number(payload.distance_m || 0)) + '</strong></div>';
      if (payload.observer_z != null) html += '<div class="ow-event"><span>Observateur</span><strong>' + Math.round(payload.observer_z) + ' m</strong></div>';
      if (payload.target_z != null) html += '<div class="ow-event"><span>Cible</span><strong>' + Math.round(payload.target_z) + ' m</strong></div>';
      openDrawer('Visée', 'Masque du relief', html);
      toast(payload.verdict_label || 'Visée calculée.');
    }).catch(function () {
      openDrawer('Visée', 'Masque du relief', '<p class="ow-help">Relief non relevé.</p>');
    });
  }

  function undoLastShape() {
    if (!lastShapeId) { toast('Aucun tracé récent à retirer.'); return; }
    var id = lastShapeId;
    api('/api/map-shapes/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
      if (shapeLayers[String(id)]) { map.removeLayer(shapeLayers[String(id)]); delete shapeLayers[String(id)]; }
      shapes = shapes.filter(function (row) { return Number(row.id) !== id; });
      lastShapeId = 0;
      toast('Dernier tracé retiré.');
    }).catch(function () { toast('Impossible de retirer ce tracé.'); });
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
    if (activeTool === 'circle' && draftPoints.length >= 2) {
      var ring = circleLatLngs(draftPoints[0], draftPoints[1]);
      var radius = map.distance(draftPoints[0], draftPoints[1]);
      saveShape('AOI', ring, 'Cercle ' + formatMeters(radius));
      toast('Cercle · rayon ' + formatMeters(radius) + ' · surface ' + Math.round(Math.PI * radius * radius) + ' m²');
      clearDraft();
      return;
    }
    if (activeTool === 'rect' && draftPoints.length >= 2) {
      var rect = rectLatLngs(draftPoints[0], draftPoints[1]);
      saveShape('AOI', rect, 'Rectangle');
      toast('Rectangle · ' + Math.round(polygonAreaM2(rect)) + ' m²');
      clearDraft();
      return;
    }
    if (activeTool === 'freehand' && draftPoints.length >= 2) {
      saveShape('LINE', draftPoints, 'Croquis');
      toast('Croquis · ' + formatMeters(pathLength(draftPoints)));
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
    if ((activeTool === 'line' || activeTool === 'route') && draftPoints.length >= 2) {
      saveShape(activeTool === 'route' ? 'ROUTE' : 'LINE', draftPoints, activeTool === 'route' ? 'Route' : 'Ligne');
      clearDraft();
      return;
    }
    if ((activeTool === 'polygon' || activeTool === 'aoi') && draftPoints.length >= 3) {
      saveShape(activeTool === 'aoi' ? 'AOI' : 'POLYGON', draftPoints, activeTool === 'aoi' ? 'Zone tactique' : 'Zone');
      toast('Zone · ' + Math.round(polygonAreaM2(draftPoints)) + ' m²');
      clearDraft();
    }
  }

  function showCalcDrawer(points, meters, cap) {
    var html = '<p class="ow-help">Mesure relevée sur la carte du théâtre. Les temps de parcours restent indicatifs.</p>' +
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

  function setTool(tool) {
    var previous = activeTool;
    if (previous === 'po' && tool !== 'po') {
      finishPoSession();
      try { map.doubleClickZoom.enable(); } catch (e) {}
    }
    if (previous === 'rally' && tool !== 'rally') {
      try { map.doubleClickZoom.enable(); } catch (eRally) {}
    }
    activeTool = tool;
    document.querySelectorAll('[data-tool]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.tool === tool);
    });
    var extra = document.getElementById('ow-rail-extra');
    var more = document.querySelector('[data-ow-rail-more]');
    if (extra && more && extra.querySelector('[data-tool="' + tool + '"]')) {
      extra.hidden = false;
      more.setAttribute('aria-expanded', 'true');
      more.classList.add('is-open');
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
    if (tool === 'freehand') toast('Maintenez le clic pour croquer, relâchez pour enregistrer.');
    if (tool === 'text') toast('Cliquez l’emplacement du texte.');
    if (tool === 'bearing') toast('Maintenez du départ à l’arrivée.');
    if (tool === 'los') toast('Maintenez de l’observateur à la cible.');
    if (tool === 'polygon' || tool === 'aoi') toast('Maintenez pour tracer le contour. Relâchez pour fermer la zone.');
    if (tool === 'line' || tool === 'route') toast('Maintenez pour un segment, ou cliquez des sommets puis double-clic.');
    if (tool === 'measure') toast('Maintenez du premier point au second.');
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
    if (activeTool === 'cursor' || activeTool === 'freehand' || activeTool === 'goto' || activeTool === 'range') return;
    if (activeTool === 'text') {
      var label = window.prompt('Texte à poser sur la carte', '');
      if (label && label.trim()) saveShape('POINT', [event.latlng], label.trim());
      return;
    }
    if (activeTool === 'marker') { saveMarker(event.latlng, 'Marqueur'); return; }
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
    if ((activeTool === 'circle' || activeTool === 'rect' || activeTool === 'bearing' || activeTool === 'los') && draftPoints.length >= 2) {
      finishDraft();
      return;
    }
    if ((activeTool === 'line' || activeTool === 'route' || activeTool === 'polygon' || activeTool === 'aoi' || activeTool === 'split' || activeTool === 'eta' || activeTool === 'profile') && draftPoints.length >= (activeTool === 'aoi' || activeTool === 'polygon' ? 3 : 2)) {
      /* keep collecting until double-click */
    }
  }

  function isDragTool(tool) {
    return /^(circle|rect|freehand|line|route|polygon|aoi|measure|bearing|los|eta|profile)$/.test(tool);
  }
  function liveMeasureHud() {
    var el = document.getElementById('ow-live-measure');
    if (!el || draftPoints.length < 2) { if (el) el.hidden = true; return; }
    var meters = pathLength(draftPoints);
    var bits = [formatMeters(meters)];
    if (activeTool === 'circle') bits.push('rayon ' + formatMeters(map.distance(draftPoints[0], draftPoints[draftPoints.length - 1])));
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
      freehandOn = activeTool === 'freehand' || activeTool === 'polygon' || activeTool === 'aoi';
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

  function bindLayerContext(layer) {
    if (!layer || layer._owCtxBound) return;
    layer._owCtxBound = true;
    layer.on('contextmenu', function (event) {
      L.DomEvent.stop(event);
      var orig = event.originalEvent || event;
      openContextAt(event.latlng || (layer.getLatLng && layer.getLatLng()), orig, layer);
    });
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
    for (var i = 0; i < pts.length; i++) {
      best = Math.min(best, pxDist(ll, pts[i]));
      if (i > 0) {
        var mid = L.latLng((pts[i - 1].lat + pts[i].lat) / 2, (pts[i - 1].lng + pts[i].lng) / 2);
        best = Math.min(best, pxDist(ll, mid));
      }
    }
    return best;
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
    if (losLayer === layer) return { kind: 'los', id: 'los', rank: 1, dist: 0, label: 'Visée' };
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

    Object.keys(pingMarkers).forEach(function (id) {
      var layer = pingMarkers[id];
      if (!layer || !layer.getLatLng) return;
      var d = pxDist(ll, layer.getLatLng());
      if (d <= 28) pushHit({ kind: 'ping', id: id, rank: 0, dist: d, label: 'Repère rapide' });
    });
    poRows.forEach(function (row) {
      var loc = worldToLatLng(row.x, row.y);
      var d = pxDist(ll, loc);
      var inside = map.distance(ll, loc) <= row.radius;
      if (inside || d <= 28) pushHit({ kind: 'po', id: row.id, rank: 0, dist: d, label: row.label });
    });
    rallyRows.forEach(function (row) {
      var loc = worldToLatLng(row.x, row.y);
      var d = pxDist(ll, loc);
      var inside = map.distance(ll, loc) <= row.radius;
      if (inside || d <= 28) pushHit({ kind: 'rally', id: row.id, rank: 0, dist: d, label: row.label });
    });
    Object.keys(postedMarkers).forEach(function (id) {
      var layer = postedMarkers[id];
      if (!layer || !layer.getLatLng) return;
      var d = pxDist(ll, layer.getLatLng());
      if (d > 28) return;
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
      if (d <= 28 || rank === 2) {
        if (poRows.some(function (row) { return String(row.id) === String(id); })) return;
        pushHit({ kind: 'arma', id: id, rank: rank, dist: d, label: 'Repère' });
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
      if (d <= 28 || rank === 2) {
        var shape = shapes.filter(function (row) { return String(row.id) === String(id); })[0];
        pushHit({ kind: 'shape', id: id, rank: rank, dist: d, label: (shape && (shape.label || shape.type)) || 'Tracé' });
      }
    });
    if (losLayer && losLayer.getLatLngs) {
      var losDist = nearestPxOnLatLngs(losLayer.getLatLngs(), ll);
      if (losDist <= 28) pushHit({ kind: 'los', id: 'los', rank: 1, dist: losDist, label: 'Visée' });
    }

    hits.sort(function (a, b) { return a.rank - b.rank || a.dist - b.dist; });
    return hits[0] || null;
  }

  function dropLocalId(store, id) {
    var key = String(id);
    if (store[key]) {
      try { map.removeLayer(store[key]); } catch (e) {}
      delete store[key];
    }
  }

  function deleteMapTarget(target) {
    if (!target) return;
    var id = String(target.id || '');
    var kind = target.kind;
    if (kind === 'los') {
      if (losLayer) { map.removeLayer(losLayer); losLayer = null; }
      toast('Visée retirée.');
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
      api('/api/markers/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
        dropLocalId(postedMarkers, id);
        dropLocalId(armaMarkerLayers, id);
        armaMarkerRows = armaMarkerRows.filter(function (row) { return String(row.id) !== id; });
        poRows = poRows.filter(function (row) { return String(row.id) !== id; });
        renderPoMarkers();
        renderArmaMarkers();
        toast(kind === 'po' ? 'Point à atteindre retiré.' : 'Repère retiré.');
        updateStatsBanner();
      }).catch(function () { toast('Impossible de retirer ce repère.'); });
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
    if (group) group.hidden = !show;
    if (btn) btn.hidden = !show;
  }

  function openContextAt(ll, originalEvent, layer) {
    if (!ll) return;
    ctxLatLng = ll;
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
    if (act === 'ping') savePing(ll);
    if (act === 'aoi') { setTool('aoi'); draftPoints = [ll]; updateDraft(); toast('Cliquez les sommets, double-clic pour fermer.'); }
    if (act === 'route') { setTool('route'); draftPoints = [ll]; updateDraft(); toast('Cliquez les points de route, double-clic pour terminer.'); }
    if (act === 'intel') saveIntelNote(ll);
    if (act === 'sitrep') saveSitrep(ll);
    if (act === 'circle') { setTool('circle'); draftPoints = [ll]; updateDraft(); }
    if (act === 'rect') { setTool('rect'); draftPoints = [ll]; updateDraft(); }
    if (act === 'bearing') { setTool('bearing'); draftPoints = [ll]; toast('Cliquez le second point.'); }
    if (act === 'po') { placeReachPoint(ll); finishPoSession(); }
    if (act === 'rally') { placeRallyPoint(ll); }
    if (act === 'los') { setTool('los'); draftPoints = [ll]; toast('Cliquez la cible.'); }
    if (act === 'ring') {
      var style = drawStyle();
      var edge = worldToLatLng(latLngToWorld(ll).x + 250, latLngToWorld(ll).y);
      saveShape('AOI', circleLatLngs(ll, edge, 48), 'Anneau 250 m');
    }
    if (act === 'chatgrid') sendChat(activeChannel, 'GRILLE ' + gridLabel(ll).replace('GRID ', ''));
    if (act === 'measure') { setTool('measure'); measureFrom = ll; draftPoints = [ll]; toast('Cliquez le second point.'); }
    if (act === 'copy') copyCoords(ll);
    if (act === 'rings' && window.OverwatchTools) window.OverwatchTools.placeRange(ll, [100, 250, 500, 1000]);
    if (act === 'centerhere') map.setView(ll, Math.max(map.getZoom(), 4));
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

  document.querySelectorAll('[data-ow-look]').forEach(function (input) {
    input.addEventListener('change', function () { applyLook(input.value); });
  });
  applyLook(storedLook());

  document.querySelectorAll('[data-ow-layer]').forEach(function (input) {
    input.addEventListener('change', function () {
      hiddenLayers[input.dataset.owLayer] = !input.checked;
      if (input.dataset.owLayer === 'tracks') {
        tracksOn = input.checked;
        Object.keys(trackLines).forEach(function (id) {
          if (tracksOn) trackLines[id].addTo(map); else map.removeLayer(trackLines[id]);
        });
      }
      if (input.dataset.owLayer === 'shapes') loadShapes();
      renderMap();
    });
  });

  function openDrawer(kicker, title, html) {
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
    return api('/api/recon/images?limit=30').then(function (payload) {
      photos = asList(payload, 'images');
      updateStatsBanner();
      if (window.OverwatchTools && window.OverwatchTools.drawPhotos) window.OverwatchTools.drawPhotos();
    }).catch(function () { photos = []; });
  }

  function loadNine() {
    return api('/api/nine-line?mapId=' + encodeURIComponent(mapId)).then(function (payload) {
      nineLines = asList(payload, 'items');
    }).catch(function () { nineLines = []; });
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
      '<p class="ow-kicker">9-line / appui aérien</p>' +
      (nineLines.slice(0, 5).map(function (row) {
        return '<div class="ow-event"><span>' + escapeHtml(clean(row.author, 'JTAC')) + '</span><span class="ow-tag">' + escapeHtml(clean(row.status, 'ACTIVE')) + '</span></div>';
      }).join('') || '<p class="ow-help">Aucune demande d’appui pour le moment.</p>') +
      '<form class="ow-form-grid" id="ow-nine-form">' +
      '<label>IP / grille<input name="line1" required placeholder="Point initial"></label>' +
      '<label>Cap<input name="line2" placeholder="Cap d’approche"></label>' +
      '<label>Distance<input name="line3" placeholder="Distance à la cible"></label>' +
      '<label>Élévation<input name="line4" placeholder="Altitude cible"></label>' +
      '<label>Description<input name="line5" placeholder="Nature de la cible"></label>' +
      '<label>Marquage<input name="line6" placeholder="Fumée, laser…"></label>' +
      '<label>Type de mission<input name="line7" value="CAS"></label>' +
      '<label>Amis à proximité<input name="line8" placeholder="Position des amis"></label>' +
      '<label>Sortie<input name="line9" placeholder="Itinéraire de sortie"></label>' +
      '<button class="ow-primary" type="submit">Envoyer 9-line</button></form>' +
      '<p class="ow-kicker">CASEVAC</p>' +
      (medevacs.slice(0, 5).map(function (row) {
        return '<div class="ow-event"><span>' + escapeHtml(clean(row.call_sign || row.author, 'MEDEVAC')) + '</span><span class="ow-tag amber">' + escapeHtml(clean(row.status, 'OPEN')) + '</span></div>';
      }).join('') || '<p class="ow-help">Aucune évacuation ouverte.</p>') +
      '<form class="ow-form-grid" id="ow-medevac-form">' +
      '<label>Indicatif<input name="callsign" value="' + escapeHtml(authorName) + '"></label>' +
      '<label>Grille de ramassage<input name="pickup_grid" placeholder="À pointer sur la carte"></label>' +
      '<label>Blessés T1<input name="patients_t1_urgent" type="number" min="0" value="0"></label>' +
      '<label>Blessés T2<input name="patients_t2_urgent" type="number" min="0" value="0"></label>' +
      '<label>Blessés T3<input name="patients_t3_delayed" type="number" min="0" value="0"></label>' +
      '<label>Blessés T4<input name="patients_t4_expectant" type="number" min="0" value="0"></label>' +
      '<label>Observations<textarea name="remarks" placeholder="État, sécurité LZ, fréquence…"></textarea></label>' +
      '<button class="ow-primary" type="submit">Ouvrir CASEVAC</button></form>';
  }

  function intelHtml() {
    return '<label class="ow-search"><span>⌕</span><input id="ow-intel-q" placeholder="Rechercher une photo, une note…"></label>' +
      '<form class="ow-form-grid" id="ow-photo-form">' +
      '<label>Photo liée à la carte<input type="file" name="photo" accept="image/*"></label>' +
      '<button class="ow-primary" type="submit">Déposer la photo</button></form>' +
      photos.slice(0, 12).map(function (row) {
        var url = String(row.url || '');
        return '<div class="ow-card"><div class="ow-card-head"><span>' + escapeHtml(clean(row.author || row.device_label, 'CAPTURE')) +
          '</span><span class="ow-tag">' + escapeHtml(clean(row.device_label, 'PHOTO')) + '</span></div>' +
          (url ? '<div class="ow-thumb" style="background-image:url(\'' + escapeHtml(url) + '\')"></div>' : '') +
          '<div class="ow-card-body"><div class="ow-event"><span>' + escapeHtml(clean(row.captured_at || row.created_at, '')) +
          '</span><button type="button" class="ow-tag" data-send-photo="' + escapeHtml(String(row.id || '')) + '">Envoyer</button></div></div></div>';
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
      '<p class="ow-kicker">Satellites</p>' +
      '<p class="ow-help">Catalogue local. Les passages ne s’affichent que si une source orbitale répond.</p>' +
      '<button type="button" class="ow-primary" data-ow-panel="sats">Ouvrir le catalogue</button>' +
      '<div id="ow-sat-status" class="ow-help">Recherche d’une source de passages…</div>' +
      '<p class="ow-kicker">Aide</p>' +
      '<button type="button" class="ow-secondary" data-ow-help>Ouvrir l’aide du poste</button>';
  }

  function layersHtml() {
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
        .then(function () { toast('9-Line transmise.'); loadNine().then(function () { openView('mission'); }); })
        .catch(function () { toast('9-Line refusée.'); });
    });
    var med = document.getElementById('ow-medevac-form');
    if (med) med.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(med);
      var loc = selected && point(selected);
      var world = loc ? latLngToWorld(loc) : { x: null, y: null };
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
    var sat = document.getElementById('ow-sat-status');
    if (sat) {
      fetch('https://celestrak.org/NORAD/elements/gp.php?GROUP=stations&FORMAT=json', { mode: 'cors' }).then(function (r) {
        if (!r.ok) throw new Error('no');
        sat.textContent = 'Source orbitale joignable. Les passages restent indicatifs.';
      }).catch(function () {
        sat.textContent = 'Source orbitale indisponible. Aucun passage n’est inventé.';
      });
    }
  }

  function openView(name) {
    document.querySelectorAll('.ow-nav button, .ow-more-menu button[data-view]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.view === name);
    });
    var workspace = document.querySelector('.ow-workspace');
    workspace.classList.toggle('is-comms', name === 'comms');
    workspace.classList.toggle('is-settings', name === 'layers');
    if (name === 'overwatch') { document.getElementById('ow-drawer').hidden = true; map.invalidateSize(); return; }
    if (name === 'comms') { switchChatTab('channels'); map.invalidateSize(); return; }
    if (name === 'layers') { openDrawer('Cartographie', 'Calques', layersHtml()); bindDrawerForms(); map.invalidateSize(); return; }
    if (name === 'mission') {
      Promise.all([loadNine(), loadMedevac(), loadGroupTasks()]).then(function () {
        openDrawer('Opérations', 'Mission', missionHtml());
        bindDrawerForms();
      });
      return;
    }
    if (name === 'intel') {
      loadPhotos().then(function () { openDrawer('Renseignement', 'Photos', intelHtml()); bindDrawerForms(); });
      return;
    }
    if (name === 'tools') {
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
    ['Ouvrir le tchat opérationnel', 'Comms', function () { openView('comms'); }],
    ['Ouvrir la mission', 'Mission', function () { openView('mission'); }],
    ['Préparer un SITREP', 'Mission', function () { setTool('cursor'); toast('Clic droit sur la carte → compte rendu géolocalisé.'); }],
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
    ['Catalogue satellites', 'Outils', function () { window.dispatchEvent(new CustomEvent('overwatch:panel', { detail: { panel: 'sats' } })); }],
    ['Visée / masque du relief', 'Carte', function () { setTool('los'); }],
    ['Poser un point à atteindre', 'Carte', function () { setTool('po'); }],
    ['Poser un point de ralliement', 'Carte', function () { setTool('rally'); }],
    ['Transmettre une tâche de groupe', 'Mission', function () { openSquadTaskForm(''); }],
    ['Envoyer une alerte plein écran', 'Comms', function () { openFullscreenAlertForm(''); }],
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
  window.ATAKMap = {
    getMap: function () { return map; },
    getBaseTileLayer: function () { return baseTileLayer; },
    worldFromLatLng: latLngToWorld,
    latLngFromWorld: function (x, y) { return worldToLatLng(x, y); },
    invalidateSize: function () { try { map.invalidateSize({ animate: false }); } catch (e) {} },
    getDisplayPrefs: getDisplayPrefs,
    patchDisplayPrefs: patchDisplayPrefs
  };
  window.ATAKUnits = {
    getUnits: function () { return units; },
    setUnits: function (rows) { applyPayload(rows); wrapCot('a-f-G-U-C', { count: (rows || []).length }); }
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

  function startPoll(ms) {
    if (pollTimer) window.clearInterval(pollTimer);
    pollTimer = window.setInterval(function () {
      refreshUnits();
      var squadsPanel = document.querySelector('[data-chat-panel="squads"]');
      var drawerTitle = document.getElementById('ow-drawer-title');
      var drawer = document.getElementById('ow-drawer');
      var squadsOpen = squadsPanel && !squadsPanel.hidden;
      var missionOpen = drawer && !drawer.hidden && drawerTitle && drawerTitle.textContent === 'Mission';
      if (squadsOpen || missionOpen) loadGroupTasks();
    }, Math.max(3000, Number(ms) || 5000));
  }

  function refreshAll() {
    refreshUnits();
    loadChannels();
    loadChat(activeChannel);
    loadShapes();
    loadPoMarkers();
    loadRallyPoints();
    loadGroupTasks();
    loadAlerts();
    loadPhotos();
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
    var row = event.target.closest('[data-unit-id]'); if (!row) return;
    var unit = units.find(function (item) { return unitId(item) === row.dataset.unitId; });
    if (unit) { selectUnit(unit); var location = point(unit); if (location) map.panTo(location); }
  });
  document.getElementById('ow-search').addEventListener('input', renderList);
  var sideFilter = document.getElementById('ow-side-filter');
  if (sideFilter) sideFilter.addEventListener('change', renderList);
  document.getElementById('ow-channel-filter').addEventListener('input', renderChannels);
  document.getElementById('ow-channel-list').addEventListener('click', function (event) {
    var row = event.target.closest('[data-channel]'); if (!row) return;
    activeChannel = row.dataset.channel;
    renderChannels();
    loadChat(activeChannel);
  });
  document.querySelector('[data-close-drawer]').addEventListener('click', function () {
    document.getElementById('ow-drawer').hidden = true;
    openView('overwatch');
  });
  document.getElementById('ow-drawer').addEventListener('click', function (event) {
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
  document.querySelectorAll('[data-tool]').forEach(function (button) {
    button.addEventListener('click', function () { setTool(button.dataset.tool); });
  });
  document.querySelectorAll('[data-view]').forEach(function (button) {
    button.addEventListener('click', function () { openView(button.dataset.view); });
  });
  document.querySelectorAll('[data-chat-tab]').forEach(function (button) {
    button.addEventListener('click', function () { switchChatTab(button.dataset.chatTab); });
  });
  document.getElementById('ow-chat-form').addEventListener('submit', function (event) {
    event.preventDefault();
    var input = document.getElementById('ow-chat-input');
    sendChat(activeChannel, input.value).then(function () { input.value = ''; });
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
    });
  }
  document.addEventListener('click', function (event) {
    if (moreMenu && !event.target.closest('[data-ow-more]')) moreMenu.hidden = true;
    if (railExtra && railMore && !event.target.closest('.ow-rail')) {
      if (!railExtra.querySelector('[data-tool].is-active')) {
        railExtra.hidden = true;
        railMore.setAttribute('aria-expanded', 'false');
        railMore.classList.remove('is-open');
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
    squadKey: squadKey,
    fitSquad: fitSquad,
    renderMap: renderMap,
    renderSquadLinks: renderSquadLinks,
    showCalcDrawer: showCalcDrawer,
    getSelected: function () { return selected; },
    renderPresenceHeat: renderPresenceHeat,
    getPhotos: function () { return photos; },
    applyLook: applyLook,
    formatMeters: formatMeters
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
  refreshAll();
  startPoll(pollMs);
  window.setInterval(function () { if (lastRx && Date.now() - lastRx > Math.max(15000, pollMs * 5)) syncStatus(false); }, 3000);
  window.setTimeout(function () {
    window.dispatchEvent(new CustomEvent('atak:mapready'));
  }, 0);
}());
