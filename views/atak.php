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
    'canEditUnitNotes' => false,
    'canDeleteUnitStaff' => false,
    'canDeleteOwnUnit' => false,
    'canPing' => true,
];
$currentUser = $currentUser ?? null;
$atakUserForJs = $atakUserForJs ?? null;
$atakUiPrefs = $atakUiPrefs ?? ['theme' => 'system', 'density' => 'compact'];
$canAccessAdminAtakConfig = $canAccessAdminAtakConfig ?? false;
$atakModDownloadUrl = $atakModDownloadUrl ?? null;
$phoneOperatorSession = $phoneOperatorSession ?? null;
$hasGameConfig = $atakConfig && ($atakConfig['arma_server_host'] ?? $atakConfig['arma_mod_credentials'] ?? $atakConfig['instructions'] ?? null);
$atakPopoutRaw = isset($_GET['popout']) ? strtolower(trim((string) $_GET['popout'])) : '';
$atakPopout = ($atakPopoutRaw === 'left' || $atakPopoutRaw === 'right') ? $atakPopoutRaw : '';
$atakPopoutTab = isset($_GET['tab']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $_GET['tab']) : '';
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
  <title><?= $atakPopout === 'left' ? 'Panneau ATAK' : ($atakPopout === 'right' ? 'Effectifs ATAK' : 'COMSPEC ATAK | Carte tactique Arma 3') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/vendor/leaflet-1.9.4/leaflet.css" />
  <link href="<?= $base ?>/assets/css/atak.css?v=202607270730" rel="stylesheet" />
  <link href="<?= $base ?>/assets/css/atak-map-popups.css" rel="stylesheet" />
  <link href="<?= $base ?>/assets/css/atak-roleplay-effects.css" rel="stylesheet" />
  <link href="<?= $base ?>/assets/css/atak-roleplay-ctab.css" rel="stylesheet" />
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/halo-loader.css" rel="stylesheet" />
  <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/mission-cycle-badge.css?v=202607270700" rel="stylesheet" />
  <script>
    window.ATAK_TOKEN = <?= json_encode($atakToken) ?>;
    window.ATAK_API_BASE = <?= json_encode($base) ?>;
    window.ATAK_TENANT_ID = <?= (int) ($atakTenantId ?? ($atakUserForJs['tenantId'] ?? 0)) ?>;
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
    window.ATAK_POPOUT = <?= json_encode($atakPopout !== '' ? $atakPopout : null) ?>;
    window.ATAK_POPOUT_TAB = <?= json_encode($atakPopoutTab !== '' ? $atakPopoutTab : null) ?>;
    window.ATAK_CAPS = <?= json_encode($atakCaps ?? [
        'loggedIn' => false,
        'canViewPersonnel' => false,
        'canLinkPersonnel' => false,
        'canRenameUnit' => false,
        'canEditUnitNotes' => false,
        'canDeleteUnitStaff' => false,
        'canDeleteOwnUnit' => false,
        'canPing' => true,
        'canTriageMedical' => false,
    ]) ?>;
    window.ATAK_CAN_ISSUE_ORDERS = <?= !empty($currentUser) ? 'true' : 'false' ?>;
    window.ATAK_PROFILE_HINTS = <?= json_encode($atakProfileHints ?? [
        'suggestedRole' => 'operator',
        'suggestedSpecialties' => [],
        'hasSuggestionBasis' => false,
    ]) ?>;
    window.ATAK_PHONE_SESSION = <?= json_encode($phoneOperatorSession ?: null) ?>;
  </script>
</head>
<body class="atak-page atak-theme-<?= htmlspecialchars((string) ($atakUiPrefs['theme'] ?? 'system')) ?> atak-density-<?= htmlspecialchars((string) ($atakUiPrefs['density'] ?? 'compact')) ?><?= !empty($phoneOperatorSession) ? ' atak-phone-session' : '' ?><?= $atakPopout !== '' ? ' atak-popout atak-popout--' . htmlspecialchars($atakPopout, ENT_QUOTES, 'UTF-8') : '' ?>">
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
        <span class="atak-beta-badge" title="Programme d’accès anticipé">BÊTA</span>
        <?php if (!empty($phoneOperatorSession)): ?>
        <span class="atak-phone-op-badge" id="atak-phone-op-badge" title="Session ouverte via code téléphone" role="status">
          <span class="atak-phone-op-badge__label"><?= htmlspecialchars((string) ($phoneOperatorSession['label'] ?? 'Opérateur téléphone'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="atak-phone-op-badge__ttl" id="atak-phone-op-ttl" data-expires-at="<?= htmlspecialchars((string) ($phoneOperatorSession['expires_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">—</span>
        </span>
        <?php endif; ?>
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
        <span class="atak-chip atak-chip--weather" id="atak-weather" title="Météo mission" hidden>
          <span class="atak-chip-key">Météo</span>
          <span class="atak-chip-value" id="atak-weather-value">—</span>
        </span>
        <span id="mission-cycle-badge" hidden title="Cycle de mission"></span>
      </div>
    </div>
    <div class="atak-header-meta" aria-label="Horloge et résumé">
      <div class="atak-zulu" id="atak-zulu" title="Heure Zulu">--:--:-- Z</div>
    </div>
    <div class="atak-header-links">
      <?php if (count($atakWorkspaces) > 1 || count($atakMapsList) > 1): ?>
      <div class="atak-header-cluster atak-header-cluster--ctx" role="group" aria-label="Contexte de mission">
        <?php if (count($atakWorkspaces) > 1): ?>
        <label class="atak-header-field">
          <span class="atak-header-field-label">Serveur</span>
          <select id="atak-workspace-select" class="atak-header-field-control" title="Choisir le serveur / mission">
            <?php foreach ($atakWorkspaces as $w): ?>
            <option value="<?= (int)($w['mapId'] ?? 1) ?>" <?= ($w['mapId'] ?? 0) == $atakDefaultMapId ? 'selected' : '' ?>><?= htmlspecialchars($w['label'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>
        <?php if (count($atakMapsList) > 1): ?>
        <label class="atak-header-field">
          <span class="atak-header-field-label">Carte</span>
          <select id="atak-map-select" class="atak-header-field-control" title="Choisir la carte">
            <?php foreach ($atakMapsList as $m): ?>
            <option value="<?= htmlspecialchars($m['slug'] ?? '') ?>" <?= ($atakMapConfig && ($atakMapConfig['slug'] ?? '') === ($m['slug'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($m['label'] ?? $m['slug'] ?? 'Carte') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($currentUser): ?>
      <div class="atak-header-cluster atak-header-cluster--liaison" role="group" aria-label="Liaison">
        <button type="button" class="atak-session-profile-chip" id="atak-session-profile-change" title="Modifier le profil de session" hidden>
          <span class="atak-session-profile-chip-key">Profil</span>
          <span class="atak-session-profile-chip-value" id="atak-session-profile-badge"></span>
        </button>
        <button type="button" class="atak-header-action atak-header-action--primary" id="atak-btn-game-link" title="Générer un code pour lier Arma à votre compte">Lier le jeu</button>
        <button type="button" class="atak-header-action atak-header-secondary" id="atak-btn-phone-link" title="Générer un code pour lier un téléphone à Athena">Téléphone</button>
      </div>
      <?php endif; ?>
      <div class="atak-header-cluster atak-header-cluster--status" role="group" aria-label="État système">
        <a class="atak-header-action" href="<?= htmlspecialchars(url('back-office/atak/cycle-mission'), ENT_QUOTES, 'UTF-8') ?>" title="Créer, ouvrir ou clôturer une mission">Cycle mission</a>
        <button type="button" class="atak-header-status" id="atak-btn-config" title="Configuration pour le jeu">
          <span class="atak-header-status-label">Config</span>
          <span class="atak-pill <?= !empty($nodeAtakUrl) ? 'atak-pill--ok' : 'atak-pill--warn' ?>" id="atak-config-summary-pill"><?= !empty($nodeAtakUrl) ? 'Prête' : 'À régler' ?></span>
        </button>
        <button type="button" class="atak-header-status" id="atak-btn-health" title="État de la liaison">
          <span class="atak-header-status-label">Liaison</span>
          <span class="atak-pill atak-pill--muted" id="atak-health-summary-pill">—</span>
        </button>
        <button type="button" class="atak-header-action" id="atak-btn-account" title="Compte, guides et navigation">Compte</button>
      </div>
    </div>
  </header>

  <div class="atak-liaison-rail is-collapsed" id="atak-liaison-rail">
    <div class="atak-liaison-rail__summary">
      <button type="button" class="atak-liaison-rail__toggle" id="atak-liaison-rail-toggle" aria-expanded="false" aria-controls="atak-liaison-rail-body" title="Afficher ou masquer le détail de liaison">
        <span class="atak-liaison-rail__chev" aria-hidden="true"></span>
        <span class="atak-liaison-rail__label">Liaison</span>
      </button>
      <ul class="atak-liaison-rail__peek" aria-label="Aperçu liaison">
        <li><span class="atak-liaison-rail__peek-k">Qualité</span> <span id="atak-liaison-peek-quality">—</span></li>
        <li><span class="atak-liaison-rail__peek-k">Latence</span> <span id="atak-liaison-peek-latency">—</span></li>
      </ul>
    </div>
    <div class="atak-liaison-rail__body" id="atak-liaison-rail-body">
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
          <li class="atak-os-metric" id="atak-metric-loss" title="Pertes de paquets (indisponible tant que le jeu ne remonte pas cette mesure)">
            <span class="atak-os-metric-key">Pertes de paquets</span>
            <span class="atak-os-metric-value" id="atak-metric-loss-value">—</span>
          </li>
          <li class="atak-os-metric" id="atak-metric-theatre" title="Dernière activité reçue depuis le théâtre">
            <span class="atak-os-metric-key">Théâtre</span>
            <span class="atak-os-metric-value" id="atak-metric-theatre-value">En attente</span>
          </li>
        </ul>
        <p class="atak-os-strip-hint">Liaison jeu : <strong>Connexion en jeu</strong> → code → téléphone ATAK (<strong>Desktop</strong> → <strong>Connexion Athena</strong>) ou touche <strong>K</strong>.</p>
      </div>
      <div class="atak-tx-strip" role="status" aria-live="polite" aria-label="Canaux de transmission">
        <p class="atak-tx-strip-lead">Transmission</p>
        <ul class="atak-tx-chips">
          <li class="atak-tx-chip atak-tx-chip--absent" id="atak-tx-site" data-state="absent" title="Transmission depuis le portail web">
            <span class="atak-tx-chip-key">Sur le site</span>
            <span class="atak-tx-chip-value" id="atak-tx-site-value">Absent</span>
          </li>
          <li class="atak-tx-chip atak-tx-chip--absent" id="atak-tx-athena" data-state="absent" title="Mod Athena (Overwatch) en jeu">
            <span class="atak-tx-chip-key">Mod Athena</span>
            <span class="atak-tx-chip-value" id="atak-tx-athena-value">Absent</span>
          </li>
          <li class="atak-tx-chip atak-tx-chip--absent" id="atak-tx-ctab" data-state="absent" title="Tablette cTab détectée en jeu">
            <span class="atak-tx-chip-key">cTab</span>
            <span class="atak-tx-chip-value" id="atak-tx-ctab-value">Absent</span>
          </li>
          <li class="atak-tx-chip atak-tx-chip--absent" id="atak-tx-atak_enhanced" data-state="absent" title="ATAK Enhanced détecté en jeu">
            <span class="atak-tx-chip-key">ATAK Enhanced</span>
            <span class="atak-tx-chip-value" id="atak-tx-atak_enhanced-value">Absent</span>
          </li>
        </ul>
      </div>
    </div>
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
        <p class="atak-game-link-hint">Générez un code, puis saisissez-le dans Arma : téléphone ATAK Enhanced → écran <strong>Desktop</strong> → <strong>Connexion Athena</strong> (ou touche <strong>K</strong> → <strong>Connexion Athena</strong>). Le code expire après 30 minutes et ne peut être utilisé qu’une fois.</p>
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
        <p class="atak-game-link-hint" id="atak-phone-link-hint">Générez un code, puis ouvrez sur le téléphone la page de connexion Athena (ou scannez le QR). Même si un téléphone est déjà lié, vous pouvez en générer un nouveau pour un autre appareil.</p>
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
        <h3 class="atak-account-section-title">Navigation</h3>
        <nav class="atak-account-nav" aria-label="Liens portail">
          <a href="<?= url('dashboard') ?>">Tableau de bord</a>
          <a href="<?= url('overwatch') ?>">Overwatch</a>
          <a href="<?= url('soutenir-atak') ?>">Soutenir ATAK</a>
          <a href="<?= url('atak/tuto') ?>">Guide du mod</a>
          <?php if ($canAccessAdminAtakConfig): ?>
          <a href="<?= url('admin/atak-config') ?>">Configuration admin</a>
          <?php endif; ?>
        </nav>
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
      <section class="atak-account-section" id="atak-display-prefs">
        <h3 class="atak-account-section-title">Affichage</h3>
        <p class="atak-game-link-hint">Apparence des positions sur la carte et simulations de liaison. Mémorisé sur cet appareil.</p>
        <label class="atak-sound-pref-label" for="atak-unit-style-mode">
          <span class="atak-sound-pref-key">Apparence des positions</span>
          <select id="atak-unit-style-mode" class="atak-header-select atak-sound-pref-select" title="Style des marqueurs d’unités sur la carte">
            <option value="nato" selected>Symbole OTAN ou photo</option>
            <option value="dot">Point simple</option>
            <option value="team_dot">Point couleur d’équipe</option>
          </select>
        </label>
        <label class="atak-sound-pref-label" for="atak-unit-marker-priority">
          <span class="atak-sound-pref-key">Priorité symbole / photo</span>
          <select id="atak-unit-marker-priority" class="atak-header-select atak-sound-pref-select" title="Quand l’apparence est en symbole OTAN ou photo">
            <option value="nato" selected>Symbole OTAN</option>
            <option value="avatar">Photo de profil</option>
          </select>
        </label>
        <label class="atak-sound-pref-label" for="atak-unit-icon-size">
          <span class="atak-sound-pref-key">Taille des icônes <span class="atak-sound-pref-val" id="atak-unit-icon-size-val">16</span></span>
          <input type="range" id="atak-unit-icon-size" class="atak-sound-pref-slider" min="8" max="48" step="1" value="16" title="Taille des icônes sur la carte" aria-valuemin="8" aria-valuemax="48" aria-valuenow="16" />
        </label>
        <label class="atak-sound-pref-label" for="atak-unit-label-size">
          <span class="atak-sound-pref-key">Taille des libellés <span class="atak-sound-pref-val" id="atak-unit-label-size-val">7</span></span>
          <input type="range" id="atak-unit-label-size" class="atak-sound-pref-slider" min="6" max="16" step="1" value="7" title="Taille des indicatifs sous les marqueurs" aria-valuemin="6" aria-valuemax="16" aria-valuenow="7" />
        </label>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-unit-ft-frame">
          <span class="atak-sound-pref-key">Cadre d’équipe</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-unit-ft-frame" checked />
            <span>Afficher le cadre coloré autour des unités d’une équipe de feu</span>
          </span>
        </label>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-hide-panel-hints">
          <span class="atak-sound-pref-key">Textes d’aide</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-hide-panel-hints" />
            <span>Masquer les aides des panneaux</span>
          </span>
        </label>
        <h4 class="atak-account-section-subtitle">Confort de lecture</h4>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-auto-center-self">
          <span class="atak-sound-pref-key">Recentrage personnel</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-auto-center-self" />
            <span>Au début de la session, recadrer la carte sur votre position dès qu’elle remonte</span>
          </span>
        </label>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-show-delayed-units">
          <span class="atak-sound-pref-key">Contacts en retard</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-show-delayed-units" checked />
            <span>Afficher aussi les contacts dont la position arrive avec retard</span>
          </span>
        </label>
        <h4 class="atak-account-section-subtitle">Simulation de liaison</h4>
        <p class="atak-game-link-hint">Pour l’entraînement : ralentir ou faire « sauter » certaines mises à jour de position sur la carte uniquement (la liste Effectifs reste à jour).</p>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-pos-delay-enabled">
          <span class="atak-sound-pref-key">Retard de position</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-pos-delay-enabled" />
            <span>Retarder l’affichage des positions sur la carte</span>
          </span>
        </label>
        <label class="atak-sound-pref-label" for="atak-pos-delay-sec">
          <span class="atak-sound-pref-key">Durée du retard <span class="atak-sound-pref-val" id="atak-pos-delay-sec-val">2 s</span></span>
          <input type="range" id="atak-pos-delay-sec" class="atak-sound-pref-slider" min="0.5" max="10" step="0.5" value="2" title="Retard appliqué aux positions" aria-valuemin="0.5" aria-valuemax="10" aria-valuenow="2" disabled />
        </label>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-pos-loss-enabled">
          <span class="atak-sound-pref-key">Pertes de liaison</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-pos-loss-enabled" />
            <span>Simuler des pertes de liaison (certaines positions ne s’actualisent pas)</span>
          </span>
        </label>
        <label class="atak-sound-pref-label" for="atak-pos-loss-pct">
          <span class="atak-sound-pref-key">Intensité des pertes <span class="atak-sound-pref-val" id="atak-pos-loss-pct-val">25 %</span></span>
          <input type="range" id="atak-pos-loss-pct" class="atak-sound-pref-slider" min="5" max="80" step="5" value="25" title="Part des mises à jour de position ignorées" aria-valuemin="5" aria-valuemax="80" aria-valuenow="25" disabled />
        </label>
      </section>
      <section class="atak-account-section" id="atak-sound-prefs">
        <h3 class="atak-account-section-title">Son des alertes</h3>
        <p class="atak-game-link-hint">Sons d’événements (démarrage, déconnexion, inconscient, mort) + alertes courantes. Le volume et les modes silencieux sont aussi réglables dans la barre latérale.</p>
        <label class="atak-sound-pref-label" for="atak-alert-volume-account">
          <span class="atak-sound-pref-key">Volume des alertes</span>
          <input type="range" id="atak-alert-volume-account" class="atak-sound-pref-slider" min="0" max="100" step="1" value="70" title="Volume des alertes" aria-valuemin="0" aria-valuemax="100" aria-valuenow="70" />
        </label>
        <label class="atak-sound-pref-label" for="atak-notif-sound">
          <span class="atak-sound-pref-key">Style des sons d'alerte</span>
          <select id="atak-notif-sound" class="atak-header-select atak-sound-pref-select" title="Style des sons d'alerte sur la carte">
            <option value="silent_vib">Silencieux — vibration seule</option>
            <option value="stalker">Ambiance tension</option>
            <option value="health">Signal médical</option>
            <option value="mute">Silencieux — sans vibration</option>
          </select>
        </label>
        <h4 class="atak-account-section-subtitle">Types d’alertes</h4>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-alert-cat-liaison">
          <span class="atak-sound-pref-key">État de liaison</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-alert-cat-liaison" checked />
            <span>Signaler les arrivées, départs et changements liés à la liaison</span>
          </span>
        </label>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-alert-cat-orders">
          <span class="atak-sound-pref-key">Ordres et urgences</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-alert-cat-orders" checked />
            <span>Garder les alertes pour les ordres, demandes d’appui et messages urgents</span>
          </span>
        </label>
        <label class="atak-sound-pref-label atak-sound-pref-label--check" for="atak-alert-cat-medical">
          <span class="atak-sound-pref-key">Alerte médicale</span>
          <span class="atak-sound-pref-check">
            <input type="checkbox" id="atak-alert-cat-medical" checked />
            <span>Signaler les blessures graves, pertes et évacuations</span>
          </span>
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
        <a href="<?= url('soutenir-atak') ?>" class="atak-game-config-link">Soutenir le financement ATAK</a>
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
          <div class="atak-health-card">
            <span class="atak-health-label">Sur le site</span>
            <span class="atak-health-cell atak-health-muted" id="health-tx-site">Absent</span>
          </div>
          <div class="atak-health-card">
            <span class="atak-health-label">Mod Athena</span>
            <span class="atak-health-cell atak-health-muted" id="health-tx-athena">Absent</span>
          </div>
          <div class="atak-health-card">
            <span class="atak-health-label">cTab</span>
            <span class="atak-health-cell atak-health-muted" id="health-tx-ctab">Absent</span>
          </div>
          <div class="atak-health-card">
            <span class="atak-health-label">ATAK Enhanced</span>
            <span class="atak-health-cell atak-health-muted" id="health-tx-atak_enhanced">Absent</span>
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
  <?php
    $hubDisplayName = trim((string) ($currentUser['display_name'] ?? ''));
    $hubCallsign = trim((string) ($currentUser['callsign'] ?? $currentUser['arma_callsign'] ?? ''));
    $hubEmail = trim((string) ($currentUser['email'] ?? ''));
    $hubInitial = mb_strtoupper(mb_substr($hubDisplayName !== '' ? $hubDisplayName : ($hubCallsign !== '' ? $hubCallsign : 'A'), 0, 1));
    $hubGameLinkUrl = $gameLinkCreateUrl ?? url('atak/game-link');
  ?>
  <div
    class="atak-session-hub"
    id="atak-session-profile-overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="atak-session-profile-title"
    data-game-link-url="<?= htmlspecialchars($hubGameLinkUrl, ENT_QUOTES, 'UTF-8') ?>"
    hidden
  >
    <div class="atak-session-hub__stage">
      <div class="atak-session-hub__visual" aria-hidden="true">
        <div class="atak-session-hub__visual-glow"></div>
        <div class="atak-session-hub__eagle">
          <img
            class="atak-session-hub__eagle-img"
            src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/img/atak-eagle-logo.png"
            alt=""
            width="512"
            height="512"
            decoding="async"
          />
          <span class="atak-session-hub__eagle-sweep"></span>
        </div>
        <div class="atak-session-hub__brand">
          <span class="atak-session-hub__brand-main">ATHENA</span>
          <span class="atak-session-hub__brand-sub">ATAK</span>
        </div>
        <p class="atak-session-hub__visual-tagline">Carte tactique · liaison <span class="atak-beta-badge" title="Programme d’accès anticipé">BÊTA</span></p>
      </div>

      <div class="atak-session-hub__panel">
        <ol class="atak-session-hub__progress" id="atak-session-hub-progress" aria-label="Étapes de connexion">
          <li class="is-active" data-step-dot="welcome"><span>1</span> Préambule</li>
          <li data-step-dot="profile"><span>2</span> Profil</li>
          <li data-step-dot="link"><span>3</span> Liaison</li>
        </ol>

        <div class="atak-session-hub__identity" id="atak-session-hub-identity">
          <span class="atak-session-hub__avatar" aria-hidden="true"><?= htmlspecialchars($hubInitial, ENT_QUOTES, 'UTF-8') ?></span>
          <div class="atak-session-hub__identity-text">
            <strong id="atak-session-hub-name"><?= htmlspecialchars($hubDisplayName !== '' ? $hubDisplayName : 'Opérateur', ENT_QUOTES, 'UTF-8') ?></strong>
            <span id="atak-session-hub-meta">
              <?php if ($hubCallsign !== ''): ?>
                Indicatif <?= htmlspecialchars($hubCallsign, ENT_QUOTES, 'UTF-8') ?>
              <?php elseif ($hubEmail !== ''): ?>
                <?= htmlspecialchars($hubEmail, ENT_QUOTES, 'UTF-8') ?>
              <?php else: ?>
                Compte Athena
              <?php endif; ?>
            </span>
          </div>
        </div>

        <section class="atak-session-hub__step is-active" data-hub-step="welcome" aria-labelledby="atak-session-profile-title">
          <p class="atak-session-hub__kicker" id="atak-session-hub-kicker">Préambule</p>
          <h2 id="atak-session-profile-title" class="atak-session-hub__title">Connexion ATAK</h2>
          <p class="atak-session-hub__lead" id="atak-session-hub-lead">
            Préparez votre entrée sur la carte tactique : rôle et spécialités pour cette session, puis liaison optionnelle avec Arma pour synchroniser le théâtre.
          </p>
          <div class="atak-session-hub__actions">
            <button type="button" class="atak-session-hub__btn atak-session-hub__btn--primary" id="atak-hub-welcome-next">Continuer</button>
          </div>
        </section>

        <section class="atak-session-hub__step" data-hub-step="profile" hidden>
          <p class="atak-session-hub__kicker">Profil</p>
          <h2 class="atak-session-hub__title" id="atak-hub-profile-title">Votre rôle de session</h2>
          <p class="atak-session-hub__lead">Les spécialités ouvrent les outils adaptés (assistances, radio, appui aérien). Tout reste modifiable ensuite.</p>
          <p class="atak-session-hub__suggest" id="atak-session-profile-suggest" role="status"></p>

          <form id="atak-session-profile-form" class="atak-session-hub__form">
            <fieldset class="atak-session-hub__fieldset">
              <legend class="atak-session-hub__legend">Votre rôle</legend>
              <div class="atak-session-hub__roles" role="radiogroup" aria-label="Rôle de session">
                <label class="atak-session-hub__card" data-role-card="commander">
                  <input type="radio" class="atak-session-hub__sr" name="atak-session-role" value="commander" />
                  <span class="atak-session-hub__card-ico" aria-hidden="true">▣</span>
                  <span class="atak-session-hub__card-body">
                    <strong>Commandant d’unité</strong>
                    <small>Pilote les ordres et la manœuvre d’ensemble.</small>
                  </span>
                </label>
                <label class="atak-session-hub__card" data-role-card="deputy">
                  <input type="radio" class="atak-session-hub__sr" name="atak-session-role" value="deputy" />
                  <span class="atak-session-hub__card-ico" aria-hidden="true">◈</span>
                  <span class="atak-session-hub__card-body">
                    <strong>Commandant adjoint</strong>
                    <small>Appuie le commandant ; mêmes outils de conduite.</small>
                  </span>
                </label>
                <label class="atak-session-hub__card" data-role-card="operator">
                  <input type="radio" class="atak-session-hub__sr" name="atak-session-role" value="operator" checked />
                  <span class="atak-session-hub__card-ico" aria-hidden="true">◎</span>
                  <span class="atak-session-hub__card-body">
                    <strong>Exécutant</strong>
                    <small>Suit les ordres reçus et remonte la situation.</small>
                  </span>
                </label>
              </div>
            </fieldset>

            <fieldset class="atak-session-hub__fieldset">
              <legend class="atak-session-hub__legend">Spécialités <span class="atak-session-hub__legend-opt">(facultatif)</span></legend>
              <div class="atak-session-hub__specs">
                <label class="atak-session-hub__spec" for="atak-spec-medic" data-spec-card="medic">
                  <input type="checkbox" class="atak-session-hub__sr" id="atak-spec-medic" value="medic" />
                  <span class="atak-session-hub__spec-ico" aria-hidden="true">✚</span>
                  <span class="atak-session-hub__spec-body">
                    <strong>Médecin</strong>
                    <small>Triage et secours</small>
                  </span>
                </label>
                <label class="atak-session-hub__spec" for="atak-spec-radio" data-spec-card="radio">
                  <input type="checkbox" class="atak-session-hub__sr" id="atak-spec-radio" value="radio" />
                  <span class="atak-session-hub__spec-ico" aria-hidden="true">◎</span>
                  <span class="atak-session-hub__spec-body">
                    <strong>Transmetteur</strong>
                    <small>Radio proximité</small>
                  </span>
                </label>
                <label class="atak-session-hub__spec" for="atak-spec-jtac" data-spec-card="jtac">
                  <input type="checkbox" class="atak-session-hub__sr" id="atak-spec-jtac" value="jtac" />
                  <span class="atak-session-hub__spec-ico" aria-hidden="true">✈</span>
                  <span class="atak-session-hub__spec-body">
                    <strong>JTAC</strong>
                    <small>Appui aérien</small>
                  </span>
                </label>
              </div>
            </fieldset>

            <div class="atak-session-hub__actions">
              <button type="button" class="atak-session-hub__btn atak-session-hub__btn--ghost" id="atak-session-profile-reset">Repartir des suggestions</button>
              <button type="button" class="atak-session-hub__btn atak-session-hub__btn--ghost" id="atak-hub-profile-back" hidden>Retour</button>
              <button type="submit" class="atak-session-hub__btn atak-session-hub__btn--primary" id="atak-session-profile-submit">Continuer</button>
            </div>
          </form>
        </section>

        <section class="atak-session-hub__step" data-hub-step="link" hidden>
          <p class="atak-session-hub__kicker">Liaison</p>
          <h2 class="atak-session-hub__title">Lier le jeu (facultatif)</h2>
          <p class="atak-session-hub__lead">
            Générez un code, puis saisissez-le dans Arma : téléphone ATAK → <strong>Desktop</strong> → <strong>Connexion Athena</strong> (ou touche <strong>K</strong>).
            Vous pourrez aussi le faire plus tard depuis le compte.
          </p>
          <div class="atak-session-hub__link-box">
            <button type="button" class="atak-session-hub__btn atak-session-hub__btn--ghost" id="atak-hub-game-link-btn">Générer un code</button>
            <div class="atak-session-hub__link-result" id="atak-hub-game-link-result" hidden>
              <p class="atak-session-hub__link-label">Votre code</p>
              <p class="atak-session-hub__link-code" id="atak-hub-game-link-code">————</p>
              <p class="atak-session-hub__link-meta" id="atak-hub-game-link-meta"></p>
              <button type="button" class="atak-session-hub__btn atak-session-hub__btn--ghost" id="atak-hub-game-link-copy">Copier</button>
            </div>
            <p class="atak-session-hub__link-error" id="atak-hub-game-link-error" hidden></p>
          </div>
          <div class="atak-session-hub__actions">
            <button type="button" class="atak-session-hub__btn atak-session-hub__btn--ghost" id="atak-hub-link-back">Retour</button>
            <button type="button" class="atak-session-hub__btn atak-session-hub__btn--primary" id="atak-hub-enter">Entrer dans la session</button>
          </div>
        </section>
      </div>
    </div>
  </div>
  <?php elseif (!empty($phoneOperatorSession)): ?>
  <?php /* Session téléphone valide : pas de hub invité — entrée directe carte + BFT / médical / radio. */ ?>
  <?php else: ?>
  <div class="atak-session-hub atak-session-hub--guest" id="atak-session-guest-hub" role="dialog" aria-modal="true" aria-labelledby="atak-guest-hub-title" hidden>
    <div class="atak-session-hub__stage">
      <div class="atak-session-hub__visual" aria-hidden="true">
        <div class="atak-session-hub__visual-glow"></div>
        <div class="atak-session-hub__eagle">
          <img
            class="atak-session-hub__eagle-img"
            src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/img/atak-eagle-logo.png"
            alt=""
            width="512"
            height="512"
            decoding="async"
          />
          <span class="atak-session-hub__eagle-sweep"></span>
        </div>
        <div class="atak-session-hub__brand">
          <span class="atak-session-hub__brand-main">ATHENA</span>
          <span class="atak-session-hub__brand-sub">ATAK</span>
        </div>
        <p class="atak-session-hub__visual-tagline">Carte tactique · liaison <span class="atak-beta-badge" title="Programme d’accès anticipé">BÊTA</span></p>
      </div>
      <div class="atak-session-hub__panel">
        <p class="atak-session-hub__kicker">Connexion</p>
        <h2 id="atak-guest-hub-title" class="atak-session-hub__title">Bienvenue sur ATAK</h2>
        <p class="atak-session-hub__lead">
          Connectez-vous pour mémoriser votre profil de session, lier Arma et débloquer les outils de votre rôle.
          Vous pouvez aussi consulter la carte en invité.
        </p>
        <div class="atak-session-hub__actions">
          <a class="atak-session-hub__btn atak-session-hub__btn--primary" href="<?= htmlspecialchars(url('login') . '?redirect=' . rawurlencode(url('atak')), ENT_QUOTES, 'UTF-8') ?>">Se connecter</a>
          <button type="button" class="atak-session-hub__btn atak-session-hub__btn--ghost" id="atak-guest-continue">Continuer en invité</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="atak-main">
    <aside class="atak-panel-left" id="atak-panel-left">
      <div class="atak-panel-dock" id="atak-panel-left-dock" hidden>
        <p class="atak-panel-dock-title">Panneau détaché</p>
        <p class="atak-panel-dock-text">Ouvert dans une autre fenêtre.</p>
        <button type="button" class="atak-panel-chrome-btn" data-atak-popout-focus="left">Revenir au panneau</button>
        <button type="button" class="atak-panel-chrome-btn atak-panel-chrome-btn--ghost" data-atak-popout-restore="left">Réintégrer ici</button>
      </div>
      <div class="atak-left-rail">
      <nav class="atak-left-aside" role="tablist" aria-label="Panneaux latéraux">
        <button type="button" class="atak-tab active" role="tab" aria-selected="true" data-tab="cams" title="Cams"><span class="atak-tab-label">Cams</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="photos" title="Photos terrain"><span class="atak-tab-label">Photos</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="markers" title="Marqueurs"><span class="atak-tab-label">Marqueurs</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="chat" title="Tchat"><span class="atak-tab-label">Tchat</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="orders" title="Ordres"><span class="atak-tab-label">Ordres</span> <span class="atak-tab-badge" id="atak-orders-tab-badge" hidden></span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="medical" title="Médical — alertes, triage et MEDEVAC"><span class="atak-tab-label">Médical</span> <span class="atak-tab-badge atak-medical-tab-badge" id="atak-medical-tab-badge" hidden></span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="identification" title="Identification ami / ennemi (IFF)"><span class="atak-tab-label">Identification</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="situation" title="Tableau de situation"><span class="atak-tab-label">Situation</span> <span class="atak-tab-badge" id="atak-sitrep-count" hidden></span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="radio" title="Radio proximité"><span class="atak-tab-label">Radio</span> <span class="atak-tab-badge" id="atak-radio-tab-badge" hidden></span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="notes" title="Notes de session et tableurs temporaires"><span class="atak-tab-label">Notes</span> <span class="atak-tab-badge" id="atak-notes-dirty" hidden>·</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="pings" title="Pings"><span class="atak-tab-label">Pings</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="jtac" title="JTAC"><span class="atak-tab-label">JTAC</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="etat" title="État du personnel et logistique"><span class="atak-tab-label">État</span></button>
        <button type="button" class="atak-tab" role="tab" aria-selected="false" data-tab="replay" title="Relecture mission et bilan après-action"><span class="atak-tab-label">Relecture</span></button>
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
          <span>Silencieux — vibration seule</span>
        </label>
        <label class="atak-rail-audio-opt" for="atak-alert-silence-novib" title="Coupe les sons et toute vibration. Les bandeaux d’alerte restent visibles.">
          <input type="checkbox" id="atak-alert-silence-novib" />
          <span>Silencieux — sans vibration</span>
        </label>
        <p class="atak-rail-audio-hint" id="atak-alert-mute-hint" hidden role="status"></p>
      </section>
      </div>
      <div class="atak-left-body">
      <div class="atak-panel-chrome" id="atak-panel-left-chrome" role="toolbar" aria-label="Contrôles du panneau">
        <span class="atak-panel-chrome-label">Panneau</span>
        <button type="button" class="atak-panel-chrome-btn" id="atak-panel-left-popout" data-atak-popout="left" title="Ouvrir dans une autre fenêtre">Ouvrir dans une autre fenêtre</button>
      </div>
      <div class="atak-tabs-content active" id="tab-cams">
        <div class="atak-cams-panel">
          <div class="atak-cams-toolbar">
            <p class="atak-panel-hint atak-cams-toolbar-hint">Aperçus photo uniquement — pas de flux vidéo en direct. Demandez une nouvelle capture aux opérateurs en liaison.</p>
            <button type="button" class="atak-ops-btn atak-ops-btn--primary" id="atak-cams-request-view" title="Demander une nouvelle capture photo aux opérateurs">Demander une nouvelle vue</button>
          </div>
          <div class="atak-cams-list" id="atak-cams-list">
            <div class="atak-empty-state">
              <div class="atak-empty-state-icon" aria-hidden="true">▣</div>
              <p class="atak-empty-state-title">Aucun aperçu reçu</p>
              <p class="atak-empty-state-text">Les caméras casque et drones actifs en jeu apparaîtront ici. Seuls des aperçus photo sont transmis, pas de vidéo en direct.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-photos">
        <div class="atak-cams-panel">
          <div class="atak-cams-list" id="atak-photos-list">
            <div class="atak-empty-state">
              <div class="atak-empty-state-icon" aria-hidden="true">◫</div>
              <p class="atak-empty-state-title">Aucune photo reçue</p>
              <p class="atak-empty-state-text">Les vues capturées depuis la tablette ou les caméras casque apparaîtront ici dès leur remontée.</p>
            </div>
          </div>
        </div>
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
          <div class="atak-chat-compose">
            <ul id="atak-chat-mentions" class="atak-chat-mentions" hidden role="listbox" aria-label="Mentionner un effectif"></ul>
            <input type="text" id="atak-chat-input" placeholder="Émettre — @ pour mentionner" autocomplete="off" spellcheck="false" aria-autocomplete="list" aria-controls="atak-chat-mentions" />
          </div>
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
            <label class="atak-orders-field atak-orders-field--wide">
              <span>Type d’ordre</span>
              <div class="atak-orders-type-row">
                <select id="atak-order-type">
                    <optgroup label="Types courants" id="atak-order-type-builtin">
                    <option value="MOVE">Se déplacer</option>
                    <option value="HOLD">Tenir la position</option>
                    <option value="RECON">Reconnaissance</option>
                    <option value="CAS">Appui aérien</option>
                    <option value="QRF">Force de réaction</option>
                    <option value="FRAGO">Ordre fragmentaire (FRAGO)</option>
                  </optgroup>
                  <optgroup label="Types personnalisés" id="atak-order-type-custom-types" hidden></optgroup>
                  <optgroup label="Mes modèles" id="atak-order-type-custom" hidden></optgroup>
                </select>
                <button type="button" class="atak-order-tpl-btn" id="atak-order-type-add-btn" title="Créer un nouveau type d’ordre">Nouveau type</button>
              </div>
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
            <label class="atak-orders-field atak-orders-field--wide" id="atak-order-payload-wrap">
              <span>Précisions</span>
              <input type="text" id="atak-order-payload" placeholder="Consignes complémentaires (facultatif)" maxlength="400" />
            </label>
            <div class="atak-orders-frago atak-orders-field--wide" id="atak-order-frago-fields" hidden>
              <p class="atak-orders-frago-title">Ordre fragmentaire</p>
              <p class="atak-orders-frago-hint">Renseignez au moins une rubrique. Le destinataire reçoit le FRAGO structuré.</p>
              <div class="atak-orders-frago-grid">
                <label class="atak-orders-field">
                  <span>Situation</span>
                  <input type="text" id="atak-order-frago-sit" maxlength="200" placeholder="Contexte terrain" />
                </label>
                <label class="atak-orders-field">
                  <span>Mission</span>
                  <input type="text" id="atak-order-frago-mis" maxlength="200" placeholder="Objectif" />
                </label>
                <label class="atak-orders-field">
                  <span>Exécution</span>
                  <input type="text" id="atak-order-frago-exe" maxlength="200" placeholder="Manoeuvre" />
                </label>
                <label class="atak-orders-field">
                  <span>Soutien</span>
                  <input type="text" id="atak-order-frago-sup" maxlength="200" placeholder="Appui, logistique" />
                </label>
                <label class="atak-orders-field atak-orders-field--wide">
                  <span>Commandement</span>
                  <input type="text" id="atak-order-frago-cmd" maxlength="200" placeholder="Chaîne de commandement / liaisons" />
                </label>
              </div>
            </div>
            <label class="atak-orders-field atak-orders-field--wide atak-orders-check">
              <input type="checkbox" id="atak-order-radio-sim" checked />
              <span>Conditions radio réalistes (délai, brouillage fictif)</span>
            </label>
            <div class="atak-orders-commander-bar atak-orders-field--wide" data-atak-needs-command>
              <button type="button" class="atak-order-tpl-btn atak-order-tpl-btn--primary" id="atak-order-compose-open-btn">Fenêtre rapide d’émission</button>
              <button type="button" class="atak-order-tpl-btn" id="atak-order-compose-frago-btn">Rédiger un FRAGO…</button>
            </div>
            <div class="atak-orders-templates atak-orders-field--wide" id="atak-orders-type-form-wrap">
              <div class="atak-orders-templates-actions">
                <button type="button" class="atak-order-tpl-btn atak-order-tpl-btn--danger" id="atak-order-type-delete-btn" hidden>Retirer ce type</button>
              </div>
              <div class="atak-orders-tpl-form" id="atak-orders-type-form" hidden>
                <label class="atak-orders-field">
                  <span>Intitulé du type</span>
                  <input type="text" id="atak-order-type-label" maxlength="120" placeholder="Ex. Sécuriser le périmètre" />
                </label>
                <label class="atak-orders-field">
                  <span>Description (facultatif)</span>
                  <input type="text" id="atak-order-type-desc" maxlength="400" placeholder="Précisions visibles lors de la sélection" />
                </label>
                <div class="atak-orders-templates-actions">
                  <button type="button" class="atak-order-tpl-btn atak-order-tpl-btn--primary" id="atak-order-type-confirm-btn">Enregistrer le type</button>
                  <button type="button" class="atak-order-tpl-btn" id="atak-order-type-cancel-btn">Annuler</button>
                </div>
              </div>
            </div>
            <div class="atak-orders-templates atak-orders-field--wide" id="atak-orders-templates">
              <div class="atak-orders-templates-actions">
                <button type="button" class="atak-order-tpl-btn" id="atak-order-tpl-save-btn">Enregistrer comme modèle</button>
                <button type="button" class="atak-order-tpl-btn atak-order-tpl-btn--danger" id="atak-order-tpl-delete-btn" hidden>Retirer ce modèle</button>
              </div>
              <div class="atak-orders-tpl-form" id="atak-orders-tpl-form" hidden>
                <label class="atak-orders-field">
                  <span>Nom du modèle</span>
                  <input type="text" id="atak-order-tpl-label" maxlength="120" placeholder="Ex. Sécuriser le périmètre" />
                </label>
                <label class="atak-orders-field">
                  <span>Consignes par défaut</span>
                  <input type="text" id="atak-order-tpl-payload" maxlength="400" placeholder="Texte prérempli à l’émission (facultatif)" />
                </label>
                <div class="atak-orders-templates-actions">
                  <button type="button" class="atak-order-tpl-btn atak-order-tpl-btn--primary" id="atak-order-tpl-confirm-btn">Enregistrer le modèle</button>
                  <button type="button" class="atak-order-tpl-btn" id="atak-order-tpl-cancel-btn">Annuler</button>
                </div>
              </div>
            </div>
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
      <div class="atak-tabs-content" id="tab-identification" role="tabpanel">
        <div class="atak-iff-panel">
          <div class="atak-panel-strip">
            <span class="atak-panel-strip-title">Identification (IFF)</span>
            <div class="atak-panel-strip-actions">
              <button type="button" class="atak-ops-btn" id="atak-iff-refresh" title="Actualiser">Actualiser</button>
            </div>
          </div>
          <p class="atak-panel-hint">Défi / réponse pour confirmer qu’une unité est amie. Le TOC publie un défi ; les unités répondent avec le code convenu. Les contacts sans réponse dans le délai apparaissent en alerte.</p>
          <div class="atak-iff-alert-banner" id="atak-iff-alert-banner" hidden role="alert"></div>
          <div class="atak-iff-current" id="atak-iff-current">
            <p class="atak-iff-label">Défi courant</p>
            <p class="atak-iff-code" id="atak-iff-challenge-code">—</p>
            <p class="atak-iff-valid" id="atak-iff-valid-until">Aucun défi actif pour cette carte.</p>
            <p class="atak-iff-expire" id="atak-iff-expire-countdown" hidden></p>
            <p class="atak-iff-empty" id="atak-iff-empty-challenge">Publiez un défi ci-dessous pour démarrer l’identification.</p>
          </div>
          <div class="atak-ops-form atak-iff-form">
            <label class="atak-ops-field">Code de défi (facultatif — généré automatiquement si vide)
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
          <details class="atak-collapse atak-ops-section" data-atak-collapse="iff-respond" data-atak-collapse-default="0">
            <summary class="atak-collapse-sum">Répondre pour une unité (TOC)</summary>
            <div class="atak-collapse-body">
              <p class="atak-panel-hint">Utile pour valider une réponse depuis le poste de commandement, ou pour une unité hors liaison automatique.</p>
              <div class="atak-ops-form">
                <label class="atak-ops-field">Unité
                  <select id="atak-iff-respond-asset">
                    <option value="">Choisir une unité…</option>
                  </select>
                </label>
                <label class="atak-ops-field">Code de réponse
                  <input type="text" id="atak-iff-respond-code" maxlength="64" autocomplete="off" placeholder="Code transmis par l’unité" spellcheck="false" />
                </label>
                <button type="button" class="atak-ops-btn" id="atak-iff-respond-submit">Envoyer la réponse</button>
              </div>
            </div>
          </details>
          <div class="atak-iff-assets">
            <p class="atak-iff-label">État des réponses</p>
            <div id="atak-iff-assets-list">
              <p class="atak-panel-hint">Chargement…</p>
            </div>
          </div>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-situation" role="tabpanel">
        <div class="atak-sitrep-panel">
          <div class="atak-panel-strip">
            <span class="atak-panel-strip-title">Tableau de situation</span>
            <div class="atak-panel-strip-actions">
              <button type="button" class="atak-ops-btn" id="atak-sitrep-refresh" title="Actualiser">Actualiser</button>
            </div>
          </div>
          <p class="atak-panel-hint">Signalements de situation fusionnés (plusieurs sources proches se confirment). Cliquez une entrée pour centrer la carte.</p>
          <div id="atak-sitrep-list" class="atak-sitrep-list">
            <p class="atak-panel-hint">Chargement…</p>
          </div>
          <details class="atak-collapse atak-ops-section" data-atak-collapse="sitrep-help" data-atak-collapse-default="0">
            <summary class="atak-collapse-sum">Qui génère ? Comment ?</summary>
            <div class="atak-collapse-body">
              <ul class="atak-sitrep-help">
                <li>Les rapports sont créés ou fusionnés lorsqu’un client de jeu ou le TOC envoie une situation (type de cible, position, indicatif source).</li>
                <li>Depuis Arma, le mod peut transmettre ces éléments via la liaison prévue.</li>
                <li>Si un rapport du même type existe déjà à proximité (moins de 100 m, dernières minutes), le nouveau message est fusionné.</li>
                <li>Une seule source donne un avis provisoire ; plusieurs sources rapprochées renforcent jusqu’à un niveau confirmé.</li>
              </ul>
            </div>
          </details>
          <div class="atak-ops-form atak-sitrep-form">
            <p class="atak-iff-label">Nouveau signalement</p>
            <label class="atak-ops-field">Type de cible
              <select id="atak-sitrep-target">
                <option value="INFANTRY">Infanterie</option>
                <option value="VEHICLE">Véhicule</option>
                <option value="ARMOR">Blindé</option>
                <option value="AIR_DEFENSE">Défense antiaérienne</option>
                <option value="UNKNOWN">Non identifié</option>
              </select>
            </label>
            <div class="atak-sitrep-pos-row">
              <button type="button" class="atak-ops-btn atak-ops-btn--primary" id="atak-sitrep-pick-map">Pointer sur la carte</button>
              <span class="atak-sitrep-grid" id="atak-sitrep-grid-display">Aucune position</span>
            </div>
            <p class="atak-panel-hint" id="atak-sitrep-pick-hint" hidden>Cliquez sur la carte pour fixer la position du signalement.</p>
            <details class="atak-sitrep-coords-adv">
              <summary>Coordonnées manuelles (facultatif)</summary>
              <div class="atak-sitrep-coords-grid">
                <label class="atak-ops-field">Est (X)
                  <input type="number" id="atak-sitrep-x" step="1" placeholder="Ex. 15000" />
                </label>
                <label class="atak-ops-field">Nord (Y)
                  <input type="number" id="atak-sitrep-y" step="1" placeholder="Ex. 16000" />
                </label>
              </div>
            </details>
            <label class="atak-ops-field">Indicatif source
              <input type="text" id="atak-sitrep-source" maxlength="64" autocomplete="off" placeholder="Ex. TOC-1" />
            </label>
            <button type="button" class="atak-ops-btn atak-ops-btn--primary" id="atak-sitrep-submit">Publier le signalement</button>
            <p class="atak-sitrep-feedback" id="atak-sitrep-feedback" hidden></p>
          </div>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-medical">
        <div class="atak-medical-head">
          <p class="atak-panel-hint">Alertes transmises depuis le théâtre et unités à secourir. Utilisez les onglets pour séparer les urgences confirmées, les détections automatiques « au sol » (parfois de faux positifs) et les bilans. Les alertes disparaissent après 30 minutes. Masquer retire l’affichage ici uniquement — le journal Liaison et le tchat restent inchangés. Le triage est réservé aux médecins et responsables d’effectifs.</p>
          <div class="atak-medical-toolbar">
            <button type="button" class="atak-hints-toggle" data-atak-hints-toggle title="Masquer ou réafficher les textes d’aide des panneaux">Masquer les aides</button>
            <button type="button" class="atak-medical-clear-all" id="atak-medical-clear-all" title="Masquer toutes les alertes affichées" hidden>Tout masquer</button>
          </div>
          <div class="atak-medical-subtabs" id="atak-medical-subtabs" role="tablist" aria-label="Filtrer les assistances">
            <button type="button" class="atak-medical-subtab is-active" role="tab" aria-selected="true" data-medical-filter="urgences" title="Arrêt cardiaque et urgences confirmées">Urgences <span class="atak-medical-subtab-count" data-medical-count="urgences" hidden></span></button>
            <button type="button" class="atak-medical-subtab" role="tab" aria-selected="false" data-medical-filter="suivi" title="Détections automatiques au sol / immobilisation — à vérifier">Au sol / suivi <span class="atak-medical-subtab-count" data-medical-count="suivi" hidden></span></button>
            <button type="button" class="atak-medical-subtab" role="tab" aria-selected="false" data-medical-filter="autres" title="Bilans de santé et autres signalements">Bilans et autres <span class="atak-medical-subtab-count" data-medical-count="autres" hidden></span></button>
          </div>
        </div>
        <div class="atak-medical-list" id="atak-medical-list">
          <div class="atak-empty-state atak-medical-empty">
            <div class="atak-empty-state-icon" aria-hidden="true">✚</div>
            <p class="atak-empty-state-title">Aucune assistance</p>
            <p class="atak-empty-state-text">Les demandes médicales en cours s’afficheront ici.</p>
          </div>
        </div>
        <details class="atak-collapse atak-ops-section" data-atak-collapse="medical-medevac" data-atak-collapse-default="1" open>
          <summary class="atak-collapse-sum" id="atak-medevac-title">9-Line MEDEVAC</summary>
          <div class="atak-collapse-body">
            <p class="atak-panel-hint">Format US d’évacuation sanitaire — distinct de la 9-line d’appui aérien (onglet JTAC).</p>
            <button type="button" class="atak-ops-btn" id="atak-medevac-new">Nouvelle demande MEDEVAC</button>
            <div id="atak-medevac-form-fields" class="atak-ops-form" style="display:none;"></div>
            <div class="atak-medevac-list" id="atak-medevac-list"></div>
          </div>
        </details>
      </div>
      <div class="atak-tabs-content" id="tab-radio">
        <div class="atak-radio-head" id="atak-radio-head">
          <details class="atak-collapse atak-collapse--hint" data-atak-collapse="radio-help" data-atak-collapse-default="0">
            <summary class="atak-collapse-sum atak-collapse-sum--subtle">Aide radio</summary>
            <p class="atak-panel-hint">Qui émet près d’un opérateur en liaison, et sur quel réseau. L’écoute audio se fait en jeu ; ici vous suivez qui émet (pastilles, liste, alertes). Sur la tablette Overwatch, « Surveiller ce réseau » bascule aussi le canal radio actif.</p>
          </details>
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
        <details class="atak-collapse atak-ops-section" data-atak-collapse="radio-pace" data-atak-collapse-default="0">
          <summary class="atak-collapse-sum" id="atak-pace-title">Plan PACE</summary>
          <div class="atak-collapse-body">
            <p class="atak-panel-hint">Primaire · Alternatif · Contingence · Urgence — plan de fréquences redondant pour le théâtre.</p>
            <p class="atak-pace-meta" id="atak-pace-meta"></p>
            <div class="atak-pace-grid">
              <fieldset class="atak-pace-slot">
                <legend>Primaire</legend>
                <label class="atak-ops-field">Libellé <input id="atak-pace-primary-label" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Fréquence <input id="atak-pace-primary-freq" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Réseau <input id="atak-pace-primary-net" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Notes <input id="atak-pace-primary-notes" type="text" autocomplete="off" /></label>
              </fieldset>
              <fieldset class="atak-pace-slot">
                <legend>Alternatif</legend>
                <label class="atak-ops-field">Libellé <input id="atak-pace-alternate-label" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Fréquence <input id="atak-pace-alternate-freq" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Réseau <input id="atak-pace-alternate-net" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Notes <input id="atak-pace-alternate-notes" type="text" autocomplete="off" /></label>
              </fieldset>
              <fieldset class="atak-pace-slot">
                <legend>Contingence</legend>
                <label class="atak-ops-field">Libellé <input id="atak-pace-contingency-label" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Fréquence <input id="atak-pace-contingency-freq" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Réseau <input id="atak-pace-contingency-net" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Notes <input id="atak-pace-contingency-notes" type="text" autocomplete="off" /></label>
              </fieldset>
              <fieldset class="atak-pace-slot">
                <legend>Urgence</legend>
                <label class="atak-ops-field">Libellé <input id="atak-pace-emergency-label" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Fréquence <input id="atak-pace-emergency-freq" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Réseau <input id="atak-pace-emergency-net" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">Notes <input id="atak-pace-emergency-notes" type="text" autocomplete="off" /></label>
              </fieldset>
            </div>
            <div class="atak-pace-team-add">
              <p class="atak-panel-hint">Ajouter / mettre à jour une équipe</p>
              <label class="atak-ops-field">Équipe <input id="atak-pace-team-name" type="text" placeholder="Ex. Alpha" autocomplete="off" /></label>
              <div class="atak-pace-team-freqs">
                <label class="atak-ops-field">P <input id="atak-pace-team-p" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">A <input id="atak-pace-team-a" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">C <input id="atak-pace-team-c" type="text" autocomplete="off" /></label>
                <label class="atak-ops-field">E <input id="atak-pace-team-e" type="text" autocomplete="off" /></label>
              </div>
            </div>
            <div id="atak-pace-teams"></div>
            <button type="button" class="atak-ops-btn" id="atak-pace-save">Enregistrer le plan PACE</button>
          </div>
        </details>
      </div>
      <div class="atak-tabs-content" id="tab-notes" role="tabpanel">
        <div class="atak-notes-toolbar">
          <p class="atak-panel-hint atak-notes-toolbar-hint">Bloc-notes et tableurs pour cette carte. Enregistrez pour partager avec l’équipe.</p>
          <div class="atak-notes-toolbar-actions">
            <span class="atak-notes-dirty-label" id="atak-notes-dirty-label" hidden>Modifications non enregistrées</span>
            <button type="button" class="atak-ops-btn" id="atak-notes-expand" title="Élargir le panneau pour afficher les colonnes">Agrandir</button>
            <button type="button" class="atak-ops-btn atak-ops-btn--primary" id="atak-notes-save">Enregistrer les notes</button>
          </div>
        </div>
        <p class="atak-pace-meta" id="atak-notes-meta"></p>

        <details class="atak-notes-block" open>
          <summary class="atak-notes-block-sum" id="atak-notepad-title">Bloc-notes</summary>
          <textarea id="atak-notepad" class="atak-notepad" rows="6" maxlength="20000" placeholder="Notes libres de session…" spellcheck="true"></textarea>
        </details>

        <details class="atak-notes-block" open>
          <summary class="atak-notes-block-sum" id="atak-soi-sheet-title">SOI — consignes radio</summary>
          <p class="atak-panel-hint">Liste de réseaux (Organization Net List) : canal, indicatif, suffixe, fréquences, rôle. Complète le plan PACE (onglet Radio).</p>
          <div id="atak-sheet-soi" class="atak-sheet-wrap"></div>
          <button type="button" class="atak-ops-btn atak-sheet-add" data-sheet="soi">Ajouter une ligne</button>
        </details>

        <details class="atak-notes-block" open>
          <summary class="atak-notes-block-sum" id="atak-eta-sheet-title">Suivi ETA alliés</summary>
          <p class="atak-panel-hint">Heures d’arrivée estimées des forces amies / alliées sur le théâtre.</p>
          <div id="atak-sheet-eta" class="atak-sheet-wrap"></div>
          <button type="button" class="atak-ops-btn atak-sheet-add" data-sheet="eta">Ajouter une ligne</button>
        </details>

        <details class="atak-notes-block" open>
          <summary class="atak-notes-block-sum" id="atak-allied-sheet-title">Identifiants ATAK alliés</summary>
          <p class="atak-panel-hint">Carnet manuel pour des unités hors de votre communauté (pas le registre interne des opérateurs Athena).</p>
          <div id="atak-sheet-allied" class="atak-sheet-wrap"></div>
          <button type="button" class="atak-ops-btn atak-sheet-add" data-sheet="allied_ids">Ajouter une ligne</button>
        </details>
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
      <div class="atak-tabs-content" id="tab-etat" role="tabpanel">
        <details class="atak-collapse atak-ops-section" data-atak-collapse="etat-perstat" data-atak-collapse-default="1" open>
          <summary class="atak-collapse-sum" id="atak-perstat-title">PERSTAT</summary>
          <div class="atak-collapse-body">
            <p class="atak-panel-hint">État du personnel agrégé (RAS / WIA / KIA) à partir des positions et alertes médicales — sans ressaisie.</p>
            <div id="atak-perstat-body"></div>
          </div>
        </details>
        <details class="atak-collapse atak-ops-section" id="atak-logistics-section" data-atak-collapse="etat-logistics" data-atak-collapse-default="0">
          <summary class="atak-collapse-sum" id="atak-logistics-title">Suivi logistique</summary>
          <div class="atak-collapse-body">
            <p class="atak-panel-hint">Carburant et munitions remontés automatiquement depuis le jeu, triés du plus critique au plus confortable.</p>
            <div id="atak-logistics-body"></div>
          </div>
        </details>
        <details class="atak-collapse atak-ops-section" data-atak-collapse="etat-salute" data-atak-collapse-default="0">
          <summary class="atak-collapse-sum" id="atak-salute-title">Compte rendu SALUTE</summary>
          <div class="atak-collapse-body">
            <p class="atak-panel-hint">Taille · Activité · Localisation · Unité · Heure · Équipement — un champ par ligne.</p>
            <div class="atak-ops-form atak-salute-form">
              <label class="atak-ops-field">Taille (Size) <input id="atak-salute-size" type="text" autocomplete="off" placeholder="Ex. 2 véhicules, ~8 pax" /></label>
              <label class="atak-ops-field">Activité (Activity) <input id="atak-salute-activity" type="text" autocomplete="off" placeholder="Ex. en déplacement vers le nord" /></label>
              <label class="atak-ops-field">Localisation (Location) <input id="atak-salute-location" type="text" autocomplete="off" placeholder="Ex. carrefour sud du village" /></label>
              <label class="atak-ops-field">Unité (Unit) <input id="atak-salute-unit" type="text" autocomplete="off" placeholder="Ex. infanterie motorisée" /></label>
              <label class="atak-ops-field">Heure (Time) <input id="atak-salute-time" type="text" autocomplete="off" placeholder="Ex. 1412 Z" /></label>
              <label class="atak-ops-field">Équipement (Equipment) <input id="atak-salute-equipment" type="text" autocomplete="off" placeholder="Ex. RPG, mitrailleuse" /></label>
              <label class="atak-ops-field">Grille (facultatif) <input id="atak-salute-grid" type="text" autocomplete="off" placeholder="Ex. 14500 16820" /></label>
              <button type="button" class="atak-ops-btn" id="atak-salute-submit">Transmettre le SALUTE</button>
              <p class="atak-salute-feedback" id="atak-salute-feedback" hidden></p>
            </div>
          </div>
        </details>
      </div>
      <div class="atak-tabs-content" id="tab-replay" role="tabpanel" aria-label="Relecture mission">
        <div class="atak-replay-panel">
          <header class="atak-replay-head">
            <h3 class="atak-replay-title">Relecture mission</h3>
            <span class="atak-replay-badge" title="Bilan après-action">Après-action</span>
          </header>
          <p class="atak-panel-hint">Parcourez les positions enregistrées sur la carte, puis consultez le bilan de mission.</p>
          <div class="atak-replay-controls" role="group" aria-label="Commandes de relecture">
            <button type="button" class="atak-ops-btn atak-ops-btn--primary" id="atak-replay-play">Lecture</button>
            <button type="button" class="atak-ops-btn" id="atak-replay-pause">Pause</button>
            <button type="button" class="atak-ops-btn" id="atak-replay-aar-refresh" title="Actualiser le bilan après-action">Après-action</button>
            <button type="button" class="atak-ops-btn" id="atak-replay-export" title="Exporter le bilan en PDF">Export PDF</button>
            <label class="atak-replay-speed-wrap">
              <span class="atak-replay-speed-label">Vitesse</span>
              <select id="atak-replay-speed" class="atak-replay-speed" title="Vitesse de lecture">
                <option value="1" selected>×1</option>
                <option value="2">×2</option>
                <option value="4">×4</option>
                <option value="8">×8</option>
              </select>
            </label>
          </div>
          <label class="atak-replay-timeline">
            <span class="atak-replay-timeline-label">Chronologie</span>
            <input type="range" id="atak-replay-slider" class="atak-replay-slider" min="0" max="0" value="0" />
          </label>
          <div class="atak-replay-info-row">
            <p id="atak-replay-info" class="atak-replay-info" role="status">Ouvrez cet onglet pour charger les positions.</p>
            <button type="button" class="atak-ops-btn atak-replay-reload" id="atak-replay-reload" title="Recharger les positions">Actualiser</button>
          </div>
          <div id="atak-replay-events" class="atak-replay-events" aria-label="Événements clés" hidden></div>
          <div id="atak-replay-aar" class="atak-replay-aar" aria-live="polite">
            <p class="atak-panel-hint">Le bilan après-action s’affichera ici.</p>
          </div>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-liaison" role="tabpanel">
        <div class="atak-activity-panel">
          <details class="atak-collapse atak-web-presence" data-atak-collapse="liaison-presence" data-atak-collapse-default="1" open>
            <summary class="atak-collapse-sum" id="atak-web-presence-title">Sur le site</summary>
            <div class="atak-collapse-body">
              <p class="atak-web-presence-intro">Opérateurs connectés à la carte Athena depuis le portail.</p>
              <ul class="atak-web-presence-list" id="atak-web-presence-list" aria-live="polite"></ul>
              <p class="atak-web-presence-empty" id="atak-web-presence-empty">En attente de liaison</p>
            </div>
          </details>
          <details class="atak-collapse atak-activity-journal" data-atak-collapse="liaison-journal" data-atak-collapse-default="1" open>
            <summary class="atak-collapse-sum">Journal TOC</summary>
            <div class="atak-collapse-body">
          <div class="atak-activity-head">
            <p class="atak-activity-intro">Chronologie de la session : qui se connecte, les alertes du terrain, les ordres et les notes du TOC.</p>
            <div class="atak-activity-toolbar">
              <div class="atak-activity-meta" id="atak-activity-meta" hidden>
                <span class="atak-pill atak-pill--ok" id="atak-activity-meta-count">0</span>
                <span class="atak-activity-meta-label" id="atak-activity-meta-label">dans le journal</span>
              </div>
              <div class="atak-activity-actions">
                <a class="atak-activity-link" id="atak-activity-fullscreen" href="<?= $base ?>/atak/liaison">Voir tout</a>
                <button type="button" class="atak-activity-clear" id="atak-activity-clear" title="Mettre le journal de côté sans le supprimer">Vider</button>
              </div>
            </div>
          </div>
          <form class="atak-toc-form" id="atak-toc-form" autocomplete="off">
            <label class="atak-ops-field" for="atak-toc-note">Ajouter une entrée TOC
              <input type="text" id="atak-toc-note" maxlength="500" placeholder="Ex. Briefing terminé — départ axe nord à 15 min" />
            </label>
            <button type="submit" class="atak-ops-btn" id="atak-toc-submit">Publier</button>
          </form>
          <ul class="atak-activity-list" id="atak-activity-list" aria-live="polite"></ul>
          <div class="atak-empty-state atak-activity-empty" id="atak-activity-empty">
            <div class="atak-empty-state-icon" aria-hidden="true">⇄</div>
            <p class="atak-empty-state-title">Journal en attente</p>
            <p class="atak-empty-state-text">Connexions, indicatifs et échanges apparaîtront ici dès qu’un joueur est en liaison avec la carte.</p>
          </div>
            </div>
          </details>
        </div>
      </div>
      </div>
      <div class="atak-resize-handle atak-resize-handle--left" data-atak-resize="left" role="separator" aria-orientation="vertical" aria-label="Élargir le panneau" title="Élargir le panneau — double-clic pour réinitialiser" tabindex="0"></div>
    </aside>

    <div class="atak-map-wrap">
      <div class="atak-map-fab" id="atak-map-fab" aria-label="Afficher les panneaux">
        <button type="button" class="atak-map-fab__btn" id="atak-fab-left" title="Panneau outils">Outils</button>
        <button type="button" class="atak-map-fab__btn" id="atak-fab-right" title="Effectifs">Effectifs</button>
      </div>
      <button type="button" class="atak-map-tools-fab" id="atak-map-tools-fab" hidden title="Afficher la barre d’outils de la carte">Outils</button>
      <div class="atak-map-tools" id="atak-map-tools" role="toolbar" aria-label="Outils de la carte">
        <div class="atak-map-tools__row" id="atak-map-tools-row">
          <button type="button" class="atak-map-tools__btn" data-tool="goto" data-tool-slot="goto" title="Aller à une grille (G)">Grille</button>
          <button type="button" class="atak-map-tools__btn" data-tool="me" data-tool-slot="me" title="Centrer sur ma position (H)">Moi</button>
          <button type="button" class="atak-map-tools__btn" data-tool="follow" data-tool-slot="follow" title="Suivre ma position (F)" aria-pressed="false">Suivre</button>
          <span class="atak-map-tools__sep" data-tool-sep="nav" aria-hidden="true"></span>
          <button type="button" class="atak-map-tools__btn" data-tool="measure" data-tool-slot="measure" title="Mesurer une distance (M)" aria-pressed="false">Mesurer</button>
          <button type="button" class="atak-map-tools__btn" data-tool="note" data-tool-slot="note" title="Enregistrer une note sur la carte" aria-pressed="false">Note</button>
          <button type="button" class="atak-map-tools__btn atak-map-tools__btn--jackpot" data-tool="jackpot" data-tool-slot="jackpot" title="JACKPOT — marquer une cible de haute valeur" aria-pressed="false">JACKPOT</button>
          <span class="atak-map-tools__sep" data-tool-sep="mark" aria-hidden="true"></span>
          <button type="button" class="atak-map-tools__btn atak-map-tools__btn--zone" data-tool="search-zone" data-tool-slot="search-zone" title="Délimiter une zone de recherche" aria-pressed="false">Recherche</button>
          <button type="button" class="atak-map-tools__btn atak-map-tools__btn--zone" data-tool="perimeter" data-tool-slot="perimeter" title="Tracer un périmètre de sécurité" aria-pressed="false">Périmètre</button>
          <button type="button" class="atak-map-tools__btn atak-map-tools__btn--zone" data-tool="aoi" data-tool-slot="aoi" title="Délimiter une zone d’intérêt" aria-pressed="false">Intérêt</button>
          <button type="button" class="atak-map-tools__btn" data-tool="line" data-tool-slot="line" title="Tracer un trait" aria-pressed="false">Trait</button>
          <button type="button" class="atak-map-tools__btn" data-tool="clear-drawings" data-tool-slot="clear-drawings" title="Effacer les tracés et zones">Effacer</button>
          <span class="atak-map-tools__sep" data-tool-sep="draw" aria-hidden="true"></span>
          <label class="atak-map-tools__field" data-tool-slot="radius" title="Rayon de référence en mètres (mis à jour lors du tracé)">
            <span class="atak-map-tools__field-label">Rayon</span>
            <input type="number" id="atak-tool-radius" min="10" max="50000" step="10" value="500" inputmode="numeric" />
            <span class="atak-map-tools__field-unit">m</span>
          </label>
          <label class="atak-map-tools__field" data-tool-slot="speed" title="Vitesse pour le délai jusqu’au bord">
            <span class="atak-map-tools__field-label">Vitesse</span>
            <input type="number" id="atak-tool-speed" min="1" max="200" step="0.5" value="5" inputmode="decimal" />
            <span class="atak-map-tools__field-unit">km/h</span>
          </label>
          <button type="button" class="atak-map-tools__btn" data-tool="speed-foot" data-tool-slot="speed-presets" title="Vitesse à pied (5 km/h)">À pied</button>
          <button type="button" class="atak-map-tools__btn" data-tool="speed-vehicle" data-tool-slot="speed-presets" title="Vitesse véhicule (40 km/h)">Véhicule</button>
          <span class="atak-map-tools__metrics" id="atak-zone-metrics" data-atak-zone-metrics data-tool-slot="metrics" hidden role="status" aria-live="polite"></span>
          <span class="atak-map-tools__sep" data-tool-sep="view" aria-hidden="true"></span>
          <button type="button" class="atak-map-tools__btn atak-map-tools__btn--icon" data-tool="zoom-in" data-tool-slot="zoom" title="Zoom avant">+</button>
          <button type="button" class="atak-map-tools__btn atak-map-tools__btn--icon" data-tool="zoom-out" data-tool-slot="zoom" title="Zoom arrière">−</button>
          <button type="button" class="atak-map-tools__btn" data-tool="nvg" data-tool-slot="nvg" title="Vision nocturne (N)" aria-pressed="false">NVG</button>
          <span class="atak-map-tools__sep" data-tool-sep="chrome" aria-hidden="true"></span>
          <button type="button" class="atak-map-tools__btn atak-map-tools__btn--chrome" data-tool-ui="customize" title="Choisir les outils affichés" aria-expanded="false" aria-controls="atak-map-tools-prefs">Personnaliser</button>
          <button type="button" class="atak-map-tools__btn atak-map-tools__btn--chrome" data-tool-ui="collapse" title="Masquer la barre d’outils">Masquer</button>
        </div>
        <div class="atak-map-tools__prefs" id="atak-map-tools-prefs" hidden role="dialog" aria-label="Personnaliser la barre d’outils">
          <p class="atak-map-tools__prefs-title">Profils d’outils</p>
          <div class="atak-map-tools__prefs-presets" id="atak-map-tools-prefs-presets" role="group" aria-label="Profils de barre d’outils">
            <button type="button" class="atak-map-tools__btn" data-tool-ui="preset" data-preset="toc" title="Tous les outils du poste de commandement">TOC</button>
            <button type="button" class="atak-map-tools__btn" data-tool-ui="preset" data-preset="sl" title="Outils utiles au chef d’équipe">Chef d’équipe</button>
            <button type="button" class="atak-map-tools__btn" data-tool-ui="preset" data-preset="medic" title="Outils utiles au médecin">Médecin</button>
          </div>
          <p class="atak-map-tools__prefs-title">Outils visibles</p>
          <div class="atak-map-tools__prefs-grid" id="atak-map-tools-prefs-grid"></div>
          <div class="atak-map-tools__prefs-actions">
            <button type="button" class="atak-map-tools__btn" data-tool-ui="prefs-all">Tout afficher</button>
            <button type="button" class="atak-map-tools__btn" data-tool-ui="prefs-close">Fermer</button>
          </div>
        </div>
      </div>
      <div id="atak-map"></div>
      <div class="atak-replay-banner" id="atak-replay-banner" hidden role="status">
        <span class="atak-replay-banner-text">Relecture en cours — les positions live sont en pause</span>
        <button type="button" class="atak-ops-btn atak-ops-btn--primary" id="atak-replay-exit">Revenir au direct</button>
      </div>

      <div class="atak-drawer atak-drawer--fixed" id="atak-effectifs-drawer">
        <div class="atak-drawer__head">
          <span class="atak-drawer__title">Tableau des effectifs</span>
          <span class="atak-drawer__count" id="atak-effectifs-count" hidden></span>
        </div>
        <div class="atak-drawer__body" id="atak-effectifs-drawer-body">
          <table class="atak-effectifs-table">
            <colgroup>
              <col class="atak-col-cs" />
              <col class="atak-col-role" />
              <col class="atak-col-ft" />
              <col class="atak-col-link" />
              <col class="atak-col-hdg" />
              <col class="atak-col-grid" />
              <col class="atak-col-notes" />
              <col class="atak-col-actions" />
            </colgroup>
            <thead>
              <tr>
                <th scope="col">Indicatif</th>
                <th scope="col">Rôle</th>
                <th scope="col">Équipe</th>
                <th scope="col">Liaison</th>
                <th scope="col">Cap</th>
                <th scope="col">Grille</th>
                <th scope="col">Notes</th>
                <th scope="col"><span class="atak-sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody id="atak-units-table-body">
              <tr><td colspan="8" class="atak-drawer-empty">Aucun contact en liaison pour le moment.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <aside class="atak-panel-right" id="atak-panel-right">
      <div class="atak-resize-handle atak-resize-handle--right" data-atak-resize="right" role="separator" aria-orientation="vertical" aria-label="Élargir le panneau" title="Élargir le panneau — double-clic pour réinitialiser" tabindex="0"></div>
      <div class="atak-panel-dock" id="atak-panel-right-dock" hidden>
        <p class="atak-panel-dock-title">Effectifs détachés</p>
        <p class="atak-panel-dock-text">Ouverts dans une autre fenêtre.</p>
        <button type="button" class="atak-panel-chrome-btn" data-atak-popout-focus="right">Revenir au panneau</button>
        <button type="button" class="atak-panel-chrome-btn atak-panel-chrome-btn--ghost" data-atak-popout-restore="right">Réintégrer ici</button>
      </div>
      <div class="atak-panel-chrome atak-panel-chrome--right" id="atak-panel-right-chrome" role="toolbar" aria-label="Contrôles des effectifs">
        <span class="atak-panel-chrome-label">Effectifs</span>
        <button type="button" class="atak-panel-chrome-btn" id="atak-panel-right-popout" data-atak-popout="right" title="Ouvrir dans une autre fenêtre">Ouvrir dans une autre fenêtre</button>
      </div>
      <details class="atak-air-assets atak-collapse" data-atak-collapse="air-assets" data-atak-collapse-default="0">
        <summary class="atak-air-assets-header atak-collapse-sum">
          <span class="atak-air-assets-title">Appui aérien</span>
        </summary>
        <div class="atak-air-assets-list" id="atak-air-assets-list">
          <div class="atak-air-assets-empty atak-empty-state" id="atak-air-assets-empty">
            <p class="atak-empty-state-title">Aucun aéronef</p>
            <p class="atak-empty-state-text">Les pilotes enregistrent un vol depuis le menu Overwatch en jeu (touche K).</p>
          </div>
        </div>
      </details>
      <div class="atak-units-header">
        <div class="atak-units-title-row">
          <div class="atak-units-title">Effectifs</div>
        </div>
        <div class="atak-filter">
          <input type="text" id="atak-units-filter" placeholder="Filtrer par indicatif, rôle, notes…" />
          <button type="button" class="btn-live active" id="atak-filter-live">En liaison</button>
          <button type="button" class="btn-all" id="atak-filter-all">Tous</button>
        </div>
        <div class="atak-ft-filter-row">
          <label class="atak-ft-filter-label" for="atak-ft-filter">Équipe de feu
            <select id="atak-ft-filter" title="Filtrer la carte et la liste par équipe de feu">
              <option value="">Toutes les équipes de feu</option>
            </select>
          </label>
          <button type="button" class="atak-ops-btn atak-ops-btn--sm" id="atak-ft-refresh" title="Actualiser les équipes">Actualiser</button>
        </div>
      </div>
      <details class="atak-ft-composition atak-collapse" data-atak-collapse="ft-composition" data-atak-collapse-default="0">
        <summary class="atak-collapse-sum">Composition des équipes de feu</summary>
        <div class="atak-collapse-body">
          <p class="atak-panel-hint">Effectifs par équipe pendant l’opération (couleur + liaison).</p>
          <div id="atak-ft-composition"></div>
        </div>
      </details>
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
  <script src="<?= $base ?>/assets/js/atak-session-profile.js?v=202607270730"></script>
  <script src="<?= $base ?>/assets/js/atak-map-crs.js"></script>
  <?php if (!$atakMapConfigForJs): ?><script src="<?= $base ?>/assets/js/maps/altis.js"></script><?php endif; ?>
  <script src="<?= $base ?>/assets/vendor/milsymbol/milsymbol.js"></script>
  <script src="<?= $base ?>/assets/vendor/milstd/milstd2525.js"></script>
  <script src="<?= $base ?>/assets/js/milstd-catalog.js"></script>
  <script src="<?= $base ?>/assets/js/nato-sidc-icons.js"></script>
  <script src="<?= $base ?>/assets/js/arma-marker-catalog.js?v=202607261745"></script>
  <script src="<?= $base ?>/assets/js/arma-map-markers.js?v=202607270730"></script>
  <script src="<?= $base ?>/assets/js/atak-symbol-picker.js"></script>
  <script src="<?= $base ?>/assets/js/atak-unit-popup.js?v=202607261735"></script>
  <script src="<?= $base ?>/assets/js/atak-map.js?v=202607271230"></script>
  <script src="<?= $base ?>/assets/js/atak-map-tools.js?v=202607270700"></script>
  <script src="<?= $base ?>/assets/js/atak-socket.js?v=202607261905"></script>
  <script src="<?= $base ?>/assets/js/atak-units.js?v=202607270700"></script>
  <script src="<?= $base ?>/assets/js/atak-fire-teams.js?v=202607270700"></script>
  <script src="<?= $base ?>/assets/js/atak-replay.js?v=202607270730"></script>
  <script src="<?= $base ?>/assets/js/mission-cycle-badge.js?v=202607270700"></script>
  <script src="<?= $base ?>/assets/js/tacmap-tactical-alerts.js"></script>
  <script src="<?= $base ?>/assets/js/tacmap-weather.js"></script>
  <script src="<?= $base ?>/assets/js/atak-chat.js?v=202607270730"></script>
  <script src="<?= $base ?>/assets/js/atak-orders.js?v=202607261905"></script>
  <script src="<?= $base ?>/assets/js/atak-collapse.js"></script>
  <script src="<?= $base ?>/assets/js/atak-medical-alerts.js?v=202607262010"></script>
  <script src="<?= $base ?>/assets/js/atak-medevac.js?v=202607262010"></script>
  <script src="<?= $base ?>/assets/js/atak-radio.js"></script>
  <script src="<?= $base ?>/assets/js/atak-soi.js"></script>
  <script src="<?= $base ?>/assets/js/atak-session-workspace.js"></script>
  <script src="<?= $base ?>/assets/js/atak-pings.js"></script>
  <script src="<?= $base ?>/assets/js/atak-markers.js?v=202607261735"></script>
  <script src="<?= $base ?>/assets/js/atak-map-shapes.js?v=202607262015"></script>
  <script src="<?= $base ?>/assets/js/atak-context-menu.js?v=202607262015"></script>
  <script src="<?= $base ?>/assets/js/atak-unit-menu.js?v=202607261735"></script>
  <script src="<?= $base ?>/assets/js/atak-jtac.js"></script>
  <script src="<?= $base ?>/assets/js/atak-salute.js"></script>
  <script src="<?= $base ?>/assets/js/atak-iff.js?v=202607270700"></script>
  <script src="<?= $base ?>/assets/js/atak-sitrep.js?v=202607270730"></script>
  <script src="<?= $base ?>/assets/js/atak-ops-status.js?v=202607270700"></script>
  <script src="<?= $base ?>/assets/js/atak-transmissions.js"></script>
  <script src="<?= $base ?>/assets/js/atak-cams.js?v=202607270730"></script>
  <script src="<?= $base ?>/assets/js/atak-air-assets.js"></script>
  <script src="<?= $base ?>/assets/js/atak-laser-codes.js"></script>
  <script src="<?= $base ?>/assets/js/atak-activity.js"></script>
  <script src="<?= $base ?>/assets/js/atak-arma-offline.js"></script>
  <script src="<?= $base ?>/assets/js/atak-sounds.js?v=202607271230"></script>
  <script src="<?= $base ?>/assets/js/atak-panel-chrome.js"></script>
  <script src="<?= $base ?>/assets/js/atak-shell-chrome.js?v=202607261900"></script>
  <script src="<?= $base ?>/assets/js/atak-roleplay-effects.js"></script>
  <script src="<?= $base ?>/assets/js/atak-roleplay-ctab.js"></script>
  <script>
    (function () {
      var HINTS_KEY = 'atak_hide_panel_hints';

      function hintsHidden() {
        try {
          return localStorage.getItem(HINTS_KEY) === '1';
        } catch (e) {
          return false;
        }
      }

      function setHintsHidden(hidden) {
        try {
          if (hidden) localStorage.setItem(HINTS_KEY, '1');
          else localStorage.removeItem(HINTS_KEY);
        } catch (e) {}
        document.body.classList.toggle('atak-hints-hidden', !!hidden);
        var cb = document.getElementById('atak-hide-panel-hints');
        if (cb) cb.checked = !!hidden;
        document.querySelectorAll('[data-atak-hints-toggle]').forEach(function (btn) {
          btn.textContent = hidden ? 'Afficher les aides' : 'Masquer les aides';
          btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
        });
      }

      function toggleHints() {
        setHintsHidden(!document.body.classList.contains('atak-hints-hidden'));
      }

      setHintsHidden(hintsHidden());
      var cbInit = document.getElementById('atak-hide-panel-hints');
      if (cbInit) {
        cbInit.addEventListener('change', function () {
          setHintsHidden(!!cbInit.checked);
        });
      }
      document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-atak-hints-toggle]') : null;
        if (!btn) return;
        e.preventDefault();
        toggleHints();
      });

      (function initPhoneOperatorBadge() {
        var ttlEl = document.getElementById('atak-phone-op-ttl');
        var badge = document.getElementById('atak-phone-op-badge');
        if (!ttlEl || !badge) return;
        var raw = ttlEl.getAttribute('data-expires-at') || '';
        if (!raw && window.ATAK_PHONE_SESSION) raw = String(window.ATAK_PHONE_SESSION.expires_at || '');
        if (!raw) {
          ttlEl.textContent = '';
          return;
        }
        function parseExpires(s) {
          s = String(s || '').trim();
          if (!s) return NaN;
          if (/^\d+$/.test(s)) return parseInt(s, 10) * (s.length <= 10 ? 1000 : 1);
          var iso = s.indexOf('T') >= 0 ? s : s.replace(' ', 'T');
          if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += 'Z';
          return Date.parse(iso);
        }
        var expiresMs = parseExpires(raw);
        if (isNaN(expiresMs)) {
          ttlEl.textContent = '';
          return;
        }
        function tick() {
          var rem = Math.floor((expiresMs - Date.now()) / 1000);
          badge.classList.toggle('is-expired', rem <= 0);
          badge.classList.toggle('is-expiring', rem > 0 && rem <= 300);
          if (rem <= 0) {
            ttlEl.textContent = 'expiré';
            return;
          }
          var h = Math.floor(rem / 3600);
          var m = Math.floor((rem % 3600) / 60);
          var sec = rem % 60;
          if (h > 0) {
            ttlEl.textContent = h + ' h ' + (m < 10 ? '0' : '') + m;
          } else {
            ttlEl.textContent = m + ' min ' + (sec < 10 ? '0' : '') + sec;
          }
        }
        tick();
        setInterval(tick, 1000);
      })();

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
        if (opts.silent !== true && window.ATAKSounds) {
          if (opts.order && typeof window.ATAKSounds.playOrder === 'function') {
            window.ATAKSounds.playOrder({
              highPriority: !!opts.highPriority,
              priority: opts.priority
            });
          } else if (opts.priority && typeof window.ATAKSounds.playPriority === 'function') {
            window.ATAKSounds.playPriority();
          } else if (typeof window.ATAKSounds.play === 'function') {
            window.ATAKSounds.play();
          }
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

      function resolveAtakMapId() {
        if (window.ATAKSocket && typeof window.ATAKSocket.getMapId === 'function') {
          return window.ATAKSocket.getMapId();
        }
        return (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0)
          ? window.ATAK_DEFAULT_MAP_ID
          : 1;
      }

      function startAtakApplication() {
      var mapId = (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0)
        ? window.ATAK_DEFAULT_MAP_ID
        : 1;
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
          if (window.ATAKIFF && window.ATAKIFF.refresh) window.ATAKIFF.refresh();
          if (window.ATAKSitrep && window.ATAKSitrep.refresh) window.ATAKSitrep.refresh();
          if (window.ATAKTransmissions && typeof window.ATAKTransmissions.refresh === 'function') {
            window.ATAKTransmissions.refresh();
          }
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
      if (!window.ATAK_POPOUT) {
        ATAKMap.init(mapId);
      }
      if (mapSelect && window.ATAK_MAPS_CONFIGS) {
        mapSelect.addEventListener('change', function () {
          var slug = this.value;
          if (!slug || !window.ATAK_MAPS_CONFIGS[slug]) return;
          window.ATAK_MAP_CONFIG = window.ATAK_MAPS_CONFIGS[slug];
          try { localStorage.setItem('atak_map_slug', slug); } catch (e) {}
          if (!window.ATAK_POPOUT) ATAKMap.init(mapId);
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
        var hudNet = document.querySelector('[data-hud-net]');
        if (hudNet) {
          hudNet.textContent = online ? 'En liaison' : 'Coupée';
          hudNet.classList.toggle('atak-map-hud__ok', !!online);
          hudNet.classList.toggle('atak-map-hud__bad', !online);
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
          if (window.ATAKCams.refresh) ATAKCams.refresh();
          else if (window.ATAKCams.fetchReconImages) ATAKCams.fetchReconImages();
          else ATAKCams.fetchIntelPhotos();
        }
        if (window.ATAKAirAssets) ATAKAirAssets.fetchAirAssets();
        if (window.ATAKMapShapes) ATAKMapShapes.fetchShapes();
        if (window.ATAKLaserCodes) ATAKLaserCodes.fetchLaserCodes();
        if (window.ATAKMap && window.ATAKMap.pollMarkers) window.ATAKMap.pollMarkers();
        else if (window.ATAKMarkers && window.ATAKMarkers.renderFromMap) window.ATAKMarkers.renderFromMap();
        if (window.TacmapWeather) {
          var wEl = document.getElementById('atak-weather');
          var wVal = document.getElementById('atak-weather-value');
          var mid = resolveAtakMapId();
          var ab = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
          TacmapWeather.poll(ab || (window.ATAK_API_BASE || ''), mid, wEl, { compact: true, valueEl: wVal });
        }
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
        var mid = resolveAtakMapId();
        Promise.all([
          measurePingLatency(),
          fetch('<?= url("api/atak/stats") ?>?mapId=' + encodeURIComponent(mid), { credentials: 'include', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
        ]).then(function (results) {
          var d = results[1];
          if (!d) {
            refreshLiaisonMetrics(null);
            return;
          }
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
          if (d.transmissions && window.ATAKTransmissions && typeof window.ATAKTransmissions.render === 'function') {
            window.ATAKTransmissions.render(d.transmissions);
          }
        }).catch(function () {
          refreshLiaisonMetrics(null);
        });
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
          if (window.ATAKSessionWorkspace && typeof window.ATAKSessionWorkspace.setPanelWide === 'function') {
            window.ATAKSessionWorkspace.setPanelWide(tab === 'notes');
          }
          if (tab === 'replay' && window.ATAKReplay && typeof window.ATAKReplay.load === 'function') {
            window.ATAKReplay.load();
            if (typeof window.ATAKReplay.loadAar === 'function') window.ATAKReplay.loadAar();
          }
          if (tab === 'identification' && window.ATAKIFF && typeof window.ATAKIFF.onTabActivated === 'function') {
            window.ATAKIFF.onTabActivated();
          }
          if (tab === 'situation' && window.ATAKSitrep && typeof window.ATAKSitrep.onTabActivated === 'function') {
            window.ATAKSitrep.onTabActivated();
          }
        });
      });
      (function syncLiaisonFullscreenHref() {
        var link = document.getElementById('atak-activity-fullscreen');
        if (!link) return;
        function update() {
          var mid = resolveAtakMapId();
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
        var mid = resolveAtakMapId();
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
            if (window.ATAKTransmissions && typeof window.ATAKTransmissions.refresh === 'function') {
              window.ATAKTransmissions.refresh();
            }
          })
          .catch(function () {});
      }
      refreshWebPresence();
      setInterval(refreshWebPresence, 20000);
      if (window.ATAKTransmissions && typeof window.ATAKTransmissions.refresh === 'function') {
        window.ATAKTransmissions.refresh();
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
      if (window.ATAKReplay && typeof window.ATAKReplay.init === 'function') {
        ATAKReplay.init();
      }
      if (window.MissionCycleBadge) {
        MissionCycleBadge.start({
          badgeId: 'mission-cycle-badge',
          hubUrl: <?= json_encode(url('back-office/atak/cycle-mission')) ?>,
        });
      }
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
        var mapId = resolveAtakMapId();
        fetch('<?= url("api/atak/stats") ?>?mapId=' + encodeURIComponent(mapId), { credentials: 'include', cache: 'no-store' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
          if (!d) {
            if (armaEl) armaEl.textContent = '—';
            if (unitsCountEl) unitsCountEl.textContent = '—';
            if (activeCallsignsEl) activeCallsignsEl.textContent = '—';
            return;
          }
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
          if (d.transmissions && window.ATAKTransmissions && typeof window.ATAKTransmissions.render === 'function') {
            window.ATAKTransmissions.render(d.transmissions);
          }
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
                metaEl.textContent = res.body.hint || 'Dans Arma : téléphone ATAK → Desktop → Connexion Athena (ou touche K), puis entrez ce code.';
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
              if (qrImg) {
                qrImg.classList.remove('atak-phone-link-qr--err');
                var qrUrl = body.qr_image_url
                  ? (body.qr_image_url + (body.qr_image_url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now())
                  : '';
                function showQrError() {
                  qrImg.removeAttribute('src');
                  qrImg.hidden = true;
                  qrImg.classList.add('atak-phone-link-qr--err');
                  if (metaEl) {
                    metaEl.textContent = (formatExpires(body.expires_at) || 'Code prêt') +
                      ' — le QR n’a pas pu être affiché ; utilisez le code ci-dessus ou le lien.';
                  }
                }
                function setQrSrc(src, allowUrlFallback) {
                  qrImg.hidden = false;
                  qrImg.onload = function () {
                    qrImg.classList.remove('atak-phone-link-qr--err');
                    qrImg.hidden = false;
                  };
                  qrImg.onerror = function () {
                    if (allowUrlFallback && qrUrl && src !== qrUrl) {
                      setQrSrc(qrUrl, false);
                      return;
                    }
                    showQrError();
                  };
                  qrImg.src = src;
                }
                if (body.qr_image_data_uri) {
                  setQrSrc(body.qr_image_data_uri, true);
                } else if (qrUrl) {
                  setQrSrc(qrUrl, false);
                } else {
                  showQrError();
                }
              }
              if (openLink) {
                var openHref = body.pair_url || body.connect_url || '';
                if (openHref) {
                  openLink.href = openHref;
                  openLink.hidden = false;
                } else {
                  openLink.hidden = true;
                }
              }
              if (metaEl && !(qrImg && qrImg.hidden && qrImg.classList.contains('atak-phone-link-qr--err'))) {
                var entryHint = body.connect_url ? (' — ou saisissez le code sur ' + String(body.connect_url).replace(/^https?:\/\//i, '')) : '';
                metaEl.textContent = (formatExpires(body.expires_at) || 'Valable 15 minutes') +
                  ' — scannez le QR' + entryHint + '.';
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
  <div id="atak-order-compose-modal" class="atak-order-compose-modal" hidden role="dialog" aria-modal="true" aria-labelledby="atak-order-compose-title">
    <div class="atak-order-compose-backdrop" data-order-compose-close></div>
    <div class="atak-order-compose-panel">
      <header class="atak-order-compose-head">
        <h2 id="atak-order-compose-title">Émettre un ordre</h2>
        <button type="button" class="atak-order-compose-close" data-order-compose-close aria-label="Fermer">×</button>
      </header>
      <p class="atak-order-compose-hint">Fenêtre rapide pour les chefs d’unité — même flux que la rédaction in-game.</p>
      <div class="atak-order-compose-grid">
        <label class="atak-orders-field">
          <span>Nature</span>
          <select id="atak-compose-type">
            <option value="MOVE">Se déplacer</option>
            <option value="HOLD">Tenir la position</option>
            <option value="RECON">Reconnaissance</option>
            <option value="CAS">Appui aérien</option>
            <option value="QRF">Force de réaction</option>
            <option value="FRAGO">Ordre fragmentaire (FRAGO)</option>
          </select>
        </label>
        <label class="atak-orders-field">
          <span>Priorité</span>
          <select id="atak-compose-priority">
            <option value="ROUTINE">Routine</option>
            <option value="IMPORTANT" selected>Important</option>
            <option value="URGENT">Urgent</option>
            <option value="CONTACT">Contact</option>
          </select>
        </label>
        <label class="atak-orders-field">
          <span>Destinataire</span>
          <select id="atak-compose-target-type">
            <option value="all">Toute l’équipe</option>
            <option value="group">Groupe en jeu</option>
            <option value="fire_team">Fire team</option>
            <option value="user">Utilisateur</option>
            <option value="solo">ATAK Solo</option>
            <option value="channel">Canal</option>
          </select>
        </label>
        <label class="atak-orders-field" id="atak-compose-target-wrap" hidden>
          <span id="atak-compose-target-label">Cible</span>
          <select id="atak-compose-target-ref">
            <option value="">Choisir…</option>
          </select>
        </label>
        <label class="atak-orders-field atak-orders-field--wide" id="atak-compose-payload-wrap">
          <span>Consignes</span>
          <textarea id="atak-compose-payload" rows="3" maxlength="400" placeholder="Consignes complémentaires (facultatif)"></textarea>
        </label>
        <div class="atak-orders-frago atak-orders-field--wide" id="atak-compose-frago-fields" hidden>
          <label class="atak-orders-field"><span>Situation</span><input type="text" id="atak-compose-frago-sit" maxlength="200" /></label>
          <label class="atak-orders-field"><span>Mission</span><input type="text" id="atak-compose-frago-mis" maxlength="200" /></label>
          <label class="atak-orders-field"><span>Exécution</span><input type="text" id="atak-compose-frago-exe" maxlength="200" /></label>
          <label class="atak-orders-field"><span>Soutien</span><input type="text" id="atak-compose-frago-sup" maxlength="200" /></label>
          <label class="atak-orders-field atak-orders-field--wide"><span>Commandement</span><input type="text" id="atak-compose-frago-cmd" maxlength="200" /></label>
        </div>
        <label class="atak-orders-field atak-orders-field--wide atak-orders-check">
          <input type="checkbox" id="atak-compose-radio-sim" checked />
          <span>Conditions radio réalistes</span>
        </label>
      </div>
      <footer class="atak-order-compose-actions">
        <button type="button" class="atak-order-issue-submit" id="atak-compose-send-btn">Envoyer</button>
        <button type="button" class="atak-order-tpl-btn" data-order-compose-close>Fermer</button>
      </footer>
    </div>
  </div>
</body>
</html>
