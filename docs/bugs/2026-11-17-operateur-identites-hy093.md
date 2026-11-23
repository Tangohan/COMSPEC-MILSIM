# Mes identités : erreur 500 à l’enregistrement du signalement

## Contexte
Incident du 16/11/2026 à 22:14 sur `POST /operateur/identite/identites`, corrélation `ea55c5b859ca0b0f`.

## Symptôme
Exception non gérée `SQLSTATE[HY093]: Invalid parameter number` lors de la création d’une identité, ou de l’ajout du signalement d’une identité existante.

## Cause
`OperatorIdentityService::saveIdentity()` appelle `SseFileRecordRepository::saveProfile()`. Lorsque le signalement n’existe pas encore, son insertion réutilise le marqueur nommé `:now` pour `created_at` et `updated_at`.

`Database` désactive l’émulation des préparations PDO. MySQL/MariaDB refusent alors cette réutilisation, tandis que SQLite l’accepte : les tests existants sur SQLite ne détectaient pas le défaut.

## Correctif
Deux marqueurs distincts, `:created_at` et `:updated_at`, reçoivent le même horodatage UTC. Les préparations natives restent activées et aucune migration n’est nécessaire.

Un test sur MySQL/MariaDB couvre l’insertion sans champs, avec champs facultatifs vides, puis avec signalement rempli et modification partielle. Il vérifie aussi les horodatages et l’isolation entre communautés. La CI l’exécute dans sa matrice MySQL 8 / MariaDB 10.11.

## Fichiers touchés
- `app/Repositories/SseFileRecordRepository.php`
- `tests/Contract/SseFileProfileMysqlTest.php`
- `.github/workflows/ci.yml`
- Cette note.

## Vérification
- Avant correctif : les trois nouveaux tests échouent avec `HY093` sur MariaDB 11.4, préparations natives activées.
- Après correctif : les trois tests passent (30 assertions).
- Sélection annuaire et signalement : 10 tests passent, 91 assertions ; 9 dépréciations PHPUnit signalées dans la suite existante.
- Après report sur `main`, `OperatorIdentityServiceTest`, `OperatorUnifiedIdentitiesTest`, `SseFilesRecordsTest`, `OperatorHubTest`, `MemberIdentityPapersServiceTest` : 62 tests passent, 1 446 assertions.
- Filtre CI élargi après report : 19 tests exécutés, 128 assertions, 2 échecs dans `RecentTablesCollationMysqlTest` (inchangé). Sur MariaDB 11.4, ces assertions attendent `utf8mb4_0900_ai_ci` mais reçoivent son nom canonique `utf8mb4_uca1400_ai_ci`. Les trois tests du signalement passent ; la CI utilise MySQL 8 et MariaDB 10.11.

## Statut
Corrigé dans le code, non déployé en production. Validation locale sur MariaDB ; MySQL sera vérifié par la CI.

L’enregistrement de l’identité précède celui du signalement : après un échec antérieur, l’identité peut donc déjà exister. Sa modification permettra de compléter son signalement après déploiement.
