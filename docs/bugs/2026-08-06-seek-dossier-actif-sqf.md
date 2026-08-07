# SEEK — erreur SQF au clic « DOSSIER ACTIF »

## Contexte

Bouton **DOSSIER ACTIF** du terminal SEEK (`display_sse_person.hpp`).

## Symptôme

Clic → boîte d’erreur Arma : `Error Nombre incorrect dans une expression`, curseur sur `\'set\'`.

## Cause

Dans l’attribut `action`, la chaîne était écrite `[\'set\', …]`. Les antislashs sont invalides en SQF / config ; le parseur casse sur `\'`.

## Correctif

Remplacer par `['set', …]` (même convention que les autres `action` du dialog).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp`

## Vérification

- Recompiler le PBO `connect`, ouvrir une fiche SEEK → page Dossier → **DOSSIER ACTIF** sans erreur.

## Statut

corrigé
