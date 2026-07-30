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

---

## Ajouts 1.4.12 — terminal SEEK

### Champs supplémentaires de `POST /api/sse/persons`

```json
{
  "case_code": "SSE-2026-0004",
  "signature": {
    "callsign": "N-10",
    "terminal_uid": "OW-2609-279840",
    "atak_id": "",
    "signed_at": "2026-07-29 19:41:02"
  },
  "medical_context": {
    "etat": "unconscious",
    "etat_label": "Inconsciente",
    "sang": 72,
    "pouls": 118,
    "douleur": true,
    "arret_cardiaque": false,
    "lesions": ["Torse", "Bras gauche"],
    "resume": "Inconsciente · pouls 118/min · volémie ≈ 72%"
  },
  "biometric_samples": [
    { "kind": "empreintes", "quality": 84, "lab_reference": "LAB-2026-EMP4821" },
    { "kind": "iris", "quality": 67, "lab_reference": "LAB-2026-IRI1093" },
    { "kind": "adn", "quality": 93, "lab_reference": "LAB-2026-ADN7734" }
  ]
}
```

Tous facultatifs. La réponse `201` porte en plus :

- `filing` : `{ "code": "…", "linked": true|false, "case": { id, reference_code, title } | null }`
  — un code inconnu n’est **pas** une erreur : la fiche est créée, simplement non classée.
- `biometric_samples` : échantillons tels qu’enregistrés.
- `medical_context`, `signature` : relus depuis la fiche.

### `GET /api/sse/persons/by-unit`

Query : `netid` (ou `net_id`), `mapId`.
Réponse `200` : `{ "person": {…} | null }`. L’absence de fiche n’est pas une erreur.
À déclarer **avant** `/api/sse/persons/{id}` dans le routeur.

### `POST /api/sse/persons/{id}/biometrics-sim`

`kind` accepte désormais `adn` en plus de `empreintes` et `iris`.

### Tables

| Table / colonne | Notes |
|---|---|
| `sse_persons.medical_context_json` | JSON — constat ACE Medical au moment du relevé |
| `sse_persons.signed_by_callsign` / `signed_terminal_uid` / `signed_atak_id` / `signed_at` | Procès-verbal ATAK |
| `sse_biometric_samples` | `person_id`, `tenant_id`, `kind`, `quality`, `lab_reference`, `operator_callsign` — unique par (personne, modalité) |
| index `idx_sse_persons_unit` | `(tenant_id, context_id, target_unit_netid)` |

### Extension Arma

**Aucune commande nouvelle.** `SubmitSsePerson` transmet le corps JSON tel quel :
les champs ci-dessus n’imposent pas de recompilation de `COMSPECExtension`.
`LookupSsePersonByUnit` reste à ajouter pour exploiter `by-unit` en jeu.

### Objet in-game

`COMSPEC_Item_SeekTerminal` — « Terminal biométrique SEEK », objet d’inventaire
(sac / gilet / uniforme). Requis pour ouvrir une fiche ; réglage CBA
`comspec_sse_require_item` pour rétablir l’accès sans objet.

---

## Exploitation de site (1.4.13)

Les tables `sse_sites`, `sse_site_rooms` et `sse_seizures` existaient depuis la 1.4.0 sans
être exploitées par aucun code. Elles sont désormais servies.

### `POST /api/sse/sites`

```json
{
  "mapId": 1,
  "name": "Habitation nord — rue basse",
  "site_type": "habitation",
  "team_label": "ALPHA",
  "pos_x": 0, "pos_y": 0, "pos_z": 0,
  "grid_reference": "045 128",
  "summary": "",
  "rooms": ["Entrée", "Séjour", "Cave"],
  "case_code": "SSE-2026-0004",
  "submitter_callsign": "ALPHA-1"
}
```

`rooms` est facultatif : sans lui, la checklist est prégarnie selon `site_type`.
`case_code` rattache le site au dossier — c'est la référence métier, jamais un identifiant
technique. Un code inconnu n'est pas une erreur : le site est créé sans rattachement.
Réponse `201` : site complet, avec `reference_code` (`SITE-2026-0001`), `rooms` et `seizures`.

Types : `habitation`, `depot`, `poste_ennemi`, `cache`, `vehicule`, `autre`.
Statuts : `ouvert`, `en_cours`, `cloture`.

### `GET /api/sse/sites` · `GET /api/sse/sites/{id}`

Query de l'index : `mapId`, `status`, `site_type`, `limit`.
La fiche détaillée porte en plus `five_line_report` (compte rendu généré).

### `POST /api/sse/sites/{id}/rooms/{roomId}`

`{ "checked": true, "notes": "" }` — marque une pièce fouillée. Réponse : site rafraîchi.

### `POST /api/sse/sites/{id}/seizures`

Un objet, ou un lot via `seizures: [...]` :

```json
{
  "seizures": [
    { "category": "arme", "label": "AK-74", "quantity": 1, "room_id": 12 },
    { "category": "document", "label": "Carnet manuscrit", "quantity": 1 }
  ],
  "submitter_callsign": "ALPHA-1"
}
```

Natures : `arme`, `munition`, `document`, `radio`, `medical`, `numerique`, `valeur`, `autre`.
`person_id` rattache la saisie à une fiche personne.

### `POST /api/sse/sites/{id}/close`

`{ "summary": "" }` — vide, le compte rendu cinq lignes généré est retenu.

### Tables

| Élément | Notes |
|---|---|
| `sse_sites.reference_code` | Ajoutée en 1.4.13, index `(tenant_id, reference_code)` |
| `sse_site_rooms` | Checklist ordonnée, `checked` + notes ; index `(tenant_id, site_id)` |
| `sse_seizures` | Nature, désignation, quantité, pièce et fiche personne |
| `sse_custody_events` | Alimentée par `site_ouvert`, `saisie`, `site_cloture` |

### Portail

`/atak/sse/sites` (registre, avancement de fouille) et `/atak/sse/sites/{id}` (checklist,
saisies, compte rendu de clôture). Entrée de navigation « Sites exploités ».

### Extension Arma

**Non couvert.** Aucune commande n'existe encore côté `COMSPECExtension` pour ouvrir un
site ou verser une saisie depuis le jeu : cela demande de nouvelles commandes et une
recompilation. L'API est prête et servie.

---

## Requête d'identité (1.4.13)

Champ supplémentaire de `POST /api/sse/persons` :

```json
{
  "identity_query": {
    "result": "confirmed",
    "confidence": 98.7,
    "record_ref": "BIO-42871"
  }
}
```

`result` : `none` | `possible` | `confirmed`. Stocké en `sse_persons.identity_query_json`,
relu sous la clé `identity_query`.

C'est le **verdict rendu par le terminal**, distinct du croisement watchlist calculé par le
serveur : le premier est un jugement de terrain sur relevés simulés, le second un
rapprochement nominatif du poste de commandement. La fiche du portail affiche les deux
séparément — ne pas les fusionner.

Le verdict est déterministe côté jeu : il dérive d'une graine stable par entité
(`COMSPEC_SSE_Seed`), pour qu'une même personne interrogée deux fois donne le même
résultat. Le chef de mission peut l'imposer via `COMSPEC_SSE_MatchResult`,
`COMSPEC_SSE_Confidence` et `COMSPEC_SSE_RecordRef`.

---

## Corrélation et automatismes (1.4.14)

### Table `sse_relations`

Arêtes **posées** — par un analyste depuis le portail, ou par un automatisme.

| Colonne | Rôle |
|---|---|
| `from_type` / `from_id`, `to_type` / `to_id` | Extrémités ; types `person`, `site`, `room`, `seizure` |
| `relation` | `present`, `recovered`, `found_at`, `associe`, `possede`, `contact`, `membre`, `mentionne`, `co_presence`, `meme_individu` |
| `reliability` | `unverified`, `corroborated`, `confirmed`, `conflicting` |
| `author_label` | Discriminant de provenance — la valeur `Automatisme` marque une arête posée par une règle |
| `note` | Sur quoi repose le lien |

Clé unique `(tenant_id, from_type, from_id, to_type, to_id, relation)` : réenregistrer
la même arête met à jour la fiabilité et la note plutôt que de dupliquer.

**Les arêtes déduites ne sont pas stockées.** « Saisie recueillie sur P02 », « objet
trouvé en pièce 03 », « P01 rattaché au site A » existent déjà dans les données ; les
stocker créerait un doublon qui se périme dès qu'une saisie est corrigée. Elles sont
recalculées à chaque lecture par `SseCorrelationService::graphForCase()`.

### Champ `automation` des réponses

`POST /api/sse/persons`, `POST /api/sse/sites/{id}/rooms/{roomId}` et
`POST /api/sse/sites/{id}/seizures` renvoient ce que les règles ont fait :

```json
{
  "automation": [
    {
      "rule": "A1",
      "label": "Classement automatique",
      "detail": "Sujet non identifié classée au dossier SSE-2026-0007 : c'était le seul dossier ouvert."
    }
  ]
}
```

`detail` est un libellé métier en français, directement affichable — le terminal n'a
aucune correspondance code → texte à maintenir de son côté.

Sur `POST /api/sse/persons`, un classement automatique (règle `A1`) est aussi reporté
dans `filing` : `linked = true`, `auto = true`, `message` = le libellé. C'est la seule
action automatique dont le terrain a besoin sur place.

### Règles

| Règle | Déclencheur | Effet |
|---|---|---|
| `A1` | Fiche sans code dossier | Rattachement si **un seul** dossier ouvert |
| `A2` | Relevé biométrique déjà versé sous la même référence de laboratoire | Signalement + relation `meme_individu` (`corroborated`) |
| `A3` | Croisement watchlist ≥ `HARD_MATCH_SCORE` (85) | Dossier `ouvert` → `en_cours`, note déposée |
| `A4` | Fiches du même dossier à moins de `CO_PRESENCE_MINUTES` (45) | Relations `co_presence` (`unverified`), plafond `CO_PRESENCE_MAX_LINKS` (5) |
| `A5` | Toutes les pièces de la checklist cochées | Signalement « prêt pour clôture » |
| `A6` | Saisie de nature `arme`, `munition`, `numerique`, `document` | Remontée immédiate + note de dossier |

Aucune règle ne clôt un site, ne fusionne des fiches, ni ne déclare une identité.
Un échec de règle n'échoue jamais la requête : la fiche transmise est enregistrée
d'abord, les automatismes tournent ensuite.

### Portail

`GET/POST /atak/sse/dossiers/{id}/correlations` et
`POST /atak/sse/dossiers/{id}/correlations/{relationId}/supprimer`.
La page distingue les trois provenances — déduit, automatisme, analyste — et ne les
confond jamais visuellement.

### Configuration mission maker

Variables d'unité lues côté jeu, toutes réglables depuis Eden (attributs
« COMSPEC — Exploitation SSE ») ou Zeus (module « Profil d'identité SSE ») :

| Variable | Type | Effet |
|---|---|---|
| `COMSPEC_SSE_Seed` | Number | Graine fixe ; 0 = dérivée de l'identifiant réseau |
| `COMSPEC_SSE_MatchResult` | String | `none` / `possible` / `confirmed` |
| `COMSPEC_SSE_Confidence` | Number | 0-100 ; absente = calculée sur la qualité des relevés |
| `COMSPEC_SSE_RecordRef` | String | Référence de dossier antérieur affichée |
| `COMSPEC_SSE_LastName`, `_FirstName`, `_Alias` | String | État civil proposé |
| `COMSPEC_SSE_Nationality`, `_Language` | String | Nationalité déclarée, langue parlée |

Variable de mission : `COMSPEC_SSE_ActiveCase` (String, publique) — dossier de
rattachement de l'élément.

Point d'entrée unique côté SQF : `comspec_overwatch_connect_fnc_sseApplyProfile`,
whitelistée en `remoteExec`. Les champs vides ne sont pas écrits, un profil partiel
complète la génération déterministe au lieu de l'écraser.
