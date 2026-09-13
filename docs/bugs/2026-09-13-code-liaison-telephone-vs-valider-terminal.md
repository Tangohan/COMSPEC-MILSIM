# Code « Liaison téléphone » collé dans « Valider un terminal »

## Contexte

L’opérateur génère un code depuis l’écran **Liaison téléphone** en jeu (`3H57N5`), puis le saisit dans **Appairer → Valider un terminal ATAK** sur le portail.

## Symptôme

Message : « Ce code est inconnu ou a déjà expiré. Générez-en un nouveau dans Arma. »

## Cause

Deux flux distincts :

1. **Liaison téléphone** → code `tactical_phone_pairings`, à saisir sur la page mobile (`/connect`) dans le navigateur du téléphone.
2. **Associer ce terminal** → code de défi jeu (`GameAtakPairing`), à valider sur le portail via `confirm-pair`.

Le code mobile était traité comme un code de terminal jeu → 404 générique.

## Correctif

- Détection du code téléphone valide dans `AtakController::confirmPairCode` : message orienté vers `/connect` et vers **Générer un code** pour lier Arma.
- Libellés portail et aide en jeu clarifiés.

## Fichiers touchés

- `app/Controllers/Web/AtakController.php`
- `views/atak.php`
- `mod/UptoDate/Sources/.../display_phone_connect.hpp`
- `mod/UptoDate/Sources/.../fn_phoneConnectDialogOnLoad.sqf`
- `app/Support/DevDispatchCatalog.php` (UPDATE 529)

## Vérification

- Coller un code `tactical_phone_pairings` encore valide dans Valider un terminal → message « ouvre la carte sur un téléphone réel… ».
- Valider un vrai code Associer ce terminal → succès inchangé.
- Saisir le code sur `/connect` → accès mobile OK.

## Statut

corrigé
