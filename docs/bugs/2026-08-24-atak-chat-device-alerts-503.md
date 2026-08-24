# ATAK — 503 sur `/api/chat` et `/api/atak/device-alerts`

## Contexte

Console carte ATAK : `api/chat?mapId=1` et `api/atak/device-alerts` en 503.
Les caméras (`video-feeds`) répondaient. Un GET manuel des alertes pouvait
renvoyer un JSON valide (certificat N-10).

## Symptôme

Journal radio figé / vide (`[]`), pastille liaison, `Failed to load resource 503`.

## Cause

1. `getChatMessages` / `getChatMessagesAfter` liaient `LIMIT ?` alors que PDO
   a `ATTR_EMULATE_PREPARES = false`. MySQL native refuse souvent ce paramètre
   → exception → 503. Les lectures « depuis » interpolaient déjà le LIMIT.
2. `deviceAlertsIndex` et le journal radio n’avaient pas de filet : une
   exception registre/certificat faisait tomber tout le GET.

## Correctif

- LIMIT interpolé (entier borné), comme `getChatMessagesSince`.
- Lecture chat : `[]` en 200 si la base échoue, pas 503.
- Alertes appareils : payload vide `ok: true` si le registre plante.

## Fichiers touchés

- `app/Repositories/AtakDataRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Tactical/AtakIntelViewService.php`

## Vérification

`php -l` sur les trois fichiers. Recette : ouvrir `/atak` ; le journal radio
ne doit plus 503 ; les alertes terminal restent visibles (ex. certificat).

## Statut

corrigé
