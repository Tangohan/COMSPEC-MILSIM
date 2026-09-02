ATHENA C2
TECHNICAL PUBLICATION

Document: ATHENA C2 — Capability Registry
Reference: REG-A3-01
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value          |
| --------------- | -------------- |
| Document ID     | REG-A3-01      |
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

# ATHENA C2 — Capability Registry

This registry is mandatory. Manuals and SOP may describe a capability only when its status is **FIELDED** or, with an explicit warning, **TEST**.

### Status

| Status | Meaning |
| ------ | ------- |
| FIELDED | Present in code, integrated, intended for normal use |
| TEST | Implemented, still under validation |
| EXPERIMENTAL | Partial or laboratory |
| PLANNED | Roadmap only |
| DEPRECATED | Still present; must not be used for new serials |
| UNVERIFIED | Mentioned in tree; state not established with certainty |

### Direction

Arma→API, API→Arma, API→Web, Web→API, bidirectional.

---

## Registry

| ID | Capability | Component | Status | Direction | Backend | Arma | Web | Notes |
| -- | ---------- | --------- | ------ | --------- | ------- | ---- | --- | ----- |
| CAP-PLI-001 | PLI / BFT positions | ATAK units | FIELDED | Arma→API→Web | Yes `POST /api/atak/position` `atak_units` | Yes `UpdatePosition` | Yes Tacmap | TTL 120 s; mapId default 1 |
| CAP-BFT-002 | BFT extra (health, fuel, ammo, role, radio) | extra JSON | FIELDED | Arma→API→Web | Stored in unit `extra` | Yes args + vehicle JSON | Tooltip/icon | Old roadmap is obsolete |
| CAP-MRK-001 | Markers Arma → Web | markers | FIELDED | Arma→API→Web | `POST /api/atak/marker` | `SendMarker` | Yes | |
| CAP-MRK-002 | Markers Web → Arma | markers poll | FIELDED | Web→API→Arma | `GET` markers | `GetMarkers` ~8 s | Yes | Buffer/delta limits apply |
| CAP-MRK-003 | Map shapes | map_shapes | FIELDED | bidirectional | `/api/map-shapes` | `GetMapShapes` ~10 s | Yes | |
| CAP-CHT-001 | Chat | chat | FIELDED | bidirectional | `/api/chat` | `SendChat` / `GetChatMessages` ~6 s | Yes poll ~3 s mobile | Not Arma side chat |
| CAP-PNG-001 | Pings | pings | FIELDED | Arma→API→Web | `/api/pings` | `SendPing` | Yes | |
| CAP-PHO-001 | Intel photos / recon | photos | FIELDED | Arma→API→Web | `/api/intel/photos` `/api/recon/images` | `UploadImage` `NotifyNewPhoto` | Yes | In-memory photo queue |
| CAP-INT-001 | Legacy intel POST | `atak_intel` | DEPRECATED | Arma→API | `POST /api/atak/intel` | `SendIntel` | Limited | **No tenant_id column** |
| CAP-INT-002 | Tactical reports / FRS | reports | FIELDED | Arma→API→Web | reports + `Intel.Report` | Yes | Yes | |
| CAP-CTB-001 | cTab / BCE / ATAK Enhanced bridge | atak_athena | FIELDED | Arma↔apps | Via connect | Yes if mods loaded | Indirect | Soft dependency |
| CAP-ACE-001 | ACE self-actions | connect | FIELDED | Arma | — | `fn_initACE` | — | |
| CAP-CAS-001 | CAS request / ack / line check | CAS | FIELDED | bidirectional | `/api/cas` | `GetCASForCallsign` `SendCASAck` `SendCASCheckLine` | Yes | Not a full NATO 9-line CAS form |
| CAP-MED-001 | MEDEVAC 9-line | MEDEVAC | FIELDED | Arma→API→Web | medevac + `/api/nine-line` | `RequestMEDEVAC` | Yes | The fielded 9-line |
| CAP-LSR-001 | Laser codes | laser | FIELDED | bidirectional | `/api/atak/laser-codes` | `SyncLaserCode` | JTAC module | |
| CAP-DSN-001 | Remote designator | designator | FIELDED | Arma→API→Web | `/api/atak/designator` | `SendDesignator` (DLL) | Yes | SQF call coverage: verify per mission mods |
| CAP-FLT-001 | Flight Manifest | air assets | FIELDED | Arma→API→Web | `/api/atak/flight-manifest` | `SendFlightManifest` | Yes | |
| CAP-ORD-001 | Orders / FRAGO | orders | FIELDED | Web→Arma | `/api/atak/orders` | `GetOrders` | Yes | |
| CAP-WPT-001 | Waypoints / GPS routes | waypoints | FIELDED | Web→Arma | waypoint APIs | `GetWaypoints` | Yes | |
| CAP-VEH-001 | Vehicle tracking | vehicles | FIELDED | Arma→API→Web | vehicles API | `UpdateVehicleTracking` | Yes | |
| CAP-ZON-001 | Tactical zones / check position | zones | FIELDED | bidirectional | zones API | `GetTacticalZones` `CheckZonePosition` | Yes | |
| CAP-ALR-001 | Medical / tactical alerts | alerts | FIELDED | Arma→API→Web | medical-alerts | Yes | Yes | PANIC / unconscious |
| CAP-RAD-001 | Radio proximity metadata | radio | FIELDED | Arma→API→Web | extra / radio fields | CBA radio proximity | Tacmap pastilles | Not a full ACRE product |
| CAP-ACR-001 | ACRE2 deep COMMS / SIGINT | ACRE | PLANNED | — | Spec only | Soft detect | — | `acre-comms-atak-sse-sigint.md` |
| CAP-TFR-001 | TFAR as SIGINT product | TFAR | PLANNED | — | — | Soft detect | — | |
| CAP-SGI-001 | SIGINT reports / bearing | sigint | EXPERIMENTAL | Arma→API→Web | `/api/atak/sigint` | `SendSigint` DLL | Partial | Do not SOP as primary |
| CAP-IFF-001 | IFF | iff | EXPERIMENTAL | bidirectional | `/api/iff` | `IFF.*` | Partial | |
| CAP-GEO-001 | Geo network / route plan | geo | FIELDED | Arma→API→Web | geo APIs | `Geo.Ingest` | Yes | |
| CAP-TRN-001 | Terrain chunks / LOS | terrain | FIELDED | Arma→API→Web | terrain APIs | `Terrain.Chunk` | 3D/LOS tools | |
| CAP-SCN-001 | Scene objects ingest | scene | FIELDED | Arma→API | scene API | `Scene.Ingest` | Overlay | |
| CAP-RPL-001 | Replay | replay | EXPERIMENTAL | API→Web | `/api/replay` | — | Partial | |
| CAP-Q-001 | In-memory retry queue | DLL | FIELDED | Arma→API | — | `PendingPosts` max 500; position coalesced | — | Lost on Arma restart |
| CAP-Q-002 | Disk-persistent offline queue | DLL | PLANNED | — | — | No | — | Do not document as available |
| CAP-HLT-001 | ATAK ping (no DB) | ping | FIELDED | any→API | `GET /api/atak/ping` | `Ping` | — | |
| CAP-HLT-002 | App health (DB) | health | FIELDED | Web→API | `GET /api/health` | — | Auth required | Poor LB probe |
| CAP-AUTH-001 | Game auth Steam/password/OTP | game | FIELDED | Arma→API | `/api/game/v1/*` | `AuthSteam` etc. | — | Opaque tokens, not JWT |
| CAP-AUTH-002 | Web session Tacmap | portal | FIELDED | Web | session | — | Yes | Feature gate `atak` |
| CAP-TEN-001 | Tenant isolation live tables | core | FIELDED | — | `tenant_id` on live tables | Community after auth | Session tenant | Exception: legacy `atak_intel` |
| CAP-MAP-001 | Multi-map selection in Overwatch | connect | EXPERIMENTAL | — | API accepts mapId | DLL hardcodes mapId 1 | Web map switch | Operational default = map 1 |
| CAP-WS-001 | Operations workspace | operations | FIELDED | Web | `operations` tables | No picker | `/operations` | Planning, not live map id |
| CAP-RP-001 | Roleplay liaison degradation | realism | FIELDED | API | 503 `connection_lost` | CBA realism | Overlay | Simulated |
| CAP-LAT-001 | Lat/Long geographic Tacmap | maps | PLANNED | — | CRS Simple only | World metres | World metres | |
| CAP-JTC-001 | Dedicated JTAC 9-line CAS form | JTAC | PLANNED | — | CAS is order/chat | Label JTAC in intel score | jtac UI module polls lasers | Use CAP-CAS-001 + CAP-LSR-001 |
| CAP-GAT-001 | Feature gate `atak` on tactical API | billing | UNVERIFIED | — | Not in AtakApiController | — | Enforced on web map | SECURITY GAP if relied upon |
| CAP-NOD-001 | Node Socket.IO live C2 | Node | UNVERIFIED | — | `NODE_ATAK_URL` optional | — | PHP polling is live | Do not require Node for serials |
| CAP-SSE-001 | SSE personnel intel | SSE | FIELDED | adjacent | `/api/sse*` | SubmitSse* | SSE workspace | Separate product; not C2 SOP |

---

## Status counts (this revision)

| Status | Count |
| ------ | ----- |
| FIELDED | 34 |
| TEST | 0 |
| EXPERIMENTAL | 4 |
| PLANNED | 5 |
| DEPRECATED | 1 |
| UNVERIFIED | 2 |

## Rules for other publications

1. SOP-A3-01 and TM-A3-21 must not present PLANNED or DEPRECATED items as available.
2. EXPERIMENTAL items may appear only as “laboratory / do not rely”.
3. When code and an old `.md` disagree, this registry follows the code (see MIG-A3-01 conflicts).

## References

- ATHENA C2 Field Manual — FM-A3-01
- ATHENA–ATAK Interface Control Document — ICD-A3-01
- COMSPEC Overwatch Technical Manual — TM-A3-11
- Documentation mapping — MIG-A3-01 (`docs/_migration/DOCUMENT-MAPPING.md`)
