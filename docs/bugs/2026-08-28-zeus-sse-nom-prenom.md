# Bug — Zeus SSE : Nom et prénom saisis ignorés par l’identité générée

## Contexte

Module Zeus **Profil d’identité SSE**. On laisse « Génération automatique », on
remplit **Nom** et **Prénom** (ex. dudule / Marc).

## Symptôme

Le terminal SEEK affiche bien la personne saisie (« Cible : Marc dudule »,
champs Nom / Prénom). L’identité réellement appliquée (fiche, requête, plaque)
reste celle inventée par la génération.

## Cause

`sseApplyProfile` n’écrivait que les variables de pont du terminal. Si
l’identité avait déjà été générée au premier examen, la section identité
gardait le nom tiré au hasard. SEEK lisait les variables (saisie Zeus) ; la
fiche et la requête lisaient la section générée.

## Correctif

Après la saisie Zeus, le nom et le prénom sont recopiés dans l’identité SSE.
Un nom déjà imposé n’est plus écrasé par la génération. La remontée vers le
poste utilise ce nom-là.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseApplyProfile.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogOnLoad.sqf`
- `mod/@COMSPEC_SSE/addons/core/functions/fn_syncIdentityBridgeVars.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_buildAthenaPersonPayload.sqf`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_identifySubject.sqf`

## Vérification

Tests `SseZeusAuthoredNameAssetTest`. Pack Overwatch 1.4.91 + SSE 0.7.17, relancer
Arma. Zeus : génération automatique + Nom / Prénom → SEEK et requête portent
la même personne.

## Statut

corrigé (pack Overwatch 1.4.95)
