/* COMSPEC ATAK — Relecture mission (positions enregistrées + après-action) */
window.ATAKReplay = (function () {
  var timeline = [];
  var events = [];
  var index = 0;
  var timer = null;
  var active = false;
  var loaded = false;
  var bound = false;
  var eventFilter = 'all';
  var eventWindowSeconds = 90;

  function apiRoot() {
    var base = '';
    if (window.ATAKSocket && window.ATAKSocket.getApiBase) {
      base = String(window.ATAKSocket.getApiBase() || '');
    } else {
      base = String(window.ATAK_API_BASE || '');
    }
    return base.replace(/\/$/, '');
  }

  function mapId() {
    if (window.ATAKSocket && window.ATAKSocket.getMapId) {
      return window.ATAKSocket.getMapId();
    }
    return (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0)
      ? window.ATAK_DEFAULT_MAP_ID
      : 1;
  }

  function tenantId() {
    var t = Number(window.ATAK_TENANT_ID || 0);
    if (t > 0) return t;
    var u = window.ATAK_USER || {};
    return Number(u.tenantId || u.tenant_id || 0) || 0;
  }

  function missionId() {
    var w = window.ATAK_MISSION_CYCLE_WINDOW;
    if (w && w.missionId) return String(w.missionId);
    return 'mission_' + tenantId() + '_map_' + mapId();
  }

  function isActive() {
    return active;
  }

  function el(id) {
    return document.getElementById(id);
  }

  function setBanner(on) {
    var ban = el('atak-replay-banner');
    if (!ban) return;
    if (on) ban.removeAttribute('hidden');
    else ban.setAttribute('hidden', '');
  }

  function setActive(on) {
    active = !!on;
    if (window.ATAKMap && typeof window.ATAKMap.setReplayActive === 'function') {
      window.ATAKMap.setReplayActive(active);
    }
    setBanner(active);
    document.body.classList.toggle('atak-replay-active', active);
  }

  function stopTimer() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function speedMs() {
    var sel = el('atak-replay-speed');
    var sp = sel ? parseFloat(sel.value) : 1;
    if (!sp || sp < 0.25) sp = 1;
    return Math.max(40, Math.round(500 / sp));
  }

  function formatTs(ts) {
    if (!ts) return '—';
    var s = String(ts).replace(' ', 'T');
    var d = new Date(s);
    if (isNaN(d.getTime())) return String(ts);
    var hh = d.getUTCHours();
    var mm = d.getUTCMinutes();
    var ss = d.getUTCSeconds();
    return (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm + ':' + (ss < 10 ? '0' : '') + ss + ' Z';
  }

  function frameToUnits(frame) {
    var units = (frame && frame.units) || [];
    return units.map(function (u, i) {
      var cs = String(u.callsign || u.unitId || ('U' + i));
      var extra = {};
      if (u.extra && typeof u.extra === 'object') {
        Object.keys(u.extra).forEach(function (k) { extra[k] = u.extra[k]; });
      }
      var kind = String(u.kind || extra.source || '');
      if (kind === 'phone' || extra.phone_geoloc) extra.phone_geoloc = true;
      if (kind === 'ally_ai' || extra.ally_ai) extra.ally_ai = true;
      if (kind === 'gps' || extra.gps_beacon) extra.gps_beacon = true;
      if (!extra.affiliation && !extra.affil) {
        extra.affiliation = extra.phone_geoloc ? 'unknown' : 'friend';
      }
      return {
        id: 'replay_' + String(u.unitId || cs || i),
        call_sign: cs,
        pos_x: u.x,
        pos_y: u.y,
        heading: u.heading,
        status: 'linked',
        extra: extra
      };
    });
  }

  function renderLegend(frame) {
    var box = el('atak-replay-legend');
    if (!box) return;
    var units = (frame && frame.units) || [];
    var counts = { player: 0, ally_ai: 0, phone: 0, gps: 0 };
    units.forEach(function (u) {
      var k = String(u.kind || 'player');
      if (counts[k] == null) counts[k] = 0;
      counts[k]++;
    });
    var parts = [];
    if (counts.player) parts.push('<span class="atak-replay-leg atak-replay-leg--op">' + counts.player + ' opérateur' + (counts.player > 1 ? 's' : '') + '</span>');
    if (counts.ally_ai) parts.push('<span class="atak-replay-leg atak-replay-leg--ai">' + counts.ally_ai + ' unité' + (counts.ally_ai > 1 ? 's' : '') + ' alliée' + (counts.ally_ai > 1 ? 's' : '') + '</span>');
    if (counts.phone) parts.push('<span class="atak-replay-leg atak-replay-leg--phone">' + counts.phone + ' téléphone' + (counts.phone > 1 ? 's' : '') + '</span>');
    if (counts.gps) parts.push('<span class="atak-replay-leg atak-replay-leg--gps">' + counts.gps + ' balise' + (counts.gps > 1 ? 's' : '') + '</span>');
    if (!parts.length) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = parts.join('');
  }

  function parseTsMs(ts) {
    if (!ts) return NaN;
    var s = String(ts).trim();
    if (/^\d+$/.test(s)) {
      var n = Number(s);
      return n < 1e12 ? n * 1000 : n;
    }
    var iso = s.indexOf('T') >= 0 ? s : s.replace(' ', 'T');
    if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += 'Z';
    return Date.parse(iso);
  }

  function eventTone(type) {
    if (type === 'medevac') return 'med';
    if (type === 'order') return 'order';
    if (type === 'marker') return 'marker';
    return 'contact';
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function filterEvents(list) {
    if (eventFilter === 'all') return list;
    return list.filter(function (ev) { return String(ev.type || 'contact') === eventFilter; });
  }

  function eventsNearFrame(frameTs) {
    var t = parseTsMs(frameTs);
    if (isNaN(t) || !events.length) return [];
    var windowMs = eventWindowSeconds * 1000;
    return filterEvents(events).filter(function (ev) {
      var et = parseTsMs(ev.timestamp);
      if (isNaN(et)) return false;
      return Math.abs(et - t) <= windowMs;
    }).slice(0, 8);
  }

  function renderEventsList(nearOnly) {
    var box = el('atak-replay-events');
    if (!box) return;
    var list = nearOnly && timeline.length
      ? eventsNearFrame(timeline[index] && timeline[index].timestamp)
      : filterEvents(events).slice(0, 40);
    if (!list.length) {
      if (!events.length) {
        box.hidden = true;
        box.innerHTML = '';
        return;
      }
      box.hidden = false;
      box.innerHTML = '<p class="atak-panel-hint">Aucun événement clé à cet instant.</p>';
      return;
    }
    box.hidden = false;
    var html = '<p class="atak-replay-events-title">Événements clés</p><ul class="atak-replay-events-list">';
    list.forEach(function (ev) {
      var tone = eventTone(ev.type);
      var lab = escapeHtml(ev.label || ev.type || 'Événement');
      var x = Number(ev.x);
      var y = Number(ev.y);
      var canLocate = Number.isFinite(x) && Number.isFinite(y);
      html += '<li class="atak-replay-event atak-replay-event--' + tone + '">' +
        (canLocate ? '<button type="button" class="atak-replay-event-target" data-x="' + x + '" data-y="' + y + '" aria-label="Centrer la carte sur ' + lab + '">' : '<div class="atak-replay-event-target">') +
        '<span class="atak-replay-event-ts">' + formatTs(ev.timestamp) + '</span> ' +
        '<span class="atak-replay-event-label">' + lab + '</span>' +
        (canLocate ? '</button>' : '</div>') + '</li>';
    });
    html += '</ul>';
    box.innerHTML = html;
  }

  function applyFrame(i, opts) {
    opts = opts || {};
    if (!timeline.length) return;
    index = Math.max(0, Math.min(i, timeline.length - 1));
    var frame = timeline[index];
    var slider = el('atak-replay-slider');
    if (slider) slider.value = String(index);
    var info = el('atak-replay-info');
    if (info) {
      var n = (frame && frame.units) ? frame.units.length : 0;
      info.textContent = (index + 1) + ' / ' + timeline.length + ' · ' + formatTs(frame && frame.timestamp)
        + (n ? ' · ' + n + ' sur la carte' : '');
    }
    renderLegend(frame);
    renderEventsList(true);
    if (!opts.previewOnly) {
      if (!active) setActive(true);
      if (window.ATAKMap && window.ATAKMap.setUnitsMarkers) {
        window.ATAKMap.setUnitsMarkers(frameToUnits(frame), { fromReplay: true });
      }
    }
  }

  function updateEmptyInfo() {
    var info = el('atak-replay-info');
    if (info) info.textContent = 'Aucun instantané enregistré pour cette mission.';
    var slider = el('atak-replay-slider');
    if (slider) {
      slider.min = '0';
      slider.max = '0';
      slider.value = '0';
    }
  }

  function windowQuery() {
    var w = window.ATAK_MISSION_CYCLE_WINDOW;
    if (!w) return '';
    var parts = [];
    if (w.from) parts.push('from=' + encodeURIComponent(String(w.from)));
    if (w.to) parts.push('to=' + encodeURIComponent(String(w.to)));
    return parts.length ? ('?' + parts.join('&')) : '';
  }

  function loadEvents() {
    var tid = tenantId();
    if (tid < 1) return Promise.resolve();
    var url = apiRoot() + '/api/replay/events/' + encodeURIComponent(missionId()) + windowQuery();
    return fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('events_http_' + r.status);
        return r.json();
      })
      .then(function (data) {
        events = Array.isArray(data && data.events) ? data.events : [];
        renderEventsList(!!timeline.length);
      })
      .catch(function () {
        events = [];
      });
  }

  function loadTimeline() {
    var tid = tenantId();
    var info = el('atak-replay-info');
    if (tid < 1) {
      if (info) info.textContent = 'Connectez-vous pour charger la relecture de mission.';
      return Promise.resolve();
    }
    if (info) info.textContent = 'Chargement des positions…';
    var url = apiRoot() + '/api/replay/mission/' + encodeURIComponent(missionId()) + windowQuery();
    return Promise.all([
      fetch(url, { credentials: 'include' }).then(function (r) {
        if (!r.ok) throw new Error('replay_http_' + r.status);
        return r.json();
      }),
      loadEvents()
    ])
      .then(function (pair) {
        var data = pair[0];
        timeline = Array.isArray(data && data.timeline) ? data.timeline : [];
        loaded = true;
        index = 0;
        stopTimer();
        var slider = el('atak-replay-slider');
        if (slider) {
          slider.min = '0';
          slider.max = String(Math.max(0, timeline.length - 1));
          slider.value = '0';
        }
        if (!timeline.length) {
          updateEmptyInfo();
          renderEventsList(false);
          return;
        }
        if (info) info.textContent = timeline.length + ' instantané(s) · ' + formatTs(timeline[0].timestamp);
        applyFrame(0, { previewOnly: true });
      })
      .catch(function () {
        loaded = false;
        timeline = [];
        if (info) info.textContent = 'Impossible de charger la relecture. Réessayez plus tard.';
      });
  }

  function renderAar(data) {
    var box = el('atak-replay-aar');
    if (!box) return;
    var s = (data && data.summary) || {};
    var errors = (data && data.errors) || [];
    var html = ''
      + '<div class="atak-replay-stats">'
      + '<div class="atak-replay-stat"><span class="atak-replay-stat-label">Opérateurs</span><strong>' + (s.operators != null ? s.operators : (s.unitCount || 0)) + '</strong></div>'
      + '<div class="atak-replay-stat"><span class="atak-replay-stat-label">Unités alliées</span><strong>' + (s.allies || 0) + '</strong></div>'
      + '<div class="atak-replay-stat"><span class="atak-replay-stat-label">Téléphones</span><strong>' + (s.phones || 0) + '</strong></div>'
      + '<div class="atak-replay-stat"><span class="atak-replay-stat-label">Balises GPS</span><strong>' + (s.beacons || 0) + '</strong></div>'
      + '<div class="atak-replay-stat"><span class="atak-replay-stat-label">Contacts</span><strong>' + (s.contactEvents != null ? s.contactEvents : (s.intelEvents || 0)) + '</strong></div>'
      + '<div class="atak-replay-stat"><span class="atak-replay-stat-label">Évacuations</span><strong>' + (s.medevacEvents || 0) + '</strong></div>'
      + '<div class="atak-replay-stat"><span class="atak-replay-stat-label">Ordres</span><strong>' + (s.orderEvents || 0) + '</strong></div>'
      + '<div class="atak-replay-stat"><span class="atak-replay-stat-label">Repères</span><strong>' + (s.markerEvents || 0) + '</strong></div>'
      + '</div>';
    if (errors.length) {
      html += '<div class="atak-replay-errors"><p class="atak-replay-errors-title">Points d’attention</p><ul>';
      errors.forEach(function (e) {
        html += '<li>' + String(e.label || 'Alerte') + (e.count ? ' (' + e.count + ')' : '') + '</li>';
      });
      html += '</ul></div>';
    }
    box.innerHTML = html;
  }

  function loadAar() {
    var box = el('atak-replay-aar');
    if (tenantId() < 1) {
      if (box) box.innerHTML = '<p class="atak-panel-hint">Connectez-vous pour consulter le bilan après-action.</p>';
      return;
    }
    if (box) box.innerHTML = '<p class="atak-panel-hint">Analyse en cours…</p>';
    var url = apiRoot() + '/api/replay/aar/' + encodeURIComponent(missionId()) + windowQuery();
    fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('aar_http_' + r.status);
        return r.json();
      })
      .then(renderAar)
      .catch(function () {
        if (box) box.innerHTML = '<p class="atak-panel-hint">Impossible de charger le bilan après-action.</p>';
      });
  }

  function play() {
    if (!timeline.length) {
      loadTimeline().then(function () {
        if (timeline.length) play();
      });
      return;
    }
    stopTimer();
    setActive(true);
    timer = setInterval(function () {
      if (index >= timeline.length - 1) {
        pause();
        return;
      }
      applyFrame(index + 1);
    }, speedMs());
  }

  function pause() {
    stopTimer();
  }

  function exitReplay() {
    pause();
    setActive(false);
    if (window.ATAKUnits && typeof window.ATAKUnits.fetchUnits === 'function') {
      window.ATAKUnits.fetchUnits();
    }
  }

  function exportPdf() {
    if (tenantId() < 1) {
      if (window.ATAKShowError) window.ATAKShowError('Connectez-vous pour exporter le bilan.');
      return;
    }
    var url = apiRoot() + '/api/replay/aar/' + encodeURIComponent(missionId()) + '/export.pdf' + windowQuery();
    var w = window.ATAK_MISSION_CYCLE_WINDOW;
    if (w && w.title) {
      url += (url.indexOf('?') >= 0 ? '&' : '?') + 'title=' + encodeURIComponent(String(w.title));
    }
    window.open(url, '_blank', 'noopener');
  }

  function onTabActivated() {
    if (!loaded) {
      loadTimeline();
      loadAar();
    }
  }

  function bind() {
    if (bound) return;
    bound = true;

    var playBtn = el('atak-replay-play');
    var pauseBtn = el('atak-replay-pause');
    var aarBtn = el('atak-replay-aar-refresh');
    var exportBtn = el('atak-replay-export');
    var reloadBtn = el('atak-replay-reload');
    var exitBtn = el('atak-replay-exit');
    var slider = el('atak-replay-slider');
    var speed = el('atak-replay-speed');
    var filter = el('atak-replay-event-filter');
    var zoom = el('atak-replay-zoom');

    if (playBtn) playBtn.addEventListener('click', play);
    if (pauseBtn) pauseBtn.addEventListener('click', pause);
    if (aarBtn) aarBtn.addEventListener('click', loadAar);
    if (exportBtn) exportBtn.addEventListener('click', exportPdf);
    if (reloadBtn) {
      reloadBtn.addEventListener('click', function () {
        loaded = false;
        loadTimeline();
        loadAar();
      });
    }
    if (exitBtn) exitBtn.addEventListener('click', exitReplay);
    if (slider) {
      slider.addEventListener('input', function () {
        pause();
        applyFrame(parseInt(slider.value, 10) || 0);
      });
    }
    if (speed) {
      speed.addEventListener('change', function () {
        if (timer) play();
      });
    }
    if (filter) {
      filter.addEventListener('change', function () {
        eventFilter = String(filter.value || 'all');
        renderEventsList(!!timeline.length);
      });
    }
    if (zoom) {
      zoom.addEventListener('input', function () {
        eventWindowSeconds = Math.max(30, Math.min(300, parseInt(zoom.value, 10) || 90));
        var output = el('atak-replay-zoom-value');
        if (output) output.textContent = '± ' + eventWindowSeconds + ' s';
        renderEventsList(!!timeline.length);
      });
    }

    var eventsBox = el('atak-replay-events');
    if (eventsBox) {
      eventsBox.addEventListener('click', function (e) {
        var target = e.target && e.target.closest ? e.target.closest('[data-x]') : null;
        if (!target) return;
        var x = parseFloat(target.getAttribute('data-x'));
        var y = parseFloat(target.getAttribute('data-y'));
        if (isNaN(x) || isNaN(y)) return;
        if (window.ATAKMap && typeof window.ATAKMap.setView === 'function') {
          window.ATAKMap.setView(y, x, 5);
        } else if (window.ATAKMap && window.ATAKMap.getMap) {
          var map = window.ATAKMap.getMap();
          if (map && map.setView) map.setView([y, x], map.getZoom());
        }
      });
    }

    document.querySelectorAll('.atak-tab[data-tab="replay"]').forEach(function (btn) {
      btn.addEventListener('click', onTabActivated);
    });

    var ws = document.getElementById('atak-workspace-select');
    if (ws) {
      ws.addEventListener('change', function () {
        if (active) exitReplay();
        loaded = false;
        timeline = [];
        var content = el('tab-replay');
        if (content && content.classList.contains('active')) {
          loadTimeline();
          loadAar();
        }
      });
    }
  }

  function init() {
    bind();
  }

  return {
    init: init,
    isActive: isActive,
    load: loadTimeline,
    loadAar: loadAar,
    play: play,
    pause: pause,
    exit: exitReplay,
    missionId: missionId
  };
})();
