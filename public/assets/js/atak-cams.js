/* COMSPEC ATAK — Cams : aperçus casque/drone + photos terrain */
window.ATAKCams = (function () {
  var apiBase = null;
  var lastFeedIds = {};
  var lightboxBound = false;

  function getApiBase() {
    if (apiBase !== undefined && apiBase !== null) return apiBase;
    apiBase = window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
    return apiBase || '';
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function resolveMediaUrl(u) {
    if (!u) return '';
    u = String(u);
    if (u.indexOf('http') === 0 || u.indexOf('//') === 0) return u;
    var base = getApiBase();
    // apiBase se termine souvent par /api → remonter à la racine publique pour /uploads
    var origin = String(base || '').replace(/\/$/, '').replace(/\/api(?:\/atak)?$/, '');
    return origin + (u.charAt(0) === '/' ? u : '/' + u);
  }

  function deviceLabel(p) {
    if (p && p.device_label) return String(p.device_label);
    var d = String((p && p.device_type) || 'CTAB').toUpperCase();
    if (d === 'HELMET' || d === 'HCAM') return 'Caméra casque';
    if (d === 'DRONE') return 'Caméra drone';
    if (d === 'UAV' || d === 'VEHICLE') return 'Caméra aérienne';
    if (d === 'CTAB' || d === 'TABLET') return 'Photo tablette';
    return 'Photo terrain';
  }

  function kindClass(kind) {
    kind = String(kind || '').toLowerCase();
    if (kind === 'drone' || kind === 'uav') return 'atak-cam-tile--drone';
    if (kind === 'vehicle') return 'atak-cam-tile--vehicle';
    return 'atak-cam-tile--helmet';
  }

  function formatAge(sec) {
    if (sec == null || isNaN(sec)) return '';
    sec = Math.max(0, Math.floor(sec));
    if (sec < 60) return 'il y a ' + sec + ' s';
    if (sec < 3600) return 'il y a ' + Math.floor(sec / 60) + ' min';
    return 'il y a ' + Math.floor(sec / 3600) + ' h';
  }

  function addPhotoMarkerOnMap(photo) {
    if (!window.ATAKMap || !window.ATAKMap.addIntelPhotoMarker) return;
    var px = photo.pos_x != null ? parseFloat(photo.pos_x) : null;
    var py = photo.pos_y != null ? parseFloat(photo.pos_y) : null;
    if (px == null || py == null || (Math.abs(px) < 0.5 && Math.abs(py) < 0.5)) return;
    var src = resolveMediaUrl(photo.url || photo.path || photo.snapshot_url || '');
    if (!src) return;
    window.ATAKMap.addIntelPhotoMarker(photo.id || photo.snapshot_id, py, px, src);
  }

  function ensureLightbox() {
    if (lightboxBound) return;
    lightboxBound = true;
    document.addEventListener('click', function (ev) {
      var thumb = ev.target && ev.target.closest ? ev.target.closest('[data-atak-cam-full]') : null;
      if (!thumb) return;
      var src = thumb.getAttribute('data-atak-cam-full');
      if (!src) return;
      ev.preventDefault();
      var existing = document.getElementById('atak-cam-lightbox');
      if (existing) existing.remove();
      var overlay = document.createElement('div');
      overlay.id = 'atak-cam-lightbox';
      overlay.className = 'atak-cam-lightbox';
      overlay.innerHTML =
        '<button type="button" class="atak-cam-lightbox-close" aria-label="Fermer">×</button>' +
        '<img src="' + escapeHtml(src) + '" alt="Aperçu agrandi" />';
      document.body.appendChild(overlay);
      function close() {
        overlay.remove();
      }
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || (e.target && e.target.classList && e.target.classList.contains('atak-cam-lightbox-close'))) {
          close();
        }
      });
    });
  }

  function emptyStateHtml(kind) {
    if (kind === 'photos') {
      return (
        '<div class="atak-empty-state">' +
          '<div class="atak-empty-state-icon" aria-hidden="true">◫</div>' +
          '<p class="atak-empty-state-title">Aucune photo reçue</p>' +
          '<p class="atak-empty-state-text">Les vues capturées depuis la tablette ou les caméras casque apparaîtront ici dès leur remontée.</p>' +
        '</div>'
      );
    }
    return (
      '<div class="atak-empty-state">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">▣</div>' +
        '<p class="atak-empty-state-title">Aucune caméra détectée</p>' +
        '<p class="atak-empty-state-text">Les caméras casque et drones actifs en jeu apparaîtront ici. Seuls des aperçus photo sont transmis, pas de vidéo en direct.</p>' +
      '</div>'
    );
  }

  function fetchJsonForMap(path, mapId) {
    var base = getApiBase();
    var sep = path.indexOf('?') >= 0 ? '&' : '?';
    var url = (base ? base : '') + path + sep + 'mapId=' + encodeURIComponent(mapId);
    return fetch(url, { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .catch(function () { return null; });
  }

  function fetchWithMapFallback(path, parseResult) {
    var mid = getMapId();
    return fetchJsonForMap(path, mid).then(function (data) {
      var result = parseResult(data);
      if (result && ((Array.isArray(result) && result.length) || (result.feeds && result.feeds.length))) {
        return result;
      }
      if (mid === 1) return result;
      return fetchJsonForMap(path, 1).then(function (fallback) {
        var fb = parseResult(fallback);
        if (Array.isArray(fb) && fb.length) return fb;
        if (fb && fb.feeds && fb.feeds.length) return fb;
        return result;
      });
    });
  }

  function renderFeeds(feeds) {
    var list = Array.isArray(feeds) ? feeds : [];
    if (!list.length) return '';
    var html = '<div class="atak-cams-section">' +
      '<div class="atak-cams-section-head">' +
        '<span class="atak-cams-section-title">Caméras en ligne</span>' +
        '<span class="atak-cams-section-hint">Aperçus photo · pas de flux vidéo</span>' +
      '</div>' +
      '<div class="atak-cams-tiles">';
    list.forEach(function (f) {
      var online = !!f.online;
      var src = resolveMediaUrl(f.snapshot_url || '');
      var kind = String(f.kind || 'helmet').toLowerCase();
      var kindLabel = f.kind_label || (kind === 'drone' || kind === 'uav' ? 'Caméra drone' : 'Caméra casque');
      var status = online ? 'En ligne' : 'Hors ligne';
      var age = formatAge(f.age_sec);
      html +=
        '<article class="atak-cam-tile ' + kindClass(kind) + (online ? ' is-online' : ' is-offline') + '" data-feed-id="' + escapeHtml(f.id || '') + '">' +
          '<div class="atak-cam-tile-media">' +
            (src
              ? '<img src="' + escapeHtml(src) + '" alt="" loading="lazy" data-atak-cam-full="' + escapeHtml(src) + '" />'
              : '<div class="atak-cam-tile-placeholder" aria-hidden="true"></div>') +
            '<span class="atak-cam-tile-badge">' + escapeHtml(kindLabel) + '</span>' +
            '<span class="atak-cam-tile-status">' + escapeHtml(status) + (age ? ' · ' + escapeHtml(age) : '') + '</span>' +
          '</div>' +
          '<div class="atak-cam-tile-meta">' +
            '<strong>' + escapeHtml(f.label || kindLabel) + '</strong>' +
            (f.callsign ? '<span>' + escapeHtml(f.callsign) + '</span>' : '') +
            (f.grid ? '<span>Grille ' + escapeHtml(f.grid) + '</span>' : '') +
          '</div>' +
        '</article>';
      if (src && f.pos_x != null && f.pos_y != null) {
        addPhotoMarkerOnMap({
          id: 'feed-' + (f.id || f.snapshot_id),
          pos_x: f.pos_x,
          pos_y: f.pos_y,
          url: src,
          snapshot_id: f.snapshot_id
        });
      }
    });
    html += '</div></div>';
    return html;
  }

  function formatPhotoStamp(p) {
    var raw = p && (p.created_at || p.captured_at || p.capturedAt || p.timestamp);
    if (raw == null || raw === '') return '';
    // Epoch secondes ou ms
    if (typeof raw === 'number' || (/^\d+$/.test(String(raw)))) {
      var n = Number(raw);
      if (n < 1e12) n *= 1000;
      var dNum = new Date(n);
      if (!isNaN(dNum.getTime())) {
        return formatStampDate(dNum);
      }
    }
    var s = String(raw).trim();
    var iso = s.indexOf('T') >= 0 ? s : s.replace(' ', 'T');
    if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += 'Z';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return s;
    return formatStampDate(d);
  }

  function formatStampDate(d) {
    var dd = d.getUTCDate();
    var mm = d.getUTCMonth() + 1;
    var hh = d.getUTCHours();
    var mi = d.getUTCMinutes();
    return (dd < 10 ? '0' : '') + dd + '/' + (mm < 10 ? '0' : '') + mm +
      ' · ' + (hh < 10 ? '0' : '') + hh + ':' + (mi < 10 ? '0' : '') + mi + ' Z';
  }

  function renderPhotos(photos, title) {
    var list = Array.isArray(photos) ? photos : [];
    if (!list.length) return '';
    var html = '<div class="atak-cams-section">' +
      '<div class="atak-cams-section-head">' +
        '<span class="atak-cams-section-title">' + escapeHtml(title || 'Photos terrain') + '</span>' +
      '</div>' +
      '<div class="atak-cams-photos">';
    list.forEach(function (p) {
      var src = resolveMediaUrl(p.url || p.path || '');
      if (!src && p.image_path) {
        src = resolveMediaUrl('/uploads/recon/' + String(p.image_path).split('/').pop());
      }
      var author = p.author_callsign || p.author || '';
      var caption = p.caption || '';
      var grid = p.grid_ref || p.grid || '';
      var label = deviceLabel(p);
      var stamp = formatPhotoStamp(p);
      html +=
        '<article class="atak-cam-photo" data-id="' + escapeHtml(p.id || '') + '">' +
          (src
            ? '<img src="' + escapeHtml(src) + '" alt="" loading="lazy" data-atak-cam-full="' + escapeHtml(src) + '" />'
            : '<div class="atak-cam-photo-missing" role="status">Image indisponible — lien dégradé ou fichier manquant.</div>') +
          '<div class="atak-cam-photo-meta">' +
            '<span class="atak-cam-photo-kind">' + escapeHtml(label) + '</span>' +
            (stamp ? '<time class="atak-cam-photo-stamp" datetime="">' + escapeHtml(stamp) + '</time>' : '') +
            (author ? '<strong>' + escapeHtml(author) + '</strong>' : '') +
            (grid ? '<span>Grille ' + escapeHtml(grid) + '</span>' : '') +
            (caption ? '<p>' + escapeHtml(caption) + '</p>' : '') +
          '</div>' +
        '</article>';
      addPhotoMarkerOnMap(p);
    });
    html += '</div></div>';
    return html;
  }

  function paint(feeds, photos) {
    ensureLightbox();
    var camsRoot = document.getElementById('atak-cams-list');
    var photosRoot = document.getElementById('atak-photos-list');
    if (!camsRoot && !photosRoot) return;

    var feedHtml = renderFeeds(feeds);
    var photoHtml = renderPhotos(photos, 'Photos reçues');

    if (camsRoot) {
      camsRoot.innerHTML = feedHtml || emptyStateHtml('cams');
    }
    if (photosRoot) {
      photosRoot.innerHTML = photoHtml || emptyStateHtml('photos');
    }
  }

  function notifyNewFeeds(feeds) {
    feeds.forEach(function (f) {
      if (f && f.id && f.online && !lastFeedIds[f.id]) {
        lastFeedIds[f.id] = true;
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification((f.kind_label || 'Caméra') + ' disponible — ' + (f.callsign || f.label || ''));
        }
      }
    });
  }

  function fetchVideoFeeds() {
    return fetchWithMapFallback('/api/atak/video-feeds', function (data) {
      return (data && Array.isArray(data.feeds)) ? data.feeds : [];
    }).then(function (feeds) {
      notifyNewFeeds(feeds || []);
      return feeds || [];
    });
  }

  function fetchReconImages() {
    return fetchWithMapFallback('/api/recon/images?limit=40', function (data) {
      return Array.isArray(data) ? data : [];
    }).then(function (list) { return list || []; });
  }

  function fetchIntelPhotos() {
    return fetchWithMapFallback('/api/intel/photos', function (data) {
      return Array.isArray(data) ? data : [];
    }).then(function (list) { return list || []; });
  }

  function refresh() {
    if (window.ATAKMap && window.ATAKMap.clearIntelMarkers) {
      window.ATAKMap.clearIntelMarkers();
    }
    return Promise.all([fetchVideoFeeds(), fetchReconImages(), fetchIntelPhotos()])
      .then(function (parts) {
        var feeds = parts[0] || [];
        var recon = parts[1] || [];
        var intel = parts[2] || [];
        // Fusion photos : recon d’abord, puis intel (éviter doublons d’URL)
        var seen = {};
        var photos = [];
        function pushPhoto(p) {
          if (!p) return;
          var key = String(p.url || p.path || p.image_path || p.id || '');
          if (key && seen[key]) return;
          if (key) seen[key] = true;
          photos.push(p);
        }
        recon.forEach(pushPhoto);
        intel.forEach(function (p) {
          if (!p.device_type) p.device_type = 'CTAB';
          if (!p.author_callsign && p.author) p.author_callsign = p.author;
          pushPhoto(p);
        });
        paint(feeds, photos);
        return { feeds: feeds, photos: photos };
      });
  }

  function appendIntelPhoto(photo) {
    var author = photo.author || photo.callsign || photo.author_callsign || 'Recon';
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('Nouvelle photo reçue de ' + author);
    }
    addPhotoMarkerOnMap(photo);
    refresh();
  }

  function addVideoElement(streamId, label) {
    var listEl = document.getElementById('atak-cams-list');
    if (!listEl) return null;
    var first = listEl.querySelector('.atak-empty-state, .atak-muted');
    if (first) first.remove();
    var wrap = document.createElement('div');
    wrap.className = 'atak-cam-item';
    wrap.setAttribute('data-stream-id', streamId);
    wrap.innerHTML =
      '<div class="atak-cam-tile-meta"><strong>' + escapeHtml(label || streamId) + '</strong></div>' +
      '<video autoplay playsinline class="atak-cam-webrtc-video"></video>';
    listEl.appendChild(wrap);
    return wrap.querySelector('video');
  }

  function attachStream(videoEl, stream) {
    if (videoEl && stream) videoEl.srcObject = stream;
  }

  function handleRemoteOffer() {
    /* WebRTC non branché en mode API PHP — les aperçus photo couvrent le besoin. */
  }

  function handleRemoteAnswer() {}
  function handleIceCandidate() {}

  var requestBusy = false;

  function requestNewView() {
    if (requestBusy) return;
    var ok = window.confirm(
      'Demander une nouvelle vue photo aux opérateurs en liaison ?\n\n' +
      'Une entrée sera ajoutée au journal du poste de commandement. Ce n’est pas une vidéo en direct.'
    );
    if (!ok) return;
    var base = getApiBase();
    if (!base) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison indisponible — réessayez dans un instant.');
      return;
    }
    requestBusy = true;
    var btn = document.getElementById('atak-cams-request-view');
    if (btn) btn.disabled = true;
    var mapId = getMapId();
    var note = 'Demande d’une nouvelle vue caméra / photo terrain';
    var author = 'Poste de commandement';
    try {
      var u = window.ATAK_USER || {};
      if (u.callsign || u.displayName) author = u.callsign || u.displayName;
      else if (window.ATAK_PHONE_SESSION && window.ATAK_PHONE_SESSION.label) author = window.ATAK_PHONE_SESSION.label;
    } catch (e) {}

    var activityPromise = fetch(base + '/api/atak/activity', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ mapId: mapId, note: note })
    }).then(function (r) {
      return r.json().then(function (body) {
        return { ok: r.ok, body: body };
      });
    }).catch(function () {
      return { ok: false };
    });

    var chatPromise = fetch(base + '/api/chat', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        mapId: mapId,
        author: author,
        body: '📷 ' + note + ' — merci de transmettre un aperçu photo depuis le terrain.'
      })
    }).then(function (r) {
      return { ok: r.ok };
    }).catch(function () {
      return { ok: false };
    });

    Promise.all([activityPromise, chatPromise]).then(function (parts) {
      var activityOk = parts[0] && parts[0].ok;
      var chatOk = parts[1] && parts[1].ok;
      if (activityOk || chatOk) {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Demande de vue envoyée au poste de commandement');
        }
        if (window.ATAKActivity && typeof window.ATAKActivity.refresh === 'function') {
          try { window.ATAKActivity.refresh(); } catch (e) {}
        }
        if (window.ATAKChat && typeof window.ATAKChat.fetchMessages === 'function') {
          try { window.ATAKChat.fetchMessages(); } catch (e) {}
        }
      } else {
        if (window.ATAKShowError) {
          window.ATAKShowError('Impossible d’envoyer la demande de vue pour le moment.');
        }
      }
    }).finally(function () {
      requestBusy = false;
      if (btn) btn.disabled = false;
    });
  }

  function bindRequestButton() {
    var btn = document.getElementById('atak-cams-request-view');
    if (!btn || btn._atakBound) return;
    btn._atakBound = true;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      requestNewView();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindRequestButton);
  } else {
    bindRequestButton();
  }

  return {
    refresh: refresh,
    fetchIntelPhotos: function () { return refresh(); },
    fetchReconImages: function () { return refresh(); },
    fetchVideoFeeds: fetchVideoFeeds,
    appendIntelPhoto: appendIntelPhoto,
    addVideoElement: addVideoElement,
    attachStream: attachStream,
    handleRemoteOffer: handleRemoteOffer,
    handleRemoteAnswer: handleRemoteAnswer,
    handleIceCandidate: handleIceCandidate,
    requestNewView: requestNewView
  };
})();
