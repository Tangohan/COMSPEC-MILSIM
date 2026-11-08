---
name: migration-sql
description: "Écrit une mise à jour SQL (table, colonne, index, rattrapage) sous forme d'étape idempotente de la file active."
user-invocable: false
---
Référence : `docs/technical/migrations-active-queue.md`.

1. Crée `database/steps/<numéro suivant>_<nom>.php`.
2. Inscris l'étape à la fin de `database/steps/registry.php` avec `'rerun' => true`.
3. Vérifie l'état réel avec `App\Support\Migrations\SchemaInspector` avant tout `ALTER TABLE` ou index.
4. Un fichier SQL est désigné précisément avec `runSqlFile`, jamais par balayage de `migrations/`.
5. Lance `vendor/bin/phpunit --filter MigrationActiveQueueTest` : l'étape relancée deux fois ne change rien au second passage.

## Interdit
- Toucher `database/steps/registry-archive.php`.
- Réintroduire `--replay` ou l'ancien pipeline.
- Créer un schéma seulement « à la demande », ou un script SQL à passer à la main. Un `ensure…()` idempotent peut rester en filet de sécurité.

## Exécution
L'étape est lancée par `run-migrations.php` : console d'administration (onglet Migrations), ticket sur la page dédiée, ou `php8.4 run-migrations.php` en SSH. Ne la lance jamais toi-même en production.
