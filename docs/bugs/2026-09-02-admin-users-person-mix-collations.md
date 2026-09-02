# Dossier personne admin — mélange de collations

## Contexte

Erreur signalée en production sur `GET /admin/users/person?email=…` (administration plateforme). Identifiant de corrélation `7fb4fe2218d09269`.

## Symptôme

L’ouverture du dossier personne multi-communautés provoque une erreur SQL 1267 :

`Illegal mix of collations (utf8mb4_bin,NONE) and (utf8mb4_unicode_ci,COERCIBLE) for operation '='`

## Cause

Après le correctif du tableau de bord (comparaison d’e-mail), il restait des comparaisons de texte « en dur » (`slug`, `status`) dans les requêtes du dossier personne :

- tri `ORDER BY` sur `t.slug = 'default'` et statuts ;
- filtre `t.slug <> 'default'` pour détecter une appartenance active hors tenant système ;
- statut d’affectation primaire (`personnel_assignments.status = 'active'`).

Sur MariaDB, ces colonnes sont souvent en `utf8mb4_bin` alors que les littéraux héritent de `collation_connection` (`utf8mb4_unicode_ci`).

## Correctif

- Extension de `SqlText` : `equalsLiteral`, `notEqualsLiteral`, `coalesceEqualsLiteral`.
- `UserRepository::listAllMembershipsByEmail` et `emailHasActiveNonDefaultMembership` : comparaisons slug/statut alignées.
- `PersonnelAssignmentRepository::getPrimaryAssignment` : statut via `SqlText::equals`.

## Fichiers touchés

- `app/Support/SqlText.php`
- `app/Repositories/UserRepository.php`
- `app/Repositories/PersonnelAssignmentRepository.php`
- `tests/Unit/SqlTextTest.php`
- `tests/Unit/PlatformUsersMultiTenantAssetTest.php`

## Vérification

- Tests unitaires `SqlTextTest` (MySQL simulé + SQLite).
- Tests de régression sur les fragments SQL du dossier personne.

## Statut

Corrigé (à déployer en production).
