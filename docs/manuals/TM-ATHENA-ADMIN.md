ATHENA C2
TECHNICAL PUBLICATION

Document: ATHENA C2 — Administrator Manual
Reference: TM-A3-31
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value          |
| --------------- | -------------- |
| Document ID     | TM-A3-31       |
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

# ATHENA C2 — Administrator Manual

Three audiences are separated on purpose.

| Audience | Scope |
| -------- | ----- |
| **Opérateur** | TM-A3-21 only |
| **Administrateur communauté** | Members, roles, ATAK config, maps, operations, supervision of the community picture |
| **Administrateur système** | Instance `.env`, database, TLS, release channels, platform RBAC, secrets |

---

## A. Administrateur communauté

### A.1 Create and own a community (tenant)

1. Create the community from the portal wizard (subject to `community_create` and billing).
2. The community is a **tenant**. Users are unique per `(tenant_id, email)`.
3. The system community slug `default` is not an operational unit back-office.

### A.2 Members and roles

RBAC has three layers:

| Layer | Examples | Where |
| ----- | -------- | ----- |
| Site | `admin.system` | Platform staff only |
| Community | `tenant_admin`, `community_owner` | Organisation back-office |
| Intra | `member`, `officer`, … | Day-to-day |

Assign community roles in the organisation back-office. Do not grant platform-reserved permissions (`admin.system`, `site.*`, `platform.*`).

Operators need membership in the community that owns the Tacmap. Switching community in the web session changes which picture they see.

### A.3 ATAK configuration (community)

Stored in `tenant_atak_config`:

| Item | Purpose |
| ---- | ------- |
| Access key | Tactical API key for Overwatch when not using a game session token |
| JWT secret | HMAC token for the **web** Tacmap page (`window.ATAK_TOKEN`). Browser API calls still use the PHP session |
| Arma server host/port | Informational / credentials blob |
| Default map slug | Preferred Tacmap world |
| Maintenance | When enabled, tactical API returns **503** `maintenance` |
| Instructions | Shown to members |
| Experience / photo HUD | Optional JSON |

Generate the access key in the community ATAK settings. Give it to the mission maker for CBA **or** prefer player ATHENA login so the key is not shared in screenshots.

**URL C2:** the Overwatch API base is the ATHENA site (often `https://athena.ttrd.fr/public` as launcher fallback). Nginx may rewrite `/public` to `/`. Do not invent a second host unless system admin documented it.

### A.4 Maps

Maps live in `atak_maps` (slug, world_name, tile pattern, config). Live Overwatch posts currently send **`mapId: 1`**. Align the community default map with that live map or accept that field traffic lands on map 1.

### A.5 Workspaces and missions

- **Tacmap** = live COP on a map id.
- **Operations workspace** = `/operations` planning (uuid, `workspace_key`, phases, overlay). Operators do not select it in CBA.

Assign the operation to the serial on the portal. Publish overlays only if the role allows `operations.overlay.publish`.

### A.6 Feature gate (ATAK web)

Plan feature key **`atak`**:

| Plan | Web Tacmap |
| ---- | ---------- |
| `free` | Typically denied |
| `standard` / `pro` / `pro_plus` | Allowed |

This gate is enforced on the **web** map controller. The HTTP tactical API is not gated by the same check. Restrict the field with the access key, maintenance flag, and membership.

### A.7 Supervision

- Tacmap: live units, chat, errors in the journal.
- Activity / device logs endpoints exist for TOC.
- Stale PLI: 120 s without update.
- If the picture mixes two communities, stop the serial and check who is logged where.

### A.8 Versions

Tell players the Overwatch pack version (connect `versionStr`, currently 1.5.13 in source) and that they must fully restart Arma after a pack change. Workshop changelog is the player-facing text.

---

## B. Administrateur système

### B.1 Instance

See ATP-A3-11. Minimum: PHP 8.4, MySQL/MariaDB, document root `public/`, TLS, `.env` not in git.

### B.2 Secrets (C2-relevant)

| Secret | Role |
| ------ | ---- |
| `JWT_SECRET` | Fallback HMAC for Tacmap token if tenant jwt_secret empty. **Must not stay empty** (code fallback string exists — SECURITY GAP) |
| `X_COMSPEC_KEY` / `ATAK_INTEL_SECRET` | Platform tactical key |
| `TACTICAL_API_STRICT` | Force key/session outside production |
| Tenant access keys | Per community |
| `STEAM_WEB_API_KEY` | Steam game auth |
| Session cookie flags | `SESSION_SECURE_COOKIE` |

Never set `tenant_id = 1` in application code as a silent default. Optional `ATAK_DEFAULT_TENANT_ID` is only for a deliberate mono-tenant instance.

### B.3 JWT vs game tokens

| Mechanism | Lifetime | Use |
| --------- | -------- | --- |
| PHP session | `SESSION_LIFETIME` minutes (example 1440) | Portal + Tacmap browser |
| HMAC ATAK token | 3600 s | Injected in the Tacmap page |
| Game access token | 7200 s | Overwatch Bearer / `X-ATAK-TOKEN` |
| Game refresh token | 30 days | Restore session |

### B.4 RBAC platform

`/admin/...` requires `admin.system`. Deployment campaigns, release channels (DEV → INTERNAL → TEST → PREPROD → PROD), audit logs live there.

### B.5 Health and logs

| Endpoint | Auth | Role |
| -------- | ---- | ---- |
| `GET /api/atak/ping` | Exempt | Cheap liveness, no DB |
| `GET /api/health` | Logged-in session | `SELECT 1` |

Logs: `storage/logs/app.log`, error-alerts mail, `audit_logs`. ATAK exceptions on `/api/atak*` render JSON 503 with `Retry-After: 30`.

### B.6 Maintenance

Platform `MAINTENANCE_*` and per-tenant ATAK maintenance are different. The latter only blocks tactical API for that community.

### B.7 Pack and DLL

System admin does not compile PBOs for each community. Point communities at the published Overwatch pack. Signing: follow current pack/Workshop practice (ATP-A3-11); do not claim a signature process that is not in the release scripts.

---

## C. Operator reminder (admin-facing)

Operators must not receive tenant ids, JWT secrets, or SQL. Give them: community name, pack link, CBA URL, and TM-A3-21.

---

## References

- ATHENA C2 Field Manual — FM-A3-01
- ATHENA C2 Standard Operating Procedures — SOP-A3-01
- ATAK Operator Manual — TM-A3-21
- ATHENA C2 Security Architecture — SEC-A3-01
- ATHENA C2 Deployment and Release Manual — ATP-A3-11
- ATHENA C2 Capability Registry — REG-A3-01
