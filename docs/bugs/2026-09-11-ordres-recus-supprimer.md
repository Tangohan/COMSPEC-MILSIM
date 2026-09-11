# Ordres reçus — pas de suppression / boutons faux

## Contexte

11 septembre 2026. Écran TASK « Ordres reçus » sur ATAK. Ordre « Refusé » sélectionné : Accepter/Refuser encore visibles, aucune action utile, pas de moyen de retirer l’ordre.

## Symptôme

- Accepter / Refuser semblent ne rien faire sur un ordre déjà clos
- Impossible de supprimer / nettoyer la liste

## Cause

Pour les états FAILED / CANCELLED / DONE, les boutons étaient masqués (ou restaient sur le libellé HPP Accepter/Refuser). Aucune action DISMISS. `DONE` n’était pas accepté par `updateOrderStatus`.

## Correctif

- Bouton **Supprimer** (DISMISS) sur ordres clos — retrait local + filtre poll
- **Terminer** pendant EXEC
- Messages d’erreur plus clairs
- `DONE` autorisé côté mise à jour de statut

## Fichiers touchés

- `fn_athena_taskSyncButtons.sqf`, `fn_athena_taskRespond.sqf`, `fn_athena_updateTask.sqf`, `fn_athena_taskSelect.sqf`
- `fn_updateOrderStatus.sqf`, `fn_orderInboxOnLoad.sqf`, `fn_pollOrders.sqf`

## Vérification

Pack **1.5.42**. Ordre refusé → Supprimer retire la ligne. Ordre à traiter → Accepter / Refuser. En cours → Terminer.

## Statut

corrigé — Overwatch 1.5.42
