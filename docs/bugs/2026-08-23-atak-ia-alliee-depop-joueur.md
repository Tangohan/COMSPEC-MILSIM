# IA alliée ATAK : le joueur disparaît puis réapparaît

## Contexte

Suivi « IA alliée sur l’ATAK » pendant une session Overwatch 1.4.51. Journal « Aujourd’hui » : bascule N-10 ↔ Alpha 1-2 — James Brown toutes les ~3 s, grilles différentes.

## Symptôme

Une fois sur deux, le joueur disparaît de l’ATAK web au profit de l’IA (ou l’inverse). Un seul contact dans le tableau des effectifs.

## Cause

1. L’IA pouvait porter le même indicatif que le slot joueur (`N-10`) si nom = groupId.
2. Le rapport IA réutilisait parfois le Steam du client relais. `retireSteamSiblingUnits` mettait hors-ligne l’autre ligne (joueur ↔ IA) à chaque heartbeat.
3. Le journal d’activité clé par adresse IP enregistrait un « changement d’indicatif » à chaque rapport relais.

## Correctif

- Indicatif IA toujours préfixé `ALLY-` (identifiant réseau).
- Extra relais (IA / téléphone / GPS) décodé même s’il arrive en texte ; Steam / identifiant BFT retirés.
- Les contacts relais ne retirent plus les frères Steam, et ne sont pas retirés par le joueur.
- Le journal d’activité ignore les rapports relais.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_allyTrackCallsign.sqf`
- `app/Controllers/Api/AtakApiController.php`
- `app/Repositories/AtakDataRepository.php`
- `tests/Unit/AtakProxyContactExtraTest.php`

## Vérification

Tests unitaires `AtakProxyContactExtraTest`. Retest jeu : joueur + IA alliée visibles en même temps, sans bascule d’indicatif.

## Statut

Corrigé (sources) — rebuild mod + déploiement site requis
