# Network / Offline — V0.4

## Flux

```
submitPersonRecord / submitBiometricsSim / submitDigitalAcquisition / submitRecord
  → buildAthena*Payload (+ idempotency_key + case_reference)
  → missionRecords (MISSION)
  → online ? sendViaOverwatch : queueOffline (QUEUED)
  → flush automatique ~45 s
```

## Adapter Overwatch

Ordre de tentative :

1. Extension `COMSPECExtension` commande typée (`SubmitSsePerson`, `SubmitSseBiometricsSim`, `SendSSE`)
2. `comspec_overwatch_connect_fnc_sendIntel` (HUMINT résumé)
3. Extension `SendSSE` générique

## Réglages CBA

- Identifiant de mission
- Référence dossier SSE (`SSE-2026-0007`)
- Identifiant carte / mapId
- Préférer l'extension COMSPEC

## API

```sqf
["SSE-2026-0007"] call comspec_sse_fnc_setCaseReference;
[_unit] call comspec_sse_fnc_submitPersonRecord;
[_unit, _bioBundle] call comspec_sse_fnc_submitBiometricsSim;
[_phone, _fog] call comspec_sse_fnc_submitDigitalAcquisition;
[] call comspec_sse_fnc_flushQueue;
```

Gameplay **jamais** bloqué sans serveur.
