# Overwatch Beta — tableau des effectifs trop étroit

**Statut :** corrigé (sources)

## Contexte

Colonne contacts BFT : un mini-tableau « Effectifs » scrollait horizontalement et prenait trop de place.

## Symptôme

Tableau illisible / moche dans le bandeau latéral.

## Correctif

Remplacé par un bouton « Tableau des effectifs » qui ouvre un grand modal plein écran relatif. Clic sur une ligne → fiche contact + fermeture. Échap / clic hors carte ferme le modal.

## Fichiers touchés

- `views/atak-overwatch-beta.php`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`
- `tests/Unit/AtakOverwatchBetaAssetTest.php`

## Vérification

1. Ctrl+F5 Overwatch Beta.
2. Colonne contacts : bouton + compteur, plus de tableau inline.
3. Clic bouton → grand tableau ; clic ligne → tiroir contact.

## Statut

corrigé (sources)
