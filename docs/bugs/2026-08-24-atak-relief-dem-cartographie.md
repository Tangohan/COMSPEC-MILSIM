# Relief ATAK — DEM progressif, hillshade et courbes serveur

## Contexte

Relevé ACE « autour de l’équipe » (~4 km, pas de 50 m). Le calque ombrage
restait vide alors que des blocs arrivaient bien.

## Symptôme

Après un relevé local, la carte ATAK n’affichait pas de relief. Le navigateur
attendait une grille « prête » (96 % du théâtre entier) puis recalculait
hillshade et courbes en JavaScript à partir du blob d’altitudes.

## Cause

1. `ready` n’était vrai qu’à 96 % d’Altis : un patch de 4 km (~7 %) ne
   déclenchait jamais le calque.
2. `GET /api/atak/terrain` renvoyait toute la heightmap au navigateur.
3. L’ombrage était calculé à chaque affichage côté client.

## Correctif

- Fusion DEM conservée (grille int16, pas de 50 m). `ready` dès 9 cellules.
- Le navigateur ne reçoit plus la grille : seulement métadonnées + couverture.
- Hillshade et pentes : PNG serveur (Horn, lumière nord-ouest).
- Courbes : Marching Squares → GeoJSON (10 m / 50 m), coordonnées Arma.
- `POST /api/atak/terrain/samples` pour un lot `{x,y,z}`.
- Couches Leaflet : satellite → ombrage → courbes → routes / BFT.
- Interrupteurs : ombrage, courbes 10 m, courbes 50 m, altitudes, pentes.

## Fichiers touchés

- `app/Services/Tactical/AtakTerrainMath.php`
- `app/Services/Tactical/AtakTerrainIsolines.php`
- `app/Services/Tactical/AtakTerrainCartography.php`
- `app/Repositories/AtakTerrainRepository.php`
- `app/Controllers/Api/AtakTerrainApiController.php`
- `app/Controllers/Api/AtakApiController.php` (`terrain_z`)
- `routes/web.php`
- `public/assets/js/atak-terrain.js`
- `public/assets/js/atak-map.js`
- `views/atak.php`
- `tests/Unit/AtakTerrainCartographyTest.php`

## Vérification

- `php -l` sur les fichiers PHP touchés.
- Tests unitaires isolignes / ombrage (si `vendor/` présent).
- En jeu : ACE « Relever le relief autour de l’équipe », puis ouvrir l’ATAK :
  couverture > 0 %, ombrage et courbes 10 m visibles sur la zone parcourue.

## Statut

corrigé (cartographie serveur ; rebuild PBO non requis)
