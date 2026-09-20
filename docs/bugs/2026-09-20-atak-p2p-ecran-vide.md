# ATAK — P2P réseau local écran vide

Date : 2026-09-20  
Statut : corrigé (Athena 1.0.159)

## Contexte

Tuile **P2P — Réseau** dans le chevron du téléphone, à côté de Message.

## Symptôme

Ouvrir P2P affiche un écran vide. Pas de contacts, pas de messages.

## Cause

La tuile P2P ouvrait un second écran calqué sur la messagerie locale, au lieu de l’écran réseau déjà fourni. Cet écran parallèle n’est pas alimenté : liste et contacts restent masqués.

## Correctif

P2P ouvre l’écran réseau local d’origine (contacts et messages). La tuile reste dans le chevron, sans toucher à l’application Message.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_messageHubOpenP2P.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_p2pOnOpened.sqf`

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.159. Chevron → P2P : contacts du réseau local visibles, pas un écran blanc.

## Statut

corrigé
