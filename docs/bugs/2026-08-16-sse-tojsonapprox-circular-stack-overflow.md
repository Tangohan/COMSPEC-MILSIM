# toJsonApprox — stack overflow sur références circulaires

## Contexte
Sérialisation JSON SQF avant envoi Athena/Overwatch (`fn_sendViaOverwatch.sqf` → `[_payload] call comspec_sse_fnc_toJsonApprox`).

## Symptôme
Risque de `STACK_OVERFLOW` / crash client si un payload SSE (fiche, numérique, biométrie, record) contient une référence circulaire (HashMap/array A → B → A) ou une profondeur excessive.

## Cause
`fn_toJsonApprox.sqf` était récursive pure, sans profondeur max ni détection de cycle (`isEqualRef`).

## Correctif
- Profondeur max 32 → littéral `"[MAX_DEPTH]"`
- Conteneurs déjà vus sur le chemin → `"[CIRCULAR_REFERENCE]"` via `isEqualRef`
- Copie du chemin `_visited` avant chaque descente (`+_visited`) pour ne pas polluer les branches sœurs
- Échappement JSON inchangé (`toString [92]`)

## Fichiers touchés
- `mod/@COMSPEC_SSE/addons/network/functions/fn_toJsonApprox.sqf`

## Vérification
Rebuild `comspec_sse_network.pbo` **2026-08-16 18:02** (Build Successful, 25581 o).
Envoi fiche / biométrie / numérique : à retester in-game ; payload circulaire → marqueur JSON, pas de débordement.

## Statut
corrigé — PBO network reconstruit
