/* Overwatch Beta — enregistrement replay (REC) depuis maintenant jusqu’à arrêt. */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || window.__OVERWATCH_REC__) return;
  window.__OVERWATCH_REC__ = true;

  var STORE_KEY = 'athena:ow-rec-v1';
  var MAX_SAMPLES_PER_UNIT = 12000;
  var tickTimer = null;
  var state = {
    recording: false,
    startedAt: 0,
    samples: {},
    clips: []
  };

  function ow() {
    return window.OverwatchBeta || null;
  }

  function toast(msg) {
    var api = ow();
    if (api && api.toast) api.toast(msg);
  }

  function mapKey() {
    var api = ow();
    var mapId = (api && api.mapId) || window.ATAK_DEFAULT_MAP_ID || 1;
    var tenant = window.ATAK_TENANT_ID || 0;
    return STORE_KEY + ':' + tenant + ':' + mapId;
  }

  function loadStore() {
    try {
      var raw = sessionStorage.getItem(mapKey());
      if (!raw) return;
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return;
      state.recording = !!parsed.recording;
      state.startedAt = Number(parsed.startedAt) || 0;
      state.samples = parsed.samples && typeof parsed.samples === 'object' ? parsed.samples : {};
      state.clips = Array.isArray(parsed.clips) ? parsed.clips : [];
    } catch (e) {}
  }

  function saveStore() {
    try {
      sessionStorage.setItem(
        mapKey(),
        JSON.stringify({
          recording: state.recording,
          startedAt: state.startedAt,
          samples: state.samples,
          clips: state.clips.slice(-8)
        })
      );
    } catch (e1) {}
  }

  function fmtClock(ms) {
    var d = new Date(ms);
    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }

  function fmtDuration(ms) {
    var s = Math.max(0, Math.floor(ms / 1000));
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    var r = s % 60;
    if (h > 0) return h + ' h ' + String(m).padStart(2, '0') + ' min';
    if (m > 0) return m + ' min ' + String(r).padStart(2, '0') + ' s';
    return r + ' s';
  }

  function countSamples(samples) {
    var n = 0;
    Object.keys(samples || {}).forEach(function (id) {
      n += (samples[id] || []).length;
    });
    return n;
  }

  function paintUi() {
    var btn = document.getElementById('ow-rec-btn');
    var banner = document.getElementById('ow-rec-banner');
    var live = document.getElementById('ow-rec-banner-text');
    var stopBtn = document.getElementById('ow-rec-stop');
    if (btn) {
      btn.classList.toggle('is-recording', state.recording);
      btn.setAttribute('aria-pressed', state.recording ? 'true' : 'false');
      btn.title = state.recording
        ? 'Enregistrement en cours — cliquez pour arrêter'
        : 'Démarrer l’enregistrement du replay à partir de maintenant';
      var label = btn.querySelector('[data-ow-rec-label]');
      if (label) label.textContent = state.recording ? 'REC ●' : 'REC';
    }
    if (banner) {
      banner.hidden = !state.recording;
      if (state.recording && live) {
        live.textContent =
          'REC · Enregistrement depuis ' +
          fmtClock(state.startedAt) +
          ' · ' +
          fmtDuration(Date.now() - state.startedAt) +
          ' · ' +
          countSamples(state.samples) +
          ' points — arrêtez quand vous voulez';
      }
    }
    if (stopBtn) stopBtn.hidden = !state.recording;
    document.body.classList.toggle('ow-is-recording', state.recording);
    refreshClipOptions();
  }

  function refreshClipOptions() {
    var source = document.getElementById('ow-replay-source');
    if (!source) return;
    var keep = source.value;
    var existing = {};
    Array.prototype.slice.call(source.querySelectorAll('option')).forEach(function (opt) {
      existing[opt.value] = true;
    });
    state.clips.forEach(function (clip, idx) {
      var value = 'clip:' + String(clip.id || idx);
      if (existing[value]) {
        var opt = source.querySelector('option[value="' + value.replace(/"/g, '') + '"]');
        if (opt) {
          opt.textContent =
            'Clip ' +
            fmtClock(clip.startedAt) +
            ' → ' +
            fmtClock(clip.endedAt) +
            ' (' +
            fmtDuration(clip.endedAt - clip.startedAt) +
            ')';
        }
        return;
      }
      var option = document.createElement('option');
      option.value = value;
      option.textContent =
        'Clip ' +
        fmtClock(clip.startedAt) +
        ' → ' +
        fmtClock(clip.endedAt) +
        ' (' +
        fmtDuration(clip.endedAt - clip.startedAt) +
        ')';
      source.appendChild(option);
    });
    if (keep && source.querySelector('option[value="' + keep.replace(/"/g, '') + '"]')) {
      source.value = keep;
    }
  }

  function startRecording() {
    if (state.recording) return;
    state.recording = true;
    state.startedAt = Date.now();
    state.samples = {};
    // Amorcer avec la dernière position connue de chaque unité (si dispo).
    var api = ow();
    if (api && api.getTrackSamples) {
      var live = api.getTrackSamples() || {};
      Object.keys(live).forEach(function (id) {
        var rows = live[id] || [];
        if (!rows.length) return;
        var last = rows[rows.length - 1];
        state.samples[id] = [{ ll: last.ll, t: last.t || Date.now(), live: !!last.live }];
      });
    }
    saveStore();
    paintUi();
    toast('Enregistrement démarré. Terminez plus tard avec STOP.');
    ensureTick();
  }

  function stopRecording() {
    if (!state.recording) return;
    var endedAt = Date.now();
    var clip = {
      id: 'c' + endedAt.toString(36),
      startedAt: state.startedAt,
      endedAt: endedAt,
      samples: state.samples
    };
    state.clips.push(clip);
    if (state.clips.length > 8) state.clips = state.clips.slice(-8);
    state.recording = false;
    state.startedAt = 0;
    state.samples = {};
    saveStore();
    paintUi();
    var source = document.getElementById('ow-replay-source');
    var timeline = document.getElementById('ow-timeline');
    if (source) source.value = 'clip:' + clip.id;
    if (timeline) timeline.hidden = false;
    toast('Enregistrement terminé (' + fmtDuration(endedAt - clip.startedAt) + '). Disponible dans Replay.');
    try {
      window.dispatchEvent(new CustomEvent('overwatch:rec-stopped', { detail: { clip: clip } }));
    } catch (e2) {}
  }

  function toggleRecording() {
    if (state.recording) stopRecording();
    else startRecording();
  }

  function onSample(id, location, meta) {
    if (!state.recording || !id || !location) return;
    if (!state.samples[id]) state.samples[id] = [];
    var rows = state.samples[id];
    var prev = rows[rows.length - 1];
    var api = ow();
    if (prev && api && api.map && typeof api.map.distance === 'function') {
      try {
        if (api.map.distance(prev.ll, location) < 1.5) return;
      } catch (e3) {}
    } else if (prev && prev.ll && location) {
      var dlat = Math.abs((prev.ll.lat || 0) - (location.lat || 0));
      var dlng = Math.abs((prev.ll.lng || 0) - (location.lng || 0));
      if (dlat < 0.00001 && dlng < 0.00001) return;
    }
    rows.push({
      ll: location,
      t: Date.now(),
      live: !(meta && meta.live === false)
    });
    if (rows.length > MAX_SAMPLES_PER_UNIT) rows.splice(0, rows.length - MAX_SAMPLES_PER_UNIT);
    if (rows.length % 8 === 0) saveStore();
  }

  function getActiveSamples() {
    if (state.recording) return state.samples;
    return null;
  }

  function getClipSamples(clipId) {
    var id = String(clipId || '').replace(/^clip:/, '');
    for (var i = 0; i < state.clips.length; i += 1) {
      if (String(state.clips[i].id) === id) return state.clips[i].samples || {};
    }
    return null;
  }

  function ensureTick() {
    if (tickTimer) return;
    tickTimer = window.setInterval(function () {
      if (!state.recording) return;
      paintUi();
      saveStore();
    }, 1000);
  }

  function bind() {
    loadStore();
    paintUi();
    if (state.recording) ensureTick();
    document.addEventListener('click', function (event) {
      var rec = event.target.closest('[data-ow-rec]');
      if (rec) {
        event.preventDefault();
        toggleRecording();
        return;
      }
      if (event.target.closest('#ow-rec-stop') || event.target.closest('[data-ow-rec-stop]')) {
        event.preventDefault();
        stopRecording();
      }
    });
    window.addEventListener('beforeunload', function () {
      if (state.recording) saveStore();
    });
  }

  window.OverwatchRec = {
    start: startRecording,
    stop: stopRecording,
    toggle: toggleRecording,
    isRecording: function () {
      return !!state.recording;
    },
    onSample: onSample,
    getActiveSamples: getActiveSamples,
    getClipSamples: getClipSamples,
    getClips: function () {
      return state.clips.slice();
    }
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
