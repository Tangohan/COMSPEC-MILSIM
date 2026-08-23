# Bug — SEEK II ignore le nom Eden (Faisal Haddad)

## Contexte

Unité customisée dans Eden (panneau Identité et/ou Nom + prénom COMSPEC). Requête d’identité SEEK II en jeu.

## Symptôme

Le compte rendu affiche un sujet inventé (`Faisal Haddad`, tiré des listes narratives Irak) au lieu du nom posé sur l’unité.

## Cause

1. `generateCluster` / `generatePerson` tirent un nom au hasard et l’écrivent dans `identity.name`.
2. `syncIdentityBridgeVars` recopie ce nom généré dans `COMSPEC_SSE_FirstName` / `LastName`, même si le chef de mission les avait remplis.
3. `identifySubject` lit uniquement la section générée, pas l’identité Eden (`name` de l’unité).

## Correctif

- Avant génération : imposer le nom Eden / COMSPEC sur le cluster (`applyAuthoredIdentity`).
- Ne plus écraser un nom explicitement saisi (`COMSPEC_SSE_NameAuthored`).
- SEEK II affiche le nom de l’unité (Identité Eden) sauf si Nom/Prénom COMSPEC sont imposés.
- Un modèle avec identité forcée (`nameForced`) reste prioritaire sur le nom Arma par défaut.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/generator/functions/fn_applyAuthoredIdentity.sqf` (nouveau)
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateData.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generatePerson.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_applyModel.sqf`
- `mod/@COMSPEC_SSE/addons/core/functions/fn_syncIdentityBridgeVars.sqf`
- `mod/@COMSPEC_SSE/addons/core/functions/fn_setIdentity.sqf`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_identifySubject.sqf`
- `mod/@COMSPEC_SSE/addons/eden/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/eden_sse_attributes.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseApplyProfile.sqf`

## Vérification

1. Rebuild PBO `core` + `generator` + `biometrics` (+ `eden` + `connect` pour les infobulles).
2. Relancer une **nouvelle** mission (la génération déjà faite en session ne se recalcule pas).
3. Unité avec Identité Eden « Jean Dupont » → SEEK II : Sujet Jean Dupont.
4. Unité avec Nom/Prénom COMSPEC remplis → ces champs, pas le nom de pool.
5. Option « Inventer un nom SSE » → comportement d’avant (nom généré).

## Statut

corrigé (à recopier les PBO / relancer la mission)
