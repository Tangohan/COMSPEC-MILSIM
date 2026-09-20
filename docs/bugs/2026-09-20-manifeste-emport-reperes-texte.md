# Manifeste de vol refusé et repères texte sans cadre

## Contexte

Poste Overwatch Beta. Manifeste de vol envoyé depuis le jeu. Repères Eden / carte du type « texte seul » (navire, zone en mer).

## Symptôme

1. Le manifeste de vol était refusé : le poste n’enregistrait pas l’appareil. Message d’erreur interne sur l’emport.
2. Des noms comme un navire ou une zone en mer n’apparaissaient que comme du texte coloré flottant, sans cadre ni picto.

## Cause

1. L’emport est une phrase libre. La colonne d’emport n’accepte qu’une valeur structurée : une phrase brute était rejetée.
2. En jeu, un repère « vide » (sans picto) a une texture transparente. Le poste chargeait cette texture : le glyphe disparaissait, il ne restait que le libellé.

## Correctif

1. L’emport et les notes du manifeste sont enregistrés même s’ils sont saisis en phrase libre.
2. Un repère sans picto n’utilise plus la texture transparente : cadre coloré autour du nom, glyphe visible.

## Fichiers touchés

- `app/Repositories/AtakDataRepository.php`
- `public/assets/js/arma-map-markers.js`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Envoyer un manifeste avec un emport en phrase. Sur la photo aérienne, un nom de lieu sans picto doit apparaître dans un cadre coloré.

## Statut

Corrigé
