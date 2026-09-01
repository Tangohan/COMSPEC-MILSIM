/* COMSPEC ATAK — Itinéraires GPS du poste vers les opérateurs en jeu. */
window.ATAKGpsRoutes = (function () {
  'use strict';

  var placing = false;
  var draft = [];
  var saved = [];
  var layer = null;
  var boundMap = null;
  var pollTimer = null;
  var sending = false;

  function apiBase() {
    if (window.ATAKSocket && window.ATAKSocket.getApiBase) return window.ATAKSocket.getApiBase();
    return (window.ATAK_API_BASE || '').replace(/\/$/, '');
  }
  function mapId() {
    if (window.ATAKSocket && window.ATAKSocket.getMapId) return window.ATAKSocket.getMapId();
    return window.ATAK_DEFAULT_MAP_ID || 1;
  }
  function csrf() {
    return window.ATAK_CSRF_TOKEN || '';
  }
  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }
  function canWrite() {
    return !!window.ATAK_CAN_ISSUE_ORDERS;
  }
  function toast(msg) {
    if (window.ATAKMapTools && window.ATAKMapTools.toast) {
      window.ATAKMapTools.toast(msg);
      return;
    }
    if (window.ATAKShowNotification) window.ATAKShowNotification(msg);
  }
  function worldFromLatLng(ll) {
    if (window.ATAKMap && window.ATAKMap.worldFromLatLng) return window.ATAKMap.worldFromLatLng(ll);
    return { x: ll.lng, y: ll.lat };
  }
  function latLngFromWorld(x, y) {
    if (window.ATAKMap && window.ATAKMap.latLngFromWorld) return window.ATAKMap.latLngFromWorld(x, y);
    return window.L ? window.L.latLng(y, x) : null;
  }
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function jsonHeaders() {
    return {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-CSRF-Token': csrf()
    };
  }

  function panel() { return document.getElementById('atak-route-panel'); }
  function titleEl() { return document.getElementById('atak-route-panel-title'); }
  function hintEl() { return document.getElementById('atak-route-panel-hint'); }
  function gpsBox() { return document.getElementById('atak-gps-route-box'); }
  function nameEl() { return document.getElementById('atak-gps-route-name'); }
  function sendBtn() { return document.getElementById('atak-gps-route-send'); }

  function setRailActive(on) {
    document.querySelectorAll('#atak-c2-rail [data-tool="route"], #atak-map-tools [data-tool="route"]').forEach(function (btn) {
      btn.classList.toggle('is-active', !!on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function setCursor(on) {
    var map = getMap();
    if (map && map.getContainer) {
      map.getContainer().classList.toggle('atak-map--gps-route', !!on);
    }
  }

  function ensureLayer() {
    var map = getMap();
    if (!map || !window.L) return null;
    if (!layer) layer = window.L.layerGroup().addTo(map);
    return layer;
  }

  function clearLayer() {
    if (layer) layer.clearLayers();
  }

  function wpIcon(num, reached, draftMark) {
    var cls = 'atak-gps-wp' + (reached ? ' is-reached' : '') + (draftMark ? ' is-draft' : '');
    return window.L.divIcon({
      className: 'atak-gps-wp-wrap',
      html: '<span class="' + cls + '">' + escapeHtml(String(num)) + '</span>',
      iconSize: [22, 22],
      iconAnchor: [11, 11]
    });
  }

  function addNumberedLine(points, opts) {
    opts = opts || {};
    var lg = ensureLayer();
    if (!lg || points.length < 1) return;
    var latlngs = points.map(function (p) { return latLngFromWorld(p.x, p.y); }).filter(Boolean);
    if (latlngs.length >= 2) {
      window.L.polyline(latlngs, {
        color: opts.color || '#eab308',
        weight: opts.weight || 4,
        opacity: opts.opacity || 0.9,
        dashArray: opts.dash || null
      }).addTo(lg);
    }
    latlngs.forEach(function (ll, i) {
      var mk = window.L.marker(ll, {
        icon: wpIcon(i + 1, !!opts.reachedFlags && opts.reachedFlags[i], !!opts.draft),
        interactive: !opts.draft
      });
      if (opts.routeId && !opts.draft) {
        var label = opts.labels && opts.labels[i] ? opts.labels[i] : ('Point ' + (i + 1));
        var html = '<p class="atak-gps-popup"><strong>' + escapeHtml(opts.routeName || 'Itinéraire') + '</strong><br>'
          + escapeHtml(label)
          + (opts.reachedFlags && opts.reachedFlags[i] ? '<br>Atteint' : '')
          + '</p>';
        if (canWrite()) {
          html += '<button type="button" class="atak-gps-popup__del" data-gps-del="' + escapeHtml(String(opts.routeId)) + '">Retirer cet itinéraire</button>';
        }
        mk.bindPopup(html);
      }
      mk.addTo(lg);
    });
  }

  function render() {
    clearLayer();
    var byRoute = {};
    saved.forEach(function (wp) {
      var rid = wp.route_id != null ? String(wp.route_id) : 'orphan-' + wp.id;
      if (!byRoute[rid]) byRoute[rid] = [];
      byRoute[rid].push(wp);
    });
    Object.keys(byRoute).forEach(function (rid) {
      var list = byRoute[rid].slice().sort(function (a, b) {
        return (Number(a.sequence_number) || 0) - (Number(b.sequence_number) || 0);
      });
      addNumberedLine(list.map(function (w) {
        return { x: Number(w.pos_x), y: Number(w.pos_y) };
      }), {
        routeId: String(rid).indexOf('orphan') === 0 ? null : rid,
        routeName: list[0] && list[0].route_name,
        labels: list.map(function (w) { return w.label || ''; }),
        reachedFlags: list.map(function (w) { return !!w.reached; }),
        color: list.every(function (w) { return w.reached; }) ? '#6b7280' : '#eab308'
      });
    });
    if (draft.length) {
      addNumberedLine(draft, { draft: true, dash: '7 5', color: '#38bdf8' });
    }
  }

  function refreshHint() {
    if (!placing) return;
    var n = draft.length;
    if (hintEl()) {
      hintEl().textContent = n < 2
        ? 'Cliquez les points de passage sur la carte. Il en faut au moins deux.'
        : (n + ' points. Double-clic ou « Transmettre » pour envoyer l’itinéraire aux opérateurs.');
    }
    var btn = sendBtn();
    if (btn) btn.disabled = n < 2 || sending || !canWrite();
  }

  function showGpsPanel(on) {
    var box = gpsBox();
    if (box) box.hidden = !on;
    var los = document.getElementById('atak-route-los-opts');
    if (los && on) los.hidden = true;
    var spark = document.getElementById('atak-route-spark');
    if (spark && on) spark.hidden = true;
    var stats = document.getElementById('atak-route-stats');
    if (stats && on) stats.innerHTML = '';
    var verdict = document.getElementById('atak-route-verdict');
    if (verdict && on) verdict.hidden = true;
    var gap = document.getElementById('atak-route-gap');
    if (gap && on) gap.hidden = true;
    var el = panel();
    if (el) el.hidden = !on && !(window.ATAKTerrainTools && window.ATAKTerrainTools.isActive && window.ATAKTerrainTools.isActive());
    if (on && el) el.hidden = false;
    if (on && titleEl()) titleEl().textContent = 'Itinéraire des opérateurs';
  }

  function fetchWaypoints() {
    var base = apiBase();
    if (!base) return Promise.resolve([]);
    return fetch(base + '/api/atak/waypoints?mapId=' + encodeURIComponent(mapId()) + '&limit=300', {
      credentials: 'include'
    }).then(function (r) { return r.json(); }).then(function (data) {
      saved = (data && data.waypoints) ? data.waypoints : [];
      render();
      return saved;
    }).catch(function () { return saved; });
  }

  function startPoll() {
    if (pollTimer) return;
    pollTimer = setInterval(function () {
      if (document.hidden) return;
      fetchWaypoints();
    }, 4000);
  }

  function stopOthers() {
    if (window.ATAKTerrainTools && window.ATAKTerrainTools.isActive && window.ATAKTerrainTools.isActive()) {
      window.ATAKTerrainTools.stop(false);
    }
    if (window.ATAKMapTools) {
      if (window.ATAKMapTools.startMeasure) { /* measure stopped below */ }
    }
  }

  function stop(opts) {
    opts = opts || {};
    placing = false;
    draft = [];
    setRailActive(false);
    var selectBtn = document.querySelector('#atak-c2-rail [data-tool="select"]');
    if (selectBtn) {
      document.querySelectorAll('#atak-c2-rail [data-tool]').forEach(function (b) {
        b.classList.remove('is-active');
      });
      selectBtn.classList.add('is-active');
    }
    setCursor(false);
    var map = getMap();
    if (map && map.doubleClickZoom) map.doubleClickZoom.enable();
    showGpsPanel(false);
    if (!opts.keepDraftRender) render();
  }

  function start() {
    if (!canWrite()) {
      toast('Profil commandement requis pour tracer un itinéraire.');
      return;
    }
    if (placing) {
      stop();
      toast('Tracé d’itinéraire annulé.');
      return;
    }
    stopOthers();
    if (window.ATAKContextMenu && typeof window.ATAKContextMenu.cancelDraw === 'function') {
      window.ATAKContextMenu.cancelDraw();
    }
    if (window.ATAKMapTools) {
      if (typeof window.ATAKMapTools.setFollow === 'function') window.ATAKMapTools.setFollow(false);
    }
    placing = true;
    draft = [];
    setRailActive(true);
    setCursor(true);
    var map = getMap();
    if (map && map.doubleClickZoom) map.doubleClickZoom.disable();
    showGpsPanel(true);
    var name = nameEl();
    if (name && !name.value) name.value = 'Itinéraire';
    refreshHint();
    render();
    toast('Itinéraire : cliquez les points de passage, puis transmettez.');
  }

  function toggle() {
    if (placing) {
      stop();
      toast('Tracé d’itinéraire annulé.');
      return;
    }
    start();
  }

  function addDraftFromEvent(e) {
    if (!placing || !e || !e.latlng) return;
    var w = worldFromLatLng(e.latlng);
    if (!w) return;
    draft.push({ x: w.x, y: w.y });
    refreshHint();
    render();
  }

  function undoLast() {
    if (!placing || !draft.length) return;
    draft.pop();
    refreshHint();
    render();
  }

  function transmit() {
    if (!canWrite() || draft.length < 2 || sending) return;
    sending = true;
    refreshHint();
    var name = nameEl() ? String(nameEl().value || '').trim() : '';
    if (!name) name = 'Itinéraire';
    var body = {
      mapId: mapId(),
      route_name: name,
      route_type: 'PATROL',
      status: 'ACTIVE',
      is_visible: true,
      _csrf_token: csrf(),
      waypoints: draft.map(function (p, i) {
        return {
          pos_x: p.x,
          pos_y: p.y,
          sequence_number: i + 1,
          label: String(i + 1),
          radius_m: 25
        };
      })
    };
    fetch(apiBase() + '/api/atak/waypoint-routes', {
      method: 'POST',
      credentials: 'include',
      headers: jsonHeaders(),
      body: JSON.stringify(body)
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        sending = false;
        if (!res.ok || (res.data && res.data.ok === false)) {
          toast((res.data && (res.data.error || res.data.message)) || 'Impossible de transmettre l’itinéraire.');
          refreshHint();
          return;
        }
        stop();
        toast('Itinéraire transmis aux opérateurs.');
        fetchWaypoints();
      })
      .catch(function () {
        sending = false;
        refreshHint();
        toast('Liaison interrompue — itinéraire non transmis.');
      });
  }

  function deleteRoute(routeId) {
    if (!canWrite() || !routeId) return;
    fetch(apiBase() + '/api/atak/waypoint-routes/' + encodeURIComponent(routeId), {
      method: 'DELETE',
      credentials: 'include',
      headers: jsonHeaders(),
      body: JSON.stringify({ mapId: mapId(), _csrf_token: csrf() })
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok) {
          toast('Impossible de retirer l’itinéraire.');
          return;
        }
        toast('Itinéraire retiré.');
        fetchWaypoints();
      })
      .catch(function () { toast('Liaison interrompue.'); });
  }

  function onMapClick(e) {
    if (!placing) return;
    if (e && e.originalEvent && e.originalEvent.preventDefault) e.originalEvent.preventDefault();
    addDraftFromEvent(e);
  }
  function onMapDblClick(e) {
    if (!placing) return;
    if (e && e.originalEvent) {
      e.originalEvent.preventDefault();
      e.originalEvent.stopPropagation();
    }
    if (draft.length >= 3) {
      var last = draft[draft.length - 1];
      var prev = draft[draft.length - 2];
      if (last && prev) {
        var dx = last.x - prev.x;
        var dy = last.y - prev.y;
        if (Math.sqrt(dx * dx + dy * dy) < 8) draft.pop();
      }
    }
    if (draft.length >= 2) transmit();
  }
  function onKey(e) {
    if (!placing) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      stop();
      toast('Tracé d’itinéraire annulé.');
    } else if (e.key === 'Enter' && draft.length >= 2) {
      e.preventDefault();
      transmit();
    } else if (e.key === 'Backspace') {
      e.preventDefault();
      undoLast();
    }
  }

  function bindMap(map) {
    if (boundMap === map) return;
    if (boundMap) {
      boundMap.off('click', onMapClick);
      boundMap.off('dblclick', onMapDblClick);
    }
    boundMap = map || null;
    if (!boundMap) return;
    boundMap.on('click', onMapClick);
    boundMap.on('dblclick', onMapDblClick);
  }

  function bindUi() {
    var send = sendBtn();
    if (send && !send._gpsBound) {
      send._gpsBound = true;
      send.addEventListener('click', function () { transmit(); });
    }
    var undo = document.getElementById('atak-gps-route-undo');
    if (undo && !undo._gpsBound) {
      undo._gpsBound = true;
      undo.addEventListener('click', undoLast);
    }
    var cancel = document.getElementById('atak-gps-route-cancel');
    if (cancel && !cancel._gpsBound) {
      cancel._gpsBound = true;
      cancel.addEventListener('click', function () {
        stop();
        toast('Tracé d’itinéraire annulé.');
      });
    }
    var close = document.getElementById('atak-route-panel-close');
    if (close && !close._gpsBound) {
      close._gpsBound = true;
      close.addEventListener('click', function () {
        if (placing) stop();
      });
    }
    document.addEventListener('keydown', onKey);
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-gps-del]') : null;
      if (!btn) return;
      e.preventDefault();
      deleteRoute(btn.getAttribute('data-gps-del'));
    });
  }

  function boot() {
    bindUi();
    bindMap(getMap());
    ensureLayer();
    fetchWaypoints();
    startPoll();
  }

  window.addEventListener('atak:mapready', function (ev) {
    bindMap(ev && ev.detail && ev.detail.map ? ev.detail.map : getMap());
    boot();
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindUi);
  else bindUi();
  setTimeout(function () {
    if (getMap()) boot();
  }, 0);

  return {
    start: start,
    stop: stop,
    toggle: toggle,
    isPlacing: function () { return placing; },
    refresh: fetchWaypoints
  };
})();
