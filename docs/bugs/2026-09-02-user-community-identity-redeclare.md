# Redeclaration de la mise à niveau d’identité communauté

Date : 2026-09-02  
Statut : corrigé

## Contexte

En production (Athena), l’écran de mise à niveau du portail s’arrêtait avec une erreur fatale. Un compte peut appartenir à plusieurs communautés ; la mise à niveau correspondante était chargée plus d’une fois dans la même requête.

## Symptôme

```
[ERREUR FATALE] Cannot redeclare function run_user_community_identity_migration()
(previously declared in …/bootstrap/user_community_identity_migration.php:11)
```

La mise à jour n’allait pas au bout. Les pages qui ouvraient à la fois l’annuaire et les appartenances pouvaient aussi tomber.

## Cause

Le fichier déclare une fonction nommée, sans garde. Il est chargé :

1. par le pipeline de mise à niveau (`run-migrations.php`, liste bootstrap, `require_once`) ;
2. à nouveau dans la même requête par `SilentSchemaMigration` (`require`, pas `require_once`) depuis l’annuaire, les appartenances et la fusion de comptes.

`require` réexécute le fichier même s’il a déjà été inclus : PHP refuse de redéclarer la fonction.

## Correctif

- La fonction n’est déclarée que si elle n’existe pas encore (`function_exists`).
- `SilentSchemaMigration` n’inclut le fichier qu’une fois (`require_once`) et, si le fichier a déjà été chargé, réutilise la fonction nommée.
- Le pipeline racine charge et exécute cette mise à niveau une seule fois, de façon idempotente.

## Fichiers touchés

- `bootstrap/user_community_identity_migration.php`
- `app/Support/SilentSchemaMigration.php`
- `run-migrations.php`

## Vérification

- Inclusion du fichier deux fois d’affilée : pas d’erreur, la fonction existe toujours.
- Catalogue public : bulletin UPDATE #377.
- Contrôle de syntaxe PHP sur les fichiers modifiés.

## Statut

Corrigé.
