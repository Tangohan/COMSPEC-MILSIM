# COMSPEC Extension — Audit workflows & performance

**Date :** 2026-10-27  
**Périmètre :** `mod/UptoDate/COMSPECExtension` (Native AOT net8.0) + interactions SQF / Athena  
**Version auditée :** Link **2.0.106** (baseline) → Lot A appliqué en **2.0.107** (voir plan / rapport perf).  
**Méthode :** lecture de code uniquement. **Aucun FPS / hitch inventé.** Arma non lancé. Benchmarks charge longue : **non réalisés**.

---

## 1. Cartographie des workflows

```text
callExtension (fil jeu / simu)
    │
    ├─ TryGetSyncResponse / RvExtensionArgsImpl
    │     ├─ AsyncLocalCommands → Task.Run (hors AsyncGate) → EnqueueOrSend / disque
    │     ├─ Async + HTTP → AsyncGate(6) → SendTimed / SendGet / SendJsonPost
    │     ├─ ServePollGet → cache + ThreadPool refresh
    │     └─ Auth / Admin / Connect (contrat ERR|use_async hors worker)
    │
    ├─ EnqueueOrSend
    │     ├─ Telemetry batch (pos / weather / sigint / veh / flight / logistics)
    │     ├─ FIFO PendingPosts (≤500) + position coalescée + marqueurs
    │     ├─ Analytics / heavy / video → EnqueueForRetry (2.0.106)
    │     └─ reste → POST fire-and-forget (HttpClient partagé)
    │
    └─ DrainTimer → DrainQueue → Task.Run async (1 drain max)
              ├─ TryDrainTelemetryBatchAsync
              └─ TrySendQueuedPostAsync (≤3 FIFO / tick)
```

| Catégorie produit | Chemins actuels | Bornage |
| --- | --- | --- |
| CRITICAL (auth, admin) | Async + `InAsyncWorker` ; sync login hors Async = risque hitch | Timeouts 8 s |
| HIGH (BFT) | Telemetry batch + coalesce position + drain async | Caps P0–P3 + FIFO 500 |
| NORMAL (marqueurs, CAS…) | Marqueurs coalescés ; CAS/chat encore FAF | Marqueurs ≤1000 clés |
| BACKGROUND (analytics, ingest) | Analytics queue 2.0.106 ; Scene/Terrain/Geo → QueueHeavyIngest | Oui |
| BEST EFFORT (vidéo, météo) | Best-effort + cooldown ; video en file | Oui |

---

## 2. Problèmes démontrables (résiduels)

### P1 — POST fire-and-forget encore larges

Après `IsAnalyticsQueuedEndpoint` / heavy / telemetry, **EnqueueOrSend** lance encore `SendAsync`+`ContinueWith` pour notamment : chat, playtime, viewshed, pings, intel, designator, rf-hits, relays, CAS status/ack, laser-codes, explosive-timers (`Extension.cs` bras défaut ~2477+).

**Impact :** concurrence sur le même `HttpClient` que le drain BFT → latence positions / faux timeouts sous Athena lent.  
**Preuve :** branche `EnqueueOrSend` après les early-outs queue ; sites d’appel listés audit concurrent.

### P2 — `MaxConnectionsPerServer` non plafonné

`CreateHttpClient` (`SocketsHttpHandler`) sans `MaxConnectionsPerServer` → défaut .NET, ouverture large sous FAF + polls + drain + AsyncGate(6).

**Impact :** saturation côté Athena / OS ; starvation BFT.  
**Preuve :** `Extension.cs` ~136–167.

### P3 — Caches / maps sans plafond dur

| Structure | Manque | Fichier |
| --- | --- | --- |
| `PollGetCache` | Pas de max clés / TTL global | Extension.cs |
| `EndpointCooldowns` | Pas de prune des entrées expirées | Extension.cs |
| `TrafficChannels` | Croissance avec diversité d’URLs | Extension_SyncStats.cs |
| `GoogleSlidesDeck.PageIdCache` | Jamais invalidé / pas de TTL | GoogleSlidesDeck.cs |
| `FeatureLatch*` | Pas de max clés (pratique bornée) | Extension_RefusalLatch.cs |

### P4 — `GetAwaiter().GetResult()` sur chemins sync

`SendTimed` (~2580), GameAuth `GamePostJson`/`GameGetJson`, ImageDataChunk offset 0, redeem/Steam/beta, chat channel create/delete, SyncStats body read.

**Impact fil jeu :** seulement si SQF appelle hors `Async`/`extAwait`. Login password/OTP/Steam/ConnectC2 encore sync côté SQF.  
**Hors fil jeu :** OK fonctionnel, bloque un worker jusqu’à 8 s.

### P5 — Google Slides : cache page IDs stale

`PageIdCache` rempli une fois, jamais vidé au `Cancel` ni après édition du deck Google.  
**Impact :** diapositives obsolètes jusqu’au redémarrage Arma.

### P6 — MapCap.Scan sync sur fil jeu

`MapCap.Scan` parcours disque synchrone ; SQF `fn_mapCapture.sqf` via `extensionCall` sync.  
**Impact :** hitch UI à l’ouverture capture (démontrable par nature I/O sync).

### P7 — Course session / corps HTTP

Champs `_baseUrl` / tokens sans snapshot atomique multi-champs ; lectures body `GetResult` multiples possibles sur une même `HttpResponseMessage` (maintenance / Retry-After / refusal).  
**Impact :** rare ; reconnect / multi-handlers erreur.

### P8 — Observabilité workflow incomplète

`MemStats` = WorkingSet/GC seulement ; `GetTelemetryMetrics` / `GetPendingQueueCount` / `SyncStats` existent mais **pas** de compteur unifié retries / FAF / profondeur poll / tâches actives exporté d’un coup.

---

## 3. Déjà mitigé (ne pas re-flaguer)

| ID | Mesure | Version |
| --- | --- | --- |
| F-DLL-02/05 | AsyncLocalCommands + Task.Run hors gate | ≤2.0.105 |
| F-DLL-06 | Drain async `_drainInFlight` | 2.0.105 |
| F-DLL-08 | Cache octets ImageDataChunk | 2.0.105 |
| F-DLL-11 | Analytics → FIFO | 2.0.106 |
| ImageDataChunk AsyncLocal | Hors AsyncGate briefing | 2.0.106 |
| Telemetry batch + coalesce pos/markers | Caps + last-wins | Phase A |
| ServePollGet | GET hors fil jeu | historique |
| Refusal latch / cooldowns 5xx | Pas de retry infini auth | historique |

**DO NOT TOUCH (contrat) :** `ERR|use_async` (SelfTest, StartupCheck, AdminControl, GameAdmin, Server.Presence) ; dual path `Connect` / `VerifyClientInitSync`.

---

## 4. Interactions SQF (résumé)

- Hot polls Overwatch : sync `callExtension` **volontaire** via `ServePollGet` (cache).
- Hot Tx ATAK : majoritairement `extAwait` ; filet sync si `!canSuspend` / sans EH (F-DLL-04 produit).
- Auth login encore sync HTTP si pas wrappé Async.

---

## 5. Risques non démontrés (exclus)

- Deadlock Monitor sur drain (supprimé en 2.0.105).
- Fuite FileSystemWatcher non prouvée sans session longue.
- FPS inventés — **aucun chiffre de perf produit ici**.

---

## 6. Verdict audit

Architecture déjà stratifiée (batch / FIFO / coalesce / AsyncGate). Le gain mesurable restant est **bornage HTTP + élargissement des files BACKGROUND + invalidation caches**, pas un ordonnanceur central monolithique. Auth/session : machine à états GameAuth présente ; transitions documentées — **pas de refonte** sans décision produit.
