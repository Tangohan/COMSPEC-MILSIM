/* Timeline d’analyse commandement (événements Athena). */
window.ATAKIntelTimeline = (function () {
  'use strict';

  var timer = null;
  var lastId = 0;
  var flashed = {};
  var mutedBeforeId = 0;
  var EMPTY_DEFAULT = 'Les mouvements, tirs, impacts et alertes analysés apparaîtront ici.';
  var EMPTY_CLEARED = 'Journal dégagé pour cette vue. Ouvrez-le pour revoir l’historique, ou attendez les nouveaux événements.';

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
  function isCombat(ev) {
    var t = String(ev && ev.event_type ? ev.event_type : '').toUpperCase();
    return t.indexOf('FIRE') >= 0 || t.indexOf('HIT') >= 0 || t.indexOf('MISSILE') >= 0;
  }
  function pingTag(ev) {
    var t = String(ev && ev.event_type ? ev.event_type : '').toUpperCase();
    if (t.indexOf('EXCHANGE') >= 0) return '[Échange] ';
    if (t.indexOf('HIT') >= 0) return '[Impact] ';
    if (t.indexOf('MISSILE') >= 0) return '[Missile] ';
    if (t.indexOf('FIR') >= 0) return '[Tir] ';
    return '[Contact] ';
  }
  function coords(ev) {
    var p = ev && ev.payload && typeof ev.payload === 'object' ? ev.payload : {};
    var x = Number(p.x);
    var y = Number(p.y);
    if (!isFinite(x) || !isFinite(y)) return null;
    return { x: x, y: y };
  }
  function showOnMap(ev, openPopup) {
    var xy = coords(ev);
    if (!xy || !window.ATAKMap) return;
    if (typeof window.ATAKMap.centerOn === 'function') {
      window.ATAKMap.centerOn(xy.y, xy.x);
    }
    if (typeof window.ATAKMap.addTemporaryPingMarker === 'function') {
      window.ATAKMap.addTemporaryPingMarker(
        xy.x,
        xy.y,
        ev.unit_ref || '',
        pingTag(ev) + (ev.message || ''),
        'combat_' + String(ev.id || Date.now())
      );
    }
    if (openPopup === false) return;
  }

  function render(events) {
    var list = document.getElementById('atak-intel-timeline-list');
    var empty = document.getElementById('atak-intel-timeline-empty');
    if (!list) return;
    var visible = (events || []).filter(function (ev) {
      return Number(ev.id || 0) > mutedBeforeId;
    });
    if (!visible.length) {
      list.innerHTML = '';
      if (empty) {
        empty.hidden = false;
        empty.textContent = mutedBeforeId ? EMPTY_CLEARED : EMPTY_DEFAULT;
      }
      return;
    }
    if (empty) {
      empty.hidden = true;
      empty.textContent = EMPTY_DEFAULT;
    }
    list.innerHTML = visible.map(function (ev) {
      var sev = String(ev.severity || 'info');
      var combat = isCombat(ev);
      var xy = coords(ev);
      var cls = 'atak-timeline__item atak-timeline__item--' + esc(sev);
      if (combat) cls += ' atak-timeline__item--combat';
      if (xy) cls += ' atak-timeline__item--loc';
      var locAttr = xy ? ' data-x="' + esc(String(xy.x)) + '" data-y="' + esc(String(xy.y)) + '"' : '';
      return '<li class="' + cls + '" data-event-id="' + esc(String(ev.id || '')) + '"' + locAttr + '>'
        + '<time>' + esc(clock(ev.created_at)) + '</time>'
        + '<span class="atak-timeline__msg">' + esc(ev.message || ev.event_type) + '</span>'
        + '<span class="atak-timeline__src">' + esc(sourceLabel(ev.source)) + '</span>'
        + '</li>';
    }).join('');
  }

  function bindClicks() {
    var list = document.getElementById('atak-intel-timeline-list');
    if (!list || list._combatBound) return;
    list._combatBound = true;
    list.addEventListener('click', function (e) {
      var li = e.target && e.target.closest ? e.target.closest('.atak-timeline__item--loc') : null;
      if (!li) return;
      var x = Number(li.getAttribute('data-x'));
      var y = Number(li.getAttribute('data-y'));
      if (!isFinite(x) || !isFinite(y) || !window.ATAKMap) return;
      if (typeof window.ATAKMap.centerOn === 'function') window.ATAKMap.centerOn(y, x);
      var msg = '';
      var msgEl = li.querySelector('.atak-timeline__msg');
      if (msgEl) msg = msgEl.textContent || '';
      var tag = li.className.indexOf('combat') >= 0 ? '[Tir] ' : '[Contact] ';
      if (msg.indexOf('échange') >= 0) tag = '[Échange] ';
      else if (msg.indexOf('Impact') >= 0) tag = '[Impact] ';
      else if (msg.toLowerCase().indexOf('missile') >= 0) tag = '[Missile] ';
      else if (msg.indexOf('ouvre le feu') >= 0) tag = '[Tir] ';
      if (typeof window.ATAKMap.addTemporaryPingMarker === 'function') {
        window.ATAKMap.addTemporaryPingMarker(x, y, '', tag + msg, 'combat_click_' + Date.now());
      }
    });
  }

  function flashNew(events) {
    if (!events || !events.length) return;
    var newest = Number(events[0].id || 0);
    if (!newest) return;
    if (lastId > 0) {
      events.forEach(function (ev) {
        var id = Number(ev.id || 0);
        if (id <= lastId || id <= mutedBeforeId || flashed[id] || !isCombat(ev) || !coords(ev)) return;
        flashed[id] = true;
        showOnMap(ev, false);
      });
    }
    if (newest > lastId) lastId = newest;
  }

  function poll() {
    if (!apiBase()) return;
    fetch(apiBase() + '/api/atak/intel-events?mapId=' + encodeURIComponent(mapId()) + '&limit=80', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      var events = (j && j.ok && Array.isArray(j.events)) ? j.events : [];
      render(events);
      bindClicks();
      flashNew(events);
    }).catch(function () {});
  }

  function clearView() {
    mutedBeforeId = lastId;
    var list = document.getElementById('atak-intel-timeline-list');
    if (list) list.innerHTML = '';
    var empty = document.getElementById('atak-intel-timeline-empty');
    if (empty) {
      empty.hidden = false;
      empty.textContent = EMPTY_CLEARED;
    }
    var details = document.getElementById('atak-intel-timeline');
    if (details) details.open = false;
  }

  function bindDetails() {
    var el = document.getElementById('atak-intel-timeline');
    if (!el || el._clearBound) return;
    el._clearBound = true;
    el.addEventListener('toggle', function () {
      if (el.open && mutedBeforeId) {
        mutedBeforeId = 0;
        poll();
      }
    });
  }

  function start() {
    bindDetails();
    poll();
    if (timer) clearInterval(timer);
    timer = setInterval(poll, 5000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  return { refresh: poll, clearView: clearView };
})();
