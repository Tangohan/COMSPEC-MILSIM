# Installation neuve : seed `grades` incompatible + socle LMS absent

## Contexte

Mise en place d'un environnement de développement neuf (base MySQL vierge) via la
procédure documentée `php setup-database.php`. Sur une base réellement vierge (pas un
import du dump de production), deux blocages successifs empêchent d'obtenir une
installation fonctionnelle.

## Symptôme

1. `php setup-database.php` s'arrête sur une erreur fatale :
   `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'tenant_id' in 'INSERT INTO'`
   (dans `run-migrations.php`, insertion du grade par défaut du compte admin).
2. Après contournement, la connexion admin aboutit mais le **tableau de bord** plante :
   `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'athena.training_courses' doesn't exist`.

## Cause

1. **Seed `grades` figé sur l'ancien modèle.** Le pipeline exécute une « bascule »
   (`run-migrations.php`, RENAME `grades` → `grades_legacy` puis `grades_referentiel`
   → `grades`) qui remplace la table `grades` par le référentiel multi-doctrine
   (`grade_system_id`, `label_short`, …). Or le bloc de seed « installation neuve »
   insérait encore les colonnes de l'ancien modèle tenant (`tenant_id`, `name`,
   `short_name`, `nato_code`, `rank_order`). Sur une base de prod (seed déjà présent
   via le dump), ce bloc ne s'exécute jamais : le bug ne se voyait donc qu'en install neuve.
2. **Socle LMS moderne jamais créé.** Les tables modernes (`training_courses`,
   `training_lessons`, `training_enrollments`, quizzes, …) ne vivent que dans
   `migrations/lms_training.sql`, qui n'était branché à aucun pipeline. `schema.sql`
   ne crée que des tables « legacy » (`training_modules` / `training_progress` /
   `training_certificates`). En prod, `training_courses` venait du dump. En install
   neuve, la table manquait et `App\Repositories\TrainingCourseRepository` (tableau de
   bord, brique Formations) plantait.
   `migrations/lms_training.sql` avait par ailleurs une FK mal typée
   (`training_resources.document_id BIGINT UNSIGNED` vs `documents.id INT UNSIGNED`),
   d'où `errno 150` à la création de la table.

## Correctif

1. Seed `grades` rendu tolérant au schéma : détection de la présence de la colonne
   `grades.tenant_id`. Si absente (référentiel après bascule), l'admin est rattaché à
   un grade officier existant du référentiel (ou `NULL`) au lieu d'insérer des colonnes
   disparues.
2. Nouveau bootstrap idempotent `bootstrap/lms_training_base_migration.php`, branché
   dans `run-migrations.php` juste après l'import de `schema.sql` : crée le socle LMS
   moderne à partir de `migrations/lms_training.sql` si `training_courses` est absente
   (RENAME legacy conditionnels, exécution des `CREATE TABLE`).
3. Correction du type de `training_resources.document_id` (`INT UNSIGNED`) dans
   `migrations/lms_training.sql` pour correspondre à `documents.id`.

## Fichiers touchés

- `run-migrations.php` (seed `grades` schéma-tolérant + appel du socle LMS)
- `bootstrap/lms_training_base_migration.php` (nouveau)
- `migrations/lms_training.sql` (type FK `document_id`)

## Vérification

- `php setup-database.php` sur base vierge : exit 0, 13 tables LMS créées
  (`training_courses`, `training_lessons`, `training_resources`, `training_enrollments`,
  `training_quizzes`, …), compte admin `admin@athena.local` rattaché à un grade
  référentiel.
- Relance de `setup-database.php` : idempotent (exit 0, pas de doublon de tenant).
- Connexion admin (mot de passe + OTP e-mail) puis `GET /dashboard` → HTTP 200,
  `<title>Dashboard — Athena</title>` (plus d'erreur `training_courses`).
- `composer test` : 161 tests OK.

## Statut

Corrigé.
