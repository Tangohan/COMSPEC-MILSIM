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
$overwatchMapsList = $overwatchMapsList ?? [['slug' => 'world', 'label' => 'Vue du monde', 'type' => 'world']];
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
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-map-crs.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/nato-sidc-icons.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-unit-popup.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-medical-alerts.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/comspec-operational-map.js"></script>
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/halo-loader.css" rel="stylesheet">
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/atak-map-popups.css" rel="stylesheet">
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/tacmap.css" rel="stylesheet">
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
        <span class="text-xs text-[color:var(--tm-muted)] max-w-[12rem] truncate" id="tacmap-platform-status">Vérification…</span>
        <span class="text-xs text-[color:var(--tm-muted)]" id="tacmap-sync-meta">—</span>
        <button type="button" class="tacmap-btn" id="tacmap-theme-toggle" title="Basculer jour / nuit">Nuit</button>
        <button type="button" class="tacmap-btn" id="tacmap-toggle-left" title="Liste des unités">Effectifs</button>
        <button type="button" class="tacmap-btn" id="tacmap-toggle-right" title="Fiche et urgences">Détail</button>
      </div>

      <div class="tacmap-links">
        <a href="<?= htmlspecialchars(url('overwatch')) ?>">Commandement avancé</a>
        <a href="<?= htmlspecialchars(url('atak')) ?>">ATAK</a>
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
        <div class="tacmap-map-toolbar">
          <label><input type="checkbox" id="tacmap-layer-units" checked /> Unités</label>
          <label><input type="checkbox" id="tacmap-layer-trails" checked /> Tracés de déplacement</label>
          <label><input type="checkbox" id="tacmap-layer-danger" checked /> Zones à signaler</label>
          <label><input type="checkbox" id="tacmap-layer-drawings" checked /> Tracés dessinés</label>
          <label><input type="checkbox" id="tacmap-layer-markers" checked /> Repères</label>
          <label><input type="checkbox" id="tacmap-layer-pings" checked /> Pings</label>
          <label><input type="checkbox" id="tacmap-layer-sigint" /> Veille radio</label>
          <label><input type="checkbox" id="tacmap-layer-intel" /> Indices fusionnés</label>
          <label><input type="checkbox" id="tacmap-layer-air" checked /> Aéronefs</label>
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
            layerAir: 'tacmap-layer-air',
          },
        });
        invalidateMapSoon();
      }
    })();
  </script>
</body>
</html>
