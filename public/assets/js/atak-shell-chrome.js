/**
 * Chrome shell ATAK : barre liaison repliable, aperçu métriques, FAB mobile panneaux.
 * Ne touche pas Leaflet / markers — invalidateSize au besoin via événement existant.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'atak-liaison-rail-collapsed';
  var MOBILE_MQ = '(max-width: 900px)';

  function qs(id) {
    return document.getElementById(id);
  }

  function syncPeek() {
    var q = qs('atak-metric-quality-value');
    var l = qs('atak-metric-latency-value');
    var pq = qs('atak-liaison-peek-quality');
    var pl = qs('atak-liaison-peek-latency');
    if (pq && q) pq.textContent = (q.textContent || '—').trim() || '—';
    if (pl && l) pl.textContent = (l.textContent || '—').trim() || '—';
  }

  function setRailCollapsed(rail, collapsed) {
    if (!rail) return;
    rail.classList.toggle('is-collapsed', collapsed);
    var btn = qs('atak-liaison-rail-toggle');
    if (btn) btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    try {
      localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    } catch (e) { /* ignore */ }
  }

  function initLiaisonRail() {
    var rail = qs('atak-liaison-rail');
    var btn = qs('atak-liaison-rail-toggle');
    if (!rail || !btn) return;

    var stored = null;
    try {
      stored = localStorage.getItem(STORAGE_KEY);
    } catch (e) { /* ignore */ }
    if (stored === '0') {
      setRailCollapsed(rail, false);
    } else {
      setRailCollapsed(rail, true);
    }

    btn.addEventListener('click', function () {
      setRailCollapsed(rail, !rail.classList.contains('is-collapsed'));
    });

    syncPeek();
    var quality = qs('atak-metric-quality-value');
    var latency = qs('atak-metric-latency-value');
    if (quality && typeof MutationObserver !== 'undefined') {
      var mo = new MutationObserver(syncPeek);
      mo.observe(quality, { childList: true, characterData: true, subtree: true });
      if (latency) mo.observe(latency, { childList: true, characterData: true, subtree: true });
    }
    window.setInterval(syncPeek, 4000);
  }

  function invalidateMapSoon() {
    window.setTimeout(function () {
      try {
        if (window.AtakMap && typeof window.AtakMap.invalidateSize === 'function') {
          window.AtakMap.invalidateSize();
        } else if (window.atakMap && typeof window.atakMap.invalidateSize === 'function') {
          window.atakMap.invalidateSize();
        } else {
          window.dispatchEvent(new Event('resize'));
        }
      } catch (e) { /* ignore */ }
    }, 80);
  }

  function isMobile() {
    return window.matchMedia(MOBILE_MQ).matches;
  }

  function setPanelOpen(side, open) {
    var panel = qs(side === 'left' ? 'atak-panel-left' : 'atak-panel-right');
    if (!panel) return;
    if (isMobile()) {
      panel.classList.toggle('collapsed', !open);
      panel.classList.toggle('atak-panel--sheet-open', open);
      document.body.classList.toggle('atak-sheet-' + side, open);
    } else {
      panel.classList.toggle('collapsed', !open);
      panel.classList.remove('atak-panel--sheet-open');
      document.body.classList.remove('atak-sheet-' + side);
    }
    invalidateMapSoon();
  }

  function initMobileFab() {
    var leftBtn = qs('atak-fab-left');
    var rightBtn = qs('atak-fab-right');
    var left = qs('atak-panel-left');
    var right = qs('atak-panel-right');
    if (!leftBtn || !rightBtn || !left || !right) return;

    function applyMobileDefaults() {
      if (!isMobile()) {
        left.classList.remove('atak-panel--sheet-open');
        right.classList.remove('atak-panel--sheet-open');
        document.body.classList.remove('atak-sheet-left', 'atak-sheet-right');
        return;
      }
      if (!left.classList.contains('atak-panel--sheet-open')) {
        left.classList.add('collapsed');
      }
      if (!right.classList.contains('atak-panel--sheet-open')) {
        right.classList.add('collapsed');
      }
    }

    leftBtn.addEventListener('click', function () {
      var willOpen = left.classList.contains('collapsed');
      if (isMobile()) setPanelOpen('right', false);
      setPanelOpen('left', willOpen);
    });

    rightBtn.addEventListener('click', function () {
      var willOpen = right.classList.contains('collapsed');
      if (isMobile()) setPanelOpen('left', false);
      setPanelOpen('right', willOpen);
    });

    applyMobileDefaults();
    window.addEventListener('resize', applyMobileDefaults);
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    initLiaisonRail();
    initMobileFab();
  });
})();
