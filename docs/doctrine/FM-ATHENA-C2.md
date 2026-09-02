ATHENA C2
TECHNICAL PUBLICATION

Document: ATHENA C2 — Field Manual
Reference: FM-A3-01
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value          |
| --------------- | -------------- |
| Document ID     | FM-A3-01       |
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

# ATHENA C2 — Field Manual

## 1. Purpose

ATHENA C2 is the command-and-control system used by COMSPEC communities to maintain a **common operational picture** between:

- operators in **Arma 3**, through **COMSPEC Overwatch**;
- the command post, through the **ATAK / Tacmap** web interface;
- the community, through the **ATHENA** portal (identity, membership, maps, missions).

This Field Manual states doctrine of employment. Procedures are in SOP-A3-01. Operator and administrator tasks are in TM-A3-21 and TM-A3-31. Implementation contracts are in the Technical Publications. Capability status is authoritative only in REG-A3-01.

## 2. System intent

ATHENA C2 exists to:

1. show **who is where** on the current map (PLI / BFT);
2. share **markers, chat, pings, photos and reports** between the field and the post;
3. keep that picture inside the correct **community (tenant)** and the correct **map**;
4. continue to operate in a **degraded** mode when the liaison is slow, interrupted, or simulated as lost.

ATHENA C2 does not replace radio, ACE medical, or mission command. It complements them.

## 3. C2 principles

| Principle | Meaning in this system |
| --------- | ---------------------- |
| Unity of picture | One tactical picture per community and map. Operators and the post must look at the same contacts, markers and chat. |
| Authority of identity | Callsign and community come from the ATHENA account after authentication. The operator does not pick a tenant id in the field. |
| Discipline of symbols | Markers and pings are operational traffic. Temporary marks must not be treated as confirmed intel. |
| Continuity | When the liaison drops, the last known picture remains until it ages out. There is no claim of a durable offline store on disk. |
| Least surprise | A capability is used only if REG-A3-01 lists it as FIELDED. Roadmap items are not doctrine. |

## 4. Common operational picture

The COP is the set of live objects bound to a **tenant** and a **map**:

- units (BFT / PLI);
- markers and map shapes;
- chat;
- pings;
- photos and reports;
- CAS / MEDEVAC / flight traffic when used;
- optional overlays (zones, vehicles, geo, SSE).

Coordinates are **Arma world metres** (`pos_x`, `pos_y`, optional `pos_z` ASL). The Tacmap uses the same world CRS. There is no operational Lat/Long conversion in the fielded stack.

A unit that has not been updated for **120 seconds** is treated as no longer live on the picture.

## 5. Terrain and command post

```text
Field (Arma 3)
    operator + COMSPEC Overwatch
        │
        │ liaison
        ▼
Command post (ATHENA ATAK / Tacmap)
    TOC, JTAC, pilots, medics, overwatch
```

- The **field** produces positions, markers, pings, photos, chat and support requests.
- The **post** consumes the picture, issues chat/orders/routes, and may draw markers that Overwatch polls back into Arma.
- **SSE** (personnel intelligence) is an adjacent system. It is not required to run a C2 serial.

## 6. Components (doctrinal)

| Component | Role |
| --------- | ---- |
| ATHENA portal | Accounts, communities, roles, ATAK configuration, maps, operations workspace |
| ATAK / Tacmap | Web common picture and TOC tools |
| ATAK mobile shell | Phone-sized Tacmap, pairing |
| COMSPEC Overwatch | Arma addons + native extension |
| Operations workspace | Web mission planning (`/operations`) — distinct from the live Tacmap map id |

COMSPEC Overwatch is not a substitute for cTab / ATAK Enhanced / BCE. When those mods are present, the `atak_athena` addon bridges them.

## 7. Roles

| Role | Typical use of ATHENA C2 |
| ---- | ------------------------ |
| Operator | Maintain PLI, read the picture, send ping/chat/photo, follow routes and orders |
| Team leader | Same, plus group traffic and reports |
| Overwatch / TOC | Watch the Tacmap, correlate contacts, pass traffic |
| JTAC | Laser codes, designator, CAS traffic as fielded |
| Pilot | CAS line checks, flight manifest, air picture |
| Medic | Medical alerts, MEDEVAC 9-line |
| Community administrator | Members, ATAK access key, maps, maintenance |
| System administrator | Instance, secrets, channels, health |

Detailed permissions are in TM-A3-31 and SEC-A3-01.

## 8. Tenants, maps, missions, workspaces

```text
User
  → Community (tenant)
    → Map (atak_maps / map_id)
      → Live C2 data (units, markers, chat, …)
    → Operation / workspace (web /operations)   [planning]
```

- A **tenant** is a community. Isolation of live C2 data is by `tenant_id`.
- A **map** is the Arma world used for the picture. The extension currently posts `mapId: 1` unless another map is supplied by the API client. Administrators must treat map 1 as the default live map.
- A **workspace / operation** on the portal is a planning object (`operations.workspace_key`). It is not a player-selectable mission id inside Overwatch.
- Operators do not type `tenant_id`. Community is resolved after ATHENA authentication or by the community access key.

## 9. Information discipline

| Class | Examples | Handling |
| ----- | -------- | -------- |
| Live | PLI, current ping, current chat | Trust while fresh; 120 s BFT TTL |
| Temporary | Unconfirmed marker, phone geoloc proxy, enemy AI contact | Label and do not promote to fact without confirmation |
| Confirmed | Marker agreed by TOC, photo with grid, MEDEVAC accepted | Retain for the serial |
| Technical | System chat, settings dumps, mod diagnostics | Hide from the operational journal |

Proxy contacts (phone track, allied AI, GPS beacon) must not be read as the operator’s own identity.

## 10. Command relationship

ATHENA C2 does not create a new chain of command. It carries the existing one:

- mission command remains with the appointed leader in game;
- the TOC uses Tacmap to see and to pass traffic;
- Zeus / mission maker prepares the serial (mods, CBA, coverage) but does not become the tenant authority.

## 11. Operational cycle

1. **Prepare** — community, map, Overwatch pack, CBA, authentication, PLI test (SOP-A3-01).
2. **Establish COP** — contacts, markers, zones, confirmed vs temporary.
3. **Execute** — PLI, chat, pings, photos, support requests.
4. **Degrade** — if liaison is lost, continue on radio; do not invent a queued store that survives a restart of Arma.
5. **Close** — stop PLI, archive what the portal retains, after-action on the community tools.

## 12. Degraded mode (doctrine)

Degraded mode is expected. Causes include web outage, API unavailability, extension failure, wrong community, stale position, or simulated roleplay loss.

Doctrine:

- last known PLI may remain until TTL;
- the extension keeps an **in-memory** retry queue (positions coalesced; other posts capped);
- restarting Arma **discards** that queue;
- operators fall back to voice;
- TOC marks the picture as stale rather than false-precise.

## 13. Documentation architecture

| Reference | Document | Audience |
| --------- | -------- | -------- |
| FM-A3-01  | This Field Manual | All |
| SOP-A3-01 | Standard Operating Procedures | Operations |
| TM-A3-21  | ATAK Operator Manual | Operators, TOC, JTAC, pilots |
| TM-A3-31  | Administrator Manual | Community and system administrators |
| ATP-A3-01 | System Architecture | Technical |
| TM-A3-11  | COMSPEC Overwatch Technical Manual | Developers, integrators |
| ICD-A3-01 | Interface Control Document | Developers, integrators |
| SEC-A3-01 | Security Architecture | Technical / security |
| ATP-A3-11 | Deployment and Release | System administrators |
| REG-A3-01 | Capability Registry | Product and technical leads |

If REG-A3-01 marks a capability PLANNED, EXPERIMENTAL or UNVERIFIED, this Field Manual does not authorise its operational use.

## References

- ATHENA C2 Standard Operating Procedures — SOP-A3-01
- ATAK Operator Manual — TM-A3-21
- ATHENA C2 Administrator Manual — TM-A3-31
- ATHENA C2 System Architecture — ATP-A3-01
- ATHENA C2 Capability Registry — REG-A3-01
