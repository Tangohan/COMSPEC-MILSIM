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
$currentUser = $currentUser ?? null;
$atakUserForJs = $atakUserForJs ?? null;
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
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link href="<?= $base ?>/assets/css/atak.css" rel="stylesheet" />
  <link href="<?= $base ?>/assets/css/atak-map-popups.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet" />
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
  </script>
</head>
<body class="atak-page">
  <div id="atak-boot-overlay" class="atak-boot-overlay" role="status" aria-live="polite" aria-busy="true">
    <div class="atak-boot-inner">
      <div class="atak-boot-spinner" aria-hidden="true"></div>
      <p class="atak-boot-label">Chargement de la Tacmap…</p>
      <p class="atak-boot-hint">Les informations affichées correspondent uniquement à la communauté à laquelle vous êtes connecté.</p>
    </div>
  </div>
  <header class="atak-header">
    <div class="atak-header-brand">
      <div class="atak-logo-wrap">
        <span class="atak-logo">ATHENA</span>
        <span class="atak-overwatch">ATAK</span>
      </div>
      <span class="atak-header-tagline" title="Image tactique commune">Image tactique</span>
      <div class="atak-header-chips" aria-label="État de la carte">
        <span class="atak-chip atak-chip--live" id="atak-status" title="État du réseau">
          <span class="dot" aria-hidden="true"></span>
          <span class="atak-chip-label">Réseau actif</span>
        </span>
        <span class="atak-chip" id="atak-chip-liaison" title="Dernière activité reçue depuis le théâtre">
          <span class="atak-chip-key">Liaison</span>
          <span class="atak-chip-value" id="atak-chip-liaison-value">En attente</span>
        </span>
        <span class="atak-chip atak-chip--muted" id="atak-chip-sync" title="Dernière actualisation du journal">
          <span class="atak-chip-key">Journal</span>
          <span class="atak-chip-value" id="atak-chip-sync-value">—</span>
        </span>
        <span class="atak-chip atak-chip--muted" id="atak-chip-contacts" title="Contacts en liaison">
          <span class="atak-chip-key">Contacts</span>
          <span class="atak-chip-value" id="atak-chip-contacts-value">0</span>
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
      <button type="button" class="atak-btn-game-link" id="atak-btn-game-link" title="Générer un code pour lier Arma à votre compte">Connexion en jeu</button>
      <?php endif; ?>
      <button type="button" class="atak-btn-account" id="atak-btn-account" title="Compte et paramètres">Compte</button>
    </div>
  </header>

  <div class="atak-os-strip" role="note">
    <p class="atak-os-strip-lead"><strong>ATAK Athena</strong> — vue partagée du théâtre : positions, tchat, marqueurs et alertes médicales en direct depuis Arma.</p>
    <p class="atak-os-strip-hint">Pour lier le mod à votre compte : <strong>Connexion en jeu</strong> → générer un code → le saisir en jeu (touche K → Connecter mon compte Athena).</p>
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
          <span class="atak-pill atak-pill--ok">15 min · usage unique</span>
        </div>
        <p class="atak-game-link-hint">Générez un code, puis saisissez-le dans Arma : touche <strong>K</strong> → <strong>Connecter mon compte Athena</strong>. Le code expire après 15 minutes et ne peut être utilisé qu’une fois.</p>
        <button type="button" class="atak-game-link-btn" id="atak-game-link-btn">Générer un code</button>
        <div class="atak-game-link-result" id="atak-game-link-result" hidden>
          <p class="atak-game-link-code-label">Votre code</p>
          <p class="atak-game-link-code" id="atak-game-link-code">————</p>
          <p class="atak-game-link-meta" id="atak-game-link-meta"></p>
          <button type="button" class="atak-game-config-copy" id="atak-game-link-copy" title="Copier le code">Copier</button>
        </div>
        <p class="atak-game-link-error" id="atak-game-link-error" hidden></p>
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
        <p><?= !empty($currentUser['steam_id']) ? 'Compte Steam associé' : 'Non renseignée' ?></p>
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
    </div>
  </aside>

  <div class="atak-connection-lost" id="atak-connection-lost" role="alert"><span id="atak-connection-lost-msg">Connexion perdue. Reconnexion…</span></div>
  <div class="atak-error-toast" id="atak-error-toast" role="alert" aria-live="polite"></div>
  <div class="atak-notification-toast" id="atak-notification-toast" role="status" aria-live="polite"></div>
  <div class="atak-medical-banner" id="atak-medical-banner" role="alert" aria-live="assertive" hidden></div>

  <div class="atak-main">
    <aside class="atak-panel-left" id="atak-panel-left">
      <div class="atak-tabs-headers">
        <button type="button" class="atak-tab active" data-tab="cams">Cams</button>
        <button type="button" class="atak-tab" data-tab="markers">Marqueurs</button>
        <button type="button" class="atak-tab" data-tab="chat">Tchat</button>
        <button type="button" class="atak-tab" data-tab="medical">Assistances <span class="atak-tab-badge atak-medical-tab-badge" id="atak-medical-tab-badge" hidden></span></button>
        <button type="button" class="atak-tab" data-tab="pings">Pings</button>
        <button type="button" class="atak-tab" data-tab="jtac">JTAC</button>
        <button type="button" class="atak-tab" data-tab="liaison">Liaison <span class="atak-tab-badge atak-tab-badge--soft" id="atak-liaison-tab-badge" hidden></span></button>
        <button type="button" class="atak-toggle" id="atak-toggle-left" title="Réduire">◀</button>
      </div>
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
        <div class="atak-chat-messages" id="atak-chat-messages">
          <div class="atak-empty-state atak-empty-state--compact" id="atak-chat-empty">
            <p class="atak-empty-state-title">Aucun message</p>
            <p class="atak-empty-state-text">Les échanges d’équipe s’afficheront ici.</p>
          </div>
        </div>
        <div class="atak-chat-input-wrap">
          <input type="text" id="atak-chat-input" placeholder="Envoyer un message…" />
          <button type="button" id="atak-chat-send">Envoyer</button>
        </div>
      </div>
      <div class="atak-tabs-content" id="tab-medical">
        <p class="atak-panel-hint">Alertes transmises depuis le théâtre (combattant au sol, rythme cardiaque à zéro) et unités à secourir.</p>
        <div class="atak-medical-list" id="atak-medical-list">
          <div class="atak-empty-state atak-medical-empty">
            <div class="atak-empty-state-icon" aria-hidden="true">✚</div>
            <p class="atak-empty-state-title">Aucune assistance</p>
            <p class="atak-empty-state-text">Les demandes médicales en cours s’afficheront ici.</p>
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
      <div class="atak-tabs-content" id="tab-liaison">
        <div class="atak-activity-panel">
          <div class="atak-activity-head">
            <p class="atak-activity-intro">Suivi des connexions en jeu, des changements d’indicatif et des échanges récents avec la carte.</p>
            <div class="atak-activity-meta" id="atak-activity-meta" hidden>
              <span class="atak-pill atak-pill--ok" id="atak-activity-meta-count">0</span>
              <span class="atak-activity-meta-label" id="atak-activity-meta-label">événements récents</span>
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
    </aside>

    <div class="atak-map-wrap">
      <div id="atak-map"></div>
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
          <span class="atak-units-count" id="atak-units-count" title="Contacts affichés">0</span>
          <button type="button" class="atak-toggle" id="atak-toggle-right" title="Réduire">▶</button>
        </div>
        <div class="atak-units-summary" id="atak-units-summary" aria-live="polite">
          <span class="atak-units-summary-item atak-units-summary-item--live" id="atak-units-sum-live">0 en liaison</span>
          <span class="atak-units-summary-item atak-units-summary-item--delayed" id="atak-units-sum-delayed">0 en retard</span>
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

  <section class="atak-game-config" id="atak-game-config" aria-labelledby="atak-game-config-title">
    <button type="button" id="atak-game-config-toggle" class="atak-game-config-toggle" aria-expanded="false" aria-controls="atak-game-config-body">
      <span class="atak-game-config-toggle-icon" aria-hidden="true">▼</span>
      <span class="atak-accordion-title-wrap">
        <span id="atak-game-config-title">Configuration pour le jeu</span>
        <span class="atak-accordion-subtitle">Adresse de liaison, serveur Arma et guides d’installation</span>
      </span>
      <span class="atak-pill <?= !empty($nodeAtakUrl) ? 'atak-pill--ok' : 'atak-pill--warn' ?>" id="atak-config-summary-pill"><?= !empty($nodeAtakUrl) ? 'Prête' : 'À compléter' ?></span>
    </button>
    <div id="atak-game-config-body" class="atak-game-config-body" hidden>
      <div class="atak-game-config-inner">
        <?php if (!empty($nodeAtakUrl)): ?>
        <div class="atak-game-config-block">
          <p class="atak-game-config-label">Adresse du serveur de liaison</p>
          <p class="atak-game-config-hint">À saisir dans le mod : Paramètres → Addons → COMSPEC Overwatch</p>
          <div class="atak-game-config-url-wrap">
            <pre class="atak-game-config-url" id="atak-node-url-copy"><?= htmlspecialchars($nodeAtakUrl) ?></pre>
            <button type="button" class="atak-game-config-copy" id="atak-copy-node-url" title="Copier l’adresse">Copier</button>
          </div>
        </div>
        <?php else: ?>
          <p class="atak-game-config-warn" id="atak-no-node-url">Aucune adresse de serveur de liaison configurée. Complétez la <a href="<?= url('admin/atak-config') ?>">configuration ATAK</a> dans l’administration pour activer la liaison.</p>
        <?php endif; ?>
        <div class="atak-game-config-block">
          <p class="atak-game-config-label">Votre adresse réseau (visiteur)</p>
          <p class="atak-game-config-value" id="atak-visitor-ip"><?= htmlspecialchars($visitorIp) ?: '—' ?></p>
        </div>
        <?php if (!empty($atakModDownloadUrl)): ?>
        <div class="atak-game-config-block">
          <p class="atak-game-config-label">Mod dédié</p>
          <p><a href="<?= htmlspecialchars($atakModDownloadUrl) ?>" class="atak-game-config-download" download>Télécharger le mod COMSPEC Overwatch</a></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($atakConfig['arma_server_host'])): ?>
        <div class="atak-game-config-block">
          <p class="atak-game-config-label">Serveur Arma</p>
          <p class="atak-game-config-value"><?= htmlspecialchars($atakConfig['arma_server_host']) ?><?= !empty($atakConfig['arma_server_port']) ? ':' . (int)$atakConfig['arma_server_port'] : '' ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($atakConfig['arma_mod_credentials'])): ?>
        <div class="atak-game-config-block">
          <p class="atak-game-config-label">Identifiants du mod</p>
          <pre class="atak-game-config-pre"><?= htmlspecialchars($atakConfig['arma_mod_credentials']) ?></pre>
        </div>
        <?php endif; ?>
        <?php if (!empty($atakConfig['instructions'])): ?>
        <div class="atak-game-config-block">
          <p class="atak-game-config-label">Instructions</p>
          <p class="atak-game-config-value atak-game-config-instructions"><?= nl2br(htmlspecialchars($atakConfig['instructions'])) ?></p>
        </div>
        <?php endif; ?>
        <div class="atak-game-config-footer">
          <a href="<?= url('atak/setup') ?>" class="atak-game-config-link">Assistant Mod Arma (installation, config, vérification)</a>
          <a href="<?= url('atak/tuto') ?>" class="atak-game-config-link">Guide complet — Tuto mod Arma</a>
        </div>
      </div>
    </div>
  </section>

  <section class="atak-health-section" id="atak-health-section" aria-labelledby="atak-health-title">
    <button type="button" id="atak-health-toggle" class="atak-game-config-toggle" aria-expanded="false" aria-controls="atak-health-body">
      <span class="atak-game-config-toggle-icon" aria-hidden="true">▼</span>
      <span class="atak-accordion-title-wrap">
        <span id="atak-health-title">État de santé</span>
        <span class="atak-accordion-subtitle">Liaison, données, contacts en jeu</span>
      </span>
      <span class="atak-pill atak-pill--muted" id="atak-health-summary-pill">Non vérifié</span>
    </button>
    <div id="atak-health-body" class="atak-game-config-body atak-health-body" hidden>
      <div class="atak-game-config-inner">
        <div class="atak-health-grid">
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
        <div class="atak-game-config-footer">
          <button type="button" class="atak-health-refresh" id="atak-health-refresh">Actualiser</button>
        </div>
      </div>
    </div>
  </section>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="<?= $base ?>/assets/js/atak-map-crs.js"></script>
  <?php if (!$atakMapConfigForJs): ?><script src="<?= $base ?>/assets/js/maps/altis.js"></script><?php endif; ?>
  <script src="<?= $base ?>/assets/js/nato-sidc-icons.js"></script>
  <script src="<?= $base ?>/assets/js/atak-unit-popup.js"></script>
  <script src="<?= $base ?>/assets/js/atak-map.js"></script>
  <script src="<?= $base ?>/assets/js/atak-socket.js"></script>
  <script src="<?= $base ?>/assets/js/atak-units.js"></script>
  <script src="<?= $base ?>/assets/js/atak-chat.js"></script>
  <script src="<?= $base ?>/assets/js/atak-medical-alerts.js"></script>
  <script src="<?= $base ?>/assets/js/atak-pings.js"></script>
  <script src="<?= $base ?>/assets/js/atak-markers.js"></script>
  <script src="<?= $base ?>/assets/js/atak-map-shapes.js"></script>
  <script src="<?= $base ?>/assets/js/atak-context-menu.js"></script>
  <script src="<?= $base ?>/assets/js/atak-jtac.js"></script>
  <script src="<?= $base ?>/assets/js/atak-cams.js"></script>
  <script src="<?= $base ?>/assets/js/atak-air-assets.js"></script>
  <script src="<?= $base ?>/assets/js/atak-laser-codes.js"></script>
  <script src="<?= $base ?>/assets/js/atak-activity.js"></script>
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
      window.ATAKShowNotification = function (msg) {
        var el = document.getElementById('atak-notification-toast');
        if (!el) return;
        el.textContent = msg || '';
        el.classList.add('show');
        clearTimeout(el._toastTimer);
        el._toastTimer = setTimeout(function () { el.classList.remove('show'); }, 4000);
      };

      var bootOverlay = document.getElementById('atak-boot-overlay');
      var bootDismissed = false;
      function dismissAtakBoot() {
        if (bootDismissed) return;
        bootDismissed = true;
        if (bootOverlay) {
          bootOverlay.classList.add('atak-boot-overlay--hidden');
          bootOverlay.setAttribute('aria-busy', 'false');
          bootOverlay.setAttribute('aria-hidden', 'true');
        }
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
          atakLiveConnectedOnce = true;
          dismissAtakBoot();
          setNetworkChip(true);
          if (connectionLostEl) connectionLostEl.classList.remove('show');
        },
        onConnectionLost: function () {
          if (connectionLostEl) connectionLostEl.classList.add('show');
          setNetworkChip(false);
          if (atakLiveConnectedOnce) {
            window.ATAKShowError('La liaison en direct avec la Tacmap est interrompue. Les données se rafraîchissent de façon moins fréquente.');
          }
        }
      });
      setTimeout(function () { dismissAtakBoot(); }, 6000);

      function atakPoll() {
        if (window.ATAKUnits) ATAKUnits.fetchUnits();
        if (window.ATAKChat) ATAKChat.fetchMessages();
        if (window.ATAKMedicalAlerts) ATAKMedicalAlerts.fetchAlerts();
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
      var lastLiaisonChipAt = 0;
      function refreshLiaisonChipQuiet() {
        var now = Date.now();
        if (now - lastLiaisonChipAt < 12000) return;
        lastLiaisonChipAt = now;
        var mid = (window.ATAKSocket && window.ATAKSocket.getMapId) ? window.ATAKSocket.getMapId() : 1;
        fetch('<?= url("api/atak/stats") ?>?mapId=' + mid, { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
          var ago = d.lastArmaActivityAgo != null ? Number(d.lastArmaActivityAgo) : null;
          var agoLabel = formatAgoFr(ago);
          var liaisonChipVal = document.getElementById('atak-chip-liaison-value');
          var chip = document.getElementById('atak-chip-liaison');
          if (liaisonChipVal) liaisonChipVal.textContent = agoLabel || 'En attente';
          if (chip) {
            chip.classList.toggle('atak-chip--ok', ago != null && ago <= 60);
            chip.classList.toggle('atak-chip--warn', ago != null && ago > 60);
          }
        }).catch(function () {});
      }
      atakPoll();
      setInterval(atakPoll, 3000);
      if (window.ATAKActivity && typeof window.ATAKActivity.start === 'function') {
        ATAKActivity.start();
      }

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

      var configToggle = document.getElementById('atak-game-config-toggle');
      var configBody = document.getElementById('atak-game-config-body');
      var configTitle = configToggle ? configToggle.querySelector('#atak-game-config-title') : null;
      var toggleIcon = configToggle ? configToggle.querySelector('.atak-game-config-toggle-icon') : null;
      if (configToggle && configBody) {
        configToggle.addEventListener('click', function () {
          var open = !configBody.hidden;
          configBody.hidden = open;
          configToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
          if (toggleIcon) toggleIcon.textContent = open ? '▼' : '▲';
        });
      }
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

      var accountBtn = document.getElementById('atak-btn-account');
      var gameLinkHeaderBtn = document.getElementById('atak-btn-game-link');
      var accountPanel = document.getElementById('atak-account-panel');
      var accountOverlay = document.getElementById('atak-account-overlay');
      var accountClose = document.getElementById('atak-account-panel-close');
      var gameLinkSection = document.getElementById('atak-game-link-section');
      function openAccountPanel(opts) {
        if (accountPanel) accountPanel.classList.add('open');
        if (accountOverlay) accountOverlay.classList.add('show');
        if (opts && opts.focusGameLink && gameLinkSection) {
          gameLinkSection.classList.add('atak-account-section--pulse');
          setTimeout(function () {
            gameLinkSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }, 50);
          setTimeout(function () {
            gameLinkSection.classList.remove('atak-account-section--pulse');
          }, 1800);
          var genBtn = document.getElementById('atak-game-link-btn');
          if (genBtn) setTimeout(function () { genBtn.focus(); }, 200);
        }
      }
      function closeAccountPanel() {
        if (accountPanel) accountPanel.classList.remove('open');
        if (accountOverlay) accountOverlay.classList.remove('show');
      }
      if (accountBtn) accountBtn.addEventListener('click', function () { openAccountPanel(); });
      if (gameLinkHeaderBtn) {
        gameLinkHeaderBtn.addEventListener('click', function () {
          openAccountPanel({ focusGameLink: true });
        });
      }
      if (accountClose) accountClose.addEventListener('click', closeAccountPanel);
      if (accountOverlay) accountOverlay.addEventListener('click', closeAccountPanel);

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
      var healthToggle = document.getElementById('atak-health-toggle');
      var healthBody = document.getElementById('atak-health-body');
      var healthRefresh = document.getElementById('atak-health-refresh');
      var healthRefreshInterval = null;
      if (healthToggle && healthBody) {
        healthToggle.addEventListener('click', function () {
          var open = healthBody.hidden;
          healthBody.hidden = !open;
          healthToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          var icon = healthToggle.querySelector('.atak-game-config-toggle-icon');
          if (icon) icon.textContent = open ? '▲' : '▼';
          if (open) {
            refreshHealth();
            healthRefreshInterval = setInterval(refreshHealth, 15000);
          } else {
            if (healthRefreshInterval) clearInterval(healthRefreshInterval);
            healthRefreshInterval = null;
          }
        });
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
                metaEl.textContent = res.body.hint || 'Dans Arma : touche K → Connecter mon compte Athena, puis saisissez ce code.';
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

      fetch('<?= url("api/atak/ping") ?>', { credentials: 'include', signal: bootAbort.signal })
        .then(function (r) {
          clearTimeout(bootTimer);
          if (!r.ok) throw new Error('ping');
          return r.json();
        })
        .then(function (data) {
          if (!data || !data.ok) throw new Error('ping');
          startAtakApplication();
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
