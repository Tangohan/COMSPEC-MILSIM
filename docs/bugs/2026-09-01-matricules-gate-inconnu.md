# Bug — page Matricules (et pages sœurs) : « Unknown service: App\Core\Gate »

## Contexte

Incident production (corrélation `c995006f7d2dd364`). GET `/back-office/organisation/matricules`, puis 500 sur `/back-office/organisation/progression`. Compte interne 5, communauté 7.

## Symptôme

Page blanche / e-mail d’incident. Message : `Unknown service: App\Core\Gate`. Impossible d’ouvrir les matricules, la progression ou les indicatifs.

## Cause

`OrganizationMemberNumberController` demandait `Gate` au conteneur (`Container::get(Gate::class)`). `Gate` n’est pas un service du conteneur : c’est un singleton (`Gate::getInstance()`), alimenté au login. Même schéma sur Progression et Indicatifs.

## Correctif

- Ces trois contrôleurs utilisent `Gate::getInstance()`.
- Le conteneur sait aussi résoudre `Gate` (même instance) si un autre code le demande encore.
- Les trois contrôleurs sont enregistrés dans le conteneur : le routeur n’a plus besoin du repli `new $class()` qui déclenchait l’erreur.

## Fichiers touchés

- `app/Controllers/Admin/Organization/OrganizationMemberNumberController.php`
- `app/Controllers/Admin/Organization/OrganizationProgressionHubController.php`
- `app/Controllers/Admin/Organization/OrganizationCallsignSequencesController.php`
- `app/Core/ContainerIntegrations.php`
- `tests/Unit/GateContainerAssetTest.php`

## Vérification

- `php -l` sur les fichiers touchés.
- Test `GateContainerAssetTest` : le conteneur renvoie le singleton, les trois contrôleurs n’appellent plus `Container::get(Gate::class)`.
- Après déploiement : recharger `/back-office/organisation/progression` et `/back-office/organisation/matricules`.

## Statut

corrigé
