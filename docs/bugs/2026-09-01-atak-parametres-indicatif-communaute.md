# Téléphone ATAK — indicatif prérempli avec le nom de communauté

## Contexte

Écran **Paramètres** du téléphone ATAK en jeu, et bandeau bas de carte (INDICATIF / GROUPE). Même communauté que le tableau Effectifs (indicatif court `YB1`, `TA1`, affectation d’unité).

## Symptôme

Les champs **Indicatif** et **Groupe en jeu** affichaient le titre de la communauté (tronqué vers 50 caractères), pas l’indicatif personnel. Le bandeau de carte répétait la même erreur.

## Cause

1. L’état de liaison Athena, lu avec une découpe qui ignorait les champs vides, pouvait placer le nom de communauté dans l’emplacement indicatif.
2. L’enregistrement d’indicatif **renommait le groupe Arma** avec cette chaîne : le groupe en jeu devenait le titre de communauté.
3. La lecture d’indicatif retombait ensuite sur le nom de groupe, donc les deux champs restaient coincés.
4. Le profil n’écartait pas un titre d’organisation (longueur, égalité avec le nom de communauté).

## Correctif

L’indicatif vient de la fiche Effectifs. Un titre de communauté, une adresse interne ou une chaîne trop longue ne sont plus acceptés. Le groupe Arma n’est plus renommé. Sans indicatif personnel, le champ reste vide. Sans équipe de feu, le groupe reste « actuel ».

## Fichiers touchés

- `app/Support/OperatorTacticalIdentity.php`
- `app/Services/Game/GameAuthService.php`
- `app/Controllers/Api/AtakApiController.php` (profil joueur pour le téléphone)
- `app/Controllers/Web/AtakController.php` (compte sur la carte du poste)
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isUsableCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_inGameGroupLabel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_applyBootstrap.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateSettings.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `tests/Unit/OperatorTacticalIdentityTest.php`
- `tests/Unit/AtakPhoneSettingsIdentityAssetTest.php`

## Vérification

Tests unitaires d’identité et d’assets. En jeu (pack à jour) : Paramètres + bandeau carte, indicatif = colonne Effectifs. Carte du poste `/atak` : fiche Compte, même indicatif.

## Statut

Corrigé
