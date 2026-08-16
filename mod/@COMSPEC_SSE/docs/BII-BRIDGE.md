# Passerelle BII Identifi ↔ COMSPEC SSE

## Source analysée

PBO Workshop : `BII_Identifi.pbo`  
(`@# S.O.A.R - FN\Addons`) — appareil **BII-10 Identifi** (biométrie + SSE terrain + sync GSQN).

## Inventaire données BII

### Item

| Classe | Rôle |
|--------|------|
| `BII_Identifi_Device` | Appareil BII-10 (hérite `ItemAndroid`) |

### Variables entité (modules / seed)

| Variable | Usage |
|----------|--------|
| `BII_Identifi_name` / `bioName` | Identité |
| `BII_Identifi_alias` | Alias |
| `BII_Identifi_nationality` | Nationalité |
| `BII_Identifi_org` | Organisation |
| `BII_Identifi_threat` | Green / Orange / Red / Black |
| `BII_Identifi_watchlist` | Looking-for |
| `BII_Identifi_family` / `associates` / `leads` / `notes` | Réseau / pistes |
| `BII_Identifi_bioKey` | Clé biométrique stable |
| `BII_Identifi_evidenceName` / `lead` / `linkedName` / `priority` | Preuves objets |
| `BII_Identifi_authoredEvidence` | Objet collectable SSE BII |
| `BII_Identifi_nodeName` | Nœud sync |

### Record local (ARRAY profil)

Index : `id, name, alias, nationality, org, threat, quality, grid, pos, operator, dtg, [bioKey, modes[]], evidence[], notes, watchlist, tick, quality2, extraHashMap`

### Modules Eden/Zeus

- **BII Identity Profile** → seed personnes  
- **BII Evidence / Lead** → objets  
- **BII Sync Node** → uplink DB  

### Webhook GSQN (hors scope Athena)

`BII_GSQN_HTTP` → `https://gsqn.net/api/bii` (clé serveur).  
La passerelle COMSPEC **n’envoie pas** vers GSQN ; elle mappe vers le modèle SSE / Athena.

## Mapping SSE

| BII | SSE |
|-----|-----|
| name / alias / nationality / org | `identity` |
| threat | `profile` (CIVILIAN / INSURGENT / HVT) |
| bioKey + modes FACE/FP/IRIS | `biometrics` |
| family / associates / leads / notes | `intel` + `associations` |
| evidence[] | `chainOfCustody` (+ `documents` si lead) |
| authored evidence object | `makeSearchable` OBJECT |

## Addon `compat_bii`

Soft-load (BII optionnel) :

- Alias matériel `BII_Identifi_Device` pour seek / fingerprint / face / dna / terminal  
- Hooks (wrap) : `processScan`, `collectEvidence`, modules identity/evidence  
- Import vars à `ensureGenerated` + export SSE → vars BII (réglable CBA)

### Réglages CBA

- **Passerelle BII Identifi** (`comspec_sse_biiBridgeEnabled`)  
- **Exporter SSE → variables BII** (`comspec_sse_biiExportToBii`)

### API

```sqf
[] call comspec_sse_fnc_biiIsPresent;
[_unit] call comspec_sse_fnc_biiImportEntityVars;
[_unit] call comspec_sse_fnc_biiExportEntityVars;
[_unit, _biiRecord] call comspec_sse_fnc_biiRecordToSse;
[_object] call comspec_sse_fnc_biiImportObject;
```

## Build

Inclure `compat_bii` dans `build_pbo.bat` → `comspec_sse_compat_bii.pbo`.  
Charger `@COMSPEC_SSE` **avec** le pack S.O.A.R (BII_Identifi).
