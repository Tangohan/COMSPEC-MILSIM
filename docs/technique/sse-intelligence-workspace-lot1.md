# SSE Intelligence Workspace — LOT 1 (fondations)

Date : 2026-08-16

## Objectif

Poser le socle commun **COLLECTE → EXPLOITATION** sans casser le portail SSE existant ni l’API Arma `/api/sse/persons|sites`.

## Cartographie

```text
Arma (@COMSPEC_SSE)
  ACE / BII / core  --CBA bus-->  raiseSseEvent
  network / Overwatch DLL
        |
        v
Athena API  /api/sse/persons|sites  (+ hooks LOT1 → index/events)
Athena API  /api/sse/v1/*           (workspace, entities, events, relations)
        |
        v
Web  /atak/sse/workspace   (Intelligence Workspace — coque)
     /atak/sse/operations  (Control Tower — inchangé)
```

## Tables ajoutées / enrichies

| Table | Rôle |
|-------|------|
| `sse_entity_index` | Registre unifié (projection persons/sites/cases/DI) |
| `sse_intel_events` | Timeline normalisée + `idempotency_key` |
| `sse_audit_log` | Journal append-only |
| `sse_cases` ALTER | `lifecycle_status`, priorité, analyste, activité, compartiment |
| `sse_relations` ALTER | `uuid`, `status` (proposed/confirmed/…), justification, fiabilité |

Migration : `bootstrap/atak_sse_intel_foundation_migration.php` (branchée dans `run-migrations.php`).

## Contrat d’événement

Champs clés : `event_uuid`, `idempotency_key`, `source_system`, `raw_source_id`, `event_type`, `entity_uuid`, `identity_tier` (`DECLARED` / `DOCUMENTARY` / `CONFIRMED` / `UNKNOWN`), `source_reliability` (A–F), `info_credibility` (1–6), `summary`, `payload`.

Sources prévues : `ARMA_SSE`, `ACE`, `ACE_DOGTAG`, `BII_IDENTIFI`, `ZEUS`, `EDEN`, `MANUAL`, `CTAB`, `TFAR`, `ACRE`, `UAV`.

Règle : un scan / une corrélation **ne passe jamais seul** une identité en `CONFIRMED`.

## Mapping lifecycle dossiers

| Legacy `status` | `lifecycle_status` |
|-----------------|--------------------|
| ouvert | COLLECTE |
| en_cours | EN_ANALYSE |
| clos / fermé | CLOS |
| archive | ARCHIVE |
| (autres / vide) | BROUILLON (conservé si déjà mappé) |

Le champ `status` legacy est **conservé**.

## API v1

- `GET /api/sse/v1/workspace/summary`
- `GET /api/sse/v1/entities` (`?q=` `&type=`)
- `GET /api/sse/v1/entities/{uuid}`
- `GET /api/sse/v1/events`
- `GET /api/sse/v1/relations`
- `POST /api/sse/v1/relations`

Toute requête est scopée `tenant_id` (session, clé API, ou contexte Athena).

## Bus CBA (Arma)

- `comspec_sse_fnc_raiseSseEvent` — profondeur max 8, envelope normalisée
- `comspec_sse_fnc_onSseEvent` — listeners une seule fois au postInit
- Émissions POC : photo ACE (`COMSPEC_SSE_PHOTO_TAKEN`), scan BII (`COMSPEC_SSE_BIOMETRIC_CAPTURED`)

## Fichiers principaux

**PHP** : repos `SseEntityIndex*`, `SseIntelEvent*`, `SseAuditLog*` ; services `SseIntelFoundationService`, `SseIntelligenceWorkspaceService` ; controllers Web/API dédiés.

**UI** : `views/atak/sse/intelligence_workspace.php`, CSS/JS workspace, lien nav « Intelligence Workspace ».

**SQF** : `fn_raiseSseEvent.sqf`, `fn_onSseEvent.sqf`, hooks `fn_doPhotograph.sqf`, `fn_biiRecordToSse.sqf`.

## Smoke checklist

1. Exécuter `run-migrations.php` → section `atak_sse_intel_foundation` OK  
2. Ouvrir `/atak/sse/workspace` avec session SSE → inbox / timeline / dossiers  
3. `GET /api/sse/v1/workspace/summary` avec tenant → JSON counts  
4. POST personne `/api/sse/persons` → ligne `sse_intel_events` + `sse_entity_index`  
5. `/atak/sse/operations` toujours accessible  
6. Rebuild PBO `core`, `interaction`, `compat_bii` pour valider le bus in-game  

## Suite (hors LOT 1)

- **LOT 2** : Inbox actionnable, chemise dossier, graph profondeur, recherche universelle, palette complète  
- **LOT 3** : menu ACE unifié, médical, dogtag, véhicule %, EOD, normalisation BII dossier multi-éléments  
- **LOT 5** : calques ATAK  
- **LOT 8** : Zeus Scenario Director, Eden Dataset/Seed, dataset FALCON ✅ `docs/technique/sse-intelligence-workspace-lot8.md`  
- Compat futurs : `compat_tfar`, `compat_acre`, `compat_ctab`, `compat_uav` (bridges only)  
  → vision ACRE détaillée : [`acre-comms-atak-sse-sigint.md`](acre-comms-atak-sse-sigint.md) (ATAK fiche/layer/network, SSE exploit physique, SIGINT DF)
