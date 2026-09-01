# 2026-09-01 — Connexion Athena obligatoire dans Overwatch

## Contexte
Le pack Overwatch se connectait avec une adresse de portail, une clé, et parfois un identifiant de communauté saisi dans les réglages. Un Steam ID seul pouvait aussi déclencher une liaison.

## Symptôme
Un opérateur pouvait transmettre positions, photos et messages sans s’être identifié sur Athena, ou en imposant une communauté qui n’était pas la sienne.

## Cause
La communauté et la clé étaient traitées comme une autorité cliente. Le démarrage enchaînait directement connexion puis suivi.

## Correctif
Cycle BOOT → authentification Athena → communauté choisie par le serveur → profil → prêt. Rien ne part tant que l’environnement n’est pas prêt. Steam exige un jeton de poste, pas seulement l’identifiant Steam.

## Fichiers touchés
- `bootstrap/athena_game_auth_migration.php`
- `app/Services/Game/GameAuthService.php`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_waitAthenaReady.sqf`
- `views/admin/atak-config/_game_experience.php`

## Vérification
Tests unitaires `GameAuthAssetTest` et `DevDispatchCatalogTest`. Contrôle visuel back-office : bloc « Expérience en jeu » avec aperçu de fenêtre.

## Statut
Corrigé
