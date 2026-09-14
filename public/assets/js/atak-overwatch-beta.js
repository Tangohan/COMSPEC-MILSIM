(function () {
  'use strict';

  var commandbar = document.getElementById('overwatch-commandbar');
  if (!commandbar) return;

  // The Overwatch chrome depends on the production V2 layout/palette runtime.
  // Activating it through the existing switch keeps one source of truth.
  var v2Switch = document.querySelector('[data-atak-version="v2"]');
  if (v2Switch && !document.body.classList.contains('atak-ui-v2')) v2Switch.click();

  function setActive(button) {
    commandbar.querySelectorAll('.overwatch-commandbar__nav button').forEach(function (item) {
      item.classList.toggle('is-active', item === button);
    });
  }

  function openTab(name, source) {
    var tab = document.querySelector('.atak-tab[data-tab="' + name + '"]');
    if (!tab) return;
    tab.click();
    var left = document.getElementById('atak-panel-left');
    if (left) left.classList.remove('is-collapsed');
    setActive(source);
  }

  commandbar.querySelectorAll('[data-overwatch-tab]').forEach(function (button) {
    button.addEventListener('click', function () { openTab(button.dataset.overwatchTab, button); });
  });

  var home = commandbar.querySelector('[data-overwatch-home]');
  home.addEventListener('click', function () {
    var mapLayout = document.querySelector('[data-atak-layout="map"]');
    if (mapLayout) mapLayout.click();
    setActive(home);
  });

  commandbar.querySelector('[data-overwatch-settings]').addEventListener('click', function () {
    var settings = document.querySelector('.js-atak-settings-toggle');
    if (settings) settings.click();
    setActive(this);
  });

  commandbar.querySelector('[data-overwatch-tools]').addEventListener('click', function () {
    var tools = document.getElementById('atak-map-tools');
    var toolsFab = document.getElementById('atak-map-tools-fab');
    if (tools && tools.hidden && toolsFab) toolsFab.click();
    var customize = document.querySelector('[data-tool-ui="customize"]');
    if (customize) customize.click();
    setActive(this);
  });

  commandbar.querySelector('[data-overwatch-command]').addEventListener('click', function () {
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', code: 'KeyK', ctrlKey: true, bubbles: true }));
  });

  var source = document.getElementById('atak-status');
  var label = document.getElementById('overwatch-link-label');
  function syncLinkState() {
    if (!source || !label) return;
    var online = source.classList.contains('atak-chip--live') || source.textContent.toLowerCase().indexOf('actif') !== -1;
    label.textContent = online ? 'LINKED' : 'DEGRADED';
    commandbar.classList.toggle('is-degraded', !online);
  }
  syncLinkState();
  if (source && window.MutationObserver) new MutationObserver(syncLinkState).observe(source, { attributes: true, childList: true, subtree: true });
})();
