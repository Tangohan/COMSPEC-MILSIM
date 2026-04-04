/* COMSPEC ATAK - Pings */
window.ATAKPings = (function () {
  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : (window.location.protocol + '//' + window.location.hostname + ':3001');
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchPings() {
    var url = getApiBase() + '/api/pings?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      var list = Array.isArray(data) ? data : [];
      var el = document.getElementById('atak-pings-list');
      if (el) el.innerHTML = list.map(formatPing).join('');
      bindPingClicks();
    }).catch(function () {});
  }

  function formatPing(p) {
    var time = p.created_at ? p.created_at.replace('T', ' ').substring(11, 19) : '';
    return '<div class="atak-ping-item" data-x="' + (p.pos_x || '') + '" data-y="' + (p.pos_y || '') + '">' +
      '<strong>' + (p.author || '') + '</strong> ' + (p.message || '') + ' <span style="color:var(--atak-muted)">' + time + '</span></div>';
  }

  function appendPing(ping) {
    var el = document.getElementById('atak-pings-list');
    if (el) {
      el.insertAdjacentHTML('beforeend', formatPing(ping));
      bindPingClicks();
    }
  }

  function bindPingClicks() {
    document.querySelectorAll('#atak-pings-list .atak-ping-item').forEach(function (node) {
      if (node._bound) return;
      node._bound = true;
      node.addEventListener('click', function () {
        var x = this.getAttribute('data-x');
        var y = this.getAttribute('data-y');
        if (x && y && window.ATAKMap && window.ATAKMap.centerOn) {
          window.ATAKMap.centerOn(parseFloat(y), parseFloat(x));
        }
      });
    });
  }

  return { appendPing: appendPing, fetchPings: fetchPings };
})();
