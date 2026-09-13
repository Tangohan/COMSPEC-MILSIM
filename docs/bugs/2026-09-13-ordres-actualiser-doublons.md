# Ordres reçus — Actualiser dupliquait les entrées

## Contexte

Application **Ordres reçus** (TASK) sur le téléphone ATAK. Bouton **Actualiser** + demande d’un bouton pour vider l’historique.

## Symptôme

Après **Actualiser**, la liste affichait plusieurs fois le même ordre (ex. « Refusé · SSE · YA1 / Bravo » × N), alors que le résumé indiquait 8 ordres / 0 à traiter.

## Cause

La fusion `GetOrders` → `COMSPEC_Orders` indexait les ordres par identifiant sans forcer le type **chaîne**. Un même ordre déjà en mémoire (id numérique ou déjà présent en double) n’était pas reconnu au refresh et était **ré-ajouté**. Les doublons déjà présents n’étaient jamais compactés.

## Correctif

- `fn_pollOrders.sqf` : ids toujours en chaîne, index HashMap unique, reconstruction de la liste sans doublons, respect de la liste des ordres retirés.
- `fn_receiveOrder.sqf` / `fn_athena_updateTask.sqf` : même normalisation + filet anti-doublon à l’affichage.
- `task_page.hpp` + `fn_athena_taskRespond.sqf` : bouton **Vider l’historique** (retire les ordres clos et les bloque au prochain Actualiser pour la session).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollOrders.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateTask.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_taskRespond.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/task_page.hpp`

## Vérification

Pack Overwatch **1.5.62** · Athena **1.0.112**. Quitter Arma. Ordres reçus → Actualiser plusieurs fois → une seule ligne par ordre. Vider l’historique → les refusés / terminés disparaissent et ne reviennent pas au refresh.

## Statut

corrigé
