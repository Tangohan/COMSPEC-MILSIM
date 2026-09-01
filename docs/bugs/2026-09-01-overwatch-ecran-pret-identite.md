# 2026-09-01 — Écran Overwatch « ENVIRONNEMENT PRÊT » : mauvaise identité

## Contexte

Après connexion Athena dans Arma 3, la fenêtre Overwatch affiche **ENVIRONNEMENT PRÊT**. Elle doit présenter l’opérateur, pas la communauté.

## Symptôme

- Première ligne : nom d’équipe / unité de la communauté (ex. « 24th STS Gold Team SOF TACP »).
- Ligne suivante : code communauté et un fragment d’indicatif (ex. « soar-milsim-group TA1 »).
- **Indicatif** : nom complet de la communauté.
- **Unité** : adresse interne du bandeau communauté.
- Pied de fenêtre : `Liaison "" • Pack actuel "" • Pack exigé ""`.
- Pas de photo opérateur, même lorsqu’une photo existe au dossier.

## Cause

1. L’état renvoyé vers le jeu est découpé par tabulations. Arma ignore les champs vides. Le code d’erreur, vide une fois prêt, n’était pas remplacé par un tiret : toutes les colonnes suivantes glissaient d’un cran. L’indicatif recevait le nom de la communauté, l’unité recevait autre chose, parfois l’adresse du bandeau.
2. Le pied de fenêtre passait les versions par une conversion qui ajoute des guillemets. Une version absente devenait `""` au lieu d’être reconnue comme vide, donc le repli (pack installé / liaison) ne s’appliquait pas.
3. Le texte prêt mettait la communauté en tête et collait grade + nom, sans rôle ni fonction, et sans photo.

## Correctif

- Athena envoie prénom, nom, indicatif, rôle, grade, fonction, unité réelle et photo (seulement si elle existe). Une adresse interne n’est jamais une unité.
- La liaison jeu remplit chaque case vide par un tiret, y compris l’erreur, conserve les cases vides à la découpe, et refuse une adresse interne comme identité. Un nom de communauté trop long n’est pas pris pour un indicatif.
- L’écran prêt affiche photo (si téléchargée), prénom nom, indicatif, rôle / grade / fonction, puis la communauté à part. Le pied reprend le pack en service, et le pack exigé seulement s’il est connu.

## Fichiers touchés

- `app/Services/Game/GameAuthService.php`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_authStateCells.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_pollAuth.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_applyBootstrap.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_logout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_athena_auth.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_splitKeepEmpty.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isUsableCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

Tests `OverwatchReadyIdentityAssetTest`, `GameAuthAssetTest`, `DevDispatchCatalogTest`. Contrôle en jeu après recompilation du pack : photo si présente, nom, indicatif, rôle / grade / fonction, communauté à part, versions de pack lisibles.

## Statut

Corrigé (visible en jeu après recompilation du pack Overwatch et de la liaison)
