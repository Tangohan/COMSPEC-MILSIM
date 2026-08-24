# Page Activité — ArgumentCountError au chargement

## Contexte

Signalement production sur `GET /activite` (corrélation `e71a210d04d4430a`).

## Symptôme

La page « Votre activité » plante avec :

`Too few arguments to function ActivityHubController::__construct(), 4 passed … and exactly 5 expected`

## Cause

Le constructeur a reçu `AlertPresentationService` pour afficher les annonces dans le hub d’activité, mais le câblage du conteneur (`ContainerIntegrations`, lu en premier, et le doublon dans `Container`) n’injectait toujours que les 4 premiers services.

## Correctif

Passer `AlertPresentationService` en 5ᵉ argument des deux bindings DI. Test unitaire de non-régression sur le câblage `ContainerIntegrations`.

## Fichiers touchés

- `app/Core/ContainerIntegrations.php`
- `app/Core/Container.php`
- `tests/Unit/ActivityHubControllerDiTest.php`

## Vérification

Réflexion du constructeur (5 dépendances) confrontée au binding `ContainerIntegrations` : les 5 services, dont `AlertPresentationService`, sont bien injectés. Test unitaire ajouté (`tests/Unit/ActivityHubControllerDiTest.php`) — `vendor/` n’était pas présent localement pour lancer PHPUnit.

## Statut

Corrigé
