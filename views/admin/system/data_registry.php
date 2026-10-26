<?php
declare(strict_types=1);

/**
 * Administration plateforme — Registre des données (/admin/system/donnees).
 * Tableaux façon tableur (communautés, comptes, joueurs Steam, ATAK, sessions de jeu, traces orphelines),
 * corrélations d'une ligne et purge. Rendu par public/assets/js/admin-data-registry.js.
 *
 * @var array<string, string> $registryDatasets
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$datasets = is_array($registryDatasets ?? null) ? $registryDatasets : [];
?>
<section class="ath-page-head pa-head" aria-labelledby="adr-title">
  <div class="ath-page-head__copy">
    <p class="ath-page-head__kicker">EXPLOITATION · DONNÉES</p>
    <h1 class="ath-page-head__title" id="adr-title">Registre des données</h1>
    <p class="ath-page-head__sub">Communautés, comptes, joueurs Steam, ATAK, unités de la carte et sessions de jeu en tableaux : corrélations, export Excel et purge des joueurs fantômes.</p>
  </div>
  <div class="ath-page-head__actions"><a class="ath-btn" href="<?= $h(url('admin/system/cron')) ?>">Entretien automatique</a></div>
</section>
<div class="adr" id="adr"
     data-api="<?= $h(url('admin/system/donnees/api')) ?>"
     data-action="<?= $h(url('admin/system/donnees/action')) ?>"
     data-corr="<?= $h(url('admin/system/donnees/correlations')) ?>"
     data-csrf="<?= $h(\App\Core\Csrf::token()) ?>"
     data-initial="users">

  <nav class="adr-tabs" role="tablist" aria-label="Jeux de données">
    <?php foreach ($datasets as $key => $label): ?>
    <button type="button" role="tab" class="adr-tabs__tab" data-dataset="<?= $h($key) ?>" aria-selected="false"><?= $h($label) ?></button>
    <?php endforeach; ?>
  </nav>

  <p class="adr-lead" id="adr-lead"></p>
  <div class="adr-flags" id="adr-flags" role="group" aria-label="Filtres rapides"></div>

  <section class="ath-table-panel adr-panel">
    <div class="ath-table-toolbar">
      <label class="ath-table-toolbar__search adr-search">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" id="adr-q" placeholder="Rechercher partout (nom, e-mail, Steam, ATAK, communauté…)" aria-label="Rechercher">
      </label>
      <span class="ath-table-toolbar__count" id="adr-count"></span>
      <span class="adr-selcount" id="adr-selcount"></span>
      <span class="ath-table-toolbar__spacer"></span>
      <div class="adr-bulk" id="adr-bulk"></div>
      <div class="adr-cols">
        <button type="button" class="ath-table-toolbar__filter" id="adr-cols" aria-haspopup="true">Colonnes</button>
        <div class="adr-cols__menu" id="adr-cols-menu" hidden></div>
      </div>
      <a class="ath-table-toolbar__export" id="adr-export" href="#">Exporter (Excel)</a>
    </div>
    <div id="adr-grid"><p class="adr-empty">Chargement…</p></div>
    <div class="ath-table-foot adr-foot"><span class="adr-mute" id="adr-status"></span><span class="adr-pager" id="adr-pager"></span></div>
  </section>

  <div class="adr-toast" id="adr-toast" role="status" hidden></div>

  <div class="adr-drawer" id="adr-drawer" hidden>
    <div class="adr-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="adr-drawer-title">
      <header class="adr-drawer__head">
        <div><p class="adr-drawer__kicker" id="adr-drawer-kicker"></p><h2 class="adr-drawer__title" id="adr-drawer-title">Corrélations</h2></div>
        <button type="button" class="ath-btn" data-close>Fermer</button>
      </header>
      <div class="adr-drawer__body" id="adr-drawer-body"></div>
    </div>
  </div>
</div>
<script src="<?= $h(asset_url('assets/js/admin-data-registry.js')) ?>" defer></script>
