# BDA « No Reattack Required » classé prioritaire

## Contexte

Priorité d’un compte-rendu BDA IceMan / ATAK.

## Symptôme

Un BDA avec « No Reattack Required » était classé PRIORITY au lieu de
ROUTINE.

## Cause

Le texte contient le mot « required », détecté comme reprise demandée.

## Correctif

Détecter d’abord les formulations de non-reprise (« no reattack », etc.).

## Fichiers touchés

- `app/Support/AtakIcemanReportCatalog.php`

## Vérification

- `AtakIcemanReportCatalogTest::testParseBdaReportsForm`

## Statut

corrigé
