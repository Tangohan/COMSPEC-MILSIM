---
description: Toute mise à jour SQL passe par une étape de la file active lancée par run-migrations.php.
paths:
  - "database/steps/**"
  - "migrations/**"
  - "bootstrap/*migration*.php"
  - "app/Repositories/**"
  - "scripts/sql/**"
---

# Mises à jour SQL : file active uniquement

Toute mise à jour SQL (table, colonne, index, rattrapage de schéma) = **une étape PHP idempotente** dans `database/steps/` (numéro suivant), inscrite à la fin de `database/steps/registry.php` avec `'rerun' => true`, et lancée par **run-migrations.php** : console d’administration (onglet Migrations), ticket sur https://athena-comspec.eu/run-migrations.php, ou `php8.4 run-migrations.php` en SSH.

- Vérifier l’état réel avant d’agir : `App\Support\Migrations\SchemaInspector` (information_schema) avant tout `ALTER TABLE` / index ; un fichier SQL est désigné précisément (`runSqlFile`), jamais de balayage de `migrations/`.
- Ne pas toucher `registry-archive.php`, ne pas réintroduire `--replay` ni l’ancien pipeline.
- Pas de schéma créé seulement « à la demande » ni de script SQL à passer à la main : un `ensure…()` idempotent peut rester en filet de sécurité, la voie officielle reste l’étape.
- `tests/Unit/MigrationActiveQueueTest.php` doit passer (étape relancée deux fois sans modification au second passage).

Référence : [docs/technical/migrations-active-queue.md](docs/technical/migrations-active-queue.md).
