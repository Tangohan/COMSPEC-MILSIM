# Carte tactique dossier SSE — snapshot + calque ATAK

## Contexte
Le panneau « Carte tactique » d’un dossier SSE ne faisait qu’afficher une Leaflet OSM
centrée sur Paris, avec une capture PNG versée en pièce. Aucune donnée permanente,
aucun ping, aucun lien avec la Tacmap ATAK.

## Symptôme
Impossible de mémoriser une vue ou des pings par dossier ; rien à superposer côté ATAK.

## Cause
Pas de schéma `sse_case_map_*`, JS inline de capture seule, pas de calque Tacmap dédié.

## Correctif
- Tables `sse_case_map_state` (vue + flag calque) et `sse_case_map_features` (pings)
- UI interactive : clic pour placer, mémorisation auto de la vue, capture enrichie
- Coordonnées terrain optionnelles pour publication ATAK
- Calque Tacmap « Dossiers SSE » (`GET /api/atak/sse-case-overlay`)

## Fichiers touchés
- `bootstrap/atak_sse_case_map_migration.php`, `run-migrations.php`
- `app/Repositories/SseCaseMapRepository.php`
- `app/Controllers/Web/SsePortalController.php`, `app/Controllers/Api/AtakApiController.php`
- `routes/web.php`
- `views/atak/sse/case_show.php`, `public/assets/js/sse-case-map.js`
- `views/tacmap.php`, `public/assets/js/comspec-operational-map.js`
- `public/assets/css/sse_portal.css`

## Vérification
1. Ouvrir un dossier → placer un ping → recharger : le point est toujours là
2. Cocher « Publier… calque ATAK » + renseigner X/Y terrain
3. Sur `/tacmap`, activer « Dossiers SSE » : le point apparaît
4. Capturer la vue : pièce + métadonnées de vue enregistrées

## Statut
corrigé
