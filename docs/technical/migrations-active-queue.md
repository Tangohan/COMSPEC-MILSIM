# File active des migrations

Le 29 octobre 2026, l’installation existante devient le point de départ. La file `database/steps/registry.php` est vide : `php run-migrations.php`, `--list` et `--status` terminent avec succès sans charger l’environnement, ouvrir une connexion ou exécuter de SQL. Les alias `setup-database.php`, `migrate.php` et leurs entrées publiques utilisent cette même file.

La remise à zéro porte sur le programme de mise à jour, pas sur les données. Les fichiers SQL, migrations PHP, étapes historiques et tables de journal restent présents. `database/steps/registry-archive.php` conserve l’ancien registre comme référence ; le lanceur actif ne le charge jamais. Il ne recherche plus les anciens fichiers `migrations/*.sql`, ne resème aucun référentiel et ne lance aucun nettoyage, rattrapage ou contrôle de maintenance ancien. Les options `--replay` et `--replay-all` sont retirées ; un ancien identifiant passé à `--step` est refusé.

Pour ajouter une nouvelle table ou colonne, créer une étape PHP autonome dans `database/steps/`, puis l’inscrire explicitement dans le registre actif :

```php
return [
    ['id' => 'new_module_table', 'label' => 'Nouvelle table du module',
     'group' => 'schema', 'file' => 'new_module_table.php', 'rerun' => true],
];
```

L’étape reçoit `$pdo`, `$root`, `$migrationRunner`, `$migrationFlush` et `$migrationEnsurePdo`. Elle doit charger elle-même ses dépendances et être idempotente. Si elle utilise un fichier SQL, elle doit sélectionner précisément ce fichier ; ne pas réintroduire de balayage des archives. Une étape réussie est notée dans `schema_migration_runs` puis ignorée lors des passages normaux. Un échec reste en attente. `--status` indique l’état des seules entrées actives, et `--step=new_module_table` permet la relance explicite d’une entrée active marquée `rerun`.

Les protections web existantes restent en place : console d’administration, ticket à usage unique, ou accès de secours avec mot de passe et CSRF. L’historique reste consultable ; aucune option de purge des données ou des journaux n’est ajoutée. Le lanceur n’est plus un outil d’installation initiale d’une base vierge : une telle installation demande un schéma de départ vérifié séparément.

## Exception documentée : codes unité (2026-11-01)

Les tests `MigrationRegistryTest` et `MigrationWebQueueTest` imposent une file active vide. Les tables `tenant_unit_codes` et `unit_codes` (codes unité lisibles et identifiants administratifs) sont donc créées à la demande, comme la page publique du joueur : `App\Repositories\UnitCodeRepository::ensureTables()` exécute uniquement `migrations/2026_11_01_002_unit_codes.sql` (`CREATE TABLE IF NOT EXISTS`, idempotent) à la première ouverture des effectifs ou de l’ORBAT, puis numérote les communautés existantes dans l’ordre de création. Aucune donnée existante n’est modifiée ; le repère saisi d’une unité reste `units.code`. Les écritures d’unités (`UnitRepository::create/update/delete`) ne recalculent les codes que si les tables existent déjà.
