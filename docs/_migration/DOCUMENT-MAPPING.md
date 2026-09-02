ATHENA C2
TECHNICAL PUBLICATION

Document: Documentation Migration Mapping
Reference: MIG-A3-01
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value            |
| --------------- | ---------------- |
| Document ID     | MIG-A3-01        |
| Revision        | 1.0              |
| Status          | CONTROLLED       |
| Owner           | COMSPEC          |
| System          | ATHENA C2        |
| Last Review     | 2026-09-02       |
| Source of Truth | Git repository   |

## Revision History

| Revision | Date       | Author  | Changes                          |
| -------- | ---------- | ------- | -------------------------------- |
| 1.0      | 2026-09-02 | COMSPEC | Initial controlled publication   |

## Purpose

This mapping records how existing Markdown in the repository was treated during the creation of the ATHENA C2 documentation system. Information was transferred only after verification against executed code, schema, configuration, tests, and current UI. Old Markdown is never treated as source of truth.

## Official publications (target)

| Reference | Document | Path |
| --------- | -------- | ---- |
| FM-A3-01  | ATHENA C2 Field Manual | `docs/doctrine/FM-ATHENA-C2.md` |
| SOP-A3-01 | ATHENA C2 Standard Operating Procedures | `docs/sop/SOP-ATHENA-C2.md` |
| TM-A3-21  | ATHENA ATAK Operator Manual | `docs/manuals/TM-ATAK-OPERATOR.md` |
| TM-A3-31  | ATHENA C2 Administrator Manual | `docs/manuals/TM-ATHENA-ADMIN.md` |
| ATP-A3-01 | ATHENA C2 System Architecture | `docs/technical/ATP-ATHENA-SYSTEM-ARCHITECTURE.md` |
| TM-A3-11  | COMSPEC Overwatch Technical Manual | `docs/technical/TM-COMSPEC-OVERWATCH.md` |
| ICD-A3-01 | ATHENA–ATAK Interface Control Document | `docs/technical/ICD-ATHENA-ATAK.md` |
| SEC-A3-01 | ATHENA C2 Security Architecture | `docs/technical/SEC-ATHENA-C2.md` |
| ATP-A3-11 | ATHENA C2 Deployment and Release Manual | `docs/technical/ATP-ATHENA-DEPLOYMENT.md` |
| REG-A3-01 | ATHENA C2 Capability Registry | `docs/registry/REG-ATHENA-CAPABILITIES.md` |

## Inventory scope

Approximately 670 Markdown files exist in the repository. This mapping covers:

- ATHENA C2 / ATAK / Tacmap / Overwatch product and integration notes
- Security, tenant, deployment notes that feed C2 publications
- Portal user/technical docs that remain in place because they are served by the website or cover non-C2 products

Out of scope for absorption into FM/SOP/ATP/TM/ICD/SEC/REG:

- `docs/bugs/` (operational bug memory)
- `docs/sse/` and `mod/docs/` SSE pack manuals
- `docs/dev/` SPOTREP / TECHREP public bulletins
- LMS, forum, recruitment, courrier, ORBAT, tableau opérationnel portal chapters
- Vendor READMEs under `public/assets/vendor/`

---

## Mapping table

| Existing document | Information | New destination | Action |
| ----------------- | ----------- | --------------- | ------ |
| `docs/README.md` | Former ATAK feature index (July 2026, emoji, outdated phases) | docs/README.md as C2 portal | REPLACE |
| `CHANGELOG-ATAK.md` | Product release journal (Overwatch 1.5.x) | ATP-A3-11 / REG-A3-01 (status only) | KEEP |
| `docs/ATAK-Documentation-Produit.md` | Product overview Tacmap + Overwatch | FM-A3-01, TM-A3-21 | ARCHIVE |
| `docs/ATAK-WEB-DOCUMENTATION-PRODUIT.md` | ATAK Web product/ops (2026-07-24) | TM-A3-21 | ARCHIVE |
| `docs/ATAK-WEB-FEATURES.md` | Feature catalogue ATAK Web | REG-A3-01 (verified vs code) | ARCHIVE |
| `docs/ATAK-WEB-VERSION-FORUM.md` | Forum marketing ATAK Web | — | ARCHIVE |
| `docs/ATAK-RECHERCHE-FONCTIONNALITES-EXTENSIONS.md` | Real ATAK ecosystem study | FM-A3-01 (context), REG-A3-01 (gaps) | ARCHIVE |
| `docs/GUIDE-INTEGRATION-API-ATAK.md` | REST guide (~31 endpoints, incomplete vs current API) | ICD-A3-01 | ARCHIVE + stub |
| `docs/QUICK-START-INTEGRATION.md` | Dev 30-min integration (stale branches) | ICD-A3-01, TM-A3-21 Quick Start | ARCHIVE + stub |
| `docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md` | Delivery note Phases 1–2 | ICD-A3-01, ATP-A3-01 | ARCHIVE |
| `docs/SYNTHESE-FINALE-INTEGRATION-ATAK.md` | Claims ATAK Web still pending | REG-A3-01 | ARCHIVE |
| `docs/RECAPITULATIF-INTEGRATION-MOD-ATAK.md` | SQF reports/POI/MEDEVAC/QRF | TM-A3-11, ICD-A3-01 | ARCHIVE |
| `docs/ETAT-AVANCEMENT-ATAK.md` | Progress board 40% / Phases 1–5 | REG-A3-01 | ARCHIVE |
| `docs/PHASE-2.5-INTELLIGENCE-ENRICHMENTS.md` | Scoring/routing enrichments | REG-A3-01, ICD-A3-01 | ARCHIVE |
| `docs/NOUVELLES-FEATURES-ATAK-MOD.md` | Proposed Overwatch features | REG-A3-01 (PLANNED only) | ARCHIVE |
| `docs/AUDIT-FEATURES-ATAK-MOD.md` | Audit proposed vs routes/DB | REG-A3-01 | ARCHIVE |
| `docs/PLAN-TESTS-ATAK.md` | Backend test plan Phases 1–2 | SOP-A3-01 (health checks) | ARCHIVE |
| `docs/atak-c2-roadmap.md` | BFT enrich, designator, Web→Arma, queue | REG-A3-01 (many now FIELDED) | ARCHIVE |
| `docs/atak-coordinates.md` | Arma metres, CRS Simple, no Lat/Long | ATP-A3-01, ICD-A3-01 | ARCHIVE |
| `docs/atak-sous-domaine.md` | DNS `atak.athena.ttrd.fr` / Node 3001 | ATP-A3-11 | ARCHIVE |
| `docs/atak-ui-improvements-summary.md` | Session CSS notes | — | ARCHIVE |
| `docs/athena-header-ui-improvements.md` | Header CSS session notes | — | ARCHIVE |
| `docs/COMPARAISON-COMSPEC-CTAB-SIT-ATAK.md` | Product comparison | FM-A3-01 (short) | ARCHIVE |
| `docs/COMPARAISON-PRODUIT-COMSPEC-CTAB-SIT.md` | Duplicate comparison | — | ARCHIVE |
| `docs/COMPARAISON-PRODUIT-VERSION-FORUM.md` | Forum comparison | — | ARCHIVE |
| `docs/MODE-REALISME-COMPLET.md` | Realism CBA catalogue | TM-A3-11, TM-A3-21 | ARCHIVE |
| `docs/ROLEPLAY-ATAK-ENHANCED.md` | Terminal damage / screen effects | TM-A3-21, SOP-A3-01 | ARCHIVE |
| `docs/SESSION-SUMMARY-2026-07-24.md` | Cursor session log | — | ARCHIVE |
| `docs/ATHENA-MYTHOLOGIE.md` | Brand storytelling | — | KEEP (brand, not C2 corpus) |
| `docs/ATHENA-MYTHOLOGIE-VERSION-FORUM.md` | Forum mythologie | — | ARCHIVE |
| `docs/DEPLOIEMENT-CANAUX.md` | DEV→PROD channels, campaigns | ATP-A3-11 | KEEP (implementation companion) |
| `docs/FEATURE-TIERS.md` | Plan keys including `atak` Standard+ | TM-A3-31 | KEEP (product pricing) |
| `docs/AUDIT-TENANT-FILTRAGE.md` | Tenant isolation audit | SEC-A3-01 | KEEP |
| `docs/DECISION-IDENTITE-MULTI-TENANT.md` | Identity UNIQUE(tenant,email) | SEC-A3-01, TM-A3-31 | KEEP |
| `docs/technique/architecture.md` | PHP monolith, middlewares | ATP-A3-01 | KEEP + pointer |
| `docs/technique/securite-et-permissions.md` | Session, RBAC, tactical keys | SEC-A3-01 | KEEP + pointer |
| `docs/technique/checklist-securite-release.md` | Pre-release security checklist | SEC-A3-01, ATP-A3-11 | KEEP |
| `docs/technique/configuration-et-deploiement.md` | .env, migrations | ATP-A3-11, TM-A3-31 | KEEP + pointer |
| `docs/technique/atak-mobile.md` | `/atak/mobile`, QR connect | ATP-A3-01, TM-A3-21 | KEEP + pointer |
| `docs/technique/atak-geo-network.md` | Geo places/roads | ICD-A3-01, REG-A3-01 | KEEP |
| `docs/technique/atak-map-c2-refonte.md` | Tacmap JS modules | ATP-A3-01 | KEEP |
| `docs/technique/terrain-3d-integration.md` | Terrain 3D renderer | ATP-A3-01, REG-A3-01 | KEEP |
| `docs/technique/atak-prod-features.md` | Branch deploy flags | ATP-A3-11 | ARCHIVE |
| `docs/technique/atak-roleplay-simulation.md` | Server-side degradation | SOP-A3-01, TM-A3-11 | KEEP |
| `docs/technique/atak-mod-align-prompt.md` | Agent prompt | — | ARCHIVE |
| `docs/technique/atak-mod-updates-necessaires-prompt.md` | Agent prompt | — | ARCHIVE |
| `docs/technique/acre-comms-atak-sse-sigint.md` | ACRE/SIGINT target spec, not delivered | REG-A3-01 PLANNED | KEEP (roadmap) |
| `docs/technique/radio-proximite-overwatch.md` | Radio metadata on Tacmap | TM-A3-21, ICD-A3-01, REG-A3-01 | KEEP |
| `docs/technique/overwatch-mod/index.md` | Portal-served Overwatch index (stamped 1.4.11) | TM-A3-11 | KEEP + pointer (website) |
| `docs/technique/overwatch-mod/architecture.md` | Addon layout | TM-A3-11 | KEEP (website `/documentation/references`) |
| `docs/technique/overwatch-mod/independance-couche-interoperabilite-api.md` | Soft deps / API boundary | TM-A3-11, ICD-A3-01 | KEEP |
| `docs/technique/overwatch-mod/compilation.md` | PBO/DLL/Workshop | TM-A3-11, ATP-A3-11 | KEEP (website) |
| `docs/technique/overwatch-mod/bibliotheques-et-dependances.md` | CBA/ACE/cTab | TM-A3-11 | KEEP (website) |
| `docs/utilisateur/overwatch-mod.md` | Portal links to `/atak/mod` | TM-A3-21 | KEEP + pointer |
| `docs/utilisateur/equipement-modpacks-atak.md` | Loadouts, ATAK tools | TM-A3-21, TM-A3-31 | KEEP + pointer |
| `docs/utilisateur/bibliotheque-marqueurs.md` | Marker legend | SOP-A3-01, TM-A3-21 | KEEP |
| `docs/utilisateur/tableau-operationnel.md` | Community ops wall (not Tacmap) | — | KEEP |
| `mod/UptoDate/docs/README.md` | Pack index stamped 1.3.0 | TM-A3-11 | KEEP (packaging) |
| `mod/UptoDate/docs/guide-joueur.md` | Operator connect/hub | TM-A3-21 | KEEP (packaging) + MERGE |
| `mod/UptoDate/docs/guide-chef-mission.md` | Zeus/Eden checklist | SOP-A3-01, TM-A3-31 | KEEP + MERGE |
| `mod/UptoDate/docs/realisme-liaison-atak.md` | Liaison realism | TM-A3-21, SOP-A3-01 | KEEP + MERGE |
| `mod/UptoDate/docs/architecture-et-addons.md` | Duplicate of technique/ | TM-A3-11 | KEEP (pack mirror) |
| `mod/UptoDate/docs/philosophie-technique.md` | Duplicate interop | TM-A3-11 | KEEP |
| `mod/UptoDate/docs/compilation-et-publication.md` | Build/publish pack | ATP-A3-11 | KEEP |
| `mod/UptoDate/@COMSPECOverwatch/CHANGELOG.md` | Steam-facing changelog | ATP-A3-11 | KEEP |
| `mod/docs/*` | SSE pack manuals | SSE corpus | KEEP |
| `docs/sse/*` | SSE product docs | SSE corpus | KEEP |
| `docs/bugs/*` | Bug notes | — | KEEP |
| `docs/dev/*` | SPOTREP / TECHREP | — | KEEP |

---

## Conflicts detected

Order of trust applied: executed code → schema → configuration → tests → current UI → recent docs → old docs → roadmap.

### C1 — ATAK Web described as pending

- **Old:** `SYNTHESE-FINALE-INTEGRATION-ATAK.md` states the web Tacmap is still pending.
- **Code:** `AtakController`, `public/assets/js/comspec-operational-map.js`, route `/atak`.
- **Resolution:** Tacmap web is FIELDED. Old claim discarded.

### C2 — Socket.IO / Node as live C2 backend

- **Old:** several July 2026 notes and `atak-sous-domaine.md` describe Node on port 3001 as the live ATAK service.
- **Code:** `routes/web.php` comment « API ATAK Full PHP (parité Node — polling, pas de Socket.IO) ». Live CRUD is PHP `AtakApiController`. `NODE_ATAK_URL` remains an optional env for a companion service.
- **Resolution:** Live interface is PHP polling. Node is optional/legacy; marked UNVERIFIED as a live requirement.

### C3 — Roadmap items already implemented

- **Old:** `atak-c2-roadmap.md` lists BFT health/fuel/ammo, designator, marker sync Arma→Web, GetMarkers Web→Arma, image upload, in-memory retry queue as future work.
- **Code:** `UpdatePosition` extra JSON includes health/fuel/ammo; `SendDesignator`; `SendMarker` / `GetMarkers`; `UploadImage`; `PendingPosts` ConcurrentQueue (max 500, in-memory).
- **Resolution:** Those items are FIELDED (with limits). Persistent disk offline queue remains PLANNED. Dedicated JTAC CAS 9-line form remains partial (MEDEVAC 9-line FIELDED; CAS is order/chat + line checks).

### C4 — Silent tenant_id = 1

- **Old:** historical audits and some integration notes imply tenant 1 as default.
- **Code:** `AtakApiController::resolveTenantId` explicitly does not fall back to tenant 1. Optional env `ATAK_DEFAULT_TENANT_ID` only. Default **map_id = 1** is real.
- **Resolution:** Documented in SEC-A3-01. Default map_id=1 is a limitation, not a tenant default.

### C5 — Version stamps

- **Old:** pack docs and `docs/technique/overwatch-mod/index.md` stamp Overwatch **1.4.11** / **1.3.0**.
- **Code:** `connect/config.cpp` versionStr **1.5.13**; extension const **1.18.6**; packaged CHANGELOG header **1.5.12**.
- **Resolution:** Technical manuals use source `versionStr` 1.5.13 / extension 1.18.6 and note the CHANGELOG header lag.

### C6 — JWT as game authentication

- **Old:** integration guides speak of JWT for the mod.
- **Code:** Game auth uses opaque access/refresh tokens hashed in `game_sessions`. `AtakTokenService` produces an HMAC token for the web Tacmap page (`window.ATAK_TOKEN`); browser API calls use the PHP session. SQF does not handle JWT.
- **Resolution:** ICD and SEC distinguish the three mechanisms.

### C7 — Workspace picker in Arma

- **Old:** some product notes imply the operator selects a mission/workspace in game.
- **Code:** No SQF workspace picker. DLL payloads hardcode `"mapId":1`. Web operations workspace lives at `/operations` (table `operations`). CBA `comspec_overwatch_tenant_id` is not authority.
- **Resolution:** SOP and operator manual: community is resolved by Athena after auth; map defaults to 1 unless the API receives another map_id.

### C8 — Endpoint count « 31 »

- **Old:** `GUIDE-INTEGRATION-API-ATAK.md` documents ~31 endpoints.
- **Code:** `routes/web.php` registers a much larger `/api/atak/*` surface plus aliases `/api/chat`, `/api/pings`, `/api/cas`, `/api/nine-line`, `/api/game/v1/*`.
- **Resolution:** ICD-A3-01 is the contract. The old count is obsolete.

### C9 — Feature gate on API vs web

- **Old:** FEATURE-TIERS implies ATAK is gated everywhere.
- **Code:** `FeatureGateService::allows($tenantId, 'atak')` is enforced on the web Tacmap controller. `AtakApiController` does not call the feature gate.
- **Resolution:** Plan gate is web-UI oriented. API relies on tactical key / session / maintenance. SECURITY GAP noted in SEC-A3-01.

---

## Stubs left in place

After archive, short redirect stubs remain at:

- `docs/GUIDE-INTEGRATION-API-ATAK.md` → ICD-A3-01
- `docs/QUICK-START-INTEGRATION.md` → TM-A3-21 / ICD-A3-01
- `docs/ATAK-Documentation-Produit.md` → FM-A3-01 / TM-A3-21
- `docs/ATAK-WEB-DOCUMENTATION-PRODUIT.md` → TM-A3-21
- `docs/NOUVELLES-FEATURES-ATAK-MOD.md` → REG-A3-01
- `docs/SYNTHESE-FINALE-INTEGRATION-ATAK.md` → ATP-A3-01 / ICD-A3-01 / REG-A3-01

Portal-served files under `docs/technique/overwatch-mod/` and `docs/utilisateur/` are **not** moved: `DocumentationController` loads them for `/documentation/references`.

## References

- ATHENA C2 Field Manual — FM-A3-01
- ATHENA C2 Standard Operating Procedures — SOP-A3-01
- ATHENA C2 System Architecture — ATP-A3-01
- COMSPEC Overwatch Technical Manual — TM-A3-11
- ATHENA–ATAK Interface Control Document — ICD-A3-01
- ATHENA C2 Security Architecture — SEC-A3-01
- ATAK Operator Manual — TM-A3-21
- ATHENA C2 Administrator Manual — TM-A3-31
- ATHENA C2 Deployment and Release Manual — ATP-A3-11
- ATHENA C2 Capability Registry — REG-A3-01
