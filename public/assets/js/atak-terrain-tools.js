/* COMSPEC ATAK — Analyse d’itinéraire et visée JTAC (profil + masque du relief). */
window.ATAKTerrainTools = (function () {
  'use strict';

  var mode = null; // 'route' | 'los' | null
  var vertices = [];
  var layer = null;
  var lineUnder = null;
  var line = null;
  var markers = [];
  var maskMarker = null;
  var boundMap = null;
  var lastResult = null;
  var requestSeq = 0;

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }
  function worldFromLatLng(ll) {
    return window.ATAKMap && window.ATAKMap.worldFromLatLng ? window.ATAKMap.worldFromLatLng(ll) : null;
  }
  function latLngFromWorld(x, y) {
    return window.ATAKMap && window.ATAKMap.latLngFromWorld ? window.ATAKMap.latLngFromWorld(x, y) : null;
  }
  function toast(msg) {
    if (window.ATAKMapTools && window.ATAKMapTools.toast) {
      window.ATAKMapTools.toast(msg);
      return;
    }
    var el = document.getElementById('atak-map-tools-toast');
    if (el) el.textContent = msg || '';
  }
  function setToolActive(tool, on) {
    var btn = document.querySelector('#atak-map-tools [data-tool="' + tool + '"]');
    if (!btn) return;
    btn.classList.toggle('is-active', !!on);
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
  }
  function isActive() {
    return !!mode;
  }

  function panel() { return document.getElementById('atak-route-panel'); }
  function titleEl() { return document.getElementById('atak-route-panel-title'); }
  function hintEl() { return document.getElementById('atak-route-panel-hint'); }
  function statsEl() { return document.getElementById('atak-route-stats'); }
  function verdictEl() { return document.getElementById('atak-route-verdict'); }
  function gapEl() { return document.getElementById('atak-route-gap'); }
  function canvasEl() { return document.getElementById('atak-route-spark'); }
  function losOpts() { return document.getElementById('atak-route-los-opts'); }

  function formatDist(m) {
    var n = Number(m);
    if (!isFinite(n) || n < 0) return '—';
    if (n < 1000) return Math.round(n) + ' m';
    return (n / 1000).toFixed(n < 10000 ? 2 : 1).replace('.', ',') + ' km';
  }
  function formatAlt(z) {
    if (z == null || !isFinite(Number(z))) return '—';
    return Math.round(Number(z)) + ' m';
  }
  function formatDeniv(z) {
    if (z == null || !isFinite(Number(z))) return '—';
    var n = Math.round(Number(z));
    return (n > 0 ? '+' : '') + n + ' m';
  }

  function showPanel(on) {
    var el = panel();
    if (!el) return;
    el.hidden = !on;
  }

  function setHint(text) {
    var el = hintEl();
    if (el) el.textContent = text || '';
  }

  function setGap(text) {
    var el = gapEl();
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
  }

  function setVerdict(text, kind) {
    var el = verdictEl();
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
    el.classList.remove('is-clear', 'is-masked', 'is-unknown');
    if (kind) el.classList.add('is-' + kind);
  }

  function clearStats() {
    var el = statsEl();
    if (el) el.innerHTML = '';
    var cv = canvasEl();
    if (cv) cv.hidden = true;
    setVerdict('', '');
    setGap('');
  }

  function addStat(dl, label, value) {
    var dt = document.createElement('dt');
    dt.textContent = label;
    var dd = document.createElement('dd');
    dd.textContent = value;
    dl.appendChild(dt);
    dl.appendChild(dd);
  }

  function observerEye() {
    var el = document.getElementById('atak-route-obs-eye');
    var v = el ? parseFloat(el.value) : 1.6;
    return isFinite(v) ? v : 1.6;
  }
  function targetEye() {
    var el = document.getElementById('atak-route-tgt-eye');
    var v = el ? parseFloat(el.value) : 0;
    return isFinite(v) ? v : 0;
  }

  function ensureLayer() {
    var map = getMap();
    if (!map || !window.L) return null;
    if (!layer) layer = window.L.layerGroup().addTo(map);
    return layer;
  }

  function clearGraphics() {
    if (layer) layer.clearLayers();
    lineUnder = null;
    line = null;
    markers = [];
    maskMarker = null;
  }

  function vertexLabel(idx) {
    if (mode === 'los') return idx === 0 ? 'Obs' : 'Cible';
    return String(idx + 1);
  }

  function redraw() {
    var lyr = ensureLayer();
    if (!lyr) return;
    clearGraphics();
    layer = lyr;
    vertices.forEach(function (ll, idx) {
      var mk = window.L.circleMarker(ll, {
        radius: 6,
        color: mode === 'los' ? '#38bdf8' : '#34d399',
        weight: 2,
        fillColor: '#0f172a',
        fillOpacity: 0.92,
        interactive: false
      });
      mk.bindTooltip(vertexLabel(idx), {
        permanent: true,
        direction: 'top',
        className: 'atak-measure-tip',
        offset: [0, -6]
      });
      mk.addTo(lyr);
      markers.push(mk);
    });
    if (vertices.length >= 2) {
      lineUnder = window.L.polyline(vertices, {
        color: '#0f172a',
        weight: 6,
        opacity: 0.8,
        interactive: false
      }).addTo(lyr);
      line = window.L.polyline(vertices, {
        color: mode === 'los' ? '#38bdf8' : '#34d399',
        weight: 3,
        dashArray: mode === 'los' ? '7 5' : null,
        opacity: 1,
        interactive: false
      }).addTo(lyr);
    }
    if (lastResult && lastResult.obstruction && lastResult.obstruction.x != null) {
      var ll = latLngFromWorld(lastResult.obstruction.x, lastResult.obstruction.y);
      if (ll) {
        maskMarker = window.L.circleMarker(ll, {
          radius: 7,
          color: '#f87171',
          weight: 2,
          fillColor: '#7f1d1d',
          fillOpacity: 0.95,
          interactive: false
        });
        maskMarker.bindTooltip('Masque', {
          permanent: true,
          direction: 'top',
          className: 'atak-measure-tip',
          offset: [0, -6]
        });
        maskMarker.addTo(lyr);
      }
    }
  }

  function pointsWorld() {
    var out = [];
    vertices.forEach(function (ll) {
      var w = worldFromLatLng(ll);
      if (w) out.push({ x: w.x, y: w.y });
    });
    return out;
  }

  function drawSpark(samples, opts) {
    var cv = canvasEl();
    if (!cv || !cv.getContext) return;
    opts = opts || {};
    var known = (samples || []).filter(function (s) { return s && s.z != null; });
    if (known.length < 2) {
      cv.hidden = true;
      return;
    }
    cv.hidden = false;
    var w = cv.width;
    var h = cv.height;
    var ctx = cv.getContext('2d');
    ctx.clearRect(0, 0, w, h);
    var dMax = 0;
    var zMin = known[0].z;
    var zMax = known[0].z;
    samples.forEach(function (s) {
      if (s.d > dMax) dMax = s.d;
      if (s.z != null) {
        if (s.z < zMin) zMin = s.z;
        if (s.z > zMax) zMax = s.z;
      }
      if (s.ray != null) {
        if (s.ray < zMin) zMin = s.ray;
        if (s.ray > zMax) zMax = s.ray;
      }
    });
    var pad = 8;
    var span = Math.max(8, zMax - zMin);
    function X(d) { return pad + (dMax > 0 ? (d / dMax) * (w - pad * 2) : 0); }
    function Y(z) { return h - pad - ((z - zMin) / span) * (h - pad * 2); }

    ctx.fillStyle = 'rgba(15, 23, 42, 0.55)';
    ctx.fillRect(0, 0, w, h);

    ctx.beginPath();
    var started = false;
    samples.forEach(function (s) {
      if (s.z == null) {
        if (started) {
          ctx.lineTo(X(s.d), h - pad);
          ctx.closePath();
          ctx.fillStyle = 'rgba(52, 211, 153, 0.22)';
          ctx.fill();
          started = false;
          ctx.beginPath();
        }
        return;
      }
      if (!started) {
        ctx.moveTo(X(s.d), h - pad);
        ctx.lineTo(X(s.d), Y(s.z));
        started = true;
      } else {
        ctx.lineTo(X(s.d), Y(s.z));
      }
    });
    if (started) {
      ctx.lineTo(X(samples[samples.length - 1].d), h - pad);
      ctx.closePath();
      ctx.fillStyle = 'rgba(52, 211, 153, 0.22)';
      ctx.fill();
    }

    ctx.beginPath();
    started = false;
    samples.forEach(function (s) {
      if (s.z == null) { started = false; return; }
      if (!started) { ctx.moveTo(X(s.d), Y(s.z)); started = true; }
      else ctx.lineTo(X(s.d), Y(s.z));
    });
    ctx.strokeStyle = '#6ee7b7';
    ctx.lineWidth = 1.6;
    ctx.stroke();

    if (opts.ray) {
      ctx.beginPath();
      started = false;
      samples.forEach(function (s) {
        if (s.ray == null) return;
        if (!started) { ctx.moveTo(X(s.d), Y(s.ray)); started = true; }
        else ctx.lineTo(X(s.d), Y(s.ray));
      });
      ctx.strokeStyle = '#38bdf8';
      ctx.setLineDash([5, 4]);
      ctx.lineWidth = 1.4;
      ctx.stroke();
      ctx.setLineDash([]);
    }

    if (opts.obstruction && opts.obstruction.d != null && opts.obstruction.z != null) {
      ctx.fillStyle = '#f87171';
      ctx.beginPath();
      ctx.arc(X(opts.obstruction.d), Y(opts.obstruction.z), 4, 0, Math.PI * 2);
      ctx.fill();
    }

    ctx.fillStyle = '#94a3b8';
    ctx.font = '10px ui-monospace, Consolas, monospace';
    ctx.fillText(formatAlt(zMax), 4, 12);
    ctx.fillText(formatAlt(zMin), 4, h - 4);
  }

  function renderProfile(j) {
    lastResult = j;
    var dl = statsEl();
    if (dl) {
      dl.innerHTML = '';
      addStat(dl, 'Distance', formatDist(j.distance_m));
      addStat(dl, 'Montée', formatDeniv(j.climb_m));
      addStat(dl, 'Descente', formatDeniv(j.descent_m != null ? -Math.abs(j.descent_m) : null));
      addStat(dl, 'Plus bas', formatAlt(j.min_z));
      addStat(dl, 'Plus haut', formatAlt(j.max_z));
      if (j.delta_m != null) addStat(dl, 'Dénivelé', formatDeniv(j.delta_m));
    }
    drawSpark(j.samples || [], {});
    setVerdict('', '');
    if (j.gaps || !j.ready) setGap(j.gap_message || 'Relief pas encore relevé sur ce tronçon');
    else setGap('');
    redraw();
  }

  function renderLos(j) {
    lastResult = j;
    var dl = statsEl();
    if (dl) {
      dl.innerHTML = '';
      addStat(dl, 'Distance', formatDist(j.distance_m));
      addStat(dl, 'Observateur', formatAlt(j.observer_z));
      addStat(dl, 'Cible', formatAlt(j.target_z));
    }
    drawSpark(j.samples || [], { ray: true, obstruction: j.obstruction });
    var kind = j.verdict === 'clear' ? 'clear' : (j.verdict === 'masked' ? 'masked' : 'unknown');
    var text = (j.verdict_label || '') + (j.detail ? ' — ' + j.detail : '');
    setVerdict(text, kind);
    if (j.gaps && j.verdict !== 'unknown') setGap(j.gap_message);
    else if (j.verdict === 'unknown') setGap(j.gap_message || j.detail || '');
    else setGap('');
    redraw();
  }

  function postJson(path, payload) {
    return fetch(apiBase() + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.ATAK_CSRF_TOKEN || ''
      },
      body: JSON.stringify(Object.assign({ mapId: mapId(), _csrf_token: window.ATAK_CSRF_TOKEN || '' }, payload || {}))
    }).then(function (r) {
      return r.text().then(function (raw) {
        try { return raw ? JSON.parse(raw) : null; } catch (e) { return null; }
      });
    });
  }

  function requestProfile() {
    var pts = pointsWorld();
    if (pts.length < 2) return;
    var seq = ++requestSeq;
    postJson('/api/atak/terrain/profile', { points: pts }).then(function (j) {
      if (seq !== requestSeq) return;
      if (!j) {
        setGap('Impossible de calculer le profil pour le moment.');
        return;
      }
      if (j.ready === false && (!j.samples || !j.samples.length)) {
        clearStats();
        setGap(j.gap_message || j.message || 'Relief pas encore relevé sur ce tronçon');
        return;
      }
      renderProfile(j);
    }).catch(function () {
      if (seq !== requestSeq) return;
      setGap('Impossible de calculer le profil pour le moment.');
    });
  }

  function requestLos() {
    var pts = pointsWorld();
    if (pts.length < 2) return;
    var seq = ++requestSeq;
    postJson('/api/atak/terrain/los', {
      observer: pts[0],
      target: pts[1],
      observer_eye_m: observerEye(),
      target_eye_m: targetEye()
    }).then(function (j) {
      if (seq !== requestSeq) return;
      if (!j) {
        setGap('Impossible de juger la visée pour le moment.');
        return;
      }
      renderLos(j);
    }).catch(function () {
      if (seq !== requestSeq) return;
      setGap('Impossible de juger la visée pour le moment.');
    });
  }

  function setCursor(on) {
    var map = getMap();
    if (map && map.getContainer) {
      map.getContainer().classList.toggle('atak-map--routing', !!on);
    }
  }

  function stop(keep) {
    mode = null;
    setToolActive('route', false);
    setToolActive('los', false);
    setCursor(false);
    if (!keep) {
      vertices = [];
      lastResult = null;
      clearGraphics();
      showPanel(false);
      clearStats();
    }
  }

  function start(next) {
    if (mode === next) {
      stop(false);
      toast(next === 'los' ? 'Visée annulée.' : 'Itinéraire annulé.');
      return;
    }
    if (window.ATAKMapTools) {
      if (window.ATAKMapTools.startMeasure && mode) { /* already handled */ }
    }
    vertices = [];
    lastResult = null;
    mode = next;
    setToolActive('route', next === 'route');
    setToolActive('los', next === 'los');
    setCursor(true);
    showPanel(true);
    clearStats();
    var los = losOpts();
    if (los) los.hidden = next !== 'los';
    if (titleEl()) titleEl().textContent = next === 'los' ? 'Visée JTAC' : 'Analyse d’itinéraire';
    if (next === 'los') {
      setHint('Cliquez l’observateur (ou l’IP), puis la cible.');
      toast('Visée : observateur, puis cible.');
    } else {
      setHint('Cliquez les points de l’itinéraire. Double-clic pour terminer.');
      toast('Itinéraire : cliquez les points, double-clic pour terminer.');
    }
    redraw();
  }

  function onMapClick(e) {
    if (!mode || !e || !e.latlng) return;
    if (mode === 'los') {
      if (vertices.length >= 2) {
        vertices = [];
        lastResult = null;
        clearStats();
      }
      vertices.push(e.latlng);
      redraw();
      if (vertices.length === 1) {
        setHint('Cliquez la cible.');
        toast('Cliquez la cible.');
      } else {
        setHint('Visée calculée. Cliquez à nouveau pour recommencer.');
        requestLos();
      }
      return;
    }
    vertices.push(e.latlng);
    lastResult = null;
    redraw();
    if (vertices.length === 1) setHint('Ajoutez d’autres points, puis double-cliquez pour terminer.');
    else setHint('Double-clic pour calculer le profil, ou cliquez encore un point.');
    if (vertices.length >= 2) requestProfile();
  }

  function onMapDblClick(e) {
    if (!mode) return;
    if (e && e.originalEvent && e.originalEvent.preventDefault) e.originalEvent.preventDefault();
    if (mode === 'route' && vertices.length >= 2) {
      if (vertices.length >= 3 && vertices[vertices.length - 1].distanceTo) {
        var last = vertices[vertices.length - 1];
        var prev = vertices[vertices.length - 2];
        if (prev && last.distanceTo(prev) < 3) vertices.pop();
      }
      setHint('Profil calculé. Cliquez pour prolonger, ou fermez le panneau.');
      requestProfile();
      toast('Profil d’itinéraire calculé.');
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

  function onKey(e) {
    if (!mode || !e) return;
    var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if (e.key === 'Escape') {
      stop(false);
      toast('Analyse annulée.');
    } else if (e.key === 'Enter' && mode === 'route' && vertices.length >= 2) {
      e.preventDefault();
      requestProfile();
    }
  }

  function bindUi() {
    var close = document.getElementById('atak-route-panel-close');
    if (close && !close._bound) {
      close._bound = true;
      close.addEventListener('click', function () { stop(false); });
    }
    function recomputeLos() {
      if (mode === 'los' && vertices.length >= 2) requestLos();
    }
    var obs = document.getElementById('atak-route-obs-eye');
    var tgt = document.getElementById('atak-route-tgt-eye');
    if (obs && !obs._bound) {
      obs._bound = true;
      obs.addEventListener('change', recomputeLos);
    }
    if (tgt && !tgt._bound) {
      tgt._bound = true;
      tgt.addEventListener('change', recomputeLos);
    }
    document.addEventListener('keydown', onKey);
  }

  window.addEventListener('atak:mapready', function (ev) {
    bindUi();
    bindMap(ev && ev.detail && ev.detail.map ? ev.detail.map : getMap());
  });
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindUi);
  } else {
    bindUi();
  }
  setTimeout(function () {
    var map = getMap();
    if (map) bindMap(map);
  }, 0);

  return {
    start: start,
    stop: stop,
    isActive: isActive,
    startRoute: function () { start('route'); },
    startLos: function () { start('los'); }
  };
})();
