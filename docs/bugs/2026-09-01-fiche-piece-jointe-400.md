# Bug — Pièce jointe de fiche de renseignement refusée

## Contexte

1er septembre 2026. Un opérateur envoie une fiche de renseignement avec une photo jointe. La fiche arrive, la pièce non.

## Symptôme

Journal Overwatch :

```
[Tx] OK · Fiche de renseignement — 5 FR-2026-000002
[Tx] OK · Pièce jointe de fiche — queued
[Tx] ÉCHEC · HTTP POST — code 400 · …/sse/notes/5/pieces
```

La fiche est au bureau SSE, sans image.

## Cause

La photo était mise en file alors que le fichier n’était pas encore complètement écrit, ou le contenu partait trop tôt. Le poste recevait la demande sans fichier et la refusait.

## Correctif

- Attendre que la capture soit bien écrite avant de joindre.
- Envoyer le fichier complet, pas un flux encore vide.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteSubmit.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

Tests d’assets fiche + UPDATE 354. Rebuild du pack. En jeu : rédiger une fiche, joindre une photo, valider. La pièce doit s’afficher sur la fiche au poste. Plus de refus juste après « queued ».

## Statut

corrigé (pack à recharger, quitter Arma complètement)
