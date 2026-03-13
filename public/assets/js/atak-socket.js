/* COMSPEC ATAK - Socket.io client + dispatch vers modules */
window.ATAKSocket = (function () {
  var io = window.io;
  var socket = null;
  var mapId = 1;
  var apiBase = '';

  function getApiBase() {
    if (apiBase) return apiBase;
    if (window.NODE_ATAK_URL && typeof window.NODE_ATAK_URL === 'string' && window.NODE_ATAK_URL.trim() !== '') {
      apiBase = window.NODE_ATAK_URL.replace(/\/$/, '');
      return apiBase;
    }
    var u = window.location;
    apiBase = u.protocol + '//' + u.hostname + ':3001';
    return apiBase;
  }

  function connect(options) {
    options = options || {};
    mapId = options.mapId != null ? options.mapId : 1;
    var url = options.serverUrl || getApiBase();
    if (!io) {
      console.error('ATAKSocket: socket.io not loaded');
      if (options.onConnectionLost) options.onConnectionLost();
      return null;
    }
    socket = io(url, { path: '/socket.io' });
    socket.on('connect', function () {
      socket.emit('Hello', { tacMapID: mapId });
      if (options.onConnect) options.onConnect();
    });
    socket.on('disconnect', function () {
      if (options.onConnectionLost) options.onConnectionLost();
    });
    socket.on('AddOrUpdateMarker', function (payload, isReadOnly) {
      if (window.ATAKMap) window.ATAKMap.addOrUpdateMarker(payload);
    });
    socket.on('RemoveMarker', function (payload) {
      if (window.ATAKMap) window.ATAKMap.removeMarker(payload);
    });
    socket.on('AddOrUpdateLayer', function (payload) {
      if (window.ATAKMap) window.ATAKMap.addOrUpdateLayer(payload);
    });
    socket.on('RemoveLayer', function (payload) {
      if (window.ATAKMap) window.ATAKMap.removeLayer(payload);
    });
    socket.on('PointMap', function (userId, pos) {
      if (window.ATAKMap) window.ATAKMap.pointMap(userId, pos);
    });
    socket.on('EndPointMap', function (userId) {
      if (window.ATAKMap) window.ATAKMap.endPointMap(userId);
    });
    socket.on('Chat', function (msg) {
      if (window.ATAKChat) window.ATAKChat.appendMessage(msg);
    });
    socket.on('Ping', function (ping) {
      if (window.ATAKPings) window.ATAKPings.appendPing(ping);
    });
    socket.on('NineLineUpdate', function (nineLine) {
      if (window.ATAKJTAC) window.ATAKJTAC.appendNineLine(nineLine);
    });
    socket.on('ProfilesUpdate', function (data) {
      if (window.ATAKUnits) window.ATAKUnits.setUnits(data.units || []);
    });
    socket.on('IntelPhoto', function (photo) {
      if (window.ATAKCams) window.ATAKCams.appendIntelPhoto(photo);
    });
    socket.on('webrtc-offer', function (payload) {
      if (window.ATAKCams && window.ATAKCams.handleRemoteOffer) window.ATAKCams.handleRemoteOffer(payload);
    });
    socket.on('webrtc-answer', function (payload) {
      if (window.ATAKCams && window.ATAKCams.handleRemoteAnswer) window.ATAKCams.handleRemoteAnswer(payload);
    });
    socket.on('webrtc-ice', function (payload) {
      if (window.ATAKCams && window.ATAKCams.handleIceCandidate) window.ATAKCams.handleIceCandidate(payload);
    });
    return socket;
  }

  function emit(event, data) {
    if (socket && socket.connected) socket.emit(event, data);
  }

  function isConnected() {
    return socket && socket.connected;
  }

  function getMapId() { return mapId; }

  return {
    connect: connect,
    emit: emit,
    isConnected: isConnected,
    getMapId: getMapId,
    getSocket: function () { return socket; },
    getApiBase: getApiBase
  };
})();
