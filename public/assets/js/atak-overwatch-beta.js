(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA) return;

  var VIEW_KEY = 'athena:overwatch-basemap';
  function click(selector) { var node = document.querySelector(selector); if (node) node.click(); }
  function select(section, tab) {
    click('.atak-section-btn[data-section="' + section + '"]');
    if (tab) window.setTimeout(function () { click('.atak-tab[data-tab="' + tab + '"]'); }, 20);
  }
  function readView() { try { return localStorage.getItem(VIEW_KEY) || 'classic'; } catch (e) { return 'classic'; } }
  function setView(value) {
    var map = document.getElementById('atak-map');
    if (!map) return;
    ['classic', 'aerial', 'mono'].forEach(function (name) { map.classList.toggle('atak-overwatch-map--' + name, name === value); });
    try { localStorage.setItem(VIEW_KEY, value); } catch (e) {}
    document.querySelectorAll('[data-overwatch-basemap]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.overwatchBasemap === value);
      button.setAttribute('aria-pressed', button.dataset.overwatchBasemap === value ? 'true' : 'false');
    });
  }
  function addNavigation() {
    var bar = document.getElementById('atak-v2-layoutbar');
    if (!bar || document.getElementById('atak-overwatch-navigation')) return;
    var nav = document.createElement('nav');
    nav.id = 'atak-overwatch-navigation'; nav.className = 'atak-overwatch-navigation'; nav.setAttribute('aria-label', 'Workspace Overwatch');
    nav.innerHTML = '<button data-workspace="overwatch" class="is-active">OVERWATCH</button><button data-workspace="comms">COMMS</button><button data-workspace="mission">MISSION</button><button data-workspace="layers">LAYERS</button><button data-workspace="intel">INTEL</button><button data-workspace="tools">TOOLS</button>';
    bar.prepend(nav);
    nav.addEventListener('click', function (event) {
      var button = event.target.closest('[data-workspace]'); if (!button) return;
      nav.querySelectorAll('button').forEach(function (item) { item.classList.toggle('is-active', item === button); });
      var workspace = button.dataset.workspace;
      if (workspace === 'overwatch') click('[data-atak-layout="command"]');
      if (workspace === 'comms') select('comms', 'chat');
      if (workspace === 'mission') select('c2', 'mission');
      if (workspace === 'layers') { click('.js-atak-settings-toggle'); window.setTimeout(injectBasemaps, 30); }
      if (workspace === 'intel') select('intel', 'photos');
      if (workspace === 'tools') { click('[data-atak-layout="map"]'); var tools = document.getElementById('atak-map-tools'); if (tools) tools.hidden = false; }
    });
  }
  function injectBasemaps() {
    var aside = document.getElementById('atak-settings-aside');
    if (!aside || document.getElementById('atak-overwatch-basemaps')) return;
    var box = document.createElement('section'); box.id = 'atak-overwatch-basemaps'; box.className = 'atak-overwatch-basemaps';
    box.innerHTML = '<h4>FOND DE CARTE · ALTIS</h4><p>Rendu du fond cartographique actif, sans modifier les symboles BFT.</p><div role="group" aria-label="Fond de carte Altis"><button data-overwatch-basemap="classic">Classique</button><button data-overwatch-basemap="aerial">Aerial</button><button data-overwatch-basemap="mono">Noir et blanc</button></div>';
    var body = aside.querySelector('.atak-settings-aside__body') || aside; body.prepend(box);
    box.addEventListener('click', function (event) { var button = event.target.closest('[data-overwatch-basemap]'); if (button) setView(button.dataset.overwatchBasemap); });
    setView(readView());
  }
  function extendPaletteAndContext() {
    var palette = document.getElementById('atak-command-palette');
    if (palette) palette.setAttribute('data-live-services', 'ATAKMap ATAKChat ATAKReplay ATAKSitrep');
    // Le menu contextuel ATAK natif reste la source d'autorité (coordonnées, SITREP,
    // routes et marqueurs). On évite volontairement tout résultat simulé ici.
    document.documentElement.dataset.overwatchConnected = 'true';
  }
  function init() { addNavigation(); injectBasemaps(); setView(readView()); extendPaletteAndContext(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
