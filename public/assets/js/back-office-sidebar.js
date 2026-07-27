/**
 * Back-office ATHENA — sidebar repliable + groupes de navigation.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var sidebar = document.getElementById('ath-sidebar');
    var aside = document.getElementById('back-office-sidebar');
    if (!sidebar) return;

    var storageKey = 'ath-bo-sidebar-collapsed';
    var groupsKey = 'ath-bo-nav-groups-v3';

    function loadCollapsed() {
      try {
        return localStorage.getItem(storageKey) === '1';
      } catch (e) {
        return false;
      }
    }

    function saveCollapsed(val) {
      try {
        localStorage.setItem(storageKey, val ? '1' : '0');
      } catch (e) { /* ignore */ }
    }

    function loadGroups() {
      try {
        var raw = localStorage.getItem(groupsKey);
        return raw ? JSON.parse(raw) : {};
      } catch (e) {
        return {};
      }
    }

    function saveGroups(state) {
      try {
        localStorage.setItem(groupsKey, JSON.stringify(state));
      } catch (e) { /* ignore */ }
    }

    var collapsed = loadCollapsed();
    var groupState = loadGroups();

    function updateToggleUi() {
      var toggleBtn = sidebar.querySelector('[data-ath-sidebar-toggle]');
      if (!toggleBtn) return;
      var label = collapsed ? 'Déplier le menu' : 'Plier le menu';
      toggleBtn.setAttribute('title', label);
      toggleBtn.setAttribute('aria-label', label);
      toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    function applyCollapsed() {
      sidebar.classList.toggle('is-collapsed', collapsed);
      if (aside) {
        aside.classList.toggle('is-collapsed', collapsed);
      }
      updateToggleUi();
    }

    function setCollapsed(next) {
      collapsed = !!next;
      saveCollapsed(collapsed);
      applyCollapsed();
    }

    applyCollapsed();

    var toggleBtn = sidebar.querySelector('[data-ath-sidebar-toggle]');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setCollapsed(!collapsed);
      });
    }

    var logo = sidebar.querySelector('.ath-sidebar__logo');
    if (logo) {
      logo.setAttribute('role', 'button');
      logo.setAttribute('tabindex', '0');
      logo.setAttribute('title', 'Déplier le menu');
      logo.addEventListener('click', function () {
        if (collapsed) {
          setCollapsed(false);
        }
      });
      logo.addEventListener('keydown', function (e) {
        if (!collapsed) return;
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          setCollapsed(false);
        }
      });
    }

    var groups = sidebar.querySelectorAll('[data-ath-nav-group]');
    groups.forEach(function (group) {
      var key = group.getAttribute('data-ath-nav-group') || '';
      var stored = groupState[key];
      var open = stored !== false;

      function setOpen(on) {
        group.classList.toggle('is-open', on);
        groupState[key] = on;
        saveGroups(groupState);
        var head = group.querySelector('[data-ath-group-toggle]');
        if (head) head.setAttribute('aria-expanded', on ? 'true' : 'false');
      }

      setOpen(open);

      var toggle = group.querySelector('[data-ath-group-toggle]');
      if (toggle) {
        toggle.addEventListener('click', function () {
          if (collapsed) return;
          setOpen(!group.classList.contains('is-open'));
        });
      }
    });

    /* Filtrage menu via recherche topbar (maquette : recherche hors sidebar) */
    var menuSearch = document.getElementById('ath-menu-search');
    var topSearch = document.getElementById('ath-top-search');
    var nav = document.getElementById('ath-sidebar-nav');

    if (topSearch && nav && !menuSearch) {
      function normalize(s) {
        return String(s || '')
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/\s+/g, ' ')
          .trim();
      }

      function applyNavFilter() {
        var q = normalize(topSearch.value);
        var items = nav.querySelectorAll('[data-ath-nav-item]');
        items.forEach(function (el) {
          var hay = normalize(el.getAttribute('data-ath-search') || '');
          var ok = q === '' || hay.indexOf(q) !== -1 || q.split(' ').every(function (tok) {
            return tok === '' || hay.indexOf(tok) !== -1;
          });
          el.hidden = !ok;
        });

        nav.querySelectorAll('[data-ath-nav-group]').forEach(function (group) {
          var any = false;
          group.querySelectorAll('[data-ath-nav-item]').forEach(function (el) {
            if (!el.hidden) any = true;
          });
          group.hidden = q !== '' && !any;
          if (q !== '' && any) {
            group.classList.add('is-open');
            var head = group.querySelector('[data-ath-group-toggle]');
            if (head) head.setAttribute('aria-expanded', 'true');
          }
        });
      }

      document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
          e.preventDefault();
          topSearch.focus();
          topSearch.select();
        }
      });
      topSearch.addEventListener('input', applyNavFilter);
      topSearch.addEventListener('search', applyNavFilter);
    }
  });
})();
