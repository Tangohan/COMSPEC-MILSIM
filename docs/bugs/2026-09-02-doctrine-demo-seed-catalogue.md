# Doctrines de démonstration visibles dans le référentiel publié

## Contexte

Le référentiel Doctrine / SOP avait reçu, à l’installation, une série de documents d’exemple (conduite du blessé, mesures de sûreté, transmissions, etc.). En production, ils apparaissaient dans le catalogue publié à côté de la doctrine d’emploi ATAK.

## Symptôme

Sur Documents → Doctrine, les opérateurs voyaient plusieurs doctrines d’exemple, comme s’il s’agissait des textes de leur communauté. Un média pédagogique (par exemple une bibliothèque JTAC) n’est pas concerné.

## Cause

L’ancien dépôt d’exemples créait ces documents dès qu’une communauté n’avait encore aucune doctrine. La doctrine ATAK, elle, est le seul texte prévu pour toutes les communautés.

## Correctif

- Le dépôt d’exemples ne crée plus rien : il ne sert qu’à reconnaître les anciennes paires référence + titre.
- Un nettoyage archive uniquement ces documents connus, sans toucher à un dépôt fait par la communauté, ni à SIC/ATAK/2026-001.
- La doctrine d’emploi ATAK est créée si elle manque ; si ce n’était encore qu’un exemple, le texte officiel la remplace, sans inventer de prises en compte.

## Fichiers touchés

- `bootstrap/doctrine_demo_seed.php`
- `bootstrap/doctrine_demo_cleanup.php`
- `bootstrap/doctrine_referential_migration.php`
- `bootstrap/doctrine_atak_employment_seed.php`
- `app/Support/Doctrine/DoctrineDemoCatalog.php`
- `tests/Unit/DoctrineDemoCleanupTest.php`

## Vérification

- PHPUnit : `DoctrineDemoCleanupTest`, `DoctrineAtakEmploymentAssetTest`, `DoctrineReferentialAssetTest`.
- Après `php run-migrations.php` (ou `setup-database.php`), Documents → Doctrine ne liste plus les exemples. Un rechargement de page sans cette mise à jour ne suffit pas.

## Statut

corrigé
