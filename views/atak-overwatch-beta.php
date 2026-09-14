<?php

declare(strict_types=1);

$base = url('');
$assetVer = platform_app_version();
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
$operator = trim((string) ($atakUserForJs['callsign'] ?? $atakUserForJs['displayName'] ?? 'OPÉRATEUR'));
$community = trim((string) ($atakTenantLabel ?? ''));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr" class="ow-root">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#050505">
  <title>ATHENA // Overwatch Beta</title>
  <link rel="icon" href="<?= $h($base) ?>/assets/icons/athena-192.png">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.css">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/css/atak-overwatch-beta.css?v=<?= $h($assetVer) ?>">
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
  </script>
</head>
<body>
<div class="ow-shell">
  <header class="ow-topbar">
    <a class="ow-brand" href="<?= $h(url('-ATAK-OVERWATCH-Beta')) ?>"><b>A</b><span>ATHENA.<small>COMSPEC / OVERWATCH BETA</small></span></a>
    <nav class="ow-nav" aria-label="Espaces de travail">
      <button type="button" class="is-active" data-view="overwatch">OVERWATCH</button>
      <button type="button" data-view="comms">COMMS</button>
      <button type="button" data-view="mission">MISSION</button>
      <button type="button" data-view="layers">LAYERS</button>
      <button type="button" data-view="intel">INTEL</button>
      <button type="button" data-view="tools">TOOLS</button>
    </nav>
    <button type="button" class="ow-command" data-command>⌘ K</button>
    <div class="ow-session"><i></i><span><strong><?= $h($operator) ?></strong><small id="ow-link-label">CONNEXION…</small></span></div>
  </header>

  <div class="ow-statusbar">
    <span>COMMUNAUTÉ &nbsp;<b id="ow-community"><?= $h($community !== '' ? $community : 'ATHENA') ?></b>&nbsp;&nbsp; / &nbsp;&nbsp;INDICATIF &nbsp;<b><?= $h($operator) ?></b>&nbsp;&nbsp; / &nbsp;&nbsp;STATUT &nbsp;<span class="ow-green" id="ow-status-word">SYNCHRONISATION</span></span>
    <div class="ow-statusbar-right">
      <label class="ow-mini ow-sync-rate">SYNC
        <select id="ow-refresh-rate" aria-label="Fréquence de synchronisation">
          <option value="3000">3 S</option>
          <option value="8000">8 S</option>
          <option value="15000">15 S</option>
          <option value="30000">30 S</option>
        </select>
      </label>
    </div>
  </div>

  <main class="ow-workspace">
    <aside class="ow-settings" id="ow-settings" aria-label="Réglages du poste">
      <header><span>●</span> RÉGLAGES DU POSTE</header>
      <div class="ow-settings-body">
        <p class="ow-kicker">CARTOGRAPHIE ALTIS</p>
        <fieldset class="ow-looks" id="ow-altis-looks">
          <legend>Fond de carte</legend>
          <p class="ow-help">Trois lectures persistantes. Les contacts et les symboles ne changent pas.</p>
          <label><input type="radio" name="ow-map-look" value="classic" data-ow-look> <span><strong>Classique</strong><small>Plan du théâtre</small></span></label>
          <label><input type="radio" name="ow-map-look" value="aerial" data-ow-look checked> <span><strong>Aerial</strong><small>Photo aérienne, relief renforcé</small></span></label>
          <label><input type="radio" name="ow-map-look" value="bw" data-ow-look> <span><strong>Noir et blanc</strong><small>Lecture tactique</small></span></label>
        </fieldset>
        <fieldset class="ow-looks" id="atak-settings-fond">
          <legend>Calques Atlas</legend>
          <div id="atak-fond-calques-list"></div>
        </fieldset>
        <p class="ow-kicker">COUCHES</p>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="units" checked> Unités</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="vehicles" checked> Véhicules</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="air" checked> Aérien / drones</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="shapes" checked> Tracés et AOI</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-layer="tracks"> Trajectoires</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-squad-links" checked> Relier les membres d’un même groupe</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-squad-hull" checked> Enveloppe de groupe</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-squad-dist"> Distances sur les liens de groupe</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-follow"> Suivre le contact sélectionné</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-po-markers" checked> Points d’objectif (libellé PO) — rayon 20 m</label>
        <p class="ow-help">Un marqueur nommé PO, PO 1 ou PO-2 devient un point d’objectif. Dès qu’un téléphone ATAK entre dans les 20 mètres, le point est confirmé atteint.</p>
        <label class="ow-toggle"><input type="checkbox" id="ow-rally-markers" checked> Points de ralliement — rayon 50 m</label>
        <p class="ow-help">Un point de ralliement est un lieu de regroupement. L’anneau vert de 50 mètres apparaît au poste et en jeu. Les opérateurs présents dans le rayon sont indiqués, sans rien inventer.</p>
        <p class="ow-kicker">ANNEAUX DE PORTÉE</p>
        <p class="ow-help">Cercles autour du contact ouvert. Distances en mètres sur le théâtre.</p>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="100"> 100 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="250" checked> 250 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="500" checked> 500 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="1000"> 1 000 m</label>
        <label class="ow-toggle"><input type="checkbox" data-ow-ring="2000"> 2 000 m</label>
        <p class="ow-kicker">PERSONNALISATION</p>
        <label class="ow-row">Thème
          <select id="ow-theme">
            <option value="night">Nuit</option>
            <option value="day">Jour</option>
          </select>
        </label>
        <label class="ow-row">Symboles
          <select id="ow-marker-style">
            <option value="diamond">Losange</option>
            <option value="dot">Point</option>
            <option value="nato">Cadre</option>
          </select>
        </label>
        <label class="ow-row">Couleur amie
          <input type="color" id="ow-color-friend" value="#00d69a">
        </label>
        <label class="ow-row">Couleur hostile
          <input type="color" id="ow-color-hostile" value="#e05b63">
        </label>
        <label class="ow-row">Taille des indicatifs
          <input type="range" id="ow-label-size" min="7" max="14" value="9">
        </label>
        <label class="ow-row">Couleur de dessin
          <input type="color" id="ow-draw-color" value="#00d69a">
        </label>
        <label class="ow-row">Épaisseur de trait
          <input type="range" id="ow-draw-width" min="1" max="8" value="2">
        </label>
        <label class="ow-toggle"><input type="checkbox" id="ow-geofence" checked> Alerte entrée / sortie d’AOI</label>
        <label class="ow-toggle"><input type="checkbox" id="ow-weather-layer" checked> Overlay météo mission</label>
        <label class="ow-row">Largeur réglages
          <input type="range" id="ow-aside-left" min="240" max="420" value="330">
        </label>
        <label class="ow-row">Largeur tchat
          <input type="range" id="ow-aside-right" min="240" max="420" value="330">
        </label>
        <p class="ow-kicker">MISSION</p>
        <button type="button" class="ow-primary" data-overwatch-export>EXPORTER</button>
        <button type="button" class="ow-primary" data-overwatch-import style="margin-top:8px;background:#101413;color:#c7ceca;border:1px solid #2b3531">IMPORTER</button>
        <button type="button" class="ow-primary" data-overwatch-print style="margin-top:8px;background:#101413;color:#c7ceca;border:1px solid #2b3531">IMPRIMER</button>
        <input type="file" id="overwatch-mission-import" accept="application/json,.json" hidden>
      </div>
    </aside>

    <section class="ow-map-stage" id="ow-map-stage" data-look="aerial">
      <div class="ow-rail" aria-label="Outils cartographiques">
        <button type="button" class="is-active" data-tool="cursor" title="Sélection">⌖</button>
        <button type="button" data-tool="center" title="Recentrer">◎</button>
        <button type="button" data-tool="locate" title="Ma position">⊕</button>
        <button type="button" data-tool="marker" title="Marqueur">✚</button>
        <button type="button" data-tool="line" title="Ligne">╱</button>
        <button type="button" data-tool="polygon" title="Polygone">⬡</button>
        <button type="button" data-tool="measure" title="Mesure">⌁</button>
        <button type="button" data-tool="aoi" title="Zone tactique">▣</button>
        <button type="button" data-tool="route" title="Route">↗</button>
        <button type="button" data-tool="split" title="Découper une zone">✂</button>
        <button type="button" data-tool="eta" title="ETA pied / véhicule">⏱</button>
        <button type="button" data-tool="profile" title="Profil d’élévation">⛰</button>
        <button type="button" data-tool="circle" title="Cercle">○</button>
        <button type="button" data-tool="rect" title="Rectangle">▭</button>
        <button type="button" data-tool="freehand" title="Croquis à main levée">✎</button>
        <button type="button" data-tool="text" title="Texte">T</button>
        <button type="button" data-tool="bearing" title="Cap et distance">⊕</button>
        <button type="button" data-tool="los" title="Visée / masque du relief">◉</button>
        <button type="button" data-tool="po" title="Point à atteindre (20 m)">①</button>
        <button type="button" data-tool="rally" title="Point de ralliement (50 m)">⚑</button>
        <span></span>
        <button type="button" data-tool="undo" title="Annuler le dernier tracé">↩</button>
        <button type="button" data-tool="refresh" title="Actualiser">↻</button>
      </div>
      <div id="ow-map" aria-label="Carte tactique temps réel"></div>
      <div class="ow-map-tools">
        <button type="button" class="is-active" data-view="overwatch">AO LIVE</button>
        <button type="button" data-view="comms">COMMS</button>
        <button type="button" data-view="mission">MISSION</button>
        <button type="button" data-view="layers">LAYERS</button>
        <button type="button" data-view="intel">INTEL</button>
        <button type="button" data-ow-replay>REPLAY</button>
        <button type="button" data-ow-panel="calcs">CALCULS</button>
        <button type="button" data-ow-panel="osint">OSINT</button>
        <button type="button" data-ow-panel="sats">SAT</button>
        <button type="button" data-ow-panel="logs">JOURNAL</button>
        <button type="button" data-ow-compact>CARTE SEULE</button>
        <button type="button" data-command>⌘ K</button>
      </div>
      <div class="ow-coordinate" id="ow-coordinate">GRID — · LIVE</div>
      <div class="ow-empty" id="ow-empty" hidden><b>AUCUNE TÉLÉMÉTRIE</b><span>En attente des contacts autorisés pour cette communauté.</span></div>
      <div class="ow-context" id="ow-context" hidden>
        <div class="ow-context-head" id="ow-ctx-head">GRILLE</div>
        <button type="button" data-ctx="marker">Marqueur <span>✚</span></button>
        <button type="button" data-ctx="ping">Quick Ping <span>•</span></button>
        <button type="button" data-ctx="aoi">Zone tactique / AOI <span>⬡</span></button>
        <button type="button" data-ctx="route">Route <span>↗</span></button>
        <button type="button" data-ctx="intel">Observation Intel / SSE <span>▣</span></button>
        <button type="button" data-ctx="sitrep">SITREP géolocalisé <span>→</span></button>
        <button type="button" data-ctx="circle">Cercle <span>○</span></button>
        <button type="button" data-ctx="rect">Rectangle <span>▭</span></button>
        <button type="button" data-ctx="bearing">Cap / distance <span>⊕</span></button>
        <button type="button" data-ctx="measure">Mesurer <span>⌁</span></button>
        <button type="button" data-ctx="po">Point à atteindre (20 m) <span>①</span></button>
        <button type="button" data-ctx="rally">Point de ralliement (50 m) <span>⚑</span></button>
        <button type="button" data-ctx="los">Visée / masque <span>◉</span></button>
        <button type="button" data-ctx="ring">Anneau 250 m <span>○</span></button>
        <button type="button" data-ctx="chatgrid">Envoyer la grille au canal <span>→</span></button>
        <button type="button" data-ctx="copy">Copier les coordonnées <span>⧉</span></button>
      </div>
      <div class="ow-timeline" id="ow-timeline" hidden>
        <span class="ow-green" id="ow-replay-live">LIVE</span>
        <input type="range" id="ow-replay-scrub" min="0" max="100" value="100" aria-label="Rejouer les trajectoires">
        <span id="ow-replay-now">NOW</span>
      </div>
      <div class="ow-north" id="ow-north" aria-hidden="true">N</div>
      <div class="ow-live-measure" id="ow-live-measure" hidden></div>
      <div class="ow-data-hud" id="ow-data-hud">GROUPES — · AMI 0 · HOSTILE 0</div>
      <div class="ow-wx" id="ow-wx" hidden></div>
      <div class="ow-toast" id="ow-toast" hidden><small>ATHENA</small><p id="ow-toast-text"></p></div>
    </section>

    <aside class="ow-chat" id="ow-chat" aria-label="Tchat opérationnel">
      <header><span>●</span> COMMS</header>
      <div class="ow-tabs" role="tablist">
        <button type="button" class="is-active" data-chat-tab="channels">CANAUX</button>
        <button type="button" data-chat-tab="contacts">CONTACTS</button>
        <button type="button" data-chat-tab="squads">GROUPES</button>
        <button type="button" data-chat-tab="support">SUPPORT</button>
      </div>
      <div class="ow-chat-main" data-chat-panel="channels">
        <label class="ow-search"><span>⌕</span><input id="ow-channel-filter" type="search" placeholder="Canal ou indicatif…"></label>
        <div class="ow-section-label">CANAUX</div>
        <div id="ow-channel-list" class="ow-channel-list"></div>
        <div class="ow-section-label">FIL</div>
        <div id="ow-chat-log" class="ow-chat-log" aria-live="polite"></div>
        <form class="ow-chat-compose" id="ow-chat-form">
          <input id="ow-chat-input" maxlength="500" placeholder="Message sur le canal actif…" autocomplete="off">
          <button type="submit">ENVOYER</button>
        </form>
      </div>
      <div class="ow-chat-main" data-chat-panel="contacts" hidden>
        <label class="ow-search"><span>⌕</span><input id="ow-search" type="search" placeholder="Filtrer callsign, groupe, rôle…"></label>
        <label class="ow-row" style="margin:0 10px 8px">Afficher
          <select id="ow-side-filter">
            <option value="all">Tous</option>
            <option value="friendly">Amis</option>
            <option value="hostile">Hostiles</option>
            <option value="unknown">Inconnus</option>
          </select>
        </label>
        <div class="ow-section-label">LIVE / CONTACTS AUTORISÉS</div>
        <div id="ow-contact-list" class="ow-contact-list" aria-live="polite"></div>
      </div>
      <div class="ow-chat-main" data-chat-panel="squads" hidden>
        <p class="ow-help">Les opérateurs d’un même groupe sont reliés sur la carte. Cliquez un groupe pour le cadrer. Transmettez une tâche au groupe, ou envoyez une alerte qui recouvre l’écran des téléphones ATAK, y compris en position mini.</p>
        <div id="ow-group-task-host"></div>
        <div id="ow-fs-alert-host"></div>
        <div class="ow-section-label">GROUPES SUR LA CARTE</div>
        <div id="ow-squad-list" class="ow-contact-list" aria-live="polite"></div>
      </div>
      <div class="ow-chat-main" data-chat-panel="support" hidden>
        <p class="ow-help">File séparée du tchat tactique, pour un problème technique ou une assistance.</p>
        <div id="ow-support-log" class="ow-chat-log"></div>
        <form class="ow-chat-compose" id="ow-support-form">
          <input id="ow-support-input" maxlength="500" placeholder="Décrire le problème…" autocomplete="off">
          <button type="submit">SIGNALER</button>
        </form>
      </div>
    </aside>

    <aside class="ow-drawer" id="ow-drawer" hidden>
      <header><small id="ow-drawer-kicker">PANNEAU</small><button type="button" data-close-drawer>×</button><h2 id="ow-drawer-title">CONTACT</h2></header>
      <div id="ow-drawer-body"></div>
    </aside>
  </main>

  <footer class="ow-footer">
    <b id="ow-footer-link">ATHENA ● SYNCHRONISATION</b>
    <span id="ow-latency">RX —</span>
    <span id="ow-bft-count">BFT 0</span>
    <span id="ow-map-name"><?= $h(strtoupper($slug)) ?></span>
    <span id="ow-weather-chip">MÉTÉO —</span>
    <span id="ow-cache-label">CACHE TUILES</span>
    <span class="ow-footer-end">OVERWATCH // BETA</span>
  </footer>
</div>

<div class="ow-palette" id="ow-palette" hidden>
  <div>
    <input id="ow-command-input" placeholder="Rechercher une unité, une commande, un outil…">
    <div id="ow-palette-results"></div>
    <p>↑↓ NAVIGUER &nbsp; ENTER EXÉCUTER &nbsp; ESC FERMER</p>
  </div>
</div>

<div class="ow-disclaimer" id="ow-disclaimer" hidden>
  <div class="ow-disclaimer-card" role="dialog" aria-modal="true" aria-labelledby="ow-disclaimer-title">
    <p class="ow-kicker">ATHENA / OVERWATCH</p>
    <h1 id="ow-disclaimer-title">Espace de travail en accès anticipé</h1>
    <p>Cette carte de poste affiche uniquement la situation autorisée pour votre compte, transmise par la liaison de la communauté. Ce n’est pas le téléphone emporté en jeu.</p>
    <p>Les positions, messages et photos viennent de la mission en cours. Un rôle plus restreint ne verra pas davantage ici qu’au poste habituel.</p>
    <label class="ow-toggle"><input type="checkbox" id="ow-disclaimer-hide"> Ne plus afficher cet avertissement</label>
    <button type="button" class="ow-primary" id="ow-disclaimer-ok">ENTRER DANS OVERWATCH</button>
  </div>
</div>

<script src="<?= $h($base) ?>/assets/vendor/leaflet-1.9.4/leaflet.js"></script>
<script src="<?= $h($base) ?>/assets/js/atak-map-crs.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-aerial.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-beta.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-gotak.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-realtime.js?v=<?= $h($assetVer) ?>"></script>
<script src="<?= $h($base) ?>/assets/js/atak-overwatch-p2.js?v=<?= $h($assetVer) ?>"></script>
</body>
</html>
