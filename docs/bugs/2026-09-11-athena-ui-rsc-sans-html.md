# Connexion / appairage ATAK : zone HTML vide + chemins Chromium

## Contexte

11 septembre 2026. Demande : toute l’UI de connexion et d’appairage en Rsc natif, sans HTML dans l’ATAK en jeu. Pack 1.5.20 → 1.5.21.

## Symptôme

- Écran Connexion Athena : grand rectangle noir vide en haut (zone branding).
- Confusion avec d’éventuels chemins tablette Chromium (`tablet.html`) pour lier le jeu / le téléphone.

## Cause

1. Contrôle `BrandingHtml: RscHTML` + `htmlLoad` d’une URL portail — souvent vide ou illisible en jeu.
2. `phoneConnectShow` pouvait encore injecter du JS dans la tablette HTML (idd 9974).
3. `athena_openTablet` ouvrait le Chromium même depuis ATAK Enhanced.

## Correctif

- Branding remplacé par `RscStructuredText` (communauté / état) — plus de `htmlLoad`.
- `phoneConnectShow` : uniquement `COMSPEC_PhoneConnect_Dialog` (Rsc).
- `athena_openTablet` : si « UI uniquement via ATAK », ouvre `openLogin` (Rsc).
- Bouton « Lier le jeu » : ferme le display auth puis ouvre `AccountLink` via `createDisplay` (reste dans ATAK).

## Fichiers touchés

- `connect/display_athena_auth.hpp`
- `connect/functions/auth/fn_pollAuth.sqf`
- `connect/functions/fn_phoneConnectShow.sqf`
- `connect/functions/fn_webBrowserJSDialog.sqf`
- `connect/display_phone_connect.hpp`
- `atak_athena/functions/fn_athena_openTablet.sqf`
- `connect/config.cpp` (1.5.21)

## Vérification

1. Connexion Athena : bandeau avec nom de communauté, pas de rectangle HTML vide.
2. Lier le jeu / Liaison mobile : dialogs Rsc enfants d’ATAK.
3. Pied de fenêtre : pack **1.5.21**.

## Statut

corrigé — rebuild + déploiement SOAR FN
