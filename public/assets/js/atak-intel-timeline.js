/* Timeline d’analyse commandement (événements Athena). */
window.ATAKIntelTimeline = (function () {
  'use strict';

  var timer = null;
  var lastId = 0;

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function clock(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }
  function sourceLabel(s) {
    if (s === 'arma') return 'Arma';
    return 'Athena';
  }

  function render(events) {
    var list = document.getElementById('atak-intel-timeline-list');
    var empty = document.getElementById('atak-intel-timeline-empty');
    if (!list) return;
    if (!events || !events.length) {
      list.innerHTML = '';
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;
    list.innerHTML = events.map(function (ev) {
      var sev = String(ev.severity || 'info');
      return '<li class="atak-timeline__item atak-timeline__item--' + esc(sev) + '">'
        + '<time>' + esc(clock(ev.created_at)) + '</time>'
        + '<span class="atak-timeline__msg">' + esc(ev.message || ev.event_type) + '</span>'
        + '<span class="atak-timeline__src">' + esc(sourceLabel(ev.source)) + '</span>'
        + '</li>';
    }).join('');
  }

  function poll() {
    if (!apiBase()) return;
    fetch(apiBase() + '/api/atak/intel-events?mapId=' + encodeURIComponent(mapId()) + '&limit=80', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      var events = (j && j.ok && Array.isArray(j.events)) ? j.events : [];
      render(events);
      if (events[0] && Number(events[0].id) > lastId) {
        lastId = Number(events[0].id);
      }
    }).catch(function () {});
  }

  function start() {
    poll();
    if (timer) clearInterval(timer);
    timer = setInterval(poll, 5000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  return { refresh: poll };
})();
