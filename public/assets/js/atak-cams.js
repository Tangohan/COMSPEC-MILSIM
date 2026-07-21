/* COMSPEC ATAK - Lives cam (WebRTC) + photos CTAB / intel */
window.ATAKCams = (function () {
  var apiBase = null;

  function getApiBase() {
    if (apiBase !== undefined && apiBase !== null) return apiBase;
    apiBase = window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
    return apiBase || '';
  }
  function isNodeConfigured() {
    return true;
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function getAuthor() {
    var u = window.ATAK_USER;
    if (u && (u.callsign || u.displayName)) return u.callsign || u.displayName;
    return 'User';
  }

  function addPhotoMarkerOnMap(photo) {
    if (!window.ATAKMap || !window.ATAKMap.addIntelPhotoMarker) return;
    var px = photo.pos_x != null ? parseFloat(photo.pos_x) : null;
    var py = photo.pos_y != null ? parseFloat(photo.pos_y) : null;
    if (px == null || py == null) return;
    var u = photo.url || photo.path || '';
    var base = getApiBase();
    var src = (u.indexOf('http') === 0 || u.indexOf('//') === 0) ? u : (base + (u.charAt(0) === '/' ? u : '/' + u));
    window.ATAKMap.addIntelPhotoMarker(photo.id, py, px, src);
  }

  function fetchReconImages() {
    var base = getApiBase();
    var url = (base ? base : '') + '/api/recon/images?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      var list = Array.isArray(data) ? data : [];
      var el = document.getElementById('atak-intel-photos');
      if (!el) return;
      if (window.ATAKMap && window.ATAKMap.clearIntelMarkers) window.ATAKMap.clearIntelMarkers();
      var html = list.length ? '<div class="atak-panel-section-label">Recon</div>' + list.map(function (p) {
        var src = (p.url || '').indexOf('http') === 0 ? p.url : base + (p.url || '/uploads/recon/' + (p.image_path || '').split('/').pop());
        if (window.ATAKMap && window.ATAKMap.addIntelPhotoMarker) window.ATAKMap.addIntelPhotoMarker(p.id, p.pos_y, p.pos_x, src);
        return '<div class="atak-cam-item atak-recon-item" data-id="' + (p.id || '') + '"><img src="' + src + '" alt="" style="max-width:100%;max-height:120px;border-radius:4px;" /><div class="atak-recon-meta">' + (p.author_callsign || '') + ' ' + (p.caption || '') + '</div></div>';
      }).join('') : '';
      el.innerHTML = html;
    }).catch(function () {});
  }

  function fetchIntelPhotos() {
    var base = getApiBase();
    var url = (base ? base : '') + '/api/intel/photos?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      var list = Array.isArray(data) ? data : [];
      var el = document.getElementById('atak-intel-photos');
      if (!el) return;
      if (window.ATAKMap && window.ATAKMap.clearIntelMarkers) window.ATAKMap.clearIntelMarkers();
      if (list.length === 0) {
        el.innerHTML = '';
        return;
      }
      var base = getApiBase();
      el.innerHTML = '<div class="atak-panel-section-label">Photos CTAB</div>' +
        list.map(function (p) {
          var u = p.url || p.path || '';
          var src = (u.indexOf('http') === 0 || u.indexOf('//') === 0) ? u : (base + (u.charAt(0) === '/' ? u : '/' + u));
          addPhotoMarkerOnMap(p);
          return '<div class="atak-cam-item"><img src="' + src + '" alt="" style="max-width:100%;max-height:120px;border-radius:4px;" /></div>';
        }).join('');
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible de charger les photos CTAB.');
    });
  }

  function appendIntelPhoto(photo) {
    var author = photo.author || photo.callsign || 'Recon';
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('Nouvelle photo de recon reçue de ' + author);
    }
    var el = document.getElementById('atak-intel-photos');
    if (el) {
      var src = photo.url || (getApiBase() + (photo.path || ''));
      if (src.indexOf('http') !== 0 && src.indexOf('//') !== 0) src = getApiBase() + (src.charAt(0) === '/' ? src : '/' + src);
      var div = document.createElement('div');
      div.className = 'atak-cam-item';
      div.innerHTML = '<img src="' + src + '" alt="" style="max-width:100%;max-height:120px;border-radius:4px;" />';
      el.appendChild(div);
    }
    addPhotoMarkerOnMap(photo);
  }

  function addVideoElement(streamId, label) {
    var listEl = document.getElementById('atak-cams-list');
    if (!listEl) return null;
    var first = listEl.querySelector('.atak-empty-state, .atak-muted');
    if (first) first.remove();
    var wrap = document.createElement('div');
    wrap.className = 'atak-cam-item';
    wrap.setAttribute('data-stream-id', streamId);
    wrap.innerHTML = '<div style="font-size:0.7rem;margin-bottom:4px">' + (label || streamId) + '</div><video autoplay playsinline style="width:100%;max-height:180px;background:#0f172a;border-radius:4px;"></video>';
    listEl.appendChild(wrap);
    return wrap.querySelector('video');
  }

  function attachStream(videoEl, stream) {
    if (videoEl && stream) videoEl.srcObject = stream;
  }

  function handleRemoteOffer(payload) {
    var listEl = document.getElementById('atak-cams-list');
    if (listEl && !listEl.querySelector('.atak-cams-webrtc-disabled')) {
      var msg = document.createElement('div');
      msg.className = 'atak-muted atak-cams-webrtc-disabled';
      msg.style.cssText = 'padding:0.5rem;font-size:0.75rem;';
      msg.textContent = 'Lives WebRTC non disponibles en mode API PHP (pas de Socket.IO).';
      listEl.appendChild(msg);
    }
  }

  function handleRemoteAnswer() {}
  function handleIceCandidate() {}

  return {
    fetchIntelPhotos: fetchIntelPhotos,
    fetchReconImages: fetchReconImages,
    appendIntelPhoto: appendIntelPhoto,
    addVideoElement: addVideoElement,
    attachStream: attachStream,
    handleRemoteOffer: handleRemoteOffer,
    handleRemoteAnswer: handleRemoteAnswer,
    handleIceCandidate: handleIceCandidate
  };
})();
