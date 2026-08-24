(function () {
  'use strict';

  var VERSION_KEY = 'atak:uiVersion';
  var LAYOUT_KEY = 'atak:layout';
  var layouts = {
    command: { left: 280, right: 340, section: 'c2' },
    intel: { left: 420, right: 0, section: 'intel' },
    jtac: { left: 300, right: 380, section: 'jtac' },
    bft: { left: 300, right: 360, section: 'forces' },
    map: { left: 0, right: 0, section: null }
  };
  var commands = [
    ['Créer un marqueur', 'M', function () { click('[data-tool="note"]'); }],
    ['Créer une 9-Line', 'JTAC', function () { selectSection('jtac'); }],
    ['Localiser une unité', 'BFT', function () { focus('#atak-units-filter'); }],
    ['Ouvrir le renseignement SSE', 'Intel', function () { selectSection('intel'); }],
    ['Ouvrir les photos', 'Intel', function () { selectSection('intel'); click('[data-tab="photos"]'); }],
    ['Mesurer une distance', 'R', function () { click('[data-tool="measure"]'); }],
    ['Afficher les couches', 'L', function () { click('[data-tool-ui="look"]'); }],
    ['Masquer les effectifs', 'Vue', function () { setPanel('right', 0); }],
    ['Passer en carte plein écran', 'Vue', function () { applyLayout('map'); }]
  ];

  function read(key, fallback) { try { return localStorage.getItem(key) || fallback; } catch (e) { return fallback; } }
  function write(key, value) { try { localStorage.setItem(key, value); } catch (e) {} }
  function click(selector) { var el = document.querySelector(selector); if (el) el.click(); }
  function focus(selector) { var el = document.querySelector(selector); if (el) { el.focus(); el.select && el.select(); } }
  function selectSection(section) { click('.atak-section-btn[data-section="' + section + '"]'); }
  function invalidateMap() { window.setTimeout(function () { if (window.ATAKMap && window.ATAKMap.invalidateSize) window.ATAKMap.invalidateSize(); window.dispatchEvent(new Event('resize')); }, 80); }

  function setPanel(side, width) {
    var panel = document.getElementById('atak-panel-' + side);
    var value = Math.max(0, Math.min(520, Number(width) || 0));
    if (!panel) return;
    document.body.style.setProperty(side === 'left' ? '--atak-v2-left' : '--atak-v2-right', value + 'px');
    panel.classList.toggle('collapsed', value === 0);
    write('atak:' + side + 'Width', String(value));
    write('atak:' + side + 'Collapsed', value === 0 ? '1' : '0');
  }

  function applyLayout(name, persist) {
    var layout = layouts[name] || layouts.command;
    document.body.classList.toggle('atak-v2-full-map', name === 'map');
    setPanel('left', layout.left);
    setPanel('right', layout.right);
    if (layout.section) selectSection(layout.section);
    document.querySelectorAll('[data-atak-layout]').forEach(function (button) { button.classList.toggle('is-active', button.dataset.atakLayout === name); });
    if (persist !== false) write(LAYOUT_KEY, name);
    invalidateMap();
  }

  function setVersion(version) {
    var v2 = version === 'v2';
    document.documentElement.classList.toggle('atak-v2-boot', v2);
    document.body.classList.toggle('atak-ui-v2', v2);
    document.querySelectorAll('[data-atak-version]').forEach(function (button) { button.classList.toggle('is-active', button.dataset.atakVersion === version); });
    write(VERSION_KEY, version);
    if (v2) applyLayout(read(LAYOUT_KEY, 'command'), false);
    else document.body.classList.remove('atak-v2-full-map');
    invalidateMap();
  }

  function createPalette() {
    var root = document.createElement('div');
    root.id = 'atak-command-palette'; root.className = 'atak-v2-palette'; root.hidden = true;
    root.innerHTML = '<div class="atak-v2-palette__dialog" role="dialog" aria-modal="true" aria-label="Palette de commandes"><input type="search" placeholder="Commande, unité, personne, position…" autocomplete="off"><div class="atak-v2-palette__results"></div></div>';
    document.body.appendChild(root);
    var input = root.querySelector('input'); var results = root.querySelector('.atak-v2-palette__results');
    function render(query) {
      var q = String(query || '').toLowerCase(); results.textContent = '';
      commands.filter(function (item) { return item[0].toLowerCase().indexOf(q) !== -1; }).forEach(function (item, index) {
        var button = document.createElement('button'); button.type = 'button'; button.className = 'atak-v2-command' + (index === 0 ? ' is-selected' : '');
        button.innerHTML = '<span></span><small></small>'; button.firstChild.textContent = item[0]; button.lastChild.textContent = item[1];
        button.addEventListener('click', function () { close(); item[2](); }); results.appendChild(button);
      });
    }
    function open() { if (!document.body.classList.contains('atak-ui-v2')) return; root.hidden = false; input.value = ''; render(''); window.setTimeout(function () { input.focus(); }, 0); }
    function close() { root.hidden = true; }
    input.addEventListener('input', function () { render(input.value); });
    input.addEventListener('keydown', function (event) { if (event.key === 'Enter') { var selected = results.querySelector('.atak-v2-command'); if (selected) selected.click(); } });
    root.addEventListener('mousedown', function (event) { if (event.target === root) close(); });
    return { open: open, close: close, visible: function () { return !root.hidden; } };
  }

  function createContextMenu() {
    var root = document.createElement('div'); root.className = 'atak-v2-context'; root.hidden = true;
    root.innerHTML = '<div class="atak-v2-context__title">Ajouter</div><button data-action="marker">Marqueur / point d’intérêt</button><button data-action="zone">Zone tactique</button><div class="atak-v2-context__title">Renseignement</div><button data-action="intel">Créer une observation</button><button data-action="sse">Ajouter au dossier SSE</button><div class="atak-v2-context__title">Outils</div><button data-action="measure">Mesurer depuis ce point</button><button data-action="copy">Copier les coordonnées</button>';
    document.body.appendChild(root);
    var map = document.getElementById('atak-map');
    if (map) map.addEventListener('contextmenu', function (event) { if (!document.body.classList.contains('atak-ui-v2')) return; event.preventDefault(); root.hidden = false; root.style.left = Math.min(event.clientX, innerWidth - 266) + 'px'; root.style.top = Math.min(event.clientY, innerHeight - 330) + 'px'; });
    root.addEventListener('click', function (event) { var action = event.target.dataset.action; root.hidden = true; if (action === 'measure') click('[data-tool="measure"]'); else if (action === 'zone') click('[data-tool="perimeter"]'); else if (action === 'marker') click('[data-tool="note"]'); else if (action === 'intel' || action === 'sse') selectSection('intel'); else if (action === 'copy') document.execCommand && document.execCommand('copy'); });
    document.addEventListener('click', function (event) { if (!root.contains(event.target)) root.hidden = true; });
  }

  function createReplay() {
    var wrap = document.querySelector('.atak-map-wrap'); if (!wrap) return;
    var replay = document.createElement('div'); replay.className = 'atak-v2-replay'; replay.hidden = true;
    replay.innerHTML = '<div class="atak-v2-replay__head"><span class="atak-v2-replay__state">Mission replay</span><button type="button">Retour live</button></div><input type="range" min="0" max="100" value="100" aria-label="Temps de mission">';
    wrap.appendChild(replay); var range = replay.querySelector('input'); var state = replay.querySelector('span');
    function sync() { var live = range.value === '100'; replay.classList.toggle('is-replay', !live); state.textContent = live ? 'Live · situation actuelle' : 'Replay · T−' + (100 - Number(range.value)) + ' min'; }
    range.addEventListener('input', sync); replay.querySelector('button').addEventListener('click', function () { range.value = '100'; sync(); });
    window.ATAKV2Replay = { toggle: function () { replay.hidden = !replay.hidden; } };
  }

  function init() {
    var palette = createPalette(); createContextMenu(); createReplay();
    document.querySelectorAll('[data-atak-version]').forEach(function (button) { button.addEventListener('click', function () { setVersion(button.dataset.atakVersion); }); });
    document.querySelectorAll('[data-atak-layout]').forEach(function (button) { button.addEventListener('click', function () { applyLayout(button.dataset.atakLayout); }); });
    document.addEventListener('keydown', function (event) {
      var editable = /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName);
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); palette.visible() ? palette.close() : palette.open(); return; }
      if (event.key === 'Escape') palette.close();
      if (!document.body.classList.contains('atak-ui-v2') || editable) return;
      if (event.key.toLowerCase() === 'l') click('[data-tool-ui="look"]');
      if (event.key.toLowerCase() === 'i') selectSection('intel');
      if (event.key.toLowerCase() === 'j') selectSection('jtac');
      if (event.key.toLowerCase() === 'r') click('[data-tool="measure"]');
    });
    var requested = new URLSearchParams(window.location.search).get('ui');
    setVersion(requested === 'v1' || requested === 'v2' ? requested : read(VERSION_KEY, document.documentElement.classList.contains('atak-v2-boot') ? 'v2' : 'v1'));
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
