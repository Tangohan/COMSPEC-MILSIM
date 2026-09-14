# Carte tactique — fonction e() introuvable

## Contexte

13 septembre 2026. Ouverture de Opérations → Carte tactique (`/back-office/operations/carte-tactique`). Signalement production, corrélation `f421b14cc59839fd`.

## Symptôme

La page ne s’ouvre pas. Message : fonction `e()` inconnue. L’opérateur voit l’écran d’incident technique.

## Cause

La vue `views/admin/athena_tactical_map/index.php` échappe les textes avec `e()`, comme sur d’autres frameworks. Cette fonction n’existait pas dans Athena.

## Correctif

Ajout de `e()` dans les aides communes : elle échappe le HTML comme le reste du portail. La carte tactique peut s’ouvrir.

## Fichiers touchés

- `app/Support/helpers.php`
- `views/admin/athena_tactical_map/index.php` (inchangée, elle s’appuie désormais sur l’aide)
- `tests/Unit/AthenaTacticalMapDiAssetTest.php`

## Vérification

Ouvrir Opérations → Carte tactique : la page s’affiche, la carte Leaflet charge.

## Statut

Corrigé
