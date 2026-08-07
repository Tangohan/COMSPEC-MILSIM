# CI — exit 255 PHPUnit (`TestCase::count`)

## Contexte

Workflow GitHub Actions `CI` / job `php`.

## Symptôme

`Process completed with exit code 255` avec :

> PHP Fatal error: Cannot override final method PHPUnit\Framework\TestCase::count() in tests/Unit/AccountPurgeServiceTest.php

## Cause

Le test définissait un helper privé `count(PDO, string)` qui collisionne avec la méthode `final count()` de PHPUnit 11.

## Correctif

Renommé en `countRows()`.

## Fichiers touchés

- `tests/Unit/AccountPurgeServiceTest.php`

## Statut

corrigé
