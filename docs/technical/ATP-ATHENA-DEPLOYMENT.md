ATHENA C2
TECHNICAL PUBLICATION

Document: ATHENA C2 — Deployment and Release Manual
Reference: ATP-A3-11
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value          |
| --------------- | -------------- |
| Document ID     | ATP-A3-11      |
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

# ATHENA C2 — Deployment and Release Manual

Documents the chain **as present in the repository**. Companion: `docs/DEPLOIEMENT-CANAUX.md`, `docs/technique/configuration-et-deploiement.md`.

---

## 1. Environments vs release channels

Two different concepts:

| Concept | Values | Where |
| ------- | ------ | ----- |
| `APP_ENV` | local, dev, test, staging, production, … | Instance `.env` |
| Deployment channel | DEV → INTERNAL → TEST → PREPROD → PROD | Table `deployment_channels`, priorities 10–50 |

`PlatformFeatureDeploymentEvaluator::resolveTargetChannel()`:

- override `DEPLOYMENT_CHANNEL` if set;
- else map `local/dev`→DEV, `test`→TEST, `staging/preprod`→PREPROD, `internal`→INTERNAL, else **PROD**.

A channel is not a second PHP cluster by itself. The same database can hold module versions and campaigns for several channels.

---

## 2. Server prerequisites

| Item | Fielded requirement |
| ---- | ------------------- |
| PHP | `>=8.4` (`composer.json`); CI and VPS use 8.4 |
| Database | MySQL/MariaDB, `utf8mb4` |
| Web | Nginx document root `…/public` (`docs/nginx.example.conf`) |
| TLS | Expected in production (`SESSION_SECURE_COOKIE`) |
| DNS | Instance host (workflow example `athena.ttrd.fr`) |
| FPM | `php8.4-fpm` reload after deploy |

Nginx example rewrites `/public/…` to `/` so Workshop clients calling `/public` still hit the app. Do not set `APP_BASE_PATH=/public` on that VPS layout.

Historical Hostinger FTP deploy is **retired** (workflow removed). Do not document FTP as current.

---

## 3. Configuration

Copy `.env.example` → `.env`. C2-relevant keys: `APP_*`, `DB_*`, `SESSION_*`, `JWT_SECRET`, `TACTICAL_API_STRICT`, `X_COMSPEC_KEY`, `NODE_ATAK_URL` (optional), `STEAM_WEB_API_KEY`, `ERROR_ALERT_*`, `CRON_SECRET`, `MIGRATIONS_WEB_PASSWORD`, optional `ATAK_DEFAULT_TENANT_ID` (mono-tenant only).

Permissions: `storage/` writable (logs, sessions, cache, uploads).

---

## 4. Database migrations

- SQL: `migrations/`
- Orchestration: `run-migrations.php` + `bootstrap/*_migration.php`
- Web UI: password `MIGRATIONS_WEB_PASSWORD`
- Production: backup before migrate

---

## 5. Reverse proxy / TLS

Terminate TLS at Nginx. FastCGI to PHP-FPM. Static assets from `public/assets/`. Map tiles may use `ATAK_MAP_TILES_CDN`.

---

## 6. CI / CD (exists)

| Workflow | Trigger | Actions |
| -------- | ------- | ------- |
| `.github/workflows/ci.yml` | PR/push | PHPUnit, PHPStan, Composer audit, Tailwind build — PHP 8.4, Node 20 |
| `.github/workflows/deploy-vps.yml` | push `main`, `workflow_dispatch` | SSH, `git fetch` + ff-only merge, `composer install --no-dev`, chown storage, reload php-fpm |

There is no multi-stage Kubernetes pipeline. Rollback = git previous revision on the VPS (ff-only merge does not rewind; rollback is a deliberate checkout **if** operators do it on the host — not automated in the workflow).

Post-deploy smoke: `scripts/post-deploy-smoke-tests.php` (hits `/api/health` — which requires a logged-in session).

---

## 7. Procedure

```text
Build
↓
Validate   (CI: PHPUnit / PHPStan)
↓
Package    (Composer --no-dev on VPS; Overwatch PBOs separately)
↓
Deploy     (SSH ff-merge on main)
↓
Migrate    (run-migrations.php when schema changed)
↓
Health Check  (/api/atak/ping anonymous; /api/health authenticated)
↓
Release    (optional: platform module version + channel current)
↓
Observe    (logs, error-alerts, Tacmap PLI)
```

### 7.1 Application (PHP)

1. Merge to `main` after CI green.
2. Deploy workflow updates `/var/www/athena.ttrd.fr` (path in workflow).
3. Run migrations if this revision adds schema.
4. Confirm `.env` secrets still present on disk.

### 7.2 Overwatch pack

1. Build PBOs from `mod/UptoDate/Sources/comspec-overwatch-addons/` (Addon Builder / Mikero — see pack compilation notes).
2. Build `COMSPECExtension_x64.dll` from `mod/UptoDate/COMSPECExtension/` (`net8.0` Native AOT).
3. Place DLL at `@COMSPECOverwatch/` root.
4. Version: connect `versionStr` (source 1.5.13) vs CHANGELOG header (may lag).
5. Publish Workshop / `mod/UptoDate/` drop. Signature: follow current pack scripts; this manual does not invent a signing CA.

### 7.3 Platform module campaigns

Admin UI `/admin/system/deployment/campaigns`:

- versions: `draft` → `validated` → `published` / `rollback_ready` / `deprecated`
- one current row per `(module_id, channel_id)`
- jobs processed by `DeploymentCampaignProcessor` (admin POST or future cron)

This is **not** the same as the VPS git deploy.

---

## 8. Health, monitoring, logs

| Check | Notes |
| ----- | ----- |
| `GET /api/atak/ping` | `{ok, service: atak, server_ms}` — no DB |
| `GET /api/health` | DB `SELECT 1` — **AuthMiddleware** |
| `storage/logs/app.log` | Monolog |
| `storage/logs/error-alerts.log` + mail | `ERROR_ALERT_EMAIL` |
| `audit_logs` | Admin |

No APM product is declared in-repo. Do not invent one.

---

## 9. Rollback

| Layer | Method |
| ----- | ------ |
| PHP code | Previous git commit on VPS (manual; workflow is ff-only) |
| Schema | Restore DB backup — no automatic down migrations documented as standard |
| Module channel | `rollback_ready` version + set current release |
| Overwatch | Players revert Workshop / previous pack; full Arma restart |

---

## Implementation References

- `.github/workflows/ci.yml`
- `.github/workflows/deploy-vps.yml`
- `.env.example`
- `docs/nginx.example.conf`
- `app/Services/Platform/DeploymentCampaignProcessor.php`
- `migrations/20260415000001_release_channels_and_tester_communities.sql`

## References

- ATHENA C2 System Architecture — ATP-A3-01
- ATHENA C2 Security Architecture — SEC-A3-01
- ATHENA C2 Administrator Manual — TM-A3-31
- ATHENA C2 Capability Registry — REG-A3-01
