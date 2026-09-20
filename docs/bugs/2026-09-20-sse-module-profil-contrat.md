# Module profil SSE — variables et préréglage hors contrat

Date : 2026-09-20  
Statut : corrigé (Overwatch 1.6.2, pack SSE 0.7.22)

## Contexte

Le chef de mission peut renseigner l’identité SEEK de deux façons : attributs sur la personne, ou module **Profil d’identité SSE** (plusieurs sujets synchronisés). Le pack SSE autonome a ses propres attributs et son propre terminal.

## Symptôme

Poser le module Overwatch et choisir « Recherché » (ou saisir un nom) ne donnait pas le même résultat que de le faire dans les attributs de la personne. Le terminal SEEK Overwatch ignorait le module. Le pack SSE autonome n’avait pas non plus ce contrat : son terminal n’affichait pas le nom ni le verdict imposés dans l’éditeur.

## Cause

Les attributs d’unité Overwatch écrivaient `COMSPEC_SSE_LastName` (et appelaient la génération de profil pour le préréglage). Le module écrivait `LastName` / `Preset` sur la logique du module, sans passer par le même contrat. SEEK Overwatch ne lit que les variables préfixées sur la personne. Le pack SSE lisait ces variables pour le nom, mais n’écrivait pas le préréglage depuis ses attributs et n’en tenait pas compte à la requête.

## Correctif

Une fonction unique sert aux deux saisies Overwatch (`sseEdenWriteField`). Sur une personne, elle écrit tout de suite les variables lues par SEEK, y compris le préréglage. Sur le module, elle mémorise le même contrat ; au lancement, le module recopie le profil sur les sujets synchronisés. Le mât Relais AT n’a plus de zone redimensionnable sans effet.

Dans le pack SSE, le même contrat est tenu par `edenWriteField` : nom, alias et verdict (Signalé / Recherché) s’écrivent dans les attributs SSE. La requête SEEK du pack reprend ce verdict. Si Overwatch et le pack sont chargés ensemble, Overwatch s’appuie sur la fonction du pack.

## Fichiers touchés

- `connect/functions/fn_sseEdenWriteField.sqf`
- `connect/functions/fn_moduleSseProfile.sqf`
- `connect/modules/module_sse.hpp`
- `connect/modules/eden_sse_attributes.hpp`
- `connect/modules/module_atak_relay.hpp`
- `connect/config.cpp`
- `mod/@COMSPEC_SSE/addons/eden/functions/fn_edenWriteField.sqf`
- `mod/@COMSPEC_SSE/addons/eden/config.cpp`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_identifySubject.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_applyAuthoredContent.sqf`

## Vérification

Overwatch 1.6.2 / 1.6.3 : module Profil d’identité SSE sur un civil, choix « Recherché », nom renseigné. SEEK Overwatch affiche ce nom et la correspondance confirmée.

Pack SSE 0.7.22 : attributs COMSPEC SSE, même saisie. Le terminal SEEK du pack affiche le même nom et le même verdict, avec ou sans Overwatch.

## Statut

corrigé
