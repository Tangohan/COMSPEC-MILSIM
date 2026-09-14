/**
 * ATAK — épingler un module en raccourci à gauche de la carte.
 * Le nœud du module est déplacé (mêmes écouteurs), pas cloné.
 */
(function () {
  'use strict';

  var STORAGE = 'atak-pin-dock-v1';
  var MAX_PINS = 3;
  var PINNABLE = ['chat', 'radio', 'liaison', 'orders', 'medical', 'pings'];
  var TITLES = {
    chat: 'Tchat',
    radio: 'Radio',
    liaison: 'Liaison',
    orders: 'Ordres',
    medical: 'Médical',
    pings: 'Pings'
  };
  var PIN_SVG = '<svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 12V5.5c1.38 0 2.5-1.12 2.5-2.5h-13C5.5 4.38 6.62 5.5 8 5.5V12l-2 2v1.5h5.2V22h1.6v-6.5H18V14l-2-2z"/></svg>';

  var pins = [];
  var homeParent = null;

  function qs(id) {
    return document.getElementById(id);
  }

  function dock() {
    return qs('atak-pin-dock');
  }

  function isDeviceOrPopout() {
    if (window.ATAK_POPOUT) return true;
    var b = document.body;
    return !!(b && (b.classList.contains('atak-page--device') || b.classList.contains('atak-device-embed')));
  }

  function isPinned(tab) {
    return pins.some(function (p) { return p.id === tab; });
  }

  function pinIndex(tab) {
    for (var i = 0; i < pins.length; i++) {
      if (pins[i].id === tab) return i;
    }
    return -1;
  }

  function loadState() {
    try {
      var raw = localStorage.getItem(STORAGE);
      if (!raw) return [];
      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return [];
      return parsed.map(function (item) {
        if (typeof item === 'string') return { id: item, collapsed: false };
        var id = item && item.id ? String(item.id) : '';
        return { id: id, collapsed: !!(item && item.collapsed) };
      }).filter(function (p) {
        return PINNABLE.indexOf(p.id) !== -1;
      }).slice(0, MAX_PINS);
    } catch (e) {
      return [];
    }
  }

  function saveState() {
    try {
      localStorage.setItem(STORAGE, JSON.stringify(pins.map(function (p) {
        return { id: p.id, collapsed: !!p.collapsed };
      })));
    } catch (e) { /* ignore */ }
  }

  function refreshMap() {
    window.setTimeout(function () {
      try {
        if (window.ATAKPanelChrome && typeof window.ATAKPanelChrome.refreshMapSize === 'function') {
          window.ATAKPanelChrome.refreshMapSize();
        } else {
          window.dispatchEvent(new Event('resize'));
        }
      } catch (e) { /* ignore */ }
    }, 60);
  }

  function keepPinnedVisible() {
    document.querySelectorAll('#atak-pin-dock .atak-tabs-content').forEach(function (c) {
      c.classList.add('active');
    });
  }

  function titleOf(tab) {
    var btn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]');
    var label = btn ? btn.querySelector('.atak-tab-label') : null;
    var text = label ? (label.textContent || '').trim() : '';
    return text || TITLES[tab] || tab;
  }

  function syncPinButton(tab) {
    var pinBtn = document.querySelector('.atak-tab-pin[data-pin-tab="' + tab + '"]');
    var tabBtn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]');
    var on = isPinned(tab);
    if (pinBtn) {
      pinBtn.classList.toggle('is-on', on);
      pinBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      pinBtn.title = on
        ? 'Retirer le raccourci « ' + titleOf(tab) + ' »'
        : 'Épingler « ' + titleOf(tab) + ' » en raccourci à gauche de la carte';
    }
    if (tabBtn) tabBtn.classList.toggle('is-pinned', on);
  }

  function syncDockChrome() {
    var el = dock();
    if (!el) return;
    el.hidden = pins.length === 0;
    document.body.classList.toggle('atak-has-pins', pins.length > 0);
    PINNABLE.forEach(syncPinButton);
    keepPinnedVisible();
    syncBadges();
  }

  function badgeSource(tab) {
    var tabBtn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]');
    return tabBtn ? tabBtn.querySelector('.atak-tab-badge, .atak-medical-tab-badge') : null;
  }

  function syncBadges() {
    pins.forEach(function (p) {
      var win = document.querySelector('.atak-pin-window[data-pin-tab="' + p.id + '"]');
      if (!win) return;
      var dest = win.querySelector('.atak-pin-window__badge');
      var src = badgeSource(p.id);
      if (!dest) return;
      if (!src || src.hidden || !(src.textContent || '').trim()) {
        dest.hidden = true;
        dest.textContent = '';
        return;
      }
      dest.hidden = false;
      dest.textContent = (src.textContent || '').trim();
    });
  }

  function activateFallback(leavingTab) {
    var tabBtn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + leavingTab + '"]');
    var wasActive = !!(tabBtn && tabBtn.classList.contains('active'));
    if (!wasActive) return;

    var pick = null;
    var sectionTabs = [];
    if (window.ATAKSectionNav && typeof window.ATAKSectionNav.sectionForTab === 'function') {
      var sec = window.ATAKSectionNav.sectionForTab(leavingTab);
      document.querySelectorAll('#atak-panel-left .atak-tab.is-section-visible[data-tab]').forEach(function (b) {
        if (b.hidden) return;
        var id = b.getAttribute('data-tab');
        if (!id || isPinned(id)) return;
        if (sec && b.getAttribute('data-atak-section') && b.getAttribute('data-atak-section') !== sec) return;
        sectionTabs.push(id);
      });
    }
    pick = sectionTabs[0];
    if (!pick) {
      var any = document.querySelector('#atak-panel-left .atak-tab[data-tab]:not(.is-pinned):not([hidden])');
      pick = any ? any.getAttribute('data-tab') : '';
    }
    if (pick && window.ATAKPanelChrome && typeof window.ATAKPanelChrome.activateTab === 'function') {
      window.ATAKPanelChrome.activateTab(pick);
    }
    keepPinnedVisible();
  }

  function highlight(tab) {
    document.querySelectorAll('.atak-pin-window').forEach(function (w) {
      w.classList.toggle('is-focus', w.getAttribute('data-pin-tab') === tab);
    });
    window.setTimeout(function () {
      document.querySelectorAll('.atak-pin-window.is-focus').forEach(function (w) {
        w.classList.remove('is-focus');
      });
    }, 900);
  }

  function setCollapsed(tab, collapsed) {
    var idx = pinIndex(tab);
    if (idx < 0) return;
    pins[idx].collapsed = !!collapsed;
    var win = document.querySelector('.atak-pin-window[data-pin-tab="' + tab + '"]');
    if (win) {
      win.classList.toggle('is-collapsed', !!collapsed);
      var tog = win.querySelector('[data-pin-collapse]');
      if (tog) {
        tog.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        tog.title = collapsed ? 'Déplier le raccourci' : 'Réduire le raccourci';
      }
    }
    saveState();
    refreshMap();
  }

  function buildWindow(tab) {
    var wrap = document.createElement('div');
    wrap.className = 'atak-pin-window' + (tab === 'chat' ? ' atak-pin-window--chat' : '');
    wrap.setAttribute('data-pin-tab', tab);
    wrap.setAttribute('role', 'region');
    wrap.setAttribute('aria-label', titleOf(tab) + ' — raccourci épinglé');

    var bar = document.createElement('header');
    bar.className = 'atak-pin-window__bar';

    var title = document.createElement('button');
    title.type = 'button';
    title.className = 'atak-pin-window__title';
    title.setAttribute('data-pin-collapse', '1');
    title.setAttribute('aria-expanded', 'true');
    title.title = 'Réduire le raccourci';
    title.textContent = titleOf(tab);

    var badge = document.createElement('span');
    badge.className = 'atak-pin-window__badge';
    badge.hidden = true;

    var expand = document.createElement('button');
    expand.type = 'button';
    expand.className = 'atak-pin-window__btn';
    expand.setAttribute('data-pin-expand', '1');
    expand.title = 'Ouvrir dans le panneau';
    expand.setAttribute('aria-label', 'Ouvrir ' + titleOf(tab) + ' dans le panneau');
    expand.textContent = '↗';

    var unpin = document.createElement('button');
    unpin.type = 'button';
    unpin.className = 'atak-pin-window__btn';
    unpin.setAttribute('data-pin-unpin', '1');
    unpin.title = 'Retirer l’épingle';
    unpin.setAttribute('aria-label', 'Retirer l’épingle ' + titleOf(tab));
    unpin.textContent = '×';

    bar.appendChild(title);
    bar.appendChild(badge);
    bar.appendChild(expand);
    bar.appendChild(unpin);
    wrap.appendChild(bar);

    bar.addEventListener('click', function (e) {
      var t = e.target && e.target.closest ? e.target.closest('[data-pin-expand], [data-pin-unpin], [data-pin-collapse]') : null;
      if (!t) return;
      e.preventDefault();
      e.stopPropagation();
      if (t.hasAttribute('data-pin-unpin')) {
        unpinTab(tab);
        return;
      }
      if (t.hasAttribute('data-pin-expand')) {
        expandTab(tab);
        return;
      }
      var rec = pins[pinIndex(tab)];
      setCollapsed(tab, !(rec && rec.collapsed));
    });

    return wrap;
  }

  function pinTab(tab, opts) {
    opts = opts || {};
    if (PINNABLE.indexOf(tab) === -1 || isPinned(tab) || isDeviceOrPopout()) return false;
    if (pins.length >= MAX_PINS) return false;

    var panel = qs('tab-' + tab);
    var host = dock();
    if (!panel || !host || !homeParent) return false;

    var win = buildWindow(tab);
    host.appendChild(win);
    panel.classList.add('active');
    win.appendChild(panel);

    pins.push({ id: tab, collapsed: !!opts.collapsed });
    if (opts.collapsed) {
      win.classList.add('is-collapsed');
      var tog = win.querySelector('[data-pin-collapse]');
      if (tog) {
        tog.setAttribute('aria-expanded', 'false');
        tog.title = 'Déplier le raccourci';
      }
    }

    if (!opts.silentFallback) activateFallback(tab);
    syncDockChrome();
    if (!opts.fromRestore) saveState();
    refreshMap();
    return true;
  }

  function unpinTab(tab, opts) {
    opts = opts || {};
    var idx = pinIndex(tab);
    if (idx < 0) return false;
    var panel = qs('tab-' + tab);
    var win = document.querySelector('.atak-pin-window[data-pin-tab="' + tab + '"]');
    if (panel && homeParent) {
      homeParent.appendChild(panel);
      var activeBtn = document.querySelector('#atak-panel-left .atak-tab.active[data-tab]');
      var activeId = activeBtn ? activeBtn.getAttribute('data-tab') : '';
      if (activeId !== tab) panel.classList.remove('active');
    }
    if (win && win.parentNode) win.parentNode.removeChild(win);
    pins.splice(idx, 1);
    syncDockChrome();
    if (!opts.fromRestore) saveState();
    refreshMap();
    return true;
  }

  function expandTab(tab) {
    unpinTab(tab);
    if (window.ATAKSectionNav && typeof window.ATAKSectionNav.setSection === 'function'
      && typeof window.ATAKSectionNav.sectionForTab === 'function') {
      var sec = window.ATAKSectionNav.sectionForTab(tab);
      if (sec) window.ATAKSectionNav.setSection(sec, { skipActivate: true });
    }
    if (window.ATAKPanelChrome && typeof window.ATAKPanelChrome.activateTab === 'function') {
      window.ATAKPanelChrome.activateTab(tab);
    }
    keepPinnedVisible();
  }

  function togglePin(tab) {
    if (isPinned(tab)) {
      unpinTab(tab);
      return;
    }
    if (pins.length >= MAX_PINS) {
      var pinBtn = document.querySelector('.atak-tab-pin[data-pin-tab="' + tab + '"]');
      if (pinBtn) {
        pinBtn.title = 'Trois raccourcis sont déjà épinglés. Retirez-en un pour en ajouter un autre.';
      }
      highlight(pins[0] ? pins[0].id : '');
      return;
    }
    pinTab(tab);
  }

  function injectPinButtons() {
    var list = document.querySelector('#atak-panel-left .atak-module-list');
    if (!list) return;
    PINNABLE.forEach(function (id) {
      var btn = list.querySelector('.atak-tab[data-tab="' + id + '"]');
      if (!btn || (btn.parentElement && btn.parentElement.classList.contains('atak-tab-wrap'))) return;
      var wrap = document.createElement('div');
      wrap.className = 'atak-tab-wrap';
      wrap.setAttribute('data-tab-wrap', id);
      btn.parentNode.insertBefore(wrap, btn);
      wrap.appendChild(btn);
      var pin = document.createElement('button');
      pin.type = 'button';
      pin.className = 'atak-tab-pin';
      pin.setAttribute('data-pin-tab', id);
      pin.setAttribute('aria-pressed', 'false');
      pin.setAttribute('aria-label', 'Épingler ' + titleOf(id) + ' en raccourci');
      pin.title = 'Épingler « ' + titleOf(id) + ' » en raccourci à gauche de la carte';
      pin.innerHTML = PIN_SVG;
      wrap.appendChild(pin);
    });
  }

  function onListClickCapture(e) {
    var pinBtn = e.target && e.target.closest ? e.target.closest('.atak-tab-pin') : null;
    if (pinBtn) {
      e.preventDefault();
      e.stopPropagation();
      togglePin(pinBtn.getAttribute('data-pin-tab'));
      return;
    }
    var tabBtn = e.target && e.target.closest ? e.target.closest('#atak-panel-left .atak-tab[data-tab]') : null;
    if (!tabBtn) return;
    var tab = tabBtn.getAttribute('data-tab');
    if (!isPinned(tab)) return;
    e.preventDefault();
    e.stopPropagation();
    highlight(tab);
    var rec = pins[pinIndex(tab)];
    if (rec && rec.collapsed) setCollapsed(tab, false);
  }

  function restore() {
    var stored = loadState();
    stored.forEach(function (p) {
      pinTab(p.id, { collapsed: p.collapsed, fromRestore: true, silentFallback: true });
    });
    var active = document.querySelector('#atak-panel-left .atak-tab.active[data-tab]');
    var activeId = active ? active.getAttribute('data-tab') : '';
    if (activeId && isPinned(activeId)) activateFallback(activeId);
    syncDockChrome();
  }

  function init() {
    if (isDeviceOrPopout()) return;
    var host = dock();
    homeParent = document.querySelector('#atak-panel-left .atak-left-body');
    if (!host || !homeParent) return;

    injectPinButtons();

    var list = document.querySelector('#atak-panel-left .atak-module-list');
    if (list) list.addEventListener('click', onListClickCapture, true);

    restore();

    if (typeof MutationObserver !== 'undefined') {
      var mo = new MutationObserver(syncBadges);
      document.querySelectorAll('#atak-panel-left .atak-tab-badge, #atak-panel-left .atak-medical-tab-badge').forEach(function (el) {
        mo.observe(el, { attributes: true, childList: true, characterData: true, subtree: true });
      });
    }
    window.setInterval(syncBadges, 4000);
  }

  window.ATAKPinDock = {
    isPinned: isPinned,
    pin: pinTab,
    unpin: unpinTab,
    keepPinnedVisible: keepPinnedVisible,
    firstUnpinnedIn: function (tabIds) {
      var ids = tabIds || [];
      for (var i = 0; i < ids.length; i++) {
        if (!isPinned(ids[i])) return ids[i];
      }
      return '';
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
