<?php
$base = url('');
$apiBase = rtrim($base, '/') . '/api';
$title = $title ?? 'COMSPEC Overwatch — C2';
$overwatchContext = $overwatchContext ?? [
    'tenantId' => 0,
    'defaultMapId' => 1,
    'defaultMapSlug' => 'altis',
    'defaultMissionId' => 'mission_0_map_1',
    'apiBase' => $apiBase,
    'syncIntervalMs' => 8000,
];
$overwatchMapsList = $overwatchMapsList ?? [['slug' => 'world', 'label' => 'World (OSM)', 'type' => 'world']];
$overwatchWorkspaces = $overwatchWorkspaces ?? [['mapId' => 1, 'label' => 'Principal', 'slug' => 'altis', 'isDefault' => true]];
$overwatchMapsConfigs = $overwatchMapsConfigs ?? [];
$overwatchDefaultMapId = $overwatchDefaultMapId ?? 1;
$overwatchDefaultMapSlug = $overwatchDefaultMapSlug ?? 'altis';
$overwatchDefaultWorkspace = $overwatchDefaultWorkspace ?? ['mapId' => 1, 'label' => 'Principal', 'slug' => 'altis'];
$overwatchPageCsrf = \App\Core\Csrf::token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/tailwind.css" />
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.css" />
  <script src="<?= htmlspecialchars($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-map-crs.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/comspec-operational-map.js"></script>
  <style>
    #overwatch-map { height: 100%; min-height: 500px; }
    .panel-tab { display: none; }
    .panel-tab.active { display: block; }
  </style>
</head>
<body class="bg-slate-100 text-slate-900 antialiased min-h-screen">
  <div class="flex flex-col h-screen">
    <header class="overwatch-header flex-shrink-0 border-b border-slate-200 bg-white px-4 py-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-4">
          <h1 class="text-xl font-black uppercase tracking-tight">COMSPEC Overwatch</h1>
          <span class="text-xs text-slate-500" id="overwatch-theatre-label">—</span>
          <span class="text-xs text-slate-500 font-mono" id="overwatch-mission-id-label">—</span>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <label class="flex items-center gap-1" title="Contexte opérationnel (théâtre / carte mission)">
            <span class="text-xs font-bold text-slate-500 uppercase">Théâtre</span>
            <select id="overwatch-workspace" class="border border-slate-300 rounded px-2 py-1 text-sm">
              <?php foreach ($overwatchWorkspaces as $w): ?>
              <option value="<?= (int)($w['mapId'] ?? 1) ?>" <?= !empty($w['isDefault']) ? 'selected' : '' ?>><?= htmlspecialchars($w['label'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="flex items-center gap-1">
            <span class="text-xs font-bold text-slate-500 uppercase">Carte</span>
            <select id="overwatch-map-select" class="border border-slate-300 rounded px-2 py-1 text-sm">
              <?php foreach ($overwatchMapsList as $m): ?>
              <option value="<?= htmlspecialchars($m['slug'] ?? 'world') ?>" <?= ($m['slug'] ?? '') === ($overwatchDefaultMapSlug ?? 'altis') ? 'selected' : '' ?>><?= htmlspecialchars($m['label'] ?? 'Carte') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <span class="text-xs text-slate-400 font-mono" id="overwatch-zulu">—:—:— Z</span>
          <span class="text-xs text-slate-400" id="overwatch-sync-indicator">—</span>
          <button type="button" id="overwatch-access-request-open" class="text-xs font-bold uppercase px-2 py-1 rounded border border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100">Demander l’accès</button>
          <nav class="flex gap-2">
            <a href="<?= htmlspecialchars(url('atak')) ?>" class="text-sm text-slate-500 hover:text-slate-800">ATAK</a>
            <a href="<?= htmlspecialchars(url('tacmap')) ?>" class="text-sm text-slate-500 hover:text-slate-800">TACMAP</a>
            <a href="<?= htmlspecialchars(url('dashboard')) ?>" class="text-sm text-slate-500 hover:text-slate-800">Dashboard</a>
          </nav>
        </div>
      </div>
    </header>

    <div class="flex flex-1 min-h-0">
      <aside class="overwatch-sidebar-left w-56 flex-shrink-0 border-r border-slate-200 bg-white flex flex-col overflow-hidden">
        <div class="p-3 border-b border-slate-200">
          <label class="block text-xs font-bold text-slate-500 mb-1">Recherche unité</label>
          <input type="text" id="overwatch-unit-search" placeholder="Indicatif…" class="w-full border border-slate-300 rounded px-2 py-1 text-sm mb-2" />
          <h2 class="text-xs font-black uppercase tracking-tight text-slate-500 mb-2">Calques tactiques</h2>
          <div class="space-y-2">
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <input type="checkbox" id="layer-units" class="rounded border-slate-300" checked />
              <span class="text-sm">Unités</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-units-count">0</span>
            </label>
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <input type="checkbox" id="layer-danger-zones" class="rounded border-slate-300" checked />
              <span class="text-sm">Danger zones</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-danger-zones-count">0</span>
            </label>
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <input type="checkbox" id="layer-fire-support" class="rounded border-slate-300" checked />
              <span class="text-sm">Fire support</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-fire-support-count">0</span>
            </label>
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <input type="checkbox" id="layer-iff" class="rounded border-slate-300" />
              <span class="text-sm">IFF</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-iff-count">0</span>
            </label>
          </div>
        </div>
        <div class="p-3 text-xs text-slate-500 flex-1">
          <p id="overwatch-units-off-map" class="hidden">Unités actives hors projection monde.</p>
        </div>
      </aside>
      <main class="flex-1 min-w-0 p-2 flex flex-col min-h-0">
        <div class="flex gap-2 mb-1 flex-wrap items-center">
          <button type="button" id="overwatch-measure-btn" class="px-2 py-1 text-xs font-bold uppercase border border-slate-300 rounded hover:bg-slate-100">Mesure</button>
          <label class="flex items-center gap-1 cursor-pointer">
            <input type="checkbox" id="overwatch-grid-toggle" class="rounded border-slate-300" />
            <span class="text-xs font-bold text-slate-600">Grille (A1, B2…)</span>
          </label>
          <span class="text-xs text-slate-500 self-center" id="overwatch-measure-result"></span>
        </div>
        <div id="overwatch-map" class="flex-1 rounded-xl border border-slate-200 bg-white shadow-sm min-h-300"></div>
      </main>

      <aside class="w-[380px] flex-shrink-0 border-l border-slate-200 bg-white flex flex-col overflow-hidden">
        <div class="border-b border-slate-200 p-2 flex gap-1 flex-wrap">
          <button type="button" data-tab="fire-support" class="tab-btn px-3 py-2 rounded-lg text-sm font-bold bg-slate-800 text-white">Fire Support</button>
          <button type="button" data-tab="danger-zones" class="tab-btn px-3 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100">Danger Zones</button>
          <button type="button" data-tab="logistics" class="tab-btn px-3 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100">Logistics</button>
          <button type="button" data-tab="sitrep" class="tab-btn px-3 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100">SITREP</button>
          <button type="button" data-tab="replay" class="tab-btn px-3 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100">Replay</button>
          <button type="button" data-tab="iff" class="tab-btn px-3 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100">IFF</button>
          <button type="button" data-tab="command-chat" class="tab-btn px-3 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100">Tchat Cmd</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
          <?php require __DIR__ . '/fire-support.php'; ?>
          <?php require __DIR__ . '/danger-zones.php'; ?>
          <?php require __DIR__ . '/logistics-status.php'; ?>
          <?php require __DIR__ . '/sitrep-board.php'; ?>
          <?php require __DIR__ . '/replay.php'; ?>
          <?php require __DIR__ . '/iff-panel.php'; ?>
          <div id="panel-command-chat" class="panel-tab">
            <h2 class="text-lg font-black uppercase tracking-tight mb-4">Tchat de commandement</h2>
            <p class="text-xs text-slate-500 mb-2">Messages partagés sur le théâtre actif (liaison ATAK).</p>
            <div id="command-chat-messages" class="border border-slate-200 rounded-lg p-2 mb-3 h-48 overflow-y-auto bg-slate-50 text-sm space-y-1"></div>
            <div class="flex gap-2">
              <input type="text" id="command-chat-input" placeholder="Message…" class="flex-1 border border-slate-300 rounded px-2 py-1 text-sm" />
              <button type="button" id="command-chat-send" class="px-3 py-1 rounded bg-slate-800 text-white text-xs font-bold uppercase">Envoyer</button>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <section class="overwatch-health border-t border-slate-200 bg-white" aria-labelledby="overwatch-health-title">
      <button type="button" id="overwatch-health-toggle" class="w-full px-4 py-2 text-left text-sm font-bold text-slate-600 hover:bg-slate-50 flex items-center justify-between" aria-expanded="false" aria-controls="overwatch-health-body">
        <span id="overwatch-health-title">Colonne santé — Liaisons techniques et jeu</span>
        <span class="text-slate-400" aria-hidden="true">▼</span>
      </button>
      <div id="overwatch-health-body" class="border-t border-slate-100 overflow-hidden" hidden>
        <div class="px-4 py-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 text-xs">
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">Données serveur</span>
            <span id="health-db" class="font-mono font-bold text-slate-700">—</span>
          </div>
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">Sync unités</span>
            <span id="health-units" class="font-mono font-bold text-slate-700">—</span>
          </div>
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">Fire support</span>
            <span id="health-fire-support" class="font-mono font-bold text-slate-700">—</span>
          </div>
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">Danger zones</span>
            <span id="health-danger-zones" class="font-mono font-bold text-slate-700">—</span>
          </div>
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">Logistics</span>
            <span id="health-logistics" class="font-mono font-bold text-slate-700">—</span>
          </div>
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">SITREP</span>
            <span id="health-sitrep" class="font-mono font-bold text-slate-700">—</span>
          </div>
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">IFF</span>
            <span id="health-iff" class="font-mono font-bold text-slate-700">—</span>
          </div>
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">Replay</span>
            <span id="health-replay" class="font-mono font-bold text-slate-700">—</span>
          </div>
          <div class="flex justify-between items-center gap-2 p-2 rounded bg-slate-50">
            <span class="text-slate-600">Tchat Cmd</span>
            <span id="health-chat" class="font-mono font-bold text-slate-700">—</span>
          </div>
        </div>
        <div class="px-4 pb-3">
          <button type="button" id="overwatch-health-refresh" class="text-xs text-slate-500 hover:text-slate-800 underline">Actualiser la santé</button>
        </div>
      </div>
    </section>
  </div>

  <div id="overwatch-access-modal" class="hidden fixed inset-0 z-[10050] flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="overwatch-access-modal-title">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-4 border border-slate-200">
      <h2 id="overwatch-access-modal-title" class="text-lg font-black uppercase tracking-tight text-slate-800 mb-1">Demande d’accès</h2>
      <p class="text-xs text-slate-600 mb-3">Un message est envoyé par e-mail aux gestionnaires de <strong>votre communauté</strong> pour qu’ils puissent vous attribuer les habilitations adaptées.</p>
      <label class="block text-xs font-bold text-slate-500 mb-1" for="overwatch-access-reason">Motif de la demande</label>
      <textarea id="overwatch-access-reason" rows="4" class="w-full border border-slate-300 rounded px-2 py-1 text-sm mb-2" placeholder="Ex. : besoin de suivre l’exercice en tant que…"></textarea>
      <p id="overwatch-access-feedback" class="text-xs mb-2 min-h-[1.25rem]" role="status"></p>
      <div class="flex gap-2 justify-end">
        <button type="button" id="overwatch-access-request-cancel" class="px-3 py-1.5 text-sm rounded border border-slate-300 text-slate-700 hover:bg-slate-50">Annuler</button>
        <button type="button" id="overwatch-access-request-submit" class="px-3 py-1.5 text-sm rounded bg-slate-800 text-white font-bold uppercase hover:bg-slate-900">Envoyer</button>
      </div>
    </div>
  </div>

  <script>
    (function() {
      const overwatchContext = <?= json_encode($overwatchContext) ?>;
      const overwatchMapsList = <?= json_encode($overwatchMapsList) ?>;
      const overwatchWorkspaces = <?= json_encode($overwatchWorkspaces) ?>;
      const overwatchMapsConfigs = <?= json_encode($overwatchMapsConfigs) ?>;
      const overwatchDefaultMapId = <?= (int)$overwatchDefaultMapId ?>;
      const overwatchDefaultMapSlug = <?= json_encode($overwatchDefaultMapSlug) ?>;
      const overwatchDefaultWorkspace = <?= json_encode($overwatchDefaultWorkspace) ?>;

      function buildMissionId(tenantId, mapId) {
        return 'mission_' + Number(tenantId) + '_map_' + Number(mapId);
      }

      window.OverwatchState = {
        tenantId: overwatchContext.tenantId,
        currentMapId: overwatchDefaultMapId,
        currentMapSlug: overwatchDefaultMapSlug,
        currentMapType: 'world',
        currentMissionId: overwatchContext.defaultMissionId,
        currentWorkspaceId: overwatchDefaultMapId,
        lastSyncAt: null,
        syncStatus: 'idle',
        unitsCount: 0,
        layers: {
          units: true,
          dangerZones: true,
          fireSupport: true,
          logistics: false,
          sitrep: false,
          iff: false,
          replay: false,
          drawings: true,
        },
      };

      function getMissionId() {
        return (window.OverwatchState && window.OverwatchState.currentMissionId) || overwatchContext.defaultMissionId || '';
      }
      function getApiBase() {
        return overwatchContext.apiBase;
      }

      const apiBase = getApiBase();
      var overwatchPageCsrf = <?= json_encode($overwatchPageCsrf ?? '') ?>;

      const tabBtns = document.querySelectorAll('.tab-btn');
      const panels = document.querySelectorAll('.panel-tab');
      tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          const id = btn.dataset.tab;
          tabBtns.forEach(b => { b.classList.remove('bg-slate-800', 'text-white'); b.classList.add('text-slate-600'); });
          btn.classList.add('bg-slate-800', 'text-white'); btn.classList.remove('text-slate-600');
          panels.forEach(p => {
            p.classList.remove('active');
            if (p.id === 'panel-' + id) p.classList.add('active');
          });
        });
      });
      document.querySelector('[data-tab="fire-support"]').click();

      const WORLD_SCALE = 30000;
      let map = null;
      let currentBaseLayer = null;
      const layerGroups = {
        base: null,
        units: null,
        dangerZones: null,
        fireSupport: null,
        drawings: null,
        markers: null,
        iff: null,
        grid: null,
      };
      var overwatchHealthStatus = { db: '—', units: '—', fireSupport: '—', dangerZones: '—', logistics: '—', sitrep: '—', iff: '—', replay: '—', chat: '—' };
      var overwatchUnitsIntervalId = null;
      var syncIntervalMs = overwatchContext.syncIntervalMs || 8000;
      var dangerZoneLayers = [];
      var dzClickMarker = null;
      var targetMarker = null;

      // Altis standard : 30720 m, 1 unit = 1 m, facteur = 212/30720 pour tuiles 212px (rayons et distances en m)
      var ALTIS_WORLD_SIZE = 30720;
      var ALTIS_FACTOR = 212 / ALTIS_WORLD_SIZE;
      var ALTIS_CENTER = [ALTIS_WORLD_SIZE / 2, ALTIS_WORLD_SIZE / 2];
      var ALTIS_BOUNDS = [[0, 0], [ALTIS_WORLD_SIZE, ALTIS_WORLD_SIZE]];
      function buildArmaConfig(raw) {
        if (!raw || !raw.tilePattern) return null;
        var isAltis = (raw.slug || (raw.config && raw.config.title) || '').toString().toLowerCase() === 'altis';
        var crsOpt = raw.crs || {};
        var tileWidth = crsOpt.tileWidth != null ? crsOpt.tileWidth : 212;
        var factorx = (isAltis ? ALTIS_FACTOR : (crsOpt.factorx != null ? crsOpt.factorx : ALTIS_FACTOR));
        var factory = (isAltis ? ALTIS_FACTOR : (crsOpt.factory != null ? crsOpt.factory : ALTIS_FACTOR));
        var CRS = typeof window.MGRS_CRS === 'function' ? window.MGRS_CRS(factorx, factory, tileWidth) : L.CRS.Simple;
        var center = isAltis ? ALTIS_CENTER : (Array.isArray(raw.center) ? raw.center : (raw.config && Array.isArray(raw.config.center) ? raw.config.center : ALTIS_CENTER));
        var bounds = isAltis ? ALTIS_BOUNDS : (raw.bounds || (raw.config && raw.config.bounds) || null);
        return {
          CRS: CRS,
          tilePattern: raw.tilePattern,
          minZoom: raw.minZoom != null ? raw.minZoom : 0,
          maxZoom: raw.maxZoom != null ? raw.maxZoom : 6,
          defaultZoom: raw.defaultZoom != null ? raw.defaultZoom : 3,
          attribution: raw.attribution || '&copy; Bohemia Interactive',
          tileSize: raw.tileSize != null ? raw.tileSize : 212,
          center: center,
          bounds: bounds,
        };
      }

      function applyBaseLayer(slug) {
        try {
          var isWorld = slug === 'world';
          var mapEl = document.getElementById('overwatch-map');
          if (!mapEl) return;

        if (map) {
          if (currentBaseLayer) {
            map.removeLayer(currentBaseLayer);
            currentBaseLayer = null;
          }
          var needRecreate = (isWorld && window.OverwatchState.currentMapType !== 'world') ||
            (!isWorld && window.OverwatchState.currentMapType !== 'arma');
          if (needRecreate) {
            map.remove();
            map = null;
            Object.keys(layerGroups).forEach(function (k) { layerGroups[k] = null; });
          }
        }

        if (!map) {
          if (isWorld) {
            map = L.map('overwatch-map', { minZoom: 2, maxZoom: 18 }).setView([0.5, 0.5], 4);
          } else {
            var cfg = overwatchMapsConfigs[slug] ? buildArmaConfig(overwatchMapsConfigs[slug]) : null;
            if (!cfg) return;
            map = L.map('overwatch-map', {
              minZoom: cfg.minZoom,
              maxZoom: cfg.maxZoom,
              crs: cfg.CRS,
            });
            map.setView(cfg.center, cfg.defaultZoom);
            if (cfg.bounds && cfg.bounds.length === 2) {
              map.setMaxBounds(L.latLngBounds(L.latLng(cfg.bounds[0][0], cfg.bounds[0][1]), L.latLng(cfg.bounds[1][0], cfg.bounds[1][1])));
            }
          }
          layerGroups.base = L.layerGroup().addTo(map);
          layerGroups.units = L.layerGroup().addTo(map);
          layerGroups.dangerZones = L.layerGroup().addTo(map);
          layerGroups.fireSupport = L.layerGroup().addTo(map);
          layerGroups.drawings = L.layerGroup().addTo(map);
          layerGroups.markers = L.layerGroup().addTo(map);
          layerGroups.iff = L.layerGroup().addTo(map);
          layerGroups.grid = L.layerGroup();
        }

        if (isWorld) {
          currentBaseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM' });
          currentBaseLayer.addTo(map);
          map.setView([0.5, 0.5], 4);
          window.OverwatchState.currentMapType = 'world';
        } else {
          var cfg = overwatchMapsConfigs[slug] ? buildArmaConfig(overwatchMapsConfigs[slug]) : null;
          if (!cfg) return;
          currentBaseLayer = L.tileLayer(cfg.tilePattern, { attribution: cfg.attribution, tileSize: cfg.tileSize });
          currentBaseLayer.addTo(map);
          map.setView(cfg.center, cfg.defaultZoom);
          if (cfg.bounds && cfg.bounds.length === 2) {
            map.setMaxBounds(L.latLngBounds(L.latLng(cfg.bounds[0][0], cfg.bounds[0][1]), L.latLng(cfg.bounds[1][0], cfg.bounds[1][1])));
          }
          window.OverwatchState.currentMapType = 'arma';
        }
        window.OverwatchState.currentMapSlug = isWorld ? 'world' : slug;

        if (overwatchUnitsIntervalId) {
          clearInterval(overwatchUnitsIntervalId);
          overwatchUnitsIntervalId = null;
        }
        if (!isWorld) {
          overwatchUnitsIntervalId = setInterval(function () { syncUnits(); }, syncIntervalMs);
          syncUnits();
        } else {
          window.OverwatchState.unitsCount = 0;
          if (layerGroups.units) layerGroups.units.clearLayers();
        }

        window.overwatchMap = map;
        applyLayerVisibility();
        attachMapClickHandlers();
        updateSyncIndicator(window.OverwatchState.syncStatus, window.OverwatchState.lastSyncAt);
        refreshOperationalContext();
        updateLayerCounts();
        var gridToggle = document.getElementById('overwatch-grid-toggle');
        if (gridToggle && gridToggle.checked && layerGroups.grid) {
          layerGroups.grid.addTo(map);
          renderMapGrid();
        }
        } catch (err) {
          if (typeof console !== 'undefined' && console.error) console.error('Overwatch applyBaseLayer:', err);
          window.OverwatchState.syncStatus = 'error';
          updateSyncIndicator('error', null);
        }
      }

      function setWorkspace(mapId) {
        mapId = parseInt(mapId, 10);
        var ws = overwatchWorkspaces.find(function (w) { return w.mapId === mapId; });
        window.OverwatchState.currentMapId = mapId;
        window.OverwatchState.currentWorkspaceId = mapId;
        window.OverwatchState.currentMissionId = buildMissionId(overwatchContext.tenantId, mapId);
        window.OverwatchState.currentMapSlug = ws ? ws.slug : window.OverwatchState.currentMapSlug;
        var selMap = document.getElementById('overwatch-map-select');
        if (selMap && ws && selMap.value !== ws.slug) {
          selMap.value = ws.slug;
          applyBaseLayer(ws.slug);
        } else if (window.OverwatchState.currentMapType === 'arma' && ws) {
          applyBaseLayer(ws.slug);
        } else {
          refreshOperationalContext();
        }
        updateHeaderLabels();
      }

      function setMap(slug) {
        if (slug === 'world') {
          window.OverwatchState.currentMapType = 'world';
          window.OverwatchState.currentMapSlug = 'world';
          applyBaseLayer('world');
        } else {
          var ws = overwatchWorkspaces.find(function (w) { return w.slug === slug; });
          if (ws) {
            window.OverwatchState.currentMapId = ws.mapId;
            window.OverwatchState.currentWorkspaceId = ws.mapId;
            window.OverwatchState.currentMissionId = buildMissionId(overwatchContext.tenantId, ws.mapId);
            var selWs = document.getElementById('overwatch-workspace');
            if (selWs && selWs.value !== String(ws.mapId)) {
              selWs.value = ws.mapId;
            }
          }
          window.OverwatchState.currentMapType = 'arma';
          window.OverwatchState.currentMapSlug = slug;
          applyBaseLayer(slug);
        }
        updateHeaderLabels();
      }

      function refreshOperationalContext() {
        try {
          if (typeof ComspecOperationalMap !== 'undefined' && ComspecOperationalMap.renderMapShapes && layerGroups.drawings && map) {
            ComspecOperationalMap.renderMapShapes({
              apiBase: apiBase,
              mapId: window.OverwatchState.currentMapId,
              missionId: getMissionId(),
              map: map,
              layerGroup: layerGroups.drawings,
              isWorld: window.OverwatchState.currentMapType === 'world',
              credentials: 'include',
            });
          }
        } catch (e) { if (console && console.error) console.error('renderMapShapes', e); }
        try {
          loadDangerZones();
        } catch (e) { if (console && console.error) console.error('loadDangerZones', e); }
        try {
          loadFireSupportUnits();
        } catch (e) { if (console && console.error) console.error('loadFireSupportUnits', e); }
        try {
          loadLogistics();
        } catch (e) { if (console && console.error) console.error('loadLogistics', e); }
        try {
          loadSitrep();
        } catch (e) { if (console && console.error) console.error('loadSitrep', e); }
        try {
          loadReplay();
        } catch (e) { if (console && console.error) console.error('loadReplay', e); }
        try {
          loadIff();
        } catch (e) { if (console && console.error) console.error('loadIff', e); }
        if (window.OverwatchState.currentMapType === 'arma') {
          try { syncUnits(); } catch (e) { if (console && console.error) console.error('syncUnits', e); }
        }
      }

      function updateHeaderLabels() {
        var theatreEl = document.getElementById('overwatch-theatre-label');
        var missionEl = document.getElementById('overwatch-mission-id-label');
        if (theatreEl) theatreEl.textContent = window.OverwatchState.currentMapSlug || '—';
        if (missionEl) missionEl.textContent = window.OverwatchState.currentMissionId || '—';
      }

      function updateSyncIndicator(status, timestamp) {
        var el = document.getElementById('overwatch-sync-indicator');
        if (!el) return;
        var msg = status === 'ok' ? 'Sync OK' : status === 'syncing' ? 'Sync…' : status === 'degraded' ? 'Dégradé' : status === 'error' ? 'Erreur' : '—';
        if (timestamp) msg += ' ' + new Date(timestamp).toLocaleTimeString('fr-FR', { hour12: false });
        var n = window.OverwatchState.unitsCount;
        if (window.OverwatchState.currentMapType === 'arma' && n >= 0) msg += ' · ' + n + ' unités';
        el.textContent = msg;
      }

      function applyLayerVisibility() {
        if (!map) return;
        var layers = window.OverwatchState.layers;
        if (layerGroups.units) {
          if (layers.units) { try { layerGroups.units.addTo(map); } catch (e) {} }
          else { try { map.removeLayer(layerGroups.units); } catch (e) {} }
        }
        if (layerGroups.dangerZones) {
          if (layers.dangerZones) { try { layerGroups.dangerZones.addTo(map); } catch (e) {} }
          else { try { map.removeLayer(layerGroups.dangerZones); } catch (e) {} }
        }
        if (layerGroups.fireSupport) {
          if (layers.fireSupport) { try { layerGroups.fireSupport.addTo(map); } catch (e) {} }
          else { try { map.removeLayer(layerGroups.fireSupport); } catch (e) {} }
        }
        if (layerGroups.iff) {
          if (layers.iff) { try { layerGroups.iff.addTo(map); } catch (e) {} }
          else { try { map.removeLayer(layerGroups.iff); } catch (e) {} }
        }
      }

      function updateLayerCounts() {
        var el = document.getElementById('layer-units-count');
        if (el) el.textContent = String(window.OverwatchState.unitsCount || 0);
        el = document.getElementById('layer-danger-zones-count');
        if (el) el.textContent = String(dangerZoneLayers.length);
        el = document.getElementById('layer-fire-support-count');
        if (el) el.textContent = targetMarker ? '1' : '0';
        el = document.getElementById('layer-iff-count');
        if (el) el.textContent = '0';
      }

      document.getElementById('overwatch-workspace').addEventListener('change', function () {
        setWorkspace(this.value);
      });
      document.getElementById('overwatch-map-select').addEventListener('change', function () {
        setMap(this.value);
      });

      document.getElementById('layer-units').addEventListener('change', function () {
        window.OverwatchState.layers.units = this.checked;
        applyLayerVisibility();
        if (this.checked && window.OverwatchState.currentMapType === 'arma') syncUnits();
        updateLayerCounts();
      });
      document.getElementById('layer-danger-zones').addEventListener('change', function () {
        window.OverwatchState.layers.dangerZones = this.checked;
        applyLayerVisibility();
        updateLayerCounts();
      });
      document.getElementById('layer-fire-support').addEventListener('change', function () {
        window.OverwatchState.layers.fireSupport = this.checked;
        applyLayerVisibility();
        updateLayerCounts();
      });
      document.getElementById('layer-iff').addEventListener('change', function () {
        window.OverwatchState.layers.iff = this.checked;
        applyLayerVisibility();
        updateLayerCounts();
      });

      var searchInput = document.getElementById('overwatch-unit-search');
      if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            focusUnitByCallsign(this.value);
          }
        });
      }

      var measureMode = false;
      var measurePointA = null;
      var measureLine = null;
      document.getElementById('overwatch-measure-btn') && document.getElementById('overwatch-measure-btn').addEventListener('click', function () {
        measureMode = true;
        measurePointA = null;
        if (measureLine && map) try { map.removeLayer(measureLine); } catch (e) {}
        measureLine = null;
        var resEl = document.getElementById('overwatch-measure-result');
        if (resEl) resEl.textContent = 'Cliquez 2 points sur la carte.';
      });
      function formatDistance(meters) {
        var m = Math.round(meters);
        var km = (meters / 1000).toFixed(2).replace('.', ',');
        var nm = (meters / 1852).toFixed(1).replace('.', ',');
        var mi = (meters / 1609.344).toFixed(2).replace('.', ',');
        function sep(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f'); }
        return 'Distance : ' + sep(m) + ' m  ·  ' + sep(km) + ' km  ·  ' + sep(nm) + ' nm  ·  ' + sep(mi) + ' mi';
      }
      function finishMeasure(latlngB) {
        if (!measurePointA || !latlngB || !map) return;
        var d;
        if (window.OverwatchState.currentMapType === 'arma') {
          // Cartes Arma : coordonnées en mètres (1 unit = 1 m), distance euclidienne explicite pour éviter tout CRS incorrect
          var dx = latlngB.lng - measurePointA.lng, dy = latlngB.lat - measurePointA.lat;
          d = Math.sqrt(dx * dx + dy * dy);
        } else {
          d = map.distance(measurePointA, latlngB);
        }
        var resEl = document.getElementById('overwatch-measure-result');
        if (resEl) resEl.textContent = formatDistance(d);
        measureMode = false;
        measurePointA = null;
      }
      function onMeasureClick(e) {
        if (!measureMode || !map) return;
        if (!measurePointA) {
          measurePointA = e.latlng;
          if (measureLine && map) try { map.removeLayer(measureLine); } catch (e) {}
          measureLine = L.polyline([measurePointA], { color: '#3b82f6', weight: 2 }).addTo(map);
          var resEl = document.getElementById('overwatch-measure-result');
          if (resEl) resEl.textContent = 'Cliquez le 2e point.';
        } else {
          measureLine.setLatLngs([measurePointA, e.latlng]);
          finishMeasure(e.latlng);
        }
      }

      var GRID_ROWS = 10;
      var GRID_COLS = 10;
      var gridLetters = 'ABCDEFGHIJ';
      /** Bounds fixes de la grille (carte) pour que les cases A1, B3… soient toujours aux mêmes endroits. */
      function getGridFixedBounds() {
        if (window.OverwatchState.currentMapType === 'world') {
          return { minLat: -90, maxLat: 90, minLng: -180, maxLng: 180 };
        }
        var slug = window.OverwatchState.currentMapSlug || 'altis';
        var cfg = overwatchMapsConfigs[slug];
        var bounds = (cfg && (cfg.bounds || (cfg.config && cfg.config.bounds))) || ALTIS_BOUNDS;
        if (bounds && bounds.length === 2) {
          return { minLat: bounds[0][0], minLng: bounds[0][1], maxLat: bounds[1][0], maxLng: bounds[1][1] };
        }
        return { minLat: 0, minLng: 0, maxLat: ALTIS_WORLD_SIZE, maxLng: ALTIS_WORLD_SIZE };
      }
      function renderMapGrid() {
        if (!layerGroups.grid || !map) return;
        layerGroups.grid.clearLayers();
        var b = getGridFixedBounds();
        var minLat = b.minLat, maxLat = b.maxLat, minLng = b.minLng, maxLng = b.maxLng;
        var dLat = (maxLat - minLat) / GRID_ROWS;
        var dLng = (maxLng - minLng) / GRID_COLS;
        for (var r = 0; r <= GRID_ROWS; r++) {
          var lat = minLat + r * dLat;
          var line = L.polyline([[lat, minLng], [lat, maxLng]], { color: 'rgba(100,100,120,0.5)', weight: 1 });
          layerGroups.grid.addLayer(line);
        }
        for (var c = 0; c <= GRID_COLS; c++) {
          var lng = minLng + c * dLng;
          var line = L.polyline([[minLat, lng], [maxLat, lng]], { color: 'rgba(100,100,120,0.5)', weight: 1 });
          layerGroups.grid.addLayer(line);
        }
        for (var r = 0; r < GRID_ROWS; r++) {
          for (var c = 0; c < GRID_COLS; c++) {
            var lat = minLat + (r + 0.5) * dLat;
            var lng = minLng + (c + 0.5) * dLng;
            var label = (gridLetters[r] || String(r + 1)) + (c + 1);
            var icon = L.divIcon({
              className: 'overwatch-grid-label',
              html: '<span style="font-size:10px;color:#475569;font-weight:700;text-shadow:0 0 2px #fff, 0 1px 1px #fff;">' + label + '</span>',
              iconSize: [24, 14],
              iconAnchor: [12, 7]
            });
            var marker = L.marker([lat, lng], { icon: icon });
            layerGroups.grid.addLayer(marker);
          }
        }
      }
      document.getElementById('overwatch-grid-toggle') && document.getElementById('overwatch-grid-toggle').addEventListener('change', function () {
        if (!map || !layerGroups.grid) return;
        if (this.checked) {
          layerGroups.grid.addTo(map);
          renderMapGrid();
        } else {
          map.removeLayer(layerGroups.grid);
        }
      });

      function updateZulu() {
        var el = document.getElementById('overwatch-zulu');
        if (el) el.textContent = new Date().toISOString().substr(11, 8) + ' Z';
      }
      setInterval(updateZulu, 1000);
      updateZulu();

      function updateUnitsOffMapBanner() {
        var el = document.getElementById('overwatch-units-off-map');
        if (!el) return;
        if (window.OverwatchState.currentMapType === 'world' && (window.OverwatchState.unitsCount || 0) > 0) {
          el.classList.remove('hidden');
          el.textContent = window.OverwatchState.unitsCount + ' unité(s) active(s) hors projection monde.';
        } else {
          el.classList.add('hidden');
        }
      }
      window.updateUnitsOffMapBanner = updateUnitsOffMapBanner;

      function refreshHealthPanel() {
        overwatchHealthStatus.units = window.OverwatchState.syncStatus === 'ok' ? 'OK' : window.OverwatchState.syncStatus === 'error' ? 'Erreur' : overwatchHealthStatus.units;
        var ids = ['health-db', 'health-units', 'health-fire-support', 'health-danger-zones', 'health-logistics', 'health-sitrep', 'health-iff', 'health-replay', 'health-chat'];
        var keys = ['db', 'units', 'fireSupport', 'dangerZones', 'logistics', 'sitrep', 'iff', 'replay', 'chat'];
        keys.forEach(function (k, i) {
          var el = document.getElementById(ids[i]);
          if (el) {
            el.textContent = overwatchHealthStatus[k] || '—';
            el.className = 'font-mono font-bold ' + (overwatchHealthStatus[k] === 'OK' ? 'text-green-700' : overwatchHealthStatus[k] === 'Erreur' ? 'text-red-700' : 'text-slate-700');
          }
        });
      }
      function refreshHealth() {
        fetch(apiBase + '/health', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
          overwatchHealthStatus.db = (d && d.db === 'ok') ? 'OK' : 'Erreur';
          refreshHealthPanel();
        }).catch(function () { overwatchHealthStatus.db = 'Erreur'; refreshHealthPanel(); });
      }
      document.getElementById('overwatch-health-toggle') && document.getElementById('overwatch-health-toggle').addEventListener('click', function () {
        var body = document.getElementById('overwatch-health-body');
        if (!body) return;
        var open = body.hidden;
        body.hidden = !open;
        this.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) refreshHealth();
      });
      document.getElementById('overwatch-health-refresh') && document.getElementById('overwatch-health-refresh').addEventListener('click', refreshHealth);
      window.refreshHealthPanel = refreshHealthPanel;

      function loadCommandChat() {
        var el = document.getElementById('command-chat-messages');
        if (!el) return;
        fetch(apiBase + '/chat?mapId=' + encodeURIComponent(window.OverwatchState.currentMapId) + '&limit=100', { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (rows) {
            overwatchHealthStatus.chat = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
            el.innerHTML = (rows || []).slice(0, 50).reverse().map(function (m) {
              var t = m.created_at || '';
              return '<div class="flex gap-1"><span class="text-slate-400 shrink-0">' + t.substring(11, 19) + '</span><strong>' + (m.author || '?') + '</strong>: ' + (m.body || '').replace(/</g, '&lt;') + '</div>';
            }).join('') || '<p class="text-slate-500 text-xs">Aucun message.</p>';
            el.scrollTop = el.scrollHeight;
          })
          .catch(function () { overwatchHealthStatus.chat = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); el.innerHTML = '<p class="text-red-600 text-xs">Erreur chargement.</p>'; });
      }
      function sendCommandChat() {
        var input = document.getElementById('command-chat-input');
        var body = input && input.value ? input.value.trim() : '';
        if (!body) return;
        fetch(apiBase + '/chat', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ mapId: window.OverwatchState.currentMapId, author: 'C2', body: body }),
          credentials: 'include'
        }).then(function (r) { return r.json(); }).then(function () {
          input.value = '';
          loadCommandChat();
        }).catch(function () { overwatchHealthStatus.chat = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); });
      }
      document.getElementById('command-chat-send') && document.getElementById('command-chat-send').addEventListener('click', sendCommandChat);
      document.getElementById('command-chat-input') && document.getElementById('command-chat-input').addEventListener('keydown', function (e) { if (e.key === 'Enter') sendCommandChat(); });
      document.querySelector('[data-tab="command-chat"]') && document.querySelector('[data-tab="command-chat"]').addEventListener('click', loadCommandChat);

      var initialMapSlug = (document.getElementById('overwatch-map-select') && document.getElementById('overwatch-map-select').value) || '<?= isset($overwatchDefaultMapSlug) ? addslashes($overwatchDefaultMapSlug) : 'altis' ?>';
      applyBaseLayer(initialMapSlug);
      updateHeaderLabels();
      updateUnitsOffMapBanner();

      window.overwatchApiBase = apiBase;
      Object.defineProperty(window, 'overwatchMissionId', { get: getMissionId, configurable: true });
      window.OverwatchState.applyBaseLayer = applyBaseLayer;
      window.OverwatchState.setWorkspace = setWorkspace;
      window.OverwatchState.setMap = setMap;
      window.OverwatchState.refreshOperationalContext = refreshOperationalContext;
      window.OverwatchState.updateSyncIndicator = updateSyncIndicator;
      window.updateLayerCounts = updateLayerCounts;
      window.applyLayerVisibility = applyLayerVisibility;

      function getClickMissionCoords(e) {
        if (window.OverwatchState.currentMapType === 'arma') {
          return { x: e.latlng.lng, y: e.latlng.lat };
        }
        return { x: e.latlng.lat * WORLD_SCALE, y: e.latlng.lng * WORLD_SCALE };
      }

      function syncUnits() {
        if (window.OverwatchState.currentMapType !== 'arma' || !window.OverwatchState.layers.units) return;
        if (window._overwatchSyncUnitsInProgress) return;
        window._overwatchSyncUnitsInProgress = true;
        window.OverwatchState.syncStatus = 'syncing';
        updateSyncIndicator('syncing', null);
        fetch(apiBase + '/units?mapId=' + encodeURIComponent(window.OverwatchState.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (rows) {
            window._overwatchSyncUnitsInProgress = false;
            window.OverwatchState.syncStatus = 'ok';
            window.OverwatchState.lastSyncAt = Date.now();
            window.OverwatchState.unitsCount = (rows && rows.length) ? rows.length : 0;
            overwatchHealthStatus.units = 'OK';
            updateSyncIndicator('ok', window.OverwatchState.lastSyncAt);
            renderUnits(rows || []);
            if (window.updateUnitsOffMapBanner) window.updateUnitsOffMapBanner();
            if (window.refreshHealthPanel) refreshHealthPanel();
          })
          .catch(function () {
            window._overwatchSyncUnitsInProgress = false;
            window.OverwatchState.syncStatus = 'error';
            overwatchHealthStatus.units = 'Erreur';
            updateSyncIndicator('error', null);
            if (window.refreshHealthPanel) refreshHealthPanel();
          });
      }

      function affiliationColor(aff) {
        var a = (aff || '').toUpperCase();
        if (a === 'ENEMY' || a === 'HOSTILE') return '#dc2626';
        if (a === 'UNKNOWN' || a === 'SUSPECT') return '#eab308';
        if (a === 'NEUTRAL') return '#22c55e';
        return '#3b82f6';
      }

      var overwatchLastUnits = [];
      function renderUnits(units) {
        if (!layerGroups.units || !map) return;
        layerGroups.units.clearLayers();
        overwatchLastUnits = [];
        (units || []).forEach(function (u) {
          var gridRef = (u.grid_ref || '').trim().split(/\s+/);
          var x = parseFloat(gridRef[0]);
          var y = parseFloat(gridRef[1]);
          if (isNaN(x) || isNaN(y)) return;
          var latlng = L.latLng(y, x);
          var extra = {};
          try {
            if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
            else if (u.extra && typeof u.extra === 'object') extra = u.extra;
          } catch (e) {}
          var aff = extra.affiliation || extra.affil || u.affiliation || 'friend';
          var color = affiliationColor(aff);
          var heading = parseFloat(u.heading) || 0;
          var rot = 'transform:rotate(' + heading + 'deg);';
          var html = '<span style="display:inline-block;padding:2px 6px;background:' + color + ';color:#fff;font-size:10px;border-radius:2px;white-space:nowrap;border:1px solid rgba(0,0,0,0.2);' + rot + '">' + (u.call_sign || '?') + '</span>';
          var icon = L.divIcon({
            className: 'overwatch-unit-icon',
            html: html,
            iconSize: [80, 24],
            iconAnchor: [40, 12]
          });
          var marker = L.marker(latlng, { icon: icon });
          marker.bindPopup('<strong>' + (u.call_sign || '—') + '</strong><br/>' + (u.role || '') + (aff !== 'friend' ? '<br/><em>' + aff + '</em>' : ''));
          marker.addTo(layerGroups.units);
          overwatchLastUnits.push({ call_sign: (u.call_sign || '').toUpperCase(), lat: y, lng: x });
        });
        if (window.updateLayerCounts) window.updateLayerCounts();
      }

      function focusUnitByCallsign(callsign) {
        var q = (callsign || '').toUpperCase().trim();
        if (!q || !map) return;
        var u = overwatchLastUnits.find(function (x) { return x.call_sign.indexOf(q) >= 0 || q.indexOf(x.call_sign) >= 0; });
        if (u) {
          map.setView([u.lat, u.lng], Math.max(map.getZoom(), 4));
        }
      }
      window.focusUnitByCallsign = focusUnitByCallsign;

      let lastFireSolution = null;

      function loadFireSupportUnits() {
        var sel = document.getElementById('fire-support-unit');
        if (!sel) return;
        sel.innerHTML = '<option value="">— Manuel (saisir position) —</option>';
        fetch(apiBase + '/fire-support/units?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (units) {
            overwatchHealthStatus.fireSupport = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
            (units || []).forEach(function (u) {
              var opt = document.createElement('option');
              opt.value = u.id;
              opt.textContent = (u.callsign || 'Unit') + (u.weapon_system ? ' (' + u.weapon_system + ')' : '');
              sel.appendChild(opt);
            });
          })
          .catch(function () { overwatchHealthStatus.fireSupport = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); });
      }

      function attachMapClickHandlers() {
        if (!map) return;
        map.off('click');
        map.on('click', function (e) {
          if (measureMode) {
            onMeasureClick(e);
            return;
          }
          var coords = getClickMissionCoords(e);
          var lat = e.latlng.lat;
          var lng = e.latlng.lng;
          var targetX = coords.x;
          var targetY = coords.y;
          var unitSel = document.getElementById('fire-support-unit');
          var ammoSel = document.getElementById('fire-support-ammo');
          var fireUnitId = unitSel && unitSel.value ? parseInt(unitSel.value, 10) : null;
          var body = {
            missionId: getMissionId(),
            target_x: targetX,
            target_y: targetY,
            target_z: 0,
            ammoType: ammoSel ? ammoSel.value : 'HE'
          };
          if (fireUnitId) body.fireUnitId = fireUnitId;
          else { body.gun_x = 0; body.gun_y = 0; body.gun_z = 0; }

          fetch(apiBase + '/fire-support/calculate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            credentials: 'include'
          })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              var errEl = document.getElementById('fire-support-error');
              var solEl = document.getElementById('fire-support-solution');
              if (errEl) errEl.classList.add('hidden');
              if (data.error) {
                if (errEl) { errEl.textContent = data.error; errEl.classList.remove('hidden'); }
                return;
              }
              lastFireSolution = data;
              var sol = data.solution || {};
              var fsDistance = document.getElementById('fs-distance');
              if (fsDistance) fsDistance.textContent = (sol.distance != null) ? Math.round(sol.distance) + ' m' : '—';
              var ids = ['fs-azimuth-deg', 'fs-azimuth-mils', 'fs-charge', 'fs-elevation', 'fs-tof'];
              var keys = ['azimuth_deg', 'azimuth_mils', 'charge', 'elevation_mils', 'tof'];
              keys.forEach(function (k, i) {
                var el = document.getElementById(ids[i]);
                if (el) el.textContent = sol[k] != null ? (k === 'tof' ? sol[k] + ' s' : sol[k]) : '—';
              });
              if (solEl) solEl.classList.remove('hidden');
              if (targetMarker && layerGroups.fireSupport) layerGroups.fireSupport.removeLayer(targetMarker);
              targetMarker = L.marker([lat, lng]).bindPopup('Cible');
              if (layerGroups.fireSupport) targetMarker.addTo(layerGroups.fireSupport);
            })
            .catch(function (err) {
              var errEl = document.getElementById('fire-support-error');
              if (errEl) { errEl.textContent = err.message || 'Erreur réseau'; errEl.classList.remove('hidden'); }
            });
        });
        map.on('click', function (e) {
          var activeTab = document.querySelector('.tab-btn.bg-slate-800');
          if (activeTab && activeTab.dataset.tab === 'danger-zones') {
            var coords = getClickMissionCoords(e);
            var dzX = document.getElementById('dz-center-x');
            var dzY = document.getElementById('dz-center-y');
            if (dzX) dzX.value = coords.x.toFixed(1);
            if (dzY) dzY.value = coords.y.toFixed(1);
            if (dzClickMarker && layerGroups.markers) layerGroups.markers.removeLayer(dzClickMarker);
            dzClickMarker = L.circleMarker([e.latlng.lat, e.latlng.lng], { radius: 8, color: '#dc2626', fillOpacity: 0.8 }).bindPopup('Centre zone');
            if (layerGroups.markers) dzClickMarker.addTo(layerGroups.markers);
          }
        });
      }

      function loadDangerZones() {
        fetch(apiBase + '/danger-zones?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (zones) {
            dangerZoneLayers.forEach(function (l) {
              if (layerGroups.dangerZones) layerGroups.dangerZones.removeLayer(l);
            });
            dangerZoneLayers = [];
            var listEl = document.getElementById('dz-list');
            if (listEl) listEl.innerHTML = '';
            var isWorld = window.OverwatchState.currentMapType === 'world';
            (zones || []).forEach(function (z) {
              var geom = z.geometry_json || z.geometry;
              var type = z.geometry_type || 'CIRCLE';
              if (type === 'CIRCLE' && geom && geom.center && geom.radius) {
                var lat, lng, radius;
                if (isWorld) {
                  lat = geom.center[0] / WORLD_SCALE;
                  lng = geom.center[1] / WORLD_SCALE;
                  radius = geom.radius / WORLD_SCALE * 111000;
                  radius = Math.min(radius, 50000);
                } else {
                  lat = geom.center[1];
                  lng = geom.center[0];
                  radius = geom.radius;
                }
                var layer = L.circle([lat, lng], { radius: radius, color: z.color || '#ef4444', fillOpacity: z.fill_opacity || 0.25 }).bindPopup(z.label || z.zone_type);
                if (layerGroups.dangerZones) layer.addTo(layerGroups.dangerZones);
                dangerZoneLayers.push(layer);
              }
              if (listEl) {
                var div = document.createElement('div');
                div.className = 'flex justify-between items-center text-sm border-b border-slate-100 py-1';
                div.innerHTML = '<span>' + (z.label || z.zone_type) + '</span><button type="button" class="text-red-600 dz-del" data-id="' + z.id + '">Suppr.</button>';
                listEl.appendChild(div);
              }
            });
            document.querySelectorAll('.dz-del').forEach(function (btn) {
              btn.addEventListener('click', function () {
                fetch(apiBase + '/danger-zones/' + btn.dataset.id + '?missionId=' + encodeURIComponent(getMissionId()), { method: 'DELETE', credentials: 'include' }).then(function () { loadDangerZones(); });
              });
            });
            if (window.updateLayerCounts) window.updateLayerCounts();
            overwatchHealthStatus.dangerZones = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
          })
          .catch(function () { overwatchHealthStatus.dangerZones = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); });
      }
      document.getElementById('dz-create') && document.getElementById('dz-create').addEventListener('click', function () {
        const cx = document.getElementById('dz-center-x').value;
        const cy = document.getElementById('dz-center-y').value;
        const radius = document.getElementById('dz-radius').value;
        const label = document.getElementById('dz-label').value;
        const zoneType = document.getElementById('dz-type').value;
        if (!cx || !cy) { alert('Cliquez sur la carte pour définir le centre.'); return; }
        fetch(apiBase + '/danger-zones', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            missionId: getMissionId(),
            zone_type: zoneType,
            label: label || zoneType,
            geometry_type: 'CIRCLE',
            geometry_json: { center: [parseFloat(cx), parseFloat(cy)], radius: parseInt(radius, 10) || 500 },
            color: '#ef4444',
            fill_opacity: 0.25
          }),
          credentials: 'include'
        }).then(function (r) { return r.json(); }).then(function () { loadDangerZones(); }).catch(function () {});
      });

      function loadLogistics() {
        const el = document.getElementById('logistics-list');
        if (!el) return;
        fetch(apiBase + '/logistics/assets?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (assets) {
            overwatchHealthStatus.logistics = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
            if (!assets || assets.length === 0) { el.innerHTML = '<p class="text-slate-500 text-xs">Aucun asset.</p>'; return; }
            el.innerHTML = assets.map(function (a) {
              const flags = (a.statusFlags || []).join(', ') || '—';
              const sust = a.sustainability || '—';
              const fuel = a.fuel_ratio != null ? Math.round(a.fuel_ratio * 100) + '%' : '—';
              const damage = a.damage_ratio != null ? Math.round(a.damage_ratio * 100) + '%' : '—';
              const crew = a.crew_count != null ? a.crew_count : '—';
              const color = sust === 'CRITICAL' || sust === 'NONE' ? 'border-red-200 bg-red-50' : sust === 'LIMITED' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50';
              return '<div class="rounded-lg border p-2 ' + color + '"><div class="font-bold">' + (a.callsign || a.asset_id) + '</div><div class="text-xs text-slate-600">Fuel: ' + fuel + ' | Damage: ' + damage + ' | Crew: ' + crew + '</div><div class="text-xs">' + flags + ' | ' + sust + '</div></div>';
            }).join('');
          })
          .catch(function () { overwatchHealthStatus.logistics = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); el.innerHTML = '<p class="text-slate-500 text-xs">Erreur chargement.</p>'; });
      }
      document.querySelector('[data-tab="logistics"]')?.addEventListener('click', loadLogistics);
      loadLogistics();

      var sitrepLayers = [];
      function loadSitrep() {
        var el = document.getElementById('sitrep-list');
        if (!el) return;
        fetch(apiBase + '/intel/fused?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (reports) {
            sitrepLayers.forEach(function (l) {
              if (layerGroups.markers && l) layerGroups.markers.removeLayer(l);
            });
            sitrepLayers = [];
            if (!reports || reports.length === 0) { el.innerHTML = '<p class="text-slate-500 text-xs">Aucun report.</p>'; return; }
            var isWorld = window.OverwatchState.currentMapType === 'world';
            el.innerHTML = reports.map(function (r) {
              var status = r.status || 'TEMPORARY';
              var color = status === 'CONFIRMED' ? 'bg-red-100 border-red-300' : status === 'CORROBORATED' ? 'bg-amber-100 border-amber-300' : 'bg-yellow-100 border-yellow-300';
              return '<div class="rounded-lg border p-2 ' + color + '"><div class="font-bold">' + (r.target_type || '?') + ' — ' + status + '</div><div class="text-xs">' + (r.merged_count || 1) + ' report(s) · ' + (r.source_callsign || '?') + '</div></div>';
            }).join('');
            overwatchHealthStatus.sitrep = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
            reports.forEach(function (r) {
              var lat = isWorld ? (r.pos_y != null ? r.pos_y : 0) / WORLD_SCALE : (r.pos_y != null ? r.pos_y : 0);
              var lng = isWorld ? (r.pos_x != null ? r.pos_x : 0) / WORLD_SCALE : (r.pos_x != null ? r.pos_x : 0);
              var status = r.status || 'TEMPORARY';
              var col = status === 'CONFIRMED' ? '#dc2626' : status === 'CORROBORATED' ? '#d97706' : '#eab308';
              var layer = L.circleMarker([lat, lng], { radius: 10, color: col, fillOpacity: 0.8 }).bindPopup((r.target_type || '?') + ' — ' + status);
              if (layerGroups.markers) layer.addTo(layerGroups.markers);
              sitrepLayers.push(layer);
            });
          })
          .catch(function () { overwatchHealthStatus.sitrep = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); el.innerHTML = '<p class="text-slate-500 text-xs">Erreur.</p>'; });
      }
      document.querySelector('[data-tab="sitrep"]')?.addEventListener('click', loadSitrep);
      loadSitrep();
      document.getElementById('sitrep-test-submit') && document.getElementById('sitrep-test-submit').addEventListener('click', function () {
        var missionId = getMissionId();
        var target = document.getElementById('sitrep-test-target');
        var x = document.getElementById('sitrep-test-x');
        var y = document.getElementById('sitrep-test-y');
        var source = document.getElementById('sitrep-test-source');
        var payload = {
          missionId: missionId,
          target_type: target ? target.value.trim() || 'UNKNOWN' : 'UNKNOWN',
          pos_x: x ? parseFloat(x.value) || 0 : 0,
          pos_y: y ? parseFloat(y.value) || 0 : 0,
          source_callsign: source ? source.value.trim() || 'C2' : 'C2'
        };
        fetch(apiBase + '/intel/report', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
          credentials: 'include'
        }).then(function (r) { return r.json(); }).then(function () { loadSitrep(); }).catch(function () { loadSitrep(); });
      });

      let replayData = { timeline: [] };
      let replayIndex = 0;
      let replayTimer = null;
      function loadReplay() {
        fetch(apiBase + '/replay/mission/' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            replayData = data;
            overwatchHealthStatus.replay = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
            var slider = document.getElementById('replay-slider');
            if (slider) { slider.max = Math.max(0, (data.timeline || []).length - 1); slider.value = 0; }
            var info = document.getElementById('replay-info');
            if (info) info.textContent = (data.timeline || []).length + ' instant(s)';
          })
          .catch(function () { overwatchHealthStatus.replay = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); var el = document.getElementById('replay-info'); if (el) el.textContent = 'Erreur chargement.'; });
      }
      document.querySelector('[data-tab="replay"]')?.addEventListener('click', loadReplay);
      loadReplay();

      function loadIff() {
        fetch(apiBase + '/iff/current?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (c) {
            var codeEl = document.getElementById('iff-challenge-code');
            var validEl = document.getElementById('iff-valid-until');
            if (codeEl) codeEl.textContent = c.code || '—';
            if (validEl) validEl.textContent = c.valid_until ? 'Valide jusqu\'à ' + c.valid_until : '—';
          });
        fetch(apiBase + '/iff/assets?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (assets) {
            overwatchHealthStatus.iff = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
            var el = document.getElementById('iff-assets-list');
            if (!el) return;
            if (!assets || assets.length === 0) { el.innerHTML = '<p class="text-slate-500 text-xs">Aucun asset.</p>'; return; }
            el.innerHTML = assets.map(function (a) {
              var st = a.response_status || 'PENDING';
              var color = st === 'FRIENDLY' ? 'bg-blue-100 border-blue-300' : st === 'SUSPECT' ? 'bg-red-100 border-red-300' : st === 'EXPIRED' ? 'bg-amber-100 border-amber-300' : 'bg-slate-100 border-slate-300';
              return '<div class="rounded-lg border p-2 ' + color + '"><span class="font-bold">' + (a.callsign || a.asset_id) + '</span> — ' + st + '</div>';
            }).join('');
          })
          .catch(function () { overwatchHealthStatus.iff = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); });
      }
      document.querySelector('[data-tab="iff"]')?.addEventListener('click', loadIff);
      loadIff();

      (function setupOverwatchAccessRequest() {
        var modal = document.getElementById('overwatch-access-modal');
        var openBtn = document.getElementById('overwatch-access-request-open');
        var cancelBtn = document.getElementById('overwatch-access-request-cancel');
        var submitBtn = document.getElementById('overwatch-access-request-submit');
        var reasonEl = document.getElementById('overwatch-access-reason');
        var feedbackEl = document.getElementById('overwatch-access-feedback');
        if (!modal || !openBtn || !cancelBtn || !submitBtn || !reasonEl || !feedbackEl) return;
        function closeModal() {
          modal.classList.add('hidden');
          feedbackEl.textContent = '';
          feedbackEl.className = 'text-xs mb-2 min-h-[1.25rem]';
        }
        openBtn.addEventListener('click', function () {
          reasonEl.value = '';
          feedbackEl.textContent = '';
          modal.classList.remove('hidden');
          reasonEl.focus();
        });
        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
          if (e.target === modal) closeModal();
        });
        submitBtn.addEventListener('click', function () {
          var reason = (reasonEl.value || '').trim();
          if (!reason) {
            feedbackEl.textContent = 'Indiquez un court motif.';
            feedbackEl.className = 'text-xs mb-2 min-h-[1.25rem] text-amber-800';
            return;
          }
          if (!overwatchPageCsrf) {
            feedbackEl.textContent = 'Session expirée : rechargez la page.';
            feedbackEl.className = 'text-xs mb-2 min-h-[1.25rem] text-red-700';
            return;
          }
          feedbackEl.textContent = 'Envoi en cours…';
          feedbackEl.className = 'text-xs mb-2 min-h-[1.25rem] text-slate-600';
          submitBtn.disabled = true;
          fetch(apiBase + '/tenant/access-request', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': overwatchPageCsrf
            },
            credentials: 'include',
            body: JSON.stringify({
              area: 'overwatch',
              reason: reason,
              _csrf_token: overwatchPageCsrf
            })
          })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, status: r.status, data: d }; }); })
            .then(function (res) {
              submitBtn.disabled = false;
              if (res.ok && res.data && res.data.ok) {
                feedbackEl.textContent = 'Demande envoyée aux gestionnaires de la communauté.';
                feedbackEl.className = 'text-xs mb-2 min-h-[1.25rem] text-green-800';
                setTimeout(closeModal, 1800);
                return;
              }
              var msg = (res.data && res.data.error) ? res.data.error : 'Envoi impossible pour le moment.';
              feedbackEl.textContent = msg;
              feedbackEl.className = 'text-xs mb-2 min-h-[1.25rem] text-red-700';
            })
            .catch(function () {
              submitBtn.disabled = false;
              feedbackEl.textContent = 'Erreur réseau. Réessayez.';
              feedbackEl.className = 'text-xs mb-2 min-h-[1.25rem] text-red-700';
            });
        });
      })();
    })();
  </script>
</body>
</html>
