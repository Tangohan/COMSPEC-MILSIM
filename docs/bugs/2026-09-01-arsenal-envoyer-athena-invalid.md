# Arsenal — Envoyer vers Athena échoue (ERR|invalid)

## Contexte

Bouton Athena de l’arsenal ACE, 2026-09-01 ~17:57. Session Athena ouverte. Toutes les tenues locales (TFR, SOAR, etc.) échouent d’un coup.

## Symptôme

- « Envoyer vers Athena » n’enregistre aucune tenue.
- Le journal se remplit : `SyncWardrobe — invalid` une ligne par tenue, parfois deux salves à quelques secondes d’intervalle.

## Cause

1. L’extension est compilée Native AOT. `JsonSerializer.Serialize` sur un dictionnaire d’objets n’est pas supporté : exception immédiate, renvoyée comme `ERR|invalid`, **sans appel au poste**.
2. Chaque tenue journalisait un échec ERROR. Un second clic relançait toute la liste.

## Correctif

- Construction du message d’envoi sans sérialisation par réflexion.
- Session Athena suffisante (plus d’exigence d’ancienne clé).
- Anti double-envoi ; les échecs de tenues restent en journal technique, plus en ERROR.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/…/connect/functions/fn_arsenalPushAll.sqf`
- `mod/UptoDate/Sources/…/connect/functions/fn_logTransmission.sqf`

## Vérification

- [ ] Pack reconstruit (extension + `connect`)
- [ ] Arsenal → Athena → Envoyer vers Athena : les tenues apparaissent au poste
- [ ] Journal : plus de rafale ERROR SyncWardrobe

## Statut

corrigé — pack jeu à reconstruire
