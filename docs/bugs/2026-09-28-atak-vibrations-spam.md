# ATAK — vibrations trop fréquentes

**Date :** 2026-09-28  
**Statut :** corrigé

## Contexte

Le téléphone vibrait en rafale (proximité téléphone, IFF, roleplay silencieux, ordres).

## Symptôme

Buzz répétés (souvent ×2 ou ×3) à quelques dixièmes de seconde, surtout en zone dense.

## Cause

Chaque alerte jouait plusieurs `playSoundUI` sans cooldown global partagé.

## Correctif

- `playAtakVibrate` : 1 bip, cooldown 10 s (forcé pour commande TOC).
- Proximité téléphone / IFF : cooldown global 12 s + une seule vibration.
- Roleplay « silencieux — vibration » passe par le même garde-fou.

## Fichiers touchés

- `fn_playAtakVibrate.sqf`, alertes proximité, `fn_athena_onVibrate`, `fn_receiveOrder`, `fn_playAtakEnhancedSound`

## Vérification

Entrer/sortir d’un rayon suivi : au plus une vibration toutes les ~10–12 s. Vibration TOC forcée toujours audible.

## Statut

Corrigé.
