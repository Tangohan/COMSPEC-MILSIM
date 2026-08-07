# COMSPEC SSE

Addon Arma 3 autonome — **Sensitive Site Exploitation** (CBA + ACE3).

Version actuelle : **V0.7.0**

## Contenu

| Version | Contenu |
|---------|---------|
| V0.1 | Core, générateur, Zeus/Eden, ACE inspect, items, modèles |
| V0.2 | Digital téléphone + PC + USB |
| V0.3 | SEEK II + biométrie |
| V0.4 | Athena / Overwatch / offline queue |
| V0.4.1 | Compat items multi-mods (cTab, ACE, alias CBA) |
| V0.5 | Véhicules, radios, armes, bâtiments + kit SSE |
| V0.6 | Moteur intel : niveaux, triage, pivot, fusion, Zeus Site Manager |
| V0.7 | UI multi-écrans liés au même SSE Record |

## Prérequis

- Arma 3 + CBA_A3 + ACE3
- Optionnel : COMSPEC Overwatch

## Installation

Voir [docs/INSTALLATION.md](docs/INSTALLATION.md)

## API rapide

```sqf
[_unit, "INSURGENT", "DETAILED"] call comspec_sse_fnc_generateData;
[_unit, "builtin_chef_hvt"] call comspec_sse_fnc_applyModel;
[_unit] call comspec_sse_fnc_uiOpenTerminal;
[_unit] call comspec_sse_fnc_openSeek;
[_phone, player, "full"] call comspec_sse_fnc_exploitDevice;
["SSE-2026-0007"] call comspec_sse_fnc_setCaseReference;
[_unit] call comspec_sse_fnc_submitPersonRecord;
```

Documentation : [docs/README.md](docs/README.md)
