<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$canPlatform = !empty($inspectorCanPlatform);
$tabs = [
    'overview' => 'Vue d’ensemble',
    'pipeline' => 'Chaîne de remontée',
    'players' => 'Joueurs',
    'mods' => 'Mods',
    'terminals' => 'Terminaux ATAK',
    'traffic' => 'Débit',
    'errors' => 'Erreurs & disponibilité',
];
if ($canPlatform) {
    $tabs['communities'] = 'Communautés';
}
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/athena-data-inspector.css')) ?>">
<main class="adi" id="athena-data-inspector"
      data-api="<?= $h(url('api/athena/inspector/metrics')) ?>"
      data-platform="<?= $canPlatform ? '1' : '0' ?>"
      data-remontees="<?= $h(url('back-office/remontees')) ?>">
  <header class="adi-head">
    <div class="adi-head-title">
      <span class="adi-kicker">ATHENA · C2 · DONNÉES TERRAIN</span>
      <h1>Data Inspector</h1>
      <p class="adi-sub">Tout ce que les joueurs font remonter du jeu : mods, terminaux, débit, comptes rendus, erreurs et disponibilité.</p>
    </div>
    <div class="adi-controls">
      <?php if ($canPlatform): ?>
      <div class="adi-seg" role="group" aria-label="Portée" data-control="scope">
        <button type="button" data-value="tenant" class="is-on">Ma communauté</button>
        <button type="button" data-value="platform">Plateforme</button>
      </div>
      <?php endif; ?>
      <div class="adi-seg" role="group" aria-label="Période" data-control="hours">
        <?php foreach ([1 => '1 h', 24 => '24 h', 168 => '7 j', 720 => '30 j'] as $v => $l): ?>
        <button type="button" data-value="<?= $v ?>" class="<?= $v === 24 ? 'is-on' : '' ?>"><?= $l ?></button>
        <?php endforeach; ?>
      </div>
      <div class="adi-live" id="adi-live"><i></i><span>Connexion…</span></div>
    </div>
  </header>

  <nav class="adi-tabs" role="tablist" aria-label="Vues">
    <?php foreach ($tabs as $k => $l): ?>
    <button type="button" role="tab" data-tab="<?= $h($k) ?>" class="<?= $k === 'overview' ? 'is-active' : '' ?>"><?= $h($l) ?></button>
    <?php endforeach; ?>
  </nav>

  <div class="adi-notice" id="adi-notice" hidden></div>

  <section class="adi-body">
    <div class="adi-toolbar" id="adi-toolbar" hidden>
      <input type="search" id="adi-q" placeholder="Rechercher un joueur, un indicatif, un mod, un terminal…" aria-label="Recherche">
      <a class="adi-btn" id="adi-csv" href="#" download>Exporter CSV</a>
    </div>
    <div id="adi-view" class="adi-view" aria-live="polite"><div class="adi-loading">Chargement des métriques…</div></div>
  </section>

  <div class="adi-drawer" id="adi-drawer" hidden>
    <div class="adi-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="adi-drawer-title">
      <header><h2 id="adi-drawer-title">Détail</h2><button type="button" class="adi-x" data-close aria-label="Fermer">×</button></header>
      <div id="adi-drawer-body"></div>
    </div>
  </div>
</main>
<script src="<?= $h(asset_url('assets/js/athena-data-inspector.js')) ?>" defer></script>
