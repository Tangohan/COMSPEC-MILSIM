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

  function emptyStateHtml() {
    return (
      '<div class="atak-empty-state">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">▣</div>' +
        '<p class="atak-empty-state-title">Aucun aperçu pour l’instant</p>' +
        '<p class="atak-empty-state-text">Les caméras casque et drones détectés en jeu, ainsi que les photos envoyées depuis la tablette, apparaîtront ici. La vidéo en direct n’est pas disponible : seuls des aperçus photo sont transmis.</p>' +
      '</div>'
    );
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
      html +=
        '<article class="atak-cam-photo" data-id="' + escapeHtml(p.id || '') + '">' +
          (src
            ? '<img src="' + escapeHtml(src) + '" alt="" loading="lazy" data-atak-cam-full="' + escapeHtml(src) + '" />'
            : '') +
          '<div class="atak-cam-photo-meta">' +
            '<span class="atak-cam-photo-kind">' + escapeHtml(label) + '</span>' +
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
    var root = document.getElementById('atak-cams-list');
    var photosEl = document.getElementById('atak-intel-photos');
    if (!root) return;

    var feedHtml = renderFeeds(feeds);
    var photoHtml = renderPhotos(photos, 'Photos reçues');

    if (!feedHtml && !photoHtml) {
      root.innerHTML = emptyStateHtml();
      if (photosEl) photosEl.innerHTML = '';
      return;
    }

    root.innerHTML = feedHtml || '';
    if (photosEl) {
      photosEl.innerHTML = photoHtml;
    } else if (photoHtml) {
      root.insertAdjacentHTML('beforeend', photoHtml);
    }
  }

  function fetchVideoFeeds() {
    var base = getApiBase();
    var url = (base ? base : '') + '/api/atak/video-feeds?mapId=' + getMapId();
    return fetch(url, { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var feeds = (data && Array.isArray(data.feeds)) ? data.feeds : [];
        feeds.forEach(function (f) {
          if (f && f.id && f.online && !lastFeedIds[f.id]) {
            lastFeedIds[f.id] = true;
            if (window.ATAKShowNotification) {
              window.ATAKShowNotification((f.kind_label || 'Caméra') + ' disponible — ' + (f.callsign || f.label || ''));
            }
          }
        });
        return feeds;
      })
      .catch(function () { return []; });
  }

  function fetchReconImages() {
    var base = getApiBase();
    var url = (base ? base : '') + '/api/recon/images?mapId=' + getMapId() + '&limit=40';
    return fetch(url, { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        return Array.isArray(data) ? data : [];
      })
      .catch(function () { return []; });
  }

  function fetchIntelPhotos() {
    var base = getApiBase();
    var url = (base ? base : '') + '/api/intel/photos?mapId=' + getMapId();
    return fetch(url, { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        return Array.isArray(data) ? data : [];
      })
      .catch(function () { return []; });
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
    handleIceCandidate: handleIceCandidate
  };
})();
