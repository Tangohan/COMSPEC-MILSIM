# Tableau de bord — mélange de collations à l’ouverture

## Contexte

Erreur signalée en production sur `GET /dashboard` (compte connecté, communauté active). Identifiant de corrélation `88fcb68e48e2a737`.

## Symptôme

Après connexion, le tableau de bord affiche une erreur technique. Le portail ne peut pas lister les communautés du compte.

Message : mélange illégal de collations (`utf8mb4_bin` et `utf8mb4_general_ci`) sur une comparaison d’égalité.

## Cause

Au chargement de chaque page authentifiée, le portail cherche les communautés liées à l’adresse du compte. Cette recherche passe par une comparaison d’adresse après mise en minuscules. Sur le serveur, la colonne d’adresse est en collation binaire, alors que la valeur envoyée par la session suit la collation de connexion (`utf8mb4_general_ci`). MariaDB refuse le mélange.

## Correctif

- La session base de données force une collation unique (`utf8mb4_unicode_ci`).
- Les comparaisons d’adresse (et le statut du compte dans la même requête) imposent la même collation des deux côtés.

## Fichiers touchés

- `app/Core/Database.php`
- `app/Core/ReconnectingPdo.php`
- `app/Support/SqlText.php`
- `app/Repositories/UserRepository.php`
- `app/Repositories/EnlistmentRepository.php`
- `app/Services/Account/AccountPurgeService.php`

## Vérification

- Tests unitaires du fragment de comparaison (SQLite et MySQL simulé).
- `listTenantsForEmail` sur SQLite : une adresse mixte retrouve le compte actif.
- Tests du journal public (UPDATE #376).

## Statut

Corrigé (à déployer en production).
