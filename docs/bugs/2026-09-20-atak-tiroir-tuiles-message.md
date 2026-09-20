# ATAK — tuiles du chevron de nouveau cassées

Date : 2026-09-20  
Statut : corrigé (Athena 1.0.157)

## Contexte

Correctif de démarrage Athena 1.0.156 (application réseau local). Capture du tiroir d’applications juste après rebuild.

## Symptôme

Chevron ouvert : Video Feeds collé en haut, Quick Pictures / Photo Library / Groups / Route décalés, libellés qui se chevauchent. Les applications Athena n’apparaissent pas clairement. Même famille que le tiroir du 19 septembre.

## Cause

Pour faire démarrer l’application réseau local, on a rouvert l’application Messagerie IceMan afin d’y déclarer une classe interne. IceMan pose toutes les tuiles sur ce modèle. Le rouvrir sans son icône d’origine casse la grille (icônes empilées, clics qui visent la mauvaise case).

## Correctif

Ne plus toucher à l’application Messagerie IceMan. L’application réseau local recopie elle-même l’ordre d’affichage et l’écran IceMan, comme les autres applications du téléphone. Le téléphone démarre toujours.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.157. Chevron : grille régulière, Video Feeds à sa place, applications Athena visibles. Pas de fenêtre d’erreur au chargement. Réseau local toujours accessible depuis Messagerie.

## Statut

corrigé
