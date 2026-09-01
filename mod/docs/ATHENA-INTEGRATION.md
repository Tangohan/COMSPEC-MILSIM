# Intégration Athena — V0.4

Aligné sur le contrat Overwatch `POST /api/sse/persons` et biométrie simulée.

## Payloads

| Fonction | Cible Athena |
|----------|----------------|
| `buildAthenaPersonPayload` | `POST /api/sse/persons` |
| `buildAthenaBiometricsPayload` | `POST .../biometrics-sim` |
| `buildAthenaDigitalPayload` | canal SSE / labnum (`SendSSE`) |

## Champs personne (extrait)

- mapId, status, last_name, first_name, alias
- nationality, language_spoken, affiliation
- capture_pos_*, grid_reference
- submitter_callsign, target_unit_netid
- sse_uid, case_reference, idempotency_key
- schema: `comspec_sse_athena_person_v0.4`

## Extension

Commandes tentées :

- `SubmitSsePerson`
- `SubmitSseBiometricsSim`
- `SendSSE`

Si l'extension ne les implémente pas encore → fallback `sendIntel` Overwatch + file QUEUED.

## Exemple terrain

```sqf
["SSE-2026-0007"] call comspec_sse_fnc_setCaseReference;
[_hvt] call comspec_sse_fnc_submitPersonRecord;
[_hvt] call comspec_sse_fnc_openSeek;
// ... captures ...
[] call comspec_sse_fnc_seekTransmit; // submitBiometricsSim
```
