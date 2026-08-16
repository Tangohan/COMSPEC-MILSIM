# SSE Intelligence Workspace — LOT 7 (Robustesse)

Date : 2026-08-16

## Objectif

Renforcer la chaîne Arma ↔ Overwatch ↔ Athena sur six axes :

| Pilier | Contenu |
|--------|---------|
| **Offline** | File terrain persistée (profil), dédup, backoff, batch |
| **Sync** | Outbox serveur, accusés, reprise |
| **Idempotence** | Clés stables SQF + unique outbox / événements |
| **Conflits** | Même clé, payloads divergents → arbitrage humain |
| **Monitoring** | Santé + snapshot liaison dans le workspace |
| **Optimisation** | Plafonds file, batch flush, compaction cron |

Sans casser l’idempotence déjà présente sur `sse_intel_events`.

## Schéma

Migration : `bootstrap/atak_sse_robustness_lot7_migration.php` (déjà branchée dans `run-migrations.php`).

| Table | Rôle |
|-------|------|
| `sse_sync_outbox` | Messages rejouables (`idempotency_key` unique) |
| `sse_sync_conflicts` | Deux versions à arbitrer |
| `sse_job_locks` | Verrous TTL |

## Services

- `App\Services\Sse\SseSyncService` — enqueue (avec détection conflit), ack, health, `monitorSnapshot`, `optimize`
- Cron `SseSyncMaintenanceCronJob` (`sse_sync_maintenance`) — purge accusés > 7 j. + verrous expirés

## API

- `GET /api/sse/v1/health`
- `GET /api/sse/v1/sync/monitor`
- `POST /api/sse/v1/sync/optimize`
- `GET /api/sse/v1/sync/pending`
- `POST /api/sse/v1/sync/enqueue` — si même clé + payload différent → `conflict: true` + enregistrement conflit
- `POST /api/sse/v1/sync/ack`
- `GET /api/sse/v1/sync/conflicts`
- `POST /api/sse/v1/sync/conflicts/{id}/resoudre`

## Terrain (SQF)

| Fonction | Rôle |
|----------|------|
| `makeIdempotencyKey` | **Stable** (`PREFIX-recordId`) — plus de sel temporel |
| `queueOffline` | Dédup par clé, plafond 80, persist profil |
| `flushQueue` | Batch 8, max 5 tentatives, backoff, persist |
| `persistQueue` / `restoreQueue` | Survie au reload mission |
| `XEH_postInit` | Restore + flush 45 s + snapshot 180 s |

## UI

Panneau **Liaison terrain** (colonne contexte workspace) :

- état lisible (nominale / dégradée / indisponible) ;
- file d’attente, échecs, conflits.

## Rate-limit

Préfixe `/api/sse/` → 180 écritures / 60 s ; `GET /api/sse/v1/health` exempté du plafond GET anonyme.

## Vérification

1. Migrations LOT 7.
2. Rebuild `comspec_sse_network.pbo` (Arma fermé) puis mission : RPT `restoreQueue` / `network postInit OK — offline/sync LOT 7`.
3. Couper la liaison → soumettre une fiche → file persistée ; relancer → restore + flush.
4. `GET /api/sse/v1/sync/monitor` et panneau Liaison dans le workspace.
5. Deux enqueue même clé / payloads différents → conflit ouvert.
6. Cron `sse_sync_maintenance` purge les accusés anciens.

## Hors LOT 7 (reporté)

Replay AAR filtré, tableau ops multi-tenant temps réel, UI résolution de conflit guidée pas à pas.
