<?php
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ATHENA — Patch Studio</title>
<meta name="csrf-token" content="<?= $e($csrf) ?>">
<link rel="stylesheet" href="<?= $e(asset_url('assets/css/patch-studio.css')) ?>">
<script type="module" src="<?= $e(asset_url('assets/js/patch-studio.mjs')) ?>"></script>
</head>
<body>
<div id="patch-studio" data-api="<?= $e($api) ?>" data-recovery="<?= $e($recoveryKey) ?>">
<header class="ps-header">
<a class="ps-brand" href="<?= $e(url('')) ?>" title="Retour à ATHENA"><span class="ps-mark">A</span><span>ATHENA<small>PATCH STUDIO</small></span></a>
<div class="ps-title"><input id="project-name" aria-label="Nom du projet" value="Sans titre" maxlength="120"><span id="save-state" role="status">Nouveau projet</span></div>
<button data-action="library">Bibliothèque</button><button data-action="save" class="ps-primary" title="Ctrl+S">Enregistrer</button><button data-action="export">Exporter ↗</button>
</header>
<nav class="ps-toolbar" aria-label="Outils de création">
<button data-action="new">＋ Nouveau</button><button data-action="undo" title="Ctrl+Z">↶ Annuler</button><button data-action="redo" title="Ctrl+Y">↷ Rétablir</button><i></i>
<button data-action="duplicate" title="Ctrl+D">Dupliquer</button><button data-action="group">Grouper</button><button data-action="ungroup">Dégrouper</button><button data-action="delete">Supprimer</button><i></i>
<label>Composition <select id="variant"><option value="standard">Standard</option><option value="night">Nocturne · simulation</option></select></label><button data-action="night">Copier standard → nuit</button>
<button data-action="preview">Aperçu</button><button data-action="fullscreen" title="Plein écran">⛶</button>
</nav>
<div class="ps-workspace">
<aside class="ps-resources" aria-label="Ressources">
<div class="ps-aside-title">RESSOURCES <span>＋</span></div>
<div class="ps-tabs" role="tablist" aria-label="Catégories de ressources">
<?php foreach (['templates'=>'Modèles','shapes'=>'Formes','text'=>'Textes','patterns'=>'Motifs','textures'=>'Textures','symbols'=>'Symboles','images'=>'Images importées','organization'=>'Organisation'] as $key=>$label): ?>
<button role="tab" data-tab="<?= $key ?>" aria-selected="<?= $key==='templates'?'true':'false' ?>"><?= $label ?></button>
<?php endforeach; ?>
</div>
<input id="resource-search" type="search" placeholder="Rechercher une ressource…" aria-label="Rechercher une ressource">
<div id="resources" class="ps-resource-content"></div>
<div class="ps-resource-foot">Glissez vos images ici.<br>PNG · JPG · WebP · SVG · TTF/OTF</div>
<input id="asset-file" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,.ttf,.otf" hidden>
<input id="project-file" type="file" accept="application/json,.json" hidden>
</aside>
<main class="ps-center">
<div class="ps-canvas-tools"><span id="canvas-size">512 × 256 px</span><label><input id="grid" type="checkbox"> Grille</label><label><input id="snap" type="checkbox" checked> Magnétisme</label><select id="canvas-backdrop" aria-label="Fond de travail"><option value="transparent">Damier</option><option value="light">Clair</option><option value="dark">Sombre</option></select><button data-action="fit">Adapter</button><button data-action="actual">1:1</button></div>
<div id="viewport" tabindex="0" aria-label="Canevas de création. Flèches pour déplacer, Suppr pour supprimer.">
<canvas id="ruler-x" class="ps-ruler-x"></canvas><canvas id="ruler-y" class="ps-ruler-y"></canvas>
<div id="artboard"><div id="artwork"></div><svg id="selection" xmlns="http://www.w3.org/2000/svg"></svg><div id="canvas-grid"></div></div>
<div class="ps-canvas-hint">Molette : zoom · Espace + glisser : déplacement · Maj : sélection multiple</div>
</div>
<footer class="ps-canvas-footer"><span id="selection-state">Aucun objet sélectionné</span><div><button data-action="zoom-out" aria-label="Dézoomer">−</button><input id="zoom" type="range" min="15" max="400" value="100" aria-label="Zoom"><output id="zoom-value">100 %</output><button data-action="zoom-in" aria-label="Zoomer">＋</button></div></footer>
</main>
<aside class="ps-inspector" aria-label="Propriétés et calques">
<div class="ps-aside-title">PROPRIÉTÉS <span id="object-kind">Canevas</span></div>
<div id="properties"></div>
<section class="ps-layers"><div class="ps-aside-title">CALQUES <span id="layer-count">0</span></div><div id="layers"></div></section>
</aside>
</div>
<div id="toast" role="status" aria-live="polite" hidden></div>
<dialog id="modal"><div class="ps-modal-head"><h2 id="modal-title"></h2><button data-action="close-modal" aria-label="Fermer">✕</button></div><div id="modal-content"></div></dialog>
</div>
</body></html>
