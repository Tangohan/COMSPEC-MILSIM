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

  var watchPanel = document.getElementById('overwatch-watchlist');
  var watchItems = document.getElementById('overwatch-watchlist-items');
  var watchButton = commandbar.querySelector('[data-overwatch-watchlist]');
  var watchKey = 'athena:overwatch-watchlist:' + String(window.ATAK_TENANT_ID || 'guest');
  var watched = [];
  try { watched = JSON.parse(localStorage.getItem(watchKey) || '[]'); } catch (ignore) {}
  if (!Array.isArray(watched)) watched = [];

  function saveWatchlist() {
    try { localStorage.setItem(watchKey, JSON.stringify(watched)); } catch (ignore) {}
  }

  function renderWatchlist() {
    if (!watchItems) return;
    watchItems.textContent = '';
    if (!watched.length) {
      watchItems.innerHTML = '<div class="overwatch-watchlist__empty">Aucun contact suivi.<br>Utilisez l’étoile dans les effectifs.</div>';
      return;
    }
    watched.forEach(function (callsign) {
      var row = document.querySelector('#atak-units-table-body [data-callsign="' + CSS.escape(callsign) + '"]');
      var item = document.createElement('button');
      item.type = 'button'; item.className = 'overwatch-watchlist__item';
      var grid = row ? (row.dataset.grid || 'POSITION EN ATTENTE') : 'DERNIÈRE LIAISON INDISPONIBLE';
      var statusNode = row ? row.querySelector('.atak-unit-status') : null;
      var status = statusNode ? statusNode.textContent.trim() : 'OFFLINE';
      item.innerHTML = '<strong></strong><small></small><span class="overwatch-watchlist__state"></span>';
      item.querySelector('strong').textContent = callsign;
      item.querySelector('small').textContent = grid;
      item.querySelector('span').textContent = status;
      item.querySelector('span').classList.toggle('is-offline', !row || /offline|hors/i.test(status));
      item.addEventListener('click', function () { if (row) row.click(); });
      watchItems.appendChild(item);
    });
  }

  function toggleWatched(callsign) {
    var index = watched.indexOf(callsign);
    if (index === -1) watched.push(callsign); else watched.splice(index, 1);
    saveWatchlist(); decorateRoster(); renderWatchlist();
  }

  function decorateRoster() {
    document.querySelectorAll('#atak-units-table-body .atak-drawer-row[data-callsign]').forEach(function (row) {
      var callsign = row.dataset.callsign;
      var actions = row.querySelector('.atak-drawer-actions');
      if (!callsign || !actions) return;
      var button = actions.querySelector('[data-overwatch-watch]');
      if (!button) {
        button = document.createElement('button'); button.type = 'button';
        button.className = 'overwatch-watch-toggle'; button.dataset.overwatchWatch = '';
        button.addEventListener('click', function (event) { event.stopPropagation(); toggleWatched(callsign); });
        actions.prepend(button);
      }
      var active = watched.indexOf(callsign) !== -1;
      button.classList.toggle('is-watched', active); button.textContent = active ? '★' : '☆';
      button.title = active ? 'Retirer de la watchlist' : 'Suivre ce contact';
      button.setAttribute('aria-label', button.title); button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  if (watchButton && watchPanel) watchButton.addEventListener('click', function () {
    watchPanel.hidden = !watchPanel.hidden; renderWatchlist(); setActive(watchButton);
  });
  var watchClose = watchPanel && watchPanel.querySelector('[data-overwatch-watchlist-close]');
  if (watchClose) watchClose.addEventListener('click', function () { watchPanel.hidden = true; });
  var roster = document.getElementById('atak-units-table-body');
  if (roster && window.MutationObserver) new MutationObserver(function () { decorateRoster(); renderWatchlist(); }).observe(roster, { childList: true });
  decorateRoster(); renderWatchlist();

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
