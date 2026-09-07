# Athena English localisation audit

**Baseline date:** 7 September 2026

**Audit command:** `php scripts/audit-i18n.php --json`

**Scope:** all Git-tracked presentation PHP/JS/HTML, the central route registry and both language catalogues.

## Executive summary

This is the repository-wide baseline and migration ledger, not a false declaration of completion. The initial scan inventories **12,684 tracked files**, **1,538 presentation files**, and **1,980 registered HTTP route declarations**. It reports **27,176 candidate French UI lines in 1,302 files**. Candidates deliberately include some false positives and must be classified during migration; the line-level, machine-readable list is produced by the audit command.

The existing central i18n infrastructure is sound and is therefore retained. The first shared-component lot localises confirmation actions, loaders, steppers, next-step links, premium gates and the no-organisation state. Catalogue parity and static-key integrity are enforced by `--strict`. Full English coverage is **not yet certified**: the matrix below records the measured debt rather than hiding it behind French fallback.

## Existing mechanism

* `LocaleService` supports `fr` and `en`. Resolution order is explicit cookie (`athena_locale`), session (`locale`), authenticated user profile, `Accept-Language`, then `APP_LOCALE`.
* `LocaleMiddleware` boots locale resolution at request entry. `LocaleController` persists a safe internal language switch in cookie, session and, when available, the user profile.
* `Translator` loads `lang/{locale}/{group}.php`; a missing English key safely falls back to French, then to the key. `__()` resolves stable keys, while `t()` accepts an explicit display fallback.
* `i18n_phrase()` supports incremental migration of existing French source phrases. It returns the source in French and resolves an English slug catalogue in English. It is already used for the shared navigation tree.
* There is no query-string locale override. The query string on `/locale/{locale}` carries only a validated internal redirect.
* Client-side copy has no universal runtime translator. JavaScript UI must receive translated strings from PHP (data attributes/JSON) or use a module-specific catalogue; repeated inline locale ternaries are not the target architecture.

## Audit method and exclusions

`scripts/audit-i18n.php` uses `git ls-files`, so generated uploads and local artefacts cannot silently change results. It:

1. enumerates routes from `routes/web.php`;
2. scans views, client assets, UI-producing controllers/support/core code;
3. flags accents and a broader French UI vocabulary;
4. skips documentation, migrations, tests, dependencies, storage, comments and recognised developer logs;
5. compares flattened FR/EN catalogue keys;
6. finds statically used `__()`/`t()` keys absent from English;
7. emits every route and line-level candidate with `--json`.

Intentional exclusions are vendor code, `node_modules`, TCPDF/phpqrcode, migrations/seeds, developer documentation, tests/fixtures, storage/uploads, Arma mod sources and user/database content. Email templates remain in the scan because recipients see them. Repository comments are excluded where reliably recognisable; mixed code/comment lines can remain candidates and must be classified manually.

## Coverage map

“FR remaining” is the baseline heuristic line count, not a string count. Access is derived from route/controller organisation and must be exercised with the listed roles. “Partial” means at least one central or localised surface exists but candidates remain; “Absent” means no systematic English treatment was found. The JSON report is the exhaustive route/file index behind this grouped map.

| Domain / representative URL | Main files and includes | Associated client code | Access | FR remaining | EN state | Current mechanism | Priority |
|---|---|---|---|---:|---|---|---|
| Public home/site `/`, `/site/*` | `views/home`, `views/site`, marketing layout | `public/assets/js` | Public | 32 | PARTIAL | `home`, `site`, `nav` catalogues + hardcoded | High |
| Authentication `/login`, `/register`, password flows | `views/auth`, public auth frame | page scripts | Public | 29 | PARTIAL | `auth` catalogue + hardcoded | High |
| Legal/cookies `/legal/*` | `views/legal`, legal layout/crosslinks | consent scripts | Public | 312 | PARTIAL | `legal`, `cookies` catalogues | Medium |
| Errors / access denial | `views/errors`, `ExceptionHandler` | — | Public/all roles | 20 | PARTIAL | `errors` catalogue + hardcoded | High |
| Dashboard/hub `/dashboard`, `/hub` | dashboard views + dashboard partials | dashboard assets | Authenticated | 1,206 | PARTIAL | navigation catalogue + hardcoded | High |
| Account/profile `/account/*` | `views/account`, account shell | account scripts | Authenticated | 307 | PARTIAL | shared catalogue + hardcoded | High |
| Community/public showcase `/communities/*` | `views/community`, vitrine partials | community assets | Public/authenticated | 441 | PARTIAL | site/nav + hardcoded | High |
| Forum/messages/notifications `/forum/*`, `/messages/*` | forum layout/views, report modal | forum scripts/API messages | Authenticated/moderator | 305 | PARTIAL | nav + hardcoded/API copy | High |
| Personnel/RH `/personnel/*`, `/rh/*` | personnel views/file partials | personnel scripts | Member/HR/manager | 583 | ABSENT | mostly hardcoded/helper labels | High |
| Recruitment/enlistment `/enlistment/*` | enlistment views/partials | recruitment scripts | Public/candidate/HR | 289 | PARTIAL | auth/nav + hardcoded | High |
| Training/LMS `/training/*`, `/formation/*` | training views + four LMS layouts | LMS/player/studio scripts | Learner/trainer/admin | 470 | ABSENT | French label helpers + hardcoded | High |
| ATAK/C2 `/atak/*` | 122 ATAK view files, tactical layouts | ATAK/maps/SSE scripts | Operator/admin | 3,264 | ABSENT | nav plus hardcoded PHP/JS/API copy | Critical |
| SSE/intelligence (under `/atak/sse/*`) | SSE portal/workspace/lab views and support catalogs | maps, polling and analytical UI | Intel/operator | included above | ABSENT | hardcoded label catalogues | Critical |
| Operations/SITAC/briefing/BDA `/operations/*` | operations views and board partials | operational board/map scripts | Operator/commander | 363 | ABSENT | hardcoded | Critical |
| JNET/transmission `/jnet/*`, `/transmission/*` | JNET and transmission views | messaging scripts | Authenticated/operator | 216 | ABSENT | hardcoded/API copy | High |
| Equipment/modpacks/Overwatch | equipment/modpack/overwatch views | module scripts | Authenticated/admin | 269 | ABSENT | hardcoded | Medium |
| Documents/courrier/reports | document/courrier views and paper partials | editor/PDF scripts | Authenticated/editor | 277 | ABSENT | hardcoded/helper labels | High |
| Community back office `/admin/*` | 286 admin views, BO layout/sidebar/topbar | admin scripts | Tenant administrator | 6,925 | ABSENT | nav plus hardcoded | Critical |
| Platform/system administration | platform views + platform sidebar/controllers | platform scripts | Site administrator | included in admin/backend | ABSENT | nav plus hardcoded | Critical |
| Transactional emails | `views/emails` (67 files) | — | Recipient | 408 | ABSENT | template hardcoded/content data | High |
| Shared layouts/partials | 8 layouts, 90 flagged partials | global assets | All | 1,177 | PARTIAL | central catalogues + hardcoded | Critical |
| UI text emitted by backend | UI-producing controllers/support/core | consumed by fetch/SSE/pages | Role-dependent | 7,231 | ABSENT | hardcoded/API `message` | Critical |
| Client-generated UI | `public` JS/mJS/HTML | alerts, modals, maps, tables, polling | Role-dependent | 1,981 | ABSENT | hardcoded JS | Critical |

## Ordered execution plan

### Lot 1 — Guardrails and shared primitives (started)

* Keep the existing locale resolver, translator and French fallback.
* Add the deterministic scanner, catalogue parity checks and route inventory.
* Establish glossary and migration ledger.
* Localise shared confirmation, loader, stepper, next-step, soft-lock and no-organisation primitives.

### Lot 2 — Global shell and rare global states

* Finish every layout, header, sidebar, mobile drawer, footer, breadcrumb, flash/toast, command palette and help modal.
* Complete 401/403/404/500, maintenance, disabled/no-organisation/session-expired and first-login states.
* Pass translated strings into global JS once, without inline `locale() === 'en'` duplication.

### Lot 3 — Public and identity journeys

* Public marketing/community pages, authentication, Steam/Arma linking, email verification, invitations and onboarding.
* Legal/cookie surfaces and all transactional email subjects/bodies.

### Lot 4 — Member workspace

* Dashboard, account/profile/settings, forum/messages/notifications/tasks and documents.
* Translate presentation labels only; preserve posts, profiles, reports, filenames and stored status codes.

### Lot 5 — Personnel, recruitment and training

* Converge the existing `*_labels_fr()` helpers into locale-aware presentation helpers.
* Cover candidate, HR, manager, learner, instructor and studio/admin paths, including dynamic validation and empty states.

### Lot 6 — Operations, ATAK/C2 and SSE

* Treat the operational workspace, SITAC/maps, briefing, BDA/BII, ATAK, SSE/intelligence, JNET and transmissions as one terminology-governed lot.
* Inventory JS templates, Leaflet controls, polling/WebSocket/SSE messages and API display messages. Maintain API response compatibility while adding stable message codes where safe.

### Lot 7 — Administration and platform operations

* Community back office, moderation, advanced settings, destructive confirmation paths, audit/history and platform-only tools.
* Exercise tenant, role and permission boundaries without altering them.

### Lot 8 — Reverse scan and certification

* Run syntax/static/unit tests, catalogue strict mode and the global reverse scan.
* Classify every remaining candidate as UI, business/user content, comment, documentation, developer log, technical value or justified exception.
* Render representative FR/EN routes for every access class and verify dynamic interactions.
* Certification is allowed only when every inventory row is COMPLETE and all remaining candidates are explicitly listed below.

## First-lot changes

The common catalogue gained 12 matched FR/EN keys. These remove hardcoded copy from six high-reuse UI primitives: confirmation dialog, halo loader, stepper, suggested-next-step block, premium soft lock and no-organisation empty state. Existing caller-provided labels remain untouched because they may be business or user content.

## Final matrix (current checkpoint)

| Metric | FR | EN | Tested | Remark |
|---|---:|---:|---|---|
| Registered route declarations | 1,980 | 1,980 locale resolution available | Static inventory | English copy is not complete on all routes. |
| Catalogue groups | 8 | 8 | Parity scanner | No FR key currently lacks EN. |
| Common catalogue keys | 48 | 48 | Unit/parity tests | Includes 12 shared-component additions. |
| Fully certified interface groups | — | **0** | No | Certification intentionally withheld. |
| Partially translated groups | — | 8 catalogued groups plus shared primitives | Static checks | Runtime role matrix remains outstanding. |
| Candidate hardcoded UI lines | source | 27,176 | Reverse scanner | Requires line-by-line classification/migration. |

## Precise remaining debt

The complete debt is too large to duplicate safely in Markdown: `php scripts/audit-i18n.php --json` outputs each **file, line, candidate text, domain and route**, and is the authoritative line-level list. The largest measured queues are:

* `views/admin/**`, administrator routes — 6,925 candidate lines; role-gated runtime verification required.
* `app/Controllers/**`, `app/Support/**`, `app/Core/**`, mixed routes — 7,231 candidate lines; separate UI/API messages from internal logs and business data.
* `views/atak/**` and root ATAK views, `/atak/*` — 3,264 candidate lines; map/SSE/operational terminology and dynamic JS must be reviewed together.
* `public/**`, all client-generated interfaces — 1,981 candidate lines; classify templates, errors, tooltips and map controls.
* `views/partials/**`, shared across routes — 1,157 candidate lines; highest leverage after the six completed primitives.
* `views/emails/**`, recipient-visible email — 408 candidate lines; locale must follow recipient rather than the request actor.

No candidate is waived merely because the French fallback prevents a crash. The intentional exclusions listed above are not exposed UI source. Database-authored/user-authored content may remain French by design and must not be machine-translated or mutated.

## Completion gate

Target: **inventoried X / English-complete X / partial 0 / absent 0 / to verify 0**. Current checkpoint: **1,980 route declarations inventoried / 0 interface groups certified complete / partial or absent debt remains**. A later migration must replace this checkpoint with the required all-green counts only after the reverse scan and role-based FR/EN rendering pass.
