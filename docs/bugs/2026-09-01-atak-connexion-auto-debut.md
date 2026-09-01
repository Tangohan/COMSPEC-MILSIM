# Connexion Athena qui s’ouvre toute seule en début de mission

## Contexte

Mission Arma avec Overwatch. L’opérateur n’a pas encore ouvert le téléphone.

## Symptôme

La grande fenêtre « Connexion à Athena » s’affichait dès le lancement, avant même d’ouvrir l’ATAK.

## Cause

Si la session enregistrée n’était pas restaurée, le jeu ouvrait immédiatement l’écran de connexion. La déconnexion rouvrait aussi cette fenêtre.

## Correctif

Plus d’ouverture automatique. L’écran de connexion s’ouvre seulement depuis la tuile **Connexion Athena** du bureau du téléphone. Une session déjà enregistrée continue de se relier toute seule, sans fenêtre.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_restoreSession.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_openLogin.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_logout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_waitAthenaReady.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installDesktopShortcut.sqf`

## Vérification

Tests d’assets. En jeu (pack 1.5.4) : la mission démarre sans écran de connexion. Téléphone → tuile Connexion Athena → l’écran s’ouvre. Après connexion, le suivi reprend.

## Statut

corrigé
