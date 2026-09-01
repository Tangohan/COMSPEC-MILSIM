# ACE — Connexion Athena ouvrait la mauvaise fenêtre

## Contexte

En jeu, l’opérateur cherche l’écran **CONNEXION À ATHENA** avec e-mail et mot de passe.

## Symptôme

ACE (sur soi) → **COMSPEC Athena** → **Compte Athena** ouvrait l’écran de liaison par code court (adresse du portail + code généré sur le site), sans champs e-mail ni mot de passe.

## Cause

Le menu ACE appelait l’écran de liaison par code, pas la fenêtre de connexion de session.

## Correctif

- ACE → **Connexion Athena** ouvre l’écran e-mail / mot de passe / Steam.
- Le bouton du même nom dans l’application Athena du téléphone ouvre le même écran.
- La tuile **Connexion Athena** du bureau du téléphone était déjà la bonne.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACEAthena.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `tests/Unit/OverwatchAceAthenaMenuAssetTest.php`
- `tests/Unit/AtakAthenaLoginOnDemandAssetTest.php`

## Vérification

ACE sur soi → COMSPEC Athena → Connexion Athena : champs e-mail et mot de passe visibles. Ou touche K → tuile Connexion Athena (déjà correcte sans ce correctif). Pack à recharger.

## Statut

Corrigé (pack à recharger).
