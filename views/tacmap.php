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
$overwatchMapsList = $overwatchMapsList ?? [['slug' => 'world', 'label' => 'Monde (OpenStreetMap)', 'type' => 'world']];
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
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-map-crs.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/nato-sidc-icons.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/atak-unit-popup.js"></script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/comspec-operational-map.js"></script>
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/halo-loader.css" rel="stylesheet">
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/atak-map-popups.css" rel="stylesheet">
  <style>
    body {
      background:
        radial-gradient(circle at top left, rgba(37,99,235,.08), transparent 30%),
        radial-gradient(circle at bottom right, rgba(14,165,233,.06), transparent 35%),
        #f8fafc;
    }
    #tacmap-map { height: 100%; min-height: 420px; width: 100%; border-radius: 1.25rem; }
  </style>
</head>
<body class="text-slate-900 antialiased">
  <?php
  $baseUrl = $base;
  $haloLoaderHint = 'Préparation de la carte tactique…';
  require base_path('views/partials/halo_loader.php');
  ?>
  <div class="min-h-screen p-4 md:p-6">
    <div class="max-w-[1920px] mx-auto space-y-5">
      <header class="rounded-[2rem] border border-slate-200 bg-white/95 backdrop-blur-xl shadow-sm overflow-hidden">
        <div class="h-1.5 bg-gradient-to-r from-blue-700 via-cyan-500 to-emerald-500"></div>
        <div class="px-5 md:px-8 py-5 flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
          <div class="flex-1 min-w-0">
            <p class="text-[10px] font-black uppercase tracking-[0.4em] text-slate-400 mb-2">Vue tactique — situation en temps réel</p>
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-2xl md:text-4xl font-black tracking-tight uppercase italic text-slate-950">COMSPEC TACMAP</h1>
              <span id="tacmap-sync-badge" class="px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-[0.2em] border-slate-200 bg-slate-50 text-slate-600">En attente</span>
            </div>
            <p class="mt-2 text-sm text-slate-500 max-w-2xl">Carte liée aux positions remontées par votre communauté. Pour le tir indirect, le replay et l’identification ami-ennemi, ouvrez les outils de commandement avancés.</p>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
              <a href="<?= htmlspecialchars(url('overwatch')) ?>" class="inline-flex items-center gap-1 font-bold text-blue-700 hover:text-blue-900 underline decoration-blue-300">Outils de commandement avancés (Overwatch)</a>
              <span class="text-slate-300">|</span>
              <a href="<?= htmlspecialchars(url('atak')) ?>" class="text-slate-600 hover:text-slate-900">ATAK</a>
              <span class="text-slate-300">|</span>
              <a href="<?= htmlspecialchars(url('dashboard')) ?>" class="text-slate-600 hover:text-slate-900">Tableau de bord</a>
            </div>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 w-full xl:max-w-[720px]">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Heure Zulu</p>
              <p class="mt-2 text-lg font-black text-slate-950 font-mono" id="tacmap-zulu">—:—:— Z</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Théâtre affiché</p>
              <p class="mt-2 text-lg font-black text-slate-950 truncate" id="tacmap-theatre-label" title="">—</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Positions suivies</p>
              <p class="mt-2 text-lg font-black text-slate-950" id="tacmap-unit-count">—</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Plateforme</p>
              <p class="mt-2 text-xs font-bold text-slate-700 leading-snug" id="tacmap-platform-status">Vérification…</p>
            </div>
          </div>
        </div>

        <div class="px-5 md:px-8 pb-5 flex flex-wrap items-end gap-4 border-t border-slate-100 pt-4">
          <label class="flex flex-col gap-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Serveur / carte mission</span>
            <select id="tacmap-workspace" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold min-w-[200px]">
              <?php foreach ($overwatchWorkspaces as $w): ?>
              <option value="<?= (int)($w['mapId'] ?? 1) ?>" <?= !empty($w['isDefault']) ? 'selected' : '' ?>><?= htmlspecialchars($w['label'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Fond de carte</span>
            <select id="tacmap-map-select" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold min-w-[200px]">
              <?php foreach ($overwatchMapsList as $m): ?>
              <option value="<?= htmlspecialchars($m['slug'] ?? 'world') ?>" <?= ($m['slug'] ?? '') === ($overwatchDefaultMapSlug ?? 'altis') ? 'selected' : '' ?>><?= htmlspecialchars($m['label'] ?? 'Carte') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php if (!empty($overwatchCanCreateCustomMaps)): ?>
          <a href="<?= htmlspecialchars(url('overwatch'), ENT_QUOTES, 'UTF-8') ?>#nouvelle-carte" class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-bold uppercase tracking-wide text-emerald-950 hover:bg-emerald-100">Nouvelle carte image</a>
          <?php endif; ?>
          <p class="text-xs text-slate-500 flex-1 min-w-[200px]" id="tacmap-sync-meta">—</p>
        </div>
      </header>

      <section class="grid grid-cols-1 xl:grid-cols-[300px_minmax(0,1fr)_320px] gap-5">
        <aside class="rounded-[1.75rem] border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col max-h-[720px] xl:max-h-none">
          <div class="px-5 py-4 border-b border-slate-100">
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-1">Effectifs</p>
            <h2 class="text-lg font-black uppercase italic tracking-tight">Liste des unités</h2>
            <input type="search" id="tacmap-roster-search" placeholder="Filtrer par indicatif…" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" autocomplete="off" />
          </div>
          <div class="p-3 overflow-y-auto flex-1 space-y-2" id="tacmap-roster"></div>
        </aside>

        <main class="rounded-[1.75rem] border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col min-h-0">
          <div class="px-5 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-1">Carte opérationnelle</p>
              <h2 class="text-lg font-black uppercase italic tracking-tight">Situation</h2>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs">
              <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-600">
                <input type="checkbox" id="tacmap-layer-units" class="rounded border-slate-300" checked /> Unités
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-600">
                <input type="checkbox" id="tacmap-layer-danger" class="rounded border-slate-300" checked /> Zones à signaler
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-600">
                <input type="checkbox" id="tacmap-layer-drawings" class="rounded border-slate-300" checked /> Tracés &amp; zones dessinées
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-600">
                <input type="checkbox" id="tacmap-layer-markers" class="rounded border-slate-300" checked /> Repères carte
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-600">
                <input type="checkbox" id="tacmap-layer-pings" class="rounded border-slate-300" checked /> Pings
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-600">
                <input type="checkbox" id="tacmap-layer-sigint" class="rounded border-slate-300" /> Veille radio (zones)
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-600">
                <input type="checkbox" id="tacmap-layer-intel" class="rounded border-slate-300" /> Indices fusionnés
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-600">
                <input type="checkbox" id="tacmap-layer-air" class="rounded border-slate-300" checked /> Aéronefs suivis
              </label>
            </div>
          </div>
          <div class="p-3 md:p-4 flex-1 min-h-[460px]">
            <div id="tacmap-map" class="border border-slate-200 shadow-inner"></div>
          </div>
        </main>

        <aside class="rounded-[1.75rem] border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-slate-100">
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-1">Fiche</p>
            <h2 class="text-lg font-black uppercase italic tracking-tight">Unité sélectionnée</h2>
          </div>
          <div class="p-5 flex-1 overflow-y-auto" id="tacmap-detail">
            <p class="text-sm text-slate-500">Sélectionnez une unité dans la liste ou sur la carte.</p>
          </div>
        </aside>
      </section>

      <section class="rounded-[1.75rem] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-black uppercase tracking-wide text-slate-500">Tableau des positions</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead class="bg-slate-900 text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">
              <tr>
                <th class="px-6 py-4 border-r border-slate-800">Indicatif</th>
                <th class="px-6 py-4 border-r border-slate-800">Rôle</th>
                <th class="px-6 py-4 border-r border-slate-800 text-center">Liaison</th>
                <th class="px-6 py-4 border-r border-slate-800 text-center">Cap</th>
                <th class="px-6 py-4 text-right">Grille (approx.)</th>
              </tr>
            </thead>
            <tbody class="text-slate-900" id="tacmap-table-body">
              <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Chargement…</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>

  <script>
    (function () {
      var ctx = <?= json_encode($overwatchContext) ?>;
      var mapsConfigs = <?= json_encode($overwatchMapsConfigs) ?>;
      var workspaces = <?= json_encode($overwatchWorkspaces) ?>;
      if (window.ComspecOperationalMap && ComspecOperationalMap.initTacmap) {
        ComspecOperationalMap.initTacmap({
          containerId: 'tacmap-map',
          context: ctx,
          mapsConfigs: mapsConfigs,
          workspaces: workspaces,
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
            tableBody: 'tacmap-table-body',
            layerUnits: 'tacmap-layer-units',
            layerDanger: 'tacmap-layer-danger',
            layerDrawings: 'tacmap-layer-drawings',
            layerMarkers: 'tacmap-layer-markers',
            layerPings: 'tacmap-layer-pings',
            layerSigint: 'tacmap-layer-sigint',
            layerIntel: 'tacmap-layer-intel',
            layerAir: 'tacmap-layer-air',
          },
        });
      }
    })();
  </script>
</body>
</html>
