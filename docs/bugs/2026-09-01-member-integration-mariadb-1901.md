# member_integration — tables absentes (MariaDB 1901 / FK 150)

## Contexte

Mise à jour du portail. Avertissements `member_integration` au dernier passage.

## Symptôme

- Erreur 1901 : `user_id` refusé dans une colonne calculée `active_user_key`.
- Ensuite errno 150 : les tables d’étapes, référents, rendez-vous, etc. ne se créent pas (la table principale n’existe pas).

## Cause

MariaDB (hébergeurs) refuse souvent une colonne **calculée** qui recopie `user_id`. Toute la table `member_integrations` échoue. Les tables suivantes pointent vers elle → clés étrangères invalides.

## Correctif

Colonne physique `active_user_key` + triggers (même principe que le RBAC `co_unit`). Relancer la mise à jour recrée les tables manquantes.

## Fichiers touchés

- `migrations/20260901000001_member_integration.sql`
- `bootstrap/member_integration_migration.php`
- `tests/Unit/MemberIntegrationAssetTest.php`

## Vérification

Relancer la mise à jour du portail : plus d’avertissement 1901 / 150, message `[OK] member_integration`.

## Statut

Corrigé (à relancer sur le serveur).
