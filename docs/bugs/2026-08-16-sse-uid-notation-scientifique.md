# SSE — identifiants en notation scientifique (`1.11177e+09`)

## Contexte

Acquisition numérique / identification watchlist transmises vers Athena / ATAK.

## Symptôme

Hint jeu : `Acquisition numérique transmise — SSE-WL-1.11177e+09`  
Journal ATAK : `E-WL-1.11177e+09` dans les lignes INTEL.

## Cause

`format ["…%1", hash]` avec un grand entier Arma → conversion flottante en notation scientifique. Affecte `SSE-WL-*`, `SSE-DIG-*`, IMEI, FP/IR/DNA, etc.

## Correctif

- Nouveau `comspec_sse_fnc_idToken` (`toFixed 0` + padding)
- UID / références générés via `idToken` (identify, phone, computer, bio, cluster, USB, VIN…)
- Nettoyage legacy dans `sendViaOverwatch` si `e+` encore présent
- Athena : `sse_normalize_ref_display()` sur résumés / refs d’événements intel

## Fichiers touchés

- `addons/core/functions/fn_idToken.sqf` + `config.cpp`
- biometrics / generator / digital / network (UID)
- `app/Support/helpers.php`, `SseIntelEventRepository.php`

## Vérification

1. Rebuild PBO core (+ biometrics, generator, digital, network)
2. Nouvelle acquisition → hint du type `SSE-DIG-084219337` (chiffres, pas `e+`)
3. Anciennes lignes web : `1.11e+09` affiché en entier

## Statut

Corrigé — rebuild PBO requis.
