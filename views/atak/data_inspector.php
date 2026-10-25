<?php
/**
 * Data Inspector — back-office ATAK. En-tête de page rendu par la coque (config/back_office_pages.php).
 * Contenu chargé par public/assets/js/athena-data-inspector.js depuis /api/athena/inspector/metrics.
 */
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
<div class="bo-di" id="athena-data-inspector"
     data-api="<?= $h(url('api/athena/inspector/metrics')) ?>"
     data-platform="<?= $canPlatform ? '1' : '0' ?>"
     data-remontees="<?= $h(url('back-office/remontees/joueurs')) ?>">

  <div class="bo-di__toolbar">
    <nav class="bo-di-tabs" role="tablist" aria-label="Vues du Data Inspector">
      <?php foreach ($tabs as $k => $l): ?>
      <button type="button" role="tab" data-tab="<?= $h($k) ?>" class="bo-di-tabs__tab<?= $k === 'overview' ? ' is-active' : '' ?>" aria-selected="<?= $k === 'overview' ? 'true' : 'false' ?>"><?= $h($l) ?></button>
      <?php endforeach; ?>
    </nav>
    <div class="bo-di__controls">
      <?php if ($canPlatform): ?>
      <div class="bo-di-seg" role="group" aria-label="Portée" data-control="scope">
        <button type="button" data-value="tenant" class="bo-di-seg__opt is-on">Ma communauté</button>
        <button type="button" data-value="platform" class="bo-di-seg__opt">Plateforme</button>
      </div>
      <?php endif; ?>
      <div class="bo-di-seg" role="group" aria-label="Période" data-control="hours">
        <?php foreach ([1 => '1 h', 24 => '24 h', 168 => '7 j', 720 => '30 j'] as $v => $l): ?>
        <button type="button" data-value="<?= $v ?>" class="bo-di-seg__opt<?= $v === 24 ? ' is-on' : '' ?>"><?= $l ?></button>
        <?php endforeach; ?>
      </div>
      <span class="bo-di-live" id="adi-live" role="status"><i aria-hidden="true"></i><span>Connexion…</span></span>
    </div>
  </div>

  <div class="ath-warn bo-di__notice" id="adi-notice" hidden></div>

  <div class="bo-di__search" id="adi-toolbar" hidden>
    <label class="ath-table-toolbar__search bo-di__search-box">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input type="search" id="adi-q" placeholder="Joueur, indicatif, Steam, mod, terminal…" aria-label="Rechercher">
    </label>
    <a class="ath-table-toolbar__export" id="adi-csv" href="#" download>Exporter le tableau</a>
  </div>

  <div id="adi-view" class="bo-di__view" aria-live="polite"><p class="bo-di-empty">Chargement des métriques…</p></div>

  <div class="bo-di-drawer" id="adi-drawer" hidden>
    <div class="bo-di-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="adi-drawer-title">
      <header class="bo-di-drawer__head">
        <div><p class="bo-di-drawer__kicker" id="adi-drawer-kicker">Détail</p><h2 class="bo-di-drawer__title" id="adi-drawer-title">Détail</h2></div>
        <button type="button" class="ath-btn" data-close aria-label="Fermer">Fermer</button>
      </header>
      <div class="bo-di-drawer__body" id="adi-drawer-body"></div>
    </div>
  </div>
</div>
<script src="<?= $h(asset_url('assets/js/athena-data-inspector.js')) ?>" defer></script>
