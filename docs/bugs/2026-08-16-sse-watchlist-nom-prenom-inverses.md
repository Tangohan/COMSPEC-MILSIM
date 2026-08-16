# Croisement surveillance — inversion nom / prénom

## Contexte

Liste de surveillance SSE (`/atak/sse/croisements`) : entrée Nom + Prénom + Alias.

## Symptôme

Une fiche terrain avec nom/prénom inversés par rapport à la liste ne déclenchait
pas (ou faiblement) de correspondance.

## Cause

`SseCrossMatchService::score` ne comparait que l’ordre saisi (nom↔nom, prénom↔prénom).

## Correctif

Double scoring : ordre normal et ordre inversé ; le meilleur score est retenu, avec
motifs du type « Nom identique (ordre inversé) » / « Nom et prénom intervertis ».
Aide sous le formulaire d’ajout d’entrée.

## Fichiers touchés

- `app/Services/Sse/SseCrossMatchService.php`
- `views/atak/sse/cross.php`

## Vérification

1. Liste : Nom=`Jawadi`, Prénom=`Khalil`.
2. Fiche : Nom=`Khalil`, Prénom=`Jawadi` → correspondance ≥ seuil, motif inversion.
3. Alias seul inchangé.

## Statut

corrigé
