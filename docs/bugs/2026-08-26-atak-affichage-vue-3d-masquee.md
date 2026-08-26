# Bug — vue 3D, inclinaison et relief Z absents d’Affichage

## Contexte

Carte ATAK web, barre d’outils **Position** → **Affichage** (« Apparence de la carte »). Codex avait déjà livré la vue inclinée (#200), l’exagération du relief (#216 / #218) et les bâtiments (#221).

## Symptôme

Le panneau Affichage montrait positions, icônes, libellés, cases (relief et profondeur, animation, cadre, flèche, ligne), puis le bloc Relief (ombrage, courbes, altitudes, pentes, opacité) et le pied « Données terrain ». Manquaient :

- le choix de vue (à plat / inclinée)
- le curseur d’inclinaison
- le curseur d’amplitude du relief (Exagération Z)

## Cause

Les réglages existaient déjà dans `#atak-terrain-3d-settings`, mais le bloc était `hidden` tant que la vue 3D n’était pas active. Le bouton **3D** était dans le groupe **Vue**, replié par défaut (seul **Position** est ouvert). Un opérateur qui ouvre Affichage depuis Position ne voyait donc jamais ces commandes.

## Correctif

Remonter le choix **Vue de la carte** (À plat / Inclinée) avec **Exagération Z** et **Inclinaison** dans Affichage, toujours visibles. Le bouton **3D** du groupe Vue reste, synchronisé. « Vue 3D » apparaît aussi dans Personnaliser.

## Fichiers touchés

- `views/atak.php`
- `public/assets/js/atak-terrain-3d.js`
- `public/assets/js/atak-map-tools.js`
- `tests/Unit/AtakTerrain3dAssetTest.php`

## Vérification

Ouvrir ATAK → Position → Affichage : **Vue de la carte**, **Exagération Z** et **Inclinaison** sont visibles sans activer 3D au préalable. Passer sur Inclinée incline la carte ; le bouton Vue → 3D passe à « 3D actif ».

## Statut

corrigé
