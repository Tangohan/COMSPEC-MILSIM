<?php
$base = url('');
$overwatchContext = $overwatchContext ?? [
    'tenantId' => 0,
    'defaultMapId' => 1,
    'defaultMapSlug' => 'altis',
    'defaultMissionId' => 'mission_0_map_1',
    'apiBase' => rtrim($base, '/') . '/api',
    'syncIntervalMs' => 8000,
];
$overwatchMapsList = $overwatchMapsList ?? [
    ['slug' => 'world', 'label' => 'Vue du monde', 'type' => 'world'],
    ['slug' => 'world_relief', 'label' => 'Relief mondial', 'type' => 'world'],
];
$overwatchWorkspaces = $overwatchWorkspaces ?? [['mapId' => 1, 'label' => 'Principal', 'slug' => 'altis', 'isDefault' => true]];
$overwatchMapsConfigs = $overwatchMapsConfigs ?? [];
$overwatchDefaultMapId = $overwatchDefaultMapId ?? 1;
$overwatchDefaultMapSlug = $overwatchDefaultMapSlug ?? 'altis';
$pageTitle = $title ?? 'TACMAP — Athena';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <script>
    (function () {
      try {
        if (localStorage.getItem('tacmap-theme') === 'night') {
          document.documentElement.classList.add('tacmap-night');
        }
      } catch (e) {}
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    window.ATAK_MARKER_ICONS_CDN = <?= json_encode(function_exists('atak_marker_icons_cdn_base') ? atak_marker_icons_cdn_base() : rtrim($base, '/') . '/assets/markers/arma') ?>;
  </script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-map-crs.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/vendor/milsymbol/milsymbol.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/vendor/milstd/milstd2525.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/milstd-catalog.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/nato-sidc-icons.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/arma-marker-catalog.js?v=202607281250"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/arma-map-markers.js?v=202607281250"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-unit-popup.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-medical-alerts.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/tacmap-terrain-tools.js?v=202607262015"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/tacmap-route-tools.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/tacmap-tactical-alerts.js?v=202607282040"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/tacmap-recon.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/tacmap-weather.js"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/comspec-operational-map.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/halo-loader.css" rel="stylesheet">
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/atak-map-popups.css" rel="stylesheet">
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/tacmap.css?v=202607282040" rel="stylesheet">
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/mission-cycle-badge.css?v=202607270700" rel="stylesheet">
  <script src="<?= htmlspecialchars($base) ?>/assets/js/mission-cycle-badge.js?v=202607270700"></script>
</head>
<body class="tacmap-body antialiased">
  <?php
  $baseUrl = $base;
  $haloLoaderHint = 'Préparation de la carte tactique…';
  require base_path('views/partials/halo_loader.php');
  ?>

  <div class="tacmap-shell">
    <header class="tacmap-header">
      <div class="tacmap-header__brand">
        <h1 class="tacmap-header__title">COMSPEC TACMAP</h1>
        <span id="tacmap-sync-badge" class="tacmap-badge">En attente</span>
        <span id="mission-cycle-badge" class="tacmap-badge" hidden></span>
      </div>

      <div class="tacmap-header__controls">
        <label>
          Serveur / carte
          <select id="tacmap-workspace">
            <?php foreach ($overwatchWorkspaces as $w): ?>
            <option value="<?= (int)($w['mapId'] ?? 1) ?>" <?= !empty($w['isDefault']) ? 'selected' : '' ?>><?= htmlspecialchars($w['label'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Fond
          <select id="tacmap-map-select">
            <?php foreach ($overwatchMapsList as $m): ?>
            <option value="<?= htmlspecialchars($m['slug'] ?? 'world') ?>" <?= ($m['slug'] ?? '') === ($overwatchDefaultMapSlug ?? 'altis') ? 'selected' : '' ?>><?= htmlspecialchars($m['label'] ?? 'Carte') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <span class="tacmap-kpi" id="tacmap-zulu" title="Heure Zulu">—:—:— Z</span>
        <span class="tacmap-kpi" id="tacmap-theatre-label" title="Théâtre">—</span>
        <span class="tacmap-kpi" id="tacmap-unit-count" title="Positions">—</span>
        <span class="tacmap-kpi tacmap-kpi--weather" id="tacmap-weather" title="Météo mission" hidden>—</span>
        <span class="text-xs text-[color:var(--tm-muted)] max-w-[10rem] truncate" id="tacmap-platform-status">Vérification…</span>
        <span class="text-xs text-[color:var(--tm-muted)]" id="tacmap-sync-meta">—</span>
        <button type="button" class="tacmap-btn" id="tacmap-theme-toggle" title="Basculer jour / nuit">Nuit</button>
        <button type="button" class="tacmap-btn" id="tacmap-toggle-left" title="Liste des unités">Effectifs</button>
        <button type="button" class="tacmap-btn" id="tacmap-toggle-right" title="Fiche et urgences">Détail</button>
      </div>

      <div class="tacmap-links">
        <a href="<?= htmlspecialchars(url('overwatch')) ?>">Commandement avancé</a>
        <a href="<?= htmlspecialchars(url('atak')) ?>">ATAK</a>
        <a href="<?= htmlspecialchars(url('back-office/atak/cycle-mission')) ?>">Cycle de mission</a>
        <a href="<?= htmlspecialchars(url('dashboard')) ?>">Tableau de bord</a>
        <?php if (!empty($overwatchCanCreateCustomMaps)): ?>
        <a href="<?= htmlspecialchars(url('overwatch'), ENT_QUOTES, 'UTF-8') ?>#nouvelle-carte">Nouvelle carte image</a>
        <?php endif; ?>
      </div>
    </header>

    <div class="tacmap-body-main" id="tacmap-layout">
      <aside class="tacmap-panel tacmap-panel--left" id="tacmap-panel-left">
        <div class="tacmap-panel__head">
          <p>Effectifs</p>
          <h2>Unités</h2>
          <input type="search" id="tacmap-roster-search" class="tacmap-search" placeholder="Filtrer par indicatif…" autocomplete="off" />
        </div>
        <div class="tacmap-panel__body" id="tacmap-roster"></div>
      </aside>

      <main class="tacmap-map-stage">
        <div class="tacmap-map-toolbar" id="tacmap-map-toolbar">
          <div class="tacmap-map-toolbar__group" aria-label="Calques principaux">
            <label><input type="checkbox" id="tacmap-layer-units" checked /> Unités</label>
            <label><input type="checkbox" id="tacmap-layer-trails" checked /> Tracés</label>
            <label><input type="checkbox" id="tacmap-layer-markers" checked /> Repères</label>
            <label><input type="checkbox" id="tacmap-layer-pings" checked /> Pings</label>
            <label><input type="checkbox" id="tacmap-layer-air" checked /> Aéronefs</label>
          </div>
          <div class="tacmap-map-toolbar__group" aria-label="Calques cTab">
            <span class="tacmap-map-toolbar__tag">cTab</span>
            <label><input type="checkbox" id="tacmap-layer-tactical" checked /> Signalements</label>
            <label><input type="checkbox" id="tacmap-layer-recon" checked /> Photos</label>
          </div>
          <div class="tacmap-map-toolbar__group" aria-label="Autres calques">
            <label><input type="checkbox" id="tacmap-layer-danger" checked /> Zones</label>
            <label><input type="checkbox" id="tacmap-layer-drawings" checked /> Dessins</label>
            <label><input type="checkbox" id="tacmap-layer-sigint" /> Veille radio</label>
            <label><input type="checkbox" id="tacmap-layer-intel" /> Indices</label>
            <label><input type="checkbox" id="tacmap-layer-sse" /> Dossiers SSE</label>
            <label><input type="checkbox" id="tacmap-layer-elevation" checked /> Terrain</label>
            <label><input type="checkbox" id="tacmap-layer-route" checked /> Itinéraire</label>
          </div>
        </div>
        <div class="tacmap-tools-bar" id="tacmap-tools-bar">
          <span class="tacmap-tools-bar__label">Outils</span>
          <div class="tacmap-tools-bar__actions">
            <button type="button" class="tacmap-btn" id="tacmap-tool-viewshed" title="Zone visible depuis un point">Zone visible</button>
            <button type="button" class="tacmap-btn" id="tacmap-tool-heatmap" title="Carte des hauteurs">Hauteurs</button>
            <button type="button" class="tacmap-btn" id="tacmap-tool-route-foot" title="Itinéraire à pied">À pied</button>
            <button type="button" class="tacmap-btn" id="tacmap-tool-route-veh" title="Itinéraire véhicule">Véhicule</button>
            <label class="tacmap-tools-bar__field">Rayon
              <input type="number" id="tacmap-tool-radius" min="100" max="3000" value="500" />
            </label>
            <label class="tacmap-tools-bar__field">Vitesse
              <input type="number" id="tacmap-tool-speed" min="1" max="120" value="5" />
            </label>
            <button type="button" class="tacmap-btn" id="tacmap-tool-clear">Effacer</button>
          </div>
          <span class="tacmap-tools-bar__hint" id="tacmap-tool-hint"></span>
          <span class="tacmap-tools-bar__eta" id="tacmap-tool-eta"></span>
        </div>
        <div class="tacmap-faction-bar" id="tacmap-faction-bar">
          <span class="tacmap-tools-bar__label">Affichage</span>
          <div class="tacmap-tools-bar__actions">
            <label><input type="checkbox" id="tacmap-show-friend" checked /> Alliés</label>
            <label><input type="checkbox" id="tacmap-show-hostile" checked /> Adversaire</label>
            <label><input type="checkbox" id="tacmap-show-unknown" checked /> Indépendants</label>
            <label><input type="checkbox" id="tacmap-show-neutral" checked /> Civils</label>
          </div>
        </div>
        <div id="tacmap-map"></div>

        <div class="tacmap-drawer" id="tacmap-table-drawer">
          <button type="button" class="tacmap-drawer__toggle" id="tacmap-table-toggle">Tableau des positions ▴</button>
          <div class="tacmap-drawer__body">
            <table>
              <thead>
                <tr>
                  <th>Indicatif</th>
                  <th>Rôle</th>
                  <th>Liaison</th>
                  <th>Cap</th>
                  <th>Grille</th>
                </tr>
              </thead>
              <tbody id="tacmap-table-body">
                <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--tm-muted)">Chargement…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </main>

      <aside class="tacmap-panel tacmap-panel--right" id="tacmap-panel-right">
        <div class="tacmap-panel__head">
          <p>Situation</p>
          <h2>Fiche &amp; urgences</h2>
        </div>
        <div class="tacmap-panel__body space-y-4">
          <section>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-[color:var(--tm-muted)] mb-2">Urgences médicales</p>
            <div id="tacmap-medical-list">
              <p class="text-sm text-[color:var(--tm-muted)]">Aucune urgence détectée pour l’instant.</p>
            </div>
          </section>
          <section>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-[color:var(--tm-muted)] mb-2">Signalements (cTab / Athena)</p>
            <div id="tacmap-tactical-list">
              <p class="text-sm text-[color:var(--tm-muted)]">Aucun signalement récent.</p>
            </div>
          </section>
          <section>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-[color:var(--tm-muted)] mb-2">Photos de terrain</p>
            <div id="tacmap-recon-list">
              <p class="text-sm text-[color:var(--tm-muted)]">Aucune photo de terrain récente.</p>
            </div>
          </section>
          <section>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-[color:var(--tm-muted)] mb-2">Unité sélectionnée</p>
            <div id="tacmap-detail">
              <p class="text-sm text-[color:var(--tm-muted)]">Sélectionnez une unité dans la liste ou sur la carte.</p>
            </div>
          </section>
        </div>
      </aside>
    </div>
  </div>

  <script>
    (function () {
      var ctx = <?= json_encode($overwatchContext) ?>;
      var mapsConfigs = <?= json_encode($overwatchMapsConfigs) ?>;
      var workspaces = <?= json_encode($overwatchWorkspaces) ?>;
      var layout = document.getElementById('tacmap-layout');
      var left = document.getElementById('tacmap-panel-left');
      var right = document.getElementById('tacmap-panel-right');
      var themeBtn = document.getElementById('tacmap-theme-toggle');
      var drawer = document.getElementById('tacmap-table-drawer');
      var drawerToggle = document.getElementById('tacmap-table-toggle');

      function invalidateMapSoon() {
        setTimeout(function () {
          if (window.ComspecOperationalMap && ComspecOperationalMap.invalidateTacmapSize) {
            ComspecOperationalMap.invalidateTacmapSize();
          }
        }, 60);
        setTimeout(function () {
          if (window.ComspecOperationalMap && ComspecOperationalMap.invalidateTacmapSize) {
            ComspecOperationalMap.invalidateTacmapSize();
          }
        }, 220);
      }

      function applyThemeButton() {
        var night = document.documentElement.classList.contains('tacmap-night');
        if (themeBtn) themeBtn.textContent = night ? 'Jour' : 'Nuit';
      }
      applyThemeButton();

      if (themeBtn) {
        themeBtn.addEventListener('click', function () {
          var night = document.documentElement.classList.toggle('tacmap-night');
          try { localStorage.setItem('tacmap-theme', night ? 'night' : 'day'); } catch (e) {}
          applyThemeButton();
          invalidateMapSoon();
        });
      }

      function togglePanel(panel, collapsedClass) {
        if (!panel || !layout) return;
        panel.classList.toggle('is-collapsed');
        layout.classList.toggle(collapsedClass, panel.classList.contains('is-collapsed'));
        invalidateMapSoon();
      }

      var btnLeft = document.getElementById('tacmap-toggle-left');
      var btnRight = document.getElementById('tacmap-toggle-right');
      if (btnLeft) btnLeft.addEventListener('click', function () { togglePanel(left, 'side-left-collapsed'); });
      if (btnRight) btnRight.addEventListener('click', function () { togglePanel(right, 'side-right-collapsed'); });

      if (drawerToggle && drawer) {
        drawerToggle.addEventListener('click', function () {
          drawer.classList.toggle('is-open');
          drawerToggle.textContent = drawer.classList.contains('is-open')
            ? 'Tableau des positions ▾'
            : 'Tableau des positions ▴';
          invalidateMapSoon();
        });
      }

      window.addEventListener('resize', invalidateMapSoon);

      if (window.ComspecOperationalMap && ComspecOperationalMap.initTacmap) {
        ComspecOperationalMap.initTacmap({
          containerId: 'tacmap-map',
          context: ctx,
          mapsConfigs: mapsConfigs,
          workspaces: workspaces,
          features: { trails: true, medicalPanel: true },
          els: {
            workspaceSelect: 'tacmap-workspace',
            mapSelect: 'tacmap-map-select',
            zulu: 'tacmap-zulu',
            theatreLabel: 'tacmap-theatre-label',
            syncBadge: 'tacmap-sync-badge',
            platformStatus: 'tacmap-platform-status',
            weatherBanner: 'tacmap-weather',
            unitCount: 'tacmap-unit-count',
            syncMeta: 'tacmap-sync-meta',
            roster: 'tacmap-roster',
            rosterSearch: 'tacmap-roster-search',
            detailRoot: 'tacmap-detail',
            medicalList: 'tacmap-medical-list',
            tableBody: 'tacmap-table-body',
            layerUnits: 'tacmap-layer-units',
            layerTrails: 'tacmap-layer-trails',
            layerDanger: 'tacmap-layer-danger',
            layerDrawings: 'tacmap-layer-drawings',
            layerMarkers: 'tacmap-layer-markers',
            layerPings: 'tacmap-layer-pings',
            layerSigint: 'tacmap-layer-sigint',
            layerIntel: 'tacmap-layer-intel',
            layerSse: 'tacmap-layer-sse',
            layerAir: 'tacmap-layer-air',
            layerTactical: 'tacmap-layer-tactical',
            layerRecon: 'tacmap-layer-recon',
            layerElevation: 'tacmap-layer-elevation',
            layerRoute: 'tacmap-layer-route',
            tacticalList: 'tacmap-tactical-list',
            reconList: 'tacmap-recon-list',
            toolViewshed: 'tacmap-tool-viewshed',
            toolHeatmap: 'tacmap-tool-heatmap',
            toolRouteFoot: 'tacmap-tool-route-foot',
            toolRouteVeh: 'tacmap-tool-route-veh',
            toolClear: 'tacmap-tool-clear',
            toolRadius: 'tacmap-tool-radius',
            toolSpeed: 'tacmap-tool-speed',
            toolHint: 'tacmap-tool-hint',
            toolEta: 'tacmap-tool-eta',
            showFriend: 'tacmap-show-friend',
            showHostile: 'tacmap-show-hostile',
            showUnknown: 'tacmap-show-unknown',
            showNeutral: 'tacmap-show-neutral',
          },
        });
        invalidateMapSoon();
      }

      if (window.MissionCycleBadge) {
        window.overwatchContext = ctx;
        MissionCycleBadge.start({
          badgeId: 'mission-cycle-badge',
          workspaceSelectId: 'tacmap-workspace',
          hubUrl: <?= json_encode(url('back-office/atak/cycle-mission')) ?>,
        });
      }
    })();
  </script>
</body>
</html>
