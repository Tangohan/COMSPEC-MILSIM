# ATAK web — plus aucune liaison (ping + APIs)

## Contexte

Après le calque planification et le correctif relief (`rows` / MariaDB), la carte web
ne démarre plus : message « Connexion impossible aux services de la Tacmap ».

## Symptôme

- Le sas de démarrage échoue (ping 12 s ou réponse non JSON).
- Ou le ping passe, mais unités / stats / relief / plan restent vides ou en 500.

## Cause

Plusieurs API de lecture se bloquaient ou renvoyaient une erreur serveur :

1. **Ping** — avant d’atteindre le contrôleur isolé, `public/index.php` ouvrait
   MySQL pour le mode maintenance. Un MySQL lent (ex. `ALTER` relief en cours)
   faisait dépasser le délai de boot.
2. **Relief** — `SELECT … grid_rows` alors que la colonne historique s’appelle
   encore `rows` (mot réservé). Un `ALTER CHANGE` HTTP réécrivait le blob
   d’altitudes et saturait les workers PHP.
3. **Plan de mission** — sondé dès le chargement de la page (avant le ping),
   en instanciant le service de cinématique BFT (migrations + relief).
4. **Unités / stats / whoami** — une exception SQL non rattrapée renvoyait 500
   au lieu d’une liste vide.

## Correctif

- Exempter `/api/atak/ping` de la sonde maintenance (aucun PDO).
- Lire `grid_rows` **ou** `` `rows` `` ; ne plus renommer la colonne en HTTP.
- Démarrer le plan de mission seulement après `atak:mapready`.
- Réponses de repli JSON (`ok: true`, listes vides) sur plan / stats / unités.

## Fichiers touchés

- `public/index.php`
- `bootstrap/atak_cop_terrain_migration.php`
- `bootstrap/atak_unit_motion_migration.php`
- `bootstrap/schema_ensure_column.php`
- `app/Repositories/AtakTerrainRepository.php`
- `app/Repositories/MissionPlanRepository.php`
- `app/Controllers/Api/AtakMissionApiController.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/MissionPlanning/MissionPlanningAtakService.php`
- `app/Core/Container.php`
- `app/Middleware/RequestTelemetryMiddleware.php`
- `public/assets/js/atak-mission-plan.js`
- `public/assets/js/atak-terrain.js`

## Vérification

- `php -l` sur les fichiers PHP listés.
- Relire : ping sans PDO ; SELECT relief sans mot `rows` nu ; poll plan après carte.

## Statut

corrigé (déploiement prod requis)
