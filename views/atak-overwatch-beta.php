<?php

declare(strict_types=1);

$base = url('');
$assetVer = platform_app_version();
$owStamp = (string) max(
    (int) @filemtime(dirname(__DIR__) . '/public/assets/css/atak-overwatch-beta.css'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-beta.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-gotak.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-aerial.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-tools.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-gotak.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-ops.js')
);
$owAsset = $assetVer . '.' . $owStamp;
$map = $atakMapConfig ?? null;
$config = is_array($map) ? ($map['config'] ?? []) : [];
$slug = (string) ($map['slug'] ?? 'altis');
$processed = is_array($atakMapsConfigs ?? null) ? ($atakMapsConfigs[$slug] ?? null) : null;
$mapConfig = is_array($processed) ? $processed : atak_with_aerial_layer([
    'slug' => $slug,
    'tilePattern' => atak_resolve_tile_pattern((string) ($map['tile_pattern'] ?? ''), $slug),
    'center' => $config['center'] ?? [15000, 15000],
    'defaultZoom' => (int) ($config['defaultZoom'] ?? 3),
    'minZoom' => (int) ($config['minZoom'] ?? 0),
    'maxZoom' => (int) ($config['maxZoom'] ?? 6),
    'tileSize' => (int) ($config['tileSize'] ?? 212),
    'worldSize' => (int) ($config['worldSize'] ?? 30720),
    'attribution' => $config['attribution'] ?? '&copy; Bohemia Interactive',
    'crs' => $config['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
    'offsetX' => (float) ($config['offset_x'] ?? 0),
    'offsetY' => (float) ($config['offset_y'] ?? 0),
], $slug);
$operator = trim((string) ($atakUserForJs['callsign'] ?? $atakUserForJs['displayName'] ?? 'Opérateur'));
$community = trim((string) ($atakTenantLabel ?? ''));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$icon = static function (string $path): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="' . $path . '"/></svg>';
};
?>
<!doctype html>
<html lang="fr" class="ow-root">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#050505">
  <title>Athena — Overwatch Beta</title>
  <link rel="icon" href="<?= $h($base) ?>/assets/icons/athena-192.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&family=Noto+Sans:wght@400;500;600&family=Source+Sans+3:wght@400;500;600&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.css">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/css/atak-overwatch-beta.css?v=<?= $h($owAsset) ?>">
  <style>.ow-map-tools{display:none!important}</style>
  <script>
    window.ATAK_OVERWATCH_BETA = true;
    window.ATAK_API_BASE = <?= json_encode($base) ?>;
    window.ATAK_TOKEN = <?= json_encode($atakToken ?? '') ?>;
    window.ATAK_TENANT_ID = <?= (int) ($atakTenantId ?? 0) ?>;
    window.ATAK_DEFAULT_MAP_ID = <?= (int) ($atakDefaultMapId ?? 1) ?>;
    window.ATAK_MAP_CONFIG = <?= json_encode($mapConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.ATAK_MAPS_CONFIGS = <?= json_encode(is_array($atakMapsConfigs ?? null) ? $atakMapsConfigs : new stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.ATAK_USER = <?= json_encode($atakUserForJs ?? ['displayName' => $operator, 'callsign' => $operator], JSON_UNESCAPED_UNICODE) ?>;
    window.ATAK_CAPS = <?= json_encode($atakCaps ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.ATAK_TENANT_LABEL = <?= json_encode($community) ?>;
    window.ATAK_CSRF = <?= json_encode(\App\Core\Csrf::token()) ?>;
    window.ATAK_CSRF_TOKEN = window.ATAK_CSRF;
    window.ATAK_MARKER_ICONS_CDN = <?= json_encode(function_exists('atak_marker_icons_cdn_base') ? atak_marker_icons_cdn_base() : rtrim($base, '/') . '/assets/markers/arma') ?>;
  </script>
</head>
<body>
<div class="ow-shell">
  <header class="ow-topbar">
    <a class="ow-brand" href="<?= $h(url('-ATAK-OVERWATCH-Beta')) ?>"><b>A</b><span class="ow-brand-word">ATHENA<small>Comspec / Overwatch Beta</small></span></a>
    <nav class="ow-nav" aria-label="Espaces de travail">
      <button type="button" class="is-active" data-view="overwatch">Overwatch</button>
      <button type="button" data-view="comms">Comms</button>
      <button type="button" data-view="mission">Mission</button>
      <button type="button" data-view="layers">Calques</button>
      <button type="button" data-view="intel">Renseignement</button>
      <button type="button" data-view="tools">Outils</button>
    </nav>
    <div class="ow-more">
      <button type="button" data-ow-more>Plus</button>
      <div class="ow-more-menu" id="ow-more-menu" hidden>
        <button type="button" data-ow-replay>Replay</button>
        <button type="button" data-ow-notes>Bloc-notes</button>
        <button type="button" data-ow-goto>Aller à une grille</button>
        <button type="button" data-ow-panel="intercept">Interception</button>
        <button type="button" data-ow-panel="osint">Notes de terrain</button>
        <button type="button" data-ow-panel="logs">Journal</button>
        <button type="button" data-ow-compact>Carte seule</button>
        <button type="button" data-command>Palette de commandes</button>
      </div>
    </div>
    <button type="button" class="ow-help-btn" data-ow-help title="Aide du poste">?</button>
    <button type="button" class="ow-command" data-command>⌘ K</button>
    <div class="ow-session"><i></i><span><strong><?= $h($operator) ?></strong><small id="ow-link-label">Connexion…</small></span></div>
  </header>

  <div class="ow-statusbar">
    <span class="ow-status-line">
      <b id="ow-community" title="Communauté"><?= $h($community !== '' ? $community : 'Athena') ?></b>
      <span class="ow-status-sep">·</span>
      <b class="ow-mono" title="Indicatif"><?= $h($operator) ?></b>
      <span class="ow-status-sep">·</span>
      <span class="ow-green" id="ow-status-word">Liaison</span>
    </span>
    <div class="ow-statusbar-right">
      <label class="ow-mini ow-sync-rate ow-select">Sync
        <select id="ow-refresh-rate" aria-label="Fréquence de synchronisation">
          <option value="3000">3 s</option>
          <option value="8000">8 s</option>
          <option value="15000">15 s</option>
          <option value="30000">30 s</option>
        </select>
      </label>
    </div>
  </div>

  <main class="ow-workspace">
    <aside class="ow-settings" id="ow-settings" aria-label="Réglages du poste">
      <header>
        <span>●</span>
        <span class="ow-aside-title">Réglages du poste</span>
        <button type="button" class="ow-collapse" data-ow-collapse-settings title="Rabattre les réglages" aria-expanded="true">‹</button>
      </header>
      <div class="ow-settings-body">
        <p class="ow-kicker">Situation</p>
        <div class="ow-stats" id="ow-stats">
          <div class="ow-stat"><b id="ow-stat-contacts">0</b><span>Contacts</span></div>
          <div class="ow-stat"><b id="ow-stat-shapes">0</b><span>Tracés</span></div>
          <div class="ow-stat"><b id="ow-stat-photos">0</b><span>Photos</span></div>
          <div class="ow-stat"><b id="ow-stat-traffic">—</b><span>Débit jeu</span></div>
        </div>
        <p class="ow-help">Remontée jeu → serveur de la communauté. Distinct de la latence du poste (pied de page).</p>
        <div id="ow-traffic-panel" class="ow-traffic-panel">
          <svg class="ow-spark" id="ow-traffic-spark" viewBox="0 0 280 72" aria-hidden="true"></svg>
          <div class="ow-event"><span>Depuis la dernière synchro</span><strong id="ow-traffic-since">Aucune remontée</strong></div>
          <div class="ow-event"><span>Volume 15 min</span><strong id="ow-traffic-window">0 Mo</strong></div>
          <div class="ow-event"><span>Dont photos</span><strong id="ow-traffic-photos">0 Mo</strong></div>
        </div>

        <p class="ow-kicker">Fond de carte</p>
        <fieldset class="ow-looks" id="atak-settings-fond">
          <legend>Calques Atlas</legend>
          <p class="ow-help">Carte du jeu (topographique) ou photo aérienne. Les contacts restent en place.</p>
          <div id="atak-fond-calques-list"></div>
        </fieldset>
        <fieldset class="ow-looks" id="ow-altis-looks">
          <legend>Lecture</legend>
          <label><input type="radio" name="ow-map-look" value="color" data-ow-look checked> <span><strong>Couleur</strong><small>Teintes naturelles</small></span></label>
          <label><input type="radio" name="ow-map-look" value="bw" data-ow-look> <span><strong>Noir et blanc</strong><small>Lecture contrastée</small></span></label>
        </fieldset>

        <p class="ow-kicker">Relief et scène</p>
        <div class="ow-looks" id="atak-settings-relief">
          <label class="ow-toggle" for="atak-terrain-hillshade"><input type="checkbox" id="atak-terrain-hillshade" checked> Ombrage</label>
          <label class="ow-toggle" for="atak-terrain-contours10"><input type="checkbox" id="atak-terrain-contours10" checked> Courbes 10 m</label>
          <label class="ow-toggle" for="atak-terrain-contours50"><input type="checkbox" id="atak-terrain-contours50"> Courbes 50 m</label>
          <label class="ow-toggle" for="atak-terrain-altitudes"><input type="checkbox" id="atak-terrain-altitudes"> Altitudes</label>
          <label class="ow-toggle" for="atak-terrain-slope"><input type="checkbox" id="atak-terrain-slope"> Pentes</label>
          <label class="ow-toggle" for="ow-presence-heat"><input type="checkbox" id="ow-presence-heat"> Chaleur de présence</label>
          <label class="ow-row" for="atak-terrain-opacity">Opacité
            <input type="range" id="atak-terrain-opacity" min="10" max="100" step="5" value="32">
          </label>
          <span class="atak-sound-pref-val" id="atak-terrain-opacity-val">32 %</span>
        </div>
        <div class="ow-looks" id="atak-terrain-3d-settings">
          <label class="ow-row" for="atak-terrain-3d-mode">Vue de la carte
            <select id="atak-terrain-3d-mode">
              <option value="flat" selected>À plat (2D)</option>
              <option value="inclined">Relief 3D</option>
            </select>
          </label>
          <label class="ow-toggle" for="atak-scene-buildings"><input type="checkbox" id="atak-scene-buildings" checked> Bâtiments et forêts du jeu</label>
          <label class="ow-row" for="atak-terrain-exaggeration">Exagération Z
            <input type="range" id="atak-terrain-exaggeration" min="1" max="4" step="0.1" value="2.5">
          </label>
          <span id="atak-terrain-exaggeration-val">2.5×</span>
          <label class="ow-row" for="atak-terrain-pitch">Inclinaison
            <input type="range" id="atak-terrain-pitch" min="25" max="65" step="1" value="48">
          </label>
          <span id="atak-terrain-pitch-val">48°</span>
        </div>
        <div id="atak-settings-map-data">
          <span class="atak-terrain-status" id="atak-terrain-status">Données terrain — aucune couverture</span>
          <div class="atak-terrain-inventory" id="atak-terrain-inventory">
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Ombrage</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-hillshade">Pas encore sur le poste</span></div>
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Relevé divers</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-survey">Pas encore sur le poste</span></div>
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Bâtiments</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-buildings">Pas encore sur le poste</span></div>
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Forêts</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-forests">Pas encore sur le poste</span></div>
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Dernier relevé</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-last">Aucun relevé reçu</span></div>
          </div>
        </div>

        <p class="ow-kicker">Couches</p>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="units" checked> Unités</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="vehicles" checked> Véhicules</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="air" checked> Aérien / drones</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="shapes" checked> Tracés et zones</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="tracks"> Trajectoires</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="arma-markers" id="ow-arma-markers" checked> Marqueurs du théâtre</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-geo-places"> Villes et localités</label>
        <p class="ow-help" id="ow-geo-places-help">Aucun relevé de villes reçu pour ce théâtre.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-geo-roads"> Réseau routier</label>
        <p class="ow-help" id="ow-geo-roads-help">Aucun relevé de routes reçu pour ce théâtre.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-relays-layer" checked> Relais ATAK</label>
        <p class="ow-help" id="ow-relays-help">Aucun relais posé en jeu pour le moment.</p>
        <p class="ow-help" id="ow-relay-mode-help">Par défaut, le téléphone transmet sans relais.</p>
        <p class="ow-kicker">Veille radio</p>
        <div id="ow-df-list"><p class="ow-help">Aucun émetteur relevé pour le moment.</p></div>
        <label class="ow-toggle"><input type="checkbox" id="ow-squad-links" checked> Relier les membres d’un même groupe</label>
        <label class="ow-row">Épaisseur des liens
          <input type="range" id="ow-squad-width" min="0.5" max="2.5" step="0.25" value="0.75">
        </label>
        <label class="ow-toggle"><input type="checkbox" id="ow-squad-hull" checked> Enveloppe de groupe</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-squad-dist"> Distances sur les liens de groupe</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-follow"> Suivre le contact sélectionné</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-look-arrow"> Flèche d’orientation</label>
        <p class="ow-help">Orientation du personnage en jeu, pas la caméra.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-predict"> Anticiper la position</label>
        <p class="ow-help">Trait indicatif sur ~30 s à partir du cap et de la vitesse transmis. Rien n’est inventé si ces données manquent.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-label-grid"> Grille sous l’indicatif</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-po-markers" checked> Points d’objectif (libellé PO) — rayon 20 m</label>
        <p class="ow-help">Un marqueur nommé PO, PO 1 ou PO-2 devient un point d’objectif. Dès qu’un téléphone ATAK entre dans les 20 mètres, le point est confirmé atteint.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-rally-markers" checked> Points de ralliement — rayon 50 m</label>
        <p class="ow-help">Un point de ralliement est un lieu de regroupement. L’anneau vert de 50 mètres apparaît au poste et en jeu. Les opérateurs présents dans le rayon sont indiqués, sans rien inventer.</p>
        <p class="ow-kicker">Anneaux de portée</p>
        <p class="ow-help">Cercles autour du contact ouvert. Distances en mètres sur le théâtre.</p>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="100"> 100 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="250" checked> 250 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="500" checked> 500 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="1000"> 1 000 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="2000"> 2 000 m</label>
        <p class="ow-kicker">Superposition</p>
        <p class="ow-help">Calques posés au-dessus du fond. Chaque option se mémorise sur ce poste.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-grid-overlay"> Grille du théâtre</label>
        <label class="ow-row">Pas de grille
          <span class="ow-select"><select id="ow-grid-step">
            <option value="500">500 m</option>
            <option value="1000" selected>1 000 m</option>
            <option value="2000">2 000 m</option>
          </select></span>
        </label>
        <label class="ow-toggle"><input type="checkbox" id="ow-intel-photos"> Photos de renseignement sur la carte</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-nvg"> Lecture nocturne</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-crosshair-toggle"> Croix au centre</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-scale-bar" checked> Barre d’échelle</label>
        <label class="ow-row">Opacité de la chaleur
          <input type="range" id="ow-heat-opacity" min="10" max="80" step="5" value="40">
        </label>
        <p class="ow-kicker">Personnalisation</p>
        <label class="ow-row">Police de l’interface
          <span class="ow-select"><select id="ow-ui-font">
            <option value="inter" selected>Inter</option>
            <option value="plex">Plex Sans</option>
            <option value="grotesk">Space Grotesk</option>
            <option value="noto">Noto Sans</option>
            <option value="source">Source Sans</option>
            <option value="mono">Plex Mono</option>
          </select></span>
        </label>
        <label class="ow-row">Police des indicatifs
          <span class="ow-select"><select id="ow-label-font">
            <option value="mono">Plex Mono</option>
            <option value="plex">Plex Sans</option>
            <option value="grotesk">Space Grotesk</option>
            <option value="noto">Noto Sans</option>
          </select></span>
        </label>
        <label class="ow-row">Taille du texte
          <span class="ow-select"><select id="ow-ui-size">
            <option value="12">Compacte</option>
            <option value="13" selected>Normale</option>
            <option value="14">Large</option>
            <option value="15">Très large</option>
          </select></span>
        </label>
        <label class="ow-row">Thème
          <span class="ow-select"><select id="ow-theme"><option value="night">Nuit</option><option value="day">Jour</option></select></span>
        </label>
        <label class="ow-row">Symboles
          <span class="ow-select"><select id="ow-marker-style"><option value="diamond">Losange</option><option value="dot">Point</option><option value="nato">Cadre</option></select></span>
        </label>
        <label class="ow-row">Couleur amie
          <input type="color" id="ow-color-friend" value="#00d69a">
        </label>
        <label class="ow-row">Couleur hostile
          <input type="color" id="ow-color-hostile" value="#e05b63">
        </label>
        <label class="ow-row">Taille des indicatifs
          <input type="range" id="ow-label-size" min="9" max="16" value="12">
        </label>
        <label class="ow-row">Taille des icônes
          <input type="range" id="ow-icon-size" min="12" max="28" value="20">
        </label>
        <label class="ow-toggle"><input type="checkbox" id="ow-show-labels" checked> Indicatifs sur la carte</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-look-depth" checked> Relief des symboles</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-look-motion" checked> Animation des contacts</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-look-frame" checked> Cadre d’équipe</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-squad-color"> Couleur de groupe</label>
        <p class="ow-help">Quand c’est coché, le cadre de l’indicatif alterne : couleur du groupe, puis état de liaison, puis groupe, puis état.</p>
        <label class="ow-row">Couleur de dessin
          <input type="color" id="ow-draw-color" value="#00d69a">
        </label>
        <label class="ow-row">Épaisseur de trait
          <input type="range" id="ow-draw-width" min="1" max="8" value="2">
        </label>
        <p class="ow-kicker">Géolocalisation</p>
        <p class="ow-help">Préférences de ce poste uniquement. Elles n’inventent pas de villes ou de routes si le théâtre n’en a pas encore remonté.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-geo-remember" checked> Mémoriser les calques villes et routes</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-geo-labels" checked> Afficher les noms des localités</label>
        <p class="ow-kicker">Liaison ATAK</p>
        <p class="ow-help" id="ow-relay-mode-help">Par défaut, la liaison du téléphone n’exige pas de relais. Un responsable peut activer le passage obligatoire par antenne dans les réglages ATAK de la communauté.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-geofence" checked> Alerte entrée / sortie de zone</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-weather-layer" checked> Overlay météo mission</label>
        <label class="ow-row">Largeur réglages
          <input type="range" id="ow-aside-left" min="240" max="420" value="300">
        </label>
        <label class="ow-row">Largeur tchat
          <input type="range" id="ow-aside-right" min="240" max="420" value="300">
        </label>
        <p class="ow-kicker">Mission</p>
        <button type="button" class="ow-primary" data-overwatch-export>Exporter</button>
        <button type="button" class="ow-secondary" data-overwatch-import>Importer</button>
        <button type="button" class="ow-secondary" data-overwatch-print>Imprimer</button>
        <input type="file" id="overwatch-mission-import" accept="application/json,.json" hidden>
      </div>
    </aside>

    <section class="ow-map-stage" id="ow-map-stage" data-look="color">
      <div class="ow-rail" aria-label="Outils cartographiques">
        <button type="button" class="is-active" data-tool="cursor" data-tip="Sélection" data-help="Cliquez un contact ou un tracé. Échap quitte l’outil en cours."><?= $icon('M5 5h6v6H5zM13 5h6v6h-6zM5 13h6v6H5zM15 15l4 4') ?></button>
        <button type="button" data-tool="center" data-tip="Recentrer" data-help="Recadre le théâtre entier."><?= $icon('M12 3v3M12 18v3M3 12h3M18 12h3M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8z') ?></button>
        <span class="ow-rail-gap"></span>
        <button type="button" data-tool="marker" data-tip="Marqueur" data-help="Un clic pose un repère. L’outil reste actif pour en poser d’autres."><?= $icon('M12 21s7-7 7-12a7 7 0 1 0-14 0c0 5 7 12 7 12z') ?></button>
        <button type="button" data-tool="line" data-tip="Ligne" data-help="Maintenez et glissez, ou cliquez des sommets puis double-clic."><?= $icon('M4 18L20 6') ?></button>
        <button type="button" data-tool="polygon" data-tip="Zone" data-help="Maintenez pour tracer un lasso. Relâchez pour fermer la zone."><?= $icon('M12 3l8 6-3 10H7L4 9z') ?></button>
        <button type="button" data-tool="circle" data-tip="Cercle" data-help="Appuyez au centre, glissez le rayon, relâchez pour poser."><?= $icon('M12 5a7 7 0 1 1 0 14 7 7 0 0 1 0-14z') ?></button>
        <button type="button" data-tool="measure" data-tip="Mesure" data-help="Maintenez du départ à l’arrivée. Distance et cap s’affichent."><?= $icon('M4 12h16M8 8v8M16 8v8') ?></button>
        <span class="ow-rail-gap"></span>
        <button type="button" data-tool="po" data-tip="Point à atteindre" data-help="Cliquez pour poser un point (rayon 20 m). Double-clic termine la série."><?= $icon('M12 3v18M8 8h8') ?></button>
        <button type="button" data-tool="rally" data-tip="Point de ralliement" data-help="Cliquez un lieu de regroupement. Anneau de 50 m au poste et en jeu."><?= $icon('M6 21V4l12 5-12 5') ?></button>
        <button type="button" data-tool="undo" data-tip="Annuler le dernier tracé" data-help="Retire le dernier tracé posé depuis le poste."><?= $icon('M9 10H4V5M4 10c3-6 13-6 16 0') ?></button>
        <button type="button" class="ow-rail-more" data-ow-rail-more data-tip="Autres outils" aria-expanded="false" aria-controls="ow-rail-extra">›</button>
        <div class="ow-rail-extra" id="ow-rail-extra" hidden>
          <button type="button" data-tool="goto" data-tip="Aller à une grille" data-help="Saisissez est / nord ou cliquez un point de la carte."><?= $icon('M12 3l7 7-7 7-7-7z') ?><span>Grille</span></button>
          <button type="button" data-tool="range" data-tip="Anneaux de portée" data-help="Cliquez un centre. Anneaux 100, 250, 500 et 1 000 m."><?= $icon('M12 5a7 7 0 1 1 0 14 7 7 0 1 1 0-14zM12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8z') ?><span>Anneaux</span></button>
          <button type="button" data-ow-nvg data-tip="Lecture nocturne" data-help="Filtre vert sur le fond. Recliquez pour retirer."><?= $icon('M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z') ?><span>Nuit</span></button>
          <button type="button" data-tool="locate" data-tip="Ma position" data-help="Recentre sur le théâtre, pas sur un GPS personnel."><?= $icon('M12 21s7-4.5 7-10a7 7 0 1 0-14 0c0 5.5 7 10 7 10zM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z') ?><span>Théâtre</span></button>
          <button type="button" data-tool="aoi" data-tip="Zone tactique" data-help="Même geste que la zone : lasso au maintien, ou sommets au clic."><?= $icon('M4 6h16v12H4zM8 10h8') ?><span>Zone</span></button>
          <button type="button" data-tool="rect" data-tip="Rectangle" data-help="Appuyez un coin, glissez l’opposé, relâchez."><?= $icon('M5 6h14v12H5z') ?><span>Rectangle</span></button>
          <button type="button" data-tool="freehand" data-tip="Croquis" data-help="Maintenez le clic et dessinez. Relâchez pour enregistrer."><?= $icon('M4 20l4-1 11-11-3-3L5 16z') ?><span>Croquis</span></button>
          <button type="button" data-tool="text" data-tip="Texte" data-help="Cliquez l’emplacement, puis saisissez le libellé."><?= $icon('M5 6h14M12 6v12') ?><span>Texte</span></button>
          <button type="button" data-tool="bearing" data-tip="Cap et distance" data-help="Glissez du premier point au second."><?= $icon('M12 3v18M5 12h14') ?><span>Cap</span></button>
          <button type="button" data-tool="route" data-tip="Route" data-help="Glissez une étape, ou cliquez plusieurs points puis double-clic."><?= $icon('M4 18c4-8 12-8 16 0') ?><span>Route</span></button>
          <button type="button" data-tool="split" data-tip="Découper une zone" data-help="Cliquez une zone, puis tracez la coupe."><?= $icon('M6 6l12 12M9 4h6M9 20h6') ?><span>Coupe</span></button>
          <button type="button" data-tool="eta" data-tip="Temps de parcours" data-help="Glissez le trajet. Temps pied et véhicule à titre indicatif."><?= $icon('M12 6a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM12 9v4l3 2') ?><span>Temps</span></button>
          <button type="button" data-tool="profile" data-tip="Profil d’élévation" data-help="Glissez une coupe. Le relief s’affiche s’il a été relevé."><?= $icon('M3 18l6-8 4 4 8-10') ?><span>Relief</span></button>
          <button type="button" data-tool="los" data-tip="Visée / masque" data-help="Glissez de l’observateur à la cible. Le relief indique si la visée est masquée."><?= $icon('M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z') ?><span>Visée</span></button>
          <button type="button" data-tool="refresh" data-tip="Actualiser" data-help="Relance la synchronisation des contacts et des canaux."><?= $icon('M20 12a8 8 0 1 1-2-5.3M20 4v6h-6') ?><span>Sync</span></button>
        </div>
      </div>
      <div id="ow-map" aria-label="Carte tactique temps réel"></div>
      <div class="ow-coordinate" id="ow-coordinate">Grille · Direct</div>
      <div class="ow-empty" id="ow-empty" hidden role="status">
        <button type="button" class="ow-empty-close" id="ow-empty-close" aria-label="Masquer l’avis" title="Masquer">×</button>
        <b>Aucune télémétrie</b>
        <span>En attente des contacts autorisés pour cette communauté.</span>
        <button type="button" class="ow-empty-dismiss" id="ow-empty-dismiss">Masquer</button>
      </div>
      <div class="ow-context" id="ow-context" hidden>
        <div class="ow-context-head" id="ow-ctx-head">Grille</div>
        <div class="ow-context-group" id="ow-ctx-delete-group" hidden>Élément</div>
        <button type="button" class="ow-ctx-danger" id="ow-ctx-delete" data-ctx="delete" hidden>Supprimer <span>Suppr</span></button>
        <div class="ow-context-group">Poser</div>
        <button type="button" data-ctx="marker">Marqueur du théâtre <span>M</span></button>
        <button type="button" data-ctx="ping">Repère rapide <span>•</span></button>
        <button type="button" data-ctx="po">Point à atteindre (20 m) <span>P</span></button>
        <button type="button" data-ctx="rally">Point de ralliement (50 m) <span>R</span></button>
        <button type="button" data-ctx="aoi">Zone tactique <span>Z</span></button>
        <div class="ow-context-group">Mesurer</div>
        <button type="button" data-ctx="measure">Distance <span></span></button>
        <button type="button" data-ctx="los">Visée / masque <span>V</span></button>
        <div class="ow-context-group">Transmettre</div>
        <button type="button" data-ctx="intel">Observation de terrain <span></span></button>
        <button type="button" data-ctx="sitrep">Compte rendu géolocalisé <span></span></button>
        <button type="button" data-ctx="chatgrid">Envoyer la grille au canal <span></span></button>
        <button type="button" data-ctx="copy">Copier les coordonnées <span></span></button>
      </div>
      <div class="ow-follow-chip" id="ow-follow-chip" hidden>
        <span id="ow-follow-label">Suivi</span>
        <button type="button" id="ow-follow-stop">Arrêter</button>
      </div>
      <div class="ow-timeline" id="ow-timeline" hidden>
        <button type="button" class="ow-replay-play" id="ow-replay-play" aria-label="Lecture">Lecture</button>
        <span class="ow-select"><select id="ow-replay-speed" aria-label="Vitesse">
          <option value="1">1×</option>
          <option value="2">2×</option>
          <option value="4">4×</option>
        </select></span>
        <span class="ow-select"><select id="ow-replay-source" aria-label="Source">
          <option value="session">Session en cours</option>
          <option value="mission">Mission enregistrée</option>
        </select></span>
        <span class="ow-green" id="ow-replay-live">Direct</span>
        <input type="range" id="ow-replay-scrub" min="0" max="100" value="100" aria-label="Rejouer les trajectoires">
        <span id="ow-replay-now">Maintenant</span>
      </div>
      <div class="ow-north" id="ow-north" aria-hidden="true">N</div>
      <div class="ow-live-measure" id="ow-live-measure" hidden></div>
      <div class="ow-data-hud" id="ow-data-hud">Groupes — · Ami 0 · Hostile 0</div>
      <div class="ow-wx" id="ow-wx" hidden></div>
      <div class="ow-toast" id="ow-toast" hidden><small>Athena</small><p id="ow-toast-text"></p></div>
    </section>

    <aside class="ow-chat" id="ow-chat" aria-label="Tchat opérationnel">
      <header><span>●</span> Comms <b id="ow-comms-unread" class="ow-unread" hidden></b></header>
      <div class="ow-tabs" role="tablist">
        <button type="button" class="is-active" data-chat-tab="channels">Canaux <span class="ow-unread" data-unread-tab="channels" hidden></span></button>
        <button type="button" data-chat-tab="contacts">Contacts</button>
        <button type="button" data-chat-tab="squads">Groupes</button>
        <button type="button" data-chat-tab="support">Support <span class="ow-unread" data-unread-tab="support" hidden></span></button>
      </div>
      <div class="ow-chat-main" data-chat-panel="channels">
        <label class="ow-search"><span>⌕</span><input id="ow-channel-filter" type="search" placeholder="Canal ou indicatif…"></label>
        <div id="ow-channel-list" class="ow-channel-list"></div>
        <div class="ow-fil-head">
          <div class="ow-fil-title"><span class="dot"></span> Fil</div>
          <div class="ow-fil-actions">
            <button type="button" class="ow-fil-action" id="ow-chat-purge">Vider le fil</button>
            <label class="ow-fil-toggle" title="Afficher le texte tel qu’il a été reçu"><input type="checkbox" id="ow-chat-raw"> Source</label>
          </div>
        </div>
        <div id="ow-chat-purge-box" class="ow-confirm" hidden></div>
        <div id="ow-chat-log" class="ow-fil" aria-live="polite"></div>
        <div class="ow-fil-legend">
          <span><i style="background:var(--prio-routine)"></i>Routine</span>
          <span><i style="background:var(--prio-priority)"></i>Priorité</span>
          <span><i style="background:var(--prio-flash)"></i>Urgent</span>
        </div>
        <form class="ow-chat-compose" id="ow-chat-form">
          <input id="ow-chat-input" maxlength="500" placeholder="Message sur le canal actif…" autocomplete="off">
          <button type="submit">Envoyer</button>
        </form>
      </div>
      <div class="ow-chat-main" data-chat-panel="contacts" hidden>
        <label class="ow-search"><span>⌕</span><input id="ow-search" type="search" placeholder="Filtrer indicatif, groupe, rôle…"></label>
        <label class="ow-row ow-chat-filter">Afficher
          <span class="ow-select"><select id="ow-side-filter">
            <option value="all">Tous</option>
            <option value="friendly">Amis</option>
            <option value="hostile">Hostiles</option>
            <option value="unknown">Inconnus</option>
          </select></span>
        </label>
        <div class="ow-section-label">Contacts autorisés</div>
        <div id="ow-contact-list" class="ow-contact-list" aria-live="polite"></div>
      </div>
      <div class="ow-chat-main" data-chat-panel="squads" hidden>
        <p class="ow-help">Les opérateurs d’un même groupe sont reliés sur la carte. Cliquez un groupe pour le cadrer.</p>
        <div id="ow-group-task-host"></div>
        <div id="ow-fs-alert-host"></div>
        <div class="ow-section-label">Groupes sur la carte</div>
        <div id="ow-squad-list" class="ow-contact-list" aria-live="polite"></div>
      </div>
      <div class="ow-chat-main" data-chat-panel="support" hidden>
        <p class="ow-help">Assistance technique, séparée des canaux de mission.</p>
        <div id="ow-support-log" class="ow-fil"></div>
        <form class="ow-chat-compose" id="ow-support-form">
          <input id="ow-support-input" maxlength="500" placeholder="Décrire le problème…" autocomplete="off">
          <button type="submit">Signaler</button>
        </form>
      </div>
    </aside>

    <aside class="ow-drawer" id="ow-drawer" hidden>
      <header>
        <div>
          <small id="ow-drawer-kicker">Panneau</small>
          <h2 id="ow-drawer-title">Contact</h2>
        </div>
        <button type="button" class="ow-drawer-close" data-close-drawer aria-label="Fermer">×</button>
      </header>
      <div id="ow-drawer-body"></div>
    </aside>
  </main>

  <footer class="ow-footer">
    <b id="ow-footer-link">Liaison</b>
    <span id="ow-latency">Rx —</span>
    <span id="ow-bft-count">BFT 0</span>
    <span id="ow-map-name"><?= $h(ucfirst($slug)) ?></span>
    <span id="ow-weather-chip">Météo —</span>
    <span id="ow-cache-label" title="État des fonds de carte">Fonds</span>
    <span class="ow-footer-end">Overwatch Beta</span>
  </footer>
</div>

<div class="ow-palette" id="ow-palette" hidden>
  <div>
    <input id="ow-command-input" placeholder="Rechercher une unité, une commande, un outil…">
    <div id="ow-palette-results"></div>
    <p>↑↓ naviguer · Entrée exécuter · Échap fermer</p>
  </div>
</div>

<div class="ow-guide" id="ow-guide" hidden>
  <div class="ow-guide-card" role="dialog" aria-labelledby="ow-guide-title">
    <p class="ow-kicker">Aide du poste</p>
    <h1 id="ow-guide-title">Overwatch Beta</h1>
    <h2>Colonnes</h2>
    <p>À gauche, les fonds, le relief et les couches. Le chevron rabat ce panneau. À droite, les canaux et le fil. Replay et le journal sont dans Plus, en haut. Les outils de tracé rarement utilisés sont derrière la flèche du rail, avec leur nom.</p>
    <h2>Fonds</h2>
    <p>Choisissez la carte du jeu ou la photo aérienne. La lecture couleur ou noir et blanc ne change pas le calque, seulement le contraste.</p>
    <h2>Calques</h2>
    <p>Ombrage, pentes et chaleur de présence s’ajoutent au fond. Bâtiments et forêts n’apparaissent que si un relevé a été reçu pour ce théâtre.</p>
    <h2>Dessin</h2>
    <p>Maintenez le clic pour tracer une zone, un cercle ou une ligne. Relâchez pour poser. L’outil reste actif. Échap ou Sélection pour quitter. Un clic court pose encore un sommet précis. Clic droit sur un tracé, un point ou un repère, puis Supprimer pour le retirer.</p>
    <h2>Réglages</h2>
    <p>Police, taille, grille, lecture nocturne et photos sur la carte se règlent à gauche. Chaque choix reste sur ce poste.</p>
    <h2>Fil</h2>
    <p>Les messages sont groupés par auteur. La barre colorée indique l’urgence. Une pastille signale les messages non lus. Vous pouvez retirer les vôtres, ou vider le fil pour tout le poste.</p>
    <button type="button" class="ow-primary" id="ow-guide-ok">Fermer l’aide</button>
  </div>
</div>

<div class="ow-disclaimer" id="ow-disclaimer" hidden>
  <div class="ow-disclaimer-card" role="dialog" aria-modal="true" aria-labelledby="ow-disclaimer-title">
    <p class="ow-kicker">Athena / Overwatch</p>
    <h1 id="ow-disclaimer-title">Espace de travail en accès anticipé</h1>
    <p>Cette carte de poste affiche uniquement la situation autorisée pour votre compte, transmise par la liaison de la communauté. Ce n’est pas le téléphone emporté en jeu.</p>
    <p>Les positions, messages et photos viennent de la mission en cours. Un rôle plus restreint ne verra pas davantage ici qu’au poste habituel.</p>
    <label class="ow-toggle"><input type="checkbox" id="ow-disclaimer-hide"> Ne plus afficher cet avertissement</label>
    <button type="button" class="ow-primary" id="ow-disclaimer-ok">Entrer dans Overwatch</button>
  </div>
</div>

<script src="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.js"></script>
<script src="<?= $h($base) ?>/assets/js/atak-map-crs.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/nato-sidc-icons.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/arma-marker-catalog.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/arma-map-markers.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-aerial.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-beta.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-tools.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-terrain.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-terrain-3d.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-scene-3d.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-geo-network.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-gotak.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-ops.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-realtime.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-p2.js?v=<?= $h($assetVer) ?>"></script>
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true" focusable="false">
  <defs>
    <pattern id="ow-hatch-diag" patternUnits="userSpaceOnUse" width="8" height="8">
      <path d="M-1,1 l2,-2 M0,8 l8,-8 M7,9 l2,-2" stroke="currentColor" stroke-width="1.2"/>
    </pattern>
  </defs>
</svg>
</body>
</html>
