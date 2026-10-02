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
    var aside = document.getElementById('back-office-sidebar')
      || document.getElementById('platform-admin-sidebar');
    if (!sidebar) return;

    var isPlatform = aside && aside.id === 'platform-admin-sidebar';
    var storageKey = isPlatform ? 'ath-platform-sidebar-collapsed' : 'ath-bo-sidebar-collapsed';
    var groupsKey = isPlatform ? 'ath-platform-nav-groups-v1' : 'ath-bo-nav-groups-v3';

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
    if (logo && logo.tagName !== 'A') {
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
      // Un groupe qui contient la page courante reste toujours ouvert.
      var hasCurrent = !!group.querySelector('.is-active');
      var open = hasCurrent || stored !== false;

      function setOpen(on, persist) {
        group.classList.toggle('is-open', on);
        if (persist) {
          groupState[key] = on;
          saveGroups(groupState);
        }
        var head = group.querySelector('[data-ath-group-toggle]');
        if (head) head.setAttribute('aria-expanded', on ? 'true' : 'false');
      }

      setOpen(open, false);
      group._athSetOpen = setOpen;

      var toggle = group.querySelector('[data-ath-group-toggle]');
      if (toggle) {
        toggle.addEventListener('click', function () {
          if (collapsed) return;
          setOpen(!group.classList.contains('is-open'), true);
        });
      }
    });

    /* Sous-pages : chevron pour déplier sans quitter la page courante */
    function setKids(block, on) {
      var kids = block.querySelector('.ath-sidebar__children');
      var btn = block.querySelector('[data-ath-kids-toggle]');
      if (!kids) return;
      kids.hidden = !on;
      block.classList.toggle('is-expanded', on);
      if (btn) btn.setAttribute('aria-expanded', on ? 'true' : 'false');
    }

    sidebar.querySelectorAll('[data-ath-kids-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var block = btn.closest('.ath-sidebar__nav-block');
        if (!block) return;
        setKids(block, !block.classList.contains('is-expanded'));
      });
    });

    /* Amener la page courante dans la zone visible du menu */
    var navScroller = document.getElementById('ath-sidebar-nav');
    var current = sidebar.querySelector('.ath-sidebar__child.is-active') || sidebar.querySelector('.ath-sidebar__item.is-active');
    if (navScroller && current && typeof current.getBoundingClientRect === 'function') {
      var nr = navScroller.getBoundingClientRect();
      var cr = current.getBoundingClientRect();
      if (cr.top < nr.top || cr.bottom > nr.bottom) {
        navScroller.scrollTop += (cr.top - nr.top) - nr.height / 3;
      }
    }

    /* Filtrage menu via recherche topbar (maquette : recherche hors sidebar) */
    var menuSearch = document.getElementById('ath-menu-search');
    var topSearch = document.getElementById('ath-top-search');
    var nav = document.getElementById('ath-sidebar-nav');

    var filterInput = menuSearch || (topSearch && !topSearch.readOnly ? topSearch : null);
    if (filterInput && nav) {
      var topSearchRef = filterInput;
      function normalize(s) {
        return String(s || '')
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/\s+/g, ' ')
          .trim();
      }

      function applyNavFilter() {
        var q = normalize(topSearchRef.value);
        var tokens = q.split(' ').filter(function (t) { return t !== ''; });
        function matches(text) {
          var hay = normalize(text);
          return tokens.every(function (tok) { return hay.indexOf(tok) !== -1; });
        }

        nav.querySelectorAll('[data-ath-nav-item]').forEach(function (el) {
          var block = el.querySelector('.ath-sidebar__nav-block');
          var kids = el.querySelectorAll('[data-ath-child-search]');
          if (q === '') {
            el.hidden = false;
            kids.forEach(function (k) { k.hidden = false; });
            if (block && block._athKidsBefore !== undefined) {
              setKids(block, block._athKidsBefore);
              delete block._athKidsBefore;
            }
            return;
          }
          var label = el.querySelector('.ath-sidebar__item-label');
          var selfOk = matches(label ? label.textContent : '');
          var anyKid = false;
          kids.forEach(function (k) {
            var ok = selfOk || matches(k.getAttribute('data-ath-child-search') || '');
            k.hidden = !ok;
            if (ok && !selfOk) anyKid = true;
          });
          el.hidden = !selfOk && !anyKid;
          if (block && kids.length) {
            if (block._athKidsBefore === undefined) {
              block._athKidsBefore = block.classList.contains('is-expanded');
            }
            setKids(block, anyKid || block._athKidsBefore);
          }
        });

        nav.querySelectorAll('[data-ath-nav-group]').forEach(function (group) {
          var any = false;
          group.querySelectorAll('[data-ath-nav-item]').forEach(function (el) {
            if (!el.hidden) any = true;
          });
          group.hidden = q !== '' && !any;
          if (q !== '') {
            if (any && group._athSetOpen) group._athSetOpen(true, false);
          } else if (group._athSetOpen) {
            // Recherche effacée : revenir à l’état choisi par l’utilisateur.
            var key = group.getAttribute('data-ath-nav-group') || '';
            group._athSetOpen(!!group.querySelector('.is-active') || groupState[key] !== false, false);
          }
        });
      }

      topSearchRef.addEventListener('input', applyNavFilter);
      topSearchRef.addEventListener('search', applyNavFilter);
      topSearchRef.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          topSearchRef.value = '';
          applyNavFilter();
        } else if (e.key === 'Enter') {
          var first = nav.querySelector('[data-ath-nav-item]:not([hidden]) .ath-sidebar__child:not([hidden]), [data-ath-nav-item]:not([hidden]) .ath-sidebar__item');
          if (first && first.href) {
            e.preventDefault();
            window.location.href = first.href;
          }
        }
      });
    }
  });
})();
