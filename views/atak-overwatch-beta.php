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
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-ops.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-support-auto.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-c2.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/atak-overwatch-tacmap.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/overwatch-gl/TheaterProjection.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/overwatch-gl/OverwatchGlMap.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/overwatch-gl/OverwatchGlLayers.js'),
    (int) @filemtime(dirname(__DIR__) . '/public/assets/js/overwatch-gl/OverwatchGlTactics.js')
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
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/vendor/maplibre-gl/maplibre-gl.css">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/css/atak-overwatch-beta.css?v=<?= $h($owAsset) ?>">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/css/halo-loader.css?v=<?= $h($assetVer) ?>">
  <style>.ow-map-tools{display:none!important}</style>
  <script>
    window.ATAK_OVERWATCH_BETA = true;
    window.ATAK_OVERWATCH_GL = true;
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
    window.ATAK_CALLSIGN_TO_USER = <?= json_encode($atakCallsignToUser ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.ATAK_MARKER_ICONS_CDN = <?= json_encode(function_exists('atak_marker_icons_cdn_base') ? atak_marker_icons_cdn_base() : rtrim($base, '/') . '/assets/markers/arma') ?>;
  </script>
</head>
<body>
<?php
  $baseUrl = $base;
  $haloLoaderHint = 'Préparation du poste Overwatch…';
  $haloLoaderSeenKey = 'athena-halo-loader-overwatch-beta';
  require base_path('views/partials/halo_loader.php');
?>
<div class="ow-shell">
  <header class="ow-topbar">
    <a class="ow-brand" href="<?= $h(url('-ATAK-OVERWATCH-Beta')) ?>"><b>A</b><span class="ow-brand-word">ATHENA<small>Comspec / Overwatch Beta</small></span></a>
    <nav class="ow-nav" aria-label="Espaces de travail">
      <button type="button" class="is-active" data-view="overwatch">Overwatch</button>
      <button type="button" data-view="comms">Ordre</button>
      <button type="button" data-view="mission">Mission</button>
      <button type="button" data-view="air">Air</button>
      <button type="button" data-view="network">Réseau</button>
      <button type="button" data-view="layers">Calques</button>
      <button type="button" data-view="intel">Renseignement</button>
      <button type="button" data-view="radio">Radio</button>
      <button type="button" data-view="iff">IFF</button>
      <button type="button" data-view="pings">Pings</button>
      <button type="button" data-view="tools">Outils</button>
    </nav>
    <div class="ow-more">
      <button type="button" data-ow-more>Plus</button>
      <div class="ow-more-menu" id="ow-more-menu" hidden>
        <button type="button" data-view="air">Air</button>
        <button type="button" data-view="network">Réseau</button>
        <button type="button" data-view="radio">Radio</button>
        <button type="button" data-view="iff">Identification</button>
        <button type="button" data-view="pings">Pings</button>
        <button type="button" data-ow-replay>Replay</button>
        <button type="button" data-ow-debrief>Exporter le bilan</button>
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
              <option value="immersive">2D immersif</option>
              <option value="volume">Relief 3D</option>
              <option value="tactical">Tactique 3D</option>
            </select>
          </label>
          <p class="ow-help">À plat : photo ou plan, sans volumes. 2D immersif : emprises des constructions collées à la photo, comme un relevé de toits. Relief et Tactique 3D dressent le sol et les volumes.</p>
          <label class="ow-toggle" for="atak-scene-buildings"><input type="checkbox" id="atak-scene-buildings" checked> Bâtiments, forêts et obstacles</label>
          <p class="ow-help">En 2D immersif, chaque construction du relevé apparaît en emprise au sol, collée à la photo. Un clic ouvre la fiche (marquer, objectif, étage). En Relief 3D, les volumes se dressent. À plat, cette case n’a pas d’effet.</p>
          <label class="ow-toggle" for="atak-scene-quality"><input type="checkbox" id="atak-scene-quality"> Qualité du relevé (cartographie)</label>
          <p class="ow-help">Éteint par défaut. Vert : données complètes. Orange : dimensions approximées. Gris : position seulement. Rouge : géométrie à vérifier.</p>
          <div class="ow-opt">
            <label class="ow-row" for="atak-symbol-occlusion">Symboles derrière un obstacle
              <select id="atak-symbol-occlusion">
                <option value="realistic">Réaliste</option>
                <option value="silhouette">Silhouette</option>
                <option value="always" selected>Toujours visibles</option>
              </select>
            </label>
            <button type="button" class="ow-i" aria-label="À propos des symboles derrière un obstacle" data-help="Toujours visibles : les pastilles restent lisibles derrière un bâtiment. Réaliste : un contact caché par un mur ou le relief disparaît. Silhouette : il reste une ombre.">i</button>
          </div>
          <div class="ow-opt">
            <label class="ow-toggle" for="atak-ghost-trails"><input type="checkbox" id="atak-ghost-trails"> Traces de déplacement</label>
            <button type="button" class="ow-i" aria-label="À propos des traces de déplacement" data-help="Laisse un sillage derrière chaque contact qui se déplace, pour voir d’où il vient.">i</button>
          </div>
          <div class="ow-opt">
            <label class="ow-toggle" for="atak-time-heat"><input type="checkbox" id="atak-time-heat"> Densité de passages</label>
            <button type="button" class="ow-i" aria-label="À propos de la densité de passages" data-help="Colorie les endroits souvent fréquentés. Plus la teinte est marquée, plus des contacts y sont passés.">i</button>
          </div>
          <div class="ow-opt">
            <label class="ow-toggle" for="atak-focus-mission"><input type="checkbox" id="atak-focus-mission"> Concentrer sur la mission</label>
            <button type="button" class="ow-i" aria-label="À propos de concentrer sur la mission" data-help="Masque ce qui est loin des objectifs et des points déjà posés, pour ne garder que le secteur utile.">i</button>
          </div>
          <div class="ow-opt">
            <label class="ow-toggle" for="atak-cinematic-aar"><input type="checkbox" id="atak-cinematic-aar"> Caméra qui suit le replay</label>
            <button type="button" class="ow-i" aria-label="À propos de la caméra du replay" data-help="Pendant la relecture, la vue suit toute seule le contact rejoué.">i</button>
          </div>
          <div class="ow-opt">
            <label class="ow-toggle" for="atak-scene-inspector"><input type="checkbox" id="atak-scene-inspector"> Inspection des constructions</label>
            <button type="button" class="ow-i" aria-label="À propos de l’inspection des constructions" data-help="Dans la fiche d’un bâtiment, affiche aussi les détails du relevé : hauteur, emprise, origine. Utile pour vérifier le terrain, pas pour la conduite.">i</button>
          </div>
          <div class="ow-opt">
            <label class="ow-toggle" for="atak-coverage-diag"><input type="checkbox" id="atak-coverage-diag"> Manques de relief sur la carte</label>
            <button type="button" class="ow-i" aria-label="À propos des manques de relief" data-help="Surligner les zones où le sol n’a pas encore été relevé. Le relief y sera plat ou incomplet.">i</button>
          </div>
          <p class="ow-kicker ow-kicker-opt">Vues enregistrées
            <button type="button" class="ow-i" aria-label="À propos des vues enregistrées" data-help="Mémorise le cadrage actuel — endroit, zoom, inclinaison — pour y revenir d’un clic.">i</button>
          </p>
          <button type="button" class="ow-secondary" id="ow-bookmark-save">Enregistrer la vue actuelle</button>
          <div id="ow-bookmark-list"></div>
          <div class="ow-opt">
            <label class="ow-row" for="atak-terrain-exaggeration">Hauteur du relief
              <input type="range" id="atak-terrain-exaggeration" min="1" max="4" step="0.1" value="2.5">
            </label>
            <span id="atak-terrain-exaggeration-val">2.5×</span>
            <button type="button" class="ow-i" aria-label="À propos de la hauteur du relief" data-help="Amplifie les collines pour mieux les lire. 1× = hauteur réelle du terrain.">i</button>
          </div>
          <div class="ow-opt">
            <label class="ow-row" for="atak-terrain-pitch">Inclinaison
              <input type="range" id="atak-terrain-pitch" min="25" max="65" step="1" value="48">
            </label>
            <span id="atak-terrain-pitch-val">48°</span>
            <button type="button" class="ow-i" aria-label="À propos de l’inclinaison" data-help="Penche la vue en relief. Plus la valeur est haute, plus on voit le sol de face.">i</button>
          </div>
        </div>
        <div id="atak-settings-map-data">
          <span class="atak-terrain-status" id="atak-terrain-status">Données terrain — aucune couverture</span>
          <div class="atak-terrain-inventory" id="atak-terrain-inventory">
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Ombrage</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-hillshade">Pas encore sur le poste</span></div>
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Relevé divers</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-survey">Pas encore sur le poste</span></div>
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Bâtiments</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-buildings">Pas encore sur le poste</span></div>
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Forêts</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-forests">Pas encore sur le poste</span></div>
            <div class="atak-terrain-inventory__row"><span class="atak-terrain-inventory__label">Obstacles</span><span class="atak-terrain-inventory__value" id="atak-terrain-inv-obstacles">Pas encore sur le poste</span></div>
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
        <p class="ow-help">Pointe du contact ouvert : orientation du personnage en jeu, pas la caméra. La longueur reste lisible quel que soit le zoom.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-predict"> Anticiper les trajectoires</label>
        <p class="ow-help">Vecteur pointillé pour chaque contact en mouvement, à partir du cap et de la vitesse déjà transmis. Rien n’est inventé si ces données manquent.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-progress-trail"> Tracé de progression</label>
        <p class="ow-help">Chemin déjà parcouru par le contact ouvert, d’après les positions reçues sur ce poste. Le tracé s’allonge au fur et à mesure.</p>
        <label class="ow-toggle"><input type="checkbox" id="atak-unit-trails" checked> Tracés unitaires (qualité)</label>
        <label class="ow-toggle"><input type="checkbox" id="atak-ghost-trails"> Tracés fantômes (hors liaison)</label>
        <p class="ow-help">Les tracés qualité reprennent le camp, la perte de liaison et les fantômes. Les fantômes n’apparaissent que pour les contacts hors liaison.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-label-grid"> Grille sous l’indicatif</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-po-markers" checked> Points d’objectif (libellé PO) — rayon 20 m</label>
        <p class="ow-help">Un marqueur nommé PO, PO 1 ou PO-2 devient un point d’objectif. Dès qu’un téléphone ATAK entre dans les 20 mètres, le point est confirmé atteint.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-rally-markers" checked> Points de ralliement — rayon 50 m</label>
        <p class="ow-help">Un point de ralliement est un lieu de regroupement. L’anneau vert de 50 mètres apparaît au poste et en jeu. Les opérateurs présents dans le rayon sont indiqués, sans rien inventer.</p>
        <p class="ow-kicker">Anneaux de portée</p>
        <p class="ow-help">Cercles autour du contact ouvert. Distances en mètres sur le théâtre.</p>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="100"> 100 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="250"> 250 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="500"> 500 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="1000"> 1 000 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="2000"> 2 000 m</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-reach-zone"> Zone possible (anneaux jaune / vert)</label>
        <p class="ow-help">Estimation pied (vert) et véhicule (jaune) depuis la dernière position connue. Désactivée par défaut ; activez-la seulement si vous en avez besoin.</p>
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
        <label class="ow-toggle"><input type="checkbox" id="ow-c2-alerts" checked> Alerte sonore proche d’un objectif</label>
        <label class="ow-row">Distance d’alerte
          <span class="ow-select"><select id="ow-c2-alert-radius" aria-label="Distance d’alerte autour d’un objectif">
            <option value="100">100 m</option>
            <option value="250" selected>250 m</option>
            <option value="500">500 m</option>
            <option value="1000">1 000 m</option>
          </select></span>
        </label>
        <p class="ow-help">Prévenez le poste si un contact hostile entre dans cette distance autour d’un point à atteindre, ou si un opérateur franchit un ralliement.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-weather-layer" checked> Overlay météo mission</label>
        <label class="ow-row">Largeur réglages
          <input type="range" id="ow-aside-left" min="240" max="480" value="300">
        </label>
        <label class="ow-row">Largeur tchat / effectifs
          <input type="range" id="ow-aside-right" min="280" max="560" value="380">
        </label>
        <p class="ow-kicker">Mission</p>
        <button type="button" class="ow-primary" data-overwatch-export>Exporter</button>
        <button type="button" class="ow-secondary" data-overwatch-import>Importer</button>
        <button type="button" class="ow-secondary" data-overwatch-print>Imprimer</button>
        <input type="file" id="overwatch-mission-import" accept="application/json,.json" hidden>
      </div>
    </aside>

    <section class="ow-map-stage atak-map-wrap" id="ow-map-stage" data-look="color">
      <div class="ow-rail" aria-label="Outils cartographiques">
        <button type="button" class="is-active" data-tool="cursor" data-tip="Sélection" data-help="Cliquez un contact ou un tracé. Échap quitte l’outil en cours."><?= $icon('M5 5h6v6H5zM13 5h6v6h-6zM5 13h6v6H5zM15 15l4 4') ?></button>
        <button type="button" data-tool="center" data-tip="Recentrer" data-help="Recadre le théâtre entier."><?= $icon('M12 3v3M12 18v3M3 12h3M18 12h3M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8z') ?></button>
        <span class="ow-rail-gap"></span>
        <button type="button" data-tool="marker" data-tip="Marqueur" data-help="Un clic pose un repère. L’outil reste actif pour en poser d’autres."><?= $icon('M12 21s7-7 7-12a7 7 0 1 0-14 0c0 5 7 12 7 12z') ?></button>
        <button type="button" data-tool="draw" data-tip="Tracé tactique" data-help="Ouvre la barre de tracé : flèches, zones, symboles OTAN, plan de bâtiment et export pour briefing."><?= $icon('M17 3l4 4L7 21l-5 1 1-5L17 3z') ?></button>
        <button type="button" data-tool="line" data-tip="Ligne" data-help="Maintenez et glissez, ou cliquez des sommets puis double-clic."><?= $icon('M4 18L20 6') ?></button>
        <button type="button" data-tool="polygon" data-tip="Zone" data-help="Maintenez pour tracer un lasso. Relâchez pour fermer la zone."><?= $icon('M12 3l8 6-3 10H7L4 9z') ?></button>
        <button type="button" data-tool="circle" data-tip="Cercle" data-help="Appuyez au centre, glissez le rayon, relâchez pour poser."><?= $icon('M12 5a7 7 0 1 1 0 14 7 7 0 0 1 0-14z') ?></button>
        <button type="button" data-tool="measure" data-tip="Mesure" data-help="Cliquez le départ, puis l’arrivée. Distance, cap, grilles et temps de parcours s’affichent."><?= $icon('M4 12h16M8 8v8M16 8v8') ?></button>
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
          <button type="button" data-tool="viewshed" data-tip="Masque de visibilité" data-help="Cliquez un observateur. Les portions visibles et masquées s’affichent."><?= $icon('M12 5a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM4 12h16') ?><span>Masque</span></button>
          <button type="button" data-tool="horizon" data-tip="Horizon" data-help="Cliquez un point. La silhouette du relief s’affiche."><?= $icon('M3 16l5-6 4 3 9-9') ?><span>Horizon</span></button>
          <button type="button" data-tool="slice" data-tip="Coupe verticale" data-help="Glissez de A vers B. Sol et constructions apparaissent en tranche."><?= $icon('M4 20V4l16 16H4z') ?><span>Coupe</span></button>
          <button type="button" data-tool="measure3d" data-tip="Mesure 3D" data-help="Deux points : distance au sol, spatiale, dénivelé, cap et pente."><?= $icon('M4 12h16M8 8v8M16 8v8M12 4v16') ?><span>Mesure 3D</span></button>
          <button type="button" data-tool="volume" data-tip="Volume" data-help="Tracez une zone puis indiquez l’altitude basse et haute."><?= $icon('M4 8l8-4 8 4v8l-8 4-8-4z') ?><span>Volume</span></button>
          <button type="button" data-tool="compare" data-tip="Comparer 2D et 3D" data-help="Carte à plat et vue en relief côte à côte, même centre et même zoom."><?= $icon('M4 5h7v14H4zM13 5h7v14h-7z') ?><span>2D / 3D</span></button>
          <button type="button" data-tool="bookmark" data-tip="Enregistrer la vue" data-help="Mémorise le cadrage, l’inclinaison et le cap."><?= $icon('M7 4h10v16l-5-3-5 3z') ?><span>Vue</span></button>
          <button type="button" data-tool="refresh" data-tip="Actualiser" data-help="Relance la synchronisation des contacts et des canaux."><?= $icon('M20 12a8 8 0 1 1-2-5.3M20 4v6h-6') ?><span>Sync</span></button>
        </div>
      </div>
      <div class="ow-drawbar" id="ow-drawbar" hidden>
        <div class="ow-drawbar-grp">
          <button type="button" class="ow-dtool" data-draw="cursor" title="Sélection"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l8 18 2-8 8-2z"/></svg><span class="ow-dtool-tip">Sélection</span></button>
          <button type="button" class="ow-dtool is-active" data-draw="arrow" title="Flèche tactique"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 19L19 5M19 5H9M19 5v10"/></svg><span class="ow-dtool-tip">Flèche tactique</span></button>
          <button type="button" class="ow-dtool" data-draw="freehand" title="Tracé libre"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20c3-8 6-12 9-12s2 6 5 6 3-6 6-6"/></svg><span class="ow-dtool-tip">Tracé libre</span></button>
          <button type="button" class="ow-dtool" data-draw="polygon" title="Zone / polygone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l9 6-3 10H6L3 8z"/></svg><span class="ow-dtool-tip">Zone / polygone</span></button>
          <button type="button" class="ow-dtool" data-draw="highlight" title="Surligneur"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11l6-6 4 4-6 6M3 21l4-1 8-8-3-3-8 8z"/></svg><span class="ow-dtool-tip">Surligneur</span></button>
          <button type="button" class="ow-dtool" data-draw="text" title="Texte"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 5h14M12 5v14"/></svg><span class="ow-dtool-tip">Texte</span></button>
        </div>
        <span class="ow-drawbar-sep"></span>
        <div class="ow-drawbar-grp ow-drawbar-tint">
          <label class="ow-tac-color" title="Couleur du tracé">
            <input type="color" id="ow-tac-color" value="#00d69a" aria-label="Couleur du tracé">
          </label>
          <input type="range" id="ow-tac-width" min="1" max="8" value="2" title="Épaisseur du trait" aria-label="Épaisseur du trait">
          <button type="button" class="ow-tint" data-tint="#5b9dff" style="background:#5b9dff" title="Ami"></button>
          <button type="button" class="ow-tint" data-tint="#ef5b5b" style="background:#ef5b5b" title="Ennemi"></button>
          <button type="button" class="ow-tint" data-tint="#2ecf9a" style="background:#2ecf9a" title="Neutre"></button>
          <button type="button" class="ow-tint" data-tint="#e8cf4a" style="background:#e8cf4a" title="Inconnu"></button>
          <button type="button" class="ow-tint" data-tint="#f0a63a" style="background:#f0a63a" title="Attention"></button>
          <button type="button" class="ow-tint" data-tint="#ffffff" style="background:#fff" title="Blanc"></button>
          <button type="button" class="ow-tint" data-tint="#111111" style="background:#111" title="Noir"></button>
        </div>
        <span class="ow-drawbar-sep"></span>
        <div class="ow-drawbar-grp">
          <button type="button" class="ow-dtool" data-draw="measure" title="Mesure"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 17l14-14 4 4-14 14H3v-4z"/></svg><span class="ow-dtool-tip">Mesure</span></button>
          <button type="button" class="ow-dtool" id="ow-btn-bplan" title="Découpage bâtiment"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21V9l9-6 9 6v12M9 21v-6h6v6"/></svg><span class="ow-dtool-tip">Découpage bâtiment</span></button>
        </div>
        <span class="ow-drawbar-sep"></span>
        <div class="ow-drawbar-grp ow-drawbar-otan">
          <button type="button" class="ow-otan-btn" id="ow-otan-btn" aria-expanded="false" aria-controls="ow-otan-flyout">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 19L19 5M19 5H9M19 5v10"/></svg>
            Symboles OTAN
          </button>
          <div class="ow-otan-flyout" id="ow-otan-flyout" hidden>
            <div class="ow-otan-cap">Affiliation</div>
            <div class="ow-otan-affil">
              <button type="button" class="ow-swatch is-on" data-affil="friend" style="background:#5b9dff" title="Ami"><span>Ami</span></button>
              <button type="button" class="ow-swatch" data-affil="hostile" style="background:#ef5b5b" title="Ennemi"><span>Ennemi</span></button>
              <button type="button" class="ow-swatch" data-affil="neutral" style="background:#2ecf9a" title="Neutre"><span>Neutre</span></button>
              <button type="button" class="ow-swatch" data-affil="unknown" style="background:#e8cf4a" title="Inconnu"><span>Inconnu</span></button>
            </div>
            <div class="ow-otan-cap">Symboles APP-6</div>
            <div class="ow-otan-grid">
              <button type="button" class="ow-otan-item" data-draw="axis"><svg viewBox="0 0 40 20"><line x1="3" y1="14" x2="30" y2="4" stroke="#5b9dff" stroke-width="2"/><path d="M30,4 L24,6 L26,11 Z" fill="#5b9dff"/></svg><span>Axe de progression</span></button>
              <button type="button" class="ow-otan-item" data-draw="attack"><svg viewBox="0 0 40 20"><path d="M3,17 L3,10 L26,6 L34,3 L26,12 L3,10" fill="#5b9dff"/></svg><span>Attaque principale</span></button>
              <button type="button" class="ow-otan-item" data-draw="phase"><svg viewBox="0 0 40 20"><line x1="2" y1="10" x2="36" y2="10" stroke="#e8cf4a" stroke-width="2" stroke-dasharray="4 2"/></svg><span>Ligne de phase</span></button>
              <button type="button" class="ow-otan-item" data-draw="sector"><svg viewBox="0 0 40 20"><line x1="20" y1="2" x2="20" y2="18" stroke="#8b93a1" stroke-width="1.6" stroke-dasharray="5 1.5 1 1.5"/><line x1="14" y1="2" x2="26" y2="2" stroke="#8b93a1" stroke-width="1.6"/></svg><span>Limite de secteur</span></button>
              <button type="button" class="ow-otan-item" data-draw="assembly"><svg viewBox="0 0 40 20"><rect x="5" y="4" width="30" height="12" fill="none" stroke="#5b9dff" stroke-width="1.8"/></svg><span>Zone de rassemblement</span></button>
              <button type="button" class="ow-otan-item" data-draw="objective"><svg viewBox="0 0 40 20"><ellipse cx="20" cy="10" rx="15" ry="7" fill="none" stroke="#ef5b5b" stroke-width="1.8"/></svg><span>Objectif</span></button>
            </div>
          </div>
        </div>
        <span class="ow-drawbar-sep"></span>
        <div class="ow-drawbar-grp">
          <button type="button" class="ow-dtool" data-draw="undo" title="Annuler"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-1"/></svg><span class="ow-dtool-tip">Annuler</span></button>
          <button type="button" class="ow-dtool" data-draw="redo" title="Rétablir"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 14l5-5-5-5"/><path d="M20 9H9a5 5 0 000 10h1"/></svg><span class="ow-dtool-tip">Rétablir</span></button>
        </div>
        <button type="button" class="ow-export-btn" id="ow-btn-export">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
          Exporter PDF
        </button>
      </div>
      <aside class="ow-bplan" id="ow-bplan" hidden>
        <div class="ow-bplan-head">
          <div class="ow-bplan-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21V9l9-6 9 6v12M9 21v-6h6v6"/></svg><span id="ow-bplan-heading">Plan de bâtiment</span></div>
          <button type="button" class="ow-bplan-close" id="ow-bplan-close" aria-label="Fermer le plan">✕</button>
        </div>
        <div class="ow-bplan-floors" id="ow-bplan-floors">
          <button type="button" class="ow-floor is-active" data-floor="rdc">RDC</button>
          <button type="button" class="ow-floor" data-floor="etage-1">Étage 1</button>
          <button type="button" class="ow-floor" id="ow-bplan-add-floor">+ Niveau</button>
        </div>
        <div class="ow-bplan-canvas-wrap">
          <canvas id="ow-bplan-canvas" width="260" height="220" aria-label="Schéma de l’étage"></canvas>
        </div>
        <div class="ow-bplan-tools">
          <button type="button" class="ow-bt is-active" data-btool="wall"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="12" x2="20" y2="12"/></svg>Mur</button>
          <button type="button" class="ow-bt" data-btool="door"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 21V3h10v18"/><path d="M14 12h6"/></svg>Porte</button>
          <button type="button" class="ow-bt" data-btool="window"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16"/><line x1="4" y1="12" x2="20" y2="12"/></svg>Fenêtre</button>
          <button type="button" class="ow-bt" data-btool="breach"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8"/><line x1="8" y1="8" x2="16" y2="16"/><line x1="16" y1="8" x2="8" y2="16"/></svg>Point de brèche</button>
          <button type="button" class="ow-bt" data-btool="room"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5h14M12 5v14"/></svg>Pièce</button>
        </div>
        <div class="ow-bplan-foot">
          <label class="ow-bplan-name">Rattacher le plan
            <select id="ow-bplan-source" aria-label="Origine du bâtiment">
              <option value="map" selected>Un clic sur la carte</option>
              <option value="pointed">Un bâtiment désigné en jeu</option>
              <option value="scene">Une construction relevée</option>
            </select>
          </label>
          <label class="ow-bplan-name" id="ow-bplan-search-wrap" hidden>Filtrer
            <input type="search" id="ow-bplan-search" maxlength="80" placeholder="Nom ou grille…">
          </label>
          <label class="ow-bplan-name" id="ow-bplan-pick-wrap" hidden>Bâtiment
            <select id="ow-bplan-pick" aria-label="Choisir un bâtiment">
              <option value="">Aucun pour le moment</option>
            </select>
          </label>
          <p class="ow-help" id="ow-bplan-attach-help">Enregistrez, puis cliquez le bâtiment sur la carte.</p>
          <label class="ow-bplan-name">Nom du bâtiment
            <input type="text" id="ow-bplan-name" maxlength="80" placeholder="Hangar, maison, entrepôt…">
          </label>
          <button type="button" class="ow-primary" id="ow-bplan-save">Enregistrer le plan sur la carte</button>
        </div>
      </aside>
      <div class="ow-export-modal" id="ow-export-modal" hidden>
        <div class="ow-export-card" role="dialog" aria-labelledby="ow-export-title">
          <div class="ow-export-head">
            <div class="ow-export-title" id="ow-export-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>Exporter en PDF</div>
            <button type="button" class="ow-bplan-close" id="ow-export-close" aria-label="Fermer">✕</button>
          </div>
          <div class="ow-export-body">
            <label class="ow-chk"><input type="checkbox" id="ow-pdf-annos" checked> Annotations du calque actif (flèches, zones, texte)</label>
            <label class="ow-chk"><input type="checkbox" id="ow-pdf-legend" checked> Légende tactique</label>
            <label class="ow-chk"><input type="checkbox" id="ow-pdf-grid"> Grille de coordonnées</label>
            <label class="ow-chk"><input type="checkbox" id="ow-pdf-time"> Horodatage de mission</label>
            <label class="ow-chk"><input type="checkbox" id="ow-pdf-chat"> Fil du canal actif</label>
            <label class="ow-export-field">Format
              <select id="ow-pdf-format">
                <option value="a4-landscape">A4 — Paysage</option>
                <option value="a4-portrait">A4 — Portrait</option>
                <option value="letter-landscape">Letter — Paysage</option>
              </select>
            </label>
          </div>
          <div class="ow-export-foot">
            <button type="button" class="ow-primary" id="ow-export-go">Générer le PDF</button>
          </div>
        </div>
      </div>
      <div id="ow-map" aria-label="Carte tactique temps réel"></div>
      <div id="ow-gl-map" class="ow-gl-map" hidden aria-label="Carte en relief"></div>
      <aside class="atak-dossier" id="atak-unit-dossier" hidden aria-label="Fiche d’unité"></aside>
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
        <button type="button" data-ctx="measure">Distance et cap <span></span></button>
        <button type="button" data-ctx="los">Visée / masque <span>V</span></button>
        <div class="ow-context-group">Transmettre</div>
        <button type="button" data-ctx="salute">Compte rendu SALUTE <span></span></button>
        <button type="button" data-ctx="intel">Observation de terrain <span></span></button>
        <button type="button" data-ctx="sitrep">Compte rendu géolocalisé <span></span></button>
        <button type="button" data-ctx="nineline">9-line ici <span></span></button>
        <button type="button" data-ctx="casevac">CASEVAC ici <span></span></button>
        <button type="button" data-ctx="chatgrid">Envoyer la grille au canal <span></span></button>
        <button type="button" data-ctx="copy">Copier les coordonnées <span></span></button>
      </div>
      <div class="ow-map-tl" id="ow-map-tl">
        <div class="ow-follow-chip" id="ow-follow-chip" hidden>
          <span id="ow-follow-label">Suivi</span>
          <button type="button" id="ow-follow-stop">Arrêter</button>
        </div>
        <div class="ow-cam-bar" id="ow-cam-bar" hidden>
          <button type="button" data-ow-cam="north">Nord</button>
          <button type="button" data-ow-cam="follow">Unité</button>
          <button type="button" data-ow-cam="ground">Sol</button>
        </div>
        <div class="ow-wx" id="ow-wx" hidden></div>
        <div class="ow-scene-load" id="ow-scene-load" hidden>Chargement du relevé…</div>
        <div class="ow-freeze-banner" id="ow-freeze-banner" hidden></div>
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
        <button type="button" class="ow-replay-play" data-ow-debrief>Exporter le bilan</button>
      </div>
      <div class="ow-north" id="ow-north" aria-hidden="true">N</div>
      <div class="ow-live-measure" id="ow-live-measure" hidden></div>
      <div class="ow-data-hud" id="ow-data-hud">Groupes — · Ami 0 · Hostile 0</div>
      <div class="ow-toast" id="ow-toast" hidden><small>Athena</small><p id="ow-toast-text"></p></div>
    </section>

    <aside class="ow-chat" id="ow-chat" aria-label="Tchat opérationnel">
      <header><span>●</span> Ordre <b id="ow-comms-unread" class="ow-unread" hidden></b></header>
      <div class="ow-tabs" role="tablist">
        <button type="button" class="is-active" data-chat-tab="channels">Canaux <span class="ow-unread" data-unread-tab="channels" hidden></span></button>
        <button type="button" data-chat-tab="contacts">Contacts</button>
        <button type="button" data-chat-tab="squads">Groupes</button>
        <button type="button" data-chat-tab="support">Support <span class="ow-unread" data-unread-tab="support" hidden></span></button>
      </div>
      <div class="ow-chat-main" data-chat-panel="channels">
        <label class="ow-search"><span>⌕</span><input id="ow-channel-filter" type="search" placeholder="Canal ou indicatif…"></label>
        <label class="ow-search"><span>⌕</span><input id="ow-comms-search" type="search" placeholder="Rechercher dans le fil…"></label>
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
      <div class="ow-chat-main ow-bft" data-chat-panel="contacts" hidden>
        <div class="ow-bft-head">
          <div class="ow-bft-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            Suivi des forces
            <span class="ow-bft-sub" id="ow-bft-sub">0 unité</span>
          </div>
          <label class="ow-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input id="ow-search" type="search" placeholder="Rechercher un contact…" autocomplete="off">
          </label>
          <label class="ow-bft-filter">Afficher
            <span class="ow-select"><select id="ow-side-filter" aria-label="Filtrer les contacts">
              <option value="all">Tous</option>
              <option value="live">En ligne</option>
              <option value="offline">Hors ligne</option>
              <option value="friendly">Amis</option>
              <option value="hostile">Hostiles</option>
              <option value="unknown">Inconnus</option>
              <option value="wave">Wave</option>
            </select></span>
          </label>
          <button type="button" class="ow-tag" id="ow-filter-wave" title="Uniquement Wave Relay" aria-pressed="false">Wave</button>
        </div>
        <div id="ow-contact-list" class="ow-bft-body" aria-live="polite"></div>
        <div class="ow-effectifs" id="ow-effectifs" aria-label="Tableau des effectifs">
          <div class="ow-effectifs-head">
            <strong>Tableau des effectifs</strong>
            <span id="ow-effectifs-count">0</span>
          </div>
          <div class="ow-effectifs-scroll">
            <table class="ow-effectifs-table">
              <thead>
                <tr>
                  <th>Indicatif</th>
                  <th>Rôle</th>
                  <th>Équipe</th>
                  <th>Liaison</th>
                  <th>Cap</th>
                  <th>Grille</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody id="ow-units-table-body"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="ow-chat-main" data-chat-panel="squads" hidden>
        <p class="ow-help">Les opérateurs d’un même groupe sont reliés sur la carte. Cliquez un groupe pour le cadrer.</p>
        <div id="ow-group-task-host"></div>
        <div id="ow-fs-alert-host"></div>
        <div class="ow-section-label">Groupes sur la carte</div>
        <div id="ow-squad-list" class="ow-contact-list" aria-live="polite"></div>
      </div>
      <div class="ow-chat-main" data-chat-panel="support" hidden>
        <p class="ow-help">Assistance technique, séparée des canaux de mission. Le poste surveille aussi les crashs et les écarts de version Overwatch.</p>
        <div id="ow-support-auto" class="ow-support-auto" aria-live="polite"></div>
        <div id="ow-support-log" class="ow-fil"></div>
        <form class="ow-chat-compose" id="ow-support-form">
          <input id="ow-support-input" maxlength="500" placeholder="Décrire le problème…" autocomplete="off">
          <button type="submit">Signaler</button>
        </form>
      </div>
    </aside>

    <aside class="ow-drawer" id="ow-drawer" hidden>
      <header>
        <span id="ow-drawer-avatar" class="ow-drawer-avatar" hidden></span>
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
    <p>À gauche, les fonds, le relief et les couches. Le chevron rabat ce panneau. À droite, les canaux et le fil : vous pouvez aussi chercher un mot dans le fil. Replay, bilan de mission et le journal sont dans Plus, en haut. Les outils de tracé rarement utilisés sont derrière la flèche du rail, avec leur nom. L’espace Air rassemble les aéronefs, les manifestes et les demandes JTAC. L’espace Réseau liste les relais posés, les terminaux ATAK et l’état satellitaire lorsqu’un catalogue est fourni.</p>
    <h2>Fonds</h2>
    <p>Choisissez la carte du jeu ou la photo aérienne. La lecture couleur ou noir et blanc ne change pas le calque, seulement le contraste.</p>
    <h2>Calques</h2>
    <p>Ombrage, pentes et chaleur de présence s’ajoutent au fond. À plat, la carte reste un plan sans volumes. En 2D immersif, les constructions du relevé apparaissent en emprise au sol, collées à la photo, comme un relevé de toits. En Relief 3D, le sol se relève et les volumes se dressent. Le masque de visibilité, l’horizon et la coupe verticale se trouvent derrière la flèche des outils. 2D / 3D affiche les deux lectures côte à côte.</p>
    <h2>Dessin</h2>
    <p>Le crayon du rail ouvre la barre de tracé au-dessus de la carte : flèche, croquis, zone, surligneur, texte, symboles OTAN (ami, ennemi, neutre, inconnu) et plan de bâtiment. Maintenez le clic pour tracer, relâchez pour poser. Exporter PDF prépare une feuille de briefing (carte, légende, fil). Échap ou Sélection pour quitter. Clic droit : SALUTE, 9-line, CASEVAC, ou supprimer un tracé.</p>
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

<div id="ow-legacy-ops" hidden>
  <div class="ow-ops-panel" id="tab-radio">
    <div class="atak-radio-head" id="atak-radio-head">
      <p class="atak-panel-hint">Qui émet près d’un opérateur en liaison, et sur quel réseau. L’écoute audio se fait en jeu ; ici vous suivez qui émet.</p>
      <div class="atak-radio-toolbar">
        <label class="atak-radio-field"><span>Opérateur de référence</span>
          <select id="atak-radio-focus"><option value="">Opérateur de référence (auto)</option></select>
        </label>
        <label class="atak-radio-field"><span>Rayon (m)</span>
          <select id="atak-radio-radius">
            <option value="50">50</option>
            <option value="75" selected>75</option>
            <option value="100">100</option>
            <option value="150">150</option>
            <option value="200">200</option>
          </select>
        </label>
        <label class="atak-radio-check"><input type="checkbox" id="atak-radio-tx-only" /><span>Émissions uniquement</span></label>
        <label class="atak-radio-check"><input type="checkbox" id="atak-radio-hide-nomodule" /><span>Masquer si aucun module radio</span></label>
      </div>
      <div class="atak-radio-listen-bar" id="atak-radio-listen-bar" hidden></div>
      <div class="atak-radio-banner" id="atak-radio-banner" hidden></div>
    </div>
    <div class="atak-radio-list" id="atak-radio-list"></div>
    <span id="atak-radio-tab-badge" hidden></span>
  </div>

  <div class="ow-ops-panel" id="tab-identification">
    <div class="atak-iff-panel">
      <div class="atak-panel-strip">
        <span class="atak-panel-strip-title">Identification (IFF)</span>
        <button type="button" class="atak-ops-btn" id="atak-iff-refresh">Actualiser</button>
      </div>
      <p class="atak-panel-hint">Défi / réponse pour confirmer qu’une unité est amie.</p>
      <div class="atak-iff-alert-banner" id="atak-iff-alert-banner" hidden role="alert"></div>
      <div class="atak-iff-current" id="atak-iff-current">
        <p class="atak-iff-label">Défi courant</p>
        <p class="atak-iff-code" id="atak-iff-challenge-code">—</p>
        <p class="atak-iff-valid" id="atak-iff-valid-until">Aucun défi actif pour cette carte.</p>
        <p class="atak-iff-expire" id="atak-iff-expire-countdown" hidden></p>
        <p class="atak-iff-empty" id="atak-iff-empty-challenge">Publiez un défi ci-dessous pour démarrer l’identification.</p>
      </div>
      <div class="atak-ops-form atak-iff-form">
        <label class="atak-ops-field">Code de défi
          <input type="text" id="atak-iff-new-code" maxlength="32" autocomplete="off" placeholder="Ex. DELTA7" spellcheck="false" />
        </label>
        <label class="atak-ops-field">Durée de validité
          <select id="atak-iff-valid-minutes">
            <option value="15">15 minutes</option>
            <option value="30" selected>30 minutes</option>
            <option value="60">1 heure</option>
            <option value="120">2 heures</option>
          </select>
        </label>
        <div class="atak-iff-actions">
          <button type="button" class="atak-ops-btn atak-ops-btn--primary" id="atak-iff-generate">Publier le défi</button>
          <button type="button" class="atak-ops-btn" id="atak-iff-sync-units">Inscrire les unités en liaison</button>
        </div>
      </div>
      <p class="atak-iff-feedback" id="atak-iff-feedback" hidden></p>
      <div class="atak-ops-form">
        <label class="atak-ops-field">Unité
          <select id="atak-iff-respond-asset"><option value="">Choisir une unité…</option></select>
        </label>
        <label class="atak-ops-field">Code réponse
          <input type="text" id="atak-iff-respond-code" maxlength="64" autocomplete="off" spellcheck="false" />
        </label>
        <button type="button" class="atak-ops-btn" id="atak-iff-respond-submit">Envoyer la réponse</button>
      </div>
      <div class="atak-iff-assets">
        <p class="atak-iff-label">État des réponses</p>
        <div id="atak-iff-assets-list"></div>
      </div>
    </div>
  </div>

  <div class="ow-ops-panel" id="tab-pings">
    <p class="atak-panel-hint">Clic droit sur la carte pour envoyer un ping. Les pings reçus apparaissent ici et sur la carte.</p>
    <div class="atak-pings-list" id="atak-pings-list">
      <div class="atak-empty-state">
        <p class="atak-empty-state-title">Aucun ping</p>
        <p class="atak-empty-state-text">Clic droit sur la carte → Envoyer un ping.</p>
      </div>
    </div>
  </div>
</div>

<script src="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.js"></script>
<script src="<?= $h($base) ?>/assets/js/atak-map-crs.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/nato-sidc-icons.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/arma-marker-catalog.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/arma-map-markers.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-motion.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-unit-popup.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-aerial.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-reach-overlay.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-beta.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-support-auto.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-unit-dossier.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-motion-map.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-sse-layers.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-radio.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-iff.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-super-ping.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-pings.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-tools.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-terrain.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/vendor/maplibre-gl/maplibre-gl.js"></script>
<script src="<?= $h($base) ?>/assets/vendor/deck.gl/deck.min.js"></script>
<script src="<?= $h($base) ?>/assets/js/overwatch-gl/TheaterProjection.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/overwatch-gl/OverwatchGlMap.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/overwatch-gl/OverwatchGlLayers.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/overwatch-gl/OverwatchGlTactics.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-geo-network.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-gotak.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-ops.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-c2.js?v=<?= $h($owAsset) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-tacmap.js?v=<?= $h($owAsset) ?>"></script>
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
