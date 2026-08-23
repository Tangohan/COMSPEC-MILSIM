# ATAK TASK — En cours / Abort / Retour inutilisables

## Contexte

Écran **Ordres reçus** (app TASK). L’opérateur ne peut ni valider « En cours »
ou « Abort », ni cliquer sur Retour. La zone de détail reste vide. Le bandeau
bas affiche plusieurs libellés superposés (Enter, Retour, Live Feed).

## Symptôme

- Clics sur En cours / Abort sans effet, ou refus silencieux.
- Bouton Retour du bandeau ATAK injoignable.
- Détail de l’ordre sélectionné vide.
- Superposition visuelle des boutons (reste de « Refuser » derrière Abort).

## Cause

1. La page TASK dépassait la hauteur du groupe d’application (~8,7 unités) et
   recouvrait le bandeau bas (Retour / Enter / Live Feed).
2. Accepter et En cours partageaient le même emplacement ; Refuser et Abort
   aussi. Un clic touchait le mauvais contrôle.
3. TASK héritait du menu Messages : Enter et Live Feed restaient visibles.

## Correctif

- Mise en page compacte, entièrement au-dessus du bandeau Retour.
- Deux boutons d’action distincts (gauche / droite), jamais superposés.
- Bandeau bas : un seul bouton Retour.
- Détail d’ordre toujours rempli à la sélection, avec la prochaine action.

## Fichiers touchés

- `atak_athena/ui/task_page.hpp`
- `atak_athena/functions/fn_athena_taskSyncButtons.sqf`
- `atak_athena/functions/fn_athena_taskSelect.sqf`
- `atak_athena/functions/fn_athena_taskClick.sqf`
- `atak_athena/functions/fn_athena_taskFooter.sqf`
- `atak_athena/functions/fn_athena_taskOnOpened.sqf`
- `atak_athena/config.cpp` (1.0.41)

## Vérification

1. Rebuild `atak_athena.pbo`, quitter Arma, relancer.
2. Ouvrir TASK : bandeau bas = **Retour** seul, cliquable.
3. Ordre « Remis » : **Accepter** / **Refuser**, détail visible.
4. Après acceptation : **En cours** / **Interrompre** répondent.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
