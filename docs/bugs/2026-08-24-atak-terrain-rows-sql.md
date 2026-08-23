# Relief ATAK — erreur SQL `rows` (MariaDB)

## Contexte

GET `/api/atak/terrain` (console ATAK, calque relief encore en test) en production Hostinger.

## Symptôme

Exception signalée :

`SQLSTATE[42000]: Syntax error or access violation: 1064 ... near 'rows, heights, min_z, max_z, ...'`

La page / la console ATAK remonte une erreur technique au lieu d’un relief simplement indisponible.

## Cause

La colonne `rows` n’était pas protégées par des backticks. Sur MariaDB, `ROWS` est un mot réservé (fenêtres / `FETCH`). La lecture `SELECT ... cols, rows, heights ...` est donc rejetée à la préparation.

## Correctif

- Entourer les identifiants SQL du dépôt relief (`atak_terrain_grids` / chunks), notamment `` `rows` ``.
- Si la lecture échoue encore, renvoyer « relief non encore relevé » au lieu d’une exception 500.

## Fichiers touchés

- `app/Repositories/AtakTerrainRepository.php`
- `app/Controllers/Api/AtakTerrainApiController.php`

## Vérification

- Relire la requête : plus de `rows` nu dans le SELECT / INSERT.
- `php -l` sur les deux fichiers.
- En prod, GET `/api/atak/terrain?mapId=1` doit répondre du JSON métier, pas une page d’erreur SQL.

## Statut

corrigé (déploiement prod requis)
