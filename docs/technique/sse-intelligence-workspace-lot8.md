# SSE Intelligence Workspace — LOT 8 (Mission Making)

Date : 2026-08-16

## Objectif

Outiller le mission maker :

| Pilier | Contenu |
|--------|---------|
| **Eden** | Dataset + rôle + graine sur les unités |
| **Zeus** | Scenario Director (dataset / niveau 0–3) |
| **Générateur** | API datasets + application par rôles |
| **Dataset FALCON** | Cellule Irak 2012 d’entraînement |

## Dataset FALCON

- **ID** : `falcon`
- **Graine** : `FALCON-IQ-2012-A`
- **Rôles** : HVT (ABU KARIM), IED, courrier, finance, planque, bruit civil
- **Niveaux** : 0 Surface → 1 Tactique → 2 Terrain → 3 Vérité complète

### Pose rapide (SQF)

```sqf
["falcon", player, 50, 1] call comspec_sse_fnc_applyDataset;
```

Ou pack scénario :

```sqf
["FALCON", player, 50] call comspec_sse_fnc_loadScenarioPack;
```

## Eden

Attributs COMSPEC SSE (nouveaux) :

- **Dataset mission** (`falcon`)
- **Rôle dans le dataset** (`falcon_hvt`, `falcon_courier`, …)
- **Graine mission** (affichage / traçabilité)

Si dataset + rôle sont renseignés en génération AUTO → `applyDatasetRole`.

## Zeus

Nouveau module **Scenario Director (dataset / niveau)** :

| Argument | Effet |
|----------|--------|
| Dataset | `falcon` (défaut) |
| Niveau 0–3 | Rôles éligibles selon `minLevel` |
| Rayon | Unités dans le rayon |
| Action | `APPLY` · `LEVEL_ONLY` · `LIST` |

Module existant **Générer depuis brief / scénario** : pack `FALCON` reconnu.

## Générateur (API)

| Fonction | Rôle |
|----------|------|
| `datasetFalcon` | Définition du pack |
| `registerDatasets` / `listDatasets` / `loadDataset` | Catalogue |
| `applyDatasetRole` / `applyDataset` | Pose sur entités |
| `setScenarioLevel` / `getScenarioLevel` | Pilotage Zeus |

## Athena

- Catalogue `App\Support\SseMissionKitCatalog`
- Hub atelier (`/atak/sse/dev`) — section **Kits mission**

## Vérification

1. Rebuild PBO : `generator`, `intel`, `zeus`, `eden`, `ui` (Arma fermé).
2. Boot RPT : `registerDatasets: 1 pack(s)`.
3. Zeus → Scenario Director → dataset `falcon`, niveau 1, rayon 50 → unités enrichies.
4. Eden : unité avec dataset `falcon` + rôle `falcon_hvt` → alias ABU KARIM.
5. Hub Athena : carte kit FALCON visible.

## Hors LOT 8 (reporté)

Éditeur graphique de datasets, triggers de révélation automatiques, multi-datasets mission synchronisés Athena↔Arma.
