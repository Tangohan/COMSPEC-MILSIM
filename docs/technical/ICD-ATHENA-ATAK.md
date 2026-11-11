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

Un ping dont le message commence par une étiquette contenant « super » (`[Super ping]`) disparaît seul : `GET /api/pings` le retire de la base 15 s après sa création.

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

## ICD-RF-001 — Fieldwatch RF hits (Lot 1)

| Field | Value |
| ----- | ----- |
| Interface ID | ICD-RF-001 |
| Endpoint | `POST /api/atak/rf-hits` · `GET /api/atak/rf-hits` |
| DLL | `SendRfHit` |
| Status | EXPERIMENTAL |

```json
{
  "mapId": 1,
  "emitter_uid": "rf_cam_12",
  "label": "IPCam-Lobby",
  "band": "wifi",
  "signature_id": "wifi_ipcam",
  "pos_x": 15820.0,
  "pos_y": 17100.0,
  "signal_dbm": -62,
  "sensor_callsign": "ALPHA-1",
  "ssid": "IPCam-Lobby",
  "mac": "AA:BB:CC:12:34:56"
}
```

`GET /api/atak/rf-hits?mode=markers` returns the latest hit per `emitter_uid` (map layer).  
`band` values: `wifi`, `ble`, `tracker`, `camera`, `phone`, `unknown`.

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

## ICD-SSE-EDITOR-001 — Bibliothèque de l'éditeur SSE

Canal : `SubmitIdCheck` de COMSPEC Link (2.0.118 et plus, aucune nouvelle DLL) → `POST /api/sse/identity/checks`, champ `kind`. Réponse HTTP `{"id": nombre, "ref": texte}`, rendue au jeu sous la forme `OK|id|ref` (8 000 octets au plus ; la DLL remplace `|`, CR, LF et tabulation dans `ref`).

| `kind` | Accès | Corps | Réponse |
|---|---|---|---|
| `editor_list` | compte identifié, habilitation Confidentiel | `offset` | `id` : position de la page suivante (0 : fin) ; `ref` : tableau `parseSimpleArray` |
| `editor_save` | compte identifié, habilitation Confidentiel | `item` : `id`, `type`, `title`, `body`, `marking`, `paper`, `nature`, `delivery`, `state`, `op`, `link`, `version`, `history`, `found` | `id` : identifiant ; `ref` : `saved`, ou `conflict` si une version plus récente est enregistrée |
| `editor_found` | clé de communauté suffisante | `uid`, `player`, `when` | `id` : identifiant (0 si inconnu) ; `ref` : `found`, `known` (premier joueur déjà inscrit) ou `unknown` |

Compte identifié : jeton de jeu (Bearer) ou session du portail. Une clé de communauté seule reçoit 403 `clearance_required` sur la lecture et l'écriture. Communauté : toujours celle du jeton, de la clé ou de la session.

Ligne de `editor_list` : `[id, type, titre, texte, classification, papier, nature, remise, état, opération, lié à, version, [[quoi, qui, quand]…], [trouvé par, quand]]`. Dans les textes : `""` pour un guillemet, `\n` pour un retour à la ligne, `\p` pour `|`.

Erreurs : 403 `clearance_required`, 422 `invalid_item` ou `too_large` (plus de 7 000 octets une fois encodé), 503 `unavailable`. Dans tous les cas le mod garde le contenu dans sa bibliothèque locale. Un mod plus ancien n'envoie jamais ces `kind`.

## ICD-SSE-COVER-001 — Couvertures reliées à Athena

Site vers jeu : `GET /api/sse/identity/pack`, bloc `briefs`. Ajouts facultatifs, ignorés par un ancien mod : `legend_free` (texte de la légende sans ses rubriques), `employer`, `travel`, `family`, `city`, `profession` (rubriques « Employeur : … » du texte), `updated_at` (date montrée au joueur). `legend` garde le texte entier.

Jeu vers site : `SubmitIdCheck` de COMSPEC Link (2.0.118 et plus, aucune nouvelle DLL) → `POST /api/sse/identity/checks`, `kind` = `cover_report`, envoyé par le poste du joueur.

| Champ | Contenu |
|---|---|
| `map`, `op` | portée (carte, code d'opération) |
| `cover` | `name`, `dob`, `pob`, `country`, `address`, `city`, `profession`, `employer`, `phone`, `travel`, `family`, `contacts` `[[nom, lien, téléphone]…]`, `docs` `[[type, pays, numéro]…]` |
| `cover` (livraison, facultatif) | `pack` étape (`ok`, `wait`, `server_fetch`, `relay_zeus`, `relay_self`, `relay_fail`, `no_relay`), `pack_code` (`unauthorized`, `no_tenant`, `uplink_off`, `not_ready`…), `pack_via` (`server`, `zeus`, `self`), `pack_at` (date du paquet reçu), `server` (nom du serveur de jeu). Hors de l'empreinte de l'identité : un changement de livraison ne rouvre pas une différence examinée. |

Compte : celui du jeton de jeu (Bearer), jamais une donnée du corps ; communauté : celle du jeton. Une clé de communauté seule ne dit pas qui appelle : rien n'est écrit.

Réponse `{"id": nombre, "ref": texte}` : `saved;AAAA-MM-JJ HH:MM`, `unchanged;AAAA-MM-JJ HH:MM` (même contenu, date du dernier changement, UTC), `unlinked` (`id` = 0, aucun compte relié). Erreurs : 422 `invalid_cover` (sans nom), 503 `unavailable`. Le corps n'a pas de `idempotency_key` : un site antérieur le refuse en 422 au lieu d'y voir un contrôle. Le mod garde alors la couverture sur le poste et réessaie plus tard.

Confidentialité : ces lignes (`sse_id_game_covers`) ne rejoignent ni le registre des identités, ni les listes de surveillance, ni le paquet. Lecture : le joueur dans Mes papiers, l'habilitation Confidentiel sur la fiche du membre.

## ICD-SSE-COVER-SYNC-001 — Couvertures d'Athena récupérées par le téléphone

Site vers jeu : `GET /api/sse/identity/pack?op=~covers` (liste) ou `op=~cover:CVR-…,CVR-…` (5 fichiers au plus), `since` = `v=1`, relayés par `GetIdentityPack` de COMSPEC Link (DLL inchangée) ; aussi `GET /api/atak/covers` et `GET /api/atak/covers/files?refs=`. Réponse au format du paquet, tout dans `settings` : `covers_api` (1), `covers` ou `files` + `errors`. Compte et communauté : ceux du jeton de jeu.

Entrée de `covers` : `ref`, `name`, `summary`, `country`, `country_code`, `status` (`valid`, `expired`, `invalid`), `has_photo`, `generated_at`, `expires_at`, `updated_at`, `size`, `sha`, `by_manager`, `downloaded`, `active`.

- `active` (booléen, ajouté sans changer `covers_api`) : vrai sur la seule couverture que le membre a marquée active dans `/operateur/identite/couverture` (`operator_identity_states`, `item_kind` = `cover`, `is_active` = 1, borné à la communauté et au membre). Aucune active, ou table absente : faux partout. Un mod plus ancien ignore le champ ; un mod récent face à un site sans ce champ garde son choix habituel.
- Ordre : la couverture active d'abord, puis de la plus récente à la plus ancienne.
- Une couverture révoquée par le membre n'est plus listée (et n'est jamais `active`).

## ICD-SSE-PACK-SELF-001 — Paquet d'un seul compte, relayé par le téléphone du joueur

Serveur de jeu sans COMSPEC Link et sans Zeus relié : le serveur demande à chaque joueur (`packFetch self`) de lire ses propres papiers, une fois sa liaison Athena établie. `GetIdentityPack [carte, "~self" | "~self:OPÉRATION", ""]` (DLL inchangée : l'opération part dans `op`) → `GET /api/sse/identity/pack?op=~self…` → même forme que le paquet, réduite au compte du jeton de jeu : son kit, ses papiers (sans `forgery_kind` ni `forgery_quality`), les identités qu'ils portent, son brief, les pays ; `watch`, `wanted`, `population` vides ; jamais servi par le cache partagé. Clé de communauté seule : 403 `unlinked`. Le serveur de jeu refiltre : kit du Steam ID qu'il lit lui-même, pays inconnus seulement, rien d'autre.

## ICD-SSE-PACK-REV-001 — Révision du paquet d'identité (304 / unchanged)

`GET /api/sse/identity/pack` porte une révision par page : empreinte du contenu de la page (pays, identités, papiers, avis, kits, population, recherchés, briefs, réglages, pagination), sans `generated_at`. Même contenu = même révision. Rétrocompatible : sans révision envoyée, la réponse est le paquet de toujours, plus un champ `revision` que la DLL publiée ignore (elle ne recopie que `version`, `generated_at`, `map`, `op`, `since`, `settings`).

| Sens | Champ | Contenu |
|---|---|---|
| Site → jeu | `revision` (corps) | 24 caractères hexadécimaux |
| Site → jeu | en-tête `ETag` | `W/"idp-<revision>"` |
| Jeu → site | `since_rev=<revision>` (paramètre, facultatif) | réponse 200 `{"version":1,"unchanged":true,"revision":…,"generated_at":…,"map":…,"op":…,"paging":{…}}`, sans aucun bloc de données |
| Jeu → site | en-tête `If-None-Match: W/"idp-<revision>"` (facultatif) | réponse 304 sans corps, même `ETag` |

- Révision par page (`offset`, `limit`) : le client garde celle de chaque page et la renvoie avec la même URL (`map`, `op`, `since`, `offset`, `limit`). La page 0 inchangée ne dit rien des suivantes : un client qui lit plusieurs pages envoie `since_rev` à chacune et suit `paging.next_offset` de la réponse `unchanged`.
- COMSPEC Link publié (2.0.118 et plus) : il envoie déjà `If-None-Match` avec l'empreinte reçue pour la même URL et ressert sa copie sur un 304 (GameApiResponseCompactor garde l'`ETag` posé par la route au lieu de l'empreinte du corps). Comme `since` = le `generated_at` de la dernière réponse, l'URL change dès qu'un nouveau paquet arrive : le 304 joue quand l'URL se répète (même `since`, ou rechargement sans `since`).
- Prochain COMSPEC Link : garder `revision` par page et l'envoyer en `since_rev` (ou garder l'URL et l'`ETag`) ; sur `unchanged`, ne rien reconvertir ni rejouer côté SQF (`fn_idAthenaPack` « apply »), garder le paquet en mémoire.
- Cache serveur : paquet et révision gardés 5 min par communauté (clé : communauté, carte, opération, `since`, page ; jamais partagés entre communautés, droits vérifiés avant chaque lecture). Toute écriture réussie du registre (portail SSE, papiers et kit d'un membre, couverture, identité, envois du jeu vers `/api/sse/`) le vide. `generated_at` d'une réponse servie par le cache = heure du calcul : l'utiliser comme `since` relit au pire un peu plus de papiers, jamais moins.
- Débit : 60 pages par minute et par appelant (compte du jeton de jeu, sinon communauté de la clé et IP), 240 contrôles `documents/lookup` par minute ; au-delà 429 `too_many_requests` avec `Retry-After`. Usage normal : 5 pages au début de la mission, puis au plus un paquet toutes les 2 min.

## ICD-SSE-PHONE-001 — Synchroniser un téléphone du jeu avec le web (extraction)

Canal : `SubmitIdCheck` de COMSPEC Link (aucune nouvelle DLL) → `POST /api/sse/identity/checks`, `kind` = `phone_extraction`. Données du téléphone du **personnage** (jeu) seulement : rien du vrai appareil du joueur. Aucun envoi avant l'autorisation de la licence (`comspec_overwatch_connect_fnc_licenceStatus`) ni quand la liaison est coupée (`uplinkOff`).

| Champ | Contenu |
|---|---|
| `idempotency_key` | 4 à 80 caractères, une clé par partie envoyée |
| `extraction_key` | 4 à 80 caractères : même valeur pour toutes les parties d'une même extraction |
| `part` | `[index, total]` (1 ≤ index ≤ total ≤ 20) ; absent = une seule partie |
| `consent` | `true` obligatoire (réglage du joueur, désactivé par défaut ; le MJ peut l'interdire pour la mission) |
| `device` | `uid` (identifiant stable du téléphone du jeu), `label`, `model`, `number`, `imei` |
| `owner` | `kind` (`player` ou `ai`), `label` (nom du personnage), `ai_uid` (IA : identifiant stable, voir ICD-SSE-LORE-001) |
| `op`, `grid`, `game_time` | portée et lieu de l'extraction |
| `data` | `contacts` `[{name, number, note}]`, `calls` `[{at, number, name, dir: in/out/missed, duration_s}]`, `messages` `[{at, from, to, body, channel}]`, `notes` `[{at, title, body}]`, `photos` `[{at, file, caption, grid}]`, `apps` `[{name, version}]`, `positions` `[{at, grid, note}]`, `places` `[{name, grid, note}]`, `network` `{carrier, number, imei, mac, wifi: [noms]}` |

Limites : 300 éléments par catégorie et par partie, 2 000 par extraction, 1 000 caractères par texte, 256 Ko par corps ; au-delà 422 `too_large`. Compte : celui du jeton de jeu (Bearer) ; clé de communauté seule : 403 `unlinked`. Le membre peut retirer son accord pour un téléphone depuis le site : 422 `consent_revoked`. Fonction coupée par la communauté : 422 `extractions_disabled` (422 et non 403 : COMSPEC Link rend tout 403 au jeu sous `unauthorized`). Réponse `{"id": n, "ref": "EXT-000123", "duplicate": bool}`.

## ICD-SSE-LORE-001 — Lore de session : remontée des IA et redescente des données décidées sur le web

Canal : `SubmitIdCheck` (aucune nouvelle DLL) → `POST /api/sse/identity/checks`. Une session = une communauté + un code d'opération (`op`) : une mission ne lit jamais une autre session. Aucun envoi avant l'autorisation de la licence.

**Remontée (`kind` = `lore_push`)**, par lots de 25 personnes au plus (500 par session) :

| Champ | Contenu |
|---|---|
| `idempotency_key` | une clé par lot |
| `op` | code d'opération (2 à 64 caractères `A-Z a-z 0-9 _ . : -`) |
| `mission` | `title`, `world`, `country` (pays par défaut des IA, ISO alpha-3) |
| `batch` | `[index, total]` |
| `persons[]` | `uid` (identifiant stable de l'IA, gardé d'une session à l'autre), `country`, `last`, `first`, `sex` (M/F), `dob` (AAAA-MM-JJ), `birth_place`, `nationality`, `profession`, `aliases` [textes], `height_cm`, `weight_kg`, `build`, `hair`, `eyes`, `marks`, `addresses` [textes], `vehicles` `[{plate, make, model, color}]`, `phones` [textes], `links` `[{uid, relation}]`, `status` `{wanted, bolo, hvt, watchlist, armed}` (booléens), `knowledge` (ce qu'elle sait, MJ), `answers` `{answers: [textes], evoke: texte, banned: [textes]}`, `cover`, `side`, `faction`, `group`, `class` |

Personne nouvelle : créée (identité du registre + fiche). Personne connue (même `uid`) : seules les données vides sur le web sont complétées ; ce que le MJ a écrit sur le web n'est jamais écrasé. Session verrouillée : rien n'est modifié, la date de remontée est notée. Réponse `ref` : `ok;créées;complétées;ignorées;verrou(0/1)`.

**Redescente (`kind` = `lore_pull`)** : `op`, `page` (0, 1, …). Réponse `ref` (7 400 octets au plus) au format `parseSimpleArray` :

`[[session, phase, verrou, heure_serveur, modifié_le, rafraîchissement_s, titre], [[uid, réf, [[clé, valeur]…], [[clé, valeur]…], [[clé, valeur]…]]…], page_suivante]`

- 1er tableau de paires : données **connues des fichiers** et déjà révélées (phase ≤ phase en cours) : `last`, `first`, `sex`, `dob`, `birth_place`, `nationality`, `profession`, `aliases`, `height_cm`, `weight_kg`, `build`, `hair`, `eyes`, `marks`, `addresses`, `vehicles`, `phones`, `cautions`, `flags`, `affiliation`, `country`, `in_files`.
- 2e : réponses de l'IA (`answers`, `evoke`, `banned`) : servies au serveur de jeu pour que l'IA réponde ; à garder côté serveur.
- 3e : données **cachées (MJ)** : seulement pour un compte relié qui a le droit « Préparer le lore des sessions » ; vide sinon (clé de communauté seule, joueur).
- `page_suivante` : 0 à la dernière page. Textes : `""` pour un guillemet, `\n` pour un retour à la ligne, `\p` pour `|`.

Variables posées en jeu (mod SSE) : voir le guide « Préparer une session : lore et synchro Athena ».

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

`routes/web.php` registers a large `/api/atak/*` surface beyond the sheets above (SOI, medevac extras, fire-teams, wardrobes, terminals, AAR, POI, terrain, scene, geo, QRF, explosive timers, weather, presence, …). New consumers must read the controller method; this ICD does not invent those payloads.

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

## ORBAT du BFT : `GET /api/atak/community-orbat`

Lecture par session de jeu : la clé commune du serveur ne dit pas qui joue (`403 session_required`, « compte non lié » sur le téléphone). Compte fermé ou rôle sans `organization.orbat.view` : `403 forbidden`. `App\Services\Atak\AtakCommunityOrbatService`, réponse identique pour toute la communauté, gardée 120 s.

```json
{
  "version": 1, "generated_at": "…", "revision": "sha1 du contenu", "truncated": false, "tag_salt": "…",
  "units": [{
    "id": 1, "parent": 0, "name": "…", "code": "…", "echelon": "…", "restricted": false,
    "posts": [{ "title": "…", "callsign": "…", "key": "commander|deputy|", "vacant": 0, "holders": [{ "user": 12, "tag": "…", "name": "Prénom N.", "grade": "SGT", "callsign": "Alpha 1", "trigram": "", "anonymized": false }] }],
    "members": []
  }]
}
```

- Vu comme un simple membre, sans droit de cadre : mêmes fiches masquées, unités masquées, anonymisées, restreintes, archivées et inactives que `/orbat`. En plus : unités `show_on_public_page = 0` et leurs sous-unités absentes. Fiche « masquer mes informations personnelles » : indicatif seul.
- Jamais d'e-mail ni de Steam ID. `tag` : 24 premiers caractères du SHA-256 de `orbat|tag_salt|SteamID64`, recalculé en jeu sur le UID de chaque joueur de mon camp (`HashText`). Compte sans Steam sûr, ou Steam porté par deux comptes : pas de tag, le membre reste « hors ligne » en jeu.
- Plafonds : 200 unités, 600 postes, 800 lignes de personnes (`truncated: true` au-delà), 60 caractères par texte.
- Côté jeu : `GetCommunityOrbat` de COMSPEC Link (Async seulement, une lecture à la fois) répond `OK|n|révision|generated_at`, puis `GetCommunityOrbatChunk [i]` en mémoire ; lu par `fn_bftOrbat`, 5 min au plus entre deux lectures, et rien n'est relu si la révision ne change pas. Champs ajoutés plus tard : ignorés par le téléphone.


## Bilan de l'année : `GET /api/atak/year-review`

- Auth : clé du jeu (comme l'ORBAT) ; le compte vient de la clé (`matchedUserId`), jamais d'un paramètre. Lecture seule.
- Réponse : `{state: OK | NOT_PUBLISHED, year, tenant, partial, slides: [{key, tone, kicker, name, title, lead, big, big_label, persona, stats: [[libellé, valeur]], bars: [[lettre, 0..100]], boards: [[titre, [[rang, nom, valeur]]]]}]}`. Écrans de `YearReviewStory` (mêmes que `/retrospective`), écran « formule payante » omis, bilan de l'unité seulement si `YearReviewAccess::collectiveAllowed`. Au plus 24 écrans, 128 Ko.
- Côté jeu : `GetYearReview` puis `GetYearReviewChunk [i]` (COMSPEC Link, comme l'ORBAT) ; lu par `fn_yearReview`, jamais avant `COMSPEC_AthenaReady`.

## App Communauté : `GET /api/atak/community-brief`

Lecture par clé de jeu (`requireTenant`), limitée au tenant de la clé. Le contenu se saisit dans Ressources › Consignes et kit ATAK (`/back-office/ressources/atak-consignes`), reste rangé dans la configuration d'expérience (`community_notes`, `community_kit`, sans migration) et se lit par `App\Support\AtakCommunityBrief`. La DLL coupe sa réponse à 7 996 octets, notes d'abord puis kit : le back-office affiche ce volume et refuse de le dépasser. Sans saisie, l'API renvoie des listes vides.

```json
{
  "notes": [{ "id": 1, "kind": "consigne|ordre|tip", "title": "…", "body": "…", "updated_at": "…" }],
  "kit": [{ "classes": ["ACE_fieldDressing"], "count": 2, "label": "Pansements" }],
  "updated_at": "…"
}
```

- Écriture par la gestion de la communauté ; texte brut, sans HTML. Plafonds côté site et côté jeu : 40 notes, titre de 60 et texte de 600 caractères, 40 lignes de kit.
- Côté jeu : commande `GetCommunityBrief` de COMSPEC Link, appelée par `fn_tenantAction` « briefLoad » avec `extAwait`, 5 min au plus entre deux lectures. Réponse : `OK|` puis une ligne par entrée, `N\tgenre\ttitre\ttexte` ou `K\tclasses,séparées\tnombre\tlibellé`.
- Priorité du kit : mission (`COMSPEC_ATAK_RequiredKit`), puis Athena, puis le réglage `comspec_atak_native_kit_required`.

## Poste TOC web : salle du camp et plans (`POST /api/atak/briefing-plan`, canal `toc_room` ; commandes web genre `toc`)

- Jeu vers site : le mod (`fn_tocWeb`) envoie l'instantané de la salle TOC d'un camp par la commande COMSPEC Link existante `BriefingPlan.Push`, avec `"channel": "toc_room"`, `side`, `mapId`, `mission_key`, `world`, `rev`, `wc` (ids des commandes web appliquées) et `room` (chronos, main courante, EXCHECK, demandes, FLASH, postes, pertes, satellites, écrans, heure locale). Sans UID de joueur. Une révision plus ancienne de la même mission est ignorée.
- Site vers jeu : file `atak_web_commands`, genre `toc`, cible `*`, argument `s` = camp ; commandes `timeradd`, `timerstart`, `timerstop`, `timerreset`, `timerdel`, `log`, `flash`, `flashclear`, `exadd`, `exdone`, `exundo`, `exdel`, `reqset`, `kill`, `plnclr`, `plnadd`. Fil ASCII de 480 caractères au plus.
- Détail, règle d'autorité et correspondance des types de plan : `docs/TOC-WEB.md`.
