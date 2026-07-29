# Capture recon — variable _zone indéfinie

## Contexte

Envoi d’une photo de recon depuis le jeu (capture ATAK / Photo Library).

## Symptôme

```
fn_captureReconImage.sqf, line 53: Error Variable indéfinie dans une expression: _zone
```

La capture échoue et la photo ne remonte pas vers Athena.

## Cause

`_zone` était déclarée avec `private` dans un bloc `if` roleplay, puis utilisée dans une branche `else` profondément imbriquée. En SQF Arma, la portée des `private` dans les blocs `else` ne voit pas toujours les variables du même bloc parent.

Un second cas a aussi été observé après correction du script : le chemin BCE pouvait pointer vers un dossier mod tiers `Screenshot\` (singulier) ou vers un fichier encore en cours d’écriture, ce qui provoquait `ERR|file_not_found`.

## Correctif

Déclaration de `_zone` au niveau fonction (avant la chaîne if/else), initialisation via `getPlayerRoleplayZone` quand le roleplay est actif.

Côté extension `.NET`, la résolution d’image attend désormais plus longtemps la fin d’écriture du fichier et cherche aussi les dossiers `Screenshot` en plus de `Screenshots`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

Recompiler le mod, capturer une photo en jeu avec roleplay actif : plus d’erreur script `_zone`, et les captures BCE / fallback native sont retrouvées même quand elles transitent par un dossier `Screenshot`.

## Statut

Corrigé
