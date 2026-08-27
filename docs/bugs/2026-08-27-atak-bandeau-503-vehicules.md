# Bandeau « le poste n’atteint pas ses données » en boucle

## Contexte

Carte ATAK web, 27/08/2026. Le bandeau rouge revient très souvent, alors que la page est ouverte et que les effectifs peuvent encore se charger. Console : 503 répétés sur la relève des engins.

## Symptôme

- Bandeau : « Le poste n’atteint pas ses données pour le moment. Les mises à jour reprendront toutes seules. »
- Console : échec à répétition sur la relève des engins, toutes les quelques secondes.
- Le reste de la carte (effectifs, ping) peut encore répondre.

## Cause

1. La relève des engins est lue toutes les 3 s. Un refus isolé était traité comme une panne de **tout** le poste.
2. Les effectifs répondaient : l’avertissement était effacé, puis le refus suivant réaffichait le bandeau. Boucle toutes les ~8–10 s.
3. Prod VPS (`d726194cfcbe9f87`) : la vue `v_atak_active_vehicles` n’existe pas (`SQLSTATE[42S02]` 1146). Le filet de schéma s’arrêtait dès que d’autres tables carte étaient déjà là, donc la vue n’était jamais posée. Chaque GET `/api/atak/vehicles` levait une exception → 503 + mail d’alerte.

## Correctif

- Un refus sur les engins, la météo ou les caméras ne déclenche plus le bandeau : seuls les effectifs (et les envois) comptent pour la liaison.
- Relève des engins : lecture de la table, liste vide si la base n’est pas prête, plus un refus qui fige la carte.
- Filet de schéma : poser le suivi des engins s’il manque, **et** créer la vue si la table est là mais pas la vue.

## Fichiers touchés

- `public/assets/js/atak-socket.js`
- `app/Controllers/Api/AtakApiController.php`
- `app/Repositories/AtakVehicleTrackingRepository.php`
- `app/Support/AtakModulesSchema.php`
- `tests/Unit/AtakPollBackoffAssetTest.php`
- `tests/Unit/AtakVehiclePollAssetTest.php`

## Vérification

Ouvrir la carte, recharger sans cache. Un engin absent ou une relève vide ne doit plus faire clignoter le bandeau tant que les effectifs répondent.

## Statut

corrigé (déploiement VPS requis)
