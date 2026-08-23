# Constante TYPE_TACTICAL_ALERT manquante

## Contexte

Journal d’activité ATAK. `GET /api/atak/fire-teams` (et tout endpoint qui instancie `AtakActivityLogService`) en production.

## Symptôme

Erreur fatale : `Undefined constant self::TYPE_TACTICAL_ALERT` dans `AtakActivityLogService.php` ligne 80. La carte / les équipes de feu ne se chargent plus.

## Cause

Le filtre UI `FILTER_GROUPS` référence `self::TYPE_TACTICAL_ALERT` dans une constante de classe. Cette valeur n’était pas déclarée. PHP évalue les constantes de classe au chargement : le service ne peut plus être instancié, même pour une requête qui n’enregistre aucune alerte.

## Correctif

Déclarer `TYPE_TACTICAL_ALERT = 'tactical_alert'` (déjà utilisé par le contrôleur pour SALUTE / FRAGO / BDA / TIC).

## Fichiers touchés

- `app/Services/Tactical/AtakActivityLogService.php`

## Vérification

`php -l` sur le service : plus d’erreur. Le chargement de la classe ne doit plus lever d’exception.

## Statut

Corrigé
