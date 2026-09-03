(function () {
  'use strict';

  var STORAGE_KEY = 'athena:atak-theme';
  var select = document.getElementById('atak-theme-select');
  if (!select) return;

  function applyTheme(theme, persist) {
    var normalized = theme === 'day' ? 'day' : 'night';
    document.documentElement.dataset.atakTheme = normalized;
    document.body.classList.toggle('atak-theme-light', normalized === 'day');
    document.body.classList.toggle('atak-theme-dark', normalized === 'night');
    select.value = normalized;
    select.setAttribute('aria-label', 'Thème d’affichage : ' + (normalized === 'day' ? 'Jour' : 'Nuit'));

    var themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) themeColor.content = normalized === 'day' ? '#f5f5fe' : '#101827';

    if (persist) {
      try { localStorage.setItem(STORAGE_KEY, normalized); } catch (e) {}
    }
  }

  applyTheme(document.documentElement.dataset.atakTheme, false);
  select.addEventListener('change', function () {
    applyTheme(select.value, true);
  });
})();
