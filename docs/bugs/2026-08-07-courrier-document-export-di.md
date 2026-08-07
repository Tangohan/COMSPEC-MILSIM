# Courrier PDF — ArgumentCountError DocumentExportService

## Contexte

Export PDF d’un document courrier : `GET /courrier/documents/{id}/pdf` et `/pdf-external`.

## Symptôme

Erreur production :

`Too few arguments to function DocumentExportService::__construct(), 1 passed … and at least 2 expected`

## Cause

Le constructeur exigeait `DocumentBuilderService` **et** `DocumentPresetRepository`, mais le Container de prod ne passait encore que le builder.

## Correctif

1. Câblage Container : injection des deux dépendances (`app/Core/Container.php`).
2. `DocumentPresetRepository` rendu **optionnel** avec défaut (`new DocumentPresetRepository()`) pour rester compatible si seul le service est déployé avant le Container.

## Fichiers touchés

- `app/Core/Container.php`
- `app/Services/Courrier/DocumentExportService.php`

## Vérification

Recharger `/courrier/documents/4/pdf` et `/pdf-external` : plus d’`ArgumentCountError`.

## Statut

corrigé
