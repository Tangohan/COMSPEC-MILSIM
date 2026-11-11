<?php
/**
 * Éditeur de plan d'opération (/atak/toc/plans/{id}) et lecture par lien (/atak/toc/plans/lien/{jeton}).
 * Trois volets : ORBAT, calques et bibliothèque à gauche ; carte 2D ou vue 3D au centre ; propriétés, plan et échanges
 * à droite ; frise du temps en bas. docs/TOC-WEB.md.
 *
 * @var int $tocPlanId
 * @var bool $tocReadOnlyLink
 * @var array<string, mixed>|null $tocSharedDoc
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = static fn (string $p): string => function_exists('asset_url') ? asset_url($p) : url($p);
$tocExtraBoot = [
    'planId' => (int) $tocPlanId,
    'readOnlyLink' => !empty($tocReadOnlyLink),
    'sharedDoc' => $tocSharedDoc ?? null,
    // Module de la vue 3D : chargé seulement quand la vue 3D est ouverte.
    'plan3dUrl' => $a('assets/js/toc/plan/plan-3d.js'),
];
$tocKeys = [
    ['Ctrl + K', 'Palette de commandes : toute action, unité, calque, phase ou grille'], ['?', 'Cette aide'],
    ['V · Q · H', 'Sélection · lasso · main'], ['Espace + glisser', 'Déplacer la carte'], ['U · T · I', 'Unité · texte · repère'], ['L · A', 'Trait · flèche'],
    ['P · R · E · C', 'Polygone · rectangle · ellipse · cercle'], ['M · O · W', 'Mesure · ligne de vue · zone vue'],
    ['Entrée · double-clic', 'Terminer un trait ou une zone'], ['Retour arrière', 'Retirer le dernier point'],
    ['Maj ou Ctrl + clic', 'Ajouter à la sélection ou en retirer'], ['Alt + clic', 'Élément du dessous'], ['Alt + glisser', 'Dupliquer en déplaçant'],
    ['Échap', 'Annuler le dessin, quitter l’outil, désélectionner, fermer'], ['Suppr', 'Supprimer la sélection'], ['Ctrl + Z · Ctrl + Y', 'Annuler · rétablir'],
    ['Ctrl + C · X · V · D', 'Copier · couper · coller sous le curseur · dupliquer'], ['Ctrl + Maj + V', 'Coller à la même place'], ['Ctrl + Alt + C · V', 'Copier · coller le style'],
    ['Ctrl + A', 'Tout choisir'], ['Ctrl + G · Ctrl + Maj + G', 'Grouper · dégrouper'], ['Ctrl + ] · Ctrl + [', 'Avancer · reculer d’un cran'], ['Ctrl + Maj + ] · [', 'Premier plan · arrière-plan'],
    ['[ · ]', 'Pivoter de 15°'], ['+ · −', 'Agrandir · réduire'], ['Flèches', 'Décaler de 10 m (Maj : 100 m, Alt : 1 m)'],
    ['K · Ctrl + H', 'Verrouiller · masquer'], ['S · Maj + S', 'Aimantation aux sommets · à la grille de 10 m'], ['G · B', 'Quadrillage · fond suivant'], ['F · Maj + F', 'Cadrer le plan · la sélection'],
    ['3', 'Vue 2D ou 3D'], ['Barre d’espace (frise)', 'Lecture ou pause quand la frise a la main'], ['Page préc. · Page suiv.', 'Phase précédente · suivante'], ['← → (briefing)', 'Phase précédente · suivante'],
];
$tocHelpTitle = 'Éditeur de plans : raccourcis';
$tool = static function (string $id, string $icon, string $label, string $key = '', bool $edit = true) use ($h): string {
    return '<button type="button" class="pe-tbtn" data-tool="' . $h($id) . '" aria-pressed="false"' . ($edit ? ' data-needs-edit' : '')
        . ' title="' . $h($label . ($key !== '' ? ' (' . $key . ')' : '')) . '" aria-label="' . $h($label) . '"><i data-ic="' . $h($icon) . '"></i></button>';
};
$runBtn = static function (string $id, string $icon, string $label, string $key = '', string $extra = '') use ($h): string {
    return '<button type="button" class="pe-tbtn" data-run="' . $h($id) . '" ' . $extra . ' title="' . $h($label . ($key !== '' ? ' (' . $key . ')' : '')) . '" aria-label="' . $h($label) . '"><i data-ic="' . $h($icon) . '"></i></button>';
};
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<link rel="stylesheet" href="<?= $h($a('assets/css/atak-toc-plan.css')) ?>">
<script src="<?= $h($a('assets/js/toc/plan/plan-logic.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/toc/toc-plan-editor.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/toc/plan/plan-panels.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/toc/plan/plan-timeline.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/toc/plan/plan-terrain.js')) ?>" defer></script>
</head>
<body class="toc-body pe-body">
<div class="pe" id="pe">
  <header class="pe-top">
    <a class="pe-tbtn" href="<?= $h(url('atak/toc/plans')) ?>" title="Retour aux plans" aria-label="Retour aux plans"><i data-ic="caret" style="transform:scaleX(-1)"></i></a>
    <div class="pe-ident">
      <label class="toc-sr" for="plan-title">Titre du plan</label>
      <input id="plan-title" class="plan-title" maxlength="120" value="<?= $h($title ?? '') ?>">
      <p><span id="plan-terrain"></span><span id="plan-save" role="status" data-state="busy">Chargement…</span></p>
    </div>
    <div class="pe-presence" id="plan-presence" role="img" aria-label="Seul sur le plan"></div>
    <div class="pe-topacts">
      <button type="button" class="pe-palbtn" id="pe-palbtn" title="Palette de commandes (Ctrl+K)"><i data-ic="search" data-ic-size="16"></i><span>Commandes</span><kbd>Ctrl K</kbd></button>
      <?= $runBtn('undo', 'undo', 'Annuler', 'Ctrl+Z', 'data-needs-edit') ?>
      <?= $runBtn('redo', 'redo', 'Rétablir', 'Ctrl+Y', 'data-needs-edit') ?>
      <details class="toc-menu" data-needs-edit>
        <summary class="toc-btn"><i data-ic="upload" data-ic-size="16"></i>Importer</summary>
        <div class="toc-menu-list">
          <button type="button" class="toc-btn" data-run="importGame">Plan dessiné en jeu</button>
          <button type="button" class="toc-btn" data-run="importFile">Fichier JSON, GeoJSON ou KML</button>
          <button type="button" class="toc-btn" data-orbat-top="site">ORBAT de la communauté</button>
          <button type="button" class="toc-btn" data-orbat-top="game">Groupes en jeu (ATAK)</button>
        </div>
      </details>
      <details class="toc-menu">
        <summary class="toc-btn"><i data-ic="download" data-ic-size="16"></i>Exporter</summary>
        <div class="toc-menu-list">
          <button type="button" class="toc-btn" data-run="exportPng">Image PNG (vue affichée)</button>
          <button type="button" class="toc-btn" data-run="copyImage">Copier l’image de la vue</button>
          <button type="button" class="toc-btn" data-run="exportPdf" data-needs-api>Fiche de briefing PDF</button>
          <button type="button" class="toc-btn" data-run="export.json" data-needs-api>Fichier JSON (échange)</button>
          <button type="button" class="toc-btn" data-run="export.geojson" data-needs-api>GeoJSON</button>
          <button type="button" class="toc-btn" data-run="export.kml" data-needs-api>KML</button>
        </div>
      </details>
      <button type="button" class="toc-btn" data-run="versions" data-needs-api><i data-ic="history" data-ic-size="16"></i>Versions</button>
      <button type="button" class="toc-btn" data-run="share" data-needs-admin><i data-ic="share" data-ic-size="16"></i>Partage</button>
      <button type="button" class="toc-btn" data-run="briefing" title="Présenter le plan en plein écran, phase par phase"><i data-ic="present" data-ic-size="16"></i>Briefing</button>
      <button type="button" class="toc-btn is-primary" data-run="publish" data-needs-api data-needs-edit title="Poser le plan dans l’ATAK en jeu (plan partagé du camp)"><i data-ic="send" data-ic-size="16"></i>Envoyer en jeu</button>
      <?= $runBtn('help', 'menu', 'Aide et raccourcis clavier', '?') ?>
    </div>
  </header>

  <div class="pe-tools" role="toolbar" aria-label="Outils">
    <div class="pe-tgroup"><?= $tool('select', 'select', 'Sélection', 'V', false) . $tool('lasso', 'lasso', 'Lasso', 'Q', false) . $tool('pan', 'pan', 'Main : déplacer la carte', 'H', false) ?></div>
    <div class="pe-tgroup"><?= $tool('unit', 'unit', 'Unité', 'U') . $tool('text', 'text', 'Texte', 'T') . $tool('line', 'line', 'Trait', 'L') . $tool('arrow', 'arrow', 'Flèche', 'A') . $tool('polygon', 'polygon', 'Polygone', 'P') . $tool('rect', 'rect', 'Rectangle', 'R') . $tool('circle', 'circle', 'Cercle', 'C') . $tool('ellipse', 'ellipse', 'Ellipse', 'E') ?></div>
    <div class="pe-tgroup"><?= $tool('measure', 'measure', 'Mesure : distance, azimut, surface', 'M', false) . $tool('los', 'los', 'Ligne de vue entre deux points', 'O', false) . $tool('profile', 'profile', 'Coupe du relief le long d’un tracé', '', false) . $tool('viewshed', 'viewshed', 'Zone vue depuis un point', 'W', false) ?>
      <button type="button" class="pe-tbtn" data-run="slope" aria-pressed="false" title="Teinte des pentes" aria-label="Teinte des pentes"><i data-ic="layers"></i></button></div>
    <div class="pe-tgroup"><?= $runBtn('snap', 'snap', 'Aimantation aux sommets', 'S', 'aria-pressed="true"') ?>
      <button type="button" class="pe-tbtn is-text" data-run="snapGrid" aria-pressed="false" title="Aimantation à la grille de 10 m (Maj+S)">10 m</button>
      <?= $runBtn('grid', 'grid', 'Quadrillage', 'G', 'aria-pressed="true"') ?></div>
    <details class="toc-menu pe-tgroup" data-needs-edit>
      <summary class="pe-tbtn" title="Aligner et répartir la sélection" aria-label="Aligner et répartir"><i data-ic="align"></i></summary>
      <div class="toc-menu-list" style="right:auto;left:0">
        <button type="button" class="toc-btn" data-run="align.left">Aligner à gauche</button><button type="button" class="toc-btn" data-run="align.hcenter">Centrer horizontalement</button><button type="button" class="toc-btn" data-run="align.right">Aligner à droite</button>
        <button type="button" class="toc-btn" data-run="align.top">Aligner en haut</button><button type="button" class="toc-btn" data-run="align.vcenter">Centrer verticalement</button><button type="button" class="toc-btn" data-run="align.bottom">Aligner en bas</button>
        <button type="button" class="toc-btn" data-run="align.dh">Répartir horizontalement</button><button type="button" class="toc-btn" data-run="align.dv">Répartir verticalement</button>
      </div>
    </details>
    <span class="pe-grow"></span>
    <div class="pe-tgroup pe-3dctl" id="pe-3dctl" hidden>
      <label title="Exagération du relief"><i data-ic="profile" data-ic-size="16"></i><span class="toc-sr">Exagération du relief</span><input type="range" id="pe-3d-exag" min="1" max="4" step="0.1" value="1.5"><output id="pe-3d-exag-out" class="mono">×1,5</output></label>
      <label title="Heure du soleil (ombres du relief)"><i data-ic="sun" data-ic-size="16"></i><span class="toc-sr">Heure du soleil</span><input type="range" id="pe-3d-sun" min="5" max="21" step="0.25" value="10"><output id="pe-3d-sun-out" class="mono">10:00</output></label>
      <button type="button" class="pe-tbtn" id="pe-3d-north" title="Nord en haut (N)" aria-label="Nord en haut"><i data-ic="north"></i></button>
    </div>
    <div class="pe-tgroup"><?= $runBtn('zoomOut', 'zoomout', 'Éloigner') . $runBtn('zoomIn', 'zoomin', 'Rapprocher') . $runBtn('fit', 'fit', 'Cadrer tout le plan', 'F') ?></div>
    <div class="pe-tgroup"><label class="toc-sr" for="plan-bg">Fond de carte</label><select id="plan-bg" class="toc-select" title="Fond de carte (B)"></select></div>
    <div class="pe-seg pe-viewmode" role="group" aria-label="Vue">
      <button type="button" data-view="2d" aria-pressed="true" title="Carte à plat (3)">2D</button><button type="button" data-view="3d" aria-pressed="false" title="Relief en 3D (3)">3D</button>
    </div>
  </div>

  <main class="pe-main">
    <aside class="pe-left" id="pe-left" aria-label="ORBAT, calques et bibliothèque">
      <nav class="pe-tabs" role="tablist" aria-label="Volet de gauche">
        <button type="button" class="toc-tab" role="tab" data-left="orbat" aria-selected="false"><i data-ic="tree" data-ic-size="16"></i>ORBAT</button>
        <button type="button" class="toc-tab" role="tab" data-left="layers" aria-selected="false"><i data-ic="layers" data-ic-size="16"></i>Calques</button>
        <button type="button" class="toc-tab" role="tab" data-left="lib" aria-selected="true"><i data-ic="book" data-ic-size="16"></i>Bibliothèque</button>
      </nav>
      <div class="pe-pane" data-left-body="orbat" hidden>
        <div class="pe-panehead">
          <label class="pe-search"><i data-ic="search" data-ic-size="15"></i><span class="toc-sr">Chercher une unité</span><input type="search" id="pe-orbat-search" class="toc-input" placeholder="Chercher une unité…" autocomplete="off"></label>
          <div class="pe-stack is-row" data-needs-edit>
            <button type="button" class="toc-btn is-small" data-unit-add title="Ajouter une unité à l’ORBAT (sous l’unité choisie)"><i data-ic="plus" data-ic-size="14"></i>Unité</button>
            <button type="button" class="toc-btn is-small" data-orbat="site" title="Reprendre les unités de l’ORBAT de la communauté">ORBAT du site</button>
            <button type="button" class="toc-btn is-small" data-orbat="game" title="Reprendre les groupes ATAK en liaison, à leur position">Depuis le jeu</button>
          </div>
        </div>
        <div class="pe-panebody" id="pe-orbat-body" role="tree" aria-label="ORBAT du plan"></div>
      </div>
      <div class="pe-pane" data-left-body="layers" hidden>
        <div class="pe-panebody">
          <div class="toc-sec"><h2>Calques du plan</h2><span>celui du haut est dessiné devant</span></div>
          <div id="pe-layers-plan"></div>
          <div class="toc-layers pe-gamelayers" id="plan-game-layers" aria-label="Calques du jeu"></div>
        </div>
      </div>
      <div class="pe-pane" data-left-body="lib">
        <div class="pe-panehead">
          <label class="pe-search"><i data-ic="search" data-ic-size="15"></i><span class="toc-sr">Chercher un symbole</span><input type="search" id="pe-lib-search" class="toc-input" placeholder="Chercher un symbole…" autocomplete="off"></label>
          <div class="pe-libopts">
            <span class="pe-seg" role="group" aria-label="Affiliation des unités posées">
              <button type="button" data-lib-affil="F" aria-pressed="true" style="--seg:#6cb8ff">Ami</button><button type="button" data-lib-affil="H" aria-pressed="false" style="--seg:#ff6b6b">Hostile</button><button type="button" data-lib-affil="N" aria-pressed="false" style="--seg:#7ee08a">Neutre</button><button type="button" data-lib-affil="U" aria-pressed="false" style="--seg:#f2d36b">Inconnu</button>
            </span>
            <label class="toc-sr" for="pe-lib-echelon">Échelon des unités posées</label><select id="pe-lib-echelon" class="toc-select" title="Échelon des unités posées"></select>
          </div>
        </div>
        <div class="pe-panebody" id="pe-lib-body"></div>
      </div>
    </aside>

    <section class="pe-center" aria-label="Carte du plan">
      <div class="toc-mapwrap" id="plan-mapwrap">
        <div class="toc-map" id="plan-map" aria-label="Carte du terrain"></div>
        <svg class="pe-overlay" id="plan-overlay" aria-hidden="true"></svg>
        <div class="pe-3d" id="plan-3d" hidden tabindex="0" aria-label="Vue 3D du terrain"></div>
        <button type="button" class="pe-collapse is-left" data-collapse="left" aria-expanded="true" aria-controls="pe-left" title="Replier ou déplier le volet de gauche" aria-label="Volet de gauche"><i data-ic="caret" data-ic-size="14"></i></button>
        <button type="button" class="pe-collapse is-right" data-collapse="right" aria-expanded="true" aria-controls="pe-right" title="Replier ou déplier le volet de droite" aria-label="Volet de droite"><i data-ic="caret" data-ic-size="14"></i></button>
        <div class="pe-float pe-analysis" id="pe-analysis" hidden></div>
        <div class="pe-float pe-3dnote" id="pe-3dnote" hidden></div>
        <div class="toc-banner plan-readonly" id="plan-ro" hidden></div>
        <div class="plan-brief pe-float" id="plan-brief-card" hidden aria-live="polite"></div>
        <div class="pe-toast" id="plan-msg" role="status" aria-live="polite" hidden></div>
        <div class="pe-dropveil" id="pe-dropveil" hidden><p>Déposez un fichier JSON, GeoJSON ou KML pour l’ajouter au plan</p></div>
        <div class="toc-loading" id="plan-loading">Chargement du plan…</div>
      </div>
      <div class="pe-status">
        <span>Grille <b id="plan-cursor">---- ----</b></span><span id="plan-alt" class="mono"></span><span id="plan-bg-note" class="pe-status-dim"></span>
        <b id="plan-measure"></b><span id="plan-hint" class="pe-status-hint"></span>
      </div>
    </section>

    <aside class="pe-right" id="pe-right" aria-label="Propriétés, plan et échanges">
      <nav class="pe-tabs" role="tablist" aria-label="Volet de droite">
        <button type="button" class="toc-tab" role="tab" data-right="props" aria-selected="true">Propriétés</button>
        <button type="button" class="toc-tab" role="tab" data-right="plan" aria-selected="false">Plan</button>
        <button type="button" class="toc-tab" role="tab" data-right="notes" aria-selected="false">Échanges<span class="toc-badge" id="pe-notes-count" hidden></span></button>
      </nav>
      <div class="pe-panebody" id="pe-right-body"></div>
    </aside>
  </main>

  <footer class="pe-time" id="pe-time" aria-label="Frise du temps"></footer>
</div>

<input type="file" id="plan-file" accept=".json,.geojson,.kml,application/json,application/geo+json,application/vnd.google-earth.kml+xml" hidden>
<div class="pe-palette" id="pe-palette" hidden role="dialog" aria-modal="true" aria-label="Palette de commandes">
  <div class="pe-pal">
    <input type="text" id="pe-pal-input" class="toc-input" placeholder="Commande, unité, calque, phase, grille (0425 1169), heure (H+0:30)…" autocomplete="off" spellcheck="false" role="combobox" aria-expanded="true" aria-controls="pe-pal-list" aria-autocomplete="list">
    <ul id="pe-pal-list" role="listbox" aria-label="Résultats"></ul>
  </div>
</div>
<ul class="pe-menu" id="pe-menu" role="menu" hidden></ul>

<div class="toc-overlay" id="dlg-publish" hidden role="dialog" aria-modal="true" aria-labelledby="pub-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="pub-title">Envoyer en jeu</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <p>Le plan est posé dans le plan partagé du camp (app Plan des téléphones, carte du PC du poste et écrans de la salle). Seul le plan part : aucune position d’unité en direct.</p>
    <div class="toc-form"><div class="toc-row" style="--cols:2">
      <label>Camp<select class="toc-select" id="pub-side"><option value="WEST">BLUFOR</option><option value="EAST">OPFOR</option><option value="GUER">INDFOR</option><option value="CIV">CIVILS</option></select></label>
      <label>Contenu<select class="toc-select" id="pub-phase"></select></label>
    </div></div>
    <p class="toc-note" id="pub-info"></p>
    <p class="toc-note">Un nouvel envoi remplace en jeu les éléments déjà envoyés par ce plan. Le jeu relève l’envoi dans les secondes qui suivent s’il est en liaison avec Athena, sinon à sa prochaine liaison (30 min au plus).</p>
    <button type="button" class="toc-btn is-primary is-block" id="pub-go">Envoyer</button>
  </div>
</div>
<div class="toc-overlay" id="dlg-import" hidden role="dialog" aria-modal="true" aria-labelledby="imp-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="imp-title">Importer depuis le jeu</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <p>Le plan du camp dessiné en jeu (app Plan du téléphone, outil PLAN du PC du poste), tel que les téléphones reliés l’ont envoyé à Athena.</p>
    <div class="toc-form"><label>Camp<select class="toc-select" id="imp-side"><option value="WEST">BLUFOR</option><option value="EAST">OPFOR</option><option value="GUER">INDFOR</option><option value="CIV">CIVILS</option></select></label></div>
    <p class="toc-note" id="imp-info"></p>
    <button type="button" class="toc-btn is-primary is-block" id="imp-go" disabled>Ajouter au plan</button>
  </div>
</div>
<div class="toc-overlay" id="dlg-versions" hidden role="dialog" aria-modal="true" aria-labelledby="ver-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="ver-title">Versions</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <div class="toc-form" data-needs-edit><div class="toc-row" style="grid-template-columns:1fr auto"><input class="toc-input" id="ver-label" maxlength="120" placeholder="Nom de la version (avant briefing, version validée…)" aria-label="Nom de la version"><button type="button" class="toc-btn is-primary" id="ver-save">Enregistrer une version</button></div></div>
    <div id="ver-list"></div>
  </div>
</div>
<div class="toc-overlay" id="dlg-share" hidden role="dialog" aria-modal="true" aria-labelledby="sh-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="sh-title">Partage</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <div class="toc-form">
      <label>Qui voit et modifie ce plan<select class="toc-select" id="sh-sharing"><option value="private">Moi seul (et les partages nominatifs)</option><option value="unit">Mon unité</option><option value="community">Toute la communauté (droit « tenir le poste TOC web »)</option></select></label>
      <label><span><input type="checkbox" id="sh-lock"> Verrouiller le plan (vous et la configuration du poste seuls le modifient)</span></label>
      <button type="button" class="toc-btn is-primary" id="sh-save">Enregistrer le partage</button>
      <label>Lien de lecture (membres connectés de la communauté)<input class="toc-input mono" id="sh-link" readonly placeholder="Pas de lien"></label>
      <div class="toc-buttons" style="--cols:3"><button type="button" class="toc-btn" id="sh-create">Créer un lien</button><button type="button" class="toc-btn" id="sh-revoke">Retirer le lien</button><button type="button" class="toc-btn" id="sh-template" hidden>Enregistrer comme modèle</button></div>
    </div>
  </div>
</div>
<div class="toc-overlay" id="dlg-time" hidden role="dialog" aria-modal="true" aria-labelledby="time-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="time-title">Réglages de la frise</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <div class="toc-form">
      <div class="toc-row" style="--cols:2">
        <label>Heure H (ZULU)<input class="toc-input mono" type="datetime-local" id="time-h"></label>
        <label>Durée de l’opération (minutes)<input class="toc-input mono" type="number" id="time-dur" min="10" max="14400" step="10"></label>
      </div>
      <div class="toc-row" style="--cols:2">
        <label>Début de la frise (minutes avant H)<input class="toc-input mono" type="number" id="time-start" min="0" max="1440" step="5"></label>
        <label><span><input type="checkbox" id="time-zulu"> Afficher les heures en ZULU plutôt qu’en H+</span></label>
      </div>
      <p class="toc-note">Sans heure H, la frise compte en H+ seulement. Les débuts des phases se règlent en les glissant sur la frise ou dans l’onglet Plan.</p>
      <button type="button" class="toc-btn is-primary" id="time-save">Appliquer</button>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_help.php'; ?>
</body>
</html>
