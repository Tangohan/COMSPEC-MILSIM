ATHENA C2
TECHNICAL PUBLICATION

Document: ATHENA C2 — Standard Operating Procedures
Reference: SOP-A3-01
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value          |
| --------------- | -------------- |
| Document ID     | SOP-A3-01      |
| Revision        | 1.0            |
| Status          | CONTROLLED     |
| Owner           | COMSPEC        |
| System          | ATHENA C2      |
| Last Review     | 2026-09-02     |
| Source of Truth | Git repository |

## Revision History

| Revision | Date       | Author  | Changes                        |
| -------- | ---------- | ------- | ------------------------------ |
| 1.0      | 2026-09-02 | COMSPEC | Initial controlled publication |

---

# ATHENA C2 — Standard Operating Procedures

These procedures assume capabilities listed as **FIELDED** in REG-A3-01. They do not instruct operators to rely on a disk-persistent offline queue, Lat/Long conversion, or a dedicated JTAC 9-line CAS form.

Operator how-to detail: TM-A3-21. Administrator configuration: TM-A3-31.

---

## SOP-01 — Mission preparation

### 1.1 Community and workspace

1. Confirm the **community (tenant)** that will own the serial. Operators must belong to that community on ATHENA.
2. On the portal, open **Operations** if a planning workspace is required (`/operations`). Create or select the operation. This workspace is **planning**. It is not selected inside Arma.
3. Confirm the **map** used by Tacmap matches the Arma world. Live Overwatch traffic currently posts **map id 1** unless an API client overrides `mapId`. Treat map 1 as the live COP map unless the administrator has a verified exception.
4. Confirm the community plan allows the ATAK web map (feature `atak`, Standard+). The game liaison uses the community access key / game session and is not the same gate.

### 1.2 Tenant control

1. Community administrator: open ATAK configuration for the community (access key, C2 URL, maintenance flag, default map slug).
2. Do not instruct players to enter a numeric `tenant_id`.
3. Do not use tenant 1 as a “default community”. If the instance is truly mono-tenant, only then may `ATAK_DEFAULT_TENANT_ID` be set in the server environment (TM-A3-31).

### 1.3 Map verification

1. Open Tacmap (`/atak`) while logged into the correct community.
2. Confirm the world, tiles and scale. Coordinates are Arma metres on CRS Simple.
3. Confirm no leftover markers from a previous serial if the map is shared.

### 1.4 Arma configuration

1. Load **CBA** and **@COMSPECOverwatch** (COMSPECExtension_x64.dll at pack root).
2. Optional but common: ACE, cTab / ATAK Enhanced, BCE (enables `atak_athena` bridges).
3. CBA: enable Overwatch, set API URL (portal, typically `https://athena.ttrd.fr/public` if unset), do **not** treat `comspec_overwatch_tenant_id` as authority.
4. Confirm indicatifs: ATHENA profile callsign is the BFT identity, not the community title.

### 1.5 Extension and liaison test

1. Start Arma, join the mission (client state in-game, not briefing).
2. Authenticate (Steam if already linked, or e-mail/password / OTP via Connexion Athena).
3. Confirm link state **linked** (ACE / Athena tile / status badges).
4. Confirm the extension answers `Ping` / version (TM-A3-21 diagnostic).
5. Move; confirm the operator appears on Tacmap within a few seconds (position send is gated; TTL live is 120 s).

### 1.6 PLI, chat, contacts, health

| Check | Pass criterion |
| ----- | -------------- |
| PLI | Own contact moves on Tacmap |
| Chat | Message from field appears in Tacmap journal (and the reverse after poll, ~6 s in game) |
| Contacts | Other linked operators visible; proxies (phone/AI/GPS) labelled as such |
| Health | `GET /api/atak/ping` reachable; no community ATAK maintenance 503 |

Do not start the serial if the operator is on the wrong community or the picture is another tenant’s map.

---

## SOP-02 — Establishing the COP

1. **Contacts** — linked operators only. Do not treat unlinked players as on the picture.
2. **Units** — BFT icons use callsign + role. Group label is tactical identity, not the community name.
3. **Markers** — draw only what the serial needs. Overwatch sends map markers to the API and polls Athena markers back (~8 s).
4. **Zones** — tactical zones / coverage if used; check-position is FIELDED.
5. **Intel** — photos, FRS/FRM, tactical reports. Legacy `POST /api/atak/intel` exists; prefer the fielded report/photo paths.
6. **Symbol discipline** — same colours/types as the marker library (portal documentation). Temporary marks stay temporary until TOC confirms.
7. **Temporary vs confirmed** — pings expire operationally; confirmed markers stay until deleted.

---

## SOP-03 — In operation

| Traffic | Who | What to do |
| ------- | --- | ---------- |
| PLI | All linked operators | Keep terminal / required item if CBA requires it. Heartbeat continues when still. |
| Markers | Field and TOC | Create/move/delete on the Arma map or Tacmap; expect seconds of delay. |
| Intel / photos | Field | Recon / camera; photos associate last known grid when the extension has a pose. |
| CHAT | Field and TOC | Operational chat is the C2 journal, not the Arma side chat. |
| PING | Field | ACE “Envoyer Ping” or Tacmap ping. |
| JTAC | JTAC | Laser codes and designator as fielded. CAS request is an order/chat path, not a full NATO 9-line CAS form. |
| CAS | Pilot / JTAC | Pilot line checks and status (`SendCASCheckLine`, ack/status). |
| 9-Line | Medic / leader | **MEDEVAC** 9-line (`RequestMEDEVAC`) is the fielded 9-line. |
| Flight Manifest | Aircrew | Dialog / tablet / automatic crewed-air report. |
| Designator | JTAC | Laser target position when the designator path is used. |

TOC watches medical alerts, orders, waypoints and video feeds as needed. Polling on the web map is on the order of 4–12 s depending on layer (ICD-A3-01).

---

## SOP-04 — Loss of liaison

There is **no durable offline store**. The extension retries in memory (coalesced last position; up to 500 other posts). Restarting Arma loses the queue.

| Symptom | Likely cause | Action |
| ------- | ------------ | ------ |
| Tacmap empty / login wall | Web session or feature `atak` denied | Re-login to the correct community; administrator checks plan and maintenance |
| API 401 | Missing/invalid access key or game session | Re-authenticate in game; check CBA key vs community key |
| API 403 `tenant_context_required` | No tenant resolved | Do not invent tenant 1. Relink account or use the community key |
| API 503 `maintenance` | Community ATAK maintenance flag | Wait or administrator clears maintenance |
| API 503 `connection_lost` | Roleplay simulated disconnect | Expected if realism is enabled |
| Extension `ERR` / Ping fail | DLL missing, blocked, or URL wrong | Check `COMSPECExtension_x64.dll`, CBA URL, firewall |
| Player not visible | Not in-mission, no terminal, origin (0,0), backoff, wrong map | SOP-01.6; wait 120 s before declaring stale |
| Position stale | Last update > 120 s | Treat as last known; use radio |
| Wrong picture | Wrong community or leftover map 1 data | Stop; fix tenant; do not merge pictures |
| Chat one-way | Poll interval or filter (MP callsign, hidden system lines) | Retry; check TOC vs operator filter |

Voice remains primary when C2 is stale.

---

## SOP-05 — End of mission

1. Operators: disconnect Athena (ACE / hub) so PLI stops; leave the mission.
2. TOC: note last knowns needed for AAR; do not keep emergency pings as current.
3. **Archiving** — live tables remain on the community database until an administrator purges. There is no automatic “close mission” that wipes BFT.
4. **Export** — use portal AAR / mission plan PDF where those tools are enabled. Do not scrape the API as an official archive.
5. **Temporary data** — delete exercise markers; clear photo nights if the community uses that tool.
6. **Debrief** — community AAR / forum / operations workspace. Lessons on C2 go to the administrator (keys, map, pack version).

---

## SOP-06 — Chef de mission (pre-flight)

Aligned with pack chef-de-mission practice:

1. Server has the same Overwatch pack and extension as players.
2. CBA settings: Overwatch enabled; API URL; network profile.
3. Mission: players spawn with the required terminal item if that CBA option is on.
4. Optional Eden/Zeus: phone tracks, coverage zones, realism.
5. Run SOP-01.5 with at least one player and one TOC browser before H-hour.

---

## References

- ATHENA C2 Field Manual — FM-A3-01
- ATAK Operator Manual — TM-A3-21
- ATHENA C2 Administrator Manual — TM-A3-31
- ATHENA–ATAK Interface Control Document — ICD-A3-01
- ATHENA C2 Capability Registry — REG-A3-01
