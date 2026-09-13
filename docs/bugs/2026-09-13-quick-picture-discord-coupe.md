# Quick Picture — l’envoi vers Discord était coupé

## Contexte

13 septembre 2026. Quick Picture transmet la vue vers Discord (bot du pack BCE), en plus du poste. Overwatch ne doit pas interrompre cet envoi.

## Symptôme

Une photo prise depuis Quick Picture n’arrive plus sur Discord, alors que le relais fonctionnait sans Overwatch. Le poste peut encore recevoir une vue.

Journal côté Discord :

`DirectoryNotFoundException` sur un chemin collé :
`…\Arma 3!Workshop@# S.O.A.R - FN + CHR + OBJ\Screenshot\2026_09_13_….jpg`

Le dossier réel est :
`…\Arma 3\!Workshop\@# S.O.A.R - FN + CHR + OBJ\Screenshot\`

## Cause

Deux problèmes se cumulaient.

1. Au moment du cliché, Overwatch prenait une seconde photo Arma et changeait la caméra autour de la capture BCE. C’est cette capture BCE qui alimente Discord. Le second cliché la bloquait.

2. Même avec le parcours BCE rétabli, Discord ouvre le dossier **annoncé** par l’extension de capture. Celle-ci colle la racine du jeu, `!Workshop` et le nom du mod **sans séparateurs**. Discord cherche donc un dossier qui n’existe pas. Overwatch, lui, retrouve souvent le fichier par son nom : le poste peut marcher, Discord non.

Ce relais Discord appartient au pack BCE, pas à IceMan. Overwatch ne peut pas modifier DiscordMessageAPI : il doit lui donner un dossier réel.

## Correctif

- Le cliché Quick Picture reprend le parcours BCE, sans second screenshot Arma ni changement de caméra.
- Avant le cliché, Overwatch indique le dossier Screenshot réel du pack (sous `!Workshop`).
- Après le cliché, le chemin renvoyé est réparé (`Arma 3!Workshop@` → `Arma 3\!Workshop\@`) avant l’événement qui alimente Discord.
- Overwatch se contente d’envoyer au poste la vue déjà prise.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_TakePicture.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bceScreenShot.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

Pack Overwatch 1.5.75. Quitter Arma complètement. Prendre une photo Quick Picture : elle arrive sur Discord et au poste. Le journal Discord ne doit plus montrer `DirectoryNotFoundException` sur `Arma 3!Workshop@`.

## Statut

Corrigé
