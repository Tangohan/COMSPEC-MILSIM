/**
 * ATAK — navigation par domaines (rail) + modules (onglets), style Athena C2.
 * Conserve data-tab / activateTab existants. Ne touche pas au header.
 */
(function () {
  'use strict';

  var STORAGE_SECTION = 'atak-c2-section-v1';
  var STORAGE_DRAWER = 'atak-c2-drawer-open-v1';

  var SECTIONS = {
    sitac: {
      eyebrow: 'ATHENA / SITAC',
      title: 'Situation tactique',
      meta: 'Théâtre',
      tabs: ['markers', 'pings', 'charges', 'identification', 'situation']
    },
    forces: {
      eyebrow: 'ATHENA / FORCES',
      title: 'Forces & personnel',
      meta: 'Effectifs',
      tabs: ['medical', 'etat', 'terminaux']
    },
    intel: {
      eyebrow: 'ATHENA / INTEL',
      title: 'Renseignement',
      meta: 'Terrain',
      tabs: ['frs', 'photos', 'personnes']
    },
    c2: {
      eyebrow: 'ATHENA / C2',
      title: 'Commandement',
      meta: 'Ordres',
      tabs: ['orders']
    },
    notes: {
      eyebrow: 'ATHENA / NOTES',
      title: 'Notes de mission',
      meta: 'Bloc-notes',
      tabs: ['notes']
    },
    journal: {
      eyebrow: 'ATHENA / JOURNAL',
      title: 'Relecture',
      meta: 'Après-action',
      tabs: ['replay']
    },
    comms: {
      eyebrow: 'ATHENA / COMMS',
      title: 'Communications',
      meta: 'Liaison',
      tabs: ['chat', 'radio', 'liaison']
    },
    support: {
      eyebrow: 'ATHENA / APPUIS',
      title: 'Appuis',
      meta: 'Feu & air',
      tabs: ['jtac']
    }
  };

  var TAB_TO_SECTION = {};
  Object.keys(SECTIONS).forEach(function (key) {
    SECTIONS[key].tabs.forEach(function (tab) {
      TAB_TO_SECTION[tab] = key;
    });
  });

  var currentSection = 'intel';

  function qs(id) {
    return document.getElementById(id);
  }

  function visibleTabsInSection(sectionId) {
    var conf = SECTIONS[sectionId];
    if (!conf) return [];
    return conf.tabs.filter(function (tab) {
      var btn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]');
      return btn && !btn.hidden;
    });
  }

  function activateTab(tab) {
    if (window.ATAKPanelChrome && typeof window.ATAKPanelChrome.activateTab === 'function') {
      return window.ATAKPanelChrome.activateTab(tab);
    }
    if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.activateTab === 'function') {
      return window.ATAKSessionProfile.activateTab(tab);
    }
    var btn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]');
    if (!btn || btn.hidden) return false;
    document.querySelectorAll('#atak-panel-left .atak-tab[data-tab]').forEach(function (b) {
      var on = b === btn;
      b.classList.toggle('active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('.atak-tabs-content').forEach(function (c) {
      c.classList.toggle('active', c.id === 'tab-' + tab);
    });
    return true;
  }

  function updateSideMeta(sectionId) {
    var conf = SECTIONS[sectionId] || SECTIONS.intel;
    var eyebrow = qs('atak-side-eyebrow');
    var title = qs('atak-side-title');
    var meta = qs('atak-side-meta');
    if (eyebrow) eyebrow.textContent = conf.eyebrow;
    if (title) title.textContent = conf.title;
    if (meta) {
      var n = visibleTabsInSection(sectionId).length;
      meta.textContent = n + (n > 1 ? ' modules' : ' module');
    }
  }

  function syncSectionBadges() {
    Object.keys(SECTIONS).forEach(function (sectionId) {
      var btn = document.querySelector('.atak-section-btn[data-section="' + sectionId + '"]');
      if (!btn) return;
      var badge = btn.querySelector('.atak-section-btn__badge');
      if (!badge) return;
      var total = 0;
      var alertish = false;
      SECTIONS[sectionId].tabs.forEach(function (tab) {
        var tabBtn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]');
        if (!tabBtn || tabBtn.hidden) return;
        var b = tabBtn.querySelector('.atak-tab-badge, .atak-medical-tab-badge');
        if (!b || b.hidden) return;
        var raw = (b.textContent || '').trim();
        var n = parseInt(raw, 10);
        if (!isNaN(n) && n > 0) {
          total += n;
          if (tab === 'medical' || tab === 'pings' || tab === 'orders' || tab === 'terminaux') alertish = true;
        } else if (raw && raw !== '·') {
          total += 1;
        }
      });
      if (total > 0) {
        badge.hidden = false;
        badge.textContent = String(total > 99 ? '99+' : total);
        badge.classList.toggle('is-alert', alertish);
        badge.classList.toggle('is-live', !alertish && sectionId === 'forces');
      } else {
        badge.hidden = true;
        badge.textContent = '';
        badge.classList.remove('is-alert', 'is-live');
      }
    });
  }

  function setSection(sectionId, opts) {
    opts = opts || {};
    if (!SECTIONS[sectionId]) sectionId = 'intel';
    currentSection = sectionId;

    document.querySelectorAll('.atak-section-btn[data-section]').forEach(function (btn) {
      var on = btn.getAttribute('data-section') === sectionId;
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });

    document.querySelectorAll('#atak-panel-left .atak-tab[data-tab]').forEach(function (tabBtn) {
      var tab = tabBtn.getAttribute('data-tab');
      var belongs = TAB_TO_SECTION[tab] === sectionId;
      tabBtn.classList.toggle('is-section-visible', belongs);
      tabBtn.setAttribute('data-atak-section', TAB_TO_SECTION[tab] || '');
    });

    updateSideMeta(sectionId);
    syncSectionBadges();

    Object.keys(SECTIONS).forEach(function (id) {
      document.body.classList.toggle('atak-section-' + id, id === sectionId);
    });

    try {
      localStorage.setItem(STORAGE_SECTION, sectionId);
    } catch (e) { /* ignore */ }

    try {
      document.dispatchEvent(new CustomEvent('atak:section-change', { detail: { section: sectionId } }));
    } catch (e) { /* ignore */ }

    if (opts.skipActivate) return;

    var active = document.querySelector('#atak-panel-left .atak-tab.active[data-tab]');
    var activeTab = active ? active.getAttribute('data-tab') : '';
    var visible = visibleTabsInSection(sectionId);
    if (activeTab && visible.indexOf(activeTab) !== -1) {
      return;
    }
    if (visible.length) {
      activateTab(visible[0]);
    }
  }

  function sectionForTab(tab) {
    return TAB_TO_SECTION[tab] || null;
  }

  function ensureSectionForTab(tab) {
    var sec = sectionForTab(tab);
    if (sec && sec !== currentSection) {
      setSection(sec, { skipActivate: true });
    } else {
      updateSideMeta(currentSection);
      syncSectionBadges();
    }
  }

  function setLeftCollapsed(collapsed) {
    var panel = qs('atak-panel-left');
    if (!panel || panel.classList.contains('is-popped-out')) return;
    panel.classList.toggle('collapsed', !!collapsed);
    var sideBtn = qs('atak-side-collapse');
    var railBtn = qs('atak-section-collapse');
    if (sideBtn) sideBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    if (railBtn) railBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    window.setTimeout(function () {
      try {
        window.dispatchEvent(new Event('resize'));
      } catch (e) { /* ignore */ }
    }, 80);
  }

  function setDrawerOpen(open) {
    document.body.classList.toggle('atak-drawer-collapsed', !open);
    var toggle = qs('atak-drawer-toggle');
    if (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.textContent = open ? '⌄' : '⌃';
      toggle.title = open ? 'Réduire le tableau des effectifs' : 'Agrandir le tableau des effectifs';
    }
    try {
      localStorage.setItem(STORAGE_DRAWER, open ? '1' : '0');
    } catch (e) { /* ignore */ }
    window.setTimeout(function () {
      try {
        window.dispatchEvent(new Event('resize'));
      } catch (e) { /* ignore */ }
    }, 80);
  }

  function initDrawer() {
    var toggle = qs('atak-drawer-toggle');
    var head = document.querySelector('#atak-effectifs-drawer .atak-drawer__head');
    var open = true;
    try {
      var stored = localStorage.getItem(STORAGE_DRAWER);
      if (stored === '0') open = false;
      if (stored === '1') open = true;
    } catch (e) { /* ignore */ }
    setDrawerOpen(open);
    if (toggle) {
      toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        setDrawerOpen(document.body.classList.contains('atak-drawer-collapsed'));
      });
    }
    if (head) {
      head.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('#atak-drawer-toggle')) return;
        setDrawerOpen(document.body.classList.contains('atak-drawer-collapsed'));
      });
      head.style.cursor = 'pointer';
      head.title = 'Afficher ou masquer le tableau des effectifs';
    }
  }

  function initCollapse() {
    var sideBtn = qs('atak-side-collapse');
    var railBtn = qs('atak-section-collapse');
    function toggle() {
      var panel = qs('atak-panel-left');
      if (!panel) return;
      setLeftCollapsed(!panel.classList.contains('collapsed'));
    }
    if (sideBtn) sideBtn.addEventListener('click', toggle);
    if (railBtn) railBtn.addEventListener('click', toggle);
  }

  function initSectionButtons() {
    document.querySelectorAll('.atak-section-btn[data-section]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var section = btn.getAttribute('data-section');
        var panel = qs('atak-panel-left');
        if (panel && panel.classList.contains('collapsed')) {
          setLeftCollapsed(false);
        }
        setSection(section);
      });
    });
  }

  function annotateTabs() {
    document.querySelectorAll('#atak-panel-left .atak-tab[data-tab]').forEach(function (tabBtn) {
      var tab = tabBtn.getAttribute('data-tab');
      var sec = TAB_TO_SECTION[tab];
      if (sec) tabBtn.setAttribute('data-atak-section', sec);
      tabBtn.classList.add('is-section-visible');
    });
  }

  function initialSection() {
    var active = document.querySelector('#atak-panel-left .atak-tab.active[data-tab]');
    if (active) {
      var fromActive = sectionForTab(active.getAttribute('data-tab'));
      if (fromActive) return fromActive;
    }
    try {
      var stored = localStorage.getItem(STORAGE_SECTION);
      if (stored && SECTIONS[stored]) return stored;
    } catch (e) { /* ignore */ }
    return 'intel';
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    if (window.ATAK_POPOUT === 'right') return;

    annotateTabs();
    initSectionButtons();
    initCollapse();
    initDrawer();

    var start = initialSection();
    setSection(start, { skipActivate: true });

    document.addEventListener('atak:tab-activated', function (ev) {
      var tab = ev && ev.detail && ev.detail.tab;
      if (tab) ensureSectionForTab(tab);
    });

    document.querySelectorAll('#atak-panel-left .atak-tab[data-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tab = btn.getAttribute('data-tab');
        ensureSectionForTab(tab);
      });
    });

    if (typeof MutationObserver !== 'undefined') {
      var mo = new MutationObserver(function () {
        syncSectionBadges();
        updateSideMeta(currentSection);
      });
      document.querySelectorAll('#atak-panel-left .atak-tab-badge, #atak-panel-left .atak-medical-tab-badge').forEach(function (el) {
        mo.observe(el, { attributes: true, childList: true, characterData: true, subtree: true });
      });
      window.setInterval(syncSectionBadges, 5000);
    }

    window.ATAKSectionNav = {
      setSection: setSection,
      sectionForTab: sectionForTab,
      syncBadges: syncSectionBadges,
      setDrawerOpen: setDrawerOpen,
      setLeftCollapsed: setLeftCollapsed
    };
  });
})();
