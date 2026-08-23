# Constante TYPE_TACTICAL_ALERT manquante

## Contexte

Journal d’activité ATAK. `GET /api/atak/fire-teams` (et tout endpoint qui instancie `AtakActivityLogService`) en production.

## Symptôme

Erreur fatale : `Undefined constant self::TYPE_TACTICAL_ALERT` dans `AtakActivityLogService.php` ligne 80.

En production (athena.ttrd.fr, assets `v=1.5.7`), **toutes** les requêtes du poste de commandement et d’Arma qui passent par `AtakApiController` répondent 500 : unités, tchat, pings, charges, marqueurs, photos, ordres, météo, FRS, position, handshake Athena, etc. Ce n’est pas une série de pannes distinctes : le constructeur du contrôleur instancie `AtakActivityLogService` à chaque requête (`$this->activityLog ??= new AtakActivityLogService()`), donc le fatal bloque tout le canal.

Exemple Arma : `HTTP 500` sur `/public/api/chat`, `/public/api/atak/position`, transmission FRS (`request_id` `2add77951e2ac855`), handshake abandonné après 45 essais.

## Cause

Le filtre UI `FILTER_GROUPS` référence `self::TYPE_TACTICAL_ALERT` dans une constante de classe. Cette valeur n’était pas déclarée. PHP évalue les constantes de classe au chargement : le service ne peut plus être instancié, même pour une requête qui n’enregistre aucune alerte.

## Correctif

Déclarer `TYPE_TACTICAL_ALERT = 'tactical_alert'` (déjà utilisé par le contrôleur pour SALUTE / FRAGO / BDA / TIC).

## Fichiers touchés

- `app/Services/Tactical/AtakActivityLogService.php`

## Vérification

`php -l` sur le service : plus d’erreur. Le chargement de la classe ne doit plus lever d’exception.

Déployer `main` (PR #187, version 1.5.8) sur athena.ttrd.fr puis recharger le poste de commandement (assets `?v=1.5.8`). Les 500 généralisés doivent disparaître. Lancer aussi les migrations si la PR charges (#188) est dans le même déploiement.

## Statut

Corrigé dans le dépôt (PR #187 mergée). En attente de déploiement production si les assets affichent encore `v=1.5.7`.
