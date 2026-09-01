# 2026-09-01 — Spam photos NotifyNewPhoto (not_connected)

## Contexte

Journal Overwatch 15:32–15:34. Session Athena se rouvre (`Session Athena prête`, handshake ok), puis le journal se remplit de `NotifyNewPhoto` / `ERR|not_connected` plusieurs fois par seconde, avec un nouveau fichier `COMSPEC_xxx.png` toutes les ~3 s.

## Symptôme

Les photos partent en boucle tant que la liaison n’est pas jugée « connectée ». Même après la session prête, chaque tentative échoue encore. Le journal et le dossier Screenshots saturent.

## Cause

1. L’envoi photo refusait tout ce qui n’avait pas une ancienne clé de liaison, alors que la session Athena (jeton de connexion) suffit désormais.
2. Un échec relançait un **nouveau** cliché (`skipArmaShot=false`). Ce cliché échouait, relançait un retry avec un nouveau PNG, et ainsi de suite.

## Correctif

- L’envoi photo accepte la session Athena, pas seulement l’ancienne clé.
- Pas de cliché tant que la session n’est pas prête (un message toutes les 30 s).
- Un retry ne recliche plus : il renvoie le même fichier, une seule fois.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `tests/Unit/OverwatchPhotoNotConnectedSpamAssetTest.php`

## Vérification

Tests assets (session Athena acceptée, plus de recliché `skipArmaShot=false` sur retry). Recette : relancer Arma avec le pack reconstruit, se connecter, prendre **une** photo — un seul envoi, pas de rafale `not_connected`.

## Statut

Corrigé (pack jeu à reconstruire pour la DLL et le module connect)
