# Parc ATAK de la communauté : erreur 500

## Contexte
Back-office d'une communauté, page /back-office/atak/parc-atak (BackOfficeAtakFleetController, version limitée du parc admin).

## Symptôme
Erreur 500 à chaque ouverture (incidents depuis le 27/10 dans storage/logs/error-alerts.log).

## Cause
`ArgumentCountError` : BackOfficeAtakFleetController hérite du constructeur d'AdminAtakFleetController (dépôt du parc + AuthService)
mais n'était pas déclaré dans `App\Core\Container`. Le routeur retombait sur `new $class()` sans arguments.

## Correctif
Entrée Container pour BackOfficeAtakFleetController, avec les mêmes dépendances que la version admin. Test de non-régression.

## Fichiers touchés
`app/Core/Container.php`, `tests/Unit/BackOfficeAtakFleetContainerTest.php`

## Vérification
Ouvrir /back-office/atak/parc-atak en gestionnaire de communauté : la liste s'affiche.

## Statut
corrigé
