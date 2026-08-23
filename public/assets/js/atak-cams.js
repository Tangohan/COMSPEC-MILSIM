/* COMSPEC ATAK — Cams : aperçus casque/drone + photos terrain */
window.ATAKCams = (function () {
  var apiBase = null;
  var lastFeedIds = {};
  var lastFeeds = [];
  var lastPhotos = [];
  var lastLinkDown = false;
  var lightboxBound = false;
  var hiddenPhotoIds = loadHiddenPhotoIds();
  var streamRefreshTimer = null;
  var selectedNight = loadSelectedNight();
  var currentNightKey = '';
  var nightsBound = false;

  function hiddenStoreKey() {
    var tenant = window.ATAK_TENANT_ID || 0;
    return 'atak_hidden_recon_photos_' + tenant;
  }

  function loadHiddenPhotoIds() {
    try {
      var raw = window.sessionStorage.getItem(hiddenStoreKey());
      var list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function saveHiddenPhotoIds() {
    try {
      window.sessionStorage.setItem(hiddenStoreKey(), JSON.stringify(hiddenPhotoIds));
    } catch (e) {}
  }

  function nightStoreKey() {
    return 'atak_photo_night_' + (window.ATAK_TENANT_ID || 0);
  }

  function loadSelectedNight() {
    try {
      var raw = window.localStorage.getItem(nightStoreKey());
      return raw === 'all' || /^\d{4}-\d{2}-\d{2}$/.test(raw || '') ? raw : 'current';
    } catch (e) {
      return 'current';
    }
  }

  function saveSelectedNight(value) {
    selectedNight = value || 'current';
    try {
      window.localStorage.setItem(nightStoreKey(), selectedNight);
    } catch (e) {}
  }

  function nightQueryValue() {
    return selectedNight || 'current';
  }

  function shouldShowOnMap(photo) {
    if (selectedNight && selectedNight !== 'current' && selectedNight !== 'all') {
      return true;
    }
    var key = String((photo && photo.play_night) || '');
    if (!key || !currentNightKey) return true;
    return key === currentNightKey;
  }

  function isHiddenLocally(id) {
    id = String(id || '');
    return !!id && hiddenPhotoIds.indexOf(id) >= 0;
  }

  function hideLocally(id) {
    id = String(id || '');
    if (!id || isHiddenLocally(id)) return;
    hiddenPhotoIds.push(id);
    saveHiddenPhotoIds();
  }

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
    if (window.ATAKMap.getDisplayPrefs) {
      var prefs = window.ATAKMap.getDisplayPrefs();
      if (prefs && prefs.showIntelPhotoMarkers === false) return;
    }
    if (isHiddenLocally(photo.id || photo.snapshot_id)) return;
    if (!shouldShowOnMap(photo)) return;
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
      var article = thumb.closest ? thumb.closest('.atak-cam-photo') : null;
      var lightboxClass = 'atak-cam-lightbox';
      if (article && article.classList && article.classList.contains('is-blurred')) {
        lightboxClass += ' is-blurred';
      }
      ev.preventDefault();
      var existing = document.getElementById('atak-cam-lightbox');
      if (existing) existing.remove();
      var overlay = document.createElement('div');
      overlay.id = 'atak-cam-lightbox';
      overlay.className = lightboxClass;
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
    document.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('[data-recon-action]') : null;
      if (!btn) return;
      ev.preventDefault();
      var action = btn.getAttribute('data-recon-action') || '';
      var article = btn.closest('.atak-cam-photo');
      if (!article) return;
      var photoId = article.getAttribute('data-id') || '';
      if (!photoId) return;
      handlePhotoAction(photoId, action, article, btn);
    });
  }

  function emptyStateHtml(kind) {
    if (kind === 'photos') {
      return (
        '<div class="atak-empty-state">' +
          '<div class="atak-empty-state-icon" aria-hidden="true">◫</div>' +
          '<p class="atak-empty-state-title">Aucune photo pour cette soirée</p>' +
          '<p class="atak-empty-state-text">Les vues de la soirée en cours apparaîtront ici. Pour revoir une autre soirée, changez le menu ci-dessus.</p>' +
        '</div>'
      );
    }
    return (
      '<div class="atak-empty-state">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">▣</div>' +
        '<p class="atak-empty-state-title">Aucune caméra détectée</p>' +
        '<p class="atak-empty-state-text">La vue casque en temps réel n’est pas au point. Les photos prises depuis le terminal ATAK se retrouvent dans l’onglet Photos.</p>' +
      '</div>'
    );
  }

  function fetchJsonForMap(path, mapId) {
    var base = getApiBase();
    var sep = path.indexOf('?') >= 0 ? '&' : '?';
    var url = (base ? base : '') + path + sep + 'mapId=' + encodeURIComponent(mapId);
    return fetch(url, { credentials: 'include' })
      .then(function (r) {
        return r.json().then(function (body) {
          if (!r.ok) lastLinkDown = true;
          return body;
        }).catch(function () {
          lastLinkDown = true;
          return null;
        });
      })
      .catch(function () {
        lastLinkDown = true;
        return null;
      });
  }

  function attachSnapshotsFromPhotos(feeds, photos) {
    var list = Array.isArray(feeds) ? feeds : [];
    var pics = Array.isArray(photos) ? photos : [];
    return list.map(function (f) {
      if (!f || f.snapshot_url) return f;
      var cs = String(f.callsign || '').toUpperCase();
      if (!cs) return f;
      for (var i = 0; i < pics.length; i++) {
        var p = pics[i];
        var pcs = String((p && (p.author_callsign || p.author)) || '').toUpperCase();
        if (pcs !== cs) continue;
        var copy = {};
        Object.keys(f).forEach(function (k) { copy[k] = f[k]; });
        copy.snapshot_url = p.url || p.path || '';
        copy.snapshot_id = p.id;
        return copy;
      }
      return f;
    });
  }

  function setLinkAlert(down) {
    var el = document.getElementById('atak-cams-link-alert');
    if (el) {
      el.hidden = !down;
    }
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
      var streaming = !!(f.streaming || f.stream_active);
      html +=
        '<article class="atak-cam-tile ' + kindClass(kind) + (online ? ' is-online' : ' is-offline') + (streaming ? ' is-streaming' : '') + '" data-feed-id="' + escapeHtml(f.id || '') + '">' +
          '<div class="atak-cam-tile-media">' +
            (src
              ? '<img src="' + escapeHtml(src) + '" alt="" loading="lazy" data-atak-cam-full="' + escapeHtml(src) + '" />'
              : '<div class="atak-cam-tile-placeholder"><span>Aucun aperçu photo</span></div>') +
            '<span class="atak-cam-tile-badge">' + escapeHtml(kindLabel) + '</span>' +
            (streaming ? '<span class="atak-cam-tile-badge atak-cam-tile-badge--stream">Flux actif</span>' : '') +
            '<span class="atak-cam-tile-status">' + escapeHtml(status) + (age ? ' · ' + escapeHtml(age) : '') + (streaming ? ' · ~5 s' : '') + '</span>' +
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

  function photoFxClass(photo) {
    var p = String((photo && photo.fx_profile) || '').toLowerCase();
    if (!p) return '';
    return ' atak-cam-photo--fx-' + p.replace(/[^a-z0-9_-]+/g, '-');
  }

  function renderPhotos(photos, title) {
    var list = (Array.isArray(photos) ? photos : []).filter(function (p) {
      return !isHiddenLocally(p && p.id);
    });
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
      var comment = p.operator_comment || '';
      var grid = p.grid_ref || p.grid || '';
      var label = deviceLabel(p);
      var stamp = formatPhotoStamp(p);
      var blurred = !!Number(p.is_blurred || 0);
      var transferred = !!(p.sse_case_id || p.sse_transferred_at);
      var fxClass = photoFxClass(p);
      html +=
        '<article class="atak-cam-photo' + (blurred ? ' is-blurred' : '') + fxClass + '" data-id="' + escapeHtml(p.id || '') + '">' +
          (src
            ? '<img src="' + escapeHtml(src) + '" alt="" loading="lazy" data-atak-cam-full="' + escapeHtml(src) + '" />'
            : '<div class="atak-cam-photo-missing" role="status">Image indisponible — lien dégradé ou fichier manquant.</div>') +
          '<div class="atak-cam-photo-meta">' +
            '<span class="atak-cam-photo-kind">' + escapeHtml(label) + '</span>' +
            (stamp ? '<time class="atak-cam-photo-stamp" datetime="">' + escapeHtml(stamp) + '</time>' : '') +
            (author ? '<strong>' + escapeHtml(author) + '</strong>' : '') +
            (grid ? '<span>Grille ' + escapeHtml(grid) + '</span>' : '') +
            (caption ? '<p>' + escapeHtml(caption) + '</p>' : '') +
            (comment ? '<p class="atak-cam-photo-comment">Commentaire: ' + escapeHtml(comment) + '</p>' : '') +
            (transferred ? '<p class="atak-cam-photo-flag">Archivée dans SSE</p>' : '') +
            '<div class="atak-cam-photo-actions">' +
              '<button type="button" class="atak-cam-photo-btn" data-recon-action="sse_transfer">Passer en SSE</button>' +
              '<button type="button" class="atak-cam-photo-btn" data-recon-action="comment">Commenter</button>' +
              '<button type="button" class="atak-cam-photo-btn" data-recon-action="blur">' + (blurred ? 'Retirer le flou' : 'Flouter') + '</button>' +
              '<button type="button" class="atak-cam-photo-btn" data-recon-action="hide">Masquer</button>' +
              '<button type="button" class="atak-cam-photo-btn atak-cam-photo-btn--danger" data-recon-action="delete">Supprimer</button>' +
            '</div>' +
          '</div>' +
        '</article>';
      addPhotoMarkerOnMap(p);
    });
    html += '</div></div>';
    return html;
  }

  function syncStreamRefresh(feeds) {
    var any = (feeds || []).some(function (f) { return f && (f.streaming || f.stream_active); });
    if (any && !streamRefreshTimer) {
      streamRefreshTimer = setInterval(function () {
        refresh();
      }, 4500);
    } else if (!any && streamRefreshTimer) {
      clearInterval(streamRefreshTimer);
      streamRefreshTimer = null;
    }
  }

  function paint(feeds, photos) {
    ensureLightbox();
    syncStreamRefresh(feeds);
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
    return fetchWithMapFallback('/api/recon/images?limit=200&night=' + encodeURIComponent(nightQueryValue()), function (data) {
      return Array.isArray(data) ? data : [];
    }).then(function (list) { return list || []; });
  }

  function fetchIntelPhotos() {
    return fetchWithMapFallback('/api/intel/photos?night=' + encodeURIComponent(nightQueryValue()), function (data) {
      return Array.isArray(data) ? data : [];
    }).then(function (list) { return list || []; });
  }

  function fillNightSelect(payload) {
    var sel = document.getElementById('atak-photos-night');
    if (!sel) return;
    currentNightKey = String((payload && payload.current) || currentNightKey || '');
    var nights = (payload && payload.nights) || [];
    var currentLabel = (payload && payload.current_label) || 'Soirée en cours';
    var html = '<option value="current">' + escapeHtml(currentLabel) + ' (en cours)</option>';
    nights.forEach(function (n) {
      if (!n || !n.key) return;
      if (n.key === currentNightKey) return;
      html += '<option value="' + escapeHtml(n.key) + '">' + escapeHtml(n.label || n.key) +
        (n.count ? ' — ' + n.count + ' photo' + (n.count > 1 ? 's' : '') : '') + '</option>';
    });
    html += '<option value="all">Toutes les soirées</option>';
    sel.innerHTML = html;
    sel.value = selectedNight;
    if (sel.value !== selectedNight) {
      sel.value = 'current';
      saveSelectedNight('current');
    }
    updateNightDeleteButton();
    updateNightHint();
  }

  function updateNightHint() {
    var hint = document.getElementById('atak-photos-night-hint');
    if (!hint) return;
    if (selectedNight === 'all') {
      hint.textContent = 'Liste complète. Sur la carte, seule la soirée en cours est affichée pour éviter l’empilement.';
      return;
    }
    if (selectedNight !== 'current') {
      hint.textContent = 'Photos de cette soirée. Elles s’affichent aussi sur la carte le temps du rappel. Pour les retirer définitivement, utilisez le bouton ci-contre.';
      return;
    }
    hint.textContent = 'Seule la soirée en cours apparaît sur la carte. Les soirées précédentes restent ici pour archivage ou suppression manuelle.';
  }

  function updateNightDeleteButton() {
    var btn = document.getElementById('atak-photos-night-delete');
    if (!btn) return;
    var show = selectedNight && selectedNight !== 'all';
    btn.hidden = !show;
  }

  function fetchPhotoNights() {
    var mid = getMapId();
    return fetchJsonForMap('/api/atak/photo-nights', mid).then(function (payload) {
      fillNightSelect(payload && typeof payload === 'object' ? payload : {});
      return payload || {};
    });
  }

  function bindNightBar() {
    if (nightsBound) return;
    nightsBound = true;
    var sel = document.getElementById('atak-photos-night');
    if (sel) {
      sel.addEventListener('change', function () {
        saveSelectedNight(sel.value || 'current');
        updateNightDeleteButton();
        updateNightHint();
        refresh();
      });
    }
    var del = document.getElementById('atak-photos-night-delete');
    if (del) {
      del.addEventListener('click', function (e) {
        e.preventDefault();
        deleteSelectedNightPhotos();
      });
    }
  }

  function deleteSelectedNightPhotos() {
    var night = selectedNight === 'current' ? (currentNightKey || 'current') : selectedNight;
    if (!night || night === 'all') return;
    var label = '';
    var sel = document.getElementById('atak-photos-night');
    if (sel && sel.selectedOptions && sel.selectedOptions[0]) {
      label = sel.selectedOptions[0].textContent || '';
    }
    if (!window.confirm('Supprimer les photos de « ' + (label || 'cette soirée') + ' » ? Les clichés déjà classés en dossier SSE sont conservés.')) {
      return;
    }
    var typed = window.prompt('Pour confirmer, saisissez SUPPRIMER LES PHOTOS');
    if (String(typed || '').trim().toUpperCase() !== 'SUPPRIMER LES PHOTOS') {
      return;
    }
    var base = getApiBase();
    if (!base) return;
    var delBtn = document.getElementById('atak-photos-night-delete');
    if (delBtn) delBtn.disabled = true;
    fetch(base + '/api/atak/photo-nights/purge', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        mapId: getMapId(),
        night: night,
        confirm: 'SUPPRIMER LES PHOTOS'
      })
    })
      .then(parseJsonResponse)
      .then(function (res) {
        if (res && res.ok) {
          if (window.ATAKShowNotification) {
            window.ATAKShowNotification((res.body && res.body.message) || 'Photos retirées.');
          }
          saveSelectedNight('current');
          return fetchPhotoNights().then(function () { return refresh(); });
        }
        if (window.ATAKShowError) {
          window.ATAKShowError((res && res.body && res.body.message) || 'Impossible de retirer ces photos pour le moment.');
        }
      })
      .finally(function () {
        var b = document.getElementById('atak-photos-night-delete');
        if (b) b.disabled = false;
      });
  }

  function refresh() {
    if (window.ATAKMap && window.ATAKMap.clearIntelMarkers) {
      window.ATAKMap.clearIntelMarkers();
    }
    lastLinkDown = false;
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
          var deviceKey = String(p.device_type || '').toUpperCase();
          var sig = [
            String(p.author_callsign || p.author || '').toUpperCase(),
            String(p.grid_ref || p.grid || ''),
            String(p.created_at || p.captured_at || p.capturedAt || '').slice(0, 19)
          ].join('|');
          if (sig !== '||' && seen['sig:' + sig]) {
            var prevDevice = seen['sig:' + sig];
            if (deviceKey === 'HELMET' && String(p.caption || '').indexOf('Aperçu casque') === 0 && prevDevice === 'CTAB') {
              return;
            }
            if (deviceKey === 'CTAB' && prevDevice === 'HELMET') {
              for (var i = photos.length - 1; i >= 0; i--) {
                var op = photos[i];
                var os = [
                  String(op.author_callsign || op.author || '').toUpperCase(),
                  String(op.grid_ref || op.grid || ''),
                  String(op.created_at || op.captured_at || op.capturedAt || '').slice(0, 19)
                ].join('|');
                if (os === sig && String(op.device_type || '').toUpperCase() === 'HELMET') {
                  photos.splice(i, 1);
                  break;
                }
              }
            }
          }
          if (key) seen[key] = true;
          if (sig !== '||') seen['sig:' + sig] = deviceKey;
          photos.push(p);
        }
        recon.forEach(pushPhoto);
        intel.forEach(function (p) {
          if (!p.device_type) p.device_type = 'CTAB';
          if (!p.author_callsign && p.author) p.author_callsign = p.author;
          pushPhoto(p);
        });
        if (lastLinkDown) {
          if (!feeds.length && lastFeeds.length) feeds = lastFeeds;
          if (!photos.length && lastPhotos.length) photos = lastPhotos;
        } else {
          lastFeeds = feeds;
          lastPhotos = photos;
        }
        feeds = attachSnapshotsFromPhotos(feeds, photos);
        setLinkAlert(lastLinkDown);
        paint(feeds, photos);
        return { feeds: feeds, photos: photos };
      });
  }

  function parseJsonResponse(r) {
    return r.json().catch(function () { return {}; }).then(function (body) {
      return { ok: r.ok, status: r.status, body: body || {} };
    });
  }

  function postReconAction(photoId, payload) {
    var base = getApiBase();
    if (!base) return Promise.resolve({ ok: false, body: { message: 'Liaison indisponible.' } });
    return fetch(base + '/api/recon/images/' + encodeURIComponent(photoId) + '/ops', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload || {})
    }).then(parseJsonResponse).catch(function () {
      return { ok: false, body: { message: 'Action indisponible pour le moment.' } };
    });
  }

  function fetchSseCases() {
    var base = getApiBase();
    if (!base) return Promise.resolve([]);
    return fetch(base + '/api/recon/images/sse-cases', {
      credentials: 'include',
      headers: { Accept: 'application/json' }
    }).then(parseJsonResponse).then(function (res) {
      return res.ok && res.body && Array.isArray(res.body.cases) ? res.body.cases : [];
    }).catch(function () { return []; });
  }

  function handlePhotoAction(photoId, action, article, btn) {
    if (action === 'hide') {
      hideLocally(photoId);
      if (window.ATAKMap && window.ATAKMap.removeIntelPhotoMarker) {
        window.ATAKMap.removeIntelPhotoMarker(photoId);
      }
      if (article && article.remove) article.remove();
      return;
    }
    if (action === 'delete') {
      if (!window.confirm('Supprimer cette photo du panneau tactique ?')) return;
      postReconAction(photoId, { action: 'delete' }).then(handleReconActionResult);
      return;
    }
    if (action === 'blur') {
      var blurred = article && article.classList && article.classList.contains('is-blurred');
      if (article && article.classList) {
        article.classList.toggle('is-blurred', !blurred);
      }
      var lightbox = document.getElementById('atak-cam-lightbox');
      if (lightbox && lightbox.classList) {
        lightbox.classList.toggle('is-blurred', !blurred);
      }
      postReconAction(photoId, { action: 'blur', blurred: !blurred }).then(handleReconActionResult);
      return;
    }
    if (action === 'comment') {
      var current = '';
      var commentEl = article ? article.querySelector('.atak-cam-photo-comment') : null;
      if (commentEl) current = String(commentEl.textContent || '').replace(/^Commentaire:\s*/i, '');
      var comment = window.prompt('Commentaire opérateur pour cette photo :', current);
      if (comment === null) return;
      postReconAction(photoId, { action: 'comment', comment: comment }).then(handleReconActionResult);
      return;
    }
    if (action === 'sse_transfer') {
      fetchSseCases().then(function (cases) {
        if (!cases.length) {
          if (window.ATAKShowError) window.ATAKShowError('Aucun dossier SSE accessible. Ouvrez d’abord le portail SSE.');
          return;
        }
        var choices = cases.map(function (c) {
          return c.id + ' - ' + (c.reference_code || 'SSE') + ' - ' + (c.title || 'Sans titre');
        }).join('\n');
        var selected = window.prompt('Choisissez l’identifiant du dossier SSE :\n\n' + choices, String(cases[0].id));
        if (selected === null) return;
        var caseId = parseInt(String(selected).trim(), 10);
        if (!caseId) {
          if (window.ATAKShowError) window.ATAKShowError('Dossier SSE invalide.');
          return;
        }
        postReconAction(photoId, { action: 'sse_transfer', case_id: caseId }).then(handleReconActionResult);
      });
    }
  }

  function handleReconActionResult(res) {
    if (res && res.ok) {
      if (window.ATAKShowNotification) window.ATAKShowNotification((res.body && res.body.message) || 'Action appliquée.');
      refresh();
      return;
    }
    if (window.ATAKShowError) {
      window.ATAKShowError((res && res.body && res.body.message) || 'Action impossible pour le moment.');
    }
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
    document.addEventListener('DOMContentLoaded', function () {
      bindRequestButton();
      bindNightBar();
      fetchPhotoNights();
    });
  } else {
    bindRequestButton();
    bindNightBar();
    fetchPhotoNights();
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
