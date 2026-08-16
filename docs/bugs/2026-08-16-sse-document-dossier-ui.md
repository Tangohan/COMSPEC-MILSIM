# Consultation documents SSE — UI type dossier

## Contexte

Lecture de documents terrain : `hint` noir brut (« CONSULTATION SSE ») + liste
répétitive « Photo imprimée / Document secondaire ».

## Correctif

- Dialog `COMSPEC_SSE_ResultDialog` : feuille crème, bandeau classification, ombre.
- `fn_fillResultDialog` : pièces numérotées (titre, résumé, grille, mot de code).
- `FEUILLE` remplace le hint CONSULTER.
- `doReadDocuments` passe les hashmaps `docs` (plus de dump lignes + hint double).
- Générateur : résumés secondaires variés.

## Fichiers

- `addons/ui/dialogs/resultDialog.hpp`
- `addons/ui/functions/fn_showResult.sqf`, `fn_fillResultDialog.sqf`, `fn_resultConsult.sqf`
- `addons/ui/config.cpp`
- `addons/interaction/functions/fn_doReadDocuments.sqf`
- `addons/generator/functions/fn_generateDocument.sqf`

## Vérification

Rebuild PBO `ui` + `interaction` (+ `generator` pour nouveaux docs). Lire documents
sur une cible : feuille dossier, pas de hint noir ; FEUILLE = détail.

## Statut

corrigé en sources — rebuild PBO requis
