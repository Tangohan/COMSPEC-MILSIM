# COMSPEC Extension — Rapport performance (Lots A+B / 2.0.109)

**Date :** 2026-10-28  
**Réf. :** `DLL_WORKFLOW_AUDIT.md`, `DLL_OPTIMIZATION_PLAN.md`  
**Pack :** ATAK **2.9.18** · Link **2.0.109** (Steam Workshop 3684656708)

---

## Mesures réalisées

| Mesure | Résultat |
| --- | --- |
| `dotnet publish` Native AOT (Link + ATAK native) | **OK** via `build_mod.bat` |
| Signature BI des 5 PBO | **OK** (`comspec_atak_native`) |
| Push Steam Workshop | **OK** (2026-10-28) |
| Assertions sources OPT-01..08 (`ComspecLinkWorkflowOptTest`) | Présentes en sources |
| Session Arma / FPS / hitch | **Non réalisée** |
| Charge 10 / 30 / 60 joueurs | **Non réalisée** |
| Coupure réseau / 429 / 503 prolongés | **Non réalisée** |
| Benchmark avant/après latence HTTP | **Non réalisée** — aucun chiffre inventé |

---

## Changements livrés (qualitatifs)

| OPT | Effet attendu (non mesuré en jeu) | Version |
| --- | --- | --- |
| OPT-01 | Plafond 8 connexions/hôte | ≤2.0.107 |
| OPT-02 | BACKGROUND : playtime / viewshed / relays / rf-hits / explosive-timers → FIFO | ≤2.0.107 |
| OPT-03 | PageIdCache TTL + plafond + clear Cancel | ≤2.0.107 |
| OPT-04 | Prune PollGet / EndpointCooldowns / TrafficChannels | ≤2.0.107 |
| OPT-05 | `MemStats` : profondeurs files | ≤2.0.107 |
| OPT-06 | PRIORITY : chat / CAS / intel / pings / designator / laser / sigint / recon → FIFO | 2.0.108 |
| OPT-07 | `MapCap.Scan` → `OK\|queued` + `ScanPoll` ; SQF hors fil image | 2.0.109 |
| OPT-08 | Auth password / OTP / Steam / ConnectC2 → spawn + `extAwait` | 2.0.109 |

Auth `ERR|use_async` / Connect dual **inchangés**. OPT-09 (ordonnanceur unique) et OPT-10 (campagne charge) **différés**.

---

## Risques résiduels

1. Latence +1 tick drain sur Relais / RF / messagerie PRIORITY (acceptable vs FAF).  
2. `MapCap.Scan` concurrent : un seul Scan à la fois (`OK|busy`) — second sync attend ou relance.  
3. Auth UI : boutons spawnés — double-clic possible pendant l’attente (état « en cours » affiché).  
4. Preuve quantitative en session : à faire après embarquement joueurs.

---

## Synthèse

Lots A+B compilables Native AOT, pack signé et publié. Stabilisation = bornage HTTP + files + caches + Scan/auth hors fil jeu. OPT-09 seulement si famine mesurée malgré A+B.
