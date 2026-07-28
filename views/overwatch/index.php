<?php
$base = rtrim(url(''), '/');
$apiBase = $base . '/api';
$title = $title ?? 'COMSPEC Overwatch — C2';
$overwatchContext = $overwatchContext ?? [
    'tenantId' => 0,
    'defaultMapId' => 1,
    'defaultMapSlug' => 'world',
    'defaultMissionId' => 'mission_0_map_1',
    'apiBase' => $apiBase,
    'syncIntervalMs' => 8000,
    'assetBase' => $base,
];
$overwatchMapsList = $overwatchMapsList ?? [
    ['slug' => 'world', 'label' => 'Vue du monde', 'type' => 'world'],
    ['slug' => 'world_relief', 'label' => 'Relief mondial', 'type' => 'world'],
];
$overwatchWorkspaces = $overwatchWorkspaces ?? [['mapId' => 1, 'label' => 'Principal', 'slug' => 'altis', 'isDefault' => true]];
$overwatchMapsConfigs = $overwatchMapsConfigs ?? [];
$overwatchDefaultMapId = $overwatchDefaultMapId ?? 1;
$overwatchDefaultMapSlug = $overwatchDefaultMapSlug ?? 'world';
$overwatchDefaultWorkspace = $overwatchDefaultWorkspace ?? ['mapId' => 1, 'label' => 'Principal', 'slug' => 'altis'];
$overwatchPageCsrf = \App\Core\Csrf::token();
$leafletCss = is_file(base_path('public/assets/vendor/leaflet-1.9.4/leaflet.css'))
    ? asset_url('assets/vendor/leaflet-1.9.4/leaflet.css')
    : 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
$leafletJs = is_file(base_path('public/assets/vendor/leaflet-1.9.4/leaflet.js'))
    ? asset_url('assets/vendor/leaflet-1.9.4/leaflet.js')
    : 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= htmlspecialchars($leafletCss, ENT_QUOTES, 'UTF-8') ?>" />
  <script src="<?= htmlspecialchars($leafletJs, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script>
    window.ATAK_MARKER_ICONS_CDN = <?= json_encode(function_exists('atak_marker_icons_cdn_base') ? atak_marker_icons_cdn_base() : ($base . '/assets/markers/arma')) ?>;
  </script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/atak-map-crs.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/vendor/milsymbol/milsymbol.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/vendor/milstd/milstd2525.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/milstd-catalog.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/nato-sidc-icons.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/arma-marker-catalog.js'), ENT_QUOTES, 'UTF-8') ?>?v=202607281250"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/arma-map-markers.js'), ENT_QUOTES, 'UTF-8') ?>?v=202607281250"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/atak-unit-popup.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/atak-medical-alerts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/comspec-operational-map.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <link href="<?= htmlspecialchars(asset_url('assets/css/atak-map-popups.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet" />
  <style>
    :root { --ow-trail: #67e8f9; }
    html, body { height: 100%; margin: 0; overflow: hidden; }
    .panel-tab { display: none; }
    .panel-tab.active { display: block; }
    .atak-medical-item {
      border: 1px solid #334155;
      border-left: 3px solid #f59e0b;
      background: #0f172a;
      border-radius: 0.5rem;
      padding: 0.55rem 0.7rem;
      cursor: pointer;
    }
    .atak-medical-item.atak-medical-critical { border-left-color: #dc2626; background: rgba(220, 38, 38, 0.12); }
    .atak-medical-item.atak-medical-attention { border-left-color: #f59e0b; }
    .atak-medical-item.atak-medical-urgent { border-left-color: #ef4444; }
    .atak-medical-item-title { font-weight: 700; font-size: 0.82rem; color: #f8fafc; }
    .atak-medical-item-label { font-size: 0.78rem; color: #cbd5e1; margin-top: 0.15rem; }
    .atak-medical-item-meta { font-size: 0.7rem; color: #94a3b8; margin-top: 0.2rem; }
    .atak-medical-section-title {
      font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em;
      color: #94a3b8; font-weight: 700; margin-top: 0.5rem;
    }
    .atak-medical-empty { color: #94a3b8; padding: 0.5rem 0; }
    .atak-medical-banner-inner { display: flex; flex-wrap: wrap; gap: 0.5rem 0.75rem; align-items: center; }
    .atak-medical-badge {
      display: inline-flex; align-items: center; padding: 0.1rem 0.45rem; border-radius: 999px;
      background: rgba(254, 226, 226, 0.15); border: 1px solid rgba(254, 202, 202, 0.35);
      font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    }
    #overwatch-map {
      width: 100%;
      height: 100%;
      min-height: 0;
      background: #0b1220;
      border-radius: 0;
      border: 0;
    }
    .overwatch-shell {
      display: flex;
      flex-direction: column;
      height: 100dvh;
      max-height: 100dvh;
      min-height: 0;
      overflow: hidden;
      background: #020617;
      color: #e2e8f0;
    }
    .overwatch-body {
      flex: 1 1 auto;
      min-height: 0;
      display: flex;
      flex-direction: column;
    }
    @media (min-width: 1280px) {
      .overwatch-body { flex-direction: row; }
    }
    .overwatch-map-stage {
      position: relative;
      flex: 1 1 auto;
      min-height: 0;
      min-width: 0;
      height: auto;
      background: #0b1220;
    }
    .overwatch-map-status {
      position: absolute; inset: 0; z-index: 500;
      display: flex; align-items: center; justify-content: center;
      background: rgba(2, 6, 23, 0.88);
      pointer-events: none;
      transition: opacity .25s ease;
      color: #cbd5e1;
    }
    .overwatch-map-status.is-hidden { opacity: 0; visibility: hidden; }
    .leaflet-container { font: inherit; width: 100% !important; height: 100% !important; background: #0b1220; }
    .nato-sidc-icon { background: transparent !important; border: none !important; filter: drop-shadow(0 1px 2px rgba(0,0,0,.8)); }
    #overwatch-ctx-menu {
      position: fixed;
      z-index: 12000;
      min-width: 200px;
      display: none;
      padding: 0.35rem;
      border-radius: 0.75rem;
      border: 1px solid #334155;
      background: #0f172a;
      box-shadow: 0 12px 32px rgba(0,0,0,.45);
      color: #e2e8f0;
    }
    #overwatch-ctx-menu.is-open { display: block; }
    #overwatch-ctx-menu .ow-ctx-label {
      padding: 0.4rem 0.65rem 0.55rem;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      border-bottom: 1px solid #1e293b;
      margin-bottom: 0.25rem;
      max-width: 240px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    #overwatch-ctx-menu button {
      display: block;
      width: 100%;
      text-align: left;
      border: 0;
      border-radius: 0.5rem;
      padding: 0.55rem 0.65rem;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      background: transparent;
      color: #e2e8f0;
    }
    #overwatch-ctx-menu button:hover { background: #1e293b; }
    #overwatch-ctx-menu button.ow-ctx-danger { color: #fca5a5; }
    #overwatch-ctx-menu button.ow-ctx-danger:hover { background: #7f1d1d; color: #fff; }
    /* Header : contraste lisible sur fond sombre (évite text-slate-100 hérité sur fond blanc) */
    .overwatch-header-select {
      color: #0f172a;
      background-color: #fff;
      border-color: #94a3b8;
    }
    .overwatch-header-select option { color: #0f172a; background: #fff; }
    .overwatch-header-nav a {
      color: #ecfdf5;
      background: rgba(6, 78, 59, 0.55);
      border-color: rgba(52, 211, 153, 0.45);
    }
    .overwatch-header-nav a:hover {
      background: rgba(6, 95, 70, 0.85);
      border-color: rgba(110, 231, 183, 0.7);
      color: #fff;
    }
    .ow-roster-btn {
      display: block;
      width: 100%;
      text-align: left;
      border: 1px solid #e2e8f0;
      border-left: 3px solid #059669;
      background: #f8fafc;
      color: #0f172a;
      border-radius: 0.65rem;
      padding: 0.55rem 0.65rem;
      margin-bottom: 0.35rem;
      cursor: pointer;
    }
    .ow-roster-btn:hover,
    .ow-roster-btn.is-selected {
      border-color: #059669;
      box-shadow: 0 0 0 1px #059669;
      background: #ecfdf5;
    }
    .ow-positions-drawer {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 25;
      max-height: 38%;
      display: flex;
      flex-direction: column;
      background: #0f172a;
      border-top: 1px solid #334155;
      box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.35);
      transform: translateY(calc(100% - 2rem));
      transition: transform 0.2s ease;
      color: #e2e8f0;
    }
    .ow-positions-drawer.is-open { transform: translateY(0); }
    .ow-positions-drawer__toggle {
      flex: 0 0 auto;
      width: 100%;
      border: 0;
      background: #1e293b;
      color: #94a3b8;
      font-size: 0.65rem;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      padding: 0.45rem;
      cursor: pointer;
    }
    .ow-positions-drawer__toggle:hover { color: #e2e8f0; }
    .ow-positions-drawer__body {
      flex: 1 1 auto;
      overflow: auto;
      min-height: 0;
    }
    .ow-positions-drawer table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.75rem;
    }
    .ow-positions-drawer th {
      position: sticky;
      top: 0;
      background: #1e293b;
      color: #94a3b8;
      font-size: 0.55rem;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      padding: 0.5rem 0.75rem;
      text-align: left;
      border-bottom: 1px solid #334155;
    }
    .ow-positions-drawer td {
      padding: 0.45rem 0.75rem;
      border-bottom: 1px solid #1e293b;
      color: #e2e8f0;
    }
    .ow-positions-drawer tr[data-unit-id] { cursor: pointer; }
    .ow-positions-drawer tr[data-unit-id]:hover { background: #1e293b; }
    .ow-positions-drawer tr.is-selected { background: rgba(5, 150, 105, 0.2); }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
  <div class="overwatch-shell">
    <header class="flex-shrink-0 border-b border-slate-800 bg-slate-950">
      <div class="h-1.5 bg-gradient-to-r from-emerald-600 via-cyan-500 to-slate-700"></div>
      <div class="px-4 md:px-6 py-4 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="min-w-0">
          <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-300 mb-1">Système de combat connecté</p>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl md:text-3xl font-black tracking-tight uppercase italic text-white">COMSPEC Overwatch</h1>
            <span id="overwatch-sync-badge" class="px-3 py-1 rounded-full border text-[11px] font-bold uppercase tracking-wide border-emerald-500/50 bg-emerald-950 text-emerald-200">En attente</span>
          </div>
          <p class="mt-1.5 text-sm text-slate-300">Image tactique commune : effectifs, urgences médicales et outils de commandement sur une seule carte.</p>
          <p class="mt-1 text-[11px] text-slate-500 uppercase tracking-wide">Carte live · Effectifs &amp; santé · Marqueurs · Urgences</p>
          <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            <span class="text-sm text-slate-200" id="overwatch-theatre-label">—</span>
            <span class="text-sm text-slate-400 font-mono" id="overwatch-mission-id-label">—</span>
            <span class="text-sm text-slate-300 font-mono" id="overwatch-zulu">—:—:— Z</span>
            <span class="text-sm text-emerald-300 font-semibold" id="overwatch-unit-count" title="Effectifs en liaison">0 en liaison</span>
            <span class="text-sm text-slate-300" id="overwatch-sync-indicator">—</span>
          </div>
        </div>
        <div class="flex flex-wrap items-end gap-3">
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold uppercase tracking-wide text-slate-200">Théâtre</span>
            <select id="overwatch-workspace" class="overwatch-header-select rounded-xl border px-3 py-2 text-sm font-semibold min-w-[160px] shadow-sm">
              <?php foreach ($overwatchWorkspaces as $w): ?>
              <option value="<?= (int)($w['mapId'] ?? 1) ?>" <?= !empty($w['isDefault']) ? 'selected' : '' ?>><?= htmlspecialchars($w['label'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold uppercase tracking-wide text-slate-200">Fond de carte</span>
            <select id="overwatch-map-select" class="overwatch-header-select rounded-xl border px-3 py-2 text-sm font-semibold min-w-[180px] shadow-sm">
              <?php foreach ($overwatchMapsList as $m): ?>
              <option value="<?= htmlspecialchars($m['slug'] ?? 'world') ?>" data-type="<?= htmlspecialchars($m['type'] ?? 'arma') ?>" <?= ($m['slug'] ?? '') === ($overwatchDefaultMapSlug ?? 'world') ? 'selected' : '' ?>><?= htmlspecialchars($m['label'] ?? 'Carte') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php if (!empty($overwatchCanCreateCustomMaps)): ?>
          <button type="button" id="overwatch-custom-map-open" class="rounded-xl border border-emerald-400/70 bg-emerald-600 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-500">Nouvelle carte</button>
          <?php endif; ?>
          <button type="button" id="overwatch-access-request-open" class="rounded-xl border border-amber-400/70 bg-amber-500 px-3 py-2 text-xs font-bold uppercase tracking-wide text-amber-950 hover:bg-amber-400">Demander l’accès</button>
          <button type="button" id="overwatch-toggle-positions" class="rounded-xl border border-slate-500 bg-slate-800 px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-100 hover:bg-slate-700" title="Ouvrir le tableau des positions">Positions</button>
          <nav class="overwatch-header-nav flex flex-wrap gap-2 text-sm font-semibold">
            <a href="<?= htmlspecialchars(url('tacmap'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border px-3 py-2">TACMAP</a>
            <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border px-3 py-2">ATAK</a>
            <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border px-3 py-2">Tableau de bord</a>
          </nav>
        </div>
      </div>
    </header>

    <div class="overwatch-body">
      <aside class="overwatch-sidebar-left w-full xl:w-72 flex-shrink-0 border-b xl:border-b-0 xl:border-r border-slate-200 bg-white flex flex-col overflow-hidden min-h-0">
        <div class="p-3 border-b border-slate-100 flex-shrink-0">
          <div class="flex items-center justify-between gap-2 mb-2">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Effectifs en liaison</p>
              <h2 class="text-sm font-black text-slate-900">Unités</h2>
            </div>
            <span class="text-xs font-mono font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-2 py-1" id="overwatch-roster-count">0</span>
          </div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5" for="overwatch-unit-search">Filtrer par indicatif</label>
          <input type="search" id="overwatch-unit-search" placeholder="Indicatif…" autocomplete="off" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto p-2" id="overwatch-roster">
          <p class="text-sm text-slate-500 px-2 py-3">En attente des positions du théâtre…</p>
        </div>
        <div class="border-t border-slate-100 p-3 flex-shrink-0 max-h-[28%] overflow-y-auto" id="overwatch-unit-detail">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">Fiche unité</p>
          <p class="text-sm text-slate-500">Sélectionnez une unité dans la liste ou sur la carte.</p>
        </div>
        <div class="p-3 border-t border-slate-100 flex-shrink-0">
          <p id="overwatch-units-off-map" class="hidden mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">Unités actives hors projection monde.</p>
          <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Calques</h2>
          <div class="space-y-2">
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" id="layer-units" class="rounded border-slate-300 text-emerald-700" checked /> Unités</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-units-count">0</span>
            </label>
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" id="layer-trails" class="rounded border-slate-300 text-emerald-700" checked /> Tracés de déplacement</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-trails-count">—</span>
            </label>
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" id="layer-danger-zones" class="rounded border-slate-300 text-emerald-700" checked /> Zones signalées</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-danger-zones-count">0</span>
            </label>
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" id="layer-fire-support" class="rounded border-slate-300 text-emerald-700" checked /> Appui-feu</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-fire-support-count">0</span>
            </label>
            <label class="flex items-center justify-between gap-2 cursor-pointer">
              <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" id="layer-iff" class="rounded border-slate-300 text-emerald-700" /> Identification</span>
              <span class="text-xs text-slate-400 font-mono" id="layer-iff-count">0</span>
            </label>
          </div>
        </div>
      </aside>

      <main class="flex-1 min-w-0 flex flex-col min-h-0">
        <div class="flex gap-2 flex-wrap items-center px-2 py-1.5 border-b border-slate-800 bg-slate-950/80">
          <button type="button" id="overwatch-measure-btn" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-200 hover:bg-slate-800">Mesure</button>
          <label class="inline-flex items-center gap-2 cursor-pointer rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5">
            <input type="checkbox" id="overwatch-grid-toggle" class="rounded border-slate-300 text-emerald-700" />
            <span class="text-xs font-bold text-slate-300">Grille (A1, B2…)</span>
          </label>
          <span class="text-xs text-slate-500 self-center" id="overwatch-measure-result"></span>
        </div>
        <div class="overwatch-map-stage overflow-hidden">
          <div id="overwatch-map-status" class="overwatch-map-status" role="status">
            <div class="text-center px-4">
              <p class="text-sm font-bold text-slate-100">Chargement de la carte…</p>
              <p class="mt-1 text-xs text-slate-400" id="overwatch-map-status-detail">Initialisation du fond cartographique</p>
            </div>
          </div>
          <div id="overwatch-map"></div>
          <div class="ow-positions-drawer" id="overwatch-table-drawer">
            <button type="button" class="ow-positions-drawer__toggle" id="overwatch-table-toggle">Positions suivies ▴</button>
            <div class="ow-positions-drawer__body">
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
                <tbody id="overwatch-table-body">
                  <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#94a3b8">Chargement…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>

      <aside class="w-full xl:w-[380px] flex-shrink-0 border-t xl:border-t-0 xl:border-l border-slate-200 bg-white flex flex-col overflow-hidden max-h-[70vh] xl:max-h-none">
        <div class="border-b border-slate-100 p-2 flex gap-1 flex-wrap">
          <button type="button" data-tab="fire-support" class="tab-btn px-3 py-2 rounded-lg text-xs font-bold bg-slate-900 text-white">Appui-feu</button>
          <button type="button" data-tab="danger-zones" class="tab-btn px-3 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100">Zones</button>
          <button type="button" data-tab="logistics" class="tab-btn px-3 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100">Logistique</button>
          <button type="button" data-tab="sitrep" class="tab-btn px-3 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100">Situation</button>
          <button type="button" data-tab="replay" class="tab-btn px-3 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100">Relecture</button>
          <button type="button" data-tab="iff" class="tab-btn px-3 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100">Identification</button>
          <button type="button" data-tab="command-chat" class="tab-btn px-3 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100">Tchat</button>
          <button type="button" data-tab="medical" class="tab-btn px-3 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100">Urgences <span id="overwatch-medical-tab-badge" class="ml-1 inline-flex min-w-[1.1rem] h-4 px-1 rounded-full bg-red-600 text-white text-[10px] font-black items-center justify-center" hidden></span></button>
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
              <input type="text" id="command-chat-input" placeholder="Message…" class="flex-1 border border-slate-300 rounded-xl px-3 py-2 text-sm" />
              <button type="button" id="command-chat-send" class="px-3 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold uppercase">Envoyer</button>
            </div>
          </div>
          <div id="panel-medical" class="panel-tab">
            <h2 class="text-lg font-black uppercase tracking-tight mb-2">Urgences médicales</h2>
            <p class="text-xs text-slate-500 mb-3">Alertes transmises depuis le théâtre (combattant au sol, rythme cardiaque à zéro) et unités à secourir.</p>
            <div id="overwatch-medical-banner" class="mb-3 rounded-xl border border-red-200 bg-red-50 text-red-900 text-sm px-3 py-2" hidden></div>
            <div id="overwatch-medical-list" class="space-y-2 text-sm"></div>
          </div>
        </div>
      </aside>
    </div>

    <section class="overwatch-health border-t border-slate-200 bg-white" aria-labelledby="overwatch-health-title">
      <button type="button" id="overwatch-health-toggle" class="w-full px-4 py-2.5 text-left text-sm font-bold text-slate-600 hover:bg-slate-50 flex items-center justify-between" aria-expanded="false" aria-controls="overwatch-health-body">
        <span id="overwatch-health-title">État des liaisons techniques</span>
        <span class="text-slate-400" aria-hidden="true">▼</span>
      </button>
      <div id="overwatch-health-body" class="border-t border-slate-100 overflow-hidden" hidden>
        <div class="px-4 py-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 text-xs">
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Données serveur</span><span id="health-db" class="font-mono font-bold text-slate-700">—</span></div>
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Sync unités</span><span id="health-units" class="font-mono font-bold text-slate-700">—</span></div>
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Appui-feu</span><span id="health-fire-support" class="font-mono font-bold text-slate-700">—</span></div>
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Zones</span><span id="health-danger-zones" class="font-mono font-bold text-slate-700">—</span></div>
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Logistique</span><span id="health-logistics" class="font-mono font-bold text-slate-700">—</span></div>
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Situation</span><span id="health-sitrep" class="font-mono font-bold text-slate-700">—</span></div>
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Identification</span><span id="health-iff" class="font-mono font-bold text-slate-700">—</span></div>
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Relecture</span><span id="health-replay" class="font-mono font-bold text-slate-700">—</span></div>
          <div class="flex justify-between items-center gap-2 p-2 rounded-lg bg-slate-50"><span class="text-slate-600">Tchat</span><span id="health-chat" class="font-mono font-bold text-slate-700">—</span></div>
        </div>
        <div class="px-4 pb-3">
          <button type="button" id="overwatch-health-refresh" class="text-xs text-slate-500 hover:text-slate-800 underline">Actualiser l’état</button>
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

  <div id="overwatch-custom-map-modal" class="hidden fixed inset-0 z-[10050] flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="overwatch-custom-map-title">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 border border-slate-200">
      <h2 id="overwatch-custom-map-title" class="text-lg font-black tracking-tight text-slate-900 mb-1">Nouvelle carte</h2>
      <p class="text-sm text-slate-600 mb-4">Donnez un nom et importez une image (plan, croquis, capture). Elle sera visible par toute la communauté.</p>
      <form id="overwatch-custom-map-form" class="space-y-4">
        <div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1" for="overwatch-custom-map-label">Nom de la carte</label>
          <input id="overwatch-custom-map-label" name="label" type="text" required minlength="2" maxlength="120" placeholder="ex. Zone d’entraînement"
                 class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1" for="overwatch-custom-map-image">Image de fond</label>
          <input id="overwatch-custom-map-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required
                 class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-bold file:uppercase file:text-emerald-900">
          <p class="mt-1.5 text-xs text-slate-500">JPEG, PNG ou WebP — 10 Mo max.</p>
          <img id="overwatch-custom-map-preview" alt="" class="mt-3 hidden max-h-40 w-full rounded-lg object-contain border border-slate-100 bg-slate-50">
        </div>
        <p id="overwatch-custom-map-feedback" class="text-xs min-h-[1.25rem]" role="status"></p>
        <div class="flex flex-wrap gap-2 justify-end">
          <button type="button" id="overwatch-custom-map-cancel" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</button>
          <button type="submit" id="overwatch-custom-map-submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700">Créer la carte</button>
        </div>
      </form>
    </div>
  </div>

  <div id="overwatch-ctx-menu" role="menu" aria-hidden="true">
    <div class="ow-ctx-label" id="overwatch-ctx-label">Élément</div>
    <button type="button" class="ow-ctx-danger" data-ow-ctx="delete" role="menuitem">Supprimer</button>
    <button type="button" data-ow-ctx="cancel" role="menuitem">Annuler</button>
  </div>

<script>
    (function() {
      const overwatchContext = <?= json_encode($overwatchContext) ?>;
      const overwatchMapsList = <?= json_encode($overwatchMapsList) ?>;
      let overwatchWorkspaces = <?= json_encode($overwatchWorkspaces) ?>;
      let overwatchMapsConfigs = <?= json_encode($overwatchMapsConfigs) ?>;
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
          trails: true,
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
          tabBtns.forEach(b => { b.classList.remove('bg-slate-900', 'bg-slate-800', 'text-white'); b.classList.add('text-slate-600'); });
          btn.classList.add('bg-slate-900', 'text-white'); btn.classList.remove('text-slate-600');
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
        trails: null,
        dangerZones: null,
        fireSupport: null,
        drawings: null,
        markers: null,
        iff: null,
        grid: null,
      };
      var unitTrailTracker = (typeof ComspecOperationalMap !== 'undefined' && ComspecOperationalMap.createUnitTrailTracker)
        ? ComspecOperationalMap.createUnitTrailTracker({ maxPoints: 40 })
        : null;
      function clearUnitTrails() {
        if (unitTrailTracker) unitTrailTracker.clear();
        if (layerGroups.trails) layerGroups.trails.clearLayers();
      }
      function renderUnitTrails() {
        if (!unitTrailTracker || !layerGroups.trails) return;
        var color = (typeof ComspecOperationalMap !== 'undefined' && ComspecOperationalMap.trailColorFromCss)
          ? ComspecOperationalMap.trailColorFromCss()
          : '#67e8f9';
        unitTrailTracker.render(layerGroups.trails, !!window.OverwatchState.layers.trails, color);
      }
      var overwatchHealthStatus = { db: '—', units: '—', fireSupport: '—', dangerZones: '—', logistics: '—', sitrep: '—', iff: '—', replay: '—', chat: '—' };
      var overwatchUnitsIntervalId = null;
      var overwatchLastUnits = [];
      var overwatchSelectedUnitId = null;
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

      function mapKind(slug) {
        if (slug === 'world' || slug === 'world_relief') return 'world';
        var c = overwatchMapsConfigs[slug];
        if (c && (c.type === 'image' || c.imageUrl)) return 'image';
        return 'arma';
      }

      function worldTileSpec(slug) {
        if (slug === 'world_relief') {
          return {
            url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
            attribution: '&copy; OpenStreetMap contributors, SRTM | Style: &copy; OpenTopoMap (CC-BY-SA)',
            maxZoom: 17,
            label: 'Relief mondial'
          };
        }
        return {
          url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
          attribution: '&copy; OpenStreetMap',
          maxZoom: 19,
          label: 'Vue du monde'
        };
      }

      function buildImageConfig(raw) {
        if (!raw || !raw.imageUrl) return null;
        if (typeof ComspecOperationalMap !== 'undefined' && ComspecOperationalMap.buildImageConfig) {
          return ComspecOperationalMap.buildImageConfig(raw);
        }
        var w = parseInt(raw.imageWidth, 10) || 1000;
        var h = parseInt(raw.imageHeight, 10) || 1000;
        var bounds = Array.isArray(raw.bounds) && raw.bounds.length === 2 ? raw.bounds : [[0, 0], [h, w]];
        return {
          CRS: L.CRS.Simple,
          imageUrl: raw.imageUrl,
          minZoom: raw.minZoom != null ? raw.minZoom : -2,
          maxZoom: raw.maxZoom != null ? raw.maxZoom : 4,
          defaultZoom: raw.defaultZoom != null ? raw.defaultZoom : 0,
          center: Array.isArray(raw.center) ? raw.center : [h / 2, w / 2],
          bounds: bounds
        };
      }

      function applyBaseLayer(slug, opts) {
        opts = opts || {};
        try {
          if (typeof L === 'undefined') {
            setMapStatus(true, 'Carte indisponible', 'La bibliothèque cartographique n’a pas pu être chargée. Rechargez la page.');
            return;
          }
          var requested = slug || 'world';
          var kind = mapKind(requested);
          var isWorld = kind === 'world';
          var isImage = kind === 'image';
          var mapEl = document.getElementById('overwatch-map');
          if (!mapEl) return;
          setMapStatus(true, 'Chargement de la carte…', isWorld ? worldTileSpec(requested).label : (isImage ? 'Carte image' : ('Carte ' + requested)));

          if (kind === 'arma') {
            var cfgProbe = overwatchMapsConfigs[requested] ? buildArmaConfig(overwatchMapsConfigs[requested]) : null;
            if (!cfgProbe || !cfgProbe.tilePattern) {
              requested = 'world';
              kind = 'world';
              isWorld = true;
              isImage = false;
              var sel = document.getElementById('overwatch-map-select');
              if (sel) sel.value = 'world';
              setMapStatus(true, 'Carte de mission indisponible', 'Affichage de la vue du monde à la place.');
            }
          } else if (kind === 'image') {
            var imgProbe = overwatchMapsConfigs[requested] ? buildImageConfig(overwatchMapsConfigs[requested]) : null;
            if (!imgProbe || !imgProbe.imageUrl) {
              requested = 'world';
              kind = 'world';
              isWorld = true;
              isImage = false;
              var sel2 = document.getElementById('overwatch-map-select');
              if (sel2) sel2.value = 'world';
              setMapStatus(true, 'Carte image indisponible', 'Affichage de la vue du monde à la place.');
            }
          }

          if (map) {
            if (currentBaseLayer) {
              try { map.removeLayer(currentBaseLayer); } catch (e) {}
              currentBaseLayer = null;
            }
            var needRecreate = window.OverwatchState.currentMapType !== kind;
            if (needRecreate) {
              try { map.remove(); } catch (e) {}
              map = null;
              Object.keys(layerGroups).forEach(function (k) { layerGroups[k] = null; });
            }
          }

          if (!map) {
            if (isWorld) {
              map = L.map('overwatch-map', { minZoom: 2, maxZoom: 18, zoomControl: true });
              map.setView([46.6, 2.4], 6);
            } else if (isImage) {
              var icfg = buildImageConfig(overwatchMapsConfigs[requested]);
              if (!icfg) {
                return applyBaseLayer('world');
              }
              map = L.map('overwatch-map', {
                minZoom: icfg.minZoom,
                maxZoom: icfg.maxZoom,
                crs: icfg.CRS,
                zoomControl: true,
                maxBoundsViscosity: 1.0
              });
              map.setView(icfg.center, icfg.defaultZoom);
            } else {
              var cfg = buildArmaConfig(overwatchMapsConfigs[requested]);
              if (!cfg) {
                return applyBaseLayer('world');
              }
              map = L.map('overwatch-map', {
                minZoom: cfg.minZoom,
                maxZoom: cfg.maxZoom,
                crs: cfg.CRS,
                zoomControl: true,
                maxBoundsViscosity: 1.0
              });
              map.setView(cfg.center, cfg.defaultZoom);
              if (cfg.bounds && cfg.bounds.length === 2) {
                map.setMaxBounds(L.latLngBounds(L.latLng(cfg.bounds[0][0], cfg.bounds[0][1]), L.latLng(cfg.bounds[1][0], cfg.bounds[1][1])));
              }
            }
            layerGroups.base = L.layerGroup().addTo(map);
            layerGroups.trails = L.layerGroup().addTo(map);
            layerGroups.units = L.layerGroup().addTo(map);
            layerGroups.dangerZones = L.layerGroup().addTo(map);
            layerGroups.fireSupport = L.layerGroup().addTo(map);
            layerGroups.drawings = L.layerGroup().addTo(map);
            layerGroups.markers = L.layerGroup().addTo(map);
            layerGroups.iff = L.layerGroup().addTo(map);
            layerGroups.grid = L.layerGroup();
            clearUnitTrails();
          }

          if (isWorld) {
            var worldSpec = worldTileSpec(requested);
            currentBaseLayer = L.tileLayer(worldSpec.url, {
              attribution: worldSpec.attribution,
              maxZoom: worldSpec.maxZoom,
              crossOrigin: true
            });
            currentBaseLayer.on('tileerror', function () {
              if (window.OverwatchState._worldFallbackTried) return;
              window.OverwatchState._worldFallbackTried = true;
              try { map.removeLayer(currentBaseLayer); } catch (e) {}
              currentBaseLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap &copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 20
              });
              currentBaseLayer.addTo(map);
            });
            currentBaseLayer.addTo(map);
            map.setView([46.6, 2.4], 6);
            window.OverwatchState.currentMapType = 'world';
            window.OverwatchState.currentMapSlug = requested;
          } else if (isImage) {
            var icfg2 = buildImageConfig(overwatchMapsConfigs[requested]);
            if (!icfg2) {
              return applyBaseLayer('world');
            }
            var ib = L.latLngBounds(L.latLng(icfg2.bounds[0][0], icfg2.bounds[0][1]), L.latLng(icfg2.bounds[1][0], icfg2.bounds[1][1]));
            currentBaseLayer = L.imageOverlay(icfg2.imageUrl, ib, { opacity: 1, interactive: false });
            currentBaseLayer.addTo(map);
            map.fitBounds(ib);
            map.setMaxBounds(ib.pad(0.05));
            window.OverwatchState.currentMapType = 'image';
          } else {
            var cfg2 = buildArmaConfig(overwatchMapsConfigs[requested]);
            if (!cfg2) {
              return applyBaseLayer('world');
            }
            // Ne jamais basculer vers la vue monde sur tileerror : au dézoom,
            // des cases hors couverture Arma échouent souvent sans que la carte soit morte.
            window.OverwatchState._armaTileWarnShown = false;
            currentBaseLayer = L.tileLayer(cfg2.tilePattern, {
              attribution: cfg2.attribution,
              tileSize: cfg2.tileSize,
              minZoom: cfg2.minZoom,
              maxZoom: cfg2.maxZoom,
              noWrap: true,
              bounds: (cfg2.bounds && cfg2.bounds.length === 2)
                ? L.latLngBounds(L.latLng(cfg2.bounds[0][0], cfg2.bounds[0][1]), L.latLng(cfg2.bounds[1][0], cfg2.bounds[1][1]))
                : undefined,
              errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
            });
            var tileErrors = 0;
            currentBaseLayer.on('tileerror', function () {
              tileErrors++;
              if (tileErrors === 12 && !window.OverwatchState._armaTileWarnShown) {
                window.OverwatchState._armaTileWarnShown = true;
                var badgeWarn = document.getElementById('overwatch-sync-badge');
                if (badgeWarn && window.OverwatchState.currentMapType === 'arma') {
                  badgeWarn.title = 'Certaines zones de la carte n’ont pas de détail à ce niveau de zoom.';
                }
              }
            });
            currentBaseLayer.on('load', function () {
              tileErrors = 0;
            });
            currentBaseLayer.addTo(map);
            map.setView(cfg2.center, cfg2.defaultZoom);
            if (cfg2.bounds && cfg2.bounds.length === 2) {
              map.setMaxBounds(L.latLngBounds(L.latLng(cfg2.bounds[0][0], cfg2.bounds[0][1]), L.latLng(cfg2.bounds[1][0], cfg2.bounds[1][1])));
            }
            window.OverwatchState.currentMapType = 'arma';
          }
          window.OverwatchState.currentMapSlug = requested;

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
            overwatchLastUnits = [];
            if (typeof renderRosterAndTable === 'function') renderRosterAndTable([]);
            else updateLayerCounts();
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
          scheduleInvalidate();
          setMapStatus(false);
          var badge = document.getElementById('overwatch-sync-badge');
          if (badge) {
            badge.textContent = isWorld ? worldTileSpec(requested).label : (isImage ? 'Carte image' : 'Carte de mission');
            badge.className = 'px-3 py-1 rounded-full border text-[11px] font-bold uppercase tracking-wide border-emerald-500/50 bg-emerald-950 text-emerald-200';
            badge.removeAttribute('title');
          }
        } catch (err) {
          if (typeof console !== 'undefined' && console.error) console.error('Overwatch applyBaseLayer:', err);
          window.OverwatchState.syncStatus = 'error';
          updateSyncIndicator('error', null);
          setMapStatus(true, 'Erreur de carte', 'Impossible d’afficher cette carte. Réessayez ou choisissez un autre fond.');
          // Pas de bascule auto vers la vue monde : laisse l’opérateur garder / choisir le fond.
        }
      }

      function setMapStatus(show, title, detail) {
        var el = document.getElementById('overwatch-map-status');
        var detailEl = document.getElementById('overwatch-map-status-detail');
        if (!el) return;
        if (title) {
          var titleEl = el.querySelector('p.text-sm');
          if (titleEl) titleEl.textContent = title;
        }
        if (detailEl && detail) detailEl.textContent = detail;
        el.classList.toggle('is-hidden', !show);
      }

      function scheduleInvalidate() {
        if (!map) return;
        setTimeout(function () { try { map.invalidateSize(true); } catch (e) {} }, 50);
        setTimeout(function () { try { map.invalidateSize(true); } catch (e) {} }, 250);
        setTimeout(function () { try { map.invalidateSize(true); } catch (e) {} }, 800);
      }

      window.addEventListener('resize', function () { scheduleInvalidate(); });

function setWorkspace(mapId) {
        clearUnitTrails();
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
        } else if ((window.OverwatchState.currentMapType === 'arma' || window.OverwatchState.currentMapType === 'image') && ws) {
          applyBaseLayer(ws.slug);
        } else {
          refreshOperationalContext();
        }
        updateHeaderLabels();
      }

      function setMap(slug) {
        clearUnitTrails();
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
              onFeatureContextMenu: onOverwatchFeatureContextMenu,
            });
          }
        } catch (e) { if (console && console.error) console.error('renderMapShapes', e); }
        try {
          loadAtakMarkers();
        } catch (e) { if (console && console.error) console.error('loadAtakMarkers', e); }
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
        if (layerGroups.trails) {
          if (layers.trails) { try { layerGroups.trails.addTo(map); } catch (e) {} }
          else { try { map.removeLayer(layerGroups.trails); } catch (e) {} }
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
        renderUnitTrails();
      }

      function updateLayerCounts() {
        var n = window.OverwatchState.unitsCount || 0;
        var el = document.getElementById('layer-units-count');
        if (el) el.textContent = String(n);
        el = document.getElementById('overwatch-roster-count');
        if (el) el.textContent = String(n);
        el = document.getElementById('overwatch-unit-count');
        if (el) el.textContent = n === 1 ? '1 en liaison' : (n + ' en liaison');
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
        if (this.checked) {
          renderUnits(overwatchLastUnits);
        } else if (layerGroups.units) {
          layerGroups.units.clearLayers();
        }
        updateLayerCounts();
      });
      document.getElementById('layer-trails') && document.getElementById('layer-trails').addEventListener('change', function () {
        window.OverwatchState.layers.trails = this.checked;
        applyLayerVisibility();
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
        searchInput.addEventListener('input', function () {
          filterRosterQuery(this.value);
        });
        searchInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            focusUnitByCallsign(this.value);
          }
        });
      }

      var positionsDrawer = document.getElementById('overwatch-table-drawer');
      var positionsToggle = document.getElementById('overwatch-table-toggle');
      var positionsHeaderBtn = document.getElementById('overwatch-toggle-positions');
      function setPositionsDrawerOpen(open) {
        if (!positionsDrawer) return;
        positionsDrawer.classList.toggle('is-open', !!open);
        if (positionsToggle) {
          positionsToggle.textContent = open ? 'Positions suivies ▾' : 'Positions suivies ▴';
        }
        scheduleInvalidate();
      }
      function togglePositionsDrawer() {
        if (!positionsDrawer) return;
        setPositionsDrawerOpen(!positionsDrawer.classList.contains('is-open'));
      }
      if (positionsToggle) positionsToggle.addEventListener('click', togglePositionsDrawer);
      if (positionsHeaderBtn) positionsHeaderBtn.addEventListener('click', togglePositionsDrawer);

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
              var body = (m.body || '').replace(/</g, '&lt;');
              var medical = window.ATAKMedicalAlerts ? window.ATAKMedicalAlerts.parseMessage(m.body || '') : null;
              var wrapCls = medical ? (medical.severity === 'critical' ? 'bg-red-100 border-l-4 border-red-600 pl-2' : 'bg-amber-50 border-l-4 border-amber-500 pl-2') : '';
              return '<div class="flex gap-1 ' + wrapCls + '"><span class="text-slate-400 shrink-0">' + t.substring(11, 19) + '</span><strong>' + (m.author || '?') + '</strong>: ' + body + '</div>';
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
      function loadMedicalAssistances() {
        if (window.ATAKMedicalAlerts) window.ATAKMedicalAlerts.fetchAlerts();
      }
      document.querySelector('[data-tab="medical"]') && document.querySelector('[data-tab="medical"]').addEventListener('click', loadMedicalAssistances);
      if (window.ATAKMedicalAlerts) {
        window.ATAKMedicalAlerts.startPolling(6000);
      }

      var initialMapSlug = (document.getElementById('overwatch-map-select') && document.getElementById('overwatch-map-select').value) || '<?= isset($overwatchDefaultMapSlug) ? addslashes($overwatchDefaultMapSlug) : 'world' ?>';
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
        if (window.OverwatchState.currentMapType === 'arma' || window.OverwatchState.currentMapType === 'image') {
          return { x: e.latlng.lng, y: e.latlng.lat };
        }
        return { x: e.latlng.lat * WORLD_SCALE, y: e.latlng.lng * WORLD_SCALE };
      }

      function statusLabelFr(status) {
        var s = (status || '').toLowerCase();
        if (s === 'linked') return 'En ligne';
        if (s === 'delayed') return 'Signal différé';
        if (s === 'offline') return 'Hors ligne';
        return status || '—';
      }

      function parseUnitExtra(u) {
        try {
          return typeof u.extra === 'string' ? JSON.parse(u.extra) : (u.extra || {});
        } catch (e) {
          return {};
        }
      }

      function healthLabelOw(h) {
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.healthLabelFr) {
          return window.ATAKUnitPopup.healthLabelFr(h);
        }
        var s = String(h || '').toLowerCase();
        if (s === 'ok' || s === 'stable' || s === 'healthy') return 'Stable';
        if (s === 'wounded' || s === 'injured') return 'Blessé';
        if (s === 'unconscious') return 'Inconscient';
        if (s === 'dead' || s === 'kia') return 'Hors combat';
        return h || '—';
      }

      function escapeHtmlOw(s) {
        return String(s == null ? '' : s)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      function unitCoords(u) {
        var gridRef = ((u && u.grid_ref) || '').trim().split(/\s+/);
        var x = parseFloat(gridRef[0]);
        var y = parseFloat(gridRef[1]);
        if (isNaN(x) || isNaN(y)) {
          x = u && u.pos_x != null ? parseFloat(u.pos_x) : NaN;
          y = u && u.pos_y != null ? parseFloat(u.pos_y) : NaN;
        }
        if (isNaN(x) || isNaN(y)) return null;
        return { x: x, y: y, lat: y, lng: x };
      }

      function renderUnitDetail(u) {
        var root = document.getElementById('overwatch-unit-detail');
        if (!root) return;
        if (!u) {
          root.innerHTML = '<p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">Fiche unité</p>' +
            '<p class="text-sm text-slate-500">Sélectionnez une unité dans la liste ou sur la carte.</p>';
          return;
        }
        var ex = parseUnitExtra(u);
        var health = ex.health || u.health || 'ok';
        var fuel = ex.fuel != null && ex.fuel !== '' ? String(ex.fuel) + '%' : '—';
        var battery = ex.battery != null && ex.battery !== '' ? String(ex.battery) + '%' : null;
        var ammo = ex.ammo && ex.ammo !== 'n/a' ? String(ex.ammo) : null;
        root.innerHTML =
          '<p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">Fiche unité</p>' +
          '<p class="text-base font-black text-slate-900">' + escapeHtmlOw(u.call_sign || '—') + '</p>' +
          '<dl class="mt-2 space-y-1 text-xs">' +
          '<div class="flex justify-between gap-2"><dt class="text-slate-500">Rôle</dt><dd class="font-semibold text-slate-800">' + escapeHtmlOw(u.role || ex.role || '—') + '</dd></div>' +
          '<div class="flex justify-between gap-2"><dt class="text-slate-500">Liaison</dt><dd class="font-semibold text-slate-800">' + escapeHtmlOw(statusLabelFr(u.status)) + '</dd></div>' +
          '<div class="flex justify-between gap-2"><dt class="text-slate-500">État</dt><dd class="font-semibold text-slate-800">' + escapeHtmlOw(healthLabelOw(health)) + '</dd></div>' +
          '<div class="flex justify-between gap-2"><dt class="text-slate-500">Cap</dt><dd class="font-semibold text-slate-800">' + escapeHtmlOw(u.heading != null ? String(u.heading) + '°' : '—') + '</dd></div>' +
          '<div class="flex justify-between gap-2"><dt class="text-slate-500">Grille</dt><dd class="font-mono font-semibold text-slate-800">' + escapeHtmlOw(u.grid_ref || '—') + '</dd></div>' +
          '<div class="flex justify-between gap-2"><dt class="text-slate-500">Carburant</dt><dd class="font-semibold text-slate-800">' + escapeHtmlOw(fuel) + '</dd></div>' +
          (battery ? '<div class="flex justify-between gap-2"><dt class="text-slate-500">Batterie</dt><dd class="font-semibold text-slate-800">' + escapeHtmlOw(battery) + '</dd></div>' : '') +
          (ammo ? '<div class="flex justify-between gap-2"><dt class="text-slate-500">Munitions</dt><dd class="font-semibold text-slate-800">' + escapeHtmlOw(ammo) + '</dd></div>' : '') +
          '</dl>';
      }

      function highlightSelectedUnit() {
        var roster = document.getElementById('overwatch-roster');
        if (roster) {
          roster.querySelectorAll('[data-unit-id]').forEach(function (node) {
            node.classList.toggle('is-selected', overwatchSelectedUnitId != null && String(overwatchSelectedUnitId) === node.getAttribute('data-unit-id'));
          });
        }
        var tbody = document.getElementById('overwatch-table-body');
        if (tbody) {
          tbody.querySelectorAll('tr[data-unit-id]').forEach(function (tr) {
            tr.classList.toggle('is-selected', overwatchSelectedUnitId != null && String(overwatchSelectedUnitId) === tr.getAttribute('data-unit-id'));
          });
        }
      }

      function filterRosterQuery(q) {
        q = (q || '').toUpperCase().trim();
        var roster = document.getElementById('overwatch-roster');
        if (roster) {
          roster.querySelectorAll('[data-unit-id]').forEach(function (node) {
            var cs = (node.getAttribute('data-callsign') || '').toUpperCase();
            node.style.display = !q || cs.indexOf(q) >= 0 ? '' : 'none';
          });
        }
        var tbody = document.getElementById('overwatch-table-body');
        if (tbody) {
          tbody.querySelectorAll('tr[data-unit-id]').forEach(function (tr) {
            var cs = (tr.getAttribute('data-callsign') || '').toUpperCase();
            tr.style.display = !q || cs.indexOf(q) >= 0 ? '' : 'none';
          });
        }
      }

      function selectOverwatchUnit(u) {
        if (!u) {
          overwatchSelectedUnitId = null;
          renderUnitDetail(null);
          highlightSelectedUnit();
          return;
        }
        overwatchSelectedUnitId = u.id;
        renderUnitDetail(u);
        highlightSelectedUnit();
        var c = unitCoords(u);
        if (c && map) {
          map.setView([c.lat, c.lng], Math.max(map.getZoom(), 4));
        }
      }

      function renderRosterAndTable(units) {
        overwatchLastUnits = units || [];
        var roster = document.getElementById('overwatch-roster');
        var tbody = document.getElementById('overwatch-table-body');
        if (roster) {
          if (!overwatchLastUnits.length) {
            roster.innerHTML = '<p class="text-sm text-slate-500 px-2 py-3">Aucune position remontée pour ce théâtre. Vérifiez la liaison en jeu.</p>';
          } else {
            roster.innerHTML = overwatchLastUnits.map(function (u) {
              var ex = parseUnitExtra(u);
              var health = ex.health || u.health || 'ok';
              var healthTxt = healthLabelOw(health);
              var fuel = ex.fuel != null && ex.fuel !== '' ? 'Carb. ' + ex.fuel + '%' : null;
              var battery = ex.battery != null && ex.battery !== '' ? 'Batt. ' + ex.battery + '%' : null;
              var metaBits = [healthTxt];
              if (fuel) metaBits.push(fuel);
              if (battery) metaBits.push(battery);
              return '<button type="button" data-unit-id="' + escapeHtmlOw(u.id) + '" data-callsign="' + escapeHtmlOw(u.call_sign || '') + '" class="ow-roster-btn">' +
                '<div class="flex justify-between gap-2 items-start"><span class="text-[10px] font-black uppercase text-slate-500">' + escapeHtmlOw(u.call_sign || '—') + '</span>' +
                '<span class="text-[9px] font-black uppercase text-emerald-700">' + escapeHtmlOw(statusLabelFr(u.status)) + '</span></div>' +
                '<p class="text-sm font-bold mt-1 text-slate-900">' + escapeHtmlOw(u.role || ex.role || 'Rôle non renseigné') + '</p>' +
                '<p class="text-[11px] mt-1 text-slate-600">' + escapeHtmlOw(metaBits.join(' · ')) + '</p>' +
                '<p class="text-xs mt-1 text-slate-500">Grille ' + escapeHtmlOw(u.grid_ref || '—') +
                (u.heading != null ? ' · Cap ' + escapeHtmlOw(String(Math.round(u.heading))) + '°' : '') +
                '</p></button>';
            }).join('');
            roster.querySelectorAll('[data-unit-id]').forEach(function (btn) {
              btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-unit-id');
                var found = overwatchLastUnits.find(function (x) { return String(x.id) === String(id); });
                selectOverwatchUnit(found);
              });
            });
          }
        }
        if (tbody) {
          if (!overwatchLastUnits.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#94a3b8">Aucune unité à afficher.</td></tr>';
          } else {
            tbody.innerHTML = overwatchLastUnits.map(function (u) {
              return '<tr data-unit-id="' + escapeHtmlOw(u.id) + '" data-callsign="' + escapeHtmlOw(u.call_sign || '') + '">' +
                '<td class="font-bold text-emerald-300">' + escapeHtmlOw(u.call_sign || '—') + '</td>' +
                '<td>' + escapeHtmlOw(u.role || '—') + '</td>' +
                '<td>' + escapeHtmlOw(statusLabelFr(u.status)) + '</td>' +
                '<td>' + escapeHtmlOw(u.heading != null ? String(u.heading) + '°' : '—') + '</td>' +
                '<td class="font-mono text-xs text-slate-400">' + escapeHtmlOw(u.grid_ref || '—') + '</td></tr>';
            }).join('');
            tbody.querySelectorAll('tr[data-unit-id]').forEach(function (tr) {
              tr.addEventListener('click', function () {
                var id = tr.getAttribute('data-unit-id');
                var found = overwatchLastUnits.find(function (x) { return String(x.id) === String(id); });
                selectOverwatchUnit(found);
              });
            });
          }
        }
        if (overwatchSelectedUnitId != null) {
          var still = overwatchLastUnits.find(function (x) { return String(x.id) === String(overwatchSelectedUnitId); });
          if (still) renderUnitDetail(still);
          else {
            overwatchSelectedUnitId = null;
            renderUnitDetail(null);
          }
        }
        highlightSelectedUnit();
        filterRosterQuery(document.getElementById('overwatch-unit-search') && document.getElementById('overwatch-unit-search').value);
        updateLayerCounts();
      }

      function affiliationColor(aff) {
        var a = (aff || '').toUpperCase();
        if (a === 'ENEMY' || a === 'HOSTILE') return '#dc2626';
        if (a === 'UNKNOWN' || a === 'SUSPECT') return '#eab308';
        if (a === 'NEUTRAL') return '#22c55e';
        return '#3b82f6';
      }

      function syncUnits() {
        if (window.OverwatchState.currentMapType !== 'arma' && window.OverwatchState.currentMapType !== 'image') {
          window.OverwatchState.unitsCount = 0;
          overwatchLastUnits = [];
          renderRosterAndTable([]);
          if (layerGroups.units) layerGroups.units.clearLayers();
          updateLayerCounts();
          return;
        }
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
            renderRosterAndTable(rows || []);
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

      function renderUnits(units) {
        if (!layerGroups.units || !map) return;
        layerGroups.units.clearLayers();
        if (!window.OverwatchState.layers.units) {
          renderUnitTrails();
          if (window.updateLayerCounts) window.updateLayerCounts();
          return;
        }
        var nato = window.NatoSidcIcons;
        (units || []).forEach(function (u) {
          var c = unitCoords(u);
          if (!c) return;
          var latlng = L.latLng(c.lat, c.lng);
          var unitKey = String(u.id != null ? u.id : (u.call_sign || (c.x + ',' + c.y)));
          if (unitTrailTracker) unitTrailTracker.push(unitKey, latlng);
          var extra = {};
          try {
            if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
            else if (u.extra && typeof u.extra === 'object') extra = u.extra;
          } catch (e) {}
          var aff = extra.affiliation || extra.affil || u.affiliation || 'friend';
          var icon = nato && nato.leafletDivIcon
            ? nato.leafletDivIcon(L, {
                affiliation: aff,
                role: u.role || extra.role || '',
                callSign: u.call_sign || '',
                heading: u.heading,
                showLabel: true,
                size: 34,
              })
            : L.divIcon({
                className: 'overwatch-unit-icon',
                html: '<span style="display:inline-block;padding:2px 6px;background:' + affiliationColor(aff) + ';color:#fff;font-size:10px;border-radius:2px;">' + (u.call_sign || '?') + '</span>',
                iconSize: [80, 24],
                iconAnchor: [40, 12]
              });
          var marker = L.marker(latlng, { icon: icon, zIndexOffset: 400 });
          if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindUnit) {
            window.ATAKUnitPopup.bindUnit(marker, u);
          } else {
            marker.bindPopup('<strong>' + (u.call_sign || '—') + '</strong><br/>' + (u.role || '') + (aff !== 'friend' ? '<br/><em>' + aff + '</em>' : ''));
          }
          marker.on('click', function () { selectOverwatchUnit(u); });
          marker.addTo(layerGroups.units);
        });
        renderUnitTrails();
        if (window.updateLayerCounts) window.updateLayerCounts();
      }

      function focusUnitByCallsign(callsign) {
        var q = (callsign || '').toUpperCase().trim();
        if (!q) return;
        var u = overwatchLastUnits.find(function (x) {
          var cs = (x.call_sign || '').toUpperCase();
          return cs.indexOf(q) >= 0 || q.indexOf(cs) >= 0;
        });
        if (u) selectOverwatchUnit(u);
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
        map.off('contextmenu');
        map.on('contextmenu', function (e) {
          if (L.DomEvent) L.DomEvent.preventDefault(e);
          if (e.originalEvent) e.originalEvent.preventDefault();
        });
        map.on('click', function (e) {
          hideOverwatchCtxMenu();
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
          var activeTab = document.querySelector('.tab-btn.bg-slate-900') || document.querySelector('.tab-btn.bg-slate-800');
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

      var overwatchCtxFeature = null;
      var overwatchCtxMenuEl = document.getElementById('overwatch-ctx-menu');
      var overwatchCtxLabelEl = document.getElementById('overwatch-ctx-label');

      function hideOverwatchCtxMenu() {
        overwatchCtxFeature = null;
        if (!overwatchCtxMenuEl) return;
        overwatchCtxMenuEl.classList.remove('is-open');
        overwatchCtxMenuEl.setAttribute('aria-hidden', 'true');
      }

      function kindLabelFr(kind) {
        if (kind === 'marker') return 'Repère';
        if (kind === 'danger') return 'Zone à signaler';
        return 'Tracé';
      }

      function onOverwatchFeatureContextMenu(payload) {
        if (!payload || payload.id == null || !overwatchCtxMenuEl) return;
        overwatchCtxFeature = {
          kind: payload.kind,
          id: payload.id,
          label: payload.label || '',
        };
        if (overwatchCtxLabelEl) {
          var title = (payload.label || kindLabelFr(payload.kind)).toString();
          overwatchCtxLabelEl.textContent = title;
          overwatchCtxLabelEl.title = title;
        }
        var x = 0;
        var y = 0;
        if (payload.originalEvent) {
          x = payload.originalEvent.clientX;
          y = payload.originalEvent.clientY;
        } else if (payload.latlng && map) {
          var pt = map.latLngToContainerPoint(payload.latlng);
          var rect = map.getContainer().getBoundingClientRect();
          x = rect.left + pt.x;
          y = rect.top + pt.y;
        }
        overwatchCtxMenuEl.style.left = Math.max(8, Math.min(window.innerWidth - 220, x)) + 'px';
        overwatchCtxMenuEl.style.top = Math.max(8, Math.min(window.innerHeight - 120, y)) + 'px';
        overwatchCtxMenuEl.classList.add('is-open');
        overwatchCtxMenuEl.setAttribute('aria-hidden', 'false');
      }

      function deleteOverwatchFeature(feature) {
        if (!feature || feature.id == null) return Promise.resolve(false);
        var kind = feature.kind;
        var id = feature.id;
        var confirmMsg = 'Retirer cet élément de la carte ?';
        if (kind === 'shape') confirmMsg = 'Retirer ce tracé de la carte ?';
        else if (kind === 'marker') confirmMsg = 'Retirer ce repère de la carte ?';
        else if (kind === 'danger') confirmMsg = 'Retirer cette zone de la carte ?';
        if (!window.confirm(confirmMsg)) return Promise.resolve(false);

        var url = '';
        if (kind === 'shape') {
          url = apiBase + '/map-shapes/' + encodeURIComponent(id);
        } else if (kind === 'marker') {
          url = apiBase + '/markers/' + encodeURIComponent(id);
        } else if (kind === 'danger') {
          url = apiBase + '/danger-zones/' + encodeURIComponent(id) + '?missionId=' + encodeURIComponent(getMissionId());
        } else {
          return Promise.resolve(false);
        }

        return fetch(url, { method: 'DELETE', credentials: 'include' })
          .then(function (r) {
            if (!r.ok) throw new Error('delete_failed');
            if (kind === 'danger') loadDangerZones();
            else if (kind === 'marker') loadAtakMarkers();
            else refreshOperationalContext();
            return true;
          })
          .catch(function () {
            window.alert('Impossible de supprimer cet élément pour le moment.');
            return false;
          });
      }

      if (overwatchCtxMenuEl) {
        overwatchCtxMenuEl.addEventListener('click', function (e) {
          var btn = e.target && e.target.closest ? e.target.closest('[data-ow-ctx]') : null;
          if (!btn) return;
          var action = btn.getAttribute('data-ow-ctx');
          if (action === 'cancel') {
            hideOverwatchCtxMenu();
            return;
          }
          if (action === 'delete') {
            var feat = overwatchCtxFeature;
            hideOverwatchCtxMenu();
            deleteOverwatchFeature(feat);
          }
        });
      }
      document.addEventListener('click', function (e) {
        if (!overwatchCtxMenuEl || !overwatchCtxMenuEl.classList.contains('is-open')) return;
        if (e.button != null && e.button !== 0) return;
        if (overwatchCtxMenuEl.contains(e.target)) return;
        hideOverwatchCtxMenu();
      });
      document.addEventListener('contextmenu', function (e) {
        if (!overwatchCtxMenuEl || !overwatchCtxMenuEl.classList.contains('is-open')) return;
        if (overwatchCtxMenuEl.contains(e.target)) return;
        // Nouveau clic droit hors menu : fermer l’ancien (le layer en ouvrira un autre)
        if (!(e.target && e.target.closest && e.target.closest('.leaflet-interactive'))) {
          hideOverwatchCtxMenu();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hideOverwatchCtxMenu();
      });

      function loadAtakMarkers() {
        if (!layerGroups.markers || !map) return;
        if (window.OverwatchState.currentMapType === 'world') {
          layerGroups.markers.clearLayers();
          return;
        }
        var isWorld = false;
        fetch(apiBase + '/markers?mapId=' + encodeURIComponent(window.OverwatchState.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (list) {
            if (typeof ComspecOperationalMap !== 'undefined' && ComspecOperationalMap.renderAtakMarkers) {
              ComspecOperationalMap.renderAtakMarkers(layerGroups.markers, list, isWorld, onOverwatchFeatureContextMenu);
            }
          })
          .catch(function () {});
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
            var bindDel = (typeof ComspecOperationalMap !== 'undefined' && ComspecOperationalMap.bindDeletableLayer)
              ? ComspecOperationalMap.bindDeletableLayer
              : null;
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
                var label = z.label || z.zone_type || 'Zone';
                var layer = L.circle([lat, lng], { radius: radius, color: z.color || '#ef4444', fillOpacity: z.fill_opacity || 0.25 }).bindPopup(label);
                if (bindDel && z.id != null) {
                  bindDel(layer, { kind: 'danger', id: z.id, label: label }, onOverwatchFeatureContextMenu);
                }
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
      function removeSitrepMarker(reportId) {
        var id = String(reportId || '');
        sitrepLayers = sitrepLayers.filter(function (l) {
          if (!l || String(l._sitrepId || '') !== id) return true;
          try {
            if (layerGroups.markers) layerGroups.markers.removeLayer(l);
          } catch (e) {}
          return false;
        });
      }
      function deleteSitrep(reportId) {
        var id = String(reportId || '');
        if (!id) return Promise.reject(new Error('missing'));
        return fetch(apiBase + '/intel/report/' + encodeURIComponent(id) + '?missionId=' + encodeURIComponent(getMissionId()), {
          method: 'DELETE',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ missionId: getMissionId() })
        }).then(function (r) {
          if (!r.ok) throw new Error('delete');
          return true;
        });
      }
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
            if (!reports || reports.length === 0) { el.innerHTML = '<p class="text-slate-500 text-xs">Aucune situation signalée.</p>'; return; }
            var isWorld = window.OverwatchState.currentMapType === 'world';
            var targetLabels = { INFANTRY: 'Infanterie', VEHICLE: 'Véhicule', ARMOR: 'Blindé', AIR_DEFENSE: 'Défense antiaérienne', UNKNOWN: 'Non identifié' };
            var statusLabels = { TEMPORARY: 'Provisoire', CORROBORATED: 'Corroboré', CONFIRMED: 'Confirmé' };
            el.innerHTML = reports.map(function (r) {
              var status = r.status || 'TEMPORARY';
              var color = status === 'CONFIRMED' ? 'bg-red-100 border-red-300' : status === 'CORROBORATED' ? 'bg-amber-100 border-amber-300' : 'bg-yellow-100 border-yellow-300';
              var tLabel = targetLabels[r.target_type] || r.target_type || '?';
              var sLabel = statusLabels[status] || status;
              var rid = String(r.id || '');
              return '<div class="rounded-lg border p-2 ' + color + '" data-sitrep-id="' + rid + '">' +
                '<div class="font-bold">' + tLabel + ' — ' + sLabel + '</div>' +
                '<div class="text-xs">' + (r.merged_count || 1) + ' source(s) · ' + (r.source_callsign || '?') + '</div>' +
                (rid
                  ? '<div class="mt-1 text-right"><button type="button" class="text-xs font-bold text-red-700 hover:underline" data-delete-sitrep="' + rid + '">Supprimer</button></div>'
                  : '') +
                '</div>';
            }).join('');
            overwatchHealthStatus.sitrep = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
            reports.forEach(function (r) {
              var lat = isWorld ? (r.pos_y != null ? r.pos_y : 0) / WORLD_SCALE : (r.pos_y != null ? r.pos_y : 0);
              var lng = isWorld ? (r.pos_x != null ? r.pos_x : 0) / WORLD_SCALE : (r.pos_x != null ? r.pos_x : 0);
              var status = r.status || 'TEMPORARY';
              var col = status === 'CONFIRMED' ? '#dc2626' : status === 'CORROBORATED' ? '#d97706' : '#eab308';
              var tLabel = targetLabels[r.target_type] || r.target_type || '?';
              var sLabel = statusLabels[status] || status;
              var layer = L.circleMarker([lat, lng], { radius: 10, color: col, fillOpacity: 0.8 }).bindPopup(tLabel + ' — ' + sLabel);
              layer._sitrepId = String(r.id || '');
              if (layerGroups.markers) layer.addTo(layerGroups.markers);
              sitrepLayers.push(layer);
            });
          })
          .catch(function () { overwatchHealthStatus.sitrep = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); el.innerHTML = '<p class="text-slate-500 text-xs">Erreur.</p>'; });
      }
      document.querySelector('[data-tab="sitrep"]')?.addEventListener('click', loadSitrep);
      loadSitrep();
      (function bindSitrepDelete() {
        var el = document.getElementById('sitrep-list');
        if (!el || el._sitrepDeleteBound) return;
        el._sitrepDeleteBound = true;
        el.addEventListener('click', function (e) {
          var btn = e.target.closest('[data-delete-sitrep]');
          if (!btn) return;
          e.preventDefault();
          e.stopPropagation();
          var id = btn.getAttribute('data-delete-sitrep');
          if (!id) return;
          if (!window.confirm('Supprimer ce signalement ?')) return;
          btn.disabled = true;
          deleteSitrep(id).then(function () {
            removeSitrepMarker(id);
            var row = el.querySelector('[data-sitrep-id="' + id + '"]');
            if (row) row.remove();
            if (!el.querySelector('[data-sitrep-id]')) {
              el.innerHTML = '<p class="text-slate-500 text-xs">Aucune situation signalée.</p>';
            }
          }).catch(function () {
            btn.disabled = false;
            window.alert('Impossible de supprimer ce signalement.');
          });
        });
      })();
      document.getElementById('sitrep-ops-submit') && document.getElementById('sitrep-ops-submit').addEventListener('click', function () {
        var missionId = getMissionId();
        var target = document.getElementById('sitrep-ops-target');
        var x = document.getElementById('sitrep-ops-x');
        var y = document.getElementById('sitrep-ops-y');
        var source = document.getElementById('sitrep-ops-source');
        var posX = x ? parseFloat(x.value) : NaN;
        var posY = y ? parseFloat(y.value) : NaN;
        if (isNaN(posX) || isNaN(posY)) {
          window.alert('Indiquez les positions Est et Nord du signalement.');
          return;
        }
        var payload = {
          missionId: missionId,
          target_type: target ? target.value.trim() || 'UNKNOWN' : 'UNKNOWN',
          pos_x: posX,
          pos_y: posY,
          source_callsign: source ? source.value.trim() || 'PC' : 'PC',
          report_type: 'SITREP'
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
            loadReplayAar();
          })
          .catch(function () { overwatchHealthStatus.replay = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); var el = document.getElementById('replay-info'); if (el) el.textContent = 'Erreur chargement.'; });
      }
      function loadReplayAar() {
        var box = document.getElementById('replay-aar');
        if (box) box.textContent = 'Analyse AAR en cours…';
        fetch(apiBase + '/replay/aar/' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!box) return;
            var s = data.summary || {};
            var errors = data.errors || [];
            var line = [];
            line.push('<p><strong>Unités</strong> : ' + (s.unitCount || 0) + '</p>');
            line.push('<p><strong>Instantanés de position</strong> : ' + (s.positionSamples || 0) + '</p>');
            line.push('<p><strong>Signalements</strong> : ' + (s.intelEvents || 0) + '</p>');
            line.push('<p><strong>Délai médian de réaction</strong> : ' + (s.medianReactionDelaySeconds != null ? s.medianReactionDelaySeconds + ' s' : 'Non disponible') + '</p>');
            if (errors.length > 0) {
              line.push('<p class="mt-2"><strong>Anomalies détectées</strong> :</p><ul class="list-disc pl-4">');
              errors.forEach(function (e) {
                line.push('<li>' + (e.label || e.code || 'Anomalie') + ' (' + (e.count || 0) + ')</li>');
              });
              line.push('</ul>');
            } else {
              line.push('<p class="mt-2 text-emerald-700">Aucune anomalie automatique détectée.</p>');
            }
            box.innerHTML = line.join('');
          })
          .catch(function () {
            if (box) box.textContent = 'Erreur chargement analyse AAR.';
          });
      }
      document.querySelector('[data-tab="replay"]')?.addEventListener('click', loadReplay);
      document.getElementById('replay-aar-refresh')?.addEventListener('click', loadReplayAar);
      document.getElementById('replay-aar-export')?.addEventListener('click', function () {
        var url = apiBase + '/replay/aar/' + encodeURIComponent(getMissionId()) + '/export.pdf';
        window.open(url, '_blank');
      });
      loadReplay();

      function loadIff() {
        fetch(apiBase + '/iff/current?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (c) {
            var codeEl = document.getElementById('iff-challenge-code');
            var validEl = document.getElementById('iff-valid-until');
            if (codeEl) codeEl.textContent = c.code || '—';
            if (validEl) {
              if (c.valid_until) {
                var untilTs = Date.parse(String(c.valid_until).replace(' ', 'T'));
                var rem = !isNaN(untilTs) ? Math.floor((untilTs - Date.now()) / 1000) : null;
                if (rem != null && rem <= 0) {
                  validEl.textContent = 'Défi expiré depuis ' + c.valid_until + '. Publiez-en un nouveau.';
                } else if (rem != null) {
                  var mins = Math.ceil(rem / 60);
                  validEl.textContent = 'Valable jusqu’à ' + c.valid_until + ' (expire dans ' + mins + ' min)';
                } else {
                  validEl.textContent = 'Valable jusqu’à ' + c.valid_until;
                }
              } else {
                validEl.textContent = 'Aucun défi actif.';
              }
            }
          });
        fetch(apiBase + '/iff/assets?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (assets) {
            overwatchHealthStatus.iff = 'OK';
            if (window.refreshHealthPanel) refreshHealthPanel();
            var el = document.getElementById('iff-assets-list');
            var alertEl = document.getElementById('iff-alert-banner');
            if (!el) return;
            if (!assets || assets.length === 0) { el.innerHTML = '<p class="text-slate-500 text-xs">Aucune unité inscrite.</p>'; if (alertEl) { alertEl.classList.add('hidden'); alertEl.innerHTML = ''; } return; }
            var statusLabels = { FRIENDLY: 'Ami confirmé', SUSPECT: 'Suspect', EXPIRED: 'Défi expiré', PENDING: 'En attente', UNKNOWN: 'Contact inconnu' };
            var alerts = assets.filter(function (a) {
              var st = a.response_status || '';
              return st === 'UNKNOWN' || st === 'SUSPECT' || st === 'EXPIRED';
            });
            if (alertEl) {
              if (alerts.length) {
                alertEl.classList.remove('hidden');
                alertEl.innerHTML = '<strong>Attention :</strong> ' + alerts.map(function (a) {
                  return (a.callsign || a.asset_id) + ' — ' + (statusLabels[a.response_status] || a.response_status);
                }).join(' · ');
              } else {
                alertEl.classList.add('hidden');
                alertEl.innerHTML = '';
              }
            }
            el.innerHTML = assets.map(function (a) {
              var st = a.response_status || 'PENDING';
              var color = st === 'FRIENDLY' ? 'bg-blue-100 border-blue-300' : st === 'SUSPECT' || st === 'UNKNOWN' ? 'bg-red-100 border-red-300' : st === 'EXPIRED' ? 'bg-amber-100 border-amber-300' : 'bg-slate-100 border-slate-300';
              var grace = '';
              if (st === 'PENDING' && a.grace_remaining_sec != null) {
                grace = ' · délai ' + Math.max(0, Math.ceil(a.grace_remaining_sec / 60)) + ' min';
              }
              return '<div class="rounded-lg border p-2 ' + color + '"><span class="font-bold">' + (a.callsign || a.asset_id) + '</span> — ' + (statusLabels[st] || st) + grace + '</div>';
            }).join('');
          })
          .catch(function () { overwatchHealthStatus.iff = 'Erreur'; if (window.refreshHealthPanel) refreshHealthPanel(); });
      }
      document.querySelector('[data-tab="iff"]')?.addEventListener('click', loadIff);
      loadIff();
      document.getElementById('iff-generate') && document.getElementById('iff-generate').addEventListener('click', function () {
        var codeEl = document.getElementById('iff-new-code');
        var minsEl = document.getElementById('iff-valid-minutes');
        var fb = document.getElementById('iff-feedback');
        var code = codeEl ? String(codeEl.value || '').trim() : '';
        var mins = minsEl ? parseInt(minsEl.value, 10) : 30;
        if (fb) fb.textContent = 'Publication…';
        fetch(apiBase + '/iff/challenge', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            missionId: getMissionId(),
            code: code || undefined,
            validMinutes: mins
          })
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
            if (fb) {
              fb.textContent = res.ok
                ? ('Défi publié' + (res.data && res.data.code ? ' : ' + res.data.code : '') + '.')
                : ((res.data && res.data.message) || 'Publication impossible.');
            }
            if (codeEl && res.ok) codeEl.value = '';
            loadIff();
          })
          .catch(function () { if (fb) fb.textContent = 'Erreur réseau.'; });
      });

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

      (function setupCustomMapCreate() {
        var modal = document.getElementById('overwatch-custom-map-modal');
        var openBtn = document.getElementById('overwatch-custom-map-open');
        var cancelBtn = document.getElementById('overwatch-custom-map-cancel');
        var form = document.getElementById('overwatch-custom-map-form');
        var labelEl = document.getElementById('overwatch-custom-map-label');
        var imageEl = document.getElementById('overwatch-custom-map-image');
        var previewEl = document.getElementById('overwatch-custom-map-preview');
        var feedbackEl = document.getElementById('overwatch-custom-map-feedback');
        var submitBtn = document.getElementById('overwatch-custom-map-submit');
        var mapSelect = document.getElementById('overwatch-map-select');
        var wsSelect = document.getElementById('overwatch-workspace');
        if (!modal || !openBtn || !form) return;

        function closeModal() {
          modal.classList.add('hidden');
          if (feedbackEl) {
            feedbackEl.textContent = '';
            feedbackEl.className = 'text-xs min-h-[1.25rem]';
          }
        }
        function openModal() {
          form.reset();
          if (previewEl) {
            previewEl.classList.add('hidden');
            previewEl.removeAttribute('src');
          }
          if (feedbackEl) feedbackEl.textContent = '';
          modal.classList.remove('hidden');
          if (labelEl) labelEl.focus();
        }
        openBtn.addEventListener('click', openModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
          if (e.target === modal) closeModal();
        });
        if (window.location.hash === '#close') {
          try { history.replaceState(null, '', window.location.pathname + window.location.search); } catch (e) {}
        }
        if (window.location.hash === '#nouvelle-carte') {
          setTimeout(openModal, 200);
        }
        if (imageEl && previewEl) {
          imageEl.addEventListener('change', function () {
            var f = imageEl.files && imageEl.files[0];
            if (!f) {
              previewEl.classList.add('hidden');
              return;
            }
            var url = URL.createObjectURL(f);
            previewEl.src = url;
            previewEl.classList.remove('hidden');
          });
        }

        form.addEventListener('submit', function (e) {
          e.preventDefault();
          var csrf = overwatchContext.csrfToken || overwatchPageCsrf || '';
          if (!csrf) {
            if (feedbackEl) {
              feedbackEl.textContent = 'Session expirée. Rechargez la page.';
              feedbackEl.className = 'text-xs min-h-[1.25rem] text-red-700';
            }
            return;
          }
          var fd = new FormData();
          fd.append('_csrf_token', csrf);
          fd.append('label', labelEl ? labelEl.value.trim() : '');
          if (imageEl && imageEl.files && imageEl.files[0]) {
            fd.append('image', imageEl.files[0]);
          }
          if (submitBtn) submitBtn.disabled = true;
          if (feedbackEl) {
            feedbackEl.textContent = 'Création en cours…';
            feedbackEl.className = 'text-xs min-h-[1.25rem] text-slate-600';
          }
          fetch(apiBase + '/custom-maps', {
            method: 'POST',
            body: fd,
            credentials: 'include'
          })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; }); })
            .then(function (res) {
              if (submitBtn) submitBtn.disabled = false;
              if (!res.ok || !res.data || !res.data.map) {
                var msg = (res.data && res.data.error) ? res.data.error : 'Création impossible.';
                if (feedbackEl) {
                  feedbackEl.textContent = msg;
                  feedbackEl.className = 'text-xs min-h-[1.25rem] text-red-700';
                }
                return;
              }
              var m = res.data.map;
              overwatchMapsConfigs[m.slug] = {
                mapId: m.mapId,
                slug: m.slug,
                label: m.label,
                type: 'image',
                imageUrl: m.imageUrl,
                imageWidth: m.imageWidth,
                imageHeight: m.imageHeight,
                center: m.center,
                bounds: m.bounds,
                defaultZoom: m.defaultZoom,
                minZoom: m.minZoom,
                maxZoom: m.maxZoom
              };
              overwatchWorkspaces.push({
                mapId: m.mapId,
                label: m.label,
                slug: m.slug,
                isDefault: false,
                type: 'image'
              });
              if (mapSelect) {
                var opt = document.createElement('option');
                opt.value = m.slug;
                opt.textContent = m.label;
                opt.setAttribute('data-type', 'image');
                mapSelect.appendChild(opt);
                mapSelect.value = m.slug;
              }
              if (wsSelect) {
                var wopt = document.createElement('option');
                wopt.value = String(m.mapId);
                wopt.textContent = m.label;
                wsSelect.appendChild(wopt);
                wsSelect.value = String(m.mapId);
              }
              window.OverwatchState.currentMapId = m.mapId;
              window.OverwatchState.currentMissionId = buildMissionId(overwatchContext.tenantId, m.mapId);
              closeModal();
              applyBaseLayer(m.slug);
              updateHeaderLabels();
            })
            .catch(function () {
              if (submitBtn) submitBtn.disabled = false;
              if (feedbackEl) {
                feedbackEl.textContent = 'Erreur réseau. Réessayez.';
                feedbackEl.className = 'text-xs min-h-[1.25rem] text-red-700';
              }
            });
        });
      })();
    })();
  </script>
</body>
</html>
