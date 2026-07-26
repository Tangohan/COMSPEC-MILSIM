/* COMSPEC ATAK — Tableau de situation (SITREP / intel fusionné) */
window.ATAKSitrep = (function () {
  var layers = [];
  var pickMode = false;
  var pickHandler = null;
  var refreshTimer = null;
  var bound = false;
  var listBound = false;

  var TARGET_LABELS = {
    INFANTRY: 'Infanterie',
    VEHICLE: 'Véhicule',
    ARMOR: 'Blindé',
    AIR_DEFENSE: 'Défense antiaérienne',
    UNKNOWN: 'Non identifié'
  };

  var STATUS_LABELS = {
    TEMPORARY: 'Provisoire',
    CORROBORATED: 'Corroboré',
    CONFIRMED: 'Confirmé'
  };

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '');
  }

  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function tenantId() {
    if (window.ATAK_TENANT_ID != null && parseInt(window.ATAK_TENANT_ID, 10) > 0) {
      return parseInt(window.ATAK_TENANT_ID, 10);
    }
    var u = window.ATAK_USER || {};
    var t = parseInt(u.tenantId, 10);
    return !isNaN(t) && t > 0 ? t : 1;
  }

  function missionId() {
    return 'mission_' + tenantId() + '_map_' + mapId();
  }

  function author() {
    var u = window.ATAK_USER || {};
    return u.callsign || u.displayName || 'TOC';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function targetLabel(t) {
    var k = String(t || 'UNKNOWN').toUpperCase();
    return TARGET_LABELS[k] || k;
  }

  function statusLabel(s) {
    var k = String(s || 'TEMPORARY').toUpperCase();
    return STATUS_LABELS[k] || k;
  }

  function statusTone(s) {
    var k = String(s || 'TEMPORARY').toUpperCase();
    if (k === 'CONFIRMED') return 'err';
    if (k === 'CORROBORATED') return 'warn';
    return 'muted';
  }

  function setFeedback(msg, tone) {
    var el = document.getElementById('atak-sitrep-feedback');
    if (!el) return;
    el.hidden = !msg;
    el.textContent = msg || '';
    el.className = 'atak-sitrep-feedback' + (tone ? ' atak-sitrep-feedback--' + tone : '');
  }

  function setPickHint(on) {
    var hint = document.getElementById('atak-sitrep-pick-hint');
    var btn = document.getElementById('atak-sitrep-pick-map');
    if (hint) hint.hidden = !on;
    if (btn) {
      btn.classList.toggle('is-active', !!on);
      btn.textContent = on ? 'Annuler le pointage' : 'Pointer sur la carte';
    }
    document.body.classList.toggle('atak-sitrep-picking', !!on);
  }

  function clearMapLayers() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    layers.forEach(function (l) {
      try {
        if (map && l) map.removeLayer(l);
      } catch (e) {}
    });
    layers = [];
  }

  function removeMapMarkerById(reportId) {
    var id = String(reportId || '');
    if (!id) return;
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    layers = layers.filter(function (l) {
      if (!l || String(l._sitrepId || '') !== id) return true;
      try {
        if (map) map.removeLayer(l);
      } catch (e) {}
      return false;
    });
  }

  function addMapMarker(report) {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map || typeof L === 'undefined') return;
    var x = parseFloat(report.pos_x);
    var y = parseFloat(report.pos_y);
    if (isNaN(x) || isNaN(y)) return;
    var tone = statusTone(report.status);
    var col = tone === 'err' ? '#f87171' : (tone === 'warn' ? '#fbbf24' : '#eab308');
    var layer = L.circleMarker([y, x], {
      radius: 9,
      color: col,
      weight: 2,
      fillColor: col,
      fillOpacity: 0.55
    });
    layer._sitrepId = String(report.id || '');
    layer.bindPopup(
      '<strong>' + escapeHtml(targetLabel(report.target_type)) + '</strong><br>' +
      escapeHtml(statusLabel(report.status)) +
      (report.source_callsign ? '<br>Source : ' + escapeHtml(report.source_callsign) : '')
    );
    layer.addTo(map);
    layers.push(layer);
  }

  function fillFormCoords(x, y) {
    var xEl = document.getElementById('atak-sitrep-x');
    var yEl = document.getElementById('atak-sitrep-y');
    var gridEl = document.getElementById('atak-sitrep-grid-display');
    if (xEl) xEl.value = Math.round(x);
    if (yEl) yEl.value = Math.round(y);
    if (gridEl) gridEl.textContent = 'Grille ' + Math.round(x) + ' / ' + Math.round(y);
  }

  function stopPickMode() {
    pickMode = false;
    setPickHint(false);
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (map && pickHandler) {
      try { map.off('click', pickHandler); } catch (e) {}
    }
    pickHandler = null;
  }

  function startPickMode() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map) {
      setFeedback('Carte indisponible pour le pointage.', 'warn');
      return;
    }
    if (pickMode) {
      stopPickMode();
      return;
    }
    pickMode = true;
    setPickHint(true);
    pickHandler = function (e) {
      if (!e || !e.latlng) return;
      fillFormCoords(e.latlng.lng, e.latlng.lat);
      stopPickMode();
      setFeedback('Position reprise depuis la carte.', 'ok');
      var tabBtn = document.querySelector('.atak-tab[data-tab="situation"]');
      if (tabBtn && !document.getElementById('tab-situation').classList.contains('active')) {
        tabBtn.click();
      }
    };
    map.on('click', pickHandler);
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('Cliquez sur la carte pour placer le signalement.');
    }
  }

  function prefillFromMap(latlng) {
    if (!latlng) return;
    fillFormCoords(latlng.lng, latlng.lat);
    var tabBtn = document.querySelector('.atak-tab[data-tab="situation"]');
    if (tabBtn) tabBtn.click();
    setFeedback('Position reprise — choisissez le type puis envoyez.', 'ok');
  }

  function updateCount(n) {
    var countEl = document.getElementById('atak-sitrep-count');
    if (!countEl) return;
    countEl.textContent = String(n);
    countEl.hidden = n === 0;
  }

  function deleteReport(id) {
    if (!apiBase()) {
      return Promise.reject(new Error('no-api'));
    }
    var reportId = String(id || '');
    if (!reportId) return Promise.reject(new Error('missing-id'));
    var url = apiBase() + '/api/intel/report/' + encodeURIComponent(reportId) +
      '?missionId=' + encodeURIComponent(missionId()) +
      '&mapId=' + encodeURIComponent(mapId());
    return fetch(url, {
      method: 'DELETE',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        missionId: missionId(),
        mapId: mapId()
      })
    }).then(function (r) {
      return r.json().then(function (d) {
        return { ok: r.ok, data: d };
      }).catch(function () {
        return { ok: r.ok, data: null };
      });
    }).then(function (res) {
      if (!res.ok) {
        var msg = (res.data && res.data.message) || 'Impossible de supprimer ce signalement.';
        throw new Error(msg);
      }
      return true;
    });
  }

  function removeListItem(reportId) {
    var list = document.getElementById('atak-sitrep-list');
    if (!list) return;
    var row = list.querySelector('.atak-sitrep-item[data-id="' + String(reportId).replace(/"/g, '') + '"]');
    if (row) row.remove();
    var remaining = list.querySelectorAll('.atak-sitrep-item').length;
    updateCount(remaining);
    if (remaining === 0) {
      list.innerHTML =
        '<div class="atak-empty-state atak-empty-state--compact">' +
        '<p class="atak-empty-state-title">Aucune situation signalée</p>' +
        '<p class="atak-empty-state-text">Créez un signalement depuis la carte ou le formulaire ci-dessous. Les remontées du théâtre fusionnent ici.</p></div>';
    }
  }

  function bindList() {
    var list = document.getElementById('atak-sitrep-list');
    if (!list || listBound) return;
    listBound = true;
    list.addEventListener('click', function (e) {
      var delBtn = e.target.closest('[data-delete-sitrep]');
      if (delBtn) {
        e.preventDefault();
        e.stopPropagation();
        var delId = delBtn.getAttribute('data-delete-sitrep');
        if (!delId) return;
        if (!window.confirm('Supprimer ce signalement ?')) return;
        delBtn.disabled = true;
        setFeedback('Suppression…', 'muted');
        deleteReport(delId).then(function () {
          removeMapMarkerById(delId);
          removeListItem(delId);
          setFeedback('Signalement retiré du tableau de situation.', 'ok');
          if (window.ATAKShowNotification) {
            window.ATAKShowNotification('Signalement retiré.');
          }
        }).catch(function (err) {
          delBtn.disabled = false;
          var msg = (err && err.message) ? err.message : 'Impossible de supprimer ce signalement.';
          setFeedback(msg, 'err');
          if (window.ATAKShowError) window.ATAKShowError(msg);
        });
        return;
      }
      var item = e.target.closest('.atak-sitrep-item');
      if (!item || !list.contains(item)) return;
      var x = parseFloat(item.getAttribute('data-x'));
      var y = parseFloat(item.getAttribute('data-y'));
      var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
      if (map && !isNaN(x) && !isNaN(y)) {
        try { map.setView([y, x], Math.max(map.getZoom(), 4)); } catch (err) {}
      }
    });
  }

  function loadList() {
    if (!apiBase()) return Promise.resolve();
    var list = document.getElementById('atak-sitrep-list');
    if (list) {
      list.innerHTML = '<p class="atak-panel-hint">Chargement du tableau de situation…</p>';
    }
    var url = apiBase() + '/api/intel/fused?missionId=' + encodeURIComponent(missionId()) +
      '&mapId=' + encodeURIComponent(mapId());
    return fetch(url, { credentials: 'include', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (reports) {
        clearMapLayers();
        if (!Array.isArray(reports)) reports = [];
        if (list) {
          if (reports.length === 0) {
            list.innerHTML =
              '<div class="atak-empty-state atak-empty-state--compact">' +
              '<p class="atak-empty-state-title">Aucune situation signalée</p>' +
              '<p class="atak-empty-state-text">Créez un signalement depuis la carte ou le formulaire ci-dessous. Les remontées du théâtre fusionnent ici.</p></div>';
          } else {
            list.innerHTML = reports.map(function (r) {
              var tone = statusTone(r.status);
              var merged = parseInt(r.merged_count, 10) || 1;
              var rid = String(r.id || '');
              return '<div class="atak-sitrep-item atak-sitrep-item--' + tone + '" data-id="' +
                escapeHtml(rid) + '" data-x="' + escapeHtml(r.pos_x) + '" data-y="' + escapeHtml(r.pos_y) + '" role="button" tabindex="0">' +
                '<div class="atak-sitrep-item-head">' +
                '<span class="atak-sitrep-item-type">' + escapeHtml(targetLabel(r.target_type)) + '</span>' +
                '<span class="atak-pill atak-pill--' + tone + '">' + escapeHtml(statusLabel(r.status)) + '</span>' +
                '</div>' +
                '<div class="atak-sitrep-item-meta">' +
                merged + ' source' + (merged > 1 ? 's' : '') +
                (r.source_callsign ? ' · ' + escapeHtml(r.source_callsign) : '') +
                ' · grille ' + Math.round(parseFloat(r.pos_x) || 0) + ' / ' + Math.round(parseFloat(r.pos_y) || 0) +
                '</div>' +
                (rid
                  ? '<div class="atak-sitrep-item-actions">' +
                    '<button type="button" class="atak-sitrep-delete" data-delete-sitrep="' + escapeHtml(rid) + '">Supprimer</button>' +
                    '</div>'
                  : '') +
                '</div>';
            }).join('');
          }
        }
        reports.forEach(addMapMarker);
        updateCount(reports.length);
        return reports;
      })
      .catch(function () {
        if (list) {
          list.innerHTML = '<p class="atak-panel-hint">Impossible de charger le tableau de situation.</p>';
        }
      });
  }

  function submitReport() {
    if (!apiBase()) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Athena indisponible.');
      return;
    }
    var typeEl = document.getElementById('atak-sitrep-target');
    var xEl = document.getElementById('atak-sitrep-x');
    var yEl = document.getElementById('atak-sitrep-y');
    var sourceEl = document.getElementById('atak-sitrep-source');
    var target = typeEl ? String(typeEl.value || 'UNKNOWN') : 'UNKNOWN';
    var x = xEl ? parseFloat(xEl.value) : NaN;
    var y = yEl ? parseFloat(yEl.value) : NaN;
    var source = sourceEl ? String(sourceEl.value || '').trim() : '';
    if (!source) source = author();
    if (isNaN(x) || isNaN(y)) {
      setFeedback('Indiquez une position (pointez sur la carte).', 'warn');
      return;
    }
    setFeedback('Envoi du signalement…', 'muted');
    fetch(apiBase() + '/api/intel/report', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        missionId: missionId(),
        mapId: mapId(),
        target_type: target,
        pos_x: x,
        pos_y: y,
        source_callsign: source,
        report_type: 'SITREP'
      })
    }).then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, data: d }; });
    }).then(function (res) {
      if (!res.ok) {
        setFeedback((res.data && res.data.message) || 'Signalement refusé.', 'err');
        return;
      }
      var merged = res.data && ((parseInt(res.data.merged_count, 10) || 1) > 1);
      setFeedback(merged
        ? 'Signalement fusionné avec une situation proche.'
        : 'Signalement publié sur le tableau de situation.', 'ok');
      loadList();
    }).catch(function () {
      setFeedback('Erreur réseau lors de l’envoi.', 'err');
    });
  }

  function bind() {
    if (bound) return;
    bound = true;
    bindList();
    var pickBtn = document.getElementById('atak-sitrep-pick-map');
    var submitBtn = document.getElementById('atak-sitrep-submit');
    var refreshBtn = document.getElementById('atak-sitrep-refresh');
    var sourceEl = document.getElementById('atak-sitrep-source');
    if (sourceEl && !sourceEl.value) sourceEl.value = author();
    if (pickBtn) pickBtn.addEventListener('click', startPickMode);
    if (submitBtn) submitBtn.addEventListener('click', submitReport);
    if (refreshBtn) refreshBtn.addEventListener('click', function () {
      setFeedback('Actualisation…', 'muted');
      loadList().then(function () { setFeedback('', null); });
    });
    loadList();
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(function () {
      var tab = document.getElementById('tab-situation');
      if (tab && tab.classList.contains('active')) loadList();
    }, 25000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  return {
    refresh: loadList,
    onTabActivated: loadList,
    prefillFromMap: prefillFromMap,
    startPickMode: startPickMode,
    missionId: missionId
  };
})();
