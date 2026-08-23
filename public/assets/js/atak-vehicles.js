/* COMSPEC ATAK — véhicules / balises GPS sur la carte */
window.ATAKVehicles = (function () {
  var lastItems = [];

  function getApiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function getMapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function isFresh(item) {
    if (!item) return false;
    var st = String(item.status || '').toUpperCase();
    if (st === 'DESTROYED') return false;
    var raw = item.last_seen_at || item.updated_at || '';
    if (!raw) return true;
    var ts = new Date(String(raw).replace(' ', 'T')).getTime();
    if (isNaN(ts)) return true;
    return (Date.now() - ts) < (4 * 60 * 1000);
  }

  function fetchList() {
    var base = getApiBase();
    if (!base) return;
    fetch(base + '/api/atak/vehicles?mapId=' + encodeURIComponent(getMapId()), { credentials: 'include' })
      .then(function (r) { return r.ok ? r.json() : { vehicles: [] }; })
      .then(function (data) {
        var rows = Array.isArray(data.vehicles) ? data.vehicles : (Array.isArray(data) ? data : []);
        lastItems = rows.filter(isFresh);
        if (window.ATAKMap && typeof window.ATAKMap.setGpsVehiclesOnMap === 'function') {
          window.ATAKMap.setGpsVehiclesOnMap(lastItems);
        }
      })
      .catch(function () {});
  }

  return {
    fetchList: fetchList,
    getItems: function () { return lastItems; }
  };
})();
