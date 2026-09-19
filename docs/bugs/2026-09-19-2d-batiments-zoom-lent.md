# 2D : bâtiments lents et décalés au zoom

## Contexte

Poste Overwatch Beta, vue À plat avec la case Bâtiments, forêts et obstacles cochée. Zoom sur un village ou un aérodrome (photo aérienne).

## Symptôme

Le zoom met plusieurs secondes. Les empreintes de bâtiments glissent par rapport au fond, puis se recollent. Sensation de carte qui « tire ».

## Cause

Les constructions n’étaient pas chargées d’avance : chaque zoom redemandait la zone visible. Le dessin se refaisait pendant l’animation du zoom, en même temps que le fond, ce qui décale les volumes. Le plan à plat mélangeait déjà ce rendu de constructions.

## Correctif

À plat n’affiche plus de volumes. 2D immersif charge tout le relevé une fois, colle les empreintes au fond, et ne redessine qu’après le zoom. Relief 3D et Tactique 3D restent les vues en volume.

## Fichiers touchés

- `views/atak-overwatch-beta.php`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/overwatch-gl/OverwatchGlMap.js`
- `app/Controllers/Api/AtakSceneApiController.php`
- `app/Repositories/AtakSceneObjectRepository.php`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). À plat : pas de volumes. 2D immersif : constructions visibles, zoom collé au fond.

## Statut

Corrigé
