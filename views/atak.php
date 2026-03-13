<?php
$base = url('');
$atakToken = $atakToken ?? '';
$nodeAtakUrl = $nodeAtakUrl ?? '';
$atakConfig = $atakConfig ?? null;
$atakMapConfig = $atakMapConfig ?? null;
$hasGameConfig = $atakConfig && ($atakConfig['arma_server_host'] ?? $atakConfig['arma_mod_credentials'] ?? $atakConfig['instructions'] ?? null);
$atakMapConfigForJs = null;
if ($atakMapConfig) {
  $c = $atakMapConfig['config'] ?? [];
  $atakMapConfigForJs = [
    'slug' => $atakMapConfig['slug'] ?? 'altis',
    'tilePattern' => $base . ($atakMapConfig['tile_pattern'] ?? '/assets/maps/altis/{z}/{x}/{y}.png'),
    'center' => $c['center'] ?? [15000, 15000],
    'defaultZoom' => (int)($c['defaultZoom'] ?? 3),
    'minZoom' => (int)($c['minZoom'] ?? 0),
    'maxZoom' => (int)($c['maxZoom'] ?? 6),
    'tileSize' => (int)($c['tileSize'] ?? 212),
    'attribution' => $c['attribution'] ?? '&copy; Bohemia Interactive',
    'crs' => $c['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
  ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>COMSPEC ATAK | Carte tactique Arma 3</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link href="<?= $base ?>/assets/css/atak.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <script>
    window.ATAK_TOKEN = <?= json_encode($atakToken) ?>;
    window.NODE_ATAK_URL = <?= json_encode($nodeAtakUrl) ?>;
    window.ATAK_TEAM_CONFIG = <?= json_encode($atakConfig ?: new stdClass()) ?>;
    <?php if ($atakMapConfigForJs): ?>window.ATAK_MAP_CONFIG = <?= json_encode($atakMapConfigForJs) ?>;<?php endif; ?>
  </script>
</head>
<body class="atak-page">
  <header class="atak-header">
    <div class="atak-logo-wrap">
      <span class="atak-logo">COMSPEC</span>
      <span class="atak-overwatch">OVERWATCH</span>
    </div>
    <div class="atak-zulu" id="atak-zulu">--:--:-- Z</div>
    <div class="atak-status" id="atak-status">
      <span class="dot"></span>
      <span>Réseau actif</span>
    </div>
    <a href="<?= url('dashboard') ?>" style="color: var(--atak-muted); font-size: 0.75rem;">Dashboard</a>
  </header>

  <?php if ($hasGameConfig): ?>
  <div class="atak-game-config" id="atak-game-config" style="background: rgba(0,0,0,0.85); border-bottom: 1px solid var(--atak-border); padding: 0.5rem 1rem; font-size: 0.75rem;">
    <button type="button" id="atak-game-config-toggle" style="color: var(--atak-muted); cursor: pointer;">▼ Configuration pour le jeu (Arma / mod)</button>
    <div id="atak-game-config-body" style="display:none; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--atak-border);">
      <?php if (!empty($atakConfig['arma_server_host'])): ?>
        <p><strong>Serveur Arma :</strong> <?= htmlspecialchars($atakConfig['arma_server_host']) ?><?= !empty($atakConfig['arma_server_port']) ? ':' . (int)$atakConfig['arma_server_port'] : '' ?></p>
      <?php endif; ?>
      <?php if (!empty($atakConfig['arma_mod_credentials'])): ?>
        <p><strong>Identifiants / config mod :</strong></p>
        <pre style="white-space: pre-wrap; word-break: break-all; margin: 0.25rem 0; font-size: 0.7rem;"><?= htmlspecialchars($atakConfig['arma_mod_credentials']) ?></pre>
      <?php endif; ?>
      <?php if (!empty($atakConfig['instructions'])): ?>
        <p><strong>Instructions :</strong></p>
        <p style="white-space: pre-wrap; margin: 0.25rem 0;"><?= nl2br(htmlspecialchars($atakConfig['instructions'])) ?></p>
      <?php endif; ?>
      <p style="margin-top: 0.5rem;"><a href="<?= url('atak/tuto') ?>" style="color: var(--atak-muted); text-decoration: underline;">Guide complet — Tuto mod Arma</a></p>
    </div>
  </div>
  <?php endif; ?>

  <div class="atak-connection-lost" id="atak-connection-lost">Connexion perdue. Reconnexion…</div>

  <div class="atak-main">
    <aside class="atak-panel-left" id="atak-panel-left">
      <div class="atak-tabs-headers">
        <button type="button" class="atak-tab active" data-tab="cams">Cams</button>
        <button type="button" class="atak-tab" data-tab="chat">Tchat</button>
        <button type="button" class="atak-tab" data-tab="pings">Pings</button>
        <button type="button" class="atak-tab" data-tab="jtac">JTAC</button>
        <button type="button" class="atak-toggle" id="atak-toggle-left" title="Réduire">◀</button>
      </div>
      <div class="atak-tabs-content active" id="tab-cams">
        <div class="atak-cams-list" id="atak-cams-list">
          <p class="atak-muted" style="padding: 0.5rem; font-size: 0.8rem;">Aucun flux. Connectez une helmet cam ou CTAB.</p>
        </div>
        <div style="padding: 0.5rem; border-top: 1px solid var(--atak-border);">
          <label style="font-size: 0.75rem;">Photo CTAB <input type="file" id="atak-intel-upload" accept="image/*" style="display:block;margin-top:4px;font-size:0.8rem;" /></label>
        </div>
        <div id="atak-intel-photos"></div>
      </div>
      <div class="atak-tabs-content" id="tab-chat">
        <div class="atak-chat-messages" id="atak-chat-messages"></div>
        <div class="atak-chat-input-wrap">
          <input type="text" id="atak-chat-input" placeholder="Envoyer un message…" />
          <button type="button" id="atak-chat-send">Envoyer</button>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-pings">
        <div class="atak-pings-list" id="atak-pings-list"></div>
      </div>
      <div class="atak-tabs-content" id="tab-jtac">
        <div class="atak-jtac-form">
          <button type="button" id="atak-jtac-new">Nouvelle 9-Line CAS</button>
          <div id="atak-jtac-form-fields" style="display:none;">
            <label>1. Type (IP/FFP/CAS/…) <input name="line1" /></label>
            <label>2. Position <input name="line2" /></label>
            <label>3. Élévation <input name="line3" /></label>
            <label>4. Cible <input name="line4" /></label>
            <label>5. Marqueur <input name="line5" /></label>
            <label>6. Ami / ennemi <input name="line6" /></label>
            <label>7. Retrait <input name="line7" /></label>
            <label>8. Autres <input name="line8" /></label>
            <label>9. Remarques <textarea name="line9"></textarea></label>
            <button type="button" id="atak-jtac-submit">Envoyer 9-Line</button>
          </div>
        </div>
        <div class="atak-jtac-list" id="atak-jtac-list"></div>
      </div>
    </aside>

    <div class="atak-map-wrap">
      <div id="atak-map"></div>
    </div>

    <aside class="atak-panel-right" id="atak-panel-right">
      <div class="atak-units-header">
        <div class="atak-units-title">All Workspaces</div>
        <div class="atak-filter">
          <input type="text" id="atak-units-filter" placeholder="Q Filter contacts..." />
          <button type="button" class="btn-live active" id="atak-filter-live">LIVE</button>
          <button type="button" class="btn-all" id="atak-filter-all">ALL</button>
        </div>
        <button type="button" class="atak-toggle" id="atak-toggle-right" title="Réduire" style="margin-left:auto;">▶</button>
      </div>
      <div class="atak-units-list" id="atak-units-list">
        <div class="atak-units-empty" id="atak-units-empty">
          <div class="atak-units-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
          </div>
          <span>No contacts connected</span>
        </div>
      </div>
    </aside>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.socket.io/4.7.0/socket.io.min.js"></script>
  <script src="<?= $base ?>/assets/js/atak-map-crs.js"></script>
  <?php if (!$atakMapConfigForJs): ?><script src="<?= $base ?>/assets/js/maps/altis.js"></script><?php endif; ?>
  <script src="<?= $base ?>/assets/js/atak-map.js"></script>
  <script src="<?= $base ?>/assets/js/atak-socket.js"></script>
  <script src="<?= $base ?>/assets/js/atak-units.js"></script>
  <script src="<?= $base ?>/assets/js/atak-chat.js"></script>
  <script src="<?= $base ?>/assets/js/atak-pings.js"></script>
  <script src="<?= $base ?>/assets/js/atak-jtac.js"></script>
  <script src="<?= $base ?>/assets/js/atak-cams.js"></script>
  <script>
    (function () {
      var mapId = 1;
      ATAKMap.init(mapId);

      function updateZulu() {
        var d = new Date();
        var h = d.getUTCHours(), m = d.getUTCMinutes(), s = d.getUTCSeconds();
        var z = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' Z';
        var el = document.getElementById('atak-zulu');
        if (el) el.textContent = z;
      }
      updateZulu();
      setInterval(updateZulu, 1000);

      ATAKSocket.connect({
        mapId: mapId,
        serverUrl: window.ATAKSocket.getApiBase(),
        onConnect: function () {
          document.getElementById('atak-status').classList.remove('offline');
          document.getElementById('atak-connection-lost').classList.remove('show');
        },
        onConnectionLost: function () {
          document.getElementById('atak-status').classList.add('offline');
          document.getElementById('atak-connection-lost').classList.add('show');
        }
      });

      document.querySelectorAll('.atak-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var tab = this.getAttribute('data-tab');
          document.querySelectorAll('.atak-tab').forEach(function (b) { b.classList.remove('active'); });
          document.querySelectorAll('.atak-tabs-content').forEach(function (c) { c.classList.remove('active'); });
          this.classList.add('active');
          var content = document.getElementById('tab-' + tab);
          if (content) content.classList.add('active');
        });
      });

      document.getElementById('atak-toggle-left').addEventListener('click', function () {
        document.getElementById('atak-panel-left').classList.toggle('collapsed');
        this.textContent = document.getElementById('atak-panel-left').classList.contains('collapsed') ? '▶' : '◀';
      });
      document.getElementById('atak-toggle-right').addEventListener('click', function () {
        document.getElementById('atak-panel-right').classList.toggle('collapsed');
        this.textContent = document.getElementById('atak-panel-right').classList.contains('collapsed') ? '◀' : '▶';
      });

      if (window.ATAKUnits) ATAKUnits.fetchUnits();
      if (window.ATAKChat) ATAKChat.fetchMessages();
      if (window.ATAKPings) ATAKPings.fetchPings();
      if (window.ATAKJTAC) ATAKJTAC.fetchNineLines();
      if (window.ATAKCams) ATAKCams.fetchIntelPhotos();

      var configToggle = document.getElementById('atak-game-config-toggle');
      var configBody = document.getElementById('atak-game-config-body');
      if (configToggle && configBody) {
        configToggle.addEventListener('click', function () {
          var open = configBody.style.display !== 'none';
          configBody.style.display = open ? 'none' : 'block';
          configToggle.textContent = (open ? '▼' : '▲') + ' Configuration pour le jeu (Arma / mod)';
        });
      }
    })();
  </script>
</body>
</html>
