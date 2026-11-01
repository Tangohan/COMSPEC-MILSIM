/* COMSPEC ATAK — repli / dépli des blocs latéraux (sessionStorage) */
window.ATAKCollapse = (function () {
  var STORAGE_KEY = 'atak_sidebar_collapse_v1';

  function loadMap() {
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return {};
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function saveMap(map) {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(map || {}));
    } catch (e) {}
  }

  function defaultOpenOf(el) {
    var def = el.getAttribute('data-atak-collapse-default');
    if (def === null || def === '') {
      return el.hasAttribute('open');
    }
    return def === '1' || def === 'true';
  }

  function isOpen(key, fallback) {
    var map = loadMap();
    if (Object.prototype.hasOwnProperty.call(map, key)) return !!map[key];
    return !!fallback;
  }

  function setOpen(key, open) {
    if (!key) return;
    var map = loadMap();
    map[key] = !!open;
    saveMap(map);
  }

  function bindOne(el) {
    if (!el || el.nodeName !== 'DETAILS') return;
    var key = el.getAttribute('data-atak-collapse');
    if (!key) return;
    if (el._atakCollapseBound) {
      el.open = isOpen(key, defaultOpenOf(el));
      return;
    }
    el._atakCollapseBound = true;
    el.open = isOpen(key, defaultOpenOf(el));
    el.addEventListener('toggle', function () {
      setOpen(key, el.open);
    });
  }

  function bind(root) {
    root = root || document;
    if (!root.querySelectorAll) return;
    root.querySelectorAll('details[data-atak-collapse]').forEach(bindOne);
  }

  function apply(el, key, defaultOpen) {
    if (!el || el.nodeName !== 'DETAILS') return;
    if (key) el.setAttribute('data-atak-collapse', key);
    if (defaultOpen != null) {
      el.setAttribute('data-atak-collapse-default', defaultOpen ? '1' : '0');
    }
    bindOne(el);
  }

  /*
   * Carte plein écran (mode tablette) : replie l'en-tête et les panneaux (classe atak-map-focus, voir atak.css)
   * et passe le navigateur en plein écran quand il le permet. Pas mémorisé : on revient toujours à l'écran normal.
   */
  var browserFullscreen = false;

  function setMapFocus(on) {
    on = !!on;
    document.body.classList.toggle('atak-map-focus', on);
    document.querySelectorAll('[data-atak-map-focus]').forEach(function (btn) {
      if (!btn.classList.contains('atak-map-focus-exit')) btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    try {
      var root = document.documentElement;
      if (on && !document.fullscreenElement && root.requestFullscreen) {
        root.requestFullscreen().then(function () { browserFullscreen = true; }).catch(function () {});
      } else if (!on && browserFullscreen && document.fullscreenElement && document.exitFullscreen) {
        document.exitFullscreen().catch(function () {});
      }
    } catch (e) {}
    if (!on) browserFullscreen = false;
    // La carte suit sa nouvelle taille (ResizeObserver / resize de atak-map.js).
    try { window.dispatchEvent(new Event('resize')); } catch (e2) {}
  }

  function bindMapFocus() {
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-atak-map-focus]') : null;
      if (!btn) return;
      e.preventDefault();
      setMapFocus(!document.body.classList.contains('atak-map-focus'));
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('atak-map-focus') && !document.fullscreenElement) setMapFocus(false);
    });
    // Sortie du plein écran par le navigateur (Échap, geste retour) : on rouvre aussi les barres.
    document.addEventListener('fullscreenchange', function () {
      if (!document.fullscreenElement && browserFullscreen && document.body.classList.contains('atak-map-focus')) setMapFocus(false);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { bind(document); });
  } else {
    bind(document);
  }
  bindMapFocus();

  return {
    bind: bind,
    setMapFocus: setMapFocus,
    apply: apply,
    isOpen: isOpen,
    setOpen: setOpen
  };
})();
