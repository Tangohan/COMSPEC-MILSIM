# Pipeline Athena — avertissement « extensions intelligence » répété (2002)

## Contexte

Mise à jour du poste (pipeline `migrate.php` / `run-migrations.php`) en production Hostinger. La connexion principale réussit (`[OK] Connexion base : u416380327_BDD_PROD`). L’étape des modules carte continue (rapports, points d’intérêt, zones, etc.) puis échoue sur « extensions intelligence ».

## Symptôme

Le journal répète des dizaines de fois :

```
[ATTENTION] extensions intelligence : SQLSTATE[HY000] [2002] Operation not permitted
```

Le reste du pipeline continue (`[OK]` / `[SKIP]`) jusqu’à `atak_modules_schema`. Les tables d’analyse carte (routage des rapports, corrélations, etc.) peuvent rester incomplètes.

## Cause

`bootstrap/atak_modules_schema_migration.php` rouvert une **seconde** session MySQL dès qu’une instruction tombait (timeout / session coupée), en relisant `DB_HOST` brut. Sur l’hébergement, `localhost` tente un socket Unix interdit (open_basedir / chroot) → erreur 2002. Chaque instruction suivante du fichier d’enrichissements recommençait la même reconnexion et réimprimait le même avertissement. La session déjà ouverte par le pipeline (souvent en TCP `127.0.0.1`) n’était pas réutilisée.

## Correctif

- Helper unique `bootstrap/migration_pdo.php` : `localhost` → `127.0.0.1` (TCP), jamais de socket Unix.
- Réutiliser le PDO du pipeline tant qu’il répond (`SELECT 1`) ; ne reconnecter qu’en TCP si la session est vraiment morte.
- Sur 2002 / session coupée : **un seul** avertissement par fichier, puis passage à la suite (plus de boucle).
- `run-migrations.php` ouvre d’emblée la session avec le même DSN TCP.

Pas de nouveau champ métier : le schéma d’enrichissement existait déjà ; pas d’entrée catalogue de configuration post-mise à jour.

## Fichiers touchés

- `bootstrap/migration_pdo.php` (nouveau)
- `bootstrap/atak_modules_schema_migration.php`
- `run-migrations.php`
- `tests/Unit/MigrationPdoReuseTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 231)
- `tests/Unit/DevDispatchCatalogTest.php`

## Vérification

1. `vendor/bin/phpunit tests/Unit/MigrationPdoReuseTest.php tests/Unit/DevDispatchCatalogTest.php`
2. Relancer `php migrate.php` (ou l’écran de mise à jour du poste) : au plus **une** ligne `[ATTENTION] extensions intelligence` en cas de panne de session ; sinon `[OK]` / `[SKIP]` comme les autres modules.
3. Relancer une seconde fois : idempotent (`CREATE IF NOT EXISTS` / colonnes déjà présentes ignorées).

## Statut

corrigé en code — à déployer puis relancer une fois la mise à jour du poste en production.
