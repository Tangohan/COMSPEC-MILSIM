/* COMSPEC ATAK - Laser codes panel */
window.ATAKLaserCodes = (function () {
  var codes = [];

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchLaserCodes() {
    var base = getApiBase();
    if (!base) return;
    var url = base + '/api/atak/laser-codes?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      codes = Array.isArray(data) ? data : [];
      var el = document.getElementById('atak-laser-codes-list');
      if (!el) return;
      if (codes.length === 0) {
        el.innerHTML = '<p class="atak-muted" style="padding:0.5rem;font-size:0.8rem;">Aucun code laser actif.</p>';
        return;
      }
      el.innerHTML = '<div class="atak-laser-header">Codes laser amis</div>' + codes.map(function (c) {
        return '<div class="atak-laser-item"><span class="atak-laser-callsign">' + (c.call_sign || '') + '</span> <span class="atak-laser-code">' + (c.laser_code || '1688') + '</span></div>';
      }).join('');
    }).catch(function () {});
  }

  return { fetchLaserCodes: fetchLaserCodes, getCodes: function () { return codes; } };
})();
