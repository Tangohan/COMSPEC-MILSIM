# Connexion Athena intégrée dans le téléphone ATAK

## Contexte

11 septembre 2026. L’app Athena n’affichait que « Compte non connecté » + bouton ouvrant une fenêtre séparée. Demande : tout gérer dans le téléphone (e-mail, mot de passe, appairage) en Rsc natif.

## Symptôme

- Panneau Athena vide hors message d’état.
- Connexion / code Appairer hors du téléphone (dialogs séparés).

## Correctif

- Formulaire natif dans `PageConnexion` (e-mail, mot de passe, code e-mail, Steam, code Appairer, Entrer, liaison mobile).
- `openLogin` / bouton Connexion → panneau ATAK, plus de dialog obligatoire.
- Pack connect **1.5.23**, Athena **1.0.81**.

## Fichiers touchés

- `atak_athena/ui/athena_page.hpp`
- `atak_athena/functions/fn_athena_authFocus.sqf`
- `atak_athena/functions/fn_athena_authAction.sqf`
- `atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `atak_athena/functions/fn_athena_updatePanel.sqf`
- `atak_athena/functions/fn_athena_pageCtrl.sqf`
- `connect/functions/auth/fn_openLogin.sqf`
- `connect/functions/auth/fn_pollAuth.sqf`

## Vérification

1. Téléphone → Athena → formulaire visible si non connecté.
2. E-mail / mot de passe, Steam, ou code Appairer fonctionnent dans le panneau.
3. Après READY : bouton Entrer ; fiche opérateur une fois lié.

## Statut

corrigé — rebuild SOAR FN
