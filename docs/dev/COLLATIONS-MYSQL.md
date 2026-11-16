# Collations MySQL / MariaDB

## Le piège

La connexion de l'application est en `utf8mb4_unicode_ci` (`Database::sessionInitSql()`).
Une table créée avec seulement `DEFAULT CHARSET=utf8mb4`, sans `COLLATE`, prend la collation par défaut de la base :

| Serveur | Collation par défaut de `utf8mb4` |
| --- | --- |
| MariaDB 11.4 et suivants (production) | `utf8mb4_uca1400_ai_ci` |
| MySQL 8 | `utf8mb4_0900_ai_ci` |
| MariaDB 10.x | `utf8mb4_general_ci` |

Une jointure ou une comparaison entre deux colonnes texte de collations différentes échoue :
`SQLSTATE[HY000] 1267 Illegal mix of collations (…,IMPLICIT) and (…,IMPLICIT) for operation '='`.

## Ce qui s'est passé (16/11/2026)

Les erreurs indiquent, dans l'ordre des opérandes, `player_sessions.steam_uid` en `utf8mb4_unicode_ci` et `users.steam_id` en `utf8mb4_uca1400_ai_ci`.
La cause est donc l'inverse de ce qu'on supposait :

- les **tables historiques** (`migrations/schema.sql` : `users`, `tenants`…, plus de nombreuses migrations bootstrap) n'ont pas de `COLLATE`. En production elles sont en `utf8mb4_uca1400_ai_ci` ;
- les **tables récentes** (étapes 4000 et suivantes : `player_sessions`, `member_availability_checks`, `sse_*`…) déclarent toutes `COLLATE=utf8mb4_unicode_ci`.

Seules exceptions récentes : `atak_terminal_sync` (bootstrap des étapes 2080 et 4040), `personnel_job_role_recovery_log` (créée à la volée) et `sse_id_settings` (aucune colonne texte).

## Jointures historique ↔ récent repérées

| Requête | Colonnes | Correction |
| --- | --- | --- |
| Widget « Disponibilité à vérifier » (`PlayerSessionRepository::membersToVerify`, 2 sous-requêtes) | `player_sessions.steam_uid` = `users.steam_id` | `SqlText::columnEquals` |
| Widget « Sessions de jeu récentes » (`recentForTenant`) | `users.steam_id` = `player_sessions.steam_uid` | `SqlText::columnEquals` |
| Alertes RH (`qualifyingExistsClause`) | idem | `SqlText::columnEquals` (PDO passé) |
| Règles d'attribution des qualifications, jours d'usage (`QualificationAwardRuleRepository::metricValues`) | `game_item_usage.steam_id` = `users.steam_id` | `SqlText::columnEquals` |
| Inspecteur Athena (`AthenaInspectorMetricsRepository`) | `atak_terminal_sync.terminal_uid` = `atak_terminals.terminal_uid` | étape 4900 (conversion de `atak_terminal_sync`) |

`SqlText::columnEquals($pdo, 'récente.col', 'historique.col')` donne sous MySQL `récente.col = (CONVERT(historique.col USING utf8mb4) COLLATE utf8mb4_unicode_ci)`.
La colonne récente garde sa collation : son index reste utilisé. Sous SQLite, on obtient une simple égalité.

## Règles

1. Tout nouveau `CREATE TABLE` (étape, fichier SQL, migration bootstrap) se termine par `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. Le test `tests/Unit/MigrationCreateTableCollationTest.php` échoue sinon. L'historique est figé dans `tests/fixtures/create_table_collation_allowlist.php`.
2. Une jointure texte entre une table historique et une table récente passe par `SqlText::columnEquals`.
3. Les grosses tables historiques ne sont jamais converties au déploiement : la conversion recopie la table et bloque les écritures, et elle casserait les jointures qui marchent aujourd'hui entre deux tables historiques.

## Étape 4900 `mysql_recent_tables_collation`

- Sans effet sous SQLite.
- Crée `user_community_membership_periods` si elle manque. L'inscription ne la crée plus pendant sa transaction.
- Pour chaque table de `TableCollationAligner::RECENT_TABLES` (tables des étapes 4000 et suivantes, plus les deux tables créées à la volée), lance `ALTER TABLE … CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`, avec ces conditions :
  - la table ou l'une de ses colonnes texte n'est pas en `utf8mb4_unicode_ci` ;
  - elle compte au plus 200 000 lignes et 256 Mo (estimation `information_schema`) ;
  - aucune de ses colonnes n'est en `…_bin` ni dans un autre jeu de caractères ;
  - le verrou est obtenu en moins de 10 s (`lock_wait_timeout`).

  Dans tous les autres cas, la table est laissée telle quelle avec un message `[ATTENTION]`.
- Liste dans le journal, sans rien modifier, les autres tables qui ne sont pas en `utf8mb4_unicode_ci`.
- Idempotente. Pas de retour arrière : voir `migrations/rollback/mysql_recent_tables_collation_down.sql`.

## Preuve sur un vrai serveur

`tests/Contract/RecentTablesCollationMysqlTest.php` tourne dans le job CI `mysql-directory` (MySQL 8 et MariaDB 10.11).
Le test crée une base dont la collation par défaut diffère : `utf8mb4_uca1400_ai_ci` si le serveur la connaît (MariaDB ≥ 10.10), sinon `utf8mb4_0900_ai_ci`.
Il vérifie ensuite quatre choses :

- l'ancienne jointure échoue bien en 1267 ;
- les requêtes des widgets et les jointures ci-dessus passent ;
- l'étape 4900 convertit `atak_terminal_sync` sans toucher à `users` ;
- relancer l'étape ne fait rien.
