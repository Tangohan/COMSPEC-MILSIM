# UI multi-écrans SSE (V0.7)

Tous les écrans partagent le **même SSE Record** (`comspec_sse_uiRecord` via `uiSetRecord` / `uiGetRecord`).

## Écrans

| Écran | Ouverture | Rôle |
|-------|-----------|------|
| Terminal SSE terrain | ACE Self / ACE cible / item Terminal | Hub : éléments, dossiers, transmission |
| SEEK II | Terminal → SEEK / ACE biométrie | Photo, empreintes, iris, identité, score, qualité |
| Digital Exploitation | Terminal → DIGITAL | Onglets Overview → Network |
| Site Exploitation | Terminal → SITE | % exploitation, pièces/objets, triage |
| Intelligence Graph | Terminal → GRAPH | Nœuds + relations + pivot |
| Evidence / Chain of Custody | Terminal → PREUVES | Preuves, scellés, chaîne |
| Mission Intel | Terminal → MISSION | Fusion OBSERVED / REPORTED / ASSESSED / CONFIRMED |
| Zeus SSE Control | Module Zeus / bouton ZEUS | Vérité vs connu joueurs, générer, lier, export, AAR |

## API

```sqf
[_entity] call comspec_sse_fnc_uiOpenTerminal;
["digital"] call comspec_sse_fnc_uiOpenScreen;
[_entity] call comspec_sse_fnc_uiSetRecord;
[] call comspec_sse_fnc_uiGetRecord;
```

## Item

- `COMSPEC_SSE_Terminal` — Terminal SSE terrain (kit SSE + alias rôle `terminal`)
