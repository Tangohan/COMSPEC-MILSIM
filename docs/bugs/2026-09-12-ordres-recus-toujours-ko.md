# Ordres reçus — toujours KO (détail vide, boutons faux)

## Contexte

12 septembre 2026. Écran TASK « Ordres reçus » sur ATAK (pack ≥ 1.5.43). Malgré le correctif « Supprimer » du 11/09, l’opérateur voit encore une zone de détail vide et Accepter / Refuser sur un ordre déjà « Refusé ».

## Symptôme

- Sélection d’un ordre : grande zone sombre sans texte de détail
- Ordre « Refusé » sélectionné : boutons Accepter / Refuser encore affichés (au lieu de Supprimer)
- Accepter / Refuser semblent ne rien faire sur « À traiter »
- Actualiser ne remet pas les actions dans le bon état

## Cause

Plusieurs défauts se cumulaient :

1. **Liste trop haute** dans `task_page.hpp` : la zone vide de la ListBox occupait presque tout l’espace jusqu’aux boutons, ce qui donnait l’impression que le détail (contrôle 9903, fond quasi identique) était vide ou absent.
2. **Sélection fragile** : le garde `COMSPEC_ATAK_Task_rebuilding` pouvait faire sortir `taskSelect` sans écrire le détail ni synchroniser les boutons ; les boutons restaient alors sur les libellés HPP (Accepter / Refuser) **sans** variable d’action → clics sans effet.
3. **Identifiants** : comparaisons d’id parfois non normalisées en chaîne pour `lbSetData` / lookup.
4. Après une réponse, le rafraîchissement UI dépendait trop du seul `onLBSelChanged`.

## Correctif

- Layout : liste raccourcie, détail plus grand et contrasté, texte placeholder
- `taskSelect` : ne bloque plus les sélections valides pendant rebuild ; écrit toujours un détail ; normalise les id
- `taskSyncButtons` / `taskClick` : repli depuis la liste ; actions peintes selon l’état (Supprimer / Terminer / Accepter…)
- `updateTask` : force un appel `taskSelect` après rebuild ; id en chaîne
- `taskRespond` + `updateOrderStatus` : id normalisés ; DISMISS filtre le poll
- Pack **Overwatch 1.5.53 · Athena 1.0.98**

## Fichiers touchés

- `atak_athena/ui/task_page.hpp`
- `fn_athena_taskSelect.sqf`, `fn_athena_taskSyncButtons.sqf`, `fn_athena_taskClick.sqf`
- `fn_athena_updateTask.sqf`, `fn_athena_taskRespond.sqf`, `fn_athena_taskOnOpened.sqf`
- `fn_updateOrderStatus.sqf`, `fn_orderCanTransition.sqf`, `fn_pollOrders.sqf`

## Vérification

1. Quitter Arma complètement, recharger le pack 1.5.53.
2. Ouvrir Ordres reçus avec au moins un ordre « À traiter » et un « Refusé ».
3. Sélectionner chaque ligne → le détail (émetteur, priorité, corps) doit s’afficher.
4. Sur « À traiter » : Accepter puis Refuser sur un autre → l’état change, les boutons suivent.
5. Sur « Refusé » / clos : bouton **Supprimer** → la ligne disparaît ; Actualiser ne la ramène pas dans la session.

## Statut

corrigé — Overwatch 1.5.53 · Athena 1.0.98
