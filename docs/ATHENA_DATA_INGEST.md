# Athena Data Ingest V2

## Architecture

Athena V2 complète les routes ATAK historiques sans les remplacer. Le contrôleur `AthenaDataApiController`, le validateur sans état `AthenaEventValidator` et `AthenaDataRepository` forment le pipeline. La clé ATAK de communauté existante résout le tenant ; aucune clé n'est envoyée au navigateur.

Le stockage sépare strictement :

* **state** (`athena_live_state`) : dernier état par tenant, source, type et entité. Les types `position.*`, `bft.*`, `weather.*`, `terminal.heartbeat`, `entity.state`, `radio.state` et `drone.state` sont coalescés ;
* **event** (`athena_events`) : faits opérationnels durables, notamment créations, mises à jour, suppressions, sync, terrain, scènes, photos et renseignement ;
* **projections** : `athena_map_objects`, chunks terrain, snapshots de scène, sources et métriques.

Le endpoint est `POST /api/atak/v2/events`. Il accepte un événement seul ou `{ "events": [...] }`, au plus 250 événements, 256 KiB par événement et 8 MiB par requête. Les anciennes routes `/api/atak/*` restent compatibles.

## Authentification et isolation

Présenter une clé ATAK **propre à la communauté** via `Authorization: Bearer <secret>`, `X-COMSPEC-KEY` ou `X-ATAK-TOKEN`. Le mécanisme Athena existant compare les secrets en temps constant et résout le tenant. Une clé plateforme non rattachable à un tenant reçoit `403 tenant_context_required`. Les échecs ne journalisent jamais la valeur du secret.

Le Data Inspector et ses API de lecture utilisent la session web et les permissions administrateur existantes (`admin.access`, `admin.organization` ou `admin.system`). Le générateur DEV exige en plus un token CSRF et renvoie 404 en production.

## Enveloppe événement

```json
{
  "schema": "athena.event.v1",
  "event_id": "evt_01J7A18N6F12A7D9F5C8",
  "type": "marker.created",
  "timestamp": "2026-09-04T17:12:31.284Z",
  "source": {
    "terminal_id": "ATAK-01",
    "callsign": "N-10",
    "source_type": "arma3",
    "mod_version": "2.0.0",
    "extension_version": "2.0.0"
  },
  "context": {"world":"Altis","mission":"OP_FALCON","server":"COMSPEC-01"},
  "pipeline": {"generated_at":"2026-09-04T17:12:31.284Z","received_at":"2026-09-04T17:12:31.300Z","queued_at":"2026-09-04T17:12:31.301Z"},
  "payload": {"id":"marker_42","x":14230.5,"y":18320.1,"z":52.3,"version":1}
}
```

`event_id` doit être stable entre les retries. Le couple tenant/event ID est unique. Un doublon est ACK dans `accepted` et signalé dans `known`, sans nouvelle écriture métier. Tous les timestamps sont ISO-8601 UTC. Les timestamps pipeline inconnus doivent être omis, jamais fabriqués.

## Objets cartographiques

Types normalisés : `marker`, `drawing`, `route`, `zone`, `poi`, `intel`, `contact`. Les actions sont `.created`, `.updated`, `.deleted`. Le payload porte `id`, `world` (ou le contexte), coordonnées monde Arma `x/y/z`, `heading`, `label`, `scope`, `persistent`, `geometry`, `style`, `metadata`, `version`. Les scopes sont `local`, `session`, `group`, `mission`, `server`, `tenant`.

Les suppressions produisent un tombstone (`deleted_at`, `deleted_by`). Une version cliente inférieure à la version serveur produit un conflit, sans écrasement. Une mise à jour acceptée incrémente la version serveur. Les coordonnées de référence sont toujours les mètres du monde Arma, jamais des pixels.

## Terrain, scènes et fichiers

`terrain.chunk.received` projette un chunk (`chunk_id`, `layer`, `bounds`, `status`, `hash`, `storage_ref`, `metadata`). `status` vaut `unknown`, `partial`, `complete` ou `error`. `scene.ingested` projette un snapshot (`snapshot_id`, `object_count`, `bounds`, `hash`, `storage_ref`). Les objets massifs et photos doivent être déposés dans le stockage fichier existant ; l'événement ne transporte que `storage_ref`, hash et métadonnées. Envoyer des chunks, jamais des milliers d'événements objet-terrain.

## Offline, batching et rétention

Le client coalesce localement les états fréquents avant resynchronisation, conserve les événements critiques et les renvoie en batch chronologique avec leurs IDs originaux. En cas de coupure avant ACK, il renvoie exactement le même batch. Les conflits sont traités objet par objet.

Politique préparée : live state = dernière valeur ; position history = échantillonnage futur configurable ; raw/debug = rétention courte future ; événements opérationnels = longue durée ; terrain = persistant. La purge doit être introduite par configuration de déploiement et non codée dans le client.

## Réponses et erreurs

* `200` : batch traité totalement ou partiellement ;
* `401` : secret absent/invalide ; `403` : tenant non résolu ;
* `413` : requête > 8 MiB ; `422` : enveloppe/batch invalide, conflit, ou aucun item accepté ;
* `500/503` : panne temporaire, retry avec backoff.

La réponse contient toujours les IDs acceptés, connus, rejetés, les conflits et `server_time`. Un rejet expose des codes machine (`invalid_event_id`, `invalid_timestamp`, etc.), jamais un secret.

## Types initiaux

State : `position.updated`, `bft.updated`, `weather.updated`, `terminal.heartbeat`, `entity.state`, `radio.state`, `drone.state`. Events : `marker|drawing|route|zone|poi|intel|contact.created|updated|deleted`, `photo.created`, `sync.started`, `sync.completed`, `terrain.chunk.received`, `scene.ingested`. Le validateur accepte aussi les nouveaux noms conformes au format afin de permettre une évolution additive.
