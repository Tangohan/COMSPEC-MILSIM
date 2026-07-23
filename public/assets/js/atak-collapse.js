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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { bind(document); });
  } else {
    bind(document);
  }

  return {
    bind: bind,
    apply: apply,
    isOpen: isOpen,
    setOpen: setOpen
  };
})();
