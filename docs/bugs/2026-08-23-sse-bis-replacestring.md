# Bug — Resynch / JSON SSE (`BIS_fnc_replaceString`)

## Contexte
Resynch ATAK et file SSE : overlay SQF pendant la sérialisation JSON.

## Symptôme
`Error Variable indéfinie dans une expression: bis_fnc_replacestring` dans `fn_toJsonApprox.sqf` (l.19 / l.22) et `fn_makeIdempotencyKey.sqf`.

## Cause
Appel à `BIS_fnc_replaceString` alors que la Functions Library n’est pas toujours chargée (preInit / file hors-ligne). L’opérateur natif `replaceString` (Arma 2.14+) est disponible.

## Correctif
Remplacer tous les appels SSE concernés par `replaceString`.

## Fichiers touchés
- `mod/@COMSPEC_SSE/addons/network/functions/fn_toJsonApprox.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_makeIdempotencyKey.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_applyAuthoredContent.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateComputer.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_createModel.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiRecordToSse.sqf`

## Vérification
Rebuild PBO SSE, relancer Arma, Resynch : plus d’overlay `bis_fnc_replacestring`.

**24/08 :** overlay encore visible en jeu — le PBO **Workshop** (`!Workshop\@COMSPEC_SSE`) datait de 15:01 (encore `call BIS_fnc_replaceString`). Les sources + PBO local 18:26 étaient déjà corrigés (`replaceString` natif). Recopie de `comspec_sse_network.pbo` vers le Workshop.

## Statut
corrigé — déployer le PBO Workshop puis quitter le launcher / relancer Arma
