# Contrat API SSE (Sensitive Site Exploitation)

Annexe technique au module décrit dans [terminal-sse-renseignement.md](terminal-sse-renseignement.md).

**Version couverte : 1.4.0** (fiche personne + photo visage). Sites / watchlist / custody = versions ultérieures (tables déjà prévues).

Auth : même modèle que les autres endpoints ATAK Arma (`ComspecApiKeyAuth` + `AtakArmaWriteGuard`). Lecture TOC : contexte tenant session / clé comme `/api/atak/reports`.

---

## Tables

### `sse_persons`

| Colonne | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| tenant_id | INT UNSIGNED | FK tenants |
| context_id | INT UNSIGNED | map / contexte ATAK (défaut 1) |
| status | VARCHAR(32) | `civil`, `combattant`, `detenu`, `prioritaire` |
| last_name, first_name, alias | VARCHAR | identité scénario |
| sex_apparent | VARCHAR(16) | nullable |
| age_estimated | SMALLINT | nullable |
| birth_date, birth_place | VARCHAR | nullable |
| nationality, language_spoken | VARCHAR | nullable |
| id_document_present | TINYINT(1) | |
| id_document_type, id_document_number | VARCHAR | nullable |
| distinguishing_marks | TEXT | |
| affiliation | VARCHAR | |
| circumstances | VARCHAR(64) | contrôle, perquisition, reddition, autre |
| statements | TEXT | déclarations |
| confidence_level | VARCHAR(32) | faible, moyenne, haute |
| weapons_json | JSON | liste armement |
| equipment_json | JSON | équipement notable |
| biometrics_simulated | TINYINT(1) | |
| consent_recorded | TINYINT(1) | |
| capture_pos_x/y/z | DOUBLE | |
| grid_reference | VARCHAR | |
| location_description | VARCHAR | |
| submitter_* | callsign, steam, user_id | opérateur |
| target_unit_netid | VARCHAR | unité Arma ciblée (option) |
| primary_photo_id | INT UNSIGNED NULL | FK soft vers sse_person_photos |
| created_at, updated_at | DATETIME | |

### `sse_person_photos`

| Colonne | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| person_id | INT UNSIGNED | FK sse_persons ON DELETE CASCADE |
| tenant_id | INT UNSIGNED | |
| image_path | VARCHAR | relatif `uploads/sse/…` |
| angle | VARCHAR(16) | `face`, `profil`, `trois_quarts` |
| caption | VARCHAR | |
| author_callsign | VARCHAR | |
| pos_x, pos_y, pos_z | DOUBLE | |
| created_at | DATETIME | |

### Prévues (schéma créé, API minimale 1.4.0)

- `sse_sites`, `sse_site_rooms`, `sse_seizures`, `sse_custody_events`, `sse_watchlist_entries`

---

## Endpoints 1.4.0

### `POST /api/sse/persons`

JSON body (clés techniques ; UI Arma = libellés métier) :

```json
{
  "mapId": 1,
  "status": "civil",
  "last_name": "Doe",
  "first_name": "John",
  "alias": "JD",
  "sex_apparent": "homme",
  "age_estimated": 32,
  "nationality": "",
  "language_spoken": "",
  "id_document_present": false,
  "id_document_type": "",
  "id_document_number": "",
  "distinguishing_marks": "",
  "affiliation": "",
  "circumstances": "controle",
  "statements": "",
  "confidence_level": "moyenne",
  "weapons": [{"name": "AK-74", "type": "rifle"}],
  "equipment": [{"name": "Radio", "type": "radio"}],
  "biometrics_simulated": false,
  "consent_recorded": true,
  "pos_x": 0,
  "pos_y": 0,
  "pos_z": 0,
  "grid_reference": "",
  "location_description": "",
  "submitter_callsign": "ALPHA-1",
  "submitter_steam_id": "",
  "target_unit_netid": ""
}
```

Réponse `201` : fiche enrichie (`status_label`, `weapons`, `equipment`, `photos`).

### `POST /api/sse/persons/{id}/photos`

Multipart : champ `image` (ou `photo`), plus `angle`, `caption`, `author`, `pos_x`, `pos_y`, `pos_z`.

Réponse `201` : métadonnées photo ; met à jour `primary_photo_id` si première photo.

### `POST /api/sse/persons/{id}/biometrics-sim`

JSON optionnel `{ "kind": "empreintes" | "iris" }`. Marque `biometrics_simulated = 1` + événement custody léger.

### `GET /api/sse/persons`

Query : `mapId`, `status`, `limit`, `offset`, `since_id`.

Réponse : `{ "persons": [...], "count": N }` avec libellés métier.

### `GET /api/sse/persons/{id}`

Fiche complète + photos.

---

## Extension Arma (`COMSPECExtension`)

| Commande | Cible |
|---|---|
| `SubmitSsePerson` | `POST /api/sse/persons` (JSON sync) |
| `UploadSsePhoto` | multipart `POST /api/sse/persons/{id}/photos` |
| `SubmitSseBiometricsSim` | `POST /api/sse/persons/{id}/biometrics-sim` |

Hors couverture : file SQF locale puis flush (même principe que rapports / photos recon).

---

## Libellés statut (jamais exposés bruts en UI)

| Code | Libellé |
|---|---|
| civil | Civil |
| combattant | Combattant |
| detenu | Détenu |
| prioritaire | Personne prioritaire |

---

## Configuration tenants

Update catalogue : `SSE_PERSONS_V1` — applicable si ATAK applicable ; satisfait si module pont `sse_person` activé **ou** au moins une fiche SSE (probe données). Nouveau tenant : `markSatisfiedForNewTenant` (module activé par défaut → COMPLETED / NOT_APPLICABLE selon type).

Update catalogue : `SSE_PORTAL_V1` — portail classifié `/atak/sse` (dossiers, codes, croisements, PDF). Satisfait si un dossier ou un code d’accès existe ; nouveaux tenants marqués via `markCompleted` au bootstrap. Écran de configuration : délivrance des codes (`/atak/sse/acces`).

---

## Portail web classifié (`/atak/sse`)

- Sas public + redeem code (membre habilité ou invité)
- Tables : `sse_cases`, `sse_case_persons`, `sse_case_notes`, `sse_case_evidence`, `sse_access_codes`, `sse_access_grants_log`
- Permissions : `atak.sse.access`, `atak.sse.grant`, `atak.sse.case.manage`, `atak.sse.export`
- Invité : session limitée au portail (pas de carte Tacmap)
