/**
 * ATAK — palette de commandes + raccourcis clavier (inspiré Athena C2).
 * Ctrl/Cmd+K. Ne touche pas au header produit.
 */
(function () {
  'use strict';

  var activeIndex = 0;
  var filtered = [];
  var open = false;

  function qs(id) {
    return document.getElementById(id);
  }

  function isTypingTarget(el) {
    if (!el) return false;
    var tag = (el.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
    if (el.isContentEditable) return true;
    return !!(el.closest && el.closest('[contenteditable="true"]'));
  }

  function openSection(section) {
    if (window.ATAKSectionNav && typeof window.ATAKSectionNav.setSection === 'function') {
      window.ATAKSectionNav.setSection(section);
      if (window.ATAKSectionNav.setLeftCollapsed) {
        window.ATAKSectionNav.setLeftCollapsed(false);
      }
      return;
    }
    var btn = document.querySelector('.atak-section-btn[data-section="' + section + '"]');
    if (btn) btn.click();
  }

  function openTab(tab) {
    if (window.ATAKPanelChrome && typeof window.ATAKPanelChrome.activateTab === 'function') {
      window.ATAKPanelChrome.activateTab(tab);
    } else {
      var btn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]');
      if (btn) btn.click();
    }
    var secMap = {
      markers: 'sitac', pings: 'sitac', identification: 'sitac', situation: 'sitac',
      medical: 'forces', etat: 'forces',
      cams: 'intel', photos: 'intel', personnes: 'intel',
      orders: 'c2', notes: 'c2', replay: 'c2',
      chat: 'comms', radio: 'comms', liaison: 'comms',
      jtac: 'support'
    };
    if (secMap[tab] && window.ATAKSectionNav) {
      window.ATAKSectionNav.setSection(secMap[tab], { skipActivate: true });
      window.ATAKSectionNav.setLeftCollapsed(false);
    }
  }

  function toggleLeft() {
    var panel = qs('atak-panel-left');
    if (!panel) return;
    var collapsed = !panel.classList.contains('collapsed');
    if (window.ATAKSectionNav && window.ATAKSectionNav.setLeftCollapsed) {
      window.ATAKSectionNav.setLeftCollapsed(collapsed);
    } else {
      panel.classList.toggle('collapsed', collapsed);
    }
  }

  function toggleRight() {
    var panel = qs('atak-panel-right');
    if (!panel) return;
    panel.classList.toggle('collapsed');
    window.setTimeout(function () {
      try { window.dispatchEvent(new Event('resize')); } catch (e) { /* ignore */ }
    }, 80);
  }

  function toggleDrawer() {
    var openNow = document.body.classList.contains('atak-drawer-collapsed');
    if (window.ATAKSectionNav && window.ATAKSectionNav.setDrawerOpen) {
      window.ATAKSectionNav.setDrawerOpen(openNow);
    } else {
      document.body.classList.toggle('atak-drawer-collapsed', !openNow);
    }
  }

  function toggleNvg() {
    var btn = document.querySelector('.atak-map-tools__btn[data-tool="nvg"]');
    if (btn) btn.click();
  }

  function openConfig() {
    var btn = qs('atak-btn-config') || qs('atak-btn-account');
    if (btn) btn.click();
  }

  function centerOnCallsign(cs) {
    var units = (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function')
      ? window.ATAKUnits.getUnits()
      : [];
    var needle = String(cs || '').toUpperCase();
    var hit = null;
    for (var i = 0; i < units.length; i++) {
      if (String(units[i].call_sign || '').toUpperCase() === needle) {
        hit = units[i];
        break;
      }
    }
    if (!hit || hit.pos_x == null || hit.pos_y == null) {
      if (window.ATAKShowError) window.ATAKShowError('Position introuvable pour ' + cs);
      return;
    }
    if (window.ATAKMap && window.ATAKMap.centerOn) {
      window.ATAKMap.centerOn(parseFloat(hit.pos_y), parseFloat(hit.pos_x));
    }
  }

  function baseCommands() {
    return [
      { id: 'sitac', icon: '⌖', title: 'Situation tactique', hint: 'Domaine SITAC', keys: 'S', run: function () { openSection('sitac'); } },
      { id: 'forces', icon: '♙', title: 'Forces & personnel', hint: 'Domaine Forces', keys: 'F', run: function () { openSection('forces'); } },
      { id: 'intel', icon: '◇', title: 'Renseignement', hint: 'Domaine Intel', keys: 'I', run: function () { openSection('intel'); } },
      { id: 'c2', icon: '▣', title: 'Commandement', hint: 'Domaine C2', keys: 'C', run: function () { openSection('c2'); } },
      { id: 'comms', icon: '≋', title: 'Communications', hint: 'Domaine Comms', keys: 'R', run: function () { openSection('comms'); } },
      { id: 'support', icon: '△', title: 'Appuis', hint: 'Domaine Appuis', keys: 'J', run: function () { openSection('support'); } },
      { id: 'markers', icon: '⌖', title: 'Marqueurs', hint: 'Module SITAC', run: function () { openTab('markers'); } },
      { id: 'pings', icon: '◎', title: 'Pings', hint: 'Contacts à traiter', run: function () { openTab('pings'); } },
      { id: 'medical', icon: '✚', title: 'Médical', hint: 'Triage et MEDEVAC', run: function () { openTab('medical'); } },
      { id: 'orders', icon: '☰', title: 'Ordres', hint: 'FRAGO / directives', run: function () { openTab('orders'); } },
      { id: 'chat', icon: '≋', title: 'Tchat', hint: 'Messagerie terrain', run: function () { openTab('chat'); } },
      { id: 'jtac', icon: '△', title: 'JTAC', hint: '9-Line / CAS', run: function () { openTab('jtac'); } },
      { id: 'toggle-left', icon: '‹', title: 'Panneau gauche', hint: 'Afficher ou masquer', keys: 'L', run: toggleLeft },
      { id: 'toggle-right', icon: '›', title: 'Panneau droit', hint: 'Effectifs', keys: 'D', run: toggleRight },
      { id: 'toggle-drawer', icon: '⌄', title: 'Tableau des effectifs', hint: 'Tiroir inférieur', keys: 'Espace', run: toggleDrawer },
      { id: 'nvg', icon: '◑', title: 'Vision nocturne', hint: 'Basculer NVG', keys: 'N', run: toggleNvg },
      { id: 'config', icon: '⚙', title: 'Configuration', hint: 'Réglages liaison', keys: ',', run: openConfig }
    ];
  }

  function unitCommands() {
    var units = (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function')
      ? window.ATAKUnits.getUnits()
      : [];
    return units.slice(0, 40).map(function (u) {
      var cs = String(u.call_sign || '').trim();
      if (!cs) return null;
      return {
        id: 'unit-' + cs,
        icon: '◎',
        title: cs,
        hint: 'Centrer la carte sur cet indicatif',
        run: function () { centerOnCallsign(cs); }
      };
    }).filter(Boolean);
  }

  function allCommands() {
    return baseCommands().concat(unitCommands());
  }

  function renderList(query) {
    var list = qs('atak-command-list');
    if (!list) return;
    var q = String(query || '').trim().toLowerCase();
    filtered = allCommands().filter(function (cmd) {
      if (!q) return true;
      return (cmd.title + ' ' + (cmd.hint || '') + ' ' + (cmd.keys || '')).toLowerCase().indexOf(q) !== -1;
    });
    activeIndex = 0;
    if (!filtered.length) {
      list.innerHTML = '<p class="atak-command-empty">Aucune commande correspondante.</p>';
      return;
    }
    list.innerHTML = filtered.map(function (cmd, i) {
      return '<button type="button" class="atak-command-item' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '">' +
        '<span class="atak-command-item-icon" aria-hidden="true">' + (cmd.icon || '·') + '</span>' +
        '<span class="atak-command-item-main"><strong>' + escapeHtml(cmd.title) + '</strong>' +
        '<small>' + escapeHtml(cmd.hint || '') + '</small></span>' +
        (cmd.keys ? '<kbd>' + escapeHtml(cmd.keys) + '</kbd>' : '') +
        '</button>';
    }).join('');
    list.querySelectorAll('.atak-command-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        runIndex(parseInt(btn.getAttribute('data-idx'), 10));
      });
    });
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setActive(i) {
    if (!filtered.length) return;
    activeIndex = (i + filtered.length) % filtered.length;
    var items = document.querySelectorAll('#atak-command-list .atak-command-item');
    items.forEach(function (el, idx) {
      el.classList.toggle('active', idx === activeIndex);
      if (idx === activeIndex && el.scrollIntoView) {
        el.scrollIntoView({ block: 'nearest' });
      }
    });
  }

  function runIndex(i) {
    var cmd = filtered[i];
    closePalette();
    if (cmd && typeof cmd.run === 'function') {
      try { cmd.run(); } catch (e) { /* ignore */ }
    }
  }

  function openPalette() {
    var root = qs('atak-command-palette');
    var input = qs('atak-command-input');
    if (!root) return;
    open = true;
    root.classList.add('show');
    root.setAttribute('aria-hidden', 'false');
    renderList('');
    if (input) {
      input.value = '';
      window.setTimeout(function () { input.focus(); }, 20);
    }
  }

  function closePalette() {
    var root = qs('atak-command-palette');
    if (!root) return;
    open = false;
    root.classList.remove('show');
    root.setAttribute('aria-hidden', 'true');
  }

  function onGlobalKey(e) {
    var metaK = (e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey);
    if (metaK) {
      e.preventDefault();
      if (open) closePalette();
      else openPalette();
      return;
    }

    if (open) {
      if (e.key === 'Escape') {
        e.preventDefault();
        closePalette();
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setActive(activeIndex + 1);
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        setActive(activeIndex - 1);
        return;
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        runIndex(activeIndex);
        return;
      }
      return;
    }

    if (isTypingTarget(e.target)) return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;

    var map = {
      s: function () { openSection('sitac'); },
      f: function () { openSection('forces'); },
      i: function () { openSection('intel'); },
      c: function () { openSection('c2'); },
      r: function () { openSection('comms'); },
      j: function () { openSection('support'); },
      l: toggleLeft,
      d: toggleRight,
      n: toggleNvg,
      ',': openConfig,
      ' ': toggleDrawer
    };
    var fn = map[e.key.toLowerCase()] || map[e.key];
    if (fn) {
      e.preventDefault();
      fn();
    }
  }

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    if (window.ATAK_POPOUT) return;
    var input = qs('atak-command-input');
    var backdrop = qs('atak-command-palette');
    var openBtn = qs('atak-command-open');
    if (input) {
      input.addEventListener('input', function () {
        renderList(input.value);
      });
    }
    if (backdrop) {
      backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) closePalette();
      });
    }
    if (openBtn) openBtn.addEventListener('click', openPalette);
    document.addEventListener('keydown', onGlobalKey);

    window.ATAKCommandPalette = {
      open: openPalette,
      close: closePalette,
      isOpen: function () { return open; }
    };
  });
})();
