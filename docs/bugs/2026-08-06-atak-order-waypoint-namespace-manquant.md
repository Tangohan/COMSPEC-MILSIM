# ATAK — `Class App\Support\AtakOrderWaypoint not found`

## Contexte

API `GET /api/atak/orders` (sérialisation des ordres, dont points de mission `@WP:`).

## Symptôme

Erreur fatale en production :

> Class "App\Support\AtakOrderWaypoint" not found

Fichier / ligne : `AtakApiController.php` → `serializeOrder()` (appel `AtakOrderWaypoint::parse`).

Corrélations signalées : `6788633e9766f578`, `26d94765a32ac308` (2026-08-06).

## Cause

`app/Support/AtakOrderWaypoint.php` déclarait la classe **sans** `namespace App\Support;`.

Le contrôleur importe `use App\Support\AtakOrderWaypoint;`, donc l’autoload PSR cherche `App\Support\AtakOrderWaypoint`, alors que le fichier enregistrait la classe dans le namespace global → « class not found ».

## Correctif

Ajouter `namespace App\Support;` en tête de `AtakOrderWaypoint.php`.

Même défaut constaté et corrigé sur `ArmaMarkerLabel.php` (déjà listé dans `tools/audit-integrite.php`).

## Fichiers touchés

- `app/Support/AtakOrderWaypoint.php`
- `app/Support/ArmaMarkerLabel.php`

## Vérification

- [x] Classe `App\Support\AtakOrderWaypoint` charge via `bootstrap/autoload.php`
- [x] `namespace App\Support;` présent (idem `ArmaMarkerLabel.php`) — revérifié 2026-08-06
- [x] `php -l` OK
- [ ] Déployer les fichiers en production (`app/Support/AtakOrderWaypoint.php`, `app/Support/ArmaMarkerLabel.php`)
- [ ] Relancer `GET /api/atak/orders?mapId=…` → 200 sans erreur

## Statut

corrigé — déployer `AtakOrderWaypoint.php` et `ArmaMarkerLabel.php` en production
