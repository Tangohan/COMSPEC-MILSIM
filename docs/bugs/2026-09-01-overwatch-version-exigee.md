# 2026-09-01 — Pack actuel et pack exigé sur la fenêtre Athena

## Contexte
Quand le pack Overwatch est trop ancien pour la communauté, la fenêtre de connexion refuse l’entrée.

## Symptôme
Le message disait seulement que le pack n’était plus accepté. Le bas de fenêtre affichait « Extension 1.18.0 • Mod 1.5.0 », sans dire ce que le poste demandait. Un opérateur ne savait pas s’il fallait mettre à jour le jeu ou si la communauté exigeait une version trop haute.

## Cause
Le serveur renvoyait déjà le pack minimal, mais la liaison ne le lisait pas. Le pied de fenêtre recopiait des numéros figés. Le seuil proposé aux nouvelles communautés était 2.3.0, alors que le pack publié est 1.5.0.

## Correctif
La fenêtre affiche le pack installé et le pack exigé. Le pied de fenêtre reprend ces deux numéros. Le seuil proposé par défaut aux communautés nouvelles est 1.5.0. Une communauté qui a déjà enregistré une exigence plus haute la conserve : le gestionnaire la baisse dans Cartographie, Expérience en jeu.

## Fichiers touchés
- `app/Services/Game/GameAuthService.php`
- `app/Services/Game/GameOverwatchExperienceService.php`
- `views/admin/atak-config/_game_experience.php`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_pollAuth.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_openLogin.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_athena_auth.hpp`

## Vérification
Tests `GameAuthAssetTest` et `DevDispatchCatalogTest`. Contrôle visuel : message « Pack actuel : 1.5.0 — version exigée : … » et pied de fenêtre avec les deux numéros.

## Statut
Corrigé (visible en jeu après recompilation du pack Overwatch)
