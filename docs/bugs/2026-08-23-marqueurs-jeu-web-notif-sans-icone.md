# ATAK — marqueurs jeu ↔ web (notif sans icône)

## Contexte

Pose d’un marqueur sur la carte Arma ou dans ATAK Enhanced (Iceman) : le journal web affiche « Marqueur placé », mais l’icône n’apparaît pas sur la carte web. Inversement, un marqueur posé sur l’ATAK web n’apparaît pas en jeu.

## Symptôme

- Journal d’activité : événement marqueur, souvent sans coordonnées.
- Carte web : pas d’icône (ou position absurde).
- Carte Arma / téléphone Iceman : le marqueur web ne redescend pas.

## Cause

1. **Jeu → web** : le JSON des coordonnées utilisait `str` / `format` (virgule FR). Le nettoyage d’extension transformait aussi les paires entières `[19345,17682]` en un seul nombre `19345.17682`, puis un blob vide `{}` : le journal s’écrivait, la position non.
2. **Web → jeu** : l’extension savait lire les marqueurs (`GetMarkers`), mais **aucun script Arma ne les interrogeait**. Seuls les tracés (formes) étaient tirés vers le jeu.

## Correctif

- Nombres en `toFixed` (point décimal) à l’envoi.
- Nettoyage JSON : virgule décimale seulement sur 1–3 chiffres après la virgule.
- Boucle `pollAthenaMarkers` : redescend les marqueurs d’origine web (`manual` / `source=web`) en `comspec_webmk_*` locaux, sans les renvoyer vers Athena.
- Rendu web : accepte `pos` ou `pos_x` / `pos_y`.
- Journal web : auteur + libellé, source `web`.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollAthenaMarkers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeCtabMarkers.sqf`
- `app/Controllers/Api/AtakApiController.php`
- `public/assets/js/atak-map.js`

## Vérification

1. Relancer Arma (Overwatch 1.4.27 + pont Athena 1.0.30).
2. Poser un marqueur sur la carte Arma ou dans ATAK Enhanced → icône sur l’ATAK web en quelques secondes.
3. Clic droit ATAK web → Placer un marqueur → point vert sur la carte Arma (`comspec_webmk_…`).

## Statut

corrigé en code — à valider en session
