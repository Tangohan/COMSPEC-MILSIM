ATHENA C2
TECHNICAL PUBLICATION

Document: ATHENA–ATAK / COMSPEC Overwatch Interface Control Document
Reference: ICD-A3-01
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value          |
| --------------- | -------------- |
| Document ID     | ICD-A3-01      |
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

# ATHENA–ATAK / COMSPEC Overwatch Interface Control Document

Contract between:

```text
ARMA SQF
↕
COMSPEC DLL
↕
ATHENA API
↕
ATAK WEB
```

ICD-* identifiers are **documentation only**. They are not protocol fields.

**Capability status** for each interface follows REG-A3-01.

### Common conventions

| Topic | Fielded behaviour |
| ----- | ----------------- |
| Transport | HTTPS JSON (PHP). No Socket.IO on the live path |
| Timestamp | SQL `created_at` / `updated_at` on rows; position freshness = `updated_at` vs 120 s TTL |
| Coordinates | Arma world metres `pos_x`, `pos_y`; optional `pos_z` / `asl_z` ASL. Not Lat/Long |
| Map | DLL posts `"mapId": 1`. API also accepts `map_id`. Default map_id = 1 |
| Tenant | Not in most DLL bodies. Resolved by key / game session / web session / query / body / env. Never silent tenant 1 |
| Auth game | `Authorization: Bearer` game access token and/or community `X-COMSPEC-KEY` / `X-ATAK-TOKEN` |
| Auth web | Session cookie `credentials: 'include'` |
| Errors | 401 unauthorized; 403 `tenant_context_required`; 503 `maintenance` / `connection_lost` / generic ATAK renderer `Retry-After: 30` |
| Retry | DLL in-memory queue (positions coalesced, max 500 other POSTs). Not disk |
| Offline | Queue lost on Arma restart |

---

## ICD-AUTH-001 — Game session

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-AUTH-001 |
| Name | Game authentication |
| Direction | DLL → API |
| Producer | `GameAuth.cs` |
| Consumer | `GameAuthApiController` |
| Transport | HTTPS JSON |
| Method / Endpoint | `POST /api/game/v1/auth/password`, `otp/request`, `otp/verify`, `steam/challenge`, `steam/exchange`, `session/restore`, `session/refresh`, `session/logout` |
| Authentication | Public for auth; session token for refresh/logout |
| Tenant context | Returned/stored on `game_sessions.tenant_id` |
| Mission/workspace | None |
| Refresh | Access 7200 s; refresh ~30 days |
| Offline | No |
| Status | FIELDED |

Implementation: `app/Controllers/Api/Game/GameAuthApiController.php`, `app/Services/Game/GameAuthService.php`

---

## ICD-AUTH-002 — Connect / tactical key

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-AUTH-002 |
| Name | Overwatch Connect |
| Direction | SQF → DLL → API |
| Producer | `fn_connect` |
| Consumer | Tactical middleware + client-init |
| DLL Command | `Connect`, `Ping`, `Disconnect` |
| Endpoint | Connect establishes `_baseUrl` + key/token; subsequent POSTs use that base |
| Authentication | Community access key and/or game Bearer |
| Tenant context | Empty string from SQF; server binds tenant from key/session |
| Status | FIELDED |

---

## ICD-PLI-001 — Position / BFT

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-PLI-001 |
| Name | Unit position upsert |
| Direction | Arma → API → Web |
| Producer | `fn_updatePosition` → `UpdatePosition` |
| Consumer | `AtakApiController::position` → `AtakDataRepository::upsertUnitPosition`; Tacmap `GET /api/atak/units` |
| Transport | HTTPS JSON |
| Method | POST |
| Endpoint / DLL Command | `POST /api/atak/position` / `UpdatePosition` |
| Authentication | `authArma()` + tenant |
| Tenant context | Resolver (not in typical DLL body) |
| Mission/workspace | `mapId` (DLL: 1) |
| Polling | SQF PFH 1 s; send gated (CBA interval / heartbeat / threshold). Web units poll default 8 s |
| Offline | Coalesced last position in DLL memory |
| Status | FIELDED |

### Request schema (DLL-built)

Required: `call_sign`, `pos_x`, `pos_y`. Optional: `heading`, `role`, `pos_z`, `asl_z`, `steam_uid`, `session_token`, `mod_version`, `extra` object.

```json
{
  "mapId": 1,
  "call_sign": "N-10",
  "pos_x": 1850.12,
  "pos_y": 5421.08,
  "pos_z": 23.4,
  "asl_z": 23.4,
  "heading": 270.0,
  "role": "Team Leader",
  "steam_uid": "7656119…",
  "session_token": "…",
  "extra": {
    "role": "Team Leader",
    "health": "ok",
    "fuel": "",
    "ammo": "n/a",
    "radio_freq": "",
    "group_name": "N-10",
    "group": "N-10",
    "bft_id": "…",
    "military_id": "…",
    "asl_z": 23.4,
    "pos_z": 23.4,
    "speed": 0,
    "mod_version": "1.5.13",
    "deferred": false
  }
}
```

`extra` may also contain vehicle flags, `phone_geoloc` / `ally_ai` / `gps_beacon` proxies, medical, `terminal_uid`, `compromise_state`, `telemetry_kind` (`position`|`heartbeat`). Proxy extras must not carry the player Steam identity.

Server also accepts `map_id`, `callsign`, `pos: [x,y,z]`. Empty callsign may be recovered from Steam then profile then `Operateur`. `(0,0)` dropped in DLL.

### Response

JSON upsert acknowledgement (unit id / ok). Enemy AI hide path may return `{ "ok": true }` without persist.

### GET units (web / poll)

`GET /api/atak/units` (and `/api/units`) — tenant + map. Live TTL **120 s**.

---

## ICD-CHAT-001 — Chat

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-CHAT-001 |
| Name | C2 chat |
| Direction | Bidirectional |
| Producer | `SendChat` / Tacmap POST |
| Consumer | `chatIndex` / `chatStore` |
| Method | POST ` /api/chat` ; GET `/api/chat` |
| DLL Command | `SendChat`, `GetChatMessages` |
| Auth | Tactical key or session |
| Map | `mapId` 1 from DLL |
| Polling | Arma ~6 s; mobile web ~3 s |
| Status | FIELDED |

### POST body (DLL)

```json
{
  "mapId": 1,
  "author": "N-10",
  "body": "Contact grid 123045",
  "steam_uid": "7656119…",
  "session_token": "…"
}
```

GET query: `limit` (default 100), `after` (id), `callsign` / `for_callsign` (MP filter). Hidden system lines stripped. Roleplay may 503.

---

## ICD-PING-001 — Pings

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-PING-001 |
| Name | Tactical ping |
| Direction | Arma / Web → API → Web |
| Method | POST `/api/pings` ; GET `/api/pings` ; DELETE `/api/pings/{id}` |
| DLL Command | `SendPing` |
| Status | FIELDED |

```json
{
  "mapId": 1,
  "author": "N-10",
  "pos_x": 1850.12,
  "pos_y": 5421.08,
  "message": "Ping",
  "steam_uid": "7656119…",
  "session_token": "…"
}
```

Coords validated. 201 + activity log `TYPE_PING`.

---

## ICD-MARKER-001 — Markers

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-MARKER-001 |
| Name | Map marker upsert / delete |
| Direction | Bidirectional |
| Method | POST `/api/atak/marker` ; GET markers |
| DLL Command | `SendMarker`, `GetMarkers` |
| Polling | Arma GET ~8 s |
| Status | FIELDED |

Create/update:

```json
{
  "mapId": 1,
  "layerId": 1,
  "arma_name": "_USER_DEFINED #0/marker_1",
  "steam_uid": "7656119…",
  "session_token": "…",
  "markerData": {}
}
```

`markerData` is a JSON object (Arma marker fields after `SanitizeLooseJsonObject`). Delete: `"deleted": true` without markerData.

GET returns the list used by `fn_pollAthenaMarkers` to `createMarkerLocal` / update / delete.

---

## ICD-MARKER-002 — Map shapes

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-MARKER-002 |
| Name | Map shapes |
| Direction | Bidirectional |
| Endpoint | `/api/map-shapes` |
| DLL | `GetMapShapes` ~10 s |
| Status | FIELDED |

---

## ICD-PHOTO-001 — Intel photos

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-PHOTO-001 |
| Name | Photo / recon upload |
| Direction | Arma → API → Web |
| Endpoints | `/api/intel/photos`, `/api/recon/images*`, related notify |
| DLL | `UploadImage`, `NotifyNewPhoto`, `StageCapture` |
| Status | FIELDED |

Body varies (multipart or JSON with path/base64). Author/grid taken from last `UpdatePosition` memo when the SQF payload is minimal. Photo queue in DLL is in-memory (max 64 jobs). Exact multipart fields: see `BeginUploadIntelPhoto` in `Extension.cs` — do not invent field names here beyond those confirmed on the PHP store: `filename`, `path`, `author`, `pos_x`, `pos_y` on `atak_intel_photos`.

---

## ICD-INTEL-001 — Legacy intel

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-INTEL-001 |
| Name | Legacy intel POST |
| Direction | Arma → API |
| Endpoint | `POST /api/atak/intel` |
| DLL | `SendIntel` |
| Status | DEPRECATED |

```json
{
  "mapId": 1,
  "type": "MARKER",
  "body": "",
  "data": ""
}
```

Table `atak_intel` has **no tenant_id**. Do not use for new serials. Prefer reports / photos / markers.

---

## ICD-INTEL-002 — Tactical report

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-INTEL-002 |
| Name | Tactical / Intel.Report |
| Direction | Arma → API → Web |
| Endpoints | `/api/intel/report`, `/api/atak/reports*` |
| DLL | `Intel.Report`, `SubmitTacticalReport` |
| Status | FIELDED |

JSON body passed through from SQF (`NormalizeArmaJson`). Schema is report-specific; see `AtakTacticalReportRepository` / controller store — UNVERIFIED field-by-field in this revision beyond existence.

---

## ICD-JTAC-001 — Laser codes

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-JTAC-001 |
| Name | Laser codes |
| Direction | Bidirectional |
| Endpoint | `/api/atak/laser-codes` |
| DLL | `SyncLaserCode` |
| Web poll | Mobile JTAC module ~15 s |
| Status | FIELDED |

---

## ICD-JTAC-002 — Designator

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-JTAC-002 |
| Name | Laser designator point |
| Direction | Arma → API → Web |
| Endpoint | `POST /api/atak/designator` |
| DLL | `SendDesignator` |
| Status | FIELDED (DLL/API); SQF caller UNVERIFIED |

```json
{
  "mapId": 1,
  "call_sign": "N-10",
  "pos_x": 0,
  "pos_y": 0
}
```

---

## ICD-CAS-001 — CAS

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-CAS-001 |
| Name | CAS ticket |
| Direction | Bidirectional |
| Endpoints | `/api/cas`, `/api/cas/{id}/status`, ack, check-line |
| DLL | `GetCASForCallsign`, `SendCASAck`, `SendCASState`, `SendCASCheckLine`, `PilotResponse` |
| Status | FIELDED |

Not a full NATO 9-line CAS form. Request path from SQF is order/chat (`fn_casRequestSubmit` → `issueOrder`).

Status PATCH example:

```json
{
  "status": "inbound"
}
```

---

## ICD-MED-001 — MEDEVAC 9-line

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-MED-001 |
| Name | MEDEVAC 9-line |
| Direction | Arma → API → Web |
| Endpoints | `/api/atak/medevac*`, `/api/nine-line` |
| DLL | `RequestMEDEVAC` |
| Status | FIELDED |

This is the fielded 9-line. Line fields `line1`… as built by `fn_requestMEDEVAC`.

---

## ICD-FLT-001 — Flight manifest

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-FLT-001 |
| Name | Flight manifest / air assets |
| Direction | Arma → API → Web |
| Endpoint | `POST /api/atak/flight-manifest` ; GET `/api/atak/air-assets` / `/api/flight-manifest` |
| DLL | `SendFlightManifest` |
| Status | FIELDED |

Body: JSON from SQF after `NormalizeArmaJson` + `EnrichAtakPayload` (session/steam fragments). Exact keys: see `flightManifestStore` in `AtakApiController.php`.

---

## ICD-ORD-001 — Orders

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-ORD-001 |
| Name | Orders / FRAGO |
| Direction | Web → Arma (poll) |
| Endpoint | `/api/atak/orders*` |
| DLL | `GetOrders`, `UpdateOrderStatus` |
| Web poll | ~4 s (`atak-orders.js`) |
| Status | FIELDED |

---

## ICD-WPT-001 — Waypoints

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-WPT-001 |
| Name | GPS routes / waypoints |
| Direction | Web → Arma |
| Endpoint | `/api/atak/waypoint-routes*`, `/api/atak/waypoints*` |
| DLL | `GetWaypoints`, `MarkWaypointReached` |
| Web poll | ~4 s |
| Status | FIELDED |

Coordinates: `pos_x`, `pos_y`, `pos_z`, `context_id`.

---

## ICD-VEH-001 — Vehicle tracking

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-VEH-001 |
| Name | Vehicle tracking |
| Direction | Arma → API → Web |
| Endpoint | `/api/atak/vehicles*` |
| DLL | `UpdateVehicleTracking` |
| Status | FIELDED |

---

## ICD-HLT-001 — Ping

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-HLT-001 |
| Name | ATAK ping |
| Direction | Any → API |
| Method | GET `/api/atak/ping` |
| Auth | Exempt |
| Response | `{ "ok": true, "service": "atak", "server_ms": … }` |
| Status | FIELDED |

DLL command `Ping` is extension liveness, not this HTTP route (both exist).

---

## ICD-SIG-001 — SIGINT

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-SIG-001 |
| Endpoint | `POST /api/atak/sigint` |
| DLL | `SendSigint` |
| Status | EXPERIMENTAL |

```json
{
  "mapId": 1,
  "call_sign": "N-10",
  "pos_x": 0,
  "pos_y": 0,
  "bearing": 45.0
}
```

`bearing` optional.

---

## ICD-GAME-001 — Operations tactical snapshot

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-GAME-001 |
| Name | Game operations list / tactical |
| Direction | API → DLL/UI |
| Endpoint | `GET /api/game/v1/operations`, `GET /api/game/v1/operations/{uuid}/tactical` |
| Auth | Game session |
| Status | FIELDED (web/game ops; not Overwatch map picker) |

---

## Web Tacmap poll frequencies (implementation)

| Client | Interval | Target |
| ------ | -------- | ------ |
| `comspec-operational-map.js` | 8000 ms default | units |
| secondary layers | ≥12 s | |
| weather | ≥20 s | |
| `atak-mobile.js` chat | 3 s | |
| pings | 4 s | |
| markers | 5 s | |
| laser/JTAC | 15 s | |

---

## Additional tactical routes

`routes/web.php` registers a large `/api/atak/*` surface beyond the sheets above (SOI, medevac extras, fire-teams, wardrobes, terminals, AAR, POI, terrain, scene, geo, QRF, explosive timers, video-feeds, weather, presence, …). New consumers must read the controller method; this ICD does not invent those payloads.

Aliases: `/api/markers`, `/api/units`, `/api/nine-line`, `/api/cas`, `/api/fire-support/*`, `/api/danger-zones`, `/api/logistics/*`, `/api/iff/*`, `/api/replay/*`, `/api/operations/*`.

---

## Implementation References

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Controllers/Api/AtakApiController.php`
- `app/Controllers/Api/AtakPingController.php`
- `app/Controllers/Api/Game/GameAuthApiController.php`
- `app/Repositories/AtakDataRepository.php`
- `routes/web.php`
- `config/tactical_api.php`
- `public/assets/js/comspec-operational-map.js`
- `public/assets/js/atak-mobile/atak-mobile.js`

## References

- ATHENA C2 System Architecture — ATP-A3-01
- COMSPEC Overwatch Technical Manual — TM-A3-11
- ATHENA C2 Security Architecture — SEC-A3-01
- ATHENA C2 Capability Registry — REG-A3-01
