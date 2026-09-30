# Bus télémétrie Overwatch — Phase A (fondation)

Date : 2026-09-30  
Statut : livré (fondation)

## Objectif

Passer d’une file de **POST unitaires** à un **bus priorisé** avec lot HTTP unique, sans casser les routes existantes.

## Contrat Athena

| Méthode | Chemin | Rôle |
| --- | --- | --- |
| POST | `/api/atak/telemetry/batch` | Ingestion d’un lot d’événements |
| GET | `/api/atak/telemetry/events?after_id=` | Journal incrémental |
| POST | `/api/atak/position` (etc.) | **Secours** unitaire inchangé |

### Corps batch (exemple)

```json
{
  "seq": 18291,
  "ts": 1790795002,
  "mapId": 1,
  "events": [
    {"t":"pos","x":1831,"y":5420,"h":271,"call_sign":"N-10","extra":{"health":"ok"}},
    {"t":"veh","id":"B-27","state":"mounted","data":{...}},
    {"t":"weather","data":{...}},
    {"t":"sigint","x":10,"y":20,"bearing":71,"call_sign":"N-10"}
  ]
}
```

Types Phase A : `pos` / `position` / `heartbeat`, `veh` / `vehicle` / `vehicles`, `weather` / `wx`, `sigint`.

Réponse : `accepted`, `rejected`, `last_id`, `results[]`. Les positions réutilisent le pipeline `ingestPositionPayload` (mêmes effets collatéraux que `/position`).

## Extension (DLL 2.0.53+)

Fichier : `Extension_Telemetry.cs` (partial).

| Capacité | Comportement |
| --- | --- |
| Priorités | P0 critique (santé KO / liaison perdue), P1 live (pos / SIGINT), P2 état (véhicules), P3 bulk (météo) |
| Delta | Empreinte par clé ; skip si inchangé ; heartbeat position ~25 s |
| Coalescence | P1/P2 last-wins par clé ; P0/P3 files bornées |
| Drain | 1 lot / tick avant la FIFO legacy ; jitter ±10 % sur la période |
| Secours | HTTP 404/501/405 → mode `legacy` + refile unitaire |
| Métriques | `GetTelemetryMetrics` → depth, dropped, oldest_ms, latency_ms, ok/fail, delta_skip |
| Mode | `SetTelemetryMode` → `batch` \| `legacy` |

Les POST non batchables (marqueurs, terrain/scène/geo, vidéo) restent sur la file historique.

## Hors Phase A (suivantes)

- ~~EventBus SQF complet (EH Fired / Killed / GetIn…)~~ → **Phase B** (`telemetry-bus-phase-b.md`)
- Schéma `TacticalEvent` canonique multi-domaines
- ~~REALITY / OBSERVATION / TRACKS / COP~~ → **Phase D** (données) + **Phase E** (COP poste)
- ~~Position adaptative fine (immobile / course / aéronef)~~ → **Phase B**
- Circuit-breaker par endpoint nommé + latence p95 exposée au poste

## Vérification

- `dotnet build` sur `COMSPECExtension.csproj`
- PHPUnit `AtakTelemetryBatchPhaseATest`
- Smoke : position unitaire encore OK ; avec Athena à jour, la DLL envoie `/telemetry/batch`
