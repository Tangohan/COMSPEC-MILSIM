# SEEK — HTTP 422 identity_required (JSON Arma à guillemets doublés)

## Contexte

Transmission fiche personne SEEK → `SubmitSsePerson` → `/api/sse/persons`.

## Symptôme

```
[ERROR][Tx] SSE fiche personne — HTTP 422: {"error":"identity_required",...}
```

Répété même après saisie / signature / dossier actif.

## Cause

1. `callExtension` livre souvent le JSON avec guillemets doublés : `{""last_name"":""X""}`.
2. `NormalizeArmaJson` existait déjà pour ce cas, mais n’était **pas** appelé dans `EnrichAtakPayload` / `PostSsePersonSync`.
3. `JsonDocument.Parse` échouait → corps renvoyé tel quel → `json_decode` PHP = `[]` → le serveur croit l’identité absente.

Facteur aggravant côté SQF : un seul `format` à 26 arguments pour tout le payload.

## Correctif

- `EnrichAtakPayload` : `NormalizeArmaJson` avant parse / enrichissement (tous les POST JSON en bénéficient).
- `fn_ssePersonDialogSubmit.sqf` : JSON assemblé par morceaux (`%1` seul) + log des longueurs d’identité + cache ctrlShow (déjà en place).

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogSubmit.sqf`

## Vérification

- Recompiler **DLL** `COMSPECExtension` + PBO `connect`.
- Transmettre une fiche : plus de 422 `identity_required` si nom/prénom/alias (ou alias cible) présents.
- Log attendu : `TX identité L=… F=… A=… octets=…`

## Statut

corrigé
