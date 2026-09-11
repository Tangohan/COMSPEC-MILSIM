# Tchat jeu ↔ web coupé (quiet handshake + poll trop strict)

## Contexte

11 septembre 2026. Après les correctifs 401 / READY trompeur. Pack Overwatch 1.5.32 / liaison 2.0.24. L’opérateur signale que le tchat en jeu ne remonte plus sur le poste, et inversement.

## Symptôme

- Messages de groupe / journal radio du téléphone absents du journal radio web.
- Messages envoyés depuis le poste absents du téléphone / Group Messages.
- La position pouvait encore remonter alors que le fil radio restait muet.

## Cause

1. `fn_sendIntel` refusait tout envoi (dont le chat) tant que `COMSPEC_HandshakeQuiet` était actif (~20 s après READY). Les messages partaient en silence côté jeu.
2. `fn_pollChatMessages` exigeait `canTransmit(true)` (mode full) : un ATAK en mode « position seule » (écran dégradé) ne relisait plus le journal TOC.
3. Un `AuthInvalidated` pendant le quiet était ignoré sans planifier de rouverture → canal potentiellement figé sans retry.
4. Les 401 sur `/api/chat` n’étaient pas traités comme sensibles côté DLL (pas de reprise de session).

Note : le chat natif d’Arma (bandeau gauche) n’est plus alimenté volontairement depuis le 1er septembre — le fil reste dans le téléphone et le journal radio du poste.

## Correctif

- Lever le blocage quiet sur `sendIntel` (le quiet ne sert plus qu’à ignorer les AuthInvalidated précoces).
- Poll chat en `canTransmit(false)` (lecture autorisée dès que la liaison n’est pas coupée).
- Fin de quiet : relancer les boucles / rouverture canal si besoin.
- AuthInvalidated pendant quiet : rouverture planifiée après stabilisation.
- DLL 2.0.25 : `/api/chat` auth-sensitive + refus d’envoi sans jeton/clé.

## Fichiers touchés

- `mod/UptoDate/Sources/.../fn_sendIntel.sqf`
- `mod/UptoDate/Sources/.../fn_pollChatMessages.sqf`
- `mod/UptoDate/Sources/.../fn_waitAthenaReady.sqf`
- `mod/UptoDate/Sources/.../fn_extensionCallback.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/.../connect/config.cpp` (1.5.33)
- `app/Support/DevDispatchCatalog.php` (UPDATE #495)

## Vérification

Pack Overwatch **1.5.33** + liaison **2.0.25**. Après liaison : message Group Messages → visible sur le poste ; message journal radio web → visible dans le téléphone. Pas de silence prolongé juste après « Session Athena prête ».

## Statut

corrigé (pack à recharger — quitter Arma complètement)
