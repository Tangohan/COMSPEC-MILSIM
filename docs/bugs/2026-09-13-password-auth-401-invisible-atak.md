# Auth mot de passe — 401 et opérateur invisible sur ATAK

**Date :** 2026-09-13  
**Statut :** corrigé (même vague que messagerie 1.5.68)

## Contexte

Après connexion Athena par e-mail / mot de passe, l’opérateur n’apparaissait pas sur la carte du poste (« Pas encore vu ») alors qu’Appairer fonctionnait.

## Symptôme

Rafales `401` sur position, marqueurs, photos ; `Session refusée par le poste`.

## Cause

La réponse d’auth jeu ne incluait pas `api_key` (présent après Appairer). La DLL vidait la clé communauté et n’envoyait que le Bearer.

## Correctif

- `GameAuthService` : inclure `api_key` (+ `tenant_id`) comme pour l’appairage.
- `GameAuth.cs` : appliquer `api_key` du payload au lieu de l’effacer.
- `AttachApiKeyHeader` : Bearer + miroir `X-ATAK-TOKEN` + `X-COMSPEC-KEY` si clé connue.

## Fichiers

Voir `docs/bugs/2026-09-13-messagerie-suppression-canal-401.md`.

## Vérification

Connexion mot de passe → position / messagerie OK sans 401 en rafale. Appairer reste valide.
