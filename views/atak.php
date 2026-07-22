<?php
$base = url('');
$atakToken = $atakToken ?? '';
$nodeAtakUrl = $nodeAtakUrl ?? '';
$visitorIp = $visitorIp ?? '';
$atakConfig = $atakConfig ?? null;
$atakMapConfig = $atakMapConfig ?? null;
$atakMapsList = $atakMapsList ?? [];
$atakMapsConfigs = $atakMapsConfigs ?? [];
$atakWorkspaces = $atakWorkspaces ?? [];
$atakDefaultMapId = (int)($atakDefaultMapId ?? 1);
$atakCallsignToUser = $atakCallsignToUser ?? [];
$atakCaps = $atakCaps ?? [
    'loggedIn' => false,
    'canViewPersonnel' => false,
    'canLinkPersonnel' => false,
    'canRenameUnit' => false,
    'canDeleteUnitStaff' => false,
    'canDeleteOwnUnit' => false,
    'canPing' => true,
];
$currentUser = $currentUser ?? null;
$atakUserForJs = $atakUserForJs ?? null;
$atakUiPrefs = $atakUiPrefs ?? ['theme' => 'system', 'density' => 'comfortable'];
$canAccessAdminAtakConfig = $canAccessAdminAtakConfig ?? false;
$atakModDownloadUrl = $atakModDownloadUrl ?? null;
$hasGameConfig = $atakConfig && ($atakConfig['arma_server_host'] ?? $atakConfig['arma_mod_credentials'] ?? $atakConfig['instructions'] ?? null);
$atakMapConfigForJs = null;
if ($atakMapConfig) {
  $c = $atakMapConfig['config'] ?? [];
  $atakMapConfigForJs = [
    'slug' => $atakMapConfig['slug'] ?? 'altis',
    'tilePattern' => atak_resolve_tile_pattern(
        (string) ($atakMapConfig['tile_pattern'] ?? ''),
        (string) ($atakMapConfig['slug'] ?? 'altis')
    ),
    'center' => $c['center'] ?? [15000, 15000],
    'defaultZoom' => (int)($c['defaultZoom'] ?? 3),
    'minZoom' => (int)($c['minZoom'] ?? 0),
    'maxZoom' => (int)($c['maxZoom'] ?? 6),
    'tileSize' => (int)($c['tileSize'] ?? 212),
    'attribution' => $c['attribution'] ?? '&copy; Bohemia Interactive',
    'crs' => $c['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
    'offsetX' => isset($c['offset_x']) ? (float)$c['offset_x'] : 0,
    'offsetY' => isset($c['offset_y']) ? (float)$c['offset_y'] : 0,
  ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>COMSPEC ATAK | Carte tactique Arma 3</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/vendor/leaflet-1.9.4/leaflet.css" />
  <link href="<?= $base ?>/assets/css/atak.css" rel="stylesheet" />
  <link href="<?= $base ?>/assets/css/atak-map-popups.css" rel="stylesheet" />
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/halo-loader.css" rel="stylesheet" />
  <script>
    window.ATAK_TOKEN = <?= json_encode($atakToken) ?>;
    window.ATAK_API_BASE = <?= json_encode($base) ?>;
    window.NODE_ATAK_URL = '';
    window.ATAK_TEAM_CONFIG = <?= json_encode($atakConfig ?: new stdClass()) ?>;
    window.ATAK_USER = <?= json_encode($atakUserForJs ?: new stdClass()) ?>;
    <?php if ($atakMapConfigForJs): ?>window.ATAK_MAP_CONFIG = <?= json_encode($atakMapConfigForJs) ?>;<?php endif; ?>
    window.ATAK_MAPS_LIST = <?= json_encode(array_map(function ($m) { return ['slug' => $m['slug'] ?? '', 'label' => $m['label'] ?? $m['slug'] ?? 'Carte']; }, $atakMapsList)) ?>;
    window.ATAK_MAPS_CONFIGS = <?= json_encode($atakMapsConfigs) ?>;
    window.ATAK_WORKSPACES = <?= json_encode($atakWorkspaces) ?>;
    window.ATAK_DEFAULT_MAP_ID = <?= (int)$atakDefaultMapId ?>;
    window.ATAK_NODE_URL = <?= json_encode($nodeAtakUrl ?? '') ?>;
    window.ATAK_CALLSIGN_TO_USER = <?= json_encode($atakCallsignToUser) ?>;
    window.ATAK_CAPS = <?= json_encode($atakCaps ?? [
        'loggedIn' => false,
        'canViewPersonnel' => false,
        'canLinkPersonnel' => false,
        'canRenameUnit' => false,
        'canDeleteUnitStaff' => false,
        'canDeleteOwnUnit' => false,
        'canPing' => true,
        'canTriageMedical' => false,
    ]) ?>;
    window.ATAK_CAN_ISSUE_ORDERS = <?= !empty($currentUser) ? 'true' : 'false' ?>;
    window.ATAK_PROFILE_HINTS = <?= json_encode($atakProfileHints ?? [
        'suggestedRole' => 'operator',
        'suggestedSpecialties' => [],
    ]) ?>;
  </script>
</head>
<body class="atak-page atak-theme-<?= htmlspecialchars((string) ($atakUiPrefs['theme'] ?? 'system')) ?> atak-density-<?= htmlspecialchars((string) ($atakUiPrefs['density'] ?? 'comfortable')) ?>">
  <?php
  $baseUrl = $base;
  $haloLoaderHint = 'Préparation de la carte tactique…';
  $haloLoaderSeenKey = 'athena-halo-loader-atak';
  require base_path('views/partials/halo_loader.php');
  ?>
  <?php if (!empty($atakMaintenanceActive) && !empty($canAccessAdminAtakConfig)): ?>
  <div class="atak-maint-banner" role="status" style="position:relative;z-index:40;padding:0.65rem 1rem;background:#78350f;color:#fffbeb;font-size:0.85rem;text-align:center;">
    Mode maintenance actif — les opérateurs ne voient pas cette carte.
    <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>" style="color:#fde68a;font-weight:700;margin-left:0.5rem;">Gérer</a>
  </div>
  <?php endif; ?>
  <header class="atak-header">
    <div class="atak-header-brand">
      <div class="atak-logo-wrap">
        <span class="atak-logo">ATHENA</span>
        <span class="atak-overwatch">ATAK</span>
      </div>
      <span class="atak-header-tagline" title="État de la liaison">Liaison</span>
      <div class="atak-header-chips" aria-label="État de la carte">
        <span class="atak-chip atak-chip--live" id="atak-status" title="État du réseau">
          <span class="dot" aria-hidden="true"></span>
          <span class="atak-chip-label">Réseau actif</span>
        </span>
        <span class="atak-chip" id="atak-chip-liaison" title="Dernière activité reçue depuis le théâtre">
          <span class="atak-chip-key">Liaison</span>
          <span class="atak-chip-value" id="atak-chip-liaison-value">En attente</span>
        </span>
      </div>
    </div>
    <div class="atak-zulu" id="atak-zulu" title="Heure Zulu">--:--:-- Z</div>
    <div class="atak-header-links">
      <?php if (count($atakWorkspaces) > 1): ?>
      <label class="atak-header-select-wrap">
        <span class="atak-header-select-label">Serveur</span>
        <select id="atak-workspace-select" class="atak-header-select" title="Choisir le serveur / mission">
          <?php foreach ($atakWorkspaces as $w): ?>
          <option value="<?= (int)($w['mapId'] ?? 1) ?>" <?= ($w['mapId'] ?? 0) == $atakDefaultMapId ? 'selected' : '' ?>><?= htmlspecialchars($w['label'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
      <?php if (count($atakMapsList) > 1): ?>
      <label class="atak-header-select-wrap">
        <span class="atak-header-select-label">Carte</span>
        <select id="atak-map-select" class="atak-header-select" title="Choisir la carte">
          <?php foreach ($atakMapsList as $m): ?>
          <option value="<?= htmlspecialchars($m['slug'] ?? '') ?>" <?= ($atakMapConfig && ($atakMapConfig['slug'] ?? '') === ($m['slug'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($m['label'] ?? $m['slug'] ?? 'Carte') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
      <a href="<?= url('overwatch') ?>" class="atak-header-link" title="Carte C2 Overwatch">Overwatch</a>
      <a href="<?= url('dashboard') ?>" class="atak-header-link">Tableau de bord</a>
      <?php if ($currentUser): ?>
      <button type="button" class="atak-session-profile-chip" id="atak-session-profile-change" title="Modifier le profil de session" hidden>
        <span class="atak-session-profile-chip-key">Profil</span>
        <span class="atak-session-profile-chip-value" id="atak-session-profile-badge"></span>
      </button>
      <button type="button" class="atak-btn-game-link" id="atak-btn-game-link" title="Générer un code pour lier Arma à votre compte">Connexion en jeu</button>
      <button type="button" class="atak-btn-phone-link" id="atak-btn-phone-link" title="Générer un QR pour lier un téléphone au briefing">Lier un téléphone</button>
      <?php endif; ?>
      <button type="button" class="atak-btn-account atak-btn-account--config" id="atak-btn-config" title="Configuration pour le jeu">
        Configuration
        <span class="atak-pill <?= !empty($nodeAtakUrl) ? 'atak-pill--ok' : 'atak-pill--warn' ?>" id="atak-config-summary-pill"><?= !empty($nodeAtakUrl) ? 'Prête' : 'À compléter' ?></span>
      </button>
      <button type="button" class="atak-btn-account atak-btn-account--config" id="atak-btn-health" title="État de la liaison">
        État
        <span class="atak-pill atak-pill--muted" id="atak-health-summary-pill">Non vérifié</span>
      </button>
      <button type="button" class="atak-btn-account" id="atak-btn-account" title="Compte et paramètres">Compte</button>
    </div>
  </header>

  <div class="atak-os-strip" role="status" aria-live="polite" aria-label="Métriques de liaison">
    <p class="atak-os-strip-lead"><strong>ATAK Athena</strong></p>
    <ul class="atak-os-metrics" aria-label="Qualité des communications">
      <li class="atak-os-metric" id="atak-metric-quality" title="Qualité perçue de la liaison avec le portail">
        <span class="atak-os-metric-key">Qualité</span>
        <span class="atak-os-metric-value" id="atak-metric-quality-value">En attente de liaison</span>
      </li>
      <li class="atak-os-metric" id="atak-metric-latency" title="Délai aller-retour mesuré avec le portail">
        <span class="atak-os-metric-key">Latence</span>
        <span class="atak-os-metric-value" id="atak-metric-latency-value">—</span>
      </li>
      <li class="atak-os-metric" id="atak-metric-loss" title="Pertes de paquets (indisponible tant que le mod ne remonte pas cette mesure)">
        <span class="atak-os-metric-key">Pertes de paquets</span>
        <span class="atak-os-metric-value" id="atak-metric-loss-value">—</span>
      </li>
      <li class="atak-os-metric" id="atak-metric-theatre" title="Dernière activité reçue depuis le théâtre">
        <span class="atak-os-metric-key">Théâtre</span>
        <span class="atak-os-metric-value" id="atak-metric-theatre-value">En attente</span>
      </li>
    </ul>
    <p class="atak-os-strip-hint">Lier le mod : bouton <strong>Connexion en jeu</strong> → <strong>Générer un code</strong> → en jeu touche <strong>K</strong> → <strong>Compte Athena (saisir un code)</strong>.</p>
  </div>

  <div class="atak-account-overlay" id="atak-account-overlay" aria-hidden="true"></div>
  <aside class="atak-account-panel" id="atak-account-panel" aria-labelledby="atak-account-title">
    <div class="atak-account-panel-head">
      <h2 id="atak-account-title" class="atak-account-panel-title">Compte &amp; liaison</h2>
      <button type="button" class="atak-account-panel-close" id="atak-account-panel-close" aria-label="Fermer">×</button>
    </div>
    <div class="atak-account-panel-body">
      <?php if ($currentUser): ?>
      <section class="atak-account-section atak-account-section--game-link" id="atak-game-link-section">
        <div class="atak-game-link-head">
          <h3 class="atak-account-section-title">Connexion en jeu</h3>
          <span class="atak-pill atak-pill--ok">30 min · usage unique</span>
        </div>
        <p class="atak-game-link-hint">Générez un code, puis saisissez-le dans Arma : touche <strong>K</strong> → <strong>Compte Athena (saisir un code)</strong>. Le code expire après 30 minutes et ne peut être utilisé qu’une fois.</p>
        <button type="button" class="atak-game-link-btn" id="atak-game-link-btn">Générer un code</button>
        <div class="atak-game-link-result" id="atak-game-link-result" hidden>
          <p class="atak-game-link-code-label">Votre code</p>
          <p class="atak-game-link-code" id="atak-game-link-code">————</p>
          <p class="atak-game-link-meta" id="atak-game-link-meta"></p>
          <button type="button" class="atak-game-config-copy" id="atak-game-link-copy" title="Copier le code">Copier</button>
        </div>
        <p class="atak-game-link-error" id="atak-game-link-error" hidden></p>
      </section>
      <section class="atak-account-section atak-account-section--phone-link" id="atak-phone-link-section">
        <div class="atak-game-link-head">
          <h3 class="atak-account-section-title">Connexion téléphone</h3>
          <span class="atak-pill atak-pill--muted" id="atak-phone-link-pill">Non lié</span>
        </div>
        <p class="atak-game-link-hint" id="atak-phone-link-hint">Générez un code, puis scannez le QR avec le téléphone (ou saisissez le code sur la page de connexion). Même si un téléphone est déjà lié, vous pouvez en générer un nouveau pour un autre appareil.</p>
        <button type="button" class="atak-game-link-btn" id="atak-phone-link-btn">Générer un QR</button>
        <div class="atak-phone-link-result" id="atak-phone-link-result" hidden>
          <div class="atak-phone-link-qr-wrap">
            <img class="atak-phone-link-qr" id="atak-phone-link-qr" alt="QR code de connexion téléphone" width="200" height="200" />
          </div>
          <p class="atak-game-link-code-label">Code à saisir</p>
          <p class="atak-game-link-code" id="atak-phone-link-code">————</p>
          <p class="atak-game-link-meta" id="atak-phone-link-meta"></p>
          <div class="atak-phone-link-actions">
            <button type="button" class="atak-game-config-copy" id="atak-phone-link-copy" title="Copier le code">Copier le code</button>
            <a class="atak-phone-link-open" id="atak-phone-link-open" href="#" target="_blank" rel="noopener noreferrer">Ouvrir la page</a>
          </div>
        </div>
        <p class="atak-game-link-error" id="atak-phone-link-error" hidden></p>
      </section>
      <section class="atak-account-section">
        <h3 class="atak-account-section-title">Profil de session</h3>
        <p class="atak-game-link-hint">Rôle et spécialités pour cette session ATAK (mémorisés sur cet appareil). Ils déterminent les outils visibles (ordres, assistances, radio, 9-line).</p>
        <p id="atak-session-profile-account-summary" class="atak-session-profile-summary">Non défini</p>
        <button type="button" class="atak-game-link-btn" id="atak-session-profile-change-account">Modifier le profil</button>
      </section>
      <section class="atak-account-section">
        <h3 class="atak-account-section-title">Compte</h3>
        <p><strong>E-mail :</strong> <?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
        <p><strong>Nom affiché :</strong> <?= htmlspecialchars($currentUser['display_name'] ?? '') ?></p>
        <p><strong>Indicatif :</strong> <?= htmlspecialchars($currentUser['callsign'] ?? '—') ?></p>
        <p><a href="<?= url('account') ?>">Gérer mon compte</a></p>
      </section>
      <section class="atak-account-section">
        <h3 class="atak-account-section-title">Liaison Steam</h3>
        <?php
          $steamLinked = \App\Support\SteamId::normalize((string) ($currentUser['steam_id'] ?? ''));
        ?>
        <?php if ($steamLinked !== null): ?>
        <p>Compte Steam associé — utilisez ce numéro dans Arma (éditeur / solo) :</p>
        <div class="atak-game-config-url-wrap">
          <pre class="atak-game-config-url" id="atak-steam-id-copy"><?= htmlspecialchars($steamLinked) ?></pre>
          <button type="button" class="atak-game-config-copy" id="atak-copy-steam-id" title="Copier l’identifiant Steam">Copier</button>
        </div>
        <?php else: ?>
        <p>Non renseignée</p>
        <p class="atak-game-link-hint">Enregistrez le numéro vu en jeu, un identifiant Steam classique, ou l’adresse de votre profil public — depuis les préférences du compte. Sans cela, utilisez un code « Connexion en jeu ».</p>
        <?php endif; ?>
        <p><a href="<?= url('account/preferences') ?>">Modifier dans les préférences</a></p>
      </section>
      <section class="atak-account-section">
        <h3 class="atak-account-section-title">Indicatif Arma</h3>
        <p><?= !empty($currentUser['arma_callsign']) ? htmlspecialchars($currentUser['arma_callsign']) : 'Non renseigné' ?></p>
        <p><a href="<?= url('account/preferences') ?>">Modifier dans les préférences</a></p>
      </section>
      <section class="atak-account-section">
        <h3 class="atak-account-section-title">Serveur &amp; guides</h3>
        <?php if ($atakConfig && ($atakConfig['arma_server_host'] ?? '')): ?>
        <p><strong>Serveur :</strong> <?= htmlspecialchars($atakConfig['arma_server_host']) ?><?= !empty($atakConfig['arma_server_port']) ? ':' . (int)$atakConfig['arma_server_port'] : '' ?></p>
        <?php else: ?>
        <p>Aucun serveur Arma configuré pour la communauté.</p>
        <?php endif; ?>
        <p><a href="<?= url('atak/tuto') ?>">Guide du mod Arma</a></p>
        <?php if ($canAccessAdminAtakConfig): ?>
        <p><a href="<?= url('admin/atak-config') ?>">Configurer le mod et le serveur</a></p>
        <?php endif; ?>
      </section>
      <?php else: ?>
      <p>Connectez-vous pour générer un code de liaison et voir vos données de compte.</p>
      <p><a href="<?= url('login') ?>">Se connecter</a></p>
      <?php endif; ?>
      <section class="atak-account-section" id="atak-sound-prefs">
        <h3 class="atak-account-section-title">Son des alertes</h3>
        <p class="atak-game-link-hint">Sons d’événements (démarrage, déconnexion, inconscient, mort) + alertes courantes. Le volume et les modes silence sont aussi réglables dans la barre latérale.</p>
        <label class="atak-sound-pref-label" for="atak-alert-volume-account">
          <span class="atak-sound-pref-key">Volume des alertes</span>
          <input type="range" id="atak-alert-volume-account" class="atak-sound-pref-slider" min="0" max="100" step="1" value="70" title="Volume des alertes" aria-valuemin="0" aria-valuemax="100" aria-valuenow="70" />
        </label>
        <label class="atak-sound-pref-label" for="atak-notif-sound">
          <span class="atak-sound-pref-key">Choix du son</span>
          <select id="atak-notif-sound" class="atak-header-select atak-sound-pref-select" title="Son des alertes sur la carte">
            <option value="silent_vib">Mode silence (avec vibration)</option>
            <option value="stalker">Stalker</option>
            <option value="health">Alerte santé</option>
            <option value="mute">Mode silence sans vibration</option>
          </select>
        </label>
        <button type="button" class="atak-game-config-copy" id="atak-notif-sound-preview" title="Écouter le son choisi">Écouter</button>
      </section>
    </div>
  </aside>

  <aside class="atak-account-panel" id="atak-config-panel" aria-labelledby="atak-config-panel-title">
    <div class="atak-account-panel-head">
      <h2 id="atak-config-panel-title" class="atak-account-panel-title">Configuration pour le jeu</h2>
      <button type="button" class="atak-account-panel-close" id="atak-config-panel-close" aria-label="Fermer">×</button>
    </div>
    <div class="atak-account-panel-body">
      <?php if (!empty($nodeAtakUrl)): ?>
      <details class="atak-details" open>
        <summary>Adresse de liaison</summary>
        <div class="atak-details-body">
          <p class="atak-game-config-hint">À saisir dans le mod : Paramètres → Addons → COMSPEC Overwatch</p>
          <div class="atak-game-config-url-wrap">
            <pre class="atak-game-config-url" id="atak-node-url-copy"><?= htmlspecialchars($nodeAtakUrl) ?></pre>
            <button type="button" class="atak-game-config-copy" id="atak-copy-node-url" title="Copier l’adresse">Copier</button>
          </div>
        </div>
      </details>
      <?php else: ?>
      <p class="atak-game-config-warn" id="atak-no-node-url">Aucune adresse de serveur de liaison configurée. Complétez la <a href="<?= url('admin/atak-config') ?>">configuration ATAK</a> dans l’administration pour activer la liaison.</p>
      <?php endif; ?>
      <details class="atak-details">
        <summary>Votre adresse réseau (visiteur)</summary>
        <div class="atak-details-body">
          <p class="atak-game-config-value" id="atak-visitor-ip"><?= htmlspecialchars($visitorIp) ?: '—' ?></p>
        </div>
      </details>
      <?php if (!empty($atakModDownloadUrl)): ?>
      <details class="atak-details">
        <summary>Pack Overwatch</summary>
        <div class="atak-details-body">
          <p><a href="<?= htmlspecialchars($atakModDownloadUrl) ?>" class="atak-game-config-download">Télécharger le pack Overwatch</a></p>
        </div>
      </details>
      <?php endif; ?>
      <?php if (!empty($atakConfig['arma_server_host'])): ?>
      <details class="atak-details">
        <summary>Serveur Arma</summary>
        <div class="atak-details-body">
          <p class="atak-game-config-value"><?= htmlspecialchars($atakConfig['arma_server_host']) ?><?= !empty($atakConfig['arma_server_port']) ? ':' . (int)$atakConfig['arma_server_port'] : '' ?></p>
        </div>
      </details>
      <?php endif; ?>
      <?php if (!empty($atakConfig['arma_mod_credentials'])): ?>
      <details class="atak-details">
        <summary>Identifiants du mod</summary>
        <div class="atak-details-body">
          <pre class="atak-game-config-pre"><?= htmlspecialchars($atakConfig['arma_mod_credentials']) ?></pre>
        </div>
      </details>
      <?php endif; ?>
      <?php if (!empty($atakConfig['instructions'])): ?>
      <details class="atak-details">
        <summary>Instructions</summary>
        <div class="atak-details-body">
          <p class="atak-game-config-value atak-game-config-instructions"><?= nl2br(htmlspecialchars($atakConfig['instructions'])) ?></p>
        </div>
      </details>
      <?php endif; ?>
      <div class="atak-game-config-footer">
        <a href="<?= url('atak/setup') ?>" class="atak-game-config-link">Assistant Mod Arma (installation, config, vérification)</a>
        <a href="<?= url('atak/tuto') ?>" class="atak-game-config-link">Guide complet — Tuto mod Arma</a>
      </div>
    </div>
  </aside>

  <aside class="atak-account-panel" id="atak-health-panel" aria-labelledby="atak-health-panel-title">
    <div class="atak-account-panel-head">
      <h2 id="atak-health-panel-title" class="atak-account-panel-title">État de la liaison</h2>
      <button type="button" class="atak-account-panel-close" id="atak-health-panel-close" aria-label="Fermer">×</button>
    </div>
    <div class="atak-account-panel-body">
      <details class="atak-details" open>
        <summary>Liaison &amp; données</summary>
        <div class="atak-details-body atak-health-grid">
          <div class="atak-health-card">
            <span class="atak-health-label">Liaison carte</span>
            <span class="atak-health-cell" id="health-node-url">—</span>
            <span class="atak-pill" id="health-node-status">—</span>
          </div>
          <div class="atak-health-card">
            <span class="atak-health-label">Mises à jour</span>
            <span class="atak-health-cell" id="health-socket-state">—</span>
            <span class="atak-health-cell atak-health-muted" id="health-socket-url">—</span>
          </div>
          <div class="atak-health-card">
            <span class="atak-health-label">Données</span>
            <span class="atak-pill" id="health-db">—</span>
          </div>
          <div class="atak-health-card">
            <span class="atak-health-label">Mod en jeu</span>
            <span class="atak-health-cell" id="health-arma">—</span>
          </div>
        </div>
      </details>
      <details class="atak-details">
        <summary>Contacts &amp; échanges</summary>
        <div class="atak-details-body atak-health-grid">
          <div class="atak-health-card">
            <span class="atak-health-label">Contacts actifs</span>
            <span class="atak-health-cell" id="health-units-count">—</span>
            <span class="atak-health-cell atak-health-muted" id="health-active-callsigns">—</span>
          </div>
          <div class="atak-health-card">
            <span class="atak-health-label">Tchat</span>
            <span class="atak-health-cell" id="health-chat-error">Aucun incident</span>
          </div>
          <div class="atak-health-card">
            <span class="atak-health-label">Pings</span>
            <span class="atak-health-cell" id="health-pings-error">Aucun incident</span>
          </div>
        </div>
      </details>
      <div class="atak-game-config-footer">
        <button type="button" class="atak-health-refresh" id="atak-health-refresh">Actualiser</button>
      </div>
    </div>
  </aside>

  <div class="atak-connection-lost" id="atak-connection-lost" role="alert"><span id="atak-connection-lost-msg">Connexion perdue. Reconnexion…</span></div>
  <div class="atak-error-toast" id="atak-error-toast" role="alert" aria-live="polite"></div>
  <div class="atak-notification-toast" id="atak-notification-toast" role="status" aria-live="polite"></div>
  <div class="atak-medical-banner" id="atak-medical-banner" role="alert" aria-live="assertive" hidden></div>

  <?php if ($currentUser): ?>
  <div class="atak-session-profile-overlay" id="atak-session-profile-overlay" role="dialog" aria-modal="true" aria-labelledby="atak-session-profile-title" hidden>
    <div class="atak-session-profile-backdrop" aria-hidden="true"></div>
    <div class="atak-session-profile-card">
      <h2 id="atak-session-profile-title" class="atak-session-profile-title">Profil de session ATAK</h2>
      <p class="atak-session-profile-lead">Indiquez votre rôle pour cette session. Les spécialités débloquent des outils supplémentaires. Le choix est mémorisé sur cet appareil et reste modifiable à tout moment.</p>
      <p class="atak-session-profile-suggest" id="atak-session-profile-suggest" role="status"></p>
      <form id="atak-session-profile-form" class="atak-session-profile-form">
        <fieldset class="atak-session-profile-fieldset">
          <legend>Rôle</legend>
          <label class="atak-session-profile-option">
            <input type="radio" name="atak-session-role" value="commander" />
            <span>
              <strong>Commandant d’unité</strong>
              <small>Pilote les ordres et la manœuvre d’ensemble.</small>
            </span>
          </label>
          <label class="atak-session-profile-option">
            <input type="radio" name="atak-session-role" value="deputy" />
            <span>
              <strong>Commandant adjoint</strong>
              <small>Appuie le commandant ; mêmes outils de conduite.</small>
            </span>
          </label>
          <label class="atak-session-profile-option">
            <input type="radio" name="atak-session-role" value="operator" checked />
            <span>
              <strong>Exécutant</strong>
              <small>Suit les ordres reçus et remonte la situation.</small>
            </span>
          </label>
        </fieldset>
        <fieldset class="atak-session-profile-fieldset">
          <legend>Spécialités (facultatif)</legend>
          <label class="atak-session-profile-option" for="atak-spec-medic">
            <input type="checkbox" id="atak-spec-medic" value="medic" />
            <span>
              <strong>Médecin</strong>
              <small>Assistances, triage et choix médicaux.</small>
            </span>
          </label>
          <label class="atak-session-profile-option" for="atak-spec-radio">
            <input type="checkbox" id="atak-spec-radio" value="radio" />
            <span>
              <strong>Transmetteur</strong>
              <small>Radio proximité et suivi des émissions.</small>
            </span>
          </label>
          <label class="atak-session-profile-option" for="atak-spec-jtac">
            <input type="checkbox" id="atak-spec-jtac" value="jtac" />
            <span>
              <strong>JTAC</strong>
              <small>Appui aérien et 9-line.</small>
            </span>
          </label>
        </fieldset>
        <div class="atak-session-profile-actions">
          <button type="button" class="atak-session-profile-btn atak-session-profile-btn--ghost" id="atak-session-profile-reset">Repartir des suggestions</button>
          <button type="submit" class="atak-session-profile-btn atak-session-profile-btn--primary">Continuer</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="atak-main">
    <aside class="atak-panel-left" id="atak-panel-left">
      <div class="atak-left-rail">
      <nav class="atak-left-aside" role="tablist" aria-label="Panneaux latéraux">
        <button type="button" class="atak-tab active" role="tab" aria-selected="true" data-tab="cams" title="Cams"><span class="atak-tab-label">Cams</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="markers" title="Marqueurs"><span class="atak-tab-label">Marqueurs</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="chat" title="Tchat"><span class="atak-tab-label">Tchat</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="orders" title="Ordres"><span class="atak-tab-label">Ordres</span> <span class="atak-tab-badge" id="atak-orders-tab-badge" hidden></span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="medical" title="Assistances"><span class="atak-tab-label">Assistances</span> <span class="atak-tab-badge atak-medical-tab-badge" id="atak-medical-tab-badge" hidden></span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="radio" title="Radio proximité"><span class="atak-tab-label">Radio</span> <span class="atak-tab-badge" id="atak-radio-tab-badge" hidden></span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="pings" title="Pings"><span class="atak-tab-label">Pings</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="jtac" title="JTAC"><span class="atak-tab-label">JTAC</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="liaison" title="Liaison"><span class="atak-tab-label">Liaison</span> <span class="atak-tab-badge" id="atak-liaison-tab-badge" hidden></span></button>
      </nav>
      <section class="atak-rail-audio" id="atak-rail-audio" aria-label="Réglages des alertes">
        <h3 class="atak-rail-audio-title">Alertes</h3>
        <label class="atak-rail-audio-vol" for="atak-alert-volume">
          <span class="atak-rail-audio-vol-label">Volume des alertes <span id="atak-alert-volume-value" aria-hidden="true">70</span>%</span>
          <input type="range" id="atak-alert-volume" class="atak-rail-audio-slider" min="0" max="100" step="1" value="70" title="Volume des alertes" aria-valuemin="0" aria-valuemax="100" aria-valuenow="70" />
        </label>
        <label class="atak-rail-audio-opt" for="atak-alert-silence" title="Coupe les sons ; garde une vibration courte si l’appareil le permet. Les bandeaux d’alerte restent visibles.">
          <input type="checkbox" id="atak-alert-silence" />
          <span>Mode silence</span>
        </label>
        <label class="atak-rail-audio-opt" for="atak-alert-silence-novib" title="Coupe les sons et toute vibration. Les bandeaux d’alerte restent visibles.">
          <input type="checkbox" id="atak-alert-silence-novib" />
          <span>Mode silence sans vibration</span>
        </label>
        <p class="atak-rail-audio-hint" id="atak-alert-mute-hint" hidden role="status"></p>
      </section>
      </div>
      <div class="atak-left-body">
      <div class="atak-tabs-content active" id="tab-cams">
        <div class="atak-cams-list" id="atak-cams-list">
          <div class="atak-empty-state">
            <div class="atak-empty-state-icon" aria-hidden="true">▣</div>
            <p class="atak-empty-state-title">Aucun flux</p>
            <p class="atak-empty-state-text">Les photos CTAB envoyées depuis Arma apparaîtront ici.</p>
          </div>
        </div>
        <div id="atak-intel-photos"></div>
      </div>
      <div class="atak-tabs-content" id="tab-markers">
        <div class="atak-markers-list" id="atak-markers-list">
          <div class="atak-empty-state">
            <div class="atak-empty-state-icon" aria-hidden="true">⌖</div>
            <p class="atak-empty-state-title">Aucun marqueur</p>
            <p class="atak-empty-state-text">Clic droit sur la carte → Placer un marqueur.</p>
          </div>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-chat">
        <div class="atak-panel-strip">
          <span class="atak-panel-strip-title">Journal radio</span>
          <div class="atak-panel-strip-actions">
            <span class="atak-panel-strip-badge">SQUAD</span>
            <button type="button" class="atak-chat-clear" id="atak-chat-clear" title="Vider l’affichage du tchat (l’historique serveur n’est pas effacé)" aria-label="Vider le tchat">
              <svg class="atak-chat-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="atak-chat-messages" id="atak-chat-messages">
          <div class="atak-empty-state atak-empty-state--compact" id="atak-chat-empty">
            <p class="atak-empty-state-title">Aucun message</p>
            <p class="atak-empty-state-text">Les échanges radio de l’équipe s’afficheront ici.</p>
          </div>
        </div>
        <div class="atak-chat-input-wrap">
          <input type="text" id="atak-chat-input" placeholder="Émettre" autocomplete="off" spellcheck="false" />
          <button type="button" class="atak-chat-send" id="atak-chat-send" title="Envoyer" aria-label="Envoyer">
            <svg class="atak-chat-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
              <path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-orders" role="tabpanel">
        <p class="atak-panel-hint">Ordres tactiques émis depuis la carte ou reçus du théâtre. Confirmez la réception, suivez l’exécution ou annulez si besoin. Un léger délai radio peut s’appliquer côté destinataire.</p>
        <div class="atak-orders-issue" id="atak-orders-issue" hidden>
          <div class="atak-orders-issue-grid">
            <label class="atak-orders-field">
              <span>Type d’ordre</span>
              <select id="atak-order-type">
                <option value="MOVE">Se déplacer</option>
                <option value="HOLD">Tenir la position</option>
                <option value="RECON">Reconnaissance</option>
                <option value="CAS">Appui aérien</option>
                <option value="QRF">Force de réaction</option>
              </select>
            </label>
            <label class="atak-orders-field">
              <span>Priorité</span>
              <select id="atak-order-priority">
                <option value="ROUTINE">Routine</option>
                <option value="IMPORTANT" selected>Important</option>
                <option value="URGENT">Urgent</option>
                <option value="CONTACT">Contact</option>
              </select>
            </label>
            <label class="atak-orders-field">
              <span>Type de destinataire</span>
              <select id="atak-order-target-type">
                <option value="all">Toute l’équipe</option>
                <option value="user">Utilisateur</option>
                <option value="group">Groupe en jeu</option>
                <option value="fire_team">Fire team</option>
                <option value="channel">Canal</option>
                <option value="solo">ATAK Solo</option>
              </select>
            </label>
            <label class="atak-orders-field" id="atak-order-target-wrap" hidden>
              <span id="atak-order-target-label">Destinataire</span>
              <select id="atak-order-target-ref">
                <option value="">Choisir…</option>
              </select>
            </label>
            <label class="atak-orders-field atak-orders-field--wide">
              <span>Précisions</span>
              <input type="text" id="atak-order-payload" placeholder="Consignes complémentaires (facultatif)" maxlength="400" />
            </label>
            <label class="atak-orders-field atak-orders-field--wide atak-orders-check">
              <input type="checkbox" id="atak-order-radio-sim" checked />
              <span>Conditions radio réalistes (délai, brouillage fictif)</span>
            </label>
          </div>
          <button type="button" class="atak-order-issue-submit" id="atak-order-issue-btn">Émettre l’ordre</button>
        </div>
        <div class="atak-orders-list" id="atak-orders-list"></div>
        <div class="atak-empty-state" id="atak-orders-empty">
          <div class="atak-empty-state-icon" aria-hidden="true">☰</div>
          <p class="atak-empty-state-title">Aucun ordre</p>
          <p class="atak-empty-state-text">Les ordres émis depuis la carte ou remontés depuis le théâtre s’afficheront ici.</p>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-medical">
        <div class="atak-medical-head">
          <p class="atak-panel-hint">Alertes transmises depuis le théâtre (combattant au sol, rythme cardiaque à zéro) et unités à secourir. Les alertes disparaissent automatiquement après 30 minutes. Masquer une alerte la retire de cet écran uniquement — le journal Liaison et le tchat restent inchangés. Le triage (Traité, KIA, Annulé…) est réservé aux médecins et responsables d’effectifs.</p>
          <div class="atak-medical-toolbar">
            <button type="button" class="atak-medical-clear-all" id="atak-medical-clear-all" title="Masquer toutes les alertes affichées" hidden>Tout masquer</button>
          </div>
        </div>
        <div class="atak-medical-list" id="atak-medical-list">
          <div class="atak-empty-state atak-medical-empty">
            <div class="atak-empty-state-icon" aria-hidden="true">✚</div>
            <p class="atak-empty-state-title">Aucune assistance</p>
            <p class="atak-empty-state-text">Les demandes médicales en cours s’afficheront ici.</p>
          </div>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-radio">
        <div class="atak-radio-head" id="atak-radio-head">
          <p class="atak-panel-hint">Qui émet près d’un opérateur en liaison, et sur quel réseau. L’écoute audio se fait en jeu ; ici vous suivez qui émet (pastilles, liste, alertes). Sur la tablette Overwatch, « Surveiller ce réseau » bascule aussi le canal radio actif.</p>
          <div class="atak-radio-toolbar">
            <label class="atak-radio-field">
              <span>Opérateur de référence</span>
              <select id="atak-radio-focus">
                <option value="">Opérateur de référence (auto)</option>
              </select>
            </label>
            <label class="atak-radio-field">
              <span>Rayon (m)</span>
              <select id="atak-radio-radius">
                <option value="50">50</option>
                <option value="75" selected>75</option>
                <option value="100">100</option>
                <option value="150">150</option>
                <option value="200">200</option>
              </select>
            </label>
            <label class="atak-radio-check">
              <input type="checkbox" id="atak-radio-tx-only" />
              <span>Émissions uniquement</span>
            </label>
            <label class="atak-radio-check">
              <input type="checkbox" id="atak-radio-hide-nomodule" />
              <span>Masquer cet onglet si aucun module radio</span>
            </label>
          </div>
          <div class="atak-radio-listen-bar" id="atak-radio-listen-bar" hidden></div>
          <div class="atak-radio-banner" id="atak-radio-banner" hidden></div>
        </div>
        <div class="atak-radio-list" id="atak-radio-list">
          <div class="atak-empty-state">
            <div class="atak-empty-state-icon" aria-hidden="true">◎</div>
            <p class="atak-empty-state-title">En attente des effectifs</p>
            <p class="atak-empty-state-text">Les contacts en liaison apparaîtront ici avec leur état d’émission.</p>
          </div>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-pings">
        <div class="atak-pings-list" id="atak-pings-list">
          <div class="atak-empty-state">
            <div class="atak-empty-state-icon" aria-hidden="true">◎</div>
            <p class="atak-empty-state-title">Aucun ping</p>
            <p class="atak-empty-state-text">Clic droit sur la carte → Envoyer un ping.</p>
          </div>
        </div>
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
        <div class="atak-laser-codes-wrap" id="atak-laser-codes-wrap">
          <div id="atak-laser-codes-list"></div>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-liaison" role="tabpanel">
        <div class="atak-activity-panel">
          <section class="atak-web-presence" aria-labelledby="atak-web-presence-title">
            <h3 class="atak-web-presence-title" id="atak-web-presence-title">Sur le site</h3>
            <p class="atak-web-presence-intro">Opérateurs connectés à la carte Athena depuis le portail.</p>
            <ul class="atak-web-presence-list" id="atak-web-presence-list" aria-live="polite"></ul>
            <p class="atak-web-presence-empty" id="atak-web-presence-empty">En attente de liaison</p>
          </section>
          <div class="atak-activity-head">
            <p class="atak-activity-intro">Suivi des connexions en jeu, des changements d’indicatif et des échanges récents avec la carte.</p>
            <div class="atak-activity-toolbar">
              <div class="atak-activity-meta" id="atak-activity-meta" hidden>
                <span class="atak-pill atak-pill--ok" id="atak-activity-meta-count">0</span>
                <span class="atak-activity-meta-label" id="atak-activity-meta-label">événements récents</span>
              </div>
              <div class="atak-activity-actions">
                <a class="atak-activity-link" id="atak-activity-fullscreen" href="<?= $base ?>/atak/liaison">Voir tout</a>
                <button type="button" class="atak-activity-clear" id="atak-activity-clear" title="Mettre le journal de côté sans le supprimer">Vider</button>
              </div>
            </div>
          </div>
          <ul class="atak-activity-list" id="atak-activity-list" aria-live="polite"></ul>
          <div class="atak-empty-state atak-activity-empty" id="atak-activity-empty">
            <div class="atak-empty-state-icon" aria-hidden="true">⇄</div>
            <p class="atak-empty-state-title">Journal en attente</p>
            <p class="atak-empty-state-text">Connexions, indicatifs et échanges apparaîtront ici dès qu’un joueur est en liaison avec la carte.</p>
          </div>
        </div>
      </div>
      </div>
    </aside>

    <div class="atak-map-wrap">
      <div id="atak-map"></div>

      <div class="atak-drawer atak-drawer--fixed" id="atak-effectifs-drawer">
        <div class="atak-drawer__head">
          <span class="atak-drawer__title">Tableau des effectifs</span>
          <span class="atak-drawer__count" id="atak-effectifs-count" hidden></span>
        </div>
        <div class="atak-drawer__body" id="atak-effectifs-drawer-body">
          <table>
            <colgroup>
              <col class="atak-col-cs" />
              <col class="atak-col-role" />
              <col class="atak-col-link" />
              <col class="atak-col-hdg" />
              <col class="atak-col-grid" />
            </colgroup>
            <thead>
              <tr>
                <th scope="col">Indicatif</th>
                <th scope="col">Rôle</th>
                <th scope="col">Liaison</th>
                <th scope="col">Cap</th>
                <th scope="col">Grille</th>
              </tr>
            </thead>
            <tbody id="atak-units-table-body">
              <tr><td colspan="5" class="atak-drawer-empty">Aucun contact en liaison pour le moment.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <aside class="atak-panel-right" id="atak-panel-right">
      <div class="atak-air-assets-header">
        <div class="atak-air-assets-title">Appui aérien</div>
      </div>
      <div class="atak-air-assets-list" id="atak-air-assets-list">
        <div class="atak-air-assets-empty" id="atak-air-assets-empty">
          <span>Aucun aéronef déclaré. Les pilotes enregistrent un vol depuis le menu Overwatch en jeu (touche K).</span>
        </div>
      </div>
      <div class="atak-units-header">
        <div class="atak-units-title-row">
          <div class="atak-units-title">Effectifs</div>
        </div>
        <div class="atak-filter">
          <input type="text" id="atak-units-filter" placeholder="Filtrer par indicatif ou rôle…" />
          <button type="button" class="btn-live active" id="atak-filter-live">En liaison</button>
          <button type="button" class="btn-all" id="atak-filter-all">Tous</button>
        </div>
      </div>
      <div class="atak-units-list" id="atak-units-list">
        <div class="atak-units-empty" id="atak-units-empty">
          <div class="atak-units-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
          </div>
          <p class="atak-units-empty-title">Aucun contact en liaison</p>
          <p class="atak-units-empty-text">Les positions remontées depuis Arma s’affichent ici. Vérifiez la liaison du mod, ou générez un code via <strong>Connexion en jeu</strong>.</p>
        </div>
      </div>
    </aside>
  </div>

  <script src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/vendor/leaflet-1.9.4/leaflet.js"></script>
  <script src="<?= $base ?>/assets/js/atak-session-profile.js"></script>
  <script src="<?= $base ?>/assets/js/atak-map-crs.js"></script>
  <?php if (!$atakMapConfigForJs): ?><script src="<?= $base ?>/assets/js/maps/altis.js"></script><?php endif; ?>
  <script src="<?= $base ?>/assets/vendor/milsymbol/milsymbol.js"></script>
  <script src="<?= $base ?>/assets/vendor/milstd/milstd2525.js"></script>
  <script src="<?= $base ?>/assets/js/milstd-catalog.js"></script>
  <script src="<?= $base ?>/assets/js/nato-sidc-icons.js"></script>
  <script src="<?= $base ?>/assets/js/atak-symbol-picker.js"></script>
  <script src="<?= $base ?>/assets/js/atak-unit-popup.js"></script>
  <script src="<?= $base ?>/assets/js/atak-map.js"></script>
  <script src="<?= $base ?>/assets/js/atak-socket.js"></script>
  <script src="<?= $base ?>/assets/js/atak-units.js"></script>
  <script src="<?= $base ?>/assets/js/atak-chat.js"></script>
  <script src="<?= $base ?>/assets/js/atak-orders.js"></script>
  <script src="<?= $base ?>/assets/js/atak-medical-alerts.js"></script>
  <script src="<?= $base ?>/assets/js/atak-radio.js"></script>
  <script src="<?= $base ?>/assets/js/atak-pings.js"></script>
  <script src="<?= $base ?>/assets/js/atak-markers.js"></script>
  <script src="<?= $base ?>/assets/js/atak-map-shapes.js"></script>
  <script src="<?= $base ?>/assets/js/atak-context-menu.js"></script>
  <script src="<?= $base ?>/assets/js/atak-unit-menu.js"></script>
  <script src="<?= $base ?>/assets/js/atak-jtac.js"></script>
  <script src="<?= $base ?>/assets/js/atak-cams.js"></script>
  <script src="<?= $base ?>/assets/js/atak-air-assets.js"></script>
  <script src="<?= $base ?>/assets/js/atak-laser-codes.js"></script>
  <script src="<?= $base ?>/assets/js/atak-activity.js"></script>
  <script src="<?= $base ?>/assets/js/atak-sounds.js"></script>
  <script>
    (function () {
      window.ATAKShowError = function (msg) {
        var el = document.getElementById('atak-error-toast');
        if (!el) return;
        el.textContent = msg || 'Erreur';
        el.classList.add('show');
        clearTimeout(el._toastTimer);
        el._toastTimer = setTimeout(function () { el.classList.remove('show'); }, 4000);
      };
      window.ATAKShowNotification = function (msg, opts) {
        var el = document.getElementById('atak-notification-toast');
        if (!el) return;
        el.textContent = msg || '';
        el.classList.add('show');
        clearTimeout(el._toastTimer);
        el._toastTimer = setTimeout(function () { el.classList.remove('show'); }, 4000);
        opts = opts || {};
        if (opts.silent !== true && window.ATAKSounds && typeof window.ATAKSounds.play === 'function') {
          window.ATAKSounds.play();
        }
      };

      var bootDismissed = false;
      function dismissAtakBoot() {
        if (bootDismissed) return;
        bootDismissed = true;
        setTimeout(function () {
          try {
            if (window.ATAKMap && window.ATAKMap.getMap) {
              var m = window.ATAKMap.getMap();
              if (m) m.invalidateSize({ animate: false });
            }
          } catch (e) {}
        }, 50);
      }

      var bootAbort = new AbortController();
      var bootTimer = setTimeout(function () { bootAbort.abort(); }, 12000);
      var atakLiveConnectedOnce = false;

      function startAtakApplication() {
      var mapId = (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0) ? window.ATAK_DEFAULT_MAP_ID : 1;
      if (window.ATAKSocket && typeof window.ATAKSocket.setMapId === 'function') {
        ATAKSocket.setMapId(mapId);
      }
      var mapSelect = document.getElementById('atak-map-select');
      var workspaceSelect = document.getElementById('atak-workspace-select');
      if (workspaceSelect && window.ATAK_WORKSPACES && window.ATAK_WORKSPACES.length > 0) {
        workspaceSelect.addEventListener('change', function () {
          var mid = parseInt(this.value, 10);
          if (isNaN(mid)) return;
          if (window.ATAKSocket && typeof window.ATAKSocket.setMapId === 'function') {
            ATAKSocket.setMapId(mid);
          }
          if (window.ATAKUnits) ATAKUnits.fetchUnits();
          if (window.ATAKChat) ATAKChat.fetchMessages();
          if (window.ATAKMedicalAlerts) ATAKMedicalAlerts.fetchAlerts();
          if (window.ATAKOrders) ATAKOrders.fetchOrders();
          if (window.ATAKPings) ATAKPings.fetchPings();
          if (window.ATAKJTAC && window.ATAKJTAC.fetchCas) window.ATAKJTAC.fetchCas();
          else if (window.ATAKJTAC && window.ATAKJTAC.fetchNineLines) window.ATAKJTAC.fetchNineLines();
          if (window.ATAKMap && window.ATAKMap.pollMarkers) window.ATAKMap.pollMarkers();
          if (window.ATAKActivity && window.ATAKActivity.refresh) window.ATAKActivity.refresh();
        });
      }
      if (window.ATAK_MAPS_CONFIGS && window.ATAK_MAPS_LIST && window.ATAK_MAPS_LIST.length > 0) {
        try {
          var savedSlug = localStorage.getItem('atak_map_slug');
          if (savedSlug && window.ATAK_MAPS_CONFIGS[savedSlug]) {
            window.ATAK_MAP_CONFIG = window.ATAK_MAPS_CONFIGS[savedSlug];
            if (mapSelect) mapSelect.value = savedSlug;
          }
        } catch (e) {}
      }
      ATAKMap.init(mapId);
      if (mapSelect && window.ATAK_MAPS_CONFIGS) {
        mapSelect.addEventListener('change', function () {
          var slug = this.value;
          if (!slug || !window.ATAK_MAPS_CONFIGS[slug]) return;
          window.ATAK_MAP_CONFIG = window.ATAK_MAPS_CONFIGS[slug];
          try { localStorage.setItem('atak_map_slug', slug); } catch (e) {}
          ATAKMap.init(mapId);
        });
      }

      function updateZulu() {
        var d = new Date();
        var h = d.getUTCHours(), m = d.getUTCMinutes(), s = d.getUTCSeconds();
        var z = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' Z';
        var el = document.getElementById('atak-zulu');
        if (el) el.textContent = z;
      }
      updateZulu();
      setInterval(updateZulu, 1000);

      var statusEl = document.getElementById('atak-status');
      var connectionLostEl = document.getElementById('atak-connection-lost');
      function setNetworkChip(online) {
        if (!statusEl) return;
        var label = statusEl.querySelector('.atak-chip-label') || statusEl.querySelector('span:last-child');
        if (online) {
          statusEl.classList.remove('offline', 'atak-chip--off');
          statusEl.classList.add('atak-chip--live');
          if (label) label.textContent = 'Réseau actif';
        } else {
          statusEl.classList.add('offline', 'atak-chip--off');
          statusEl.classList.remove('atak-chip--live');
          if (label) label.textContent = 'Hors ligne';
        }
      }
      ATAKSocket.connect({
        mapId: mapId,
        onConnect: function () {
          var firstBoot = !atakLiveConnectedOnce;
          atakLiveConnectedOnce = true;
          dismissAtakBoot();
          setNetworkChip(true);
          if (connectionLostEl) connectionLostEl.classList.remove('show');
          if (firstBoot && window.ATAKSounds && typeof window.ATAKSounds.playEvent === 'function') {
            window.ATAKSounds.playEvent('start');
          }
        },
        onConnectionLost: function () {
          if (connectionLostEl) connectionLostEl.classList.add('show');
          setNetworkChip(false);
          if (atakLiveConnectedOnce) {
            if (window.ATAKSounds && typeof window.ATAKSounds.playEvent === 'function') {
              window.ATAKSounds.playEvent('disconnect');
            }
            window.ATAKShowError('La liaison en direct avec la Tacmap est interrompue. Les données se rafraîchissent de façon moins fréquente.');
          }
        }
      });
      setTimeout(function () { dismissAtakBoot(); }, 6000);

      function atakPoll() {
        if (window.ATAKUnits) ATAKUnits.fetchUnits();
        if (window.ATAKChat) ATAKChat.fetchMessages();
        if (window.ATAKMedicalAlerts) ATAKMedicalAlerts.fetchAlerts();
        if (window.ATAKOrders) ATAKOrders.fetchOrders();
        if (window.ATAKPings) ATAKPings.fetchPings();
        if (window.ATAKJTAC && window.ATAKJTAC.fetchCas) ATAKJTAC.fetchCas();
        else if (window.ATAKJTAC) ATAKJTAC.fetchNineLines();
        if (window.ATAKCams) {
          if (window.ATAKCams.fetchReconImages) ATAKCams.fetchReconImages();
          else ATAKCams.fetchIntelPhotos();
        }
        if (window.ATAKAirAssets) ATAKAirAssets.fetchAirAssets();
        if (window.ATAKMapShapes) ATAKMapShapes.fetchShapes();
        if (window.ATAKLaserCodes) ATAKLaserCodes.fetchLaserCodes();
        if (window.ATAKMap && window.ATAKMap.pollMarkers) window.ATAKMap.pollMarkers();
        else if (window.ATAKMarkers && window.ATAKMarkers.renderFromMap) window.ATAKMarkers.renderFromMap();
        refreshLiaisonChipQuiet();
      }
      var lastMeasuredLatencyMs = null;
      var lastPingOk = false;
      function setMetricValue(id, text, tone) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = text;
        el.classList.remove('atak-os-metric-value--ok', 'atak-os-metric-value--warn', 'atak-os-metric-value--err');
        if (tone) el.classList.add('atak-os-metric-value--' + tone);
      }
      function qualityFromLatency(ms, pingOk, theatreAgo) {
        if (!pingOk || ms == null || isNaN(ms)) return { label: 'En attente de liaison', tone: null };
        if (theatreAgo != null && theatreAgo > 180) return { label: 'Dégradée', tone: 'warn' };
        if (ms <= 120) return { label: 'Bonne', tone: 'ok' };
        if (ms <= 350) return { label: 'Acceptable', tone: 'warn' };
        return { label: 'Dégradée', tone: 'err' };
      }
      function refreshLiaisonMetrics(theatreAgo) {
        var q = qualityFromLatency(lastMeasuredLatencyMs, lastPingOk, theatreAgo);
        setMetricValue('atak-metric-quality-value', q.label, q.tone);
        if (lastPingOk && lastMeasuredLatencyMs != null && !isNaN(lastMeasuredLatencyMs)) {
          setMetricValue(
            'atak-metric-latency-value',
            Math.round(lastMeasuredLatencyMs) + ' ms',
            lastMeasuredLatencyMs <= 120 ? 'ok' : (lastMeasuredLatencyMs <= 350 ? 'warn' : 'err')
          );
        } else {
          setMetricValue('atak-metric-latency-value', '—', null);
        }
        setMetricValue('atak-metric-loss-value', '—', null);
        var theatreLabel = (typeof formatAgoFr === 'function') ? formatAgoFr(theatreAgo) : null;
        setMetricValue(
          'atak-metric-theatre-value',
          theatreLabel || 'En attente',
          theatreAgo != null && theatreAgo <= 60 ? 'ok' : (theatreAgo != null ? 'warn' : null)
        );
      }
      function measurePingLatency() {
        var t0 = performance.now();
        var ctrl = new AbortController();
        var to = setTimeout(function () { ctrl.abort(); }, 5000);
        return fetch('<?= url("api/atak/ping") ?>', { credentials: 'include', signal: ctrl.signal, cache: 'no-store' })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok && !!(d && d.ok), data: d }; }); })
          .then(function (res) {
            clearTimeout(to);
            lastPingOk = !!res.ok;
            lastMeasuredLatencyMs = lastPingOk ? (performance.now() - t0) : null;
            return res;
          })
          .catch(function () {
            clearTimeout(to);
            lastPingOk = false;
            lastMeasuredLatencyMs = null;
          });
      }
      var lastLiaisonChipAt = 0;
      function refreshLiaisonChipQuiet() {
        var now = Date.now();
        if (now - lastLiaisonChipAt < 12000) return;
        lastLiaisonChipAt = now;
        var mid = (window.ATAKSocket && window.ATAKSocket.getMapId) ? window.ATAKSocket.getMapId() : 1;
        Promise.all([
          measurePingLatency(),
          fetch('<?= url("api/atak/stats") ?>?mapId=' + mid, { credentials: 'include' }).then(function (r) { return r.json(); })
        ]).then(function (results) {
          var d = results[1] || {};
          var ago = d.lastArmaActivityAgo != null ? Number(d.lastArmaActivityAgo) : null;
          var agoLabel = formatAgoFr(ago);
          var liaisonChipVal = document.getElementById('atak-chip-liaison-value');
          var chip = document.getElementById('atak-chip-liaison');
          if (liaisonChipVal) liaisonChipVal.textContent = agoLabel || 'En attente';
          if (chip) {
            chip.classList.toggle('atak-chip--ok', ago != null && ago <= 60);
            chip.classList.toggle('atak-chip--warn', ago != null && ago > 60);
          }
          refreshLiaisonMetrics(ago);
        }).catch(function () {
          refreshLiaisonMetrics(null);
        });
      }
      atakPoll();
      setInterval(atakPoll, 3000);
      if (window.ATAKActivity && typeof window.ATAKActivity.start === 'function') {
        ATAKActivity.start();
      }
      if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.startPolling === 'function') {
        ATAKMedicalAlerts.startPolling(5000);
      }
      if (window.ATAKOrders && typeof window.ATAKOrders.startPolling === 'function') {
        ATAKOrders.startPolling(4000);
      }

      document.querySelectorAll('.atak-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var tab = this.getAttribute('data-tab');
          document.querySelectorAll('.atak-tab').forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
          });
          document.querySelectorAll('.atak-tabs-content').forEach(function (c) { c.classList.remove('active'); });
          this.classList.add('active');
          this.setAttribute('aria-selected', 'true');
          var content = document.getElementById('tab-' + tab);
          if (content) content.classList.add('active');
          if (window.ATAKActivity && typeof window.ATAKActivity.setLiaisonTabActive === 'function') {
            window.ATAKActivity.setLiaisonTabActive(tab === 'liaison');
          }
        });
      });
      (function syncLiaisonFullscreenHref() {
        var link = document.getElementById('atak-activity-fullscreen');
        if (!link) return;
        function update() {
          var mid = (window.ATAKSocket && window.ATAKSocket.getMapId) ? window.ATAKSocket.getMapId() : (window.ATAK_DEFAULT_MAP_ID || 1);
          link.href = '<?= $base ?>/atak/liaison?mapId=' + encodeURIComponent(mid);
        }
        update();
        var ws = document.getElementById('atak-workspace-select');
        if (ws) ws.addEventListener('change', update);
      })();

      function refreshWebPresence() {
        var listEl = document.getElementById('atak-web-presence-list');
        var emptyEl = document.getElementById('atak-web-presence-empty');
        if (!listEl) return;
        var mid = (window.ATAKSocket && window.ATAKSocket.getMapId) ? window.ATAKSocket.getMapId() : 1;
        fetch('<?= url("api/atak/presence") ?>?mapId=' + encodeURIComponent(mid), { credentials: 'include', cache: 'no-store' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
            var viewers = (d && d.viewers) ? d.viewers : [];
            listEl.innerHTML = '';
            if (!viewers.length) {
              if (emptyEl) emptyEl.hidden = false;
              return;
            }
            if (emptyEl) emptyEl.hidden = true;
            viewers.forEach(function (v) {
              var li = document.createElement('li');
              li.className = 'atak-web-presence-item';
              var name = (v && (v.callsign || v.label || v.display_name)) || 'Opérateur';
              li.textContent = name;
              listEl.appendChild(li);
            });
          })
          .catch(function () {});
      }
      refreshWebPresence();
      setInterval(refreshWebPresence, 20000);
      measurePingLatency().then(function () { refreshLiaisonMetrics(null); });
      setInterval(function () {
        lastLiaisonChipAt = 0;
        refreshLiaisonChipQuiet();
      }, 15000);

      var copyBtn = document.getElementById('atak-copy-node-url');
      var urlPre = document.getElementById('atak-node-url-copy');
      if (copyBtn && urlPre) {
        copyBtn.addEventListener('click', function () {
          var url = urlPre.textContent;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () { copyBtn.textContent = 'Copié !'; setTimeout(function () { copyBtn.textContent = 'Copier'; }, 1500); });
          } else {
            var ta = document.createElement('textarea'); ta.value = url; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
            copyBtn.textContent = 'Copié !'; setTimeout(function () { copyBtn.textContent = 'Copier'; }, 1500);
          }
        });
      }

      var copySteamBtn = document.getElementById('atak-copy-steam-id');
      var steamPre = document.getElementById('atak-steam-id-copy');
      if (copySteamBtn && steamPre) {
        copySteamBtn.addEventListener('click', function () {
          var sid = (steamPre.textContent || '').trim();
          if (!sid) return;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(sid).then(function () {
              copySteamBtn.textContent = 'Copié !';
              setTimeout(function () { copySteamBtn.textContent = 'Copier'; }, 1500);
            });
          } else {
            var ta = document.createElement('textarea');
            ta.value = sid;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            copySteamBtn.textContent = 'Copié !';
            setTimeout(function () { copySteamBtn.textContent = 'Copier'; }, 1500);
          }
        });
      }

      // Panneaux latéraux (Compte, Configuration, État) : un seul overlay partagé, un seul
      // panneau ouvert à la fois.
      var accountPanel = document.getElementById('atak-account-panel');
      var configPanel = document.getElementById('atak-config-panel');
      var healthPanel = document.getElementById('atak-health-panel');
      var accountOverlay = document.getElementById('atak-account-overlay');
      var slidePanels = [accountPanel, configPanel, healthPanel];
      var gameLinkSection = document.getElementById('atak-game-link-section');
      var phoneLinkSection = document.getElementById('atak-phone-link-section');

      function closeAllSlidePanels() {
        slidePanels.forEach(function (p) { if (p) p.classList.remove('open'); });
        if (accountOverlay) accountOverlay.classList.remove('show');
        stopHealthAutoRefresh();
      }
      function pulseAccountSection(section, focusBtnId) {
        if (!section) return;
        section.classList.add('atak-account-section--pulse');
        setTimeout(function () {
          section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 50);
        setTimeout(function () {
          section.classList.remove('atak-account-section--pulse');
        }, 1800);
        if (focusBtnId) {
          var genBtn = document.getElementById(focusBtnId);
          if (genBtn) setTimeout(function () { genBtn.focus(); }, 200);
        }
      }
      function openSlidePanel(panel, opts) {
        closeAllSlidePanels();
        if (panel) panel.classList.add('open');
        if (accountOverlay) accountOverlay.classList.add('show');
        if (opts && opts.focusGameLink) {
          pulseAccountSection(gameLinkSection, 'atak-game-link-btn');
        }
        if (opts && opts.focusPhoneLink) {
          pulseAccountSection(phoneLinkSection, 'atak-phone-link-btn');
        }
        if (panel === healthPanel) {
          refreshHealth();
          startHealthAutoRefresh();
        }
      }

      var accountBtn = document.getElementById('atak-btn-account');
      var gameLinkHeaderBtn = document.getElementById('atak-btn-game-link');
      var phoneLinkHeaderBtn = document.getElementById('atak-btn-phone-link');
      var configBtn = document.getElementById('atak-btn-config');
      var healthBtn = document.getElementById('atak-btn-health');
      if (accountBtn) accountBtn.addEventListener('click', function () { openSlidePanel(accountPanel); });
      if (gameLinkHeaderBtn) {
        gameLinkHeaderBtn.addEventListener('click', function () {
          openSlidePanel(accountPanel, { focusGameLink: true });
        });
      }
      if (phoneLinkHeaderBtn) {
        phoneLinkHeaderBtn.addEventListener('click', function () {
          openSlidePanel(accountPanel, { focusPhoneLink: true });
        });
      }
      if (configBtn) configBtn.addEventListener('click', function () { openSlidePanel(configPanel); });
      if (healthBtn) healthBtn.addEventListener('click', function () { openSlidePanel(healthPanel); });

      document.querySelectorAll('.atak-account-panel-close').forEach(function (btn) {
        btn.addEventListener('click', closeAllSlidePanels);
      });
      if (accountOverlay) accountOverlay.addEventListener('click', closeAllSlidePanels);

      window.ATAKLastChatError = function (msg) {
        var el = document.getElementById('health-chat-error');
        if (!el) return;
        if (!msg) {
          el.textContent = 'Aucun incident';
          el.className = 'atak-health-cell atak-health-ok-text';
          return;
        }
        el.textContent = msg;
        el.className = 'atak-health-cell atak-health-err';
      };
      window.ATAKLastPingsError = function (msg) {
        var el = document.getElementById('health-pings-error');
        if (!el) return;
        if (!msg) {
          el.textContent = 'Aucun incident';
          el.className = 'atak-health-cell atak-health-ok-text';
          return;
        }
        el.textContent = msg;
        el.className = 'atak-health-cell atak-health-err';
      };
      function setPill(el, tone, text) {
        if (!el) return;
        el.textContent = text;
        el.className = 'atak-pill' + (tone ? ' atak-pill--' + tone : '');
      }
      function formatAgoFr(sec) {
        if (sec == null || isNaN(sec)) return null;
        var s = Math.max(0, Math.floor(Number(sec)));
        if (s < 5) return 'à l’instant';
        if (s < 60) return 'il y a ' + s + ' s';
        var m = Math.floor(s / 60);
        if (m < 60) return 'il y a ' + m + ' min';
        var h = Math.floor(m / 60);
        return 'il y a ' + h + ' h';
      }
      function refreshHealth() {
        var nodeUrlEl = document.getElementById('health-node-url');
        var nodeStatusEl = document.getElementById('health-node-status');
        var socketStateEl = document.getElementById('health-socket-state');
        var socketUrlEl = document.getElementById('health-socket-url');
        var dbEl = document.getElementById('health-db');
        var armaEl = document.getElementById('health-arma');
        var unitsCountEl = document.getElementById('health-units-count');
        var activeCallsignsEl = document.getElementById('health-active-callsigns');
        var summaryPill = document.getElementById('atak-health-summary-pill');
        var liaisonChipVal = document.getElementById('atak-chip-liaison-value');
        var hasDedicated = (typeof window.ATAK_NODE_URL === 'string' && window.ATAK_NODE_URL);
        if (nodeUrlEl) nodeUrlEl.textContent = hasDedicated ? 'Adresse dédiée configurée' : 'Hébergement principal';
        setPill(nodeStatusEl, 'muted', 'Vérification…');
        var healthFlags = { node: null, db: null, arma: null };
        function updateSummary() {
          if (healthFlags.node === false || healthFlags.db === false) {
            setPill(summaryPill, 'err', 'Incident');
          } else if (healthFlags.arma === 'stale') {
            setPill(summaryPill, 'warn', 'Mod silencieux');
          } else if (healthFlags.node && healthFlags.db) {
            setPill(summaryPill, 'ok', 'Nominal');
          }
        }
        var pingController = new AbortController();
        var pingTimeout = setTimeout(function () { pingController.abort(); }, 5000);
        fetch('<?= url("api/atak/ping") ?>', { credentials: 'include', signal: pingController.signal }).then(function (r) { return r.json(); }).then(function (d) {
          clearTimeout(pingTimeout);
          healthFlags.node = !!d.ok;
          setPill(nodeStatusEl, d.ok ? 'ok' : 'err', d.ok ? 'Opérationnel' : 'Indisponible');
          updateSummary();
        }).catch(function (e) {
          clearTimeout(pingTimeout);
          healthFlags.node = false;
          setPill(nodeStatusEl, 'err', e.name === 'AbortError' ? 'Délai dépassé' : 'Indisponible');
          updateSummary();
        });
        if (socketStateEl) {
          socketStateEl.textContent = 'Actives';
          socketStateEl.className = 'atak-health-cell atak-health-ok-text';
        }
        if (socketUrlEl) socketUrlEl.textContent = 'Rafraîchissement automatique';
        var mapId = (window.ATAKSocket && window.ATAKSocket.getMapId) ? window.ATAKSocket.getMapId() : 1;
        fetch('<?= url("api/atak/stats") ?>?mapId=' + mapId, { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
          var ago = d.lastArmaActivityAgo != null ? Number(d.lastArmaActivityAgo) : null;
          var agoLabel = formatAgoFr(ago);
          if (armaEl) {
            if (agoLabel) {
              armaEl.textContent = 'Dernière activité ' + agoLabel;
              armaEl.className = 'atak-health-cell' + (ago != null && ago > 120 ? ' atak-health-warn-text' : '');
            } else {
              armaEl.textContent = 'Aucune activité reçue';
              armaEl.className = 'atak-health-cell atak-health-muted';
            }
          }
          if (liaisonChipVal) {
            liaisonChipVal.textContent = agoLabel || 'En attente';
            var chip = document.getElementById('atak-chip-liaison');
            if (chip) {
              chip.classList.toggle('atak-chip--ok', ago != null && ago <= 60);
              chip.classList.toggle('atak-chip--warn', ago != null && ago > 60);
            }
          }
          healthFlags.arma = (ago == null) ? 'stale' : (ago > 180 ? 'stale' : 'ok');
          if (unitsCountEl) {
            var n = d.unitsCount != null ? d.unitsCount : 0;
            unitsCountEl.textContent = n === 0 ? 'Aucun contact' : (n + ' contact' + (n > 1 ? 's' : ''));
          }
          var calls = (d.activeCallSigns && d.activeCallSigns.length)
            ? d.activeCallSigns.map(function (u) { return u.call_sign || ''; }).filter(Boolean).slice(0, 10).join(' · ')
            : '—';
          if (activeCallsignsEl) activeCallsignsEl.textContent = calls;
          updateSummary();
        }).catch(function () {
          if (armaEl) armaEl.textContent = '—';
          if (unitsCountEl) unitsCountEl.textContent = '—';
          if (activeCallsignsEl) activeCallsignsEl.textContent = '—';
        });
        fetch('<?= url("api/health") ?>', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
          healthFlags.db = d.db === 'ok';
          setPill(dbEl, d.db === 'ok' ? 'ok' : 'err', d.db === 'ok' ? 'Disponibles' : (d.message || 'Indisponibles'));
          updateSummary();
        }).catch(function () {
          healthFlags.db = false;
          setPill(dbEl, 'err', 'Indisponibles');
          updateSummary();
        });
      }
      var healthRefresh = document.getElementById('atak-health-refresh');
      var healthRefreshInterval = null;
      function startHealthAutoRefresh() {
        if (healthRefreshInterval) return;
        healthRefreshInterval = setInterval(refreshHealth, 15000);
      }
      function stopHealthAutoRefresh() {
        if (healthRefreshInterval) clearInterval(healthRefreshInterval);
        healthRefreshInterval = null;
      }
      if (healthRefresh) healthRefresh.addEventListener('click', refreshHealth);
      }

      (function initGameLink() {
        var btn = document.getElementById('atak-game-link-btn');
        if (!btn) return;
        var resultEl = document.getElementById('atak-game-link-result');
        var codeEl = document.getElementById('atak-game-link-code');
        var metaEl = document.getElementById('atak-game-link-meta');
        var errEl = document.getElementById('atak-game-link-error');
        var copyBtn = document.getElementById('atak-game-link-copy');
        var createUrl = <?= json_encode($gameLinkCreateUrl ?? url('atak/game-link'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var linkBusy = false;
        function unlockLinkBtn(label, cooldownMs) {
          var wait = Math.max(0, cooldownMs || 0);
          setTimeout(function () {
            linkBusy = false;
            btn.disabled = false;
            btn.textContent = label || 'Générer un code';
          }, wait);
        }
        btn.addEventListener('click', function () {
          if (linkBusy) return;
          if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
          linkBusy = true;
          btn.disabled = true;
          btn.textContent = 'Génération…';
          fetch(createUrl, { method: 'POST', credentials: 'include', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
              return r.text().then(function (raw) {
                var j = null;
                try { j = raw ? JSON.parse(raw) : null; } catch (e) { j = null; }
                return { ok: r.ok, status: r.status, body: j };
              });
            })
            .then(function (res) {
              if (!res.ok || !res.body || !res.body.code) {
                var msg = (res.body && res.body.message)
                  ? res.body.message
                  : (res.status === 404
                    ? 'Service de liaison introuvable. Rechargez la page après mise à jour du portail.'
                    : 'Impossible de générer le code.');
                if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
                // Pas de retry auto ; cooldown après 503 pour éviter de spammer le serveur.
                unlockLinkBtn('Générer un code', res.status === 503 ? 4000 : 800);
                return;
              }
              unlockLinkBtn('Générer un nouveau code', 0);
              if (codeEl) codeEl.textContent = res.body.code;
              if (metaEl) {
                metaEl.textContent = res.body.hint || 'Dans Arma : touche K → Compte Athena (saisir un code), puis entrez ce code.';
              }
              if (resultEl) resultEl.hidden = false;
            })
            .catch(function () {
              unlockLinkBtn('Générer un code', 1500);
              if (errEl) {
                errEl.textContent = 'Réseau indisponible. Réessayez dans un instant.';
                errEl.hidden = false;
              }
            });
        });
        if (copyBtn && codeEl) {
          copyBtn.addEventListener('click', function () {
            var t = codeEl.textContent || '';
            if (!t || t.indexOf('—') === 0) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(t).then(function () {
                copyBtn.textContent = 'Copié';
                setTimeout(function () { copyBtn.textContent = 'Copier'; }, 1500);
              });
            }
          });
        }
      })();

      (function initPhoneLink() {
        var btn = document.getElementById('atak-phone-link-btn');
        if (!btn) return;
        var resultEl = document.getElementById('atak-phone-link-result');
        var codeEl = document.getElementById('atak-phone-link-code');
        var metaEl = document.getElementById('atak-phone-link-meta');
        var errEl = document.getElementById('atak-phone-link-error');
        var qrImg = document.getElementById('atak-phone-link-qr');
        var copyBtn = document.getElementById('atak-phone-link-copy');
        var openLink = document.getElementById('atak-phone-link-open');
        var pill = document.getElementById('atak-phone-link-pill');
        var hintEl = document.getElementById('atak-phone-link-hint');
        var createUrl = <?= json_encode(url('api/atak/phone-pairing'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var statusUrl = <?= json_encode(url('api/atak/phone-pairing/status'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var busy = false;
        var activeToken = null;
        var pollTimer = null;

        function setPill(tone, text) {
          if (!pill) return;
          pill.textContent = text;
          pill.className = 'atak-pill' + (tone ? ' atak-pill--' + tone : '');
        }
        function unlockBtn(label, cooldownMs) {
          var wait = Math.max(0, cooldownMs || 0);
          setTimeout(function () {
            busy = false;
            btn.disabled = false;
            btn.textContent = label || 'Générer un QR';
          }, wait);
        }
        function stopPoll() {
          if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
          }
        }
        function formatExpires(iso) {
          if (!iso) return '';
          try {
            var d = new Date(iso.indexOf('T') >= 0 || iso.indexOf('Z') >= 0 ? iso : (iso.replace(' ', 'T') + 'Z'));
            if (isNaN(d.getTime())) return '';
            return 'Valable jusqu’à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
          } catch (e) {
            return '';
          }
        }
        function applyLinkedState(linked, message) {
          if (linked) {
            setPill('ok', 'Téléphone lié');
            if (hintEl && message) hintEl.textContent = message;
            else if (hintEl) {
              hintEl.textContent = 'Un téléphone a déjà été lié. Vous pouvez générer un nouveau QR pour un autre appareil.';
            }
          } else {
            setPill('muted', 'Non lié');
          }
        }
        function refreshStatus(token) {
          var url = statusUrl + (token ? ('?token=' + encodeURIComponent(token)) : '');
          return fetch(url, { credentials: 'include', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
              return r.text().then(function (raw) {
                var j = null;
                try { j = raw ? JSON.parse(raw) : null; } catch (e) { j = null; }
                return { ok: r.ok, status: r.status, body: j };
              });
            })
            .then(function (res) {
              if (!res.ok || !res.body) {
                if (res.status === 503 && res.body && res.body.message && errEl && !activeToken) {
                  errEl.textContent = res.body.message;
                  errEl.hidden = false;
                  btn.disabled = true;
                  btn.textContent = 'Indisponible';
                }
                return res;
              }
              applyLinkedState(!!res.body.linked, res.body.message || '');
              if (res.body.linked && !busy && !activeToken) {
                btn.textContent = 'Nouveau QR';
              }
              if (res.body.current && res.body.current.paired) {
                setPill('ok', 'Téléphone lié');
                if (metaEl) {
                  metaEl.textContent = 'Lien réussi — vous pouvez générer un nouveau QR pour un autre appareil.';
                }
                stopPoll();
                unlockBtn('Nouveau QR', 0);
              } else if (res.body.current && res.body.current.expired && activeToken) {
                if (metaEl) metaEl.textContent = 'Ce code a expiré. Générez-en un nouveau.';
                stopPoll();
                unlockBtn('Générer un QR', 0);
              }
              return res;
            })
            .catch(function () { return null; });
        }
        function startPoll(token) {
          stopPoll();
          activeToken = token;
          pollTimer = setInterval(function () { refreshStatus(token); }, 3000);
        }

        refreshStatus(null);

        btn.addEventListener('click', function () {
          if (busy) return;
          if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
          busy = true;
          btn.disabled = true;
          btn.textContent = 'Génération…';
          stopPoll();
          fetch(createUrl, { method: 'GET', credentials: 'include', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
              return r.text().then(function (raw) {
                var j = null;
                try { j = raw ? JSON.parse(raw) : null; } catch (e) { j = null; }
                return { ok: r.ok, status: r.status, body: j };
              });
            })
            .then(function (res) {
              if (!res.ok || !res.body || !res.body.code) {
                var msg = (res.body && res.body.message)
                  ? res.body.message
                  : (res.status === 401 || res.status === 403
                    ? 'Connexion requise pour générer un code téléphone.'
                    : 'Impossible de préparer la connexion téléphone.');
                if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
                unlockBtn('Générer un QR', res.status === 503 ? 4000 : 800);
                return;
              }
              var body = res.body;
              if (codeEl) codeEl.textContent = body.code;
              if (qrImg && body.qr_image_url) {
                qrImg.onload = function () { qrImg.classList.remove('atak-phone-link-qr--err'); };
                qrImg.onerror = function () {
                  qrImg.classList.add('atak-phone-link-qr--err');
                  if (metaEl) {
                    metaEl.textContent = (formatExpires(body.expires_at) || 'Code prêt') +
                      ' — l’image du QR n’a pas pu être chargée ; utilisez le code ci-dessus.';
                  }
                };
                qrImg.src = body.qr_image_url + (body.qr_image_url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
              }
              if (openLink && body.connect_url) {
                openLink.href = body.connect_url;
                openLink.hidden = false;
              } else if (openLink) {
                openLink.hidden = true;
              }
              if (metaEl) {
                metaEl.textContent = (formatExpires(body.expires_at) || 'Valable 15 minutes') +
                  ' — scannez le QR ou saisissez le code sur le téléphone.';
              }
              if (resultEl) resultEl.hidden = false;
              unlockBtn('Nouveau QR', 0);
              if (body.token) startPoll(body.token);
              refreshStatus(null);
            })
            .catch(function () {
              unlockBtn('Générer un QR', 1500);
              if (errEl) {
                errEl.textContent = 'Réseau indisponible. Réessayez dans un instant.';
                errEl.hidden = false;
              }
            });
        });

        if (copyBtn && codeEl) {
          copyBtn.addEventListener('click', function () {
            var t = codeEl.textContent || '';
            if (!t || t.indexOf('—') === 0) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(t).then(function () {
                copyBtn.textContent = 'Copié';
                setTimeout(function () { copyBtn.textContent = 'Copier le code'; }, 1500);
              });
            }
          });
        }
      })();

      fetch('<?= url("api/atak/ping") ?>', { credentials: 'include', signal: bootAbort.signal })
        .then(function (r) {
          clearTimeout(bootTimer);
          if (!r.ok) throw new Error('ping');
          return r.json();
        })
        .then(function (data) {
          if (!data || !data.ok) throw new Error('ping');
          function bootApp() {
            startAtakApplication();
          }
          if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.onReady === 'function') {
            window.ATAKSessionProfile.onReady(bootApp);
            window.ATAKSessionProfile.init();
          } else {
            bootApp();
          }
        })
        .catch(function (err) {
          clearTimeout(bootTimer);
          dismissAtakBoot();
          var msg = err && err.name === 'AbortError'
            ? 'La Tacmap met trop de temps à répondre. Vérifiez votre connexion ou réessayez.'
            : 'Connexion impossible aux services de la Tacmap pour le moment.';
          window.ATAKShowError(msg);
        });
    })();
  </script>
</body>
</html>
