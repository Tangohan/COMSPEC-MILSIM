# COMSPEC Extension — Plan d’optimisation

**Réf. audit :** `DLL_WORKFLOW_AUDIT.md`  
**Principe :** lots ciblés, Native AOT, contrats SQF inchangés, pas de refonte auth/Connect.

---

## Priorisation

| Prio | ID | Action | Bénéfice | Complexité | Risque |
| --- | --- | --- | --- | --- | --- |
| **A** | OPT-01 | `MaxConnectionsPerServer` + pool lifetime sur `CreateHttpClient` | Contient saturation Athena / starvation BFT | Faible | Faible |
| **A** | OPT-02 | File BACKGROUND : playtime, viewshed, relays, rf-hits, explosive-timers | Moins de FAF concurrents | Faible | Faible (latence +1 drain tick) |
| **A** | OPT-03 | `PageIdCache` TTL + plafond + clear Cancel | Diapos cohérentes ; mémoire bornée | Faible | Faible |
| **A** | OPT-04 | Prune `PollGetCache` / `EndpointCooldowns` / soft-cap `TrafficChannels` | Stabilité multi-heures | Faible | Faible |
| **A** | OPT-05 | Enrichir `MemStats` (profondeurs files, drain, poll) | Observabilité sans secret | Faible | Nul (append format) |
| **B** | OPT-06 | Chat / CAS / intel : file prioritaire dédiée (pas drop silencieux) | Latence contrôlée sans FAF | Moyenne | **Fait en 2.0.108** (`IsPriorityQueuedEndpoint`) |
| **B** | OPT-07 | `MapCap.Scan` → `OK\|queued` + Poll résultat | Fil jeu léger | Moyenne | **Fait en 2.0.109** (`MapCap.ScanPoll`) |
| **B** | OPT-08 | Auth login SQF → extAwait (password/OTP/Steam/C2) | Moins de freeze lobby | Moyenne | **Fait en 2.0.109** (spawn + extAwait) |
| **C** | OPT-09 | Ordonnanceur unique CRITICAL/HIGH/… | Unification | Haute | **Élevé** — différé : les files actuelles suffisent si OPT-01..05 |
| **C** | OPT-10 | Tests charge 10–60 joueurs / 429 / coupure | Preuve longue durée | Haute | — (infra) |
| **—** | — | Toucher `ERR\|use_async` / Connect dual | — | — | **Interdit** sans design |

---

## Lot A (cette vague) → Link **2.0.107**

1. OPT-01 … OPT-05 dans les sources.  
2. Alignement `ExtensionVersion` / `.csproj` / `LINK_VERSION_STR`.  
3. DevDispatch bulletin liaison.  
4. Build Release ; publish Native AOT si environnement OK.  
5. Tests PHP asset existants (alignement versions).  
6. `DLL_PERFORMANCE_REPORT.md` : mesures **disponibles seulement** (build) ; charge Arma = non réalisée.

## Lot B → Link **2.0.109** / ATAK **2.9.18**

OPT-06 (2.0.108) + OPT-07/08 (2.0.109) : MapCap.Scan async + auth SQF extAwait. OPT-09/10 toujours différés.

## Lot C

OPT-09 seulement si mesure charge montre famine malgré A+B. OPT-10 = campagne QA dédiée.
