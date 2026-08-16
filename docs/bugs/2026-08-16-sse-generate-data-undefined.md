# SSE — génération cassée (_data / setToPairs) + UI vide

## Contexte
Zeus SSE Control / Brief générer : erreurs script, SSE DATA à `?`, overlay `SSE ? | ? | ?`.

## Symptômes
1. `Variable indéfinie : _data` dans `fn_generateData.sqf` (~L211)
2. `[BIS_fnc_setToPairs] type ARRAY, expected STRING, on index 1`
3. Panneaux Zeus vides / entités UNKNOWN

## Cause
PBO generator encore sur l’ancien appel `[_data, ["sections", _sections]] call BIS_fnc_setToPairs` (échec → `_data` non réassigné). Les sources étaient déjà migrées vers `comspec_sse_fnc_setPair` mais non rechargées en jeu.

## Correctif
- Rebuild PBO `core` / `generator` / `interaction` / `zeus`
- Garde-fous `_data` dans `generateData`
- Journal technique SSE (WARN/ERROR → RPT + tampon)

## Vérification
1. Recharger `@COMSPEC_SSE`
2. Zeus → Brief / Générer : plus d’erreur setToPairs
3. Overlay debug : `SSE <uid> | <profil> | …`
4. ACE Self → Journal technique

## Statut
corrigé (rebuild requis)
