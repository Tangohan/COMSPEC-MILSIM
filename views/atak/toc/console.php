<?php
/**
 * Poste TOC web (/atak/toc) : console plein écran, même présentation que le PC du poste en jeu
 * (docs/TOC-THEME.md). Les données arrivent par toc-console.js.
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = static fn (string $p): string => function_exists('asset_url') ? asset_url($p) : url($p);
$tocKeys = [
    ['?', 'Cette aide'], ['Alt + 1 à 4', 'Rubriques : Situation, Conduite, Plans, Salle'], ['← →', 'Onglet précédent ou suivant (sur la barre d’onglets)'],
    ['/', 'Chercher une grille, un groupe, un indicatif ou un marqueur'], ['Entrée', 'Aller au premier résultat de la recherche'],
    ['F', 'Écrire un FLASH'], ['L', 'Panneau des calques'], ['B', 'Fond suivant (satellite, carte du jeu, sombre)'], ['G', 'Quadrillage'],
    ['M', 'Mesurer (clics successifs, Échap pour finir)'], ['T', 'Voir toutes les pistes'], ['P', 'Plein écran'],
    ['+ −', 'Rapprocher, éloigner'], ['Clic droit, Maj + F10', 'Menu de la carte (appui long sur un écran tactile)'],
    ['Ctrl + Entrée', 'Inscrire à la main courante'], ['Échap', 'Fermer le menu, la fenêtre ou le panneau'],
    ['Molette, glisser', 'Rapprocher, déplacer la carte'],
];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<script src="<?= $h($a('assets/js/toc/toc-console-ui.js')) ?>" defer></script>
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
      <div class="toc-link" id="toc-link"><i class="toc-dot" id="toc-link-dot" aria-hidden="true"></i><span id="toc-link-txt">Liaison du jeu…</span><span id="toc-link-age" class="toc-link-age"></span></div>
    </div>
    <div class="toc-actions">
      <label class="toc-sr" for="toc-terrain">Terrain</label>
      <select id="toc-terrain" class="toc-select" title="Terrain"></select>
      <label class="toc-sr" for="toc-side">Camp</label>
      <select id="toc-side" class="toc-select" title="Camp du poste">
        <option value="WEST">BLUFOR</option><option value="EAST">OPFOR</option><option value="GUER">INDFOR</option><option value="CIV">CIVILS</option>
      </select>
      <div class="toc-flashbox">
        <label class="toc-sr" for="toc-flash-input">Texte du FLASH</label>
        <input id="toc-flash-input" class="toc-input toc-flash-input" maxlength="160" placeholder="Texte du FLASH…" autocomplete="off">
        <button type="button" class="toc-btn is-primary" id="toc-flash-btn">FLASH</button>
      </div>
      <button type="button" class="toc-btn is-icon" id="toc-help-btn" aria-label="Aide et raccourcis clavier" title="Aide et raccourcis clavier (?)">?</button>
      <a class="toc-btn" href="<?= $h(url('atak')) ?>" title="Quitter le poste : retour à ATAK web">Quitter</a>
    </div>
  </header>
  <div class="toc-flash" id="toc-flash" hidden role="alert"><span id="toc-flash-text"></span><button type="button" class="toc-btn is-danger is-small" id="toc-flash-clear">Lever le FLASH</button></div>
  <nav class="toc-nav" aria-label="Rubriques du poste">
    <div class="toc-nav-groups" id="toc-groups" role="tablist" aria-label="Rubriques"></div>
    <div class="toc-nav-tabs" id="toc-tabs" role="tablist" aria-label="Onglets de la rubrique"></div>
  </nav>
  <main class="toc-main">
    <section class="toc-left" aria-label="Carte commune">
      <div class="toc-toolbar">
        <button type="button" class="toc-btn" id="toc-layers-btn" title="Calques du jeu et du web (L)" aria-expanded="false"><span>Calques</span><b class="toc-count" id="toc-layers-count">0</b></button>
        <label class="toc-sr" for="toc-bg">Fond de carte</label>
        <select id="toc-bg" class="toc-select" title="Fond de carte (B)"></select>
        <div class="toc-search" role="search">
          <label class="toc-sr" for="toc-search">Chercher une grille, un groupe, un indicatif ou un marqueur</label>
          <input id="toc-search" class="toc-input" placeholder="Grille, groupe, indicatif ou marqueur…" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="toc-suggest" aria-autocomplete="list">
          <kbd class="toc-search-key" aria-hidden="true">/</kbd>
          <ul class="toc-suggest" id="toc-suggest" role="listbox" hidden></ul>
        </div>
      </div>
      <div class="toc-mapwrap" id="toc-mapwrap">
        <div class="toc-map" id="toc-map" aria-label="Carte du terrain" tabindex="0"></div>
        <div class="toc-layers" id="toc-layers" hidden aria-label="Calques"></div>
        <div class="toc-mapctl" role="group" aria-label="Commandes de la carte">
          <button type="button" class="toc-mapbtn" data-map="north" title="Nord en haut : voir toutes les pistes (T)" aria-label="Voir toutes les pistes"><svg width="18" height="20" viewBox="0 0 18 20" aria-hidden="true"><path d="M9 1 L16 18 L9 13 L2 18 Z" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>
          <button type="button" class="toc-mapbtn" data-map="in" title="Rapprocher (+)" aria-label="Rapprocher">+</button>
          <button type="button" class="toc-mapbtn" data-map="out" title="Éloigner (−)" aria-label="Éloigner">−</button>
          <button type="button" class="toc-mapbtn" data-map="layers" title="Calques (L)" aria-label="Calques"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l9 5-9 5-9-5zM3 13l9 5 9-5M3 17.500l9 5 9-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></button>
          <button type="button" class="toc-mapbtn" data-map="measure" title="Mesurer (M)" aria-label="Mesurer" aria-pressed="false"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17L17 3l4 4L7 21zM7 13l2 2M10 10l2 2M13 7l2 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></button>
          <button type="button" class="toc-mapbtn" data-map="full" title="Plein écran (P)" aria-label="Plein écran"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>
        </div>
        <div class="toc-legend" id="toc-legend" aria-label="Légende de la carte">
          <span class="toc-chip"><i style="background:var(--toc-friend)"></i><span>Ami</span></span>
          <span class="toc-chip"><i style="background:var(--toc-hostile)"></i><span>Hostile</span></span>
          <span class="toc-chip"><i style="background:var(--toc-neutral)"></i><span>Neutre</span></span>
          <span class="toc-chip"><i style="background:var(--toc-unknown)"></i><span>Inconnu</span></span>
          <span class="toc-chip is-state"><i class="is-live"></i><span>En direct</span></span>
          <span class="toc-chip is-state"><i class="is-old"></i><span>Ancien</span></span>
          <span class="toc-chip is-state"><i class="is-lost"></i><span>Perdu</span></span>
        </div>
        <div class="toc-mapstate" id="toc-mapstate" hidden role="status"></div>
        <div class="toc-measure" id="toc-measure" hidden role="status"></div>
        <div class="toc-isr-hud" id="toc-isr" hidden aria-hidden="true">
          <span class="toc-isr-tag tl" id="isr-tl"></span><span class="toc-isr-tag tr" id="isr-tr"></span>
          <span class="toc-isr-tag bl" id="isr-bl"></span><span class="toc-isr-tag br">VUE CARTE · PAS LA CAMÉRA DU JEU</span>
          <span class="toc-isr-cross"></span>
        </div>
      </div>
      <div class="toc-help"><span class="toc-cursor"><span>Grille</span> <b id="toc-cursor">---- ----</b></span><span id="toc-declutter"></span><span id="toc-bg-note" class="toc-bg-note"></span></div>
    </section>
    <aside class="toc-panel" aria-label="Panneau de l’onglet">
      <div class="toc-banner" id="toc-banner" hidden></div>
      <div class="toc-pick" id="toc-pick" hidden></div>
      <div class="toc-panel-body" id="toc-panel" role="tabpanel"></div>
    </aside>
  </main>
  <footer class="toc-status"><div class="toc-status-pills" id="toc-status-net"></div><div class="toc-status-pills" id="toc-status-room"></div><span class="toc-status-msg" id="toc-status-msg" role="status" aria-live="polite"></span></footer>
</div>
<div class="toc-ctx" id="toc-ctx" hidden role="menu" aria-label="Menu de la carte"></div>
<div class="toc-drawer-veil" id="toc-drawer-veil" hidden>
  <div class="toc-drawer" id="toc-drawer" role="dialog" aria-modal="true" aria-labelledby="toc-drawer-title" tabindex="-1"></div>
</div>
<?php require __DIR__ . '/_help.php'; ?>
</body>
</html>
