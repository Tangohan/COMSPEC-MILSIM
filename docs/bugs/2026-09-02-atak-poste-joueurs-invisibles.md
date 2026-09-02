# Opérateurs invisibles sur la carte du poste

## Contexte

Carte du poste ATAK en vue à plat. Un contact (TA1) figure dans le tableau des effectifs, en liaison, avec une grille. La carte montre le terrain, les zones et de tout petits symboles bleus, sans nom.

## Symptôme

On voit tout sur la carte sauf les joueurs : pas d’indicatif sous les symboles, impression que les opérateurs ne sont pas posés alors qu’ils sont en liaison dans le tableau.

## Cause

- L’affichage tactique des effectifs prenait la main même avant d’être prêt : les symboles classiques n’étaient plus dessinés, et s’il n’était pas encore chargé, plus rien d’identifiable.
- Le cadre du symbole était trop étroit : l’indicatif était recadré et illisible sur la carte claire.
- En secours, les symboles OTAN n’affichaient le nom qu’en vue relief.

## Correctif

- Tant que l’affichage tactique n’est pas prêt, les effectifs restent dessinés.
- Cadre assez large pour l’indicatif, fond sombre, au-dessus du reste.
- L’indicatif reste affiché à plat.
- Une position nulle n’est plus utilisée : on reprend la grille.

## Fichiers touchés

- `public/assets/js/atak-map.js`
- `public/assets/js/map/MarkerManager.js`
- `public/assets/js/map/atak-c2-bridge.js`
- `public/assets/css/atak-map-c2-v2.css`
- `tests/Unit/AtakC2PlayerMarkerAppearanceAssetTest.php`

## Vérification

Test d’assets. Contrôle visuel : recharger le poste, un contact en liaison doit montrer son indicatif sous le symbole, au même endroit que le tableau.

## Statut

corrigé
