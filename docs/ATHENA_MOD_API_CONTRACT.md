# Contrat Mod → Athena Data API V2

**Version du contrat : 1.0 — 2026-09-04.** Ce document est la référence à transmettre au développeur Cursor du mod/extension. Le portail est prêt ; le mod Arma/SQF et l'extension C# ne font pas partie de cette modification.

## Requête obligatoire

```http
POST /api/atak/v2/events HTTP/1.1
Authorization: Bearer <ATHENA_TENANT_ATAK_KEY>
Content-Type: application/json
Accept: application/json
User-Agent: Athena-Arma-Extension/<version>
```

`X-COMSPEC-KEY` ou `X-ATAK-TOKEN` peuvent remplacer Bearer pour compatibilité. Utiliser la clé ATAK **du tenant**, jamais la clé plateforme. Ne jamais mettre la clé dans le JSON, les logs, `event_id`, URLs ou captures UI. TLS est obligatoire en production.

### Limites

* 1 à **250** événements par batch ; **256 KiB** maximum par événement ; **8 MiB** maximum par requête.
* UTF-8 JSON objet, `Content-Type: application/json`.
* Toutes les dates ISO-8601 UTC avec `Z`, idéalement millisecondes.
* Coordonnées `x/y/z` en mètres monde Arma. Ne jamais envoyer des pixels écran.

## JSON exact

Unitaire : l'objet événement ci-dessous directement. Batch recommandé :

```json
{
  "events": [
    {
      "schema": "athena.event.v1",
      "event_id": "evt_01J7A18N6F12A7D9F5C8",
      "type": "position.updated",
      "timestamp": "2026-09-04T17:12:31.284Z",
      "source": {
        "terminal_id": "ATAK-01",
        "callsign": "N-10",
        "source_type": "arma3",
        "mod_version": "2.0.0",
        "extension_version": "2.0.0"
      },
      "context": {"world":"Altis","mission":"OP_FALCON","server":"COMSPEC-01"},
      "pipeline": {
        "generated_at":"2026-09-04T17:12:31.284Z",
        "received_at":"2026-09-04T17:12:31.298Z",
        "queued_at":"2026-09-04T17:12:31.300Z",
        "request_at":"2026-09-04T17:12:31.400Z"
      },
      "payload": {"entity_id":"unit_B_alpha_1","x":14230.5,"y":18320.1,"z":52.3,"heading":122.0,"speed":4.2}
    }
  ]
}
```

Obligatoires : `schema`, `event_id` (8–128 caractères `[A-Za-z0-9._:-]`), `type`, `timestamp`, `source.terminal_id`, `source.source_type`, `payload` objet. `context` et `pipeline` sont facultatifs mais recommandés. Omettre tout timestamp inconnu : ne pas l'estimer.

## Types et payloads

| Classe | Types | Règle serveur |
|---|---|---|
| État | `position.updated`, `bft.updated`, `weather.updated`, `terminal.heartbeat`, `entity.state`, `radio.state`, `drone.state` | Upsert du dernier état par source/type/entity ; pas d'historique permanent haute fréquence |
| Carte | `{marker,drawing,route,zone,poi,intel,contact}.{created,updated,deleted}` | Événement durable + projection versionnée/tombstone |
| Fichier | `photo.created` | Le payload référence `storage_ref`; pas de gros binaire inline |
| Terrain | `terrain.chunk.received` | Chunk agrégé, hash/idempotence |
| Scène | `scene.ingested` | Snapshot, pas un événement par objet |
| Sync | `sync.started`, `sync.completed` | Événement durable avec résumé |

Payload carte :

```json
{"id":"marker_uuid","world":"Altis","subtype":"enemy_observation","x":14230.5,"y":18320.1,"z":52.3,"heading":122,"label":"OBS ENI","scope":"mission","persistent":true,"style":{},"metadata":{},"version":4}
```

Scopes reconnus : `local`, `session`, `group`, `mission`, `server`, `tenant`. N'envoyer au serveur que les scopes synchronisables. Pour suppression, conserver le même `id`, envoyer `.deleted` et la dernière `version` connue. Terrain : `chunk_id`, `layer`, `bounds`, `status` (`unknown|partial|complete|error`), `hash` SHA-256, `storage_ref`, `metadata`. Scène : `snapshot_id`, `object_count`, `bounds`, `hash`, `storage_ref`.

## ACK

```json
{
  "accepted": ["evt_new", "evt_already_known"],
  "known": ["evt_already_known"],
  "rejected": [{"index":2,"event_id":"evt_bad","errors":["invalid_payload"]}],
  "conflicts": [{"event_id":"evt_conflict","entity_id":"marker_uuid","client_version":4,"server_version":6}],
  "server_time": "2026-09-04T17:12:31+00:00"
}
```

Retirer de la file locale tous les IDs `accepted`, y compris `known`. Corriger/mettre en quarantaine `rejected`. Pour un `conflict`, ne jamais réémettre aveuglément : récupérer/présenter la version serveur dans une future phase de résolution. Un batch partiellement valide peut répondre 200 ; traiter chaque tableau, pas seulement le code HTTP.

## Codes HTTP et retry

| Code | Signification | Action extension |
|---|---|---|
| 200 | ACK total/partiel | Appliquer les listes |
| 401 | clé absente/invalide | Stopper, demander ré-enrôlement ; pas de boucle |
| 403 | clé sans tenant | Stopper, utiliser une clé communauté |
| 413 | batch trop gros | Scinder immédiatement |
| 422 | JSON/batch invalide ou aucun item accepté | Corriger/quarantiner ; ne pas boucler inchangé |
| 429 | rate limit | Respecter `Retry-After`, sinon backoff |
| 500/503 | panne temporaire | Retry idempotent avec jitter |

Backoff recommandé : 1 s, 2 s, 5 s, 10 s, 30 s, puis 60 s maximum avec ±20 % de jitter. Timeout connexion 5 s, requête 20 s. Après timeout/réponse perdue, renvoyer **les mêmes `event_id`**. Ne générer un nouvel ID que pour un nouveau fait logique.

## Offline

1. Persister localement les événements critiques avec event ID avant envoi.
2. Coalescer position/BFT/météo/heartbeat par entité : ne conserver que le dernier état hors ligne.
3. Au retour réseau, envoyer d'abord heartbeat/sync.started, puis événements critiques chronologiques par lots, puis états coalescés et sync.completed.
4. Exemple `sync.completed.payload` : `{"sync_id":"sync_uuid","state_updates_coalesced":54,"markers":12,"drawings":8,"routes":4,"intel":3,"photos":1}`.
5. Attendre l'ACK avant suppression locale. Les tombstones doivent rester dans la file comme tout événement critique.

## Points à connecter côté Cursor

Générer un ID durable par événement ; renseigner versions mod/extension et contexte ; implémenter queue disque, coalescence d'état, batching/limites, retry/ACK ; envoyer les timestamps réellement mesurés ; transformer les marqueurs/dessins/routes/zones en coordonnées monde ; uploader les blobs ailleurs puis envoyer `storage_ref` ; transmettre heartbeat et résumés sync. Aucun changement de l'ancien protocole n'est requis pour les versions déjà déployées.
