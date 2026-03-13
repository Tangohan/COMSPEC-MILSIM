/* COMSPEC ATAK - Lives cam (WebRTC) + photos CTAB / intel */
window.ATAKCams = (function () {
  var apiBase = '';

  function getApiBase() {
    if (apiBase) return apiBase;
    apiBase = window.ATAKSocket ? window.ATAKSocket.getApiBase() : (window.location.protocol + '//' + window.location.hostname + ':3001');
    return apiBase;
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchIntelPhotos() {
    var url = getApiBase() + '/api/intel/photos?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      var list = Array.isArray(data) ? data : [];
      var el = document.getElementById('atak-intel-photos');
      if (!el) return;
      if (list.length === 0) {
        el.innerHTML = '';
        return;
      }
      var base = getApiBase();
      el.innerHTML = '<div style="padding:0.5rem;font-size:0.75rem;color:var(--atak-muted)">Photos CTAB</div>' +
        list.map(function (p) {
          var u = p.url || p.path || '';
          var src = (u.indexOf('http') === 0 || u.indexOf('//') === 0) ? u : (base + (u.charAt(0) === '/' ? u : '/' + u));
          return '<div class="atak-cam-item"><img src="' + src + '" alt="" style="max-width:100%;max-height:120px;border-radius:4px;" /></div>';
        }).join('');
    }).catch(function () {});
  }

  function appendIntelPhoto(photo) {
    var el = document.getElementById('atak-intel-photos');
    if (!el) return;
    var src = photo.url || (getApiBase() + (photo.path || ''));
    var div = document.createElement('div');
    div.className = 'atak-cam-item';
    div.innerHTML = '<img src="' + src + '" alt="" style="max-width:100%;max-height:120px;border-radius:4px;" />';
    el.appendChild(div);
  }

  function addVideoElement(streamId, label) {
    var listEl = document.getElementById('atak-cams-list');
    if (!listEl) return null;
    var first = listEl.querySelector('.atak-muted');
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

  var peerConnections = {};
  var pendingCandidates = {};

  function handleRemoteOffer(payload) {
    var from = payload.from;
    var streamId = payload.streamId || from;
    var sdp = payload.sdp || payload.offer;
    if (!sdp) return;
    var video = addVideoElement(streamId, payload.label || 'Live ' + streamId);
    if (!video) return;
    var pc = new (window.RTCPeerConnection || window.webkitRTCPeerConnection)({
      iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });
    peerConnections[streamId] = { pc: pc, video: video };
    pc.ontrack = function (e) { attachStream(video, e.streams[0]); };
    pc.addTransceiver('video', { direction: 'recvonly' });
    pc.setRemoteDescription(new RTCSessionDescription(sdp)).then(function () {
      return pc.createAnswer();
    }).then(function (answer) {
      return pc.setLocalDescription(answer);
    }).then(function () {
      if (window.ATAKSocket && window.ATAKSocket.getSocket()) {
        window.ATAKSocket.getSocket().emit('webrtc-answer', { to: from, answer: pc.localDescription });
      }
    }).catch(function (err) { console.warn('ATAKCams WebRTC answer error', err); });
  }

  function handleRemoteAnswer(payload) {
    var streamId = payload.streamId;
    if (streamId && peerConnections[streamId]) {
      var pc = peerConnections[streamId].pc;
      if (payload.answer) pc.setRemoteDescription(new RTCSessionDescription(payload.answer)).catch(function (e) { console.warn(e); });
    }
  }

  function handleIceCandidate(payload) {
    var from = payload.from;
    Object.keys(peerConnections).forEach(function (streamId) {
      var pc = peerConnections[streamId].pc;
      if (payload.candidate) pc.addIceCandidate(new RTCIceCandidate(payload.candidate)).catch(function () {});
    });
  }

  function initUpload() {
    var input = document.getElementById('atak-intel-upload');
    if (!input) return;
    input.addEventListener('change', function () {
      var file = this.files && this.files[0];
      if (!file) return;
      var fd = new FormData();
      fd.append('photo', file);
      fd.append('mapId', getMapId());
      fd.append('author', 'User');
      fetch(getApiBase() + '/api/intel/photos', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (p) { appendIntelPhoto(p); })
        .catch(function () {});
      this.value = '';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUpload);
  } else {
    initUpload();
  }

  return {
    fetchIntelPhotos: fetchIntelPhotos,
    appendIntelPhoto: appendIntelPhoto,
    addVideoElement: addVideoElement,
    attachStream: attachStream,
    handleRemoteOffer: handleRemoteOffer,
    handleRemoteAnswer: handleRemoteAnswer,
    handleIceCandidate: handleIceCandidate
  };
})();
