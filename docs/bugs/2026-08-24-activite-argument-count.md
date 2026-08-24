# Hub d’activité — 500 à l’ouverture

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

La page « Votre activité » (`/activite`) est instanciée par le conteneur d’injection (`ContainerIntegrations`), qui passe avant le gros `Container.php`.

## Symptôme

En production, ouvrir `/activite` lève `ArgumentCountError` : 4 arguments passés, 5 attendus, dans `ActivityHubController::__construct()`.

## Cause

Depuis fin juillet le contrôleur exige un 5ᵉ service (les bandeaux d’alerte). Le câblage réellement utilisé en production n’en passait que 4. Le 5ᵉ argument existait dans `Container.php`, mais ce bloc n’est jamais atteint : `ContainerIntegrations` résout le contrôleur en premier.

## Correctif

- Passer le service d’alertes dans `ContainerIntegrations`.
- Rendre le 5ᵉ argument optionnel : un ancien câblage à 4 services reprend le service tout seul, sans 500.

## Fichiers touchés

- `app/Controllers/Web/ActivityHubController.php`
- `app/Core/ContainerIntegrations.php`
- `tests/Unit/ActivityHubControllerDiTest.php`

## Vérification

- `php -l` sur le contrôleur.
- Le constructeur accepte 4 arguments obligatoires ; le 5ᵉ est optionnel.
- Le câblage `ContainerIntegrations` cite bien les 5 classes.

## Statut

Corrigé (à déployer sur la production, qui part de `main`).
