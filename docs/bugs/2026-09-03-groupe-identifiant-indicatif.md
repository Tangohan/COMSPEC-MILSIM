# Identifiant de groupe = nom de profil au lieu de l’indicatif

## Contexte

En jeu, le panneau « Données techniques » propose le champ Identifiant du groupe. L’opérateur a un indicatif sur sa fiche (TA1, etc.).

## Symptôme

Le champ affiche le nom de profil Arma (par exemple NewPl) au lieu de l’indicatif.

## Cause

Arma nomme le groupe d’un chef avec son nom de profil. L’indicatif Athena n’était plus recopié sur ce nom, pour éviter de coller le titre de communauté.

## Correctif

Si l’identifiant est encore le nom de profil, il est remplacé par l’indicatif de l’opérateur. Un nom de groupe déjà choisi (équipe, section) n’est pas écrasé. Le champ Zeus est prérempli de la même façon.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyGroupIdFromCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_fillZeusGroupId.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesInject.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZeusAttributeButtons.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/CfgEventHandlers.hpp`

## Vérification

Ouvrir le panneau Données techniques : Identifiant du groupe = indicatif (pas NewPl). Relancer Arma complètement après le pack Overwatch 1.5.16.

## Statut

corrigé (Overwatch 1.5.16)
