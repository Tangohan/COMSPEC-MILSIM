# Affichage — pavés techniques et inventaire contradictoire

## Contexte

Réglages carte du poste ATAK. L’opérateur voit deux pavés gris d’atelier, puis « Relief du théâtre non encore relevé » / ombrage et relevé « Pas encore sur le poste », alors que 460 bâtiments, 506 forêts et une date de dernier relevé sont déjà affichés.

## Symptôme

- Textes d’atelier au milieu des réglages.
- On croit qu’aucun relevé n’est arrivé, alors que les volumes du jeu sont déjà sur le poste.

## Cause

1. Un calque villes/routes injectait une notice d’atelier. Un autre script réécrivait encore un pavé du même genre.
2. L’inventaire mélange deux choses : le **sol** (ombrage, courbes, pentes) et les **volumes** (bâtiments, forêts). La date de dernier relevé venait des volumes. Sans altitudes, ombrage et courbes sont vraiment absents — le bandeau le disait comme si rien n’avait été reçu.

## Correctif

- Retrait des pavés d’atelier. Cases « Villes et villages » / « Routes ».
- Si des bâtiments ou forêts sont là sans sol : « Bâtiments et forêts reçus. L’ombrage du sol n’est pas encore sur le poste. »
- Un relevé de sol déjà reçu, ombrage pas encore prêt : le poste le dit, au lieu de « pas encore sur le poste ».

Les données d’ombrage ne sont pas dans Git : elles arrivent en session et restent sur le poste. Les fonctions carte, elles, sont dans le dépôt.

## Fichiers touchés

- `public/assets/js/atak-geo-live.js`
- `public/assets/js/atak-terrain.js`
- `public/assets/js/atak-terrain3d-premium.js`
- `public/assets/js/map/MapControls.js`
- `views/atak.php`

## Vérification

Tests d’assets. Contrôle : Réglages → Carte, plus de pavé gris ; bandeau cohérent avec bâtiments déjà comptés.

## Statut

Corrigé.
