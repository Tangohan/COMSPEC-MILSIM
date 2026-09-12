# 2026-09-12 — Steam toujours « non associé » sur Athena

## Contexte

Écran Athena du téléphone ATAK : compte trouvé / session prête, mais badge rouge « Steam non associé ». Parfois « associé » un instant (canal ouvert), puis retour à non associé. Connexion e-mail / Appairer / bouton Steam concernés.

## Symptôme

- Badge Steam reste « non associé » après connexion e-mail réussie.
- L’état ne persiste pas après reprise de liaison.
- Bouton Steam refuse encore « non associé » alors que le portail a un Steam, ou l’inverse : session OK sans Steam rattaché.

## Cause

1. **E-mail / OTP sans SteamID** : `AuthPassword` / `VerifyOtp` envoyaient le Steam mémorisé dans l’extension, souvent vide (pas de `SetSteamId` avant). L’association serveur (`attachSteamFromEmailLogin`) ne recevait rien.
2. **Flag `steam_linked` trop étroit** : calculé seulement sur le Steam de la requête / `linked_now`, pas sur le Steam déjà présent en fiche Effectifs ou compte Athena. Bootstrap sans `notices` ne renvoyait pas l’état.
3. **UI trompeuse** : si `COMSPEC_SteamLinked` n’était pas encore défini, le panneau prenait l’état « compte prêt » pour afficher associé, puis le poll le remettait à non associé.
4. **Restore trop strict** : une session e-mail sans Steam enregistré échouait en `STEAM_NOT_LINKED` dès qu’un UID client était présent sans liaison préalable.

## Correctif

- SQF + extension : transmettre `getPlayerUID` à chaque connexion e-mail / code.
- Portail : `steam_linked` d’après compte + fiche ; `notices` aussi sur bootstrap ; Appairer écrit aussi la fiche Effectifs ; restore autorise la reprise sans Steam et rattache si libre.
- Extension : si bootstrap n’a pas de notices mais le compte a un Steam, badge = associé.
- Panneau : ne plus déduire « associé » du seul fait que le compte est prêt.

## Fichiers touchés

- `app/Services/Game/GameAuthService.php`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/COMSPECExtension/Extension.cs` (version)
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_submitPassword.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_submitOTP.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_authAction.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_authFocus.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_homeAction.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `app/Support/DevDispatchCatalog.php`
- `tests/Unit/GameAuthAssetTest.php`
- `tests/Unit/OverwatchAthenaSteamLinkOnEmailLoginAssetTest.php`

## Vérification

- PHPUnit : `GameAuthAssetTest`, `OverwatchAthenaSteamLinkOnEmailLoginAssetTest`, `DevDispatchCatalogTest`.
- En jeu (multijoueur) : connexion e-mail → badge Steam associé ; quitter / rejoindre → badge conservé ; bouton Steam OK si compte lié.

## Statut

Corrigé (pack Overwatch 1.5.51 · Athena 1.0.95 · liaison 2.0.31)
