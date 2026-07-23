/* COMSPEC ATAK — largeur des panneaux (localStorage) + détachement en fenêtre. */
(function () {
  'use strict';

  var LS_LEFT = 'atak_panel_left_w_v1';
  var LS_RIGHT = 'atak_panel_right_w_v1';
  var CHANNEL = 'atak-panel-chrome-v1';
  var MIN_LEFT = 240;
  var MAX_LEFT_RATIO = 0.72;
  var MIN_RIGHT = 200;
  var MAX_RIGHT_RATIO = 0.55;
  var COLLAPSED_W = 88;

  var channel = null;
  var popoutWindows = { left: null, right: null };
  var applyingRemote = false;
  var dragState = null;

  function refreshMapSize() {
    try {
      var m = window.ATAKMap && typeof window.ATAKMap.getMap === 'function'
        ? window.ATAKMap.getMap()
        : (window.ATAKMap && window.ATAKMap._map);
      if (m && typeof m.invalidateSize === 'function') {
        m.invalidateSize({ animate: false });
      }
    } catch (e) { /* ignore */ }
  }

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function readStored(key) {
    try {
      var v = parseInt(localStorage.getItem(key) || '', 10);
      return isNaN(v) ? null : v;
    } catch (e) {
      return null;
    }
  }

  function writeStored(key, px) {
    try {
      localStorage.setItem(key, String(Math.round(px)));
    } catch (e) { /* ignore */ }
  }

  function isNotesOverride(panel) {
    return panel && (panel.classList.contains('is-notes-wide') || panel.classList.contains('is-notes-expanded'));
  }

  function applyWidth(side, px, persist) {
    var panel = document.getElementById(side === 'left' ? 'atak-panel-left' : 'atak-panel-right');
    if (!panel || panel.classList.contains('collapsed') || panel.classList.contains('is-popped-out')) return;
    if (side === 'left' && isNotesOverride(panel)) return;

    var max = side === 'left'
      ? Math.floor(window.innerWidth * MAX_LEFT_RATIO)
      : Math.floor(window.innerWidth * MAX_RIGHT_RATIO);
    var min = side === 'left' ? MIN_LEFT : MIN_RIGHT;
    var w = clamp(Math.round(px), min, Math.max(min, max));
    var prop = side === 'left' ? '--atak-left-w' : '--atak-right-w';
    panel.style.setProperty(prop, w + 'px');
    panel.style.width = w + 'px';
    panel.style.minWidth = w + 'px';
    panel.classList.add('is-resized');
    if (persist) writeStored(side === 'left' ? LS_LEFT : LS_RIGHT, w);
    refreshMapSize();
  }

  function restoreStoredWidths() {
    if (window.ATAK_POPOUT) return;
    var left = readStored(LS_LEFT);
    var right = readStored(LS_RIGHT);
    if (left) applyWidth('left', left, false);
    if (right) applyWidth('right', right, false);
    setTimeout(refreshMapSize, 60);
  }

  function activeTabId() {
    var active = document.querySelector('#atak-panel-left .atak-tab.active[data-tab]');
    return active ? active.getAttribute('data-tab') : '';
  }

  function selectTab(tab, fromRemote) {
    if (!tab) return;
    var btn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]');
    if (!btn) return;
    if (fromRemote) applyingRemote = true;
    try {
      btn.click();
    } finally {
      if (fromRemote) applyingRemote = false;
    }
  }

  function buildPopoutUrl(side) {
    var url = new URL(window.location.href);
    url.searchParams.set('popout', side);
    var tab = activeTabId();
    if (side === 'left' && tab) url.searchParams.set('tab', tab);
    else url.searchParams.delete('tab');
    return url.toString();
  }

  function setDockVisible(side, visible) {
    var panel = document.getElementById(side === 'left' ? 'atak-panel-left' : 'atak-panel-right');
    var dock = document.getElementById(side === 'left' ? 'atak-panel-left-dock' : 'atak-panel-right-dock');
    if (!panel) return;
    panel.classList.toggle('is-popped-out', !!visible);
    if (dock) dock.hidden = !visible;
    if (visible) {
      panel.style.width = COLLAPSED_W + 'px';
      panel.style.minWidth = COLLAPSED_W + 'px';
    } else {
      panel.style.width = '';
      panel.style.minWidth = '';
      restoreStoredWidths();
    }
    setTimeout(refreshMapSize, 80);
  }

  function openPopout(side) {
    if (window.ATAK_POPOUT) return;
    var existing = popoutWindows[side];
    if (existing && !existing.closed) {
      try { existing.focus(); } catch (e) { /* ignore */ }
      return;
    }
    var features = side === 'left'
      ? 'popup=yes,width=420,height=860,menubar=no,toolbar=no,location=no,status=no'
      : 'popup=yes,width=360,height=860,menubar=no,toolbar=no,location=no,status=no';
    var win = window.open(buildPopoutUrl(side), 'atak-popout-' + side, features);
    if (!win) {
      if (window.ATAKShowError) {
        window.ATAKShowError('Impossible d’ouvrir une autre fenêtre. Autorisez les fenêtres contextuelles pour ce site.');
      }
      return;
    }
    popoutWindows[side] = win;
    setDockVisible(side, true);
    broadcast({ type: 'popout-opened', side: side, tab: activeTabId() });
    var watch = setInterval(function () {
      if (!popoutWindows[side] || popoutWindows[side].closed) {
        clearInterval(watch);
        if (popoutWindows[side] === win || !popoutWindows[side]) {
          popoutWindows[side] = null;
          setDockVisible(side, false);
        }
      }
    }, 900);
  }

  function focusPopout(side) {
    var win = popoutWindows[side];
    if (win && !win.closed) {
      try { win.focus(); } catch (e) { /* ignore */ }
      return;
    }
    openPopout(side);
  }

  function restorePopout(side) {
    var win = popoutWindows[side];
    if (win && !win.closed) {
      try { win.close(); } catch (e) { /* ignore */ }
    }
    popoutWindows[side] = null;
    setDockVisible(side, false);
    broadcast({ type: 'popout-restored', side: side });
  }

  function broadcast(msg) {
    if (!channel || applyingRemote) return;
    try {
      channel.postMessage(Object.assign({ source: window.ATAK_POPOUT || 'main' }, msg));
    } catch (e) { /* ignore */ }
  }

  function onChannelMessage(ev) {
    var data = ev && ev.data;
    if (!data || typeof data !== 'object') return;
    if (data.source && data.source === (window.ATAK_POPOUT || 'main')) return;

    if (data.type === 'tab' && data.tab) {
      selectTab(data.tab, true);
      return;
    }
    if (data.type === 'popout-closed' && data.side && !window.ATAK_POPOUT) {
      popoutWindows[data.side] = null;
      setDockVisible(data.side, false);
      return;
    }
    if (data.type === 'width' && data.side && data.px && !window.ATAK_POPOUT) {
      applyWidth(data.side, data.px, true);
    }
  }

  function bindResize(handle) {
    var side = handle.getAttribute('data-atak-resize');
    if (side !== 'left' && side !== 'right') return;

    function onMove(ev) {
      if (!dragState) return;
      var clientX = ev.touches && ev.touches[0] ? ev.touches[0].clientX : ev.clientX;
      var next = side === 'left'
        ? dragState.startWidth + (clientX - dragState.startX)
        : dragState.startWidth - (clientX - dragState.startX);
      applyWidth(side, next, false);
      ev.preventDefault();
    }

    function onUp() {
      if (!dragState) return;
      var panel = document.getElementById(side === 'left' ? 'atak-panel-left' : 'atak-panel-right');
      var finalW = panel ? panel.getBoundingClientRect().width : 0;
      dragState = null;
      document.body.classList.remove('atak-resizing');
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      document.removeEventListener('touchmove', onMove);
      document.removeEventListener('touchend', onUp);
      if (finalW) {
        applyWidth(side, finalW, true);
        broadcast({ type: 'width', side: side, px: finalW });
      }
      setTimeout(refreshMapSize, 40);
    }

    function onDown(ev) {
      var panel = document.getElementById(side === 'left' ? 'atak-panel-left' : 'atak-panel-right');
      if (!panel || panel.classList.contains('collapsed') || panel.classList.contains('is-popped-out')) return;
      if (side === 'left' && isNotesOverride(panel)) return;
      var clientX = ev.touches && ev.touches[0] ? ev.touches[0].clientX : ev.clientX;
      dragState = {
        side: side,
        startX: clientX,
        startWidth: panel.getBoundingClientRect().width
      };
      document.body.classList.add('atak-resizing');
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
      document.addEventListener('touchmove', onMove, { passive: false });
      document.addEventListener('touchend', onUp);
      ev.preventDefault();
    }

    handle.addEventListener('mousedown', onDown);
    handle.addEventListener('touchstart', onDown, { passive: false });
    handle.addEventListener('dblclick', function () {
      var fallback = side === 'left' ? 300 : 280;
      try {
        var cs = getComputedStyle(document.documentElement);
        var raw = cs.getPropertyValue(side === 'left' ? '--atak-left-w' : '--atak-right-w');
        var parsed = parseInt(raw, 10);
        if (!isNaN(parsed)) fallback = parsed;
      } catch (e) { /* ignore */ }
      var panel = document.getElementById(side === 'left' ? 'atak-panel-left' : 'atak-panel-right');
      if (panel) {
        panel.style.width = '';
        panel.style.minWidth = '';
        panel.style.removeProperty(side === 'left' ? '--atak-left-w' : '--atak-right-w');
        panel.classList.remove('is-resized');
      }
      try {
        localStorage.removeItem(side === 'left' ? LS_LEFT : LS_RIGHT);
      } catch (e2) { /* ignore */ }
      setTimeout(refreshMapSize, 40);
    });
  }

  function bindChrome() {
    document.querySelectorAll('[data-atak-resize]').forEach(bindResize);

    document.querySelectorAll('[data-atak-popout]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openPopout(btn.getAttribute('data-atak-popout'));
      });
    });

    document.querySelectorAll('[data-atak-popout-focus]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        focusPopout(btn.getAttribute('data-atak-popout-focus'));
      });
    });

    document.querySelectorAll('[data-atak-popout-restore]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        restorePopout(btn.getAttribute('data-atak-popout-restore'));
      });
    });

    document.querySelectorAll('#atak-panel-left .atak-tab[data-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (applyingRemote) return;
        broadcast({ type: 'tab', tab: btn.getAttribute('data-tab') });
      });
    });
  }

  function initPopoutMode() {
    var side = window.ATAK_POPOUT;
    if (!side) return;
    document.title = (side === 'left' ? 'Panneau ATAK' : 'Effectifs ATAK') + ' · COMSPEC';
    if (side === 'left' && window.ATAK_POPOUT_TAB) {
      setTimeout(function () { selectTab(window.ATAK_POPOUT_TAB, true); }, 0);
    }
    window.addEventListener('beforeunload', function () {
      broadcast({ type: 'popout-closed', side: side });
    });
  }

  function initChannel() {
    if (typeof BroadcastChannel === 'undefined') return;
    try {
      channel = new BroadcastChannel(CHANNEL);
      channel.onmessage = onChannelMessage;
    } catch (e) {
      channel = null;
    }
  }

  function init() {
    initChannel();
    bindChrome();
    restoreStoredWidths();
    initPopoutMode();
    window.addEventListener('resize', function () {
      if (dragState) return;
      var left = document.getElementById('atak-panel-left');
      var right = document.getElementById('atak-panel-right');
      if (left && left.classList.contains('is-resized') && !left.classList.contains('is-popped-out')) {
        applyWidth('left', left.getBoundingClientRect().width, true);
      }
      if (right && right.classList.contains('is-resized') && !right.classList.contains('is-popped-out')) {
        applyWidth('right', right.getBoundingClientRect().width, true);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.ATAKPanelChrome = {
    applyWidth: applyWidth,
    openPopout: openPopout,
    restorePopout: restorePopout,
    selectTab: selectTab,
    refreshMapSize: refreshMapSize
  };
})();
