/* COMSPEC Overwatch — Phase E COP : pistes d’observation / évaluations confirmées */
(function () {
  'use strict';

  var obsGroup = null;
  var assessGroup = null;
  var markersByUid = {};
  var tracksByUid = {};
  var pollTimer = null;
  var confirming = {};
  var started = false;

  function beta() {
    return window.OverwatchBeta || null;
  }

  function map() {
    var b = beta();
    return (b && b.map) || (window.ATAKMap && window.ATAKMap.getMap && window.ATAKMap.getMap()) || null;
  }

  function api(path, opts) {
    var b = beta();
    if (b && typeof b.api === 'function') {
      return b.api(path, opts);
    }
    return Promise.reject(new Error('api_unavailable'));
  }

  function mapId() {
    var b = beta();
    if (b && b.mapId != null) return b.mapId;
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function esc(s) {
    var b = beta();
    if (b && typeof b.escapeHtml === 'function') return b.escapeHtml(s);
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function toast(text) {
    var b = beta();
    if (b && typeof b.toast === 'function') b.toast(text);
  }

  function openDrawer(kicker, title, html) {
    var b = beta();
    if (b && typeof b.openDrawer === 'function') b.openDrawer(kicker, title, html);
  }

  function worldToLatLng(x, y) {
    var b = beta();
    if (b && typeof b.worldToLatLng === 'function') return b.worldToLatLng(x, y);
    if (window.ATAKMap && typeof window.ATAKMap.latLngFromWorld === 'function') {
      return window.ATAKMap.latLngFromWorld(x, y);
    }
    return null;
  }

  function layerChecked(id, defaultOn) {
    var el = document.querySelector('[data-ow-layer="' + id + '"]');
    if (!el) return !!defaultOn;
    return !!el.checked;
  }

  function ensureGroups() {
    var m = map();
    if (!m || typeof L === 'undefined') return false;
    if (!obsGroup) obsGroup = L.layerGroup();
    if (!assessGroup) assessGroup = L.layerGroup();
    syncGroupVisibility();
    return true;
  }

  function syncGroupVisibility() {
    var m = map();
    if (!m || !obsGroup || !assessGroup) return;
    var showObs = layerChecked('tactical-obs', true);
    var showAssess = layerChecked('tactical-assess', true);
    if (showObs) {
      if (!m.hasLayer(obsGroup)) obsGroup.addTo(m);
    } else if (m.hasLayer(obsGroup)) {
      m.removeLayer(obsGroup);
    }
    if (showAssess) {
      if (!m.hasLayer(assessGroup)) assessGroup.addTo(m);
    } else if (m.hasLayer(assessGroup)) {
      m.removeLayer(assessGroup);
    }
  }

  function affiliationColor(aff) {
    var a = String(aff || 'UNKNOWN').toUpperCase();
    if (a === 'FRIEND' || a === 'FRIENDLY' || a === 'ASSUMED_FRIEND') return '#00d69a';
    if (a === 'HOSTILE' || a === 'SUSPECTED_HOSTILE') return '#e05b63';
    if (a === 'NEUTRAL') return '#6ea8fe';
    return '#e7b14d';
  }

  function affiliationLabel(aff) {
    var a = String(aff || 'UNKNOWN').toUpperCase();
    if (a === 'FRIEND' || a === 'FRIENDLY') return 'Ami';
    if (a === 'ASSUMED_FRIEND') return 'Ami présumé';
    if (a === 'HOSTILE') return 'Hostile';
    if (a === 'SUSPECTED_HOSTILE') return 'Hostile présumé';
    if (a === 'NEUTRAL') return 'Neutre';
    return 'Inconnu';
  }

  function statusLabel(status) {
    var s = String(status || '').toLowerCase();
    if (s === 'candidate') return 'Candidat';
    if (s === 'confirmed') return 'Confirmé';
    if (s === 'active') return 'Actif';
    if (s === 'dismissed') return 'Écarté';
    if (s === 'stale') return 'Obsolète';
    return status || '—';
  }

  function sourceLabel(source) {
    var s = String(source || '').toLowerCase();
    if (s === 'salute') return 'SALUTE';
    if (s === 'bda') return 'Bilan des dégâts';
    if (s === 'sigint') return 'Veille radio';
    if (s === 'recon') return 'Reconnaissance';
    return source || 'Observation';
  }

  function assessmentLabel(a) {
    var v = String(a || '').toUpperCase();
    if (v === 'DESTROYED') return 'Détruit';
    if (v === 'DAMAGED') return 'Endommagé';
    if (v === 'NO_DAMAGE') return 'Sans dégât visible';
    if (v === 'UNKNOWN') return 'Indéterminé';
    return '—';
  }

  function trackIcon(track, isAssessment) {
    var color = affiliationColor(track.affiliation);
    var conf = Math.round((Number(track.confidence) || 0.5) * 100);
    var dashed = !isAssessment;
    var cls = 'ow-tactical-track' + (isAssessment ? ' is-assessment' : ' is-observation');
    var html =
      '<span class="' + cls + '" style="--ow-trk:' + color + '">' +
      '<span class="ow-tactical-track__dot"' + (dashed ? ' data-dash="1"' : '') + '></span>' +
      '<span class="ow-tactical-track__lbl">' + esc(track.label || track.track_uid || 'Piste') +
      '<small>' + esc(statusLabel(track.status)) + ' · ' + conf + ' %</small></span></span>';
    return L.divIcon({
      className: 'ow-tactical-track-wrap',
      html: html,
      iconSize: [160, 28],
      iconAnchor: [10, 14]
    });
  }

  function clearMarkers() {
    Object.keys(markersByUid).forEach(function (uid) {
      var layer = markersByUid[uid];
      try {
        if (obsGroup) obsGroup.removeLayer(layer);
        if (assessGroup) assessGroup.removeLayer(layer);
      } catch (e) {}
    });
    markersByUid = {};
  }

  function openTrackDrawer(track) {
    if (!track) return;
    var uid = String(track.track_uid || '');
    var meta = track.meta && typeof track.meta === 'object' ? track.meta : {};
    var isCandidate = String(track.status || '') === 'candidate' || !!meta.requires_confirmation;
    var isBda = String(track.source || '').toLowerCase() === 'bda' || String(meta.kind || '').toLowerCase() === 'bda';
    var grid = '';
    if (track.pos_x != null && track.pos_y != null) {
      var px = Number(track.pos_x);
      var py = Number(track.pos_y);
      var llGrid = worldToLatLng(px, py);
      var b = beta();
      if (llGrid && b && typeof b.gridLabel === 'function') {
        try {
          grid = b.gridLabel(llGrid);
        } catch (e0) {
          grid = Math.round(px) + ' / ' + Math.round(py);
        }
      } else {
        grid = Math.round(px) + ' / ' + Math.round(py);
      }
    }

    var body =
      '<div class="ow-card"><div class="ow-card-body">' +
      '<p><strong>' + esc(track.label || uid) + '</strong></p>' +
      '<p class="ow-help">Couche : ' + esc(track.layer === 'assessment' ? 'Évaluation confirmée' : 'Observation terrain') + '</p>' +
      '<p class="ow-help">Origine : ' + esc(sourceLabel(track.source)) + '</p>' +
      '<p class="ow-help">Affiliation : ' + esc(affiliationLabel(track.affiliation)) + '</p>' +
      '<p class="ow-help">État : ' + esc(statusLabel(track.status)) + '</p>' +
      (meta.assessment
        ? '<p class="ow-help">Bilan : ' + esc(assessmentLabel(meta.assessment)) + '</p>'
        : '') +
      (grid ? '<p class="ow-help">Grille : ' + esc(grid) + '</p>' : '') +
      (track.call_sign_ref
        ? '<p class="ow-help">Signalé par : ' + esc(track.call_sign_ref) + '</p>'
        : '') +
      '</div></div>';

    if (isCandidate || String(track.layer) === 'observation') {
      body +=
        '<div class="ow-card" style="margin-top:8px"><div class="ow-card-body">' +
        '<p class="ow-help">Confirmation humaine requise avant de figer l’évaluation.</p>';
      if (isBda) {
        body +=
          '<label class="ow-row">Bilan observé' +
          '<select id="ow-trk-assessment">' +
          '<option value="UNKNOWN">Indéterminé</option>' +
          '<option value="DAMAGED">Endommagé</option>' +
          '<option value="DESTROYED">Détruit</option>' +
          '<option value="NO_DAMAGE">Sans dégât visible</option>' +
          '</select></label>';
      }
      body +=
        '<label class="ow-row">Affiliation' +
        '<select id="ow-trk-affiliation">' +
        '<option value="UNKNOWN">Inconnu</option>' +
        '<option value="SUSPECTED_HOSTILE">Hostile présumé</option>' +
        '<option value="HOSTILE">Hostile</option>' +
        '<option value="NEUTRAL">Neutre</option>' +
        '<option value="FRIEND">Ami</option>' +
        '</select></label>' +
        '<div class="ow-row" style="gap:8px;margin-top:10px">' +
        '<button type="button" class="ow-btn" data-ow-trk-act="confirm" data-uid="' +
        esc(uid) +
        '">Confirmer</button>' +
        '<button type="button" class="ow-btn ow-btn--ghost" data-ow-trk-act="dismiss" data-uid="' +
        esc(uid) +
        '">Écarter</button>' +
        '</div></div></div>';
    }

    openDrawer('Piste tactique', track.label || uid, body);

    window.setTimeout(function () {
      var affSel = document.getElementById('ow-trk-affiliation');
      if (affSel && track.affiliation) {
        affSel.value = String(track.affiliation).toUpperCase();
      }
      var asSel = document.getElementById('ow-trk-assessment');
      if (asSel && meta.assessment) {
        asSel.value = String(meta.assessment).toUpperCase();
      }
    }, 0);
  }

  function confirmTrack(uid, status) {
    uid = String(uid || '');
    if (!uid || confirming[uid]) return;
    confirming[uid] = true;
    var body = {
      mapId: mapId(),
      status: status
    };
    var aff = document.getElementById('ow-trk-affiliation');
    if (aff && aff.value) body.affiliation = aff.value;
    var asmt = document.getElementById('ow-trk-assessment');
    if (asmt && asmt.value) body.assessment = asmt.value;

    api('/api/atak/tracks/' + encodeURIComponent(uid) + '/confirm', {
      method: 'POST',
      body: body
    })
      .then(function (payload) {
        confirming[uid] = false;
        if (!payload || !payload.ok) {
          toast('Confirmation refusée.');
          return;
        }
        toast(status === 'dismissed' ? 'Piste écartée.' : 'Piste confirmée.');
        return refresh();
      })
      .catch(function () {
        confirming[uid] = false;
        toast('Liaison indisponible pour confirmer.');
      });
  }

  function renderTracks(list) {
    if (!ensureGroups()) return;
    clearMarkers();
    tracksByUid = {};
    (list || []).forEach(function (track) {
      if (!track || track.pos_x == null || track.pos_y == null) return;
      if (String(track.layer || '') === 'reality') return;
      if (String(track.status || '') === 'dismissed') return;
      var uid = String(track.track_uid || track.id || '');
      if (!uid) return;
      var ll = worldToLatLng(Number(track.pos_x), Number(track.pos_y));
      if (!ll) return;
      tracksByUid[uid] = track;
      var isAssessment = String(track.layer || '') === 'assessment' || String(track.status || '') === 'confirmed';
      var marker = L.marker(ll, {
        icon: trackIcon(track, isAssessment),
        zIndexOffset: isAssessment ? 420 : 380
      });
      marker.on('click', function () {
        openTrackDrawer(track);
      });
      var b = beta();
      if (b && typeof b.bindLayerContext === 'function') {
        try {
          b.bindLayerContext(marker, 'tactical_track', uid, track.label || uid);
        } catch (e1) {}
      }
      if (isAssessment) {
        assessGroup.addLayer(marker);
      } else {
        obsGroup.addLayer(marker);
      }
      markersByUid[uid] = marker;
    });
    syncGroupVisibility();
    paintCount();
  }

  function paintCount() {
    var el = document.getElementById('ow-tactical-tracks-help');
    if (!el) return;
    var n = Object.keys(tracksByUid).length;
    el.textContent =
      n < 1
        ? 'Aucune piste d’observation pour le moment.'
        : n + ' piste' + (n > 1 ? 's' : '') + ' d’observation ou d’évaluation.';
  }

  function refresh() {
    if (!ensureGroups()) return Promise.resolve();
    return api('/api/atak/tracks?mapId=' + encodeURIComponent(mapId()) + '&limit=200')
      .then(function (payload) {
        var list = (payload && Array.isArray(payload.tracks) ? payload.tracks : []) || [];
        renderTracks(list);
      })
      .catch(function () {
        /* silence : calque optionnel */
      });
  }

  function bindUi() {
    document.addEventListener('change', function (ev) {
      var t = ev.target;
      if (!t || !t.getAttribute) return;
      var layer = t.getAttribute('data-ow-layer');
      if (layer === 'tactical-obs' || layer === 'tactical-assess') {
        try {
          localStorage.setItem('athena:ow-layer-' + layer, t.checked ? '1' : '0');
        } catch (e) {}
        syncGroupVisibility();
      }
    });
    document.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('[data-ow-trk-act]') : null;
      if (!btn) return;
      var act = btn.getAttribute('data-ow-trk-act');
      var uid = btn.getAttribute('data-uid');
      if (act === 'confirm') confirmTrack(uid, 'confirmed');
      if (act === 'dismiss') confirmTrack(uid, 'dismissed');
    });
  }

  function start() {
    if (started) return;
    if (!map() || !beta()) return;
    started = true;
    ensureGroups();
    bindUi();
    refresh();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refresh, 8000);
  }

  function boot() {
    if (beta() && map()) {
      start();
      return;
    }
    var tries = 0;
    var t = setInterval(function () {
      tries++;
      if (beta() && map()) {
        clearInterval(t);
        start();
      } else if (tries > 40) {
        clearInterval(t);
      }
    }, 250);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.OverwatchTacticalTracks = {
    refresh: refresh,
    start: start,
    getTracks: function () {
      return tracksByUid;
    }
  };
})();
