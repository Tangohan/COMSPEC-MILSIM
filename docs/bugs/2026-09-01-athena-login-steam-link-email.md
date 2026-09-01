# 2026-09-01 — Connexion Athena en jeu sans Steam déjà enregistré

## Contexte

Un opérateur se connecte à Athena depuis Overwatch avec son e-mail. Son compte n’a pas encore d’identifiant Steam. La session du jeu connaît pourtant Steam (partie multijoueur).

## Symptôme

La connexion échouait ou n’associait pas Steam. L’opérateur n’était pas prévenu. L’encadrement n’était pas informé.

## Cause

La session pouvait être ouverte sans Steam, mais l’identifiant envoyé par le jeu n’était pas enregistré sur le compte. Aucun courriel n’était envoyé. L’écran prêt affichait « Steam lié » même quand ce n’était pas le cas.

## Correctif

Après une connexion par e-mail ou par code, si le jeu envoie un identifiant Steam valide et que le compte n’en a pas encore, il est associé au compte et à la fiche. L’opérateur voit un message à l’écran et reçoit un courriel. L’encadrement (administrateurs de la communauté) reçoit aussi un courriel. Si l’identifiant appartient déjà à un autre compte, la session reste ouverte, l’association est refusée, et les deux parties sont informées. Un identifiant déjà présent n’est jamais écrasé.

## Fichiers touchés

- `app/Services/Game/GameAuthService.php`
- `app/Repositories/AthenaAccountRepository.php`
- `app/Services/Email/EmailEvents.php`
- `app/Controllers/Web/AccountController.php`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_authStateCells.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_pollAuth.sqf`
- `app/Support/DevDispatchCatalog.php`
- `tests/Unit/GameAuthAssetTest.php`
- `tests/Unit/OverwatchAthenaSteamLinkOnEmailLoginAssetTest.php`
- `tests/Unit/DevDispatchCatalogTest.php`

## Vérification

Tests d’actifs : connexion e-mail sans Steam obligatoire, association après e-mail, courriels membre et encadrement, message à l’écran, catalogue UPDATE 325.

## Statut

Corrigé
