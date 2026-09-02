# Annuaire personnel — membres et matricules manquants

## Contexte

Signalement en production : l’annuaire `/personnel` n’affiche plus tous les membres du tenant ; matricules et ancienneté absents ou incorrects.

## Cause

Régression liée au modèle **un compte / plusieurs communautés** :

1. **`sqlMemberOfTenantPredicate`** ne conservait plus le repli `users.tenant_id = ?` — seules les lignes `user_community_memberships` actives comptaient.
2. **`listPersonnelDirectoryRich`** lisait `users.tenant_member_number`, grades et dossiers RH via `u.tenant_id` au lieu du tenant consulté et de `user_community_profiles`.
3. **Backfill migration** : comptes `inactive` au moment de la migration ont reçu une appartenance `left`, donc exclus de l’annuaire même après réactivation.

## Correctif

- Prédicat d’appartenance : `(membership active) OR users.tenant_id = tenant`.
- Annuaire personnel : jointure `user_community_profiles`, RH scopé par `tenant_id`, grades / fonctions sur le tenant courant.
- Migration idempotente : backfill appartenances + réactivation des `left` pour comptes `active`.

## Fichiers touchés

- `app/Repositories/UserRepository.php`
- `bootstrap/user_community_identity_migration.php`
- `tests/Unit/PersonnelDirectoryTenantScopeTest.php`

## Déploiement

1. Déployer le code.
2. Exécuter les migrations (`run-migrations.php`) pour lancer la réparation des appartenances.

## Statut

Corrigé (à déployer en production).
