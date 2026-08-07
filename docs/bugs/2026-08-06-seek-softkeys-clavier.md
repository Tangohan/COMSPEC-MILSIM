# SEEK — softkeys A1/A2/QUERY/SIGN sur le clavier + chevauchement TRANSMETTRE

## Contexte

Terminal biométrique SEEK (`display_sse_person.hpp`, idd 9991) : touches physiques et page Dossier.

## Symptôme

- Les libellés **A1 / A2 / QUERY / SIGN** formaient une barre bleue au milieu du **clavier QWERTY** de l’illustration.
- Sur la page Dossier, **TRANSMETTRE / ANNULER** collaient la flèche de navigation (petit bouton parasite en bas à gauche de l’écran).

## Cause

- Softkeys placées à `y = 0.530 * SEEK_H` avec `h = 0.060 * SEEK_H` (bande clavier `0.516–0.609`), trop hautes et trop basses.
- Boutons de transmission à `NAV_Y - 0.023` alors que `◄` est à `NAV_Y` → quasi-chevauchement.

## Correctif

- Softkeys déplacées **sous l’écran** (`y ≈ 0.348`), alignées sur la largeur LCD, taille réduite.
- **TRANSMETTRE / ANNULER** remontés à `ROW(4)` ; navigation libre en bas d’écran.
- Flèches libellées `<<` / `>>`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp`

## Vérification

- Recompiler / recharger le PBO `connect`, ouvrir une fiche SEEK page Dossier.
- Softkeys visibles entre écran et clavier, clavier libre.
- TRANSMETTRE au-dessus de `<<`, sans chevauchement.

## Statut

corrigé
