# SEEK — refus « identity_required » à la transmission

## Contexte

Transmission d’une fiche depuis le terminal SEEK.

## Symptôme

HTTP 422 `identity_required` / bandeau ambre demandant nom, prénom ou alias.

## Cause (deux couches)

1. **DLL** : JSON Arma à guillemets doublés non normalisé dans `EnrichAtakPayload` → PHP reçoit `[]` (voir `2026-08-06-seek-identity-required-json-arma.md`).
2. **SQF** : champs Sujet masqués (`ctrlShow`) pouvant renvoyer vide hors page Sujet.

## Correctif

- Cache identité + repli nom de cible + bascule page Sujet.
- Normalisation JSON côté extension + assemblage SQF par morceaux.

## Fichiers touchés

- `fn_ssePersonDialogOnLoad.sqf`, `fn_sseTerminalPage.sqf`, `fn_ssePersonDialogSubmit.sqf`
- `display_sse_person.hpp`
- `Extension.cs` (`EnrichAtakPayload`)

## Vérification

Recompiler **DLL + PBO connect**, retransmettre une fiche avec identité.

## Statut

corrigé (complété par la note JSON Arma)
