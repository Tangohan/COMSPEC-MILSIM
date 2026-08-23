# Dossier documentaire — identifiant illisible

## Contexte

Capture in-game d’un dossier « Pièces saisies sur le terrain » : les pièces
affichaient `SSE-DOC-1.14314e+09-0`.

## Symptôme

Référence interne en notation scientifique, illisible pour l’opérateur.

## Cause

`format ["SSE-DOC-%1-%2", _seed, _i]` avec un grand entier SQF.

## Correctif

Jeton via `idToken` (toFixed) ; masquage des identifiants `e+` dans la
visionneuse. Eden permet désormais de rédiger chaque pièce.

## Fichiers touchés

- `generator/functions/fn_generateDocument.sqf`
- `ui/functions/fn_fillResultDialog.sqf`
- attributs Eden documents / téléphone / ordinateur

## Vérification

Relancer Arma avec SSE 0.7.8. Ouvrir un dossier : plus de `e+`. Dans Eden,
catégorie « COMSPEC SSE — Documents ».

## Statut

Corrigé.
