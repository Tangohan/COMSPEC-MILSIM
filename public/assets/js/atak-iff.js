/* COMSPEC ATAK — Identification (IFF défi / réponse) */
window.ATAKIFF = (function () {
  var refreshTimer = null;
  var pickBound = false;

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

  function statusLabel(st) {
    var s = String(st || 'PENDING').toUpperCase();
    if (s === 'FRIENDLY') return 'Ami confirmé';
    if (s === 'SUSPECT') return 'Suspect';
    if (s === 'EXPIRED') return 'Défi expiré';
    return 'En attente de réponse';
  }

  function statusTone(st) {
    var s = String(st || 'PENDING').toUpperCase();
    if (s === 'FRIENDLY') return 'ok';
    if (s === 'SUSPECT') return 'err';
    if (s === 'EXPIRED') return 'warn';
    return 'muted';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setFeedback(msg, tone) {
    var el = document.getElementById('atak-iff-feedback');
    if (!el) return;
    el.hidden = !msg;
    el.textContent = msg || '';
    el.className = 'atak-iff-feedback' + (tone ? ' atak-iff-feedback--' + tone : '');
  }

  function qs(path) {
    var sep = path.indexOf('?') >= 0 ? '&' : '?';
    return apiBase() + path + sep + 'missionId=' + encodeURIComponent(missionId()) +
      '&mapId=' + encodeURIComponent(mapId());
  }

  function loadCurrent() {
    return fetch(qs('/api/iff/current'), { credentials: 'include', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (c) {
        var codeEl = document.getElementById('atak-iff-challenge-code');
        var validEl = document.getElementById('atak-iff-valid-until');
        var emptyEl = document.getElementById('atak-iff-empty-challenge');
        if (!c || !c.code) {
          if (codeEl) codeEl.textContent = '—';
          if (validEl) validEl.textContent = 'Aucun défi actif pour cette carte.';
          if (emptyEl) emptyEl.hidden = false;
          return null;
        }
        if (codeEl) codeEl.textContent = c.code;
        if (validEl) {
          validEl.textContent = c.valid_until
            ? 'Valable jusqu’à ' + String(c.valid_until).replace('T', ' ').slice(0, 19)
            : '';
        }
        if (emptyEl) emptyEl.hidden = true;
        return c;
      })
      .catch(function () {
        var validEl = document.getElementById('atak-iff-valid-until');
        if (validEl) validEl.textContent = 'Impossible de charger le défi courant.';
        return null;
      });
  }

  function loadAssets() {
    return fetch(qs('/api/iff/assets'), { credentials: 'include', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (assets) {
        var list = document.getElementById('atak-iff-assets-list');
        var select = document.getElementById('atak-iff-respond-asset');
        if (!Array.isArray(assets)) assets = [];
        if (list) {
          if (assets.length === 0) {
            list.innerHTML =
              '<div class="atak-empty-state atak-empty-state--compact">' +
              '<p class="atak-empty-state-title">Aucune unité inscrite</p>' +
              '<p class="atak-empty-state-text">Inscrivez les unités en liaison, ou attendez une réponse depuis le théâtre.</p></div>';
          } else {
            list.innerHTML = assets.map(function (a) {
              var tone = statusTone(a.response_status);
              return '<div class="atak-iff-asset atak-iff-asset--' + tone + '">' +
                '<div class="atak-iff-asset-head">' +
                '<span class="atak-iff-asset-cs">' + escapeHtml(a.callsign || a.asset_id) + '</span>' +
                '<span class="atak-pill atak-pill--' + tone + '">' + escapeHtml(statusLabel(a.response_status)) + '</span>' +
                '</div>' +
                (a.responded_at
                  ? '<div class="atak-iff-asset-meta">Réponse reçue · ' + escapeHtml(String(a.responded_at).replace('T', ' ').slice(0, 19)) + '</div>'
                  : '') +
                '</div>';
            }).join('');
          }
        }
        if (select) {
          var prev = select.value;
          select.innerHTML = '<option value="">Choisir une unité…</option>' +
            assets.map(function (a) {
              return '<option value="' + escapeHtml(a.asset_id) + '">' +
                escapeHtml(a.callsign || a.asset_id) + '</option>';
            }).join('');
          if (prev) select.value = prev;
        }
        return assets;
      })
      .catch(function () {
        var list = document.getElementById('atak-iff-assets-list');
        if (list) {
          list.innerHTML = '<p class="atak-panel-hint">Impossible de charger les réponses d’identification.</p>';
        }
        return [];
      });
  }

  function refresh() {
    if (!apiBase()) return Promise.resolve();
    return Promise.all([loadCurrent(), loadAssets()]);
  }

  function generateChallenge() {
    if (!apiBase()) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Athena indisponible.');
      return;
    }
    var codeInput = document.getElementById('atak-iff-new-code');
    var minsInput = document.getElementById('atak-iff-valid-minutes');
    var code = codeInput ? String(codeInput.value || '').trim().toUpperCase() : '';
    var mins = minsInput ? parseInt(minsInput.value, 10) : 30;
    if (isNaN(mins) || mins < 5) mins = 5;
    if (mins > 240) mins = 240;
    setFeedback('Publication du défi…', 'muted');
    fetch(apiBase() + '/api/iff/challenge', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        missionId: missionId(),
        mapId: mapId(),
        code: code || undefined,
        validMinutes: mins,
        syncUnits: true
      })
    }).then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, data: d }; });
    }).then(function (res) {
      if (!res.ok) {
        setFeedback((res.data && res.data.message) || 'Impossible de publier le défi.', 'err');
        return;
      }
      if (codeInput) codeInput.value = '';
      setFeedback('Défi publié' + (res.data && res.data.code ? ' : ' + res.data.code : '') + '.', 'ok');
      refresh().then(function () { syncUnits(); });
    }).catch(function () {
      setFeedback('Erreur réseau lors de la publication du défi.', 'err');
    });
  }

  function syncUnits() {
    if (!apiBase()) return;
    setFeedback('Inscription des unités en liaison…', 'muted');
    fetch(apiBase() + '/api/units?mapId=' + encodeURIComponent(mapId()), {
      credentials: 'include',
      cache: 'no-store'
    }).then(function (r) { return r.ok ? r.json() : []; })
      .then(function (units) {
        if (!Array.isArray(units)) {
          units = (units && units.units) ? units.units : [];
        }
        var assets = units.map(function (u) {
          var id = String(u.id || u.unit_id || u.call_sign || u.callsign || '');
          var cs = String(u.call_sign || u.callsign || id);
          if (!id) return null;
          return {
            assetId: id,
            callsign: cs,
            platformType: u.vehicle_class || u.type || 'infantry'
          };
        }).filter(Boolean);
        return fetch(apiBase() + '/api/iff/assets/sync', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            missionId: missionId(),
            mapId: mapId(),
            assets: assets
          })
        });
      })
      .then(function (r) {
        if (!r) return;
        return r.json().then(function (d) { return { ok: r.ok, data: d }; });
      })
      .then(function (res) {
        if (!res) return;
        if (!res.ok) {
          setFeedback((res.data && res.data.message) || 'Inscription impossible.', 'err');
          return;
        }
        var n = (res.data && res.data.count != null) ? res.data.count : 0;
        setFeedback(n + ' unité(s) inscrite(s) pour le défi courant.', 'ok');
        loadAssets();
      })
      .catch(function () {
        setFeedback('Impossible d’inscrire les unités.', 'err');
      });
  }

  function submitResponse() {
    if (!apiBase()) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Athena indisponible.');
      return;
    }
    var assetEl = document.getElementById('atak-iff-respond-asset');
    var codeEl = document.getElementById('atak-iff-respond-code');
    var assetId = assetEl ? String(assetEl.value || '').trim() : '';
    var responseCode = codeEl ? String(codeEl.value || '').trim() : '';
    if (!assetId) {
      setFeedback('Choisissez une unité.', 'warn');
      return;
    }
    if (!responseCode) {
      setFeedback('Saisissez le code de réponse.', 'warn');
      return;
    }
    fetch(apiBase() + '/api/iff/respond', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        missionId: missionId(),
        mapId: mapId(),
        assetId: assetId,
        responseCode: responseCode,
        callsign: author()
      })
    }).then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, data: d }; });
    }).then(function (res) {
      if (!res.ok) {
        setFeedback((res.data && (res.data.message || res.data.error)) || 'Réponse refusée.', 'err');
        return;
      }
      var st = statusLabel(res.data && res.data.status);
      setFeedback('Résultat : ' + st + '.', statusTone(res.data && res.data.status) === 'ok' ? 'ok' : 'warn');
      if (codeEl) codeEl.value = '';
      loadAssets();
    }).catch(function () {
      setFeedback('Erreur réseau lors de l’envoi de la réponse.', 'err');
    });
  }

  function onTabActivated() {
    refresh();
  }

  function bind() {
    if (pickBound) return;
    pickBound = true;
    var gen = document.getElementById('atak-iff-generate');
    var sync = document.getElementById('atak-iff-sync-units');
    var resp = document.getElementById('atak-iff-respond-submit');
    var refreshBtn = document.getElementById('atak-iff-refresh');
    if (gen) gen.addEventListener('click', generateChallenge);
    if (sync) sync.addEventListener('click', syncUnits);
    if (resp) resp.addEventListener('click', submitResponse);
    if (refreshBtn) refreshBtn.addEventListener('click', function () {
      setFeedback('Actualisation…', 'muted');
      refresh().then(function () { setFeedback('', null); });
    });
    refresh();
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(function () {
      var tab = document.getElementById('tab-identification');
      if (tab && tab.classList.contains('active')) refresh();
    }, 20000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  return {
    refresh: refresh,
    onTabActivated: onTabActivated,
    missionId: missionId
  };
})();
