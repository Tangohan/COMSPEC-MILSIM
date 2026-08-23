# Fiches de renseignement — dictionnaire de données

Schéma installé par `bootstrap/atak_sse_field_notes_migration.php`, appelé par
`run-migrations.php` et, en secours, par le repository lui-même
(`SilentSchemaMigration`) : une communauté qui n'a pas encore rejoué le pipeline
obtient les tables au premier accès.

## `sse_field_notes`

| Colonne | Type | Note |
|---|---|---|
| `id` | `INT UNSIGNED` | Clé primaire |
| `tenant_id` | `INT UNSIGNED` | Communauté. Toute lecture est filtrée dessus. |
| `context_id` | `INT UNSIGNED` | Carte / théâtre (`mapId`), 1 par défaut |
| `reference_code` | `VARCHAR(32)` | `FR-<année>-<séquence>`, unique par communauté |
| `note_kind` | `VARCHAR(16)` | `FRM`, `FRO`, `FRC`, `FRA`, `FRT` |
| `themes` | `VARCHAR(400)` | Liste JSON de codes de thème, 4 au maximum |
| `body` | `MEDIUMTEXT` | Le renseignement, 1000 caractères utiles |
| `observed_at` | `DATETIME` | Date du constat, pas de la saisie |
| `place_label` | `VARCHAR(180)` | Lieu en clair |
| `grid_reference` | `VARCHAR(32)` | Carroyage |
| `pos_x`, `pos_y`, `pos_z` | `DECIMAL(12,2)` | Position jeu |
| `lat`, `lng` | `DECIMAL(10,7)` | Coordonnées géographiques |
| `urgency` | `VARCHAR(16)` | `routine`, `priorite`, `immediate` |
| `classification` | `VARCHAR(24)` | Reprend la diffusion active du portail |
| `source_reliability` | `CHAR(1)` | `A` à `F`, `C` par défaut |
| `info_credibility` | `TINYINT UNSIGNED` | 1 à 6, 3 par défaut |
| `status` | `VARCHAR(24)` | `brouillon`, `transmise`, `prise_en_compte`, `exploitee`, `sans_suite` |
| `origin` | `VARCHAR(16)` | `web`, `atak`, `arma` |
| `author_label` | `VARCHAR(120)` | Indicatif ou nom affiché |
| `author_user_id` | `INT UNSIGNED` | Compte Athena, si connu |
| `author_steam_id` | `VARCHAR(32)` | Identité vérifiée par le garde terrain |
| `author_unit` | `VARCHAR(120)` | Élément d'appartenance |
| `case_id` | `INT UNSIGNED` | Dossier validé de rattachement |
| `interest_case_id` | `INT UNSIGNED` | Dossier d'intérêt de rattachement |
| `triage_note` | `VARCHAR(400)` | Commentaire de suivi de l'analyste |
| `triaged_by` | `INT UNSIGNED` | Auteur du suivi |
| `triaged_at` | `DATETIME` | Date du suivi |
| `idempotency_key` | `VARCHAR(80)` | Unique par communauté — anti-doublon terrain |
| `created_at`, `updated_at` | `DATETIME` | Horodatage technique |

Index : unicité sur `(tenant_id, reference_code)` et `(tenant_id,
idempotency_key)` ; parcours sur `(tenant_id, status, observed_at)`,
`(tenant_id, note_kind, observed_at)` et `(tenant_id, case_id)`.

La contrainte sur `tenants` est en `ON DELETE CASCADE` : la suppression d'une
communauté emporte ses fiches.

## `sse_field_note_attachments`

| Colonne | Type | Note |
|---|---|---|
| `id` | `INT UNSIGNED` | Clé primaire |
| `tenant_id` | `INT UNSIGNED` | Communauté |
| `note_id` | `INT UNSIGNED` | Fiche porteuse, `ON DELETE CASCADE` |
| `file_path` | `VARCHAR(255)` | Chemin relatif sous `uploads/sse/fiches/` |
| `original_name` | `VARCHAR(180)` | Nom d'origine, tel que fourni |
| `mime_type` | `VARCHAR(80)` | Type détecté côté serveur, jamais celui annoncé |
| `byte_size` | `INT UNSIGNED` | Taille après compression éventuelle |
| `kind` | `VARCHAR(16)` | `photo`, `capture`, `document`, `croquis` |
| `caption` | `VARCHAR(255)` | Légende |
| `grid_reference` | `VARCHAR(32)` | Repère au moment de la prise |
| `pos_x`, `pos_y`, `pos_z` | `DECIMAL(12,2)` | Position au moment de la prise |
| `author_label` | `VARCHAR(120)` | Auteur de la pièce |
| `created_at` | `DATETIME` | Horodatage technique |

## Retombées dans les autres tables

Une fiche transmise écrit aussi :

- un événement dans **`sse_intel_events`** (`event_type = REPORT_RECEIVED`,
  `source_system` = `CTAB` depuis l'ATAK, `ARMA_SSE` depuis le jeu, `MANUAL`
  depuis le portail). La charge utile transporte `field_note_id`,
  `reference_code`, les thèmes en clair et le texte ;
- une ligne dans le **journal d'activité ATAK** sous le type `SSE_FIELD_NOTE`.

Ces deux écritures sont enveloppées : une indexation qui échoue ne doit jamais
faire perdre la fiche que le terrain vient de transmettre.
