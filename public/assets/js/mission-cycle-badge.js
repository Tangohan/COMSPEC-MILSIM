/**
 * Badge « mission en cours » pour Tacmap / ATAK (cycle de mission).
 * Module autonome — polling léger, sans dépendre du shell carte.
 */
(function (global) {
  'use strict';

  var POLL_MS = 20000;
  var timer = null;

  function apiBase() {
    if (global.ATAK_API_BASE) return String(global.ATAK_API_BASE).replace(/\/$/, '');
    if (global.TACMAP_CTX && global.TACMAP_CTX.apiBase) {
      return String(global.TACMAP_CTX.apiBase).replace(/\/api\/?$/, '').replace(/\/$/, '');
    }
    if (global.overwatchContext && global.overwatchContext.apiBase) {
      return String(global.overwatchContext.apiBase).replace(/\/api\/?$/, '').replace(/\/$/, '');
    }
    return '';
  }

  function resolveMapId(opts) {
    opts = opts || {};
    if (typeof opts.mapId === 'function') {
      var v = opts.mapId();
      if (v > 0) return v;
    }
    if (opts.mapId > 0) return opts.mapId;
    if (global.ATAKSocket && typeof global.ATAKSocket.getMapId === 'function') {
      var m = global.ATAKSocket.getMapId();
      if (m > 0) return m;
    }
    var sel = document.getElementById(opts.workspaceSelectId || 'tacmap-workspace');
    if (sel && sel.value) {
      var n = parseInt(sel.value, 10);
      if (n > 0) return n;
    }
    if (global.ATAK_DEFAULT_MAP_ID > 0) return global.ATAK_DEFAULT_MAP_ID;
    if (global.overwatchContext && global.overwatchContext.defaultMapId > 0) {
      return global.overwatchContext.defaultMapId;
    }
    return 1;
  }

  function applyWindowBounds(mission) {
    if (!mission || !mission.window) {
      global.ATAK_MISSION_CYCLE_WINDOW = null;
      return;
    }
    var status = String(mission.status || '');
    if (status === 'cloturee' && (mission.window.from || mission.window.to)) {
      global.ATAK_MISSION_CYCLE_WINDOW = {
        from: mission.window.from || null,
        to: mission.window.to || null,
        missionId: mission.replay_mission_id || null,
        title: mission.title || null,
      };
    } else if (status === 'en_cours' && mission.window.from) {
      global.ATAK_MISSION_CYCLE_WINDOW = {
        from: mission.window.from || null,
        to: null,
        missionId: mission.replay_mission_id || null,
        title: mission.title || null,
      };
    } else {
      global.ATAK_MISSION_CYCLE_WINDOW = null;
    }
  }

  function renderBadge(el, mission, hubUrl) {
    if (!el) return;
    if (!mission) {
      el.hidden = true;
      el.textContent = '';
      el.removeAttribute('title');
      el.classList.remove('is-live', 'is-prep', 'is-done');
      return;
    }
    var status = String(mission.status || '');
    var label = String(mission.status_label || '');
    var title = String(mission.title || 'Mission');
    el.hidden = false;
    el.classList.remove('is-live', 'is-prep', 'is-done');
    if (status === 'en_cours') {
      el.classList.add('is-live');
      el.textContent = 'Mission · ' + title;
      el.title = 'Mission en cours — ' + title;
    } else if (status === 'preparation') {
      el.classList.add('is-prep');
      el.textContent = 'Préparation · ' + title;
      el.title = 'Mission en préparation — ' + title;
    } else if (status === 'cloturee') {
      el.classList.add('is-done');
      el.textContent = 'Clôturée · ' + title;
      el.title = 'Mission clôturée — relecture disponible';
    } else {
      el.textContent = label + ' · ' + title;
    }
    if (hubUrl) {
      el.setAttribute('data-hub', hubUrl);
    }
  }

  function fetchCurrent(opts) {
    opts = opts || {};
    var base = apiBase();
    var mapId = resolveMapId(opts);
    var url = base + '/api/mission-cycle/current?mapId=' + encodeURIComponent(String(mapId));
    return fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('mcycle_http_' + r.status);
        return r.json();
      })
      .then(function (data) {
        var mission = data && data.mission ? data.mission : null;
        applyWindowBounds(mission);
        var el = document.getElementById(opts.badgeId || 'mission-cycle-badge');
        renderBadge(el, mission, opts.hubUrl || null);
        if (typeof opts.onUpdate === 'function') {
          opts.onUpdate(mission);
        }
        return mission;
      })
      .catch(function () {
        return null;
      });
  }

  function start(opts) {
    opts = opts || {};
    stop();
    fetchCurrent(opts);
    timer = setInterval(function () {
      fetchCurrent(opts);
    }, opts.intervalMs || POLL_MS);

    var sel = document.getElementById(opts.workspaceSelectId || 'tacmap-workspace');
    if (sel && !sel.__mcycleBound) {
      sel.__mcycleBound = true;
      sel.addEventListener('change', function () {
        fetchCurrent(opts);
      });
    }
    var atakWs = document.getElementById('atak-workspace-select');
    if (atakWs && !atakWs.__mcycleBound) {
      atakWs.__mcycleBound = true;
      atakWs.addEventListener('change', function () {
        fetchCurrent(opts);
      });
    }
  }

  function stop() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  global.MissionCycleBadge = {
    start: start,
    stop: stop,
    refresh: fetchCurrent,
  };
})(window);
