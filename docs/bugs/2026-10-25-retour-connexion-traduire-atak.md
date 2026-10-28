# Retour après connexion vers l’atelier de traduction

## Contexte

L’atelier de traduction du téléphone propose aux visiteurs de se connecter pour retrouver leur travail sur un autre appareil. Le lien vers la connexion indique la page à rouvrir ensuite.

## Symptôme

Un visiteur qui ouvrait la connexion avec un retour prévu vers l’atelier revenait ailleurs : la page de connexion ignorait cette indication.

## Cause

La page de connexion ne lisait pas le retour demandé dans l’adresse. Seule une destination déjà mémorisée pendant la navigation était honorée après la connexion.

## Correctif

Si l’adresse de connexion contient un retour interne sûr, il est mémorisé avant le formulaire. Un membre déjà connecté est renvoyé directement vers cette page. Les adresses externes sont refusées.

## Fichiers touchés

- `app/Controllers/Auth/AuthController.php`
- `app/Support/LoginIntendedDestination.php`
- `views/site/atak_translate.php`

## Vérification

Contrôle de syntaxe PHP. Le parcours complet (connexion réelle, puis retour sur l’atelier) n’a pas été rejoué dans le navigateur.

## Statut

Corrigé dans le code.
