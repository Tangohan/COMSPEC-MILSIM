# 2D immersif : emprises de constructions et nom technique

## Contexte

Poste Overwatch Beta, photo aérienne. Des rectangles gris/blancs collés aux toits apparaissent parfois, avec au survol un libellé du type `marker_36`.

## Symptôme

Ces formes ressemblent au relevé des bâtiments, mais ce sont des rectangles de carte sans nom utile. En 2D immersif, le relevé n’avait pas toujours cette lecture d’emprise au sol. Le nom interne du jeu s’affichait au survol.

## Cause

Les rectangles de carte (forme rectangle, sans libellé ou avec un nom interne) étaient dessinés comme des zones, avec le nom brut en infobulle. Le 2D immersif dessinait déjà les constructions du relevé, mais un rectangle de carte restait superposé et affichait le nom technique.

## Correctif

En 2D immersif, les constructions du relevé restent des emprises claires collées à la photo. Un rectangle de taille bâtiment sans nom se lit de la même façon, sans libellé technique. Quand le relevé est chargé, ces rectangles ne se superposent plus au relevé.

## Fichiers touchés

- `public/assets/js/arma-map-markers.js`
- `public/assets/js/atak-overwatch-beta.js`
- `views/atak-overwatch-beta.php`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Photo aérienne, vue 2D immersif : toits en emprise au sol. Un rectangle sans nom ne montre plus de libellé technique au survol. Un clic sur une construction du relevé ouvre la fiche.

## Statut

Corrigé
