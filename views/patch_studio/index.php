<?php
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ATHENA — Patch Studio</title>
<meta name="csrf-token" content="<?= $e($csrf) ?>">
<?php require base_path('views/patch_studio/_head.php'); ?>
<script type="module" data-patch-studio-module src="<?= $e($studioAsset('js/patch-studio.js')) ?>"></script>
</head>
<body class="nl-page ps-page">
<a class="ps-skip" href="#viewport">Aller au canevas</a>
<div id="patch-studio" data-api="<?= $e($api) ?>" data-recovery="<?= $e($recoveryKey) ?>">
<?php $compactHeader = true; require base_path('views/patch_studio/_header.php'); ?>
<?php require base_path('views/patch_studio/_disclaimer.php'); ?>
<section id="studio-home" class="ps-home" aria-label="Accueil du Patch Studio"></section>
<div id="studio-editor" hidden>
<header class="ps-header">
<label class="ps-project-label" for="project-name">PROJET OUVERT</label>
<div class="ps-title"><input id="project-name" aria-label="Nom du projet" value="Sans titre" maxlength="120"><span id="save-state" role="status" aria-live="polite">Nouveau projet</span></div>
<button data-action="library">← Accueil</button><button data-action="save-setup" title="Garder la matière, le format et les réglages pour créer d’autres patchs">Enregistrer un setup</button><button data-action="save" class="ps-primary" title="Ctrl+S">Enregistrer</button><button data-action="export"><svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-4-8 4-4 4 4M5 14v6h14v-6"/></svg>Exporter / Arma 3</button>
</header>
<nav class="ps-toolbar" aria-label="Outils de création">
<button data-action="new">＋ Nouveau</button><button data-action="undo" title="Ctrl+Z">↶ Annuler</button><button data-action="redo" title="Ctrl+Y">↷ Rétablir</button><i></i>
<button data-action="duplicate" title="Ctrl+D">Dupliquer</button><button data-action="group">Grouper</button><button data-action="ungroup">Dégrouper</button><button data-action="delete">Supprimer</button><i></i>
<label>Version <select id="variant"><option value="standard">Jour</option><option value="night">Nuit (JVN)</option></select></label><button data-action="night" title="Crée la version nuit à partir de la version jour">Copier jour → nuit</button>
<button data-action="preview">Aperçu</button><button data-action="nvpreview" title="Voir le patch comme dans des jumelles de vision nocturne (simulation)">Vision nocturne</button><button data-action="shortcuts" title="Raccourcis clavier" aria-label="Raccourcis clavier">?</button><button data-action="fullscreen" title="Plein écran" aria-label="Plein écran"><svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m8 0h5v-5"/></svg></button>
</nav>
<nav class="ps-panel-tabs" aria-label="Zones de travail"><button data-panel="resources" aria-pressed="false">Ressources</button><button data-panel="canvas" aria-pressed="true">Canevas</button><button data-panel="inspector" aria-pressed="false">Réglages</button></nav>
<div class="ps-workspace" data-panel="canvas">
<aside class="ps-resources" aria-label="Ressources">
<div class="ps-aside-title">RESSOURCES</div>
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
<main class="ps-center" aria-label="Éditeur du patch">
<div class="ps-canvas-tools"><span id="canvas-size">512 × 256 px</span><label><input id="grid" type="checkbox"> Grille</label><label><input id="snap" type="checkbox" checked> Magnétisme</label><select id="canvas-backdrop" aria-label="Fond de travail"><option value="transparent">Damier</option><option value="light">Clair</option><option value="dark">Sombre</option></select><button data-action="fit">Adapter</button><button data-action="actual">1:1</button></div>
<div id="viewport" tabindex="0" aria-label="Canevas de création. Flèches pour déplacer, Maj et flèches pour aller plus vite, Page précédente et suivante pour changer d’objet, Suppr pour supprimer, point d’interrogation pour tous les raccourcis.">
<canvas id="ruler-x" class="ps-ruler-x"></canvas><canvas id="ruler-y" class="ps-ruler-y"></canvas>
<div id="artboard"><div id="artwork"></div><svg id="selection" xmlns="http://www.w3.org/2000/svg"></svg><div id="canvas-grid"></div></div>
<div class="ps-canvas-hint">Molette : zoom · Espace + glisser : déplacement · Maj : sélection multiple · ? : raccourcis</div>
</div>
<footer class="ps-canvas-footer"><span id="selection-state" role="status" aria-live="polite">Aucun objet sélectionné</span><div><button data-action="zoom-out" aria-label="Dézoomer">−</button><input id="zoom" type="range" min="15" max="400" value="100" aria-label="Zoom"><output id="zoom-value">100 %</output><button data-action="zoom-in" aria-label="Zoomer">＋</button></div></footer>
</main>
<aside class="ps-inspector" aria-label="Propriétés et calques">
<div class="ps-aside-title">PROPRIÉTÉS <span id="object-kind">Canevas</span></div>
<div id="properties"></div>
<section class="ps-layers"><div class="ps-aside-title">CALQUES <span id="layer-count">0</span></div><div id="layers"></div></section>
</aside>
</div>
</div>
<div id="toast" role="status" aria-live="polite" hidden></div>
<dialog id="modal" aria-labelledby="modal-title"><div class="ps-modal-head"><h2 id="modal-title"></h2><button data-action="close-modal" aria-label="Fermer">✕</button></div><div id="modal-content"></div></dialog>
</div>
</body></html>
