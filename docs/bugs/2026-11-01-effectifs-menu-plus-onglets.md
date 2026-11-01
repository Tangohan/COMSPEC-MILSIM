# Effectifs : menu « Plus » des onglets coupé ou resté ouvert

## Contexte
Back-office › Effectifs, barre d’onglets (`nav.bo-eff-tabs`) avec le menu « Plus » en `<details>`.

## Symptôme
- Le menu « Plus » s’ouvrait sous la barre puis était coupé, ou passait sous le tableau.
- Il restait ouvert après un clic ailleurs, à Échap, ou au retour sur la page par « Précédent ».
- Aucune navigation au clavier dans la liste.

## Cause
- Le menu (position absolue) était dans la barre d’onglets elle-même en `overflow-x: auto` : tout ce qui dépassait était rogné. Le contournement `:has(.bo-eff-tabs__more[open]) { overflow: visible }` cassait le défilement et dépendait du navigateur.
- Un `<details>` ne se referme ni au clic extérieur ni à Échap, et le navigateur restaure son état `open` depuis le cache de retour arrière.

## Correctif
- Les onglets défilent dans `.bo-eff-tabs__scroll` ; le menu « Plus » est posé à côté, hors du conteneur qui défile, dans une barre à `z-index` propre (sous la barre du haut).
- `public/assets/js/eff-workspace-shell.js` : fermeture au clic extérieur, à Échap (le focus revient sur « Plus »), quand le focus quitte le menu et à `pageshow` ; flèches haut/bas, Début/Fin ; recalage si le menu sort de l’écran.
- Menu classé par groupes (Organisation, Suivi des dossiers, Registre), pastilles cumulées sur « Plus ».

## Fichiers touchés
- `views/admin/effectifs_workspace/shell.php`
- `public/assets/js/eff-workspace-shell.js`
- `public/assets/css/back-office-effectifs-maquette.css`
- `public/assets/css/back-office-effectifs-refonte.css`, `public/assets/css/back-office-effectifs-workspace.css` (anciennes règles retirées)

## Vérification
- `tests/Unit/EffectifsShellRenderTest.php` (rendu de la barre, menu fermé par défaut, gestionnaires clavier / clic extérieur / retour arrière).
- Aperçu hors application à 1600 px et 390 px.

## Statut
Corrigé (branche `site/effectifs-onglets`).
