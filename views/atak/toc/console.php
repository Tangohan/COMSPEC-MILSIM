<?php
/**
 * Poste TOC web (/atak/toc) : console plein écran, même présentation que le PC du poste en jeu
 * (docs/TOC-THEME.md). Les données arrivent par toc-console.js.
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = static fn (string $p): string => function_exists('asset_url') ? asset_url($p) : url($p);
$tocKeys = [
    ['?', 'Cette aide'], ['Alt + 1 à 9', 'Onglets de la première rangée'], ['← →', 'Onglet précédent ou suivant (sur la rangée d’onglets)'],
    ['F', 'Écrire un FLASH'], ['L', 'Panneau des calques'], ['B', 'Fond suivant (satellite, carte du jeu, sombre)'], ['G', 'Quadrillage'],
    ['/', 'Chercher une grille, un groupe ou un indicatif'], ['Ctrl + Entrée', 'Inscrire à la main courante'], ['Échap', 'Fermer'],
    ['Molette, glisser', 'Rapprocher, déplacer la carte'],
];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<script src="<?= $h($a('assets/js/toc/toc-console.js')) ?>" defer></script>
</head>
<body class="toc-body">
<div class="toc" id="toc-root">
  <header class="toc-top">
    <div class="toc-post">
      <h1 id="toc-title">Poste TOC</h1>
      <p id="toc-sub">Chargement…</p>
    </div>
    <div class="toc-clocks" aria-live="off">
      <div class="toc-clock-row">
        <span><span id="toc-zulu">--:--:--</span><small>ZULU</small></span>
        <span><span id="toc-local">--:--:--</span><small>LOCAL</small></span>
      </div>
      <div class="toc-link"><span><i class="toc-dot" id="toc-link-dot" aria-hidden="true"></i><span id="toc-link-txt">Liaison du jeu…</span></span></div>
    </div>
    <div class="toc-actions">
      <label class="toc-sr" for="toc-terrain">Terrain</label>
      <select id="toc-terrain" class="toc-select" title="Terrain"></select>
      <label class="toc-sr" for="toc-side">Camp</label>
      <select id="toc-side" class="toc-select" title="Camp du poste">
        <option value="WEST">BLUFOR</option><option value="EAST">OPFOR</option><option value="GUER">INDFOR</option><option value="CIV">CIVILS</option>
      </select>
      <label class="toc-sr" for="toc-flash-input">Texte du FLASH</label>
      <input id="toc-flash-input" class="toc-input toc-flash-input" maxlength="160" placeholder="Texte du FLASH…" autocomplete="off">
      <button type="button" class="toc-btn is-primary" id="toc-flash-btn">FLASH</button>
      <a class="toc-btn" href="<?= $h(url('atak/toc/plans')) ?>">Plans</a>
      <button type="button" class="toc-btn" id="toc-help-btn" aria-label="Aide et raccourcis clavier">?</button>
      <a class="toc-btn" href="<?= $h(url('atak')) ?>">Fermer</a>
    </div>
  </header>
  <div class="toc-flash" id="toc-flash" hidden role="alert"><span id="toc-flash-text"></span><button type="button" class="toc-btn is-danger is-small" id="toc-flash-clear">Lever le FLASH</button></div>
  <nav class="toc-tabs" id="toc-tabs" role="tablist" aria-label="Onglets du poste"></nav>
  <main class="toc-main">
    <section class="toc-left" aria-label="Carte commune">
      <div class="toc-toolbar">
        <button type="button" class="toc-btn" id="toc-layers-btn" title="Calques du jeu et du web (L)">Calques</button>
        <label class="toc-sr" for="toc-bg">Fond de carte</label>
        <select id="toc-bg" class="toc-select" title="Fond de carte (B)"></select>
        <div class="toc-search">
          <label class="toc-sr" for="toc-search">Grille, groupe ou indicatif</label>
          <input id="toc-search" class="toc-input" placeholder="Grille, groupe ou indicatif…" autocomplete="off">
          <button type="button" class="toc-btn" id="toc-search-btn">Chercher</button>
        </div>
      </div>
      <div class="toc-mapwrap">
        <div class="toc-map" id="toc-map" aria-label="Carte du terrain"></div>
        <div class="toc-layers" id="toc-layers" hidden aria-label="Calques"></div>
        <div class="toc-isr-hud" id="toc-isr" hidden aria-hidden="true">
          <span class="toc-isr-tag tl" id="isr-tl"></span><span class="toc-isr-tag tr" id="isr-tr"></span>
          <span class="toc-isr-tag bl" id="isr-bl"></span><span class="toc-isr-tag br">VUE CARTE · PAS LA CAMÉRA DU JEU</span>
          <span class="toc-isr-cross"></span>
        </div>
      </div>
      <div class="toc-help"><span>Grille <b id="toc-cursor">----</b></span><span id="toc-bg-note"></span></div>
    </section>
    <aside class="toc-panel" aria-label="Panneau de l’onglet">
      <div class="toc-banner" id="toc-banner" hidden></div>
      <div class="toc-panel-body" id="toc-panel" role="tabpanel"></div>
    </aside>
  </main>
  <footer class="toc-status"><span id="toc-status-net"><b>Réseau</b> …</span><span id="toc-status-room"></span><span class="toc-status-msg" id="toc-status-msg" role="status" aria-live="polite"></span></footer>
</div>
<?php require __DIR__ . '/_help.php'; ?>
</body>
</html>
