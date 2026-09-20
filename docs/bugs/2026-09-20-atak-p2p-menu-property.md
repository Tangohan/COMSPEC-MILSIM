# ATAK — classe Menu_Property introuvable au chargement

Date : 2026-09-20  
Statut : corrigé (Athena 1.0.156) ; récidive tuiles traitée en 1.0.157 (`2026-09-20-atak-tiroir-tuiles-message.md`)

## Contexte

Ouverture d’Arma après le rebuild Overwatch 1.6.3. L’addon téléphone Athena se charge avec IceMan / cTab.

## Symptôme

Fenêtre d’erreur Arma 3 : `AtakP2P.Menu_Property: Undefined base class 'Menu_Property'`. Le téléphone ATAK ne démarre pas.

## Cause

L’app P2P héritait de `Menu_Property` alors que cette classe n’était pas déclarée sur l’app Messagerie. Le moteur ne trouve donc pas la classe de base.

## Correctif

Déclarer `Menu_Property` sur l’app Messagerie, puis n’en changer que l’ordre d’affichage pour P2P. Même correction dans les deux blocs de configuration (principal et titres).

## Fichiers touchés

- `atak_athena/config.cpp`

## Vérification

Rebuild Athena 1.0.156. Relancer Arma complètement : plus de fenêtre d’erreur au chargement. Le tiroir d’applications s’ouvre. P2P reste l’écran réseau local IceMan.

## Statut

corrigé
