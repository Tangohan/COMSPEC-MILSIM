# Bug — gel violent à la prise de photo ATAK

## Contexte

Capture photo via l’app Photos / BCE / Quick Picture sur ATAK Enhanced, avec remontée automatique vers Athena.

## Symptôme

Le jeu se fige plusieurs secondes au moment de la prise de cliché ou juste après (freeze perceptible, parfois 5–7 s).

## Cause

- `ResolveLocalImagePath` dans `COMSPECExtension` : boucle **28 × `Thread.Sleep(250)`** (jusqu’à ~7 s) sur le thread `callExtension`, ce qui bloque Arma en synchrone.
- À chaque tentative : `FindNewestScreenshot` / `FindScreenshotByFileName` avec **`SearchOption.AllDirectories`** sur tous les fichiers image des dossiers Screenshots — scan disque très coûteux, répété à chaque retry.
- Côté SQF : attente **2,2 s** avant upload auto (`bce_took_screenshot`) puis nouvel appel synchrone `UploadReconImage`, cumulant le blocage.

## Correctif

- **Extension** : résolution immédiate si le fichier existe ; attente réduite à **8 × 80 ms** (~640 ms max) ; recherche limitée au niveau racine des Screenshots (`EnumerateRecentImagesInDir`) ; scans profonds uniquement en secours pour `FindScreenshotByFileName`.
- **SQF** : délai pré-upload réduit (0,5 s + retry 0,8 s) ; repli capture native raccourci (0,5 s).
- Version connect : **1.4.11**.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

- Rebuild `COMSPECExtension_x64.dll` + PBO `atak_athena` / `connect`.
- En jeu : prendre une photo ATAK → pas de freeze multi-secondes ; photo visible sur le portail sous quelques secondes.
- Cas limite : gros PNG BCE encore en écriture → retry auto ou poll Iceman sans gel prolongé.

## Statut

`corrigé à vérifier en jeu`
