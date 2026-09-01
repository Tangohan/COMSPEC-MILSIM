# Modèle de données SSE

Variable entité : `comspec_sse_data` (ARRAY de paires, synchronisée).

## Structure racine

| Clé | Type | Description |
|-----|------|-------------|
| uid | STRING | Ex. `SSE-26-000184` |
| type | STRING | PERSON, PHONE, SMARTPHONE, DOCUMENT, VEHICLE, OBJECT… |
| classification | STRING | UNCLASSIFIED (défaut) |
| profile | STRING | INSURGENT, CIVILIAN… |
| complexity | STRING | LIGHT / STANDARD / DETAILED / HIGH_VALUE |
| seed | NUMBER | Seed déterministe |
| generated | BOOL | Contenu généré |
| lazyReady | BOOL | Cache lazy rempli |
| state | STRING | UNTOUCHED → TRANSMITTED |
| searched / exploited | BOOL | Raccourcis d'état |
| createdBy | STRING | ZEUS / EDEN / SCRIPT / LAZY / AUTO |
| sections | HASHMAP | Voir ci-dessous |
| revealed | HASHMAP | Fog of war par action |
| clusterId / networkId | STRING | Liens narratifs |

## Sections

- `identity` — name, alias, nationality, role, phone…
- `biometrics` — fingerprintId, irisId, dnaId, facePhoto, qualities
- `documents` — array de docs `{uid,title,summary,grid}`
- `digitalDevices` — array d'appareils (voir DIGITAL-EXPLOITATION)
- `communications`, `associations`, `locations`
- `vehicle`, `weapons`, `equipment`
- `notes`, `photos`, `intel`
- `metadata` — version, noiseProbability…
- `chainOfCustody` — preuves collectées
- `sectionStatus` — identity/biometrics/digital/documents : none|partial|complete

## Exemple setVariable

```sqf
private _data = ["PERSON", "ZEUS", "INSURGENT", "DETAILED"] call comspec_sse_fnc_createDataModel;
[_unit, _data] call comspec_sse_fnc_setData;
```
