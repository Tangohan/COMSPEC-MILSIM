# Relief ATAK — erreur SQL `rows` (MariaDB)

## Contexte

GET `/api/atak/terrain?mapId=1` depuis la carte ATAK (calque relief), production Hostinger.
Réf. corrélation `295b70ff20a0bc93` (compte 5, communauté 7).

## Symptôme

Exception PDO 1064 :

`near 'rows, heights, min_z, max_z, filled_cells, ready, sampled_at, updated_at FROM...'`

La page d’erreur technique s’affiche au lieu d’un JSON « relief non encore relevé ». La bannière ATAK « Liaison temporairement coupée » peut apparaître dans le même écran.

## Cause

La colonne s’appelait `rows`. Sur MariaDB, `ROWS` est un mot réservé (fenêtres / `FETCH`). Un `SELECT … cols, rows, heights …` sans protection est rejeté à la préparation. Un 500 JSON/HTML casse le chargement du relief.

## Correctif

- Renommer la colonne SQL en `grid_rows` **en CLI seulement** (`run-migrations`).
  Un `ALTER` HTTP réécrit le blob d’altitudes et peut faire timeout toute la carte.
- Les lectures détectent `grid_rows` **ou** `` `rows` `` (identifiant protégé).
  Le JSON ATAK conserve `rows` pour le calque.
- Si la lecture échoue encore, répondre « relief non encore relevé » (pas d’exception 500).

## Fichiers touchés

- `bootstrap/atak_cop_terrain_migration.php`
- `app/Repositories/AtakTerrainRepository.php`
- `app/Controllers/Api/AtakTerrainApiController.php`

## Vérification

- `php -l` sur le dépôt et la migration.
- Relire le SELECT : identifiant `` `grid_rows` `` ou `` `rows` AS grid_rows ``, jamais `rows` nu.
- Après déploiement : GET `/api/atak/terrain?mapId=1` doit renvoyer du JSON métier (`ok: true`), pas une page d’erreur SQL.

## Statut

corrigé (déploiement prod requis ; le rename CLI reste à lancer pour figer le schéma)
