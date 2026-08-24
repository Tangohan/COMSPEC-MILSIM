<?php
$base = url('');
$assetVer = platform_app_version();
$atakToken = $atakToken ?? '';
$atakWorkspaces = $atakWorkspaces ?? [['mapId' => 1, 'label' => 'Principal']];
$atakDefaultMapId = (int) ($atakDefaultMapId ?? 1);
$demoSeedAllowed = !empty($demoSeedAllowed);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Journal de liaison — ATAK</title>
  <link href="<?= $base ?>/assets/css/atak.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <script>
    window.ATAK_TOKEN = <?= json_encode($atakToken) ?>;
    window.ATAK_API_BASE = <?= json_encode($base) ?>;
    window.ATAK_DEFAULT_MAP_ID = <?= (int) $atakDefaultMapId ?>;
    window.ATAK_WORKSPACES = <?= json_encode($atakWorkspaces) ?>;
  </script>
</head>
<body class="atak-page atak-liaison-page">
  <header class="atak-liaison-hero">
    <div class="atak-liaison-hero-inner">
      <div class="atak-liaison-hero-brand">
        <a href="<?= $base ?>/atak" class="atak-liaison-back">← Retour à la carte</a>
        <h1 class="atak-liaison-title">Journal de liaison</h1>
        <p class="atak-liaison-lead">Historique des connexions, incidents, données reçues, échanges et actions tactiques remontés depuis le théâtre.</p>
      </div>
      <div class="atak-liaison-hero-actions">
        <?php if ($demoSeedAllowed): ?>
          <button type="button" class="atak-liaison-btn atak-liaison-btn--ghost" id="atak-liaison-demo" title="Réservé au mode développement">Charger un exemple</button>
        <?php endif; ?>
        <button type="button" class="atak-liaison-btn atak-liaison-btn--danger" id="atak-liaison-clear">Vider le journal</button>
      </div>
    </div>
  </header>

  <main class="atak-liaison-main">
    <section class="atak-liaison-filters" aria-label="Filtres du journal">
      <label class="atak-liaison-field">
        <span>Carte</span>
        <select id="atak-liaison-map">
          <?php foreach ($atakWorkspaces as $ws): ?>
            <option value="<?= (int) ($ws['mapId'] ?? 0) ?>"<?= ((int) ($ws['mapId'] ?? 0) === $atakDefaultMapId) ? ' selected' : '' ?>>
              <?= htmlspecialchars((string) ($ws['label'] ?? 'Carte'), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="atak-liaison-field atak-liaison-field--grow">
        <span>Rechercher</span>
        <input type="search" id="atak-liaison-q" placeholder="Indicatif, message, libellé…" autocomplete="off" />
      </label>
      <label class="atak-liaison-field">
        <span>Type</span>
        <select id="atak-liaison-type">
          <option value="">Tous les types</option>
          <option value="connexion">Connexion / accès</option>
          <option value="indicatif">Indicatif</option>
          <option value="position">Position</option>
          <option value="tchat">Tchat</option>
          <option value="ping">Ping</option>
          <option value="tactique">Tactique</option>
          <option value="incidents">Incidents</option>
          <option value="donnees">Remontées de données</option>
        </select>
      </label>
      <label class="atak-liaison-field">
        <span>Du</span>
        <input type="date" id="atak-liaison-from" />
      </label>
      <label class="atak-liaison-field">
        <span>Au</span>
        <input type="date" id="atak-liaison-to" />
      </label>
      <label class="atak-liaison-check">
        <input type="checkbox" id="atak-liaison-archived" />
        <span>Voir l’historique archivé</span>
      </label>
      <button type="button" class="atak-liaison-btn" id="atak-liaison-apply">Appliquer</button>
    </section>

    <p class="atak-liaison-meta-line" id="atak-liaison-meta">Chargement…</p>
    <p class="atak-liaison-status" id="atak-liaison-status" aria-live="polite"></p>

    <ul class="atak-activity-list atak-liaison-list" id="atak-liaison-list" aria-live="polite"></ul>
    <div class="atak-empty-state atak-activity-empty" id="atak-liaison-empty" hidden>
      <div class="atak-empty-state-icon" aria-hidden="true">⇄</div>
      <p class="atak-empty-state-title">Aucune entrée</p>
      <p class="atak-empty-state-text">Modifiez les filtres, ou attendez qu’un joueur se connecte au théâtre.</p>
    </div>
  </main>

  <script src="<?= $base ?>/assets/js/atak-activity.js?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= $base ?>/assets/js/atak-liaison-page.js?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
