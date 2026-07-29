# Bug — page État ATAK sans scroll

## Contexte

Écran `État ATAK` dans l’ATAK in-game.

## Symptôme

- les informations en bas de la fiche étaient coupées
- impossible de défiler pour voir certificat, terminal, chiffrement intel et autres lignes tardives

## Cause

- le bloc principal était un simple `RscStructuredText` avec une hauteur fixe
- aucune `ControlsGroup` scrollable n’enveloppait le contenu

## Correctif

- remplacement du bloc de détail par une zone scrollable (`RscControlsGroup`)
- recalcul dynamique de la hauteur du texte via `ctrlTextHeight`

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/status_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateStatus.sqf`

## Vérification

- contrôle de structure UI
- contrôle du recalcul de hauteur du contenu

## Statut

`corrigé à vérifier en jeu`
