# Bug — repère web mal placé sur le bureau ATAK

## Contexte

Ajout d’une action `Repère web` pour créer un marqueur compatible site.

## Symptôme

- l’action était placée sur le bureau ATAK
- ce placement obligeait à quitter la carte, donc l’action devenait peu pratique au moment de poser un repère

## Cause

- le raccourci a été ajouté dans la grille du bureau au lieu du panneau Athena utilisé en situation

## Correctif

- suppression du raccourci du bureau
- déplacement de l’action dans le panneau Athena, à côté des actions photo

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installDesktopShortcut.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`

## Vérification

- contrôle visuel du bureau ATAK sans bouton parasite
- contrôle visuel du bouton `Repère web` dans le panneau Athena

## Statut

`corrigé à vérifier en jeu`
