# SSE Intelligence Workspace — LOT 3 (Terrain)

Date : 2026-08-16

## Objectif

Renforcer la chaîne **terrain → registre → Intelligence Workspace** pour :

- personne (SEEK, sujet, palier d’identité) ;
- qualité d’acquisition biométrique ;
- site (zones + progression pondérée) ;
- matériel (chaîne de possession) ;
- photos terrain (métadonnées) ;
- exploitation numérique (constats → timeline intel).

Sans casser le portail legacy ni l’API `/api/sse/persons|sites`.

## Schéma

Migration : `bootstrap/atak_sse_terrain_lot3_migration.php` (branchée dans `run-migrations.php`).

| Élément | Enrichissement |
|---------|----------------|
| `sse_persons` | `subject_id`, `seek_stage`, `identity_tier`, `acquisition_quality_avg` |
| `sse_biometric_samples` | `laterality`, `quality_label`, `conditions_json` |
| `sse_person_photos` | `photo_type`, `quality`, `heading`, `case_id`, `target_ref`, `metadata_json` |
| `sse_site_rooms` | `zone_type`, `exploitation_pct` |
| `sse_sites` | `exploitation_pct` |
| `sse_seizures` | `custody_state`, packaging / scellé / acteur |
| `sse_custody_events` | `seizure_id` |
| `sse_field_photos` | photos site / objet / véhicule |

## Service

`App\Services\Sse\SseTerrainService` :

- étapes SEEK (`capture` → `query` → `match` → `sign` → `done`) ;
- libellés qualité (Insuffisante / Partielle / Bonne / Excellente) ;
- dérivation `identity_tier` (jamais `CONFIRMED` par ingest terrain seul) ;
- progression site pondérée (CACHE / COLLECTION_POINT / VEHICLE…) ;
- avance custody matériel + événement intel ;
- enregistrement photo terrain + événement `PHOTOGRAPHED` ;
- pont lab numérique → `DIGITAL_FINDING`.

## API

- Ingest personne existant enrichi (`terrain` dans la réponse).
- `POST /api/sse/v1/terrain/photos`
- `POST /api/sse/v1/terrain/seizures/{id}/custody`
- `POST /api/sse/v1/terrain/persons/{id}/seek`

## UI portail

- Fiche identité : sujet, étape SEEK, qualité, détail biométrie.
- Fiche site : % exploitation, type de zone, possession des saisies (action « Passer à… »).

## Arma

- Photos ACE : type, heading, mission, room/site, qualité dégradée par état.
- `calcQuality` : facteur de dégradation optionnel.
- Véhicule : section `exploitation` + `markVehicleSection` (fouille / digital).
- Label custody : pièce réelle si disponible.
- BII : un événement par élément de preuve + scan biométrique enrichi.

## Vérification

1. Lancer les migrations (LOT 3 terrain).
2. Transmettre une fiche personne depuis le terminal → champs `subject_id` / `seek_stage` / `identity_tier`.
3. Ouvrir une identité web → badges SEEK / qualité.
4. Sur un site, marquer des pièces puis faire avancer une saisie dans la chaîne de possession.
5. Générer des constats lab numérique → événements `DIGITAL_FINDING` dans la timeline workspace.
6. Rebuild PBO core / interaction / digital / intel / compat_bii / generator si test in-game.

## Hors LOT 3 (reporté)

Menu ACE 100 % unifié, médical / EOD dédiés, table photo Athena complète côté DLL.
