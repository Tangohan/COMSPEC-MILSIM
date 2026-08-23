# Bureau ATAK — icônes Athena / Photos / État / Briefing

## Contexte

Le bureau de la tablette ATAK Enhanced affichait trop de raccourcis, dont plusieurs doublons ou peu utilisés.

## Symptôme

Rangée d’icônes : Athena (messagerie), Photos Athena, État ATAK, Briefing, en plus de Connexion, urgences, mobile, ordres, sons et resynch.

## Cause

`fn_athena_installDesktopShortcut.sqf` enregistrait tous ces raccourcis sur le Desktop cTab.

## Correctif

Retrait des quatre raccourcis demandés. Restent : Connexion Athena, Messages d’urgence, Liaison mobile, Ordres, Sons, Resynch.

## Fichiers touchés

- `atak_athena/functions/fn_athena_installDesktopShortcut.sqf`
- `atak_athena/config.cpp` (1.0.37)

## Vérification

Ouvrir ATAK Enhanced (Desktop) : les quatre icônes absentes, les six autres alignées.

## Statut

Corrigé (à valider in-game après relance Arma).
